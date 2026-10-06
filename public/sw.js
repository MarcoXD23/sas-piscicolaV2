/*
 * Service worker de El SAS Piscícola.
 * - Guarda en caché solo archivos estáticos (estilos, scripts, íconos e imágenes).
 * - Las páginas siempre se piden a la red: tienen datos de la finca que no deben quedar guardados en el
 *   teléfono ni mostrarse desactualizados. Sin conexión se muestra /offline.html.
 */
const CACHE = 'sas-piscicola-v1';
const PRECARGA = ['/offline.html', '/icono.svg', '/icons/icono-192.png', '/manifest.webmanifest'];
const ESTATICOS = ['/build/', '/images/', '/icons/'];

self.addEventListener('install', (evento) => {
    evento.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECARGA)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (evento) => {
    evento.waitUntil(
        caches.keys()
            .then((nombres) => Promise.all(nombres.filter((nombre) => nombre !== CACHE).map((nombre) => caches.delete(nombre))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (evento) => {
    const peticion = evento.request;
    if (peticion.method !== 'GET') return;

    const url = new URL(peticion.url);
    if (url.origin !== self.location.origin) return;

    // Páginas: siempre de la red; sin conexión, la página de aviso
    if (peticion.mode === 'navigate') {
        evento.respondWith(fetch(peticion).catch(() => caches.match('/offline.html')));
        return;
    }

    // Archivos estáticos: primero la caché (los de /build/ llevan huella en el nombre)
    if (ESTATICOS.some((prefijo) => url.pathname.startsWith(prefijo))) {
        evento.respondWith(
            caches.match(peticion).then((guardada) => guardada ?? fetch(peticion).then((respuesta) => {
                if (respuesta.ok) {
                    const copia = respuesta.clone();
                    caches.open(CACHE).then((cache) => cache.put(peticion, copia));
                }
                return respuesta;
            })),
        );
    }
});
