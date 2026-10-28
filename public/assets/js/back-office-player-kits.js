/**
 * Back-office — Équipement des joueurs › Kits portés (views/admin/organization/partials/game_player_kits.php).
 * Filtre texte sur le joueur, son rôle et le contenu du kit.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-pk]');
  if (!root) {
    return;
  }
  var input = root.querySelector('[data-pk-filter]');
  var none = root.querySelector('[data-pk-none]');
  if (!input) {
    return;
  }

  function norm(s) {
    return String(s || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '');
  }

  input.addEventListener('input', function () {
    var q = norm(input.value.trim());
    var shown = 0;
    root.querySelectorAll('[data-pk-row]').forEach(function (row) {
      var ok = q === '' || norm(row.textContent).indexOf(q) !== -1;
      row.hidden = !ok;
      if (ok) {
        shown++;
      }
    });
    if (none) {
      none.hidden = shown > 0;
    }
  });
})();
