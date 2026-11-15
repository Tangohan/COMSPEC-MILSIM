/*
 * Bureau SSE : prépare les tableaux pour l'affichage en cartes sur téléphone.
 * Chaque cellule reçoit l'intitulé de sa colonne (data-label) ; le tableau reçoit data-sse-stack.
 * Un tableau clé / valeur (intitulés en tête de ligne) ou marqué data-sse-keep reste tel quel.
 */
(function () {
    'use strict';
    function prepare(table) {
        if (table.hasAttribute('data-sse-keep') || table.hasAttribute('data-sse-stack')) { return; }
        var heads = table.querySelectorAll('thead th');
        if (heads.length < 2 || table.querySelector('tbody th[scope="row"]')) { return; }
        var labels = [];
        for (var i = 0; i < heads.length; i++) {
            var span = parseInt(heads[i].getAttribute('colspan') || '1', 10) || 1;
            for (var k = 0; k < span; k++) { labels.push((heads[i].textContent || '').trim()); }
        }
        var rows = table.querySelectorAll('tbody tr');
        for (var r = 0; r < rows.length; r++) {
            var cells = rows[r].children;
            for (var c = 0; c < cells.length; c++) {
                if (cells[c].tagName === 'TD' && labels[c]) { cells[c].setAttribute('data-label', labels[c]); }
            }
        }
        table.setAttribute('data-sse-stack', '');
    }
    function run() {
        var tables = document.querySelectorAll('.sx-main .table-wrap table');
        for (var i = 0; i < tables.length; i++) { prepare(tables[i]); }
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', run); } else { run(); }
})();
