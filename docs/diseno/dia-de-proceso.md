# Día de proceso, Jornaleros y bodega: decisiones de diseño

Decisiones tomadas con la finca piloto para el día de proceso (jornada de pesca y procesamiento), Jornaleros, ventas a trabajadores, despachos a turbos y recepción en la bodega. **Todavía no está construido**: es el diseño acordado, y se valida con otros piscicultores antes de cerrarlo.

Cada tema separa:

- **Confirmado en la finca piloto:** cómo funciona hoy en la finca piloto.
- **Configurable por finca:** lo que cada finca ajusta. Los valores de la finca piloto son el punto de partida, nunca valores fijos del sistema.
- **Pendiente:** lo que falta confirmar.
- **Estado actual del sistema:** lo que ya existe en el código y lo que falta (cuando aplica).

> Las cifras marcadas *(ejemplo de la finca piloto)* son de esa finca. El sistema no las trae fijas: van en la configuración de cada finca.

Última actualización: 2026-10-14.

---

## 1. Principio multifinca y estrategia

**Principio.** La finca piloto es el primer ejemplo, no el molde: el diseño tiene que servir para otras fincas.

- **Fijo en el sistema**, solo lo que es igual en todas: lagos, pesajes, proceso, salidas, cuadre, pagos y registro.
- **Configurable por finca:**
  - tara de la canasta;
  - valor por kilo de los procesadores;
  - cupo y tarifas de los trabajadores;
  - hora límite y extra del pescador;
  - topes del cuadre y umbral del rendimiento;
  - presentaciones;
  - cargos del proceso (no todas tienen rayador o SESA);
  - formas de pago;
  - período de la nómina;
  - tipos de comprador;
  - destinos para el pescado sin despachar.
- **Funciones que se encienden o se apagan:**
  - hay fincas que no procesan (venden entero o vivo), que no tienen jornaleros o que no venden a particulares;
  - «permitir precio especial en una venta»;
  - «permitir ventas sin jornada»;
  - «no se alimenta el día de proceso».
- **Plantillas al crear una finca:** «con proceso propio», «vende entero» y «finca pequeña», con la configuración lista para ajustar.

**Estrategia.** Se construye cómo funciona la finca piloto, pero sin reglas ni números fijos en el código: todo va en la configuración, con los valores de la finca piloto como punto de partida. Las opciones de otras fincas se agregan cuando un cliente real las necesite, sin rehacer nada. **No se construyen opciones que nadie ha pedido.**

**Variaciones conocidas en Colombia.** Se tienen en cuenta para no cerrarles la puerta, no para construirlas ya:

- fincas que venden vivo, o entero sin procesar;
- pesca selectiva (raleo: solo los grandes);
- cultivo en jaulas flotantes (tilapia roja en represas) o en canales (trucha), no solo en estanques;
- venta directa en el estanque cualquier día.

**Pendiente:** comparar el diseño con la información de otras fincas antes de cerrarlo (ver «Validación con otras fincas»).

---

## 2. Jornada y varios lagos

**Confirmado en la finca piloto:**

- Un día de proceso es una jornada. Saca en promedio unas 4 toneladas *(ejemplo de la finca piloto)*. De ahí salen las ventas a particulares, lo que llevan los trabajadores y los pedidos de los turbos.
- Cuando un lago se termina de cosechar y falta pescado, se pesca otro. La planilla lleva solo los nombres de los lagos que se pescaron ese día, no los de todos los lagos de la finca.
- El pesaje entero se registra aparte por lago.
- Si no alcanza para completar los pedidos, se pesca un poco más el mismo día. La jornada admite pesajes enteros adicionales, del mismo lago o de otro.
- Los kilos procesados (pescado limpio) son un solo total para toda la jornada. Con ese total se paga a los procesadores.
- El limpio de cada lago, que se usa para su cosecha y su conversión, lo calcula el sistema al cerrar la jornada, en proporción al entero de cada lago. Nadie lo digita.
- La venta rápida se registra por especie y por jornada, sin preguntar el lago, porque la jornada ya sabe qué lagos se pescan. Si la jornada tiene un solo lago, todo va a ese lago.

**Configurable por finca:** ver el tema 1.

**Pendiente:** los números de un día real (ver la lista final).

---

## 3. Policultivo y siembras revueltas

