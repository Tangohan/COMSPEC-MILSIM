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
    return String(repairMojibake(s == null ? '' : s))
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /** Corrige UTF-8 lu comme Latin-1 (ex. envoyÃ© → envoyé). */
  function repairMojibake(s) {
    s = String(s == null ? '' : s);
    if (!/[Ãâ€]/.test(s)) return s;
    try {
      return decodeURIComponent(escape(s));
    } catch (e) {
      return s;
    }
  }

  function eventType(ev) {
    return String((ev && ev.type) || '').toLowerCase();
  }

  function eventActor(ev) {
    var meta = (ev && ev.meta && typeof ev.meta === 'object') ? ev.meta : {};
    var a = String((ev && ev.actor) || '').trim();
    if (a && a.toLowerCase() !== 'unknown') return a;
    return String(meta.profile_callsign || meta.call_sign || meta.callsign || meta.display_name || '').trim();
  }

  function reportTypeLabelFr(code) {
    var t = String(code || '').toUpperCase();
    var labels = {
      SPOTREP: 'Observation',
      SITREP: 'Situation',
      SALUTE: 'Compte rendu SALUTE',
      CONTACT: 'Prise de contact',
      BDA: 'Bilan des dégâts',
      TIC: 'Contact',
      EAGLE_DOWN: 'Opérateur à terre',
      FRAGO: 'Ordre fragmentaire',
      OTHER: 'Rapport'
    };
    if (window.ATAK_REPORT_CATALOG && Array.isArray(window.ATAK_REPORT_CATALOG.types)) {
      for (var i = 0; i < window.ATAK_REPORT_CATALOG.types.length; i++) {
        var row = window.ATAK_REPORT_CATALOG.types[i];
        if (row && String(row.code || '').toUpperCase() === t && row.label) return row.label;
      }
    }
    return labels[t] || 'Rapport';
  }

  function typeClass(type) {
    switch (String(type || '').toLowerCase()) {
      case 'client_init':
      case 'disconnect':
        return 'atak-activity-item--init';
      case 'callsign_change': return 'atak-activity-item--callsign';
      case 'position': return 'atak-activity-item--position';
      case 'auth': return 'atak-activity-item--auth';
      case 'phone': return 'atak-activity-item--phone';
      case 'error': return 'atak-activity-item--error';
      case 'ingest': return 'atak-activity-item--ingest';
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
      case 'toc_note':
      case 'medevac':
      case 'fire_team':
      case 'tactical_report':
        return 'atak-activity-item--tactical';
      default: return '';
    }
  }

  function typeLabelFr(type, ev) {
    var labelHint = String((ev && ev.label) || '');
    switch (String(type || '').toLowerCase()) {
      case 'client_init': return 'Connexion';
      case 'disconnect': return 'Déconnexion';
      case 'callsign_change': return 'Indicatif';
      case 'position': return 'Position';
      case 'auth': return 'Accès';
      case 'phone': return 'Briefing';
      case 'error': return 'Incident';
      case 'ingest': return 'Remontée';
      case 'chat': return 'Radio';
      case 'ping': return 'Repère';
      case 'marker': return 'Marqueur';
      case 'intel': return 'Renseignement';
      case 'nine_line': return '9-Line';
      case 'medevac': return 'MEDEVAC';
      case 'tactical_alert':
        if (/réglages|affichage/i.test(labelHint)) return 'Carte';
        var kindMeta = String((ev && ev.meta && ev.meta.kind) || '').toLowerCase();
        if (kindMeta === 'frago') return 'FRAGO';
        if (kindMeta === 'eagle_down') return 'À terre';
        if (kindMeta === 'bda') return 'BDA';
        if (kindMeta === 'salute') return 'SALUTE';
        if (kindMeta === 'tic') return 'Contact';
        if (kindMeta === 'tic_clear') return 'Fin contact';
        if (/fragmentaire|frago/i.test(labelHint)) return 'FRAGO';
        if (/à terre|eagle/i.test(labelHint)) return 'À terre';
        if (/salute/i.test(labelHint)) return 'SALUTE';
        if (/dégâts|bda/i.test(labelHint)) return 'BDA';
        if (/fin de contact/i.test(labelHint)) return 'Fin contact';
        if (/^contact\b/i.test(labelHint)) return 'Contact';
        return 'Alerte';
      case 'toc_note': return 'Note TOC';
      case 'fire_team': return 'Équipe';
      case 'designator': return 'Désignateur';
      case 'laser': return 'Laser';
      case 'flight': return 'Vol';
      case 'sigint': return 'Écoute';
      case 'order': return 'Ordre';
      case 'tactical_report': return 'Rapport';
      default: return 'Activité';
    }
  }

  /** Résumé d’une ligne, coupé sur un mot (pas au milieu). */
  function summaryLine(text, maxLen) {
    var s = String(text || '').replace(/\s+/g, ' ').trim();
    var limit = maxLen > 0 ? maxLen : 96;
    if (s.length <= limit) return s;
    var cut = s.slice(0, limit);
    var sp = cut.lastIndexOf(' ');
    if (sp >= Math.floor(limit * 0.55)) cut = cut.slice(0, sp);
    return cut.replace(/[.,;:–—-]+$/, '') + '…';
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

  function isStaleEvent(ev) {
    if (!ev || !ev.at) return false;
    var windowSec = (typeof window.ATAK_ACTIVITY_STALE_SECONDS === 'number' && window.ATAK_ACTIVITY_STALE_SECONDS > 0)
      ? window.ATAK_ACTIVITY_STALE_SECONDS
      : (30 * 60); // aligné MedicalAlertParser::ACTIVE_WINDOW_SECONDS
    var d = new Date(String(ev.at).replace(' ', 'T') + (String(ev.at).indexOf('Z') >= 0 || String(ev.at).indexOf('+') >= 0 ? '' : 'Z'));
    if (isNaN(d.getTime())) d = new Date(ev.at);
    if (isNaN(d.getTime())) return false;
    return (Date.now() - d.getTime()) > (windowSec * 1000);
  }

  var EYE_SVG = '<svg class="atak-activity-jump-icon" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 5c-5.5 0-9.7 4.2-11 7 1.3 2.8 5.5 7 11 7s9.7-4.2 11-7c-1.3-2.8-5.5-7-11-7zm0 11.5A4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 0 1 0 9zm0-2.2a2.3 2.3 0 1 0 0-4.6 2.3 2.3 0 0 0 0 4.6z"/></svg>';

  function isMedicalActivity(ev) {
    var type = eventType(ev);
    if (type === 'medevac' || type === 'nine_line') return true;
    var label = String((ev && ev.label) || '');
    if (/assistance m[ée]dicale|triage m[ée]dical|m[ée]devac/i.test(label)) return true;
    var meta = (ev && ev.meta && typeof ev.meta === 'object') ? ev.meta : {};
    var kind = String(meta.kind || '').toLowerCase();
    return ['unconscious', 'cardiac_arrest', 'death', 'kia', 'dead', 'injured', 'wounded'].indexOf(kind) >= 0;
  }

  function resolveJumpTarget(ev) {
    if (!ev) return null;
    var type = eventType(ev);
    var meta = (ev.meta && typeof ev.meta === 'object') ? ev.meta : {};
    var chatId = meta.chat_id != null && meta.chat_id !== '' ? String(meta.chat_id) : '';
    var orderId = meta.order_id != null && meta.order_id !== '' ? String(meta.order_id) : '';
    var callSign = String(meta.call_sign || meta.callsign || ev.actor || '').trim();

    if (type === 'order' || orderId) {
      return { tab: 'orders', orderId: orderId, title: 'Voir l’ordre' };
    }
    if (type === 'tactical_alert') {
      return { tab: 'chat', chatId: chatId, callSign: callSign, openTactical: true, title: 'Voir le signalement' };
    }
    if (isMedicalActivity(ev)) {
      return { tab: 'medical', chatId: chatId, callSign: callSign, title: 'Voir l’onglet Médical' };
    }
    if (type === 'chat' || type === 'ping') {
      return { tab: 'chat', chatId: chatId, callSign: callSign, title: 'Voir le message' };
    }
    if (type === 'phone' || type === 'sigint') {
      return { tab: 'radio', title: 'Ouvrir la radio' };
    }
    if (type === 'marker') {
      return { tab: 'markers', title: 'Ouvrir les marqueurs' };
    }
    if (type === 'intel') {
      return { tab: 'personnes', title: 'Ouvrir les personnes' };
    }
    if (type === 'toc_note') {
      return { tab: 'notes', title: 'Ouvrir les notes' };
    }
    if (type === 'designator' || type === 'laser' || type === 'flight') {
      return { tab: 'jtac', title: 'Ouvrir JTAC' };
    }
    if (type === 'client_init' || type === 'disconnect' || type === 'callsign_change' || type === 'auth' || type === 'fire_team') {
      return { tab: 'etat', callSign: callSign, title: 'Voir l’état du personnel' };
    }
    return null;
  }

  function selectAtakTab(tab) {
    if (!tab) return;
    if (window.ATAKPanelChrome && typeof window.ATAKPanelChrome.selectTab === 'function') {
      try { window.ATAKPanelChrome.selectTab(tab); return; } catch (e) { /* fall through */ }
    }
    var btn = document.querySelector('#atak-panel-left .atak-tab[data-tab="' + tab + '"]')
      || document.querySelector('.atak-tab[data-tab="' + tab + '"]');
    if (btn) {
      try { btn.click(); } catch (e2) { /* ignore */ }
    }
  }

  function jumpToEvent(ev) {
    var target = resolveJumpTarget(ev);
    if (!target) {
      openEventDetails(ev);
      return;
    }
    if (target.openTactical && window.TacmapTacticalAlerts && typeof window.TacmapTacticalAlerts.openDetail === 'function') {
      var alertObj = alertFromActivityEvent(ev);
      if (alertObj) {
        window.TacmapTacticalAlerts.openDetail(alertObj, function (x, y) {
          try {
            window.dispatchEvent(new CustomEvent('atak:locate', { detail: { x: x, y: y } }));
          } catch (err) { /* ignore */ }
        });
        return;
      }
    }
    selectAtakTab(target.tab);
    setTimeout(function () {
      if (target.tab === 'orders' && target.orderId) {
        if (window.ATAKOrders && typeof window.ATAKOrders.openOrder === 'function') {
          window.ATAKOrders.openOrder(target.orderId);
        } else if (typeof window.ATAKOpenOrder === 'function') {
          window.ATAKOpenOrder(target.orderId);
        }
        return;
      }
      if (target.tab === 'chat' && window.ATAKChat && typeof window.ATAKChat.focusMessage === 'function') {
        window.ATAKChat.focusMessage(target.chatId, { callSign: target.callSign });
        return;
      }
      if (target.tab === 'medical' && window.ATAKMedicalAlerts && typeof window.ATAKMedicalAlerts.focusFromActivity === 'function') {
        window.ATAKMedicalAlerts.focusFromActivity({
          chatId: target.chatId,
          callSign: target.callSign
        });
        return;
      }
      if (target.callSign && typeof window.focusUnitByCallsign === 'function') {
        try { window.focusUnitByCallsign(target.callSign); } catch (e3) { /* ignore */ }
      }
    }, 140);
  }

  function certStatusLabelFr(raw) {
    var s = String(raw || '').toLowerCase();
    var map = {
      active: 'Certificat actif',
      issued: 'Certificat délivré',
      valid: 'Certificat valide',
      pending: 'Certificat en attente',
      expired: 'Certificat expiré',
      revoked: 'Certificat révoqué',
      missing: 'Sans certificat',
      inactive: 'Certificat inactif',
      lost: 'Certificat perdu'
    };
    return map[s] || (s ? ('Certificat · ' + s) : '');
  }

  function linkStateLabelFr(raw) {
    var s = String(raw || '').toLowerCase();
    var map = {
      ok: 'Liaison OK',
      linked: 'Liaison établie',
      connected: 'Connecté',
      degraded: 'Liaison dégradée',
      poor: 'Liaison faible',
      lost: 'Liaison perdue',
      offline: 'Hors liaison',
      disconnected: 'Déconnecté'
    };
    return map[s] || (s ? ('Liaison · ' + s) : '');
  }

  function buildMetaChipsHtml(ev) {
    var meta = (ev && ev.meta && typeof ev.meta === 'object') ? ev.meta : {};
    var chips = [];
    function pushChip(cls, text) {
      var t = String(text || '').trim();
      if (!t) return;
      chips.push('<span class="atak-activity-chip' + (cls ? ' ' + cls : '') + '">' + escapeHtml(t) + '</span>');
    }
    if (meta.latency_ms != null && meta.latency_ms !== '' && !isNaN(Number(meta.latency_ms))) {
      pushChip('atak-activity-chip--ms', String(Math.round(Number(meta.latency_ms))) + ' ms');
    }
    if (meta.cert_status) {
      pushChip('atak-activity-chip--cert', certStatusLabelFr(meta.cert_status));
    } else if (meta.certificate_ref) {
      pushChip('atak-activity-chip--cert', 'Certificat ' + String(meta.certificate_ref));
    }
    if (meta.radio_freq) {
      var freq = String(meta.radio_freq).trim();
      if (freq && !/mhz|hz/i.test(freq) && /^[\d.]+$/.test(freq)) freq += ' MHz';
      pushChip('atak-activity-chip--freq', freq);
    }
    if (meta.link_state) {
      pushChip('atak-activity-chip--link', linkStateLabelFr(meta.link_state));
    }
    if (meta.packet_loss != null && meta.packet_loss !== '' && !isNaN(Number(meta.packet_loss))) {
      var pl = Number(meta.packet_loss);
      if (pl > 0) pushChip('atak-activity-chip--loss', pl + ' % perte');
    }
    var grid = String(meta.grid || meta.grid_ref || '').trim();
    if (grid) pushChip('atak-activity-chip--grid', 'Grille ' + grid);
    if (meta.group_name) pushChip('atak-activity-chip--group', String(meta.group_name));
    else if (meta.group_id) pushChip('atak-activity-chip--group', 'Groupe ' + String(meta.group_id));
    if (meta.kind_label) pushChip('', String(meta.kind_label));
    else if (meta.health) pushChip('', formatMetaValue('health', meta.health));
    if (meta.mod_version) pushChip('atak-activity-chip--mod', 'Overwatch ' + String(meta.mod_version));
    if (!chips.length) return '';
    return '<div class="atak-activity-chips">' + chips.join('') + '</div>';
  }

  function renderItem(ev) {
    var type = eventType(ev);
    var li = document.createElement('li');
    li.className = 'atak-activity-item ' + typeClass(type);
    if (ev.archived) li.className += ' atak-activity-item--archived';
    var stale = !ev.archived && isStaleEvent(ev);
    if (stale) li.className += ' atak-activity-item--stale';
    li.setAttribute('data-id', String(ev.id || ''));
    li.setAttribute('data-day', dayKeyFromIso(ev.at));
    var actorName = eventActor(ev);
    var actorHtml = actorName
      ? '<p class="atak-activity-actor-line"><span class="atak-activity-actor-k">Par</span> <span class="atak-activity-actor">' + escapeHtml(actorName) + '</span></p>'
      : '';
    var staleTag = stale ? '<span class="atak-activity-stale-tag">Ancien</span>' : '';
    var archivedTag = ev.archived ? '<span class="atak-activity-archived-tag">Archivé</span>' : '';
    var ftChip = '';
    var meta = ev.meta && typeof ev.meta === 'object' ? ev.meta : {};
    var ftColor = String(meta.fire_team_color || '').trim();
    var ftLabel = String(meta.fire_team_label || '').trim();
    if (type === 'fire_team' || ftColor || ftLabel) {
      var colorStyle = ftColor ? (' style="--ft-color:' + escapeHtml(ftColor) + ';border-color:' + escapeHtml(ftColor) + ';color:' + escapeHtml(ftColor) + '"') : '';
      ftChip = '<span class="atak-ft-chip"' + colorStyle + '>'
        + (ftColor ? '<span class="atak-ft-chip-dot" aria-hidden="true"></span>' : '')
        + escapeHtml(ftLabel || 'Équipe')
        + '</span>';
    }
    if (type === 'fire_team' && ftColor) {
      li.style.setProperty('--ft-color', ftColor);
      li.className += ' atak-activity-item--fire-team';
    }
    var chipsHtml = buildMetaChipsHtml(ev);
    var labelText = activityLabelPreview(ev);
    var labelPreview = summaryLine(labelText, 110);
    var actorStr = eventActor(ev);
    var actorRedundant = !!(actorStr && labelText.toLowerCase().indexOf(actorStr.toLowerCase()) >= 0);
    var needsExpand = labelText.length > labelPreview.length || !!ftChip || !!chipsHtml || (!!actorStr && !actorRedundant);
    var foldKey = 'act-' + String(ev.id || (ev.at || '') + '-' + type);
    var typeFr = typeLabelFr(type, ev);
    var infoBtnLabel = type === 'tactical_alert' ? 'Ouvrir' : 'Fiche';
    var infoBtnTitle = type === 'tactical_alert' ? 'Ouvrir le signalement' : 'Ouvrir la fiche';
    var jump = resolveJumpTarget(ev);
    var jumpBtn = jump
      ? ('<button type="button" class="atak-activity-jump-btn" data-activity-jump="' + escapeHtml(String(ev.id || '')) + '" title="' + escapeHtml(jump.title || 'Voir dans l’onglet') + '" aria-label="' + escapeHtml(jump.title || 'Voir dans l’onglet') + '">' + EYE_SVG + '</button>')
      : '';
    li.innerHTML =
      '<span class="atak-activity-rail" aria-hidden="true"></span>' +
      '<details class="atak-activity-fold' + (needsExpand ? '' : ' atak-activity-fold--plain') + '" data-atak-collapse="' + escapeHtml(foldKey) + '" data-atak-collapse-default="0">' +
        '<summary class="atak-activity-fold-sum"' + (needsExpand ? '' : ' tabindex="-1"') + '>' +
          '<div class="atak-activity-top">' +
            '<span class="atak-activity-type">' + escapeHtml(typeFr) + staleTag + archivedTag + '</span>' +
            '<div class="atak-activity-top-actions">' +
              '<span class="atak-activity-time">' + escapeHtml(formatTime(ev.at)) + '</span>' +
              jumpBtn +
              '<button type="button" class="atak-activity-info-btn" data-activity-info="' + escapeHtml(String(ev.id || '')) + '" title="' + escapeHtml(infoBtnTitle) + '" aria-label="' + escapeHtml(infoBtnTitle) + '">' + escapeHtml(infoBtnLabel) + '</button>' +
            '</div>' +
          '</div>' +
          (labelPreview
            ? '<p class="atak-activity-label atak-activity-label--preview">' + escapeHtml(labelPreview) + '</p>'
            : '<p class="atak-activity-label atak-activity-label--preview atak-activity-label--empty">Événement sans résumé</p>') +
          chipsHtml +
          (needsExpand
            ? '<span class="atak-activity-fold-hint"><span class="atak-activity-fold-hint-open">Voir le détail</span><span class="atak-activity-fold-hint-close">Masquer</span></span>'
            : '') +
        '</summary>' +
        '<div class="atak-activity-fold-body">' +
          (labelText
            ? '<p class="atak-activity-label">' + escapeHtml(labelText) + '</p>'
            : '') +
          ftChip +
          actorHtml +
        '</div>' +
      '</details>';
    var infoBtns = li.querySelectorAll('[data-activity-info]');
    for (var bi = 0; bi < infoBtns.length; bi++) {
      infoBtns[bi].addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (type === 'tactical_alert' && window.TacmapTacticalAlerts && typeof window.TacmapTacticalAlerts.openDetail === 'function') {
          var alertObj = alertFromActivityEvent(ev);
          if (alertObj) {
            window.TacmapTacticalAlerts.openDetail(alertObj, function (x, y) {
              try {
                window.dispatchEvent(new CustomEvent('atak:locate', { detail: { x: x, y: y } }));
              } catch (err) { /* ignore */ }
            });
            return;
          }
        }
        openEventDetails(ev);
      });
    }
    var jumpBtns = li.querySelectorAll('[data-activity-jump]');
    for (var ji = 0; ji < jumpBtns.length; ji++) {
      jumpBtns[ji].addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        jumpToEvent(ev);
      });
    }
    var fold = li.querySelector('.atak-activity-fold');
    if (fold && needsExpand && window.ATAKCollapse && typeof window.ATAKCollapse.apply === 'function') {
      window.ATAKCollapse.apply(fold, foldKey, false);
    }
    if (fold && !needsExpand) {
      fold.open = false;
      var sum = fold.querySelector('summary');
      if (sum) {
        sum.addEventListener('click', function (e) {
          if (e.target && e.target.closest && e.target.closest('[data-activity-info], [data-activity-jump]')) return;
          e.preventDefault();
        });
      }
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
    has_ctab: 'Tablette cTab',
    has_atak_enhanced: 'ATAK Enhanced',
    has_athena_ctab: 'Application Athena (cTab)',
    mod_athena: 'Mod Athena',
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
    kind_label: 'Libellé',
    order_id: 'Ordre lié',
    summary: 'Détail',
    method: 'Méthode',
    path_hint: 'Chemin',
    action: 'Action',
    fire_team_id: 'Équipe (n°)',
    fire_team_label: 'Équipe',
    fire_team_color: 'Couleur d’équipe',
    fire_team_kind: 'Type d’équipe',
    member_callsign: 'Membre',
    member_user_id: 'Membre (compte)',
    member_role: 'Rôle dans l’équipe',
    member_count: 'Effectif',
    added: 'Ajouts',
    removed: 'Retraits',
    terminal_uid: 'Terminal',
    cert_status: 'Certificat',
    certificate_ref: 'Référence certificat',
    report_type: 'Type de rapport',
    report_number: 'N° rapport',
    priority: 'Priorité',
    classification: 'Classification',
    details: 'Détails',
    remarks: 'Remarques',
    grid_reference: 'Grille',
    location_description: 'Localisation',
    dtg: 'DTG',
    event_timestamp: 'Heure signalée',
    report_size: 'Taille',
    report_activity: 'Activité',
    report_location: 'Localisation observée',
    report_unit: 'Unité / uniforme',
    report_time: 'Heure observée',
    report_equipment: 'Équipement',
    radio_freq: 'Fréquence radio',
    link_state: 'État de liaison',
    latency_ms: 'Latence',
    packet_loss: 'Perte de paquets',
    packets_sent: 'Paquets envoyés',
    packets_received: 'Paquets reçus',
    chat_id: 'Message lié',
    group_id: 'Groupe',
    channel: 'Canal'
  };

  var META_PRIMARY_ORDER = [
    'display_name', 'user_id', 'call_sign', 'callsign', 'profile_callsign',
    'steam_uid', 'mod_version', 'tenant_id', 'map_id', 'grid', 'grid_ref',
    'role', 'group_name', 'radio_freq', 'latency_ms', 'packet_loss',
    'cert_status', 'certificate_ref', 'link_state', 'terminal_uid',
    'pos_x', 'pos_y', 'asl_z', 'heading', 'health',
    'from', 'to', 'ok', 'reason', 'mentions', 'kind', 'kind_label', 'order_id', 'summary', 'source', 'side', 'affiliation',
    'action', 'report_type', 'report_number', 'priority', 'classification', 'details', 'remarks',
    'grid_reference', 'location_description', 'dtg', 'event_timestamp',
    'report_size', 'report_activity', 'report_location', 'report_unit', 'report_time', 'report_equipment',
    'fire_team_label', 'fire_team_color', 'member_callsign', 'member_role',
    'member_count', 'added', 'removed', 'chat_id'
  ];

  var META_SKIP = {
    tenant_id: 1, map_id: 1, user_id: 1, steam_uid: 1,
    routing_error: 1, routing_enabled: 1, routing_rules_applied: 1, routing_routes_created: 1
  };

  function activityLabelPreview(ev) {
    var type = eventType(ev);
    var raw = String((ev && ev.label) || '').trim();
    var meta = (ev && ev.meta && typeof ev.meta === 'object') ? ev.meta : {};
    if (type === 'tactical_report') {
      var rt = reportTypeLabelFr(meta.report_type);
      var sum = String(meta.summary || meta.details || '').trim();
      if (!sum) {
        sum = raw.replace(/^rapport\s+\w+\s+soumis\s*:\s*/i, '').trim();
      }
      if (sum && sum.toLowerCase() !== rt.toLowerCase()) return rt + ' — ' + sum;
      if (raw && !/^rapport\s+other\s+soumis/i.test(raw)) return raw;
      return rt;
    }
    if (type !== 'tactical_alert') return raw;
    var kind = String(meta.kind || '').toLowerCase();
    if (!kind) {
      if (/fragmentaire|\bfrago\b/i.test(raw)) kind = 'frago';
      else if (/à terre|eagle/i.test(raw)) kind = 'eagle_down';
      else if (/\bbda\b|dégâts/i.test(raw)) kind = 'bda';
      else if (/salute/i.test(raw)) kind = 'salute';
      else if (/fin de contact/i.test(raw)) kind = 'tic_clear';
      else if (/^contact\b/i.test(raw)) kind = 'tic';
    }
    var callSign = String(meta.call_sign || meta.callsign || ev.actor || '');
    var grid = String(meta.grid || meta.grid_ref || '');
    if (!grid) {
      var gm = raw.match(/Grille\s+(\S+)/i);
      if (gm) grid = gm[1];
    }
    var summary = String(meta.summary || raw || '');
    var kindLabel = String(meta.kind_label || '').trim();
    if (!kindLabel) {
      var labels = {
        frago: 'Ordre fragmentaire', bda: 'Bilan des dégâts', eagle_down: 'Opérateur à terre',
        tic: 'Contact', tic_clear: 'Fin de contact', salute: 'Compte rendu SALUTE'
      };
      kindLabel = labels[kind] || typeLabelFr('tactical_alert', ev) || 'Alerte';
    }
    if (window.TacmapTacticalAlerts && typeof window.TacmapTacticalAlerts.cleanSummary === 'function') {
      var cleaned = window.TacmapTacticalAlerts.cleanSummary(summary, kind || 'tic', callSign, grid);
      var alertLike = {
        kind: kind || 'tic',
        kind_label: kindLabel,
        call_sign: callSign,
        grid: grid,
        summary: cleaned,
        frago: meta.frago,
        salute: meta.salute,
      };
      if (typeof window.TacmapTacticalAlerts.bodyPreview === 'function') {
        var preview = window.TacmapTacticalAlerts.bodyPreview(alertLike);
        if (preview && preview !== 'Aucun détail textuel.' && preview.toLowerCase() !== kindLabel.toLowerCase()) {
          return kindLabel + ' — ' + preview;
        }
      }
      if (cleaned && cleaned.toLowerCase() !== kindLabel.toLowerCase()) {
        return kindLabel + (grid ? ' — Grille ' + grid : '') + ' — ' + cleaned;
      }
    }
    return kindLabel + (grid ? ' — Grille ' + grid : '');
  }

  function alertFromActivityEvent(ev) {
    if (!ev) return null;
    var meta = (ev.meta && typeof ev.meta === 'object') ? ev.meta : {};
    var raw = String(ev.label || '');
    var kind = String(meta.kind || '').toLowerCase();
    if (!kind) {
      if (/fragmentaire|\bfrago\b/i.test(raw)) kind = 'frago';
      else if (/à terre|eagle/i.test(raw)) kind = 'eagle_down';
      else if (/\bbda\b|dégâts/i.test(raw)) kind = 'bda';
      else if (/salute/i.test(raw)) kind = 'salute';
      else if (/fin de contact/i.test(raw)) kind = 'tic_clear';
      else if (/^contact\b/i.test(raw)) kind = 'tic';
      else kind = 'tic';
    }
    var callSign = String(meta.call_sign || meta.callsign || ev.actor || '');
    var grid = String(meta.grid || meta.grid_ref || '');
    if (!grid) {
      var gm = raw.match(/Grille\s+(\S+)/i);
      if (gm) grid = gm[1];
    }
    var labels = {
      frago: 'Ordre fragmentaire', bda: 'Bilan des dégâts', eagle_down: 'Opérateur à terre',
      tic: 'Contact', tic_clear: 'Fin de contact', salute: 'Compte rendu SALUTE'
    };
    var summary = String(meta.summary || '');
    if (!summary && raw) {
      summary = raw;
    }
    if (window.TacmapTacticalAlerts && window.TacmapTacticalAlerts.cleanSummary) {
      summary = window.TacmapTacticalAlerts.cleanSummary(summary, kind, callSign, grid);
    }
    return {
      id: ev.id,
      kind: kind,
      kind_label: meta.kind_label || labels[kind] || typeLabelFr('tactical_alert', ev),
      call_sign: callSign,
      author: ev.actor || '',
      grid: grid,
      summary: summary,
      order_id: meta.order_id || undefined,
      pos_x: meta.pos_x,
      pos_y: meta.pos_y,
      frago: meta.frago,
      salute: meta.salute,
      bda: meta.bda,
      created_at: ev.at,
      severity: (kind === 'eagle_down' || kind === 'tic') ? 'critical' : 'high',
    };
  }

  function formatMetaValue(key, value) {
    if (key === 'report_type') {
      return reportTypeLabelFr(value);
    }
    if (key === 'priority') {
      var p = String(value || '').toUpperCase();
      var pFr = { ROUTINE: 'Routine', PRIORITY: 'Prioritaire', IMMEDIATE: 'Immédiat', FLASH: 'Flash' };
      return pFr[p] || String(value || '—');
    }
    if (key === 'classification') {
      var c = String(value || '').toUpperCase();
      if (c === 'UNCLASSIFIED') return 'Non classifié';
      return String(value || '—');
    }
    if (key === 'ok') return value ? 'Réussi' : 'Échec';
    if (key === 'has_ctab' || key === 'has_atak_enhanced' || key === 'has_athena_ctab' || key === 'mod_athena') {
      return value ? 'Oui' : 'Non';
    }
    if (key === 'cert_status') {
      return certStatusLabelFr(value) || '—';
    }
    if (key === 'link_state') {
      return linkStateLabelFr(value) || '—';
    }
    if (key === 'latency_ms' && value != null && value !== '' && !isNaN(Number(value))) {
      return Math.round(Number(value)) + ' ms';
    }
    if (key === 'packet_loss' && value != null && value !== '' && !isNaN(Number(value))) {
      return Number(value) + ' %';
    }
    if (key === 'radio_freq') {
      var freq = String(value || '').trim();
      if (!freq) return '—';
      if (!/mhz|hz/i.test(freq) && /^[\d.]+$/.test(freq)) return freq + ' MHz';
      return freq;
    }
    if (key === 'action') {
      var a = String(value || '');
      var actionFr = {
        created: 'Création',
        updated: 'Mise à jour',
        color_changed: 'Changement de couleur',
        dissolved: 'Dissolution',
        member_added: 'Attribution',
        member_updated: 'Changement de membre',
        member_removed: 'Retrait',
        roster_changed: 'Changement d’effectif'
      };
      return actionFr[a] || a || '—';
    }
    if (key === 'source') {
      var s = String(value || '').toLowerCase();
      if (s === 'arma') return 'Jeu';
      if (s === 'web') return 'Portail';
      if (s === 'phone') return 'Téléphone';
      if (s === 'admin') return 'État-major';
      return String(value || '—');
    }
    if (key === 'fire_team_color' && typeof value === 'string' && /^#[0-9A-Fa-f]{6}$/.test(value.trim())) {
      return value.trim().toUpperCase();
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
          '<h2 id="atak-activity-details-title" class="atak-activity-details-title">Fiche de l’événement</h2>' +
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
    rows.push({ label: 'Catégorie', value: typeLabelFr(ev.type || '', ev) });
    rows.push({ label: 'Résumé', value: activityLabelPreview(ev) || '—' });
    rows.push({ label: 'Auteur', value: eventActor(ev) || '—' });
    rows.push({ label: 'Heure', value: formatTime(ev.at) });
    if (ev.archived) {
      rows.push({ label: 'État', value: 'Archivé' });
    }

    var used = {};
    META_PRIMARY_ORDER.forEach(function (key) {
      if (META_SKIP[key]) return;
      if (!Object.prototype.hasOwnProperty.call(meta, key)) return;
      if (meta[key] == null || meta[key] === '') return;
      used[key] = true;
      rows.push({
        label: META_LABELS_FR[key] || key,
        value: formatMetaValue(key, meta[key])
      });
    });
    Object.keys(meta).forEach(function (key) {
      if (used[key] || META_SKIP[key]) return;
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
        '<summary>Informations avancées (support)</summary>' +
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
    var key = dayKeyFromIso(iso);
    var today = ymdLocal(new Date());
    var hint = '';
    if (key === today) {
      hint = '<span class="atak-activity-day-hint">Derniers événements de la session</span>';
    } else if (key !== 'unknown') {
      hint = '<span class="atak-activity-day-hint">Événements de cette journée</span>';
    }
    li.innerHTML =
      '<span class="atak-activity-day-label">' + escapeHtml(dayLabelFr(iso)) + '</span>' +
      hint;
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
      // Sync BFT « Position envoyée » : bruit de fond, hors panneau Activité.
      if (ev && String(ev.type || '') === 'position') continue;
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

    try {
      window.dispatchEvent(new CustomEvent('atak:activity-fresh', {
        detail: { events: fresh, incremental: !!playSound }
      }));
    } catch (eAct) {}

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
        // Demande d'évacuation médicale : son MEDEVAC dédié.
        if (actType === 'medevac' && window.ATAKSounds.playEvent) {
          window.ATAKSounds.playEvent('medevac', { priority: true });
          played = true;
          break;
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
    var tocForm = document.getElementById('atak-toc-form');
    if (tocForm && !tocForm._atakBound) {
      tocForm._atakBound = true;
      tocForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var input = document.getElementById('atak-toc-note');
        var note = input ? String(input.value || '').trim() : '';
        if (!note) {
          if (window.ATAKShowError) window.ATAKShowError('Saisissez le texte de l’entrée TOC.');
          return;
        }
        var base = getApiBase();
        if (!base) return;
        var btn = document.getElementById('atak-toc-submit');
        if (btn) btn.disabled = true;
        fetch(base + '/api/atak/activity', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ mapId: getMapId(), note: note })
        })
          .then(function (r) {
            if (!r.ok) throw new Error('toc');
            return r.json();
          })
          .then(function () {
            if (input) input.value = '';
            return fetchActivity(false);
          })
          .catch(function () {
            if (window.ATAKShowError) window.ATAKShowError('Impossible d’ajouter l’entrée au journal.');
          })
          .finally(function () {
            if (btn) btn.disabled = false;
          });
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
    repairMojibake: repairMojibake,
    openEventDetails: openEventDetails,
    getCachedEvents: function () { return eventsCache.slice(); }
  };
})();
