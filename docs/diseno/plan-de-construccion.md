# Plan de construcción

Plan aprobado el 2026-10-15 para construir el diseño de `dia-de-proceso.md` (temas 1 a 15) y `asistente.md`.

**Cómo se trabaja:**

- Se construye fase por fase. Antes de cada fase se listan los archivos y se espera el «apruebo».
- Al cerrar cada fase:
  - se corren todas las pruebas;
  - se hace el commit y se sube a `main` (la única rama desde el 2026-10-15);
  - se espera el «continúa».
- Antes de cada migración que toque datos existentes se saca un respaldo fuera del proyecto.
- Todo campo nuevo del paquete de Registro rápido se **agrega**: nunca se cambia ni se quita, para no romper la cola sin conexión de los teléfonos.
- Cada fase pasa la prueba de aislamiento entre fincas.

Los días son estimados de trabajo.

---

## Orden aprobado

**Cambio de plan (2026-10-16):** el piloto en la finca ya no va después de F3. Queda **al final**, después de F13 y de F14 (servidor). Mientras tanto se sigue construyendo en el orden del plan.

### Primer bloque (hecho)

**Orden:** F0 → F1 → F4 → F2 → F3. Las fases de los lagos van antes que la ración, porque la ración sugerida depende de la fase y del peso del lago.

### Fase agregada: invitaciones de finca (2 días)

Pedida por el usuario el 2026-10-16 y construida antes de F5. Desde el panel de plataforma se invita un correo, con un enlace de un solo uso que vence en 7 días, y el cliente crea su finca él mismo. Lo dejado para después:

- **el registro público, con o sin aprobación:** cuando haya clientes que lleguen solos;
- **el cobro.**

Ver «Invitar una finca» en el README.

### Siguiente, en este orden

**Orden:** F5 → F6a → F6b → F6c → F6d → F7 → F8a → F8b → F9 → F10 → F11 → F12 → F13 → F14 (servidor) → piloto en la finca.

- **Jornaleros (F6a a F6d):** la pausa está levantada. Se construye con los valores configurables y provisionales. Se ajustan con los números de los días de proceso reales, a más tardar en el piloto.
- **F12:** el Excel de ejemplo, sin nombres, lo prepara el usuario más adelante.

**Total aproximado:** unos 57 a 63 días de trabajo con F14, sin contar el piloto.

### Al final

- **F14. Servidor:** ver la fase más abajo.
- **Piloto en la finca:** la finca la usa en su trabajo diario. De los días de proceso del piloto salen los números reales que todavía son provisionales: tolerancias del cuadre, topes, rendimiento y pago a jornaleros.

### Por qué este orden

- **La configuración va primero (F1):** de ahí leen sus reglas las fases, la ración, el agua, el proceso, las canastas y la nómina.
- **Fases (F2) antes que la ración (F3):** la ración sugerida depende de la fase y del peso.
- **Catálogo de concentrados (F3) antes que la bodega (F7):** el precio y el costo van por concentrado.
- **La jornada (F6a) antes que las ventas (F6c), los despachos (F6d) y la nómina (F8b):**
  - las ventas son por jornada;
  - el cuadre necesita las salidas;
  - las horas extra salen de la hora de salida de la jornada.
- **La bodega con precio (F7) antes que Gastos (F9):** el alimento es más de la mitad del gasto.
- **La guía de uso al final (F13),** cuando las pantallas ya no cambien.

---

## Fases

### F0. Correcciones (0,5 día)

- Corregir el tema 15.5 del diseño y la memoria: el «limpio» del pesaje actual es el peso neto sin canastas, o sea el **pescado entero**. Lo que no existe es el limpio procesado.
- Cambiar ese «limpio» del pesaje por **«entero»**, el nombre que usa la finca y el diseño (rendimiento = limpio ÷ entero): textos y claves internas. La columna `neto_kg` no cambia. Así «limpio» queda solo para el pescado procesado del día de proceso.
- **Toca:** el documento de diseño, `lang/es` y los textos del pesaje de la cosecha. No cambia la base de datos.
- **Riesgo:** ninguno.

