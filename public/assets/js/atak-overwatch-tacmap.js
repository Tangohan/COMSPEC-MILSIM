(function () {
  'use strict';

  var AFFIL = { friend: '#5b9dff', hostile: '#ef5b5b', neutral: '#2ecf9a', unknown: '#e8cf4a' };
  var affiliation = 'friend';
  var pendingPin = false;
  var btool = 'wall';
  var floorKey = 'rdc';
  var floorNames = { rdc: 'RDC', 'etage-1': 'Étage 1' };
  var floors = { rdc: [], 'etage-1': [] };
  var draft = null;
  var canvas = null;
  var ctx = null;

  function ow() { return window.OverwatchBeta || {}; }
  function toast(msg) { if (ow().toast) ow().toast(msg); }
  function $(id) { return document.getElementById(id); }

  function setAffiliation(key) {
    affiliation = AFFIL[key] ? key : 'friend';
    var colorEl = $('ow-draw-color');
    if (colorEl) colorEl.value = AFFIL[affiliation];
    document.querySelectorAll('[data-affil]').forEach(function (btn) {
      btn.classList.toggle('is-on', btn.getAttribute('data-affil') === affiliation);
    });
  }

  function pickDraw(name, keep) {
    var api = ow();
    if (!api.setTool) return;
    if (name === 'undo') { if (api.undoLastShape) api.undoLastShape(); return; }
    if (name === 'redo') { if (api.redoLastShape) api.redoLastShape(); return; }
    api.setTool(name, keep !== false);
    closeOtan();
  }

  function closeOtan() {
    var fly = $('ow-otan-flyout');
    var btn = $('ow-otan-btn');
    if (fly) fly.hidden = true;
    if (btn) {
      btn.classList.remove('is-open');
      btn.setAttribute('aria-expanded', 'false');
    }
  }

  function toggleOtan() {
    var fly = $('ow-otan-flyout');
    var btn = $('ow-otan-btn');
    if (!fly || !btn) return;
    var open = fly.hidden;
    fly.hidden = !open;
    btn.classList.toggle('is-open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function setBplanOpen(open) {
    var panel = $('ow-bplan');
    var btn = $('ow-btn-bplan');
    if (!panel) return;
    panel.hidden = !open;
    if (btn) btn.classList.toggle('is-active', open);
    if (open) {
      sizeCanvas();
      redrawPlan();
    }
  }

  function setExportOpen(open) {
    var modal = $('ow-export-modal');
    if (modal) modal.hidden = !open;
  }

  function currentStrokes() {
    if (!floors[floorKey]) floors[floorKey] = [];
    return floors[floorKey];
  }

  function sizeCanvas() {
    canvas = $('ow-bplan-canvas');
    if (!canvas) return;
    var wrap = canvas.parentElement;
    var w = Math.max(200, wrap.clientWidth);
    var h = Math.max(160, wrap.clientHeight);
    if (canvas.width !== w || canvas.height !== h) {
      canvas.width = w;
      canvas.height = h;
    }
    ctx = canvas.getContext('2d');
  }

  function redrawPlan() {
    if (!canvas || !ctx) sizeCanvas();
    if (!canvas || !ctx) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#101413';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = 'rgba(255,255,255,0.06)';
    ctx.lineWidth = 1;
    var g;
    for (g = 20; g < canvas.width; g += 20) {
      ctx.beginPath(); ctx.moveTo(g, 0); ctx.lineTo(g, canvas.height); ctx.stroke();
    }
    for (g = 20; g < canvas.height; g += 20) {
      ctx.beginPath(); ctx.moveTo(0, g); ctx.lineTo(canvas.width, g); ctx.stroke();
    }
    currentStrokes().forEach(drawStroke);
    if (draft) drawStroke(draft);
  }

  function drawStroke(s) {
    if (!ctx) return;
    if (s.tool === 'wall' || s.tool === 'door' || s.tool === 'window') {
      ctx.lineCap = 'square';
      ctx.lineWidth = s.tool === 'wall' ? 3 : 4;
      ctx.strokeStyle = s.tool === 'wall' ? '#8b93a1' : (s.tool === 'door' ? '#2ecf9a' : '#5b9dff');
      ctx.beginPath();
      ctx.moveTo(s.x1, s.y1);
      ctx.lineTo(s.x2, s.y2);
      ctx.stroke();
      return;
    }
    if (s.tool === 'breach') {
      ctx.strokeStyle = '#ef5b5b';
      ctx.lineWidth = 2;
      ctx.beginPath(); ctx.arc(s.x, s.y, 8, 0, Math.PI * 2); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(s.x - 6, s.y - 6); ctx.lineTo(s.x + 6, s.y + 6); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(s.x + 6, s.y - 6); ctx.lineTo(s.x - 6, s.y + 6); ctx.stroke();
      return;
    }
    if (s.tool === 'room') {
      ctx.fillStyle = '#8b93a1';
      ctx.font = '600 11px Inter, sans-serif';
      ctx.fillText(s.text || 'Pièce', s.x, s.y);
    }
  }

  function canvasPoint(event) {
    var rect = canvas.getBoundingClientRect();
    return {
      x: (event.clientX - rect.left) * (canvas.width / rect.width),
      y: (event.clientY - rect.top) * (canvas.height / rect.height)
    };
  }

  function bindPlanCanvas() {
    canvas = $('ow-bplan-canvas');
    if (!canvas) return;
    canvas.addEventListener('mousedown', function (event) {
      if (event.button !== 0) return;
      var p = canvasPoint(event);
      if (btool === 'breach') {
        currentStrokes().push({ tool: 'breach', x: p.x, y: p.y });
        redrawPlan();
        return;
      }
      if (btool === 'room') {
        var name = window.prompt('Nom de la pièce', '') || '';
        if (name.trim()) currentStrokes().push({ tool: 'room', x: p.x, y: p.y, text: name.trim() });
        redrawPlan();
        return;
      }
      draft = { tool: btool, x1: p.x, y1: p.y, x2: p.x, y2: p.y };
    });
    canvas.addEventListener('mousemove', function (event) {
      if (!draft) return;
      var p = canvasPoint(event);
      draft.x2 = p.x;
      draft.y2 = p.y;
      redrawPlan();
    });
    function endDraft() {
      if (!draft) return;
      if (Math.abs(draft.x2 - draft.x1) + Math.abs(draft.y2 - draft.y1) > 4) {
        currentStrokes().push(draft);
      }
      draft = null;
      redrawPlan();
    }
    canvas.addEventListener('mouseup', endDraft);
    canvas.addEventListener('mouseleave', endDraft);
  }

  function addFloor() {
    var n = Object.keys(floors).length;
    var key = 'etage-' + n;
    var label = 'Étage ' + n;
    floors[key] = [];
    floorNames[key] = label;
    var host = $('ow-bplan-floors');
    var addBtn = $('ow-bplan-add-floor');
    if (!host || !addBtn) return;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ow-floor';
    btn.setAttribute('data-floor', key);
    btn.textContent = label;
    host.insertBefore(btn, addBtn);
    selectFloor(key);
  }

  function selectFloor(key) {
    if (key === 'add') return;
    floorKey = key;
    document.querySelectorAll('[data-floor]').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-floor') === key);
    });
    redrawPlan();
  }

  function consumeMapClick(latlng) {
    if (!pendingPin || !latlng) return false;
    pendingPin = false;
    var api = ow();
    if (!api.saveShape) return true;
    var title = (($('ow-bplan-name') || {}).value || '').trim() || 'Plan de bâtiment';
    var payload = {
      kind: 'buildingPlan',
      floors: floors,
      floorNames: floorNames,
      current: floorKey
    };
    api.saveShape('POINT', [latlng], title, { confirmed: true, color: '#2ecf9a', meta: payload });
    toast('Plan rattaché à la carte.');
    setBplanOpen(false);
    return true;
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function pad(n) { return String(n).padStart(2, '0'); }

  function missionStamp() {
    var d = new Date();
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + '  ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }

  function captureMap(withGrid, withAnnos) {
    return new Promise(function (resolve) {
      var mapEl = $('ow-map');
      if (!mapEl) { resolve(null); return; }
      var rect = mapEl.getBoundingClientRect();
      var c = document.createElement('canvas');
      c.width = Math.max(320, Math.round(rect.width));
      c.height = Math.max(240, Math.round(rect.height));
      var cctx = c.getContext('2d');
      cctx.fillStyle = '#1a1f18';
      cctx.fillRect(0, 0, c.width, c.height);
      Array.prototype.forEach.call(mapEl.querySelectorAll('.leaflet-tile-pane img.leaflet-tile, .leaflet-atakAerialPane-pane img.leaflet-tile'), function (img) {
        if (!img.complete || !img.naturalWidth) return;
        var ir = img.getBoundingClientRect();
        try { cctx.drawImage(img, ir.left - rect.left, ir.top - rect.top, ir.width, ir.height); } catch (eImg) {}
      });
      function overlayThenGrid() {
        if (withGrid) {
          cctx.strokeStyle = 'rgba(231, 233, 236, 0.18)';
          cctx.lineWidth = 1;
          var step = 80;
          var x;
          var y;
          for (x = 0; x <= c.width; x += step) {
            cctx.beginPath(); cctx.moveTo(x + 0.5, 0); cctx.lineTo(x + 0.5, c.height); cctx.stroke();
          }
          for (y = 0; y <= c.height; y += step) {
            cctx.beginPath(); cctx.moveTo(0, y + 0.5); cctx.lineTo(c.width, y + 0.5); cctx.stroke();
          }
          var map = ow().map;
          if (map && ow().gridLabel) {
            cctx.fillStyle = 'rgba(231,233,236,0.65)';
            cctx.font = '10px "IBM Plex Mono", monospace';
            var sw = map.containerPointToLatLng([0, c.height]);
            var ne = map.containerPointToLatLng([c.width, 0]);
            cctx.fillText(ow().gridLabel(sw), 8, c.height - 10);
            cctx.fillText(ow().gridLabel(ne), c.width - 110, 16);
          }
        }
        resolve(c);
      }
      var svg = withAnnos !== false ? mapEl.querySelector('.leaflet-overlay-pane svg') : null;
      if (!svg) { overlayThenGrid(); return; }
      var xml = new XMLSerializer().serializeToString(svg);
      var url = URL.createObjectURL(new Blob([xml], { type: 'image/svg+xml;charset=utf-8' }));
      var image = new Image();
      image.onload = function () {
        var sr = svg.getBoundingClientRect();
        try { cctx.drawImage(image, sr.left - rect.left, sr.top - rect.top, sr.width, sr.height); } catch (eSvg) {}
        URL.revokeObjectURL(url);
        overlayThenGrid();
      };
      image.onerror = function () { URL.revokeObjectURL(url); overlayThenGrid(); };
      image.src = url;
    });
  }

  function natoLegendHtml() {
    return '<div class="leg"><b>Légende tactique</b>' +
      '<div><i style="background:#5b9dff"></i> Ami</div>' +
      '<div><i style="background:#ef5b5b"></i> Ennemi</div>' +
      '<div><i style="background:#2ecf9a"></i> Neutre</div>' +
      '<div><i style="background:#e8cf4a"></i> Inconnu</div>' +
      '<div>Axe de progression — flèche simple</div>' +
      '<div>Attaque principale — flèche pleine</div>' +
      '<div>Ligne de phase — tirets</div>' +
      '<div>Limite de secteur — trait mixte</div>' +
      '<div>Zone de rassemblement — rectangle</div>' +
      '<div>Objectif — ellipse</div>' +
      '<div>Zone à risque — surlignage</div></div>';
  }

  function chatHtml() {
    var rows = (ow().getChatMessages && ow().getChatMessages()) || [];
    var channel = (ow().getActiveChannel && ow().getActiveChannel()) || 'general';
    var label = channel === 'general' ? 'Général' : channel;
    var bits = rows.slice(-8).map(function (row) {
      var who = escapeHtml(row.author || row.callsign || 'Opérateur');
      var body = escapeHtml(row.body || row.message || row.text || '');
      return '<p><b>' + who + '</b> ' + body + '</p>';
    }).join('');
    return '<div class="feed"><b>Fil — ' + escapeHtml(label) + '</b>' + (bits || '<p>Aucun message sur ce canal.</p>') + '</div>';
  }

  function scaleLabel() {
    var map = ow().map;
    if (!map || !ow().formatMeters) return 'Échelle selon le cadrage actuel';
    var a = map.containerPointToLatLng([0, 0]);
    var b = map.containerPointToLatLng([100, 0]);
    return '100 px ≈ ' + ow().formatMeters(map.distance(a, b));
  }

  function generatePdf() {
    var withAnnos = ($('ow-pdf-annos') || {}).checked !== false;
    var withLegend = ($('ow-pdf-legend') || {}).checked !== false;
    var withGrid = !!($('ow-pdf-grid') || {}).checked;
    var withTime = !!($('ow-pdf-time') || {}).checked;
    var withChat = !!($('ow-pdf-chat') || {}).checked;
    var format = ($('ow-pdf-format') || {}).value || 'a4-landscape';
    var tenant = String(window.ATAK_TENANT_LABEL || 'Athena');
    var mapName = String((window.ATAK_MAP_CONFIG && (window.ATAK_MAP_CONFIG.name || window.ATAK_MAP_CONFIG.slug)) || 'Théâtre');
    setExportOpen(false);
      captureMap(withGrid, withAnnos).then(function (c) {
      var page = window.open('', 'ow-briefing', 'noopener,noreferrer,width=1200,height=850');
      if (!page) { toast('Autorisez l’ouverture de fenêtre pour l’export.'); return; }
      var landscape = format !== 'a4-portrait';
      var css = '@page{size:' + (format === 'letter-landscape' ? 'letter landscape' : (landscape ? 'A4 landscape' : 'A4 portrait')) + ';margin:12mm}' +
        'body{font-family:Inter,Segoe UI,sans-serif;color:#111;background:#fff;margin:0;padding:16px}' +
        'h1{font-size:18px;margin:0 0 4px} .sub{color:#555;font-size:12px;margin:0 0 12px}' +
        '.sheet{display:grid;grid-template-columns:' + (withLegend || withChat ? '1fr 220px' : '1fr') + ';gap:14px;align-items:start}' +
        'img.map{width:100%;border:1px solid #ccc;background:#1a1f18}' +
        '.leg,.feed{border:1px solid #ddd;padding:10px;font-size:11px;line-height:1.45}' +
        '.leg b,.feed b{display:block;margin-bottom:6px;font-size:12px}' +
        '.leg i{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:6px;vertical-align:middle}' +
        '.leg div,.feed p{margin:0 0 5px}' +
        '.scale{font-size:11px;color:#444;margin-top:6px}';
      var html = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Briefing tactique</title><style>' + css + '</style></head><body>';
      html += '<h1>' + escapeHtml(tenant) + ' / ' + escapeHtml(mapName) + '</h1>';
      html += '<p class="sub">Briefing de carte' + (withTime ? ' · ' + escapeHtml(missionStamp()) : '') + '</p>';
      html += '<div class="sheet"><div>';
      if (c) html += '<img class="map" alt="Carte du théâtre" src="' + c.toDataURL('image/jpeg', 0.82) + '">';
      html += '<p class="scale">' + escapeHtml(scaleLabel()) + '</p></div><div>';
      if (withLegend) html += natoLegendHtml();
      if (withChat) html += chatHtml();
      html += '</div></div></body></html>';
      page.document.write(html);
      page.document.close();
      page.focus();
      window.setTimeout(function () {
        try { page.print(); } catch (ePrint) {}
      }, 350);
    });
  }

  function bind() {
    document.querySelectorAll('#ow-drawbar [data-draw]').forEach(function (btn) {
      btn.addEventListener('click', function (event) {
        event.preventDefault();
        pickDraw(btn.getAttribute('data-draw'), true);
      });
    });
    var otanBtn = $('ow-otan-btn');
    if (otanBtn) otanBtn.addEventListener('click', function (event) {
      event.preventDefault();
      toggleOtan();
    });
    document.querySelectorAll('[data-affil]').forEach(function (btn) {
      btn.addEventListener('click', function () { setAffiliation(btn.getAttribute('data-affil')); });
    });
    var bplanBtn = $('ow-btn-bplan');
    if (bplanBtn) bplanBtn.addEventListener('click', function () {
      setBplanOpen($('ow-bplan').hidden);
    });
    var bplanClose = $('ow-bplan-close');
    if (bplanClose) bplanClose.addEventListener('click', function () { setBplanOpen(false); });
    var addFloorBtn = $('ow-bplan-add-floor');
    if (addFloorBtn) addFloorBtn.addEventListener('click', addFloor);
    var floorsEl = document.getElementById('ow-bplan-floors');
    if (floorsEl) floorsEl.addEventListener('click', function (event) {
      var btn = event.target.closest('[data-floor]');
      if (!btn || btn.id === 'ow-bplan-add-floor') return;
      selectFloor(btn.getAttribute('data-floor'));
    });
    document.querySelectorAll('[data-btool]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        btool = btn.getAttribute('data-btool');
        document.querySelectorAll('[data-btool]').forEach(function (other) {
          other.classList.toggle('is-active', other === btn);
        });
      });
    });
    var savePlan = $('ow-bplan-save');
    if (savePlan) savePlan.addEventListener('click', function () {
      pendingPin = true;
      var api = ow();
      if (api.setTool) api.setTool('cursor', true);
      toast('Cliquez le bâtiment sur la carte pour y rattacher le plan.');
    });
    var exportBtn = $('ow-btn-export');
    if (exportBtn) exportBtn.addEventListener('click', function () { setExportOpen(true); });
    var exportClose = $('ow-export-close');
    if (exportClose) exportClose.addEventListener('click', function () { setExportOpen(false); });
    var exportGo = $('ow-export-go');
    if (exportGo) exportGo.addEventListener('click', generatePdf);
    var backdrop = $('ow-export-modal');
    if (backdrop) backdrop.addEventListener('click', function (event) {
      if (event.target === backdrop) setExportOpen(false);
    });
    bindPlanCanvas();
    setAffiliation('friend');
    document.addEventListener('click', function (event) {
      if (!event.target.closest('#ow-drawbar .ow-drawbar-otan')) closeOtan();
    });
    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') return;
      var modal = $('ow-export-modal');
      if (modal && !modal.hidden) { event.stopImmediatePropagation(); setExportOpen(false); return; }
      var fly = $('ow-otan-flyout');
      if (fly && !fly.hidden) { event.stopImmediatePropagation(); closeOtan(); return; }
      var plan = $('ow-bplan');
      if (plan && !plan.hidden) { event.stopImmediatePropagation(); setBplanOpen(false); }
    }, true);
  }

  window.OverwatchTacmap = {
    consumeMapClick: consumeMapClick,
    isPinning: function () { return pendingPin; }
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();
}());
