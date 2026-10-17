<?php

/*
| Ajustes › Precios (F6c): docs/diseno/dia-de-proceso.md, tema 8. Ningún precio de la finca piloto en el código.
*/

return [
    'titulo' => 'Precios de venta',
    'subtitulo' => 'Precio por kilo de cada especie. Solo el propietario los cambia; cada venta guarda los suyos y un cambio solo aplica a las ventas nuevas.',
    'enlace' => 'Ver y cambiar precios',
    'volver' => 'Volver a Ajustes',
    'general' => 'Precio general por kg',
    'entra_en_cupo' => 'Entra en el cupo',
    'cupo' => 'Precio en el cupo por kg',
    'ayuda' => 'El precio general es para particulares y para lo que pase del cupo. Si la especie entra en el cupo, fijos y jornaleros pagan el precio en el cupo hasta completar sus kilos del período.',
    'cupo_activo' => 'Cupo de cada trabajador: :kg kg por período (Ajustes › Operación de la finca).',
    'sin_cupo' => 'La finca no tiene cupo (0 kg en Ajustes › Operación de la finca): todos pagan el precio general.',
    'sin_especies' => 'No hay especies activas en el catálogo de la finca.',
    'guardar' => 'Guardar precios',
    'guardados' => 'Precios guardados (:cantidad especies cambiaron).',
    'sin_cambios' => 'No cambiaste ningún precio.',
    'sin_precio' => 'sin precio',
    'texto_sin_cupo' => ':general por kg',
    'texto_con_cupo' => ':general por kg; :cupo en el cupo',
    'errores' => [
        'precio_minimo' => 'El precio debe ser mayor que cero.',
        'falta_precio_cupo' => 'Si :especie entra en el cupo, escribe su precio en el cupo.',
    ],
];