**Confirmado en la finca piloto:**

- Un mismo lago puede tener varias especies juntas, incluso tres. En la finca piloto la principal es la cachama, luego la mojarra negra y por último el bocachico.
- En la cosecha, el pesaje entero se separa por especie dentro de cada lago. El limpio, el rendimiento, las ventas y los precios van por especie.
- La mortalidad y los muestreos van por especie.
- La ración se da al lago, no a cada especie, así que la conversión alimenticia real es por lago. Si se muestra por especie, es un estimado repartido según la biomasa de cada una, y la pantalla debe decir que es estimado.
- Pasa que se siembra la misma especie en un lago que todavía tiene peces de una siembra anterior, y quedan revueltos. Como la cosecha no es selectiva, el entero se reparte entre esas siembras según la biomasa de cada una. El administrador puede ajustar el reparto.

**Configurable por finca:**

- El catálogo de especies (Especies › Catálogo de la finca). El diseño no supone ninguna especie.
- Un lago puede tener una o varias especies.
- La regla de reparto entre siembras revueltas: según la biomasa (finca piloto), o primero la siembra más vieja, para fincas que sacan solo los grandes.

**Estado actual del sistema:**

- Ya existe:
  - varias especies activas en un lago, con advertencia si se mezcla agua fría y cálida;
  - la ración registrada al lago;
  - la ración sugerida calculada por especie;
  - la conversión que reparte el alimento según la biomasa y marca «estimado en policultivo» en las filas por siembra;
  - mortalidad, muestreo, cosecha y traslado por lote;
  - los lotes agrupados por especie en la pantalla del lago.
- Falta o no encaja:
  - Cada cosecha descuenta de una sola siembra; no reparte el entero de una especie entre varias siembras revueltas.
  - Cada venta está ligada a una cosecha (un lote de un lago). La venta por especie y por jornada tendrá que ligarse a la jornada y a la especie.
  - En Conversión, la tabla «Por especie» no marca la fila como estimada; solo lo explica la nota general al pie.
  - La especie de una siembra vieja puede no estar ligada al catálogo. El cupo y los precios por especie necesitan esa liga siempre.

---

## 4. Cosecha parcial y traslados

**Confirmado en la finca piloto:**

- La cosecha no es selectiva: se saca lo que se va a procesar, sin escoger.
- A veces el lago no se procesa al 100 %. El pescado restante se deja en el lago, el lago se vuelve a llenar y luego se le trasladan peces de otro lago.
- Al cerrar la jornada, el administrador indica por cada lago si «quedó vacío» o «quedó pescado».
  - **Quedó vacío:** se cierran todas sus siembras.
  - **Quedó pescado:** las siembras siguen abiertas. El sistema resta lo cosechado repartido según la biomasa de cada siembra y recalcula los peces y la biomasa que quedan. El peso promedio de cada siembra no cambia, porque la cosecha no es selectiva. El administrador puede poner un estimado de lo que quedó y ajustar el reparto.
- Al trasladar peces, el sistema debe preguntar «sumar al lote existente» o «crear lote aparte», porque lo que llega de otro lago puede ser de otro tamaño.

**Configurable por finca:** la regla de reparto (ver el tema 3).

**Estado actual del sistema:**

- La cosecha parcial y no selectiva de **una** siembra ya funciona: resta peces, recalcula la biomasa sin cambiar el peso promedio y cierra la siembra cuando llega a cero.
- La cosecha pide el número de peces. En la jornada habría que calcularlo como kilos ÷ peso promedio de la siembra.
- La cosecha tiene un solo destino. En la jornada, los destinos van por pedido o turbo.
- La cosecha se bloquea durante el retiro de un tratamiento. Se mantiene en la jornada.
- Cerrar a mano una siembra que todavía tiene peces no registra la diferencia entre lo estimado y lo cosechado, ni deja registro en la bitácora. Para «quedó vacío» hace falta registrarla.
- El traslado suma siempre al lote más viejo de la misma especie, con peso ponderado. No ofrece «crear lote aparte». Además sale de una sola siembra, así que con siembras revueltas en el origen tiene el mismo problema de reparto.

---

## 5. Ayuno y día de proceso sin alimentación

**Confirmado en la finca piloto:**

