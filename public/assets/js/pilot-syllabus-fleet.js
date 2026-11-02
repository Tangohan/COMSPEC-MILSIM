/**
 * Cursus pilote > Installer un cursus : recherche et filtres de la liste des appareils (nom ou classe Arma,
 * famille, pilotés seulement) et « tout cocher / tout décocher » par famille. Aucun texte affiché ici : les
 * messages (aucun résultat…) sont dans la page.
 */
(function () {
    'use strict';

    function norm(v) {
        return String(v || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    function setup(root) {
        var q = root.querySelector('[data-pcs-q]');
        var group = root.querySelector('[data-pcs-group]');
        var flown = root.querySelector('[data-pcs-flown]');
        var none = root.querySelector('[data-pcs-none]');
        var groups = Array.prototype.slice.call(root.querySelectorAll('[data-pcs-grp]'));

        function apply() {
            var term = norm(q ? q.value : '').trim();
            var g = group ? group.value : '';
            var onlyFlown = !!(flown && flown.checked);
            var shown = 0;
            groups.forEach(function (grp) {
                var inGroup = 0;
                grp.querySelectorAll('[data-pcs-ac]').forEach(function (ac) {
                    var ok = (g === '' || ac.getAttribute('data-group') === g)
                        && (!onlyFlown || ac.getAttribute('data-flown') === '1')
                        && (term === '' || norm(ac.getAttribute('data-search')).indexOf(term) !== -1);
                    ac.hidden = !ok;
                    if (ok) {
                        inGroup++;
                    }
                });
                grp.hidden = inGroup === 0;
                shown += inGroup;
            });
            if (none) {
                none.hidden = shown > 0;
            }
        }

        if (q) {
            q.addEventListener('input', apply);
        }
        if (group) {
            group.addEventListener('change', apply);
        }
        if (flown) {
            flown.addEventListener('change', apply);
        }
        root.addEventListener('click', function (ev) {
            var btn = ev.target.closest ? ev.target.closest('[data-pcs-check]') : null;
            if (!btn) {
                return;
            }
            var grp = btn.closest('[data-pcs-grp]');
            var on = btn.getAttribute('data-pcs-check') === 'all';
            if (grp) {
                grp.querySelectorAll('[data-pcs-ac]').forEach(function (ac) {
                    var box = ac.querySelector('input[type="checkbox"]');
                    if (box && !ac.hidden) {
                        box.checked = on;
                    }
                });
            }
        });
    }

    function init() {
        document.querySelectorAll('[data-pcs-fleet]').forEach(setup);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
