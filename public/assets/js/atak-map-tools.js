/* COMSPEC ATAK — Outils carte (grille, suivi, mesure, zones, vision nocturne) */
window.ATAKMapTools = (function () {
  var followOn = false;
  var measureOn = false;
  var nvgOn = false;
  var placeMode = null; // 'note' | 'jackpot' | null
  var activeDrawTool = null; // 'search-zone' | 'perimeter' | 'aoi' | 'line' | null
  var measurePoints = [];
  var measureLayer = null;
  var measureLineUnder = null;
  var measureLine = null;
  var measureMarkers = [];
  var followTimer = null;
  var boundMap = null;
  var toastTimer = null;

  var DRAW_TOOL_PRESET = {
    'search-zone': 'search',
    perimeter: 'perimeter',
    aoi: 'aoi',
    line: null
  };

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
    return (meters / 1000).toFixed(meters < 10000 ? 2 : 1).replace('.', ',') + ' km';
  }

  /** Séparateur milliers FR (espace fine non-sécable → espace simple pour lisibilité TOC). */
  function formatIntFr(n) {
    var s = String(Math.round(Math.abs(n)));
    return s.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  }

  /** Superficie d’un cercle : π × r² (r en mètres). */
  function circleAreaM2(radiusM) {
    var r = Number(radiusM);
    if (!isFinite(r) || r < 0) return 0;
    return Math.PI * r * r;
  }

  /**
   * Libellé superficie français : « 785 000 m² » ou « 0,79 km² ».
   * Seuil : ≥ 100 000 m² → km² (2 décimales), sinon m².
   */
  function formatAreaFr(areaM2) {
    var a = Number(areaM2);
    if (!isFinite(a) || a < 0) return '—';
    if (a >= 100000) {
      var km2 = a / 1e6;
      var decimals = km2 < 10 ? 2 : (km2 < 100 ? 1 : 0);
      return km2.toFixed(decimals).replace('.', ',') + ' km²';
    }
    return formatIntFr(a) + ' m²';
  }

  /**
   * Délai centre → bord : distance(rayon) / vitesse.
   * Vitesse en km/h (presets À pied 5 / Véhicule 40), convertie en m/s.
   */
  function delaySeconds(radiusM, speedKph) {
    var r = Number(radiusM);
    var kph = Math.max(Number(speedKph) || 0, 0.1);
    if (!isFinite(r) || r < 0) return 0;
    var speedMS = kph / 3.6;
    return r / speedMS;
  }

  /** Ex. « 12 min », « 1 h 05 min », « 45 s ». */
  function formatDelayFr(seconds) {
    var s = Math.max(0, Math.round(Number(seconds) || 0));
    if (s < 60) return s + ' s';
    var h = Math.floor(s / 3600);
    var m = Math.floor((s % 3600) / 60);
    if (h >= 1) {
      if (m === 0) return h + ' h';
      return h + ' h ' + String(m).padStart(2, '0') + ' min';
    }
    return m + ' min';
  }

  function getToolRadiusM() {
    var el = document.getElementById('atak-tool-radius');
    var v = el ? parseFloat(el.value) : NaN;
    if (!isFinite(v) || v < 10) return 500;
    return Math.min(50000, v);
  }

  function setToolRadiusM(meters, fromDraw) {
    var el = document.getElementById('atak-tool-radius');
    if (!el) return;
    var v = Math.max(10, Math.min(50000, Math.round(Number(meters) || 0)));
    if (fromDraw) {
      // Évite de saturater le champ pendant le tracé (pas de focus/step parasites).
      if (document.activeElement === el) return;
    }
    el.value = String(v);
  }

  function getToolSpeedKph() {
    var el = document.getElementById('atak-tool-speed');
    var v = el ? parseFloat(el.value) : NaN;
    if (!isFinite(v) || v < 0.1) return 5;
    return Math.min(200, v);
  }

  function setToolSpeedKph(kph) {
    var el = document.getElementById('atak-tool-speed');
    if (!el) return;
    var v = Math.max(1, Math.min(200, Number(kph) || 5));
    el.value = String(v);
    refreshZoneMetrics();
  }

  function circleMetrics(radiusM, speedKph) {
    var r = Number(radiusM);
    if (!isFinite(r) || r < 0) r = 0;
    var speed = speedKph != null ? Number(speedKph) : getToolSpeedKph();
    var area = circleAreaM2(r);
    var delay = delaySeconds(r, speed);
    return {
      radiusM: r,
      speedKph: speed,
      areaM2: area,
      delayS: delay,
      areaLabel: formatAreaFr(area),
      delayLabel: formatDelayFr(delay),
      summary: 'Superficie : ' + formatAreaFr(area) + ' · Délai jusqu’au bord : ' + formatDelayFr(delay)
    };
  }

  function refreshZoneMetrics(radiusOverride) {
    var r = radiusOverride != null ? Number(radiusOverride) : getToolRadiusM();
    if (!isFinite(r) || r <= 0) r = getToolRadiusM();
    var m = circleMetrics(r, getToolSpeedKph());
    var speedLabel = String(m.speedKph).replace('.', ',') + ' km/h';
    var areaEl = document.getElementById('atak-zone-area');
    var delayEl = document.getElementById('atak-zone-delay');
    var speedReadout = document.getElementById('atak-zone-speed-readout');
    var el = document.getElementById('atak-zone-metrics');
    if (areaEl) areaEl.textContent = m.areaLabel;
    if (delayEl) delayEl.textContent = m.delayLabel;
    if (speedReadout) speedReadout.textContent = speedLabel;
    if (el) {
      el.textContent = m.summary + ' · ' + speedLabel;
      el.title = el.textContent;
    }
  }

  function onZoneRadiusPreview(ev) {
    var detail = ev && ev.detail ? ev.detail : {};
    var r = detail.radius != null ? Number(detail.radius) : NaN;
    if (!isFinite(r) || r <= 0) return;
    setToolRadiusM(r, true);
    refreshZoneMetrics(r);
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

  function unitSteamId(u) {
    if (!u) return '';
    var top = String(u.steam_id || u.steamId || '').trim();
    if (top) return top;
    var ex = u.extra;
    if (typeof ex === 'string') {
      try { ex = JSON.parse(ex || '{}'); } catch (e) { ex = {}; }
    }
    if (ex && typeof ex === 'object') {
      return String(ex.steam_uid || ex.steam_id || ex.steamId || '').trim();
    }
    return '';
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
        var sid = unitSteamId(u2);
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
    if (measureLineUnder && map) {
      try { map.removeLayer(measureLineUnder); } catch (e) {}
    }
    measureLineUnder = null;
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
      measureLineUnder = L.polyline(measurePoints, {
        color: '#111827',
        weight: 6,
        opacity: 0.85,
        interactive: false
      }).addTo(layer);
      measureLine = L.polyline(measurePoints, {
        color: '#fbbf24',
        weight: 3,
        dashArray: '8 5',
        opacity: 1,
        interactive: false
      }).addTo(layer);
      var dist = distanceMeters(measurePoints[0], measurePoints[1]);
      var brg = bearingBetween(measurePoints[0], measurePoints[1]);
      updateHudMeasure(formatDistance(dist) + ' · ' + formatBearing(brg));
      toast('Mesure : ' + formatDistance(dist) + ' — cap ' + formatBearing(brg));
    }
  }

  function onMapClickMeasure(e) {
    if (placeMode) {
      onMapClickPlace(e);
      return;
    }
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

  function clearPlaceMode() {
    if (!placeMode) return;
    var prev = placeMode;
    placeMode = null;
    setToolActive('note', false);
    setToolActive('jackpot', false);
    var map = getMap();
    if (map && map.getContainer) {
      map.getContainer().classList.remove('atak-map--place-note', 'atak-map--place-jackpot');
    }
    return prev;
  }

  function setDrawToolActive(tool) {
    ['search-zone', 'perimeter', 'aoi', 'line'].forEach(function (t) {
      setToolActive(t, t === tool);
    });
    activeDrawTool = tool || null;
  }

  function cancelMapDraw() {
    if (window.ATAKContextMenu && window.ATAKContextMenu.cancelDraw) {
      window.ATAKContextMenu.cancelDraw();
    }
    setDrawToolActive(null);
  }

  function startDrawTool(tool) {
    if (activeDrawTool === tool) {
      cancelMapDraw();
      toast('Dessin annulé.');
      return;
    }
    stopMeasure(false);
    setFollow(false);
    clearPlaceMode();
    var ctx = window.ATAKContextMenu;
    if (!ctx) {
      toast('Outils de dessin indisponibles.');
      return;
    }
    if (tool === 'line') {
      if (typeof ctx.startDraw === 'function') ctx.startDraw('line', null);
      else {
        toast('Outil trait indisponible.');
        return;
      }
    } else {
      var preset = DRAW_TOOL_PRESET[tool];
      if (!preset || typeof ctx.startZoneTool !== 'function') {
        toast('Outil de zone indisponible.');
        return;
      }
      ctx.startZoneTool(preset, null);
    }
    setDrawToolActive(tool);
    if (tool === 'search-zone' || tool === 'perimeter' || tool === 'aoi') {
      revealZoneModule();
    }
    if (tool === 'search-zone') toast('Zone de recherche : cliquez le centre, puis le bord.');
    else if (tool === 'perimeter') toast('Périmètre : cliquez les sommets, puis Terminer.');
    else if (tool === 'aoi') toast('Zone d’intérêt : cliquez deux coins opposés.');
    else if (tool === 'line') toast('Trait : cliquez les points, puis Terminer.');
  }

  function revealZoneModule() {
    var panel = document.getElementById('atak-panel-left');
    if (panel && panel.classList.contains('collapsed') && window.ATAKSectionNav && window.ATAKSectionNav.setLeftCollapsed) {
      window.ATAKSectionNav.setLeftCollapsed(false);
    }
    var btn = document.querySelector('#atak-panel-left .atak-tab[data-tab="zones"]');
    if (btn) {
      if (!btn.classList.contains('active')) btn.click();
      else if (window.ATAKSectionNav && window.ATAKSectionNav.setSection) {
        window.ATAKSectionNav.setSection('sitac', { skipActivate: true });
      }
    }
  }

  function clearDrawings() {
    cancelMapDraw();
    clearPlaceMode();
    var shapesApi = window.ATAKMapShapes;
    if (!shapesApi || !shapesApi.clearAllDrawings) {
      toast('Aucun tracé à effacer.');
      return;
    }
    var list = (shapesApi.getShapes && shapesApi.getShapes()) || [];
    var hasAny = list.some(function (s) {
      var kind = (s.meta && s.meta.kind) || '';
      var type = String(s.type || '').toUpperCase();
      return kind === 'zone' || kind === 'search_zone' || kind === 'perimeter' || kind === 'aoi' || kind === 'line'
        || type === 'CIRCLE' || type === 'POLYGON' || type === 'LINE' || type === 'POLYLINE';
    });
    if (!hasAny) {
      toast('Aucun tracé à effacer.');
      return;
    }
    var confirmFn = window.ATAKContextMenu && window.ATAKContextMenu.confirmAction
      ? window.ATAKContextMenu.confirmAction
      : null;
    var ask = confirmFn
      ? confirmFn('Effacer tous les tracés et zones de la carte ?')
      : Promise.resolve(window.confirm('Effacer tous les tracés et zones de la carte ?'));
    ask.then(function (ok) {
      if (!ok) return;
      shapesApi.clearAllDrawings();
    });
  }

  function startPlaceMode(mode) {
    if (placeMode === mode) {
      clearPlaceMode();
      toast(mode === 'jackpot' ? 'Placement JACKPOT annulé.' : 'Placement de note annulé.');
      return;
    }
    stopMeasure(false);
    setFollow(false);
    cancelMapDraw();
    clearPlaceMode();
    placeMode = mode;
    setToolActive(mode, true);
    var map = getMap();
    if (map && map.getContainer) {
      map.getContainer().classList.add(mode === 'jackpot' ? 'atak-map--place-jackpot' : 'atak-map--place-note');
    }
    toast(mode === 'jackpot'
      ? 'JACKPOT : cliquez sur la carte pour marquer la HVT.'
      : 'Note : cliquez sur la carte pour enregistrer une note.');
  }

  function onMapClickPlace(e) {
    if (!placeMode || !e || !e.latlng) return;
    var mode = placeMode;
    clearPlaceMode();
    var ctx = window.ATAKContextMenu;
    if (mode === 'jackpot') {
      if (ctx && typeof ctx.promptJackpotAt === 'function') {
        ctx.promptJackpotAt(e.latlng);
      } else if (ctx && typeof ctx.placeJackpotAt === 'function') {
        ctx.placeJackpotAt(e.latlng);
      }
      return;
    }
    if (mode === 'note') {
      if (ctx && typeof ctx.promptMapNoteAt === 'function') {
        ctx.promptMapNoteAt(e.latlng);
      }
    }
  }

  function startMeasure() {
    if (measureOn) {
      stopMeasure(false);
      toast('Mesure annulée.');
      return;
    }
    clearPlaceMode();
    cancelMapDraw();
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
    else if (tool === 'follow') setFollow(!followOn);
    else if (tool === 'measure') startMeasure();
    else if (tool === 'note') startPlaceMode('note');
    else if (tool === 'search-zone' || tool === 'perimeter' || tool === 'aoi' || tool === 'line') startDrawTool(tool);
    else if (tool === 'clear-drawings') clearDrawings();
    else if (tool === 'speed-foot') {
      setToolSpeedKph(5);
      toast('Vitesse à pied : 5 km/h');
    } else if (tool === 'speed-vehicle') {
      setToolSpeedKph(40);
      toast('Vitesse véhicule : 40 km/h');
    } else if (tool === 'zoom-in') zoomBy(1);
    else if (tool === 'zoom-out') zoomBy(-1);
    else if (tool === 'nvg') setNvg(!nvgOn);
    else if (tool === 'cop') {
      if (window.ATAKCopBoard && window.ATAKCopBoard.toggle) window.ATAKCopBoard.toggle();
    }
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
      if (placeMode) {
        clearPlaceMode();
        toast('Placement annulé.');
      }
      if (activeDrawTool) setDrawToolActive(null);
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

  function onDrawEnded() {
    setDrawToolActive(null);
  }

  function bindZoneMetricInputs() {
    var radiusEl = document.getElementById('atak-tool-radius');
    var speedEl = document.getElementById('atak-tool-speed');
    function onChange() { refreshZoneMetrics(); }
    if (radiusEl && !radiusEl._atakBound) {
      radiusEl._atakBound = true;
      radiusEl.addEventListener('input', onChange);
      radiusEl.addEventListener('change', onChange);
    }
    if (speedEl && !speedEl._atakBound) {
      speedEl._atakBound = true;
      speedEl.addEventListener('input', onChange);
      speedEl.addEventListener('change', onChange);
    }
    refreshZoneMetrics();
  }

  var LS_COLLAPSED = 'atak_map_tools_collapsed';
  var LS_VISIBLE = 'atak_map_tools_visible_v1';
  var LS_PRESET = 'atak_map_tools_preset_v1';

  var TOOL_PREF_DEFS = [
    { id: 'goto', label: 'Grille' },
    { id: 'follow', label: 'Suivre' },
    { id: 'measure', label: 'Mesurer' },
    { id: 'note', label: 'Note' },
    { id: 'search-zone', label: 'Recherche' },
    { id: 'perimeter', label: 'Périmètre' },
    { id: 'aoi', label: 'Zone d’intérêt' },
    { id: 'line', label: 'Trait' },
    { id: 'clear-drawings', label: 'Effacer' },
    { id: 'zoom', label: 'Zoom' },
    { id: 'nvg', label: 'Vision nocturne' },
    { id: 'cop', label: 'Tableau des unités' }
  ];

  var SEP_GROUPS = {
    nav: ['goto', 'follow'],
    mark: ['measure', 'note'],
    draw: ['search-zone', 'perimeter', 'aoi', 'line', 'clear-drawings'],
    view: ['zoom', 'nvg', 'cop']
  };

  /** Profils métier : TOC (tout), chef d’équipe (manœuvre), médecin (repères + mesure). */
  var ROLE_PRESETS = {
    toc: {
      label: 'TOC',
      ids: null // tous
    },
    sl: {
      label: 'Chef d’équipe',
      ids: ['goto', 'follow', 'measure', 'note', 'search-zone', 'perimeter', 'aoi', 'line', 'clear-drawings', 'zoom']
    },
    medic: {
      label: 'Médecin',
      ids: ['goto', 'follow', 'measure', 'note', 'aoi', 'line', 'clear-drawings', 'zoom']
    }
  };

  function defaultVisibleMap() {
    var out = {};
    TOOL_PREF_DEFS.forEach(function (d) { out[d.id] = true; });
    return out;
  }

  function visibleMapFromPreset(presetId) {
    var preset = ROLE_PRESETS[presetId];
    if (!preset) return defaultVisibleMap();
    if (!preset.ids) return defaultVisibleMap();
    var out = {};
    TOOL_PREF_DEFS.forEach(function (d) {
      out[d.id] = preset.ids.indexOf(d.id) !== -1;
    });
    return out;
  }

  function loadPresetId() {
    try {
      var v = localStorage.getItem(LS_PRESET) || '';
      return ROLE_PRESETS[v] ? v : '';
    } catch (e) {
      return '';
    }
  }

  function savePresetId(id) {
    try {
      if (id && ROLE_PRESETS[id]) localStorage.setItem(LS_PRESET, id);
      else localStorage.removeItem(LS_PRESET);
    } catch (e) {}
  }

  function syncPresetButtons(activeId) {
    var root = document.getElementById('atak-map-tools-prefs-presets');
    if (!root) return;
    root.querySelectorAll('[data-preset]').forEach(function (btn) {
      var id = btn.getAttribute('data-preset');
      var on = !!activeId && id === activeId;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  function applyPreset(presetId, opts) {
    opts = opts || {};
    if (!ROLE_PRESETS[presetId]) return;
    var map = visibleMapFromPreset(presetId);
    saveVisibleMap(map);
    savePresetId(presetId);
    applyVisibleSlots(map);
    syncPresetButtons(presetId);
    var grid = document.getElementById('atak-map-tools-prefs-grid');
    if (grid) {
      grid.querySelectorAll('input[data-pref-slot]').forEach(function (cb) {
        var slot = cb.getAttribute('data-pref-slot');
        cb.checked = map[slot] !== false;
      });
    }
    if (!opts.silent) {
      var label = ROLE_PRESETS[presetId].label || presetId;
      toast('Profil « ' + label + ' » appliqué');
    }
  }

  function loadVisibleMap() {
    var base = defaultVisibleMap();
    try {
      var raw = localStorage.getItem(LS_VISIBLE);
      if (!raw) return base;
      var parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object') return base;
      TOOL_PREF_DEFS.forEach(function (d) {
        if (Object.prototype.hasOwnProperty.call(parsed, d.id)) {
          base[d.id] = !!parsed[d.id];
        }
      });
    } catch (e) {}
    return base;
  }

  function saveVisibleMap(map) {
    try {
      localStorage.setItem(LS_VISIBLE, JSON.stringify(map || defaultVisibleMap()));
    } catch (e) {}
  }

  function applyVisibleSlots(visible) {
    var bar = document.getElementById('atak-map-tools');
    if (!bar) return;
    var map = visible || loadVisibleMap();
    bar.querySelectorAll('[data-tool-slot]').forEach(function (el) {
      var slot = el.getAttribute('data-tool-slot');
      var show = map[slot] !== false;
      el.hidden = !show;
    });
    Object.keys(SEP_GROUPS).forEach(function (sepId) {
      var sep = bar.querySelector('[data-tool-sep="' + sepId + '"]');
      if (!sep) return;
      var any = SEP_GROUPS[sepId].some(function (id) { return map[id] !== false; });
      sep.hidden = !any;
    });
    var chromeSep = bar.querySelector('[data-tool-sep="chrome"]');
    if (chromeSep) chromeSep.hidden = false;
  }

  function buildPrefsPanel() {
    var grid = document.getElementById('atak-map-tools-prefs-grid');
    if (!grid || grid._atakBuilt) return;
    grid._atakBuilt = true;
    var visible = loadVisibleMap();
    grid.innerHTML = '';
    TOOL_PREF_DEFS.forEach(function (def) {
      var lab = document.createElement('label');
      lab.className = 'atak-map-tools__prefs-item';
      var cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.checked = visible[def.id] !== false;
      cb.setAttribute('data-pref-slot', def.id);
      cb.addEventListener('change', function () {
        var next = loadVisibleMap();
        next[def.id] = !!cb.checked;
        saveVisibleMap(next);
        savePresetId('');
        syncPresetButtons('');
        applyVisibleSlots(next);
      });
      lab.appendChild(cb);
      lab.appendChild(document.createTextNode(def.label));
      grid.appendChild(lab);
    });
    syncPresetButtons(loadPresetId());
  }

  function setLookOpen(open) {
    var panel = document.getElementById('atak-map-look-prefs');
    var btn = document.querySelector('#atak-map-tools [data-tool-ui="look"]');
    if (panel) panel.hidden = !open;
    if (btn) {
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.classList.toggle('is-active', !!open);
    }
    if (open && window.ATAKMap && typeof window.ATAKMap.syncDisplayPrefsUi === 'function') {
      try { window.ATAKMap.syncDisplayPrefsUi(); } catch (e) {}
    }
  }

  function setPrefsOpen(open) {
    var panel = document.getElementById('atak-map-tools-prefs');
    var btn = document.querySelector('#atak-map-tools [data-tool-ui="customize"]');
    if (panel) panel.hidden = !open;
    if (btn) {
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.classList.toggle('is-active', !!open);
    }
    if (open) {
      setLookOpen(false);
      buildPrefsPanel();
    }
  }

  function setToolbarCollapsed(collapsed) {
    var bar = document.getElementById('atak-map-tools');
    var fab = document.getElementById('atak-map-tools-fab');
    if (bar) {
      bar.classList.toggle('is-collapsed', !!collapsed);
      bar.setAttribute('aria-hidden', collapsed ? 'true' : 'false');
    }
    if (fab) fab.hidden = !collapsed;
    if (collapsed) {
      setPrefsOpen(false);
      setLookOpen(false);
    }
    try {
      localStorage.setItem(LS_COLLAPSED, collapsed ? '1' : '0');
    } catch (e) {}
  }

  function isToolbarCollapsed() {
    try {
      return localStorage.getItem(LS_COLLAPSED) === '1';
    } catch (e) {
      return false;
    }
  }

  function onChromeClick(e) {
    var ui = e.target.closest('[data-tool-ui]');
    if (!ui) return;
    e.preventDefault();
    e.stopPropagation();
    var action = ui.getAttribute('data-tool-ui');
    if (action === 'collapse') setToolbarCollapsed(true);
    else if (action === 'customize') {
      var panel = document.getElementById('atak-map-tools-prefs');
      setPrefsOpen(!(panel && !panel.hidden));
    } else if (action === 'look') {
      var look = document.getElementById('atak-map-look-prefs');
      var nextOpen = !(look && !look.hidden);
      if (nextOpen) setPrefsOpen(false);
      setLookOpen(nextOpen);
    } else if (action === 'look-close') setLookOpen(false);
    else if (action === 'prefs-close') setPrefsOpen(false);
    else if (action === 'preset') {
      var presetId = ui.getAttribute('data-preset');
      if (presetId) applyPreset(presetId);
    } else if (action === 'prefs-all') {
      var all = defaultVisibleMap();
      saveVisibleMap(all);
      savePresetId('toc');
      applyVisibleSlots(all);
      syncPresetButtons('toc');
      var grid = document.getElementById('atak-map-tools-prefs-grid');
      if (grid) {
        grid.querySelectorAll('input[data-pref-slot]').forEach(function (cb) {
          cb.checked = true;
        });
      }
    }
  }

  function initToolbar() {
    var zoneTab = document.getElementById('tab-zones');
    if (zoneTab && !zoneTab._atakToolsBound) {
      zoneTab._atakToolsBound = true;
      zoneTab.addEventListener('click', onToolClick);
    }
    var bar = document.getElementById('atak-map-tools');
    if (!bar || bar._atakBound) return;
    bar._atakBound = true;
    bar.addEventListener('click', onToolClick);
    bar.addEventListener('click', onChromeClick);
    var fab = document.getElementById('atak-map-tools-fab');
    if (fab && !fab._atakBound) {
      fab._atakBound = true;
      fab.addEventListener('click', function () {
        setToolbarCollapsed(false);
      });
    }
    document.addEventListener('keydown', onKey);
    window.addEventListener('atak:draw-ended', onDrawEnded);
    window.addEventListener('atak:zone-radius-preview', onZoneRadiusPreview);
    bindZoneMetricInputs();
    applyVisibleSlots(loadVisibleMap());
    setToolbarCollapsed(isToolbarCollapsed());
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
    findSelfUnit: findSelfUnit,
    setFollow: setFollow,
    startMeasure: startMeasure,
    startDrawTool: startDrawTool,
    clearDrawings: clearDrawings,
    setNvg: setNvg,
    updateHudContacts: updateHudContacts,
    getToolRadiusM: getToolRadiusM,
    setToolRadiusM: setToolRadiusM,
    getToolSpeedKph: getToolSpeedKph,
    setToolSpeedKph: setToolSpeedKph,
    circleAreaM2: circleAreaM2,
    formatAreaFr: formatAreaFr,
    delaySeconds: delaySeconds,
    formatDelayFr: formatDelayFr,
    circleMetrics: circleMetrics,
    refreshZoneMetrics: refreshZoneMetrics,
    setToolbarCollapsed: setToolbarCollapsed
  };
})();
