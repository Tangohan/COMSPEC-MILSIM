/**
 * Notre histoire : bascule jour / nuit, compteurs animés, filtres de la frise des versions.
 * Sans JavaScript, la page reste complète : chiffres finaux, toutes les versions affichées.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-nh]');
  if (!root) return;

  var KEY = 'athena.history.theme';
  var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  var reduced = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)').matches : false;

  // ---------- Thème ----------
  function stored() {
    try { return localStorage.getItem(KEY); } catch (e) { return null; }
  }

  function applyTheme(theme) {
    root.setAttribute('data-theme', theme);
    var btn = root.querySelector('[data-nh-theme-toggle]');
    if (!btn) return;
    var label = theme === 'dark' ? btn.getAttribute('data-label-light') : btn.getAttribute('data-label-dark');
    btn.setAttribute('aria-label', label || '');
    btn.setAttribute('title', label || '');
  }

  var pref = stored();
  applyTheme(pref === 'dark' || pref === 'light' ? pref : (media && media.matches ? 'dark' : 'light'));

  var toggle = root.querySelector('[data-nh-theme-toggle]');
  if (toggle) {
    toggle.hidden = false;
    toggle.addEventListener('click', function () {
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      try {
        // Revenir au choix du système efface la préférence.
        if (media && (next === 'dark') === media.matches) localStorage.removeItem(KEY);
        else localStorage.setItem(KEY, next);
      } catch (e) {}
      applyTheme(next);
    });
  }
  if (media) {
    var onSystem = function (e) {
      var p = stored();
      if (p !== 'dark' && p !== 'light') applyTheme(e.matches ? 'dark' : 'light');
    };
    if (media.addEventListener) media.addEventListener('change', onSystem);
    else if (media.addListener) media.addListener(onSystem);
  }

  // ---------- Compteurs ----------
  var lang = (document.documentElement.getAttribute('lang') || 'fr').slice(0, 2);
  var nf;
  try { nf = new Intl.NumberFormat(lang === 'en' ? 'en-GB' : 'fr-FR'); } catch (e) { nf = null; }
  function format(n) { return nf ? nf.format(n) : String(n); }

  function run(el) {
    var target = parseInt(el.getAttribute('data-nh-count'), 10);
    if (!isFinite(target) || target <= 0) return;
    var start = null;
    var duration = Math.min(1800, 900 + String(target).length * 120);
    function frame(ts) {
      if (start === null) start = ts;
      var t = Math.min(1, (ts - start) / duration);
      var eased = 1 - Math.pow(1 - t, 3);
      el.textContent = format(Math.round(target * eased));
      if (t < 1) window.requestAnimationFrame(frame);
    }
    window.requestAnimationFrame(frame);
  }

  var counters = root.querySelectorAll('[data-nh-count]');
  if (!reduced && 'IntersectionObserver' in window && counters.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        io.unobserve(entry.target);
        run(entry.target);
      });
    }, { threshold: 0.4 });
    counters.forEach(function (el) { io.observe(el); });
  }

  // ---------- Frise des versions ----------
  var box = root.querySelector('[data-nh-versions]');
  if (!box) return;
  var STEP = 12;
  var items = Array.prototype.slice.call(box.querySelectorAll('[data-nh-release]'));
  var months = Array.prototype.slice.call(box.querySelectorAll('[data-nh-month]'));
  var chips = Array.prototype.slice.call(box.querySelectorAll('[data-nh-filter]'));
  var more = box.querySelector('[data-nh-more]');
  var status = box.querySelector('[data-nh-status]');
  var statusTpl = box.getAttribute('data-status-label') || '';
  var filter = 'all';
  var limit = STEP;

  function matches(el) {
    return filter === 'all' || (' ' + (el.getAttribute('data-products') || '') + ' ').indexOf(' ' + filter + ' ') !== -1;
  }

  function render() {
    var shown = 0;
    var total = 0;
    items.forEach(function (el) {
      var ok = matches(el);
      if (ok) total++;
      var visible = ok && shown < limit;
      if (visible) shown++;
      el.hidden = !visible;
    });
    months.forEach(function (m) {
      m.hidden = !m.querySelector('[data-nh-release]:not([hidden])');
    });
    if (more) more.hidden = shown >= total;
    if (status && statusTpl) status.textContent = statusTpl.replace(':shown', format(shown)).replace(':total', format(total));
  }

  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      filter = chip.getAttribute('data-nh-filter') || 'all';
      limit = STEP;
      chips.forEach(function (c) { c.setAttribute('aria-pressed', c === chip ? 'true' : 'false'); });
      render();
    });
  });
  if (more) {
    more.addEventListener('click', function () {
      limit += STEP * 2;
      render();
    });
  }
  render();
})();
