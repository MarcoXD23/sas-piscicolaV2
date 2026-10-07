<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto real de una especie, subida por el administrador. Dos archivos en el disco privado «fotos»:
 * el original sin recomprimir (sin metadatos de ubicación ni datos personales) y la versión ligera WebP de la interfaz.
 */
class EspecieFoto extends Model
{
    use PerteneceATenant;

    public const LIGERA = 'ligera';

    public const ORIGINAL = 'original';

    protected $table = 'especie_fotos';

    protected $fillable = [
        'especie_id', 'user_id', 'ruta_original', 'ruta_ligera', 'tipo_original', 'bytes_original', 'bytes_ligera',
        'ancho', 'alto', 'orden', 'autor', 'licencia',
    ];

    protected function casts(): array
    {
        return [
            'bytes_original' => 'integer',
            'bytes_ligera' => 'integer',
            'ancho' => 'integer',
            'alto' => 'integer',
            'orden' => 'integer',
        ];
    }

    public function especie(): BelongsTo
    {
        return $this->belongsTo(Especie::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Dirección de la versión ligera (la que usa toda la interfaz). */
    public function url(): string
    {
        return route('especies.fotos.ver', [$this, self::LIGERA]);
    }

    public function urlOriginal(): string
    {
        return route('especies.fotos.ver', [$this, self::ORIGINAL]);
    }

    /** Bytes que ocupa en disco (original + ligera), para el tope por finca. */
    public function bytesTotales(): int
    {
        return $this->bytes_original + $this->bytes_ligera;
    }
}
