/*
 * Overwatch — fenêtre « Charger un replay » : choisir un enregistrement (sessions du serveur, clips locaux,
 * session en cours), voir son aperçu, régler départ / vitesse / options, puis le charger.
 * Même API que l'ancienne barre : GET /api/replay/sessions/{mission} et /api/replay/events/{mission}
 * (champs de session ajoutés sans rien retirer : mission, map, units, durationSeconds, approxBytes).
 * Le chargement lui-même passe par OverwatchOps.replayLoad (atak-overwatch-ops.js).
 */
(function () {
  'use strict';
  if (!window.ATAK_OVERWATCH_BETA || window.OverwatchReplayPicker) return;

  var root = null;
  var opener = null;
  var entries = [];
  var selectedId = '';
  var state = { loading: false, error: '', busy: false, aborted: false };
  var eventCache = {};
  var PREFS_KEY = 'athena:ow-replay-prefs-v1';
  var prefs = { speed: '1', trails: true, events: true, labels: true, autoplay: true };
  var filters = { q: '', date: 'all', map: '', mission: '', origin: '', sort: 'date-desc' };
  var startMode = 'begin';
  var startFrac = 0;

  function $(sel, ctx) { return (ctx || root).querySelector(sel); }
  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' }[c];
    });
  }
  function attr(label) { return ' aria-label="' + esc(label) + '"'; }
  function ow() { return window.OverwatchBeta || null; }
  function ops() { return window.OverwatchOps || null; }
  function isEn() { return String(document.documentElement.lang || 'fr').slice(0, 2).toLowerCase() === 'en'; }
  function locale() { return isEn() ? 'en-GB' : 'fr-FR'; }
  function missionId() {
    return 'mission_' + Number(window.ATAK_TENANT_ID || 0) + '_map_' + Number((ow() && ow().mapId) || window.ATAK_DEFAULT_MAP_ID || 1);
  }

  function loadPrefs() {
    try {
      var p = JSON.parse(localStorage.getItem(PREFS_KEY) || '{}');
      Object.keys(prefs).forEach(function (k) { if (p && p[k] != null) prefs[k] = p[k]; });
    } catch (e) {}
  }
  function savePrefs() { try { localStorage.setItem(PREFS_KEY, JSON.stringify(prefs)); } catch (e) {} }

  function toMs(ts) {
    var s = String(ts == null ? '' : ts).trim();
    if (!s) return NaN;
    if (/^\d+$/.test(s)) return Number(s) < 1e12 ? Number(s) * 1000 : Number(s);
    var iso = s.indexOf('T') >= 0 ? s : s.replace(' ', 'T');
    if (!/[zZ]|[+-]\d{2}:?\d{2}$/.test(iso)) iso += 'Z';
    return Date.parse(iso);
  }
  function fmtDate(ms) {
    if (!isFinite(ms)) return '—';
    var d = new Date(ms);
    return d.toLocaleDateString(locale(), { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' '
      + d.toLocaleTimeString(locale(), { hour: '2-digit', minute: '2-digit' });
  }
  function fmtClock(ms) {
    return isFinite(ms) ? new Date(ms).toLocaleTimeString(locale(), { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '—';
  }
  function fmtDur(sec) {
    sec = Math.max(0, Math.round(Number(sec) || 0));
    var h = Math.floor(sec / 3600);
    var m = Math.floor((sec % 3600) / 60);
    var s = sec % 60;
    if (h > 0) return h + ' h ' + (m < 10 ? '0' : '') + m + ' min';
    if (m > 0) return m + ' min ' + (s < 10 ? '0' : '') + s + ' s';
    return s + ' s';
  }
  function fmtBytes(b) {
    b = Number(b) || 0;
    var u = isEn() ? ['B', 'KB', 'MB', 'GB'] : ['o', 'Ko', 'Mo', 'Go'];
    var i = 0;
    while (b >= 1024 && i < u.length - 1) { b /= 1024; i += 1; }
    return (i === 0 ? String(Math.round(b)) : b.toFixed(b < 10 ? 1 : 0)) + ' ' + u[i];
  }

  /* ---------- Données : sessions du serveur + clips locaux + session en cours ---------- */

  function localEntries() {
    var list = [];
    var source = document.getElementById('ow-replay-source');
    list.push({
      id: 'live', kind: 'live', name: 'Session en cours',
      sub: 'Trajectoires reçues depuis l’ouverture de cette page', fromMs: NaN, toMs: NaN, durationSec: 0,
      units: 0, samples: 0, bytes: 0, map: '', mission: '', origin: 'local'
    });
    if (source) {
      Array.prototype.slice.call(source.options).forEach(function (opt) {
        if (String(opt.value).indexOf('clip:') !== 0) return;
        list.push({
          id: opt.value, kind: 'clip', value: opt.value, name: opt.textContent.trim() || 'Clip',
          sub: 'Enregistrement fait depuis ce navigateur (REC)', fromMs: NaN, toMs: NaN, durationSec: 0,
          units: 0, samples: 0, bytes: 0, map: '', mission: '', origin: 'local'
        });
      });
    }
    return list;
  }

  function serverEntries(payload) {
    var rows = Array.isArray(payload && payload.sessions) ? payload.sessions : [];
    var out = rows.map(function (m) {
      var from = toMs(m.from);
      var to = toMs(m.to);
      var base = String(m.key || '').split('#')[0].split('@');
      return {
        id: 'key:' + String(m.key), kind: 'server', key: String(m.key),
        name: String(m.mission || base[0] || m.label || m.key),
        sub: String(m.label || m.key),
        fromMs: from, toMs: to,
        durationSec: m.durationSeconds != null ? Number(m.durationSeconds) : (isFinite(from) && isFinite(to) ? Math.max(0, (to - from) / 1000) : 0),
        units: Number(m.units) || 0, samples: Number(m.samples) || 0,
        bytes: m.approxBytes != null ? Number(m.approxBytes) : (Number(m.samples) || 0) * 160,
        map: String(m.map || base[1] || ''), mission: String(m.mission || base[0] || ''), origin: 'server'
      };
    });
    if (out.length) {
      var all = {
        id: 'all', kind: 'server', key: '', name: 'Toute la mission',
        sub: 'Toutes les sessions de cette carte, bout à bout', fromMs: Infinity, toMs: -Infinity, durationSec: 0,
        units: 0, samples: 0, bytes: 0, map: '', mission: '', origin: 'server'
      };
      out.forEach(function (e) {
        if (isFinite(e.fromMs)) all.fromMs = Math.min(all.fromMs, e.fromMs);
        if (isFinite(e.toMs)) all.toMs = Math.max(all.toMs, e.toMs);
        all.units = Math.max(all.units, e.units);
        all.samples += e.samples;
        all.bytes += e.bytes;
      });
      if (!isFinite(all.fromMs)) all.fromMs = NaN;
      if (!isFinite(all.toMs)) all.toMs = NaN;
      all.durationSec = out.reduce(function (sum, e) { return sum + e.durationSec; }, 0);
      all.map = out[0].map;
      out.unshift(all);
    }
    return out;
  }

  function fetchSessions() {
    var api = ow();
    state.loading = true;
    state.error = '';
    paintList();
    if (!api) { state.loading = false; state.error = 'unavailable'; entries = localEntries(); paintList(); return; }
    api.api('/api/replay/sessions/' + encodeURIComponent(missionId())).then(function (payload) {
      entries = serverEntries(payload).concat(localEntries());
      state.loading = false;
    }).catch(function (err) {
      entries = localEntries();
      state.loading = false;
      state.error = errorText(err);
    }).then(function () {
      if (!entries.some(function (e) { return e.id === selectedId; })) {
        var vis = visibleEntries();
        var recent = vis.filter(function (e) { return e.kind === 'server' && e.id !== 'all'; })[0];
        selectedId = recent ? recent.id : (vis[0] ? vis[0].id : '');
      }
      fillFilterOptions();
      paintList();
      paintDetail();
      var cur = current();
      if (cur && cur.kind === 'server') fetchEvents(cur);
    });
  }

  function errorText(err) {
    var code = String((err && err.message) || '');
    if (code === '401') return 'Session expirée : reconnectez-vous puis réessayez.';
    if (code === '403') return 'Accès refusé à ce replay pour votre rôle ou votre communauté.';
    if (code === '404') return 'Enregistrement introuvable : il a peut-être été supprimé.';
    if (code === '422') return 'Identifiant de mission invalide.';
    if (code === '429' || code === '503') return 'Le serveur demande une pause. Réessayez dans quelques secondes.';
    if (code === 'empty') return 'Aucune position n’est enregistrée pour ce replay.';
    if (/^5\d\d$/.test(code)) return 'Erreur du serveur Athena. Réessayez dans un instant.';
    if (code === 'unavailable') return 'La carte n’est pas encore prête. Réessayez dans un instant.';
    return 'Liaison impossible : vérifiez votre connexion puis réessayez.';
  }

  /* ---------- Filtres, tri ---------- */

  function inDateRange(e) {
    if (filters.date === 'all') return true;
    var t = isFinite(e.toMs) ? e.toMs : e.fromMs;
    if (!isFinite(t)) return e.kind !== 'server';
    var now = Date.now();
    var day = 86400000;
    if (filters.date === 'today') return t >= new Date(new Date().setHours(0, 0, 0, 0)).getTime();
    if (filters.date === '7d') return t >= now - 7 * day;
    if (filters.date === '30d') return t >= now - 30 * day;
    return true;
  }
  function visibleEntries() {
    var q = filters.q.trim().toLowerCase();
    var list = entries.filter(function (e) {
      if (filters.origin && e.origin !== filters.origin) return false;
      if (filters.map && e.map !== filters.map) return false;
      if (filters.mission && e.mission !== filters.mission) return false;
      if (!inDateRange(e)) return false;
      if (q) {
        var hay = (e.name + ' ' + e.sub + ' ' + e.map + ' ' + e.mission).toLowerCase();
        if (hay.indexOf(q) === -1) return false;
      }
      return true;
    });
    var s = filters.sort;
    var num = function (v) { return isFinite(v) ? v : -Infinity; };
    list.sort(function (a, b) {
      if (a.id === 'all' && s.indexOf('date') === 0) return -1;
      if (b.id === 'all' && s.indexOf('date') === 0) return 1;
      if (s === 'date-asc') return num(a.toMs) - num(b.toMs);
      if (s === 'name') return a.name.localeCompare(b.name, locale());
      if (s === 'duration') return b.durationSec - a.durationSec;
      if (s === 'units') return b.units - a.units;
      if (s === 'size') return b.bytes - a.bytes;
      return num(b.toMs) - num(a.toMs);
    });
    return list;
  }
  function uniqueValues(field) {
    var seen = {};
    entries.forEach(function (e) { if (e[field]) seen[e[field]] = true; });
    return Object.keys(seen).sort();
  }
  function fillFilterOptions() {
    if (!root) return;
    [['map', 'Toutes les cartes'], ['mission', 'Toutes les missions']].forEach(function (pair) {
      var sel = $('[data-owr-filter="' + pair[0] + '"]');
      if (!sel) return;
      sel.innerHTML = '<option value="">' + esc(pair[1]) + '</option>' + uniqueValues(pair[0]).map(function (v) {
        return '<option value="' + esc(v) + '">' + esc(v) + '</option>';
      }).join('');
      sel.value = filters[pair[0]];
    });
  }

  /* ---------- Rendu ---------- */

  function build() {
    root = document.createElement('div');
    root.className = 'owr-back';
    root.id = 'ow-replay-modal';
    root.hidden = true;
    root.innerHTML =
      '<div class="owr" role="dialog" aria-modal="true" aria-labelledby="owr-title" aria-describedby="owr-sub">' +
        '<header class="owr-head">' +
          '<div><h2 id="owr-title">Charger un replay</h2>' +
          '<p id="owr-sub">Choisissez un enregistrement, réglez le départ et la vitesse, puis chargez-le sur la carte.</p></div>' +
          '<button type="button" class="owr-x" data-owr-close aria-label="Fermer">&times;</button>' +
        '</header>' +
        '<div class="owr-body">' +
          '<section class="owr-col owr-col--list" aria-label="Enregistrements">' +
            '<div class="owr-tools">' +
              '<label class="owr-search"><span class="owr-sr">Rechercher un replay</span>' +
                '<input type="search" data-owr-q placeholder="Rechercher un nom, une carte, une mission…" autocomplete="off"></label>' +
              '<div class="owr-filters">' +
                '<label><span>Date</span><select data-owr-filter="date">' +
                  '<option value="all">Toutes les dates</option><option value="today">Aujourd’hui</option>' +
                  '<option value="7d">7 derniers jours</option><option value="30d">30 derniers jours</option></select></label>' +
                '<label><span>Carte</span><select data-owr-filter="map"><option value="">Toutes les cartes</option></select></label>' +
                '<label><span>Mission</span><select data-owr-filter="mission"><option value="">Toutes les missions</option></select></label>' +
                '<label><span>Origine</span><select data-owr-filter="origin">' +
                  '<option value="">Toutes les origines</option><option value="server">Serveur Athena</option>' +
                  '<option value="local">Ce navigateur</option></select></label>' +
                '<label><span>Tri</span><select data-owr-filter="sort">' +
                  '<option value="date-desc">Plus récents d’abord</option><option value="date-asc">Plus anciens d’abord</option>' +
                  '<option value="name">Nom</option><option value="duration">Durée</option>' +
                  '<option value="units">Nombre d’unités</option><option value="size">Taille</option></select></label>' +
              '</div>' +
            '</div>' +
            '<p class="owr-count" data-owr-count role="status" aria-live="polite"></p>' +
            '<div class="owr-listwrap">' +
              '<ul class="owr-list" role="listbox" aria-label="Enregistrements disponibles" tabindex="0" data-owr-list></ul>' +
              '<div class="owr-state" data-owr-state hidden></div>' +
            '</div>' +
          '</section>' +
          '<section class="owr-col owr-col--detail" aria-label="Aperçu et réglages" data-owr-detail></section>' +
        '</div>' +
        '<footer class="owr-foot">' +
          '<div class="owr-status" data-owr-status role="status" aria-live="polite"></div>' +
          '<div class="owr-actions">' +
            '<button type="button" class="owr-btn" data-owr-close>Annuler</button>' +
            '<button type="button" class="owr-btn owr-btn--go" data-owr-load disabled>Charger</button>' +
          '</div>' +
        '</footer>' +
      '</div>';
    document.body.appendChild(root);

    root.addEventListener('mousedown', function (e) { if (e.target === root) root.__down = true; else root.__down = false; });
    root.addEventListener('click', function (e) {
      if (e.target === root && root.__down) { close(); return; }
      var t = e.target;
      if (t.closest('[data-owr-close]')) { close(); return; }
      if (t.closest('[data-owr-load]')) { load(); return; }
      if (t.closest('[data-owr-retry]')) { fetchSessions(); return; }
      var item = t.closest('.owr-item');
      if (item) { select(item.getAttribute('data-id')); return; }
      var tl = t.closest('[data-owr-tl]');
      if (tl && startMode === 'time') {
        var r = tl.getBoundingClientRect();
        setStart(Math.max(0, Math.min(1, (e.clientX - r.left) / Math.max(1, r.width))));
      }
    });
    root.addEventListener('dblclick', function (e) {
      var item = e.target.closest && e.target.closest('.owr-item');
      if (item) { select(item.getAttribute('data-id')); load(); }
    });
    root.addEventListener('input', function (e) {
      var t = e.target;
      if (t.matches('[data-owr-q]')) { filters.q = t.value; refilter(); }
      else if (t.matches('[data-owr-range]')) { setStart(Number(t.value) / 1000, true); }
      else if (t.matches('[data-owr-clock]')) { clockToStart(t.value); }
    });
    root.addEventListener('change', function (e) {
      var t = e.target;
      var f = t.getAttribute && t.getAttribute('data-owr-filter');
      if (f) { filters[f] = t.value; refilter(); return; }
      if (t.matches('[data-owr-start]')) { startMode = t.value; paintDetail(); return; }
      if (t.matches('[data-owr-speed]')) { prefs.speed = t.value; savePrefs(); return; }
      var pref = t.getAttribute && t.getAttribute('data-owr-pref');
      if (pref) { prefs[pref] = !!t.checked; savePrefs(); }
    });
    root.addEventListener('keydown', onKey);
  }

  function refilter() {
    var vis = visibleEntries();
    if (!vis.some(function (e) { return e.id === selectedId; })) selectedId = vis[0] ? vis[0].id : '';
    paintList();
    paintDetail();
  }

  function paintList() {
    if (!root) return;
    var ul = $('[data-owr-list]');
    var st = $('[data-owr-state]');
    var count = $('[data-owr-count]');
    var vis = visibleEntries();
    var err = state.error ? '<p class="owr-note owr-note--err" role="alert">' + esc(state.error) + '</p>' : '';
    if (state.loading) {
      ul.hidden = true;
      st.hidden = false;
      st.innerHTML = '<progress class="owr-prog" aria-label="Chargement de la liste"></progress><p>Chargement des enregistrements…</p>';
      count.textContent = '';
      return;
    }
    ul.hidden = !vis.length;
    st.hidden = vis.length > 0 && !state.error;
    if (!vis.length) {
      var empty = entries.length > 0;
      st.innerHTML = err + '<p class="owr-empty-title">' + (empty ? 'Aucun replay ne correspond' : 'Aucun enregistrement disponible') + '</p>' +
        '<p>' + (empty ? 'Élargissez la recherche ou retirez un filtre.' : 'Les positions reçues pendant une mission apparaissent ici. Vous pouvez aussi lancer un REC depuis la barre du haut.') + '</p>' +
        (state.error ? '<button type="button" class="owr-btn" data-owr-retry>Réessayer</button>' : '');
    } else if (state.error) {
      st.innerHTML = err + '<button type="button" class="owr-btn" data-owr-retry>Réessayer</button>';
    }
    count.textContent = vis.length + (vis.length > 1 ? ' replays' : ' replay');
    ul.innerHTML = vis.map(function (e) {
      var sel = e.id === selectedId;
      var meta = [];
      if (isFinite(e.toMs)) meta.push('<span>' + esc(fmtDate(e.toMs)) + '</span>');
      if (e.durationSec) meta.push('<span>' + esc(fmtDur(e.durationSec)) + '</span>');
      if (e.map) meta.push('<span>' + esc(e.map) + '</span>');
      if (e.units) meta.push('<span>' + e.units + (e.units > 1 ? ' unités' : ' unité') + '</span>');
      var badge = e.kind === 'live' ? 'Direct' : (e.kind === 'clip' ? 'Local' : (e.id === 'all' ? 'Complet' : 'Serveur'));
      return '<li class="owr-item' + (sel ? ' is-sel' : '') + '" role="option" id="owr-opt-' + esc(e.id.replace(/[^a-z0-9_-]/gi, '_')) + '" data-id="' + esc(e.id) + '" aria-selected="' + (sel ? 'true' : 'false') + '">' +
        '<span class="owr-item-top"><b class="owr-item-name">' + esc(e.name) + '</b><em class="owr-badge owr-badge--' + e.kind + '">' + badge + '</em></span>' +
        (meta.length ? '<span class="owr-item-meta">' + meta.join('') + '</span>' : '<span class="owr-item-meta"><span>' + esc(e.sub) + '</span></span>') +
        '</li>';
    }).join('');
    var active = ul.querySelector('.is-sel');
    if (active) { ul.setAttribute('aria-activedescendant', active.id); keepVisible(active); }
    else ul.removeAttribute('aria-activedescendant');
    syncLoadBtn();
  }

  function keepVisible(li) {
    var ul = li.parentNode;
    if (li.offsetTop < ul.scrollTop) ul.scrollTop = li.offsetTop;
    else if (li.offsetTop + li.offsetHeight > ul.scrollTop + ul.clientHeight) ul.scrollTop = li.offsetTop + li.offsetHeight - ul.clientHeight;
  }

  function current() {
    for (var i = 0; i < entries.length; i += 1) if (entries[i].id === selectedId) return entries[i];
    return null;
  }

  function syncLoadBtn() {
    var b = $('[data-owr-load]');
    if (b) b.disabled = !current() || state.busy;
  }

  function select(id) {
    selectedId = id;
    startMode = 'begin';
    startFrac = 0;
    paintList();
    paintDetail();
    var e = current();
    if (e && e.kind === 'server') fetchEvents(e);
  }

  function fetchEvents(e) {
    var api = ow();
    if (!api || eventCache[e.id]) return;
    eventCache[e.id] = { loading: true, rows: [] };
    var q = e.key ? '?mission_key=' + encodeURIComponent(e.key) : '';
    api.api('/api/replay/events/' + encodeURIComponent(missionId()) + q).then(function (payload) {
      eventCache[e.id] = { loading: false, rows: Array.isArray(payload && payload.events) ? payload.events : [] };
    }).catch(function () {
      eventCache[e.id] = { loading: false, rows: [], failed: true };
    }).then(function () { if (selectedId === e.id) paintDetail(); });
  }

  var TYPE_COLORS = { contact: '#e05b63', medevac: '#e7b14d', order: '#5b8def', marker: '#8b8bf0', intel: '#00d69a' };

  function paintDetail() {
    if (!root) return;
    var box = $('[data-owr-detail]');
    var e = current();
    if (!e) {
      box.innerHTML = '<div class="owr-pick"><p class="owr-empty-title">Aucun replay sélectionné</p><p>Choisissez un enregistrement dans la liste pour voir son aperçu.</p></div>';
      syncLoadBtn();
      return;
    }
    var ev = eventCache[e.id];
    var kinds = {};
    var events = ev && ev.rows ? ev.rows : [];
    events.forEach(function (r) { var k = String(r.type || 'contact'); kinds[k] = (kinds[k] || 0) + 1; });
    var span = isFinite(e.fromMs) && isFinite(e.toMs) ? e.toMs - e.fromMs : 0;
    var ticks = '';
    if (span > 0) {
      ticks = events.slice(0, 400).map(function (r) {
        var t = toMs(r.timestamp);
        if (!isFinite(t)) return '';
        var p = Math.max(0, Math.min(100, ((t - e.fromMs) / span) * 100));
        return '<i style="left:' + p.toFixed(2) + '%;background:' + (TYPE_COLORS[String(r.type || 'contact')] || '#8b8bf0') + '"></i>';
      }).join('');
    }
    var canStart = e.kind === 'server' && span > 0;
    var startMs = canStart ? e.fromMs + span * startFrac : NaN;
    var dl = [
      ['Début', isFinite(e.fromMs) ? fmtDate(e.fromMs) : '—'],
      ['Fin', isFinite(e.toMs) ? fmtDate(e.toMs) : '—'],
      ['Durée', e.durationSec ? fmtDur(e.durationSec) : '—'],
      ['Carte', e.map || '—'],
      ['Mission', e.mission || '—'],
      ['Unités', e.units ? String(e.units) : '—'],
      ['Positions', e.samples ? String(e.samples) : '—'],
      ['Événements', e.kind !== 'server' ? '—' : (ev && ev.loading ? '…' : String(events.length))],
      ['Taille estimée', e.bytes ? fmtBytes(e.bytes) : '—']
    ].map(function (r) { return '<div><dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd></div>'; }).join('');
    var legend = Object.keys(kinds).map(function (k) {
      return '<span><i style="background:' + (TYPE_COLORS[k] || '#8b8bf0') + '"></i>' + esc(k) + ' ' + kinds[k] + '</span>';
    }).join('');
    box.innerHTML =
      '<div class="owr-d-head"><h3>' + esc(e.name) + '</h3><p>' + esc(e.sub) + '</p></div>' +
      '<dl class="owr-dl">' + dl + '</dl>' +
      (e.kind === 'server'
        ? '<div class="owr-tlwrap"><p class="owr-label">Chronologie</p>' +
          '<div class="owr-tl" data-owr-tl role="img" aria-label="Chronologie du replay avec les événements clés">' + ticks +
          (startMode === 'time' ? '<b class="owr-cursor" style="left:' + (startFrac * 100).toFixed(2) + '%"></b>' : '') + '</div>' +
          '<div class="owr-tl-ends"><span>' + esc(fmtClock(e.fromMs)) + '</span><span>' + esc(fmtClock(e.toMs)) + '</span></div>' +
          (ev && ev.failed ? '<p class="owr-note">Aperçu des événements indisponible.</p>'
            : (ev && !ev.loading && !events.length ? '<p class="owr-note">Aucun événement clé enregistré.</p>' : '')) +
          (legend ? '<p class="owr-legend">' + legend + '</p>' : '') + '</div>'
        : '<p class="owr-note">' + (e.kind === 'live'
          ? 'Rejoue les déplacements reçus depuis l’ouverture de cette page.'
          : 'Clip enregistré dans ce navigateur : il se charge instantanément.') + '</p>') +
      '<fieldset class="owr-fs"' + (e.kind === 'server' ? '' : ' disabled') + '><legend>Point de départ</legend>' +
        '<label class="owr-radio"><input type="radio" name="owr-start" value="begin" data-owr-start' + (startMode === 'begin' ? ' checked' : '') + '> Depuis le début</label>' +
        '<label class="owr-radio"><input type="radio" name="owr-start" value="time" data-owr-start' + (startMode === 'time' ? ' checked' : '') + (canStart ? '' : ' disabled') + '> <span>' + 'À partir d’un horodatage' + '</span></label>' +
        (startMode === 'time' && canStart
          ? '<div class="owr-time"><input type="range" min="0" max="1000" step="1" value="' + Math.round(startFrac * 1000) + '" data-owr-range' + attr('Position de départ dans le replay') + '>' +
            '<input type="time" step="1" data-owr-clock value="' + new Date(startMs).toTimeString().slice(0, 8) + '"' + attr('Heure de départ') + '></div>' +
            '<p class="owr-note" data-owr-startnote>' + esc(fmtDur((startMs - e.fromMs) / 1000)) + ' · ' + esc(fmtClock(startMs)) + '</p>'
          : '') +
      '</fieldset>' +
      '<div class="owr-grid2"><label class="owr-field"><span>Vitesse de lecture</span><select data-owr-speed>' +
        ['1', '2', '4', '8'].map(function (v) { return '<option value="' + v + '"' + (String(prefs.speed) === v ? ' selected' : '') + '>' + v + '×</option>'; }).join('') +
      '</select></label></div>' +
      '<fieldset class="owr-fs"><legend>Options</legend>' +
        '<label class="owr-check"><input type="checkbox" data-owr-pref="trails"' + (prefs.trails ? ' checked' : '') + '> <span>' + 'Afficher les trajectoires' + '</span></label>' +
        '<label class="owr-check"><input type="checkbox" data-owr-pref="events"' + (prefs.events ? ' checked' : '') + '> <span>' + 'Marqueurs des événements clés' + '</span></label>' +
        '<label class="owr-check"><input type="checkbox" data-owr-pref="labels"' + (prefs.labels ? ' checked' : '') + '> <span>' + 'Étiquettes des indicatifs' + '</span></label>' +
        '<label class="owr-check"><input type="checkbox" data-owr-pref="autoplay"' + (prefs.autoplay ? ' checked' : '') + '> <span>' + 'Lancer la lecture après le chargement' + '</span></label>' +
      '</fieldset>';
    syncLoadBtn();
  }

  function setStart(frac, fromRange) {
    startFrac = Math.max(0, Math.min(1, frac));
    var e = current();
    if (!e) return;
    var tl = $('.owr-cursor');
    if (tl) tl.style.left = (startFrac * 100).toFixed(2) + '%';
    var span = e.toMs - e.fromMs;
    var ms = e.fromMs + span * startFrac;
    var note = $('[data-owr-startnote]');
    if (note) note.textContent = fmtDur((ms - e.fromMs) / 1000) + ' · ' + fmtClock(ms);
    var clock = $('[data-owr-clock]');
    if (clock) clock.value = new Date(ms).toTimeString().slice(0, 8);
    var range = $('[data-owr-range]');
    if (range && !fromRange) range.value = String(Math.round(startFrac * 1000));
  }
  function clockToStart(value) {
    var e = current();
    var m = /^(\d{2}):(\d{2})(?::(\d{2}))?$/.exec(value || '');
    if (!e || !m) return;
    var d = new Date(e.fromMs);
    d.setHours(Number(m[1]), Number(m[2]), Number(m[3] || 0), 0);
    var ms = d.getTime();
    if (ms < e.fromMs) ms += 86400000;
    setStart((ms - e.fromMs) / Math.max(1, e.toMs - e.fromMs));
  }

  /* ---------- Chargement ---------- */

  function setStatus(html, kind) {
    var s = $('[data-owr-status]');
    if (!s) return;
    s.className = 'owr-status' + (kind ? ' is-' + kind : '');
    s.innerHTML = html;
  }

  function load() {
    var e = current();
    var o = ops();
    if (!e || state.busy) return;
    if (!o || typeof o.replayLoad !== 'function') { setStatus(esc(errorText(new Error('unavailable'))), 'err'); return; }
    state.busy = true;
    state.aborted = false;
    syncLoadBtn();
    var btn = $('[data-owr-load]');
    btn.textContent = 'Chargement…';
    setStatus('<progress class="owr-prog" aria-label="Chargement du replay"></progress><span>Chargement des positions…</span>', 'busy');
    var span = isFinite(e.fromMs) && isFinite(e.toMs) ? e.toMs - e.fromMs : 0;
    var opts = {
      kind: e.kind, key: e.key || '', value: e.value || '', name: e.name,
      speed: Number(prefs.speed) || 1, trails: !!prefs.trails, events: !!prefs.events, labels: !!prefs.labels, autoplay: !!prefs.autoplay,
      startTs: startMode === 'time' && span > 0 ? e.fromMs + span * startFrac : NaN
    };
    o.replayLoad(opts).then(function (res) {
      if (state.aborted) return;
      state.busy = false;
      btn.textContent = 'Charger';
      markCurrent(e);
      close(true);
      var api = ow();
      if (api && api.toast) {
        api.toast(res && res.frames ? 'Replay chargé : %1 instants.'.replace('%1', String(res.frames)) : 'Replay prêt.');
      }
    }).catch(function (err) {
      if (state.aborted) return;
      state.busy = false;
      btn.textContent = 'Réessayer';
      syncLoadBtn();
      setStatus('<span>' + esc(errorText(err)) + '</span>', 'err');
    });
  }

  function markCurrent(e) {
    var label = document.getElementById('ow-replay-current');
    if (label) label.textContent = e.name;
  }

  /* ---------- Ouverture, fermeture, clavier ---------- */

  function focusables() {
    return Array.prototype.slice.call(root.querySelectorAll('button,input,select,textarea,[tabindex="0"]')).filter(function (n) {
      return !n.disabled && !n.hidden && n.offsetParent !== null && n.getAttribute('tabindex') !== '-1';
    });
  }

  function onKey(e) {
    if (e.key === 'Escape') {
      e.preventDefault();
      e.stopPropagation();
      if (state.busy) { state.aborted = true; state.busy = false; var b = $('[data-owr-load]'); if (b) b.textContent = 'Charger'; setStatus('Chargement annulé.', ''); syncLoadBtn(); return; }
      close();
      return;
    }
    if (e.key === 'Tab') {
      var f = focusables();
      if (!f.length) return;
      var first = f[0];
      var last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      return;
    }
    var t = e.target;
    var inList = t && t.matches && t.matches('[data-owr-list]');
    if (inList && (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Home' || e.key === 'End')) {
      e.preventDefault();
      var vis = visibleEntries();
      if (!vis.length) return;
      var i = -1;
      vis.forEach(function (v, n) { if (v.id === selectedId) i = n; });
      if (e.key === 'ArrowDown') i = Math.min(vis.length - 1, i + 1);
      else if (e.key === 'ArrowUp') i = Math.max(0, i - 1);
      else if (e.key === 'Home') i = 0;
      else i = vis.length - 1;
      select(vis[i].id);
      return;
    }
    if (e.key === 'Enter') {
      if (inList) { e.preventDefault(); load(); return; }
      if (t && t.matches && t.matches('[data-owr-q]')) {
        e.preventDefault();
        var list = $('[data-owr-list]');
        if (list && !list.hidden) list.focus();
      }
    }
  }

  function open() {
    if (!root) { loadPrefs(); build(); }
    opener = document.activeElement;
    var shell = document.querySelector('.ow-shell');
    if (shell && 'inert' in shell) shell.inert = true;
    root.hidden = false;
    document.body.classList.add('owr-open');
    state.busy = false;
    state.aborted = false;
    var btn = $('[data-owr-load]');
    if (btn) btn.textContent = 'Charger';
    setStatus('', '');
    eventCache = {};
    entries = localEntries();
    fetchSessions();
    var q = $('[data-owr-q]');
    window.setTimeout(function () { if (q) q.focus(); }, 0);
  }

  function close(loaded) {
    if (!root || root.hidden) return;
    root.hidden = true;
    document.body.classList.remove('owr-open');
    var shell = document.querySelector('.ow-shell');
    if (shell && 'inert' in shell) shell.inert = false;
    state.busy = false;
    if (!loaded) state.aborted = true;
    if (opener && opener.focus && document.contains(opener)) { try { opener.focus(); } catch (e) {} }
  }

  /* Remplace l'ancien basculement de la barre : tout bouton « Rejouer / Ouvrir le replay » ouvre la fenêtre. */
  document.addEventListener('click', function (e) {
    var trigger = e.target && e.target.closest ? e.target.closest('[data-ow-replay]') : null;
    if (!trigger) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    open();
  }, true);

  window.OverwatchReplayPicker = { open: open, close: close };
})();
