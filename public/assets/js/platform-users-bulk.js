(() => {
    const dialog = document.querySelector('[data-pu-bulk]');
    const opener = document.querySelector('[data-pu-bulk-open]');
    if (!dialog?.showModal || !opener) return;

    const form = dialog.querySelector('form');
    const operation = dialog.querySelector('[data-bulk-operation]');
    const status = dialog.querySelector('[data-bulk-status]');
    const threshold = dialog.querySelector('[data-bulk-threshold]');
    const list = dialog.querySelector('[data-bulk-results]');
    const banFields = dialog.querySelector('[data-bulk-ban]');
    const reason = form.elements.namedItem('reason');
    const nonce = form.elements.namedItem('preview_nonce');
    const confirmation = dialog.querySelector('[data-bulk-confirm]');
    const checkbox = form.elements.namedItem('confirm_bulk');
    const apply = dialog.querySelector('[data-bulk-apply]');
    const close = dialog.querySelector('[data-pu-close]');
    const refresh = dialog.querySelector('[data-bulk-refresh]');
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
        list.hidden = true;
        list.replaceChildren();
        confirmation.hidden = true;
        checkbox.checked = false;
        checkbox.disabled = true;
        apply.disabled = true;
    }

    function showRows(rows) {
        list.replaceChildren(...rows.map(({ email, label, message }) => {
            const row = document.createElement('li');
            const title = document.createElement('strong');
            const detail = document.createElement('span');
            title.textContent = label && label !== email ? label + ' · ' + email : email;
            detail.className = 'pu-muted';
            detail.textContent = message;
            row.append(title, detail);
            return row;
        }));
        list.hidden = rows.length === 0;
    }

    async function post(url, body, signal) {
        const response = await fetch(url, { method: 'POST', body, signal, credentials: 'same-origin' });
        const data = await response.json().catch(() => null);
        if (!response.ok || !data) throw new Error(data?.error || 'La demande n’a pas pu être traitée.');
        return data;
    }

    async function preview() {
        previewRequest?.abort();
        clearPreview();
        const action = operation.value;
        const bans = action.startsWith('ban');
        banFields.hidden = !bans;
        banFields.disabled = !bans;
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
        selected.forEach(email => body.append('emails[]', email));
        announce('Vérification des comptes cochés…');
        try {
            const data = await post(form.dataset.previewUrl, body, request.signal);
            if (request.signal.aborted) return;
            nonce.value = data.nonce;
            threshold.textContent = data.threshold;
            threshold.hidden = !data.threshold;
            announce(data.eligible + ' compte(s) admissible(s) sur ' + data.rows.length + '.');
            showRows(data.rows.map(row => ({ ...row, message: row.eligible ? 'Admissible' : row.reason })));
            confirmation.hidden = data.eligible === 0;
            checkbox.disabled = data.eligible === 0;
            dialog.querySelector('[data-bulk-confirm-label]').textContent = bans
                ? 'Je confirme le bannissement sur tout Athena des ' + data.eligible + ' compte(s) admissible(s).'
                : 'Je confirme la relance des ' + data.eligible + ' compte(s) admissible(s).';
            apply.className = 'pa-btn ' + (bans ? 'pa-btn--danger' : 'pa-btn--solid');
            apply.textContent = bans ? 'Bannir les comptes admissibles' : 'Renvoyer les liens';
        } catch (error) {
            if (!request.signal.aborted) announce(error.message, 'err');
        }
    }

    opener.addEventListener('click', () => {
        selected = Array.from(document.querySelectorAll('[data-pu-pick]:checked'), row => row.value);
        if (!selected.length) return;
        form.reset();
        operation.disabled = false;
        apply.hidden = false;
        refresh.hidden = true;
        dialog.showModal();
        preview();
        operation.focus();
    });
    operation.addEventListener('change', preview);
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
        applying = true;
        operation.disabled = true;
        close.disabled = true;
        apply.disabled = true;
        announce('Action en cours…');
        try {
            const data = await post(form.action, body);
            const count = state => data.outcomes.filter(row => row.state === state).length;
            announce(count('applied') + ' appliquée(s), ' + count('skipped') + ' ignorée(s), ' + count('failed') + ' en échec.'
                + (data.mail_notice ? ' ' + data.mail_notice : ''), count('failed') ? 'warn' : 'ok');
            showRows(data.outcomes);
            banFields.hidden = true;
            banFields.disabled = true;
            confirmation.hidden = true;
            apply.hidden = true;
            refresh.hidden = false;
            refresh.focus();
        } catch (error) {
            announce(error.message, 'err');
            apply.disabled = false;
            operation.disabled = false;
        } finally {
            close.disabled = false;
            applying = false;
        }
    });
})();
