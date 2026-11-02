/*
 * Page Catalogue de référence des qualifications (back-office) : filtres appliqués dès qu'ils changent (adresse de la
 * page, donc partageable), sélection à importer, et fiche détaillée d'une qualification dans une fenêtre
 * (assets/js/qualification-sheet.js) : ?fiche=<id> l'ouvre au chargement, prérequis et qualifications reliées s'ouvrent
 * dans la même fenêtre, Échap la ferme. Sans JavaScript, les filtres et l'import restent de simples formulaires.
 */
(() => {
    'use strict';
    const page = document.querySelector('[data-rqc]');
    if (!page) return;
    const dialog = page.querySelector('[data-rqc-dialog]');
    const sheet = page.querySelector('[data-rqc-sheet]');
    let opener = null;

    // Filtres : envoi immédiat (la recherche, à la validation ou après une courte pause).
    const filters = page.querySelector('[data-rqc-filters]');
    let timer = 0;
    filters?.addEventListener('change', event => { if (!event.target.matches('input[type=search]')) filters.requestSubmit(); });
    filters?.addEventListener('input', event => {
        if (!event.target.matches('input[type=search]')) return;
        window.clearTimeout(timer);
        timer = window.setTimeout(() => filters.requestSubmit(), 700);
    });

    // Sélection : compteur et bouton « Importer la sélection ».
    const importForm = page.querySelector('[data-rqc-import]');
    const boxes = () => Array.from(importForm?.querySelectorAll('input[name="ids[]"]:not(:disabled)') || []);
    function refresh() {
        const n = boxes().filter(box => box.checked).length;
        const holder = page.querySelector('[data-rqc-selected]');
        if (holder) { holder.hidden = n === 0; holder.textContent = `${n} cochée(s)`; }
        const submit = page.querySelector('[data-rqc-import-selection]');
        if (submit) submit.disabled = n === 0;
        const all = page.querySelector('[data-rqc-select-all]');
        if (all) all.textContent = n > 0 && n === boxes().length ? 'Tout décocher' : 'Tout cocher';
    }
    importForm?.addEventListener('change', event => { if (event.target.matches('input[name="ids[]"]')) refresh(); });
    page.querySelector('[data-rqc-select-all]')?.addEventListener('click', () => {
        const list = boxes();
        const on = !list.every(box => box.checked);
        list.forEach(box => { box.checked = on; });
        refresh();
    });
    refresh();

    // Fiche détaillée.
    function setAddress(id) {
        const url = new URL(window.location.href);
        if (id) url.searchParams.set('fiche', id); else url.searchParams.delete('fiche');
        window.history.replaceState(null, '', url.toString());
    }

    function show(id) {
        if (!dialog || !sheet || !window.AthenaQualificationSheet) return false;
        if (!dialog.open) {
            opener = document.activeElement;
            dialog.showModal();
        }
        setAddress(id);
        window.AthenaQualificationSheet.open(sheet, id, {
            url: page.dataset.qualificationUrl,
            csrf: page.dataset.csrf || '',
            onQualification: show,
            onImported: q => {
                // La ligne de la page passe à « Déjà dans votre référentiel ».
                const row = page.querySelector('[data-rqc-row="' + CSS.escape(q.id) + '"]');
                if (!row) return;
                row.classList.add('is-done');
                const box = row.querySelector('input[name="ids[]"]');
                if (box) { box.checked = false; box.disabled = true; }
                row.querySelector('button[name="one"]')?.remove();
                refresh();
            },
        }).then(() => { sheet.scrollTop = 0; sheet.focus({preventScroll: true}); });
        return true;
    }

    page.addEventListener('click', event => {
        const link = event.target.closest('[data-rqc-open]');
        if (link && show(link.dataset.rqcOpen)) { event.preventDefault(); return; }
        if (event.target.closest('[data-rqc-close]') || event.target === dialog) dialog.close();
    });
    dialog?.addEventListener('close', () => {
        setAddress('');
        if (opener && opener.isConnected) opener.focus();
        opener = null;
    });

    if (page.dataset.detail) show(page.dataset.detail);
})();
