/* Éditeur de plans : panneaux. À gauche l'ORBAT du plan (arbre des unités, glisser pour ranger ou poser sur la carte),
 * les calques (ordre par glisser, opacité, visibilité, verrou ; calques du jeu) et la bibliothèque de symboles
 * (recherche, favoris, récents, glisser sur la carte). À droite les propriétés de la sélection (identité, style,
 * position, temps, notes), le plan (phases, ordre d'opération, légende) et les échanges. Palette de commandes (Ctrl+K),
 * menus du clic droit, dépôt de fichiers. Se branche sur window.TocPlanEditor (toc-plan-editor.js). */
(function () {
  'use strict';
  var E = window.TocPlanEditor;
  if (!E) return;
  var P = E.P, G = E.G, R = E.R, LG = E.LG, CAT = E.CAT, KINDS = E.KINDS, $ = E.$, $$ = E.$$, esc = E.esc;
  var AFFILS = [['F', 'Ami'], ['H', 'Hostile'], ['N', 'Neutre'], ['U', 'Inconnu']];
  var AFFIL_TITLES = { F: 'Amis', H: 'Hostiles', N: 'Neutres', U: 'Inconnus' };
  var ECHELONS = [['', 'Aucun'], ['team', 'Équipe'], ['squad', 'Groupe'], ['section', 'Section'], ['platoon', 'Peloton'], ['company', 'Compagnie'], ['battalion', 'Bataillon'], ['regiment', 'Régiment'], ['brigade', 'Brigade']];
  var ICON_NAMES = [['flag', 'Drapeau'], ['star', 'Étoile'], ['cross', 'Croix'], ['target', 'Cible'], ['triangle', 'Triangle'], ['dot', 'Point'], ['square', 'Carré'], ['diamond', 'Losange']];
  var store = (function () { try { return window.localStorage; } catch (e) { return null; } })();
  function load(key, def) { try { var v = store && store.getItem('athena:toc:' + key); return v ? JSON.parse(v) : def; } catch (e) { return def; } }
  function save(key, v) { try { if (store) store.setItem('athena:toc:' + key, JSON.stringify(v)); } catch (e) {} }

  /* ---------- Pictogrammes de l'interface ---------- */
  var IC = {
    select: 'M5 3l14 8-6 2-2 6z', lasso: 'M12 5c5 0 8 2 8 5s-3 5-8 5-8-2-8-5 3-5 8-5zM7 15c0 3 1 5 3 6', pan: 'M8 12V6a1.5 1.5 0 0 1 3 0v4m0-5a1.5 1.5 0 0 1 3 0v5m0-3a1.5 1.5 0 0 1 3 0v6c0 4-2 6-5 6s-4-1-6-4l-2-4a1.5 1.5 0 0 1 2.5-1.5L8 14',
    unit: 'M3 6h18v12H3zM3 6l18 12M21 6L3 18', text: 'M5 6h14M12 6v13', line: 'M4 20L20 4', arrow: 'M4 20L20 4M12 4h8v8', polygon: 'M5 9l7-5 7 5-3 10H8z', rect: 'M4 6h16v12H4z',
    circle: 'M12 4a8 8 0 1 0 0 16 8 8 0 1 0 0-16', ellipse: 'M12 7c5 0 9 2 9 5s-4 5-9 5-9-2-9-5 4-5 9-5z', measure: 'M3 17L17 3l4 4L7 21zM7 13l2 2M10 10l2 2M13 7l2 2',
    los: 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 9a3 3 0 1 0 0 6 3 3 0 1 0 0-6', profile: 'M3 19l6-10 4 6 3-4 5 8z', viewshed: 'M12 20L4 8a10 10 0 0 1 16 0z',
    snap: 'M6 4v8a6 6 0 0 0 12 0V4h-4v8a2 2 0 0 1-4 0V4z', grid: 'M4 4h16v16H4zM4 10h16M4 15h16M10 4v16M15 4v16', undo: 'M9 14L4 9l5-5M4 9h10a6 6 0 0 1 0 12h-3', redo: 'M15 14l5-5-5-5M20 9H10a6 6 0 0 0 0 12h3',
    zoomin: 'M11 4a7 7 0 1 0 0 14 7 7 0 1 0 0-14M21 21l-5-5M8 11h6M11 8v6', zoomout: 'M11 4a7 7 0 1 0 0 14 7 7 0 1 0 0-14M21 21l-5-5M8 11h6', fit: 'M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5',
    eye: 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 9a3 3 0 1 0 0 6 3 3 0 1 0 0-6', eyeoff: 'M3 3l18 18M10 5.3A9 9 0 0 1 12 5c6 0 10 7 10 7a17 17 0 0 1-3 3.7M6.5 6.6C3.7 8.5 2 12 2 12s4 7 10 7c1.6 0 3-.4 4.3-1',
    lock: 'M6 11h12v9H6zM8 11V8a4 4 0 0 1 8 0v3', unlock: 'M6 11h12v9H6zM8 11V8a4 4 0 0 1 7.5-2', trash: 'M5 7h14M9 7V4h6v3M7 7l1 13h8l1-13', copy: 'M9 9h11v11H9zM5 15H4V4h11v1',
    front: 'M10 10h10v10H10zM4 4h10v4M4 4v10h4', back: 'M4 4h10v10H4zM14 10h6v10H10v-6', group: 'M4 4h6v6H4zM14 14h6v6h-6zM10 7h7v7M7 10v7h7',
    star: 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z', play: 'M7 4l13 8-13 8z', pause: 'M7 4h4v16H7zM13 4h4v16h-4z', prev: 'M6 4v16M20 4L9 12l11 8z', next: 'M18 4v16M4 4l11 8-11 8z',
    key: 'M12 3l9 9-9 9-9-9z', cube: 'M12 3l8 4.5v9L12 21l-8-4.5v-9zM12 12l8-4.5M12 12v9M12 12L4 7.5', sun: 'M12 8a4 4 0 1 0 0 8 4 4 0 1 0 0-8M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2',
    search: 'M11 4a7 7 0 1 0 0 14 7 7 0 1 0 0-14M21 21l-5-5', plus: 'M12 5v14M5 12h14', grip: 'M9 6v.01M15 6v.01M9 12v.01M15 12v.01M9 18v.01M15 18v.01', caret: 'M9 6l6 6-6 6',
    download: 'M12 4v11M7 11l5 5 5-5M5 20h14', upload: 'M12 16V5M7 9l5-5 5 5M5 20h14', share: 'M4 13v7h16v-7M12 15V4M8 8l4-4 4 4', send: 'M4 12l16-8-6 16-3-7z', history: 'M4 12a8 8 0 1 0 3-6.2M4 4v5h5M12 8v5l3 2',
    close: 'M6 6l12 12M18 6L6 18', layers: 'M12 3l9 5-9 5-9-5zM3 13l9 5 9-5', tree: 'M5 4h6v5H5zM13 15h6v5h-6zM13 8.5h6M8 9v8.5h5', book: 'M4 5h7v14H4zM13 5h7v14h-7z', north: 'M12 3l6 17-6-4-6 4z',
    present: 'M4 5h16v11H4zM9 20h6M12 16v4', flag: 'M6 3v18M6 4h11l-3 4 3 4H6', align: 'M4 4v16M8 7h10v4H8zM8 14h6v4H8z', menu: 'M5 7h14M5 12h14M5 17h14', pin: 'M12 21s6-5.5 6-10a6 6 0 1 0-12 0c0 4.5 6 10 6 10zM12 9a2 2 0 1 0 0 4 2 2 0 1 0 0-4'
  };
  function icon(name, size) {
    return '<svg class="pe-ic" width="' + (size || 18) + '" height="' + (size || 18) + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' + (name === 'grip' ? 2.6 : 1.8) + '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' + (IC[name] || IC.circle) + '"/></svg>';
  }
  E.icon = icon;

  /* ---------- Bibliothèque de symboles ---------- */
  // Fonctions et matériels hors de la liste du site : code 2525C (« * » = affiliation et statut). Les codes que
  // milsymbol ne sait pas dessiner sont retirés à l'affichage.
  var EXTRA_UNITS = [
    ['Infanterie aéroportée', 'S*G*UCIA--', 'para airborne'], ['Infanterie héliportée', 'S*G*UCIS--', 'air assault'], ['Infanterie de montagne', 'S*G*UCIO--', 'mountain alpin'], ['Infanterie légère', 'S*G*UCIL--', 'light'],
    ['Reconnaissance blindée', 'S*G*UCRVA-', 'cavalerie'], ['Lance-roquettes multiples', 'S*G*UCFRM-', 'mlrs lrm'], ['Artillerie automotrice', 'S*G*UCFHE-', 'howitzer'], ['Missiles sol-air', 'S*G*UCDM--', 'sam'],
    ['Génie de combat', 'S*G*UCEC--', 'sapeurs'], ['Drones', 'S*G*UCVU--', 'uav rpas'], ['Aviation (voilure fixe)', 'S*G*UCVF--', 'fixed wing'], ['Renseignement', 'S*G*UUM---', 'intel'],
    ['Police militaire', 'S*G*UULM--', 'mp prévôté'], ['NRBC', 'S*G*UUA---', 'cbrn nbc'], ['NEDEX (EOD)', 'S*G*UUE---', 'eod déminage'], ['Guerre électronique', 'S*G*UUMSE-', 'ew'],
    ['Transport', 'S*G*UST---', 'train'], ['Maintenance', 'S*G*USX---', 'réparation'], ['Forces spéciales (terre)', 'S*F*G-----', 'sof fs']
  ];
  var EQUIPMENT = [
    ['Char', 'S*G*EVAT--', 'tank mbt'], ['Transport de troupes blindé', 'S*G*EVAA--', 'apc vab'], ['Véhicule de combat d’infanterie', 'S*G*EVAI--', 'ifv vbci'], ['Blindé léger', 'S*G*EVAL--', 'lav'],
    ['Véhicule utilitaire', 'S*G*EVU---', 'camion truck'], ['Véhicule du génie', 'S*G*EVE---', 'engineer'], ['Mitrailleuse', 'S*G*EWR---', 'mg'], ['Lance-grenades', 'S*G*EWZ---', 'gl'],
    ['Mortier', 'S*G*EWO---', 'mortar'], ['Obusier', 'S*G*EWH---', 'canon howitzer'], ['Lance-missiles antichar', 'S*G*EWMT--', 'atgm'], ['Lance-missiles sol-air', 'S*G*EWMA--', 'sam manpads'],
    ['Lance-roquettes multiple', 'S*G*EWX---', 'mlrs'], ['Canon antiaérien', 'S*G*EWA---', 'aa'], ['Radar', 'S*G*ESR---', 'sensor'], ['Hélicoptère d’attaque', 'S*A*MHA---', 'attack helo'],
    ['Hélicoptère de manœuvre', 'S*A*MHU---', 'utility helo'], ['Hélicoptère de transport', 'S*A*MHC---', 'cargo'], ['Avion de chasse', 'S*A*MFF---', 'fighter'], ['Avion d’attaque', 'S*A*MFA---', 'cas strike'],
    ['Avion de transport', 'S*A*MFC---', 'cargo airlift'], ['Drone aérien', 'S*A*MFQ---', 'uav'], ['Navire de combat', 'S*S*CL----', 'ship'], ['Embarcation', 'S*S*CP----', 'patrol boat'],
    ['Installation', 'S*G*I-----', 'bâtiment facility']
  ];
  var CATS = [
    ['units', 'Unités'], ['equip', 'Matériels et installations'], ['points', 'Points'], ['move', 'Manœuvre'], ['control', 'Mesures de coordination'], ['areas', 'Zones'],
    ['fire', 'Appuis feux'], ['obst', 'Obstacles et NRBC'], ['css', 'Santé et logistique'], ['free', 'Dessin libre']
  ];
  var KIND_CAT = {
    text: 'free', icon: 'points', checkpoint: 'points', rally: 'points', trp: 'fire', objpt: 'points', cleared: 'points', uncleared: 'points', op: 'points', target: 'fire',
    medevac: 'css', ccp: 'css', supply: 'css', decon: 'obst', fan: 'fire', ring: 'fire', sbf: 'fire', abf: 'fire', fscl: 'fire', cfl: 'fire', rfl: 'fire', nfa: 'fire', ffa: 'fire', rfa: 'fire',
    route: 'move', axis: 'move', attack: 'move', support: 'move', move: 'move', phase_line: 'control', ld: 'control', loa: 'control', boundary: 'control', flot: 'control',
    objective: 'areas', ao: 'areas', nai: 'areas', tai: 'areas', ea: 'areas', aa: 'areas', enemy: 'areas', bp: 'areas', lz: 'areas', pz: 'areas', dz: 'areas', infil: 'areas', exfil: 'areas',
    minefield: 'obst', obstacle: 'obst', obstacle_belt: 'obst', cbrn: 'obst', line: 'free', arrow: 'free', polygon: 'free', rect: 'free', ellipse: 'free', circle: 'free', textbox: 'free'
  };
  var libItems = null, libQuery = '', libFav = load('fav', []), libRecent = load('recent', []), libOpen = load('libopen', { units: true, move: true, areas: true });
  function buildLibrary() {
    var items = [];
    (CAT.unitTypes || []).forEach(function (t) { items.push({ id: 'u:' + t.id, cat: 'units', label: t.label, kind: 'unit', props: { unitType: t.id }, kw: t.id }); });
    var valid = function (code) { return R.unitSymbol({ sidc: code, affil: 'F' }, CAT, 20).valid !== false; };
    var known = {};
    (CAT.unitTypes || []).forEach(function (t) { known[t.fn] = true; });
    EXTRA_UNITS.forEach(function (u) { if (!(u[1].charAt(2) === 'G' && known[u[1].slice(4, 10)]) && valid(u[1])) items.push({ id: 's:' + u[1], cat: 'units', label: u[0], kind: 'unit', props: { sidc: u[1], unitType: 'unknown' }, kw: u[2] }); });
    EQUIPMENT.forEach(function (u) { if (valid(u[1])) items.push({ id: 's:' + u[1], cat: 'equip', label: u[0], kind: 'unit', props: { sidc: u[1], unitType: 'unknown', echelon: '' }, kw: u[2], noEchelon: true }); });
    Object.keys(KINDS).forEach(function (k) {
      if (k === 'unit') return;
      var d = KINDS[k];
      items.push({ id: 'k:' + k, cat: KIND_CAT[k] || (d.family === 'area' ? 'areas' : (d.family === 'line' ? 'control' : 'free')), label: d.label, kind: k, props: null, kw: k + ' ' + (d.text || '') });
    });
    return items;
  }
  function itemPreview(it, big) {
    var d = KINDS[it.kind] || {};
    if (it.kind === 'unit') {
      var props = { affil: P.lib.affil, echelon: it.noEchelon ? '' : P.lib.echelon, unitType: 'infantry' };
      Object.keys(it.props || {}).forEach(function (k) { props[k] = it.props[k]; });
      return R.unitSymbol(props, CAT, big ? 30 : 22).svg;
    }
    var c = d.color || '#f2f2f2';
    var g = (d.geoms || ['point'])[0];
    if (it.kind === 'rect') g = 'rect';
    if (it.kind === 'ellipse') g = 'ellipse';
    if (g === 'point' && it.kind !== 'fan') return R.pointHtml({ kind: it.kind, geom: 'point', pts: [[0, 0]], props: { color: c, size: 24, label: it.kind === 'text' ? 'Aa' : '', icon: 'flag' } }, { catalog: CAT }).html;
    var dash = d.dash === 'dash' ? ' stroke-dasharray="5 4"' : '';
    var fill = d.fill ? ' fill="' + c + '" fill-opacity="' + Math.max(0.18, d.fill * 1.6) + '"' : ' fill="none"';
    var s = '<svg width="40" height="28" viewBox="0 0 40 28" fill="none" stroke="' + c + '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round">';
    if (it.kind === 'fan') s += '<path d="M20 25L5 9a22 22 0 0 1 30 0z"' + fill.replace('none', c).replace('fill="' + c + '"', 'fill="' + c + '" fill-opacity=".18"') + '/>';
    else if (g === 'line') {
      if (it.kind === 'axis' || it.kind === 'attack') s += '<path d="M3 20h20M3 10h20M23 6l13 9-13 9"' + dash + '/>';
      else if (it.kind === 'textbox') s += '<path d="M5 23L18 12"/><rect x="18" y="4" width="19" height="11" fill="rgba(7,10,13,.8)"/>';
      else if (it.kind === 'obstacle') s += '<path d="M3 16h34M9 16v-6M17 16v-6M25 16v-6M33 16v-6"/>';
      else s += '<path d="M3 21C12 21 14 8 ' + (d.arrow === 'end' ? '31 8' : '37 8') + '"' + dash + '/>' + (d.arrow === 'end' ? '<path d="M29 3l8 5-8 5z" fill="' + c + '"/>' : '');
    } else if (g === 'circle') s += '<circle cx="20" cy="14" r="11"' + fill + dash + '/>' + (it.kind === 'ring' ? '<circle cx="20" cy="14" r="5" stroke-dasharray="3 3"/>' : '');
    else if (g === 'ellipse') s += '<ellipse cx="20" cy="14" rx="16" ry="9"' + fill + dash + '/>';
    else if (g === 'rect') s += '<rect x="4" y="5" width="32" height="18"' + fill + dash + '/>';
    else s += '<path d="M5 9l12-5 17 6-4 14H9z"' + fill + dash + '/>';
    if (d.text && g !== 'line' && it.kind !== 'fan') s += '<text x="20" y="18" text-anchor="middle" font-size="9" font-weight="700" fill="' + c + '" stroke="none" font-family="Arial">' + esc(d.text.slice(0, 5)) + '</text>';
    return s + '</svg>';
  }
  function libTile(it) {
    var fav = libFav.indexOf(it.id) >= 0;
    return '<div class="pe-tile" role="button" tabindex="0" data-lib="' + esc(it.id) + '" data-drag="lib" title="' + esc(it.label) + ' : cliquer puis cliquer sur la carte, ou glisser sur la carte"><span class="pe-tile-ic">' + itemPreview(it) + '</span><span class="pe-tile-lb">' + esc(it.label) + '</span>' +
      '<button type="button" class="pe-tile-fav' + (fav ? ' is-on' : '') + '" data-fav="' + esc(it.id) + '" aria-label="' + (fav ? 'Retirer des favoris' : 'Ajouter aux favoris') + '" aria-pressed="' + fav + '">' + icon('star', 14) + '</button></div>';
  }
  function renderLibrary() {
    var body = $('#pe-lib-body');
    if (!body) return;
    libItems = libItems || buildLibrary();
    var by = {};
    libItems.forEach(function (it) { by[it.id] = it; });
    var h = '';
    var q = libQuery.trim();
    if (q) {
      var found = libItems.map(function (it) { return [LG.fuzzyScore(q, it.label + ' ' + (it.kw || '')), it]; }).filter(function (x) { return x[0] > 0; }).sort(function (a, b) { return b[0] - a[0]; }).slice(0, 60);
      h += found.length ? '<div class="pe-tiles">' + found.map(function (x) { return libTile(x[1]); }).join('') + '</div>' : '<div class="toc-empty"><p>Aucun symbole pour « ' + esc(q) + ' ».</p></div>';
    } else {
      var favs = libFav.map(function (id) { return by[id]; }).filter(Boolean);
      var rec = libRecent.map(function (id) { return by[id]; }).filter(Boolean).slice(0, 8);
      if (favs.length) h += '<div class="pe-libsec"><h3>Favoris</h3><div class="pe-tiles">' + favs.map(libTile).join('') + '</div></div>';
      if (rec.length) h += '<div class="pe-libsec"><h3>Récents</h3><div class="pe-tiles">' + rec.map(libTile).join('') + '</div></div>';
      CATS.forEach(function (c) {
        var its = libItems.filter(function (it) { return it.cat === c[0]; });
        if (!its.length) return;
        h += '<details class="pe-libsec" data-libcat="' + c[0] + '"' + (libOpen[c[0]] ? ' open' : '') + '><summary>' + icon('caret', 14) + '<span>' + esc(c[1]) + '</span><small>' + its.length + '</small></summary><div class="pe-tiles">' + its.map(libTile).join('') + '</div></details>';
      });
    }
    body.innerHTML = h;
  }
  function libFind(id) { libItems = libItems || buildLibrary(); return libItems.filter(function (it) { return it.id === id; })[0] || null; }
  function libProps(it) {
    var props = {};
    Object.keys(it.props || {}).forEach(function (k) { props[k] = it.props[k]; });
    if (it.kind === 'unit') { props.affil = P.lib.affil; if (!it.noEchelon && props.echelon === undefined) props.echelon = P.lib.echelon; }
    return props;
  }
  function libUsed(it) {
    libRecent = [it.id].concat(libRecent.filter(function (x) { return x !== it.id; })).slice(0, 12);
    save('recent', libRecent);
  }
  /** Arme l'outil d'un symbole de la bibliothèque : le prochain clic sur la carte le pose (ou commence le tracé). */
  function armItem(it) {
    if (!P.canEdit) { E.say('Lecture seule.', true); return; }
    libUsed(it);
    var d = KINDS[it.kind] || {};
    E.setTool(it.kind, { kind: it.kind, props: libProps(it), geom: it.kind === 'rect' ? 'rect' : (it.kind === 'ellipse' ? 'ellipse' : (d.geoms || ['point'])[0]) });
    E.say(it.label + ' : cliquez sur la carte' + ((d.geoms || ['point'])[0] === 'point' ? ' (Maj : en poser plusieurs)' : ''));
    $$('.pe-tile').forEach(function (t) { t.classList.toggle('is-armed', t.getAttribute('data-lib') === it.id); });
  }
  function dropItem(it, world) {
    libUsed(it);
    var d = KINDS[it.kind] || {};
    if ((d.geoms || ['point'])[0] === 'point') E.placePoint(it.kind, world, libProps(it));
    else E.placeShape(it.kind, world, libProps(it));
    renderLibrary();
  }

  /* ---------- Glisser-déposer au pointeur (souris, stylet, doigt) ---------- */
  var drag = null;
  function onDragDown(ev) {
    if (ev.button != null && ev.button !== 0) return;
    var src = ev.target.closest('[data-drag]');
    if (!src || ev.target.closest('button, input, select, a')) return;
    drag = { src: src, kind: src.getAttribute('data-drag'), x: ev.clientX, y: ev.clientY, on: false, ghost: null };
    document.addEventListener('pointermove', onDragMove);
    document.addEventListener('pointerup', onDragUp);
    document.addEventListener('pointercancel', onDragUp);
  }
  function mapWorldAt(ev) {
    var wrap = $('#plan-mapwrap');
    var r = wrap.getBoundingClientRect();
    if (ev.clientX < r.left || ev.clientX > r.right || ev.clientY < r.top || ev.clientY > r.bottom) return null;
    var el = document.elementFromPoint(ev.clientX, ev.clientY);
    if (el && el.closest('.pe-float')) return null;
    if (P.mode === '3d') return E.pick3d ? E.pick3d(ev.clientX, ev.clientY) : null;
    return E.worldOf(ev);
  }
  function onDragMove(ev) {
    if (!drag) return;
    if (!drag.on) {
      if (Math.abs(ev.clientX - drag.x) + Math.abs(ev.clientY - drag.y) < 6) return;
      drag.on = true;
      var g = document.createElement('div');
      g.className = 'pe-ghost';
      var pic = drag.src.querySelector('.pe-tile-ic, .pe-node-ic');
      g.innerHTML = (pic ? pic.innerHTML : '') + '<span>' + esc((drag.src.querySelector('.pe-tile-lb, .pe-node-lb, .pe-layer-name') || {}).textContent || (drag.src.querySelector('input') || {}).value || '') + '</span>';
      document.body.appendChild(g);
      drag.ghost = g;
      document.body.classList.add('is-dragging');
    }
    ev.preventDefault();
    drag.ghost.style.transform = 'translate(' + (ev.clientX + 12) + 'px,' + (ev.clientY + 10) + 'px)';
    $$('.is-drop-in, .is-drop-before, .is-drop-after').forEach(function (n) { n.classList.remove('is-drop-in', 'is-drop-before', 'is-drop-after'); });
    drag.target = null;
    var over = document.elementFromPoint(ev.clientX, ev.clientY);
    var w = (drag.kind === 'lib' || drag.kind === 'node') ? mapWorldAt(ev) : null;
    drag.ghost.classList.toggle('is-ok', !!w);
    if (w) { $('#plan-cursor').textContent = G.grid(w[0], w[1], 8); drag.world = E.snapPoint(w); return; }
    drag.world = null;
    if (!over) return;
    if (drag.kind === 'node') {
      var row = over.closest('[data-node]');
      var head = over.closest('[data-side-drop]');
      if (row && row !== drag.src && !row.contains(drag.src)) {
        var r = row.getBoundingClientRect();
        var f = (ev.clientY - r.top) / r.height;
        var mode = f < 0.28 ? 'before' : (f > 0.72 ? 'after' : 'in');
        row.classList.add('is-drop-' + mode);
        drag.target = { uid: row.getAttribute('data-node'), mode: mode };
      } else if (head) { head.classList.add('is-drop-in'); drag.target = { side: head.getAttribute('data-side-drop'), mode: 'root' }; }
    } else if (drag.kind === 'layer') {
      var lr = over.closest('[data-layer-row]');
      if (lr && lr !== drag.src) {
        var rr = lr.getBoundingClientRect();
        var after = ev.clientY > rr.top + rr.height / 2;
        lr.classList.add(after ? 'is-drop-after' : 'is-drop-before');
        drag.target = { uid: lr.getAttribute('data-layer-row'), after: after };
      }
    }
  }
  function onDragUp() {
    document.removeEventListener('pointermove', onDragMove);
    document.removeEventListener('pointerup', onDragUp);
    document.removeEventListener('pointercancel', onDragUp);
    var d = drag;
    drag = null;
    if (!d) return;
    if (d.ghost) d.ghost.remove();
    document.body.classList.remove('is-dragging');
    $$('.is-drop-in, .is-drop-before, .is-drop-after').forEach(function (n) { n.classList.remove('is-drop-in', 'is-drop-before', 'is-drop-after'); });
    if (!d.on) return;
    // Le clic qui suit le relâchement ne doit pas armer l'outil ou choisir la ligne.
    P.dragJustEnded = Date.now();
    if (!P.canEdit) { E.say('Lecture seule.', true); return; }
    if (d.kind === 'lib' && d.world) { var it = libFind(d.src.getAttribute('data-lib')); if (it) dropItem(it, d.world); return; }
    if (d.kind === 'node') {
      var uid = d.src.getAttribute('data-node');
      if (d.world) { placeUnit(uid, d.world); return; }
      if (d.target) moveNode(uid, d.target);
      return;
    }
    if (d.kind === 'layer' && d.target) moveLayer(d.src.getAttribute('data-layer-row'), d.target.uid, d.target.after);
  }

  /* ---------- ORBAT du plan ---------- */
  var orbatQuery = '', collapsed = load('orbat-collapsed', {});
  function unitLabel(el) {
    var p = el.props;
    if (p.callsign) return p.callsign;
    var t = (CAT.unitTypes || []).filter(function (x) { return x.id === p.unitType; })[0];
    var extra = p.sidc ? EXTRA_UNITS.concat(EQUIPMENT).filter(function (u) { return u[1].replace(/\*/g, '-').slice(4, 10) === String(p.sidc).replace(/\*/g, '-').slice(4, 10) && u[1].charAt(2) === String(p.sidc).charAt(2); })[0] : null;
    return extra ? extra[0] : (t ? t.label : 'Unité');
  }
  E.unitLabel = unitLabel;
  function nodeHtml(n, depth, match) {
    var el = n.el, p = el.props;
    var kids = n.children;
    var shown = !match || match[el.uid];
    if (!shown) return '';
    var isCol = collapsed[el.uid] && !match;
    var sel = P.sel.indexOf(el.uid) >= 0;
    var h = '<div class="pe-node' + (sel ? ' is-sel' : '') + (p.placed === false ? ' is-unplaced' : '') + (p.hidden ? ' is-hidden' : '') + '" data-node="' + el.uid + '" data-drag="node" style="--depth:' + depth + '" role="treeitem" aria-selected="' + sel + '"' + (kids.length ? ' aria-expanded="' + !isCol + '"' : '') + ' tabindex="-1">' +
      '<button type="button" class="pe-node-caret' + (kids.length ? '' : ' is-leaf') + (isCol ? '' : ' is-open') + '" data-caret="' + el.uid + '" aria-label="' + (isCol ? 'Déplier' : 'Replier') + '"' + (kids.length ? '' : ' tabindex="-1" disabled') + '>' + icon('caret', 14) + '</button>' +
      '<span class="pe-node-ic">' + R.unitSymbol(p, CAT, 16).svg + '</span><span class="pe-node-lb">' + esc(unitLabel(el)) + '</span>' +
      (p.placed === false ? '<span class="pe-node-tag" title="Pas encore sur la carte : glissez l’unité sur la carte">à poser</span>' : '') +
      (p.track && p.track.length > 1 ? '<span class="pe-node-tag is-move" title="Unité en mouvement : ' + p.track.length + ' images clés">' + p.track.length + '</span>' : '') +
      '<button type="button" class="pe-node-btn' + (p.hidden ? ' is-on' : '') + '" data-node-eye="' + el.uid + '" aria-label="' + (p.hidden ? 'Afficher' : 'Masquer') + '" title="' + (p.hidden ? 'Afficher' : 'Masquer') + '">' + icon(p.hidden ? 'eyeoff' : 'eye', 15) + '</button>' +
      '<button type="button" class="pe-node-btn' + (el.locked ? ' is-on' : '') + '" data-node-lock="' + el.uid + '" aria-label="' + (el.locked ? 'Déverrouiller' : 'Verrouiller') + '" title="' + (el.locked ? 'Déverrouiller' : 'Verrouiller') + '">' + icon(el.locked ? 'lock' : 'unlock', 15) + '</button></div>';
    if (!isCol) kids.forEach(function (k) { h += nodeHtml(k, depth + 1, match); });
    return h;
  }
  function renderOrbat() {
    var body = $('#pe-orbat-body');
    if (!body) return;
    var els = E.list();
    var units = els.filter(function (e) { return e.kind === 'unit'; });
    var tree = LG.orbatTree(els);
    var match = null;
    var q = orbatQuery.trim();
    if (q) {
      match = {};
      var by = {};
      units.forEach(function (u) { by[u.uid] = u; });
      units.forEach(function (u) {
        if (LG.fuzzyScore(q, unitLabel(u) + ' ' + (u.props.higher || '') + ' ' + (u.props.info || '')) <= 0) return;
        var cur = u, guard = 0;
        while (cur && guard++ < 40) { match[cur.uid] = true; cur = cur.props.parent ? by[cur.props.parent] : null; }
      });
    }
    if (!units.length) {
      body.innerHTML = '<div class="toc-empty"><p>Aucune unité dans ce plan. Glissez une unité de la bibliothèque sur la carte, ou reprenez l’ORBAT de la communauté ou les groupes en jeu.</p>' + (P.canEdit ? '<div class="pe-stack"><button type="button" class="toc-btn is-primary" data-orbat="site">Importer l’ORBAT du site</button><button type="button" class="toc-btn" data-orbat="game">Depuis le jeu</button></div>' : '') + '</div>';
      return;
    }
    var h = '';
    AFFILS.forEach(function (a) {
      var roots = tree[a[0]];
      var n = units.filter(function (u) { return (u.props.affil || 'F') === a[0]; }).length;
      if (!n && a[0] !== 'F') return;
      h += '<div class="pe-side" data-side-drop="' + a[0] + '"><span class="pe-side-dot" style="background:' + E.AFFIL_COLOR[a[0]] + '"></span><b>' + esc(AFFIL_TITLES[a[0]]) + '</b><small class="mono">' + n + '</small></div>';
      h += '<div role="group">' + roots.map(function (r) { return nodeHtml(r, 0, match); }).join('') + '</div>';
    });
    if (match && !Object.keys(match).length) h = '<div class="toc-empty"><p>Aucune unité pour « ' + esc(q) + ' ».</p></div>';
    body.innerHTML = h;
  }
  function placeUnit(uid, world) {
    var el = P.els[uid];
    if (!el || !E.editable(el)) { E.say('Unité verrouillée.', true); return; }
    var m = {};
    if (el.props.placed === false) {
      var c = G.clone(el);
      c.props.placed = true; c.pts = [[Math.round(world[0] * 10) / 10, Math.round(world[1] * 10) / 10]];
      delete c.props.track;
      m[uid] = c;
      E.commit(m, unitLabel(el) + ' posée sur la carte');
    } else {
      var cur = LG.positionAt(el, P.time == null ? -1e9 : P.time, P.els);
      if (P.time == null) cur = el.pts[0];
      m[uid] = E.moveEl(el, world[0] - cur[0], world[1] - cur[1]);
      E.commit(m, unitLabel(el) + ' déplacée');
    }
    E.setSel([uid]);
  }
  function moveNode(uid, target) {
    var els = E.list();
    var res = null;
    if (target.mode === 'root') res = LG.reparent(els, uid, '', null, target.side);
    else {
      var t = P.els[target.uid];
      if (!t) return;
      if (target.mode === 'in') res = LG.reparent(els, uid, target.uid, null);
      else {
        var par = t.props.parent || '';
        var sib = els.filter(function (e) { return e.kind === 'unit' && e.uid !== uid && (e.props.parent || '') === par && (par ? true : e.props.affil === t.props.affil); }).sort(function (a, b) { return ((a.props.ord || 0) - (b.props.ord || 0)) || (a.uid < b.uid ? -1 : 1); });
        var idx = sib.map(function (e) { return e.uid; }).indexOf(target.uid) + (target.mode === 'after' ? 1 : 0);
        res = LG.reparent(els, uid, par, idx, t.props.affil);
      }
    }
    if (!res) { E.say('Déplacement impossible : une unité ne peut pas passer sous l’une de ses subordonnées.', true); return; }
    Object.keys(res).forEach(function (u) { if (res[u].kind === 'unit') res[u].props.color = E.AFFIL_COLOR[res[u].props.affil] || res[u].props.color; });
    if (target.mode === 'in') { delete collapsed[target.uid]; save('orbat-collapsed', collapsed); }
    E.commit(res, 'ORBAT réorganisé');
  }
  function importOrbat(source) {
    if (!P.canEdit) return;
    if (source === 'game') {
      var us = E.layers ? E.layers.units() : [];
      var groups = {};
      us.forEach(function (u) { if (!u.pos) return; var k = u.group || u.callsign; (groups[k] = groups[k] || []).push(u); });
      var names = Object.keys(groups);
      if (!names.length) { E.say('Aucun groupe ATAK en liaison sur ce terrain pour le moment.', true); return; }
      var have = {};
      E.list().forEach(function (e) { if (e.kind === 'unit') have[String(e.props.callsign).toUpperCase()] = true; });
      var m = {}, z = E.maxZ(), n = 0;
      names.forEach(function (k) {
        if (have[k.toUpperCase()]) return;
        var mem = groups[k];
        var el = E.newElement('unit', 'point', [mem[0].pos], { callsign: k.slice(0, 40), affil: 'F', echelon: mem.length > 12 ? 'section' : (mem.length > 4 ? 'squad' : 'team'), info: mem.length + ' pax' });
        el.z = ++z; m[el.uid] = el; n++;
      });
      if (!n) { E.say('Les groupes en jeu sont déjà dans le plan.'); return; }
      E.commit(m, n + ' groupe(s) du jeu ajouté(s) à leur position actuelle · Ctrl+Z pour annuler');
      return;
    }
    E.say('Lecture de l’ORBAT de la communauté…');
    E.api('/atak/toc/api/orbat').then(function (j) {
      var rows = (j && j.ok && j.units) || [];
      if (!rows.length) { E.say(j && j.ok ? 'L’ORBAT de la communauté est vide.' : 'ORBAT du site indisponible.', true); return; }
      var have = {};
      E.list().forEach(function (e) { if (e.kind === 'unit' && e.props.info && /^#\d+$/.test(e.props.info)) have[e.props.info] = e.uid; });
      var ids = {}, m = {}, z = E.maxZ(), n = 0;
      rows.forEach(function (u) { ids[u.id] = have['#' + u.id] || G.uid('e'); });
      rows.forEach(function (u, i) {
        if (have['#' + u.id]) return;
        var el = E.newElement('unit', 'point', [[0, 0]], { callsign: String(u.short || u.callsign || u.name || '').slice(0, 40), higher: '', info: '#' + u.id, affil: 'F', placed: false, ord: i, parent: u.parent_id && ids[u.parent_id] ? ids[u.parent_id] : '', echelon: u.echelon || P.lib.echelon, unitType: u.unitType || 'infantry', note: String(u.name || '').slice(0, 400) });
        el.uid = ids[u.id]; el.z = ++z; el.phases = [];
        m[el.uid] = el; n++;
      });
      if (!n) { E.say('Les unités de la communauté sont déjà dans l’ORBAT du plan.'); return; }
      E.commit(m, n + ' unité(s) de la communauté ajoutée(s) à l’ORBAT : glissez-les sur la carte pour les poser');
      setLeftTab('orbat');
    }).catch(function () { E.say('Réseau indisponible.', true); });
  }

  /* ---------- Calques ---------- */
  function renderLayers() {
    var body = $('#pe-layers-plan');
    if (!body) return;
    if (body.contains(document.activeElement) && document.activeElement.tagName === 'INPUT' && document.activeElement.type === 'text') return;
    var ls = P.meta.layers || [];
    var els = E.list();
    var h = '';
    ls.slice().reverse().forEach(function (l) {
      var n = els.filter(function (e) { return e.layer === l.uid; }).length;
      var act = P.layer === l.uid;
      h += '<div class="pe-layer' + (act ? ' is-active' : '') + (l.visible === false ? ' is-off' : '') + '" data-layer-row="' + l.uid + '" data-drag="layer">' +
        '<span class="pe-grip" title="Glisser pour changer l’ordre">' + icon('grip', 16) + '</span>' +
        '<button type="button" class="pe-node-btn is-keep' + (l.visible === false ? ' is-on' : '') + '" data-lv="' + l.uid + '" aria-label="' + (l.visible === false ? 'Afficher le calque' : 'Masquer le calque') + '" aria-pressed="' + (l.visible !== false) + '">' + icon(l.visible === false ? 'eyeoff' : 'eye', 16) + '</button>' +
        '<button type="button" class="pe-node-btn is-keep' + (l.locked ? ' is-on' : '') + '" data-ll="' + l.uid + '" aria-label="' + (l.locked ? 'Déverrouiller le calque' : 'Verrouiller le calque') + '" aria-pressed="' + !!l.locked + '"' + (P.canEdit ? '' : ' disabled') + '>' + icon(l.locked ? 'lock' : 'unlock', 16) + '</button>' +
        '<input class="pe-layer-name" type="text" data-ln="' + l.uid + '" value="' + esc(l.name) + '" maxlength="40" aria-label="Nom du calque"' + (P.canEdit ? '' : ' disabled') + '>' +
        '<small class="mono" title="Éléments dans ce calque">' + n + '</small>' +
        '<button type="button" class="pe-layer-act" data-lyr-active="' + l.uid + '" aria-pressed="' + act + '" title="Les nouveaux éléments vont dans ce calque">' + (act ? 'Actif' : 'Activer') + '</button>' +
        '<input type="range" class="pe-layer-op" min="0.1" max="1" step="0.05" value="' + (l.opacity != null ? l.opacity : 1) + '" data-lo="' + l.uid + '" aria-label="Opacité du calque ' + esc(l.name) + '">' +
        (P.canEdit && ls.length > 1 ? '<button type="button" class="pe-node-btn is-keep" data-ld="' + l.uid + '" aria-label="Retirer le calque" title="Retirer le calque (ses éléments passent dans un autre)">' + icon('trash', 15) + '</button>' : '<span></span>') + '</div>';
    });
    if (P.canEdit) h += '<button type="button" class="toc-btn is-small is-block" data-layer-add>' + icon('plus', 15) + ' Ajouter un calque</button>';
    body.innerHTML = h;
  }
  function moveLayer(uid, targetUid, after) {
    // La liste se lit du dessus (premier) au dessous : l'ordre gardé est l'inverse (dessin du dessous vers le dessus).
    var top = (P.meta.layers || []).slice().reverse();
    var from = top.map(function (l) { return l.uid; }).indexOf(uid);
    if (from < 0) return;
    var it = top.splice(from, 1)[0];
    var to = top.map(function (l) { return l.uid; }).indexOf(targetUid);
    if (to < 0) return;
    top.splice(to + (after ? 1 : 0), 0, it);
    P.meta.layers = top.reverse();
    E.metaChanged();
    E.renderAll();
  }
  function removeLayer(uid) {
    var rest = (P.meta.layers || []).filter(function (l) { return l.uid !== uid; });
    if (!rest.length) return;
    var gone = E.layerOf(uid);
    var target = rest[0].uid;
    var m = {};
    E.list().forEach(function (el) { if (el.layer === uid) { var c = G.clone(el); c.layer = target; m[c.uid] = c; } });
    if (Object.keys(m).length && !window.confirm('Retirer le calque « ' + gone.name + ' » ? Ses ' + Object.keys(m).length + ' élément(s) passent dans « ' + rest[0].name + ' ».')) return;
    P.meta.layers = rest;
    if (P.layer === uid) P.layer = target;
    E.metaChanged();
    if (Object.keys(m).length) E.commit(m, 'Éléments passés dans « ' + rest[0].name + ' »'); else E.renderAll();
  }

  /* ---------- Onglets ---------- */
  var leftTab = load('left', 'lib'), rightTab = 'props', propTab = 'id';
  function setLeftTab(t) {
    leftTab = t;
    save('left', t);
    $$('[data-left]').forEach(function (b) { b.setAttribute('aria-selected', String(b.getAttribute('data-left') === t)); });
    $$('[data-left-body]').forEach(function (b) { b.hidden = b.getAttribute('data-left-body') !== t; });
    renderLeft();
  }
  function renderLeft() {
    if (leftTab === 'orbat') renderOrbat();
    if (leftTab === 'layers') { renderLayers(); if (E.layers) E.layers.openPanel(true); }
    if (leftTab === 'lib') renderLibrary();
  }
  function setRightTab(t) {
    rightTab = t;
    $$('[data-right]').forEach(function (b) { b.setAttribute('aria-selected', String(b.getAttribute('data-right') === t)); });
    renderRight(true);
  }
  E.setRightTab = setRightTab;
  E.setLeftTab = setLeftTab;

  /* ---------- Panneau de droite ---------- */
  function sec(t, s) { return '<div class="toc-sec"><h2>' + esc(t) + '</h2><span>' + (s || '') + '</span></div>'; }
  function opt(list, cur) { return list.map(function (o) { return '<option value="' + esc(o[0]) + '"' + (String(cur == null ? '' : cur) === String(o[0]) ? ' selected' : '') + '>' + esc(o[1]) + '</option>'; }).join(''); }
  function field(label, html, cls) { return '<label class="pe-f' + (cls ? ' ' + cls : '') + '"><span>' + esc(label) + '</span>' + html + '</label>'; }
  function txt(key, val, max, dis, extra) { return '<input class="toc-input" type="text" data-prop="' + key + '" maxlength="' + max + '" value="' + esc(val == null ? '' : val) + '"' + dis + (extra || '') + '>'; }
  function num(key, val, min, max, dis, step) { return '<input class="toc-input mono" type="number" data-prop="' + key + '" data-num min="' + min + '" max="' + max + '"' + (step ? ' step="' + step + '"' : '') + ' value="' + (val == null || val === '' ? '' : val) + '"' + dis + '>'; }
  function range(key, val, min, max, step, dis) { return '<span class="pe-range"><input type="range" data-prop="' + key + '" data-num min="' + min + '" max="' + max + '" step="' + step + '" value="' + val + '"' + dis + '><output class="mono">' + esc(String(val)) + '</output></span>'; }
  function seg(key, list, cur, dis) {
    return '<span class="pe-seg" role="group">' + list.map(function (o) { return '<button type="button" data-set="' + key + '" data-val="' + esc(o[0]) + '" aria-pressed="' + (String(cur) === String(o[0])) + '"' + dis + (o[2] ? ' style="--seg:' + o[2] + '"' : '') + '>' + esc(o[1]) + '</button>'; }).join('') + '</span>';
  }
  function check(key, val, label, dis) { return '<label class="pe-check"><input type="checkbox" data-prop="' + key + '" data-bool' + (val ? ' checked' : '') + dis + '><span>' + esc(label) + '</span></label>'; }
  function swatches(cur, dis) {
    return '<div class="plan-swatches">' + Object.keys(G.GAME_COLORS).map(function (k) {
      var c = G.GAME_COLORS[k];
      return '<button type="button" data-color="' + c + '" title="' + k + ' (couleur du jeu)" aria-label="' + k + '" aria-pressed="' + (cur === c) + '" style="background:' + c + '"' + dis + '></button>';
    }).join('') + '<input type="color" data-color-input value="' + esc(cur || '#f2f2f2') + '" aria-label="Autre couleur" title="Autre couleur (la plus proche des 9 couleurs du jeu part en jeu)"' + dis + '></div>';
  }
  function polyArea(pts) { var a = 0; for (var i = 0, j = pts.length - 1; i < pts.length; j = i++) a += (pts[j][0] + pts[i][0]) * (pts[j][1] - pts[i][1]); return Math.abs(a / 2); }
  function fmtArea(m2) { return m2 >= 1e6 ? (m2 / 1e6).toFixed(2).replace('.', ',') + ' km²' : (m2 >= 1e4 ? (m2 / 1e4).toFixed(1).replace('.', ',') + ' ha' : Math.round(m2) + ' m²'); }

  var PROP_TABS = [['id', 'Identité'], ['style', 'Style'], ['pos', 'Emplacement'], ['time', 'Temps'], ['notes', 'Notes']];
  function renderRight(force) {
    var body = $('#pe-right-body');
    if (!body || !P.loaded) return;
    if (!force && body.contains(document.activeElement) && E.typingIn(document.activeElement)) return;
    var keep = body.scrollTop;
    body.innerHTML = rightTab === 'plan' ? planTab() : (rightTab === 'notes' ? notesTab() : propsTab());
    body.scrollTop = keep;
    var cnt = $('#pe-notes-count');
    if (cnt) { var open = P.comments.filter(function (c) { return !c.resolved; }).length; cnt.textContent = open ? String(open) : ''; cnt.hidden = !open; }
  }
  function actionsRow(els) {
    if (!P.canEdit) return '';
    var allLocked = els.every(function (e) { return e.locked; });
    var b = function (run, ic, label, on) { return '<button type="button" class="pe-ibtn' + (on ? ' is-on' : '') + '" data-run="' + run + '" title="' + esc(label + (E.ACTIONS[run] && E.ACTIONS[run].key ? ' (' + E.ACTIONS[run].key + ')' : '')) + '" aria-label="' + esc(label) + '">' + icon(ic, 17) + '</button>'; };
    return '<div class="pe-actions">' + b('duplicate', 'copy', 'Dupliquer') + b('lock', allLocked ? 'lock' : 'unlock', allLocked ? 'Déverrouiller' : 'Verrouiller', allLocked) + b('hide', 'eyeoff', 'Masquer') + b('front', 'front', 'Premier plan') + b('back', 'back', 'Arrière-plan') +
      b(els.length > 1 ? 'group' : 'ungroup', 'group', els.length > 1 ? 'Grouper' : 'Dégrouper') + b('fitSel', 'fit', 'Cadrer la sélection') + '<span class="pe-grow"></span>' + b('delete', 'trash', 'Supprimer') + '</div>';
  }
  function propsTab() {
    var els = E.selected();
    if (!els.length) return emptyProps();
    var el = els[0], one = els.length === 1;
    var can = els.every(E.editable);
    var dis = can ? '' : ' disabled';
    var p = el.props;
    var def = KINDS[el.kind] || { label: el.kind };
    var shown = E.shownOf(el.uid) || el;
    var c = G.centroid(shown.kind === 'fan' ? { geom: 'point', pts: shown.pts, props: {} } : shown);
    var kinds = {};
    els.forEach(function (e) { kinds[(KINDS[e.kind] || {}).label || e.kind] = (kinds[(KINDS[e.kind] || {}).label || e.kind] || 0) + 1; });
    var h = '<div class="pe-prophead">' + (one ? '<span class="pe-prophead-ic">' + itemPreview({ kind: el.kind, props: el.kind === 'unit' ? p : null, noEchelon: false }, true).replace(/^<span[^>]*>|<\/span>$/g, '') + '</span>' : '') +
      '<div><h2>' + esc(one ? (el.kind === 'unit' ? unitLabel(el) : (p.label || def.label)) : els.length + ' éléments') + '</h2><p>' + (one ? esc(def.label) + ' · <span class="mono">' + G.grid(c[0], c[1], 8) + '</span>' : esc(Object.keys(kinds).map(function (k) { return kinds[k] + ' × ' + k; }).join(' · '))) + '</p></div></div>';
    if (!can) h += '<p class="toc-banner">' + (P.canEdit ? 'Verrouillé : déverrouillez l’élément ou son calque pour le changer.' : 'Lecture seule.') + '</p>';
    h += actionsRow(els);
    if (one && el.kind === 'unit') h = h.replace('<span class="pe-prophead-ic">' , '<span class="pe-prophead-ic is-unit">');
    h += '<div class="pe-subtabs" role="tablist">' + PROP_TABS.map(function (t) { return '<button type="button" role="tab" data-ptab="' + t[0] + '" aria-selected="' + (propTab === t[0]) + '">' + esc(t[1]) + '</button>'; }).join('') + '</div><div class="pe-form">';
    if (propTab === 'id') h += idTab(els, el, one, dis);
    if (propTab === 'style') h += styleTab(els, el, one, dis);
    if (propTab === 'pos') h += posTab(els, el, one, dis, shown);
    if (propTab === 'time') h += timeTab(els, el, one, dis);
    if (propTab === 'notes') h += elNotesTab(els, el, one, dis);
    return h + '</div>';
  }
  function emptyProps() {
    var els = E.list();
    var n = els.length;
    var units = els.filter(function (e) { return e.kind === 'unit'; }).length;
    var hidden = els.filter(function (e) { return e.props.hidden; }).length;
    var h = sec('Propriétés', n + ' élément(s)');
    if (!n) return h + '<div class="toc-empty"><p>Le plan est vide. Glissez un symbole de la bibliothèque sur la carte, ou choisissez un outil de dessin dans la barre du haut.</p>' + (P.canEdit ? '<div class="pe-stack"><button type="button" class="toc-btn is-primary" data-go-lib>Ouvrir la bibliothèque</button><button type="button" class="toc-btn" data-orbat="site">Importer l’ORBAT du site</button></div>' : '') + '</div>';
    h += '<div class="pe-kpis"><div><b class="mono">' + units + '</b><span>unités</span></div><div><b class="mono">' + (n - units) + '</b><span>graphismes</span></div><div><b class="mono">' + P.phases.length + '</b><span>phases</span></div></div>';
    h += '<p class="toc-note">Cliquez un élément pour le régler. Maj ou Ctrl+clic pour en choisir plusieurs, ou tracez un cadre dans le vide. Clic droit : menu. <kbd>Ctrl</kbd> <kbd>K</kbd> : toutes les commandes.</p>';
    if (hidden) h += '<button type="button" class="toc-btn is-block" data-run="showAll">Afficher les ' + hidden + ' élément(s) masqué(s)</button>';
    return h;
  }
  function idTab(els, el, one, dis) {
    var p = el.props, h = '';
    var allUnits = els.every(function (e) { return e.kind === 'unit'; });
    if (allUnits) {
      if (one) h += field('Indicatif', txt('callsign', p.callsign, 40, dis, ' id="prop-main"'));
      h += field('Affiliation', seg('affil', AFFILS.map(function (a) { return [a[0], a[1], E.AFFIL_COLOR[a[0]]]; }), p.affil, dis));
      if (!p.sidc) h += field('Fonction', '<select class="toc-select" data-prop="unitType"' + dis + '>' + opt((CAT.unitTypes || []).map(function (t) { return [t.id, t.label]; }), p.unitType) + '</select>');
      else h += field('Code du symbole (2525C)', txt('sidc', p.sidc, 30, dis, ' class="toc-input mono" spellcheck="false"'));
      h += '<div class="pe-row2">' + field('Échelon', '<select class="toc-select" data-prop="echelon"' + dis + '>' + opt(ECHELONS, p.echelon) + '</select>') + field('Statut', seg('status', [['present', 'Présent'], ['planned', 'Prévu']], p.status || 'present', dis)) + '</div>';
      h += field('Effectif', seg('mod', [['', 'Normal'], ['reinforced', 'Renforcé'], ['reduced', 'Réduit'], ['both', 'Les deux']], p.mod || '', dis));
      h += '<div class="pe-row2">' + check('hq', p.hq, 'Poste de commandement', dis) + check('tf', p.tf, 'Groupement', dis) + '</div>';
      if (one) {
        h += '<div class="pe-row2">' + field('Formation supérieure', txt('higher', p.higher, 30, dis)) + field('Informations', txt('info', p.info, 40, dis)) + '</div>';
        var others = E.list().filter(function (e) { return e.kind === 'unit' && e.uid !== el.uid && LG.descendants(E.list(), el.uid).indexOf(e.uid) < 0; });
        h += field('Unité mère (ORBAT)', '<select class="toc-select" data-parent' + dis + '><option value="">Aucune</option>' + others.map(function (o) { return '<option value="' + o.uid + '"' + (p.parent === o.uid ? ' selected' : '') + '>' + esc(unitLabel(o)) + '</option>'; }).join('') + '</select>');
        if (p.placed === false) h += '<p class="toc-banner">Unité dans l’ORBAT, pas encore sur la carte : glissez-la depuis l’onglet ORBAT.</p>';
      }
      return h;
    }
    if (one) {
      var isText = el.kind === 'text' || el.kind === 'textbox';
      h += field(isText ? 'Texte' : 'Libellé', isText && el.kind === 'textbox' ? '<textarea class="toc-textarea" rows="3" data-prop="label" maxlength="240" id="prop-main"' + dis + '>' + esc(p.label || '') + '</textarea>' : txt('label', p.label, el.kind === 'text' ? 120 : 60, dis, ' id="prop-main"'));
      if (el.kind === 'icon') h += field('Repère', '<select class="toc-select" data-prop="icon"' + dis + '>' + opt(ICON_NAMES, p.icon) + '</select>');
      if (el.kind === 'axis' || el.kind === 'attack') h += field('Largeur de l’axe (m, 0 = trait simple)', num('awidth', p.awidth || 0, 0, 5000, dis, 10));
      if (el.kind === 'boundary') h += field('Échelon de la limite', '<select class="toc-select" data-prop="echelon"' + dis + '>' + opt(ECHELONS, p.echelon || '') + '</select>');
      if (el.kind === 'ring') h += field('Cercles concentriques', range('rings', p.rings || 1, 1, 5, 1, dis));
      if (el.kind === 'route') h += '<div class="pe-row2">' + field('Vitesse (km/h, 0 = sans délais)', num('speed', p.speed || 0, 0, 200, dis, 1)) + check('cp', p.cp, 'Points de passage numérotés', dis) + '</div>' + (p.speed > 0 ? '<p class="toc-note">Parcours : ' + G.fmtDist(G.pathLength(el.pts)) + ' · ' + Math.round(G.pathLength(el.pts) / 1000 / p.speed * 60) + ' min.</p>' : '');
      if (el.kind === 'fan') {
        h += '<div class="pe-row2">' + field('Portée (m)', num('radius', Math.round(p.radius || 1000), 50, 40000, dis, 50)) + field('Portée minimale (m)', num('minRange', Math.round(p.minRange || 0), 0, 40000, dis, 50)) + '</div>';
        h += '<div class="pe-row2">' + field('Azimut (°)', num('bearing', Math.round(p.bearing || 0), 0, 360, dis, 1)) + field('Ouverture (°)', num('arc', Math.round(p.arc || 60), 5, 360, dis, 5)) + '</div>';
        h += check('mask', p.mask, 'Masquer ce que le relief cache (si le relief est relevé)', dis);
      }
    }
    h += '<p class="toc-note">' + esc((KINDS[el.kind] || {}).label || '') + (KINDS[el.kind] && KINDS[el.kind].game && KINDS[el.kind].game !== '*' ? ' · part en jeu comme « ' + esc(KINDS[el.kind].game) + ' »' : '') + '</p>';
    return h;
  }
  function styleTab(els, el, one, dis) {
    var p = el.props, h = '';
    var anyShape = els.some(function (e) { return e.geom !== 'point' || e.kind === 'fan'; });
    var anyLine = els.some(function (e) { return e.geom === 'line'; });
    var anyArea = els.some(function (e) { return e.geom !== 'line' && (e.geom !== 'point' || e.kind === 'fan'); });
    var anyPoint = els.some(function (e) { return e.geom === 'point' && e.kind !== 'fan'; });
    if (!els.every(function (e) { return e.kind === 'unit'; })) h += '<div class="pe-f"><span>Couleur</span>' + swatches(p.color, dis) + '</div>';
    else h += '<p class="toc-note">La couleur d’une unité suit son affiliation (onglet Identité).</p>';
    if (anyShape) h += field('Épaisseur du trait', range('width', p.width || 2, 1, 8, 1, dis));
    if (anyShape) h += '<div class="pe-row2">' + field('Trait', '<select class="toc-select" data-prop="dash"' + dis + '>' + opt([['solid', 'Plein'], ['dash', 'Tirets'], ['dot', 'Pointillé']], p.dash) + '</select>') + (anyLine ? field('Flèche', '<select class="toc-select" data-prop="arrow"' + dis + '>' + opt([['none', 'Sans'], ['end', 'Au bout'], ['both', 'Aux deux bouts']], p.arrow) + '</select>') : '') + '</div>';
    if (anyArea) h += field('Remplissage', range('fill', p.fill || 0, 0, 0.6, 0.02, dis));
    if (anyPoint) h += field(el.kind === 'text' ? 'Taille du texte' : 'Taille du symbole', range('size', p.size || 24, el.kind === 'text' ? 10 : 12, el.kind === 'text' ? 48 : 56, 1, dis));
    h += field('Opacité', range('opacity', p.opacity != null ? p.opacity : 1, 0.1, 1, 0.05, dis));
    if (anyShape) h += '<div class="pe-row2">' + field('Libellé', '<select class="toc-select" data-prop="labelPos"' + dis + '>' + opt([['auto', 'Automatique'], ['start', 'Au début'], ['middle', 'Au milieu'], ['end', 'À la fin'], ['none', 'Masqué']], p.labelPos || 'auto') + '</select>') + field('Taille du libellé', range('labelSize', p.labelSize || 14, 10, 32, 1, dis)) + '</div>';
    if (P.canEdit) h += '<div class="pe-stack is-row"><button type="button" class="toc-btn is-small" data-run="copyStyle">Copier le style</button><button type="button" class="toc-btn is-small" data-run="pasteStyle"' + (P.styleClip ? '' : ' disabled') + '>Coller le style</button></div>';
    return h;
  }
  function posTab(els, el, one, dis, shown) {
    var p = el.props, h = '';
    if (one) {
      var c = shown.geom === 'point' ? shown.pts[0] : G.centroid(shown);
      var canMove = shown.geom === 'point' || shown.geom === 'circle' || shown.geom === 'ellipse';
      h += '<div class="pe-row2">' + field('Grille à 8 chiffres', '<input class="toc-input mono" type="text" data-grid="8" value="' + G.grid(c[0], c[1], 8) + '" maxlength="12" inputmode="numeric"' + (canMove ? dis : ' disabled') + '>') + field('Grille à 10 chiffres', '<input class="toc-input mono" type="text" data-grid="10" value="' + LG.grid10(c[0], c[1]) + '" maxlength="14" inputmode="numeric"' + (canMove ? dis : ' disabled') + '>') + '</div>';
      var z = E.dem ? LG.demSample(E.dem, c[0], c[1]) : null;
      h += '<p class="toc-note mono">' + (z != null ? 'Altitude ' + Math.round(z) + ' m' : (E.dem ? 'Altitude : hors du relief relevé' : 'Altitude : relief non relevé pour ce terrain')) + (P.time != null && p.track ? ' · position à ' + LG.fmtH(P.time) : '') + '</p>';
      if (el.kind === 'unit') h += field('Direction de mouvement (°, vide = aucune)', num('dir', p.dir == null ? '' : Math.round(p.dir), 0, 360, dis, 5));
      if (el.geom === 'circle') h += field('Rayon (m)', num('radius', Math.round(p.radius), 5, 20000, dis, 10));
      if (el.geom === 'ellipse') h += '<div class="pe-row2">' + field('Rayon est-ouest (m)', num('rx', Math.round(p.rx), 5, 20000, dis, 10)) + field('Rayon nord-sud (m)', num('ry', Math.round(p.ry), 5, 20000, dis, 10)) + '</div>';
      if ((el.geom === 'point' && el.kind !== 'unit' && el.kind !== 'fan') || el.geom === 'ellipse') h += field('Rotation (°)', num('rotation', Math.round(p.rotation || 0), -360, 360, dis, 5));
      if (el.geom === 'line') h += '<p class="toc-note mono">Longueur ' + G.fmtDist(G.pathLength(el.pts)) + ' · ' + el.pts.length + ' points · azimut ' + Math.round(G.azimuth(el.pts[0], el.pts[el.pts.length - 1])) + '°</p>';
      if (el.geom !== 'line' && el.geom !== 'point') { var o = G.outline(el, 64); h += '<p class="toc-note mono">Surface ' + fmtArea(polyArea(o)) + ' · périmètre ' + G.fmtDist(G.pathLength(o.concat([o[0]]))) + '</p>'; }
    } else {
      var b = LG.bboxOf(els.map(function (e) { return E.shownOf(e.uid) || e; }));
      h += '<p class="toc-note mono">Emprise ' + G.fmtDist(b[2] - b[0]) + ' × ' + G.fmtDist(b[3] - b[1]) + ' · centre ' + G.grid((b[0] + b[2]) / 2, (b[1] + b[3]) / 2, 8) + '</p>';
    }
    if (els.length > 1 && P.canEdit) {
      var ab = function (id, label) { return '<button type="button" class="toc-btn is-small" data-run="align.' + id + '">' + esc(label) + '</button>'; };
      h += '<div class="pe-f"><span>Aligner</span><div class="pe-grid3">' + ab('left', 'Gauche') + ab('hcenter', 'Centre') + ab('right', 'Droite') + ab('top', 'Haut') + ab('vcenter', 'Milieu') + ab('bottom', 'Bas') + '</div></div>';
      h += '<div class="pe-f"><span>Répartir (3 éléments ou plus)</span><div class="pe-grid3" style="--cols:2">' + ab('dh', 'Horizontalement') + ab('dv', 'Verticalement') + '</div></div>';
    }
    h += field('Calque', '<select class="toc-select" data-layer-sel' + dis + '>' + (P.meta.layers || []).map(function (l) { return '<option value="' + l.uid + '"' + (el.layer === l.uid ? ' selected' : '') + '>' + esc(l.name) + '</option>'; }).join('') + '</select>');
    if (P.canEdit) h += '<p class="toc-note">Flèches du clavier : 10 m · avec Maj : 100 m · avec Alt : 1 m.</p>';
    return h;
  }
  function timeTab(els, el, one, dis) {
    var p = el.props, h = '';
    h += '<div class="pe-f"><span>Phases (aucune cochée : toutes)</span>' + (P.phases.length ? P.phases.map(function (ph, i) {
      var onAll = els.every(function (e) { return (e.phases || []).indexOf(ph.uid) >= 0; });
      return '<label class="pe-check"><input type="checkbox" data-phase="' + ph.uid + '"' + (onAll ? ' checked' : '') + dis + '><span>' + (i + 1) + '. ' + esc(ph.name) + '</span></label>';
    }).join('') : '<p class="toc-note">Pas encore de phase : ajoutez-en dans la frise du bas ou l’onglet Plan.</p>') + '</div>';
    h += '<div class="pe-row2">' + field('Apparaît à', '<input class="toc-input mono" type="text" data-tw="t0" placeholder="H+0:30" value="' + (p.t0 != null ? LG.fmtH(p.t0) : '') + '"' + dis + '>') + field('Disparaît à', '<input class="toc-input mono" type="text" data-tw="t1" placeholder="toujours" value="' + (p.t1 != null ? LG.fmtH(p.t1) : '') + '"' + dis + '>') + '</div>';
    if (one && el.geom === 'point' && el.kind !== 'fan') {
      var tr = p.track || [];
      h += '<div class="pe-f"><span>Images clés (positions dans le temps)</span>';
      if (!tr.length) h += '<p class="toc-note">Aucune : l’unité reste en place. Placez la frise sur un instant, activez « Clé auto » puis déplacez l’unité : elle ira d’une position à l’autre.</p>';
      else h += '<table class="toc-table pe-keys"><tbody>' + tr.map(function (k) { return '<tr' + (P.time != null && Math.abs(k.t - P.time) < 0.01 ? ' class="is-sel"' : '') + '><td class="mono"><a href="#" data-goto-t="' + k.t + '">' + LG.fmtH(k.t) + '</a></td><td class="mono">' + G.grid(k.p[0], k.p[1], 8) + '</td><td class="num">' + (P.canEdit ? '<button type="button" class="pe-node-btn is-keep" data-key-del="' + k.t + '" aria-label="Retirer l’image clé de ' + LG.fmtH(k.t) + '"' + dis + '>' + icon('trash', 14) + '</button>' : '') + '</td></tr>'; }).join('') + '</tbody></table>';
      if (P.canEdit) h += '<button type="button" class="toc-btn is-small is-block" data-key-add' + dis + (P.time == null ? ' disabled title="Choisissez d’abord un instant sur la frise"' : '') + '>' + icon('key', 14) + ' Image clé ici' + (P.time != null ? ' (' + LG.fmtH(P.time) + ')' : '') + '</button>';
      h += '</div>';
      var routes = E.list().filter(function (e) { return e.geom === 'line' && e.kind !== 'textbox'; });
      h += field('Suit le tracé', '<select class="toc-select" data-prop="route"' + dis + '><option value="">En ligne droite</option>' + routes.map(function (r) { return '<option value="' + r.uid + '"' + (p.route === r.uid ? ' selected' : '') + '>' + esc((r.props.label || (KINDS[r.kind] || {}).label || 'Tracé') + ' · ' + G.fmtDist(G.pathLength(r.pts))) + '</option>'; }).join('') + '</select>');
    }
    return h;
  }
  function elNotesTab(els, el, one, dis) {
    var h = '';
    if (one) h += field('Note', '<textarea class="toc-textarea" rows="4" maxlength="400" data-prop="note"' + dis + '>' + esc(el.props.note || '') + '</textarea>');
    else h += '<p class="toc-note">Choisissez un seul élément pour lire ou écrire sa note.</p>';
    if (one && !P.readonlyLink) {
      var cs = P.comments.filter(function (c) { return c.element_uid === el.uid; });
      h += '<div class="pe-f"><span>Commentaires sur cet élément</span>' + (cs.length ? cs.map(commentCard).join('') : '<p class="toc-note">Aucun commentaire.</p>') + '</div>';
      h += '<form class="pe-form" data-form="comment" data-el="' + el.uid + '"><textarea class="toc-textarea" name="body" rows="2" maxlength="2000" placeholder="Commentaire sur cet élément…" aria-label="Commentaire sur cet élément"></textarea><button class="toc-btn is-small is-primary" type="submit">Publier</button></form>';
    }
    h += '<p class="toc-note">Les pièces jointes ne sont pas encore proposées sur un élément.</p>';
    return h;
  }
  function commentCard(c) {
    return '<div class="toc-card' + (c.resolved ? ' is-done' : '') + '"><p><b>' + esc(c.author) + '</b> · <span class="mono">' + esc(String(c.created_at).slice(5, 16)) + '</span>' + (c.element_uid && P.els[c.element_uid] ? ' · <a href="#" data-goto="' + esc(c.element_uid) + '">voir l’élément</a>' : '') + '</p><p>' + esc(c.body) + '</p>' + (P.readonlyLink ? '' : '<button type="button" class="toc-btn is-small" data-resolve="' + c.id + '" data-state="' + (c.resolved ? 0 : 1) + '">' + (c.resolved ? 'Rouvrir' : 'Réglé') + '</button>') + '</div>';
  }
  var NOTE_TITLES = [['situation', '1. Situation'], ['mission', '2. Mission'], ['execution', '3. Exécution'], ['support', '4. Soutien'], ['command', '5. Commandement et transmissions'], ['free', 'Notes libres']];
  function planTab() {
    var n = P.meta.notes || {};
    var dis = P.canEdit ? '' : ' disabled';
    var starts = LG.phaseStarts(P.phases, P.meta.timeline);
    var h = sec('Phases', P.phases.length + ' phase(s)');
    if (!P.phases.length) h += '<div class="toc-empty"><p>Pas de phase : le plan se présente d’un bloc. Ajoutez des phases pour briefer pas à pas et faire bouger les unités dans le temps.</p></div>';
    h += P.phases.map(function (ph, i) {
      var count = E.list().filter(function (e) { return (e.phases || []).indexOf(ph.uid) >= 0; }).length;
      return '<div class="toc-card pe-phase' + (P.phase === ph.uid ? ' is-on' : '') + '" data-phase-card="' + ph.uid + '"><div class="pe-phase-head"><b class="mono">' + (i + 1) + '</b><input class="toc-input" data-ph="name" maxlength="80" value="' + esc(ph.name) + '" aria-label="Nom de la phase"' + dis + '><input class="toc-input mono" data-ph-start maxlength="10" value="' + LG.fmtH(starts[i]) + '" aria-label="Début de la phase" title="Début de la phase (H+0:30)"' + dis + '></div>' +
        '<textarea class="toc-textarea" rows="2" data-ph="note" maxlength="2000" placeholder="Ce qui se passe dans cette phase…" aria-label="Note de la phase"' + dis + '>' + esc(ph.note || '') + '</textarea>' +
        '<div class="pe-stack is-row"><button type="button" class="toc-btn is-small" data-ph-act="show">Afficher</button>' + (P.canEdit ? '<button type="button" class="toc-btn is-small" data-ph-act="assign"' + (P.sel.length ? '' : ' disabled') + ' title="Mettre la sélection dans cette phase">+ sélection</button><button type="button" class="toc-btn is-small" data-ph-act="up"' + (i ? '' : ' disabled') + ' aria-label="Monter la phase">↑</button><button type="button" class="toc-btn is-small" data-ph-act="del" aria-label="Retirer la phase">' + icon('trash', 14) + '</button>' : '') + '<span class="toc-muted">' + count + ' él. propres</span></div></div>';
    }).join('');
    if (P.canEdit) h += '<button type="button" class="toc-btn is-primary is-block" data-phase-add>Ajouter une phase</button>';
    h += sec('Ordre d’opération', 'SMEAC, facultatif');
    h += '<div class="pe-form"><div class="pe-row2">' + field('Camp du plan', '<select class="toc-select" data-meta="side"' + dis + '>' + opt(Object.keys(E.SIDES).map(function (s) { return [s, E.SIDES[s]]; }), P.meta.side) + '</select>') + field('Groupe date-heure', '<input class="toc-input mono" data-meta="dtg" maxlength="40" placeholder="111800ZNOV26" value="' + esc(P.meta.dtg || '') + '"' + dis + '>') + '</div>';
    NOTE_TITLES.forEach(function (t) { h += field(t[1], '<textarea class="toc-textarea" rows="3" maxlength="6000" data-note="' + t[0] + '"' + dis + '>' + esc(n[t[0]] || '') + '</textarea>'); });
    h += '</div>' + sec('Légende', 'imprimée sur la fiche');
    var seen = {};
    E.list().forEach(function (e) { var k = (KINDS[e.kind] || {}).label || e.kind; seen[k] = e.props.color; });
    h += '<p class="toc-note">' + (Object.keys(seen).length ? 'Types utilisés : ' + Object.keys(seen).map(function (k) { return '<span style="color:' + esc(seen[k]) + '">■</span> ' + esc(k); }).join(' · ') : 'Le plan est vide.') + '</p>';
    h += (P.meta.legend || []).map(function (l, i) { return '<div class="pe-legend"><input type="color" data-lg-c="' + i + '" value="' + esc(l.color) + '"' + dis + ' aria-label="Couleur"><input class="toc-input" data-lg-l="' + i + '" value="' + esc(l.label) + '" maxlength="60"' + dis + ' aria-label="Libellé"><button type="button" class="pe-node-btn is-keep" data-lg-d="' + i + '"' + dis + ' aria-label="Retirer la ligne">' + icon('trash', 15) + '</button></div>'; }).join('');
    if (P.canEdit) h += '<button type="button" class="toc-btn is-block" data-legend-add>Ajouter une ligne de légende</button>';
    return h;
  }
  function notesTab() {
    var h = sec('Échanges', P.presence.length + ' membre(s) sur le plan');
    h += '<div class="plan-presence">' + (P.presence.length ? P.presence.map(function (p) { return '<span>' + esc(p.label) + (p.tool ? ' · ' + esc(p.tool) : '') + '</span>'; }).join('') : '<span>Vous seul</span>') + '</div>';
    if (!P.readonlyLink) h += '<form class="pe-form" data-form="comment"><textarea class="toc-textarea" name="body" rows="2" maxlength="2000" placeholder="Commentaire sur le plan…" aria-label="Commentaire sur le plan"></textarea><button class="toc-btn is-primary is-block" type="submit">Publier</button></form>';
    if (!P.comments.length) return h + '<div class="toc-empty"><p>Aucun commentaire sur ce plan.</p></div>';
    return h + P.comments.map(commentCard).join('');
  }

  /* ---------- Saisie dans le panneau de droite ---------- */
  var propTimer = null;
  function setProp(key, val) {
    E.editSelected(function (c) {
      if (val === '' && (key === 'dir')) c.props[key] = null; else c.props[key] = val;
      if (key === 'affil' && c.kind === 'unit') c.props.color = E.AFFIL_COLOR[val] || c.props.color;
      if (key === 'radius' && c.kind === 'fan') c.props.minRange = Math.min(c.props.minRange || 0, val);
      if (key === 'sidc') c.props.sidc = String(val).toUpperCase().replace(/[^A-Z0-9*-]/g, '');
      return c;
    });
  }
  function onRightInput(e) {
    var t = e.target;
    if (t.hasAttribute('data-prop')) {
      var k = t.getAttribute('data-prop');
      var v = t.hasAttribute('data-bool') ? t.checked : (t.hasAttribute('data-num') ? (t.value === '' ? '' : Number(t.value)) : t.value);
      if (t.type === 'range') { var o = t.parentNode.querySelector('output'); if (o) o.textContent = t.value; }
      if (e.type === 'input' && (t.tagName === 'SELECT' || t.type === 'checkbox')) return;
      if (e.type === 'change' && (t.type === 'text' || t.type === 'number' || t.tagName === 'TEXTAREA' || t.type === 'range')) { clearTimeout(propTimer); setProp(k, v); return; }
      clearTimeout(propTimer);
      propTimer = setTimeout(function () { setProp(k, v); }, e.type === 'input' ? 260 : 0);
      return;
    }
    if (t.hasAttribute('data-color-input')) { clearTimeout(propTimer); propTimer = setTimeout(function () { E.editSelected(function (c) { if (c.kind !== 'unit') c.props.color = t.value; return c; }); }, 120); return; }
    if (e.type !== 'change' && !t.hasAttribute('data-ph') && !t.hasAttribute('data-note') && !t.hasAttribute('data-meta') && !t.hasAttribute('data-lg-l') && !t.hasAttribute('data-lg-c')) return;
    if (t.hasAttribute('data-phase')) {
      var ph = t.getAttribute('data-phase');
      E.editSelected(function (c) { c.phases = (c.phases || []).filter(function (x) { return x !== ph; }); if (t.checked) c.phases.push(ph); return c; });
      return;
    }
    if (t.hasAttribute('data-layer-sel')) { E.editSelected(function (c) { c.layer = t.value; return c; }); return; }
    if (t.hasAttribute('data-parent')) {
      var el = E.selected()[0];
      var res = el ? LG.reparent(E.list(), el.uid, t.value, null) : null;
      if (res) { Object.keys(res).forEach(function (u) { res[u].props.color = E.AFFIL_COLOR[res[u].props.affil] || res[u].props.color; }); E.commit(res, 'ORBAT réorganisé'); } else E.say('Rattachement impossible.', true);
      return;
    }
    if (t.hasAttribute('data-grid')) {
      var at = G.parseGrid(t.value);
      if (!at) { E.say('Grille illisible : 8 chiffres (0425 1169) ou 10 chiffres.', true); return; }
      // Une grille saisie désigne le coin de la case quand elle est à 10 chiffres, sinon son centre.
      E.editSelected(function (c) {
        var sh = E.shownOf(c.uid) || c;
        var cur = sh.geom === 'point' ? sh.pts[0] : G.centroid(sh);
        return E.moveEl(c, at[0] - cur[0], at[1] - cur[1]);
      }, 'Déplacé à la grille ' + t.value);
      return;
    }
    if (t.hasAttribute('data-tw')) {
      var key = t.getAttribute('data-tw');
      var tv = t.value.trim() === '' ? null : LG.parseH(t.value);
      if (t.value.trim() !== '' && tv == null) { E.say('Heure illisible : H+0:30, H-15 ou un nombre de minutes.', true); return; }
      E.editSelected(function (c) { c.props[key] = tv; return c; });
      return;
    }
    if (t.hasAttribute('data-ph') || t.hasAttribute('data-ph-start')) {
      var card = t.closest('[data-phase-card]');
      var ph2 = P.phases.filter(function (x) { return x.uid === card.getAttribute('data-phase-card'); })[0];
      if (!ph2) return;
      if (t.hasAttribute('data-ph-start')) {
        var st = LG.parseH(t.value);
        if (st == null) { E.say('Heure illisible : H+0:30, H-15 ou un nombre de minutes.', true); return; }
        P.meta.timeline.phases[ph2.uid] = st; ph2.time_label = LG.fmtH(st);
        E.metaChanged(); E.phasesChanged(); E.emit('time');
      } else { ph2[t.getAttribute('data-ph')] = t.value; E.phasesChanged(); E.emit('phases'); }
      return;
    }
    if (t.hasAttribute('data-note')) { P.meta.notes = P.meta.notes || {}; P.meta.notes[t.getAttribute('data-note')] = t.value; E.metaChanged(); return; }
    if (t.hasAttribute('data-meta')) { P.meta[t.getAttribute('data-meta')] = t.value; E.metaChanged(); return; }
    if (t.hasAttribute('data-lg-c') || t.hasAttribute('data-lg-l')) {
      var i = Number(t.getAttribute('data-lg-c') != null ? t.getAttribute('data-lg-c') : t.getAttribute('data-lg-l'));
      var row = P.meta.legend[i];
      if (row) { if (t.hasAttribute('data-lg-c')) row.color = t.value; else row.label = t.value; E.metaChanged(); }
    }
  }
  function addPhase() {
    var starts = LG.phaseStarts(P.phases, P.meta.timeline);
    var tl = P.meta.timeline;
    var last = starts.length ? starts[starts.length - 1] : tl.start;
    var uid = G.uid('p');
    var t = P.phases.length ? Math.min(tl.start + tl.dur, last + Math.max(10, Math.round(tl.dur / 8))) : tl.start;
    // Les débuts déjà répartis automatiquement deviennent des débuts réglés : ils ne bougent plus quand une phase arrive.
    P.phases.forEach(function (ph, i) { if (typeof tl.phases[ph.uid] !== 'number') tl.phases[ph.uid] = Math.round(starts[i]); });
    tl.phases[uid] = Math.round(t);
    P.phases.push({ uid: uid, name: 'Phase ' + (P.phases.length + 1), note: '', time_label: LG.fmtH(t) });
    E.metaChanged(); E.phasesChanged(); E.emit('phases'); renderRight(true);
  }
  E.addPhase = addPhase;
  function phaseAction(act, uid) {
    var i = P.phases.map(function (p) { return p.uid; }).indexOf(uid);
    if (i < 0) return;
    if (act === 'show') { E.setPhase(uid); return; }
    if (act === 'assign') { E.editSelected(function (c) { c.phases = (c.phases || []).filter(function (x) { return x !== uid; }); c.phases.push(uid); return c; }, 'Sélection mise dans « ' + P.phases[i].name + ' »'); return; }
    if (act === 'up' && i > 0) {
      var a = P.phases[i - 1], b = P.phases[i];
      var st = LG.phaseStarts(P.phases, P.meta.timeline);
      P.phases[i - 1] = b; P.phases[i] = a;
      P.meta.timeline.phases[b.uid] = st[i - 1]; P.meta.timeline.phases[a.uid] = st[i];
      E.metaChanged();
    }
    if (act === 'del') {
      if (!window.confirm('Retirer la phase « ' + P.phases[i].name + ' » ? Ses éléments restent dans le plan.')) return;
      P.phases.splice(i, 1);
      delete P.meta.timeline.phases[uid];
      E.metaChanged();
      if (P.phase === uid) E.setPhase(null);
    }
    E.phasesChanged(); E.emit('phases'); renderRight(true);
  }
  function onRightClick(e) {
    var t = e.target.closest('button, a');
    if (!t) return;
    if (t.tagName === 'A') e.preventDefault();
    if (t.hasAttribute('data-ptab')) { propTab = t.getAttribute('data-ptab'); renderRight(true); return; }
    if (t.hasAttribute('data-set')) { setProp(t.getAttribute('data-set'), t.getAttribute('data-val')); return; }
    if (t.hasAttribute('data-color')) { E.editSelected(function (c) { if (c.kind !== 'unit') c.props.color = t.getAttribute('data-color'); return c; }); return; }
    if (t.hasAttribute('data-goto')) { var el = P.els[t.getAttribute('data-goto')]; if (el) { E.tm.centerOn(G.centroid(E.shownOf(el.uid) || el)); E.select([el.uid]); setRightTab('props'); } return; }
    if (t.hasAttribute('data-goto-t')) { E.setTime(Number(t.getAttribute('data-goto-t'))); return; }
    if (t.hasAttribute('data-resolve')) { E.api('/atak/toc/api/plans/' + P.id + '/comments', { resolve: Number(t.getAttribute('data-resolve')), resolved: t.getAttribute('data-state') === '1' }).then(function (j) { if (j && j.ok) { P.comments = j.comments; renderRight(true); } }); return; }
    if (t.hasAttribute('data-key-add')) { var u = E.selected()[0]; if (u && P.time != null) { var cur = LG.positionAt(u, P.time, P.els); var k = LG.setKeyframe(u, P.time, cur, P.meta.timeline.start); if (k) { var m = {}; m[k.uid] = k; E.commit(m, 'Image clé posée à ' + LG.fmtH(P.time)); } else E.say('60 images clés au plus par unité.', true); } return; }
    if (t.hasAttribute('data-key-del')) { var kt = Number(t.getAttribute('data-key-del')); E.editSelected(function (c) { return LG.removeKeyframe(c, kt); }, 'Image clé retirée'); return; }
    if (t.hasAttribute('data-ph-act')) { phaseAction(t.getAttribute('data-ph-act'), t.closest('[data-phase-card]').getAttribute('data-phase-card')); return; }
    if (t.hasAttribute('data-phase-add')) { addPhase(); return; }
    if (t.hasAttribute('data-legend-add')) { P.meta.legend = P.meta.legend || []; P.meta.legend.push({ color: '#f2f2f2', label: 'Nouvelle ligne' }); E.metaChanged(); renderRight(true); return; }
    if (t.hasAttribute('data-lg-d')) { P.meta.legend.splice(Number(t.getAttribute('data-lg-d')), 1); E.metaChanged(); renderRight(true); return; }
    if (t.hasAttribute('data-go-lib')) { setLeftTab('lib'); var s = $('#pe-lib-search'); if (s) s.focus(); return; }
    if (t.hasAttribute('data-orbat')) importOrbat(t.getAttribute('data-orbat'));
  }
  function onSubmit(e) {
    var f = e.target.closest('form[data-form="comment"]');
    if (!f) return;
    e.preventDefault();
    var body = f.elements.body.value.trim();
    if (!body) return;
    E.api('/atak/toc/api/plans/' + P.id + '/comments', { body: body, element_uid: f.getAttribute('data-el') || null }).then(function (j) {
      if (j && j.ok) { P.comments = j.comments; f.elements.body.value = ''; f.elements.body.blur(); renderRight(true); E.say('Commentaire publié.'); } else E.say(E.ERR[j && j.error] || 'Commentaire refusé.', true);
    });
  }

  /* ---------- Saisie dans le panneau de gauche ---------- */
  function onLeftClick(e) {
    if (P.dragJustEnded && Date.now() - P.dragJustEnded < 250) return;
    var t = e.target;
    var fav = t.closest('[data-fav]');
    if (fav) { var id = fav.getAttribute('data-fav'); var i = libFav.indexOf(id); if (i >= 0) libFav.splice(i, 1); else libFav.unshift(id); save('fav', libFav); renderLibrary(); return; }
    var tile = t.closest('[data-lib]');
    if (tile) { var it = libFind(tile.getAttribute('data-lib')); if (it) armItem(it); return; }
    var af = t.closest('[data-lib-affil]');
    if (af) { P.lib.affil = af.getAttribute('data-lib-affil'); $$('[data-lib-affil]').forEach(function (b) { b.setAttribute('aria-pressed', String(b === af)); }); renderLibrary(); return; }
    var car = t.closest('[data-caret]');
    if (car) { var u = car.getAttribute('data-caret'); if (collapsed[u]) delete collapsed[u]; else collapsed[u] = 1; save('orbat-collapsed', collapsed); renderOrbat(); return; }
    var eye = t.closest('[data-node-eye]');
    if (eye) { E.toggleHidden([eye.getAttribute('data-node-eye')]); return; }
    var lk = t.closest('[data-node-lock]');
    if (lk) { var le = P.els[lk.getAttribute('data-node-lock')]; if (le && P.canEdit) { var c = G.clone(le); c.locked = !c.locked; var m = {}; m[c.uid] = c; E.commit(m, c.locked ? 'Verrouillé' : 'Déverrouillé'); } return; }
    var node = t.closest('[data-node]');
    if (node) { E.select([node.getAttribute('data-node')], e.shiftKey || e.ctrlKey || e.metaKey); setRightTab('props'); return; }
    var ob = t.closest('[data-orbat]');
    if (ob) { importOrbat(ob.getAttribute('data-orbat')); return; }
    if (t.closest('[data-unit-add]')) { if (!P.canEdit) return; var nu = E.newElement('unit', 'point', [[0, 0]], { placed: false, callsign: 'UNITÉ ' + (E.list().filter(function (x) { return x.kind === 'unit'; }).length + 1), parent: E.selected()[0] && E.selected()[0].kind === 'unit' ? E.selected()[0].uid : '' }); nu.phases = []; var mm = {}; mm[nu.uid] = nu; E.commit(mm, 'Unité ajoutée à l’ORBAT : glissez-la sur la carte'); E.setSel([nu.uid]); return; }
    // Calques
    var b = t.closest('button');
    if (!b) return;
    var l;
    if (b.hasAttribute('data-lv')) { l = E.layerOf(b.getAttribute('data-lv')); if (l) { l.visible = l.visible === false; E.metaChanged(); E.renderAll(); } return; }
    if (b.hasAttribute('data-ll')) { l = E.layerOf(b.getAttribute('data-ll')); if (l && P.canEdit) { l.locked = !l.locked; E.metaChanged(); E.renderAll(); } return; }
    if (b.hasAttribute('data-lyr-active')) { P.layer = b.getAttribute('data-lyr-active'); renderLayers(); return; }
    if (b.hasAttribute('data-ld')) { removeLayer(b.getAttribute('data-ld')); return; }
    if (b.hasAttribute('data-layer-add')) { if ((P.meta.layers || []).length >= 16) { E.say('16 calques au plus.', true); return; } var nl = { uid: G.uid('l'), name: 'Calque ' + (P.meta.layers.length + 1), visible: true, locked: false, opacity: 1 }; P.meta.layers.push(nl); P.layer = nl.uid; E.metaChanged(); renderLayers(); }
  }
  function onLeftInput(e) {
    var t = e.target;
    if (t.id === 'pe-lib-search') { libQuery = t.value; renderLibrary(); return; }
    if (t.id === 'pe-orbat-search') { orbatQuery = t.value; renderOrbat(); return; }
    if (t.id === 'pe-lib-echelon') { P.lib.echelon = t.value; renderLibrary(); return; }
    var l;
    if (t.hasAttribute('data-ln') && e.type === 'change') { l = E.layerOf(t.getAttribute('data-ln')); if (l && t.value.trim()) { l.name = t.value.trim(); E.metaChanged(); } return; }
    if (t.hasAttribute('data-lo')) { l = E.layerOf(t.getAttribute('data-lo')); if (l) { l.opacity = Number(t.value); E.metaChanged(); clearTimeout(P.loTimer); P.loTimer = setTimeout(E.renderAll, 60); } }
  }

  /* ---------- Palette de commandes (Ctrl+K) ---------- */
  var pal = { open: false, items: [], idx: 0 };
  function paletteItems(q) {
    var out = [];
    var add = function (group, label, hint, fn, extra) { var s = LG.fuzzyScore(q, label + ' ' + (extra || '')); if (s > 0) out.push({ group: group, label: label, hint: hint || '', run: fn, score: s }); };
    var grid = G.parseGrid(q);
    if (grid && /^[\d\s]+$/.test(q.trim())) out.push({ group: 'Carte', label: 'Aller à la grille ' + q.trim(), hint: '', run: function () { goTo(grid); }, score: 5000 });
    var hm = LG.parseH(q);
    if (hm != null && /^h/i.test(q.trim())) out.push({ group: 'Temps', label: 'Aller à ' + LG.fmtH(hm), hint: '', run: function () { E.setTime(hm); }, score: 4000 });
    Object.keys(E.ACTIONS).forEach(function (id) { var a = E.ACTIONS[id]; if (a.edit && !P.canEdit) return; add(a.group || 'Commandes', a.label, a.key, function () { E.run(id); }); });
    if (q.trim()) {
      (libItems || buildLibrary()).forEach(function (it) { if (P.canEdit) add('Poser', it.label, 'bibliothèque', function () { armItem(it); }, it.kw); });
      E.list().forEach(function (el) {
        var name = el.kind === 'unit' ? unitLabel(el) : (el.props.label || '');
        if (!name) return;
        add('Aller à', name, (KINDS[el.kind] || {}).label, function () { E.setSel([el.uid]); if (el.props.placed !== false) goTo(G.centroid(E.shownOf(el.uid) || el)); setRightTab('props'); });
      });
      (P.meta.layers || []).forEach(function (l) { add('Calque actif', l.name, 'calque', function () { P.layer = l.uid; setLeftTab('layers'); }); });
      P.phases.forEach(function (ph, i) { add('Phase', (i + 1) + '. ' + ph.name, ph.time_label, function () { E.setPhase(ph.uid); }); });
      (E.tm ? E.tm.backgrounds : []).forEach(function (b) { add('Fond de carte', b.label, '', function () { E.setBackground(b.id); }); });
    }
    out.sort(function (a, b) { return b.score - a.score; });
    return out.slice(0, 40);
  }
  function goTo(p) {
    if (P.mode === '3d' && E.view3d) { E.view3d.setView({ x: p[0], y: p[1], mpp: Math.min(E.view3d.getView().mpp, 4) }); return; }
    E.tm.centerOn(p);
  }
  E.goTo = goTo;
  function renderPalette() {
    var q = $('#pe-pal-input').value;
    pal.items = paletteItems(q);
    pal.idx = Math.min(pal.idx, Math.max(0, pal.items.length - 1));
    var h = '', last = '';
    pal.items.forEach(function (it, i) {
      if (it.group !== last && !q.trim()) { h += '<li class="pe-pal-group" role="presentation">' + esc(it.group) + '</li>'; last = it.group; }
      h += '<li role="option" id="pe-pal-' + i + '" data-pal="' + i + '" aria-selected="' + (i === pal.idx) + '"><span>' + (q.trim() ? '<small>' + esc(it.group) + '</small> ' : '') + esc(it.label) + '</span>' + (it.hint ? '<kbd>' + esc(it.hint) + '</kbd>' : '') + '</li>';
    });
    $('#pe-pal-list').innerHTML = h || '<li class="pe-pal-empty" role="presentation">Rien ne correspond. Essayez un nom d’unité, une commande ou une grille (0425 1169).</li>';
    $('#pe-pal-input').setAttribute('aria-activedescendant', pal.items.length ? 'pe-pal-' + pal.idx : '');
    var cur = $('#pe-pal-' + pal.idx);
    if (cur && cur.scrollIntoView) cur.scrollIntoView({ block: 'nearest' });
  }
  function openPalette(prefill) {
    pal.open = true; pal.idx = 0;
    $('#pe-palette').hidden = false;
    var i = $('#pe-pal-input');
    i.value = prefill || '';
    renderPalette();
    i.focus();
  }
  function closePalette() { pal.open = false; $('#pe-palette').hidden = true; }
  function runPalette(i) {
    var it = pal.items[i];
    closePalette();
    if (it) it.run();
  }

  /* ---------- Menus du clic droit ---------- */
  function openMenu(x, y, items) {
    var m = $('#pe-menu');
    m.innerHTML = items.map(function (it, i) {
      if (it === '-') return '<li class="pe-menu-sep" role="separator"></li>';
      return '<li role="menuitem" tabindex="-1" data-mi="' + i + '"' + (it.off ? ' aria-disabled="true"' : '') + '><span>' + esc(it.label) + '</span>' + (it.key ? '<kbd>' + esc(it.key) + '</kbd>' : '') + '</li>';
    }).join('');
    m.hidden = false;
    m._items = items;
    var r = m.getBoundingClientRect();
    m.style.left = Math.max(4, Math.min(x, window.innerWidth - r.width - 6)) + 'px';
    m.style.top = Math.max(4, Math.min(y, window.innerHeight - r.height - 6)) + 'px';
    var first = m.querySelector('[role="menuitem"]:not([aria-disabled])');
    if (first) first.focus();
  }
  function closeMenu() { var m = $('#pe-menu'); if (m) m.hidden = true; }
  function act(id, label) { var a = E.ACTIONS[id]; return { label: label || a.label, key: a.key, run: function () { E.run(id); }, off: a.edit && !P.canEdit }; }
  function contextItems(ctx) {
    var sel = E.selected();
    var items = [];
    if (sel.length) {
      items.push(act('copy'), act('cut'), act('duplicate'), act('paste'), '-');
      items.push(act('forward'), act('backward'), act('front'), act('back'), '-');
      items.push(act(sel.length > 1 ? 'group' : 'ungroup'), act('lock', sel.every(function (e) { return e.locked; }) ? 'Déverrouiller' : 'Verrouiller'), act('hide'), '-');
      items.push(act('copyStyle'), act('pasteStyle'), act('selectKind'), act('selectLayer'), '-');
      if (sel.length === 1 && sel[0].geom === 'point' && sel[0].kind !== 'fan' && P.time != null && P.canEdit) items.push({ label: 'Image clé ici (' + LG.fmtH(P.time) + ')', run: function () { var k = LG.setKeyframe(sel[0], P.time, LG.positionAt(sel[0], P.time, P.els), P.meta.timeline.start); if (k) { var m = {}; m[k.uid] = k; E.commit(m, 'Image clé posée'); } } });
      if (sel.length === 1 && ctx.world && E.dem && E.terrainTool) items.push({ label: 'Ligne de vue depuis cet élément…', run: function () { E.terrainTool('los', G.centroid(E.shownOf(sel[0].uid) || sel[0])); } }, { label: 'Zone vue depuis cet élément', run: function () { E.terrainTool('viewshed', G.centroid(E.shownOf(sel[0].uid) || sel[0])); } });
      items.push(act('delete'));
    } else {
      items.push(act('paste'), act('pasteInPlace'), act('selectAll'), '-');
      if (ctx.world) {
        items.push({ label: 'Poser une unité ici', off: !P.canEdit, run: function () { E.placePoint('unit', ctx.world, { affil: P.lib.affil, echelon: P.lib.echelon }); } });
        items.push({ label: 'Poser un texte ici', off: !P.canEdit, run: function () { E.placePoint('text', ctx.world); E.emit('edit'); } });
        items.push({ label: 'Copier la grille ' + G.grid(ctx.world[0], ctx.world[1], 8), run: function () { var g = G.grid(ctx.world[0], ctx.world[1], 8); if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(g).then(function () { E.say('Grille ' + g + ' copiée'); }, function () { E.say('Grille ' + g); }); else E.say('Grille ' + g); } });
        if (E.terrainTool) items.push({ label: 'Zone vue depuis ce point', off: !E.dem, run: function () { E.terrainTool('viewshed', ctx.world); } });
        items.push('-');
      }
      items.push(act('fit'), act('grid'), act('background'), act('showAll'));
    }
    return items.filter(function (it, i, a) { return !(it === '-' && (i === 0 || a[i - 1] === '-' || i === a.length - 1)); });
  }

  /* ---------- Dépôt de fichiers ---------- */
  function bindFileDrop() {
    var wrap = $('#plan-mapwrap');
    var veil = $('#pe-dropveil');
    var depth = 0;
    var hasFiles = function (ev) { return ev.dataTransfer && Array.prototype.indexOf.call(ev.dataTransfer.types || [], 'Files') >= 0; };
    wrap.addEventListener('dragenter', function (ev) { if (!hasFiles(ev)) return; ev.preventDefault(); depth++; veil.hidden = false; });
    wrap.addEventListener('dragover', function (ev) { if (!hasFiles(ev)) return; ev.preventDefault(); ev.dataTransfer.dropEffect = P.canEdit ? 'copy' : 'none'; });
    wrap.addEventListener('dragleave', function (ev) { if (!hasFiles(ev)) return; depth = Math.max(0, depth - 1); if (!depth) veil.hidden = true; });
    wrap.addEventListener('drop', function (ev) {
      if (!hasFiles(ev)) return;
      ev.preventDefault();
      depth = 0; veil.hidden = true;
      if (!P.canEdit) { E.say('Lecture seule.', true); return; }
      var f = ev.dataTransfer.files[0];
      if (f && /^image\//.test(f.type)) { E.say('Les images en surimpression ne sont pas encore prises en charge : déposez un fichier JSON, GeoJSON ou KML.', true); return; }
      E.importFile(f);
    });
  }

  /* ---------- Barre du haut ---------- */
  function syncToolbar() {
    var set = function (sel, on) { var b = $(sel); if (b) b.setAttribute('aria-pressed', String(!!on)); };
    set('[data-run="snap"]', P.snap);
    set('[data-run="snapGrid"]', P.snapGrid);
    set('[data-run="grid"]', P.grid);
    $$('[data-run="undo"]').forEach(function (b) { b.disabled = !P.hist.canUndo(); });
    $$('[data-run="redo"]').forEach(function (b) { b.disabled = !P.hist.canRedo(); });
    if (!P.armed) $$('.pe-tile.is-armed').forEach(function (t) { t.classList.remove('is-armed'); });
  }
  function renderPresence() {
    var el = $('#plan-presence');
    if (!el) return;
    var others = P.presence.filter(function (p) { return p.label !== E.BOOT.operator; });
    el.innerHTML = others.slice(0, 5).map(function (p) { return '<span class="pe-avatar" title="' + esc(p.label + (p.tool ? ' · ' + p.tool : '')) + '">' + esc(String(p.label).slice(0, 2).toUpperCase()) + '</span>'; }).join('') + (others.length > 5 ? '<span class="pe-avatar">+' + (others.length - 5) + '</span>' : '');
    el.setAttribute('aria-label', others.length ? others.map(function (p) { return p.label; }).join(', ') + ' sur le plan' : 'Seul sur le plan');
  }

  /* ---------- Branchement ---------- */
  E.on('init', function () {
    $$('[data-ic]').forEach(function (n) { n.innerHTML = icon(n.getAttribute('data-ic'), Number(n.getAttribute('data-ic-size')) || 18); });
    var left = $('#pe-left'), right = $('#pe-right');
    left.addEventListener('click', onLeftClick);
    left.addEventListener('input', onLeftInput);
    left.addEventListener('change', onLeftInput);
    left.addEventListener('pointerdown', onDragDown);
    left.addEventListener('toggle', function (e) { var d = e.target; if (d.hasAttribute && d.hasAttribute('data-libcat')) { libOpen[d.getAttribute('data-libcat')] = d.open; save('libopen', libOpen); } }, true);
    left.addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('.pe-tile')) { e.preventDefault(); e.target.click(); } });
    left.addEventListener('dblclick', function (e) { var n = e.target.closest('[data-node]'); if (n && !e.target.closest('button')) { var el = P.els[n.getAttribute('data-node')]; if (el && el.props.placed !== false) goTo((E.shownOf(el.uid) || el).pts[0]); } });
    left.addEventListener('contextmenu', function (e) { var n = e.target.closest('[data-node]'); if (!n) return; e.preventDefault(); if (P.sel.indexOf(n.getAttribute('data-node')) < 0) E.select([n.getAttribute('data-node')]); openMenu(e.clientX, e.clientY, contextItems({})); });
    $$('[data-left]').forEach(function (b) { b.addEventListener('click', function () { setLeftTab(b.getAttribute('data-left')); }); });
    $$('[data-right]').forEach(function (b) { b.addEventListener('click', function () { setRightTab(b.getAttribute('data-right')); }); });
    right.addEventListener('input', onRightInput);
    right.addEventListener('change', onRightInput);
    right.addEventListener('click', onRightClick);
    right.addEventListener('submit', onSubmit);
    $('#pe-lib-echelon').innerHTML = opt(ECHELONS, P.lib.echelon);
    $('#pe-pal-input').addEventListener('input', function () { pal.idx = 0; renderPalette(); });
    $('#pe-pal-input').addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); pal.idx = Math.min(pal.items.length - 1, pal.idx + 1); renderPalette(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); pal.idx = Math.max(0, pal.idx - 1); renderPalette(); }
      if (e.key === 'Enter') { e.preventDefault(); runPalette(pal.idx); }
    });
    $('#pe-palette').addEventListener('click', function (e) { var li = e.target.closest('[data-pal]'); if (li) runPalette(Number(li.getAttribute('data-pal'))); else if (e.target.id === 'pe-palette') closePalette(); });
    var menu = $('#pe-menu');
    menu.addEventListener('click', function (e) { var li = e.target.closest('[data-mi]'); if (!li || li.getAttribute('aria-disabled')) return; var it = menu._items[Number(li.getAttribute('data-mi'))]; closeMenu(); if (it && it.run) it.run(); });
    menu.addEventListener('keydown', function (e) {
      var lis = $$('[role="menuitem"]:not([aria-disabled])', menu);
      var i = lis.indexOf(document.activeElement);
      if (e.key === 'ArrowDown') { e.preventDefault(); (lis[(i + 1) % lis.length] || lis[0]).focus(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); (lis[(i - 1 + lis.length) % lis.length] || lis[0]).focus(); }
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if (document.activeElement) document.activeElement.click(); }
    });
    document.addEventListener('pointerdown', function (e) { if (!menu.hidden && !menu.contains(e.target)) closeMenu(); }, true);
    $$('[data-collapse]').forEach(function (b) { b.addEventListener('click', function () {
      var side = b.getAttribute('data-collapse');
      var on = document.body.classList.toggle('pe-hide-' + side);
      b.setAttribute('aria-expanded', String(!on));
      save('hide-' + side, on);
      setTimeout(function () { if (E.tm) E.tm.map.invalidateSize(); if (E.view3d) E.view3d.resize(); E.emit('layout'); }, 60);
    }); });
    ['left', 'right'].forEach(function (s) { if (load('hide-' + s, false)) { document.body.classList.add('pe-hide-' + s); var b = $('[data-collapse="' + s + '"]'); if (b) b.setAttribute('aria-expanded', 'false'); } });
    bindFileDrop();
    E.escape = function () {
      if (!menu.hidden) { closeMenu(); return true; }
      if (pal.open) { closePalette(); return true; }
      return false;
    };
  });
  E.on('loaded', function () { setLeftTab(leftTab); setRightTab('props'); syncToolbar(); renderPresence(); });
  E.on('render', function () { renderLeft(); renderRight(); syncToolbar(); });
  E.on('selection', function () { if (leftTab === 'orbat') renderOrbat(); renderRight(true); });
  E.on('time', function () { renderRight(); });
  E.on('phases', function () { renderRight(); });
  E.on('comments', function () { renderRight(); });
  E.on('presence', renderPresence);
  E.on('rights', function () { renderLeft(); renderRight(true); });
  E.on('tool', syncToolbar);
  E.on('dem', function () { renderRight(); });
  E.on('palette', function () { if (pal.open) closePalette(); else openPalette(''); });
  E.on('context', function (ctx) { openMenu(ctx.x, ctx.y, contextItems(ctx)); });
  E.on('edit', function () { setRightTab('props'); propTab = 'id'; renderRight(true); setTimeout(function () { var i = $('#prop-main'); if (i) { i.focus(); if (i.select) i.select(); } }, 30); });
  E.on('added', function (el) { if (el.kind === 'text' || el.kind === 'textbox' || el.kind === 'unit') E.emit('edit'); });
  E.openMenu = openMenu;
  E.openPalette = openPalette;
})();
