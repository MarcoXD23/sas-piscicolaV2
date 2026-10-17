<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lago;
use App\Support\ComparacionProveedores;
use App\Support\ConversionAlimenticia;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Conversión alimenticia por lago, por siembra, por especie y por proveedor de alevinos, con tendencia semanal
 * (propietario y administrador).
 */
class ConversionController extends Controller
{
    public const SEMANAS = 12;

    public function index(Request $request): View
    {
        $conversion = new ConversionAlimenticia;
        $lagos = Lago::query()->orderBy('nombre')->get();
        $lago = $lagos->firstWhere('id', (int) $request->query('lago')) ?? $lagos->first(fn (Lago $lago) => $lago->siembrasActivas()->exists()) ?? $lagos->first();

        return view('admin.conversion.index', [
            'porLago' => $conversion->porLago(),
            'porEspecie' => $conversion->porEspecie(),
            // Comparación de proveedores: crecimiento, supervivencia y conversión (comparte las consultas de $conversion)
            'porProveedor' => (new ComparacionProveedores($conversion))->filas(),
            'lagos' => $lagos,
            'lago' => $lago,
            'semanas' => $lago ? $conversion->semanal($lago, self::SEMANAS) : [],
        ]);
    }
}
