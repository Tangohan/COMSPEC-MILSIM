/* COMSPEC ATAK — Outils carte (grille, suivi, mesure, vision nocturne) */
window.ATAKMapTools = (function () {
  var followOn = false;
  var measureOn = false;
  var nvgOn = false;
  var measurePoints = [];
  var measureLayer = null;
  var measureLine = null;
  var measureMarkers = [];
  var followTimer = null;
  var boundMap = null;
  var toastTimer = null;

  function getMap() {
    return window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
  }

  function getWrap() {
    return document.querySelector('.atak-map-wrap');
  }

  function setToolActive(tool, on) {
    var btn = document.querySelector('#atak-map-tools [data-tool="' + tool + '"]');
    if (!btn) return;
    btn.classList.toggle('is-active', !!on);
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
  }

  function toast(msg) {
    var el = document.getElementById('atak-map-tools-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'atak-map-tools-toast';
      el.className = 'atak-map-tools-toast';
      el.setAttribute('role', 'status');
      var wrap = getWrap();
      if (wrap) wrap.appendChild(el);
      else document.body.appendChild(el);
    }
    el.textContent = msg || '';
    el.hidden = !msg;
    clearTimeout(toastTimer);
    if (msg) {
      toastTimer = setTimeout(function () {
        el.hidden = true;
        el.textContent = '';
      }, 3200);
    }
  }

  function updateHudMeasure(text) {
    var v = document.querySelector('[data-hud-measure]');
    if (v) v.textContent = text || '—';
    var row = document.querySelector('[data-hud-measure-row]');
    if (row) row.hidden = !text;
  }

  function updateHudZoom() {
    var map = getMap();
    var v = document.querySelector('[data-hud-zoom]');
    if (v && map) v.textContent = 'Z' + map.getZoom();
  }

  function updateHudContacts() {
    var v = document.querySelector('[data-hud-contacts]');
    if (!v) return;
    var n = 0;
    if (window.ATAKUnits && window.ATAKUnits.getUnits) {
      var units = window.ATAKUnits.getUnits() || [];
      units.forEach(function (u) {
        var live = window.ATAKUnits.resolveLiveStatus
          ? window.ATAKUnits.resolveLiveStatus(u)
          : String((u && u.status) || '').toLowerCase();
        if (live !== 'offline') n += 1;
      });
    }
    v.textContent = String(n);
  }

  function formatDistance(meters) {
    if (!isFinite(meters) || meters < 0) return '—';
    if (meters < 1000) return Math.round(meters) + ' m';
    return (meters / 1000).toFixed(meters < 10000 ? 2 : 1) + ' km';
  }

  function formatBearing(deg) {
    var d = ((Math.round(deg) % 360) + 360) % 360;
    return String(d).padStart(3, '0') + '°';
  }

  /** Cap militaire : 0 = nord (axe Y), croissant vers l’est (axe X). */
  function bearingBetween(a, b) {
    var dx = b.lng - a.lng;
    var dy = b.lat - a.lat;
    var rad = Math.atan2(dx, dy);
    return (rad * 180 / Math.PI + 360) % 360;
  }

  function distanceMeters(a, b) {
    return Math.hypot(b.lng - a.lng, b.lat - a.lat);
  }

  function parseGridInput(raw) {
    var s = String(raw || '').trim().replace(/[,;/|]+/g, ' ').replace(/\s+/g, ' ');
    var m = s.match(/^(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)$/);
    if (!m) return null;
    return { x: parseFloat(m[1]), y: parseFloat(m[2]) };
  }

  function unitPos(u) {
    if (!u) return null;
    var x = u.pos_x != null ? parseFloat(u.pos_x) : NaN;
    var y = u.pos_y != null ? parseFloat(u.pos_y) : NaN;
    if (isNaN(x) || isNaN(y) || (Math.abs(x) < 0.5 && Math.abs(y) < 0.5)) {
      var parts = String(u.grid_ref || '').trim().split(/\s+/);
      x = parseFloat(parts[0]);
      y = parseFloat(parts[1]);
    }
    if (isNaN(x) || isNaN(y) || (Math.abs(x) < 0.5 && Math.abs(y) < 0.5)) return null;
    return { x: x, y: y };
  }

  function findSelfUnit() {
    var user = window.ATAK_USER || {};
    var callsigns = [];
    [user.callsign, user.armaCallsign].forEach(function (c) {
      var k = String(c || '').toUpperCase().trim();
      if (k && callsigns.indexOf(k) < 0) callsigns.push(k);
    });
    var steam = String(user.steamId || '').trim();
    var units = (window.ATAKUnits && window.ATAKUnits.getUnits) ? window.ATAKUnits.getUnits() : [];
    var i;
    for (i = 0; i < units.length; i++) {
      var u = units[i];
      var cs = String(u.call_sign || '').toUpperCase().trim();
      if (cs && callsigns.indexOf(cs) >= 0) return u;
    }
    if (steam) {
      for (i = 0; i < units.length; i++) {
        var u2 = units[i];
        var sid = String(u2.steam_id || u2.steamId || '').trim();
        if (sid && sid === steam) return u2;
      }
    }
    return null;
  }

  function centerOnSelf(announce) {
    var u = findSelfUnit();
    var pos = unitPos(u);
    if (!pos || !window.ATAKMap || !window.ATAKMap.centerOn) {
      if (announce) toast('Position introuvable — vérifiez la liaison en jeu.');
      return false;
    }
    window.ATAKMap.centerOn(pos.y, pos.x);
    if (announce) {
      var label = (u && u.call_sign) ? String(u.call_sign) : 'votre unité';
      toast('Centré sur ' + label);
    }
    return true;
  }

  function setFollow(on) {
    followOn = !!on;
    setToolActive('follow', followOn);
    clearInterval(followTimer);
    followTimer = null;
    if (followOn) {
      centerOnSelf(true);
      followTimer = setInterval(function () {
        if (!followOn) return;
        centerOnSelf(false);
      }, 2000);
    }
  }

  function clearMeasureGraphics() {
    var map = getMap();
    measureMarkers.forEach(function (m) {
      try { if (map) map.removeLayer(m); } catch (e) {}
    });
    measureMarkers = [];
    if (measureLine && map) {
      try { map.removeLayer(measureLine); } catch (e) {}
    }
    measureLine = null;
    if (measureLayer && map) {
      try { map.removeLayer(measureLayer); } catch (e) {}
    }
    measureLayer = null;
  }

  function stopMeasure(keepResult) {
    measureOn = false;
    measurePoints = [];
    setToolActive('measure', false);
    var map = getMap();
    if (map && map.getContainer) {
      map.getContainer().classList.remove('atak-map--measuring');
    }
    if (!keepResult) {
      clearMeasureGraphics();
      updateHudMeasure('');
    }
  }

  function ensureMeasureLayer() {
    var map = getMap();
    if (!map) return null;
    if (!measureLayer) measureLayer = L.layerGroup().addTo(map);
    return measureLayer;
  }

  function redrawMeasure() {
    var layer = ensureMeasureLayer();
    if (!layer || measurePoints.length === 0) return;
    clearMeasureGraphics();
    measureLayer = layer;
    measurePoints.forEach(function (ll, idx) {
      var marker = L.circleMarker(ll, {
        radius: 5,
        color: '#fbbf24',
        weight: 2,
        fillColor: '#0f172a',
        fillOpacity: 0.9,
        interactive: false
      });
      marker.bindTooltip(idx === 0 ? 'A' : 'B', {
        permanent: true,
        direction: 'top',
        className: 'atak-measure-tip',
        offset: [0, -6]
      });
      marker.addTo(layer);
      measureMarkers.push(marker);
    });
    if (measurePoints.length === 2) {
      measureLine = L.polyline(measurePoints, {
        color: '#fbbf24',
        weight: 2,
        dashArray: '6 5',
        opacity: 0.95,
        interactive: false
      }).addTo(layer);
      var dist = distanceMeters(measurePoints[0], measurePoints[1]);
      var brg = bearingBetween(measurePoints[0], measurePoints[1]);
      updateHudMeasure(formatDistance(dist) + ' · ' + formatBearing(brg));
      toast('Mesure : ' + formatDistance(dist) + ' — cap ' + formatBearing(brg));
    }
  }

  function onMapClickMeasure(e) {
    if (!measureOn || !e || !e.latlng) return;
    if (measurePoints.length >= 2) {
      measurePoints = [];
      clearMeasureGraphics();
      updateHudMeasure('');
    }
    measurePoints.push(e.latlng);
    redrawMeasure();
    if (measurePoints.length === 1) {
      toast('Cliquez le second point pour mesurer.');
    }
  }

  function onMapDblClick(e) {
    if (!e || !e.latlng || measureOn) return;
    var gx = Math.round(e.latlng.lng);
    var gy = Math.round(e.latlng.lat);
    var text = gx + ' ' + gy;
    var done = function () {
      toast('Grille copiée : ' + text);
      var v = document.querySelector('[data-hud-grid]');
      if (v) v.textContent = text;
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(function () {
        toast('Grille : ' + text);
      });
      return;
    }
    toast('Grille : ' + text);
  }

  function startMeasure() {
    if (measureOn) {
      stopMeasure(false);
      toast('Mesure annulée.');
      return;
    }
    setFollow(false);
    measureOn = true;
    measurePoints = [];
    clearMeasureGraphics();
    updateHudMeasure('');
    setToolActive('measure', true);
    var map = getMap();
    if (map && map.getContainer) {
      map.getContainer().classList.add('atak-map--measuring');
    }
    toast('Mesure : cliquez deux points sur la carte.');
  }

  function setNvg(on) {
    nvgOn = !!on;
    setToolActive('nvg', nvgOn);
    var wrap = getWrap();
    if (wrap) wrap.classList.toggle('atak-map--nvg', nvgOn);
    try {
      localStorage.setItem('atak_map_nvg', nvgOn ? '1' : '0');
    } catch (e) {}
  }

  function goToGrid() {
    var opener = window.ATAKContextMenu && window.ATAKContextMenu.openPrompt
      ? window.ATAKContextMenu.openPrompt
      : null;
    function apply(raw) {
      if (raw == null) return;
      var g = parseGridInput(raw);
      if (!g) {
        toast('Format attendu : X Y (ex. 14500 16820).');
        return;
      }
      if (window.ATAKMap && window.ATAKMap.centerOn) {
        window.ATAKMap.centerOn(g.y, g.x);
        toast('Carte centrée sur ' + Math.round(g.x) + ' ' + Math.round(g.y));
      }
    }
    if (opener) {
      opener(
        'Aller à une grille',
        'Saisissez les coordonnées carte (est puis nord), séparées par un espace.',
        'Ex. 14500 16820',
        ''
      ).then(apply);
      return;
    }
    apply(window.prompt('Coordonnées grille (X Y)', ''));
  }

  function zoomBy(delta) {
    var map = getMap();
    if (!map) return;
    map.setZoom(map.getZoom() + delta);
    updateHudZoom();
  }

  function onToolClick(e) {
    var btn = e.target.closest('[data-tool]');
    if (!btn) return;
    e.preventDefault();
    var tool = btn.getAttribute('data-tool');
    if (tool === 'goto') goToGrid();
    else if (tool === 'me') {
      setFollow(false);
      centerOnSelf(true);
    } else if (tool === 'follow') setFollow(!followOn);
    else if (tool === 'measure') startMeasure();
    else if (tool === 'zoom-in') zoomBy(1);
    else if (tool === 'zoom-out') zoomBy(-1);
    else if (tool === 'nvg') setNvg(!nvgOn);
    else if (tool === 'clear-measure') {
      stopMeasure(false);
      toast('Mesure effacée.');
    }
  }

  function bindMap(map) {
    if (boundMap === map) return;
    if (boundMap) {
      boundMap.off('click', onMapClickMeasure);
      boundMap.off('dblclick', onMapDblClick);
      boundMap.off('zoomend', updateHudZoom);
      boundMap.off('moveend', updateHudZoom);
    }
    boundMap = map || null;
    if (!boundMap) return;
    try {
      if (boundMap.doubleClickZoom) boundMap.doubleClickZoom.disable();
    } catch (e) {}
    boundMap.on('click', onMapClickMeasure);
    boundMap.on('dblclick', onMapDblClick);
    boundMap.on('zoomend', updateHudZoom);
    boundMap.on('moveend', updateHudZoom);
    updateHudZoom();
    updateHudContacts();
  }

  function onKey(e) {
    if (!e || e.defaultPrevented) return;
    var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || (e.target && e.target.isContentEditable)) return;
    if (e.key === 'Escape') {
      if (measureOn) {
        stopMeasure(false);
        toast('Mesure annulée.');
      }
      if (followOn) setFollow(false);
      return;
    }
    if (e.altKey || e.ctrlKey || e.metaKey) return;
    var k = String(e.key || '').toLowerCase();
    if (k === 'g') { e.preventDefault(); goToGrid(); }
    else if (k === 'm') { e.preventDefault(); startMeasure(); }
    else if (k === 'f') { e.preventDefault(); setFollow(!followOn); }
    else if (k === 'h') { e.preventDefault(); centerOnSelf(true); }
    else if (k === 'n') { e.preventDefault(); setNvg(!nvgOn); }
  }

  function initToolbar() {
    var bar = document.getElementById('atak-map-tools');
    if (!bar || bar._atakBound) return;
    bar._atakBound = true;
    bar.addEventListener('click', onToolClick);
    document.addEventListener('keydown', onKey);
    try {
      if (localStorage.getItem('atak_map_nvg') === '1') setNvg(true);
    } catch (e) {}
  }

  function onMapReady(ev) {
    initToolbar();
    bindMap(ev && ev.detail && ev.detail.map ? ev.detail.map : getMap());
    updateHudContacts();
  }

  window.addEventListener('atak:mapready', onMapReady);
  window.addEventListener('atak:units-updated', updateHudContacts);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initToolbar);
  } else {
    initToolbar();
  }

  // Si la carte est déjà prête (script chargé tard).
  setTimeout(function () {
    var map = getMap();
    if (map) {
      initToolbar();
      bindMap(map);
      updateHudContacts();
    }
  }, 0);

  return {
    goToGrid: goToGrid,
    centerOnSelf: centerOnSelf,
    setFollow: setFollow,
    startMeasure: startMeasure,
    setNvg: setNvg,
    updateHudContacts: updateHudContacts
  };
})();
