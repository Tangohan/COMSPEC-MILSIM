/**
 * Indicateurs d’usage : surligne dans le sommaire la section visible à l’écran.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-an-root]');
  if (!root || !('IntersectionObserver' in window)) {
    return;
  }
  var links = root.querySelectorAll('[data-an-nav]');
  if (!links.length) {
    return;
  }

  function activate(id) {
    links.forEach(function (link) {
      var on = link.getAttribute('data-an-nav') === id;
      link.classList.toggle('is-active', on);
      if (on) {
        link.setAttribute('aria-current', 'true');
      } else {
        link.removeAttribute('aria-current');
      }
    });
  }

  var visible = {};
  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      visible[entry.target.id] = entry.isIntersecting ? entry.intersectionRatio : 0;
    });
    var best = null;
    var bestRatio = 0;
    links.forEach(function (link) {
      var id = link.getAttribute('data-an-nav');
      if ((visible[id] || 0) > bestRatio) {
        bestRatio = visible[id];
        best = id;
      }
    });
    if (best) {
      activate(best);
    }
  }, { rootMargin: '-80px 0px -45% 0px', threshold: [0, 0.15, 0.4, 0.75] });

  links.forEach(function (link) {
    var section = document.getElementById(link.getAttribute('data-an-nav'));
    if (section) {
      observer.observe(section);
    }
  });
  activate(links[0].getAttribute('data-an-nav'));
})();
