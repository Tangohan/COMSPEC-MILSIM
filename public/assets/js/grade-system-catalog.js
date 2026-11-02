/*
 * Fenêtre « Systèmes de grades » (views/partials/grade_system_catalog.php) : choix du pays et de l’armée, recherche
 * plein texte sur tous les grades, comparaison avec le système actuel, adoption par la communauté (référentiel des
 * grades : confirmation puis envoi du formulaire) ou choix dans l’assistant de création (bouton radio de l’étape Grades).
 * Délégation sur document : la fenêtre est remplacée quand le référentiel se recharge par fragment.
 */
(() => {
  const dialog = () => document.querySelector('[data-grade-catalog]');
  const label = (root, key) => root.querySelector(`[data-grade-catalog-label="${key}"]`)?.textContent.trim() || '';
  const mobile = () => window.matchMedia('(max-width: 760px)').matches;

  function select(root, code, focus) {
    const frame = root.querySelector('.jobcat__frame');
    let found = false;
    root.querySelectorAll('[data-grade-catalog-panel]').forEach(panel => {
      const on = panel.dataset.gradeCatalogPanel === code;
      panel.hidden = !on;
      found = found || on;
    });
    root.querySelectorAll('[data-grade-catalog-system]').forEach(button => {
      button.setAttribute('aria-current', button.dataset.gradeCatalogSystem === code ? 'true' : 'false');
      button.classList.toggle('is-selected', button.dataset.gradeCatalogSystem === code);
    });
    const placeholder = root.querySelector('[data-grade-catalog-placeholder]');
    if (placeholder) placeholder.hidden = found;
    root.dataset.selected = found ? code : '';
    if (frame) frame.classList.toggle('is-detail', found && focus && mobile());
    if (found && focus) root.querySelector(`[data-grade-catalog-panel="${code}"]`)?.focus({preventScroll: true});
  }

  function search(root) {
    const query = (root.querySelector('[data-grade-catalog-search]')?.value || '').trim().toLowerCase();
    let total = 0;
    let firstHit = '';
    root.querySelectorAll('[data-grade-catalog-panel]').forEach(panel => {
      const code = panel.dataset.gradeCatalogPanel;
      let hits = 0;
      panel.querySelectorAll('[data-grade-catalog-row]').forEach(row => {
        const match = query === '' || row.dataset.search.includes(query);
        row.hidden = !match;
        if (match) hits++;
      });
      total += hits;
      const button = root.querySelector(`[data-grade-catalog-system="${code}"]`);
      if (button) {
        button.closest('li').hidden = query !== '' && hits === 0;
        const badge = button.querySelector('[data-grade-catalog-hits]');
        if (badge) { badge.hidden = query === ''; badge.textContent = query === '' ? '' : ` · ${hits} ${label(root, 'found')}`; }
      }
      if (query !== '' && hits > 0 && !firstHit) firstHit = code;
    });
    root.querySelectorAll('[data-grade-catalog-country]').forEach(group => {
      group.hidden = query !== '' && ![...group.querySelectorAll('[data-grade-catalog-system]')].some(b => !b.closest('li').hidden);
    });
    const count = root.querySelector('[data-grade-catalog-count]');
    if (count) {
      if (!count.dataset.initial) count.dataset.initial = count.textContent;
      count.textContent = query === '' ? count.dataset.initial : (total > 0 ? `${total} ${label(root, 'found')}` : label(root, 'none'));
    }
    if (query !== '') {
      const selected = root.dataset.selected || '';
      const selectedHits = selected ? root.querySelectorAll(`[data-grade-catalog-panel="${selected}"] [data-grade-catalog-row]:not([hidden])`).length : 0;
      if (selectedHits === 0 && firstHit) select(root, firstHit, false);
    }
  }

  function open(code) {
    const root = dialog();
    if (!root || typeof root.showModal !== 'function') return;
    const start = code || root.dataset.selected || root.dataset.current || root.querySelector('[data-grade-catalog-system]')?.dataset.gradeCatalogSystem || '';
    select(root, start, false);
    root.querySelector('.jobcat__frame')?.classList.remove('is-detail');
    if (!root.open) root.showModal();
    root.querySelector(`[data-grade-catalog-system="${start}"]`)?.scrollIntoView({block: 'nearest'});
    root.querySelector('[data-grade-catalog-search]')?.focus();
  }

  document.addEventListener('click', event => {
    const opener = event.target.closest('[data-grade-catalog-open]');
    if (opener) { event.preventDefault(); open(opener.dataset.gradeCatalogOpen || ''); return; }
    const root = event.target.closest('[data-grade-catalog]');
    if (!root) return;
    if (event.target.closest('[data-grade-catalog-close]')) { root.close(); return; }
    const system = event.target.closest('[data-grade-catalog-system]');
    if (system) { select(root, system.dataset.gradeCatalogSystem, true); return; }
    if (event.target.closest('[data-grade-catalog-back]')) { root.querySelector('.jobcat__frame')?.classList.remove('is-detail'); return; }
    const pick = event.target.closest('[data-grade-catalog-pick]');
    if (pick) {
      const radio = document.querySelector(`input[name="wizard_grade_system_code"][value="${pick.dataset.gradeCatalogPick}"]`);
      if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change', {bubbles: true})); }
      root.close();
      return;
    }
    const use = event.target.closest('[data-grade-catalog-use]');
    if (use) {
      const form = use.closest('form');
      const confirm = root.querySelector('[data-grade-catalog-confirm]');
      if (!form) return;
      if (!confirm || typeof confirm.showModal !== 'function') { form.requestSubmit(); return; }
      const summary = form.closest('[data-grade-catalog-panel]')?.querySelector('[data-grade-catalog-summary]');
      confirm.querySelector('[data-grade-catalog-confirm-summary]').replaceChildren(...(summary ? [...summary.cloneNode(true).children] : []));
      confirm.returnValue = '';
      confirm.onclose = () => {
        if (confirm.returnValue !== 'use') return;
        root.close();
        form.requestSubmit();
      };
      confirm.showModal();
    }
  });
  // Assistant de création : la carte « Autres pays et armées » affiche le système choisi dans la fenêtre.
  function syncWizardCard() {
    const card = document.querySelector('[data-grade-catalog-card]');
    if (!card) return;
    const checked = document.querySelector('input[name="wizard_grade_system_code"]:checked');
    const inCatalog = !!checked?.closest('[data-grade-catalog-options]');
    const title = inCatalog ? checked.closest('label')?.querySelector('.cc-choice__title')?.textContent.trim() || '' : '';
    card.classList.toggle('is-picked', inCatalog);
    card.setAttribute('aria-pressed', inCatalog ? 'true' : 'false');
    const chosen = card.querySelector('[data-grade-catalog-chosen]');
    const placeholder = card.querySelector('[data-grade-catalog-placeholder-title]');
    if (chosen) { chosen.textContent = title; chosen.hidden = !inCatalog; }
    if (placeholder) placeholder.hidden = inCatalog;
    if (inCatalog) card.dataset.gradeCatalogOpen = checked.value;
  }
  document.addEventListener('change', event => {
    if (event.target.matches('input[name="wizard_grade_system_code"]')) syncWizardCard();
  });
  document.addEventListener('change', event => {
    const compare = event.target.closest('[data-grade-catalog-compare]');
    if (!compare) return;
    compare.closest('[data-grade-catalog-panel]')?.querySelectorAll('[data-grade-catalog-compare-col]').forEach(cell => { cell.hidden = !compare.checked; });
  });
  document.addEventListener('input', event => {
    if (!event.target.matches('[data-grade-catalog-search]')) return;
    const root = event.target.closest('[data-grade-catalog]');
    if (root) search(root);
  });
  syncWizardCard();
})();
