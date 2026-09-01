(function () {
  function affiliationColor(aff) {
    if (aff === 'hostile') return '#ef4444';
    if (aff === 'neutral') return '#22c55e';
    if (aff === 'unknown') return '#eab308';
    return '#38bdf8';
  }

  function parsePoints(geo) {
    if (Array.isArray(geo.points)) return geo.points;
    if (typeof geo.x === 'number' && typeof geo.y === 'number') return [[geo.x, geo.y]];
    return [[500, 500]];
  }

  function drawObject(svgNs, obj) {
    var g = document.createElementNS(svgNs, 'g');
    g.setAttribute('data-uuid', obj.uuid || '');
    g.setAttribute('data-overlay', String(obj.overlay_id || ''));
    var geo = obj.geometry || {};
    var color = affiliationColor(obj.affiliation);
    var pts = parsePoints(geo);
    var type = geo.type || 'point';
    var el;
    if (type === 'circle') {
      el = document.createElementNS(svgNs, 'circle');
      el.setAttribute('cx', geo.x || pts[0][0]);
      el.setAttribute('cy', geo.y || pts[0][1]);
      el.setAttribute('r', geo.r || 28);
    } else if (type === 'rectangle') {
      el = document.createElementNS(svgNs, 'rect');
      el.setAttribute('x', geo.x || pts[0][0]);
      el.setAttribute('y', geo.y || pts[0][1]);
      el.setAttribute('width', geo.w || 70);
      el.setAttribute('height', geo.h || 40);
    } else if (type === 'polygon' || type === 'polyline' || type === 'line' || type === 'arrow') {
      el = document.createElementNS(svgNs, type === 'polygon' ? 'polygon' : 'polyline');
      el.setAttribute('points', pts.map(function (p) { return p[0] + ',' + p[1]; }).join(' '));
      el.setAttribute('fill', type === 'polygon' ? color + '33' : 'none');
    } else {
      el = document.createElementNS(svgNs, 'circle');
      el.setAttribute('cx', pts[0][0]);
      el.setAttribute('cy', pts[0][1]);
      el.setAttribute('r', 8);
    }
    el.setAttribute('stroke', color);
    el.setAttribute('stroke-width', '2');
    if (!el.getAttribute('fill')) el.setAttribute('fill', color);
    g.appendChild(el);
    var label = document.createElementNS(svgNs, 'text');
    label.setAttribute('x', pts[0][0] + 10);
    label.setAttribute('y', pts[0][1] - 10);
    label.setAttribute('fill', '#e8eef7');
    label.setAttribute('font-size', '12');
    label.textContent = obj.name || '';
    g.appendChild(label);
    return g;
  }

  function renderInto(target, objects, filter) {
    if (!target) return;
    target.innerHTML = '';
    (objects || []).forEach(function (obj) {
      if (filter && !filter(obj)) return;
      target.appendChild(drawObject('http://www.w3.org/2000/svg', obj));
    });
  }

  function planner() {
    var root = document.getElementById('ops-planner');
    if (!root || !window.OPS_PLANNING) return;
    var state = window.OPS_PLANNING;
    var canvas = document.getElementById('ops-canvas');
    var layer = document.getElementById('ops-objects');
    var pendingType = null;
    var selected = null;
    var hidden = {};

    function redraw() {
      renderInto(layer, state.objects, function (obj) {
        return !hidden[String(obj.overlay_id)];
      });
    }
    redraw();

    document.querySelectorAll('.ops-ws__graphic').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('.ops-ws__graphic').forEach(function (b) { b.classList.remove('is-on'); });
        btn.classList.add('is-on');
        pendingType = btn.getAttribute('data-type');
      });
    });

    var search = document.getElementById('ops-graphic-search');
    if (search) {
      search.addEventListener('input', function () {
        var q = search.value.toLowerCase();
        document.querySelectorAll('.ops-ws__graphic').forEach(function (btn) {
          var hay = (btn.getAttribute('data-label') || '').toLowerCase();
          btn.parentElement.style.display = hay.indexOf(q) === -1 && q !== '' ? 'none' : '';
        });
      });
    }

    document.querySelectorAll('.ops-layer-toggle').forEach(function (box) {
      box.addEventListener('change', function () {
        hidden[box.getAttribute('data-overlay')] = !box.checked;
        redraw();
      });
    });

    canvas.addEventListener('click', function (ev) {
      var pt = canvas.createSVGPoint();
      pt.x = ev.clientX;
      pt.y = ev.clientY;
      var ctm = canvas.getScreenCTM();
      if (!ctm) return;
      var loc = pt.matrixTransform(ctm.inverse());
      var hit = ev.target.closest('[data-uuid]');
      if (hit && hit.getAttribute('data-uuid')) {
        selected = hit.getAttribute('data-uuid');
        openProps(selected);
        return;
      }
      if (!pendingType) return;
      var overlay = (state.overlays && state.overlays[0]) ? state.overlays[0].id : 0;
      var body = new URLSearchParams();
      body.set('_csrf_token', root.getAttribute('data-csrf'));
      body.set('_json', '1');
      body.set('graphic_type', pendingType);
      body.set('overlay_id', String(overlay));
      body.set('x', String(Math.round(loc.x)));
      body.set('y', String(Math.round(loc.y)));
      body.set('all_phases', '1');
      fetch(root.getAttribute('data-store'), {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      }).then(function (r) { return r.json(); }).then(function (data) {
        if (!data || !data.ok) return;
        window.location.reload();
      }).catch(function () {});
    });

    function openProps(uuid) {
      var box = document.getElementById('ops-props');
      var form = document.getElementById('ops-props-form');
      if (!box || !form) return;
      var obj = (state.objects || []).find(function (o) { return o.uuid === uuid; });
      if (!obj) return;
      box.hidden = false;
      form.name.value = obj.name || '';
      form.element_code.value = obj.element_code || '';
      form.all_phases.checked = !!Number(obj.all_phases);
      form.onsubmit = function (e) {
        e.preventDefault();
        var body = new URLSearchParams();
        body.set('_csrf_token', root.getAttribute('data-csrf'));
        body.set('_json', '1');
        body.set('name', form.name.value);
        body.set('element_code', form.element_code.value);
        if (form.all_phases.checked) body.set('all_phases', '1');
        fetch(root.getAttribute('data-store').replace(/\/objets$/, '/objets/' + uuid), {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        }).then(function () { window.location.reload(); });
      };
      var del = document.getElementById('ops-props-delete');
      if (del) {
        del.onclick = function () {
          var body = new URLSearchParams();
          body.set('_csrf_token', root.getAttribute('data-csrf'));
          body.set('_json', '1');
          fetch(root.getAttribute('data-store').replace(/\/objets$/, '/objets/' + uuid + '/supprimer'), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
          }).then(function () { window.location.reload(); });
        };
      }
    }
  }

  function tactical() {
    var g = document.getElementById('tac-objects');
    if (!g || !window.OPS_TACTICAL) return;
    renderInto(g, window.OPS_TACTICAL.objects || []);
    document.querySelectorAll('.ops-tac__dock button').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('.ops-tac__dock button').forEach(function (b) { b.classList.remove('is-active'); });
        btn.classList.add('is-active');
        var pane = document.getElementById('tac-pane');
        var id = btn.getAttribute('data-pane');
        if (id === 'map') {
          if (pane) pane.hidden = true;
          return;
        }
        if (pane) pane.hidden = false;
        document.querySelectorAll('[data-pane-body]').forEach(function (el) {
          el.hidden = el.getAttribute('data-pane-body') !== id;
        });
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { planner(); tactical(); });
  } else {
    planner();
    tactical();
  }
})();
