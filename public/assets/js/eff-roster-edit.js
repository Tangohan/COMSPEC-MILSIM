/*
 * Tableur des effectifs : fenêtres « Emploi », « Rôle » et « Niveau d'accès ».
 * Une seule fenêtre par type pour toute la page, remplie avec la ligne cliquée.
 * L'ouverture, la fermeture, la recherche et le maximum d'emplois viennent de eff-member.js.
 */
(function () {
    'use strict';
    if (window.__effRosterEditInit) {
        return;
    }
    window.__effRosterEditInit = true;

    var DIALOGS = { emploi: 'eff-dlg-roster-job', role: 'eff-dlg-roster-role', acces: 'eff-dlg-roster-access' };

    function prepare(dialog, button) {
        var form = dialog.querySelector('form');
        var id = button.getAttribute('data-eff-member-id') || '';
        form.setAttribute('action', (form.getAttribute('data-eff-action') || '').replace('__ID__', encodeURIComponent(id)));
        dialog.querySelectorAll('[data-eff-member-name]').forEach(function (el) {
            el.textContent = button.getAttribute('data-eff-member-name') || '';
        });
        return form;
    }

    function fillJobs(form, button) {
        var ids = [];
        try {
            ids = JSON.parse(button.getAttribute('data-eff-jobs') || '[]').map(String);
        } catch (e) {
            ids = [];
        }
        var primary = button.getAttribute('data-eff-job-primary') || '';
        form.querySelectorAll('input[name="job_role_ids[]"]').forEach(function (box) {
            box.disabled = false;
            box.checked = ids.indexOf(box.value) !== -1;
        });
        form.querySelectorAll('input[name="primary_job_role_id"]').forEach(function (radio) {
            radio.disabled = false;
            radio.checked = primary !== '0' && radio.value === primary;
        });
        var search = form.querySelector('[data-filter-input]');
        if (search) {
            search.value = '';
            search.dispatchEvent(new Event('input', { bubbles: true }));
        }
        form.querySelectorAll('[data-filter-group]').forEach(function (group, index) {
            group.open = index === 0 || group.querySelector('input[name="job_role_ids[]"]:checked') !== null;
        });
        // Recalcule le compteur et le maximum (eff-member.js écoute « change » sur le formulaire).
        form.dispatchEvent(new Event('change', { bubbles: true }));
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('[data-eff-edit]') : null;
        if (!button) {
            return;
        }
        var kind = button.getAttribute('data-eff-edit');
        var dialog = document.getElementById(DIALOGS[kind] || '');
        if (!dialog || typeof dialog.showModal !== 'function') {
            return;
        }
        event.preventDefault();
        var form = prepare(dialog, button);
        if (kind === 'emploi') {
            fillJobs(form, button);
        } else if (kind === 'role') {
            var input = form.querySelector('[data-eff-role-input]');
            if (input) {
                input.value = button.getAttribute('data-eff-role') || '';
            }
        } else if (kind === 'acces') {
            var current = button.getAttribute('data-eff-access') || '';
            form.querySelectorAll('input[name="access_role_id"]').forEach(function (radio) {
                radio.checked = radio.value === current;
            });
        }
        dialog.showModal();
        var focus = dialog.querySelector('[data-eff-role-input], [data-filter-input], input[name="access_role_id"]:checked');
        if (focus) {
            focus.focus();
        }
    });
})();
