/**
 * Mode nuit du back-office : interrupteur de la barre du haut.
 * Le thème initial est posé par le script en tête de page (views/layout/main.php).
 * Préférence stockée dans localStorage « athena.bo.theme » : light | dark (absente = suit le système).
 */
(function () {
  'use strict';

  var KEY = 'athena.bo.theme';
  var root = document.documentElement;
  var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function stored() {
    try { return localStorage.getItem(KEY); } catch (e) { return null; }
  }

  function isDark() {
    return root.getAttribute('data-bo-theme') === 'dark';
  }

  function syncButtons() {
    var dark = isDark();
    var label = dark ? 'Passer en mode jour' : 'Passer en mode nuit';
    document.querySelectorAll('[data-ath-theme-toggle]').forEach(function (btn) {
      btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
      btn.setAttribute('title', label);
      btn.setAttribute('aria-label', label);
    });
  }

  function apply(dark, animate) {
    if (animate) {
      root.classList.add('ath-theme-switching');
      window.setTimeout(function () { root.classList.remove('ath-theme-switching'); }, 320);
    }
    root.setAttribute('data-bo-theme', dark ? 'dark' : 'light');
    syncButtons();
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest ? ev.target.closest('[data-ath-theme-toggle]') : null;
    if (!btn) return;
    var next = !isDark();
    try {
      // Si le choix rejoint celui du système, on revient au mode automatique.
      var systemDark = media ? media.matches : false;
      if (next === systemDark) localStorage.removeItem(KEY);
      else localStorage.setItem(KEY, next ? 'dark' : 'light');
    } catch (e) {}
    apply(next, true);
  });

  if (media) {
    var onChange = function (e) {
      var pref = stored();
      if (pref !== 'dark' && pref !== 'light') apply(e.matches, true);
    };
    if (media.addEventListener) media.addEventListener('change', onChange);
    else if (media.addListener) media.addListener(onChange);
  }

  // Raccourci clavier : Maj + D (hors champs de saisie)
  document.addEventListener('keydown', function (ev) {
    if (!ev.shiftKey || ev.ctrlKey || ev.metaKey || ev.altKey || (ev.key !== 'D' && ev.key !== 'd')) return;
    var t = ev.target;
    if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
    var btn = document.querySelector('[data-ath-theme-toggle]');
    if (btn) { ev.preventDefault(); btn.click(); }
  });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', syncButtons);
  else syncButtons();
})();
