/**
 * Studio formation — glisser-déposer modules et leçons (SortableJS).
 */
(function () {
  'use strict';

  function postReorder(url, csrf, action, fields) {
    var fd = new FormData();
    fd.append('_csrf_token', csrf);
    fd.append('_action', action);
    if (action === 'reorder_modules') {
      fields.ids.forEach(function (id) {
        fd.append('module_ids[]', String(id));
      });
    } else if (action === 'reorder_lessons') {
      fd.append('module_id', String(fields.moduleId));
      fields.ids.forEach(function (id) {
        fd.append('lesson_ids[]', String(id));
      });
    }
    return fetch(url, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { Accept: 'text/html,application/xhtml+xml' },
    });
  }

  function init() {
    var root = document.getElementById('studio-modules-list');
    if (!root || typeof window.Sortable !== 'function') return;
    var url = root.getAttribute('data-studio-url') || '';
    var csrf = root.getAttribute('data-csrf') || '';
    if (!url || !csrf) return;

    new Sortable(root, {
      animation: 165,
      handle: '.studio-module-drag-handle',
      draggable: '.studio-sort-module-card',
      ghostClass: 'studio-sort-ghost',
      onEnd: function () {
        var ids = Array.prototype.map.call(root.querySelectorAll('.studio-sort-module-card'), function (el) {
          return el.getAttribute('data-module-id');
        });
        postReorder(url, csrf, 'reorder_modules', { ids: ids })
          .then(function (r) {
            if (r.ok) window.location.reload();
            else window.alert('Impossible de réordonner les modules.');
          })
          .catch(function () {
            window.alert('Erreur réseau.');
          });
      },
    });

    Array.prototype.forEach.call(root.querySelectorAll('.studio-sort-lessons'), function (listEl) {
      var mid = listEl.getAttribute('data-module-id');
      if (!mid) return;
      new Sortable(listEl, {
        animation: 165,
        handle: '.studio-lesson-drag-handle',
        draggable: '.studio-sort-lesson-card',
        ghostClass: 'studio-sort-ghost',
        onEnd: function () {
          var lids = Array.prototype.map.call(listEl.querySelectorAll('.studio-sort-lesson-card'), function (el) {
            return el.getAttribute('data-lesson-id');
          });
          postReorder(url, csrf, 'reorder_lessons', { moduleId: mid, ids: lids })
            .then(function (r) {
              if (r.ok) window.location.reload();
              else window.alert('Impossible de réordonner les leçons.');
            })
            .catch(function () {
              window.alert('Erreur réseau.');
            });
        },
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
