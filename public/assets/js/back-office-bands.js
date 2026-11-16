/**
 * Back-office : fenêtres de formulaire .bo-dialog (back-office-bands.css).
 * [data-bo-dialog-open="id"] ouvre la fenêtre <dialog id="id"> ; [data-bo-dialog-close] ou Échap la ferme,
 * un clic sur le fond aussi. Le focus revient au bouton d'ouverture.
 */
(function () {
    'use strict';
    if (window.__boBandsDialogs) { return; }
    window.__boBandsDialogs = true;

    var opener = null;
    document.addEventListener('click', function (e) {
        var open = e.target.closest('[data-bo-dialog-open]');
        if (open) {
            var dlg = document.getElementById(open.getAttribute('data-bo-dialog-open'));
            if (dlg && typeof dlg.showModal === 'function') {
                e.preventDefault();
                opener = open;
                dlg.showModal();
                var first = dlg.querySelector('textarea, input:not([type="hidden"]), select');
                if (first) { first.focus(); }
            }
            return;
        }
        var close = e.target.closest('[data-bo-dialog-close]');
        if (close) {
            var d = close.closest('dialog');
            if (d) { d.close(); }
            return;
        }
        if (e.target instanceof HTMLDialogElement && e.target.classList.contains('bo-dialog') && e.target.open) {
            var r = e.target.getBoundingClientRect();
            if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) {
                e.target.close();
            }
        }
    });
    document.addEventListener('close', function (e) {
        if (e.target instanceof HTMLDialogElement && e.target.classList.contains('bo-dialog') && opener) {
            opener.focus();
            opener = null;
        }
    }, true);
})();
