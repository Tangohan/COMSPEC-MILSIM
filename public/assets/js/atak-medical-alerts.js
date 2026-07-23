/* COMSPEC — Assistances médicales (alertes mod → portail) */
window.ATAKMedicalAlerts = (function () {
  'use strict';

  var lastFingerprint = '';
  var lastToastedAlertKey = '';
  var pollTimer = null;
  var lastData = null;
  var LS_PREFIX = 'atak_medical_dismissed_v1_';
  var LS_FILTER = 'atak_medical_filter_v1';
  var boundUi = false;
  var ACTIVE_WINDOW_MS = 30 * 60 * 1000;
  // Toast/son : uniquement pour une alerte réellement fraîche (pas un vieux message de tchat
  // rejoué par un rechargement de page ou un resync après reconnexion).
  var TOAST_MAX_AGE_MS = 2 * 60 * 1000;
  var canTriageCached = null;
  /** Filtre onglets : urgences | suivi | autres */
  var activeFilter = 'urgences';

  var TRIAGE_OPTIONS = [
    { value: 'a_secourir', label: 'À secourir' },
    { value: 'en_cours', label: 'En cours' },
    { value: 'traite', label: 'Traité' },
    { value: 'kia', label: 'KIA' },
    { value: 'annule', label: 'Annulé' }
  ];

  function getApiBase() {
    if (window.ATAKSocket && window.ATAKSocket.getApiBase) return window.ATAKSocket.getApiBase();
    if (window.overwatchApiBase) return window.overwatchApiBase;
    return (window.ATAK_API_BASE || '').replace(/\/$/, '');
  }

  function getMapId() {
    if (window.ATAKSocket && window.ATAKSocket.getMapId) return window.ATAKSocket.getMapId();
    if (window.OverwatchState && window.OverwatchState.currentMapId != null) return window.OverwatchState.currentMapId;
    return 1;
  }

  function storageKey() {
    return LS_PREFIX + String(getMapId());
  }

  function pruneDismissMap(map, nowMs) {
    var out = {};
    var src = map && typeof map === 'object' ? map : {};
    Object.keys(src).forEach(function (k) {
      var ts = Number(src[k]);
      if (!ts || isNaN(ts)) return;
      // Ne conserve un masquage que pendant la fenêtre active (évite un dismiss « éternel »).
      if ((nowMs - ts) < ACTIVE_WINDOW_MS) out[k] = ts;
    });
    return out;
  }

  function readDismissed() {
    try {
      var raw = localStorage.getItem(storageKey());
      if (!raw) return { alerts: {}, units: {} };
      var parsed = JSON.parse(raw);
      var now = Date.now();
      var cleaned = {
        alerts: pruneDismissMap(parsed && parsed.alerts, now),
        units: pruneDismissMap(parsed && parsed.units, now)
      };
      // Réécrit si des entrées ont expiré (évite une croissance infinie).
      try {
        var beforeA = parsed && parsed.alerts ? Object.keys(parsed.alerts).length : 0;
        var beforeU = parsed && parsed.units ? Object.keys(parsed.units).length : 0;
        if (beforeA !== Object.keys(cleaned.alerts).length || beforeU !== Object.keys(cleaned.units).length) {
          writeDismissed(cleaned);
        }
      } catch (e2) { /* ignore */ }
      return cleaned;
    } catch (e) {
      return { alerts: {}, units: {} };
    }
  }

  function writeDismissed(state) {
    try {
      localStorage.setItem(storageKey(), JSON.stringify({
        alerts: state.alerts || {},
        units: state.units || {}
      }));
    } catch (e) { /* quota / mode privé */ }
  }

  function alertKey(a) {
    if (a && a.client_key) return String(a.client_key);
    if (a && a.id != null && a.id !== '' && !isNaN(Number(a.id))) return 'a:' + String(a.id);
    var body = a && (a.body || a.summary || a.label) ? String(a.body || a.summary || a.label) : '';
    var t = a && a.created_at ? String(a.created_at) : '';
    return 'a:' + body.substring(0, 120) + '|' + t;
  }

  function unitKey(u) {
    var cs = String((u && u.call_sign) || '').toUpperCase();
    var health = String((u && (u.health || u.health_label)) || '');
    var id = u && u.id != null ? String(u.id) : '';
    return 'u:' + (id || cs) + ':' + health;
  }

  function isAlertDismissed(a, state) {
    var key = alertKey(a);
    var dismissedAt = state.alerts && state.alerts[key];
    if (!dismissedAt) return false;
    // Nouvelle alerte (created_at plus récent que le masquage) → réafficher.
    var created = parseCreatedAtMs(a && a.created_at);
    if (!isNaN(created) && Number(dismissedAt) < created) return false;
    return true;
  }

  function isUnitDismissed(u, state) {
    return !!(state.units && state.units[unitKey(u)]);
  }

  function dismissAlert(a) {
    if (!a) return;
    var state = readDismissed();
    state.alerts[alertKey(a)] = Date.now();
    writeDismissed(state);
  }

  function dismissUnit(u) {
    if (!u) return;
    var state = readDismissed();
    state.units[unitKey(u)] = Date.now();
    writeDismissed(state);
  }

  function dismissAllVisible(data) {
    var state = readDismissed();
    ((data && data.alerts) || []).forEach(function (a) {
      state.alerts[alertKey(a)] = Date.now();
    });
    ((data && data.criticalUnits) || []).forEach(function (u) {
      state.units[unitKey(u)] = Date.now();
    });
    writeDismissed(state);
  }

  function filterData(data) {
    var state = readDismissed();
    var now = Date.now();
    var windowMs = (data && data.active_window_seconds)
      ? (Number(data.active_window_seconds) * 1000)
      : ACTIVE_WINDOW_MS;
    var units = ((data && data.criticalUnits) || []).filter(function (u) {
      if (isUnitDismissed(u, state)) return false;
      var ts = (u && (u.updated_at || u.created_at)) || '';
      return isWithinActiveWindow(ts, now, windowMs);
    });
    units = collapseUnitsByCallsign(units);
    // Indicatifs déjà en « Unités à secourir » → pas de doublon dans « Alertes reçues ».
    var unitCs = {};
    units.forEach(function (u) {
      var cs = String((u && u.call_sign) || '').trim().toUpperCase();
      if (cs) unitCs[cs] = true;
    });
    var alerts = ((data && data.alerts) || []).filter(function (a) {
      if (isAlertDismissed(a, state)) return false;
      // Alertes clôturées (Traité / KIA / Annulé) : hors bannière et liste active.
      if (a && a.triage && a.triage.is_resolved) return false;
      var status = triageStatusOf(a);
      if (status === 'traite' || status === 'kia' || status === 'annule') return false;
      var acs = String((a && a.call_sign) || '').trim().toUpperCase();
      if (acs && unitCs[acs]) return false;
      // Alertes issues de l’API (id numérique) : déjà bornées par l’horloge MySQL — ne pas
      // re-filtrer au fuseau navigateur (Date.parse UTC vs datetime naïf).
      if (a && a.id != null && a.id !== '' && !isNaN(Number(a.id))) return true;
      return isWithinActiveWindow(a && a.created_at, now, windowMs);
    });
    alerts = collapseAlertsByCallsign(alerts);
    var emergency = 0;
    alerts.forEach(function (a) {
      if ((a.severity || '') === 'critical' && !(a.triage && a.triage.is_resolved)) emergency++;
    });
    units.forEach(function (u) {
      if ((u.severity || '') === 'critical') emergency++;
    });
    return {
      mapId: data && data.mapId,
      alerts: alerts,
      criticalUnits: units,
      counts: {
        alerts: alerts.length,
        criticalUnits: units.length,
        emergency: emergency
      },
      can_triage: !!(data && data.can_triage),
      triage_statuses: (data && data.triage_statuses) || TRIAGE_OPTIONS,
      _raw: data
    };
  }

  /** Rang sévérité : arrêt cardiaque > inconscient > blessé… */
  function kindSeverityRank(kind) {
    var k = String(kind || '').toLowerCase();
    if (k === 'cardiac_arrest' || k === 'cardiac-arrest' || k === 'death' || k === 'dead' || k === 'kia') return 100;
    if (k === 'unconscious') return 80;
    if (k === 'critical' || k === 'incapacitated' || k === 'down') return 70;
    if (k === 'wounded' || k === 'injured' || k === 'medical_alert') return 40;
    if (k === 'wia_report') return 30;
    return 10;
  }

  /**
   * Catégorie d’affichage (onglets Assistances).
   * - urgences : arrêt cardiaque / hors combat
   * - suivi : au sol / inconscient (détections auto souvent bruyantes)
   * - autres : bilans WIA, blessés, reste
   */
  function alertCategory(a) {
    var k = String((a && a.kind) || '').toLowerCase();
    if (k === 'cardiac_arrest' || k === 'cardiac-arrest' || k === 'death' || k === 'dead' || k === 'kia') {
      return 'urgences';
    }
    if (k === 'unconscious' || k === 'incapacitated' || k === 'down') {
      return 'suivi';
    }
    var label = String((a && (a.label || a.summary || a.body)) || '').toLowerCase();
    if (
      label.indexOf('arrêt cardiaque') >= 0
      || label.indexOf('arret cardiaque') >= 0
      || label.indexOf('rythme à zéro') >= 0
      || label.indexOf('rythme a zero') >= 0
      || /\bkia\b/.test(label)
      || label.indexOf('hors combat') >= 0
    ) {
      return 'urgences';
    }
    if (label.indexOf('inconscient') >= 0 || label.indexOf('au sol') >= 0 || label.indexOf('immobile') >= 0) {
      return 'suivi';
    }
    return 'autres';
  }

  function unitCategory(u) {
    var h = String((u && (u.health || u.health_label)) || '').toLowerCase();
    if (
      h === 'cardiac_arrest' || h === 'cardiac-arrest' || h === 'dead' || h === 'kia'
      || h.indexOf('arrêt cardiaque') >= 0 || h.indexOf('arret cardiaque') >= 0
      || h.indexOf('hors combat') >= 0
    ) {
      return 'urgences';
    }
    if (
      h === 'unconscious' || h === 'incapacitated' || h === 'down' || h === 'critical'
      || h.indexOf('inconscient') >= 0 || h.indexOf('au sol') >= 0
    ) {
      return 'suivi';
    }
    return 'autres';
  }

  function readStoredFilter() {
    try {
      var v = localStorage.getItem(LS_FILTER);
      if (v === 'urgences' || v === 'suivi' || v === 'autres') return v;
    } catch (e) { /* ignore */ }
    return 'urgences';
  }

  function writeStoredFilter(f) {
    try { localStorage.setItem(LS_FILTER, f); } catch (e) { /* ignore */ }
  }

  function applyCategoryFilter(data, filter) {
    var f = filter || activeFilter || 'urgences';
    var alerts = ((data && data.alerts) || []).filter(function (a) {
      return alertCategory(a) === f;
    });
    var units = ((data && data.criticalUnits) || []).filter(function (u) {
      return unitCategory(u) === f;
    });
    var emergency = 0;
    alerts.forEach(function (a) {
      if ((a.severity || '') === 'critical' && !(a.triage && a.triage.is_resolved)) emergency++;
    });
    units.forEach(function (u) {
      if ((u.severity || '') === 'critical') emergency++;
    });
    return {
      mapId: data && data.mapId,
      alerts: alerts,
      criticalUnits: units,
      counts: {
        alerts: alerts.length,
        criticalUnits: units.length,
        emergency: emergency
      },
      can_triage: !!(data && data.can_triage),
      triage_statuses: (data && data.triage_statuses) || TRIAGE_OPTIONS,
      _raw: data && data._raw,
      _filter: f,
      _categoryCounts: countByCategory(data)
    };
  }

  function countByCategory(data) {
    var out = { urgences: 0, suivi: 0, autres: 0 };
    ((data && data.alerts) || []).forEach(function (a) {
      var c = alertCategory(a);
      if (out[c] != null) out[c]++;
    });
    ((data && data.criticalUnits) || []).forEach(function (u) {
      var c = unitCategory(u);
      if (out[c] != null) out[c]++;
    });
    return out;
  }

  function updateSubtabUi(counts) {
    var root = document.getElementById('atak-medical-subtabs');
    if (!root) return;
    root.querySelectorAll('[data-medical-filter]').forEach(function (btn) {
      var f = btn.getAttribute('data-medical-filter') || '';
      var on = f === activeFilter;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    var c = counts || {};
    ['urgences', 'suivi', 'autres'].forEach(function (key) {
      var el = root.querySelector('[data-medical-count="' + key + '"]');
      if (!el) return;
      var n = Number(c[key]) || 0;
      el.textContent = n > 0 ? String(n) : '';
      el.hidden = n <= 0;
    });
  }

  function emptyStateForFilter(filter) {
    if (filter === 'suivi') {
      return '<div class="atak-empty-state atak-medical-empty">' +
        '<div class="atak-empty-state-icon" aria-hidden="true">✚</div>' +
        '<p class="atak-empty-state-title">Aucune détection au sol</p>' +
        '<p class="atak-empty-state-text">Les détections automatiques « au sol / inconscient » (parfois de faux positifs) s’affichent ici pour vérification.</p></div>';
    }
    if (filter === 'autres') {
      return '<div class="atak-empty-state atak-medical-empty">' +
        '<div class="atak-empty-state-icon" aria-hidden="true">✚</div>' +
        '<p class="atak-empty-state-title">Aucun bilan</p>' +
        '<p class="atak-empty-state-text">Les bilans de santé et les autres signalements médicaux apparaîtront ici.</p></div>';
    }
    return '<div class="atak-empty-state atak-medical-empty">' +
      '<div class="atak-empty-state-icon" aria-hidden="true">✚</div>' +
      '<p class="atak-empty-state-title">Aucune urgence</p>' +
      '<p class="atak-empty-state-text">Les arrêts cardiaques et urgences confirmées s’afficheront ici.</p></div>';
  }

  /** Une carte active par indicatif — conserve la sévérité max. */
  function collapseAlertsByCallsign(alerts) {
    var best = {};
    var noCs = [];
    var order = [];
    (alerts || []).forEach(function (a) {
      if (!a) return;
      var cs = String((a.call_sign || a.author || '')).trim().toUpperCase();
      if (!cs) {
        noCs.push(a);
        return;
      }
      var rank = kindSeverityRank(a.kind);
      var created = String(a.created_at || '');
      if (!best[cs]) {
        order.push(cs);
        best[cs] = { alert: a, rank: rank, created: created };
        return;
      }
      var cur = best[cs];
      if (rank > cur.rank || (rank === cur.rank && created > cur.created)) {
        best[cs] = { alert: a, rank: rank, created: created };
      }
    });
    return order.map(function (cs) { return best[cs].alert; }).concat(noCs);
  }

  function collapseUnitsByCallsign(units) {
    var best = {};
    var order = [];
    var orphans = [];
    (units || []).forEach(function (u) {
      if (!u) return;
      var cs = String(u.call_sign || '').trim().toUpperCase();
      if (!cs) {
        orphans.push(u);
        return;
      }
      var rank = kindSeverityRank(u.health);
      var updated = String(u.updated_at || u.created_at || '');
      if (!best[cs]) {
        order.push(cs);
        best[cs] = { unit: u, rank: rank, updated: updated };
        return;
      }
      var cur = best[cs];
      if (rank > cur.rank || (rank === cur.rank && updated > cur.updated)) {
        best[cs] = { unit: u, rank: rank, updated: updated };
      }
    });
    return order.map(function (cs) { return best[cs].unit; }).concat(orphans);
  }

  /** Parse un datetime MySQL naïf (sans TZ) en heure locale navigateur. */
  function parseCreatedAtMs(createdAt) {
    if (!createdAt) return NaN;
    var s = String(createdAt).trim();
    var m = s.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
    if (m) {
      return new Date(
        parseInt(m[1], 10),
        parseInt(m[2], 10) - 1,
        parseInt(m[3], 10),
        parseInt(m[4], 10),
        parseInt(m[5], 10),
        parseInt(m[6], 10)
      ).getTime();
    }
    var t = Date.parse(s.replace(' ', 'T'));
    return t;
  }

  function isWithinActiveWindow(createdAt, nowMs, windowMs) {
    if (!createdAt) return true;
    var t = parseCreatedAtMs(createdAt);
    if (isNaN(t)) return true;
    var age = nowMs - t;
    if (age < 0) return true; // futur = décalage fuseau
    var win = windowMs || ACTIVE_WINDOW_MS;
    // Marge fuseau (UTC ↔ Paris) alignée côté serveur (~3 h).
    return age < (win + 3 * 60 * 60 * 1000);
  }

  function foldMedicalPrefix(upper) {
    var s = String(upper || '');
    try {
      if (s.normalize) s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    } catch (e) { /* ignore */ }
    return s
      .replace(/[ÉÈÊË]/g, 'E')
      .replace(/[ÁÀÂÄ]/g, 'A')
      .replace(/[ÍÌÎÏ]/g, 'I')
      .replace(/[ÓÒÔÖ]/g, 'O')
      .replace(/[ÚÙÛÜ]/g, 'U')
      .replace(/Ç/g, 'C');
  }

  function canTriage() {
    if (window.ATAKSessionProfile && typeof window.ATAKSessionProfile.canTriageMedicalUi === 'function') {
      // Profil de session chargé (spécialité Médecin) : priorité sur le cache API.
      if (window.ATAK_SESSION_PROFILE || (typeof window.ATAKSessionProfile.get === 'function' && window.ATAKSessionProfile.get())) {
        return !!window.ATAKSessionProfile.canTriageMedicalUi();
      }
    }
    if (canTriageCached != null) return canTriageCached;
    if (window.ATAK_CAPS && typeof window.ATAK_CAPS.canTriageMedical === 'boolean') {
      canTriageCached = !!window.ATAK_CAPS.canTriageMedical;
      return canTriageCached;
    }
    return false;
  }

  function triageStatusOf(a) {
    if (a && a.triage && a.triage.status) return String(a.triage.status);
    return 'a_secourir';
  }

  function triageLabelOf(a) {
    if (a && a.triage && a.triage.status_label) return String(a.triage.status_label);
    var s = triageStatusOf(a);
    for (var i = 0; i < TRIAGE_OPTIONS.length; i++) {
      if (TRIAGE_OPTIONS[i].value === s) return TRIAGE_OPTIONS[i].label;
    }
    return 'À secourir';
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function isDeathLabel(ll, heartRate) {
    ll = String(ll || '').toLowerCase();
    if (ll.indexOf('arrêt cardiaque') >= 0 || ll.indexOf('arret cardiaque') >= 0) return true;
    if (ll.indexOf('rythme à zéro') >= 0 || ll.indexOf('rythme a zero') >= 0) return true;
    if (ll.indexOf('kia') >= 0 || ll.indexOf('hors combat') >= 0) return true;
    if (/\bmort\b/.test(ll) || ll.indexOf('dead') >= 0) return true;
    if (/\bfc\s*[=:]?\s*0\b/.test(ll)) return true;
    if (heartRate != null && heartRate <= 0) return true;
    return false;
  }

  function isUnconsciousLabel(ll) {
    ll = String(ll || '').toLowerCase().replace(/[\u2013\u2014\-]+/g, ' ');
    return ll.indexOf('inconscient') >= 0 || ll.indexOf('au sol') >= 0;
  }

  function stripCommsPrefix(body) {
    var raw = String(body || '').trim();
    var m = raw.match(/^\[(\d{1,2}:\d{2}:\d{2})\]\[([A-Za-z0-9_]+)\]\[([A-Za-z0-9_]+)\]\[([A-Za-z0-9_]+)\]\s*([\s\S]+)$/);
    return m ? String(m[5] || '').trim() : raw;
  }

  function buildMedicalResult(opts) {
    return {
      is_medical: true,
      kind: opts.kind,
      severity: opts.severity,
      call_sign: opts.call_sign || '',
      label: opts.label || 'Assistance médicale',
      heart_rate: opts.heart_rate != null ? opts.heart_rate : null,
      blood_pct: opts.blood_pct != null ? opts.blood_pct : null,
      grid: opts.grid || '',
      summary: opts.summary || '',
      body: opts.body || ''
    };
  }

  function classifyKind(label, heartRate, haystack) {
    var ll = String(label || '') + ' ' + String(haystack || '');
    if (isDeathLabel(ll, heartRate)) {
      return { kind: 'cardiac_arrest', severity: 'critical' };
    }
    if (isUnconsciousLabel(ll)) {
      return { kind: 'unconscious', severity: 'critical' };
    }
    return { kind: 'medical_alert', severity: 'urgent' };
  }

  /** Format Liaison / toast : « Assistance médicale — NewPI — Au sol — inconscient — FC 95 — Grille … » */
  function parseHumanAssistance(body) {
    body = String(body || '').trim();
    if (!body) return null;
    var folded = foldMedicalPrefix(body.toUpperCase());
    if (folded.indexOf('ASSISTANCE MEDICALE') !== 0) return null;

    var parts = body.split(/\s*[—–\-]+\s*/).map(function (p) { return p.trim(); }).filter(Boolean);
    if (parts.length < 2) return null;

    var callSign = parts[1] || '';
    var fcIdx = -1;
    var gridIdx = -1;
    for (var i = 2; i < parts.length; i++) {
      if (fcIdx < 0 && /^FC\s*[=:]?\s*\d+/i.test(parts[i])) fcIdx = i;
      if (gridIdx < 0 && /^Grille\b/i.test(parts[i])) gridIdx = i;
    }
    var labelEnd = fcIdx >= 0 ? fcIdx : (gridIdx >= 0 ? gridIdx : parts.length);
    var labelParts = parts.slice(2, labelEnd);
    var label = labelParts.length ? labelParts.join(' — ') : 'Assistance médicale';
    var heartRate = null;
    if (fcIdx >= 0) {
      var hm = parts[fcIdx].match(/(\d+)/);
      if (hm) heartRate = parseInt(hm[1], 10);
    }
    var grid = '';
    if (gridIdx >= 0) {
      grid = parts[gridIdx].replace(/^Grille\s+/i, '').trim();
    }
    var csLow = callSign.toLowerCase();
    if (callSign && (csLow.indexOf('inconscient') >= 0 || csLow.indexOf('au sol') >= 0
      || csLow.indexOf('arrêt') >= 0 || csLow.indexOf('arret') >= 0 || csLow.indexOf('cardiaque') >= 0)) {
      label = label === 'Assistance médicale' ? callSign : (callSign + ' — ' + label);
      callSign = '';
    }
    var cls = classifyKind(label, heartRate, body);
    return buildMedicalResult({
      kind: cls.kind,
      severity: cls.severity,
      call_sign: callSign,
      label: label,
      heart_rate: heartRate,
      grid: grid,
      summary: [callSign, label, heartRate != null ? ('FC ' + heartRate) : '', grid ? ('Grille ' + grid) : ''].filter(Boolean).join(' — '),
      body: body
    });
  }

  function parseMessage(body) {
    body = stripCommsPrefix(body);
    if (!body) return null;
    var upper = body.toUpperCase();
    var folded = foldMedicalPrefix(upper);
    if (folded.indexOf('ALERTE MEDICALE') === 0 || upper.indexOf('ALERTE MÉDICALE') === 0 || upper.indexOf('ALERTE MEDICALE') === 0) {
      var parts = body.split('|').map(function (p) { return p.trim(); });
      var callSign = parts[1] || '';
      var label = parts[2] || 'Assistance médicale';
      var hrMatch = (parts[3] || '').match(/(\d+)/);
      var bloodMatch = (parts[4] || '').match(/(\d+)/);
      var grid = (parts[5] || '').replace(/^Grille\s+/i, '');
      var heartRate = hrMatch ? parseInt(hrMatch[1], 10) : null;
      var cls = classifyKind(label, heartRate, body);
      return buildMedicalResult({
        kind: cls.kind,
        severity: cls.severity,
        call_sign: callSign,
        label: label,
        heart_rate: heartRate,
        blood_pct: bloodMatch ? parseInt(bloodMatch[1], 10) : null,
        grid: grid,
        summary: [callSign, label, hrMatch ? ('FC ' + hrMatch[1]) : '', grid ? ('Grille ' + grid) : ''].filter(Boolean).join(' — '),
        body: body
      });
    }
    if (folded.indexOf('WIA|') === 0 || upper.indexOf('WIA|') === 0) {
      var wp = body.split('|').map(function (p) { return p.trim(); });
      var status = wp[1] || 'Blessé';
      var bm = (wp[2] || '').match(/(\d+)/);
      var hm = (wp[3] || '').match(/(\d+)/);
      return buildMedicalResult({
        kind: 'wia_report',
        severity: 'attention',
        call_sign: '',
        label: 'Bilan santé — ' + status,
        heart_rate: hm ? parseInt(hm[1], 10) : null,
        blood_pct: bm ? parseInt(bm[1], 10) : null,
        grid: '',
        summary: 'Bilan santé — ' + status,
        body: body
      });
    }
    return parseHumanAssistance(body);
  }

  function kindLabelFr(kind) {
    var k = String(kind || '').toLowerCase();
    if (k === 'cardiac_arrest' || k === 'death' || k === 'dead' || k === 'kia') return 'Arrêt cardiaque';
    if (k === 'unconscious') return 'Inconscient';
    if (k === 'wia_report') return 'Bilan santé';
    return 'Assistance médicale';
  }

  function formatChatBody(body) {
    var parsed = parseMessage(body);
    if (!parsed) return escapeHtml(body);
    var text = 'Assistance médicale — ' + (parsed.summary || body);
    return '<span class="atak-medical-chat-flag" title="Assistance médicale">' + escapeHtml(text) + '</span>';
  }

  function resolveSoundKind(kind, label, heartRate) {
    var k = String(kind || '').toLowerCase();
    if (k === 'cardiac_arrest' || k === 'death' || k === 'kia' || k === 'dead') return 'death';
    if (isDeathLabel(label, heartRate)) return 'death';
    if (k === 'unconscious' || isUnconsciousLabel(label)) return 'unconscious';
    return '';
  }

  function playMedicalSound(kind, label, heartRate) {
    if (!window.ATAKSounds) return;
    var soundKind = resolveSoundKind(kind, label, heartRate);
    if (typeof window.ATAKSounds.playEvent === 'function' && soundKind) {
      window.ATAKSounds.playEvent(soundKind);
      return;
    }
    if (typeof window.ATAKSounds.play === 'function') {
      window.ATAKSounds.play();
    }
  }

  function audioMuteHint() {
    if (!window.ATAKSounds) return '';
    var silent = typeof window.ATAKSounds.isSilentMode === 'function' && window.ATAKSounds.isSilentMode();
    var vol = typeof window.ATAKSounds.getVolume === 'function' ? window.ATAKSounds.getVolume() : 70;
    if (silent && vol <= 0) return ' (son coupé — mode silence et volume à 0 %)';
    if (silent) return ' (son coupé — mode silence)';
    if (vol <= 0) return ' (son coupé — volume à 0 %)';
    return '';
  }

  function showToast(summary, kind, label, heartRate) {
    var toast = document.getElementById('atak-notification-toast')
      || document.getElementById('atak-medical-toast')
      || document.getElementById('overwatch-medical-toast');
    // L’alerte visuelle doit toujours s’afficher, même si le son est coupé.
    if (toast) {
      var hint = audioMuteHint();
      toast.textContent = 'Assistance médicale — ' + (summary || 'Nouvelle alerte') + hint;
      toast.classList.add('visible', 'atak-medical-toast-visible', 'show', 'atak-notification-toast--medical');
      if (hint) toast.classList.add('atak-notification-toast--muted');
      else toast.classList.remove('atak-notification-toast--muted');
      toast.hidden = false;
      toast.removeAttribute('hidden');
      clearTimeout(showToast._t);
      showToast._t = setTimeout(function () {
        toast.classList.remove('visible', 'atak-medical-toast-visible', 'show', 'atak-notification-toast--medical', 'atak-notification-toast--muted');
      }, 8000);
    }
    playMedicalSound(kind, label || summary, heartRate);
    if (typeof window.ATAKSounds !== 'undefined' && typeof window.ATAKSounds.refreshMuteHint === 'function') {
      window.ATAKSounds.refreshMuteHint();
    }
  }

  function updateClearAllVisibility(data) {
    var btn = document.getElementById('atak-medical-clear-all');
    if (!btn) return;
    var n = ((data && data.alerts) || []).length + ((data && data.criticalUnits) || []).length;
    btn.hidden = n <= 0;
  }

  function renderBanner(data) {
    var banner = document.getElementById('atak-medical-banner')
      || document.getElementById('overwatch-medical-banner')
      || document.getElementById('tacmap-medical-banner');
    if (!banner) return;
    var alerts = (data && data.alerts) || [];
    var units = (data && data.criticalUnits) || [];
    var emergency = (data && data.counts && data.counts.emergency) || 0;
    if (!alerts.length && !units.length) {
      banner.hidden = true;
      banner.innerHTML = '';
      return;
    }
    var latest = alerts[alerts.length - 1] || null;
    var latestCs = String((latest && latest.call_sign) || '').trim().toUpperCase();
    var unitBits = units.slice(0, 3).map(function (u) {
      return escapeHtml((u.call_sign || '?') + ' · ' + (u.health_label || u.health || ''));
    }).filter(function (bit, idx) {
      // Évite le doublon « NewPl — Arrêt… » + « N-10 · Arrêt · NewPl · Arrêt ».
      var cs = String((units[idx] && units[idx].call_sign) || '').trim().toUpperCase();
      if (latestCs && cs === latestCs) return false;
      return true;
    }).join(' · ');
    var msg = latest ? String(latest.summary || latest.label || '').trim() : '';
    banner.hidden = false;
    banner.innerHTML =
      '<div class="atak-medical-banner-inner">' +
      '<strong>Médical</strong>' +
      (emergency ? ' <span class="atak-medical-badge">' + emergency + ' critique(s)</span>' : '') +
      (msg ? '<span class="atak-medical-banner-msg">' + escapeHtml(msg) + '</span>' : '') +
      (unitBits ? '<span class="atak-medical-banner-units">' + unitBits + '</span>' : '') +
      '<button type="button" class="atak-medical-dismiss atak-medical-dismiss--banner" data-medical-action="clear-all" title="Masquer ces alertes" aria-label="Masquer ces alertes">✕</button>' +
      '</div>';
  }

  function buildTriageSelectHtml(alertId, status) {
    return '<select class="atak-medical-triage-select" data-medical-action="triage-select" data-alert-id="' +
      escapeHtml(String(alertId)) + '" aria-label="Statut de secours" title="Statut de secours">' +
      TRIAGE_OPTIONS.map(function (o) {
        var sel = status === o.value ? ' selected' : '';
        return '<option value="' + escapeHtml(o.value) + '"' + sel + '>' + escapeHtml(o.label) + '</option>';
      }).join('') +
      '</select>';
  }

  function renderList(data) {
    var list = document.getElementById('atak-medical-list')
      || document.getElementById('overwatch-medical-list');
    // TACMAP : liste gérée par ComspecOperationalMap (éviter d’écraser le panneau unités).
    if (!list) return;
    var alerts = (data && data.alerts) || [];
    var units = (data && data.criticalUnits) || [];
    var filter = (data && data._filter) || activeFilter;
    updateClearAllVisibility(data);
    updateSubtabUi((data && data._categoryCounts) || countByCategory(data));
    if (!alerts.length && !units.length) {
      list.innerHTML = emptyStateForFilter(filter);
      return;
    }
    var html = '';
    if (units.length) {
      html += '<div class="atak-medical-section-title">Unités à secourir</div>';
      html += units.map(function (u) {
        var sev = u.severity === 'critical' ? 'critical' : 'attention';
        var ukRaw = unitKey(u);
        var uk = escapeHtml(ukRaw);
        var healthLabel = escapeHtml(u.health_label || kindLabelFr(u.health));
        var gridBit = u.grid_ref ? escapeHtml(u.grid_ref) : '';
        var previewParts = [healthLabel];
        if (gridBit) previewParts.push(gridBit);
        return '<details class="atak-medical-item atak-medical-' + sev + '" data-callsign="' + escapeHtml(u.call_sign || '') + '"'
          + ' data-atak-collapse="med-unit-' + uk + '" data-atak-collapse-default="0">' +
          '<summary class="atak-medical-item-sum">' +
          '<span class="atak-medical-item-sum-main">' +
          '<span class="atak-medical-item-title">' + escapeHtml(u.call_sign || '—') + '</span>' +
          '<span class="atak-medical-item-sum-preview">' + previewParts.join(' · ') + '</span>' +
          '</span>' +
          '<span class="atak-medical-item-side atak-medical-item-side--sum">' +
          '<button type="button" class="atak-medical-dismiss" data-medical-action="dismiss-unit" data-dismiss-key="' + uk + '" title="Masquer cette alerte" aria-label="Masquer cette alerte">✕</button>' +
          '</span>' +
          '</summary>' +
          '<div class="atak-medical-item-body">' +
          '<div class="atak-medical-item-meta">' + healthLabel + (gridBit ? ' · Grille ' + gridBit : '') + '</div>' +
          '</div>' +
          '</details>';
      }).join('');
    }
    if (alerts.length) {
      html += '<div class="atak-medical-section-title">' +
        (filter === 'suivi' ? 'Détections automatiques' : (filter === 'autres' ? 'Bilans et signalements' : 'Alertes reçues')) +
        '</div>';
      var allowTriage = canTriage() || !!(data && data.can_triage);
      html += alerts.slice().reverse().slice(0, 25).map(function (a) {
        var sev = a.severity === 'critical' ? 'critical' : (a.severity === 'attention' ? 'attention' : 'urgent');
        var t = a.created_at ? String(a.created_at).replace('T', ' ').substring(0, 19) : '';
        var akRaw = alertKey(a);
        var ak = escapeHtml(akRaw);
        var status = triageStatusOf(a);
        var statusLabel = triageLabelOf(a);
        var alertId = a.id != null && a.id !== '' ? a.id : (a.chat_id != null ? a.chat_id : null);
        var cs = a.call_sign ? String(a.call_sign) : '';
        var title = escapeHtml(cs || kindLabelFr(a.kind));
        var kindBit = escapeHtml(kindLabelFr(a.kind));
        var detailLabel = escapeHtml(a.label || a.summary || '');
        var gridBit = a.grid ? escapeHtml(a.grid) : '';
        var previewParts = [];
        if (cs) previewParts.push(kindBit);
        if (gridBit) previewParts.push(gridBit);
        var triageCtrl = '';
        if (allowTriage && alertId != null && alertId !== '') {
          triageCtrl = buildTriageSelectHtml(alertId, status);
        } else {
          triageCtrl = '<span class="atak-medical-triage-badge is-' + escapeHtml(status) + '">' + escapeHtml(statusLabel) + '</span>';
        }
        // Évite de répéter le titre (indicatif) dans le détail si le libellé est redondant.
        var showDetail = detailLabel && detailLabel !== title && detailLabel.indexOf(title) !== 0;
        return '<details class="atak-medical-item atak-medical-' + sev + '"'
          + ' data-atak-collapse="med-alert-' + ak + '" data-atak-collapse-default="0">' +
          '<summary class="atak-medical-item-sum">' +
          '<span class="atak-medical-item-sum-main">' +
          '<span class="atak-medical-item-title">' + title + '</span>' +
          (previewParts.length
            ? '<span class="atak-medical-item-sum-preview">' + previewParts.join(' · ') + '</span>'
            : '') +
          '</span>' +
          '<span class="atak-medical-item-side atak-medical-item-side--sum">' +
          triageCtrl +
          '<button type="button" class="atak-medical-dismiss" data-medical-action="dismiss-alert" data-dismiss-key="' + ak + '" title="Masquer cette alerte" aria-label="Masquer cette alerte">✕</button>' +
          '</span>' +
          '</summary>' +
          '<div class="atak-medical-item-body">' +
          (showDetail ? '<div class="atak-medical-item-label">' + detailLabel + '</div>' : '') +
          '<div class="atak-medical-item-meta">' +
          (cs ? kindBit + (t || gridBit ? ' · ' : '') : '') +
          escapeHtml(t) +
          (gridBit ? (t || cs ? ' · ' : '') + 'Grille ' + gridBit : '') +
          '</div>' +
          '</div>' +
          '</details>';
      }).join('');
    }
    list.innerHTML = html;
    if (window.ATAKCollapse && typeof window.ATAKCollapse.bind === 'function') {
      window.ATAKCollapse.bind(list);
    }
    list.querySelectorAll('[data-callsign]').forEach(function (el) {
      el.addEventListener('click', function (ev) {
        if (ev.target && ev.target.closest && ev.target.closest('[data-medical-action]')) return;
        // Ne pas recentrer si l’utilisateur ne fait que déplier / replier la carte.
        if (ev.target && ev.target.closest && ev.target.closest('summary')) return;
        var cs = el.getAttribute('data-callsign');
        if (!cs) return;
        if (typeof window.focusUnitByCallsign === 'function') {
          window.focusUnitByCallsign(cs);
          return;
        }
        document.querySelectorAll('.atak-unit-card').forEach(function (c) {
          var call = c.querySelector('.atak-unit-callsign');
          if (call && call.textContent && call.textContent.toUpperCase().indexOf(cs.toUpperCase()) >= 0) {
            c.click();
          }
        });
      });
    });
  }

  function apply(data) {
    lastData = data || { alerts: [], criticalUnits: [], counts: {} };
    var baseVisible = filterData(lastData);
    var visible = applyCategoryFilter(baseVisible, activeFilter);
    var fp = JSON.stringify({
      a: (visible.alerts || []).map(function (x) { return x.id || x.summary; }),
      u: (visible.criticalUnits || []).map(function (x) { return (x.call_sign || '') + ':' + (x.health || ''); }),
      e: (visible.counts && visible.counts.emergency) || 0,
      f: activeFilter,
      c: visible._categoryCounts || {}
    });
    if (fp === lastFingerprint) return;
    var prev = lastFingerprint;
    lastFingerprint = fp;
    // Bannière : urgences + au sol (pas les seuls bilans du filtre actif).
    var bannerData = {
      alerts: (baseVisible.alerts || []).filter(function (a) {
        var c = alertCategory(a);
        return c === 'urgences' || c === 'suivi';
      }),
      criticalUnits: (baseVisible.criticalUnits || []).filter(function (u) {
        var c = unitCategory(u);
        return c === 'urgences' || c === 'suivi';
      }),
      counts: { emergency: 0 }
    };
    bannerData.counts.emergency = bannerData.alerts.filter(function (a) {
      return (a.severity || '') === 'critical';
    }).length + bannerData.criticalUnits.filter(function (u) {
      return (u.severity || '') === 'critical';
    }).length;
    renderBanner(bannerData);
    renderList(visible);
    // Toast : urgences critiques uniquement (évite le bruit « au sol »).
    var toastPool = applyCategoryFilter(baseVisible, 'urgences');
    var alerts = toastPool.alerts || [];
    // Toast dès qu’une nouvelle alerte critique apparaît (y compris 1er peuplement après vide).
    // Le son peut être coupé (silence / volume 0) : l’UI s’affiche quand même.
    if (alerts.length) {
      var latest = alerts[alerts.length - 1];
      var latestKey = alertKey(latest);
      // fp change pour plein de raisons qui n'ont rien à voir avec « nouvelle alerte » (statut
      // d'une unité, triage, fenêtre active qui glisse...) : ne rejouer le toast/son que si
      // l'alerte critique la plus récente n'est pas celle déjà signalée la dernière fois.
      var isNew = !!prev && latestKey !== lastToastedAlertKey;
      // Un rechargement de page remet lastToastedAlertKey à zéro : sans garde d'âge, la plus
      // récente alerte encore visible (même vieille d'une session précédente) rejouerait le
      // toast/son au premier chargement. On ne sonne que pour du vraiment récent.
      var latestAgeMs = Date.now() - parseCreatedAtMs(latest && latest.created_at);
      var isRecent = !isNaN(latestAgeMs) && latestAgeMs >= 0 && latestAgeMs < TOAST_MAX_AGE_MS;
      if (isNew && isRecent && latest && latest.severity === 'critical') {
        showToast(
          latest.summary || latest.label,
          latest.kind,
          latest.label || latest.summary,
          latest.heart_rate != null ? latest.heart_rate : null
        );
      }
      if (latest && latest.severity === 'critical') lastToastedAlertKey = latestKey;
    }
    var badge = document.getElementById('atak-medical-tab-badge')
      || document.getElementById('overwatch-medical-tab-badge');
    if (badge) {
      var cat = countByCategory(baseVisible);
      var n = (Number(cat.urgences) || 0) + (Number(cat.suivi) || 0);
      badge.textContent = n > 0 ? String(n) : '';
      badge.hidden = n <= 0;
    }
  }

  function refreshFromLast() {
    if (lastData) apply(lastData);
    else fetchAlerts();
  }

  function onDismissClick(ev) {
    var btn = ev.target && ev.target.closest ? ev.target.closest('[data-medical-action]') : null;
    if (!btn) return;
    var action = btn.getAttribute('data-medical-action');
    // Le select de triage gère son propre change ; empêcher le repli/dépli de la carte.
    if (action === 'triage-select') {
      ev.stopPropagation();
      return;
    }
    ev.preventDefault();
    ev.stopPropagation();
    var visible = applyCategoryFilter(filterData(lastData || {}), activeFilter);
    if (action === 'triage') {
      var alertId = btn.getAttribute('data-alert-id') || '';
      var status = btn.getAttribute('data-triage-status') || '';
      if (!alertId || !status) return;
      if (!(canTriage() || (lastData && lastData.can_triage))) {
        if (window.ATAKShowNotification) {
          window.ATAKShowNotification('Cochez la spécialité Médecin dans votre profil de session pour indiquer un statut.');
        }
        return;
      }
      submitTriage(alertId, status);
      return;
    }
    if (action === 'clear-all') {
      dismissAllVisible(visible);
      if (window.ATAKShowNotification) {
        window.ATAKShowNotification('Alertes masquées. Le journal Liaison et le tchat restent inchangés.');
      }
      refreshFromLast();
      return;
    }
    var key = btn.getAttribute('data-dismiss-key') || '';
    if (action === 'dismiss-alert') {
      var alert = (visible.alerts || []).find(function (a) { return alertKey(a) === key; });
      if (alert) dismissAlert(alert);
      else if (key) {
        var stA = readDismissed();
        stA.alerts[key] = Date.now();
        writeDismissed(stA);
      }
      refreshFromLast();
      return;
    }
    if (action === 'dismiss-unit') {
      var unit = (visible.criticalUnits || []).find(function (u) { return unitKey(u) === key; });
      if (unit) dismissUnit(unit);
      else if (key) {
        var stU = readDismissed();
        stU.units[key] = Date.now();
        writeDismissed(stU);
      }
      refreshFromLast();
    }
  }

  function onTriageSelectChange(ev) {
    var sel = ev.target && ev.target.closest ? ev.target.closest('[data-medical-action="triage-select"]') : null;
    if (!sel || sel.tagName !== 'SELECT') return;
    var alertId = sel.getAttribute('data-alert-id') || '';
    var status = sel.value || '';
    if (!alertId || !status) return;
    if (!(canTriage() || (lastData && lastData.can_triage))) {
      if (window.ATAKShowNotification) {
        window.ATAKShowNotification('Cochez la spécialité Médecin dans votre profil de session pour indiquer un statut.');
      }
      return;
    }
    submitTriage(alertId, status);
  }

  function submitTriage(alertId, status) {
    var base = String(getApiBase() || '').replace(/\/$/, '');
    if (!base) return;
    var path = /\/api$/i.test(base)
      ? '/atak/medical-alerts/' + encodeURIComponent(alertId) + '/triage'
      : '/api/atak/medical-alerts/' + encodeURIComponent(alertId) + '/triage';
    var by = '';
    if (window.ATAK_USER) {
      by = String(window.ATAK_USER.callsign || window.ATAK_USER.displayName || '').trim();
    }
    fetch(base + path, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ status: status, by: by, mapId: getMapId() })
    })
      .then(function (r) {
        return r.json().then(function (body) {
          return { ok: r.ok, status: r.status, body: body || {} };
        });
      })
      .then(function (res) {
        if (!res.ok) {
          var msg = (res.body && res.body.message)
            ? res.body.message
            : 'Impossible de mettre à jour le triage pour le moment.';
          if (window.ATAKShowNotification) window.ATAKShowNotification(msg);
          return;
        }
        if (lastData && Array.isArray(lastData.alerts) && res.body.alert) {
          lastData.alerts = lastData.alerts.map(function (a) {
            if (String(a.id) === String(alertId)) {
              return Object.assign({}, a, res.body.alert, { triage: res.body.triage || (res.body.alert && res.body.alert.triage) });
            }
            return a;
          });
        }
        if (window.ATAKShowNotification) {
          var label = (res.body.triage && res.body.triage.status_label) || status;
          window.ATAKShowNotification('Triage mis à jour — ' + label);
        }
        refreshFromLast();
        fetchAlerts();
      })
      .catch(function () {
        if (window.ATAKShowNotification) {
          window.ATAKShowNotification('Impossible de mettre à jour le triage pour le moment.');
        }
      });
  }

  function bindUi() {
    if (boundUi) return;
    boundUi = true;
    activeFilter = readStoredFilter();
    document.addEventListener('click', onDismissClick);
    document.addEventListener('change', onTriageSelectChange);
    document.addEventListener('click', function (ev) {
      var tab = ev.target && ev.target.closest
        ? ev.target.closest('#atak-medical-subtabs [data-medical-filter]')
        : null;
      if (!tab) return;
      ev.preventDefault();
      var f = tab.getAttribute('data-medical-filter') || 'urgences';
      if (f !== 'urgences' && f !== 'suivi' && f !== 'autres') f = 'urgences';
      if (f === activeFilter) return;
      activeFilter = f;
      writeStoredFilter(f);
      lastFingerprint = '';
      refreshFromLast();
    });
    // Empêche le <details> de basculer quand on clique le select / ✕ dans le résumé.
    document.addEventListener('mousedown', function (ev) {
      var el = ev.target && ev.target.closest
        ? ev.target.closest('[data-medical-action="triage-select"], .atak-medical-dismiss, .atak-medical-triage-select')
        : null;
      if (el) ev.stopPropagation();
    }, true);
    var clearBtn = document.getElementById('atak-medical-clear-all');
    if (clearBtn && !clearBtn._atakBound) {
      clearBtn._atakBound = true;
      clearBtn.addEventListener('click', function () {
        if (!lastData) return;
        var visible = applyCategoryFilter(filterData(lastData), activeFilter);
        var n = (visible.alerts || []).length + (visible.criticalUnits || []).length;
        if (n <= 0) return;
        if (!window.confirm('Masquer toutes les alertes affichées ? Elles resteront consultables dans le journal Liaison et le tchat.')) {
          return;
        }
        dismissAllVisible(visible);
        if (window.ATAKShowNotification) {
          window.ATAKShowNotification('Toutes les alertes affichées ont été masquées.');
        }
        refreshFromLast();
      });
    }
  }

  function alertFromChatMessage(m) {
    if (!m) return null;
    var body = m.body || m.message || '';
    var parsed = m.medical || parseMessage(body);
    if (!parsed) return null;
    return {
      is_medical: true,
      id: m.id != null ? m.id : (parsed.id != null ? parsed.id : null),
      author: m.author || '',
      body: body,
      created_at: m.created_at || '',
      kind: parsed.kind,
      severity: parsed.severity,
      call_sign: parsed.call_sign || '',
      label: parsed.label || '',
      heart_rate: parsed.heart_rate != null ? parsed.heart_rate : null,
      blood_pct: parsed.blood_pct != null ? parsed.blood_pct : null,
      grid: parsed.grid || '',
      summary: parsed.summary || '',
      source: 'chat',
      client_key: (m.id != null && !isNaN(Number(m.id))) ? ('a:' + String(m.id)) : undefined,
      triage: (m.medical && m.medical.triage) || parsed.triage || {
        status: 'a_secourir',
        status_label: 'À secourir',
        status_by: '',
        status_note: '',
        updated_at: '',
        is_resolved: false
      }
    };
  }

  /**
   * Signature de contenu stable entre sources (API / tchat / activité) pour un même événement
   * réel : call_sign+kind+grid ne dépend pas de l’id (différent selon la table d’origine).
   */
  function contentSignature(a) {
    if (a && a.call_sign && a.kind && a.grid) {
      return String(a.call_sign).trim().toUpperCase() + '|' + a.kind + '|' + String(a.grid).trim();
    }
    return '';
  }

  /** Un id numérique d’une vraie ligne d’alerte API (pas un id de tchat/activité de secours). */
  function hasApiAlertId(a) {
    return !!(a && a.id != null && a.id !== '' && !a.source);
  }

  /** Préfère un triage clôturé / non défaut lors de la fusion multi-sources. */
  function preferTriage(existing, incoming) {
    var a = existing || null;
    var b = incoming || null;
    if (!a) return b;
    if (!b) return a;
    if (b.is_resolved && !a.is_resolved) return b;
    if (a.is_resolved && !b.is_resolved) return a;
    var as = String(a.status || 'a_secourir');
    var bs = String(b.status || 'a_secourir');
    if (bs !== 'a_secourir' && as === 'a_secourir') return b;
    if (as !== 'a_secourir' && bs === 'a_secourir') return a;
    return b.status ? b : a;
  }

  function mergeAlerts(primary, secondary) {
    var byKey = {};
    var sigToKey = {};
    var order = [];
    function put(a) {
      if (!a) return;
      // Clé stable : préférer l’id chat numérique ; sinon client_key / body.
      if (!a.client_key) {
        if (a.id != null && a.id !== '' && !isNaN(Number(a.id))) {
          a.client_key = 'a:' + String(a.id);
        } else if (a.source === 'activity' && a.activity_id != null) {
          a.client_key = 'act:' + String(a.activity_id);
        }
      }
      // Même événement vu par 2 sources (API + tchat, ou tchat + activité) : même signature de
      // contenu → une seule carte au lieu de deux (l’id/tchat/activité diffère selon la table).
      var sig = contentSignature(a);
      var k = (sig && sigToKey[sig]) || alertKey(a);
      if (sig && !sigToKey[sig]) sigToKey[sig] = k;
      if (!byKey[k]) {
        order.push(k);
        byKey[k] = a;
      } else {
        var existing = byKey[k];
        var merged = Object.assign({}, existing, a);
        // Garder l’id de la vraie ligne API (triage) même si une source tchat/activité arrive après.
        if (hasApiAlertId(existing) && !hasApiAlertId(a)) merged.id = existing.id;
        merged.triage = preferTriage(existing.triage, a.triage);
        merged.client_key = existing.client_key || a.client_key || k;
        byKey[k] = merged;
      }
    }
    (primary || []).forEach(put);
    (secondary || []).forEach(put);
    return order.map(function (k) { return byKey[k]; });
  }

  /** Fusionne dans le store local (ne jamais écraser par une API vide). */
  function rememberAlerts(list) {
    if (!list || !list.length) return false;
    var before = ((lastData && lastData.alerts) || []).length;
    var base = lastData || {
      alerts: [],
      criticalUnits: [],
      counts: {},
      can_triage: canTriage(),
      active_window_seconds: ACTIVE_WINDOW_MS / 1000
    };
    var merged = mergeAlerts(base.alerts || [], list);
    lastData = Object.assign({}, base, { alerts: merged });
    return merged.length !== before || merged.some(function (a, i) {
      var prev = (base.alerts || [])[i];
      return !prev || alertKey(prev) !== alertKey(a);
    });
  }

  function collectFromCaches() {
    var out = [];
    if (window.ATAKChat && typeof window.ATAKChat.getCachedMessages === 'function') {
      (window.ATAKChat.getCachedMessages() || []).forEach(function (m) {
        var a = alertFromChatMessage(m);
        if (a) out.push(a);
      });
    }
    if (window.ATAKActivity && typeof window.ATAKActivity.getCachedEvents === 'function') {
      (window.ATAKActivity.getCachedEvents() || []).forEach(function (ev) {
        if (!ev) return;
        var label = String(ev.label || ev.message || '');
        var parsed = parseMessage(label);
        if (!parsed) return;
        out.push({
          is_medical: true,
          id: null,
          activity_id: ev.id != null ? ev.id : null,
          source: 'activity',
          client_key: ev.id != null ? ('act:' + String(ev.id)) : undefined,
          author: ev.actor || '',
          body: label,
          created_at: ev.at || ev.created_at || '',
          kind: parsed.kind,
          severity: parsed.severity,
          call_sign: parsed.call_sign || '',
          label: parsed.label || '',
          heart_rate: parsed.heart_rate != null ? parsed.heart_rate : null,
          blood_pct: parsed.blood_pct != null ? parsed.blood_pct : null,
          grid: parsed.grid || '',
          summary: parsed.summary || '',
          triage: {
            status: 'a_secourir',
            status_label: 'À secourir',
            status_by: '',
            status_note: '',
            updated_at: '',
            is_resolved: false
          }
        });
      });
    }
    return out;
  }

  /**
   * Source secondaire : parse les messages tchat déjà chargés pour peupler Assistances
   * même si l’API medical-alerts a filtré à tort (fuseau / dismiss expiré).
   */
  function ingestFromChatMessages(messages) {
    bindUi();
    var list = Array.isArray(messages) ? messages : [];
    var fromChat = [];
    list.forEach(function (m) {
      var a = alertFromChatMessage(m);
      if (a) fromChat.push(a);
    });
    if (!fromChat.length) {
      // Même sans nouveau message médical, resync depuis Liaison cache.
      var fromCaches = collectFromCaches();
      if (!fromCaches.length) return;
      rememberAlerts(fromCaches);
      apply(lastData);
      return;
    }
    rememberAlerts(fromChat);
    rememberAlerts(collectFromCaches());
    apply(lastData);
  }

  function notifyFromChatMessage(msg) {
    var body = msg && (msg.body || msg.message);
    var parsed = (msg && msg.medical) || parseMessage(body);
    if (!parsed) return;
    // Rejeu historique (resync socket / reload) : peupler la liste sans toast/son.
    var ageMs = Date.now() - parseCreatedAtMs(msg && msg.created_at);
    var isRecent = isNaN(ageMs) || (ageMs >= 0 && ageMs < TOAST_MAX_AGE_MS);
    if (isRecent) {
      showToast(
        parsed.summary || parsed.label,
        parsed.kind,
        parsed.label || parsed.summary,
        parsed.heart_rate != null ? parsed.heart_rate : null
      );
    }
    ingestFromChatMessages([msg]);
    fetchAlerts();
  }

  /**
   * Source tertiaire : événements Liaison (« Assistance médicale — … »)
   * pour peupler Assistances même si le tchat a été vidé localement.
   */
  function ingestFromActivityEvents(events) {
    bindUi();
    var list = Array.isArray(events) ? events : [];
    var fromActivity = [];
    list.forEach(function (ev) {
      if (!ev) return;
      var label = String(ev.label || ev.message || '');
      var parsed = parseMessage(label);
      if (!parsed) return;
      fromActivity.push({
        is_medical: true,
        id: null,
        activity_id: ev.id != null ? ev.id : null,
        source: 'activity',
        client_key: ev.id != null ? ('act:' + String(ev.id)) : undefined,
        author: ev.actor || '',
        body: label,
        created_at: ev.at || ev.created_at || '',
        kind: parsed.kind,
        severity: parsed.severity,
        call_sign: parsed.call_sign || '',
        label: parsed.label || '',
        heart_rate: parsed.heart_rate != null ? parsed.heart_rate : null,
        blood_pct: parsed.blood_pct != null ? parsed.blood_pct : null,
        grid: parsed.grid || '',
        summary: parsed.summary || '',
        triage: {
          status: 'a_secourir',
          status_label: 'À secourir',
          status_by: '',
          status_note: '',
          updated_at: '',
          is_resolved: false
        }
      });
    });
    if (!fromActivity.length) return;
    rememberAlerts(fromActivity);
    apply(lastData);
  }

  function fetchAlerts() {
    bindUi();
    // Toujours resync depuis tchat / Liaison déjà en mémoire (même si l’API répond vide).
    rememberAlerts(collectFromCaches());
    var base = String(getApiBase() || '').replace(/\/$/, '');
    if (!base) {
      if (lastData) apply(lastData);
      return Promise.resolve(null);
    }
    var path = /\/api$/i.test(base)
      ? '/atak/medical-alerts'
      : '/api/atak/medical-alerts';
    var url = base + path + '?mapId=' + encodeURIComponent(getMapId()) + '&limit=40';
    return fetch(url, { credentials: 'include' })
      .then(function (r) {
        if (!r.ok) throw new Error('medical-alerts ' + r.status);
        return r.json();
      })
      .then(function (data) {
        if (data && typeof data.can_triage === 'boolean') {
          canTriageCached = !!data.can_triage;
        }
        var incoming = data || { alerts: [], criticalUnits: [] };
        var apiAlerts = Array.isArray(incoming.alerts) ? incoming.alerts : [];
        var prevAlerts = (lastData && Array.isArray(lastData.alerts)) ? lastData.alerts : [];
        var cacheAlerts = collectFromCaches();
        // Jamais remplacer le store local par une réponse API vide.
        var merged = mergeAlerts(mergeAlerts(apiAlerts, prevAlerts), cacheAlerts);
        lastData = Object.assign({}, incoming, {
          alerts: merged,
          criticalUnits: Array.isArray(incoming.criticalUnits)
            ? incoming.criticalUnits
            : ((lastData && lastData.criticalUnits) || []),
          can_triage: incoming.can_triage != null ? incoming.can_triage : canTriage()
        });
        apply(lastData);
        return data;
      })
      .catch(function () {
        if (lastData) apply(lastData);
        return null;
      });
  }

  function startPolling(intervalMs) {
    stopPolling();
    if (!window.__atakMedicalProfileBound) {
      window.__atakMedicalProfileBound = true;
      document.addEventListener('atak:session-profile', function () {
        if (lastData) apply(lastData);
      });
    }
    fetchAlerts();
    pollTimer = setInterval(fetchAlerts, intervalMs || 5000);
  }

  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  return {
    parseMessage: parseMessage,
    formatChatBody: formatChatBody,
    fetchAlerts: fetchAlerts,
    startPolling: startPolling,
    stopPolling: stopPolling,
    notifyFromChatMessage: notifyFromChatMessage,
    ingestFromChatMessages: ingestFromChatMessages,
    ingestFromActivityEvents: ingestFromActivityEvents,
    apply: apply,
    kindLabelFr: kindLabelFr,
    kindSeverityRank: kindSeverityRank,
    collapseAlertsByCallsign: collapseAlertsByCallsign,
    dismissAlert: dismissAlert,
    dismissUnit: dismissUnit,
    dismissAllVisible: dismissAllVisible,
    bindUi: bindUi,
    submitTriage: submitTriage,
    ACTIVE_WINDOW_MS: ACTIVE_WINDOW_MS
  };
})();
