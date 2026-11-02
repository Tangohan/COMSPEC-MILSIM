/**
 * Doctrine › Référentiel doctrinal (views/admin/organization/doctrine_referential.php) : filtres de la liste (rail des
 * pays et organisations, rail des types, domaine, niveau, affichage, recherche), volet de détail (#ref-<clé>) et
 * confirmation des actions (attribut data-confirm, traduit à l’affichage). Sans JavaScript, chaque fiche reste lisible
 * sous sa ligne. Filtre de départ possible par l’adresse : ?pays=fr|us|nato|de|custom.
 */
(function () {
  'use strict';
  var root = document.querySelector('[data-dlib]');
  if (!root) return;

  var refs = Array.prototype.slice.call(root.querySelectorAll('[data-dlib-ref]'));
  var q = root.querySelector('[data-dlib-q]');
  var selects = Array.prototype.slice.call(root.querySelectorAll('[data-dlib-f]'));
  var rails = Array.prototype.slice.call(root.querySelectorAll('[data-dlib-rail]'));
  var stats = Array.prototype.slice.call(root.querySelectorAll('[data-dlib-stat]'));
  var countEl = root.querySelector('[data-dlib-count]');
  var noMatch = root.querySelector('[data-dlib-nomatch]');
  var rail = { pack: '', type: '' };

  function val(name) {
    for (var i = 0; i < selects.length; i++) {
      if (selects[i].getAttribute('data-dlib-f') === name) return selects[i].value;
    }
    return '';
  }

  function stateOk(el, state) {
    var s = el.getAttribute('data-state');
    if (state === 'hidden' || state === 'archived') return s === state;
    if (s !== 'visible') return false;
    if (state === 'linked') return el.getAttribute('data-linked') === '1';
    if (state === 'custom') return el.getAttribute('data-origin') === 'custom';
    return true;
  }

  function apply() {
    var term = q ? q.value.trim().toLowerCase() : '';
    var state = val('state');
    var domain = val('domain');
    var level = val('level');
    var shown = 0;
    refs.forEach(function (el) {
      var ok = stateOk(el, state);
      if (ok && rail.pack) ok = el.getAttribute('data-pack') === rail.pack;
      if (ok && rail.type) ok = el.getAttribute('data-type') === rail.type;
      if (ok && domain) ok = el.getAttribute('data-domain') === domain;
      if (ok && level) ok = el.getAttribute('data-level') === level;
      if (ok && term) ok = (el.getAttribute('data-q') || '').indexOf(term) !== -1;
      el.hidden = !ok;
      if (ok) shown++;
    });
    if (countEl) countEl.textContent = String(shown);
    if (noMatch) noMatch.hidden = shown > 0 || refs.length === 0;
    selects.forEach(function (s) { s.classList.toggle('is-set', s.value !== ''); });
    stats.forEach(function (b) {
      var on = b.getAttribute('data-dlib-stat') === state;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  function setRail(group, value) {
    rail[group] = value;
    rails.forEach(function (o) {
      var parts = (o.getAttribute('data-dlib-rail') || '').split(':');
      if (parts[0] !== group) return;
      var on = (parts[1] || '') === value;
      o.classList.toggle('is-on', on);
      o.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  rails.forEach(function (b) {
    b.addEventListener('click', function () {
      var parts = (b.getAttribute('data-dlib-rail') || '').split(':');
      setRail(parts[0], parts[1] || '');
      apply();
    });
  });
  stats.forEach(function (b) {
    b.addEventListener('click', function () {
      var key = b.getAttribute('data-dlib-stat');
      var sel = root.querySelector('[data-dlib-f="state"]');
      if (sel) sel.value = sel.value === key ? '' : key;
      apply();
    });
  });
  selects.forEach(function (s) { s.addEventListener('change', apply); });
  if (q) q.addEventListener('input', apply);
  var reset = root.querySelector('[data-dlib-reset]');
  if (reset) {
    reset.addEventListener('click', function () {
      if (q) q.value = '';
      selects.forEach(function (s) { s.value = ''; });
      setRail('pack', '');
      setRail('type', '');
      apply();
    });
  }

  /* Volet de détail */
  var drawer = root.querySelector('[data-dlib-drawer]');
  var body = root.querySelector('[data-dlib-drawer-body]');
  var current = null;
  var opener = null;

  function openRef(el, from) {
    var detail = el.querySelector('[data-dlib-detail]');
    if (!drawer || !body || !detail || typeof drawer.showModal !== 'function') return;
    if (current) current.classList.remove('is-act');
    current = el;
    opener = from || null;
    el.classList.add('is-act');
    body.innerHTML = detail.innerHTML;
    if (!drawer.open) drawer.showModal();
    requestAnimationFrame(function () { drawer.classList.add('is-on'); });
    var x = body.querySelector('[data-dlib-close]');
    if (x) x.focus();
    if (history.replaceState) history.replaceState(null, '', '#' + el.id);
  }

  if (drawer) {
    drawer.addEventListener('close', function () {
      drawer.classList.remove('is-on');
      if (current) current.classList.remove('is-act');
      current = null;
      if (history.replaceState && /^#ref-/.test(location.hash)) history.replaceState(null, '', location.pathname + location.search);
      if (opener && document.contains(opener)) opener.focus();
    });
    drawer.addEventListener('click', function (e) {
      if (e.target === drawer || e.target.closest('[data-dlib-close]')) drawer.close();
    });
  }

  root.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-dlib-open]');
    if (btn) {
      var row = btn.closest('[data-dlib-ref]');
      if (row) openRef(row, btn);
      return;
    }
    var row2 = e.target.closest('[data-dlib-ref]');
    if (row2 && !e.target.closest('a, button, form, [data-dlib-detail]')) openRef(row2, row2.querySelector('[data-dlib-open]'));
  });

  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!(f instanceof HTMLFormElement) || !f.hasAttribute('data-confirm') || !f.closest('[data-dlib]')) return;
    if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
  });

  var pack = new URLSearchParams(location.search).get('pays');
  if (pack) {
    pack = pack.replace(/[^a-z]/g, '');
    if (root.querySelector('[data-dlib-rail="pack:' + pack + '"]')) setRail('pack', pack);
  }

  apply();

  var hash = location.hash;
  if (/^#ref-[a-z0-9-]+$/.test(hash)) {
    var target = document.getElementById(hash.slice(1));
    if (target && target.hasAttribute('data-dlib-ref')) {
      if (target.hidden) {
        var sel = root.querySelector('[data-dlib-f="state"]');
        var st = target.getAttribute('data-state');
        if (sel) sel.value = st === 'visible' ? '' : st;
        setRail('pack', '');
        setRail('type', '');
        apply();
      }
      openRef(target, target.querySelector('[data-dlib-open]'));
    }
  } else if (hash === '#trames') {
    var tr = document.getElementById('trames');
    if (tr) tr.open = true;
  }
})();
