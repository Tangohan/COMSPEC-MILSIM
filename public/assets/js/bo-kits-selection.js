/**
 * Page Kits d’accès : case visible, état de carte et compteur du pied de page.
 */
(function () {
  'use strict';

  function hintText(count) {
    if (count < 1) {
      return 'Aucun kit coché pour l’instant.';
    }
    var many = count > 1;

    return count + ' kit' + (many ? 's' : '') + ' sélectionné' + (many ? 's' : '') + ' — multi-sélection possible.';
  }

  function refresh(form) {
    var boxes = form.querySelectorAll('input[type="checkbox"][name="kit_ids[]"]');
    var count = 0;
    boxes.forEach(function (box) {
      var card = box.closest('.bo-kits__card');
      if (card) {
        card.classList.toggle('is-on', box.checked);
      }
      if (box.checked) {
        count += 1;
      }
    });
    var hint = form.querySelector('[data-bo-kits-hint]');
    if (hint) {
      hint.textContent = hintText(count);
    }
  }

  function bind(form) {
    if (!form || form.getAttribute('data-bo-kits-ready') === '1') {
      return;
    }
    form.setAttribute('data-bo-kits-ready', '1');
    form.addEventListener('change', function () {
      refresh(form);
    });
    refresh(form);
  }

  function init() {
    document.querySelectorAll('form.bo-kits__form').forEach(bind);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