- **Día de proceso sin alimentación:** ese día no se alimenta en la finca, porque los trabajadores fijos ayudan en el proceso. En un día con jornada no debe haber alertas por raciones faltantes ni por turnos sin registro de alimentación. La asistencia de los fijos aparece en la jornada.
- **Ayuno antes de la cosecha:** al lago que se va a pescar no se le da alimento el día anterior ni el día de la pesca *(ejemplo de la finca piloto)*.
  - Al programar la jornada se marcan los lagos que se van a pescar, y desde el día anterior quedan «en ayuno».
  - En Registro rápido aparecen marcados. Si alguien intenta registrarles ración, el sistema avisa y dice por qué, pero no bloquea.
  - No hay alertas de ración faltante para esos lagos.
  - Si la pesca se cancela, el ayuno se quita.
- Son dos cosas distintas: el día sin alimentación es de toda la finca y es el día del proceso; el ayuno es solo del lago que se pesca y empieza un día antes.

**Configurable por finca:** la opción «no se alimenta el día de proceso» y los días de ayuno antes de la cosecha.

**Estado actual del sistema:**

- No existe ninguna alerta de ración faltante ni de turno sin alimentación: no hay nada que apagar.
- No existe el ayuno ni el lago programado para pesca. Lo más cercano es el aviso «listo para pescar», que la cosecha atiende.
- Inicio muestra la ración sugerida del día por lago. Un lago en ayuno debe decir «en ayuno» en vez de sugerir ración.
- El aviso de stock bajo usa el consumo promedio; los días de ayuno lo bajan un poco (efecto menor).

---

## 6. Presentaciones y pesaje

**Confirmado en la finca piloto:**

- Al empacar, cada presentación se pesa aparte. Una presentación es la combinación de especie + tamaño + proceso, por ejemplo «cachama grande descamada».
- Tamaños: todas las especies tienen grande, mediana y pequeña. La pequeña de la cachama se llama «cachamín».
- Procesos: lavado con escamas, descamado y embolsado.
- El limpio total de la jornada es la suma de las pesadas por presentación. Nadie lo digita.
- Los pedidos y las remisiones usan las mismas presentaciones.
- Tara: cada canasta pesa 2,5 kg *(ejemplo de la finca piloto)* y se descuenta al pesar.

**Configurable por finca:**

- Cada finca define los tamaños de cada especie, con el nombre que use y un rango de peso opcional.
- Cada finca define sus procesos.
- La presentación se arma con especie + tamaño + proceso, sin escribir cada combinación a mano.
- El catálogo de presentaciones puede llevar especie, tamaño, tipo de proceso y color de cabuya.
- La tara de la canasta (y la de la canastilla).

**Estado actual del sistema:** la tara de la canasta y la de la canastilla ya se configuran por finca (Ajustes de pesaje), y el pesaje por pesadas ya las descuenta. El sistema trae 2,00 kg por defecto para la canasta, así que la finca piloto tiene que poner sus 2,5 kg. Hoy la cosecha guarda solo el pescado entero; no hay limpio, rendimiento ni presentaciones. **Ojo con el nombre:** en el pesaje actual de la cosecha, «limpio» quiere decir peso neto sin canastas, o sea pescado entero, no procesado. En el diseño hay que cambiarle el nombre a uno de los dos para no confundirlos.

---

## 7. Pedidos, despachos y remisiones

**Confirmado en la finca piloto:**

- Los pedidos de los turbos se conocen antes de pescar. Ejemplo: un turbo de 3.500 kg y otro de 500 kg *(ejemplo de la finca piloto)*.
- Cada pedido tiene líneas por presentación. El sistema suma lo pedido y avisa si falta pescado.
- A veces el turbo lleva menos de lo pedido porque no le cabe más. El pedido guarda por separado los kilos pedidos y los despachados, y la remisión sale con lo despachado de verdad.
- Lo que sobra se carga en otro pedido de otro turbo, hacia otra ciudad, que se puede crear durante la jornada.
- El cuadre de la jornada usa los kilos despachados, no los pedidos.
- Si al cerrar la jornada queda pescado sin destino, el sistema lo muestra y exige decir qué pasó con él (otro pedido, una venta u otro destino). No puede quedar sin explicar.
- **El despacho lleva:**
  - las líneas por presentación, con kilos pedidos y despachados;
  - el destino;
  - quién recibió y quién despachó;
  - la hora;
  - el consecutivo de la remisión;
  - el número del salvoconducto de la AUNAP con su archivo: solo el número y el archivo, nunca conexión ni credenciales de la AUNAP;
  - la placa y el conductor, que son opcionales pero se ven en el formulario.
