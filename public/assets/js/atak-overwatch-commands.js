/*
 * COMSPEC Athena — Overwatch : commandes du poste vers les téléphones en jeu.
 *  - Drones appairés : fonctions du drone (pas le pilotage) et photo de sa caméra.
 *  - Charges ACE suivies : raccorder à ATAK, rendre au déclencheur local, mise à feu, séquence.
 *  - Mon téléphone ATAK : notifications reçues par MON téléphone, tout marquer lu, préférences.
 * Le téléphone du pilote / du propriétaire exécute puis rend compte : le journal affiche la réponse.
 * Dépend de window.OverwatchBeta (atak-overwatch-beta.js) ; ouverture d'un drone par l'événement
 * « ow:uav-select » émis au clic sur un drone de la carte.
 */
(function () {
  'use strict';

  var OW = null;
  var root = null;
  var view = 'uav';
  var openNow = false;
  var pollTimer = null;
  var selectedUav = null;
  var uavRows = [];
  var charges = [];
  var canFire = false;
  var cmdLog = [];
  var picked = {};
  var pick = null;
  var pickLayer = null;
  var notifData = null;
  var lastErr = '';

  var UAV_LABELS = {
    hover: 'Stationnaire', home: 'Retour pilote', rth: 'Retour décollage', land: 'Atterrir', takeoff: 'Décoller',
    standby: 'Veille', resume: 'Reprendre', force: 'Forcer l’exécution', follow_me: 'Suivre le pilote',
    follow_unit: 'Suivre une unité', goto: 'Aller à', loiter: 'Orbite', observe: 'Observer', hunt: 'Recherche',
    alt: 'Altitude', speed: 'Vitesse', photo: 'Photo'
  };
  var EXPLO_LABELS = { arm: 'Raccorder à ATAK', disarm: 'Rendre au déclencheur', fire: 'Mise à feu', sequence: 'Séquence' };
  var STATUS_LABELS = {
    pending: 'En file', accepted: 'Pris par le téléphone', done: 'Exécuté', failed: 'Échec',
    expired: 'Sans réponse', cancelled: 'Annulé'
  };
  var NOTIF_TYPES = [
    ['INFO', 'Info'], ['MESSAGE', 'Messages'], ['WARNING', 'Alertes'], ['TACTICAL', 'Tactique'], ['SUCCESS', 'Réussites'], ['ERROR', 'Erreurs']
  ];

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function toast(t) { if (OW && OW.toast) OW.toast(t); }
  function api(path, opts) {
    // Le helper du poste lève une erreur sans le corps : on relit le message du serveur ici.
    var o = opts || {};
    var headers = { 'Accept': 'application/json' };
    var csrf = OW && OW.csrf ? OW.csrf() : '';
    var init = { credentials: 'include', method: o.method || 'GET', headers: headers };
    if (o.body) {
      headers['Content-Type'] = 'application/json';
      if (csrf) headers['X-CSRF-TOKEN'] = csrf;
      init.body = JSON.stringify(Object.assign({ _csrf_token: csrf, mapId: OW.mapId }, o.body));
    }
    var base = String(window.ATAK_API_BASE || '').replace(/\/$/, '');
    return fetch(base + path, init).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok) throw new Error((j && j.message) || ('Erreur ' + r.status));
        return j;
      });
    });
  }
  function mapQ() { return 'mapId=' + encodeURIComponent(OW.mapId); }
  function fmtTime(s) {
    var m = /(\d{2}):(\d{2}):(\d{2})/.exec(String(s || ''));
    return m ? m[1] + ':' + m[2] + ':' + m[3] : '';
  }

  /* ---------------- Squelette ---------------- */

  function build() {
    var dock = document.createElement('div');
    dock.className = 'owc-dock';
    dock.innerHTML =
      '<button type="button" data-owc-open="uav" title="Commander un drone appairé">' + svgUav() + '<span>Drones</span></button>' +
      '<button type="button" data-owc-open="explo" title="Charges ACE suivies">' + svgCharge() + '<span>Charges</span></button>' +
      '<button type="button" data-owc-open="notif" title="Notifications de mon téléphone ATAK">' + svgPhone() + '<span>Mon ATAK</span><b class="owc-badge" hidden></b></button>';
    root = document.createElement('section');
    root.className = 'owc-panel';
    root.hidden = true;
    root.setAttribute('aria-label', 'Commandes du poste');
    root.innerHTML =
      '<header class="owc-head"><nav class="owc-tabs">' +
      '<button type="button" data-owc-tab="uav">Drones</button><button type="button" data-owc-tab="explo">Charges</button>' +
      '<button type="button" data-owc-tab="notif">Mon ATAK</button></nav>' +
      '<button type="button" class="owc-close" data-owc-close aria-label="Fermer">×</button></header>' +
      '<div class="owc-body" id="owc-body"></div>';
    var host = document.getElementById('ow-map') ? document.getElementById('ow-map').parentNode : document.body;
    host.appendChild(dock);
    host.appendChild(root);
    dock.addEventListener('click', function (e) {
      var b = e.target.closest('[data-owc-open]');
      if (b) open(b.getAttribute('data-owc-open'));
    });
    root.addEventListener('click', onClick);
    root.addEventListener('change', onChange);
  }

  function svgUav() {
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="5" r="3"/><circle cx="19" cy="5" r="3"/><circle cx="5" cy="19" r="3"/><circle cx="19" cy="19" r="3"/><path d="M7.2 7.2l3 3M16.8 7.2l-3 3M7.2 16.8l3-3M16.8 16.8l-3-3"/><rect x="9.5" y="9.5" width="5" height="5" rx="1"/></svg>';
  }
  function svgCharge() {
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="9" width="13" height="10" rx="1.5"/><path d="M17 12h3M8 9V6.5a2 2 0 0 1 2-2h1M14 3l1.5 1.5M17 2v2M19.5 4.5L18 6"/></svg>';
  }
  function svgPhone() {
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="6.5" y="2.5" width="11" height="19" rx="2"/><path d="M10.5 18.5h3"/><path d="M9.5 8.5h5M9.5 11.5h5"/></svg>';
  }

  function open(which) {
    view = which || view;
    openNow = true;
    root.hidden = false;
    Array.prototype.forEach.call(root.querySelectorAll('[data-owc-tab]'), function (b) {
      b.classList.toggle('is-on', b.getAttribute('data-owc-tab') === view);
    });
    render();
    refresh();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(refresh, 3000);
  }
  function close() {
    openNow = false;
    root.hidden = true;
    cancelPick();
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  }

  /* ---------------- Données ---------------- */

  function refresh() {
    if (!openNow) return;
    var jobs = [];
    if (view === 'uav') {
      jobs.push(api('/api/atak/vehicles?' + mapQ() + '&vehicle_class=UAV').then(function (p) {
        uavRows = (p.vehicles || []).filter(function (v) {
          var pr = props(v);
          return pr.kind === 'uav' && String(v.status || '').toUpperCase() !== 'DESTROYED' && String(v.status || '').toUpperCase() !== 'ABANDONED';
        });
        if (selectedUav) {
          var k = selectedUav.vehicle_callsign;
          selectedUav = uavRows.filter(function (v) { return v.vehicle_callsign === k; })[0] || selectedUav;
        }
      }));
    }
    if (view === 'explo') {
      jobs.push(api('/api/atak/explosive-timers?' + mapQ()).then(function (p) {
        charges = (p.items || []).filter(function (c) { return c.status === 'armed' || c.status === 'detonated' || c.status === 'defused'; });
        canFire = !!p.can_command_detonate;
        charges.forEach(function (c) { c._recvAt = Date.now(); });
      }));
    }
    if (view === 'notif') {
      jobs.push(api('/api/atak/my-phone/notifs').then(function (p) { notifData = p; paintBadge(); }));
    }
    if (view !== 'notif') {
      jobs.push(api('/api/atak/web-commands?' + mapQ() + '&kind=' + view).then(function (p) { cmdLog = p.commands || []; }));
    }
    Promise.all(jobs).then(function () { lastErr = ''; render(); }).catch(function (e) { lastErr = e.message; render(); });
  }

  function props(v) {
    var p = v && v.properties;
    if (typeof p === 'string') { try { p = JSON.parse(p); } catch (e) { p = {}; } }
    return p || {};
  }

  /* ---------------- Rendu ---------------- */

  function render() {
    var body = root.querySelector('#owc-body');
    if (!body) return;
    var keep = body.querySelector('.owc-scroll') ? body.querySelector('.owc-scroll').scrollTop : 0;
    var html = lastErr ? '<p class="owc-err">' + esc(lastErr) + '</p>' : '';
    if (view === 'uav') html += renderUav();
    if (view === 'explo') html += renderExplo();
    if (view === 'notif') html += renderNotif();
    body.innerHTML = html;
    var sc = body.querySelector('.owc-scroll');
    if (sc) sc.scrollTop = keep;
  }

  function logHtml(filter) {
    var rows = cmdLog.filter(filter || function () { return true; }).slice(0, 12);
    if (!rows.length) return '<p class="owc-hint">Aucun ordre récent.</p>';
    return '<ol class="owc-log">' + rows.map(function (c) {
      var lbl = (c.kind === 'uav' ? UAV_LABELS[c.cmd] : EXPLO_LABELS[c.cmd]) || c.cmd;
      return '<li class="owc-st-' + esc(c.status) + '"><span class="owc-log-t">' + esc(fmtTime(c.created_at)) + '</span>' +
        '<span class="owc-log-w">' + esc(lbl) + ' · ' + esc(c.target_label) + '</span>' +
        '<span class="owc-pill">' + esc(STATUS_LABELS[c.status] || c.status) + '</span>' +
        (c.result_text ? '<span class="owc-log-r">' + esc(c.result_text) + '</span>' : '') +
        (c.status === 'pending' ? '<button type="button" class="owc-link" data-owc-cancel="' + c.id + '">Annuler</button>' : '') +
        '</li>';
    }).join('') + '</ol>';
  }

  function renderUav() {
    var out = '<div class="owc-scroll">';
    if (!uavRows.length) {
      out += '<p class="owc-hint">Aucun drone appairé en vol sur cette carte. Un drone apparaît ici quand un pilote l’appaire à son téléphone COMSPEC ATAK.</p>';
    } else {
      out += '<div class="owc-chips">' + uavRows.map(function (v) {
        var p = props(v);
        var on = selectedUav && selectedUav.vehicle_callsign === v.vehicle_callsign;
        return '<button type="button" class="owc-chip' + (on ? ' is-on' : '') + '" data-owc-uav="' + esc(v.vehicle_callsign) + '">' +
          esc(p.name || p.pilot || v.vehicle_name || 'Drone') + '</button>';
      }).join('') + '</div>';
    }
    if (selectedUav) {
      var p = props(selectedUav);
      var paired = !!(p.pilot_uid && p.net_id);
      out += '<div class="owc-card"><div class="owc-card-h"><strong>' + esc(p.name || selectedUav.vehicle_name || 'Drone') + '</strong>' +
        '<span class="owc-pill">' + esc(p.mode || '—') + '</span></div>' +
        '<p class="owc-meta">Pilote ' + esc(p.pilot || '—') + ' · ' + esc(p.model || '') + ' · ' +
        (p.alt_agl != null ? esc(p.alt_agl) + ' m sol' : '') + (p.speed_kmh != null ? ' · ' + esc(p.speed_kmh) + ' km/h' : '') +
        (p.task ? ' · tâche ' + esc(p.task) : '') + '</p>' +
        (paired ? '' : '<p class="owc-warn">Drone connecté par un terminal UAV, pas appairé à un téléphone : il ne reçoit pas d’ordres du poste.</p>') +
        '</div>';
      if (paired) {
        out += '<h4>Vol</h4><div class="owc-grid">' +
          btn('hover') + btn('home') + btn('rth') + btn('land') + btn('takeoff') + btn('follow_me') +
          '</div><div class="owc-row"><select id="owc-follow" aria-label="Unité à suivre"><option value="">Unité à suivre…</option>' +
          unitOptions() + '</select><button type="button" data-owc-cmd="follow_unit">Suivre</button></div>' +
          '<h4>Tâches sur la carte</h4><div class="owc-grid">' +
          pickBtn('goto', 'Aller à') + pickBtn('loiter', 'Orbite') + pickBtn('observe', 'Observer') + pickBtn('hunt', 'Recherche') +
          '</div><div class="owc-row"><label>Rayon <input type="number" id="owc-radius" min="30" max="1000" step="10" value="150"> m</label>' +
          (pick ? '<span class="owc-warn">Cliquez sur la carte · <button type="button" class="owc-link" data-owc-pickcancel>annuler</button></span>' : '') +
          '</div><h4>Réglages</h4><div class="owc-row owc-presets"><span>Altitude</span>' +
          [10, 20, 40, 80, 120].map(function (a) { return '<button type="button" data-owc-alt="' + a + '">' + a + ' m</button>'; }).join('') +
          '</div><div class="owc-row owc-presets"><span>Vitesse</span>' +
          [10, 20, 40, 80, 120].map(function (s) { return '<button type="button" data-owc-speed="' + s + '">' + s + '</button>'; }).join('') +
          '<span>km/h</span></div><h4>Modes</h4><div class="owc-grid">' +
          '<button type="button" data-owc-cmd="standby" data-owc-kind="LAND">Veille au sol</button>' +
          '<button type="button" data-owc-cmd="standby" data-owc-kind="HOVER">Veille en l’air</button>' + btn('resume') +
          '<button type="button" data-owc-cmd="force" class="owc-amber" title="Renvoie le dernier ordre de vol et remet l’IA du drone d’aplomb">Forcer l’exécution</button>' +
          '</div><button type="button" class="owc-photo" data-owc-cmd="photo">' + svgCam() + ' PRENDRE UNE PHOTO</button>' +
          '<p class="owc-hint">Photo : l’écran du pilote bascule ~0,4 s sur la caméra du drone. Refusée si le pilote est dans un menu, en pause, sur la carte ou en Zeus. La photo arrive dans Photos.</p>';
      }
      out += '<h4>Journal</h4>' + logHtml(function (c) { return c.target_ref === String(p.net_id || ''); });
    } else {
      out += '<h4>Journal</h4>' + logHtml();
    }
    return out + '</div>';
  }
  function btn(cmd) { return '<button type="button" data-owc-cmd="' + cmd + '">' + esc(UAV_LABELS[cmd]) + '</button>'; }
  function pickBtn(cmd, lbl) {
    return '<button type="button" data-owc-pick="' + cmd + '"' + (pick && pick.cmd === cmd ? ' class="is-on"' : '') + '>' + esc(lbl) + '</button>';
  }
  function svgCam() {
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>';
  }
  function unitOptions() {
    var list = (OW.getUnits ? OW.getUnits() : []) || [];
    var seen = {};
    return list.map(function (u) { return OW.callsign ? OW.callsign(u) : (u.callsign || ''); })
      .filter(function (c) { if (!c || seen[c]) return false; seen[c] = 1; return true; })
      .sort().map(function (c) { return '<option value="' + esc(c) + '">' + esc(c) + '</option>'; }).join('');
  }

  function renderExplo() {
    var armed = charges.filter(function (c) { return c.status === 'armed'; });
    var out = '<div class="owc-scroll">';
    if (!canFire) out += '<p class="owc-warn">Connectez-vous au portail pour commander les charges.</p>';
    out += '<p class="owc-hint">Exécuté par le téléphone du propriétaire : allumé, en liaison et à portée (ou exploseur à portée pour une charge non raccordée). Une minuterie lancée ne se commande pas.</p>';
    if (!armed.length) out += '<p class="owc-hint">Aucune charge armée suivie.</p>';
    else {
      out += '<table class="owc-table"><thead><tr><th></th><th>Charge</th><th>Propriétaire</th><th>Mode</th><th>Grille</th><th>État</th><th></th></tr></thead><tbody>' +
        armed.map(function (c) {
          var timer = c.has_countdown ? remaining(c) : '';
          var isTimer = c.trigger_kind === 'timer';
          return '<tr><td>' + (isTimer ? '' : '<input type="checkbox" data-owc-pickc="' + c.id + '"' + (picked[c.id] ? ' checked' : '') + ' aria-label="Choisir">') + '</td>' +
            '<td>' + esc(c.magazine_label || 'Charge') + '</td><td>' + esc(c.author || '—') + '</td>' +
            '<td>' + esc(c.trigger_label || c.trigger_kind) + '</td><td class="owc-mono">' + esc(c.grid_ref || '') + '</td>' +
            '<td>' + esc(c.status_label || 'Armée') + (timer ? ' <b class="owc-timer" data-owc-timer="' + c.id + '">' + timer + '</b>' : '') + '</td>' +
            '<td class="owc-acts">' + (isTimer ? '' :
              (c.trigger_kind === 'atak'
                ? '<button type="button" data-owc-ex="disarm" data-id="' + c.id + '" title="Rendre au déclencheur local : le poste ne pourra plus la tirer">Rendre</button>'
                : '<button type="button" data-owc-ex="arm" data-id="' + c.id + '" title="Raccorder à ATAK : déclenchable par le téléphone et le poste">Raccorder</button>') +
              '<button type="button" class="owc-red" data-owc-ex="fire" data-id="' + c.id + '"' + (canFire ? '' : ' disabled') + '>FEU</button>') + '</td></tr>';
        }).join('') + '</tbody></table>' +
        '<div class="owc-row owc-seq"><strong>Séquence</strong><label>Délai <input type="number" id="owc-delay" min="0" max="120" value="5"> s</label>' +
        '<label>Intervalle <input type="number" id="owc-gap" min="0" max="30" step="0.5" value="1"> s</label>' +
        '<button type="button" class="owc-red" data-owc-seq' + (canFire ? '' : ' disabled') + '>Lancer (' + Object.keys(picked).filter(function (k) { return picked[k]; }).length + ')</button></div>';
    }
    var done = charges.filter(function (c) { return c.status !== 'armed'; }).slice(0, 6);
    if (done.length) {
      out += '<h4>Récemment</h4><ul class="owc-list">' + done.map(function (c) {
        return '<li>' + esc(c.magazine_label || 'Charge') + ' · ' + esc(c.author || '') + ' · ' + esc(c.status_label || c.status) + '</li>';
      }).join('') + '</ul>';
    }
    return out + '<h4>Journal</h4>' + logHtml() + '</div>';
  }
  function remaining(c) {
    var s = Math.max(0, Number(c.remaining_seconds || 0) - Math.floor((Date.now() - (c._recvAt || Date.now())) / 1000));
    var m = Math.floor(s / 60);
    return m + ':' + String(s % 60).padStart(2, '0');
  }

  function renderNotif() {
    var d = notifData;
    if (!d) return '<p class="owc-hint">Chargement…</p>';
    if (!d.linked) return '<p class="owc-warn">' + esc(d.message || 'Compte Steam non lié.') + '</p>';
    var pr = d.prefs || {};
    var muted = {};
    (pr.muted || []).forEach(function (t) { muted[t] = 1; });
    var out = '<div class="owc-scroll"><div class="owc-row"><span class="owc-meta">' +
      (pr.last_seen_at ? 'Téléphone vu ' + esc(fmtTime(pr.last_seen_at)) : 'Téléphone pas encore vu en jeu') +
      ' · ' + esc(d.unread || 0) + ' non lue(s)</span><button type="button" data-owc-readall>Tout marquer lu</button></div>';
    out += '<details class="owc-prefs"><summary>Préférences du téléphone' +
      (pr.updated_at && !pr.synced_at ? ' <span class="owc-pill">en attente du jeu</span>' : (pr.synced_at ? ' <span class="owc-pill owc-ok">appliquées</span>' : '')) +
      '</summary><p class="owc-hint">Types coupés : gardés dans l’historique, sans bandeau ni son.</p><div class="owc-grid">' +
      NOTIF_TYPES.map(function (t) {
        return '<label class="owc-check"><input type="checkbox" data-owc-mute="' + t[0] + '"' + (muted[t[0]] ? ' checked' : '') + '> Couper ' + esc(t[1]) + '</label>';
      }).join('') + '</div>' +
      '<label class="owc-check"><input type="checkbox" id="owc-silent"' + (pr.silent ? ' checked' : '') + '> Mode discrétion (aucun son)</label>' +
      '<label class="owc-check"><input type="checkbox" id="owc-banners"' + (pr.banners !== false ? ' checked' : '') + '> Bandeaux de notification</label>' +
      '<div class="owc-row"><label>Durée des bandeaux <select id="owc-toast">' +
      [[0, 'Selon la notification'], [3, '3 s'], [5, '5 s'], [8, '8 s'], [12, '12 s'], [20, '20 s']].map(function (o) {
        return '<option value="' + o[0] + '"' + (Number(pr.toast_seconds || 0) === o[0] ? ' selected' : '') + '>' + o[1] + '</option>';
      }).join('') + '</select></label><button type="button" data-owc-saveprefs>Enregistrer</button></div></details>';
    var items = d.items || [];
    out += items.length ? '<ol class="owc-notifs">' + items.map(function (n) {
      return '<li class="owc-n-' + esc(String(n.type).toLowerCase()) + (n.read ? '' : ' is-unread') + '"><span class="owc-n-type">' + esc(n.type) + '</span>' +
        '<span class="owc-log-t">' + esc(n.game_time || fmtTime(n.received_at)) + '</span><span class="owc-n-msg">' + esc(n.message) + '</span></li>';
    }).join('') + '</ol>' : '<p class="owc-hint">Aucune notification reçue. Elles remontent toutes les 10 s quand vous êtes en jeu avec le téléphone COMSPEC ATAK.</p>';
    return out + '</div>';
  }

  function paintBadge() {
    var b = document.querySelector('.owc-dock .owc-badge');
    if (!b || !notifData) return;
    var n = Number(notifData.unread || 0);
    b.hidden = !n;
    b.textContent = n > 99 ? '99+' : String(n);
  }

  /* ---------------- Actions ---------------- */

  function sendUav(cmd, extra) {
    if (!selectedUav) return;
    var body = Object.assign({ vehicle_callsign: selectedUav.vehicle_callsign, cmd: cmd }, extra || {});
    api('/api/atak/web-commands/uav', { method: 'POST', body: body }).then(function (r) {
      toast(r.message || 'Ordre envoyé.');
      refresh();
    }).catch(function (e) { toast(e.message); });
  }

  function sendExplo(action, ids, extra) {
    var confirmNeeded = action === 'fire' || action === 'sequence';
    if (confirmNeeded) {
      var names = charges.filter(function (c) { return ids.indexOf(c.id) !== -1; }).map(function (c) { return (c.magazine_label || 'Charge') + ' (' + (c.author || '?') + ')'; });
      if (!window.confirm('Mise à feu ' + (action === 'sequence' ? 'en séquence ' : '') + 'de :\n' + names.join('\n') + '\n\nConfirmer ?')) return;
    }
    api('/api/atak/web-commands/explo', { method: 'POST', body: Object.assign({ action: action, ids: ids, confirm: confirmNeeded }, extra || {}) })
      .then(function (r) { toast(r.message || 'Ordre transmis.'); if (action === 'sequence') picked = {}; refresh(); })
      .catch(function (e) { toast(e.message); });
  }

  function startPick(cmd) {
    cancelPick();
    pick = { cmd: cmd };
    var map = OW.map;
    if (map && map.getContainer) map.getContainer().classList.add('owc-picking');
    pick.handler = function (e) {
      var w = OW.latLngToWorld(e.latlng);
      var r = Number((root.querySelector('#owc-radius') || {}).value || 150);
      var c = pick.cmd;
      cancelPick();
      if (map && L && L.circle) {
        pickLayer = L.layerGroup().addTo(map);
        L.circleMarker(e.latlng, { radius: 6, color: '#4da3ff', weight: 2, fill: false, interactive: false }).addTo(pickLayer);
        if (c !== 'goto') L.circle(e.latlng, { radius: r, color: '#4da3ff', weight: 1, dashArray: '4 4', fill: false, interactive: false }).addTo(pickLayer);
        setTimeout(function () { if (pickLayer) { map.removeLayer(pickLayer); pickLayer = null; } }, 6000);
      }
      sendUav(c, { x: Math.round(w.x), y: Math.round(w.y), r: r });
    };
    map.once('click', pick.handler);
    render();
  }
  function cancelPick() {
    if (pick && OW && OW.map) {
      OW.map.off('click', pick.handler);
      if (OW.map.getContainer) OW.map.getContainer().classList.remove('owc-picking');
    }
    pick = null;
  }

  function onClick(e) {
    var t = e.target.closest('button, [data-owc-tab]');
    if (!t) return;
    if (t.hasAttribute('data-owc-close')) return close();
    if (t.hasAttribute('data-owc-tab')) return open(t.getAttribute('data-owc-tab'));
    if (t.hasAttribute('data-owc-uav')) {
      var k = t.getAttribute('data-owc-uav');
      selectedUav = uavRows.filter(function (v) { return v.vehicle_callsign === k; })[0] || null;
      return render();
    }
    if (t.hasAttribute('data-owc-cmd')) {
      var cmd = t.getAttribute('data-owc-cmd');
      if (cmd === 'follow_unit') {
        var cs = (root.querySelector('#owc-follow') || {}).value || '';
        if (!cs) return toast('Choisissez l’unité à suivre.');
        return sendUav(cmd, { callsign: cs });
      }
      if (cmd === 'standby') return sendUav(cmd, { kind: t.getAttribute('data-owc-kind') });
      return sendUav(cmd);
    }
    if (t.hasAttribute('data-owc-pick')) return startPick(t.getAttribute('data-owc-pick'));
    if (t.hasAttribute('data-owc-pickcancel')) { cancelPick(); return render(); }
    if (t.hasAttribute('data-owc-alt')) return sendUav('alt', { value: Number(t.getAttribute('data-owc-alt')) });
    if (t.hasAttribute('data-owc-speed')) return sendUav('speed', { value: Number(t.getAttribute('data-owc-speed')) });
    if (t.hasAttribute('data-owc-cancel')) {
      return api('/api/atak/web-commands/' + t.getAttribute('data-owc-cancel') + '/cancel', { method: 'POST', body: {} })
        .then(function (r) { toast(r.message); refresh(); }).catch(function (er) { toast(er.message); });
    }
    if (t.hasAttribute('data-owc-ex')) return sendExplo(t.getAttribute('data-owc-ex'), [Number(t.getAttribute('data-id'))]);
    if (t.hasAttribute('data-owc-seq')) {
      var ids = Object.keys(picked).filter(function (i) { return picked[i]; }).map(Number);
      if (!ids.length) return toast('Cochez au moins une charge.');
      return sendExplo('sequence', ids, {
        delay: Number((root.querySelector('#owc-delay') || {}).value || 5),
        gap: Number((root.querySelector('#owc-gap') || {}).value || 1)
      });
    }
    if (t.hasAttribute('data-owc-readall')) {
      return api('/api/atak/my-phone/notifs/read', { method: 'POST', body: {} }).then(function () { toast('Notifications marquées lues.'); refresh(); })
        .catch(function (er) { toast(er.message); });
    }
    if (t.hasAttribute('data-owc-saveprefs')) {
      var muted = Array.prototype.filter.call(root.querySelectorAll('[data-owc-mute]'), function (i) { return i.checked; })
        .map(function (i) { return i.getAttribute('data-owc-mute'); });
      return api('/api/atak/my-phone/notif-prefs', { method: 'POST', body: {
        muted: muted,
        silent: !!(root.querySelector('#owc-silent') || {}).checked,
        banners: !!(root.querySelector('#owc-banners') || {}).checked,
        toast_seconds: Number((root.querySelector('#owc-toast') || {}).value || 0)
      } }).then(function (r) { toast(r.message); refresh(); }).catch(function (er) { toast(er.message); });
    }
  }
  function onChange(e) {
    var t = e.target;
    if (t.hasAttribute('data-owc-pickc')) {
      picked[t.getAttribute('data-owc-pickc')] = t.checked;
      var b = root.querySelector('[data-owc-seq]');
      if (b) b.textContent = 'Lancer (' + Object.keys(picked).filter(function (k) { return picked[k]; }).length + ')';
    }
  }

  // Compte à rebours des minuteries sans attendre le poll.
  setInterval(function () {
    if (!openNow || view !== 'explo' || !root) return;
    Array.prototype.forEach.call(root.querySelectorAll('[data-owc-timer]'), function (el) {
      var c = charges.filter(function (x) { return String(x.id) === el.getAttribute('data-owc-timer'); })[0];
      if (c) el.textContent = remaining(c);
    });
  }, 1000);

  function boot() {
    OW = window.OverwatchBeta;
    if (!OW || !OW.map) return false;
    build();
    document.addEventListener('ow:uav-select', function (e) {
      if (!e.detail) return;
      selectedUav = e.detail;
      open('uav');
    });
    // Badge non lu : un coup d'œil toutes les 30 s, panneau fermé compris.
    var badge = function () {
      api('/api/atak/my-phone/notifs').then(function (p) { notifData = p; paintBadge(); if (openNow && view === 'notif') render(); }).catch(function () {});
    };
    badge();
    setInterval(function () { if (!openNow || view !== 'notif') badge(); }, 30000);
    return true;
  }
  if (!boot()) {
    var tries = 0;
    var iv = setInterval(function () { if (boot() || ++tries > 40) clearInterval(iv); }, 250);
  }
})();
