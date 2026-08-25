/* ATAK — terrain texturé : les tuiles PNG sont drapées sur le modèle altimétrique. */
window.ATAKTerrain3D = (function () {
  'use strict';

  var KEY = 'atak_terrain_3d_view';
  var state = { enabled: false, pitch: 48, bearing: 0, verticalExaggeration: 2.5 };
  var stage;
  var button;
  var nav;
  var settings;
  var pitchInput;
  var exaggerationInput;
  var dragging = false;
  var dragged = false;
  var startX = 0;
  var startBearing = 0;
  var terrainCanvas;
  var terrainGl;
  var terrainGrid;
  var terrainGridMapId;
  var terrainProgram;
  var terrainFrame;
  var terrainListeners = [];

  function clamp(value, min, max) { return Math.max(min, Math.min(max, Number(value) || min)); }
  function normalizeBearing(value) { return ((Number(value) || 0) % 360 + 360) % 360; }

  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase
      ? window.ATAKSocket.getApiBase()
      : (window.ATAK_API_BASE || window.ATAK_BASE_URL || '');
  }

  function mapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId
      ? window.ATAKSocket.getMapId()
      : 1;
  }

  function restore() {
    try {
      var saved = JSON.parse(localStorage.getItem(KEY) || '{}');
      state.enabled = !!saved.enabled;
      state.pitch = clamp(saved.pitch == null ? 48 : saved.pitch, 25, 65);
      state.bearing = normalizeBearing(saved.bearing);
      state.verticalExaggeration = clamp(saved.verticalExaggeration == null ? 2.5 : saved.verticalExaggeration, 1, 4);
    } catch (e) {}
  }

  function save() {
    try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {}
  }

  function decodeGrid(data) {
    if (!data || !data.ready || !data.heights || data.encoding !== 'int16le_b64') return null;
    var raw = atob(data.heights);
    var heights = new Int16Array(Math.floor(raw.length / 2));
    for (var i = 0; i < heights.length; i += 1) {
      var value = raw.charCodeAt(i * 2) | (raw.charCodeAt(i * 2 + 1) << 8);
      heights[i] = value > 32767 ? value - 65536 : value;
    }
    data.values = heights;
    return data;
  }

  function heightAt(world) {
    var g = terrainGrid;
    if (!g || !world) return 0;
    var col = Math.round((world.x - Number(g.origin_x)) / Number(g.cell_m));
    var row = Math.round((world.y - Number(g.origin_y)) / Number(g.cell_m));
    if (col < 0 || row < 0 || col >= g.cols || row >= g.rows) return 0;
    var z = g.values[row * g.cols + col];
    return z === -32768 ? 0 : z;
  }

  function reliefShade(world) {
    var g = terrainGrid;
    if (!g || !world) return 1;
    var cell = Math.max(1, Number(g.cell_m) || 1);
    var left = heightAt({ x: world.x - cell, y: world.y });
    var right = heightAt({ x: world.x + cell, y: world.y });
    var down = heightAt({ x: world.x, y: world.y - cell });
    var up = heightAt({ x: world.x, y: world.y + cell });
    var dx = (right - left) / (cell * 2) * state.verticalExaggeration;
    var dy = (up - down) / (cell * 2) * state.verticalExaggeration;
    /* Lumière rasante nord-ouest : les versants opposés restent lisibles sur le fond IGN. */
    var length = Math.sqrt(dx * dx + dy * dy + 1);
    var light = (-dx * -.58 + -dy * .58 + .58) / length;
    return Math.max(.48, Math.min(1.28, .82 + light * .46));
  }

  function shader(gl, type, source) {
    var item = gl.createShader(type);
    gl.shaderSource(item, source); gl.compileShader(item);
    return item;
  }

  function initRenderer() {
    if (terrainGl) return true;
    var mapEl = document.getElementById('atak-map');
    if (!mapEl) return false;
    terrainCanvas = document.createElement('canvas');
    terrainCanvas.className = 'atak-terrain-mesh';
    terrainCanvas.setAttribute('aria-hidden', 'true');
    mapEl.appendChild(terrainCanvas);
    terrainGl = terrainCanvas.getContext('webgl', { alpha: true, antialias: true });
    if (!terrainGl) { terrainCanvas.remove(); terrainCanvas = null; return false; }
    var vs = shader(terrainGl, terrainGl.VERTEX_SHADER,
      'attribute vec2 p;attribute vec2 uv;attribute float shade;varying vec2 v;varying float s;uniform vec2 size;'+
      'void main(){vec2 q=p/size*2.0-1.0;gl_Position=vec4(q.x,-q.y,0.,1.);v=uv;s=shade;}');
    var fs = shader(terrainGl, terrainGl.FRAGMENT_SHADER,
      'precision mediump float;varying vec2 v;varying float s;uniform sampler2D image;'+
      'void main(){vec4 c=texture2D(image,v);gl_FragColor=vec4(c.rgb*s,c.a);}');
    terrainProgram = terrainGl.createProgram();
    terrainGl.attachShader(terrainProgram, vs); terrainGl.attachShader(terrainProgram, fs);
    terrainGl.linkProgram(terrainProgram);
    return true;
  }

  function bindMapEvent(map, name) {
    var fn = scheduleTerrain;
    map.on(name, fn); terrainListeners.push([map, name, fn]);
  }

  function tileImageReady(tile) {
    var image = tile && tile.el;
    if (!image) return false;
    /* Leaflet 1.9 ne publie pas de drapeau `loaded` dans ses entrées _tiles.
       L'état de l'élément image est la source fiable, y compris pour une
       tuile déjà présente dans le cache du navigateur. */
    return image.complete && Number(image.naturalWidth || image.width) > 0;
  }

  function startTerrain() {
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    if (!map || !initRenderer()) return;
    if (!terrainListeners.length) ['move', 'moveend', 'zoomend', 'resize'].forEach(function (name) { bindMapEvent(map, name); });
    var layer = window.ATAKMap.getBaseTileLayer && window.ATAKMap.getBaseTileLayer();
    if (layer && !layer._atakTerrain3DBound) {
      layer._atakTerrain3DBound = true;
      bindMapEvent(layer, 'tileload');
      bindMapEvent(layer, 'tileunload');
    }
    var activeMapId = mapId();
    if (!terrainGrid || terrainGridMapId !== activeMapId) {
      terrainGrid = null;
      terrainGridMapId = activeMapId;
      fetch(apiBase() + '/api/atak/terrain?mapId=' + encodeURIComponent(activeMapId) + '&include=heights', { credentials: 'same-origin' })
        .then(function (response) { return response.ok ? response.json() : null; })
        .then(function (data) {
          if (terrainGridMapId !== activeMapId) return;
          terrainGrid = decodeGrid(data);
          scheduleTerrain();
        })
        .catch(function () {});
    }
    scheduleTerrain();
  }

  function scheduleTerrain() {
    if (!state.enabled || terrainFrame) return;
    terrainFrame = requestAnimationFrame(function () { terrainFrame = 0; drawTerrain(); });
  }

  function syncHillshade() {
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    var pane = map && map.getPane ? map.getPane('atakHillshadePane') : null;
    if (!pane) return;
    /* Le maillage applique déjà l'éclairage du DEM à la texture des tuiles.
       Le PNG reste disponible comme repli, mais ne doit pas griser une seconde
       fois la carte lorsque WebGL a effectivement produit une image. */
    pane.style.opacity = state.enabled && stage.classList.contains('atak-terrain-mesh-ready') ? '0' : '';
  }

  function drawTerrain() {
    if (!terrainGl || !terrainGrid) return;
    var map = window.ATAKMap.getMap();
    var layer = window.ATAKMap.getBaseTileLayer && window.ATAKMap.getBaseTileLayer();
    if (!map || !layer || !layer._tiles) return;
    var size = map.getSize(), ratio = Math.min(2, window.devicePixelRatio || 1);
    terrainCanvas.width = Math.round(size.x * ratio); terrainCanvas.height = Math.round(size.y * ratio);
    terrainCanvas.style.width = size.x + 'px'; terrainCanvas.style.height = size.y + 'px';
    var gl = terrainGl; gl.viewport(0, 0, terrainCanvas.width, terrainCanvas.height);
    gl.clearColor(0, 0, 0, 0); gl.clear(gl.COLOR_BUFFER_BIT); gl.useProgram(terrainProgram);
    gl.uniform2f(gl.getUniformLocation(terrainProgram, 'size'), size.x, size.y);
    var pLoc = gl.getAttribLocation(terrainProgram, 'p'), uvLoc = gl.getAttribLocation(terrainProgram, 'uv');
    var shadeLoc = gl.getAttribLocation(terrainProgram, 'shade');
    var renderedTiles = 0;
    Object.keys(layer._tiles).forEach(function (key) {
      var tile = layer._tiles[key];
      if (!tileImageReady(tile)) return;
      var image = tile.el, geoBounds = layer._tileCoordsToBounds(tile.coords);
      var nw = map.latLngToContainerPoint(geoBounds.getNorthWest());
      var se = map.latLngToContainerPoint(geoBounds.getSouthEast());
      var left = nw.x, top = nw.y, w = se.x - nw.x, h = se.y - nw.y;
      var steps = 16, vertices = [], uvs = [], shades = [], indices = [];
      for (var y = 0; y <= steps; y += 1) for (var x = 0; x <= steps; x += 1) {
        var sx = left + w * x / steps, sy = top + h * y / steps;
        var ll = map.containerPointToLatLng([sx, sy]);
        var world = window.ATAKMap.worldFromLatLng(ll);
        var z = heightAt(world);
        var displacement = (z - Number(terrainGrid.min_z || 0)) * .32 * state.verticalExaggeration;
        var relief = Math.max(-300, Math.min(550, displacement));
        vertices.push(sx, sy - relief); uvs.push(x / steps, y / steps); shades.push(reliefShade(world));
      }
      for (var gy = 0; gy < steps; gy += 1) for (var gx = 0; gx < steps; gx += 1) {
        var a = gy * (steps + 1) + gx, b = a + 1, c = a + steps + 1, d = c + 1;
        indices.push(a, c, b, b, c, d);
      }
      try {
        var texture = gl.createTexture(); gl.bindTexture(gl.TEXTURE_2D, texture);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR); gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, image);
        var pb = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, pb); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(vertices), gl.STREAM_DRAW); gl.enableVertexAttribArray(pLoc); gl.vertexAttribPointer(pLoc, 2, gl.FLOAT, false, 0, 0);
        var ub = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, ub); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(uvs), gl.STREAM_DRAW); gl.enableVertexAttribArray(uvLoc); gl.vertexAttribPointer(uvLoc, 2, gl.FLOAT, false, 0, 0);
        var sb = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, sb); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(shades), gl.STREAM_DRAW); gl.enableVertexAttribArray(shadeLoc); gl.vertexAttribPointer(shadeLoc, 1, gl.FLOAT, false, 0, 0);
        var ib = gl.createBuffer(); gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, ib); gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, new Uint16Array(indices), gl.STREAM_DRAW); gl.drawElements(gl.TRIANGLES, indices.length, gl.UNSIGNED_SHORT, 0);
        renderedTiles += 1;
        gl.deleteBuffer(pb); gl.deleteBuffer(ub); gl.deleteBuffer(sb); gl.deleteBuffer(ib); gl.deleteTexture(texture);
      } catch (error) { /* Une tuile distante sans CORS reste rendue par Leaflet. */ }
    });
    stage.classList.toggle('atak-terrain-mesh-ready', renderedTiles > 0);
    syncHillshade();
  }

  function render() {
    if (!stage) return;
    stage.classList.toggle('atak-map-stage--3d', state.enabled);
    if (!state.enabled) stage.classList.remove('atak-terrain-mesh-ready');
    syncHillshade();
    stage.style.setProperty('--atak-map-pitch', state.pitch + 'deg');
    stage.style.setProperty('--atak-map-bearing', state.bearing + 'deg');
    stage.style.setProperty('--atak-map-bearing-number', String(state.bearing));
    if (button) {
      button.classList.toggle('is-active', state.enabled);
      button.setAttribute('aria-pressed', state.enabled ? 'true' : 'false');
      button.textContent = state.enabled ? '3D actif' : '3D';
    }
    if (nav) nav.hidden = !state.enabled;
    if (settings) settings.hidden = !state.enabled;
    if (pitchInput) pitchInput.value = String(state.pitch);
    if (exaggerationInput) exaggerationInput.value = String(state.verticalExaggeration);
    var pitchValue = document.getElementById('atak-terrain-pitch-val');
    if (pitchValue) pitchValue.textContent = state.pitch + '°';
    var exaggerationValue = document.getElementById('atak-terrain-exaggeration-val');
    if (exaggerationValue) exaggerationValue.textContent = state.verticalExaggeration.toFixed(1) + '×';
    window.dispatchEvent(new CustomEvent('atak:terrain3dchange', { detail: { enabled: state.enabled } }));
  }

  function setEnabled(enabled) {
    state.enabled = !!enabled;
    if (state.enabled && window.ATAKMap && window.ATAKMap.patchDisplayPrefs) {
      window.ATAKMap.patchDisplayPrefs({ terrainHillshade: true });
      startTerrain();
    }
    render();
    if (state.enabled) startTerrain();
    save();
    window.setTimeout(function () {
      var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
      if (map && map.invalidateSize) map.invalidateSize(false);
    }, 450);
  }

  function bindCompass(compass) {
    if (!compass) return;
    compass.addEventListener('pointerdown', function (event) {
      dragging = true;
      dragged = false;
      startX = event.clientX;
      startBearing = state.bearing;
      compass.setPointerCapture(event.pointerId);
    });
    compass.addEventListener('pointermove', function (event) {
      if (!dragging) return;
      var delta = event.clientX - startX;
      if (Math.abs(delta) > 2) dragged = true;
      state.bearing = Math.round(normalizeBearing(startBearing + delta * 1.5));
      render();
    });
    compass.addEventListener('pointerup', function () { dragging = false; save(); });
    compass.addEventListener('click', function () {
      if (dragged) return;
      state.bearing = 0;
      render();
      save();
    });
  }

  function init() {
    stage = document.querySelector('.atak-map-stage');
    button = document.getElementById('atak-view-3d');
    nav = document.getElementById('atak-map-3d-nav');
    settings = document.getElementById('atak-terrain-3d-settings');
    pitchInput = document.getElementById('atak-terrain-pitch');
    exaggerationInput = document.getElementById('atak-terrain-exaggeration');
    if (!stage || !button) return;
    restore();
    button.addEventListener('click', function () { setEnabled(!state.enabled); });
    var flat = document.getElementById('atak-map-3d-flat');
    if (flat) flat.addEventListener('click', function () { setEnabled(false); });
    if (pitchInput) pitchInput.addEventListener('input', function () {
      state.pitch = clamp(pitchInput.value, 25, 65);
      render();
      save();
    });
    if (exaggerationInput) exaggerationInput.addEventListener('input', function () {
      state.verticalExaggeration = clamp(exaggerationInput.value, 1, 4);
      scheduleTerrain();
      render();
      save();
    });
    bindCompass(document.getElementById('atak-map-3d-compass'));
    render();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

  return { setEnabled: setEnabled, getState: function () { return Object.assign({}, state); } };
})();
