/* COMSPEC ATAK — journal local incidents / remontées (carte web) */
window.ATAKWebLog = (function () {
  var MAX_ENTRIES = 400;
  var FLUSH_MAX = 20;
  var FLUSH_INTERVAL_MS = 4000;
  var FLUSH_MINUTE_CAP = 20;
  var UNITS_THROTTLE_MS = 8000;
  var entries = [];
  var pendingFlush = [];
  var filterKind = 'all';
  var flushedThisMinute = 0;
  var minuteStartedAt = 0;
  var lastUnitsFp = '';
  var lastUnitsAt = 0;
  var lastNewCallsigns = {};
  var fetchWrapped = false;
  var origFetch = null;
  var flushTimer = null;

  function apiBase() {
    if (window.ATAKSocket && typeof window.ATAKSocket.getApiBase === 'function') {
      return String(window.ATAKSocket.getApiBase() || '').replace(/\/$/, '');
    }
    return String(window.ATAK_API_BASE || '').replace(/\/$/, '');
  }

  function mapId() {
    if (window.ATAKSocket && typeof window.ATAKSocket.getMapId === 'function') {
      return window.ATAKSocket.getMapId();
    }
    var n = Number(window.ATAK_DEFAULT_MAP_ID);
    return (!isNaN(n) && n > 0) ? n : 1;
  }

  function storageKey() {
    return 'atak_weblog_m' + mapId();
  }

  function nowIso() {
    try {
      return new Date().toISOString();
    } catch (e) {
      return '';
    }
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatTime(iso) {
    var d = iso ? new Date(iso) : new Date();
    if (isNaN(d.getTime())) return '—';
    var h = d.getHours();
    var m = d.getMinutes();
    var s = d.getSeconds();
    return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }

  function loadStored() {
    try {
      var raw = localStorage.getItem(storageKey());
      if (!raw) return;
      var parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) entries = parsed.slice(-MAX_ENTRIES);
    } catch (e) { /* ignore */ }
  }

  function persist() {
    try {
      localStorage.setItem(storageKey(), JSON.stringify(entries.slice(-MAX_ENTRIES)));
    } catch (e) { /* ignore */ }
  }

  function requestUrl(input) {
    if (typeof input === 'string') return input;
    if (input && input.url) return String(input.url);
    return '';
  }

  function requestMethod(input, init) {
    if (init && init.method) return String(init.method).toUpperCase();
    if (input && typeof input !== 'string' && input.method) return String(input.method).toUpperCase();
    return 'GET';
  }

  function pathnameOf(url) {
    var s = String(url || '');
    try {
      if (s.indexOf('http') === 0) return new URL(s).pathname;
    } catch (e) { /* keep */ }
    var q = s.indexOf('?');
    return q >= 0 ? s.slice(0, q) : s;
  }

  function isIgnoredUrl(url) {
    var s = String(url || '');
    return /\/api\/atak\/web-log(?:\?|$)/.test(s)
      || /\/api\/atak\/ping(?:\?|$)/.test(s)
      || /\/api\/health(?:\?|$)/.test(s);
  }

  function isOurApi(url) {
    var s = String(url || '');
    if (!s) return false;
    return /\/api\//.test(s);
  }

  function incidentLabelForStatus(status) {
    if (status === 0 || status == null) return 'La liaison avec le poste a été interrompue.';
    if (status === 401 || status === 403) return 'Accès refusé pour cette action.';
    if (status === 404) return 'Une donnée demandée est introuvable.';
    if (status === 409) return 'Cette action entre en conflit avec l’état actuel.';
    if (status === 422) return 'La demande n’a pas pu être enregistrée.';
    if (status === 429) return 'Trop d’actions en peu de temps — patientez un instant.';
    if (status === 503) return 'Le poste est momentanément injoignable.';
    if (status >= 500) return 'Le poste n’a pas pu traiter la demande.';
    return 'Une action n’a pas abouti.';
  }

  function remonteeLabelForPath(path, method) {
    var p = String(path || '');
    if (/\/api\/atak\/chat/.test(p)) return 'Message radio transmis';
    if (/\/api\/atak\/pings?/.test(p)) return 'Repère transmis';
    if (/\/api\/atak\/markers?/.test(p)) return 'Marqueur transmis';
    if (/\/api\/atak\/orders?/.test(p)) return 'Ordre transmis';
    if (/\/api\/atak\/medevac/.test(p)) return 'Demande MEDEVAC transmise';
    if (/\/api\/atak\/salute/.test(p)) return 'Compte rendu SALUTE transmis';
    if (/\/api\/atak\/intel/.test(p)) return 'Renseignement transmis';
    if (/\/api\/atak\/activity/.test(p)) return 'Note TOC publiée';
    if (/\/api\/atak\/soi/.test(p)) return 'Plan de fréquences mis à jour';
    if (/\/api\/atak\/fire-teams/.test(p)) return 'Équipe de feu mise à jour';
    if (/\/api\/atak\/mission-plan/.test(p)) return 'Plan de mission mis à jour';
    if (/\/api\/atak\/explosive/.test(p)) return 'Minuterie explosive mise à jour';
    if (/\/api\/atak\/waypoints?/.test(p)) return 'Point de route transmis';
    if (/\/api\/sse\//.test(p)) return 'Dossier SSE mis à jour';
    if (/\/api\/recon\//.test(p)) return 'Photo ou renseignement transmis';
    if (method === 'DELETE') return 'Élément retiré du poste';
    return 'Données transmises au poste';
  }

  function render() {
    var lists = [
      document.getElementById('atak-weblog-list'),
      document.getElementById('atak-weblog-list-liaison')
    ];
    var empties = [
      document.getElementById('atak-weblog-empty'),
      document.getElementById('atak-weblog-empty-liaison')
    ];
    var filtered = entries.filter(function (e) {
      if (filterKind === 'all') return true;
      return e.kind === filterKind;
    });
    var html = filtered.slice().reverse().map(function (e) {
      var kindFr = e.kind === 'incident' ? 'Incident' : 'Remontée';
      var cls = e.kind === 'incident' ? 'atak-weblog-item--incident' : 'atak-weblog-item--remontee';
      var detail = e.detail
        ? '<p class="atak-weblog-detail">' + escapeHtml(e.detail) + '</p>'
        : '';
      return '<li class="atak-weblog-item ' + cls + '">' +
        '<span class="atak-weblog-time">' + escapeHtml(formatTime(e.at)) + '</span>' +
        '<span class="atak-weblog-kind">' + kindFr + '</span>' +
        '<p class="atak-weblog-label">' + escapeHtml(e.label || '') + '</p>' +
        detail +
        '</li>';
    }).join('');
    var incidentCount = 0;
    for (var i = 0; i < entries.length; i++) {
      if (entries[i].kind === 'incident') incidentCount++;
    }
    lists.forEach(function (el) {
      if (!el) return;
      el.innerHTML = html;
    });
    empties.forEach(function (el) {
      if (!el) return;
      el.hidden = filtered.length > 0;
    });
    var badge = document.getElementById('atak-weblog-badge');
    if (badge) {
      badge.hidden = incidentCount < 1;
      badge.textContent = String(incidentCount);
    }
    var countEls = [
      document.getElementById('atak-weblog-count'),
      document.getElementById('atak-weblog-count-liaison')
    ];
    countEls.forEach(function (el) {
      if (!el) return;
      el.textContent = filtered.length ? (filtered.length + ' entrée' + (filtered.length > 1 ? 's' : '')) : '';
    });
    document.querySelectorAll('[data-weblog-filter]').forEach(function (btn) {
      var on = btn.getAttribute('data-weblog-filter') === filterKind;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  function queueFlush(entry) {
    if (!entry || !entry.persist) return;
    pendingFlush.push({
      kind: entry.kind,
      label: entry.label,
      detail: entry.detail || '',
      ingest_kind: entry.ingestKind || 'web'
    });
    if (pendingFlush.length > 80) pendingFlush = pendingFlush.slice(-80);
  }

  function pushEntry(kind, label, opts) {
    opts = opts || {};
    label = String(label || '').replace(/\s+/g, ' ').trim();
    if (!label) return null;
    if (label.length > 280) label = label.slice(0, 280) + '…';
    var detail = String(opts.detail || '').replace(/\s+/g, ' ').trim();
    if (detail.length > 200) detail = detail.slice(0, 200) + '…';
    var fp = kind + '|' + label;
    var last = entries.length ? entries[entries.length - 1] : null;
    if (last && last._fp === fp && (Date.now() - (last._ts || 0)) < 8000) {
      last.repeat = (last.repeat || 1) + 1;
      persist();
      render();
      return last;
    }
    var entry = {
      at: nowIso(),
      kind: kind === 'incident' ? 'incident' : 'remontee',
      label: label,
      detail: detail,
      ingestKind: opts.ingestKind || (kind === 'incident' ? '' : 'web'),
      persist: opts.persist !== false,
      _fp: fp,
      _ts: Date.now()
    };
    entries.push(entry);
    if (entries.length > MAX_ENTRIES) entries = entries.slice(-MAX_ENTRIES);
    persist();
    render();
    queueFlush(entry);
    return entry;
  }

  function incident(label, opts) {
    opts = opts || {};
    if (opts.persist == null) opts.persist = true;
    return pushEntry('incident', label, opts);
  }

  function ingest(label, opts) {
    opts = opts || {};
    if (opts.persist == null) opts.persist = true;
    return pushEntry('remontee', label, opts);
  }

  function flush() {
    if (!pendingFlush.length) return;
    var now = Date.now();
    if (now - minuteStartedAt > 60000) {
      minuteStartedAt = now;
      flushedThisMinute = 0;
    }
    if (flushedThisMinute >= FLUSH_MINUTE_CAP) {
      pendingFlush = pendingFlush.slice(-10);
      return;
    }
    var base = apiBase();
    if (!base) return;
    var batch = pendingFlush.splice(0, FLUSH_MAX);
    flushedThisMinute += batch.length;
    var payload = JSON.stringify({ mapId: mapId(), events: batch });
    var send = origFetch || window.fetch;
    if (typeof send !== 'function') return;
    send.call(window, base + '/api/atak/web-log', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: payload
    }).catch(function () { /* best-effort */ });
  }

  function wrapFetch() {
    if (fetchWrapped || typeof window.fetch !== 'function') return;
    origFetch = window.fetch.bind(window);
    fetchWrapped = true;
    window.fetch = function (input, init) {
      var url = requestUrl(input);
      var method = requestMethod(input, init);
      var skip = isIgnoredUrl(url);
      return origFetch(input, init).then(function (res) {
        if (skip || !isOurApi(url)) return res;
        var status = res ? res.status : 0;
        if (!res || !res.ok) {
          incident(incidentLabelForStatus(status), {
            persist: true,
            detail: method === 'GET' ? 'Lecture des données' : 'Envoi vers le poste'
          });
          return res;
        }
        if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
          ingest(remonteeLabelForPath(pathnameOf(url), method), {
            persist: false,
            ingestKind: 'web'
          });
        }
        return res;
      }).catch(function (err) {
        if (!skip && isOurApi(url)) {
          incident('La liaison avec le poste a été interrompue.', { persist: true });
        }
        throw err;
      });
    };
  }

  function hookShowError() {
    var orig = window.ATAKShowError;
    if (typeof orig !== 'function' || orig._atakWebLogHooked) return;
    var wrapped = function (msg) {
      try {
        incident(String(msg || 'Un incident est survenu sur la carte.'), { persist: true });
      } catch (e) { /* ignore */ }
      return orig.apply(this, arguments);
    };
    wrapped._atakWebLogHooked = true;
    window.ATAKShowError = wrapped;
  }

  function liveCallsigns() {
    if (!window.ATAKUnits || typeof window.ATAKUnits.getUnits !== 'function') return [];
    var list = window.ATAKUnits.getUnits() || [];
    var out = [];
    for (var i = 0; i < list.length; i++) {
      var u = list[i];
      var st = String((u && u.status) || '').toLowerCase();
      if (st === 'offline') continue;
      var cs = String((u && (u.call_sign || u.callsign)) || '').trim();
      if (cs) out.push(cs);
    }
    out.sort();
    return out;
  }

  function onUnitsUpdated(ev) {
    var signs = liveCallsigns();
    var count = signs.length;
    if (ev && ev.detail && typeof ev.detail.count === 'number' && signs.length === 0) {
      count = ev.detail.count;
    }
    var fp = count + '|' + signs.join(',');
    var now = Date.now();
    var newcomers = [];
    for (var i = 0; i < signs.length; i++) {
      if (!lastNewCallsigns[signs[i]]) newcomers.push(signs[i]);
    }
    var nextKnown = {};
    for (var j = 0; j < signs.length; j++) nextKnown[signs[j]] = 1;
    lastNewCallsigns = nextKnown;
    if (fp === lastUnitsFp && newcomers.length === 0) return;
    if (newcomers.length === 0 && lastUnitsAt && (now - lastUnitsAt) < UNITS_THROTTLE_MS) {
      lastUnitsFp = fp;
      return;
    }
    lastUnitsFp = fp;
    lastUnitsAt = now;
    var label;
    if (newcomers.length === 1) {
      label = 'Nouveau contact en liaison — ' + newcomers[0];
    } else if (newcomers.length > 1) {
      label = newcomers.length + ' nouveaux contacts en liaison';
    } else if (count < 1) {
      label = 'Aucun contact en liaison pour le moment';
    } else {
      var preview = signs.slice(0, 4).join(', ');
      label = 'Effectifs en liaison : ' + count + (preview ? ' (' + preview + (signs.length > 4 ? '…' : '') + ')' : '');
    }
    ingest(label, { persist: true, ingestKind: 'effectifs' });
  }

  function onWindowError(ev) {
    incident('Un incident a bloqué l’affichage de la carte.', { persist: true, detail: 'Affichage' });
    if (ev && typeof ev.preventDefault === 'function') { /* keep browser default */ }
  }

  function onRejection() {
    incident('Une action en cours n’a pas pu se terminer.', { persist: true });
  }

  function copyVisible() {
    var filtered = entries.filter(function (e) {
      if (filterKind === 'all') return true;
      return e.kind === filterKind;
    });
    var lines = filtered.map(function (e) {
      var kindFr = e.kind === 'incident' ? 'Incident' : 'Remontée';
      var extra = e.repeat && e.repeat > 1 ? ' (×' + e.repeat + ')' : '';
      return '[' + formatTime(e.at) + '] ' + kindFr + ' — ' + (e.label || '') + extra;
    });
    var text = lines.join('\n');
    function done() {
      if (window.ATAKShowNotification) {
        window.ATAKShowNotification('Journal copié.', { silent: true });
      }
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(function () {
        window.prompt('Copiez le journal', text);
      });
    } else {
      window.prompt('Copiez le journal', text);
    }
  }

  function clearLocal() {
    entries = [];
    pendingFlush = [];
    persist();
    render();
  }

  function bindUi() {
    document.addEventListener('click', function (e) {
      var t = e.target && e.target.closest ? e.target.closest('[data-weblog-filter], [data-weblog-copy], [data-weblog-clear]') : null;
      if (!t) return;
      if (t.hasAttribute('data-weblog-filter')) {
        filterKind = t.getAttribute('data-weblog-filter') || 'all';
        render();
        return;
      }
      if (t.hasAttribute('data-weblog-copy')) {
        e.preventDefault();
        copyVisible();
        return;
      }
      if (t.hasAttribute('data-weblog-clear')) {
        e.preventDefault();
        clearLocal();
      }
    });
  }

  function start() {
    loadStored();
    wrapFetch();
    bindUi();
    hookShowError();
    window.addEventListener('error', onWindowError);
    window.addEventListener('unhandledrejection', onRejection);
    window.addEventListener('atak:units-updated', onUnitsUpdated);
    render();
    if (flushTimer) clearInterval(flushTimer);
    flushTimer = setInterval(flush, FLUSH_INTERVAL_MS);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }

  wrapFetch();

  return {
    log: function (kind, label, opts) {
      return kind === 'incident' || kind === 'error'
        ? incident(label, opts)
        : ingest(label, opts);
    },
    error: incident,
    incident: incident,
    ingest: ingest,
    getEntries: function () { return entries.slice(); },
    refresh: render,
    flush: flush
  };
})();
