<?php

namespace App\Support\Campo;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Ronda;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\Turno;
use App\Models\User;
use App\Support\CalculadoraRacion;
use App\Support\Hora;
use App\Support\LotesPorEspecie;
use App\Support\Region;
use App\Support\UnidadesPeso;
use Illuminate\Support\Collection;

/**
 * «Paquete de trabajo» para registrar sin conexión (pantalla Registro rápido, /campo): lo mínimo para armar los
 * formularios de campo en el teléfono, solo de la finca activa (TenantScope) y solo con las secciones del rol.
 * - Trabajador: ración, mortalidad y aviso de pesca. Administrador: ración, mortalidad y muestreo. Celador: ronda y medición.
 *   Propietario: ninguna (solo consulta).
 * - Sin datos de personal, dinero ni de otros roles. Lagos: hasta config('sin_conexion.max_lagos'), por nombre.
 * El servidor vuelve a revisar todo al recibir cada registro (turno, bodega, peces vivos): esto solo arma la pantalla.
 */
final class PaqueteTrabajo
{
    public const RACION = 'racion';

    public const MORTALIDAD = 'mortalidad';

    public const MUESTREO = 'muestreo';

    public const AVISO_PESCA = 'aviso_pesca';

    public const RONDA = 'ronda';

    public const MEDICION = 'medicion';

    /** Rangos de la medición del agua: los mismos que valida MedicionAguaController (lo comprueba PaqueteTrabajoTest). */
    public const RANGOS_MEDICION = [
        'temperatura_c' => [0, 45, 0.1],
        'oxigeno_mg_l' => [0, 30, 0.01],
        'ph' => [0, 14, 0.01],
        'amonio_mg_l' => [0, 100, 0.01],
        'nitritos_mg_l' => [0, 100, 0.01],
        'transparencia_cm' => [0, 500, 1],
    ];

    /** Límites del muestreo: los mismos que valida MuestreoController (lo comprueba RegistroRapidoMuestreoTest). */
    public const RANGOS_MUESTREO = [
        'peces_pesados' => [1, 100000],
        'peso_total_g' => [0.1, 999999],
    ];

    /** Secciones que el administrador registra sin turno. */
    private const SIN_TURNO_ADMINISTRADOR = [self::RACION, self::MORTALIDAD, self::MUESTREO];

    /** Horas de turnos que viajan en el paquete (solo para avisar «fuera de turno»; el servidor decide). */
    private const HORAS_TURNOS_ATRAS = 12;

    private const HORAS_TURNOS_ADELANTE = 48;

    /** @return list<string> Secciones que puede registrar la persona según sus roles */
    public static function secciones(User $usuario): array
    {
        return array_values(array_filter([
            $usuario->tieneRol(Rol::ADMINISTRADOR, Rol::TRABAJADOR) ? self::RACION : null,
            $usuario->tieneRol(Rol::ADMINISTRADOR, Rol::TRABAJADOR) ? self::MORTALIDAD : null,
            $usuario->tieneRol(Rol::ADMINISTRADOR) ? self::MUESTREO : null,
            $usuario->tieneRol(Rol::TRABAJADOR) ? self::AVISO_PESCA : null,
            $usuario->tieneRol(Rol::CELADOR) ? self::RONDA : null,
            $usuario->tieneRol(Rol::CELADOR) ? self::MEDICION : null,
        ]));
    }

