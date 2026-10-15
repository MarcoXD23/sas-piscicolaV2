<?php

namespace App\Support\Ajustes;

use App\Models\AjusteFinca;
use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Ajustes de operación de la finca activa: leer (con el valor por defecto del catálogo si la finca no lo cambió),
 * validar y guardar. Cada cambio queda en la bitácora con el valor de antes y el de después.
 * Solo los cambia el propietario (AGENTS.md: los ajustes de la finca son exclusivos suyos); lo protege la ruta.
 */
final class AjustesFinca
{
    /** Valor de un ajuste en la finca activa (el filtro por finca de AjusteFinca). */
    public static function valor(string $clave): mixed
    {
        $definicion = CatalogoAjustes::definicion($clave);
        $guardado = AjusteFinca::query()->where('clave', $clave)->first();

        return $guardado !== null && in_array($guardado->valor, $definicion['opciones'], true) ? $guardado->valor : $definicion['defecto'];
    }

    /** @return array<string, mixed> clave => valor de todos los ajustes del catálogo */
    public static function todos(): array
    {
        return collect(CatalogoAjustes::todos())->keys()->mapWithKeys(fn (string $clave) => [$clave => self::valor($clave)])->all();
    }

    /** Reglas de validación del formulario (un campo por ajuste; ver CatalogoAjustes::campo). */
    public static function reglas(): array
    {
        return collect(CatalogoAjustes::todos())->mapWithKeys(fn (array $definicion, string $clave) => [
            CatalogoAjustes::campo($clave) => ['required', 'string', Rule::in($definicion['opciones'])],
        ])->all();
    }

    /** Texto de una opción, para la pantalla y la bitácora. */
    public static function textoOpcion(string $clave, string $valor): string
    {
        return __("ajustes_finca.{$clave}.opciones.{$valor}");
    }

    /**
     * Guarda los valores que cambiaron (validados con reglas()) y deja cada cambio en la bitácora.
     *
     * @param  array<string, string>  $datos  campo => valor
     * @return int cuántos ajustes cambiaron
     */
    public static function guardar(User $usuario, array $datos): int
    {
        return DB::transaction(function () use ($usuario, $datos) {
            $cambios = 0;
            foreach (CatalogoAjustes::todos() as $clave => $definicion) {
                $campo = CatalogoAjustes::campo($clave);
                if (! array_key_exists($campo, $datos)) {
                    continue;
                }
                $antes = self::valor($clave);
                $nuevo = $datos[$campo];
                if ($nuevo === $antes) {
                    continue;
                }

                AjusteFinca::query()->updateOrCreate(['clave' => $clave], ['valor' => $nuevo, 'cambiado_por' => $usuario->id]);
                Bitacora::registrar($usuario, 'ajustes', null, [Rol::PROPIETARIO], [
                    'ajuste' => __("ajustes_finca.{$clave}.etiqueta"),
                    'antes' => self::textoOpcion($clave, $antes),
                    'despues' => self::textoOpcion($clave, $nuevo),
                ]);
                $cambios++;
            }

            return $cambios;
        });
    }
}
