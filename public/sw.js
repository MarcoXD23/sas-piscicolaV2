/*
 * Service worker de El SAS Piscícola.
 * - Archivos estáticos (estilos, scripts, íconos, fondos y fotos de referencia de especies): primero la caché.
 *   Cada versión nueva borra del teléfono las copias de la anterior (por ejemplo, las imágenes retiradas en la v7).
 * - Pantallas de consulta: primero la red; si no hay señal, la última copia guardada (solo lectura).
 *   Nunca se guardan el ingreso, la recuperación de contraseña, el panel de plataforma, el asistente ni las descargas.
 * - Sin copia guardada: /offline.html.
 * - Al cerrar sesión la página pide borrar las copias ({tipo: 'limpiar'}); el servidor además envía Clear-Site-Data.
 * - Los formularios verifican la señal antes de enviar (resources/js/app.js). Sin señal, solo raciones, mortalidad,
 *   avisos de pesca, rondas, mediciones y (en Registro rápido) muestreos se guardan en el teléfono (IndexedDB) y se envían
 *   al volver (resources/js/sin-conexion.js).
 * - Registro rápido (/campo): página sin datos que se guarda al instalarse, junto con sus estilos y scripts, aparte de
 *   las 40 pantallas (no se pierde al navegar). Con señal se renueva; sin señal se sirve la guardada. Cada paquete de
 *   trabajo nuevo pide renovarla ({tipo: 'actualizar-campo'}). Nunca se guarda nada de /campo/ (paquete y token).
 */
// v14: íconos de Lucide y color por área; los teléfonos borran los estilos y las pantallas guardados de la versión anterior
const VERSION = 'v14';
const CACHE_ESTATICOS = `sas-piscicola-estaticos-${VERSION}`;
const CACHE_PAGINAS = `sas-piscicola-paginas-${VERSION}`;
const MAXIMO_PAGINAS = 40;
const CAMPO = '/campo';
const PRECARGA = ['/offline.html', CAMPO, '/favicon.ico', '/icons/icon-192.png', '/manifest.webmanifest'];
const ESTATICOS = ['/build/', '/images/', '/icons/'];
const NUNCA_GUARDAR = ['/login', '/registro', '/olvide-contrasena', '/restablecer-contrasena', '/ingreso', '/mi-cuenta', '/plataforma', '/asistente', '/logout', '/api/', '/up', '/salud', '/campo/'];
const DESCARGAS = ['/excel', '/pdf'];

const sePuedeGuardar = (url) =>
    !NUNCA_GUARDAR.some((prefijo) => url.pathname === prefijo || url.pathname.startsWith(prefijo.endsWith('/') ? prefijo : `${prefijo}/`))
    && !DESCARGAS.some((final) => url.pathname.endsWith(final));

self.addEventListener('install', (evento) => {
    evento.waitUntil(caches.open(CACHE_ESTATICOS)
        .then((cache) => cache.addAll(PRECARGA))
        .then(() => caches.match(CAMPO, { cacheName: CACHE_ESTATICOS }))
        .then((guardada) => guardada && guardarCampo(guardada).catch(() => {}))
        .then(() => self.skipWaiting()));
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

/** Guarda /campo y los estilos y scripts que usa (para que funcione sin señal aunque nunca se haya abierto). */
const guardarCampo = async (respuesta) => {
    const cache = await caches.open(CACHE_ESTATICOS);
    const html = await respuesta.clone().text();
    await cache.put(CAMPO, respuesta);
    const recursos = [...new Set(html.match(/\/build\/assets\/[^"'\s)]+/g) ?? [])];
    await Promise.all(recursos.map(async (ruta) => {
        if (!(await cache.match(ruta))) await cache.add(ruta).catch(() => {});
    }));
};

const actualizarCampo = async () => {
    const respuesta = await fetch(CAMPO, { cache: 'no-cache' });
    if (respuesta.ok && respuesta.type === 'basic') await guardarCampo(respuesta);
};

self.addEventListener('message', (evento) => {
    if (evento.data?.tipo === 'limpiar') {
        evento.waitUntil(caches.delete(CACHE_PAGINAS));
    }
    if (evento.data?.tipo === 'actualizar-campo') {
        evento.waitUntil(actualizarCampo().catch(() => {}));
    }
});

/** Registro rápido: con señal, la versión nueva (y se guarda); sin señal, la guardada. */
const campo = async (evento) => {
    try {
        const respuesta = await fetch(evento.request);
        if (respuesta.ok && !respuesta.redirected && respuesta.type === 'basic') evento.waitUntil(guardarCampo(respuesta.clone()));
        return respuesta;
    } catch {
        return (await caches.match(CAMPO, { cacheName: CACHE_ESTATICOS })) ?? caches.match('/offline.html');
    }
};

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
        evento.respondWith(url.pathname === CAMPO ? campo(evento) : pagina(evento, url));
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
