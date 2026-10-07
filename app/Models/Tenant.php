<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Support\Region;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre', 'codigo', 'pais', 'zona_horaria', 'idioma',
        'alerta_stock_dias', 'alerta_mortalidad_porcentaje', 'alerta_mortalidad_dias', 'alerta_despacho_dias',
    ];

    protected function casts(): array
    {
        return [
            'suspendida_at' => InstanteLocal::class,
            'alerta_stock_dias' => 'integer',
            'alerta_mortalidad_porcentaje' => 'float',
            'alerta_mortalidad_dias' => 'integer',
            'alerta_despacho_dias' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // La región en memoria debe reflejar siempre los ajustes guardados
        static::saved(fn () => Region::olvidar());
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function estaSuspendida(): bool
    {
        return $this->suspendida_at !== null;
    }
}
