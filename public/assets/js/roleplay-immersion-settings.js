(function () {
  'use strict';

  function parseLines(raw) {
    return String(raw || '')
      .split(/\r\n|\r|\n/)
      .map(function (line) {
        return line.trim();
      })
      .filter(function (line) {
        return line !== '';
      });
  }

  function bindList(root) {
    var source = root.querySelector('[data-imm-source]');
    var rows = root.querySelector('[data-imm-rows]');
    var addBtn = root.querySelector('[data-imm-add]');
    if (!source || !rows || !addBtn) {
      return;
    }

    var placeholder = source.getAttribute('data-placeholder') || '';
    var fieldName = source.getAttribute('name') || '';
    source.setAttribute('hidden', 'hidden');
    source.classList.add('bo-imm-list__source');

    function sync() {
      var values = [];
      rows.querySelectorAll('input[type="text"]').forEach(function (input) {
        var v = input.value.trim();
        if (v !== '') {
          values.push(v);
        }
      });
      source.value = values.join('\n');
    }

    function addRow(value) {
      var row = document.createElement('div');
      row.className = 'bo-imm-list__row';
      var input = document.createElement('input');
      input.type = 'text';
      input.value = value || '';
      input.placeholder = placeholder;
      input.setAttribute('autocomplete', 'off');
      input.addEventListener('input', sync);
      var remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'bo-imm-list__remove';
      remove.setAttribute('aria-label', 'Retirer cette ligne');
      remove.textContent = 'Retirer';
      remove.addEventListener('click', function () {
        row.remove();
        if (!rows.querySelector('.bo-imm-list__row')) {
          addRow('');
        }
        sync();
      });
      row.appendChild(input);
      row.appendChild(remove);
      rows.appendChild(row);
      if (value === '') {
        input.focus();
      }
    }

    parseLines(source.value).forEach(function (line) {
      addRow(line);
    });
    if (!rows.querySelector('.bo-imm-list__row')) {
      addRow('');
    }

    addBtn.addEventListener('click', function () {
      addRow('');
    });

    var form = root.closest('form');
    if (form) {
      form.addEventListener('submit', sync);
    }
    sync();
    if (fieldName) {
      source.setAttribute('name', fieldName);
    }
  }

  document.querySelectorAll('[data-imm-list]').forEach(bindList);
})();
