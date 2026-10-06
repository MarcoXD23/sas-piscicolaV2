<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Turno extends Model
{
    use PerteneceATenant;

    /** Roles que trabajan por turnos. */
    public const ROLES = [Rol::TRABAJADOR, Rol::CELADOR];

    protected $fillable = ['user_id', 'rol', 'inicia_at', 'termina_at', 'asignado_por'];

    protected function casts(): array
    {
        return [
            'inicia_at' => InstanteLocal::class,
            'termina_at' => InstanteLocal::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }

    /** Turnos en curso en el momento indicado (por defecto, ahora). */
    public function scopeEnCurso(Builder $consulta, ?CarbonInterface $momento = null): void
    {
        $momento ??= now();
        $consulta->where('inicia_at', '<=', $momento)->where('termina_at', '>', $momento);
    }

    /** Turnos que se cruzan con el rango [inicio, fin). */
    public function scopeQueSeCruzanCon(Builder $consulta, CarbonInterface $inicio, CarbonInterface $fin): void
    {
        $consulta->where('inicia_at', '<', $fin)->where('termina_at', '>', $inicio);
    }

    public function haEmpezado(): bool
    {
        return $this->inicia_at->lte(now());
    }

    public function estaEnCurso(): bool
    {
        return $this->haEmpezado() && $this->termina_at->gt(now());
    }
}
