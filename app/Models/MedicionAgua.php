<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use App\Support\CatalogoEspecies;
use App\Support\Formato;
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
     * Compara la medición con los rangos de las especies sembradas en el lago.
     *
     * @param  iterable<string>  $especies  Claves del catálogo de especies
     * @return list<string> Mensajes de alerta (vacío si todo está en rango)
     */
    public function alertas(iterable $especies): array
    {
        $alertas = [];
        $numero = fn (float $valor) => rtrim(rtrim(Formato::numero($valor, 2), '0'), ',.');

        foreach ($especies as $clave) {
            $ficha = CatalogoEspecies::ficha($clave);
            if ($ficha === null) {
                continue;
            }

            [$tMin, $tMax] = $ficha['temperatura'];
            if ($this->temperatura_c !== null && ($this->temperatura_c < $tMin || $this->temperatura_c > $tMax)) {
                $alertas[] = __('lagos.alertas.temperatura', ['valor' => $numero($this->temperatura_c), 'especie' => $ficha['nombre'], 'min' => $tMin, 'max' => $tMax]);
            }

            if ($this->oxigeno_mg_l !== null && $this->oxigeno_mg_l < $ficha['oxigeno_min']) {
                $alertas[] = __('lagos.alertas.oxigeno', ['valor' => $numero($this->oxigeno_mg_l), 'especie' => $ficha['nombre'], 'minimo' => $numero($ficha['oxigeno_min'])]);
            }

            [$phMin, $phMax] = $ficha['ph'];
            if ($this->ph !== null && ($this->ph < $phMin || $this->ph > $phMax)) {
                $alertas[] = __('lagos.alertas.ph', ['valor' => $numero($this->ph), 'especie' => $ficha['nombre'], 'min' => $numero($phMin), 'max' => $numero($phMax)]);
            }
        }

        if ($this->amonio_mg_l !== null && $this->amonio_mg_l > self::AMONIO_MAX) {
            $alertas[] = __('lagos.alertas.amonio', ['valor' => $numero($this->amonio_mg_l), 'maximo' => $numero(self::AMONIO_MAX)]);
        }

        if ($this->nitritos_mg_l !== null && $this->nitritos_mg_l > self::NITRITOS_MAX) {
            $alertas[] = __('lagos.alertas.nitritos', ['valor' => $numero($this->nitritos_mg_l), 'maximo' => $numero(self::NITRITOS_MAX)]);
        }

        return $alertas;
    }
}
