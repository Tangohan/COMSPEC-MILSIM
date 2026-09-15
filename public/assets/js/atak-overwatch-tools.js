/* Overwatch Beta — outils carte autonomes (grille, aller à, anneaux, NVG, photos, polices). */
(function () {
  'use strict';
  if (!window.ATAK_OVERWATCH_BETA || window.__OVERWATCH_TOOLS__) return;
  if (!document.getElementById('ow-map')) return;
  window.__OVERWATCH_TOOLS__ = true;

  var CHROME_KEY = 'athena:overwatch-chrome-v1';
  var NOTE_KEY = 'athena:overwatch-notepad';
  var gridLayer = null;
  var rangeLayer = null;
  var photoLayers = [];
  var scaleCtl = null;
  var chrome = {
    uiFont: 'plex',
    labelFont: 'mono',
    uiSize: '13',
    grid: false,
    gridStep: 1000,
    nvg: false,
    photos: false,
    crosshair: false,
    scale: true,
    heatOpacity: 40,
    aerialOpacity: 100
  };

  function ow() { return window.OverwatchBeta || null; }
  function toast(text) { var api = ow(); if (api) api.toast(text); }
  function esc(value) { var api = ow(); return api ? api.escapeHtml(value) : String(value == null ? '' : value); }

  var FONTS = {
    plex: '"IBM Plex Sans", "Segoe UI", system-ui, sans-serif',
    grotesk: '"Space Grotesk", "IBM Plex Sans", sans-serif',
    noto: '"Noto Sans", "IBM Plex Sans", sans-serif',
    source: '"Source Sans 3", "IBM Plex Sans", sans-serif',
    mono: '"IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace'
  };

  function loadChrome() {
    try {
      var raw = JSON.parse(localStorage.getItem(CHROME_KEY) || '{}');
      if (raw && typeof raw === 'object') Object.keys(chrome).forEach(function (k) {
        if (raw[k] != null) chrome[k] = raw[k];
      });
    } catch (e) {}
  }
  function saveChrome() {
    try { localStorage.setItem(CHROME_KEY, JSON.stringify(chrome)); } catch (e) {}
  }

  function applyFonts() {
    var root = document.documentElement;
    var ui = FONTS[chrome.uiFont] || FONTS.plex;
    var labels = FONTS[chrome.labelFont] || FONTS.mono;
    root.style.setProperty('--ow-sans', ui);
    if (chrome.uiFont === 'grotesk') root.style.setProperty('--ow-display', FONTS.grotesk);
    else root.style.setProperty('--ow-display', chrome.uiFont === 'mono' ? FONTS.mono : '"Space Grotesk", "IBM Plex Sans", sans-serif');
    root.style.setProperty('--ow-label-font', labels);
    root.style.fontSize = (Number(chrome.uiSize) || 13) + 'px';
    root.setAttribute('data-ow-ui-font', chrome.uiFont);
    root.setAttribute('data-ow-label-font', chrome.labelFont);
    var mapEl = document.getElementById('ow-map');
    if (mapEl) mapEl.style.setProperty('--ow-label-font', labels);
  }

  function applyNvg() {
    var stage = document.getElementById('ow-map-stage');
    if (!stage) return;
    stage.classList.toggle('is-nvg', !!chrome.nvg);
    var api = ow();
    if (!api || !api.map) return;
    var tile = api.map.getPane('tilePane');
    var aerial = api.map.getPane('atakAerialPane');
    var look = stage.dataset.look || 'color';
    var bw = look === 'bw' ? 'grayscale(1) contrast(1.18) brightness(1.02)' : 'none';
    var nvg = 'hue-rotate(78deg) saturate(.55) brightness(.82) contrast(1.18)';
    var filter = chrome.nvg ? (look === 'bw' ? 'grayscale(1) ' + nvg : nvg) : bw;
    if (tile) tile.style.filter = filter;
    if (aerial) aerial.style.filter = filter;
  }

  function applyCrosshair() {
    var stage = document.getElementById('ow-map-stage');
    if (!stage) return;
    var el = document.getElementById('ow-crosshair');
    if (!el) {
      el = document.createElement('div');
      el.id = 'ow-crosshair';
      el.className = 'ow-crosshair';
      el.setAttribute('aria-hidden', 'true');
      stage.appendChild(el);
    }
    el.hidden = !chrome.crosshair;
  }

  function applyScale() {
    var api = ow();
    if (!api || !api.map || !window.L || !L.control || !L.control.scale) return;
    if (chrome.scale && !scaleCtl) {
      scaleCtl = L.control.scale({ metric: true, imperial: false, position: 'bottomleft' });
      scaleCtl.addTo(api.map);
    }
    if (!chrome.scale && scaleCtl) {
      try { api.map.removeControl(scaleCtl); } catch (e) {}
      scaleCtl = null;
    }
  }

  function applyHeatOpacity() {
    document.documentElement.style.setProperty('--ow-heat-opacity', String((Number(chrome.heatOpacity) || 40) / 100));
  }

  function worldSize() {
    var cfg = window.ATAK_MAP_CONFIG || {};
    var n = Number(cfg.worldSize || (cfg.config && cfg.config.worldSize) || 30720);
    return n > 1000 ? n : 30720;
  }

  function clearGrid() {
    var api = ow();
    if (gridLayer && api) {
      try { api.map.removeLayer(gridLayer); } catch (e) {}
    }
    gridLayer = null;
  }

  function drawGrid() {
    clearGrid();
    if (!chrome.grid) return;
    var api = ow();
    if (!api || !api.map) return;
    var step = Math.max(250, Number(chrome.gridStep) || 1000);
    var W = worldSize();
    var group = L.layerGroup();
    var style = { color: '#7d8883', weight: 1, opacity: 0.35, interactive: false, dashArray: '2 6' };
    var major = { color: '#a8b0ad', weight: 1, opacity: 0.55, interactive: false };
    var x;
    for (x = 0; x <= W; x += step) {
      var a = api.worldToLatLng(x, 0);
      var b = api.worldToLatLng(x, W);
      L.polyline([a, b], x % (step * 5) === 0 ? major : style).addTo(group);
    }
    var y;
    for (y = 0; y <= W; y += step) {
      var c = api.worldToLatLng(0, y);
      var d = api.worldToLatLng(W, y);
      L.polyline([c, d], y % (step * 5) === 0 ? major : style).addTo(group);
    }
    group.addTo(api.map);
    gridLayer = group;
  }

  function clearRange() {
    var api = ow();
    if (rangeLayer && api) {
      try { api.map.removeLayer(rangeLayer); } catch (e) {}
    }
    rangeLayer = null;
  }

  function placeRange(ll, radii) {
    var api = ow();
    if (!api || !ll) return;
    clearRange();
    var group = L.layerGroup();
    (radii || [100, 250, 500]).forEach(function (m) {
      L.circle(ll, { radius: m, color: '#e7b14d', weight: 1, dashArray: '4 4', fillOpacity: 0.03, interactive: false }).addTo(group);
      var edge = api.worldToLatLng(api.latLngToWorld(ll).x + m, api.latLngToWorld(ll).y);
      L.marker(edge, {
        interactive: false,
        icon: L.divIcon({ className: 'ow-ring-label', html: '<span>' + m + ' m</span>' })
      }).addTo(group);
    });
    group.addTo(api.map);
    rangeLayer = group;
    toast('Anneaux posés autour du point.');
  }

  function parseGrid(raw) {
    var text = String(raw || '').trim().replace(/,/g, ' ');
    var bits = text.split(/[\s\/xX]+/).filter(Boolean);
    if (bits.length < 2) return null;
    var x = Number(bits[0]);
    var y = Number(bits[1]);
    if (!isFinite(x) || !isFinite(y)) return null;
    return { x: x, y: y };
  }

  function gotoGrid(raw) {
    var api = ow();
    if (!api) return;
    var g = parseGrid(raw);
    if (!g) { toast('Indiquez deux nombres : est puis nord.'); return; }
    var ll = api.worldToLatLng(g.x, g.y);
    api.map.setView(ll, Math.max(api.map.getZoom(), 4));
    toast(api.gridLabel(ll));
  }

  function clearPhotos() {
    var api = ow();
    photoLayers.forEach(function (layer) {
      if (api) try { api.map.removeLayer(layer); } catch (e) {}
    });
    photoLayers = [];
  }

  function drawPhotos() {
    clearPhotos();
    if (!chrome.photos) return;
    var api = ow();
    if (!api) return;
    var rows = typeof api.getPhotos === 'function' ? api.getPhotos() : [];
    rows.forEach(function (row) {
      var x = Number(row.pos_x != null ? row.pos_x : row.x);
      var y = Number(row.pos_y != null ? row.pos_y : row.y);
      if (!isFinite(x) || !isFinite(y) || (Math.abs(x) < 0.5 && Math.abs(y) < 0.5)) return;
      var ll = api.worldToLatLng(x, y);
      var url = String(row.url || '');
      var pin = L.circleMarker(ll, { radius: 7, color: '#8b8bf0', weight: 2, fillOpacity: 0.7 });
      if (pin.bindTooltip) pin.bindTooltip(esc(row.caption || row.author || 'Photo'), { direction: 'top' });
      if (url && pin.on) {
        pin.on('click', function () {
          api.openDrawer('Renseignement', 'Photo', (url ? '<div class="ow-thumb" style="background-image:url(\'' + esc(url) + '\')"></div>' : '') +
            '<p class="ow-help">' + esc(row.caption || row.author || 'Photo de terrain') + '</p>');
        });
      }
      pin.addTo(api.map);
      photoLayers.push(pin);
    });
  }

  function syncInputs() {
    var ui = document.getElementById('ow-ui-font');
    var lab = document.getElementById('ow-label-font');
    var size = document.getElementById('ow-ui-size');
    var grid = document.getElementById('ow-grid-overlay');
    var step = document.getElementById('ow-grid-step');
    var nvg = document.getElementById('ow-nvg');
    var photos = document.getElementById('ow-intel-photos');
    var cross = document.getElementById('ow-crosshair-toggle');
    var scale = document.getElementById('ow-scale-bar');
    var heat = document.getElementById('ow-heat-opacity');
    if (ui) ui.value = chrome.uiFont;
    if (lab) lab.value = chrome.labelFont;
    if (size) size.value = String(chrome.uiSize);
    if (grid) grid.checked = !!chrome.grid;
    if (step) step.value = String(chrome.gridStep);
    if (nvg) nvg.checked = !!chrome.nvg;
    if (photos) photos.checked = !!chrome.photos;
    if (cross) cross.checked = !!chrome.crosshair;
    if (scale) scale.checked = !!chrome.scale;
    if (heat) heat.value = String(chrome.heatOpacity);
  }

  function bindInputs() {
    function on(id, fn) {
      var el = document.getElementById(id);
      if (!el) return;
      el.addEventListener('change', fn);
      el.addEventListener('input', fn);
    }
    on('ow-ui-font', function (ev) { chrome.uiFont = ev.target.value; saveChrome(); applyFonts(); });
    on('ow-label-font', function (ev) { chrome.labelFont = ev.target.value; saveChrome(); applyFonts(); });
    on('ow-ui-size', function (ev) { chrome.uiSize = ev.target.value; saveChrome(); applyFonts(); });
    on('ow-grid-overlay', function (ev) { chrome.grid = !!ev.target.checked; saveChrome(); drawGrid(); });
    on('ow-grid-step', function (ev) { chrome.gridStep = Number(ev.target.value) || 1000; saveChrome(); drawGrid(); });
    on('ow-nvg', function (ev) { chrome.nvg = !!ev.target.checked; saveChrome(); applyNvg(); });
    on('ow-intel-photos', function (ev) {
      chrome.photos = !!ev.target.checked;
      saveChrome();
      if (window.ATAKMap && window.ATAKMap.patchDisplayPrefs) {
        window.ATAKMap.patchDisplayPrefs({ showIntelPhotoMarkers: chrome.photos });
      }
      drawPhotos();
    });
    on('ow-crosshair-toggle', function (ev) { chrome.crosshair = !!ev.target.checked; saveChrome(); applyCrosshair(); });
    on('ow-scale-bar', function (ev) { chrome.scale = !!ev.target.checked; saveChrome(); applyScale(); });
    on('ow-heat-opacity', function (ev) {
      chrome.heatOpacity = Number(ev.target.value) || 40;
      saveChrome();
      applyHeatOpacity();
      var api = ow();
      if (api && api.renderPresenceHeat) api.renderPresenceHeat();
    });
    var icon = document.getElementById('ow-icon-size');
    if (icon) icon.addEventListener('input', function () {
      var v = Number(icon.value) || 20;
      document.documentElement.style.setProperty('--ow-icon-size', Math.max(6, Math.round(v / 2.5)) + 'px');
      if (window.ATAKMap && window.ATAKMap.patchDisplayPrefs) window.ATAKMap.patchDisplayPrefs({ iconSize: v });
    });
    ['ow-look-depth', 'ow-look-motion', 'ow-look-frame'].forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.addEventListener('change', function () {
        if (!window.ATAKMap || !window.ATAKMap.patchDisplayPrefs) return;
        var patch = {};
        if (id === 'ow-look-depth') patch.markerDepth = !!el.checked;
        if (id === 'ow-look-motion') patch.markerMotion = !!el.checked;
        if (id === 'ow-look-frame') patch.showFtFrame = !!el.checked;
        window.ATAKMap.patchDisplayPrefs(patch);
        var mapEl = document.getElementById('ow-map');
        if (mapEl && id === 'ow-look-depth') mapEl.classList.toggle('atak-map--marker-depth', !!el.checked);
        if (mapEl && id === 'ow-look-motion') mapEl.classList.toggle('atak-map--marker-motion', !!el.checked);
      });
    });
  }

  function openGoto() {
    var api = ow();
    if (!api) return;
    var html = '<p class="ow-help">Deux nombres du théâtre : est puis nord. Vous pouvez aussi cliquer la carte avec l’outil Aller à.</p>' +
      '<form class="ow-form-grid" id="ow-goto-form"><label>Grille<input name="grid" placeholder="15200 15840" autocomplete="off"></label>' +
      '<button class="ow-primary" type="submit">Centrer</button></form>';
    api.openDrawer('Carte', 'Aller à une grille', html);
    var form = document.getElementById('ow-goto-form');
    if (form) form.addEventListener('submit', function (event) {
      event.preventDefault();
      gotoGrid(form.querySelector('[name="grid"]').value);
    });
  }

  function openNotes() {
    var api = ow();
    if (!api) return;
    var stored = '';
    try { stored = localStorage.getItem(NOTE_KEY) || ''; } catch (e) {}
    var html = '<p class="ow-help">Bloc-notes du poste, conservé sur cet ordinateur. Il n’est pas transmis aux opérateurs tant que vous ne l’envoyez pas sur un canal.</p>' +
      '<textarea id="ow-notepad" rows="10" style="width:100%;min-height:180px;background:#0e1211;border:1px solid #27302d;color:#e4e8e8;padding:8px">' + esc(stored) + '</textarea>' +
      '<div class="ow-row" style="margin-top:8px;gap:8px">' +
      '<button type="button" class="ow-primary" id="ow-note-save">Enregistrer</button>' +
      '<button type="button" class="ow-secondary" id="ow-note-send">Envoyer sur le canal</button></div>';
    api.openDrawer('Mission', 'Bloc-notes', html);
    var area = document.getElementById('ow-notepad');
    var save = document.getElementById('ow-note-save');
    var send = document.getElementById('ow-note-send');
    if (save) save.addEventListener('click', function () {
      try { localStorage.setItem(NOTE_KEY, area.value); } catch (e2) {}
      toast('Notes enregistrées sur ce poste.');
    });
    if (send) send.addEventListener('click', function () {
      var text = String(area.value || '').trim();
      if (!text) { toast('Le bloc-notes est vide.'); return; }
      api.api('/api/chat', { method: 'POST', body: { mapId: api.mapId, author: api.authorName, body: text, channel: 'commandement' } })
        .then(function () { toast('Notes envoyées sur le canal commandement.'); })
        .catch(function () { toast('Envoi refusé.'); });
    });
  }

  function handleTool(tool, ll) {
    if (tool === 'goto' && ll) {
      var api = ow();
      if (api) { api.map.setView(ll, Math.max(api.map.getZoom(), 4)); toast(api.gridLabel(ll)); }
      return true;
    }
    if (tool === 'range' && ll) {
      placeRange(ll, [100, 250, 500, 1000]);
      return true;
    }
    return false;
  }

  function ready() {
    var api = ow();
    if (!api) { window.setTimeout(ready, 40); return; }
    loadChrome();
    syncInputs();
    bindInputs();
    applyFonts();
    applyNvg();
    applyCrosshair();
    applyScale();
    applyHeatOpacity();
    drawGrid();
    drawPhotos();
    window.addEventListener('overwatch:tool', function (event) {
      var tool = event.detail && event.detail.tool;
      if (tool === 'goto') { openGoto(); toast('Cliquez la carte ou saisissez une grille.'); }
      if (tool === 'range') toast('Cliquez le centre des anneaux de portée.');
      if (tool === 'nvg') {
        chrome.nvg = !chrome.nvg;
        saveChrome();
        var box = document.getElementById('ow-nvg');
        if (box) box.checked = chrome.nvg;
        applyNvg();
        toast(chrome.nvg ? 'Lecture nocturne activée.' : 'Lecture nocturne retirée.');
      }
    });
    api.map.on('click', function (event) {
      var tool = document.querySelector('.ow-rail [data-tool].is-active');
      var name = tool && tool.getAttribute('data-tool');
      handleTool(name, event.latlng);
    });
    api.map.on('moveend', function () { if (chrome.grid) drawGrid(); });
    window.addEventListener('overwatch:units-updated', function () { if (chrome.photos) drawPhotos(); });
    document.addEventListener('click', function (event) {
      var go = event.target.closest('[data-ow-goto]');
      if (go) openGoto();
      var notes = event.target.closest('[data-ow-notes]');
      if (notes) openNotes();
      var nvgBtn = event.target.closest('[data-ow-nvg]');
      if (nvgBtn) {
        chrome.nvg = !chrome.nvg;
        saveChrome();
        var box = document.getElementById('ow-nvg');
        if (box) box.checked = chrome.nvg;
        applyNvg();
        toast(chrome.nvg ? 'Lecture nocturne activée.' : 'Lecture nocturne retirée.');
      }
      var rng = event.target.closest('[data-ow-range-clear]');
      if (rng) { clearRange(); toast('Anneaux retirés.'); }
    });
    window.OverwatchTools = {
      gotoGrid: gotoGrid,
      placeRange: placeRange,
      clearRange: clearRange,
      openGoto: openGoto,
      openNotes: openNotes,
      drawPhotos: drawPhotos,
      applyFonts: applyFonts,
      applyNvg: applyNvg
    };
  }

  ready();
})();
