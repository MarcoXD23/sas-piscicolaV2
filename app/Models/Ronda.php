<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ronda extends Model
{
    use PerteneceATenant;

    public const SIN_NOVEDAD = 'sin_novedad';

    /** Tipos de ronda: clave => texto para mostrar. */
    public const TIPOS = [
        self::SIN_NOVEDAD => 'Sin novedad',
        'intrusion' => 'Intrusión o robo',
        'depredador' => 'Depredadores (aves, nutrias, babillas…)',
        'dano' => 'Daño en infraestructura',
        'energia' => 'Corte de energía',
        'otro' => 'Otra novedad',
    ];

    protected $fillable = ['user_id', 'turno_id', 'lago_id', 'registrada_at', 'tipo', 'descripcion'];

    protected function casts(): array
    {
        return ['registrada_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lago(): BelongsTo
    {
        return $this->belongsTo(Lago::class);
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function esNovedad(): bool
    {
        return $this->tipo !== self::SIN_NOVEDAD;
    }

    public function textoTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function scopeNovedades(Builder $consulta): void
    {
        $consulta->where('tipo', '!=', self::SIN_NOVEDAD);
    }
}
