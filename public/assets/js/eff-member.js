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
            if (event.target.tagName === 'DIALOG' && (event.target.classList.contains('eff-dialog') || event.target.classList.contains('eff-civil'))) {
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

    // Fenêtre « État civil du personnage » : ombre sous l'en-tête et au-dessus du pied selon le défilement du corps ;
    // au retour d'une erreur d'enregistrement, le focus va au champ refusé (sinon au message).
    function initCivilDialog() {
        var dialog = document.getElementById('eff-dlg-civil');
        var form = dialog ? dialog.querySelector('[data-civil-form]') : null;
        var body = dialog ? dialog.querySelector('[data-civil-scroll]') : null;
        if (!form || !body) {
            return;
        }
        var shade = function () {
            form.classList.toggle('is-scrolled', body.scrollTop > 2);
            form.classList.toggle('has-more', body.scrollTop + body.clientHeight < body.scrollHeight - 2);
        };
        body.addEventListener('scroll', shade, { passive: true });
        window.addEventListener('resize', shade);
        dialog.addEventListener('close', shade);
        new MutationObserver(function () {
            if (!dialog.open) {
                return;
            }
            shade();
            if (dialog.hasAttribute('data-civil-has-error')) {
                var target = dialog.querySelector('[aria-invalid="true"]') || dialog.querySelector('.eff-civil__alert');
                if (target) {
                    target.focus();
                }
            }
        }).observe(dialog, { attributes: true, attributeFilter: ['open'] });
    }

    // Lien direct vers une fenêtre (ex. « À traiter » → fiche#eff-dlg-jobs) : on l'ouvre au chargement.
    // Idem avec ?ouvrir=etat-civil (liens des tâches et des qualités de données) : alias connus ci-dessous,
    // un paramètre inconnu ou une fenêtre absente de la page (droits) est ignoré.
    var DIALOG_ALIASES = {
        'etat-civil': 'eff-dlg-civil',
        'langues': 'eff-dlg-langues',
        'fonctions': 'eff-dlg-jobs',
        'portrait': 'eff-dlg-portrait',
        'elevation': 'eff-dlg-elevation',
        'depart': 'eff-dlg-depart',
        'acces-sse': 'eff-dlg-acces-sse'
    };

    function openDialogFromHash() {
        var id = window.location.hash.replace('#', '');
        var wanted = '';
        try {
            wanted = new URL(window.location.href).searchParams.get('ouvrir') || '';
        } catch (e) {
            wanted = '';
        }
        if (Object.prototype.hasOwnProperty.call(DIALOG_ALIASES, wanted)) {
            id = DIALOG_ALIASES[wanted];
        }
        if (!/^eff-dlg-[a-z0-9-]+$/i.test(id)) {
            return;
        }
        var dialog = document.getElementById(id);
        if (dialog && typeof dialog.showModal === 'function' && !dialog.open) {
            dialog.showModal();
        }
    }

    // Onglets de la fiche : un panneau à la fois. Sans ce script, le serveur affiche tous les panneaux à la suite
    // (les onglets sont de simples liens ?onglet=…). Les anciennes ancres (#grade, #statut…) ouvrent l'onglet qui
    // contient leur carte ; le clavier suit le motif WAI-ARIA des onglets (flèches, Début, Fin).
    function initFicheTabs() {
        var nav = document.querySelector('[data-eff-fm-tabs]');
        var root = nav ? nav.closest('.eff-fm') : null;
        if (!nav || !root) {
            return;
        }
        var tabs = Array.prototype.slice.call(nav.querySelectorAll('[data-eff-fm-tab]'));
        var panels = {};
        root.querySelectorAll('[data-eff-fm-panel]').forEach(function (panel) {
            panels[panel.getAttribute('data-eff-fm-panel')] = panel;
        });
        tabs = tabs.filter(function (tab) {
            return panels[tab.getAttribute('data-eff-fm-tab')];
        });
        if (tabs.length === 0) {
            return;
        }
        root.classList.add('eff-fm--tabs');
        nav.setAttribute('role', 'tablist');
        tabs.forEach(function (tab) {
            var slug = tab.getAttribute('data-eff-fm-tab');
            tab.setAttribute('role', 'tab');
            panels[slug].setAttribute('role', 'tabpanel');
        });

        var current = '';
        var show = function (slug, focus) {
            if (!panels[slug]) {
                return;
            }
            current = slug;
            tabs.forEach(function (tab) {
                var on = tab.getAttribute('data-eff-fm-tab') === slug;
                tab.classList.toggle('is-active', on);
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
                tab.tabIndex = on ? 0 : -1;
                if (on && focus) {
                    tab.focus();
                }
            });
            Object.keys(panels).forEach(function (key) {
                panels[key].classList.toggle('is-active', key === slug);
            });
        };
        var remember = function (slug, push) {
            try {
                var url = new URL(window.location.href);
                url.searchParams.set('onglet', slug);
                url.hash = '';
                window.history[push ? 'pushState' : 'replaceState'](null, '', url.toString());
            } catch (e) {
                // Adresse non modifiable : l'onglet reste affiché, sans lien partageable.
            }
        };
        // Ouvre l'onglet (et le <details> fermé) qui contient la cible d'une ancre, puis y fait défiler.
        var revealAnchor = function () {
            var id = decodeURIComponent(window.location.hash.replace('#', ''));
            if (id === '' || /^eff-dlg-/i.test(id)) {
                return false;
            }
            var target = document.getElementById(id);
            var panel = target ? target.closest('[data-eff-fm-panel]') : null;
            if (!target || !panel) {
                return false;
            }
            if (panel.getAttribute('data-eff-fm-panel') !== current) {
                show(panel.getAttribute('data-eff-fm-panel'), false);
            }
            var details = target.closest('details');
            if (details && !details.open) {
                details.open = true;
            }
            target.scrollIntoView();
            return true;
        };

        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function (event) {
                if (event.ctrlKey || event.metaKey || event.shiftKey || event.button > 0) {
                    return;
                }
                event.preventDefault();
                var slug = tab.getAttribute('data-eff-fm-tab');
                show(slug, false);
                remember(slug, true);
            });
            tab.addEventListener('keydown', function (event) {
                var next = -1;
                if (event.key === 'ArrowRight') {
                    next = (index + 1) % tabs.length;
                } else if (event.key === 'ArrowLeft') {
                    next = (index - 1 + tabs.length) % tabs.length;
                } else if (event.key === 'Home') {
                    next = 0;
                } else if (event.key === 'End') {
                    next = tabs.length - 1;
                } else if (event.key === ' ') {
                    event.preventDefault();
                    tab.click();
                    return;
                }
                if (next < 0) {
                    return;
                }
                event.preventDefault();
                var slug = tabs[next].getAttribute('data-eff-fm-tab');
                show(slug, true);
                remember(slug, false);
            });
        });

        var serverTab = tabs.filter(function (tab) { return tab.classList.contains('is-active'); })[0] || tabs[0];
        show(serverTab.getAttribute('data-eff-fm-tab'), false);
        window.addEventListener('hashchange', revealAnchor);
        window.addEventListener('popstate', function () {
            var params = new URL(window.location.href).searchParams;
            // ?ouvrir=suivi (vue équipe, tâches) ouvre l'onglet « Suivi du dossier » quand aucun onglet n'est demandé.
            var wanted = params.get('onglet') || (params.get('ouvrir') === 'suivi' ? 'suivi' : null);
            show(wanted && panels[wanted] ? wanted : tabs[0].getAttribute('data-eff-fm-tab'), false);
        });
        revealAnchor();
    }

    function init() {
        initDialogs();
        initFicheTabs();
        initCivilDialog();
        openDialogFromHash();
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
