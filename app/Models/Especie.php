<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use App\Support\CatalogoEspecies;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Especie del catálogo de una finca: lo que se puede sembrar y sus rangos de agua y tipo de alimentación.
 * Cada finca arranca con las 6 especies por defecto (CatalogoEspecies) y las puede editar, desactivar o agregar.
 * Los rangos y la alimentación son opcionales: sin rangos no se inventan valores, sin alimentación no hay ración sugerida.
 */
class Especie extends Model
{
    use PerteneceATenant;

    public const CALIDO = 'calido';

    public const FRIO = 'frio';

    public const CLIMAS = [self::CALIDO, self::FRIO];

    /** Partes de la ficha de cultivo y de procesamiento (texto libre, todas opcionales). */
    public const CAMPOS_CULTIVO = ['preparacion', 'siembra', 'alimentacion', 'calidad_agua', 'cosecha', 'densidades', 'tipo_estanque'];

    public const CAMPOS_PROCESAMIENTO = ['ayuno', 'sacrificio', 'eviscerado', 'fileteado', 'frio', 'empaque'];

    /** Perfiles de las tablas de ración (CalculadoraRacion). */
    public const ALIMENTACIONES = ['calido', 'frio', 'detritivoro'];

    protected $fillable = [
        'clave', 'nombre', 'nombre_cientifico', 'clima', 'temp_min', 'temp_max', 'oxigeno_min', 'ph_min', 'ph_max', 'alimentacion', 'activo',
        'cultivo', 'procesamiento', 'color', 'aspecto', 'ficha_orientativa',
    ];

    /** Valores por defecto (los mismos de la base) también en el modelo recién creado. */
    protected $attributes = ['activo' => true, 'ficha_orientativa' => false];

    protected function casts(): array
    {
        return [
            'temp_min' => 'float',
            'temp_max' => 'float',
            'oxigeno_min' => 'float',
            'ph_min' => 'float',
            'ph_max' => 'float',
            'activo' => 'boolean',
            'cultivo' => 'array',
            'procesamiento' => 'array',
            'ficha_orientativa' => 'boolean',
        ];
    }

    /** Carga las 6 especies por defecto en una finca (editables y desactivables). */
    public static function cargarPorDefecto(int $tenantId): void
    {
        foreach (CatalogoEspecies::porDefecto() as $fila) {
            (new self($fila + CatalogoEspecies::fichaPorDefecto($fila['clave'])))->forceFill(['tenant_id' => $tenantId, 'activo' => true])->save();
        }
    }

    /** Especie por defecto de la finca activa, por su clave (por ejemplo 'mojarra roja'). */
    public static function deClave(string $clave): self
    {
        return self::query()->where('clave', $clave)->firstOrFail();
    }

    /** Fotos reales de la especie (máximo 5), en su orden; la primera es la portada. */
    public function fotos(): HasMany
    {
        return $this->hasMany(EspecieFoto::class)->orderBy('orden');
    }

    public function siembras(): HasMany
    {
        return $this->hasMany(Siembra::class);
    }

    public function scopeActivas(Builder $consulta): void
    {
        $consulta->where('activo', true);
    }

    public function esFria(): bool
    {
        return $this->clima === self::FRIO;
    }

    /** @return array{0: float, 1: float}|null */
    public function rangoTemperatura(): ?array
    {
        return $this->temp_min !== null && $this->temp_max !== null ? [$this->temp_min, $this->temp_max] : null;
    }

    /** @return array{0: float, 1: float}|null */
    public function rangoPh(): ?array
    {
        return $this->ph_min !== null && $this->ph_max !== null ? [$this->ph_min, $this->ph_max] : null;
    }

    /** ¿Tiene todos los rangos de agua (temperatura, oxígeno y pH)? */
    public function tieneRangos(): bool
    {
        return $this->rangoTemperatura() !== null && $this->oxigeno_min !== null && $this->rangoPh() !== null;
    }

    /** Textos de referencia (solo las especies por defecto, por su clave). */
    public function referencia(): array
    {
        return CatalogoEspecies::referencia($this->clave);
    }

    /** Portada: la primera foto que subió la finca (versión ligera); null si no ha subido ninguna. */
    public function foto(): ?string
    {
        return $this->fotos->first()?->url();
    }

    /** @return array<string, ?string> cada campo con su texto o null si está vacío */
    private static function partes(?array $valores, array $campos): array
    {
        return collect($campos)->mapWithKeys(fn (string $campo) => [$campo => filled($valores[$campo] ?? null) ? trim($valores[$campo]) : null])->all();
    }

    /** ¿La ficha de cultivo o de procesamiento tiene algún dato? */
    public function tieneFicha(): bool
    {
        return collect([$this->cultivo, $this->procesamiento])->flatten()->filter(fn ($texto) => filled($texto))->isNotEmpty()
            || filled($this->color) || filled($this->aspecto);
    }

    /**
     * Ficha para la guía, el asistente y la ración: datos de la finca + textos de referencia si los tiene.
     *
     * @return array<string, mixed>
     */
    public function ficha(): array
    {
        $referencia = $this->referencia();

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'cientifico' => $this->nombre_cientifico,
            'clima' => $this->clima,
            'temperatura' => $this->rangoTemperatura(),
            'oxigeno_min' => $this->oxigeno_min,
            'ph' => $this->rangoPh(),
            'alimentacion' => $this->alimentacion,
            'activo' => $this->activo,
            // Ficha editable de la finca: cada parte vacía queda en null (se muestra como «sin dato»)
            'cultivo' => self::partes($this->cultivo, self::CAMPOS_CULTIVO),
            'procesamiento' => self::partes($this->procesamiento, self::CAMPOS_PROCESAMIENTO),
            'color' => $this->color ?: null,
            'aspecto' => $this->aspecto ?: null,
            'orientativa' => $this->ficha_orientativa,
            // Portada: la primera foto que subió la finca (null: la vista muestra «Sin foto todavía»)
            'foto' => $this->foto(),
            // Solo de referencia (especies por defecto): familia, proteína por etapa, tiempo a cosecha y peso comercial
            'familia' => $referencia['familia'] ?? null,
            'proteina' => $referencia['proteina'] ?? null,
            'meses_cosecha' => $referencia['meses_cosecha'] ?? null,
            'peso_comercial' => $referencia['peso_comercial'] ?? null,
            'policultivo' => $referencia['policultivo'] ?? null,
        ];
    }
}
