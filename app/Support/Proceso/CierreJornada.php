<?php

namespace App\Support\Proceso;

use App\Http\Controllers\Admin\CosechaController;
use App\Models\AvisoPesca;
use App\Models\Bitacora;
use App\Models\Cosecha;
use App\Models\Jornada;
use App\Models\JornadaLago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tratamiento;
use App\Models\User;
use App\Support\Formato;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cierre de una jornada (docs/diseno/dia-de-proceso.md, temas 2 a 4). En una sola transacción:
 * - bloquea la jornada y, si ya no está abierta (cerrada, cancelada, doble clic), no hace nada;
 * - bloquea las siembras activas de sus lagos y vuelve a revisar el retiro de tratamiento a la fecha de la jornada;
 * - por cada siembra crea una cosecha normal ligada a la jornada (así la conversión, la supervivencia, el historial,
 *   los informes y las ventas siguen igual) con los kg del reparto, ajustado o no por el administrador;
 * - «Quedó vacío»: cierra todas las siembras del lago; la cosecha lleva todos los peces vivos y la diferencia con
 *   kg ÷ peso promedio queda en el cierre y en la bitácora.
 * - «Quedó pescado»: resta kg ÷ peso promedio; el peso promedio no cambia. Si el administrador estima los kg que
 *   quedan, los peces que quedan son esos kg ÷ peso promedio y la diferencia queda registrada.
 * - atiende los avisos «listo para pescar» de esos lagos y deja todo en la bitácora.
 */
final class CierreJornada
{
    /**
     * @param  array<int|string, array{resultado?: string, kg?: array<int|string, mixed>, restante_kg?: array<int|string, mixed>}>  $entrada
     *                                                                                                                                        jornada_lago_id => resultado, kg por siembra (ajuste del reparto) y kg que quedan por siembra
     * @return ?Jornada la jornada cerrada, o null si ya no estaba abierta (no hizo nada)
     */
    public static function cerrar(Jornada $jornada, User $usuario, array $entrada): ?Jornada
    {
        return DB::transaction(function () use ($jornada, $usuario, $entrada) {
            $bloqueada = Jornada::query()->whereKey($jornada->id)->lockForUpdate()->first();
            if ($bloqueada === null || ! $bloqueada->estaAbierta()) {
                return null;
            }
            if (! $bloqueada->yaEsSuDia()) {
                throw ValidationException::withMessages(['jornada' => __('jornadas.errores.antes_de_su_dia')]);
            }

            $lagos = $bloqueada->lagos()->with(['lago', 'pesajes'])->get();
            if ($lagos->isEmpty() || $lagos->every(fn (JornadaLago $jl) => $jl->pesajes->isEmpty())) {
                throw ValidationException::withMessages(['jornada' => __('jornadas.errores.sin_pesajes')]);
            }

            // Siembras activas de todos sus lagos, bloqueadas en orden de id (dos cierres no se cruzan)
            $activas = Siembra::query()->whereIn('lago_id', $lagos->pluck('lago_id'))->whereNull('finalizada_at')
                ->orderBy('id')->lockForUpdate()->get()->groupBy('lago_id');

            self::revisarRetiro($bloqueada, $lagos);

            $kgTotal = 0.0;
            foreach ($lagos as $jornadaLago) {
                $kgTotal += self::cerrarLago($bloqueada, $jornadaLago, $activas->get($jornadaLago->lago_id, collect()), $entrada[$jornadaLago->id] ?? [], $usuario);
            }

            $bloqueada->update(['estado' => Jornada::CERRADA, 'cerrada_at' => now(), 'cerrada_por' => $usuario->id]);
            Bitacora::registrar($usuario, 'jornada_cerrada', null, [Rol::ADMINISTRADOR], [
                'fecha' => $bloqueada->fecha->toDateString(), 'lagos' => $lagos->count(), 'kg' => round($kgTotal, 2),
            ]);

            return $bloqueada;
        });
    }

    /** El retiro pudo registrarse después de agregar el lago: se revisa otra vez al cerrar. */
    private static function revisarRetiro(Jornada $jornada, Collection $lagos): void
    {
        foreach ($lagos as $jornadaLago) {
            $retiro = Tratamiento::query()->where('lago_id', $jornadaLago->lago_id)->enRetiro($jornada->fecha)->get()
                ->sortByDesc(fn (Tratamiento $t) => $t->finRetiro())->first();
            if ($retiro) {
                throw ValidationException::withMessages(['jornada' => __('jornadas.errores.lago_en_retiro', [
                    'lago' => $jornadaLago->lago->nombre, 'fecha' => $retiro->finRetiro()->format('d/m/Y'), 'producto' => $retiro->producto,
                ])]);
            }
        }
    }

