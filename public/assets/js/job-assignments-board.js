/* Effectifs › Emplois & fonctions › « Qui tient quel emploi » : filtres, sélection groupée, fenêtre d’attribution. */
(() => {
    'use strict';
    const board = document.querySelector('[data-ja-board]');
    if (!board || board.dataset.jaReady) return;
    board.dataset.jaReady = '1';

    const fold = value => String(value || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLocaleLowerCase().trim();
    let jobs = [];
    try { jobs = JSON.parse(board.querySelector('[data-ja-jobs]')?.textContent || '[]'); } catch (e) { jobs = []; }
    const jobName = id => (jobs.find(job => String(job.id) === String(id)) || {}).name || '';

    // Filtres : un choix dans une liste recharge la page ; la recherche part avec Entrée.
    const filters = board.querySelector('[data-ja-filters]');
    filters?.addEventListener('change', event => {
        if (event.target.matches('[data-ja-auto]')) filters.requestSubmit();
    });

    board.querySelector('[data-ja-print]')?.addEventListener('click', () => window.print());

    // Confirmation des retraits (texte traduit par i18n_dom via data-confirm).
    board.addEventListener('submit', event => {
        const form = event.target;
        const message = event.submitter?.dataset.confirm || form.dataset.confirm;
        if (form.matches('[data-ja-bulk]') && !form.querySelector('[data-ja-member]:checked')) {
            event.preventDefault();
            window.alert('Sélectionnez au moins un membre.');
            return;
        }
        if (message && !window.confirm(message)) event.preventDefault();
    });

    // Sélection groupée (vue par membre).
    const bulk = board.querySelector('[data-ja-bulk]');
    const refreshSelection = () => {
        if (!bulk) return;
        const boxes = Array.from(bulk.querySelectorAll('[data-ja-member]'));
        const checked = boxes.filter(box => box.checked).length;
        const counter = bulk.querySelector('[data-ja-selected]');
        if (counter) counter.textContent = String(checked);
        bulk.querySelector('[data-ja-bulkbar]')?.classList.toggle('is-empty', checked === 0);
        const all = bulk.querySelector('[data-ja-select-all]');
        if (all) {
            all.checked = checked > 0 && checked === boxes.length;
            all.indeterminate = checked > 0 && checked < boxes.length;
        }
    };
    bulk?.addEventListener('change', event => {
        if (event.target.matches('[data-ja-select-all]')) {
            bulk.querySelectorAll('[data-ja-member]').forEach(box => { box.checked = event.target.checked; });
        }
        refreshSelection();
    });
    refreshSelection();

    // Fenêtre « Ajouter des titulaires » / « Remplacer le titulaire ».
    const dialog = board.querySelector('[data-ja-dialog]');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const search = dialog.querySelector('[data-ja-dialog-search]');
    const items = Array.from(dialog.querySelectorAll('[data-ja-pick]'));
    let opener = null;

    const filterPicker = () => {
        const query = fold(search.value);
        let visible = 0;
        items.forEach(item => {
            item.hidden = query !== '' && !item.dataset.search.includes(query);
            if (!item.hidden) visible++;
        });
        dialog.querySelector('[data-ja-picker-empty]').hidden = visible > 0;
    };
    search.addEventListener('input', filterPicker);

    const open = (mode, jobId, fromId, trigger) => {
        opener = trigger;
        dialog.querySelector('[data-ja-dialog-action]').value = mode;
        dialog.querySelector('[data-ja-dialog-job]').value = jobId;
        dialog.querySelector('[data-ja-dialog-from]').value = mode === 'remplacer' ? fromId : '';
        dialog.querySelector('[data-ja-dialog-name]').textContent = jobName(jobId);
        dialog.querySelectorAll('[data-ja-mode]').forEach(node => { node.hidden = node.dataset.jaMode !== mode; });
        items.forEach(item => {
            const input = item.querySelector('input');
            const held = (item.dataset.jobs || '').split(',').includes(String(jobId));
            input.type = mode === 'remplacer' ? 'radio' : 'checkbox';
            input.checked = false;
            input.disabled = held || (mode === 'remplacer' && input.value === String(fromId));
            item.classList.toggle('is-held', held);
        });
        search.value = '';
        filterPicker();
        dialog.showModal();
        search.focus();
    };

    board.addEventListener('click', event => {
        const trigger = event.target.closest('[data-ja-open]');
        if (trigger) {
            event.preventDefault();
            open(trigger.dataset.jaOpen, trigger.dataset.job, trigger.dataset.from || '', trigger);
            return;
        }
        if (event.target.closest('[data-ja-dialog-close]')) dialog.close();
    });
    dialog.addEventListener('close', () => { opener?.focus(); });
    dialog.querySelector('form').addEventListener('submit', event => {
        if (!dialog.querySelector('[data-ja-pick] input:checked')) {
            event.preventDefault();
            window.alert('Sélectionnez au moins un membre.');
        }
    });
})();
