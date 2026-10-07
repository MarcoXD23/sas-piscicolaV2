<?php

return [
    'crear_admin_plataforma' => [
        'descripcion' => 'Crea el acceso al panel de plataforma (dueño del software; pide la contraseña)',
        'nombre' => 'Nombre',
        'correo' => 'Correo',
        'clave' => 'Contraseña (mínimo 12 caracteres)',
        'confirmacion' => 'Confirma la contraseña',
        'creado' => 'Acceso de plataforma creado para :correo. Ingresa en :url',
    ],
    'crear_propietario' => [
        'descripcion' => 'Crea una finca y su usuario propietario (pide la contraseña)',
        'finca' => 'Nombre de la finca',
        'codigo' => 'Código de la finca (lo usará el personal para registrarse)',
        'pais' => 'País de la finca (código de 2 letras, por ejemplo CO, MX o PE)',
        'zona' => 'Zona horaria de la finca',
        'nombre' => 'Nombre del propietario',
        'correo' => 'Correo del propietario',
        'clave' => 'Contraseña (mínimo 8 caracteres)',
        'confirmacion' => 'Confirma la contraseña',
        'creado' => 'Propietario creado para la finca «:finca» (código: :codigo, país: :pais, zona: :zona).',
        'pais_invalido' => 'El país no es un código válido de 2 letras.',
        'zona_invalida' => 'La zona horaria no es válida.',
    ],
    'evaluar_alertas' => [
        'descripcion' => 'Revisa las alertas de todas las fincas activas (se programa cada hora)',
        'listo' => 'Alertas revisadas en :fincas finca(s).',
    ],
    'usuario_base' => [
        'descripcion' => 'Imprime el SQL para usar dos roles de PostgreSQL (dueño para migraciones y uno de la app sin cambiar estructura) en lugar de un superusuario. No ejecuta nada.',
        'nombres_invalidos' => 'Los nombres de los roles deben ser distintos y usar solo minúsculas, números y guion bajo.',
        'hoy_superusuario' => 'Hoy la app se conecta como «:rol», que es SUPERUSUARIO. En producción usa los roles de abajo.',
        'hoy_normal' => 'Hoy la app se conecta como «:rol» (no es superusuario).',
        'como_ejecutar' => 'Guarda el SQL en un archivo y córrelo como superusuario: psql -U postgres -d :base -v clave_dueno=\'...\' -v clave_app=\'...\' -f roles.sql. Luego: migraciones con :dueno y la app (DB_USERNAME en el .env) con :app. Ver README, «Despliegue».',
    ],
];
