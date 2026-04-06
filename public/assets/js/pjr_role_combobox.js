/**
 * Listes d’emplois métier : champ repliable avec recherche et fil d’Ariane (page attributions).
 */
(function () {
  'use strict';

  var OPTIONS = [];
  var activePanel = null;
  var activeTrigger = null;
  var repositionHandler = null;

  function esc(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function loadOptions() {
    if (!OPTIONS.length && window.__PJR_JOB_ROLES && Array.isArray(window.__PJR_JOB_ROLES)) {
      OPTIONS = window.__PJR_JOB_ROLES;
    }
    return OPTIONS;
  }

  function closePanel() {
    if (repositionHandler) {
      window.removeEventListener('scroll', repositionHandler, true);
      window.removeEventListener('resize', repositionHandler);
      repositionHandler = null;
    }
    if (activePanel && activePanel.parentNode) {
      activePanel.parentNode.removeChild(activePanel);
    }
    if (activeTrigger) {
      activeTrigger.setAttribute('aria-expanded', 'false');
      activeTrigger.classList.remove('ring-2', 'ring-emerald-500/40', 'border-emerald-400');
    }
    activePanel = null;
    activeTrigger = null;
  }

  function positionPanel(trigger, panel) {
    var r = trigger.getBoundingClientRect();
    var w = Math.max(r.width, 288);
    var maxH = Math.min(384, window.innerHeight - 80);
    var top = r.bottom + 6;
    if (top + maxH > window.innerHeight - 12) {
      top = Math.max(12, r.top - maxH - 6);
    }
    var left = r.left;
    if (left + w > window.innerWidth - 12) {
      left = Math.max(12, window.innerWidth - w - 12);
    }
    panel.style.top = top + 'px';
    panel.style.left = left + 'px';
    panel.style.width = w + 'px';
    panel.style.maxHeight = maxH + 'px';
  }

  function renderOptionButton(o) {
    var parts = o.segments && o.segments.length ? o.segments : [o.label || ''];
    var inner = '';
    for (var i = 0; i < parts.length; i++) {
      if (i) {
        inner += '<span class="text-slate-300 shrink-0 px-0.5" aria-hidden="true">›</span>';
      }
      var isLast = i === parts.length - 1;
      inner +=
        '<span class="' +
        (isLast ? 'font-semibold text-slate-900' : 'text-slate-500') +
        '">' +
        esc(parts[i]) +
        '</span>';
    }
    var en = o.label_en ? '<span class="mt-0.5 block text-[11px] font-normal text-slate-400">' + esc(o.label_en) + '</span>' : '';
    var pc = typeof o.permission_count === 'number' ? o.permission_count : parseInt(o.permission_count, 10);
    if (isNaN(pc)) {
      pc = 0;
    }
    var badge =
      pc > 0
        ? '<span class="shrink-0 rounded-md bg-amber-50 px-2 py-1 text-center text-[10px] font-semibold leading-tight text-amber-950 ring-1 ring-amber-200/90">' +
          pc +
          (pc > 1 ? ' autorisations liées' : ' autorisation liée') +
          '</span>'
        : '<span class="shrink-0 max-w-[7rem] text-right text-[10px] leading-tight text-slate-400">Aucune autorisation liée</span>';
    return (
      '<li role="none">' +
      '<button type="button" role="option" class="pjr-role-combobox-option flex w-full items-start gap-3 border-b border-slate-100 px-3 py-2.5 text-left text-xs leading-snug last:border-b-0 hover:bg-emerald-50 focus:bg-emerald-50 focus:outline-none' +
      '" data-id="' +
      esc(String(o.id)) +
      '" data-label="' +
      esc(o.label) +
      '">' +
      '<span class="min-w-0 flex-1">' +
      '<span class="flex flex-wrap items-baseline gap-x-0 gap-y-0">' +
      inner +
      '</span>' +
      en +
      '</span>' +
      badge +
      '</button></li>'
    );
  }

  function filterOptions(query) {
    var q = (query || '').trim().toLowerCase();
    var opts = loadOptions();
    if (!q) {
      return opts.slice();
    }
    return opts.filter(function (o) {
      return (o.search && o.search.indexOf(q) !== -1) || (o.label && String(o.label).toLowerCase().indexOf(q) !== -1);
    });
  }

  function openFor(trigger) {
    closePanel();
    var root = trigger.closest('[data-pjr-role-combobox]');
    if (!root) {
      return;
    }
    var hidden = root.querySelector('.pjr-role-combobox-value');
    var labelEl = root.querySelector('.pjr-role-combobox-label');
    if (!hidden || !labelEl) {
      return;
    }

    var resetValue = root.getAttribute('data-reset-value');
    if (resetValue === null) {
      resetValue = '';
    }
    var resetLabel = root.getAttribute('data-reset-label') || '—';

    activeTrigger = trigger;
    trigger.setAttribute('aria-expanded', 'true');
    trigger.classList.add('ring-2', 'ring-emerald-500/40', 'border-emerald-400');

    var panel = document.createElement('div');
    panel.className =
      'pjr-role-combobox-float fixed z-[400] flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl ring-1 ring-slate-900/5';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', 'Choisir un emploi');

    panel.innerHTML =
      '<div class="border-b border-slate-100 p-2">' +
      '<label class="sr-only" for="pjr-combobox-filter-active">Filtrer la liste</label>' +
      '<input type="search" id="pjr-combobox-filter-active" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30" placeholder="Tapez pour filtrer…" autocomplete="off">' +
      '</div>' +
      '<ul class="pjr-role-combobox-ul min-h-0 flex-1 overflow-y-auto overscroll-contain p-1" role="listbox"></ul>';

    document.body.appendChild(panel);
    activePanel = panel;

    var filterInput = panel.querySelector('#pjr-combobox-filter-active');
    var ul = panel.querySelector('.pjr-role-combobox-ul');

    function renderList(q) {
      var rows = filterOptions(q);
      var html = '';
      html +=
        '<li role="none"><button type="button" role="option" class="pjr-role-combobox-option w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-slate-500 hover:bg-slate-100 focus:bg-slate-100 focus:outline-none" data-id="__RESET__" data-label="' +
        esc(resetLabel) +
        '">' +
        esc(resetLabel) +
        '</button></li>';
      for (var i = 0; i < rows.length; i++) {
        html += renderOptionButton(rows[i]);
      }
      if (rows.length === 0) {
        html +=
          '<li class="px-3 py-6 text-center text-xs text-slate-500" role="presentation">Aucun emploi ne correspond à votre recherche.</li>';
      }
      ul.innerHTML = html;
    }

    renderList('');

    repositionHandler = function () {
      positionPanel(trigger, panel);
    };
    window.addEventListener('scroll', repositionHandler, true);
    window.addEventListener('resize', repositionHandler);
    repositionHandler();

    filterInput.addEventListener('input', function () {
      renderList(filterInput.value);
      positionPanel(trigger, panel);
    });

    panel.addEventListener('click', function (ev) {
      var btn = ev.target.closest('.pjr-role-combobox-option');
      if (!btn) {
        return;
      }
      ev.preventDefault();
      ev.stopPropagation();
      var idRaw = btn.getAttribute('data-id');
      var lbl = btn.getAttribute('data-label') || resetLabel;
      if (idRaw === '__RESET__') {
        hidden.value = resetValue;
      } else {
        hidden.value = idRaw || '';
      }
      labelEl.textContent = lbl;
      closePanel();
    });

    setTimeout(function () {
      filterInput.focus();
      filterInput.select();
    }, 10);
  }

  document.addEventListener('click', function (e) {
    if (e.target.closest('.pjr-role-combobox-float')) {
      return;
    }
    var trigger = e.target.closest('.pjr-role-combobox-trigger');
    if (trigger) {
      e.preventDefault();
      e.stopPropagation();
      if (activeTrigger === trigger && activePanel) {
        closePanel();
      } else {
        openFor(trigger);
      }
      return;
    }
    closePanel();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape' || !activePanel) {
      return;
    }
    e.preventDefault();
    var t = activeTrigger;
    closePanel();
    if (t) {
      t.focus();
    }
  });

  window.PjrRoleCombobox = {
    close: closePanel,
    refreshLabel: function (root) {
      if (!root) {
        return;
      }
      var hidden = root.querySelector('.pjr-role-combobox-value');
      var labelEl = root.querySelector('.pjr-role-combobox-label');
      if (!hidden || !labelEl) {
        return;
      }
      var resetValue = root.getAttribute('data-reset-value');
      if (resetValue === null) {
        resetValue = '';
      }
      var resetLabel = root.getAttribute('data-reset-label') || '—';
      var v = hidden.value;
      if (v === resetValue || v === '') {
        labelEl.textContent = resetLabel;
        return;
      }
      var opts = loadOptions();
      for (var i = 0; i < opts.length; i++) {
        if (String(opts[i].id) === String(v)) {
          labelEl.textContent = opts[i].label || '—';
          return;
        }
      }
      labelEl.textContent = hidden.value ? 'Emploi #' + hidden.value : resetLabel;
    },
  };
})();
