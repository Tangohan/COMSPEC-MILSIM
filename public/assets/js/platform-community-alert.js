(() => {
    const dialog = document.querySelector('[data-community-alert-dialog]');
    if (!dialog?.showModal) return;

    const form = dialog.querySelector('form');
    const fields = dialog.querySelector('[data-community-alert-fields]');
    const status = dialog.querySelector('[data-community-alert-status]');
    const submit = dialog.querySelector('[data-community-alert-submit]');
    const close = dialog.querySelector('[data-community-alert-close]');
    const tenant = fields.querySelector('select[name=tenant_id]');
    const defaultTenant = tenant?.value;
    const tracking = dialog.querySelector('[data-community-alert-tracking]');
    let sending = false;

    function announce(message, tone) {
        status.className = 'pa-flash' + (tone ? ' pa-flash--' + tone : '');
        status.textContent = message;
        status.hidden = !message;
    }

    document.querySelectorAll('[data-community-alert-open]').forEach(button => {
        button.addEventListener('click', () => {
            fields.hidden = false;
            submit.hidden = false;
            tracking.hidden = true;
            announce('', '');
            if (tenant) tenant.value = button.dataset.communityAlertTenant || defaultTenant;
            dialog.showModal();
            const title = fields.querySelector('input[type=text]');
            (tenant && !tenant.value ? tenant : title).focus();
        });
    });
    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('cancel', event => { if (sending) event.preventDefault(); });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (sending || fields.hidden) return;
        sending = true;
        submit.disabled = true;
        close.disabled = true;
        announce(form.dataset.publishing, '');
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                announce(data.error || form.dataset.error, 'err');
                return;
            }
            announce(data.message, 'ok');
            form.reset();
            dialog.querySelector('[data-community-alert-translation]').open = false;
            fields.hidden = true;
            submit.hidden = true;
            tracking.href = data.tracking_url;
            tracking.hidden = false;
            tracking.focus();
        } catch {
            announce(form.dataset.error, 'err');
        } finally {
            submit.disabled = false;
            close.disabled = false;
            sending = false;
        }
    });
})();