    /** @return array<string, mixed> */
    public static function para(User $usuario): array
    {
        $usuario->loadMissing(['roles', 'tenant']);
        $secciones = self::secciones($usuario);
        $tiene = fn (string ...$cuales) => array_intersect($cuales, $secciones) !== [];
        $maximo = (int) config('sin_conexion.max_lagos');

        // Celador: todos los lagos (ronda y medición). Ración, mortalidad y aviso: solo lagos con siembra activa.
        $lagos = $secciones === [] ? collect() : Lago::query()
            ->when(! $tiene(self::RONDA, self::MEDICION), fn ($consulta) => $consulta->whereHas('siembrasActivas'))
            ->with(['siembrasActivas.especie', 'siembrasActivas.lagoOrigen', 'ultimaMedicion'])
            ->when($tiene(self::AVISO_PESCA), fn ($consulta) => $consulta->withExists('avisoPescaPendiente as aviso_pendiente'))
            ->orderBy('nombre')
            ->limit($maximo + 1)
            ->get();
        $truncado = $lagos->count() > $maximo;

        return [
            'version' => 1,
            'generado_at' => now()->utc()->toIso8601String(),
            'clave' => $usuario->tenant_id.'|'.$usuario->id,
            'finca' => ['nombre' => $usuario->tenant?->nombre, 'zona_horaria' => Region::zona()],
            'usuario' => ['nombre' => $usuario->name],
            // Formato de hora de la persona (12 o 24), ya resuelto: el teléfono lo usa sin conexión
            'formato_hora' => Hora::de($usuario),
            'secciones' => $secciones,
            'horas_limite' => (int) config('sin_conexion.horas'),
            'lagos' => self::lagos($lagos->take($maximo), $tiene),
            'lagos_truncados' => $truncado,
            'max_lagos' => $maximo,
            'concentrados' => $tiene(self::RACION) ? self::concentrados() : [],
            'tipos_ronda' => $tiene(self::RONDA) ? collect(Ronda::opciones())->map(fn ($texto, $codigo) => ['codigo' => $codigo, 'texto' => $texto])->values()->all() : [],
            'sin_novedad' => Ronda::SIN_NOVEDAD,
            'medicion' => $tiene(self::MEDICION) ? self::medicion() : [],
            // Límites en gramos (después de convertir), unidades con sus factores exactos (los mismos de UnidadesPeso) y la
            // unidad por defecto de la finca. Un teléfono con un paquete viejo, sin unidades, sigue en gramos.
            'muestreo' => $tiene(self::MUESTREO) ? collect(self::RANGOS_MUESTREO)->map(fn (array $rango) => ['min' => $rango[0], 'max' => $rango[1]])->all() + [
                'unidades' => UnidadesPeso::UNIDADES,
                'factores' => UnidadesPeso::FACTORES,
                'unidad_defecto' => UnidadesPeso::valida($usuario->tenant?->unidad_peso_muestreo) ? $usuario->tenant->unidad_peso_muestreo : UnidadesPeso::GRAMOS,
            ] : [],
            'turnos' => self::turnos($usuario, $secciones),
            // Secciones que exigen turno a esta persona (el administrador registra ración, mortalidad y muestreo sin turno)
            'exige_turno' => collect($secciones)->mapWithKeys(fn (string $seccion) => [$seccion => ! (in_array($seccion, self::SIN_TURNO_ADMINISTRADOR, true) && $usuario->tieneRol(Rol::ADMINISTRADOR))])->all(),
            'urls' => $secciones === [] ? [] : array_filter([
                'ronda' => $tiene(self::RONDA) ? route('noche.rondas.store', absolute: false) : null,
            ]),
        ];
    }

    /** @param  Collection<int, Lago>  $lagos */
    private static function lagos(Collection $lagos, \Closure $tiene): array
    {
        return $lagos->map(function (Lago $lago) use ($tiene) {
            // Con varios lotes de la misma especie: número del lote dentro de su especie y su origen, para elegirlo
            $numeros = LotesPorEspecie::numeros($lago->siembrasActivas);
            $porEspecie = $lago->siembrasActivas->countBy(fn (Siembra $s) => (string) ($s->especie_id ?? $s->nombre_especie));

            return self::lago($lago, $tiene, fn (Siembra $siembra) => $porEspecie[(string) ($siembra->especie_id ?? $siembra->nombre_especie)] > 1
                ? ['lote' => $numeros[$siembra->id], 'origen' => $siembra->textoOrigen()]
                : []);
        })->values()->all();
    }

