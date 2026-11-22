(() => {
    const dialog = document.querySelector('[data-pu-bulk]');
    const opener = document.querySelector('[data-pu-bulk-open]');
    if (!dialog?.showModal || !opener) return;

    const form = dialog.querySelector('form');
    const operation = dialog.querySelector('[data-bulk-operation]');
    const status = dialog.querySelector('[data-bulk-status]');
    const threshold = dialog.querySelector('[data-bulk-threshold]');
    const selection = dialog.querySelector('[data-bulk-selection]');
    const summary = dialog.querySelector('[data-bulk-selection-summary]');
    const defaultSummary = summary.textContent;
    const rosterOption = operation.querySelector('option[value="remove-roster"]');
    const rosterHelp = dialog.querySelector('[data-bulk-roster-help]');
    const rosterShortcut = dialog.querySelector('[data-bulk-roster-shortcut]');
    const list = dialog.querySelector('[data-bulk-results]');
    const banFields = dialog.querySelector('[data-bulk-ban]');
    const reason = form.elements.namedItem('reason');
    const nonce = form.elements.namedItem('preview_nonce');
    const confirmation = dialog.querySelector('[data-bulk-confirm]');
    const confirmLabel = dialog.querySelector('[data-bulk-confirm-label]');
    const checkbox = form.elements.namedItem('confirm_bulk');
    const apply = dialog.querySelector('[data-bulk-apply]');
    const close = dialog.querySelector('[data-pu-close]');
    const refresh = dialog.querySelector('[data-bulk-refresh]');
    const retry = dialog.querySelector('[data-bulk-retry]');
    let selected = [];
    let previewRequest;
    let applying = false;

    function announce(message, flavor = '') {
        status.hidden = !message;
        status.className = 'pa-flash' + (flavor ? ' pa-flash--' + flavor : '');
        status.textContent = message;
    }

    function clearPreview() {
        nonce.value = '';
        threshold.hidden = true;
        rosterHelp.hidden = true;
        rosterShortcut.hidden = true;
        selection.hidden = true;
        selection.open = false;
        list.replaceChildren();
        banFields.hidden = true;
        banFields.disabled = true;
        apply.hidden = true;
        retry.hidden = true;
        confirmation.hidden = true;
        confirmLabel.textContent = '';
        checkbox.checked = false;
        checkbox.disabled = true;
        checkbox.required = false;
        apply.disabled = true;
    }

    function showRows(rows, expanded) {
        list.replaceChildren(...rows.map(({ email, label, roster, message }) => {
            const row = document.createElement('li');
            const title = document.createElement('strong');
            const detail = document.createElement('span');
            title.textContent = roster ? label : (label && label !== email ? label + ' · ' + email : email);
            detail.className = 'pu-muted';
            detail.textContent = message;
            row.append(title, detail);
            return row;
        }));
        selection.hidden = rows.length === 0;
        selection.open = expanded;
    }

    async function post(url, body, signal) {
        const response = await fetch(url, { method: 'POST', body, signal, credentials: 'same-origin' });
        const data = await response.json().catch(() => null);
        if (!response.ok || !data) {
            const error = new Error(data?.error || 'La demande n’a pas pu être traitée.');
            error.status = data?.error ? response.status : 0;
            throw error;
        }
        return data;
    }

    async function preview() {
        previewRequest?.abort();
        clearPreview();
        const action = operation.value;
        const bans = action.startsWith('ban');
        const removesRoster = action === rosterOption.value;
        summary.textContent = removesRoster ? summary.dataset.rosterLabel : defaultSummary;
        reason.required = action === 'ban';
        reason.placeholder = action === 'ban-inactive' ? 'Motif d’inactivité généré si laissé vide' : '';
        if (!action) {
            announce(selected.length + ' compte(s) coché(s) sur cette page.');
            return;
        }

        const request = new AbortController();
        previewRequest = request;
        const body = new FormData();
        body.set('_csrf_token', form.elements.namedItem('_csrf_token').value);
        body.set('operation', action);
        body.set('inactif', form.dataset.inactivity);
        body.set('tenant_id', form.dataset.tenantId);
        selected.forEach(row => body.append('emails[]', row.value));
        announce(removesRoster ? status.dataset.rosterChecking : 'Vérification des comptes cochés…');
        try {
            const data = await post(form.dataset.previewUrl, body, request.signal);
            if (request.signal.aborted) return;
            nonce.value = data.nonce;
            threshold.textContent = data.threshold;
            threshold.hidden = !data.threshold;
            const hasEligible = data.eligible > 0;
            const count = data.eligible === 1 ? '1 compte admissible' : data.eligible + ' comptes admissibles';
            let previewStatus = hasEligible ? count + ' sur ' + data.rows.length + '.' : 'Aucun compte admissible dans la sélection.';
            if (removesRoster) {
                const formatted = data.eligible === 1 ? status.dataset.rosterEligibleOne : status.dataset.rosterEligibleMany;
                previewStatus = hasEligible ? formatted.replace(':count', data.eligible).replace(':total', data.rows.length) : status.dataset.rosterNone;
            }
            announce(previewStatus, hasEligible ? '' : 'warn');
            showRows(data.rows.map(row => ({
                ...row,
                message: row.eligible ? (removesRoster ? row.communities.join(', ') : 'Admissible') : row.reason,
            })), removesRoster || !hasEligible);
            rosterHelp.hidden = !removesRoster || !hasEligible;
            rosterShortcut.hidden = removesRoster || !data.rows.some(row => row.roster);
            banFields.hidden = !bans || !hasEligible;
            banFields.disabled = banFields.hidden;
            confirmation.hidden = !hasEligible;
            checkbox.disabled = !hasEligible;
            checkbox.required = hasEligible;
            apply.hidden = !hasEligible;
            if (hasEligible) {
                if (removesRoster) {
                    confirmLabel.textContent = confirmLabel.dataset.rosterLabel;
                } else {
                    confirmLabel.textContent = bans
                        ? 'Je confirme le bannissement sur tout Athena des ' + data.eligible + ' compte(s) admissible(s).'
                        : 'Je confirme la relance des ' + data.eligible + ' compte(s) admissible(s).';
                }
                apply.className = 'pa-btn ' + (bans ? 'pa-btn--danger' : 'pa-btn--solid');
                apply.textContent = removesRoster ? rosterOption.textContent : (bans ? 'Bannir les comptes admissibles' : 'Renvoyer les liens');
            }
        } catch (error) {
            if (!request.signal.aborted) {
                announce(error.message, 'err');
                retry.hidden = error.status === 403 || error.status === 422;
            }
        }
    }

    function open(rows) {
        if (!rows.length) return;
        selected = rows;
        form.reset();
        form.querySelector('input[name="duree"]:checked')?.dispatchEvent(new Event('change'));
        operation.disabled = false;
        operation.value = rows.every(row => row.hasAttribute('data-pu-roster')) ? rosterOption.value : '';
        refresh.hidden = true;
        dialog.showModal();
        preview();
        operation.focus();
    }
    opener.addEventListener('click', () => open(Array.from(document.querySelectorAll('[data-pu-pick]:checked'))));
    document.querySelectorAll('[data-pu-roster-open]').forEach(button => {
        button.addEventListener('click', () => open([button.closest('[data-pu-row]').querySelector('[data-pu-pick]')]));
    });
    rosterShortcut.addEventListener('click', () => {
        operation.value = rosterOption.value;
        preview();
        operation.focus();
    });
    operation.addEventListener('change', preview);
    retry.addEventListener('click', preview);
    checkbox.addEventListener('change', () => { apply.disabled = !checkbox.checked; });
    dialog.addEventListener('close', () => previewRequest?.abort());
    dialog.addEventListener('cancel', event => { if (applying) event.preventDefault(); });
    dialog.addEventListener('click', event => {
        if (applying && event.target === dialog) event.stopImmediatePropagation();
    }, true);

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (applying || !nonce.value || !checkbox.checked) return;
        const body = new FormData(form);
        const removesRoster = operation.value === rosterOption.value;
        applying = true;
        operation.disabled = true;
        close.disabled = true;
        apply.disabled = true;
        announce(removesRoster ? status.dataset.rosterProcessing : 'Action en cours…');
        try {
            const data = await post(form.action, body);
            const count = state => data.outcomes.filter(row => row.state === state).length;
            const applied = count('applied');
            const skipped = count('skipped');
            const failed = count('failed');
            const needsReview = failed + skipped > 0;
            const outcome = removesRoster
                ? status.dataset.rosterResults.replace(':applied', applied).replace(':skipped', skipped).replace(':failed', failed)
                : applied + ' appliquée(s), ' + skipped + ' ignorée(s), ' + failed + ' en échec.';
            announce(outcome + (data.mail_notice ? ' ' + data.mail_notice : ''), needsReview ? 'warn' : 'ok');
            showRows(data.outcomes.map(row => ({ ...row, roster: removesRoster })), needsReview);
            rosterHelp.hidden = true;
            rosterShortcut.hidden = true;
            banFields.hidden = true;
            banFields.disabled = true;
            confirmation.hidden = true;
            apply.hidden = true;
            refresh.hidden = false;
            refresh.focus();
        } catch (error) {
            if (error.status === 422) {
                apply.disabled = false;
            } else {
                clearPreview();
                retry.hidden = error.status === 403;
            }
            announce(error.message, 'err');
            operation.disabled = false;
        } finally {
            close.disabled = false;
            applying = false;
        }
    });
})();
