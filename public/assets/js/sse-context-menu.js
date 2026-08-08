/**
 * Menu contextuel (clic droit) — Intelligence Workspace SSE.
 * Cible : cartes, lignes de tableau, fiches, canevas (via API), actions page.
 */
(function () {
  'use strict';

  if (!document.body || !document.body.classList.contains('sse-iw')) {
    return;
  }

  var menuEl = null;
  var IGNORE = 'input, textarea, select, option, [contenteditable="true"]';

  function closeMenu() {
    if (menuEl) {
      menuEl.remove();
      menuEl = null;
    }
  }

  function clamp(menu, clientX, clientY) {
    var pad = 8;
    var mw = menu.offsetWidth;
    var mh = menu.offsetHeight;
    var x = clientX;
    var y = clientY;
    if (x + mw + pad > window.innerWidth) {
      x = Math.max(pad, window.innerWidth - mw - pad);
    }
    if (y + mh + pad > window.innerHeight) {
      y = Math.max(pad, window.innerHeight - mh - pad);
    }
    menu.style.left = x + 'px';
    menu.style.top = y + 'px';
  }

  function copyText(text) {
    var value = String(text || '').trim();
    if (!value) {
      return Promise.resolve(false);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(value).then(function () {
        return true;
      }).catch(function () {
        return fallbackCopy(value);
      });
    }
    return Promise.resolve(fallbackCopy(value));
  }

  function fallbackCopy(value) {
    var ta = document.createElement('textarea');
    ta.value = value;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    var ok = false;
    try {
      ok = document.execCommand('copy');
    } catch (err) {
      ok = false;
    }
    ta.remove();
    return ok;
  }

  function runAction(action) {
    if (!action || action.disabled) {
      return;
    }
    if (typeof action.run === 'function') {
      action.run();
      return;
    }
    if (action.copy) {
      void copyText(action.copy);
      return;
    }
    if (action.post) {
      if (action.confirm && !window.confirm(String(action.confirm))) {
        return;
      }
      var form = document.createElement('form');
      form.method = 'post';
      form.action = String(action.post);
      form.style.display = 'none';
      var csrf = action.csrf || (window.SSE_CTX && window.SSE_CTX.csrf) || '';
      if (csrf) {
        var token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_csrf_token';
        token.value = csrf;
        form.appendChild(token);
      }
      document.body.appendChild(form);
      form.submit();
      return;
    }
    if (action.href) {
      if (action.target === '_blank') {
        window.open(String(action.href), '_blank', 'noopener');
      } else {
        window.location.href = String(action.href);
      }
    }
  }

  function openMenu(clientX, clientY, actions, title) {
    closeMenu();
    var items = (actions || []).filter(function (a) {
      return a && a.label && (a.href || a.copy || a.post || typeof a.run === 'function' || a.separator);
    });
    if (items.length === 0) {
      return;
    }

    menuEl = document.createElement('div');
    menuEl.className = 'sse-ctx-menu';
    menuEl.setAttribute('role', 'menu');

    if (title) {
      var head = document.createElement('div');
      head.className = 'sse-ctx-menu__title';
      head.textContent = title;
      menuEl.appendChild(head);
    }

    items.forEach(function (action) {
      if (action.separator) {
        var hr = document.createElement('div');
        hr.className = 'sse-ctx-menu__sep';
        menuEl.appendChild(hr);
        return;
      }
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'sse-ctx-menu__item' + (action.danger ? ' is-danger' : '');
      btn.setAttribute('role', 'menuitem');
      btn.textContent = action.label;
      if (action.disabled) {
        btn.disabled = true;
      }
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        closeMenu();
        runAction(action);
      });
      menuEl.appendChild(btn);
    });

    document.body.appendChild(menuEl);
    clamp(menuEl, clientX, clientY);
  }

  function parseJsonAttr(el, name) {
    var raw = el.getAttribute(name);
    if (!raw) {
      return null;
    }
    try {
      return JSON.parse(raw);
    } catch (err) {
      return null;
    }
  }

  function textFrom(el, selector) {
    var node = el.querySelector(selector);
    return node ? String(node.textContent || '').trim() : '';
  }

  function openHrefActions(href, title, copyValue) {
    var actions = [];
    if (href) {
      actions.push({ label: 'Ouvrir', href: href });
      actions.push({ label: 'Ouvrir dans un nouvel onglet', href: href, target: '_blank' });
    }
    if (copyValue) {
      actions.push({ separator: true });
      actions.push({ label: 'Copier la référence', copy: copyValue });
    }
    return { title: title || '', actions: actions };
  }

  function fromExplicit(el) {
    var actions = parseJsonAttr(el, 'data-sse-ctx-actions');
    if (!Array.isArray(actions) || actions.length === 0) {
      return null;
    }
    return {
      title: el.getAttribute('data-sse-ctx-title') || textFrom(el, 'strong, .record-name') || '',
      actions: actions
    };
  }

  function fromCard(card) {
    var href = card.getAttribute('href') || '';
    var title = textFrom(card, 'strong') || textFrom(card, '.record-name');
    var ref = textFrom(card, '.record-id');
    return openHrefActions(href, title, ref);
  }

  function fromRecord(record) {
    var link = record.querySelector('a.record-name.link, a.link[href*="/identites/"], a.link[href*="/atak/sse/"]');
    var href = link ? link.getAttribute('href') : '';
    var title = textFrom(record, '.record-name') || (link ? String(link.textContent || '').trim() : '');
    var ref = textFrom(record, '.record-sub') || textFrom(record, 'a.link');
    var refMatch = ref.match(/IDN-\d+/i);
    return openHrefActions(href, title, refMatch ? refMatch[0] : '');
  }

  function fromRow(row) {
    var open = row.querySelector('a.btn-open, a.link[href]');
    if (!open) {
      return null;
    }
    var href = open.getAttribute('href') || '';
    if (!href || href === '#') {
      return null;
    }
    var title = textFrom(row, '.record-name') || textFrom(row, 'strong') || String(open.textContent || '').trim();
    var ref = textFrom(row, '.record-id');
    return openHrefActions(href, title, ref);
  }

  function fromPageBlank() {
    var cfg = window.SSE_CTX;
    if (!cfg || !Array.isArray(cfg.pageActions) || cfg.pageActions.length === 0) {
      return null;
    }
    return {
      title: cfg.pageTitle || 'Bureau SSE',
      actions: cfg.pageActions
    };
  }

  function resolveContext(target) {
    if (!target || !target.closest) {
      return null;
    }
    if (target.closest(IGNORE) || target.closest('.sse-ctx-menu')) {
      return null;
    }

    var explicit = target.closest('[data-sse-ctx-actions]');
    if (explicit) {
      return fromExplicit(explicit);
    }

    var card = target.closest('a.sse-folder-card, a.sse-mesh-card');
    if (card) {
      return fromCard(card);
    }

    var record = target.closest('article.sse-record');
    if (record) {
      return fromRecord(record);
    }

    var row = target.closest('.iw-main table tbody tr');
    if (row) {
      return fromRow(row);
    }

    var ops = target.closest('.sse-ops-grid > a, .iw-main a.btn, .iw-main a.btn-open');
    if (ops && ops.tagName === 'A' && ops.getAttribute('href')) {
      return openHrefActions(ops.getAttribute('href'), String(ops.textContent || '').trim().slice(0, 48), '');
    }

    // Zone vide de la page → actions globales (créer / naviguer)
    if (target.closest('.iw-main') && !target.closest('a, button, form, .sse-mesh-canvas-wrap')) {
      return fromPageBlank();
    }

    return null;
  }

  document.addEventListener(
    'contextmenu',
    function (e) {
      var ctx = resolveContext(e.target);
      if (!ctx || !ctx.actions || ctx.actions.length === 0) {
        return;
      }
      e.preventDefault();
      openMenu(e.clientX, e.clientY, ctx.actions, ctx.title);
    },
    true
  );

  document.addEventListener('click', function (e) {
    if (menuEl && (!e.target || !menuEl.contains(e.target))) {
      closeMenu();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeMenu();
    }
  });

  window.addEventListener('scroll', closeMenu, true);
  window.addEventListener('resize', closeMenu);

  window.SseContextMenu = {
    open: openMenu,
    close: closeMenu,
    copy: copyText
  };
})();
