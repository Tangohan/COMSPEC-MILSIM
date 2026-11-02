/*
 * Effectifs › Emplois & fonctions › « Fonctions du site » (views/admin/effectifs_workspace/job_site_catalog.php).
 * Filtres instantanés (domaine, recherche, état), sélection multiple, interrupteurs et masquage par lot.
 * Écriture : POST fonctions/masquage (slugs[], action hide|show, jeton CSRF), réponse JSON (en-tête X-Effectifs-Fragment).
 * La page est remplacée par la navigation de l’espace Emplois : tout passe par des écouteurs posés sur document.
 * Les textes affichés viennent de la page (déjà traduits) ; le script ne change que des nombres et des états.
 */
(function () {
  'use strict';
  if (window.jobSiteCatalogLoaded) return;
  window.jobSiteCatalogLoaded = true;

  var state = new WeakMap(); // racine -> { domain, filter, q }
  var haystacks = new WeakMap();
  var lastUndo = null;
  var toastTimer = 0;

  function fold(s) {
    return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  }
  function rootOf(el) { return el && el.closest ? el.closest('[data-fs]') : null; }
  function all(root, sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); }
  function st(root) {
    var s = state.get(root);
    if (!s) {
      s = { domain: '', filter: 'toutes', q: '' };
      state.set(root, s);
    }
    return s;
  }
  function setText(root, sel, value) {
    all(root, sel).forEach(function (el) { el.textContent = String(value); });
  }

  function haystack(card) {
    var h = haystacks.get(card);
    if (h === undefined) {
      var group = card.closest('[data-fs-group]');
      h = fold([
        card.querySelector('[data-fs-name]') ? card.querySelector('[data-fs-name]').textContent : '',
        card.querySelector('[data-fs-abbr]') ? card.querySelector('[data-fs-abbr]').textContent : '',
        card.querySelector('[data-fs-sub]') ? card.querySelector('[data-fs-sub]').textContent : '',
        group && group.querySelector('[data-fs-gname]') ? group.querySelector('[data-fs-gname]').textContent : ''
      ].join(' '));
      haystacks.set(card, h);
    }
    return h;
  }

  function matchesFilter(card, filter) {
    var hidden = card.dataset.hidden === '1';
    var held = Number(card.dataset.holders) > 0;
    switch (filter) {
      case 'visibles': return !hidden;
      case 'masquees': return hidden;
      case 'occupees': return held;
      case 'inutilisees': return !held;
      default: return true;
    }
  }

  /** Recalcule chiffres, filtres et visibilité des cartes. */
  function render(root) {
    var s = st(root);
    var words = fold(s.q).split(/\s+/).filter(Boolean);
    var cards = all(root, '[data-fs-card]');
    var totals = { toutes: 0, visibles: 0, masquees: 0, occupees: 0, inutilisees: 0 };
    var perDomain = {};
    var shown = 0;
    cards.forEach(function (card) {
      var d = card.dataset.domain;
      var hidden = card.dataset.hidden === '1';
      var held = Number(card.dataset.holders) > 0;
      perDomain[d] = perDomain[d] || { total: 0, hidden: 0 };
      perDomain[d].total++;
      if (hidden) perDomain[d].hidden++;
      var inScope = (!s.domain || s.domain === d) && words.every(function (w) { return haystack(card).indexOf(w) !== -1; });
      if (inScope) {
        totals.toutes++;
        totals[hidden ? 'masquees' : 'visibles']++;
        totals[held ? 'occupees' : 'inutilisees']++;
      }
      var visible = inScope && matchesFilter(card, s.filter);
      card.hidden = !visible;
      if (visible) shown++;
      var warn = card.querySelector('[data-fs-warn]');
      var pick = card.querySelector('[data-fs-pick]');
      if (warn) warn.hidden = !(hidden || (pick && pick.checked));
    });
    Object.keys(totals).forEach(function (k) { setText(root, '[data-fs-fn="' + k + '"]', totals[k]); });

    // Chiffres globaux (indépendants des filtres).
    var g = { toutes: cards.length, visibles: 0, masquees: 0, occupees: 0 };
    cards.forEach(function (c) {
      g[c.dataset.hidden === '1' ? 'masquees' : 'visibles']++;
      if (Number(c.dataset.holders) > 0) g.occupees++;
    });
    Object.keys(g).forEach(function (k) { setText(root, '[data-fs-stat="' + k + '"]', g[k]); });
    all(root, '[data-fs-set]').forEach(function (b) {
      b.classList.toggle('is-on', b.dataset.fsSet === s.filter && s.filter !== 'toutes');
      b.setAttribute('aria-pressed', String(b.dataset.fsSet === s.filter));
    });
    Object.keys(perDomain).forEach(function (d) {
      setText(root, '[data-fs-dh="' + d + '"]', perDomain[d].hidden);
      setText(root, '[data-fs-gh="' + d + '"]', perDomain[d].hidden);
      all(root, '[data-fs-dh-wrap="' + d + '"]').forEach(function (el) { el.classList.toggle('is-zero', perDomain[d].hidden === 0); });
      var btn = root.querySelector('[data-fs-hide-domain="' + d + '"]');
      if (btn) btn.disabled = perDomain[d].hidden >= perDomain[d].total;
    });

    all(root, '[data-fs-group]').forEach(function (group) {
      group.hidden = !group.querySelector('[data-fs-card]:not([hidden])');
    });
    all(root, '[data-fs-filter]').forEach(function (b) {
      var on = b.dataset.fsFilter === s.filter;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', String(on));
    });
    all(root, '[data-fs-domain]').forEach(function (a) {
      var on = a.dataset.fsDomain === s.domain;
      a.classList.toggle('is-on', on);
      if (on) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
    });
    var label = root.querySelector('[data-fs-dom-label]');
    if (label) {
      var link = s.domain ? root.querySelector('[data-fs-domain="' + s.domain + '"] .fs-rail__name') : null;
      label.hidden = !link;
      setText(root, '[data-fs-dom-name]', link ? link.textContent : '');
    }
    setText(root, '[data-fs-shown]', shown);
    var empty = root.querySelector('[data-fs-empty]');
    if (empty) empty.hidden = shown > 0;
    selection(root);
  }

  /** Barre d’actions : nombre sélectionné, à masquer, à réafficher. */
  function selection(root) {
    var picked = all(root, '[data-fs-pick]:checked');
    var toHide = 0;
    var toShow = 0;
    all(root, '[data-fs-card]').forEach(function (card) {
      var p = card.querySelector('[data-fs-pick]');
      card.classList.toggle('is-picked', !!(p && p.checked));
    });
    picked.forEach(function (p) {
      if (p.closest('[data-fs-card]').dataset.hidden === '1') toShow++; else toHide++;
    });
    setText(root, '[data-fs-sel]', picked.length);
    setText(root, '[data-fs-bn="hide"]', toHide);
    setText(root, '[data-fs-bn="show"]', toShow);
    var bar = root.querySelector('[data-fs-bar]');
    if (bar) bar.classList.toggle('is-on', picked.length > 0);
    var bh = root.querySelector('[data-fs-bulk="hide"]');
    var bs = root.querySelector('[data-fs-bulk="show"]');
    if (bh) bh.disabled = toHide === 0;
    if (bs) bs.disabled = toShow === 0;
    all(root, '[data-fs-pick-group]').forEach(function (box) {
      var boxes = all(root, '[data-fs-card][data-domain="' + box.dataset.fsPickGroup + '"]:not([hidden]) [data-fs-pick]');
      var n = boxes.filter(function (b) { return b.checked; }).length;
      box.checked = boxes.length > 0 && n === boxes.length;
      box.indeterminate = n > 0 && n < boxes.length;
    });
  }

  function init(root) {
    if (root.classList.contains('is-js')) return;
    root.classList.add('is-js');
    var s = st(root);
    s.filter = root.dataset.fsInitial || 'toutes';
    var q = root.querySelector('[data-fs-q]');
    s.q = q ? q.value : '';
    render(root);
  }
  function initAll() { Array.prototype.forEach.call(document.querySelectorAll('[data-fs]:not(.is-js)'), init); }

  function toast(root, message, isError, undo) {
    var box = root.querySelector('[data-fs-toast]');
    if (!box) return;
    window.clearTimeout(toastTimer);
    box.classList.toggle('is-error', !!isError);
    box.setAttribute('role', isError ? 'alert' : 'status');
    box.querySelector('[data-fs-toast-text]').textContent = message;
    var u = box.querySelector('[data-fs-undo]');
    lastUndo = undo || null;
    if (u) u.hidden = !undo;
    box.hidden = false;
    toastTimer = window.setTimeout(function () { box.hidden = true; lastUndo = null; }, isError ? 9000 : 7000);
  }

  function cardsBySlugs(root, slugs) {
    return all(root, '[data-fs-card]').filter(function (c) { return slugs.indexOf(c.dataset.slug) !== -1; });
  }

  /** Envoie le changement ; met la page à jour seulement si le serveur confirme. */
  function send(root, action, slugs, opts) {
    opts = opts || {};
    var form = root.querySelector('[data-fs-form]');
    if (!form || !slugs.length || root.getAttribute('aria-busy') === 'true') return;
    var body = new FormData();
    var token = form.querySelector('input[name="_csrf_token"]');
    body.append('_csrf_token', token ? token.value : '');
    body.append('action', action);
    slugs.forEach(function (slug) { body.append('slugs[]', slug); });
    var controls = all(root, '[data-fs-switch], [data-fs-bulk], [data-fs-hide-domain], [data-fs-dlg-ok]');
    root.setAttribute('aria-busy', 'true');
    controls.forEach(function (c) { c.disabled = true; });
    fetch(form.action, {
      method: 'POST', credentials: 'same-origin', body: body,
      headers: { 'X-Effectifs-Fragment': 'jobs', 'Accept': 'application/json' }
    }).then(function (response) {
      return response.json().catch(function () { return { ok: false }; }).then(function (data) { return { response: response, data: data }; });
    }).then(function (r) {
      if (!r.response.ok || !r.data || r.data.ok !== true) {
        throw new Error((r.data && r.data.message) || 'Le changement n’a pas été enregistré. Réessayez.');
      }
      var cards = cardsBySlugs(root, slugs);
      cards.forEach(function (card) {
        var hidden = action === 'hide';
        card.dataset.hidden = hidden ? '1' : '0';
        card.classList.toggle('is-hidden', hidden);
        var sw = card.querySelector('[data-fs-switch]');
        if (sw) sw.setAttribute('aria-checked', String(!hidden));
        var pick = card.querySelector('[data-fs-pick]');
        if (pick) pick.checked = false;
        card.classList.remove('is-flash');
        void card.offsetWidth;
        card.classList.add('is-flash');
      });
      render(root);
      var undo = opts.noUndo ? null : { action: action === 'hide' ? 'show' : 'hide', slugs: slugs.slice() };
      toast(root, r.data.message || 'Modifications enregistrées.', false, undo);
      if (opts.focus && document.contains(opts.focus)) opts.focus.focus();
    }).catch(function (error) {
      toast(root, (error && error.message) || 'La réponse n’a pas pu être reçue. Réessayez.', true);
    }).then(function () {
      root.removeAttribute('aria-busy');
      controls.forEach(function (c) { if (c.isConnected) c.disabled = false; });
      selection(root);
      render(root);
    });
  }

  /** Confirmation avant de masquer, avec la liste des fonctions occupées. */
  function confirmHide(root, slugs, domainId, focusBack) {
    var dlg = root.querySelector('[data-fs-dialog]');
    var cards = cardsBySlugs(root, slugs);
    var held = cards.filter(function (c) { return Number(c.dataset.holders) > 0; });
    if (!dlg || typeof dlg.showModal !== 'function') {
      send(root, 'hide', slugs, { focus: focusBack });
      return;
    }
    all(dlg, '[data-fs-dlg-mode]').forEach(function (el) {
      el.hidden = el.dataset.fsDlgMode !== (domainId ? 'domain' : 'hide');
    });
    setText(dlg, '[data-fs-dlg-n]', cards.length);
    setText(dlg, '[data-fs-dlg-held-n]', held.length);
    var dn = domainId ? root.querySelector('[data-fs-group="' + domainId + '"] [data-fs-gname]') : null;
    setText(dlg, '[data-fs-dlg-domain]', dn ? dn.textContent : '');
    dlg.querySelector('[data-fs-dlg-held]').hidden = held.length === 0;
    var list = dlg.querySelector('[data-fs-dlg-list]');
    list.textContent = '';
    held.slice(0, 30).forEach(function (c) {
      var li = document.createElement('li');
      var name = document.createElement('span');
      name.textContent = c.querySelector('[data-fs-name]').textContent;
      var n = document.createElement('b');
      n.textContent = c.dataset.holders;
      li.appendChild(name);
      li.appendChild(document.createTextNode(' · '));
      li.appendChild(n);
      li.appendChild(document.createTextNode(' '));
      var unit = c.querySelector('.fs-held > span');
      li.appendChild(document.createTextNode(unit ? unit.textContent : ''));
      list.appendChild(li);
    });
    dlg._fs = { slugs: slugs, focus: focusBack };
    dlg.showModal();
    var ok = dlg.querySelector('[data-fs-dlg-ok]');
    if (ok) ok.focus();
  }

  function cardSlug(el) { var c = el.closest('[data-fs-card]'); return c ? c.dataset.slug : ''; }

  document.addEventListener('click', function (e) {
    var root = rootOf(e.target);
    if (!root) return;
    init(root);
    var s = st(root);
    var t;
    if ((t = e.target.closest('[data-fs-domain]'))) {
      e.preventDefault();
      s.domain = t.dataset.fsDomain;
      render(root);
      var main = root.querySelector('.fs-main');
      if (main && main.getBoundingClientRect().top < 0) main.scrollIntoView({ block: 'start' });
      return;
    }
    if ((t = e.target.closest('[data-fs-filter]')) || (t = e.target.closest('[data-fs-set]'))) {
      s.filter = t.dataset.fsFilter || t.dataset.fsSet;
      render(root);
      return;
    }
    if (e.target.closest('[data-fs-reset]')) {
      s.domain = '';
      s.filter = 'toutes';
      s.q = '';
      var q = root.querySelector('[data-fs-q]');
      if (q) { q.value = ''; q.focus(); }
      render(root);
      return;
    }
    if (e.target.closest('[data-fs-clear]')) {
      all(root, '[data-fs-pick]:checked').forEach(function (p) { p.checked = false; });
      render(root);
      return;
    }
    if ((t = e.target.closest('[data-fs-switch]'))) {
      var slug = cardSlug(t);
      var card = t.closest('[data-fs-card]');
      if (card.dataset.hidden === '1') send(root, 'show', [slug], { focus: t });
      else if (Number(card.dataset.holders) > 0) confirmHide(root, [slug], '', t);
      else send(root, 'hide', [slug], { focus: t });
      return;
    }
    if ((t = e.target.closest('[data-fs-hide-domain]'))) {
      var d = t.dataset.fsHideDomain;
      var slugs = all(root, '[data-fs-card][data-domain="' + d + '"][data-hidden="0"]').map(function (c) { return c.dataset.slug; });
      if (slugs.length) confirmHide(root, slugs, d, t);
      return;
    }
    if (e.target.closest('[data-fs-dlg-cancel]')) {
      var dlg = e.target.closest('dialog');
      if (dlg) dlg.close();
      return;
    }
    if (e.target.closest('[data-fs-dlg-ok]')) {
      var box = e.target.closest('dialog');
      var pending = box && box._fs;
      if (box) box.close();
      if (pending) send(root, 'hide', pending.slugs, { focus: pending.focus });
      return;
    }
    if (e.target.closest('[data-fs-undo]')) {
      var u = lastUndo;
      root.querySelector('[data-fs-toast]').hidden = true;
      if (u) send(root, u.action, u.slugs, { noUndo: true });
    }
  });

  document.addEventListener('close', function (e) {
    var dlg = e.target;
    if (!dlg.matches || !dlg.matches('[data-fs-dialog]')) return;
    var f = dlg._fs && dlg._fs.focus;
    dlg._fs = null;
    if (f && document.contains(f)) f.focus();
  }, true);

  document.addEventListener('input', function (e) {
    if (!e.target.matches('[data-fs-q]')) return;
    var root = rootOf(e.target);
    init(root);
    st(root).q = e.target.value;
    render(root);
  });

  document.addEventListener('change', function (e) {
    var root = rootOf(e.target);
    if (!root) return;
    init(root);
    if (e.target.matches('[data-fs-pick-group]')) {
      var on = e.target.checked;
      all(root, '[data-fs-card][data-domain="' + e.target.dataset.fsPickGroup + '"]:not([hidden]) [data-fs-pick]').forEach(function (p) { p.checked = on; });
      render(root);
    } else if (e.target.matches('[data-fs-pick]')) {
      render(root);
    }
  });

  // Actions groupées : sans script, le formulaire part tel quel ; avec, on confirme puis on envoie.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches || !form.matches('[data-fs-form]')) return;
    var root = rootOf(form);
    e.preventDefault();
    var action = e.submitter && e.submitter.value === 'show' ? 'show' : 'hide';
    var slugs = all(root, '[data-fs-pick]:checked').filter(function (p) {
      return (p.closest('[data-fs-card]').dataset.hidden === '1') === (action === 'show');
    }).map(function (p) { return p.value; });
    if (!slugs.length) return;
    if (action === 'hide') confirmHide(root, slugs, '', e.submitter);
    else send(root, 'show', slugs, { focus: e.submitter });
  }, true);

  document.addEventListener('keydown', function (e) {
    if (!e.target.matches || !e.target.matches('[data-fs-q]')) return;
    if (e.key === 'Enter') { e.preventDefault(); return; } // la recherche ne doit pas soumettre le masquage
    if (e.key !== 'Escape') return;
    if (e.target.value) {
      e.target.value = '';
      var root = rootOf(e.target);
      st(root).q = '';
      render(root);
    }
  });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll); else initAll();
  // L’onglet peut arriver plus tard (navigation sans rechargement de l’espace Emplois).
  new MutationObserver(function () { if (document.querySelector('[data-fs]:not(.is-js)')) initAll(); })
    .observe(document.documentElement, { childList: true, subtree: true });
})();
