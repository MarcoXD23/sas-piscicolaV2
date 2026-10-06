<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\TenantActual;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aplica el TenantScope y asigna tenant_id automáticamente al crear.
 * Todo modelo de negocio debe usar este trait.
 */
trait PerteneceATenant
{
    public static function bootPerteneceATenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $tenantId = app(TenantActual::class)->id();

            if ($model->getAttribute('tenant_id') === null && $tenantId !== null) {
                $model->setAttribute('tenant_id', $tenantId);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

