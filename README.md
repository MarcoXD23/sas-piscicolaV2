# El SAS Piscícola

SaaS multi-tenant para fincas piscícolas (Tolima, Colombia). Permite llevar el control de lagos, siembras, muestreos, mortalidad, alimentación y bodega de alimento, con acceso por rol para cada persona de la finca.

- **Stack:** Laravel, PostgreSQL, Blade con Tailwind compilado con Vite (sin CDN) y Alpine.js.
- **Zona horaria:** America/Bogota. **Idioma:** español.
- **Pensado primero para celular**, porque los trabajadores lo usan en la finca.

## Roles

Un usuario puede tener más de un rol (por ejemplo trabajador y celador, por la rotación de turnos).

| Rol | Acceso |
|---|---|
| `propietario` | Control total. Exclusivo: personal, recepción de alimento a bodega, agenda operativa y dashboard ejecutivo. |
| `administrador` | Área técnica: lagos, siembras, muestreos, mortalidad y traslados. |
| `trabajador` | Alimentación y mortalidad, solo en su turno. |
| `celador` | Solo el módulo Seguridad & Noche, solo en su turno. |

## Multi-tenant

Cada finca es un `tenant`. Toda tabla de negocio lleva `tenant_id` y se filtra con un único global scope (`TenantScope`), de modo que una finca nunca ve los datos de otra.

## Requisitos

- PHP 8.4 o superior (según `composer.lock`)
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

Edita `.env` con los datos de tu PostgreSQL (`DB_USERNAME`, `DB_PASSWORD`; la base por defecto es `sas_piscicola_v2`) y luego:

```bash
php artisan migrate
npm run build
```

Para desarrollo con recarga de estilos usa `npm run dev`.

### Primer propietario

No hay usuarios ni contraseñas fijas. El primer propietario se crea con un comando que pide la contraseña:

```bash
php artisan piscicola:crear-propietario
```

### Datos de demostración (opcional)

```bash
php artisan db:seed --class=DemoPiscicolaSeeder
```

## Pruebas

Las pruebas corren contra PostgreSQL real, en la base `sas_piscicola_v2_test`, con `RefreshDatabase`. Nunca usan tablas en memoria ni la base `sas_piscicola_v2`.

```bash
createdb sas_piscicola_v2_test
npm run build        # las vistas necesitan el manifest de Vite
php artisan test
```

## Estructura

```
app/Http/Controllers/Admin   Lagos, siembras, muestreos, mortalidad, raciones, bodega, personal
app/Http/Middleware          EstablecerTenant, VerificarRol
app/Models                   Modelos de dominio; Scopes/TenantScope y Concerns/PerteneceATenant
resources/views              Layout único y componentes Blade reutilizables
tests/Feature                Pruebas de cada regla de negocio
```

## Reglas del proyecto

Las reglas de desarrollo (fases, nombres, base de datos, seguridad y diseño) están en [`AGENTS.md`](AGENTS.md).
