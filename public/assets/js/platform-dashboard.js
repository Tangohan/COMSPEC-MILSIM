(() => {
    const root = document.querySelector('[data-platform-dashboard]');
    if (!root) return;

    const tools = root.querySelector('#ph-tools-dialog');
    const filter = tools.querySelector('[data-dashboard-tool-filter]');
    const status = root.querySelector('[data-dashboard-status]');
    let pendingPeriod;
    const normalize = (text) => text.toLocaleLowerCase('fr').normalize('NFD').replace(/\p{M}/gu, '');

    async function loadJournal(dialog) {
        const journal = dialog.querySelector('[data-dashboard-journal]');
        if (!journal || journal.getAttribute('aria-busy') === 'true') return;
        const fallback = dialog.querySelector('[data-dashboard-journal-fallback]');
        fallback.hidden = false;
        journal.textContent = 'Chargement du journal…';
        journal.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(journal.dataset.dashboardJournal);
            if (!response.ok) throw new Error('Journal indisponible');
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            const section = html.querySelector('section[aria-labelledby="recent-activity-heading"]');
            if (!section) throw new Error('Réponse inattendue');
            journal.replaceChildren(section);
            fallback.hidden = true;
        } catch {
            journal.textContent = 'Aperçu du journal momentanément indisponible.';
        } finally {
            journal.removeAttribute('aria-busy');
        }
    }

    filter.addEventListener('input', () => {
        const query = normalize(filter.value.trim());
        let matches = 0;
        for (const group of tools.querySelectorAll('[data-dashboard-tool-group]')) {
            let visible = false;
            for (const link of group.querySelectorAll('[data-dashboard-tool]')) {
                link.hidden = !normalize(link.textContent).includes(query);
                if (!link.hidden) {
                    visible = true;
                    matches += 1;
                }
            }
            group.hidden = !visible;
        }
        tools.querySelector('[data-dashboard-tool-empty]').hidden = matches > 0;
    });

    filter.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            tools.close();
            return;
        }
        if (event.key !== 'Enter') return;
        tools.querySelector('[data-dashboard-tool]:not([hidden])')?.focus();
    });

    document.addEventListener('keydown', (event) => {
        if (!(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== 'k' || root.querySelector('dialog[open]')) return;
        event.preventDefault();
        tools.showModal();
    });

    root.addEventListener('click', async (event) => {
        const opener = event.target.closest('[data-dashboard-open]');
        if (opener) {
            const dialog = document.getElementById(opener.dataset.dashboardOpen);
            dialog?.showModal();
            if (dialog?.id === 'ph-journal-dialog') await loadJournal(dialog);
            return;
        }

        const link = event.target.closest('[data-dashboard-periods] a');
        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        pendingPeriod?.abort();
        const request = new AbortController();
        pendingPeriod = request;
        const activity = link.closest('[data-dashboard-activity]');
        if (link.hasAttribute('aria-current')) {
            activity.removeAttribute('aria-busy');
            status.textContent = '';
            return;
        }

        activity.setAttribute('aria-busy', 'true');
        status.textContent = 'Chargement des statistiques…';
        const address = new URL(link.href);
        address.searchParams.set('fragment', 'activity');
        try {
            const response = await fetch(address, { signal: request.signal });
            if (!response.ok) throw new Error('Statistiques indisponibles');
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            const replacement = html.querySelector('[data-dashboard-activity]');
            if (!replacement) throw new Error('Réponse inattendue');
            if (request.signal.aborted) return;
            const restoreFocus = activity.contains(document.activeElement);
            activity.replaceWith(replacement);
            const visibleUrl = new URL(link.href);
            visibleUrl.hash = location.hash;
            history.replaceState(history.state, '', visibleUrl);
            if (restoreFocus) replacement.querySelector('[aria-current="page"]')?.focus({ preventScroll: true });
            status.textContent = 'Statistiques actualisées.';
        } catch (error) {
            if (!request.signal.aborted) location.assign(link.href);
        }
    });
})();
