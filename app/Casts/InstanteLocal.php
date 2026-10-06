<?php

namespace App\Casts;

use App\Support\Region;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Columna de fecha y hora guardada en UTC y leída en la zona horaria de la finca.
 * Al asignar texto (por ejemplo, desde un formulario) se interpreta en la zona de la finca.
 */
class InstanteLocal implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value, 'UTC')->setTimezone(Region::zona());
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $instante = $value instanceof DateTimeInterface
            ? Carbon::instance($value)
            : Carbon::parse((string) $value, Region::zona());

        return $instante->utc()->format('Y-m-d H:i:s');
    }
}
