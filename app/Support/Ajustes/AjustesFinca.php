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

        return $guardado !== null && self::valido($definicion, $guardado->valor) ? $guardado->valor : $definicion['defecto'];
    }

    /** Un valor guardado vale si es una de las opciones o un entero dentro del rango; si no, se usa el valor por defecto. */
    private static function valido(array $definicion, mixed $valor): bool
    {
        return $definicion['tipo'] === CatalogoAjustes::TIPO_ENTERO
            ? is_int($valor) && $valor >= $definicion['min'] && $valor <= $definicion['max']
            : in_array($valor, $definicion['opciones'], true);
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
            CatalogoAjustes::campo($clave) => $definicion['tipo'] === CatalogoAjustes::TIPO_ENTERO
                ? ['required', 'integer', 'min:'.$definicion['min'], 'max:'.$definicion['max']]
                : ['required', 'string', Rule::in($definicion['opciones'])],
        ])->all();
    }

    /** Texto de un valor (la opción, o el número con su unidad), para la pantalla y la bitácora. */
    public static function textoOpcion(string $clave, string|int $valor): string
    {
        return CatalogoAjustes::definicion($clave)['tipo'] === CatalogoAjustes::TIPO_ENTERO
            ? __("ajustes_finca.{$clave}.valor", ['valor' => $valor])
            : __("ajustes_finca.{$clave}.opciones.{$valor}");
    }

    /**
     * Guarda los valores que cambiaron (validados con reglas()) y deja cada cambio en la bitácora.
     *
     * @param  array<string, string|int>  $datos  campo => valor
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
                $nuevo = $definicion['tipo'] === CatalogoAjustes::TIPO_ENTERO ? (int) $datos[$campo] : $datos[$campo];
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
