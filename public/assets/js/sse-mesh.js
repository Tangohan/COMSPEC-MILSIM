/**
 * Éditeur de toile de données SSE — canevas SVG + force layout léger.
 * Aucune dépendance externe.
 */
(function () {
  'use strict';
  var cfg = window.SSE_MESH;
  if (!cfg || !document.getElementById('sse-mesh-canvas')) return;

  var svg = document.getElementById('sse-mesh-canvas');
  var wrap = svg.parentElement;
  var nodes = (cfg.nodes || []).map(function (n) {
    return {
      id: +n.id,
      kind: n.kind || 'custom',
      kind_label: n.kind_label || (cfg.kindLabels && cfg.kindLabels[n.kind]) || n.kind,
      label: n.label || '',
      detail: n.detail || '',
      image_url: n.image_url || '',
      meta_lines: Array.isArray(n.meta_lines) ? n.meta_lines : [],
      x: +n.pos_x || 200,
      y: +n.pos_y || 200,
      vx: 0,
      vy: 0
    };
  });
  var edges = (cfg.edges || []).map(function (e) {
    return {
      id: +e.id,
      from: +e.from_node_id,
      to: +e.to_node_id,
      relation: e.relation_label || e.relation || '',
      reliability: e.reliability || 'unverified'
    };
  });
  var byId = {};
  nodes.forEach(function (n) { byId[n.id] = n; });
  var filterState = { depth: 2, reliability: 'any', kind: 'any', anomalies: false };

  var W = 900;
  var H = 560;
  var scale = 1;
  var panX = 0;
  var panY = 0;
  var selected = null;
  var drag = null;
  var simFrames = 0;

  function size() {
    var r = wrap.getBoundingClientRect();
    W = Math.max(640, Math.floor(r.width));
    H = Math.max(420, Math.floor(r.height || 520));
    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    svg.setAttribute('width', String(W));
    svg.setAttribute('height', String(H));
  }

  function kindColor(kind) {
    switch (kind) {
      case 'person': return '#3ddc9a';
      case 'site': return '#6bb2f0';
      case 'event': return '#e0a233';
      case 'document': return '#a78bfa';
      case 'vehicle': return '#38bdf8';
      case 'weapon': return '#ff6b5e';
      case 'phone': return '#f472b6';
      case 'organization': return '#94a3b8';
      case 'seizure': return '#fbbf24';
      default: return '#7dd3fc';
    }
  }

  function tick(steps) {
    steps = steps || 1;
    var i, j, a, b, dx, dy, dist, f;
    for (var s = 0; s < steps; s++) {
      for (i = 0; i < nodes.length; i++) {
        a = nodes[i];
        a.vx *= 0.85;
        a.vy *= 0.85;
        // Attraction douce vers le centre
        a.vx += (W / 2 - a.x) * 0.002;
        a.vy += (H / 2 - a.y) * 0.002;
      }
      for (i = 0; i < nodes.length; i++) {
        for (j = i + 1; j < nodes.length; j++) {
          a = nodes[i];
          b = nodes[j];
          dx = a.x - b.x;
          dy = a.y - b.y;
          dist = Math.sqrt(dx * dx + dy * dy) || 1;
          f = 1200 / (dist * dist);
          a.vx += (dx / dist) * f;
          a.vy += (dy / dist) * f;
          b.vx -= (dx / dist) * f;
          b.vy -= (dy / dist) * f;
        }
      }
      edges.forEach(function (e) {
        a = byId[e.from];
        b = byId[e.to];
        if (!a || !b) return;
        dx = b.x - a.x;
        dy = b.y - a.y;
        dist = Math.sqrt(dx * dx + dy * dy) || 1;
        f = (dist - 140) * 0.02;
        a.vx += (dx / dist) * f;
        a.vy += (dy / dist) * f;
        b.vx -= (dx / dist) * f;
        b.vy -= (dy / dist) * f;
      });
      nodes.forEach(function (n) {
        if (drag && drag.id === n.id) return;
        n.x += n.vx;
        n.y += n.vy;
        n.x = Math.max(40, Math.min(W - 40, n.x));
        n.y = Math.max(40, Math.min(H - 40, n.y));
      });
    }
  }

  function relRank(r) {
    if (r === 'confirmed') return 3;
    if (r === 'corroborated') return 2;
    if (r === 'conflicting') return 0;
    return 1;
  }

  function minRelRank() {
    if (filterState.reliability === 'confirmed') return 3;
    if (filterState.reliability === 'corroborated') return 2;
    return 0;
  }

  function visibleSets() {
    var minR = minRelRank();
    var focus = selected;
    var allowedNodes = {};
    var depth = Math.max(1, +filterState.depth || 2);

    if (focus) {
      allowedNodes[focus] = 0;
      var frontier = [focus];
      var d;
      for (d = 0; d < depth; d++) {
        var next = [];
        frontier.forEach(function (nid) {
          edges.forEach(function (e) {
            if (relRank(e.reliability) < minR) return;
            if (filterState.anomalies && e.reliability !== 'conflicting') return;
            var other = null;
            if (e.from === nid) other = e.to;
            else if (e.to === nid) other = e.from;
            if (other && allowedNodes[other] === undefined) {
              allowedNodes[other] = d + 1;
              next.push(other);
            }
          });
        });
        frontier = next;
      }
    } else {
      nodes.forEach(function (n) { allowedNodes[n.id] = 0; });
    }

    var visEdges = edges.filter(function (e) {
      if (relRank(e.reliability) < minR) return false;
      if (filterState.anomalies && e.reliability !== 'conflicting') return false;
      if (allowedNodes[e.from] === undefined || allowedNodes[e.to] === undefined) return false;
      return true;
    });
    var visNodes = nodes.filter(function (n) {
      if (allowedNodes[n.id] === undefined) return false;
      if (filterState.kind !== 'any' && n.kind !== filterState.kind && (!focus || n.id !== focus)) return false;
      return true;
    });
    if (focus && byId[focus] && !visNodes.some(function (n) { return n.id === focus; })) {
      visNodes.push(byId[focus]);
    }
    var nodeIds = {};
    visNodes.forEach(function (n) { nodeIds[n.id] = true; });
    visEdges = visEdges.filter(function (e) { return nodeIds[e.from] && nodeIds[e.to]; });
    return { nodes: visNodes, edges: visEdges };
  }

  function render() {
    var ns = 'http://www.w3.org/2000/svg';
    while (svg.firstChild) svg.removeChild(svg.firstChild);

    var gRoot = document.createElementNS(ns, 'g');
    gRoot.setAttribute('transform', 'translate(' + panX + ',' + panY + ') scale(' + scale + ')');
    svg.appendChild(gRoot);
    var vis = visibleSets();

    // Liens
    vis.edges.forEach(function (e) {
      var a = byId[e.from];
      var b = byId[e.to];
      if (!a || !b) return;
      var line = document.createElementNS(ns, 'line');
      line.setAttribute('x1', a.x);
      line.setAttribute('y1', a.y);
      line.setAttribute('x2', b.x);
      line.setAttribute('y2', b.y);
      line.setAttribute('class', 'sse-mesh-link' + (e.reliability === 'conflicting' ? ' is-anomaly' : ''));
      gRoot.appendChild(line);
      var mx = (a.x + b.x) / 2;
      var my = (a.y + b.y) / 2;
      var t = document.createElementNS(ns, 'text');
      t.setAttribute('x', mx);
      t.setAttribute('y', my - 4);
      t.setAttribute('class', 'sse-mesh-link-label');
      t.textContent = e.relation;
      gRoot.appendChild(t);
    });

    vis.nodes.forEach(function (n) {
      var g = document.createElementNS(ns, 'g');
      g.setAttribute('class', 'sse-mesh-node' + (selected === n.id ? ' is-selected' : ''));
      g.setAttribute('transform', 'translate(' + n.x + ',' + n.y + ')');
      g.setAttribute('data-id', String(n.id));
      g.style.cursor = 'grab';

      var circ = document.createElementNS(ns, 'circle');
      circ.setAttribute('r', '22');
      circ.setAttribute('fill', '#0b0f18');
      circ.setAttribute('stroke', kindColor(n.kind));
      circ.setAttribute('stroke-width', selected === n.id ? '3' : '2');
      g.appendChild(circ);

      var glyph = document.createElementNS(ns, 'text');
      glyph.setAttribute('text-anchor', 'middle');
      glyph.setAttribute('dominant-baseline', 'central');
      glyph.setAttribute('class', 'sse-mesh-glyph');
      glyph.setAttribute('fill', kindColor(n.kind));
      glyph.textContent = (n.kind_label || n.kind || '?').charAt(0).toUpperCase();
      g.appendChild(glyph);

      var lab = document.createElementNS(ns, 'text');
      lab.setAttribute('y', '38');
      lab.setAttribute('text-anchor', 'middle');
      lab.setAttribute('class', 'sse-mesh-node-label');
      lab.textContent = (n.label || '').slice(0, 28);
      g.appendChild(lab);

      gRoot.appendChild(g);
    });
  }

  function selectNode(id) {
    selected = id;
    var empty = document.getElementById('sse-mesh-sel-empty');
    var body = document.getElementById('sse-mesh-sel-body');
    var n = byId[id];
    if (!n) {
      if (empty) empty.hidden = false;
      if (body) body.hidden = true;
      render();
      return;
    }
    if (empty) empty.hidden = true;
    if (body) body.hidden = false;
    var k = document.getElementById('sse-mesh-sel-kind');
    var l = document.getElementById('sse-mesh-sel-label');
    var d = document.getElementById('sse-mesh-sel-detail');
    var links = document.getElementById('sse-mesh-sel-links');
    var edgeList = document.getElementById('sse-mesh-sel-edge-list');
    if (k) k.textContent = n.kind_label || n.kind;
    if (l) l.textContent = n.label;
    if (d) {
      var lines = (n.meta_lines && n.meta_lines.length) ? n.meta_lines.join('\n') : '';
      d.textContent = lines || n.detail || 'Aucune précision renseignée.';
      d.style.whiteSpace = lines ? 'pre-line' : '';
    }
    var imgWrap = document.getElementById('sse-mesh-sel-image');
    var imgEl = document.getElementById('sse-mesh-sel-image-img');
    if (imgWrap && imgEl) {
      if (n.image_url) {
        imgEl.src = n.image_url;
        imgWrap.hidden = false;
      } else {
        imgEl.removeAttribute('src');
        imgWrap.hidden = true;
      }
    }
    var related = edges.filter(function (e) { return e.from === id || e.to === id; });
    if (links) {
      links.textContent = related.length
        ? related.length + ' lien' + (related.length > 1 ? 's' : '') + ' sur la toile'
        : 'Aucun lien pour cette entité';
    }
    if (edgeList) {
      edgeList.innerHTML = '';
      related.slice(0, 8).forEach(function (e) {
        var otherId = e.from === id ? e.to : e.from;
        var other = byId[otherId];
        var li = document.createElement('li');
        var dir = e.from === id ? '→' : '←';
        li.textContent = dir + ' ' + (e.relation || 'lié') + ' ' + ((other && other.label) || ('#' + otherId));
        edgeList.appendChild(li);
      });
    }
    var from = document.getElementById('from_node_id');
    if (from) from.value = String(id);
    render();
  }

  function clientToWorld(ev) {
    var rect = svg.getBoundingClientRect();
    var x = (ev.clientX - rect.left - panX) / scale;
    var y = (ev.clientY - rect.top - panY) / scale;
    return { x: x, y: y };
  }

  function nearest(pt) {
    var best = null;
    var bestD = 28;
    nodes.forEach(function (n) {
      var dx = n.x - pt.x;
      var dy = n.y - pt.y;
      var d = Math.sqrt(dx * dx + dy * dy);
      if (d < bestD) {
        bestD = d;
        best = n;
      }
    });
    return best;
  }

  svg.addEventListener('mousedown', function (ev) {
    if (ev.button === 2) {
      return;
    }
    var pt = clientToWorld(ev);
    var n = nearest(pt);
    if (n) {
      drag = n;
      selectNode(n.id);
      ev.preventDefault();
    }
  });

  svg.addEventListener('contextmenu', function (ev) {
    ev.preventDefault();
    var pt = clientToWorld(ev);
    var n = nearest(pt);
    var actions = [];
    var title = '';

    if (n) {
      selectNode(n.id);
      title = n.label || (n.kind_label || 'Entité');
      actions.push({
        label: 'Afficher la fiche',
        run: function () {
          selectNode(n.id);
          var explore = document.querySelector('[data-mesh-tab="explore"]');
          if (explore) explore.click();
        }
      });
      if (cfg.canManage) {
        actions.push({
          label: 'Créer un lien depuis cette entité',
          run: function () {
            selectNode(n.id);
            var build = document.querySelector('[data-mesh-tab="build"]');
            if (build) build.click();
            var from = document.getElementById('from_node_id');
            if (from) from.value = String(n.id);
          }
        });
      }
      actions.push({ separator: true });
      actions.push({ label: 'Copier le libellé', copy: n.label || '' });
      if (cfg.canManage && cfg.deleteNodeUrlTpl) {
        actions.push({ separator: true });
        actions.push({
          label: 'Retirer de l’investigation',
          danger: true,
          post: String(cfg.deleteNodeUrlTpl).replace('__ID__', String(n.id)),
          csrf: cfg.csrf,
          confirm: 'Retirer « ' + (n.label || 'cette entité') + ' » de l’investigation ?'
        });
      }
    } else {
      title = 'Canevas';
      actions.push({
        label: 'Réorganiser',
        run: function () {
          simFrames = 80;
        }
      });
      actions.push({
        label: 'Recadrer la vue',
        run: function () {
          var fit = document.getElementById('sse-mesh-fit');
          if (fit) fit.click();
        }
      });
      if (cfg.canManage) {
        actions.push({ separator: true });
        actions.push({
          label: 'Ajouter une entité ici',
          run: function () {
            var nx = document.getElementById('sse-mesh-new-x');
            var ny = document.getElementById('sse-mesh-new-y');
            if (nx) nx.value = String(Math.round(pt.x));
            if (ny) ny.value = String(Math.round(pt.y));
            var build = document.querySelector('[data-mesh-tab="build"]');
            if (build) build.click();
            var label = document.getElementById('label');
            if (label) label.focus();
          }
        });
        if (document.getElementById('sse-mesh-save-layout')) {
          actions.push({
            label: 'Enregistrer la disposition',
            run: function () {
              var save = document.getElementById('sse-mesh-save-layout');
              if (save) save.click();
            }
          });
        }
      }
    }

    if (window.SseContextMenu && typeof window.SseContextMenu.open === 'function') {
      window.SseContextMenu.open(ev.clientX, ev.clientY, actions, title);
    }
  });
  window.addEventListener('mousemove', function (ev) {
    if (!drag) return;
    var pt = clientToWorld(ev);
    drag.x = pt.x;
    drag.y = pt.y;
    drag.vx = 0;
    drag.vy = 0;
    render();
  });
  window.addEventListener('mouseup', function () {
    if (drag) {
      var nx = document.getElementById('sse-mesh-new-x');
      var ny = document.getElementById('sse-mesh-new-y');
      if (nx) nx.value = String(Math.round(drag.x));
      if (ny) ny.value = String(Math.round(drag.y));
    }
    drag = null;
  });

  svg.addEventListener('wheel', function (ev) {
    ev.preventDefault();
    var delta = ev.deltaY > 0 ? 0.92 : 1.08;
    scale = Math.max(0.45, Math.min(2.4, scale * delta));
    render();
  }, { passive: false });

  var relayoutBtn = document.getElementById('sse-mesh-relayout');
  if (relayoutBtn) {
    relayoutBtn.addEventListener('click', function () {
      simFrames = 80;
    });
  }

  var saveBtn = document.getElementById('sse-mesh-save-layout');
  if (saveBtn && cfg.canManage) {
    saveBtn.addEventListener('click', function () {
      saveBtn.disabled = true;
      saveBtn.textContent = 'Enregistrement…';
      var positions = nodes.map(function (n) {
        return { id: n.id, x: Math.round(n.x), y: Math.round(n.y) };
      });
      var body = new FormData();
      body.append('_csrf_token', cfg.csrf);
      body.append('positions_json', JSON.stringify(positions));
      fetch(cfg.layoutUrl, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          saveBtn.disabled = false;
          saveBtn.textContent = data && data.ok
            ? 'Disposition enregistrée'
            : 'Échec — réessayer';
          setTimeout(function () { saveBtn.textContent = 'Enregistrer la disposition'; }, 1800);
        })
        .catch(function () {
          saveBtn.disabled = false;
          saveBtn.textContent = 'Enregistrer la disposition';
        });
    });
  }

  function loop() {
    if (simFrames > 0) {
      tick(2);
      simFrames -= 1;
      render();
    }
    requestAnimationFrame(loop);
  }

  size();
  window.addEventListener('resize', function () {
    size();
    render();
  });
  // Première organisation si positions toutes proches de 0
  var clustered = nodes.every(function (n) {
    return Math.abs(n.x) < 5 && Math.abs(n.y) < 5;
  });
  if (clustered || nodes.length > 0) {
    // Si beaucoup de nœuds partagent le même coin, on relance une courte sim.
    var sameSpot = 0;
    nodes.forEach(function (n) {
      if (Math.abs(n.x - nodes[0].x) < 8 && Math.abs(n.y - nodes[0].y) < 8) sameSpot++;
    });
    if (sameSpot > 1 || clustered) simFrames = 60;
  }
  render();
  loop();

  document.querySelectorAll('[data-mesh-filter]').forEach(function (el) {
    el.addEventListener('change', function () {
      var key = el.getAttribute('data-mesh-filter');
      if (key === 'depth') filterState.depth = +el.value || 2;
      if (key === 'reliability') filterState.reliability = el.value || 'any';
      if (key === 'kind') filterState.kind = el.value || 'any';
      if (key === 'anomalies') filterState.anomalies = !!el.checked;
      render();
    });
  });

  // Onglets du panneau latéral
  document.querySelectorAll('[data-mesh-tab]').forEach(function (tab) {
    tab.addEventListener('click', function () {
      var name = tab.getAttribute('data-mesh-tab');
      document.querySelectorAll('[data-mesh-tab]').forEach(function (t) {
        var on = t === tab;
        t.classList.toggle('is-active', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      document.querySelectorAll('[data-mesh-panel]').forEach(function (panel) {
        var on = panel.getAttribute('data-mesh-panel') === name;
        panel.classList.toggle('is-active', on);
        panel.hidden = !on;
      });
    });
  });

  // Recadrage simple : recentre et remet le zoom
  var fitBtn = document.getElementById('sse-mesh-fit');
  if (fitBtn) {
    fitBtn.addEventListener('click', function () {
      scale = 1;
      panX = 0;
      panY = 0;
      if (nodes.length === 0) {
        render();
        return;
      }
      var minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
      nodes.forEach(function (n) {
        minX = Math.min(minX, n.x);
        maxX = Math.max(maxX, n.x);
        minY = Math.min(minY, n.y);
        maxY = Math.max(maxY, n.y);
      });
      var cx = (minX + maxX) / 2;
      var cy = (minY + maxY) / 2;
      nodes.forEach(function (n) {
        n.x += (W / 2 - cx);
        n.y += (H / 2 - cy);
        n.vx = 0;
        n.vy = 0;
      });
      render();
    });
  }
})();
