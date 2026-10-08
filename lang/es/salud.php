<?php

// Estado del sistema: /salud, piscicola:estado y piscicola:vigilar
return [
    'comprobaciones' => [
        'base' => 'Base de datos',
        'almacenamiento' => 'Almacenamiento (escribir y leer archivos)',
        'programador' => 'Programador de tareas (corrió en la última hora)',
    ],
    'bien' => 'Bien',
    'falla' => 'FALLA',
    'todo_bien' => 'Todo funciona.',
    'algo_falla' => 'Hay comprobaciones que fallan.',
    'fallan' => 'Fallan: :comprobaciones',
    'columna_dato' => 'Dato',
    'columna_valor' => 'Valor',
    'cola' => 'Cola: trabajos pendientes',
    'cola_fallos' => 'Cola: trabajos fallidos',
    'disco_libre' => 'Espacio libre en disco',
    'ultima_copia' => 'Última copia de seguridad',
    'gb' => ':gb GB',
    'sin_dato' => 'Sin dato',
    'nunca' => 'Nunca',
    'sin_carpeta_respaldos' => 'Sin RESPALDOS_CARPETA configurada',
    'sin_copias' => 'No hay copias en la carpeta de respaldos',
    'copia' => ':archivo (:fecha, :mb MB)',
    'correo' => [
        'asunto_falla' => ':app: algo falla en el servidor',
        'falla' => 'La revisión automática de :app (:url) encontró fallas en:',
        'revisar' => 'Revisa el servidor con «php artisan piscicola:estado» y el registro de errores en storage/logs. No se volverá a avisar mientras siga fallando.',
        'asunto_recuperado' => ':app: el servidor se recuperó',
        'recuperado' => 'Todas las comprobaciones de :app (:url) vuelven a funcionar.',
    ],
];
