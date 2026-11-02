/**
 * Effectifs — kit commun (pages Chaîne et Affectations, réutilisable) : menus flottants accrochés à un bouton,
 * avatars à initiales, échappement HTML. Exposé sous window.EffKit.
 * Le menu se ferme à Échap, au clic extérieur et quand le focus le quitte ; le focus revient au bouton d’origine.
 */
(function () {
  'use strict';
  if (window.EffKit) return;

  var open = null; // { el, anchor, onClose }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function avatar(initials, color, size) {
    return '<span class="effk-av' + (size ? ' effk-av--' + size : '') + '" style="--av:' + esc(color || '#475569') + '" aria-hidden="true">' + esc(initials || '?') + '</span>';
  }

  function place(el, anchor) {
    var r = anchor.getBoundingClientRect();
    var vw = document.documentElement.clientWidth;
    var vh = window.innerHeight;
    el.style.left = '0px';
    el.style.top = '0px';
    var w = el.offsetWidth;
    var hgt = el.offsetHeight;
    var left = Math.min(Math.max(12, r.left), vw - w - 12);
    var top = r.bottom + 6;
    if (top + hgt > vh - 12 && r.top - hgt - 6 > 12) top = r.top - hgt - 6;
    el.style.left = Math.max(12, left) + 'px';
    el.style.top = Math.max(12, Math.min(top, vh - hgt - 12)) + 'px';
  }

  function close(restoreFocus) {
    if (!open) return;
    var cur = open;
    open = null;
    cur.el.hidden = true;
    if (cur.anchor) cur.anchor.setAttribute('aria-expanded', 'false');
    if (cur.onClose) cur.onClose();
    if (restoreFocus !== false && cur.anchor && document.contains(cur.anchor)) cur.anchor.focus();
  }

  /** Ouvre le menu el (élément [hidden] déjà dans la page) sous anchor. */
  function pop(el, anchor, onClose) {
    if (open && open.el === el && open.anchor === anchor) {
      close();
      return;
    }
    close(false);
    el.hidden = false;
    open = { el: el, anchor: anchor, onClose: onClose || null };
    if (anchor) anchor.setAttribute('aria-expanded', 'true');
    place(el, anchor);
    var first = el.querySelector('input, button, a[href]');
    if (first) first.focus();
  }

  document.addEventListener('pointerdown', function (e) {
    if (!open) return;
    if (open.el.contains(e.target) || (open.anchor && open.anchor.contains(e.target))) return;
    close(false);
  });
  document.addEventListener('keydown', function (e) {
    if (!open) return;
    if (e.key === 'Escape') {
      e.preventDefault();
      close();
      return;
    }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      var items = Array.prototype.slice.call(open.el.querySelectorAll('.effk-pi:not([hidden])'));
      if (!items.length) return;
      var i = items.indexOf(document.activeElement);
      var next = e.key === 'ArrowDown' ? (i + 1) % items.length : (i <= 0 ? items.length - 1 : i - 1);
      items[next].focus();
      e.preventDefault();
    }
  });
  document.addEventListener('focusin', function (e) {
    if (!open) return;
    if (open.el.contains(e.target) || (open.anchor && open.anchor.contains(e.target))) return;
    close(false);
  });
  window.addEventListener('resize', function () { close(false); });
  window.addEventListener('scroll', function (e) {
    if (open && !open.el.contains(e.target)) close(false);
  }, true);

  window.EffKit = { esc: esc, avatar: avatar, pop: pop, closePop: close };
})();
