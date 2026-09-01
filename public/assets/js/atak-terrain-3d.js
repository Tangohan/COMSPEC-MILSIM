/* ATAK — terrain texturé : les tuiles PNG sont drapées sur le modèle altimétrique. */
window.ATAKTerrain3D = (function () {
  'use strict';

  /* Vue topo premium (Three.js) : ne pas monter le maillage CSS-pitch legacy. */
  if (window.ATAK_TERRAIN3D_PREMIUM) {
    return {
      setEnabled: function () {},
      getState: function () { return { enabled: false, premiumDelegated: true }; },
      premiumDelegated: true,
    };
  }

  var KEY = 'atak_terrain_3d_view';
  var state = { enabled: false, pitch: 48, bearing: 0, verticalExaggeration: 2.5 };
  var stage;
  var button;
  var nav;
  var settings;
  var pitchInput;
  var exaggerationInput;
  var modeSelect;
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
  var terrainLoading = false;
  var shadeTexture;

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

  /* Le canevas doit vivre dans un calque Leaflet (au-dessus des tuiles, sous les
     pastilles). En frère du plan de carte (z-index 400), il reste invisible tant
     que les tuiles sont opaques — d’où des curseurs « morts » sans DEM drapé. */
  function ensureMapPane(map, name, zIndex) {
    if (!map || !map.getPane) return null;
    if (!map.getPane(name)) {
      map.createPane(name);
      var pane = map.getPane(name);
      pane.style.zIndex = String(zIndex);
      pane.style.pointerEvents = 'none';
    }
    return map.getPane(name);
  }

  function placeViewportCanvas(canvas, map, paneName, zIndex) {
    if (!canvas || !map) return;
    var pane = ensureMapPane(map, paneName, zIndex);
    if (!pane) return;
    if (canvas.parentNode !== pane) pane.appendChild(canvas);
    canvas.style.position = 'absolute';
    canvas.style.inset = 'auto';
    canvas.style.pointerEvents = 'none';
    var topLeft = map.containerPointToLayerPoint([0, 0]);
    if (window.L && L.DomUtil && typeof L.DomUtil.setPosition === 'function') {
      L.DomUtil.setPosition(canvas, topLeft);
    } else {
      canvas.style.left = topLeft.x + 'px';
      canvas.style.top = topLeft.y + 'px';
      canvas.style.transform = '';
    }
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
    /* Même couverture que l’ombrage : on drape dès qu’un relevé d’altitudes existe,
       sans attendre un drapeau « théâtre prêt » séparé. */
    if (!data || !data.heights || data.encoding !== 'int16le_b64') return null;
    var raw = atob(data.heights);
    var heights = new Int16Array(Math.floor(raw.length / 2));
    for (var i = 0; i < heights.length; i += 1) {
      var value = raw.charCodeAt(i * 2) | (raw.charCodeAt(i * 2 + 1) << 8);
      heights[i] = value > 32767 ? value - 65536 : value;
    }
    data.values = heights;
    var minZ = Number(data.min_z);
    var maxZ = Number(data.max_z);
    if (!Number.isFinite(minZ) || !Number.isFinite(maxZ) || maxZ <= minZ) {
      var mn = 32767;
      var mx = -32768;
      for (var h = 0; h < heights.length; h += 1) {
        var hz = heights[h];
        if (hz === -32768) continue;
        if (hz < mn) mn = hz;
        if (hz > mx) mx = hz;
      }
      if (mx > mn) {
        data.min_z = mn;
        data.max_z = mx;
      }
    }
    return data;
  }

  function heightAt(world) {
    var g = terrainGrid;
    if (!g || !world) return 0;
    var col = Math.round((world.x - Number(g.origin_x)) / Number(g.cell_m));
    var row = Math.round((world.y - Number(g.origin_y)) / Number(g.cell_m));
    if (col < 0 || row < 0 || col >= g.cols || row >= g.rows) return 0;
    var z = g.values[row * g.cols + col];
    return z === -32768 ? Number(g.min_z || 0) : z;
  }

  /* Convertit toute la plage altimétrique en pixels avant d'appliquer le
     facteur Z. L'ancien coefficient exprimé en pixels/mètre atteignait presque
     toujours sa limite de 550 px : plusieurs valeurs du curseur produisaient
     donc exactement le même maillage, donnant l'impression qu'il ne marchait pas. */
  function reliefOffset(z) {
    var minZ = Number(terrainGrid && terrainGrid.min_z);
    var maxZ = Number(terrainGrid && terrainGrid.max_z);
    if (!Number.isFinite(minZ) || !Number.isFinite(maxZ) || maxZ <= minZ) return 0;
    var normalizedHeight = Math.max(0, Math.min(1, (Number(z) - minZ) / (maxZ - minZ)));
    /* 4.0× à 65° doit rester visiblement vallonné sur Altis, y compris en vue d’ensemble. */
    return normalizedHeight * 180 * state.verticalExaggeration;
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
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    var mapEl = document.getElementById('atak-map');
    if (!mapEl || !map) return false;
    terrainCanvas = document.createElement('canvas');
    terrainCanvas.className = 'atak-terrain-mesh';
    terrainCanvas.setAttribute('aria-hidden', 'true');
    placeViewportCanvas(terrainCanvas, map, 'atakTerrainMeshPane', 250);
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
    terrainGl.enable(terrainGl.BLEND);
    terrainGl.blendFunc(terrainGl.SRC_ALPHA, terrainGl.ONE_MINUS_SRC_ALPHA);
    return true;
  }

  function bindShadeTexture(gl) {
    if (!shadeTexture) {
      shadeTexture = gl.createTexture();
      gl.bindTexture(gl.TEXTURE_2D, shadeTexture);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
      gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, 1, 1, 0, gl.RGBA, gl.UNSIGNED_BYTE, new Uint8Array([236, 228, 214, 130]));
    } else {
      gl.bindTexture(gl.TEXTURE_2D, shadeTexture);
    }
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
    var src = String(image.currentSrc || image.src || '');
    if (src.indexOf('data:image/gif') === 0) return false;
    var width = Number(image.naturalWidth || image.width);
    var height = Number(image.naturalHeight || image.height);
    /* Le repli 1×1 (tuile CDN absente) drapé sur tout le carré produit un
       rectangle blanc. On ne texture que de vraies tuiles carto. */
    return image.complete && width >= 8 && height >= 8;
  }

  function startTerrain() {
    if (!state.enabled) return;
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
      if (terrainGridMapId !== activeMapId) {
        terrainGrid = null;
        terrainLoading = false;
      }
      terrainGridMapId = activeMapId;
      if (!terrainLoading) {
        terrainLoading = true;
        fetch(apiBase() + '/api/atak/terrain?mapId=' + encodeURIComponent(activeMapId) + '&include=heights', { credentials: 'same-origin' })
          .then(function (response) { return response.ok ? response.json() : null; })
          .then(function (data) {
            terrainLoading = false;
            if (terrainGridMapId !== activeMapId) return;
            terrainGrid = decodeGrid(data);
            scheduleTerrain();
          })
          .catch(function () { terrainLoading = false; });
      }
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
    var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    if (terrainCanvas && map) placeViewportCanvas(terrainCanvas, map, 'atakTerrainMeshPane', 250);
    if (!terrainGl || !terrainGrid) {
      if (stage) stage.classList.remove('atak-terrain-mesh-ready');
      syncHillshade();
      return;
    }
    var layer = window.ATAKMap.getBaseTileLayer && window.ATAKMap.getBaseTileLayer();
    if (!map || !layer || !layer._tiles) return;
    var size = map.getSize(), ratio = Math.min(2, window.devicePixelRatio || 1);
    terrainCanvas.width = Math.round(size.x * ratio); terrainCanvas.height = Math.round(size.y * ratio);
    terrainCanvas.style.width = size.x + 'px'; terrainCanvas.style.height = size.y + 'px';
    var gl = terrainGl; gl.viewport(0, 0, terrainCanvas.width, terrainCanvas.height);
    gl.clearColor(0, 0, 0, 0); gl.clear(gl.COLOR_BUFFER_BIT); gl.useProgram(terrainProgram);
    gl.enable(gl.BLEND);
    gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);
    gl.uniform2f(gl.getUniformLocation(terrainProgram, 'size'), size.x, size.y);
    var pLoc = gl.getAttribLocation(terrainProgram, 'p'), uvLoc = gl.getAttribLocation(terrainProgram, 'uv');
    var shadeLoc = gl.getAttribLocation(terrainProgram, 'shade');
    var renderedTiles = 0;
    var drapedTiles = 0;
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
        var relief = reliefOffset(z);
        vertices.push(sx, sy - relief); uvs.push(x / steps, y / steps); shades.push(reliefShade(world));
      }
      for (var gy = 0; gy < steps; gy += 1) for (var gx = 0; gx < steps; gx += 1) {
        var a = gy * (steps + 1) + gx, b = a + 1, c = a + steps + 1, d = c + 1;
        indices.push(a, c, b, b, c, d);
      }
      var texture = null;
      var usedTile = false;
      try {
        texture = gl.createTexture();
        gl.bindTexture(gl.TEXTURE_2D, texture);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
        while (gl.getError() !== gl.NO_ERROR) {}
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, image);
        if (gl.getError() === gl.NO_ERROR) usedTile = true;
        else {
          try { gl.deleteTexture(texture); } catch (e) {}
          texture = null;
          bindShadeTexture(gl);
        }
      } catch (error) {
        if (texture) {
          try { gl.deleteTexture(texture); } catch (e) {}
          texture = null;
        }
        bindShadeTexture(gl);
      }
      try {
        var pb = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, pb); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(vertices), gl.STREAM_DRAW); gl.enableVertexAttribArray(pLoc); gl.vertexAttribPointer(pLoc, 2, gl.FLOAT, false, 0, 0);
        var ub = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, ub); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(uvs), gl.STREAM_DRAW); gl.enableVertexAttribArray(uvLoc); gl.vertexAttribPointer(uvLoc, 2, gl.FLOAT, false, 0, 0);
        var sb = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, sb); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(shades), gl.STREAM_DRAW); gl.enableVertexAttribArray(shadeLoc); gl.vertexAttribPointer(shadeLoc, 1, gl.FLOAT, false, 0, 0);
        var ib = gl.createBuffer(); gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, ib); gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, new Uint16Array(indices), gl.STREAM_DRAW); gl.drawElements(gl.TRIANGLES, indices.length, gl.UNSIGNED_SHORT, 0);
        renderedTiles += 1;
        if (usedTile) drapedTiles += 1;
        gl.deleteBuffer(pb); gl.deleteBuffer(ub); gl.deleteBuffer(sb); gl.deleteBuffer(ib);
        if (texture) gl.deleteTexture(texture);
      } catch (error) { /* Une tuile distante sans CORS reste rendue par Leaflet ; le maillage ombré prend le relais. */ }
    });
    if (terrainCanvas) terrainCanvas.classList.toggle('atak-terrain-mesh--draped', drapedTiles > 0);
    stage.classList.toggle('atak-terrain-mesh-ready', renderedTiles > 0);
    syncHillshade();
  }

  function render() {
    if (!stage) return;
    stage.classList.toggle('atak-map-stage--3d', state.enabled);
    if (!state.enabled) stage.classList.remove('atak-terrain-mesh-ready');
    syncHillshade();
    stage.style.setProperty('--atak-map-bearing-number', String(state.bearing));
    if (button) {
      button.classList.toggle('is-active', state.enabled);
      button.setAttribute('aria-pressed', state.enabled ? 'true' : 'false');
      button.textContent = state.enabled ? '3D actif' : '3D';
    }
    if (nav) nav.hidden = !state.enabled;
    if (settings) {
      settings.removeAttribute('hidden');
      settings.hidden = false;
      settings.classList.toggle('is-inclined', state.enabled);
    }
    if (modeSelect) modeSelect.value = state.enabled ? 'inclined' : 'flat';
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
    /* La case « Relief et profondeur » ne concerne que les pastilles.
       Vue inclinée / 3D actif démarre toujours le maillage du sol. */
    if (state.enabled && window.ATAKMap && window.ATAKMap.patchDisplayPrefs) {
      window.ATAKMap.patchDisplayPrefs({ terrainHillshade: true });
    }
    render();
    if (state.enabled) startTerrain();
    save();
    window.setTimeout(function () {
      var map = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
      if (map && map.invalidateSize) map.invalidateSize(false);
      if (state.enabled) startTerrain();
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
    modeSelect = document.getElementById('atak-terrain-3d-mode');
    pitchInput = document.getElementById('atak-terrain-pitch');
    exaggerationInput = document.getElementById('atak-terrain-exaggeration');
    if (!stage) return;
    restore();
    if (button) button.addEventListener('click', function () { setEnabled(!state.enabled); });
    if (modeSelect) modeSelect.addEventListener('change', function () {
      setEnabled(modeSelect.value === 'inclined');
    });
    var flat = document.getElementById('atak-map-3d-flat');
    if (flat) flat.addEventListener('click', function () { setEnabled(false); });
    if (pitchInput) pitchInput.addEventListener('input', function () {
      state.pitch = clamp(pitchInput.value, 25, 65);
      render();
      if (state.enabled) scheduleTerrain();
      save();
    });
    if (exaggerationInput) exaggerationInput.addEventListener('input', function () {
      state.verticalExaggeration = clamp(exaggerationInput.value, 1, 4);
      if (state.enabled && !terrainGrid) startTerrain();
      else scheduleTerrain();
      render();
      save();
    });
    bindCompass(document.getElementById('atak-map-3d-compass'));
    window.addEventListener('atak:mapready', function () {
      if (state.enabled) startTerrain();
    });
    window.addEventListener('atak:terrain-ready', function () {
      if (state.enabled && !terrainGrid) startTerrain();
    });
    render();
    if (state.enabled) startTerrain();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

  return { setEnabled: setEnabled, getState: function () { return Object.assign({}, state); } };
})();
