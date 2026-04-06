/**
 * Studio LMS — affiche le panneau d’ajout de ressource selon le mode (lien / fichier / bibliothèque).
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
  }

  document.addEventListener('change', function (e) {
    if (e.target.matches('[data-lms-resource-mode]')) {
      var form = e.target.closest('form[data-lms-lesson-resource-form]');
      if (form) syncForm(form);
    }
  });

  document.querySelectorAll('form[data-lms-lesson-resource-form]').forEach(syncForm);
})();
