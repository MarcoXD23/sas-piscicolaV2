<?php

// Estado del sistema: /salud, piscicola:estado y piscicola:vigilar
return [
    'comprobaciones' => [
        'base' => 'Base de datos',
        'almacenamiento' => 'Almacenamiento (escribir y leer archivos)',
        'programador' => 'Programador de tareas (corrió en los últimos :minutos minutos)',
        'copia' => 'Copia de seguridad (la última tiene :horas horas o menos)',
        'disco' => 'Espacio libre en disco (al menos :porcentaje % y :gb GB)',
        'cola' => 'Cola de trabajos (ninguno esperando más de :minutos minutos y ninguno fallido)',
    ],
    'bien' => 'Bien',
    'falla' => 'FALLA',
    'todo_bien' => 'Todo funciona.',
    'algo_falla' => 'Hay comprobaciones que fallan.',
    'fallan' => 'Fallan: :comprobaciones',
    'columna_dato' => 'Dato',
    'columna_valor' => 'Valor',
    'disco' => ':gb GB libres (:porcentaje %)',
    'detalle_cola' => ':pendientes pendientes, :atascados atascados, :fallidos fallidos',
    'sin_dato' => 'Sin dato',
    'nunca' => 'Nunca',
    'sin_carpeta_respaldos' => 'Sin RESPALDOS_CARPETA configurada',
    'sin_carpeta_no_aplica' => 'Sin RESPALDOS_CARPETA configurada (no se exige fuera de producción)',
    'sin_copias' => 'No hay copias en la carpeta de respaldos',
    'copia' => ':archivo (:fecha, hace :horas horas, :mb MB)',
    'correo' => [
        'asunto_falla' => ':app: algo falla en el servidor',
        'falla' => 'La revisión automática de :app (:url) encontró fallas en:',
        'revisar' => 'Revisa el servidor con «php artisan piscicola:estado» y el registro de errores en storage/logs. No se volverá a avisar mientras siga fallando.',
        'asunto_recuperado' => ':app: el servidor se recuperó',
        'recuperado' => 'Todas las comprobaciones de :app (:url) vuelven a funcionar.',
    ],
];
