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
  /* Un 429 ou un 403 est entré dans l'épisode de pause en cours : échelle longue jusqu'à sa sortie. */
  var throttled = false;
  var SEND_BACKOFF_LADDER_SEC = [45, 75, 150, 300, 600];
  /* Panne (Athena injoignable, 503, maintenance) : paliers courts plafonnés à 45 s (60 s avec la gigue) et sortie au
     premier succès (NET-006). L'échelle longue ci-dessus et sa descente d'un palier tous les deux succès restent
     réservées au frein demandé par Athena (429, 403). Un Retry-After plus long est toujours respecté. */
  var OUTAGE_BACKOFF_LADDER_SEC = [10, 20, 30, 45];
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

  /* Engins, météo, caméras… : un refus isolé ne doit pas crier que tout le poste est coupé.
     Les effectifs qui répondent ensuite réinitialisaient l’avertissement, donc le bandeau
     revenait toutes les quelques secondes. */
  function countsTowardUnavailable(url, method) {
    if (!isOurApiUrl(url) || isHeartbeatUrl(url)) return false;
    if (isMutatingMethod(method)) return true;
    return isCoreRosterUrl(url);
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

  function currentLadder() {
    return throttled ? SEND_BACKOFF_LADDER_SEC : OUTAGE_BACKOFF_LADDER_SEC;
  }

  function currentLadderSec() {
    if (backoffStep <= 0) return 0;
    var ladder = currentLadder();
    var i = Math.max(0, Math.min(ladder.length - 1, backoffStep - 1));
    return ladder[i];
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

  /* Pause allongée d'une gigue tirée entre 0 et jitterMaxSec (par défaut 20 % du délai), jamais plus courte que
     demandé : après une panne commune, les postes ne redemandent plus tous à la même seconde. */
  function applyPause(sec, jitterMaxSec) {
    var n = Math.max(1, Number(sec) || 8);
    var jitterSec = jitterMaxSec == null ? n * 0.2 : Math.max(0, Number(jitterMaxSec) || 0);
    pauseUntil = Date.now() + n * 1000 + Math.floor(Math.random() * (jitterSec * 1000 + 1));
    unavailableListeners.forEach(function (fn) {
      try { fn(n); } catch (e) {}
    });
    emitDeferred();
  }

  function noteUnavailable(retrySec, kind) {
    lastPauseKind = kind || lastPauseKind || 'unavailable';
    okStreak = 0;
    failStreak += 1;
    if (kind === 'forbidden' || kind === 'throttled') throttled = true;

    if (backoffStep > 0) {
      if (backoffStep < currentLadder().length) backoffStep += 1;
    } else if (failStreak >= SEND_FAIL_STREAK_TO_ENTER) {
      backoffStep = 1;
    }

    var sec;
    var jitterSec = null;
    if (backoffStep > 0) {
      sec = currentLadderSec();
      var hinted = Math.max(10, Number(retrySec) || 0);
      var asked = hinted > sec;
      if (asked) sec = Math.min(600, hinted);
      /* Gigue : palier de panne → jusqu'à la moitié du palier (3 à 15 s) ; délai demandé par Athena (maintenance,
         déjà étalée par le site) → 20 %, 15 s au plus ; frein (429, 403) → 20 %. */
      if (!throttled) jitterSec = asked ? Math.min(15, sec / 5) : Math.min(15, Math.max(3, sec / 2));
    } else {
      sec = kind === 'forbidden' ? Math.max(15, Number(retrySec) || 20) : Math.max(8, Number(retrySec) || 10);
      sec = Math.min(sec, 30);
    }
    applyPause(sec, jitterSec);

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
      throttled = false;
      pauseUntil = 0;
      warnedUnavailable = false;
      lastPauseKind = '';
      emitDeferred();
      return;
    }
    /* Panne : ce succès prouve qu'Athena répond, tout repart maintenant. Avant, la pause entière était réappliquée
       et il fallait deux succès par palier : la carte restait figée jusqu'à plusieurs minutes après une courte panne
       (atakPoll ne lit rien pendant la pause, NET-006). Frein (429, 403) : un palier de moins tous les deux succès,
       avec la pause réappliquée entre les deux, comme avant. */
    okStreak += 1;
    if (throttled && okStreak < SEND_OK_STREAK_TO_STEP_DOWN) {
      applyPause(currentLadderSec());
      return;
    }
    okStreak = 0;
    backoffStep = throttled ? backoffStep - 1 : 0;
    if (backoffStep <= 0) {
      backoffStep = 0;
      throttled = false;
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
        if (ours && res && countsTowardUnavailable(url, method) && (res.status === 403 || res.status === 429 || res.status === 503 || res.status === 0)) {
          var retry = res.status === 403 ? 20 : 30;
          try {
            var header = res.headers.get('Retry-After');
            if (header) retry = Math.max(10, parseInt(header, 10) || retry);
          } catch (e) {}
          /* 429 : frein demandé par Athena (échelle longue). 503 et réponse vide : panne (échelle courte). */
          if (res.status === 429) throttled = true;
          noteUnavailable(retry, res.status === 403 ? 'forbidden' : 'unavailable');
        } else if (ours && res && res.ok && (isCoreRosterUrl(url) || heartbeat)) {
          noteSendSuccess();
        }
        return res;
      }).catch(function (err) {
        if (ours && countsTowardUnavailable(url, method)) {
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
