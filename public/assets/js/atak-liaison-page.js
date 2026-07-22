/* COMSPEC ATAK — page dédiée Journal de liaison */
window.ATAKLiaisonPage = (function () {
  var listEl = null;
  var emptyEl = null;
  var metaEl = null;
  var loading = false;
  var hasMore = false;
  var oldestId = 0;
  var knownIds = {};
  /** @type {Array<object>} */
  var eventsCache = [];
  var PAGE_SIZE = 40;

  function getApiBase() {
    return (window.ATAK_API_BASE || '').replace(/\/$/, '');
  }

  function getMapId() {
    var sel = document.getElementById('atak-liaison-map');
    if (sel && sel.value) {
      var n = parseInt(sel.value, 10);
      if (!isNaN(n) && n > 0) return n;
    }
    return (window.ATAK_DEFAULT_MAP_ID != null && window.ATAK_DEFAULT_MAP_ID > 0)
      ? window.ATAK_DEFAULT_MAP_ID
      : 1;
  }

  function escapeHtml(s) {
    if (window.ATAKActivity && ATAKActivity.escapeHtml) return ATAKActivity.escapeHtml(s);
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function typeClass(t) {
    return (window.ATAKActivity && ATAKActivity.typeClass) ? ATAKActivity.typeClass(t) : '';
  }
  function typeLabelFr(t) {
    return (window.ATAKActivity && ATAKActivity.typeLabelFr) ? ATAKActivity.typeLabelFr(t) : 'Activité';
  }
  function formatTime(iso) {
    return (window.ATAKActivity && ATAKActivity.formatTime) ? ATAKActivity.formatTime(iso) : '—';
  }
  function dayKeyFromIso(iso) {
    return (window.ATAKActivity && ATAKActivity.dayKeyFromIso) ? ATAKActivity.dayKeyFromIso(iso) : '';
  }
  function dayLabelFr(iso) {
    return (window.ATAKActivity && ATAKActivity.dayLabelFr) ? ATAKActivity.dayLabelFr(iso) : '';
  }

  function markSeenFromPage() {
    var max = 0;
    for (var i = 0; i < eventsCache.length; i++) {
      if ((eventsCache[i].id || 0) > max) max = eventsCache[i].id;
    }
    try {
      localStorage.setItem('atak_liaison_last_seen_m' + getMapId(), String(max));
    } catch (e) { /* ignore */ }
    if (window.ATAKActivity && typeof ATAKActivity.markSeen === 'function') {
      ATAKActivity.markSeen();
    }
  }

  function filters() {
    var qEl = document.getElementById('atak-liaison-q');
    var typeEl = document.getElementById('atak-liaison-type');
    var fromEl = document.getElementById('atak-liaison-from');
    var toEl = document.getElementById('atak-liaison-to');
    var archEl = document.getElementById('atak-liaison-archived');
    return {
      q: qEl ? String(qEl.value || '').trim() : '',
      type: typeEl ? String(typeEl.value || '') : '',
      from: fromEl ? String(fromEl.value || '') : '',
      to: toEl ? String(toEl.value || '') : '',
      archived: archEl && archEl.checked ? 'all' : ''
    };
  }

  function buildUrl(beforeId) {
    var f = filters();
    var url = getApiBase() + '/api/atak/activity?mapId=' + encodeURIComponent(getMapId())
      + '&limit=' + PAGE_SIZE + '&page=1';
    if (f.q) url += '&q=' + encodeURIComponent(f.q);
    if (f.type) url += '&type=' + encodeURIComponent(f.type);
    if (f.from) url += '&from=' + encodeURIComponent(f.from);
    if (f.to) url += '&to=' + encodeURIComponent(f.to);
    if (f.archived) url += '&archived=' + encodeURIComponent(f.archived);
    if (beforeId && beforeId > 0) url += '&before=' + encodeURIComponent(beforeId);
    return url;
  }

  function renderItem(ev) {
    var type = ev.type || '';
    var li = document.createElement('li');
    li.className = 'atak-activity-item ' + typeClass(type);
    if (ev.archived) li.className += ' atak-activity-item--archived';
    li.setAttribute('data-id', String(ev.id || ''));
    var actor = ev.actor ? '<span class="atak-activity-actor">' + escapeHtml(ev.actor) + '</span>' : '';
    var archivedTag = ev.archived
      ? '<span class="atak-activity-archived-tag">Archivé</span>'
      : '';
    li.innerHTML =
      '<span class="atak-activity-rail" aria-hidden="true"></span>' +
      '<div class="atak-activity-body">' +
        '<div class="atak-activity-top">' +
          '<span class="atak-activity-type">' + escapeHtml(typeLabelFr(type)) + '</span>' +
          '<span class="atak-activity-time">' + escapeHtml(formatTime(ev.at)) + '</span>' +
        '</div>' +
        '<div class="atak-activity-label">' + escapeHtml(ev.label || '') + '</div>' +
        actor + archivedTag +
      '</div>';
    return li;
  }

  function rebuildList() {
    if (!listEl) return;
    listEl.innerHTML = '';
    if (!eventsCache.length) {
      if (emptyEl) emptyEl.hidden = false;
      if (metaEl) metaEl.textContent = 'Aucun événement';
      return;
    }
    if (emptyEl) emptyEl.hidden = true;
    var lastDay = null;
    var frag = document.createDocumentFragment();
    for (var i = 0; i < eventsCache.length; i++) {
      var ev = eventsCache[i];
      var day = dayKeyFromIso(ev.at);
      if (day !== lastDay) {
        var h = document.createElement('li');
        h.className = 'atak-activity-day';
        h.innerHTML = '<span class="atak-activity-day-label">' + escapeHtml(dayLabelFr(ev.at)) + '</span>';
        frag.appendChild(h);
        lastDay = day;
      }
      frag.appendChild(renderItem(ev));
    }
    listEl.appendChild(frag);
    if (metaEl) {
      metaEl.textContent = eventsCache.length + ' événement' + (eventsCache.length > 1 ? 's' : '')
        + (hasMore ? ' — faites défiler pour en charger davantage' : '');
    }
  }

  function appendIncoming(events, reset) {
    if (reset) {
      knownIds = {};
      eventsCache = [];
      oldestId = 0;
    }
    var added = 0;
    for (var i = 0; i < events.length; i++) {
      var ev = events[i];
      var id = ev && ev.id != null ? Number(ev.id) : 0;
      if (!id || knownIds[id]) continue;
      knownIds[id] = true;
      eventsCache.push(ev);
      added++;
      if (!oldestId || id < oldestId) oldestId = id;
    }
    eventsCache.sort(function (a, b) {
      return (Number(b.id) || 0) - (Number(a.id) || 0)
        || String(b.at || '').localeCompare(String(a.at || ''));
    });
    // Recalculate oldest after sort (lowest id among visible)
    oldestId = 0;
    for (var j = 0; j < eventsCache.length; j++) {
      var oid = Number(eventsCache[j].id) || 0;
      if (oid && (!oldestId || oid < oldestId)) oldestId = oid;
    }
    rebuildList();
    return added;
  }

  function fetchPage(reset) {
    if (loading) return Promise.resolve();
    var base = getApiBase();
    if (!base) return Promise.resolve();
    loading = true;
    var before = reset ? 0 : oldestId;
    var url = buildUrl(before);
    var status = document.getElementById('atak-liaison-status');
    if (status) status.textContent = 'Chargement…';
    return fetch(url, { credentials: 'include' })
      .then(function (r) {
        if (!r.ok) throw new Error('activity');
        return r.json();
      })
      .then(function (data) {
        var events = (data && data.events) ? data.events : [];
        hasMore = !!(data && data.has_more);
        appendIncoming(events, !!reset);
        markSeenFromPage();
        if (status) {
          status.textContent = hasMore ? 'Défilez pour charger la suite' : 'Fin du journal';
        }
      })
      .catch(function () {
        if (status) status.textContent = 'Impossible de charger le journal pour le moment.';
      })
      .finally(function () {
        loading = false;
      });
  }

  function onScroll() {
    if (!listEl || loading || !hasMore) return;
    if (listEl.scrollTop + listEl.clientHeight >= listEl.scrollHeight - 80) {
      fetchPage(false);
    }
  }

  function clearJournal() {
    if (!window.confirm('Mettre de côté le journal affiché ? Les entrées resteront consultables via « Voir l’historique archivé ».')) {
      return;
    }
    var base = getApiBase();
    if (!base) return;
    fetch(base + '/api/atak/activity/clear', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ mapId: getMapId() })
    })
      .then(function (r) {
        if (!r.ok) throw new Error('clear');
        return r.json();
      })
      .then(function () {
        fetchPage(true);
      })
      .catch(function () {
        var status = document.getElementById('atak-liaison-status');
        if (status) status.textContent = 'Impossible de mettre le journal de côté.';
      });
  }

  function seedDemo() {
    var base = getApiBase();
    if (!base) return;
    var url = base + '/api/atak/activity?mapId=' + encodeURIComponent(getMapId())
      + '&limit=1&page=1&demo=1&demo_force=1';
    fetch(url, { credentials: 'include' })
      .then(function () { return fetchPage(true); })
      .catch(function () { /* ignore */ });
  }

  function init() {
    listEl = document.getElementById('atak-liaison-list');
    emptyEl = document.getElementById('atak-liaison-empty');
    metaEl = document.getElementById('atak-liaison-meta');
    if (!listEl) return;

    var applyBtn = document.getElementById('atak-liaison-apply');
    if (applyBtn) applyBtn.addEventListener('click', function () { fetchPage(true); });

    ['atak-liaison-type', 'atak-liaison-archived', 'atak-liaison-map'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('change', function () { fetchPage(true); });
    });

    var qEl = document.getElementById('atak-liaison-q');
    var qTimer = null;
    if (qEl) {
      qEl.addEventListener('input', function () {
        if (qTimer) clearTimeout(qTimer);
        qTimer = setTimeout(function () { fetchPage(true); }, 320);
      });
    }

    var clearBtn = document.getElementById('atak-liaison-clear');
    if (clearBtn) clearBtn.addEventListener('click', clearJournal);

    var demoBtn = document.getElementById('atak-liaison-demo');
    if (demoBtn) demoBtn.addEventListener('click', seedDemo);

    listEl.addEventListener('scroll', onScroll);
    fetchPage(true);
  }

  return { init: init, refresh: function () { return fetchPage(true); } };
})();

document.addEventListener('DOMContentLoaded', function () {
  if (window.ATAKLiaisonPage) ATAKLiaisonPage.init();
});
