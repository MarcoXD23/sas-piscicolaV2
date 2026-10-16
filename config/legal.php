<?php

/*
 * Términos de uso y política de privacidad (textos en lang/es/legal.php, en BORRADOR hasta que los revise un abogado).
 */
return [

    // Versión vigente de los textos. Al cambiarla, cada persona debe aceptarlos de nuevo en su próximo ingreso.
    // 2026-11-01: sección «Personas sin cuenta en la app» (jornaleros), pendiente de revisión por el abogado
    // 2026-11-10: «Quién los ve»: el panel de la plataforma ve el nombre de la finca y el nombre y el correo del propietario
    'version' => '2026-11-10',

    // Quién responde por el servicio. Vacíos: las páginas muestran un aviso de dato pendiente (no se inventan).
    'entidad' => env('LEGAL_ENTIDAD') ?: null,
    'correo' => env('LEGAL_CORREO') ?: null,

];
