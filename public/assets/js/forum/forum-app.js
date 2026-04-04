/**
 * Forum — utilitaires AJAX (recherche, brouillons localStorage).
 * Les pages peuvent appeler window.ForumApp.initSearch() si #forum-search-input est présent.
 */
(function () {
  'use strict';

  function debounce(fn, ms) {
    var t;
    return function () {
      var a = arguments;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(null, a); }, ms);
    };
  }

  window.ForumApp = {
    draftKey: function (topicId) {
      return 'forum_draft_' + (topicId || 'new');
    },
    saveDraft: function (topicId, text) {
      try {
        localStorage.setItem(this.draftKey(topicId), text);
      } catch (e) {}
    },
    loadDraft: function (topicId) {
      try {
        return localStorage.getItem(this.draftKey(topicId)) || '';
      } catch (e) {
        return '';
      }
    },
    initSearch: function (inputSelector, onQuery) {
      var el = document.querySelector(inputSelector);
      if (!el) return;
      var run = debounce(function () {
        var q = (el.value || '').trim();
        if (q.length < 2) return;
        if (typeof onQuery === 'function') onQuery(q);
      }, 300);
      el.addEventListener('input', run);
    }
  };
})();
