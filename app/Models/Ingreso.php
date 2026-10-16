<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un ingreso a una cuenta de la finca, exitoso o fallido (lo registra App\Support\Seguridad\RegistroIngresos).
 * Sin la IP completa: solo la red recortada. Ver la migración create_ingresos_table.
 */
class Ingreso extends Model
{
    use PerteneceATenant;

    protected $table = 'ingresos';

    public const CANAL_WEB = 'web';

    public const CANAL_API = 'api';

    /** Por qué falló: contraseña equivocada, código del segundo paso, cuenta sin aprobar o finca suspendida. */
    public const MOTIVO_CONTRASENA = 'contrasena';

    public const MOTIVO_CODIGO = 'codigo';

    public const MOTIVO_PENDIENTE = 'pendiente';

    public const MOTIVO_SUSPENDIDA = 'suspendida';

    /** Días que se guardan (limpieza diaria en routes/console.php). */
    public const DIAS_GUARDADOS = 90;

    protected $fillable = ['user_id', 'exitoso', 'motivo', 'canal', 'equipo', 'red', 'equipo_hash', 'equipo_nuevo'];

    protected function casts(): array
    {
        return [
            'exitoso' => 'boolean',
            'equipo_nuevo' => 'boolean',
            'aviso_cerrado_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Ingresos desde un equipo nuevo cuyo aviso la persona todavía no cerró. */
    public function scopeAvisoPendiente(Builder $consulta): void
    {
        $consulta->where('exitoso', true)->where('equipo_nuevo', true)->whereNull('aviso_cerrado_at');
    }
}
