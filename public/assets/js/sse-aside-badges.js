/* Bureau SSE : actualisation douce des pastilles de la barre latérale et du total « À trier ».
   Une requête JSON légère toutes les 60 s, en pause quand l'onglet est masqué ; aucune animation, aucun clignotement. */
(function () {
  'use strict';
  var btn = document.querySelector('[data-sb-triage]');
  if (!btn || !window.fetch) return;
  var url = btn.getAttribute('data-sb-url');
  var EVERY = 60000;
  var timer = null;
  var lastAt = 0;
  var busy = false;
  var TONES = ['neutral', 'warn', 'bad'];

  function paint(el, kind, e) {
    if (!el) return;
    var n = e && e.count ? e.count : 0;
    TONES.forEach(function (t) { el.classList.remove('sb-' + kind + '--' + t); });
    el.classList.add('sb-' + kind + '--' + (e && TONES.indexOf(e.tone) >= 0 ? e.tone : 'neutral'));
    el.setAttribute('data-count', String(n));
    if (n < 1) {
      el.hidden = true;
      el.removeAttribute('aria-label');
      el.removeAttribute('role');
      el.textContent = '';
      return;
    }
    el.hidden = false;
    el.setAttribute('role', 'img');
    el.setAttribute('aria-label', e.aria || '');
    el.textContent = e.label || String(n);
  }

  function apply(d) {
    var entries = d.entries || {};
    var sections = d.sections || {};
    Array.prototype.forEach.call(document.querySelectorAll('[data-sb-nav]'), function (el) {
      var id = el.getAttribute('data-sb-nav');
      if (id === '') return;
      paint(el, 'badge', entries[id]);
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-sb-section]'), function (el) {
      paint(el, 'pill', sections[el.getAttribute('data-sb-section')]);
    });
    var worst = 'neutral';
    Object.keys(entries).forEach(function (k) {
      var t = entries[k].tone;
      if (t === 'bad' || (t === 'warn' && worst === 'neutral')) worst = t;
    });
    paint(btn.querySelector('[data-sb-nav=""]'), 'badge', { count: d.total || 0, label: d.label, aria: d.aria, tone: worst });
  }

  function refresh() {
    if (busy || document.hidden) return;
    busy = true;
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (d) apply(d); })
      .catch(function () {})
      .then(function () { busy = false; lastAt = Date.now(); });
  }

  function stop() {
    if (timer) { window.clearInterval(timer); timer = null; }
  }
  function start() {
    stop();
    timer = window.setInterval(refresh, EVERY);
  }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) { stop(); return; }
    if (Date.now() - lastAt >= EVERY) refresh();
    start();
  });
  lastAt = Date.now();
  if (!document.hidden) start();
})();
