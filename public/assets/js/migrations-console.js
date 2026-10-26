/* Interface web des migrations : filtres des étapes, confirmations, console en direct (défilement, progression). */
(function () {
  'use strict';

  // Confirmations avant de lancer
  document.querySelectorAll('form[data-mg-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-mg-confirm'))) { e.preventDefault(); return; }
      f.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
    });
  });

  // Filtres de la liste des étapes
  var search = document.querySelector('[data-mg-search]');
  var group = document.querySelector('[data-mg-group]');
  var status = document.querySelector('[data-mg-status]');
  var count = document.querySelector('[data-mg-count]');
  var empty = document.querySelector('[data-mg-empty]');
  var norm = function (s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
  function apply() {
    var q = norm(search ? search.value.trim() : '');
    var g = group ? group.value : '';
    var st = status ? status.value : '';
    var shown = 0;
    document.querySelectorAll('[data-mg-tbody]').forEach(function (tb) {
      var inGroup = 0;
      tb.querySelectorAll('[data-mg-row]').forEach(function (r) {
        var stOk = !st || (st === 'issues' ? r.dataset.status !== 'ok' : r.dataset.status === st);
        var ok = (!g || r.dataset.group === g) && stOk && (!q || norm(r.dataset.search).indexOf(q) !== -1);
        r.classList.toggle('is-hidden', !ok);
        if (ok) { inGroup++; }
      });
      tb.classList.toggle('is-hidden', inGroup === 0);
      shown += inGroup;
    });
    if (count) { count.textContent = String(shown); }
    if (empty) { empty.hidden = shown > 0; }
  }
  var note = document.querySelector('[data-mg-issues-note]');
  var showAll = document.querySelector('[data-mg-show-all]');
  var refresh = function () { apply(); if (note) { note.hidden = !status || status.value !== 'issues'; } };
  [search, group, status].forEach(function (el) { if (el) { el.addEventListener('input', refresh); el.addEventListener('change', refresh); } });
  if (showAll && status) { showAll.addEventListener('click', function () { status.value = ''; refresh(); }); }
  if (status) { refresh(); }

  // Console en direct : suit la fin du flux et la progression « » [n/total] ».
  var live = document.querySelector('[data-mg-live]');
  if (live) {
    var bar = document.querySelector('[data-mg-progress]');
    var label = document.querySelector('[data-mg-progress-label]');
    var runbar = document.querySelector('[data-mg-runbar]');
    var total = runbar ? parseInt(runbar.dataset.total || '0', 10) : 0;
    var follow = true;
    var seen = 0;
    live.addEventListener('scroll', function () {
      follow = live.scrollTop + live.clientHeight >= live.scrollHeight - 40;
    });
    var tick = function () {
      var steps = live.querySelectorAll('.mg-c-step');
      if (steps.length !== seen) {
        seen = steps.length;
        var last = steps[steps.length - 1];
        if (last && label) { label.textContent = last.textContent.replace(/^»\s*/, ''); }
        if (bar && total > 0) { bar.style.width = Math.min(100, Math.round(seen * 100 / total)) + '%'; }
      }
      if (follow) { live.scrollTop = live.scrollHeight; }
      if (document.querySelector('[data-mg-done]')) {
        if (bar) { bar.style.width = '100%'; }
        if (label) { label.textContent = 'Terminé'; }
        if (runbar) { runbar.classList.add('is-done'); }
        live.scrollTop = live.scrollHeight;
        return;
      }
      window.setTimeout(tick, 400);
    };
    tick();
  }
})();
