<?php

/*
| Invitaciones para crear una finca: panel de plataforma (invitar y anular) y la página pública del enlace.
*/

return [
    // Panel de plataforma
    'titulo' => 'Invitar una finca',
    'subtitulo' => 'Escribe el correo del cliente: te sale un enlace de un solo uso que vence en :dias días. Cópialo y envíalo por WhatsApp o correo; con él, el cliente crea su finca y entra como propietario.',
    'correo' => 'Correo del cliente',
    'invitar' => 'Invitar',
    'creada' => 'Invitación para :correo lista. El enlace vence en :dias días.',
    'enlace' => 'Enlace de la invitación',
    'enlace_ayuda' => 'Cópialo ahora: por seguridad no se vuelve a mostrar. Si se pierde, anula la invitación y crea otra.',
    'copiar' => 'Copiar enlace',
    'copiado' => 'Copiado',
    'correo_ya_uso' => 'Ese correo ya usó su invitación.',
    'correo_con_pendiente' => 'Ese correo ya tiene una invitación pendiente; anúlala si quieres enviar una nueva.',
    'lista' => 'Invitaciones',
    'vacio' => 'Aún no has invitado a nadie.',
    'columna_correo' => 'Correo',
    'columna_estado' => 'Estado',
    'columna_vence' => 'Vence',
    'anular' => 'Anular',
    'anular_confirmar' => '¿Anular la invitación de :correo? El enlace dejará de servir.',
    'anulada' => 'La invitación de :correo quedó anulada.',
    'estados' => [
        'pendiente' => 'Pendiente',
        'usada' => 'Usada: :finca',
        'usada_sin_finca' => 'Usada',
        'vencida' => 'Vencida',
        'anulada' => 'Anulada',
    ],

    // Página pública del enlace
    'crear_titulo' => 'Crea tu finca',
    'crear_subtitulo' => 'Te invitaron a usar El SAS Piscícola. Llena los datos de tu finca y entrarás como su propietario.',
    'tu_correo' => 'Tu correo (con el que entrarás)',
    'nombre_finca' => 'Nombre de la finca',
    'pais' => 'País',
    'zona_horaria' => 'Zona horaria',
    'zona_ayuda' => 'La hora de tu finca: con ella se guardan los registros.',
    'tu_nombre' => 'Tu nombre completo',
    'contrasena' => 'Contraseña',
    'confirma_contrasena' => 'Confirma la contraseña',
    'crear' => 'Crear mi finca',
    'finca_creada' => '¡Bienvenido! La finca :finca quedó creada y ya eres su propietario.',
    'sesion_abierta' => 'Tienes la sesión abierta como :nombre. Para usar esta invitación, cierra sesión primero.',
    'cerrar_y_continuar' => 'Cerrar sesión y continuar',
    'invalida_titulo' => 'Este enlace ya no sirve',
    'invalida' => 'El enlace de invitación venció, ya se usó, fue anulado o no existe. Pide una invitación nueva a quien te la envió.',
    'ir_ingresar' => 'Ir a ingresar',
];
