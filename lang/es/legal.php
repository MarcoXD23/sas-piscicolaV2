<?php

/*
 * Términos de uso y política de privacidad: TEXTO BASE en BORRADOR. Debe revisarlo un abogado antes de publicarse.
 * Neutro a propósito: no menciona leyes, autoridades ni países. «:entidad» y «:correo» salen de LEGAL_ENTIDAD y LEGAL_CORREO.
 */
return [
    'borrador' => 'Borrador: este texto es una base que debe revisar un abogado antes de publicarse.',
    'version' => 'Versión :version',
    'pendiente_entidad' => '[pendiente: configurar LEGAL_ENTIDAD con el nombre de quien presta el servicio]',
    'pendiente_correo' => '[pendiente: configurar LEGAL_CORREO con el correo de contacto]',
    'terminos' => 'Términos de uso',
    'privacidad' => 'Política de privacidad',
    'enlaces' => 'Términos de uso · Política de privacidad',

    'documentos' => [
        'terminos' => [
            'titulo' => 'Términos de uso',
            'intro' => 'Estos términos regulan el uso de :app, el servicio que :entidad ofrece a las fincas piscícolas para registrar y consultar su operación.',
            'secciones' => [
                'El servicio' => 'La aplicación permite a cada finca registrar lagos, siembras, alimentación, cosechas, ventas, personal, turnos y otros datos de su operación, y consultarlos en pantallas, reportes y un asistente. Los valores de referencia (rangos de agua, raciones, fichas de especies) son orientativos y no reemplazan el criterio de un asistente técnico.',
                'Cuentas y acceso' => 'Cada persona usa su propia cuenta y es responsable de mantener su contraseña en secreto y de activar la verificación en dos pasos cuando se le recomiende. El propietario de cada finca aprueba a su personal y decide sus roles. Si sospechas que alguien usó tu cuenta, cambia tu contraseña y avisa al propietario.',
                'Uso permitido' => 'El servicio se usa solo para la gestión de la finca. No está permitido intentar acceder a datos de otras fincas, interferir con el funcionamiento del servicio, subir contenido ajeno sin derecho a usarlo ni usar el asistente para fines distintos a la operación de la finca.',
                'Datos de la finca' => 'Los datos que la finca registra le pertenecen. :entidad los guarda y los procesa para prestar el servicio y no los vende ni los comparte con terceros, salvo con los proveedores técnicos indispensables para funcionar (por ejemplo, el alojamiento y el servicio del asistente) o cuando la finca lo pida.',
                'Fotos y contenido' => 'Quien sube una foto declara que la tomó o que tiene derecho a usarla. La finca puede borrar sus fotos en cualquier momento.',
                'Disponibilidad' => 'Se procura que el servicio esté disponible y que haya copias de seguridad, pero pueden ocurrir interrupciones por mantenimiento o fallas. La finca debe verificar la información antes de tomar decisiones importantes.',
                'Cambios' => 'Estos términos pueden cambiar. Cuando cambien, se pedirá aceptarlos de nuevo en el siguiente ingreso.',
                'Contacto' => 'Para preguntas sobre estos términos escribe a :correo.',
            ],
        ],
        'privacidad' => [
            'titulo' => 'Política de privacidad',
            'intro' => 'Esta política explica qué datos personales trata :entidad en :app, para qué y cómo puedes ejercer tus derechos.',
            'secciones' => [
                'Qué datos se guardan' => 'Tu nombre, correo, contraseña (siempre cifrada con un algoritmo de un solo sentido), tus roles en la finca, tus turnos y horas, y los registros que haces (raciones, rondas, mediciones, etc.) con su fecha y hora. Si la activas, una clave de verificación en dos pasos guardada cifrada. Al aceptar estos textos se guarda la versión y la fecha, pero no tu dirección IP.',
                'Para qué se usan' => 'Para darte acceso, organizar el trabajo de la finca, calcular la nómina con las tarifas que fija el propietario, generar reportes y dejar un registro (bitácora) de quién hizo cada acción.',
                'Quién los ve' => 'Solo las personas de tu finca, según su rol: el propietario y el administrador ven más información que el trabajador o el celador. Las demás fincas nunca ven tus datos. El asistente con inteligencia artificial recibe únicamente los datos que tu rol puede ver, solo cuando le haces una pregunta.',
                'Fotos' => 'A las fotos se les quitan los datos de ubicación y del teléfono antes de guardarlas. Se guardan en un almacenamiento privado de la finca.',
                'Cuánto tiempo' => 'Mientras la finca use el servicio. Las copias de seguridad se conservan por un tiempo limitado y se borran al vencer su plazo.',
                'Seguridad' => 'Se usan conexiones cifradas, contraseñas cifradas, permisos por rol, límites de intentos y copias de seguridad.',
                'Tus derechos' => 'Puedes pedir ver, corregir o borrar tus datos personales, o retirar tu aceptación. Algunas cosas pueden conservarse si son necesarias para la operación o la bitácora de la finca; en ese caso se te explicará el motivo.',
                'Contacto' => 'Para cualquier solicitud sobre tus datos escribe a :correo.',
            ],
        ],
    ],

    'aceptar' => [
        'titulo' => 'Términos de uso y privacidad',
        'texto' => 'Para seguir, lee y acepta los términos de uso y la política de privacidad (versión :version).',
        'casilla' => 'Leí y acepto los términos de uso y la política de privacidad.',
        'boton' => 'Aceptar y continuar',
        'no_acepto' => 'Si no estás de acuerdo, cierra sesión.',
        'marca_la_casilla' => 'Para continuar debes aceptar los términos de uso y la política de privacidad.',
        'gracias' => 'Gracias. Términos y privacidad aceptados.',
        'pendiente_api' => 'Debes aceptar la versión vigente de los términos de uso y la política de privacidad.',
        'registro_casilla' => 'Leí y acepto los :terminos y la :privacidad.',
    ],
];
