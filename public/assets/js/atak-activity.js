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
  /** @type {Array<{id:number,type:string,label:string,actor?:string,at:string}>} */
  var eventsCache = [];
  var PANEL_MAX = 80;
  var liaisonTabActive = false;

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

  function seenStorageKey() {
    return 'atak_liaison_last_seen_m' + getMapId();
  }

  function getLastSeenId() {
    try {
      var v = parseInt(localStorage.getItem(seenStorageKey()) || '0', 10);
      return isNaN(v) ? 0 : v;
    } catch (e) {
      return 0;
    }
  }

  function setLastSeenId(id) {
    try {
      localStorage.setItem(seenStorageKey(), String(id > 0 ? id : 0));
    } catch (e) { /* ignore */ }
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
      case 'client_init':
      case 'disconnect':
        return 'atak-activity-item--init';
      case 'callsign_change': return 'atak-activity-item--callsign';
      case 'position': return 'atak-activity-item--position';
      case 'auth': return 'atak-activity-item--auth';
      case 'phone': return 'atak-activity-item--phone';
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
      case 'order':
      case 'tactical_alert':
        return 'atak-activity-item--tactical';
      default: return '';
    }
  }

  function typeLabelFr(type) {
    switch (type) {
      case 'client_init': return 'Connexion';
      case 'disconnect': return 'Déconnexion';
      case 'callsign_change': return 'Indicatif';
      case 'position': return 'Position';
      case 'auth': return 'Accès';
      case 'phone': return 'Briefing';
      case 'chat': return 'Tchat';
      case 'ping': return 'Ping';
      case 'marker': return 'Marqueur';
      case 'intel': return 'Renseignement';
      case 'nine_line': return '9-Line';
      case 'tactical_alert': return 'Situation';
      case 'designator': return 'Désignateur';
      case 'laser': return 'Laser';
      case 'flight': return 'Vol';
      case 'sigint': return 'SIGINT';
      case 'order': return 'Ordre';
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

  function ymdLocal(d) {
    return d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
  }

  function dayKeyFromIso(iso) {
    var d = new Date(iso);
    if (isNaN(d.getTime())) return 'unknown';
    return ymdLocal(d);
  }

  function dayLabelFr(iso) {
    var d = new Date(iso);
    if (isNaN(d.getTime())) return 'Date inconnue';
    var today = new Date();
    var yest = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
    var key = ymdLocal(d);
    if (key === ymdLocal(today)) return 'Aujourd’hui';
    if (key === ymdLocal(yest)) return 'Hier';
    try {
      return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
    } catch (e) {
      return key;
    }
  }

  function ensureEls() {
    if (!listEl) listEl = document.getElementById('atak-activity-list');
    if (!emptyEl) emptyEl = document.getElementById('atak-activity-empty');
    if (!metaEl) metaEl = document.getElementById('atak-activity-meta');
    if (!metaCountEl) metaCountEl = document.getElementById('atak-activity-meta-count');
  }

  function updateBadge() {
    var badge = document.getElementById('atak-liaison-tab-badge');
    if (!badge) return;
    if (liaisonTabActive) {
      badge.textContent = '';
      badge.hidden = true;
      return;
    }
    var seen = getLastSeenId();
    var n = 0;
    for (var i = 0; i < eventsCache.length; i++) {
      if ((eventsCache[i].id || 0) > seen) n++;
    }
    badge.textContent = n > 0 ? String(n > 99 ? '99+' : n) : '';
    badge.hidden = n <= 0;
  }

  function updateChrome() {
    ensureEls();
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
    updateBadge();
  }

  function renderItem(ev) {
    var type = ev.type || '';
    var li = document.createElement('li');
    li.className = 'atak-activity-item ' + typeClass(type);
    if (ev.archived) li.className += ' atak-activity-item--archived';
    li.setAttribute('data-id', String(ev.id || ''));
    li.setAttribute('data-day', dayKeyFromIso(ev.at));
    var actor = ev.actor ? '<span class="atak-activity-actor">' + escapeHtml(ev.actor) + '</span>' : '';
    li.innerHTML =
      '<span class="atak-activity-rail" aria-hidden="true"></span>' +
      '<div class="atak-activity-body">' +
        '<div class="atak-activity-top">' +
          '<span class="atak-activity-type">' + escapeHtml(typeLabelFr(type)) + '</span>' +
          '<div class="atak-activity-top-actions">' +
            '<span class="atak-activity-time">' + escapeHtml(formatTime(ev.at)) + '</span>' +
            '<button type="button" class="atak-activity-info-btn" data-activity-info="' + escapeHtml(String(ev.id || '')) + '" title="Voir les détails" aria-label="Voir les détails de l’événement">i</button>' +
          '</div>' +
        '</div>' +
        '<div class="atak-activity-label">' + escapeHtml(ev.label || '') + '</div>' +
        actor +
      '</div>';
    var btn = li.querySelector('[data-activity-info]');
    if (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        openEventDetails(ev);
      });
    }
    return li;
  }

  var META_LABELS_FR = {
    call_sign: 'Indicatif',
    callsign: 'Indicatif',
    profile_callsign: 'Indicatif du profil',
    display_name: 'Compte Athena',
    user_id: 'Identifiant compte',
    steam_uid: 'Identifiant Steam',
    mod_version: 'Version Overwatch',
    tenant_id: 'Communauté (n°)',
    map_id: 'Théâtre (n°)',
    grid: 'Grille',
    grid_ref: 'Grille',
    role: 'Rôle',
    group_name: 'Groupe',
    pos_x: 'Position X',
    pos_y: 'Position Y',
    asl_z: 'Altitude',
    heading: 'Cap',
    health: 'État médical',
    side: 'Camp',
    affiliation: 'Affiliation',
    from: 'Ancien indicatif',
    to: 'Nouvel indicatif',
    ok: 'Résultat',
    reason: 'Motif',
    mentions: 'Mentions',
    source: 'Origine',
    kind: 'Type',
    method: 'Méthode',
    path_hint: 'Chemin'
  };

  var META_PRIMARY_ORDER = [
    'display_name', 'user_id', 'call_sign', 'callsign', 'profile_callsign',
    'steam_uid', 'mod_version', 'tenant_id', 'map_id', 'grid', 'grid_ref',
    'role', 'group_name', 'pos_x', 'pos_y', 'asl_z', 'heading', 'health',
    'from', 'to', 'ok', 'reason', 'mentions', 'kind', 'source', 'side', 'affiliation'
  ];

  function formatMetaValue(key, value) {
    if (key === 'ok') return value ? 'Réussi' : 'Échec';
    if (key === 'source') {
      var s = String(value || '');
      if (s === 'arma') return 'Jeu Arma';
      if (s === 'phone') return 'Téléphone';
      if (s === 'admin') return 'État-major';
      return s || '—';
    }
    if (Array.isArray(value)) {
      return value.map(function (v) { return String(v); }).filter(Boolean).join(', ') || '—';
    }
    if (value == null || value === '') return '—';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
  }

  function ensureDetailsModal() {
    var el = document.getElementById('atak-activity-details-modal');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'atak-activity-details-modal';
    el.className = 'atak-activity-details-modal';
    el.hidden = true;
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'atak-activity-details-title');
    el.innerHTML =
      '<div class="atak-activity-details-backdrop" data-activity-details-close="1"></div>' +
      '<div class="atak-activity-details-panel">' +
        '<div class="atak-activity-details-head">' +
          '<h2 id="atak-activity-details-title" class="atak-activity-details-title">Détail de l’événement</h2>' +
          '<button type="button" class="atak-activity-details-close" data-activity-details-close="1" aria-label="Fermer">×</button>' +
        '</div>' +
        '<div class="atak-activity-details-body" id="atak-activity-details-body"></div>' +
      '</div>';
    document.body.appendChild(el);
    el.addEventListener('click', function (e) {
      var t = e.target;
      if (t && t.getAttribute && t.getAttribute('data-activity-details-close')) {
        closeEventDetails();
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && el && !el.hidden) closeEventDetails();
    });
    return el;
  }

  function closeEventDetails() {
    var el = document.getElementById('atak-activity-details-modal');
    if (el) el.hidden = true;
  }

  function openEventDetails(ev) {
    if (!ev) return;
    var modal = ensureDetailsModal();
    var body = document.getElementById('atak-activity-details-body');
    if (!body) return;
    var meta = (ev.meta && typeof ev.meta === 'object') ? ev.meta : {};
    var rows = [];
    rows.push({ label: 'Type', value: typeLabelFr(ev.type || '') });
    rows.push({ label: 'Résumé', value: ev.label || '—' });
    rows.push({ label: 'Acteur', value: ev.actor || '—' });
    rows.push({ label: 'Horodatage', value: formatTime(ev.at) + (ev.at ? ' (' + String(ev.at) + ')' : '') });
    if (ev.archived) {
      rows.push({ label: 'État', value: 'Archivé' });
    }

    var used = {};
    META_PRIMARY_ORDER.forEach(function (key) {
      if (!Object.prototype.hasOwnProperty.call(meta, key)) return;
      if (meta[key] == null || meta[key] === '') return;
      used[key] = true;
      rows.push({
        label: META_LABELS_FR[key] || key,
        value: formatMetaValue(key, meta[key])
      });
    });
    Object.keys(meta).forEach(function (key) {
      if (used[key]) return;
      if (meta[key] == null || meta[key] === '') return;
      if (typeof meta[key] === 'object' && !Array.isArray(meta[key])) return;
      used[key] = true;
      rows.push({
        label: META_LABELS_FR[key] || key,
        value: formatMetaValue(key, meta[key])
      });
    });

    var html = '<dl class="atak-activity-details-dl">';
    for (var i = 0; i < rows.length; i++) {
      html +=
        '<div class="atak-activity-details-row">' +
          '<dt>' + escapeHtml(rows[i].label) + '</dt>' +
          '<dd>' + escapeHtml(rows[i].value) + '</dd>' +
        '</div>';
    }
    html += '</dl>';

    var hasMeta = Object.keys(meta).length > 0;
    html +=
      '<details class="atak-activity-details-tech"' + (hasMeta ? '' : ' open') + '>' +
        '<summary>Détail technique</summary>' +
        '<pre class="atak-activity-details-json">' + escapeHtml(JSON.stringify({
          id: ev.id,
          type: ev.type,
          label: ev.label,
          actor: ev.actor,
          at: ev.at,
          archived: ev.archived || false,
          meta: meta
        }, null, 2)) + '</pre>' +
      '</details>';

    if (!hasMeta) {
      html =
        '<p class="atak-activity-details-empty">Aucune métadonnée enrichie pour cet événement (entrée plus ancienne ou non issue du jeu).</p>' +
        html;
    }

    body.innerHTML = html;
    modal.hidden = false;
  }

  function renderDayHeader(iso) {
    var li = document.createElement('li');
    li.className = 'atak-activity-day';
    li.setAttribute('data-day-header', dayKeyFromIso(iso));
    li.innerHTML = '<span class="atak-activity-day-label">' + escapeHtml(dayLabelFr(iso)) + '</span>';
    return li;
  }

  function rebuildList() {
    ensureEls();
    if (!listEl) return;
    listEl.innerHTML = '';
    if (!eventsCache.length) {
      visibleCount = 0;
      if (emptyEl) emptyEl.hidden = false;
      updateChrome();
      return;
    }
    if (emptyEl) emptyEl.hidden = true;
    var lastDay = null;
    var frag = document.createDocumentFragment();
    for (var i = 0; i < eventsCache.length; i++) {
      var ev = eventsCache[i];
      var day = dayKeyFromIso(ev.at);
      if (day !== lastDay) {
        frag.appendChild(renderDayHeader(ev.at));
        lastDay = day;
      }
      frag.appendChild(renderItem(ev));
    }
    listEl.appendChild(frag);
    visibleCount = eventsCache.length;
    updateChrome();
  }

  function eventKey(ev) {
    return String(ev && ev.id != null ? ev.id : 0) + '|' + String(ev && ev.at ? ev.at : '') + '|' + String(ev && ev.type ? ev.type : '');
  }

  function mergeEvents(incoming, playSound, mapCursor) {
    var hasCursor = mapCursor !== null && mapCursor !== undefined && !isNaN(Number(mapCursor));
    if (!incoming || !incoming.length) {
      if (hasCursor && Number(mapCursor) > lastId) lastId = Number(mapCursor);
      return false;
    }
    var fresh = [];
    for (var i = 0; i < incoming.length; i++) {
      var ev = incoming[i];
      var id = ev && ev.id != null ? Number(ev.id) : 0;
      var key = eventKey(ev);
      if (!id || knownIds[key]) continue;
      knownIds[key] = true;
      fresh.push(ev);
    }
    if (hasCursor) {
      if (Number(mapCursor) > lastId) lastId = Number(mapCursor);
    } else {
      for (var k = 0; k < fresh.length; k++) {
        var fid = Number(fresh[k].id) || 0;
        if (fid > lastId) lastId = fid;
      }
    }
    if (!fresh.length) return false;

    // incoming / fresh : plus récent d’abord
    eventsCache = fresh.concat(eventsCache);
    eventsCache.sort(function (a, b) {
      return (Number(b.id) || 0) - (Number(a.id) || 0);
    });
    if (eventsCache.length > PANEL_MAX) {
      var dropped = eventsCache.slice(PANEL_MAX);
      eventsCache = eventsCache.slice(0, PANEL_MAX);
      for (var d = 0; d < dropped.length; d++) {
        delete knownIds[eventKey(dropped[d])];
      }
    }

    rebuildList();

    if (window.ATAKMedicalAlerts && typeof window.ATAKMedicalAlerts.ingestFromActivityEvents === 'function') {
      // Peuple Assistances depuis le journal Liaison (format « Assistance médicale — … »).
      // apply() affiche bandeau / toast si de nouvelles alertes critiques apparaissent.
      window.ATAKMedicalAlerts.ingestFromActivityEvents(fresh);
    }

    if (liaisonTabActive && lastId > 0) {
      setLastSeenId(lastId);
      updateBadge();
    }

    if (playSound && window.ATAKSounds && typeof window.ATAKSounds.shouldPlayForActivity === 'function') {
      var played = false;
      for (var j = 0; j < fresh.length; j++) {
        var actType = fresh[j].type;
        var actLabel = String((fresh[j] && fresh[j].label) || '');
        // Alerte médicale : son dédié (inconscient / mort) plutôt que le bip générique.
        if (window.ATAKMedicalAlerts && typeof window.ATAKMedicalAlerts.parseMessage === 'function') {
          var med = window.ATAKMedicalAlerts.parseMessage(actLabel);
          if (med && window.ATAKSounds.playEvent) {
            var sk = '';
            var mk = String(med.kind || '').toLowerCase();
            if (mk === 'cardiac_arrest' || mk === 'death' || mk === 'kia' || mk === 'dead') sk = 'death';
            else if (mk === 'unconscious') sk = 'unconscious';
            if (sk) {
              // playEvent respect silence/volume ; le bandeau/toast est géré par ingest/apply.
              window.ATAKSounds.playEvent(sk);
              played = true;
              break;
            }
          }
        }
        // Mention radio : prioriser un toast métier (sans doubler le bip chat générique).
        if (actType === 'chat' && notifyMentionFromActivityEvent(fresh[j])) {
          played = true;
          break;
        }
        if (!window.ATAKSounds.shouldPlayForActivity(actType)) continue;
        if (typeof window.ATAKSounds.playForActivity === 'function') {
          if (window.ATAKSounds.playForActivity(actType)) played = true;
        } else if (typeof window.ATAKSounds.play === 'function') {
          if (window.ATAKSounds.play()) played = true;
        }
        if (played) break;
      }
    }
    return true;
  }

  function myCallsignsUpper() {
    var out = [];
    var u = window.ATAK_USER || {};
    [u.callsign, u.armaCallsign, u.displayName].forEach(function (v) {
      var s = String(v || '').trim().toUpperCase();
      if (s) out.push(s);
    });
    return out;
  }

  /** @returns {boolean} true si toast mention affiché (son géré par ATAKShowNotification) */
  function notifyMentionFromActivityEvent(ev) {
    if (!ev || ev.type !== 'chat') return false;
    var mentions = ev.meta && Array.isArray(ev.meta.mentions) ? ev.meta.mentions : null;
    if (!mentions || !mentions.length) return false;
    var mine = myCallsignsUpper();
    if (!mine.length) return false;
    var actor = String(ev.actor || '').toUpperCase();
    if (mine.indexOf(actor) >= 0) return false;
    var hit = false;
    for (var m = 0; m < mentions.length; m++) {
      if (mine.indexOf(String(mentions[m] || '').toUpperCase()) >= 0) {
        hit = true;
        break;
      }
    }
    if (!hit) return false;
    var dedupeKey = 'mention:' + actor;
    if (window.ATAKChat && typeof window.ATAKChat.consumeMentionToast === 'function') {
      if (!window.ATAKChat.consumeMentionToast(dedupeKey)) return false;
    }
    if (window.ATAKShowNotification) {
      window.ATAKShowNotification((ev.actor || 'Un opérateur') + ' vous a mentionné dans le journal radio.');
    }
    return true;
  }

  /** @deprecated kept as alias for external callers expecting prependEvents */
  function prependEvents(events, playSound) {
    mergeEvents(events, playSound);
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
        var cursor = (data && data.cursor != null) ? Number(data.cursor) : null;
        if (!incremental) {
          knownIds = {};
          lastId = 0;
          eventsCache = [];
          visibleCount = 0;
          if (listEl) listEl.innerHTML = '';
          if (emptyEl) emptyEl.hidden = events.length > 0;
        }
        mergeEvents(events, !!incremental, cursor);
        // Chargement initial : peupler Assistances depuis tout l’historique Liaison visible.
        if (!incremental && window.ATAKMedicalAlerts && typeof window.ATAKMedicalAlerts.ingestFromActivityEvents === 'function') {
          window.ATAKMedicalAlerts.ingestFromActivityEvents(events);
        }
        if (!eventsCache.length && emptyEl) {
          emptyEl.hidden = false;
          visibleCount = 0;
        }
        updateChrome();
      })
      .catch(function () {
        /* silencieux : le panneau santé couvre déjà les pannes */
      });
  }

  function markSeen() {
    if (lastId > 0) setLastSeenId(lastId);
    else if (eventsCache.length) {
      var max = 0;
      for (var i = 0; i < eventsCache.length; i++) {
        if ((eventsCache[i].id || 0) > max) max = eventsCache[i].id;
      }
      setLastSeenId(max);
    }
    updateBadge();
  }

  function setLiaisonTabActive(active) {
    liaisonTabActive = !!active;
    if (liaisonTabActive) markSeen();
    else updateBadge();
  }

  function clearJournal() {
    var base = getApiBase();
    if (!base) return Promise.resolve();
    return fetch(base + '/api/atak/activity/clear', {
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
        knownIds = {};
        lastId = 0;
        eventsCache = [];
        rebuildList();
        return fetchActivity(false);
      })
      .catch(function () {
        if (window.ATAKShowError) {
          window.ATAKShowError('Impossible de mettre le journal de côté pour le moment.');
        }
      });
  }

  function bindPanelActions() {
    var clearBtn = document.getElementById('atak-activity-clear');
    if (clearBtn && !clearBtn._atakBound) {
      clearBtn._atakBound = true;
      clearBtn.addEventListener('click', function () {
        if (!window.confirm('Mettre de côté le journal affiché ? Les entrées resteront consultables dans l’historique archivé.')) {
          return;
        }
        clearJournal();
      });
    }
  }

  function start() {
    ensureEls();
    bindPanelActions();
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
    fetchActivity: fetchActivity,
    markSeen: markSeen,
    setLiaisonTabActive: setLiaisonTabActive,
    clearJournal: clearJournal,
    prependEvents: prependEvents,
    typeLabelFr: typeLabelFr,
    typeClass: typeClass,
    formatTime: formatTime,
    dayLabelFr: dayLabelFr,
    dayKeyFromIso: dayKeyFromIso,
    escapeHtml: escapeHtml,
    openEventDetails: openEventDetails,
    getCachedEvents: function () { return eventsCache.slice(); }
  };
})();
