/* COMSPEC ATAK - Map shapes (live drawing overlay) */
window.ATAKMapShapes = (function () {
  var shapes = [];
  var layer = null;

  function getApiBase() {
    return window.ATAKSocket ? window.ATAKSocket.getApiBase() : '';
  }
  function getMapId() {
    return window.ATAKSocket ? window.ATAKSocket.getMapId() : 1;
  }

  function fetchShapes() {
    var base = getApiBase();
    if (!base) return;
    var url = base + '/api/map-shapes?mapId=' + getMapId();
    fetch(url).then(function (r) { return r.json(); }).then(function (data) {
      shapes = Array.isArray(data) ? data : [];
      if (window.ATAKMap && window.ATAKMap.getMap) {
        var map = window.ATAKMap.getMap();
        if (map) {
          if (layer) map.removeLayer(layer);
          layer = L.layerGroup();
          shapes.forEach(function (s) {
            var geom = s.geometry || {};
            var center = geom.center;
            var radius = geom.radius || 100;
            if (center && center.length >= 2) {
              var circle = L.circle([center[1], center[0]], { radius: radius, color: s.color || '#ef4444', fillOpacity: s.fill_opacity || 0.15, weight: s.stroke || 2 });
              circle.bindPopup((s.label || s.type || '') + ' (' + (s.meta && s.meta.category || '') + ')');
              layer.addLayer(circle);
            }
          });
          layer.addTo(map);
        }
      }
    }).catch(function () {});
  }

  return { fetchShapes: fetchShapes, getShapes: function () { return shapes; } };
})();
