/* Calques issus du jeu (et du web) superposés à la carte du TOC web et de l'éditeur de plans : pistes BFT, traces,
 * marqueurs, formes, plan du camp, zones, itinéraires, relais, comptes rendus, photos, demandes, ordres, plan web.
 * Chaque calque : case à cocher, opacité, source, heure de la dernière mise à jour, nombre d'éléments ; filtres par
 * camp, groupe et fraîcheur. Lecture des API ATAK existantes (mêmes droits et même réalisme que la carte ATAK web :
 * seul ce que le réseau connaît). En mode éditeur : « Ajouter au plan » sur chaque élément. */
window.TocLayers = (function () {
  'use strict';
  var G = window.TocGeo;
  var R = window.TocPlanRender;
  var esc = R.esc;

  var DEFS = [
    { id: 'units', label: 'Pistes BFT (positions en direct)', source: 'jeu', every: 3, on: true },
    { id: 'trails', label: 'Traces (depuis l’ouverture du poste)', source: 'jeu', every: 0, on: false },
    { id: 'markers', label: 'Marqueurs (mission et joueurs)', source: 'jeu + web', every: 10, on: true },
    { id: 'shapes', label: 'Formes et dessins', source: 'jeu + web', every: 10, on: true },
    { id: 'camp', label: 'Plan du camp (app Plan, PC du poste)', source: 'jeu', every: 10, on: true },
    { id: 'zones', label: 'Zones (battleplan)', source: 'jeu + web', every: 15, on: true },
    { id: 'routes', label: 'Itinéraires et points de passage', source: 'jeu + web', every: 30, on: false },
    { id: 'relays', label: 'Relais et couverture réseau', source: 'jeu', every: 30, on: false },
    { id: 'reports', label: 'Comptes rendus géolocalisés', source: 'jeu', every: 10, on: true },
    { id: 'photos', label: 'Photos géolocalisées', source: 'jeu', every: 30, on: false },
    { id: 'requests', label: 'Demandes du poste (MEDEVAC, feux, ravitaillement)', source: 'jeu + web', every: 0, on: true },
    { id: 'orders', label: 'Ordres avec point', source: 'web', every: 15, on: false },
    { id: 'plan', label: 'Plan web affiché', source: 'web', every: 0, on: true },
    { id: 'tracing', label: 'Tracés de l’app Traçage', source: 'jeu', unavailable: 'Pas remonté par le jeu au site pour l’instant.' },
    { id: 'tasks', label: 'Cibles et tâches', source: 'jeu', unavailable: 'Pas remonté par le jeu au site pour l’instant.' }
  ];
  var SIDE_ALIASES = { WEST: 'WEST', BLUFOR: 'WEST', BLUE: 'WEST', EAST: 'EAST', OPFOR: 'EAST', RED: 'EAST', GUER: 'GUER', INDEP: 'GUER', INDFOR: 'GUER', RESISTANCE: 'GUER', GREEN: 'GUER', CIV: 'CIV', CIVILIAN: 'CIV' };
  var ARMA_COLORS = { ColorRed: '#ff5f56', ColorBlue: '#62b0f5', ColorGreen: '#7ee08a', ColorYellow: '#f2d36b', ColorOrange: '#ff9433', ColorWhite: '#e8eef2', ColorBlack: '#141414', ColorBLUFOR: '#6cb8ff', ColorOPFOR: '#ff6b6b', ColorIndependent: '#7ee08a', ColorCivilian: '#c780f2', ColorPink: '#ff80c0', ColorBrown: '#a0784a', ColorKhaki: '#bdb76b', ColorGrey: '#98a4ae', ColorUNKNOWN: '#f2d36b' };

  function side(v) { return SIDE_ALIASES[String(v || '').toUpperCase()] || ''; }
  function num(v) { var n = parseFloat(v); return isFinite(n) ? n : null; }
  function posOf(a, b) { var x = num(a), y = num(b); return x != null && y != null && (x !== 0 || y !== 0) ? [x, y] : null; }
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
  function colorOf(c, fallback) {
    if (!c) return fallback;
    if (/^#[0-9a-f]{6}$/i.test(c)) return c;
    return ARMA_COLORS[c] || G.GAME_COLORS[String(c).toUpperCase()] || fallback;
  }

  function create(tm, opts) {
    opts = opts || {};
    var store = (function () { try { return window.localStorage; } catch (e) { return null; } })();
    var prefsKey = 'athena:toc:layers:' + (opts.mode || 'console');
    var prefs = {};
    try { prefs = JSON.parse((store && store.getItem(prefsKey)) || '{}') || {}; } catch (e) { prefs = {}; }
    var state = {};
    DEFS.forEach(function (d) {
      var p = prefs[d.id] || {};
      // Éditeur : calques du jeu masqués au départ (le plan d'abord), sauf le plan du camp et les zones.
      var defOn = opts.mode === 'editor' ? (d.id === 'camp' || d.id === 'zones' || d.id === 'plan') : !!d.on;
      state[d.id] = { def: d, on: p.on != null ? !!p.on : defOn, opacity: p.opacity != null ? p.opacity : 1, at: null, count: 0, items: [], group: L.layerGroup(), error: false };
    });
    var filters = { side: prefs._side || 'mine', group: '', fresh: prefs._fresh || 'all' };
    var mySide = opts.side || 'WEST';
    var timers = [];
    var trails = {};
    var requests = [];
    var shownPlanId = null;
    var shownPlan = null;
    var rawUnits = [];
    var feedItems = [];
    var orderItems = [];
    var campPlans = [];
    var dead = false;

    function savePrefs() {
      var out = { _side: filters.side, _fresh: filters.fresh };
      Object.keys(state).forEach(function (k) { out[k] = { on: state[k].on, opacity: state[k].opacity }; });
      try { if (store) store.setItem(prefsKey, JSON.stringify(out)); } catch (e) {}
    }

    function get(path) {
      return fetch(opts.base + path, { credentials: 'same-origin', cache: 'no-cache', headers: { Accept: 'application/json' } }).then(function (r) {
        if (!r.ok) throw new Error('http ' + r.status);
        return r.json();
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

    /* ---------- Normalisation ---------- */
    function normUnit(u) {
      var ex = u.extra && typeof u.extra === 'object' ? u.extra : {};
      var st = String(u.status || '');
      var age = u.age_seconds != null ? Number(u.age_seconds) : ageSec(u.updated_at);
      var fresh = st === 'linked' && age < 60 ? 'live' : (st === 'linked' || st === 'delayed' ? 'old' : 'lost');
      return {
        id: u.id, callsign: String(u.display_call_sign || u.call_sign || '?'), pos: posOf(u.pos_x, u.pos_y), heading: num(u.heading),
        status: st, fresh: fresh, group: String(u.group_name || ex.group_name || ex.group || ''), side: side(ex.side || ex.affiliation),
        ai: /ai|bot/i.test(String(ex.source || '')) || ex.is_ai === true, vehicle: ex.vehicle || '', updated_at: u.updated_at
      };
    }

    /* ---------- Rendu ---------- */
    function draw(id) {
      var st = state[id];
      st.group.clearLayers();
      if (!st.on || st.def.unavailable) { if (tm.map.hasLayer(st.group)) tm.map.removeLayer(st.group); return; }
      if (!tm.map.hasLayer(st.group)) st.group.addTo(tm.map);
      var o = st.opacity;
      var n = 0;
      if (id === 'units') {
        // Un symbole par groupe (comme le PC du poste en jeu) ; les nœuds un par un quand on est près du sol.
        var byGroup = {};
        units().forEach(function (u) {
          if (!u.pos || (filters.group && u.group !== filters.group)) return;
          var k = u.group || u.callsign;
          (byGroup[k] = byGroup[k] || []).push(u);
        });
        var close = tm.map.getZoom() >= tm.map.getMaxZoom() - 2.5;
        Object.keys(byGroup).forEach(function (k) {
          var mem = byGroup[k];
          var live = mem.filter(function (u) { return u.fresh === 'live'; });
          var old = mem.filter(function (u) { return u.fresh === 'old'; });
          var lead = live[0] || old[0] || mem[0];
          var fresh = live.length ? 'live' : (old.length ? 'old' : 'lost');
          var hostile = lead.side && lead.side !== mySide;
          var el = { geom: 'point', kind: 'unit', pts: [lead.pos], props: { affil: hostile ? 'H' : 'F', unitType: lead.vehicle ? 'motorized' : 'infantry', echelon: mem.length > 1 ? 'squad' : 'team', callsign: k + (fresh === 'old' ? ' · ancien' : (fresh === 'lost' ? ' · perdu' : '')), size: 22 } };
          var layer = R.render(tm, el, { opacity: o, catalog: opts.catalog, stale: fresh === 'lost', plateClass: fresh === 'old' ? 'is-old' : '' });
          bind(layer, popup(k, [mem.length + ' nœud(s) ATAK · ' + live.length + ' en direct', '<span class="mono">' + G.grid(lead.pos[0], lead.pos[1], 8) + '</span>', mem.map(function (u) { return esc(u.callsign) + (u.fresh === 'live' ? '' : ' (' + (u.fresh === 'old' ? 'ancien' : 'perdu') + ')'); }).join(', '), 'Source : jeu (ATAK) · ' + hhmm(lead.updated_at)],
            { kind: 'unit', geom: 'point', pts: [lead.pos], props: { affil: el.props.affil, unitType: el.props.unitType, echelon: el.props.echelon, callsign: k } }));
          st.group.addLayer(layer);
          if (close) {
            mem.forEach(function (u) {
              if (u === lead) return;
              st.group.addLayer(L.circleMarker(tm.toLL(u.pos), { radius: 4, color: u.fresh === 'live' ? '#6cb8ff' : (u.fresh === 'old' ? '#f5b841' : '#98a4ae'), weight: 2, fillOpacity: 0.6 * o, opacity: o }).bindTooltip(esc(u.callsign), { className: 'toc-tip' }));
            });
          }
          n++;
        });
      } else if (id === 'trails') {
        Object.keys(trails).forEach(function (k) {
          var t = trails[k];
          if (t.length < 2) return;
          st.group.addLayer(L.polyline(t.map(function (q) { return tm.toLL(q.p); }), { color: '#6cb8ff', weight: 2, opacity: 0.6 * o, dashArray: '2 5', interactive: false }));
          n++;
        });
      } else if (id === 'markers' || id === 'shapes' || id === 'zones' || id === 'routes' || id === 'relays' || id === 'reports' || id === 'photos' || id === 'orders' || id === 'camp') {
        st.items.forEach(function (it) {
          if (!it.pts || !it.pts.length) return;
          if (!sideOk(it.side) || !freshOk(it.ts)) return;
          var layer;
          if (it.relay) {
            layer = L.featureGroup([
              L.circle(tm.toLL(it.pts[0]), { radius: it.range || 500, color: '#34d399', weight: 1, opacity: 0.6 * o, fillOpacity: 0.05 * o, dashArray: '4 6' }),
              L.circleMarker(tm.toLL(it.pts[0]), { radius: 5, color: it.alive ? '#34d399' : '#98a4ae', weight: 2, fillOpacity: o, opacity: o })
            ]);
          } else {
            layer = R.render(tm, it.el, { opacity: o, catalog: opts.catalog });
          }
          bind(layer, popup(it.title, it.lines, it.el));
          st.group.addLayer(layer);
          n++;
        });
      } else if (id === 'requests') {
        requests.forEach(function (r) {
          if (!r.pos) return;
          var el = { geom: 'point', kind: 'icon', pts: [r.pos], props: { icon: 'triangle', color: r.status === 'RECUE' ? '#f5b841' : (r.status === 'REFUSEE' ? '#ff5f56' : '#62b0f5'), label: r.kind, size: 24 } };
          st.group.addLayer(bind(R.render(tm, el, { opacity: o }), popup(r.kind + ' · ' + r.by, [esc(r.text), '<span class="mono">' + esc(r.t) + 'Z · ' + esc(r.grid) + '</span>', 'Statut : ' + esc(r.status)], el)));
          n++;
        });
      } else if (id === 'plan' && shownPlan) {
        shownPlan.elements.forEach(function (el) {
          st.group.addLayer(R.render(tm, el, { opacity: o, catalog: opts.catalog, tooltip: esc(shownPlan.title) }));
          n++;
        });
      }
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
      draw(id);
    }
    function fail(id) { state[id].error = true; renderPanel(); }

    function loadUnits() {
      return get('/api/atak/units?mapId=' + opts.mapId).then(function (rows) {
        rawUnits = Array.isArray(rows) ? rows.map(normUnit) : [];
        var now = Date.now();
        rawUnits.forEach(function (u) {
          if (!u.pos || u.fresh === 'lost') return;
          var t = trails[u.id] || (trails[u.id] = []);
          var last = t[t.length - 1];
          if (!last || G.dist(last.p, u.pos) > 5) t.push({ p: u.pos, at: now });
          while (t.length && now - t[0].at > 900000) t.shift();
        });
        state.units.at = new Date();
        if (!state.units.fitted && opts.mode === 'console') {
          var mine = units().filter(function (u) { return u.pos && u.fresh !== 'lost'; }).map(function (u) { return u.pos; });
          if (mine.length) { state.units.fitted = true; var bb = G.bbox(mine); var pad = 600; tm.fitWorld([bb[0] - pad, bb[1] - pad, bb[2] + pad, bb[3] + pad]); }
        }
        draw('units');
        if (state.trails.on) draw('trails');
        if (opts.onData) opts.onData('units');
      }).catch(function () { fail('units'); });
    }
    function loadMarkers() {
      return get('/api/atak/markers?mapId=' + opts.mapId).then(function (rows) {
        var items = [];
        (Array.isArray(rows) ? rows : []).forEach(function (r) {
          if (String(r.id).indexOf('del:') === 0) return;
          var d = r.markerData;
          if (typeof d === 'string') { try { d = JSON.parse(d); } catch (e) { d = null; } }
          if (!d) return;
          var p = posOf(d.x, d.y) || (Array.isArray(d.pos) ? posOf(d.pos[0], d.pos[1]) : null);
          if (!p) return;
          var label = String(d.text || d.label || d.name || '');
          var col = colorOf(d.color, '#f2d36b');
          var el = { geom: 'point', kind: 'icon', pts: [p], props: { icon: 'dot', color: col, label: label, size: 20 } };
          items.push({ el: el, pts: [p], side: side(d.side), ts: r.updated_at, title: label || 'Marqueur', lines: ['Type : ' + esc(d.type || '—'), '<span class="mono">' + G.grid(p[0], p[1], 8) + '</span>', 'Source : ' + (d.source === 'web' ? 'web' : 'jeu') + (r.updated_at ? ' · ' + hhmm(r.updated_at) : '')] });
        });
        mark('markers', items);
      }).catch(function () { fail('markers'); });
    }
    function loadShapes() {
      return get('/api/map-shapes?mapId=' + opts.mapId).then(function (rows) {
        var items = [];
        (Array.isArray(rows) ? rows : []).forEach(function (s) {
          var g = s.geometry && typeof s.geometry === 'object' ? s.geometry : {};
          var col = colorOf(s.color, '#f2f2f2');
          var props = { color: col, width: Number(s.stroke) || 2, fill: Math.min(0.5, Number(s.fill_opacity) || 0), label: s.label || '' };
          var type = String(s.type || '').toUpperCase();
          var el = null;
          var coords = Array.isArray(g.coordinates) ? g.coordinates : (Array.isArray(g.points) ? g.points : null);
          var pts = coords ? coords.map(function (q) { return Array.isArray(q) ? posOf(q[0], q[1]) : posOf(q.x, q.y); }).filter(Boolean) : [];
          if (type === 'CIRCLE' && Array.isArray(g.center)) {
            el = { geom: 'circle', kind: 'circle', pts: [[Number(g.center[0]), Number(g.center[1])]], props: L.extend(props, { radius: Number(g.radius) || 100, fill: props.fill || 0.08 }) };
          } else if (type === 'POLYGON' && pts.length >= 3) {
            el = { geom: 'area', kind: 'polygon', pts: pts, props: props };
          } else if ((type === 'LINE' || type === 'POLYLINE') && pts.length >= 2) {
            el = { geom: 'line', kind: 'line', pts: pts, props: props };
          } else if (type === 'POINT') {
            var c = Array.isArray(g.center) ? g.center : (pts[0] || null);
            if (c) el = { geom: 'point', kind: 'icon', pts: [[Number(c[0]), Number(c[1])]], props: L.extend(props, { icon: 'dot' }) };
          }
          if (!el) return;
          items.push({ el: el, pts: el.pts, side: '', ts: s.updated_at, title: s.label || 'Forme', lines: ['Forme ' + esc(type.toLowerCase()), 'Source : ' + (s.meta && s.meta.owner_user_id ? 'web' : 'jeu') + (s.updated_at ? ' · ' + hhmm(s.updated_at) : '')] });
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
      return get('/api/atak/briefing-plan?mapId=' + opts.mapId).then(function (j) {
        campPlans = (j && j.plans) || [];
        var items = [];
        campPlans.forEach(function (pl) {
          if (pl.personal) return;
          (pl.items || []).forEach(function (it) {
            var pts = (it.pts || []).map(function (q) { return posOf(q[0], q[1]); }).filter(Boolean);
            if (!pts.length) return;
            var wk = gameKind(it.kind);
            var def = (opts.catalog && opts.catalog.kinds && opts.catalog.kinds[wk]) || { geoms: ['line'] };
            var geom = def.geoms[0] === 'circle' ? 'area' : def.geoms[0];
            if (geom === 'area' && pts.length < 3) geom = pts.length >= 2 ? 'line' : 'point';
            if (geom === 'line' && pts.length < 2) geom = 'point';
            if (geom === 'point') pts = [pts[0]];
            var col = G.GAME_COLORS[String(it.color || 'WHITE').toUpperCase()] || '#f2f2f2';
            var el = { geom: geom, kind: geom === 'point' && wk !== 'unit' && wk !== 'text' && def.geoms[0] !== 'point' ? 'icon' : wk, pts: pts, props: { color: col, dash: it.dashed ? 'dash' : 'solid', arrow: it.arrow ? 'end' : 'none', width: (Number(it.width) || 2) * 2, label: it.text || '', callsign: it.text || '', affil: it.kind === 'UNITH' ? 'H' : 'F', fill: geom === 'area' ? 0.1 : 0, icon: 'flag' } };
            items.push({ el: el, pts: pts, side: pl.side, ts: pl.updated_at, title: (it.text || it.kind) + ' · ' + it.kind, lines: ['Plan du camp ' + esc(pl.side) + (it.author ? ' · ' + esc(it.author) : ''), it.desc ? esc(it.desc) : '', 'Source : jeu · ' + hhmm(pl.updated_at)] });
          });
        });
        mark('camp', items);
        if (opts.onData) opts.onData('camp');
      }).catch(function () { fail('camp'); });
    }
    var ZONE_COLOR = { LZ: '#7ee08a', DZ: '#7ee08a', OBJECTIVE: '#ff5f56', DANGER_ZONE: '#ff5f56', NO_GO_AREA: '#ff5f56', EXTRACT_POINT: '#62b0f5', RALLY_POINT: '#7ee08a', AO: '#e8eef2', ROZ: '#f5b841', RESTRICTED_AREA: '#f5b841', FREE_FIRE_ZONE: '#7ee08a', SAFE_ZONE: '#62b0f5' };
    function loadZones() {
      return get('/api/atak/zones?mapId=' + opts.mapId).then(function (j) {
        var items = [];
        ((j && j.zones) || []).forEach(function (z) {
          var col = colorOf(z.border_color, ZONE_COLOR[z.zone_type] || '#f2d36b');
          var props = { color: col, width: Number(z.border_width) || 2, fill: Math.min(0.4, num(z.opacity) != null ? num(z.opacity) * 0.4 : 0.1), label: z.zone_code || z.zone_name || '', rotation: num(z.rotation) || 0 };
          var gt = String(z.geometry_type || '').toUpperCase();
          var c = posOf(z.center_x, z.center_y);
          var el = null;
          if (gt === 'POLYGON' && Array.isArray(z.polygon_points) && z.polygon_points.length >= 3) el = { geom: 'area', kind: 'polygon', pts: z.polygon_points.map(function (q) { return [Number(q[0]), Number(q[1])]; }), props: props };
          else if (gt === 'CIRCLE' && c) el = { geom: 'circle', kind: 'circle', pts: [c], props: L.extend(props, { radius: num(z.radius) || 100 }) };
          else if (gt === 'ELLIPSE' && c) el = { geom: 'ellipse', kind: 'ellipse', pts: [c], props: L.extend(props, { rx: num(z.radius_major) || 100, ry: num(z.radius_minor) || 60 }) };
          else if (gt === 'RECTANGLE' && c) {
            var w = (num(z.width) || 200) / 2, h = (num(z.height) || 200) / 2;
            el = G.rotate({ geom: 'rect', kind: 'rect', pts: [[c[0] - w, c[1] - h], [c[0] + w, c[1] + h]], props: props }, props.rotation || 0);
            el.kind = 'polygon';
          } else if (c) el = { geom: 'point', kind: 'icon', pts: [c], props: L.extend(props, { icon: 'diamond' }) };
          if (!el) return;
          items.push({ el: el, pts: el.pts, side: '', ts: z.updated_at, title: (z.zone_name || 'Zone') + (z.zone_code ? ' · ' + z.zone_code : ''), lines: ['Zone ' + esc(String(z.zone_type || '').toLowerCase().replace(/_/g, ' ')) + ' · ' + esc(String(z.status || '').toLowerCase()), z.description ? esc(z.description) : '', 'Source : battleplan · ' + hhmm(z.updated_at)] });
        });
        mark('zones', items);
      }).catch(function () { fail('zones'); });
    }
    function loadRoutes() {
      return Promise.all([get('/api/atak/waypoint-routes?mapId=' + opts.mapId), get('/api/atak/waypoints?mapId=' + opts.mapId)]).then(function (res) {
        var routes = {};
        ((res[0] && res[0].routes) || []).forEach(function (r) { routes[r.id] = r; });
        var by = {};
        ((res[1] && res[1].waypoints) || []).forEach(function (w) {
          var p = posOf(w.pos_x, w.pos_y);
          if (!p) return;
          (by[w.route_id || 0] = by[w.route_id || 0] || []).push({ p: p, n: Number(w.sequence_number) || 0, w: w });
        });
        var items = [];
        Object.keys(by).forEach(function (rid) {
          var list = by[rid].sort(function (a, b) { return a.n - b.n; });
          var r = routes[rid] || {};
          var col = colorOf(r.marker_color, '#f2d36b');
          if (list.length >= 2) {
            var el = { geom: 'line', kind: 'route', pts: list.map(function (q) { return q.p; }), props: { color: col, width: 2, arrow: 'end', label: r.route_code || r.route_name || '' } };
            items.push({ el: el, pts: el.pts, side: '', ts: r.updated_at, title: r.route_name || 'Itinéraire', lines: [esc(String(r.route_type || '').toLowerCase()) + (r.assigned_callsign ? ' · ' + esc(r.assigned_callsign) : ''), (r.waypoints_reached || 0) + ' / ' + (r.waypoints_total || list.length) + ' point(s) atteint(s)', 'Source : jeu + web'] });
          }
          list.forEach(function (q) {
            var pel = { geom: 'point', kind: 'checkpoint', pts: [q.p], props: { color: col, label: q.w.label || '', size: 20 } };
            items.push({ el: pel, pts: pel.pts, side: '', ts: q.w.updated_at, title: q.w.label || 'Point de passage', lines: [esc(String(q.w.waypoint_type || '').toLowerCase().replace(/_/g, ' ')), '<span class="mono">' + G.grid(q.p[0], q.p[1], 8) + '</span>', q.w.reached ? 'Atteint ' + hhmm(q.w.reached_at) : 'Pas encore atteint'] });
          });
        });
        mark('routes', items);
      }).catch(function () { fail('routes'); });
    }
    function loadRelays() {
      return get('/api/atak/relays?mapId=' + opts.mapId).then(function (j) {
        var items = [];
        ((j && j.relays) || []).forEach(function (r) {
          var p = posOf(r.pos_x, r.pos_y);
          if (!p) return;
          items.push({ relay: true, alive: Number(r.alive) === 1, range: num(r.range_m) || 500, pts: [p], el: { geom: 'point', kind: 'icon', pts: [p], props: { icon: 'target', color: '#34d399', label: 'Relais' } }, side: '', ts: r.updated_at, title: 'Relais ' + (r.relay_uid || r.id), lines: ['Portée ' + G.fmtDist(num(r.range_m) || 0), Number(r.alive) === 1 ? 'En service' : 'Détruit ou éteint', 'Vu ' + hhmm(r.last_seen_at)] });
        });
        mark('relays', items);
      }).catch(function () { fail('relays'); });
    }
    function loadReports() {
      return get('/api/atak/reports?mapId=' + opts.mapId + '&limit=80').then(function (j) {
        var items = [];
        feedItems = [];
        ((j && j.reports) || []).forEach(function (r) {
          var p = posOf(r.pos_x, r.pos_y);
          var kind = String(r.report_type || 'CR').toUpperCase();
          var t = hhmm(r.event_timestamp || r.report_timestamp || r.created_at);
          var text = String(r.summary || r.details || r.remarks || '');
          feedItems.push({ id: r.id, t: t, kind: kind, who: r.submitter_callsign || r.submitter_unit || '', pos: p, text: text, prio: r.priority });
          if (!p) return;
          var col = r.priority === 'FLASH' || /TIC|CONTACT/.test(kind) ? '#ff5f56' : (r.priority === 'IMMEDIATE' || kind === 'MEDEVAC' ? '#f5b841' : '#62b0f5');
          var el = { geom: 'point', kind: 'icon', pts: [p], props: { icon: 'triangle', color: col, label: kind, size: 22 } };
          items.push({ el: el, pts: [p], side: '', ts: r.created_at, title: kind + ' · ' + (r.submitter_callsign || ''), lines: [esc(text), '<span class="mono">' + t + 'Z · ' + esc(r.grid_reference || G.grid(p[0], p[1], 8)) + '</span>', 'Priorité : ' + esc(String(r.priority || '').toLowerCase())] });
        });
        mark('reports', items);
        if (opts.onData) opts.onData('reports');
      }).catch(function () { fail('reports'); });
    }
    function loadPhotos() {
      return get('/api/recon/images?limit=60').then(function (rows) {
        var items = [];
        (Array.isArray(rows) ? rows : []).forEach(function (ph) {
          var p = posOf(ph.pos_x, ph.pos_y);
          if (!p || p[0] > tm.worldSize || p[1] > tm.worldSize) return;
          // Pas de filtre par carte côté API : seules les photos récentes (12 h) du camp sont posées.
          if (ageSec(ph.captured_at || ph.created_at) > 43200 || side(ph.side || 'WEST') !== mySide) return;
          var el = { geom: 'point', kind: 'icon', pts: [p], props: { icon: 'square', color: '#e8eef2', label: ph.author_callsign || '', size: 18 } };
          items.push({ el: el, pts: [p], side: side(ph.side), ts: ph.captured_at || ph.created_at, title: ph.title || ph.caption || 'Photo', lines: [ph.url ? '<img src="' + esc(ph.url) + '" alt="" loading="lazy">' : 'Photo réservée', esc(ph.author_callsign || ph.author || ''), hhmm(ph.captured_at || ph.created_at)] });
        });
        mark('photos', items);
      }).catch(function () { fail('photos'); });
    }
    function loadOrders() {
      return get('/api/atak/orders?mapId=' + opts.mapId + '&limit=60').then(function (j) {
        var items = [];
        orderItems = [];
        ((j && j.orders) || []).forEach(function (o) {
          if (o.deleted) return;
          var st = String(o.status || '').toLowerCase();
          orderItems.push({ id: o.id, label: o.type_label || o.type || 'Ordre', target: o.target_label || o.target || '', status: o.status_label || st });
          var w = o.waypoint;
          var p = w ? posOf(w.pos_x, w.pos_y) : null;
          if (!p) return;
          var el = { geom: 'point', kind: 'icon', pts: [p], props: { icon: 'flag', color: '#62b0f5', label: (o.type_label || '') + ' ' + (o.target_label || ''), size: 22 } };
          items.push({ el: el, pts: [p], side: '', ts: o.updated_at, title: o.type_label || 'Ordre', lines: ['Pour ' + esc(o.target_label || ''), 'Statut : ' + esc(o.status_label || st), o.issuer ? 'Par ' + esc(o.issuer) : ''] });
        });
        mark('orders', items);
        if (opts.onData) opts.onData('orders');
      }).catch(function () { fail('orders'); });
    }
    var LOADERS = { units: loadUnits, markers: loadMarkers, shapes: loadShapes, camp: loadCamp, zones: loadZones, routes: loadRoutes, relays: loadRelays, reports: loadReports, photos: loadPhotos, orders: loadOrders };

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
      if (!el || el.hidden) return;
      var groups = {};
      rawUnits.forEach(function (u) { if (u.group) groups[u.group] = true; });
      var h = '<div class="toc-sec"><h2>Calques</h2><span><button type="button" class="toc-btn is-small" data-lyr-close>Fermer</button></span></div>';
      h += '<div class="toc-form"><div class="toc-row"><label>Camp<select class="toc-select" data-lyr-filter="side"><option value="mine">Mon camp</option><option value="all">Tous</option></select></label><label>Groupe<select class="toc-select" data-lyr-filter="group"><option value="">Tous</option>' + Object.keys(groups).sort().map(function (g) { return '<option>' + esc(g) + '</option>'; }).join('') + '</select></label><label>Session<select class="toc-select" data-lyr-filter="fresh"><option value="all">Tout</option><option value="120">2 dernières h</option><option value="30">30 dernières min</option></select></label></div></div>';
      DEFS.forEach(function (d) {
        var st = state[d.id];
        var meta = d.unavailable ? d.unavailable : ('Source : ' + d.source + ' · ' + (st.error ? 'indisponible' : (st.at ? 'mis à jour ' + st.at.toLocaleTimeString('fr-FR', { hour12: false }) + ' · ' + st.count + ' élément(s)' : (d.id === 'plan' ? (shownPlan ? esc(shownPlan.title) : 'aucun plan affiché') : (st.on ? 'chargement…' : 'masqué')))));
        h += '<div class="toc-layer' + (d.unavailable ? ' is-unavailable' : '') + '"><input type="checkbox" id="lyr-' + d.id + '" data-lyr="' + d.id + '"' + (st.on && !d.unavailable ? ' checked' : '') + (d.unavailable ? ' disabled' : '') + '><label for="lyr-' + d.id + '">' + esc(d.label) + '</label>' +
          (d.unavailable ? '<span></span>' : '<input type="range" min="0.2" max="1" step="0.1" value="' + st.opacity + '" data-lyr-op="' + d.id + '" aria-label="Opacité : ' + esc(d.label) + '">') + '<small>' + meta + '</small></div>';
      });
      h += '<p class="toc-note">Lecture seule, sauf les marqueurs, formes et plans, qui ont un retour vers le jeu (ATAK web et « Envoyer en jeu »).</p>';
      el.innerHTML = h;
      el.querySelector('[data-lyr-filter="side"]').value = filters.side;
      el.querySelector('[data-lyr-filter="group"]').value = filters.group;
      el.querySelector('[data-lyr-filter="fresh"]').value = filters.fresh;
    }
    if (opts.panel) {
      opts.panel.addEventListener('change', function (e) {
        var t = e.target;
        if (t.hasAttribute('data-lyr')) {
          var id = t.getAttribute('data-lyr');
          state[id].on = t.checked;
          savePrefs();
          if (t.checked && LOADERS[id]) LOADERS[id]();
          draw(id);
        } else if (t.hasAttribute('data-lyr-filter')) {
          filters[t.getAttribute('data-lyr-filter')] = t.value;
          savePrefs();
          redrawAll();
        }
      });
      opts.panel.addEventListener('input', function (e) {
        var t = e.target;
        if (!t.hasAttribute('data-lyr-op')) return;
        var id = t.getAttribute('data-lyr-op');
        state[id].opacity = Number(t.value);
        savePrefs();
        clearTimeout(state[id].opTimer);
        state[id].opTimer = setTimeout(function () { draw(id); }, 120);
      });
      opts.panel.addEventListener('click', function (e) {
        if (e.target.closest('[data-lyr-close]')) openPanel(false);
      });
    }
    function openPanel(force) {
      if (!opts.panel) return;
      opts.panel.hidden = force === undefined ? !opts.panel.hidden : !force;
      renderPanel();
    }

    function units() {
      return rawUnits.filter(function (u) { return sideOk(u.side) && freshOk(u.updated_at); });
    }

    schedule();
    redrawAll();
    tm.map.on('zoomend', function () { draw('units'); });

    return {
      units: units,
      feed: function () { return feedItems; },
      orders: function () { return orderItems; },
      count: function (id) { return state[id] ? state[id].items.length : 0; },
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
      refresh: function () { Object.keys(LOADERS).forEach(function (k) { if (state[k].on) LOADERS[k](); }); },
      destroy: function () {
        dead = true;
        timers.forEach(clearInterval);
        Object.keys(state).forEach(function (k) { if (tm.map.hasLayer(state[k].group)) tm.map.removeLayer(state[k].group); });
      }
    };
  }

  return { create: create, DEFS: DEFS };
})();
