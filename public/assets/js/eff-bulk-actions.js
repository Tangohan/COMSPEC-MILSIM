(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var bar = document.querySelector('[data-eff-bulk-bar]');
        var selectAll = document.querySelector('[data-eff-bulk-all]');
        var submitBtns = document.querySelectorAll('[data-eff-bulk-submit]');
        var countEl = document.querySelector('[data-eff-bulk-count]');
        if (!bar || document.querySelectorAll('.eff-bulk-check').length === 0) {
            return;
        }

        function refresh() {
            // Liste relue à chaque fois : une ligne peut être remplacée après un enregistrement sans rechargement.
            var checks = document.querySelectorAll('.eff-bulk-check');
            var checked = document.querySelectorAll('.eff-bulk-check:checked').length;
            if (countEl) {
                countEl.textContent = checked <= 1
                    ? checked + ' sélectionné'
                    : checked + ' sélectionnés';
            }
            submitBtns.forEach(function (btn) {
                btn.disabled = checked === 0;
            });
            // La barre d'actions groupées ne s'affiche en entier qu'avec une sélection.
            bar.classList.toggle('is-active', checked > 0);
            if (selectAll) {
                selectAll.checked = checked > 0 && checked === checks.length;
            }
        }

        document.addEventListener('ath:quiet-swap', refresh);
        document.addEventListener('change', function (e) {
            if (e.target && e.target.classList && e.target.classList.contains('eff-bulk-check')) {
                refresh();
            }
        });

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                document.querySelectorAll('.eff-bulk-check').forEach(function (cb) {
                    cb.checked = selectAll.checked;
                });
                refresh();
            });
        }

        var form = document.getElementById('eff-bulk-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                var checked = document.querySelectorAll('.eff-bulk-check:checked').length;
                if (checked === 0) {
                    e.preventDefault();
                    return;
                }
                var actionLabel = (e.submitter && e.submitter.textContent.trim()) || 'cette action';
                if (!window.confirm('Confirmer « ' + actionLabel + ' » pour ' + checked + ' membre(s) ?')) {
                    e.preventDefault();
                }
            });
        }

        refresh();
    });
})();
