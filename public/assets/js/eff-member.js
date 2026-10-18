/*
 * Fiche membre Effectifs : fenêtres (dialog), recherche dans les listes, filtre des champs
 * d'élévation selon le type de demande, sélecteur de fonctions (maximum + principale).
 * Chargé par la fiche membre et par le formulaire d'élévation partagé ; ne s'initialise qu'une fois.
 */
(function () {
    'use strict';
    if (window.__effMemberInit) {
        return;
    }
    window.__effMemberInit = true;

    function normalize(text) {
        return String(text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    function initDialogs() {
        document.addEventListener('click', function (event) {
            var opener = event.target.closest('[data-dialog-open]');
            if (opener) {
                var dialog = document.getElementById(opener.getAttribute('data-dialog-open'));
                if (dialog && typeof dialog.showModal === 'function') {
                    event.preventDefault();
                    dialog.showModal();
                    var focus = dialog.querySelector('[data-filter-input], input:not([type=hidden]), select, textarea');
                    if (focus) {
                        focus.focus();
                    }
                }
                return;
            }
            var closer = event.target.closest('[data-dialog-close]');
            if (closer) {
                var parent = closer.closest('dialog');
                if (parent) {
                    event.preventDefault();
                    parent.close();
                }
                return;
            }
            // Clic sur le fond de la fenêtre : on ferme.
            if (event.target.tagName === 'DIALOG' && event.target.classList.contains('eff-dialog')) {
                var rect = event.target.getBoundingClientRect();
                var inside = event.clientX >= rect.left && event.clientX <= rect.right
                    && event.clientY >= rect.top && event.clientY <= rect.bottom;
                if (!inside) {
                    event.target.close();
                }
            }
        });
    }

    function initFilters() {
        document.querySelectorAll('[data-filter-input]').forEach(function (input) {
            var root = input.closest('fieldset, form, dialog') || document;
            var scope = root.querySelector('[data-filter-scope]');
            if (!scope) {
                return;
            }
            var empty = root.querySelector('[data-filter-empty]');
            var groups = scope.querySelectorAll('[data-filter-group]');
            var initialOpen = Array.prototype.map.call(groups, function (g) { return g.open; });
            input.addEventListener('input', function () {
                var q = normalize(input.value.trim());
                var anyVisible = false;
                scope.querySelectorAll('[data-filter-item]').forEach(function (item) {
                    var match = q === '' || normalize(item.getAttribute('data-filter-text')).indexOf(q) !== -1;
                    item.hidden = !match;
                });
                groups.forEach(function (group, index) {
                    var visible = group.querySelector('[data-filter-item]:not([hidden])') !== null;
                    group.hidden = !visible;
                    group.open = q !== '' ? visible : initialOpen[index];
                    anyVisible = anyVisible || visible;
                });
                if (empty) {
                    empty.hidden = anyVisible;
                }
            });
        });
    }

    function initElevationKind() {
        document.querySelectorAll('[data-elev-form]').forEach(function (form) {
            var select = form.querySelector('[data-elev-kind]');
            if (!select) {
                return;
            }
            var apply = function () {
                var kind = select.value;
                form.querySelectorAll('[data-elev-kinds]').forEach(function (field) {
                    var show = field.getAttribute('data-elev-kinds').split(' ').indexOf(kind) !== -1;
                    field.hidden = !show;
                    // Champ masqué = valeur non envoyée, pour éviter une proposition oubliée.
                    field.querySelectorAll('input, select, textarea').forEach(function (el) {
                        el.disabled = !show;
                    });
                });
                var perms = form.querySelector('.elevation-access-rights');
                if (perms && !perms.hidden && kind === 'droits') {
                    var first = perms.querySelector('details');
                    if (first && perms.querySelectorAll('details').length <= 3) {
                        first.open = true;
                    }
                }
            };
            select.addEventListener('change', apply);
            apply();
            var counter = form.querySelector('[data-elev-selected-count]');
            if (counter) {
                form.addEventListener('change', function (event) {
                    if (event.target.name !== 'elevation_permission_ids[]') {
                        return;
                    }
                    var n = form.querySelectorAll('input[name="elevation_permission_ids[]"]:checked').length;
                    counter.hidden = n === 0;
                    counter.textContent = n + (n > 1 ? ' droits sélectionnés' : ' droit sélectionné');
                });
            }
        });
    }

    function initJobPicker() {
        document.querySelectorAll('[data-job-picker]').forEach(function (form) {
            var max = parseInt(form.getAttribute('data-job-max'), 10) || 5;
            var counter = form.querySelector('[data-job-count]');
            var boxes = function () { return form.querySelectorAll('input[name="job_role_ids[]"]'); };
            var refresh = function () {
                var checked = form.querySelectorAll('input[name="job_role_ids[]"]:checked');
                boxes().forEach(function (box) {
                    box.disabled = !box.checked && checked.length >= max;
                    var radio = box.closest('[data-filter-item]').querySelector('input[type=radio]');
                    if (radio) {
                        radio.disabled = !box.checked;
                        if (!box.checked) {
                            radio.checked = false;
                        }
                    }
                });
                if (checked.length > 0 && !form.querySelector('input[name="primary_job_role_id"]:checked')) {
                    var firstRadio = checked[0].closest('[data-filter-item]').querySelector('input[type=radio]');
                    if (firstRadio) {
                        firstRadio.checked = true;
                    }
                }
                if (counter) {
                    counter.textContent = checked.length + ' / ' + max;
                }
            };
            form.addEventListener('change', function (event) {
                if (event.target.name === 'primary_job_role_id') {
                    var box = event.target.closest('[data-filter-item]').querySelector('input[type=checkbox]');
                    if (box && !box.checked) {
                        box.checked = true;
                    }
                }
                refresh();
            });
            refresh();
        });
    }

    function initPortraitPreview() {
        document.querySelectorAll('[data-portrait-input]').forEach(function (input) {
            var preview = input.closest('form').querySelector('[data-portrait-preview]');
            input.addEventListener('change', function () {
                if (!preview || !input.files || !input.files[0]) {
                    return;
                }
                var img = document.createElement('img');
                img.alt = '';
                img.src = URL.createObjectURL(input.files[0]);
                preview.innerHTML = '';
                preview.appendChild(img);
            });
        });
    }

    function init() {
        initDialogs();
        initFilters();
        initElevationKind();
        initJobPicker();
        initPortraitPreview();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
