(function () {
  var root = document.getElementById('jnet-map-root');
  var dataEl = document.getElementById('jnet-regions-data');
  if (!root || !dataEl || typeof L === 'undefined') return;

  var regions = [];
  try {
    regions = JSON.parse(dataEl.textContent || '[]');
  } catch (e) {
    regions = [];
  }
  if (!regions.length) return;

  var map = L.map(root, {
    zoomControl: true,
    attributionControl: false,
  }).setView(regions[0].center, regions[0].zoom || 5);

  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    maxZoom: 18,
  }).addTo(map);

  var markers = [];
  regions.forEach(function (r) {
    var m = L.circleMarker(r.center, {
      radius: 7,
      color: '#3db8c5',
      weight: 2,
      fillColor: '#3db8c5',
      fillOpacity: 0.35,
    }).addTo(map);
    m.bindTooltip(r.label, { direction: 'top', opacity: 0.9 });
    markers.push(m);
  });

  function activate(id) {
    var region = regions.find(function (r) { return r.id === id; });
    if (!region) return;
    map.flyTo(region.center, region.zoom || 6, { duration: 0.8 });
    document.querySelectorAll('[data-jnet-region]').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-jnet-region') === id);
    });
    var hint = document.getElementById('jnet-map-hint');
    if (hint) {
      hint.textContent = 'Secteur actif // ' + region.label;
    }
  }

  document.querySelectorAll('[data-jnet-region]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      activate(btn.getAttribute('data-jnet-region'));
    });
  });

  activate(regions[0].id);
})();
