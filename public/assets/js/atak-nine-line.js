/*
 * COMSPEC Athena — 9-LINE du JTAC sur un autre écran (page /9-line, QR code de l'app JTAC de l'ATAK natif).
 * Relit toutes les 5 s les derniers 9-LINE transmis par la communauté (GET /9-line/feed), affiche le plus récent
 * en grand et les précédents en liste ; calculateur IP → cible (cap magnétique, retour, distance m / NM).
 */
(function () {
  'use strict';

  var cfg = window.NINE_LINE || {};
  var base = String(cfg.base || '').replace(/\/$/, '');
  var fr = cfg.lang === 'fr';
  var T = fr ? {
    labels: ['IP / BP', 'Cap magnétique', 'Distance', 'Altitude cible', 'Description', 'Position cible', 'Marquage', 'Amis', 'Dégagement'],
    none: 'Aucun 9-LINE transmis pour l\'instant. Dans l\'app JTAC, TRANSMETTRE LE 9-LINE : il apparaît ici en quelques secondes.',
    live: 'à jour', off: 'hors ligne', by: 'par', aircraft: 'appareil', heading: 'Cap', back: 'retour', dist: 'Distance',
    elev: 'Altitude', bad: 'Grilles de 4 à 10 chiffres', login: 'Connectez-vous à Athena pour voir les 9-LINE.'
  } : {
    labels: ['IP / BP', 'Magnetic heading', 'Distance', 'Target elevation', 'Description', 'Target location', 'Mark', 'Friendlies', 'Egress'],
    none: 'No 9-LINE sent yet. In the JTAC app, SEND THE 9-LINE: it shows up here within a few seconds.',
    live: 'up to date', off: 'offline', by: 'by', aircraft: 'aircraft', heading: 'Heading', back: 'back', dist: 'Distance',
    elev: 'Elevation', bad: 'Grids of 4 to 10 digits', login: 'Sign in to Athena to see 9-LINEs.'
  };
  var main = document.getElementById('nl-main');
  var hist = document.getElementById('nl-hist');
  var live = document.getElementById('nl-live');
  var items = [];
  var pinned = null;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function when(it) {
    var d = new Date(String(it.updated_at || it.created_at || '').replace(' ', 'T'));
    return isNaN(d) ? '' : d.toLocaleString(fr ? 'fr-FR' : 'en-GB', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
  }
  function render() {
    if (!items.length) {
      main.innerHTML = '<p class="empty">' + esc(T.none) + '</p>';
      hist.innerHTML = '';
      return;
    }
    var cur = items.filter(function (it) { return it.id === pinned; })[0] || items[0];
    var lines = '';
    for (var i = 1; i <= 9; i++) {
      lines += '<li><i>' + i + '</i><em>' + esc(T.labels[i - 1]) + '</em><b>' + (esc(cur['line' + i]) || '—') + '</b></li>';
    }
    main.innerHTML = '<div class="meta">' + esc((cur.mission_kind || 'cas').toUpperCase()) + ' · ' + esc(T.by) + ' ' + esc(cur.author || 'JTAC')
      + (cur.assigned_aircraft ? ' · ' + esc(T.aircraft) + ' ' + esc(cur.assigned_aircraft) : '')
      + (cur.map_name ? ' · ' + esc(cur.map_name) : '') + ' · ' + esc(when(cur))
      + '<span class="badge ' + esc(String(cur.status || '').toLowerCase()) + '">' + esc(cur.status || '') + '</span></div><ul class="nl">' + lines + '</ul>';
    hist.innerHTML = items.map(function (it) {
      return '<li><button type="button" data-id="' + it.id + '" class="' + (it.id === cur.id ? 'on' : '') + '">'
        + esc(it.line1 || 'IP') + ' → ' + esc(it.line6 || '?') + '<small>' + esc(it.author || 'JTAC') + ' · ' + esc(when(it)) + '</small></button></li>';
    }).join('');
  }
  hist.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-id]');
    if (!b) return;
    var id = Number(b.getAttribute('data-id'));
    pinned = (pinned === id || (items[0] && items[0].id === id)) ? null : id;
    render();
  });
  function poll() {
    fetch(base + '/9-line/feed', { credentials: 'include', headers: { Accept: 'application/json' } })
      .then(function (r) {
        if (r.status === 401 || r.redirected) throw new Error(T.login);
        return r.json();
      })
      .then(function (j) {
        items = (j && j.items) || [];
        items.forEach(function (it) { it.id = Number(it.id); });
        render();
        live.textContent = T.live + ' · ' + new Date().toLocaleTimeString(fr ? 'fr-FR' : 'en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      })
      .catch(function (err) {
        live.textContent = T.off;
        if (err && err.message === T.login) main.innerHTML = '<p class="empty">' + esc(T.login) + '</p>';
      });
  }

  // Calculateur : grille -> mètres (case centrée), même lecture que la recherche de la carte en jeu.
  function grid(txt) {
    var t = String(txt || '').toUpperCase().trim();
    var m = t.match(/[A-Z](?!.*[A-Z])/);
    if (m) t = t.slice(t.lastIndexOf(m[0]) + 1);
    var g = t.match(/[0-9]+/g) || [];
    var s;
    if (g.length === 2) {
      var k = Math.min(g[0].length, g[1].length, 5);
      s = g[0].slice(0, k) + g[1].slice(0, k);
    } else {
      s = g.join('');
    }
    if (s.length < 4 || s.length > 10 || s.length % 2) return null;
    var n = s.length / 2;
    var cell = Math.pow(10, 5 - n);
    return { x: Number(s.slice(0, n)) * cell + cell / 2, y: Number(s.slice(n)) * cell + cell / 2 };
  }
  function pad3(v) { return ('00' + Math.round(v) % 360).slice(-3); }
  var cIp = document.getElementById('c-ip');
  var cTg = document.getElementById('c-tg');
  var cDecl = document.getElementById('c-decl');
  var cAlt = document.getElementById('c-alt');
  var cOut = document.getElementById('c-out');
  function calc() {
    var a = grid(cIp.value);
    var b = grid(cTg.value);
    if (!a || !b) { cOut.innerHTML = (cIp.value || cTg.value) ? '<span>' + esc(T.bad) + '</span>' : ''; return; }
    var dx = b.x - a.x;
    var dy = b.y - a.y;
    var tru = (Math.atan2(dx, dy) * 180 / Math.PI + 360) % 360;
    var decl = parseFloat(String(cDecl.value).replace(',', '.')) || 0;
    var mag = ((tru - decl) % 360 + 360) % 360;
    var d = Math.sqrt(dx * dx + dy * dy);
    var alt = parseFloat(String(cAlt.value).replace(',', '.'));
    cOut.innerHTML = '<span>2 · ' + esc(T.heading) + '</span> ' + pad3(mag) + '° M <span>(' + pad3(tru) + '° · ' + esc(T.back) + ' ' + pad3(mag + 180) + '° M)</span><br>'
      + '<span>3 · ' + esc(T.dist) + '</span> ' + (d / 1852).toFixed(1) + ' NM <span>(' + Math.round(d) + ' m)</span>'
      + (isNaN(alt) ? '' : '<br><span>4 · ' + esc(T.elev) + '</span> ' + Math.round(alt * 3.28084) + ' ft MSL');
  }
  [cIp, cTg, cDecl, cAlt].forEach(function (el) { el.addEventListener('input', calc); });

  poll();
  setInterval(poll, 5000);
})();
