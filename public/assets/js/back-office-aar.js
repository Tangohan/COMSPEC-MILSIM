/* Back-office — Comptes rendus : lignes cliquables, confirmations, formulaire (modèle, mission, actions). */
(() => {
  'use strict';
  if (window.__boAar) return;
  window.__boAar = true;

  // Lignes de tableau cliquables (hors liens, boutons et formulaires).
  document.addEventListener('click', e => {
    const tr = e.target.closest('tr[data-href]');
    if (!tr || e.target.closest('a, button, input, select, form, label')) return;
    if (e.metaKey || e.ctrlKey) window.open(tr.dataset.href, '_blank');
    else window.location.href = tr.dataset.href;
  });

  // Confirmations avant suppression / archivage.
  document.addEventListener('submit', e => {
    const f = e.target.closest('form[data-aar-confirm]');
    if (f && !window.confirm(f.dataset.aarConfirm)) e.preventDefault();
  });

  const form = document.getElementById('bo-aar-form');
  if (!form) return;

  // Formulaire standard ou questionnaire : un seul bloc actif (les autres sont désactivés, donc ni validés ni envoyés).
  const blocks = [...form.querySelectorAll('[data-aar-block]')];
  const radios = [...form.querySelectorAll('input[data-aar-template]')];
  function pick() {
    const sel = (radios.find(r => r.checked) || { value: '0' }).value;
    blocks.forEach(b => {
      const on = b.dataset.aarBlock === sel;
      b.hidden = !on;
      b.disabled = !on;
    });
  }
  radios.forEach(r => r.addEventListener('change', pick));
  if (radios.length) pick();

  const val = name => { const el = form.elements.namedItem(name); return el && !el.disabled ? String(el.value || '').trim() : ''; };
  const inEnabled = el => !el.closest('fieldset[disabled]');

  // Mission : cartes « en attente de compte rendu » + liste complète, carte récapitulative à droite.
  const mission = form.querySelector('[data-aar-mission]');
  const missionField = form.querySelector('[data-aar-mission-field]');
  const operation = form.querySelector('[data-aar-operation]');
  const card = form.querySelector('[data-aar-mission-card]');
  function prefill(opt) {
    const t = (opt && opt.dataset.title) || '';
    if (!t) return;
    if (operation && operation.value.trim() === '') operation.value = t;
    const title = form.elements.namedItem('title');
    if (title && title.value.trim() === '') title.value = 'Compte rendu — ' + t;
  }
  function showCard() {
    if (!card || !mission) return;
    const opt = mission.selectedOptions[0];
    card.hidden = !opt || !opt.value;
    if (card.hidden) return;
    card.querySelector('[data-aar-mc-title]').textContent = opt.dataset.title || opt.textContent;
    card.querySelector('[data-aar-mc-status]').textContent = opt.dataset.status || '—';
    card.querySelector('[data-aar-mc-start]').textContent = opt.dataset.start || '—';
    card.querySelector('[data-aar-mc-end]').textContent = opt.dataset.end || '—';
  }
  if (mission) {
    mission.addEventListener('change', () => { prefill(mission.selectedOptions[0]); showCard(); refresh(); });
    form.querySelectorAll('[data-aar-mission-pick]').forEach(r => r.addEventListener('change', () => {
      if (!r.checked) return;
      if (r.value) { mission.value = r.value; prefill(mission.selectedOptions[0]); }
      else if ([...form.querySelectorAll('[data-aar-mission-pick]')].some(x => x.value === mission.value)) mission.value = '';
      if (missionField) missionField.hidden = r.value !== '';
      showCard(); refresh();
    }));
  }

  // Listes de points : Entrée ajoute une ligne, Retour arrière sur une ligne vide la supprime.
  function addPoint(list, after) {
    const items = list.querySelector('[data-aar-list-items]');
    const tpl = list.querySelector('template[data-aar-list-tpl]');
    const li = document.createElement('div'); li.innerHTML = tpl.innerHTML.trim();
    const row = li.firstElementChild;
    if (after) after.after(row); else items.append(row);
    row.querySelector('input').focus();
    count(list);
  }
  function count(list) {
    const n = [...list.querySelectorAll('[data-aar-list-items] input')].filter(i => i.value.trim()).length;
    const c = list.querySelector('[data-aar-list-count]'); if (c) c.textContent = n ? '· ' + n : '';
  }
  form.querySelectorAll('[data-aar-list]').forEach(list => {
    count(list);
    list.addEventListener('click', e => {
      if (e.target.closest('[data-aar-list-add]')) addPoint(list);
      const del = e.target.closest('[data-aar-list-del]');
      if (del) {
        const items = list.querySelector('[data-aar-list-items]');
        const li = del.closest('li');
        if (items.children.length > 1) li.remove(); else li.querySelector('input').value = '';
        count(list); refresh();
      }
    });
    list.addEventListener('keydown', e => {
      const input = e.target.closest('[data-aar-list-items] input'); if (!input) return;
      const li = input.closest('li');
      if (e.key === 'Enter') { e.preventDefault(); if (input.value.trim()) addPoint(list, li); }
      if (e.key === 'Backspace' && input.value === '' && li.parentElement.children.length > 1) {
        e.preventDefault(); const prev = li.previousElementSibling || li.nextElementSibling; li.remove(); prev.querySelector('input').focus(); count(list); refresh();
      }
    });
    list.addEventListener('input', () => count(list));
  });

  // Trame de synthèse et compteur.
  const summary = form.querySelector('[data-aar-frame-target]');
  const counter = form.querySelector('[data-aar-counter]');
  const countWords = () => { if (counter && summary) { const n = summary.value.trim() ? summary.value.trim().split(/\s+/).length : 0; counter.textContent = n ? n + ' mot' + (n > 1 ? 's' : '') : ''; } };
  form.querySelectorAll('[data-aar-frame]').forEach(b => b.addEventListener('click', () => {
    if (!summary) return;
    if (summary.value.trim() !== '' && !window.confirm('Ajouter la trame à la suite du texte déjà saisi ?')) return;
    summary.value = (summary.value.trim() ? summary.value.trim() + '\n\n' : '') + b.dataset.aarFrame;
    summary.focus(); summary.setSelectionRange(summary.value.indexOf(':') + 2, summary.value.indexOf(':') + 2); countWords(); refresh();
  }));
  if (summary) summary.addEventListener('input', countWords);
  countWords();

  // Avancement (colonne de droite).
  const pct = form.querySelector('[data-aar-pct]');
  const bar = form.querySelector('[data-aar-bar]');
  function refresh() {
    if (!pct) return;
    const custom = radios.some(r => r.checked && r.value !== '0') || !form.querySelector('[data-aar-block="0"]');
    const filled = sel => [...form.querySelectorAll(sel)].filter(inEnabled).some(i => (i.type === 'radio' || i.type === 'checkbox') ? i.checked : i.value.trim() !== '');
    const state = {
      title: val('title') !== '',
      mission: !!(mission && mission.value),
      summary: custom ? filled('[name^="answers["]') : val('summary_text') !== '',
      points: custom ? filled('[name^="answers["]') : filled('input[name="strengths[]"], input[name="weaknesses[]"]'),
      lessons: custom ? true : val('lessons_learned') !== '' || val('conclusion_text') !== '',
      actions: [...form.querySelectorAll('[data-aar-act-label]')].some(i => i.value.trim() !== ''),
    };
    const rows = [...form.querySelectorAll('[data-aar-check]')];
    rows.forEach(li => {
      const k = li.dataset.aarCheck;
      li.hidden = custom && (k === 'lessons' || k === 'points');
      li.classList.toggle('is-done', !!state[k]);
      if (custom && k === 'summary') li.lastChild.textContent = 'Questionnaire rempli';
    });
    const vis = rows.filter(li => !li.hidden);
    const p = Math.round(100 * vis.filter(li => li.classList.contains('is-done')).length / Math.max(1, vis.length));
    pct.textContent = p; bar.style.width = p + '%';
  }
  form.addEventListener('input', refresh);
  form.addEventListener('change', refresh);
  showCard();

  // Actions : ajout, retrait, ligne close.
  const box = form.querySelector('[data-aar-actions]');
  if (box) {
    const rows = box.querySelector('[data-aar-rows]');
    const tpl = box.querySelector('template[data-aar-row-tpl]');
    let seq = rows.children.length + 1000;
    const add = () => { rows.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__i__/g, String(seq++))); return rows.lastElementChild; };
    box.addEventListener('click', e => {
      if (e.target.closest('[data-aar-add]')) add().querySelector('input').focus();
      const del = e.target.closest('[data-aar-del]');
      if (del) { del.closest('li').remove(); if (!rows.children.length) add(); refresh(); }
    });
    box.addEventListener('change', e => {
      const st = e.target.closest('[data-aar-act-state]');
      if (st) st.closest('li').classList.toggle('is-closed', st.value === 'closed');
    });
    if (rows.children.length === 0) add();
  }

  // Quitter la page avec une saisie non enregistrée.
  let dirty = false;
  form.addEventListener('input', () => { dirty = true; });
  form.addEventListener('submit', () => { dirty = false; });
  window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  refresh();
})();
