(() => {
    'use strict';
    if (window.personnelJobsWorkspaceLoaded) return;
    window.personnelJobsWorkspaceLoaded = true;

    let currentUrl = window.location.href;
    let navigation = null;
    let writing = false;
    let catalogSearch = '';
    const dirty = new WeakSet();
    const root = () => document.querySelector('[data-jobs-workspace]');
    const normalize = value => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
    const hasChanges = () => Array.from(root()?.querySelectorAll('form[data-jobs-save]') || []).some(form => dirty.has(form));
    const mayLeave = () => !hasChanges() || window.confirm('Quitter ce formulaire et abandonner les modifications non enregistrées ?');

    function notice(message, isError = false) {
        const target = root()?.querySelector('[data-jobs-feedback]');
        if (!target) return;
        target.textContent = message;
        target.hidden = !message;
        target.classList.toggle('is-error', isError);
        target.setAttribute('role', isError ? 'alert' : 'status');
    }

    function filter() {
        const scope = root();
        if (!scope) return;
        const searchInput = scope.querySelector('[data-jobs-search]');
        if (searchInput) catalogSearch = searchInput.value;
        const query = normalize(searchInput?.value || '').trim();
        let visible = 0;
        scope.querySelectorAll('[data-jobs-row]').forEach(row => {
            row.hidden = !normalize(row.dataset.search || row.textContent).includes(query);
            if (!row.hidden) visible++;
        });
        const empty = scope.querySelector('[data-jobs-empty]');
        if (empty) empty.hidden = visible > 0;
    }

    function workspaceUrl(value) {
        const scope = root();
        if (!scope) return null;
        const url = new URL(value, window.location.href);
        const base = new URL(scope.dataset.jobsBase, window.location.href);
        if (url.origin !== base.origin || url.pathname !== base.pathname || url.searchParams.get('vue') === 'attributions') return null;
        return url;
    }

    async function navigate(value, options = {}) {
        const url = workspaceUrl(value);
        if (!url || writing) return false;
        if (!options.skipGuard && !mayLeave()) return false;
        if (navigation) navigation.abort();
        const request = new AbortController();
        navigation = request;
        const scope = root();
        const scroll = [window.scrollX, window.scrollY];
        const search = scope.querySelector('[data-jobs-search]')?.value ?? catalogSearch;
        const open = new Set(Array.from(scope.querySelectorAll('details[data-jobs-details][open]'), details => details.dataset.jobsDetails));
        scope.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url.href, {
                credentials: 'same-origin', headers: {'X-Effectifs-Fragment': 'jobs'}, signal: request.signal,
            });
            if (!response.ok) throw new Error('Le contenu n’a pas pu être chargé. Vos modifications restent affichées.');
            if (response.redirected && !workspaceUrl(response.url)) throw new Error('La session ou les autorisations ont changé. Reconnectez-vous pour continuer.');
            const html = await response.text();
            if (request.signal.aborted) return false;
            const next = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-jobs-workspace]');
            if (!next) throw new Error('Le contenu reçu est incomplet. Rechargez la page après avoir conservé votre saisie.');
            next.querySelectorAll('script').forEach(script => script.remove());
            scope.replaceWith(next);
            const nextSearch = next.querySelector('[data-jobs-search]');
            if (nextSearch) nextSearch.value = search;
            next.querySelectorAll('details[data-jobs-details]').forEach(details => {
                if (open.has(details.dataset.jobsDetails)) details.open = true;
            });
            filter();
            if (options.history !== false) window.history.pushState({personnelJobs: true}, '', url.href);
            currentUrl = url.href;
            const heading = next.querySelector('h2, h1');
            if (heading) { heading.tabIndex = -1; heading.focus({preventScroll: true}); }
            window.scrollTo(...scroll);
            if (options.message) notice(options.message);
            return true;
        } catch (error) {
            if (error.name !== 'AbortError') notice(error.message || 'Le chargement a échoué. Réessayez.', true);
            return false;
        } finally {
            if (navigation === request) {
                navigation = null;
                root()?.removeAttribute('aria-busy');
            }
        }
    }

    document.addEventListener('input', event => {
        if (!event.target.closest('[data-jobs-workspace]')) return;
        if (event.target.matches('[data-jobs-search]')) filter();
        const form = event.target.closest('form[data-jobs-save]');
        if (form) dirty.add(form);
    });
    document.addEventListener('change', event => {
        const form = event.target.closest('[data-jobs-workspace] form[data-jobs-save]');
        if (form) dirty.add(form);
    });

    document.addEventListener('click', event => {
        const link = event.target.closest('a[data-jobs-nav]');
        if (!link || !link.closest('[data-jobs-workspace]') || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank') return;
        if (!workspaceUrl(link.href)) return;
        event.preventDefault();
        if (writing) return;
        navigate(link.href);
    });

    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.closest('[data-jobs-workspace]')) return;
        if (form.matches('[data-jobs-query]')) {
            event.preventDefault();
            if (writing) return;
            const url = new URL(form.action, window.location.href);
            url.search = new URLSearchParams(new FormData(form)).toString();
            await navigate(url.href);
            return;
        }
        if (!form.matches('[data-jobs-save]')) return;
        event.preventDefault();
        if (writing) return;
        const submitter = event.submitter;
        if (!submitter?.formNoValidate && !form.reportValidity()) return;
        if (Array.from(root().querySelectorAll('form[data-jobs-save]')).some(other => other !== form && dirty.has(other)) && !window.confirm('Les autres formulaires modifiés ne seront pas enregistrés. Continuer avec ce formulaire ?')) return;
        const confirmation = submitter?.dataset.jobsConfirm || form.dataset.jobsConfirm;
        if (confirmation && !window.confirm(confirmation)) return;
        // Build the payload before disabling controls. The clicked category operation must be preserved.
        const payload = new FormData(form);
        if (submitter?.name) payload.append(submitter.name, submitter.value);
        const endpoint = new URL(form.action, window.location.href);
        const base = new URL(root().dataset.jobsBase, window.location.href);
        if (endpoint.origin !== base.origin || !(endpoint.pathname === base.pathname || endpoint.pathname.startsWith(base.pathname + '/'))) return;
        if (navigation) navigation.abort();
        writing = true;
        root().setAttribute('aria-busy', 'true');
        const buttons = Array.from(root().querySelectorAll('button, input[type=submit]')).filter(button => !button.disabled);
        buttons.forEach(button => { button.disabled = true; });
        let saved = false;
        try {
            const response = await fetch(endpoint.href, {
                method: 'POST', credentials: 'same-origin',
                headers: {'X-Effectifs-Fragment': 'jobs', 'Accept': 'application/json'}, body: payload,
            });
            if (!(response.headers.get('Content-Type') || '').includes('application/json')) throw new Error('La réponse n’a pas pu être confirmée. Votre saisie est conservée ; vérifiez votre connexion avant de réessayer.');
            const result = await response.json();
            if (!response.ok || result.ok !== true) throw new Error(result.message || 'L’enregistrement a échoué. Votre saisie est conservée.');
            saved = true;
            dirty.delete(form);
            writing = false;
            const destination = workspaceUrl(result.redirect || currentUrl);
            if (!destination || !await navigate(destination.href, {skipGuard: true, message: result.message || 'Modifications enregistrées.'})) {
                notice((result.message || 'Modifications enregistrées.') + ' Actualisez la page pour afficher le résultat.', true);
            }
        } catch (error) {
            notice(error.message || 'La réponse n’a pas pu être reçue. Votre saisie est conservée ; aucun nouvel essai automatique n’a été effectué.', true);
        } finally {
            writing = false;
            buttons.forEach(button => { if (button.isConnected) button.disabled = false; });
            root()?.removeAttribute('aria-busy');
            if (saved) dirty.delete(form);
        }
    });

    window.addEventListener('popstate', async () => {
        if (!root()) return;
        const destination = window.location.href;
        if (writing || !mayLeave()) {
            window.history.pushState({personnelJobs: true}, '', currentUrl);
            return;
        }
        if (!await navigate(destination, {history: false, skipGuard: true})) {
            window.history.replaceState({personnelJobs: true}, '', currentUrl);
        }
    });
    window.addEventListener('beforeunload', event => {
        if (writing || hasChanges()) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    filter();
})();
