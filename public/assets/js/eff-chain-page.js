/**
 * Effectifs — Chaîne de commandement : onglets de la page, ancres et filtres de l’organigramme (views/admin/effectifs_workspace/chaine.php).
 *  - Onglets ARIA (tablist) : flèches, Début, Fin ; l’adresse garde ?vue=… ; sans script, chaque onglet est un lien.
 *  - Ancres : #eff-chain-setup, #eff-chain-people-panel, #eff-chain-proposals ouvrent l’onglet qui les contient ;
 *    #eff-adequacy fait défiler jusqu’au résumé de l’adéquation.
 *  - Filtres par unité (la branche reste visible), échelon et état : les postes qui ne correspondent pas s’estompent.
 *  - La zone vivante est remplacée après chaque enregistrement (eff-chain.js, évènement « chain:swapped ») : l’onglet
 *    ouvert et les filtres sont réappliqués.
 * Sur la page Adéquation, garde l’état courant de la sous-navigation aligné sur l’onglet ouvert.
 */
(function () {
  'use strict';

  var subnav = document.querySelector('[data-chn-subnav]');
  // ——— Adéquation : « Adéquation » ou « Échelons attendus » selon l’onglet ouvert ———
  var adqTabs = document.querySelectorAll('[data-adqp-tab]');
  if (subnav && adqTabs.length) {
    var syncSubnav = function () {
      var open = document.querySelector('[data-adqp-tab][aria-selected="true"]');
      var key = open && open.getAttribute('data-adqp-tab') === 'echelons' ? 'echelons' : 'adequation';
      subnav.querySelectorAll('[data-chn-subnav]').forEach(function (a) {
        var on = a.getAttribute('data-chn-subnav') === key;
        a.classList.toggle('is-current', on);
        if (on) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
      });
    };
    var mo = new MutationObserver(syncSubnav);
    adqTabs.forEach(function (t) { mo.observe(t, { attributes: true, attributeFilter: ['aria-selected'] }); });
  }

  var root = document.querySelector('[data-chn]');
  if (!root) return;

  var state = { tab: '', unit: '', echelon: '', state: '' };

  function tabs() {
    return Array.prototype.slice.call(root.querySelectorAll('[data-chn-tab]'));
  }
  function currentTab() {
    var on = root.querySelector('[data-chn-tab][aria-selected="true"]');
    return on ? on.getAttribute('data-chn-tab') : 'organigramme';
  }

  /** Ouvre un onglet ; focus : place le focus sur l’onglet ; push : met l’adresse à jour. */
  function select(key, focus, push) {
    var list = tabs();
    var target = null;
    list.forEach(function (t) {
      var on = t.getAttribute('data-chn-tab') === key;
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.setAttribute('tabindex', on ? '0' : '-1');
      if (on) target = t;
    });
    if (!target) return;
    root.querySelectorAll('[data-chn-panel]').forEach(function (p) {
      p.hidden = p.getAttribute('data-chn-panel') !== key;
    });
    state.tab = key;
    if (focus) target.focus();
    if (push) {
      try {
        var u = new URL(window.location.href);
        if (key === 'organigramme') u.searchParams.delete('vue'); else u.searchParams.set('vue', key);
        u.hash = '';
        window.history.replaceState(null, '', u.toString());
      } catch (e) { /* adresse inchangée */ }
    }
  }

  root.addEventListener('click', function (e) {
    var tab = e.target.closest('[data-chn-tab]');
    if (tab && e.button === 0 && !e.metaKey && !e.ctrlKey && !e.shiftKey) {
      e.preventDefault();
      select(tab.getAttribute('data-chn-tab'), true, true);
      return;
    }
    var go = e.target.closest('[data-chn-goto]');
    if (go && e.button === 0 && !e.metaKey && !e.ctrlKey) {
      e.preventDefault();
      select(go.getAttribute('data-chn-goto'), false, true);
      var panel = root.querySelector('[data-chn-panel="' + go.getAttribute('data-chn-goto') + '"]');
      if (panel) {
        panel.scrollIntoView({ block: 'start' });
        panel.focus({ preventScroll: true });
      }
      return;
    }
    if (e.target.closest('[data-chn-reset]')) {
      state.unit = '';
      state.echelon = '';
      state.state = '';
      root.querySelectorAll('[data-chn-filter]').forEach(function (s) { s.value = ''; });
      applyFilters();
      var first = root.querySelector('[data-chn-filter]');
      if (first) first.focus();
    }
  });

  root.addEventListener('keydown', function (e) {
    var tab = e.target.closest && e.target.closest('[data-chn-tab]');
    if (!tab) return;
    var list = tabs();
    var i = list.indexOf(tab);
    var next = -1;
    if (e.key === 'ArrowRight') next = (i + 1) % list.length;
    else if (e.key === 'ArrowLeft') next = (i - 1 + list.length) % list.length;
    else if (e.key === 'Home') next = 0;
    else if (e.key === 'End') next = list.length - 1;
    else if (e.key === ' ' || e.key === 'Enter') {
      e.preventDefault();
      select(tab.getAttribute('data-chn-tab'), true, true);
      return;
    }
    if (next < 0) return;
    e.preventDefault();
    select(list[next].getAttribute('data-chn-tab'), true, true);
  });

  // ——— Ancres ———
  function fromHash() {
    var id = window.location.hash.slice(1);
    if (!id) return;
    var el = document.getElementById(id);
    if (!el || !root.contains(el)) return;
    var panel = el.closest('[data-chn-panel]');
    if (panel) select(panel.getAttribute('data-chn-panel'), false, false);
    el.scrollIntoView({ block: 'start' });
    if (el.id === 'eff-adequacy') el.focus({ preventScroll: true });
  }

  // ——— Filtres de l’organigramme ———
  function applyFilters() {
    var blocks = root.querySelectorAll('[data-chn-unit]');
    var active = state.unit !== '' || state.echelon !== '' || state.state !== '';
    var shown = 0;
    var match = 0;
    var unitPath = '';
    if (state.unit !== '') {
      var sel = root.querySelector('[data-chn-unit="' + state.unit + '"]');
      unitPath = sel ? ' ' + (sel.getAttribute('data-chn-path') || '') + ' ' : '';
    }
    blocks.forEach(function (b) {
      var path = ' ' + (b.getAttribute('data-chn-path') || '') + ' ';
      var id = b.getAttribute('data-chn-unit');
      // Unité choisie : sa branche, et les unités au-dessus d’elle pour garder le fil de la chaîne.
      var inScope = state.unit === '' || path.indexOf(' ' + state.unit + ' ') !== -1 || unitPath.indexOf(' ' + id + ' ') !== -1;
      b.classList.toggle('is-out', !inScope);
      var card = b.querySelector(':scope > [data-chn-card]');
      if (!inScope) return;
      shown++;
      var ok = (state.echelon === '' || b.getAttribute('data-chn-echelon') === state.echelon)
        && (state.state === '' || (' ' + (b.getAttribute('data-chn-state') || '') + ' ').indexOf(' ' + state.state + ' ') !== -1)
        && (state.unit === '' || path.indexOf(' ' + state.unit + ' ') !== -1);
      if (ok) match++;
      if (card) card.classList.toggle('is-dim', active && !ok);
    });
    var count = root.querySelector('[data-chn-count]');
    if (count) {
      // Modèles entiers (%1) : i18n_dom.js les reconnaît pour l’anglais.
      count.textContent = active
        ? (match > 1 ? '%1 postes correspondent'.replace('%1', match) : (match === 1 ? 'Un poste correspond' : 'Aucun poste ne correspond'))
        : (shown > 1 ? '%1 postes de chef'.replace('%1', shown) : 'Un poste de chef');
    }
    var none = root.querySelector('[data-chn-none]');
    if (none) none.hidden = !(active && match === 0);
    var reset = root.querySelector('[data-chn-reset]');
    if (reset) reset.hidden = !active;
  }
  root.addEventListener('change', function (e) {
    var f = e.target.getAttribute && e.target.getAttribute('data-chn-filter');
    if (!f) return;
    state[f] = e.target.value;
    applyFilters();
  });

  /** Après une actualisation de la zone vivante : même onglet, mêmes filtres, outils visibles. */
  function restore() {
    var tools = root.querySelector('[data-chn-tools]');
    if (tools) tools.hidden = false;
    root.querySelectorAll('[data-chn-filter]').forEach(function (s) {
      var key = s.getAttribute('data-chn-filter');
      if (s.querySelector('option[value="' + state[key] + '"]')) s.value = state[key]; else state[key] = '';
    });
    if (state.tab) select(state.tab, false, false);
    applyFilters();
  }

  root.addEventListener('chain:swapped', restore);
  root.addEventListener('chain:reveal', function (e) {
    var key = e.detail && e.detail.panel;
    if (key) select(key, false, true);
  });
  window.addEventListener('hashchange', fromHash);

  state.tab = currentTab();
  restore();
  fromHash();
})();
