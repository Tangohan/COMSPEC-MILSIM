/* COMSPEC — moteur carte opérationnelle partagé (Overwatch + TACMAP / initTacmap) */
(function (global) {
  'use strict';

  var WORLD_SCALE = 30000;
  var ALTIS_WORLD_SIZE = 30720;
  var ALTIS_FACTOR = 212 / ALTIS_WORLD_SIZE;
  var ALTIS_CENTER = [ALTIS_WORLD_SIZE / 2, ALTIS_WORLD_SIZE / 2];
  var ALTIS_BOUNDS = [[0, 0], [ALTIS_WORLD_SIZE, ALTIS_WORLD_SIZE]];
  var lastTacmapInvalidate = null;

  /**
   * Traceurs de déplacement (polylines) réutilisables par Overwatch / initTacmap.
   * @param {{ maxPoints?: number }} [options]
   */
  function createUnitTrailTracker(options) {
    options = options || {};
    var maxPoints = options.maxPoints || 40;
    /** @type {Object.<string, Array<[number,number]>>} */
    var buffers = {};
    return {
      clear: function () {
        buffers = {};
      },
      push: function (unitKey, latlng) {
        if (!unitKey || !latlng) return;
        var buf = buffers[unitKey] || [];
        var last = buf.length ? buf[buf.length - 1] : null;
        if (last && last[0] === latlng.lat && last[1] === latlng.lng) return;
        buf.push([latlng.lat, latlng.lng]);
        if (buf.length > maxPoints) buf = buf.slice(buf.length - maxPoints);
        buffers[unitKey] = buf;
      },
      render: function (layerGroup, visible, color) {
        if (!layerGroup) return;
        layerGroup.clearLayers();
        if (!visible) return;
        var c = color || '#67e8f9';
        Object.keys(buffers).forEach(function (key) {
          var pts = buffers[key];
          if (!pts || pts.length < 2) return;
          L.polyline(pts, { color: c, weight: 2, opacity: 0.75, interactive: false }).addTo(layerGroup);
        });
      },
    };
  }

  function trailColorFromCss() {
    if (typeof getComputedStyle !== 'function') return '#67e8f9';
    var fromOw = getComputedStyle(document.documentElement).getPropertyValue('--ow-trail').trim();
    if (fromOw) return fromOw;
    var fromTm = getComputedStyle(document.documentElement).getPropertyValue('--tm-trail').trim();
    return fromTm || '#67e8f9';
  }

  function buildArmaConfig(raw) {
    if (!raw || !raw.tilePattern) return null;
    var isAltis = (raw.slug || (raw.config && raw.config.title) || '').toString().toLowerCase() === 'altis';
    var crsOpt = raw.crs || {};
    var tileWidth = crsOpt.tileWidth != null ? crsOpt.tileWidth : 212;
    var factorx = isAltis ? ALTIS_FACTOR : (crsOpt.factorx != null ? crsOpt.factorx : ALTIS_FACTOR);
    var factory = isAltis ? ALTIS_FACTOR : (crsOpt.factory != null ? crsOpt.factory : ALTIS_FACTOR);
    var CRS = typeof global.MGRS_CRS === 'function' ? global.MGRS_CRS(factorx, factory, tileWidth) : L.CRS.Simple;
    var center = isAltis ? ALTIS_CENTER : (Array.isArray(raw.center) ? raw.center : (raw.config && Array.isArray(raw.config.center) ? raw.config.center : ALTIS_CENTER));
    var bounds = isAltis ? ALTIS_BOUNDS : (raw.bounds || (raw.config && raw.config.bounds) || null);
    return {
      CRS: CRS,
      tilePattern: raw.tilePattern,
      minZoom: raw.minZoom != null ? raw.minZoom : 0,
      maxZoom: raw.maxZoom != null ? raw.maxZoom : 6,
      defaultZoom: raw.defaultZoom != null ? raw.defaultZoom : 3,
      attribution: raw.attribution || '&copy; Bohemia Interactive',
      tileSize: raw.tileSize != null ? raw.tileSize : 212,
      center: center,
      bounds: bounds,
    };
  }

  function buildImageConfig(raw) {
    if (!raw || !raw.imageUrl) return null;
    var w = parseInt(raw.imageWidth, 10) || 1000;
    var h = parseInt(raw.imageHeight, 10) || 1000;
    var bounds = Array.isArray(raw.bounds) && raw.bounds.length === 2
      ? raw.bounds
      : [[0, 0], [h, w]];
    var center = Array.isArray(raw.center) ? raw.center : [h / 2, w / 2];
    return {
      CRS: L.CRS.Simple,
      imageUrl: raw.imageUrl,
      minZoom: raw.minZoom != null ? raw.minZoom : -2,
      maxZoom: raw.maxZoom != null ? raw.maxZoom : 4,
      defaultZoom: raw.defaultZoom != null ? raw.defaultZoom : 0,
      attribution: raw.attribution || 'Fond personnalisé',
      center: center,
      bounds: bounds,
      mapId: raw.mapId,
      slug: raw.slug,
      label: raw.label,
    };
  }

  function mapKindFromSlug(slug, mapsConfigs) {
    if (slug === 'world' || slug === 'world_relief') return 'world';
    var c = mapsConfigs && mapsConfigs[slug];
    if (c && (c.type === 'image' || c.imageUrl)) return 'image';
    return 'arma';
  }

  function worldTileSpec(slug) {
    if (slug === 'world_relief') {
      return {
        url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
        attribution: '© OpenStreetMap contributors, SRTM | Style: © OpenTopoMap (CC-BY-SA)',
        maxZoom: 17
      };
    }
    return {
      url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      attribution: '© OpenStreetMap',
      maxZoom: 19
    };
  }

  function affiliationColor(aff) {
    var a = (aff || '').toUpperCase();
    if (a === 'ENEMY' || a === 'HOSTILE') return '#dc2626';
    if (a === 'UNKNOWN' || a === 'SUSPECT') return '#eab308';
    if (a === 'NEUTRAL') return '#22c55e';
    return '#3b82f6';
  }

  /** Préfixe …/api déjà présent (portail) ou origine seule (clients externes). */
  function tacticalMapShapesUrl(apiBase) {
    var base = String(apiBase || '').replace(/\/$/, '');
    if (base.length >= 4 && base.slice(-4) === '/api') {
      return base + '/map-shapes';
    }
    return base + '/api/map-shapes';
  }

  /**
   * Attache métadonnées + clic droit pour suppression (Overwatch / TACMAP).
   * @param {L.Layer} layer
   * @param {{ kind: string, id: string|number, label?: string }} meta
   * @param {function|null|undefined} onCtx
   */
  function bindDeletableLayer(layer, meta, onCtx) {
    if (!layer || !meta || meta.id == null || meta.id === '') return layer;
    var kind = String(meta.kind || 'shape');
    var id = meta.id;
    var label = (meta.label != null ? String(meta.label) : '') || '';
    layer.options = layer.options || {};
    layer.options.comspecFeatureKind = kind;
    layer.options.comspecFeatureId = id;
    layer.options.comspecFeatureLabel = label;
    layer.feature = layer.feature || {};
    layer.feature.comspec = { kind: kind, id: id, label: label };
    if (typeof onCtx === 'function') {
      layer.on('contextmenu', function (e) {
        if (L.DomEvent) {
          L.DomEvent.preventDefault(e);
          L.DomEvent.stopPropagation(e);
        }
        if (e.originalEvent) {
          e.originalEvent.preventDefault();
          e.originalEvent.stopPropagation();
        }
        onCtx({
          kind: kind,
          id: id,
          label: label,
          latlng: e.latlng,
          layer: layer,
          originalEvent: e.originalEvent || null,
        });
      });
    }
    return layer;
  }

  /**
   * @param {object} opts
   * @param {string} opts.apiBase
   * @param {number} opts.mapId
   * @param {string} [opts.missionId]
   * @param {L.Map} opts.map
   * @param {L.LayerGroup} opts.layerGroup
   * @param {boolean} opts.isWorld
   * @param {function} [opts.onFeatureContextMenu]
   */
  function renderMapShapes(opts) {
    if (!opts || !opts.layerGroup || !opts.map) return;
    var url = tacticalMapShapesUrl(opts.apiBase) + '?mapId=' + encodeURIComponent(opts.mapId);
    if (opts.missionId) url += '&missionId=' + encodeURIComponent(opts.missionId);
    fetch(url, { credentials: opts.credentials || 'include' })
      .then(function (r) { return r.json(); })
      .then(function (rows) {
        if (!opts.layerGroup || !opts.map) return;
        opts.layerGroup.clearLayers();
        (rows || []).forEach(function (s) {
          addOneMapShape(s, opts.layerGroup, opts.isWorld, opts.onFeatureContextMenu);
        });
      })
      .catch(function () {});
  }

  function addOneMapShape(s, layerGroup, isWorld, onCtx) {
    var geom = s.geometry || {};
    var type = (s.type || '').toString().toUpperCase();
    var color = s.color || '#3388ff';
    var fillOp = s.fill_opacity != null ? parseFloat(s.fill_opacity) : 0.15;
    var weight = s.stroke != null ? parseInt(s.stroke, 10) : 2;
    var label = (s.label || type || 'Tracé').toString();
    var shapeId = s.id != null ? s.id : (s.shapeUid || s.shape_uid || null);
    var layer = null;

    if (geom.center && geom.radius != null) {
      var lat, lng, radius;
      if (isWorld) {
        lat = geom.center[0] / WORLD_SCALE;
        lng = geom.center[1] / WORLD_SCALE;
        radius = Math.min((geom.radius / WORLD_SCALE) * 111000, 50000);
      } else {
        lat = geom.center[1];
        lng = geom.center[0];
        radius = geom.radius;
      }
      layer = L.circle([lat, lng], { radius: radius, color: color, fillOpacity: fillOp, weight: weight });
      layer.bindPopup(label);
      bindDeletableLayer(layer, { kind: 'shape', id: shapeId, label: label }, onCtx);
      layer.addTo(layerGroup);
      return;
    }

    var pts = geom.points || geom.vertices || geom.path;
    if (Array.isArray(pts) && pts.length >= 2) {
      var latlngs = pts.map(function (p) {
        var x = p[0];
        var y = p[1];
        if (isWorld) return [x / WORLD_SCALE, y / WORLD_SCALE];
        return [y, x];
      });
      if (type === 'POLYGON' || geom.closed) {
        layer = L.polygon(latlngs, { color: color, fillOpacity: fillOp, weight: weight });
      } else {
        layer = L.polyline(latlngs, { color: color, weight: weight });
      }
      layer.bindPopup(label);
      bindDeletableLayer(layer, { kind: 'shape', id: shapeId, label: label }, onCtx);
      layer.addTo(layerGroup);
    }
  }

  function parseMarkerDataPos(pos, isWorld) {
    if (!pos || !pos.length) return null;
    var x, y;
    if (Array.isArray(pos[0])) {
      x = pos[0][0];
      y = pos[0][1];
    } else {
      x = pos[0];
      y = pos[1];
    }
    if (x == null || y == null || isNaN(x) || isNaN(y)) return null;
    if (isWorld) {
      return L.latLng(x / WORLD_SCALE, y / WORLD_SCALE);
    }
    return L.latLng(y, x);
  }

  function renderAtakMarkers(layerGroup, list, isWorld, onCtx, unitsForDedupe) {
    if (!layerGroup) return;
    layerGroup.clearLayers();
    var arma = window.ArmaMapMarkers;
    var units = Array.isArray(unitsForDedupe) ? unitsForDedupe : [];
    (list || []).forEach(function (m) {
      var id = m.id;
      if (id == null) return;
      var raw = m.markerData;
      var data = typeof raw === 'string' ? (function () { try { return JSON.parse(raw || '{}'); } catch (e) { return {}; } })() : (raw || {});
      if (arma && arma.isLiveUnitDuplicate && arma.isLiveUnitDuplicate(data, units)) return;
      var latlng = parseMarkerDataPos(data.pos, isWorld);
      if (!latlng) return;
      var label = (arma && arma.displayLabelOf) ? arma.displayLabelOf(data) : ((arma && arma.labelOf) ? arma.labelOf(data) : ((data.text || data.label || 'Repère') + ''));
      var layer = null;
      if (arma && arma.isAreaShape && arma.isAreaShape(data) && arma.leafletShapeLayer) {
        layer = arma.leafletShapeLayer(L, data, latlng);
      }
      if (!layer) {
        var icon;
        if (arma && arma.leafletDivIcon && (arma.isArmaStyleMarker(data) || data.type || data.color)) {
          icon = arma.leafletDivIcon(L, data);
        } else {
          var color = (arma && arma.armaColorHex) ? arma.armaColorHex(data.color) : '#10b981';
          icon = L.divIcon({
            className: 'comspec-atak-marker',
            html: '<div style="display:flex;flex-direction:column;align-items:center;gap:1px;">' +
              '<span style="width:11px;height:11px;border-radius:50%;background:' + color + ';border:2px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.2);"></span>' +
              '<span style="font:700 8px/1 ui-sans-serif,system-ui;color:#94a3b8;text-shadow:0 0 2px #000;">' +
              String(label || 'Repère').replace(/</g, '&lt;').slice(0, 14) + '</span></div>',
            iconSize: [80, 28],
            iconAnchor: [40, 10],
          });
        }
        layer = L.marker(latlng, { icon: icon });
      }
      var typeLab = (arma && arma.typeLabelFr) ? arma.typeLabelFr(data) : (data.type ? String(data.type) : '');
      layer.bindPopup(
        '<div class="atak-marker-popup__kind">Repère carte</div><strong>' + String(label || 'Repère').replace(/</g, '&lt;') + '</strong>' +
        (typeLab ? '<br/>' + String(typeLab).replace(/</g, '&lt;') : '') +
        '<p class="atak-marker-popup__hint">Ce point n’est pas un effectif en liaison — c’est un repère posé sur la carte.</p>'
      );
      bindDeletableLayer(layer, { kind: 'marker', id: id, label: label }, onCtx);
      layer.addTo(layerGroup);
    });
  }

  function pingKindFromMessage(msg) {
    var rawMsg = String(msg || '');
    var m = rawMsg.match(/^\s*\[([^\]]+)\]\s*/);
    if (m) {
      var raw = m[1].toLowerCase();
      var rest = rawMsg.slice(m[0].length);
      if (raw.indexOf('drone') >= 0 || raw.indexOf('isr') >= 0) {
        var dLabel = 'Contact ISR';
        var dColor = '#f97316';
        if (raw.indexOf('eny') >= 0 || raw.indexOf('hostile') >= 0) {
          dLabel = 'ISR adversaire';
          dColor = '#ef4444';
        } else if (raw.indexOf('civ') >= 0) {
          dLabel = 'ISR civil';
          dColor = '#22c55e';
        } else if (raw.indexOf('unk') >= 0) {
          dLabel = 'ISR inconnu';
          dColor = '#eab308';
        }
        return { kind: 'drone', label: dLabel, color: dColor, rest: rest };
      }
      if (raw.indexOf('hostile') >= 0 || raw.indexOf('ennemi') >= 0) return { kind: 'hostile', label: 'Hostile', color: '#ef4444', rest: rest };
      if (raw.indexOf('medical') >= 0 || raw.indexOf('medecin') >= 0 || raw.indexOf('médecin') >= 0 || raw.indexOf('bless') >= 0) {
        return { kind: 'medical', label: 'Médical', color: '#f8fafc', rest: rest };
      }
      if (raw.indexOf('rally') >= 0 || raw.indexOf('ralli') >= 0 || raw.indexOf('rp') >= 0) {
        return { kind: 'rally', label: 'Ralliement', color: '#22c55e', rest: rest };
      }
      if (raw.indexOf('contact') >= 0) return { kind: 'contact', label: 'Contact', color: '#f97316', rest: rest };
      if (raw.indexOf('objectif') >= 0 || raw.indexOf('obj') >= 0) return { kind: 'objective', label: 'Objectif', color: '#eab308', rest: rest };
      if (raw.indexOf('alerte') >= 0 || raw.indexOf('warning') >= 0) return { kind: 'warning', label: 'Alerte', color: '#f97316', rest: rest };
      if (raw.indexOf('rep') >= 0 || raw.indexOf('marqueur') >= 0) return { kind: 'marker', label: 'Repère', color: '#ef4444', rest: rest };
      return { kind: 'info', label: m[1], color: '#38bdf8', rest: rest };
    }
    var low = rawMsg.toLowerCase();
    if (low.indexOf('point d') >= 0 && low.indexOf('inter') >= 0) {
      return { kind: 'info', label: 'Intérêt', color: '#38bdf8', rest: rawMsg };
    }
    if (low.indexOf('marqueur') >= 0) {
      return { kind: 'marker', label: 'Repère', color: '#ef4444', rest: rawMsg };
    }
    return { kind: 'info', label: 'Ping', color: '#ec4899', rest: rawMsg };
  }

  function renderPingsLayer(layerGroup, rows, isWorld) {
    if (!layerGroup) return;
    layerGroup.clearLayers();
    (rows || []).forEach(function (p) {
      var x = parseFloat(p.pos_x);
      var y = parseFloat(p.pos_y);
      if (isNaN(x) || isNaN(y)) return;
      var latlng = isWorld ? L.latLng(x / WORLD_SCALE, y / WORLD_SCALE) : L.latLng(y, x);
      var kind = pingKindFromMessage(p.message);
      var author = String(p.author || '').trim();
      var pinLabel = kind.label || 'Ping';
      var icon = L.divIcon({
        className: 'comspec-ping-marker',
        html: '<div style="display:flex;flex-direction:column;align-items:center;">' +
          '<span style="width:12px;height:12px;border-radius:50%;background:' + kind.color + ';border:2px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.35);"></span>' +
          '<span style="margin-top:1px;font:700 8px/1 ui-sans-serif,system-ui;color:' + kind.color + ';text-shadow:0 0 2px #000;white-space:nowrap;max-width:72px;overflow:hidden;text-overflow:ellipsis;">' +
          String(pinLabel).replace(/</g, '&lt;').slice(0, 14) + '</span></div>',
        iconSize: [72, 26],
        iconAnchor: [36, 8],
      });
      L.marker(latlng, { icon: icon })
        .bindPopup(
          '<div class="atak-marker-popup__kind">Ping</div><strong>' + String(kind.label).replace(/</g, '&lt;') +
          (author ? ' — ' + String(author).replace(/</g, '&lt;') : '') + '</strong><br/>' +
          String(kind.rest || p.message || '').replace(/</g, '&lt;') +
          '<p class="atak-marker-popup__hint">Signal ponctuel — ce n’est pas la position d’un effectif.</p>'
        )
        .addTo(layerGroup);
    });
  }

  function renderSseCaseOverlay(layerGroup, payload, isWorld) {
    if (!layerGroup) return;
    layerGroup.clearLayers();
    var layers = (payload && payload.layers) ? payload.layers : null;
    var points = [];
    var polylines = [];
    if (layers && layers.length) {
      layers.forEach(function (layer) {
        (layer.points || []).forEach(function (p) { points.push(p); });
        (layer.polylines || []).forEach(function (line) { polylines.push(line); });
      });
    } else {
      points = (payload && payload.points) ? payload.points : [];
    }
    points.forEach(function (p) {
      var x = parseFloat(p.pos_x);
      var y = parseFloat(p.pos_y);
      if (isNaN(x) || isNaN(y)) return;
      var latlng = isWorld ? L.latLng(x / WORLD_SCALE, y / WORLD_SCALE) : L.latLng(y, x);
      var color = p.color || (p.source === 'site' ? '#f59e0b' : '#34d399');
      var label = String(p.label || 'SSE').slice(0, 18);
      var icon = L.divIcon({
        className: 'comspec-sse-case-marker',
        html: '<div style="display:flex;flex-direction:column;align-items:center;">'
          + '<span style="width:11px;height:11px;border-radius:2px;background:' + color
          + ';border:1px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.4);transform:rotate(45deg);"></span>'
          + '<span style="margin-top:3px;font:700 8px/1 ui-sans-serif,system-ui;color:' + color
          + ';text-shadow:0 0 2px #000;white-space:nowrap;max-width:88px;overflow:hidden;text-overflow:ellipsis;">'
          + String(label).replace(/</g, '&lt;') + '</span></div>',
        iconSize: [88, 28],
        iconAnchor: [44, 8],
      });
      var kindLabel = p.layer === 'pir' ? 'Priorité de renseignement'
        : (p.layer === 'taskings' ? 'Ordre de collecte'
          : (p.layer === 'photos' ? 'Photo terrain'
            : (p.layer === 'history' ? 'Historique' : 'Dossier SSE')));
      var popup = '<div class="atak-marker-popup__kind">' + kindLabel + '</div>'
        + '<strong>' + String(p.case_ref || '').replace(/</g, '&lt;') + '</strong>'
        + (p.case_title ? '<br/>' + String(p.case_title).replace(/</g, '&lt;') : '')
        + '<br/><em>' + String(p.label || '').replace(/</g, '&lt;') + '</em>'
        + (p.note ? '<p style="margin:.4rem 0 0">' + String(p.note).replace(/</g, '&lt;') + '</p>' : '')
        + '<p class="atak-marker-popup__hint">Calque renseignement — distinct des pings mission.</p>';
      L.marker(latlng, { icon: icon }).bindPopup(popup).addTo(layerGroup);
    });
    polylines.forEach(function (line) {
      var pts = (line.points || []).map(function (pt) {
        var x = parseFloat(pt.pos_x);
        var y = parseFloat(pt.pos_y);
        if (isNaN(x) || isNaN(y)) return null;
        return isWorld ? L.latLng(x / WORLD_SCALE, y / WORLD_SCALE) : L.latLng(y, x);
      }).filter(Boolean);
      if (pts.length < 2) return;
      L.polyline(pts, {
        color: line.color || '#67e8f9',
        weight: 2,
        opacity: line.dashed ? 0.55 : 0.8,
        dashArray: line.dashed ? '6 8' : null
      }).bindPopup(String(line.label || 'Tracé').replace(/</g, '&lt;')).addTo(layerGroup);
    });
  }

  function renderSigintLayer(layerGroup, zones, isWorld) {
    if (!layerGroup) return;
    layerGroup.clearLayers();
    (zones || []).forEach(function (z, i) {
      var posX = z.pos_x != null ? z.pos_x : 0;
      var posY = z.pos_y != null ? z.pos_y : 0;
      var radius = z.radius != null ? z.radius : 200;
      var latlng = isWorld ? L.latLng(posX / WORLD_SCALE, posY / WORLD_SCALE) : L.latLng(posY, posX);
      var r = isWorld ? Math.min((radius / WORLD_SCALE) * 111000, 50000) : radius;
      L.circle(latlng, { radius: r, color: '#b91c1c', fillOpacity: 0.12, weight: 2 })
        .bindPopup('Veille : zone d’incertitude (' + (z.reports || 0) + ' signalement(s))')
        .addTo(layerGroup);
    });
  }

  function renderIntelFusedMarkers(layerGroup, reports, isWorld) {
    if (!layerGroup) return;
    layerGroup.clearLayers();
    (reports || []).forEach(function (r) {
      var lat = isWorld ? (r.pos_y != null ? r.pos_y : 0) / WORLD_SCALE : (r.pos_y != null ? r.pos_y : 0);
      var lng = isWorld ? (r.pos_x != null ? r.pos_x : 0) / WORLD_SCALE : (r.pos_x != null ? r.pos_x : 0);
      var status = r.status || 'TEMPORARY';
      var col = status === 'CONFIRMED' ? '#dc2626' : status === 'CORROBORATED' ? '#d97706' : '#eab308';
      L.circleMarker([lat, lng], { radius: 9, color: col, fillOpacity: 0.85, weight: 2 })
        .bindPopup((r.target_type || 'Indice') + ' — ' + status)
        .addTo(layerGroup);
    });
  }

  function renderAirAssetsLayer(layerGroup, assets, isWorld) {
    if (!layerGroup) return;
    layerGroup.clearLayers();
    var nato = window.NatoSidcIcons;
    (assets || []).forEach(function (a) {
      if (a.pos_x == null || a.pos_y == null) return;
      var latlng = isWorld ? L.latLng(a.pos_x / WORLD_SCALE, a.pos_y / WORLD_SCALE) : L.latLng(a.pos_y, a.pos_x);
      var side = (a.side || 'WEST').toUpperCase();
      var aff = 'friend';
      if (side === 'EAST') aff = 'hostile';
      else if (side === 'GUER' || side === 'CIV') aff = 'unknown';
      var icon = nato && nato.leafletDivIcon
        ? nato.leafletDivIcon(L, {
            affiliation: aff,
            aircraftType: a.aircraft_type || 'plane',
            role: a.model || '',
            callSign: a.callsign || '',
            showLabel: true,
            size: 34,
          })
        : L.divIcon({
            className: 'comspec-air-marker',
            html: '<span style="color:#3b82f6;font-size:15px;font-weight:bold;">▲</span>',
            iconSize: [18, 18],
            iconAnchor: [9, 9],
          });
      var airMarker = L.marker(latlng, { icon: icon });
      if (window.ATAKUnitPopup && window.ATAKUnitPopup.bindAir) {
        window.ATAKUnitPopup.bindAir(airMarker, a);
      } else {
        airMarker.bindPopup('<strong>' + (a.callsign || '—') + '</strong><br/>' + (a.model || '') + '<br/>' + (a.status || ''));
      }
      airMarker.addTo(layerGroup);
    });
  }

  function statusLabelFr(status) {
    var s = (status || '').toLowerCase();
    if (s === 'linked') return 'En liaison';
    if (s === 'delayed') return 'Signal différé';
    if (s === 'offline') return 'Hors liaison';
    return status || '—';
  }

  function affiliationLabelFr(a) {
    var x = (a || '').toString().toUpperCase();
    if (x === 'FRIEND' || x === 'FRIENDLY') return 'Allié';
    if (x === 'ENEMY' || x === 'HOSTILE') return 'Hostile';
    if (x === 'NEUTRAL') return 'Neutre';
    if (x === 'UNKNOWN' || x === 'SUSPECT') return 'Incertain';
    return a ? String(a) : '';
  }

  function formatTimeAgo(iso) {
    if (!iso) return '—';
    var t = new Date(iso).getTime();
    if (isNaN(t)) return '—';
    var sec = Math.max(0, Math.floor((Date.now() - t) / 1000));
    if (sec < 60) return 'Il y a ' + sec + ' s';
    if (sec < 3600) return 'Il y a ' + Math.floor(sec / 60) + ' min';
    return new Date(iso).toLocaleString('fr-FR');
  }

  /**
   * @param {object} cfg
   */
  function initTacmap(cfg) {
    var ctx = cfg.context;
    var mapsConfigs = cfg.mapsConfigs || {};
    var workspaces = cfg.workspaces || [];
    var apiBase = ctx.apiBase;
    var syncMs = ctx.syncIntervalMs || 8000;
    var tenantId = ctx.tenantId || 0;
    var els = cfg.els || {};
    var features = cfg.features || {};
    var trailsEnabled = features.trails !== false;
    var medicalPanelEnabled = !!features.medicalPanel;
    var TRAIL_MAX_POINTS = 40;

    var state = {
      currentMapId: ctx.defaultMapId,
      currentMapSlug: ctx.defaultMapSlug,
      currentMapType: 'arma',
      currentMissionId: ctx.defaultMissionId,
      syncStatus: 'idle',
      lastSyncAt: null,
      unitsCount: 0,
      layers: {
        units: true,
        trails: trailsEnabled,
        danger: true,
        drawings: true,
        markers: true,
        pings: true,
        sigint: false,
        intel: false,
        air: true,
        sse: false,
        elevation: true,
        route: true,
        tactical: true,
        recon: true,
      },
      affiliations: {
        friend: true,
        hostile: true,
        unknown: true,
        neutral: true,
      },
    };

    var map = null;
    var currentBaseLayer = null;
    var layerGroups = {};
    var intervals = [];
    var mapIntervals = [];
    var lastUnits = [];
    var lastUnitsRaw = [];
    var selectedUnitId = null;
    var syncLock = false;
    var trailTracker = createUnitTrailTracker({ maxPoints: TRAIL_MAX_POINTS });
    var terrainTools = null;
    var routeTools = null;

    function getEl(id) {
      return typeof id === 'string' ? document.getElementById(id) : id;
    }

    function getMissionId() {
      return 'mission_' + Number(tenantId) + '_map_' + Number(state.currentMapId);
    }

    function invalidateSize() {
      if (map) {
        try { map.invalidateSize(true); } catch (e) {}
      }
    }
    lastTacmapInvalidate = invalidateSize;
    function applyLayerVisibility() {
      if (!map) return;
      var Ls = state.layers;
      [['units', layerGroups.units], ['trails', layerGroups.trails], ['danger', layerGroups.dangerZones], ['drawings', layerGroups.drawings],
        ['markers', layerGroups.markers], ['pings', layerGroups.pings], ['sigint', layerGroups.sigint],
        ['intel', layerGroups.intel], ['air', layerGroups.air], ['sse', layerGroups.sse],
        ['elevation', layerGroups.elevation], ['route', layerGroups.route],
        ['tactical', layerGroups.tactical], ['recon', layerGroups.recon]].forEach(function (pair) {
        var key = pair[0];
        var lg = pair[1];
        if (!lg) return;
        try {
          if (Ls[key]) map.addLayer(lg);
          else map.removeLayer(lg);
        } catch (e) {}
      });
    }

    function clearTrails() {
      trailTracker.clear();
      if (layerGroups.trails) layerGroups.trails.clearLayers();
    }

    function pushTrailPoint(unitKey, latlng) {
      if (!trailsEnabled || !unitKey || !latlng) return;
      trailTracker.push(unitKey, latlng);
    }

    function renderTrails() {
      trailTracker.render(layerGroups.trails, !!state.layers.trails, trailColorFromCss());
    }

    function unitHealthRaw(u) {
      var extra = {};
      try {
        if (typeof u.extra === 'string') extra = JSON.parse(u.extra || '{}');
        else if (u.extra && typeof u.extra === 'object') extra = u.extra;
      } catch (e) {}
      return extra.health != null ? extra.health : u.health;
    }

    function isCriticalHealth(raw) {
      var x = String(raw || '').toLowerCase().trim();
      return x === 'unconscious' || x === 'cardiac_arrest' || x === 'cardiac-arrest' || x === 'dead' || x === 'kia';
    }

    function renderMedicalPanel(units, chatAlerts) {
      var root = getEl(els.medicalList);
      if (!root || !medicalPanelEnabled) return;
      var windowMs = (window.ATAKMedicalAlerts && window.ATAKMedicalAlerts.ACTIVE_WINDOW_MS)
        ? window.ATAKMedicalAlerts.ACTIVE_WINDOW_MS
        : (30 * 60 * 1000);
      var now = Date.now();
      function withinWindow(ts) {
        if (!ts) return true;
        var t = Date.parse(String(ts).replace(' ', 'T'));
        if (isNaN(t)) return true;
        return (now - t) < windowMs;
      }
      var critical = (units || []).filter(function (u) {
        if (!isCriticalHealth(unitHealthRaw(u))) return false;
        return withinWindow(u.updated_at || u.created_at);
      });
      var alerts = (Array.isArray(chatAlerts) ? chatAlerts : []).filter(function (a) {
        if (a && a.triage && a.triage.is_resolved) return false;
        var st = a && a.triage && a.triage.status ? String(a.triage.status) : '';
        if (st === 'traite' || st === 'kia' || st === 'annule') return false;
        return withinWindow(a && a.created_at);
      });
      if (window.ATAKMedicalAlerts && typeof window.ATAKMedicalAlerts.collapseAlertsByCallsign === 'function') {
        alerts = window.ATAKMedicalAlerts.collapseAlertsByCallsign(alerts);
      }
      if (!critical.length && !alerts.length) {
        root.innerHTML = '<p class="text-sm" style="color:var(--tm-muted)">Aucune urgence détectée pour l’instant.</p>';
        return;
      }
      var html = '';
      if (critical.length) {
        html += critical.map(function (u) {
          var raw = unitHealthRaw(u);
          var label = (window.ATAKUnitPopup && window.ATAKUnitPopup.healthLabelFr)
            ? window.ATAKUnitPopup.healthLabelFr(raw)
            : String(raw || 'Urgence');
          return '<button type="button" class="tacmap-assist-item is-critical" data-unit-id="' + u.id + '">' +
            '<div class="tacmap-assist-item__title">' + escapeHtml(u.call_sign || '—') + ' — ' + escapeHtml(label) + '</div>' +
            '<div class="tacmap-assist-item__meta">' + escapeHtml(u.role || '—') + ' · Grille ' + escapeHtml(u.grid_ref || '—') + '</div>' +
            '</button>';
        }).join('');
      }
      if (alerts.length) {
        html += alerts.slice().reverse().slice(0, 12).map(function (a) {
          var sev = (a.severity === 'critical') ? 'is-critical' : 'is-attention';
          var triageLabel = (a.triage && a.triage.status_label) ? String(a.triage.status_label) : 'À secourir';
          return '<div class="tacmap-assist-item ' + sev + '">' +
            '<div class="tacmap-assist-item__title">' + escapeHtml(a.summary || a.label || 'Assistance médicale') +
            ' · ' + escapeHtml(triageLabel) + '</div>' +
            '<div class="tacmap-assist-item__meta">' + escapeHtml(a.created_at ? String(a.created_at).replace('T', ' ').substring(0, 19) : '') +
            (a.grid ? ' · Grille ' + escapeHtml(a.grid) : '') + '</div>' +
            '</div>';
        }).join('');
      }
      root.innerHTML = html;
      root.querySelectorAll('[data-unit-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = parseInt(btn.getAttribute('data-unit-id'), 10);
          var u = lastUnits.find(function (x) { return x.id === id; });
          selectUnit(u);
        });
      });
    }

    function fetchMedicalChatAlerts() {
      return fetch(apiBase + '/atak/medical-alerts?mapId=' + encodeURIComponent(state.currentMapId) + '&limit=30', { credentials: 'include' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) { return (data && data.alerts) ? data.alerts : []; })
        .catch(function () { return []; });
    }

    function deleteFeatureByKind(kind, id) {
      var url = '';
      if (kind === 'shape') url = apiBase + '/map-shapes/' + encodeURIComponent(id);
      else if (kind === 'marker') url = apiBase + '/markers/' + encodeURIComponent(id);
      else if (kind === 'ping') url = apiBase + '/pings/' + encodeURIComponent(id);
      else if (kind === 'danger') url = apiBase + '/danger-zones/' + encodeURIComponent(id) + '?missionId=' + encodeURIComponent(getMissionId());
      else return;
      fetch(url, { method: 'DELETE', credentials: 'include' })
        .then(function (r) {
          if (!r.ok) throw new Error('fail');
          refreshSecondaryLayers();
        })
        .catch(function () {
          window.alert('Impossible de supprimer cet élément pour le moment.');
        });
    }

    function onTacmapFeatureContextMenu(payload) {
      if (!payload || payload.id == null) return;
      var msg = 'Retirer cet élément de la carte ?';
      if (payload.kind === 'shape') msg = 'Retirer ce tracé de la carte ?';
      else if (payload.kind === 'marker') msg = 'Retirer ce repère de la carte ?';
      else if (payload.kind === 'ping') msg = 'Supprimer ce ping ?';
      else if (payload.kind === 'danger') msg = 'Retirer cette zone de la carte ?';
      if (!window.confirm(msg)) return;
      deleteFeatureByKind(payload.kind, payload.id);
    }

    function loadDangerZones() {
      if (!layerGroups.dangerZones || (state.currentMapType !== 'arma' && state.currentMapType !== 'image')) return;
      fetch(apiBase + '/danger-zones?missionId=' + encodeURIComponent(getMissionId()), { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (zones) {
          layerGroups.dangerZones.clearLayers();
          (zones || []).forEach(function (z) {
            var geom = z.geometry_json || z.geometry;
            var type = z.geometry_type || 'CIRCLE';
            if (type === 'CIRCLE' && geom && geom.center && geom.radius) {
              var lat = geom.center[1];
              var lng = geom.center[0];
              var radius = geom.radius;
              var label = z.label || z.zone_type || 'Zone';
              var layer = L.circle([lat, lng], { radius: radius, color: z.color || '#ef4444', fillOpacity: z.fill_opacity || 0.25 });
              layer.bindPopup(label);
              bindDeletableLayer(layer, { kind: 'danger', id: z.id, label: label }, onTacmapFeatureContextMenu);
              layer.addTo(layerGroups.dangerZones);
            }
          });
        })
        .catch(function () {});
    }

    function refreshSecondaryLayers() {
      if (state.currentMapType !== 'arma' && state.currentMapType !== 'image') return;
      var isWorld = false;
      if (state.layers.drawings) {
        renderMapShapes({
          apiBase: apiBase,
          mapId: state.currentMapId,
          missionId: getMissionId(),
          map: map,
          layerGroup: layerGroups.drawings,
          isWorld: isWorld,
          credentials: 'include',
          onFeatureContextMenu: onTacmapFeatureContextMenu,
        });
      }
      if (state.layers.markers) {
        fetch(apiBase + '/markers?mapId=' + encodeURIComponent(state.currentMapId), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (list) {
            renderAtakMarkers(layerGroups.markers, list, isWorld, onTacmapFeatureContextMenu, lastUnitsRaw);
          })
          .catch(function () {});
      }
      if (state.layers.pings) {
        fetch(apiBase + '/pings?mapId=' + encodeURIComponent(state.currentMapId) + '&limit=80', { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (rows) { renderPingsLayer(layerGroups.pings, rows, isWorld); })
          .catch(function () {});
      }
      if (state.layers.sigint) {
        fetch(apiBase + '/atak/sigint/zones?mapId=' + encodeURIComponent(state.currentMapId), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (zones) { renderSigintLayer(layerGroups.sigint, zones, isWorld); })
          .catch(function () {});
      }
      if (state.layers.intel) {
        fetch(apiBase + '/intel/fused?missionId=' + encodeURIComponent(getMissionId()), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (reports) { renderIntelFusedMarkers(layerGroups.intel, reports, isWorld); })
          .catch(function () {});
      }
      if (state.layers.sse) {
        fetch(apiBase + '/atak/sse-case-overlay?mapId=' + encodeURIComponent(state.currentMapId), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (payload) { renderSseCaseOverlay(layerGroups.sse, payload, isWorld); })
          .catch(function () {});
      }
      if (state.layers.air) {
        fetch(apiBase + '/atak/air-assets?mapId=' + encodeURIComponent(state.currentMapId), { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (assets) { renderAirAssetsLayer(layerGroups.air, assets, isWorld); })
          .catch(function () {});
      }
      if (state.layers.danger) loadDangerZones();
      if (state.layers.tactical) refreshTacticalPanel();
      else if (layerGroups.tactical) layerGroups.tactical.clearLayers();
      if (state.layers.recon) refreshReconPanel();
      else if (layerGroups.recon) layerGroups.recon.clearLayers();
    }

    function renderTacticalMarkers(alerts) {
      if (!layerGroups.tactical || !map) return;
      layerGroups.tactical.clearLayers();
      if (!state.layers.tactical) return;
      (alerts || []).forEach(function (a) {
        if (!window.TacmapTacticalAlerts || !window.TacmapTacticalAlerts.hasMapPos(a)) return;
        var x = parseFloat(a.pos_x);
        var y = parseFloat(a.pos_y);
        var latlng = L.latLng(y, x);
        var kind = String(a.kind || '').toLowerCase();
        var color = kind === 'eagle_down' || kind === 'tic' ? '#ef4444'
          : (kind === 'bda' ? '#ea580c' : (kind === 'tic_clear' ? '#22c55e' : '#f59e0b'));
        var label = escapeHtml(a.kind_label || 'Alerte');
        var icon = L.divIcon({
          className: 'tacmap-talert-marker',
          html: '<span style="display:inline-flex;align-items:center;justify-content:center;min-width:1.6rem;height:1.6rem;padding:0 4px;border-radius:999px;background:' +
            color + ';color:#fff;font-size:9px;font-weight:700;box-shadow:0 0 0 2px rgba(0,0,0,.35);">' +
            (kind === 'bda' ? 'BDA' : (kind === 'eagle_down' ? '!' : 'S')) + '</span>',
          iconSize: [28, 28],
          iconAnchor: [14, 14],
        });
        var popup = '<strong>' + label + '</strong><br/>' +
          escapeHtml(a.call_sign || a.author || '') +
          (a.grid ? '<br/>Grille ' + escapeHtml(a.grid) : '') +
          (a.summary ? '<br/>' + escapeHtml(String(a.summary).slice(0, 160)) : '');
        L.marker(latlng, { icon: icon, zIndexOffset: 350 })
          .bindPopup(popup)
          .addTo(layerGroups.tactical);
      });
    }

    function renderReconMarkers(photos) {
      if (!layerGroups.recon || !map) return;
      layerGroups.recon.clearLayers();
      if (!state.layers.recon) return;
      var origin = String(apiBase || '').replace(/\/$/, '').replace(/\/api(?:\/atak)?$/, '');
      (photos || []).forEach(function (p) {
        if (!window.TacmapRecon || !window.TacmapRecon.hasPos(p)) return;
        var x = parseFloat(p.pos_x);
        var y = parseFloat(p.pos_y);
        var latlng = L.latLng(y, x);
        var src = window.TacmapRecon.resolveUrl(apiBase, p);
        if (src && src.charAt(0) === '/' && origin) src = origin + src;
        var author = escapeHtml(p.author_callsign || p.author || 'Recon');
        var icon = L.divIcon({
          className: 'tacmap-recon-marker',
          html: '<span style="display:inline-block;width:1.5rem;height:1.5rem;border-radius:4px;background:#0ea5e9;border:2px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.4);"></span>',
          iconSize: [24, 24],
          iconAnchor: [12, 12],
        });
        var html = '<strong>Photo terrain</strong><br/>' + author +
          (p.grid_ref || p.grid ? '<br/>Grille ' + escapeHtml(p.grid_ref || p.grid) : '') +
          (src ? '<br/><img src="' + escapeHtml(src) + '" alt="" style="max-width:160px;max-height:100px;margin-top:6px;border-radius:4px;" />' : '');
        L.marker(latlng, { icon: icon, zIndexOffset: 340 })
          .bindPopup(html)
          .addTo(layerGroups.recon);
      });
    }

    function focusMapPos(x, y) {
      if (!map || isNaN(x) || isNaN(y)) return;
      try {
        map.setView(L.latLng(y, x), Math.max(map.getZoom(), 5), { animate: true });
      } catch (e) {}
    }

    function updateSyncBadge() {
      var el = getEl(els.syncBadge);
      if (!el) return;
      el.className = 'tacmap-badge';
      if (state.currentMapType === 'world') {
        el.textContent = 'Vue monde';
        return;
      }
      if (state.syncStatus === 'ok') {
        el.textContent = 'Synchronisé';
        el.classList.add('is-ok');
      } else if (state.syncStatus === 'error') {
        el.textContent = 'Problème de liaison';
        el.classList.add('is-err');
      } else if (state.syncStatus === 'syncing') {
        el.textContent = 'Mise à jour…';
      } else {
        el.textContent = 'En attente';
      }
    }

    function updateTheatreLabel() {
      var el = getEl(els.theatreLabel);
      if (!el) return;
      var slug = state.currentMapSlug;
      var c = mapsConfigs[slug];
      el.textContent = c && c.label ? c.label : (slug === 'world' ? 'Monde' : slug || '—');
    }

    function updateUnitCountEl() {
      var el = getEl(els.unitCount);
      if (el) el.textContent = (state.currentMapType === 'arma' || state.currentMapType === 'image') ? String(state.unitsCount) : '—';
      el = getEl(els.syncMeta);
      if (el && state.lastSyncAt && (state.currentMapType === 'arma' || state.currentMapType === 'image')) {
        el.textContent = new Date(state.lastSyncAt).toLocaleTimeString('fr-FR', { hour12: false }) + ' · ' + state.unitsCount + ' position(s)';
      } else if (el) el.textContent = state.currentMapType === 'world' ? 'Vue d’ensemble' : '—';
    }

    function selectUnit(u) {
      if (!u) {
        selectedUnitId = null;
        renderDetail(null);
        renderRosterHighlight();
        return;
      }
      selectedUnitId = u.id;
      renderDetail(u);
      renderRosterHighlight();
      var gridRef = (u.grid_ref || '').trim().split(/\s+/);
      var x = parseFloat(gridRef[0]);
      var y = parseFloat(gridRef[1]);
      if (!isNaN(x) && !isNaN(y) && map && (state.currentMapType === 'arma' || state.currentMapType === 'image')) {
        map.setView([y, x], Math.max(map.getZoom(), 4));
      }
    }

    function renderDetail(u) {
      var root = getEl(els.detailRoot);
      if (!root) return;
      if (!u) {
        root.innerHTML = '<p class="text-sm text-slate-500">Sélectionnez une unité dans la liste ou sur la carte.</p>';
        return;
      }
      var extra = {};
      try {
        if (typeof u.extra === 'string') extra = JSON.parse(u.extra || '{}');
        else if (u.extra && typeof u.extra === 'object') extra = u.extra;
      } catch (e) {}
      var aff = extra.affiliation || extra.affil || u.affiliation || '';
      var affLabel = aff ? affiliationLabelFr(aff) : '';
      var healthRaw = extra.health != null ? extra.health : u.health;
      var healthLabel = '';
      if (window.ATAKUnitPopup && window.ATAKUnitPopup.healthLabelFr) {
        healthLabel = window.ATAKUnitPopup.healthLabelFr(healthRaw);
      } else if (healthRaw) {
        healthLabel = String(healthRaw);
      }
      var radio = extra.radio_freq != null && extra.radio_freq !== ''
        ? String(extra.radio_freq)
        : (extra.radio != null && extra.radio !== '' ? String(extra.radio) : '');
      var fuel = extra.fuel !== undefined && extra.fuel !== null && extra.fuel !== ''
        ? String(extra.fuel) + (String(extra.fuel).indexOf('%') >= 0 ? '' : ' %')
        : '';
      var ammo = extra.ammo != null && extra.ammo !== '' && String(extra.ammo).toLowerCase() !== 'n/a'
        ? String(extra.ammo)
        : '';
      var parent = extra.parent || extra.parent_callsign || extra.group || extra.groupe || '';
      root.innerHTML =
        '<div class="space-y-4">' +
        '<div><p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-400 mb-1">Indicatif</p><p class="text-xl font-black text-slate-950">' + escapeHtml(u.call_sign || '—') + '</p></div>' +
        '<dl class="space-y-2 text-sm">' +
        rowDl('Rôle', u.role || '—') +
        rowDl('Grille (approx.)', u.grid_ref || '—') +
        rowDl('Cap', u.heading != null ? String(u.heading) + '°' : '—') +
        rowDl('Liaison', statusLabelFr(u.status)) +
        (healthLabel ? rowDl('État', healthLabel) : '') +
        rowDl('Dernière mise à jour', formatTimeAgo(u.updated_at)) +
        (affLabel ? rowDl('Affiliation', affLabel) : '') +
        (parent ? rowDl('Groupe', parent) : '') +
        (radio ? rowDl('Radio', radio) : '') +
        (fuel ? rowDl('Carburant', fuel) : '') +
        (ammo ? rowDl('Munitions', ammo) : '') +
        '</dl>' +
        (extra.notes ? '<p class="text-xs text-slate-600">' + escapeHtml(String(extra.notes)) + '</p>' : '') +
        '</div>';
    }

    function rowDl(dt, dd) {
      return '<div class="flex justify-between gap-3"><dt class="text-slate-500">' + escapeHtml(dt) + '</dt><dd class="font-bold text-slate-950 text-right">' + escapeHtml(dd) + '</dd></div>';
    }

    function escapeHtml(s) {
      return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderRosterHighlight() {
      var roster = getEl(els.roster);
      if (!roster) return;
      roster.querySelectorAll('[data-unit-id]').forEach(function (node) {
        var id = node.getAttribute('data-unit-id');
        if (selectedUnitId && String(selectedUnitId) === id) {
          node.classList.add('is-selected');
        } else {
          node.classList.remove('is-selected');
        }
      });
    }

    function filterRosterQuery(q) {
      q = (q || '').toUpperCase().trim();
      var roster = getEl(els.roster);
      if (!roster) return;
      roster.querySelectorAll('[data-unit-id]').forEach(function (node) {
        var cs = (node.getAttribute('data-callsign') || '').toUpperCase();
        node.style.display = !q || cs.indexOf(q) >= 0 ? '' : 'none';
      });
    }

    function renderRosterAndTable(units) {
      var view = units || [];
      lastUnits = view;
      var roster = getEl(els.roster);
      var tbody = getEl(els.tableBody);
      if (roster) {
        if (!view.length) {
          roster.innerHTML = '<p class="text-sm text-slate-500 px-2">Aucune position remontée pour ce théâtre. Vérifiez la liaison en jeu ou l’outil Overwatch.</p>';
        } else {
          roster.innerHTML = view.map(function (u) {
            var st = statusLabelFr(u.status);
            var rawH = unitHealthRaw(u);
            var hLabel = '';
            if (isCriticalHealth(rawH) && window.ATAKUnitPopup && window.ATAKUnitPopup.healthLabelFr) {
              hLabel = window.ATAKUnitPopup.healthLabelFr(rawH);
            }
            return '<button type="button" data-unit-id="' + u.id + '" data-callsign="' + escapeHtml(u.call_sign || '') + '" class="tacmap-roster-btn">' +
              '<div class="flex justify-between gap-2"><span class="text-[10px] font-black uppercase" style="color:var(--tm-muted)">' + escapeHtml(u.call_sign || '—') + '</span><span class="text-[9px] font-black uppercase" style="color:var(--tm-ok)">' + escapeHtml(hLabel || st) + '</span></div>' +
              '<p class="text-sm font-bold mt-1">' + escapeHtml(u.role || '—') + '</p>' +
              '<p class="text-xs mt-1" style="color:var(--tm-muted)">Grille ' + escapeHtml(u.grid_ref || '—') + '</p></button>';
          }).join('');
          roster.querySelectorAll('[data-unit-id]').forEach(function (btn) {
            btn.addEventListener('click', function () {
              var id = parseInt(btn.getAttribute('data-unit-id'), 10);
              var u = lastUnits.find(function (x) { return x.id === id; });
              selectUnit(u);
            });
          });
        }
        renderRosterHighlight();
      }
      if (tbody) {
        if (!lastUnits.length) {
          tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Aucune unité à afficher.</td></tr>';
        } else {
          tbody.innerHTML = lastUnits.map(function (u) {
            return '<tr class="border-b border-slate-100 hover:bg-slate-50 cursor-pointer" data-unit-id="' + u.id + '">' +
              '<td class="px-6 py-4 font-bold text-blue-700">' + escapeHtml(u.call_sign || '—') + '</td>' +
              '<td class="px-6 py-4">' + escapeHtml(u.role || '—') + '</td>' +
              '<td class="px-6 py-4 text-center"><span class="text-xs font-bold">' + escapeHtml(statusLabelFr(u.status)) + '</span></td>' +
              '<td class="px-6 py-4 text-center text-sm">' + escapeHtml(u.heading != null ? String(u.heading) + '°' : '—') + '</td>' +
              '<td class="px-6 py-4 text-right font-mono text-xs text-slate-500">' + escapeHtml(u.grid_ref || '—') + '</td></tr>';
          }).join('');
          tbody.querySelectorAll('tr[data-unit-id]').forEach(function (tr) {
            tr.addEventListener('click', function () {
              var id = parseInt(tr.getAttribute('data-unit-id'), 10);
              selectUnit(lastUnits.find(function (x) { return x.id === id; }));
            });
          });
        }
      }
      filterRosterQuery(getEl(els.rosterSearch) && getEl(els.rosterSearch).value);
    }

    function unitAffiliation(u) {
      var extra = {};
      try {
        if (typeof u.extra === 'string') extra = JSON.parse(u.extra || '{}');
        else if (u.extra && typeof u.extra === 'object') extra = u.extra;
      } catch (e) {}
      var aff = String(extra.affiliation || extra.affil || u.affiliation || 'friend').toLowerCase();
      var side = String(extra.side || u.side || '').toUpperCase();
      if (side === 'EAST') aff = 'hostile';
      else if (side === 'GUER') aff = 'unknown';
      else if (side === 'CIV') aff = 'neutral';
      else if (side === 'WEST') aff = 'friend';
      if (aff === 'hostile' || aff === 'enemy' || aff === 'east') return 'hostile';
      if (aff === 'unknown' || aff === 'guer' || aff === 'independent') return 'unknown';
      if (aff === 'neutral' || aff === 'civ' || aff === 'civilian') return 'neutral';
      return 'friend';
    }

    function filterUnitsByAffiliation(units) {
      var aff = state.affiliations || {};
      return (units || []).filter(function (u) {
        var a = unitAffiliation(u);
        if (a === 'hostile') return !!aff.hostile;
        if (a === 'unknown') return !!aff.unknown;
        if (a === 'neutral') return !!aff.neutral;
        return !!aff.friend;
      });
    }

    /** Retire les fantômes compte/alerte encore présents dans le payload. */
    function dedupeUnitsForMap(units) {
      var list = Array.isArray(units) ? units.slice() : [];
      if (list.length < 2) return list;
      function parseExtra(u) {
        try {
          if (typeof u.extra === 'string') return JSON.parse(u.extra || '{}') || {};
          if (u.extra && typeof u.extra === 'object') return u.extra;
        } catch (e) {}
        return {};
      }
      function steamOf(u) {
        var ex = parseExtra(u);
        return String(ex.steam_uid || ex.steamId || ex.player_uid || '').trim().toLowerCase();
      }
      function statusOf(u) {
        return String((u && u.status) || '').toLowerCase();
      }
      function isLive(u) {
        var s = statusOf(u);
        return s === 'linked' || s === 'delayed';
      }
      function isGhost(u) {
        var ex = parseExtra(u);
        var src = String(ex.source || '');
        if (src === 'medical_chat' || src === 'tactical_alert') return true;
        var role = String(u.role || ex.role || '').toLowerCase();
        var steam = steamOf(u);
        var hasTelemetry = !!(ex.ammo || ex.radio || ex.radio_freq || ex.fuel || ex.steam_uid || ex.steamId);
        // Compte Athena (ex. Newp1) : rôle générique, sans télémétrie / sans Steam.
        if (role === 'operator' && !hasTelemetry && steam === '') return true;
        var health = String(ex.health || u.health || '').toLowerCase();
        if (statusOf(u) === 'offline' && role === 'operator' &&
          (health === 'unconscious' || health === 'cardiac_arrest' || health === 'cardiac-arrest' || health === 'dead' || health === 'kia')) {
          return true;
        }
        return false;
      }
      function score(u) {
        var s = 0;
        if (statusOf(u) === 'linked') s += 40;
        else if (statusOf(u) === 'delayed') s += 10;
        if (steamOf(u)) s += 25;
        if (isGhost(u)) s -= 30;
        var ex = parseExtra(u);
        var role = String(u.role || ex.role || '').toLowerCase();
        if (role && role !== 'operator') s += 20;
        else if (role === 'operator') s -= 5;
        if (ex.ammo || ex.radio || ex.radio_freq || ex.fuel) s += 15;
        return s;
      }
      function nearOf(a, b, maxDist) {
        var ax = a.pos_x != null ? parseFloat(a.pos_x) : NaN;
        var ay = a.pos_y != null ? parseFloat(a.pos_y) : NaN;
        var bx = b.pos_x != null ? parseFloat(b.pos_x) : NaN;
        var by = b.pos_y != null ? parseFloat(b.pos_y) : NaN;
        if (isNaN(ax) || isNaN(ay) || isNaN(bx) || isNaN(by)) return false;
        var dx = ax - bx;
        var dy = ay - by;
        return (dx * dx + dy * dy) <= (maxDist * maxDist);
      }
      function preferA(a, b) {
        if (isLive(a) && !isLive(b)) return true;
        if (isLive(b) && !isLive(a)) return false;
        if (isGhost(a) && !isGhost(b)) return false;
        if (isGhost(b) && !isGhost(a)) return true;
        return score(a) >= score(b);
      }
      var drop = {};
      var i, j;
      for (i = 0; i < list.length; i++) {
        if (drop[i]) continue;
        for (j = i + 1; j < list.length; j++) {
          if (drop[j]) continue;
          var a = list[i];
          var b = list[j];
          var sa = steamOf(a);
          var sb = steamOf(b);
          var sameSteam = !!(sa && sa === sb);
          var nearClose = nearOf(a, b, 120);
          var nearWide = nearOf(a, b, 400);
          var ga = isGhost(a);
          var gb = isGhost(b);
          // Même Steam → une seule ligne.
          if (sameSteam) {
            if (preferA(a, b)) drop[j] = true;
            else drop[i] = true;
            continue;
          }
          // Fantôme compte/alerte à proximité d’un BFT.
          if (nearWide && (ga || gb)) {
            if (preferA(a, b)) drop[j] = true;
            else drop[i] = true;
            continue;
          }
          // Très proche : garder le contact le plus « réel ».
          if (nearClose) {
            if (preferA(a, b)) drop[j] = true;
            else drop[i] = true;
          }
        }
      }
      return list.filter(function (_u, idx) { return !drop[idx]; });
    }

    function renderUnitsOnMap(units) {
      if (!layerGroups.units || !map) return;
      layerGroups.units.clearLayers();
      var nato = window.NatoSidcIcons;
      // Liste déjà dédoublonnée / filtrée — hors liaison : pas de marqueur fantôme.
      var filtered = (units || []).filter(function (u) {
        var st = String((u && u.status) || '').toLowerCase();
        return st === 'linked' || st === 'delayed';
      });
      filtered.forEach(function (u) {
        var x = u.pos_x != null && u.pos_x !== '' ? parseFloat(u.pos_x) : NaN;
        var y = u.pos_y != null && u.pos_y !== '' ? parseFloat(u.pos_y) : NaN;
        if (isNaN(x) || isNaN(y)) {
          var gridRaw = String(u.grid_ref || '').trim();
          var gridParts = gridRaw.split(/\s+/).filter(Boolean);
          if (gridParts.length >= 2) {
            x = parseFloat(gridParts[0]);
            y = parseFloat(gridParts[1]);
          } else {
            // Grille Arma compacte (ex. 099153) → approx. monde 100 m.
            var digits = gridRaw.replace(/\D+/g, '');
            if (digits.length >= 6 && (digits.length % 2) === 0) {
              var half = digits.length / 2;
              var east = parseInt(digits.slice(0, half), 10);
              var north = parseInt(digits.slice(half), 10);
              var cell = half === 4 ? 10 : (half === 5 ? 1 : 100);
              x = (east * cell) + (cell / 2);
              y = (north * cell) + (cell / 2);
            }
          }
        }
        if (isNaN(x) || isNaN(y) || (Math.abs(x) < 0.5 && Math.abs(y) < 0.5)) return;
        var latlng = L.latLng(y, x);
        var unitKey = String(u.id != null ? u.id : (u.call_sign || Math.random()));
        pushTrailPoint(unitKey, latlng);
        var extra = {};
        try {
          if (typeof u.extra === 'string') extra = JSON.parse(u.extra || '{}');
          else if (u.extra && typeof u.extra === 'object') extra = u.extra;
        } catch (e) {}
        var aff = unitAffiliation(u);
        var health = String(extra.health || u.health || '').toLowerCase();
        var icon = nato && nato.leafletDivIcon
          ? nato.leafletDivIcon(L, {
              affiliation: aff,
              role: u.role || extra.role || '',
              sidc: extra.sidc || u.sidc || '',
              platform: extra.platform || extra.vehicle_class || '',
              vehicle: extra.vehicle || extra.vehicle_type || extra.vehicle_name || extra.model || '',
              vehicle_class: extra.vehicle_class || '',
              in_vehicle: extra.in_vehicle,
              aircraftType: extra.aircraft_type || u.aircraft_type || '',
              callSign: u.call_sign || '',
              heading: u.heading,
              showLabel: true,
              size: 34,
              health: health,
            })
          : L.divIcon({
              className: 'tacmap-unit-icon',
              html: '<span style="display:inline-block;padding:2px 6px;background:' + affiliationColor(aff) + ';color:#fff;font-size:10px;">' + escapeHtml(u.call_sign || '?') + '</span>',
              iconSize: [80, 24],
              iconAnchor: [40, 12],
            });
        var marker = L.marker(latlng, { icon: icon, zIndexOffset: 400 });
        if (window.ATAKUnitPopup && window.ATAKUnitPopup.bindUnit) {
          window.ATAKUnitPopup.bindUnit(marker, u);
        } else {
          marker.bindPopup('<strong>' + escapeHtml(u.call_sign || '—') + '</strong><br/>' + escapeHtml(u.role || ''));
        }
        marker.on('click', function () { selectUnit(u); });
        marker.addTo(layerGroups.units);
      });
      renderTrails();
    }

    function syncUnits() {
      if ((state.currentMapType !== 'arma' && state.currentMapType !== 'image') || !state.layers.units) return;
      if (syncLock) return;
      syncLock = true;
      state.syncStatus = 'syncing';
      updateSyncBadge();
      fetch(apiBase + '/units?mapId=' + encodeURIComponent(state.currentMapId), { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (rows) {
          syncLock = false;
          state.syncStatus = 'ok';
          state.lastSyncAt = Date.now();
          var list = Array.isArray(rows) ? rows : (rows && Array.isArray(rows.units) ? rows.units : []);
          lastUnitsRaw = dedupeUnitsForMap(list);
          var filtered = filterUnitsByAffiliation(lastUnitsRaw);
          state.unitsCount = filtered.length;
          renderUnitsOnMap(filtered);
          renderRosterAndTable(filtered);
          // Repères carte : recharger pour masquer ceux qui doublonnent un BFT.
          if (state.layers.markers && layerGroups.markers) {
            fetch(apiBase + '/markers?mapId=' + encodeURIComponent(state.currentMapId), { credentials: 'include' })
              .then(function (r) { return r.json(); })
              .then(function (mlist) {
                renderAtakMarkers(layerGroups.markers, mlist, false, onTacmapFeatureContextMenu, lastUnitsRaw);
              })
              .catch(function () {});
          }
          if (medicalPanelEnabled) {
            fetchMedicalChatAlerts().then(function (alerts) {
              renderMedicalPanel(filtered, alerts);
            });
          } else {
            renderMedicalPanel(filtered, []);
          }
          refreshTacticalPanel();
          updateUnitCountEl();
          updateSyncBadge();
        })
        .catch(function () {
          syncLock = false;
          state.syncStatus = 'error';
          updateSyncBadge();
        });
    }

    function applyBaseLayer(slug) {
      var container = getEl(cfg.containerId);
      if (!container) return;
      var kind = mapKindFromSlug(slug, mapsConfigs);
      var isWorld = kind === 'world';
      var isImage = kind === 'image';

      if (map) {
        if (currentBaseLayer) {
          map.removeLayer(currentBaseLayer);
          currentBaseLayer = null;
        }
        var needRecreate = state.currentMapType !== kind;
        if (needRecreate) {
          mapIntervals.forEach(function (id) { clearInterval(id); });
          mapIntervals = [];
          map.remove();
          map = null;
          layerGroups = {};
        }
      }

      if (!map) {
        if (isWorld) {
          map = L.map(container, { minZoom: 2, maxZoom: 18 }).setView([0.5, 0.5], 4);
        } else if (isImage) {
          var ic0 = mapsConfigs[slug] ? buildImageConfig(mapsConfigs[slug]) : null;
          if (!ic0) return;
          map = L.map(container, { minZoom: ic0.minZoom, maxZoom: ic0.maxZoom, crs: ic0.CRS });
          map.setView(ic0.center, ic0.defaultZoom);
          if (ic0.bounds && ic0.bounds.length === 2) {
            map.setMaxBounds(L.latLngBounds(L.latLng(ic0.bounds[0][0], ic0.bounds[0][1]), L.latLng(ic0.bounds[1][0], ic0.bounds[1][1])));
          }
        } else {
          var ac = mapsConfigs[slug] ? buildArmaConfig(mapsConfigs[slug]) : null;
          if (!ac) return;
          map = L.map(container, { minZoom: ac.minZoom, maxZoom: ac.maxZoom, crs: ac.CRS });
          map.setView(ac.center, ac.defaultZoom);
          if (ac.bounds && ac.bounds.length === 2) {
            map.setMaxBounds(L.latLngBounds(L.latLng(ac.bounds[0][0], ac.bounds[0][1]), L.latLng(ac.bounds[1][0], ac.bounds[1][1])));
          }
        }
        layerGroups.units = L.layerGroup();
        layerGroups.trails = L.layerGroup();
        layerGroups.dangerZones = L.layerGroup();
        layerGroups.drawings = L.layerGroup();
        layerGroups.markers = L.layerGroup();
        layerGroups.pings = L.layerGroup();
        layerGroups.sigint = L.layerGroup();
        layerGroups.intel = L.layerGroup();
        layerGroups.air = L.layerGroup();
        layerGroups.sse = L.layerGroup();
        layerGroups.elevation = L.layerGroup();
        layerGroups.route = L.layerGroup();
        layerGroups.tactical = L.layerGroup();
        layerGroups.recon = L.layerGroup();
        map.on('contextmenu', function (e) {
          if (L.DomEvent) L.DomEvent.preventDefault(e);
          if (e.originalEvent) e.originalEvent.preventDefault();
        });
        applyLayerVisibility();
        bindAnalysisTools();
      }

      clearTrails();
      setTimeout(invalidateSize, 80);
      setTimeout(invalidateSize, 250);

      if (isWorld) {
        var wSpec = worldTileSpec(slug);
        currentBaseLayer = L.tileLayer(wSpec.url, { attribution: wSpec.attribution, maxZoom: wSpec.maxZoom });
        currentBaseLayer.addTo(map);
        map.setView([46.6, 2.4], 6);
        state.currentMapType = 'world';
      } else if (isImage) {
        var ic = mapsConfigs[slug] ? buildImageConfig(mapsConfigs[slug]) : null;
        if (!ic) return;
        var ibounds = L.latLngBounds(L.latLng(ic.bounds[0][0], ic.bounds[0][1]), L.latLng(ic.bounds[1][0], ic.bounds[1][1]));
        currentBaseLayer = L.imageOverlay(ic.imageUrl, ibounds, { opacity: 1, interactive: false });
        currentBaseLayer.addTo(map);
        map.fitBounds(ibounds);
        state.currentMapType = 'image';
      } else {
        var cfgM = mapsConfigs[slug] ? buildArmaConfig(mapsConfigs[slug]) : null;
        if (!cfgM) return;
        currentBaseLayer = L.tileLayer(cfgM.tilePattern, { attribution: cfgM.attribution, tileSize: cfgM.tileSize });
        currentBaseLayer.addTo(map);
        map.setView(cfgM.center, cfgM.defaultZoom);
        if (cfgM.bounds && cfgM.bounds.length === 2) {
          map.setMaxBounds(L.latLngBounds(L.latLng(cfgM.bounds[0][0], cfgM.bounds[0][1]), L.latLng(cfgM.bounds[1][0], cfgM.bounds[1][1])));
        }
        state.currentMapType = 'arma';
      }
      state.currentMapSlug = slug;
      updateTheatreLabel();
      updateSyncBadge();

      mapIntervals.forEach(function (id) { clearInterval(id); });
      mapIntervals = [];
      if (!isWorld) {
        mapIntervals.push(setInterval(syncUnits, syncMs));
        mapIntervals.push(setInterval(refreshSecondaryLayers, Math.max(syncMs, 12000)));
        syncUnits();
        refreshSecondaryLayers();
      } else {
        state.unitsCount = 0;
        if (layerGroups.units) layerGroups.units.clearLayers();
        clearTrails();
        renderRosterAndTable([]);
        renderMedicalPanel([]);
        updateUnitCountEl();
      }
      applyLayerVisibility();
      setTimeout(invalidateSize, 100);
    }

    function setWorkspace(mapId) {
      mapId = parseInt(mapId, 10);
      var ws = workspaces.find(function (w) { return w.mapId === mapId; });
      state.currentMapId = mapId;
      state.currentMissionId = getMissionId();
      state.currentMapSlug = ws ? ws.slug : state.currentMapSlug;
      var selMap = getEl(els.mapSelect);
      if (selMap && ws && selMap.value !== ws.slug) {
        selMap.value = ws.slug;
        applyBaseLayer(ws.slug);
        } else if ((state.currentMapType === 'arma' || state.currentMapType === 'image') && ws) {
          applyBaseLayer(ws.slug);
        } else {
          if (ws) state.currentMapSlug = ws.slug;
          state.currentMissionId = getMissionId();
          refreshSecondaryLayers();
          syncUnits();
        }
        updateTheatreLabel();
      }

    function setMap(slug) {
      if (slug === 'world') {
        state.currentMapType = 'world';
        state.currentMapSlug = 'world';
        applyBaseLayer('world');
      } else {
        var ws = workspaces.find(function (w) { return w.slug === slug; });
        if (ws) {
          state.currentMapId = ws.mapId;
          var selWs = getEl(els.workspaceSelect);
          if (selWs && selWs.value !== String(ws.mapId)) selWs.value = ws.mapId;
        }
        state.currentMissionId = getMissionId();
        state.currentMapType = mapKindFromSlug(slug, mapsConfigs);
        applyBaseLayer(slug);
      }
      updateTheatreLabel();
    }

    function bindLayerCheckbox(id, key) {
      var el = getEl(id);
      if (!el) return;
      el.checked = !!state.layers[key];
      el.addEventListener('change', function () {
        state.layers[key] = el.checked;
        applyLayerVisibility();
        if (key === 'trails') {
          renderTrails();
          return;
        }
        if (state.currentMapType === 'arma' || state.currentMapType === 'image') {
          if (key === 'units') syncUnits();
          else refreshSecondaryLayers();
        }
      });
    }

    function refreshPlatformHealth() {
      var el = getEl(els.platformStatus);
      if (!el) return;
      fetch(apiBase + '/health', { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          el.textContent = d && d.db === 'ok' ? 'Liaison plateforme OK' : 'Plateforme indisponible';
        })
        .catch(function () { el.textContent = 'Liaison plateforme : erreur'; });
    }

    var wsSel = getEl(els.workspaceSelect);
    if (wsSel) wsSel.addEventListener('change', function () { setWorkspace(this.value); });
    var mapSel = getEl(els.mapSelect);
    if (mapSel) mapSel.addEventListener('change', function () { setMap(this.value); });

    var rs = getEl(els.rosterSearch);
    if (rs) rs.addEventListener('input', function () { filterRosterQuery(this.value); });

    bindLayerCheckbox(els.layerUnits, 'units');
    bindLayerCheckbox(els.layerTrails, 'trails');
    bindLayerCheckbox(els.layerDanger, 'danger');
    bindLayerCheckbox(els.layerDrawings, 'drawings');
    bindLayerCheckbox(els.layerMarkers, 'markers');
    bindLayerCheckbox(els.layerPings, 'pings');
    bindLayerCheckbox(els.layerSigint, 'sigint');
    bindLayerCheckbox(els.layerIntel, 'intel');
    bindLayerCheckbox(els.layerSse, 'sse');
    bindLayerCheckbox(els.layerAir, 'air');
    bindLayerCheckbox(els.layerElevation, 'elevation');
    bindLayerCheckbox(els.layerRoute, 'route');
    bindLayerCheckbox(els.layerTactical, 'tactical');
    bindLayerCheckbox(els.layerRecon, 'recon');

    function bindAffCheckbox(id, key) {
      var el = getEl(id);
      if (!el) return;
      el.checked = !!state.affiliations[key];
      el.addEventListener('change', function () {
        state.affiliations[key] = el.checked;
        if (lastUnitsRaw.length) {
          var filtered = filterUnitsByAffiliation(lastUnitsRaw);
          state.unitsCount = filtered.length;
          renderUnitsOnMap(filtered);
          renderRosterAndTable(filtered);
          updateUnitCountEl();
        }
      });
    }
    bindAffCheckbox(els.showFriend, 'friend');
    bindAffCheckbox(els.showHostile, 'hostile');
    bindAffCheckbox(els.showUnknown, 'unknown');
    bindAffCheckbox(els.showNeutral, 'neutral');

    function bindAnalysisTools() {
      if (!map) return;
      var hintEl = getEl(els.toolHint);
      var etaEl = getEl(els.toolEta);
      if (window.TacmapTerrainTools && typeof window.TacmapTerrainTools.bind === 'function') {
        terrainTools = window.TacmapTerrainTools.bind(map, layerGroups, { hintEl: hintEl });
      }
      if (window.TacmapRouteTools && typeof window.TacmapRouteTools.bind === 'function') {
        routeTools = window.TacmapRouteTools.bind(map, layerGroups, { hintEl: hintEl, etaEl: etaEl });
      }
    }

    function wireToolButtons() {
      var btnVs = getEl(els.toolViewshed);
      var btnHm = getEl(els.toolHeatmap);
      var btnRf = getEl(els.toolRouteFoot);
      var btnRv = getEl(els.toolRouteVeh);
      var btnClr = getEl(els.toolClear);
      var inpR = getEl(els.toolRadius);
      var inpS = getEl(els.toolSpeed);
      if (btnVs) btnVs.addEventListener('click', function () {
        if (terrainTools) {
          if (inpR) terrainTools.setRadiusM(inpR.value);
          terrainTools.startViewshed();
        }
      });
      if (btnHm) btnHm.addEventListener('click', function () {
        if (terrainTools) terrainTools.startHeatmap();
      });
      if (btnRf) btnRf.addEventListener('click', function () {
        if (routeTools) {
          if (inpS) routeTools.setSpeedKph(inpS.value);
          routeTools.startFoot();
        }
      });
      if (btnRv) btnRv.addEventListener('click', function () {
        if (routeTools) {
          if (inpS) routeTools.setSpeedKph(inpS.value || 40);
          routeTools.startVehicle();
        }
      });
      if (btnClr) btnClr.addEventListener('click', function () {
        if (terrainTools) terrainTools.clear();
        if (routeTools) routeTools.clear();
      });
      if (inpR) inpR.addEventListener('change', function () {
        if (terrainTools) terrainTools.setRadiusM(inpR.value);
        refreshRadiusMetrics();
      });
      if (inpR) inpR.addEventListener('input', refreshRadiusMetrics);
      if (inpS) inpS.addEventListener('change', function () {
        if (routeTools) routeTools.setSpeedKph(inpS.value);
        refreshRadiusMetrics();
      });
      if (inpS) inpS.addEventListener('input', refreshRadiusMetrics);
      refreshRadiusMetrics();
    }

    function refreshRadiusMetrics() {
      var etaEl = getEl(els.toolEta);
      var inpR = getEl(els.toolRadius);
      var inpS = getEl(els.toolSpeed);
      if (!etaEl || !inpR) return;
      // Ne pas écraser un ETA d’itinéraire déjà affiché (contient « Arrivée estimée »).
      var cur = etaEl.textContent || '';
      if (cur.indexOf('Arrivée estimée') >= 0) return;
      var r = Math.max(0, parseFloat(inpR.value) || 0);
      if (r <= 0) {
        if (cur.indexOf('Superficie') >= 0) etaEl.textContent = '';
        return;
      }
      var speed = Math.max(0.1, parseFloat(inpS && inpS.value) || 5);
      var area = Math.PI * r * r;
      var areaLabel = area >= 100000
        ? (area / 1e6).toFixed(2).replace('.', ',') + ' km²'
        : Math.round(area).toLocaleString('fr-FR') + ' m²';
      var delayS = r / (speed / 3.6);
      var delayLabel;
      if (delayS < 60) delayLabel = Math.round(delayS) + ' s';
      else if (delayS < 3600) delayLabel = Math.round(delayS / 60) + ' min';
      else {
        var h = Math.floor(delayS / 3600);
        var m = Math.floor((delayS % 3600) / 60);
        delayLabel = m === 0 ? (h + ' h') : (h + ' h ' + String(m).padStart(2, '0') + ' min');
      }
      etaEl.textContent =
        'Rayon ' + Math.round(r) + ' m · Superficie : ' + areaLabel +
        ' · Délai jusqu’au bord : ' + delayLabel +
        ' (à ' + String(speed).replace('.', ',') + ' km/h)';
    }
    wireToolButtons();

    function refreshWeatherBanner() {
      var el = getEl(els.weatherBanner);
      if (!el || !window.TacmapWeather) return;
      window.TacmapWeather.poll(apiBase, state.currentMapId, el, { compact: true });
    }

    function refreshTacticalPanel() {
      var listEl = getEl(els.tacticalList);
      if (!listEl || !window.TacmapTacticalAlerts) return;
      window.TacmapTacticalAlerts.poll(apiBase, state.currentMapId, listEl, {
        onAlerts: function (alerts) {
          renderTacticalMarkers(alerts);
        },
        onLocate: focusMapPos,
      });
    }

    function refreshReconPanel() {
      var listEl = getEl(els.reconList);
      if (!listEl || !window.TacmapRecon) return;
      window.TacmapRecon.poll(apiBase, state.currentMapId, listEl, {
        tenantId: tenantId,
        missionId: getMissionId(),
        onPhotos: function (photos) {
          renderReconMarkers(photos);
        },
        onLocate: focusMapPos,
      });
    }

    function loadMissionSettings() {
      var url = apiBase + '/atak/mission-settings?mapId=' + encodeURIComponent(state.currentMapId);
      if (String(apiBase).indexOf('/atak') >= 0) {
        url = apiBase + '/mission-settings?mapId=' + encodeURIComponent(state.currentMapId);
      }
      fetch(url, { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var s = (data && data.settings) ? data.settings : null;
          if (!s) return;
          // show_east → hostile, show_guer → unknown, show_civ → neutral
          if (typeof s.show_east === 'boolean') state.affiliations.hostile = s.show_east;
          if (typeof s.show_guer === 'boolean') state.affiliations.unknown = s.show_guer;
          if (typeof s.show_civ === 'boolean') state.affiliations.neutral = s.show_civ;
          var h = getEl(els.showHostile);
          var u = getEl(els.showUnknown);
          var n = getEl(els.showNeutral);
          if (h) h.checked = !!state.affiliations.hostile;
          if (u) u.checked = !!state.affiliations.unknown;
          if (n) n.checked = !!state.affiliations.neutral;
          if (lastUnitsRaw.length) {
            var filteredAff = filterUnitsByAffiliation(lastUnitsRaw);
            state.unitsCount = filteredAff.length;
            renderUnitsOnMap(filteredAff);
            renderRosterAndTable(filteredAff);
            updateUnitCountEl();
          }
        })
        .catch(function () {});
    }

    // Trails handled in bindLayerCheckbox

    function zuluTick() {
      var el = getEl(els.zulu);
      if (el) el.textContent = new Date().toISOString().substr(11, 8) + ' Z';
    }
    zuluTick();
    intervals.push(setInterval(zuluTick, 1000));

    refreshPlatformHealth();
    intervals.push(setInterval(refreshPlatformHealth, 60000));
    intervals.push(setInterval(refreshTacticalPanel, Math.max(syncMs, 8000)));
    intervals.push(setInterval(refreshReconPanel, Math.max(syncMs, 12000)));
    intervals.push(setInterval(refreshWeatherBanner, Math.max(syncMs, 20000)));
    loadMissionSettings();
    refreshTacticalPanel();
    refreshReconPanel();
    refreshWeatherBanner();

    var initialSlug = (mapSel && mapSel.value) || ctx.defaultMapSlug || 'altis';
    applyBaseLayer(initialSlug);

    return {
      destroy: function () {
        intervals.forEach(function (id) { clearInterval(id); });
        mapIntervals.forEach(function (id) { clearInterval(id); });
        if (map) {
          try { map.remove(); } catch (e) {}
        }
        map = null;
      },
      getState: function () { return state; },
      getMap: function () { return map; },
      selectUnit: selectUnit,
      syncUnits: syncUnits,
      refreshSecondaryLayers: refreshSecondaryLayers,
      invalidateSize: invalidateSize,
      loadMissionSettings: loadMissionSettings,
    };
  }

  global.ComspecOperationalMap = {
    WORLD_SCALE: WORLD_SCALE,
    buildArmaConfig: buildArmaConfig,
    buildImageConfig: buildImageConfig,
    mapKindFromSlug: mapKindFromSlug,
    bindDeletableLayer: bindDeletableLayer,
    renderMapShapes: renderMapShapes,
    renderAtakMarkers: renderAtakMarkers,
    createUnitTrailTracker: createUnitTrailTracker,
    trailColorFromCss: trailColorFromCss,
    initTacmap: initTacmap,
    invalidateTacmapSize: function () {
      if (typeof lastTacmapInvalidate === 'function') lastTacmapInvalidate();
    },
  };
})(window);
