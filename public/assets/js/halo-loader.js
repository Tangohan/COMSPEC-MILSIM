/**
 * Animation du loader Halo Reach (progression + disparition).
 */
(function () {
  'use strict';

  var root = document.getElementById('halo-loader');
  if (!root) return;

  var fill = root.querySelector('[data-halo-fill]');
  var pctEl = root.querySelector('[data-halo-pct]');
  var ring = root.querySelector('[data-halo-ring-value]');
  var statusEl = root.querySelector('[data-halo-status]');
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var phrases = [
    'Initialisation',
    'Synchronisation',
    'Chargement des documents',
    'Prêt'
  ];

  var CIRC = 2 * Math.PI * 50;
  var progress = 0;
  var done = false;
  var start = performance.now();
  var minMs = reduced ? 280 : 1100;
  var maxMs = reduced ? 600 : 2200;

  function setProgress(p) {
    progress = Math.max(0, Math.min(100, p));
    if (fill) fill.style.width = progress.toFixed(1) + '%';
    if (pctEl) pctEl.textContent = String(Math.round(progress));
    if (ring) ring.style.strokeDashoffset = String(CIRC * (1 - progress / 100));
    if (statusEl) {
      var idx = progress < 30 ? 0 : progress < 65 ? 1 : progress < 92 ? 2 : 3;
      statusEl.textContent = phrases[idx];
    }
  }

  function finish() {
    if (done) return;
    done = true;
    setProgress(100);
    window.setTimeout(function () {
      root.classList.add('is-done');
      root.setAttribute('aria-busy', 'false');
      window.setTimeout(function () {
        if (root.parentNode) root.parentNode.removeChild(root);
      }, 600);
    }, reduced ? 80 : 220);
  }

  function tick(now) {
    var elapsed = now - start;
    var natural = Math.min(100, (elapsed / maxMs) * 100);
    var eased = natural < 70 ? natural * 0.92 : 70 + (natural - 70) * 1.35;
    var pageReady = document.readyState === 'complete';
    if (pageReady && elapsed >= minMs) {
      setProgress(Math.max(eased, 96));
      finish();
      return;
    }
    setProgress(Math.min(eased, pageReady ? 96 : 88));
    window.requestAnimationFrame(tick);
  }

  if (ring) {
    ring.style.strokeDasharray = String(CIRC);
    ring.style.strokeDashoffset = String(CIRC);
  }

  var ticksHost = root.querySelector('[data-halo-ticks]');
  if (ticksHost) {
    var n = 36;
    for (var i = 0; i < n; i++) {
      var tickEl = document.createElement('span');
      tickEl.className = 'halo-loader__tick';
      var len = i % 6 === 0 ? '12px' : i % 3 === 0 ? '8px' : '5px';
      var opacity = i % 6 === 0 ? '0.85' : '0.4';
      tickEl.style.setProperty('--tick-len', len);
      tickEl.style.setProperty('--tick-opacity', opacity);
      tickEl.style.transform = 'rotate(' + (360 / n) * i + 'deg)';
      ticksHost.appendChild(tickEl);
    }
  }

  setProgress(0);
  window.requestAnimationFrame(tick);

  window.addEventListener('load', function () {
    if (performance.now() - start >= minMs) finish();
  });

  window.setTimeout(finish, maxMs + 400);
})();
