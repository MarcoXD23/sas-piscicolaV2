# El SAS Piscícola

SaaS multi-tenant para fincas piscícolas. Laravel + PostgreSQL + Blade/Tailwind (Vite). Interfaz en español.

Las reglas del proyecto están en `AGENTS.md` y son obligatorias: trabajar por fases, listar los archivos a tocar y esperar el "apruebo" antes de modificarlos, y detenerse al cerrar cada fase hasta que el usuario escriba "continúa".

## Comandos

- Pruebas: `npm run build` (una vez) y `php artisan test`. Usan la base `sas_piscicola_v2_test`.
- Primer propietario: `php artisan piscicola:crear-propietario`.
- Estilos: `npm run build` o `npm run dev`.

## Recordatorios

- No leer ni modificar `.env`. No usar `migrate:fresh` ni `migrate:rollback` sobre `sas_piscicola_v2`.
- Solo `tenant_id`; solo los 4 roles: propietario, administrador, trabajador, celador. Permisos de cada rol: ver `AGENTS.md` (el administrador opera; el propietario consulta y conserva personal, nómina, agenda, ajustes, bitácora y despachos).
- Solo PostgreSQL, sin `enum` de base de datos.
