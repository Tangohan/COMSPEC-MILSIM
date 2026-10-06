/**
 * Back-office > Décorations : import d’insignes (glisser-déposer, aperçu, lignes éditables)
 * et création unitaire avec image. Les noms et codes sont dérivés du nom de fichier
 * comme côté serveur (App\Support\DecorationImageImport).
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-rdk]');
  if (!root) return;

  var MAX_BYTES = 2000000;
  var MAX_FILES = 20;
  var OK_TYPES = ['image/png', 'image/webp', 'image/jpeg'];
  var ACCENTS = { 'à': 'a', 'â': 'a', 'ä': 'a', 'á': 'a', 'ã': 'a', 'å': 'a', 'ç': 'c', 'é': 'e', 'è': 'e', 'ê': 'e', 'ë': 'e',
    'î': 'i', 'ï': 'i', 'í': 'i', 'ì': 'i', 'ñ': 'n', 'ô': 'o', 'ö': 'o', 'ó': 'o', 'ò': 'o', 'õ': 'o',
    'ù': 'u', 'û': 'u', 'ü': 'u', 'ú': 'u', 'ÿ': 'y', 'œ': 'oe', 'æ': 'ae' };

  function nameFromFilename(filename) {
    var base = String(filename || '').split(/[\\/]/).pop();
    base = base.replace(/\.[A-Za-z0-9]{2,5}$/, '').replace(/\s*\(\d+\)\s*$/, '').replace(/[_\-.]+/g, ' ');
    base = base.replace(/\s+/g, ' ').trim();
    if (base && base.toLowerCase() === base) {
      base = base.replace(/(^|\s)(\S)/g, function (m, sp, c) { return sp + c.toUpperCase(); });
    }
    return base.slice(0, 180);
  }

  function codeFromName(name) {
    var s = String(name || '').toLowerCase().replace(/[^\u0000-\u007f]/g, function (c) { return ACCENTS[c] || ' '; });
    s = s.replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').toUpperCase();
    if (s.length > 32) s = s.slice(0, 32).replace(/_+$/, '');
    return s;
  }

  function fileProblem(f) {
    if (OK_TYPES.indexOf(f.type) === -1) return 'format non accepté (PNG, WebP ou JPEG)';
    if (f.size > MAX_BYTES) return 'plus de 2 Mo';
    return '';
  }

  function sizeLabel(bytes) {
    return bytes >= 1000000 ? (bytes / 1000000).toFixed(1).replace('.', ',') + ' Mo' : Math.max(1, Math.round(bytes / 1000)) + ' Ko';
  }

  function canAssignFiles() {
    try { return typeof DataTransfer !== 'undefined' && !!new DataTransfer().items; } catch (e) { return false; }
  }

  function wireDrop(zone, onFiles) {
    ['dragenter', 'dragover'].forEach(function (ev) {
      zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-over'); });
    });
    ['dragleave', 'dragend', 'drop'].forEach(function (ev) {
      zone.addEventListener(ev, function () { zone.classList.remove('is-over'); });
    });
    zone.addEventListener('drop', function (e) {
      e.preventDefault();
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) onFiles(e.dataTransfer.files);
    });
  }

  /* ——— Import en lot ——— */
  var bulk = root.querySelector('[data-rdk-bulk]');
  if (bulk) {
    var input = bulk.querySelector('[data-rdk-bulk-input]');
    var drop = bulk.querySelector('[data-rdk-drop]');
    var rowsBox = bulk.querySelector('[data-rdk-rows]');
    var list = bulk.querySelector('[data-rdk-list]');
    var foot = bulk.querySelector('[data-rdk-foot]');
    var submit = bulk.querySelector('[data-rdk-submit]');
    var clearBtn = bulk.querySelector('[data-rdk-clear]');
    var errorBox = bulk.querySelector('[data-rdk-bulk-error]');
    var tpl = root.querySelector('[data-rdk-row-tpl]');
    var items = []; // { file, url, el }

    function showError(lines) {
      if (!lines.length) { errorBox.hidden = true; errorBox.textContent = ''; return; }
      errorBox.hidden = false;
      errorBox.textContent = 'Ignoré : ' + lines.join(' · ');
    }

    function syncInput() {
      if (!canAssignFiles()) return;
      var dt = new DataTransfer();
      items.forEach(function (it) { dt.items.add(it.file); });
      input.files = dt.files;
    }

    function render() {
      var n = items.length;
      rowsBox.hidden = n === 0;
      foot.hidden = n === 0;
      submit.textContent = n > 1 ? 'Importer ' + n + ' insignes' : 'Importer l’insigne';
      submit.disabled = n === 0;
      checkDuplicates();
    }

    function checkDuplicates() {
      var seen = {};
      items.forEach(function (it) {
        var code = it.code.value.trim().toUpperCase() || codeFromName(it.name.value);
        it.el.classList.toggle('is-dup', !!seen[code]);
        it.meta.textContent = seen[code] ? 'Code en double : cette ligne sera ignorée' : it.metaText;
        seen[code] = true;
      });
    }

    function addFiles(fileList) {
      var problems = [];
      Array.prototype.forEach.call(fileList, function (f) {
        var issue = fileProblem(f);
        if (issue) { problems.push(f.name + ' (' + issue + ')'); return; }
        if (items.length >= MAX_FILES) { problems.push(f.name + ' (limite de ' + MAX_FILES + ' par envoi)'); return; }
        var dup = items.some(function (it) { return it.file.name === f.name && it.file.size === f.size; });
        if (dup) return;
        var li = tpl.content.firstElementChild.cloneNode(true);
        var it = {
          file: f,
          url: URL.createObjectURL(f),
          el: li,
          name: li.querySelector('[data-f="name"]'),
          code: li.querySelector('[data-f="code"]'),
          meta: li.querySelector('[data-f="meta"]'),
          codeTouched: false,
          metaText: f.name + ' · ' + sizeLabel(f.size)
        };
        li.querySelector('[data-f="img"]').src = it.url;
        it.name.value = nameFromFilename(f.name);
        it.code.value = codeFromName(it.name.value);
        it.meta.textContent = it.metaText;
        it.name.addEventListener('input', function () {
          if (!it.codeTouched) it.code.value = codeFromName(it.name.value);
          checkDuplicates();
        });
        it.code.addEventListener('input', function () {
          it.codeTouched = it.code.value.trim() !== '';
          checkDuplicates();
        });
        li.querySelector('[data-f="remove"]').addEventListener('click', function () {
          URL.revokeObjectURL(it.url);
          items = items.filter(function (x) { return x !== it; });
          li.remove();
          syncInput();
          render();
        });
        items.push(it);
        list.appendChild(li);
      });
      syncInput();
      showError(problems);
      render();
      if (items.length && problems.length === 0) {
        var first = items[items.length - 1].name;
        if (first && document.activeElement === document.body) first.focus();
      }
    }

    if (canAssignFiles()) {
      wireDrop(drop, addFiles);
      input.addEventListener('change', function () {
        // Le sélecteur remplace input.files : on reprend la liste accumulée.
        var picked = Array.prototype.slice.call(input.files || []);
        syncInput();
        addFiles(picked);
      });
    } else {
      // Navigateur ancien : un seul choix de fichiers, sans glisser-déposer.
      input.addEventListener('change', function () {
        items.forEach(function (it) { URL.revokeObjectURL(it.url); it.el.remove(); });
        items = [];
        addFiles(input.files || []);
      });
    }

    clearBtn.addEventListener('click', function () {
      items.forEach(function (it) { URL.revokeObjectURL(it.url); it.el.remove(); });
      items = [];
      syncInput();
      showError([]);
      render();
    });

    bulk.addEventListener('submit', function (e) {
      if (!items.length) { e.preventDefault(); return; }
      submit.disabled = true;
      submit.textContent = 'Import en cours…';
    });
  }

  /* ——— Création unitaire ——— */
  var single = root.querySelector('[data-rdk-single]');
  if (single) {
    var sInput = single.querySelector('[data-rdk-single-input]');
    var sDrop = single.querySelector('[data-rdk-single-drop]');
    var sPreview = single.querySelector('[data-rdk-single-preview]');
    var sEmpty = single.querySelector('[data-rdk-single-empty]');
    var sNote = single.querySelector('[data-rdk-file-note]');
    var nameEl = single.querySelector('[data-rdk-name]');
    var codeEl = single.querySelector('[data-rdk-code]');
    var codeTouched = false;
    var sUrl = null;
    var defaultNote = sNote ? sNote.textContent : '';

    function showSingle(f) {
      if (sUrl) { URL.revokeObjectURL(sUrl); sUrl = null; }
      if (!f) { sPreview.hidden = true; sEmpty.hidden = false; if (sNote) { sNote.textContent = defaultNote; sNote.classList.remove('is-error'); } return; }
      var issue = fileProblem(f);
      if (issue) {
        sInput.value = '';
        sPreview.hidden = true;
        sEmpty.hidden = false;
        if (sNote) { sNote.textContent = f.name + ' : ' + issue + '.'; sNote.classList.add('is-error'); }
        return;
      }
      sUrl = URL.createObjectURL(f);
      sPreview.src = sUrl;
      sPreview.hidden = false;
      sEmpty.hidden = true;
      if (sNote) { sNote.textContent = f.name + ' · ' + sizeLabel(f.size); sNote.classList.remove('is-error'); }
      if (nameEl && nameEl.value.trim() === '') {
        nameEl.value = nameFromFilename(f.name);
        nameEl.dispatchEvent(new Event('input'));
      }
    }

    sInput.addEventListener('change', function () { showSingle(sInput.files && sInput.files[0]); });
    if (canAssignFiles()) {
      wireDrop(sDrop, function (files) {
        var dt = new DataTransfer();
        dt.items.add(files[0]);
        sInput.files = dt.files;
        showSingle(files[0]);
      });
    }
    if (nameEl && codeEl) {
      nameEl.addEventListener('input', function () { if (!codeTouched) codeEl.value = codeFromName(nameEl.value); });
      codeEl.addEventListener('input', function () { codeTouched = codeEl.value.trim() !== ''; });
    }
  }

  /* ——— Recherche dans le référentiel ——— */
  var search = root.querySelector('[data-rdk-search]');
  var grid = root.querySelector('[data-rdk-grid]');
  var noResult = root.querySelector('[data-rdk-noresult]');
  if (search && grid) {
    search.addEventListener('input', function () {
      var q = search.value.trim().toLowerCase();
      var shown = 0;
      Array.prototype.forEach.call(grid.children, function (card) {
        var ok = !q || (card.getAttribute('data-search') || '').indexOf(q) !== -1;
        card.hidden = !ok;
        if (ok) shown++;
      });
      if (noResult) noResult.hidden = shown !== 0;
    });
  }
})();
