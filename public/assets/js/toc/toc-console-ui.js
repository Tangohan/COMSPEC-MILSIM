/* Poste TOC web : composants de la console sans données. Menu contextuel de la carte (clic droit, Maj + F10, appui
 * long ; flèches, Entrée, Échap), fenêtre latérale des fonctions ouvertes dans la console (piège du focus, Échap,
 * retour du focus à l'ouverture précédente). Mêmes jetons que le reste du poste (atak-toc.css). */
window.TocUi = (function () {
  'use strict';
  var esc = function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
  var $ = function (sel, root) { return (root || document).querySelector(sel); };

  /* ---------- Menu contextuel ---------- */
  var menu = (function () {
    var el = null, onPick = null, back = null, stack = [];
    function items() { return Array.prototype.slice.call(el.querySelectorAll('[role="menuitem"]:not([disabled])')); }
    function render(header, entries) {
      var h = header ? '<div class="toc-ctx-head">' + header + '</div>' : '';
      entries.forEach(function (e) {
        if (!e) return;
        if (e.sep) { h += '<div class="toc-ctx-sep" role="separator"></div>'; return; }
        if (e.title) { h += '<div class="toc-ctx-title">' + esc(e.title) + '</div>'; return; }
        h += '<button type="button" role="menuitem" class="toc-ctx-item' + (e.danger ? ' is-danger' : '') + '" data-ctx="' + esc(e.id) + '"' + (e.disabled ? ' disabled' : '') + (e.hint ? ' title="' + esc(e.hint) + '"' : '') + '><span>' + esc(e.label) + '</span>' + (e.sub ? '<i aria-hidden="true">›</i>' : (e.kbd ? '<kbd>' + esc(e.kbd) + '</kbd>' : '')) + '</button>';
      });
      el.innerHTML = h;
    }
    function place(x, y) {
      el.style.left = '0px'; el.style.top = '0px';
      var w = el.offsetWidth, hh = el.offsetHeight;
      var vw = window.innerWidth, vh = window.innerHeight;
      el.style.left = Math.max(4, Math.min(x, vw - w - 4)) + 'px';
      el.style.top = Math.max(4, Math.min(y, vh - hh - 4)) + 'px';
    }
    function close(keepFocus) {
      if (!el || el.hidden) return;
      el.hidden = true;
      el.innerHTML = '';
      stack = [];
      if (!keepFocus && back && back.focus) back.focus();
      back = null;
    }
    function show(level) {
      render(level.header, level.entries);
      place(level.x, level.y);
      var first = items()[0];
      if (first) first.focus();
    }
    function open(x, y, header, entries, pick) {
      el = el || $('#toc-ctx');
      if (!el) return;
      if (el.hidden) back = document.activeElement;
      onPick = pick;
      el.hidden = false;
      stack = [{ x: x, y: y, header: header, entries: entries }];
      show(stack[0]);
    }
    function entryOf(id) {
      var cur = stack[stack.length - 1];
      for (var i = 0; i < cur.entries.length; i++) if (cur.entries[i] && cur.entries[i].id === id) return cur.entries[i];
      return null;
    }
    document.addEventListener('click', function (e) {
      if (!el || el.hidden) return;
      var b = e.target.closest ? e.target.closest('[data-ctx]') : null;
      if (!b || !el.contains(b)) { close(true); return; }
      var entry = entryOf(b.getAttribute('data-ctx'));
      if (!entry) return;
      if (entry.sub) {
        var cur = stack[stack.length - 1];
        stack.push({ x: cur.x, y: cur.y, header: cur.header, entries: [{ id: '__back', label: 'Retour' }, { sep: true }].concat(entry.sub) });
        show(stack[stack.length - 1]);
        return;
      }
      if (entry.id === '__back') { stack.pop(); show(stack[stack.length - 1]); return; }
      var fn = onPick;
      close();
      if (fn) fn(entry.id, entry);
    }, true);
    document.addEventListener('contextmenu', function (e) { if (el && !el.hidden && el.contains(e.target)) e.preventDefault(); });
    document.addEventListener('keydown', function (e) {
      if (!el || el.hidden) return;
      var list = items();
      var i = list.indexOf(document.activeElement);
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); if (stack.length > 1) { stack.pop(); show(stack[stack.length - 1]); } else close(); }
      else if (e.key === 'ArrowDown') { e.preventDefault(); list[(i + 1) % list.length].focus(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); list[(i - 1 + list.length) % list.length].focus(); }
      else if (e.key === 'Home') { e.preventDefault(); list[0].focus(); }
      else if (e.key === 'End') { e.preventDefault(); list[list.length - 1].focus(); }
      else if (e.key === 'ArrowLeft' && stack.length > 1) { e.preventDefault(); stack.pop(); show(stack[stack.length - 1]); }
      else if (e.key === 'ArrowRight' && i >= 0 && list[i].querySelector('i')) { e.preventDefault(); list[i].click(); }
      else if (e.key === 'Tab') { e.preventDefault(); close(); }
    }, true);
    var lastSize = window.innerWidth + 'x' + window.innerHeight;
    window.addEventListener('resize', function () {
      var size = window.innerWidth + 'x' + window.innerHeight;
      if (size !== lastSize) { lastSize = size; close(true); }
    });
    return { open: open, close: close, isOpen: function () { return !!el && !el.hidden; } };
  })();

  /* ---------- Fenêtre latérale ---------- */
  var drawer = (function () {
    var veil = null, box = null, back = null, cur = null;
    function focusables() {
      return Array.prototype.slice.call(box.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex="0"]')).filter(function (n) { return n.offsetParent !== null; });
    }
    function close(silent) {
      if (!veil || veil.hidden) return;
      veil.hidden = true;
      box.innerHTML = '';
      var c = cur;
      cur = null;
      if (back && back.focus && document.contains(back)) back.focus();
      back = null;
      if (c && c.onClose && !silent) c.onClose();
    }
    /**
     * cfg : id, title, sub, body (HTML), fullHref + fullLabel (lien « page complète »), onOpen(racine),
     * onSubmit(formulaire, événement), onClose().
     */
    function open(cfg) {
      veil = veil || $('#toc-drawer-veil');
      box = box || $('#toc-drawer');
      if (!veil) return null;
      if (veil.hidden) back = document.activeElement;
      cur = cfg;
      box.innerHTML = '<header class="toc-drawer-head"><div><h2 id="toc-drawer-title">' + esc(cfg.title) + '</h2>' + (cfg.sub ? '<p>' + esc(cfg.sub) + '</p>' : '') + '</div><button type="button" class="toc-btn is-small" data-drawer-close>Fermer <kbd>Échap</kbd></button></header>' +
        '<div class="toc-drawer-body">' + (cfg.body || '') + '</div>' +
        (cfg.fullHref ? '<footer class="toc-drawer-foot"><a href="' + esc(cfg.fullHref) + '">' + esc(cfg.fullLabel || 'Ouvrir dans la page complète') + '</a></footer>' : '');
      veil.hidden = false;
      if (cfg.onOpen) cfg.onOpen(box);
      var f = box.querySelector('[autofocus]') || focusables()[1] || focusables()[0];
      if (f) f.focus(); else box.focus();
      return box;
    }
    document.addEventListener('click', function (e) {
      if (!veil || veil.hidden) return;
      if (e.target === veil || (e.target.closest && e.target.closest('[data-drawer-close]'))) close();
    });
    document.addEventListener('submit', function (e) {
      if (!veil || veil.hidden || !box.contains(e.target)) return;
      e.preventDefault();
      if (cur && cur.onSubmit) cur.onSubmit(e.target, e);
    });
    document.addEventListener('keydown', function (e) {
      if (!veil || veil.hidden || menu.isOpen()) return;
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); return; }
      if (e.key !== 'Tab') return;
      var list = focusables();
      if (!list.length) { e.preventDefault(); return; }
      var first = list[0], last = list[list.length - 1];
      if (e.shiftKey && (document.activeElement === first || !box.contains(document.activeElement))) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && (document.activeElement === last || !box.contains(document.activeElement))) { e.preventDefault(); first.focus(); }
    }, true);
    return {
      open: open, close: close,
      isOpen: function () { return !!veil && !veil.hidden; },
      current: function () { return cur ? cur.id : null; },
      body: function () { return box ? box.querySelector('.toc-drawer-body') : null; },
      message: function (text, isError) {
        var b = box && box.querySelector('.toc-drawer-msg');
        if (!b) return;
        b.textContent = text || '';
        b.classList.toggle('is-error', !!isError);
      }
    };
  })();

  return { menu: menu, drawer: drawer, esc: esc };
})();
