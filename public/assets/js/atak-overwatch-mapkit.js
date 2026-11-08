/*
 * Carte Overwatch partagée : fond de carte du terrain (repère Arma, décalage, tuiles relayées et gardées en cache),
 * tracés (zones, axes, itinéraires), marqueurs du théâtre (ArmaMapMarkers), appareils (symboles APP-6), géométrie des
 * 9-LINE / 5-LINE (cible, IP/BP, cap) et contacts BFT.
 * Une seule source pour l'Overwatch (atak-overwatch-beta.js) et la carte du téléphone (/m/carte, /m/briefs : atak-m.js) :
 * mêmes repères, mêmes symboles, mêmes couleurs. Chargé après Leaflet et atak-map-crs.js.
 */
(function () {
  'use strict';
  if (!window.L) return;

  var DEFAULT_TILE_CACHE = 'athena-overwatch-tiles-v2';

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /** Tuiles d'un autre domaine : relayées par Athena (/api/atak/tiles), comme sur l'Overwatch. */
  function proxiedTilePattern(pattern, apiBase) {
    if (window.OverwatchTheaterProjection && window.OverwatchTheaterProjection.proxiedTilePattern) {
      return window.OverwatchTheaterProjection.proxiedTilePattern(pattern);
    }
    var raw = String(pattern || '');
    if (!/^https?:\/\//i.test(raw)) return raw;
    try {
      if (new URL(raw, window.location.href).hostname === window.location.hostname) return raw;
    } catch (e0) {}
    var api = String(apiBase != null ? apiBase : (window.ATAK_API_BASE || '')).replace(/\/$/, '');
    var encoded = encodeURIComponent(raw)
      .replace(/%7Bz%7D/gi, '{z}')
      .replace(/%7Bx%7D/gi, '{x}')
      .replace(/%7By%7D/gi, '{y}');
    return api + '/api/atak/tiles?u=' + encoded;
  }

  function parseMarkerData(row) {
    var raw = row && (row.markerData || row.marker_data);
    if (typeof raw === 'string') {
      try { raw = JSON.parse(raw); } catch (e) { raw = {}; }
    }
    return raw && typeof raw === 'object' ? raw : {};
  }
  function markerWorld(data) {
    var pos = data.pos;
    if (typeof pos === 'string') {
      var nums = pos.match(/-?\d+(?:\.\d+)?(?:e[-+]?\d+)?/gi);
      pos = nums && nums.length >= 2 ? [nums[0], nums[1]] : null;
    }
    if (Array.isArray(pos) && pos.length >= 2) {
      var px = Number(pos[0]);
      var py = Number(pos[1]);
      if (Number.isFinite(px) && Number.isFinite(py) && (Math.abs(px) > 0.5 || Math.abs(py) > 0.5)) return { x: px, y: py };
    }
    var x = Number(data.pos_x != null ? data.pos_x : data.x);
    var y = Number(data.pos_y != null ? data.pos_y : data.y);
    return Number.isFinite(x) && Number.isFinite(y) && (Math.abs(x) > 0.5 || Math.abs(y) > 0.5) ? { x: x, y: y } : null;
  }
  function isPoLabel(label) {
    var text = String(label || '').trim();
    if (!text) return false;
    return /^PO(?:[\s\-_.#]*\d+|[\s\-_.]+[A-Z0-9]{1,16})?$/i.test(text);
  }
  function parseShapeMeta(shape) {
    var meta = shape && shape.meta;
    if (typeof meta === 'string') {
      try { meta = JSON.parse(meta); } catch (eMeta) { meta = {}; }
    }
    return meta && typeof meta === 'object' ? meta : {};
  }
  function natoDash(meta) {
    if (meta.dash) return String(meta.dash);
    var nato = String(meta.nato || '');
    if (nato === 'phase' || nato === 'nofire') return '8 6';
    if (nato === 'sector') return '10 4 2 4';
    if (nato === 'highlight') return '6 4';
    if (nato === 'support_fire' || nato === 'dz') return '6 4';
    if (nato === 'feba') return null;
    if (nato === 'flot') return '2 6';
    if (nato === 'obstacle') return '4 3';
    return null;
  }
  function geometryOf(shape) {
    var geo = shape && shape.geometry;
    if (typeof geo === 'string') {
      try { geo = JSON.parse(geo); } catch (e) { return null; }
    }
    return geo || null;
  }
  /** Camp d'un contact : hostile, unknown ou friendly (même règle que l'Overwatch). */
  function side(unit) {
    var raw = String((unit && (unit.side || unit.faction || unit.iff || unit.type)) || '').toLowerCase();
    if (/hostile|opfor|east|enemy/.test(raw)) return 'hostile';
    if (/unknown|civ|neutral/.test(raw)) return 'unknown';
    return 'friendly';
  }
  // ── État des contacts BFT (âge de la position, liaison) : mêmes seuils que l'Overwatch ──
  var STALE_HIDE_SEC = 15 * 60;
  var STALE_DEAD_SEC = 2 * 60 * 60;
  function unitCallsign(unit) {
    var text = String((unit && (unit.call_sign || unit.callsign || unit.name || unit.label)) || '').trim();
    return text || 'Contact';
  }
  function extraOf(unit) {
    var extra = unit.extra || unit.meta || {};
    if (typeof extra === 'string') {
      try { extra = JSON.parse(extra); } catch (e) { extra = {}; }
    }
    return extra && typeof extra === 'object' ? extra : {};
  }
  function unitAgeSec(unit) {
    if (!unit) return NaN;
    var apiAge = Number(unit._ageAtReceive);
    if (!Number.isFinite(apiAge) || apiAge < 0) apiAge = Number(unit.age_seconds);
    if (Number.isFinite(apiAge) && apiAge >= 0) {
      var received = Number(unit._receivedAt);
      if (Number.isFinite(received) && received > 0) {
        return Math.max(0, apiAge + (Date.now() - received) / 1000);
      }
      return apiAge;
    }
    var extra = extraOf(unit || {});
    var stamped = extra.last_seen_at || unit.updated_at || unit.last_seen_at || unit.last_seen;
    if (!stamped) return NaN;
    var raw = String(stamped).trim();
    var iso = raw.indexOf('T') >= 0 ? raw : raw.replace(' ', 'T');
    if (!/[zZ]|[+-]\d{2}:?\d{2}$/.test(iso)) iso += 'Z';
    var t = Date.parse(iso);
    if (!Number.isFinite(t)) return NaN;
    return Math.max(0, (Date.now() - t) / 1000);
  }
  function isTrackedAi(unit) {
    var extra = extraOf(unit);
    if (extra.ally_ai || extra.enemy_ai || extra.is_ai) return true;
    var src = String(extra.source || '').toLowerCase();
    if (src === 'ally' || src === 'enemy') return true;
    var cs = unitCallsign(unit).toUpperCase();
    return cs.indexOf('ALLY-') === 0 || cs.indexOf('ENY-') === 0;
  }
  function isDisconnected(unit) {
    var st = String(unit.status || '').toLowerCase();
    if (st === 'offline' || st === 'disconnected') return true;
    var extra = extraOf(unit);
    var link = String(unit.link_state || extra.link_state || extra.linkState || '').toLowerCase();
    return link === 'offline' || link === 'disconnected' || link === 'lost';
  }
  function tooOldToShow(unit) {
    var age = unitAgeSec(unit);
    if (Number.isFinite(age) && age > STALE_DEAD_SEC) return true;
    if (isTrackedAi(unit) || !isDisconnected(unit)) return false;
    return Number.isFinite(age) && age > STALE_HIDE_SEC;
  }
  function jtacGeoPoint(p) {
    if (!Array.isArray(p) || p.length < 2) return null;
    var x = Number(p[0]);
    var y = Number(p[1]);
    if (!Number.isFinite(x) || !Number.isFinite(y) || (Math.abs(x) < 0.5 && Math.abs(y) < 0.5)) return null;
    return { x: x, y: y };
  }
  function jtacGeoOffset(pt, dist, bearing) {
    var r = bearing * Math.PI / 180;
    return { x: pt.x + Math.sin(r) * dist, y: pt.y + Math.cos(r) * dist };
  }
  /** Objet « geo » d'un appui : déjà décodé (/api/cas) ou brut (geo_json, /api/nine-line : 5-LINE compris). */
  function rowGeo(row) {
    var g = row && row.geo;
    if ((!g || typeof g !== 'object') && row && typeof row.geo_json === 'string' && row.geo_json !== '') {
      try { g = JSON.parse(row.geo_json); } catch (e) { g = null; }
    }
    return g && typeof g === 'object' ? g : null;
  }
  /** Empreinte des appuis affichés : la couche n'est refaite que si elle change (une fiche ouverte reste ouverte). */
  function jtacGeoSignature(rows) {
    return JSON.stringify((rows || []).map(function (row) {
      var g = rowGeo(row);
      return g && jtacGeoActive(row) ? [row.id, row.status, row.updated_at, g] : null;
    }).filter(Boolean));
  }
  function jtacGeoActive(row) {
    var st = String(row.status || '').toUpperCase();
    if (st === 'COMPLETE' || st === 'ABORTED' || st === 'CANCELLED') return false;
    var t = Date.parse(String(row.updated_at || row.created_at || '').replace(' ', 'T'));
    return !Number.isFinite(t) || (Date.now() - t) < 6 * 3600 * 1000;
  }

  /**
   * Carte d'un terrain dans l'élément `el` (id ou nœud), configuration ATAK_MAP_CONFIG de l'Overwatch.
   * opts.mobile : téléphone (zoom par pas de 0,25, carte toujours pleine largeur, pas de bandes noires).
   */
  function create(el, config, opts) {
    config = config || {};
    opts = opts || {};
    var L = window.L;
    var crsOpt = config.crs || {};
    var factorx = crsOpt.factorx != null ? Number(crsOpt.factorx) : 0.006839;
    var factory = crsOpt.factory != null ? Number(crsOpt.factory) : 0.006836;
    var tileWidth = crsOpt.tileWidth != null ? Number(crsOpt.tileWidth) : 212;
    var CRS = typeof window.MGRS_CRS === 'function' ? window.MGRS_CRS(factorx, factory, tileWidth) : L.CRS.Simple;
    var worldSize = Number(config.worldSize || 30720);
    var maxNative = Number(config.maxZoom != null ? config.maxZoom : 6);
    var maxZoom = maxNative + 2;
    var mobile = !!opts.mobile;
    var offX = Number(config.offsetX || 0);
    var offY = Number(config.offsetY || 0);

    var mapOpts = {
      crs: CRS,
      zoomControl: false,
      attributionControl: opts.attribution !== false,
      minZoom: Number(config.minZoom || 0),
      maxZoom: maxZoom
    };
    if (mobile) {
      mapOpts.zoomSnap = 0.25;
      mapOpts.zoomDelta = 0.5;
      mapOpts.wheelPxPerZoomLevel = 90;
      mapOpts.bounceAtZoomLimits = false;
      mapOpts.tapTolerance = 20;
      mapOpts.maxBoundsViscosity = 1;
    }
    var map = L.map(el, mapOpts);

    function worldToLatLng(x, y) {
      return L.latLng(Number(y) + offY, Number(x) + offX);
    }
    function latLngToWorld(ll) {
      return { x: Number(ll.lng) - offX, y: Number(ll.lat) - offY };
    }

    var bounds = L.latLngBounds(worldToLatLng(0, 0), worldToLatLng(worldSize, worldSize));
    var baseTileLayer = null;
    var tileCache = opts.tileCache || (window.OverwatchTileCache && window.OverwatchTileCache.NAME) || DEFAULT_TILE_CACHE;
    if (config.tilePattern) {
      baseTileLayer = L.tileLayer(proxiedTilePattern(config.tilePattern, opts.apiBase), {
        tileSize: Number(config.tileSize || 212),
        minZoom: Number(config.minZoom || 0),
        maxZoom: maxZoom,
        maxNativeZoom: maxNative,
        noWrap: true,
        crossOrigin: true,
        bounds: bounds,
        attribution: config.attribution || '&copy; Bohemia Interactive'
      });
      baseTileLayer.on('tileload', function (ev) {
        var src = ev && ev.tile && ev.tile.src;
        if (!src || !window.caches) return;
        if (window.OverwatchTileCache && window.OverwatchTileCache.fetchBlob) {
          window.OverwatchTileCache.fetchBlob(src).catch(function () {});
          return;
        }
        caches.open(tileCache).then(function (cache) {
          return fetch(src, { mode: 'cors', credentials: 'omit' }).then(function (res) {
            if (res && res.ok) cache.put(src, res);
          });
        }).catch(function () {});
      });
      baseTileLayer.on('tileerror', function (ev) {
        var img = ev && ev.tile;
        if (!img || !window.caches) return;
        var restore = function (blob) {
          if (blob) img.src = URL.createObjectURL(blob);
        };
        if (window.OverwatchTileCache && window.OverwatchTileCache.fetchBlob) {
          window.OverwatchTileCache.fetchBlob(img.src).then(restore).catch(function () {});
          return;
        }
        caches.open(tileCache).then(function (cache) {
          return cache.match(img.src).then(function (hit) {
            if (hit) return hit.blob().then(restore);
          });
        }).catch(function () {});
      });
      baseTileLayer.addTo(map);
    }

    // Centre du terrain (repère Leaflet de l'Overwatch : [lat, lng]).
    var center = Array.isArray(config.center) ? config.center : [15000, 15000];
    map.setView(center, Number(config.defaultZoom || 3));
    var kitFitMin = function () {};
    if (mobile) {
      // Téléphone : la carte remplit toujours l'écran (zoom minimal = terrain qui couvre la vue), sans sortir du terrain.
      map.setMaxBounds(bounds);
      var fitMin = function () {
        var z = map.getBoundsZoom(bounds, true);
        if (!Number.isFinite(z)) return;
        z = Math.max(Number(config.minZoom || 0), Math.ceil(z * 4) / 4);
        map.setMinZoom(Math.min(z, maxZoom));
        if (map.getZoom() < z) map.setZoom(z, { animate: false });
      };
      fitMin();
      map.on('resize', fitMin);
      kitFitMin = fitMin;
    } else {
      map.setMaxBounds(bounds.pad(0.08));
    }

    function circleLatLngs(centerLl, edge, steps) {
      var radius = map.distance(centerLl, edge);
      var cw = latLngToWorld(centerLl);
      var out = [];
      var n = steps || 32;
      for (var i = 0; i < n; i += 1) {
        var ang = (i / n) * Math.PI * 2;
        out.push(worldToLatLng(cw.x + Math.sin(ang) * radius, cw.y + Math.cos(ang) * radius));
      }
      return out;
    }
    function offsetPerp(aw, bw, dist) {
      var dx = bw.x - aw.x;
      var dy = bw.y - aw.y;
      var len = Math.sqrt(dx * dx + dy * dy) || 1;
      return { x: (-dy / len) * dist, y: (dx / len) * dist };
    }
    function arrowHeadPolygon(latlngs, color, wide) {
      if (!latlngs || latlngs.length < 2) return null;
      var a = latLngToWorld(latlngs[latlngs.length - 2]);
      var b = latLngToWorld(latlngs[latlngs.length - 1]);
      var dx = b.x - a.x;
      var dy = b.y - a.y;
      var len = Math.sqrt(dx * dx + dy * dy) || 1;
      var ux = dx / len;
      var uy = dy / len;
      var size = wide ? 36 : 18;
      var spread = wide ? 16 : 8;
      var tip = worldToLatLng(b.x, b.y);
      var left = worldToLatLng(b.x - ux * size + (-uy) * spread, b.y - uy * size + ux * spread);
      var right = worldToLatLng(b.x - ux * size - (-uy) * spread, b.y - uy * size - ux * spread);
      return L.polygon([tip, left, right], { color: color, fillColor: color, fillOpacity: 1, weight: 1, interactive: false });
    }
    function filledAttackLatLngs(latlngs, halfWidth) {
      if (!latlngs || latlngs.length < 2) return [];
      var left = [];
      var right = [];
      for (var i = 0; i < latlngs.length; i += 1) {
        var a = latLngToWorld(latlngs[Math.max(0, i - 1)]);
        var b = latLngToWorld(latlngs[Math.min(latlngs.length - 1, i + 1)]);
        if (i === 0) { a = latLngToWorld(latlngs[0]); b = latLngToWorld(latlngs[1]); }
        if (i === latlngs.length - 1) { a = latLngToWorld(latlngs[i - 1]); b = latLngToWorld(latlngs[i]); }
        var off = offsetPerp(a, b, halfWidth);
        var p = latLngToWorld(latlngs[i]);
        left.push(worldToLatLng(p.x + off.x, p.y + off.y));
        right.push(worldToLatLng(p.x - off.x, p.y - off.y));
      }
      var last = latLngToWorld(latlngs[latlngs.length - 1]);
      var prev = latLngToWorld(latlngs[latlngs.length - 2]);
      var dx = last.x - prev.x;
      var dy = last.y - prev.y;
      var len = Math.sqrt(dx * dx + dy * dy) || 1;
      var ux = dx / len;
      var uy = dy / len;
      var tip = worldToLatLng(last.x, last.y);
      var headW = halfWidth * 2.4;
      var leftH = worldToLatLng(last.x - ux * halfWidth * 2.2 + (-uy) * headW, last.y - uy * halfWidth * 2.2 + ux * headW);
      var rightH = worldToLatLng(last.x - ux * halfWidth * 2.2 - (-uy) * headW, last.y - uy * halfWidth * 2.2 - ux * headW);
      return left.concat([leftH, tip, rightH], right.slice().reverse());
    }
    function sectorTickLayers(latlngs, color) {
      var out = [];
      if (!latlngs || latlngs.length < 2) return out;
      for (var i = 1; i < latlngs.length; i += 1) {
        var a = latLngToWorld(latlngs[i - 1]);
        var b = latLngToWorld(latlngs[i]);
        var dx = b.x - a.x;
        var dy = b.y - a.y;
        var len = Math.sqrt(dx * dx + dy * dy) || 1;
        var steps = Math.max(1, Math.floor(len / 90));
        for (var s = 0; s <= steps; s += 1) {
          var t = s / steps;
          var px = a.x + dx * t;
          var py = a.y + dy * t;
          var off = offsetPerp(a, b, 14);
          out.push(L.polyline([
            worldToLatLng(px - off.x, py - off.y),
            worldToLatLng(px + off.x, py + off.y)
          ], { color: color, weight: 2, interactive: false }));
        }
      }
      return out;
    }

    /**
     * Couche d'un tracé de l'Overwatch (/api/map-shapes) : point, trait, axe, zone, symboles OTAN.
     * Renvoie { layer, latlngs, nato, type, meta, labelNato } ou null ; la couche n'est pas encore ajoutée à la carte.
     */
    function shapeLayer(shape) {
      var geo = geometryOf(shape);
      if (!geo) return null;
      var color = shape.color || '#00d69a';
      var layer = null;
      var type = String(shape.type || '').toUpperCase();
      var geoType = String(geo.type || '').toLowerCase();
      if (!type) {
        if (geoType === 'point') type = 'POINT';
        else if (geoType === 'linestring') type = 'LINE';
        else if (geoType === 'polygon') type = 'POLYGON';
      }
      var meta = parseShapeMeta(shape);
      var nato = String(meta.nato || meta.kind || '');
      var dash = natoDash(meta);
      var latlngs = [];
      var isLine = type === 'LINE' || type === 'ROUTE' || type === 'POLYLINE' || geoType === 'linestring';
      var isPoly = type === 'POLYGON' || type === 'AOI' || geoType === 'polygon';
      if (type === 'POINT' && geo.coordinates) {
        var pll = worldToLatLng(geo.coordinates[0], geo.coordinates[1]);
        if (meta.kind === 'buildingPlan') {
          var pin = document.createElement('div');
          pin.className = 'ow-bplan-pin';
          pin.innerHTML = '<small>' + escapeHtml(shape.label || 'Plan') + '</small>Plan rattaché';
          layer = L.marker(pll, { icon: L.divIcon({ className: '', html: pin.outerHTML, iconSize: [140, 36], iconAnchor: [70, 36] }) });
        } else if (meta.image || nato === 'stamp') {
          var iw = Math.max(24, Math.min(160, Number(meta.width) || 72));
          var ih = Math.max(24, Math.min(160, Number(meta.height) || 72));
          var stamp = document.createElement('img');
          stamp.className = 'ow-map-stamp';
          stamp.src = String(meta.image || '');
          stamp.alt = shape.label || 'Image';
          stamp.width = iw;
          stamp.height = ih;
          layer = L.marker(pll, {
            icon: L.divIcon({
              className: '',
              html: stamp.outerHTML,
              iconSize: [iw, ih],
              iconAnchor: [Math.round(iw / 2), Math.round(ih / 2)]
            })
          });
        } else if (nato === 'checkpoint') {
          var cp = document.createElement('div');
          cp.className = 'ow-map-stamp';
          cp.style.cssText = 'width:34px;height:34px;display:grid;place-items:center;border:2px solid ' + color + ';transform:rotate(45deg);background:rgba(0,0,0,.35);color:' + color + ';font:700 9px/1 sans-serif';
          cp.innerHTML = '<span style="transform:rotate(-45deg)">CP</span>';
          layer = L.marker(pll, { icon: L.divIcon({ className: '', html: cp.outerHTML, iconSize: [34, 34], iconAnchor: [17, 17] }) });
        } else {
          layer = L.circleMarker(pll, { radius: 8, color: color, weight: 2, fillOpacity: 0.5 });
        }
      } else if (isLine && Array.isArray(geo.coordinates)) {
        latlngs = geo.coordinates.map(function (p) {
          if (!p) return worldToLatLng(0, 0);
          if (Array.isArray(p)) return worldToLatLng(p[0], p[1]);
          return worldToLatLng(p.x != null ? p.x : p.lng, p.y != null ? p.y : p.lat);
        });
        if (nato === 'attack') {
          layer = L.polygon(filledAttackLatLngs(latlngs, 16), {
            color: color, fillColor: color, fillOpacity: 0.92, weight: 1
          });
        } else if (nato === 'breach') {
          var breachGroup = L.featureGroup();
          breachGroup.addLayer(L.polyline(latlngs, { color: color, weight: 3 }));
          if (latlngs.length >= 2) {
            var mid = latlngs[Math.floor(latlngs.length / 2)];
            breachGroup.addLayer(L.circleMarker(mid, { radius: 5, color: color, fillColor: color, fillOpacity: 1, weight: 1 }));
          }
          layer = breachGroup;
        } else {
          layer = L.polyline(latlngs, {
            color: color,
            weight: Number(shape.stroke || (nato === 'arrow' || nato === 'axis' || nato === 'feba' ? 3 : 2)),
            dashArray: dash || undefined
          });
        }
      } else if (isPoly && Array.isArray(geo.coordinates)) {
        var ring = geo.coordinates[0] && Array.isArray(geo.coordinates[0][0]) ? geo.coordinates[0] : geo.coordinates;
        latlngs = ring.map(function (p) { return worldToLatLng(p[0], p[1]); });
        var fillStyle = String(meta.fill_style || (meta.hatch ? 'hatch-d' : 'solid'));
        var hatchClass = fillStyle === 'hatch-h' ? 'ow-hatch-h' : (fillStyle === 'hatch-d' || meta.hatch || nato === 'minefield' ? 'ow-hatch-diag' : '');
        var fillOp = fillStyle === 'none' ? 0 : Number(shape.fillOpacity || shape.fill_opacity || meta.fill_opacity || 0.15);
        if (nato === 'assembly' || nato === 'objective' || nato === 'lz' || nato === 'dz' || nato === 'medical' || nato === 'cbrn') fillOp = 0;
        if (nato === 'minefield' || nato === 'nofire') fillOp = Number(meta.fill_opacity || 0.12);
        if (nato === 'highlight') fillOp = Number(meta.fill_opacity || 0.18);
        layer = L.polygon(latlngs, {
          color: color,
          fillColor: meta.fill_color || color,
          fillOpacity: fillOp,
          className: hatchClass,
          dashArray: dash || undefined,
          weight: nato === 'highlight' ? 1.5 : 2
        });
        if (meta.interior && layer.bindTooltip) {
          layer.bindTooltip(String(meta.interior), { permanent: true, direction: 'center', className: 'ow-geo-label' });
        }
      }
      if (!layer) return null;
      if (meta.interior && type !== 'POLYGON' && type !== 'AOI' && layer.bindTooltip && !layer.getTooltip()) {
        layer.bindTooltip(String(meta.interior), { permanent: true, direction: 'center', className: 'ow-geo-label' });
      }
      if ((nato === 'arrow' || nato === 'axis' || nato === 'support_fire' || nato === 'withdraw' || nato === 'delay' || meta.arrow) && type !== 'POINT' && nato !== 'attack' && nato !== 'breach' && latlngs.length >= 2) {
        var group = L.featureGroup([layer]);
        var head = arrowHeadPolygon(latlngs, color, false);
        if (head) group.addLayer(head);
        layer = group;
      }
      if (nato === 'sector' && latlngs.length >= 2) {
        var ticks = L.featureGroup([layer]);
        sectorTickLayers(latlngs, color).forEach(function (tick) { ticks.addLayer(tick); });
        layer = ticks;
      }
      var labelNato = /^(phase|assembly|objective|highlight|sector|axis|attack|support_fire|breach|withdraw|delay|feba|flot|obstacle|lz|dz|minefield|nofire|medical|cbrn|checkpoint|stamp)$/.test(nato);
      return { layer: layer, latlngs: latlngs, nato: nato, type: type, meta: meta, labelNato: labelNato };
    }
    /** Libellé d'un tracé (après ajout à la carte), comme sur l'Overwatch. */
    function labelShape(shape, built) {
      var layer = built.layer;
      if ((built.type === 'POINT' || built.labelNato) && shape.label && layer.bindTooltip && !layer.getTooltip()) {
        layer.bindTooltip(String(shape.label), {
          permanent: built.labelNato,
          direction: built.labelNato ? 'center' : 'top',
          sticky: !built.labelNato,
          className: built.labelNato ? 'ow-geo-label ow-nato-label' : ''
        });
      }
    }

    function removeFrom(store, id) {
      if (!store[id]) return;
      try { map.removeLayer(store[id]); } catch (e) {}
      delete store[id];
    }
    function clearStore(store) {
      Object.keys(store).forEach(function (id) { removeFrom(store, id); });
    }

    /**
     * Marqueurs du théâtre (/api/atak/markers) : icônes, couleurs et libellés du jeu (ArmaMapMarkers).
     * store : { id: couche } gardé par l'appelant. hooks.enabled(), hooks.skip(data, latlng, buildingLike),
     * hooks.bind(layer, id, title, row, isNew). hooks.icon(data, existing) : icône de remplacement, null si inchangée ;
     * hooks.shape(data, latlng, row) : couche de remplacement d'un tracé (style tactique du téléphone).
     */
    function syncArmaMarkers(store, rows, hooks) {
      hooks = hooks || {};
      var helper = window.ArmaMapMarkers;
      if (!helper) return;
      var seen = {};
      if (hooks.enabled && !hooks.enabled()) { clearStore(store); return; }
      (rows || []).forEach(function (row) {
        var id = String((row && row.id) || '');
        if (!id) return;
        var data = parseMarkerData(row);
        if (data.suppressed) return;
        if (isPoLabel(String(data.text || data.label || '')) || data.po) return;
        var world = markerWorld(data) || helper.parsePos(data);
        if (!world) return;
        var latlng = worldToLatLng(world.x, world.y);
        var buildingLike = !!(helper.isBuildingLikeArea && helper.isBuildingLikeArea(data));
        if (hooks.skip && hooks.skip(data, latlng, buildingLike)) { removeFrom(store, id); return; }
        seen[id] = true;
        // Forme (zone, trait, dessin libre du téléphone) : si elle change, la couche est refaite. Le téléphone pose
        // parfois d'abord un point puis la polyligne sous le même nom : sans cela le point restait affiché.
        var area = !!(helper.isAreaShape && helper.isAreaShape(data));
        var shapeSig = area
          ? JSON.stringify([data.shape, data.polyline || data.points || null, data.size || null, data.dir || 0, data.color || '', data.brush || '', data.alpha])
          : 'icon';
        if (store[id] && store[id]._owShapeSig !== shapeSig) removeFrom(store, id);
        var title = helper.displayLabelOf ? helper.displayLabelOf(data) : (data.label || data.text || 'Repère');
        if (buildingLike) title = '';
        var desc = String(data.description || '').trim();
        var tip = helper.markerTooltipOf ? helper.markerTooltipOf(data) : (title ? (desc ? (String(title) + ' — ' + desc) : String(title)) : '');
        if (desc && tip && tip.indexOf(desc) < 0) tip = tip + ' — ' + desc;
        var existing = store[id];
        if (existing) {
          if (existing.setLatLng) existing.setLatLng(latlng);
          if (!buildingLike && helper.leafletDivIcon && existing.setIcon && !area) {
            var nextIcon = hooks.icon ? hooks.icon(data, existing) : helper.leafletDivIcon(L, data);
            if (nextIcon) existing.setIcon(nextIcon);
          }
          if (buildingLike && existing.setStyle && helper.buildingFootprintStyle) {
            existing.setStyle(helper.buildingFootprintStyle());
          }
          if (tip && existing.setTooltipContent) existing.setTooltipContent(tip);
          else if (!tip && existing.unbindTooltip) existing.unbindTooltip();
          if (hooks.bind) hooks.bind(existing, id, title, row, false);
          return;
        }
        var layer = null;
        if (area && helper.leafletShapeLayer) {
          layer = hooks.shape ? hooks.shape(data, latlng, row) : helper.leafletShapeLayer(L, data, latlng);
        } else if (helper.leafletDivIcon) {
          layer = L.marker(latlng, { icon: hooks.icon ? hooks.icon(data, null) : helper.leafletDivIcon(L, data), keyboard: false });
        }
        if (!layer) return;
        if (tip && layer.bindTooltip) layer.bindTooltip(tip, { permanent: false, direction: 'top' });
        else if (layer.unbindTooltip) layer.unbindTooltip();
        layer._owShapeSig = shapeSig;
        layer.addTo(map);
        store[id] = layer;
        if (hooks.bind) hooks.bind(layer, id, title, row, true);
      });
      Object.keys(store).forEach(function (id) {
        if (!seen[id]) removeFrom(store, id);
      });
    }

    /** Symbole APP-6 d'un appareil remonté par le jeu (/api/atak/air-assets). */
    function airAssetIcon(a) {
      var nato = window.NatoSidcIcons;
      var airSide = String(a.side || 'WEST').toUpperCase();
      var status = String(a.status || 'IN-FLIGHT').toUpperCase();
      var aff = 'friend';
      if (airSide === 'EAST') aff = 'hostile';
      else if (airSide === 'GUER' || airSide === 'CIV' || status === 'SUSPECT') aff = 'unknown';
      var label = a.callsign || a.call_sign || 'Aérien';
      return nato && nato.leafletDivIcon
        ? nato.leafletDivIcon(L, {
            affiliation: aff,
            aircraftType: a.aircraft_type || 'plane',
            role: a.model || a.aircraft_type || '',
            callSign: label,
            showLabel: true,
            size: 22
          })
        : L.divIcon({
            className: 'ow-air-map-icon',
            html: '<span class="ow-air-glyph">▲</span><span class="ow-air-label">' + escapeHtml(label) + '</span>',
            iconSize: [16, 16],
            iconAnchor: [8, 8]
          });
    }
    function airAssetId(a) {
      var id = 'air_' + String(a.callsign || a.call_sign || a.id || '').replace(/\s/g, '_');
      return id === 'air_' ? '' : id;
    }
    /** Appareils sur la carte. hooks.skip(a), hooks.onClick(a), hooks.zIndexOffset(a). */
    function syncAirAssets(store, rows, hooks) {
      hooks = hooks || {};
      var seen = {};
      (rows || []).forEach(function (a) {
        if (!a) return;
        var id = airAssetId(a);
        if (!id) return;
        if (hooks.skip && hooks.skip(a)) return;
        var x = Number(a.pos_x);
        var y = Number(a.pos_y);
        if (!Number.isFinite(x) || !Number.isFinite(y) || (Math.abs(x) < 0.5 && Math.abs(y) < 0.5)) return;
        seen[id] = true;
        var latlng = worldToLatLng(x, y);
        var icon = airAssetIcon(a);
        var z = hooks.zIndexOffset ? hooks.zIndexOffset(a) : 500;
        if (store[id]) {
          store[id].setLatLng(latlng);
          store[id].setIcon(icon);
          if (store[id].setZIndexOffset) store[id].setZIndexOffset(z);
          store[id].off('click').on('click', function () { if (hooks.onClick) hooks.onClick(a); });
          return;
        }
        var marker = L.marker(latlng, { icon: icon, zIndexOffset: z });
        marker.on('click', function () { if (hooks.onClick) hooks.onClick(a); });
        marker.addTo(map);
        store[id] = marker;
      });
      Object.keys(store).forEach(function (id) {
        if (!seen[id]) removeFrom(store, id);
      });
    }

    function jtacGeoLabel(pt, text, cls) {
      return L.marker(worldToLatLng(pt.x, pt.y), {
        interactive: false,
        keyboard: false,
        icon: L.divIcon({ className: 'ow-jtac-geo-label ' + (cls || ''), html: '<span>' + escapeHtml(text) + '</span>', iconSize: null })
      });
    }
    /**
     * Géométrie des appuis JTAC (objet « geo » du 9-LINE / 5-LINE) : cible, IP / BP et axe d'entrée (cap, distance),
     * cône FAH, distance de risque, JTAC et laser, ami le plus proche, appareil. Renvoie un L.layerGroup (non ajouté).
     * opts.filter(row) : lignes retenues ; opts.highlight(row) : cible mise en avant (brief reçu par mon appareil).
     */
    function jtacGeoLayer(rows, opts) {
      opts = opts || {};
      var group = L.layerGroup();
      var amber = '#f2ab33';
      var red = '#e5483a';
      var green = '#5cc76b';
      var cyan = '#33e6f2';
      var ll = function (pt) { return worldToLatLng(pt.x, pt.y); };
      (rows || []).forEach(function (row) {
        var g = rowGeo(row);
        if (!g || !jtacGeoActive(row)) return;
        if (opts.filter && !opts.filter(row)) return;
        var tgt = jtacGeoPoint(g.target);
        if (!tgt) return;
        var dc = !!g.dangerClose;
        var ip = jtacGeoPoint(g.ip);
        var who = String(row.author || row.jtac || 'JTAC');
        // Cône FAH : l'appareil vole au cap FAH vers la cible, il arrive donc du côté opposé.
        var win = Array.isArray(g.fahWindow) && g.fahWindow.length === 2 ? g.fahWindow.map(Number) : null;
        if (win && Number.isFinite(win[0]) && Number.isFinite(win[1])) {
          var span = Math.max(2, ((win[1] - win[0]) % 360 + 360) % 360);
          var rad = ip ? Math.min(15000, Math.max(3000, Math.hypot(ip.x - tgt.x, ip.y - tgt.y) * 1.1)) : 6000;
          var cone = [ll(tgt)];
          for (var a = 0; a <= span; a += Math.max(1, span / 12)) cone.push(ll(jtacGeoOffset(tgt, rad, win[0] + a + 180)));
          L.polygon(cone, { color: amber, weight: 1.5, fillColor: amber, fillOpacity: 0.12, interactive: false }).addTo(group);
          jtacGeoLabel(jtacGeoOffset(tgt, rad * 0.7, win[0] + span / 2 + 180), 'FAH ' + (g.fah || ''), 'is-amber').addTo(group);
        }
        // Distance de risque de l'armement (rouge si DANGER CLOSE).
        var risk = Number(g.risk || 0);
        if (risk > 0) {
          var ring = [];
          for (var d = 0; d < 360; d += 10) ring.push(ll(jtacGeoOffset(tgt, risk, d)));
          L.polygon(ring, { color: dc ? red : amber, weight: 1.5, dashArray: '6 4', fill: false, interactive: false }).addTo(group);
          jtacGeoLabel(jtacGeoOffset(tgt, risk, 135), 'RED ' + Math.round(risk) + ' m', dc ? 'is-red' : 'is-amber').addTo(group);
        }
        // IP / BP et axe d'entrée (cap et distance).
        if (ip) {
          L.polyline([ll(ip), ll(tgt)], { color: green, weight: 2, interactive: false }).addTo(group);
          var hdg = Math.round((Math.atan2(tgt.x - ip.x, tgt.y - ip.y) * 180 / Math.PI + 360) % 360);
          var nm = (Math.hypot(tgt.x - ip.x, tgt.y - ip.y) / 1852).toFixed(1);
          jtacGeoLabel({ x: (ip.x + tgt.x) / 2, y: (ip.y + tgt.y) / 2 }, String(hdg).padStart(3, '0') + '° · ' + nm + ' NM', 'is-green').addTo(group);
          L.circleMarker(ll(ip), { radius: 6, color: green, weight: 2, fillColor: green, fillOpacity: 0.35 })
            .bindTooltip(g.ipName || 'IP', { permanent: true, direction: 'right', className: 'ow-jtac-geo-tip is-green' }).addTo(group);
        }
        // Ami le plus proche relié à la cible.
        var fr = jtacGeoPoint(g.friendly);
        if (fr) {
          L.polyline([ll(tgt), ll(fr)], { color: dc ? red : green, weight: 1.5, dashArray: '3 4', interactive: false }).addTo(group);
          jtacGeoLabel({ x: (fr.x + tgt.x) / 2, y: (fr.y + tgt.y) / 2 }, Math.round(Math.hypot(fr.x - tgt.x, fr.y - tgt.y)) + ' m', dc ? 'is-red' : 'is-green').addTo(group);
        }
        // JTAC et ligne laser-cible.
        var jt = jtacGeoPoint(g.jtac);
        if (jt) {
          if (g.laser) L.polyline([ll(jt), ll(tgt)], { color: red, weight: 1.5, opacity: 0.75, interactive: false }).addTo(group);
          L.circleMarker(ll(jt), { radius: 5, color: cyan, weight: 2, fillColor: cyan, fillOpacity: 0.5 })
            .bindTooltip(who, { permanent: true, direction: 'right', className: 'ow-jtac-geo-tip is-cyan' }).addTo(group);
        }
        // Appareil au moment du 9-LINE.
        var ac = jtacGeoPoint(g.aircraftPos);
        if (ac) {
          L.polyline([ll(ac), ll(tgt)], { color: green, weight: 1, dashArray: '2 6', opacity: 0.7, interactive: false }).addTo(group);
          L.circleMarker(ll(ac), { radius: 5, color: green, weight: 2, fillColor: '#0b120e', fillOpacity: 0.8 })
            .bindTooltip(g.aircraft || 'ACFT', { permanent: true, direction: 'top', className: 'ow-jtac-geo-tip is-green' }).addTo(group);
        }
        // Cible : losange, grille (ligne 6) et fiche du 9-LINE au clic.
        var hot = !!(opts.highlight && opts.highlight(row));
        var five = /5/.test(String(row.mission_kind || row.format || ''));
        var tip = 'TGT · ' + who + (row.line6 ? ' · ' + row.line6 : '') + (g.laser && g.laserCode ? ' · LSR ' + g.laserCode : '');
        L.marker(ll(tgt), {
          zIndexOffset: 600,
          icon: L.divIcon({ className: 'ow-jtac-geo-target' + (dc ? ' is-dc' : '') + (hot ? ' is-mine' : ''), html: '<span></span>', iconSize: [18, 18], iconAnchor: [9, 9] })
        }).bindTooltip(tip, { permanent: true, direction: 'right', offset: [10, 0], className: 'ow-jtac-geo-tip is-red' })
          .bindPopup('<strong>' + (five ? '5-LINE ' : '9-LINE ') + escapeHtml(who) + '</strong><br>' + (five ? [1, 2, 3, 4, 5] : [1, 2, 3, 4, 5, 6, 7, 8, 9]).map(function (n) {
            return n + '. ' + escapeHtml(row['line' + n] || '—');
          }).join('<br>') + (dc ? '<br><strong>DANGER CLOSE</strong>' : ''))
          .addTo(group);
      });
      return group;
    }

    /**
     * Icône d'un contact BFT (losange / point / OTAN aux couleurs du camp), libellé de l'indicatif.
     * o : { style, extra (classes), stateColor, squad, hint, labelHtml, badgeHtml }.
     */
    function unitIcon(o) {
      o = o || {};
      var stateColor = String(o.stateColor || '#00d69a');
      var squad = String(o.squad || stateColor);
      return L.divIcon({
        className: 'ow-marker ow-mark-' + (o.style || 'diamond') + (o.extra || ''),
        iconSize: [16, 16],
        iconAnchor: [8, 8],
        html: '<div' + (o.hint ? ' title="' + escapeHtml(o.hint) + '"' : '') + ' style="--ow-iff:' + escapeHtml(stateColor) + ';--ow-state:' + escapeHtml(stateColor) + ';--ow-squad:' + escapeHtml(squad) + '"><i></i>' + (o.labelHtml || '') + (o.badgeHtml || '') + '</div>'
      });
    }

    return {
      map: map,
      config: config,
      worldSize: worldSize,
      bounds: bounds,
      center: center,
      maxZoom: maxZoom,
      baseTileLayer: baseTileLayer,
      worldToLatLng: worldToLatLng,
      latLngToWorld: latLngToWorld,
      fitMinZoom: function () { kitFitMin(); },
      circleLatLngs: circleLatLngs,
      offsetPerp: offsetPerp,
      arrowHeadPolygon: arrowHeadPolygon,
      filledAttackLatLngs: filledAttackLatLngs,
      sectorTickLayers: sectorTickLayers,
      shapeLayer: shapeLayer,
      labelShape: labelShape,
      syncArmaMarkers: syncArmaMarkers,
      clearStore: clearStore,
      airAssetIcon: airAssetIcon,
      airAssetId: airAssetId,
      syncAirAssets: syncAirAssets,
      jtacGeoLayer: jtacGeoLayer,
      unitIcon: unitIcon
    };
  }

  window.OverwatchMapKit = {
    create: create,
    escapeHtml: escapeHtml,
    proxiedTilePattern: proxiedTilePattern,
    parseMarkerData: parseMarkerData,
    markerWorld: markerWorld,
    isPoLabel: isPoLabel,
    parseShapeMeta: parseShapeMeta,
    natoDash: natoDash,
    geometryOf: geometryOf,
    side: side,
    extraOf: extraOf,
    unitAgeSec: unitAgeSec,
    isTrackedAi: isTrackedAi,
    isDisconnected: isDisconnected,
    tooOldToShow: tooOldToShow,
    unitCallsign: unitCallsign,
    jtacGeoPoint: jtacGeoPoint,
    jtacGeoActive: jtacGeoActive,
    rowGeo: rowGeo,
    jtacGeoSignature: jtacGeoSignature
  };
})();
