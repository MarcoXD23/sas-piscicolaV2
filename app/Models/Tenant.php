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

    protected $fillable = ['nombre', 'codigo', 'pais', 'zona_horaria', 'idioma'];

    protected function casts(): array
    {
        return ['suspendida_at' => InstanteLocal::class];
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
