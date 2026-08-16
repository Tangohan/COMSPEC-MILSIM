/**
 * Graphe SVG léger — Intelligence Workspace LOT 2.
 */
(function () {
  'use strict';
  var cfg = window.SSE_IW;
  var svg = document.getElementById('sse-iw-graph');
  if (!cfg || !svg) return;

  var wrap = svg.parentElement;
  var rawNodes = (cfg.graph && cfg.graph.nodes) || [];
  var rawEdges = (cfg.graph && cfg.graph.edges) || [];
  var statusFilter = 'any';
  var scale = 1;
  var panX = 0;
  var panY = 0;
  var drag = null;
  var W = 800;
  var H = 480;

  function size() {
    var r = wrap.getBoundingClientRect();
    W = Math.max(480, Math.floor(r.width || 800));
    H = Math.max(360, Math.floor(r.height || 480));
    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    svg.setAttribute('width', String(W));
    svg.setAttribute('height', String(H));
  }

  function layout(nodes) {
    var n = nodes.length || 1;
    var cx = W / 2;
    var cy = H / 2;
    var rad = Math.min(W, H) * 0.32;
    nodes.forEach(function (node, i) {
      var a = (i / n) * Math.PI * 2;
      node.x = cx + Math.cos(a) * rad;
      node.y = cy + Math.sin(a) * rad;
    });
  }

  function filteredEdges() {
    return rawEdges.filter(function (e) {
      if (statusFilter === 'any') return true;
      if (statusFilter === 'proposed') return !!e.proposed || e.status === 'proposed';
      return !e.proposed && e.status !== 'proposed';
    });
  }

  function render() {
    size();
    var nodes = rawNodes.map(function (n) {
      return {
        id: n.uuid || n.id,
        label: n.label || n.uuid || '?',
        type: n.entity_type || '',
        conf: n.confidence_code || '',
        x: 0,
        y: 0
      };
    });
    layout(nodes);
    var byId = {};
    nodes.forEach(function (n) { byId[n.id] = n; });
    var edges = filteredEdges().filter(function (e) {
      return byId[e.from] && byId[e.to];
    });

    var g = '';
    edges.forEach(function (e) {
      var a = byId[e.from];
      var b = byId[e.to];
      var dash = e.proposed || e.status === 'proposed' ? ' stroke-dasharray="4 3"' : '';
      g += '<line class="iw-g-edge" x1="' + a.x + '" y1="' + a.y + '" x2="' + b.x + '" y2="' + b.y + '"' + dash + '></line>';
    });
    nodes.forEach(function (n) {
      g += '<g class="iw-g-node" data-id="' + String(n.id).replace(/"/g, '') + '" transform="translate(' + n.x + ',' + n.y + ')">'
        + '<circle r="18"></circle>'
        + '<text y="4">' + escapeXml((n.label || '').slice(0, 14)) + '</text>'
        + '<title>' + escapeXml(n.label + ' · ' + n.type + ' · ' + n.conf) + '</title>'
        + '</g>';
    });
    svg.innerHTML = '<g class="iw-g-root" transform="translate(' + panX + ',' + panY + ') scale(' + scale + ')">' + g + '</g>';

    svg.querySelectorAll('.iw-g-node').forEach(function (el) {
      el.addEventListener('click', function (ev) {
        ev.stopPropagation();
        var id = el.getAttribute('data-id');
        var node = rawNodes.find(function (n) { return (n.uuid || n.id) === id; });
        if (!node) return;
        document.dispatchEvent(new CustomEvent('sse-iw-select', { detail: node }));
      });
    });
  }

  function escapeXml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  svg.addEventListener('wheel', function (e) {
    e.preventDefault();
    scale = Math.max(0.4, Math.min(2.5, scale * (e.deltaY < 0 ? 1.1 : 0.9)));
    render();
  }, { passive: false });

  svg.addEventListener('mousedown', function (e) {
    if (e.button !== 0) return;
    drag = { x: e.clientX - panX, y: e.clientY - panY };
  });
  window.addEventListener('mouseup', function () { drag = null; });
  window.addEventListener('mousemove', function (e) {
    if (!drag) return;
    panX = e.clientX - drag.x;
    panY = e.clientY - drag.y;
    render();
  });

  var depthSel = document.querySelector('[data-iw-graph-depth]');
  var statusSel = document.querySelector('[data-iw-graph-status]');
  var resetBtn = document.querySelector('[data-iw-graph-reset]');
  if (statusSel) {
    statusSel.addEventListener('change', function () {
      statusFilter = statusSel.value;
      render();
    });
  }
  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      scale = 1; panX = 0; panY = 0; render();
    });
  }
  if (depthSel) {
    depthSel.addEventListener('change', function () {
      var url = (cfg.graphUrl || '') + '?depth=' + encodeURIComponent(depthSel.value);
      if (cfg.caseId) url += '&case_id=' + encodeURIComponent(cfg.caseId);
      fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
        .then(function (data) {
          rawNodes = data.nodes || [];
          rawEdges = data.edges || [];
          render();
        })
        .catch(function () { /* silence */ });
    });
  }

  window.addEventListener('resize', function () { render(); });
  render();
})();
