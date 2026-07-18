/**
 * Loader Halo Reach — grille diamant, balayage lumineux circulaire.
 */
(function () {
  'use strict';

  var root = document.getElementById('halo-loader');
  if (!root) return;

  var svg = root.querySelector('[data-halo-grid]');
  var statusEl = root.querySelector('[data-halo-status]');
  var pctEl = root.querySelector('[data-halo-pct]');
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var phrases = ['Initialisation', 'Synchronisation', 'Chargement', 'Prêt'];
  var NS = 'http://www.w3.org/2000/svg';
  var CX = 160;
  var CY = 160;
  var HALF = 8;
  var SPACING = 14;
  var MAX_R = 118;

  /** @type {{el: SVGLineElement, ang: number, dist: number}[]} */
  var segs = [];
  var progress = 0;
  var done = false;
  var start = performance.now();
  var minMs = reduced ? 280 : 1200;
  var maxMs = reduced ? 600 : 2400;
  var phase = 0;

  function toXY(i, j) {
    var s = SPACING * 0.70710678;
    return {
      x: CX + (i - j) * s,
      y: CY + (i + j) * s
    };
  }

  function addSeg(i0, j0, i1, j1) {
    var a = toXY(i0, j0);
    var b = toXY(i1, j1);
    var mx = (a.x + b.x) / 2;
    var my = (a.y + b.y) / 2;
    var dx = mx - CX;
    var dy = my - CY;
    var dist = Math.sqrt(dx * dx + dy * dy);
    if (dist > MAX_R) return;

    // Raccourcir légèrement pour l’effet « capsule » discrète
    var vx = b.x - a.x;
    var vy = b.y - a.y;
    var len = Math.sqrt(vx * vx + vy * vy) || 1;
    var inset = Math.min(3.2, len * 0.18);
    var ux = vx / len;
    var uy = vy / len;
    var x1 = a.x + ux * inset;
    var y1 = a.y + uy * inset;
    var x2 = b.x - ux * inset;
    var y2 = b.y - uy * inset;

    var line = document.createElementNS(NS, 'line');
    line.setAttribute('x1', x1.toFixed(2));
    line.setAttribute('y1', y1.toFixed(2));
    line.setAttribute('x2', x2.toFixed(2));
    line.setAttribute('y2', y2.toFixed(2));
    line.setAttribute('class', 'halo-loader__seg' + (dist > MAX_R * 0.72 ? ' is-dim' : ''));
    svg.appendChild(line);

    segs.push({
      el: line,
      ang: Math.atan2(dy, dx),
      dist: dist
    });
  }

  function buildGrid() {
    var i;
    var j;
    for (i = -HALF; i <= HALF; i++) {
      for (j = -HALF; j <= HALF; j++) {
        if (i < HALF) addSeg(i, j, i + 1, j);
        if (j < HALF) addSeg(i, j, i, j + 1);
      }
    }
  }

  function normAng(a) {
    while (a > Math.PI) a -= Math.PI * 2;
    while (a < -Math.PI) a += Math.PI * 2;
    return a;
  }

  function paint(p) {
    var ring = 38 + (p / 100) * 52;
    var band = 22 + (p / 100) * 10;
    var i;
    var s;
    var dAng;
    var dRing;
    var score;
    var lit;
    var hot;

    for (i = 0; i < segs.length; i++) {
      s = segs[i];
      dAng = Math.abs(normAng(s.ang - phase));
      dRing = Math.abs(s.dist - ring);
      score = 0;
      // Anneau principal tournant
      if (dRing < band) {
        score += (1 - dRing / band) * (1 - Math.min(dAng, 1.15) / 1.15);
      }
      // Noyau
      if (s.dist < 28 + p * 0.12) {
        score += 0.35 * (1 - s.dist / 40);
      }
      // Bras / chevrons périodiques (effet mandala)
      if (dAng < 0.28 && s.dist > 30 && s.dist < ring + 8) {
        score += 0.55;
      }

      lit = score > 0.22;
      hot = score > 0.55;
      s.el.classList.toggle('is-lit', lit);
      s.el.classList.toggle('is-hot', hot);
    }
  }

  function setProgress(p) {
    progress = Math.max(0, Math.min(100, p));
    if (pctEl) pctEl.textContent = String(Math.round(progress));
    if (statusEl) {
      var idx = progress < 28 ? 0 : progress < 62 ? 1 : progress < 90 ? 2 : 3;
      statusEl.textContent = phrases[idx];
    }
    paint(progress);
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
      }, 520);
    }, reduced ? 80 : 240);
  }

  function tick(now) {
    var elapsed = now - start;
    if (!reduced) {
      phase = (elapsed / 900) % (Math.PI * 2);
    }
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

  buildGrid();
  setProgress(0);
  window.requestAnimationFrame(tick);

  window.addEventListener('load', function () {
    if (performance.now() - start >= minMs) finish();
  });

  window.setTimeout(finish, maxMs + 400);
})();
