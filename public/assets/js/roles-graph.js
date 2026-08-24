/**
 * Carte des rôles — organigramme SVG (forêts de commandement + liaisons).
 */
(function () {
  'use strict';

  var NODE_W = 200;
  var NODE_H = 62;
  var RANK_GAP = 88;
  var COL_GAP = 28;
  var TREE_GAP = 56;
  var PAD = 28;

  var DEFAULT_PALETTE = {
    reports_to: '#334155',
    cross_cutting: '#7c3aed',
    mentored_by: '#0369a1',
    independent: '#94a3b8'
  };

  function qs(id) {
    return document.getElementById(id);
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function wrapLines(text, maxChars) {
    var words = String(text || '').trim().split(/\s+/);
    var lines = [];
    var cur = '';
    words.forEach(function (w) {
      var next = cur ? cur + ' ' + w : w;
      if (next.length > maxChars && cur) {
        lines.push(cur);
        cur = w;
      } else {
        cur = next;
      }
    });
    if (cur) lines.push(cur);
    if (lines.length === 0) lines.push('Rôle');
    return lines.slice(0, 2);
  }

  function dashFor(type) {
    if (type === 'mentored_by') return '7 5';
    if (type === 'cross_cutting') return '2 6';
    if (type === 'independent') return '8 6';
    return '';
  }

  function widthFor(type) {
    if (type === 'reports_to') return 2.6;
    if (type === 'independent') return 1.4;
    return 1.9;
  }

  function edgeWeight(type) {
    if (type === 'reports_to') return 4;
    if (type === 'mentored_by') return 3;
    if (type === 'cross_cutting') return 2;
    return 1;
  }

  function buildForest(nodes, edges) {
    var byId = {};
    nodes.forEach(function (n) { byId[n.id] = n; });

    var parent = {};
    edges.forEach(function (e) {
      if (e.type === 'reports_to' && e.from && e.to && e.from !== e.to && byId[e.from] && byId[e.to]) {
        parent[e.from] = e.to;
      }
    });
    edges.forEach(function (e) {
      if (e.type === 'mentored_by' && e.from && e.to && e.from !== e.to && byId[e.from] && byId[e.to] && !parent[e.from]) {
        parent[e.from] = e.to;
      }
    });

    var children = {};
    nodes.forEach(function (n) { children[n.id] = []; });
    nodes.forEach(function (n) {
      var p = parent[n.id];
      if (p && children[p]) children[p].push(n.id);
    });
    Object.keys(children).forEach(function (id) {
      children[id].sort(function (a, b) {
        return String((byId[a] && byId[a].label) || '').localeCompare(String((byId[b] && byId[b].label) || ''), 'fr');
      });
    });

    var roots = nodes.filter(function (n) { return !parent[n.id]; });
    if (!roots.length) roots = nodes.slice();
    roots.sort(function (a, b) {
      return String(a.label || '').localeCompare(String(b.label || ''), 'fr');
    });

    return { byId: byId, parent: parent, children: children, roots: roots };
  }

  function layout(nodes, edges, containerW) {
    var forest = buildForest(nodes, edges);
    var children = forest.children;
    var roots = forest.roots;
    var parent = forest.parent;
    var leafCount = {};

    function countLeaves(id, stack) {
      if (leafCount[id] != null) return leafCount[id];
      stack = stack || {};
      if (stack[id]) {
        leafCount[id] = 1;
        return 1;
      }
      stack[id] = true;
      var ch = children[id] || [];
      var sum = 0;
      ch.forEach(function (c) {
        if (!stack[c]) sum += countLeaves(c, stack);
      });
      stack[id] = false;
      leafCount[id] = Math.max(sum, 1);
      return leafCount[id];
    }
    roots.forEach(function (r) { countLeaves(r.id); });

    var pos = {};
    var maxRank = 0;

    function place(id, rank, xLeft, stack) {
      if (pos[id]) return;
      stack = stack || {};
      if (stack[id]) return;
      stack[id] = true;
      maxRank = Math.max(maxRank, rank);
      var ch = (children[id] || []).filter(function (c) { return !stack[c] && !pos[c]; });
      var cx;
      if (!ch.length) {
        cx = xLeft + NODE_W / 2;
      } else {
        var cursor = xLeft;
        ch.forEach(function (c) {
          var cw = countLeaves(c) * (NODE_W + COL_GAP) - COL_GAP;
          place(c, rank + 1, cursor, Object.assign({}, stack));
          cursor += Math.max(cw, NODE_W) + COL_GAP;
        });
        var first = pos[ch[0]];
        var last = pos[ch[ch.length - 1]] || first;
        cx = first && last ? (first.cx + last.cx) / 2 : xLeft + NODE_W / 2;
      }
      pos[id] = {
        x: cx - NODE_W / 2,
        y: PAD + rank * (NODE_H + RANK_GAP),
        cx: cx,
        cy: 0,
        rank: rank,
        root: parent[id] ? '0' : '1'
      };
      pos[id].cy = pos[id].y + NODE_H / 2;
    }

    var cursor = PAD;
    roots.forEach(function (r, i) {
      var w = countLeaves(r.id) * (NODE_W + COL_GAP) - COL_GAP;
      place(r.id, 0, cursor);
      cursor += Math.max(w, NODE_W) + (i < roots.length - 1 ? TREE_GAP : 0);
    });

    nodes.forEach(function (n) {
      if (!pos[n.id]) {
        place(n.id, 0, cursor);
        cursor += NODE_W + COL_GAP;
      }
    });

    var minX = Infinity;
    var maxX = 0;
    var maxY = 0;
    Object.keys(pos).forEach(function (id) {
      minX = Math.min(minX, pos[id].x);
      maxX = Math.max(maxX, pos[id].x + NODE_W);
      maxY = Math.max(maxY, pos[id].y + NODE_H);
    });
    var shift = PAD - (isFinite(minX) ? minX : 0);
    Object.keys(pos).forEach(function (id) {
      pos[id].x += shift;
      pos[id].cx += shift;
    });
    maxX += shift;

    var width = Math.max(maxX + PAD, containerW || 480, 420);
    var extra = Math.max(0, (width - (maxX + PAD)) / 2);
    if (extra > 0) {
      Object.keys(pos).forEach(function (id) {
        pos[id].x += extra;
        pos[id].cx += extra;
      });
    }

    return {
      pos: pos,
      width: width,
      height: Math.max(maxY + PAD, 220),
      maxRank: maxRank,
      parent: parent
    };
  }

  function elbow(from, to, kind) {
    var x1, y1, x2, y2;
    if (kind === 'side') {
      if (from.cx <= to.cx) {
        x1 = from.x + NODE_W;
        x2 = to.x;
      } else {
        x1 = from.x;
        x2 = to.x + NODE_W;
      }
      y1 = from.cy;
      y2 = to.cy;
      var mx = (x1 + x2) / 2;
      return 'M ' + x1 + ' ' + y1 + ' C ' + mx + ' ' + y1 + ', ' + mx + ' ' + y2 + ', ' + x2 + ' ' + y2;
    }
    x1 = from.cx;
    y1 = from.y;
    x2 = to.cx;
    y2 = to.y + NODE_H;
    if (to.y > from.y) {
      y1 = from.y + NODE_H;
      y2 = to.y;
    }
    var mid = (y1 + y2) / 2;
    return 'M ' + x1 + ' ' + y1 + ' L ' + x1 + ' ' + mid + ' L ' + x2 + ' ' + mid + ' L ' + x2 + ' ' + y2;
  }

  function rankBands(pos, width) {
    var ys = {};
    Object.keys(pos).forEach(function (id) {
      ys[pos[id].y] = pos[id].rank || 0;
    });
    return Object.keys(ys).map(Number).sort(function (a, b) { return a - b; }).map(function (y, i) {
      var h = NODE_H + 24;
      var fill = i % 2 === 0 ? 'rgba(11,138,92,0.045)' : 'rgba(15,23,42,0.02)';
      return '<rect class="bo-rf__graph-band" x="0" y="' + (y - 12) + '" width="' + width + '" height="' + h + '" fill="' + fill + '" rx="10"></rect>';
    }).join('');
  }

  function render(host, data, palette) {
    var stage = host.querySelector('.bo-rf__graph-stage');
    if (!stage) return;
    var nodes = Array.isArray(data.nodes) ? data.nodes : [];
    var edges = Array.isArray(data.edges) ? data.edges.slice() : [];
    if (!nodes.length) {
      stage.innerHTML = '<p class="bo-rf__graph-empty">Ajoutez des relations entre rôles pour afficher la carte.</p>';
      return;
    }
    edges.sort(function (a, b) {
      return edgeWeight(a.type) - edgeWeight(b.type);
    });
    var colors = Object.assign({}, DEFAULT_PALETTE, palette || {});
    var laid = layout(nodes, edges, Math.max(stage.clientWidth || 0, 480));
    var pos = laid.pos;
    var markerIds = {};
    var defs = '';
    Object.keys(colors).forEach(function (type) {
      var id = 'rf-arrow-' + type.replace(/[^a-z0-9_-]/gi, '');
      markerIds[type] = id;
      defs += '<marker id="' + id + '" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto">' +
        '<path d="M 0 1.2 L 10 5 L 0 8.8 Z" fill="' + esc(colors[type]) + '"></path></marker>';
    });
    defs += '<marker id="rf-arrow-default" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto">' +
      '<path d="M 0 1.2 L 10 5 L 0 8.8 Z" fill="#64748b"></path></marker>';

    var edgeSvg = edges.map(function (e) {
      var a = pos[e.from];
      var b = pos[e.to];
      if (!a || !b) return '';
      var color = colors[e.type] || '#64748b';
      var sameRank = Math.abs(a.y - b.y) < 8;
      var treeLink = laid.parent[e.from] === e.to;
      var d = elbow(a, b, (sameRank || !treeLink) ? 'side' : 'vert');
      var marker = markerIds[e.type] || 'rf-arrow-default';
      var title = esc(e.type_label || '');
      return '<g class="bo-rf__graph-edge" data-from="' + esc(e.from) + '" data-to="' + esc(e.to) + '" data-type="' + esc(e.type || '') + '">' +
        '<path class="bo-rf__graph-edge-hit" d="' + d + '" fill="none" stroke="transparent" stroke-width="12"></path>' +
        '<path d="' + d + '" fill="none" stroke="' + esc(color) + '" stroke-width="' + widthFor(e.type) + '"' +
        (dashFor(e.type) ? ' stroke-dasharray="' + dashFor(e.type) + '"' : '') +
        ' stroke-linecap="round" stroke-linejoin="round" marker-end="url(#' + marker + ')"></path>' +
        (title ? '<title>' + title + '</title>' : '') +
        '</g>';
    }).join('');

    var nodeSvg = nodes.map(function (n) {
      var p = pos[n.id];
      if (!p) return '';
      var lines = wrapLines(n.label || n.slug || 'Rôle', 20);
      var ty = lines.length === 1 ? p.cy + 5 : p.y + 27;
      var text = lines.map(function (line, i) {
        return '<tspan x="' + p.cx + '" dy="' + (i === 0 ? 0 : 15) + '">' + esc(line) + '</tspan>';
      }).join('');
      return '<g class="bo-rf__graph-node" data-id="' + esc(n.id) + '" data-root="' + p.root + '">' +
        '<rect x="' + p.x + '" y="' + p.y + '" width="' + NODE_W + '" height="' + NODE_H + '" rx="10"></rect>' +
        '<rect class="bo-rf__graph-node-bar" x="' + p.x + '" y="' + p.y + '" width="5" height="' + NODE_H + '" rx="2.5"></rect>' +
        '<text x="' + p.cx + '" y="' + ty + '" text-anchor="middle">' + text + '</text>' +
        '<title>' + esc(n.label || n.slug || 'Rôle') + '</title>' +
        '</g>';
    }).join('');

    stage.innerHTML = '<svg class="bo-rf__graph-svg" viewBox="0 0 ' + laid.width + ' ' + laid.height +
      '" width="100%" role="img" aria-label="Carte des rôles" preserveAspectRatio="xMidYMin meet">' +
      '<defs>' + defs + '</defs>' +
      '<rect class="bo-rf__graph-bg" x="0" y="0" width="' + laid.width + '" height="' + laid.height + '"></rect>' +
      rankBands(pos, laid.width) +
      edgeSvg + nodeSvg +
      '</svg>';

    bindHover(stage);
  }

  function bindHover(stage) {
    var svg = stage.querySelector('svg');
    if (!svg) return;

    function clear() {
      svg.querySelectorAll('.is-dim, .is-on').forEach(function (el) {
        el.classList.remove('is-dim', 'is-on');
      });
    }

    function highlightIds(ids) {
      svg.querySelectorAll('.bo-rf__graph-node').forEach(function (el) {
        var on = ids[el.getAttribute('data-id')];
        el.classList.toggle('is-on', !!on);
        el.classList.toggle('is-dim', !on);
      });
      svg.querySelectorAll('.bo-rf__graph-edge').forEach(function (el) {
        var on = ids[el.getAttribute('data-from')] && ids[el.getAttribute('data-to')];
        if (!on) {
          on = ids[el.getAttribute('data-from')] || ids[el.getAttribute('data-to')];
          if (Object.keys(ids).length === 2) on = ids[el.getAttribute('data-from')] && ids[el.getAttribute('data-to')];
        }
        el.classList.toggle('is-on', !!on);
        el.classList.toggle('is-dim', !on);
      });
    }

    svg.addEventListener('mouseover', function (ev) {
      var node = ev.target.closest('.bo-rf__graph-node');
      var edge = ev.target.closest('.bo-rf__graph-edge');
      if (node) {
        var id = node.getAttribute('data-id');
        var related = {};
        related[id] = true;
        svg.querySelectorAll('.bo-rf__graph-edge').forEach(function (el) {
          if (el.getAttribute('data-from') === id || el.getAttribute('data-to') === id) {
            related[el.getAttribute('data-from')] = true;
            related[el.getAttribute('data-to')] = true;
            el.classList.add('is-on');
            el.classList.remove('is-dim');
          } else {
            el.classList.add('is-dim');
            el.classList.remove('is-on');
          }
        });
        svg.querySelectorAll('.bo-rf__graph-node').forEach(function (el) {
          var on = !!related[el.getAttribute('data-id')];
          el.classList.toggle('is-on', on);
          el.classList.toggle('is-dim', !on);
        });
        return;
      }
      if (edge) {
        var ids = {};
        ids[edge.getAttribute('data-from')] = true;
        ids[edge.getAttribute('data-to')] = true;
        highlightIds(ids);
        edge.classList.add('is-on');
        edge.classList.remove('is-dim');
        return;
      }
    });
    svg.addEventListener('mouseleave', clear);
  }

  function boot() {
    var host = qs('roles-graph-host');
    if (!host) return;
    var url = host.getAttribute('data-graph-url');
    if (!url) return;
    var palette = {};
    try {
      palette = JSON.parse(host.getAttribute('data-edge-palette') || '{}') || {};
    } catch (e) {}
    var lastData = null;
    var timer = null;

    function paint(data) {
      lastData = data || {};
      render(host, lastData, palette);
    }

    fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(paint).catch(function () {
      var stage = host.querySelector('.bo-rf__graph-stage');
      if (stage) stage.innerHTML = '<p class="bo-rf__graph-empty">Impossible d’afficher la carte pour le moment.</p>';
    });

    window.addEventListener('resize', function () {
      if (!lastData || !lastData.nodes) return;
      clearTimeout(timer);
      timer = setTimeout(function () { render(host, lastData, palette); }, 140);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
