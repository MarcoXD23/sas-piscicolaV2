<?php

// Mi cuenta: contraseña y verificación en dos pasos
return [
    'titulo' => 'Mi cuenta',
    'subtitulo' => 'Tu contraseña y la verificación en dos pasos.',
    'enlace' => 'Mi cuenta',
    'datos' => 'Tus datos',
    'nombre' => 'Nombre',
    'correo' => 'Correo',

    'formato_hora' => [
        'titulo' => 'Formato de hora',
        'ayuda' => 'Cómo ves las horas en la app y en tus reportes. Lo guardado no cambia.',
        'etiqueta' => 'Mostrar las horas en',
        'de_la_finca' => 'El de la finca (:formato)',
        'guardar' => 'Guardar formato',
        'guardado' => 'Formato de hora guardado.',
    ],
    'contrasena' => [
        'titulo' => 'Cambiar la contraseña',
        'ayuda' => 'Al cambiarla se cierran tus sesiones en otros equipos y se desconecta la app móvil.',
        'actual' => 'Contraseña actual',
        'actual_ayuda' => 'La misma con la que entraste. Por seguridad, la app no la muestra ni la puede rellenar.',
        'nueva' => 'Contraseña nueva (mínimo 8 caracteres)',
        'confirmar' => 'Repite la contraseña nueva',
        'guardar' => 'Cambiar contraseña',
        'cambiada' => 'Contraseña cambiada. Se cerraron tus sesiones en otros equipos.',
        'actual_incorrecta' => 'La contraseña actual no es correcta.',
    ],

    'dos_pasos' => [
        'titulo' => 'Verificación en dos pasos',
        'que_es' => 'Además de tu contraseña, al entrar se pide un código de 6 números que cambia cada 30 segundos en una app de tu teléfono (Google Authenticator, Microsoft Authenticator, Authy u otra).',
        'activa' => 'Activa desde el :fecha.',
        'activa_corto' => 'Dos pasos activos',
        'inactiva' => 'No está activa.',
        'activar' => 'Activar verificación en dos pasos',
        'desactivar' => 'Desactivar',
        'desactivar_ayuda' => 'Para desactivarla escribe tu contraseña y un código de tu app (o uno de recuperación).',
        'desactivada' => 'Verificación en dos pasos desactivada.',
        'recuperacion_quedan' => '{0} No te quedan códigos de recuperación.|{1} Te queda 1 código de recuperación.|[2,*] Te quedan :count códigos de recuperación.',
        'aviso_panel' => 'Protege la finca: activa la verificación en dos pasos en Mi cuenta.',
        'aviso_enlace' => 'Activar ahora',

        // Activar
        'activar_titulo' => 'Activar verificación en dos pasos',
        'paso_1' => '1. En tu app de autenticación (Google Authenticator, Microsoft Authenticator, Authy…), agrega una cuenta y escanea este código QR.',
        'paso_1_manual' => '¿No puedes escanear? En la app elige «Ingresar una clave de configuración» y escribe ESTA CLAVE allá, en la app (no aquí abajo):',
        'paso_2' => '2. La app muestra un código de 6 números que cambia cada 30 segundos. Escríbelo aquí (no la clave de arriba).',
        'codigo' => 'Código de 6 números',
        'confirmar' => 'Activar',
        'clave_privada' => 'No compartas esta clave ni el código QR con nadie.',

        // Códigos de recuperación
        'codigos_titulo' => 'Tus códigos de recuperación',
        'codigos_ayuda' => 'Si pierdes tu teléfono, entra con uno de estos códigos (cada uno sirve una sola vez). Guárdalos en un lugar seguro: no se vuelven a mostrar.',
        'codigos_listo' => 'Ya los guardé',

        // Ingreso
        'ingreso_titulo' => 'Verificación en dos pasos',
        'ingreso_ayuda' => 'Escribe el código de 6 números de tu app. Si perdiste tu teléfono, usa un código de recuperación.',
        'ingreso_codigo' => 'Código de la app o de recuperación',
        'ingreso_entrar' => 'Entrar',
        'ingreso_volver' => 'Volver al ingreso',
        'codigo_invalido' => 'El código no es válido o ya se usó. Revisa la hora del teléfono e intenta con el siguiente.',
        'demasiados' => 'Demasiados intentos. Espera :segundos segundos.',
        'requerido' => 'Esta cuenta tiene verificación en dos pasos: envía también el código.',
        'vencido' => 'Pasaron más de 5 minutos. Vuelve a escribir tu correo y tu contraseña.',

        // Personal
        'quitar' => 'Quitar verificación en dos pasos',
        'confirmar_quitar' => '¿Quitar la verificación en dos pasos de :nombre? Úsalo solo si perdió su teléfono y sus códigos de recuperación.',
        'quitada_a' => 'Se quitó la verificación en dos pasos de :nombre. Puede volver a activarla en Mi cuenta.',
    ],
];
