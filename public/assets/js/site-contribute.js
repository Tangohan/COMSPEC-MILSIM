/**
 * Contribuer (/contribuer) : bascule jour / nuit (sans JavaScript, la page suit le système),
 * filtre du mur par rôle, champ « langues » montré seulement quand « Traducteur » est coché.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-cb]');
  if (!root) return;

  // ---- Thème -------------------------------------------------------------------------------
  var KEY = 'athena.contribute.theme';
  var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  var btn = root.querySelector('[data-cb-theme-toggle]');

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

  // ---- Filtre du mur -----------------------------------------------------------------------
  var filter = root.querySelector('[data-cb-filter]');
  var wall = root.querySelector('[data-cb-wall]');
  var none = root.querySelector('[data-cb-none]');
  if (filter && wall) {
    var chips = filter.querySelectorAll('button[data-role]');
    var people = wall.querySelectorAll('[data-roles]');
    if (chips.length > 2) filter.hidden = false;
    filter.addEventListener('click', function (e) {
      var chip = e.target.closest ? e.target.closest('button[data-role]') : null;
      if (!chip) return;
      var role = chip.getAttribute('data-role') || '';
      var shown = 0;
      Array.prototype.forEach.call(chips, function (c) { c.setAttribute('aria-pressed', c === chip ? 'true' : 'false'); });
      Array.prototype.forEach.call(people, function (p) {
        var match = role === '' || (' ' + p.getAttribute('data-roles') + ' ').indexOf(' ' + role + ' ') !== -1;
        p.hidden = !match;
        if (match) shown++;
      });
      if (none) none.hidden = shown > 0;
    });
  }

  // ---- Formulaire : langues du traducteur --------------------------------------------------
  var form = document.getElementById('cb-form');
  var langs = document.getElementById('cb-langs-field');
  if (form && langs) {
    var box = form.querySelector('input[name="roles[]"][value="translator"]');
    var sync = function () { langs.hidden = !(box && box.checked && !box.disabled); };
    Array.prototype.forEach.call(form.querySelectorAll('input[name="roles[]"]'), function (input) {
      input.addEventListener('change', sync);
    });
    sync();
  }
})();
