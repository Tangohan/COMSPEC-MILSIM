/**
 * Tuile unique « Signaler / Contacter » : choix du destinataire, puis
 * formulaire existant (rail bureau, formulaire mobile).
 */
(function () {
  'use strict';

  if (document.documentElement.getAttribute('data-dash-contact-choice-init') === '1') {
    return;
  }
  document.documentElement.setAttribute('data-dash-contact-choice-init', '1');

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  ready(function () {
    var tile = document.querySelector('.dash-contact-choice-tile');
    var openBtn = document.querySelector('[data-dash-contact-choice-open]');
    var modal = document.getElementById('dash-contact-choice-modal');
    if (!tile || !openBtn) {
      return;
    }

    var hasAdminChoice = !!document.querySelector('[data-dash-contact-choice="site-support"]');
    var lastFocus = null;

    function isDesktop() {
      return window.matchMedia('(min-width: 1024px)').matches;
    }

    function showInlineForm(kind) {
      var forms = tile.querySelectorAll('[data-dash-contact-form]');
      var opened = false;
      Array.prototype.forEach.call(forms, function (form) {
        var match = form.getAttribute('data-dash-contact-form') === kind;
        form.hidden = !match;
        form.classList.toggle('is-open', match);
        if (match) {
          opened = true;
        }
      });
      tile.classList.toggle('is-inline-open', opened && !isDesktop());
      if (opened && !isDesktop()) {
        var target = tile.querySelector('[data-dash-contact-form="' + kind + '"]');
        if (target && typeof target.scrollIntoView === 'function') {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }
    }

    function openModal() {
      if (!modal) {
        return;
      }
      lastFocus = document.activeElement;
      modal.hidden = false;
      document.body.classList.add('dash-contact-choice-modal-open');
      var first = modal.querySelector('[data-dash-contact-choice]');
      if (first && typeof first.focus === 'function') {
        first.focus();
      }
    }

    function closeModal() {
      if (!modal || modal.hidden) {
        return;
      }
      modal.hidden = true;
      document.body.classList.remove('dash-contact-choice-modal-open');
      if (lastFocus && typeof lastFocus.focus === 'function') {
        lastFocus.focus();
      } else if (typeof openBtn.focus === 'function') {
        openBtn.focus();
      }
    }

    openBtn.addEventListener('click', function (event) {
      if (hasAdminChoice) {
        event.preventDefault();
        event.stopPropagation();
        openModal();
        return;
      }
      showInlineForm('org-anomaly');
    });

    document.querySelectorAll('[data-dash-contact-choice]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var kind = btn.getAttribute('data-dash-contact-choice') || 'org-anomaly';
        closeModal();
        showInlineForm(kind);
      });
    });

    document.querySelectorAll('[data-dash-contact-choice-close]').forEach(function (el) {
      el.addEventListener('click', function () {
        closeModal();
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && modal && !modal.hidden) {
        event.preventDefault();
        closeModal();
      }
    });

    function applyHash() {
      var hash = (window.location.hash || '').replace(/^#/, '');
      if (hash === 'signaler-anomalie') {
        closeModal();
        showInlineForm('org-anomaly');
        return;
      }
      if (hash === 'contacter-admin-site' && hasAdminChoice) {
        closeModal();
        showInlineForm('site-support');
      }
    }

    applyHash();
    window.addEventListener('hashchange', applyHash);
  });
})();
