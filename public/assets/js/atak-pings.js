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
    return 'Opérateur';
  }

  function fetchPings() {
    if (!isNodeConfigured()) return;
    var url = getApiBase() + '/api/pings?mapId=' + getMapId();
    fetch(url, { credentials: 'include' })
      .then(function (r) {
        if (!r.ok) {
          var msg = r.status === 401
            ? 'Session expirée — reconnectez-vous pour voir les pings.'
            : r.status === 403
              ? 'Vous n’avez pas l’autorisation d’accéder aux pings.'
              : 'Impossible de charger les pings pour le moment.';
          if (window.ATAKShowError) window.ATAKShowError(msg);
          if (window.ATAKLastPingsError) window.ATAKLastPingsError(msg);
          throw new Error('Pings:');
        }
        return r.json();
      })
      .then(function (data) {
        var list = Array.isArray(data) ? data : [];
        var el = document.getElementById('atak-pings-list');
        if (el) {
          if (list.length === 0) {
            el.innerHTML = '<div class="atak-empty-state">' +
              '<div class="atak-empty-state-icon" aria-hidden="true">◎</div>' +
              '<p class="atak-empty-state-title">Aucun ping</p>' +
              '<p class="atak-empty-state-text">Clic droit sur la carte → Envoyer un ping.</p></div>';
          } else {
            el.innerHTML = list.map(formatPing).join('');
            bindPingClicks();
          }
        }
        if (window.ATAKLastPingsError) window.ATAKLastPingsError(null);
      })
      .catch(function (err) {
        if (window.ATAKShowError && (!err.message || err.message.indexOf('Pings:') !== 0)) window.ATAKShowError('Impossible de charger les pings.');
      });
  }

  function formatPing(p) {
    var time = p.created_at ? p.created_at.replace('T', ' ').substring(11, 19) : '';
    var gx = p.pos_x != null ? Math.round(Number(p.pos_x)) : '—';
    var gy = p.pos_y != null ? Math.round(Number(p.pos_y)) : '—';
    return '<div class="atak-ping-item" data-x="' + (p.pos_x || '') + '" data-y="' + (p.pos_y || '') + '">' +
      '<strong>' + escapeHtml(p.author || '') + '</strong> ' + escapeHtml(p.message || '') +
      ' <span style="color:var(--atak-muted)">' + time + ' · grille ' + gx + ' / ' + gy + '</span></div>';
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function appendPing(ping) {
    var el = document.getElementById('atak-pings-list');
    if (el) {
      var empty = el.querySelector('.atak-empty-state, .atak-muted');
      if (empty) empty.remove();
      el.insertAdjacentHTML('afterbegin', formatPing(ping));
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
      if (window.ATAKShowError) window.ATAKShowError('Liaison Tacmap indisponible pour envoyer un ping.');
      return;
    }
    var author = getAuthor();
    var payload = { mapId: getMapId(), author: author, pos_x: posX, pos_y: posY, message: message || '' };

    // Toujours passer par l’API HTTP (mode PHP : pas de bus temps réel).
    fetch(getApiBase() + '/api/pings', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) {
      if (!r.ok) {
        if (window.ATAKShowError) window.ATAKShowError('Impossible d’envoyer le ping.');
        return null;
      }
      return r.json().catch(function () { return null; });
    }).then(function (row) {
      if (row && row.pos_x != null) {
        appendPing(row);
      } else {
        // Affichage immédiat même si la réponse est minimale
        appendPing({
          author: author,
          message: message || '',
          pos_x: posX,
          pos_y: posY,
          created_at: new Date().toISOString()
        });
        fetchPings();
      }
      if (window.ATAKShowNotification) window.ATAKShowNotification('Ping envoyé.');
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible d’envoyer le ping.');
    });
  }

  // Le clic droit est géré par ATAKContextMenu (menu marqueur / ping / trait / commentaire).

  return { appendPing: appendPing, fetchPings: fetchPings, createPingAt: createPingAt };
})();
