/**
 * Remplace les confirmations natives : formulaires avec data-ui-confirm="1".
 * Attributs : data-ui-confirm-title, data-ui-confirm-body (texte affiché tel quel).
 */
(function () {
    var dlg = document.getElementById('portal-ui-confirm');
    if (!dlg || typeof dlg.showModal !== 'function') {
        return;
    }
    var titleEl = document.getElementById('portal-ui-confirm-title');
    var bodyEl = document.getElementById('portal-ui-confirm-body');
    var pendingForm = null;

    function readAttr(form, name, fallback) {
        var v = form.getAttribute(name);
        return v != null && String(v).trim() !== '' ? String(v).trim() : fallback;
    }

    document.addEventListener(
        'submit',
        function (e) {
            var form = e.target;
            if (!form || form.tagName !== 'FORM' || form.getAttribute('data-ui-confirm') !== '1') {
                return;
            }
            if (form.getAttribute('data-ui-confirm-skip') === '1') {
                form.removeAttribute('data-ui-confirm-skip');
                return;
            }
            e.preventDefault();
            pendingForm = form;
            if (titleEl) {
                titleEl.textContent = readAttr(form, 'data-ui-confirm-title', 'Confirmer');
            }
            if (bodyEl) {
                bodyEl.textContent = readAttr(
                    form,
                    'data-ui-confirm-body',
                    'Souhaitez-vous poursuivre ?'
                );
            }
            try {
                dlg.showModal();
            } catch (err) {
                pendingForm = null;
            }
        },
        true
    );

    dlg.addEventListener('close', function () {
        if (!pendingForm) {
            return;
        }
        var ret = dlg.returnValue;
        if (ret === 'confirm') {
            var f = pendingForm;
            pendingForm = null;
            f.setAttribute('data-ui-confirm-skip', '1');
            if (typeof f.requestSubmit === 'function') {
                f.requestSubmit();
            } else {
                f.submit();
            }
        } else {
            pendingForm = null;
        }
    });
})();
