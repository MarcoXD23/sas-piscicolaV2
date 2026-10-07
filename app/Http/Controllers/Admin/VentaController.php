<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Abono;
use App\Models\Bitacora;
use App\Models\Cosecha;
use App\Models\Rol;
use App\Models\User;
use App\Models\Venta;
use App\Support\Formato;
use App\Support\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Ventas de pescado y créditos (exclusivo del propietario).
 */
class VentaController extends Controller
{
    public function index(Request $request): View
    {
        $inicioMes = Region::hoy()->startOfMonth()->toDateString();

        return view('admin.ventas.index', [
            // Registra ventas y abonos el administrador; el propietario consulta
            'puedeVender' => $request->user()->tieneRol(Rol::ADMINISTRADOR),
            'disponibles' => Cosecha::query()->with(['lago', 'siembra'])->withSum('ventas', 'kg')->latest('fecha')->limit(50)->get()
                ->filter(fn (Cosecha $cosecha) => $cosecha->kgDisponibles() > 0)->values(),
            'creditos' => Venta::query()->with('comprador')->conSaldo()->orderBy('fecha')->get(),
            'ventas' => Venta::query()->with(['cosecha.lago', 'comprador'])->latest('fecha')->latest('id')->paginate(20),
            'vendidoMes' => (int) Venta::where('fecha', '>=', $inicioMes)->sum('total'),
            'kgMes' => (float) Venta::where('fecha', '>=', $inicioMes)->sum('kg'),
            'cartera' => (int) Venta::conSaldo()->sum('saldo'),
            'empleados' => User::query()->whereNotNull('aprobado_at')->orderBy('name')->pluck('name', 'id')->all(),
            'formasPago' => Venta::opciones(),
            'medios' => Abono::opciones(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant_id;

        $datos = $request->validate([
            'cosecha_id' => ['required', 'integer', Rule::exists('cosechas', 'id')->where('tenant_id', $tenant)],
            'comprador_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenant)->whereNotNull('aprobado_at')],
            'cliente' => ['nullable', 'string', 'max:255', 'required_without:comprador_id'],
            'fecha' => ['required', 'date', 'before_or_equal:'.Region::hoy()->toDateString()],
            'kg' => ['required', 'numeric', 'min:0.1'],
            'precio_kg' => ['required', 'integer', 'min:1', 'max:10000000'],
            'forma_pago' => ['required', Rule::in(Venta::FORMAS_PAGO)],
        ], [
            'cosecha_id.required' => __('mensajes.venta.elige_cosecha_sale_pescado'),
            'cliente.required_without' => __('mensajes.venta.escribe_cliente_elige_empleado'),
            'comprador_id.exists' => __('mensajes.venta.ese_empleado_no_pertenece_finca'),
            'precio_kg.integer' => __('region.dinero_sin_decimales'),
            'fecha.before_or_equal' => __('mensajes.venta.fecha_no_puede_ser_futura'),
        ]);

        $venta = DB::transaction(function () use ($datos, $request) {
            // Bloquea la cosecha para que dos ventas simultáneas no vendan el mismo pescado
            $cosecha = Cosecha::whereKey($datos['cosecha_id'])->lockForUpdate()->first();
            $kg = round((float) $datos['kg'], 2);

            if ($datos['fecha'] < $cosecha->fecha->toDateString()) {
                throw ValidationException::withMessages(['fecha' => __('mensajes.venta.venta_no_puede_ser_anterior')]);
            }

            $disponible = $cosecha->kgDisponibles();
            if ($kg > $disponible) {
                throw ValidationException::withMessages(['kg' => __('mensajes.venta.kg_disponibles', ['kg' => Formato::numero($disponible, 2)])]);
            }

            $total = (int) round($kg * (int) $datos['precio_kg']);
            $comprador = empty($datos['comprador_id']) ? null : User::find($datos['comprador_id']);

            $venta = Venta::create([
                'cosecha_id' => $cosecha->id,
                'user_id' => $request->user()->id,
                'comprador_id' => $comprador?->id,
                'cliente' => $comprador?->name ?? $datos['cliente'],
                'fecha' => $datos['fecha'],
                'kg' => $kg,
                'precio_kg' => (int) $datos['precio_kg'],
                'total' => $total,
                'forma_pago' => $datos['forma_pago'],
                'saldo' => $datos['forma_pago'] === 'credito' ? $total : 0,
            ]);

            Bitacora::registrar($request->user(), 'venta', $cosecha->lago, [Rol::PROPIETARIO], [
                'kg' => (float) $kg, 'cliente' => $venta->cliente, 'total' => Formato::dinero($total),
                'forma_pago' => __('ventas.formas_pago.'.$venta->forma_pago), 'fecha' => $datos['fecha'],
            ]);

            return $venta;
        });

        return redirect()->route('admin.ventas.index')->with('estado', __($venta->esCredito() ? 'mensajes.venta.registrada_credito' : 'mensajes.venta.registrada_contado', ['cliente' => $venta->cliente]));
    }

    public function abonar(Request $request, Venta $venta): RedirectResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:'.Region::hoy()->toDateString()],
            'valor' => ['required', 'integer', 'min:1'],
            'medio' => ['required', Rule::in(Abono::MEDIOS)],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ], [
            'valor.integer' => __('region.dinero_sin_decimales'),
            'valor.min' => __('mensajes.venta.abono_debe_ser_mayor_cero'),
        ]);

        DB::transaction(function () use ($venta, $datos, $request) {
            $bloqueada = Venta::whereKey($venta->id)->lockForUpdate()->first();

            if ($bloqueada->saldo === 0) {
                throw ValidationException::withMessages(['valor' => __('mensajes.venta.esta_venta_no_tiene_saldo')]);
            }
            if ((int) $datos['valor'] > $bloqueada->saldo) {
                throw ValidationException::withMessages(['valor' => __('mensajes.venta.abono_supera_saldo', ['saldo' => Formato::dinero($bloqueada->saldo)])]);
            }

            $bloqueada->abonos()->create($datos + ['user_id' => $request->user()->id]);
            $bloqueada->saldo -= (int) $datos['valor'];
            $bloqueada->save();
        });

        return back()->with('estado', __('mensajes.venta.abono_registrado'));
    }
}
