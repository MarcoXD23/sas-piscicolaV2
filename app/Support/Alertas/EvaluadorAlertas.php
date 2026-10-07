<?php

namespace App\Support\Alertas;

use App\Models\Alerta;
use App\Models\Lago;
use App\Models\Mortalidad;
use App\Models\Rol;
use App\Models\User;
use App\Support\CalculadoraRacion;
use App\Support\CoberturaBodega;
use App\Support\Region;
use App\Support\TenantActual;
use Illuminate\Support\Facades\DB;

/**
 * Revisa las condiciones de alerta de la finca actual y mantiene el historial:
 * abre las nuevas, actualiza las que siguen abiertas (sin repetirlas) y cierra las que ya no se cumplen.
 * Las críticas se avisan una sola vez a los propietarios, por cada canal disponible.
 *
 * Fuentes (sin duplicar reglas): CoberturaBodega para el stock, MedicionAgua::problemas() para el agua
 * y los umbrales de la finca (Ajustes) para el stock y la mortalidad.
 */
class EvaluadorAlertas
{
    /** Evalúa una finca concreta (por ejemplo desde el comando programado o después de guardar un registro). */
    public static function paraFinca(int $tenantId): void
    {
        $tenantActual = app(TenantActual::class);
        $anterior = $tenantActual->id();
        $tenantActual->establecer($tenantId);

        try {
            self::evaluar();
        } finally {
            $tenantActual->establecer($anterior);
        }
    }

    /** Evalúa la finca actual. */
    public static function evaluar(): void
    {
        $tenantId = app(TenantActual::class)->id();
        if ($tenantId === null) {
            return;
        }

        $condiciones = collect([...self::stock(), ...self::mortalidad(), ...self::agua()])
            ->keyBy(fn ($condicion) => $condicion['tipo'].'|'.$condicion['referencia']);

        $porAvisar = DB::transaction(function () use ($tenantId, $condiciones) {
            // Una sola evaluación a la vez por finca (evita abrir dos veces la misma alerta)
            DB::select('SELECT pg_advisory_xact_lock(?)', [$tenantId]);

            $abiertas = Alerta::query()->abiertas()->get()->keyBy(fn (Alerta $alerta) => $alerta->tipo.'|'.$alerta->referencia);
            $ahora = now();

            foreach ($abiertas as $clave => $alerta) {
                if (! $condiciones->has($clave)) {
                    $alerta->update(['cerrada_at' => $ahora]);
                }
            }

            foreach ($condiciones as $clave => $condicion) {
                $alerta = $abiertas->get($clave);
                if ($alerta === null) {
                    Alerta::create($condicion + ['abierta_at' => $ahora]);
                } else {
                    // Sigue abierta: no se repite, solo se actualizan sus datos (y puede volverse crítica)
                    $alerta->update(['severidad' => $condicion['severidad'], 'datos' => $condicion['datos']]);
                }
            }

            return Alerta::query()->abiertas()->where('severidad', Alerta::CRITICA)->whereNull('notificada_at')->get();
        });

        if ($porAvisar->isNotEmpty()) {
            self::avisar($porAvisar);
        }
    }

    /** @param  iterable<Alerta>  $alertas */
    private static function avisar(iterable $alertas): void
    {
        $propietarios = User::query()->whereNotNull('aprobado_at')
            ->whereHas('roles', fn ($consulta) => $consulta->where('nombre', Rol::PROPIETARIO))->get();
        $canales = collect(app()->tagged('canales_notificacion'))->filter(fn (CanalNotificacion $canal) => $canal->disponible());

        foreach ($alertas as $alerta) {
            foreach ($propietarios as $propietario) {
                foreach ($canales as $canal) {
                    $canal->enviar($propietario, $alerta);
                }
            }
            $alerta->update(['notificada_at' => now()]);
        }
    }

