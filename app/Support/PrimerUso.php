<?php

namespace App\Support;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;

/**
 * Lista de primer uso de una finca nueva, en el inicio del propietario y del administrador.
 * Cada paso se marca solo al cumplirse. La lista no vuelve a aparecer cuando se oculta o cuando se completa
 * (aunque después se deshaga algún paso, por ejemplo al borrar el único lago).
 */
final class PrimerUso
{
    /** Marca un paso que no se deduce de los datos (ajustes_revisados_at, especies_revisadas_at, pesos_definidos_at). */
    public static function marcar(Tenant $finca, string $columna): void
    {
        if ($finca->{$columna} === null) {
            $finca->forceFill([$columna => now()])->save();
        }
    }

    /** ¿Se muestra la lista a esta persona? */
    public static function visible(User $usuario): bool
    {
        $finca = $usuario->tenant;

        return $usuario->tieneRol(Rol::PROPIETARIO, Rol::ADMINISTRADOR)
            && $finca->primer_uso_oculto_at === null
            && $finca->primer_uso_completado_at === null;
    }

    /**
     * Pasos con su estado. Los de Ajustes y Personal los hace el propietario: al administrador no se le ofrece el enlace.
     *
     * @return list<array{clave: string, hecho: bool, ruta: ?string}>
     */
    public static function pasos(User $usuario): array
    {
        $finca = $usuario->tenant;
        $esPropietario = $usuario->tieneRol(Rol::PROPIETARIO);
        $esAdministrador = $usuario->tieneRol(Rol::ADMINISTRADOR);

        // Personal de la finca (todos menos el propietario)
        $personal = User::query()->whereDoesntHave('roles', fn ($q) => $q->where('nombre', Rol::PROPIETARIO));

        return [
            ['clave' => 'ajustes', 'hecho' => $finca->ajustes_revisados_at !== null, 'ruta' => $esPropietario ? route('admin.ajustes.edit') : null],
            ['clave' => 'especies', 'hecho' => $finca->especies_revisadas_at !== null, 'ruta' => route('admin.especies.index')],
            ['clave' => 'pesos', 'hecho' => $finca->pesos_definidos_at !== null, 'ruta' => $esAdministrador ? route('admin.ajustes-pesaje.edit') : null],
            ['clave' => 'lago', 'hecho' => Lago::query()->exists(), 'ruta' => route('admin.lagos.index')],
            ['clave' => 'invitar', 'hecho' => (clone $personal)->exists(), 'ruta' => $esPropietario ? route('admin.personal.index') : null],
            ['clave' => 'aprobar', 'hecho' => (clone $personal)->whereNotNull('aprobado_at')->whereHas('roles', fn ($q) => $q->where('nombre', Rol::TRABAJADOR))->exists(),
                'ruta' => $esPropietario ? route('admin.personal.index') : null],
        ];
    }

    /** Pasos para el inicio; si ya están todos, la lista queda completada para siempre y no se muestra. */
    public static function paraInicio(User $usuario): ?array
    {
        if (! self::visible($usuario)) {
            return null;
        }

        $pasos = self::pasos($usuario);
        if (collect($pasos)->every(fn (array $paso) => $paso['hecho'])) {
            self::marcar($usuario->tenant, 'primer_uso_completado_at');

            return null;
        }

        return $pasos;
    }
}
