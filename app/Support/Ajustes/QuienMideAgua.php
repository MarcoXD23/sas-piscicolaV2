<?php

namespace App\Support\Ajustes;

use App\Models\Rol;

/**
 * Quién mide el agua en la finca activa (ajuste «agua.quien_mide», F4): el celador, el administrador o los dos.
 * Lo usan el controlador de mediciones (el servidor decide siempre), el paquete de Registro rápido y las pantallas.
 */
final class QuienMideAgua
{
    /** ¿Este rol (celador o administrador) puede registrar mediciones del agua en la finca activa? */
    public static function puedeMedir(string $rol): bool
    {
        $valor = AjustesFinca::valor(CatalogoAjustes::AGUA_QUIEN_MIDE);

        return $valor === CatalogoAjustes::QUIEN_MIDE_AMBOS || ($rol === Rol::CELADOR && $valor === CatalogoAjustes::QUIEN_MIDE_CELADOR)
            || ($rol === Rol::ADMINISTRADOR && $valor === CatalogoAjustes::QUIEN_MIDE_ADMINISTRADOR);
    }

    /** Texto que explica quién mide (para el 403 y para la nota en la pantalla del rol que no mide). */
    public static function aviso(): string
    {
        return __('mensajes.medicion_agua.quien_mide.'.AjustesFinca::valor(CatalogoAjustes::AGUA_QUIEN_MIDE));
    }
}
