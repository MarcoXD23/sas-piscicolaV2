<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un ajuste de operación que la finca cambió (clave → valor). Leer y guardar siempre con App\Support\Ajustes\AjustesFinca,
 * que aplica el valor por defecto del catálogo, valida y deja el cambio en la bitácora.
 */
class AjusteFinca extends Model
{
    use PerteneceATenant;

    protected $table = 'ajustes_finca';

    protected $fillable = ['clave', 'valor', 'cambiado_por'];

    protected function casts(): array
    {
        return ['valor' => 'json'];
    }

    public function cambiadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cambiado_por');
    }
}
