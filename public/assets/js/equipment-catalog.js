/**
 * Catalogue équipement — filtres, recherche, tri, quick-view, sélecteur de collection.
 * Préserve filtres/recherche à la fermeture des modales.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-eq-catalog]');
  if (!root) return;

  var dataEl = document.getElementById('eq-catalog-data');
  var catalog = {};
  try {
    catalog = dataEl ? JSON.parse(dataEl.textContent || '{}') : {};
  } catch (e) {
    catalog = {};
  }

  var state = {
    q: '',
    collections: {},
    kinds: {},
    availNone: false,
    availIn: false,
    sort: 'name',
    openTenueId: null,
    tab: 'fiches',
  };

  // Plusieurs listes de tenues (mes tenues, communauté, favoris) : filtres, recherche et tri s'appliquent à chacune.
  var grids = Array.prototype.slice.call(root.querySelectorAll('[data-eq-grid]'));
  var cards = [];
  grids.forEach(function (g) {
    Array.prototype.forEach.call(g.querySelectorAll('[data-eq-card]'), function (c) {
      cards.push(c);
    });
  });
  var countEl = root.querySelector('[data-eq-count]');
  var searchInput = root.querySelector('[data-eq-search]');
  var kindSelect = root.querySelector('[data-eq-kind-select]');
  var sortSelect = root.querySelector('[data-eq-sort]');
  var clearBtn = root.querySelector('[data-eq-clear-filters]');
  var activeFilters = root.querySelector('[data-eq-active-filters]');
  var filtersAside = root.querySelector('.eq-hub__filters');
  var qvDialog = document.getElementById('eq-quickview');
  var colDialog = document.getElementById('eq-collection-new');
  var colEditDialog = document.getElementById('eq-collection-edit');
  var helpDialog = document.getElementById('eq-tenue-help');
  var ficheDialog = document.getElementById('eq-fiche-view');
  var ficheNewDialog = document.getElementById('eq-fiche-new');
  var dotationDialog = document.getElementById('eq-dotation-view');
  var dotationNewDialog = document.getElementById('eq-dotation-new');
  var qvDesc = root.querySelector('[data-eq-qv-desc]');
  var qvDescription = root.querySelector('[data-eq-qv-description]');
  var qvGalleryEdit = root.querySelector('[data-eq-qv-gallery-edit]');

  function parseJsonScript(id) {
    var el = document.getElementById(id);
    if (!el) return null;
    try {
      return JSON.parse(el.textContent || 'null');
    } catch (e) {
      return null;
    }
  }

  function debounce(fn, ms) {
    var t;
    return function () {
      var args = arguments;
      var ctx = this;
      clearTimeout(t);
      t = setTimeout(function () {
        fn.apply(ctx, args);
      }, ms);
    };
  }

  function selectedKeys(map) {
    return Object.keys(map).filter(function (k) {
      return map[k];
    });
  }

  function syncUrl() {
    if (!window.history || !window.history.replaceState) return;
    var params = new URLSearchParams(window.location.search);
    if (state.openTenueId) params.set('tenue', String(state.openTenueId));
    else params.delete('tenue');
    if (root.getAttribute('data-eq-space') === 'materiel') {
      if (state.tab === 'dotation') params.set('tab', state.tab);
      else params.delete('tab');
    }
    params.delete('edit_collection');
    var qs = params.toString();
    var next = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
    window.history.replaceState({}, '', next);
  }

  function setTab(tab) {
    state.tab = tab === 'dotation' ? 'dotation' : 'fiches';
    root.querySelectorAll('[data-eq-tab]').forEach(function (btn) {
      var on = btn.getAttribute('data-eq-tab') === state.tab;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    root.querySelectorAll('[data-eq-panel]').forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-eq-panel') !== state.tab;
    });
    if (filtersAside) {
      filtersAside.setAttribute('data-eq-filters-for', state.tab);
    }
    syncUrl();
  }

  function updateCount(visible) {
    if (!countEl) return;
    var total = cards.length;
    var n = typeof visible === 'number' ? visible : total;
    var label = n + ' tenue' + (n > 1 ? 's' : '');
    if (n !== total && total > 0) label += ' sur ' + total;
    countEl.textContent = label;
  }

  function hasActiveFilters() {
    return (
      state.q !== '' ||
      selectedKeys(state.collections).length > 0 ||
      selectedKeys(state.kinds).length > 0 ||
      state.availNone ||
      state.availIn
    );
  }

  // Collection choisie : en-tête (nom, visibilité, description) et export ACE de cette collection seule.
  function renderCollectionHead() {
    var keys = selectedKeys(state.collections);
    var c = null;
    if (keys.length === 1) {
      c = (catalog.collections || []).find(function (x) {
        return String(x.id) === String(keys[0]);
      });
    }
    var head = root.querySelector('[data-eq-coll-head]');
    if (head) {
      head.hidden = !c;
      if (c) {
        var t = head.querySelector('[data-eq-coll-head-title]');
        var m = head.querySelector('[data-eq-coll-head-meta]');
        var d = head.querySelector('[data-eq-coll-head-desc]');
        var n = c.wardrobe_count || 0;
        if (t) t.textContent = c.name || 'Collection';
        if (m) m.textContent = (c.visibility_label || 'Collection') + ' · ' + n + ' ' + (n > 1 ? 'tenues' : 'tenue');
        if (d) {
          d.textContent = (c.description || '').trim();
          d.hidden = !d.textContent;
        }
      }
    }
    var gridTitle = root.querySelector('[data-eq-grid-title]');
    if (gridTitle) gridTitle.textContent = c ? 'Tenues de la collection' : 'Toutes les tenues';
    var ace = root.querySelector('[data-eq-ace-export]');
    var aceLabel = root.querySelector('[data-eq-ace-label]');
    var aceBase = (catalog.urls && catalog.urls.aceExport) || '/equipment/ace-export';
    if (ace) ace.setAttribute('href', c ? aceBase + '?collection=' + encodeURIComponent(c.id) : aceBase);
    if (aceLabel) {
      aceLabel.textContent = c
        ? 'Les tenues de cette collection, prêtes à importer dans « Mes équipements » d’ACE en jeu.'
        : 'Toutes les tenues, prêtes à importer dans « Mes équipements » d’ACE en jeu.';
    }
  }

  function renderActiveFilters() {
    renderCollectionHead();
    if (!activeFilters || !clearBtn) return;
    activeFilters.innerHTML = '';
    var on = hasActiveFilters();
    activeFilters.hidden = !on;
    clearBtn.hidden = !on;
    var editWrap = root.querySelector('[data-eq-edit-collection-wrap]');
    var editLink = root.querySelector('[data-eq-edit-collection]');
    var colKeys = selectedKeys(state.collections);
    if (editWrap && editLink) {
      var owned = null;
      if (colKeys.length === 1) {
        owned = (catalog.collections || []).find(function (x) {
          return String(x.id) === String(colKeys[0]) && x.mine;
        });
      }
      if (owned) {
        editWrap.hidden = false;
        editLink.setAttribute('data-eq-edit-collection-id', String(owned.id));
      } else {
        editWrap.hidden = true;
        editLink.removeAttribute('data-eq-edit-collection-id');
      }
    }
    if (!on) return;

    function chip(label) {
      var span = document.createElement('span');
      span.className = 'eq-hub__chip';
      span.textContent = label;
      activeFilters.appendChild(span);
    }
    if (state.q) chip('« ' + state.q + ' »');
    colKeys.forEach(function (id) {
      var c = (catalog.collections || []).find(function (x) {
        return String(x.id) === String(id);
      });
      chip(c ? c.name : 'Collection ' + id);
    });
    selectedKeys(state.kinds).forEach(function (k) {
      chip((catalog.kinds && catalog.kinds[k]) || k);
    });
    if (state.availNone) chip('Sans collection');
    if (state.availIn) chip('En collection');
  }

  function cardMatches(card) {
    var q = state.q;
    if (q) {
      var hay =
        (card.getAttribute('data-name') || '') +
        ' ' +
        (card.getAttribute('data-display') || '') +
        ' ' +
        (card.getAttribute('data-collection-name') || '') +
        ' ' +
        (card.getAttribute('data-owner') || '') +
        ' ' +
        (card.getAttribute('data-items') || '');
      if (hay.indexOf(q) === -1) return false;
    }

    var cid = parseInt(card.getAttribute('data-collection') || '0', 10) || 0;
    var colKeys = selectedKeys(state.collections);
    if (colKeys.length) {
      if (colKeys.indexOf(String(cid)) === -1) return false;
    }
    if (state.availNone && state.availIn) {
      /* both = no restriction beyond collection checkboxes */
    } else if (state.availNone && cid !== 0) {
      return false;
    } else if (state.availIn && cid === 0) {
      return false;
    }

    var kindKeys = selectedKeys(state.kinds);
    if (kindKeys.length) {
      var kinds = (card.getAttribute('data-kinds') || '').split(',').filter(Boolean);
      var ok = kindKeys.some(function (k) {
        return kinds.indexOf(k) !== -1;
      });
      if (!ok) return false;
    }
    return true;
  }

  function sortCards(list) {
    var mode = state.sort;
    list.sort(function (a, b) {
      if (mode === 'date') {
        return String(b.getAttribute('data-created') || '').localeCompare(
          String(a.getAttribute('data-created') || '')
        );
      }
      if (mode === 'collection') {
        var ca = a.getAttribute('data-collection-name') || '';
        var cb = b.getAttribute('data-collection-name') || '';
        if (ca !== cb) return ca.localeCompare(cb, 'fr');
      }
      return String(a.getAttribute('data-display') || '').localeCompare(
        String(b.getAttribute('data-display') || ''),
        'fr'
      );
    });
  }

  function applyFilters() {
    var total = 0;
    grids.forEach(function (grid) {
      var matched = [];
      var all = Array.prototype.slice.call(grid.querySelectorAll('[data-eq-card]'));
      all.forEach(function (card) {
        if (cardMatches(card)) matched.push(card);
        else card.hidden = true;
      });
      sortCards(matched);
      matched.forEach(function (card) {
        card.hidden = false;
        grid.appendChild(card);
      });
      var section = grid.closest('section');
      var noResults = section ? section.querySelector('[data-eq-no-results]') : null;
      if (noResults) noResults.hidden = matched.length > 0 || all.length === 0;
      total += matched.length;
    });
    updateCount(total);
    renderActiveFilters();
    syncCollectionPressed();
    syncUrl();
  }

  function syncCollectionPressed() {
    var keys = selectedKeys(state.collections);
    root.querySelectorAll('[data-eq-filter-collection]').forEach(function (btn) {
      var id = btn.getAttribute('data-eq-filter-collection');
      btn.setAttribute('aria-pressed', keys.length === 1 && keys[0] === String(id) ? 'true' : 'false');
    });
    root.querySelectorAll('[data-eq-collection-cb]').forEach(function (cb) {
      cb.checked = !!state.collections[String(cb.value)];
    });
  }

  function setCollectionFilter(id, exclusive) {
    id = String(id);
    if (exclusive) {
      var was = !!state.collections[id] && selectedKeys(state.collections).length === 1;
      state.collections = {};
      if (!was) state.collections[id] = true;
    } else {
      state.collections[id] = !state.collections[id];
      if (!state.collections[id]) delete state.collections[id];
    }
    applyFilters();
  }

  /* ---- Image skeleton ---- */
  function bindMedia(scope) {
    (scope || root).querySelectorAll('.eq-hub__media').forEach(function (media) {
      var img = media.querySelector('img.eq-hub__img');
      if (!img) {
        media.classList.add('is-ready');
        return;
      }
      function done() {
        img.classList.add('is-loaded');
        media.classList.add('is-ready');
      }
      if (img.complete && img.naturalWidth) done();
      else {
        img.addEventListener('load', done, { once: true });
        img.addEventListener('error', done, { once: true });
      }
    });
  }

  /* ---- Dialogs ---- */
  function openDialog(dlg) {
    if (!dlg || typeof dlg.showModal !== 'function') return;
    if (!dlg.open) dlg.showModal();
  }
  function closeDialog(dlg) {
    if (dlg && dlg.open) dlg.close();
  }

  root.querySelectorAll('[data-eq-open]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      if (e && e.preventDefault) e.preventDefault();
      var which = btn.getAttribute('data-eq-open');
      if (which === 'kit-new') openDialog(document.getElementById('eq-kit-new'));
      if (which === 'collection-new') openDialog(colDialog);
      if (which === 'tenue-help') openDialog(helpDialog);
      if (which === 'fiche-new') openDialog(ficheNewDialog);
      if (which === 'dotation-new') openDialog(dotationNewDialog);
    });
  });

  root.querySelectorAll('[data-eq-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dlg = btn.closest('dialog');
      closeDialog(dlg);
    });
  });

  var kitDialog = document.getElementById('eq-kit-new');
  var genericDialog = document.getElementById('eq-generic');
  [qvDialog, colDialog, colEditDialog, helpDialog, ficheDialog, ficheNewDialog, dotationDialog, dotationNewDialog, kitDialog, genericDialog].forEach(function (dlg) {
    if (!dlg) return;
    dlg.addEventListener('click', function (e) {
      if (e.target === dlg) closeDialog(dlg);
    });
    dlg.addEventListener('close', function () {
      if (dlg === qvDialog) {
        state.openTenueId = null;
        syncUrl();
        showQvView();
      }
    });
  });

  root.querySelectorAll('[data-eq-tab]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      if (e && e.preventDefault) e.preventDefault();
      setTab(btn.getAttribute('data-eq-tab'));
    });
  });

  /* ---- Collection picker ---- */
  function updatePickCount() {
    var countEl2 = root.querySelector('[data-eq-pick-count]');
    if (!countEl2) return;
    var n = root.querySelectorAll('[data-eq-pick] input:checked').length;
    countEl2.textContent = n + ' sélectionnée' + (n > 1 ? 's' : '');
  }

  function filterPicker() {
    var qEl = root.querySelector('[data-eq-pick-search]');
    var kEl = root.querySelector('[data-eq-pick-kind]');
    var q = ((qEl && qEl.value) || '').trim().toLowerCase();
    var kind = (kEl && kEl.value) || '';
    root.querySelectorAll('[data-eq-pick]').forEach(function (pick) {
      var nameOk = !q || (pick.getAttribute('data-name') || '').indexOf(q) !== -1;
      var kinds = (pick.getAttribute('data-kinds') || '').split(',').filter(Boolean);
      var kindOk = !kind || kinds.indexOf(kind) !== -1;
      pick.hidden = !(nameOk && kindOk);
    });
  }

  root.querySelectorAll('[data-eq-pick]').forEach(function (pick) {
    var input = pick.querySelector('input[type="checkbox"]');
    function sync() {
      pick.classList.toggle('is-selected', !!(input && input.checked));
      updatePickCount();
    }
    pick.addEventListener('click', function (e) {
      if (!input) return;
      if (e.target === input) {
        sync();
        return;
      }
      e.preventDefault();
      input.checked = !input.checked;
      sync();
    });
    sync();
  });

  var pickSearch = root.querySelector('[data-eq-pick-search]');
  var pickKind = root.querySelector('[data-eq-pick-kind]');
  if (pickSearch) pickSearch.addEventListener('input', debounce(filterPicker, 120));
  if (pickKind) pickKind.addEventListener('change', filterPicker);

  /* ---- Quick view ---- */
  var qvView = root.querySelector('[data-eq-qv-view]');
  var qvEdit = root.querySelector('[data-eq-qv-edit-panel]');
  var qvTitle = root.querySelector('[data-eq-qv-title]');
  var qvHint = root.querySelector('[data-eq-qv-hint]');
  var qvItems = root.querySelector('[data-eq-qv-items]');
  var qvStage = root.querySelector('[data-eq-qv-stage]');
  var qvThumbs = root.querySelector('[data-eq-qv-thumbs]');
  var qvCollection = root.querySelector('[data-eq-qv-collection]');
  var qvEditBtn = root.querySelector('[data-eq-qv-edit]');
  var qvForm = root.querySelector('[data-eq-qv-form]');
  var qvDelete = root.querySelector('[data-eq-qv-delete]');
  var qvCollectionSelect = root.querySelector('[data-eq-qv-collection-select]');
  var qvNotes = root.querySelector('[data-eq-qv-notes]');
  var qvBack = root.querySelector('[data-eq-qv-back]');

  function showQvView() {
    if (qvView) qvView.hidden = false;
    if (qvEdit) qvEdit.hidden = true;
  }
  function showQvEdit() {
    if (qvView) qvView.hidden = true;
    if (qvEdit) qvEdit.hidden = false;
  }

  function renderGallery(urls) {
    if (!qvStage || !qvThumbs) return;
    urls = (urls || []).filter(Boolean);
    qvStage.innerHTML = '';
    qvThumbs.innerHTML = '';
    if (!urls.length) {
      qvStage.innerHTML =
        '<span class="eq-hub__ph eq-hub__ph--lg" aria-hidden="true"><svg viewBox="0 0 80 100" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M40 18c6 0 10 4 10 10v6c8 3 14 10 14 20v28H16V54c0-10 6-17 14-20v-6c0-6 4-10 10-10z" stroke="currentColor" stroke-width="2.2"/></svg></span>';
      qvThumbs.hidden = true;
      return;
    }
    var main = document.createElement('img');
    main.src = urls[0];
    main.alt = '';
    qvStage.appendChild(main);
    if (urls.length < 2) {
      qvThumbs.hidden = true;
      return;
    }
    qvThumbs.hidden = false;
    urls.forEach(function (url, i) {
      var b = document.createElement('button');
      b.type = 'button';
      if (i === 0) b.classList.add('is-active');
      var im = document.createElement('img');
      im.src = url;
      im.alt = '';
      b.appendChild(im);
      b.addEventListener('click', function () {
        main.src = url;
        qvThumbs.querySelectorAll('button').forEach(function (x) {
          x.classList.remove('is-active');
        });
        b.classList.add('is-active');
      });
      qvThumbs.appendChild(b);
    });
  }

  function renderLoadout(sections) {
    if (!qvItems) return;
    qvItems.innerHTML = '';
    sections = Array.isArray(sections) ? sections : [];
    if (!sections.length) {
      qvItems.innerHTML = '<p class="eq-hub__qv-empty">Aucun équipement listé pour cette tenue.</p>';
      return;
    }
    sections.forEach(function (sec) {
      var items = Array.isArray(sec.items) ? sec.items : [];
      if (!sec.title || !items.length) return;
      var wrap = document.createElement('div');
      wrap.className = 'eq-hub__item-group';
      var h = document.createElement('h3');
      h.textContent = sec.title;
      wrap.appendChild(h);
      var ul = document.createElement('ul');
      items.forEach(function (it) {
        var li = document.createElement('li');
        // Pastille de repli (initiale) tant que l’icône du jeu n’est pas arrivée ou si elle ne charge pas.
        var ph = document.createElement('span');
        ph.className = 'eq-hub__item-ico eq-hub__item-ico--ph';
        ph.setAttribute('aria-hidden', 'true');
        ph.textContent = ((it.name || it.class || '?').replace(/[^A-Za-z0-9À-ÿ]/g, '').charAt(0) || '?').toUpperCase();
        if (it.icon) {
          var img = document.createElement('img');
          img.className = 'eq-hub__item-ico';
          img.src = it.icon;
          img.alt = '';
          img.loading = 'lazy';
          img.width = 32;
          img.height = 32;
          img.addEventListener('error', function () {
            img.replaceWith(ph);
          });
          li.appendChild(img);
        } else {
          li.appendChild(ph);
        }
        var span = document.createElement('span');
        span.textContent = it.name || '';
        span.setAttribute('translate', 'no');
        if (it.class) span.title = it.class;
        li.appendChild(span);
        if ((it.qty || 1) > 1) {
          var em = document.createElement('em');
          em.textContent = '× ' + it.qty;
          li.appendChild(em);
        }
        // Objet vu en jeu : nombre de joueurs qui l’ont porté ces 90 derniers jours.
        if ((it.players || 0) > 0) {
          var seen = document.createElement('small');
          seen.className = 'eq-hub__item-seen';
          seen.textContent = it.players > 1 ? it.players + ' joueurs en jeu' : '1 joueur en jeu';
          li.appendChild(seen);
        }
        ul.appendChild(li);
      });
      wrap.appendChild(ul);
      qvItems.appendChild(wrap);
    });
  }

  function fillEditForm(payload) {
    var w = payload.wardrobe || {};
    var collections = payload.collections || [];
    if (qvForm) {
      qvForm.action = (payload.urls && payload.urls.update) || '';
    }
    if (qvDelete) {
      qvDelete.action = (payload.urls && payload.urls.delete) || '';
    }
    if (qvCollectionSelect) {
      qvCollectionSelect.innerHTML = '<option value="0">Sans collection</option>';
      collections.forEach(function (c) {
        var opt = document.createElement('option');
        opt.value = String(c.id);
        opt.textContent = c.name;
        if (String(c.id) === String(w.collection_id || '')) opt.selected = true;
        qvCollectionSelect.appendChild(opt);
      });
    }
    if (qvNotes) qvNotes.value = w.notes || '';
    var qvName = root.querySelector('[data-eq-qv-name]');
    if (qvName) qvName.value = w.name || '';
    var qvVisibility = root.querySelector('[data-eq-qv-visibility]');
    if (qvVisibility) qvVisibility.value = w.visibility === 'personal' ? 'personal' : 'tenant';
    if (qvDescription) qvDescription.value = w.description || '';
    if (qvGalleryEdit) {
      qvGalleryEdit.innerHTML = '';
      var paths = Array.isArray(w.gallery_paths) ? w.gallery_paths : [];
      var urls = Array.isArray(w.gallery) ? w.gallery.slice(w.cover_url ? 1 : 0) : [];
      if (!paths.length) {
        qvGalleryEdit.hidden = true;
      } else {
        qvGalleryEdit.hidden = false;
        paths.forEach(function (path, i) {
          var label = document.createElement('label');
          var img = document.createElement('img');
          img.src = urls[i] || '';
          img.alt = '';
          var cb = document.createElement('input');
          cb.type = 'checkbox';
          cb.name = 'remove_gallery[]';
          cb.value = path;
          cb.hidden = true;
          var span = document.createElement('span');
          span.textContent = 'Retirer';
          label.appendChild(img);
          label.appendChild(cb);
          label.appendChild(span);
          label.addEventListener('click', function (e) {
            e.preventDefault();
            cb.checked = !cb.checked;
            label.style.opacity = cb.checked ? '0.45' : '1';
            span.textContent = cb.checked ? 'Retiré' : 'Retirer';
          });
          qvGalleryEdit.appendChild(label);
        });
      }
    }
    var csrfInputs = root.querySelectorAll('[data-eq-qv-form] input[name="_csrf_token"], [data-eq-qv-delete] input[name="_csrf_token"]');
    csrfInputs.forEach(function (inp) {
      if (payload.csrf) inp.value = payload.csrf;
    });
  }

  var qvAceCopy = root.querySelector('[data-eq-qv-ace-copy]');
  var qvAceDownload = root.querySelector('[data-eq-qv-ace-download]');
  var qvAceStatus = root.querySelector('[data-eq-qv-ace-status]');
  var qvAceText = '';

  function aceStatus(text) {
    if (!qvAceStatus) return;
    qvAceStatus.textContent = text || '';
    qvAceStatus.hidden = !text;
  }

  function renderAceActions(w) {
    qvAceText = (w && w.ace_export) || '';
    if (qvAceCopy) qvAceCopy.hidden = !qvAceText;
    if (qvAceDownload) {
      qvAceDownload.hidden = !(w && w.ace_export_url);
      if (w && w.ace_export_url) qvAceDownload.setAttribute('href', w.ace_export_url);
    }
    aceStatus(qvAceText ? '' : w ? 'Cette tenue ne peut pas être exportée pour ACE.' : '');
  }

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      var ok = false;
      try {
        ok = document.execCommand('copy');
      } catch (e) {
        ok = false;
      }
      ta.remove();
      if (ok) resolve();
      else reject(new Error('copy'));
    });
  }

  if (qvAceCopy) {
    qvAceCopy.addEventListener('click', function () {
      if (!qvAceText) return;
      copyText(qvAceText).then(
        function () {
          aceStatus('Copiée. En jeu : arsenal ACE, bouton Importer (écran principal) pour l’enfiler sur le mannequin.');
        },
        function () {
          aceStatus('Copie impossible ici : utilisez « Télécharger (.txt) ».');
        }
      );
    });
  }

  var aceHelpToggle = root.querySelector('[data-eq-ace-help-toggle]');
  var aceHelp = document.getElementById('eq-ace-help');
  if (aceHelpToggle && aceHelp) {
    aceHelpToggle.addEventListener('click', function () {
      aceHelp.hidden = !aceHelp.hidden;
      aceHelpToggle.setAttribute('aria-expanded', aceHelp.hidden ? 'false' : 'true');
    });
  }

  // « Modifier » d’une carte : l’aperçu s’ouvre directement sur le formulaire une fois la tenue chargée.
  var qvOpenInEdit = false;

  function openQuickView(id) {
    id = parseInt(id, 10);
    if (!id) return;
    state.openTenueId = id;
    syncUrl();
    showQvView();
    openDialog(qvDialog);
    if (qvTitle) qvTitle.textContent = 'Chargement…';
    if (qvHint) qvHint.textContent = '';
    if (qvItems) qvItems.innerHTML = '<p class="eq-hub__qv-empty">Chargement de l’équipement…</p>';
    renderAceActions(null);
    if (qvEditBtn) qvEditBtn.hidden = true;
    if (qvCollection) qvCollection.hidden = true;

    var base = (catalog.urls && catalog.urls.tenue) || '/equipment/tenues/';
    var url = base.replace(/\/?$/, '/') + id + '?format=json';
    fetch(url, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.json().then(function (j) {
          return { ok: r.ok, body: j };
        });
      })
      .then(function (res) {
        if (!res.ok || !res.body || !res.body.ok) {
          if (qvTitle) qvTitle.textContent = 'Tenue introuvable';
          if (qvItems) qvItems.innerHTML = '<p class="eq-hub__qv-empty">Impossible de charger cette tenue.</p>';
          return;
        }
        var w = res.body.wardrobe || {};
        if (qvTitle) qvTitle.textContent = w.display_name || w.name || 'Tenue';
        if (qvHint) qvHint.textContent = w.usage_hint || '';
        if (qvDesc) {
          var desc = (w.description || '').trim();
          qvDesc.hidden = !desc;
          qvDesc.textContent = desc;
        }
        renderGallery(w.gallery && w.gallery.length ? w.gallery : w.cover_url ? [w.cover_url] : []);
        renderLoadout(w.loadout_items || []);
        renderAceActions(w);
        if (qvCollection) {
          if (w.collection_id && w.collection_name) {
            qvCollection.hidden = false;
            qvCollection.textContent = w.collection_name;
            qvCollection.onclick = function () {
              closeDialog(qvDialog);
              setCollectionFilter(w.collection_id, true);
            };
          } else {
            qvCollection.hidden = true;
          }
        }
        if (qvEditBtn) {
          qvEditBtn.hidden = !w.can_edit;
        }
        if (w.can_edit) fillEditForm(res.body);
        if (w.can_edit && qvOpenInEdit) showQvEdit();
        qvOpenInEdit = false;
      })
      .catch(function () {
        if (qvTitle) qvTitle.textContent = 'Erreur';
        if (qvItems) qvItems.innerHTML = '<p class="eq-hub__qv-empty">Impossible de charger cette tenue.</p>';
      });
  }

  // Cartes, Modifier et ACE sont de vrais liens (adresse de la tenue : utile sans script) : clic simple = fenêtre, Ctrl ou Cmd = lien normal.
  function plainClick(e) {
    return !(e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button > 0);
  }

  root.querySelectorAll('[data-eq-quickview]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      if (plainClick(e)) e.preventDefault();
      openQuickView(btn.getAttribute('data-eq-quickview'));
    });
  });

  root.querySelectorAll('[data-eq-edit-card]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      if (plainClick(e)) e.preventDefault();
      qvOpenInEdit = true;
      openQuickView(btn.getAttribute('data-eq-edit-card'));
    });
  });

  // « Copier pour ACE » d’une carte : texte d’UNE tenue, prêt pour le bouton Importer de l’arsenal.
  var copyStatus = root.querySelector('[data-eq-copy-status]');
  var copyStatusTimer = null;
  function cardCopyStatus(text) {
    if (!copyStatus) return;
    copyStatus.textContent = text || '';
    copyStatus.hidden = !text;
    if (copyStatusTimer) clearTimeout(copyStatusTimer);
    copyStatusTimer = text ? setTimeout(function () { copyStatus.hidden = true; }, 7000) : null;
  }
  root.querySelectorAll('[data-eq-ace-copy-card]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      var id = parseInt(btn.getAttribute('data-eq-ace-copy-card'), 10);
      if (!id || !plainClick(e)) return;
      e.preventDefault();
      var base = (catalog.urls && catalog.urls.aceExport) || '/equipment/ace-export';
      var url = base + '?tenue=' + encodeURIComponent(id);
      btn.disabled = true;
      fetch(url + '&inline=1', { credentials: 'same-origin', headers: { Accept: 'text/plain' } })
        .then(function (r) {
          var type = (r.headers.get('Content-Type') || '').toLowerCase();
          if (!r.ok || type.indexOf('text/plain') !== 0) throw new Error('export');
          return r.text();
        })
        .then(function (text) {
          return copyText(text).then(
            function () {
              cardCopyStatus('Tenue copiée. En jeu : arsenal ACE, bouton Importer (écran principal) pour l’enfiler.');
            },
            function () {
              cardCopyStatus('Copie impossible ici : le fichier de la tenue va être téléchargé à la place.');
              window.location.href = url;
            }
          );
        })
        .catch(function () {
          cardCopyStatus('Cette tenue ne peut pas être copiée pour ACE.');
        })
        .finally(function () {
          btn.disabled = false;
        });
    });
  });

  if (qvEditBtn) {
    qvEditBtn.addEventListener('click', function () {
      showQvEdit();
    });
  }
  if (qvBack) {
    qvBack.addEventListener('click', function () {
      showQvView();
    });
  }

  /* ---- Filters wiring ---- */
  root.querySelectorAll('[data-eq-filter-collection]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setCollectionFilter(btn.getAttribute('data-eq-filter-collection'), true);
    });
  });

  root.querySelectorAll('[data-eq-collection-cb]').forEach(function (cb) {
    cb.addEventListener('change', function () {
      if (cb.checked) state.collections[String(cb.value)] = true;
      else delete state.collections[String(cb.value)];
      applyFilters();
    });
  });

  root.querySelectorAll('[data-eq-kind-cb]').forEach(function (cb) {
    cb.addEventListener('change', function () {
      if (cb.checked) state.kinds[String(cb.value)] = true;
      else delete state.kinds[String(cb.value)];
      applyFilters();
    });
  });

  root.querySelectorAll('[data-eq-avail]').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var v = cb.getAttribute('data-eq-avail');
      if (v === 'none') state.availNone = !!cb.checked;
      if (v === 'in') state.availIn = !!cb.checked;
      applyFilters();
    });
  });

  if (searchInput) {
    searchInput.addEventListener(
      'input',
      debounce(function () {
        state.q = (searchInput.value || '').trim().toLowerCase();
        applyFilters();
      }, 120)
    );
  }
  if (kindSelect) {
    kindSelect.addEventListener('change', function () {
      state.kinds = {};
      if (kindSelect.value) state.kinds[kindSelect.value] = true;
      applyFilters();
    });
  }
  if (sortSelect) {
    sortSelect.addEventListener('change', function () {
      state.sort = sortSelect.value || 'name';
      applyFilters();
    });
  }
  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      state.q = '';
      state.collections = {};
      state.kinds = {};
      state.availNone = false;
      state.availIn = false;
      if (searchInput) searchInput.value = '';
      if (kindSelect) kindSelect.value = '';
      root.querySelectorAll('[data-eq-collection-cb], [data-eq-kind-cb], [data-eq-avail]').forEach(function (cb) {
        cb.checked = false;
      });
      applyFilters();
    });
  }

  /* ---- Collection edit modal ---- */
  function openCollectionEdit(id) {
    id = parseInt(id, 10);
    if (!id || !colEditDialog) return;
    var base = (catalog.urls && catalog.urls.collection) || '/equipment/collections/';
    var url = base.replace(/\/?$/, '/') + id + '?format=json';
    openDialog(colEditDialog);
    fetch(url, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (body) {
        if (!body || !body.ok) return;
        var c = body.collection || {};
        var form = root.querySelector('[data-eq-collection-edit-form]');
        var del = root.querySelector('[data-eq-col-edit-delete]');
        if (form) form.action = (body.urls && body.urls.update) || '';
        if (del) del.action = (body.urls && body.urls.delete) || '';
        var nameEl = root.querySelector('[data-eq-col-edit-name]');
        var descEl = root.querySelector('[data-eq-col-edit-description]');
        var visEl = root.querySelector('[data-eq-col-edit-visibility]');
        if (nameEl) nameEl.value = c.name || '';
        if (descEl) descEl.value = c.description || '';
        if (visEl) visEl.value = c.visibility || 'personal';
        var selected = {};
        (c.selected_ids || []).forEach(function (sid) {
          selected[String(sid)] = true;
        });
        var gridEl = root.querySelector('[data-eq-col-edit-grid]');
        var countEl2 = root.querySelector('[data-eq-col-edit-count]');
        if (!gridEl) return;
        gridEl.innerHTML = '';
        (body.mine_wardrobes || []).forEach(function (w) {
          var label = document.createElement('label');
          label.className = 'eq-hub__pick';
          label.setAttribute('data-eq-col-edit-pick', '1');
          label.setAttribute('data-name', String(w.display_name || w.name || '').toLowerCase());
          var input = document.createElement('input');
          input.type = 'checkbox';
          input.name = 'wardrobe_ids[]';
          input.value = String(w.id);
          input.hidden = true;
          input.checked = !!selected[String(w.id)];
          var media = document.createElement('span');
          media.className = 'eq-hub__media eq-hub__media--pick';
          if (w.cover_url) {
            var img = document.createElement('img');
            img.className = 'eq-hub__img is-loaded';
            img.src = w.cover_url;
            img.alt = '';
            media.appendChild(img);
          } else {
            media.innerHTML = '<span class="eq-hub__ph" aria-hidden="true"></span>';
          }
          var mark = document.createElement('span');
          mark.className = 'eq-hub__pick-mark';
          mark.setAttribute('aria-hidden', 'true');
          mark.textContent = '✓';
          media.appendChild(mark);
          var nm = document.createElement('span');
          nm.className = 'eq-hub__pick-name';
          nm.textContent = w.display_name || w.name || 'Tenue';
          label.appendChild(input);
          label.appendChild(media);
          label.appendChild(nm);
          label.classList.toggle('is-selected', input.checked);
          label.addEventListener('click', function (e) {
            e.preventDefault();
            input.checked = !input.checked;
            label.classList.toggle('is-selected', input.checked);
            if (countEl2) {
              var n = gridEl.querySelectorAll('input:checked').length;
              countEl2.textContent = n + ' sélectionnée' + (n > 1 ? 's' : '');
            }
          });
          gridEl.appendChild(label);
        });
        if (countEl2) {
          var n0 = gridEl.querySelectorAll('input:checked').length;
          countEl2.textContent = n0 + ' sélectionnée' + (n0 > 1 ? 's' : '');
        }
        root.querySelectorAll('[data-eq-collection-edit-form] input[name="_csrf_token"], [data-eq-col-edit-delete] input[name="_csrf_token"]').forEach(function (inp) {
          if (body.csrf) inp.value = body.csrf;
        });
      });
  }

  var editColBtn = root.querySelector('[data-eq-edit-collection]');
  if (editColBtn) {
    editColBtn.addEventListener('click', function () {
      var id = editColBtn.getAttribute('data-eq-edit-collection-id');
      if (id) openCollectionEdit(id);
    });
  }
  var colEditSearch = root.querySelector('[data-eq-col-edit-search]');
  if (colEditSearch) {
    colEditSearch.addEventListener(
      'input',
      debounce(function () {
        var q = (colEditSearch.value || '').trim().toLowerCase();
        root.querySelectorAll('[data-eq-col-edit-pick]').forEach(function (pick) {
          pick.hidden = !!(q && (pick.getAttribute('data-name') || '').indexOf(q) === -1);
        });
      }, 100)
    );
  }

  /* ---- Fiches / Dotation ---- */
  function setStageMedia(el, url) {
    if (!el) return;
    if (url) {
      el.innerHTML = '<img src="' + url.replace(/"/g, '&quot;') + '" alt="">';
    } else {
      el.innerHTML =
        '<span class="eq-hub__ph eq-hub__ph--lg" aria-hidden="true"><svg viewBox="0 0 64 80" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="12" y="10" width="40" height="52" rx="3" stroke="currentColor" stroke-width="2"/></svg></span>';
    }
  }

  function openFiche(id) {
    id = parseInt(id, 10);
    if (!id || !ficheDialog) return;
    var base = (catalog.urls && catalog.urls.fiche) || '/equipment/fiches/';
    openDialog(ficheDialog);
    var view = root.querySelector('[data-eq-fiche-view-panel]');
    var edit = root.querySelector('[data-eq-fiche-edit-panel]');
    if (view) view.hidden = false;
    if (edit) edit.hidden = true;
    fetch(base.replace(/\/?$/, '/') + id, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (body) {
        if (!body || !body.ok) return;
        var f = body.fiche || {};
        var title = root.querySelector('[data-eq-fiche-title]');
        var desc = root.querySelector('[data-eq-fiche-desc]');
        var cat = root.querySelector('[data-eq-fiche-cat]');
        var page = root.querySelector('[data-eq-fiche-page]');
        var editBtn = root.querySelector('[data-eq-fiche-edit]');
        if (title) title.textContent = f.name || 'Fiche';
        if (desc) desc.textContent = f.description || 'Aucune description pour le moment.';
        if (cat) {
          cat.hidden = !f.category;
          cat.textContent = f.category || '';
        }
        setStageMedia(root.querySelector('[data-eq-fiche-stage]'), f.cover_url);
        if (page) page.href = (body.urls && body.urls.page) || '#';
        if (editBtn) editBtn.hidden = !f.can_edit;
        var form = root.querySelector('[data-eq-fiche-edit-form]');
        if (form && body.urls) form.action = body.urls.update || '';
        var n = root.querySelector('[data-eq-fiche-edit-name]');
        var c = root.querySelector('[data-eq-fiche-edit-category]');
        var d = root.querySelector('[data-eq-fiche-edit-description]');
        if (n) n.value = f.name || '';
        if (c) c.value = f.category || '';
        if (d) d.value = f.description || '';
        root.querySelectorAll('[data-eq-fiche-edit-form] input[name="_csrf_token"]').forEach(function (inp) {
          if (body.csrf) inp.value = body.csrf;
        });
      });
  }

  function openDotation(id) {
    id = parseInt(id, 10);
    if (!id || !dotationDialog) return;
    var base = (catalog.urls && catalog.urls.dotation) || '/equipment/dotation/';
    openDialog(dotationDialog);
    var view = root.querySelector('[data-eq-dotation-view-panel]');
    var edit = root.querySelector('[data-eq-dotation-edit-panel]');
    if (view) view.hidden = false;
    if (edit) edit.hidden = true;
    fetch(base.replace(/\/?$/, '/') + id, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (body) {
        if (!body || !body.ok) return;
        var item = body.item || {};
        var title = root.querySelector('[data-eq-dotation-title]');
        var desc = root.querySelector('[data-eq-dotation-desc]');
        var code = root.querySelector('[data-eq-dotation-code]');
        var admin = root.querySelector('[data-eq-dotation-admin]');
        var editBtn = root.querySelector('[data-eq-dotation-edit]');
        if (title) title.textContent = item.name || 'Article';
        if (desc) desc.textContent = item.description || 'Aucune description pour le moment.';
        if (code) {
          code.hidden = !item.code;
          code.textContent = item.code || '';
        }
        setStageMedia(root.querySelector('[data-eq-dotation-stage]'), item.cover_url);
        if (admin) {
          admin.hidden = !item.admin_url || !item.can_edit;
          if (item.admin_url) admin.href = item.admin_url;
        }
        if (editBtn) editBtn.hidden = !item.can_edit;
        var form = root.querySelector('[data-eq-dotation-edit-form]');
        if (form && body.urls) form.action = body.urls.update || '';
        var codeEl = root.querySelector('[data-eq-dotation-edit-code]');
        var nameEl = root.querySelector('[data-eq-dotation-edit-name]');
        var catEl = root.querySelector('[data-eq-dotation-edit-category]');
        var descEl = root.querySelector('[data-eq-dotation-edit-description]');
        if (codeEl) codeEl.value = item.code || '';
        if (nameEl) nameEl.value = item.name || '';
        if (catEl) catEl.value = item.category || '';
        if (descEl) descEl.value = item.description || '';
        root.querySelectorAll('[data-eq-dotation-edit-form] input[name="_csrf_token"]').forEach(function (inp) {
          if (body.csrf) inp.value = body.csrf;
        });
      });
  }

  root.querySelectorAll('[data-eq-fiche-view]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openFiche(btn.getAttribute('data-eq-fiche-view'));
    });
  });
  root.querySelectorAll('[data-eq-dotation-view]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openDotation(btn.getAttribute('data-eq-dotation-view'));
    });
  });

  var ficheEditBtn = root.querySelector('[data-eq-fiche-edit]');
  var ficheBack = root.querySelector('[data-eq-fiche-back]');
  if (ficheEditBtn) {
    ficheEditBtn.addEventListener('click', function () {
      var view = root.querySelector('[data-eq-fiche-view-panel]');
      var edit = root.querySelector('[data-eq-fiche-edit-panel]');
      if (view) view.hidden = true;
      if (edit) edit.hidden = false;
    });
  }
  if (ficheBack) {
    ficheBack.addEventListener('click', function () {
      var view = root.querySelector('[data-eq-fiche-view-panel]');
      var edit = root.querySelector('[data-eq-fiche-edit-panel]');
      if (view) view.hidden = false;
      if (edit) edit.hidden = true;
    });
  }
  var dotEditBtn = root.querySelector('[data-eq-dotation-edit]');
  var dotBack = root.querySelector('[data-eq-dotation-back]');
  if (dotEditBtn) {
    dotEditBtn.addEventListener('click', function () {
      var view = root.querySelector('[data-eq-dotation-view-panel]');
      var edit = root.querySelector('[data-eq-dotation-edit-panel]');
      if (view) view.hidden = true;
      if (edit) edit.hidden = false;
    });
  }
  if (dotBack) {
    dotBack.addEventListener('click', function () {
      var view = root.querySelector('[data-eq-dotation-view-panel]');
      var edit = root.querySelector('[data-eq-dotation-edit-panel]');
      if (view) view.hidden = false;
      if (edit) edit.hidden = true;
    });
  }

  function bindSimpleSearch(inputSel, cardSel) {
    var input = root.querySelector(inputSel);
    if (!input) return;
    input.addEventListener(
      'input',
      debounce(function () {
        var q = (input.value || '').trim().toLowerCase();
        root.querySelectorAll(cardSel).forEach(function (card) {
          card.hidden = !!(q && (card.getAttribute('data-name') || '').indexOf(q) === -1);
        });
      }, 100)
    );
  }
  bindSimpleSearch('[data-eq-fiche-search]', '[data-eq-fiche-card]');
  bindSimpleSearch('[data-eq-dotation-search]', '[data-eq-dotation-card]');

  /* ---- Navigation par espaces : favori en un clic, fenêtres, repli sans JavaScript ---- */
  function announce(text) {
    var el = root.querySelector('[data-eq-copy-status]');
    if (!el) return;
    el.textContent = text || '';
    el.hidden = !text;
    if (text) setTimeout(function () { el.hidden = true; }, 4000);
  }

  function bumpCount(key, delta) {
    var el = root.querySelector('[data-eq-badge="' + key + '"]');
    if (el) el.textContent = String(Math.max(0, (parseInt(el.textContent, 10) || 0) + delta));
  }

  root.addEventListener('submit', function (e) {
    var form = e.target && e.target.closest ? e.target.closest('[data-eq-fav-form]') : null;
    if (!form || !window.fetch) return;
    e.preventDefault();
    var btn = form.querySelector('button[type="submit"]');
    var onField = form.querySelector('input[name="on"]');
    var wasOn = onField && onField.value === '0';
    if (btn) btn.disabled = true;
    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
      .then(function (res) {
        if (!res.ok || !res.body || !res.body.ok) throw new Error('fav');
        var on = !!res.body.favorite;
        if (onField) onField.value = on ? '0' : '1';
        if (btn) {
          btn.classList.toggle('is-on', on);
          btn.setAttribute('aria-pressed', on ? 'true' : 'false');
          var label = on ? btn.getAttribute('data-label-on') : btn.getAttribute('data-label-off');
          btn.setAttribute('title', label);
          var star = btn.querySelector('[data-eq-fav-star]');
          if (star) star.textContent = on ? '★' : '☆';
          var lab = btn.querySelector('[data-eq-fav-label]');
          if (lab) lab.textContent = label;
        }
        bumpCount('favoris', on ? 1 : -1);
        // Dans l'espace Favoris, retirer un favori retire la carte (la page reste à jour sans rechargement).
        if (!on && root.getAttribute('data-eq-space') === 'favoris') {
          var card = form.closest('[data-eq-card]');
          if (card) card.remove();
          if (!root.querySelector('[data-eq-card]')) window.location.reload();
        }
        announce(on ? 'Ajoutée aux favoris.' : 'Retirée des favoris.');
      })
      .catch(function () {
        announce('Impossible de modifier le favori. Réessayez.');
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  });

  // Détails d'un kit, gestion d'un kit, mon kit en jeu : le <details> de la page (repli) s'ouvre en fenêtre.
  root.addEventListener('click', function (e) {
    var summary = e.target && e.target.closest ? e.target.closest('details[data-eq-details-modal] > summary') : null;
    if (!summary || !genericDialog || typeof genericDialog.showModal !== 'function') return;
    var details = summary.parentNode;
    e.preventDefault();
    var body = genericDialog.querySelector('[data-eq-generic-body]');
    var title = genericDialog.querySelector('[data-eq-generic-title]');
    if (!body || !title) return;
    title.textContent = (summary.textContent || '').trim();
    body.innerHTML = '';
    Array.prototype.forEach.call(details.childNodes, function (n) {
      if (n !== summary) body.appendChild(n.cloneNode(true));
    });
    openDialog(genericDialog);
  });

  // Modifier une collection : fenêtre sur la liste (adresse ?collection=ID&edit_collection=1 conservée en repli).
  root.querySelectorAll('[data-eq-collection-edit]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      openCollectionEdit(a.getAttribute('data-eq-collection-edit'));
    });
  });

  // Recherche et filtres d'un espace : un changement de liste relance la recherche (le bouton Filtrer reste là sans script).
  root.querySelectorAll('form[data-eq-autosubmit] select').forEach(function (sel) {
    sel.addEventListener('change', function () {
      if (sel.form) sel.form.submit();
    });
  });

  // Fenêtres ouvertes côté serveur (repli sans JavaScript) : on les passe en vraies fenêtres modales ; blocs de repli retirés.
  function upgradeFallbacks(params) {
    root.querySelectorAll('dialog[open]').forEach(function (dlg) {
      dlg.close();
      openDialog(dlg);
    });
    if (params.get('creer') === 'kit') {
      openDialog(kitDialog);
    }
    root.querySelectorAll('[data-eq-detail-fallback]').forEach(function (el) {
      el.remove();
    });
  }

  /* ---- Init from URL ---- */
  (function initFromUrl() {
    var params = new URLSearchParams(window.location.search);
    var tab = params.get('tab');
    setTab(tab || 'fiches');
    // Ancienne adresse ?tab=fiches / ?tab=dotation : on amène la personne à la section du matériel.
    if (tab === 'fiches' || tab === 'dotation') {
      var materiel = document.getElementById('materiel');
      if (materiel && materiel.scrollIntoView) materiel.scrollIntoView();
    }
    var col = params.get('collection');
    var q = params.get('q');
    if (q && searchInput) {
      searchInput.value = q;
      state.q = q.trim().toLowerCase();
    }
    applyFilters();
    upgradeFallbacks(params);
    var tenue = params.get('tenue');
    if (tenue) openQuickView(tenue);
    if (params.get('edit_collection') === '1' && col) openCollectionEdit(col);
    var fiche = params.get('fiche');
    if (fiche) {
      setTab('fiches');
      openFiche(fiche);
    }
    var dot = params.get('dotation');
    if (dot) {
      setTab('dotation');
      openDotation(dot);
    }
  })();

  bindMedia(root);
  updatePickCount();
})();
