/*
 * Bureau de suivi roleplay (views/admin/organization/roleplay_followup.php) : filtres, recherche, fenêtre d'un membre.
 * Le filtre et la recherche sont gardés le temps de la session : chaque enregistrement recharge la page.
 * Textes en français : traduits à l'affichage par i18n_dom.js (lang/en/site_auto.php, js-bundle).
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-rps-board]');
  if (!root) return;
  var KEY = 'athena.rps.view';
  var tbody = root.querySelector('[data-rps-rows]');
  var rows = tbody ? Array.prototype.slice.call(tbody.querySelectorAll('tr')) : [];
  var search = root.querySelector('[data-rps-search]');
  var stage = root.querySelector('[data-rps-stage]');
  var noMatch = root.querySelector('[data-rps-nomatch]');
  var shown = root.querySelector('[data-rps-shown]');
  var filter = root.getAttribute('data-default-filter') || 'tous';

  try {
    var saved = JSON.parse(sessionStorage.getItem(KEY) || 'null');
    if (saved && typeof saved === 'object') {
      if (root.querySelector('.rps-filter[data-rps-filter="' + String(saved.f).replace(/[^a-z-]/g, '') + '"]')) filter = saved.f;
      if (search && saved.q) search.value = String(saved.q);
      if (stage && saved.s) stage.value = String(saved.s);
    }
  } catch (e) {}

  function apply() {
    var q = search ? search.value.trim().toLowerCase() : '';
    var st = stage ? stage.value : '';
    var n = 0;
    rows.forEach(function (tr) {
      var on = (' ' + tr.getAttribute('data-filters') + ' ').indexOf(' ' + filter + ' ') !== -1
        && (!q || tr.getAttribute('data-search').indexOf(q) !== -1)
        && (!st || tr.getAttribute('data-stage') === st);
      tr.hidden = !on;
      if (on) n++;
    });
    if (noMatch) noMatch.hidden = n > 0;
    if (shown) shown.textContent = String(n);
    Array.prototype.forEach.call(root.querySelectorAll('.rps-filter'), function (b) {
      b.setAttribute('aria-pressed', b.getAttribute('data-rps-filter') === filter ? 'true' : 'false');
    });
    try { sessionStorage.setItem(KEY, JSON.stringify({ f: filter, q: search ? search.value : '', s: st })); } catch (e) {}
  }

  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-rps-filter]');
    if (b) {
      filter = b.getAttribute('data-rps-filter');
      apply();
      return;
    }
    var open = e.target.closest('[data-rps-open]');
    if (open) openSheet(open.closest('tr'));
  });
  if (search) search.addEventListener('input', apply);
  if (stage) stage.addEventListener('change', apply);

  // ---------- Fenêtre d'un membre ----------
  var sheet = document.getElementById('rps-sheet');

  function setFact(name, text, late) {
    var dd = sheet.querySelector('[data-sheet="' + name + '"]');
    if (!dd) return;
    dd.textContent = text || '—';
    dd.classList.toggle('is-late', !!late);
    dd.classList.toggle('is-empty', !text);
  }

  function selectValue(select, value) {
    var found = Array.prototype.some.call(select.options, function (o) { return o.value === value; });
    if (!found && value) {
      // Étape retirée de la liste depuis : on la montre sans la proposer comme choix ordinaire.
      var o = document.createElement('option');
      o.value = value;
      o.textContent = value;
      o.setAttribute('data-rps-extra', '1');
      select.appendChild(o);
    }
    select.value = value;
  }

  function openSheet(tr) {
    if (!sheet || !tr) return;
    var d;
    try { d = JSON.parse(tr.getAttribute('data-sheet') || '{}'); } catch (e) { return; }
    Array.prototype.forEach.call(sheet.querySelectorAll('[data-rps-extra]'), function (o) { o.remove(); });
    sheet.querySelector('#rps-sheet-name').textContent = d.name || '';
    sheet.querySelector('[data-sheet-where]').textContent = d.stage ? d.stage : 'Pas encore d’étape';
    Object.keys(d.urls || {}).forEach(function (k) {
      var f = sheet.querySelector('[data-sheet-form="' + k + '"]');
      if (f) f.setAttribute('action', d.urls[k]);
    });
    selectValue(sheet.querySelector('#rps-stage'), d.stage || '');
    selectValue(sheet.querySelector('#rps-tutor'), d.tutorId ? String(d.tutorId) : '');
    sheet.querySelector('[data-sheet-dossier]').setAttribute('href', d.dossier || '#');
    setFact('progress', d.progress === null || d.progress === undefined ? '' : d.progress + ' %');
    setFact('status', d.status);
    setFact('function', d.function);
    setFact('track', d.track);
    setFact('origin', d.origin);
    setFact('eligible', d.eligible ? 'Prêt' : 'À compléter');
    setFact('interview', d.interview, d.interviewLate);
    setFact('medical', d.medical, d.medicalLate);
    setFact('rotation', d.rotation, d.rotationLate);
    setFact('bilanNext', d.bilanNext, d.bilanLate);
    setFact('bilanLast', d.bilanLast);
    setFact('event', d.eventTitle ? d.eventTitle + (d.eventMeta ? ' (' + d.eventMeta + ')' : '') : '');
    setFact('notes', d.notes);
    if (typeof sheet.showModal === 'function') sheet.showModal();
    else sheet.setAttribute('open', '');
  }

  if (sheet) {
    sheet.addEventListener('click', function (e) {
      if (e.target === sheet || e.target.closest('[data-rps-close]')) {
        if (typeof sheet.close === 'function') sheet.close();
        else sheet.removeAttribute('open');
      }
    });
  }

  apply();
})();
