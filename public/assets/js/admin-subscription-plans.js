/**
 * Administration des formules (admin/system/subscription-plans) : recherche d'un paramètre, groupes repliables du
 * tableau comparatif, onglets de la fiche, case « Illimité » des quotas, aperçu client et dépassements en direct.
 * Sans JavaScript, tout reste affiché et le formulaire s'enregistre normalement.
 */
(function () {
  'use strict';

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
  }

  function each(root, sel, fn) {
    Array.prototype.forEach.call(root.querySelectorAll(sel), fn);
  }

  /* ── Tableau comparatif ── */
  function initCompare(table) {
    var root = table.closest('.pa-panel') || document;
    var search = root.querySelector('[data-spl-search]');
    var empty = root.querySelector('[data-spl-search-empty]');

    function setGroup(tbody, open) {
      var btn = tbody.querySelector('[data-spl-toggle]');
      if (btn) { btn.setAttribute('aria-expanded', open ? 'true' : 'false'); }
      tbody.classList.toggle('is-collapsed', !open);
    }

    each(table, '[data-spl-toggle]', function (btn) {
      btn.addEventListener('click', function () {
        setGroup(btn.closest('tbody'), btn.getAttribute('aria-expanded') !== 'true');
      });
    });
    each(root, '[data-spl-expand]', function (btn) {
      btn.addEventListener('click', function () {
        var open = btn.getAttribute('data-spl-expand') === '1';
        each(table, 'tbody.spl-group', function (tb) { setGroup(tb, open); });
      });
    });

    function filter() {
      var q = norm(search ? search.value : '');
      var any = false;
      each(table, 'tbody.spl-group', function (tb) {
        var visible = 0;
        each(tb, '[data-spl-row]', function (tr) {
          var hit = q === '' || norm(tr.getAttribute('data-spl-text')).indexOf(q) !== -1;
          tr.hidden = !hit;
          if (hit) { visible++; }
        });
        tb.hidden = visible === 0;
        if (q !== '' && visible > 0) { setGroup(tb, true); }
        any = any || visible > 0;
      });
      if (empty) { empty.hidden = any; }
    }
    if (search) {
      search.addEventListener('input', filter);
      if (search.value) { filter(); }
    }
  }

  /* ── Fiche d'une formule ── */
  function initForm(form) {
    var tabs = form.querySelectorAll('[data-spl-tab]');
    var tabInput = form.querySelector('[data-spl-tab-input]');
    var search = form.querySelector('[data-spl-search]');
    var empty = form.querySelector('[data-spl-search-empty]');
    var preview = form.querySelector('[data-spl-preview]');
    var impact = form.querySelector('[data-spl-impact]');
    var previewUrl = form.getAttribute('data-preview-url');
    var groupPanels = ['general', 'apercu', 'historique'];
    form.classList.add('is-js');

    function select(name, focus) {
      Array.prototype.forEach.call(tabs, function (t) {
        var on = t.getAttribute('data-spl-tab') === name;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        if (on && focus) { t.focus(); }
      });
      each(form, '[data-spl-panel]', function (p) {
        p.classList.toggle('is-active', p.getAttribute('data-spl-panel') === name);
      });
      if (tabInput) { tabInput.value = name; }
      try {
        var u = new URL(window.location.href);
        u.searchParams.set('onglet', name);
        window.history.replaceState(null, '', u.toString());
      } catch (e) { /* adresse inchangée */ }
    }

    Array.prototype.forEach.call(tabs, function (t, i) {
      t.addEventListener('click', function () { select(t.getAttribute('data-spl-tab'), false); });
      t.addEventListener('keydown', function (ev) {
        var d = ev.key === 'ArrowDown' || ev.key === 'ArrowRight' ? 1 : (ev.key === 'ArrowUp' || ev.key === 'ArrowLeft' ? -1 : 0);
        if (!d) { return; }
        ev.preventDefault();
        var next = tabs[(i + d + tabs.length) % tabs.length];
        select(next.getAttribute('data-spl-tab'), true);
      });
    });
    select(tabInput && tabInput.value ? tabInput.value : 'members', false);

    // Recherche : tous les groupes de paramètres affichés, lignes filtrées.
    function filter() {
      var q = norm(search ? search.value : '');
      form.classList.toggle('is-searching', q !== '');
      var any = false;
      each(form, '[data-spl-panel]', function (p) {
        if (groupPanels.indexOf(p.getAttribute('data-spl-panel')) !== -1) { return; }
        var visible = 0;
        each(p, '[data-spl-row]', function (row) {
          var hit = q === '' || norm(row.getAttribute('data-spl-text')).indexOf(q) !== -1;
          row.hidden = !hit;
          if (hit) { visible++; }
        });
        p.classList.toggle('is-match', q !== '' && visible > 0);
        any = any || visible > 0;
      });
      if (empty) { empty.hidden = q === '' || any; }
    }
    if (search) { search.addEventListener('input', filter); }

    // Quotas : « Illimité » désactive le nombre (0 enregistré côté serveur).
    each(form, '[data-spl-quota]', function (wrap) {
      var num = wrap.querySelector('input[type="number"]');
      var unl = wrap.querySelector('[data-spl-unlimited]');
      if (!num || !unl) { return; }
      unl.addEventListener('change', function () {
        num.disabled = unl.checked;
        num.required = !unl.checked;
        if (!unl.checked) {
          if (num.value === '') { num.value = num.getAttribute('data-last') || num.min || '1'; }
          num.focus();
        } else if (num.value !== '') {
          num.setAttribute('data-last', num.value);
        }
      });
    });

    function copyImpact() {
      if (!impact || !preview) { return; }
      var src = preview.querySelector('[data-spl-impact-src]');
      impact.innerHTML = src ? src.innerHTML : '';
    }
    copyImpact();

    var timer = null;
    var seq = 0;
    function refresh() {
      if (!previewUrl || !window.fetch || !preview) { return; }
      var mine = ++seq;
      preview.classList.add('is-busy');
      fetch(previewUrl, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
        .then(function (html) {
          if (mine !== seq) { return; }
          preview.innerHTML = html;
          copyImpact();
        })
        .catch(function () { /* l'aperçu enregistré reste affiché */ })
        .then(function () { if (mine === seq) { preview.classList.remove('is-busy'); } });
    }
    function schedule(ev) {
      if (ev && ev.target && (ev.target === search || ev.target === tabInput)) { return; }
      clearTimeout(timer);
      timer = setTimeout(refresh, 450);
    }
    form.addEventListener('change', schedule);
    form.addEventListener('input', function (ev) {
      if (ev.target && ev.target.type === 'number') { schedule(ev); }
    });

    // Contrôle avant envoi : ouvre l'onglet du premier champ invalide.
    form.addEventListener('submit', function (ev) {
      var bad = null;
      each(form, 'input:not([disabled]), textarea', function (el) {
        if (!bad && el.willValidate && !el.checkValidity()) { bad = el; }
      });
      if (!bad) { return; }
      ev.preventDefault();
      var panel = bad.closest('[data-spl-panel]');
      if (search && search.value) { search.value = ''; filter(); }
      if (panel) { select(panel.getAttribute('data-spl-panel'), false); }
      bad.focus();
      if (bad.reportValidity) { bad.reportValidity(); }
    });
  }

  function boot() {
    each(document, '.spl-compare', initCompare);
    each(document, '[data-spl-form]', initForm);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