- Puede haber varios turbos por jornada. El despacho reutiliza el pesaje por pesadas que ya existe.
- La remisión de cada turbo sale con el detalle por presentación.

**Configurable por finca:** los destinos para el pescado sin despachar.

---

## 8. Ventas, precios, cupo y formas de pago

**Confirmado en la finca piloto:**

- Todas las ventas entran por la venta rápida. La pantalla Ventas queda solo para consulta, abonos y cartera.
- No se vende pescado en días sin proceso.
- El precio va por especie, no por presentación:
  - Cachama y mojarra negra: $7.000 por kilo al trabajador dentro de su cupo, y $9.000 por kilo a particulares y en el exceso del cupo *(ejemplo de la finca piloto)*.
  - Bocachico: $20.000 por kilo para todos *(ejemplo de la finca piloto)*. No entra en el cupo ni tiene precio de trabajador.
- **Cupo:** cada persona tiene un cupo semanal de 10 kg *(ejemplo de la finca piloto)*. Es igual para fijos y jornaleros.
- **Tipos de comprador:** además de los particulares, compran con precio de trabajador los fijos, los procesadores (lavador, eviscerador, SESA y empacador), el pescador y el rayador. Los rayadores suelen llevar más, así que a ellos se les aplica el exceso con frecuencia.
- No hay precios especiales. En la venta no se puede cambiar el precio.
- **Forma de pago:** en cada venta se elige «descontar del pago» o «contado».
  - El sistema recuerda la preferencia de cada persona y la propone por defecto.
  - Al jornalero se le descuenta de su pago de la jornada; al fijo, de su nómina.
  - Si es de contado, se anota quién recibió el dinero, para cuadrar la caja del día.
  - Si el descuento es mayor que lo ganado en la jornada, el sistema avisa y deja el saldo pendiente para la siguiente. Nunca hay un pago negativo.

**Configurable por finca:**

- Los precios de venta, por especie y, si la finca lo necesita, por presentación.
- Por cada especie: si entra en el cupo del trabajador, y su precio por tipo de comprador.
- El cupo:
  - es opcional: una finca puede no tener cupo y vender a todos al mismo precio;
  - sus kilos y sus tarifas;
  - su período: semanal, quincenal o mensual.
- La lista de tipos de comprador.
- La opción «permitir precio especial en una venta», apagada por defecto. Si una finca la enciende, cada cambio pide el motivo, solo lo hace quien la finca autorice y queda en la bitácora.
- La opción «permitir ventas sin jornada», apagada en la finca piloto.
- **Reglas para todas las fincas:**
  - cada venta guarda el precio con el que se hizo, y un cambio de precio solo aplica a las ventas nuevas;
  - solo el propietario cambia los precios, y la bitácora registra quién y cuándo.

**Estado actual del sistema:** cada venta ya guarda su precio. La liquidación de la nómina ya tiene un campo para el descuento por pescado.

---

## 9. Obsequios

**Confirmado en la finca piloto:**

- A veces se regala pescado. Lo decide el propietario y le da la orden al administrador, que lo entrega y lo registra.
- El registro lleva especie, presentación, kilos, a quién se le dio, quién lo registró y quién lo autorizó.
- En la finca piloto autoriza siempre el propietario. El administrador no puede autorizarse a sí mismo.
- El propietario recibe el aviso y lo ve en la bitácora.
- En el cuadre cuenta como salida sin dinero, nunca como faltante.

**Configurable por finca:** quién puede autorizar obsequios: solo el propietario, o también el administrador.

---

## 10. Procesadores, pescador, rayador y fijos (horas extra)

**Confirmado en la finca piloto:**

