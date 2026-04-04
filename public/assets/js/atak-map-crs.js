/* COMSPEC ATAK - CRS pour cartes Arma (coordonnées monde) - doit être chargé après Leaflet.
 * Avec factorx = factory = tileWidth / worldSize (ex. 212/30720 pour Altis), 1 unité = 1 mètre :
 * - distance() retourne des mètres, L.circle(..., { radius }) attend un rayon en mètres.
 */
window.MGRS_CRS = function (factorx, factory, tileWidth) {
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
};
