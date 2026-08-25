/* ATAK — volumes tactiques remontés par le jeu (bâtiments et couverts forestiers). */
window.ATAKScene3D = (function () {
  'use strict';

  var enabled = true, canvas, ctx, objects = [], frame = 0, fetchTimer = 0, boundMap;

  function apiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : (window.ATAK_API_BASE || window.ATAK_BASE_URL || '');
  }
  function mapId() { return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1; }
  function worldPoint(x, y) {
    var map = window.ATAKMap.getMap(), ll = window.ATAKMap.latLngFromWorld(Number(x), Number(y));
    return map.latLngToContainerPoint(ll);
  }
  function corners(item) {
    var angle = Number(item.bearing || 0) * Math.PI / 180, c = Math.cos(angle), s = Math.sin(angle);
    var hw = Number(item.width || 4) / 2, hd = Number(item.depth || 4) / 2;
    return [[-hw,-hd],[hw,-hd],[hw,hd],[-hw,hd]].map(function (v) {
      return worldPoint(Number(item.x) + v[0] * c - v[1] * s, Number(item.y) + v[0] * s + v[1] * c);
    });
  }
  function polygon(points, fill, stroke) {
    if (!points.length) return;
    ctx.beginPath(); ctx.moveTo(points[0].x, points[0].y);
    points.slice(1).forEach(function (p) { ctx.lineTo(p.x, p.y); });
    ctx.closePath(); ctx.fillStyle = fill; ctx.fill();
    if (stroke) { ctx.strokeStyle = stroke; ctx.lineWidth = 1; ctx.stroke(); }
  }
  function drawObject(item) {
    var base = corners(item), scale = Math.max(.28, Math.min(1.4, boundMap.getZoom() / 9));
    var rise = Math.max(2, Number(item.height || 3) * scale);
    var top = base.map(function (p) { return { x: p.x, y: p.y - rise }; });
    var forest = item.kind === 'forest', alpha = forest ? Math.max(.18, Math.min(.58, Number(item.density || 1) * .58)) : .82;
    polygon([base[1], base[2], top[2], top[1]], forest ? 'rgba(20,83,45,' + alpha + ')' : 'rgba(71,85,105,.78)');
    polygon([base[2], base[3], top[3], top[2]], forest ? 'rgba(22,101,52,' + alpha + ')' : 'rgba(51,65,85,.88)');
    polygon(top, forest ? 'rgba(74,222,128,' + alpha + ')' : 'rgba(203,213,225,.9)', forest ? 'rgba(134,239,172,.7)' : 'rgba(15,23,42,.8)');
  }
  function draw() {
    frame = 0;
    if (!canvas || !boundMap) return;
    var size = boundMap.getSize(), ratio = Math.min(2, window.devicePixelRatio || 1);
    canvas.width = Math.round(size.x * ratio); canvas.height = Math.round(size.y * ratio);
    canvas.style.width = size.x + 'px'; canvas.style.height = size.y + 'px';
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0); ctx.clearRect(0, 0, size.x, size.y);
    if (!enabled || !document.querySelector('.atak-map-stage.atak-map-stage--3d')) return;
    objects.slice().sort(function (a, b) { return Number(a.y) - Number(b.y); }).forEach(drawObject);
  }
  function schedule() { if (!frame) frame = requestAnimationFrame(draw); }
  function loadVisible() {
    if (!boundMap || !enabled) return;
    var bounds = boundMap.getBounds(), nw = window.ATAKMap.worldFromLatLng(bounds.getNorthWest()), se = window.ATAKMap.worldFromLatLng(bounds.getSouthEast());
    var bbox = [Math.min(nw.x,se.x), Math.min(nw.y,se.y), Math.max(nw.x,se.x), Math.max(nw.y,se.y)].join(',');
    fetch(apiBase() + '/api/atak/scene?mapId=' + encodeURIComponent(mapId()) + '&bbox=' + encodeURIComponent(bbox), { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; }).then(function (data) { objects = data && Array.isArray(data.objects) ? data.objects : []; schedule(); }).catch(function () {});
  }
  function queueLoad() { window.clearTimeout(fetchTimer); fetchTimer = window.setTimeout(loadVisible, 180); schedule(); }
  function init() {
    var mapEl = document.getElementById('atak-map'), toggle = document.getElementById('atak-scene-buildings');
    boundMap = window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
    if (!mapEl || !boundMap) return;
    canvas = document.createElement('canvas'); canvas.className = 'atak-scene-3d'; canvas.setAttribute('aria-hidden', 'true'); mapEl.appendChild(canvas); ctx = canvas.getContext('2d');
    ['move','moveend','zoomend','resize'].forEach(function (name) { boundMap.on(name, name === 'moveend' || name === 'zoomend' ? queueLoad : schedule); });
    if (toggle) toggle.addEventListener('change', function () { enabled = toggle.checked; if (enabled) loadVisible(); else schedule(); });
    window.addEventListener('atak:terrain3dchange', schedule); loadVisible();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
  return { reload: loadVisible };
})();