- **Procesadores** (lavador, eviscerador, SESA y empacador): cobran $270 por kilo limpio total de la jornada *(ejemplo de la finca piloto)*, repartido en partes iguales.
- **Rayador:** cobra igual, con una bonificación opcional.
- **Fijos:** el día de proceso es para ellos un día normal de trabajo; no cobran por kilo.
  - La asistencia de la jornada registra la hora de salida de cada fijo.
  - Si se pasa de su horario, el sistema calcula las horas extra. Quedan propuestas hasta que alguien las aprueba; solo entonces entran a la nómina.
  - En la finca piloto las aprueba el **administrador**.
  - El propietario las ve al revisar la nómina (tema 11).
  - Nadie aprueba sus propias horas extra: las del administrador las aprueba el propietario.
  - En la bitácora queda quién las propuso y quién las aprobó.
- Las horas extra en Colombia llevan recargos distintos: diurna, nocturna, dominical y festiva. La reforma laboral reciente cambió la hora de inicio de la jornada nocturna y la jornada semanal.

**Configurable por finca:**

- El valor por kilo de los procesadores.
- Los cargos del proceso: no todas las fincas tienen rayador o SESA.
- La hora límite y el valor de la hora extra del pescador.
- El período de la nómina.
- Los porcentajes de recargo, la hora de inicio de la jornada nocturna y la jornada semanal. Nunca van fijos en el código.
- Quién aprueba las horas extra: el administrador, el propietario o cualquiera de los dos.

**Pendiente:**

- Los valores de los recargos, la hora nocturna y la jornada semanal, que se confirman con un contador.
- **Antes de construirlo hay que cambiar una regla del proyecto** (ver el tema 11).

**Estado actual del sistema:**

- Hoy cada persona pide sus horas extra a mano, y se aprueban o se rechazan.
- La nómina es semanal fija: horas de turno por la tarifa normal, más horas extra aprobadas por la tarifa extra. Las dos tarifas son por persona.
- No hay recargos, ni calendario de festivos, ni hora de inicio de la jornada nocturna, ni jornada semanal, ni cálculo de horas extra desde la hora real de salida.

---

## 11. Nómina

**Confirmado en la finca piloto:**

- El administrador hace la nómina, y el propietario la ve y la revisa.
- El propietario da el visto bueno antes de cerrarla y pagarla. Si no está de acuerdo, la devuelve con un comentario.
- Las horas extra las aprueba el administrador (tema 10).
- La nómina de los fijos es mensual.

**Reglas fijas, iguales en todas las fincas:**

- Nadie aprueba sus propias horas extra, sus propios permisos ni su propio pago. La regla es sobre el pago de cada uno, no sobre la nómina de los demás.
- El administrador no puede cambiar su propio sueldo.
- Si el sueldo del administrador va en la nómina, su fila la aprueba siempre el propietario.
- No se puede aprobar una nómina con horas extra si los recargos no están configurados. El sistema muestra el aviso para configurarlos.
- El cupo de pescado sigue siendo semanal, aunque la nómina sea mensual. En la planilla mensual, el descuento de pescado se muestra semana por semana (kilos dentro del cupo y del exceso, con su precio) y luego el total del mes.
- Los sueldos base y las tarifas los cambia solo el propietario.
- Todo queda en la bitácora: quién preparó, quién aprobó y qué cambió.

**Impresión y pago:**

- Al aprobarse la nómina, se imprime en PDF la **planilla de pago** del período: una fila por trabajador con días, horas extra, descuentos de pescado, anticipos, total pagado y espacio para la firma.
- También se imprime un **comprobante individual** por trabajador, con el detalle y espacio para las dos firmas.
- Sin aprobar, solo se imprime con la marca «BORRADOR».
- Después de pagar, se sube la foto o el escaneo de la planilla firmada, y la nómina se marca como pagada, con la fecha y quién pagó.
- La planilla del proceso de los jornaleros también se imprime, para firmar al recibir el pago.

**Configurable por finca:**

- Quién prepara la nómina: el administrador o el propietario.
- Si hace falta el visto bueno del propietario antes de cerrarla. Solo aplica cuando la prepara el administrador; si la prepara el propietario, no necesita visto bueno. Por eso una finca pequeña, donde una sola persona es propietario y administrador, puede preparar y cerrar la nómina de los demás.
- Quién aprueba las horas extra: el administrador, el propietario o cualquiera de los dos.
- El período: semanal, quincenal o mensual.
- Quién aprueba los permisos (días libres): el administrador, el propietario o cualquiera de los dos, con la misma regla de no aprobarse a sí mismo.

**Cómo se construye (decidido):**

