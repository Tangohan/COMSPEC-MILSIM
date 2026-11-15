/*
 * Suivi du dossier des membres (Effectifs) : sous-onglets, filtre du fil d'étapes, tiroir de détail, fenêtres d'édition
 * et sélection de la relance groupée. Sans JavaScript, le serveur affiche déjà tout (filtres envoyés en GET).
 */
(function () {
    'use strict';
    if (window.__effSuiviInit) {
        return;
    }
    window.__effSuiviInit = true;

    function normalize(text) {
        return String(text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    function openDialog(dialog) {
        if (dialog && typeof dialog.showModal === 'function' && !dialog.open) {
            dialog.showModal();
        }
    }

    function initDialogs() {
        document.addEventListener('click', function (event) {
            var closer = event.target.closest('[data-sv-close]');
            if (closer) {
                var parent = closer.closest('dialog');
                if (parent) {
                    event.preventDefault();
                    parent.close();
                }
                return;
            }
            // Clic sur le fond : on ferme (la boîte elle-même couvre toute sa surface, le fond est l'élément dialog).
            if (event.target.tagName === 'DIALOG' && event.target.hasAttribute('data-sv-dialog')) {
                var rect = event.target.getBoundingClientRect();
                var inside = event.clientX >= rect.left && event.clientX <= rect.right
                    && event.clientY >= rect.top && event.clientY <= rect.bottom;
                if (!inside) {
                    event.target.close();
                }
            }
        });
    }

    function initTabs(root) {
        var tabs = root.querySelectorAll('[data-sv-tab]');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var wanted = tab.getAttribute('data-sv-tab');
                tabs.forEach(function (t) {
                    var on = t === tab;
                    t.classList.toggle('is-active', on);
                    t.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                root.querySelectorAll('[data-sv-panel]').forEach(function (panel) {
                    var on = panel.getAttribute('data-sv-panel') === wanted;
                    panel.hidden = !on;
                    panel.classList.toggle('is-active', on);
                });
            });
        });
    }

    function initFilter(root) {
        var form = root.querySelector('[data-sv-filter]');
        if (!form) {
            return;
        }
        root.setAttribute('data-sv-js', '1');
        var q = form.querySelector('[data-sv-q]');
        var track = form.querySelector('[data-sv-track]');
        var status = form.querySelector('[data-sv-status]');
        var result = root.querySelector('[data-sv-result]');
        var steps = root.querySelectorAll('[data-sv-step]');
        var groups = root.querySelectorAll('[data-sv-track-group]');
        var apply = function () {
            var text = normalize(q ? q.value.trim() : '');
            var tr = track ? track.value : '';
            var st = status ? status.value : '';
            var shown = 0;
            steps.forEach(function (step) {
                var ok = true;
                if (tr !== '' && step.getAttribute('data-track') !== tr) {
                    ok = false;
                }
                if (ok && st === 'open' && step.getAttribute('data-open') !== '1') {
                    ok = false;
                } else if (ok && st !== '' && st !== 'open') {
                    ok = step.getAttribute('data-status') === st || (st === 'overdue' && step.getAttribute('data-late') === '1');
                }
                if (ok && text !== '' && normalize(step.getAttribute('data-text')).indexOf(text) === -1) {
                    ok = false;
                }
                step.hidden = !ok;
                shown += ok ? 1 : 0;
            });
            groups.forEach(function (group) {
                group.hidden = group.querySelector('[data-sv-step]:not([hidden])') === null;
            });
            if (result) {
                var filtered = text !== '' || tr !== '' || st !== '';
                result.textContent = filtered
                    ? (shown === 0 ? 'Aucune étape ne correspond.' : shown + (shown > 1 ? ' étapes affichées.' : ' étape affichée.'))
                    : '';
            }
        };
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            apply();
        });
        [q, track, status].forEach(function (el) {
            if (el) {
                el.addEventListener(el.tagName === 'INPUT' ? 'input' : 'change', apply);
            }
        });
        apply();
    }

    var anchorOf = function (key) {
        return 'etape-' + String(key).replace(/[^a-z0-9]+/gi, '-').replace(/^-+|-+$/g, '');
    };

    function initDrawer(root) {
        var drawer = document.getElementById('sv-drawer');
        if (!drawer) {
            return;
        }
        var body = drawer.querySelector('[data-sv-drawer-body]');
        var title = drawer.querySelector('#sv-drawer-title');
        var open = function (key) {
            var tpl = document.getElementById('sv-tpl-' + anchorOf(key));
            if (!tpl) {
                return;
            }
            body.innerHTML = '';
            body.appendChild(tpl.content.cloneNode(true));
            title.textContent = tpl.getAttribute('data-title') || 'Détail de l’étape';
            openDialog(drawer);
        };
        root.addEventListener('click', function (event) {
            var btn = event.target.closest('[data-sv-open]');
            if (btn) {
                event.preventDefault();
                open(btn.getAttribute('data-sv-open'));
            }
        });
        // Lien direct #etape-… : ouvre le détail de l'étape.
        var fromHash = function () {
            var id = decodeURIComponent(window.location.hash.replace('#', ''));
            var tpl = id ? document.getElementById('sv-tpl-' + id) : null;
            if (tpl) {
                var li = document.getElementById(id);
                if (li) {
                    li.scrollIntoView({ block: 'center' });
                }
            }
        };
        fromHash();
        window.addEventListener('hashchange', fromHash);
    }

    function initActions() {
        document.addEventListener('click', function (event) {
            var btn = event.target.closest('[data-sv-act]');
            if (!btn) {
                return;
            }
            var dialog = document.getElementById('sv-dlg-' + btn.getAttribute('data-sv-act'));
            if (!dialog) {
                return;
            }
            event.preventDefault();
            var key = dialog.querySelector('[data-sv-key-input]');
            if (key) {
                key.value = btn.getAttribute('data-sv-key') || '';
            }
            var label = dialog.querySelector('[data-sv-step-title]');
            if (label) {
                label.textContent = btn.getAttribute('data-sv-title') || '';
            }
            var due = dialog.querySelector('[data-sv-due-input]');
            if (due) {
                var current = btn.getAttribute('data-sv-due') || '';
                due.value = current && current >= due.min ? current : '';
            }
            var owner = dialog.querySelector('[data-sv-owner-select]');
            if (owner && btn.hasAttribute('data-sv-owner')) {
                var wanted = btn.getAttribute('data-sv-owner');
                owner.value = Array.prototype.some.call(owner.options, function (o) { return o.value === wanted; }) ? wanted : '0';
            }
            var reason = dialog.querySelector('[data-sv-reason]');
            if (reason && btn.getAttribute('data-sv-act') === 'complete') {
                var force = btn.getAttribute('data-sv-force') === '1';
                reason.required = force;
                reason.minLength = force ? 3 : 0;
            }
            var drawer = document.getElementById('sv-drawer');
            if (drawer && drawer.open && dialog !== drawer) {
                drawer.close();
            }
            openDialog(dialog);
            var first = dialog.querySelector('input:not([type=hidden]), select, textarea');
            if (first) {
                first.focus();
            }
        });
        document.addEventListener('submit', function (event) {
            var form = event.target.closest('[data-sv-form]');
            if (form) {
                var submit = form.querySelector('button[type=submit]');
                if (submit) {
                    // Un seul envoi : pas de double relance ni de double validation.
                    window.setTimeout(function () { submit.disabled = true; }, 0);
                }
            }
        });
    }

    function initBulk() {
        var form = document.querySelector('[data-sv-bulk]');
        if (!form) {
            return;
        }
        var max = parseInt(form.getAttribute('data-max') || '25', 10);
        var items = document.querySelectorAll('[data-sv-bulk-item]');
        var all = document.querySelector('[data-sv-bulk-all]');
        var count = form.querySelector('[data-sv-bulk-count]');
        var plural = form.querySelector('[data-sv-bulk-plural]');
        var submit = form.querySelector('[data-sv-bulk-submit]');
        var refresh = function () {
            var n = 0;
            items.forEach(function (i) { n += i.checked ? 1 : 0; });
            count.textContent = String(n);
            if (plural) {
                plural.hidden = n < 2;
            }
            submit.disabled = n === 0;
            submit.textContent = n > max ? 'Relancer les ' + max + ' premiers' : 'Relancer la sélection';
            if (all) {
                var enabled = Array.prototype.filter.call(items, function (i) { return !i.disabled; });
                all.checked = enabled.length > 0 && enabled.every(function (i) { return i.checked; });
            }
        };
        items.forEach(function (i) { i.addEventListener('change', refresh); });
        if (all) {
            all.addEventListener('change', function () {
                items.forEach(function (i) { if (!i.disabled) { i.checked = all.checked; } });
                refresh();
            });
        }
        form.addEventListener('submit', function (event) {
            var n = parseInt(count.textContent, 10) || 0;
            if (n === 0) {
                event.preventDefault();
                return;
            }
        });
        refresh();
    }

    function init() {
        initDialogs();
        initActions();
        document.querySelectorAll('[data-sv-root]').forEach(function (root) {
            initTabs(root);
            initFilter(root);
            initDrawer(root);
        });
        initBulk();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
