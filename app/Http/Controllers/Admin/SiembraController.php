<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Especie;
use App\Models\Lago;
use App\Models\Siembra;
use App\Models\Tercero;
use App\Support\NumeroLote;
use App\Support\Region;
use Carbon\CarbonImmutable;
use App\Support\ReglasPolicultivo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SiembraController extends Controller
{
    public function store(Request $request, Lago $lago): RedirectResponse
    {
        $datos = $request->validate([
            // Solo especies activas del catálogo de esta finca
            'especie_id' => ['required', 'integer', Rule::exists('especies', 'id')->where('tenant_id', $request->user()->tenant_id)->where('activo', true)],
            // Proveedor del catálogo: un tercero activo de esta finca que provee alevinos (se comprueba abajo)
            'proveedor_id' => ['nullable', 'integer'],
            'lote_alevinos' => ['nullable', 'string', 'max:100'],
            'numero_lote' => ['nullable', 'string', 'max:40'],
            'fecha' => ['required', 'date', 'before_or_equal:'.Region::hoy()->toDateString()],
            'cantidad_alevinos' => ['required', 'integer', 'min:1', 'max:10000000'],
            'peso_promedio_inicial_g' => ['required', 'numeric', 'min:0.1', 'max:20000'],
        ], [
            'especie_id.exists' => __('mensajes.siembra.especie_no_disponible'),
        ]);
        if (! empty($datos['proveedor_id']) && ! Tercero::query()->queProveen(Tercero::PROVEE_ALEVINOS)->activos()->whereKey($datos['proveedor_id'])->exists()) {
            throw ValidationException::withMessages(['proveedor_id' => __('mensajes.siembra.proveedor_no_disponible')]);
        }
        $datos['proveedor_id'] = ! empty($datos['proveedor_id']) ? (int) $datos['proveedor_id'] : null;
        $datos['numero_lote'] = filled($datos['numero_lote'] ?? null) ? trim($datos['numero_lote']) : null;
        $especie = Especie::findOrFail($datos['especie_id']);
        $confirmado = $request->boolean(ReglasPolicultivo::CAMPO_CONFIRMACION);

        DB::transaction(function () use ($lago, $datos, $especie, $confirmado) {
            // Número de lote: con numeración automática lo pone la app (lo que llegue escrito no cuenta)
            if (NumeroLote::esAutomatica()) {
                $datos['numero_lote'] = NumeroLote::siguiente(CarbonImmutable::parse($datos['fecha']));
            }

            // Bloquea el lago para que dos siembras simultáneas no se salten las reglas de policultivo
            Lago::whereKey($lago->id)->lockForUpdate()->first();
            $activas = $lago->siembrasActivas()->get();

            // La misma especie puede estar dos veces como lotes de fecha distinta; frío con cálido se confirma
            ReglasPolicultivo::verificarLote($activas, $especie, CarbonImmutable::parse($datos['fecha'])->toDateString(), 'especie_id');
            ReglasPolicultivo::verificarClima($activas, $especie, $confirmado);

            $peces = (int) $datos['cantidad_alevinos'];
            $peso = (float) $datos['peso_promedio_inicial_g'];

            // En la siembra, el stock actual es igual al inicial
            $lago->siembras()->create($datos + [
                'peces_vivos' => $peces,
                'peso_actual_g' => $peso,
                'biomasa_kg' => ($peces * $peso) / 1000,
            ]);
        });

        return redirect()->route('admin.lagos.index')
            ->with('estado', __('mensajes.siembra.registrada', ['especie' => $especie->nombre, 'lago' => $lago->nombre]));
    }

    /**
     * Cierra el ciclo de una siembra (el registro se conserva como histórico) y libera su lugar en el lago.
     */
    public function finalizar(Siembra $siembra): RedirectResponse
    {
        DB::transaction(function () use ($siembra) {
            $bloqueada = Siembra::whereKey($siembra->id)->lockForUpdate()->first();

            if (! $bloqueada->estaActiva()) {
                throw ValidationException::withMessages(['siembra' => __('mensajes.siembra.esta_siembra_ya_estaba_finalizada')]);
            }

            $bloqueada->update(['finalizada_at' => now()]);
        });

        return redirect()->route('admin.lagos.index')
            ->with('estado', __('mensajes.siembra.finalizada', ['especie' => $siembra->nombre_especie, 'lago' => $siembra->lago->nombre]));
    }
}
