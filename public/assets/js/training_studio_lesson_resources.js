/**
 * Studio LMS — panneau ressources leçon : modes lien / fichier / centre documentaire + filtre liste documents.
 */
(function () {
  function syncForm(form) {
    var sel = form.querySelector('[data-lms-resource-mode]');
    if (!sel) return;
    var v = sel.value;
    form.querySelectorAll('[data-lms-res-panel]').forEach(function (el) {
      var show = el.getAttribute('data-lms-res-panel') === v;
      el.classList.toggle('hidden', !show);
      el.querySelectorAll('input, select, textarea').forEach(function (inp) {
        if (!inp.name) return;
        if (inp.name === '_csrf_token' || inp.name === '_action' || inp.name === 'lesson_id' || inp.name === 'resource_add_mode') return;
        inp.disabled = !show;
      });
    });
    var submitBtn = form.querySelector('[data-lms-inline-submit]');
    if (submitBtn) {
      submitBtn.classList.toggle('hidden', v === 'library_upload');
    }
  }

  function bindDocFilter(form) {
    var panel = form.querySelector('[data-lms-res-panel="library"]');
    if (!panel) return;
    var filterInp = panel.querySelector('[data-lms-doc-filter]');
    var docSel = panel.querySelector('[data-lms-doc-select]');
    if (!filterInp || !docSel) return;
    filterInp.addEventListener('input', function () {
      var q = filterInp.value.trim().toLowerCase();
      if (q === '') {
        docSel.querySelectorAll('option, optgroup').forEach(function (n) {
          n.hidden = false;
        });
        return;
      }
      Array.prototype.forEach.call(docSel.children, function (node) {
        if (node.tagName === 'OPTION') {
          node.hidden = false;
        }
      });
      docSel.querySelectorAll('optgroup').forEach(function (og) {
        var any = false;
        Array.prototype.forEach.call(og.querySelectorAll('option'), function (opt) {
          var t = (opt.textContent || '').toLowerCase();
          var show = t.indexOf(q) !== -1;
          opt.hidden = !show;
          if (show) {
            any = true;
          }
        });
        og.hidden = !any;
      });
      var cur = docSel.options[docSel.selectedIndex];
      if (cur && cur.hidden) {
        docSel.selectedIndex = 0;
      }
    });
  }

  document.addEventListener('change', function (e) {
    if (e.target.matches('[data-lms-resource-mode]')) {
      var form = e.target.closest('form[data-lms-lesson-resource-form]');
      if (form) syncForm(form);
    }
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-lms-open-upload-modal]');
    if (!btn) return;
    var targetId = btn.getAttribute('data-lms-upload-modal-target');
    if (!targetId) return;
    var modal = document.getElementById(targetId);
    if (!modal || typeof modal.showModal !== 'function') return;
    modal.showModal();
  });

  document.querySelectorAll('form[data-lms-lesson-resource-form]').forEach(function (form) {
    syncForm(form);
    bindDocFilter(form);
  });
})();
