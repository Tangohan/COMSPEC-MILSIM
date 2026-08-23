/**
 * Outils terrain Tacmap — viewshed + heatmap.
 * Algorithme inspiré Iceman ATAK_Elevation (fn_elev_computeviewshed / computeheatmap).
 * Relief synthétique local (pas de DEM Arma côté web) — utile pour l’analyse ops MVP.
 */
(function (global) {
  'use strict';

  /**
   * Hauteur terrain synthétique (m) pour cartes Arma CRS (x,y = coords monde).
   * Crédit conceptuel : Iceman viewshed utilise getTerrainHeightASL ; ici approximation.
   */
  function syntheticHeight(x, y) {
    if (window.ATAKTerrain && typeof window.ATAKTerrain.heightAt === 'function' && window.ATAKTerrain.isReady && window.ATAKTerrain.isReady()) {
      var z = window.ATAKTerrain.heightAt(x, y);
      if (z != null && isFinite(z)) return z;
    }
    var h = 20;
    h += 35 * Math.sin(x / 420) * Math.cos(y / 510);
    h += 18 * Math.sin((x + y) / 280);
    h += 12 * Math.cos(x / 180) * Math.sin(y / 220);
    // Collines locales
    h += 45 * Math.exp(-Math.pow((x % 2000) - 1000, 2) / 180000)
           * Math.exp(-Math.pow((y % 2000) - 1000, 2) / 180000);
    return Math.max(0, h);
  }

  /**
   * Viewshed radial (Iceman fn_elev_computeviewshed.sqf).
   * @param {[number,number]} center [x, y] monde Arma (Leaflet latlng = [y,x])
   * @param {number} heightM hauteur observateur au-dessus du sol
   * @param {number} radiusM
   * @returns {{ center, radius, segments, visibleCount, deadCount }}
   */
  function computeViewshed(center, heightM, radiusM) {
    var cx = center[0];
    var cy = center[1];
    var height = Math.max(1, heightM || 2);
    var radius = Math.max(100, Math.min(3000, radiusM || 500));
    var bearingStep = radius <= 800 ? 5 : 8;
    var rangeStep = Math.max(15, Math.min(40, radius / 36));
    var observerASL = syntheticHeight(cx, cy) + height;
    var segments = [];
    var visibleCount = 0;
    var deadCount = 0;

    function posAt(dist, bearingDeg) {
      var rad = (bearingDeg * Math.PI) / 180;
      return [cx + Math.sin(rad) * dist, cy + Math.cos(rad) * dist];
    }

    for (var bearing = 0; bearing < 360; bearing += bearingStep) {
      var maxAngle = -1e9;
      var halfBearing = bearingStep / 2;
      for (var dist = rangeStep; dist <= radius; dist += rangeStep) {
        var p = posAt(dist, bearing);
        var inner = Math.max(0, dist - rangeStep);
        var outer = Math.min(radius, dist);
        var targetASL = syntheticHeight(p[0], p[1]) + 1.5;
        var angle = (targetASL - observerASL) / dist;
        var visible = angle >= maxAngle - 0.002;
        if (angle > maxAngle) maxAngle = angle;
        if (visible) visibleCount++;
        else deadCount++;

        if (visible) {
          var left = bearing - halfBearing;
          var right = bearing + halfBearing;
          var points;
          if (inner <= 0) {
            points = [
              [cy, cx],
              (function () { var q = posAt(outer, left); return [q[1], q[0]]; })(),
              (function () { var q = posAt(outer, right); return [q[1], q[0]]; })(),
            ];
          } else {
            var a = posAt(inner, left);
            var b = posAt(outer, left);
            var c = posAt(outer, right);
            var d = posAt(inner, right);
            points = [
              [a[1], a[0]],
              [b[1], b[0]],
              [c[1], c[0]],
              [d[1], d[0]],
            ];
          }
          segments.push(points);
        }
      }
    }

    return {
      center: center,
      radius: radius,
      segments: segments,
      visibleCount: visibleCount,
      deadCount: deadCount,
    };
  }

  /**
   * Heatmap altitudes (Iceman fn_elev_computeheatmap.sqf).
   */
  function computeHeatmap(center, sizeM, sampleM) {
    var cx = center[0];
    var cy = center[1];
    var size = Math.max(250, Math.min(5000, sizeM || 1000));
    var sample = Math.max(25, Math.min(250, sampleM || 80));
    var half = size / 2;
    var cells = [];
    var minH = Infinity;
    var maxH = -Infinity;
    var raw = [];

    for (var dx = -half; dx <= half; dx += sample) {
      for (var dy = -half; dy <= half; dy += sample) {
        var x = cx + dx;
        var y = cy + dy;
        var h = syntheticHeight(x, y);
        raw.push({ x: x, y: y, h: h });
        if (h < minH) minH = h;
        if (h > maxH) maxH = h;
      }
    }
    var range = Math.max(1, maxH - minH);
    raw.forEach(function (c) {
      var t = (c.h - minH) / range;
      var color = heatmapColor(t);
      cells.push({
        latlng: [c.y, c.x],
        color: color,
        sample: sample,
        height: c.h,
      });
    });

    return { cells: cells, minH: minH, maxH: maxH };
  }

  function heatmapColor(t) {
    // bleu → cyan → vert → orange (Iceman palette simplifiée)
    var r, g, b;
    if (t < 0.33) {
      var u = t / 0.33;
      r = 30; g = Math.round(80 + 120 * u); b = Math.round(200 - 80 * u);
    } else if (t < 0.66) {
      var u2 = (t - 0.33) / 0.33;
      r = Math.round(30 + 100 * u2); g = Math.round(200 - 40 * u2); b = Math.round(120 - 80 * u2);
    } else {
      var u3 = (t - 0.66) / 0.34;
      r = Math.round(130 + 100 * u3); g = Math.round(160 - 80 * u3); b = 40;
    }
    return 'rgba(' + r + ',' + g + ',' + b + ',0.46)';
  }

  /**
   * Branche les outils sur une instance Tacmap (map Leaflet + layerGroups).
   */
  function bind(map, layerGroups, opts) {
    opts = opts || {};
    if (!map || !global.L) return null;

    if (!layerGroups.elevation) {
      layerGroups.elevation = L.layerGroup().addTo(map);
    }

    var state = {
      mode: null, // 'viewshed' | 'heatmap'
      heightM: opts.heightM || 2,
      radiusM: opts.radiusM || 500,
      heatmapSizeM: opts.heatmapSizeM || 1000,
    };

    var hintEl = opts.hintEl || null;

    function setHint(text) {
      if (hintEl) hintEl.textContent = text || '';
    }

    function clearElevation() {
      layerGroups.elevation.clearLayers();
      setHint('');
    }

    function drawViewshed(centerXY) {
      clearElevation();
      var result = computeViewshed(centerXY, state.heightM, state.radiusM);
      // Zone morte (cercle rouge)
      L.circle([centerXY[1], centerXY[0]], {
        radius: result.radius,
        color: '#c44',
        fillColor: '#a22',
        fillOpacity: 0.18,
        weight: 1,
        interactive: false,
      }).addTo(layerGroups.elevation);
      result.segments.forEach(function (pts) {
        L.polygon(pts, {
          color: '#2a8',
          fillColor: '#3c9',
          fillOpacity: 0.35,
          weight: 0,
          interactive: false,
        }).addTo(layerGroups.elevation);
      });
      L.circleMarker([centerXY[1], centerXY[0]], {
        radius: 6,
        color: '#fff',
        fillColor: '#1a6',
        fillOpacity: 1,
        weight: 2,
      }).bindPopup('Point d’observation').addTo(layerGroups.elevation);
      setHint(
        'Zone visible : ' + result.visibleCount + ' échantillons — zones masquées : ' + result.deadCount +
        ' (relief approximatif). ' + circleReadout(result.radius)
      );
    }

    function circleReadout(radiusM) {
      var r = Math.max(0, Number(radiusM) || 0);
      var speedEl = opts.speedEl || document.getElementById('tacmap-tool-speed');
      var speed = speedEl ? Math.max(0.1, parseFloat(speedEl.value) || 5) : 5;
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
      return 'Superficie : ' + areaLabel + ' · Délai jusqu’au bord : ' + delayLabel +
        ' (à ' + String(speed).replace('.', ',') + ' km/h)';
    }

    function drawHeatmap(centerXY) {
      clearElevation();
      var result = computeHeatmap(centerXY, state.heatmapSizeM, 80);
      var half = (result.cells[0] && result.cells[0].sample ? result.cells[0].sample : 80) * 0.55;
      result.cells.forEach(function (cell) {
        var lat = cell.latlng[0];
        var lng = cell.latlng[1];
        L.rectangle(
          [[lat - half, lng - half], [lat + half, lng + half]],
          {
            color: cell.color,
            fillColor: cell.color,
            fillOpacity: 1,
            weight: 0,
            interactive: false,
          }
        ).addTo(layerGroups.elevation);
      });
      setHint(
        'Carte des hauteurs (approx.) — bas : ' + Math.round(result.minH) + ' m, haut : ' + Math.round(result.maxH) + ' m.'
      );
    }

    function onMapClick(e) {
      if (!state.mode) return;
      // CRS Arma : lat = y, lng = x
      var xy = [e.latlng.lng, e.latlng.lat];
      if (state.mode === 'viewshed') drawViewshed(xy);
      else if (state.mode === 'heatmap') drawHeatmap(xy);
      state.mode = null;
      map.getContainer().style.cursor = '';
    }

    map.on('click', onMapClick);

    function startViewshed() {
      state.mode = 'viewshed';
      map.getContainer().style.cursor = 'crosshair';
      setHint('Cliquez sur la carte pour placer le point d’observation.');
    }

    function startHeatmap() {
      state.mode = 'heatmap';
      map.getContainer().style.cursor = 'crosshair';
      setHint('Cliquez sur la carte pour centrer la carte des hauteurs.');
    }

    return {
      startViewshed: startViewshed,
      startHeatmap: startHeatmap,
      clear: clearElevation,
      setHeightM: function (v) { state.heightM = Math.max(1, Number(v) || 2); },
      setRadiusM: function (v) { state.radiusM = Math.max(100, Math.min(3000, Number(v) || 500)); },
      computeViewshed: computeViewshed,
      computeHeatmap: computeHeatmap,
    };
  }

  global.TacmapTerrainTools = {
    bind: bind,
    computeViewshed: computeViewshed,
    computeHeatmap: computeHeatmap,
    syntheticHeight: syntheticHeight,
  };
})(window);
