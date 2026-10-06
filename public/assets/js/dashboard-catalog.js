/**
 * Tableau de bord — vitrines « Nos formations » et « Nos tenues ».
 * Composants Alpine : trainingShowcase / kitShowcase (carrousel + fenêtre de détail accessible).
 * Données : window.__dashboardShowcaseCourses / window.__dashboardShowcaseKits.
 */
(function () {
  'use strict';

  var FOCUSABLE = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';

  function showcase(source) {
    return {
      openModal: null,
      items: [],
      canPrev: false,
      canNext: false,
      _trigger: null,

      init: function () {
        this.items = window[source] || [];
        var self = this;
        this.$nextTick(function () { self.syncNav(); });
        window.addEventListener('resize', function () { self.syncNav(); }, { passive: true });
      },

      active: function () {
        var self = this;
        return this.items.find(function (c) { return c.id === self.openModal; }) || null;
      },

      syncNav: function () {
        var t = this.$refs.track;
        if (!t) {
          return;
        }
        this.canPrev = t.scrollLeft > 4;
        this.canNext = t.scrollLeft + t.clientWidth < t.scrollWidth - 4;
      },

      /** dir : -1 / 1 (ou un décalage en px, compat. ancienne API). */
      scrollTrack: function (dir) {
        var t = this.$refs.track;
        if (!t) {
          return;
        }
        var step = Math.abs(dir) > 1 ? Math.abs(dir) : Math.max(260, Math.round(t.clientWidth * 0.85));
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        t.scrollBy({ left: dir < 0 ? -step : step, behavior: reduce ? 'auto' : 'smooth' });
      },

      open: function (id, ev) {
        this._trigger = ev && ev.currentTarget ? ev.currentTarget : document.activeElement;
        this.openModal = id;
        document.documentElement.classList.add('dash-cat-lock');
        var self = this;
        this.$nextTick(function () {
          setTimeout(function () {
            var dlg = self.$root.querySelector('[data-cat-dialog]');
            var first = dlg ? dlg.querySelector('[data-cat-close]') : null;
            if (first) {
              first.focus();
            }
          }, 0);
        });
      },

      close: function () {
        if (this.openModal === null) {
          return;
        }
        this.openModal = null;
        document.documentElement.classList.remove('dash-cat-lock');
        var back = this._trigger;
        this._trigger = null;
        if (back && typeof back.focus === 'function') {
          this.$nextTick(function () { back.focus(); });
        }
      },

      /** Garde le focus clavier dans la fenêtre ouverte. */
      trap: function (e) {
        var dlg = e.currentTarget;
        var nodes = Array.prototype.filter.call(dlg.querySelectorAll(FOCUSABLE), function (n) {
          return n.offsetParent !== null;
        });
        if (nodes.length === 0) {
          e.preventDefault();
          return;
        }
        var first = nodes[0];
        var last = nodes[nodes.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    };
  }

  document.addEventListener('alpine:init', function () {
    window.Alpine.data('trainingShowcase', function () { return showcase('__dashboardShowcaseCourses'); });
    window.Alpine.data('kitShowcase', function () { return showcase('__dashboardShowcaseKits'); });
  });
})();
