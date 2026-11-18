(() => {
    const dialog = document.querySelector('#mp-workshop');
    const trigger = document.querySelector('[data-workshop-notice]');
    if (!trigger || typeof dialog?.showModal !== 'function') return;

    trigger.hidden = false;
    trigger.addEventListener('click', () => dialog.showModal());
    const key = 'comspec.modpack.workshop-notice.v1';
    try {
        if (!sessionStorage.getItem(key)) {
            dialog.showModal();
            sessionStorage.setItem(key, 'seen');
        }
    } catch {
        // The inline button still opens the notice when browser storage is unavailable.
    }
})();
