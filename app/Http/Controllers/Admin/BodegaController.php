<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Concentrado;
use App\Models\DespachoAlimento;
use App\Models\MovimientoAlimento;
use App\Models\Rol;
use App\Models\StockAlimento;
use App\Support\Alertas\EvaluadorAlertas;
use App\Support\CoberturaBodega;
use App\Support\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Bodega en dos pasos: el propietario registra el DESPACHO de concentrado y el administrador la RECEPCIÓN.
 * El stock es lo recibido menos las raciones (stocks_alimento), siempre en transacción y sin quedar negativo.
 */
class BodegaController extends Controller
{
    private const TIPOS_MOVIMIENTO = ['entrada', 'salida'];

    private const DESPACHOS_RECIENTES = 20;

    public function index(Request $request): View
    {
        $tipo = in_array($request->query('tipo'), self::TIPOS_MOVIMIENTO, true) ? $request->query('tipo') : null;

        $movimientos = MovimientoAlimento::query()
            ->with(['usuario', 'lago', 'despacho', 'concentrado'])
            ->when($tipo, fn ($consulta) => $consulta->where('tipo_movimiento', $tipo))
            ->latest('fecha')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $despachos = DespachoAlimento::query()
            ->with(['usuario', 'concentrado'])
            ->withCount('recepciones')
            ->withSum('recepciones', 'cantidad_kg')
            ->latest('fecha')->latest('id')
            ->limit(self::DESPACHOS_RECIENTES)
            ->get();

        return view('admin.bodega.index', [
            'cobertura' => CoberturaBodega::calcular(),
            // Ordenados por proteína, de menor a mayor; los que no tienen (de antes de que fuera obligatoria) al final
            'concentradosCatalogo' => Concentrado::query()->activos()->orderByRaw('proteina_porcentaje IS NULL')->orderBy('proteina_porcentaje')->orderBy('nombre')->get(),
            'movimientos' => $movimientos,
            'tipo' => $tipo,
            'despachos' => $despachos,
            // Despachos que todavía se pueden recibir (sin recepción o con faltante)
            'despachosPorRecibir' => $despachos->filter(fn (DespachoAlimento $d) => in_array($d->estado(), [DespachoAlimento::SIN_RECEPCION, DespachoAlimento::FALTANTE], true)),
            'puedeDespachar' => $request->user()->tieneRol(Rol::PROPIETARIO),
            'puedeRecibir' => $request->user()->tieneRol(Rol::ADMINISTRADOR),
        ]);
    }

