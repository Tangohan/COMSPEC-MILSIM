/**
 * Sélecteur de symboles tactiques (milstd + milsymbol) pour le formulaire marqueur ATAK.
 * Affiche des libellés FR ; le SIDC reste interne.
 */
window.ATAKSymbolPicker = (function () {
  var state = {
    mode: 'tactical', // tactical | simple
    affiliation: 'friend',
    familyId: '',
    query: '',
    selectedId: '',
    selectedEntry: null,
    simpleIcon: 'pin',
  };

  var AFFILIATIONS = [
    { value: 'friend', label: 'Ami' },
    { value: 'hostile', label: 'Hostile' },
    { value: 'neutral', label: 'Neutre' },
    { value: 'unknown', label: 'Inconnu' },
  ];

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function catalog() {
    return window.MilstdCatalog || null;
  }

  function mount(container) {
    if (!container) return;
    container.innerHTML =
      '<div class="atak-sym-picker" id="atak-sym-picker">' +
      '<fieldset class="atak-marker-form__fieldset">' +
      '<legend>Type de marqueur</legend>' +
      '<div class="atak-marker-form__choices" id="atak-sym-mode">' +
      '<label class="atak-marker-form__choice"><input type="radio" name="sym-mode" value="tactical" checked /><span>Symbole tactique</span></label>' +
      '<label class="atak-marker-form__choice"><input type="radio" name="sym-mode" value="simple" /><span>Repère simple</span></label>' +
      '</div></fieldset>' +

      '<div id="atak-sym-tactical-pane">' +
      '<fieldset class="atak-marker-form__fieldset">' +
      '<legend>Affiliation</legend>' +
      '<div class="atak-marker-form__choices" id="atak-sym-aff"></div></fieldset>' +
      '<label class="atak-marker-form__label">Famille' +
      '<select class="atak-input-modal__field" id="atak-sym-family"></select></label>' +
      '<label class="atak-marker-form__label">Rechercher un symbole' +
      '<input type="search" class="atak-input-modal__field" id="atak-sym-search" placeholder="Ex. infanterie, blindé, hélicoptère…" autocomplete="off" /></label>' +
      '<div class="atak-sym-picker__preview" id="atak-sym-preview" aria-live="polite"></div>' +
      '<p class="atak-sym-picker__selected" id="atak-sym-selected-label">Aucun symbole choisi</p>' +
      '<div class="atak-sym-picker__grid" id="atak-sym-grid" role="listbox" aria-label="Liste des symboles"></div>' +
      '<p class="atak-sym-picker__hint" id="atak-sym-count"></p>' +
      '</div>' +

      '<div id="atak-sym-simple-pane" hidden>' +
      '<fieldset class="atak-marker-form__fieldset"><legend>Forme</legend>' +
      '<div class="atak-marker-form__choices" id="atak-sym-simple-icons"></div></fieldset>' +
      '</div>' +
      '</div>';

    document.getElementById('atak-sym-aff').innerHTML = AFFILIATIONS.map(function (a) {
      var checked = a.value === state.affiliation ? ' checked' : '';
      return '<label class="atak-marker-form__choice"><input type="radio" name="sym-aff" value="' + a.value + '"' + checked + ' /><span>' + esc(a.label) + '</span></label>';
    }).join('');

    var famSel = document.getElementById('atak-sym-family');
    var fams = catalog() ? catalog().families() : [];
    famSel.innerHTML = '<option value="">Toutes les familles</option>' + fams.map(function (f) {
      return '<option value="' + esc(f.id) + '">' + esc(f.label) + '</option>';
    }).join('');

    var simpleIcons = [
      { value: 'pin', label: 'Épingle' },
      { value: 'dot', label: 'Point' },
      { value: 'flag', label: 'Drapeau' },
      { value: 'warning', label: 'Alerte' },
      { value: 'target', label: 'Cible' },
    ];
    document.getElementById('atak-sym-simple-icons').innerHTML = simpleIcons.map(function (it) {
      var checked = it.value === 'pin' ? ' checked' : '';
      return '<label class="atak-marker-form__choice"><input type="radio" name="sym-simple-icon" value="' + it.value + '"' + checked + ' /><span>' + esc(it.label) + '</span></label>';
    }).join('');

    container.querySelectorAll('input[name="sym-mode"]').forEach(function (el) {
      el.addEventListener('change', function () {
        state.mode = el.value;
        syncModePanes();
      });
    });
    container.querySelectorAll('input[name="sym-aff"]').forEach(function (el) {
      el.addEventListener('change', function () {
        state.affiliation = el.value;
        updatePreview();
        renderGrid();
      });
    });
    famSel.addEventListener('change', function () {
      state.familyId = famSel.value;
      renderGrid();
    });
    var searchEl = document.getElementById('atak-sym-search');
    var searchTimer = null;
    searchEl.addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () {
        state.query = searchEl.value;
        renderGrid();
      }, 120);
    });
    container.querySelectorAll('input[name="sym-simple-icon"]').forEach(function (el) {
      el.addEventListener('change', function () {
        state.simpleIcon = el.value;
      });
    });

    syncModePanes();
    renderGrid();
    // défaut : premier symbole infanterie si possible
    if (!state.selectedEntry) {
      var defaults = catalog() ? catalog().search({ query: 'infanterie', familyId: 'GRDTRK_UNT', limit: 1 }) : [];
      if (!defaults.length && catalog()) defaults = catalog().search({ familyId: 'GRDTRK_UNT', limit: 1 });
      if (defaults[0]) selectEntry(defaults[0]);
      else updatePreview();
    }
  }

  function syncModePanes() {
    var tac = document.getElementById('atak-sym-tactical-pane');
    var simp = document.getElementById('atak-sym-simple-pane');
    var colorFs = document.getElementById('atak-marker-color-fieldset');
    if (tac) tac.hidden = state.mode !== 'tactical';
    if (simp) simp.hidden = state.mode !== 'simple';
    if (colorFs) colorFs.hidden = state.mode !== 'simple';
  }

  function selectEntry(entry) {
    state.selectedEntry = entry || null;
    state.selectedId = entry ? entry.id : '';
    var lab = document.getElementById('atak-sym-selected-label');
    if (lab) {
      lab.textContent = entry
        ? (entry.nameFr + ' · ' + entry.familyLabel)
        : 'Aucun symbole choisi';
    }
    updatePreview();
    var grid = document.getElementById('atak-sym-grid');
    if (grid) {
      grid.querySelectorAll('.atak-sym-picker__item').forEach(function (btn) {
        var on = btn.getAttribute('data-id') === state.selectedId;
        btn.classList.toggle('is-selected', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
      });
    }
  }

  function updatePreview() {
    var box = document.getElementById('atak-sym-preview');
    if (!box) return;
    var nato = window.NatoSidcIcons;
    var entry = state.selectedEntry;
    if (!nato || !entry) {
      box.innerHTML = '<span class="atak-sym-picker__preview-empty">Aperçu</span>';
      return;
    }
    var sidc = catalog() ? catalog().sidcForEntry(entry, state.affiliation) : null;
    box.innerHTML = nato.previewHtml({
      sidc: sidc,
      affiliation: state.affiliation,
      functionid: entry.functionid,
      scheme: entry.scheme,
      battledimension: entry.battledimension,
      size: 48,
      showLabel: false,
    });
  }

  function renderGrid() {
    var grid = document.getElementById('atak-sym-grid');
    var countEl = document.getElementById('atak-sym-count');
    if (!grid) return;
    var cat = catalog();
    var items = cat
      ? cat.search({ query: state.query, familyId: state.familyId, limit: 180 })
      : [];
    if (!items.length) {
      grid.innerHTML = '<p class="atak-sym-picker__empty">Aucun symbole ne correspond. Élargissez la recherche ou changez de famille.</p>';
      if (countEl) countEl.textContent = '';
      return;
    }
    var nato = window.NatoSidcIcons;
    grid.innerHTML = items.map(function (it) {
      var sidc = cat.sidcForEntry(it, state.affiliation);
      var thumb = nato
        ? nato.previewHtml({
            sidc: sidc,
            affiliation: state.affiliation,
            functionid: it.functionid,
            scheme: it.scheme,
            battledimension: it.battledimension,
            size: 28,
            showLabel: false,
          })
        : '';
      var sel = it.id === state.selectedId ? ' is-selected' : '';
      return '<button type="button" class="atak-sym-picker__item' + sel + '" role="option" data-id="' + esc(it.id) + '" aria-selected="' + (sel ? 'true' : 'false') + '" title="' + esc(it.nameFr) + '">' +
        '<span class="atak-sym-picker__thumb">' + thumb + '</span>' +
        '<span class="atak-sym-picker__name">' + esc(it.nameFr) + '</span>' +
        '</button>';
    }).join('');
    if (countEl) {
      var total = cat ? cat.count() : 0;
      countEl.textContent = items.length + ' symbole(s) affiché(s)' + (total ? ' sur ' + total + ' disponibles' : '');
    }
    grid.querySelectorAll('.atak-sym-picker__item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-id');
        var entry = cat.findById(id);
        if (entry) selectEntry(entry);
      });
    });
  }

  function reset(defaults) {
    defaults = defaults || {};
    state.mode = defaults.sidc || defaults.symbolMode === 'tactical' || !defaults.icon || defaults.icon === 'milsymbol'
      ? 'tactical'
      : (defaults.symbolMode === 'simple' ? 'simple' : 'tactical');
    if (defaults.icon && defaults.icon !== 'milsymbol' && !defaults.sidc) state.mode = 'simple';
    state.affiliation = defaults.affiliation || 'friend';
    state.familyId = defaults.symbolFamily || '';
    state.query = '';
    state.simpleIcon = (defaults.icon && defaults.icon !== 'milsymbol') ? defaults.icon : 'pin';
    state.selectedEntry = null;
    state.selectedId = '';

    var modeEl = document.querySelector('input[name="sym-mode"][value="' + state.mode + '"]');
    if (modeEl) modeEl.checked = true;
    var affEl = document.querySelector('input[name="sym-aff"][value="' + state.affiliation + '"]');
    if (affEl) affEl.checked = true;
    var fam = document.getElementById('atak-sym-family');
    if (fam) fam.value = state.familyId;
    var search = document.getElementById('atak-sym-search');
    if (search) search.value = '';
    var simple = document.querySelector('input[name="sym-simple-icon"][value="' + state.simpleIcon + '"]');
    if (simple) simple.checked = true;

    syncModePanes();

    var cat = catalog();
    if (defaults.sidc && cat) {
      // retrouver via functionid (positions 5-10 du SIDC lettre)
      var sidc = String(defaults.sidc);
      var fid = sidc.length >= 10 ? sidc.slice(4, 10) : '';
      var found = fid ? cat.findByFunctionId(fid) : null;
      if (found) selectEntry(found);
    } else if (defaults.symbolId && cat) {
      var byId = cat.findById(defaults.symbolId);
      if (byId) selectEntry(byId);
    }
    if (!state.selectedEntry && cat) {
      var d = cat.search({ query: 'infanterie', familyId: 'GRDTRK_UNT', limit: 1 });
      if (d[0]) selectEntry(d[0]);
    }
    renderGrid();
    updatePreview();
  }

  function readValue() {
    if (state.mode === 'simple') {
      var simple = (document.querySelector('input[name="sym-simple-icon"]:checked') || {}).value || state.simpleIcon || 'pin';
      return {
        symbolMode: 'simple',
        icon: simple,
        affiliation: null,
        sidc: null,
        symbolId: null,
        symbolName: null,
        symbolFamily: null,
        functionid: null,
      };
    }
    var entry = state.selectedEntry;
    var aff = (document.querySelector('input[name="sym-aff"]:checked') || {}).value || state.affiliation || 'friend';
    if (!entry) {
      return {
        symbolMode: 'tactical',
        icon: 'milsymbol',
        affiliation: aff,
        sidc: null,
        symbolId: null,
        symbolName: null,
        symbolFamily: null,
        functionid: null,
      };
    }
    var sidc = catalog() ? catalog().sidcForEntry(entry, aff) : null;
    return {
      symbolMode: 'tactical',
      icon: 'milsymbol',
      affiliation: aff,
      sidc: sidc,
      symbolId: entry.id,
      symbolName: entry.nameFr,
      symbolFamily: entry.familyId,
      functionid: entry.functionid,
      scheme: entry.scheme,
      battledimension: entry.battledimension,
    };
  }

  return {
    mount: mount,
    reset: reset,
    readValue: readValue,
  };
})();
