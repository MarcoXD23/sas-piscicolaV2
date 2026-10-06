# El SAS Piscícola

SaaS multi-tenant para la gestión de fincas piscícolas (Tolima, Colombia): lagos, siembras y policultivo, alimentación y bodega, calidad del agua y sanidad, turnos y nómina, seguridad nocturna, cosecha y ventas, reportes y un asistente con IA.

- **Stack:** Laravel 13 · PostgreSQL · Blade + Tailwind 4 (compilado con Vite, sin CDN) · Alpine.js · Sanctum (API) · SDK oficial de Anthropic (asistente).
- **Zona horaria:** America/Bogota. **Idioma:** español (incluidos los mensajes de validación, en `lang/es`).
- **Pensado primero para celular**, porque el personal la usa en la finca. Se instala como app (PWA) y muestra un aviso cuando no hay conexión.

## Roles

Solo existen cuatro roles. Una persona puede tener varios (por ejemplo trabajador y celador, por la rotación de turnos); se guardan en la tabla pivote `rol_user`.

| Rol | Qué puede hacer |
|---|---|
| `propietario` | Todo. Exclusivo: personal (roles, turnos, solicitudes, nómina), bodega, ventas y créditos, agenda operativa y panel ejecutivo. |
| `administrador` | Área técnica: lagos, siembras, muestreos, mortalidad, traslados, calidad del agua, sanidad, cosecha, tareas y reportes. |
| `trabajador` | Alimentación y mortalidad **solo dentro de su turno**; sus tareas, permisos y horas extra. |
| `celador` | Seguridad & Noche (rondas, oxígeno, aireadores) **solo dentro de su turno**; sus tareas, permisos y horas extra. |

## Módulos

| Módulo | Dónde | Qué hace |
|---|---|---|
| Inicio | `/` | Un panel por rol: ejecutivo (gráficas, ventas, cartera), técnico, ración del día (trabajador) y alertas de agua (celador). |
| Lagos | `/admin/lagos` | Siembras y **policultivo** (sin repetir especie ni mezclar agua fría y cálida), alimentación con ración sugerida, mortalidad, muestreos, traslados y desdobles, cosecha. Detalle con historial completo. |
| Calidad del agua y sanidad | detalle del lago | Mediciones con alertas según las especies del lago; tratamientos con **retiro sanitario** que bloquea la cosecha. |
| Especies | `/especies` | Guía con fichas de referencia de 6 especies. |
| Bodega | `/admin/bodega` | Entradas y salidas de concentrado, días de cobertura y alertas de stock bajo. |
| Ventas | `/admin/ventas` | Ventas de pescado cosechado (de contado o a crédito, también a empleados) y abonos. |
| Personal | `/admin/personal` | Aprobación y roles, **turnos rotativos**, solicitudes (permisos y horas extra) y **nómina semanal**. |
| Tareas | `/admin/tareas` | Tareas para el personal de campo; se marcan hechas solo en turno. |
| Mi trabajo | `/mi-trabajo` | Para trabajador y celador: tareas, permisos, horas extra, saldo de pescado y pagos de nómina. |
| Seguridad & Noche | `/noche` | Rondas con o sin novedad, encendido de aireadores y oxígeno nocturno. |
| Agenda | `/admin/agenda` | Calendario de la finca con recordatorios; muestra retiros sanitarios y permisos aprobados. |
| Reportes | `/admin/reportes` | Informe mensual y libro de campo por lago (imprimibles o PDF desde el navegador). |
| Asistente IA | botón flotante | Preguntas sobre la finca con Claude; solo usa los datos que el rol puede ver. |

Todo movimiento de stock, peces, biomasa o dinero se hace en una transacción y queda como registro histórico (nunca se sobrescribe solo un saldo).

## Multi-tenant

Cada finca es un `tenant`. Toda tabla de negocio lleva `tenant_id` y se filtra con un único global scope ([`TenantScope`](app/Models/Scopes/TenantScope.php), aplicado con el trait [`PerteneceATenant`](app/Models/Concerns/PerteneceATenant.php)). Los middlewares `tenant`, `rol` y `turno` fijan la finca activa, restringen por rol y exigen turno en curso. Una prueba (`CierreTest`) verifica que toda ruta privada exija sesión, finca y, en los módulos restringidos, rol.

## Requisitos

- PHP 8.4 o superior
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
| `ANTHROPIC_API_KEY` | Clave de [console.anthropic.com](https://console.anthropic.com) para el asistente IA. Sin clave, el asistente aparece como "no configurado". |
| `ANTHROPIC_MODEL` | Opcional. Por defecto `claude-opus-5-5`. |

Luego:

```bash
php artisan migrate
npm run build      # o `npm run dev` durante el desarrollo
```

### Primer propietario

No hay usuarios ni contraseñas fijas. Crea la finca y su propietario con un comando que pide la contraseña:

```bash
php artisan piscicola:crear-propietario
```

El personal se registra en `/registro` con el código de la finca, y el propietario lo aprueba en **Personal**.

### Datos de demostración (opcional, nunca en producción)

```bash
php artisan db:seed --class=DemoPiscicolaSeeder
```

Las contraseñas de los usuarios demo se generan al azar y se muestran una sola vez en la consola.

## Pruebas

Corren contra PostgreSQL real, en la base `sas_piscicola_v2_test`, con `RefreshDatabase`; nunca con tablas en memoria ni sobre `sas_piscicola_v2`. El asistente se prueba con un doble: las pruebas no llaman a la API de Anthropic.

```bash
createdb sas_piscicola_v2_test
npm run build      # las vistas necesitan el manifest de Vite
composer test
```

## Valores de referencia

Las fichas de especies, las tablas de alimentación y los umbrales de calidad del agua son **orientativos**: ajústalos con el asistente técnico de la finca. La nómina usa las tarifas que configura el propietario y **no calcula recargos de ley** (nocturno, dominical, festivo). El libro de campo es un formato de apoyo: confirma con tu oficina del ICA lo que exige la norma vigente.

## Pendiente

- Foto real de la mojarra roja (`public/images/especies/mojarra-roja.webp`, menos de 200 KB).
- Registro sin conexión con sincronización posterior (hoy, sin internet solo se muestra un aviso).
- Superadmin y planes de suscripción para varias fincas.
- Edición de eventos de la agenda y pesajes por canasta en la cosecha.

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
