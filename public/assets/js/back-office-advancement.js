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
    document.querySelectorAll('form[data-adv-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!window.confirm(form.getAttribute('data-adv-confirm') || 'Confirmer ?')) {
          event.preventDefault();
        }
      });
    });

    // Assistant de création de campagne : une étape à la fois (sans script, tout reste visible).
    var wizard = document.querySelector('[data-advc-wizard]');
    if (wizard) {
      var steps = Array.prototype.slice.call(wizard.querySelectorAll('[data-advc-step]'));
      var nav = wizard.querySelector('[data-advc-nav]');
      var bar = wizard.querySelector('[data-advc-bar]');
      var prev = wizard.querySelector('[data-advc-prev]');
      var next = wizard.querySelector('[data-advc-next]');
      var where = wizard.querySelector('[data-advc-where]');
      var current = 1;
      var total = steps.length;

      function checkedGrades() {
        return Array.prototype.slice.call(wizard.querySelectorAll('[data-advc-grade]:checked'));
      }

      function syncQuotas() {
        var ids = {};
        checkedGrades().forEach(function (box) { ids[box.value] = true; });
        wizard.querySelectorAll('[data-advc-quota]').forEach(function (row) {
          row.hidden = !ids[row.getAttribute('data-advc-quota')];
        });
      }

      function fmt(iso) {
        if (!iso) return '—';
        var p = iso.split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso;
      }

      function node(tag, text, cls) {
        var el = document.createElement(tag);
        if (cls) el.className = cls;
        if (text !== undefined && text !== null) el.textContent = text;
        return el;
      }

      // Chaque libellé fixe est un nœud à part : la traduction de l’affichage s’y applique.
      function line(label, parts) {
        var row = document.createElement('div');
        row.appendChild(node('dt', label));
        var dd = document.createElement('dd');
        parts.forEach(function (part) { dd.appendChild(part); });
        row.appendChild(dd);
        return row;
      }

      function summary() {
        var box = wizard.querySelector('[data-advc-summary]');
        if (!box) return;
        box.innerHTML = '';
        var dl = node('dl', null, 'advc-summary__list');
        var grades = checkedGrades();
        var gradeParts = [];
        if (!grades.length) {
          gradeParts.push(node('span', 'Aucun grade coché', 'advc-error'));
        }
        grades.forEach(function (g) {
          var item = node('span', null, 'advc-summary__item');
          item.appendChild(node('b', g.getAttribute('data-label')));
          var quota = wizard.querySelector('input[name="quota[' + g.value + ']"]');
          if (quota && quota.value !== '') {
            item.appendChild(node('span', quota.value, 'advc-summary__num'));
            item.appendChild(node('span', 'place(s)'));
          } else {
            item.appendChild(node('span', 'places non limitées'));
          }
          item.appendChild(node('span', g.getAttribute('data-proposed'), 'advc-summary__num'));
          item.appendChild(node('span', 'proposé(s) aujourd’hui'));
          gradeParts.push(item);
        });
        dl.appendChild(line('Grades', gradeParts));
        var opens = wizard.querySelector('[data-advc-opens]');
        var closes = wizard.querySelector('[data-advc-closes]');
        var year = wizard.querySelector('[data-advc-year]');
        dl.appendChild(line('Période', [
          node('span', 'Ouverture'), node('b', fmt(opens && opens.value)), node('span', 'Clôture'), node('b', fmt(closes && closes.value)),
          node('span', 'Année du tableau'), node('b', year ? year.value : '')
        ]));
        var members = Array.prototype.slice.call(wizard.querySelectorAll('[data-advc-member]')).filter(function (s) { return s.value !== ''; }).map(function (s) {
          return node('span', (s.options[s.selectedIndex].textContent || '').trim(), 'advc-summary__item');
        });
        dl.appendChild(line('Commission', members.length ? members : [node('span', 'À composer plus tard')]));
        var save = wizard.querySelector('input[name="save_criteria"]');
        dl.appendChild(line('Seuils', [node('span', save && save.checked ? 'Enregistrés pour la communauté' : 'Inchangés')]));
        box.appendChild(dl);
      }

      function valid(n) {
        var err = wizard.querySelector('[data-advc-error="' + n + '"]');
        var ok = true;
        if (n === 1) {
          ok = checkedGrades().length > 0;
        } else if (n === 2) {
          var o = wizard.querySelector('[data-advc-opens]');
          var c = wizard.querySelector('[data-advc-closes]');
          ok = !!(o && c && o.value && c.value && c.value >= o.value);
        }
        if (err) err.hidden = ok;
        return ok;
      }

      function show(n) {
        current = Math.max(1, Math.min(total, n));
        steps.forEach(function (step) {
          step.hidden = Number(step.getAttribute('data-advc-step')) !== current;
        });
        if (nav) {
          nav.querySelectorAll('[data-advc-goto]').forEach(function (btn) {
            var k = Number(btn.getAttribute('data-advc-goto'));
            btn.classList.toggle('is-current', k === current);
            btn.classList.toggle('is-done', k < current);
            if (k === current) { btn.setAttribute('aria-current', 'step'); } else { btn.removeAttribute('aria-current'); }
          });
        }
        if (prev) prev.disabled = current === 1;
        if (next) next.hidden = current === total;
        if (where) where.textContent = current + ' / ' + total;
        if (current === 4) syncQuotas();
        if (current === total) summary();
      }

      if (steps.length > 1) {
        if (nav) nav.hidden = false;
        if (bar) bar.hidden = false;
        if (prev) prev.addEventListener('click', function () { show(current - 1); });
        if (next) next.addEventListener('click', function () {
          if (valid(current)) show(current + 1);
        });
        if (nav) nav.addEventListener('click', function (event) {
          var btn = event.target.closest('[data-advc-goto]');
          if (!btn) return;
          var target = Number(btn.getAttribute('data-advc-goto'));
          for (var k = 1; k < target; k++) {
            if (!valid(k)) { show(k); return; }
          }
          show(target);
        });
        wizard.addEventListener('change', function (event) {
          if (event.target.matches('[data-advc-grade]')) {
            syncQuotas();
            valid(1);
          }
        });
        wizard.addEventListener('submit', function (event) {
          if (!valid(1)) { event.preventDefault(); show(1); return; }
          if (!valid(2)) { event.preventDefault(); show(2); }
        });
        show(1);
      }
    }
})();
