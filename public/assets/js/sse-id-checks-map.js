/**
 * Tableau de bord identité (COMSPEC IDENTITY, Lot 6) : carte des contrôles d'identité selon leur position en jeu
 * (mètres de la carte Arma, repère plan Leaflet), couleur selon le verdict, lien vers chaque contrôle.
 */
(function () {
  var el = document.getElementById('idw-checks-map');
  if (!el || typeof L === 'undefined') return;
  var points = [];
  try {
    points = JSON.parse(el.getAttribute('data-points') || '[]') || [];
  } catch (err) {
    points = [];
  }
  if (!points.length) return;
  var base = el.getAttribute('data-check-url') || '';
  var COLORS = { ok: '#16a34a', warn: '#f59e0b', bad: '#dc2626', none: '#94a3b8' };

  var map = L.map(el, { crs: L.CRS.Simple, minZoom: -6, maxZoom: 4, zoomSnap: 0.25, attributionControl: false });
  var bounds = L.latLngBounds([]);
  points.forEach(function (p) {
    var ll = L.latLng(Number(p.y) || 0, Number(p.x) || 0);
    bounds.extend(ll);
    var tone = p.forgery ? 'bad' : (p.tone || 'none');
    var marker = L.circleMarker(ll, { radius: 7, weight: 2, color: '#0f172a', fillColor: COLORS[tone] || COLORS.none, fillOpacity: 0.9 });
    var box = document.createElement('div');
    var title = document.createElement('strong');
    title.textContent = String(p.ref || '');
    box.appendChild(title);
    var line = document.createElement('div');
    line.textContent = String(p.label || '') + (p.grid ? ' · ' + p.grid : '') + (p.at ? ' · ' + String(p.at).slice(0, 16) : '');
    box.appendChild(line);
    if (base && p.id) {
      var a = document.createElement('a');
      a.href = base + '/' + encodeURIComponent(String(p.id));
      a.textContent = 'Ouvrir le contrôle';
      box.appendChild(a);
    }
    marker.bindPopup(box);
    marker.addTo(map);
  });
  if (bounds.isValid()) {
    map.fitBounds(bounds.pad(0.25), { maxZoom: 1 });
  } else {
    map.setView([0, 0], 0);
  }
  // Quadrillage kilométrique léger pour se repérer.
  var sw = bounds.getSouthWest();
  var ne = bounds.getNorthEast();
  var step = 1000;
  var x0 = Math.floor((sw.lng - 2000) / step) * step;
  var x1 = Math.ceil((ne.lng + 2000) / step) * step;
  var y0 = Math.floor((sw.lat - 2000) / step) * step;
  var y1 = Math.ceil((ne.lat + 2000) / step) * step;
  var style = { color: '#334155', weight: 1, opacity: 0.5, interactive: false };
  if ((x1 - x0) / step > 200 || (y1 - y0) / step > 200) return;
  for (var x = x0; x <= x1; x += step) L.polyline([[y0, x], [y1, x]], style).addTo(map);
  for (var y = y0; y <= y1; y += step) L.polyline([[y, x0], [y, x1]], style).addTo(map);
})();
