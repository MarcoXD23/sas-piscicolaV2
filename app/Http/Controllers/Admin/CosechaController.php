<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AvisoPesca;
use App\Models\Bitacora;
use App\Models\Cosecha;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tratamiento;
use App\Models\User;
use App\Support\CalculoPesaje;
use App\Support\Formato;
use App\Support\Region;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Cosechas: el administrador las registra con la tabla de pesadas (como en el cuaderno) o con el total limpio;
 * el propietario las consulta. Todas las cuentas las rehace el servidor con CalculoPesaje.
 */
class CosechaController extends Controller
{
    /** Peso promedio máximo por pez (g) que se acepta en una cosecha. */
    public const PESO_MAXIMO_PEZ_G = 20000;

    public const MAX_DESTINO = 120;

    public function create(Request $request): View
    {
        $finca = $request->user()->tenant;
        $lagos = Lago::query()->whereHas('siembrasActivas')->with('siembrasActivas')->orderBy('nombre')->get();

        // Ya elegida si se abre desde la tarjeta del lago (o de una siembra)
        $elegida = $request->integer('siembra') ?: $lagos->firstWhere('id', $request->integer('lago'))?->siembrasActivas->first()?->id;

        return view('admin.cosechas.crear', [
            'lagos' => $lagos,
            'siembraElegida' => $elegida,
            'destinos' => Cosecha::query()->whereNotNull('destino')->distinct()->orderBy('destino')->limit(50)->pluck('destino'),
            'personal' => User::query()->whereNotNull('aprobado_at')->orderBy('name')->get(['id', 'name']),
            'pesoCanasta' => $finca->peso_canasta_kg,
            'pesoCanastilla' => $finca->peso_canastilla_kg,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $datos = $request->validate([
            'siembra_id' => ['required', 'integer', Rule::exists('siembras', 'id')->where('tenant_id', $tenantId)->whereNull('finalizada_at')],
            'destino' => ['required', 'string', 'max:'.self::MAX_DESTINO],
            'despachado_por_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)->whereNotNull('aprobado_at')],
            'fecha' => ['required', 'date', 'before_or_equal:'.Region::hoy()->toDateString()],
            'peces' => ['required', 'integer', 'min:1', 'max:10000000'],
            // Dos modos: tabla de pesadas, o solo el total limpio (kg)
            'kg' => ['nullable', 'required_without:pesadas', 'numeric', 'min:0.1', 'max:9999999'],
            'pesadas' => ['nullable', 'array', 'min:1', 'max:'.CalculoPesaje::MAX_PESADAS],
            'pesadas.*.tipo' => ['required', 'string', Rule::in(CalculoPesaje::TIPOS)],
            'pesadas.*.cantidad' => ['required', 'integer', 'min:1', 'max:'.CalculoPesaje::MAX_CANTIDAD],
            'pesadas.*.peso_pesado' => ['required', 'numeric', 'gt:0', 'max:'.CalculoPesaje::MAX_PESO_PESADA_KG],
            'peso_canasta_kg' => ['nullable', 'required_with:pesadas', 'numeric', 'min:'.CalculoPesaje::MIN_PESO_VACIA_KG, 'max:'.CalculoPesaje::MAX_PESO_VACIA_KG],
            'peso_canastilla_kg' => ['nullable', 'required_with:pesadas', 'numeric', 'min:'.CalculoPesaje::MIN_PESO_VACIA_KG, 'max:'.CalculoPesaje::MAX_PESO_VACIA_KG],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ], $this->mensajes());

        $siembra = Siembra::findOrFail($datos['siembra_id']);
        if ($siembra->fecha->format('Y-m-d') > $datos['fecha']) {
            throw ValidationException::withMessages(['fecha' => __('mensajes.cosecha.fecha_no_puede_ser_anterior')]);
        }

        $pesaje = $this->pesaje($datos);

        $cosecha = DB::transaction(function () use ($siembra, $datos, $request, $pesaje) {
            $bloqueada = Siembra::whereKey($siembra->id)->lockForUpdate()->first();

            if (! $bloqueada->estaActiva()) {
                throw ValidationException::withMessages(['siembra_id' => __('mensajes.cosecha.esta_siembra_ya_esta_finalizada')]);
            }

            // Bloqueo sanitario: no se cosecha mientras corre el período de retiro de un tratamiento
            $fecha = CarbonImmutable::parse($datos['fecha']);
            $retiro = Tratamiento::where('lago_id', $bloqueada->lago_id)->enRetiro($fecha)->get()
                ->sortByDesc(fn (Tratamiento $t) => $t->finRetiro())->first();
            if ($retiro) {
                throw ValidationException::withMessages([
                    'fecha' => __('mensajes.cosecha.lago_en_retiro', ['fecha' => $retiro->finRetiro()->format('d/m/Y'), 'producto' => $retiro->producto]),
                ]);
            }

            $peces = (int) $datos['peces'];
            if ($peces > $bloqueada->peces_vivos) {
                throw ValidationException::withMessages(['peces' => __('mensajes.comun.solo_hay_peces', ['cantidad' => Formato::numero($bloqueada->peces_vivos)])]);
            }

            // Con pesadas, el total limpio lo calcula el servidor (nunca se confía en un total enviado por el navegador)
            $kg = $pesaje !== null ? $pesaje['totales']['limpio'] : round((float) $datos['kg'], 2);
            if ($kg <= 0) {
                throw ValidationException::withMessages(['kg' => __('mensajes.cosecha.registra_peso_cosechado')]);
            }
            // Peso promedio por pez realista (y dentro de la columna): evita un error 500 con kg desproporcionados
            if ($kg * 1000 / $peces > self::PESO_MAXIMO_PEZ_G) {
                throw ValidationException::withMessages(['kg' => __('mensajes.cosecha.peso_promedio_irreal', ['gramos' => Formato::numero($kg * 1000 / $peces), 'maximo' => Formato::numero(self::PESO_MAXIMO_PEZ_G)])]);
            }

            $cosecha = Cosecha::create([
                'siembra_id' => $bloqueada->id,
                'lago_id' => $bloqueada->lago_id,
                'user_id' => $request->user()->id,
                'despachado_por_id' => $datos['despachado_por_id'] ?? $request->user()->id,
                'destino' => trim($datos['destino']),
                'fecha' => $datos['fecha'],
                'peces' => $peces,
                'kg' => $kg,
                'peso_promedio_g' => round($kg * 1000 / $peces, 2),
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            foreach ($pesaje['filas'] ?? [] as $fila) {
                $cosecha->pesajes()->create(CalculoPesaje::paraGuardar($fila));
            }

            $bloqueada->peces_vivos -= $peces;
            $bloqueada->biomasa_kg = ($bloqueada->peces_vivos * $bloqueada->peso_actual_g) / 1000;
            if ($bloqueada->peces_vivos === 0) {
                // Cosecha total: la siembra se cierra y el lago queda libre
                $bloqueada->finalizada_at = now();
            }
            $bloqueada->save();

            // La cosecha atiende el aviso «listo para pescar» de esa siembra
            AvisoPesca::atenderDeSiembra($bloqueada, $request->user());

            Bitacora::registrar($request->user(), 'cosecha', $bloqueada->lago, [Rol::ADMINISTRADOR], [
                'peces' => $peces, 'kg' => (float) $kg, 'especie' => $bloqueada->nombre_especie,
                'destino' => $cosecha->destino, 'fecha' => $datos['fecha'],
            ]);

            return $cosecha;
        });

        return redirect()->route('admin.cosechas.show', $cosecha)->with('estado', __($pesaje !== null ? 'mensajes.cosecha.registrada_pesadas' : 'mensajes.cosecha.registrada', [
            'kg' => Formato::numero($cosecha->kg, 1),
            'peces' => Formato::numero($cosecha->peces),
            'gramos' => Formato::numero($cosecha->peso_promedio_g),
            'pesadas' => count($pesaje['filas'] ?? []),
        ]));
    }

    public function show(Cosecha $cosecha): View
    {
        $cosecha->load(['lago', 'siembra', 'usuario', 'despachadoPor', 'pesajes']);

        return view('admin.cosechas.show', [
            'cosecha' => $cosecha,
            'pesaje' => $cosecha->pesajes->isNotEmpty() ? CalculoPesaje::deCosecha($cosecha) : null,
        ]);
    }

    /**
     * Rehace las cuentas de las pesadas. Null si la cosecha se registra con el total limpio.
     *
     * @param  array<string, mixed>  $datos
     */
    private function pesaje(array $datos): ?array
    {
        if (empty($datos['pesadas'])) {
            return null;
        }

        $pesaje = CalculoPesaje::calcular(array_values($datos['pesadas']), (float) $datos['peso_canasta_kg'], (float) $datos['peso_canastilla_kg']);

        // El peso pesado debe ser mayor que el descuento de sus canastas o canastillas
        $errores = [];
        foreach ($pesaje['filas'] as $indice => $fila) {
            if ($fila['peso_pesado'] <= $fila['descuento']) {
                $errores["pesadas.{$indice}.peso_pesado"] = __('mensajes.cosecha.pesada_bajo_descuento', [
                    'numero' => $fila['numero'], 'pesado' => CalculoPesaje::kg($fila['peso_pesado']), 'descuento' => CalculoPesaje::kg($fila['descuento']),
                ]);
            }
        }
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        return $pesaje;
    }

    private function mensajes(): array
    {
        return [
            'siembra_id.required' => __('mensajes.cosecha.elige_lago'),
            'siembra_id.exists' => __('mensajes.cosecha.lago_sin_siembra'),
            'destino.required' => __('mensajes.cosecha.escribe_destino'),
            'destino.max' => __('mensajes.cosecha.destino_largo', ['maximo' => self::MAX_DESTINO]),
            'despachado_por_id.exists' => __('mensajes.cosecha.despacho_no_es_personal'),
            'fecha.before_or_equal' => __('mensajes.cosecha.fecha_no_puede_ser_futura'),
            'peces.min' => __('mensajes.cosecha.cosecha_menos_pez'),
            'kg.required_without' => __('mensajes.cosecha.registra_peso_limpio_o_pesadas'),
            'kg.min' => __('mensajes.cosecha.registra_peso_cosechado'),
            'pesadas.max' => __('mensajes.cosecha.maximo_pesadas', ['maximo' => CalculoPesaje::MAX_PESADAS]),
            'pesadas.*.tipo.required' => __('mensajes.cosecha.pesada_tipo'),
            'pesadas.*.tipo.in' => __('mensajes.cosecha.pesada_tipo'),
            'pesadas.*.cantidad.required' => __('mensajes.cosecha.pesada_cantidad', ['maximo' => CalculoPesaje::MAX_CANTIDAD]),
            'pesadas.*.cantidad.integer' => __('mensajes.cosecha.pesada_cantidad', ['maximo' => CalculoPesaje::MAX_CANTIDAD]),
            'pesadas.*.cantidad.min' => __('mensajes.cosecha.pesada_cantidad', ['maximo' => CalculoPesaje::MAX_CANTIDAD]),
            'pesadas.*.cantidad.max' => __('mensajes.cosecha.pesada_cantidad', ['maximo' => CalculoPesaje::MAX_CANTIDAD]),
            'pesadas.*.peso_pesado.required' => __('mensajes.cosecha.pesada_peso', ['maximo' => Formato::numero(CalculoPesaje::MAX_PESO_PESADA_KG)]),
            'pesadas.*.peso_pesado.numeric' => __('mensajes.cosecha.pesada_peso', ['maximo' => Formato::numero(CalculoPesaje::MAX_PESO_PESADA_KG)]),
            'pesadas.*.peso_pesado.gt' => __('mensajes.cosecha.pesada_peso', ['maximo' => Formato::numero(CalculoPesaje::MAX_PESO_PESADA_KG)]),
            'pesadas.*.peso_pesado.max' => __('mensajes.cosecha.pesada_peso', ['maximo' => Formato::numero(CalculoPesaje::MAX_PESO_PESADA_KG)]),
            'peso_canasta_kg.required_with' => __('mensajes.cosecha.peso_vacia', ['tipo' => __('cosechas.nombres.canasta')]),
            'peso_canasta_kg.numeric' => __('mensajes.cosecha.peso_vacia', ['tipo' => __('cosechas.nombres.canasta')]),
            'peso_canasta_kg.min' => __('mensajes.cosecha.peso_vacia', ['tipo' => __('cosechas.nombres.canasta')]),
            'peso_canasta_kg.max' => __('mensajes.cosecha.peso_vacia', ['tipo' => __('cosechas.nombres.canasta')]),
            'peso_canastilla_kg.required_with' => __('mensajes.cosecha.peso_vacia', ['tipo' => __('cosechas.nombres.canastilla')]),
            'peso_canastilla_kg.numeric' => __('mensajes.cosecha.peso_vacia', ['tipo' => __('cosechas.nombres.canastilla')]),
            'peso_canastilla_kg.min' => __('mensajes.cosecha.peso_vacia', ['tipo' => __('cosechas.nombres.canastilla')]),
            'peso_canastilla_kg.max' => __('mensajes.cosecha.peso_vacia', ['tipo' => __('cosechas.nombres.canastilla')]),
        ];
    }
}
