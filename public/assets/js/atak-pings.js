/* COMSPEC ATAK - Pings */
window.ATAKPings = (function () {
  function getApiBase() {
    return window.ATAKSocket ? window.ATAKSocket.getApiBase() : '';
  }
  function isNodeConfigured() {
    var base = getApiBase();
    return base && base.trim() !== '';
  }

  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }

  function getAuthor() {
    var u = window.ATAK_USER;
    if (u && (u.callsign || u.displayName)) return u.callsign || u.displayName;
    return 'User';
  }

  function fetchPings() {
    if (!isNodeConfigured()) return;
    var url = getApiBase() + '/api/pings?mapId=' + getMapId();
    fetch(url, { credentials: 'include' })
      .then(function (r) {
        if (!r.ok) {
          var msg = 'Pings: ' + (r.status === 401 ? 'Non authentifié (401)' : r.status === 403 ? 'Accès refusé (403)' : 'Erreur ' + r.status);
          if (window.ATAKShowError) window.ATAKShowError(msg);
          if (window.ATAKLastPingsError) window.ATAKLastPingsError(msg);
          throw new Error(msg);
        }
        return r.json();
      })
      .then(function (data) {
        var list = Array.isArray(data) ? data : [];
        var el = document.getElementById('atak-pings-list');
        if (el) el.innerHTML = list.map(formatPing).join('');
        bindPingClicks();
        if (window.ATAKLastPingsError) window.ATAKLastPingsError(null);
      })
      .catch(function (err) {
        if (window.ATAKShowError && (!err.message || err.message.indexOf('Pings:') !== 0)) window.ATAKShowError('Impossible de charger les pings.');
      });
  }

  function formatPing(p) {
    var time = p.created_at ? p.created_at.replace('T', ' ').substring(11, 19) : '';
    return '<div class="atak-ping-item" data-x="' + (p.pos_x || '') + '" data-y="' + (p.pos_y || '') + '">' +
      '<strong>' + (p.author || '') + '</strong> ' + (p.message || '') + ' <span style="color:var(--atak-muted)">' + time + '</span></div>';
  }

  function appendPing(ping) {
    var el = document.getElementById('atak-pings-list');
    if (el) {
      el.insertAdjacentHTML('beforeend', formatPing(ping));
      bindPingClicks();
    }
    if (ping.pos_x != null && ping.pos_y != null && window.ATAKMap && window.ATAKMap.addTemporaryPingMarker) {
      window.ATAKMap.addTemporaryPingMarker(ping.pos_x, ping.pos_y, ping.author, ping.message || '');
    }
  }

  function bindPingClicks() {
    document.querySelectorAll('#atak-pings-list .atak-ping-item').forEach(function (node) {
      if (node._bound) return;
      node._bound = true;
      node.addEventListener('click', function () {
        var x = this.getAttribute('data-x');
        var y = this.getAttribute('data-y');
        if (x && y && window.ATAKMap && window.ATAKMap.centerOn) {
          window.ATAKMap.centerOn(parseFloat(y), parseFloat(x));
        }
      });
    });
  }

  function createPingAt(posX, posY, message) {
    if (!isNodeConfigured()) {
      if (window.ATAKShowError) window.ATAKShowError('Configurez l\'URL du nœud ATAK dans Admin → Configuration ATAK.');
      return;
    }
    var author = getAuthor();
    var payload = { mapId: getMapId(), author: author, pos_x: posX, pos_y: posY, message: message || '' };
    if (window.ATAKSocket && window.ATAKSocket.isConnected()) {
      window.ATAKSocket.emit('Ping', payload);
      return;
    }
    fetch(getApiBase() + '/api/pings', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) {
      if (!r.ok) {
        if (window.ATAKShowError) window.ATAKShowError('Envoi ping: ' + r.status);
        return;
      }
      fetchPings();
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible d\'envoyer le ping.');
    });
  }

  function initMapPing() {
    var map = window.ATAKMap && window.ATAKMap.getMap && window.ATAKMap.getMap();
    if (!map) return;
    map.on('contextmenu', function (e) {
      e.originalEvent.preventDefault();
      var latlng = e.latlng;
      var lat = latlng.lat;
      var lng = latlng.lng;
      var msg = window.prompt('Message du ping (optionnel)', '');
      if (msg === null) return;
      createPingAt(lng, lat, msg || '');
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { setTimeout(initMapPing, 500); });
  } else {
    setTimeout(initMapPing, 500);
  }

  return { appendPing: appendPing, fetchPings: fetchPings, createPingAt: createPingAt };
})();
