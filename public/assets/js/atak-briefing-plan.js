/* Calque « Plan de briefing » : zones, lignes et points dessinés dans l'app Plan de l'ATAK natif (lecture seule).
   Source : GET /api/atak/briefing-plan (plan partagé de chaque camp, envoyé par les téléphones). */
(function () {
  'use strict';

  var group = null;
  var lastSig = '';
  var lastPlans = [];
  var timer = null;

  var COLORS = {
    RED: '#f24d47', CYAN: '#4dccf2', GREEN: '#73d973', YELLOW: '#fae04d', BLUE: '#5999ff',
    WHITE: '#f2f2f2', ORANGE: '#ff9433', PURPLE: '#c780f2', BLACK: '#141414'
  };
  var SIDES = { WEST: 'BLUFOR', EAST: 'OPFOR', GUER: 'INDFOR', CIV: 'Civils' };
  // Géométrie et libellé de chaque type de l'app Plan (fn_planCatalog).
  var KINDS = {
    ENY: ['AREA', 'Ennemi (zone)'], INFIL: ['AREA', 'Infiltration'], EXFIL: ['AREA', 'Exfiltration'], OBJ: ['AREA', 'Objectif'],
    AO: ['AREA', "Zone d'action (AO)"], NAI: ['AREA', "Zone d'intérêt (NAI)"], EA: ['AREA', "Zone d'engagement (EA)"],
    AA: ['AREA', 'Zone de regroupement (AA)'], HLZ: ['AREA', 'Zone de poser (HLZ)'], NFA: ['AREA', 'Tirs interdits (NFA)'], ZONE: ['AREA', 'Zone'],
    PL: ['PHASE', 'Ligne de phase'], LD: ['PHASE', 'Ligne de débouché'], LOA: ['PHASE', "Limite d'avance"],
    BDY: ['LINE', 'Limite'], ROUTE: ['LINE', 'Itinéraire'], MOVE: ['LINE', 'Mouvement'], AXIS: ['LINE', 'Axe de progression'],
    ATK: ['LINE', 'Attaque principale'], SPT: ['LINE', 'Attaque secondaire'], FREE: ['LINE', 'Tracé libre'],
    CLEARED: ['POINT', 'Bâtiment nettoyé'], UNCLEARED: ['POINT', 'Bâtiment non nettoyé'], UNITF: ['POINT', 'Unité amie'],
    UNITH: ['POINT', 'Unité ennemie'], NOTE: ['POINT', 'Note'], CP: ['POINT', 'Point de contrôle'], RP: ['POINT', 'Point de ralliement'],
    TRP: ['POINT', 'Point de référence de tir'], OBJPT: ['POINT', 'Objectif'],
    SBF: ['DIR', 'Appui par le feu'], ABF: ['DIR', 'Attaque par le feu']
  };

  function esc(t) {
    return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function prefs() {
    return window.ATAKMap && window.ATAKMap.getDisplayPrefs ? window.ATAKMap.getDisplayPrefs() : {};
  }
  function getMap() {
    return window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
  }
  function toLatLng(p) {
    if (!p || p.length < 2 || !window.ATAKMap || !window.ATAKMap.latLngFromWorld) return null;
    return window.ATAKMap.latLngFromWorld(Number(p[0]), Number(p[1]));
  }
  function ensureGroup(map) {
    if (!group) group = L.layerGroup();
    if (map && !map.hasLayer(group)) group.addTo(map);
    return group;
  }
  function label(ll, text, color) {
    return L.marker(ll, {
      interactive: false,
      keyboard: false,
      icon: L.divIcon({
        className: 'atak-briefing-plan-label atak-compact-marker',
        html: '<span style="color:' + color + ';border-color:' + color + '">' + esc(text) + '</span>',
        iconSize: null,
        iconAnchor: [0, 10]
      })
    });
  }
  function popupHtml(item, plan) {
    var def = KINDS[item.kind] || ['', item.kind];
    var bits = ['<strong>' + esc(def[1]) + (item.text ? ' · ' + esc(item.text) : '') + '</strong>'];
    bits.push('Plan de briefing ' + esc(SIDES[plan.side] || plan.side) + (plan.name ? ' « ' + esc(plan.name) + ' »' : ''));
    if (item.author) bits.push('Posé par ' + esc(item.author));
    return bits.join('<br>');
  }
  // Pointe de flèche au bout d'un trait (en coordonnées monde, 3 % de la longueur, 25 à 150 m).
  function arrowHead(pts) {
    var a = pts[pts.length - 2];
    var b = pts[pts.length - 1];
    var dx = b[0] - a[0];
    var dy = b[1] - a[1];
    var len = Math.sqrt(dx * dx + dy * dy);
    if (len < 1) return null;
    var total = 0;
    for (var i = 1; i < pts.length; i++) {
      total += Math.sqrt(Math.pow(pts[i][0] - pts[i - 1][0], 2) + Math.pow(pts[i][1] - pts[i - 1][1], 2));
    }
    var h = Math.max(25, Math.min(150, total * 0.06));
    var ux = dx / len;
    var uy = dy / len;
    var bx = b[0] - ux * h;
    var by = b[1] - uy * h;
    return [[bx + uy * h * 0.55, by - ux * h * 0.55], b, [bx - uy * h * 0.55, by + ux * h * 0.55]];
  }
  function drawItem(g, item, plan) {
    var def = KINDS[item.kind];
    if (!def || !Array.isArray(item.pts) || !item.pts.length) return;
    var geom = def[0];
    var color = COLORS[item.color] || COLORS.WHITE;
    var weight = 1 + (Number(item.width) || 2) * 1.2;
    var dash = item.dashed ? '8,6' : null;
    var text = item.text || '';
    var pts = item.pts.filter(function (p) { return Array.isArray(p) && p.length >= 2; });
    var lls = pts.map(toLatLng).filter(Boolean);
    if (!lls.length) return;
    var popup = popupHtml(item, plan);

    if (geom === 'AREA') {
      var ring = lls;
      if (pts.length === 2) {
        var a = pts[0];
        var b = pts[1];
        ring = [[a[0], a[1]], [b[0], a[1]], [b[0], b[1]], [a[0], b[1]]].map(toLatLng);
      }
      if (ring.length < 3) return;
      L.polygon(ring, { color: color, weight: weight, dashArray: dash, fillColor: color, fillOpacity: 0.14 }).bindPopup(popup).addTo(g);
      if (text) label(L.polygon(ring).getBounds().getCenter(), text, color).addTo(g);
      return;
    }
    if (geom === 'POINT') {
      var t = text;
      if ((item.kind === 'CLEARED' || item.kind === 'UNCLEARED') && Number(item.num) > 0) t = (t || item.kind) + ' ' + item.num;
      var shape = item.kind === 'UNITH' ? 'diamond' : (item.kind === 'UNITF' ? 'rect' : 'dot');
      L.marker(lls[0], {
        keyboard: false,
        icon: L.divIcon({
          className: 'atak-briefing-plan-point atak-briefing-plan-point--' + shape,
          html: '<i style="border-color:' + color + ';background:' + color + '33"></i>' + (t ? '<span style="color:' + color + '">' + esc(t) + '</span>' : ''),
          iconSize: [16, 16],
          iconAnchor: [8, 8]
        })
      }).bindPopup(popup).addTo(g);
      return;
    }
    if (lls.length < 2) {
      if (geom === 'DIR') L.circleMarker(lls[0], { radius: 6, color: color, weight: 2 }).bindPopup(popup).addTo(g);
      return;
    }
    L.polyline(lls, { color: color, weight: weight, dashArray: dash }).bindPopup(popup).addTo(g);
    if (item.arrow || geom === 'DIR') {
      var head = arrowHead(pts);
      if (head) L.polyline(head.map(toLatLng), { color: color, weight: weight, interactive: false }).addTo(g);
    }
    if (geom === 'PHASE') {
      var name = (item.kind + ' ' + text).trim();
      label(lls[0], name, color).addTo(g);
      label(lls[lls.length - 1], name, color).addTo(g);
    } else if (text) {
      label(geom === 'DIR' ? lls[0] : lls[Math.floor(lls.length / 2)], text, color).addTo(g);
    }
  }
  function draw(plans) {
    var map = getMap();
    if (!map) return;
    var g = ensureGroup(map);
    g.clearLayers();
    if (prefs().showBriefingPlan === false) return;
    (plans || []).forEach(function (plan) {
      (plan.items || []).forEach(function (item) {
        try { drawItem(g, item, plan); } catch (e) { /* élément illisible : ignoré */ }
      });
    });
  }
  function poll() {
    if (!getMap()) return;
    if (prefs().showBriefingPlan === false) {
      if (lastSig !== '') { lastSig = ''; draw([]); }
      return;
    }
    var base = window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : '';
    var mapId = window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
    fetch((base || '') + '/api/atak/briefing-plan?mapId=' + encodeURIComponent(mapId), { credentials: 'include', cache: 'no-store' })
      .then(function (r) {
        if (!r.ok) throw new Error('briefing_plan_http_' + r.status);
        return r.json();
      })
      .then(function (payload) {
        if (!payload || !Array.isArray(payload.plans)) return;
        var sig = JSON.stringify(payload.plans.map(function (p) { return [p.side, p.rev, p.updated_at]; }));
        if (sig === lastSig) return;
        lastSig = sig;
        lastPlans = payload.plans;
        draw(lastPlans);
      })
      .catch(function () { /* garder le dernier plan affiché */ });
  }

  window.addEventListener('atak:mapready', function () {
    ensureGroup(getMap());
    poll();
    if (!timer) timer = setInterval(poll, 10000);
  });
  window.addEventListener('atak:display-prefs-changed', function () {
    lastSig = '';
    draw(prefs().showBriefingPlan === false ? [] : lastPlans);
    poll();
  });
})();
