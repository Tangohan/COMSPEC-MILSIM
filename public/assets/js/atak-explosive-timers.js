/* COMSPEC ATAK — charges à retardement (minuterie ACE) */
window.ATAKExplosiveTimers = (function () {
  var lastItems = [];
  var tickTimer = null;
  var fetchedAt = 0;

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
  function liveRemaining(item) {
    if (!item || item.status !== 'armed') return 0;
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

  function render() {
    var el = document.getElementById('atak-charges-list');
    if (!el) return;
    var items = lastItems || [];
    if (items.length === 0) {
      el.innerHTML = '<div class="atak-empty-state">' +
        '<div class="atak-empty-state-icon" aria-hidden="true">◉</div>' +
        '<p class="atak-empty-state-title">Aucune charge à retardement</p>' +
        '<p class="atak-empty-state-text">Les explosifs posés avec une minuterie apparaissent ici, avec leurs coordonnées et le temps restant.</p>' +
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
      var urgent = armed && remaining <= 15;
      var cls = 'atak-charge-card';
      if (armed) cls += ' atak-charge-card--armed';
      if (urgent) cls += ' atak-charge-card--urgent';
      if (!armed) cls += ' atak-charge-card--done';
      var label = item.magazine_label || 'Charge';
      var author = item.author || 'Terrain';
      var x = item.pos_x != null ? item.pos_x : '';
      var y = item.pos_y != null ? item.pos_y : '';
      return '<article class="' + cls + '" data-x="' + esc(x) + '" data-y="' + esc(y) + '">' +
        '<header class="atak-charge-card-head">' +
        '<strong>' + esc(label) + '</strong>' +
        '<span class="atak-charge-pill">' + esc(item.status_label || '') + '</span>' +
        '</header>' +
        '<p class="atak-charge-meta">Posée par ' + esc(author) + '</p>' +
        '<p class="atak-charge-line"><span class="atak-charge-k">Coordonnées</span> ' + esc(coordsLabel(item)) + '</p>' +
        '<p class="atak-charge-line"><span class="atak-charge-k">Délai programmé</span> ' + esc(formatDuration(item.fuse_seconds)) + '</p>' +
        '<p class="atak-charge-line atak-charge-remain"><span class="atak-charge-k">Temps restant</span> ' +
        (armed ? esc(formatDuration(remaining)) : '—') + '</p>' +
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
    if (window.ATAKMap && typeof window.ATAKMap.setExplosiveTimersOnMap === 'function') {
      window.ATAKMap.setExplosiveTimersOnMap(items.map(function (item) {
        return Object.assign({}, item, { remaining_seconds: liveRemaining(item) });
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
