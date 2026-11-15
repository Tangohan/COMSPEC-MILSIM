/**
 * JNET — Espace commun (centre de commandement) : améliorations facultatives.
 * Sans JavaScript, chaque salon est une page servie par le serveur (?salon=), chaque filtre, tri et détail d'unité une adresse,
 * chaque épingle, accusé ou réponse à un événement un formulaire. Ce script ajoute :
 *   - le changement de salon sans rechargement (la page du salon est lue puis substituée ; repli : navigation normale) ;
 *   - le détail d'une unité dans un tiroir (contenu déjà rendu par le serveur dans des <template>) ;
 *   - la recherche rapide (touche « / ») : salons et unités ; Alt + flèches haut et bas : changer de salon ;
 *   - le panneau de droite repliable (mémorisé dans ce navigateur) ;
 *   - l'arbre des unités repliable, le filtre au fil de la frappe, la copie d'un indicatif ;
 *   - épingler, désépingler, accuser réception, répondre à un événement sans rechargement, glisser-déposer des épinglés,
 *     recherche d'une unité ou d'une page à épingler.
 * Aucun texte français n'est écrit ici : les libellés viennent du bloc [data-jc-t] de la vue (traduit avec elle).
 * Le serveur revérifie chaque droit : rien ici ne décide d'un accès.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-jc]');
  if (!root) {
    return;
  }
  root.classList.add('jc-js');

  // La barre des salons se colle juste sous la barre du haut, quelle que soit sa hauteur (elle passe sur plusieurs lignes en mobile).
  var topBar = document.querySelector('.ja-hd');
  var syncTop = function () {
    if (topBar) {
      root.style.setProperty('--jc-top', Math.round(topBar.getBoundingClientRect().height) + 'px');
    }
  };
  syncTop();
  if (topBar && typeof ResizeObserver === 'function') {
    new ResizeObserver(syncTop).observe(topBar);
  } else {
    window.addEventListener('resize', syncTop);
  }

  var store = {
    get: function (k) {
      try { return window.localStorage.getItem(k); } catch (e) { return null; }
    },
    set: function (k, v) {
      try { window.localStorage.setItem(k, v); } catch (e) { /* stockage indisponible : sans mémoire */ }
    }
  };

  var live = document.createElement('p');
  live.className = 'ja-sr';
  live.setAttribute('role', 'status');
  live.setAttribute('aria-live', 'polite');
  document.body.appendChild(live);

  function fold(text) {
    return String(text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/œ/g, 'oe').replace(/’/g, "'").replace(/\s+/g, ' ').trim();
  }

  function say(message) {
    live.textContent = '';
    window.setTimeout(function () { live.textContent = message; }, 30);
  }

  /** Libellé traduit de la vue (bloc caché [data-jc-t]). */
  function t(key) {
    var el = root.querySelector('[data-jc-t] [data-t="' + key + '"]');
    return el ? el.textContent || '' : '';
  }

  // ——— Avis ———

  var toastTimer = null;

  function toast(message, bad) {
    var el = root.querySelector('[data-jc-toast]');
    if (!el || !message) { return; }
    el.textContent = message;
    el.classList.toggle('is-bad', !!bad);
    el.classList.add('is-on');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(function () { el.classList.remove('is-on'); }, 2600);
    say(message);
  }

  // ——— Mise en page : hauteur de l'en-tête, panneau de droite ———

  function measure() {
    var hd = document.querySelector('.ja-hd');
    if (hd) { root.style.setProperty('--jc-top', hd.offsetHeight + 'px'); }
  }

  function applyMemory() {
    root.classList.toggle('is-aside-off', store.get('jnet.commun.aside') === 'off');
    var toggle = root.querySelector('[data-jc-aside-toggle]');
    if (toggle) {
      toggle.setAttribute('aria-pressed', root.classList.contains('is-aside-off') ? 'false' : 'true');
    }
    var finder = root.querySelector('[data-jc-finder]');
    if (finder) { finder.hidden = false; }
  }

  // ——— Arbre des unités (tableau) ———

  var collapsed = {};

  function treeRows() {
    return Array.prototype.slice.call(root.querySelectorAll('[data-jc-tree-table] tbody > tr[data-id]'));
  }

  function applyTree(showAll) {
    var rows = treeRows();
    var stack = [];
    rows.forEach(function (row) {
      var depth = parseInt(row.getAttribute('data-depth') || '0', 10);
      while (stack.length && stack[stack.length - 1].depth >= depth) { stack.pop(); }
      var hidden = !showAll && stack.some(function (s) { return collapsed[s.id]; });
      if (hidden) { row.setAttribute('data-collapsed', ''); } else { row.removeAttribute('data-collapsed'); }
      stack.push({ id: row.getAttribute('data-id'), depth: depth });
    });
    rows.forEach(function (row, i) {
      var id = row.getAttribute('data-id');
      var btn = row.querySelector('[data-jc-tg]');
      var sub = row.querySelector('[data-jc-sub]');
      var depth = parseInt(row.getAttribute('data-depth') || '0', 10);
      var count = 0;
      for (var j = i + 1; j < rows.length && parseInt(rows[j].getAttribute('data-depth') || '0', 10) > depth; j++) { count++; }
      if (btn) { btn.setAttribute('aria-expanded', collapsed[id] ? 'false' : 'true'); }
      if (sub) { sub.textContent = collapsed[id] && !showAll ? '+' + count + ' ' + t('sub') : ''; }
    });
    var all = root.querySelector('[data-jc-expand-all]');
    if (all) {
      var anyCollapsed = Object.keys(collapsed).some(function (k) { return collapsed[k]; });
      all.textContent = anyCollapsed ? t('expand') : t('collapse');
    }
  }

  function initTree() {
    collapsed = {};
    var rows = treeRows();
    if (!rows.length) { return; }
    // Une grande organisation s'ouvre sur ses deux premiers niveaux seulement.
    if (rows.length > 60) {
      rows.forEach(function (row) {
        if (parseInt(row.getAttribute('data-kids') || '0', 10) > 0 && parseInt(row.getAttribute('data-depth') || '0', 10) >= 1) {
          collapsed[row.getAttribute('data-id')] = true;
        }
      });
    }
    applyTree(false);
  }

  root.addEventListener('click', function (e) {
    var tg = e.target.closest('[data-jc-tg]');
    if (tg) {
      e.preventDefault();
      e.stopPropagation();
      var id = tg.getAttribute('data-jc-tg');
      collapsed[id] = !collapsed[id];
      applyTree(false);
      return;
    }
    var all = e.target.closest('[data-jc-expand-all]');
    if (all) {
      e.preventDefault();
      var anyCollapsed = Object.keys(collapsed).some(function (k) { return collapsed[k]; });
      if (anyCollapsed) {
        collapsed = {};
      } else {
        treeRows().forEach(function (row) {
          if (parseInt(row.getAttribute('data-kids') || '0', 10) > 0 && parseInt(row.getAttribute('data-depth') || '0', 10) >= 0) {
            collapsed[row.getAttribute('data-id')] = true;
          }
        });
      }
      applyTree(false);
    }
  });

  // ——— Tiroir : détail d'une unité ———

  var drawerFrom = null;
  var drawerTimer = null;

  function drawerEls() {
    return {
      dw: root.querySelector('[data-jc-drawer]'),
      body: root.querySelector('[data-jc-drawer-body]'),
      scrim: root.querySelector('[data-jc-scrim]')
    };
  }

  function unitTemplate(id) {
    return root.querySelector('template[data-jc-unit-tpl="' + String(id).replace(/[^0-9]/g, '') + '"]');
  }

  function openDrawer(content) {
    var d = drawerEls();
    if (!d.dw || !d.body || !d.scrim) { return false; }
    closePalette();
    d.body.innerHTML = '';
    d.body.appendChild(content);
    window.clearTimeout(drawerTimer);
    if (!drawerFrom) { drawerFrom = document.activeElement; }
    d.dw.hidden = false;
    d.scrim.hidden = false;
    document.body.classList.add('jc-lock');
    window.requestAnimationFrame(function () {
      d.dw.classList.add('is-on');
      d.scrim.classList.add('is-on');
      d.dw.focus({ preventScroll: true });
    });
    var title = d.body.querySelector('.jc-dw__t');
    if (title) { say(title.textContent || ''); }
    return true;
  }

  function closeDrawer() {
    var d = drawerEls();
    if (!d.dw || d.dw.hidden) { return; }
    d.dw.classList.remove('is-on');
    d.scrim.classList.remove('is-on');
    document.body.classList.remove('jc-lock');
    drawerTimer = window.setTimeout(function () {
      d.dw.hidden = true;
      d.scrim.hidden = true;
      d.body.innerHTML = '';
    }, 300);
    if (drawerFrom && document.contains(drawerFrom) && drawerFrom.focus) { drawerFrom.focus({ preventScroll: true }); }
    drawerFrom = null;
  }

  /** Ouvre l'unité dans le tiroir si le contenu est dans la page ; sinon false (le lien normal prend le relais). */
  function openUnit(id) {
    var tpl = unitTemplate(id);
    if (!tpl || !tpl.content) { return false; }
    return openDrawer(tpl.content.cloneNode(true));
  }

  root.addEventListener('click', function (e) {
    var close = e.target.closest('[data-jc-close]');
    if (close) { e.preventDefault(); closeDrawer(); return; }
    if (e.target.closest('[data-jc-scrim]')) { closeDrawer(); return; }
    var a = e.target.closest('[data-jc-unit]');
    if (a && !e.defaultPrevented && e.button === 0 && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey) {
      if (openUnit(a.getAttribute('data-jc-unit'))) { e.preventDefault(); e.stopPropagation(); }
      return;
    }
    // Une ligne du tableau s'ouvre au clic, sauf sur ses propres commandes.
    var row = e.target.closest('tr.jc-row');
    if (row && !e.target.closest('a, button, input, select, textarea, label, summary')) {
      var link = row.querySelector('[data-jc-unit]');
      if (link) { openUnit(link.getAttribute('data-jc-unit')); }
    }
  }, true);

  // ——— Recherche rapide ———

  var pal = { items: [], sel: 0, from: null };

  function units() {
    var el = root.querySelector('script[data-jc-units]');
    if (!el) { return []; }
    try { return JSON.parse(el.textContent || '[]'); } catch (err) { return []; }
  }

  function palRender() {
    var box = root.querySelector('[data-jc-pal-res]');
    var input = root.querySelector('[data-jc-pal-q]');
    if (!box || !input) { return; }
    var q = fold(input.value);
    var groups = [];
    var salons = [];
    root.querySelectorAll('[data-jc-rail] a.jc-sb').forEach(function (a) {
      var label = (a.querySelector('.jc-sb__t') || a).textContent.trim();
      var num = (a.querySelector('.jc-sb__n') || { textContent: '' }).textContent.trim();
      if (!q || fold(label).indexOf(q) !== -1) {
        salons.push({ label: label, right: num, run: function () { go(a.href); } });
      }
    });
    groups.push([t('salons'), salons]);
    var list = [];
    units().forEach(function (u) {
      if (list.length >= 8) { return; }
      if (!q || fold(u.label + ' ' + u.code).indexOf(q) !== -1) {
        list.push({ label: u.label, right: u.code, run: function () { if (!openUnit(u.id)) { go(u.href); } } });
      }
    });
    groups.push([t('units'), list]);
    pal.items = [];
    box.innerHTML = '';
    var any = false;
    groups.forEach(function (g) {
      if (!g[1].length) { return; }
      any = true;
      var h = document.createElement('div');
      h.className = 'jc-pal__g';
      h.textContent = g[0];
      box.appendChild(h);
      g[1].forEach(function (it) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'jc-pal__i';
        b.setAttribute('role', 'option');
        var span = document.createElement('span');
        span.textContent = it.label;
        span.setAttribute('translate', 'no');
        b.appendChild(span);
        if (it.right) {
          var s = document.createElement('small');
          s.textContent = it.right;
          b.appendChild(s);
        }
        b.addEventListener('click', function () { closePalette(); it.run(); });
        box.appendChild(b);
        pal.items.push({ el: b, run: it.run });
      });
    });
    if (!any) {
      var p = document.createElement('p');
      p.className = 'jc-pal__none';
      p.textContent = t('none');
      box.appendChild(p);
    }
    pal.sel = Math.min(pal.sel, Math.max(0, pal.items.length - 1));
    palMark();
  }

  function palMark() {
    pal.items.forEach(function (it, i) {
      it.el.classList.toggle('is-on', i === pal.sel);
      it.el.setAttribute('aria-selected', i === pal.sel ? 'true' : 'false');
      if (i === pal.sel && it.el.scrollIntoView) { it.el.scrollIntoView({ block: 'nearest' }); }
    });
  }

  function openPalette() {
    var el = root.querySelector('[data-jc-pal]');
    var input = root.querySelector('[data-jc-pal-q]');
    if (!el || !input) { return; }
    closeDrawer();
    pal.from = document.activeElement;
    el.hidden = false;
    input.value = '';
    pal.sel = 0;
    palRender();
    window.setTimeout(function () { input.focus(); }, 20);
  }

  function closePalette() {
    var el = root.querySelector('[data-jc-pal]');
    if (!el || el.hidden) { return; }
    el.hidden = true;
    if (pal.from && document.contains(pal.from) && pal.from.focus) { pal.from.focus({ preventScroll: true }); }
    pal.from = null;
  }

  root.addEventListener('click', function (e) {
    if (e.target.closest('[data-jc-open-pal]')) { openPalette(); return; }
    var overlay = e.target.closest('[data-jc-pal]');
    if (overlay && e.target === overlay) { closePalette(); }
  });

  root.addEventListener('input', function (e) {
    if (e.target.closest('[data-jc-pal-q]')) { pal.sel = 0; palRender(); }
  });

  root.addEventListener('keydown', function (e) {
    if (!e.target.closest('[data-jc-pal-q]')) { return; }
    if (e.key === 'ArrowDown') { pal.sel = Math.min(pal.sel + 1, pal.items.length - 1); palMark(); e.preventDefault(); }
    if (e.key === 'ArrowUp') { pal.sel = Math.max(pal.sel - 1, 0); palMark(); e.preventDefault(); }
    if (e.key === 'Enter' && pal.items[pal.sel]) {
      var it = pal.items[pal.sel];
      e.preventDefault();
      closePalette();
      it.run();
    }
  });

  // ——— Changement de salon sans rechargement ———

  var loading = false;

  function load(url, push, quiet) {
    if (loading) { return; }
    loading = true;
    root.setAttribute('aria-busy', 'true');
    window.fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (res) {
        if (!res.ok || res.redirected) { throw new Error('http'); }
        return res.text();
      })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var next = doc.querySelector('[data-jc]');
        if (!next) { throw new Error('page'); }
        closeDrawer();
        closePalette();
        root.innerHTML = next.innerHTML;
        root.setAttribute('data-salon', next.getAttribute('data-salon') || '');
        document.title = doc.title || document.title;
        if (push) { window.history.pushState({ jc: 1 }, '', url); }
        init();
        if (quiet) { return; }
        var hash = (url.split('#')[1] || '');
        var target = hash ? document.getElementById(hash) : null;
        var view = root.querySelector('[data-jc-view]');
        if (target) {
          target.scrollIntoView();
        } else if (view) {
          view.scrollIntoView({ block: 'start' });
          view.focus({ preventScroll: true });
        }
        var title = root.querySelector('#jc-title');
        if (title) { say(title.textContent || ''); }
      })
      .catch(function () {
        window.location.href = url;
      })
      .then(function () {
        loading = false;
        root.removeAttribute('aria-busy');
      });
  }

  function go(href) {
    var a = document.createElement('a');
    a.href = href;
    if (sameOrigin(a)) { load(a.href, true); } else { window.location.href = href; }
  }

  function sameOrigin(a) {
    try {
      var u = new URL(a.href, window.location.href);
      return u.origin === window.location.origin && u.pathname === window.location.pathname;
    } catch (e) {
      return false;
    }
  }

  root.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-jc-salon]');
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
    if (!sameOrigin(a)) { return; }
    e.preventDefault();
    load(a.href, true);
  });

  window.addEventListener('popstate', function (e) {
    if (e.state && e.state.jc) { load(window.location.href, false); }
  });
  try { window.history.replaceState({ jc: 1 }, '', window.location.href); } catch (e) { /* historique indisponible */ }

  // ——— Panneau de droite ———

  root.addEventListener('click', function (e) {
    var tg = e.target.closest('[data-jc-aside-toggle]');
    if (!tg) { return; }
    var off = root.classList.toggle('is-aside-off');
    tg.setAttribute('aria-pressed', off ? 'false' : 'true');
    store.set('jnet.commun.aside', off ? 'off' : 'on');
  });

  // ——— Filtre au fil de la frappe ———

  root.addEventListener('input', function (e) {
    var input = e.target.closest('[data-jc-search]');
    if (!input) { return; }
    var selector = input.getAttribute('data-jc-filter') || '[data-jc-row]';
    var needle = fold(input.value);
    root.querySelectorAll(selector).forEach(function (el) {
      var hit = needle === '' || fold(el.getAttribute('data-jc-q')).indexOf(needle) !== -1;
      el.hidden = !hit;
    });
    if (root.querySelector('[data-jc-tree-table]')) { applyTree(needle !== ''); }
  });

  // ——— Copier un indicatif ———

  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-jc-copy]');
    if (!b) { return; }
    e.preventDefault();
    e.stopPropagation();
    var text = b.getAttribute('data-jc-copy') || '';
    var done = function () { toast(t('copied') + ' : ' + text); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done, done);
    } else {
      done();
    }
  });

  // ——— Clavier ———

  function salonLinks() {
    return Array.prototype.slice.call(root.querySelectorAll('[data-jc-rail] a.jc-sb'));
  }

  document.addEventListener('keydown', function (e) {
    var tag = (e.target.tagName || '').toLowerCase();
    var typing = tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable;
    if (e.key === 'Escape') {
      closeDrawer();
      closePalette();
      return;
    }
    if (e.key === '/' && !typing && !e.ctrlKey && !e.metaKey && !e.altKey) {
      e.preventDefault();
      openPalette();
      return;
    }
    if (e.altKey && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
      var links = salonLinks();
      var i = links.findIndex(function (a) { return a.classList.contains('is-active'); });
      if (!links.length || i === -1) { return; }
      e.preventDefault();
      var j = (i + (e.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
      load(links[j].href, true);
      return;
    }
    if ((e.key === 'ArrowRight' || e.key === 'ArrowLeft') && e.target.closest && e.target.closest('[data-jc-rail]')) {
      var tabs = salonLinks();
      var k = tabs.indexOf(e.target.closest('a.jc-sb'));
      if (k === -1) { return; }
      e.preventDefault();
      tabs[Math.max(0, Math.min(tabs.length - 1, k + (e.key === 'ArrowRight' ? 1 : -1)))].focus();
      return;
    }
    // Piège de focus dans le tiroir et la recherche rapide.
    if (e.key === 'Tab') {
      var layer = root.querySelector('[data-jc-drawer]:not([hidden])') || root.querySelector('[data-jc-pal]:not([hidden])');
      if (!layer) { return; }
      var f = Array.prototype.slice.call(layer.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])')).filter(function (el) { return el.offsetParent !== null; });
      if (!f.length) { e.preventDefault(); return; }
      var first = f[0];
      var last = f[f.length - 1];
      if (e.shiftKey && (document.activeElement === first || document.activeElement === layer)) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });

  // ——— Écritures sans rechargement ———

  function postJson(form, done) {
    var data = new FormData(form);
    window.fetch(form.getAttribute('data-api') || form.action, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }
    })
      .then(function (res) {
        return res.json().then(function (j) { return { ok: res.ok && j.ok !== false, message: j.message || j.error || '', status: j.status || '' }; });
      })
      .then(done)
      .catch(function () { done({ ok: false, message: t('net') }); });
  }

  // Épingles et accusés : écriture, puis la page du salon est relue.
  root.addEventListener('submit', function (e) {
    var form = e.target.closest('form[data-jc-pin], form[data-jc-ack]');
    if (!form || !window.fetch) { return; }
    e.preventDefault();
    var button = form.querySelector('button[type="submit"]');
    if (button) { button.disabled = true; }
    postJson(form, function (r) {
      if (r.ok) {
        load(window.location.href, false, true);
        window.setTimeout(function () { toast(r.message, false); }, 250);
      } else {
        if (button) { button.disabled = false; }
        toast(r.message, true);
      }
    });
  });

  // Réponse à un événement : les boutons du même événement se mettent à jour.
  root.addEventListener('submit', function (e) {
    var form = e.target.closest('form[data-jc-rsvp]');
    if (!form || !window.fetch) { return; }
    e.preventDefault();
    var id = form.getAttribute('data-jc-rsvp');
    var button = form.querySelector('button[type="submit"]');
    if (button) { button.disabled = true; }
    postJson(form, function (r) {
      if (button) { button.disabled = false; }
      if (!r.ok) { toast(r.message || t('net'), true); return; }
      root.querySelectorAll('form[data-jc-rsvp="' + id + '"]').forEach(function (f) {
        var on = f.getAttribute('data-status') === (r.status || form.getAttribute('data-status'));
        var b = f.querySelector('button');
        if (b) { b.classList.toggle('is-on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); }
      });
      toast(t('rsvp'), false);
    });
  });

  // ——— Glisser-déposer des épinglés ———

  var dragged = null;

  root.addEventListener('dragstart', function (e) {
    var li = e.target.closest && e.target.closest('[data-jc-pin-id]');
    if (!li || !li.closest('[data-jc-sortable]')) { return; }
    dragged = li;
    li.classList.add('is-dragging');
    if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; }
    try { if (e.dataTransfer) { e.dataTransfer.setData('text/plain', li.getAttribute('data-jc-pin-id') || ''); } } catch (err) { /* navigateur sans données de glissement */ }
  });

  root.addEventListener('dragover', function (e) {
    var li = e.target.closest && e.target.closest('[data-jc-pin-id]');
    if (!dragged || !li || li === dragged || !li.closest('[data-jc-sortable]')) { return; }
    e.preventDefault();
    var rect = li.getBoundingClientRect();
    var after = e.clientY > rect.top + rect.height / 2;
    li.parentNode.insertBefore(dragged, after ? li.nextSibling : li);
  });

  root.addEventListener('dragend', function () {
    if (!dragged) { return; }
    var list = dragged.closest('[data-jc-sortable]');
    dragged.classList.remove('is-dragging');
    dragged = null;
    if (!list) { return; }
    var token = root.querySelector('input[name="_csrf_token"]');
    var data = new FormData();
    data.append('salon', 'epingles');
    if (token) { data.append('_csrf_token', token.value); }
    list.querySelectorAll('[data-jc-pin-id]').forEach(function (li) { data.append('ids[]', li.getAttribute('data-jc-pin-id')); });
    window.fetch(list.getAttribute('data-jc-order-url'), {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }
    })
      .then(function (res) { return res.json().then(function (j) { return { ok: res.ok && j.ok !== false, message: j.message || '' }; }); })
      .catch(function () { return { ok: false, message: t('net') }; })
      .then(function (r) {
        load(window.location.href, false, true);
        window.setTimeout(function () { toast(r.message, !r.ok); }, 250);
      });
  });

  // ——— Recherche d'une unité ou d'une page à épingler ———

  var timer = null;

  root.addEventListener('input', function (e) {
    var input = e.target.closest('[data-jc-finder-q]');
    if (!input) { return; }
    var box = input.closest('[data-jc-finder]');
    var list = box.querySelector('[data-jc-finder-list]');
    window.clearTimeout(timer);
    var q = input.value.trim();
    if (q.length < 2) { list.innerHTML = ''; return; }
    timer = window.setTimeout(function () {
      window.fetch(box.getAttribute('data-url') + '?types=unite,page&q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (res) { return res.json(); })
        .then(function (j) {
          list.innerHTML = '';
          var csrf = box.querySelector('.jc-finder__csrf input');
          var pinLabel = box.getAttribute('data-label') || '';
          (j.results || []).forEach(function (r) {
            var parts = String(r.ref || '').split(':');
            if (parts.length !== 2) { return; }
            var li = document.createElement('li');
            var label = document.createElement('span');
            var strong = document.createElement('strong');
            strong.textContent = r.title;
            var em = document.createElement('em');
            em.textContent = (r.typeLabel || '') + (r.sub ? ' · ' + r.sub : '');
            label.appendChild(strong);
            label.appendChild(em);
            var form = document.createElement('form');
            form.method = 'post';
            form.action = box.getAttribute('data-action');
            form.setAttribute('data-jc-pin', '');
            [['salon', 'epingles'], ['type', parts[0]], ['id', parts[1]], ['_csrf_token', csrf ? csrf.value : '']].forEach(function (pair) {
              var hidden = document.createElement('input');
              hidden.type = 'hidden';
              hidden.name = pair[0];
              hidden.value = pair[1];
              form.appendChild(hidden);
            });
            var btn = document.createElement('button');
            btn.type = 'submit';
            btn.className = 'jc-tool';
            btn.textContent = pinLabel;
            form.appendChild(btn);
            li.appendChild(label);
            li.appendChild(form);
            list.appendChild(li);
          });
        })
        .catch(function () { list.innerHTML = ''; });
    }, 250);
  });

  // ——— Mise en route (à chaque salon affiché) ———

  function init() {
    measure();
    applyMemory();
    initTree();
    var active = root.querySelector('[data-jc-rail] a.is-active');
    if (active && active.scrollIntoView) { active.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
    // Une unité demandée dans l'adresse (?unite=) s'ouvre dans le tiroir à la place de la fiche en page.
    var card = root.querySelector('[data-jc-unit-card]');
    if (card) {
      var id = card.getAttribute('data-jc-unit-card');
      if (openUnit(id)) { card.remove(); }
    }
  }

  window.addEventListener('resize', measure);
  init();
})();
