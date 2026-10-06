<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cosecha;
use App\Models\Siembra;
use App\Models\Tratamiento;
use App\Support\Formato;
use App\Support\Region;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CosechaController extends Controller
{
    public const MAX_CANASTAS = 200;

    public function store(Request $request, Siembra $siembra): RedirectResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:'.Region::hoy()->toDateString(), 'after_or_equal:'.$siembra->fecha->format('Y-m-d')],
            'peces' => ['required', 'integer', 'min:1'],
            // Dos modos: total neto (kg) o canastas (tara común + peso bruto de cada canasta)
            'kg' => ['nullable', 'required_without:pesajes', 'numeric', 'min:0.1', 'max:9999999'],
            'pesajes' => ['nullable', 'array', 'min:1', 'max:'.self::MAX_CANASTAS],
            'pesajes.*' => ['required', 'numeric', 'min:0.1', 'max:9999'],
            'tara_kg' => ['nullable', 'required_with:pesajes', 'numeric', 'min:0', 'max:999'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ], [
            'fecha.before_or_equal' => __('mensajes.cosecha.fecha_no_puede_ser_futura'),
            'fecha.after_or_equal' => __('mensajes.cosecha.fecha_no_puede_ser_anterior'),
            'peces.min' => __('mensajes.cosecha.cosecha_menos_pez'),
            'kg.required_without' => __('mensajes.cosecha.registra_peso_neto_peso_cada'),
            'kg.min' => __('mensajes.cosecha.registra_peso_cosechado'),
            'pesajes.max' => __('mensajes.cosecha.maximo_canastas', ['maximo' => self::MAX_CANASTAS]),
            'pesajes.*.required' => __('mensajes.cosecha.escribe_peso_cada_canasta'),
            'pesajes.*.numeric' => __('mensajes.cosecha.peso_cada_canasta_debe_ser'),
            'tara_kg.required_with' => __('mensajes.cosecha.escribe_tara_peso_canasta_vacia'),
        ]);

        $canastas = $this->canastas($datos);

        $cosecha = DB::transaction(function () use ($siembra, $datos, $request, $canastas) {
            $bloqueada = Siembra::whereKey($siembra->id)->lockForUpdate()->first();

            if (! $bloqueada->estaActiva()) {
                throw ValidationException::withMessages(['peces' => __('mensajes.cosecha.esta_siembra_ya_esta_finalizada')]);
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

            // Con canastas, el total lo calcula el servidor (no se confía en un total enviado por el navegador)
            $kg = $canastas !== [] ? round(array_sum(array_column($canastas, 'neto_kg')), 2) : round((float) $datos['kg'], 2);
            $cosecha = Cosecha::create([
                'siembra_id' => $bloqueada->id,
                'lago_id' => $bloqueada->lago_id,
                'user_id' => $request->user()->id,
                'fecha' => $datos['fecha'],
                'peces' => $peces,
                'kg' => $kg,
                'peso_promedio_g' => round($kg * 1000 / $peces, 2),
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            foreach ($canastas as $canasta) {
                $cosecha->pesajes()->create($canasta);
            }

            $bloqueada->peces_vivos -= $peces;
            $bloqueada->biomasa_kg = ($bloqueada->peces_vivos * $bloqueada->peso_actual_g) / 1000;
            if ($bloqueada->peces_vivos === 0) {
                // Cosecha total: la siembra se cierra y el lago queda libre
                $bloqueada->finalizada_at = now();
            }
            $bloqueada->save();

            return $cosecha;
        });

        return back()->with('estado', __($canastas !== [] ? 'mensajes.cosecha.registrada_canastas' : 'mensajes.cosecha.registrada', [
            'kg' => Formato::numero($cosecha->kg, 1),
            'peces' => Formato::numero($cosecha->peces),
            'gramos' => Formato::numero($cosecha->peso_promedio_g),
            'canastas' => count($canastas),
        ]));
    }

    /**
     * Neto de cada canasta (bruto − tara común). Vacío si la cosecha se registra con el total neto.
     *
     * @param  array<string, mixed>  $datos
     * @return list<array{numero: int, bruto_kg: float, tara_kg: float, neto_kg: float}>
     */
    private function canastas(array $datos): array
    {
        if (empty($datos['pesajes'])) {
            return [];
        }

        $tara = round((float) $datos['tara_kg'], 2);
        $canastas = [];

        foreach (array_values($datos['pesajes']) as $indice => $bruto) {
            $bruto = round((float) $bruto, 2);
            if ($bruto <= $tara) {
                throw ValidationException::withMessages([
                    "pesajes.{$indice}" => __('mensajes.cosecha.canasta_bajo_tara', ['numero' => $indice + 1]),
                ]);
            }
            $canastas[] = ['numero' => $indice + 1, 'bruto_kg' => $bruto, 'tara_kg' => $tara, 'neto_kg' => round($bruto - $tara, 2)];
        }

        return $canastas;
    }
}
