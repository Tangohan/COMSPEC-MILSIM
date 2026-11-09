/*
 * Style tactique de la carte du téléphone (/m/carte) : fond sombre, pions d'équipe, plaques de nom, route orange, zones
 * hachurées. Mêmes teintes que le style tactique de la carte ATAK en jeu (fn_mapColors : tacPlate, tacOrange, hatch).
 * Seul l'habillage change : données, droits et actions restent ceux de la carte (atak-m.js, atak-overwatch-mapkit.js).
 * Téléphone : icônes refaites seulement si leur empreinte change, plaques calculées sans lecture du DOM (largeur tirée
 * du nombre de caractères, police à chasse fixe), à la fin d'un déplacement et à l'arrivée des données, jamais pendant.
 */
window.MMTactique = (function () {
  'use strict';

  var STYLE_KEY = 'mm-map-style';
  var ORANGE = '#ff9e1f';
  var FRIEND = '#00d69a';
  var OFFLINE = '#8d9592';
  var RED = '#ff4d42';
  var PING = '#7fd3e0';
  var CHAR_W = 7.4;
  var PLATE_PAD = 12;
  var PLATE_H = 20;
  var PLATE_MAX = 16;
  // Priorité d'affichage des plaques : la plus basse passe d'abord.
  var PRIO = { me: 0, med: 1, unit: 2, objective: 3, marker: 4, area: 5 };

  function readStyle() {
    try { return localStorage.getItem(STYLE_KEY) === 'classique' ? 'classique' : 'tactique'; } catch (e) { return 'tactique'; }
  }
  function saveStyle(name) {
    try { localStorage.setItem(STYLE_KEY, name); } catch (e) {}
  }

  /** Couleur en #rrggbb, éclaircie si elle disparaîtrait sur le fond sombre (règle « lift » de fn_mapColors). */
  function liftColor(raw, fallback) {
    var m = /^#?([0-9a-f]{3}|[0-9a-f]{6})(?:[0-9a-f]{2})?$/i.exec(String(raw || '').trim());
    if (!m) return fallback || FRIEND;
    var hex = m[1].length === 3 ? m[1].replace(/./g, '$&$&') : m[1];
    var rgb = [0, 2, 4].map(function (i) { return parseInt(hex.substr(i, 2), 16); });
    var luma = (rgb[0] * 0.3 + rgb[1] * 0.59 + rgb[2] * 0.11) / 255;
    if (luma < 0.3) {
      var t = (0.55 - luma) / (1 - luma);
      rgb = rgb.map(function (c) { return Math.round(c + (255 - c) * t); });
    }
    return '#' + rgb.map(function (c) { return ('0' + c.toString(16)).slice(-2); }).join('');
  }

  /** Équipe réelle : équipe de feu du site, sinon groupe du jeu (préfixe de camp « B », « O », « I », « C » retiré). */
  function teamOf(u, extra) {
    var label = String(u.fire_team_label || u.group_name || extra.group_name || extra.group || '').trim().replace(/^[BOIC]\s+(?=\S)/, '');
    var letter = (label.match(/[0-9A-Za-zÀ-ÿ]/) || [''])[0].toUpperCase();
    return { label: label, letter: letter, color: liftColor(u.fire_team_color, FRIEND) };
  }

  /** Cap d'un joueur, dans le même ordre que l'Overwatch ; null s'il n'est pas connu (pas de triangle). */
  function headingOf(u, extra) {
    var arma = u.source_arma || extra.source_arma || {};
    var motion = u.motion || extra.motion || {};
    var list = [extra.movement_heading, u.movement_heading, arma.movement_heading_deg, extra.heading_object, u.heading_object, arma.heading_deg, extra.heading, u.heading, motion.heading];
    for (var i = 0; i < list.length; i += 1) {
      if (list[i] === null || list[i] === undefined || list[i] === '') continue;
      var n = Number(list[i]);
      if (Number.isFinite(n)) return ((n % 360) + 360) % 360;
    }
    return null;
  }

  /** « hurt » : santé critique (mêmes valeurs que MedicalAlertParser::isCriticalHealth) ; « medic » : rôle. */
  function medStateOf(u, extra) {
    var health = String(extra.health || u.health || '').trim().toLowerCase();
    if (/^(unconscious|cardiac[_-]arrest|critical|incapacitated|down|dead|kia|wounded|injured)$/.test(health)) return 'hurt';
    return /medic|m[ée]decin|corpsman|infirmi/i.test(String(u.role || extra.role || '')) ? 'medic' : '';
  }

  function create(kit, wrap, ctx) {
    var L = window.L;
    var Kit = window.OverwatchMapKit;
    var helper = window.ArmaMapMarkers;
    var map = kit.map;
    var esc = Kit.escapeHtml;
    var name = readStyle();
    var defs = null;
    var hatches = {};

    function isOn() { return name === 'tactique'; }

    /** Photo du terrain capturée par le mod (servie par Athena). Jamais de photo d'un site tiers ici. */
    function photoSpec() {
      var layers = kit.config && Array.isArray(kit.config.fondLayers) ? kit.config.fondLayers : [];
      return layers.filter(function (l) { return l && l.id === 'photo-comspec' && l.spec; })[0] || null;
    }
    function applyBase() {
      var photo = photoSpec();
      wrap.classList.toggle('mm-tac', isOn());
      wrap.classList.toggle('mm-tac--photo', isOn() && !!photo);
      if (!photo || !window.ATAKAerial) return;
      if (isOn()) {
        window.ATAKAerial.attach(map, { fondLayers: [{ id: photo.id, label: photo.label, kind: 'overlay', spec: photo.spec }], offsetX: kit.config.offsetX, offsetY: kit.config.offsetY });
      } else {
        window.ATAKAerial.detach(map);
      }
    }
    function setStyle(next) {
      name = next === 'classique' ? 'classique' : 'tactique';
      saveStyle(name);
      applyBase();
    }

    /** Remplissage hachuré d'une couleur : motif SVG partagé par toutes les zones de cette couleur. */
    function hatch(color) {
      var id = 'mmt-h-' + color.slice(1);
      if (hatches[id]) return 'url(#' + id + ')';
      var NS = 'http://www.w3.org/2000/svg';
      if (!defs) {
        var svg = document.createElementNS(NS, 'svg');
        svg.setAttribute('width', '0');
        svg.setAttribute('height', '0');
        svg.setAttribute('aria-hidden', 'true');
        svg.style.position = 'absolute';
        defs = document.createElementNS(NS, 'defs');
        svg.appendChild(defs);
        wrap.appendChild(svg);
      }
      var pattern = document.createElementNS(NS, 'pattern');
      pattern.setAttribute('id', id);
      pattern.setAttribute('patternUnits', 'userSpaceOnUse');
      pattern.setAttribute('width', '16');
      pattern.setAttribute('height', '16');
      pattern.setAttribute('patternTransform', 'rotate(-45)');
      [[16, 0.12], [6, 0.6]].forEach(function (bar) {
        var rect = document.createElementNS(NS, 'rect');
        rect.setAttribute('width', String(bar[0]));
        rect.setAttribute('height', '16');
        rect.setAttribute('fill', color);
        rect.setAttribute('fill-opacity', String(bar[1]));
        pattern.appendChild(rect);
      });
      defs.appendChild(pattern);
      hatches[id] = true;
      return 'url(#' + id + ')';
    }

    /** Icône : symbole, plaque (texte échappé, translate="no"), cible de 44 px. */
    function makeIcon(o) {
      var text = String(o.plate || '').trim();
      if (text.length > PLATE_MAX) text = text.slice(0, PLATE_MAX - 1) + '…';
      return L.divIcon({
        className: 'mmt' + (o.cls || ''),
        iconSize: [44, 44],
        iconAnchor: [22, 22],
        popupAnchor: [0, -18],
        html: '<div class="mmt__g">' + (o.glyph || '') + '</div>' +
          (text ? '<span class="mmt__p" translate="no" style="--gh:' + o.half + 'px">' + esc(text) + '</span>' : ''),
        mmSig: o.sig,
        mmPrio: o.prio,
        mmHalf: o.half,
        mmW: text ? text.length * CHAR_W + PLATE_PAD : 0,
        mmFixed: !!o.fixed
      });
    }
    function ringGlyph(color, inner) {
      return '<span class="mmt-ring" style="--c:' + color + '">' + inner + '</span>';
    }
    function medGlyph(color) {
      return ringGlyph(color, '<i class="mmt-dot"></i>');
    }

    function popup(ll, html) {
      L.popup({ autoPanPadding: [16, 80] }).setLatLng(ll).setContent(html).openOn(map);
    }
    /** Pendant la pose d'un marqueur, toucher un symbole place le marqueur au lieu d'ouvrir sa fiche. */
    function tapped(layer, open) {
      layer.on('click', function (e) {
        L.DomEvent.stopPropagation(e);
        if (ctx.placing && ctx.placing()) { map.fire('click', { latlng: e.latlng }); return; }
        open(e);
      });
    }

    // ── Joueurs ──────────────────────────────────────────────────────────────
    function unitIcon(u, st, view) {
      var cs = Kit.unitCallsign(u);
      var color = st.disc ? OFFLINE : view.team.color;
      if (view.med) {
        return makeIcon({ glyph: medGlyph(st.disc ? OFFLINE : RED), plate: cs, half: 17, prio: PRIO.med, sig: view.sig, cls: (st.me ? ' mmt--me' : '') + (st.disc ? ' mmt--off-link' : '') });
      }
      var tick = view.heading === null ? '' : '<i class="mmt-tick" style="transform:rotate(' + Math.round(view.heading) + 'deg)"></i>';
      return makeIcon({
        glyph: '<span class="mmt-pawn" style="--c:' + color + '"><b translate="no">' + esc(view.team.letter) + '</b>' + tick + '</span>',
        plate: cs,
        half: 16,
        prio: st.me ? PRIO.me : PRIO.unit,
        sig: view.sig,
        cls: (st.me ? ' mmt--me' : '') + (st.disc ? ' mmt--off-link' : '')
      });
    }
    function unitPopup(u, ll, st, view) {
      var world = kit.latLngToWorld(ll);
      var rows = [];
      if (view.team.label) rows.push('<span>Équipe</span> <b translate="no">' + esc(view.team.label) + '</b>');
      if (view.med === 'hurt') rows.push('<span>Blessé</span>');
      if (view.med === 'medic') rows.push('<span>Médecin</span>');
      if (view.heading !== null) rows.push('<span>Cap</span> <b translate="no">' + ('00' + Math.round(view.heading)).slice(-3) + '°</b>');
      rows.push('<span>Grille</span> <b class="mm-mono" translate="no">' + esc(ctx.grid(world.x, world.y)) + '</b>');
      if (st.disc) rows.push('<span>Dernière position connue</span>');
      popup(ll, '<strong translate="no">' + esc(Kit.unitCallsign(u)) + '</strong><br>' + rows.join('<br>'));
    }
    /** Pion d'un joueur : la position et le triangle bougent sans refaire l'icône. */
    function syncUnit(store, id, u, ll, st) {
      var extra = Kit.extraOf(u);
      var view = { team: teamOf(u, extra), heading: headingOf(u, extra), med: medStateOf(u, extra) };
      view.sig = [Kit.unitCallsign(u), view.team.letter, view.team.color, view.med, st.me, st.disc, view.heading === null].join('|');
      var layer = store[id];
      if (!layer) {
        layer = L.marker(ll, { icon: unitIcon(u, st, view), keyboard: false, zIndexOffset: st.me ? 700 : 300 }).addTo(map);
        store[id] = layer;
        tapped(layer, function () { unitPopup(layer._mmU[0], layer.getLatLng(), layer._mmU[1], layer._mmU[2]); });
      } else {
        if (!layer.getLatLng().equals(ll)) layer.setLatLng(ll);
        if (layer.options.icon.options.mmSig !== view.sig) {
          layer.setIcon(unitIcon(u, st, view));
        } else if (view.heading !== null && layer._icon) {
          var tick = layer._icon.querySelector('.mmt-tick');
          if (tick) tick.style.transform = 'rotate(' + Math.round(view.heading) + 'deg)';
        }
      }
      layer._mmU = [u, st, view];
    }

    // ── Marqueurs ────────────────────────────────────────────────────────────
    function plateOf(data) {
      var raw = helper.labelOf(data);
      return raw && !helper.isTechnicalLabel(raw) ? helper.displayLabelOf(data) : '';
    }
    function markerColor(data) {
      return liftColor(helper.resolveColor(data, helper.decodeType(data)), '#d0d6d6');
    }
    /** Fonction d'icône pour syncArmaMarkers : null si le marqueur affiché n'a pas changé. */
    function markerIcon(data, existing) {
      var dec = helper.decodeType(data);
      var color = markerColor(data);
      var dir = Number(helper.readDir(data)) || 0;
      var plate = plateOf(data);
      var sig = [data.type, color, dir, plate].join('|');
      if (existing && existing.options.icon.options.mmSig === sig) return null;
      var type = String(data.type || '').toLowerCase();
      var key = dec.typeKey;
      var o = { plate: plate, half: 17, prio: PRIO.marker, sig: sig };
      if (/(^|_)med(_|$)|hospital|medevac|casevac/.test(type) || dec.roleKey === 'medical' || /\b(m[ée]dic|medevac|casevac|bless[ée])/i.test(plate)) {
        o.glyph = medGlyph(RED);
      } else if (key === 'objective') {
        o.glyph = ringGlyph(color, '<i class="mmt-diamond"></i>');
        o.prio = PRIO.objective;
      } else if (key === 'triangle' || key === 'pickup' || key === 'flag') {
        o.glyph = ringGlyph(color, '<i class="mmt-tri" style="transform:rotate(' + dir + 'deg)"></i>');
      } else if (key === 'dot') {
        o.glyph = medGlyph(color);
      } else if (helper.isInvisibleArmaIcon(data)) {
        o.half = 0;
        o.fixed = true;
        o.cls = ' mmt--label';
      } else {
        // Autres symboles (ennemi, danger, OTAN, icônes du jeu) : le symbole réel dans le cercle fin, sans son libellé.
        var spec = helper.buildIconSpec(Object.assign({}, data, { text: '', label: '', name: '', color: color, colorHex: color }));
        o.glyph = ringGlyph(color, '<span class="mmt-sym">' + spec.html + '</span>');
      }
      return makeIcon(o);
    }

    // ── Routes et zones ──────────────────────────────────────────────────────
    function plateLayer(ll, text) {
      return L.marker(ll, { interactive: false, keyboard: false, icon: makeIcon({ plate: text, half: 0, prio: PRIO.area, fixed: true, cls: ' mmt--label' }) });
    }
    function arrowLayer(latlngs, color) {
      var a = latlngs[latlngs.length - 2];
      var b = latlngs[latlngs.length - 1];
      var deg = Math.round(Math.atan2(b.lng - a.lng, b.lat - a.lat) * 180 / Math.PI);
      return L.marker(b, {
        interactive: false,
        keyboard: false,
        icon: L.divIcon({ className: 'mmt-arrow', iconSize: [24, 24], iconAnchor: [12, 12], html: '<i style="--c:' + color + ';transform:rotate(' + deg + 'deg)"></i>' })
      });
    }
    /** Trait épais et halo ; flèche de sens pour une route. Le tracé reçoit ses points réels, rien n'est simplifié. */
    function lineParts(line, latlngs, color, isRoute, keepDash) {
      line.setStyle({ color: color, weight: 6, opacity: 1, lineCap: 'round', lineJoin: 'round', dashArray: keepDash ? line.options.dashArray : null });
      var parts = [L.polyline(latlngs, { color: color, weight: 16, opacity: 0.3, interactive: false, lineCap: 'round', lineJoin: 'round' }), line];
      if (isRoute && latlngs.length >= 2) parts.push(arrowLayer(latlngs, color));
      return parts;
    }
    function zoneStyle(zone, color, hollow) {
      var alpha = zone.options.opacity == null ? 1 : Number(zone.options.opacity);
      zone.setStyle({ color: color, weight: 3, opacity: alpha, dashArray: zone.options.dashArray, fillColor: hollow ? color : hatch(color), fillOpacity: hollow ? 0 : alpha });
    }
    /** Couche d'un marqueur tracé (POLYLINE, RECTANGLE, ELLIPSE) pour syncArmaMarkers. */
    function markerShape(data, latlng, row) {
      var base = helper.leafletShapeLayer(L, data, latlng);
      if (!base || helper.isBuildingLikeArea(data)) return base;
      base.unbindTooltip();
      var color = markerColor(data);
      var label = plateOf(data);
      var parts = [base];
      var at = latlng;
      if (String(helper.readShape(data)).toUpperCase() === 'POLYLINE') {
        var pts = base.getLatLngs();
        var route = !!(row && row.trace);
        parts = lineParts(base, pts, route ? ORANGE : color, route, false);
        at = pts[Math.floor(pts.length / 2)];
      } else {
        zoneStyle(base, color, /border|hollow/i.test(String(helper.readBrush(data))));
      }
      if (label) parts.push(plateLayer(at, label));
      return L.featureGroup(parts);
    }
    /** Tracé de /api/map-shapes déjà construit par kit.shapeLayer : même habillage. */
    function styleShape(shape, built) {
      var layer = built.layer;
      var color = liftColor(shape.color, FRIEND);
      var pts = built.latlngs || [];
      var parts = [layer];
      var isPoly = layer instanceof L.Polygon;
      if (layer instanceof L.Polyline && !isPoly && pts.length >= 2) {
        var route = built.type === 'ROUTE' || built.nato === 'route' || built.meta.kind === 'route';
        parts = lineParts(layer, pts, route ? ORANGE : color, route, !route);
      } else if (isPoly && built.nato !== 'attack') {
        zoneStyle(layer, color, built.meta.fill_style === 'none' || /^(assembly|objective|lz|dz|medical|cbrn)$/.test(built.nato));
      }
      var label = String(shape.label || '').trim();
      if (label) {
        var at = null;
        if (isPoly && pts.length) at = L.latLngBounds(pts).getCenter();
        else if (pts.length) at = pts[Math.floor(pts.length / 2)];
        else if (layer.getLatLng) at = layer.getLatLng();
        if (at) parts.push(plateLayer(at, label));
      }
      return L.featureGroup(parts);
    }

    // ── Pings (Quick Ping de l'Overwatch, /api/pings) ────────────────────────
    function syncPings(store, rows, enabled) {
      var seen = {};
      if (enabled) {
        (rows || []).forEach(function (p) {
          var id = String((p && p.id) || '');
          var x = Number(p && p.pos_x);
          var y = Number(p && p.pos_y);
          if (!id || !Number.isFinite(x) || !Number.isFinite(y) || (Math.abs(x) < 0.5 && Math.abs(y) < 0.5)) return;
          seen[id] = true;
          if (store[id]) return;
          var ll = kit.worldToLatLng(x, y);
          var layer = L.marker(ll, { keyboard: false, zIndexOffset: 400, icon: makeIcon({ glyph: medGlyph(PING), plate: p.message || p.author, half: 17, prio: PRIO.marker, sig: id }) }).addTo(map);
          store[id] = layer;
          tapped(layer, function () {
            popup(ll, '<strong translate="no">' + esc(p.message || '') + '</strong><br><small translate="no">' + esc(p.author || '') + '</small><br><span>Grille</span> <b class="mm-mono" translate="no">' + esc(ctx.grid(x, y)) + '</b>');
          });
        });
      }
      Object.keys(store).forEach(function (id) {
        if (!seen[id]) { map.removeLayer(store[id]); delete store[id]; }
      });
    }

    // ── Plaques sans chevauchement ───────────────────────────────────────────
    function hits(r, list) {
      for (var i = 0; i < list.length; i += 1) {
        var o = list[i];
        if (r[0] < o[2] && r[2] > o[0] && r[1] < o[3] && r[3] > o[1]) return true;
      }
      return false;
    }
    /**
     * Place chaque plaque (dessous, dessus, droite, gauche, dans l'écran) ou la masque : le nom se lit en touchant le symbole.
     * Appelée par atak-m.js après le regroupement des piles (mm-grp-off : plaque masquée) ; symboles à l'échelle du zoom.
     * taken : cadres (points du calque) des noms déjà affichés par atak-m.js (appareils), que les plaques évitent.
     */
    function declutter(taken) {
      var k = ctx.scale ? ctx.scale() : 1;
      var far = wrap.classList.contains('mm-z-far');
      var items = [];
      var tl = map.containerPointToLayerPoint([0, 0]);
      var size = map.getSize();
      var inView = function (r) { return r[0] >= tl.x && r[1] >= tl.y && r[2] <= tl.x + size.x && r[3] <= tl.y + size.y; };
      map.eachLayer(function (layer) {
        if (!(layer instanceof L.Marker) || !layer._icon) return;
        var o = layer.options.icon.options;
        if (o.mmPrio === undefined) return;
        items.push({ el: layer._icon, o: o, p: map.latLngToLayerPoint(layer.getLatLng()) });
      });
      items.sort(function (a, b) { return a.o.mmPrio - b.o.mmPrio; });
      var used = items.filter(function (it) { return it.o.mmHalf > 0; }).map(function (it) {
        var h = it.o.mmHalf * k * 0.75;
        return [it.p.x - h, it.p.y - h, it.p.x + h, it.p.y + h];
      }).concat(taken || []);
      items.forEach(function (it) {
        var w = it.o.mmW;
        var place = 'off';
        if (w && (it.o.mmPrio === PRIO.me || (!far && !it.el.classList.contains('mm-grp-off')))) {
          var x = it.p.x;
          var y = it.p.y;
          var g = it.o.mmHalf * k + 2;
          var anchorSeen = inView([x, y, x, y]) && !it.o.mmFixed;
          var spots = it.o.mmFixed
            ? [['c', x - w / 2, y - PLATE_H / 2]]
            : [['b', x - w / 2, y + g], ['t', x - w / 2, y - g - PLATE_H], ['r', x + g + 2, y - PLATE_H / 2], ['l', x - g - 2 - w, y - PLATE_H / 2]];
          for (var i = 0; i < spots.length; i += 1) {
            var r = [spots[i][1], spots[i][2], spots[i][1] + w, spots[i][2] + PLATE_H];
            if (!hits(r, used) && (!anchorSeen || inView(r))) { used.push(r); place = spots[i][0]; break; }
          }
          // Ma plaque reste affichée (les joueurs passent après elle : elle ne peut recouvrir qu'un symbole, jamais une plaque).
          if (place === 'off' && it.o.mmPrio === PRIO.me) {
            used.push([x - w / 2, y + g, x + w / 2, y + g + PLATE_H]);
            place = 'b';
          }
        }
        it.el.classList.toggle('mmt--t', place === 't');
        it.el.classList.toggle('mmt--r', place === 'r');
        it.el.classList.toggle('mmt--l', place === 'l');
        it.el.classList.toggle('mmt--hide', place === 'off');
      });
    }
    applyBase();

    return {
      name: function () { return name; },
      isOn: isOn,
      setStyle: setStyle,
      syncUnit: syncUnit,
      markerIcon: markerIcon,
      markerShape: markerShape,
      styleShape: styleShape,
      syncPings: syncPings,
      declutter: declutter
    };
  }

  return { create: create, liftColor: liftColor, teamOf: teamOf, headingOf: headingOf, medStateOf: medStateOf };
})();
