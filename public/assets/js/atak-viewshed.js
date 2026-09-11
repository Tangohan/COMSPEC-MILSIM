/* Calques de vue terrain (viewshed) publiés depuis le jeu → carte poste */
window.ATAKViewshed = (function () {
  var layer = null;
  var lastFp = '';
  var timer = null;

  function getApiBase() {
    return window.ATAKSocket ? window.ATAKSocket.getApiBase() : '';
  }
  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }
  function getMap() {
    return window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
  }
  function armaToLatLng(x, y) {
    if (window.ATAKMap && typeof window.ATAKMap.latLngFromWorld === 'function') {
      return window.ATAKMap.latLngFromWorld(x, y);
    }
    return null;
  }

  function ensureLayer() {
    var map = getMap();
    if (!map || !window.L) return null;
    if (!layer) {
      layer = L.layerGroup().addTo(map);
    }
    return layer;
  }

  function clear() {
    if (layer) layer.clearLayers();
  }

  function drawOverlays(list) {
    var lg = ensureLayer();
    if (!lg) return;
    lg.clearLayers();
    (list || []).forEach(function (o) {
      if (!o) return;
      var x = Number(o.center_x);
      var y = Number(o.center_y);
      var r = Number(o.radius_m) || 500;
      var ll = armaToLatLng(x, y);
      if (!ll) return;
      var circle = L.circle(ll, {
        radius: Math.max(50, Math.min(5000, r)),
        color: '#38bdf8',
        weight: 2,
        fillColor: '#0ea5e9',
        fillOpacity: 0.12,
        className: 'atak-viewshed-circle'
      });
      var cs = String(o.call_sign || 'Opérateur');
      circle.bindTooltip('Zone de vue — ' + cs, { sticky: true });
      lg.addLayer(circle);
      lg.addLayer(L.circleMarker(ll, {
        radius: 5,
        color: '#38bdf8',
        fillColor: '#e0f2fe',
        fillOpacity: 0.9,
        weight: 2
      }));
    });
  }

  function fetchOverlays() {
    var base = getApiBase();
    if (!base) return;
    fetch(base + '/api/atak/viewshed?mapId=' + getMapId(), { credentials: 'include' })
      .then(function (r) { return r.ok ? r.json() : { overlays: [] }; })
      .then(function (data) {
        var list = (data && data.overlays) || [];
        var fp = list.map(function (o) {
          return (o && o.id) + ':' + (o && o.updated_at);
        }).join('|');
        if (fp === lastFp) return;
        lastFp = fp;
        drawOverlays(list);
      })
      .catch(function () { /* ignore */ });
  }

  function start() {
    if (timer) return;
    fetchOverlays();
    timer = setInterval(fetchOverlays, 8000);
  }

  document.addEventListener('DOMContentLoaded', function () {
    setTimeout(start, 2500);
  });

  return { fetchOverlays: fetchOverlays, start: start, clear: clear };
})();
