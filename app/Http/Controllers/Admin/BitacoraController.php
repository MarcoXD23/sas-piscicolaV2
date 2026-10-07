<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\User;
use App\Support\Region;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Bitácora de la finca (solo lectura, exclusiva del propietario) con filtros por persona, rol, fechas y lago.
 */
class BitacoraController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'rol' => ['nullable', Rule::in(Rol::TODOS)],
            'lago_id' => ['nullable', 'integer', Rule::exists('lagos', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        // Las fechas del filtro son días de la finca: se convierten al rango de instantes correspondiente
        $inicioDia = fn (string $fecha) => CarbonImmutable::parse($fecha, Region::zona())->startOfDay();

        $registros = Bitacora::query()->with(['usuario', 'lago'])
            ->when($filtros['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filtros['rol'] ?? null, fn ($q, $rol) => $q->where('rol', $rol))
            ->when($filtros['lago_id'] ?? null, fn ($q, $id) => $q->where('lago_id', $id))
            ->when($filtros['desde'] ?? null, fn ($q, $fecha) => $q->where('ocurrido_at', '>=', $inicioDia($fecha)))
            ->when($filtros['hasta'] ?? null, fn ($q, $fecha) => $q->where('ocurrido_at', '<', $inicioDia($fecha)->addDay()))
            ->latest('ocurrido_at')->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.bitacora.index', [
            'registros' => $registros,
            'filtros' => $filtros,
            'personas' => User::query()->orderBy('name')->pluck('name', 'id')->all(),
            'roles' => collect(Rol::TODOS)->mapWithKeys(fn ($rol) => [$rol => __('bitacora.roles.'.$rol)])->all(),
            'lagos' => Lago::query()->orderBy('nombre')->pluck('nombre', 'id')->all(),
        ]);
    }
}
