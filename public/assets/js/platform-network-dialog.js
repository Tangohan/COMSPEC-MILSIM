(() => {
    const dialog = document.querySelector('[data-network-dialog]');
    if (!dialog?.showModal) return;
    const content = dialog.querySelector('[data-network-content]');
    const status = dialog.querySelector('[data-network-status]');
    let pending;

    async function load(url) {
        pending?.abort();
        const request = new AbortController();
        pending = request;
        const fallback = url.href;
        url.searchParams.set('fragment', 'resultats');
        status.textContent = 'Chargement des connexions…';
        content.replaceChildren();
        if (!dialog.open) dialog.showModal();
        try {
            const response = await fetch(url, { signal: request.signal });
            if (!response.ok) throw new Error('Journal indisponible');
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            const results = html.querySelector('[data-network-results]');
            if (!results) throw new Error('Résultats absents');
            if (request.signal.aborted) return;
            content.replaceChildren(document.importNode(results, true));
            status.textContent = '';
            content.querySelector('input')?.focus();
        } catch {
            if (request.signal.aborted) return;
            status.textContent = 'Impossible d’ouvrir les connexions.';
            const link = document.createElement('a');
            link.className = 'pu-link';
            link.href = fallback;
            link.textContent = 'Ouvrir les résultats dans une page';
            content.replaceChildren(link);
        }
    }

    document.addEventListener('click', event => {
        const link = event.target.closest('a[data-network-open], a[data-network-view]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        load(new URL(link.href));
    });
    dialog.addEventListener('submit', event => {
        if (!event.target.matches('[data-network-search]')) return;
        event.preventDefault();
        const url = new URL(event.target.action);
        url.search = new URLSearchParams(new FormData(event.target));
        load(url);
    });
    dialog.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            event.preventDefault();
            dialog.close();
        }
    });
    dialog.addEventListener('close', () => {
        pending?.abort();
        content.replaceChildren();
        status.textContent = '';
    });
})();