- Los cambios en la base de datos se hacen con columnas nuevas, sin borrar ni rehacer la tabla de liquidaciones. Las semanas ya liquidadas se siguen leyendo igual.
- Las planillas firmadas se guardan privadas y separadas por finca.

**Para más adelante:** exportar los datos para que el contador reporte la nómina electrónica de la DIAN, si a la finca le aplica.

**Pendiente:**

- La estructura de la nómina mensual de la finca piloto. Se recibe en foto, sin nombres ni valores.
- En la finca piloto, quién aprueba los permisos: ¿el administrador o el propietario?
- En la finca piloto, ¿el sueldo del administrador va en la nómina?
- **Antes de construirlo hay que cambiar las reglas del proyecto.** Hoy AGENTS.md dice que la nómina, las solicitudes y aprobar permisos y horas extra son exclusivos del propietario, y el sistema funciona así. Con la aprobación del dueño del proyecto, AGENTS.md tiene que pasar a decir quién prepara la nómina y quién aprueba las horas extra según la configuración de cada finca, con las reglas fijas de arriba. Se hace en una fase aparte, después de Jornaleros.

**Estado actual del sistema:**

- La nómina, las tarifas y las solicitudes (permisos y horas extra) son solo del propietario.
- La nómina es semanal fija, por persona y por semana. No hay estado de borrador, devolución con comentario ni visto bueno.
- Solo entran en la nómina el trabajador y el celador. El administrador no tiene nómina en el sistema.
- La liquidación guarda horas, tarifas, bruto, descuento de pescado y total. No guarda días ni anticipos.
- «Pagada» guarda solo la fecha: ni quién pagó ni la planilla firmada.
- La nómina y las solicitudes no dejan registro en la bitácora.
- No hay PDF de la nómina. Sí hay un generador de PDF para los reportes, que se puede reutilizar.

---

## 12. Rendimiento y cuadre

Son dos comparaciones distintas.

**a) Rendimiento: de entero a limpio.**

- Varía de forma natural (vísceras, hembras con huevas), así que **no** lleva tolerancia de cuadre.
- Se registra el rendimiento del día y se compara con el promedio de la finca por especie. El rendimiento de referencia va por especie y no por presentación, porque el entero se pesa por especie y no por tamaño.
- Si sale muy por debajo de lo normal, el sistema avisa y se puede anotar el motivo, por ejemplo «lote en reproducción».

**b) Cuadre: de limpio a salidas** (turbos + particulares + trabajadores + obsequios). Usa los kilos despachados, no los pedidos. Es asimétrico:

- Si lo que sale es igual o mayor que el limpio (pasa a veces por la balanza, el agua o la ñapa), se acepta y se registra el sobrante. Si supera un tope, el sistema avisa.
- Si lo que sale es menor que el limpio (faltante), la jornada no se puede cerrar sin explicar la diferencia: el motivo es obligatorio y queda quién lo explica. Queda en la bitácora y el propietario lo ve.

**Configurable por finca:** el umbral de aviso del rendimiento y el tope del sobrante.

**Pendiente:** la tolerancia exacta, que se fija con los números de un día real.

---

## 13. Bodega: recepción de concentrado

**Confirmado en la finca piloto (cómo es hoy):** cuando llega el concentrado, los trabajadores bajan y cuentan los bultos. El administrador a veces está mirando y a veces no. Los trabajadores le dicen cuántos llegaron, y el administrador se lo comenta al propietario. Todo es de palabra.

**El punto débil:** no queda escrito quién contó ni hay forma de comprobar la cuenta.

**Idea para el diseño (sin construir):**

- El propietario registra lo comprado, con el despacho de alimento que ya existe.
- El trabajador que baja los bultos registra cuántos llegaron desde Registro rápido, también sin conexión. Queda quién contó y a qué hora, con foto opcional de la remisión o la factura.
- El sistema compara lo comprado con lo recibido.
  - Si cuadra, el administrador confirma y entra al stock.
  - Si no cuadra, avisa directo al propietario.
- Si el administrador no está, la recepción queda pendiente de confirmar, pero el propietario ya la ve.

**Configurable por finca:** quién puede registrar la recepción: el trabajador, el administrador o ambos.

**Pendiente:** las preguntas de la bodega (ver la lista final).

**Estado actual del sistema:**

