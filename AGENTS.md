# AGENTS.md — El SAS Piscícola

SaaS multi-tenant para fincas piscícolas (Tolima, Colombia). Stack: Laravel + PostgreSQL + Blade con Tailwind compilado con Vite (sin CDN). Zona horaria America/Bogota. Interfaz en español.

## Fase 0 – Reglas generales (seguirlas siempre)

- Trabaja por FASES, en orden. Al terminar una fase te DETIENES y esperas que el usuario escriba "continúa". Nunca empieces la siguiente por tu cuenta.
- Haz SOLO lo que dice la fase actual. No agregues módulos, páginas, paquetes ni datos de ejemplo que no se pidan.
- Antes de crear o modificar archivos, lista cuáles vas a tocar y espera el "apruebo".
- Multi-tenant desde el inicio: toda tabla de negocio lleva tenant_id y todo se filtra con un único global scope. Usa solo el nombre tenant_id en todo el proyecto.
- Roles (solo estos 4, con estos nombres exactos): propietario, administrador, trabajador, celador. No inventes otros ni alias. Un usuario puede tener más de un rol (por ejemplo trabajador y celador, por la rotación de turnos): guárdalos en una tabla pivote, no en una columna enum.
- Permisos por rol:
  - propietario: control total. Exclusivo suyo: gestión de personal, recepción de alimento a bodega, agenda operativa y dashboard ejecutivo.
  - administrador: área técnica (lagos, siembras, muestreos, mortalidad, traslados). Bloqueado de personal, bodega y agenda.
  - trabajador: alimentación y mortalidad, solo en su turno.
  - celador: solo el módulo Seguridad & Noche, solo en su turno.
  - trabajador y celador además tienen autogestión ("Mi trabajo"): marcar como hechas sus tareas (solo en su turno), pedir permisos, reportar horas extra y ver su saldo de pescado y sus pagos de nómina. Aprobar permisos y horas extra, y liquidar la nómina, es exclusivo del propietario.
- Un solo modelo y una sola tabla por concepto, con nombres del dominio en español y consistentes (lagos, siembras, muestreos, mortalidades, traslados). Nunca dos nombres para lo mismo ni versiones en inglés y español.
- Base de datos: solo PostgreSQL. No uses enum de base de datos: para valores fijos (tipo de lago, especie) usa string y valídalos en la aplicación. No uses sintaxis de otras bases (nada de CREATE VIEW IF NOT EXISTS ni SQL de MySQL o SQLite). No uses try/catch para esconder errores de SQL dentro de migraciones.
- Todo movimiento de stock, peces o biomasa va en transacción y se guarda como registro histórico (nunca solo sobrescribir saldos).
- Pruebas: cada regla de negocio tiene prueba. Corren contra las migraciones reales de PostgreSQL en la base sas_piscicola_v2_test con RefreshDatabase, nunca con tablas en memoria ni sobre sas_piscicola_v2.
- No leas ni modifiques el .env. No uses migrate:fresh ni migrate:rollback sobre sas_piscicola_v2. Si necesitas una variable nueva, agrégala vacía en .env.example e informa cuál.
- Seguridad: no crees usuarios con contraseñas fijas ni datos reales de nadie (NIT, registros ICA, correos) en el código. El primer propietario se crea con un comando artisan que pide la contraseña.
- Diseño: interfaz limpia y profesional, pensada primero para celular (los trabajadores la usan en la finca). Paleta verde azulado, tipografía legible, botones grandes. Un solo layout y componentes Blade reutilizables (tarjeta, tabla, formulario, botón). Un solo dashboard por rol y un solo widget del asistente IA. Sin imágenes pesadas: cada imagen optimizada y de menos de 200 KB.
- Al cerrar cada fase: corre las pruebas y da un resumen de máximo 10 líneas (qué se hizo, resultado de las pruebas, qué falta). Luego espera.

