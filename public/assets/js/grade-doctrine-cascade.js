/**
 * Filtre catégorie et grade selon la nationalité / doctrine (FR ou US).
 * Racine : [data-grade-doctrine]
 */
(function () {
  function snapshot(select) {
    var list = [];
    Array.prototype.forEach.call(select.options, function (opt) {
      list.push(opt.cloneNode(true));
    });
    return list;
  }

  function refill(select, clones, keepFn) {
    var prev = select.value;
    select.innerHTML = '';
    clones.forEach(function (src) {
      if (!keepFn(src)) {
        return;
      }
      select.appendChild(src.cloneNode(true));
    });
    select.value = prev;
    if (select.value !== prev) {
      select.value = '';
    }
  }

  function bind(root) {
    if (root.getAttribute('data-grade-doctrine-bound') === '1') {
      return;
    }
    var nation = root.querySelector('[data-grade-doctrine-nation]');
    var category = root.querySelector('[data-grade-doctrine-category]');
    var grade = root.querySelector('[data-grade-doctrine-grade]');
    if (!nation || !grade) {
      return;
    }
    root.setAttribute('data-grade-doctrine-bound', '1');

    var gradeClones = snapshot(grade);
    var categoryClones = category ? snapshot(category) : [];

    function country() {
      return String(nation.value || '').toUpperCase();
    }

    function apply() {
      var c = country();
      var k = category ? String(category.value || '') : '';
      var currentGrade = grade.value;
      var catKeep = {};

      refill(grade, gradeClones, function (opt) {
        if (!opt.value) {
          return true;
        }
        if (c === '') {
          return opt.value === currentGrade;
        }
        var oc = String(opt.getAttribute('data-country') || '').toUpperCase();
        var ok = String(opt.getAttribute('data-category') || '');
        if (oc === c && ok) {
          catKeep[ok] = true;
        }
        return oc === c && (k === '' || ok === k);
      });

      if (category) {
        refill(category, categoryClones, function (opt) {
          if (!opt.value) {
            return true;
          }
          if (c === '') {
            return opt.value === k;
          }
          return !!catKeep[opt.value];
        });
        if (k !== '' && category.value === '') {
          apply();
          return;
        }
      }
    }

    nation.addEventListener('change', function () {
      if (category) {
        category.value = '';
      }
      grade.value = '';
      apply();
    });
    if (category) {
      category.addEventListener('change', apply);
    }
    apply();
  }

  function init() {
    document.querySelectorAll('[data-grade-doctrine]').forEach(bind);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
