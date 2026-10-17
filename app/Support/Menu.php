<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Entradas del menú principal y quién las ve. Un solo lugar: lo usan el layout (el menú) y el asistente (la guía de
 * la app de cada rol, en asistente.guia.pantallas), para que la guía nunca nombre una pantalla que la persona no ve.
 */
final class Menu
{
    /**
     * Todas las entradas, en el orden del menú. roles null = todos.
     *
     * @return list<array{ruta: string, activa?: string|list<string>, texto: string, icono: string, roles: ?list<string>}>
     */
    public static function entradas(): array
    {
        return [
            ['ruta' => 'inicio', 'texto' => __('layout.menu.inicio'), 'icono' => 'inicio', 'roles' => null],
            // Registro rápido: registrar sin conexión con el paquete de trabajo del teléfono (no para el propietario)
            ['ruta' => 'campo', 'texto' => __('layout.menu.registro_rapido'), 'icono' => 'mas', 'roles' => ['administrador', 'trabajador', 'celador']],
            ['ruta' => 'admin.lagos.index', 'activa' => ['admin.lagos.*', 'admin.cosechas.*'], 'texto' => __('layout.menu.lagos'), 'icono' => 'lagos', 'roles' => ['propietario', 'administrador', 'trabajador']],
            ['ruta' => 'admin.bodega.index', 'texto' => __('layout.menu.bodega'), 'icono' => 'bodega', 'roles' => ['propietario', 'administrador']],
            ['ruta' => 'admin.ventas.index', 'activa' => 'admin.ventas.*', 'texto' => __('layout.menu.ventas'), 'icono' => 'dinero', 'roles' => ['propietario', 'administrador']],
            ['ruta' => 'admin.jornaleros.index', 'activa' => 'admin.jornaleros.*', 'texto' => __('layout.menu.jornaleros'), 'icono' => 'personal', 'roles' => ['propietario', 'administrador']],
            ['ruta' => 'admin.proveedores.index', 'activa' => 'admin.proveedores.*', 'texto' => __('layout.menu.proveedores'), 'icono' => 'bodega', 'roles' => ['propietario', 'administrador']],
            ['ruta' => 'admin.agenda.index', 'activa' => 'admin.agenda.*', 'texto' => __('layout.menu.agenda'), 'icono' => 'calendario', 'roles' => ['propietario']],
            ['ruta' => 'admin.personal.index', 'activa' => ['admin.personal.*', 'admin.turnos.*', 'admin.solicitudes.*', 'admin.nomina.*'], 'texto' => __('layout.menu.personal'), 'icono' => 'personal', 'roles' => ['propietario']],
            ['ruta' => 'noche.index', 'activa' => 'noche.*', 'texto' => __('layout.menu.noche'), 'icono' => 'luna', 'roles' => ['propietario', 'administrador', 'celador']],
            ['ruta' => 'admin.tareas.index', 'activa' => 'admin.tareas.*', 'texto' => __('layout.menu.tareas'), 'icono' => 'tareas', 'roles' => ['administrador']],
            ['ruta' => 'admin.alertas.index', 'activa' => 'admin.alertas.*', 'texto' => __('layout.menu.alertas'), 'icono' => 'alerta', 'roles' => ['propietario', 'administrador']],
            ['ruta' => 'admin.conversion.index', 'activa' => 'admin.conversion.*', 'texto' => __('layout.menu.conversion'), 'icono' => 'biomasa', 'roles' => ['propietario', 'administrador']],
            ['ruta' => 'admin.bitacora.index', 'activa' => 'admin.bitacora.*', 'texto' => __('layout.menu.bitacora'), 'icono' => 'reloj', 'roles' => ['propietario']],
            ['ruta' => 'admin.reportes.index', 'activa' => 'admin.reportes.*', 'texto' => __('layout.menu.reportes'), 'icono' => 'reporte', 'roles' => ['propietario', 'administrador']],
            ['ruta' => 'admin.ajustes-pesaje.edit', 'activa' => 'admin.ajustes-pesaje.*', 'texto' => __('cosechas.ajustes.enlace'), 'icono' => 'ajustes', 'roles' => ['administrador']],
            ['ruta' => 'admin.ajustes.edit', 'activa' => 'admin.ajustes.*', 'texto' => __('region.ajustes.enlace'), 'icono' => 'ajustes', 'roles' => ['propietario']],
            ['ruta' => 'mi-trabajo.index', 'activa' => 'mi-trabajo.*', 'texto' => __('layout.menu.mi_trabajo'), 'icono' => 'tareas', 'roles' => ['trabajador', 'celador']],
            // Una sola entrada: la guía (todos) y, en pestañas, el catálogo de la finca (propietario y administrador)
            ['ruta' => 'especies.index', 'activa' => ['especies.*', 'admin.especies.*'], 'texto' => __('layout.menu.especies'), 'icono' => 'pez', 'roles' => ['propietario', 'administrador', 'trabajador', 'celador']],
        ];
    }

    /**
     * Entradas que ve una persona según sus roles, en el orden del menú.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function para(User $usuario): Collection
    {
        $usuario->loadMissing('roles');

        return collect(self::entradas())
            ->filter(fn (array $enlace) => $enlace['roles'] === null
                || collect($enlace['roles'])->contains(fn (string $rol) => $usuario->tieneRol($rol)))
            ->values();
    }
}