    /** @param  \Closure(Siembra): array<string, mixed>  $lote */
    private static function lago(Lago $lago, \Closure $tiene, \Closure $lote): array
    {
        return array_filter([
            'id' => $lago->id,
            'nombre' => $lago->nombre,
            'siembras' => $lago->siembrasActivas->map(fn (Siembra $siembra) => array_filter([
                'id' => $siembra->id,
                'especie' => $siembra->nombre_especie,
                'fecha' => $siembra->fecha->format('Y-m-d'),
                'peces_vivos' => (int) $siembra->peces_vivos,
            ] + $lote($siembra) + [
                'url_mortalidad' => $tiene(self::MORTALIDAD) ? route('admin.mortalidades.store', $siembra->id, absolute: false) : null,
                // Muestreo: el peso de ahora (para mostrar la diferencia; la que vale la calcula el servidor al llegar)
                'peso_actual_g' => $tiene(self::MUESTREO) ? round((float) $siembra->peso_actual_g, 2) : null,
                'url_muestreo' => $tiene(self::MUESTREO) ? route('admin.muestreos.store', $siembra->id, absolute: false) : null,
            ], fn ($valor) => $valor !== null))->values()->all(),
            'racion_sugerida_kg' => $tiene(self::RACION) && $lago->siembrasActivas->isNotEmpty() ? CalculadoraRacion::paraLago($lago)['kg_por_toma'] : null,
            'aviso_pendiente' => $tiene(self::AVISO_PESCA) ? (bool) $lago->aviso_pendiente : null,
            'url_racion' => $tiene(self::RACION) && $lago->siembrasActivas->isNotEmpty() ? route('admin.raciones.store', $lago->id, absolute: false) : null,
            'url_aviso_pesca' => $tiene(self::AVISO_PESCA) && $lago->siembrasActivas->isNotEmpty() ? route('admin.avisos-pesca.store', $lago, absolute: false) : null,
            'url_medicion' => $tiene(self::MEDICION) ? route('noche.mediciones.store', $lago, absolute: false) : null,
        ], fn ($valor) => $valor !== null);
    }

    /** Concentrados con existencias (lo recibido en bodega). Solo informativo: el servidor descuenta al llegar. */
    private static function concentrados(): array
    {
        return StockAlimento::query()->where('cantidad_kg', '>', 0)->orderBy('tipo_concentrado')->get()
            ->map(fn (StockAlimento $stock) => ['tipo' => $stock->tipo_concentrado, 'kg' => round((float) $stock->cantidad_kg, 2)])
            ->all();
    }

    private static function medicion(): array
    {
        return collect(self::RANGOS_MEDICION)->map(fn (array $rango, string $campo) => [
            'campo' => $campo,
            'etiqueta' => __('campo.medicion.'.$campo),
            'min' => $rango[0],
            'max' => $rango[1],
            'paso' => $rango[2],
        ])->values()->all();
    }

    /** Turnos de la persona cercanos a hoy, solo de los roles de campo que registra. */
    private static function turnos(User $usuario, array $secciones): array
    {
        $roles = array_values(array_filter([
            array_intersect([self::RACION, self::MORTALIDAD, self::AVISO_PESCA], $secciones) !== [] && $usuario->tieneRol(Rol::TRABAJADOR) ? Rol::TRABAJADOR : null,
            in_array(self::RONDA, $secciones, true) ? Rol::CELADOR : null,
        ]));
        if ($roles === []) {
            return [];
        }

        return Turno::query()
            ->where('user_id', $usuario->id)
            ->whereIn('rol', $roles)
            ->where('termina_at', '>=', now()->subHours(self::HORAS_TURNOS_ATRAS))
            ->where('inicia_at', '<=', now()->addHours(self::HORAS_TURNOS_ADELANTE))
            ->orderBy('inicia_at')
            ->get()
            ->map(fn (Turno $turno) => [
                'rol' => $turno->rol,
                'inicia_at' => $turno->inicia_at->utc()->toIso8601String(),
                'termina_at' => $turno->termina_at->utc()->toIso8601String(),
            ])
            ->all();
    }
}
