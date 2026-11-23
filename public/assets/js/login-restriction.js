(() => {
    const dialog = document.querySelector('[data-login-restriction]');
    if (!dialog?.showModal) return;
    dialog.removeAttribute('open');
    dialog.showModal();
    dialog.querySelector('h2').focus();
    dialog.querySelectorAll('[data-restriction-dismiss]').forEach(link => {
        link.addEventListener('click', event => {
            event.preventDefault();
            dialog.close();
        });
    });
    dialog.addEventListener('close', () => document.getElementById('email')?.focus());
})();
