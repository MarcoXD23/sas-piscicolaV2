<?php

namespace App\Support;

/**
 * Tenant (finca) de la petición en curso.
 * Lo fija el middleware EstablecerTenant a partir del usuario autenticado.
 */
class TenantActual
{
    private ?int $id = null;

    public function establecer(?int $id): void
    {
        if ($id !== $this->id) {
            Region::olvidar();
        }
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function definido(): bool
    {
        return $this->id !== null;
    }
}