    /** @return list<array{tipo: string, referencia: string, severidad: string, datos: array<string, mixed>}> */
    private static function stock(): array
    {
        $minimo = CoberturaBodega::diasMinimos();

        return CoberturaBodega::alertas()->map(fn (array $item) => [
            'tipo' => Alerta::STOCK,
            'referencia' => $item['stock']->tipo_concentrado,
            'severidad' => $item['estado'] === CoberturaBodega::AGOTADO ? Alerta::CRITICA : Alerta::ADVERTENCIA,
            'datos' => [
                'concentrado' => $item['stock']->tipo_concentrado,
                'agotado' => $item['estado'] === CoberturaBodega::AGOTADO,
                'dias' => $item['dias'],
                'minimo' => $minimo,
            ],
        ])->all();
    }

    /**
     * Mortalidad del período sobre la población del lago (vivos de hoy más los que murieron en el período).
     * Crítica si llega al doble del umbral.
     *
     * @return list<array{tipo: string, referencia: string, severidad: string, datos: array<string, mixed>}>
     */
    private static function mortalidad(): array
    {
        $finca = Region::tenant();
        $umbral = (float) ($finca?->alerta_mortalidad_porcentaje ?? 3);
        $dias = (int) ($finca?->alerta_mortalidad_dias ?: 7);
        $desde = Region::hoy()->subDays($dias - 1)->toDateString();

        $muertosPorLago = Mortalidad::query()
            ->join('siembras', 'siembras.id', '=', 'mortalidades.siembra_id')
            ->whereNull('siembras.finalizada_at')
            ->where('mortalidades.fecha', '>=', $desde)
            ->groupBy('siembras.lago_id')
            ->selectRaw('siembras.lago_id, SUM(mortalidades.cantidad) AS total')
            ->pluck('total', 'siembras.lago_id');

        $condiciones = [];
        foreach (Lago::query()->whereIn('id', $muertosPorLago->keys())->withSum('siembrasActivas', 'peces_vivos')->get() as $lago) {
            $muertos = (int) $muertosPorLago[$lago->id];
            $poblacion = (int) $lago->siembras_activas_sum_peces_vivos + $muertos;
            $porcentaje = $poblacion > 0 ? round($muertos / $poblacion * 100, 2) : 0.0;

            if ($umbral > 0 && $porcentaje >= $umbral) {
                $condiciones[] = [
                    'tipo' => Alerta::MORTALIDAD,
                    'referencia' => (string) $lago->id,
                    'severidad' => $porcentaje >= $umbral * 2 ? Alerta::CRITICA : Alerta::ADVERTENCIA,
                    'datos' => ['lago' => $lago->nombre, 'lago_id' => $lago->id, 'muertos' => $muertos, 'porcentaje' => $porcentaje, 'dias' => $dias, 'umbral' => $umbral],
                ];
            }
        }

        return $condiciones;
    }

    /**
     * Última medición vigente de cada lago fuera de los rangos de sus especies. Crítica si el oxígeno está bajo.
     *
     * @return list<array{tipo: string, referencia: string, severidad: string, datos: array<string, mixed>}>
     */
    private static function agua(): array
    {
        $condiciones = [];
        $vigente = now()->subHours(CalculadoraRacion::HORAS_MEDICION_VIGENTE);

        foreach (Lago::query()->with(['siembrasActivas', 'ultimaMedicion'])->get() as $lago) {
            $medicion = $lago->ultimaMedicion;
            if ($medicion === null || $medicion->medido_at->lt($vigente)) {
                continue;
            }

            $problemas = $medicion->problemas($lago->siembrasActivas->pluck('especie'));
            if ($problemas !== []) {
                $condiciones[] = [
                    'tipo' => Alerta::AGUA,
                    'referencia' => (string) $lago->id,
                    'severidad' => in_array('oxigeno', array_column($problemas, 'parametro'), true) ? Alerta::CRITICA : Alerta::ADVERTENCIA,
                    'datos' => ['lago' => $lago->nombre, 'lago_id' => $lago->id, 'problemas' => array_column($problemas, 'texto'), 'medicion_id' => $medicion->id],
                ];
            }
        }

        return $condiciones;
    }
}
