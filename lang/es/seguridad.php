<?php

/*
| Mi cuenta y seguridad: equipos, sesiones, ingresos, aviso de equipo nuevo, verificación en dos pasos obligatoria y
| Ajustes › Seguridad de la finca.
*/

return [
    // Nombre legible del equipo (App\Support\Seguridad\Equipo)
    'equipo' => [
        'en' => ':navegador en :sistema',
        'desconocido' => 'Equipo desconocido',
        'navegadores' => [
            'edge' => 'Edge', 'opera' => 'Opera', 'samsung' => 'Samsung Internet', 'firefox' => 'Firefox', 'chrome' => 'Chrome', 'safari' => 'Safari',
        ],
        'sistemas' => [
            'android' => 'Android', 'iphone' => 'iPhone', 'ipad' => 'iPad', 'windows' => 'Windows', 'chromeos' => 'ChromeOS', 'macos' => 'Mac', 'linux' => 'Linux',
        ],
    ],

    'mi_cuenta' => [
        'datos_titulo' => 'Mis datos',
        'nombre' => 'Nombre',
        'guardar_nombre' => 'Guardar nombre',
        'nombre_guardado' => 'Nombre guardado.',
        'correo' => 'Correo',
        'correo_ayuda' => 'El correo todavía no se puede cambiar desde aquí: falta el envío de correos para verificarlo.',
        'finca' => 'Finca',
        'roles' => 'Mi rol|Mis roles',
    ],

    'sesiones' => [
        'titulo' => 'Mis sesiones abiertas',
        'subtitulo' => 'Los equipos donde tu cuenta tiene la sesión abierta. Si no reconoces uno, ciérralo y cambia tu contraseña.',
        'esta' => 'Esta sesión',
        'actividad' => 'Última actividad: :momento',
        'red' => 'Red :red',
        'cerrar_esta' => 'Cerrar',
        'cerrar_confirmar' => '¿Cerrar la sesión de :equipo?',
        'cerrada' => 'Sesión cerrada.',
        'ninguna_mas' => 'No tienes la sesión abierta en otro equipo.',
        'cerrar_demas' => 'Cerrar todas las demás',
        'cerrar_demas_ayuda' => 'Cierra tu sesión en los demás equipos y en la app; esta sigue abierta. Escribe tu contraseña para confirmar.',
        'cerradas_demas' => '{0} No había otras sesiones abiertas; se revocaron los accesos de la app.|{1} Se cerró 1 sesión en otro equipo.|[2,*] Se cerraron :cantidad sesiones en otros equipos.',
    ],

    'ingresos' => [
        'titulo' => 'Mis últimos ingresos',
        'subtitulo' => 'Los últimos 20 ingresos a tu cuenta, también los intentos fallidos. Se guardan 90 días, sin la dirección IP completa.',
        'vacio' => 'Todavía no hay ingresos registrados.',
        'exitoso' => 'Entró',
        'fallido' => 'Intento fallido',
        'motivos' => [
            'contrasena' => 'contraseña equivocada',
            'codigo' => 'código de verificación equivocado',
            'pendiente' => 'cuenta sin aprobar',
            'suspendida' => 'finca suspendida',
        ],
        'canales' => ['web' => 'Web', 'api' => 'App'],
        'equipo_nuevo' => 'Equipo nuevo',
        'red' => 'Red :red',
    ],

    // Aviso en el inicio: ingreso desde un equipo con el que la persona nunca había entrado
    'aviso_equipo_nuevo' => [
        'titulo' => 'Se entró a tu cuenta desde un equipo nuevo',
        'detalle' => ':equipo, el :momento.',
        'no_fui' => 'Si no fuiste tú, cierra las demás sesiones y cambia tu contraseña.',
        'revisar' => 'No fui yo: revisar',
        'entendido' => 'Entendido',
    ],

    'dos_pasos_obligatoria' => [
        'pendiente' => 'Tu finca exige la verificación en dos pasos para tu rol. Actívala para seguir.',
        'no_se_quita' => 'Tu finca exige la verificación en dos pasos para tu rol: no se puede quitar.',
    ],

    // Ajustes › Seguridad (solo propietario)
    'finca' => [
        'titulo' => 'Seguridad',
        'subtitulo' => 'Quién debe usar la verificación en dos pasos, cerrar las sesiones del personal y ver los ingresos.',
        'guardar' => 'Guardar',
    ],
    'personal' => [
        'cerrar_titulo' => 'Cerrar la sesión de todo el personal',
        'cerrar_ayuda' => 'Todos, menos tú, salen de la app en todos sus equipos y tienen que volver a entrar. Úsalo si se perdió un teléfono o cambió el personal. Los registros sin conexión que no se hayan enviado quedan en el teléfono hasta que la persona vuelva a entrar.',
        'cerrar' => 'Cerrar la sesión de todo el personal',
        'cerrar_confirmar' => '¿Cerrar la sesión de todo el personal?',
        'cerradas' => '{0} No hay más personas en la finca.|{1} Se cerró la sesión de 1 persona.|[2,*] Se cerró la sesión de :cantidad personas.',
        'ingresos_titulo' => 'Ingresos del personal',
        'ingresos_ayuda' => 'Ingresos e intentos fallidos de los últimos 90 días, por persona.',
        'ver_ingresos' => 'Ver los ingresos del personal',
        'persona' => 'Persona',
        'todas' => 'Todas las personas',
        'filtrar' => 'Filtrar',
        'vacio' => 'No hay ingresos registrados.',
        'columna_cuando' => 'Cuándo',
        'columna_persona' => 'Persona',
        'columna_resultado' => 'Resultado',
        'columna_equipo' => 'Equipo',
    ],

    // Índice de Ajustes
    'indice' => [
        'titulo' => 'En esta página',
        'finca' => 'Finca',
        'operacion' => 'Operación',
        'fases' => 'Fases',
        'concentrados' => 'Concentrados',
        'precios' => 'Precios',
        'pesaje' => 'Pesaje',
        'seguridad' => 'Seguridad',
        'datos' => 'Datos',
    ],
];
