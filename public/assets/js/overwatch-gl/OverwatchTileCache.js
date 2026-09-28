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

  function tileUrlFromPattern(pattern, z, x, y) {
    var raw = String(pattern || '')
      .replace('{z}', String(z))
      .replace('{x}', String(x))
      .replace('{y}', String(y));
    if (window.OverwatchTheaterProjection && window.OverwatchTheaterProjection.proxiedTileUrl) {
      return window.OverwatchTheaterProjection.proxiedTileUrl(raw);
    }
    var api = String(window.ATAK_API_BASE || '').replace(/\/$/, '');
    if (/^https?:\/\//i.test(raw) && api) {
      try {
        if (new URL(raw, window.location.href).hostname !== window.location.hostname) {
          return api + '/api/atak/tiles?u=' + encodeURIComponent(raw);
        }
      } catch (e) {}
    }
    return raw;
  }

  function collectTheaterPatterns() {
    var out = [];
    var seen = {};
    function add(pattern, minZ, maxZ, label) {
      var p = String(pattern || '');
      if (!p || seen[p]) return;
      seen[p] = true;
      out.push({
        pattern: p,
        minZ: Math.max(0, minZ != null ? Number(minZ) : 0),
        maxZ: Math.max(0, maxZ != null ? Number(maxZ) : 5),
        label: label || 'Fond'
      });
    }
    var cfg = window.ATAK_MAP_CONFIG || {};
    if (cfg.tilePattern) {
      add(cfg.tilePattern, cfg.minZoom, cfg.maxZoom != null ? cfg.maxZoom : 5, 'Plan');
    }
    if (window.ATAKAerial && typeof window.ATAKAerial.resolveLayers === 'function') {
      window.ATAKAerial.resolveLayers(cfg).forEach(function (layer) {
        if (!layer || !layer.spec || !layer.spec.tilePattern) return;
        add(
          layer.spec.tilePattern,
          layer.spec.minZoom,
          layer.spec.maxZoom,
          layer.label || layer.id || 'Calque'
        );
      });
    }
    return out;
  }

  function listUrlsForPattern(entry, maxZCap) {
    var urls = [];
    var maxZ = Math.min(entry.maxZ, maxZCap != null ? maxZCap : entry.maxZ);
    var z;
    for (z = entry.minZ; z <= maxZ; z++) {
      var n = Math.pow(2, z);
      var x;
      var y;
      for (x = 0; x < n; x++) {
        for (y = 0; y < n; y++) {
          urls.push(tileUrlFromPattern(entry.pattern, z, x, y));
        }
      }
    }
    return urls;
  }

  /**
   * Télécharge les fonds du théâtre dans le cache navigateur.
   * options: { onProgress(done, total, phase), signal, concurrency, maxTiles }
   */
  function prefetchTheater(options) {
    options = options || {};
    var onProgress = typeof options.onProgress === 'function' ? options.onProgress : function () {};
    var signal = options.signal || null;
    var concurrency = Math.max(2, Math.min(8, Number(options.concurrency) || 5));
    var maxTiles = Math.max(200, Number(options.maxTiles) || 4200);
    var entries = collectTheaterPatterns();
    if (!entries.length) {
      return Promise.reject(new Error('Aucun fond de carte à télécharger.'));
    }

    var maxZCap = 7;
    var urls = [];
    function rebuild() {
      urls = [];
      entries.forEach(function (entry) {
        listUrlsForPattern(entry, maxZCap).forEach(function (u) { urls.push(u); });
      });
      // Dédupliquer
      var uniq = {};
      urls = urls.filter(function (u) {
        if (uniq[u]) return false;
        uniq[u] = true;
        return true;
      });
    }
    rebuild();
    while (urls.length > maxTiles && maxZCap > 3) {
      maxZCap -= 1;
      rebuild();
    }

    var total = urls.length;
    var done = 0;
    var ok = 0;
    var failed = 0;
    var idx = 0;
    onProgress(0, total, 'start');

    function aborted() {
      return !!(signal && signal.aborted);
    }

    function next() {
      if (aborted()) return Promise.resolve({ cancelled: true, done: done, total: total, ok: ok, failed: failed });
      if (idx >= urls.length) {
        return Promise.resolve({ cancelled: false, done: done, total: total, ok: ok, failed: failed, maxZoom: maxZCap });
      }
      var url = urls[idx++];
      return fetchBlob(url).then(function (blob) {
        done += 1;
        if (blob) ok += 1;
        else failed += 1;
        onProgress(done, total, 'progress');
        return next();
      });
    }

    var workers = [];
    var w;
    for (w = 0; w < concurrency; w++) {
      workers.push(next());
    }
    return Promise.all(workers).then(function () {
      var cancelled = aborted();
      onProgress(done, total, cancelled ? 'cancelled' : 'done');
      return {
        cancelled: cancelled,
        done: done,
        total: total,
        ok: ok,
        failed: failed,
        maxZoom: maxZCap
      };
    });
  }

  return {
    NAME: NAME,
    fetchBlob: fetchBlob,
    loadImage: loadImage,
    getArrayBuffer: getArrayBuffer,
    putArrayBuffer: putArrayBuffer,
    ready: ready,
    prefetchTheater: prefetchTheater,
    collectTheaterPatterns: collectTheaterPatterns
  };
})();
