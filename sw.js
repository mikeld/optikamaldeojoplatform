/* Optikamaldeojo - Service Worker */

const STATIC_CACHE = 'optikamaldeojo-static-v1';

const PRECACHE_URLS = [
  '/offline.html',
  '/manifest.json',
  '/assets/pwa/icon-192.png',
  '/assets/pwa/icon-512.png',
  '/assets/pwa/maskable-icon-512.png',
  '/assets/pwa/apple-touch-icon.png',
  '/assets/css/style.css',
  '/pedidos/assets/css/style.css'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE_URLS)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    Promise.all([
      caches.keys().then((keys) =>
        Promise.all(keys.map((k) => (k !== STATIC_CACHE ? caches.delete(k) : Promise.resolve(true))))
      ),
      self.clients.claim()
    ])
  );
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);
  if (url.origin !== self.location.origin) return;

  // Navegación (HTML): network-first + fallback offline, sin cachear HTML autenticado
  if (event.request.mode === 'navigate') {
    event.respondWith(fetch(event.request).catch(() => caches.match('/offline.html')));
    return;
  }

  const isStatic =
    ['style', 'script', 'image', 'font'].includes(event.request.destination) ||
    url.pathname.startsWith('/assets/') ||
    url.pathname.startsWith('/pedidos/assets/');

  if (!isStatic) return;

  // Static: cache-first + refresh en background
  event.respondWith(
    caches.match(event.request).then((cached) => {
      const fetchPromise = fetch(event.request)
        .then((res) => {
          if (!res || res.status !== 200) return res;
          const copy = res.clone();
          caches.open(STATIC_CACHE).then((cache) => cache.put(event.request, copy));
          return res;
        })
        .catch(() => cached);

      return cached || fetchPromise;
    })
  );
});
