/**
 * Réseau géographique Athena — lieux et routes (ingest mod → calques carte).
 */
(function (global) {
  'use strict';

  function dist2d(a, b) {
    var dx = a[0] - b[0];
    var dy = a[1] - b[1];
    return Math.sqrt(dx * dx + dy * dy);
  }

  function create(apiBase, mapId, layerGroup) {
    var state = {
      apiBase: (apiBase || '').replace(/\/$/, ''),
      mapId: mapId || 1,
      placesLayer: null,
      roadsLayer: null,
      coverage: null,
      visible: { places: false, roads: false },
    };

    if (layerGroup && global.L) {
      state.placesLayer = global.L.layerGroup();
      state.roadsLayer = global.L.layerGroup();
    }

    function fetchJson(url) {
      return fetch(url, { credentials: 'same-origin' }).then(function (r) {
        return r.json();
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

    function render(data) {
      if (!global.L || !layerGroup) return;
      state.placesLayer.clearLayers();
      state.roadsLayer.clearLayers();

      if (state.visible.roads && data.roads && data.roads.length) {
        data.roads.forEach(function (seg) {
          if (!seg.a || !seg.b) return;
          global.L.polyline(
            [[seg.a[1], seg.a[0]], [seg.b[1], seg.b[0]]],
            { color: '#6688aa', weight: 2, opacity: 0.55, interactive: false }
          ).addTo(state.roadsLayer);
        });
        if (!mapHasLayer(state.roadsLayer)) state.roadsLayer.addTo(layerGroup);
      } else if (mapHasLayer(state.roadsLayer)) {
        layerGroup.removeLayer(state.roadsLayer);
      }

      if (state.visible.places && data.places && data.places.length) {
        data.places.forEach(function (p) {
          global.L.circleMarker([p.y, p.x], {
            radius: p.type === 'CITY' ? 7 : 5,
            color: '#111',
            fillColor: placeColor(p.type),
            fillOpacity: 0.85,
            weight: 1,
          }).bindPopup('<strong>' + (p.name || p.type) + '</strong><br>' + p.type).addTo(state.placesLayer);
        });
        if (!mapHasLayer(state.placesLayer)) state.placesLayer.addTo(layerGroup);
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
