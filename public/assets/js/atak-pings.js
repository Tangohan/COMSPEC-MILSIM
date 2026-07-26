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

  function askConfirm(message) {
    if (window.ATAKContextMenu && typeof window.ATAKContextMenu.confirmAction === 'function') {
      return window.ATAKContextMenu.confirmAction(message);
    }
    return Promise.resolve(window.confirm(message));
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
            bindPingList();
          }
        }
        // Même couche que TACMAP : tracer tous les pings sur la carte (pas seulement la liste).
        if (window.ATAKMap && typeof window.ATAKMap.setPingsOnMap === 'function') {
          window.ATAKMap.setPingsOnMap(list);
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
    var id = p.id != null ? String(p.id) : '';
    return '<div class="atak-ping-item" data-id="' + escapeHtml(id) + '" data-x="' + (p.pos_x || '') + '" data-y="' + (p.pos_y || '') + '">' +
      '<button type="button" class="atak-ping-item__main" data-focus-ping>' +
      '<strong>' + escapeHtml(p.author || '') + '</strong> ' + escapeHtml(p.message || '') +
      ' <span style="color:var(--atak-muted)">' + time + ' · grille ' + gx + ' / ' + gy + '</span>' +
      '</button>' +
      (id
        ? '<button type="button" class="atak-ping-item__del" data-delete-ping="' + escapeHtml(id) + '" title="Supprimer">×</button>'
        : '') +
      '</div>';
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
      bindPingList();
    }
    if (ping.pos_x != null && ping.pos_y != null && window.ATAKMap && window.ATAKMap.addTemporaryPingMarker) {
      window.ATAKMap.addTemporaryPingMarker(ping.pos_x, ping.pos_y, ping.author, ping.message || '', ping.id);
    }
  }

  function bindPingList() {
    var el = document.getElementById('atak-pings-list');
    if (!el || el._bound) return;
    el._bound = true;
    el.addEventListener('click', function (e) {
      var delBtn = e.target.closest('[data-delete-ping]');
      if (delBtn) {
        e.preventDefault();
        e.stopPropagation();
        var delId = delBtn.getAttribute('data-delete-ping');
        if (!delId) return;
        askConfirm('Supprimer ce ping ?').then(function (ok) {
          if (!ok) return;
          deletePing(delId).then(function () {
            if (window.ATAKShowNotification) window.ATAKShowNotification('Ping supprimé.');
          }).catch(function () {
            if (window.ATAKShowError) window.ATAKShowError('Impossible de supprimer le ping.');
          });
        });
        return;
      }
      var focusBtn = e.target.closest('[data-focus-ping]');
      if (focusBtn) {
        var row = focusBtn.closest('.atak-ping-item');
        if (!row) return;
        var x = row.getAttribute('data-x');
        var y = row.getAttribute('data-y');
        if (x && y && window.ATAKMap && window.ATAKMap.centerOn) {
          window.ATAKMap.centerOn(parseFloat(y), parseFloat(x));
        }
      }
    });
  }

  function deletePing(id) {
    if (!isNodeConfigured()) {
      return Promise.reject(new Error('no-api'));
    }
    var pingId = String(id || '');
    if (!pingId || pingId.indexOf('live_') === 0 || pingId.indexOf('p_') === 0) {
      if (window.ATAKMap && window.ATAKMap.removeTemporaryPingMarker) {
        window.ATAKMap.removeTemporaryPingMarker(pingId);
      }
      return Promise.resolve(true);
    }
    return fetch(getApiBase() + '/api/pings/' + encodeURIComponent(pingId), {
      method: 'DELETE',
      credentials: 'include'
    }).then(function (r) {
      if (!r.ok) throw new Error('delete');
      if (window.ATAKMap && window.ATAKMap.removeTemporaryPingMarker) {
        window.ATAKMap.removeTemporaryPingMarker(pingId);
      }
      fetchPings();
      return true;
    });
  }

  function createPingAt(posX, posY, message, kind) {
    if (!isNodeConfigured()) {
      if (window.ATAKShowError) window.ATAKShowError('Liaison Tacmap indisponible pour envoyer un ping.');
      return;
    }
    var author = getAuthor();
    var kindKey = String(kind || 'info').toLowerCase();
    var kindLabels = {
      contact: 'Contact',
      hostile: 'Hostile',
      jackpot: 'JACKPOT',
      medical: 'Médical',
      rally: 'Ralliement',
      objective: 'Objectif',
      info: 'Info'
    };
    var kindLabel = kindLabels[kindKey] || 'Info';
    var body = String(message || '').trim();
    if (!/^\s*\[[^\]]+\]/.test(body)) {
      body = '[' + kindLabel + ']' + (body ? ' ' + body : '');
    }
    var payload = { mapId: getMapId(), author: author, pos_x: posX, pos_y: posY, message: body };

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
          message: body,
          pos_x: posX,
          pos_y: posY,
          created_at: new Date().toISOString()
        });
        fetchPings();
      }
      if (window.ATAKShowNotification) window.ATAKShowNotification('Ping ' + kindLabel + ' envoyé.');
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible d’envoyer le ping.');
    });
  }

  // Le clic droit est géré par ATAKContextMenu (menu marqueur / ping / trait / commentaire).

  return {
    appendPing: appendPing,
    fetchPings: fetchPings,
    createPingAt: createPingAt,
    deletePing: deletePing
  };
})();
