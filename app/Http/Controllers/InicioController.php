<?php

namespace App\Http\Controllers;

use App\Models\EventoAgenda;
use App\Models\HoraExtra;
use App\Models\Lago;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Ronda;
use App\Models\Tarea;
use App\Models\Turno;
use App\Support\CalculadoraRacion;
use App\Support\CoberturaBodega;
use App\Support\Indicadores;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Un solo dashboard por rol: ejecutivo (propietario), técnico (administrador),
 * operativo del turno (trabajador) y de vigilancia (celador). Quien tiene varios roles ve cada sección.
 */
class InicioController extends Controller
{
    public function __invoke(Request $request): View
    {
        $usuario = $request->user()->load('roles', 'tenant');
        $esPropietario = $usuario->tieneRol(Rol::PROPIETARIO);
        $esTecnico = $usuario->tieneRol(Rol::PROPIETARIO, Rol::ADMINISTRADOR);
        $trabajaPorTurnos = $usuario->tieneRol(...Turno::ROLES);
        $produccion = $esTecnico ? Indicadores::produccionActual() : null;

        return view('inicio', [
            'usuario' => $usuario,
            'esPropietario' => $esPropietario,
            'esTecnico' => $esTecnico,

            // Turno (trabajador y celador)
            'trabajaPorTurnos' => $trabajaPorTurnos,
            'turnoActual' => $trabajaPorTurnos ? $usuario->turnos()->enCurso()->first() : null,
            'proximoTurno' => $trabajaPorTurnos ? $usuario->proximoTurno() : null,
            'tareasPendientes' => $trabajaPorTurnos ? Tarea::query()->where('asignada_a', $usuario->id)->pendientes()->count() : 0,
            'racionesHoy' => $usuario->tieneRol(Rol::TRABAJADOR) ? $this->racionesHoy() : collect(),

            // Avisos
            'alertasBodega' => $esPropietario ? CoberturaBodega::alertas() : collect(),
            'recordatorios' => $esPropietario
                ? EventoAgenda::query()->with('lago')->paraRecordar()->orderBy('inicia_at')->limit(10)->get()
                : collect(),
            'solicitudesPendientes' => $esPropietario
                ? Permiso::query()->pendientes()->count() + HoraExtra::query()->pendientes()->count()
                : 0,
            'novedadesNoche' => $esTecnico
                ? Ronda::novedades()->with(['lago', 'usuario'])->where('registrada_at', '>=', now()->subDay())->latest('registrada_at')->get()
                : collect(),
            'lagosConAlertaAgua' => ($esTecnico || $usuario->tieneRol(Rol::CELADOR)) ? $this->lagosConAlertaAgua() : collect(),

            // Producción (propietario y administrador)
            'lagosActivos' => $produccion['lagos_activos'] ?? 0,
            'biomasaTotal' => $produccion['biomasa_kg'] ?? 0.0,
            'pecesVivos' => $produccion['peces_vivos'] ?? 0,
            'mortalidad' => $esTecnico ? Indicadores::mortalidadReciente() : null,
            'biomasaPorLago' => $esTecnico ? Indicadores::biomasaPorLago() : collect(),
            'mortalidadDiaria' => $esTecnico ? Indicadores::mortalidadDiaria() : collect(),
            // Consumo de alimento: parte del dashboard ejecutivo (la bodega es del propietario)
            'alimento30' => $esPropietario ? Indicadores::alimentoReciente() : null,
            'alimentoDiario' => $esPropietario ? Indicadores::alimentoDiario() : collect(),
        ]);
    }

    /** @return Collection<int, array{lago: Lago, racion: array<string, mixed>}> */
    private function racionesHoy(): Collection
    {
        return Lago::query()->has('siembrasActivas')->with(['siembrasActivas', 'ultimaMedicion'])->orderBy('nombre')->get()
            ->map(fn (Lago $lago) => ['lago' => $lago, 'racion' => CalculadoraRacion::paraLago($lago)]);
    }

    /** Lagos cuya medición de las últimas 24 horas tiene alertas. @return Collection<int, array{lago: Lago, alertas: list<string>}> */
    private function lagosConAlertaAgua(): Collection
    {
        return Lago::query()->with(['siembrasActivas', 'ultimaMedicion'])->orderBy('nombre')->get()
            ->filter(fn (Lago $lago) => $lago->ultimaMedicion?->medido_at->gte(now()->subHours(CalculadoraRacion::HORAS_MEDICION_VIGENTE)))
            ->map(fn (Lago $lago) => ['lago' => $lago, 'alertas' => $lago->ultimaMedicion->alertas($lago->siembrasActivas->pluck('especie'))])
            ->filter(fn ($item) => $item['alertas'] !== [])
            ->values();
    }
}
