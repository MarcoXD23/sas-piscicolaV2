<?php

namespace App\Models\Concerns;

use App\Exceptions\SinFincaActiva;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\TenantActual;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aplica el TenantScope (falla cerrado sin finca activa) y asigna tenant_id automáticamente al crear.
 * Todo modelo de negocio debe usar este trait.
 */
trait PerteneceATenant
{
    public static function bootPerteneceATenant(): void
    {
        static::addGlobalScope(new TenantScope);

        // Sin tenant_id explícito se usa la finca activa; sin ninguna de las dos, falla cerrado
        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id') === null) {
                $model->setAttribute('tenant_id', app(TenantActual::class)->id() ?? throw SinFincaActiva::alCrear($model::class));
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

