# Asistente IA: decisiones de diseño

Decisiones sobre el asistente IA (botón flotante), por fases. La memoria de trabajo es la fuente; este documento es la copia en el repositorio: si una decisión cambia, se actualizan los dos.

Última actualización: 2026-10-14.

---

## Estado actual

- **Proveedor en uso en la finca piloto:** Gemini (`ASISTENTE_PROVEEDOR=gemini`) con `gemini-3.5-flash-lite`. Todavía no hay clave de Anthropic.
- **Modelo de Claude por defecto:** `claude-haiku-5-5`, configurable con `ANTHROPIC_MODEL`. Opus queda descartado como valor por defecto: es caro y lento para preguntas de finca.
- **Respaldo del servidor** (`fallbacks`): solo se envía con los modelos que lo admiten (`services.anthropic.modelos_con_respaldo`). Haiku no lo admite.
- **Privacidad:** no se envían el nombre de la persona ni el de la finca; basta con el rol.
- **Límites:** ya existen topes diarios por persona y por finca (`ASISTENTE_LIMITE_DIARIO_PERSONA`, `ASISTENTE_LIMITE_DIARIO_FINCA`), iguales para todas las fincas.

## Fases

1. **Modelo, respaldo y privacidad.** Hecha.
2. **Instrucciones nuevas y guía de la app.** Hecha.
   - Responde con conocimiento general, con la piscicultura como especialidad.
   - Usa los datos de la finca sin inventarlos, y prefiere los valores de las fichas.
   - En medicamentos y enfermedades: orientación general **sin dosis exactas ni marcas**, y siempre remite al técnico o al veterinario.
   - Las preguntas fuera de tema se responden breve y con amabilidad.
   - El aviso de rol no cambia. Después del aviso, el asistente dice la parte del tema que la persona sí ve. No lo deduce el modelo: el código arma la frase junto a cada tema oculto, según el menú de la persona (`ContextoFinca::PARTES_PROPIAS`). El modelo la repite tal cual. Hoy hay dos frases:
     - nómina: «no la puede ver, pero sí ve sus propios pagos de nómina en Mi trabajo»;
     - ventas y dinero: «no las puede ver, pero sí ve su propio saldo de pescado en Mi trabajo», porque el saldo de pescado es la parte de las ventas que le toca al trabajador.

     Quien no tiene Mi trabajo en su menú (administrador, propietario) recibe solo el tema oculto, sin la frase. Las instrucciones no nombran ninguna pantalla. Con la regla sola, sin la frase del código, Gemini Flash-Lite a veces la omitía y una vez le sugirió Mi trabajo al administrador.
   - Va el país de la finca; **sin país configurado no asume ninguno**.
   - La guía de la app es por rol, sale de `App\Support\Menu` (el menú se ve igual) y cuida la caché de las instrucciones.
3. **Prueba de modelos.** Espera a que el usuario traiga las preguntas reales de la finca.
   - 5 preguntas por rol (20 en total). El borrador se cambia por preguntas reales de la finca.
   - Solo con Gemini, en el nivel gratuito, y con los datos de demostración: la finca de demostración o una finca de prueba de la base local, nunca datos reales. En el nivel gratuito Google puede usar lo que se envía.
   - Ya no se comparan Haiku ni Sonnet.
   - Corre fuera de las pruebas automáticas.

## Política de datos: textos libres que pueden llevar nombres

Aunque ya no se envían el nombre de la persona ni el de la finca, estos textos libres viajan al proveedor tal como los escribió la gente, y pueden llevar nombres:

- títulos de las tareas pendientes de quien pregunta;
- descripciones de las novedades de seguridad nocturna;
- títulos de la agenda (solo para el propietario).

También va el nombre del proveedor de concentrado en los despachos sin recibir (es una empresa, no una persona) y, por supuesto, lo que la persona escribe en la conversación.

No se envían cédulas, teléfonos, correos ni valores de nómina. Las ventas van solo como totales, sin compradores.

**Pendiente:** decidir en la política de datos si estos textos se filtran, se resumen o se avisa al usuario.

## Para más adelante (anotado, sin construir)

- **Modelo según el plan de la finca:** uno barato (Haiku) en los planes básico y completo, y uno más capaz (Sonnet) en el avanzado. Se configura desde el panel de plataforma, sin nombres de modelos fijos en el código.
- **Registro de uso del asistente por finca:** preguntas, tokens y costo estimado, por día y por mes, visible en el panel de plataforma.
- **Límites diarios por persona y por finca según el plan.** Hoy existen, pero son iguales para todas las fincas.
- **Opus**, si algún día se usa, solo para una «consulta experta» del plan avanzado o para análisis mensuales.
