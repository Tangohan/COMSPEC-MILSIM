(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var bar = document.querySelector('[data-eff-bulk-bar]');
        var selectAll = document.querySelector('[data-eff-bulk-all]');
        var submitBtns = document.querySelectorAll('[data-eff-bulk-submit]');
        var countEl = document.querySelector('[data-eff-bulk-count]');
        var checks = document.querySelectorAll('.eff-bulk-check');
        if (!bar || checks.length === 0) {
            return;
        }

        function refresh() {
            var checked = document.querySelectorAll('.eff-bulk-check:checked').length;
            if (countEl) {
                countEl.textContent = checked + ' sélectionné(s)';
            }
            submitBtns.forEach(function (btn) {
                btn.disabled = checked === 0;
            });
            if (selectAll) {
                selectAll.checked = checked > 0 && checked === checks.length;
            }
        }

        checks.forEach(function (cb) {
            cb.addEventListener('change', refresh);
        });

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checks.forEach(function (cb) {
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
