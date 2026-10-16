<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAlimento extends Model
{
    use HasFactory, PerteneceATenant;

    protected $table = 'stocks_alimento';

    protected $fillable = [
        'concentrado_id',
        'tipo_concentrado',
        'cantidad_kg',
    ];

    public function concentrado(): BelongsTo
    {
        return $this->belongsTo(Concentrado::class);
    }

    /**
     * Stock de un concentrado, bloqueado para descontar o sumar: el ligado a ese concentrado del catálogo o, si no
     * hay, el que tiene ese texto (los nombres de la bodega que no están en el catálogo siguen funcionando).
     */
    public static function paraBloquear(?Concentrado $concentrado, string $texto): ?self
    {
        return self::query()
            ->where(fn ($consulta) => $consulta->where('tipo_concentrado', $texto)
                ->when($concentrado, fn ($o) => $o->orWhere('concentrado_id', $concentrado->id)))
            // Primero el ligado al concentrado; si no, el del mismo texto
            ->when($concentrado, fn ($consulta) => $consulta->orderByRaw('CASE WHEN concentrado_id = ? THEN 0 ELSE 1 END', [$concentrado->id]))
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }
}
