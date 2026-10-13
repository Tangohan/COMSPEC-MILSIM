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

  // Localités : le nom sur un fond, sans pastille. Styles injectés ici car la page
  // Overwatch beta ne charge pas atak.css (même rendu sur toutes les cartes).
  var PLACE_RANK = { CITY: 0, TOWN: 1, VILLAGE: 2, LANDMARK: 3, OTHER: 3, INTERSECTION: 4 };
  // Niveaux de zoom au-dessus du minimum de la carte à partir desquels chaque rang s'affiche.
  var PLACE_MIN_REL_ZOOM = [0, 0, 1, 3, 5];

  function ensurePlaceStyles() {
    var doc = global.document;
    if (!doc || doc.getElementById('atak-geo-place-styles')) return;
    // Thème Athena / Overwatch : fond noir mat, filet fin, Inter / IBM Plex Mono, hiérarchie par la taille seulement.
    var css = [
      '.leaflet-marker-icon.atak-geo-place{background:none;border:0;width:0!important;height:0!important;overflow:visible;}',
      '.atak-geo-place__name{position:absolute;left:0;top:0;transform:translate(-50%,-50%);white-space:nowrap;cursor:pointer;pointer-events:auto;',
      'font:500 10.5px/1.3 Inter,"IBM Plex Sans","Segoe UI",system-ui,sans-serif;letter-spacing:.02em;',
      'color:var(--athena-soft,#c5cbc8);background:rgba(5,5,5,.72);border:1px solid var(--athena-line-2,#27302d);border-radius:2px;padding:1px 5px;}',
      '.atak-geo-place--city .atak-geo-place__name{font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.12em;',
      'color:var(--athena-text,#e4e8e8);padding:2px 7px;}',
      '.atak-geo-place--town .atak-geo-place__name{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.1em;color:var(--athena-text,#e4e8e8);}',
      '.atak-geo-place--landmark .atak-geo-place__name,.atak-geo-place--other .atak-geo-place__name{',
      'font:400 9.5px/1.3 "IBM Plex Mono",ui-monospace,Menlo,Consolas,monospace;color:var(--athena-soft,#c5cbc8);opacity:.85;',
      'background:none;border-color:transparent;text-shadow:0 0 3px #000,0 0 2px #000;}',
      '.atak-geo-place__name:hover{border-color:var(--athena-green,#00d69a);color:#fff;}',
      '.atak-geo-place.is-culled{display:none!important;}'
    ].join('');
    var el = doc.createElement('style');
    el.id = 'atak-geo-place-styles';
    el.textContent = css;
    (doc.head || doc.documentElement).appendChild(el);
  }

  function placeKind(type) {
    var t = String(type || 'OTHER').toUpperCase();
    return PLACE_RANK.hasOwnProperty(t) ? t : 'OTHER';
  }

  function placeLabelSize(p) {
    var kind = placeKind(p.type);
    var name = String(p.name || '');
    var charW = kind === 'CITY' ? 9.4 : (kind === 'TOWN' ? 8.2 : 6.2);
    var h = kind === 'CITY' ? 20 : 17;
    return { w: Math.max(24, name.length * charW + 14), h: h };
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
      // Overwatch beta et carte opérationnelle : pas d'ATAKMap, la carte est celle du calque.
      if (!state.map && layerGroup && layerGroup._map) state.map = layerGroup._map;
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

    // Rangs visibles selon le zoom, puis on retire les noms qui se chevauchent
    // (villes d'abord, lieux-dits en dernier) : la carte reste lisible à tout zoom.
    function syncPlaceLabels() {
      var map = resolveMap();
      if (!map || typeof map.getZoom !== 'function') return;
      var rel = map.getZoom() - (typeof map.getMinZoom === 'function' ? (map.getMinZoom() || 0) : 0);
      var taken = [];
      var entries = state.placeMarkers.slice().sort(function (a, b) {
        return PLACE_RANK[placeKind(a.place.type)] - PLACE_RANK[placeKind(b.place.type)];
      });
      entries.forEach(function (entry) {
        var el = entry.marker.getElement ? entry.marker.getElement() : null;
        if (!el) return;
        var p = entry.place;
        var show = rel >= PLACE_MIN_REL_ZOOM[PLACE_RANK[placeKind(p.type)]];
        if (show) {
          var pt = map.latLngToContainerPoint([p.y, p.x]);
          var sz = placeLabelSize(p);
          var box = [pt.x - sz.w / 2 - 3, pt.y - sz.h / 2 - 2, pt.x + sz.w / 2 + 3, pt.y + sz.h / 2 + 2];
          for (var i = 0; i < taken.length; i++) {
            var o = taken[i];
            if (box[0] < o[2] && box[2] > o[0] && box[1] < o[3] && box[3] > o[1]) { show = false; break; }
          }
          if (show) taken.push(box);
        }
        if (show) el.classList.remove('is-culled'); else el.classList.add('is-culled');
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
      ensurePlaceStyles();

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
          if (!p.name) return;
          var kind = placeKind(p.type);
          var marker = global.L.marker([p.y, p.x], {
            icon: global.L.divIcon({
              className: 'atak-geo-place atak-geo-place--' + kind.toLowerCase(),
              html: '<span class="atak-geo-place__name">' + escapeHtml(p.name) + '</span>',
              iconSize: [0, 0],
            }),
            keyboard: false,
            riseOnHover: true,
            zIndexOffset: (4 - PLACE_RANK[kind]) * 10,
          });
          marker.bindPopup('<strong>' + escapeHtml(p.name) + '</strong><br>' + escapeHtml(p.type));
          marker.addTo(state.placesLayer);
          state.placeMarkers.push({ marker: marker, place: p });
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
