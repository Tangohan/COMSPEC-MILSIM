/**
 * Recherche complète du back-office (Ctrl/⌘+K).
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  ready(function () {
    var dlg = document.getElementById('ath-bo-search');
    var topSearch = document.getElementById('ath-top-search');
    var lmsSearch = document.getElementById('eff-bo-search-open');
    if (!dlg || typeof dlg.showModal !== 'function') {
      return;
    }
    var apiUrl = dlg.getAttribute('data-api-url') || '';
    var input = document.getElementById('ath-bo-search-q');
    var resultsEl = document.getElementById('ath-bo-search-results');
    var timer = null;
    var seq = 0;
    var abortCtl = null;

    function esc(s) {
      var t = document.createElement('div');
      t.textContent = s == null ? '' : String(s);
      return t.innerHTML;
    }

    function openDlg(seed) {
      if (dlg.open) {
        if (input) input.focus();
        return;
      }
      dlg.showModal();
      if (input) {
        input.value = seed || '';
        input.focus();
        input.select();
        run();
      }
    }

    function closeDlg() {
      if (dlg.open) {
        dlg.close();
      }
    }

    function rows(items, title) {
      if (!items || !items.length) {
        return '';
      }
      var inner = items
        .map(function (it) {
          var sub = it.subtitle
            ? '<span class="ath-bo-search__sub">' + esc(it.subtitle) + '</span>'
            : '';
          return (
            '<li><a href="' +
            esc(it.href) +
            '"><strong>' +
            esc(it.title) +
            '</strong>' +
            sub +
            '</a></li>'
          );
        })
        .join('');
      return (
        '<section><h3>' +
        esc(title) +
        '</h3><ul>' +
        inner +
        '</ul></section>'
      );
    }

    function render(data) {
      if (!resultsEl) return;
      var pages = data.pages || [];
      var pers = data.personnel || [];
      var docs = data.documents || [];
      var events = data.events || [];
      var total = pages.length + pers.length + docs.length + events.length;
      if (total === 0) {
        resultsEl.innerHTML =
          '<p class="ath-bo-search__empty">Aucun résultat. Essayez un nom, un indicatif ou le titre d’une page.</p>';
        return;
      }
      resultsEl.innerHTML =
        rows(pages, 'Pages') +
        rows(pers, 'Membres') +
        rows(docs, 'Documents') +
        rows(events, 'Manœuvres');
    }

    function run() {
      var q = input ? String(input.value || '') : '';
      var my = ++seq;
      if (abortCtl) {
        try {
          abortCtl.abort();
        } catch (e) { /* ignore */ }
      }
      if (!apiUrl) {
        render({});
        return;
      }
      abortCtl = typeof AbortController !== 'undefined' ? new AbortController() : null;
      var url = apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q);
      fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        signal: abortCtl ? abortCtl.signal : undefined,
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (my !== seq) return;
          if (!data || data.success === false) {
            resultsEl.innerHTML =
              '<p class="ath-bo-search__empty">La recherche n’a pas abouti. Réessayez.</p>';
            return;
          }
          render(data);
        })
        .catch(function (err) {
          if (err && err.name === 'AbortError') return;
          if (my !== seq) return;
          resultsEl.innerHTML =
            '<p class="ath-bo-search__empty">La recherche n’a pas abouti. Réessayez.</p>';
        });
    }

    function schedule() {
      if (timer) clearTimeout(timer);
      timer = setTimeout(run, 220);
    }

    if (input) {
      input.addEventListener('input', schedule);
      input.addEventListener('search', schedule);
    }
    dlg.querySelectorAll('[data-ath-bo-search-close]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        closeDlg();
      });
    });
    dlg.addEventListener('click', function (e) {
      if (e.target === dlg) closeDlg();
    });

    document.addEventListener('keydown', function (e) {
      if ((e.metaKey || e.ctrlKey) && String(e.key).toLowerCase() === 'k') {
        e.preventDefault();
        openDlg(topSearch ? topSearch.value : '');
      }
    });

    if (topSearch) {
      topSearch.setAttribute('aria-label', 'Rechercher dans le back-office');
      topSearch.setAttribute('placeholder', 'Pages, membres, documents…');
      topSearch.addEventListener('focus', function () {
        openDlg(topSearch.value);
      });
      topSearch.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          openDlg(topSearch.value);
        }
      });
    }
    if (lmsSearch) {
      lmsSearch.addEventListener('click', function (e) {
        e.preventDefault();
        openDlg('');
      });
    }
  });
})();