    /**
     * @param  Collection<int, Siembra>  $activas  siembras activas del lago, ya bloqueadas
     * @return float kg enteros cosechados en el lago
     */
    private static function cerrarLago(Jornada $jornada, JornadaLago $jornadaLago, Collection $activas, array $entrada, User $usuario): float
    {
        $lago = $jornadaLago->lago;
        $campo = "lagos.{$jornadaLago->id}";
        $resultado = $entrada['resultado'] ?? null;
        if (! in_array($resultado, JornadaLago::RESULTADOS, true)) {
            throw ValidationException::withMessages(["{$campo}.resultado" => __('jornadas.errores.elige_resultado', ['lago' => $lago->nombre])]);
        }

        $siembras = RepartoCosecha::siembrasDelDia($jornada, $activas);
        $propuesta = RepartoCosecha::propuestaLago($jornadaLago, $siembras);
        if ($propuesta['sin_siembra'] !== []) {
            throw ValidationException::withMessages([$campo => __('jornadas.errores.especie_sin_siembra', ['lago' => $lago->nombre])]);
        }

        $kg = self::kgPorSiembra($propuesta, $entrada['kg'] ?? [], $campo, $lago->nombre);
        $vacio = $resultado === JornadaLago::VACIO;
        $detalle = [];
        $kgLago = 0.0;
        $diferenciaLago = 0;

        foreach ($siembras as $siembra) {
            $kgSiembra = $kg[$siembra->id];
            $antes = (int) $siembra->peces_vivos;
            $calculados = RepartoCosecha::peces($kgSiembra, $siembra);
            if ($calculados === null) {
                throw ValidationException::withMessages(["{$campo}.kg.{$siembra->id}" => __('jornadas.errores.sin_peso_promedio', ['especie' => $siembra->nombre_especie, 'lago' => $lago->nombre])]);
            }

            $restanteKg = self::kgOpcional($entrada['restante_kg'][$siembra->id] ?? null);
            if ($vacio) {
                // Sale todo lo que había: la cosecha lleva los peces vivos; la diferencia es lo que no cuadró
                $cosechados = $kgSiembra > 0 ? $antes : 0;
                $quedan = 0;
                $diferencia = $antes - $calculados;
            } else {
                if ($calculados > $antes) {
                    throw ValidationException::withMessages(["{$campo}.kg.{$siembra->id}" => __('jornadas.errores.mas_peces_que_vivos', [
                        'especie' => $siembra->nombre_especie, 'lago' => $lago->nombre, 'calculados' => Formato::numero($calculados), 'vivos' => Formato::numero($antes),
                    ])]);
                }
                $cosechados = $calculados;
                $quedan = $antes - $calculados;
                $diferencia = 0;
                if ($restanteKg !== null) {
                    $estimados = RepartoCosecha::peces($restanteKg, $siembra) ?? 0;
                    if ($estimados > $quedan) {
                        throw ValidationException::withMessages(["{$campo}.restante_kg.{$siembra->id}" => __('jornadas.errores.quedan_mas_que_antes', [
                            'especie' => $siembra->nombre_especie, 'lago' => $lago->nombre, 'estimados' => Formato::numero($estimados), 'quedan' => Formato::numero($quedan),
                        ])]);
                    }
                    $diferencia = $quedan - $estimados;
                    $quedan = $estimados;
                }
            }

            if ($cosechados > 0) {
                $pesoPromedio = round($kgSiembra * 1000 / $cosechados, 2);
                if ($pesoPromedio > CosechaController::PESO_MAXIMO_PEZ_G) {
                    throw ValidationException::withMessages(["{$campo}.kg.{$siembra->id}" => __('jornadas.errores.peso_promedio_irreal', [
                        'especie' => $siembra->nombre_especie, 'lago' => $lago->nombre, 'gramos' => Formato::numero($pesoPromedio), 'maximo' => Formato::numero(CosechaController::PESO_MAXIMO_PEZ_G),
                    ])]);
                }
                Cosecha::create([
                    'siembra_id' => $siembra->id, 'lago_id' => $lago->id, 'jornada_id' => $jornada->id,
                    'user_id' => $usuario->id, 'despachado_por_id' => $usuario->id,
                    'fecha' => $jornada->fecha->toDateString(), 'peces' => $cosechados, 'kg' => $kgSiembra, 'peso_promedio_g' => $pesoPromedio,
                ]);
                Bitacora::registrar($usuario, 'cosecha', $lago, [Rol::ADMINISTRADOR], [
                    'peces' => $cosechados, 'kg' => (float) $kgSiembra, 'especie' => $siembra->nombre_especie,
                    'destino' => __('jornadas.destino_cosecha', ['fecha' => $jornada->fecha->format('d/m')]), 'fecha' => $jornada->fecha->toDateString(),
                ]);
            }

            $siembra->peces_vivos = $quedan;
            $siembra->biomasa_kg = ($quedan * (float) $siembra->peso_actual_g) / 1000;
            if ($quedan === 0) {
                $siembra->finalizada_at = now();
            }
            $siembra->save();

            $kgLago += $kgSiembra;
            $diferenciaLago += $diferencia;
            $detalle[] = [
                'siembra_id' => $siembra->id, 'especie' => $siembra->nombre_especie, 'kg' => $kgSiembra,
                'peces_antes' => $antes, 'peces_calculados' => $calculados, 'peces_cosechados' => $cosechados,
                'peces_quedan' => $quedan, 'restante_kg' => $restanteKg, 'diferencia' => $diferencia,
            ];
        }

        $jornadaLago->update(['resultado' => $resultado, 'cierre' => ['siembras' => $detalle]]);

        // La pesca atiende los avisos «listo para pescar» del lago
        AvisoPesca::query()->pendientes()->where('lago_id', $lago->id)
            ->update(['estado' => AvisoPesca::ATENDIDO, 'atendido_at' => now(), 'atendido_por' => $usuario->id]);

        Bitacora::registrar($usuario, 'jornada_lago_cerrado', $lago, [Rol::ADMINISTRADOR], [
            'resultado' => __('jornadas.resultados.'.$resultado), 'kg' => round($kgLago, 2), 'diferencia' => $diferenciaLago,
            'fecha' => $jornada->fecha->toDateString(),
        ]);

        return $kgLago;
    }

