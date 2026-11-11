/* Poste TOC web (/atak/toc) : même console que le PC du poste en jeu. Barre du haut (poste, ZULU, LOCAL, liaison,
 * FLASH), deux rangées d'onglets, carte commune à gauche (calques du jeu), panneau de l'onglet à droite, ligne d'état.
 * Données : API ATAK existantes (positions, marqueurs, formes, comptes rendus…) et salle TOC partagée avec le jeu
 * (/atak/toc/api/state, sondée toutes les 3 s avec ETag). Textes en français (traduits par le dictionnaire du site). */
(function () {
  'use strict';
  var BOOT = window.TOC_BOOT || {};
  var G = window.TocGeo;
  var esc = window.TocMap.esc;
  var $ = function (sel, root) { return (root || document).querySelector(sel); };

  var TABS = [
    ['groupes', 'Groupes'], ['orbat', 'ORBAT'], ['flux', 'Flux'], ['ordres', 'Ordres'], ['camp', 'Camp'], ['reseau', 'Réseau'], ['radio', 'Radio'], ['plans', 'Plans'],
    ['sat', 'SAT'], ['salle', 'Salle'], ['chronos', 'Chronos'], ['main', 'Main courante'], ['excheck', 'EXCHECK'], ['pertes', 'Pertes'], ['demandes', 'Demandes'], ['calques', 'Calques']
  ];
  var SIDES = { WEST: 'BLUFOR', EAST: 'OPFOR', GUER: 'INDFOR', CIV: 'CIVILS' };
  var store = (function () {
    try { return window.localStorage; } catch (e) { return null; }
  })();
  function pref(k, d) { try { var v = store && store.getItem('athena:toc:' + k); return v != null ? v : d; } catch (e) { return d; } }
  function setPref(k, v) { try { if (store) store.setItem('athena:toc:' + k, String(v)); } catch (e) {} }

  var S = {
    mapId: Number(pref('map', BOOT.defaultMap)) || BOOT.defaultMap,
    side: pref('side', 'WEST'),
    tab: pref('tab', 'groupes'),
    room: null, game: null, serverOffset: 0, roomVersion: '',
    sel: {}, plans: [], plansLoaded: false, tm: null, layers: null, isr: false, statusTimer: null
  };
  if (!SIDES[S.side]) S.side = 'WEST';
  (function () {
    // Lien direct vers un onglet : /atak/toc?onglet=demandes
    var m = /[?&]onglet=([a-z]+)/.exec(window.location.search);
    if (m && TABS.some(function (t) { return t[0] === m[1]; })) S.tab = m[1];
  })();

  function terrain() {
    var list = BOOT.terrains || [];
    for (var i = 0; i < list.length; i++) if (list[i].id === S.mapId) return list[i];
    return list[0] || null;
  }

  /* ---------- Réseau ---------- */
  function api(path, opts) {
    opts = opts || {};
    var init = { method: opts.method || 'GET', credentials: 'same-origin', cache: 'no-cache', headers: { 'Accept': 'application/json' } };
    if (opts.body !== undefined) {
      init.headers['Content-Type'] = 'application/json';
      init.headers['X-CSRF-Token'] = BOOT.csrf || '';
      init.body = JSON.stringify(opts.body);
    }
    return fetch(BOOT.base + path, init).then(function (r) {
      if (r.status === 304) return { notModified: true };
      return r.json().catch(function () { return { ok: false, error: 'http_' + r.status }; }).then(function (j) {
        if (!r.ok && j && j.ok !== false) j.ok = false;
        if (j) j._status = r.status;
        return j;
      });
    });
  }
  var ERRORS = {
    csrf: 'Session expirée : rechargez la page.', rate_limited: 'Trop d’actions d’affilée : attendez quelques secondes.',
    forbidden: 'Ce poste demande le droit « tenir le poste TOC web ».', name_required: 'Donnez un nom.', text_required: 'Écrivez le texte.',
    value_invalid: 'Valeur hors limites.', time_invalid: 'Heure au format HH:MM.', schema_not_ready: 'Le poste n’est pas encore installé sur le site (migration 4370 à lancer).',
    request_invalid: 'Demande inconnue.', kill_invalid: 'Élément et nombre requis.'
  };
  function say(msg, isError) {
    var el = $('#toc-status-msg');
    if (!el) return;
    el.textContent = msg || '';
    el.classList.toggle('is-error', !!isError);
    clearTimeout(S.statusTimer);
    S.statusTimer = setTimeout(function () { el.textContent = ''; }, 6000);
  }

  /* ---------- Horloges ---------- */
  function two(n) { return (n < 10 ? '0' : '') + n; }
  function hms(d) { return two(d.getUTCHours()) + ':' + two(d.getUTCMinutes()) + ':' + two(d.getUTCSeconds()); }
  function serverNow() { return Date.now() / 1000 + S.serverOffset; }
  function tickClocks() {
    var now = new Date(serverNow() * 1000);
    $('#toc-zulu').textContent = hms(now);
    var local = $('#toc-local');
    var clock = S.room && S.room.clock;
    if (clock && S.game && S.game.live) {
      var p = clock.local.split(':');
      var secs = (+p[0]) * 3600 + (+p[1]) * 60 + (+p[2]) + Math.max(0, Math.round(serverNow() - clock.at));
      secs = ((secs % 86400) + 86400) % 86400;
      local.textContent = two(Math.floor(secs / 3600)) + ':' + two(Math.floor(secs % 3600 / 60)) + ':' + two(secs % 60);
      local.parentNode.title = 'Heure de la mission en jeu';
    } else {
      var d = new Date();
      local.textContent = two(d.getHours()) + ':' + two(d.getMinutes()) + ':' + two(d.getSeconds());
      local.parentNode.title = 'Heure de ce poste (pas de jeu en liaison)';
    }
    if (S.tab === 'chronos') updateTimerValues();
  }

  /* ---------- Salle (état partagé) ---------- */
  function loadRoom() {
    return api('/atak/toc/api/state?mapId=' + S.mapId + '&side=' + S.side).then(function (j) {
      if (!j || j.notModified) return;
      if (!j.ok) {
        if (j.error) setBanner(ERRORS[j.error] || 'Salle TOC indisponible.');
        return;
      }
      setBanner('');
      S.serverOffset = (j.now || Date.now() / 1000) - Date.now() / 1000;
      var sig = JSON.stringify([j.state, j.game]);
      S.game = j.game;
      if (sig === S.roomVersion) return;
      S.roomVersion = sig;
      S.room = j.state;
      renderTop();
      renderFlash();
      renderTabs();
      if (['chronos', 'main', 'excheck', 'pertes', 'demandes', 'salle', 'sat'].indexOf(S.tab) >= 0) renderPanel();
      if (S.layers) S.layers.setRequests(S.room.requests || []);
      if (S.isr) renderIsr();
    }).catch(function () { setLink('off'); });
  }
  function act(name, args) {
    return api('/atak/toc/api/action', { method: 'POST', body: { mapId: S.mapId, side: S.side, act: name, args: args || {} } }).then(function (j) {
      if (!j || !j.ok) { say(ERRORS[j && j.error] || 'Action refusée.', true); return false; }
      S.room = j.state; S.game = j.game; S.roomVersion = JSON.stringify([j.state, j.game]);
      renderTop(); renderFlash(); renderTabs(); renderPanel();
      say(S.game && S.game.live ? 'Envoyé au jeu : en attente de la prise en compte du serveur.' : 'Inscrit sur le poste web ; partira en jeu à la prochaine liaison.');
      return true;
    }).catch(function () { say('Réseau indisponible : action non envoyée.', true); return false; });
  }

  function setBanner(msg) {
    var b = $('#toc-banner');
    b.textContent = msg || '';
    b.hidden = !msg;
  }
  function setLink(state) {
    var dot = $('#toc-link-dot'), txt = $('#toc-link-txt');
    dot.className = 'toc-dot ' + (state === 'live' ? 'is-live' : state === 'old' ? 'is-old' : 'is-off');
    txt.textContent = state === 'live' ? 'Jeu en liaison' : state === 'old' ? 'Dernière liaison il y a ' + Math.round((serverNow() - S.game.seen_at) / 60) + ' min' : 'Pas de jeu en liaison';
  }

  /* ---------- Barre du haut, FLASH ---------- */
  function renderTop() {
    var t = terrain();
    $('#toc-title').textContent = 'Poste TOC · ' + (t ? t.label : '');
    var post = S.room && S.room.posts && S.room.posts[0];
    $('#toc-sub').textContent = SIDES[S.side] + ' · ' + (post ? 'poste pris en jeu par ' + post.op + ' à ' + post.t + 'Z · ' : '') + 'opérateur web ' + (BOOT.operator || '');
    if (S.game) setLink(S.game.live ? 'live' : (S.game.seen_at ? 'old' : 'off'));
  }
  function renderFlash() {
    var f = S.room && S.room.flash;
    var band = $('#toc-flash');
    band.hidden = !f;
    $('#toc-root').classList.toggle('is-flash', !!f);
    if (f) $('#toc-flash-text').textContent = 'FLASH · ' + f.text + ' · ' + f.author + ' · ' + f.t + 'Z' + (f.pending ? ' · en attente du jeu' : '');
  }
  function sendFlash() {
    var inp = $('#toc-flash-input');
    var text = inp.value.trim();
    if (!text) { inp.focus(); say('Écrivez le texte du FLASH.', true); return; }
    act('flash', { text: text }).then(function (ok) { if (ok) inp.value = ''; });
  }

  /* ---------- Onglets ---------- */
  function badgeFor(id) {
    var r = S.room || {};
    if (id === 'demandes') return (r.requests || []).filter(function (q) { return q.status === 'RECUE'; }).length;
    return 0;
  }
  function renderTabs() {
    var nav = $('#toc-tabs');
    nav.innerHTML = TABS.map(function (t, i) {
      var b = badgeFor(t[0]);
      return '<button type="button" role="tab" class="toc-tab" id="tab-' + t[0] + '" aria-controls="toc-panel" aria-selected="' + (S.tab === t[0]) + '" tabindex="' + (S.tab === t[0] ? 0 : -1) + '" data-tab="' + t[0] + '" title="Raccourci ' + (i < 9 ? 'Alt+' + (i + 1) : '') + '">' + esc(t[1]) + (b ? '<span class="toc-badge">' + b + '</span>' : '') + '</button>';
    }).join('');
  }
  function setTab(id) {
    S.tab = id;
    setPref('tab', id);
    S.isr = id === 'sat';
    renderTabs();
    renderPanel();
    renderIsr();
    if (id === 'calques' && S.layers) S.layers.openPanel(true);
  }

  /* ---------- Panneau de droite ---------- */
  function sec(title, sub) { return '<div class="toc-sec"><h2>' + esc(title) + '</h2><span>' + esc(sub || '') + '</span></div>'; }
  function empty(sentence, actionHtml) { return '<div class="toc-empty"><p>' + esc(sentence) + '</p>' + (actionHtml || '') + '</div>'; }
  function pill(text, tone) { return '<span class="toc-pill ' + (tone ? 'is-' + tone : '') + '">' + esc(text) + '</span>'; }
  function pendingMark(row) { return row && row.pending ? ' <span class="toc-pending" title="Pas encore repris par le serveur du jeu">◷</span>' : ''; }

  function units() { return S.layers ? S.layers.units() : []; }
  function groups() {
    var by = {};
    units().forEach(function (u) {
      if (u.side && u.side !== S.side) return;
      var g = u.group || u.callsign;
      if (!by[g]) by[g] = { name: g, members: [], live: 0, old: 0, lost: 0, players: 0, pos: null };
      var e = by[g];
      e.members.push(u);
      e[u.fresh] += 1;
      if (!u.ai) e.players += 1;
      if (!e.pos && u.pos) e.pos = u.pos;
    });
    return Object.keys(by).sort().map(function (k) {
      var e = by[k];
      e.state = e.live ? 'live' : (e.old ? 'old' : 'lost');
      return e;
    });
  }

  function panelGroupes() {
    var gs = groups();
    var live = gs.reduce(function (a, g) { return a + g.live; }, 0);
    var h = sec('Groupes', gs.length + ' groupe(s) · ' + live + ' en ligne');
    if (!gs.length) return h + empty('Aucune position reçue sur ce terrain pour ce camp.', '<p class="toc-note">Les positions arrivent quand les joueurs ont un téléphone ATAK relié à Athena.</p>');
    h += '<ul class="toc-list" role="listbox" aria-label="Groupes">' + gs.map(function (g) {
      return '<li role="option" data-sel="grp" data-id="' + esc(g.name) + '" class="' + (S.sel.grp === g.name ? 'is-sel' : '') + '"><span class="is-' + (g.state === 'lost' ? 'lost' : g.state) + '">' + esc(g.name) + ' · ' + g.players + ' joueur(s) · ' + g.members.length + ' nœud(s) ATAK</span><span class="mono">' + (g.pos ? G.grid(g.pos[0], g.pos[1], 6) : '') + '</span></li>';
    }).join('') + '</ul>';
    var sel = gs.filter(function (g) { return g.name === S.sel.grp; })[0];
    if (sel) {
      var lead = sel.members[0];
      h += '<div class="toc-card"><h3>' + esc(sel.name) + '</h3><p>Chef : ' + esc(lead.callsign) + '</p><p>' + sel.members.length + ' nœud(s) ATAK dont ' + sel.players + ' joueur(s) · ' + sel.live + ' en direct</p>' +
        (sel.pos ? '<p class="mono">Grille ' + G.grid(sel.pos[0], sel.pos[1], 8) + '</p>' : '') +
        '<div class="toc-buttons" style="--cols:2"><button type="button" class="toc-btn is-primary" data-do="center-grp">Centrer</button><a class="toc-btn" href="' + esc(BOOT.base + '/atak') + '" title="Messages et ordres : ATAK web">Message au chef</a></div></div>';
    } else {
      h += '<p class="toc-note">Choisissez un groupe : la carte se centre sur lui.</p>';
    }
    return h;
  }

  function panelOrbat() {
    var gs = groups();
    var n = gs.reduce(function (a, g) { return a + g.members.length; }, 0);
    var h = sec('ORBAT du site', n + ' membre(s) · ' + gs.length + ' groupe(s)');
    if (!gs.length) return h + empty('L’ORBAT se remplit avec les positions reçues des joueurs.');
    gs.forEach(function (g) {
      h += '<table class="toc-table"><thead><tr><th>' + esc(g.name) + '</th><th class="num" style="width:34%">État</th></tr></thead><tbody>' + g.members.map(function (u) {
        var st = u.fresh === 'live' ? pill('en jeu', 'accent') : u.fresh === 'old' ? pill('ancien', 'amber') : pill('hors ligne');
        return '<tr data-sel="unit" data-id="' + esc(u.id) + '"><td>' + esc(u.callsign) + (u.ai ? ' <span class="toc-muted">IA</span>' : '') + '</td><td class="num">' + st + '</td></tr>';
      }).join('') + '</tbody></table>';
    });
    return h + '<p class="toc-note">ORBAT prévu et comparé : <a href="' + esc(BOOT.base + '/atak') + '">ATAK web › ORBAT</a>.</p>';
  }

  function panelFlux() {
    var items = S.layers ? S.layers.feed() : [];
    var h = sec('Flux du camp', items.length + ' compte(s) rendu(s) · heures ZULU');
    if (!items.length) return h + empty('Aucun compte rendu reçu sur ce terrain.', '<p class="toc-note">SALUTE, MEDEVAC, TIC et SITREP envoyés des téléphones arrivent ici.</p>');
    return h + '<table class="toc-table"><thead><tr><th style="width:62px">Heure</th><th style="width:96px">Type</th><th>Émetteur</th><th class="num" style="width:96px">Grille</th></tr></thead><tbody>' +
      items.slice(0, 60).map(function (f) {
        var tone = /TIC|CONTACT|FLASH/.test(f.kind) ? 'red' : (/MEDEVAC|URG/.test(f.kind) ? 'amber' : '');
        return '<tr data-sel="feed" data-id="' + esc(f.id) + '" title="' + esc(f.text) + '"><td class="mono">' + esc(f.t) + '</td><td>' + (tone ? pill(f.kind, tone) : '<b>' + esc(f.kind) + '</b>') + '</td><td>' + esc(f.who) + '</td><td class="num">' + esc(f.pos ? G.grid(f.pos[0], f.pos[1], 6) : '') + '</td></tr>';
      }).join('') + '</tbody></table>';
  }

  function panelOrdres() {
    var items = S.layers ? S.layers.orders() : [];
    var h = sec('Ordres', items.length + ' ordre(s)');
    if (!items.length) return h + empty('Aucun ordre en cours sur ce terrain.', '<a class="toc-btn" href="' + esc(BOOT.base + '/atak') + '">Donner un ordre (ATAK web)</a>');
    return h + '<table class="toc-table"><thead><tr><th>Ordre</th><th style="width:30%">Pour</th><th class="num" style="width:26%">Statut</th></tr></thead><tbody>' + items.map(function (o) {
      return '<tr data-sel="order" data-id="' + esc(o.id) + '"><td>' + esc(o.label) + '</td><td>' + esc(o.target) + '</td><td class="num">' + pill(o.status, o.status === 'done' || o.status === 'completed' ? 'accent' : '') + '</td></tr>';
    }).join('') + '</tbody></table><p class="toc-note">Les ordres se donnent dans ATAK web ; ils arrivent sur les téléphones et sur le PC du poste.</p>';
  }

  function panelCamp() {
    var cp = S.layers ? S.layers.campPlan(S.side) : null;
    var h = sec('Camp', cp ? (cp.items.length + ' élément(s) · ' + (cp.updated_at || '')) : 'plan du camp');
    if (!cp || !cp.items.length) return h + empty('Le plan du camp est vide : il se dessine dans l’app Plan du téléphone ou sur le PC du poste (outil PLAN).', '<a class="toc-btn" href="' + esc(BOOT.base + '/atak/toc/plans') + '">Préparer un plan web</a>');
    var counts = {};
    cp.items.forEach(function (it) { counts[it.kind] = (counts[it.kind] || 0) + 1; });
    return h + '<table class="toc-table"><thead><tr><th>Type</th><th class="num">Nombre</th></tr></thead><tbody>' + Object.keys(counts).sort().map(function (k) {
      return '<tr><td>' + esc(k) + '</td><td class="num">' + counts[k] + '</td></tr>';
    }).join('') + '</tbody></table><p class="toc-note">Calque « Plan du camp » sur la carte. Dans un plan web : « Importer depuis le jeu ».</p>';
  }

  function panelReseau() {
    var all = units().filter(function (u) { return !u.side || u.side === S.side; });
    var c = { live: 0, old: 0, lost: 0, off: 0 };
    all.forEach(function (u) { c[u.fresh === 'live' ? 'live' : u.fresh === 'old' ? 'old' : (u.status === 'offline' ? 'off' : 'lost')] += 1; });
    var relays = S.layers ? S.layers.count('relays') : 0;
    var h = sec('Réseau', all.filter(function (u) { return !u.ai; }).length + ' joueur(s) · ' + all.filter(function (u) { return u.ai; }).length + ' IA · ' + relays + ' relais');
    h += '<div class="toc-kpis"><div class="toc-kpi is-accent"><b>' + c.live + '</b><span>en ligne</span></div><div class="toc-kpi is-amber"><b>' + c.old + '</b><span>anciens</span></div><div class="toc-kpi is-red"><b>' + c.lost + '</b><span>perdus</span></div><div class="toc-kpi"><b>' + c.off + '</b><span>hors ligne</span></div></div>';
    var gs = groups();
    if (!gs.length) return h + empty('Aucun nœud ATAK reçu.');
    h += '<table class="toc-table"><thead><tr><th>Élément</th><th class="num" style="width:64px">Nœuds</th><th style="width:90px">État</th><th class="num" style="width:96px">Grille</th></tr></thead><tbody>' + gs.map(function (g) {
      var st = g.state === 'live' ? pill('en direct', 'accent') : g.state === 'old' ? pill('ancien', 'amber') : pill('perdu', 'red');
      return '<tr data-sel="grp" data-id="' + esc(g.name) + '"><td><b>' + esc(g.name) + '</b></td><td class="num">' + g.members.length + '</td><td>' + st + '</td><td class="num">' + (g.pos ? G.grid(g.pos[0], g.pos[1], 6) : '') + '</td></tr>';
    }).join('') + '</tbody></table>';
    return h + '<h3 class="toc-sec"><span>Actions du poste web</span></h3><div id="toc-journal" class="toc-note">…</div>';
  }

  function panelRadio() {
    return sec('Radio', 'en jeu seulement') + empty('La radio se lit sur le PC du poste en jeu (ACRE2 ou TFAR, poste local) : rien n’en remonte au site.', '<p class="toc-note">Les messages écrits passent par ATAK web › Transmissions.</p>');
  }

  function panelPlans() {
    var h = sec('Plans', S.plansLoaded ? S.plans.length + ' plan(s)' : 'chargement…');
    var cur = S.layers ? S.layers.shownPlan() : null;
    if (S.plansLoaded && !S.plans.length) return h + empty('Aucun plan d’opération pour l’instant.', '<a class="toc-btn is-primary" href="' + esc(BOOT.base + '/atak/toc/plans') + '">Créer un plan</a>');
    h += '<ul class="toc-list">' + S.plans.map(function (p) {
      return '<li data-sel="plan" data-id="' + p.id + '" class="' + (cur === p.id ? 'is-sel' : '') + '"><span>' + esc(p.title) + ' <span class="toc-muted">· ' + esc(p.world || '') + ' · ' + p.elements + ' élément(s)</span></span><span class="toc-muted">' + (p.published_at ? 'en jeu' : '') + '</span></li>';
    }).join('') + '</ul>';
    var sel = S.plans.filter(function (p) { return p.id === S.sel.plan; })[0];
    if (sel) {
      h += '<div class="toc-card"><h3>' + esc(sel.title) + '</h3><p>' + sel.elements + ' élément(s) · révision ' + sel.rev + (sel.published_at ? ' · envoyé en jeu (' + esc(sel.published_side) + ') le ' + esc(sel.published_at) : '') + '</p>' +
        '<div class="toc-buttons" style="--cols:3"><button type="button" class="toc-btn is-primary" data-do="plan-show">' + (cur === sel.id ? 'Masquer' : 'Afficher') + '</button><a class="toc-btn" href="' + esc(BOOT.base + '/atak/toc/plans/' + sel.id) + '">Éditer</a><button type="button" class="toc-btn" data-do="plan-publish">Envoyer en jeu</button></div></div>';
    }
    return h + '<a class="toc-btn is-block" href="' + esc(BOOT.base + '/atak/toc/plans') + '">Tous les plans</a>';
  }

  function panelSat() {
    var sats = (S.room && S.room.sats) || [];
    var h = sec('SAT', 'vue carte ISR');
    h += '<p class="toc-banner">Vue carte : c’est le terrain du site avec la position et l’emprise des satellites du poste, pas l’image de la caméra du jeu (elle ne sort pas du jeu).</p>';
    if (!sats.length || !sats.some(function (s) { return s.x >= 0; })) return h + empty('Aucun satellite pris en main en jeu pour ce camp.', '<p class="toc-note">En jeu : PC du poste › SAT › Prendre la main.</p>');
    sats.forEach(function (s) {
      h += '<div class="toc-card" data-sel="sat" data-id="' + s.n + '"><h3>SAT ' + s.n + '</h3><p>' + (s.op ? 'Opérateur : ' + esc(s.op) : 'Libre') + ' · capteur ' + esc(s.sensor || 'AUTO') + '</p>' +
        (s.x >= 0 ? '<p class="mono">Centre ' + G.grid(s.x, s.y, 8) + ' · emprise ' + Math.round(s.hw * 2) + ' m</p>' : '<p>Pas encore pointé.</p>') +
        (s.x >= 0 ? '<button type="button" class="toc-btn" data-do="sat-center" data-n="' + s.n + '">Centrer la vue</button>' : '') + '</div>';
    });
    return h;
  }

  function panelSalle() {
    var scr = (S.room && S.room.screens) || [];
    var h = sec('Salle', scr.length + ' écran(s)');
    if (!scr.length) return h + empty('Le jeu n’a pas encore envoyé la liste des écrans de la salle.', '<p class="toc-note">Elle arrive avec la salle TOC dès qu’un poste du camp est en liaison.</p>');
    h += '<table class="toc-table"><thead><tr><th>Écran</th><th style="width:36%">Affichage</th></tr></thead><tbody>' + scr.map(function (s) {
      return '<tr><td>' + esc(s.name) + (s.hall ? ' <span class="toc-muted">· ' + esc(s.hall) + '</span>' : '') + '</td><td>' + pill(s.display) + '</td></tr>';
    }).join('') + '</tbody></table>';
    return h + '<p class="toc-note">Changer l’affichage d’un écran : disponible en jeu (menu de l’écran ou PC du poste › SALLE).</p>';
  }

  function timerClass(t) { return t.state === 'OVER' ? 'is-over' : (t.kind === 'AT' ? 'is-at' : (t.running ? 'is-run' : 'is-stop')); }
  function timerValue(t) {
    var now = Math.floor(serverNow());
    var e = (t.acc || 0) + (t.running ? Math.max(0, now - (t.base_at || now)) : 0);
    var f = function (v) { v = Math.abs(Math.round(v)); return two(Math.floor(v / 3600)) + ':' + two(Math.floor(v % 3600 / 60)) + ':' + two(v % 60); };
    if (t.kind === 'DOWN') { var r = t.value - e; t.state = r >= 0 ? (t.running ? 'RUN' : 'STOP') : 'OVER'; return (r >= 0 ? '-' : '+') + f(r); }
    if (t.kind === 'AT') { var d = t.value * 60 - (now % 86400); if (d > 43200) d -= 86400; if (d < -43200) d += 86400; t.state = d > 0 ? 'RUN' : 'OVER'; return (d > 0 ? 'H-' : 'H+') + f(d); }
    t.state = t.running ? 'RUN' : 'STOP';
    return '+' + f(e);
  }
  function updateTimerValues() {
    ((S.room && S.room.timers) || []).forEach(function (t) {
      var el = document.querySelector('[data-timer="' + t.id + '"]');
      if (el) { el.textContent = timerValue(t); el.className = 'toc-timer-val ' + timerClass(t); }
    });
  }
  function panelChronos() {
    var ts = (S.room && S.room.timers) || [];
    var h = sec('Chronos', ts.length + ' chrono(s) du poste');
    if (!ts.length) h += empty('Aucun chrono : créez l’heure H ou un compte à rebours ci-dessous.');
    ts.forEach(function (t) {
      var sub = t.kind === 'AT' ? 'heure H ' + two(Math.floor(t.value / 60)) + ':' + two(t.value % 60) + 'Z' : (t.kind === 'DOWN' ? 'compte à rebours' : 'chrono') + (t.running ? ' · en marche' : ' · arrêté');
      h += '<div class="toc-timer ' + (S.sel.timer === t.id ? 'is-sel' : '') + '" data-sel="timer" data-id="' + esc(t.id) + '"><div><b>' + esc(t.name) + pendingMark(t) + '</b><small>' + esc(sub) + '</small></div><span class="toc-timer-val ' + timerClass(t) + '" data-timer="' + esc(t.id) + '">' + timerValue(t) + '</span></div>';
    });
    var sel = ts.filter(function (t) { return t.id === S.sel.timer; })[0];
    h += '<div class="toc-buttons"><button type="button" class="toc-btn is-primary" data-do="timer" data-a="timerStart"' + (sel ? '' : ' disabled') + '>Lancer</button><button type="button" class="toc-btn" data-do="timer" data-a="timerStop"' + (sel ? '' : ' disabled') + '>Arrêter</button><button type="button" class="toc-btn" data-do="timer" data-a="timerReset"' + (sel ? '' : ' disabled') + '>Zéro</button><button type="button" class="toc-btn" data-do="timer" data-a="timerDel"' + (sel ? '' : ' disabled') + '>Supprimer</button></div>';
    h += '<form class="toc-form" data-form="timer"><div class="toc-row"><input class="toc-input" name="name" maxlength="24" placeholder="Nom (H-HOUR, EXFIL…)" aria-label="Nom du chrono" required><select class="toc-select" name="kind" aria-label="Genre"><option value="UP">Chrono</option><option value="DOWN">Compte à rebours</option><option value="AT">Heure H (ZULU)</option></select><input class="toc-input mono" name="value" placeholder="mm:ss ou HH:MM" aria-label="Durée ou heure"></div><button class="toc-btn is-primary is-block" type="submit">Créer le chrono</button></form>';
    return h + '<p class="toc-note">Les chronos sont ceux du poste en jeu : écrans HORLOGE et PC du poste.</p>';
  }

  function logTone(kind) { return { FLASH: 'red', DEMANDE: 'amber', CR: 'amber', ALERTE: 'red', EXCHECK: 'accent', POSTE: 'blue' }[kind] || ''; }
  function panelMain() {
    var log = ((S.room && S.room.log) || []).slice().reverse();
    var h = sec('Main courante', log.length + ' entrée(s) · heures ZULU');
    h += '<form class="toc-form" data-form="log"><textarea class="toc-textarea" name="text" rows="2" maxlength="200" placeholder="Entrée de la main courante…" aria-label="Entrée"></textarea><button class="toc-btn is-primary is-block" type="submit">Inscrire <kbd>Ctrl+Entrée</kbd></button></form>';
    if (!log.length) return h + empty('La main courante est vide : comptes rendus, demandes, FLASH et EXCHECK s’y inscrivent seuls.');
    return h + '<table class="toc-table"><thead><tr><th style="width:84px">Heure Z</th><th style="width:104px">Type</th><th>Entrée</th></tr></thead><tbody>' + log.slice(0, 150).map(function (l) {
      return '<tr title="' + esc(l.author) + '"><td class="mono">' + esc(l.t) + '</td><td>' + pill(l.kind, logTone(l.kind)) + '</td><td class="wrap">' + esc(l.text) + ' <span class="toc-muted">· ' + esc(l.author) + '</span>' + pendingMark(l) + '</td></tr>';
    }).join('') + '</tbody></table>';
  }

  function panelExcheck() {
    var ex = (S.room && S.room.excheck) || [];
    var done = ex.filter(function (e) { return e.done; }).length;
    var h = sec('EXCHECK', done + ' / ' + ex.length + ' passé(s)');
    if (!ex.length) h += empty('Liste d’exécution vide : ajoutez les lignes (mot de code, événement, élément, heure prévue).');
    else {
      var nextDone = false;
      h += '<ul class="toc-list" aria-label="Liste d’exécution">' + ex.map(function (e) {
        var isNext = !e.done && !nextDone;
        if (isNext) nextDone = true;
        return '<li data-sel="ex" data-id="' + esc(e.id) + '" class="' + (S.sel.ex === e.id ? 'is-sel' : '') + '"><span class="' + (e.done ? 'is-done' : '') + '">' + e.n + '. ' + esc(e.cw ? e.cw + ' · ' : '') + esc(e.ev) + (e.el ? ' · ' + esc(e.el) : '') + pendingMark(e) + '</span><span class="mono' + (isNext ? ' is-old' : '') + '">' + (e.done ? 'passé ' + esc(e.done) : (e.at ? 'prévu ' + esc(e.at) : '')) + '</span></li>';
      }).join('') + '</ul>';
    }
    var sel = ex.filter(function (e) { return e.id === S.sel.ex; })[0];
    h += '<div class="toc-buttons" style="--cols:3"><button type="button" class="toc-btn is-primary" data-do="ex" data-a="exDone"' + (sel && !sel.done ? '' : ' disabled') + '>Passé</button><button type="button" class="toc-btn" data-do="ex" data-a="exUndo"' + (sel && sel.done ? '' : ' disabled') + '>Annuler</button><button type="button" class="toc-btn" data-do="ex" data-a="exDel"' + (sel ? '' : ' disabled') + '>Supprimer</button></div>';
    h += '<form class="toc-form" data-form="ex"><input class="toc-input" name="ev" maxlength="60" placeholder="Événement…" aria-label="Événement" required><div class="toc-row"><input class="toc-input" name="cw" maxlength="24" placeholder="Mot de code" aria-label="Mot de code"><input class="toc-input" name="el" maxlength="24" placeholder="Élément" aria-label="Élément"><input class="toc-input mono" name="at" placeholder="HH:MM" pattern="[0-2][0-9]:[0-5][0-9]" aria-label="Heure prévue"></div><button class="toc-btn is-primary is-block" type="submit">Ajouter la ligne</button></form>';
    return h + '<p class="toc-note">PASSÉ inscrit l’heure ZULU réelle et une entrée à la main courante.</p>';
  }

  function panelPertes() {
    var k = (S.room && S.room.kills) || [];
    var tot = { wia: 0, kia: 0, ekia: 0, ewia: 0 };
    k.forEach(function (r) { ['wia', 'kia', 'ekia', 'ewia'].forEach(function (x) { tot[x] += r[x] || 0; }); });
    var h = sec('Pertes et résultats', 'déclarés au poste');
    h += '<div class="toc-kpis"><div class="toc-kpi is-amber"><b>' + tot.wia + '</b><span>amis blessés</span></div><div class="toc-kpi is-red"><b>' + tot.kia + '</b><span>amis tués</span></div><div class="toc-kpi is-accent"><b>' + tot.ekia + '</b><span>ennemis neutralisés</span></div><div class="toc-kpi is-blue"><b>' + tot.ewia + '</b><span>ennemis blessés / capturés</span></div></div>';
    if (!k.length) h += empty('Aucune perte ni résultat déclaré.');
    else {
      h += '<table class="toc-table"><thead><tr><th>Élément</th><th class="num">Bl.</th><th class="num">Tués</th><th class="num">Neutr.</th><th class="num">Bl./capt.</th></tr></thead><tbody>' + k.map(function (r) {
        return '<tr data-sel="kill" data-id="' + esc(r.el) + '" class="' + (S.sel.kill === r.el ? 'is-sel' : '') + '"><td><b>' + esc(r.el) + '</b></td><td class="num">' + r.wia + '</td><td class="num">' + r.kia + '</td><td class="num">' + r.ekia + '</td><td class="num">' + r.ewia + '</td></tr>';
      }).join('') + '</tbody></table>';
    }
    h += '<form class="toc-form" data-form="kill"><div class="toc-row"><input class="toc-input" name="el" maxlength="40" placeholder="Élément" aria-label="Élément" value="' + esc(S.sel.kill || '') + '" required><select class="toc-select" name="key" aria-label="Catégorie"><option value="wia">Amis blessés</option><option value="kia">Amis tués</option><option value="ekia">Ennemis neutralisés</option><option value="ewia">Ennemis blessés / capturés</option></select><input class="toc-input mono" name="n" type="number" min="-50" max="50" value="1" aria-label="Nombre"></div><button class="toc-btn is-primary is-block" type="submit">Déclarer</button></form>';
    return h + '<p class="toc-note">Nombre négatif pour corriger. Le jeu ajoute seul les blessés (homme à terre) et les ennemis déclarés par BDA.</p>';
  }

  function reqTone(st) { return { RECUE: 'amber', PRISE: 'blue', REFUSEE: 'red', TERMINEE: 'accent' }[st] || ''; }
  var REQ_LABEL = { RECUE: 'reçue', PRISE: 'prise en compte', REFUSEE: 'refusée', TERMINEE: 'terminée' };
  function panelDemandes() {
    var rq = (S.room && S.room.requests) || [];
    var c = { RECUE: 0, PRISE: 0, TERMINEE: 0, REFUSEE: 0 };
    rq.forEach(function (r) { c[r.status] = (c[r.status] || 0) + 1; });
    var h = sec('Demandes', 'MEDEVAC, appuis feu, ravitaillements');
    h += '<div class="toc-kpis"><div class="toc-kpi is-amber"><b>' + c.RECUE + '</b><span>reçues</span></div><div class="toc-kpi is-blue"><b>' + c.PRISE + '</b><span>prises en compte</span></div><div class="toc-kpi is-accent"><b>' + c.TERMINEE + '</b><span>terminées</span></div><div class="toc-kpi is-red"><b>' + c.REFUSEE + '</b><span>refusées</span></div></div>';
    if (!rq.length) return h + empty('Aucune demande des éléments : MEDEVAC, appels de feu et ravitaillements faits en jeu arrivent ici.');
    h += '<table class="toc-table"><thead><tr><th style="width:62px">Heure</th><th style="width:118px">Type</th><th>Demandeur</th><th class="num" style="width:132px">Statut</th></tr></thead><tbody>' + rq.slice().reverse().map(function (r) {
      return '<tr data-sel="req" data-id="' + esc(r.id) + '" class="' + (S.sel.req === r.id ? 'is-sel' : '') + '"><td class="mono">' + esc(r.t.slice(0, 5)) + '</td><td><b>' + esc(r.kind) + '</b></td><td>' + esc(r.by) + '</td><td class="num">' + pill(REQ_LABEL[r.status] || r.status, reqTone(r.status)) + pendingMark(r) + '</td></tr>';
    }).join('') + '</tbody></table>';
    var sel = rq.filter(function (r) { return r.id === S.sel.req; })[0];
    if (sel) {
      h += '<div class="toc-card"><h3>' + esc(sel.kind) + ' · ' + esc(sel.by) + '</h3><p class="mono">' + esc(sel.t) + 'Z' + (sel.grid ? ' · grille ' + esc(sel.grid) : '') + '</p><p>' + esc(sel.text) + '</p>' + (sel.answer ? '<p>Réponse : ' + esc(sel.answer) + '</p>' : '') +
        '<form class="toc-form" data-form="req"><input class="toc-input" name="answer" maxlength="160" placeholder="Réponse envoyée au téléphone du demandeur…" aria-label="Réponse"><div class="toc-buttons" style="--cols:3"><button class="toc-btn is-primary" type="submit" name="status" value="PRISE">Prise en compte</button><button class="toc-btn" type="submit" name="status" value="TERMINEE">Terminée</button><button class="toc-btn is-danger" type="submit" name="status" value="REFUSEE">Refusée</button></div></form>' +
        (sel.pos ? '<button type="button" class="toc-btn" data-do="req-center">Centrer</button>' : '') + '</div>';
    } else {
      h += '<p class="toc-note">Choisissez une demande pour répondre : la réponse part sur le téléphone du demandeur en jeu.</p>';
    }
    return h;
  }

  function panelCalques() {
    return sec('Calques', 'données du jeu et du web') + '<p class="toc-note">Le panneau des calques est ouvert sur la carte : cases à cocher, opacité, filtres par camp et par groupe.</p><button type="button" class="toc-btn is-block" data-do="layers-toggle">Afficher / masquer le panneau</button>';
  }

  var PANELS = {
    groupes: panelGroupes, orbat: panelOrbat, flux: panelFlux, ordres: panelOrdres, camp: panelCamp, reseau: panelReseau, radio: panelRadio, plans: panelPlans,
    sat: panelSat, salle: panelSalle, chronos: panelChronos, main: panelMain, excheck: panelExcheck, pertes: panelPertes, demandes: panelDemandes, calques: panelCalques
  };
  function renderPanel() {
    var body = $('#toc-panel');
    var focusName = document.activeElement && body.contains(document.activeElement) ? document.activeElement.getAttribute('name') : null;
    var keep = {};
    body.querySelectorAll('input[name],textarea[name],select[name]').forEach(function (i) { if (i.value) keep[i.closest('form') ? i.closest('form').getAttribute('data-form') + ':' + i.name : i.name] = i.value; });
    body.innerHTML = (PANELS[S.tab] || panelGroupes)();
    body.querySelectorAll('input[name],textarea[name],select[name]').forEach(function (i) {
      var k = (i.closest('form') ? i.closest('form').getAttribute('data-form') + ':' : '') + i.name;
      if (keep[k] != null && i.type !== 'number') i.value = keep[k];
    });
    if (focusName) { var f = body.querySelector('[name="' + focusName + '"]'); if (f) f.focus(); }
    if (S.tab === 'plans' && !S.plansLoaded) loadPlans();
    if (S.tab === 'reseau') loadJournal();
  }
  function loadPlans() {
    api('/atak/toc/api/plans').then(function (j) {
      S.plansLoaded = true;
      S.plans = j && j.ok ? j.plans.filter(function (p) { return !p.map_id || p.map_id === S.mapId; }) : [];
      if (S.tab === 'plans') renderPanel();
    });
  }
  function loadJournal() {
    api('/atak/toc/api/journal?mapId=' + S.mapId + '&side=' + S.side).then(function (j) {
      var el = $('#toc-journal');
      if (!el) return;
      var rows = (j && j.actions) || [];
      var lbl = { queued: 'en file', sent: 'envoyée', applied: 'appliquée en jeu', dropped: 'non reprise' };
      el.innerHTML = rows.length ? '<table class="toc-table"><tbody>' + rows.slice(0, 12).map(function (a) {
        return '<tr><td class="mono" style="width:84px">' + esc(String(a.created_at).slice(11, 19)) + '</td><td>' + esc(a.act) + ' · ' + esc(a.who) + '</td><td class="num">' + pill(lbl[a.status] || a.status, a.status === 'applied' ? 'accent' : (a.status === 'dropped' ? 'red' : 'amber')) + '</td></tr>';
      }).join('') + '</tbody></table>' : 'Aucune action du poste web.';
    });
  }

  function parseDuration(v, kind) {
    v = String(v || '').trim();
    if (kind === 'AT') { var m = /^(\d{1,2}):(\d{2})$/.exec(v); return m ? (+m[1]) * 60 + (+m[2]) : -1; }
    if (kind === 'DOWN') {
      var p = v.split(':').map(Number);
      if (p.some(isNaN) || !v) return -1;
      return p.length === 3 ? p[0] * 3600 + p[1] * 60 + p[2] : (p.length === 2 ? p[0] * 60 + p[1] : p[0] * 60);
    }
    return 0;
  }

  /* ---------- Vue SAT (carte ISR) ---------- */
  var isrLayer = null;
  function renderIsr() {
    var hud = $('#toc-isr');
    if (!S.tm) return;
    if (isrLayer) { S.tm.map.removeLayer(isrLayer); isrLayer = null; }
    hud.hidden = !S.isr;
    if (!S.isr) return;
    var sats = ((S.room && S.room.sats) || []).filter(function (s) { return s.x >= 0; });
    isrLayer = L.layerGroup().addTo(S.tm.map);
    sats.forEach(function (s) {
      var b = [S.tm.toLL([s.x - s.hw, s.y - s.hw * 0.5625]), S.tm.toLL([s.x + s.hw, s.y + s.hw * 0.5625])];
      L.rectangle(b, { color: '#34d399', weight: 2, fill: false, dashArray: '6 4', interactive: false }).addTo(isrLayer);
      L.marker(S.tm.toLL([s.x - s.hw, s.y + s.hw * 0.5625]), { interactive: false, icon: L.divIcon({ className: '', html: '<span class="toc-plate">SAT ' + s.n + (s.op ? ' · ' + esc(s.op) : '') + '</span>', iconSize: null, iconAnchor: [0, 26] }) }).addTo(isrLayer);
    });
    var s0 = sats[0];
    $('#isr-tl').textContent = s0 ? 'SAT ' + s0.n + ' · ' + (s0.sensor || 'AUTO') + ' · VUE CARTE' : 'SAT · VUE CARTE';
    $('#isr-tr').textContent = s0 ? 'EMPRISE ' + Math.round(s0.hw * 2) + ' M' : 'AUCUN SATELLITE';
    $('#isr-bl').textContent = s0 ? 'GR ' + G.grid(s0.x, s0.y, 8) : '';
    if (s0 && !S.isrCentered) { S.isrCentered = true; S.tm.fitWorld([s0.x - s0.hw, s0.y - s0.hw, s0.x + s0.hw, s0.y + s0.hw]); }
  }

  /* ---------- Événements ---------- */
  function onPanelClick(e) {
    var row = e.target.closest('[data-sel]');
    var doBtn = e.target.closest('[data-do]');
    if (doBtn) {
      var d = doBtn.getAttribute('data-do');
      if (d === 'timer' && S.sel.timer) act(doBtn.getAttribute('data-a'), { id: S.sel.timer });
      else if (d === 'ex' && S.sel.ex) act(doBtn.getAttribute('data-a'), { id: S.sel.ex });
      else if (d === 'center-grp') { var g = groups().filter(function (x) { return x.name === S.sel.grp; })[0]; if (g && g.pos) S.tm.centerOn(g.pos); }
      else if (d === 'req-center') { var r = ((S.room && S.room.requests) || []).filter(function (x) { return x.id === S.sel.req; })[0]; if (r && r.pos) S.tm.centerOn(r.pos); }
      else if (d === 'sat-center') { var s = ((S.room && S.room.sats) || [])[Number(doBtn.getAttribute('data-n')) - 1]; if (s) S.tm.fitWorld([s.x - s.hw, s.y - s.hw, s.x + s.hw, s.y + s.hw]); }
      else if (d === 'layers-toggle' && S.layers) S.layers.openPanel();
      else if (d === 'plan-show' && S.layers) { S.layers.showPlan(S.layers.shownPlan() === S.sel.plan ? null : S.sel.plan); renderPanel(); }
      else if (d === 'plan-publish') publishPlan(S.sel.plan);
      return;
    }
    if (!row) return;
    var kind = row.getAttribute('data-sel'), id = row.getAttribute('data-id');
    if (kind === 'unit') { var u = units().filter(function (x) { return String(x.id) === id; })[0]; if (u && u.pos) S.tm.centerOn(u.pos); return; }
    if (kind === 'feed') { var f = (S.layers ? S.layers.feed() : []).filter(function (x) { return String(x.id) === id; })[0]; if (f && f.pos) S.tm.centerOn(f.pos); return; }
    if (kind === 'plan') id = Number(id);
    S.sel[kind] = S.sel[kind] === id && kind !== 'plan' ? null : id;
    if (kind === 'grp' && S.sel.grp) { var gg = groups().filter(function (x) { return x.name === id; })[0]; if (gg && gg.pos) S.tm.centerOn(gg.pos); }
    renderPanel();
  }
  function publishPlan(id) {
    var p = S.plans.filter(function (x) { return x.id === id; })[0];
    if (!p) return;
    if (!window.confirm('Envoyer « ' + p.title + ' » dans le plan partagé du camp ' + SIDES[S.side] + ' ? Les éléments déjà envoyés par ce plan sont remplacés.')) return;
    api('/atak/toc/api/plans/' + id + '/publish', { method: 'POST', body: { side: S.side } }).then(function (j) {
      if (j && j.ok) say(j.sent + ' élément(s) envoyé(s) en jeu' + (j.skipped ? ', ' + j.skipped + ' trop détaillé(s) écarté(s)' : '') + '.');
      else say({ nothing_to_send: 'Plan vide.', too_many_for_game: 'Plus de 300 éléments : envoyez phase par phase.', queue_unavailable: 'File des commandes du jeu indisponible.' }[j && j.error || j && j.reason] || 'Envoi refusé.', true);
    });
  }
  function onPanelSubmit(e) {
    var form = e.target.closest('form[data-form]');
    if (!form) return;
    e.preventDefault();
    var kind = form.getAttribute('data-form');
    var v = function (n) { var i = form.elements[n]; return i ? String(i.value || '').trim() : ''; };
    var done = function (ok) { if (ok) form.reset(); };
    if (kind === 'log') { if (!v('text')) return say('Écrivez l’entrée.', true); act('log', { text: v('text') }).then(done); }
    if (kind === 'timer') {
      var k = v('kind'), val = parseDuration(v('value'), k);
      if (val < 0) return say(k === 'AT' ? 'Heure H au format HH:MM (ZULU).' : 'Durée au format mm:ss.', true);
      act('timerAdd', { name: v('name'), kind: k, value: val }).then(done);
    }
    if (kind === 'ex') act('exAdd', { ev: v('ev'), cw: v('cw'), el: v('el'), at: v('at') }).then(done);
    if (kind === 'kill') act('kill', { el: v('el'), key: v('key'), n: Number(v('n')) }).then(done);
    if (kind === 'req' && S.sel.req) {
      var st = e.submitter ? e.submitter.value : 'PRISE';
      act('reqSet', { id: S.sel.req, status: st, answer: v('answer') }).then(done);
    }
  }

  function bindKeys() {
    document.addEventListener('keydown', function (e) {
      var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName);
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && e.target.name === 'text' && e.target.closest('form[data-form="log"]')) { e.preventDefault(); e.target.closest('form').requestSubmit(); return; }
      if (e.key === 'Escape') { $('#toc-help-overlay').hidden = true; if (S.layers) S.layers.openPanel(false); return; }
      if (typing) return;
      if (e.key === '?') { e.preventDefault(); $('#toc-help-overlay').hidden = false; $('#toc-help-close').focus(); return; }
      if (e.altKey && /^[1-9]$/.test(e.key)) { e.preventDefault(); setTab(TABS[Number(e.key) - 1][0]); return; }
      if (e.key === 'f' || e.key === 'F') { e.preventDefault(); $('#toc-flash-input').focus(); return; }
      if (e.key === 'l' || e.key === 'L') { if (S.layers) S.layers.openPanel(); return; }
      if (e.key === 'g' || e.key === 'G') { S.gridOn = !S.gridOn; S.tm.setGrid(S.gridOn); return; }
      if (e.key === 'b' || e.key === 'B') { cycleBackground(); return; }
      if (e.key === '/') { e.preventDefault(); $('#toc-search').focus(); }
    });
    $('#toc-tabs').addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
      var i = TABS.map(function (t) { return t[0]; }).indexOf(S.tab);
      i = (i + (e.key === 'ArrowRight' ? 1 : -1) + TABS.length) % TABS.length;
      setTab(TABS[i][0]);
      $('#tab-' + TABS[i][0]).focus();
    });
  }
  function cycleBackground() {
    var bgs = S.tm.backgrounds, cur = S.tm.currentBackground();
    var i = bgs.indexOf(cur);
    var next = bgs[(i + 1) % bgs.length];
    S.tm.setBackground(next.id);
    $('#toc-bg').value = next.id;
    setPref('bg', next.id);
  }

  function search() {
    var q = $('#toc-search').value.trim();
    if (!q) return;
    var p = G.parseGrid(q);
    if (p) { S.tm.centerOn(p); say('Grille ' + q); return; }
    var u = units().filter(function (x) { return (x.callsign + ' ' + (x.group || '')).toUpperCase().indexOf(q.toUpperCase()) >= 0; })[0];
    if (u && u.pos) { S.tm.centerOn(u.pos); say(u.callsign + ' · ' + G.grid(u.pos[0], u.pos[1], 8)); return; }
    say('Rien trouvé pour « ' + q + ' ».', true);
  }

  function buildMap() {
    var t = terrain();
    if (!t) { setBanner('Aucun terrain n’est configuré sur le site : le poste ne peut pas afficher de carte.'); return; }
    if (S.tm) { S.tm.destroy(); S.tm = null; }
    if (S.layers) S.layers.destroy();
    S.isrCentered = false;
    S.tm = window.TocMap.create($('#toc-map'), t, {
      onCursor: function (p) { $('#toc-cursor').textContent = G.grid(p[0], p[1], 8); },
      onBackground: function (bg) { $('#toc-bg-note').textContent = bg.note; }
    });
    var bg = pref('bg', 'sat');
    $('#toc-bg').innerHTML = S.tm.backgrounds.map(function (b) { return '<option value="' + b.id + '">' + esc(b.label) + '</option>'; }).join('');
    $('#toc-bg').value = bg;
    S.tm.setBackground(bg);
    S.gridOn = true;
    S.layers = window.TocLayers.create(S.tm, {
      base: BOOT.base, mapId: S.mapId, side: S.side, panel: $('#toc-layers'), mode: 'console',
      onData: function () {
        if (['groupes', 'orbat', 'flux', 'ordres', 'camp', 'reseau'].indexOf(S.tab) >= 0 && !document.activeElement.closest('#toc-panel form')) renderPanel();
        renderStatus();
      },
      fetchPlan: function (id) { return api('/atak/toc/api/plans/' + id); }
    });
  }
  function renderStatus() {
    var all = units().filter(function (u) { return !u.side || u.side === S.side; });
    var live = all.filter(function (u) { return u.fresh === 'live'; }).length;
    var old = all.filter(function (u) { return u.fresh === 'old'; }).length;
    var lost = all.length - live - old;
    $('#toc-status-net').innerHTML = '<b>Réseau</b> ' + live + ' en ligne · ' + old + ' ancien(s) · ' + lost + ' perdu(s) ou hors ligne';
    var reqs = ((S.room && S.room.requests) || []).filter(function (r) { return r.status === 'RECUE'; }).length;
    $('#toc-status-room').innerHTML = '<b>Salle</b> ' + reqs + ' demande(s) à traiter' + (S.game && S.game.pending ? ' · ' + S.game.pending + ' action(s) web en attente du jeu' : '');
  }

  function init() {
    if (!BOOT.terrains || !BOOT.terrains.length) { setBanner('Aucun terrain n’est configuré sur le site.'); }
    var mapSel = $('#toc-terrain');
    mapSel.innerHTML = (BOOT.terrains || []).map(function (t) { return '<option value="' + t.id + '">' + esc(t.label) + '</option>'; }).join('');
    if (!terrain()) S.mapId = BOOT.defaultMap;
    mapSel.value = String(S.mapId);
    $('#toc-side').value = S.side;
    renderTabs();
    renderTop();
    buildMap();
    renderPanel();
    tickClocks();
    setInterval(tickClocks, 1000);
    loadRoom();
    setInterval(function () { if (!document.hidden) loadRoom(); }, 3000);
    $('#toc-tabs').addEventListener('click', function (e) { var b = e.target.closest('[data-tab]'); if (b) setTab(b.getAttribute('data-tab')); });
    $('#toc-panel').addEventListener('click', onPanelClick);
    $('#toc-panel').addEventListener('submit', onPanelSubmit);
    $('#toc-flash-btn').addEventListener('click', sendFlash);
    $('#toc-flash-input').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); sendFlash(); } });
    $('#toc-flash-clear').addEventListener('click', function () { act('flashClear', {}); });
    $('#toc-side').addEventListener('change', function () { S.side = this.value; setPref('side', S.side); S.roomVersion = ''; S.room = null; S.sel = {}; if (S.layers) S.layers.setSide(S.side); renderTop(); renderPanel(); loadRoom(); });
    $('#toc-terrain').addEventListener('change', function () { S.mapId = Number(this.value); setPref('map', S.mapId); S.roomVersion = ''; S.room = null; S.plansLoaded = false; S.sel = {}; buildMap(); renderTop(); renderPanel(); loadRoom(); });
    $('#toc-bg').addEventListener('change', function () { S.tm.setBackground(this.value); setPref('bg', this.value); });
    $('#toc-layers-btn').addEventListener('click', function () { if (S.layers) S.layers.openPanel(); });
    $('#toc-search-btn').addEventListener('click', search);
    $('#toc-search').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); search(); } });
    $('#toc-help-btn').addEventListener('click', function () { $('#toc-help-overlay').hidden = false; $('#toc-help-close').focus(); });
    $('#toc-help-close').addEventListener('click', function () { $('#toc-help-overlay').hidden = true; });
    bindKeys();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
