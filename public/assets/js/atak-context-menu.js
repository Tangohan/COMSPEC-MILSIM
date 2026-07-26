/* COMSPEC ATAK — Menu contextuel carte (clic droit) */
window.ATAKContextMenu = (function () {
  var menuEl = null;
  var promptEl = null;
  var markerFormEl = null;
  var lastLatLng = null;
  var activeFeature = null;
  var suppressMapContextMenu = false;
  var boundMap = null;
  var drawMode = null; // 'line' | 'zone' | 'polygon' | 'rectangle'
  var drawPoints = [];
  var drawPreview = null;
  var drawOpts = null;
  var hintEl = null;
  var promptResolve = null;
  var markerFormResolve = null;

  /** Presets zones tactiques (TOC web — indépendant du mod en jeu). */
  var ZONE_PRESETS = {
    zone: {
      mode: 'zone',
      kind: 'zone',
      color: '#eab308',
      defaultLabel: 'Zone',
      title: 'Libellé de la zone',
      hint: 'Optionnel.',
      mapHint: 'Zone : cliquez le centre, puis le bord pour fixer le rayon. Échap pour abandonner.'
    },
    search: {
      mode: 'zone',
      kind: 'search_zone',
      color: '#22d3ee',
      defaultLabel: 'Zone de recherche',
      title: 'Nom de la zone de recherche',
      hint: 'Ex. secteur nord — fouille en cours',
      mapHint: 'Zone de recherche : cliquez le centre, puis le bord pour fixer le rayon. Échap pour abandonner.'
    },
    perimeter: {
      mode: 'polygon',
      kind: 'perimeter',
      color: '#fb923c',
      defaultLabel: 'Périmètre',
      title: 'Nom du périmètre',
      hint: 'Ex. périmètre de sécurité — bâtiment A',
      mapHint: 'Périmètre : cliquez pour poser les sommets (minimum 3), puis Terminer. Échap pour abandonner.'
    },
    aoi: {
      mode: 'rectangle',
      kind: 'aoi',
      color: '#86efac',
      defaultLabel: 'Zone d’intérêt',
      title: 'Nom de la zone d’intérêt',
      hint: 'Ex. zone d’intérêt — carrefour Est',
      mapHint: 'Zone d’intérêt : cliquez un coin, puis le coin opposé. Échap pour abandonner.'
    }
  };

  var MARKER_COLORS = [
    { value: '#34d399', label: 'Vert' },
    { value: '#ef4444', label: 'Rouge' },
    { value: '#eab308', label: 'Jaune' },
    { value: '#60a5fa', label: 'Bleu' },
    { value: '#f97316', label: 'Orange' },
    { value: '#a78bfa', label: 'Violet' },
    { value: '#f8fafc', label: 'Blanc' }
  ];
  var MARKER_SIZES = [
    { value: 'sm', label: 'Petit' },
    { value: 'md', label: 'Moyen' },
    { value: 'lg', label: 'Grand' }
  ];

  function getMap() {
    return window.ATAKMap && window.ATAKMap.getMap ? window.ATAKMap.getMap() : null;
  }

  function getApiBase() {
    return window.ATAKSocket && window.ATAKSocket.getApiBase ? window.ATAKSocket.getApiBase() : '';
  }

  function getMapId() {
    return window.ATAKSocket && window.ATAKSocket.getMapId ? window.ATAKSocket.getMapId() : 1;
  }

  function getAuthor() {
    var u = window.ATAK_USER;
    if (u && (u.callsign || u.displayName)) return u.callsign || u.displayName;
    return 'Opérateur';
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function gridLabel(latlng) {
    if (!latlng) return '—';
    return Math.round(latlng.lng) + ' / ' + Math.round(latlng.lat);
  }

  function featureTitle(feature) {
    if (!feature) return '';
    var kind = '';
    if (feature.shape && feature.shape.meta) kind = String(feature.shape.meta.kind || '');
    else if (feature.meta) kind = String(feature.meta.kind || '');
    if (kind === 'search_zone') return 'Zone de recherche';
    if (kind === 'perimeter') return 'Périmètre';
    if (kind === 'aoi') return 'Zone d’intérêt';
    var t = feature.featureType;
    if (t === 'marker') return 'Marqueur';
    if (t === 'comment') return 'Commentaire';
    if (t === 'line') return 'Trait';
    if (t === 'zone') return 'Zone';
    if (t === 'ping') return 'Ping';
    return 'Élément';
  }

  function ensureMenu() {
    if (menuEl) return menuEl;
    menuEl = document.createElement('div');
    menuEl.id = 'atak-ctx-menu';
    menuEl.className = 'atak-ctx-menu';
    menuEl.setAttribute('role', 'menu');
    menuEl.hidden = true;
    menuEl.innerHTML = '<div class="atak-ctx-menu__coords" id="atak-ctx-coords"></div><div id="atak-ctx-items"></div>';
    document.body.appendChild(menuEl);
    menuEl.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-action]');
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();
      var action = btn.getAttribute('data-action');
      hideMenu();
      runAction(action);
    });
    return menuEl;
  }

  function renderCreateItems() {
    return '' +
      '<button type="button" class="atak-ctx-menu__item atak-ctx-menu__item--jackpot" data-action="jackpot" role="menuitem">JACKPOT — HVT</button>' +
      '<button type="button" class="atak-ctx-menu__item" data-action="marker" role="menuitem">Placer un marqueur</button>' +
      '<button type="button" class="atak-ctx-menu__item" data-action="sitrep" role="menuitem">Signaler une situation</button>' +
      '<button type="button" class="atak-ctx-menu__item" data-action="ping" role="menuitem">Envoyer un ping</button>' +
      '<button type="button" class="atak-ctx-menu__item" data-action="line" role="menuitem">Tracer un trait</button>' +
      '<button type="button" class="atak-ctx-menu__item" data-action="comment" role="menuitem">Enregistrer une note</button>' +
      '<button type="button" class="atak-ctx-menu__item" data-action="zone" role="menuitem">Zone circulaire</button>' +
      '<button type="button" class="atak-ctx-menu__item" data-action="search-zone" role="menuitem">Zone de recherche</button>' +
      '<button type="button" class="atak-ctx-menu__item" data-action="perimeter" role="menuitem">Périmètre de sécurité</button>' +
      '<button type="button" class="atak-ctx-menu__item" data-action="aoi" role="menuitem">Zone d’intérêt</button>' +
      '<div class="atak-ctx-menu__sep" role="separator"></div>' +
      '<button type="button" class="atak-ctx-menu__item atak-ctx-menu__item--muted" data-action="copy" role="menuitem">Copier les coordonnées</button>';
  }

  function renderFeatureItems(feature) {
    var title = featureTitle(feature);
    var name = (feature.label || '').trim();
    var header = '<div class="atak-ctx-menu__feature">' + escapeHtml(title) +
      (name ? ' · ' + escapeHtml(name.length > 40 ? name.slice(0, 40) + '…' : name) : '') +
      '</div>';
    if (feature.featureType === 'ping') {
      return header +
        '<button type="button" class="atak-ctx-menu__item atak-ctx-menu__item--danger" data-action="feature-delete" role="menuitem">Supprimer</button>' +
        '<div class="atak-ctx-menu__sep" role="separator"></div>' +
        '<button type="button" class="atak-ctx-menu__item atak-ctx-menu__item--muted" data-action="copy" role="menuitem">Copier les coordonnées</button>';
    }
    return header +
      '<button type="button" class="atak-ctx-menu__item" data-action="feature-rename" role="menuitem">Renommer</button>' +
      '<button type="button" class="atak-ctx-menu__item" data-action="feature-edit" role="menuitem">Modifier</button>' +
      '<button type="button" class="atak-ctx-menu__item atak-ctx-menu__item--danger" data-action="feature-delete" role="menuitem">Supprimer</button>' +
      '<div class="atak-ctx-menu__sep" role="separator"></div>' +
      '<button type="button" class="atak-ctx-menu__item atak-ctx-menu__item--muted" data-action="copy" role="menuitem">Copier les coordonnées</button>';
  }

  function fillMenuContent(feature) {
    ensureMenu();
    var items = document.getElementById('atak-ctx-items');
    if (!items) return;
    items.innerHTML = feature ? renderFeatureItems(feature) : renderCreateItems();
  }

  function ensurePrompt() {
    if (promptEl) return promptEl;
    promptEl = document.createElement('div');
    promptEl.id = 'atak-input-modal';
    promptEl.className = 'atak-input-modal';
    promptEl.hidden = true;
    promptEl.setAttribute('aria-hidden', 'true');
    promptEl.innerHTML =
      '<div class="atak-input-modal__backdrop" data-atak-prompt-cancel></div>' +
      '<div class="atak-input-modal__box" role="dialog" aria-modal="true" aria-labelledby="atak-input-modal-title">' +
      '<h3 class="atak-input-modal__title" id="atak-input-modal-title"></h3>' +
      '<p class="atak-input-modal__hint" id="atak-input-modal-hint"></p>' +
      '<input type="text" class="atak-input-modal__field" id="atak-input-modal-field" maxlength="200" autocomplete="off" />' +
      '<div class="atak-input-modal__actions">' +
      '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--ghost" data-atak-prompt-cancel>Annuler</button>' +
      '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--primary" id="atak-input-modal-ok">Valider</button>' +
      '</div></div>';
    document.body.appendChild(promptEl);

    function cancel() {
      closePrompt(null);
    }
    promptEl.querySelectorAll('[data-atak-prompt-cancel]').forEach(function (el) {
      el.addEventListener('click', cancel);
    });
    document.getElementById('atak-input-modal-ok').addEventListener('click', function () {
      var field = document.getElementById('atak-input-modal-field');
      closePrompt(field ? field.value : '');
    });
    document.getElementById('atak-input-modal-field').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        closePrompt(this.value);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        closePrompt(null);
      }
    });
    return promptEl;
  }

  function openPrompt(title, hint, placeholder, defaultValue) {
    ensurePrompt();
    var titleEl = document.getElementById('atak-input-modal-title');
    var hintElLocal = document.getElementById('atak-input-modal-hint');
    var field = document.getElementById('atak-input-modal-field');
    if (titleEl) titleEl.textContent = title || '';
    if (hintElLocal) {
      hintElLocal.textContent = hint || '';
      hintElLocal.hidden = !hint;
    }
    if (field) {
      field.value = defaultValue != null ? String(defaultValue) : '';
      field.placeholder = placeholder || '';
    }
    promptEl.hidden = false;
    promptEl.setAttribute('aria-hidden', 'false');
    setTimeout(function () { if (field) field.focus(); }, 30);
    return new Promise(function (resolve) {
      promptResolve = resolve;
    });
  }

  function closePrompt(value) {
    if (!promptEl) return;
    promptEl.hidden = true;
    promptEl.setAttribute('aria-hidden', 'true');
    var cb = promptResolve;
    promptResolve = null;
    if (cb) cb(value);
  }

  var pingFormEl = null;
  var pingFormResolve = null;
  var PING_KINDS = [
    { value: 'contact', label: 'Contact' },
    { value: 'hostile', label: 'Hostile' },
    { value: 'jackpot', label: 'JACKPOT (HVT)' },
    { value: 'medical', label: 'Médical' },
    { value: 'rally', label: 'Ralliement' },
    { value: 'objective', label: 'Objectif' },
    { value: 'info', label: 'Info' }
  ];

  function ensurePingForm() {
    if (pingFormEl) return pingFormEl;
    pingFormEl = document.createElement('div');
    pingFormEl.id = 'atak-ping-modal';
    pingFormEl.className = 'atak-input-modal';
    pingFormEl.hidden = true;
    pingFormEl.setAttribute('aria-hidden', 'true');
    pingFormEl.innerHTML =
      '<div class="atak-input-modal__backdrop" data-atak-ping-cancel></div>' +
      '<div class="atak-input-modal__box atak-marker-form" role="dialog" aria-modal="true" aria-labelledby="atak-ping-modal-title">' +
      '<h3 class="atak-input-modal__title" id="atak-ping-modal-title">Envoyer un ping</h3>' +
      '<p class="atak-input-modal__hint">Choisissez le type de repère, puis un message optionnel.</p>' +
      '<fieldset class="atak-marker-form__fieldset"><legend>Type de ping</legend>' +
      '<div class="atak-marker-form__choices" id="atak-ping-kinds"></div></fieldset>' +
      '<label class="atak-marker-form__label">Message' +
      '<input type="text" class="atak-input-modal__field" id="atak-ping-message" maxlength="160" placeholder="Ex. mouvement au nord" autocomplete="off" /></label>' +
      '<div class="atak-input-modal__actions">' +
      '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--ghost" data-atak-ping-cancel>Annuler</button>' +
      '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--primary" id="atak-ping-ok">Envoyer</button>' +
      '</div></div>';
    document.body.appendChild(pingFormEl);
    document.getElementById('atak-ping-kinds').innerHTML = optionButtons('pingkind', PING_KINDS, 'contact');
    function cancel() { closePingForm(null); }
    pingFormEl.querySelectorAll('[data-atak-ping-cancel]').forEach(function (el) {
      el.addEventListener('click', cancel);
    });
    document.getElementById('atak-ping-ok').addEventListener('click', function () {
      var kind = (pingFormEl.querySelector('input[name="pingkind"]:checked') || {}).value || 'info';
      var msgEl = document.getElementById('atak-ping-message');
      closePingForm({ kind: kind, message: msgEl ? msgEl.value : '' });
    });
    document.getElementById('atak-ping-message').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('atak-ping-ok').click();
      } else if (e.key === 'Escape') {
        e.preventDefault();
        closePingForm(null);
      }
    });
    return pingFormEl;
  }

  function openPingForm() {
    ensurePingForm();
    var def = pingFormEl.querySelector('input[name="pingkind"][value="contact"]') ||
      pingFormEl.querySelector('input[name="pingkind"]');
    if (def) def.checked = true;
    var msg = document.getElementById('atak-ping-message');
    if (msg) msg.value = '';
    pingFormEl.hidden = false;
    pingFormEl.setAttribute('aria-hidden', 'false');
    setTimeout(function () { if (msg) msg.focus(); }, 30);
    return new Promise(function (resolve) {
      pingFormResolve = resolve;
    });
  }

  function closePingForm(value) {
    if (!pingFormEl) return;
    pingFormEl.hidden = true;
    pingFormEl.setAttribute('aria-hidden', 'true');
    var cb = pingFormResolve;
    pingFormResolve = null;
    if (cb) cb(value);
  }

  var colorPickEl = null;
  var colorPickResolve = null;

  function ensureColorPick() {
    if (colorPickEl) return colorPickEl;
    colorPickEl = document.createElement('div');
    colorPickEl.id = 'atak-color-modal';
    colorPickEl.className = 'atak-input-modal';
    colorPickEl.hidden = true;
    colorPickEl.setAttribute('aria-hidden', 'true');
    colorPickEl.innerHTML =
      '<div class="atak-input-modal__backdrop" data-atak-color-cancel></div>' +
      '<div class="atak-input-modal__box atak-marker-form" role="dialog" aria-modal="true" aria-labelledby="atak-color-modal-title">' +
      '<h3 class="atak-input-modal__title" id="atak-color-modal-title">Couleur</h3>' +
      '<p class="atak-input-modal__hint">Choisissez la couleur affichée sur la carte.</p>' +
      '<fieldset class="atak-marker-form__fieldset"><legend>Couleur</legend><div class="atak-marker-form__choices" id="atak-color-choices"></div></fieldset>' +
      '<div class="atak-input-modal__actions">' +
      '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--ghost" data-atak-color-cancel>Annuler</button>' +
      '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--primary" id="atak-color-ok">Valider</button>' +
      '</div></div>';
    document.body.appendChild(colorPickEl);
    document.getElementById('atak-color-choices').innerHTML = optionButtons('pickcolor', MARKER_COLORS, '#34d399');
    function cancel() { closeColorPick(null); }
    colorPickEl.querySelectorAll('[data-atak-color-cancel]').forEach(function (el) {
      el.addEventListener('click', cancel);
    });
    document.getElementById('atak-color-ok').addEventListener('click', function () {
      var checked = colorPickEl.querySelector('input[name="pickcolor"]:checked');
      closeColorPick(checked ? checked.value : '#34d399');
    });
    return colorPickEl;
  }

  function openColorPick(selected) {
    ensureColorPick();
    var val = selected || '#34d399';
    var input = colorPickEl.querySelector('input[name="pickcolor"][value="' + val + '"]') ||
      colorPickEl.querySelector('input[name="pickcolor"][value="#34d399"]');
    if (input) input.checked = true;
    colorPickEl.hidden = false;
    colorPickEl.setAttribute('aria-hidden', 'false');
    return new Promise(function (resolve) {
      colorPickResolve = resolve;
    });
  }

  function closeColorPick(value) {
    if (!colorPickEl) return;
    colorPickEl.hidden = true;
    colorPickEl.setAttribute('aria-hidden', 'true');
    var cb = colorPickResolve;
    colorPickResolve = null;
    if (cb) cb(value);
  }

  function optionButtons(name, items, selected) {
    return items.map(function (it) {
      var checked = it.value === selected ? ' checked' : '';
      var swatch = name === 'color'
        ? '<span class="atak-marker-form__swatch" style="background:' + it.value + '"></span>'
        : '';
      return '<label class="atak-marker-form__choice">' +
        '<input type="radio" name="' + name + '" value="' + escapeHtml(it.value) + '"' + checked + ' />' +
        swatch + '<span>' + escapeHtml(it.label) + '</span></label>';
    }).join('');
  }

  function ensureMarkerForm() {
    if (markerFormEl) return markerFormEl;
    markerFormEl = document.createElement('div');
    markerFormEl.id = 'atak-marker-modal';
    markerFormEl.className = 'atak-input-modal';
    markerFormEl.hidden = true;
    markerFormEl.setAttribute('aria-hidden', 'true');
    markerFormEl.innerHTML =
      '<div class="atak-input-modal__backdrop" data-atak-marker-cancel></div>' +
      '<div class="atak-input-modal__box atak-marker-form" role="dialog" aria-modal="true" aria-labelledby="atak-marker-modal-title">' +
      '<h3 class="atak-input-modal__title" id="atak-marker-modal-title">Nouveau marqueur</h3>' +
      '<p class="atak-input-modal__hint" id="atak-marker-modal-coords"></p>' +
      '<label class="atak-marker-form__label">Libellé' +
      '<input type="text" class="atak-input-modal__field" id="atak-marker-label" maxlength="80" placeholder="Ex. point de ralliement" autocomplete="off" /></label>' +
      '<label class="atak-marker-form__label">Description' +
      '<textarea class="atak-marker-form__textarea" id="atak-marker-desc" maxlength="300" rows="2" placeholder="Précisions utiles pour l’équipe (optionnel)"></textarea></label>' +
      '<div id="atak-marker-symbol-host"></div>' +
      '<fieldset class="atak-marker-form__fieldset" id="atak-marker-color-fieldset"><legend>Couleur (repère simple)</legend><div class="atak-marker-form__choices" id="atak-marker-colors"></div></fieldset>' +
      '<fieldset class="atak-marker-form__fieldset"><legend>Taille</legend><div class="atak-marker-form__choices" id="atak-marker-sizes"></div></fieldset>' +
      '<div class="atak-input-modal__actions">' +
      '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--ghost" data-atak-marker-cancel>Annuler</button>' +
      '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--primary" id="atak-marker-ok">Placer</button>' +
      '</div></div>';
    document.body.appendChild(markerFormEl);

    document.getElementById('atak-marker-colors').innerHTML = optionButtons('color', MARKER_COLORS, '#34d399');
    document.getElementById('atak-marker-sizes').innerHTML = optionButtons('size', MARKER_SIZES, 'md');
    if (window.ATAKSymbolPicker && window.ATAKSymbolPicker.mount) {
      window.ATAKSymbolPicker.mount(document.getElementById('atak-marker-symbol-host'));
    }

    function cancel() { closeMarkerForm(null); }
    markerFormEl.querySelectorAll('[data-atak-marker-cancel]').forEach(function (el) {
      el.addEventListener('click', cancel);
    });
    document.getElementById('atak-marker-ok').addEventListener('click', function () {
      var vals = readMarkerForm();
      if (vals.symbolMode === 'tactical' && !vals.sidc) {
        if (window.ATAKShowError) window.ATAKShowError('Choisissez un symbole tactique dans la liste.');
        return;
      }
      closeMarkerForm(vals);
    });
    document.getElementById('atak-marker-label').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        var vals = readMarkerForm();
        if (vals.symbolMode === 'tactical' && !vals.sidc) {
          if (window.ATAKShowError) window.ATAKShowError('Choisissez un symbole tactique dans la liste.');
          return;
        }
        closeMarkerForm(vals);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        closeMarkerForm(null);
      }
    });
    return markerFormEl;
  }

  function readMarkerForm() {
    var labelEl = document.getElementById('atak-marker-label');
    var descEl = document.getElementById('atak-marker-desc');
    var color = (markerFormEl.querySelector('input[name="color"]:checked') || {}).value || '#34d399';
    var size = (markerFormEl.querySelector('input[name="size"]:checked') || {}).value || 'md';
    var sym = window.ATAKSymbolPicker && window.ATAKSymbolPicker.readValue
      ? window.ATAKSymbolPicker.readValue()
      : { symbolMode: 'simple', icon: 'pin' };
    var out = {
      label: labelEl ? labelEl.value : '',
      description: descEl ? descEl.value : '',
      color: color,
      icon: sym.icon || 'pin',
      size: size,
      symbolMode: sym.symbolMode || 'simple'
    };
    if (sym.symbolMode === 'tactical' && sym.sidc) {
      out.icon = 'milsymbol';
      out.sidc = sym.sidc;
      out.affiliation = sym.affiliation;
      out.symbolId = sym.symbolId;
      out.symbolName = sym.symbolName;
      out.symbolFamily = sym.symbolFamily;
      out.functionid = sym.functionid;
      out.scheme = sym.scheme;
      out.battledimension = sym.battledimension;
      // Couleur d’affiliation indicative pour listes / historique
      if (sym.affiliation === 'hostile') out.color = '#ef4444';
      else if (sym.affiliation === 'neutral') out.color = '#22c55e';
      else if (sym.affiliation === 'unknown') out.color = '#eab308';
      else out.color = '#60a5fa';
    }
    return out;
  }

  function openMarkerForm(latlng, defaults, mode) {
    ensureMarkerForm();
    defaults = defaults || {};
    mode = mode || 'create';
    var titleEl = document.getElementById('atak-marker-modal-title');
    var okBtn = document.getElementById('atak-marker-ok');
    if (titleEl) titleEl.textContent = mode === 'edit' ? 'Modifier le marqueur' : 'Nouveau marqueur';
    if (okBtn) okBtn.textContent = mode === 'edit' ? 'Enregistrer' : 'Placer';
    var coordsEl = document.getElementById('atak-marker-modal-coords');
    if (coordsEl) coordsEl.textContent = 'Position grille : ' + gridLabel(latlng);
    var labelEl = document.getElementById('atak-marker-label');
    var descEl = document.getElementById('atak-marker-desc');
    if (labelEl) labelEl.value = defaults.label != null ? String(defaults.label) : 'Marqueur';
    if (descEl) descEl.value = defaults.description != null ? String(defaults.description) : '';
    var colorVal = defaults.color || '#34d399';
    var sizeVal = defaults.size || 'md';
    var colorDef = markerFormEl.querySelector('input[name="color"][value="' + colorVal + '"]') ||
      markerFormEl.querySelector('input[name="color"][value="#34d399"]');
    var sizeDef = markerFormEl.querySelector('input[name="size"][value="' + sizeVal + '"]') ||
      markerFormEl.querySelector('input[name="size"][value="md"]');
    if (colorDef) colorDef.checked = true;
    if (sizeDef) sizeDef.checked = true;
    if (window.ATAKSymbolPicker && window.ATAKSymbolPicker.reset) {
      window.ATAKSymbolPicker.reset(defaults);
    }
    markerFormEl.hidden = false;
    markerFormEl.setAttribute('aria-hidden', 'false');
    setTimeout(function () {
      if (labelEl) { labelEl.focus(); labelEl.select(); }
    }, 30);
    return new Promise(function (resolve) {
      markerFormResolve = resolve;
    });
  }

  function closeMarkerForm(value) {
    if (!markerFormEl) return;
    markerFormEl.hidden = true;
    markerFormEl.setAttribute('aria-hidden', 'true');
    var cb = markerFormResolve;
    markerFormResolve = null;
    if (cb) cb(value);
  }

  function ensureHint() {
    if (hintEl) return hintEl;
    hintEl = document.createElement('div');
    hintEl.id = 'atak-draw-hint';
    hintEl.className = 'atak-draw-hint';
    hintEl.hidden = true;
    hintEl.innerHTML =
      '<div class="atak-draw-hint__text" id="atak-draw-hint-text"></div>' +
      '<div class="atak-draw-hint__actions" id="atak-draw-hint-actions">' +
      '<button type="button" class="atak-draw-hint__btn atak-draw-hint__btn--primary" data-draw-finish>Terminer</button>' +
      '<button type="button" class="atak-draw-hint__btn" data-draw-cancel>Annuler</button>' +
      '</div>';
    document.body.appendChild(hintEl);
    hintEl.addEventListener('click', function (e) {
      var finish = e.target.closest('[data-draw-finish]');
      var cancel = e.target.closest('[data-draw-cancel]');
      if (finish) {
        e.preventDefault();
        e.stopPropagation();
        if (drawMode === 'line') finishLine();
        else if (drawMode === 'polygon') finishPolygon();
        return;
      }
      if (cancel) {
        e.preventDefault();
        e.stopPropagation();
        cancelDraw();
        if (window.ATAKShowNotification) window.ATAKShowNotification('Dessin annulé.');
        window.dispatchEvent(new CustomEvent('atak:draw-ended'));
      }
    });
    return hintEl;
  }

  function updatePolygonHint() {
    var n = drawPoints.length;
    var label = (drawOpts && drawOpts.defaultLabel) ? drawOpts.defaultLabel : 'Périmètre';
    if (n < 3) {
      showHint(
        label + ' : cliquez pour poser les sommets (minimum 3). Échap ou Annuler pour abandonner.',
        { showFinish: true, finishDisabled: true, finishLabel: 'Terminer' }
      );
    } else {
      showHint(
        label + ' : ' + n + ' sommets. Cliquez pour continuer, ou Terminer / double-clic pour valider.',
        { showFinish: true, finishDisabled: false, finishLabel: 'Terminer' }
      );
    }
  }

  function showHint(text, opts) {
    opts = opts || {};
    ensureHint();
    var textEl = document.getElementById('atak-draw-hint-text');
    var actions = document.getElementById('atak-draw-hint-actions');
    if (textEl) textEl.textContent = text;
    if (actions) {
      actions.hidden = !opts.showFinish;
      var finishBtn = actions.querySelector('[data-draw-finish]');
      if (finishBtn) {
        finishBtn.disabled = !!opts.finishDisabled;
        finishBtn.textContent = opts.finishLabel || 'Terminer';
      }
    }
    hintEl.hidden = false;
  }

  function hideHint() {
    if (hintEl) hintEl.hidden = true;
  }

  function updateLineHint() {
    var n = drawPoints.length;
    if (n < 2) {
      showHint(
        'Trait en cours — cliquez pour placer les points (minimum 2). Échap ou Annuler pour abandonner.',
        { showFinish: true, finishDisabled: true, finishLabel: 'Terminer' }
      );
    } else {
      showHint(
        'Trait : ' + n + ' points. Cliquez pour continuer, ou Terminer / double-clic / Entrée pour valider. Échap pour annuler.',
        { showFinish: true, finishDisabled: false, finishLabel: 'Terminer le trait' }
      );
    }
  }

  function getDrawSpeedKph() {
    if (window.ATAKMapTools && typeof window.ATAKMapTools.getToolSpeedKph === 'function') {
      return window.ATAKMapTools.getToolSpeedKph();
    }
    return 5;
  }

  function zoneMetricsLine(radiusM) {
    var r = Math.max(10, Math.round(Number(radiusM) || 0));
    var tools = window.ATAKMapTools;
    if (tools && typeof tools.circleMetrics === 'function') {
      var m = tools.circleMetrics(r, getDrawSpeedKph());
      return 'Rayon ' + r + ' m · ' + m.summary + ' (à ' + String(m.speedKph).replace('.', ',') + ' km/h)';
    }
    var area = Math.PI * r * r;
    var speed = getDrawSpeedKph();
    var delayS = r / (speed / 3.6);
    var areaLabel = area >= 100000
      ? (area / 1e6).toFixed(2).replace('.', ',') + ' km²'
      : Math.round(area).toLocaleString('fr-FR') + ' m²';
    var delayLabel = delayS < 60
      ? Math.round(delayS) + ' s'
      : Math.round(delayS / 60) + ' min';
    return 'Rayon ' + r + ' m · Superficie : ' + areaLabel + ' · Délai jusqu’au bord : ' + delayLabel;
  }

  function updateZoneDrawHint(radiusM) {
    if (drawMode !== 'zone') return;
    var base = (drawOpts && drawOpts.mapHint) || 'Zone : cliquez pour fixer le rayon.';
    var intro = drawPoints.length < 1
      ? base
      : 'Centre posé — déplacez pour fixer le bord, puis cliquez.';
    showHint(
      intro + ' — ' + zoneMetricsLine(radiusM != null ? radiusM : 0),
      { showFinish: true, finishDisabled: true, finishLabel: 'Terminer' }
    );
  }

  function hideMenu() {
    if (!menuEl) return;
    menuEl.hidden = true;
  }

  function positionMenu(clientX, clientY) {
    ensureMenu();
    menuEl.hidden = false;
    var pad = 8;
    var mw = menuEl.offsetWidth || 220;
    var mh = menuEl.offsetHeight || 260;
    var x = clientX;
    var y = clientY;
    if (x + mw + pad > window.innerWidth) x = window.innerWidth - mw - pad;
    if (y + mh + pad > window.innerHeight) y = window.innerHeight - mh - pad;
    if (x < pad) x = pad;
    if (y < pad) y = pad;
    menuEl.style.left = x + 'px';
    menuEl.style.top = y + 'px';
  }

  function showMenu(clientX, clientY, latlng) {
    activeFeature = null;
    lastLatLng = latlng;
    fillMenuContent(null);
    var coords = document.getElementById('atak-ctx-coords');
    if (coords && latlng) {
      coords.textContent = 'Grille ' + gridLabel(latlng);
    }
    positionMenu(clientX, clientY);
  }

  function showFeatureMenu(detail) {
    if (!detail) return;
    activeFeature = detail;
    lastLatLng = detail.latlng || lastLatLng;
    fillMenuContent(detail);
    var coords = document.getElementById('atak-ctx-coords');
    if (coords) {
      var name = (detail.label || '').trim();
      coords.textContent = featureTitle(detail) + (name ? ' · ' + name : '') +
        (detail.latlng ? ' — grille ' + gridLabel(detail.latlng) : '');
    }
    positionMenu(detail.clientX || 0, detail.clientY || 0);
  }

  function onFeatureContextMenu(ev) {
    if (!ev || !ev.detail) return;
    if (drawMode) {
      cancelDraw();
      return;
    }
    suppressMapContextMenu = true;
    setTimeout(function () { suppressMapContextMenu = false; }, 50);
    showFeatureMenu(ev.detail);
  }

  function onContextMenu(e) {
    if (!e || !e.originalEvent) return;
    e.originalEvent.preventDefault();
    e.originalEvent.stopPropagation();
    if (suppressMapContextMenu) return;
    if (drawMode === 'line') {
      if (drawPoints.length >= 2) {
        finishLine();
      } else {
        cancelDraw();
        if (window.ATAKShowNotification) window.ATAKShowNotification('Trait annulé (pas assez de points).');
      }
      return;
    }
    if (drawMode) {
      cancelDraw();
      return;
    }
    showMenu(e.originalEvent.clientX, e.originalEvent.clientY, e.latlng);
  }

  function bindMap(map) {
    if (!map) return;
    if (boundMap === map) return;
    if (boundMap) {
      try { boundMap.off('contextmenu', onContextMenu); } catch (err) {}
      try { boundMap.off('click', onDrawClick); } catch (err) {}
      try { boundMap.off('dblclick', onDrawDblClick); } catch (err) {}
      try { boundMap.off('mousemove', onDrawMove); } catch (err) {}
    }
    boundMap = map;
    map.on('contextmenu', onContextMenu);
  }

  function clearDrawPreview() {
    var map = getMap();
    if (drawPreview && map) {
      try { map.removeLayer(drawPreview); } catch (err) {}
    }
    drawPreview = null;
  }

  function cancelDraw() {
    var map = getMap();
    if (map) {
      map.off('click', onDrawClick);
      map.off('dblclick', onDrawDblClick);
      map.off('mousemove', onDrawMove);
      if (map.getContainer) map.getContainer().classList.remove('atak-map--drawing');
      if (map.doubleClickZoom) map.doubleClickZoom.enable();
    }
    clearDrawPreview();
    drawMode = null;
    drawPoints = [];
    drawOpts = null;
    hideHint();
  }

  function activeDrawColor() {
    return (drawOpts && drawOpts.color) || (drawMode === 'line' ? '#34d399' : '#eab308');
  }

  function updateDrawPreview(hoverLatLng) {
    var map = getMap();
    if (!map || !drawMode || drawPoints.length === 0) return;
    clearDrawPreview();
    var pts = drawPoints.slice();
    if (hoverLatLng) pts.push(hoverLatLng);
    var color = activeDrawColor();
    if (drawMode === 'line' && pts.length >= 2) {
      drawPreview = L.polyline(pts, { color: color, weight: 2, dashArray: '6 4', opacity: 0.9 });
      drawPreview.addTo(map);
    } else if (drawMode === 'zone' && pts.length >= 1 && hoverLatLng) {
      var r = map.distance(pts[0], hoverLatLng);
      var radiusM = Math.max(r, 10);
      drawPreview = L.circle(pts[0], {
        radius: radiusM,
        color: color,
        fillColor: color,
        fillOpacity: 0.12,
        weight: 2,
        dashArray: '4 4'
      });
      drawPreview.addTo(map);
      updateZoneDrawHint(radiusM);
      window.dispatchEvent(new CustomEvent('atak:zone-radius-preview', {
        detail: { radius: radiusM, kind: drawOpts && drawOpts.kind }
      }));
    } else if (drawMode === 'polygon' && pts.length >= 2) {
      drawPreview = L.polygon(pts, {
        color: color,
        weight: 2,
        fillColor: color,
        fillOpacity: 0.12,
        dashArray: '4 4'
      });
      drawPreview.addTo(map);
    } else if (drawMode === 'rectangle' && pts.length >= 1 && hoverLatLng) {
      var a = pts[0];
      var b = hoverLatLng;
      var corners = [
        L.latLng(a.lat, a.lng),
        L.latLng(a.lat, b.lng),
        L.latLng(b.lat, b.lng),
        L.latLng(b.lat, a.lng)
      ];
      drawPreview = L.polygon(corners, {
        color: color,
        weight: 2,
        fillColor: color,
        fillOpacity: 0.12,
        dashArray: '4 4'
      });
      drawPreview.addTo(map);
    } else if (pts.length === 1) {
      drawPreview = L.circleMarker(pts[0], { radius: 5, color: color, fillColor: color, fillOpacity: 1 });
      drawPreview.addTo(map);
    }
  }

  function onDrawClick(e) {
    if (!drawMode || !e || !e.latlng) return;
    L.DomEvent.stopPropagation(e);
    drawPoints.push(e.latlng);
    if (drawMode === 'zone' && drawPoints.length >= 2) {
      finishZone();
      return;
    }
    if (drawMode === 'rectangle' && drawPoints.length >= 2) {
      finishRectangle();
      return;
    }
    updateDrawPreview(null);
    if (drawMode === 'line') updateLineHint();
    else if (drawMode === 'polygon') updatePolygonHint();
  }

  function onDrawDblClick(e) {
    if (!drawMode) return;
    L.DomEvent.preventDefault(e);
    L.DomEvent.stopPropagation(e);
    if (drawMode === 'line') finishLine();
    else if (drawMode === 'polygon') finishPolygon();
  }

  function onDrawMove(e) {
    if (!drawMode || !e || !e.latlng) return;
    updateDrawPreview(e.latlng);
  }

  function startDraw(mode, startLatLng, opts) {
    cancelDraw();
    var map = getMap();
    if (!map) return;
    drawMode = mode;
    drawOpts = opts || null;
    drawPoints = startLatLng ? [startLatLng] : [];
    if (map.doubleClickZoom) map.doubleClickZoom.disable();
    if (map.getContainer) map.getContainer().classList.add('atak-map--drawing');
    map.on('click', onDrawClick);
    map.on('dblclick', onDrawDblClick);
    map.on('mousemove', onDrawMove);
    updateDrawPreview(null);
    window.dispatchEvent(new CustomEvent('atak:draw-started', { detail: { mode: mode, kind: drawOpts && drawOpts.kind } }));
    if (mode === 'line') {
      updateLineHint();
    } else if (mode === 'polygon') {
      updatePolygonHint();
    } else if (mode === 'rectangle') {
      showHint(
        (drawOpts && drawOpts.mapHint) || 'Rectangle : cliquez un coin, puis le coin opposé. Échap pour abandonner.',
        { showFinish: true, finishDisabled: true, finishLabel: 'Terminer' }
      );
    } else if (mode === 'zone') {
      updateZoneDrawHint(0);
    }
  }

  function startZoneTool(presetKey, startLatLng) {
    var key = String(presetKey || 'zone');
    var preset = ZONE_PRESETS[key] || ZONE_PRESETS.zone;
    startDraw(preset.mode, startLatLng || null, preset);
  }

  function finishLine() {
    if (drawMode !== 'line' || drawPoints.length < 2) {
      if (window.ATAKShowError) window.ATAKShowError('Placez au moins deux points, puis cliquez sur Terminer.');
      updateLineHint();
      return;
    }
    var pts = drawPoints.slice();
    cancelDraw();
    window.dispatchEvent(new CustomEvent('atak:draw-ended'));
    openPrompt('Libellé du trait', 'Optionnel — laissez vide pour un trait sans nom.', 'Ex. axe d’approche', '').then(function (label) {
      if (label === null) return;
      var coordinates = pts.map(function (p) { return [p.lng, p.lat]; });
      var payload = {
        mapId: getMapId(),
        type: 'LINE',
        label: (label || '').trim() || 'Trait',
        color: '#34d399',
        stroke: 2,
        createdBy: getAuthor(),
        geometry: { coordinates: coordinates },
        meta: { category: 'manual', kind: 'line' }
      };
      if (window.ATAKMapShapes && window.ATAKMapShapes.createShape) {
        window.ATAKMapShapes.createShape(payload);
      } else {
        postShape(payload);
      }
    });
  }

  function finishZone() {
    if (drawMode !== 'zone' || drawPoints.length < 2) return;
    var map = getMap();
    var center = drawPoints[0];
    var edge = drawPoints[1];
    var radius = map ? map.distance(center, edge) : 100;
    var radiusM = Math.max(Math.round(radius), 10);
    var opts = drawOpts || ZONE_PRESETS.zone;
    var speedKph = getDrawSpeedKph();
    var metrics = (window.ATAKMapTools && window.ATAKMapTools.circleMetrics)
      ? window.ATAKMapTools.circleMetrics(radiusM, speedKph)
      : null;
    cancelDraw();
    window.dispatchEvent(new CustomEvent('atak:draw-ended'));
    if (window.ATAKMapTools && window.ATAKMapTools.setToolRadiusM) {
      window.ATAKMapTools.setToolRadiusM(radiusM, false);
    }
    if (window.ATAKMapTools && window.ATAKMapTools.refreshZoneMetrics) {
      window.ATAKMapTools.refreshZoneMetrics(radiusM);
    }
    openPrompt(opts.title || 'Libellé de la zone', opts.hint || 'Optionnel.', 'Ex. ' + (opts.defaultLabel || 'zone'), '').then(function (label) {
      if (label === null) return;
      var meta = {
        category: 'manual',
        kind: opts.kind || 'zone',
        radius_m: radiusM,
        speed_kph: speedKph,
        area_m2: metrics ? Math.round(metrics.areaM2) : Math.round(Math.PI * radiusM * radiusM),
        delay_s: metrics ? Math.round(metrics.delayS) : Math.round(radiusM / (speedKph / 3.6))
      };
      var payload = {
        mapId: getMapId(),
        type: 'CIRCLE',
        label: (label || '').trim() || opts.defaultLabel || 'Zone',
        color: opts.color || '#eab308',
        stroke: 2,
        fillOpacity: 0.15,
        createdBy: getAuthor(),
        geometry: { center: [center.lng, center.lat], radius: radiusM },
        meta: meta
      };
      if (window.ATAKMapShapes && window.ATAKMapShapes.createShape) {
        window.ATAKMapShapes.createShape(payload);
      } else {
        postShape(payload);
      }
    });
  }

  function finishPolygon() {
    if (drawMode !== 'polygon' || drawPoints.length < 3) {
      if (window.ATAKShowError) window.ATAKShowError('Placez au moins trois sommets, puis cliquez sur Terminer.');
      updatePolygonHint();
      return;
    }
    var pts = drawPoints.slice();
    var opts = drawOpts || ZONE_PRESETS.perimeter;
    cancelDraw();
    window.dispatchEvent(new CustomEvent('atak:draw-ended'));
    openPrompt(opts.title || 'Nom du périmètre', opts.hint || 'Optionnel.', 'Ex. ' + (opts.defaultLabel || 'périmètre'), '').then(function (label) {
      if (label === null) return;
      var coordinates = pts.map(function (p) { return [p.lng, p.lat]; });
      var payload = {
        mapId: getMapId(),
        type: 'POLYGON',
        label: (label || '').trim() || opts.defaultLabel || 'Périmètre',
        color: opts.color || '#fb923c',
        stroke: 2,
        fillOpacity: 0.12,
        createdBy: getAuthor(),
        geometry: { coordinates: coordinates },
        meta: { category: 'manual', kind: opts.kind || 'perimeter' }
      };
      if (window.ATAKMapShapes && window.ATAKMapShapes.createShape) {
        window.ATAKMapShapes.createShape(payload);
      } else {
        postShape(payload);
      }
    });
  }

  function finishRectangle() {
    if (drawMode !== 'rectangle' || drawPoints.length < 2) return;
    var a = drawPoints[0];
    var b = drawPoints[1];
    var opts = drawOpts || ZONE_PRESETS.aoi;
    cancelDraw();
    window.dispatchEvent(new CustomEvent('atak:draw-ended'));
    var corners = [
      [a.lng, a.lat],
      [b.lng, a.lat],
      [b.lng, b.lat],
      [a.lng, b.lat]
    ];
    openPrompt(opts.title || 'Nom de la zone d’intérêt', opts.hint || 'Optionnel.', 'Ex. ' + (opts.defaultLabel || 'zone d’intérêt'), '').then(function (label) {
      if (label === null) return;
      var payload = {
        mapId: getMapId(),
        type: 'POLYGON',
        label: (label || '').trim() || opts.defaultLabel || 'Zone d’intérêt',
        color: opts.color || '#86efac',
        stroke: 2,
        fillOpacity: 0.12,
        createdBy: getAuthor(),
        geometry: { coordinates: corners },
        meta: { category: 'manual', kind: opts.kind || 'aoi', shape: 'rectangle' }
      };
      if (window.ATAKMapShapes && window.ATAKMapShapes.createShape) {
        window.ATAKMapShapes.createShape(payload);
      } else {
        postShape(payload);
      }
    });
  }

  function postShape(payload) {
    var base = getApiBase();
    if (!base) {
      if (window.ATAKShowError) window.ATAKShowError('Liaison Tacmap indisponible pour enregistrer la forme.');
      return;
    }
    fetch(base + '/api/map-shapes', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) {
      if (!r.ok) {
        if (window.ATAKShowError) window.ATAKShowError('Impossible d’enregistrer la forme.');
        return null;
      }
      return r.json();
    }).then(function (row) {
      if (row && window.ATAKMapShapes && window.ATAKMapShapes.fetchShapes) {
        window.ATAKMapShapes.fetchShapes();
      }
      if (row && window.ATAKShowNotification) window.ATAKShowNotification('Forme enregistrée.');
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible d’enregistrer la forme.');
    });
  }

  function createMarkerAt(latlng, opts) {
    opts = opts || {};
    var base = getApiBase();
    var label = (opts.label || '').trim() || (opts.symbolName || 'Marqueur');
    var markerData = {
      pos: [latlng.lng, latlng.lat],
      label: label,
      description: (opts.description || '').trim(),
      color: opts.color || '#34d399',
      icon: opts.icon || 'pin',
      size: opts.size || 'md',
      type: 'manual',
      author: getAuthor(),
      created_at: new Date().toISOString()
    };
    if (opts.sidc) {
      markerData.sidc = opts.sidc;
      markerData.affiliation = opts.affiliation || 'friend';
      markerData.symbolMode = 'tactical';
      markerData.symbolId = opts.symbolId || null;
      markerData.symbolName = opts.symbolName || null;
      markerData.symbolFamily = opts.symbolFamily || null;
      markerData.functionid = opts.functionid || null;
      markerData.scheme = opts.scheme || null;
      markerData.battledimension = opts.battledimension || null;
      markerData.icon = 'milsymbol';
    } else {
      markerData.symbolMode = opts.symbolMode || 'simple';
    }
    var localId = 'local_m_' + Date.now();
    if (window.ATAKMap && window.ATAKMap.addOrUpdateMarker) {
      window.ATAKMap.addOrUpdateMarker({ id: localId, layerId: 1, data: markerData });
    }
    if (window.ATAKShowNotification) {
      window.ATAKShowNotification('Marqueur placé — grille ' + gridLabel(latlng));
    }
    if (!base) {
      if (window.ATAKMarkers && window.ATAKMarkers.refresh) window.ATAKMarkers.refresh();
      return;
    }
    fetch(base + '/api/markers', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ mapId: getMapId(), layerId: 1, markerData: markerData })
    }).then(function (r) {
      if (!r.ok) {
        if (window.ATAKShowError) window.ATAKShowError('Impossible d’enregistrer le marqueur.');
        return null;
      }
      return r.json();
    }).then(function (row) {
      if (!row) return;
      if (window.ATAKMap && window.ATAKMap.removeMarker) {
        window.ATAKMap.removeMarker({ id: localId });
      }
      if (window.ATAKMap && window.ATAKMap.addOrUpdateMarker) {
        var data = typeof row.markerData === 'string'
          ? (function () { try { return JSON.parse(row.markerData); } catch (e) { return markerData; } })()
          : (row.markerData || markerData);
        window.ATAKMap.addOrUpdateMarker({ id: row.id, layerId: row.layerId != null ? row.layerId : 1, data: data });
      }
      if (window.ATAKMarkers && window.ATAKMarkers.refresh) window.ATAKMarkers.refresh();
      if (window.ATAKShowNotification) window.ATAKShowNotification('Marqueur enregistré — grille ' + gridLabel(latlng));
    }).catch(function () {
      if (window.ATAKShowError) window.ATAKShowError('Impossible d’enregistrer le marqueur.');
    });
  }

  function createCommentAt(latlng, text) {
    var payload = {
      mapId: getMapId(),
      type: 'POINT',
      label: text,
      color: '#60a5fa',
      stroke: 2,
      createdBy: getAuthor(),
      geometry: { center: [latlng.lng, latlng.lat] },
      meta: { category: 'manual', kind: 'comment', author: getAuthor() }
    };
    if (window.ATAKMapShapes && window.ATAKMapShapes.createShape) {
      window.ATAKMapShapes.createShape(payload);
    } else {
      postShape(payload);
    }
    if (window.ATAKShowNotification) {
      window.ATAKShowNotification('Note enregistrée — grille ' + gridLabel(latlng));
    }
  }

  function featureDoneMsg(feature, action) {
    var t = feature && feature.featureType;
    if (action === 'rename') {
      if (t === 'zone') return 'Zone renommée.';
      if (t === 'line') return 'Trait renommé.';
      if (t === 'comment') return 'Commentaire renommé.';
      return 'Élément renommé.';
    }
    if (t === 'zone') return 'Zone mise à jour.';
    if (t === 'line') return 'Trait mis à jour.';
    if (t === 'comment') return 'Commentaire mis à jour.';
    return 'Élément mis à jour.';
  }

  function featureDeletedMsg(feature) {
    var t = feature && feature.featureType;
    if (t === 'zone') return 'Zone supprimée.';
    if (t === 'line') return 'Trait supprimé.';
    if (t === 'comment') return 'Commentaire supprimé.';
    return 'Élément supprimé.';
  }

  function currentFeatureLabel(feature) {
    if (!feature) return '';
    if (feature.featureType === 'marker' && feature.data) {
      return feature.data.label || feature.data.text || feature.data.name || feature.label || '';
    }
    if (feature.shape) return feature.shape.label || feature.label || '';
    return feature.label || '';
  }

  function renameFeature(feature) {
    var current = currentFeatureLabel(feature);
    var title = 'Renommer — ' + featureTitle(feature);
    openPrompt(title, 'Nouveau libellé affiché sur la carte.', 'Ex. point de ralliement', current).then(function (label) {
      if (label === null) return;
      var next = (label || '').trim();
      if (!next) {
        if (window.ATAKShowError) window.ATAKShowError('Saisissez un libellé.');
        return;
      }
      if (feature.featureType === 'marker') {
        if (!window.ATAKMap || !window.ATAKMap.updateMarkerById) return;
        window.ATAKMap.updateMarkerById(feature.id, { label: next }).then(function () {
          if (window.ATAKShowNotification) window.ATAKShowNotification('Marqueur renommé.');
        }).catch(function () {
          if (window.ATAKShowError) window.ATAKShowError('Impossible de renommer le marqueur.');
        });
        return;
      }
      if (window.ATAKMapShapes && window.ATAKMapShapes.updateShape) {
        window.ATAKMapShapes.updateShape(feature.id, { label: next }).then(function (row) {
          if (row && window.ATAKShowNotification) {
            window.ATAKShowNotification(featureDoneMsg(feature, 'rename'));
          }
        });
      }
    });
  }

  function editFeature(feature) {
    if (feature.featureType === 'marker') {
      var data = feature.data || {};
      var ll = lastLatLng;
      if (!ll && data.pos) {
        ll = L.latLng(data.pos[1], data.pos[0]);
      }
      openMarkerForm(ll || { lat: 0, lng: 0 }, {
        label: data.label || data.symbolName || 'Marqueur',
        description: data.description || '',
        color: data.color || '#34d399',
        icon: data.icon || 'pin',
        size: data.size || 'md',
        sidc: data.sidc || null,
        affiliation: data.affiliation || 'friend',
        symbolMode: data.symbolMode || (data.sidc ? 'tactical' : 'simple'),
        symbolId: data.symbolId || null,
        symbolFamily: data.symbolFamily || null,
        symbolName: data.symbolName || null
      }, 'edit').then(function (opts) {
        if (!opts) return;
        if (!window.ATAKMap || !window.ATAKMap.updateMarkerById) return;
        var patch = {
          label: (opts.label || '').trim() || opts.symbolName || 'Marqueur',
          description: (opts.description || '').trim(),
          color: opts.color,
          icon: opts.icon,
          size: opts.size,
          symbolMode: opts.symbolMode || 'simple'
        };
        if (opts.sidc) {
          patch.sidc = opts.sidc;
          patch.affiliation = opts.affiliation;
          patch.symbolId = opts.symbolId;
          patch.symbolName = opts.symbolName;
          patch.symbolFamily = opts.symbolFamily;
          patch.functionid = opts.functionid;
          patch.scheme = opts.scheme;
          patch.battledimension = opts.battledimension;
          patch.icon = 'milsymbol';
        } else {
          patch.sidc = null;
          patch.affiliation = null;
          patch.symbolId = null;
          patch.symbolName = null;
          patch.symbolFamily = null;
          patch.functionid = null;
        }
        window.ATAKMap.updateMarkerById(feature.id, patch).then(function () {
          if (window.ATAKShowNotification) window.ATAKShowNotification('Marqueur mis à jour.');
        }).catch(function () {
          if (window.ATAKShowError) window.ATAKShowError('Impossible de modifier le marqueur.');
        });
      });
      return;
    }
    if (feature.featureType === 'comment') {
      var current = currentFeatureLabel(feature);
      openPrompt('Modifier le commentaire', 'Texte visible sur la carte pour votre équipe.', 'Ex. couverture au nord', current).then(function (text) {
        if (text === null) return;
        var t = (text || '').trim();
        if (!t) {
          if (window.ATAKShowError) window.ATAKShowError('Saisissez un commentaire.');
          return;
        }
        if (window.ATAKMapShapes && window.ATAKMapShapes.updateShape) {
          window.ATAKMapShapes.updateShape(feature.id, { label: t }).then(function (row) {
            if (row && window.ATAKShowNotification) window.ATAKShowNotification(featureDoneMsg(feature, 'edit'));
          });
        }
      });
      return;
    }
    // Trait / zone : libellé + couleur (choix nommés) ; cercle → rafraîchir délai (vitesse barre d’outils)
    var shape = feature.shape || {};
    var currentLabel = shape.label || feature.label || '';
    var currentColor = shape.color || (feature.featureType === 'zone' ? '#eab308' : '#34d399');
    var isCircle = String(shape.type || '').toUpperCase() === 'CIRCLE';
    openPrompt('Libellé — ' + featureTitle(feature), 'Modifiez le nom affiché.', 'Ex. axe d’approche', currentLabel).then(function (label) {
      if (label === null) return;
      var nextLabel = (label || '').trim() || featureTitle(feature);
      openColorPick(currentColor).then(function (color) {
        if (color === null) return;
        if (!window.ATAKMapShapes || !window.ATAKMapShapes.updateShape) return;
        var patch = { label: nextLabel, color: color };
        if (isCircle) {
          var geom = shape.geometry || {};
          var radiusM = geom.radius != null ? Number(geom.radius) : NaN;
          var speedKph = getDrawSpeedKph();
          var prevMeta = Object.assign({}, shape.meta || {});
          if (isFinite(radiusM) && radiusM > 0) {
            var metrics = (window.ATAKMapTools && window.ATAKMapTools.circleMetrics)
              ? window.ATAKMapTools.circleMetrics(radiusM, speedKph)
              : null;
            prevMeta.radius_m = Math.round(radiusM);
            prevMeta.speed_kph = speedKph;
            prevMeta.area_m2 = metrics ? Math.round(metrics.areaM2) : Math.round(Math.PI * radiusM * radiusM);
            prevMeta.delay_s = metrics ? Math.round(metrics.delayS) : Math.round(radiusM / (speedKph / 3.6));
            patch.meta = prevMeta;
          }
        }
        window.ATAKMapShapes.updateShape(feature.id, patch).then(function (row) {
          if (row && window.ATAKShowNotification) {
            window.ATAKShowNotification(featureDoneMsg(feature, 'edit'));
          }
        });
      });
    });
  }

  function deleteConfirmLabel(feature) {
    var t = feature && feature.featureType;
    if (t === 'marker') return 'ce marqueur';
    if (t === 'ping') return 'ce ping';
    if (t === 'comment') return 'ce commentaire';
    if (t === 'line') return 'ce trait';
    if (t === 'zone') return 'cette zone';
    return 'cet élément';
  }

  var confirmEl = null;
  var confirmResolve = null;

  function ensureConfirm() {
    if (confirmEl) return confirmEl;
    confirmEl = document.createElement('div');
    confirmEl.id = 'atak-confirm-modal';
    confirmEl.className = 'atak-input-modal';
    confirmEl.hidden = true;
    confirmEl.setAttribute('aria-hidden', 'true');
    confirmEl.innerHTML =
      '<div class="atak-input-modal__backdrop" data-atak-confirm-cancel></div>' +
      '<div class="atak-input-modal__box" role="dialog" aria-modal="true" aria-labelledby="atak-confirm-title">' +
      '<h3 class="atak-input-modal__title" id="atak-confirm-title">Confirmation</h3>' +
      '<p class="atak-input-modal__hint" id="atak-confirm-message"></p>' +
      '<div class="atak-input-modal__actions">' +
      '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--ghost" data-atak-confirm-cancel>Annuler</button>' +
      '<button type="button" class="atak-input-modal__btn atak-input-modal__btn--primary" id="atak-confirm-ok">Supprimer</button>' +
      '</div></div>';
    document.body.appendChild(confirmEl);
    function cancel() { closeConfirm(false); }
    confirmEl.querySelectorAll('[data-atak-confirm-cancel]').forEach(function (el) {
      el.addEventListener('click', cancel);
    });
    document.getElementById('atak-confirm-ok').addEventListener('click', function () {
      closeConfirm(true);
    });
    return confirmEl;
  }

  function closeConfirm(ok) {
    if (!confirmEl) return;
    confirmEl.hidden = true;
    confirmEl.setAttribute('aria-hidden', 'true');
    var cb = confirmResolve;
    confirmResolve = null;
    if (cb) cb(!!ok);
  }

  function confirmAction(message) {
    ensureConfirm();
    var msgEl = document.getElementById('atak-confirm-message');
    if (msgEl) msgEl.textContent = message || 'Confirmer cette action ?';
    confirmEl.hidden = false;
    confirmEl.setAttribute('aria-hidden', 'false');
    return new Promise(function (resolve) {
      confirmResolve = resolve;
    });
  }

  function deleteFeature(feature) {
    confirmAction('Supprimer ' + deleteConfirmLabel(feature) + ' ?').then(function (ok) {
      if (!ok || !feature) return;
      if (feature.featureType === 'ping') {
        if (window.ATAKPings && window.ATAKPings.deletePing) {
          window.ATAKPings.deletePing(feature.id).then(function () {
            if (window.ATAKShowNotification) window.ATAKShowNotification('Ping supprimé.');
          }).catch(function () {
            if (window.ATAKShowError) window.ATAKShowError('Impossible de supprimer le ping.');
          });
        } else if (window.ATAKMap && window.ATAKMap.removeTemporaryPingMarker) {
          window.ATAKMap.removeTemporaryPingMarker(feature.id);
        }
        return;
      }
      if (feature.featureType === 'marker') {
        if (!window.ATAKMap || !window.ATAKMap.deleteMarkerById) return;
        window.ATAKMap.deleteMarkerById(feature.id).then(function () {
          if (window.ATAKMarkers && window.ATAKMarkers.refresh) window.ATAKMarkers.refresh();
          if (window.ATAKShowNotification) window.ATAKShowNotification('Marqueur supprimé.');
        }).catch(function () {
          if (window.ATAKShowError) window.ATAKShowError('Impossible de supprimer le marqueur.');
        });
        return;
      }
      if (window.ATAKMapShapes && window.ATAKMapShapes.deleteShape) {
        window.ATAKMapShapes.deleteShape(feature.id).then(function (okDel) {
          if (okDel && window.ATAKShowNotification) {
            window.ATAKShowNotification(featureDeletedMsg(feature));
          }
        });
      }
    });
  }

  function runAction(action) {
    if (action === 'feature-rename') {
      if (activeFeature) renameFeature(activeFeature);
      return;
    }
    if (action === 'feature-edit') {
      if (activeFeature) editFeature(activeFeature);
      return;
    }
    if (action === 'feature-delete') {
      if (activeFeature) deleteFeature(activeFeature);
      return;
    }
    if (!lastLatLng) return;
    var ll = lastLatLng;
    if (action === 'copy') {
      var text = gridLabel(ll);
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          if (window.ATAKShowNotification) window.ATAKShowNotification('Coordonnées copiées : ' + text);
        }).catch(function () {
          if (window.ATAKShowNotification) window.ATAKShowNotification(text);
        });
      } else if (window.ATAKShowNotification) {
        window.ATAKShowNotification(text);
      }
      return;
    }
    if (action === 'ping') {
      openPingForm().then(function (vals) {
        if (!vals) return;
        if (window.ATAKPings && window.ATAKPings.createPingAt) {
          window.ATAKPings.createPingAt(ll.lng, ll.lat, vals.message || '', vals.kind || 'info');
        }
      });
      return;
    }
    if (action === 'marker') {
      openMarkerForm(ll).then(function (opts) {
        if (!opts) return;
        createMarkerAt(ll, opts);
      });
      return;
    }
    if (action === 'sitrep') {
      if (window.ATAKSitrep && typeof window.ATAKSitrep.prefillFromMap === 'function') {
        window.ATAKSitrep.prefillFromMap(ll);
      } else if (window.ATAKShowError) {
        window.ATAKShowError('Tableau de situation indisponible.');
      }
      return;
    }
    if (action === 'comment') {
      openPrompt('Enregistrer une note', 'Note visible sur la carte pour votre équipe.', 'Ex. couverture au nord', '').then(function (text) {
        if (text === null) return;
        var t = (text || '').trim();
        if (!t) {
          if (window.ATAKShowError) window.ATAKShowError('Saisissez une note.');
          return;
        }
        createCommentAt(ll, t);
      });
      return;
    }
    if (action === 'jackpot') {
      placeJackpotAt(ll);
      return;
    }
    if (action === 'line') {
      startDraw('line', ll);
      return;
    }
    if (action === 'zone') {
      startZoneTool('zone', ll);
      return;
    }
    if (action === 'search-zone') {
      startZoneTool('search', ll);
      return;
    }
    if (action === 'perimeter') {
      startZoneTool('perimeter', null);
      return;
    }
    if (action === 'aoi') {
      startZoneTool('aoi', null);
      return;
    }
  }

  /**
   * Place un marqueur JACKPOT (HVT) + ping associé.
   */
  function placeJackpotAt(latlng, opts) {
    opts = opts || {};
    if (!latlng) return;
    var label = (opts.label || 'JACKPOT').trim() || 'JACKPOT';
    var detail = (opts.detail || '').trim();
    createMarkerAt(latlng, {
      label: label,
      description: detail
        ? ('HVT — ' + detail)
        : 'HVT — cible de haute valeur (JACKPOT)',
      color: '#ef4444',
      icon: 'flag',
      size: 'lg',
      symbolMode: 'simple'
    });
    if (window.ATAKPings && window.ATAKPings.createPingAt) {
      window.ATAKPings.createPingAt(
        latlng.lng,
        latlng.lat,
        detail || 'Cible de haute valeur',
        'jackpot'
      );
    }
    if (window.ATAKShowNotification) {
      window.ATAKShowNotification('JACKPOT enregistré — grille ' + gridLabel(latlng));
    }
  }

  function promptJackpotAt(latlng) {
    if (!latlng) return Promise.resolve(null);
    return openPrompt(
      'JACKPOT — HVT',
      'Repère rouge pour une cible de haute valeur. Précisez un détail si besoin (indicatif, description).',
      'Ex. chef de section, véhicule VIP…',
      ''
    ).then(function (text) {
      if (text === null) return null;
      placeJackpotAt(latlng, { detail: (text || '').trim() });
      return true;
    });
  }

  function promptMapNoteAt(latlng) {
    if (!latlng) return Promise.resolve(null);
    return openPrompt(
      'Enregistrer une note',
      'Note fixée sur la carte à cet emplacement.',
      'Ex. couverture, cache, observation…',
      ''
    ).then(function (text) {
      if (text === null) return null;
      var t = (text || '').trim();
      if (!t) {
        if (window.ATAKShowError) window.ATAKShowError('Saisissez une note.');
        return null;
      }
      createCommentAt(latlng, t);
      return true;
    });
  }

  function onDocClick(e) {
    if (!menuEl || menuEl.hidden) return;
    if (menuEl.contains(e.target)) return;
    hideMenu();
  }

  function onKeyDown(e) {
    if (e.key === 'Escape') {
      if (confirmEl && !confirmEl.hidden) {
        closeConfirm(false);
        return;
      }
      if (pingFormEl && !pingFormEl.hidden) {
        closePingForm(null);
        return;
      }
      if (colorPickEl && !colorPickEl.hidden) {
        closeColorPick(null);
        return;
      }
      if (markerFormEl && !markerFormEl.hidden) {
        closeMarkerForm(null);
        return;
      }
      if (promptEl && !promptEl.hidden) {
        closePrompt(null);
        return;
      }
      if (drawMode) {
        cancelDraw();
        if (window.ATAKShowNotification) window.ATAKShowNotification('Dessin annulé.');
        window.dispatchEvent(new CustomEvent('atak:draw-ended'));
        return;
      }
      hideMenu();
      return;
    }
    if (e.key === 'Enter' && (drawMode === 'line' || drawMode === 'polygon') && !(promptEl && !promptEl.hidden) && !(markerFormEl && !markerFormEl.hidden) && !(colorPickEl && !colorPickEl.hidden) && !(pingFormEl && !pingFormEl.hidden)) {
      e.preventDefault();
      if (drawMode === 'line') finishLine();
      else if (drawMode === 'polygon') finishPolygon();
    }
  }

  function onMapReady() {
    bindMap(getMap());
    cancelDraw();
    hideMenu();
  }

  function init() {
    ensureMenu();
    ensurePrompt();
    ensureMarkerForm();
    ensureHint();
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onKeyDown);
    window.addEventListener('atak:mapready', onMapReady);
    window.addEventListener('atak:feature-contextmenu', onFeatureContextMenu);
    var tries = 0;
    (function retry() {
      var map = getMap();
      if (map) {
        bindMap(map);
        return;
      }
      tries += 1;
      if (tries < 40) setTimeout(retry, 250);
    })();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return {
    hide: hideMenu,
    cancelDraw: cancelDraw,
    startDraw: startDraw,
    startZoneTool: startZoneTool,
    isDrawing: function () { return !!drawMode; },
    getDrawKind: function () { return drawOpts && drawOpts.kind ? drawOpts.kind : (drawMode || null); },
    openPrompt: openPrompt,
    openPingForm: openPingForm,
    finishLine: finishLine,
    confirmAction: confirmAction,
    createMarkerAt: createMarkerAt,
    createCommentAt: createCommentAt,
    placeJackpotAt: placeJackpotAt,
    promptJackpotAt: promptJackpotAt,
    promptMapNoteAt: promptMapNoteAt
  };
})();
