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

  // Mission choisie : préremplir l'opération et le titre s'ils sont vides.
  const mission = form.querySelector('[data-aar-mission]');
  const operation = form.querySelector('[data-aar-operation]');
  if (mission && operation) {
    mission.addEventListener('change', () => {
      const t = (mission.selectedOptions[0] && mission.selectedOptions[0].dataset.title) || '';
      if (!t) return;
      if (operation.value.trim() === '') operation.value = t;
      const title = form.elements.namedItem('title');
      if (title && title.value.trim() === '') title.value = 'Compte rendu — ' + t;
    });
  }

  // Tableau des actions.
  const box = form.querySelector('[data-aar-actions]');
  if (box) {
    const rows = box.querySelector('[data-aar-rows]');
    const tpl = box.querySelector('template[data-aar-row-tpl]');
    let seq = rows.children.length + 1000;
    box.addEventListener('click', e => {
      if (e.target.closest('[data-aar-add]')) {
        rows.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__i__/g, String(seq++)));
        rows.lastElementChild.querySelector('input').focus();
      }
      const del = e.target.closest('[data-aar-del]');
      if (del) del.closest('tr').remove();
    });
    if (rows.children.length === 0 && !form.action.match(/\/\d+$/)) {
      rows.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__i__/g, String(seq++)));
    }
  }
})();
