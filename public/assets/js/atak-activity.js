/* COMSPEC ATAK — panneau Activité de liaison */
window.ATAKActivity = (function () {
  var listEl = null;
  var emptyEl = null;
  var metaEl = null;
  var metaCountEl = null;
  var lastId = 0;
  var pollTimer = null;
  var knownIds = {};
  var visibleCount = 0;

  function getApiBase() {
    if (window.ATAKSocket && typeof window.ATAKSocket.getApiBase === 'function') {
      return window.ATAKSocket.getApiBase();
    }
    return (window.ATAK_API_BASE || '').replace(/\/$/, '');
  }

  function getMapId() {
    if (window.ATAKSocket && typeof window.ATAKSocket.getMapId === 'function') {
      return window.ATAKSocket.getMapId();
    }
    return (window.ATAK_DEFAULT_MAP_ID != null && window.ATAK_DEFAULT_MAP_ID > 0) ? window.ATAK_DEFAULT_MAP_ID : 1;
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function typeClass(type) {
    switch (type) {
      case 'client_init': return 'atak-activity-item--init';
      case 'callsign_change': return 'atak-activity-item--callsign';
      case 'position': return 'atak-activity-item--position';
      case 'chat':
      case 'ping':
      case 'marker':
      case 'intel':
        return 'atak-activity-item--message';
      case 'nine_line':
      case 'designator':
      case 'laser':
      case 'flight':
      case 'sigint':
        return 'atak-activity-item--tactical';
      default: return '';
    }
  }

  function typeLabelFr(type) {
    switch (type) {
      case 'client_init': return 'Connexion';
      case 'callsign_change': return 'Indicatif';
      case 'position': return 'Position';
      case 'chat': return 'Tchat';
      case 'ping': return 'Ping';
      case 'marker': return 'Marqueur';
      case 'intel': return 'Renseignement';
      case 'nine_line': return '9-Line';
      case 'designator': return 'Désignateur';
      case 'laser': return 'Laser';
      case 'flight': return 'Vol';
      case 'sigint': return 'SIGINT';
      default: return 'Activité';
    }
  }

  function formatTime(iso) {
    if (!iso) return '—';
    var d = new Date(iso);
    if (isNaN(d.getTime())) return '—';
    var h = d.getUTCHours();
    var m = d.getUTCMinutes();
    var s = d.getUTCSeconds();
    return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s + ' Z';
  }

  function formatSyncNow() {
    var d = new Date();
    var h = d.getUTCHours();
    var m = d.getUTCMinutes();
    var s = d.getUTCSeconds();
    return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s + ' Z';
  }

  function ensureEls() {
    if (!listEl) listEl = document.getElementById('atak-activity-list');
    if (!emptyEl) emptyEl = document.getElementById('atak-activity-empty');
    if (!metaEl) metaEl = document.getElementById('atak-activity-meta');
    if (!metaCountEl) metaCountEl = document.getElementById('atak-activity-meta-count');
  }

  function updateChrome() {
    ensureEls();
    var badge = document.getElementById('atak-liaison-tab-badge');
    if (badge) {
      badge.textContent = visibleCount > 0 ? String(Math.min(visibleCount, 99)) : '';
      badge.hidden = visibleCount <= 0;
    }
    if (metaEl && metaCountEl) {
      if (visibleCount > 0) {
        metaEl.hidden = false;
        metaCountEl.textContent = String(visibleCount);
      } else {
        metaEl.hidden = true;
      }
    }
    var syncVal = document.getElementById('atak-chip-sync-value');
    if (syncVal) syncVal.textContent = formatSyncNow();
  }

  function renderItem(ev) {
    var type = ev.type || '';
    var li = document.createElement('li');
    li.className = 'atak-activity-item ' + typeClass(type);
    li.setAttribute('data-id', String(ev.id || ''));
    var actor = ev.actor ? '<span class="atak-activity-actor">' + escapeHtml(ev.actor) + '</span>' : '';
    li.innerHTML =
      '<span class="atak-activity-rail" aria-hidden="true"></span>' +
      '<div class="atak-activity-body">' +
        '<div class="atak-activity-top">' +
          '<span class="atak-activity-type">' + escapeHtml(typeLabelFr(type)) + '</span>' +
          '<span class="atak-activity-time">' + escapeHtml(formatTime(ev.at)) + '</span>' +
        '</div>' +
        '<div class="atak-activity-label">' + escapeHtml(ev.label || '') + '</div>' +
        actor +
      '</div>';
    return li;
  }

  function prependEvents(events) {
    ensureEls();
    if (!listEl || !events || !events.length) return;
    var fresh = [];
    for (var i = 0; i < events.length; i++) {
      var ev = events[i];
      var id = ev && ev.id != null ? Number(ev.id) : 0;
      if (!id || knownIds[id]) continue;
      knownIds[id] = true;
      if (id > lastId) lastId = id;
      fresh.push(ev);
    }
    if (!fresh.length) return;
    if (emptyEl) emptyEl.hidden = true;
    var frag = document.createDocumentFragment();
    for (var j = 0; j < fresh.length; j++) {
      frag.appendChild(renderItem(fresh[j]));
    }
    listEl.insertBefore(frag, listEl.firstChild);
    while (listEl.children.length > 80) {
      listEl.removeChild(listEl.lastChild);
    }
    visibleCount = listEl.children.length;
    updateChrome();
  }

  function fetchActivity(incremental) {
    ensureEls();
    var base = getApiBase();
    if (!base) return Promise.resolve();
    var url = base + '/api/atak/activity?mapId=' + encodeURIComponent(getMapId()) + '&limit=40';
    if (incremental && lastId > 0) {
      url += '&after=' + encodeURIComponent(lastId);
    }
    return fetch(url, { credentials: 'include' })
      .then(function (r) {
        if (!r.ok) throw new Error('activity');
        return r.json();
      })
      .then(function (data) {
        var events = (data && data.events) ? data.events : [];
        if (!incremental) {
          knownIds = {};
          lastId = 0;
          visibleCount = 0;
          if (listEl) listEl.innerHTML = '';
          if (emptyEl) emptyEl.hidden = events.length > 0;
        }
        prependEvents(events);
        if (listEl && listEl.children.length === 0 && emptyEl) {
          emptyEl.hidden = false;
          visibleCount = 0;
        } else if (listEl) {
          visibleCount = listEl.children.length;
        }
        updateChrome();
      })
      .catch(function () {
        /* silencieux : le panneau santé couvre déjà les pannes */
      });
  }

  function start() {
    ensureEls();
    fetchActivity(false);
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(function () { fetchActivity(true); }, 4000);
  }

  function refresh() {
    return fetchActivity(false);
  }

  function stop() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = null;
  }

  return {
    start: start,
    refresh: refresh,
    stop: stop,
    fetchActivity: fetchActivity
  };
})();
