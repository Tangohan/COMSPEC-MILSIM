/**
 * Accueil du back-office, panneau « Personnaliser » : glisser-déposer et flèches sans rechargement. L'ordre est
 * celui des champs order[] du formulaire, enregistré avec le bouton « Enregistrer ». Sans ce script, les flèches
 * envoient le formulaire.
 */
(function () {
  'use strict';

  var form = document.querySelector('[data-home-form]');
  var list = form ? form.querySelector('[data-home-list]') : null;
  if (!list) return;

  function items() { return Array.prototype.slice.call(list.querySelectorAll('[data-home-item]')); }
  function refresh() {
    var all = items();
    all.forEach(function (li, i) {
      var up = li.querySelector('[data-home-move="up"]');
      var down = li.querySelector('[data-home-move="down"]');
      if (up) up.disabled = i === 0;
      if (down) down.disabled = i === all.length - 1;
    });
  }

  list.addEventListener('click', function (e) {
    var b = e.target.closest('[data-home-move]');
    if (!b) return;
    e.preventDefault();
    var li = b.closest('[data-home-item]');
    if (b.getAttribute('data-home-move') === 'up' && li.previousElementSibling) {
      list.insertBefore(li, li.previousElementSibling);
    } else if (b.getAttribute('data-home-move') === 'down' && li.nextElementSibling) {
      list.insertBefore(li.nextElementSibling, li);
    }
    refresh();
    if (!b.disabled) b.focus();
  });

  form.addEventListener('click', function (e) {
    var b = e.target.closest('[data-sheet-confirm]');
    if (b && !window.confirm(b.getAttribute('data-sheet-confirm'))) e.preventDefault();
  });

  var dragged = null;
  items().forEach(function (li) {
    li.addEventListener('dragstart', function (e) {
      dragged = li;
      li.classList.add('is-dragging');
      if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', ''); }
    });
    li.addEventListener('dragover', function (e) {
      if (!dragged || dragged === li) return;
      e.preventDefault();
      var r = li.getBoundingClientRect();
      list.insertBefore(dragged, e.clientY > r.top + r.height / 2 ? li.nextElementSibling : li);
    });
    li.addEventListener('dragend', function () {
      li.classList.remove('is-dragging');
      dragged = null;
      refresh();
    });
  });
})();
