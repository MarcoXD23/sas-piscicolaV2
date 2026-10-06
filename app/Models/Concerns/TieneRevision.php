<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitudes que el propietario aprueba o rechaza (permisos y horas extra).
 */
trait TieneRevision
{
    public static function estados(): array
    {
        return ['pendiente', 'aprobado', 'rechazado'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function scopePendientes(Builder $consulta): void
    {
        $consulta->where('estado', 'pendiente');
    }

    public function scopeAprobadas(Builder $consulta): void
    {
        $consulta->where('estado', 'aprobado');
    }

    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    public function textoEstado(): string
    {
        return ['pendiente' => 'Pendiente', 'aprobado' => 'Aprobado', 'rechazado' => 'Rechazado'][$this->estado] ?? $this->estado;
    }
}
