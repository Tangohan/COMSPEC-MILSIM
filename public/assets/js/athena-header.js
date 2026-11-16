/**
 * Navbar Athena — panneaux déroulants (Espaces, Menu, Alertes, Profil).
 *
 * Menu Espaces (role="menu") : ouverture au clic ou à la flèche bas, flèches / Début / Fin pour passer d'une carte
 * à l'autre, Échap ferme et rend le focus au bouton, Tab referme le menu, clic extérieur ferme.
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

  var reduceMotion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

  function menuItems(panel) {
    return Array.prototype.slice.call(panel.querySelectorAll('[role="menuitem"]')).filter(function (el) {
      return el.offsetParent !== null;
    });
  }

  ready(function () {
    document.querySelectorAll('[data-athena-header]').forEach(function (root) {
      var buttons = Array.prototype.slice.call(root.querySelectorAll('[data-athena-toggle]'));
      var panels = Array.prototype.slice.call(root.querySelectorAll('[data-athena-panel]'));

      function hidePanel(panel) {
        if (panel.classList.contains('hidden')) {
          return;
        }
        // Fermeture douce du menu Espaces ; sans animation si l'utilisateur la désactive.
        if (panel.getAttribute('role') === 'menu' && !(reduceMotion && reduceMotion.matches)) {
          panel.classList.add('is-closing');
          window.setTimeout(function () {
            if (panel.classList.contains('is-closing')) {
              panel.classList.remove('is-closing');
              panel.classList.add('hidden');
            }
          }, 130);
          return;
        }
        panel.classList.add('hidden');
      }

      function closePanels(except) {
        panels.forEach(function (panel) {
          if (panel.getAttribute('data-athena-panel') !== except) {
            hidePanel(panel);
          }
        });
        buttons.forEach(function (button) {
          if (button.getAttribute('data-athena-toggle') !== except) {
            button.setAttribute('aria-expanded', 'false');
          }
        });
      }

      function openPanel(name, button, panel, focusItem) {
        closePanels(name);
        panel.classList.remove('is-closing');
        panel.classList.remove('hidden');
        button.setAttribute('aria-expanded', 'true');
        if (panel.getAttribute('role') === 'menu' && focusItem) {
          var items = menuItems(panel);
          var target = panel.querySelector('[role="menuitem"][aria-current="page"]') || items[0];
          if (focusItem === 'last') {
            target = items[items.length - 1];
          }
          if (target) {
            target.focus();
          }
        }
      }

      buttons.forEach(function (button) {
        var name = button.getAttribute('data-athena-toggle');
        var panel = root.querySelector('[data-athena-panel="' + name + '"]');
        if (!panel) {
          return;
        }

        button.addEventListener('click', function (event) {
          event.stopPropagation();
          var willOpen = panel.classList.contains('hidden') || panel.classList.contains('is-closing');
          if (willOpen) {
            // Au clavier (clic synthétique, detail 0), le focus entre dans le menu.
            openPanel(name, button, panel, event.detail === 0 ? 'first' : null);
          } else {
            closePanels();
          }
        });

        if (panel.getAttribute('role') === 'menu') {
          button.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
              event.preventDefault();
              openPanel(name, button, panel, event.key === 'ArrowUp' ? 'last' : 'first');
            }
          });

          panel.addEventListener('keydown', function (event) {
            var items = menuItems(panel);
            var i = items.indexOf(document.activeElement);
            var next = null;
            if (event.key === 'ArrowDown') {
              next = items[(i + 1) % items.length];
            } else if (event.key === 'ArrowUp') {
              next = items[(i - 1 + items.length) % items.length];
            } else if (event.key === 'Home') {
              next = items[0];
            } else if (event.key === 'End') {
              next = items[items.length - 1];
            } else if (event.key === 'Tab') {
              closePanels();
              return;
            }
            if (next) {
              event.preventDefault();
              next.focus();
            }
          });
        }
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
        if (event.key !== 'Escape') {
          return;
        }
        var openMenu = panels.filter(function (p) {
          return !p.classList.contains('hidden') && p.contains(document.activeElement);
        })[0];
        var owner = openMenu
          ? root.querySelector('[data-athena-toggle="' + openMenu.getAttribute('data-athena-panel') + '"]')
          : null;
        closePanels();
        if (owner) {
          owner.focus();
        }
      });
    });
  });
})();
