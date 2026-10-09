# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Trabajador y celador (usuarios principales en volumen):** personal de campo de una finca piscícola. Usan el celular en el estanque, con sol fuerte, manos mojadas o guantes, a veces sin señal. Registran alimentación y mortalidad (trabajador) o rondas, oxígeno y aireadores de noche (celador), solo dentro de su turno. También ven sus tareas, permisos, horas extra, saldo de pescado y pagos.
- **Administrador:** opera la finca: lagos, siembras, cosechas, traslados, muestreos, calidad del agua, tratamientos, tareas, recepción de concentrado y ventas. Celular en campo y computador para gestión.
- **Propietario:** dueño. Consulta casi todo en solo lectura y decide lo exclusivo suyo: personal, turnos, solicitudes, nómina, agenda, ajustes, bitácora y despachos de concentrado. Usa más el computador, para reportes.
- Una persona puede tener varios roles (por ejemplo trabajador y celador por rotación, o propietario y administrador en una finca pequeña).

## Product Purpose

SaaS multi-finca para gestionar una finca piscícola de punta a punta: lagos y siembras (con policultivo), alimentación y bodega de concentrado, calidad del agua y sanidad, turnos y nómina, seguridad nocturna, cosecha con pesaje, ventas, reportes y un asistente con IA que responde solo con los datos de la finca. Éxito: que el registro diario en campo sea rápido y confiable, y que el dueño tenga los números al día sin estar en la finca.

## Positioning

Hecho para el personal de campo: quien está en el estanque registra desde el celular, por turnos, incluso sin señal (Registro rápido con cola en el teléfono que se envía al volver la conexión). La herramienta se adapta al trabajo del campo, no al revés.

## Operating Context

- Uso principal en celular, en exteriores; PWA instalable con aviso y modo de consulta sin conexión.
- Registros rápidos por turno (alimentación, mortalidad, rondas nocturnas); acciones de autogestión del personal.
- Propietario y administrador en computador para reportes imprimibles (informe mensual, libro de campo en pantalla, Excel y PDF) y gestión.
- Cada finca configura país, zona horaria e idioma; monedas, normas y formatos salen de esos ajustes. Nada visual ni textual atado a una región.

## Capabilities and Constraints

- Stack: Laravel 13, PostgreSQL, Blade + Tailwind 4 compilado con Vite (sin CDN), Alpine.js. Componentes Blade reutilizables en `resources/views/components`.
- Todo texto de interfaz en `lang/es` (vía `__()`), nunca fijo en vistas ni JavaScript.
- Solo 4 roles: propietario, administrador, trabajador, celador.
- Imágenes de la interfaz optimizadas, de menos de 200 KB, con origen y licencia anotados en `docs/marca/LEEME.md`.
- Sin librerías pesadas ni fuentes de más; carga rápida en celulares de gama media y redes lentas.
- El modo sin conexión, el Registro rápido y las vistas de PDF no se pueden romper; los scripts dependen de ids, atributos `data-*` y algunas clases.
- Un solo layout, un solo dashboard por rol y un solo widget del asistente IA.

## Brand Commitments

- Nombre: El SAS Piscícola.
- Logo obligatorio: trucha arcoíris con corona (archivos en `docs/marca/`, copias livianas en `public/icons/`).
- Paleta obligatoria: verde azulado (marca-50 a marca-950 en `resources/css/app.css`); cualquier dirección visual parte de ella.
- Interfaz en español, profesional y confiable pero cercana para gente de campo.

## Evidence on Hand

- Ninguna todavía: el producto está en desarrollo y solo hay datos de prueba. No hay clientes, testimonios, cifras de uso ni fotos propias de fincas; no se deben inventar.
- Imágenes con licencia libre ya verificadas: dos fondos de estanque y seis fotos de referencia de especies (`public/images/`, detalle en `docs/marca/LEEME.md`).

## Product Principles

1. El campo primero: cada pantalla de registro se usa con una mano, al sol y con prisa.
2. Confiable sin señal: lo registrado nunca se pierde y el estado de envío siempre se ve.
3. Cada rol ve solo lo suyo, sin ruido.
4. Fiel a los números: lo que se muestra es lo registrado; si falta un dato se dice, no se inventa.
5. Sirve en cualquier región sin cambiar el producto.

## Accessibility & Inclusion

- Contraste mínimo WCAG AA, pensado para leer bajo el sol.
- Zonas táctiles grandes (mínimo 44 × 44 px; el proyecto usa 48 px) para dedos mojados o con guantes.
- Las alertas llevan ícono y texto, nunca solo color.
- Respeta `prefers-reduced-motion`.
- Usuarios con distinta familiaridad con la tecnología: lenguaje claro y directo.
