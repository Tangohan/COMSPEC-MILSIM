(function () {
    'use strict';

    function normalize(text) {
        return (text || '').toString().trim().toLowerCase();
    }

    function wireSearch(input) {
        var tableId = input.getAttribute('data-eff-search-table');
        var table = tableId ? document.getElementById(tableId) : null;
        if (!table) {
            return;
        }
        var tbody = table.tBodies[0];
        if (!tbody) {
            return;
        }
        input.addEventListener('input', function () {
            var q = normalize(input.value);
            var rows = tbody.querySelectorAll('tr');
            var visible = 0;
            rows.forEach(function (row) {
                var haystack = normalize(row.getAttribute('data-eff-search') || row.textContent);
                var match = q === '' || haystack.indexOf(q) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) {
                    visible++;
                }
            });
            var counter = input.getAttribute('data-eff-search-count');
            var counterEl = counter ? document.getElementById(counter) : null;
            if (counterEl) {
                counterEl.textContent = String(visible);
            }
        });
    }

    function wireSort(table) {
        var headers = table.querySelectorAll('th[data-eff-sort]');
        if (!headers.length) {
            return;
        }
        var tbody = table.tBodies[0];
        headers.forEach(function (th, index) {
            th.style.cursor = 'pointer';
            th.addEventListener('click', function () {
                var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                var dir = th.getAttribute('data-eff-sort-dir') === 'asc' ? 'desc' : 'asc';
                headers.forEach(function (other) {
                    other.removeAttribute('data-eff-sort-dir');
                });
                th.setAttribute('data-eff-sort-dir', dir);
                rows.sort(function (a, b) {
                    var av = normalize((a.children[index] || {}).textContent);
                    var bv = normalize((b.children[index] || {}).textContent);
                    var cmp = av.localeCompare(bv, 'fr', { numeric: true });
                    return dir === 'asc' ? cmp : -cmp;
                });
                rows.forEach(function (row) {
                    tbody.appendChild(row);
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-eff-search-table]').forEach(wireSearch);
        document.querySelectorAll('table[data-eff-sortable]').forEach(wireSort);
    });
})();
