/**
 * Barre de recherche globale SSE — suggestions live + soumission Enter.
 */
(function () {
  'use strict';

  var form = document.getElementById('iw-search-form');
  if (!form) return;

  var input = document.getElementById('iw-q');
  var box = document.getElementById('iw-search-suggest');
  var suggestUrl = form.getAttribute('data-suggest-url') || '';
  if (!input || !box || !suggestUrl) return;

  var timer = null;
  var lastQ = '';
  var active = -1;
  var items = [];

  function hide() {
    box.hidden = true;
    box.innerHTML = '';
    active = -1;
    items = [];
    input.setAttribute('aria-expanded', 'false');
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function render(list, q) {
    items = Array.isArray(list) ? list : [];
    active = -1;
    if (!items.length) {
      box.innerHTML = '<div class="iw-search-suggest__empty">Aucun aperçu pour « ' + esc(q) + ' » — Entrée pour la page complète</div>';
      box.hidden = false;
      return;
    }
    box.innerHTML = items.map(function (r, i) {
      return '<a class="iw-search-suggest__item" role="option" data-idx="' + i + '" href="' + esc(r.href || '#') + '">'
        + '<span class="iw-search-suggest__type">' + esc(r.type || '') + '</span>'
        + '<span class="iw-search-suggest__label">' + esc(r.label || '') + '</span>'
        + '<span class="iw-search-suggest__ref">' + esc(r.ref || '') + '</span>'
        + (r.hint ? '<span class="iw-search-suggest__hint">' + esc(r.hint) + '</span>' : '')
        + '</a>';
    }).join('') + '<a class="iw-search-suggest__more" href="' + esc(form.action) + '?q=' + encodeURIComponent(q) + '">Voir tous les résultats</a>';
    box.hidden = false;
    input.setAttribute('aria-expanded', 'true');
  }

  function fetchSuggest(q) {
    if (q.length < 2) {
      hide();
      return;
    }
    if (q === lastQ) return;
    lastQ = q;
    fetch(suggestUrl + '?q=' + encodeURIComponent(q), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function (data) {
        if (input.value.trim() !== q) return;
        render((data && data.results) || [], q);
      })
      .catch(function () {
        /* La soumission classique reste disponible. */
      });
  }

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(function () {
      fetchSuggest(input.value.trim());
    }, 220);
  }

  function move(delta) {
    var links = box.querySelectorAll('.iw-search-suggest__item');
    if (!links.length) return;
    active = (active + delta + links.length) % links.length;
    links.forEach(function (el, i) {
      el.classList.toggle('is-active', i === active);
    });
    links[active].scrollIntoView({ block: 'nearest' });
  }

  input.addEventListener('input', schedule);
  input.addEventListener('focus', function () {
    if (input.value.trim().length >= 2) schedule();
  });
  input.addEventListener('keydown', function (ev) {
    if (box.hidden) return;
    if (ev.key === 'ArrowDown') {
      ev.preventDefault();
      move(1);
    } else if (ev.key === 'ArrowUp') {
      ev.preventDefault();
      move(-1);
    } else if (ev.key === 'Escape') {
      hide();
    } else if (ev.key === 'Enter' && active >= 0) {
      var links = box.querySelectorAll('.iw-search-suggest__item');
      if (links[active]) {
        ev.preventDefault();
        window.location.href = links[active].getAttribute('href');
      }
    }
  });

  document.addEventListener('click', function (ev) {
    if (!form.contains(ev.target)) hide();
  });
})();
