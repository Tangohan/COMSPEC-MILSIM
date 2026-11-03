/*
 * Assistant d'importation du catalogue de référence (views/academy/import_wizard.php).
 * 1. Portée : tout, pays, catégorie, domaine, niveau, émetteur, recherche, sélection à la main ; compteurs en direct.
 * 2. Aperçu (serveur) : éléments, dépendances, émetteurs réels, prérequis détectés, cours Academy liés.
 * 3. Formule : places restantes, import partiel au-delà, « pays entier en un clic » selon la formule.
 * 4. Import par lots (un appel par lot, l'état est gardé par le serveur), puis rapport.
 */
(function () {
  'use strict';
  var dlg = document.querySelector('[data-aiw]');
  if (!dlg) return;
  var base = dlg.getAttribute('data-url');
  var csrf = dlg.getAttribute('data-csrf');
  var upgrade = dlg.getAttribute('data-upgrade');
  var $ = function (sel, root) { return (root || dlg).querySelector(sel); };
  var DIMS = [
    ['pays', 'Pays'],
    ['nature', 'Catégorie'],
    ['categorie', 'Type ou domaine'],
    ['niveau', 'Niveau'],
    ['emetteur', 'Émetteur']
  ];
  var S = { step: 1, data: null, scope: {}, q: '', manual: {}, preview: null, job: null, busy: false, finished: false };
  DIMS.forEach(function (d) { S.scope[d[0]] = {}; });

  function fmt(tpl) {
    var args = arguments;
    return tpl.replace(/%(\d)/g, function (_m, i) { return String(args[+i]); });
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }
  function norm(s) {
    return String(s || '').toLowerCase().replace(/ß/g, 'ss').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
  }
  function plural(n, one, many) { return n === 1 ? one : many; }
  function error(msg) {
    var el = $('[data-aiw-error]');
    el.textContent = msg || '';
    el.hidden = !msg;
  }
  function failure(status, body) {
    if (status === 401 || status === 419) return 'Session expirée. Rechargez la page avant de réessayer.';
    if (status === 403 && body && body.code === 'forbidden') return 'Votre compte ne permet pas d’importer dans le référentiel.';
    if (status === 403 && body && body.code === 'bulk') return 'Importer un pays entier en un clic n’est pas inclus dans votre formule.';
    if (status === 403 && body && body.code === 'quota') return 'Plafond de votre formule atteint : aucune qualification ne peut être ajoutée.';
    if (body && body.code === 'empty') return 'Aucun élément retenu : élargissez la portée.';
    return 'Le serveur ne répond pas. Réessayez dans un instant.';
  }
  function request(path, form) {
    var opts = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (form) {
      form.append('_csrf_token', csrf);
      opts.method = 'POST';
      opts.body = form;
      opts.headers['X-CSRF-Token'] = csrf;
    }
    return fetch(base + path, opts).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (body) {
        if (!r.ok || !body.ok) {
          var e = new Error(failure(r.status, body));
          e.body = body;
          throw e;
        }
        return body;
      });
    });
  }

  /* ——— Portée ——— */
  function selected(except) {
    var words = norm(S.q).split(' ').filter(Boolean);
    return S.data.rows.filter(function (r) {
      for (var i = 0; i < DIMS.length; i++) {
        var k = DIMS[i][0];
        if (k === except) continue;
        var wanted = S.scope[k];
        if (Object.keys(wanted).length && !wanted[r[k]]) return false;
      }
      for (var j = 0; j < words.length; j++) {
        if (r.search.indexOf(words[j]) === -1) return false;
      }
      return true;
    });
  }
  function chosen() {
    var rows = selected();
    var manual = Object.keys(S.manual).length ? rows.filter(function (r) { return S.manual[r.id]; }) : rows;
    return { rows: rows, picked: manual };
  }
  function anyScope() {
    return S.q !== '' || DIMS.some(function (d) { return Object.keys(S.scope[d[0]]).length > 0; });
  }
  function renderDims() {
    var html = '';
    DIMS.forEach(function (d) {
      var k = d[0];
      var opts = S.data.options[k] || [];
      html += '<fieldset class="aiw-dim aiw-dim--' + k + '"><legend>' + esc(d[1]) + '</legend>';
      if (k === 'emetteur') {
        html += '<label class="aiw-dim__filter"><span class="aiw-sr">Filtrer les émetteurs</span><input type="search" data-aiw-issuer-q placeholder="Filtrer les émetteurs…" autocomplete="off"></label>';
      }
      html += '<div class="aiw-chips">';
      opts.forEach(function (o) {
        var native = o.native && o.native !== o.label ? ' <small translate="no">' + esc(o.native) + '</small>' : '';
        html += '<label class="aiw-chip" data-dim="' + k + '" data-value="' + esc(o.value) + '"><input type="checkbox" value="' + esc(o.value) + '" data-aiw-dim="' + k + '">'
          + (o.flag || '') + '<span' + (k === 'emetteur' ? ' translate="no"' : '') + '>' + esc(o.label) + native + '</span> <b class="aiw-n" data-n></b></label>';
      });
      html += '</div></fieldset>';
    });
    $('[data-aiw-dims]').innerHTML = html;
  }
  function refreshScope() {
    var c = chosen();
    var already = c.picked.filter(function (r) { return r.done; }).length;
    var total = S.data.rows.length;
    $('[data-aiw-all]').setAttribute('aria-pressed', anyScope() ? 'false' : 'true');
    $('[data-aiw-all-count]').textContent = fmt('%1 éléments', total);
    $('[data-aiw-count]').innerHTML = c.picked.length === 0
      ? '<b>Aucun élément retenu</b> <span>Élargissez la portée ou la recherche.</span>'
      : '<b>' + esc(fmt('%1 éléments, dont %2 déjà dans votre référentiel', c.picked.length, already)) + '</b> <span>' + esc(fmt('%1 à importer', c.picked.length - already)) + '</span>';
    DIMS.forEach(function (d) {
      var k = d[0];
      var counts = {};
      selected(k).forEach(function (r) { counts[r[k]] = (counts[r[k]] || 0) + 1; });
      dlg.querySelectorAll('.aiw-chip[data-dim="' + k + '"]').forEach(function (chip) {
        var v = chip.getAttribute('data-value');
        var n = counts[v] || 0;
        chip.querySelector('[data-n]').textContent = String(n);
        chip.classList.toggle('is-empty', n === 0 && !S.scope[k][v]);
        chip.querySelector('input').checked = !!S.scope[k][v];
      });
    });
    var manualN = Object.keys(S.manual).length;
    $('[data-aiw-manual-count]').textContent = manualN ? fmt('%1 cochés', manualN) : '';
    renderList(c.rows);
    $('[data-aiw-next]').disabled = c.picked.length === 0 || c.picked.length === already;
  }
  function renderList(rows) {
    var list = $('[data-aiw-list]');
    if (!$('[data-aiw-manual]').open) { list.innerHTML = ''; return; }
    var shown = rows.slice(0, 300);
    list.innerHTML = shown.map(function (r) {
      return '<li class="' + (r.done ? 'is-done' : '') + '"><label><input type="checkbox" value="' + esc(r.id) + '"' + (S.manual[r.id] ? ' checked' : '') + (r.done ? ' disabled' : '') + '>'
        + '<span><b>' + esc(r.name) + '</b>' + (r.official ? ' <small translate="no">' + esc(r.official) + '</small>' : '')
        + '<small class="aiw-meta"><span translate="no">' + esc(r.code) + '</span> · <span translate="no">' + esc(r.emetteur) + '</span>' + (r.done ? ' · <span>déjà dans votre référentiel</span>' : '') + '</small></span></label></li>';
    }).join('') + (rows.length > shown.length ? '<li class="aiw-more">' + esc(fmt('%1 autres éléments : affinez la recherche pour les voir.', rows.length - shown.length)) + '</li>' : '');
  }

  /* ——— Aperçu ——— */
  function pickedIds() { return chosen().picked.map(function (r) { return r.id; }); }
  function formFor() {
    var f = new FormData();
    pickedIds().forEach(function (id) { f.append('ids[]', id); });
    f.append('deps', $('[data-aiw-deps]').checked ? '1' : '0');
    var p = dlg.querySelector('input[name="aiw-prereqs"]:checked');
    f.append('prereqs', p ? p.value : 'none');
    return f;
  }
  function loadPreview() {
    S.busy = true;
    error('');
    $('[data-aiw-preview-count]').textContent = 'Calcul de l’aperçu…';
    $('[data-aiw-preview]').innerHTML = '';
    syncFoot();
    return request('/apercu', formFor()).then(function (body) {
      S.preview = body;
      renderPreview();
    }).catch(function (e) { error(e.message); }).then(function () { S.busy = false; syncFoot(); });
  }
  function block(title, count, inner, open) {
    return '<details class="aiw-block"' + (open ? ' open' : '') + '><summary><b>' + esc(title) + '</b> <span class="aiw-n">' + count + '</span></summary>' + inner + '</details>';
  }
  function renderPreview() {
    var p = S.preview;
    var c = p.counts;
    var deps = $('[data-aiw-deps]').checked;
    var sure = dlg.querySelector('input[name="aiw-prereqs"]:checked').value === 'sure';
    $('[data-aiw-preview-count]').innerHTML = '<b>' + esc(fmt('%1 à importer', c.to_create)) + '</b> <span>' + esc(fmt('%1 déjà dans votre référentiel, ignorés', c.already)) + '</span>'
      + (c.dependencies ? ' <span>' + esc(fmt('dont %1 qualifications préalables', c.dependencies)) + '</span>' : '');
    var names = {};
    p.items.forEach(function (it) { names[it.id] = it.name; });
    var items = p.items.map(function (it) {
      var tags = '';
      if (it.already) tags += '<span class="aiw-tag">Déjà présent, ignoré</span>';
      if (it.dependency) tags += '<span class="aiw-tag aiw-tag--dep">' + esc(fmt('Préalable de %1', it.required_by.map(function (r) { return names[r] || r; }).join(', '))) + '</span>';
      if (!it.already && sure && it.prereqs_sure.length) tags += '<span class="aiw-tag aiw-tag--ok">' + esc(fmt('%1 prérequis sûrs', it.prereqs_sure.length)) + '</span>';
      return '<li class="' + (it.already ? 'is-done' : '') + '"><span><b>' + esc(it.name) + '</b> <small translate="no">' + esc(it.code) + '</small></span>' + tags + '</li>';
    }).join('');
    var html = block('Éléments', p.items.length, '<ul class="aiw-plan">' + items + '</ul>', true);
    if (!deps && p.missing.length) {
      html += block('Qualifications préalables manquantes', p.missing.length, '<p class="aiw-hint">Cochez « Importer les dépendances » pour les ajouter aussi. Sans elles, ces prérequis restent à importer plus tard.</p><ul class="aiw-plan">'
        + p.missing.map(function (m) { return '<li><span><b>' + esc(m.name) + '</b> <small translate="no">' + esc(m.code) + '</small></span></li>'; }).join('') + '</ul>', true);
    }
    html += block('Émetteurs réels importés avec', p.issuers.length, '<ul class="aiw-plan">' + p.issuers.map(function (i) {
      return '<li><span><b translate="no">' + esc(i.name) + '</b>' + (i.short_name ? ' <small translate="no">' + esc(i.short_name) + '</small>' : '') + '</span>'
        + '<span class="aiw-tag' + (i.new ? ' aiw-tag--ok' : '') + '">' + esc(i.new ? 'Nouveau' : 'Déjà dans votre référentiel') + '</span> <span class="aiw-n">' + i.count + '</span></li>';
    }).join('') + '</ul>', false);
    var prereqItems = p.items.filter(function (it) { return !it.already && it.prereqs_sure.length; });
    html += block('Prérequis détectés', sure ? c.prereqs_sure : 0, sure
      ? (prereqItems.length ? '<ul class="aiw-plan">' + prereqItems.map(function (it) { return '<li><span><b>' + esc(it.name) + '</b><small class="aiw-meta">' + it.prereqs_sure.map(esc).join(' · ') + '</small></span></li>'; }).join('') + '</ul>'
        : '<p class="aiw-hint">Aucun prérequis sûr détecté.</p>') + '<p class="aiw-hint">Les autres prérequis détectés se revoient ensuite, fiche par fiche, sur l’écran « Prérequis détectés ».</p>'
      : '<p class="aiw-hint">Aucun prérequis ne sera ajouté. Vous pourrez les revoir sur l’écran « Prérequis détectés ».</p>', false);
    html += block('Cours Academy liés', p.courses.length, p.courses.length ? '<ul class="aiw-plan">' + p.courses.map(function (co) {
      return '<li><a class="text-link" href="' + esc(co.url) + '" target="_blank" rel="noopener">' + esc(co.title) + '</a></li>';
    }).join('') + '</ul>' : '<p class="aiw-hint">Aucun cours Academy lié à ces éléments.</p>', false);
    $('[data-aiw-preview]').innerHTML = html;
  }

  /* ——— Formule ——— */
  function renderQuota() {
    var p = S.preview;
    var q = p.quota;
    var b = p.bulk;
    var html = '';
    if (q.unlimited) {
      html += '<div class="aiw-gauge is-ok"><p><b>Imports illimités avec votre formule</b></p><p>' + esc(fmt('%1 qualifications seront importées.', q.to_create)) + '</p></div>';
    } else {
      var pctUsed = q.limit ? Math.min(100, Math.round(q.used * 100 / q.limit)) : 0;
      var pctNew = q.limit ? Math.min(100 - pctUsed, Math.round(q.will_create * 100 / q.limit)) : 0;
      html += '<div class="aiw-gauge' + (q.over > 0 ? ' is-over' : '') + '">'
        + '<p><b>' + esc(fmt('%1 imports restants sur %2', q.remaining, q.limit)) + '</b> <span>' + esc(fmt('%1 déjà utilisés', q.used)) + '</span></p>'
        + '<div class="aiw-gauge__bar" role="img" aria-label="' + esc(fmt('%1 utilisés, %2 avec cet import, sur %3', q.used, q.used + q.will_create, q.limit)) + '"><i style="width:' + pctUsed + '%"></i><i class="is-new" style="width:' + pctNew + '%"></i></div>'
        + '<p>' + esc(fmt('%1 qualifications seront importées.', q.will_create)) + '</p>';
      if (q.over > 0) {
        html += '<p class="aiw-warn">' + esc(fmt('%1 dépassent le plafond de votre formule et ne seront pas importées. Ce qui est déjà dans votre référentiel reste utilisable.', q.over)) + '</p>'
          + (q.offer ? '<p>' + esc(fmt('Formule %1 : %2', q.offer.name, q.offer.value === 0 ? 'illimité' : fmt('%1 qualifications importées', q.offer.value))) + '</p>' : '')
          + '<a class="button small" href="' + esc(q.upgrade_url || upgrade) + '"><span>Voir les formules</span></a>';
      }
      html += '</div>';
    }
    if (b.packs.length) {
      var labels = b.packs.map(function (x) { return x.label; }).join(', ');
      html += b.locked
        ? '<div class="aiw-gauge is-over"><p><b>Import d’un pays entier en un clic</b></p><p>' + esc(fmt('Votre sélection couvre tout un pays (%1). C’est inclus dans la formule %2.', labels, b.offer ? b.offer.name : 'Pro'))
          + '</p><p>Vous pouvez restreindre la portée (catégorie, domaine, niveau) ou cocher les éléments à la main, dans la limite de votre formule.</p><a class="button small" href="' + esc(b.upgrade_url || upgrade) + '"><span>Voir les formules</span></a></div>'
        : '<p class="aiw-hint">' + esc(fmt('Import d’un pays entier en un clic inclus dans votre formule : %1.', labels)) + '</p>';
    }
    $('[data-aiw-quota]').innerHTML = html;
  }
  function canImport() {
    var p = S.preview;
    return p && !p.bulk.locked && p.quota.will_create > 0;
  }

  /* ——— Import ——— */
  function setProgress(pos, total) {
    var pct = total ? Math.round(pos * 100 / total) : 100;
    $('[data-aiw-bar]').style.width = pct + '%';
    $('.aiw-bar').setAttribute('aria-valuenow', String(pct));
    $('[data-aiw-progress-label]').textContent = fmt('%1 sur %2 traités', pos, total);
  }
  function runImport() {
    S.busy = true;
    S.finished = false;
    error('');
    $('[data-aiw-report]').innerHTML = '';
    syncFoot();
    request('/demarrer', formFor()).then(function (body) {
      S.job = body.job;
      setProgress(0, body.total);
      return next(0, body.total, 0);
    }).catch(function (e) {
      error(e.message);
      S.busy = false;
      syncFoot();
    });
  }
  function next(from, total, retries) {
    var f = new FormData();
    f.append('job', S.job);
    f.append('from', String(from));
    return request('/lot', f).then(function (body) {
      setProgress(body.pos, body.total);
      if (body.done) {
        S.busy = false;
        S.finished = true;
        renderReport(body.report);
        syncFoot();
        return null;
      }
      return next(body.pos, body.total, 0);
    }, function (e) {
      // Coupure réseau : le même lot est redemandé (le serveur ne le refait pas s'il est déjà traité).
      if (retries < 3 && !(e.body && e.body.code)) {
        return new Promise(function (res) { setTimeout(res, 1500 * (retries + 1)); }).then(function () { return next(from, total, retries + 1); });
      }
      error(e.message);
      S.busy = false;
      syncFoot();
      return null;
    });
  }
  function reportList(items, withLinks) {
    return '<ul class="aiw-plan">' + items.slice(0, 200).map(function (it) {
      return '<li><span>' + (withLinks && it.url ? '<a class="text-link" href="' + esc(it.url) + '">' + esc(it.name) + '</a>' : '<b>' + esc(it.name) + '</b>') + '</span>'
        + (it.admin_url ? '<a class="text-link aiw-small" href="' + esc(it.admin_url) + '"><span>Fiche du référentiel</span></a>' : '') + '</li>';
    }).join('') + (items.length > 200 ? '<li class="aiw-more">' + esc(fmt('et %1 autres', items.length - 200)) + '</li>' : '') + '</ul>';
  }
  function renderReport(r) {
    $('[data-aiw-progress-label]').textContent = 'Import terminé';
    var html = '<div class="aiw-summary">'
      + '<p class="is-ok"><b>' + r.created.length + '</b> <span>' + esc(plural(r.created.length, 'importé', 'importés')) + '</span></p>'
      + '<p><b>' + r.skipped.length + '</b> <span>' + esc(plural(r.skipped.length, 'ignoré (déjà présent)', 'ignorés (déjà présents)')) + '</span></p>'
      + (r.blocked.length ? '<p class="is-warn"><b>' + r.blocked.length + '</b> <span>hors formule</span></p>' : '')
      + (r.errors.length ? '<p class="is-bad"><b>' + r.errors.length + '</b> <span>' + esc(plural(r.errors.length, 'erreur', 'erreurs')) + '</span></p>' : '')
      + '</div>';
    if (r.prereqs_added) html += '<p class="aiw-hint">' + esc(fmt('%1 prérequis sûrs ajoutés aux fiches importées.', r.prereqs_added)) + '</p>';
    if (r.created.length) html += block('Importés', r.created.length, reportList(r.created, true), true);
    if (r.blocked.length) {
      html += block('Hors formule', r.blocked.length, '<p class="aiw-warn">Plafond de votre formule atteint : ces éléments n’ont pas été importés.</p><a class="button small" href="' + esc((r.quota && r.quota.upgrade_url) || upgrade) + '"><span>Voir les formules</span></a>' + reportList(r.blocked, true), true);
    }
    if (r.errors.length) html += block('Erreurs', r.errors.length, '<p class="aiw-hint">Relancez l’assistant : ce qui est déjà importé est ignoré, rien n’est créé en double.</p>' + reportList(r.errors, false), true);
    if (r.skipped.length) html += block('Ignorés', r.skipped.length, reportList(r.skipped, true), false);
    html += '<div class="aiw-links">'
      + (r.prerequisites_url ? '<a class="button small outline" href="' + esc(r.prerequisites_url) + '"><span>Revoir les prérequis détectés</span></a>' : '')
      + '<a class="button small outline" href="' + esc(r.referentiel_url) + '"><span>Ouvrir le référentiel des qualifications</span></a>'
      + '<a class="text-link" href="' + esc(r.catalogue_url) + '"><span>Voir dans le catalogue</span></a></div>';
    $('[data-aiw-report]').innerHTML = html;
  }

  /* ——— Étapes ——— */
  function syncFoot() {
    var back = $('[data-aiw-back]');
    var nextBtn = $('[data-aiw-next]');
    var label = $('[data-aiw-next-label]');
    back.hidden = S.step === 1 || S.step === 4;
    back.disabled = S.busy;
    if (S.step === 1) { label.textContent = 'Voir l’aperçu'; }
    if (S.step === 2) { label.textContent = 'Suivant'; nextBtn.disabled = S.busy || !S.preview; }
    if (S.step === 3) { label.textContent = 'Lancer l’import'; nextBtn.disabled = S.busy || !canImport(); }
    if (S.step === 4) { label.textContent = S.finished ? 'Fermer' : 'Import en cours…'; nextBtn.disabled = S.busy; }
  }
  function go(step) {
    S.step = step;
    dlg.querySelectorAll('[data-aiw-step]').forEach(function (sec) { sec.hidden = +sec.getAttribute('data-aiw-step') !== step; });
    dlg.querySelectorAll('.ds-stepper__item').forEach(function (li, i) {
      li.classList.toggle('ds-stepper__item--active', i + 1 === step);
      li.classList.toggle('ds-stepper__item--done', i + 1 < step);
      if (i + 1 === step) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
      var num = li.querySelector('.ds-stepper__num');
      if (num) num.textContent = i + 1 < step ? '✓' : String(i + 1);
    });
    error('');
    if (step === 1) refreshScope();
    if (step === 2) loadPreview();
    if (step === 3) renderQuota();
    if (step === 4) runImport();
    syncFoot();
    var body = $('.aiw__body');
    if (body) body.scrollTop = 0;
  }

  dlg.addEventListener('change', function (e) {
    var t = e.target;
    if (t.matches('[data-aiw-dim]')) {
      var k = t.getAttribute('data-aiw-dim');
      if (t.checked) S.scope[k][t.value] = true; else delete S.scope[k][t.value];
      refreshScope();
    } else if (t.closest('[data-aiw-list]')) {
      if (t.checked) S.manual[t.value] = true; else delete S.manual[t.value];
      refreshScope();
    } else if (t.matches('[data-aiw-deps], input[name="aiw-prereqs"]') && S.step === 2) {
      loadPreview();
    }
  });
  dlg.addEventListener('input', function (e) {
    var t = e.target;
    if (t.matches('[data-aiw-q]')) {
      S.q = t.value;
      refreshScope();
    } else if (t.matches('[data-aiw-issuer-q]')) {
      var w = norm(t.value);
      dlg.querySelectorAll('.aiw-chip[data-dim="emetteur"]').forEach(function (chip) {
        chip.hidden = w !== '' && norm(chip.getAttribute('data-value')).indexOf(w) === -1;
      });
    }
  });
  $('[data-aiw-manual]').addEventListener('toggle', function () { if (S.data) refreshScope(); });
  $('[data-aiw-all]').addEventListener('click', function () {
    DIMS.forEach(function (d) { S.scope[d[0]] = {}; });
    S.manual = {};
    S.q = '';
    $('[data-aiw-q]').value = '';
    refreshScope();
  });
  dlg.querySelectorAll('[data-aiw-check]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (b.getAttribute('data-aiw-check') === 'all') selected().forEach(function (r) { if (!r.done) S.manual[r.id] = true; });
      else S.manual = {};
      refreshScope();
    });
  });
  $('[data-aiw-back]').addEventListener('click', function () { if (!S.busy && S.step > 1) go(S.step - 1); });
  $('[data-aiw-next]').addEventListener('click', function () {
    if (S.busy) return;
    if (S.step === 4) {
      if (S.finished) window.location.reload();
      return;
    }
    go(S.step + 1);
  });
  function close() {
    if (S.busy && S.step === 4) return;
    if (typeof dlg.close === 'function') dlg.close(); else dlg.removeAttribute('open');
    if (S.finished) window.location.reload();
  }
  $('[data-aiw-close]').addEventListener('click', close);
  dlg.addEventListener('cancel', function (e) { if (S.busy && S.step === 4) e.preventDefault(); else if (S.finished) window.location.reload(); });
  document.querySelectorAll('[data-aiw-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', '');
      if (S.data) { if (S.step === 4 && !S.busy) go(1); return; }
      request('').then(function (body) {
        S.data = body;
        renderDims();
        go(1);
      }).catch(function (e) {
        $('[data-aiw-count]').textContent = '';
        error(e.message);
      });
    });
  });
})();
