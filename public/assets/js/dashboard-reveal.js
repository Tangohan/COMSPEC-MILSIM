/**
 * Révélations de sections du tableau de bord (DSFR-inspired, prefers-reduced-motion).
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  ready(function () {
    var nodes = Array.prototype.slice.call(
      document.querySelectorAll('[data-dash-reveal], .dash-reveal')
    );
    if (!nodes.length) {
      return;
    }

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce || !('IntersectionObserver' in window)) {
      nodes.forEach(function (el) {
        el.classList.add('is-revealed');
      });
      return;
    }

    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) {
            return;
          }
          entry.target.classList.add('is-revealed');
          io.unobserve(entry.target);
        });
      },
      { rootMargin: '0px 0px -8% 0px', threshold: 0.08 }
    );

    nodes.forEach(function (el, i) {
      el.style.setProperty('--dash-reveal-delay', Math.min(i * 40, 200) + 'ms');
      io.observe(el);
    });
  });
})();