- Ya existe:
  - el propietario registra el despacho (proveedor, tipo de concentrado, bultos, peso por bulto y lote);
  - el administrador registra una o varias recepciones, que entran directo al stock;
  - alertas para el propietario y el administrador si un despacho pasa varios días sin recibirse, o si lo recibido no coincide con lo despachado, hasta que el propietario lo marca como revisado.
- Falta:
  - el conteo del trabajador, desde Registro rápido y sin conexión;
  - quién contó y a qué hora;
  - la foto de la remisión o la factura;
  - el estado «pendiente de confirmar» antes de entrar al stock;
  - la opción por finca de quién registra la recepción.

---

## 14. «Lago» como unidad de cultivo

**Para no cerrarles la puerta** a las jaulas flotantes y a los canales. No se construye hasta que una finca real lo pida.

**Estado actual del sistema:**

- El lago tiene solo nombre y tipo. Ese «tipo» es el **material** (tierra, geomembrana o concreto), no la clase de unidad. No hay área, volumen ni densidad guardados.
- El concepto aparece en unas 15 tablas, 41 archivos de la aplicación, 23 pantallas, unos 145 textos y la cola sin conexión de los teléfonos.

**Cómo generalizarlo sin romper nada:**

1. Agregar un campo nuevo para la clase de unidad (estanque, jaula o canal; estanque por defecto), sin tocar el de material. El material solo aplica a los estanques.
2. Cambiar solo el texto que se ve en pantalla («unidad» o el nombre que use la finca). En el código sigue llamándose `lago`.

No hay que renombrar el lago en el código: rompería los registros guardados sin conexión en los teléfonos y obligaría a tocar casi todo.

**A revisar cuando una finca lo pida:**

- En jaulas dentro de una represa, el agua (oxígeno, temperatura) es del cuerpo de agua compartido, no de cada jaula. Las mediciones y los aireadores irían a un grupo.
- En canales de trucha importa el caudal.
- El ayuno y el «quedó vacío / quedó pescado» aplican igual.

---

## Validación con otras fincas

Antes de cerrar el diseño se compara con fincas distintas de la piloto. En este repositorio nunca van nombres de personas ni de empresas, solo la región.

**En camino:**

- Información de tres fincas de otras regiones: una del Huila, una del Putumayo y una de los Llanos. Llega en una semana, más o menos.
- Conocimiento general, de hace unos años, de una empresa grande con jaulas en el Huila. Servirá para el caso de las jaulas (tema 14).

**Cuando llegue**, se compara con este diseño y se anota, por cada diferencia:

- qué ya cubre el diseño;
- qué debe ser configurable por finca;
- qué sería una función nueva. Se construye solo si un cliente real la necesita (tema 1).

---

## Pendiente

**Números de un día real de proceso** (los trae el usuario después de ir a la finca):

- el entero por lago y por especie;
- el limpio por presentación;
- cuánto fue a cada destino (turbos, particulares, trabajadores, obsequios);
- si sobró o faltó.

Con ellos se fijan la tolerancia del cuadre y los topes.

**Preguntas de la bodega:**

- si llega remisión o factura con el concentrado;
- cuántos tipos de concentrado hay;
- cada cuánto llega y cuántos bultos;
- si se han perdido o dañado bultos;
- quién saca los bultos para alimentar y si se anota.

**Preguntas para el trabajador y el celador:**

- ¿Cómo es el día normal de un trabajador y el de un celador, de la entrada a la salida?
- ¿Se mide el oxígeno? ¿Quién, cada cuánto y con qué?
- ¿Hay portería? ¿Quién controla quién entra y sale?
- ¿Qué se olvida entre turnos?

**Qué días se pesca:** si hay días fijos de proceso en la semana o se decide según los pedidos.

**Otras decisiones abiertas:**

- los valores de los recargos, la hora nocturna y la jornada semanal, que se confirman con un contador (tema 10);
- cambiar en AGENTS.md las reglas de la nómina y de las horas extra antes de construirlo (tema 11);
- la estructura de la nómina mensual de la finca piloto, en foto y sin nombres ni valores (tema 11);
- en la finca piloto, quién aprueba los permisos y si el sueldo del administrador va en la nómina (tema 11);
- la comparación con las fincas del Huila, el Putumayo y los Llanos (ver «Validación con otras fincas»).
