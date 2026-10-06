<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use App\Support\Region;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tratamiento extends Model
{
    use PerteneceATenant;

    public const VIAS = ['baño', 'alimento', 'agua'];

    /** @return array<string, string> código => texto en el idioma de la finca */
    public static function opciones(): array
    {
        return collect(self::VIAS)->mapWithKeys(fn (string $codigo) => [$codigo => __('lagos.vias.'.$codigo)])->all();
    }

    protected $fillable = [
        'lago_id', 'user_id', 'fecha_inicio', 'fecha_fin', 'motivo', 'producto', 'registro_sanitario', 'dosis', 'via', 'dias_retiro', 'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'dias_retiro' => 'integer',
        ];
    }

    public function lago(): BelongsTo
    {
        return $this->belongsTo(Lago::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Último día en que el pescado no se debe cosechar. */
    public function finRetiro(): CarbonInterface
    {
        return $this->fecha_fin->copy()->addDays($this->dias_retiro);
    }

    /** Tratamientos cuyo período de retiro sigue vigente en la fecha indicada (por defecto, hoy). */
    public function scopeEnRetiro(Builder $consulta, ?CarbonInterface $fecha = null): void
    {
        $consulta->whereRaw('fecha_fin + dias_retiro >= ?', [($fecha ?? Region::hoy())->toDateString()]);
    }
}
