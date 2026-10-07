<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use App\Support\Formato;
use App\Support\RangoAgua;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicionAgua extends Model
{
    use PerteneceATenant;

    protected $table = 'mediciones_agua';

    /** Umbrales generales de referencia (mg/L) para especies de cultivo. */
    public const AMONIO_MAX = 1.0;

    public const NITRITOS_MAX = 0.5;

    protected $fillable = [
        'lago_id', 'user_id', 'medido_at', 'temperatura_c', 'oxigeno_mg_l', 'ph',
        'amonio_mg_l', 'nitritos_mg_l', 'transparencia_cm', 'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'medido_at' => InstanteLocal::class,
            'temperatura_c' => 'float',
            'oxigeno_mg_l' => 'float',
            'ph' => 'float',
            'amonio_mg_l' => 'float',
            'nitritos_mg_l' => 'float',
        ];
    }

    public function lago(): BelongsTo
    {
        return $this->belongsTo(Lago::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Compara la medición con el rango COMÚN de las especies sembradas en el lago (RangoAgua).
     * Única fuente de los rangos de agua: la usan el inicio, Seguridad & Noche, el asistente IA y las alertas.
     *
     * @param  iterable<Especie|null>  $especies  Especies de las siembras activas del lago
     * @return list<array{parametro: string, texto: string}> Problemas encontrados (vacío si todo está en rango)
     */
    public function problemas(iterable $especies): array
    {
        $problemas = [];
        $agregar = function (string $parametro, string $texto) use (&$problemas) {
            $problemas[] = ['parametro' => $parametro, 'texto' => $texto];
        };
        $numero = fn (float $valor) => rtrim(rtrim(Formato::numero($valor, 2), '0'), ',.');
        $rango = RangoAgua::para($especies);

        if ($rango['temperatura'] !== null && $this->temperatura_c !== null) {
            [$tMin, $tMax] = $rango['temperatura'];
            if ($this->temperatura_c < $tMin || $this->temperatura_c > $tMax) {
                $agregar('temperatura', __('lagos.alertas.temperatura', ['valor' => $numero($this->temperatura_c), 'especie' => $rango['especies'], 'min' => $numero($tMin), 'max' => $numero($tMax)]));
            }
        }

        if ($rango['oxigeno'] !== null && $this->oxigeno_mg_l !== null && $this->oxigeno_mg_l < $rango['oxigeno']) {
            $agregar('oxigeno', __('lagos.alertas.oxigeno', ['valor' => $numero($this->oxigeno_mg_l), 'especie' => $rango['especies'], 'minimo' => $numero($rango['oxigeno'])]));
        }

        if ($rango['ph'] !== null && $this->ph !== null) {
            [$phMin, $phMax] = $rango['ph'];
            if ($this->ph < $phMin || $this->ph > $phMax) {
                $agregar('ph', __('lagos.alertas.ph', ['valor' => $numero($this->ph), 'especie' => $rango['especies'], 'min' => $numero($phMin), 'max' => $numero($phMax)]));
            }
        }

        if ($this->amonio_mg_l !== null && $this->amonio_mg_l > self::AMONIO_MAX) {
            $agregar('amonio', __('lagos.alertas.amonio', ['valor' => $numero($this->amonio_mg_l), 'maximo' => $numero(self::AMONIO_MAX)]));
        }

        if ($this->nitritos_mg_l !== null && $this->nitritos_mg_l > self::NITRITOS_MAX) {
            $agregar('nitritos', __('lagos.alertas.nitritos', ['valor' => $numero($this->nitritos_mg_l), 'maximo' => $numero(self::NITRITOS_MAX)]));
        }

        return $problemas;
    }

    /**
     * @param  iterable<Especie|null>  $especies
     * @return list<string> Mensajes de alerta (vacío si todo está en rango)
     */
    public function alertas(iterable $especies): array
    {
        return array_column($this->problemas($especies), 'texto');
    }
}
