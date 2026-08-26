/* COMSPEC ATAK - API client (Full PHP mode: polling, no Socket.IO) */
window.ATAKSocket = (function () {
  var mapId = 1;
  var apiBase = null;
  var pauseUntil = 0;
  var unavailableListeners = [];
  var deferredListeners = [];
  var warnedUnavailable = false;
  var lastPauseKind = '';
  var lastDeferred = false;
  var remoteDeferred = false;
  var failStreak = 0;
  var okStreak = 0;
  var backoffStep = 0;
  var SEND_BACKOFF_LADDER_SEC = [45, 75, 150, 300, 600];
  var SEND_FAIL_STREAK_TO_ENTER = 3;
  var SEND_OK_STREAK_TO_STEP_DOWN = 2;
  var HUD_DEFERRED_LABEL = 'Différé · mauvaise connexion';
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

  function isMutatingMethod(method) {
    var m = String(method || 'GET').toUpperCase();
    return m !== 'GET' && m !== 'HEAD' && m !== 'OPTIONS';
  }

  /* Une pause ne doit jamais couper les lectures (effectifs, marqueurs, couverture…).
     Sinon un 503 isole le poste : toutes les lectures suivantes deviennent un faux refus. */
  function shouldShortCircuitPaused(url, method) {
    if (!isOurApiUrl(url) || isHeartbeatUrl(url)) return false;
    if (!isApiPaused()) return false;
    return isMutatingMethod(method);
  }

  function isApiPaused() {
    return Date.now() < pauseUntil;
  }

  function currentLadderSec() {
    if (backoffStep <= 0) return 0;
    var i = Math.max(0, Math.min(SEND_BACKOFF_LADDER_SEC.length - 1, backoffStep - 1));
    return SEND_BACKOFF_LADDER_SEC[i];
  }

  function isDeferred() {
    return isApiPaused() || remoteDeferred || backoffStep >= 1;
  }

  function emitDeferred() {
    var now = isDeferred();
    if (now === lastDeferred) return;
    lastDeferred = now;
    deferredListeners.forEach(function (fn) {
      try { fn(now); } catch (e) {}
    });
  }

  function applyPause(sec) {
    var n = Math.max(1, Number(sec) || 8);
    pauseUntil = Date.now() + n * 1000;
    unavailableListeners.forEach(function (fn) {
      try { fn(n); } catch (e) {}
    });
    emitDeferred();
  }

  function noteUnavailable(retrySec, kind) {
    lastPauseKind = kind || lastPauseKind || 'unavailable';
    okStreak = 0;
    failStreak += 1;

    if (backoffStep > 0) {
      if (backoffStep < SEND_BACKOFF_LADDER_SEC.length) backoffStep += 1;
    } else if (failStreak >= SEND_FAIL_STREAK_TO_ENTER) {
      backoffStep = 1;
    }

    var sec;
    if (backoffStep > 0) {
      sec = currentLadderSec();
      var hinted = Math.max(10, Number(retrySec) || 0);
      if (hinted > sec) sec = Math.min(600, hinted);
    } else {
      sec = kind === 'forbidden' ? Math.max(15, Number(retrySec) || 20) : Math.max(8, Number(retrySec) || 10);
      sec = Math.min(sec, 30);
    }
    applyPause(sec);

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

  function noteSendSuccess() {
    failStreak = 0;
    if (backoffStep <= 0) {
      okStreak = 0;
      pauseUntil = 0;
      warnedUnavailable = false;
      lastPauseKind = '';
      emitDeferred();
      return;
    }
    okStreak += 1;
    if (okStreak < SEND_OK_STREAK_TO_STEP_DOWN) {
      applyPause(currentLadderSec());
      return;
    }
    okStreak = 0;
    backoffStep -= 1;
    if (backoffStep <= 0) {
      backoffStep = 0;
      pauseUntil = 0;
      warnedUnavailable = false;
      lastPauseKind = '';
      emitDeferred();
      return;
    }
    applyPause(currentLadderSec());
  }

  function noteRemoteDeferred(on) {
    remoteDeferred = !!on;
    emitDeferred();
  }

  function onApiUnavailable(fn) {
    if (typeof fn === 'function') unavailableListeners.push(fn);
  }

  function onDeferredChange(fn) {
    if (typeof fn === 'function') deferredListeners.push(fn);
  }

  if (nativeFetch) {
    window.fetch = function (input, init) {
      var url = requestUrl(input);
      var method = requestMethod(input, init);
      var ours = isOurApiUrl(url);
      var heartbeat = isHeartbeatUrl(url);
      if (shouldShortCircuitPaused(url, method)) {
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
        if (ours && res && !heartbeat && (res.status === 403 || res.status === 429 || res.status === 503 || res.status === 0)) {
          var retry = res.status === 403 ? 20 : 30;
          try {
            var header = res.headers.get('Retry-After');
            if (header) retry = Math.max(10, parseInt(header, 10) || retry);
          } catch (e) {}
          noteUnavailable(retry, res.status === 403 ? 'forbidden' : 'unavailable');
        } else if (ours && res && res.ok && (isCoreRosterUrl(url) || heartbeat)) {
          noteSendSuccess();
        }
        return res;
      }).catch(function (err) {
        if (ours && !heartbeat && isMutatingMethod(method)) {
          noteUnavailable(8, 'unavailable');
        }
        throw err;
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
    isDeferred: isDeferred,
    onApiUnavailable: onApiUnavailable,
    onDeferredChange: onDeferredChange,
    noteRemoteDeferred: noteRemoteDeferred,
    getBackoffSec: currentLadderSec,
    HUD_DEFERRED_LABEL: HUD_DEFERRED_LABEL
  };
})();
