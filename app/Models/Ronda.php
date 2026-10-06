<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ronda extends Model
{
    use PerteneceATenant;

    public const SIN_NOVEDAD = 'sin_novedad';

    /** Tipos de ronda (el texto de cada uno está en lang/es/noche.php, «tipos_ronda»). */
    public const TIPOS = [self::SIN_NOVEDAD, 'intrusion', 'depredador', 'dano', 'energia', 'otro'];

    /** @return array<string, string> código => texto en el idioma de la finca */
    public static function opciones(): array
    {
        return collect(self::TIPOS)->mapWithKeys(fn (string $codigo) => [$codigo => __('noche.tipos_ronda.'.$codigo)])->all();
    }

    protected $fillable = ['user_id', 'turno_id', 'lago_id', 'registrada_at', 'tipo', 'descripcion'];

    protected function casts(): array
    {
        return ['registrada_at' => InstanteLocal::class];
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
        return in_array($this->tipo, self::TIPOS, true) ? __('noche.tipos_ronda.'.$this->tipo) : $this->tipo;
    }

    public function scopeNovedades(Builder $consulta): void
    {
        $consulta->where('tipo', '!=', self::SIN_NOVEDAD);
    }
}
