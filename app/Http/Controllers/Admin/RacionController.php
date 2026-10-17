<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Concentrado;
use App\Models\Lago;
use App\Models\MovimientoAlimento;
use App\Models\Rol;
use App\Models\StockAlimento;
use App\Support\Formato;
use App\Support\Proceso\Ayuno;
use App\Support\Region;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RacionController extends Controller
{
    public const UNIDAD_KG = 'kg';

    public const UNIDAD_BULTOS = 'bultos';

    public const UNIDADES = [self::UNIDAD_KG, self::UNIDAD_BULTOS];

    public function store(Request $request, Lago $lago): RedirectResponse
    {
        // La fecha no puede ser posterior al día real del registro (sin conexión, el del teléfono ya corregido)
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:'.Region::hoyDelRegistro()->toDateString()],
            'tipo_concentrado' => ['required', 'string', 'max:255'],
            'unidad' => ['nullable', 'string', Rule::in(self::UNIDADES)],
            'bultos' => ['nullable', 'numeric', 'min:0.01', 'max:2500'],
            'cantidad_kg' => ['required_without:bultos', 'nullable', 'numeric', 'min:0.1', 'max:100000'],
        ], Region::mensajeFechaDelRegistro());

        if (! $lago->siembrasActivas()->exists()) {
            throw ValidationException::withMessages(['cantidad_kg' => __('mensajes.racion.este_lago_no_tiene_peces')]);
        }

        $cantidadKg = DB::transaction(function () use ($datos, $lago, $request) {
            // El teléfono o el formulario pueden enviar el nombre completo o el nombre corto del concentrado
            $concentrado = Concentrado::resolver($datos['tipo_concentrado']);
            $stock = StockAlimento::paraBloquear($concentrado, $datos['tipo_concentrado']);

            // El stock es solo lo recibido en bodega: sin recepción no se puede alimentar con ese concentrado
            if (! $stock || (float) $stock->cantidad_kg <= 0) {
                throw ValidationException::withMessages(['cantidad_kg' => __('mensajes.racion.sin_concentrado_recibido')]);
            }

            if ($concentrado === null && $stock->concentrado_id !== null) {
                $concentrado = Concentrado::query()->find($stock->concentrado_id);
            }

            $pesoBulto = (float) ($concentrado?->peso_bulto_kg ?: Concentrado::PESO_BULTO_POR_DEFECTO);
            $enBultos = ($datos['unidad'] ?? self::UNIDAD_KG) === self::UNIDAD_BULTOS || (! empty($datos['bultos']) && empty($datos['cantidad_kg']));
            $bultos = $enBultos ? (float) ($datos['bultos'] ?? $datos['cantidad_kg']) : null;
            $kg = $enBultos ? round($bultos * $pesoBulto, 2) : (float) $datos['cantidad_kg'];

            if ($kg < 0.1 || $kg > 100000) {
                throw ValidationException::withMessages(['cantidad_kg' => __('mensajes.racion.cantidad_fuera_de_rango')]);
            }

            if ((float) $stock->cantidad_kg < $kg) {
                throw ValidationException::withMessages([
                    'cantidad_kg' => __('mensajes.racion.stock_insuficiente', ['kg' => Formato::numero($stock->cantidad_kg, 2), 'concentrado' => $stock->tipo_concentrado]),
                ]);
            }

            if ($concentrado !== null && $stock->concentrado_id === null) {
                $stock->concentrado_id = $concentrado->id;
            }

            MovimientoAlimento::create([
                'user_id' => $request->user()->id,
                'lago_id' => $lago->id,
                'concentrado_id' => $concentrado?->id ?? $stock->concentrado_id,
                'fecha' => $datos['fecha'],
                'tipo_movimiento' => 'salida',
                'tipo_concentrado' => $stock->tipo_concentrado,
                'cantidad_kg' => $kg,
                'bultos' => $bultos !== null && fmod($bultos, 1.0) === 0.0 ? (int) $bultos : null,
                'peso_bulto_kg' => $enBultos ? $pesoBulto : null,
            ]);

            $stock->cantidad_kg -= $kg;
            $stock->save();

            Bitacora::registrar($request->user(), 'racion', $lago, [Rol::ADMINISTRADOR, Rol::TRABAJADOR], [
                'kg' => (float) $kg, 'concentrado' => $stock->tipo_concentrado, 'fecha' => $datos['fecha'],
            ]);

            return $kg;
        });

        $mensaje = __('mensajes.racion.registrada', ['kg' => Formato::numero($cantidadKg, 1), 'lago' => $lago->nombre]);
        // Lago en ayuno antes de su pesca: se avisa por qué, pero no se bloquea (tema 5)
        $pesca = Ayuno::delLago($lago->id, CarbonImmutable::parse($datos['fecha']));
        if ($pesca !== null) {
            $mensaje .= ' '.__('jornadas.ayuno.racion_registrada', ['lago' => $lago->nombre, 'fecha' => $pesca->format('d/m')]);
        }

        return back()->with('estado', $mensaje);
    }
}
