/**
 * Doctrine › Publier un document (views/admin/documents/publish_form.php) : assistant étape par étape.
 * Même principe que l’assistant de coopération (cooperation/create-wizard.js) et l’ajout de membre
 * (eff-new-member.js) : une étape à la fois, stepper partagé (views/partials/ui/stepper.php), vérification
 * avant d’avancer, récapitulatif. En plus : import d’un fichier texte par glisser-déposer, listes de
 * destinataires filtrables avec compteurs, saisie conservée sur l’appareil jusqu’à l’enregistrement.
 * Sans ce script, le formulaire complet reste affiché et utilisable.
 */
(function () {
  'use strict';

  var form = document.querySelector('[data-doc-wizard]');
  if (!form) return;
  var root = form.closest('.dct') || document;
  var steps = Array.prototype.slice.call(form.querySelectorAll('[data-wiz-step]'));
  var labels = ['Le texte', 'Le classement', 'Les destinataires', 'La lecture attendue', 'Récapitulatif & publication'];
  var progress = form.querySelector('[data-wiz-progress]');
  var heading = form.querySelector('[data-wiz-heading]');
  var nextLabel = form.querySelector('[data-wiz-next-label]');
  var bar = form.querySelector('[data-wiz-bar]');
  var pager = form.querySelector('[data-wiz-pager]');
  var prev = form.querySelector('[data-wiz-prev]');
  var next = form.querySelector('[data-wiz-next]');
  var action = document.getElementById('doc-publish-action');
  var targetedBox = document.getElementById('audience-targeted');
  var stepperItems = Array.prototype.slice.call(form.querySelectorAll('.ds-stepper__item'));
  var total = steps.length;
  var current = 0;
  var reached = form.hasAttribute('data-wiz-edit') ? total - 1 : 0;
  var storageKey = 'athena.docPublish.' + (form.getAttribute('data-draft-key') || 'new');

  form.classList.add('is-enhanced');
  form.setAttribute('novalidate', '');
  Array.prototype.forEach.call(form.querySelectorAll('[data-wiz-js]'), function (el) { el.hidden = false; });
  if (progress) progress.hidden = false;
  if (pager) pager.hidden = false;

  function $(sel) { return form.querySelector(sel); }
  function field(name) { return form.querySelector('[name="' + name + '"]'); }
  function editor() {
    return window.tinymce && window.tinymce.get ? window.tinymce.get('doc-body-html') : null;
  }
  function bodyHtml() {
    var ed = editor();
    if (ed) return ed.getContent();
    var ta = document.getElementById('doc-body-html');
    return ta ? ta.value : '';
  }
  function setBody(html) {
    var ta = document.getElementById('doc-body-html');
    if (ta) ta.value = html;
    var ed = editor();
    if (ed) ed.setContent(html);
  }
  function selectedText(select) {
    if (!select || select.selectedIndex < 0 || !select.value) return '';
    return select.options[select.selectedIndex].text;
  }
  function fmtDate(v) {
    if (!v) return '';
    var d = new Date(v);
    return isNaN(d.getTime()) ? v : d.toLocaleString(document.documentElement.lang || 'fr-FR', { dateStyle: 'short', timeStyle: 'short' });
  }

  /* ── Messages d’erreur sous les champs ─────────────────────────── */

  function setMsg(key, text, input) {
    var el = form.querySelector('[data-wiz-msg="' + key + '"]');
    if (el) {
      el.textContent = text || '';
      el.classList.toggle('is-err', !!text);
    }
    if (input) input.setAttribute('aria-invalid', text ? 'true' : 'false');
  }

  /** Retourne le premier champ en défaut de l’étape (ou null), et affiche les messages. */
  function stepProblem(i) {
    if (i === 0) {
      var title = $('#doc-title');
      var v = title ? title.value.trim() : '';
      setMsg('title', v === '' ? 'Indiquez un titre : c’est ce que les destinataires verront en premier.' : '', title);
      return v === '' ? title : null;
    }
    if (i === 1) {
      var type = $('#doc-type');
      var bad = !!type && !type.value;
      setMsg('type', bad ? (type.options.length > 1 ? 'Choisissez le type du document : il donne sa référence.' : 'Aucun type actif : créez d’abord un type de document.') : '', type);
      return bad ? type : null;
    }
    if (i === 2) {
      var targeted = audienceMode() === 'targeted';
      var none = targeted && countChecked() === 0;
      setMsg('audience', none ? 'Cochez au moins un destinataire, ou choisissez « Toute la communauté ».' : '');
      return none ? (form.querySelector('[data-wiz-pick] input[type="checkbox"]') || form.querySelector('input[name="audience_mode"]')) : null;
    }
    if (i === 3) {
      var eff = field('effective_at');
      var dl = field('acknowledgment_deadline_at');
      var exp = field('expires_at');
      var first = null;
      var dlBad = eff && dl && eff.value && dl.value && dl.value < eff.value;
      setMsg('deadline', dlBad ? 'La date limite de lecture doit suivre la prise d’effet.' : '', dl);
      if (dlBad) first = dl;
      var expBad = eff && exp && eff.value && exp.value && exp.value <= eff.value;
      setMsg('expires', expBad ? 'La fin d’application doit suivre la prise d’effet.' : '', exp);
      if (expBad && !first) first = exp;
      return first;
    }
    return null;
  }

  /* ── Navigation ────────────────────────────────────────────────── */

  function show(i, focus) {
    current = Math.max(0, Math.min(total - 1, i));
    reached = Math.max(reached, current);
    steps.forEach(function (s, idx) { s.hidden = idx !== current; });
    if (prev) prev.hidden = current === 0;
    if (next) next.hidden = current === total - 1;
    if (heading) heading.textContent = 'Étape ' + (current + 1) + ' sur ' + total + ' — ' + labels[current];
    if (nextLabel) nextLabel.textContent = current < total - 1 ? 'Étape suivante : ' + labels[current + 1] : 'Dernière étape : vérifiez puis publiez.';
    if (bar) {
      bar.setAttribute('aria-valuenow', String(current + 1));
      bar.style.setProperty('--p', ((current + 1) / total * 100).toFixed(1) + '%');
    }
    stepperItems.forEach(function (li, idx) {
      li.classList.toggle('ds-stepper__item--done', idx < current);
      li.classList.toggle('ds-stepper__item--active', idx === current);
      if (idx === current) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
      var btn = li.querySelector('button');
      if (btn) btn.disabled = idx > reached;
    });
    if (current === total - 1) recap();
    try { history.replaceState(null, '', '#etape-' + (current + 1)); } catch (e) { /* sans historique */ }
    if (focus) {
      var h = steps[current].querySelector('h2');
      if (h) h.focus();
    }
  }

  /** Avance après vérification des étapes intermédiaires (clic sur le stepper ou « Étape suivante »). */
  function goTo(i) {
    for (var k = current; k < i; k++) {
      var bad = stepProblem(k);
      if (bad) {
        show(k, false);
        bad.focus();
        return;
      }
    }
    show(i, true);
  }

  // Étapes du stepper partagé : rendues cliquables (boutons) une fois atteintes.
  stepperItems.forEach(function (li, idx) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'dct-wiz__stepbtn';
    while (li.firstChild) btn.appendChild(li.firstChild);
    li.appendChild(btn);
    // L’état (fait, en cours) passe par aria-current, tenu à jour à chaque étape.
    Array.prototype.forEach.call(btn.querySelectorAll('.sr-only'), function (n) { n.remove(); });
    btn.addEventListener('click', function () { if (idx <= current) show(idx, true); else goTo(idx); });
  });
  Array.prototype.forEach.call(form.querySelectorAll('[data-wiz-goto]'), function (b) {
    b.addEventListener('click', function () { show(parseInt(b.getAttribute('data-wiz-goto'), 10) || 0, true); });
  });
  if (next) next.addEventListener('click', function () { goTo(current + 1); });
  if (prev) prev.addEventListener('click', function () { show(current - 1, true); });

  // Entrée dans un champ simple : étape suivante plutôt qu’envoi du formulaire.
  form.addEventListener('keydown', function (e) {
    var t = e.target;
    if (e.key !== 'Enter' || !t || t.tagName !== 'INPUT' || /^(checkbox|radio|submit|button|file)$/.test(t.type)) return;
    e.preventDefault();
    if (t.hasAttribute('data-wiz-filter')) return;
    if (current < total - 1) goTo(current + 1);
  });

  /* ── Destinataires : mode, filtres, compteurs ──────────────────── */

  function audienceMode() {
    var m = form.querySelector('input[name="audience_mode"]:checked');
    return m ? m.value : 'all_members';
  }
  function countChecked() {
    return form.querySelectorAll('[data-wiz-pick] input[type="checkbox"]:checked').length;
  }
  function pickedLabels(key) {
    return Array.prototype.map.call(
      form.querySelectorAll('[data-wiz-pick="' + key + '"] input[type="checkbox"]:checked'),
      function (c) { return c.getAttribute('data-wiz-label') || c.value; }
    );
  }
  function syncAudience() {
    var targeted = audienceMode() === 'targeted';
    if (targetedBox) targetedBox.hidden = !targeted;
    Array.prototype.forEach.call(form.querySelectorAll('[data-wiz-pick]'), function (box) {
      var n = box.querySelectorAll('input[type="checkbox"]:checked').length;
      var c = box.querySelector('[data-wiz-pick-count]');
      if (c) c.textContent = n + ' choisi(s)';
    });
    var tot = form.querySelector('[data-wiz-aud-total]');
    if (tot) {
      var n = countChecked();
      tot.textContent = n === 0 ? 'Aucun destinataire choisi pour l’instant.' : n + ' choix au total (unités, emplois, rôles et membres cumulés).';
    }
    if (targeted && countChecked() > 0) setMsg('audience', '');
  }
  Array.prototype.forEach.call(form.querySelectorAll('[data-wiz-pick]'), function (box) {
    var input = box.querySelector('[data-wiz-filter]');
    var items = Array.prototype.slice.call(box.querySelectorAll('[data-wiz-item]'));
    var nomatch = box.querySelector('[data-wiz-nomatch]');
    function visible() { return items.filter(function (li) { return !li.hidden; }); }
    if (input) {
      input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        items.forEach(function (li) { li.hidden = q !== '' && (li.getAttribute('data-wiz-item') || '').indexOf(q) === -1; });
        if (nomatch) nomatch.hidden = visible().length > 0;
      });
    }
    function setAll(on) {
      visible().forEach(function (li) { var c = li.querySelector('input'); if (c) c.checked = on; });
      syncAudience();
      saveSoon();
    }
    var all = box.querySelector('[data-wiz-all]');
    var none = box.querySelector('[data-wiz-none]');
    if (all) all.addEventListener('click', function () { setAll(true); });
    if (none) none.addEventListener('click', function () { setAll(false); });
  });

  /* ── Récapitulatif et aperçu ───────────────────────────────────── */

  function plainText(html) {
    // DOMParser : aucun script ni chargement d’image n’est déclenché (texte importé ou collé).
    var body = new DOMParser().parseFromString(html || '', 'text/html').body;
    return ((body && body.textContent) || '').replace(/\s+/g, ' ').trim();
  }
  function recap() {
    var set = function (key, val) {
      var el = form.querySelector('[data-recap="' + key + '"]');
      if (!el) return;
      if (!Array.isArray(val)) { el.textContent = val; return; }
      // Une ligne par élément : chaque morceau est traduit à part (visiteur en anglais).
      el.textContent = '';
      val.forEach(function (part) {
        var sp = document.createElement('span');
        sp.className = 'dct-wiz__part';
        sp.textContent = part;
        el.appendChild(sp);
      });
    };
    var title = $('#doc-title');
    set('title', title && title.value.trim() !== '' ? title.value.trim() : '—');
    var words = plainText(bodyHtml()).split(' ').filter(Boolean).length;
    set('body', words === 0 ? 'Vide pour l’instant' : words + ' mot(s)');
    set('type', selectedText($('#doc-type')) || 'Non choisi');
    set('level', selectedText(field('classification_level')) || '—');
    set('replaces', selectedText(field('replaces_document_id')) || 'Aucun');
    if (audienceMode() === 'all_members') {
      set('audience', 'Toute la communauté');
    } else {
      var parts = [];
      [['units', 'Unités'], ['jobs', 'Emplois'], ['roles', 'Rôles'], ['users', 'Membres']].forEach(function (p) {
        var l = pickedLabels(p[0]);
        if (l.length) parts.push(p[1] + ' : ' + (l.length > 4 ? l.slice(0, 4).join(', ') + ' (+' + (l.length - 4) + ')' : l.join(', ')));
      });
      var sub = field('include_children');
      if (parts.length && sub && sub.checked && pickedLabels('units').length) parts.push('Sous-unités incluses');
      set('audience', parts.length ? parts : 'Aucun destinataire choisi');
    }
    var reading = [];
    [['reading_required', 'Lecture obligatoire'], ['acknowledgment_required', 'Accusé de lecture'], ['reminder_on_publish', 'Prévenir à la publication'], ['require_validation', 'Validation avant publication'], ['is_permanent', 'Document permanent']].forEach(function (p) {
      var c = field(p[0]);
      if (c && c.checked) reading.push(p[1]);
    });
    set('reading', reading.length ? reading : 'Aucune exigence');
    var dl = field('acknowledgment_deadline_at');
    set('deadline', dl && dl.value ? fmtDate(dl.value) : 'Pas de date limite');
    set('visibility', selectedText(field('visibility_mode')) || '—');
  }

  var previewBox = form.querySelector('[data-wiz-preview-box]');
  var previewFrame = form.querySelector('[data-wiz-preview-frame]');
  var previewBtn = form.querySelector('[data-wiz-preview]');
  if (previewBtn && previewBox && previewFrame) {
    previewBtn.addEventListener('click', function () {
      var title = $('#doc-title');
      var esc = document.createElement('div');
      esc.textContent = title ? title.value : '';
      previewFrame.srcdoc = '<!doctype html><meta charset="utf-8"><style>body{font:16px/1.65 Inter,system-ui,sans-serif;color:#0f172a;margin:16px 20px}h1{font-size:22px}table{border-collapse:collapse}td,th{border:1px solid #cbd5e1;padding:4px 8px}</style>'
        + '<h1>' + esc.innerHTML + '</h1>' + bodyHtml();
      previewBox.hidden = false;
      previewFrame.focus();
    });
    var close = form.querySelector('[data-wiz-preview-close]');
    if (close) close.addEventListener('click', function () { previewBox.hidden = true; previewBtn.focus(); });
  }

  /* ── Éditeur riche : état de chargement ────────────────────────── */

  var edStatus = form.querySelector('[data-wiz-editor-status]');
  if (edStatus) {
    var tries = 0;
    var poll = setInterval(function () {
      tries++;
      var ed = editor();
      if (ed && ed.initialized !== false) {
        clearInterval(poll);
        edStatus.hidden = true;
        if (ed.on) ed.on('input change', saveSoon);
      } else if (tries > 40) {
        clearInterval(poll);
        edStatus.classList.add('is-err');
        edStatus.textContent = 'Éditeur enrichi indisponible : le champ texte simple reste utilisable.';
      }
    }, 250);
  }

  /* ── Import d’un fichier par glisser-déposer ───────────────────── */

  var drop = form.querySelector('[data-wiz-drop]');
  var fileInput = form.querySelector('[data-wiz-file]');
  var dropStatus = form.querySelector('[data-wiz-drop-status]');
  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }
  function textToHtml(text, markdown) {
    return text.replace(/\r\n?/g, '\n').split(/\n{2,}/).map(function (block) {
      block = block.trim();
      if (!block) return '';
      if (markdown) {
        var m = block.match(/^(#{1,4})\s+(.*)$/);
        if (m && block.indexOf('\n') === -1) {
          var lvl = Math.min(4, Math.max(2, m[1].length + 1));
          return '<h' + lvl + '>' + escapeHtml(m[2]) + '</h' + lvl + '>';
        }
        if (/^([-*]|\d+\.)\s+/.test(block)) {
          var ordered = /^\d+\./.test(block);
          var lis = block.split('\n').map(function (l) { return '<li>' + escapeHtml(l.replace(/^\s*([-*]|\d+\.)\s+/, '')) + '</li>'; }).join('');
          return ordered ? '<ol>' + lis + '</ol>' : '<ul>' + lis + '</ul>';
        }
      }
      return '<p>' + escapeHtml(block).replace(/\n/g, '<br>') + '</p>';
    }).join('');
  }
  function htmlBody(text) {
    var doc = new DOMParser().parseFromString(text, 'text/html');
    Array.prototype.forEach.call(doc.querySelectorAll('script,style,iframe,object,embed,form,link,meta'), function (n) { n.remove(); });
    return doc.body ? doc.body.innerHTML : '';
  }
  function importFile(file) {
    if (!file || !dropStatus) return;
    var name = (file.name || '').toLowerCase();
    var ext = name.slice(name.lastIndexOf('.') + 1);
    if (['html', 'htm', 'txt', 'md'].indexOf(ext) === -1) {
      dropStatus.textContent = 'Format non pris en charge : utilisez un fichier .html, .txt ou .md (un document Word peut être copié-collé dans l’éditeur).';
      dropStatus.className = 'dct-wiz__busy is-err';
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      dropStatus.textContent = 'Fichier trop volumineux : 2 Mo au plus.';
      dropStatus.className = 'dct-wiz__busy is-err';
      return;
    }
    if (plainText(bodyHtml()) !== '' && !window.confirm('Remplacer le contenu actuel par celui du fichier ?')) return;
    dropStatus.className = 'dct-wiz__busy is-loading';
    dropStatus.textContent = 'Lecture du fichier…';
    var reader = new FileReader();
    reader.onload = function () {
      var text = String(reader.result || '');
      setBody(ext === 'html' || ext === 'htm' ? htmlBody(text) : textToHtml(text, ext === 'md'));
      var title = $('#doc-title');
      if (title && title.value.trim() === '') {
        var h = text.match(/<title>([^<]+)<\/title>/i) || text.match(/^#\s+(.+)$/m);
        if (h) title.value = h[1].trim().slice(0, 255);
      }
      dropStatus.className = 'dct-wiz__busy is-ok';
      dropStatus.textContent = 'Contenu importé : relisez-le dans l’éditeur.';
      saveSoon();
    };
    reader.onerror = function () {
      dropStatus.className = 'dct-wiz__busy is-err';
      dropStatus.textContent = 'Lecture du fichier impossible.';
    };
    reader.readAsText(file);
  }
  if (drop) {
    ['dragenter', 'dragover'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      drop.addEventListener(ev, function () { drop.classList.remove('is-over'); });
    });
    drop.addEventListener('drop', function (e) {
      e.preventDefault();
      if (e.dataTransfer && e.dataTransfer.files.length) importFile(e.dataTransfer.files[0]);
    });
  }
  if (fileInput) fileInput.addEventListener('change', function () { importFile(fileInput.files[0]); fileInput.value = ''; });

  /* ── Saisie conservée sur l’appareil ───────────────────────────── */

  var SKIP = { _csrf_token: 1, action: 1, document_id: 1, source_reference: 1 };
  function snapshot() {
    var data = [];
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || SKIP[el.name] || el.disabled || el.type === 'file' || el.type === 'search' || el.name === 'body_html') return;
      if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
      if (el.tagName === 'SELECT' && el.multiple) {
        Array.prototype.forEach.call(el.selectedOptions, function (o) { data.push([el.name, o.value]); });
        return;
      }
      data.push([el.name, el.value]);
    });
    data.push(['body_html', bodyHtml()]);
    return data;
  }
  function apply(data) {
    var values = {};
    data.forEach(function (p) { (values[p[0]] = values[p[0]] || []).push(p[1]); });
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || SKIP[el.name] || el.disabled || el.type === 'file' || el.type === 'search') return;
      var v = values[el.name] || [];
      if (el.type === 'checkbox' || el.type === 'radio') {
        el.checked = v.indexOf(el.value) !== -1;
      } else if (el.name === 'body_html') {
        setBody(v[0] || '');
      } else if (v.length) {
        el.value = v[0];
      }
    });
    syncAudience();
  }
  function storage() {
    try { return window.localStorage; } catch (e) { return null; }
  }
  function readDraft(key) {
    var s = storage();
    if (!s) return null;
    try { return JSON.parse(s.getItem(key) || 'null'); } catch (e) { return null; }
  }
  function writeDraft(extra) {
    var s = storage();
    if (!s) return;
    var d = { at: Date.now(), step: current, data: snapshot() };
    if (extra) Object.keys(extra).forEach(function (k) { d[k] = extra[k]; });
    try { s.setItem(storageKey, JSON.stringify(d)); } catch (e) { /* stockage plein ou refusé */ }
  }
  function dropDraft(key) {
    var s = storage();
    if (s) try { s.removeItem(key); } catch (e) { /* ignoré */ }
  }
  var initial = JSON.stringify(snapshot());
  var timer = null;
  var autosave = root.querySelector('[data-wiz-autosave]');
  function saveSoon() {
    clearTimeout(timer);
    timer = setTimeout(function () {
      if (JSON.stringify(snapshot()) === initial) return;
      writeDraft();
      if (autosave) {
        var at = new Date().toLocaleTimeString(document.documentElement.lang || 'fr-FR', { hour: '2-digit', minute: '2-digit' });
        autosave.textContent = 'Saisie conservée sur cet appareil à ' + at + ', pas encore enregistrée.';
      }
    }, 600);
  }
  form.addEventListener('input', saveSoon);
  form.addEventListener('change', function (e) {
    if (e.target && e.target.name === 'audience_mode') syncAudience();
    if (e.target && e.target.closest && e.target.closest('[data-wiz-pick]')) syncAudience();
    saveSoon();
  });

  var hasError = !!root.querySelector('.dct-flash.is-err');
  var hasSuccess = !!root.querySelector('.dct-flash.is-ok[role="status"]:not([style]):not([data-wiz-restore])');
  if (hasSuccess) {
    // Enregistrement réussi : la saisie locale de ce document (et d’un nouveau document envoyé) n’a plus d’utilité.
    var st = storage();
    var keys = [];
    if (st) try { for (var ki = 0; ki < st.length; ki++) keys.push(st.key(ki)); } catch (e) { keys = []; }
    keys.forEach(function (k) {
      if (k !== storageKey && String(k).indexOf('athena.docPublish.new') !== 0) return;
      var d = readDraft(k);
      if (d && d.submitted) dropDraft(k);
    });
  }
  var saved = readDraft(storageKey);
  var restore = form.querySelector('[data-wiz-restore]');
  if (saved && saved.data && JSON.stringify(saved.data) !== initial && restore) {
    var rt = form.querySelector('[data-wiz-restore-text]');
    if (rt) {
      var when = new Date(saved.at).toLocaleString(document.documentElement.lang || 'fr-FR', { dateStyle: 'short', timeStyle: 'short' });
      rt.textContent = hasError && saved.submitted
        ? 'L’enregistrement n’a pas abouti : votre saisie du ' + when + ' est conservée sur cet appareil.'
        : 'Une saisie non enregistrée du ' + when + ' a été retrouvée sur cet appareil.';
    }
    restore.hidden = false;
    form.querySelector('[data-wiz-restore-yes]').addEventListener('click', function () {
      apply(saved.data);
      restore.hidden = true;
      reached = total - 1;
      show(typeof saved.step === 'number' ? saved.step : 0, true);
    });
    form.querySelector('[data-wiz-restore-no]').addEventListener('click', function () {
      dropDraft(storageKey);
      restore.hidden = true;
    });
  }

  /* ── Envoi : vérification complète, indicateur d’attente ───────── */

  var sending = false;
  form.querySelectorAll('[data-dct-action]').forEach(function (b) {
    b.addEventListener('click', function () { if (action) action.value = b.getAttribute('data-dct-action'); });
  });
  form.addEventListener('submit', function (e) {
    if (sending) { e.preventDefault(); return; }
    if (window.tinymce && window.tinymce.triggerSave) window.tinymce.triggerSave();
    for (var k = 0; k < total; k++) {
      var bad = stepProblem(k);
      if (bad) {
        e.preventDefault();
        show(k, false);
        bad.focus();
        return;
      }
    }
    sending = true;
    writeDraft({ submitted: true });
    var btn = e.submitter || form.querySelector('[data-dct-action="' + (action ? action.value : 'save') + '"]');
    form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.setAttribute('aria-disabled', 'true'); b.classList.add('is-wait'); });
    if (btn) {
      btn.classList.add('is-loading');
      btn.setAttribute('aria-busy', 'true');
      var lab = btn.querySelector('span') || btn;
      lab.textContent = action && action.value === 'publish' ? 'Publication…' : 'Enregistrement…';
    }
  });

  // Retour arrière du navigateur après un envoi : page rechargée pour retrouver des boutons actifs.
  window.addEventListener('pageshow', function (e) { if (e.persisted && sending) window.location.reload(); });

  syncAudience();
  var fromHash = parseInt((location.hash.match(/^#etape-(\d)$/) || [])[1], 10);
  show(fromHash >= 1 && fromHash <= total && fromHash - 1 <= reached ? fromHash - 1 : 0, false);
})();
