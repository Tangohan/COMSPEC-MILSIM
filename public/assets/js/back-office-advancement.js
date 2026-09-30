(function () {
  function enhanceSelect(select) {
    if (!select || select.dataset.advEnhanced === '1' || select.disabled) {
      return;
    }
    select.dataset.advEnhanced = '1';
    var wrap = document.createElement('div');
    wrap.className = 'adv-select';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.style.position = 'absolute';
    select.style.opacity = '0';
    select.style.pointerEvents = 'none';
    select.style.height = '0';
    select.style.width = '0';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'adv-select__btn';
    var placeholder = select.getAttribute('data-placeholder') || 'Choisir';
    btn.textContent = selectedLabel(select) || placeholder;
    wrap.appendChild(btn);

    var panel = document.createElement('div');
    panel.className = 'adv-select__panel';
    var search = document.createElement('input');
    search.type = 'search';
    search.className = 'adv-select__search';
    search.placeholder = 'Rechercher…';
    search.setAttribute('autocomplete', 'off');
    var list = document.createElement('div');
    list.className = 'adv-select__list';
    panel.appendChild(search);
    panel.appendChild(list);
    wrap.appendChild(panel);

    function selectedLabel(el) {
      var opt = el.options[el.selectedIndex];
      if (!opt || opt.value === '') {
        return '';
      }
      return (opt.textContent || '').trim();
    }

    function rebuild(filter) {
      list.innerHTML = '';
      var q = (filter || '').toLowerCase();
      var shown = 0;
      Array.prototype.forEach.call(select.options, function (opt, index) {
        var label = (opt.textContent || '').trim();
        if (opt.parentNode && opt.parentNode.tagName === 'OPTGROUP') {
          label = opt.parentNode.label + ' · ' + label;
        }
        if (q && label.toLowerCase().indexOf(q) === -1 && String(opt.value).toLowerCase().indexOf(q) === -1) {
          return;
        }
        var item = document.createElement('button');
        item.type = 'button';
        item.textContent = label || '—';
        if (opt.disabled || opt.value === '' && index === 0 && q) {
          item.disabled = opt.disabled;
        }
        if (index === select.selectedIndex) {
          item.classList.add('is-active');
        }
        item.addEventListener('click', function () {
          select.selectedIndex = index;
          select.dispatchEvent(new Event('change', { bubbles: true }));
          btn.textContent = selectedLabel(select) || placeholder;
          wrap.classList.remove('is-open');
        });
        list.appendChild(item);
        shown++;
      });
      if (shown === 0) {
        var empty = document.createElement('div');
        empty.className = 'adv-select__empty';
        empty.textContent = 'Aucun résultat';
        list.appendChild(empty);
      }
    }

    btn.addEventListener('click', function () {
      wrap.classList.toggle('is-open');
      if (wrap.classList.contains('is-open')) {
        rebuild('');
        search.value = '';
        search.focus();
      }
    });
    search.addEventListener('input', function () {
      rebuild(search.value);
    });
    document.addEventListener('click', function (event) {
      if (!wrap.contains(event.target)) {
        wrap.classList.remove('is-open');
      }
    });
  }

    document.querySelectorAll('select.adv-search').forEach(enhanceSelect);

    var body = document.getElementById('adv-grade-body');
    if (body) {
      var dragged = null;
      body.addEventListener('dragstart', function (event) {
        dragged = event.target.closest('[data-grade-row]');
        if (dragged) dragged.classList.add('is-dragging');
      });
      body.addEventListener('dragend', function () {
        if (dragged) dragged.classList.remove('is-dragging');
        dragged = null;
        renumberByFiliere();
      });
      body.addEventListener('dragover', function (event) {
        event.preventDefault();
        var target = event.target.closest('[data-grade-row]');
        if (!dragged || !target || target === dragged) return;
        var rect = target.getBoundingClientRect();
        var after = event.clientY > rect.top + rect.height / 2;
        body.insertBefore(dragged, after ? target.nextSibling : target);
      });
      function renumberByFiliere() {
        var counts = {};
        body.querySelectorAll('[data-grade-row]').forEach(function (row) {
          var key = row.getAttribute('data-filiere') || '0';
          counts[key] = (counts[key] || 0) + 1;
          var cell = row.querySelector('.adv-order');
          if (cell) cell.textContent = String(counts[key]);
        });
      }
    }

    document.querySelectorAll('[data-adv-force]').forEach(function (box) {
      var article = box.closest('[data-adv-candidate]');
      if (!article) return;
      var area = article.querySelector('textarea[name*="[exceptional_reason]"]');
      function sync() {
        if (area) area.required = box.checked;
        article.classList.toggle('is-exception', box.checked);
      }
      box.addEventListener('change', sync);
      sync();
    });

    document.querySelectorAll('form[data-adv-publish]').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (form.getAttribute('data-adv-publish') !== '1') return;
        if (!window.confirm('Ce tableau contient un passage exceptionnel. Confirmer la publication ?')) {
          event.preventDefault();
        }
      });
    });

    var commissionForm = document.getElementById('adv-commission-form');
    if (commissionForm) {
      commissionForm.addEventListener('submit', function (event) {
        var ranks = [];
        commissionForm.querySelectorAll('[data-adv-rank]').forEach(function (input) {
          if (input.value !== '') ranks.push(Number(input.value));
        });
        var seen = {};
        var dup = ranks.some(function (n) {
          if (seen[n]) return true;
          seen[n] = true;
          return false;
        });
        if (dup && !window.confirm('Des rangs sont en double. Enregistrer quand même ?')) {
          event.preventDefault();
          return;
        }
        var missing = false;
        commissionForm.querySelectorAll('[data-adv-force]:checked').forEach(function (box) {
          var article = box.closest('[data-adv-candidate]');
          var area = article && article.querySelector('textarea[name*="[exceptional_reason]"]');
          if (!area || area.value.trim().length < 8) missing = true;
        });
        if (missing) {
          event.preventDefault();
          window.alert('Chaque passage exceptionnel doit avoir un motif (au moins quelques mots).');
        }
      });
    }
})();
