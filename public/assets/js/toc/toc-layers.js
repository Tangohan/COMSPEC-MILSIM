/* Calques issus du jeu (et du web) superposés à la carte du TOC web et de l'éditeur de plans : pistes au sol, véhicules,
 * aéronefs, traces, marqueurs, formes, plan du camp, zones, points et itinéraires, relais, comptes rendus, photos,
 * demandes, ordres, plan web. Chaque calque : case à cocher, nombre d'éléments, opacité, source, heure de la dernière
 * mise à jour ; filtres par camp, groupe et fraîcheur ; luminosité du fond. Lecture des API ATAK existantes (mêmes
 * droits et même réalisme que la carte ATAK web : seul ce que le réseau connaît). Les symboles et les plaques sont
 * dessinés par toc-overlay.js. En mode éditeur : « Ajouter au plan » sur chaque élément. */
window.TocLayers = (function () {
  'use strict';
  var G = window.TocGeo;
  var R = window.TocPlanRender;
  var T = window.TocTracks;
  var esc = R.esc;

  var DEFS = [
    { id: 'units', label: 'Unités au sol', source: 'jeu', every: 3, on: true },
    { id: 'vehicles', label: 'Véhicules', source: 'jeu', every: 5, on: true },
    { id: 'aircraft', label: 'Aéronefs (hélicoptères, avions, drones)', source: 'jeu', every: 3, on: true },
    { id: 'trails', label: 'Traces (depuis l’ouverture du poste)', source: 'jeu', every: 0, on: false },
    { id: 'markers', label: 'Marqueurs (mission et joueurs)', source: 'jeu + web', every: 10, on: true },
    { id: 'routes', label: 'Points et itinéraires', source: 'jeu + web', every: 20, on: true },
    { id: 'shapes', label: 'Formes et dessins', source: 'jeu + web', every: 10, on: true },
    { id: 'camp', label: 'Plan du camp (app Plan, PC du poste)', source: 'jeu', every: 10, on: true },
    { id: 'zones', label: 'Zones (battleplan)', source: 'jeu + web', every: 15, on: true },
    { id: 'relays', label: 'Relais et couverture réseau', source: 'jeu', every: 30, on: false },
    { id: 'reports', label: 'Comptes rendus géolocalisés', source: 'jeu', every: 10, on: true },
    { id: 'photos', label: 'Photos géolocalisées', source: 'jeu', every: 30, on: false },
    { id: 'requests', label: 'Demandes du poste (MEDEVAC, feux, ravitaillement)', source: 'jeu + web', every: 0, on: true },
    { id: 'orders', label: 'Ordres avec point', source: 'web', every: 15, on: false },
    { id: 'plan', label: 'Plan web affiché', source: 'web', every: 0, on: true },
    { id: 'tracing', label: 'Tracés de l’app Traçage', source: 'jeu', unavailable: 'Pas remonté par le jeu au site pour l’instant.' },
    { id: 'tasks', label: 'Cibles et tâches', source: 'jeu', unavailable: 'Pas remonté par le jeu au site pour l’instant.' }
  ];
  var SOURCE_LABEL = { 'jeu': 'Source : jeu', 'jeu + web': 'Source : jeu et web', 'web': 'Source : web' };
  // Calques dessinés par toc-overlay.js (symbole + plaque) ; les autres sont des tracés Leaflet.
  var OVERLAY = { units: 1, vehicles: 1, aircraft: 1, markers: 1, routes: 1, relays: 1, reports: 1, photos: 1, requests: 1, orders: 1 };
  var PRIO = { aircraft: 900, units: 800, vehicles: 700, requests: 620, reports: 600, routes: 500, markers: 400, orders: 380, relays: 300, photos: 200 };
  var WP_ICON = { CHECKPOINT: 'checkpoint', RALLY_POINT: 'rally', OVERWATCH: 'eye', ASSAULT_POSITION: 'ambush', OBJECTIVE: 'objective', LZ: 'heli', DZ: 'para', OTHER: 'waypoint' };
  var WP_LABEL = { CHECKPOINT: 'Point de contrôle', RALLY_POINT: 'Point de ralliement', OVERWATCH: 'Poste d’observation', ASSAULT_POSITION: 'Position d’assaut', OBJECTIVE: 'Objectif', LZ: 'Zone de poser', DZ: 'Zone de largage', OTHER: 'Point de passage' };
  var ZONE_COLOR = { LZ: '#7ee08a', DZ: '#7ee08a', OBJECTIVE: '#ff5f56', DANGER_ZONE: '#ff5f56', NO_GO_AREA: '#ff5f56', EXTRACT_POINT: '#62b0f5', RALLY_POINT: '#7ee08a', AO: '#e8eef2', ROZ: '#f5b841', RESTRICTED_AREA: '#f5b841', FREE_FIRE_ZONE: '#7ee08a', SAFE_ZONE: '#62b0f5' };
  var ZONE_ICON = { LZ: 'heli', DZ: 'para', OBJECTIVE: 'objective', RALLY_POINT: 'rally', EXTRACT_POINT: 'pickup', DANGER_ZONE: 'warning', NO_GO_AREA: 'warning' };
  var REQ_COLOR = { RECUE: '#f5b841', PRISE: '#62b0f5', REFUSEE: '#ff5f56', TERMINEE: '#34d399' };
  var REQ_LABEL = { RECUE: 'Reçue', PRISE: 'Prise en compte', REFUSEE: 'Refusée', TERMINEE: 'Terminée' };
  var STATE_LABEL = { live: 'En direct', old: 'Ancien', lost: 'Perdu' };

  function hhmm(ts) {
    if (!ts) return '';
    var m = /(\d{2}):(\d{2}):?(\d{2})?/.exec(String(ts));
    return m ? m[1] + ':' + m[2] : '';
  }
  function ageSec(ts) {
    if (!ts) return Infinity;
    var t = Date.parse(String(ts).replace(' ', 'T') + (/[zZ]|[+-]\d\d:?\d\d$/.test(ts) ? '' : 'Z'));
    return isFinite(t) ? (Date.now() - t) / 1000 : Infinity;
  }

  function create(tm, opts) {
    opts = opts || {};
    var sym = opts.symbols || (window.TOC_BOOT && window.TOC_BOOT.symbols) || {};
    var store = (function () { try { return window.localStorage; } catch (e) { return null; } })();
    var prefsKey = 'athena:toc:layers:' + (opts.mode || 'console') + (opts.userId ? ':' + opts.userId : '');
    var prefs = {};
    try { prefs = JSON.parse((store && store.getItem(prefsKey)) || '{}') || {}; } catch (e) { prefs = {}; }
    var state = {};
    DEFS.forEach(function (d) {
      var p = prefs[d.id] || {};
      // Éditeur : calques du jeu masqués au départ (le plan d'abord), sauf le plan du camp et les zones.
      var defOn = opts.mode === 'editor' ? (d.id === 'camp' || d.id === 'zones' || d.id === 'plan') : !!d.on;
      state[d.id] = { def: d, on: p.on != null ? !!p.on : defOn, opacity: p.opacity != null ? p.opacity : 1, at: null, count: 0, items: [], group: L.layerGroup(), error: false, loaded: false };
    });
    var filters = { side: prefs._side || 'mine', group: '', fresh: prefs._fresh || 'all' };
    var brightness = prefs._bright != null ? Number(prefs._bright) : 55;
    var mySide = opts.side || 'WEST';
    var me = String(opts.me || '').toUpperCase().trim();
    var timers = [];
    var trails = {};
    var requests = [];
    var shownPlanId = null;
    var shownPlan = null;
    var raw = { units: [], vehicles: [], air: [] };
    var tracks = [];
    var feedItems = [];
    var orderItems = [];
    var campPlans = [];
    var lastText = {};
    var dead = false;
    var ringLayer = L.layerGroup().addTo(tm.map);

    var overlay = window.TocOverlay.create(tm, {
      sym: sym,
      onSelect: function (it) {
        if (opts.onSelect) { opts.onSelect(it); return; }
        // Éditeur de plans : fiche en bulle, avec « Ajouter au plan ».
        L.popup({ className: 'toc-pop', maxWidth: 320 }).setLatLng(tm.toLL(it.pos)).setContent(it.popup ? it.popup() : (it.tip ? it.tip(it) : esc(it.title))).openOn(tm.map);
      },
      onFollow: opts.onFollow,
      onLayout: opts.onLayout,
      clusterItem: function (c, mem, lead, tone) {
        var lost = tone === 'lost';
        var affil = lead.data && lead.data.affil ? lead.data.affil : 'F';
        var s = window.TocOverlay.natoSymbol(sym, lead.data && lead.data.track ? lead.data.track.kind : 'infantry', affil, { size: 22, lost: lost, echelon: mem.length >= 12 ? 'D' : (mem.length >= 5 ? 'B' : 'A') });
        var ages = mem.map(function (m) { return m.data && m.data.track && m.data.track.age != null ? m.data.track.age : Infinity; });
        var minAge = Math.min.apply(null, ages);
        return {
          id: 'c:' + c.lead, kind: 'cluster', symbol: s, count: mem.length, tone: tone, color: lost ? T.LOST_COLOR : T.AFFIL_COLOR[affil],
          title: c.group ? c.group + ' · ' + mem.length : (lead.group || lead.title) + ' +' + (c.groups - 1), badge: c.group ? (tone === 'live' ? '' : T.ageShort(minAge)) : String(mem.length),
          sub: [], prio: 850, data: { cluster: true },
          tip: function () {
            return '<h4>' + esc(c.group || ((lead.group || lead.title) + ' +' + (c.groups - 1))) + '</h4><p><b>' + mem.length + '</b> <span>piste(s)</span></p><p class="toc-tip-list">' + mem.slice(0, 12).map(function (m) { return '<span>' + esc(m.title) + '</span>'; }).join('') + '</p><p class="toc-tip-hint">Cliquer pour rapprocher</p>';
          }
        };
      }
    });

    function savePrefs() {
      var out = { _side: filters.side, _fresh: filters.fresh, _bright: brightness };
      Object.keys(state).forEach(function (k) { out[k] = { on: state[k].on, opacity: state[k].opacity }; });
      try { if (store) store.setItem(prefsKey, JSON.stringify(out)); } catch (e) {}
    }

    /** Lecture d'une API ATAK. Réponse identique à la précédente : null (rien à redessiner). */
    function get(path, key) {
      return fetch(opts.base + path, { credentials: 'same-origin', cache: 'no-cache', headers: { Accept: 'application/json' } }).then(function (r) {
        if (!r.ok) throw new Error('http ' + r.status);
        return r.text();
      }).then(function (txt) {
        if (key && lastText[key] === txt) return null;
        var j = JSON.parse(txt);
        if (key) lastText[key] = txt;
        return j;
      });
    }

    function sideOk(s) {
      if (filters.side === 'all') return true;
      return !s || s === mySide;
    }
    function freshOk(ts) {
      if (filters.fresh === 'all') return true;
      var lim = filters.fresh === '30' ? 1800 : 7200;
      return ageSec(ts) <= lim;
    }
    function freshAge(age) {
      if (filters.fresh === 'all') return true;
      return age != null && age <= (filters.fresh === '30' ? 1800 : 7200);
    }

    function addToPlanBtn(conv) {
      if (opts.mode !== 'editor' || !opts.onAddToPlan || !conv) return '';
      var id = 'tp' + Math.random().toString(36).slice(2, 9);
      setTimeout(function () {
        var b = document.getElementById(id);
        if (b) b.addEventListener('click', function () { opts.onAddToPlan(conv); tm.map.closePopup(); });
      }, 0);
      return '<button type="button" class="toc-btn is-small is-primary" id="' + id + '">Ajouter au plan</button>';
    }
    function popup(title, lines, conv) {
      return '<h4>' + esc(title) + '</h4>' + lines.filter(Boolean).map(function (l) { return '<p>' + l + '</p>'; }).join('') + addToPlanBtn(conv);
    }
    function bind(layer, html) {
      layer.bindPopup(html, { className: 'toc-pop', maxWidth: 320 });
      return layer;
    }
    function gridHtml(p) { return '<span class="mono">' + G.grid(p[0], p[1], 8) + '</span>'; }
    function iconSymbol(id, color, size, text) { var s = size || 24; return { html: T.iconSvg(id, color, s, text), w: s, h: s, ax: s / 2, ay: s / 2 }; }

    /* ---------- Pistes ---------- */
    function rebuildTracks() {
      tracks = T.merge(raw.units, raw.vehicles, raw.air);
      var now = Date.now();
      tracks.forEach(function (t) {
        if (!t.pos || t.fresh === 'lost') return;
        var tr = trails[t.uid] || (trails[t.uid] = []);
        var last = tr[tr.length - 1];
        if (!last || G.dist(last.p, t.pos) > (t.air ? 15 : 5)) tr.push({ p: t.pos, at: now });
        while (tr.length && now - tr[0].at > 900000) tr.shift();
      });
    }
    function visibleTracks() {
      return tracks.filter(function (t) { return sideOk(t.side) && freshAge(t.age == null && t.fresh === 'live' ? 0 : t.age); });
    }
    function layerOfTrack(t) { return t.air ? 'aircraft' : (t.src === 'vehicle' ? 'vehicles' : 'units'); }

    function trackTip(t, affil) {
      var kl = T.kindLabel(sym, t.kind);
      var h = '<h4>' + esc(t.callsign) + '</h4><p class="toc-tip-list">' + (t.group && t.group !== t.callsign ? '<span>' + esc(t.group) + '</span>' : '') + (kl ? '<span>' + esc(kl) + '</span>' : '') + (t.ai ? '<span>IA</span>' : '') + '</p>';
      h += '<dl>';
      if (t.pos) h += '<dt>Grille</dt><dd class="mono">' + G.grid(t.pos[0], t.pos[1], 8) + '</dd>';
      h += '<dt>État</dt><dd><span class="toc-pill is-' + (t.fresh === 'live' ? 'accent' : (t.fresh === 'old' ? 'amber' : 'grey')) + '">' + STATE_LABEL[t.fresh] + '</span></dd>';
      if (t.age != null) h += '<dt>Dernière position reçue</dt><dd>' + esc(T.ageLabel(t.age)) + '</dd>';
      if (t.vehicle) h += '<dt>Véhicule</dt><dd>' + esc(t.vehicle) + '</dd>';
      if (t.crew != null && (t.src !== 'unit' || t.crew > 0)) h += '<dt>À bord</dt><dd class="mono">' + t.crew + (t.crewMax ? ' / ' + t.crewMax : '') + '</dd>';
      if (t.alt != null && t.air) h += '<dt>Altitude</dt><dd class="mono">' + esc(T.altLabel(t.alt)) + '</dd>';
      if (t.speed != null && Math.abs(t.speed) > 0.3) h += '<dt>Vitesse</dt><dd class="mono">' + esc(T.speedLabel(t.speed)) + '</dd>';
      if (t.heading != null) h += '<dt>Cap (degrés)</dt><dd class="mono">' + esc(T.headingLabel(t.heading)) + '</dd>';
      if (t.fuel != null) h += '<dt>Carburant</dt><dd class="mono">' + Math.round(t.fuel) + ' %</dd>';
      if (t.battery != null) h += '<dt>Batterie</dt><dd class="mono">' + t.battery + ' %</dd>';
      if (t.health) h += '<dt>Santé</dt><dd>' + esc(t.health) + '</dd>';
      return h + '</dl>';
    }
    function trackItem(t) {
      var affil = T.affiliation(t.side, mySide, t.hostile);
      var lost = t.fresh === 'lost';
      var layer = layerOfTrack(t);
      var kl = T.kindLabel(sym, t.kind);
      var sub = [];
      if (t.air) {
        if (t.ground) sub.push('Posé au sol');
        else if (t.alt != null) sub.push(Math.round(t.alt) + ' m');
        if (t.speed != null && Math.abs(t.speed) > 0.5) sub.push(T.speedLabel(t.speed));
        if (t.heading != null && !t.ground) sub.push(T.headingLabel(t.heading));
        if (!sub.length && kl) sub.push(kl);
      } else {
        if (t.group && t.group !== t.callsign) sub.push(t.group);
        sub.push(t.src === 'vehicle' && t.vehicle ? t.vehicle : kl);
        if (t.src === 'vehicle' && t.crew != null) sub.push('équipage ' + t.crew + ' pers.');
      }
      if (t.fresh !== 'live' && t.age != null) sub.push(T.ageLabel(t.age));
      var isMe = !!me && t.callsign.toUpperCase() === me;
      var moving = t.speed != null && Math.abs(t.speed) > 0.5;
      return {
        id: t.id, kind: 'track', pos: t.pos, symbol: window.TocOverlay.natoSymbol(sym, t.kind, affil, { size: t.air ? 22 : 20, lost: lost }),
        title: t.callsign, sub: sub, badge: t.fresh === 'live' ? '' : T.ageShort(t.age), tone: t.fresh, color: lost ? T.LOST_COLOR : T.AFFIL_COLOR[affil],
        heading: (t.air && !t.ground) || (t.src === 'vehicle' && moving) ? t.heading : null, prio: PRIO[layer] + (t.fresh === 'live' ? 60 : (t.fresh === 'old' ? 30 : 0)) + (t.ai ? 0 : 10),
        clusterable: !t.air, solo: t.air, group: t.group || t.callsign, me: isMe, data: { type: 'track', track: t, affil: affil },
        tip: function () { return trackTip(t, affil); },
        popup: function () { return trackTip(t, affil) + addToPlanBtn({ kind: 'unit', geom: 'point', pts: [t.pos], props: { affil: affil, unitType: t.kind === 'motorized' || t.kind === 'mechanized' ? 'motorized' : (t.kind === 'armor' ? 'armor' : 'infantry'), echelon: 'team', callsign: t.callsign } }); }
      };
    }
    function drawTracks() {
      var per = { units: [], vehicles: [], aircraft: [] };
      visibleTracks().forEach(function (t) {
        if (!t.pos || (filters.group && (t.group || t.callsign) !== filters.group)) return;
        per[layerOfTrack(t)].push(trackItem(t));
      });
      Object.keys(per).forEach(function (id) {
        overlay.setLayer(id, state[id].on ? per[id] : []);
        state[id].count = per[id].length;
      });
      // Courte traînée derrière chaque aéronef en vol (les 8 dernières positions).
      var g = state.aircraft.group;
      g.clearLayers();
      if (state.aircraft.on) {
        if (!tm.map.hasLayer(g)) g.addTo(tm.map);
        visibleTracks().forEach(function (t) {
          var tr = trails[t.uid];
          if (!t.air || !t.pos || !tr || tr.length < 2) return;
          var affil = T.affiliation(t.side, mySide, t.hostile);
          g.addLayer(L.polyline(tr.slice(-8).map(function (q) { return tm.toLL(q.p); }), { color: T.AFFIL_COLOR[affil], weight: 2, opacity: 0.75 * state.aircraft.opacity, dashArray: '1 6', lineCap: 'round', interactive: false }));
        });
      } else if (tm.map.hasLayer(g)) tm.map.removeLayer(g);
      if (state.trails.on) draw('trails');
      renderPanel();
    }

    /* ---------- Rendu des autres calques ---------- */
    function hatch(layer, kind, color) {
      // Pinceau Arma hachuré : motif SVG posé sur le tracé une fois ajouté à la carte.
      if (!kind) return;
      layer.on('add', function () {
        var path = layer._path;
        var svg = path && path.ownerSVGElement;
        if (!svg) return;
        var id = 'toc-h-' + kind + '-' + color.replace(/[^0-9a-f]/gi, '');
        if (!svg.querySelector('#' + id)) {
          var defs = svg.querySelector('defs') || svg.insertBefore(document.createElementNS('http://www.w3.org/2000/svg', 'defs'), svg.firstChild);
          var lines = { diag: 'M-2 10L10 -2M0 12L12 0', cross: 'M-2 10L10 -2M-2 -2L10 10', grid: 'M0 5H10M5 0V10', horiz: 'M0 5H10', vert: 'M5 0V10' }[kind] || 'M0 10L10 0';
          var pat = document.createElementNS('http://www.w3.org/2000/svg', 'pattern');
          pat.setAttribute('id', id); pat.setAttribute('width', 10); pat.setAttribute('height', 10); pat.setAttribute('patternUnits', 'userSpaceOnUse');
          pat.innerHTML = '<path d="' + lines + '" stroke="' + color + '" stroke-width="1.6" stroke-opacity=".8" fill="none"/>';
          defs.appendChild(pat);
        }
        path.setAttribute('fill', 'url(#' + id + ')');
        path.setAttribute('fill-opacity', '1');
      });
    }
    function draw(id) {
      var st = state[id];
      if (id === 'units' || id === 'vehicles' || id === 'aircraft') { overlay.setLayerState(id, st.on, st.opacity); drawTracks(); return; }
      st.group.clearLayers();
      overlay.setLayerState(id, st.on && !st.def.unavailable, st.opacity);
      if (!st.on || st.def.unavailable) { if (tm.map.hasLayer(st.group)) tm.map.removeLayer(st.group); if (OVERLAY[id]) overlay.setLayer(id, []); renderPanel(); return; }
      if (!tm.map.hasLayer(st.group)) st.group.addTo(tm.map);
      var o = st.opacity;
      var n = 0;
      var feats = [];
      if (id === 'trails') {
        Object.keys(trails).forEach(function (k) {
          var t = trails[k];
          if (t.length < 2) return;
          st.group.addLayer(L.polyline(t.map(function (q) { return tm.toLL(q.p); }), { color: '#6cb8ff', weight: 2, opacity: 0.7 * o, dashArray: '2 5', interactive: false }));
          n++;
        });
      } else if (id === 'requests') {
        requests.forEach(function (r) {
          if (!r.pos) return;
          var col = REQ_COLOR[r.status] || '#62b0f5';
          feats.push({
            id: 'rq' + r.id, kind: 'marker', pos: r.pos, symbol: iconSymbol('request', col, 26), title: r.kind, sub: [REQ_LABEL[r.status] || r.status], badge: String(r.t || '').slice(0, 5), tone: 'live', color: col, prio: PRIO.requests, labelMax: 40,
            data: { type: 'request', id: r.id },
            tip: function () { return '<h4>' + esc(r.kind) + '</h4><p class="toc-tip-list"><span>' + esc(r.by) + '</span><span>' + esc(REQ_LABEL[r.status] || r.status) + '</span></p><p>' + esc(r.text) + '</p><p class="mono">' + esc(r.t) + 'Z · ' + esc(r.grid) + '</p>'; }
          });
          n++;
        });
      } else if (id === 'plan') {
        if (shownPlan) shownPlan.elements.forEach(function (el) {
          st.group.addLayer(R.render(tm, el, { opacity: o, catalog: opts.catalog, tooltip: esc(shownPlan.title) }));
          n++;
        });
      } else {
        st.items.forEach(function (it) {
          if (!it.pts || !it.pts.length) return;
          if (!sideOk(it.side) || !freshOk(it.ts)) return;
          if (it.feat) { feats.push(it.feat); n++; }
          if (it.el || it.relay) {
            var layer;
            if (it.relay) layer = L.circle(tm.toLL(it.pts[0]), { radius: it.range || 500, color: '#34d399', weight: 1, opacity: 0.6 * o, fillOpacity: 0.05 * o, dashArray: '4 6', interactive: false });
            else {
              layer = R.render(tm, it.el, { opacity: o, catalog: opts.catalog });
              if (it.hatch) layer.eachLayer(function (l) { if (l instanceof L.Polygon) hatch(l, it.hatch, it.el.props.color); });
              bind(layer, popup(it.title, it.lines, it.el));
            }
            st.group.addLayer(layer);
            if (!it.feat) n++;
          }
        });
      }
      if (OVERLAY[id]) overlay.setLayer(id, feats);
      st.count = n;
      renderPanel();
    }
    function redrawAll() { Object.keys(state).forEach(draw); }

    /* ---------- Chargements ---------- */
    function mark(id, items) {
      var st = state[id];
      st.items = items;
      st.at = new Date();
      st.error = false;
      st.loaded = true;
      draw(id);
    }
    function fail(id) { state[id].error = true; renderPanel(); if (opts.onData) opts.onData('error'); }
    function touched(ids) { ids.forEach(function (id) { state[id].at = new Date(); state[id].error = false; state[id].loaded = true; }); }

    function fitOnce() {
      if (state.units.fitted || opts.mode !== 'console') return;
      var mine = visibleTracks().filter(function (t) { return t.pos; });
      var live = mine.filter(function (t) { return t.fresh !== 'lost'; });
      var pts = (live.length ? live : mine).map(function (t) { return t.pos; });
      if (!pts.length) return;
      state.units.fitted = true;
      var bb = G.bbox(pts);
      var pad = Math.max(300, (bb[2] - bb[0] + bb[3] - bb[1]) * 0.12);
      tm.fitWorld([bb[0] - pad, bb[1] - pad, bb[2] + pad, bb[3] + pad]);
    }
    function loadUnits() {
      return get('/api/atak/units?mapId=' + opts.mapId + '&toc=1', 'units').then(function (rows) {
        touched(['units']);
        if (rows === null) { renderPanel(); return; }
        raw.units = Array.isArray(rows) ? rows.map(function (u) { return T.unitTrack(u, sym); }) : [];
        rebuildTracks();
        fitOnce();
        drawTracks();
        if (opts.onData) opts.onData('units');
      }).catch(function () { fail('units'); });
    }
    function loadVehicles() {
      return get('/api/atak/vehicles?mapId=' + opts.mapId + '&toc=1', 'vehicles').then(function (j) {
        touched(['vehicles']);
        if (j === null) return;
        var rows = j && Array.isArray(j.vehicles) ? j.vehicles : (Array.isArray(j) ? j : []);
        // Dernière position connue gardée 12 h (marquée ancienne ou perdue), pas au-delà.
        raw.vehicles = rows.map(function (v) { return T.vehicleTrack(v, sym); }).filter(function (t) { return t.age == null || t.age < 43200; });
        rebuildTracks();
        drawTracks();
        if (opts.onData) opts.onData('units');
      }).catch(function () { fail('vehicles'); });
    }
    function loadAir() {
      return get('/api/atak/air-assets?mapId=' + opts.mapId + '&toc=1', 'air').then(function (rows) {
        touched(['aircraft']);
        if (rows === null) return;
        raw.air = Array.isArray(rows) ? rows.map(function (a) { return T.airTrack(a, sym); }) : [];
        rebuildTracks();
        drawTracks();
        if (opts.onData) opts.onData('units');
      }).catch(function () { fail('aircraft'); });
    }

    function loadMarkers() {
      return get('/api/atak/markers?mapId=' + opts.mapId, 'markers').then(function (rows) {
        if (rows === null) { touched(['markers']); renderPanel(); return; }
        var items = [];
        var calls = {};
        tracks.forEach(function (t) { if (t.fresh !== 'lost') calls[t.callsign.toUpperCase()] = true; });
        (Array.isArray(rows) ? rows : []).forEach(function (r) {
          var rec = T.markerRecord(r, sym, G);
          if (!rec) return;
          var d = rec.d, p = rec.pos, shape = rec.shape, label = rec.label, col = rec.color, size = rec.size, dir = rec.dir;
          // Marqueur du jeu qui porte l'indicatif d'une piste en liaison : la piste fait foi (pas de doublon).
          if (shape === 'ICON' && label && calls[label.toUpperCase()]) return;
          var src = rec.source;
          var meta = { type: 'marker', id: r.id, d: d, own: !!(opts.userId && Number(d.owner_user_id) === Number(opts.userId)), web: src === 'web' };
          var lines = ['<span>Type</span> : ' + esc(d.type || '—'), gridHtml(p), '<span>' + SOURCE_LABEL[src] + '</span>' + (r.updated_at ? ' · ' + hhmm(r.updated_at) : '')];
          var item = { pts: [p], side: T.side(d.side), ts: r.updated_at, title: label || 'Marqueur', lines: lines };
          if (shape !== 'ICON') {
            item.el = rec.el;
            item.hatch = rec.hatch;
            item.pts = rec.el.pts;
          } else {
            var spec = T.markerSpec(sym, d.type || d.icon);
            var symb = spec.nato ? window.TocOverlay.natoSymbol(sym, spec.nato, spec.affil, { size: 18 }) : iconSymbol(spec.icon, col, Math.round(22 * Math.max(0.8, Math.min(1.6, size[0]))));
            if (spec.icon === 'arrow' && dir) symb = L.extend({}, symb, { html: '<span style="display:block;transform:rotate(' + Math.round(dir) + 'deg)">' + symb.html + '</span>' });
            item.feat = {
              id: 'mk' + r.id, kind: 'marker', pos: p, symbol: symb, title: label, sub: [], tone: 'live', color: spec.nato ? T.AFFIL_COLOR[spec.affil] : col, prio: PRIO.markers + (label ? 5 : 0), labelMax: 14, data: meta,
              tip: function () { return popup(label || 'Marqueur', lines); },
              popup: function () { return popup(label || 'Marqueur', lines, { geom: 'point', kind: 'icon', pts: [p], props: { icon: 'dot', color: col, label: label, size: 20 } }); }
            };
          }
          items.push(item);
        });
        mark('markers', items);
        if (opts.onData) opts.onData('markers');
      }).catch(function () { fail('markers'); });
    }
    function loadShapes() {
      return get('/api/map-shapes?mapId=' + opts.mapId, 'shapes').then(function (rows) {
        if (rows === null) { touched(['shapes']); return; }
        var items = [];
        (Array.isArray(rows) ? rows : []).forEach(function (s) {
          var g = s.geometry && typeof s.geometry === 'object' ? s.geometry : {};
          var col = T.markerColor(s.color, '#f2f2f2');
          var props = { color: col, width: Number(s.stroke) || 2, fill: Math.min(0.5, Number(s.fill_opacity) || 0), label: s.label || '' };
          var type = String(s.type || '').toUpperCase();
          var el = null;
          var coords = Array.isArray(g.coordinates) ? g.coordinates : (Array.isArray(g.points) ? g.points : null);
          var pts = coords ? coords.map(function (q) { return Array.isArray(q) ? T.posOf(q[0], q[1]) : T.posOf(q.x, q.y); }).filter(Boolean) : [];
          if (type === 'CIRCLE' && Array.isArray(g.center)) {
            el = { geom: 'circle', kind: 'circle', pts: [[Number(g.center[0]), Number(g.center[1])]], props: L.extend(props, { radius: Number(g.radius) || 100, fill: props.fill || 0.08 }) };
          } else if (type === 'POLYGON' && pts.length >= 3) {
            el = { geom: 'area', kind: 'polygon', pts: pts, props: props };
          } else if ((type === 'LINE' || type === 'POLYLINE' || type === 'ARROW') && pts.length >= 2) {
            el = { geom: 'line', kind: 'line', pts: pts, props: L.extend(props, { arrow: type === 'ARROW' ? 'end' : 'none' }) };
          } else if (type === 'POINT') {
            var c = Array.isArray(g.center) ? g.center : (pts[0] || null);
            if (c) el = { geom: 'point', kind: 'icon', pts: [[Number(c[0]), Number(c[1])]], props: L.extend(props, { icon: 'dot' }) };
          }
          if (!el) return;
          items.push({ el: el, pts: el.pts, side: '', ts: s.updated_at, title: s.label || 'Forme dessinée', lines: ['<span>' + SOURCE_LABEL[s.meta && s.meta.owner_user_id ? 'web' : 'jeu'] + '</span>' + (s.updated_at ? ' · ' + hhmm(s.updated_at) : '')] });
        });
        mark('shapes', items);
      }).catch(function () { fail('shapes'); });
    }
    var GAME_KIND = {};
    function gameKind(k) {
      if (!GAME_KIND.ENY) {
        var cat = opts.catalog && opts.catalog.kinds ? opts.catalog.kinds : {};
        Object.keys(cat).forEach(function (wk) { var gk = cat[wk].game; if (gk && gk !== '*' && !GAME_KIND[gk]) GAME_KIND[gk] = wk; });
        GAME_KIND.UNITF = 'unit'; GAME_KIND.UNITH = 'unit'; GAME_KIND.ZONE = 'polygon'; GAME_KIND.FREE = 'line'; GAME_KIND.NOTE = 'text';
      }
      return GAME_KIND[k] || 'line';
    }
    function loadCamp() {
      return get('/api/atak/briefing-plan?mapId=' + opts.mapId, 'camp').then(function (j) {
        if (j === null) { touched(['camp']); return; }
        campPlans = (j && j.plans) || [];
        var items = [];
        campPlans.forEach(function (pl) {
          if (pl.personal) return;
          (pl.items || []).forEach(function (it) {
            var pts = (it.pts || []).map(function (q) { return T.posOf(q[0], q[1]); }).filter(Boolean);
            if (!pts.length) return;
            var wk = gameKind(it.kind);
            var def = (opts.catalog && opts.catalog.kinds && opts.catalog.kinds[wk]) || { geoms: ['line'] };
            var geom = def.geoms[0] === 'circle' ? 'area' : def.geoms[0];
            if (geom === 'area' && pts.length < 3) geom = pts.length >= 2 ? 'line' : 'point';
            if (geom === 'line' && pts.length < 2) geom = 'point';
            if (geom === 'point') pts = [pts[0]];
            var col = G.GAME_COLORS[String(it.color || 'WHITE').toUpperCase()] || '#f2f2f2';
            var el = { geom: geom, kind: geom === 'point' && wk !== 'unit' && wk !== 'text' && def.geoms[0] !== 'point' ? 'icon' : wk, pts: pts, props: { color: col, dash: it.dashed ? 'dash' : 'solid', arrow: it.arrow ? 'end' : 'none', width: (Number(it.width) || 2) * 2, label: it.text || '', callsign: it.text || '', affil: it.kind === 'UNITH' ? 'H' : 'F', fill: geom === 'area' ? 0.1 : 0, icon: 'flag' } };
            items.push({ el: el, pts: pts, side: pl.side, ts: pl.updated_at, title: (it.text || it.kind) + ' · ' + it.kind, lines: ['<span>Plan du camp</span> ' + esc(pl.side) + (it.author ? ' · ' + esc(it.author) : ''), it.desc ? esc(it.desc) : '', '<span>' + SOURCE_LABEL.jeu + '</span> · ' + hhmm(pl.updated_at)] });
          });
        });
        mark('camp', items);
        if (opts.onData) opts.onData('camp');
      }).catch(function () { fail('camp'); });
    }
    function loadZones() {
      return get('/api/atak/zones?mapId=' + opts.mapId, 'zones').then(function (j) {
        if (j === null) { touched(['zones']); return; }
        var items = [];
        ((j && j.zones) || []).forEach(function (z) {
          var col = T.markerColor(z.border_color, ZONE_COLOR[z.zone_type] || '#f2d36b');
          var props = { color: col, width: Number(z.border_width) || 2, fill: Math.min(0.4, T.num(z.opacity) != null ? T.num(z.opacity) * 0.4 : 0.1), label: z.zone_code || z.zone_name || '', rotation: T.num(z.rotation) || 0 };
          var gt = String(z.geometry_type || '').toUpperCase();
          var c = T.posOf(z.center_x, z.center_y);
          var el = null;
          if (gt === 'POLYGON' && Array.isArray(z.polygon_points) && z.polygon_points.length >= 3) el = { geom: 'area', kind: 'polygon', pts: z.polygon_points.map(function (q) { return [Number(q[0]), Number(q[1])]; }), props: props };
          else if (gt === 'CIRCLE' && c) el = { geom: 'circle', kind: 'circle', pts: [c], props: L.extend(props, { radius: T.num(z.radius) || 100 }) };
          else if (gt === 'ELLIPSE' && c) el = { geom: 'ellipse', kind: 'ellipse', pts: [c], props: L.extend(props, { rx: T.num(z.radius_major) || 100, ry: T.num(z.radius_minor) || 60 }) };
          else if (gt === 'RECTANGLE' && c) {
            var w = (T.num(z.width) || 200) / 2, h = (T.num(z.height) || 200) / 2;
            el = G.rotate({ geom: 'rect', kind: 'rect', pts: [[c[0] - w, c[1] - h], [c[0] + w, c[1] + h]], props: props }, props.rotation || 0);
            el.kind = 'polygon';
          } else if (c) el = { geom: 'point', kind: 'icon', pts: [c], props: L.extend(props, { icon: 'diamond' }) };
          if (!el) return;
          items.push({ el: el, pts: el.pts, side: '', ts: z.updated_at, title: (z.zone_name || 'Zone') + (z.zone_code ? ' · ' + z.zone_code : ''), lines: [esc(String(z.zone_type || '').replace(/_/g, ' ')) + ' · ' + esc(String(z.status || '').toLowerCase()), z.description ? esc(z.description) : '', '<span>Source : battleplan</span> · ' + hhmm(z.updated_at)] });
        });
        mark('zones', items);
      }).catch(function () { fail('zones'); });
    }
    function loadRoutes() {
      return Promise.all([get('/api/atak/waypoint-routes?mapId=' + opts.mapId), get('/api/atak/waypoints?mapId=' + opts.mapId)]).then(function (res) {
        var txt = JSON.stringify(res);
        if (lastText.routes === txt) { touched(['routes']); return; }
        lastText.routes = txt;
        var routes = {};
        ((res[0] && res[0].routes) || []).forEach(function (r) { routes[r.id] = r; });
        var by = {};
        ((res[1] && res[1].waypoints) || []).forEach(function (w) {
          var p = T.posOf(w.pos_x, w.pos_y);
          if (!p) return;
          (by[w.route_id || 0] = by[w.route_id || 0] || []).push({ p: p, n: Number(w.sequence_number) || 0, w: w });
        });
        var items = [];
        Object.keys(by).forEach(function (rid) {
          var list = by[rid].sort(function (a, b) { return a.n - b.n; });
          var r = routes[rid] || {};
          var col = T.markerColor(r.marker_color, '#f2d36b');
          var isRoute = rid !== '0' && list.length >= 2;
          if (isRoute) {
            var el = { geom: 'line', kind: 'route', pts: list.map(function (q) { return q.p; }), props: { color: col, width: 3, dash: 'dash', arrow: 'end', label: r.route_code || r.route_name || '' } };
            items.push({ el: el, pts: el.pts, side: '', ts: r.updated_at, title: r.route_name || 'Itinéraire', lines: [esc(String(r.route_type || '').toLowerCase()) + (r.assigned_callsign ? ' · ' + esc(r.assigned_callsign) : ''), '<b>' + (r.waypoints_reached || 0) + ' / ' + (r.waypoints_total || list.length) + '</b> <span>point(s) atteint(s)</span>', '<span>' + SOURCE_LABEL['jeu + web'] + '</span>'] });
          }
          list.forEach(function (q, i) {
            var type = String(q.w.waypoint_type || 'CHECKPOINT').toUpperCase();
            var reached = !!Number(q.w.reached) || !!q.w.reached_at;
            var c = reached ? '#34d399' : col;
            var icon = WP_ICON[type] || 'waypoint';
            // Étape d'un itinéraire : pastille numérotée ; point isolé : icône de son type.
            var s = isRoute && icon === 'checkpoint' ? iconSymbol('checkpoint', c, 24, String(i + 1)) : iconSymbol(icon, c, 24);
            var lines = ['<span>' + (WP_LABEL[type] || WP_LABEL.OTHER) + '</span>' + (isRoute ? ' · ' + esc(r.route_code || r.route_name || '') + ' ' + (i + 1) + ' / ' + list.length : ''), gridHtml(q.p), '<span>' + (reached ? 'Atteint' : 'Pas encore atteint') + '</span>' + (reached && q.w.reached_at ? ' ' + hhmm(q.w.reached_at) : '')];
            var title = q.w.label || (isRoute ? (r.route_code || 'PT') + ' ' + (i + 1) : (WP_LABEL[type] || WP_LABEL.OTHER));
            // Étape sans nom : la pastille numérotée suffit sur la carte (le nom complet est dans la fiche).
            items.push({ pts: [q.p], side: '', ts: q.w.updated_at, title: title, lines: lines, feat: {
              id: 'wp' + q.w.id, kind: 'marker', pos: q.p, symbol: s, title: q.w.label || (isRoute ? '' : title), find: title, sub: [], tone: 'live', color: c, prio: PRIO.routes, labelMax: 14, data: { type: 'point', id: q.w.id, w: q.w },
              tip: function () { return popup(title, lines); },
              popup: function () { return popup(title, lines, { geom: 'point', kind: 'checkpoint', pts: [q.p], props: { color: col, label: title, size: 20 } }); }
            } });
          });
        });
        mark('routes', items);
        if (opts.onData) opts.onData('markers');
      }).catch(function () { fail('routes'); });
    }
    function loadRelays() {
      return get('/api/atak/relays?mapId=' + opts.mapId, 'relays').then(function (j) {
        if (j === null) { touched(['relays']); return; }
        var items = [];
        ((j && j.relays) || []).forEach(function (r) {
          var p = T.posOf(r.pos_x, r.pos_y);
          if (!p) return;
          var alive = Number(r.alive) === 1;
          var title = 'Relais ' + (r.relay_uid || r.id) + '';
          var lines = ['<span>Portée</span> ' + G.fmtDist(T.num(r.range_m) || 0), '<span>' + (alive ? 'En service' : 'Détruit ou éteint') + '</span>', '<span>Dernier contact</span> ' + hhmm(r.last_seen_at)];
          items.push({ relay: true, range: T.num(r.range_m) || 500, pts: [p], side: '', ts: r.updated_at, title: title, lines: lines, feat: {
            id: 'rl' + r.id, kind: 'marker', pos: p, symbol: iconSymbol('relay', alive ? '#34d399' : T.LOST_COLOR, 24), title: title, sub: [], tone: alive ? 'live' : 'lost', color: '#34d399', prio: PRIO.relays, labelMax: 8, data: { type: 'relay', id: r.id },
            tip: function () { return popup(title, lines); }
          } });
        });
        mark('relays', items);
      }).catch(function () { fail('relays'); });
    }
    function loadReports() {
      return get('/api/atak/reports?mapId=' + opts.mapId + '&limit=80', 'reports').then(function (j) {
        if (j === null) { touched(['reports']); return; }
        var items = [];
        feedItems = [];
        ((j && j.reports) || []).forEach(function (r) {
          var p = T.posOf(r.pos_x, r.pos_y);
          var kind = String(r.report_type || 'CR').toUpperCase();
          var t = hhmm(r.event_timestamp || r.report_timestamp || r.created_at);
          var text = String(r.summary || r.details || r.remarks || '');
          feedItems.push({ id: r.id, t: t, kind: kind, who: r.submitter_callsign || r.submitter_unit || '', pos: p, text: text, prio: r.priority });
          if (!p) return;
          var col = r.priority === 'FLASH' || /TIC|CONTACT/.test(kind) ? '#ff5f56' : (r.priority === 'IMMEDIATE' || kind === 'MEDEVAC' ? '#f5b841' : '#62b0f5');
          var lines = [esc(text), '<span class="mono">' + t + 'Z · ' + esc(r.grid_reference || G.grid(p[0], p[1], 8)) + '</span>', '<span>Priorité</span> : ' + esc(String(r.priority || '').toLowerCase())];
          var title = kind + (r.submitter_callsign ? ' · ' + r.submitter_callsign : '');
          items.push({ pts: [p], side: '', ts: r.created_at, title: title, lines: lines, feat: {
            id: 'rp' + r.id, kind: 'marker', pos: p, symbol: iconSymbol('report', col, 24), title: kind, sub: [], badge: t, tone: 'live', color: col, prio: PRIO.reports, labelMax: 14, data: { type: 'report', id: r.id },
            tip: function () { return popup(title, lines); },
            popup: function () { return popup(title, lines, { geom: 'point', kind: 'icon', pts: [p], props: { icon: 'triangle', color: col, label: kind, size: 22 } }); }
          } });
        });
        mark('reports', items);
        if (opts.onData) opts.onData('reports');
      }).catch(function () { fail('reports'); });
    }
    function loadPhotos() {
      return get('/api/recon/images?limit=60', 'photos').then(function (rows) {
        if (rows === null) { touched(['photos']); return; }
        var items = [];
        (Array.isArray(rows) ? rows : []).forEach(function (ph) {
          var p = T.posOf(ph.pos_x, ph.pos_y);
          if (!p || p[0] > tm.worldSize || p[1] > tm.worldSize) return;
          // Pas de filtre par carte côté API : seules les photos récentes (12 h) du camp sont posées.
          if (ageSec(ph.captured_at || ph.created_at) > 43200 || T.side(ph.side || 'WEST') !== mySide) return;
          var title = ph.title || ph.caption || 'Photo';
          var lines = [ph.url ? '<img src="' + esc(ph.url) + '" alt="" loading="lazy">' : '<span>Photo réservée</span>', esc(ph.author_callsign || ph.author || ''), hhmm(ph.captured_at || ph.created_at)];
          items.push({ pts: [p], side: T.side(ph.side), ts: ph.captured_at || ph.created_at, title: title, lines: lines, feat: {
            id: 'ph' + ph.id, kind: 'marker', pos: p, symbol: iconSymbol('photo', '#e8eef2', 22), title: ph.author_callsign || '', sub: [], tone: 'live', color: '#e8eef2', prio: PRIO.photos, labelMax: 6, data: { type: 'photo', id: ph.id },
            tip: function () { return popup(title, lines); }
          } });
        });
        mark('photos', items);
      }).catch(function () { fail('photos'); });
    }
    function loadOrders() {
      return get('/api/atak/orders?mapId=' + opts.mapId + '&limit=60', 'orders').then(function (j) {
        if (j === null) { touched(['orders']); return; }
        var items = [];
        orderItems = [];
        ((j && j.orders) || []).forEach(function (o) {
          if (o.deleted) return;
          var st = String(o.status || '').toLowerCase();
          orderItems.push({ id: o.id, label: o.type_label || o.type || 'Ordre', target: o.target_label || o.target || '', status: o.status_label || st, raw: st, issuer: o.issuer || '', text: o.payload || o.body || '' });
          var w = o.waypoint;
          var p = w ? T.posOf(w.pos_x, w.pos_y) : null;
          if (!p) return;
          var title = o.type_label || 'Ordre';
          var lines = ['<span>Pour</span> ' + esc(o.target_label || ''), '<span>Statut</span> : ' + esc(o.status_label || st), o.issuer ? '<span>Émis par</span> ' + esc(o.issuer) : ''];
          items.push({ pts: [p], side: '', ts: o.updated_at, title: title, lines: lines, feat: {
            id: 'or' + o.id, kind: 'marker', pos: p, symbol: iconSymbol('order', '#62b0f5', 24), title: title, sub: [o.target_label || ''], tone: 'live', color: '#62b0f5', prio: PRIO.orders, labelMax: 14, data: { type: 'order', id: o.id },
            tip: function () { return popup(title, lines); }
          } });
        });
        mark('orders', items);
        if (opts.onData) opts.onData('orders');
      }).catch(function () { fail('orders'); });
    }
    var LOADERS = { units: loadUnits, vehicles: loadVehicles, aircraft: loadAir, markers: loadMarkers, shapes: loadShapes, camp: loadCamp, zones: loadZones, routes: loadRoutes, relays: loadRelays, reports: loadReports, photos: loadPhotos, orders: loadOrders };

    function schedule() {
      DEFS.forEach(function (d) {
        var fn = LOADERS[d.id];
        if (!fn || !d.every) return;
        var need = function () { return state[d.id].on || opts.mode === 'console' && (d.id === 'units' || d.id === 'reports' || d.id === 'orders' || d.id === 'camp'); };
        if (need()) fn();
        timers.push(setInterval(function () { if (!dead && !document.hidden && need()) fn(); }, d.every * 1000));
      });
    }

    /* ---------- Panneau ---------- */
    function renderPanel() {
      var el = opts.panel;
      if (opts.onCounts) opts.onCounts();
      if (!el || el.hidden) return;
      if (el.contains(document.activeElement) && document.activeElement.type === 'range') return;
      var groups = {};
      tracks.forEach(function (t) { if (t.group) groups[t.group] = true; });
      var h = '<div class="toc-sec"><h2>Calques</h2><span><button type="button" class="toc-btn is-small" data-lyr-close>Fermer</button></span></div>';
      h += '<div class="toc-buttons" style="--cols:2"><button type="button" class="toc-btn is-small" data-lyr-all="1">Tout afficher</button><button type="button" class="toc-btn is-small" data-lyr-all="0">Tout masquer</button></div>';
      h += '<label class="toc-slider"><span>Luminosité du fond</span><input type="range" min="0" max="100" step="5" value="' + brightness + '" data-lyr-bright aria-label="' + esc('Luminosité du fond') + '"><b class="mono">' + brightness + ' %</b></label>';
      h += '<div class="toc-form"><div class="toc-row"><label><span>Camp</span><select class="toc-select" data-lyr-filter="side"><option value="mine">Mon camp</option><option value="all">Tous</option></select></label><label><span>Groupe</span><select class="toc-select" data-lyr-filter="group"><option value="">Tous</option>' + Object.keys(groups).sort().map(function (g) { return '<option>' + esc(g) + '</option>'; }).join('') + '</select></label><label><span>Fraîcheur</span><select class="toc-select" data-lyr-filter="fresh"><option value="all">Tout</option><option value="120">2 dernières h</option><option value="30">30 dernières min</option></select></label></div></div>';
      DEFS.forEach(function (d) {
        var st = state[d.id];
        var meta;
        if (d.unavailable) meta = '<span>' + d.unavailable + '</span>';
        else if (st.error) meta = '<span class="is-error">Indisponible pour l’instant</span>';
        else if (d.id === 'plan' && !shownPlan) meta = '<span>Aucun plan affiché</span>';
        else if (!st.on) meta = '<span>Masqué</span>';
        else if (!st.at && d.every) meta = '<span>Chargement…</span>';
        else meta = '<span>' + SOURCE_LABEL[d.source] + '</span>' + (st.at ? '<span class="mono">' + st.at.toLocaleTimeString([], { hour12: false }) + '</span>' : '');
        h += '<div class="toc-layer' + (d.unavailable ? ' is-unavailable' : '') + (st.on ? '' : ' is-off') + '"><input type="checkbox" id="lyr-' + d.id + '" data-lyr="' + d.id + '"' + (st.on && !d.unavailable ? ' checked' : '') + (d.unavailable ? ' disabled' : '') + '><label for="lyr-' + d.id + '">' + esc(d.label) + '</label>' +
          (d.unavailable ? '<span></span><span></span>' : '<b class="toc-count' + (st.count ? '' : ' is-zero') + '">' + st.count + '</b><input type="range" min="0.2" max="1" step="0.1" value="' + st.opacity + '" data-lyr-op="' + d.id + '" aria-label="' + esc('Opacité') + '">') + '<small>' + meta + '</small></div>';
      });
      h += '<p class="toc-note">Lecture seule, sauf les marqueurs, formes et plans, qui ont un retour vers le jeu (ATAK web et « Envoyer en jeu »).</p>';
      el.innerHTML = h;
      el.querySelector('[data-lyr-filter="side"]').value = filters.side;
      el.querySelector('[data-lyr-filter="group"]').value = filters.group;
      el.querySelector('[data-lyr-filter="fresh"]').value = filters.fresh;
    }
    function setLayerOn(id, on) {
      if (!state[id] || state[id].def.unavailable) return;
      state[id].on = !!on;
      savePrefs();
      if (on && LOADERS[id]) { delete lastText[id === 'aircraft' ? 'air' : id]; LOADERS[id](); }
      draw(id);
    }
    function setBrightness(v) {
      brightness = Math.max(0, Math.min(100, Number(v) || 0));
      tm.setVeil(T.veilFor(brightness));
      savePrefs();
    }
    if (opts.panel) {
      opts.panel.addEventListener('change', function (e) {
        var t = e.target;
        if (t.hasAttribute('data-lyr')) setLayerOn(t.getAttribute('data-lyr'), t.checked);
        else if (t.hasAttribute('data-lyr-filter')) {
          filters[t.getAttribute('data-lyr-filter')] = t.value;
          savePrefs();
          redrawAll();
          if (opts.onData) opts.onData('units');
        } else if (t.hasAttribute('data-lyr-bright') || t.hasAttribute('data-lyr-op')) { t.blur(); renderPanel(); }
      });
      opts.panel.addEventListener('input', function (e) {
        var t = e.target;
        if (t.hasAttribute('data-lyr-bright')) { setBrightness(t.value); t.parentNode.querySelector('b').textContent = brightness + ' %'; return; }
        if (!t.hasAttribute('data-lyr-op')) return;
        var id = t.getAttribute('data-lyr-op');
        state[id].opacity = Number(t.value);
        savePrefs();
        clearTimeout(state[id].opTimer);
        state[id].opTimer = setTimeout(function () { draw(id); }, 120);
      });
      opts.panel.addEventListener('click', function (e) {
        if (e.target.closest('[data-lyr-close]')) { openPanel(false); return; }
        var all = e.target.closest('[data-lyr-all]');
        if (all) { var on = all.getAttribute('data-lyr-all') === '1'; DEFS.forEach(function (d) { if (!d.unavailable && state[d.id].on !== on) setLayerOn(d.id, on); }); }
      });
    }
    function openPanel(force) {
      if (!opts.panel) return;
      opts.panel.hidden = force === undefined ? !opts.panel.hidden : !force;
      renderPanel();
      if (opts.onPanel) opts.onPanel(!opts.panel.hidden);
    }

    tm.setVeil(T.veilFor(brightness));
    schedule();
    redrawAll();
    // Pointes de flèche et libellés des tracés : taille fixe à l'écran, donc redessinés après un changement d'échelle.
    var zoomTimer = null;
    tm.map.on('zoomend', function () {
      clearTimeout(zoomTimer);
      zoomTimer = setTimeout(function () {
        if (dead) return;
        ['markers', 'routes', 'shapes', 'camp', 'plan'].forEach(function (id) { if (state[id].on && state[id].group.getLayers().length) draw(id); });
      }, 160);
    });

    return {
      overlay: overlay,
      /** Pistes visibles (camp et fraîcheur choisis), dédoublonnées : unités, véhicules, aéronefs. */
      units: visibleTracks,
      tracks: visibleTracks,
      /** Groupes du camp du poste. only : 'unit' pour les seuls nœuds des joueurs et des IA (ORBAT), sinon toutes les pistes. */
      groups: function (only) { return T.groups(visibleTracks().filter(function (t) { return (!t.side || t.side === mySide) && (!only || t.src === only); })); },
      feed: function () { return feedItems; },
      orders: function () { return orderItems; },
      count: function (id) { return state[id] ? state[id].count : 0; },
      isOn: function (id) { return !!(state[id] && state[id].on); },
      setLayerOn: setLayerOn,
      defs: function () { return DEFS; },
      loading: function () { return !state.units.loaded && !state.units.error; },
      failed: function () { return state.units.error; },
      brightness: function () { return brightness; },
      setBrightness: function (v) { setBrightness(v); renderPanel(); },
      /** Repères cherchables : marqueurs, points, comptes rendus (libellé, position). */
      features: function () {
        var out = [];
        ['markers', 'routes', 'reports', 'requests', 'relays'].forEach(function (id) { overlay.all(id).forEach(function (f) { if (f.title || f.find) out.push(f); }); });
        return out;
      },
      rangeRing: function (p, radius) {
        ringLayer.clearLayers();
        if (!p) return;
        [radius, radius * 2].forEach(function (r, i) {
          ringLayer.addLayer(L.circle(tm.toLL(p), { radius: r, color: '#f5b841', weight: i ? 1 : 2, fill: false, dashArray: i ? '4 6' : null, interactive: false }));
          ringLayer.addLayer(L.marker(tm.toLL([p[0], p[1] + r]), { interactive: false, keyboard: false, icon: L.divIcon({ className: '', iconSize: null, iconAnchor: [0, 22], html: '<span class="toc-gridlbl">' + esc(G.fmtDist(r)) + '</span>' }) }));
        });
      },
      campPlan: function (s) {
        for (var i = 0; i < campPlans.length; i++) if (campPlans[i].side === s && !campPlans[i].personal) return campPlans[i];
        return null;
      },
      setRequests: function (list) { requests = (list || []).filter(function (r) { return r.pos; }); draw('requests'); },
      setSide: function (s) { mySide = s; redrawAll(); },
      openPanel: openPanel,
      shownPlan: function () { return shownPlanId; },
      showPlan: function (id) {
        shownPlanId = id;
        shownPlan = null;
        if (!id) { draw('plan'); return; }
        opts.fetchPlan(id).then(function (j) {
          if (!j || !j.ok) return;
          shownPlan = { title: j.plan.title, elements: j.elements || [] };
          state.plan.at = new Date();
          state.plan.on = true;
          draw('plan');
          var all = [];
          shownPlan.elements.forEach(function (el) { all = all.concat(G.outline(el, 8)); });
          if (all.length) tm.fitWorld(G.bbox(all));
        });
      },
      reload: function (id) { if (LOADERS[id]) { delete lastText[id]; return LOADERS[id](); } return null; },
      refresh: function () { Object.keys(LOADERS).forEach(function (k) { if (state[k].on) LOADERS[k](); }); },
      destroy: function () {
        dead = true;
        timers.forEach(clearInterval);
        overlay.destroy();
        if (tm.map.hasLayer(ringLayer)) tm.map.removeLayer(ringLayer);
        Object.keys(state).forEach(function (k) { if (tm.map.hasLayer(state[k].group)) tm.map.removeLayer(state[k].group); });
      }
    };
  }

  return { create: create, DEFS: DEFS };
})();
