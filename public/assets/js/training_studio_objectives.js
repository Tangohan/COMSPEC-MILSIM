/**
 * Listes dynamiques d’objectifs pédagogiques (studio formation).
 */
(function () {
  'use strict';

  function initScope(scope) {
    var list = scope.querySelector('[data-lms-objectives-list]');
    var addBtn = scope.querySelector('[data-lms-objective-add]');
    if (!list || !addBtn) {
      return;
    }

    addBtn.addEventListener('click', function () {
      var rows = list.querySelectorAll('[data-lms-objective-row]');
      var proto = rows.length ? rows[rows.length - 1] : null;
      if (!proto) {
        return;
      }
      var nu = proto.cloneNode(true);
      nu.querySelectorAll('input[type="text"]').forEach(function (inp) {
        inp.value = '';
      });
      list.appendChild(nu);
    });

    list.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-lms-objective-remove]');
      if (!btn) {
        return;
      }
      var row = btn.closest('[data-lms-objective-row]');
      if (!row || !list.contains(row)) {
        return;
      }
      var rows = list.querySelectorAll('[data-lms-objective-row]');
      if (rows.length <= 1) {
        row.querySelectorAll('input[type="text"]').forEach(function (inp) {
          inp.value = '';
        });
        return;
      }
      row.remove();
    });
  }

  function init() {
    document.querySelectorAll('[data-lms-objectives-scope]').forEach(initScope);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
