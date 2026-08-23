/* COMSPEC ATAK — charges ACE (minuterie et déclenchement depuis le poste de commandement) */
window.ATAKExplosiveTimers = (function () {
  var lastItems = [];
  var tickTimer = null;
  var fetchedAt = 0;
  var canCommandDetonate = false;
  var pendingConfirmId = 0;
  var pendingConfirmTimer = null;
  var sendingIds = {};

  function getApiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : (window.ATAK_API_BASE || '');
  }
  function getMapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
  function formatDuration(sec) {
    sec = Math.max(0, Math.floor(Number(sec) || 0));
    var h = Math.floor(sec / 3600);
    var m = Math.floor((sec % 3600) / 60);
    var s = sec % 60;
    if (h > 0) return h + ' h ' + m + ' min';
    if (m > 0) return m + ' min ' + (s < 10 ? '0' : '') + s + ' s';
    return s + ' s';
  }
  function hasCountdown(item) {
    return !!(item && item.has_countdown && item.status === 'armed');
  }
  function liveRemaining(item) {
    if (!hasCountdown(item)) return null;
    var base = Number(item.remaining_seconds);
    if (isNaN(base)) base = 0;
    var elapsed = (Date.now() - fetchedAt) / 1000;
    return Math.max(0, Math.round(base - elapsed));
  }
  function coordsLabel(item) {
    var grid = String(item.grid_ref || '').trim();
    if (grid) return grid;
    if (item.pos_x != null && item.pos_y != null) {
      return Math.round(Number(item.pos_x)) + ' / ' + Math.round(Number(item.pos_y));
    }
    return '—';
  }
  function setBadge(count) {
    var badge = document.getElementById('atak-charges-tab-badge');
    if (!badge) return;
    if (count > 0) {
      badge.hidden = false;
      badge.textContent = String(count);
    } else {
      badge.hidden = true;
      badge.textContent = '';
    }
  }
  function showError(msg) {
    if (window.ATAKShowError) {
      window.ATAKShowError(msg);
      return;
    }
    window.alert(msg);
  }
  function clearPendingConfirm() {
    pendingConfirmId = 0;
    if (pendingConfirmTimer) {
      clearTimeout(pendingConfirmTimer);
      pendingConfirmTimer = null;
    }
  }
  function armPendingConfirm(id) {
    clearPendingConfirm();
    pendingConfirmId = id;
    pendingConfirmTimer = setTimeout(function () {
      pendingConfirmId = 0;
      pendingConfirmTimer = null;
      render();
    }, 5000);
    render();
  }
  function requestDetonate(id) {
    var base = getApiBase();
    if (!base || sendingIds[id]) return;
    sendingIds[id] = true;
    clearPendingConfirm();
    render();
    fetch(base + '/api/atak/explosive-timers/' + encodeURIComponent(id) + '/detonate', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ mapId: getMapId() })
    }).then(function (r) {
      return r.json().then(function (d) { return { ok: r.ok, data: d }; });
    }).then(function (res) {
      sendingIds[id] = false;
      if (!res.ok) {
        showError((res.data && res.data.message) || 'Impossible de déclencher cette charge pour le moment.');
        render();
        return;
      }
      lastItems = lastItems.map(function (item) {
        if (Number(item.id) !== Number(id)) return item;
        return Object.assign({}, item, res.data, { detonate_pending: true, status_label: 'Déclenchement demandé' });
      });
      render();
    }).catch(function () {
      sendingIds[id] = false;
      showError('Liaison interrompue. Réessayez dans un instant.');
      render();
    });
  }

  function render() {
    var el = document.getElementById('atak-charges-list');
    if (!el) return;
    var items = lastItems || [];
    if (items.length === 0) {
      el.innerHTML = '<div class="atak-empty-state">' +
        '<div class="atak-empty-state-icon" aria-hidden="true">◉</div>' +
        '<p class="atak-empty-state-title">Aucune charge posée</p>' +
        '<p class="atak-empty-state-text">Les explosifs posés sur le terrain apparaissent ici, avec leurs coordonnées. Une minuterie affiche le temps restant ; les autres se déclenchent à la demande.</p>' +
        '</div>';
      setBadge(0);
      if (window.ATAKMap && typeof window.ATAKMap.setExplosiveTimersOnMap === 'function') {
        window.ATAKMap.setExplosiveTimersOnMap([]);
      }
      return;
    }
    var armedCount = 0;
    el.innerHTML = items.map(function (item) {
      var remaining = liveRemaining(item);
      var armed = item.status === 'armed';
      if (armed) armedCount += 1;
      var urgent = armed && remaining != null && remaining <= 15;
      var cls = 'atak-charge-card';
      if (armed) cls += ' atak-charge-card--armed';
      if (urgent) cls += ' atak-charge-card--urgent';
      if (!armed) cls += ' atak-charge-card--done';
      if (item.detonate_pending) cls += ' atak-charge-card--pending';
      var label = item.magazine_label || 'Charge';
      var author = item.author || 'Terrain';
      var x = item.pos_x != null ? item.pos_x : '';
      var y = item.pos_y != null ? item.pos_y : '';
      var remainHtml;
      if (!armed) {
        remainHtml = '—';
      } else if (remaining != null) {
        remainHtml = esc(formatDuration(remaining));
      } else {
        remainHtml = 'À la demande';
      }
      var fuseHtml = (item.trigger_kind === 'timer' && Number(item.fuse_seconds) > 0)
        ? esc(formatDuration(item.fuse_seconds))
        : 'Aucun — déclenchement manuel';
      var actions = '';
      if (armed && canCommandDetonate) {
        var id = Number(item.id) || 0;
        if (item.detonate_pending || sendingIds[id]) {
          actions = '<p class="atak-charge-pending">Ordre envoyé — en attente du terrain</p>';
        } else if (pendingConfirmId === id) {
          actions = '<button type="button" class="atak-charge-detonate atak-charge-detonate--confirm" data-charge-id="' + esc(id) + '">Confirmer le déclenchement</button>';
        } else {
          actions = '<button type="button" class="atak-charge-detonate" data-charge-id="' + esc(id) + '">Déclencher</button>';
        }
      }
      return '<article class="' + cls + '" data-x="' + esc(x) + '" data-y="' + esc(y) + '">' +
        '<header class="atak-charge-card-head">' +
        '<strong>' + esc(label) + '</strong>' +
        '<span class="atak-charge-pill">' + esc(item.status_label || '') + '</span>' +
        '</header>' +
        '<p class="atak-charge-meta">Posée par ' + esc(author) + ' · ' + esc(item.trigger_label || 'Charge') + '</p>' +
        '<p class="atak-charge-line"><span class="atak-charge-k">Coordonnées</span> ' + esc(coordsLabel(item)) + '</p>' +
        '<p class="atak-charge-line"><span class="atak-charge-k">Délai</span> ' + fuseHtml + '</p>' +
        '<p class="atak-charge-line atak-charge-remain"><span class="atak-charge-k">Temps restant</span> ' + remainHtml + '</p>' +
        actions +
        '</article>';
    }).join('');
    setBadge(armedCount);
    el.querySelectorAll('.atak-charge-card').forEach(function (card) {
      card.addEventListener('click', function () {
        var x = parseFloat(card.getAttribute('data-x'));
        var y = parseFloat(card.getAttribute('data-y'));
        if (isNaN(x) || isNaN(y)) return;
        if (window.ATAKMap && window.ATAKMap.centerOn) {
          window.ATAKMap.centerOn(y, x);
        }
      });
    });
    el.querySelectorAll('.atak-charge-detonate').forEach(function (btn) {
      btn.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        var id = parseInt(btn.getAttribute('data-charge-id'), 10);
        if (!id) return;
        if (pendingConfirmId === id) {
          requestDetonate(id);
          return;
        }
        armPendingConfirm(id);
      });
    });
    if (window.ATAKMap && typeof window.ATAKMap.setExplosiveTimersOnMap === 'function') {
      window.ATAKMap.setExplosiveTimersOnMap(items.map(function (item) {
        var remain = liveRemaining(item);
        return Object.assign({}, item, {
          remaining_seconds: remain,
          has_countdown: remain != null
        });
      }));
    }
  }

  function fetchList() {
    var base = getApiBase();
    if (!base) return;
    fetch(base + '/api/atak/explosive-timers?mapId=' + encodeURIComponent(getMapId()), {
      credentials: 'include'
    }).then(function (r) {
      if (!r.ok) throw new Error('charges');
      return r.json();
    }).then(function (data) {
      lastItems = Array.isArray(data && data.items) ? data.items : (Array.isArray(data) ? data : []);
      canCommandDetonate = !!(data && data.can_command_detonate);
      fetchedAt = Date.now();
      render();
    }).catch(function () {
      /* silence : le prochain cycle de rafraîchissement retentera */
    });
  }

  function startTicker() {
    if (tickTimer) return;
    tickTimer = setInterval(function () {
      if (!lastItems.length) return;
      var hasArmed = lastItems.some(function (item) { return item.status === 'armed'; });
      if (hasArmed) render();
    }, 1000);
  }

  startTicker();

  return {
    fetchList: fetchList,
    refresh: fetchList
  };
})();
