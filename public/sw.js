/*
 * Service worker de El SAS Piscícola.
 * - Archivos estáticos (estilos, scripts, íconos, imágenes): primero la caché.
 * - Pantallas de consulta: primero la red; si no hay señal, la última copia guardada (solo lectura).
 *   Nunca se guardan el ingreso, la recuperación de contraseña, el panel de plataforma, el asistente ni las descargas.
 * - Sin copia guardada: /offline.html.
 * - Al cerrar sesión la página pide borrar las copias ({tipo: 'limpiar'}); el servidor además envía Clear-Site-Data.
 * - Nada se registra sin conexión: los formularios verifican la señal antes de enviar (resources/js/app.js).
 */
const VERSION = 'v4';
const CACHE_ESTATICOS = `sas-piscicola-estaticos-${VERSION}`;
const CACHE_PAGINAS = `sas-piscicola-paginas-${VERSION}`;
const MAXIMO_PAGINAS = 40;
const PRECARGA = ['/offline.html', '/favicon.ico', '/icons/icon-192.png', '/manifest.webmanifest'];
const ESTATICOS = ['/build/', '/images/', '/icons/'];
const NUNCA_GUARDAR = ['/login', '/registro', '/olvide-contrasena', '/restablecer-contrasena', '/ingreso', '/mi-cuenta', '/plataforma', '/asistente', '/logout', '/api/', '/up'];
const DESCARGAS = ['/excel', '/pdf'];

const sePuedeGuardar = (url) =>
    !NUNCA_GUARDAR.some((prefijo) => url.pathname === prefijo || url.pathname.startsWith(prefijo.endsWith('/') ? prefijo : `${prefijo}/`))
    && !DESCARGAS.some((final) => url.pathname.endsWith(final));

self.addEventListener('install', (evento) => {
    evento.waitUntil(caches.open(CACHE_ESTATICOS).then((cache) => cache.addAll(PRECARGA)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (evento) => {
    evento.waitUntil(
        caches.keys()
            .then((nombres) => Promise.all(nombres
                .filter((nombre) => ![CACHE_ESTATICOS, CACHE_PAGINAS].includes(nombre))
                .map((nombre) => caches.delete(nombre))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (evento) => {
    if (evento.data?.tipo === 'limpiar') {
        evento.waitUntil(caches.delete(CACHE_PAGINAS));
    }
});

const guardarPagina = async (peticion, respuesta) => {
    const cache = await caches.open(CACHE_PAGINAS);
    await cache.delete(peticion);
    await cache.put(peticion, respuesta);

    // Solo las últimas páginas: las más antiguas salen primero
    const claves = await cache.keys();
    await Promise.all(claves.slice(0, Math.max(claves.length - MAXIMO_PAGINAS, 0)).map((clave) => cache.delete(clave)));
};

const pagina = async (evento, url) => {
    const peticion = evento.request;
    try {
        const respuesta = await fetch(peticion);
        // Se guarda solo una página propia y correcta (no una redirección al ingreso por sesión vencida)
        if (respuesta.ok && !respuesta.redirected && respuesta.type === 'basic' && sePuedeGuardar(url)) {
            evento.waitUntil(guardarPagina(peticion, respuesta.clone()));
        }
        return respuesta;
    } catch {
        const guardada = sePuedeGuardar(url) ? await caches.match(peticion, { cacheName: CACHE_PAGINAS }) : undefined;
        return guardada ?? caches.match('/offline.html');
    }
};

self.addEventListener('fetch', (evento) => {
    const peticion = evento.request;
    if (peticion.method !== 'GET') return;

    const url = new URL(peticion.url);
    if (url.origin !== self.location.origin) return;

    if (peticion.mode === 'navigate') {
        evento.respondWith(pagina(evento, url));
        return;
    }

    // Archivos estáticos: primero la caché (los de /build/ llevan huella en el nombre)
    if (ESTATICOS.some((prefijo) => url.pathname.startsWith(prefijo))) {
        evento.respondWith(
            caches.match(peticion).then((guardada) => guardada ?? fetch(peticion).then((respuesta) => {
                if (respuesta.ok) {
                    const copia = respuesta.clone();
                    caches.open(CACHE_ESTATICOS).then((cache) => cache.put(peticion, copia));
                }
                return respuesta;
            })),
        );
    }
});
