/*
 * COMSPEC Athena — télécommande drone sur le vrai téléphone (page /telecommande-drone).
 * Liste les drones appairés à un téléphone ATAK (les siens d'abord), affiche l'état du drone choisi
 * et envoie les mêmes ordres que le poste (POST /api/atak/web-commands/uav) : le téléphone du pilote
 * en jeu les exécute s'il est en liaison. Rôles et essaim : flotte de l'ATAK natif (1.49.0 et plus).
 */
(function () {
  'use strict';

  var cfg = window.DRONE_REMOTE || {};
  var base = String(cfg.base || '').replace(/\/$/, '');
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
  var app = document.getElementById('dr-app');
  var live = document.getElementById('dr-live');
  var toastEl = document.getElementById('dr-toast');
  var drones = [];
  var selected = '';
  var log = [];
  var busy = false;
  var toastTimer = null;

  var LABELS = {
    takeoff: 'Décoller', hover: 'Stationnaire', follow_me: 'Me suivre', home: 'Retour au pilote', rth: 'RTB', land: 'Atterrir',
    hunt: 'Recherche', alt: 'Altitude', speed: 'Vitesse', standby: 'Veille', resume: 'Reprendre', force: 'Forcer', photo: 'Photo',
    role: 'Rôle', swarm: 'Essaim', goto: 'Aller', loiter: 'Orbite', observe: 'Observer', follow_unit: 'Suivre une unité'
  };
  var ROLES = [['ME', 'Me suivre'], ['CQB', 'CQB'], ['GUARD', 'Garde'], ['HUNT', 'Recherche'], ['NONE', 'Aucun']];
  var SWARM = [['ME', 'Tous me suivre'], ['CQB', 'Tous en CQB'], ['GUARD', 'Tous en garde'], ['HUNT', 'Tous en recherche'],
    ['HOVER', 'Tous stationnaire'], ['RTH', 'Tous RTB'], ['LAND', 'Tous atterrir']];
  var STATUS = { pending: 'en file', accepted: 'reçu', done: 'exécuté', failed: 'échec', expired: 'sans réponse', cancelled: 'annulé' };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function toast(t) {
    toastEl.textContent = t;
    toastEl.classList.add('show');
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('show'); }, 3200);
  }
  function api(path, body) {
    var init = { credentials: 'include', method: body ? 'POST' : 'GET', headers: { 'Accept': 'application/json' } };
    if (body) {
      init.headers['Content-Type'] = 'application/json';
      init.headers['X-CSRF-TOKEN'] = csrf;
      init.body = JSON.stringify(Object.assign({ _csrf_token: csrf, mapId: cfg.mapId }, body));
    }
    return fetch(base + path, init).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok) throw new Error((j && j.message) || ('Erreur ' + r.status));
        return j;
      });
    });
  }
  function props(v) {
    var p = v && v.properties;
    if (typeof p === 'string') { try { p = JSON.parse(p); } catch (e) { p = {}; } }
    return p || {};
  }
  function mine(v) { return !!cfg.steamUid && String(props(v).pilot_uid || '') === String(cfg.steamUid); }
  function current() { return drones.filter(function (v) { return v.vehicle_callsign === selected; })[0] || null; }

  function refresh() {
    var q = 'mapId=' + encodeURIComponent(cfg.mapId);
    Promise.all([
      api('/api/atak/vehicles?' + q + '&vehicle_class=UAV'),
      api('/api/atak/web-commands?' + q + '&kind=uav')
    ]).then(function (res) {
      drones = (res[0].vehicles || []).filter(function (v) {
        var p = props(v);
        var st = String(v.status || '').toUpperCase();
        return p.kind === 'uav' && p.pilot_uid && p.net_id && st !== 'DESTROYED' && st !== 'ABANDONED';
      });
      drones.sort(function (a, b) { return (mine(b) ? 1 : 0) - (mine(a) ? 1 : 0); });
      if (!current()) selected = drones.length ? drones[0].vehicle_callsign : '';
      log = res[1].commands || [];
      live.textContent = 'en ligne · ' + new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      render();
    }).catch(function (e) {
      live.textContent = 'hors ligne';
      if (!drones.length) app.innerHTML = '<p class="empty">' + esc(e.message) + '</p>';
    });
  }

  function btn(cmd, label, cls, extra) {
    return '<button type="button" class="b ' + (cls || '') + '" data-cmd="' + cmd + '"' + (extra || '') + (busy ? ' disabled' : '') + '>' + esc(label) + '</button>';
  }

  function render() {
    if (!drones.length) {
      app.innerHTML = '<p class="empty">Aucun drone appairé à un téléphone ATAK sur la carte.<br>En jeu : posez le drone à vos pieds et appairez-le dans l’app Drone.</p>';
      return;
    }
    var v = current();
    var p = props(v);
    var bat = Number(p.battery);
    var lvl = bat <= 15 ? 'low' : (bat <= 40 ? 'mid' : '');
    var out = '<div class="chips">' + drones.map(function (d) {
      var dp = props(d);
      return '<button type="button" class="chip' + (d.vehicle_callsign === selected ? ' on' : '') + '" data-pick="' + esc(d.vehicle_callsign) + '">' +
        esc(dp.name || d.vehicle_name || 'Drone') + '<small>' + esc(mine(d) ? 'à moi' : (dp.pilot || '')) + '</small></button>';
    }).join('') + '</div>';
    out += '<div class="card" style="margin-top:10px"><div class="name">' + esc(p.name || v.vehicle_name || 'Drone') + '</div>' +
      '<div class="meta">' + esc(String(p.mode || '—').toLowerCase()) + ' · ' + esc(p.alt_agl != null ? p.alt_agl + ' m sol' : '') +
      (p.speed_kmh != null ? ' · ' + esc(p.speed_kmh) + ' km/h' : '') + ' · pilote ' + esc(p.pilot || '—') + '</div>' +
      (Number.isFinite(bat) ? '<div class="gauge ' + lvl + '"><i style="width:' + Math.max(0, Math.min(100, bat)) + '%"></i></div><div class="meta">Batterie ' + Math.round(bat) + ' %</div>' : '') +
      '</div>';
    out += '<h2>Vol</h2><div class="grid">' +
      btn('takeoff', 'Décoller', 'main') + btn('hover', 'Stationnaire') + btn('follow_me', 'Me suivre', 'main') +
      btn('home', 'Retour au pilote') + btn('rth', 'RTB') + btn('land', 'Atterrir', 'red') + '</div>';
    out += '<h2>Rôle du drone</h2><div class="grid">' + ROLES.map(function (r) {
      return btn('role', r[1], '', ' data-kind="' + r[0] + '"');
    }).join('') + btn('hunt', 'Recherche ici') + '</div>' +
      '<p class="hint">Rôles : ATAK natif 1.49.0 et plus. La garde rapprochée doit être autorisée par l’admin (repérage automatique par drone).</p>';
    out += '<h2>Altitude</h2><div class="presets">' + [5, 15, 30, 60, 100].map(function (a) {
      return btn('alt', a + ' m', '', ' data-v="' + a + '"');
    }).join('') + '</div>';
    out += '<h2>Vitesse</h2><div class="presets">' + [10, 20, 40, 60, 90].map(function (s) {
      return btn('speed', s + ' km/h', '', ' data-v="' + s + '"');
    }).join('') + '</div>';
    out += '<h2>Essaim (tous mes drones)</h2><div class="grid two">' + SWARM.map(function (s) {
      return btn('swarm', s[1], s[0] === 'LAND' ? 'red' : '', ' data-kind="' + s[0] + '"');
    }).join('') + '</div>';
    out += '<h2>Divers</h2><div class="grid">' + btn('photo', 'Photo', 'main') + btn('standby', 'Veille au sol', '', ' data-kind="LAND"') +
      btn('resume', 'Reprendre') + btn('force', 'Forcer l’exécution') + '</div>';
    var mineLog = log.filter(function (c) { return c.target_ref === String(p.net_id || '') || c.cmd === 'swarm'; }).slice(0, 12);
    out += '<h2>Journal</h2>' + (mineLog.length ? '<ul class="log">' + mineLog.map(function (c) {
      var t = /(\d{2}:\d{2}:\d{2})/.exec(String(c.created_at || ''));
      return '<li><em>' + esc(t ? t[1] : '') + '</em><span>' + esc(LABELS[c.cmd] || c.cmd) + ' · <span class="' + esc(c.status) + '">' +
        esc(STATUS[c.status] || c.status) + '</span>' + (c.result_text ? ' · ' + esc(c.result_text) : '') + '</span></li>';
    }).join('') + '</ul>' : '<p class="hint">Aucun ordre envoyé à ce drone.</p>');
    out += '<p class="hint">Les ordres partent par Athena : le téléphone du pilote les prend en quelques secondes, s’il est en jeu et en liaison avec le drone. Pas de pilotage au joystick ni de vidéo en direct.</p>';
    app.innerHTML = out;
  }

  function send(cmd, extra) {
    var v = current();
    if (!v || busy) return;
    var body = Object.assign({ vehicle_callsign: v.vehicle_callsign, cmd: cmd }, extra || {});
    busy = true;
    render();
    if (navigator.vibrate) navigator.vibrate(15);
    api('/api/atak/web-commands/uav', body).then(function (r) {
      toast(r.message || 'Ordre envoyé.');
    }).catch(function (e) { toast(e.message); }).then(function () {
      busy = false;
      refresh();
    });
  }

  app.addEventListener('click', function (e) {
    var t = e.target.closest('button');
    if (!t) return;
    if (t.hasAttribute('data-pick')) { selected = t.getAttribute('data-pick'); render(); return; }
    var cmd = t.getAttribute('data-cmd');
    if (!cmd) return;
    var extra = {};
    if (t.hasAttribute('data-kind')) extra.kind = t.getAttribute('data-kind');
    if (t.hasAttribute('data-v')) extra.value = Number(t.getAttribute('data-v'));
    if (cmd === 'hunt') {
      var v = current();
      extra.x = Math.round(Number(v.pos_x) || 0);
      extra.y = Math.round(Number(v.pos_y) || 0);
      extra.r = 250;
    }
    if (cmd === 'land' && !window.confirm('Faire atterrir le drone sur place ?')) return;
    if (cmd === 'swarm' && extra.kind === 'LAND' && !window.confirm('Faire atterrir tous les drones ?')) return;
    send(cmd, extra);
  });

  refresh();
  setInterval(function () { if (!document.hidden && !busy) refresh(); }, 4000);
})();
