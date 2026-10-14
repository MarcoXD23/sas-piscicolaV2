# El SAS Piscícola

SaaS multi-tenant para la gestión de fincas piscícolas: lagos, siembras y policultivo, alimentación y bodega, calidad del agua y sanidad, turnos y nómina, seguridad nocturna, cosecha y ventas, reportes y un asistente con IA.

- **Stack:** Laravel 13 · PostgreSQL · Blade + Tailwind 4 (compilado con Vite, sin CDN) · Alpine.js · Sanctum (API) · SDK oficial de Anthropic (asistente).
- **Región por finca:** país, zona horaria e idioma se configuran en **Ajustes** (ver [Regionalización](#regionalización)). **Idioma:** español; todos los textos de la interfaz están en `lang/es`.
- **Pensado primero para celular**, porque el personal la usa en la finca. Se instala como app (PWA) y muestra un aviso cuando no hay conexión.
- **Marca:** trucha arcoíris con corona. Los archivos de la marca están en `docs/marca/`; la app usa solo las copias livianas de `public/` (`favicon.ico` y `public/icons/`, cada una de menos de 200 KB). `icono-original.png` nunca se carga. El favicon se derivó del arte para que se lea a 16 px (ver `docs/marca/LEEME.md`).

## Roles

Solo existen cuatro roles. Una persona puede tener varios (por ejemplo trabajador y celador, por la rotación de turnos); se guardan en la tabla pivote `rol_user`.

| Rol | Qué puede hacer |
|---|---|
| `propietario` | Dueño. Exclusivo: personal (roles, turnos, solicitudes, nómina), agenda, ajustes, bitácora y **despachos** de concentrado. Ve en solo lectura lagos, ventas, bodega, conversión, alertas, reportes y los reportes del celador. Puede activarse también el rol administrador (finca pequeña) en **Personal → Tu cuenta**. |
| `administrador` | Opera la finca: lagos, siembras, cosechas, traslados, muestreos, mortalidad, alimentación, agua, sanidad, tareas, **recepción** de concentrado y ventas (créditos y abonos). Ve reportes y los reportes del celador. |
| `trabajador` | Alimentación y mortalidad **solo dentro de su turno**; sus tareas, permisos y horas extra. |
| `celador` | Seguridad & Noche (rondas, oxígeno, aireadores) **solo dentro de su turno**; sus tareas, permisos y horas extra. |

## Módulos

| Módulo | Dónde | Qué hace |
|---|---|---|
| Inicio | `/` | Un panel por rol: ejecutivo (gráficas, ventas, cartera), técnico, ración del día (trabajador) y alertas de agua (celador). |
| Lagos | `/admin/lagos` | Siembras y **policultivo** (varias especies; la misma especie otra vez como lote de fecha distinta; mezclar agua fría y cálida es una advertencia que se confirma; las alertas de agua usan el **rango común** de las especies del lago), alimentación con ración sugerida, mortalidad, muestreos (con el peso promedio en vivo y su diferencia con el anterior), traslados y desdobles, cosecha (ver **Cosechas**) y el aviso **«listo para pescar»** del trabajador en su turno (uno pendiente por lago; lo atiende el administrador o la cosecha). Detalle con historial completo. |
| Calidad del agua y sanidad | detalle del lago | Mediciones con alertas según las especies del lago; tratamientos con **retiro sanitario** que bloquea la cosecha. |
| Especies | `/especies` y `/admin/especies` | **Una sola entrada del menú con dos pestañas**: «Guía» (todos los roles) y «Catálogo de la finca» (el propietario consulta, el administrador edita; el trabajador y el celador solo ven la guía). **Catálogo por finca**: cada finca arranca con 6 especies por defecto (editables y desactivables) y el administrador agrega las suyas: clima, rangos de temperatura, oxígeno y pH y tipo de alimentación (todos opcionales; sin rangos no se inventan valores, sin alimentación la ración se registra a mano). Una especie con siembras no se borra, se desactiva. Cada especie tiene una **ficha de cultivo** (preparación del estanque, siembra, alimentación, calidad del agua, cosecha, densidades y tipo de estanque), una **ficha de procesamiento** (ayuno, sacrificio, eviscerado, fileteado, frío y empaque), color y aspecto, editables por el administrador; las 6 por defecto traen contenido **orientativo** y las propias arrancan vacías. La guía la ven todos los roles y el asistente la usa: lo que falta va como «sin dato». **Fotos reales** (hasta 5 por especie, las sube el administrador): ver **Fotos de especies**. |
| Bodega | `/admin/bodega` | Dos pasos: el propietario registra el **despacho** y el administrador la **recepción** (contra el despacho o sin despacho). Diferencia por despacho (faltante, sobrante, exacto), aviso de despachos sin recibir después de N días (Ajustes), stock = recibido − raciones (nunca negativo), días de cobertura y alertas de stock. |
| Cosechas | `/admin/cosechas/crear` | Pesaje como en el cuaderno: cada **pesada** con su tipo (canasta o canastilla), cantidad y peso pesado. **Descuento = cantidad × peso de una vacía** (se multiplica) y **peso limpio = peso pesado − descuento**; totales y meta opcional en vivo. El servidor rehace todas las cuentas y guarda el total limpio. Encabezado con lago y especie, destino del pedido (con sugerencias), fecha (editable para cosechas atrasadas), hora automática y quién despachó. También se puede registrar solo el total limpio. Los pesos por defecto de una canasta (2 kg) y una canastilla (1,5 kg) vacías los cambia el administrador en **Pesaje**. Detalle de cada cosecha, historial del lago, informe mensual y libro de campo (pantalla, Excel y PDF) muestran pesadas y totales. |
| Ventas | `/admin/ventas` | Ventas de pescado cosechado (de contado o a crédito, también a empleados) y abonos. |
| Jornaleros | `/admin/jornaleros` | Trabajadores ocasionales **sin cuenta en la app** que vienen a pescar o procesar y se pagan por jornada (no son personal ni tienen rol). Propietario y administrador los agregan con nombres, apellidos, documento y teléfono opcionales y su categoría habitual (procesador, pescador, rayador, lavado de canastas u otro). Pueden comprar pescado a crédito en Ventas. Ver **Jornaleros y personas sin cuenta**. |
| Personal | `/admin/personal` | Aprobación y roles, **turnos rotativos**, solicitudes (permisos y horas extra) y **nómina semanal**. |
| Tareas | `/admin/tareas` | Tareas para el personal de campo; se marcan hechas solo en turno. |
| Mi trabajo | `/mi-trabajo` | Para trabajador y celador: tareas, permisos, horas extra, saldo de pescado y pagos de nómina. |
| Seguridad & Noche | `/noche` | El celador, en su turno: rondas con o sin novedad, aireadores y oxígeno nocturno. Propietario y administrador lo consultan en solo lectura, con los reportes de cada turno. |
| Agenda | `/admin/agenda` | Calendario de la finca con recordatorios; muestra retiros sanitarios y permisos aprobados. |
| Reportes | `/admin/reportes` | Informe mensual y libro de campo por lago (imprimibles o PDF desde el navegador). |
| Asistente IA | botón flotante | Preguntas sobre la finca con Claude o Gemini. Responde solo con las fichas de especies y los datos que el rol puede ver, sin conocimiento general. Si el dato no está, lo dice ("No tengo ese dato…"). Si existe pero el rol no lo ve, responde "No tengo esa información para tu rol". |

Todo movimiento de stock, peces, biomasa o dinero se hace en una transacción y queda como registro histórico (nunca se sobrescribe solo un saldo).

## Multi-tenant

Cada finca es un `tenant`. Toda tabla de negocio lleva `tenant_id` y se filtra con un único global scope ([`TenantScope`](app/Models/Scopes/TenantScope.php), aplicado con el trait [`PerteneceATenant`](app/Models/Concerns/PerteneceATenant.php)). Los middlewares `tenant`, `rol` y `turno` fijan la finca activa, restringen por rol y exigen turno en curso. Una prueba (`CierreTest`) verifica que toda ruta privada exija sesión, finca y, en los módulos restringidos, rol.

El filtro **falla cerrado**: sin finca activa, consultar o crear un modelo de finca lanza `SinFincaActiva` en vez de devolver datos de todas.

- **Para ver varias fincas a propósito:** se usa `TenantActual::sinFiltro(fn () => ...)`, como hace el panel de plataforma.
- **Identidad:** el login, la sesión, la recuperación de contraseña y los tokens de la API buscan a la persona sin filtro (`ProveedorUsuarios` y `TokenAcceso`).
- **Pruebas:** `AislamientoFincasTest` recorre todos los modelos de finca y todas las rutas con ids. `FiltroFincaCerradoTest` prueba el cierre.

## Regionalización

Nada está atado a un país. Cada finca define en **Ajustes** (solo el propietario):

- **País:** fija la moneda y el formato de números y dinero (con `intl`; por ejemplo `$ 118.750` en Colombia y `$118,750` en México). Los montos se guardan como enteros, sin centavos.
- **Zona horaria** (por defecto `America/Bogota`): las fechas con hora se guardan en **UTC** y se muestran e interpretan en la zona de la finca, también al validar turnos y al decidir qué es "hoy". Las fechas de solo día (siembras, muestreos, ventas) son días del calendario de la finca y no se convierten.
- **Idioma** (por defecto `es`): hoy solo hay traducción completa al español, en `lang/es` (vistas, mensajes, ficha de especies y textos del JavaScript, publicados como `window.textos`). Una prueba (`TextosEnLangTest`) falla si una vista tiene textos fijos o usa una clave que no existe.

La lógica de zona, "hoy" y formatos está en [`App\Support\Region`](app/Support/Region.php); el cast [`InstanteLocal`](app/Casts/InstanteLocal.php) y la conexión [`ConexionPostgresUtc`](app/Database/ConexionPostgresUtc.php) garantizan que todo instante llegue a la base en UTC.

**Instalaciones existentes:** la migración `convert_timestamps_to_utc` pasa a UTC las horas ya guardadas (que estaban en hora de Bogotá). Antes de correr `php artisan migrate` en producción, saca una copia con `php artisan piscicola:respaldar`, que guarda fuera del proyecto (ver [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md)).

## Requisitos

- PHP 8.4 o superior, con la extensión `intl` (formatos de número y moneda por país)
- Composer
- Node.js y npm
- PostgreSQL

## Instalación

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Edita `.env`:

| Variable | Para qué |
|---|---|
| `DB_*` | Tu PostgreSQL. La base por defecto es `sas_piscicola_v2`. |
| `ASISTENTE_PROVEEDOR` | Proveedor del asistente IA: `claude` o `gemini`. Vacío = `claude`. |
| `ANTHROPIC_API_KEY` | Clave de Claude, en [console.anthropic.com](https://console.anthropic.com). |
| `ANTHROPIC_MODEL` | Opcional. Por defecto `claude-opus-5-5`. |
| `GEMINI_API_KEY` | Clave de Gemini, en [aistudio.google.com](https://aistudio.google.com). |
| `GEMINI_MODEL` | Opcional. Por defecto `gemini-3.5-flash-lite` (el anterior, `gemini-3.8-flash`, se saturaba con errores 503). |
| `GEMINI_MODEL_RESPALDO` | Opcional. Modelo que se prueba una vez si el principal sigue respondiendo 500 o 503 después de 2 reintentos (1 s y 3 s). Vacío = sin respaldo. Sugerido: `gemini-flash-lite-latest`. |
| `MAIL_*` | Correo saliente (recuperar contraseña). Vacío = los correos se escriben en el archivo del día de `storage/logs/`. |

### Asistente IA: elegir proveedor

El asistente funciona con **Claude** (Anthropic) o con **Gemini** (Google). Las dos opciones usan las mismas instrucciones y los mismos datos de la finca, filtrados por rol, y se llaman siempre desde el servidor: la clave vive solo en el `.env` y nunca llega al navegador.

1. Pon `ASISTENTE_PROVEEDOR=claude` o `ASISTENTE_PROVEEDOR=gemini`.
2. Pon la clave del proveedor elegido (`ANTHROPIC_API_KEY` o `GEMINI_API_KEY`). Si falta, el asistente aparece como "no configurado".
3. Si cambiaste el `.env` con la configuración en caché, ejecuta `php artisan config:clear`.

Gemini tiene un nivel gratuito: la clave se saca en [aistudio.google.com](https://aistudio.google.com).

> **Advertencia sobre el nivel gratuito de Gemini:** según las condiciones de Google, en el nivel gratuito lo que envías **puede usarse para mejorar sus productos y modelos**. Cada pregunta al asistente envía datos de la finca (lagos, producción y, para el propietario, bodega y ventas). **No uses el nivel gratuito si manejas datos de clientes o información que no deba compartirse**; para eso usa el nivel de pago de Gemini o Claude.

Luego:

```bash
php artisan migrate
npm run build      # o `npm run dev` durante el desarrollo
```

### Correo y recuperar contraseña

En **Ingresar → ¿Olvidaste tu contraseña?** la persona escribe su correo y recibe un mensaje con el botón **Restablecer contraseña**. El enlace sirve una sola vez y vence a los 30 minutos (`config/auth.php`). La pantalla responde lo mismo exista o no el correo, y hay un límite de 5 intentos por minuto para pedir enlaces y otros 5 para restablecer. Al cambiar la contraseña se cierran las sesiones recordadas y los accesos de la API.

Para enviar con Gmail, crea una **contraseña de aplicación** (cuenta de Google → Seguridad → Verificación en 2 pasos → Contraseñas de aplicaciones) y ponla en `.env`:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tucorreo@gmail.com
MAIL_PASSWORD=la-clave-de-aplicacion-de-16-letras
MAIL_FROM_ADDRESS=tucorreo@gmail.com
MAIL_FROM_NAME="El SAS Piscícola"
```

Luego `php artisan config:clear`. Mientras `MAIL_MAILER` esté vacío, el correo (con el enlace) queda en el archivo del día de `storage/logs/` (`laravel-AAAA-MM-DD.log`).

### Primer propietario

No hay usuarios ni contraseñas fijas. Crea la finca y su propietario con un comando que pide el país, la zona horaria y la contraseña:

```bash
php artisan piscicola:crear-propietario
```

El personal se registra en `/registro` con el código de la finca, y el propietario lo aprueba en **Personal**.

### Panel de plataforma (dueño del software)

En `/plataforma` el dueño del software ve todas las fincas (usuarios, estado y fecha de creación) y puede **suspender** o **reactivar** cada una. No es un rol de finca: usa una tabla (`administradores_plataforma`) y un ingreso (`guard` `plataforma`) aparte, sin `tenant_id`. Ningún usuario de finca puede entrar al panel y el acceso de plataforma no entra a los módulos de las fincas.

```bash
php artisan piscicola:crear-admin-plataforma
```

El comando pide nombre, correo y contraseña (mínimo 12 caracteres). Al suspender una finca su personal no puede ingresar (web ni API) y ve un mensaje claro; las sesiones abiertas se cierran en el siguiente clic y los tokens de la API se revocan. Los datos de la finca no se borran.

**Verificación en dos pasos obligatoria.** Al entrar por primera vez, el panel exige activarla: se escanea un QR con la app del teléfono y se reciben 10 códigos de recuperación, que se muestran una sola vez. Desde entonces, la contraseña sola no abre sesión: se pide también el código de la app o uno de recuperación. El intento pendiente vence a los 5 minutos y hay un límite de intentos.
No se puede desactivar desde el panel. Si se pierden el teléfono y los códigos, se quita en el servidor y al volver a entrar se activa de nuevo:

```bash
php artisan piscicola:plataforma-quitar-dos-pasos correo@ejemplo.com
```

### Alertas

Cada finca recibe alertas de **stock de alimento bajo o agotado**, **mortalidad anormal en un lago** y **agua fuera de rango**. Para el agua se usan los mismos rangos de las especies que ya usan el inicio, Seguridad & Noche y el asistente IA (`MedicionAgua::problemas()`). Los umbrales de stock (días mínimos) y de mortalidad (porcentaje y período) se editan en **Ajustes**.

- Se guardan como historial (tabla `alertas`, página **Alertas**). Una alerta abierta no se repite: un índice único parcial de PostgreSQL lo garantiza. Se cierra sola cuando el problema se resuelve.
- El propietario las ve todas en su inicio; el administrador ve las de mortalidad y agua (la bodega no es suya).
- Las **críticas** (stock agotado, oxígeno bajo, mortalidad del doble del umbral) se envían **una vez** por correo a los propietarios.
- Se revisan al guardar movimientos de bodega, mortalidad y mediciones de agua, y cada hora con `php artisan piscicola:evaluar-alertas`. Para lo segundo, el servidor necesita el cron de Laravel:

```
* * * * * cd /ruta/del/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

**WhatsApp:** existe la interfaz `CanalNotificacion` (con `CanalCorreo` y `CanalNulo`) y las variables `WHATSAPP_*` vacías, pero todavía no hay proveedor. El recomendado es la **WhatsApp Cloud API de Meta** (oficial, sin intermediario); requiere una cuenta de WhatsApp Business verificada, plantillas aprobadas y el celular de cada usuario.

### Conversión alimenticia

En **Conversión** (propietario y administrador) se ve el FCA = kg de alimento ÷ kg de biomasa ganada, por lago y siembra (ciclo actual), por especie (todas las siembras) y la tendencia de las últimas 12 semanas de cada lago. El cálculo está en [`App\Support\ConversionAlimenticia`](app/Support/ConversionAlimenticia.php):

- Alimento: salidas de bodega al lago. En policultivo se reparte entre las especies según su biomasa de ese día (estimado).
- Biomasa ganada: biomasa final − inicial + kg cosechados + biomasa trasladada − biomasa recibida. Los peces muertos no cuentan.
- El peso sale de la siembra, los muestreos, las cosechas y los traslados, interpolado entre fechas; el FCA se calcula hasta el último peso conocido. Sin ganancia (cero o negativa) o sin muestreo no se calcula y se muestra el motivo.
- El costo por lago no está incluido: requeriría guardar el precio por kg en las entradas de bodega.

### Bitácora

Registro de **solo inserción** de ración, mortalidad, traslado, muestreo, ronda, novedad, cosecha y venta: usuario, rol con el que actuó, acción, lago, detalle (copia de los datos de ese momento) y el instante exacto con la zona horaria que tenía la finca. Se escribe en la misma transacción que la acción. El propietario la consulta en **Bitácora** con filtros por persona, rol, fechas y lago.

No se puede editar ni borrar:

1. El modelo `Bitacora` lanza un error al actualizar o borrar.
2. Un trigger de PostgreSQL rechaza `UPDATE`, `DELETE` y `TRUNCATE` sobre la tabla.
3. La migración quita esos permisos al usuario de la base, y las llaves foráneas impiden borrar fincas, usuarios o lagos con registros.

El paso 3 solo tiene efecto si la aplicación **no** se conecta como superusuario de PostgreSQL (un superusuario ignora los permisos y podría desactivar el trigger). En producción usa un usuario propio, sin superusuario, dueño de las tablas.

### Exportar reportes

El informe mensual y el libro de campo se descargan en **Excel** (`openspout/openspout`, una hoja por sección, con los números como números) y en **PDF** (`dompdf/dompdf`, plantilla `resources/views/pdf/reporte.blade.php`). Pantalla, Excel y PDF usan los mismos datos (`App\Support\Reportes`), siempre de la finca del usuario, con fechas en su zona horaria y textos y formatos de su idioma y país. Las ventas y las entradas de bodega solo van en los archivos del propietario.

### Sin conexión

La app se instala en el teléfono (PWA). El service worker (`public/sw.js`) guarda las últimas 40 pantallas de consulta: con señal se piden a la red; sin señal se muestra la última copia con una franja que dice de cuándo son los datos. Antes de enviar, cada formulario verifica la señal (`/up`). Si no hay señal, el resultado depende del formulario:

- **Se guardan en el teléfono:** ración, mortalidad, aviso «listo para pescar», ronda y medición del agua; en Registro rápido, también el muestreo del administrador. Se guardan en IndexedDB, separados por finca y persona (`resources/js/sin-conexion.js`).
  Se envían solos, en orden y con su hora real, al volver la señal o al abrir cualquier pantalla.
  El servidor los revisa al llegar con las reglas de siempre: turno a esa hora, bodega y peces vivos. No acepta los de más de `SIN_CONEXION_HORAS` horas.
  **Fechas con el día real del registro:** las fechas y horas que escribe la persona se validan contra el momento real del registro, ya corregido con el desfase del reloj y en la zona de la finca (`Region::hoyDelRegistro()` y `Region::ahoraDelRegistro()`, que leen `MomentoRegistro`). No se validan contra la hora de llegada.
  Así, una ración, mortalidad o muestreo de las 23:50 enviado después de medianoche vale con la fecha de ese día. Una fecha posterior al día real del registro se rechaza con «La fecha no puede ser posterior al día en que se registró».
  En la medición del agua, la hora no puede ser posterior al momento real del registro.
  El aviso de pesca y la ronda no tienen fecha que escribir: se guardan a la hora real del registro.
  Con conexión todo sigue igual.
  **Reloj del teléfono:** cada envío trae también la hora del teléfono en ese instante (`X-Hora-Telefono`; sin ella se rechaza). El servidor calcula el desfase (su hora menos la del teléfono) y lo suma a la hora del registro antes de revisar el turno, el límite de horas y la bitácora. Si el desfase pasa de 5 minutos, la bitácora anota «hora del teléfono corregida en X minutos» y el propietario lo ve.
  **Última hora del servidor:** cada respuesta trae la hora del servidor (cabecera `X-Hora-Servidor`, middleware global `HoraServidor`, también en `/up`, `/campo/token` y `/campo/paquete`). Cada pantalla la trae también en `data-hora-servidor`.
  El teléfono guarda la más reciente por finca y persona. Nunca la baja: una pantalla guardada sin señal trae una vieja.
  Al guardar un registro, la congela en él y la envía como `X-Ultima-Hora-Servidor`. El servidor rechaza el registro si su hora real, ya corregida, es más de 2 minutos anterior a ella.
  Así queda cerrado el truco de atrasar el reloj para caer en el turno y volverlo a poner bien antes de enviar: el teléfono ya había visto una hora posterior.
  Un registro sin esa cabecera se rechaza. `SIN_CONEXION_EXIGIR_HORA_SERVIDOR=false` lo afloja.
  Caso a tener en cuenta: si el teléfono iba atrasado sin saberlo y la hora automática lo corrige entre guardar y enviar, ya no queda desfase que corregir. El registro se rechaza con el motivo («Revisa que el teléfono tenga la fecha y la hora automáticas…») y queda en «Rechazados» con sus datos para repetirlo.
  Un trabajo legítimo de varias horas sin señal pasa, porque la hora congelada es la de la última conexión. Siguen igual la corrección del reloj, los ids idempotentes y la nota de bitácora «registrado sin conexión, recibido el …».
  Un botón con el número de registros sin enviar abre la lista de pendientes y rechazados; cada rechazado muestra su motivo y sus datos.
  Al cerrar sesión con registros sin enviar, la app avisa: si sales, se borran del teléfono.
- **Los demás formularios no se envían:** lo escrito queda en el formulario. Por ejemplo el aireador, que depende de su estado en ese momento. Nunca se guardan el ingreso, la recuperación de contraseña, el panel de plataforma, el asistente ni las descargas. Al cerrar sesión se borran las copias (`Clear-Site-Data` y aviso al service worker).

#### Modo de campo: Registro rápido

Para registrar sin señal no hace falta haber abierto antes ninguna pantalla:

- **Paquete de trabajo:** con señal, el teléfono descarga solo, en segundo plano, lo mínimo para registrar (`GET /campo/paquete`). Lo hace al entrar, en cada pantalla si el paquete tiene más de 15 minutos y al volver la conexión.
  Lo guarda en IndexedDB, por finca y persona. Trae solo lo del rol:

  | Rol | Secciones |
  |---|---|
  | Trabajador | Ración, mortalidad y aviso de pesca |
  | Administrador | Ración, mortalidad y muestreo (sin turno) |
  | Celador | Ronda y medición |
  | Propietario | Ninguna |

  Incluye hasta `SIN_CONEXION_MAX_LAGOS` lagos (vacío = 60) con sus lotes activos, los concentrados de bodega, los tipos de ronda, los rangos de medición y los turnos cercanos. Para el administrador, cada lote trae además su peso actual y los límites del muestreo (`PaqueteTrabajo::RANGOS_MUESTREO`, los mismos del servidor). No trae personal ni dinero.
- **Registro rápido (`/campo`):** una sola página sin datos, que el service worker guarda al instalarse. Arma los formularios con el paquete y valida en el teléfono con las reglas del servidor.
  Muestra «Datos del … (hace …)», en ámbar si pasan de 12 horas, y avisa si según los turnos guardados la persona no está en turno.
  Se abre desde el menú (administrador, trabajador y celador), desde la franja «Sin conexión» y desde la página «Sin conexión» (`offline.html`).
- **Muestreo (administrador):** junto al lago, sin señal. Lago, lote, fecha, peces pesados y peso total en gramos, kilos o libras (ver **Unidades del peso en el muestreo**). El teléfono muestra el promedio en vivo y la diferencia con el peso del paquete; la que vale es la que calcula el servidor al llegar.
  Valida con las reglas del servidor: peces enteros de 1 a 100.000, peso de 0,1 a 999.999 g después de convertir, fecha no posterior a hoy ni anterior a la siembra. Si la siembra se finalizó mientras tanto, el registro queda en «Rechazados».
  El muestreo siempre queda en el historial. El peso actual y la biomasa solo cambian si es el más reciente por fecha de la siembra; con la misma fecha, gana el que llega después. Si no es el más reciente, la respuesta dice «Guardado en el historial. El peso actual sigue siendo X g».
  La fecha se compara con el día en que de verdad se registró (`MomentoRegistro`): un muestreo de las 23:50 enviado después de medianoche conserva su día. El modal de muestreo en Lagos sigue necesitando conexión.
- **Envío:** los registros van a la misma cola: uuid, hora real, corrección del reloj y bitácora. Antes de enviar se pide un token vigente (`GET /campo/token`), nunca el de una página guardada. Al volver la señal salen solos y la app avisa «Se enviaron N registros».
- **Al cerrar sesión** se borran el paquete, la sesión activa del teléfono y la cola, con aviso previo si hay pendientes. Si entra otra persona en el mismo teléfono, se borran los paquetes ajenos.
- **Límite:** la sesión dura `SESSION_LIFETIME` minutos (120). Tras una jornada larga sin señal, lo registrado espera a que la persona vuelva a entrar para enviarse.

#### Prueba a mano en un teléfono real (modo avión, desde cero)

Hazla en el teléfono de la finca, con la app ya en HTTPS.

1. **Teléfono limpio:** borra los datos del sitio (Chrome → ⋮ → Configuración → Configuración de sitios → Todos los sitios → la app → Borrar). Entra con señal como **trabajador** en turno y **no abras Lagos**. Espera unos 10 segundos en Inicio.
2. **Modo avión:** activa el modo avión y toca **Lagos**. Aparece «Sin conexión» con el botón **Abrir Registro rápido**.
3. **Registro rápido:** debe mostrar tu nombre, la finca, «Datos del … (hace …)» y los botones Ración, Mortalidad y Listo para pescar.
4. **Registrar sin señal:** registra una ración y una mortalidad. Dice «Quedó guardado en este teléfono» y el contador de arriba muestra **2**.
5. **Validación:** intenta más peces que los vivos del lote, una fecha futura y más kilos que los de bodega. No deja guardar y explica por qué.
6. **Volver la señal:** desactiva el modo avión y espera unos segundos. Sale «Se enviaron 2 registros» y el contador desaparece. En Lagos están la ración y la mortalidad. En la Bitácora (propietario) dicen «registrado sin conexión».
7. **Rechazo:** en modo avión registra una mortalidad. Antes de quitar el modo avión, desde otro teléfono con señal, finaliza esa siembra o registra una mortalidad que deje menos peces. Al volver la señal, la tuya queda en **Rechazados** con su motivo, y «Descartar» la quita.
8. **Celador:** entra como celador en turno, activa el modo avión y abre Registro rápido. Una ronda con novedad sin descripción no se guarda, y una medición fuera de rango tampoco. Una ronda «Sin novedad» y una temperatura válida se guardan y se envían al volver la señal.
9. **Datos viejos:** deja el teléfono más de 12 horas sin abrir la app con señal, o adelanta la hora del teléfono. El aviso «Datos del …» sale en ámbar.
10. **Cerrar sesión:** con un registro pendiente, toca «Salir». Aparece el aviso para esperar o salir y borrarlos. Sal, activa el modo avión y abre Registro rápido: dice «Todavía no hay datos en este teléfono».
11. **Otra persona:** en el mismo teléfono entra otra persona con señal. En Registro rápido solo ve su nombre y sus lagos.

#### Pantallas guardadas (consulta)

Revisión con Chrome → DevTools → Network → «Offline». `Clear-Site-Data` solo funciona con HTTPS o en localhost.

1. Con señal, abre Inicio, Lagos y el detalle de un lago. Pasa a «Offline» y vuelve a abrirlos: se ven con la franja amarilla y la hora de esos datos.
2. Sin señal, llena un formulario que no se guarda en el teléfono (por ejemplo, una siembra): aparece el aviso y lo escrito sigue ahí.
3. Cierra sesión, pasa a «Offline» y abre Inicio: ya no se ven datos de la finca.

Pruebas automáticas de la cola, del paquete y de Registro rápido en Node, sin paquetes: `npm run test:js`.

### Datos de demostración (opcional, nunca en producción)

```bash
php artisan db:seed --class=DemoPiscicolaSeeder
```

Las contraseñas de los usuarios demo se generan al azar y se muestran una sola vez en la consola.

## Seguridad

Resultado de la auditoría de la Fase 24 (ya aplicado):

- **Cabeceras** en todas las respuestas: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`, `Permissions-Policy: camera=(self), microphone=(), geolocation=()` y HSTS solo con HTTPS.
- **Límites de intentos con contador propio**: ingreso (5 por minuto por correo y 20 por IP), registro, API y panel de plataforma. Una persona bloqueada no bloquea a las demás de la misma red.
- **Registro** con un mensaje único para código de finca inexistente o correo ya registrado (con enlace a «¿Olvidaste tu contraseña?»). La contraseña no puede ser el correo.
- **Ver la contraseña:** todo campo de contraseña usa `<x-contrasena>`, con un botón de ojo para mostrar u ocultar lo escrito (`resources/js/contrasena.js`, en el bundle y sin scripts en línea). Está en el ingreso, el ingreso de plataforma, el registro, restablecer y cambiar la contraseña, y las confirmaciones con contraseña (eliminar la finca, quitar los dos pasos); no en los códigos de verificación en dos pasos.
  - El botón es `<button type="button">` de 48 × 48 px, con `aria-pressed` y `aria-label` («Mostrar contraseña» / «Ocultar contraseña»). No le quita el foco al campo y conserva el `autocomplete` de los administradores de contraseñas.
  - La contraseña vuelve a ocultarse sola al enviar el formulario, al salir de la pantalla y al volver con «atrás».
- **Topes numéricos** en todos los formularios (un valor enorme recibe un mensaje y no un error 500).
- **Tokens de la API** que vencen (`SANCTUM_TOKEN_MINUTOS`, 30 días por defecto) y **topes diarios del asistente** (`ASISTENTE_LIMITE_DIARIO_PERSONA` y `ASISTENTE_LIMITE_DIARIO_FINCA`).
- Pruebas generales de aislamiento: todos los modelos de finca y todas las rutas con ids (`AislamientoFincasTest`).

## Mi cuenta y verificación en dos pasos

- **Mi cuenta** (`/mi-cuenta`, todos los roles): cambiar la contraseña pidiendo la actual (mínimo 8, distinta del correo). Al cambiarla se cierran las demás sesiones, deja de servir «recordarme» en otros equipos y se revocan los tokens de la API.
- **Verificación en dos pasos (TOTP)** opcional, sin servicios externos: códigos de 6 números de una app de autenticación (Google Authenticator, Microsoft Authenticator, Authy…). Se activa con un código QR (paquete `chillerlan/php-qrcode`, generado en el servidor) o escribiendo la clave a mano. La clave se guarda **cifrada** con `APP_KEY`; se entregan 10 códigos de recuperación de un solo uso, guardados solo como hashes. Un código no se acepta dos veces.
- **Ingreso:** web, en `/ingreso/dos-pasos` después de la contraseña (vence a los 5 minutos); API, `POST /api/login` con el campo `codigo` (sin él responde 422 con `dos_pasos_requerido: true`). Límite propio: 5 intentos por minuto por persona y 20 por IP.
- **Teléfono perdido:** el propietario quita los dos pasos a una persona de su personal desde **Personal** (queda en la bitácora); para el propietario: `php artisan piscicola:quitar-dos-pasos correo@…`.
- El inicio de propietario y administrador avisa mientras no la tengan activa. En el panel de plataforma es obligatoria (ver «Panel de plataforma»).
- **Importante:** si cambia `APP_KEY`, las claves cifradas ya no se pueden leer y cada persona debe volver a activarla (o quitarla con el comando).

## Diseño: libreta de campo y tipografía

- Estilos en `resources/css/app.css` (Tailwind 4, tokens de la «libreta de campo»), compilados con Vite.
- **Tipografía: Nunito Sans** (variable, licencia SIL OFL 1.1), alojada en el proyecto con `@fontsource-variable/nunito-sans`, sin CDN. Se eligió por leerse bien al sol (letra grande a igual tamaño), alinear las cifras en columna con `tabular-nums`, distinguir la l (con cola) de la I y el 0 (más angosto, sin raya) de la O. Una página en español descarga solo su archivo latino (unos 31 KB), y el service worker lo guarda. Reemplazó a Atkinson Hyperlegible Next, cuyo cero con raya no se puede desactivar. Los PDF siguen con DejaVu Sans (dompdf). Ver `docs/marca/LEEME.md`.
- **Revisado a 360 px:** en el teléfono, las cifras con su unidad no se parten (`whitespace-nowrap`). El detalle del lote usa dos columnas y pasa a tres desde `sm`. El peso con rango ocupa una fila completa.
- **Íconos: Lucide 1.56** (licencia ISC, en `docs/marca/licencia-iconos.txt`), copiados dentro de `resources/views/components/icono.blade.php`, sin paquete ni CDN. Solo están los 30 que la app usa, con sus nombres de siempre. Una prueba revisa que no falte ni sobre ninguno.
- **Color por área:** cada pantalla tiene su área, definida por nombre de ruta en `app/Support/Areas.php`: Campo (verde de la marca), Bodega, Ventas, Personal (incluye Jornaleros) y Reportes. El área da color al ícono del menú y al recuadro del ícono del título, y pone una raya arriba de las fichas. Una pantalla que no está en `Areas.php` usa el estilo neutro. `IdentidadVisualTest` dice cuál quedó sin área. El color nunca es la única señal: los estados llevan texto o ícono, y la pantalla actual del menú va en negrita y con raya. Los colores de estado (bien, atención, alerta, información) no cambiaron.

## Formato de hora (12 o 24 horas)

- Cada persona elige en **Mi cuenta** cómo ve las horas: 12 horas («10:24 p. m.») o 24 horas («22:24»). Si no elige, se usa el de la finca (**Ajustes**, solo propietario). Si la finca tampoco eligió, el del país (`config/region.php`: 12 horas en CO, MX, US, VE, PE, PH, IN, CA y AU). Sin país, 24 horas.
- **Solo cambia cómo se muestra la hora:** lo guardado sigue en UTC, la fecha no cambia y los cálculos tampoco.
- **Un solo ayudante,** `App\Support\Hora`, con el macro de Carbon `->formatoHora('d/m/Y H:i')`. Hace lo mismo que `format()`, pero la parte de la hora sale en el formato de la persona. Los formatos técnicos (campos de formulario, ISO) siguen con `format()`. Una prueba barre vistas y código para que ninguna pantalla escriba la hora a mano.
- **«a. m.» y «p. m.»** salen de lang/es, con espacios que no se parten. Una frase que termina en una hora no deja punto doble («a las 5:24 a. m.»): lo resuelven el traductor (`App\Support\Traductor`) y `t()` en el JavaScript.
- **Sin conexión:** el formato ya resuelto viaja en `<body data-formato-hora>` y en el paquete de trabajo. `resources/js/hora.js` escribe igual que el servidor; una tabla de casos compartida (`tests/fixtures/horas.json`) lo comprueba en PHP y en JS. Un paquete viejo usa 24 horas.
- **Campos donde se escribe una hora** (turnos, agenda, medición): el selector del teléfono, con una ayuda debajo que repite la hora elegida en el formato de la persona.
- **Excel:** las fechas con hora son valores de fecha y hora de Excel (se pueden ordenar y calcular), con el formato elegido. El texto «AM/PM» lo escribe Excel según su idioma. **PDF:** texto en el formato elegido. **Correos:** en el formato de quien los recibe.

## Lotes de la misma especie en un lago

Un lago puede tener varios **lotes** (siembras activas) de la misma especie: dos siembras de fechas distintas, o un lote creado por un traslado.

- **Se muestran agrupados por especie**, con el total de peces, la biomasa, el peso promedio **ponderado** por los peces vivos de cada lote y, con más de un lote, el rango («319,23 g · de 50 a 400 g»), más el número de lotes (`App\Support\LotesPorEspecie`).
- **Cada lote es un detalle que se abre**, con su origen («Sembrado el 07/10/2026» o «Creado por traslado desde L02 el 15/10/2026»), los traslados que le sumaron peces («Recibió 300 peces de L03 el 20/10/2026»), sus cifras y sus botones de siempre. Con un solo lote de la especie se ve como antes, sin desplegable.
- **Dónde:** detalle del lago, lista de Lagos (también los títulos de sus modales y el selector del aviso «listo para pescar»), inicio, Noche, informe mensual (pantalla, Excel y PDF), Registro rápido (el lote se elige agrupado por especie, con su número y su origen; un paquete viejo se ve como antes) y el asistente (por especie: el total y cada lote con su origen).
- **Origen guardado:** `siembras.origen` (siembra o traslado) y `siembras.lago_origen_id`. El traslado lo guarda al crear un lote; si suma a un lote existente, ese conserva su origen. Las siembras anteriores se rellenaron en la migración: un lote es «traslado» si su primer traslado recibido se creó en la misma transacción (misma fecha, cantidad y peso inicial, a lo sumo 60 segundos después).
- **No cambia:** las operaciones siguen siendo por lote (mortalidad, muestreo, cosecha, traslado, finalizar, aviso de pesca), igual que la ración, la conversión, las alertas, el muestreo y la cola sin conexión. Las rutas, los campos y los ids de los modales son los mismos.

## Unidades del peso en el muestreo

- El peso total del muestreo se escribe en **gramos, kilos o libras**, con un selector junto al campo, en el modal de Lagos y en Registro rápido. **Lo guardado siempre queda en gramos**; el historial y la biomasa siguen en gramos.
- **El servidor convierte** (`App\Support\UnidadesPeso`: 1 kg = 1000 g, 1 lb = 453,59237 g) y no confía en la conversión del teléfono. Valida en este orden:
  1. la unidad;
  2. que el valor sea un número con hasta 6 cifras enteras y 3 decimales (numérico, finito y de tamaño razonable);
  3. si llegan `peso_total` y `peso_total_g`, que coincidan en gramos;
  4. recién después convierte y aplica el rango de 0,1 a 999.999 g. El mensaje de error dice el máximo también en la unidad elegida (999,999 kg o 2.204,620 lb).
- **Mismo redondeo en el servidor y en el teléfono:** los dos usan aritmética entera en centésimas de gramo (BigInt en JavaScript, `resources/js/unidades-peso.js`) con redondeo a la mitad hacia arriba; nada de flotantes. Una tabla de casos compartida (`tests/fixtures/unidades-peso.json`, generada con aritmética decimal exacta) la usan la prueba de PHP y la de JavaScript.
- **Compatibilidad:** un registro viejo de la cola, que solo trae `peso_total_g`, se toma en gramos. Un teléfono con un paquete de trabajo viejo, sin unidades ni factores, sigue registrando en gramos hasta que baja el paquete nuevo.
- **Unidad que aparece primero:** la última que eligió esa persona en ese teléfono (por finca y persona, en `localStorage`); si no hay, la de la finca, que el propietario elige en **Ajustes** (`tenants.unidad_peso_muestreo`, gramos por defecto).
- **Pantalla:** el promedio por pez, el «antes» y la diferencia se muestran en gramos y, entre paréntesis, en la unidad elegida: «56,70 g (0,125 lb)». La bitácora guarda los gramos y, si se escribió en otra unidad, lo escrito («peso total escrito: 2,5 lb»).
- **Para otros formularios:** `UnidadesPeso` y `unidades-peso.js` sirven también para ración o cosecha; por ahora solo los usa el muestreo.

## Jornaleros y personas sin cuenta

Fase A del módulo (las jornadas, las tarifas y la planilla vienen en las siguientes fases).

- **Tabla única `terceros`** para las personas externas de la finca: jornaleros ahora; clientes y proveedores (Gastos) después, sin duplicar personas (`es_jornalero`, `es_cliente`, `es_proveedor`).
- **Nombres y apellidos** se guardan con mayúscula inicial en cada palabra (`Tercero::formatearNombre`), solo al guardar: «tereza gonzale» → «Tereza Gonzale», «PEREZ» → «Perez», «maría de la cruz» → «María de la Cruz», «gómez-pérez» → «Gómez-Pérez», «o'neil» → «O'Neil». Respeta tildes y eñes, deja las iniciales en mayúscula («J. Pérez», «J.C.») y las partículas (de, del, la, las, los, y, e) en minúscula salvo al inicio. Lo ya guardado no cambia hasta que se edita.
- **Documento:**
  - se guarda **cifrado** con `APP_KEY` (cast `encrypted`), nunca en texto claro;
  - para que no se repita dentro de la finca se guarda una **huella HMAC-SHA256** del documento normalizado, con una clave derivada de `APP_KEY`: no se puede adivinar probando documentos;
  - el **administrador** lo escribe y ve solo los últimos 4 dígitos (`•••• 1234`); el **propietario** lo ve completo;
  - se busca solo por documento exacto, comparando huellas.
- **Anonimizar** (solo el propietario, escribiendo `ANONIMIZAR`; no se deshace): borra documento y teléfono y cambia el nombre por «Jornalero N». No se permite con saldo por cobrar.
- **Bitácora:** guarda solo el `tercero_id` (nunca el nombre ni el documento) y resuelve el nombre al mostrarse, así la anonimización también la cubre. Las ventas guardan el nombre de ese día en `cliente` y se reemplaza al anonimizar.
- **Ventas:** el comprador puede ser un empleado, un jornalero (`ventas.tercero_id`) o un nombre escrito. Un `CHECK` impide tener empleado y jornalero a la vez.
- **Asistente IA:** nunca recibe documento ni teléfono de terceros.
- **Exportar y eliminar la finca:** `terceros` sale en el ZIP con el documento legible y sin la huella, y se borra con la finca, después de la bitácora que lo referencia.
- **Guarda la `APP_KEY` aparte** (gestor de contraseñas): la copia de la base no la incluye, y sin ella los documentos cifrados no se pueden leer. Ver docs/DESPLIEGUE.md, pasos 4 y 11.

## Exportar y eliminar los datos de una finca

- **Exportar** (propietario, en Ajustes → Datos de la finca): un ZIP con un CSV por tabla (UTF-8 con BOM), las fotos originales y un `LEEME.txt`; sin contraseñas, tokens, claves de dos pasos ni datos de otras fincas. Se genera en segundo plano (trabajador de la cola), una vez por hora, y se descarga durante 24 horas (`piscicola:limpiar-exportaciones`, cada hora). Queda en la bitácora.
- **Eliminar** (propietario o administrador de plataforma): exige escribir el código de la finca y la contraseña. La finca queda suspendida y «en eliminación» `FINCA_DIAS_GRACIA` días (14 por defecto); en ese plazo solo el propietario entra, a una pantalla para cancelarla, y se le avisa por correo. Al vencer, `piscicola:eliminar-fincas` (a diario) borra en una transacción todos sus datos, en el orden de las claves foráneas leído de la base, las cuentas de su personal y sus archivos, y comprueba que no quede nada.
- **Bitácora:** sigue siendo de solo inserción. Solo la función `borrar_bitacora_finca()` (SECURITY DEFINER) borra, y únicamente filas de una finca con el plazo vencido; la app solo puede ejecutarla.
- **Copias de seguridad:** guardan los datos hasta que vence su retención. Si se restaura una copia vieja, la finca eliminada reaparece y el mismo comando la vuelve a borrar (la lista está en `storage/app/private/fincas-eliminadas.json`, fuera de la base).

## Primeros pasos de una finca nueva

El inicio del propietario y del administrador muestra una lista de 6 pasos con el código de la finca: revisar la zona horaria y el idioma (al guardar Ajustes), revisar o agregar especies, definir los pesos de canasta y canastilla, crear el primer lago, invitar al personal (se marca con la primera persona registrada) y aprobar al primer trabajador. Cada paso se marca solo; los que hace otro rol se indican sin enlace. Se puede ocultar para toda la finca y no vuelve a aparecer al ocultarse ni al completarse. Las fincas que ya tenían lagos la tienen completada.

## Términos de uso y privacidad

- Páginas públicas `/terminos` y `/privacidad` con un **texto base en borrador** (`lang/es/legal.php`) que **debe revisar un abogado antes de publicarse**. Es neutro: no menciona leyes, autoridades ni países.
- **Pendiente de revisión por el abogado:** la sección «Personas sin cuenta en la app» (jornaleros), agregada en la versión `2026-11-01`: qué datos se guardan, para qué, quién los ve, que la finca debe informarles y tener su autorización cuando la normativa lo exija, y cómo pedir verlos, corregirlos o borrarlos.
- Quién presta el servicio y su correo salen de `LEGAL_ENTIDAD` y `LEGAL_CORREO` (vacías en `.env.example`); mientras falten, las páginas lo avisan.
- **Aceptación:** casilla obligatoria en el registro y, en el primer ingreso tras cambiar `config/legal.php` → `version`, una pantalla para aceptar antes de seguir (la app móvil recibe 403 con `terminos_pendientes` y acepta en `POST /api/terminos/aceptar`). Se guarda quién, qué versión y cuándo (UTC), **sin la IP**. Las personas que ya existían aceptan en su próximo ingreso.

## Fotos de especies

Las sube una persona; la app nunca genera ni inventa imágenes. Por cada foto se guardan **dos archivos** en el disco privado `fotos` (`storage/app/private/fotos/{finca}/{especie}/`, nombres aleatorios):

- **Original** (JPEG, PNG o WebP de hasta 30 MB) **sin recomprimir**: solo se le quitan los metadatos (GPS, modelo del teléfono, autor, comentarios); en JPEG se conserva la orientación.
- **Versión ligera** WebP de máximo 1600 px y menos de 200 KB, la que usa toda la interfaz.

Se revisa el contenido real (no la extensión) y las dimensiones antes de decodificar (la mitad de `memory_limit` entre unos 5 bytes por píxel, máximo 50 megapíxeles y 12.000 px por lado). Máximo 5 fotos por especie (también en la base) y un tope por finca de 2 GB (`FOTOS_TOPE_FINCA_MB`). Se sirven solo con sesión de la finca, con el tipo fijado por el servidor y `nosniff`: la ligera para todos los roles, el original para propietario y administrador.

**Ajustes del servidor** para fotos de 30 MB: `upload_max_filesize = 32M`, `post_max_size = 40M`, `memory_limit = 512M` (con 128 MB solo pasan fotos de unos 13 megapíxeles) y en Nginx `client_max_body_size 40M`. En WAMP se cambian en el `php.ini` de Apache (ícono de WAMP → PHP → php.ini) y se reinician los servicios.

**Respaldo:** `piscicola:respaldar` copia solo la base; copia también la carpeta `storage/app/private/fotos` el mismo día (ver [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md)). Mientras la finca no sube fotos, cada especie por defecto muestra una foto de referencia con licencia libre y su crédito (autor, licencia y fuente en [docs/marca/LEEME.md](docs/marca/LEEME.md)). Los fondos del ingreso y de Inicio son fotos de estanques con licencia CC0 y de dominio público (detalle y fecha de verificación en el mismo LEEME).

## Despliegue y copias de seguridad

La guía paso a paso está en **[docs/DESPLIEGUE.md](docs/DESPLIEGUE.md)**:

- **El servidor:** Linux con PHP, PostgreSQL, Nginx y HTTPS.
- **Configuración:** permisos, los dos roles de base de datos y el `.env` de producción.
- **Procesos:** el cron y la cola.
- **Mantenimiento:** cómo actualizar sin perder datos y cómo restaurar.

Hosting compartido como InfinityFree **no sirve**: solo ofrece MySQL, sin SSH ni cron.

| Comando | Qué hace |
|---|---|
| `php artisan piscicola:respaldar` | Saca una copia con `pg_dump` y la verifica. Guarda **fuera del proyecto** (`RESPALDOS_CARPETA`) y borra las copias de más de `RESPALDOS_DIAS` días. Corre a diario si hay carpeta configurada. |
| `php artisan piscicola:restaurar archivo.dump --base=nueva` | Restaura una copia en una base nueva o vacía. Nunca escribe sobre la base que usa la app. |
| `php artisan piscicola:usuario-base` | Imprime el SQL de los dos roles de base de datos, uno dueño y otro de la app. No ejecuta nada. |

Variables: `RESPALDOS_CARPETA`, `RESPALDOS_DIAS`, `RESPALDOS_HORA`, `PG_BIN` (carpeta de `pg_dump` si no está en el PATH; en Windows, entre comillas simples) y `TRUSTED_PROXIES` (IPs del proxy de confianza).

### Estado del sistema

- **`/salud`** responde solo `ok` o `falla`, sin más datos, y guarda su resultado 30 segundos. Seis comprobaciones, todas con umbral en `config/salud.php`:

  | Comprobación | Falla si… | Variable opcional |
  |---|---|---|
  | Base de datos | no responde | — |
  | Almacenamiento | no se puede escribir y leer en `storage` | — |
  | Programador | el cron no corrió en los últimos 10 minutos | `SALUD_MINUTOS_PROGRAMADOR` |
  | Copia de seguridad | la última tiene más de 36 horas, o no hay copias en `RESPALDOS_CARPETA` | `SALUD_HORAS_COPIA` |
  | Disco | el espacio libre baja del 10 % o de 1 GB | `SALUD_DISCO_PORCENTAJE`, `SALUD_DISCO_GB` |
  | Cola | un trabajo lleva más de 15 minutos esperando, o hay trabajos en `failed_jobs` | `SALUD_MINUTOS_COLA` |

  Sin `RESPALDOS_CARPETA`, la copia falla solo en producción (`SALUD_EXIGIR_COPIA` lo cambia). La caché se ajusta con `SALUD_CACHE_SEGUNDOS`.
  Vigílala con UptimeRobot o Better Stack: monitor de palabra clave `ok`, cada 5 minutos.
- **`php artisan piscicola:estado`** muestra cada comprobación con su umbral y lo medido: versión de la base, último ciclo del programador, última copia y hace cuántas horas, espacio libre (GB y %) y la cola (pendientes, atascados y fallidos).
- **`piscicola:vigilar`** corre cada 5 minutos, muestra cada comprobación con su umbral y envía un solo correo cuando algo falla y otro cuando se recupera.
  El correo va a `SALUD_CORREO_AVISO`; si está vacía, a los administradores de plataforma.
- **Errores:** se guardan en un archivo por día en `storage/logs/` y se conservan `LOG_DAILY_DAYS` días (14).

Ver [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md), sección 15.

## Pruebas

Corren contra PostgreSQL real, en la base `sas_piscicola_v2_test`, con `RefreshDatabase`; nunca con tablas en memoria ni sobre `sas_piscicola_v2`. El asistente se prueba con un doble (Claude) y con `Http::fake()` (Gemini): las pruebas nunca llaman a las APIs reales.

```bash
createdb sas_piscicola_v2_test
npm run build      # las vistas necesitan el manifest de Vite
composer test
npm run test:js    # reglas de la cola sin conexión (node --test, sin paquetes)
```

## Valores de referencia

Las fichas de especies, las tablas de alimentación y los umbrales de calidad del agua son **orientativos**: ajústalos con el asistente técnico de la finca. La nómina usa las tarifas que configura el propietario y **no calcula recargos de ley** (nocturno, dominical, festivo). El libro de campo es un formato de apoyo: confirma con la autoridad sanitaria de tu país lo que exige la norma vigente.

## Pendiente

- Superadmin y planes de suscripción para varias fincas.
- **Traslado a un lago con la misma especie:** hoy suma los peces al lote más antiguo de esa especie con peso promedio ponderado, aunque las tallas sean muy distintas (por ejemplo alevinos de 5 g a un lote de 400 g). Mejorar con un aviso cuando los pesos difieran mucho y la opción «crear lote aparte».

## Estructura

```
app/Http/Controllers/Admin   Módulos del propietario y del administrador
app/Http/Controllers/Noche   Seguridad & Noche (celador)
app/Http/Middleware          EstablecerTenant, VerificarRol, VerificarTurno
app/Models                   Modelos de dominio; Scopes/TenantScope, Concerns/PerteneceATenant y TieneRevision
app/Support                  Lógica compartida: catálogo de especies, ración, cobertura de bodega, nómina,
                             indicadores, historial del lago y asistente IA
resources/views              Layout único y componentes Blade reutilizables
public/sw.js                 Service worker (solo archivos estáticos; las páginas no se guardan)
tests/Feature                Pruebas de cada regla de negocio
```

## Reglas del proyecto

Las reglas de desarrollo (fases, nombres, base de datos, seguridad y diseño) están en [`AGENTS.md`](AGENTS.md).
