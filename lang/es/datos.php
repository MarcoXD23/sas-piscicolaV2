<?php

// Datos de la finca: exportarlos y eliminarlos (solo el propietario; también el administrador de plataforma)
return [
    // Exportación: documento cifrado con una APP_KEY que ya no está
    'documento_ilegible' => '[no se pudo descifrar: falta la APP_KEY con que se guardó]',
    'titulo' => 'Datos de la finca',
    'subtitulo' => 'Descarga una copia de los datos de tu finca o pide que se eliminen.',
    'enlace' => 'Datos de la finca',

    'codigo' => [
        'titulo' => 'Código de la finca',
        'ayuda' => 'Dáselo a tu personal para que pida acceso en /registro.',
        'copiar' => 'Copiar',
        'copiado' => 'Copiado',
    ],

    'exportar' => [
        'titulo' => 'Exportar mis datos',
        'ayuda' => 'Un archivo ZIP con una hoja (CSV) por cada tipo de registro y las fotos originales. No incluye contraseñas, claves ni datos de otras fincas. Se prepara en unos minutos y se puede descargar durante 24 horas.',
        'boton' => 'Exportar mis datos',
        'encolada' => 'Estamos preparando tus datos. Vuelve a esta pantalla en unos minutos para descargarlos.',
        'espera' => 'Ya pediste una exportación hace poco. Podrás pedir otra en :minutos minutos.',
        'vencida' => 'Este paquete ya no está disponible. Pide una exportación nueva.',
        'recientes' => 'Exportaciones recientes',
        'pedida' => 'Pedida el :fecha',
        'estados' => [
            'pendiente' => 'En preparación',
            'lista' => 'Lista',
            'fallo' => 'No se pudo preparar',
        ],
        'descargar' => 'Descargar (:tamano MB)',
        'disponible_hasta' => 'Disponible hasta el :fecha',
        'vencio' => 'Venció',
        'ninguna' => 'Todavía no has exportado los datos.',
    ],

    'eliminar' => [
        'titulo' => 'Eliminar la finca',
        'ayuda' => 'Se borrarán TODOS los datos de la finca (lagos, siembras, cosechas, ventas, personal, nómina, bitácora y fotos) y las cuentas de su personal. La finca queda suspendida :dias días antes del borrado definitivo, y en ese plazo puedes cancelarlo. Te recomendamos exportar tus datos antes.',
        'codigo' => 'Escribe el código de la finca (:codigo) para confirmar',
        'actual' => 'Tu contraseña',
        'boton' => 'Eliminar la finca',
        'codigo_incorrecto' => 'El código no coincide con el de la finca.',
        'pantalla_titulo' => 'Esta finca se eliminará',
        'pantalla_texto' => 'Todos los datos de :finca se borrarán definitivamente el :fecha. Hasta entonces la finca está suspendida y solo tú puedes entrar, para cancelar la eliminación.',
        'cancelar_boton' => 'Cancelar la eliminación',
        'cancelada' => 'Eliminación cancelada. La finca vuelve a funcionar.',
        'copias' => 'Las copias de seguridad guardan los datos hasta que vence su plazo (:dias días); después desaparecen también de ellas.',
        'en_eliminacion' => 'En eliminación: se borra el :fecha',
        'programada_plataforma' => 'La finca «:finca» se eliminará el :fecha.',
        'eliminar_plataforma' => 'Eliminar…',
        'cancelar_plataforma' => 'Cancelar eliminación',
        'confirmar_plataforma' => 'Eliminar la finca «:finca»',
        'tu_contrasena_plataforma' => 'Tu contraseña del panel',
    ],

    'correo_eliminacion' => [
        'asunto' => 'La finca :finca se eliminará',
        'saludo' => 'Hola, :nombre:',
        'motivo' => 'Se pidió eliminar la finca :finca. Todos sus datos se borrarán definitivamente el :fecha. Si no fue así o cambiaste de opinión, entra y cancela la eliminación antes de esa fecha.',
        'boton' => 'Entrar a cancelar',
        'copias' => 'Las copias de seguridad conservan los datos hasta que vence su plazo (:dias días).',
    ],

    'leeme' => "Datos de la finca exportados desde El SAS Piscícola.\n\n- Un archivo CSV por tipo de registro (UTF-8; separador: coma). finca.csv tiene los datos de la finca.\n- Las fechas con hora están en UTC (formato ISO 8601); las fechas de solo día son días de la finca.\n- fotos/: las fotos originales de las especies (especie-<id>-foto-<orden>).\n- No incluye contraseñas, claves de verificación en dos pasos, tokens ni datos de otras fincas.\n- Términos y privacidad vigentes: versión :version.\n",
];
