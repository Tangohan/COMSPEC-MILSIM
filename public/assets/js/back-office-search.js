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
    var docsUrl = dlg.getAttribute('data-docs-url') || '';
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
        var end = input.value.length;
        try {
          input.setSelectionRange(end, end);
        } catch (e) { /* ignore */ }
        run();
      }
    }

    function closeDlg() {
      if (timer) {
        clearTimeout(timer);
        timer = null;
      }
      if (abortCtl) {
        try {
          abortCtl.abort();
        } catch (e) { /* ignore */ }
        abortCtl = null;
      }
      if (dlg.open) {
        dlg.close();
      }
      // Le navigateur rend le focus au champ du bandeau : on le retire pour
      // qu'il ne rouvre pas la recherche et qu'un 2e clic la rouvre bien.
      if (topSearch && document.activeElement === topSearch) {
        topSearch.blur();
      }
    }

    var activeIdx = -1;
    var lastQuery = '';

    function fold(s) {
      var t = String(s == null ? '' : s).toLowerCase();
      if (t.normalize) t = t.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      return t;
    }

    /** Met en gras les mots tapés (comparaison sans accents, sur le texte d'origine). */
    function highlight(text) {
      var raw = String(text == null ? '' : text);
      var tokens = fold(lastQuery).split(/[^a-z0-9]+/).filter(function (t) { return t.length >= 2; });
      if (!tokens.length) return esc(raw);
      var folded = fold(raw);
      if (folded.length !== raw.length) return esc(raw);
      var marks = new Array(raw.length);
      tokens.forEach(function (tok) {
        var from = 0;
        var at;
        while ((at = folded.indexOf(tok, from)) !== -1) {
          for (var k = at; k < at + tok.length; k++) marks[k] = true;
          from = at + tok.length;
        }
      });
      var out = '';
      var open = false;
      for (var i = 0; i < raw.length; i++) {
        if (marks[i] && !open) { out += '<mark>'; open = true; }
        if (!marks[i] && open) { out += '</mark>'; open = false; }
        out += esc(raw.charAt(i));
      }
      if (open) out += '</mark>';
      return out;
    }

    function links() {
      return resultsEl ? Array.prototype.slice.call(resultsEl.querySelectorAll('a[data-hit]')) : [];
    }

    function setActive(idx) {
      var all = links();
      if (!all.length) { activeIdx = -1; return; }
      if (idx < 0) idx = all.length - 1;
      if (idx >= all.length) idx = 0;
      all.forEach(function (a, i) {
        a.classList.toggle('is-active', i === idx);
        a.setAttribute('aria-selected', i === idx ? 'true' : 'false');
      });
      activeIdx = idx;
      try { all[idx].scrollIntoView({ block: 'nearest' }); } catch (e) { /* ignore */ }
    }

    function rows(items, title) {
      if (!items || !items.length) {
        return '';
      }
      var inner = items
        .map(function (it) {
          var sub = it.subtitle
            ? '<span class="ath-bo-search__sub">' + highlight(it.subtitle) + '</span>'
            : '';
          return (
            '<li><a data-hit role="option" aria-selected="false" href="' +
            esc(it.href) +
            '"><strong>' +
            highlight(it.title) +
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
      var guides = data.guides || [];
      var total = pages.length + pers.length + docs.length + events.length + guides.length;
      activeIdx = -1;
      if (total === 0) {
        resultsEl.innerHTML =
          '<div class="ath-bo-search__empty"><p>Aucun résultat pour « ' + esc(lastQuery) + ' ».</p>' +
          '<p>Essayez un nom, un indicatif, un mot plus court ou un synonyme (« organigramme » plutôt que « orbat », « droits » plutôt que « permissions »).</p>' +
          (docsUrl ? '<p><a href="' + esc(docsUrl) + '">Ouvrir le guide du portail</a></p>' : '') +
          '</div>';
        return;
      }
      var emptyQuery = String(lastQuery || '').trim() === '';
      resultsEl.innerHTML =
        rows(pages, emptyQuery ? 'Raccourcis' : 'Pages') +
        rows(pers, 'Membres') +
        rows(docs, 'Documents') +
        rows(events, 'Événements') +
        rows(guides, emptyQuery ? 'Pour bien démarrer' : 'Aide et guides');
      if (!emptyQuery) setActive(0);
    }

    function run() {
      var q = input ? String(input.value || '') : '';
      lastQuery = q;
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
      // Flèches pour parcourir les résultats, Entrée pour ouvrir le résultat en surbrillance.
      input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          setActive(activeIdx + 1);
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          setActive(activeIdx - 1);
        } else if (e.key === 'Enter') {
          var all = links();
          var target = all[activeIdx >= 0 ? activeIdx : 0];
          if (target) {
            e.preventDefault();
            window.location.href = target.getAttribute('href');
          }
        }
      });
    }
    dlg.querySelectorAll('[data-ath-bo-search-close]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        closeDlg();
      });
    });
    // Échap : géré nous-mêmes (le champ de type « search » avale le 1er Échap
    // pour se vider, et Chrome ignore parfois un 2e « cancel » d'affilée).
    dlg.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' || e.key === 'Esc') {
        e.preventDefault();
        e.stopPropagation();
        closeDlg();
      }
    });
    dlg.addEventListener('cancel', function (e) {
      e.preventDefault();
      closeDlg();
    });

    // Clic à l'extérieur : seulement si le clic commence ET finit hors de la
    // boîte (une sélection de texte qui déborde ne ferme pas la recherche).
    function outside(e) {
      var r = dlg.getBoundingClientRect();
      return (
        e.clientX < r.left ||
        e.clientX > r.right ||
        e.clientY < r.top ||
        e.clientY > r.bottom
      );
    }
    var downOutside = false;
    dlg.addEventListener('pointerdown', function (e) {
      downOutside = e.target === dlg && outside(e);
    });
    dlg.addEventListener('click', function (e) {
      var wasOutside = downOutside;
      downOutside = false;
      if (e.target === dlg && wasOutside && outside(e)) closeDlg();
    });

    document.addEventListener('keydown', function (e) {
      if ((e.metaKey || e.ctrlKey) && String(e.key).toLowerCase() === 'k') {
        e.preventDefault();
        openDlg(topSearch ? topSearch.value : '');
      }
    });

    if (topSearch) {
      topSearch.setAttribute('aria-label', 'Rechercher dans le back-office');
      topSearch.setAttribute('placeholder', 'Pages, membres, documents, guides…');
      // Ouverture au clic, pas au focus : à la fermeture, le navigateur rend
      // le focus à ce champ, ce qui rouvrait aussitôt la recherche.
      topSearch.addEventListener('click', function () {
        openDlg(topSearch.value);
      });
      topSearch.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          openDlg(topSearch.value);
        } else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
          e.preventDefault();
          openDlg(e.key);
        }
      });
    }
    // Bouton loupe de la barre mobile (la recherche de la topbar y est masquée).
    document.querySelectorAll('[data-ath-search-open]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        openDlg('');
      });
    });
    if (lmsSearch) {
      lmsSearch.addEventListener('click', function (e) {
        e.preventDefault();
        openDlg('');
      });
    }
  });
})();
