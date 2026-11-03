/* Academy — tableau de suivi (views/academy/tracker.php).
   Sélection des lignes pour les actions groupées, choix des formations en colonnes, fenêtre de dispense, impression.
   Aucun texte ici : libellés et valeurs viennent de la vue (FR/EN) ; le serveur revérifie chaque action.
   Fenêtres (ouvrir / fermer) : academy-queue.js. */
(function () {
  'use strict';

  document.querySelectorAll('[data-trk]').forEach(function (root) {
    var picks = Array.prototype.slice.call(root.querySelectorAll('[data-trk-pick]'));
    var counter = root.querySelector('[data-trk-count]');
    var needs = root.querySelectorAll('[data-trk-needs-pick]');
    var all = root.querySelector('[data-trk-all]');
    var refresh = function () {
      var n = picks.filter(function (p) { return p.checked; }).length;
      if (counter) counter.textContent = String(n);
      needs.forEach(function (b) { b.disabled = n === 0; });
      if (all) {
        all.checked = n > 0 && n === picks.length;
        all.indeterminate = n > 0 && n < picks.length;
      }
    };
    picks.forEach(function (p) { p.addEventListener('change', refresh); });
    if (all) {
      all.addEventListener('change', function () {
        picks.forEach(function (p) { p.checked = all.checked; });
        refresh();
      });
    }
    refresh();

    // Formations en colonnes : les cases cochées forment le paramètre « cols ».
    var cols = root.querySelector('[data-trk-cols]');
    if (cols) {
      cols.addEventListener('submit', function () {
        var keys = Array.prototype.slice.call(cols.querySelectorAll('[data-trk-col]:checked')).map(function (c) { return c.value; });
        cols.querySelector('[data-trk-cols-value]').value = keys.join(',');
      });
    }

    // Une seule cellule ouverte à la fois.
    root.addEventListener('toggle', function (event) {
      var opened = event.target;
      if (!opened.classList || !opened.classList.contains('trk-pop') || !opened.open) return;
      root.querySelectorAll('details.trk-pop[open]').forEach(function (d) { if (d !== opened) d.open = false; });
    }, true);

    // Dispense : la fenêtre partagée reçoit la cellule (membre|cours) et son intitulé.
    var dialog = document.getElementById('trk-exempt');
    root.addEventListener('click', function (event) {
      var exempt = event.target.closest('[data-trk-exempt]');
      if (exempt && dialog && typeof dialog.showModal === 'function') {
        dialog.querySelector('[data-trk-exempt-cell]').value = exempt.getAttribute('data-trk-exempt');
        dialog.querySelector('[data-trk-exempt-title]').textContent = exempt.getAttribute('data-trk-exempt-name') || '';
        dialog.showModal();
        var field = dialog.querySelector('textarea');
        if (field) field.focus();
        return;
      }
      if (event.target.closest('[data-trk-print]')) {
        window.print();
      }
    });
  });
})();
