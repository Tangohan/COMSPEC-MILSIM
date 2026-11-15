/*
 * Widget Cerbère du portail SSE : ouvre le tiroir, charge son contenu (fragment HTML du serveur), gère les onglets, le badge,
 * le bouton masquable et sa position au-dessus des barres collantes. Aucun texte français ici : les messages viennent de
 * gabarits <template> du serveur (traduits comme la page). État (ouvert/fermé, onglet, masqué) en localStorage dans try/catch :
 * sans stockage, tout marche, seule la mémoire du dernier état disparaît.
 */
(function () {
  'use strict';
  var root = document.querySelector('[data-cw]');
  var KEY = 'sse.cerbere';
  var REOPEN_MS = 120000;

  function load() {
    try { return JSON.parse(window.localStorage.getItem(KEY) || '{}') || {}; } catch (e) { return {}; }
  }
  function save(patch) {
    try {
      var s = load();
      Object.keys(patch).forEach(function (k) { s[k] = patch[k]; });
      window.localStorage.setItem(KEY, JSON.stringify(s));
    } catch (e) { /* stockage indisponible : la page fonctionne sans */ }
  }

  // Restauration du bouton depuis la page « Cerbères » (fonctionne même quand le bouton est masqué).
  Array.prototype.forEach.call(document.querySelectorAll('[data-cw-restore]'), function (btn) {
    btn.addEventListener('click', function () {
      save({ hidden: false });
      if (root) { root.hidden = false; }
      var done = btn.parentNode && btn.parentNode.querySelector('[data-cw-restored]');
      if (done) { done.hidden = false; }
    });
  });

  if (!root) { return; }
  // Jamais dans un cadre (aperçus de documents) : le bouton ne sert que dans la page elle-même.
  try { if (window.self !== window.top) { root.parentNode.removeChild(root); return; } } catch (e) { root.parentNode.removeChild(root); return; }

  var fab = root.querySelector('[data-cw-open]');
  var dlg = root.querySelector('dialog');
  var body = root.querySelector('[data-cw-body]');
  var badge = root.querySelector('[data-cw-badge]');
  var url = root.getAttribute('data-cw-url');
  var page = root.getAttribute('data-cw-page');
  var seq = 0;

  function tpl(name) {
    var t = root.querySelector('template[data-cw-t="' + name + '"]');
    return t ? t.innerHTML : '';
  }

  var state = load();
  root.hidden = state.hidden === true;

  // —— Position : au-dessus de la barre collante du bas (formulaires), sans jamais la masquer ——
  var BARS = '.wc-bar, .shx-actions, .idw-actions, .fn-bottom, [data-cw-lift]';
  var raf = 0;
  function lift() {
    raf = 0;
    var h = 0, vh = window.innerHeight;
    Array.prototype.forEach.call(document.querySelectorAll(BARS), function (el) {
      var r = el.getBoundingClientRect();
      if (r.height > 0 && r.width > 0 && r.top < vh && r.bottom >= vh - 4) { h = Math.max(h, vh - r.top); }
    });
    root.style.setProperty('--cw-lift', Math.min(h, Math.round(vh / 2)) + 'px');
  }
  function schedule() { if (!raf) { raf = window.requestAnimationFrame(lift); } }
  window.addEventListener('scroll', schedule, { passive: true, capture: true });
  window.addEventListener('resize', schedule);
  schedule();

  // —— Badge ——
  function setBadge(count, tone, label) {
    if (!badge) { return; }
    badge.textContent = label || '';
    badge.setAttribute('data-count', String(count));
    badge.hidden = !(count > 0);
    badge.className = 'cw-badge cw-badge--' + (tone === 'warn' || tone === 'bad' ? tone : 'neutral');
  }

  // —— Onglets ——
  function wireTabs() {
    var tabs = Array.prototype.slice.call(body.querySelectorAll('[data-cw-tab]'));
    if (!tabs.length) { return; }
    function select(id, focus) {
      var found = false;
      tabs.forEach(function (t) {
        var on = t.getAttribute('data-cw-tab') === id;
        found = found || on;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        if (on && focus) { t.focus(); }
      });
      if (!found) { return false; }
      Array.prototype.forEach.call(body.querySelectorAll('[data-cw-pane]'), function (p) { p.hidden = p.getAttribute('data-cw-pane') !== id; });
      save({ tab: id });
      return true;
    }
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { select(t.getAttribute('data-cw-tab'), false); });
      t.addEventListener('keydown', function (e) {
        var n = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : e.key === 'Home' ? 0 : e.key === 'End' ? tabs.length - 1 : -1;
        if (n < 0) { return; }
        e.preventDefault();
        select(tabs[(n + tabs.length) % tabs.length].getAttribute('data-cw-tab'), true);
      });
    });
    var want = load().tab;
    if (want) { select(want, false); }
  }

  // —— Chargement du contenu à l'ouverture ——
  function fetchPanel() {
    var mine = ++seq;
    body.setAttribute('aria-busy', 'true');
    body.innerHTML = tpl('loading');
    var ctl = typeof AbortController === 'function' ? new AbortController() : null;
    var timer = window.setTimeout(function () { if (ctl) { ctl.abort(); } }, 15000);
    fetch(url + '?page=' + encodeURIComponent(page), { credentials: 'same-origin', headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, signal: ctl ? ctl.signal : undefined })
      .then(function (r) { return r.text().then(function (t) { return { ok: r.ok, status: r.status, text: t }; }); })
      .then(function (res) {
        window.clearTimeout(timer);
        if (mine !== seq) { return; }
        if (!res.text || res.text.indexOf('cw-content') < 0) { throw new Error('vide'); }
        body.innerHTML = res.text;
        body.setAttribute('aria-busy', 'false');
        var c = body.querySelector('.cw-content');
        if (c && res.ok) { setBadge(parseInt(c.getAttribute('data-cw-count') || '0', 10) || 0, c.getAttribute('data-cw-tone') || 'neutral', c.getAttribute('data-cw-label') || ''); }
        wireTabs();
        Array.prototype.forEach.call(body.querySelectorAll('form'), function (f) {
          f.addEventListener('submit', function () { save({ reopen: Date.now() }); });
        });
      })
      .catch(function () {
        window.clearTimeout(timer);
        if (mine !== seq) { return; }
        body.setAttribute('aria-busy', 'false');
        body.innerHTML = tpl('error');
        var retry = body.querySelector('[data-cw-retry]');
        if (retry) { retry.addEventListener('click', fetchPanel); }
      });
  }

  function open() {
    if (!dlg.open) {
      if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
    }
    fab.setAttribute('aria-expanded', 'true');
    save({ open: true });
    var h = dlg.querySelector('#cw-title');
    if (h) { h.focus({ preventScroll: true }); }
    fetchPanel();
  }
  function close() {
    if (dlg.open) { if (typeof dlg.close === 'function') { dlg.close(); } else { dlg.removeAttribute('open'); } }
  }

  fab.addEventListener('click', open);
  root.querySelector('[data-cw-close]').addEventListener('click', close);
  dlg.addEventListener('close', function () { fab.setAttribute('aria-expanded', 'false'); save({ open: false }); try { fab.focus({ preventScroll: true }); } catch (e) { /* rien */ } });
  // Clic à l'extérieur du tiroir (sur le fond) : fermeture.
  dlg.addEventListener('click', function (e) {
    if (e.target !== dlg) { return; }
    var r = dlg.getBoundingClientRect();
    if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) { close(); }
  });
  root.querySelector('[data-cw-hide]').addEventListener('click', function () {
    save({ hidden: true });
    close();
    root.hidden = true;
  });

  // Après une décision prise dans le tiroir (formulaire envoyé, page rechargée) : le tiroir se rouvre une fois.
  var s = load();
  if (s.reopen && Date.now() - s.reopen < REOPEN_MS && !root.hidden) { save({ reopen: 0 }); open(); } else if (s.reopen) { save({ reopen: 0 }); }
})();
