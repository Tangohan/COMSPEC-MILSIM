(() => {
    const root = document.querySelector('[data-platform-dashboard]');
    if (!root) return;

    const tools = root.querySelector('#ph-tools-dialog');
    const filter = tools.querySelector('[data-dashboard-tool-filter]');
    const status = root.querySelector('[data-dashboard-status]');
    const search = root.querySelector('#ph-search-dialog');
    let pendingSearch;
    let pendingPeriod;
    const normalize = (text) => text.toLocaleLowerCase('fr').normalize('NFD').replace(/\p{M}/gu, '');

    async function readFragment(address, selector, signal) {
        const response = await fetch(address, { signal });
        if (!response.ok) throw new Error('Chargement impossible');
        const document = new DOMParser().parseFromString(await response.text(), 'text/html');
        const section = document.querySelector(selector);
        if (!section) throw new Error('Réponse inattendue');
        return section;
    }

    async function loadJournal(dialog) {
        const journal = dialog.querySelector('[data-dashboard-journal]');
        if (!journal || journal.getAttribute('aria-busy') === 'true') return;
        const fallback = dialog.querySelector('[data-dashboard-journal-fallback]');
        fallback.hidden = false;
        journal.textContent = 'Chargement du journal…';
        journal.setAttribute('aria-busy', 'true');
        try {
            const section = await readFragment(journal.dataset.dashboardJournal, 'section[aria-labelledby="recent-activity-heading"]');
            journal.replaceChildren(section);
            fallback.hidden = true;
        } catch {
            journal.textContent = 'Aperçu du journal momentanément indisponible.';
        } finally {
            journal.removeAttribute('aria-busy');
        }
    }

    async function loadAppeals(dialog) {
        const output = dialog.querySelector('[data-dashboard-appeals]');
        if (output.getAttribute('aria-busy') === 'true') return;
        output.textContent = 'Chargement des contestations…';
        output.setAttribute('aria-busy', 'true');
        try {
            output.replaceChildren(await readFragment(output.dataset.dashboardAppeals, '[data-dashboard-appeals-results]'));
        } catch {
            output.textContent = 'Aperçu des contestations momentanément indisponible.';
        } finally {
            output.removeAttribute('aria-busy');
        }
    }

    search?.addEventListener('close', () => {
        if (!search.open) pendingSearch?.abort();
    });
    search?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            search.close();
        }
    });
    root.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!form.matches('[data-dashboard-search]') || typeof search?.showModal !== 'function') return;
        event.preventDefault();
        pendingSearch?.abort();
        const request = new AbortController();
        pendingSearch = request;
        const query = new FormData(form).get('q').trim();
        const address = new URL(form.action);
        address.searchParams.set('q', query);
        search.querySelector('[data-dashboard-search-fallback]').href = address.href;
        search.querySelector('input[name="q"]').value = query;
        const output = search.querySelector('[data-dashboard-search-output]');
        output.textContent = 'Recherche en cours…';
        output.setAttribute('aria-busy', 'true');
        if (!search.open) search.showModal();

        address.searchParams.set('fragment', 'resultats');
        try {
            const fragment = await readFragment(address, '[data-dashboard-search-results]', request.signal);
            if (!request.signal.aborted) output.replaceChildren(fragment);
        } catch {
            if (!request.signal.aborted) output.textContent = 'La recherche rapide est indisponible. Essayez la recherche complète.';
        } finally {
            if (pendingSearch === request) output.removeAttribute('aria-busy');
        }
    });

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
            if (typeof dialog?.showModal !== 'function' || event.button !== 0
                || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            dialog.showModal();
            if (dialog.id === 'ph-journal-dialog') await loadJournal(dialog);
            if (dialog.id === 'ph-appeals-dialog') await loadAppeals(dialog);
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
            const replacement = await readFragment(address, '[data-dashboard-activity]', request.signal);
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