    /** Propietario: despacho de concentrado hacia la finca (no cambia el stock hasta que se reciba). */
    public function despachar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:'.Region::hoy()->toDateString()],
            'proveedor' => ['required', 'string', 'max:255'],
            'concentrado_id' => ['required', 'integer', $this->delCatalogo($request)],
            'bultos' => ['required', 'integer', 'min:1', 'max:10000'],
            'peso_bulto_kg' => ['required', 'numeric', 'min:1', 'max:1000'],
            'lote' => ['required', 'string', 'max:255'],
        ], $this->mensajesConcentrado());

        DB::transaction(function () use ($datos, $request) {
            // El concentrado se elige del catálogo de la finca (Ajustes › Concentrados); tipo_concentrado guarda su
            // nombre, para que el stock, la ración y la cola sin conexión sigan funcionando con el texto
            $concentrado = Concentrado::query()->findOrFail($datos['concentrado_id']);

            $despacho = DespachoAlimento::create($datos + [
                'user_id' => $request->user()->id,
                'tipo_concentrado' => $concentrado->nombre,
                'cantidad_kg' => round($datos['bultos'] * $datos['peso_bulto_kg'], 2),
            ]);

            Bitacora::registrar($request->user(), 'despacho', null, [Rol::PROPIETARIO], [
                'kg' => (float) $despacho->cantidad_kg, 'concentrado' => $despacho->tipo_concentrado, 'bultos' => (int) $despacho->bultos,
                'proveedor' => $despacho->proveedor, 'fecha' => $datos['fecha'],
            ]);
        });

        return redirect()->route('admin.bodega.index')->with('estado', __('mensajes.bodega.despacho_registrado'));
    }

    /** Administrador: recepción contra un despacho o «sin despacho». Suma al stock. */
    public function recibir(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'despacho_id' => ['nullable', 'integer', Rule::exists('despachos_alimento', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'fecha' => ['required', 'date', 'before_or_equal:'.Region::hoy()->toDateString()],
            // Sin despacho, el concentrado se elige del catálogo; con despacho, es el del despacho
            'concentrado_id' => ['required_without:despacho_id', 'nullable', 'integer', $this->delCatalogo($request)],
            'proveedor' => ['required_without:despacho_id', 'nullable', 'string', 'max:255'],
            'lote' => ['required_without:despacho_id', 'nullable', 'string', 'max:255'],
            'bultos' => ['required', 'integer', 'min:1', 'max:10000'],
            'peso_bulto_kg' => ['required', 'numeric', 'min:1', 'max:1000'],
        ], [
            'despacho_id.exists' => __('mensajes.bodega.despacho_no_existe'),
        ] + $this->mensajesConcentrado());

        DB::transaction(function () use ($datos, $request) {
            $despacho = empty($datos['despacho_id']) ? null : DespachoAlimento::whereKey($datos['despacho_id'])->lockForUpdate()->first();

            if ($despacho && $datos['fecha'] < $despacho->fecha->toDateString()) {
                throw ValidationException::withMessages(['fecha' => __('mensajes.bodega.recepcion_antes_del_despacho')]);
            }

            // Con despacho, el concentrado, el proveedor y el lote son los del despacho
            // (un despacho viejo sin catálogo se liga por su texto, como siempre)
            $concentrado = $despacho !== null
                ? Concentrado::resolver($despacho->tipo_concentrado, $despacho->concentrado_id)
                : Concentrado::query()->findOrFail($datos['concentrado_id']);
            $tipo = $concentrado?->nombre ?? trim($despacho->tipo_concentrado);
            $cantidad = round($datos['bultos'] * $datos['peso_bulto_kg'], 2);

            MovimientoAlimento::create([
                'user_id' => $request->user()->id,
                'despacho_id' => $despacho?->id,
                'concentrado_id' => $concentrado?->id,
                'fecha' => $datos['fecha'],
                'tipo_movimiento' => 'entrada',
                'tipo_concentrado' => $tipo,
                'cantidad_kg' => $cantidad,
                'proveedor' => $despacho?->proveedor ?? $datos['proveedor'],
                'lote' => $despacho?->lote ?? $datos['lote'],
                'bultos' => $datos['bultos'],
                'peso_bulto_kg' => $datos['peso_bulto_kg'],
            ]);

            $stock = StockAlimento::paraBloquear($concentrado, $tipo);
            if ($stock) {
                $stock->concentrado_id ??= $concentrado?->id;
                $stock->cantidad_kg += $cantidad;
                $stock->save();
            } else {
                StockAlimento::create([
                    'concentrado_id' => $concentrado?->id,
                    'tipo_concentrado' => $tipo,
                    'cantidad_kg' => $cantidad,
                ]);
            }

            Bitacora::registrar($request->user(), 'recepcion', null, [Rol::ADMINISTRADOR], [
                'kg' => (float) $cantidad, 'concentrado' => $tipo,
                'despacho' => $despacho ? __('bodega.despacho_del', ['fecha' => $despacho->fecha->format('d/m/Y'), 'proveedor' => $despacho->proveedor]) : __('bodega.sin_despacho'),
                'fecha' => $datos['fecha'],
            ]);
        });

        return redirect()->route('admin.bodega.index')->with('estado', __('mensajes.bodega.recepcion_alimento_registrada'));
    }

    /** Propietario: deja constancia de que revisó la diferencia de un despacho (cierra su alerta). */
    public function marcarRevisado(DespachoAlimento $despacho): RedirectResponse
    {
        $despacho->forceFill(['revisado_at' => now()])->save();
        EvaluadorAlertas::paraFinca($despacho->tenant_id);

        return back()->with('estado', __('mensajes.bodega.despacho_revisado'));
    }

    /** El concentrado elegido es del catálogo de esta finca y está activo (Ajustes › Concentrados). */
    private function delCatalogo(Request $request): Exists
    {
        return Rule::exists('concentrados', 'id')->where('tenant_id', $request->user()->tenant_id)->where('activo', true);
    }

    /** @return array<string, string> */
    private function mensajesConcentrado(): array
    {
        return [
            'concentrado_id.required' => __('bodega.concentrado_elige'),
            'concentrado_id.required_without' => __('bodega.concentrado_elige'),
            'concentrado_id.integer' => __('bodega.concentrado_no_valido'),
            'concentrado_id.exists' => __('bodega.concentrado_no_valido'),
        ];
    }
}
