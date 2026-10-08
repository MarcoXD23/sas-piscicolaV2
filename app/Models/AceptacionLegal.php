<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aceptación de los términos de uso y la política de privacidad: quién, qué versión y cuándo. Sin la IP.
 */
class AceptacionLegal extends Model
{
    use PerteneceATenant;

    protected $table = 'aceptaciones_legales';

    protected $fillable = ['user_id', 'version', 'aceptado_at'];

    protected function casts(): array
    {
        return ['aceptado_at' => InstanteLocal::class];
    }

    /** Registra que la persona acepta la versión vigente (si ya la había aceptado, no se duplica). */
    public static function registrar(User $usuario): self
    {
        // Por persona (no por finca activa): en el registro todavía no hay finca activa
        $existente = self::query()->withoutGlobalScopes()->where('user_id', $usuario->id)->where('version', config('legal.version'))->first();
        if ($existente) {
            return $existente;
        }

        $aceptacion = new self(['user_id' => $usuario->id, 'version' => config('legal.version'), 'aceptado_at' => now()]);
        $aceptacion->forceFill(['tenant_id' => $usuario->tenant_id])->save();

        return $aceptacion;
    }

    /** ¿La persona ya aceptó la versión vigente? */
    public static function vigente(User $usuario): bool
    {
        return self::query()->withoutGlobalScopes()->where('user_id', $usuario->id)->where('version', config('legal.version'))->exists();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
