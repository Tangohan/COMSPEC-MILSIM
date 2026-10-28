/**
 * Aside des modules LMS (Formations, Recrutement) — menu repliable sur mobile.
 * Bouton « Menu » : ouvre / ferme le panneau ; Échap ferme et rend le focus ;
 * un clic sur un lien referme le menu ; retour au bureau (≥ 1024 px) = panneau toujours visible.
 */
(function () {
  'use strict';

  function init(aside) {
    if (aside.getAttribute('data-lms-nav-init') === '1') return;
    aside.setAttribute('data-lms-nav-init', '1');
    var btn = aside.querySelector('[data-lms-nav-toggle]');
    var panel = btn ? document.getElementById(btn.getAttribute('aria-controls')) : null;
    if (!btn || !panel) return;
    var mq = window.matchMedia('(max-width: 1023.98px)');

    function set(open, focusBack) {
      aside.classList.toggle('is-open', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        var first = panel.querySelector('.lms-nav__item.is-active') || panel.querySelector('a[href]');
        if (first) first.focus({ preventScroll: true });
      } else if (focusBack) {
        btn.focus({ preventScroll: true });
      }
    }

    btn.addEventListener('click', function () {
      set(!aside.classList.contains('is-open'), false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && aside.classList.contains('is-open')) {
        set(false, true);
      }
    });
    panel.addEventListener('click', function (e) {
      if (e.target.closest && e.target.closest('a[href]') && mq.matches) {
        set(false, false);
      }
    });
    var onChange = function () { if (!mq.matches) set(false, false); };
    if (mq.addEventListener) mq.addEventListener('change', onChange);
    else if (mq.addListener) mq.addListener(onChange);
  }

  function run() {
    document.querySelectorAll('[data-lms-nav]').forEach(init);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
})();
