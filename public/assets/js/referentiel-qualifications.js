/**
 * Back-office > Qualifications : aides du formulaire (exemples, champs dépendants, aperçu)
 * et filtres de la liste. Aucune donnée n’est envoyée : tout reste local jusqu’à l’enregistrement.
 */
(function () {
  'use strict';

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  }

  /* ——— Formulaire ——— */
  var root = document.querySelector('[data-rq]');
  if (root) {
    var form = root.querySelector('[data-rq-form]');
    var year = parseInt(root.getAttribute('data-rq-year'), 10) || new Date().getFullYear();
    var $ = function (sel) { return form ? form.querySelector(sel) : null; };
    var f = {
      code: $('[data-rq-code]'), name: $('[data-rq-name]'), short: $('[data-rq-short]'), desc: $('[data-rq-desc]'),
      scope: $('[data-rq-scope]'), category: $('[data-rq-category]'), type: $('[data-rq-type]'),
      permanent: $('[data-rq-permanent]'), months: $('[data-rq-months]'), alert: $('[data-rq-alert]'), grace: $('[data-rq-grace]'),
      renewal: $('[data-rq-renewal]'), currency: $('[data-rq-currency]'),
      exam: $('[data-rq-exam]'), panel: $('[data-rq-panel]'), levels: $('[data-rq-levels]'), progression: $('[data-rq-progression]'),
      format: $('[data-rq-format]')
    };
    var validityBox = $('[data-rq-validity]');
    var progressionWrap = $('[data-rq-progression-wrap]');
    var numberOut = $('[data-rq-number]');
    var s = function (sel) { return root.querySelector(sel); };

    function numberPreview() {
      var fmt = (f.format && f.format.value.trim()) || 'QUAL-{code}-{year}-{seq}';
      var code = ((f.code && f.code.value) || '').replace(/[^A-Za-z0-9_\-]/g, '').toUpperCase() || 'QUAL';
      return fmt.split('{code}').join(code).split('{year}').join(year).split('{année}').join(year)
        .split('{seq}').join('0042').split('{sequence}').join('0042');
    }

    function plural(n, one, many) { return n + ' ' + (n > 1 ? many : one); }

    function selectedText(sel) {
      if (!sel || !sel.value) return '';
      var o = sel.options[sel.selectedIndex];
      return o ? o.text : '';
    }

    function setDisabled(el, off) {
      if (!el) return;
      el.disabled = off;
      var wrap = el.closest('.rq-field, .rq-toggle');
      if (wrap) wrap.classList.toggle('is-off', off);
    }

    function refresh() {
      if (!form) return;
      var permanent = f.permanent && f.permanent.checked;
      [f.months, f.alert, f.grace, f.renewal].forEach(function (el) { setDisabled(el, !!permanent); });
      if (validityBox) validityBox.classList.toggle('is-off', !!permanent);
      var levels = f.levels && f.levels.checked;
      setDisabled(f.progression, !levels);
      if (progressionWrap) progressionWrap.classList.toggle('is-off', !levels);

      var months = parseInt(f.months && f.months.value, 10) || 0;
      var alertD = f.alert && f.alert.value !== '' ? parseInt(f.alert.value, 10) || 0 : 30;
      var graceD = parseInt(f.grace && f.grace.value, 10) || 0;
      var currency = parseInt(f.currency && f.currency.value, 10) || 0;

      var tv = s('[data-rq-t-valid]'), ta = s('[data-rq-t-alert]'), tg = s('[data-rq-t-grace]');
      if (tv) tv.textContent = months ? plural(months, 'mois', 'mois') : 'durée à la carte';
      if (ta) ta.textContent = alertD ? alertD + ' j avant' : 'sans alerte';
      if (tg) tg.textContent = graceD ? graceD + ' j après' : 'aucune';
      var timeline = s('[data-rq-timeline]');
      if (timeline) timeline.classList.toggle('no-grace', !graceD);

      var preview = numberPreview();
      if (numberOut) numberOut.textContent = preview;

      // Résumé latéral
      var nm = s('[data-rq-s-name]'), cd = s('[data-rq-s-code]'), mono = s('[data-rq-s-mono]');
      if (nm) nm.textContent = (f.name && f.name.value.trim()) || 'Nom de la qualification';
      if (cd) cd.textContent = (f.code && f.code.value.trim().toUpperCase()) || 'CODE';
      if (mono) mono.textContent = (((f.short && f.short.value.trim()) || (f.code && f.code.value.trim()) || '?')).toUpperCase().slice(0, 5);
      var cls = s('[data-rq-s-class]');
      if (cls) {
        var parts = [f.scope && f.scope.value === 'unit' ? 'Portée unité' : 'Portée globale'];
        if (selectedText(f.category)) parts.push(selectedText(f.category));
        if (selectedText(f.type)) parts.push(selectedText(f.type));
        cls.textContent = parts.join(' · ');
      }
      var val = s('[data-rq-s-validity]');
      if (val) {
        var txt;
        if (permanent) {
          txt = 'Permanente : acquise à vie, sans date d’expiration.';
        } else if (months) {
          txt = 'Valable ' + plural(months, 'mois', 'mois') + '. ' +
            (alertD ? 'Signalée « Expire bientôt » ' + alertD + ' j avant l’échéance' : 'Aucune alerte avant l’échéance') +
            (graceD ? ', puis ' + graceD + ' j de grâce.' : ', expirée dès l’échéance.');
        } else {
          txt = 'Pas de durée par défaut : la date d’expiration se saisit à chaque attribution (ou reste vide).';
        }
        if (currency) txt += ' Pratique exigée tous les ' + currency + ' j.';
        val.textContent = txt;
      }
      var flags = s('[data-rq-s-flags]');
      if (flags) {
        var list = [];
        if (f.exam && f.exam.checked) list.push('Examen');
        if (f.panel && f.panel.checked) list.push('Jury');
        if (levels) list.push(f.progression && f.progression.checked ? 'Niveaux successifs' : 'Niveaux');
        if (!permanent && f.renewal && f.renewal.checked) list.push('Recyclage');
        if (currency) list.push('Pratique ' + currency + ' j');
        flags.innerHTML = '';
        list.forEach(function (t) { var li = document.createElement('li'); li.textContent = t; flags.appendChild(li); });
        if (!list.length) { var li = document.createElement('li'); li.className = 'is-empty'; li.textContent = 'Attribution directe, sans épreuve'; flags.appendChild(li); }
      }
      var num = s('[data-rq-s-number]');
      if (num) num.textContent = preview;
    }

    if (form) {
      form.addEventListener('input', refresh);
      form.addEventListener('change', refresh);
      if (f.code && !f.code.readOnly) {
        f.code.addEventListener('input', function () {
          var pos = f.code.selectionStart;
          f.code.value = f.code.value.toUpperCase().replace(/\s+/g, '-');
          try { f.code.setSelectionRange(pos, pos); } catch (e) { /* type sans sélection */ }
        });
      }
      // Jetons {code} {year} {seq} : insertion au curseur
      Array.prototype.forEach.call(form.querySelectorAll('[data-rq-token]'), function (btn) {
        btn.addEventListener('click', function () {
          if (!f.format) return;
          var tok = btn.getAttribute('data-rq-token');
          var v = f.format.value;
          var a = typeof f.format.selectionStart === 'number' ? f.format.selectionStart : v.length;
          var b = typeof f.format.selectionEnd === 'number' ? f.format.selectionEnd : v.length;
          if (v === '' ) { a = b = 0; }
          f.format.value = v.slice(0, a) + tok + v.slice(b);
          f.format.focus();
          try { f.format.setSelectionRange(a + tok.length, a + tok.length); } catch (e) { /* ignore */ }
          refresh();
        });
      });
    }

    // Exemples
    var dataEl = root.querySelector('[data-rq-examples]');
    var note = root.querySelector('[data-rq-example-note]');
    var examples = [];
    try { examples = dataEl ? JSON.parse(dataEl.textContent || '[]') : []; } catch (e) { examples = []; }

    function pickOption(sel, words) {
      if (!sel) return '';
      sel.value = '';
      var keys = (words || []).map(norm);
      for (var i = 0; i < sel.options.length; i++) {
        var t = norm(sel.options[i].text);
        if (!sel.options[i].value) continue;
        if (keys.some(function (k) { return k && t.indexOf(k) !== -1; })) { sel.value = sel.options[i].value; return sel.options[i].text; }
      }
      return '';
    }

    function setVal(el, v) { if (el) el.value = v === null || v === undefined ? '' : String(v); }
    function setChk(el, v) { if (el) el.checked = !!v; }

    function applyExample(ex) {
      var hasContent = (f.name && f.name.value.trim()) || (f.desc && f.desc.value.trim());
      if (hasContent && !applyExample.confirmed) {
        if (!window.confirm('Remplacer le contenu déjà saisi par l’exemple « ' + ex.label + ' » ?')) return;
      }
      applyExample.confirmed = true;
      if (f.code && !f.code.readOnly) setVal(f.code, ex.code);
      setVal(f.name, ex.name); setVal(f.short, ex.short_name); setVal(f.desc, ex.description);
      if (f.scope) f.scope.value = ex.scope === 'unit' ? 'unit' : 'global';
      var cat = pickOption(f.category, ex.category);
      var typ = pickOption(f.type, ex.type);
      setChk(f.permanent, ex.is_permanent);
      setVal(f.months, ex.validity); setVal(f.alert, ex.alert === '' ? 30 : ex.alert); setVal(f.grace, ex.grace);
      setVal(f.currency, ex.currency);
      setChk(f.renewal, ex.renewal_required); setChk(f.exam, ex.requires_exam); setChk(f.panel, ex.requires_panel);
      setChk(f.levels, ex.uses_levels); setChk(f.progression, ex.enforce_level_progression);
      setVal(f.format, ex.number_format);
      Array.prototype.forEach.call(root.querySelectorAll('[data-rq-example]'), function (b) {
        b.classList.toggle('is-active', b.getAttribute('data-rq-example') === ex.key);
        b.setAttribute('aria-pressed', b.getAttribute('data-rq-example') === ex.key ? 'true' : 'false');
      });
      if (note) {
        var miss = [];
        if (f.category && f.category.options.length > 1 && !cat) miss.push('catégorie');
        if (f.type && f.type.options.length > 1 && !typ) miss.push('type');
        note.hidden = false;
        note.textContent = 'Exemple « ' + ex.label + ' » appliqué. Relisez chaque section avant de créer' +
          (miss.length ? ' et choisissez la ' + miss.join(' et le ') + ' (aucune correspondance trouvée).' : '.');
      }
      refresh();
    }

    Array.prototype.forEach.call(root.querySelectorAll('[data-rq-example]'), function (btn) {
      btn.setAttribute('aria-pressed', 'false');
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-rq-example');
        var ex = examples.filter(function (x) { return x.key === key; })[0];
        if (ex) applyExample(ex);
      });
    });

    refresh();
  }

  /* ——— Liste ——— */
  var listRoot = document.querySelector('[data-rql]');
  if (listRoot) {
    var search = listRoot.querySelector('[data-rql-search]');
    var chips = listRoot.querySelectorAll('[data-rql-cat]');
    var rows = listRoot.querySelectorAll('[data-rql-row]');
    var empty = listRoot.querySelector('[data-rql-empty]');
    var countEl = listRoot.querySelector('[data-rql-count]');
    var showArchived = listRoot.querySelector('[data-rql-archived]');
    var cat = '';

    function apply() {
      var q = norm(search ? search.value.trim() : '');
      var arch = showArchived ? showArchived.checked : true;
      var shown = 0;
      Array.prototype.forEach.call(rows, function (r) {
        var ok = (!q || norm(r.getAttribute('data-search')).indexOf(q) !== -1)
          && (!cat || r.getAttribute('data-cat') === cat)
          && (arch || r.getAttribute('data-archived') !== '1');
        r.hidden = !ok;
        if (ok) shown++;
      });
      if (empty) empty.hidden = shown !== 0;
      if (countEl) countEl.textContent = String(shown);
    }

    Array.prototype.forEach.call(chips, function (c) {
      c.addEventListener('click', function () {
        cat = c.getAttribute('data-rql-cat') || '';
        Array.prototype.forEach.call(chips, function (o) { o.setAttribute('aria-pressed', o === c ? 'true' : 'false'); });
        apply();
      });
    });
    if (search) search.addEventListener('input', apply);
    if (showArchived) showArchived.addEventListener('change', apply);
    apply();
  }
})();
