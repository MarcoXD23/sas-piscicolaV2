<?php

return [
    'titulo' => 'Fases de los lagos',
    'subtitulo' => 'Define las etapas productivas de la finca y las metas esperadas por fase y especie (peso, días, supervivencia y densidad).',
    'enlace' => 'Configurar fases y reglas',
    'volver_ajustes' => 'Ajustes',
    'nueva_fase' => 'Nueva fase',
    'nombre' => 'Nombre de la fase',
    'orden' => 'Orden',
    'guardar_fase' => 'Guardar fase',
    'eliminar' => 'Eliminar',
    'confirmar_eliminar' => '¿Eliminar la fase «:nombre»?',
    'sin_fases' => 'Aún no hay fases configuradas en la finca.',
    'lagos_en_fase' => '{0} Sin lagos|{1} 1 lago|[2,*] :count lagos',

    'por_defecto' => [
        'alevinaje' => 'Alevinaje',
        'precria' => 'Precría',
        'levante' => 'Levante',
        'engorde' => 'Engorde',
    ],

    'reglas' => [
        'titulo' => 'Reglas por especie en :fase',
        'subtitulo' => 'Valores esperados para comparar con lo real en el detalle del lago. Deja en blanco lo que no aplique.',
        'especie' => 'Especie',
        'peso_entrada_g' => 'Peso entrada (g)',
        'peso_salida_g' => 'Peso salida (g)',
        'dias_esperados' => 'Días en la fase',
        'supervivencia_porcentaje' => 'Supervivencia (%)',
        'densidad_min_m2' => 'Densidad mín. (peces/m²)',
        'densidad_max_m2' => 'Densidad máx. (peces/m²)',
        'guardar' => 'Guardar regla',
    ],

    // Comparación real vs. esperado en el detalle del lago
    'comparacion' => [
        'titulo' => 'Fase :fase · real vs. esperado',
        'sin_regla' => 'La fase :fase aún no tiene reglas configuradas para :especie.',
        'dias' => 'Días en la fase',
        'dias_valor' => ':real de :esperado días',
        'dias_atraso' => ':atraso días de atraso',
        'dias_en_tiempo' => 'En tiempo',
        'peso' => 'Peso promedio',
        'peso_meta' => 'Meta de salida: :salida g (esperado hoy: :esperado g)',
        'peso_atrasado' => ':diferencia g bajo lo esperado hoy',
        'peso_al_dia' => 'Al día (:diferencia g sobre lo esperado hoy)',
        'supervivencia' => 'Supervivencia',
        'supervivencia_meta' => ':real % (esperada: :esperada %)',
        'supervivencia_baja' => ':diferencia puntos por debajo de lo esperado',
        'supervivencia_bien' => 'Dentro de lo esperado',
        'densidad' => 'Densidad del lago',
        'densidad_valor' => ':real peces/m²',
        'densidad_rango' => 'Referencia: :min a :max peces/m²',
        'estados_densidad' => [
            'alta' => 'Por encima de la densidad de referencia',
            'baja' => 'Por debajo de la densidad de referencia',
            'en_rango' => 'En rango',
        ],
    ],
];
