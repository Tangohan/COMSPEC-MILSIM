/* Page publique COMSPEC ATAK (/atak-natif) : bascule jour / nuit, apparition au défilement, ancre active. */
(function () {
  'use strict';
  var root = document.getElementById('atx');
  if (!root) {
    return;
  }
  var KEY = 'athena.atak.theme';

  var toggle = root.querySelector('[data-atx-theme]');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var next = root.getAttribute('data-theme') === 'day' ? 'night' : 'day';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem(KEY, next); } catch (e) {}
    });
  }

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var items = root.querySelectorAll('.atx-reveal');
  if (!reduce && 'IntersectionObserver' in window) {
    root.classList.add('atx-js');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-in');
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    items.forEach(function (el) { io.observe(el); });
  }

  // Ancre de la sous-navigation qui suit la section à l'écran.
  var links = root.querySelectorAll('.atx-subnav a[href^="#"]');
  if ('IntersectionObserver' in window && links.length) {
    var byId = {};
    links.forEach(function (a) { byId[a.getAttribute('href').slice(1)] = a; });
    var spy = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) {
          return;
        }
        links.forEach(function (a) { a.classList.remove('is-active'); });
        var a = byId[entry.target.id];
        if (a) {
          a.classList.add('is-active');
        }
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    Object.keys(byId).forEach(function (id) {
      var section = document.getElementById(id);
      if (section) {
        spy.observe(section);
      }
    });
  }
})();
