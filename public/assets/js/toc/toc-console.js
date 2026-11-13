/* Poste TOC web (/atak/toc) : même console que le PC du poste en jeu. Barre du haut (poste, ZULU, LOCAL, liaison,
 * FLASH), quatre rubriques et leurs onglets, carte commune à gauche (calques du jeu), panneau de l'onglet à droite,
 * ligne d'état. Clic droit sur la carte : menu comme dans l'ATAK en jeu. Message, ordre, marqueur et point de passage
 * s'ouvrent dans une fenêtre de la console (lien #fenetre=…), sans quitter le poste.
 * Données : API ATAK existantes (positions, véhicules, aéronefs, marqueurs, formes, comptes rendus…) et salle TOC
 * partagée avec le jeu (/atak/toc/api/state, sondée toutes les 3 s avec ETag). Textes en français (traduits par le
 * dictionnaire du site : chaque texte posé dans la page est une phrase entière ou un modèle « … %1 … »). */
(function () {
  'use strict';
  var BOOT = window.TOC_BOOT || {};
  var G = window.TocGeo;
  var T = window.TocTracks;
  var UI = window.TocUi;
  var esc = window.TocMap.esc;
  var $ = function (sel, root) { return (root || document).querySelector(sel); };

  // Rubriques et onglets : la situation, la conduite de l'opération, les plans, la salle.
  var NAV = [
    ['situation', 'Situation', [['groupes', 'Groupes'], ['orbat', 'ORBAT'], ['reseau', 'Réseau'], ['flux', 'Flux']]],
    ['conduite', 'Conduite', [['ordres', 'Ordres'], ['demandes', 'Demandes'], ['chronos', 'Chronos'], ['excheck', 'EXCHECK'], ['main', 'Main courante'], ['pertes', 'Pertes']]],
    ['plans', 'Plans', [['plans', 'Plans web'], ['camp', 'Camp']]],
    ['salle', 'Salle', [['salle', 'Écrans'], ['sat', 'SAT'], ['radio', 'Radio']]]
  ];
  var TAB_GROUP = {};
  NAV.forEach(function (g) { g[2].forEach(function (t) { TAB_GROUP[t[0]] = g[0]; }); });
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
    sel: {}, pick: null, filter: {}, orbatClosed: {}, plans: [], plansLoaded: false, tm: null, layers: null, isr: false, statusTimer: null,
    measure: null, ctxPos: null, suggest: [], suggestIdx: -1, openLayers: false
  };
  if (!SIDES[S.side]) S.side = 'WEST';
  if (!TAB_GROUP[S.tab]) S.tab = 'groupes';
  (function () {
    // Lien direct vers un onglet : /atak/toc?onglet=demandes (« calques » ouvre le panneau des calques).
    var m = /[?&]onglet=([a-z]+)/.exec(window.location.search);
    if (m && TAB_GROUP[m[1]]) S.tab = m[1];
    if (m && m[1] === 'calques') S.openLayers = true;
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
    if (opts.body !== undefined || init.method !== 'GET') {
      init.headers['X-CSRF-Token'] = BOOT.csrf || '';
    }
    if (opts.body !== undefined) {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(opts.body);
    }
    return fetch(BOOT.base + path, init).then(function (r) {
      if (r.status === 304) return { notModified: true };
      return r.json().catch(function () { return { ok: r.ok, error: r.ok ? '' : 'http_' + r.status }; }).then(function (j) {
        if (j === null || typeof j !== 'object') j = { value: j };
        if (!r.ok && j.ok !== false) j.ok = false;
        if (r.ok && j.ok === undefined && !Array.isArray(j)) j.ok = true;
        j._status = r.status;
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
  function copyText(text, done) {
    var ok = function () { say(done); };
    var fallback = function () {
      var ta = document.createElement('textarea');
      ta.value = text; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); ok(); } catch (e) { say('Copie impossible dans ce navigateur.', true); }
      ta.remove();
    };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text).then(ok, fallback);
    else fallback();
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
      local.parentNode.title = 'Heure de ce poste (aucune partie en liaison)';
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
      if (sig === S.roomVersion) { renderLink(); return; }
      S.roomVersion = sig;
      S.room = j.state;
      renderTop();
      renderFlash();
      renderTabs();
      renderStatus();
      if (['chronos', 'main', 'excheck', 'pertes', 'demandes', 'salle', 'sat'].indexOf(S.tab) >= 0) renderPanel();
      if (S.layers) S.layers.setRequests(S.room.requests || []);
      if (S.isr) renderIsr();
    }).catch(function () { S.linkDown = true; renderLink(); });
  }
  function act(name, args) {
    return api('/atak/toc/api/action', { method: 'POST', body: { mapId: S.mapId, side: S.side, act: name, args: args || {} } }).then(function (j) {
      if (!j || !j.ok) { say(ERRORS[j && j.error] || 'Action refusée.', true); return false; }
      S.room = j.state; S.game = j.game; S.roomVersion = JSON.stringify([j.state, j.game]);
      renderTop(); renderFlash(); renderTabs(); renderPanel(); renderStatus();
      say(S.game && S.game.live ? 'Envoyé au jeu : en attente de la prise en compte du serveur.' : 'Inscrit sur le poste web ; partira en jeu à la prochaine liaison.');
      return true;
    }).catch(function () { say('Réseau indisponible : action non envoyée.', true); return false; });
  }

  function setBanner(msg) {
    var b = $('#toc-banner');
    b.textContent = msg || '';
    b.hidden = !msg;
  }
  /** État de la liaison : partie en liaison, ou âge des dernières données reçues (pistes ou salle). */
  function renderLink() {
    var dot = $('#toc-link-dot'), txt = $('#toc-link-txt'), ageEl = $('#toc-link-age');
    var live = !!(S.game && S.game.live);
    var ages = tracks().map(function (t) { return t.age; }).filter(function (a) { return a != null && isFinite(a); });
    var age = ages.length ? Math.min.apply(null, ages) : null;
    if (S.game && S.game.seen_at) { var roomAge = Math.max(0, serverNow() - S.game.seen_at); age = age == null ? roomAge : Math.min(age, roomAge); }
    var fresh = live || (age != null && age < 60);
    dot.className = 'toc-dot ' + (fresh ? 'is-live' : (age != null && age < 900 ? 'is-old' : 'is-off'));
    txt.textContent = S.linkDown && !S.game ? 'Site injoignable' : (fresh ? 'Partie en liaison' : 'Aucune partie en liaison');
    ageEl.innerHTML = fresh || age == null ? '' : '<span>dernières données</span> <span>' + esc(T.ageLabel(age)) + '</span>';
    ageEl.hidden = !ageEl.innerHTML;
  }

  /* ---------- Barre du haut, FLASH ---------- */
  function renderTop() {
    var t = terrain();
    $('#toc-title').innerHTML = '<span>Poste TOC</span>' + (t ? ' · ' + esc(t.label) : '');
    var post = S.room && S.room.posts && S.room.posts[0];
    $('#toc-sub').innerHTML = '<span>' + SIDES[S.side] + '</span>' + (post ? txt('poste pris en jeu par ' + post.op + ' à ' + post.t + 'Z') : '') + '<span><span>Opérateur web</span> ' + esc(BOOT.operator || '') + '</span>';
    renderLink();
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

  /* ---------- Rubriques et onglets ---------- */
  function badgeFor(id) {
    var r = S.room || {};
    if (id === 'demandes') return (r.requests || []).filter(function (q) { return q.status === 'RECUE'; }).length;
    return 0;
  }
  function renderTabs() {
    var cur = TAB_GROUP[S.tab];
    $('#toc-groups').innerHTML = NAV.map(function (g, i) {
      var b = g[2].reduce(function (a, t) { return a + badgeFor(t[0]); }, 0);
      return '<button type="button" role="tab" class="toc-group" aria-selected="' + (cur === g[0]) + '" tabindex="' + (cur === g[0] ? 0 : -1) + '" data-group="' + g[0] + '" title="Alt+' + (i + 1) + '"><span>' + esc(g[1]) + '</span>' + (b ? '<span class="toc-badge">' + b + '</span>' : '') + '</button>';
    }).join('');
    var grp = NAV.filter(function (g) { return g[0] === cur; })[0];
    $('#toc-tabs').innerHTML = grp[2].map(function (t) {
      var b = badgeFor(t[0]);
      return '<button type="button" role="tab" class="toc-tab" id="tab-' + t[0] + '" aria-controls="toc-panel" aria-selected="' + (S.tab === t[0]) + '" tabindex="' + (S.tab === t[0] ? 0 : -1) + '" data-tab="' + t[0] + '"><span>' + esc(t[1]) + '</span>' + (b ? '<span class="toc-badge">' + b + '</span>' : '') + '</button>';
    }).join('');
  }
  function setTab(id) {
    if (!TAB_GROUP[id]) return;
    S.tab = id;
    setPref('tab', id);
    S.isr = id === 'sat';
    renderTabs();
    renderPanel();
    renderIsr();
  }
  function setGroup(gid) {
    var g = NAV.filter(function (x) { return x[0] === gid; })[0];
    if (!g) return;
    // Dernier onglet ouvert dans la rubrique, sinon le premier.
    var last = pref('tab:' + gid, g[2][0][0]);
    setTab(TAB_GROUP[last] === gid ? last : g[2][0][0]);
  }

  /* ---------- Panneau de droite ---------- */
  // Un nombre et son libellé sont deux textes séparés : le libellé seul est traduit par le dictionnaire du site.
  function cnt(n, label) { return '<span class="toc-n"><b>' + esc(n) + '</b> <span>' + esc(label) + '</span></span>'; }
  function txt(s) { return '<span>' + esc(s) + '</span>'; }
  function sec(title, parts) {
    var list = Array.isArray(parts) ? parts : (parts ? [txt(parts)] : []);
    return '<div class="toc-sec"><h2>' + esc(title) + '</h2><span class="toc-dots">' + list.join('') + '</span></div>';
  }
  function empty(sentence, actionHtml) { return '<div class="toc-empty"><p>' + esc(sentence) + '</p>' + (actionHtml || '') + '</div>'; }
  function pill(text, tone) { return '<span class="toc-pill ' + (tone ? 'is-' + tone : '') + '">' + esc(text) + '</span>'; }
  function pendingMark(row) { return row && row.pending ? ' <span class="toc-pending" title="Pas encore repris par le serveur du jeu">◷</span>' : ''; }
  // Pastilles d'état des nœuds, les mêmes partout : EN JEU (vert), ANCIEN (ambre), PERDU ou HORS LIGNE (gris).
  function statePill(t) {
    if (t.fresh === 'live') return pill('En jeu', 'accent');
    if (t.fresh === 'old') return pill('Ancien', 'amber');
    return pill(t.status === 'offline' ? 'Hors ligne' : 'Perdu', 'grey');
  }
  function groupPill(g) { return g.state === 'live' ? pill('En jeu', 'accent') : (g.state === 'old' ? pill('Ancien', 'amber') : pill('Perdu', 'grey')); }
  function symHtml(t, size) {
    var affil = T.affiliation(t.side, S.side, t.hostile);
    return '<span class="toc-rowsym">' + window.TocOverlay.natoSymbol(BOOT.symbols, t.kind, affil, { size: size || 15, lost: t.fresh === 'lost' }).html + '</span>';
  }
  function filterBox(key, placeholder) {
    return '<div class="toc-filter"><input class="toc-input" name="q-' + key + '" data-filter="' + key + '" value="' + esc(S.filter[key] || '') + '" placeholder="' + esc(placeholder) + '" aria-label="' + esc(placeholder) + '" autocomplete="off"></div>';
  }
  function matches(q, text) { return !q || String(text).toUpperCase().indexOf(String(q).toUpperCase().trim()) >= 0; }

  function tracks() { return S.layers ? S.layers.tracks() : []; }
  function myTracks() { return tracks().filter(function (t) { return !t.side || t.side === S.side; }); }
  function groups(only) { return S.layers ? S.layers.groups(only) : []; }
  function trackById(id) { var l = tracks(); for (var i = 0; i < l.length; i++) if (l[i].id === id) return l[i]; return null; }

  function panelGroupes() {
    var gs = groups();
    var live = gs.filter(function (g) { return g.state === 'live'; }).length;
    var h = sec('Groupes', [cnt(gs.length, 'groupe(s)'), cnt(live, 'en jeu')]);
    if (!gs.length) return h + (S.layers && S.layers.loading() ? '<p class="toc-note">Chargement des positions…</p>' : empty('Aucune position reçue sur ce terrain pour ce camp.', '<p class="toc-note">Les positions arrivent quand les joueurs ont un téléphone ATAK relié à Athena.</p>'));
    h += filterBox('grp', 'Filtrer les groupes…');
    var q = S.filter.grp;
    var rows = gs.filter(function (g) { return matches(q, g.name + ' ' + g.members.map(function (m) { return m.callsign; }).join(' ')); });
    h += '<ul class="toc-rows" role="listbox" aria-label="Groupes">' + rows.map(function (g) {
      var sub = g.single ? T.kindLabel(BOOT.symbols, g.kind) : '';
      return '<li role="option" tabindex="0" data-sel="grp" data-id="' + esc(g.name) + '" aria-selected="' + (S.sel.grp === g.name) + '" class="' + (S.sel.grp === g.name ? 'is-sel' : '') + (g.state === 'lost' ? ' is-lost' : '') + '">' + symHtml(g.lead) +
        '<span class="toc-row-main"><b>' + esc(g.name) + '</b>' + (g.single ? (sub ? '<small>' + esc(sub) + '</small>' : '') : '<small>' + cnt(g.live + ' / ' + g.members.length, 'en jeu') + '</small>') + '</span>' +
        groupPill(g) + '<span class="toc-row-grid mono">' + (g.pos ? G.grid(g.pos[0], g.pos[1], 6) : '—') + '</span><span class="toc-row-age">' + (g.state === 'live' ? '' : esc(T.ageShort(g.age))) + '</span></li>';
    }).join('') + '</ul>';
    if (!rows.length) h += '<p class="toc-note">Aucun groupe ne correspond au filtre.</p>';
    return h + '<p class="toc-note">Un clic choisit le groupe et centre la carte. Clic droit sur la carte : menu des actions.</p>';
  }

  function panelOrbat() {
    // ORBAT : seuls les nœuds des joueurs et des IA comptent, pas les véhicules ni les aéronefs suivis.
    var gs = groups('unit');
    var n = gs.reduce(function (a, g) { return a + g.members.length; }, 0);
    var live = gs.reduce(function (a, g) { return a + g.live; }, 0);
    var h = sec('ORBAT du site', [cnt(n, 'membre(s)'), cnt(live, 'en jeu')]);
    if (!gs.length) return h + empty('L’ORBAT se remplit avec les positions reçues des joueurs.');
    h += filterBox('orbat', 'Filtrer par indicatif ou par groupe…');
    var q = S.filter.orbat;
    // Un en-tête de colonnes pour tout le tableau ; un groupe d'un seul membre tient sur une ligne.
    h += '<div class="toc-orbat" role="tree" aria-label="ORBAT"><div class="toc-orbat-head"><span>Indicatif</span><span>État</span><span>Grille</span></div>';
    var shown = 0;
    gs.forEach(function (g) {
      var mem = g.members.filter(function (u) { return matches(q, u.callsign + ' ' + g.name); });
      if (!mem.length) return;
      shown++;
      var row = function (u, indent) {
        return '<div class="toc-orbat-row' + (indent ? ' is-member' : '') + (S.pick === u.id ? ' is-sel' : '') + '" role="treeitem" tabindex="0" data-sel="unit" data-id="' + esc(u.id) + '">' + symHtml(u, 13) +
          '<span class="toc-row-main"><b>' + esc(u.callsign) + '</b>' + (!indent && g.name !== u.callsign ? '<small>' + esc(g.name) + '</small>' : '') + (u.ai ? '<small>Nœud IA</small>' : '') + '</span>' + statePill(u) +
          '<span class="toc-row-grid mono">' + (u.pos ? G.grid(u.pos[0], u.pos[1], 6) : '—') + '</span></div>';
      };
      if (g.single) { h += row(g.members[0], false); return; }
      var closed = !!S.orbatClosed[g.name] && !q;
      h += '<div class="toc-orbat-group" role="treeitem" aria-expanded="' + !closed + '" tabindex="0" data-do="orbat-toggle" data-id="' + esc(g.name) + '"><i class="toc-caret" aria-hidden="true"></i>' +
        '<span class="toc-row-main"><b>' + esc(g.name) + '</b></span><span class="toc-orbat-agg is-' + g.state + '">' + cnt(g.live + ' / ' + g.members.length, 'en jeu') + '</span><span class="toc-row-grid mono">' + (g.pos ? G.grid(g.pos[0], g.pos[1], 6) : '—') + '</span></div>';
      if (!closed) h += mem.map(function (u) { return row(u, true); }).join('');
    });
    h += '</div>';
    if (!shown) h += '<p class="toc-note">Aucun membre ne correspond au filtre.</p>';
    return h + '<p class="toc-note"><span>ORBAT prévu et comparé :</span> <a href="' + esc(BOOT.base + '/atak') + '">ATAK web › ORBAT</a></p>';
  }

  function panelFlux() {
    var items = S.layers ? S.layers.feed() : [];
    var h = sec('Flux du camp', [cnt(items.length, 'compte(s) rendu(s)'), txt('heures ZULU')]);
    if (!items.length) return h + empty('Aucun compte rendu reçu sur ce terrain.', '<p class="toc-note">SALUTE, MEDEVAC, TIC et SITREP envoyés des téléphones arrivent ici.</p>');
    return h + '<table class="toc-table"><thead><tr><th style="width:72px">Heure</th><th style="width:104px">Type</th><th>Émetteur</th><th class="num" style="width:96px">Grille</th></tr></thead><tbody>' +
      items.slice(0, 60).map(function (f) {
        var tone = /TIC|CONTACT|FLASH/.test(f.kind) ? 'red' : (/MEDEVAC|URG/.test(f.kind) ? 'amber' : '');
        return '<tr data-sel="feed" data-id="' + esc(f.id) + '" title="' + esc(f.text) + '"><td class="mono">' + esc(f.t) + '</td><td>' + (tone ? pill(f.kind, tone) : '<b>' + esc(f.kind) + '</b>') + '</td><td>' + esc(f.who) + '</td><td class="num">' + esc(f.pos ? G.grid(f.pos[0], f.pos[1], 6) : '') + '</td></tr>';
      }).join('') + '</tbody></table>';
  }

  function panelOrdres() {
    var items = S.layers ? S.layers.orders() : [];
    var h = sec('Ordres', [cnt(items.length, 'ordre(s)')]);
    h += '<div class="toc-buttons" style="--cols:2"><button type="button" class="toc-btn is-primary" data-do="open" data-win="ordre">Donner un ordre</button><button type="button" class="toc-btn" data-do="open" data-win="message">Message</button></div>';
    if (!items.length) return h + empty('Aucun ordre en cours sur ce terrain.');
    return h + '<table class="toc-table"><thead><tr><th>Ordre</th><th style="width:30%">Pour</th><th class="num" style="width:26%">Statut</th></tr></thead><tbody>' + items.map(function (o) {
      return '<tr title="' + esc(o.text) + '"><td>' + esc(o.label) + '</td><td>' + esc(o.target) + '</td><td class="num">' + pill(o.status, o.raw === 'done' || o.raw === 'completed' || o.raw === 'acknowledged' ? 'accent' : '') + '</td></tr>';
    }).join('') + '</tbody></table><p class="toc-note">Un ordre arrive sur les téléphones et sur le PC du poste en jeu.</p>';
  }

  function panelCamp() {
    var cp = S.layers ? S.layers.campPlan(S.side) : null;
    var h = sec('Plan du camp', cp ? [cnt(cp.items.length, 'élément(s)'), '<span class="mono">' + esc(cp.updated_at || '') + '</span>'] : 'dessiné en jeu');
    if (!cp || !cp.items.length) return h + empty('Le plan du camp est vide : il se dessine dans l’app Plan du téléphone ou sur le PC du poste (outil PLAN).', '<a class="toc-btn" href="' + esc(BOOT.base + '/atak/toc/plans') + '">Préparer un plan web</a>');
    var counts = {};
    cp.items.forEach(function (it) { counts[it.kind] = (counts[it.kind] || 0) + 1; });
    return h + '<table class="toc-table"><thead><tr><th>Type</th><th class="num">Nombre</th></tr></thead><tbody>' + Object.keys(counts).sort().map(function (k) {
      return '<tr><td>' + esc(k) + '</td><td class="num">' + counts[k] + '</td></tr>';
    }).join('') + '</tbody></table><p class="toc-note">Calque « Plan du camp » sur la carte. Dans un plan web : « Importer depuis le jeu ».</p>';
  }

  function panelReseau() {
    var all = myTracks().filter(function (t) { return t.src === 'unit'; });
    var c = { live: 0, old: 0, lost: 0, off: 0 };
    all.forEach(function (u) { c[u.fresh === 'live' ? 'live' : u.fresh === 'old' ? 'old' : (u.status === 'offline' ? 'off' : 'lost')] += 1; });
    var relays = S.layers ? S.layers.count('relays') : 0;
    var h = sec('Réseau', [cnt(all.filter(function (u) { return !u.ai; }).length, 'joueur(s)'), cnt(all.filter(function (u) { return u.ai; }).length, 'nœud(s) IA'), cnt(relays, 'Relais')]);
    h += '<div class="toc-kpis"><div class="toc-kpi is-accent"><b>' + c.live + '</b><span>en jeu</span></div><div class="toc-kpi is-amber"><b>' + c.old + '</b><span>Anciens</span></div><div class="toc-kpi"><b>' + c.lost + '</b><span>Perdus</span></div><div class="toc-kpi"><b>' + c.off + '</b><span>hors ligne</span></div></div>';
    if (!all.length) return h + empty('Aucun nœud ATAK reçu.');
    // Un nœud par ligne (jamais le groupe répété) : état de la liaison, âge du dernier relevé, batterie.
    var rank = { live: 0, old: 1, lost: 2 };
    var rows = all.slice().sort(function (a, b) { return rank[a.fresh] - rank[b.fresh] || a.callsign.localeCompare(b.callsign, undefined, { numeric: true }); });
    h += '<table class="toc-table"><thead><tr><th>Nœud</th><th style="width:108px">Liaison</th><th class="num" style="width:104px">Dernier contact</th><th class="num" style="width:72px">Batterie</th></tr></thead><tbody>' + rows.map(function (u) {
      return '<tr data-sel="unit" data-id="' + esc(u.id) + '"><td><b>' + esc(u.callsign) + '</b></td><td>' + statePill(u) + '</td><td class="num">' + esc(T.ageShort(u.age) || '—') + '</td><td class="num">' + (u.battery != null ? u.battery + ' %' : '—') + '</td></tr>';
    }).join('') + '</tbody></table>';
    return h + '<h3 class="toc-sec"><span>Actions du poste web</span></h3><div id="toc-journal" class="toc-note">…</div>';
  }

  function panelRadio() {
    return sec('Radio', 'en jeu seulement') + empty('La radio se lit sur le PC du poste en jeu (ACRE2 ou TFAR, poste local) : rien n’en remonte au site.', '<button type="button" class="toc-btn" data-do="open" data-win="message">Écrire un message</button>');
  }

  function panelPlans() {
    var h = sec('Plans web', S.plansLoaded ? [cnt(S.plans.length, 'plan(s)')] : 'Chargement…');
    var cur = S.layers ? S.layers.shownPlan() : null;
    if (S.plansLoaded && !S.plans.length) return h + empty('Aucun plan d’opération pour l’instant.', '<a class="toc-btn is-primary" href="' + esc(BOOT.base + '/atak/toc/plans') + '">Créer un plan</a>');
    h += '<ul class="toc-list">' + S.plans.map(function (p) {
      return '<li data-sel="plan" data-id="' + p.id + '" class="' + (S.sel.plan === p.id ? 'is-sel' : '') + '"><span>' + esc(p.title) + ' <span class="toc-muted toc-dots"><span>' + esc(p.world || '') + '</span>' + cnt(p.elements, 'élément(s)') + '</span></span><span class="toc-muted">' + (cur === p.id ? 'Affiché' : (p.published_at ? 'en jeu' : '')) + '</span></li>';
    }).join('') + '</ul>';
    var sel = S.plans.filter(function (p) { return p.id === S.sel.plan; })[0];
    if (sel) {
      h += '<div class="toc-card"><h3>' + esc(sel.title) + '</h3><p class="toc-dots">' + cnt(sel.elements, 'élément(s)') + '<span><span>Révision du plan</span> ' + sel.rev + '</span>' + (sel.published_at ? '<span>' + esc('envoyé en jeu (' + sel.published_side + ') le ' + sel.published_at + '') + '</span>' : '') + '</p>' +
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
    var h = sec('Écrans de la salle', [cnt(scr.length, 'écran(s)')]);
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
    var h = sec('Chronos', [cnt(ts.length, 'chrono(s) du poste')]);
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
    var h = sec('Main courante', [cnt(log.length, 'entrée(s)'), txt('heures ZULU')]);
    h += '<form class="toc-form" data-form="log"><textarea class="toc-textarea" name="text" rows="2" maxlength="200" placeholder="Entrée de la main courante…" aria-label="Entrée"></textarea><button class="toc-btn is-primary is-block" type="submit">Inscrire <kbd>Ctrl+Entrée</kbd></button></form>';
    if (!log.length) return h + empty('La main courante est vide : comptes rendus, demandes, FLASH et EXCHECK s’y inscrivent seuls.');
    return h + '<table class="toc-table"><thead><tr><th style="width:100px">Heure Z</th><th style="width:112px">Type</th><th>Entrée</th></tr></thead><tbody>' + log.slice(0, 150).map(function (l) {
      return '<tr title="' + esc(l.author) + '"><td class="mono">' + esc(l.t) + '</td><td>' + pill(l.kind, logTone(l.kind)) + '</td><td class="wrap">' + esc(l.text) + ' <span class="toc-muted">· ' + esc(l.author) + '</span>' + pendingMark(l) + '</td></tr>';
    }).join('') + '</tbody></table>';
  }

  function panelExcheck() {
    var ex = (S.room && S.room.excheck) || [];
    var done = ex.filter(function (e) { return e.done; }).length;
    var h = sec('EXCHECK', [cnt(done + ' / ' + ex.length, 'passé(s)')]);
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
    h += '<table class="toc-table"><thead><tr><th style="width:72px">Heure</th><th style="width:124px">Type</th><th>Demandeur</th><th class="num" style="width:168px">Statut</th></tr></thead><tbody>' + rq.slice().reverse().map(function (r) {
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

  var PANELS = {
    groupes: panelGroupes, orbat: panelOrbat, flux: panelFlux, ordres: panelOrdres, camp: panelCamp, reseau: panelReseau, radio: panelRadio, plans: panelPlans,
    sat: panelSat, salle: panelSalle, chronos: panelChronos, main: panelMain, excheck: panelExcheck, pertes: panelPertes, demandes: panelDemandes
  };
  function renderPanel() {
    var body = $('#toc-panel');
    var act0 = document.activeElement && body.contains(document.activeElement) ? document.activeElement : null;
    var focusName = act0 ? act0.getAttribute('name') : null;
    var caret = act0 && focusName && act0.selectionStart != null ? act0.selectionStart : null;
    var scroll = body.scrollTop;
    var keep = {};
    body.querySelectorAll('input[name],textarea[name],select[name]').forEach(function (i) { if (i.value) keep[i.closest('form') ? i.closest('form').getAttribute('data-form') + ':' + i.name : i.name] = i.value; });
    setPref('tab:' + TAB_GROUP[S.tab], S.tab);
    body.innerHTML = (PANELS[S.tab] || panelGroupes)();
    body.querySelectorAll('input[name],textarea[name],select[name]').forEach(function (i) {
      var k = (i.closest('form') ? i.closest('form').getAttribute('data-form') + ':' : '') + i.name;
      if (keep[k] != null && i.type !== 'number' && !i.hasAttribute('data-filter')) i.value = keep[k];
    });
    if (focusName) { var f = body.querySelector('[name="' + focusName + '"]'); if (f) { f.focus(); if (caret != null && f.setSelectionRange) { try { f.setSelectionRange(caret, caret); } catch (e) {} } } }
    body.scrollTop = scroll;
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

  /* ---------- Fiche de l'élément choisi sur la carte ---------- */
  function pickItem() { return S.pick && S.layers ? S.layers.overlay.get(S.pick) : null; }
  function setPick(id, center) {
    var ov = S.layers && S.layers.overlay;
    if (!ov) return;
    S.pick = id && ov.get(id) ? id : null;
    ov.select(S.pick);
    var it = pickItem();
    if (it && center) S.tm.centerOn(it.pos, Math.max(S.tm.map.getZoom(), S.tm.map.getMaxZoom() - 3));
    if (it && it.data && it.data.track) S.sel.grp = it.data.track.group || it.data.track.callsign;
    renderPick();
    if (S.tab === 'groupes' || S.tab === 'orbat') renderPanel();
  }
  function canEditMarker(d) { return !!(d && d.type === 'marker' && String(d.id).indexOf('athena:') !== 0 && (d.own || BOOT.canManage)); }
  function renderPick() {
    var box = $('#toc-pick');
    var it = pickItem();
    if (!it) { box.hidden = true; box.innerHTML = ''; return; }
    var d = it.data || {};
    var following = S.layers.overlay.following() === it.id;
    var btns = '<button type="button" class="toc-btn is-small is-primary" data-pick="center">Centrer</button>';
    if (d.type === 'track') {
      btns += '<button type="button" class="toc-btn is-small' + (following ? ' is-on' : '') + '" data-pick="follow" aria-pressed="' + following + '">' + (following ? 'Ne plus suivre' : 'Suivre') + '</button>' +
        '<button type="button" class="toc-btn is-small" data-pick="message">Message</button><button type="button" class="toc-btn is-small" data-pick="order">Ordre</button>';
    } else if (canEditMarker(d)) {
      btns += '<button type="button" class="toc-btn is-small" data-pick="edit">Modifier</button><button type="button" class="toc-btn is-small is-danger" data-pick="delete">Supprimer</button>';
    }
    box.innerHTML = '<div class="toc-pick-head"><span class="toc-pick-sym">' + it.symbol.html + '</span><div class="toc-pick-body">' + (it.tip ? it.tip(it) : '<h4>' + esc(it.title) + '</h4>') + '</div><button type="button" class="toc-btn is-small is-icon" data-pick="close" aria-label="Fermer la fiche" title="Fermer la fiche">×</button></div><div class="toc-pick-actions">' + btns + '</div>';
    box.hidden = false;
  }
  function onPickClick(e) {
    var b = e.target.closest('[data-pick]');
    var it = pickItem();
    if (!b || !it) return;
    var a = b.getAttribute('data-pick');
    if (a === 'close') setPick(null);
    else runAction(a, it, it.pos);
  }

  /* ---------- Actions communes (fiche, menu contextuel, raccourcis) ---------- */
  function runAction(a, it, pos) {
    var d = (it && it.data) || {};
    var t = d.track;
    if (a === 'center') S.tm.centerOn(pos, Math.max(S.tm.map.getZoom(), S.tm.map.getMaxZoom() - 3));
    else if (a === 'here') S.tm.map.panTo(S.tm.toLL(pos));
    else if (a === 'details' && it) setPick(it.id, false);
    else if (a === 'follow' && it) { var ov = S.layers.overlay; ov.follow(ov.following() === it.id ? null : it.id); renderPick(); say(ov.following() ? 'La carte suit ' + it.title + ' (déplacer la carte arrête le suivi).' : 'Suivi arrêté.'); }
    else if (a === 'message') openWindow('message', { pour: t ? (t.group || t.callsign) : '' });
    else if (a === 'order') openWindow('ordre', { pour: t ? (t.group || t.callsign) : '' });
    else if (a === 'trail') { var on = !S.layers.isOn('trails'); S.layers.setLayerOn('trails', on); say(on ? 'Traces affichées (depuis l’ouverture du poste).' : 'Traces masquées.'); }
    else if (a === 'orbat') { if (t) S.filter.orbat = t.group || t.callsign; setTab('orbat'); }
    else if (a === 'copy') copyText(G.grid(pos[0], pos[1], 8), 'Grille copiée : ' + G.grid(pos[0], pos[1], 8) + '.');
    else if (a === 'link') copyText(window.location.origin + window.location.pathname + '#carte=' + Math.round(pos[0]) + ',' + Math.round(pos[1]), 'Lien copié : il ouvre le poste centré sur ce point.');
    else if (a === 'ping') ping(pos);
    else if (a === 'marker') openWindow('marqueur', { x: Math.round(pos[0]), y: Math.round(pos[1]) });
    else if (a === 'waypoint') openWindow('point', { x: Math.round(pos[0]), y: Math.round(pos[1]) });
    else if (a === 'edit' && d.type === 'marker') openWindow('marqueur', { id: d.id });
    else if (a === 'delete' && d.type === 'marker') deleteMarker(it);
    else if (a === 'measure') startMeasure(pos);
    else if (a.indexOf('ring:') === 0) { var r = Number(a.slice(5)); S.layers.rangeRing(r ? pos : null, r); }
    else if (a.indexOf('report:') === 0) quickMarker(pos, a.slice(7));
    else if (a === 'plan-add') addToPlan(pos);
    else if (a === 'plan-hide') { S.layers.showPlan(null); if (S.tab === 'plans') renderPanel(); }
    else if (a === 'plan-edit') window.location.href = BOOT.base + '/atak/toc/plans/' + S.layers.shownPlan();
    else if (a === 'zoom' && it) S.tm.centerOn(pos, S.tm.map.getZoom() + 2);
  }

  /* Écritures par les API ATAK existantes (mêmes droits et même cloisonnement que la carte ATAK web), jeton CSRF joint. */
  function ping(pos) {
    api('/api/pings', { method: 'POST', body: { mapId: S.mapId, author: BOOT.operator || 'TOC', pos_x: pos[0], pos_y: pos[1], message: '[Info] TOC' } }).then(function (j) {
      say(j && j.ok !== false ? 'Ping envoyé : ' + G.grid(pos[0], pos[1], 8) + '.' : 'Ping refusé.', !(j && j.ok !== false));
    }).catch(function () { say('Réseau indisponible : action non envoyée.', true); });
  }
  var QUICK = { hostile: ['o_unknown', 'ColorOPFOR', 'HOSTILE'], friendly: ['b_unknown', 'ColorBLUFOR', 'AMI'], unknown: ['hd_unknown', 'ColorUNKNOWN', 'INCONNU'] };
  function saveMarker(id, data) {
    var path = id ? '/api/markers/' + encodeURIComponent(id) : '/api/markers';
    return api(path, { method: id ? 'PATCH' : 'POST', body: { mapId: S.mapId, layerId: 1, markerData: data } }).then(function (j) {
      var ok = j && j.ok !== false && !j.error;
      if (ok) S.layers.reload('markers');
      return ok;
    }).catch(function () { return false; });
  }
  function quickMarker(pos, kind) {
    var q = QUICK[kind];
    if (!q) return;
    var now = new Date(serverNow() * 1000);
    saveMarker(null, { pos: [pos[0], pos[1]], type: q[0], color: q[1], text: q[2] + ' ' + two(now.getUTCHours()) + two(now.getUTCMinutes()) + 'Z', label: q[2] + ' ' + two(now.getUTCHours()) + two(now.getUTCMinutes()) + 'Z', author: BOOT.operator || 'TOC', source: 'web' }).then(function (ok) {
      say(ok ? 'Marqueur posé : il part aussi en jeu.' : 'Marqueur refusé.', !ok);
    });
  }
  function deleteMarker(it) {
    if (!canEditMarker(it.data)) { say('Seul son auteur ou un responsable du poste peut retirer ce marqueur.', true); return; }
    if (!window.confirm('Supprimer le marqueur « ' + (it.title || 'sans nom') + ' » ? Il est aussi retiré en jeu.')) return;
    api('/api/markers/' + encodeURIComponent(it.data.id), { method: 'DELETE' }).then(function (j) {
      var ok = j && j.ok !== false && !j.error;
      if (ok) { if (S.pick === it.id) setPick(null); S.layers.reload('markers'); }
      say(ok ? 'Marqueur supprimé.' : 'Suppression refusée.', !ok);
    }).catch(function () { say('Réseau indisponible : action non envoyée.', true); });
  }
  function addToPlan(pos) {
    var id = S.layers.shownPlan();
    if (!id) { say('Affichez d’abord un plan (Plans › Plans web).', true); return; }
    var el = { uid: G.uid('e'), geom: 'point', kind: 'checkpoint', pts: [[Math.round(pos[0]), Math.round(pos[1])]], props: { color: '#f2d36b', label: 'CP', size: 20 }, phases: [] };
    api('/atak/toc/api/plans/' + id + '/elements', { method: 'POST', body: { upserts: [el], deletes: [], base: {} } }).then(function (j) {
      var ok = j && j.ok;
      if (ok) S.layers.showPlan(id);
      say(ok ? 'Point ajouté au plan affiché.' : 'Ajout refusé : le plan est verrouillé ou en lecture seule.', !ok);
    }).catch(function () { say('Réseau indisponible : action non envoyée.', true); });
  }

  /* ---------- Menu contextuel de la carte (même ordre que l'ATAK en jeu : ping, marqueur, point, centrer, grille) ---------- */
  function ctxHeader(title, pos) {
    return '<b>' + esc(title) + '</b><span class="mono">' + G.grid(pos[0], pos[1], 8) + '</span>';
  }
  function openContext(clientX, clientY, pos, it) {
    var d = (it && it.data) || {};
    var entries = [];
    var title;
    var rings = [{ id: 'ring:100', label: '100 m' }, { id: 'ring:300', label: '300 m' }, { id: 'ring:500', label: '500 m' }, { id: 'ring:1000', label: '1 km' }, { id: 'ring:0', label: 'Effacer le cercle' }];
    if (d.cluster) {
      title = it.title;
      entries = [{ id: 'zoom', label: 'Rapprocher' }, { id: 'copy', label: 'Copier la grille' }];
    } else if (d.type === 'track') {
      var t = d.track;
      var following = S.layers.overlay.following() === it.id;
      title = t.callsign;
      entries = [
        { id: 'details', label: 'Fiche détaillée' }, { id: 'center', label: 'Centrer' }, { id: 'follow', label: following ? 'Ne plus suivre' : 'Suivre' },
        { sep: true }, { id: 'message', label: 'Message…' }, { id: 'order', label: 'Ordre…' },
        { sep: true }, { id: 'trail', label: S.layers.isOn('trails') ? 'Masquer les traces' : 'Afficher les traces' }, t.air ? null : { id: 'orbat', label: 'Voir dans l’ORBAT' },
        { id: 'measure', label: 'Mesurer d’ici' }, { id: 'ring', label: 'Cercle de portée', sub: rings }, { id: 'copy', label: 'Copier la grille' }
      ];
    } else if (d.type === 'plan') {
      title = 'Plan affiché';
      entries = [{ id: 'plan-edit', label: 'Ouvrir dans l’éditeur' }, { id: 'plan-hide', label: 'Masquer le plan' }, { sep: true }, { id: 'here', label: 'Centrer ici' }, { id: 'copy', label: 'Copier la grille' }];
    } else if (it) {
      title = it.title || 'Repère';
      entries = [{ id: 'details', label: 'Fiche détaillée' }, { id: 'center', label: 'Centrer' }];
      if (d.type === 'marker') {
        var can = canEditMarker(d);
        var why = can ? '' : 'Réservé à son auteur ou à un responsable du poste';
        entries.push({ sep: true }, { id: 'edit', label: 'Modifier…', disabled: !can, hint: why }, { id: 'delete', label: 'Supprimer', danger: true, disabled: !can, hint: why });
      }
      entries.push({ sep: true }, { id: 'link', label: 'Partager (copier le lien)' }, { id: 'measure', label: 'Mesurer d’ici' }, { id: 'copy', label: 'Copier la grille' });
    } else {
      title = 'Carte';
      entries = [
        { id: 'ping', label: 'Ping' }, { id: 'marker', label: 'Marqueur…' },
        { id: 'report', label: 'Signaler', sub: [{ id: 'report:hostile', label: 'Hostile' }, { id: 'report:friendly', label: 'Ami' }, { id: 'report:unknown', label: 'Inconnu' }] },
        { id: 'waypoint', label: 'Point de passage…' },
        { sep: true }, { id: 'here', label: 'Centrer ici' }, { id: 'copy', label: 'Copier la grille' },
        { sep: true }, { id: 'measure', label: 'Mesurer d’ici', kbd: 'M' }, { id: 'ring', label: 'Cercle de portée', sub: rings },
        { id: 'plan-add', label: 'Ajouter au plan affiché', disabled: !S.layers.shownPlan(), hint: S.layers.shownPlan() ? '' : 'Affichez d’abord un plan (Plans › Plans web)' },
        { id: 'link', label: 'Partager (copier le lien)' }
      ];
    }
    UI.menu.open(clientX, clientY, ctxHeader(title, pos), entries.filter(Boolean), function (id) { runAction(id, it, pos); });
  }

  /* ---------- Fenêtres de la console (lien #fenetre=…) ---------- */
  function hashParams() {
    var out = {};
    String(window.location.hash || '').replace(/^#/, '').split('&').forEach(function (kv) {
      var i = kv.indexOf('=');
      if (i > 0) { try { out[decodeURIComponent(kv.slice(0, i))] = decodeURIComponent(kv.slice(i + 1)); } catch (e) {} }
    });
    return out;
  }
  function setHash(params) {
    var parts = Object.keys(params || {}).filter(function (k) { return params[k] !== '' && params[k] != null; }).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); });
    try { window.history.replaceState(null, '', window.location.pathname + window.location.search + (parts.length ? '#' + parts.join('&') : '')); } catch (e) {}
  }
  function field(label, html) { return '<label class="toc-field"><span>' + esc(label) + '</span>' + html + '</label>'; }
  function recipientOptions(sel) {
    var seen = {};
    var h = '<option value="">Tous les éléments</option>';
    groups().forEach(function (g) {
      seen[g.name] = true;
      h += '<option value="' + esc(g.name) + '"' + (sel === g.name ? ' selected' : '') + '>' + esc(g.name) + '</option>';
      if (!g.single) g.members.forEach(function (m) { if (!seen[m.callsign]) { seen[m.callsign] = true; h += '<option value="' + esc(m.callsign) + '"' + (sel === m.callsign ? ' selected' : '') + '>' + esc(g.name + ' › ' + m.callsign) + '</option>'; } });
    });
    if (sel && !seen[sel]) h += '<option value="' + esc(sel) + '" selected>' + esc(sel) + '</option>';
    return h;
  }
  var MARKER_TYPES = [
    ['mil_dot', 'Point'], ['mil_objective', 'Objectif'], ['mil_flag', 'Drapeau'], ['mil_marker', 'Repère'], ['mil_warning', 'Danger'], ['mil_destroy', 'Détruire'],
    ['mil_ambush', 'Embuscade'], ['mil_pickup', 'Point de récupération'], ['mil_start', 'Départ'], ['mil_end', 'Arrivée'], ['mil_join', 'Jonction'], ['mil_unknown', 'Inconnu'],
    ['b_inf', 'Infanterie amie'], ['b_armor', 'Blindés amis'], ['b_air', 'Hélicoptère ami'], ['o_inf', 'Infanterie hostile'], ['o_armor', 'Blindés hostiles'], ['o_air', 'Hélicoptère hostile']
  ];
  var MARKER_COLORS = [['ColorBlue', 'Bleu'], ['ColorRed', 'Rouge'], ['ColorGreen', 'Vert'], ['ColorYellow', 'Jaune'], ['ColorOrange', 'Orange'], ['ColorWhite', 'Blanc'], ['ColorBlack', 'Noir']];
  var WP_TYPES = [['CHECKPOINT', 'Point de contrôle'], ['RALLY_POINT', 'Point de ralliement'], ['OBJECTIVE', 'Objectif'], ['LZ', 'Zone de poser'], ['DZ', 'Zone de largage'], ['OVERWATCH', 'Poste d’observation'], ['ASSAULT_POSITION', 'Position d’assaut'], ['OTHER', 'Autre']];

  var WINDOWS = {
    message: function (p) {
      return {
        title: 'Message', sub: 'Écrit aux téléphones et au fil des transmissions', fullHref: BOOT.base + '/atak', fullLabel: 'Ouvrir les transmissions dans ATAK web',
        body: '<form class="toc-form">' + field('Destinataire', '<select class="toc-select" name="to">' + recipientOptions(p.pour || '') + '</select>') +
          field('Message', '<textarea class="toc-textarea" name="text" rows="3" maxlength="300" required autofocus placeholder="Texte du message…"></textarea>') +
          '<button class="toc-btn is-primary is-block" type="submit">Envoyer</button><p class="toc-drawer-msg" role="status"></p></form><div class="toc-sec"><h3>Derniers messages</h3><span></span></div><div id="win-chat" class="toc-note">Chargement…</div>',
        onOpen: loadChat,
        onSubmit: function (form) {
          var to = form.elements.to.value, text = form.elements.text.value.trim();
          if (!text) return;
          api('/api/chat', { method: 'POST', body: { mapId: S.mapId, author: 'TOC ' + (BOOT.operator || ''), body: (to ? to + ', ici TOC : ' : 'À tous, ici TOC : ') + text } }).then(function (j) {
            var ok = j && j.ok !== false && !j.error;
            UI.drawer.message(ok ? 'Message envoyé.' : 'Message refusé.', !ok);
            if (ok) { form.elements.text.value = ''; loadChat(); }
          }).catch(function () { UI.drawer.message('Réseau indisponible : message non envoyé.', true); });
        }
      };
    },
    ordre: function (p) {
      return {
        title: 'Donner un ordre', sub: 'Il arrive sur les téléphones et sur le PC du poste en jeu', fullHref: BOOT.base + '/atak', fullLabel: 'Ordres avancés (FRAGO, modèles) dans ATAK web',
        body: '<form class="toc-form">' + field('Ordre', '<select class="toc-select" name="type"><option value="MOVE">Se déplacer</option><option value="HOLD">Tenir la position</option><option value="RECON">Reconnaissance</option><option value="CAS">Appui aérien</option><option value="QRF">Force de réaction</option></select>') +
          field('Destinataire', '<select class="toc-select" name="target" data-want="' + esc(p.pour || '') + '"><option value="all|">Toute l’équipe</option></select>') +
          field('Priorité', '<select class="toc-select" name="priority"><option value="ROUTINE">Routine</option><option value="IMPORTANT" selected>Important</option><option value="URGENT">Urgent</option></select>') +
          field('Consigne', '<textarea class="toc-textarea" name="text" rows="3" maxlength="400" autofocus placeholder="Précisions de l’ordre (grille, délai, effet attendu)…"></textarea>') +
          '<button class="toc-btn is-primary is-block" type="submit">Émettre l’ordre</button><p class="toc-drawer-msg" role="status"></p></form>',
        onOpen: loadRecipients,
        onSubmit: function (form) {
          var tv = form.elements.target.value.split('|');
          var opt = form.elements.target.selectedOptions[0];
          var ty = form.elements.type;
          api('/api/atak/orders', { method: 'POST', body: { mapId: S.mapId, order_type: ty.value, type_label: ty.selectedOptions[0].getAttribute('data-label') || '', target_type: tv[0], target_ref: tv.slice(1).join('|'), target_label: opt ? opt.textContent : '', payload: form.elements.text.value.trim(), priority: form.elements.priority.value } }).then(function (j) {
            if (j && j.ok) { S.layers.reload('orders'); closeWindow(); say('Ordre émis.'); if (S.tab === 'ordres') renderPanel(); } else UI.drawer.message((j && j.message) || 'Ordre refusé.', true);
          }).catch(function () { UI.drawer.message('Réseau indisponible : ordre non envoyé.', true); });
        }
      };
    },
    marqueur: function (p) {
      var it = p.id ? S.layers.overlay.get('mk' + p.id) : null;
      var d = it && it.data ? it.data.d : null;
      if (p.id && !d) return null;
      var pos = d ? it.pos : [Number(p.x), Number(p.y)];
      if (!isFinite(pos[0]) || !isFinite(pos[1])) return null;
      var curType = d ? String(d.type || 'mil_dot').toLowerCase() : 'mil_dot';
      var curCol = d ? String(d.color || 'ColorYellow') : 'ColorYellow';
      var types = MARKER_TYPES.slice();
      if (!types.some(function (t) { return t[0] === curType; })) types.unshift([curType, 'Type actuel']);
      return {
        title: d ? 'Modifier le marqueur' : 'Poser un marqueur', sub: 'Grille ' + G.grid(pos[0], pos[1], 8) + '',
        body: '<form class="toc-form">' + field('Texte', '<input class="toc-input" name="text" maxlength="40" autofocus value="' + esc(d ? (d.text || d.label || '') : '') + '" placeholder="' + esc('Texte affiché sur la carte…') + '">') +
          '<fieldset class="toc-picker"><legend>Symbole</legend>' + types.map(function (t) {
            var spec = T.markerSpec(BOOT.symbols, t[0]);
            var svg = spec.nato ? window.TocOverlay.natoSymbol(BOOT.symbols, spec.nato, spec.affil, { size: 15 }).html : T.iconSvg(spec.icon, '#e8eef2', 22);
            return '<label title="' + esc(t[1]) + '"><input type="radio" name="type" value="' + esc(t[0]) + '"' + (t[0] === curType ? ' checked' : '') + '><span>' + svg + '<small>' + esc(t[1]) + '</small></span></label>';
          }).join('') + '</fieldset>' +
          '<fieldset class="toc-picker is-colors"><legend>Couleur</legend>' + MARKER_COLORS.map(function (c) {
            return '<label title="' + esc(c[1]) + '"><input type="radio" name="color" value="' + c[0] + '"' + (c[0].toLowerCase() === curCol.toLowerCase() ? ' checked' : '') + '><span><i style="background:' + T.markerColor(c[0]) + '"></i><small>' + esc(c[1]) + '</small></span></label>';
          }).join('') + '</fieldset>' +
          '<button class="toc-btn is-primary is-block" type="submit">' + (d ? 'Enregistrer' : 'Poser le marqueur') + '</button><p class="toc-drawer-msg" role="status"></p><p class="toc-note">Le marqueur part aussi en jeu, sur la carte et les téléphones du camp.</p></form>',
        onSubmit: function (form) {
          var data = d ? JSON.parse(JSON.stringify(d)) : { pos: [pos[0], pos[1]], author: BOOT.operator || 'TOC', source: 'web' };
          data.type = (form.querySelector('[name="type"]:checked') || {}).value || 'mil_dot';
          data.color = (form.querySelector('[name="color"]:checked') || {}).value || data.color || 'ColorYellow';
          data.text = form.elements.text.value.trim();
          data.label = data.text;
          saveMarker(d ? p.id : null, data).then(function (ok) {
            if (ok) { closeWindow(); say(d ? 'Marqueur modifié.' : 'Marqueur posé : il part aussi en jeu.'); } else UI.drawer.message('Marqueur refusé.', true);
          });
        }
      };
    },
    point: function (p) {
      var pos = [Number(p.x), Number(p.y)];
      if (!isFinite(pos[0]) || !isFinite(pos[1])) return null;
      return {
        title: 'Point de passage', sub: 'Grille ' + G.grid(pos[0], pos[1], 8) + '',
        body: '<form class="toc-form">' + field('Nom', '<input class="toc-input" name="label" maxlength="40" autofocus required placeholder="RP NORD, LZ ALPHA…">') +
          field('Genre', '<select class="toc-select" name="type">' + WP_TYPES.map(function (t) { return '<option value="' + t[0] + '">' + esc(t[1]) + '</option>'; }).join('') + '</select>') +
          field('Consigne au point', '<input class="toc-input" name="desc" maxlength="160" placeholder="' + esc('Précision facultative…') + '">') +
          '<button class="toc-btn is-primary is-block" type="submit">Créer le point</button><p class="toc-drawer-msg" role="status"></p><p class="toc-note">Le point apparaît dans le calque « Points et itinéraires » et dans ATAK web.</p></form>',
        onSubmit: function (form) {
          api('/api/atak/waypoints?mapId=' + S.mapId, { method: 'POST', body: { mapId: S.mapId, label: form.elements.label.value.trim(), waypoint_type: form.elements.type.value, pos_x: pos[0], pos_y: pos[1], description: form.elements.desc.value.trim() } }).then(function (j) {
            if (j && j.ok) { S.layers.reload('routes'); closeWindow(); say('Point de passage créé.'); } else UI.drawer.message((j && typeof j.error === 'string' && j.error.indexOf(' ') > 0 ? j.error : '') || 'Point refusé.', true);
          }).catch(function () { UI.drawer.message('Réseau indisponible : point non créé.', true); });
        }
      };
    }
  };
  function loadChat() {
    api('/api/chat?mapId=' + S.mapId + '&limit=12').then(function (j) {
      var el = $('#win-chat');
      if (!el) return;
      var rows = Array.isArray(j) ? j : ((j && (j.messages || j.items)) || []);
      rows = rows.slice().sort(function (a, b) { return String(b.created_at || '').localeCompare(String(a.created_at || '')); }).slice(0, 12);
      el.innerHTML = rows.length ? '<ul class="toc-chat">' + rows.map(function (m) {
        return '<li><span class="mono">' + esc(String(m.created_at || '').slice(11, 16)) + '</span><b>' + esc(m.author || '') + '</b><span>' + esc(m.body || m.message || '') + '</span></li>';
      }).join('') + '</ul>' : 'Aucun message sur ce terrain.';
    }).catch(function () { var el = $('#win-chat'); if (el) el.textContent = 'Messages indisponibles pour l’instant.'; });
  }
  function loadRecipients(root) {
    var sel = root.querySelector('[name="target"]');
    var want = String(sel.getAttribute('data-want') || '').toUpperCase();
    api('/api/atak/orders/recipients?mapId=' + S.mapId).then(function (j) {
      if (!j || j.ok === false || !document.contains(sel)) return;
      var h = '<option value="all|">Toute l’équipe</option>';
      var picked = '';
      [['group', 'Groupes en jeu', j.groups], ['solo', 'Téléphones seuls', j.solos], ['fire_team', 'Équipes', j.fire_teams], ['user', 'Membres', j.users], ['ally', 'Unités alliées', j.allies]].forEach(function (cat) {
        var list = Array.isArray(cat[2]) ? cat[2] : [];
        if (!list.length) return;
        h += '<optgroup label="' + esc(cat[1]) + '">' + list.slice(0, 80).map(function (r) {
          var v = cat[0] + '|' + r.id;
          if (!picked && want && (String(r.label).toUpperCase() === want || String(r.id).toUpperCase() === want)) picked = v;
          return '<option value="' + esc(v) + '">' + esc(r.label) + '</option>';
        }).join('') + '</optgroup>';
      });
      sel.innerHTML = h;
      if (picked) sel.value = picked;
    }).catch(function () {});
    api('/api/atak/orders/types').then(function (j) {
      var ty = root.querySelector('[name="type"]');
      if (!j || !j.ok || !Array.isArray(j.types) || !j.types.length || !document.contains(ty)) return;
      ty.insertAdjacentHTML('beforeend', '<optgroup label="Ordres de la communauté">' + j.types.slice(0, 40).map(function (t) { return '<option value="' + esc(t.code || ('TYP_' + t.id)) + '" data-label="' + esc(t.label) + '">' + esc(t.label) + '</option>'; }).join('') + '</optgroup>');
    }).catch(function () {});
  }
  function openWindow(id, params) {
    var make = WINDOWS[id];
    var cfg = make ? make(params || {}) : null;
    if (!cfg) { setHash({}); return; }
    cfg.id = id;
    cfg.onClose = function () { setHash({}); };
    var hp = { fenetre: id };
    Object.keys(params || {}).forEach(function (k) { hp[k] = params[k]; });
    setHash(hp);
    UI.drawer.open(cfg);
  }
  function closeWindow() { UI.drawer.close(); }
  function applyHash() {
    var p = hashParams();
    if (p.carte && S.tm) {
      var c = p.carte.split(',').map(Number);
      if (isFinite(c[0]) && isFinite(c[1])) S.tm.centerOn([c[0], c[1]], Math.max(S.tm.map.getZoom(), S.tm.map.getMaxZoom() - 3));
    }
    if (p.fenetre && WINDOWS[p.fenetre] && UI.drawer.current() !== p.fenetre) {
      var id = p.fenetre;
      delete p.fenetre; delete p.carte;
      openWindow(id, p);
    }
  }

  /* ---------- Mesure ---------- */
  function startMeasure(from) {
    stopMeasure();
    S.measure = { pts: from ? [from] : [], layer: L.layerGroup().addTo(S.tm.map) };
    $('#toc-map').classList.add('is-measuring');
    $('[data-map="measure"]').setAttribute('aria-pressed', 'true');
    drawMeasure();
  }
  function stopMeasure() {
    if (!S.measure) return;
    S.tm.map.removeLayer(S.measure.layer);
    S.measure = null;
    $('#toc-map').classList.remove('is-measuring');
    $('[data-map="measure"]').setAttribute('aria-pressed', 'false');
    $('#toc-measure').hidden = true;
  }
  function drawMeasure(cursor) {
    var m = S.measure;
    if (!m) return;
    m.layer.clearLayers();
    var pts = cursor && m.pts.length ? m.pts.concat([cursor]) : m.pts;
    var box = $('#toc-measure');
    box.hidden = false;
    if (!m.pts.length) { box.innerHTML = '<span>Mesure : cliquez le premier point (Échap pour finir).</span>'; return; }
    m.layer.addLayer(L.polyline(pts.map(S.tm.toLL), { color: '#f5b841', weight: 2, dashArray: '6 4', interactive: false }));
    m.pts.forEach(function (p) { m.layer.addLayer(L.circleMarker(S.tm.toLL(p), { radius: 4, color: '#f5b841', weight: 2, fillColor: '#070a0d', fillOpacity: 1, interactive: false })); });
    if (pts.length < 2) { box.innerHTML = '<span>Mesure : cliquez le point suivant (Échap pour finir).</span>'; return; }
    var a = pts[pts.length - 2], b = pts[pts.length - 1];
    var az = G.azimuth(a, b);
    box.innerHTML = '<span>Distance</span> <b class="mono">' + esc(G.fmtDist(G.dist(a, b))) + '</b><span>Azimut</span> <b class="mono">' + esc(T.headingLabel(az)) + ' · ' + Math.round(az * 6400 / 360) + ' mil</b>' + (pts.length > 2 ? '<span>Total</span> <b class="mono">' + esc(G.fmtDist(G.pathLength(pts))) + '</b>' : '');
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

  /* ---------- Événements du panneau ---------- */
  function onPanelClick(e) {
    var row = e.target.closest('[data-sel]');
    var doBtn = e.target.closest('[data-do]');
    if (doBtn) {
      var d = doBtn.getAttribute('data-do');
      if (d === 'timer' && S.sel.timer) act(doBtn.getAttribute('data-a'), { id: S.sel.timer });
      else if (d === 'ex' && S.sel.ex) act(doBtn.getAttribute('data-a'), { id: S.sel.ex });
      else if (d === 'req-center') { var r = ((S.room && S.room.requests) || []).filter(function (x) { return x.id === S.sel.req; })[0]; if (r && r.pos) S.tm.centerOn(r.pos); }
      else if (d === 'sat-center') { var s = ((S.room && S.room.sats) || [])[Number(doBtn.getAttribute('data-n')) - 1]; if (s) S.tm.fitWorld([s.x - s.hw, s.y - s.hw, s.x + s.hw, s.y + s.hw]); }
      else if (d === 'plan-show' && S.layers) { S.layers.showPlan(S.layers.shownPlan() === S.sel.plan ? null : S.sel.plan); renderPanel(); }
      else if (d === 'plan-publish') publishPlan(S.sel.plan);
      else if (d === 'open') openWindow(doBtn.getAttribute('data-win'), {});
      else if (d === 'orbat-toggle') { var gname = doBtn.getAttribute('data-id'); S.orbatClosed[gname] = !S.orbatClosed[gname]; renderPanel(); }
      return;
    }
    if (!row) return;
    var kind = row.getAttribute('data-sel'), id = row.getAttribute('data-id');
    if (kind === 'unit') { var u = trackById(id); if (u && u.pos) setPick(u.id, true); else say('Pas de position connue pour ce nœud.', true); return; }
    if (kind === 'feed') { var f = (S.layers ? S.layers.feed() : []).filter(function (x) { return String(x.id) === id; })[0]; if (f && f.pos) { S.tm.centerOn(f.pos); if (S.layers.overlay.get('rp' + f.id)) setPick('rp' + f.id, false); } return; }
    if (kind === 'grp') {
      var gg = groups().filter(function (x) { return x.name === id; })[0];
      S.sel.grp = id;
      if (gg && gg.lead && gg.lead.pos) setPick(gg.lead.id, true); else { renderPanel(); say('Pas de position connue pour ce groupe.', true); }
      return;
    }
    if (kind === 'plan') id = Number(id);
    S.sel[kind] = S.sel[kind] === id && kind !== 'plan' ? null : id;
    renderPanel();
  }
  function publishPlan(id) {
    var p = S.plans.filter(function (x) { return x.id === id; })[0];
    if (!p) return;
    if (!window.confirm('Envoyer « ' + p.title + ' » dans le plan partagé du camp ' + SIDES[S.side] + ' ? Les éléments déjà envoyés par ce plan sont remplacés.')) return;
    api('/atak/toc/api/plans/' + id + '/publish', { method: 'POST', body: { side: S.side } }).then(function (j) {
      if (j && j.ok && j.skipped) say('Envoyé en jeu : ' + j.sent + ' élément(s), ' + j.skipped + ' trop détaillé(s) écarté(s).');
      else if (j && j.ok) say('Envoyé en jeu : ' + j.sent + ' élément(s).');
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

  /* ---------- Carte : commandes, recherche, clavier ---------- */
  function fitAll() {
    var pts = tracks().filter(function (t) { return t.pos; }).map(function (t) { return t.pos; });
    if (!pts.length) { say('Aucune piste à montrer sur ce terrain.', true); return; }
    var bb = G.bbox(pts);
    var pad = Math.max(300, (bb[2] - bb[0] + bb[3] - bb[1]) * 0.12);
    S.tm.fitWorld([bb[0] - pad, bb[1] - pad, bb[2] + pad, bb[3] + pad]);
  }
  function toggleFull() {
    var root = $('#toc-root');
    if (document.fullscreenElement) { if (document.exitFullscreen) document.exitFullscreen(); return; }
    if (root.requestFullscreen) root.requestFullscreen().catch(function () { say('Plein écran refusé par le navigateur.', true); });
  }
  function toggleLayers(force) { if (S.layers) S.layers.openPanel(force); }
  function mapCommand(c) {
    if (c === 'in') S.tm.map.zoomIn();
    else if (c === 'out') S.tm.map.zoomOut();
    else if (c === 'north') fitAll();
    else if (c === 'layers') toggleLayers();
    else if (c === 'measure') { if (S.measure) stopMeasure(); else startMeasure(null); }
    else if (c === 'full') toggleFull();
  }
  function cycleBackground() {
    var bgs = S.tm.backgrounds, cur = S.tm.currentBackground();
    var i = bgs.indexOf(cur);
    var next = bgs[(i + 1) % bgs.length];
    S.tm.setBackground(next.id);
    $('#toc-bg').value = next.id;
    setPref('bg', next.id);
  }

  var KIND_LABEL = { group: 'Groupe', track: 'Piste BFT', marker: 'Repère', grid: 'Grille' };
  function searchEntries() {
    var out = [];
    groups().forEach(function (g) { if (!g.single && g.pos) out.push({ label: g.name, sub: '× ' + g.members.length, kind: 'group', pos: g.pos, id: g.lead.id }); });
    tracks().forEach(function (t) { if (t.pos) out.push({ label: t.callsign, sub: t.group && t.group !== t.callsign ? t.group : T.kindLabel(BOOT.symbols, t.kind), kind: 'track', pos: t.pos, id: t.id }); });
    (S.layers ? S.layers.features() : []).forEach(function (f) { out.push({ label: f.title || f.find, sub: G.grid(f.pos[0], f.pos[1], 6), kind: 'marker', pos: f.pos, id: f.id }); });
    return out;
  }
  function renderSuggest() {
    var box = $('#toc-suggest'), inp = $('#toc-search');
    S.suggest = T.suggest(inp.value, searchEntries(), G.parseGrid, 8);
    if (S.suggestIdx >= S.suggest.length) S.suggestIdx = S.suggest.length - 1;
    box.hidden = !S.suggest.length && !inp.value.trim();
    inp.setAttribute('aria-expanded', String(!box.hidden));
    box.innerHTML = S.suggest.length ? S.suggest.map(function (s, i) {
      return '<li role="option" id="sg-' + i + '" data-sg="' + i + '" aria-selected="' + (i === S.suggestIdx) + '" class="' + (i === S.suggestIdx ? 'is-sel' : '') + '"><b>' + esc(s.label) + '</b><span class="toc-muted">' + esc(s.sub || '') + '</span><small>' + KIND_LABEL[s.kind] + '</small></li>';
    }).join('') : '<li class="is-empty">Rien ne correspond : essayez une grille à 6 ou 8 chiffres.</li>';
  }
  function goSuggest(i) {
    var s = S.suggest[i];
    if (!s) { if ($('#toc-search').value.trim()) say('Rien trouvé pour « ' + $('#toc-search').value.trim() + ' ».', true); return; }
    S.tm.centerOn(s.pos, Math.max(S.tm.map.getZoom(), S.tm.map.getMaxZoom() - 3));
    if (s.kind !== 'grid') setPick(s.id, false);
    say(s.label + ' · ' + G.grid(s.pos[0], s.pos[1], 8));
    $('#toc-suggest').hidden = true;
    $('#toc-search').setAttribute('aria-expanded', 'false');
    S.suggestIdx = -1;
  }

  function bindKeys() {
    document.addEventListener('keydown', function (e) {
      var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName);
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && e.target.name === 'text' && e.target.closest('form[data-form="log"]')) { e.preventDefault(); e.target.closest('form').requestSubmit(); return; }
      if (UI.menu.isOpen() || UI.drawer.isOpen()) return;
      if (e.key === 'Escape') {
        if (!$('#toc-help-overlay').hidden) { $('#toc-help-overlay').hidden = true; $('#toc-help-btn').focus(); return; }
        if (S.measure) { stopMeasure(); return; }
        if (!$('#toc-suggest').hidden) { $('#toc-suggest').hidden = true; return; }
        if (S.layers && !$('#toc-layers').hidden) { S.layers.openPanel(false); return; }
        if (S.pick) setPick(null);
        return;
      }
      // Menu de la carte au clavier : Maj + F10 ou touche Menu, sur l'élément choisi sinon au centre de la carte.
      if ((e.key === 'ContextMenu' || (e.key === 'F10' && e.shiftKey)) && (e.target.id === 'toc-map' || $('#toc-map').contains(e.target))) {
        e.preventDefault();
        var it = pickItem();
        var box = $('#toc-map').getBoundingClientRect();
        var pos = it ? it.pos : S.tm.toWorld(S.tm.map.getCenter());
        var pt = S.tm.map.latLngToContainerPoint(S.tm.toLL(pos));
        openContext(box.left + pt.x, box.top + pt.y, pos, it);
        return;
      }
      if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('[data-sel][tabindex],[data-do][tabindex]')) { e.preventDefault(); e.target.click(); return; }
      if (typing) return;
      if (e.key === '?') { e.preventDefault(); $('#toc-help-overlay').hidden = false; $('#toc-help-close').focus(); return; }
      if (e.altKey && /^[1-4]$/.test(e.key)) { e.preventDefault(); setGroup(NAV[Number(e.key) - 1][0]); return; }
      if (e.ctrlKey || e.metaKey || e.altKey) return;
      var k = e.key.toLowerCase();
      if (k === 'f') { e.preventDefault(); $('#toc-flash-input').focus(); }
      else if (k === 'l') toggleLayers();
      else if (k === 'g') { S.gridOn = !S.gridOn; S.tm.setGrid(S.gridOn); }
      else if (k === 'b') cycleBackground();
      else if (k === 'm') mapCommand('measure');
      else if (k === 't') fitAll();
      else if (k === 'p') toggleFull();
      else if (e.key === '+' || e.key === '=') S.tm.map.zoomIn();
      else if (e.key === '-') S.tm.map.zoomOut();
      else if (e.key === '/') { e.preventDefault(); $('#toc-search').focus(); }
    });
    var arrows = function (listEl, attr, go) {
      listEl.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
        var btns = Array.prototype.slice.call(listEl.querySelectorAll('[' + attr + ']'));
        var i = btns.indexOf(document.activeElement);
        if (i < 0) return;
        i = (i + (e.key === 'ArrowRight' ? 1 : -1) + btns.length) % btns.length;
        go(btns[i].getAttribute(attr));
        var again = listEl.querySelectorAll('[' + attr + ']')[i];
        if (again) again.focus();
      });
    };
    arrows($('#toc-tabs'), 'data-tab', setTab);
    arrows($('#toc-groups'), 'data-group', setGroup);
  }

  function buildMap() {
    var t = terrain();
    if (!t) { setBanner('Aucun terrain n’est configuré sur le site : le poste ne peut pas afficher de carte.'); return; }
    stopMeasure();
    if (S.layers) { S.layers.destroy(); S.layers = null; }
    if (S.tm) { S.tm.destroy(); S.tm = null; }
    S.isrCentered = false;
    S.pick = null;
    renderPick();
    S.tm = window.TocMap.create($('#toc-map'), t, {
      controls: false,
      onCursor: function (p) { $('#toc-cursor').textContent = G.grid(p[0], p[1], 8); if (S.measure && S.measure.pts.length) drawMeasure(p); },
      onBackground: function (bg) { $('#toc-bg-note').textContent = bg.note; }
    });
    var bg = pref('bg', 'sat');
    $('#toc-bg').innerHTML = S.tm.backgrounds.map(function (b) { return '<option value="' + b.id + '">' + esc(b.label) + '</option>'; }).join('');
    $('#toc-bg').value = bg;
    S.tm.setBackground(bg);
    S.gridOn = true;
    S.layers = window.TocLayers.create(S.tm, {
      base: BOOT.base, mapId: S.mapId, side: S.side, panel: $('#toc-layers'), mode: 'console', me: BOOT.operator, userId: BOOT.userId, symbols: BOOT.symbols,
      onData: function () {
        if (['groupes', 'orbat', 'flux', 'ordres', 'camp', 'reseau'].indexOf(S.tab) >= 0 && !(document.activeElement && document.activeElement.closest && document.activeElement.closest('#toc-panel form'))) renderPanel();
        if (S.pick) { if (pickItem()) renderPick(); else setPick(null); }
        renderStatus();
        renderLink();
        renderMapState();
      },
      onCounts: renderLayerCount,
      onPanel: function (open) { $('#toc-layers-btn').setAttribute('aria-expanded', String(open)); $('#toc-layers-btn').classList.toggle('is-on', open); },
      onSelect: function (it) { setPick(it.id, false); },
      onFollow: function () { renderPick(); },
      onLayout: function (st) {
        var el = $('#toc-declutter');
        el.textContent = st.hidden ? 'Plaques masquées faute de place : ' + st.hidden + ' (rapprochez).' : (st.mode === 'none' ? 'Vue d’ensemble : symboles seuls, rapprochez pour les indicatifs.' : '');
      },
      fetchPlan: function (id) { return api('/atak/toc/api/plans/' + id); }
    });
    var map = S.tm.map;
    map.on('contextmenu', function (e) {
      if (e.originalEvent) e.originalEvent.preventDefault();
      var it = S.layers.overlay.itemAt(e.originalEvent && e.originalEvent.target);
      var pos = it ? it.pos : S.tm.toWorld(e.latlng);
      // Clic droit sur un tracé du plan web affiché : menu du plan.
      if (!it && e.originalEvent && e.originalEvent.target && e.originalEvent.target.closest && S.layers.shownPlan() && e.originalEvent.target.closest('.leaflet-overlay-pane path')) it = { id: 'plan', title: 'Plan affiché', pos: pos, data: { type: 'plan' } };
      openContext(e.originalEvent ? e.originalEvent.clientX : 0, e.originalEvent ? e.originalEvent.clientY : 0, pos, it);
    });
    map.on('click', function (e) {
      if (!S.measure) return;
      S.measure.pts.push(S.tm.toWorld(e.latlng));
      drawMeasure();
    });
    if (S.openLayers) { S.openLayers = false; S.layers.openPanel(true); }
    renderMapState();
    applyHash();
  }
  // Jamais le menu du navigateur sur la carte (plaques et symboles compris).
  function blockNativeMenu(e) { if (e.target.closest && e.target.closest('#toc-map')) e.preventDefault(); }

  function renderLayerCount() {
    if (!S.layers) return;
    var n = S.layers.defs().filter(function (d) { return !d.unavailable && S.layers.isOn(d.id); }).length;
    $('#toc-layers-count').textContent = n;
  }
  function renderMapState() {
    var el = $('#toc-mapstate');
    if (!S.layers) { el.hidden = true; return; }
    var msg = '';
    if (S.layers.failed()) msg = 'Positions indisponibles pour l’instant : nouvelle tentative dans quelques secondes.';
    else if (S.layers.loading()) msg = 'Chargement des pistes…';
    else if (!tracks().some(function (t) { return t.pos; })) msg = 'Aucune piste reçue sur ce terrain pour ce camp.';
    el.textContent = msg;
    el.classList.toggle('is-error', S.layers.failed());
    el.hidden = !msg;
  }
  function spill(n, label, tone) { return '<span class="toc-spill' + (tone ? ' is-' + tone : '') + (n ? '' : ' is-zero') + '"><b>' + n + '</b><span>' + label + '</span></span>'; }
  function renderStatus() {
    var all = myTracks();
    var live = all.filter(function (u) { return u.fresh === 'live'; }).length;
    var old = all.filter(function (u) { return u.fresh === 'old'; }).length;
    var lost = all.length - live - old;
    var air = all.filter(function (u) { return u.air; }).length;
    var veh = all.filter(function (u) { return u.src === 'vehicle'; }).length;
    var L0 = S.layers;
    $('#toc-status-net').innerHTML = spill(live, 'en jeu', 'accent') + spill(old, 'Anciens', 'amber') + spill(lost, 'Perdus', 'grey') + spill(veh, 'Véhicules', '') + spill(air, 'Aéronefs', '') + spill(L0 ? L0.count('markers') : 0, 'Marqueurs', '') + spill(L0 ? L0.count('routes') : 0, 'Points de passage', '');
    var reqs = ((S.room && S.room.requests) || []).filter(function (r) { return r.status === 'RECUE'; }).length;
    var pend = S.game && S.game.pending ? S.game.pending : 0;
    $('#toc-status-room').innerHTML = spill(reqs, 'demandes à traiter', reqs ? 'red' : '') + (pend ? spill(pend, 'actions web en attente du jeu', 'amber') : '');
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
    renderStatus();
    tickClocks();
    setInterval(tickClocks, 1000);
    setInterval(renderLink, 15000);
    loadRoom();
    setInterval(function () { if (!document.hidden) loadRoom(); }, 3000);
    $('#toc-tabs').addEventListener('click', function (e) { var b = e.target.closest('[data-tab]'); if (b) setTab(b.getAttribute('data-tab')); });
    $('#toc-groups').addEventListener('click', function (e) { var b = e.target.closest('[data-group]'); if (b) setGroup(b.getAttribute('data-group')); });
    $('#toc-panel').addEventListener('click', onPanelClick);
    $('#toc-panel').addEventListener('submit', onPanelSubmit);
    $('#toc-panel').addEventListener('input', function (e) {
      var k = e.target.getAttribute && e.target.getAttribute('data-filter');
      if (!k) return;
      S.filter[k] = e.target.value;
      renderPanel();
    });
    $('#toc-pick').addEventListener('click', onPickClick);
    $('#toc-flash-btn').addEventListener('click', sendFlash);
    $('#toc-flash-input').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); sendFlash(); } });
    $('#toc-flash-clear').addEventListener('click', function () { act('flashClear', {}); });
    $('#toc-side').addEventListener('change', function () { S.side = this.value; setPref('side', S.side); S.roomVersion = ''; S.room = null; S.sel = {}; setPick(null); if (S.layers) S.layers.setSide(S.side); renderTop(); renderPanel(); renderStatus(); loadRoom(); });
    $('#toc-terrain').addEventListener('change', function () { S.mapId = Number(this.value); setPref('map', S.mapId); S.roomVersion = ''; S.room = null; S.plansLoaded = false; S.sel = {}; buildMap(); renderTop(); renderPanel(); renderStatus(); loadRoom(); });
    $('#toc-bg').addEventListener('change', function () { S.tm.setBackground(this.value); setPref('bg', this.value); });
    $('#toc-layers-btn').addEventListener('click', function () { toggleLayers(); });
    document.querySelector('.toc-mapctl').addEventListener('click', function (e) { var b = e.target.closest('[data-map]'); if (b) mapCommand(b.getAttribute('data-map')); });
    var search = $('#toc-search');
    search.addEventListener('input', function () { S.suggestIdx = -1; renderSuggest(); });
    search.addEventListener('focus', function () { if (search.value.trim()) renderSuggest(); });
    search.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (!S.suggest.length) return;
        S.suggestIdx = (S.suggestIdx + (e.key === 'ArrowDown' ? 1 : -1) + S.suggest.length) % S.suggest.length;
        renderSuggest();
        search.setAttribute('aria-activedescendant', 'sg-' + S.suggestIdx);
      } else if (e.key === 'Enter') { e.preventDefault(); renderSuggest(); goSuggest(S.suggestIdx >= 0 ? S.suggestIdx : 0); }
      else if (e.key === 'Escape') { $('#toc-suggest').hidden = true; search.blur(); }
    });
    $('#toc-suggest').addEventListener('mousedown', function (e) { var li = e.target.closest('[data-sg]'); if (li) { e.preventDefault(); goSuggest(Number(li.getAttribute('data-sg'))); } });
    document.addEventListener('click', function (e) { if (!e.target.closest('.toc-search')) $('#toc-suggest').hidden = true; });
    document.addEventListener('contextmenu', blockNativeMenu);
    $('#toc-help-btn').addEventListener('click', function () { $('#toc-help-overlay').hidden = false; $('#toc-help-close').focus(); });
    $('#toc-help-close').addEventListener('click', function () { $('#toc-help-overlay').hidden = true; $('#toc-help-btn').focus(); });
    window.addEventListener('hashchange', applyHash);
    bindKeys();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
