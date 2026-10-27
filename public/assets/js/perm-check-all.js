/**
 * Cases « Tout cocher » des éditeurs de permissions.
 * - [data-perm-all] coche / décoche toutes les cases du formulaire ;
 * - [data-perm-group] coche / décoche les cases de son groupe ([data-perm-scope]).
 * Les cases maîtresses passent en état intermédiaire quand une partie seulement est cochée.
 */
(function () {
    'use strict';

    function boxes(root) {
        return Array.prototype.slice.call(root.querySelectorAll('input[type="checkbox"][name="permission_ids[]"]'));
    }

    function toggleable(list) {
        return list.filter(function (b) { return !b.disabled && !b.hasAttribute('data-perm-locked'); });
    }

    function sync(master, list) {
        var editable = toggleable(list);
        var on = editable.filter(function (b) { return b.checked; }).length;
        master.checked = editable.length > 0 && on === editable.length;
        master.indeterminate = on > 0 && on < editable.length;
    }

    function init(form) {
        var all = form.querySelector('[data-perm-all]');
        var groups = Array.prototype.slice.call(form.querySelectorAll('[data-perm-group]'));
        var refresh = function () {
            if (all) {
                sync(all, boxes(form));
            }
            groups.forEach(function (g) {
                var scope = g.closest('[data-perm-scope]');
                if (scope) {
                    sync(g, boxes(scope));
                }
            });
        };
        if (all) {
            all.addEventListener('change', function () {
                toggleable(boxes(form)).forEach(function (b) { b.checked = all.checked; });
                refresh();
            });
        }
        groups.forEach(function (g) {
            g.addEventListener('change', function () {
                var scope = g.closest('[data-perm-scope]');
                if (scope) {
                    toggleable(boxes(scope)).forEach(function (b) { b.checked = g.checked; });
                }
                refresh();
            });
        });
        form.addEventListener('change', function (e) {
            if (e.target && e.target.name === 'permission_ids[]') {
                refresh();
            }
        });
        refresh();
    }

    function boot() {
        Array.prototype.slice.call(document.querySelectorAll('form[data-perm-form]')).forEach(init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
