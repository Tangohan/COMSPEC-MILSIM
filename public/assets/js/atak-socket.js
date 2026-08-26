/* COMSPEC ATAK - API client (Full PHP mode: polling, no Socket.IO) */
window.ATAKSocket = (function () {
  var mapId = 1;
  var apiBase = null;
  var pauseUntil = 0;
  var unavailableListeners = [];
  var warnedUnavailable = false;
  var lastPauseKind = '';
  var nativeFetch = typeof window.fetch === 'function' ? window.fetch.bind(window) : null;

  function getApiBase() {
    if (apiBase !== undefined && apiBase !== null) return apiBase;
    if (window.ATAK_API_BASE !== undefined && window.ATAK_API_BASE !== null && String(window.ATAK_API_BASE).trim() !== '') {
      apiBase = String(window.ATAK_API_BASE).replace(/\/$/, '');
      return apiBase;
    }
    var path = window.location.pathname || '';
    var atakIdx = path.indexOf('/atak');
    var basePath = atakIdx >= 0 ? path.substring(0, atakIdx) : path.replace(/\/[^/]*$/, '');
    apiBase = window.location.origin + (basePath || '');
    return apiBase;
  }

  function isOurApiUrl(url) {
    if (!url) return false;
    var abs = String(url);
    if (abs.charAt(0) === '/') {
      return abs.indexOf('/api/') !== -1;
    }
    var base = getApiBase();
    return abs.indexOf(base + '/api/') === 0 || /\/api\//.test(abs);
  }

  function isHeartbeatUrl(url) {
    var s = String(url || '');
    return /\/api\/atak\/ping(?:\?|$)/.test(s) || /\/api\/health(?:\?|$)/.test(s);
  }

  function isCoreRosterUrl(url) {
    var s = String(url || '');
    return /\/api\/units(?:\?|$)/.test(s);
  }

  function requestMethod(input, init) {
    if (init && init.method) return String(init.method).toUpperCase();
    if (input && typeof input !== 'string' && input.method) return String(input.method).toUpperCase();
    return 'GET';
  }

  function requestUrl(input) {
    if (typeof input === 'string') return input;
    if (input && input.url) return String(input.url);
    return '';
  }

  function isApiPaused() {
    return Date.now() < pauseUntil;
  }

  function noteUnavailable(retrySec, kind) {
    var sec = Math.max(10, Number(retrySec) || 20);
    if (kind === 'forbidden') sec = Math.max(20, sec);
    pauseUntil = Date.now() + sec * 1000;
    lastPauseKind = kind || lastPauseKind || 'unavailable';
    unavailableListeners.forEach(function (fn) {
      try { fn(sec); } catch (e) {}
    });
    if (warnedUnavailable) return;
    warnedUnavailable = true;
    var msg = lastPauseKind === 'forbidden'
      ? 'Accès au poste momentanément refusé. Les mises à jour reprendront toutes seules.'
      : 'Le poste n’atteint pas ses données pour le moment. Les mises à jour reprendront toutes seules.';
    window.setTimeout(function () {
      if (window.ATAKShowError) {
        window.ATAKShowError(msg);
      }
    }, 0);
  }

  function onApiUnavailable(fn) {
    if (typeof fn === 'function') unavailableListeners.push(fn);
  }

  if (nativeFetch) {
    window.fetch = function (input, init) {
      var url = requestUrl(input);
      var ours = isOurApiUrl(url);
      var heartbeat = isHeartbeatUrl(url);
      if (ours && isApiPaused() && !heartbeat) {
        var remain = Math.max(1, Math.ceil((pauseUntil - Date.now()) / 1000));
        return Promise.resolve(new Response(JSON.stringify({
          ok: false,
          paused: true,
          error: lastPauseKind === 'forbidden' ? 'forbidden' : 'database_unavailable',
          message: 'Service temporairement indisponible. Réessayez dans un instant.'
        }), {
          status: lastPauseKind === 'forbidden' ? 403 : 503,
          headers: {
            'Content-Type': 'application/json',
            'Retry-After': String(remain)
          }
        }));
      }
      return nativeFetch(input, init).then(function (res) {
        if (ours && res && !heartbeat && (res.status === 403 || res.status === 429 || res.status === 503)) {
          var retry = res.status === 403 ? 20 : 30;
          try {
            var header = res.headers.get('Retry-After');
            if (header) retry = Math.max(10, parseInt(header, 10) || retry);
          } catch (e) {}
          noteUnavailable(retry, res.status === 403 ? 'forbidden' : 'unavailable');
        } else if (ours && res && res.ok && isCoreRosterUrl(url)) {
          pauseUntil = 0;
          warnedUnavailable = false;
          lastPauseKind = '';
        }
        return res;
      });
    };
  }

  function connect(options) {
    options = options || {};
    mapId = options.mapId != null ? options.mapId : 1;
    // Mode PHP : pas de WebSocket — on signale quand même « prêt » pour démarrer le polling.
    if (options.onConnect) options.onConnect();
    return null;
  }

  function emit(/* event, data */) {
    // Pas de bus temps réel en mode PHP ; les modules passent par l’API HTTP.
  }

  function isConnected() {
    // false → les modules (ping, tchat…) utilisent POST/fetch au lieu d’emit (no-op).
    return false;
  }

  function getMapId() { return mapId; }

  function setMapId(id) {
    var n = Number(id);
    mapId = (id != null && !isNaN(n) && n > 0) ? n : 1;
  }

  return {
    connect: connect,
    emit: emit,
    isConnected: isConnected,
    getMapId: getMapId,
    setMapId: setMapId,
    getSocket: function () { return null; },
    getApiBase: getApiBase,
    isApiPaused: isApiPaused,
    onApiUnavailable: onApiUnavailable
  };
})();
