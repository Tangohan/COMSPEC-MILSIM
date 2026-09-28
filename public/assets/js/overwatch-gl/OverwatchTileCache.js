/* Cache navigateur des fonds de carte Overwatch (Cache API + mémoire).
   Objectif : moins de hits VPS sur /api/atak/tiles et le peintre Relief 3D. */
window.OverwatchTileCache = (function () {
  'use strict';

  var NAME = 'athena-overwatch-tiles-v2';
  var MEM_MAX = 320;
  var mem = Object.create(null);
  var memKeys = [];
  var openPromise = null;

  function openCache() {
    if (!window.caches || typeof window.caches.open !== 'function') {
      return Promise.resolve(null);
    }
    if (!openPromise) {
      openPromise = window.caches.open(NAME).catch(function () { return null; });
    }
    return openPromise;
  }

  function remember(key, blob) {
    if (!key || !blob) return;
    if (!mem[key]) {
      memKeys.push(key);
      while (memKeys.length > MEM_MAX) {
        var drop = memKeys.shift();
        delete mem[drop];
      }
    }
    mem[key] = blob;
  }

  function networkBlob(url, credentials) {
    return fetch(url, {
      mode: 'cors',
      credentials: credentials || 'omit',
      cache: 'default'
    }).then(function (res) {
      if (!res || !res.ok) return null;
      var ct = String(res.headers.get('content-type') || '').toLowerCase();
      if (ct.indexOf('text/') === 0 || ct.indexOf('application/json') === 0) return null;
      return res.blob().then(function (blob) {
        if (!blob || blob.size < 32) return null;
        return blob;
      });
    }).catch(function () { return null; });
  }

  function fetchBlob(url, opts) {
    opts = opts || {};
    var credentials = opts.credentials || 'omit';
    var key = String(url || '');
    if (!key) return Promise.resolve(null);
    if (mem[key]) return Promise.resolve(mem[key]);

    return openCache().then(function (cache) {
      if (!cache) {
        return networkBlob(key, credentials).then(function (blob) {
          if (blob) remember(key, blob);
          return blob;
        });
      }
      return cache.match(key).then(function (hit) {
        if (hit && hit.ok) {
          return hit.blob().then(function (blob) {
            if (blob && blob.size >= 32) {
              remember(key, blob);
              return blob;
            }
            return null;
          });
        }
        return networkBlob(key, credentials).then(function (blob) {
          if (!blob) return null;
          remember(key, blob);
          try {
            cache.put(key, new Response(blob.slice(), {
              status: 200,
              headers: { 'Content-Type': blob.type || 'image/png', 'Cache-Control': 'public, max-age=604800' }
            })).catch(function () {});
          } catch (e) {}
          return blob;
        });
      });
    }).catch(function () {
      return networkBlob(key, credentials);
    });
  }

  function loadImage(url, abort) {
    return fetchBlob(url).then(function (blob) {
      if (!blob) return null;
      return new Promise(function (resolve, reject) {
        if (abort && abort.signal && abort.signal.aborted) {
          reject(new Error('abort'));
          return;
        }
        var img = new Image();
        img.crossOrigin = 'anonymous';
        var obj = URL.createObjectURL(blob);
        var done = false;
        function finish(val) {
          if (done) return;
          done = true;
          try { URL.revokeObjectURL(obj); } catch (e) {}
          resolve(val);
        }
        img.onload = function () { finish(img); };
        img.onerror = function () { finish(null); };
        if (abort && abort.signal) {
          abort.signal.addEventListener('abort', function () {
            img.src = '';
            finish(null);
          });
        }
        img.src = obj;
      });
    });
  }

  function composedKey(parts) {
    return 'ow-composed:' + String(parts || '');
  }

  function getArrayBuffer(key) {
    var full = composedKey(key);
    if (mem[full] && mem[full].arrayBuffer) {
      return mem[full].arrayBuffer().then(function (buf) { return buf; }).catch(function () { return null; });
    }
    if (mem[full] instanceof ArrayBuffer) {
      return Promise.resolve(mem[full]);
    }
    return openCache().then(function (cache) {
      if (!cache) return null;
      return cache.match(full).then(function (hit) {
        if (!hit || !hit.ok) return null;
        return hit.arrayBuffer().then(function (buf) {
          if (buf && buf.byteLength > 64) {
            remember(full, new Blob([buf], { type: 'image/png' }));
            return buf;
          }
          return null;
        });
      });
    }).catch(function () { return null; });
  }

  function putArrayBuffer(key, buffer) {
    if (!buffer || !(buffer.byteLength > 1024)) return;
    var full = composedKey(key);
    var blob = new Blob([buffer], { type: 'image/png' });
    remember(full, blob);
    openCache().then(function (cache) {
      if (!cache) return;
      cache.put(full, new Response(blob.slice(), {
        status: 200,
        headers: { 'Content-Type': 'image/png', 'Cache-Control': 'public, max-age=604800' }
      })).catch(function () {});
    }).catch(function () {});
  }

  function ready() {
    return openCache().then(function (cache) { return !!cache; });
  }

  return {
    NAME: NAME,
    fetchBlob: fetchBlob,
    loadImage: loadImage,
    getArrayBuffer: getArrayBuffer,
    putArrayBuffer: putArrayBuffer,
    ready: ready
  };
})();
