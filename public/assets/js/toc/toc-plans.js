/* Liste des plans d'opération (/atak/toc/plans) : plans visibles, modèles de la communauté, création (vide, depuis un
 * modèle, import JSON), duplication, suppression. */
(function () {
  'use strict';
  var BOOT = window.TOC_BOOT || {};
  var esc = function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
  var $ = function (s) { return document.querySelector(s); };
  var data = { plans: [], templates: [], can_manage: false };
  var SHARE = { private: 'privé', unit: 'mon unité', community: 'communauté' };
  var ERR = { title_required: 'Donnez un titre au plan.', import_format: 'Ce fichier n’est pas un plan exporté d’Athena.', import_too_large: 'Fichier trop lourd (2 Mo au plus).', import_too_many: 'Trop d’éléments (1 500 au plus).', csrf: 'Session expirée : rechargez la page.', rate_limited: 'Trop d’actions d’affilée : attendez un instant.', schema_not_ready: 'Les plans ne sont pas encore installés sur le site (migration 4370 à lancer).', forbidden: 'Action réservée à la configuration du poste.' };

  function api(path, body) {
    var init = { method: body ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-cache', headers: { Accept: 'application/json' } };
    if (body) { init.headers['Content-Type'] = 'application/json'; init.headers['X-CSRF-Token'] = BOOT.csrf || ''; init.body = JSON.stringify(body); }
    return fetch(BOOT.base + path, init).then(function (r) { return r.json().catch(function () { return { ok: false, error: 'http_' + r.status }; }); });
  }
  function say(msg, isError) {
    var el = $('#plans-msg');
    el.textContent = msg || '';
    el.classList.toggle('is-error', !!isError);
  }
  function terrainLabel(p) {
    var t = (BOOT.terrains || []).filter(function (x) { return x.id === p.map_id; })[0];
    return t ? t.label : (p.world || '—');
  }
  function card(p, tpl) {
    var meta = terrainLabel(p) + ' · ' + p.elements + ' élément(s) · ' + (SHARE[p.sharing] || p.sharing) + (p.locked ? ' · verrouillé' : '');
    var pub = p.published_at ? 'Envoyé en jeu (' + esc(p.published_side) + ') le ' + esc(p.published_at.slice(0, 16)) : 'Pas encore envoyé en jeu';
    return '<article class="plans-card"><h3>' + esc(p.title) + '</h3><p>' + esc(meta) + '</p><p>Modifié le ' + esc(String(p.updated_at).slice(0, 16)) + ' · ' + pub + '</p>' +
      '<div class="toc-buttons">' + (tpl
        ? '<button type="button" class="toc-btn is-primary" data-use="' + p.id + '">Utiliser</button><a class="toc-btn" href="' + esc(BOOT.base + '/atak/toc/plans/' + p.id) + '">Voir</a>' + (data.can_manage ? '<button type="button" class="toc-btn" data-del="' + p.id + '">Supprimer</button>' : '')
        : '<a class="toc-btn is-primary" href="' + esc(BOOT.base + '/atak/toc/plans/' + p.id) + '">Ouvrir</a><button type="button" class="toc-btn" data-dup="' + p.id + '">Dupliquer</button>' + (p.mine || data.can_manage ? '<button type="button" class="toc-btn" data-del="' + p.id + '">Supprimer</button>' : '')) +
      '</div></article>';
  }
  function render() {
    var list = $('#plans-list');
    list.innerHTML = data.plans.length ? data.plans.map(function (p) { return card(p, false); }).join('') : '<div class="toc-empty"><p>Aucun plan pour l’instant : créez le premier plan d’opération.</p><button type="button" class="toc-btn is-primary" data-new>Nouveau plan</button></div>';
    var tl = $('#plans-templates');
    tl.innerHTML = data.templates.length ? data.templates.map(function (p) { return card(p, true); }).join('') : '<div class="toc-empty"><p>Aucun modèle dans la communauté.' + (data.can_manage ? ' Ouvrez un plan puis « Enregistrer comme modèle ».' : '') + '</p></div>';
    $('#plans-count').textContent = data.plans.length + ' plan(s)';
    $('#plans-tcount').textContent = data.templates.length + ' modèle(s)';
  }
  function load() {
    $('#plans-list').innerHTML = '<p class="toc-note">Chargement des plans…</p>';
    api('/atak/toc/api/plans').then(function (j) {
      if (!j || !j.ok) { $('#plans-list').innerHTML = '<div class="toc-empty"><p>' + esc(ERR[j && j.error] || 'Plans indisponibles pour le moment.') + '</p><button type="button" class="toc-btn" data-reload>Réessayer</button></div>'; return; }
      data = j;
      render();
    }).catch(function () { $('#plans-list').innerHTML = '<div class="toc-empty"><p>Réseau indisponible.</p><button type="button" class="toc-btn" data-reload>Réessayer</button></div>'; });
  }
  function openNew(templateId) {
    var dlg = $('#plans-new');
    dlg.hidden = false;
    var sel = $('#new-terrain');
    sel.innerHTML = (BOOT.terrains || []).map(function (t) { return '<option value="' + t.id + '" data-world="' + esc(t.world) + '">' + esc(t.label) + '</option>'; }).join('');
    sel.value = String(BOOT.defaultMap);
    $('#new-template').innerHTML = '<option value="">Plan vide</option>' + data.templates.map(function (t) { return '<option value="' + t.id + '">' + esc(t.title) + '</option>'; }).join('');
    $('#new-template').value = templateId ? String(templateId) : '';
    $('#new-is-template').closest('label').hidden = !data.can_manage;
    $('#new-title').focus();
  }
  function create(e) {
    e.preventDefault();
    var title = $('#new-title').value.trim();
    var tpl = $('#new-template').value;
    var opt = $('#new-terrain').selectedOptions[0];
    var body = tpl ? { template_id: Number(tpl), title: title } : { title: title, mapId: Number($('#new-terrain').value), world: opt ? opt.getAttribute('data-world') : '', side: $('#new-side').value, is_template: $('#new-is-template').checked };
    api('/atak/toc/api/plans', body).then(function (j) {
      if (j && j.ok) { window.location.href = BOOT.base + '/atak/toc/plans/' + j.id; return; }
      say(ERR[j && j.error] || 'Création refusée.', true);
    });
  }
  function importFile(file) {
    if (!file) return;
    if (file.size > 2000000) { say(ERR.import_too_large, true); return; }
    var reader = new FileReader();
    reader.onload = function () {
      api('/atak/toc/api/plans', { import: String(reader.result || '') }).then(function (j) {
        if (j && j.ok) { window.location.href = BOOT.base + '/atak/toc/plans/' + j.id; return; }
        say(ERR[j && j.error] || 'Import refusé.', true);
      });
    };
    reader.readAsText(file);
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('button');
    if (!t) return;
    if (t.hasAttribute('data-new')) openNew();
    if (t.hasAttribute('data-reload')) load();
    if (t.hasAttribute('data-close')) $('#plans-new').hidden = true;
    if (t.hasAttribute('data-use')) openNew(Number(t.getAttribute('data-use')));
    if (t.hasAttribute('data-dup')) api('/atak/toc/api/plans/' + t.getAttribute('data-dup') + '/duplicate', {}).then(function (j) { if (j && j.ok) { say('Copie créée.'); load(); } else say(ERR[j && j.error] || 'Copie refusée.', true); });
    if (t.hasAttribute('data-del')) {
      var id = t.getAttribute('data-del');
      var p = data.plans.concat(data.templates).filter(function (x) { return String(x.id) === id; })[0];
      if (!window.confirm('Supprimer « ' + (p ? p.title : '') + ' » ? Une version reste gardée pour l’encadrement.')) return;
      api('/atak/toc/api/plans/' + id + '/delete', {}).then(function (j) { if (j && j.ok) { say('Plan supprimé.'); load(); } else say(ERR[j && j.error] || 'Suppression refusée.', true); });
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') $('#plans-new').hidden = true;
    if (e.key === 'n' && !/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) && $('#plans-new').hidden) { e.preventDefault(); openNew(); }
  });
  document.addEventListener('DOMContentLoaded', function () {
    $('#plans-new-form').addEventListener('submit', create);
    $('#plans-import').addEventListener('change', function () { importFile(this.files[0]); this.value = ''; });
    load();
  });
})();
