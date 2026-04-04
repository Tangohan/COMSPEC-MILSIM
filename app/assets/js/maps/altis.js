/* Config carte Arma 3 Altis - COMSPEC ATAK */
(function () {
  function MGRS_CRS(factorx, factory, tileWidth) {
    return L.extend({}, L.CRS.Simple, {
      projection: L.Projection.LonLat,
      transformation: new L.Transformation(factorx, 0, -factory, tileWidth),
      scale: function (zoom) { return Math.pow(2, zoom); },
      zoom: function (scale) { return Math.log(scale) / Math.LN2; },
      distance: function (latlng1, latlng2) {
        var dx = latlng2.lng - latlng1.lng, dy = latlng2.lat - latlng1.lat;
        return Math.sqrt(dx * dx + dy * dy);
      },
      infinite: true
    });
  }
  window.Arma3Map = window.Arma3Map || { Maps: {} };
  window.Arma3Map.Maps.altis = {
    CRS: MGRS_CRS(0.006839, 0.006836, 212),
    tilePattern: 'ressources/MapViewers/maps/altis/{z}/{x}/{y}.png',
    maxZoom: 6,
    minZoom: 0,
    defaultZoom: 3,
    attribution: '&copy; Bohemia Interactive',
    tileSize: 212,
    center: [15000, 15000],
    worldSize: 30720,
    title: 'Altis',
    cities: [
      { name: 'Therisa', x: 10618.9, y: 12237.3 },
      { name: 'Kavala', x: 3458.95, y: 12966.4 },
      { name: 'Pyrgos', x: 16780.6, y: 12604.5 }
    ]
  };
})();
