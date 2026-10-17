<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Concentrado del catálogo de la finca: nombre completo, nombre corto (por ejemplo el color del hilo del bulto),
 * porcentaje de proteína, tamaño del pellet y peso del bulto en kilos.
 */
class Concentrado extends Model
{
    use PerteneceATenant;

    public const PESO_BULTO_POR_DEFECTO = 40.0;

    protected $table = 'concentrados';

    protected $fillable = [
        'nombre',
        'nombre_corto',
        'proteina_porcentaje',
        'pellet_mm',
        'peso_bulto_kg',
        'activo',
    ];

    protected $attributes = [
        'peso_bulto_kg' => self::PESO_BULTO_POR_DEFECTO,
        'activo' => true,
    ];

    protected function casts(): array
    {
        return [
            'proteina_porcentaje' => 'float',
            'peso_bulto_kg' => 'float',
            'activo' => 'boolean',
        ];
    }

    public function scopeActivos(Builder $consulta): void
    {
        $consulta->where('activo', true);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(StockAlimento::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoAlimento::class);
    }

    public function despachos(): HasMany
    {
        return $this->hasMany(DespachoAlimento::class);
    }

    public function tramosRacion(): HasMany
    {
        return $this->hasMany(TablaRacion::class);
    }

    /** Nombre para mostrar en pantallas y selectores: incluye el nombre corto si la finca lo definió. */
    public function etiqueta(): string
    {
        return $this->nombre_corto !== null && $this->nombre_corto !== ''
            ? "{$this->nombre_corto} ({$this->nombre})"
            : $this->nombre;
    }

    /**
     * Texto en la lista de la bodega: la proteína primero («24 %») y después el nombre, solo si dice algo distinto de
     * la proteína («24 % · Azul (Finalizador)»). Un nombre como «32» o «32 %» con 32 % de proteína sale solo «32 %».
     * Sin proteína (los de antes de que fuera obligatoria): solo su nombre.
     */
    public function textoProteina(): string
    {
        if ($this->proteina_porcentaje === null) {
            return $this->etiqueta();
        }

        $proteina = \App\Support\Formato::numero($this->proteina_porcentaje, fmod($this->proteina_porcentaje, 1.0) === 0.0 ? 0 : 1);
        $repiteProteina = fn (?string $texto) => $texto === null || $texto === ''
            || (is_numeric($limpio = str_replace([' ', '%', ','], ['', '', '.'], $texto)) && abs((float) $limpio - $this->proteina_porcentaje) < 0.01);
        $partes = array_values(array_filter([$this->nombre_corto, $this->nombre], fn ($texto) => ! $repiteProteina($texto)));
        $nombre = match (count($partes)) {
            0 => null,
            1 => $partes[0],
            default => "{$partes[0]} ({$partes[1]})",
        };

        return $nombre === null
            ? __('bodega.opcion_proteina', ['proteina' => $proteina])
            : __('bodega.opcion_proteina_nombre', ['proteina' => $proteina, 'nombre' => $nombre]);
    }

    /**
     * Busca un concentrado en la finca activa por su id o por su nombre o nombre corto (sin importar mayúsculas).
     * Permite que la cola sin conexión siga enviando el nombre o el nombre corto como texto.
     */
    public static function resolver(?string $texto = null, ?int $concentradoId = null): ?self
    {
        if ($concentradoId !== null) {
            $porId = self::query()->find($concentradoId);
            if ($porId !== null) {
                return $porId;
            }
        }

        $limpio = trim((string) $texto);
        if ($limpio === '') {
            return null;
        }

        $minuscula = mb_strtolower($limpio);

        $porNombre = self::query()
            ->where(fn (Builder $consulta) => $consulta->whereRaw('LOWER(nombre) = ?', [$minuscula])
                ->orWhereRaw('LOWER(nombre_corto) = ?', [$minuscula]))
            ->orderByDesc('activo')
            ->orderBy('id')
            ->first();
        if ($porNombre !== null) {
            return $porNombre;
        }

        // Renombrar no cambia la historia: un nombre viejo (el de la bodega o el de un registro ya guardado, que puede
        // venir de la cola de un teléfono) sigue llevando a su concentrado
        foreach ([StockAlimento::class, MovimientoAlimento::class, DespachoAlimento::class] as $modelo) {
            $id = $modelo::query()->whereNotNull('concentrado_id')->whereRaw('LOWER(tipo_concentrado) = ?', [$minuscula])
                ->latest('id')->value('concentrado_id');
            if ($id !== null) {
                return self::query()->find($id);
            }
        }

        return null;
    }
}

