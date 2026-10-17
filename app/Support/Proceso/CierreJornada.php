<?php

namespace App\Support\Proceso;

use App\Http\Controllers\Admin\CosechaController;
use App\Models\AvisoPesca;
use App\Models\Bitacora;
use App\Models\Cosecha;
use App\Models\Especie;
use App\Models\Jornada;
use App\Models\JornadaLago;
use App\Models\JornadaRendimiento;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tratamiento;
use App\Models\User;
use App\Support\Formato;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cierre de una jornada (docs/diseno/dia-de-proceso.md, temas 2 a 4, 6, 12 y 15.5). En una sola transacción:
 * - bloquea la jornada y, si ya no está abierta (cerrada, cancelada, doble clic), no hace nada;
 * - bloquea las siembras activas de sus lagos y vuelve a revisar el retiro de tratamiento a la fecha de la jornada;
 * - por cada siembra crea una cosecha normal ligada a la jornada (así la conversión, la supervivencia, el historial,
 *   los informes y las ventas siguen igual) con los kg del reparto, ajustado o no por el administrador. La
 *   «reproducción» se resta antes del reparto: esos peces vuelven al lago;
 * - «Quedó vacío»: cierra todas las siembras del lago; la cosecha lleva todos los peces vivos y la diferencia con
 *   kg ÷ peso promedio (mortalidad no registrada) queda en el cierre y en la bitácora.
 * - «Quedó pescado»: resta kg ÷ peso promedio. Si el administrador estima los kg que quedan, los peces que quedan son
 *   esos kg ÷ peso promedio y la diferencia queda registrada.
 * - Muestra al pescar (opcional, por siembra): el peso medido reemplaza al del registro en las cuentas; si el lago quedó
 *   pescado, queda como un muestreo normal (fecha de la jornada, usuario y bitácora) y actualiza el peso del lote.
 * - Cada cosecha guarda lo esperado (peces y peso del registro antes de pescar) y su parte del limpio de su especie,
 *   en proporción a sus kg; el rendimiento de cada especie queda en jornada_rendimientos, con su motivo si salió bajo.
 * - atiende los avisos «listo para pescar» de esos lagos y deja todo en la bitácora.
 */
final class CierreJornada
{
    /**
     * @param  array<int|string, array<string, mixed>>  $entrada  jornada_lago_id => resultado, kg (ajuste del reparto), restante_kg,
     *                                                            muestra_peces y muestra_g, cada uno por siembra
     * @param  array<int|string, array{motivo?: ?string}>  $rendimientos  especie_id => motivo del rendimiento bajo
     * @return ?Jornada la jornada cerrada, o null si ya no estaba abierta (no hizo nada)
     */
    public static function cerrar(Jornada $jornada, User $usuario, array $entrada, array $rendimientos = []): ?Jornada
    {
        return DB::transaction(function () use ($jornada, $usuario, $entrada, $rendimientos) {
            $bloqueada = Jornada::query()->whereKey($jornada->id)->lockForUpdate()->first();
            if ($bloqueada === null || ! $bloqueada->estaAbierta()) {
                return null;
            }
            if (! $bloqueada->yaEsSuDia()) {
                throw ValidationException::withMessages(['jornada' => __('jornadas.errores.antes_de_su_dia')]);
            }

            $bloqueada->load(['lagos.lago', 'lagos.pesajes', 'lagos.reproducciones', 'pesajesLimpio.presentacion']);
            $lagos = $bloqueada->lagos;
            if ($lagos->isEmpty() || $lagos->every(fn (JornadaLago $jl) => $jl->pesajes->isEmpty())) {
                throw ValidationException::withMessages(['jornada' => __('jornadas.errores.sin_pesajes')]);
            }

            $rendimiento = Rendimiento::deJornada($bloqueada);
            self::revisarLimpio($rendimiento);

            // Siembras activas de todos sus lagos, bloqueadas en orden de id (dos cierres no se cruzan)
            $activas = Siembra::query()->whereIn('lago_id', $lagos->pluck('lago_id'))->whereNull('finalizada_at')
                ->orderBy('id')->lockForUpdate()->get()->groupBy('lago_id');

            self::revisarRetiro($bloqueada, $lagos);

            $kgTotal = 0.0;
            $cosechas = [];
            foreach ($lagos as $jornadaLago) {
                [$kgLago, $delLago] = self::cerrarLago($bloqueada, $jornadaLago, $activas->get($jornadaLago->lago_id, collect()), $entrada[$jornadaLago->id] ?? [], $usuario);
                $kgTotal += $kgLago;
                array_push($cosechas, ...$delLago);
            }

            self::repartirLimpio($rendimiento, $cosechas);
            self::guardarRendimientos($bloqueada, $rendimiento, $rendimientos, $usuario);

            $bloqueada->update(['estado' => Jornada::CERRADA, 'cerrada_at' => now(), 'cerrada_por' => $usuario->id]);
            Bitacora::registrar($usuario, 'jornada_cerrada', null, [Rol::ADMINISTRADOR], [
                'fecha' => $bloqueada->fecha->toDateString(), 'lagos' => $lagos->count(), 'kg' => round($kgTotal, 2),
            ]);

            return $bloqueada;
        });
    }

