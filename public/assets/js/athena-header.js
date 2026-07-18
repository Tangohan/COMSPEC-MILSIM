/**
 * Navbar Athena — panneaux déroulants (Espaces, Menu, Alertes, Profil).
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
    document.querySelectorAll('[data-athena-header]').forEach(function (root) {
      var buttons = Array.prototype.slice.call(root.querySelectorAll('[data-athena-toggle]'));
      var panels = Array.prototype.slice.call(root.querySelectorAll('[data-athena-panel]'));

      function closePanels(except) {
        panels.forEach(function (panel) {
          if (panel.getAttribute('data-athena-panel') !== except) {
            panel.classList.add('hidden');
          }
        });
        buttons.forEach(function (button) {
          if (button.getAttribute('data-athena-toggle') !== except) {
            button.setAttribute('aria-expanded', 'false');
          }
        });
      }

      buttons.forEach(function (button) {
        var name = button.getAttribute('data-athena-toggle');
        var panel = root.querySelector('[data-athena-panel="' + name + '"]');
        if (!panel) {
          return;
        }

        button.addEventListener('click', function (event) {
          event.stopPropagation();
          var willOpen = panel.classList.contains('hidden');
          closePanels(name);
          panel.classList.toggle('hidden', !willOpen);
          button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
      });

      panels.forEach(function (panel) {
        panel.addEventListener('click', function (event) {
          event.stopPropagation();
        });
      });

      document.addEventListener('click', function () {
        closePanels();
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          closePanels();
        }
      });
    });
  });
})();
