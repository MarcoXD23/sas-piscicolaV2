<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cosecha;
use App\Models\Siembra;
use App\Models\Tratamiento;
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
            'fecha' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$siembra->fecha->format('Y-m-d')],
            'peces' => ['required', 'integer', 'min:1'],
            // Dos modos: total neto (kg) o canastas (tara común + peso bruto de cada canasta)
            'kg' => ['nullable', 'required_without:pesajes', 'numeric', 'min:0.1', 'max:9999999'],
            'pesajes' => ['nullable', 'array', 'min:1', 'max:'.self::MAX_CANASTAS],
            'pesajes.*' => ['required', 'numeric', 'min:0.1', 'max:9999'],
            'tara_kg' => ['nullable', 'required_with:pesajes', 'numeric', 'min:0', 'max:999'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ], [
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
            'fecha.after_or_equal' => 'La fecha no puede ser anterior a la siembra.',
            'peces.min' => 'Cosecha al menos un pez.',
            'kg.required_without' => 'Registra el peso neto o el peso de cada canasta.',
            'kg.min' => 'Registra el peso cosechado.',
            'pesajes.max' => 'Máximo '.self::MAX_CANASTAS.' canastas por cosecha.',
            'pesajes.*.required' => 'Escribe el peso de cada canasta.',
            'pesajes.*.numeric' => 'El peso de cada canasta debe ser un número.',
            'tara_kg.required_with' => 'Escribe la tara (peso de la canasta vacía).',
        ]);

        $canastas = $this->canastas($datos);

        $cosecha = DB::transaction(function () use ($siembra, $datos, $request, $canastas) {
            $bloqueada = Siembra::whereKey($siembra->id)->lockForUpdate()->first();

            if (! $bloqueada->estaActiva()) {
                throw ValidationException::withMessages(['peces' => 'Esta siembra ya está finalizada.']);
            }

            // Bloqueo sanitario: no se cosecha mientras corre el período de retiro de un tratamiento
            $fecha = CarbonImmutable::parse($datos['fecha']);
            $retiro = Tratamiento::where('lago_id', $bloqueada->lago_id)->enRetiro($fecha)->get()
                ->sortByDesc(fn (Tratamiento $t) => $t->finRetiro())->first();
            if ($retiro) {
                throw ValidationException::withMessages([
                    'fecha' => "El lago está en retiro sanitario hasta el {$retiro->finRetiro()->format('d/m/Y')} por el tratamiento con {$retiro->producto}: no se puede cosechar.",
                ]);
            }

            $peces = (int) $datos['peces'];
            if ($peces > $bloqueada->peces_vivos) {
                throw ValidationException::withMessages(['peces' => "Solo hay {$bloqueada->peces_vivos} peces vivos en esta siembra."]);
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

        return back()->with('estado', number_format($cosecha->kg, 1, ',', '.')." kg cosechados ({$cosecha->peces} peces de "
            .number_format($cosecha->peso_promedio_g, 0, ',', '.').' g en promedio'
            .($canastas !== [] ? ', en '.count($canastas).' canastas' : '').').');
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
                    "pesajes.{$indice}" => 'La canasta '.($indice + 1).' pesa igual o menos que la tara: revisa el peso.',
                ]);
            }
            $canastas[] = ['numero' => $indice + 1, 'bruto_kg' => $bruto, 'tara_kg' => $tara, 'neto_kg' => round($bruto - $tara, 2)];
        }

        return $canastas;
    }
}
