<?php

/*
| Panel de plataforma (dueño del software) y mensajes de finca suspendida.
*/

return [
    'panel' => 'Panel de plataforma',
    'finca_suspendida' => 'Tu finca está suspendida. Comunícate con el soporte de El SAS Piscícola para reactivarla.',
    'login' => [
        'titulo' => 'Panel de plataforma',
        'subtitulo' => 'Acceso exclusivo para la administración del software.',
        'incorrecto' => 'El correo o la contraseña no son correctos.',
    ],
    // Verificación en dos pasos del panel (obligatoria). Los textos comunes están en cuenta.dos_pasos
    'dos_pasos' => [
        'obligatoria' => 'El panel de plataforma exige la verificación en dos pasos. Actívala para continuar.',
        'activar_titulo' => 'Activa la verificación en dos pasos',
        'activar_ayuda' => 'Desde este panel se suspenden y eliminan fincas: además de la contraseña, cada ingreso pedirá un código de tu teléfono. No se puede desactivar desde aquí; si pierdes el teléfono y los códigos de recuperación, se quita en el servidor con php artisan piscicola:plataforma-quitar-dos-pasos.',
        'codigos_listo' => 'Ya los guardé: ir al panel',
    ],
    'fincas' => [
        'titulo' => 'Fincas',
        'subtitulo' => ':activas activas de :total fincas registradas.',
        'finca' => 'Finca',
        'usuarios' => 'Usuarios',
        'estado' => 'Estado',
        'creada' => 'Creada',
        'codigo' => 'Código :codigo',
        'activa' => 'Activa',
        'suspendida_desde' => 'Suspendida desde el :fecha',
        'vacio' => 'Aún no hay fincas registradas.',
        'suspender' => 'Suspender',
        'reactivar' => 'Reactivar',
        'suspender_finca' => 'Suspender :finca',
        'aviso_suspender' => 'Las :usuarios personas de esta finca no podrán ingresar hasta que la reactives. Sus datos no se borran.',
        'motivo' => 'Motivo (opcional)',
        'suspendida' => 'La finca :finca quedó suspendida.',
        'reactivada' => 'La finca :finca fue reactivada.',
    ],
];
