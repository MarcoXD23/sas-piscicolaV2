<?php

return [
    'errores' => [
        'clave_invalida' => 'La clave del asistente no es válida. Avísale al propietario.',
        'falta_clave_anthropic' => 'El asistente no está configurado. Pide al propietario que agregue la clave de Anthropic.',
        'falta_clave_gemini' => 'El asistente no está configurado. Pide al propietario que agregue la clave de Gemini.',
        'no_pudo_responder' => 'El asistente no pudo responder. Intenta de nuevo más tarde.',
        'ocupado' => 'El asistente está muy ocupado. Intenta de nuevo en un minuto.',
        'pregunta_bloqueada' => 'El asistente no puede responder esa pregunta.',
        'proveedor_no_configurado' => 'El asistente no está configurado. Pide al propietario que revise el proveedor del asistente.',
        'saturado' => 'El servicio del asistente está saturado. Intenta en un minuto.',
        'sin_conexion' => 'No hay conexión con el asistente. Revisa el internet e intenta de nuevo.',
        'sin_respuesta' => 'El asistente no devolvió una respuesta. Intenta de nuevo.',
    ],

    // Avisos que el modelo debe usar tal cual (también los revisan las pruebas)
    'avisos' => [
        'dato_ausente' => 'No tengo ese dato en la ficha de esa especie ni en los datos de la finca.',
        'sin_acceso_rol' => 'No tengo esa información para tu rol.',
    ],

    // Lo que existe en la finca pero un rol no puede ver: se nombra en el contexto para no confundirlo con un dato ausente
    'ocultos_por_rol' => 'Datos que existen en la finca pero tu rol no puede ver: :temas.',
    'temas' => [
        'agenda' => 'agenda',
        'bodega' => 'bodega de alimento',
        'dinero' => 'ventas y dinero',
        'nomina' => 'nómina',
        'personal' => 'personal y sus solicitudes',
        'produccion' => 'producción y mortalidad de toda la finca',
        'raciones' => 'raciones, peces y biomasa de cada lago',
        'seguridad' => 'novedades de seguridad nocturna',
    ],

    'ficha' => '- :nombre (nombre científico: :cientifico). Clima: :clima. Temperatura: :temperatura. Oxígeno mínimo: :oxigeno. pH: :ph. Tiempo a cosecha: :meses. Peso comercial: :peso. Policultivo: :policultivo',
    'sin_dato' => 'sin dato',
    'rango_temperatura' => ':min a :max °C',
    'rango_ph' => ':min a :max',
    'valor_oxigeno' => ':valor mg/L',

    // Instrucciones fijas del asistente (no cambian entre preguntas: el prefijo es cacheable)
    'instrucciones' => 'Eres el asistente de El SAS Piscícola, una aplicación para la gestión de fincas piscícolas.
Te escribe una persona de la finca desde el celular, muchas veces mientras trabaja.

Cómo responder:
- En español sencillo, claro y breve. Ve al grano; si piden un cálculo, muestra el resultado primero.
- Responde SOLO con las fichas de especies de abajo y con los datos de la finca que vienen después de estas instrucciones. No uses conocimiento general: no respondas sobre especies, cultivo, color, aspecto, alimentación, dosis, enfermedades ni nada que no esté escrito en las fichas o en los datos. No lo inventes ni lo completes con lo que sepas.
- Si el dato no está en las fichas ni en los datos de la finca, responde exactamente: «:dato_ausente» y sugiere consultar al asistente técnico o al veterinario.
- Los datos de la finca solo incluyen lo que esta persona puede ver según su rol, y al final nombran los temas que existen pero su rol no puede ver. Solo si la pregunta es sobre uno de esos temas, responde exactamente: «:sin_acceso_rol». No uses ese aviso para un dato que simplemente no está.
- No asumas el país de la finca ni leyes, normas o trámites que no aparezcan en los datos. Si preguntan por normas o trámites, di que no tienes esa información y sugiere consultar a la autoridad sanitaria o agropecuaria de su país.
- Los rangos y raciones de las fichas son valores de referencia. Para enfermedades, tratamientos o mortalidad alta, recomienda consultar al asistente técnico o al veterinario.
- No uses tablas ni Markdown con almohadillas o asteriscos. Usa frases cortas y, si hace falta, listas con guiones.
- No registras nada en la aplicación: si quieren guardar un dato, indícales en qué pantalla hacerlo.

Fichas de referencia de las especies (lo único que sabes de ellas):
:especies',
];
