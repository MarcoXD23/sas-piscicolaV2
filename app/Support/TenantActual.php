<?php

namespace App\Support;

/**
 * Tenant (finca) de la petición en curso.
 * Lo fija el middleware EstablecerTenant a partir del usuario autenticado.
 * Sin finca activa, las consultas de modelos de finca fallan (SinFincaActiva) salvo dentro de sinFiltro().
 */
class TenantActual
{
    private ?int $id = null;

    private int $sinFiltro = 0;

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

    /**
     * Única forma explícita de consultar varias fincas a la vez (identidad en el login, panel de plataforma).
     * Las consultas deben ejecutarse dentro del callback: un builder devuelto y usado después vuelve a fallar cerrado.
     *
     * @template T
     *
     * @param  callable(): T  $accion
     * @return T
     */
    public static function sinFiltro(callable $accion): mixed
    {
        $actual = app(self::class);
        $actual->sinFiltro++;

        try {
            return $accion();
        } finally {
            $actual->sinFiltro--;
        }
    }

    public function filtroDesactivado(): bool
    {
        return $this->sinFiltro > 0;
    }
}
