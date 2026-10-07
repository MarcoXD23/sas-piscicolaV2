<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * La contraseña no puede ser igual al correo (ni a la parte antes de la @), sin importar mayúsculas.
 */
class NoIgualAlCorreo implements ValidationRule
{
    public function __construct(private readonly ?string $correo) {}

    public function validate(string $atributo, mixed $valor, Closure $falla): void
    {
        $correo = mb_strtolower(trim((string) $this->correo));
        $clave = mb_strtolower(trim((string) $valor));

        if ($correo !== '' && ($clave === $correo || $clave === strstr($correo, '@', true))) {
            $falla(__('mensajes.contrasena.igual_al_correo'));
        }
    }
}
