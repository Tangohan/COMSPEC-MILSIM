(() => {
    const dialog = document.querySelector('[data-login-restriction]');
    if (!dialog?.showModal) return;
    dialog.removeAttribute('open');
    dialog.showModal();
    dialog.querySelector('h2').focus();
    dialog.querySelector('[data-restriction-dismiss]').addEventListener('click', event => {
        event.preventDefault();
        dialog.close();
    });
    dialog.addEventListener('close', () => document.getElementById('email')?.focus());
})();