### F1. Configuración por finca (2–3 días)

- Ajustes guardados por finca, con valores por defecto:
  - interruptores que se encienden o se apagan;
  - valores;
  - «quién puede» (quién mide el agua, quién registra la recepción…).
- Plantilla con los valores de la finca piloto.
- Pantalla Ajustes organizada por secciones.
- Solo el propietario cambia los ajustes, y queda en la bitácora.
- Se construyen solo los ajustes que usan las fases siguientes (tema 1: no construir opciones que nadie ha pedido).
- **Tablas:** `ajustes_finca` (nueva). Las columnas que ya existen en `tenants` (taras, alertas, unidad de peso) se quedan donde están.
- **Pruebas:**
  - valores por defecto;
  - aislamiento entre fincas;
  - solo el propietario cambia;
  - bitácora.
- **Riesgo:** bajo.

### F4. Agua: quién mide (1–1,5 días)

- Ajuste «quién mide el agua».
- El administrador mide en Registro rápido y sin conexión.
- **Pruebas:**
  - el administrador mide sin conexión;
  - un rol no autorizado no puede;
  - la bitácora.
- **Riesgo:** la ruta de medición sin conexión exige el turno al celador, pero no al administrador.

### F2. Fases de los lagos (3–4 días)

- La lista de fases por finca.
- La fase del lago: campo nuevo; no se toca `tipo`, que es el material.
- El área del lago, para la densidad.
- Las reglas por fase y especie: peso de entrada y salida, días, supervivencia y densidad.
- En el detalle del lago: lo real contra lo esperado (atraso en días, peso y supervivencia).
- **Tablas:** `fases`, `reglas_fase`, y `lagos.fase_id` y `lagos.area_m2`, que aceptan vacío.
- **Pantallas:** Ajustes › Fases, editar lago, detalle del lago, Registro rápido (la fase en el paquete).
- **Pruebas:**
  - aislamiento;
  - un lago sin fase sigue funcionando;
  - el cálculo del atraso.
- **Riesgos:**
  - los lagos que ya existen quedan «sin fase» hasta que alguien se la ponga;
  - el paquete del teléfono crece, solo con campos agregados.

**Estado: hecha** (hecha con Antigravity, revisada en F2-F3c y unida a `main` el 2026-10-15, commit f6a4f6b).
- Las reglas iniciales traen solo los datos de la finca piloto (tema 15.1): precría, levante y engorde para las especies de agua cálida. La trucha y el alevinaje quedan sin reglas, y lo que falta dice «sin dato».
- El detalle del lago compara el peso con el rango de la fase, la supervivencia y la densidad.
- **Pendiente para F5 (hecho en F5):** el atraso en días, con los días del lote en la fase.
- **Pendiente de la finca:**
  - la supervivencia esperada y las densidades de alevinaje, precría y levante;
  - el peso de entrada a precría;
  - el peso de salida de las demás especies.

### F3. Concentrados y ración configurable (4 días)

- Catálogo de concentrados: nombre corto (el color del hilo en la finca piloto), proteína, pellet y peso del bulto.
- Tabla de ración por finca y especie, que reemplaza las tablas fijas de `CalculadoraRacion`. Arranca con las tablas de hoy y los valores de la finca piloto.
- Sugerencia de concentrado según la fase y el peso.
- Aviso del cambio gradual (¼, ½ y ¾ durante 3 días).
- Ración en kilos o en bultos.
- Alerta de muestreo vencido (30 días).
- **Tablas:** `concentrados`, `tabla_racion`.
- **Datos que cambian:** `tipo_concentrado` (texto) en stock, raciones y despachos pasa a ligarse al catálogo.
- **Pruebas:**
  - ración sugerida con la tabla de la finca;
  - cambio gradual;
  - bultos guardados como kg;
  - alerta de muestreo.
- **Riesgos:**
  - **migración de datos:** sacar respaldo antes;
  - **cola sin conexión:** los registros guardados en los teléfonos envían `tipo_concentrado` como texto. El servidor debe seguir aceptándolo, buscándolo por nombre.
