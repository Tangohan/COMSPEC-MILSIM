/*
 * COMSPEC Athena — Overwatch Beta : alertes tactiques et balises, cartes d'alerte dans le tchat,
 * applications synchronisées, fil de renseignement, anneaux de géolocalisation, curseur du joueur.
 * Dépend de window.OverwatchBeta (atak-overwatch-beta.js, chargé avant).
 */
(function () {
  'use strict';

  var B = null;
  var POLL_ALERTS_MS = 10000;
  var POLL_RINGS_MS = 15000;

  var KIND = {
    panic: { label: 'PANIQUE', long: 'Panique — opérateur en détresse', color: '#ff3b3b', pulse: true },
    eagle_down: { label: 'OPÉRATEUR À TERRE', long: 'Opérateur à terre / appareil abattu', color: '#f472b6', pulse: true },
    tic: { label: 'CONTACT', long: 'Contact (troupes au contact)', color: '#f2ab33', pulse: true },
    tic_clear: { label: 'FIN DE CONTACT', long: 'Fin de contact', color: '#5cc76b', pulse: false },
    salute: { label: 'SALUTE', long: 'Compte rendu SALUTE', color: '#4d9ffa', pulse: false },
    frago: { label: 'FRAGO', long: 'Ordre fragmentaire', color: '#a78bfa', pulse: false },
    bda: { label: 'BDA', long: 'Bilan des dégâts', color: '#fb923c', pulse: false },
    beacon: { label: 'BALISE GPS', long: 'Balise GPS', color: '#38bdf8', pulse: false }
  };
  var RAW_KIND = {
    PANIC: 'panic', PANIQUE: 'panic', EAGLE_DOWN: 'eagle_down', EAGLEDOWN: 'eagle_down', TIC: 'tic', CONTACT: 'tic',
    TIC_CLEAR: 'tic_clear', CLEAR: 'tic_clear', SALUTE: 'salute', FRAGO: 'frago', BDA: 'bda'
  };

  // ------------------------------------------------------------------ Outils

  function esc(v) {
    if (B && B.escapeHtml) return B.escapeHtml(v == null ? '' : String(v));
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function store(key, val) {
    try {
      if (val === undefined) { var raw = localStorage.getItem(key); return raw ? JSON.parse(raw) : null; }
      if (val === null) localStorage.removeItem(key); else localStorage.setItem(key, JSON.stringify(val));
    } catch (e) {}
    return null;
  }
  function num(v) { var n = Number(v); return Number.isFinite(n) ? n : null; }
  function hasPos(x, y) { x = num(x); y = num(y); return x !== null && y !== null && (Math.abs(x) > 0.5 || Math.abs(y) > 0.5); }
  function ago(sec) {
    if (sec == null || !Number.isFinite(Number(sec))) return '—';
    sec = Math.max(0, Math.round(Number(sec)));
    if (sec < 60) return 'il y a ' + sec + ' s';
    if (sec < 3600) return 'il y a ' + Math.round(sec / 60) + ' min';
    if (sec < 86400) return 'il y a ' + Math.floor(sec / 3600) + ' h ' + String(Math.round((sec % 3600) / 60)).padStart(2, '0');
    return 'il y a ' + Math.round(sec / 86400) + ' j';
  }
  function parseSqlDate(s) {
    var t = Date.parse(String(s || '').replace(' ', 'T'));
    return Number.isFinite(t) ? t : null;
  }
  function clock(s) {
    var m = String(s || '').match(/(\d{2}):(\d{2})/);
    return m ? m[1] + ':' + m[2] : '';
  }
  function ageFromSql(s) {
    var t = parseSqlDate(s);
    return t ? Math.max(0, (Date.now() - t) / 1000) : null;
  }
  function gridOf(x, y) {
    if (!hasPos(x, y)) return '';
    return String(Math.round(Number(x))).padStart(5, '0').slice(0, 5) + ' ' + String(Math.round(Number(y))).padStart(5, '0').slice(0, 5);
  }
  function mapId() { return B ? B.mapId : 1; }
  function api(path, opts) { return B.api(path, opts); }
  function toast(t) { if (B && B.toast) B.toast(t); }

  // Recentre la carte (et revient à la vue carte depuis Transmissions) avec un repère temporaire.
  var gotoFlash = null;
  function goto(x, y, label) {
    if (!hasPos(x, y)) { toast('Position inconnue pour cet élément.'); return; }
    var ws = document.querySelector('.ow-workspace');
    if (ws && ws.classList.contains('is-comms') && B.openView) B.openView('overwatch');
    var ll = B.worldToLatLng(Number(x), Number(y));
    B.map.setView(ll, Math.max(B.map.getZoom(), 4));
    if (gotoFlash) { try { B.map.removeLayer(gotoFlash); } catch (e) {} }
    gotoFlash = L.marker(ll, {
      interactive: false, zIndexOffset: 1200,
      icon: L.divIcon({ className: 'owi-goto', html: '<i></i>' + (label ? '<span>' + esc(label) + '</span>' : ''), iconSize: [44, 44], iconAnchor: [22, 22] })
    }).addTo(B.map);
    var mine = gotoFlash;
    window.setTimeout(function () { if (gotoFlash === mine) { try { B.map.removeLayer(mine); } catch (e) {} gotoFlash = null; } }, 6000);
  }

  // Grille saisie : « 151173 » (6 chiffres = 100 m), « 15191730 » (8 = 10 m), ou « 15190 17301 » en mètres.
  function parseGridInput(raw) {
    var s = String(raw || '').trim();
    if (!s) return null;
    var two = s.match(/^(-?\d+(?:[.,]\d+)?)[\s;,/]+(-?\d+(?:[.,]\d+)?)$/);
    if (two) return { x: Number(two[1].replace(',', '.')), y: Number(two[2].replace(',', '.')) };
    var d = s.replace(/\D/g, '');
    if (d.length >= 6 && d.length % 2 === 0 && d.length <= 10) {
      var h = d.length / 2;
      var mult = Math.pow(10, 5 - h);
      return { x: Number(d.slice(0, h)) * mult + mult / 2, y: Number(d.slice(h)) * mult + mult / 2 };
    }
    return null;
  }

  // ------------------------------------------------------------------ Alertes et balises

  var alerts = [];
  var beacons = [];
  var alertLayers = {};
  var beaconLayers = {};
  var seenAlertKeys = null;
  var alertsOpen = false;
  var dismissedBeaconKey = 'athena:owi-beacons-hidden';

  function kindMeta(k) { return KIND[k] || { label: String(k || 'ALERTE').toUpperCase(), long: 'Alerte', color: '#e7b14d', pulse: false }; }

  function alertById(key) {
    for (var i = 0; i < alerts.length; i++) if (alerts[i].key === key) return alerts[i];
    for (var j = 0; j < beacons.length; j++) if (beacons[j].key === key) return beacons[j];
    return null;
  }

  function loadAlerts() {
    if (!B) return Promise.resolve();
    return api('/api/atak/overwatch/alerts?mapId=' + encodeURIComponent(mapId())).then(function (payload) {
      alerts = Array.isArray(payload && payload.alerts) ? payload.alerts : [];
      beacons = Array.isArray(payload && payload.beacons) ? payload.beacons : [];
      announceNew();
      drawAlerts();
      drawBeacons();
      paintAlertsButton();
      if (alertsOpen) paintAlertsPanel();
    }).catch(function () {});
  }

  function announceNew() {
    var keys = {};
    alerts.forEach(function (a) { keys[a.key] = true; });
    if (seenAlertKeys === null) { seenAlertKeys = keys; return; }
    alerts.forEach(function (a) {
      if (seenAlertKeys[a.key] || a.acked) return;
      var m = kindMeta(a.kind);
      if (a.kind === 'panic' || a.kind === 'eagle_down' || a.kind === 'tic') {
        toast(m.label + ' — ' + (a.call_sign || a.author || '?') + (a.grid ? ' · ' + a.grid : ''));
        try { if (window.ATAKSounds && window.ATAKSounds.playPriority) window.ATAKSounds.playPriority({ force: true }); } catch (e) {}
        var btn = document.getElementById('owi-alerts-btn');
        if (btn) { btn.classList.remove('is-ring'); void btn.offsetWidth; btn.classList.add('is-ring'); }
      }
    });
    seenAlertKeys = keys;
  }

  function alertPopupHtml(a) {
    var m = kindMeta(a.kind);
    var age = ageFromSql(a.created_at);
    return '<div class="owi-pop"><b style="color:' + m.color + '">' + esc(m.label) + '</b> · ' + esc(a.call_sign || a.author || '') +
      '<div class="owi-pop-meta">' + esc(a.grid ? 'Grille ' + a.grid : gridOf(a.pos_x, a.pos_y)) + ' · ' + esc(clock(a.created_at)) + ' (' + esc(ago(age)) + ')</div>' +
      (a.summary ? '<p>' + esc(a.summary) + '</p>' : '') +
      (a.acked ? '<div class="owi-pop-meta">Acquittée par ' + esc(a.acked_by || 'le poste') + '</div>'
        : '<button type="button" class="ow-tag" data-owi-ack="' + esc(a.key) + '">Acquitter</button>') + '</div>';
  }

  function drawAlerts() {
    var seen = {};
    alerts.forEach(function (a) {
      if (!hasPos(a.pos_x, a.pos_y)) return;
      seen[a.key] = true;
      var m = kindMeta(a.kind);
      var live = m.pulse && !a.acked;
      var ll = B.worldToLatLng(Number(a.pos_x), Number(a.pos_y));
      var icon = L.divIcon({
        className: 'owi-alert-marker owi-k-' + esc(a.kind) + (live ? ' is-live' : '') + (a.acked ? ' is-acked' : ''),
        html: '<div style="--owi-c:' + m.color + '"><i class="r1"></i><i class="r2"></i><b></b><span>' + esc(m.label) + ' · ' + esc(a.call_sign || '') + '</span></div>',
        iconSize: [28, 28], iconAnchor: [14, 14]
      });
      var mk = alertLayers[a.key];
      if (!mk) {
        mk = L.marker(ll, { icon: icon, zIndexOffset: live ? 1000 : 600 }).addTo(B.map);
        mk.bindPopup(alertPopupHtml(a));
        alertLayers[a.key] = mk;
      } else {
        mk.setLatLng(ll).setIcon(icon);
        mk.setPopupContent(alertPopupHtml(a));
      }
    });
    Object.keys(alertLayers).forEach(function (k) {
      if (!seen[k]) { try { B.map.removeLayer(alertLayers[k]); } catch (e) {} delete alertLayers[k]; }
    });
  }

  function hiddenBeacons() { return store(dismissedBeaconKey) || {}; }

  function drawBeacons() {
    var seen = {};
    var hidden = hiddenBeacons();
    beacons.forEach(function (b) {
      if (!hasPos(b.pos_x, b.pos_y) || hidden[b.key]) return;
      seen[b.key] = true;
      var ll = B.worldToLatLng(Number(b.pos_x), Number(b.pos_y));
      var stale = Number(b.age_sec || 0) > 120;
      var icon = L.divIcon({
        className: 'owi-beacon-marker' + (stale ? ' is-stale' : ''),
        html: '<div><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">' +
          '<path d="M7 6.5a7 7 0 0 0 0 11M17 6.5a7 7 0 0 1 0 11M9.6 9a3.6 3.6 0 0 0 0 6M14.4 9a3.6 3.6 0 0 1 0 6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>' +
          '<circle cx="12" cy="12" r="2.3" fill="currentColor"/></svg><span>' + esc(b.label || b.call_sign || 'Balise') + '</span></div>',
        iconSize: [22, 22], iconAnchor: [11, 11]
      });
      var pop = '<div class="owi-pop"><b style="color:#38bdf8">BALISE GPS</b> · ' + esc(b.label || b.call_sign) +
        '<div class="owi-pop-meta">' + esc(gridOf(b.pos_x, b.pos_y)) + ' · dernier signal ' + esc(ago(b.age_sec)) + '</div>' +
        (b.speed != null ? '<div class="owi-pop-meta">Vitesse ' + esc(Math.round(Number(b.speed))) + ' km/h' + (b.heading != null ? ' · cap ' + esc(Math.round(Number(b.heading))) + '°' : '') + '</div>' : '') +
        '<button type="button" class="ow-tag" data-owi-beacon-hide="' + esc(b.key) + '">Masquer sur ce poste</button></div>';
      var mk = beaconLayers[b.key];
      if (!mk) {
        mk = L.marker(ll, { icon: icon, zIndexOffset: 700 }).addTo(B.map);
        mk.bindPopup(pop);
        beaconLayers[b.key] = mk;
      } else {
        mk.setLatLng(ll).setIcon(icon);
        mk.setPopupContent(pop);
      }
    });
    Object.keys(beaconLayers).forEach(function (k) {
      if (!seen[k]) { try { B.map.removeLayer(beaconLayers[k]); } catch (e) {} delete beaconLayers[k]; }
    });
  }

  function ackAlert(key, undo) {
    var a = alertById(key);
    api('/api/atak/overwatch/alerts/ack', { method: 'POST', body: { mapId: mapId(), key: key, undo: !!undo } }).then(function (res) {
      if (a) { a.acked = !undo; a.acked_by = res && res.acked_by ? res.acked_by : ''; }
      drawAlerts();
      paintAlertsButton();
      if (alertsOpen) paintAlertsPanel();
      refreshChatCards();
      toast(undo ? 'Alerte rouverte.' : 'Alerte acquittée.');
    }).catch(function () { toast('Acquittement impossible (connexion ou session).'); });
  }

  function mountAlertsUi() {
    var stage = document.getElementById('ow-map-stage');
    if (!stage || document.getElementById('owi-alerts-btn')) return;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.id = 'owi-alerts-btn';
    btn.className = 'owi-alerts-btn';
    btn.title = 'Alertes tactiques et balises GPS';
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M12 3 2.5 20h19L12 3zm0 6v5m0 3v.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Alertes</span><em hidden>0</em>';
    stage.appendChild(btn);
    var panel = document.createElement('div');
    panel.id = 'owi-alerts-panel';
    panel.className = 'owi-alerts-panel';
    panel.hidden = true;
    stage.appendChild(panel);
    btn.addEventListener('click', function () {
      alertsOpen = panel.hidden;
      panel.hidden = !alertsOpen;
      if (alertsOpen) paintAlertsPanel();
    });
  }

  function paintAlertsButton() {
    var btn = document.getElementById('owi-alerts-btn');
    if (!btn) return;
    var open = alerts.filter(function (a) { return !a.acked && a.kind !== 'tic_clear'; });
    var critical = open.some(function (a) { return a.kind === 'panic' || a.kind === 'eagle_down'; });
    var em = btn.querySelector('em');
    em.hidden = !open.length;
    em.textContent = String(open.length);
    btn.classList.toggle('is-critical', critical);
  }

  function alertRowHtml(a) {
    var m = kindMeta(a.kind);
    var age = ageFromSql(a.created_at);
    return '<div class="owi-alert-row' + (a.acked ? ' is-acked' : '') + '" style="--owi-c:' + m.color + '">' +
      '<div class="owi-alert-head"><span class="owi-badge">' + esc(m.label) + '</span><b>' + esc(a.call_sign || a.author || '—') + '</b>' +
      '<time title="' + esc(a.created_at) + '">' + esc(clock(a.created_at)) + ' · ' + esc(ago(age)) + '</time></div>' +
      (a.summary ? '<p>' + esc(a.summary) + '</p>' : '') +
      '<div class="owi-alert-foot"><span>' + esc(a.grid ? 'Grille ' + a.grid : (gridOf(a.pos_x, a.pos_y) || 'sans position')) + '</span>' +
      (hasPos(a.pos_x, a.pos_y) ? '<button type="button" class="ow-tag" data-owi-goto="' + esc(a.pos_x) + ',' + esc(a.pos_y) + '" data-owi-goto-label="' + esc(m.label) + '">Voir sur la carte</button>' : '') +
      (a.acked
        ? '<button type="button" class="ow-tag" data-owi-unack="' + esc(a.key) + '" title="Acquittée par ' + esc(a.acked_by || '') + '">Rouvrir</button>'
        : '<button type="button" class="ow-tag owi-ack" data-owi-ack="' + esc(a.key) + '">Acquitter</button>') +
      '</div></div>';
  }

  function paintAlertsPanel() {
    var panel = document.getElementById('owi-alerts-panel');
    if (!panel) return;
    var open = alerts.filter(function (a) { return !a.acked; });
    var done = alerts.filter(function (a) { return a.acked; });
    var hidden = hiddenBeacons();
    var html = '<div class="owi-panel-head"><strong>Alertes tactiques</strong><span>' + open.length + ' à traiter</span>' +
      '<button type="button" class="owi-x" data-owi-close-alerts aria-label="Fermer">×</button></div>';
    html += open.length ? open.map(alertRowHtml).join('') : '<p class="ow-help">Aucune alerte en attente. Les alertes du téléphone (panique, contact, appareil abattu, SALUTE) apparaissent ici.</p>';
    if (done.length) html += '<p class="owi-sub">Acquittées</p>' + done.slice(0, 15).map(alertRowHtml).join('');
    html += '<p class="owi-sub">Balises GPS (' + beacons.length + ')</p>';
    html += beacons.length ? beacons.map(function (b) {
      return '<div class="owi-alert-row" style="--owi-c:#38bdf8"><div class="owi-alert-head"><span class="owi-badge">BALISE</span><b>' + esc(b.label || b.call_sign) + '</b>' +
        '<time>' + esc(ago(b.age_sec)) + '</time></div><div class="owi-alert-foot"><span>' + esc(gridOf(b.pos_x, b.pos_y)) + '</span>' +
        '<button type="button" class="ow-tag" data-owi-goto="' + esc(b.pos_x) + ',' + esc(b.pos_y) + '" data-owi-goto-label="Balise">Voir sur la carte</button>' +
        '<button type="button" class="ow-tag" data-owi-beacon-hide="' + esc(b.key) + '">' + (hidden[b.key] ? 'Afficher' : 'Masquer') + '</button></div></div>';
    }).join('') : '<p class="ow-help">Aucune balise active. Zeus : « Balise GPS » sur un véhicule pour le suivre ici.</p>';
    panel.innerHTML = html;
  }

  // ------------------------------------------------------------------ Tchat : carte d'alerte

  // Ligne « ALERTE TACTIQUE|TYPE|indicatif|grille|x|y|libellé » → carte lisible (sinon '').
  function parseAlertLine(raw) {
    var s = String(raw || '');
    var at = s.toUpperCase().indexOf('ALERTE TACTIQUE|');
    if (at < 0) return null;
    var parts = s.slice(at).split('|').map(function (p) { return p.trim(); });
    var kindRaw = String(parts[1] || 'TIC').toUpperCase().replace(/[\s-]+/g, '_');
    var kind = RAW_KIND[kindRaw] || kindRaw.toLowerCase();
    var tail = parts.slice(6).filter(function (p) { return p && !/^(ATHENA_)?ORDER_ID=/i.test(p); });
    var label = tail.join(' — ');
    if (kind === 'eagle_down' && /PANI(Q|C)|D[ÉE]TRESSE/i.test(label)) kind = 'panic';
    return {
      kind: kind, call_sign: parts[2] || '', grid: parts[3] || '',
      pos_x: num(String(parts[4] || '').replace(',', '.')), pos_y: num(String(parts[5] || '').replace(',', '.')),
      label: label
    };
  }

  function chatAlertCard(raw, row) {
    var p = parseAlertLine(raw);
    if (!p) return '';
    var m = kindMeta(p.kind);
    var key = row && row.id ? 'chat:' + row.id : '';
    var known = key ? alertById(key) : null;
    var acked = known && known.acked;
    var when = String((row && (row.created_at || row.time)) || '');
    return '<div class="owi-chat-alert' + (m.pulse && !acked ? ' is-live' : '') + (acked ? ' is-acked' : '') + '" style="--owi-c:' + m.color + '"' + (key ? ' data-owi-chat-key="' + esc(key) + '"' : '') + '>' +
      '<div class="owi-alert-head"><span class="owi-badge">' + esc(m.label) + '</span><b>' + esc(p.call_sign || '—') + '</b>' +
      '<time>' + esc(clock(when)) + '</time></div>' +
      '<div class="owi-chat-grid"><span>Grille</span><b>' + esc(p.grid || gridOf(p.pos_x, p.pos_y) || '—') + '</b>' +
      (hasPos(p.pos_x, p.pos_y) ? '<span>Position</span><b>' + esc(Math.round(p.pos_x)) + ' / ' + esc(Math.round(p.pos_y)) + '</b>' : '') + '</div>' +
      (p.label ? '<p>' + esc(p.label) + '</p>' : '') +
      '<div class="owi-alert-foot">' +
      (hasPos(p.pos_x, p.pos_y) ? '<button type="button" class="ow-tag" data-owi-goto="' + esc(p.pos_x) + ',' + esc(p.pos_y) + '" data-owi-goto-label="' + esc(m.label) + '">Voir sur la carte</button>' : '') +
      (key ? (acked ? '<span class="owi-acked">Acquittée' + (known.acked_by ? ' · ' + esc(known.acked_by) : '') + '</span>'
        : '<button type="button" class="ow-tag owi-ack" data-owi-ack="' + esc(key) + '">Acquitter</button>') : '') +
      '</div></div>';
  }

  // Rafraîchit l'état « acquittée » des cartes déjà affichées dans le tchat.
  function refreshChatCards() {
    document.querySelectorAll('[data-owi-chat-key]').forEach(function (el) {
      var a = alertById(el.getAttribute('data-owi-chat-key'));
      if (!a) return;
      el.classList.toggle('is-acked', !!a.acked);
      if (a.acked) {
        el.classList.remove('is-live');
        var b = el.querySelector('[data-owi-ack]');
        if (b) b.outerHTML = '<span class="owi-acked">Acquittée' + (a.acked_by ? ' · ' + esc(a.acked_by) : '') + '</span>';
      }
    });
  }

  // ------------------------------------------------------------------ Apps synchronisées

  var appsTimer = null;

  function appsDrawerOpen() {
    var d = document.getElementById('ow-drawer');
    var t = document.getElementById('ow-drawer-title');
    return d && !d.hidden && t && t.textContent === 'Apps synchronisées';
  }

  function openApps() {
    B.openDrawer('Liaison', 'Apps synchronisées', '<p class="ow-help">Chargement…</p>');
    loadApps();
    if (appsTimer) window.clearInterval(appsTimer);
    appsTimer = window.setInterval(function () {
      if (!appsDrawerOpen()) { window.clearInterval(appsTimer); appsTimer = null; return; }
      loadApps();
    }, 20000);
  }

  function loadApps() {
    return api('/api/atak/overwatch/apps-sync?mapId=' + encodeURIComponent(mapId())).then(function (payload) {
      if (!appsDrawerOpen()) return;
      var rows = Array.isArray(payload && payload.apps) ? payload.apps : [];
      var active = rows.filter(function (r) { return r.status === 'actif'; }).length;
      var body = document.getElementById('ow-drawer-body');
      body.innerHTML = '<p class="ow-help">Une ligne par application du téléphone ou module qui transmet au poste. ' +
        '« Actif » = donnée reçue dans les ' + Math.round(Number(payload.active_after_sec || 900) / 60) + ' dernières minutes. Mise à jour toutes les 20 s.</p>' +
        '<div class="owi-apps-sum"><b>' + active + '</b> active' + (active > 1 ? 's' : '') + ' sur ' + rows.length + '</div>' +
        '<div class="owi-table-wrap"><table class="owi-table"><thead><tr><th>App</th><th>Module / source</th><th>Données transmises</th><th>Aujourd’hui</th><th>Dernière donnée</th><th>État</th></tr></thead><tbody>' +
        rows.map(function (r) {
          var st = r.status === 'actif' ? 'Actif' : (r.status === 'inactif' ? 'Inactif' : 'Jamais reçu');
          return '<tr class="is-' + esc(r.status) + '"><td><b>' + esc(r.app) + '</b></td><td>' + esc(r.module) + '</td><td>' + esc(r.data) +
            (r.telemetry_types && r.telemetry_types.length ? '<small>télémétrie : ' + esc(r.telemetry_types.join(', ')) + '</small>' : '') + '</td>' +
            '<td class="num">' + esc(r.today) + '</td>' +
            '<td>' + (r.age_sec == null ? '—' : esc(ago(r.age_sec)) + (r.last_author ? '<small>' + esc(r.last_author) + '</small>' : '')) + '</td>' +
            '<td><span class="owi-state">' + esc(st) + '</span></td></tr>';
        }).join('') + '</tbody></table></div>';
    }).catch(function () {
      if (!appsDrawerOpen()) return;
      document.getElementById('ow-drawer-body').innerHTML = '<p class="ow-help">Tableau indisponible : connexion au serveur ou session expirée.</p>';
    });
  }

  // ------------------------------------------------------------------ Fil de renseignement

  var feed = { items: [], types: [], counts: {}, active: {}, q: '', detail: null };
  var TYPE_COLORS = { reco: '#f97316', frs: '#4d9ffa', osint: '#a78bfa', sse: '#22c55e', report: '#e7b14d', photo: '#38bdf8', wanted: '#ef4444' };
  var showPhotosNext = false;
  var feedTimer = null;

  function intelTabsHtml(active) {
    return '<div class="owi-tabs" data-owi-tabs><button type="button" data-owi-tab="feed" class="' + (active === 'feed' ? 'is-active' : '') + '">Fil de renseignement</button>' +
      '<button type="button" data-owi-tab="photos" class="' + (active === 'photos' ? 'is-active' : '') + '">Photos</button></div>';
  }

  // Appelé par atak-overwatch-beta.js juste après l'ouverture du tiroir Renseignement (Photos).
  function afterIntelOpen() {
    if (showPhotosNext) {
      showPhotosNext = false;
      ensurePhotoTabs();
      return;
    }
    openFeed();
  }

  function ensurePhotoTabs() {
    var body = document.getElementById('ow-drawer-body');
    var title = document.getElementById('ow-drawer-title');
    if (!body || !title || title.textContent !== 'Photos' || body.querySelector('[data-owi-tabs]')) return;
    body.insertAdjacentHTML('afterbegin', intelTabsHtml('photos'));
  }

  function feedOpen() {
    var d = document.getElementById('ow-drawer');
    var t = document.getElementById('ow-drawer-title');
    return d && !d.hidden && t && t.textContent === 'Fil de renseignement';
  }

  function openFeed() {
    feed.detail = null;
    B.openDrawer('Renseignement', 'Fil de renseignement', intelTabsHtml('feed') + '<div id="owi-feed"><p class="ow-help">Chargement…</p></div>');
    loadFeed();
    if (feedTimer) window.clearInterval(feedTimer);
    feedTimer = window.setInterval(function () {
      if (!feedOpen()) { window.clearInterval(feedTimer); feedTimer = null; return; }
      if (!feed.detail && document.activeElement && document.activeElement.id !== 'owi-feed-q') loadFeed();
    }, 30000);
  }

  function loadFeed() {
    return api('/api/atak/overwatch/intel-feed?mapId=' + encodeURIComponent(mapId()) + '&limit=200').then(function (payload) {
      feed.items = Array.isArray(payload && payload.items) ? payload.items : [];
      feed.types = Array.isArray(payload && payload.types) ? payload.types : [];
      feed.counts = (payload && payload.counts) || {};
      if (feedOpen() && !feed.detail) paintFeed();
    }).catch(function () {
      var host = document.getElementById('owi-feed');
      if (host) host.innerHTML = '<p class="ow-help">Fil indisponible : connexion au serveur ou session expirée.</p>';
    });
  }

  function feedFiltered() {
    var any = Object.keys(feed.active).some(function (k) { return feed.active[k]; });
    var q = feed.q.toLowerCase();
    return feed.items.filter(function (it) {
      if (any && !feed.active[it.type]) return false;
      if (!q) return true;
      var meta = it.meta ? Object.keys(it.meta).map(function (k) { return it.meta[k]; }).join(' ') : '';
      return (it.type_label + ' ' + it.title + ' ' + it.text + ' ' + it.author + ' ' + it.grid + ' ' + meta).toLowerCase().indexOf(q) >= 0;
    });
  }

  function hl(text) {
    var safe = esc(text);
    var q = feed.q.trim();
    if (!q || q.length < 2) return safe;
    var re = new RegExp('(' + esc(q).replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    return safe.replace(re, '<mark>$1</mark>');
  }

  function feedItemHtml(it, i) {
    var c = TYPE_COLORS[it.type] || '#9aa5a1';
    var thumbs = (it.attachments || []).filter(function (a) { return a.is_image && a.url; }).slice(0, 4);
    var short = String(it.text || '');
    if (short.length > 260) short = short.slice(0, 257) + '…';
    var grid = it.grid || gridOf(it.pos_x, it.pos_y);
    return '<article class="owi-intel" style="--owi-c:' + c + '" data-owi-intel="' + i + '" tabindex="0">' +
      '<div class="owi-alert-head"><span class="owi-badge">' + esc(it.type_label) + '</span><b>' + hl(it.title || '') + '</b><time title="' + esc(it.at) + '">' + esc(clock(it.at)) + '</time></div>' +
      '<p>' + hl(short) + '</p>' +
      (thumbs.length ? '<div class="owi-thumbs">' + thumbs.map(function (a) { return '<span style="background-image:url(\'' + esc(a.url) + '\')"></span>'; }).join('') + '</div>' : '') +
      '<div class="owi-alert-foot"><span>' + esc(it.author || '—') + (grid ? ' · ' + esc(grid) : '') + ' · ' + esc(ago(ageFromSql(it.at))) + '</span>' +
      (hasPos(it.pos_x, it.pos_y) ? '<button type="button" class="ow-tag" data-owi-goto="' + esc(it.pos_x) + ',' + esc(it.pos_y) + '" data-owi-goto-label="' + esc(it.type_label) + '">Voir sur la carte</button>' : '') +
      '</div></article>';
  }

  function paintFeed() {
    var host = document.getElementById('owi-feed');
    if (!host) return;
    var list = feedFiltered();
    var chips = feed.types.map(function (t) {
      var n = feed.counts[t.value] || 0;
      return '<button type="button" class="owi-chip' + (feed.active[t.value] ? ' is-on' : '') + '" data-owi-type="' + esc(t.value) + '" style="--owi-c:' + (TYPE_COLORS[t.value] || '#9aa5a1') + '">' +
        esc(t.label) + ' <em>' + n + '</em></button>';
    }).join('');
    var hadFocus = document.activeElement && document.activeElement.id === 'owi-feed-q';
    host.innerHTML = '<label class="ow-search"><span>⌕</span><input id="owi-feed-q" placeholder="Rechercher dans tout le renseignement (texte, auteur, grille…)" value="' + esc(feed.q) + '"></label>' +
      '<div class="owi-chips">' + chips + '</div>' +
      '<p class="owi-sub">' + list.length + ' élément' + (list.length > 1 ? 's' : '') + '</p>' +
      (list.length ? list.slice(0, 150).map(function (it) { return feedItemHtml(it, feed.items.indexOf(it)); }).join('')
        : '<p class="ow-help">Rien ne correspond. Les notes RECO, fiches FRS/FRM, OSINT, fiches SSE, comptes rendus, légendes de photos et avis de recherche arrivent ici.</p>');
    if (hadFocus) {
      var q = document.getElementById('owi-feed-q');
      if (q) { q.focus(); q.setSelectionRange(q.value.length, q.value.length); }
    }
  }

  function paintDetail(it) {
    var host = document.getElementById('owi-feed');
    if (!host || !it) return;
    feed.detail = it;
    var c = TYPE_COLORS[it.type] || '#9aa5a1';
    var meta = it.meta || {};
    var rows = [['Type', it.type_label], ['Auteur', it.author], ['Date', it.at], ['Grille', it.grid || gridOf(it.pos_x, it.pos_y)]]
      .concat(Object.keys(meta).map(function (k) { return [k, meta[k]]; }))
      .filter(function (r) { return r[1] !== '' && r[1] != null; });
    host.innerHTML = '<button type="button" class="ow-tag" data-owi-back>← Retour au fil</button>' +
      '<article class="owi-intel owi-intel-detail" style="--owi-c:' + c + '">' +
      '<div class="owi-alert-head"><span class="owi-badge">' + esc(it.type_label) + '</span><b>' + esc(it.title || '') + '</b></div>' +
      '<dl class="owi-kv">' + rows.map(function (r) { return '<dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd>'; }).join('') + '</dl>' +
      '<div class="owi-fulltext">' + esc(it.text || '').replace(/\n/g, '<br>') + '</div>' +
      ((it.attachments || []).length ? '<div class="owi-attach">' + it.attachments.map(function (a) {
        return a.is_image
          ? '<a href="' + esc(a.url) + '" target="_blank" rel="noopener"><img src="' + esc(a.url) + '" alt="' + esc(a.caption || 'Pièce jointe') + '" loading="lazy"></a>'
          : '<a class="ow-tag" href="' + esc(a.url) + '" target="_blank" rel="noopener">' + esc(a.caption || 'Pièce jointe') + '</a>';
      }).join('') + '</div>' : '') +
      (hasPos(it.pos_x, it.pos_y) ? '<button type="button" class="ow-primary" data-owi-goto="' + esc(it.pos_x) + ',' + esc(it.pos_y) + '" data-owi-goto-label="' + esc(it.type_label) + '">Voir sur la carte</button>' : '') +
      '</article>';
  }

  // ------------------------------------------------------------------ Anneaux de géolocalisation

  var rings = [];
  var ringLayers = {};
  var ringsLocal = false;
  var LOCAL_RINGS_KEY = 'athena:owi-geoloc-rings';
  var HIDDEN_PHONE_RINGS_KEY = 'athena:owi-geoloc-phone-hidden';

  function loadRings() {
    if (!B) return Promise.resolve();
    return api('/api/atak/overwatch/geoloc-rings?mapId=' + encodeURIComponent(mapId())).then(function (payload) {
      ringsLocal = false;
      rings = Array.isArray(payload && payload.rings) ? payload.rings : [];
      drawRings();
    }).catch(function () {
      // Pas de serveur (hors ligne / ancienne version) : anneaux locaux à ce poste.
      ringsLocal = true;
      rings = store(LOCAL_RINGS_KEY) || [];
      drawRings();
    });
  }

  // Contacts « téléphone géolocalisé » (demande du hub) : cercle de précision dérivé, masquable localement.
  function phoneRings() {
    var hidden = store(HIDDEN_PHONE_RINGS_KEY) || {};
    var out = [];
    (B.getUnits ? B.getUnits() : []).forEach(function (u) {
      var ex = u && u.extra;
      if (typeof ex === 'string') { try { ex = JSON.parse(ex); } catch (e) { ex = {}; } }
      if (!ex || !(ex.phone_geoloc === true || ex.phone_geoloc === 1 || ex.phone_geoloc === '1' || ex.phone_geoloc === 'true')) return;
      if (!hasPos(u.pos_x, u.pos_y)) return;
      var key = 'unit:' + (u.id || u.call_sign);
      if (hidden[key]) return;
      out.push({
        id: key, label: 'GÉOLOC ' + (u.call_sign || ''), pos_x: Number(u.pos_x), pos_y: Number(u.pos_y),
        radius_m: Number(ex.precision_m || ex.radius_m || 150), source: 'hub', created_at: u.updated_at || '', derived: true
      });
    });
    return out;
  }

  function allRings() { return rings.concat(phoneRings()); }

  function ringPoints(x, y, r) {
    var out = [];
    for (var i = 0; i < 64; i++) {
      var a = (i / 64) * Math.PI * 2;
      out.push(B.worldToLatLng(x + Math.sin(a) * r, y + Math.cos(a) * r));
    }
    return out;
  }

  function ringPopup(r) {
    return '<div class="owi-pop"><b style="color:#facc15">' + esc(r.label || 'Géolocalisation') + '</b>' +
      '<div class="owi-pop-meta">Rayon ' + esc(Math.round(r.radius_m)) + ' m · ' + esc(r.grid_ref || gridOf(r.pos_x, r.pos_y)) + '</div>' +
      '<div class="owi-pop-meta">' + esc(r.source === 'phone' ? 'Téléphone ' + (r.author || '') : (r.source === 'hub' ? 'Téléphone suivi (hub)' : 'Saisi au poste ' + (r.author || ''))) +
      (r.created_at ? ' · ' + esc(clock(r.created_at)) : '') + '</div>' +
      '<button type="button" class="ow-tag red" data-owi-ring-del="' + esc(r.id) + '">Supprimer l’anneau</button></div>';
  }

  function drawRings() {
    var seen = {};
    allRings().forEach(function (r) {
      if (!hasPos(r.pos_x, r.pos_y)) return;
      var id = String(r.id);
      seen[id] = true;
      var x = Number(r.pos_x), y = Number(r.pos_y), rad = Math.max(5, Number(r.radius_m) || 150);
      var sig = [x, y, rad, r.label].join('|');
      var pack = ringLayers[id];
      if (pack && pack.sig === sig) return;
      if (pack) { pack.layers.forEach(function (l) { try { B.map.removeLayer(l); } catch (e) {} }); }
      var poly = L.polygon(ringPoints(x, y, rad), {
        color: '#facc15', weight: 1.6, dashArray: '6 5', fillColor: '#facc15', fillOpacity: 0.08, className: 'owi-ring'
      }).addTo(B.map);
      poly.bindPopup(ringPopup(r));
      var center = L.marker(B.worldToLatLng(x, y), {
        icon: L.divIcon({ className: 'owi-ring-label', html: '<i></i><span>' + esc(r.label || 'GÉOLOC') + ' · ' + esc(Math.round(rad)) + ' m</span>', iconSize: [12, 12], iconAnchor: [6, 6] }),
        zIndexOffset: 500
      }).addTo(B.map);
      center.bindPopup(ringPopup(r));
      ringLayers[id] = { sig: sig, layers: [poly, center] };
    });
    Object.keys(ringLayers).forEach(function (id) {
      if (seen[id]) return;
      ringLayers[id].layers.forEach(function (l) { try { B.map.removeLayer(l); } catch (e) {} });
      delete ringLayers[id];
    });
    paintRingList();
  }

  function deleteRing(id) {
    id = String(id);
    if (id.indexOf('unit:') === 0) {
      var hidden = store(HIDDEN_PHONE_RINGS_KEY) || {};
      hidden[id] = true;
      store(HIDDEN_PHONE_RINGS_KEY, hidden);
      B.map.closePopup();
      drawRings();
      return;
    }
    if (ringsLocal || id.indexOf('local-') === 0) {
      rings = rings.filter(function (r) { return String(r.id) !== id; });
      store(LOCAL_RINGS_KEY, rings.filter(function (r) { return String(r.id).indexOf('local-') === 0; }));
      B.map.closePopup();
      drawRings();
      return;
    }
    api('/api/atak/overwatch/geoloc-rings/' + encodeURIComponent(id) + '/supprimer', { method: 'POST', body: { mapId: mapId() } }).then(function () {
      rings = rings.filter(function (r) { return String(r.id) !== id; });
      B.map.closePopup();
      drawRings();
      toast('Anneau supprimé.');
    }).catch(function () { toast('Suppression impossible (connexion ou session).'); });
  }

  function clearRings() {
    store(HIDDEN_PHONE_RINGS_KEY, phoneRings().reduce(function (acc, r) { acc[r.id] = true; return acc; }, store(HIDDEN_PHONE_RINGS_KEY) || {}));
    if (ringsLocal) {
      rings = [];
      store(LOCAL_RINGS_KEY, null);
      drawRings();
      return;
    }
    api('/api/atak/overwatch/geoloc-rings/tout/supprimer', { method: 'POST', body: { mapId: mapId() } }).then(function () {
      rings = [];
      drawRings();
      toast('Anneaux effacés.');
    }).catch(function () { toast('Effacement impossible (connexion ou session).'); });
  }

  function addRing(data) {
    if (ringsLocal) {
      data.id = 'local-' + Date.now();
      data.source = 'web';
      data.created_at = new Date().toISOString().replace('T', ' ').slice(0, 19);
      rings.unshift(data);
      store(LOCAL_RINGS_KEY, rings.filter(function (r) { return String(r.id).indexOf('local-') === 0; }));
      drawRings();
      return;
    }
    api('/api/atak/overwatch/geoloc-rings', { method: 'POST', body: Object.assign({ mapId: mapId() }, data) }).then(function () {
      toast('Anneau posé.');
      loadRings();
    }).catch(function () { toast('Anneau non enregistré (connexion ou session).'); });
  }

  var armedRing = null;
  function ringFormValues() {
    var label = (document.getElementById('owi-ring-label') || {}).value || '';
    var radius = Number((document.getElementById('owi-ring-radius') || {}).value || 150);
    return { label: label.trim() || 'GÉOLOC', radius_m: Math.max(5, Math.min(20000, radius || 150)) };
  }

  function mountRingsUi() {
    var host = document.getElementById('owi-geoloc-host');
    if (!host || host.dataset.ready) return;
    host.dataset.ready = '1';
    host.innerHTML = '<p class="ow-help">Cercles de probabilité des géolocalisations (requête GÉOLOC du téléphone, suivi du hub, ou saisie ici). Supprimables depuis la carte ou la liste.</p>' +
      '<div class="owi-ring-form">' +
      '<label class="ow-row">Libellé<input id="owi-ring-label" maxlength="60" placeholder="ex. GÉOLOC 06 12…"></label>' +
      '<label class="ow-row">Rayon (m)<input id="owi-ring-radius" type="number" min="5" max="20000" step="5" value="150"></label>' +
      '<label class="ow-row">Grille ou X Y<input id="owi-ring-grid" placeholder="151173 ou 15190 17301"></label>' +
      '<div class="owi-ring-actions"><button type="button" class="ow-secondary" data-owi-ring-add>Poser à la grille</button>' +
      '<button type="button" class="ow-secondary" data-owi-ring-click>Poser d’un clic</button></div></div>' +
      '<div id="owi-ring-list"></div>';
  }

  function paintRingList() {
    var host = document.getElementById('owi-ring-list');
    if (!host) return;
    var list = allRings();
    host.innerHTML = (list.length ? list.map(function (r) {
      return '<div class="owi-ring-row"><button type="button" class="owi-ring-go" data-owi-goto="' + esc(r.pos_x) + ',' + esc(r.pos_y) + '" data-owi-goto-label="' + esc(r.label || 'GÉOLOC') + '">' +
        '<b>' + esc(r.label || 'GÉOLOC') + '</b><small>' + esc(Math.round(r.radius_m)) + ' m · ' + esc(r.grid_ref || gridOf(r.pos_x, r.pos_y)) +
        (r.created_at ? ' · ' + esc(clock(r.created_at)) : '') + '</small></button>' +
        '<button type="button" class="owi-x" data-owi-ring-del="' + esc(r.id) + '" title="Supprimer l’anneau" aria-label="Supprimer l’anneau">×</button></div>';
    }).join('') + '<button type="button" class="ow-secondary owi-ring-clear" data-owi-ring-clear>Tout effacer</button>'
      : '<p class="ow-help">Aucun anneau.</p>') + (ringsLocal ? '<p class="ow-help">Hors ligne : anneaux gardés sur ce poste seulement.</p>' : '');
  }

  // ------------------------------------------------------------------ Curseur du joueur

  var CURSOR_DEFAULT = { shape: 'pin', color: '#22d3ee', size: 22, label: true, heading: true, callsign: '' };
  function cursorKey() {
    var u = window.ATAK_USER || {};
    return 'athena:owi-cursor:' + (u.id || u.steamId || u.callsign || 'poste');
  }
  function cursorPrefs() { return Object.assign({}, CURSOR_DEFAULT, store(cursorKey()) || {}); }
  function myAliases(prefs) {
    var u = window.ATAK_USER || {};
    var out = [];
    [prefs.callsign, u.callsign, u.armaCallsign, u.displayName].forEach(function (v) {
      var s = String(v || '').trim().toLowerCase();
      if (s && out.indexOf(s) < 0) out.push(s);
    });
    return prefs.callsign ? [String(prefs.callsign).trim().toLowerCase()] : out;
  }

  function shapeSvg(shape, color, size) {
    var s = size;
    switch (shape) {
      case 'arrow':
        return '<path d="M0,' + (-s * 0.55) + ' L' + (s * 0.4) + ',' + (s * 0.45) + ' L0,' + (s * 0.22) + ' L' + (-s * 0.4) + ',' + (s * 0.45) + ' Z" fill="' + color + '" stroke="#06100d" stroke-width="1.5" stroke-linejoin="round"/>';
      case 'nato':
        return '<rect x="' + (-s * 0.55) + '" y="' + (-s * 0.38) + '" width="' + (s * 1.1) + '" height="' + (s * 0.76) + '" fill="rgba(6,16,13,.75)" stroke="' + color + '" stroke-width="2"/>' +
          '<path d="M' + (-s * 0.55) + ',' + (-s * 0.38) + ' L' + (s * 0.55) + ',' + (s * 0.38) + ' M' + (s * 0.55) + ',' + (-s * 0.38) + ' L' + (-s * 0.55) + ',' + (s * 0.38) + '" stroke="' + color + '" stroke-width="1.6"/>';
      case 'dot':
        return '<circle r="' + (s * 0.3) + '" fill="' + color + '" stroke="#06100d" stroke-width="1.5"/><circle r="' + (s * 0.48) + '" fill="none" stroke="' + color + '" stroke-width="1" opacity=".55"/>';
      default:
        // Repère « goutte » : la pointe est sur la position.
        return '<path d="M0,0 C' + (-s * 0.12) + ',' + (-s * 0.3) + ' ' + (-s * 0.42) + ',' + (-s * 0.5) + ' ' + (-s * 0.42) + ',' + (-s * 0.78) +
          ' A' + (s * 0.42) + ',' + (s * 0.42) + ' 0 1 1 ' + (s * 0.42) + ',' + (-s * 0.78) + ' C' + (s * 0.42) + ',' + (-s * 0.5) + ' ' + (s * 0.12) + ',' + (-s * 0.3) + ' 0,0 Z" fill="' + color + '" stroke="#06100d" stroke-width="1.5"/>' +
          '<circle cy="' + (-s * 0.78) + '" r="' + (s * 0.16) + '" fill="#06100d"/>';
    }
  }

  function cursorHtml(prefs, heading, label) {
    var size = Math.max(12, Math.min(48, Number(prefs.size) || 22));
    var box = Math.round(size * 4);
    var half = box / 2;
    var hasHdg = prefs.heading && heading != null && Number.isFinite(Number(heading));
    var rot = prefs.shape === 'arrow' && hasHdg ? ' transform="rotate(' + Number(heading) + ')"' : '';
    var line = hasHdg
      ? '<line x1="0" y1="0" x2="0" y2="' + (-half + 2) + '" stroke="' + prefs.color + '" stroke-width="1.6" stroke-dasharray="4 4" transform="rotate(' + Number(heading) + ')" opacity=".9"/>'
      : '';
    return '<svg class="owi-self-svg" width="' + box + '" height="' + box + '" viewBox="' + (-half) + ' ' + (-half) + ' ' + box + ' ' + box + '" aria-hidden="true">' +
      line + '<g' + rot + '>' + shapeSvg(prefs.shape, prefs.color, size) + '</g></svg>' +
      (prefs.label && label ? '<span class="owi-self-label" style="--owi-c:' + prefs.color + ';left:' + (half + size * 0.5) + 'px">' + esc(label) + '</span>' : '');
  }

  // Appelé par markerIcon() du fichier principal : icône personnalisée pour mon propre contact, sinon null.
  function selfIcon(unit, opts) {
    if (!B || !unit) return null;
    var prefs = cursorPrefs();
    if (prefs.enabled === false) return null;
    var cs = String(B.callsign ? B.callsign(unit) : (unit.call_sign || '')).trim().toLowerCase();
    if (!cs || myAliases(prefs).indexOf(cs) < 0) return null;
    var heading = null;
    try { heading = B.unitHeading ? B.unitHeading(unit) : null; } catch (e) { heading = null; }
    var size = Math.max(12, Math.min(48, Number(prefs.size) || 22));
    var box = Math.round(size * 4);
    return L.divIcon({
      className: 'owi-self-marker' + (opts && opts.selected ? ' is-selected' : ''),
      html: cursorHtml(prefs, heading, B.callsign ? B.callsign(unit) : unit.call_sign),
      iconSize: [box, box],
      iconAnchor: [box / 2, box / 2]
    });
  }

  function mountCursorUi() {
    var host = document.getElementById('owi-cursor-host');
    if (!host || host.dataset.ready) return;
    host.dataset.ready = '1';
    var p = cursorPrefs();
    var u = window.ATAK_USER || {};
    var auto = u.callsign || u.armaCallsign || u.displayName || '';
    host.innerHTML = '<p class="ow-help">Votre propre contact sur la carte (reconnu par votre indicatif). Réglage gardé pour votre compte sur ce navigateur.</p>' +
      '<label class="ow-toggle"><input type="checkbox" id="owi-cur-on"' + (p.enabled === false ? '' : ' checked') + '> Curseur personnalisé</label>' +
      '<label class="ow-row">Mon indicatif<input id="owi-cur-cs" maxlength="40" placeholder="' + esc(auto || 'automatique') + '" value="' + esc(p.callsign) + '"></label>' +
      '<label class="ow-row">Forme<span class="ow-select"><select id="owi-cur-shape">' +
      [['pin', 'Repère'], ['arrow', 'Flèche (cap)'], ['nato', 'Symbole OTAN'], ['dot', 'Point']].map(function (o) {
        return '<option value="' + o[0] + '"' + (p.shape === o[0] ? ' selected' : '') + '>' + o[1] + '</option>';
      }).join('') + '</select></span></label>' +
      '<label class="ow-row">Couleur<input type="color" id="owi-cur-color" value="' + esc(p.color) + '"></label>' +
      '<label class="ow-row">Taille<input type="range" id="owi-cur-size" min="12" max="48" value="' + esc(p.size) + '"></label>' +
      '<label class="ow-toggle"><input type="checkbox" id="owi-cur-label"' + (p.label ? ' checked' : '') + '> Afficher l’indicatif</label>' +
      '<label class="ow-toggle"><input type="checkbox" id="owi-cur-heading"' + (p.heading ? ' checked' : '') + '> Ligne de cap</label>' +
      '<div class="owi-cur-preview" id="owi-cur-preview"></div>' +
      '<button type="button" class="ow-secondary" data-owi-cur-reset>Revenir au réglage par défaut</button>';
    function save() {
      var next = {
        enabled: !!document.getElementById('owi-cur-on').checked,
        callsign: document.getElementById('owi-cur-cs').value.trim(),
        shape: document.getElementById('owi-cur-shape').value,
        color: document.getElementById('owi-cur-color').value,
        size: Number(document.getElementById('owi-cur-size').value),
        label: !!document.getElementById('owi-cur-label').checked,
        heading: !!document.getElementById('owi-cur-heading').checked
      };
      store(cursorKey(), next);
      preview();
      if (B && B.renderMap) B.renderMap();
    }
    function preview() {
      var box = document.getElementById('owi-cur-preview');
      if (box) box.innerHTML = cursorHtml(cursorPrefs(), 45, cursorPrefs().callsign || auto || 'MOI');
    }
    host.addEventListener('input', save);
    host.addEventListener('change', save);
    host.querySelector('[data-owi-cur-reset]').addEventListener('click', function () {
      store(cursorKey(), null);
      host.dataset.ready = '';
      mountCursorUi();
      if (B && B.renderMap) B.renderMap();
    });
    preview();
  }

  // ------------------------------------------------------------------ Branchements

  function mountMoreMenu() {
    var menu = document.getElementById('ow-more-menu');
    if (!menu || menu.querySelector('[data-owi-open]')) return;
    var anchor = menu.querySelector('[data-ow-replay]');
    [['apps', 'Apps synchronisées'], ['feed', 'Fil de renseignement'], ['alerts', 'Alertes et balises']].forEach(function (o) {
      var b = document.createElement('button');
      b.type = 'button';
      b.setAttribute('data-owi-open', o[0]);
      b.textContent = o[1];
      menu.insertBefore(b, anchor || null);
    });
  }

  function onClick(event) {
    var t = event.target;
    var el;
    if ((el = t.closest('[data-owi-goto]'))) {
      var xy = String(el.getAttribute('data-owi-goto')).split(',');
      goto(Number(xy[0]), Number(xy[1]), el.getAttribute('data-owi-goto-label') || '');
      return;
    }
    if ((el = t.closest('[data-owi-ack]'))) { ackAlert(el.getAttribute('data-owi-ack'), false); return; }
    if ((el = t.closest('[data-owi-unack]'))) { ackAlert(el.getAttribute('data-owi-unack'), true); return; }
    if ((el = t.closest('[data-owi-close-alerts]'))) {
      alertsOpen = false;
      document.getElementById('owi-alerts-panel').hidden = true;
      return;
    }
    if ((el = t.closest('[data-owi-beacon-hide]'))) {
      var key = el.getAttribute('data-owi-beacon-hide');
      var h = hiddenBeacons();
      if (h[key]) delete h[key]; else h[key] = true;
      store(dismissedBeaconKey, h);
      B.map.closePopup();
      drawBeacons();
      if (alertsOpen) paintAlertsPanel();
      return;
    }
    if ((el = t.closest('[data-owi-open]'))) {
      var what = el.getAttribute('data-owi-open');
      var menu = document.getElementById('ow-more-menu');
      if (menu) menu.hidden = true;
      if (what === 'apps') openApps();
      else if (what === 'feed') { if (B.openView) B.openView('intel'); else openFeed(); }
      else if (what === 'alerts') { alertsOpen = true; document.getElementById('owi-alerts-panel').hidden = false; paintAlertsPanel(); }
      return;
    }
    if ((el = t.closest('[data-owi-tab]'))) {
      var tab = el.getAttribute('data-owi-tab');
      if (tab === 'feed') openFeed();
      else { showPhotosNext = true; B.openView('intel'); }
      return;
    }
    if ((el = t.closest('[data-owi-type]'))) {
      var ty = el.getAttribute('data-owi-type');
      feed.active[ty] = !feed.active[ty];
      paintFeed();
      return;
    }
    if (t.closest('[data-owi-back]')) { feed.detail = null; paintFeed(); return; }
    if ((el = t.closest('[data-owi-intel]')) && !t.closest('button, a')) {
      paintDetail(feed.items[Number(el.getAttribute('data-owi-intel'))]);
      return;
    }
    if ((el = t.closest('[data-owi-ring-del]'))) { deleteRing(el.getAttribute('data-owi-ring-del')); return; }
    if (t.closest('[data-owi-ring-clear]')) {
      if (window.confirm('Effacer tous les anneaux de géolocalisation ?')) clearRings();
      return;
    }
    if (t.closest('[data-owi-ring-add]')) {
      var pos = parseGridInput((document.getElementById('owi-ring-grid') || {}).value);
      if (!pos) { toast('Grille illisible : 6 ou 8 chiffres, ou « X Y » en mètres.'); return; }
      var v = ringFormValues();
      addRing({ label: v.label, radius_m: v.radius_m, pos_x: pos.x, pos_y: pos.y, grid_ref: (document.getElementById('owi-ring-grid') || {}).value.trim() });
      return;
    }
    if (t.closest('[data-owi-ring-click]')) {
      armedRing = ringFormValues();
      toast('Cliquez la carte pour poser l’anneau.');
      B.map.once('click', function (e) {
        if (!armedRing) return;
        var w = B.latLngToWorld(e.latlng);
        addRing({ label: armedRing.label, radius_m: armedRing.radius_m, pos_x: w.x, pos_y: w.y });
        armedRing = null;
      });
    }
  }

  function onInput(event) {
    if (event.target && event.target.id === 'owi-feed-q') {
      feed.q = event.target.value;
      paintFeed();
    }
  }

  function onKey(event) {
    if (event.key !== 'Enter') return;
    var el = event.target && event.target.closest && event.target.closest('[data-owi-intel]');
    if (el) paintDetail(feed.items[Number(el.getAttribute('data-owi-intel'))]);
  }

  function boot() {
    if (B) return;
    B = window.OverwatchBeta;
    if (!B || !B.map) { B = null; return; }
    mountAlertsUi();
    mountMoreMenu();
    mountRingsUi();
    mountCursorUi();
    document.addEventListener('click', onClick);
    document.addEventListener('input', onInput);
    document.addEventListener('keydown', onKey);
    var body = document.getElementById('ow-drawer-body');
    if (body && window.MutationObserver) {
      // Le tiroir Photos se redessine seul quand une photo arrive : on remet les onglets.
      new MutationObserver(function () { ensurePhotoTabs(); }).observe(body, { childList: true });
    }
    loadAlerts();
    loadRings();
    window.setInterval(loadAlerts, POLL_ALERTS_MS);
    window.setInterval(loadRings, POLL_RINGS_MS);
    window.addEventListener('atak:units-updated', function () { drawRings(); });
    window.setInterval(function () { if (phoneRings().length || Object.keys(ringLayers).length) drawRings(); }, 5000);
    if (B.renderMap) B.renderMap();
  }

  window.OverwatchIntel = {
    chatAlertCard: chatAlertCard,
    parseAlertLine: parseAlertLine,
    afterIntelOpen: afterIntelOpen,
    selfIcon: selfIcon,
    openApps: function () { boot(); if (B) openApps(); },
    openFeed: function () { boot(); if (B) openFeed(); },
    goto: function (x, y, label) { boot(); if (B) goto(x, y, label); },
    reloadAlerts: loadAlerts
  };

  if (window.OverwatchBeta) boot();
  window.addEventListener('atak:mapready', boot);
}());
