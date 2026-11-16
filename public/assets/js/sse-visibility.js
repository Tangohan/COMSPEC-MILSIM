/*
 * SSE › Visibilité par niveau : colore le tableau « rubriques × niveaux » selon le choix de chaque ligne et rétablit les niveaux
 * livrés. Le choix est un bouton radio : sans ce script, le formulaire envoie les mêmes valeurs. Aucun texte n'est écrit ici.
 */
(function () {
    'use strict';

    function paint(root) {
        var totals = {};
        Array.prototype.forEach.call(root.querySelectorAll('[data-svl-row]'), function (row) {
            var checked = row.querySelector('input[type="radio"]:checked');
            var rank = checked ? parseInt(checked.getAttribute('data-rank'), 10) : 99;
            Array.prototype.forEach.call(row.querySelectorAll('[data-svl-cell]'), function (cell) {
                var r = parseInt(cell.getAttribute('data-rank'), 10);
                var input = cell.querySelector('input[type="radio"]');
                var state = r >= rank ? 'on' : (input && input.disabled ? 'locked' : 'off');
                cell.classList.remove('is-on', 'is-off', 'is-locked');
                cell.classList.add('is-' + state);
                cell.classList.toggle('is-picked', !!(input && input.checked));
                if (r >= rank) { totals[r] = (totals[r] || 0) + 1; }
            });
        });
        Array.prototype.forEach.call(root.querySelectorAll('[data-svl-total]'), function (cell) {
            var n = cell.querySelector('[data-svl-n]');
            if (n) { n.textContent = String(totals[parseInt(cell.getAttribute('data-rank'), 10)] || 0); }
        });
    }

    document.addEventListener('change', function (e) {
        var t = e.target;
        if (t instanceof Element && t.matches('[data-svl] input[type="radio"]')) { paint(t.closest('[data-svl]')); }
    });

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!(t instanceof Element)) { return; }
        var reset = t.closest('[data-svl-reset]');
        if (!reset) { return; }
        var root = reset.closest('[data-svl]');
        Array.prototype.forEach.call(root.querySelectorAll('[data-svl-row]'), function (row) {
            var want = row.getAttribute('data-default');
            var input = row.querySelector('input[type="radio"][value="' + want + '"]');
            if (input && !input.disabled) { input.checked = true; }
        });
        paint(root);
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-svl]'), paint);
})();
