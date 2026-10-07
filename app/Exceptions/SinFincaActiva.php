<?php

namespace App\Exceptions;

use LogicException;

/**
 * Se consultó o creó un modelo de finca sin finca activa y fuera de TenantActual::sinFiltro().
 * Es un error de programación: el filtro por tenant_id falla cerrado en lugar de devolver datos de todas las fincas.
 */
class SinFincaActiva extends LogicException
{
    public static function alConsultar(string $modelo): self
    {
        return new self(__('comun.sin_finca_activa.consulta', ['modelo' => class_basename($modelo)]));
    }

    public static function alCrear(string $modelo): self
    {
        return new self(__('comun.sin_finca_activa.creacion', ['modelo' => class_basename($modelo)]));
    }
}
