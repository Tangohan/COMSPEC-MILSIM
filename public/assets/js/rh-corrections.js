/*
 * Corrections de fiches : sélection pour le traitement par lot.
 * La case « Tout sélectionner » coche toutes les demandes, le compteur suit, les boutons de lot restent
 * inactifs sans sélection, et le motif devient obligatoire quand on refuse la sélection.
 * Sans ce script, chaque demande reste traitable une par une.
 */
(function () {
    'use strict';
    var form = document.querySelector('[data-rh-batch]');
    if (!form) {
        return;
    }
    var all = form.querySelector('[data-rh-batch-all]');
    var counter = form.querySelector('[data-rh-batch-n]');
    var goButtons = form.querySelectorAll('[data-rh-batch-go]');
    var reason = form.querySelector('[data-rh-batch-reason]');
    var boxes = function () {
        return document.querySelectorAll('[data-rh-batch-one]');
    };

    function refresh() {
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
        goButtons.forEach(function (button) {
            button.disabled = n === 0;
        });
        if (all) {
            all.checked = n > 0 && n === list.length;
            all.indeterminate = n > 0 && n < list.length;
        }
    }

    if (all) {
        all.addEventListener('change', function () {
            boxes().forEach(function (box) {
                box.checked = all.checked;
            });
            refresh();
        });
    }
    document.addEventListener('change', function (event) {
        if (event.target && event.target.matches && event.target.matches('[data-rh-batch-one]')) {
            refresh();
        }
    });
    goButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (reason) {
                reason.required = button.hasAttribute('data-rh-batch-reject');
            }
        });
    });
    refresh();
})();
