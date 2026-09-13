/**
 * Réseau géographique Athena — lieux et routes (ingest mod → calques carte).
 * Styles par classe de route, libellés opérateur, nommage au poste.
 */
(function (global) {
  'use strict';

  var PLACE_LABEL_ZOOM = 4;

  function dist2d(a, b) {
    var dx = a[0] - b[0];
    var dy = a[1] - b[1];
    return Math.sqrt(dx * dx + dy * dy);
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function csrfToken() {
    if (global.ATAK_CSRF_TOKEN) return String(global.ATAK_CSRF_TOKEN);
    var meta = global.document && global.document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.getAttribute('content')) return meta.getAttribute('content') || '';
    return '';
  }

  function roadStyle(cls) {
    switch (String(cls || 'OTHER').toUpperCase()) {
      case 'HIGHWAY':
        return { color: '#9ec5ff', weight: 4.5, opacity: 0.92, dashArray: null };
      case 'PRIMARY':
        return { color: '#7aa8e0', weight: 3.2, opacity: 0.85, dashArray: null };
      case 'SECONDARY':
        return { color: '#6a90b8', weight: 2.4, opacity: 0.75, dashArray: null };
      case 'TRACK':
        return { color: '#8a9aaa', weight: 1.6, opacity: 0.65, dashArray: '4 6' };
      default:
        return { color: '#5a6a7a', weight: 1.4, opacity: 0.45, dashArray: null };
    }
  }

  function create(apiBase, mapId, layerGroup) {
    var state = {
      apiBase: (apiBase || '').replace(/\/$/, ''),
      mapId: mapId || 1,
      placesLayer: null,
      roadsLayer: null,
      coverage: null,
      visible: { places: false, roads: false },
      lastData: { places: [], roads: [] },
      map: null,
      placeMarkers: [],
    };

    if (layerGroup && global.L) {
      state.placesLayer = global.L.layerGroup();
      state.roadsLayer = global.L.layerGroup();
    }

    function resolveMap() {
      if (state.map) return state.map;
      if (global.ATAKMap && typeof global.ATAKMap.getMap === 'function') {
        state.map = global.ATAKMap.getMap();
      }
      return state.map;
    }

    function fetchJson(url, opts) {
      return fetch(url, Object.assign({ credentials: 'same-origin' }, opts || {})).then(function (r) {
        return r.json().then(function (data) {
          if (!r.ok) {
            var err = new Error((data && data.message) || 'request_failed');
            err.status = r.status;
            err.data = data;
            throw err;
          }
          return data;
        });
      });
    }

    function loadCoverage() {
      if (!state.apiBase) return Promise.resolve(null);
      return fetchJson(state.apiBase + '/atak/geo/coverage?mapId=' + encodeURIComponent(state.mapId))
        .then(function (data) {
          if (data && data.ok) state.coverage = data;
          return state.coverage;
        })
        .catch(function () { return null; });
    }

    function loadBbox(bbox) {
      if (!state.apiBase || !bbox || bbox.length !== 4) {
        return Promise.resolve({ places: [], roads: [] });
      }
      var q = bbox.map(function (n) { return Number(n).toFixed(2); }).join(',');
      var base = state.apiBase + '/atak/geo';
      return Promise.all([
        fetchJson(base + '/places?mapId=' + state.mapId + '&bbox=' + q),
        fetchJson(base + '/roads?mapId=' + state.mapId + '&bbox=' + q),
      ]).then(function (res) {
        return {
          places: (res[0] && res[0].places) || [],
          roads: (res[1] && res[1].roads) || [],
        };
      });
    }

    function searchPlaces(query) {
      if (!state.apiBase || !query) return Promise.resolve([]);
      return fetchJson(
        state.apiBase + '/atak/geo/places?mapId=' + state.mapId + '&q=' + encodeURIComponent(query)
      ).then(function (data) {
        return (data && data.places) || [];
      });
    }

    function placeColor(type) {
      switch (type) {
        case 'CITY': return '#f4c542';
        case 'TOWN': return '#e8a838';
        case 'VILLAGE': return '#c9a227';
        case 'LANDMARK': return '#6cf';
        case 'INTERSECTION': return '#aaa';
        default: return '#ccc';
      }
    }

    function placeRadius(type) {
      if (type === 'CITY') return 9;
      if (type === 'TOWN') return 7;
      return 5;
    }

    function roadDisplayName(seg) {
      var label = seg && (seg.label || seg.operator_label);
      if (label && String(label).trim() !== '') return String(label).trim();
      return 'Route sans nom';
    }

    function askRoadName(current) {
      var initial = current && current !== 'Route sans nom' ? current : '';
      if (global.ATAKContextMenu && typeof global.ATAKContextMenu.openPrompt === 'function') {
        return global.ATAKContextMenu.openPrompt(
          'Nom de cette route',
          'Ce libellé reste enregistré au poste, même après un nouveau relevé depuis Arma. Laissez vide pour effacer le nom.',
          'Ex. Axe nord Kavala',
          initial
        );
      }
      if (typeof global.ATAKOpenPrompt === 'function') {
        return Promise.resolve(global.ATAKOpenPrompt('Nom de cette route', initial));
      }
      return Promise.resolve(global.prompt('Nom de cette route', initial));
    }

    function saveRoadLabel(seg, label) {
      var csrf = csrfToken();
      var body = {
        mapId: state.mapId,
        source_id: seg.id || seg.source_id || '',
        label: label,
        _csrf_token: csrf,
      };
      return fetchJson(state.apiBase + '/atak/geo/roads/label', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-CSRF-Token': csrf,
        },
        body: JSON.stringify(body),
      });
    }

    function bindRoadInteractions(line, seg) {
      function applyTip() {
        var name = roadDisplayName(seg);
        var map = resolveMap();
        var z = map && typeof map.getZoom === 'function' ? map.getZoom() : 0;
        var hasLabel = !!(seg.label && String(seg.label).trim() !== '');
        line.unbindTooltip();
        if (hasLabel && z >= PLACE_LABEL_ZOOM) {
          line.bindTooltip(escapeHtml(String(seg.label).trim()), {
            permanent: true,
            direction: 'center',
            className: 'atak-geo-road-label',
            opacity: 0.9,
          });
        } else {
          line.bindTooltip(name, {
            sticky: true,
            direction: 'top',
            opacity: 0.95,
          });
        }
      }
      applyTip();
      line._atakApplyRoadTip = applyTip;
      line.on('click', function () {
        var current = roadDisplayName(seg);
        askRoadName(current === 'Route sans nom' ? '' : current).then(function (raw) {
          if (raw === null || raw === undefined) return;
          var next = String(raw).trim();
          var payloadLabel = next === '' ? null : next.slice(0, 120);
          saveRoadLabel(seg, payloadLabel).then(function (res) {
            var updated = (res && res.road) || {};
            seg.label = updated.label != null ? updated.label : payloadLabel;
            if (updated.db_id != null) seg.db_id = updated.db_id;
            applyTip();
            if (global.ATAKShowNotification) {
              global.ATAKShowNotification('Nom de route enregistré.');
            }
          }).catch(function () {
            if (global.ATAKShowError) {
              global.ATAKShowError('Impossible d’enregistrer le nom de cette route.');
            }
          });
        });
      });
    }

    function syncPlaceLabels() {
      var map = resolveMap();
      var z = map && typeof map.getZoom === 'function' ? map.getZoom() : 0;
      state.placeMarkers.forEach(function (entry) {
        var marker = entry.marker;
        var p = entry.place;
        var showPermanent = z >= PLACE_LABEL_ZOOM && (p.type === 'CITY' || p.type === 'TOWN') && p.name;
        if (showPermanent) {
          if (!entry.permanent) {
            marker.unbindTooltip();
            marker.bindTooltip(escapeHtml(p.name), {
              permanent: true,
              direction: 'top',
              className: 'atak-geo-place-label',
              opacity: 0.95,
            });
            entry.permanent = true;
          }
        } else if (entry.permanent) {
          marker.unbindTooltip();
          if (p.name) {
            marker.bindTooltip(escapeHtml(p.name), { direction: 'top', opacity: 0.9 });
          }
          entry.permanent = false;
        }
      });
      if (state.roadsLayer && typeof state.roadsLayer.eachLayer === 'function') {
        state.roadsLayer.eachLayer(function (layer) {
          if (typeof layer._atakApplyRoadTip === 'function') layer._atakApplyRoadTip();
        });
      }
    }

    function ensureZoomHook() {
      var map = resolveMap();
      if (!map || map._atakGeoLabelZoomBound) return;
      map._atakGeoLabelZoomBound = true;
      map.on('zoomend', syncPlaceLabels);
    }

    function render(data) {
      if (!global.L || !layerGroup) return;
      state.lastData = data || { places: [], roads: [] };
      state.placesLayer.clearLayers();
      state.roadsLayer.clearLayers();
      state.placeMarkers = [];
      ensureZoomHook();

      if (state.visible.roads && data.roads && data.roads.length) {
        data.roads.forEach(function (seg) {
          if (!seg.a || !seg.b) return;
          var style = roadStyle(seg.class || seg.road_class);
          var line = global.L.polyline(
            [[seg.a[1], seg.a[0]], [seg.b[1], seg.b[0]]],
            {
              color: style.color,
              weight: style.weight,
              opacity: style.opacity,
              dashArray: style.dashArray || undefined,
              interactive: true,
              className: 'atak-geo-road',
            }
          );
          bindRoadInteractions(line, seg);
          line.addTo(state.roadsLayer);
        });
        if (!mapHasLayer(state.roadsLayer)) state.roadsLayer.addTo(layerGroup);
      } else if (mapHasLayer(state.roadsLayer)) {
        layerGroup.removeLayer(state.roadsLayer);
      }

      if (state.visible.places && data.places && data.places.length) {
        data.places.forEach(function (p) {
          var marker = global.L.circleMarker([p.y, p.x], {
            radius: placeRadius(p.type),
            color: '#111',
            fillColor: placeColor(p.type),
            fillOpacity: 0.85,
            weight: 1,
          });
          marker.bindPopup('<strong>' + escapeHtml(p.name || p.type) + '</strong><br>' + escapeHtml(p.type));
          if (p.name) {
            marker.bindTooltip(escapeHtml(p.name), { direction: 'top', opacity: 0.9 });
          }
          marker.addTo(state.placesLayer);
          state.placeMarkers.push({ marker: marker, place: p, permanent: false });
        });
        if (!mapHasLayer(state.placesLayer)) state.placesLayer.addTo(layerGroup);
        syncPlaceLabels();
      } else if (mapHasLayer(state.placesLayer)) {
        layerGroup.removeLayer(state.placesLayer);
      }
    }

    function mapHasLayer(sub) {
      return layerGroup && sub && layerGroup.hasLayer(sub);
    }

    function setVisible(kind, on) {
      if (kind === 'places') state.visible.places = !!on;
      if (kind === 'roads') state.visible.roads = !!on;
    }

    function nearestPlace(x, y, places, maxM) {
      var best = null;
      var bestD = maxM || 500;
      (places || []).forEach(function (p) {
        var d = dist2d([x, y], [p.x, p.y]);
        if (d <= bestD) {
          bestD = d;
          best = p;
        }
      });
      return best;
    }

    return {
      loadCoverage: loadCoverage,
      loadBbox: loadBbox,
      searchPlaces: searchPlaces,
      render: render,
      setVisible: setVisible,
      getCoverage: function () { return state.coverage; },
      isGeoReady: function () {
        return !!(state.coverage && state.coverage.geo_ready);
      },
      nearestPlace: nearestPlace,
    };
  }

  global.AtakGeoNetwork = { create: create };
})(window);
