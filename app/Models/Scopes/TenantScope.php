<?php

namespace App\Models\Scopes;

use App\Exceptions\SinFincaActiva;
use App\Support\TenantActual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Único global scope multi-tenant del proyecto: filtra por tenant_id.
 * Falla cerrado: sin finca activa lanza SinFincaActiva, salvo dentro de TenantActual::sinFiltro().
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantActual = app(TenantActual::class);

        if ($tenantActual->filtroDesactivado()) {
            return;
        }

        $tenantId = $tenantActual->id() ?? throw SinFincaActiva::alConsultar($model::class);

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