- **Falta de la finca:** el color de hilo de cada concentrado. Se construye igual, porque es el nombre corto que escribe la finca.

**Estado: hecha** (hecha con Antigravity, revisada en F2-F3c y unida a `main` el 2026-10-15, commit f6a4f6b).
- Ya no hay tablas fijas en `CalculadoraRacion`. La tabla de la finca arranca con los porcentajes de antes y, en el perfil cálido, con la tabla de la finca piloto (tema 15.3). La proteína y el pellet que no dio la finca quedan vacíos.
- El concentrado sugerido es el de la regla de la fase (`reglas_fase.concentrado_id`). Si la fase no tiene uno, es el del tramo de peso.
- La bodega no agrega concentrados al catálogo, y renombrar uno no cambia la historia.
- El plazo del muestreo es el ajuste `muestreo.plazo_dias` de `ajustes_finca` (30 días por defecto), sin columna nueva.
- El paquete de Registro rápido calcula la ración de todos los lagos con las mismas consultas, y solo agrega campos (`concentrado_sugerido`, `nombre_corto`, `peso_bulto_kg`).
- **Pendiente de la finca:**
  - los concentrados azul (24 %), blanca (30 %) y «38» (38 %) se crean en Ajustes › Concentrados (no van por defecto en las fincas nuevas);
  - la proteína y el pellet de los tramos que faltan.
- **Antes de migrar `sas_piscicola_v2`:** respaldo con `piscicola:respaldar`.

### F5. Lotes y trazabilidad (3 días)

- Catálogo de proveedores de alevinos: los terceros que ya existen, con la marca de proveedor.
- Número de lote.
- En el traslado:
  - preguntar «sumar al lote o crear lote aparte» (tema 4);
  - arrastrar los días y el alimento, para la conversión de toda la vida del pez.
- **Activar el atraso en días de las fases (pendiente de F2):** con los días en la fase de cada lote, comparar contra `reglas_fase.dias_esperados` en el detalle del lago (`EstadoFase`). Hasta F5 no se muestra, porque el lote solo conoce sus días desde la siembra.
- Comparación de proveedores: crecimiento, supervivencia y conversión.
- **Pruebas:**
  - conversión de vida a través de dos traslados;
  - siembras viejas sin proveedor del catálogo.
- **Riesgo:** los proveedores escritos a mano se ligan con una pantalla de revisión, sin adivinar.

**Estado: hecha** (2026-10-17, en dos commits a `main`).
- **Migración:** una sola, `2026_11_10_000001_lotes_y_trazabilidad`, con todas las columnas vacías permitidas: `terceros.provee`, `siembras.proveedor_id` y `numero_lote`, `lagos.fase_desde` y `traslados.modo`. Antes de correrla en `sas_piscicola_v2` se sacó el respaldo.
- **Proveedores:** pantalla «Proveedores» sobre `terceros`, con «qué provee» (alevinos, alimento, otros; uno o varios, en una lista JSON y sin enum). Sirve también para F7. El administrador la edita y el propietario la consulta. La siembra solo ofrece los proveedores activos de alevinos.
- **Revisión:** las siembras viejas siguen mostrando el texto escrito a mano. Se ligan por texto exacto (sin espacios de más) a un proveedor que elige el administrador, o se crea uno con ese nombre. El texto escrito se conserva.
- **Número de lote:** el ajuste `lotes.numeracion` de cada finca es manual por defecto. En modo automático el formato es `AAAA-NNN`, con consecutivo por finca y año. El lote aparte conserva el número del origen.
- **Traslado:** siempre pregunta «sumar» o «aparte».
  - Sumar: si el destino no tiene lote de esa especie, se niega; si tiene varios, hay que elegir uno. El lote conserva sus días.
  - Aparte: el lote nuevo conserva el proveedor y los dos números de lote.
- **Conversión de vida** (`ConversionVida`): se calcula, no se copia.
  - Cada salida de peces vivos se lleva su parte de lo acumulado del lote: peces que salen ÷ peces que había.
  - Se ve en el detalle del lago para los lotes que recibieron peces. Los totales siguen con el FCA propio.
