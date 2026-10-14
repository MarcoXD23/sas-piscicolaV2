<?php

return [
    'unidades_peso' => [
        'g' => 'Gramos (g)',
        'kg' => 'Kilos (kg)',
        'lb' => 'Libras (lb)',
    ],
    'anteriores' => 'Anteriores',
    'cancelar' => 'Cancelar',
    'desde' => 'Desde',
    'dias_cortos' => [
        0 => 'Lun',
        1 => 'Mar',
        2 => 'Mié',
        3 => 'Jue',
        4 => 'Vie',
        5 => 'Sáb',
        6 => 'Dom',
    ],
    'fecha' => 'Fecha',
    'formatos' => [
        'mes_anio' => 'F \\d\\e Y',
        'dia_mes' => 'l j \\d\\e F',
        // Solo la fecha: la hora se agrega con App\Support\Hora (12 o 24 horas) y el texto fecha_y_hora
        'fecha_larga' => 'l j \\d\\e F \\d\\e Y',
        'fecha_y_hora' => ':fecha, :hora',
    ],
    'guardar' => 'Guardar',
    'guardar_cambios' => 'Guardar cambios',
    'hasta' => 'Hasta',
    'horas_corto' => ':horas h',
    'hoy' => 'Hoy',
    'lago_opcional' => 'Lago (opcional)',
    'ninguno' => 'Ninguno',
    'pagina_de' => 'Página :actual de :total',
    'paginas' => 'Páginas',
    'personal' => 'Personal',
    'rango_dias' => ':desde – :hasta (:dias día(s))',
    'semana_del' => 'Semana del :desde al :hasta',
    'siguientes' => 'Siguientes',
    'sin_finca_activa' => [
        'consulta' => 'Consulta de :modelo sin finca activa. Fija la finca (TenantActual::establecer) o usa TenantActual::sinFiltro().',
        'creacion' => 'Se intentó crear :modelo sin finca activa y sin tenant_id.',
    ],
    'y' => 'y',
];
