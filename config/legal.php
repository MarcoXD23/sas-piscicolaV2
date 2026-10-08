<?php

/*
 * Términos de uso y política de privacidad (textos en lang/es/legal.php, en BORRADOR hasta que los revise un abogado).
 */
return [

    // Versión vigente de los textos. Al cambiarla, cada persona debe aceptarlos de nuevo en su próximo ingreso.
    'version' => '2026-10-28',

    // Quién responde por el servicio. Vacíos: las páginas muestran un aviso de dato pendiente (no se inventan).
    'entidad' => env('LEGAL_ENTIDAD') ?: null,
    'correo' => env('LEGAL_CORREO') ?: null,

];
