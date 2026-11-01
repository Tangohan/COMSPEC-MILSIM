/**
 * Contribuer (/contribuer) : apparition au défilement et compteurs (mêmes effets que la page de dons),
 * filtre du mur par rôle, champ « langues » montré seulement quand « Traducteur » est coché.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-ctr]');
  if (!root) return;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ---- Apparition au défilement ------------------------------------------------------------
  var items = root.querySelectorAll('.don-reveal');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
      });
    }, { threshold: 0.12 });
    Array.prototype.forEach.call(items, function (el) { io.observe(el); });
  } else {
    Array.prototype.forEach.call(items, function (el) { el.classList.add('is-in'); });
  }

  // ---- Compteurs ---------------------------------------------------------------------------
  if (!reduce && 'IntersectionObserver' in window) {
    Array.prototype.forEach.call(root.querySelectorAll('[data-don-count]'), function (el) {
      var target = parseInt(el.getAttribute('data-don-count'), 10) || 0;
      var final = el.textContent;
      el.textContent = '0';
      var seen = new IntersectionObserver(function (entries) {
        if (!entries[0].isIntersecting) return;
        seen.disconnect();
        var t0 = null;
        var step = function (ts) {
          if (t0 === null) t0 = ts;
          var k = Math.min(1, (ts - t0) / 1200);
          el.textContent = k < 1 ? String(Math.round(target * (1 - Math.pow(1 - k, 3)))) : final;
          if (k < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
      }, { threshold: 0.6 });
      seen.observe(el);
    });
  }

  // ---- Filtre du mur -----------------------------------------------------------------------
  var filter = root.querySelector('[data-ctr-filter]');
  var wall = root.querySelector('[data-ctr-wall]');
  var none = root.querySelector('[data-ctr-none]');
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
  var form = document.getElementById('ctr-form');
  var langs = document.getElementById('ctr-langs-field');
  if (form && langs) {
    var box = form.querySelector('input[name="roles[]"][value="translator"]');
    var sync = function () { langs.hidden = !(box && box.checked && !box.disabled); };
    Array.prototype.forEach.call(form.querySelectorAll('input[name="roles[]"]'), function (input) {
      input.addEventListener('change', sync);
    });
    sync();
  }
})();
