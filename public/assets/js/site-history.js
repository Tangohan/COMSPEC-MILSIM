(function () {
  'use strict';

  function parsePayload() {
    var el = document.getElementById('hist-chart-data');
    if (!el) return null;
    try {
      return JSON.parse(el.textContent || '{}');
    } catch (e) {
      return null;
    }
  }

  function maxOf() {
    var m = 1;
    for (var i = 0; i < arguments.length; i++) {
      var arr = arguments[i] || [];
      for (var j = 0; j < arr.length; j++) {
        var v = Number(arr[j]) || 0;
        if (v > m) m = v;
      }
    }
    return m;
  }

  function drawChart(canvas, data) {
    if (!canvas || !data) return;
    var labels = data.labels || [];
    var ops = data.operators || [];
    var orgs = data.organizations || [];
    var markers = data.markers || [];
    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    var cssW = canvas.clientWidth || 960;
    var cssH = Math.max(220, Math.min(360, Math.round(cssW * 0.38)));
    canvas.width = Math.round(cssW * dpr);
    canvas.height = Math.round(cssH * dpr);
    canvas.style.height = cssH + 'px';
    var ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    var pad = { t: 18, r: 16, b: 36, l: 44 };
    var w = cssW - pad.l - pad.r;
    var h = cssH - pad.t - pad.b;
    var n = Math.max(labels.length, 1);
    var ymax = maxOf(ops, orgs) * 1.12;
    if (ymax < 5) ymax = 5;

    function xAt(i) {
      if (n <= 1) return pad.l + w / 2;
      return pad.l + (i / (n - 1)) * w;
    }
    function yAt(v) {
      return pad.t + h - (Math.max(0, Number(v) || 0) / ymax) * h;
    }

    ctx.clearRect(0, 0, cssW, cssH);

    // Grid
    ctx.strokeStyle = 'rgba(255,255,255,0.06)';
    ctx.lineWidth = 1;
    for (var g = 0; g <= 4; g++) {
      var gy = pad.t + (h * g) / 4;
      ctx.beginPath();
      ctx.moveTo(pad.l, gy);
      ctx.lineTo(pad.l + w, gy);
      ctx.stroke();
      var val = Math.round(ymax * (1 - g / 4));
      ctx.fillStyle = 'rgba(255,255,255,0.35)';
      ctx.font = '600 10px ui-sans-serif, system-ui, sans-serif';
      ctx.textAlign = 'right';
      ctx.fillText(String(val), pad.l - 8, gy + 3);
    }

    function strokeSeries(series, color, fill) {
      if (!series.length) return;
      ctx.beginPath();
      for (var i = 0; i < series.length; i++) {
        var x = xAt(i);
        var y = yAt(series[i]);
        if (i === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      }
      ctx.strokeStyle = color;
      ctx.lineWidth = 2.25;
      ctx.lineJoin = 'round';
      ctx.stroke();
      if (fill && series.length > 1) {
        ctx.lineTo(xAt(series.length - 1), pad.t + h);
        ctx.lineTo(xAt(0), pad.t + h);
        ctx.closePath();
        ctx.fillStyle = fill;
        ctx.fill();
      }
    }

    strokeSeries(orgs, '#94a3b8', 'rgba(148,163,184,0.08)');
    strokeSeries(ops, '#34d399', 'rgba(52,211,153,0.12)');

    // Markers for major versions with known dates
    var labelIndex = {};
    for (var li = 0; li < labels.length; li++) labelIndex[labels[li]] = li;
    markers.forEach(function (m) {
      var idx = labelIndex[m.date];
      if (idx == null) {
        // nearest label
        var best = -1;
        var bestDiff = Infinity;
        for (var i = 0; i < labels.length; i++) {
          var d = Math.abs(Date.parse(labels[i]) - Date.parse(m.date));
          if (!isNaN(d) && d < bestDiff) {
            bestDiff = d;
            best = i;
          }
        }
        idx = best;
      }
      if (idx < 0) return;
      var mx = xAt(idx);
      ctx.strokeStyle = 'rgba(52,211,153,0.55)';
      ctx.setLineDash([4, 4]);
      ctx.beginPath();
      ctx.moveTo(mx, pad.t);
      ctx.lineTo(mx, pad.t + h);
      ctx.stroke();
      ctx.setLineDash([]);
      ctx.fillStyle = '#34d399';
      ctx.beginPath();
      ctx.arc(mx, pad.t + 6, 3.5, 0, Math.PI * 2);
      ctx.fill();
      ctx.fillStyle = 'rgba(236,253,245,0.85)';
      ctx.font = '700 10px ui-sans-serif, system-ui, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(String(m.version || ''), mx, pad.t + 20);
    });

    // X labels (sparse)
    ctx.fillStyle = 'rgba(255,255,255,0.4)';
    ctx.font = '600 10px ui-sans-serif, system-ui, sans-serif';
    ctx.textAlign = 'center';
    var step = Math.max(1, Math.ceil(n / 6));
    for (var xi = 0; xi < n; xi += step) {
      var lab = String(labels[xi] || '');
      if (lab.length >= 10) lab = lab.slice(5); // MM-DD
      ctx.fillText(lab, xAt(xi), pad.t + h + 18);
    }

    // Hover
    var tip = canvas._histTip;
    if (!tip) {
      tip = document.createElement('div');
      tip.className = 'site-history__chart-tip';
      tip.hidden = true;
      tip.style.cssText =
        'position:absolute;z-index:5;pointer-events:none;padding:0.45rem 0.6rem;border-radius:0.5rem;' +
        'background:rgba(5,5,5,0.92);border:1px solid rgba(52,211,153,0.35);color:#ecfdf5;' +
        'font-size:0.72rem;line-height:1.4;transform:translate(-50%,-120%);white-space:nowrap;';
      canvas.parentNode.style.position = 'relative';
      canvas.parentNode.appendChild(tip);
      canvas._histTip = tip;
    }

    canvas.onmousemove = function (ev) {
      if (n < 1) return;
      var rect = canvas.getBoundingClientRect();
      var px = ev.clientX - rect.left;
      var idx = 0;
      if (n > 1) {
        idx = Math.round(((px - pad.l) / w) * (n - 1));
        idx = Math.max(0, Math.min(n - 1, idx));
      }
      var i18n = data.i18n || {};
      tip.hidden = false;
      tip.style.left = xAt(idx) + 'px';
      tip.style.top = yAt(ops[idx] || 0) + 'px';
      tip.innerHTML =
        '<strong>' +
        String(labels[idx] || '') +
        '</strong><br>' +
        (i18n.operators || 'Ops') +
        ' : ' +
        String(ops[idx] || 0) +
        '<br>' +
        (i18n.orgs || 'Orgs') +
        ' : ' +
        String(orgs[idx] || 0);
    };
    canvas.onmouseleave = function () {
      tip.hidden = true;
    };
  }

  function bindToggles() {
    document.querySelectorAll('[data-hist-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var node = btn.closest('.site-history__node');
        if (!node) return;
        var panel = node.querySelector('.site-history__node-panel');
        var open = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', open ? 'false' : 'true');
        if (panel) panel.hidden = open;
        node.classList.toggle('is-open', !open);
      });
    });
  }

  function init() {
    bindToggles();
    var canvas = document.getElementById('hist-chart');
    var data = parsePayload();
    if (!canvas || !data) return;
    var redraw = function () {
      drawChart(canvas, data);
    };
    redraw();
    window.addEventListener('resize', redraw);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
