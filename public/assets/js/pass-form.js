/**
 * Formulaire PASS RH : champs utiles selon le type de condition, phrases lisibles, aperçu en direct,
 * modèles de départ, code auto, contrôle avant envoi et essai sur un membre (PASS enregistré).
 * Même logique de phrases que App\Support\PassConditionSummary.
 */
(function () {
  var root = document.querySelector('[data-pass-form]');
  if (!root) return;
  var cfgEl = document.getElementById('pass-form-config');
  var cfg = {};
  try { cfg = JSON.parse(cfgEl ? cfgEl.textContent : '{}') || {}; } catch (e) { cfg = {}; }
  var form = root.querySelector('[data-pass-main-form]');
  var list = root.querySelector('[data-pass-conditions]');
  var tpl = document.getElementById('pass-condition-template');
  var empty = root.querySelector('[data-pass-empty]');
  var count = root.querySelector('[data-pass-count]');
  if (!form || !list || !tpl) return;

  var q = function (el, sel) { return el.querySelector(sel); };
  var optText = function (select) {
    if (!select || !select.value) return '';
    var o = select.options[select.selectedIndex];
    return o ? o.textContent.trim() : '';
  };
  var fmt = function (v) {
    var n = parseFloat(String(v || '').replace(',', '.'));
    if (!isFinite(n) || n <= 0) return '';
    return (Math.round(n * 10) / 10).toString().replace('.', ',');
  };

  // Phrase lisible + ce qu'il manque (miroir de PassConditionSummary::describe).
  function describe(row) {
    var type = q(row, '[data-pass-type]').value;
    var val = function (name) { var el = q(row, '[name="' + name + '[]"]'); return el ? el.value : ''; };
    var txt = function (name) { return optText(q(row, 'select[name="' + name + '[]"]')); };
    var n = fmt(val('threshold_value'));
    var valid = q(row, '[data-pass-validity]').value === '1';
    switch (type) {
      case 'lms_completed':
        return txt('training_module_id') ? { text: 'A validé la formation « ' + txt('training_module_id') + ' »', missing: '' }
          : { text: 'A validé une formation', missing: 'Choisissez la formation.' };
      case 'qualification_held':
        var v = valid ? ', encore valable' : '';
        return txt('qualification_id') ? { text: 'Détient la qualification « ' + txt('qualification_id') + ' »' + v, missing: '' }
          : { text: 'Détient une qualification' + v, missing: 'Choisissez la qualification.' };
      case 'playtime_raw_hours':
        return { text: 'Au moins ' + (n || '0') + ' h de jeu (temps brut)', missing: n ? '' : 'Indiquez le nombre d’heures.' };
      case 'playtime_validated_hours':
        return { text: 'Au moins ' + (n || '0') + ' h de jeu validées', missing: n ? '' : 'Indiquez le nombre d’heures.' };
      case 'playtime_category_hours':
        var cat = val('hour_category');
        return {
          text: 'Au moins ' + (n || '0') + ' h validées' + (cat ? ' en « ' + cat + ' »' : ''),
          missing: !n ? 'Indiquez le nombre d’heures.' : (!cat ? 'Choisissez le type d’heure.' : '')
        };
      case 'min_grade':
        return txt('grade_id') ? { text: 'A au moins le grade ' + txt('grade_id'), missing: '' }
          : { text: 'A au moins un grade donné', missing: 'Choisissez le grade minimum.' };
      case 'hierarchical_avis':
        var kind = val('avis_kind');
        return kind ? { text: 'A reçu un avis favorable « ' + ((cfg.avis || {})[kind] || kind) + ' »', missing: '' }
          : { text: 'A reçu un avis hiérarchique favorable', missing: 'Choisissez qui donne l’avis.' };
      case 'bilan_rating_min':
        var b = val('bilan_kind');
        var w = parseInt(val('window_days'), 10) || 0;
        return {
          text: 'Moyenne des bilans' + (b ? ' « ' + ((cfg.bilan || {})[b] || b) + ' »' : '') + ' d’au moins ' + (n || '0') + (w > 0 ? ' sur les ' + w + ' derniers jours' : ''),
          missing: n ? '' : 'Indiquez la note minimale.'
        };
      default:
        return { text: 'Condition à choisir', missing: 'Choisissez le type de condition.' };
    }
  }

  function applyType(row) {
    var type = q(row, '[data-pass-type]').value;
    row.setAttribute('data-type', type);
    var fields = (cfg.fields || {})[type] || [];
    Array.prototype.forEach.call(row.querySelectorAll('[data-pass-field]'), function (f) {
      f.hidden = fields.indexOf(f.getAttribute('data-pass-field')) === -1;
    });
    var help = q(row, '[data-pass-help]');
    if (help) help.textContent = (cfg.help || {})[type] || 'Choisissez ce que le membre doit remplir.';
    var ico = q(row, '[data-pass-icon]');
    if (ico && (cfg.icons || {})[type]) ico.innerHTML = cfg.icons[type];
    var isNote = type === 'bilan_rating_min';
    var lab = q(row, '[data-pass-threshold-label]');
    var unit = q(row, '[data-pass-unit]');
    if (lab) lab.textContent = isNote ? 'Note minimale' : 'Heures minimum';
    if (unit) unit.textContent = isNote ? '/ 5' : 'h';
  }

  function refreshRow(row) {
    var d = describe(row);
    q(row, '[data-pass-sentence]').textContent = d.text;
    var miss = q(row, '[data-pass-missing]');
    miss.textContent = d.missing;
    miss.hidden = d.missing === '';
    row.classList.toggle('is-incomplete', d.missing !== '');
    return d;
  }

  // Aperçu.
  var pv = {
    code: q(root, '[data-pv-code]'), name: q(root, '[data-pv-name]'), desc: q(root, '[data-pv-desc]'),
    usages: q(root, '[data-pv-usages]'), rule: q(root, '[data-pv-rule]'), list: q(root, '[data-pv-list]'),
    warn: q(root, '[data-pv-warn]'), state: q(root, '[data-pv-state]')
  };
  var labelIn = q(root, '[data-pass-label]');
  var codeIn = q(root, '[data-pass-code]');
  var descIn = q(root, '[data-pass-desc]');
  var activeIn = q(root, '[data-pass-active]');

  function logicValue() {
    var r = q(form, '[data-pass-logic]:checked');
    return r ? r.value : 'all';
  }

  function refresh() {
    var rows = Array.prototype.slice.call(list.querySelectorAll('[data-pass-condition]'));
    var logic = logicValue();
    list.setAttribute('data-logic', logic);
    if (count) count.textContent = String(rows.length);
    if (empty) empty.hidden = rows.length > 0;
    var incomplete = 0;
    if (pv.list) pv.list.innerHTML = '';
    rows.forEach(function (row) {
      var d = refreshRow(row);
      if (d.missing) incomplete++;
      if (pv.list) {
        var li = document.createElement('li');
        li.textContent = d.text;
        if (d.missing) li.className = 'is-incomplete';
        pv.list.appendChild(li);
      }
    });
    if (pv.code) pv.code.textContent = (codeIn && codeIn.value.trim()) || 'PASS';
    if (pv.name) pv.name.textContent = (labelIn && labelIn.value.trim()) || 'Nom du PASS';
    if (pv.desc) { pv.desc.textContent = descIn ? descIn.value.trim() : ''; pv.desc.hidden = !pv.desc.textContent; }
    if (pv.state && activeIn) { pv.state.textContent = activeIn.checked ? 'Actif' : 'Inactif'; pv.state.classList.toggle('is-off', !activeIn.checked); }
    if (pv.usages) {
      var used = Array.prototype.filter.call(form.querySelectorAll('[data-pass-usage]'), function (c) { return c.checked; })
        .map(function (c) { return c.getAttribute('data-pass-usage'); });
      pv.usages.textContent = used.length ? 'Exigé pour : ' + used.join(' · ') : 'Rattaché à aucun usage pour l’instant.';
      pv.usages.classList.toggle('is-muted', !used.length);
    }
    if (pv.rule) {
      pv.rule.textContent = rows.length === 0 ? 'Aucune condition : tout le monde remplit ce PASS.'
        : rows.length === 1 ? 'Le membre doit remplir cette condition :'
        : logic === 'any' ? 'Le membre doit remplir au moins une de ces ' + rows.length + ' conditions :'
        : 'Le membre doit remplir les ' + rows.length + ' conditions :';
    }
    if (pv.warn) {
      pv.warn.hidden = incomplete === 0;
      pv.warn.textContent = incomplete === 1 ? '1 condition est à compléter.' : incomplete + ' conditions sont à compléter.';
    }
  }

  function addRow(type, values) {
    var frag = tpl.content.cloneNode(true);
    var row = frag.querySelector('[data-pass-condition]');
    list.appendChild(frag);
    row = list.lastElementChild;
    var sel = q(row, '[data-pass-type]');
    sel.value = type || '';
    values = values || {};
    Object.keys(values).forEach(function (k) {
      if (k === 'condition_type') return;
      if (k === 'require_validity') {
        q(row, '[data-pass-validity]').value = values[k] ? '1' : '0';
        q(row, '[data-pass-validity-box]').checked = !!values[k];
        return;
      }
      var el = q(row, '[name="' + k + '[]"]');
      if (el) el.value = String(values[k]);
    });
    applyType(row);
    refresh();
    return row;
  }

  // Événements.
  root.addEventListener('click', function (e) {
    var add = e.target.closest('[data-pass-add]');
    if (add) {
      var row = addRow(add.getAttribute('data-pass-add'));
      var first = row.querySelector('[data-pass-field]:not([hidden]) select, [data-pass-field]:not([hidden]) input:not([type="hidden"])');
      row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      if (first) first.focus({ preventScroll: true });
      return;
    }
    var preset = e.target.closest('[data-pass-preset]');
    if (preset) {
      ((cfg.presets || {})[preset.getAttribute('data-pass-preset')] || []).forEach(function (c) { addRow(c.condition_type, c); });
      return;
    }
    var rm = e.target.closest('[data-pass-remove-condition]');
    if (rm) {
      var r = rm.closest('[data-pass-condition]');
      if (r) { r.remove(); refresh(); }
    }
  });
  form.addEventListener('change', function (e) {
    var row = e.target.closest('[data-pass-condition]');
    if (e.target.matches('[data-pass-type]') && row) applyType(row);
    if (e.target.matches('[data-pass-validity-box]') && row) q(row, '[data-pass-validity]').value = e.target.checked ? '1' : '0';
    refresh();
  });
  form.addEventListener('input', function (e) {
    if (e.target === labelIn && codeIn && !codeIn.getAttribute('data-touched')) {
      // Code court proposé à partir du nom (« PASS JTAC niveau 2 » → « JTAC-N2 »).
      var words = labelIn.value.normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase()
        .replace(/[^A-Z0-9 ]+/g, ' ').split(/\s+/).filter(function (w) { return w && w !== 'PASS' && w !== 'DE' && w !== 'DU' && w !== 'LA' && w !== 'LE'; });
      var parts = [];
      words.forEach(function (w, i) {
        if (/^\d+$/.test(w) && parts.length) { parts[parts.length - 1] += w; return; }
        parts.push(i === 0 ? w.slice(0, 10) : w.slice(0, /^(NIVEAU|NIV)$/.test(w) ? 1 : 3));
      });
      var code = parts.join('-');
      codeIn.value = code.slice(0, 24);
    }
    if (e.target === codeIn) codeIn.setAttribute('data-touched', '1');
    refresh();
  });
  form.addEventListener('submit', function (e) {
    if (labelIn && !labelIn.value.trim()) {
      e.preventDefault();
      labelIn.focus();
      labelIn.setCustomValidity('Indiquez le nom du PASS.');
      labelIn.reportValidity();
      labelIn.setCustomValidity('');
      return;
    }
    var bad = list.querySelector('[data-pass-condition].is-incomplete');
    if (bad) {
      e.preventDefault();
      bad.classList.add('is-flash');
      bad.scrollIntoView({ block: 'center', behavior: 'smooth' });
      setTimeout(function () { bad.classList.remove('is-flash'); }, 1200);
      var f = bad.querySelector('[data-pass-field]:not([hidden]) select, [data-pass-field]:not([hidden]) input:not([type="hidden"]), [data-pass-type]');
      if (f) f.focus({ preventScroll: true });
    }
  });

  Array.prototype.forEach.call(list.querySelectorAll('[data-pass-condition]'), applyType);
  refresh();

  // Essai sur un membre (PASS enregistré).
  var test = q(root, '[data-pass-test]');
  if (test && cfg.testUrl) {
    var input = q(test, '[data-pass-test-q]');
    var results = q(test, '[data-pass-test-results]');
    var out = q(test, '[data-pass-test-out]');
    var timer = null;
    var get = function (params) {
      return fetch(cfg.testUrl + '?' + new URLSearchParams(params).toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); });
    };
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    input.addEventListener('input', function () {
      clearTimeout(timer);
      var v = input.value.trim();
      if (v.length < 2) { results.hidden = true; return; }
      timer = setTimeout(function () {
        get({ q: v }).then(function (data) {
          var members = (data && data.members) || [];
          results.innerHTML = members.length ? members.map(function (m) {
            return '<li><button type="button" data-member="' + m.id + '">' + esc(m.label) + '</button></li>';
          }).join('') : '<li class="is-empty">Aucun membre trouvé.</li>';
          results.hidden = false;
        }).catch(function () { results.hidden = true; });
      }, 220);
    });
    results.addEventListener('click', function (e) {
      var b = e.target.closest('[data-member]');
      if (!b) return;
      results.hidden = true;
      input.value = b.textContent;
      out.hidden = false;
      out.innerHTML = '<p class="psf-test__loading">Évaluation…</p>';
      get({ user_id: b.getAttribute('data-member') }).then(function (data) {
        if (!data || !data.ok) { out.innerHTML = '<p class="psf-test__err">' + esc((data && data.error) || 'Évaluation impossible.') + '</p>'; return; }
        var head = '<p class="psf-test__verdict ' + (data.eligible ? 'is-ok' : 'is-ko') + '">' + (data.eligible ? '✓ PASS rempli' : '✗ PASS non rempli') + '</p>';
        var items = (data.items || []).map(function (it) {
          return '<li class="' + (it.passed ? 'is-ok' : 'is-ko') + '"><b>' + (it.passed ? '✓' : '✗') + ' ' + esc(it.label) + '</b><span>' + esc(it.reason) + '</span></li>';
        }).join('');
        out.innerHTML = head + (items ? '<ul class="psf-test__items">' + items + '</ul>' : '<p class="psf-test__hint">Aucune condition enregistrée.</p>');
      }).catch(function () { out.innerHTML = '<p class="psf-test__err">Évaluation impossible pour le moment.</p>'; });
    });
  }
})();
