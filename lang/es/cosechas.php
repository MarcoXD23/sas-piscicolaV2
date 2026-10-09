<?php

// Cosechas y su pesaje, con las palabras del cuaderno de la finca (nunca "arrume", "tara", "bruto" ni "neto" en pantalla)
return [
    'titulo_crear' => 'Registrar cosecha',
    'subtitulo_crear' => 'Anota cada pesada como en el cuaderno: la app hace las cuentas.',
    'titulo_detalle' => 'Cosecha del :fecha',
    'sin_lagos' => 'No hay lagos con siembra activa para cosechar.',
    'si_cosechas_todo' => 'Si cosechas todos los peces de la siembra, se cierra y el lago queda libre.',

    // Encabezado
    'lago_pescado' => 'Lago pescado',
    'lago_pescado_ayuda' => 'Si el lago tiene varias especies, elige cuál se pesca.',
    'opcion_siembra' => 'Lago :lago · :especie (sembrada el :fecha, :peces peces)',
    'destino' => 'Destino del pedido',
    'destino_ayuda' => 'A qué lugar o cliente va.',
    'fecha' => 'Fecha',
    'fecha_ayuda' => 'Viene con la fecha de hoy. Cámbiala solo para cargar una cosecha atrasada.',
    'hora' => 'Hora del registro',
    'hora_ayuda' => 'Se guarda sola al registrar (ahora son las :hora en la finca). No se edita.',
    'despacho' => 'Despachó',
    'despacho_ayuda' => 'Por defecto, tú. Cámbialo si despachó otra persona del personal.',
    'peces' => 'Cantidad de peces',
    'peces_hay' => 'Hay :cantidad peces en esa siembra.',
    'observaciones' => 'Observaciones (opcional)',

    // Modo de pesaje
    'como_se_peso' => 'Cómo se pesó',
    'modo_pesadas' => 'Por pesadas',
    'modo_total' => 'Solo el total limpio',
    'total_limpio_kg' => 'Peso limpio total (kg)',
    'total_limpio_ayuda' => 'Lo que pesó el pescado, ya sin el peso de las canastas o canastillas.',

    // Tabla de pesadas
    'peso_canasta_vacia' => 'Peso de una canasta vacía (kg)',
    'peso_canastilla_vacia' => 'Peso de una canastilla vacía (kg)',
    'pesos_vacias_ayuda' => 'Vienen de la finca; puedes corregirlos solo para esta cosecha.',
    'pesada' => 'Pesada',
    'tipo' => 'Tipo',
    'cantidad' => 'Cantidad',
    'peso_pesado' => 'Peso pesado (kg)',
    'descuento' => 'Descuento (kg)',
    'peso_limpio' => 'Peso limpio (kg)',
    'anadir_pesada' => 'Añadir pesada',
    'quitar_pesada' => 'Quitar la pesada :numero',
    'tipos' => [
        'canasta' => 'Canasta',
        'canastilla' => 'Canastilla',
    ],
    'nombres' => [
        'canasta' => 'canasta',
        'canastilla' => 'canastilla',
    ],

    // Meta (solo ayuda en pantalla: no se guarda)
    'meta' => 'Cantidad que necesito (kg)',
    'meta_ayuda' => 'Opcional. Te dice cuánto falta mientras pesas; no se guarda.',

    // Totales
    'totales' => 'Totales',
    'total_pesadas' => 'Pesadas',
    'total_cantidad' => 'Cantidad',
    'cantidad_por_tipo' => ':canastas canastas y :canastillas canastillas',
    'total_pesado' => 'Total pesado (kg)',
    'total_descuento' => 'Total de descuento (kg)',
    'total_limpio' => 'Total limpio (kg)',
    'cuenta' => [
        'grupo_canasta' => '{1} :cantidad canasta × :kg kg|[0,*] :cantidad canastas × :kg kg',
        'grupo_canastilla' => '{1} :cantidad canastilla × :kg kg|[0,*] :cantidad canastillas × :kg kg',
        'linea' => ':grupos = :descuento kg de descuento; :pesado kg − :descuento kg = :limpio kg limpios',
    ],
    'registrar' => 'Registrar cosecha',

    // Detalle
    'kg_limpios' => 'Kg limpios',
    'peso_promedio' => 'Peso promedio',
    'gramos' => ':gramos g',
    'especie' => 'Especie',
    'lago' => 'Lago',
    'registro' => 'Registró',
    'sin_dato' => '—',
    'sin_pesadas' => 'Esta cosecha se registró con el total limpio, sin tabla de pesadas.',
    'ver_detalle' => 'Ver detalle',
    'volver_lago' => 'Volver al lago',
    'pesadas' => 'Pesadas',

    // Pesos por defecto de la finca
    'ajustes' => [
        'titulo' => 'Pesaje de cosechas',
        'subtitulo' => 'Peso de UNA canasta y de UNA canastilla vacías. Se usan para calcular el descuento de cada pesada.',
        'enlace' => 'Pesaje',
        'guardar' => 'Guardar pesos',
        'guardado' => 'Pesos de la canasta y la canastilla vacías guardados.',
        'solo_lectura' => 'Los cambia el administrador en «Pesaje».',
    ],
];