    /**
     * Kg de cada siembra: los del reparto según la biomasa, o los que escribió el administrador. Lo escrito debe sumar
     * exacto el entero de cada especie (en centésimas).
     *
     * @return array<int, float> siembra_id => kg
     */
    private static function kgPorSiembra(array $propuesta, array $escritos, string $campo, string $lago): array
    {
        $kg = [];
        foreach ($propuesta['filas'] as $fila) {
            $id = $fila['siembra']->id;
            $valor = array_key_exists($id, $escritos) ? self::kgOpcional($escritos[$id]) : null;
            $kg[$id] = $valor ?? $fila['kg'];
        }

        foreach ($propuesta['totales'] as $especieId => $total) {
            $filas = collect($propuesta['filas'])->filter(fn ($fila) => $fila['siembra']->especie_id === $especieId);
            $suma = $filas->sum(fn ($fila) => (int) round($kg[$fila['siembra']->id] * 100));
            if ($suma !== (int) round($total * 100)) {
                throw ValidationException::withMessages([$campo => __('jornadas.errores.reparto_no_suma', [
                    'lago' => $lago, 'especie' => $filas->first()['siembra']->nombre_especie,
                    'suma' => Formato::sinCeros($suma / 100), 'total' => Formato::sinCeros($total),
                ])]);
            }
        }

        // Una siembra cuya especie no se pesó no recibe kg
        foreach ($propuesta['filas'] as $fila) {
            if (! array_key_exists((int) $fila['siembra']->especie_id, $propuesta['totales']) && $kg[$fila['siembra']->id] > 0) {
                throw ValidationException::withMessages(["{$campo}.kg.{$fila['siembra']->id}" => __('jornadas.errores.especie_no_pesada', ['especie' => $fila['siembra']->nombre_especie])]);
            }
        }

        return $kg;
    }

    /** Un número de kg escrito (≥ 0, centésimas), o null si se dejó vacío. */
    private static function kgOpcional(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (! is_numeric($valor) || (float) $valor < 0 || (float) $valor > 9999999) {
            throw ValidationException::withMessages(['jornada' => __('jornadas.errores.kg_invalido')]);
        }

        return round((float) $valor, 2);
    }
}
