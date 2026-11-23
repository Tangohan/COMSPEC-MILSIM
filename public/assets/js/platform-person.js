(() => {
    const root = document.querySelector('[data-person]');
    if (!root) return;

    if (typeof root.querySelector('#pu-confirm')?.showModal === 'function') {
        for (const button of root.querySelectorAll('[data-person-enhanced]')) {
            button.hidden = false;
        }
    }

    const copy = root.querySelector('[data-person-copy]');
    if (copy && navigator.clipboard) {
        copy.hidden = false;
        copy.addEventListener('click', async () => {
            const status = root.querySelector('[data-person-copy-status]');
            try {
                await navigator.clipboard.writeText(copy.dataset.personCopy);
                status.textContent = 'Adresse copiée.';
            } catch {
                status.textContent = 'La copie automatique a échoué. L’adresse reste affichée dans le dossier.';
            }
        });
    }
})();
