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

    /** Perfiles de las tablas de ración (CalculadoraRacion). */
    public const ALIMENTACIONES = ['calido', 'frio', 'detritivoro'];

    protected $fillable = [
        'clave', 'nombre', 'nombre_cientifico', 'clima', 'temp_min', 'temp_max', 'oxigeno_min', 'ph_min', 'ph_max', 'alimentacion', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'temp_min' => 'float',
            'temp_max' => 'float',
            'oxigeno_min' => 'float',
            'ph_min' => 'float',
            'ph_max' => 'float',
            'activo' => 'boolean',
        ];
    }

    /** Carga las 6 especies por defecto en una finca (editables y desactivables). */
    public static function cargarPorDefecto(int $tenantId): void
    {
        foreach (CatalogoEspecies::porDefecto() as $fila) {
            (new self($fila))->forceFill(['tenant_id' => $tenantId, 'activo' => true])->save();
        }
    }

    /** Especie por defecto de la finca activa, por su clave (por ejemplo 'mojarra roja'). */
    public static function deClave(string $clave): self
    {
        return self::query()->where('clave', $clave)->firstOrFail();
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

    /** Textos y foto de referencia (solo las especies por defecto, por su clave). */
    public function referencia(): array
    {
        return CatalogoEspecies::referencia($this->clave);
    }

    public function foto(): ?string
    {
        return $this->referencia()['foto'] ?? null;
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
            'familia' => $referencia['familia'] ?? null,
            'foto' => $referencia['foto'] ?? null,
            'densidad_tierra' => $referencia['densidad_tierra'] ?? null,
            'densidad_tanque' => $referencia['densidad_tanque'] ?? null,
            'proteina' => $referencia['proteina'] ?? null,
            'meses_cosecha' => $referencia['meses_cosecha'] ?? null,
            'peso_comercial' => $referencia['peso_comercial'] ?? null,
            'policultivo' => $referencia['policultivo'] ?? null,
            'manejo' => $referencia['manejo'] ?? null,
        ];
    }
}
