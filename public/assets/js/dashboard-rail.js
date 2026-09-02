/**
 * Aside dashboard Athena — panneau drill unique (clic tuile = ouverture directe), glow emerald, press.
 * Pattern « même aside, contenu remplacé, bouton retour » (réf. aside Registre / compte).
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
    var rail = document.querySelector('[data-dash-rail]');
    if (!rail) {
      return;
    }
    // Garde d'idempotence : évite d'attacher deux jeux d'écouteurs si le script
    // se retrouve inclus plusieurs fois sur la même page (double clic ouvre/referme
    // aussitôt le panneau, ce qui ressemble à un clic « mort »).
    if (rail.getAttribute('data-dash-rail-init') === '1') {
      return;
    }
    rail.setAttribute('data-dash-rail-init', '1');

    var rootView = rail.querySelector('[data-dash-rail-root]');
    var drillView = rail.querySelector('[data-dash-rail-drill]');
    var drillBody = rail.querySelector('[data-dash-rail-drill-body]');
    var drillLead = rail.querySelector('[data-dash-rail-drill-lead]');
    var drillHeading = rail.querySelector('#dash-rail-drill-heading, #bo-rail-drill-heading, [data-dash-rail-drill-heading]')
      || document.getElementById('dash-rail-drill-heading');
    var backBtn = rail.querySelector('[data-dash-rail-back]');
    var openTriggers = Array.prototype.slice.call(rail.querySelectorAll('[data-dash-rail-open]'));
    var drillOpenId = null;
    var lastFocus = null;
    /** Empêche focusout/pointerdown de refermer le panneau pendant l’ouverture. */
    var suppressCloseUntil = 0;

    function setExpanded(on) {
      rail.classList.toggle('is-expanded', on);
    }

    function lockCloseBriefly(ms) {
      suppressCloseUntil = Date.now() + (ms || 320);
    }

    function isCloseLocked() {
      return Date.now() < suppressCloseUntil;
    }

    function syncOpenTriggers() {
      openTriggers.forEach(function (btn) {
        var id = btn.getAttribute('data-dash-rail-open');
        var isOpen = drillOpenId !== null && id === drillOpenId;
        btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        btn.classList.toggle('is-open', isOpen);
        var item = btn.closest('[data-dash-rail-item]');
        if (item) {
          item.classList.toggle('is-nested-open', isOpen);
        }
      });
    }

    function closeDrill(opts) {
      opts = opts || {};
      if (drillOpenId === null) {
        setExpanded(false);
        return;
      }

      drillOpenId = null;
      rail.classList.remove('is-drill-open');
      if (drillView) {
        drillView.hidden = true;
      }
      if (rootView) {
        rootView.hidden = false;
      }
      if (drillBody) {
        drillBody.innerHTML = '';
      }
      syncOpenTriggers();
      setExpanded(false);

      if (opts.restoreFocus && lastFocus && typeof lastFocus.focus === 'function') {
        try {
          lastFocus.focus({ preventScroll: true });
        } catch (e) {
          lastFocus.focus();
        }
      }
    }

    function openDrill(id, trigger) {
      var source = rail.querySelector('[data-dash-rail-nested="' + id + '"]');
      if (!source || !drillBody || !drillView) {
        return;
      }

      lockCloseBriefly(400);

      var title = source.getAttribute('data-dash-rail-title') || 'Rubrique';
      var lead = source.getAttribute('data-dash-rail-lead') || '';
      var body = source.querySelector('.dash-rail__nested-body') || source;

      if (drillHeading) {
        drillHeading.textContent = title;
      }
      if (drillLead) {
        drillLead.textContent = lead;
        drillLead.hidden = lead === '';
      }

      drillBody.innerHTML = '';
      drillBody.appendChild(body.cloneNode(true));

      drillOpenId = id;
      lastFocus = trigger || null;
      rail.classList.add('is-drill-open');
      setExpanded(true);

      // Afficher le panneau drill AVANT de masquer la racine, puis y placer le focus
      // immédiatement : masquer un bouton focalisé déclenche un focusout avec
      // relatedTarget=null, ce qui fermait le panneau à la milliseconde suivante.
      drillView.hidden = false;
      if (backBtn && typeof backBtn.focus === 'function') {
        try {
          backBtn.focus({ preventScroll: true });
        } catch (e) {
          backBtn.focus();
        }
      }
      if (rootView) {
        rootView.hidden = true;
      }
      syncOpenTriggers();
    }

    openTriggers.forEach(function (btn) {
      btn.addEventListener('click', function (event) {
        if (btn.disabled) {
          return;
        }
        event.preventDefault();
        event.stopPropagation();
        var id = btn.getAttribute('data-dash-rail-open');
        if (!id) {
          return;
        }
        if (drillOpenId === id) {
          closeDrill({ restoreFocus: true });
          return;
        }
        openDrill(id, btn);
      });
    });

    if (backBtn) {
      backBtn.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        closeDrill({ restoreFocus: true });
      });
    }

    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape' || drillOpenId === null) {
        return;
      }
      event.preventDefault();
      closeDrill({ restoreFocus: true });
    });

    document.addEventListener('pointerdown', function (event) {
      if (drillOpenId === null || isCloseLocked()) {
        return;
      }
      // Aside fixe (back-office) : ne pas refermer au clic dans le contenu principal.
      if (rail.hasAttribute('data-dash-rail-persist-drill')) {
        return;
      }
      var target = event.target;
      if (!(target instanceof Node)) {
        return;
      }
      if (rail.contains(target)) {
        return;
      }
      closeDrill({ restoreFocus: false });
    });

    // Ne plus fermer sur focusout : masquer la vue racine / déplacer le focus
    // provoquait une fermeture immédiate (clic « mort »). Fermeture via Retour,
    // Escape, ou clic hors du rail uniquement.

    function openFromExternal(id) {
      if (!id) {
        return;
      }
      var desktop = window.matchMedia('(min-width: 1024px)').matches;
      if (!desktop) {
        var cardId = id === 'site-support' ? 'contacter-admin-site' : 'signaler-anomalie';
        var card = document.getElementById(cardId);
        if (card && typeof card.scrollIntoView === 'function') {
          card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        return;
      }
      var btn = rail.querySelector('[data-dash-rail-open="' + id + '"]');
      if (!btn || btn.disabled) {
        return;
      }
      lockCloseBriefly(500);
      if (drillOpenId === id) {
        return;
      }
      openDrill(id, btn);
    }

    document.querySelectorAll('[data-dash-rail-open-external]').forEach(function (el) {
      el.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        openFromExternal(el.getAttribute('data-dash-rail-open-external'));
      });
    });

    function openFromHash() {
      var hash = (window.location.hash || '').replace(/^#/, '');
      if (hash === 'signaler-anomalie') {
        openFromExternal('org-anomaly');
      }
      if (hash === 'contacter-admin-site') {
        openFromExternal('site-support');
      }
    }

    openFromHash();
    window.addEventListener('hashchange', openFromHash);

    // Back-office / pages avec data-dash-rail-autoload : ouvrir la rubrique active.
    if (rail.hasAttribute('data-dash-rail-autoload')) {
      var activeBtn = rail.querySelector('[data-dash-rail-open].is-active');
      if (activeBtn && !activeBtn.disabled) {
        var autoId = activeBtn.getAttribute('data-dash-rail-open');
        if (autoId) {
          openDrill(autoId, activeBtn);
        }
      }
    }
  });
})();
