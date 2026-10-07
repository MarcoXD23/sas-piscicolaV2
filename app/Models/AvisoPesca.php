<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aviso «listo para pescar»: lo reporta el trabajador en su turno y lo atiende el administrador
 * (a mano o al registrar una cosecha de esa siembra). Uno pendiente por lago como máximo.
 */
class AvisoPesca extends Model
{
    use PerteneceATenant;

    public const PENDIENTE = 'pendiente';

    public const ATENDIDO = 'atendido';

    protected $table = 'avisos_pesca';

    protected $fillable = ['lago_id', 'siembra_id', 'user_id', 'nota', 'estado', 'reportado_at', 'atendido_at', 'atendido_por'];

    protected function casts(): array
    {
        return [
            'reportado_at' => InstanteLocal::class,
            'atendido_at' => InstanteLocal::class,
        ];
    }

    public function scopePendientes(Builder $consulta): void
    {
        $consulta->where('estado', self::PENDIENTE);
    }

    public function estaPendiente(): bool
    {
        return $this->estado === self::PENDIENTE;
    }

    public function lago(): BelongsTo
    {
        return $this->belongsTo(Lago::class);
    }

    public function siembra(): BelongsTo
    {
        return $this->belongsTo(Siembra::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function atendidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendido_por');
    }

    /** Marca atendidos los avisos pendientes de una siembra (por ejemplo, al cosecharla). */
    public static function atenderDeSiembra(Siembra $siembra, User $quien): int
    {
        return self::query()->pendientes()->where('siembra_id', $siembra->id)
            ->update(['estado' => self::ATENDIDO, 'atendido_at' => now(), 'atendido_por' => $quien->id]);
    }
}
