/* Poste TOC web : calculs sans navigateur des pistes et des repères de la carte. Genre et code OTAN d'une piste,
 * fraîcheur et âge, dédoublonnage par identifiant stable, regroupement à petite échelle (« Bravo · 5 »), placement des
 * plaques sans chevauchement, icônes des marqueurs Arma, suggestions de recherche. Les tables de symboles viennent du
 * site (App\Services\AtakToc\TocSymbols, window.TOC_BOOT.symbols). Testé par tests/Frontend/toc-tracks.test.cjs.
 * Coordonnées monde : mètres Arma [x, y] ; coordonnées écran : pixels, y vers le bas. */
(function (root, factory) {
  'use strict';
  var api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.TocTracks = api;
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var AFFIL_COLOR = { F: '#6cb8ff', H: '#ff6b6b', N: '#7ee08a', U: '#f2d36b' };
  var LOST_COLOR = '#b4bec6';
  var SIDE_ALIASES = { WEST: 'WEST', BLUFOR: 'WEST', BLUE: 'WEST', B: 'WEST', EAST: 'EAST', OPFOR: 'EAST', RED: 'EAST', O: 'EAST', GUER: 'GUER', INDEP: 'GUER', INDFOR: 'GUER', INDEPENDENT: 'GUER', RESISTANCE: 'GUER', GREEN: 'GUER', I: 'GUER', CIV: 'CIV', CIVILIAN: 'CIV', C: 'CIV' };

  function side(v) { return SIDE_ALIASES[String(v || '').toUpperCase()] || ''; }
  function num(v) { var n = parseFloat(v); return isFinite(n) ? n : null; }
  function posOf(a, b) { var x = num(a), y = num(b); return x != null && y != null && (Math.abs(x) >= 1 || Math.abs(y) >= 1) ? [x, y] : null; }

  /* ---------- Fraîcheur et âge ---------- */

  /** En direct (moins de 60 s), ancien (liaison différée ou jusqu'à 5 min), perdu au-delà ou hors liaison. */
  function freshness(status, age) {
    var st = String(status || '').toLowerCase();
    if (st === 'offline' || st === 'destroyed') return 'lost';
    if (age == null || !isFinite(age)) return st === 'linked' ? 'live' : (st === 'delayed' ? 'old' : 'lost');
    if (age < 60 && st !== 'delayed') return 'live';
    return age < 300 ? 'old' : 'lost';
  }

  /** Âge en clair. Chaque forme est un texte entier (traduit tel quel par le dictionnaire du site). */
  function ageLabel(sec) {
    if (sec == null || !isFinite(sec) || sec < 0) return '';
    if (sec < 45) return 'à l’instant';
    if (sec < 3600) return 'il y a ' + Math.max(1, Math.round(sec / 60)) + ' min';
    if (sec < 172800) return 'il y a ' + Math.max(1, Math.round(sec / 3600)) + ' h';
    return 'il y a ' + Math.round(sec / 86400) + ' j';
  }
  /** Âge court pour une pastille : « 40 s », « 4 min », « 2 h ». */
  function ageShort(sec) {
    if (sec == null || !isFinite(sec) || sec < 0) return '';
    if (sec < 60) return Math.round(sec) + ' s';
    if (sec < 3600) return Math.round(sec / 60) + ' min';
    // Au-delà : en heures (unité lue pareil dans toutes les langues du site), plafonné.
    return Math.min(999, Math.round(sec / 3600)) + ' h';
  }
  function altLabel(m) {
    if (m == null || !isFinite(m)) return '';
    return Math.round(m) + ' m · ' + Math.round(m * 3.28084 / 10) * 10 + ' ft';
  }
  /** Vitesse reçue en m/s. */
  function speedLabel(ms) {
    if (ms == null || !isFinite(ms)) return '';
    return Math.round(Math.abs(ms) * 3.6) + ' km/h';
  }
  function headingLabel(deg) {
    if (deg == null || !isFinite(deg)) return '';
    var d = Math.round(((deg % 360) + 360) % 360);
    return (d < 10 ? '00' : d < 100 ? '0' : '') + d + '°';
  }

  /* ---------- Genre et symbole OTAN ---------- */

  function kindOf(sym, hint) {
    var p = String(hint || '').toUpperCase().replace(/[\s-]+/g, '_');
    return (sym && sym.platforms && sym.platforms[p]) || '';
  }
  function isAir(sym, kind) { return !!(sym && sym.kinds && sym.kinds[kind] && sym.kinds[kind].dim === 'A'); }
  function kindLabel(sym, kind) { return (sym && sym.kinds && sym.kinds[kind] && sym.kinds[kind].label) || ''; }

  /** SIDC 2525C. lost : cadre en tirets (état « prévu » du standard). echelon : lettre 2525C ou vide. */
  function sidc(sym, kind, affil, lost, echelon) {
    var k = (sym && sym.kinds && sym.kinds[kind]) || { fn: 'UCI---', dim: 'G' };
    var a = /^[FHNU]$/.test(affil) ? affil : 'F';
    var e = k.dim === 'G' && /^[A-I]$/.test(echelon || '') ? echelon : '-';
    return 'S' + a + k.dim + (lost ? 'A' : 'P') + k.fn + '-' + e + '---';
  }

  /** Affiliation vue du camp du poste : même camp = ami, camp adverse = hostile, civils = neutre, sans camp = ami. */
  function affiliation(trackSide, mySide, hostileFlag) {
    if (hostileFlag) return 'H';
    var s = side(trackSide), me = side(mySide) || 'WEST';
    if (!s || s === me) return 'F';
    if (s === 'CIV') return 'N';
    if (me === 'CIV') return 'U';
    if (s === 'GUER' || me === 'GUER') return 'U';
    return 'H';
  }

  /* ---------- Pistes ---------- */

  /** Ligne de /api/atak/units?toc=1 -> piste. */
  function unitTrack(u, sym) {
    var ex = u.extra && typeof u.extra === 'object' ? u.extra : {};
    var t = u.toc || {};
    var age = t.age != null ? Number(t.age) : (u.age_seconds != null ? Number(u.age_seconds) : null);
    var kind = t.kind || kindOf(sym, ex.platform || ex.vehicle_class) || 'infantry';
    var callsign = String(u.display_call_sign || u.call_sign || '?');
    var group = String(u.group_name || ex.group_name || ex.group || '');
    return {
      id: 'u' + u.id, uid: t.uid || ('i:' + u.id), src: 'unit', rowId: u.id, callsign: callsign, group: group, kind: kind,
      air: t.air != null ? !!t.air : isAir(sym, kind), side: side(ex.side || ex.affiliation), hostile: !!t.hostile,
      pos: posOf(u.pos_x, u.pos_y), heading: num(u.heading != null ? u.heading : ex.heading), speed: num(t.speed), alt: num(t.alt),
      status: String(u.status || ''), fresh: freshness(u.status, age), age: age, ai: !!t.ai, vehicle: String(t.vehicle || ''),
      crew: t.crew != null ? Number(t.crew) : null, battery: t.battery != null ? Number(t.battery) : null,
      health: String(ex.health || ''), role: String(u.role || ex.role || ''), updated_at: u.updated_at || ''
    };
  }
  /** Ligne de /api/atak/vehicles?toc=1 -> piste. */
  function vehicleTrack(v, sym) {
    var t = v.toc || {};
    var kind = t.kind || kindOf(sym, v.vehicle_class) || 'motorized';
    var age = t.age != null ? Number(t.age) : null;
    return {
      id: 'v' + (v.id != null ? v.id : v.vehicle_callsign), uid: t.uid || ('v:' + v.id), src: 'vehicle', rowId: v.id,
      callsign: String(v.vehicle_callsign || v.vehicle_name || 'Véhicule'), group: String(v.unit_assigned || ''), kind: kind,
      air: t.air != null ? !!t.air : isAir(sym, kind), side: side(v.side), hostile: false, pos: posOf(v.pos_x, v.pos_y),
      heading: num(v.heading), speed: num(v.speed), alt: num(v.pos_z), status: String(v.status || ''),
      fresh: freshness(String(v.status || '').toUpperCase() === 'DESTROYED' ? 'destroyed' : 'linked', age), age: age, ai: false,
      vehicle: String(v.vehicle_name || v.vehicle_type || ''), crew: v.crew_count != null ? Number(v.crew_count) : null, crewMax: v.crew_max != null ? Number(v.crew_max) : null,
      battery: null, health: '', role: '', fuel: v.fuel_level != null ? Number(v.fuel_level) : (v.fuel_pct != null ? Number(v.fuel_pct) : null), updated_at: v.last_seen_at || v.updated_at || ''
    };
  }
  /** Ligne de /api/atak/air-assets?toc=1 -> piste. */
  function airTrack(a, sym) {
    var t = a.toc || {};
    var kind = t.kind || kindOf(sym, a.aircraft_type) || 'fixed';
    var age = t.age != null ? Number(t.age) : null;
    var st = String(a.status || '').toUpperCase();
    return {
      id: 'a' + String(a.callsign || '').replace(/\s+/g, '_'), uid: t.uid || ('a:' + String(a.callsign || '').toLowerCase()), src: 'air', rowId: a.callsign,
      callsign: String(a.callsign || 'Aéronef'), group: '', kind: kind, air: true, side: side(a.side), hostile: false,
      pos: posOf(a.pos_x, a.pos_y), heading: num(a.heading), speed: num(a.speed), alt: num(a.alt), status: st,
      fresh: freshness(st === 'OFFLINE' ? 'offline' : 'linked', age), age: age, ai: false, vehicle: String(a.model || ''),
      crew: a.crew_count != null ? Number(a.crew_count) : null, battery: null, health: '', role: String(a.mission_id || ''),
      fuel: a.fuel_pct != null ? Number(a.fuel_pct) : null, pilot: String(a.pilot || ''), ground: st === 'AVAILABLE', updated_at: a.updated_at || ''
    };
  }

  var FRESH_RANK = { live: 3, old: 2, lost: 1 };
  function better(a, b) {
    var ra = FRESH_RANK[a.fresh] || 0, rb = FRESH_RANK[b.fresh] || 0;
    if (ra !== rb) return ra > rb;
    var aa = a.age == null ? Infinity : a.age, ab = b.age == null ? Infinity : b.age;
    return aa < ab;
  }
  /** Une piste par identifiant stable : la plus vivante, puis la plus récente. Ordre d'arrivée gardé. */
  function dedupe(tracks) {
    var by = {}, order = [];
    (tracks || []).forEach(function (t) {
      var k = t.uid || t.id;
      if (!by[k]) { by[k] = t; order.push(k); return; }
      if (better(t, by[k])) by[k] = t;
    });
    return order.map(function (k) { return by[k]; });
  }
  /**
   * Fusion des trois sources sans doublon : un aéronef suivi remplace le pilote qui porte le même indicatif ; un
   * véhicule suivi remplace le nœud du même indicatif ; un joueur n'apparaît qu'une fois.
   */
  function merge(units, vehicles, air) {
    var key = function (t) { return String(t.callsign || '').toUpperCase().replace(/\s+/g, ' ').trim(); };
    var taken = {};
    var out = [];
    dedupe(air).forEach(function (t) { taken[key(t)] = true; out.push(t); });
    dedupe(vehicles).forEach(function (t) { if (!taken[key(t)]) { taken[key(t)] = true; out.push(t); } });
    dedupe(units).forEach(function (t) { if (!taken[key(t)]) out.push(t); });
    return out;
  }

  /** Groupes d'un camp : nom, membres (dédoublonnés), comptes par état, position du membre le plus frais. */
  function groups(tracks) {
    var by = {}, order = [];
    (tracks || []).forEach(function (t) {
      var g = t.group || t.callsign;
      if (!by[g]) { by[g] = { name: g, members: [], live: 0, old: 0, lost: 0, players: 0, pos: null, age: null, lead: null }; order.push(g); }
      var e = by[g];
      e.members.push(t);
      e[t.fresh] += 1;
      if (!t.ai) e.players += 1;
    });
    return order.map(function (k) {
      var e = by[k];
      // Membres : en jeu d'abord, puis anciens, puis perdus ; par indicatif dans chaque état (ordre stable d'un relevé à l'autre).
      e.members.sort(function (a, b) { return ((FRESH_RANK[b.fresh] || 0) - (FRESH_RANK[a.fresh] || 0)) || a.callsign.localeCompare(b.callsign, undefined, { numeric: true }); });
      e.state = e.live ? 'live' : (e.old ? 'old' : 'lost');
      for (var i = 0; i < e.members.length; i++) if (e.members[i].pos) { e.lead = e.members[i]; break; }
      if (!e.lead) e.lead = e.members[0];
      e.pos = e.lead.pos;
      e.age = e.lead.age;
      e.kind = e.lead.kind;
      e.single = e.members.length === 1;
      return e;
    }).sort(function (a, b) {
      var d = (FRESH_RANK[b.state] || 0) - (FRESH_RANK[a.state] || 0);
      return d || a.name.localeCompare(b.name, undefined, { numeric: true });
    });
  }

  /* ---------- Regroupement à petite échelle ---------- */

  /**
   * items : [{id, x, y, group, prio, solo}] en pixels. radius : rayon de regroupement (0 = aucun).
   * Retour : [{ids, x, y, group (nom commun ou ''), groups (nombre de groupes), lead (id du plus prioritaire)}].
   * solo (aéronef, piste choisie, mon unité) : jamais regroupé.
   */
  function cluster(items, radius) {
    var list = (items || []).slice().sort(function (a, b) { return (b.prio || 0) - (a.prio || 0); });
    var out = [];
    if (!(radius > 0)) return list.map(function (it) { return { ids: [it.id], x: it.x, y: it.y, group: it.group || '', groups: 1, lead: it.id }; });
    var cell = radius, grid = {};
    var free = [];
    list.forEach(function (it, i) {
      if (it.solo) { out.push({ ids: [it.id], x: it.x, y: it.y, group: it.group || '', groups: 1, lead: it.id }); return; }
      var k = Math.floor(it.x / cell) + ':' + Math.floor(it.y / cell);
      (grid[k] = grid[k] || []).push(i);
      free.push(i);
    });
    var used = {};
    var r2 = radius * radius;
    free.forEach(function (i) {
      if (used[i]) return;
      var seed = list[i];
      used[i] = true;
      var mem = [seed];
      var cx = Math.floor(seed.x / cell), cy = Math.floor(seed.y / cell);
      for (var gx = cx - 1; gx <= cx + 1; gx++) {
        for (var gy = cy - 1; gy <= cy + 1; gy++) {
          (grid[gx + ':' + gy] || []).forEach(function (j) {
            if (used[j]) return;
            var o = list[j], dx = o.x - seed.x, dy = o.y - seed.y;
            if (dx * dx + dy * dy <= r2) { used[j] = true; mem.push(o); }
          });
        }
      }
      var names = {}, n = 0, sx = 0, sy = 0;
      mem.forEach(function (m) { var g = m.group || m.id; if (!names[g]) { names[g] = true; n++; } sx += m.x; sy += m.y; });
      out.push({ ids: mem.map(function (m) { return m.id; }), x: mem.length > 1 ? sx / mem.length : seed.x, y: mem.length > 1 ? sy / mem.length : seed.y, group: n === 1 ? (seed.group || '') : '', groups: n, lead: seed.id });
    });
    return out;
  }

  /** Détail des plaques selon l'échelle (mètres par pixel) : complet de près, indicatif seul, symboles seuls de loin. */
  function labelMode(mpp) {
    if (mpp <= 1.6) return 'full';
    if (mpp <= 40) return 'name';
    return 'none';
  }
  /** Rayon de regroupement (px) selon l'échelle : aucun de près. */
  function clusterRadius(mpp) {
    // Au plus près, chaque piste a son symbole ; ensuite, des symboles qui se recouvriraient sont regroupés.
    if (mpp <= 0.3) return 0;
    if (mpp <= 2.5) return 26;
    return mpp <= 12 ? 36 : 48;
  }

  /* ---------- Placement des plaques ---------- */

  function hit(a, b) { return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y; }
  // Positions autour du symbole, dans l'ordre de préférence : droite, puis diagonales, gauche, dessus, dessous.
  var SLOTS = [[1, 0], [1, -1], [1, 1], [-1, 0], [-1, -1], [-1, 1], [0, -1], [0, 1]];

  /**
   * items : [{id, x, y, r (demi-côté du symbole), w, h (plaque ; w = 0 : pas de plaque), prio, must}].
   * Retour : {id: {dx, dy, slot}} en pixels depuis le centre du symbole (coin haut-gauche de la plaque), ou null quand
   * aucune place libre (plaque masquée : le symbole reste, détail au survol). must (piste choisie, mon unité) : placée
   * quand même, à l'endroit le moins chargé. Une plaque ne recouvre ni une autre plaque ni un symbole.
   */
  function placeLabels(items, opts) {
    opts = opts || {};
    var gap = opts.gap != null ? opts.gap : 6;
    var rings = opts.rings || [1, 2.6];
    var bounds = opts.bounds || null;
    var list = (items || []).slice().sort(function (a, b) { return (b.prio || 0) - (a.prio || 0); });
    var cell = 96, grid = {};
    function cells(r, fn) {
      for (var gx = Math.floor(r.x / cell); gx <= Math.floor((r.x + r.w) / cell); gx++) {
        for (var gy = Math.floor(r.y / cell); gy <= Math.floor((r.y + r.h) / cell); gy++) fn(gx + ':' + gy);
      }
    }
    function add(r) { cells(r, function (k) { (grid[k] = grid[k] || []).push(r); }); }
    function overlaps(r, own) {
      var n = 0, seen = [];
      cells(r, function (k) {
        (grid[k] || []).forEach(function (o) {
          if (o === own || seen.indexOf(o) >= 0) return;
          seen.push(o);
          if (hit(r, o)) n++;
        });
      });
      return n;
    }
    var boxes = {};
    list.forEach(function (it) {
      var b = { x: it.x - it.r, y: it.y - it.r, w: it.r * 2, h: it.r * 2 };
      boxes[it.id] = b;
      add(b);
    });
    var out = {};
    list.forEach(function (it) {
      if (!(it.w > 0)) { out[it.id] = null; return; }
      var best = null, bestN = Infinity;
      for (var ri = 0; ri < rings.length && bestN > 0; ri++) {
        var d = it.r + gap * rings[ri] + (ri ? it.r * (rings[ri] - 1) : 0);
        for (var s = 0; s < SLOTS.length; s++) {
          var sx = SLOTS[s][0], sy = SLOTS[s][1];
          var x = sx > 0 ? it.x + d : (sx < 0 ? it.x - d - it.w : it.x - it.w / 2);
          var y = sy === 0 ? it.y - it.h / 2 : (sy < 0 ? it.y - d - it.h : it.y + d);
          // Diagonales : plaque à côté du symbole, décalée vers le haut ou le bas (et plus loin aux anneaux suivants).
          if (sx !== 0 && sy !== 0) y = sy < 0 ? it.y - it.h - 2 - (d - it.r - gap) : it.y + 2 + (d - it.r - gap);
          var r = { x: x, y: y, w: it.w, h: it.h };
          if (bounds && (r.x < bounds.x || r.y < bounds.y || r.x + r.w > bounds.x + bounds.w || r.y + r.h > bounds.y + bounds.h) && !(it.x < bounds.x || it.y < bounds.y || it.x > bounds.x + bounds.w || it.y > bounds.y + bounds.h)) continue;
          var n = overlaps(r, boxes[it.id]);
          if (n < bestN) { bestN = n; best = { r: r, slot: s, ring: ri }; if (n === 0) break; }
        }
      }
      if (!best || (bestN > 0 && !it.must)) { out[it.id] = null; return; }
      add(best.r);
      out[it.id] = { dx: Math.round(best.r.x - it.x), dy: Math.round(best.r.y - it.y), slot: best.slot, ring: best.ring };
    });
    return out;
  }

  /** Trait de rappel : du bord du symbole au bord le plus proche de la plaque (repère du centre du symbole). */
  function leader(place, w, h, r) {
    if (!place) return null;
    var cx = Math.max(place.dx, Math.min(0, place.dx + w)), cy = Math.max(place.dy, Math.min(0, place.dy + h));
    var len = Math.sqrt(cx * cx + cy * cy);
    if (len < r + 11) return null;
    var k = (r + 1) / len;
    return { x1: Math.round(cx * k), y1: Math.round(cy * k), x2: Math.round(cx), y2: Math.round(cy) };
  }

  /* ---------- Marqueurs Arma ---------- */

  /** Type de marqueur Arma -> {icon} ou {nato (genre), affil}. Même règle que TocSymbols::marker côté site. */
  function markerSpec(sym, type) {
    var t = String(type || '').trim().toLowerCase().replace(/[\s-]+/g, '_');
    var m = /^([boncu])_(.+)$/.exec(t);
    if (m && sym && sym.natoMarkers) {
      var role = m[2].replace(/_ca$/, '');
      if (sym.natoMarkers[role]) return { icon: '', nato: sym.natoMarkers[role], affil: (sym.natoPrefix && sym.natoPrefix[m[1]]) || 'F' };
    }
    if (t.indexOf('flag') === 0) return { icon: 'flag', nato: '', affil: '' };
    var icons = (sym && sym.markerIcons) || {};
    var bare = t.replace(/^(mil|hd|mplus|mts)_/, '');
    var icon = icons[t] || icons[bare] || (t.indexOf('respawn_') === 0 ? 'respawn' : '');
    return { icon: icon || 'dot', nato: '', affil: '' };
  }

  var ARMA_COLORS = {
    colorred: '#ff5f56', colorblue: '#62b0f5', colorgreen: '#7ee08a', coloryellow: '#f2d36b', colororange: '#ff9433', colorwhite: '#f2f2f2',
    colorblack: '#20262b', colorblufor: '#6cb8ff', colorwest: '#6cb8ff', coloropfor: '#ff6b6b', coloreast: '#ff6b6b', colorindependent: '#7ee08a',
    colorguer: '#7ee08a', colorcivilian: '#c780f2', colorciv: '#c780f2', colorpink: '#ff80c0', colorbrown: '#b98a55', colorkhaki: '#bdb76b',
    colorgrey: '#b4bec6', colorgray: '#b4bec6', colorunknown: '#f2d36b', default: '#f2d36b'
  };
  function markerColor(c, fallback) {
    var s = String(c || '').trim();
    if (/^#[0-9a-f]{6}$/i.test(s)) return s;
    if (/^[0-9a-f]{6}$/i.test(s)) return '#' + s;
    return ARMA_COLORS[s.toLowerCase()] || fallback || '#f2d36b';
  }

  /** Position d'un marqueur : x/y, pos en tableau, ou pos en texte « [1234.5,6789,0] ». */
  function markerPos(d) {
    if (!d) return null;
    var p = posOf(d.x, d.y);
    if (p) return p;
    var pos = d.pos != null ? d.pos : d.position;
    if (typeof pos === 'string') {
      var nums = pos.match(/-?\d+(?:\.\d+)?(?:e[-+]?\d+)?/gi);
      return nums && nums.length >= 2 ? posOf(nums[0], nums[1]) : null;
    }
    if (Array.isArray(pos)) return Array.isArray(pos[0]) ? posOf(pos[0][0], pos[0][1]) : posOf(pos[0], pos[1]);
    if (pos && typeof pos === 'object') return posOf(pos.x, pos.y);
    return posOf(d.pos_x, d.pos_y);
  }

  /** Points d'un tracé de marqueur (POLYLINE) : liste plate [x1,y1,x2,y2…] ou liste de couples. */
  function markerPath(d) {
    var poly = d && (d.polyline || d.points || d.path);
    var out = [];
    if (!Array.isArray(poly)) return out;
    if (typeof poly[0] === 'number') {
      for (var i = 0; i + 1 < poly.length; i += 2) { var p = posOf(poly[i], poly[i + 1]); if (p) out.push(p); }
    } else {
      poly.forEach(function (q) { var p2 = Array.isArray(q) ? posOf(q[0], q[1]) : (q ? posOf(q.x, q.y) : null); if (p2) out.push(p2); });
    }
    return out;
  }

  /** Remplissage d'une zone selon le pinceau Arma : {fill (opacité), hatch ('' | 'diag' | 'cross' | 'grid' | 'horiz' | 'vert'), stroke}. */
  function brush(name, alpha) {
    var b = String(name || 'Solid').toLowerCase();
    var a = alpha == null || !isFinite(Number(alpha)) ? 1 : Math.max(0.15, Math.min(1, Number(alpha)));
    if (b.indexOf('border') >= 0 || b.indexOf('hollow') >= 0) return { fill: 0, hatch: '', stroke: a };
    if (b.indexOf('solidfull') >= 0) return { fill: 0.6 * a, hatch: '', stroke: a };
    if (b.indexOf('solidborder') >= 0 || b === 'solid') return { fill: 0.28 * a, hatch: '', stroke: a };
    if (b.indexOf('cross') >= 0) return { fill: 0.06 * a, hatch: 'cross', stroke: a };
    if (b.indexOf('grid') >= 0) return { fill: 0.06 * a, hatch: 'grid', stroke: a };
    if (b.indexOf('horizontal') >= 0) return { fill: 0.06 * a, hatch: 'horiz', stroke: a };
    if (b.indexOf('vertical') >= 0) return { fill: 0.06 * a, hatch: 'vert', stroke: a };
    if (b.indexOf('diag') >= 0) return { fill: 0.06 * a, hatch: 'diag', stroke: a };
    return { fill: 0.22 * a, hatch: '', stroke: a };
  }

  /* Icônes des repères : tracés 24 x 24, trait seul (f : rempli). Une entrée par icône de TocSymbols::ICONS. */
  var ICONS = {
    dot: { d: 'M12 7a5 5 0 1 0 0 10 5 5 0 1 0 0-10z', f: 1 },
    circle: { d: 'M12 4a8 8 0 1 0 0 16 8 8 0 1 0 0-16z' },
    square: { d: 'M5 5h14v14H5z' },
    triangle: { d: 'M12 4l9 16H3z' },
    objective: { d: 'M12 3a9 9 0 1 0 0 18 9 9 0 1 0 0-18zM6 6l12 12M18 6L6 18' },
    pin: { d: 'M12 21s-6-6.2-6-11a6 6 0 1 1 12 0c0 4.800-6 11-6 11zM12 8a2 2 0 1 0 0 4 2 2 0 1 0 0-4z' },
    flag: { d: 'M6 21V3M6 4h12l-3 4 3 4H6' },
    arrow: { d: 'M12 21V5M5 11l7-7 7 7' },
    ambush: { d: 'M4 17c2-8 14-8 16 0M12 11V3M8 6l4-3 4 3' },
    destroy: { d: 'M5 5l14 14M19 5L5 19' },
    start: { d: 'M5 4v16M5 5l13 5-13 5' },
    end: { d: 'M5 4v16M5 5h13v9H5M11 5v9M5 9.500h13' },
    pickup: { d: 'M12 3v12M6 10l6 6 6-6M4 20h16' },
    join: { d: 'M4 6l8 7 8-7M12 13v8' },
    warning: { d: 'M12 3l10 18H2zM12 10v5M12 17.500v.5' },
    unknown: { d: 'M8.500 9a3.500 3.500 0 1 1 5 3.200c-1 .6-1.500 1.300-1.500 2.800M12 18.500v.5M12 2a10 10 0 1 0 0 20 10 10 0 1 0 0-20z' },
    waypoint: { d: 'M12 3l7 9-7 9-7-9zM12 9.500a2.500 2.500 0 1 0 0 5 2.500 2.500 0 1 0 0-5z' },
    kia: { d: 'M12 3v18M6 9h12' },
    mine: { d: 'M12 7a5 5 0 1 0 0 10 5 5 0 1 0 0-10zM12 2v5M12 17v5M2 12h5M17 12h5M5 5l3.500 3.500M15.500 15.500L19 19M19 5l-3.500 3.500M8.500 15.500L5 19' },
    label: { d: 'M4 7h16M12 7v12' },
    respawn: { d: 'M12 4a8 8 0 1 0 8 8M20 4v6h-6' },
    heli: { d: 'M12 3a9 9 0 1 0 0 18 9 9 0 1 0 0-18zM8.500 7.500v9M15.500 7.500v9M8.500 12h7' },
    plane: { d: 'M12 2l1.500 7 8.500 5v2l-8.500-2.500-.5 5 2.500 2v1.500l-3.500-1-3.500 1V20.500l2.500-2-.5-5L2 16v-2l8.500-5z' },
    anchor: { d: 'M12 8v13M12 3a2.500 2.500 0 1 0 0 5 2.500 2.500 0 1 0 0-5zM4 14c0 4 3.500 7 8 7s8-3 8-7M8 11h8' },
    para: { d: 'M3 10a9 7 0 0 1 18 0zM3 10l9 11 9-11M12 10v11' },
    medical: { d: 'M9 3h6v6h6v6h-6v6H9v-6H3V9h6z' },
    fuel: { d: 'M5 21V4h9v17M3 21h13M7 7h5v4H7zM14 10l4 2v6a1.500 1.500 0 0 0 3 0V8l-3-3' },
    ammo: { d: 'M4 8h16v12H4zM4 12h16M9 8V5h6v3' },
    repair: { d: 'M14.500 3.500a5 5 0 0 0-5 6.500L3 16.500 7.500 21l6.500-6.500a5 5 0 0 0 6.500-5l-3.500 3-3-3z' },
    vehicle: { d: 'M3 16V9l3-4h9l3 4h3v7M3 16h18M7.500 14a2.500 2.500 0 1 0 0 5 2.500 2.500 0 1 0 0-5zM16.500 14a2.500 2.500 0 1 0 0 5 2.500 2.500 0 1 0 0-5z' },
    tower: { d: 'M12 8l-5 13M12 8l5 13M9 15h6M12 8V3M7.500 4.500a6 6 0 0 0 0 7M16.500 4.500a6 6 0 0 1 0 7' },
    bunker: { d: 'M3 20v-7a9 9 0 0 1 18 0v7zM8 13h8v3H8z' },
    building: { d: 'M4 21V8l8-5 8 5v13zM10 21v-6h4v6' },
    eye: { d: 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 9a3 3 0 1 0 0 6 3 3 0 1 0 0-6z' },
    relay: { d: 'M12 21V10M8 21h8M12 10l-4 11M12 10l4 11M12 5.500a2 2 0 1 0 0 4 2 2 0 1 0 0-4zM6.500 3a7 7 0 0 0 0 9M17.500 3a7 7 0 0 1 0 9' },
    photo: { d: 'M3 7h4l2-3h6l2 3h4v13H3zM12 10a3.500 3.500 0 1 0 0 7 3.500 3.500 0 1 0 0-7z' },
    report: { d: 'M4 4h16v12H10l-5 4v-4H4zM8 8.500h8M8 12h5' },
    request: { d: 'M12 3l9 9-9 9-9-9zM12 8v5M12 15.500v.5' },
    order: { d: 'M5 3h10l4 4v14H5zM15 3v4h4M8.500 12h7M8.500 16h7' },
    rally: { d: 'M12 3a9 9 0 1 0 0 18 9 9 0 1 0 0-18zM12 7v10M7 12h10' },
    checkpoint: { d: 'M12 3a9 9 0 1 0 0 18 9 9 0 1 0 0-18z' }
  };

  /** SVG d'une icône : fond sombre arrondi (lisible sur photo claire), tracé de couleur, liseré sombre. text : 1 à 3 caractères au centre. */
  function iconSvg(id, color, size, text) {
    var ic = ICONS[id] || ICONS.dot;
    var s = size || 24;
    var c = color || '#f2d36b';
    var halo = '<path d="' + ic.d + '" fill="none" stroke="#070a0d" stroke-opacity=".9" stroke-width="5" stroke-linejoin="round" stroke-linecap="round"/>';
    var body = '<path d="' + ic.d + '" fill="' + (ic.f ? c : 'none') + '" stroke="' + c + '" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>';
    var txt = text ? '<text x="12" y="' + (String(text).length > 2 ? 15 : 16) + '" text-anchor="middle" font-family="Roboto Condensed,Arial Narrow,Arial" font-weight="700" font-size="' + (String(text).length > 2 ? 8 : 10.500) + '" fill="' + c + '" stroke="#070a0d" stroke-width="2.4" paint-order="stroke">' + String(text).replace(/[<>&"]/g, '') + '</text>' : '';
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' + s + '" height="' + s + '" viewBox="0 0 24 24" aria-hidden="true">' + (text ? '<path d="' + ic.d + '" fill="rgba(7,10,13,.78)" stroke="none"/>' : '') + halo + body + txt + '</svg>';
  }

  /* ---------- Fond de carte ---------- */

  /**
   * Voile sombre posé sur la photo selon le réglage « luminosité du fond » (0 à 100). Courbe de contraste : la photo
   * reste lisible au milieu de la course (voile 0,18 à 55 %), nettement assombrie en bas, sans voile en haut.
   */
  function veilFor(brightness) {
    var b = Math.max(0, Math.min(100, Number(brightness)));
    if (!isFinite(b)) b = 55;
    var t = 1 - b / 100;
    return Math.round(Math.pow(t, 1.6) * 0.72 * 1000) / 1000;
  }

  /* ---------- Recherche ---------- */

  function norm(s) { return String(s || '').toUpperCase().normalize ? String(s || '').toUpperCase().normalize('NFD').replace(/[̀-ͯ]/g, '') : String(s || '').toUpperCase(); }
  /**
   * Suggestions : entries = [{label, sub, kind ('group' | 'track' | 'marker' | 'point' | 'grid'), pos, id}].
   * Débuts de mot d'abord, puis contenu ; une grille saisie (6, 8 ou 10 chiffres) vient en tête.
   */
  function suggest(query, entries, parseGrid, limit) {
    var q = norm(query).trim();
    var out = [];
    if (!q) return out;
    var g = parseGrid ? parseGrid(query) : null;
    if (g && /^[\d\s]+$/.test(String(query))) out.push({ label: String(query).trim(), sub: '', kind: 'grid', pos: g, id: 'grid' });
    var starts = [], has = [];
    (entries || []).forEach(function (e) {
      var l = norm(e.label), s = norm(e.sub);
      if (l.indexOf(q) === 0 || l.split(/[\s·\-_]+/).some(function (w) { return w.indexOf(q) === 0; })) starts.push(e);
      else if (l.indexOf(q) >= 0 || s.indexOf(q) >= 0) has.push(e);
    });
    return out.concat(starts, has).slice(0, limit || 8);
  }

  return {
    AFFIL_COLOR: AFFIL_COLOR, LOST_COLOR: LOST_COLOR, ICONS: ICONS, side: side, num: num, posOf: posOf,
    freshness: freshness, ageLabel: ageLabel, ageShort: ageShort, altLabel: altLabel, speedLabel: speedLabel, headingLabel: headingLabel,
    kindOf: kindOf, isAir: isAir, kindLabel: kindLabel, sidc: sidc, affiliation: affiliation,
    unitTrack: unitTrack, vehicleTrack: vehicleTrack, airTrack: airTrack, dedupe: dedupe, merge: merge, groups: groups,
    cluster: cluster, labelMode: labelMode, clusterRadius: clusterRadius, placeLabels: placeLabels, leader: leader,
    markerSpec: markerSpec, markerColor: markerColor, markerPos: markerPos, markerPath: markerPath, brush: brush, iconSvg: iconSvg,
    veilFor: veilFor, suggest: suggest
  };
});
