/**
 * SSE > Personnes : sélection, tiroir d'aperçu, fenêtres modales (classer, verrouiller, clore, rouvrir, rattacher), annulation de
 * clôture pendant 15 s, bascule tableau / cartes mémorisée et raccourcis clavier (désactivables).
 * Les textes affichés viennent du HTML de la page (traduits côté serveur) : ce script ne contient aucune phrase à traduire.
 * Sans JavaScript, la liste, les onglets, la recherche et les filtres restent utilisables (adresses et formulaires).
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-pe]');
  var UNDO_MS = 15000;
  var KEYS_KEY = 'pe.keys';
  var VIEW_KEY = 'pe.view';
  var UNDO_KEY = 'pe.undo';

  function store(op, key, value) {
    try {
      if (op === 'get') { return window.localStorage.getItem(key); }
      if (op === 'set') { window.localStorage.setItem(key, value); }
    } catch (e) { /* stockage indisponible : le réglage n'est pas mémorisé */ }
    return null;
  }
  function session(op, key, value) {
    try {
      if (op === 'get') { return window.sessionStorage.getItem(key); }
      if (op === 'set') { window.sessionStorage.setItem(key, value); }
      if (op === 'del') { window.sessionStorage.removeItem(key); }
    } catch (e) { /* idem */ }
    return null;
  }
  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
  function msg(key, a) {
    var el = $('[data-pe-msgs] [data-k="' + key + '"]');
    var t = el ? el.textContent : '';
    return String(t).replace('%1', a === undefined ? '' : a);
  }
  function csrfToken() {
    var f = $('[data-pe-form] input[name="_csrf_token"]');
    return f ? f.value : (root ? root.getAttribute('data-csrf') : '');
  }
  function batchUrl() {
    var f = $('#pe-dlg-reopen form') || $('[data-pe-form]');
    return f ? f.getAttribute('action') : '';
  }

  /* ------------------------------------------------------------------ Fenêtres modales */
  function openDialog(dlg) {
    if (!dlg) { return; }
    if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
    var first = dlg.querySelector('select, textarea, input:not([type="hidden"]), button');
    if (first) { first.focus(); }
  }
  function closeDialog(dlg) {
    if (dlg && dlg.open) { dlg.close(); }
  }
  document.addEventListener('click', function (e) {
    var cancel = e.target.closest ? e.target.closest('[data-pe-cancel]') : null;
    if (cancel) { closeDialog(cancel.closest('dialog')); return; }
    if (e.target.tagName === 'DIALOG' && e.target.classList.contains('pe-dialog')) { closeDialog(e.target); }
  });

  function itemsById(ids) {
    var out = [];
    ids.forEach(function (id) {
      var el = $('[data-pe-item][data-id="' + id + '"]') || $('[data-pe-dr][data-id="' + id + '"]') || $('[data-pe-subject][data-id="' + id + '"]');
      if (el) { out.push(el); }
    });
    return out;
  }
  function configureDialog(act, ids) {
    var dlg = document.getElementById('pe-dlg-' + act);
    if (!dlg) { return null; }
    var form = dlg.querySelector('form');
    var idsField = dlg.querySelector('[data-pe-ids]');
    if (idsField) { idsField.value = ids.join(','); }
    var els = itemsById(ids);
    var names = els.map(function (el) { return el.getAttribute('data-name') || ''; }).filter(Boolean);
    names = names.filter(function (n, i) { return names.indexOf(n) === i; });
    var targets = dlg.querySelector('[data-pe-targets]');
    if (targets) {
      targets.textContent = ids.length === 1 && names.length ? names[0] : msg('targets', ids.length) + (names.length ? ' : ' + names.slice(0, 4).join(', ') + (names.length > 4 ? '…' : '') : '');
    }
    var err = dlg.querySelector('.pe-dlg__error');
    if (err) { err.hidden = true; err.textContent = ''; }
    if (form) { form.reset(); }
    if (act === 'close') {
      var wanted = els.filter(function (el) { return el.getAttribute('data-wanted') === '1'; }).length;
      var warn = dlg.querySelector('[data-pe-wanted-warn]');
      var submit = dlg.querySelector('[data-pe-close-submit]');
      var lift = dlg.querySelector('[data-pe-lift]');
      var subject = $('[data-pe-subject]');
      var canLift = root ? root.getAttribute('data-can-wanted') === '1' : !!subject && subject.getAttribute('data-can-wanted') === '1';
      if (lift) { lift.value = ''; }
      if (submit) { submit.disabled = false; submit.textContent = msg('close'); }
      if (warn) { warn.hidden = wanted === 0; warn.textContent = ''; }
      if (wanted > 0 && warn) {
        if (canLift) {
          warn.textContent = msg('wantedblock');
          if (lift) { lift.value = '1'; }
          if (submit) { submit.textContent = msg('lift'); }
        } else {
          warn.textContent = msg('wantednoright');
          if (submit) { submit.disabled = true; }
        }
      }
    }
    return dlg;
  }
  function openAct(act, ids) {
    if (!ids.length) { return; }
    if (act === 'export') {
      var f = $('[data-pe-export-form]');
      if (f) { f.querySelector('input[name="ids"]').value = ids.join(','); f.submit(); }
      return;
    }
    var dlg = configureDialog(act, ids);
    openDialog(dlg);
  }

  function postForm(form, onDone) {
    var data = new FormData(form);
    var btn = form.querySelector('button[type="submit"]');
    if (btn) { btn.disabled = true; }
    fetch(form.getAttribute('action'), {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch', 'X-CSRF-Token': csrfToken() }
    }).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: msg('error') }; });
    }).then(function (j) {
      if (btn) { btn.disabled = false; }
      onDone(j || { ok: false, error: msg('error') });
    }).catch(function () {
      if (btn) { btn.disabled = false; }
      onDone({ ok: false, error: msg('error') });
    });
  }

  $$('[data-pe-form]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var act = form.getAttribute('data-pe-form');
      var dlg = form.closest('dialog');
      var err = form.querySelector('.pe-dlg__error');
      postForm(form, function (j) {
        if (j.ok) {
          if (act === 'close' && j.done && j.done.length) {
            session('set', UNDO_KEY, JSON.stringify({ ids: j.done, at: Date.now() }));
          }
          closeDialog(dlg);
          window.location.reload();
          return;
        }
        var text = j.error || msg('error');
        if (j.results) {
          for (var i = 0; i < j.results.length; i++) {
            if (!j.results[i].ok && j.results[i].error) { text = j.results[i].error; break; }
          }
        }
        if (err) { err.textContent = text; err.hidden = false; }
      });
    });
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-pe-act]') : null;
    if (!btn || btn.disabled) { return; }
    var ids = (btn.getAttribute('data-ids') || '').split(',').filter(Boolean);
    if (!ids.length && root) { ids = selectedIds(); }
    e.preventDefault();
    var more = btn.closest('details');
    if (more) { more.open = false; }
    openAct(btn.getAttribute('data-pe-act'), ids);
  });
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-pe-dialog]') : null;
    if (!btn) { return; }
    openDialog(document.getElementById('pe-dlg-' + btn.getAttribute('data-pe-dialog')));
  });

  /* ------------------------------------------------------------------ Annulation de clôture (15 s) */
  (function undo() {
    var bar = $('[data-pe-undo]');
    var raw = session('get', UNDO_KEY);
    if (!bar || !raw) { return; }
    var info;
    try { info = JSON.parse(raw); } catch (e) { session('del', UNDO_KEY); return; }
    var left = Math.ceil((UNDO_MS - (Date.now() - info.at)) / 1000);
    if (!info.ids || left <= 0) { session('del', UNDO_KEY); return; }
    var text = $('[data-pe-undo-text]', bar);
    var btn = $('[data-pe-undo-btn]', bar);
    text.textContent = msg('closed', info.ids.length);
    bar.hidden = false;
    var timer = window.setInterval(function () {
      left = Math.ceil((UNDO_MS - (Date.now() - info.at)) / 1000);
      if (left <= 0) { window.clearInterval(timer); bar.hidden = true; session('del', UNDO_KEY); return; }
      btn.textContent = msg('undo', left);
    }, 250);
    btn.textContent = msg('undo', left);
    btn.addEventListener('click', function () {
      window.clearInterval(timer);
      var fd = new FormData();
      fd.append('_csrf_token', csrfToken());
      fd.append('action', 'reopen');
      fd.append('ids', info.ids.join(','));
      fd.append('reason', '');
      btn.disabled = true;
      fetch(batchUrl(), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch', 'X-CSRF-Token': csrfToken() } })
        .then(function () { session('del', UNDO_KEY); text.textContent = msg('undone'); window.location.reload(); })
        .catch(function () { btn.disabled = false; text.textContent = msg('error'); });
    });
  })();

  if (!root) { return; }

  /* ------------------------------------------------------------------ Sélection */
  var selbar = $('[data-pe-selbar]');
  function visibleItems() {
    return $$('[data-pe-item]').filter(function (el) { return el.offsetParent !== null; });
  }
  function selectedIds() {
    var seen = {};
    $$('[data-pe-select]:checked').forEach(function (c) { seen[c.value] = true; });
    return Object.keys(seen);
  }
  function syncSelection(changed) {
    var ids = selectedIds();
    if (changed) {
      $$('[data-pe-select][value="' + changed.value + '"]').forEach(function (c) { c.checked = changed.checked; });
      ids = selectedIds();
    }
    $$('[data-pe-item]').forEach(function (el) { el.classList.toggle('is-selected', ids.indexOf(el.getAttribute('data-id')) !== -1); });
    if (selbar) {
      selbar.hidden = ids.length === 0;
      var c = $('[data-pe-selcount]', selbar);
      if (c) { c.textContent = msg('selected', ids.length); }
    }
  }
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (t.matches && t.matches('[data-pe-select]')) { syncSelection(t); }
    if (t.matches && t.matches('[data-pe-selall]')) {
      $$('[data-pe-select]').forEach(function (c) { if (c.offsetParent !== null) { c.checked = t.checked; } });
      syncSelection(null);
    }
    if (t.matches && t.matches('[data-pe-auto]')) {
      var form = t.closest('form');
      if (form) { if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); } }
    }
  });
  var clear = $('[data-pe-selclear]');
  if (clear) {
    clear.addEventListener('click', function () {
      $$('[data-pe-select], [data-pe-selall]').forEach(function (c) { c.checked = false; });
      syncSelection(null);
    });
  }

  /* ------------------------------------------------------------------ Vue tableau / cartes */
  function setView(v, remember) {
    root.setAttribute('data-view', v);
    $$('[data-pe-view]').forEach(function (a) {
      if (a.getAttribute('data-pe-view') === v) { a.setAttribute('aria-current', 'true'); } else { a.removeAttribute('aria-current'); }
    });
    if (remember) { store('set', VIEW_KEY, v); }
    try {
      var u = new URL(window.location.href);
      u.searchParams.set('vue', v);
      window.history.replaceState(null, '', u.toString());
    } catch (e) { /* ancien navigateur */ }
    $$('.pe-tabs a, .pe-pager a').forEach(function (a) {
      try { var u2 = new URL(a.href); u2.searchParams.set('vue', v); a.href = u2.toString(); } catch (e) { /* sans effet */ }
    });
  }
  $$('[data-pe-view]').forEach(function (a) {
    a.addEventListener('click', function (e) { e.preventDefault(); setView(a.getAttribute('data-pe-view'), true); });
  });
  (function initView() {
    var hasParam = /[?&]vue=/.test(window.location.search);
    var saved = store('get', VIEW_KEY);
    if (!hasParam && (saved === 'cartes' || saved === 'tableau') && saved !== root.getAttribute('data-view')) { setView(saved, false); }
  })();

  /* ------------------------------------------------------------------ Tiroir d'aperçu */
  var drawer = $('#pe-drawer');
  var scrim = $('.pe-scrim');
  var drawerBody = $('[data-pe-drawer-body]');
  var drawerOpener = null;
  var currentId = 0;

  function setActive(el) {
    $$('.is-active[data-pe-item]').forEach(function (x) { x.classList.remove('is-active'); });
    if (el) { el.classList.add('is-active'); }
  }
  function openDrawer(id, focusSign) {
    if (!drawer || !id) { return; }
    drawerOpener = document.activeElement;
    currentId = id;
    var url = root.getAttribute('data-drawer-url').replace('{id}', id);
    drawer.hidden = false;
    if (scrim) { scrim.hidden = false; }
    drawerBody.setAttribute('aria-busy', 'true');
    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        drawerBody.innerHTML = html;
        drawerBody.removeAttribute('aria-busy');
        var title = $('#pe-dr-title', drawerBody);
        if (title) { drawer.setAttribute('aria-labelledby', 'pe-dr-title'); }
        bindDrawerTabs();
        var visible = visibleItems().filter(function (el) { return el.getAttribute('data-id') === String(id); })[0];
        setActive(visible || null);
        if (focusSign) {
          var s = $('#pe-dr-sign', drawerBody);
          if (s) { s.setAttribute('tabindex', '-1'); s.focus(); return; }
        }
        drawer.focus();
      })
      .catch(function () { drawerBody.textContent = msg('error'); });
    try {
      var u = new URL(window.location.href);
      u.searchParams.set('open', id);
      window.history.replaceState(null, '', u.toString());
    } catch (e) { /* ancien navigateur */ }
  }
  function closeDrawer() {
    if (!drawer || drawer.hidden) { return; }
    drawer.hidden = true;
    if (scrim) { scrim.hidden = true; }
    drawerBody.innerHTML = '';
    currentId = 0;
    try {
      var u = new URL(window.location.href);
      u.searchParams.delete('open');
      window.history.replaceState(null, '', u.toString());
    } catch (e) { /* ancien navigateur */ }
    if (drawerOpener && drawerOpener.focus && document.contains(drawerOpener)) { drawerOpener.focus(); }
  }
  function bindDrawerTabs() {
    var tabs = $$('[data-pe-tab]', drawerBody);
    function select(t) {
      tabs.forEach(function (x) {
        var on = x === t;
        x.setAttribute('aria-selected', on ? 'true' : 'false');
        x.tabIndex = on ? 0 : -1;
        var panel = document.getElementById(x.getAttribute('aria-controls'));
        if (panel) { panel.hidden = !on; }
      });
      t.focus();
    }
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { select(t); });
      t.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight') { e.preventDefault(); select(tabs[(i + 1) % tabs.length]); }
        if (e.key === 'ArrowLeft') { e.preventDefault(); select(tabs[(i + tabs.length - 1) % tabs.length]); }
      });
    });
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-pe-drawer-close]')) { closeDrawer(); return; }
    var op = e.target.closest ? e.target.closest('[data-pe-open]') : null;
    if (op) {
      if (op.tagName === 'A' && (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1)) { return; }
      e.preventDefault();
      var more = op.closest('details');
      if (more) { more.open = false; }
      openDrawer(op.getAttribute('data-pe-open'), op.getAttribute('data-pe-focus') === 'sign');
      return;
    }
    var copy = e.target.closest ? e.target.closest('[data-pe-copy]') : null;
    if (copy) {
      var url = new URL(copy.getAttribute('data-pe-copy'), window.location.href).toString();
      if (navigator.clipboard) { navigator.clipboard.writeText(url).catch(function () {}); }
      var det = copy.closest('details');
      if (det) { det.open = false; }
      return;
    }
    // Clic sur une ligne ou une carte (hors liens, boutons et cases) : aperçu.
    var item = e.target.closest ? e.target.closest('[data-pe-item]') : null;
    if (item && !e.target.closest('a, button, label, input, summary, select, textarea, details')) {
      openDrawer(item.getAttribute('data-id'), false);
    }
    $$('details.pe-more[open]').forEach(function (d) { if (!d.contains(e.target)) { d.open = false; } });
  });
  if (drawer) {
    drawer.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab') { return; }
      var f = $$('a[href], button:not([disabled]), [tabindex="0"], summary', drawer).filter(function (x) { return x.offsetParent !== null; });
      if (!f.length) { return; }
      var first = f[0];
      var last = f[f.length - 1];
      if (e.shiftKey && (document.activeElement === first || document.activeElement === drawer)) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
  }
  var openFromUrl = parseInt(root.getAttribute('data-open') || '0', 10);
  if (openFromUrl > 0) { openDrawer(openFromUrl, false); }

  /* ------------------------------------------------------------------ Raccourcis clavier */
  var keysOn = store('get', KEYS_KEY) !== '0';
  var toggle = $('[data-pe-keys-toggle]');
  if (toggle) {
    toggle.checked = keysOn;
    toggle.addEventListener('change', function () { keysOn = toggle.checked; store('set', KEYS_KEY, keysOn ? '1' : '0'); });
  }
  var gPending = 0;
  function activeItem() {
    return visibleItems().filter(function (el) { return el.classList.contains('is-active'); })[0] || null;
  }
  function move(delta) {
    var list = visibleItems();
    if (!list.length) { return; }
    var cur = list.indexOf(activeItem());
    var next = cur === -1 ? (delta > 0 ? 0 : list.length - 1) : Math.max(0, Math.min(list.length - 1, cur + delta));
    setActive(list[next]);
    list[next].scrollIntoView({ block: 'nearest' });
  }
  function targetIds() {
    var sel = selectedIds();
    if (sel.length) { return sel; }
    var a = activeItem();
    return a ? [a.getAttribute('data-id')] : [];
  }
  function typing(t) {
    return t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable);
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      var dlg = $('dialog[open]');
      if (!dlg && drawer && !drawer.hidden) { closeDrawer(); }
      return;
    }
    if (!keysOn || e.ctrlKey || e.metaKey || e.altKey || typing(e.target) || $('dialog[open]')) { return; }
    var k = e.key;
    if (gPending && Date.now() - gPending < 1500) {
      gPending = 0;
      if (k === 'p') { e.preventDefault(); setView('tableau', true); return; }
      if (k === 'f') { e.preventDefault(); setView('cartes', true); return; }
    }
    gPending = 0;
    var a = activeItem();
    var ids = targetIds();
    switch (k) {
      case 'j': e.preventDefault(); move(1); break;
      case 'k': e.preventDefault(); move(-1); break;
      case 'Enter':
        if (a && (document.activeElement === document.body || document.activeElement === a)) { e.preventDefault(); openDrawer(a.getAttribute('data-id'), false); }
        break;
      case 'o': if (a) { window.location.href = a.getAttribute('data-fiche'); } break;
      case 'x':
        if (a) { var c = $('[data-pe-select]', a); if (c) { c.checked = !c.checked; syncSelection(c); } }
        break;
      case 'c': if (ids.length && root.getAttribute('data-can-act') === '1') { e.preventDefault(); openAct('classify', ids); } break;
      case 'l':
        if (ids.length && root.getAttribute('data-can-act') === '1') { e.preventDefault(); openAct(a && ids.length === 1 && a.getAttribute('data-locked') === '1' ? 'unlock' : 'lock', ids); }
        break;
      case 'e':
        if (ids.length && root.getAttribute('data-can-act') === '1') { e.preventDefault(); openAct(a && ids.length === 1 && a.getAttribute('data-closed') === '1' ? 'reopen' : 'close', ids); }
        break;
      case 'a':
        if (a) { var w = $('[data-pe-wanted]', a); if (w && w.href) { window.location.href = w.href; } }
        break;
      case 's': if (a) { e.preventDefault(); openDrawer(a.getAttribute('data-id'), true); } break;
      case '/': e.preventDefault(); var s = $('[data-pe-search]'); if (s) { s.focus(); s.select(); } break;
      case 'g': gPending = Date.now(); break;
      case '?': e.preventDefault(); openDialog(document.getElementById('pe-dlg-help')); break;
      default: break;
    }
  });
})();
