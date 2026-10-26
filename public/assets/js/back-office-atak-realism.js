/* Back-office — Configuration réalisme ATAK : onglets, valeurs des curseurs, dépendances, défaut, profils, modifications. */
(() => {
  'use strict';
  const root = document.getElementById('bo-rl');
  if (!root) return;
  const $ = (s, el = root) => el.querySelector(s);
  const $$ = (s, el = root) => [...el.querySelectorAll(s)];
  const form = $('#bo-rl-form');
  let data = { profiles: {} };
  try { data = JSON.parse(document.getElementById('bo-rl-data').textContent || '{}'); } catch (e) { /* rien */ }

  // ------------------------------------------------------------ onglets
  const panels = $$('[data-rl-panel]');
  const tabs = $$('[data-rl-tab]');
  const tabInput = $('[data-rl-current-tab]');
  function show(id, push) {
    if (!panels.some(p => p.id === id)) id = panels[0] ? panels[0].id : '';
    panels.forEach(p => { p.hidden = p.id !== id; });
    tabs.forEach(a => a.classList.toggle('is-active', a.dataset.rlTab === id));
    if (tabInput) tabInput.value = id === 'historique' ? '' : id;
    if (push) history.replaceState(null, '', '#' + id);
  }
  root.classList.add('is-tabbed');
  show(location.hash.slice(1), false);
  window.addEventListener('hashchange', () => show(location.hash.slice(1), false));
  root.addEventListener('click', e => {
    const a = e.target.closest('[data-rl-tab]');
    if (!a) return;
    e.preventDefault();
    show(a.dataset.rlTab, true);
  });

  // ------------------------------------------------------------ lecture / écriture d'un réglage
  const rows = $$('[data-rl-param]');
  const byKey = Object.fromEntries(rows.map(r => [r.dataset.rlParam, r]));
  function read(row) {
    const type = row.dataset.rlType;
    if (type === 'toggle') return $('input[type=checkbox]', row).checked;
    if (type === 'multi-select') return $$('input[type=checkbox]:checked', row).map(i => i.value).sort();
    if (type === 'dropdown') { const r = $('input[type=radio]:checked', row); return r ? r.value : ($('select', row) || {}).value; }
    const input = $('input:not([type=hidden]), select', row);
    return type === 'slider' || type === 'number' ? Number(input.value) : input.value;
  }
  function write(row, v) {
    const type = row.dataset.rlType;
    if (type === 'toggle') { $('input[type=checkbox]', row).checked = !!v; }
    else if (type === 'multi-select') { const set = new Set((v || []).map(String)); $$('input[type=checkbox]', row).forEach(c => { c.checked = set.has(c.value); }); }
    else if (type === 'dropdown' && $('input[type=radio]', row)) { $$('input[type=radio]', row).forEach(r => { r.checked = r.value === String(v); }); }
    else { const input = $('input:not([type=hidden]), select', row); input.value = v; }
    refresh(row);
  }
  const defaultOf = row => { try { return JSON.parse(row.dataset.rlDefault); } catch (e) { return null; } };
  const same = (a, b) => JSON.stringify(Array.isArray(a) ? [...a].sort() : a) === JSON.stringify(Array.isArray(b) ? [...b].sort() : b)
    || (typeof a === 'number' && Number(b) === a);
  const initial = new Map(rows.map(r => [r, JSON.stringify(read(r))]));

  function fmt(input) {
    const v = Number(input.value);
    const unit = input.dataset.rlUnit || '';
    if (input.hasAttribute('data-rl-pct')) return Math.round(v * 100) + ' %';
    const s = (Math.round(v * 100) / 100).toLocaleString('fr-FR');
    return unit ? s + ' ' + unit : s;
  }
  function refresh(row) {
    const range = $('input[type=range]', row);
    if (range) { const out = $('output', row); if (out) out.textContent = fmt(range); range.style.setProperty('--p', ((range.value - range.min) / (range.max - range.min) * 100) + '%'); }
    const color = $('input[type=color]', row);
    if (color) { $('[data-rl-hex]', row).textContent = color.value.toUpperCase(); $('[data-rl-sample]', row).style.setProperty('--c', color.value); }
    const reset = $('[data-rl-reset]', row);
    if (reset) reset.hidden = same(read(row), defaultOf(row));
    row.classList.toggle('is-changed', JSON.stringify(read(row)) !== initial.get(row));
  }
  // Un réglage dépendant est grisé quand son interrupteur parent est coupé.
  function dependencies() {
    rows.forEach(row => {
      const dep = row.dataset.rlDepends;
      if (!dep || !byKey[dep]) return;
      const off = byKey[dep].dataset.rlType === 'toggle' && !read(byKey[dep]);
      row.classList.toggle('is-off', off);
      $$('input:not([type=hidden]), select', row).forEach(i => { i.disabled = off; });
    });
  }
  // Champs désactivés : ils ne partent pas avec le formulaire ; on les réactive juste avant l'envoi
  // pour ne pas perdre leur valeur (le serveur les garderait de toute façon).
  form.addEventListener('submit', () => { $$('input:disabled, select:disabled', form).forEach(i => { i.disabled = false; }); dirty = false; });

  let dirty = false;
  function countDirty() {
    const n = rows.filter(r => r.classList.contains('is-changed')).length;
    dirty = n > 0;
    const el = $('[data-rl-dirty]');
    el.textContent = n ? n + ' réglage' + (n > 1 ? 's' : '') + ' modifié' + (n > 1 ? 's' : '') + ' — pensez à enregistrer' : 'Aucune modification';
    el.classList.toggle('is-dirty', n > 0);
    tabs.forEach(a => {
      const sec = document.getElementById(a.dataset.rlTab);
      a.classList.toggle('has-changes', !!sec && !!sec.querySelector('.bo-rl-param.is-changed'));
    });
  }
  form.addEventListener('input', e => { const row = e.target.closest('[data-rl-param]'); if (row) refresh(row); dependencies(); countDirty(); });
  form.addEventListener('change', e => { const row = e.target.closest('[data-rl-param]'); if (row) refresh(row); dependencies(); countDirty(); });
  form.addEventListener('click', e => {
    const b = e.target.closest('[data-rl-reset]');
    if (!b) return;
    const row = b.closest('[data-rl-param]');
    write(row, defaultOf(row)); dependencies(); countDirty();
  });
  rows.forEach(refresh); dependencies(); countDirty();

  // ------------------------------------------------------------ profils
  root.addEventListener('click', e => {
    const b = e.target.closest('[data-rl-profile]');
    if (!b) return;
    const p = (data.profiles || {})[b.dataset.rlProfile];
    if (!p) return;
    const overrides = p.overrides || {};
    let n = 0;
    Object.keys(overrides).forEach(d => Object.keys(overrides[d]).forEach(k => {
      const row = byKey[d + '.' + k];
      if (row) { write(row, overrides[d][k]); n++; }
    }));
    dependencies(); countDirty();
    const first = rows.find(r => r.classList.contains('is-changed'));
    if (first) show(first.closest('[data-rl-panel]').id, true);
    b.textContent = n + ' réglages remplis — à enregistrer';
    setTimeout(() => { b.textContent = 'Appliquer ce profil'; }, 3500);
  });

  // ------------------------------------------------------------ masquer les réglages sans effet
  const hide = $('[data-rl-hide-dead]');
  if (hide) {
    try { hide.checked = localStorage.getItem('bo-rl-hide-dead') === '1'; } catch (e) { /* rien */ }
    const apply = () => { root.classList.toggle('hide-dead', hide.checked); try { localStorage.setItem('bo-rl-hide-dead', hide.checked ? '1' : '0'); } catch (e) { /* rien */ } };
    hide.addEventListener('change', apply); apply();
  }

  // ------------------------------------------------------------ confirmations, sortie
  root.addEventListener('submit', e => { const f = e.target.closest('form[data-rl-confirm]'); if (f && !window.confirm(f.dataset.rlConfirm)) e.preventDefault(); });
  window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
