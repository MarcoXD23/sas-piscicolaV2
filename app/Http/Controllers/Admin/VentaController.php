<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Abono;
use App\Models\Cosecha;
use App\Models\User;
use App\Models\Venta;
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
    public function index(): View
    {
        $inicioMes = today()->startOfMonth();

        return view('admin.ventas.index', [
            'disponibles' => Cosecha::query()->with(['lago', 'siembra'])->withSum('ventas', 'kg')->latest('fecha')->limit(50)->get()
                ->filter(fn (Cosecha $cosecha) => $cosecha->kgDisponibles() > 0)->values(),
            'creditos' => Venta::query()->with('comprador')->conSaldo()->orderBy('fecha')->get(),
            'ventas' => Venta::query()->with(['cosecha.lago', 'comprador'])->latest('fecha')->latest('id')->paginate(20),
            'vendidoMes' => (int) Venta::where('fecha', '>=', $inicioMes)->sum('total'),
            'kgMes' => (float) Venta::where('fecha', '>=', $inicioMes)->sum('kg'),
            'cartera' => (int) Venta::conSaldo()->sum('saldo'),
            'empleados' => User::query()->whereNotNull('aprobado_at')->orderBy('name')->pluck('name', 'id')->all(),
            'formasPago' => Venta::FORMAS_PAGO,
            'medios' => Abono::MEDIOS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant_id;

        $datos = $request->validate([
            'cosecha_id' => ['required', 'integer', Rule::exists('cosechas', 'id')->where('tenant_id', $tenant)],
            'comprador_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenant)->whereNotNull('aprobado_at')],
            'cliente' => ['nullable', 'string', 'max:255', 'required_without:comprador_id'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'kg' => ['required', 'numeric', 'min:0.1'],
            'precio_kg' => ['required', 'integer', 'min:1', 'max:10000000'],
            'forma_pago' => ['required', Rule::in(array_keys(Venta::FORMAS_PAGO))],
        ], [
            'cosecha_id.required' => 'Elige de qué cosecha sale el pescado.',
            'cliente.required_without' => 'Escribe el cliente o elige un empleado.',
            'comprador_id.exists' => 'Ese empleado no pertenece a la finca.',
            'precio_kg.integer' => 'Escribe el precio en pesos, sin decimales.',
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
        ]);

        $venta = DB::transaction(function () use ($datos, $request) {
            // Bloquea la cosecha para que dos ventas simultáneas no vendan el mismo pescado
            $cosecha = Cosecha::whereKey($datos['cosecha_id'])->lockForUpdate()->first();
            $kg = round((float) $datos['kg'], 2);

            if ($datos['fecha'] < $cosecha->fecha->toDateString()) {
                throw ValidationException::withMessages(['fecha' => 'La venta no puede ser anterior a la cosecha.']);
            }

            $disponible = $cosecha->kgDisponibles();
            if ($kg > $disponible) {
                throw ValidationException::withMessages(['kg' => 'De esa cosecha quedan '.number_format($disponible, 2, ',', '.').' kg sin vender.']);
            }

            $total = (int) round($kg * (int) $datos['precio_kg']);
            $comprador = empty($datos['comprador_id']) ? null : User::find($datos['comprador_id']);

            return Venta::create([
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
        });

        return redirect()->route('admin.ventas.index')->with('estado', 'Venta registrada a '.$venta->cliente
            .($venta->esCredito() ? ' a crédito.' : ' de contado.'));
    }

    public function abonar(Request $request, Venta $venta): RedirectResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'valor' => ['required', 'integer', 'min:1'],
            'medio' => ['required', Rule::in(array_keys(Abono::MEDIOS))],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ], [
            'valor.integer' => 'Escribe el valor en pesos, sin decimales.',
            'valor.min' => 'El abono debe ser mayor que cero.',
        ]);

        DB::transaction(function () use ($venta, $datos, $request) {
            $bloqueada = Venta::whereKey($venta->id)->lockForUpdate()->first();

            if ($bloqueada->saldo === 0) {
                throw ValidationException::withMessages(['valor' => 'Esta venta no tiene saldo pendiente.']);
            }
            if ((int) $datos['valor'] > $bloqueada->saldo) {
                throw ValidationException::withMessages(['valor' => 'El abono supera el saldo de $ '.number_format($bloqueada->saldo, 0, ',', '.').'.']);
            }

            $bloqueada->abonos()->create($datos + ['user_id' => $request->user()->id]);
            $bloqueada->saldo -= (int) $datos['valor'];
            $bloqueada->save();
        });

        return back()->with('estado', 'Abono registrado.');
    }
}
