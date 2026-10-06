<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Liquidacion;
use App\Models\User;
use App\Models\Venta;
use App\Support\CalculadoraNomina;
use App\Support\Formato;
use App\Support\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Nómina semanal del personal de campo (exclusiva del propietario).
 */
class NominaController extends Controller
{
    public function index(Request $request): View
    {
        $lunes = CalculadoraNomina::lunes($this->fechaValida($request->query('semana')));
        $personal = User::query()->with('roles')->deCampo()->orderBy('name')->get();
        $liquidaciones = Liquidacion::query()->with('usuario')->where('semana_inicio', $lunes->toDateString())->get()->keyBy('user_id');

        return view('admin.nomina.index', [
            'lunes' => $lunes,
            'personal' => $personal,
            'liquidaciones' => $liquidaciones,
            // Vista previa para quien aún no tiene liquidación esta semana
            'previas' => $personal->reject(fn (User $persona) => $liquidaciones->has($persona->id))
                ->mapWithKeys(fn (User $persona) => [$persona->id => CalculadoraNomina::calcular($persona, $lunes)]),
            'saldosPescado' => Venta::query()->conSaldo()->whereNotNull('comprador_id')
                ->groupBy('comprador_id')->selectRaw('comprador_id, SUM(saldo) AS total')->pluck('total', 'comprador_id'),
            'semanaTerminada' => $lunes->addWeek()->lte(now()),
        ]);
    }

    /** Liquida la semana de una persona. */
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'user_id' => ['required', 'integer', Rule::in(User::query()->deCampo()->pluck('id')->all())],
            'semana' => ['required', 'date_format:Y-m-d'],
            'descontar_pescado' => ['nullable', 'boolean'],
        ], [
            'user_id.in' => __('mensajes.nomina.solo_liquida_personal_campo_aprobado'),
        ]);

        $lunes = CalculadoraNomina::lunes($datos['semana']);
        if ($lunes->gt(Region::hoy())) {
            throw ValidationException::withMessages(['semana' => __('mensajes.nomina.no_liquidan_semanas_aun_no')]);
        }

        $liquidacion = DB::transaction(function () use ($datos, $lunes, $request) {
            // Bloquea a la persona: evita liquidar dos veces la misma semana al mismo tiempo
            $persona = User::whereKey($datos['user_id'])->lockForUpdate()->first();

            if ($persona->valor_hora === null || $persona->valor_hora_extra === null) {
                throw ValidationException::withMessages(['user_id' => __('mensajes.nomina.configura_tarifas', ['nombre' => $persona->name])]);
            }
            if (Liquidacion::where('user_id', $persona->id)->where('semana_inicio', $lunes->toDateString())->exists()) {
                throw ValidationException::withMessages(['user_id' => __('mensajes.nomina.ya_liquidada', ['nombre' => $persona->name])]);
            }

            $calculo = CalculadoraNomina::calcular($persona, $lunes);
            if ($calculo['bruto'] === 0) {
                throw ValidationException::withMessages(['user_id' => __('mensajes.nomina.sin_horas', ['nombre' => $persona->name])]);
            }

            $liquidacion = Liquidacion::create([
                'user_id' => $persona->id,
                'creada_por' => $request->user()->id,
                'semana_inicio' => $lunes->toDateString(),
                'semana_fin' => $lunes->addDays(6)->toDateString(),
                'horas_turnos' => $calculo['horas_turnos'],
                'valor_hora' => $persona->valor_hora,
                'valor_turnos' => $calculo['valor_turnos'],
                'horas_extra' => $calculo['horas_extra'],
                'valor_hora_extra' => $persona->valor_hora_extra,
                'valor_horas_extra' => $calculo['valor_horas_extra'],
                'bruto' => $calculo['bruto'],
                'descuento_pescado' => 0,
                'total_pagar' => $calculo['bruto'],
            ]);

            if (! empty($datos['descontar_pescado'])) {
                $this->descontarPescado($liquidacion, $persona, $request->user());
            }

            return $liquidacion;
        });

        return redirect()->route('admin.nomina.index', ['semana' => $lunes->toDateString()])
            ->with('estado', __('mensajes.nomina.por_pagar', ['nombre' => $liquidacion->usuario->name, 'total' => Formato::dinero($liquidacion->total_pagar)]));
    }

    public function pagar(Liquidacion $liquidacion): RedirectResponse
    {
        if (! $liquidacion->estaPagada()) {
            $liquidacion->update(['pagada_at' => now()]);
        }

        return back()->with('estado', __('mensajes.nomina.pagada', ['nombre' => $liquidacion->usuario->name]));
    }

    /** Anula una liquidación sin pagar y devuelve los saldos de pescado que se habían descontado. */
    public function destroy(Liquidacion $liquidacion): RedirectResponse
    {
        DB::transaction(function () use ($liquidacion) {
            $bloqueada = Liquidacion::whereKey($liquidacion->id)->lockForUpdate()->first();

            if ($bloqueada->estaPagada()) {
                throw ValidationException::withMessages(['liquidacion' => __('mensajes.nomina.nomina_pagada_no_anula_queda')]);
            }

            foreach ($bloqueada->abonos()->get() as $abono) {
                Venta::whereKey($abono->venta_id)->lockForUpdate()->first()->increment('saldo', $abono->valor);
                $abono->delete();
            }

            $bloqueada->delete();
        });

        return back()->with('estado', __('mensajes.nomina.anulada', ['nombre' => $liquidacion->usuario->name]));
    }

    public function tarifas(Request $request, User $usuario): RedirectResponse
    {
        abort_unless(User::query()->deCampo()->whereKey($usuario->id)->exists(), 404);

        $datos = $request->validate([
            'valor_hora' => ['required', 'integer', 'min:1', 'max:1000000'],
            'valor_hora_extra' => ['required', 'integer', 'min:1', 'max:1000000'],
        ], [
            'integer' => __('region.dinero_sin_decimales'),
        ]);

        // Las tarifas no son asignables en masa (forman parte de la gestión de personal)
        $usuario->forceFill($datos)->save();

        return back()->with('estado', __('mensajes.nomina.tarifas_guardadas', ['nombre' => $usuario->name]));
    }

    /**
     * Descuenta de la liquidación los créditos de pescado de la persona, del más antiguo al más reciente,
     * sin pasar del valor bruto. Cada descuento queda como abono por nómina.
     */
    private function descontarPescado(Liquidacion $liquidacion, User $persona, User $registra): void
    {
        $disponible = $liquidacion->bruto;
        $creditos = Venta::query()->where('comprador_id', $persona->id)->conSaldo()->orderBy('fecha')->orderBy('id')->lockForUpdate()->get();

        foreach ($creditos as $credito) {
            if ($disponible === 0) {
                break;
            }

            $valor = min($credito->saldo, $disponible);
            $credito->abonos()->create([
                'liquidacion_id' => $liquidacion->id,
                'user_id' => $registra->id,
                'fecha' => Region::hoy()->toDateString(),
                'valor' => $valor,
                'medio' => 'nomina',
                'observaciones' => __('mensajes.nomina.observacion_abono', ['fecha' => $liquidacion->semana_inicio->format('d/m/Y')]),
            ]);
            $credito->decrement('saldo', $valor);
            $disponible -= $valor;
        }

        $descuento = $liquidacion->bruto - $disponible;
        $liquidacion->update(['descuento_pescado' => $descuento, 'total_pagar' => $liquidacion->bruto - $descuento]);
    }

    private function fechaValida(?string $valor): ?string
    {
        return $valor !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) && strtotime($valor) !== false ? $valor : null;
    }
}
