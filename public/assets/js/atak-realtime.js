/* ATAK Overwatch — SSE positions/chat/alertes avec polling HTTP conservé en secours. */
(function () {
  'use strict';
  if (!window.ATAK_OVERWATCH_BETA || !window.EventSource || window.__ATAK_REALTIME_BOOTSTRAPPED__) return;
  window.__ATAK_REALTIME_BOOTSTRAPPED__ = true;

  var stream = null;
  var reconnectTimer = null;
  var retryMs = 3000;
  var maxRetryMs = 30000;
  var knownChatIds = {};

  function mapId() {
    return window.ATAKSocket && typeof window.ATAKSocket.getMapId === 'function'
      ? window.ATAKSocket.getMapId() : 1;
  }

  function announce(state) {
    window.dispatchEvent(new CustomEvent('atak:realtime-state', { detail: { state: state } }));
  }

  function setRealtime(active) {
    if (window.ATAKPolling && typeof window.ATAKPolling.setRealtimeActive === 'function') {
      window.ATAKPolling.setRealtimeActive(active);
    }
  }

  function parse(event) {
    try { return JSON.parse(event.data); } catch (ignore) { return null; }
  }

  function onUnits(event) {
    var rows = parse(event);
    if (Array.isArray(rows) && window.ATAKUnits && typeof window.ATAKUnits.setUnits === 'function') {
      window.ATAKUnits.setUnits(rows);
    }
  }

  function onChat(event) {
    var rows = parse(event);
    if (!Array.isArray(rows) || !window.ATAKChat) return;
    var cached = typeof window.ATAKChat.getCachedMessages === 'function' ? window.ATAKChat.getCachedMessages() : [];
    cached.forEach(function (row) { if (row && Number(row.id) > 0) knownChatIds[String(row.id)] = true; });
    rows.forEach(function (row) {
      var id = row && Number(row.id);
      if (id > 0 && knownChatIds[String(id)]) return;
      if (id > 0) knownChatIds[String(id)] = true;
      if (typeof window.ATAKChat.appendMessage === 'function') window.ATAKChat.appendMessage(row);
    });
  }

  function onAlerts(event) {
    var payload = parse(event);
    window.dispatchEvent(new CustomEvent('atak:tactical-alerts-updated', { detail: payload || {} }));
  }

  function connect() {
    if (stream) stream.close();
    var base = String(window.ATAK_API_BASE || '').replace(/\/$/, '');
    stream = new EventSource(base + '/api/atak/stream?mapId=' + encodeURIComponent(mapId()), { withCredentials: true });
    announce('connecting');
    stream.addEventListener('units', onUnits);
    stream.addEventListener('chat', onChat);
    stream.addEventListener('alerts', onAlerts);
    stream.onopen = function () {
      retryMs = 3000;
      setRealtime(true);
      announce('connected');
    };
    stream.onerror = function () {
      if (stream) stream.close();
      stream = null;
      setRealtime(false);
      announce('fallback');
      if (reconnectTimer !== null) clearTimeout(reconnectTimer);
      reconnectTimer = setTimeout(connect, retryMs);
      retryMs = Math.min(maxRetryMs, retryMs * 2);
    };
  }

  window.addEventListener('atak:map-changed', function () {
    retryMs = 3000;
    connect();
  });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', connect); else connect();
}());
