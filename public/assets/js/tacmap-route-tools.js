/**
 * Outils itinéraire + ETA Tacmap.
 * Esprit Iceman ATAK_Route (fn_route_findpath / formateta / measureremaining) —
 * MVP : waypoints manuels (pas de graphe routier Arma côté web) + ETA vitesse.
 */
(function (global) {
  'use strict';

  function formatEta(seconds) {
    seconds = Math.max(0, Math.round(seconds || 0));
    var h = Math.floor(seconds / 3600);
    var m = Math.floor((seconds % 3600) / 60);
    var s = seconds % 60;
    function pad(n) { return n < 10 ? '0' + n : String(n); }
    return pad(h) + ':' + pad(m) + ':' + pad(s);
  }

  function dist2d(a, b) {
    var dx = a[0] - b[0];
    var dy = a[1] - b[1];
    return Math.sqrt(dx * dx + dy * dy);
  }

  /** Distance cumulée le long d’une polyline [[x,y],…] */
  function pathLength(points) {
    var total = 0;
    for (var i = 1; i < points.length; i++) {
      total += dist2d(points[i - 1], points[i]);
    }
    return total;
  }

  /**
   * Distance restante depuis une position (Iceman fn_route_measureremaining).
   * points : [[x,y],…]
   */
  function measureRemaining(points, posXY) {
    if (!points || points.length < 2) return 0;
    var bestI = 0;
    var bestD = Infinity;
    var cum = [0];
    for (var i = 1; i < points.length; i++) {
      cum[i] = cum[i - 1] + dist2d(points[i - 1], points[i]);
      var d = dist2d(points[i], posXY);
      if (d < bestD) {
        bestD = d;
        bestI = i;
      }
    }
    var total = cum[cum.length - 1];
    return Math.max(0, total - cum[bestI]);
  }

  /**
   * ETA secondes — Iceman : remaining / max(speed, minSpeed) en m/s.
   * @param {number} remainingM
   * @param {number} speedKph vitesse courante (0 = utiliser min)
   * @param {number} minSpeedKph défaut 4.5 pied / 5 véhicule
   */
  function etaSeconds(remainingM, speedKph, minSpeedKph) {
    var minKph = minSpeedKph != null ? minSpeedKph : 4.5;
    var kph = Math.max(speedKph || 0, minKph, 1);
    var speedMS = kph / 3.6;
    return remainingM / speedMS;
  }

  function bind(map, layerGroups, opts) {
    opts = opts || {};
    if (!map || !global.L) return null;

    if (!layerGroups.route) {
      layerGroups.route = L.layerGroup().addTo(map);
    }

    var state = {
      placing: false,
      points: [], // [[x,y],…]
      speedKph: opts.speedKph || 5,
      mode: opts.mode || 'foot', // foot | vehicle
    };
    var hintEl = opts.hintEl || null;
    var etaEl = opts.etaEl || null;

    function setHint(t) {
      if (hintEl) hintEl.textContent = t || '';
    }

    function setEta(t) {
      if (etaEl) etaEl.textContent = t || '';
    }

    function clearRoute() {
      layerGroups.route.clearLayers();
      state.points = [];
      state.placing = false;
      map.getContainer().style.cursor = '';
      setHint('');
      setEta('');
    }

    function redraw() {
      layerGroups.route.clearLayers();
      if (state.points.length === 0) return;
      var latlngs = state.points.map(function (p) { return [p[1], p[0]]; });
      if (latlngs.length >= 2) {
        L.polyline(latlngs, {
          color: state.mode === 'vehicle' ? '#3af' : '#8c4',
          weight: 4,
          opacity: 0.9,
        }).addTo(layerGroups.route);
      }
      latlngs.forEach(function (ll, i) {
        var label = i === 0 ? 'Départ' : (i === latlngs.length - 1 && latlngs.length > 1 ? 'Arrivée' : 'Point ' + (i + 1));
        L.circleMarker(ll, {
          radius: i === 0 || i === latlngs.length - 1 ? 7 : 5,
          color: '#fff',
          fillColor: i === 0 ? '#2a6' : (i === latlngs.length - 1 ? '#c33' : '#888'),
          fillOpacity: 1,
          weight: 2,
        }).bindPopup(label).addTo(layerGroups.route);
      });

      var total = pathLength(state.points);
      var minKph = state.mode === 'vehicle' ? 5 : 4.5;
      var eta = etaSeconds(total, state.speedKph, minKph);
      setEta(
        'Distance : ' + Math.round(total) + ' m — Arrivée estimée : ' + formatEta(eta) +
        ' (à ' + state.speedKph + ' km/h)'
      );
    }

    function onMapClick(e) {
      if (!state.placing) return;
      state.points.push([e.latlng.lng, e.latlng.lat]);
      redraw();
      setHint(
        state.points.length < 2
          ? 'Cliquez pour ajouter l’arrivée (double-clic pour terminer).'
          : 'Point ajouté. Double-clic pour terminer l’itinéraire.'
      );
    }

    function onDblClick(e) {
      if (!state.placing) return;
      L.DomEvent.stop(e);
      state.placing = false;
      map.getContainer().style.cursor = '';
      if (state.points.length < 2) {
        setHint('Itinéraire incomplet — ajoutez au moins deux points.');
        return;
      }
      setHint('Itinéraire prêt. Ajustez la vitesse si besoin.');
      redraw();
    }

    map.on('click', onMapClick);
    map.on('dblclick', onDblClick);

    function startRoute(mode) {
      clearRoute();
      state.mode = mode === 'vehicle' ? 'vehicle' : 'foot';
      state.speedKph = state.mode === 'vehicle' ? 40 : 5;
      state.placing = true;
      map.getContainer().style.cursor = 'crosshair';
      setHint('Cliquez le départ, puis les points suivants. Double-clic pour terminer.');
    }

    return {
      startFoot: function () { startRoute('foot'); },
      startVehicle: function () { startRoute('vehicle'); },
      clear: clearRoute,
      setSpeedKph: function (v) {
        state.speedKph = Math.max(1, Number(v) || 5);
        redraw();
      },
      getPoints: function () { return state.points.slice(); },
      measureRemaining: measureRemaining,
      formatEta: formatEta,
      etaSeconds: etaSeconds,
    };
  }

  global.TacmapRouteTools = {
    bind: bind,
    formatEta: formatEta,
    pathLength: pathLength,
    measureRemaining: measureRemaining,
    etaSeconds: etaSeconds,
  };
})(window);
