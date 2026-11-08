/*
 * Corrections de fiches : sélection pour le traitement par lot.
 * La case « Tout sélectionner » coche toutes les demandes, le compteur suit, les boutons de lot restent
 * inactifs sans sélection, et le motif devient obligatoire quand on refuse la sélection.
 * La file est remplacée après chaque décision (ath-quiet-form.js) : tout passe par document.
 * Sans ce script, chaque demande reste traitable une par une.
 */
(function () {
    'use strict';
    var boxes = function () {
        return document.querySelectorAll('[data-rh-batch-one]');
    };

    function refresh() {
        var form = document.querySelector('[data-rh-batch]');
        if (!form) {
            return;
        }
        var all = form.querySelector('[data-rh-batch-all]');
        var counter = form.querySelector('[data-rh-batch-n]');
        var list = boxes();
        var n = 0;
        list.forEach(function (box) {
            if (box.checked) {
                n++;
            }
        });
        if (counter) {
            counter.textContent = String(n);
        }
        form.querySelectorAll('[data-rh-batch-go]').forEach(function (button) {
            button.disabled = n === 0;
        });
        if (all) {
            all.checked = n > 0 && n === list.length;
            all.indeterminate = n > 0 && n < list.length;
        }
    }

    document.addEventListener('change', function (event) {
        var target = event.target;
        if (!target || !target.matches) {
            return;
        }
        if (target.matches('[data-rh-batch-all]')) {
            boxes().forEach(function (box) {
                box.checked = target.checked;
            });
        }
        if (target.matches('[data-rh-batch-all], [data-rh-batch-one]')) {
            refresh();
        }
    });
    document.addEventListener('click', function (event) {
        var button = event.target && event.target.closest ? event.target.closest('[data-rh-batch-go]') : null;
        var form = button ? button.closest('[data-rh-batch]') : null;
        var reason = form ? form.querySelector('[data-rh-batch-reason]') : null;
        if (reason) {
            reason.required = button.hasAttribute('data-rh-batch-reject');
        }
    });
    document.addEventListener('ath:quiet-swap', refresh);
    refresh();
})();
