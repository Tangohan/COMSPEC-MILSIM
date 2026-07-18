/* Athena PWA — cache shell + message hors ligne */
const CACHE_NAME = 'athena-shell-v1';
const SHELL = [
  './',
  './manifest.webmanifest',
  './assets/css/design-system.css',
  './assets/js/portal_command_palette.js',
  './assets/images/logo.png'
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.addAll(SHELL).catch(function () {
        return undefined;
      });
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys.filter(function (k) { return k !== CACHE_NAME; }).map(function (k) {
          return caches.delete(k);
        })
      );
    }).then(function () {
      return self.clients.claim();
    })
  );
});

self.addEventListener('fetch', function (event) {
  if (event.request.method !== 'GET') {
    return;
  }
  event.respondWith(
    fetch(event.request)
      .then(function (response) {
        var copy = response.clone();
        caches.open(CACHE_NAME).then(function (cache) {
          if (event.request.url.indexOf('http') === 0) {
            cache.put(event.request, copy).catch(function () {});
          }
        });
        return response;
      })
      .catch(function () {
        return caches.match(event.request).then(function (cached) {
          if (cached) {
            return cached;
          }
          return new Response(
            '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hors ligne — Athena</title></head><body style="font-family:system-ui,sans-serif;background:#f8fafc;color:#0f172a;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:1.5rem;text-align:center"><div><h1 style="font-size:1.5rem;margin:0 0 .75rem">Connexion indisponible</h1><p style="margin:0;color:#475569;max-width:28rem">Athena ne peut pas charger cette page pour le moment. Vérifiez votre connexion réseau, puis réessayez.</p></div></body></html>',
            {
              status: 503,
              statusText: 'Offline',
              headers: { 'Content-Type': 'text/html; charset=utf-8' }
            }
          );
        });
      })
  );
});
