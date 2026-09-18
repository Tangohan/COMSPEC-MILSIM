/* Théâtre Arma (mètres) → emprise locale à l’échelle WGS84 pour MapLibre / deck.gl.
   1 m ≈ 1 m près de l’équateur. Réutilise offsetX / offsetY / worldSize de ATAK_MAP_CONFIG. */
window.OverwatchTheaterProjection = (function () {
  'use strict';

  var METERS_PER_DEGREE = 111319.49079327358;
  var MERCATOR_MPP0 = 156543.03392804097;

  function create(raw) {
    var cfg = raw || window.ATAK_MAP_CONFIG || {};
    var offsetX = Number(cfg.offsetX != null ? cfg.offsetX : (cfg.offset_x != null ? cfg.offset_x : 0)) || 0;
    var offsetY = Number(cfg.offsetY != null ? cfg.offsetY : (cfg.offset_y != null ? cfg.offset_y : 0)) || 0;
    var worldSize = Number(cfg.worldSize || 30720) || 30720;
    var tileSize = Number(cfg.tileSize || 212) || 212;
    var crs = cfg.crs || {};
    var factorx = crs.factorx != null ? Number(crs.factorx) : (tileSize / worldSize);
    var factory = crs.factory != null ? Number(crs.factory) : factorx;
    var tileWidth = crs.tileWidth != null ? Number(crs.tileWidth) : tileSize;
    var minZoom = cfg.minZoom != null ? Number(cfg.minZoom) : 0;
    var maxZoom = cfg.maxZoom != null ? Number(cfg.maxZoom) : 6;

    function worldToLngLat(x, y) {
      return [(Number(x) + offsetX) / METERS_PER_DEGREE, (Number(y) + offsetY) / METERS_PER_DEGREE];
    }

    function lngLatToWorld(lng, lat) {
      return {
        x: Number(lng) * METERS_PER_DEGREE - offsetX,
        y: Number(lat) * METERS_PER_DEGREE - offsetY
      };
    }

    function leafletToLngLat(ll) {
      if (!ll) return null;
      var world = { x: Number(ll.lng) - offsetX, y: Number(ll.lat) - offsetY };
      var pair = worldToLngLat(world.x, world.y);
      return { lng: pair[0], lat: pair[1] };
    }

    function lngLatToLeaflet(lng, lat) {
      var w = lngLatToWorld(lng, lat);
      return { lat: w.y + offsetY, lng: w.x + offsetX };
    }

    function boundsLngLat() {
      var sw = worldToLngLat(0, 0);
      var ne = worldToLngLat(worldSize, worldSize);
      return [sw, ne];
    }

    function maxBounds() {
      var pad = worldSize * 0.08;
      var sw = worldToLngLat(-pad, -pad);
      var ne = worldToLngLat(worldSize + pad, worldSize + pad);
      return [
        [sw[0], sw[1]],
        [ne[0], ne[1]]
      ];
    }

    function centerLngLat() {
      return worldToLngLat(worldSize / 2, worldSize / 2);
    }

    function leafletZoomToMaplibre(z) {
      var armaMpp = worldSize / (Math.pow(2, Number(z) || 0) * tileSize);
      if (!(armaMpp > 0)) return 12;
      return Math.log2(MERCATOR_MPP0 / armaMpp);
    }

    function maplibreZoomToLeaflet(z) {
      var mpp = MERCATOR_MPP0 / Math.pow(2, Number(z) || 0);
      if (!(mpp > 0)) return 3;
      return Math.log2(worldSize / (mpp * tileSize));
    }

    function mercatorTileLngLat(z, x, y) {
      var n = Math.pow(2, z);
      var west = x / n * 360 - 180;
      var east = (x + 1) / n * 360 - 180;
      function mercYToLat(ty) {
        var t = Math.min(1, Math.max(0, ty));
        return Math.atan(Math.sinh(Math.PI * (1 - 2 * t))) * 180 / Math.PI;
      }
      return { west: west, south: mercYToLat((y + 1) / n), east: east, north: mercYToLat(y / n) };
    }

    function mercatorTileWorld(z, x, y) {
      var ll = mercatorTileLngLat(z, x, y);
      var sw = lngLatToWorld(ll.west, ll.south);
      var ne = lngLatToWorld(ll.east, ll.north);
      return {
        minX: Math.min(sw.x, ne.x),
        minY: Math.min(sw.y, ne.y),
        maxX: Math.max(sw.x, ne.x),
        maxY: Math.max(sw.y, ne.y)
      };
    }

    function armaTilesForWorld(bbox, armaZoom, spec) {
      var s = spec || {};
      var fx = Number(s.factorX != null ? s.factorX : factorx) || factorx;
      var fy = Number(s.factorY != null ? s.factorY : factory) || factory;
      var ts = Number(s.tileSize != null ? s.tileSize : tileSize) || tileSize;
      var tw = Number(s.tileWidth != null ? s.tileWidth : tileWidth) || ts;
      var z = Math.max(0, Math.min(8, Math.round(armaZoom)));
      var scale = Math.pow(2, z);
      function pxPy(wx, wy) {
        var lng = wx + offsetX;
        var lat = wy + offsetY;
        return { px: scale * fx * lng, py: scale * (-fy * lat + tw) };
      }
      var a = pxPy(bbox.minX, bbox.maxY);
      var b = pxPy(bbox.maxX, bbox.minY);
      var minTx = Math.floor(Math.min(a.px, b.px) / ts);
      var maxTx = Math.floor(Math.max(a.px, b.px) / ts);
      var minTy = Math.floor(Math.min(a.py, b.py) / ts);
      var maxTy = Math.floor(Math.max(a.py, b.py) / ts);
      var tiles = [];
      var tx, ty;
      for (tx = minTx; tx <= maxTx; tx++) {
        for (ty = minTy; ty <= maxTy; ty++) {
          var x0 = (tx * ts) / (scale * fx) - offsetX;
          var x1 = ((tx + 1) * ts) / (scale * fx) - offsetX;
          var lat0 = (tw - (ty * ts) / scale) / fy - offsetY;
          var lat1 = (tw - ((ty + 1) * ts) / scale) / fy - offsetY;
          tiles.push({
            z: z,
            x: tx,
            y: ty,
            minX: Math.min(x0, x1),
            maxX: Math.max(x0, x1),
            minY: Math.min(lat0, lat1),
            maxY: Math.max(lat0, lat1)
          });
        }
      }
      return tiles;
    }

    function pickArmaZoom(metersPerPixel, spec) {
      var s = spec || {};
      var ts = Number(s.tileSize != null ? s.tileSize : tileSize) || tileSize;
      var maxZ = s.maxZoom != null ? Number(s.maxZoom) : maxZoom;
      var minZ = s.minZoom != null ? Number(s.minZoom) : minZoom;
      var world = s.factorX ? (ts / Number(s.factorX)) : worldSize;
      var z = Math.log2(world / (Math.max(1, metersPerPixel) * ts));
      return Math.max(minZ, Math.min(maxZ, Math.round(z)));
    }

    function tileUrl(pattern, z, x, y) {
      return String(pattern || '')
        .replace('{z}', String(z))
        .replace('{x}', String(x))
        .replace('{y}', String(y));
    }

    return {
      offsetX: offsetX,
      offsetY: offsetY,
      worldSize: worldSize,
      tileSize: tileSize,
      factorx: factorx,
      factory: factory,
      tileWidth: tileWidth,
      minZoom: minZoom,
      maxZoom: maxZoom,
      tilePattern: cfg.tilePattern || '',
      METERS_PER_DEGREE: METERS_PER_DEGREE,
      worldToLngLat: worldToLngLat,
      lngLatToWorld: lngLatToWorld,
      leafletToLngLat: leafletToLngLat,
      lngLatToLeaflet: lngLatToLeaflet,
      boundsLngLat: boundsLngLat,
      maxBounds: maxBounds,
      centerLngLat: centerLngLat,
      leafletZoomToMaplibre: leafletZoomToMaplibre,
      maplibreZoomToLeaflet: maplibreZoomToLeaflet,
      mercatorTileLngLat: mercatorTileLngLat,
      mercatorTileWorld: mercatorTileWorld,
      armaTilesForWorld: armaTilesForWorld,
      pickArmaZoom: pickArmaZoom,
      tileUrl: tileUrl
    };
  }

  return { create: create, METERS_PER_DEGREE: METERS_PER_DEGREE };
})();
