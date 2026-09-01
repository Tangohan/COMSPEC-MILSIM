/* Athena PWA — cache shell uniquement (jamais les pages HTML dynamiques). */
const CACHE_NAME = 'athena-shell-v7';
const SHELL = [
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
        keys.map(function (k) {
          if (k !== CACHE_NAME) {
            return caches.delete(k);
          }
          return undefined;
        })
      );
    }).then(function () {
      return self.clients.claim();
    })
  );
});

function isNavigationRequest(request) {
  if (request.mode === 'navigate') {
    return true;
  }
  var accept = request.headers.get('accept') || '';
  return accept.indexOf('text/html') !== -1;
}

function isCacheableAssetResponse(request, response) {
  if (!response || !response.ok) {
    return false;
  }
  // Ne jamais mettre en cache une page HTML sous une URL d’asset (cause classique MIME text/html sur CSS/JS).
  var ct = (response.headers.get('content-type') || '').toLowerCase();
  if (ct.indexOf('text/html') !== -1) {
    return false;
  }
  if (ct.indexOf('audio/') !== -1 || ct.indexOf('video/') !== -1) {
    return false;
  }
  var url = request.url || '';
  if (url.indexOf('/assets/') === -1 && url.indexOf('/sw.js') === -1 && url.indexOf('manifest.webmanifest') === -1) {
    return false;
  }
  return url.indexOf('http') === 0;
}

function shouldBypassServiceWorker(request) {
  if (isNavigationRequest(request)) {
    return true;
  }
  var url = request.url || '';
  var path = '';
  try {
    path = new URL(url).pathname || '';
  } catch (e) {
    path = url;
  }
  if (url.indexOf('/api/') !== -1 || path.indexOf('/api/') !== -1) {
    return true;
  }
  if (url.indexOf('/atak') !== -1 || path.indexOf('/atak') !== -1) {
    return true;
  }
  if (url.indexOf('/public/atak') !== -1 || path === '/public/atak' || path.indexOf('/public/atak/') === 0) {
    return true;
  }
  if (url.indexOf('/uploads/') !== -1) {
    return true;
  }
  if (path.indexOf('/assets/sounds/') !== -1 || url.indexOf('/assets/sounds/') !== -1) {
    return true;
  }
  var dest = request.destination || '';
  if (dest === 'audio' || dest === 'video') {
    return true;
  }
  try {
    if (request.headers && request.headers.get('range')) {
      return true;
    }
  } catch (e) {}
  return false;
}

self.addEventListener('fetch', function (event) {
  if (event.request.method !== 'GET') {
    return;
  }

  // Carte, lectures tactiques, photos : réseau strict. Un échec ne doit pas
  // transformer /atak en « network error » (FetchEvent).
  if (shouldBypassServiceWorker(event.request)) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then(function (response) {
        if (isCacheableAssetResponse(event.request, response)) {
          var copy = response.clone();
          caches.open(CACHE_NAME).then(function (cache) {
            cache.put(event.request, copy).catch(function () {});
          });
        }
        return response;
      })
      .catch(function () {
        return caches.match(event.request).then(function (cached) {
          if (cached) {
            return cached;
          }
          return new Response('', { status: 504, statusText: 'offline' });
        });
      })
  );
});
