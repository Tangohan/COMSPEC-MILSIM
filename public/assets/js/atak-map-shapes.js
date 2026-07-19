/* COMSPEC ATAK - Formes carte (traits, commentaires, zones) */
window.ATAKMapShapes = (function () {
  var shapes = [];
  var layer = null;
  var leafletById = {};

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
      layer = L.layerGroup().addTo(map);
    } else if (!map.hasLayer(layer)) {
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

    if (type === 'LINE' || type === 'POLYLINE' || type === 'POLYGON') {
      var coords = geom.coordinates || geom.points || [];
      var latlngs = [];
      (Array.isArray(coords) ? coords : []).forEach(function (c) {
        var ll = toLatLng(c);
        if (ll) latlngs.push(ll);
      });
      if (latlngs.length >= 2) {
        if (type === 'POLYGON') {
          obj = L.polygon(latlngs, {
            color: color,
            weight: s.stroke || 2,
            fillOpacity: s.fill_opacity != null ? s.fill_opacity : 0.12
          });
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
            iconSize: isComment ? [22, 22] : [14, 14],
            iconAnchor: isComment ? [11, 11] : [7, 7]
          });
          obj = L.marker(pll, { icon: icon });
        }
      }
    }

    if (!obj) return;
    var popup = '<strong>' + escapeHtml(label) + '</strong>';
    if (meta.author || s.created_by || s.createdBy) {
      popup += '<br/><span style="color:var(--atak-muted)">' + escapeHtml(meta.author || s.created_by || s.createdBy) + '</span>';
    }
    if (kind === 'comment' && label) {
      popup = '<strong>Commentaire</strong><br/>' + escapeHtml(label);
    }
    obj.bindPopup(popup);
    obj._atakShapeId = key;
    obj.addTo(lg);
    leafletById[key] = obj;
  }

  function redrawAll() {
    clearLayer();
    ensureLayer();
    shapes.forEach(renderShape);
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

  function onMapReady() {
    redrawAll();
  }

  window.addEventListener('atak:mapready', onMapReady);

  return {
    fetchShapes: fetchShapes,
    getShapes: function () { return shapes; },
    createShape: createShape,
    redrawAll: redrawAll
  };
})();
