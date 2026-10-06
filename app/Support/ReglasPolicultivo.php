<?php

namespace App\Support;

use App\Models\Siembra;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Reglas para tener varias especies en un mismo lago. Se usan al sembrar y al trasladar peces.
 */
class ReglasPolicultivo
{
    /**
     * @param  Collection<int, Siembra>  $activas  Siembras activas del lago
     *
     * @throws ValidationException si la especie no es compatible con las que ya están en el lago
     */
    public static function verificarClima(Collection $activas, string $especie, string $campo): void
    {
        $clima = CatalogoEspecies::clima($especie);

        if ($activas->contains(fn (Siembra $siembra) => CatalogoEspecies::clima($siembra->especie) !== $clima)) {
            throw ValidationException::withMessages([
                $campo => 'No se pueden mezclar especies de agua fría y de agua cálida en el mismo lago.',
            ]);
        }
    }
}
