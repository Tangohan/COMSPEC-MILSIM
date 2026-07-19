/* COMSPEC ATAK - API client (Full PHP mode: polling, no Socket.IO) */
window.ATAKSocket = (function () {
  var mapId = 1;
  var apiBase = null;

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
    mapId = id != null ? Number(id) : 1;
  }

  return {
    connect: connect,
    emit: emit,
    isConnected: isConnected,
    getMapId: getMapId,
    setMapId: setMapId,
    getSocket: function () { return null; },
    getApiBase: getApiBase
  };
})();
