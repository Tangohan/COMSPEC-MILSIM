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
  };

  var grid = root.querySelector('[data-eq-grid]');
  var cards = grid ? Array.prototype.slice.call(grid.querySelectorAll('[data-eq-card]')) : [];
  var noResults = root.querySelector('[data-eq-no-results]');
  var countEl = root.querySelector('[data-eq-count]');
  var searchInput = root.querySelector('[data-eq-search]');
  var sortSelect = root.querySelector('[data-eq-sort]');
  var clearBtn = root.querySelector('[data-eq-clear-filters]');
  var activeFilters = root.querySelector('[data-eq-active-filters]');
  var qvDialog = document.getElementById('eq-quickview');
  var colDialog = document.getElementById('eq-collection-new');
  var helpDialog = document.getElementById('eq-tenue-help');

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
    var cols = selectedKeys(state.collections);
    if (cols.length === 1) params.set('collection', cols[0]);
    else params.delete('collection');
    if (state.openTenueId) params.set('tenue', String(state.openTenueId));
    else params.delete('tenue');
    if (state.q) params.set('q', state.q);
    else params.delete('q');
    var qs = params.toString();
    var next = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
    window.history.replaceState({}, '', next);
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

  function renderActiveFilters() {
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
        var hubBase = String((catalog.urls && catalog.urls.hub) || '/equipment').replace(/\/?$/, '');
        editLink.href = hubBase + '/collections/' + owned.id + '?edit=1';
      } else {
        editWrap.hidden = true;
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
        (card.getAttribute('data-owner') || '');
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
    if (!grid) return;
    var matched = [];
    cards.forEach(function (card) {
      if (cardMatches(card)) matched.push(card);
      else card.hidden = true;
    });
    sortCards(matched);
    matched.forEach(function (card) {
      card.hidden = false;
      grid.appendChild(card);
    });
    if (noResults) noResults.hidden = matched.length > 0 || cards.length === 0;
    updateCount(matched.length);
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
    btn.addEventListener('click', function () {
      var which = btn.getAttribute('data-eq-open');
      if (which === 'collection-new') openDialog(colDialog);
      if (which === 'tenue-help') openDialog(helpDialog);
    });
  });

  root.querySelectorAll('[data-eq-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dlg = btn.closest('dialog');
      closeDialog(dlg);
    });
  });

  [qvDialog, colDialog, helpDialog].forEach(function (dlg) {
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
        var span = document.createElement('span');
        span.textContent = it.name || '';
        li.appendChild(span);
        if ((it.qty || 1) > 1) {
          var em = document.createElement('em');
          em.textContent = '× ' + it.qty;
          li.appendChild(em);
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
    var csrfInputs = root.querySelectorAll('[data-eq-qv-form] input[name="_csrf_token"], [data-eq-qv-delete] input[name="_csrf_token"]');
    csrfInputs.forEach(function (inp) {
      if (payload.csrf) inp.value = payload.csrf;
    });
  }

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
        renderGallery(w.gallery && w.gallery.length ? w.gallery : w.cover_url ? [w.cover_url] : []);
        renderLoadout(w.loadout_items || []);
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
      })
      .catch(function () {
        if (qvTitle) qvTitle.textContent = 'Erreur';
        if (qvItems) qvItems.innerHTML = '<p class="eq-hub__qv-empty">Impossible de charger cette tenue.</p>';
      });
  }

  root.querySelectorAll('[data-eq-quickview]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openQuickView(btn.getAttribute('data-eq-quickview'));
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
      root.querySelectorAll('[data-eq-collection-cb], [data-eq-kind-cb], [data-eq-avail]').forEach(function (cb) {
        cb.checked = false;
      });
      applyFilters();
    });
  }

  /* ---- Init from URL ---- */
  (function initFromUrl() {
    var params = new URLSearchParams(window.location.search);
    var col = params.get('collection');
    if (col) state.collections[String(col)] = true;
    var q = params.get('q');
    if (q && searchInput) {
      searchInput.value = q;
      state.q = q.trim().toLowerCase();
    }
    applyFilters();
    var tenue = params.get('tenue');
    if (tenue) openQuickView(tenue);
  })();

  bindMedia(root);
  updatePickCount();
})();
