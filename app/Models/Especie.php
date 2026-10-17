<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use App\Support\CatalogoEspecies;
use App\Support\TenantActual;
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
        // Rendimiento de referencia inicial de la finca (%), hasta que haya promedio real (F6b)
        'rendimiento_referencia',
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
            'rendimiento_referencia' => 'float',
        ];
    }

    protected static function booted(): void
    {
        // Una especie con tipo de alimentación y sin tabla de ración arranca con la tabla de su perfil (TablaRacion):
        // así la ración sugerida sigue como antes de F3, cuando las tablas eran fijas por perfil. Si ya tiene tabla, no se toca.
        static::saved(function (Especie $especie) {
            if ($especie->alimentacion !== null && ($especie->wasRecentlyCreated || $especie->wasChanged('alimentacion'))
                && ! TenantActual::sinFiltro(fn () => TablaRacion::query()->where('tenant_id', $especie->tenant_id)->where('especie_id', $especie->id)->exists())) {
                TablaRacion::cargarParaEspecie($especie);
            }
        });
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

    /** Tamaños de la especie en el catálogo de presentaciones de la finca (F6b). */
    public function tamanos(): HasMany
    {
        return $this->hasMany(Tamano::class);
    }

    /** Tramos de la tabla de ración de esta especie en la finca, de menor a mayor peso. */
    public function tablaRacion(): HasMany
    {
        return $this->hasMany(TablaRacion::class)->orderBy('peso_hasta_g');
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

    /** Portada: la primera foto que subió la finca (versión ligera); sin fotos propias, la de referencia (si tiene). */
    public function foto(): ?string
    {
        return $this->fotos->first()?->url() ?? $this->referencia()['foto']['ruta'] ?? null;
    }

    /**
     * Crédito de la foto de referencia (autor, licencia, fuente y nota), solo cuando es la que se muestra: las fotos
     * que sube la finca muestran su propio autor y licencia.
     *
     * @return array{ruta: string, autor: string, licencia: string, licencia_url?: string, fuente: string, tipo?: string, nota?: string, modificada?: bool}|null
     */
    public function creditoFoto(): ?array
    {
        $foto = $this->fotos->isEmpty() ? ($this->referencia()['foto'] ?? null) : null;
        if ($foto === null) {
            return null;
        }

        // «dominio_publico», el tipo y las notas se traducen; las licencias CC se muestran con su nombre corto
        $foto['licencia'] = $foto['licencia'] === 'dominio_publico' ? __('especies.foto_credito.dominio_publico') : $foto['licencia'];
        $foto['tipo'] = __('especies.foto_credito.tipos.'.($foto['tipo'] ?? 'foto'));
        $foto['modificada'] = (bool) ($foto['modificada'] ?? false);
        if (isset($foto['nota'])) {
            $foto['nota'] = __('especies.foto_credito.notas.'.$foto['nota']);
        }

        return $foto;
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
            // Portada: la primera foto que subió la finca o, sin ella, la de referencia con su crédito (null: «Sin foto todavía»)
            'foto' => $this->foto(),
            'foto_credito' => $this->creditoFoto(),
            // Solo de referencia (especies por defecto): familia, proteína por etapa, tiempo a cosecha y peso comercial
            'familia' => $referencia['familia'] ?? null,
            'proteina' => $referencia['proteina'] ?? null,
            'meses_cosecha' => $referencia['meses_cosecha'] ?? null,
            'peso_comercial' => $referencia['peso_comercial'] ?? null,
            'policultivo' => $referencia['policultivo'] ?? null,
        ];
    }
}
