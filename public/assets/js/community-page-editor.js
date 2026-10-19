/*
 * Vitrine communauté : édition en place (gestionnaires).
 *
 * Textes modifiés directement sur la page, blocs déplacés, masqués, ajoutés depuis la bibliothèque, réglages
 * de style dans le panneau latéral. Tout part en brouillon (enregistrement automatique) ; « Publier » le rend
 * visible aux visiteurs. Le serveur revalide tout (PublicPageBuilder::normalize).
 */
(function () {
  'use strict';

  var cfgEl = document.getElementById('pb-editor-config');
  var root = document.querySelector('.cl-vitrine[data-cl-editing]');
  if (!cfgEl || !root) { return; }
  var cfg;
  try { cfg = JSON.parse(cfgEl.textContent || '{}'); } catch (e) { return; }

  var state = {
    theme: cfg.layout.theme || 'light',
    header: Object.assign({}, cfg.layout.header || {}),
    blocks: (cfg.layout.blocks || []).map(cleanBlock),
    fields: Object.assign({}, cfg.fields || {})
  };
  /* PHP encode un tableau vide en [] : on veut des objets pour props et style. */
  function cleanBlock(b) {
    var c = JSON.parse(JSON.stringify(b));
    if (!c.props || Array.isArray(c.props)) { c.props = {}; }
    if (!c.style || Array.isArray(c.style)) { c.style = {}; }
    return c;
  }
  var dirty = false;
  var saving = null;
  var saveTimer = null;
  var selectedId = null;
  var insertAfterId = null;

  var LABELS = {
    theme: ['Fond', { auto: 'Sans fond', light: 'Clair', tinted: 'Teinté', dark: 'Sombre', accent: 'Couleur' }],
    spacing: ['Espacement', { standard: 'Normal', compact: 'Serré', spacious: 'Aéré' }],
    width: ['Largeur', { standard: 'Contenu', narrow: 'Étroit', full: 'Pleine largeur' }],
    align: ['Alignement', { left: 'Gauche', center: 'Centré' }],
    height: ['Hauteur', { standard: 'Normale', compact: 'Basse', tall: 'Plein écran' }],
    overlay: ['Voile sur l’image', { medium: 'Moyen', light: 'Léger', strong: 'Fort' }],
    columns: ['Colonnes', { 2: '2', 3: '3', 4: '4' }],
    ratio: ['Format des images', { landscape: 'Paysage', square: 'Carré', portrait: 'Portrait', wide: 'Large' }],
    variant: ['Présentation', { overlay: 'Texte sur l’image', card: 'Carte' }],
    tone: ['Couleur', { info: 'Info', success: 'Succès', warning: 'Attention', danger: 'Urgent', neutral: 'Neutre', accent: 'Accent' }],
    icon: ['Icône', { info: 'Info', alert: 'Alerte', check: 'Validé', star: 'Étoile', calendar: 'Agenda', radio: 'Radio', shield: 'Bouclier', none: 'Aucune' }],
    size: ['Taille', { standard: 'Normale', large: 'Grande' }],
    image_side: ['Position de l’image', { right: 'À droite', left: 'À gauche' }],
    orbat_density: ['Densité', { standard: 'Normale', compact: 'Serrée', airy: 'Aérée' }],
    orbat_depth: ['Niveaux affichés', { all: 'Tous', 1: '1', 2: '2', 3: '3' }],
    orbat_colors: ['Couleurs', { unit: 'Par unité', branch: 'Par branche', accent: 'Couleur de la page', mono: 'Sobre' }]
  };
  /* ORBAT public : styles proposés (aperçu en direct, tout est en CSS) et éléments affichables. */
  var ORBAT_STYLES = [
    ['tree', 'Arbre hiérarchique', 'Unités et sous-unités en cascade.', '<rect x="10" y="8" width="62" height="16" rx="3" fill="#24332e"/><rect x="10" y="8" width="62" height="3" fill="#12d18e"/><path d="M18 28v36M18 38h10M18 58h10" stroke="#3c524a" stroke-width="2" fill="none"/><rect x="30" y="31" width="56" height="14" rx="3" fill="#1c2724"/><rect x="30" y="51" width="56" height="14" rx="3" fill="#1c2724"/><rect x="94" y="31" width="56" height="14" rx="3" fill="#1c2724"/>'],
    ['orgchart', 'Organigramme', 'Commandement en haut, sous-unités en dessous.', '<rect x="56" y="6" width="48" height="16" rx="3" fill="#24332e"/><rect x="56" y="6" width="48" height="3" fill="#12d18e"/><path d="M80 22v8M28 30h104M28 30v8M80 30v8M132 30v8" stroke="#3c524a" stroke-width="2" fill="none"/><rect x="8" y="38" width="40" height="14" rx="3" fill="#1c2724"/><rect x="60" y="38" width="40" height="14" rx="3" fill="#1c2724"/><rect x="112" y="38" width="40" height="14" rx="3" fill="#1c2724"/><path d="M28 52v6M18 58h20M18 58v4M38 58v4" stroke="#3c524a" stroke-width="2" fill="none"/><rect x="10" y="62" width="16" height="10" rx="2" fill="#1c2724"/><rect x="30" y="62" width="16" height="10" rx="2" fill="#1c2724"/>'],
    ['cards', 'Cartes par unité', 'Grandes cartes illustrées, une par unité.', '<rect x="8" y="10" width="44" height="58" rx="5" fill="#1c2724"/><rect x="8" y="10" width="44" height="18" rx="5" fill="#12d18e"/><rect x="58" y="10" width="44" height="58" rx="5" fill="#1c2724"/><rect x="58" y="10" width="44" height="18" rx="5" fill="#1e6fbf"/><rect x="108" y="10" width="44" height="58" rx="5" fill="#1c2724"/><rect x="108" y="10" width="44" height="18" rx="5" fill="#c98a12"/><rect x="14" y="22" width="14" height="14" rx="3" fill="#0c1116" stroke="#fff" stroke-width="1.5"/><rect x="64" y="22" width="14" height="14" rx="3" fill="#0c1116" stroke="#fff" stroke-width="1.5"/><rect x="114" y="22" width="14" height="14" rx="3" fill="#0c1116" stroke="#fff" stroke-width="1.5"/><rect x="14" y="42" width="30" height="5" rx="2" fill="#fff"/><rect x="64" y="42" width="30" height="5" rx="2" fill="#fff"/><rect x="114" y="42" width="30" height="5" rx="2" fill="#fff"/>'],
    ['grid', 'Grille d’insignes', 'Tuiles compactes centrées sur l’insigne.', '<g fill="#1c2724"><rect x="8" y="8" width="32" height="30" rx="4"/><rect x="45" y="8" width="32" height="30" rx="4"/><rect x="83" y="8" width="32" height="30" rx="4"/><rect x="120" y="8" width="32" height="30" rx="4"/><rect x="8" y="42" width="32" height="30" rx="4"/><rect x="45" y="42" width="32" height="30" rx="4"/><rect x="83" y="42" width="32" height="30" rx="4"/><rect x="120" y="42" width="32" height="30" rx="4"/></g><g><circle cx="24" cy="21" r="7" fill="#12d18e"/><circle cx="61" cy="21" r="7" fill="#1e6fbf"/><circle cx="99" cy="21" r="7" fill="#12d18e"/><circle cx="136" cy="21" r="7" fill="#c98a12"/><circle cx="24" cy="55" r="7" fill="#1e6fbf"/><circle cx="61" cy="55" r="7" fill="#12d18e"/><circle cx="99" cy="55" r="7" fill="#c98a12"/><circle cx="136" cy="55" r="7" fill="#12d18e"/></g>'],
    ['branches', 'Liste par branche', 'Une colonne par branche, sous-unités en liste.', '<rect x="8" y="8" width="68" height="64" rx="5" fill="#1c2724"/><rect x="84" y="8" width="68" height="64" rx="5" fill="#1c2724"/><rect x="8" y="8" width="68" height="18" rx="5" fill="#24332e"/><rect x="8" y="8" width="3" height="18" fill="#12d18e"/><rect x="84" y="8" width="68" height="18" rx="5" fill="#24332e"/><rect x="84" y="8" width="3" height="18" fill="#1e6fbf"/><g fill="#3c524a"><rect x="14" y="32" width="48" height="4" rx="2"/><rect x="14" y="44" width="40" height="4" rx="2"/><rect x="20" y="56" width="36" height="4" rx="2"/><rect x="90" y="32" width="48" height="4" rx="2"/><rect x="90" y="44" width="44" height="4" rx="2"/></g>'],
    ['strength', 'Vue par effectifs', 'Tableau des unités avec chef et remplissage.', '<g fill="#1c2724"><rect x="8" y="8" width="144" height="14" rx="2"/><rect x="8" y="25" width="144" height="14" rx="2"/><rect x="8" y="42" width="144" height="14" rx="2"/><rect x="8" y="59" width="144" height="14" rx="2"/></g><g fill="#3c524a"><rect x="14" y="13" width="34" height="4" rx="2"/><rect x="22" y="30" width="30" height="4" rx="2"/><rect x="22" y="47" width="30" height="4" rx="2"/><rect x="30" y="64" width="26" height="4" rx="2"/></g><g fill="#24332e"><rect x="96" y="13" width="48" height="4" rx="2"/><rect x="96" y="30" width="48" height="4" rx="2"/><rect x="96" y="47" width="48" height="4" rx="2"/><rect x="96" y="64" width="48" height="4" rx="2"/></g><g fill="#12d18e"><rect x="96" y="13" width="40" height="4" rx="2"/><rect x="96" y="30" width="22" height="4" rx="2"/><rect x="96" y="47" width="46" height="4" rx="2"/><rect x="96" y="64" width="14" height="4" rx="2" fill="#c98a12"/></g>']
  ];
  var ORBAT_SHOW = [
    ['orbat_insignia', 'Insignes'],
    ['orbat_portraits', 'Portraits des chefs'],
    ['orbat_grades', 'Grades'],
    ['orbat_strength', 'Effectifs'],
    ['orbat_slots', 'Places ouvertes'],
    ['orbat_desc', 'Descriptions']
  ];
  var ICONS = {
    up: '<path d="M12 19V5M6 11l6-6 6 6"/>',
    down: '<path d="M12 5v14M6 13l6 6 6-6"/>',
    gear: '<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14 3h-4l-.5 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7 7 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2L10 21h4l.5-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2z"/>',
    copy: '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
    eye: '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    eyeOff: '<path d="M3 3l18 18M10.6 5.1A10 10 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4M6.6 6.6C3.8 8.4 2 12 2 12s3.6 7 10 7a9.6 9.6 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
    trash: '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
    plus: '<path d="M12 5v14M5 12h14"/>'
  };
  var ART = {
    hero: '<rect x="0" y="0" width="160" height="78" fill="#16211d"/><rect x="0" y="40" width="160" height="38" fill="#0c1116" opacity=".7"/><rect x="12" y="34" width="70" height="8" rx="2" fill="#fff"/><rect x="12" y="46" width="50" height="4" rx="2" fill="#7d8a84"/><rect x="12" y="56" width="28" height="9" rx="2" fill="#12d18e"/>',
    tiles: '<rect x="8" y="10" width="44" height="58" rx="4" fill="#1f2c28"/><rect x="58" y="10" width="44" height="58" rx="4" fill="#24332e"/><rect x="108" y="10" width="44" height="58" rx="4" fill="#1f2c28"/><rect x="14" y="54" width="28" height="5" rx="2" fill="#fff"/><rect x="64" y="54" width="28" height="5" rx="2" fill="#fff"/><rect x="114" y="54" width="28" height="5" rx="2" fill="#fff"/>',
    infobox: '<rect x="10" y="20" width="140" height="38" rx="5" fill="#13213a"/><rect x="10" y="20" width="4" height="38" fill="#3b82f6"/><circle cx="32" cy="39" r="8" fill="#3b82f6" opacity=".5"/><rect x="48" y="31" width="60" height="6" rx="2" fill="#fff"/><rect x="48" y="42" width="84" height="4" rx="2" fill="#7d8a84"/>',
    stats: '<rect x="8" y="18" width="44" height="42" rx="3" fill="#121a1c"/><rect x="58" y="18" width="44" height="42" rx="3" fill="#121a1c"/><rect x="108" y="18" width="44" height="42" rx="3" fill="#121a1c"/><rect x="14" y="26" width="24" height="12" rx="2" fill="#fff"/><rect x="64" y="26" width="18" height="12" rx="2" fill="#fff"/><rect x="114" y="26" width="28" height="12" rx="2" fill="#fff"/><rect x="14" y="46" width="30" height="4" rx="2" fill="#12d18e"/><rect x="64" y="46" width="30" height="4" rx="2" fill="#12d18e"/><rect x="114" y="46" width="30" height="4" rx="2" fill="#12d18e"/>',
    textmedia: '<rect x="10" y="22" width="50" height="6" rx="2" fill="#fff"/><rect x="10" y="34" width="64" height="4" rx="2" fill="#7d8a84"/><rect x="10" y="42" width="58" height="4" rx="2" fill="#7d8a84"/><rect x="10" y="50" width="44" height="4" rx="2" fill="#7d8a84"/><rect x="86" y="12" width="64" height="54" rx="4" fill="#24332e"/>',
    cta: '<rect x="8" y="16" width="144" height="46" rx="5" fill="#0c1116" stroke="#24332e"/><rect x="18" y="28" width="62" height="7" rx="2" fill="#fff"/><rect x="18" y="40" width="44" height="4" rx="2" fill="#7d8a84"/><rect x="106" y="32" width="36" height="12" rx="2" fill="#12d18e"/>'
  };

  var $ = function (sel, el) { return (el || document).querySelector(sel); };
  var $$ = function (sel, el) { return Array.prototype.slice.call((el || document).querySelectorAll(sel)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var svg = function (name) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICONS[name] + '</svg>'; };
  var findBlock = function (id) { for (var i = 0; i < state.blocks.length; i++) { if (state.blocks[i].id === id) { return state.blocks[i]; } } return null; };
  var blockIndex = function (id) { for (var i = 0; i < state.blocks.length; i++) { if (state.blocks[i].id === id) { return i; } } return -1; };
  var blockEl = function (id) { return root.querySelector('.pb-block[data-pb-id="' + id + '"]'); };
  var blockLabel = function (b) {
    if (!b) { return ''; }
    if (b.type === 'section') { return cfg.sections[b.key] || b.key; }
    var t = cfg.types[b.type] ? cfg.types[b.type].label : b.type;
    var title = b.props && b.props.title ? ' · ' + b.props.title : '';
    return t + title;
  };

  /* —— Statut et enregistrement —— */
  var dot = $('[data-pbe-dot]');
  var statusEl = $('[data-pbe-status]');
  function setStatus(kind, text) {
    if (dot) { dot.className = 'pbe-dot pbe-dot--' + kind; }
    if (statusEl) { statusEl.textContent = text; }
  }
  function toast(text, isError) {
    var t = document.createElement('div');
    t.className = 'pbe-toast' + (isError ? ' pbe-toast--error' : '');
    t.setAttribute('role', 'status');
    t.textContent = text;
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 3200);
  }
  function payload() {
    return { theme: state.theme, header: state.header, blocks: state.blocks, fields: state.fields };
  }
  function post(url, body, isForm) {
    var opts = {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': cfg.csrf, 'Accept': 'application/json' },
      body: isForm ? body : JSON.stringify(body || {})
    };
    if (!isForm) { opts.headers['Content-Type'] = 'application/json'; }
    return fetch(url, opts).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'Session expirée : rechargez la page.' }; }).then(function (j) {
        if (!r.ok || !j.ok) { throw new Error(j.error || 'Enregistrement impossible.'); }
        return j;
      });
    });
  }
  function markDirty() {
    dirty = true;
    setStatus('dirty', 'Modifications non enregistrées');
    clearTimeout(saveTimer);
    saveTimer = setTimeout(save, 1200);
  }
  function save() {
    clearTimeout(saveTimer);
    if (saving) { return saving.then(function () { return dirty ? save() : null; }); }
    if (!dirty) { return Promise.resolve(); }
    dirty = false;
    setStatus('saving', 'Enregistrement…');
    saving = post(cfg.endpoints.draft, payload()).then(function (j) {
      var t = new Date();
      setStatus(dirty ? 'dirty' : 'saved', dirty ? 'Modifications non enregistrées' : 'Brouillon enregistré à ' + String(t.getHours()).padStart(2, '0') + 'h' + String(t.getMinutes()).padStart(2, '0'));
      cfg.hasDraft = true;
      return j;
    }).catch(function (e) {
      dirty = true;
      setStatus('error', 'Échec de l’enregistrement');
      toast(e.message, true);
      throw e;
    }).finally(function () { saving = null; });
    return saving;
  }
  function saveAndReload(anchorId) {
    try { sessionStorage.setItem('pbe.scroll', String(window.scrollY)); if (anchorId) { sessionStorage.setItem('pbe.select', anchorId); } } catch (e) {}
    dirty = true;
    save().then(function () { window.location.reload(); }).catch(function () {});
  }
  window.addEventListener('beforeunload', function (e) {
    if (dirty || saving) { e.preventDefault(); e.returnValue = ''; }
  });

  /* —— Textes modifiables en place —— */
  var plainOnly = (function () { var d = document.createElement('div'); d.setAttribute('contenteditable', 'plaintext-only'); return d.contentEditable === 'plaintext-only'; })();
  var EDITABLE = '[data-cl-field],[data-cl-sprop],[data-cl-hprop],[data-cl-sub],[data-cl-prop],[data-cl-isub]';
  function isMultiline(el) {
    if (/^(H1|H2|H3|SPAN|A|BUTTON)$/.test(el.tagName)) { return false; }
    var k = el.getAttribute('data-cl-prop') || el.getAttribute('data-cl-isub') || el.getAttribute('data-cl-sub') || el.getAttribute('data-cl-field') || '';
    return !/(^|[_.])(kicker2?|title2?|headline|label|badge|value|name|meta|delay|q|doctrine)$/.test(k);
  }
  function readText(el) {
    /* textContent et non innerText : innerText applique text-transform (surtitres en capitales). */
    var c = el.cloneNode(true);
    if (el.getAttribute('data-pbe-multi') !== '1') {
      var walker = document.createTreeWalker(c, NodeFilter.SHOW_TEXT);
      var tn;
      while ((tn = walker.nextNode())) { tn.nodeValue = tn.nodeValue.replace(/\s+/g, ' '); }
    }
    $$('br', c).forEach(function (br) { br.replaceWith('\n'); });
    $$('div,p', c).forEach(function (d) { d.prepend('\n'); });
    return (c.textContent || '').replace(/\u00a0/g, ' ').replace(/[ \t]*\n[ \t]*/g, '\n').replace(/\n{3,}/g, '\n\n').replace(/[ \t]{2,}/g, ' ').trim();
  }
  function enableEditable(scope) {
    $$(EDITABLE, scope).forEach(function (el) {
      if (el.closest('[data-cl-sub]') !== el && el.closest('[data-cl-sub]')) { return; }
      if (el.hasAttribute('data-cl-sub') && !el.closest('[data-cl-list]')) { return; }
      if (isMultiline(el)) {
        /* Texte sur plusieurs lignes : retours à la ligne en clair, affichés tels quels. */
        var txt = readText(el);
        el.setAttribute('data-pbe-multi', '1');
        el.textContent = txt;
        el.style.whiteSpace = 'pre-line';
      }
      el.setAttribute('contenteditable', plainOnly ? 'plaintext-only' : 'true');
      el.setAttribute('spellcheck', 'true');
      if (!el.dataset.placeholder) { el.dataset.placeholder = 'Texte'; }
    });
  }
  function onTextInput(el) {
    var v = readText(el);
    if (el.hasAttribute('data-cl-field')) {
      state.fields[el.getAttribute('data-cl-field')] = v;
    } else if (el.hasAttribute('data-cl-sprop')) {
      var sp = el.getAttribute('data-cl-sprop').split('.');
      var sb = findBlock('s_' + sp[0]);
      if (sb) { sb.props = sb.props || {}; sb.props[sp[1]] = v; }
    } else if (el.hasAttribute('data-cl-hprop')) {
      state.header[el.getAttribute('data-cl-hprop')] = v;
    } else if (el.hasAttribute('data-cl-sub')) {
      var list = el.closest('[data-cl-list]');
      if (list) {
        state.fields[list.getAttribute('data-cl-list')] = $$('[data-cl-item]', list).map(function (it) {
          var row = {};
          $$('[data-cl-sub]', it).forEach(function (s) { row[s.getAttribute('data-cl-sub')] = readText(s); });
          if (it.hasAttribute('data-cl-item-hl')) { row.highlight = true; }
          return row;
        });
      }
    } else {
      var bel = el.closest('.pb-block');
      var b = bel ? findBlock(bel.getAttribute('data-pb-id')) : null;
      if (!b) { return; }
      if (el.hasAttribute('data-cl-prop')) {
        b.props[el.getAttribute('data-cl-prop')] = v;
      } else {
        var item = el.closest('[data-cl-bitem]');
        var idx = item ? parseInt(item.getAttribute('data-cl-bitem'), 10) : -1;
        if (b.props.items && b.props.items[idx]) { b.props.items[idx][el.getAttribute('data-cl-isub')] = v; }
      }
      if (selectedId === b.id && panelMode === 'block') { refreshPanelInputs(b); }
    }
    markDirty();
  }
  root.addEventListener('input', function (e) {
    var el = e.target.closest && e.target.closest('[contenteditable]');
    if (el && root.contains(el)) { onTextInput(el); }
  });
  root.addEventListener('keydown', function (e) {
    var el = e.target.closest && e.target.closest('[contenteditable]');
    if (!el) { return; }
    if (e.key === 'Enter' && !e.shiftKey && !isMultiline(el)) { e.preventDefault(); el.blur(); }
    if (e.key === 'Escape') { el.blur(); }
  });
  root.addEventListener('paste', function (e) {
    var el = e.target.closest && e.target.closest('[contenteditable]');
    if (!el || plainOnly) { return; }
    e.preventDefault();
    var text = (e.clipboardData || window.clipboardData).getData('text/plain');
    document.execCommand('insertText', false, text);
  });
  /* Les liens ne mènent nulle part en édition (sauf liens explicites du panneau, des fantômes et de la barre). */
  root.addEventListener('click', function (e) {
    var a = e.target.closest('a');
    if (a && root.contains(a) && !a.closest('.pbe-dock,.pbe-panel,.pb-ghost,.pbe-launch') && !a.hasAttribute('data-pbe-action')) {
      var href = a.getAttribute('href') || '';
      if (href.charAt(0) !== '#' || a.hasAttribute('contenteditable')) { e.preventDefault(); }
    }
    var img = e.target.closest('[data-cl-img]');
    if (img) {
      e.preventDefault();
      var bel = img.closest('.pb-block');
      if (bel) { select(bel.getAttribute('data-pb-id')); openBlockPanel(bel.getAttribute('data-pb-id'), img.getAttribute('data-cl-img')); }
      return;
    }
    var blk = e.target.closest('.pb-block');
    if (blk && !e.target.closest('.pbe-bar,.pbe-insert')) { select(blk.getAttribute('data-pb-id')); }
  }, true);

  /* —— Barres de bloc et points d’insertion —— */
  function decorate(el) {
    var id = el.getAttribute('data-pb-id');
    var b = findBlock(id);
    if (!b || el.querySelector(':scope > .pbe-bar')) { return; }
    var bar = document.createElement('div');
    bar.className = 'pbe-bar';
    bar.setAttribute('contenteditable', 'false');
    var custom = b.type !== 'section';
    bar.innerHTML = '<span class="pbe-bar__label"></span>'
      + '<button type="button" class="pbe-icon" data-act="up" aria-label="Monter">' + svg('up') + '</button>'
      + '<button type="button" class="pbe-icon" data-act="down" aria-label="Descendre">' + svg('down') + '</button>'
      + '<button type="button" class="pbe-icon" data-act="settings" aria-label="Réglages du bloc">' + svg('gear') + '</button>'
      + (custom ? '<button type="button" class="pbe-icon" data-act="duplicate" aria-label="Dupliquer">' + svg('copy') + '</button>' : '')
      + '<button type="button" class="pbe-icon" data-act="toggle" aria-label="Masquer ou afficher">' + svg(b.hidden ? 'eyeOff' : 'eye') + '</button>'
      + (custom ? '<button type="button" class="pbe-icon pbe-icon--danger" data-act="delete" aria-label="Supprimer">' + svg('trash') + '</button>' : '');
    bar.querySelector('.pbe-bar__label').textContent = blockLabel(b);
    el.insertBefore(bar, el.firstChild);
    bar.addEventListener('click', function (e) {
      var btn = e.target.closest('button[data-act]');
      if (!btn) { return; }
      e.preventDefault();
      e.stopPropagation();
      act(btn.getAttribute('data-act'), id);
    });
    var ins = document.createElement('div');
    ins.className = 'pbe-insert';
    ins.innerHTML = '<button type="button">' + svg('plus') + 'Ajouter ici</button>';
    ins.querySelector('button').addEventListener('click', function (e) {
      e.preventDefault();
      insertAfterId = id;
      openLibrary();
    });
    el.parentNode.insertBefore(ins, el.nextSibling);
  }
  function refreshBars() {
    $$('.pb-block', root).forEach(function (el) {
      var i = blockIndex(el.getAttribute('data-pb-id'));
      var bar = el.querySelector(':scope > .pbe-bar');
      if (!bar) { return; }
      bar.querySelector('[data-act="up"]').disabled = i <= 0;
      bar.querySelector('[data-act="down"]').disabled = i >= state.blocks.length - 1;
    });
  }
  function select(id) {
    selectedId = id;
    $$('.pb-block.is-selected', root).forEach(function (el) { el.classList.remove('is-selected'); });
    var el = id ? blockEl(id) : null;
    if (el) { el.classList.add('is-selected'); }
  }
  function sameFlow(a, b) { return a && b && a.parentNode === b.parentNode; }
  function act(what, id) {
    var i = blockIndex(id);
    var b = state.blocks[i];
    if (!b) { return; }
    var el = blockEl(id);
    if (what === 'up' || what === 'down') {
      var j = what === 'up' ? i - 1 : i + 1;
      if (j < 0 || j >= state.blocks.length) { return; }
      var other = state.blocks[j];
      state.blocks[i] = other;
      state.blocks[j] = b;
      var oel = blockEl(other.id);
      if (sameFlow(el, oel)) {
        var elIns = el.nextSibling; var oIns = oel.nextSibling;
        if (what === 'up') { oel.parentNode.insertBefore(el, oel); el.parentNode.insertBefore(elIns, oel); }
        else { el.parentNode.insertBefore(oel, el); oel.parentNode.insertBefore(oIns, el); }
        refreshBars();
        el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        markDirty();
      } else {
        saveAndReload(id);
      }
    } else if (what === 'toggle') {
      b.hidden = !b.hidden;
      if (b.hidden) { el.setAttribute('data-pb-hidden', '1'); } else { el.removeAttribute('data-pb-hidden'); }
      el.querySelector(':scope > .pbe-bar [data-act="toggle"]').innerHTML = svg(b.hidden ? 'eyeOff' : 'eye');
      markDirty();
      if (panelMode === 'page') { openPagePanel(); }
    } else if (what === 'delete') {
      if (!window.confirm('Supprimer ce bloc ? Vous pourrez encore annuler en abandonnant le brouillon.')) { return; }
      state.blocks.splice(i, 1);
      var ins = el.nextSibling;
      if (ins && ins.classList && ins.classList.contains('pbe-insert')) { ins.remove(); }
      el.remove();
      if (selectedId === id) { closePanel(); }
      refreshBars();
      markDirty();
    } else if (what === 'duplicate') {
      var copy = JSON.parse(JSON.stringify(b));
      copy.id = 'b_' + Math.random().toString(36).slice(2, 12);
      state.blocks.splice(i + 1, 0, copy);
      renderBlock(copy).then(function (html) { placeNewBlock(copy, html, id); markDirty(); });
    } else if (what === 'settings') {
      select(id);
      openBlockPanel(id);
    }
  }
  function renderBlock(b) {
    return post(cfg.endpoints.block, { block: b }).then(function (j) { return j.html; });
  }
  function htmlToEl(html) {
    var t = document.createElement('template');
    t.innerHTML = html.trim();
    return t.content.firstElementChild;
  }
  function placeNewBlock(b, html, afterId) {
    var ref = afterId ? blockEl(afterId) : null;
    var full = b.style && b.style.width === 'full';
    var refFull = ref ? !ref.parentNode.classList.contains('cl-body') : true;
    if (!ref || full !== refFull) { saveAndReload(b.id); return; }
    var nel = htmlToEl(html);
    var anchor = ref.nextSibling && ref.nextSibling.classList && ref.nextSibling.classList.contains('pbe-insert') ? ref.nextSibling : ref;
    anchor.parentNode.insertBefore(nel, anchor.nextSibling);
    enableEditable(nel);
    decorate(nel);
    refreshBars();
    select(b.id);
    nel.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }
  function replaceBlock(b) {
    return renderBlock(b).then(function (html) {
      var old = blockEl(b.id);
      if (!old) { return; }
      var nel = htmlToEl(html);
      old.replaceWith(nel);
      enableEditable(nel);
      decorate(nel);
      var dup = nel.nextSibling && nel.nextSibling.nextSibling;
      if (dup && dup.classList && dup.classList.contains('pbe-insert') && nel.nextSibling.classList.contains('pbe-insert')) { dup.remove(); }
      refreshBars();
      select(b.id);
    }).catch(function (e) { toast(e.message, true); });
  }
  var rerenderTimer = null;
  function scheduleRerender(b) {
    clearTimeout(rerenderTimer);
    rerenderTimer = setTimeout(function () { replaceBlock(b); }, 350);
  }

  /* —— Panneau latéral —— */
  var panel = $('[data-pbe-panel]');
  var scrim = $('[data-pbe-scrim]');
  var pBody = $('[data-pbe-panel-body]');
  var panelMode = null;
  function openPanel(kicker, title) {
    $('[data-pbe-panel-kicker]').textContent = kicker;
    $('[data-pbe-panel-title]').textContent = title;
    panel.classList.add('is-open');
    panel.setAttribute('aria-hidden', 'false');
    scrim.hidden = false;
    document.body.classList.add('pbe-panel-open');
  }
  function closePanel() {
    panel.classList.remove('is-open');
    panel.setAttribute('aria-hidden', 'true');
    scrim.hidden = true;
    document.body.classList.remove('pbe-panel-open');
    panelMode = null;
  }
  scrim.addEventListener('click', closePanel);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && panelMode && !(e.target.closest && e.target.closest('[contenteditable]'))) { closePanel(); } });

  function seg(name, options, current, onPick, labels) {
    var wrap = document.createElement('div');
    wrap.className = 'pbe-seg';
    wrap.setAttribute('role', 'group');
    options.forEach(function (o) {
      var bt = document.createElement('button');
      bt.type = 'button';
      bt.textContent = labels && labels[o] != null ? labels[o] : o;
      bt.setAttribute('aria-pressed', String(o === current));
      bt.addEventListener('click', function () {
        $$('button', wrap).forEach(function (x) { x.setAttribute('aria-pressed', 'false'); });
        bt.setAttribute('aria-pressed', 'true');
        onPick(o);
      });
      wrap.appendChild(bt);
    });
    return field(name, wrap);
  }
  function field(label, control, hint) {
    var l = document.createElement('div');
    l.className = 'pbe-field';
    var s = document.createElement('span');
    s.textContent = label;
    l.appendChild(s);
    l.appendChild(control);
    if (hint) { var sm = document.createElement('small'); sm.textContent = hint; l.appendChild(sm); }
    return l;
  }
  function input(label, value, onInput, opts) {
    opts = opts || {};
    var el = document.createElement(opts.multiline ? 'textarea' : 'input');
    el.className = 'pbe-input';
    if (!opts.multiline) { el.type = 'text'; }
    if (opts.rows) { el.rows = opts.rows; }
    el.value = value || '';
    if (opts.placeholder) { el.placeholder = opts.placeholder; }
    if (opts.key) { el.setAttribute('data-pbe-key', opts.key); }
    el.addEventListener('input', function () { onInput(el.value); });
    return field(label, el, opts.hint);
  }
  function toggle(label, checked, onChange) {
    var l = document.createElement('label');
    l.className = 'pbe-toggle';
    l.innerHTML = '<span></span><input type="checkbox">';
    l.querySelector('span').textContent = label;
    var cb = l.querySelector('input');
    cb.checked = !!checked;
    cb.addEventListener('change', function () { onChange(cb.checked); });
    return l;
  }
  function group(title) {
    var g = document.createElement('section');
    g.className = 'pbe-group';
    if (title) { var h = document.createElement('h3'); h.className = 'pbe-group__title'; h.textContent = title; g.appendChild(h); }
    pBody.appendChild(g);
    return g;
  }

  /* Page et en-tête */
  var nav = $('[data-cl-nav]', root);
  function applyTheme() {
    root.setAttribute('data-pb-theme', state.theme);
    var m = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)');
    root.classList.toggle('pb-dark', state.theme === 'auto' && !!(m && m.matches));
  }
  function setHeader(key, val) {
    state.header[key] = val;
    if (nav) {
      if (key === 'style') { nav.setAttribute('data-pb-header-style', val); }
      if (key === 'layout') { nav.setAttribute('data-pb-header-layout', val); }
      if (key === 'show_back') { nav.setAttribute('data-pb-show-back', val ? '1' : '0'); }
      if (key === 'show_nav') { nav.setAttribute('data-pb-show-nav', val ? '1' : '0'); }
      if (key === 'show_badges') { nav.setAttribute('data-pb-show-badges', val ? '1' : '0'); }
    }
    markDirty();
  }
  function openPagePanel() {
    panelMode = 'page';
    pBody.innerHTML = '';
    openPanel('Réglages', 'Page et en-tête');
    var g1 = group('Apparence de la page');
    g1.appendChild(seg('Thème', ['light', 'dark', 'auto'], state.theme, function (v) { state.theme = v; applyTheme(); markDirty(); }, { light: 'Clair', dark: 'Sombre', auto: 'Selon l’appareil' }));
    var g2 = group('En-tête');
    g2.appendChild(seg('Style', ['solid', 'glass', 'light'], state.header.style, function (v) { setHeader('style', v); }, { solid: 'Sombre', glass: 'Transparent', light: 'Clair' }));
    g2.appendChild(seg('Disposition', ['split', 'centered'], state.header.layout, function (v) { setHeader('layout', v); }, { split: 'Menu à droite', centered: 'Menu centré' }));
    g2.appendChild(toggle('Bouton « Retour »', state.header.show_back, function (v) { setHeader('show_back', v); }));
    g2.appendChild(toggle('Menu des sections', state.header.show_nav, function (v) { setHeader('show_nav', v); }));
    g2.appendChild(toggle('Pastille « Recrutement ouvert »', state.header.show_badges, function (v) { setHeader('show_badges', v); }));
    g2.appendChild(input('Sous-titre', state.header.tagline, function (v) {
      setHeader('tagline', v);
      var sub = $('.cl-nav__sub', nav);
      if (sub && v) { sub.textContent = v; }
    }, { placeholder: 'Automatique (style, région, date de création)', hint: 'Aussi modifiable directement dans l’en-tête.' }));
    g2.appendChild(input('Texte du bouton', state.header.cta_label, function (v) {
      setHeader('cta_label', v);
      var c = $('.cl-nav__cta', nav);
      if (c && v) { c.textContent = v; }
    }, { placeholder: 'Automatique (Rejoindre, Candidater…)' }));
    g2.appendChild(input('Lien du bouton', state.header.cta_url, function (v) { setHeader('cta_url', v); }, { placeholder: 'Automatique', hint: 'Adresse https://…, #contact ou /chemin du site.' }));
    var g3 = group('Ordre des blocs');
    var ol = document.createElement('div');
    ol.className = 'pbe-outline';
    state.blocks.forEach(function (b) {
      var row = document.createElement('div');
      row.className = 'pbe-outline__row' + (b.hidden ? ' is-hidden' : '');
      row.innerHTML = '<span></span><button type="button" class="pbe-icon" aria-label="Masquer ou afficher">' + svg(b.hidden ? 'eyeOff' : 'eye') + '</button>';
      row.querySelector('span').textContent = blockLabel(b);
      row.addEventListener('click', function (e) {
        if (e.target.closest('button')) { act('toggle', b.id); return; }
        var el = blockEl(b.id);
        select(b.id);
        if (el) { el.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
      });
      ol.appendChild(row);
    });
    g3.appendChild(ol);
    var g4 = group('Contenus du back-office');
    var p = document.createElement('p');
    p.className = 'pbe-field';
    p.innerHTML = '<small>Unités, agenda, offres, médias et prérequis viennent des outils du back-office. Les textes de cette page se modifient ici, directement.</small>';
    g4.appendChild(p);
    var a = document.createElement('a');
    a.className = 'pbe-btn pbe-btn--soft';
    a.href = cfg.backOffice;
    a.target = '_blank';
    a.rel = 'noopener';
    a.textContent = 'Ouvrir la page Présentation';
    g4.appendChild(a);
    if (cfg.hasDraft) {
      var g5 = group('Brouillon');
      var d = document.createElement('button');
      d.type = 'button';
      d.className = 'pbe-btn pbe-btn--soft';
      d.textContent = 'Abandonner le brouillon';
      d.addEventListener('click', discard);
      g5.appendChild(d);
    }
  }

  /* Réglages d’un bloc */
  function refreshPanelInputs(b) {
    $$('[data-pbe-key]', pBody).forEach(function (el) {
      if (document.activeElement === el) { return; }
      var k = el.getAttribute('data-pbe-key').split('.');
      var v = k.length === 1 ? b.props[k[0]] : (b.props.items && b.props.items[k[1]] ? b.props.items[k[1]][k[2]] : '');
      el.value = v || '';
    });
  }
  function imagePicker(label, current, onPick) {
    var wrap = document.createElement('div');
    wrap.className = 'pbe-image';
    var prev = document.createElement('div');
    prev.className = 'pbe-image__preview';
    var setPrev = function (u) { prev.style.backgroundImage = u ? 'url("' + String(u).replace(/"/g, '%22') + '")' : ''; };
    setPrev(current && /^https?:/.test(current) ? current : (current ? cfg.base.replace(/\/c\/[^/]+$/, '') + '/' + current.replace(/^\//, '') : ''));
    var actions = document.createElement('div');
    actions.className = 'pbe-image__actions';
    var up = document.createElement('button');
    up.type = 'button';
    up.className = 'pbe-btn pbe-btn--primary';
    up.textContent = 'Téléverser';
    up.addEventListener('click', function () {
      var f = $('[data-pbe-file]');
      f.value = '';
      f.onchange = function () {
        if (!f.files || !f.files[0]) { return; }
        var fd = new FormData();
        fd.append('image', f.files[0]);
        up.disabled = true;
        up.textContent = 'Envoi…';
        post(cfg.endpoints.image, fd, true).then(function (j) {
          setPrev(j.url);
          onPick(j.path);
        }).catch(function (e) { toast(e.message, true); }).finally(function () { up.disabled = false; up.textContent = 'Téléverser'; });
      };
      f.click();
    });
    actions.appendChild(up);
    if (cfg.media && cfg.media.length) {
      var lib = document.createElement('button');
      lib.type = 'button';
      lib.className = 'pbe-btn pbe-btn--soft';
      lib.textContent = 'Médiathèque';
      lib.addEventListener('click', function () {
        var grid = wrap.parentNode.querySelector('.pbe-media-grid');
        if (grid) { grid.remove(); return; }
        grid = document.createElement('div');
        grid.className = 'pbe-media-grid pbe-library';
        cfg.media.forEach(function (m) {
          var c = document.createElement('button');
          c.type = 'button';
          c.className = 'pbe-card';
          c.innerHTML = '<div class="pbe-card__art" style="background:#0b1012 center/cover no-repeat"></div><span></span>';
          c.querySelector('.pbe-card__art').style.backgroundImage = 'url("' + m.url.replace(/"/g, '%22') + '")';
          c.querySelector('span').textContent = m.title || 'Image';
          c.addEventListener('click', function () { setPrev(m.url); onPick(m.url); grid.remove(); });
          grid.appendChild(c);
        });
        wrap.parentNode.appendChild(grid);
      });
      actions.appendChild(lib);
    }
    var rm = document.createElement('button');
    rm.type = 'button';
    rm.className = 'pbe-btn pbe-btn--soft';
    rm.textContent = 'Retirer';
    rm.addEventListener('click', function () { setPrev(''); onPick(''); });
    actions.appendChild(rm);
    wrap.appendChild(prev);
    wrap.appendChild(actions);
    var f = field(label, wrap);
    var url = document.createElement('input');
    url.className = 'pbe-input';
    url.type = 'url';
    url.placeholder = 'ou collez une adresse https://…';
    url.style.marginTop = '8px';
    url.value = current && /^https?:/.test(current) ? current : '';
    url.addEventListener('change', function () { setPrev(url.value); onPick(url.value.trim()); });
    f.appendChild(url);
    return f;
  }
  function orbatPanel(b, el, keys) {
    b.style = b.style || {};
    var setStyle = function (k, v) {
      b.style[k] = v;
      if (el) { el.setAttribute('data-pb-' + k.replace(/_/g, '-'), v); }
      markDirty();
    };
    var current = function (k) { return b.style[k] || (cfg.styleOptions[k] || [])[0]; };
    var gs = group('Style de l’ORBAT');
    var grid = document.createElement('div');
    grid.className = 'pbe-library pbe-orbat-styles';
    grid.setAttribute('role', 'radiogroup');
    grid.setAttribute('aria-label', 'Style de l’ORBAT');
    ORBAT_STYLES.forEach(function (o) {
      var c = document.createElement('button');
      c.type = 'button';
      c.className = 'pbe-card';
      c.setAttribute('role', 'radio');
      c.setAttribute('aria-checked', String(current('orbat_style') === o[0]));
      c.innerHTML = '<div class="pbe-card__art"><svg viewBox="0 0 160 78" preserveAspectRatio="xMidYMid meet">' + o[3] + '</svg></div><strong></strong><span></span>';
      c.querySelector('strong').textContent = o[1];
      c.querySelector('span').textContent = o[2];
      c.addEventListener('click', function () {
        $$('.pbe-card', grid).forEach(function (x) { x.setAttribute('aria-checked', 'false'); });
        c.setAttribute('aria-checked', 'true');
        setStyle('orbat_style', o[0]);
      });
      grid.appendChild(c);
    });
    gs.appendChild(grid);
    var gr = group('Réglages');
    ['orbat_density', 'orbat_depth', 'orbat_colors'].forEach(function (k) {
      if (keys.indexOf(k) < 0) { return; }
      var lab = LABELS[k];
      gr.appendChild(seg(lab[0], cfg.styleOptions[k], current(k), function (v) { setStyle(k, v); }, lab[1]));
    });
    var ga = group('Afficher');
    ORBAT_SHOW.forEach(function (o) {
      if (keys.indexOf(o[0]) < 0) { return; }
      ga.appendChild(toggle(o[1], current(o[0]) !== 'off', function (on) { setStyle(o[0], on ? 'on' : 'off'); }));
    });
    var note = document.createElement('p');
    note.className = 'pbe-field';
    note.innerHTML = '<small>Seules les unités cochées « visibles sur la page publique » apparaissent. Les libellés suivent la langue de l’ORBAT.</small>';
    ga.appendChild(note);
  }
  function openBlockPanel(id, focusImage) {
    var b = findBlock(id);
    if (!b) { return; }
    panelMode = 'block';
    pBody.innerHTML = '';
    var custom = b.type !== 'section';
    openPanel(custom ? 'Bloc' : 'Section', custom ? cfg.types[b.type].label : cfg.sections[b.key]);
    var el = blockEl(id);
    var keys = custom ? (cfg.styleKeys[b.type] || []) : (cfg.styleKeys['section:' + b.key] || cfg.styleKeys.section);
    if (!custom && b.key === 'organisation') {
      orbatPanel(b, el, keys);
      keys = keys.filter(function (k) { return k.indexOf('orbat_') !== 0; });
    }
    if (keys.length) {
      var gs = group('Style');
      keys.forEach(function (k) {
        var lab = LABELS[k] || [k, {}];
        gs.appendChild(seg(lab[0], cfg.styleOptions[k], (b.style || {})[k], function (v) {
          var wasFull = (b.style || {}).width === 'full';
          b.style = b.style || {};
          b.style[k] = v;
          if (el) { el.setAttribute('data-pb-' + k.replace(/_/g, '-'), v); }
          markDirty();
          if (k === 'width' && (v === 'full') !== wasFull) { saveAndReload(b.id); return; }
          if (k === 'icon' && custom) { scheduleRerender(b); }
        }, lab[1]));
      });
    }
    var gn = group('Menu de l’en-tête');
    gn.appendChild(input('Libellé dans le menu', b.nav_label, function (v) { b.nav_label = v; markDirty(); }, { placeholder: custom ? 'Absent du menu' : 'Libellé par défaut', hint: 'Ajoute un lien vers ce bloc dans l’en-tête. Visible après enregistrement.' }));
    if (!custom) {
      var gh = group('Contenu');
      var note = document.createElement('p');
      note.className = 'pbe-field';
      note.innerHTML = '<small>Cliquez sur un texte de la section pour le modifier. Les données (unités, agenda, médias, offres, prérequis) se gèrent dans le back-office.</small>';
      gh.appendChild(note);
      gh.appendChild(toggle('Masquer pour les visiteurs', b.hidden, function () { act('toggle', b.id); }));
      return;
    }
    var gc = group('Contenu');
    ['kicker', 'title', 'text'].forEach(function (k) {
      gc.appendChild(input({ kicker: 'Surtitre', title: 'Titre', text: 'Texte' }[k], b.props[k], function (v) {
        b.props[k] = v;
        var t = el && el.querySelector('[data-cl-prop="' + k + '"]');
        if (t) { t.textContent = v; } else { scheduleRerender(b); }
        markDirty();
      }, { key: k, multiline: k === 'text', rows: 3 }));
    });
    if (b.type === 'hero' || b.type === 'textmedia') {
      var gi = group('Image');
      gi.appendChild(imagePicker('Image', b.props.image, function (v) { b.props.image = v; markDirty(); scheduleRerender(b); }));
      gi.appendChild(input('Description de l’image', b.props.image_alt, function (v) { b.props.image_alt = v; markDirty(); }, { hint: 'Lue par les lecteurs d’écran.' }));
      if (focusImage) { setTimeout(function () { gi.scrollIntoView({ block: 'start' }); }, 50); }
    }
    if (b.type !== 'tiles' && b.type !== 'stats') {
      var gb = group('Boutons');
      var btns = b.type === 'hero' || b.type === 'cta' ? ['', '2'] : [''];
      btns.forEach(function (n) {
        gb.appendChild(input(n ? 'Second bouton' : 'Bouton', b.props['button' + n + '_label'], function (v) { b.props['button' + n + '_label'] = v; markDirty(); scheduleRerender(b); }, { key: 'button' + n + '_label', placeholder: 'Sans bouton' }));
        gb.appendChild(input('Lien', b.props['button' + n + '_url'], function (v) { b.props['button' + n + '_url'] = v; markDirty(); }, { placeholder: '#contact, https://… ou /chemin' }));
      });
    }
    if (b.type === 'tiles' || b.type === 'stats') {
      var gl = group(b.type === 'tiles' ? 'Miniatures' : 'Chiffres');
      var listWrap = document.createElement('div');
      listWrap.className = 'pbe-items';
      var max = b.type === 'stats' ? 6 : 12;
      var drawItems = function () {
        listWrap.innerHTML = '';
        (b.props.items || []).forEach(function (it, idx) {
          var row = document.createElement('div');
          row.className = 'pbe-item';
          if (b.type === 'stats') { row.style.gridTemplateColumns = '1fr auto'; }
          var main = document.createElement('div');
          main.className = 'pbe-item__main';
          var subs = b.type === 'tiles' ? [['title', 'Titre'], ['text', 'Texte'], ['badge', 'Badge'], ['url', 'Lien (facultatif)']] : [['value', 'Valeur'], ['label', 'Libellé'], ['note', 'Précision']];
          subs.forEach(function (s) {
            var inp = document.createElement('input');
            inp.className = 'pbe-input';
            inp.placeholder = s[1];
            inp.value = it[s[0]] || '';
            inp.setAttribute('data-pbe-key', 'items.' + idx + '.' + s[0]);
            inp.addEventListener('input', function () { it[s[0]] = inp.value; markDirty(); scheduleRerender(b); });
            main.appendChild(inp);
          });
          if (b.type === 'tiles') {
            var th = document.createElement('div');
            th.className = 'pbe-item__thumb';
            th.title = 'Changer l’image';
            var u = it.image ? (/^https?:/.test(it.image) ? it.image : cfg.base.replace(/\/c\/[^/]+$/, '') + '/' + it.image.replace(/^\//, '')) : '';
            if (u) { th.style.backgroundImage = 'url("' + u.replace(/"/g, '%22') + '")'; }
            th.addEventListener('click', function () {
              var holder = row.nextSibling && row.nextSibling.classList && row.nextSibling.classList.contains('pbe-field') ? row.nextSibling : null;
              if (holder) { holder.remove(); return; }
              var picker = imagePicker('Image de la miniature', it.image, function (v) { it.image = v; markDirty(); scheduleRerender(b); drawItems(); });
              listWrap.insertBefore(picker, row.nextSibling);
            });
            row.appendChild(th);
          }
          row.appendChild(main);
          var tools = document.createElement('div');
          tools.className = 'pbe-item__tools';
          tools.innerHTML = '<button type="button" class="pbe-icon" data-t="up" aria-label="Monter">' + svg('up') + '</button><button type="button" class="pbe-icon" data-t="down" aria-label="Descendre">' + svg('down') + '</button><button type="button" class="pbe-icon pbe-icon--danger" data-t="rm" aria-label="Retirer">' + svg('trash') + '</button>';
          tools.addEventListener('click', function (e) {
            var t = e.target.closest('button');
            if (!t) { return; }
            var items = b.props.items;
            var w = t.getAttribute('data-t');
            if (w === 'rm') { items.splice(idx, 1); }
            if (w === 'up' && idx > 0) { items.splice(idx - 1, 0, items.splice(idx, 1)[0]); }
            if (w === 'down' && idx < items.length - 1) { items.splice(idx + 1, 0, items.splice(idx, 1)[0]); }
            drawItems();
            markDirty();
            scheduleRerender(b);
          });
          row.appendChild(tools);
          listWrap.appendChild(row);
          if (focusImage === 'items.' + idx + '.image') {
            focusImage = null;
            setTimeout(function () { row.querySelector('.pbe-item__thumb').click(); row.scrollIntoView({ block: 'center' }); }, 60);
          }
        });
        var add = document.createElement('button');
        add.type = 'button';
        add.className = 'pbe-btn pbe-btn--soft';
        add.innerHTML = svg('plus') + (b.type === 'tiles' ? 'Ajouter une miniature' : 'Ajouter un chiffre');
        add.disabled = (b.props.items || []).length >= max;
        add.addEventListener('click', function () {
          b.props.items = b.props.items || [];
          b.props.items.push(b.type === 'tiles' ? { title: 'Nouvelle miniature', text: '', badge: '', image: '', url: '' } : { value: '0', label: 'Libellé', note: '' });
          drawItems();
          markDirty();
          scheduleRerender(b);
        });
        listWrap.appendChild(add);
      };
      drawItems();
      gl.appendChild(listWrap);
    }
  }

  /* Bibliothèque */
  function openLibrary() {
    panelMode = 'library';
    pBody.innerHTML = '';
    openPanel('Bibliothèque', 'Ajouter un bloc');
    var g = group('');
    var grid = document.createElement('div');
    grid.className = 'pbe-library';
    Object.keys(cfg.types).forEach(function (t) {
      var c = document.createElement('button');
      c.type = 'button';
      c.className = 'pbe-card';
      c.innerHTML = '<div class="pbe-card__art"><svg viewBox="0 0 160 78" preserveAspectRatio="xMidYMid slice">' + ART[t] + '</svg></div><strong></strong><span></span>';
      c.querySelector('strong').textContent = cfg.types[t].label;
      c.querySelector('span').textContent = cfg.types[t].desc;
      c.addEventListener('click', function () { addBlock(t); });
      grid.appendChild(c);
    });
    g.appendChild(grid);
    var hint = document.createElement('p');
    hint.className = 'pbe-field';
    hint.innerHTML = '<small></small>';
    var target = insertAfterId ? findBlock(insertAfterId) : null;
    hint.querySelector('small').textContent = target ? 'Le bloc sera ajouté après « ' + blockLabel(target) + ' ».' : 'Le bloc sera ajouté avant le contact. Survolez la page et utilisez « Ajouter ici » pour choisir sa place.';
    g.appendChild(hint);
  }
  function addBlock(type) {
    post(cfg.endpoints.block, { new_type: type }).then(function (j) {
      var b = cleanBlock(j.block);
      var after = insertAfterId;
      if (!after || blockIndex(after) < 0) {
        var ci = blockIndex('s_contact');
        after = ci > 0 ? state.blocks[ci - 1].id : (state.blocks.length ? state.blocks[state.blocks.length - 1].id : null);
      }
      state.blocks.splice(blockIndex(after) + 1, 0, b);
      insertAfterId = null;
      markDirty();
      placeNewBlock(b, j.html, after);
      openBlockPanel(b.id);
    }).catch(function (e) { toast(e.message, true); });
  }

  /* —— Publication —— */
  function publish() {
    var btn = $('[data-pbe-action="publish"]');
    btn.disabled = true;
    clearTimeout(saveTimer);
    setStatus('saving', 'Publication…');
    (saving || Promise.resolve()).catch(function () {}).then(function () {
      return post(cfg.endpoints.publish, payload());
    }).then(function (j) {
      dirty = false;
      setStatus('saved', 'Publié');
      toast(j.message || 'Page publiée');
      setTimeout(function () { window.location.href = cfg.base; }, 700);
    }).catch(function (e) {
      btn.disabled = false;
      setStatus('error', 'Publication impossible');
      toast(e.message, true);
    });
  }
  function discard() {
    if (!window.confirm('Abandonner le brouillon ? La page revient à la version publiée.')) { return; }
    clearTimeout(saveTimer);
    dirty = false;
    post(cfg.endpoints.discard, {}).then(function () { window.location.href = cfg.base; }).catch(function (e) { toast(e.message, true); });
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-pbe-action]');
    if (!a) { return; }
    var what = a.getAttribute('data-pbe-action');
    if (what === 'exit') {
      e.preventDefault();
      var go = function () { window.location.href = cfg.base; };
      if (dirty || saving) { save().then(go).catch(function () {}); } else { go(); }
      return;
    }
    e.preventDefault();
    if (what === 'page') { panelMode === 'page' ? closePanel() : openPagePanel(); }
    if (what === 'library') { insertAfterId = null; panelMode === 'library' ? closePanel() : openLibrary(); }
    if (what === 'publish') { publish(); }
    if (what === 'close') { closePanel(); }
  });

  /* —— Démarrage —— */
  enableEditable(root);
  $$('.pb-block', root).forEach(decorate);
  refreshBars();
  try {
    var sy = sessionStorage.getItem('pbe.scroll');
    var sel = sessionStorage.getItem('pbe.select');
    sessionStorage.removeItem('pbe.scroll');
    sessionStorage.removeItem('pbe.select');
    if (sy) { window.scrollTo(0, parseInt(sy, 10) || 0); }
    if (sel && blockEl(sel)) { select(sel); openBlockPanel(sel); }
  } catch (e) {}
  if (!cfg.hasDraft) { setStatus('idle', 'Aucune modification'); }
})();
