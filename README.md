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
| Especies | `/especies` y `/admin/especies` | **Catálogo por finca**: cada finca arranca con 6 especies por defecto (editables y desactivables) y el administrador agrega las suyas: clima, rangos de temperatura, oxígeno y pH y tipo de alimentación (todos opcionales; sin rangos no se inventan valores, sin alimentación la ración se registra a mano). Una especie con siembras no se borra, se desactiva. La guía y el asistente usan solo las especies de la finca. |
| Bodega | `/admin/bodega` | Dos pasos: el propietario registra el **despacho** y el administrador la **recepción** (contra el despacho o sin despacho). Diferencia por despacho (faltante, sobrante, exacto), aviso de despachos sin recibir después de N días (Ajustes), stock = recibido − raciones (nunca negativo), días de cobertura y alertas de stock. |
| Cosechas | `/admin/cosechas/crear` | Pesaje como en el cuaderno: cada **pesada** con su tipo (canasta o canastilla), cantidad y peso pesado. **Descuento = cantidad × peso de una vacía** (se multiplica) y **peso limpio = peso pesado − descuento**; totales y meta opcional en vivo. El servidor rehace todas las cuentas y guarda el total limpio. Encabezado con lago y especie, destino del pedido (con sugerencias), fecha (editable para cosechas atrasadas), hora automática y quién despachó. También se puede registrar solo el total limpio. Los pesos por defecto de una canasta (2 kg) y una canastilla (1,5 kg) vacías los cambia el administrador en **Pesaje**. Detalle de cada cosecha, historial del lago, informe mensual y libro de campo (pantalla, Excel y PDF) muestran pesadas y totales. |
| Ventas | `/admin/ventas` | Ventas de pescado cosechado (de contado o a crédito, también a empleados) y abonos. |
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
| `MAIL_*` | Correo saliente (recuperar contraseña). Vacío = los correos se escriben en `storage/logs/laravel.log`. |

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

Luego `php artisan config:clear`. Mientras `MAIL_MAILER` esté vacío, el correo (con el enlace) queda en `storage/logs/laravel.log`.

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

La app se instala en el teléfono (PWA). El service worker (`public/sw.js`) guarda las últimas 40 pantallas de consulta: con señal se piden a la red; sin señal se muestra la última copia con una franja que dice de cuándo son los datos. **Nada se registra sin conexión**: antes de enviar, cada formulario verifica la señal (`/up`); si no hay, no se envía y lo escrito queda en el formulario. Nunca se guardan el ingreso, la recuperación de contraseña, el panel de plataforma, el asistente ni las descargas. Al cerrar sesión se borran las copias (`Clear-Site-Data` y aviso al service worker).

Revisión manual (Chrome → DevTools → Network → «Offline»; `Clear-Site-Data` solo funciona con HTTPS o en localhost):

1. Con señal, abrir Inicio, Lagos y el detalle de un lago. Pasar a «Offline» y volver a abrirlos: se ven con la franja amarilla y la hora de esos datos.
2. Sin señal, abrir una pantalla nunca visitada: aparece la página «Sin conexión».
3. Sin señal, llenar un formulario (por ejemplo, una ración) y guardar: aparece el aviso y lo escrito sigue ahí. Volver a «Online» y guardar: se registra.
4. Cerrar sesión, pasar a «Offline» y abrir Inicio: ya no se ven datos de la finca.

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
- **Topes numéricos** en todos los formularios (un valor enorme recibe un mensaje y no un error 500).
- **Tokens de la API** que vencen (`SANCTUM_TOKEN_MINUTOS`, 30 días por defecto) y **topes diarios del asistente** (`ASISTENTE_LIMITE_DIARIO_PERSONA` y `ASISTENTE_LIMITE_DIARIO_FINCA`).
- Pruebas generales de aislamiento: todos los modelos de finca y todas las rutas con ids (`AislamientoFincasTest`).

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

## Pruebas

Corren contra PostgreSQL real, en la base `sas_piscicola_v2_test`, con `RefreshDatabase`; nunca con tablas en memoria ni sobre `sas_piscicola_v2`. El asistente se prueba con un doble (Claude) y con `Http::fake()` (Gemini): las pruebas nunca llaman a las APIs reales.

```bash
createdb sas_piscicola_v2_test
npm run build      # las vistas necesitan el manifest de Vite
composer test
```

## Valores de referencia

Las fichas de especies, las tablas de alimentación y los umbrales de calidad del agua son **orientativos**: ajústalos con el asistente técnico de la finca. La nómina usa las tarifas que configura el propietario y **no calcula recargos de ley** (nocturno, dominical, festivo). El libro de campo es un formato de apoyo: confirma con la autoridad sanitaria de tu país lo que exige la norma vigente.

## Pendiente

- Foto real de la mojarra roja (`public/images/especies/mojarra-roja.webp`, menos de 200 KB).
- Registro sin conexión con sincronización posterior (hoy, sin internet solo se muestra un aviso).
- Superadmin y planes de suscripción para varias fincas.

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
