/* Portail SSE — Générer du contenu (sans dépendance) :
 *  - la valeur d'un curseur [data-gen-range] s'affiche dans sa sortie [data-gen-out="id"] ;
 *  - six kits au plus : les cases restantes se grisent ;
 *  - sans réseau coché, la part de population civile est figée à 100 % ;
 *  - un formulaire [data-gen-delete] demande confirmation avant d'envoyer (suppression d'un lot). */
(function () {
  'use strict';
  var form = document.querySelector('[data-gen-form]');
  var each = function (list, fn) { Array.prototype.forEach.call(list, fn); };

  each(document.querySelectorAll('[data-gen-range]'), function (range) {
    var out = document.querySelector('[data-gen-out="' + range.id + '"]');
    var show = function () { if (out) { out.textContent = range.value; } };
    range.addEventListener('input', show);
    show();
  });

  if (form) {
    var kits = form.querySelectorAll('input[name="kits[]"]');
    var themes = form.querySelectorAll('input[name="themes[]"]');
    var civil = form.querySelector('input[name="civil_pct"]');
    var sync = function () {
      var picked = 0;
      each(kits, function (k) { if (k.checked) { picked++; } });
      each(kits, function (k) { k.disabled = !k.checked && picked >= 6; });
      var network = 0;
      each(themes, function (t) { if (t.checked) { network++; } });
      if (civil) {
        civil.disabled = network === 0;
        var label = civil.closest('.fx-gen-sl');
        if (label) { label.classList.toggle('off', network === 0); }
      }
    };
    form.addEventListener('change', sync);
    sync();
  }

  each(document.querySelectorAll('form[data-gen-delete]'), function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); }
    });
  });
})();
