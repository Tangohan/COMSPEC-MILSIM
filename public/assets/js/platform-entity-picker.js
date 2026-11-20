(() => {
    for (const picker of document.querySelectorAll('[data-entity-picker]')) {
        const input = picker.querySelector('[data-entity-query]');
        const status = picker.querySelector('[data-entity-status]');
        const results = picker.querySelector('[data-entity-results]');
        const target = document.getElementById(picker.dataset.entityTarget);
        const fallback = target?.closest('[data-entity-fallback]');
        const account = picker.dataset.entityPicker === 'account';
        const dialog = picker.closest('dialog');
        let timer;
        let pending;
        picker.hidden = false;

        if (fallback) {
            target.type = 'hidden';
            fallback.hidden = true;
            input.required = true;
        }

        function cancelSearch() {
            clearTimeout(timer);
            pending?.abort();
        }

        function choose(label, value) {
            cancelSearch();
            results.replaceChildren();
            if (!target) {
                const page = new URL(picker.dataset.entityDestination, location.href);
                if (account) page.searchParams.set('email', value);
                else page.pathname = page.pathname.replace(/\/$/, '') + '/' + encodeURIComponent(value) + '/edit';
                location.assign(page);
                return;
            }
            target.value = value;
            target.dispatchEvent(new Event('change', { bubbles: true }));
            if (fallback) {
                input.value = value;
                const warning = target.form.querySelector('[data-ban-label]');
                const address = target.form.querySelector('[data-ban-email]');
                if (warning) warning.textContent = label;
                if (address) address.textContent = value;
                input.setCustomValidity('');
                status.textContent = 'Compte sélectionné · ' + label;
                input.focus();
            } else {
                dialog.close();
                target.focus();
            }
        }

        async function search(query) {
            pending = new AbortController();
            const request = pending;
            const url = new URL(picker.dataset.entityUrl, location.href);
            url.searchParams.set('fragment', 'choix');
            url.searchParams.set('q', query);
            url.searchParams.set('type', picker.dataset.entityPicker);
            status.textContent = 'Recherche en cours…';
            try {
                const response = await fetch(url, { signal: request.signal, credentials: 'same-origin' });
                if (!response.ok) throw new Error('Recherche indisponible');
                const data = await response.json();
                if (request.signal.aborted) return;
                const seen = new Set();
                for (const item of data.items) {
                    const value = account ? item.email : String(item.id);
                    if (!value || seen.has(value.toLowerCase())) continue;
                    if (target instanceof HTMLSelectElement && !Array.from(target.options).some(option => option.value === value)) continue;
                    seen.add(value.toLowerCase());
                    const title = account ? item.callsign || item.display_name || value : item.name;
                    const detail = account ? [value, item.community, item.state] : [item.community_code || item.slug, item.archived ? 'Archivée' : 'Active'];
                    const itemNode = document.createElement('li');
                    const button = document.createElement('button');
                    const heading = document.createElement('strong');
                    const caption = document.createElement('span');
                    button.type = 'button';
                    heading.textContent = title;
                    caption.className = 'pu-muted';
                    caption.textContent = [...detail.filter(Boolean), 'n° ' + item.id].join(' · ');
                    button.append(heading, caption);
                    button.addEventListener('click', () => choose(title, value));
                    itemNode.append(button);
                    results.append(itemNode);
                }
                status.textContent = data.error || (results.children.length ? 'Sélectionnez un résultat.' : 'Aucun résultat.');
            } catch {
                if (!request.signal.aborted) status.textContent = 'Recherche indisponible. Réessayez dans un instant.';
            }
        }

        input.addEventListener('input', () => {
            cancelSearch();
            results.replaceChildren();
            if (fallback) {
                target.value = '';
                const confirmation = target.form?.querySelector('[name="confirm_ban"]');
                if (confirmation) confirmation.checked = false;
                const warning = target.form.querySelector('[data-ban-label]');
                const address = target.form.querySelector('[data-ban-email]');
                if (warning) warning.textContent = 'cette personne';
                if (address) address.textContent = 'du compte ciblé';
                input.setCustomValidity('Sélectionnez un compte dans les résultats.');
            }
            const query = input.value.trim();
            status.textContent = 'Saisissez au moins deux caractères.';
            if (query.length >= 2 || /^\d$/.test(query)) timer = setTimeout(() => search(query), 230);
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                results.querySelector('button')?.focus();
            } else if (event.key === 'Enter' && (!fallback || !target.value)) {
                event.preventDefault();
                results.querySelector('button')?.focus();
            }
        });
        dialog?.addEventListener('close', cancelSearch);
    }
})();