    /** El limpio de una especie necesita su entero, y no puede pasar del entero que se procesó. */
    private static function revisarLimpio(array $rendimiento): void
    {
        if ($rendimiento['limpio_sin_entero'] !== []) {
            throw ValidationException::withMessages(['jornada' => __('jornadas.errores.limpio_sin_entero', [
                'especie' => Especie::query()->whereKey($rendimiento['limpio_sin_entero'][0])->value('nombre'),
            ])]);
        }
        foreach ($rendimiento['especies'] as $fila) {
            if ($fila['limpio'] !== null && $fila['limpio'] > $fila['base']) {
                throw ValidationException::withMessages(['jornada' => __('jornadas.errores.limpio_supera_entero', [
                    'especie' => Especie::query()->whereKey($fila['especie_id'])->value('nombre'),
                    'limpio' => Formato::sinCeros($fila['limpio']), 'entero' => Formato::sinCeros($fila['base']),
                ])]);
            }
        }
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
     * @return array{0: float, 1: list<Cosecha>} kg enteros cosechados en el lago y las cosechas creadas
     */
    private static function cerrarLago(Jornada $jornada, JornadaLago $jornadaLago, Collection $activas, array $entrada, User $usuario): array
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
        if ($propuesta['sin_entero'] !== []) {
            throw ValidationException::withMessages([$campo => __('jornadas.errores.reproduccion_supera_entero', ['lago' => $lago->nombre])]);
        }

        $kg = self::kgPorSiembra($propuesta, $entrada['kg'] ?? [], $campo, $lago->nombre);
        $vacio = $resultado === JornadaLago::VACIO;
        $detalle = [];
        $cosechas = [];
        $kgLago = 0.0;
        $diferenciaLago = 0;

        foreach ($siembras as $siembra) {
            $kgSiembra = $kg[$siembra->id];
            $antes = (int) $siembra->peces_vivos;
            $pesoRegistro = (float) $siembra->peso_actual_g;
            $muestra = self::muestra($entrada, $siembra->id, $campo);
            $pesoMedido = $muestra['promedio'] ?? null;
            $calculados = RepartoCosecha::peces($kgSiembra, $siembra, $pesoMedido);
            if ($calculados === null) {
                throw ValidationException::withMessages(["{$campo}.kg.{$siembra->id}" => __('jornadas.errores.sin_peso_promedio', ['especie' => $siembra->nombre_especie, 'lago' => $lago->nombre])]);
            }

            $restanteKg = self::kgOpcional($entrada['restante_kg'][$siembra->id] ?? null);
            if ($vacio) {
                // Sale todo lo que había: la cosecha lleva los peces vivos; la diferencia es la mortalidad no registrada
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
                    $estimados = RepartoCosecha::peces($restanteKg, $siembra, $pesoMedido) ?? 0;
                    if ($estimados > $quedan) {
                        throw ValidationException::withMessages(["{$campo}.restante_kg.{$siembra->id}" => __('jornadas.errores.quedan_mas_que_antes', [
                            'especie' => $siembra->nombre_especie, 'lago' => $lago->nombre, 'estimados' => Formato::numero($estimados), 'quedan' => Formato::numero($quedan),
                        ])]);
                    }
                    $diferencia = $quedan - $estimados;
                    $quedan = $estimados;
                }
                // La muestra del lote que sigue en el lago queda como un muestreo normal y actualiza su peso
                if ($muestra !== null) {
                    self::registrarMuestreo($jornada, $siembra, $muestra, $usuario);
                }
            }

            if ($cosechados > 0) {
                $pesoPromedio = round($kgSiembra * 1000 / $cosechados, 2);
                if ($pesoPromedio > CosechaController::PESO_MAXIMO_PEZ_G) {
                    throw ValidationException::withMessages(["{$campo}.kg.{$siembra->id}" => __('jornadas.errores.peso_promedio_irreal', [
                        'especie' => $siembra->nombre_especie, 'lago' => $lago->nombre, 'gramos' => Formato::numero($pesoPromedio), 'maximo' => Formato::numero(CosechaController::PESO_MAXIMO_PEZ_G),
                    ])]);
                }
                $cosechas[] = Cosecha::create([
                    'siembra_id' => $siembra->id, 'lago_id' => $lago->id, 'jornada_id' => $jornada->id,
                    'user_id' => $usuario->id, 'despachado_por_id' => $usuario->id,
                    'fecha' => $jornada->fecha->toDateString(), 'peces' => $cosechados, 'kg' => $kgSiembra, 'peso_promedio_g' => $pesoPromedio,
                    'peces_esperados' => $antes, 'peso_esperado_g' => $pesoRegistro, 'peso_medido_g' => $pesoMedido, 'cerro_lote' => $quedan === 0,
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
                'peso_registro_g' => $pesoRegistro, 'peso_medido_g' => $pesoMedido,
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

        return [$kgLago, $cosechas];
    }

    /**
     * Muestra al pescar de una siembra: peces pesados y gramos totales, los dos o ninguno. El promedio lo calcula el
     * servidor, en centésimas de gramo como el muestreo.
     *
     * @return ?array{peces: int, gramos: float, promedio: float}
     */
    private static function muestra(array $entrada, int $siembraId, string $campo): ?array
    {
        $peces = $entrada['muestra_peces'][$siembraId] ?? null;
        $gramos = $entrada['muestra_g'][$siembraId] ?? null;
        if (($peces === null || $peces === '') && ($gramos === null || $gramos === '')) {
            return null;
        }
        if (! is_numeric($peces) || (int) $peces < 1 || (int) $peces > 100000 || (string) (int) $peces !== (string) $peces
            || ! is_numeric($gramos) || (float) $gramos < 0.1 || (float) $gramos > 999999) {
            throw ValidationException::withMessages(["{$campo}.muestra.{$siembraId}" => __('jornadas.errores.muestra_invalida')]);
        }

        $centesimas = (int) round((float) $gramos * 100);

        return ['peces' => (int) $peces, 'gramos' => $centesimas / 100, 'promedio' => round($centesimas / (int) $peces) / 100];
    }

    /**
     * Muestra del lote que queda en el lago: un muestreo normal con la fecha de la jornada. Como en MuestreoController,
     * solo cambia el peso del lote si es el muestreo más reciente por fecha.
     */
    private static function registrarMuestreo(Jornada $jornada, Siembra $siembra, array $muestra, User $usuario): void
    {
        $fecha = $jornada->fecha->toDateString();
        $ultima = $siembra->muestreos()->max('fecha');
        $siembra->muestreos()->create([
            'user_id' => $usuario->id, 'fecha' => $fecha,
            'peces_pesados' => $muestra['peces'], 'peso_total_g' => $muestra['gramos'], 'peso_promedio_g' => $muestra['promedio'],
        ]);
        if ($ultima === null || $fecha >= substr((string) $ultima, 0, 10)) {
            $siembra->peso_actual_g = $muestra['promedio'];
        }

        Bitacora::registrar($usuario, 'muestreo', $siembra->lago, [Rol::ADMINISTRADOR], [
            'especie' => $siembra->nombre_especie, 'gramos' => (float) $muestra['promedio'], 'peces' => $muestra['peces'], 'fecha' => $fecha,
        ]);
    }

    /**
     * El limpio de cada especie se reparte entre sus cosechas de la jornada en proporción a sus kg enteros (tema 2: el
     * limpio de cada lago lo calcula el sistema; nadie lo digita).
     *
     * @param  list<Cosecha>  $cosechas
     */
    private static function repartirLimpio(array $rendimiento, array $cosechas): void
    {
        $porEspecie = [];
        foreach ($cosechas as $cosecha) {
            $porEspecie[(int) $cosecha->siembra->especie_id][$cosecha->id] = $cosecha;
        }
        foreach ($porEspecie as $especieId => $deLaEspecie) {
            $limpio = $rendimiento['especies'][$especieId]['limpio'] ?? null;
            if ($limpio === null) {
                continue;
            }
            $partes = RepartoCosecha::proporcional($limpio, array_map(fn (Cosecha $c) => (float) $c->kg, $deLaEspecie));
            foreach ($deLaEspecie as $id => $cosecha) {
                $cosecha->update(['limpio_kg' => $partes[$id]]);
            }
        }
    }

    /** Rendimiento de cada especie, con la referencia usada; si salió bajo, su motivo (opcional) y la bitácora. */
    private static function guardarRendimientos(Jornada $jornada, array $rendimiento, array $motivos, User $usuario): void
    {
        foreach ($rendimiento['especies'] as $especieId => $fila) {
            $motivo = trim((string) ($motivos[$especieId]['motivo'] ?? ''));
            if (mb_strlen($motivo) > 300) {
                throw ValidationException::withMessages(["rendimientos.{$especieId}.motivo" => __('jornadas.errores.motivo_largo', ['maximo' => 300])]);
            }
            JornadaRendimiento::create([
                'jornada_id' => $jornada->id, 'especie_id' => $especieId,
                'entero_kg' => $fila['entero'], 'reproduccion_kg' => $fila['reproduccion'], 'limpio_kg' => $fila['limpio'],
                'rendimiento' => $fila['rendimiento'], 'referencia' => $fila['referencia'], 'fuente_referencia' => $fila['fuente'],
                'bajo' => $fila['bajo'], 'motivo' => $fila['bajo'] && $motivo !== '' ? $motivo : null,
            ]);
            if ($fila['bajo']) {
                Bitacora::registrar($usuario, 'rendimiento_bajo', null, [Rol::ADMINISTRADOR], [
                    'especie' => Especie::query()->whereKey($especieId)->value('nombre'),
                    'rendimiento' => (float) $fila['rendimiento'], 'referencia' => (float) $fila['referencia'],
                    'motivo' => $motivo !== '' ? $motivo : __('jornadas.sin_motivo'), 'fecha' => $jornada->fecha->toDateString(),
                ]);
            }
        }
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
