/* COMSPEC ATAK - Formes carte (traits, commentaires, zones) */
window.ATAKMapShapes = (function () {
  var shapes = [];
  var layer = null;
  var leafletById = {};
  var drawingsVisible = true;
  var VISIBILITY_STORAGE_KEY = 'atak_map_drawings_visible_v1';

  try {
    drawingsVisible = localStorage.getItem(VISIBILITY_STORAGE_KEY) !== '0';
  } catch (e) {}

  function getApiBase() {
    return window.ATAKSocket ? window.ATAKSocket.getApiBase() : '';
  }
  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }

  function getMap() {
    return window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
  }

  function applyOffset(lat, lng) {
    if (window.ATAKMap && window.ATAKMap.applyOffset) {
      return window.ATAKMap.applyOffset(lat, lng);
    }
    return [lat, lng];
  }

  function ensureLayer() {
    var map = getMap();
    if (!map) return null;
    if (!layer) {
      layer = L.layerGroup();
      if (drawingsVisible) layer.addTo(map);
    } else if (drawingsVisible && !map.hasLayer(layer)) {
      layer.addTo(map);
    }
    return layer;
  }

  function clearLayer() {
    var map = getMap();
    if (layer && map) {
      try { map.removeLayer(layer); } catch (e) {}
    }
    layer = null;
    leafletById = {};
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function shapeKey(s) {
    return String(s.id != null ? s.id : (s.shapeUid || s.shape_uid || Math.random()));
  }

  function toLatLng(pair) {
    if (!pair || pair.length < 2) return null;
    // geometry stockée [x/lng, y/lat]
    var applied = applyOffset(pair[1], pair[0]);
    return L.latLng(applied[0], applied[1]);
  }

  function centerOfCoords(coords) {
    var pts = Array.isArray(coords) ? coords : [];
    if (!pts.length) return null;
    var sx = 0;
    var sy = 0;
    var n = 0;
    pts.forEach(function (pair) {
      if (!pair || pair.length < 2) return;
      sx += Number(pair[0]) || 0;
      sy += Number(pair[1]) || 0;
      n += 1;
    });
    if (!n) return null;
    return toLatLng([sx / n, sy / n]);
  }

  function formatIntFr(n) {
    var s = String(Math.round(Math.abs(n)));
    return s.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  }

  function formatAreaFrLocal(areaM2) {
    if (window.ATAKMapTools && typeof window.ATAKMapTools.formatAreaFr === 'function') {
      return window.ATAKMapTools.formatAreaFr(areaM2);
    }
    var a = Number(areaM2);
    if (!isFinite(a) || a < 0) return '—';
    if (a >= 100000) {
      return (a / 1e6).toFixed(2).replace('.', ',') + ' km²';
    }
    return formatIntFr(a) + ' m²';
  }

  function formatDelayFrLocal(seconds) {
    if (window.ATAKMapTools && typeof window.ATAKMapTools.formatDelayFr === 'function') {
      return window.ATAKMapTools.formatDelayFr(seconds);
    }
    var s = Math.max(0, Math.round(Number(seconds) || 0));
    if (s < 60) return s + ' s';
    var h = Math.floor(s / 3600);
    var m = Math.floor((s % 3600) / 60);
    if (h >= 1) return m === 0 ? (h + ' h') : (h + ' h ' + String(m).padStart(2, '0') + ' min');
    return m + ' min';
  }

  function circleShapeMetrics(s) {
    var geom = s.geometry || {};
    var meta = s.meta || {};
    var radius = geom.radius != null ? Number(geom.radius) : (meta.radius_m != null ? Number(meta.radius_m) : NaN);
    if (!isFinite(radius) || radius <= 0) return null;
    var speed = meta.speed_kph != null ? Number(meta.speed_kph) : NaN;
    if (!isFinite(speed) || speed <= 0) {
      speed = (window.ATAKMapTools && window.ATAKMapTools.getToolSpeedKph)
        ? window.ATAKMapTools.getToolSpeedKph()
        : 5;
    }
    var area = meta.area_m2 != null ? Number(meta.area_m2) : (Math.PI * radius * radius);
    var delay = meta.delay_s != null ? Number(meta.delay_s) : (radius / (speed / 3.6));
    // Recalcule toujours à partir du rayon + vitesse (source de vérité).
    if (window.ATAKMapTools && typeof window.ATAKMapTools.circleMetrics === 'function') {
      var m = window.ATAKMapTools.circleMetrics(radius, speed);
      return {
        radiusM: radius,
        speedKph: speed,
        areaLabel: m.areaLabel,
        delayLabel: m.delayLabel,
        tip: 'Rayon ' + Math.round(radius) + ' m · Superficie ' + m.areaLabel +
          ' · Délai jusqu’au bord ' + m.delayLabel
      };
    }
    return {
      radiusM: radius,
      speedKph: speed,
      areaLabel: formatAreaFrLocal(area),
      delayLabel: formatDelayFrLocal(delay),
      tip: 'Rayon ' + Math.round(radius) + ' m · Superficie ' + formatAreaFrLocal(area) +
        ' · Délai jusqu’au bord ' + formatDelayFrLocal(delay)
    };
  }

  function renderShape(s) {
    var lg = ensureLayer();
    if (!lg || !s) return;
    var key = shapeKey(s);
    if (leafletById[key]) {
      try { lg.removeLayer(leafletById[key]); } catch (e) {}
      delete leafletById[key];
    }
    var geom = s.geometry || {};
    var color = s.color || '#34d399';
    var label = s.label || s.type || '';
    var meta = s.meta || {};
    var kind = meta.kind || '';
    var type = String(s.type || '').toUpperCase();
    var obj = null;
    var layers = [];
    var kindLabel = '';
    if (kind === 'search_zone') kindLabel = 'Zone de recherche';
    else if (kind === 'perimeter') kindLabel = 'Périmètre';
    else if (kind === 'aoi') kindLabel = 'Zone d’intérêt';
    else if (kind === 'zone') kindLabel = 'Zone';
    else if (kind === 'line') kindLabel = 'Trait';
    else if (kind === 'comment') kindLabel = 'Commentaire';

    if (type === 'LINE' || type === 'POLYLINE' || type === 'POLYGON') {
      var coords = geom.coordinates || geom.points || [];
      var latlngs = [];
      (Array.isArray(coords) ? coords : []).forEach(function (c) {
        var ll = toLatLng(c);
        if (ll) latlngs.push(ll);
      });
      if (latlngs.length >= 2) {
        if (type === 'POLYGON') {
          var polyOpts = {
            color: color,
            weight: s.stroke || 2,
            fillOpacity: s.fill_opacity != null ? s.fill_opacity : 0.12
          };
          if (meta.fill_style === 'gradient') {
            layers.push(L.polygon(latlngs, {
              color: color,
              weight: (s.stroke || 2) + 8,
              opacity: 0.18,
              fillColor: color,
              fillOpacity: Math.max(0.04, (s.fill_opacity != null ? s.fill_opacity : 0.12) * 0.35),
              className: 'atak-shape-gradient-halo'
            }));
          }
          obj = L.polygon(latlngs, polyOpts);
        } else {
          obj = L.polyline(latlngs, { color: color, weight: s.stroke || 2, opacity: 0.95 });
        }
      }
    } else if (type === 'CIRCLE') {
      var center = geom.center;
      var radius = geom.radius != null ? geom.radius : 100;
      if (center && center.length >= 2) {
        var cll = toLatLng(center);
        if (cll) {
          if (meta.fill_style === 'gradient') {
            layers.push(L.circle(cll, {
              radius: radius,
              color: color,
              fillColor: color,
              fillOpacity: Math.max(0.04, (s.fill_opacity != null ? s.fill_opacity : 0.15) * 0.35),
              opacity: 0.18,
              weight: (s.stroke || 2) + 8,
              className: 'atak-shape-gradient-halo'
            }));
          }
          obj = L.circle(cll, {
            radius: radius,
            color: color,
            fillColor: color,
            fillOpacity: s.fill_opacity != null ? s.fill_opacity : 0.15,
            weight: s.stroke || 2
          });
        }
      }
    } else {
      // POINT / commentaire / défaut
      var c2 = geom.center || geom.coordinates || geom.pos;
      if (c2 && !Array.isArray(c2[0]) && c2.length >= 2) {
        var pll = toLatLng(c2);
        if (pll) {
          var isComment = kind === 'comment' || type === 'POINT';
          var icon = L.divIcon({
            className: isComment ? 'atak-shape-comment' : 'atak-shape-point',
            html: isComment
              ? '<span class="atak-shape-comment__pin" title="' + escapeHtml(label) + '">✎</span>'
              : '<span class="atak-shape-point__dot"></span>',
            iconSize: isComment ? [16, 16] : [12, 12],
            iconAnchor: isComment ? [8, 8] : [6, 6]
          });
          obj = L.marker(pll, { icon: icon });
        }
      }
    }

    if (!obj) return;
    layers.push(obj);
    var iconText = String(meta.icon_text || '').trim();
    var imageUrl = String(meta.image_url || '').trim();
    if ((type === 'CIRCLE' || type === 'POLYGON') && (iconText || imageUrl)) {
      var centerLatLng = type === 'CIRCLE'
        ? toLatLng(geom.center || [])
        : centerOfCoords(geom.coordinates || geom.points || []);
      if (centerLatLng) {
        var centerIcon = L.divIcon({
          className: 'atak-shape-center-icon',
          html: imageUrl
            ? '<span class="atak-shape-center-icon__image" style="background-image:url(\'' + escapeHtml(imageUrl) + '\')"></span>'
            : '<span class="atak-shape-center-icon__text">' + escapeHtml(iconText) + '</span>',
          iconSize: imageUrl ? [22, 22] : [18, 18],
          iconAnchor: imageUrl ? [11, 11] : [9, 9]
        });
        layers.push(L.marker(centerLatLng, { icon: centerIcon }));
      }
    }
    if (layers.length > 1) {
      obj = L.featureGroup(layers);
    }
    var displayLabel = label || kindLabel || 'Élément';
    var popup = '<strong>' + escapeHtml(displayLabel) + '</strong>';
    if (kindLabel && label && kindLabel !== label) {
      popup = '<strong>' + escapeHtml(kindLabel) + '</strong><br/>' + escapeHtml(label);
    }
    if (meta.author || s.created_by || s.createdBy) {
      popup += '<br/><span style="color:var(--atak-muted)">' + escapeHtml(meta.author || s.created_by || s.createdBy) + '</span>';
    }
    if (kind === 'comment' && label) {
      popup = '<strong>Commentaire</strong><br/>' + escapeHtml(label);
    }
    if (meta.comment) {
      popup += '<br/><div class="atak-shape-popup__comment">' + escapeHtml(meta.comment) + '</div>';
    }
    if (imageUrl) {
      popup += '<br/><div class="atak-shape-popup__image"><img src="' + escapeHtml(imageUrl) + '" alt="Illustration de zone" loading="lazy" /></div>';
    }
    if (iconText) {
      popup += '<br/><div class="atak-shape-popup__icon">Icône : <strong>' + escapeHtml(iconText) + '</strong></div>';
    }
    var circleMetrics = (type === 'CIRCLE') ? circleShapeMetrics(s) : null;
    if (circleMetrics) {
      popup += '<br/><span class="atak-shape-metrics">' +
        'Rayon : ' + escapeHtml(String(Math.round(circleMetrics.radiusM))) + ' m<br/>' +
        'Superficie : ' + escapeHtml(circleMetrics.areaLabel) + '<br/>' +
        'Délai jusqu’au bord : ' + escapeHtml(circleMetrics.delayLabel) +
        ' <span style="color:var(--atak-muted)">(à ' + escapeHtml(String(circleMetrics.speedKph).replace('.', ',')) + ' km/h)</span>' +
        '</span>';
      obj.bindTooltip(circleMetrics.tip, {
        sticky: true,
        direction: 'top',
        opacity: 0.95,
        className: 'atak-shape-circle-tip'
      });
    }
    obj.bindPopup(popup);
    obj._atakShapeId = key;
    obj._atakShape = s;
    if (!obj._atakCtxBound) {
      obj._atakCtxBound = true;
      obj.on('contextmenu', function (e) {
        if (!e || !e.originalEvent) return;
        L.DomEvent.preventDefault(e);
        L.DomEvent.stopPropagation(e);
        try { e.originalEvent.stopImmediatePropagation(); } catch (err) {}
        var featureType = 'shape';
        if (kind === 'comment' || type === 'POINT') featureType = 'comment';
        else if (kind === 'line' || type === 'LINE' || type === 'POLYLINE') featureType = 'line';
        else if (kind === 'zone' || kind === 'search_zone' || kind === 'perimeter' || kind === 'aoi' || type === 'CIRCLE' || type === 'POLYGON') featureType = 'zone';
        window.dispatchEvent(new CustomEvent('atak:feature-contextmenu', {
          detail: {
            featureType: featureType,
            id: key,
            shape: s,
            label: label || kindLabel || featureTypeLabel(featureType),
            latlng: e.latlng || null,
            clientX: e.originalEvent.clientX,
            clientY: e.originalEvent.clientY
          }
        }));
      });
    }
    obj.addTo(lg);
    leafletById[key] = obj;
  }

  function featureTypeLabel(featureType) {
    if (featureType === 'comment') return 'Commentaire';
    if (featureType === 'line') return 'Trait';
    if (featureType === 'zone') return 'Zone';
    return 'Élément';
  }

  function getShapeById(id) {
    var key = String(id);
    for (var i = 0; i < shapes.length; i++) {
      if (shapeKey(shapes[i]) === key) return shapes[i];
    }
    return null;
  }

  function applyLocalShapeUpdate(id, patch) {
    var key = String(id);
    for (var i = 0; i < shapes.length; i++) {
      if (shapeKey(shapes[i]) === key) {
        shapes[i] = Object.assign({}, shapes[i], patch || {});
        renderShape(shapes[i]);
        return shapes[i];
      }
    }
    return null;
  }

  function removeLocalShape(id) {
    var key = String(id);
    shapes = shapes.filter(function (s) { return shapeKey(s) !== key; });
    if (leafletById[key] && layer) {
      try { layer.removeLayer(leafletById[key]); } catch (e) {}
      delete leafletById[key];
    }
  }

  function updateShape(id, patch) {
    var base = getApiBase();
    var local = applyLocalShapeUpdate(id, patch);
    if (!base || String(id).indexOf('local_') === 0) {
      return Promise.resolve(local);
    }
    return fetch(base + '/api/map-shapes/' + encodeURIComponent(id), {
      method: 'PATCH',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(patch || {})
    }).then(function (r) {
      if (!r.ok) {
        if (window.ATAKShowError) window.ATAKShowError('Impossible de mettre à jour cet élément.');
        fetchShapes();
        return null;
      }
      return r.json();
    }).then(function (row) {
      if (!row) return null;
      var key = shapeKey(row);
      shapes = shapes.filter(function (s) { return shapeKey(s) !== String(id) && shapeKey(s) !== key; });
      shapes.push(row);
      renderShape(row);
      return row;
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible de mettre à jour cet élément.');
      fetchShapes();
      return null;
    });
  }

  function deleteShape(id) {
    var base = getApiBase();
    removeLocalShape(id);
    if (!base || String(id).indexOf('local_') === 0) {
      return Promise.resolve(true);
    }
    return fetch(base + '/api/map-shapes/' + encodeURIComponent(id), {
      method: 'DELETE',
      credentials: 'include'
    }).then(function (r) {
      if (!r.ok) {
        if (window.ATAKShowError) window.ATAKShowError('Impossible de supprimer cet élément.');
        fetchShapes();
        return false;
      }
      return true;
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible de supprimer cet élément.');
      fetchShapes();
      return false;
    });
  }

  function redrawAll() {
    clearLayer();
    ensureLayer();
    shapes.forEach(renderShape);
  }

  function setDrawingsVisible(visible) {
    drawingsVisible = !!visible;
    var map = getMap();
    var lg = ensureLayer();
    if (map && lg) {
      if (drawingsVisible && !map.hasLayer(lg)) lg.addTo(map);
      else if (!drawingsVisible && map.hasLayer(lg)) map.removeLayer(lg);
    }
    try { localStorage.setItem(VISIBILITY_STORAGE_KEY, drawingsVisible ? '1' : '0'); } catch (e) {}
    window.dispatchEvent(new CustomEvent('atak:drawings-visibility', {
      detail: { visible: drawingsVisible }
    }));
    return drawingsVisible;
  }

  function fetchShapes() {
    var base = getApiBase();
    if (!base) return;
    var url = base + '/api/map-shapes?mapId=' + getMapId();
    fetch(url, { credentials: 'include' })
      .then(function (r) {
        if (!r.ok) throw new Error('shapes ' + r.status);
        return r.json();
      })
      .then(function (data) {
        shapes = Array.isArray(data) ? data : [];
        if (getMap()) redrawAll();
      })
      .catch(function () {});
  }

  function addLocal(shape) {
    shapes.push(shape);
    renderShape(shape);
  }

  function createShape(payload) {
    var base = getApiBase();
    var tempId = 'local_s_' + Date.now();
    var optimistic = Object.assign({}, payload, { id: tempId });
    addLocal(optimistic);

    if (!base) {
      if (window.ATAKShowNotification) window.ATAKShowNotification('Forme placée (local).');
      return Promise.resolve(optimistic);
    }

    return fetch(base + '/api/map-shapes', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) {
      if (!r.ok) {
        if (window.ATAKShowError) window.ATAKShowError('Impossible d’enregistrer sur la carte.');
        return null;
      }
      return r.json();
    }).then(function (row) {
      if (!row) return null;
      // remplace l’optimiste
      shapes = shapes.filter(function (s) { return shapeKey(s) !== tempId; });
      if (leafletById[tempId] && layer) {
        try { layer.removeLayer(leafletById[tempId]); } catch (e) {}
        delete leafletById[tempId];
      }
      shapes.push(row);
      renderShape(row);
      if (window.ATAKShowNotification) {
        var kind = (payload.meta && payload.meta.kind) || payload.type || 'élément';
        var msg = kind === 'comment' ? 'Commentaire enregistré.'
          : kind === 'line' ? 'Trait enregistré.'
          : kind === 'search_zone' ? 'Zone de recherche enregistrée.'
          : kind === 'perimeter' ? 'Périmètre enregistré.'
          : kind === 'aoi' ? 'Zone d’intérêt enregistrée.'
          : kind === 'zone' ? 'Zone enregistrée.'
          : 'Élément enregistré sur la carte.';
        window.ATAKShowNotification(msg);
      }
      return row;
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible d’enregistrer sur la carte.');
      return null;
    });
  }

  var TACTICAL_KINDS = {
    zone: true,
    search_zone: true,
    perimeter: true,
    aoi: true,
    line: true
  };

  function isTacticalDrawing(s) {
    if (!s) return false;
    var meta = s.meta || {};
    var kind = String(meta.kind || '');
    if (TACTICAL_KINDS[kind]) return true;
    var type = String(s.type || '').toUpperCase();
    return type === 'CIRCLE' || type === 'POLYGON' || type === 'LINE' || type === 'POLYLINE';
  }

  function clearLastDrawing() {
    var list = shapes.filter(isTacticalDrawing);
    if (!list.length) return Promise.resolve(false);
    var last = list[list.length - 1];
    return deleteShape(shapeKey(last)).then(function (ok) {
      if (ok && window.ATAKShowNotification) window.ATAKShowNotification('Dernier tracé effacé.');
      return !!ok;
    });
  }

  function clearAllDrawings() {
    var targets = shapes.filter(isTacticalDrawing);
    if (!targets.length) {
      if (window.ATAKShowNotification) window.ATAKShowNotification('Aucun tracé à effacer.');
      return Promise.resolve(0);
    }
    var chain = Promise.resolve();
    var count = 0;
    targets.forEach(function (s) {
      chain = chain.then(function () {
        return deleteShape(shapeKey(s)).then(function (ok) {
          if (ok) count += 1;
        });
      });
    });
    return chain.then(function () {
      if (window.ATAKShowNotification) {
        window.ATAKShowNotification(count > 1
          ? count + ' tracés effacés.'
          : (count === 1 ? 'Tracé effacé.' : 'Aucun tracé effacé.'));
      }
      return count;
    });
  }

  function onMapReady() {
    redrawAll();
  }

  window.addEventListener('atak:mapready', onMapReady);

  return {
    fetchShapes: fetchShapes,
    getShapes: function () { return shapes; },
    getShapeById: getShapeById,
    createShape: createShape,
    updateShape: updateShape,
    deleteShape: deleteShape,
    clearLastDrawing: clearLastDrawing,
    clearAllDrawings: clearAllDrawings,
    areDrawingsVisible: function () { return drawingsVisible; },
    setDrawingsVisible: setDrawingsVisible,
    redrawAll: redrawAll
  };
})();
