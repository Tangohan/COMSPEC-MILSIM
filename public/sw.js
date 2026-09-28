/* Athena PWA — cache shell + fonds de carte Overwatch (tuiles / relief).
   Les pages HTML dynamiques et le reste de l’API restent hors cache. */
const CACHE_NAME = 'athena-shell-v10';
const TILE_CACHE_NAME = 'athena-overwatch-tiles-v2';
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
          // Conserver le shell courant et les caches de tuiles Overwatch.
          if (k === CACHE_NAME || k.indexOf('athena-overwatch-tiles') === 0) {
            return undefined;
          }
          return caches.delete(k);
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
  if (ct.indexOf('text/') !== -1 && ct.indexOf('text/css') === -1) {
    return false;
  }
  if (ct.indexOf('application/json') !== -1) {
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

function isMapTileRequest(request) {
  var url = request.url || '';
  var path = '';
  try {
    var parsed = new URL(url);
    if (parsed.origin !== self.location.origin) {
      return false;
    }
    path = parsed.pathname || '';
  } catch (e) {
    return false;
  }
  if (path.indexOf('/api/atak/tiles') !== -1) {
    return true;
  }
  if (path.indexOf('/api/atak/terrain/rgb/') !== -1) {
    return true;
  }
  return false;
}

function respondMapTile(request) {
  return caches.open(TILE_CACHE_NAME).then(function (cache) {
    return cache.match(request).then(function (cached) {
      if (cached) {
        return cached;
      }
      return fetch(request).then(function (response) {
        if (response && response.ok) {
          var ct = (response.headers.get('content-type') || '').toLowerCase();
          if (ct.indexOf('image/') === 0 || ct.indexOf('octet-stream') !== -1 || ct === '') {
            cache.put(request, response.clone()).catch(function () {});
          }
        }
        return response;
      }).catch(function () {
        return new Response('', { status: 504, statusText: 'offline' });
      });
    });
  });
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
  try {
    if (new URL(url).origin !== self.location.origin) {
      return true;
    }
  } catch (e) {}
  if (isMapTileRequest(request)) {
    return false;
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
  if (path.indexOf('/assets/vendor/pdfjs/') !== -1 || url.indexOf('/assets/vendor/pdfjs/') !== -1) {
    return true;
  }
  if (path.indexOf('/documents/') !== -1 || url.indexOf('/documents/') !== -1) {
    return true;
  }
  if (path.slice(-4) === '.mjs' || path.indexOf('.mjs?') !== -1) {
    return true;
  }
  var dest = request.destination || '';
  if (dest === 'audio' || dest === 'video' || dest === 'worker' || dest === 'script') {
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

  if (isMapTileRequest(event.request)) {
    event.respondWith(respondMapTile(event.request));
    return;
  }

  // Carte (hors tuiles), lectures tactiques, photos : réseau strict.
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