- **Atraso en días:** los días en la fase cuentan desde la fecha más reciente entre la llegada del lote y `lagos.fase_desde`, que se pone al asignar o cambiar la fase. Se comparan con `dias_esperados`. Los lagos que ya tenían fase antes de F5 cuentan desde la llegada del lote.
- **Comparación de proveedores:** en Conversión. Supervivencia = (vivos + cosechados + trasladados afuera) ÷ (sembrados + recibidos); los traslados dentro del mismo grupo se cancelan. Además: crecimiento en g por día de vida y FCA.
- **Supervivencia del detalle del lago (F2):** desde el arreglo posterior a F5 usa la misma fórmula, por lote.
- **Para después:** lo que queda al cerrar un lote sin cosechar cuenta como pérdida en la comparación hasta que F6a registre la diferencia.

### F6a. Jornada de proceso (5 días)

- La jornada, con sus lagos y el entero por lago y especie.
- Ayuno y día sin alimentación (tema 5).
- «Quedó vacío / quedó pescado» y el reparto entre siembras revueltas (temas 3 y 4).
- **Tablas:** `jornadas`, `jornada_lagos`, `pesajes_entero`. La cosecha de hoy sigue funcionando para las fincas sin proceso.
- **Riesgo alto:** convive con las cosechas que ya existen y con su conversión.

### F6b. Presentaciones, limpio y rendimiento (3 días)

- Tallas y procesos, y la presentación como especie + tamaño + proceso.
- Pesaje del limpio por presentación.
- Rendimiento contra la referencia (86 % en la finca piloto, que luego se reemplaza por el promedio real).
- «Reproducción» que se enciende o se apaga.
- Esperado contra real.

### F6c. Venta rápida, precios, cupo y obsequios (5 días)

- **Al inicio de la fase, el cambio de AGENTS.md** (con la aprobación del dueño del proyecto): el administrador prepara el pago a jornaleros y el propietario lo aprueba, igual que la nómina. Este cambio va aquí y no en F8a, porque el pago a jornaleros se construye en esta fase.
- Precios por especie y tipo de comprador.
- Cupo semanal.
- Forma de pago con saldo pendiente.
- Obsequios con su autorización.
- **Pago a jornaleros** (temas 8, 10 y 11 del diseño):
  - por jornada o por kilo, según el cargo y lo que configure la finca (por ejemplo, los procesadores por kilo limpio de la jornada, repartido en partes iguales);
  - descuento de lo que el jornalero compró a crédito («descontar del pago»);
  - si el descuento es mayor que lo ganado, queda un saldo pendiente para la siguiente jornada; nunca hay un pago negativo;
  - planilla de pago de la jornada para imprimir y firmar al recibir el pago;
  - lo prepara el administrador y lo aprueba el propietario; nadie aprueba su propio pago.
- **Pendiente de la finca** (mientras tanto, F6c usa valores configurables):
  - qué cargos cobran por jornada (una suma fija) y cuáles por kilo;
  - qué entra en «a crédito»: solo el pescado comprado, o también anticipos u otras cosas.
- **Riesgo:** las ventas que ya existen están ligadas a una cosecha. Se mantienen; las nuevas van por jornada.

### F6d. Pedidos, despacho, remisión y canastas (5 días)

- Pedido a una ciudad y un cliente, con líneas por presentación (kilos pedidos y despachados).
- Remisión en PDF:
  - consecutivo;
  - «Nombre común (nombre científico)»;
  - «recibí a satisfacción»;
  - placa opcional y oculta.
- Salvoconducto: solo el número y el archivo.
- Cuadre asimétrico (tema 12).
- Canastas prestadas: saldo por destino o transportador, con aviso.

### F7. Bodega con factura, precio y costos (3–4 días)

