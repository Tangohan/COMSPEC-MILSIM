/**
 * Carte tactique permanente d’un dossier SSE.
 * Place des pings, mémorise la vue, capture un snapshot.
 */
(function () {
  var boot = window.SSE_CASE_MAP;
  if (!boot || typeof L === 'undefined') return;

  var el = document.getElementById('sse-tacmap');
  if (!el) return;

  var state = boot.state || {};
  var features = Array.isArray(boot.features) ? boot.features.slice() : [];
  var canManage = !!boot.canManage;
  var pendingLatLng = null;
  var markersById = {};
  var saveTimer = null;
  var currentBaseLayer = null;
  var storageKey = 'sse-case-basemap:' + String(boot.caseId || 0);

  var BASEMAPS = {
    dark: {
      url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
      opts: { maxZoom: 19, crossOrigin: true, attribution: '&copy; OpenStreetMap &copy; CARTO', subdomains: 'abcd' }
    },
    light: {
      url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
      opts: { maxZoom: 19, crossOrigin: true, attribution: '&copy; OpenStreetMap &copy; CARTO', subdomains: 'abcd' }
    },
    street: {
      url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      opts: { maxZoom: 19, crossOrigin: true, attribution: '&copy; OpenStreetMap' }
    },
    relief: {
      url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
      opts: { maxZoom: 17, crossOrigin: true, attribution: '&copy; OpenStreetMap, SRTM | Style: &copy; OpenTopoMap (CC-BY-SA)' }
    }
  };

  function normalizeBasemap(key) {
    return BASEMAPS[key] ? key : 'dark';
  }

  function readStoredBasemap() {
    try {
      return normalizeBasemap(localStorage.getItem(storageKey) || '');
    } catch (err) {
      return 'dark';
    }
  }

  function writeStoredBasemap(key) {
    try { localStorage.setItem(storageKey, key); } catch (err) {}
  }

  var activeBasemap = normalizeBasemap(state.basemap || readStoredBasemap() || 'dark');

  var map = L.map(el, { zoomControl: true, attributionControl: true })
    .setView([Number(state.center_lat) || 48.8566, Number(state.center_lng) || 2.3522], Number(state.zoom) || 6);

  function applyBasemap(key, persist) {
    var next = normalizeBasemap(key);
    var spec = BASEMAPS[next];
    if (currentBaseLayer) {
      try { map.removeLayer(currentBaseLayer); } catch (err) {}
      currentBaseLayer = null;
    }
    currentBaseLayer = L.tileLayer(spec.url, spec.opts);
    currentBaseLayer.addTo(map);
    activeBasemap = next;
    state.basemap = next;
    var select = document.getElementById('sse-tacmap-basemap');
    if (select && select.value !== next) select.value = next;
    if (persist !== false) writeStoredBasemap(next);
    return next;
  }

  applyBasemap(activeBasemap, false);
  writeStoredBasemap(activeBasemap);

  var featureLayer = L.layerGroup().addTo(map);
  var draftMarker = null;

  function refreshMapSize() {
    map.invalidateSize({ pan: false });
  }

  setTimeout(refreshMapSize, 80);
  setTimeout(refreshMapSize, 320);
  window.addEventListener('resize', refreshMapSize);
  if (window.ResizeObserver) {
    try {
      var ro = new ResizeObserver(function () { refreshMapSize(); });
      ro.observe(el);
    } catch (err) {}
  }

  var basemapSelect = document.getElementById('sse-tacmap-basemap');
  if (basemapSelect) {
    basemapSelect.value = activeBasemap;
    basemapSelect.addEventListener('change', function () {
      applyBasemap(basemapSelect.value, true);
      if (canManage) scheduleSave();
    });
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function pingIcon(color, label) {
    var c = color || '#34d399';
    return L.divIcon({
      className: 'sse-case-ping',
      html: '<span style="display:flex;flex-direction:column;align-items:center;">'
        + '<i style="width:12px;height:12px;border-radius:50%;background:' + esc(c)
        + ';border:2px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.4);"></i>'
        + '<b style="margin-top:2px;font:700 9px/1 ui-sans-serif,system-ui;color:' + esc(c)
        + ';text-shadow:0 0 3px #000;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'
        + esc(String(label || 'Ping').slice(0, 18)) + '</b></span>',
      iconSize: [90, 28],
      iconAnchor: [45, 8]
    });
  }

  function renderMarkers() {
    featureLayer.clearLayers();
    markersById = {};
    features.forEach(function (f) {
      if (f.lat == null || f.lng == null) return;
      var m = L.marker([Number(f.lat), Number(f.lng)], { icon: pingIcon(f.color, f.label) });
      var html = '<strong>' + esc(f.label) + '</strong><br><span>' + esc(f.kind_label || f.kind) + '</span>';
      if (f.note) html += '<p style="margin:.35rem 0 0">' + esc(f.note) + '</p>';
      if (f.arma_x != null && f.arma_y != null) {
        html += '<p style="margin:.35rem 0 0;opacity:.7">Terrain '
          + esc(Math.round(f.arma_x)) + ' / ' + esc(Math.round(f.arma_y)) + '</p>';
      }
      m.bindPopup(html);
      m.addTo(featureLayer);
      markersById[f.id] = m;
    });
    syncList();
  }

  function syncList() {
    var list = document.getElementById('sse-tacmap-list');
    var count = document.getElementById('sse-tacmap-count');
    if (count) count.textContent = String(features.length);
    if (!list) return;
    if (!features.length) {
      list.innerHTML = '<li class="sse-tacmap-list__empty" id="sse-tacmap-empty">Aucun ping pour l’instant.</li>';
      return;
    }
    list.innerHTML = features.map(function (f) {
      var meta = f.kind_label || 'Ping';
      if (f.arma_x != null && f.arma_y != null) {
        meta += ' · terrain ' + Math.round(f.arma_x) + ' / ' + Math.round(f.arma_y);
      } else if (f.lat != null) {
        meta += ' · ' + Number(f.lat).toFixed(4) + ', ' + Number(f.lng).toFixed(4);
      }
      return '<li data-feature-id="' + f.id + '">'
        + '<span class="sse-tacmap-dot" style="background:' + esc(f.color || '#34d399') + '"></span>'
        + '<div><strong>' + esc(f.label) + '</strong><em>' + esc(meta) + '</em></div>'
        + (canManage
          ? '<button type="button" class="btn btn--ghost btn--sm" data-sse-del-feature="' + f.id + '">Retirer</button>'
          : '')
        + '</li>';
    }).join('');
  }

  function currentViewPayload() {
    var c = map.getCenter();
    var atakEl = document.getElementById('sse-atak-layer');
    return {
      center_lat: c.lat,
      center_lng: c.lng,
      zoom: map.getZoom(),
      map_id: Number(state.map_id) || 1,
      basemap: normalizeBasemap(activeBasemap),
      atak_layer_enabled: atakEl ? !!atakEl.checked : !!state.atak_layer_enabled,
      _csrf_token: boot.csrf
    };
  }

  function fillCaptureHidden() {
    var v = currentViewPayload();
    var lat = document.getElementById('sse-tacmap-lat');
    var lng = document.getElementById('sse-tacmap-lng');
    var zoom = document.getElementById('sse-tacmap-zoom');
    var flag = document.getElementById('sse-tacmap-atakflag');
    var basemapField = document.getElementById('sse-tacmap-basemap-field');
    if (lat) lat.value = String(v.center_lat);
    if (lng) lng.value = String(v.center_lng);
    if (zoom) zoom.value = String(v.zoom);
    if (flag) flag.value = v.atak_layer_enabled ? '1' : '0';
    if (basemapField) basemapField.value = String(v.basemap || 'dark');
  }

  function saveView(silent) {
    if (!canManage || !boot.urls || !boot.urls.save) return;
    var body = currentViewPayload();
    fetch(boot.urls.save, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
      body: Object.keys(body).map(function (k) {
        return encodeURIComponent(k) + '=' + encodeURIComponent(body[k] === true ? '1' : (body[k] === false ? '0' : body[k]));
      }).join('&')
    }).then(function (r) {
      return r.json().then(function (data) {
        if (!r.ok) {
          throw new Error((data && data.error) || ('Erreur ' + r.status));
        }
        return data;
      });
    }).then(function (data) {
      if (!silent && data && data.ok) {
        var btn = document.getElementById('sse-tacmap-save-btn');
        if (btn) {
          var prev = btn.textContent;
          btn.textContent = 'Vue mémorisée';
          setTimeout(function () { btn.textContent = prev; }, 1400);
        }
      }
    }).catch(function (err) {
      if (!silent && err && err.message) {
        console.warn('[sse-case-map] sauvegarde vue:', err.message);
      }
    });
  }

  function scheduleSave() {
    if (!canManage) return;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(function () { saveView(true); }, 800);
  }

  map.on('moveend', scheduleSave);
  map.on('zoomend', scheduleSave);

  renderMarkers();

  if (!canManage) return;

  var pendingEl = document.getElementById('sse-tacmap-pending');
  var submitBtn = document.getElementById('sse-ping-submit');
  var pingForm = document.getElementById('sse-tacmap-ping-form');
  var atakToggle = document.getElementById('sse-atak-layer');

  if (atakToggle) {
    atakToggle.addEventListener('change', function () { saveView(true); });
  }

  map.on('click', function (ev) {
    pendingLatLng = ev.latlng;
    if (draftMarker) map.removeLayer(draftMarker);
    draftMarker = L.circleMarker(ev.latlng, {
      radius: 7,
      color: '#34d399',
      fillColor: '#34d399',
      fillOpacity: 0.85,
      weight: 2
    }).addTo(map);
    if (pendingEl) pendingEl.hidden = false;
    if (submitBtn) submitBtn.disabled = false;
  });

  if (pingForm) {
    pingForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!pendingLatLng) {
        if (pendingEl) pendingEl.hidden = false;
        return;
      }
      var label = (document.getElementById('sse-ping-label') || {}).value || 'Ping';
      var note = (document.getElementById('sse-ping-note') || {}).value || '';
      var ax = (document.getElementById('sse-ping-ax') || {}).value;
      var ay = (document.getElementById('sse-ping-ay') || {}).value;
      var view = currentViewPayload();
      var payload = {
        kind: 'ping',
        label: label,
        note: note,
        color: '#34d399',
        lat: pendingLatLng.lat,
        lng: pendingLatLng.lng,
        arma_x: ax !== '' ? ax : '',
        arma_y: ay !== '' ? ay : '',
        center_lat: view.center_lat,
        center_lng: view.center_lng,
        zoom: view.zoom,
        map_id: view.map_id,
        atak_layer_enabled: view.atak_layer_enabled ? '1' : '0',
        _csrf_token: boot.csrf
      };

      submitBtn.disabled = true;
      fetch(boot.urls.add, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
        body: Object.keys(payload).map(function (k) {
          return encodeURIComponent(k) + '=' + encodeURIComponent(payload[k]);
        }).join('&')
      }).then(function (r) {
        return r.json().then(function (data) {
          if (!r.ok) {
            throw new Error((data && data.error) || ('Erreur ' + r.status));
          }
          return data;
        });
      }).then(function (data) {
        if (!data || !data.ok || !data.feature) {
          alert((data && data.error) || 'Impossible d’enregistrer le ping.');
          submitBtn.disabled = false;
          return;
        }
        features.push(data.feature);
        pendingLatLng = null;
        if (draftMarker) { map.removeLayer(draftMarker); draftMarker = null; }
        if (pendingEl) pendingEl.hidden = true;
        document.getElementById('sse-ping-label').value = '';
        document.getElementById('sse-ping-note').value = '';
        document.getElementById('sse-ping-ax').value = '';
        document.getElementById('sse-ping-ay').value = '';
        submitBtn.disabled = true;
        renderMarkers();
      }).catch(function (err) {
        alert((err && err.message) || 'Erreur réseau lors de l’enregistrement du ping.');
        submitBtn.disabled = false;
      });
    });
  }

  document.getElementById('sse-tacmap-list').addEventListener('click', function (e) {
    var btn = e.target.closest('[data-sse-del-feature]');
    if (!btn) return;
    var id = btn.getAttribute('data-sse-del-feature');
    if (!id || !confirm('Retirer ce point du dossier ?')) return;
    var url = boot.urls.del.replace('__ID__', encodeURIComponent(id));
    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
      body: '_csrf_token=' + encodeURIComponent(boot.csrf)
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (!data || !data.ok) return;
      features = features.filter(function (f) { return String(f.id) !== String(id); });
      renderMarkers();
    });
  });

  var saveBtn = document.getElementById('sse-tacmap-save-btn');
  if (saveBtn) saveBtn.addEventListener('click', function () { saveView(false); });

  var captureBtn = document.getElementById('sse-tacmap-capture-btn');
  var form = document.getElementById('sse-tacmap-form');
  var dataInput = document.getElementById('sse-tacmap-data');
  if (captureBtn && form && dataInput) {
    captureBtn.addEventListener('click', function () {
      fillCaptureHidden();
      captureBtn.disabled = true;
      captureBtn.textContent = 'Capture…';
      var size = map.getSize();
      var canvas = document.createElement('canvas');
      canvas.width = size.x;
      canvas.height = size.y;
      var ctx = canvas.getContext('2d');
      ctx.fillStyle = '#0b0f18';
      ctx.fillRect(0, 0, canvas.width, canvas.height);

      el.querySelectorAll('.leaflet-tile-pane img').forEach(function (img) {
        if (!img.complete || !img.naturalWidth) return;
        var t = img.getBoundingClientRect();
        var p = el.getBoundingClientRect();
        try { ctx.drawImage(img, t.left - p.left, t.top - p.top, t.width, t.height); } catch (err) {}
      });

      // Dessine les pings sur la capture
      features.forEach(function (f) {
        if (f.lat == null || f.lng == null) return;
        var pt = map.latLngToContainerPoint([Number(f.lat), Number(f.lng)]);
        ctx.beginPath();
        ctx.fillStyle = f.color || '#34d399';
        ctx.arc(pt.x, pt.y, 5, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 1.5;
        ctx.stroke();
        ctx.fillStyle = '#e2e8f0';
        ctx.font = '700 10px ui-sans-serif, system-ui';
        ctx.fillText(String(f.label || '').slice(0, 22), pt.x + 8, pt.y + 3);
      });

      ctx.fillStyle = 'rgba(194,48,48,.92)';
      ctx.fillRect(0, 0, canvas.width, 22);
      ctx.fillStyle = '#fff';
      ctx.font = '700 11px monospace';
      ctx.fillText('ATHENA // SSE // CAPTURE TACMAP', 10, 15);
      ctx.fillStyle = 'rgba(0,0,0,.55)';
      ctx.fillRect(0, canvas.height - 24, canvas.width, 24);
      ctx.fillStyle = '#c8d4e4';
      ctx.font = '500 11px monospace';
      var c = map.getCenter();
      ctx.fillText(
        'Z' + map.getZoom() + '  ' + c.lat.toFixed(5) + ', ' + c.lng.toFixed(5)
        + '  ' + features.length + ' pt  ' + new Date().toISOString().slice(0, 16).replace('T', ' '),
        10, canvas.height - 8
      );

      try {
        dataInput.value = canvas.toDataURL('image/png');
      } catch (err) {
        captureBtn.disabled = false;
        captureBtn.textContent = 'Capturer la vue';
        alert('Impossible de capturer la carte (restriction navigateur). Réessayez après un instant.');
        return;
      }
      form.submit();
    });
  }
})();
