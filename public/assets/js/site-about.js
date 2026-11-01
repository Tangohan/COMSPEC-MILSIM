/**
 * À propos : bascule jour / nuit. Sans JavaScript, la page suit le thème du système.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-ap]');
  if (!root) return;

  var KEY = 'athena.about.theme';
  var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  var btn = root.querySelector('[data-ap-theme-toggle]');

  function stored() {
    try { return localStorage.getItem(KEY); } catch (e) { return null; }
  }

  function apply(theme) {
    root.setAttribute('data-theme', theme);
    if (!btn) return;
    var label = theme === 'dark' ? btn.getAttribute('data-label-light') : btn.getAttribute('data-label-dark');
    btn.setAttribute('aria-label', label || '');
    btn.setAttribute('title', label || '');
  }

  var pref = stored();
  apply(pref === 'dark' || pref === 'light' ? pref : (media && media.matches ? 'dark' : 'light'));

  if (btn) {
    btn.hidden = false;
    btn.addEventListener('click', function () {
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      try {
        // Revenir au choix du système efface la préférence.
        if (media && (next === 'dark') === media.matches) localStorage.removeItem(KEY);
        else localStorage.setItem(KEY, next);
      } catch (e) {}
      apply(next);
    });
  }

  if (media) {
    var onSystem = function (e) {
      var p = stored();
      if (p !== 'dark' && p !== 'light') apply(e.matches ? 'dark' : 'light');
    };
    if (media.addEventListener) media.addEventListener('change', onSystem);
    else if (media.addListener) media.addListener(onSystem);
  }
})();
