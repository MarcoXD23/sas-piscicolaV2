<?php

namespace App\Support;

use Illuminate\Translation\Translator;

/**
 * El traductor de Laravel con un único ajuste: una frase que termina en una hora de 12 horas («… a las :hora.») no
 * deja punto doble. La abreviatura «a. m.» / «p. m.» de App\Support\Hora lleva espacios que no se parten (no los usa
 * ningún otro texto), así que se reconoce sin confundirla; su punto hace de punto final («a las 5:24 a. m.»).
 * En 24 horas la frase queda igual («a las 05:24.»).
 */
class Traductor extends Translator
{
    /** Quita el punto que sigue a «a. m.» o «p. m.» (también tras etiquetas de cierre, como </strong>). */
    public static function sinPuntoDoble(string $texto): string
    {
        return (string) preg_replace('/([ap])\.\x{00A0}m\.((?:<\/[a-z]+>)*)\./u', "\$1.\u{00A0}m.\$2", $texto);
    }

    protected function makeReplacements($line, array $replace)
    {
        return self::sinPuntoDoble(parent::makeReplacements($line, $replace));
    }
}
