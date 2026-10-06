<?php

namespace App\Http\Controllers;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Ronda;
use App\Models\Siembra;
use App\Models\Turno;
use App\Support\CoberturaBodega;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InicioController extends Controller
{
    public function __invoke(Request $request): View
    {
        $usuario = $request->user()->load('roles', 'tenant');
        $trabajaPorTurnos = $usuario->tieneRol(...Turno::ROLES);

        return view('inicio', [
            'usuario' => $usuario,
            'turnoActual' => $trabajaPorTurnos ? $usuario->turnos()->enCurso()->first() : null,
            'proximoTurno' => $trabajaPorTurnos ? $usuario->proximoTurno() : null,
            'trabajaPorTurnos' => $trabajaPorTurnos,
            // La bodega es exclusiva del propietario
            'alertasBodega' => $usuario->tieneRol(Rol::PROPIETARIO) ? CoberturaBodega::alertas() : collect(),
            'novedadesNoche' => $usuario->tieneRol(Rol::PROPIETARIO, Rol::ADMINISTRADOR)
                ? Ronda::novedades()->with(['lago', 'usuario'])->where('registrada_at', '>=', now()->subDay())->latest('registrada_at')->get()
                : collect(),
            'lagosActivos' => Lago::has('siembrasActivas')->count(),
            'biomasaTotal' => (float) Siembra::activas()->sum('biomasa_kg'),
            'pecesVivos' => (int) Siembra::activas()->sum('peces_vivos'),
        ]);
    }
}
