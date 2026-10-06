<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use App\Support\CatalogoEspecies;
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
            'medido_at' => 'datetime',
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
     * Compara la medición con los rangos de las especies sembradas en el lago.
     *
     * @param  iterable<string>  $especies  Claves del catálogo de especies
     * @return list<string> Mensajes de alerta (vacío si todo está en rango)
     */
    public function alertas(iterable $especies): array
    {
        $alertas = [];
        $numero = fn (float $valor) => str_replace('.', ',', (string) round($valor, 2));

        foreach ($especies as $clave) {
            $ficha = CatalogoEspecies::ficha($clave);
            if ($ficha === null) {
                continue;
            }

            [$tMin, $tMax] = $ficha['temperatura'];
            if ($this->temperatura_c !== null && ($this->temperatura_c < $tMin || $this->temperatura_c > $tMax)) {
                $alertas[] = "Temperatura de {$numero($this->temperatura_c)} °C fuera del rango de {$ficha['nombre']} ({$tMin} a {$tMax} °C).";
            }

            if ($this->oxigeno_mg_l !== null && $this->oxigeno_mg_l < $ficha['oxigeno_min']) {
                $alertas[] = "Oxígeno de {$numero($this->oxigeno_mg_l)} mg/L por debajo del mínimo de {$ficha['nombre']} ({$numero($ficha['oxigeno_min'])} mg/L).";
            }

            [$phMin, $phMax] = $ficha['ph'];
            if ($this->ph !== null && ($this->ph < $phMin || $this->ph > $phMax)) {
                $alertas[] = "pH de {$numero($this->ph)} fuera del rango de {$ficha['nombre']} ({$numero($phMin)} a {$numero($phMax)}).";
            }
        }

        if ($this->amonio_mg_l !== null && $this->amonio_mg_l > self::AMONIO_MAX) {
            $alertas[] = "Amonio de {$numero($this->amonio_mg_l)} mg/L por encima de ".$numero(self::AMONIO_MAX).' mg/L.';
        }

        if ($this->nitritos_mg_l !== null && $this->nitritos_mg_l > self::NITRITOS_MAX) {
            $alertas[] = "Nitritos de {$numero($this->nitritos_mg_l)} mg/L por encima de ".$numero(self::NITRITOS_MAX).' mg/L.';
        }

        return $alertas;
    }
}
