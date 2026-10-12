<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\Siembra;
use App\Support\Formato;
use App\Support\Region;
use App\Support\UnidadesPeso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Muestreo de una siembra (desde Lagos con conexión, o desde Registro rápido sin conexión: middleware RegistroSinConexion).
 * Siempre queda en el historial. El peso actual y la biomasa solo cambian si es el muestreo más reciente por fecha de la
 * siembra (con la misma fecha, gana el que llega después): uno atrasado que llega tarde no pisa a uno más nuevo.
 * Peso total en gramos, kilos o libras (peso_total + unidad): el servidor convierte con UnidadesPeso (enteros, igual que
 * el teléfono) y aplica el rango de 0,1 a 999.999 g después de convertir. Lo guardado siempre queda en gramos.
 * Un envío viejo de la cola (solo peso_total_g, sin unidad) se toma en gramos.
 */
class MuestreoController extends Controller
{
    public function store(Request $request, Siembra $siembra): RedirectResponse
    {
        // «Hoy» es el día en que de verdad se registró (sin conexión, la hora real del teléfono ya corregida), en la zona
        // de la finca: un muestreo de las 23:50 enviado después de medianoche conserva su día.
        $hoy = Region::hoyDelRegistro()->toDateString();
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:'.$hoy, 'after_or_equal:'.$siembra->fecha->format('Y-m-d')],
            'peces_pesados' => ['required', 'integer', 'min:1', 'max:100000'],
        ], Region::mensajeFechaDelRegistro());
        $peso = $this->pesoTotal($request);

        $resultado = DB::transaction(function () use ($datos, $peso, $siembra, $request) {
            // Bloquea la siembra antes de registrar para evitar condiciones de carrera
            $siembraBloqueada = Siembra::where('id', $siembra->id)->lockForUpdate()->first();

            if (! $siembraBloqueada->estaActiva()) {
                throw ValidationException::withMessages(['peces_pesados' => __('mensajes.muestreo.esta_siembra_ya_esta_finalizada')]);
            }

            // El promedio lo calcula el servidor (nunca se confía en el que muestra el navegador), con enteros como el teléfono
            $promedio = UnidadesPeso::promedio($peso['centesimas'], (int) $datos['peces_pesados']);
            $pesoPromedio = UnidadesPeso::gramos($promedio);
            $anterior = UnidadesPeso::centesimasDeGramos($siembraBloqueada->peso_actual_g);
            $fecha = Carbon::parse($datos['fecha'])->toDateString();
            // ¿Es el más reciente por fecha? (con la misma fecha, sí: gana el que llega después)
            $ultimaFecha = $siembraBloqueada->muestreos()->max('fecha');
            $esMasReciente = $ultimaFecha === null || $fecha >= Carbon::parse($ultimaFecha)->toDateString();

            $siembraBloqueada->muestreos()->create([
                'user_id' => $request->user()->id,
                'fecha' => $datos['fecha'],
                'peces_pesados' => $datos['peces_pesados'],
                'peso_total_g' => UnidadesPeso::gramos($peso['centesimas']),
                'peso_promedio_g' => $pesoPromedio,
            ]);

            if ($esMasReciente) {
                $siembraBloqueada->peso_actual_g = $pesoPromedio;
                $siembraBloqueada->biomasa_kg = ($siembraBloqueada->peces_vivos * $pesoPromedio) / 1000;
                $siembraBloqueada->save();
            }

            // Lo que se escribió, si no fue en gramos (los gramos van siempre en «gramos»)
            Bitacora::registrar($request->user(), 'muestreo', $siembraBloqueada->lago, [Rol::ADMINISTRADOR], [
                'especie' => $siembraBloqueada->nombre_especie, 'gramos' => round((float) $pesoPromedio, 2), 'peces' => (int) $datos['peces_pesados'], 'fecha' => $datos['fecha'],
            ] + ($peso['unidad'] !== UnidadesPeso::GRAMOS ? ['escrito' => $peso['escrito'].' '.$peso['unidad']] : []));

            return ['promedio' => $promedio, 'anterior' => $anterior, 'mas_reciente' => $esMasReciente];
        });

        $unidad = $peso['unidad'];
        $enGramos = $unidad === UnidadesPeso::GRAMOS;
        if (! $resultado['mas_reciente']) {
            return back()->with('estado', $enGramos
                ? __('mensajes.muestreo.guardado_en_historial', ['actual' => Formato::numero($resultado['anterior'] / 100, 2)])
                : __('mensajes.muestreo.guardado_en_historial_unidad', ['actual' => UnidadesPeso::texto($resultado['anterior'], $unidad)]));
        }

        $diferencia = $resultado['promedio'] - $resultado['anterior'];
        $signo = $diferencia >= 0 ? '+' : '−';

        return back()->with('estado', $enGramos
            ? __('mensajes.muestreo.registrado_con_promedio', [
                'promedio' => Formato::numero($resultado['promedio'] / 100, 2),
                'anterior' => Formato::numero($resultado['anterior'] / 100, 2),
                'diferencia' => $signo.Formato::numero(abs($diferencia) / 100, 2),
            ])
            : __('mensajes.muestreo.registrado_con_promedio_unidad', [
                'promedio' => UnidadesPeso::texto($resultado['promedio'], $unidad),
                'anterior' => UnidadesPeso::texto($resultado['anterior'], $unidad),
                'diferencia' => $signo.UnidadesPeso::texto(abs($diferencia), $unidad),
            ]));
    }

    /**
     * Peso total en centésimas de gramo, en este orden:
     * 1. Unidad válida (o ninguna: gramos). 2. Cada valor enviado es un número con hasta 6 cifras enteras y 3 decimales
     * (numérico, finito, de tamaño razonable). 3. Si llegan peso_total y peso_total_g, deben coincidir en gramos.
     * 4. Recién después se convierte y se aplica el rango de 0,1 a 999.999 g.
     * Con unidad manda peso_total; sin unidad, peso_total_g (la cola vieja), y si no hay, peso_total en gramos.
     *
     * @return array{centesimas: int, unidad: string, escrito: string}
     */
    private function pesoTotal(Request $request): array
    {
        $unidad = $request->input('unidad');
        if ($unidad !== null && ! UnidadesPeso::valida($unidad)) {
            throw ValidationException::withMessages(['unidad' => __('mensajes.muestreo.unidad_invalida')]);
        }
        $nuevo = $request->input('peso_total');
        $viejo = $request->input('peso_total_g');
        if ($nuevo === null && $viejo === null) {
            throw ValidationException::withMessages(['peso_total' => __('mensajes.muestreo.escribe_peso_total')]);
        }
        foreach (['peso_total' => $nuevo, 'peso_total_g' => $viejo] as $campo => $valor) {
            if ($valor !== null && ! UnidadesPeso::numeroValido($valor)) {
                throw ValidationException::withMessages([$campo => __('mensajes.muestreo.peso_no_valido')]);
            }
        }

        $deNuevo = $nuevo === null ? null : UnidadesPeso::aCentesimas($nuevo, $unidad ?? UnidadesPeso::GRAMOS);
        $deViejo = $viejo === null ? null : UnidadesPeso::aCentesimas($viejo, UnidadesPeso::GRAMOS);
        if ($deNuevo !== null && $deViejo !== null && $deNuevo !== $deViejo) {
            throw ValidationException::withMessages(['peso_total' => __('mensajes.muestreo.pesos_no_coinciden')]);
        }

        [$campo, $centesimas, $unidad, $escrito] = match (true) {
            $unidad !== null && $nuevo !== null => ['peso_total', $deNuevo, $unidad, (string) $nuevo],
            $viejo !== null => ['peso_total_g', $deViejo, UnidadesPeso::GRAMOS, (string) $viejo],
            default => ['peso_total', $deNuevo, UnidadesPeso::GRAMOS, (string) $nuevo],
        };

        $minimo = 10;          // 0,1 g
        $maximo = 99999900;    // 999.999 g
        if ($centesimas < $minimo || $centesimas > $maximo) {
            throw ValidationException::withMessages([$campo => $unidad === UnidadesPeso::GRAMOS
                ? __('mensajes.muestreo.peso_fuera_de_rango')
                : __('mensajes.muestreo.peso_fuera_de_rango_unidad', ['maximo' => UnidadesPeso::maximoEnUnidad($maximo, $unidad), 'unidad' => $unidad])]);
        }

        return ['centesimas' => $centesimas, 'unidad' => $unidad, 'escrito' => $escrito];
    }
}
