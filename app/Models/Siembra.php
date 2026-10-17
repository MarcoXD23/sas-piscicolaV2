<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use App\Support\Region;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Siembra extends Model
{
    use HasFactory, PerteneceATenant;

    /** Origen del lote: alevinos sembrados, o lote creado al trasladar peces desde otro lago (lago_origen_id). */
    public const ORIGEN_SIEMBRA = 'siembra';

    public const ORIGEN_TRASLADO = 'traslado';

    protected $fillable = [
        'lago_id',
        'especie_id',
        'proveedor_id',
        'proveedor_alevinos',
        'lote_alevinos',
        'numero_lote',
        'fecha',
        'cantidad_alevinos',
        'peso_promedio_inicial_g',
        'peces_vivos',
        'peso_actual_g',
        'biomasa_kg',
        'finalizada_at',
        'origen',
        'lago_origen_id',
    ];

    /** La especie se usa en casi todas las pantallas (nombre, foto, rangos): siempre se carga. */
    protected $with = ['especie'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'finalizada_at' => InstanteLocal::class,
        ];
    }

    public function lago(): BelongsTo
    {
        return $this->belongsTo(Lago::class);
    }

    /** Especie del catálogo de la finca. */
    public function especie(): BelongsTo
    {
        return $this->belongsTo(Especie::class);
    }

    /** Proveedor de los alevinos, del catálogo de la finca (terceros que proveen alevinos). */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Tercero::class, 'proveedor_id');
    }

    /**
     * Nombre del proveedor de los alevinos: el del catálogo o, en las siembras viejas aún sin ligar, el texto escrito a
     * mano (que nunca se borra). Null si no se registró.
     */
    public function nombreProveedor(): ?string
    {
        if ($this->proveedor_id !== null && $this->proveedor !== null) {
            return $this->proveedor->nombreCompleto();
        }

        return filled($this->proveedor_alevinos) ? trim((string) $this->proveedor_alevinos) : null;
    }

    /** Lago de donde vinieron los peces, si el lote se creó por un traslado. */
    public function lagoOrigen(): BelongsTo
    {
        return $this->belongsTo(Lago::class, 'lago_origen_id');
    }

    /** Traslados que llegaron a este lote (el que lo creó, si fue por traslado, y los que le sumaron peces). */
    public function trasladosRecibidos(): HasMany
    {
        return $this->hasMany(Traslado::class, 'siembra_destino_id')->orderBy('fecha')->orderBy('id');
    }

    public function vinoDeTraslado(): bool
    {
        return $this->origen === self::ORIGEN_TRASLADO;
    }

    /** «Sembrado el 07/10/2026» o «Creado por traslado desde L02 el 15/10/2026». */
    public function textoOrigen(): string
    {
        return $this->vinoDeTraslado()
            ? __('lagos.lotes.origen_traslado', ['lago' => (string) $this->lagoOrigen?->nombre, 'fecha' => $this->fecha->format('d/m/Y')])
            : __('lagos.lotes.origen_siembra', ['fecha' => $this->fecha->format('d/m/Y')]);
    }

    /**
     * Traslados que sumaron peces a este lote después de crearse (sin el que lo creó).
     *
     * @return Collection<int, Traslado>
     */
    public function trasladosSumados(): Collection
    {
        $recibidos = $this->trasladosRecibidos;

        return ($this->vinoDeTraslado() ? $recibidos->slice(1) : $recibidos)->values();
    }

    public function muestreos(): HasMany
    {
        return $this->hasMany(Muestreo::class);
    }

    public function mortalidades(): HasMany
    {
        return $this->hasMany(Mortalidad::class);
    }

    /**
     * Calcula los días transcurridos desde la fecha de siembra.
     */
    protected function diasCultivo(): Attribute
    {
        // Días de calendario entre la siembra y hoy en la zona de la finca
        return Attribute::get(fn () => (int) CarbonImmutable::parse($this->fecha->toDateString())
            ->diffInDays(CarbonImmutable::parse(Region::hoy()->toDateString())));
    }

    /** Siembras que siguen en el agua (sin finalizar). */
    public function scopeActivas(Builder $consulta): void
    {
        $consulta->whereNull('finalizada_at');
    }

    public function estaActiva(): bool
    {
        return $this->finalizada_at === null;
    }

    protected function nombreEspecie(): Attribute
    {
        return Attribute::get(fn () => (string) $this->especie?->nombre);
    }

    protected function fotoEspecie(): Attribute
    {
        return Attribute::get(fn () => $this->especie?->foto());
    }
}
