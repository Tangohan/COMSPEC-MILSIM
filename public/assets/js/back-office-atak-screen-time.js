/* Back-office · Temps d'écran et rôles : membres dépliables, filtres (texte, rôle, app) et durée des apps masquée
   par défaut (préférence gardée dans le navigateur). */
(function () {
  'use strict';
  var root = document.querySelector('[data-aksq-screen]');
  if (!root) return;
  var KEY = 'aksq.screen.durations';
  var q = root.querySelector('[data-aksq-q]');
  var role = root.querySelector('[data-aksq-role]');
  var app = root.querySelector('[data-aksq-app]');
  var dur = root.querySelector('[data-aksq-durations]');
  var expandAll = root.querySelector('[data-aksq-expand-all]');
  var count = root.querySelector('[data-aksq-count]');
  var none = root.querySelector('[data-aksq-none]');
  var members = Array.prototype.slice.call(root.querySelectorAll('.aksq__member'));

  function list(el, attr) {
    try { var v = JSON.parse(el.getAttribute(attr) || '[]'); return Array.isArray(v) ? v : []; } catch (e) { return []; }
  }
  members.forEach(function (m) {
    m._roles = list(m, 'data-aksq-roles');
    m._apps = list(m, 'data-aksq-apps');
    m._name = m.getAttribute('data-aksq-name') || '';
  });

  function setOpen(m, open) {
    var btn = m.querySelector('.aksq__fold');
    var detail = m.querySelector('.aksq__member-detail');
    if (!btn || !detail) return;
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    detail.hidden = !open;
    m.classList.toggle('is-open', open);
  }
  members.forEach(function (m) {
    var btn = m.querySelector('.aksq__fold');
    if (btn) btn.addEventListener('click', function () { setOpen(m, btn.getAttribute('aria-expanded') !== 'true'); });
  });

  function visible() { return members.filter(function (m) { return !m.hidden; }); }
  function syncExpandLabel() {
    if (!expandAll) return;
    var vis = visible();
    var allOpen = vis.length > 0 && vis.every(function (m) { return m.classList.contains('is-open'); });
    expandAll.textContent = allOpen ? 'Tout replier' : 'Tout déplier';
  }
  if (expandAll) {
    expandAll.addEventListener('click', function () {
      var vis = visible();
      var open = !vis.every(function (m) { return m.classList.contains('is-open'); });
      vis.forEach(function (m) { setOpen(m, open); });
      syncExpandLabel();
    });
  }
  root.addEventListener('click', function (e) { if (e.target.closest('.aksq__fold')) syncExpandLabel(); });

  function apply() {
    var text = (q && q.value || '').trim().toLowerCase();
    var r = role ? role.value : '';
    var a = app ? app.value : '';
    var n = 0;
    members.forEach(function (m) {
      var ok = (!text || m._name.indexOf(text) >= 0) && (!r || m._roles.indexOf(r) >= 0) && (!a || m._apps.indexOf(a) >= 0);
      m.hidden = !ok;
      if (ok) n++;
    });
    if (count) count.textContent = n + ' membre(s)';
    if (none) none.hidden = n > 0;
    syncExpandLabel();
  }
  [q, role, app].forEach(function (el) { if (el) el.addEventListener(el === q ? 'input' : 'change', apply); });

  function applyDurations(on) {
    root.classList.toggle('is-durations', on);
    if (dur) dur.checked = on;
  }
  var stored = false;
  try { stored = window.localStorage.getItem(KEY) === '1'; } catch (e) {}
  applyDurations(stored);
  if (dur) {
    dur.addEventListener('change', function () {
      applyDurations(dur.checked);
      try { window.localStorage.setItem(KEY, dur.checked ? '1' : '0'); } catch (e) {}
    });
  }
})();