- Factura, remisión, entrega total o parcial, precio por bulto y quién recibió.
- Conteo del trabajador sin conexión, con confirmación (tema 13).
- Costo del alimento por lago y por kilo producido.
- El proveedor del despacho se elige de un catálogo de proveedores de la finca (hoy es texto libre).
- **Riesgos:**
  - las recepciones viejas quedan sin precio, y el costo de ese período se muestra «sin dato»;
  - el conteo sin conexión entra a la cola.

### F8a. Permisos y personal configurables (2–3 días)

- **Primero se cambia AGENTS.md, con la aprobación del dueño del proyecto.** (El cambio para el pago a jornaleros ya se hizo al inicio de F6c.)
- Quién aprueba permisos, horas extra y personal, y nadie se aprueba a sí mismo.
- Aviso de solicitudes de acceso.
- **Riesgo:** reglas de seguridad. Todo se comprueba en el servidor, con pruebas por rol.

### F8b. Nómina mensual (6 días)

- Período configurable y visto bueno con devolución.
- La fila del administrador, que aprueba siempre el propietario.
- Recargos configurables; sin ellos no se aprueba una nómina con horas extra.
- Horas extra calculadas desde la salida de la jornada.
- Planilla en PDF y foto de la planilla firmada.
- **Riesgo:** solo columnas nuevas. Las semanas ya liquidadas se siguen leyendo igual.
- **Falta de la finca:** la foto de la nómina mensual (para el formato de la planilla) y los valores de los recargos (con un contador).

### F9. Gastos e ingresos (3 días)

- Categorías de gasto configurables por finca, con la plantilla de la finca piloto (tema 15.8).
- Gastos del mes, con la foto del recibo (guardada privada y separada por finca).
- Venta de alevinos.
- Resumen mensual: gastos por categoría, con el alimento sacado de la bodega.
- Costo por kilo, de dos formas:
  - **costo de producción por kilo producido** (crecimiento de la biomasa), por lote y por lago;
  - **resultado del mes:** gastos contra ventas, y costo por kilo vendido.
- Exportar a Excel para el contador, con openspout (ya está en el proyecto y genera los Excel de los reportes, en `ExportadorExcel`). Sin paquete nuevo.

### F10. Planilla mensual de ración (1,5 días)

- Días por lagos, una por concentrado, con entrada, salida y saldo de bodega, imprimible.

### F11. Buenas prácticas, BPPA (3 días)

- Visitantes, disposición de la mortalidad, plagas, monitoreo sanitario y cuarentena.
- El veterinario en los tratamientos.
- Informe BPPA.

### F12. Importador desde Excel (4–5 días)

- Con revisión antes de guardar y conversión bien calculada.
- **Falta:** el Excel de ejemplo, sin nombres.

### F13. Guía de uso y asistente (2–3 días)

- La guía de uso.
- El asistente al día con los módulos nuevos.
- La fase 3 del asistente: espera las preguntas reales de la finca.

### F14. Servidor (2–3 días)

Va al final, antes del piloto en la finca. La guía paso a paso es `docs/DESPLIEGUE.md`.

- VPS, dominio y HTTPS.
- Envío de correos y, con él, la verificación por código en las invitaciones (ver «Para más adelante»).
- Respaldos diarios, también copiados fuera del servidor.
- El cron del programador de Laravel, para que corra el comando de vigilancia (`piscicola:vigilar`) y las demás tareas programadas.
- La lista de lo que debe tener el `.env` de producción según `DESPLIEGUE.md`. **El `.env` lo edita el usuario**, nunca quien programa.

### Para más adelante (sin fecha)

- Equipos y mantenimiento (tema 15.10).
- La unidad de cultivo para jaulas o canales (tema 14).
- Finca demo: una finca con datos inventados y una cuenta de solo consulta para que los clientes vean la app antes de comprar. Se arma después del piloto (que ahora va al final) con base en cómo se usa la finca piloto, sin sus datos reales.
- Invitaciones (se construye en F14): verificar el correo con un código de 6 dígitos enviado al correo invitado antes de crear la finca. Requiere tener configurado el envío de correos (`MAIL_MAILER`) en el servidor. Hasta entonces, el enlace es la única prueba: se envía solo por chat privado, vence en 7 días y se puede anular.
