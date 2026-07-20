/**
 * Mode « manuel à chapitres » — documentations HTML (pages-html).
 */
(function () {
  'use strict';

  var root = document.getElementById('cp-editor-root');
  if (!root) return;

  var form = document.getElementById('cp-main-form');
  var nonHandbookChrome = document.getElementById('cp-non-handbook-chrome');
  var handbookPanel = document.getElementById('cp-handbook-panel');
  var mobileTablist = document.getElementById('cp-mobile-tablist');
  var chaptersRoot = document.getElementById('cp-chapters-root');
  var tpl = document.getElementById('cp-chapter-template');
  var hiddenJson = document.getElementById('cp-sections-json');
  var radios = root.querySelectorAll('input[name="doc_structure"]');
  var introTa = document.getElementById('cp-html');
  var titleInput = document.getElementById('cp-title');

  function isHandbook() {
    var r = root.querySelector('input[name="doc_structure"]:checked');
    return r && r.value === 'handbook';
  }

  function escapeHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function escapeTitle(s) {
    return escapeHtml(s).trim() || 'Documentation';
  }

  function collectChapters() {
    var out = [];
    if (!chaptersRoot) return out;
    chaptersRoot.querySelectorAll('.cp-chapter').forEach(function (row) {
      var ti = row.querySelector('.cp-chapter-title');
      var ta = row.querySelector('.cp-chapter-html');
      var sl = row.querySelector('.cp-chapter-slug');
      out.push({
        title: ti ? ti.value : '',
        slug: sl ? sl.value : '',
        html: ta
          ? typeof window.cpGetTextareaValue === 'function'
            ? window.cpGetTextareaValue(ta)
            : ta.value
          : ''
      });
    });
    return out;
  }

  function syncHiddenJson() {
    if (!hiddenJson) return;
    if (!isHandbook()) {
      hiddenJson.value = '[]';
      return;
    }
    try {
      hiddenJson.value = JSON.stringify(collectChapters());
    } catch (e) {
      hiddenJson.value = '[]';
    }
  }

  function refreshPanels() {
    var hb = isHandbook();
    if (nonHandbookChrome) nonHandbookChrome.classList.toggle('hidden', hb);
    if (handbookPanel) handbookPanel.classList.toggle('hidden', !hb);
    if (mobileTablist) mobileTablist.classList.toggle('hidden', hb);
    if (introTa) {
      introTa.required = !hb;
    }
    syncHiddenJson();
    if (introTa) introTa.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function addChapter(prefill) {
    if (!chaptersRoot || !tpl) return;
    var node = tpl.content.firstElementChild.cloneNode(true);
    if (prefill && prefill.title) {
      var ti = node.querySelector('.cp-chapter-title');
      if (ti) ti.value = prefill.title;
    }
    if (prefill && prefill.html) {
      var ta = node.querySelector('.cp-chapter-html');
      if (ta) ta.value = prefill.html;
    }
    if (prefill && prefill.slug) {
      var sl = node.querySelector('.cp-chapter-slug');
      if (sl) sl.value = prefill.slug;
    }
    chaptersRoot.appendChild(node);
    bindRow(node);
    if (typeof window.cpBindChapterRow === 'function') {
      window.cpBindChapterRow(node);
    }
    syncHiddenJson();
    introTa && introTa.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function bindRow(row) {
    row.querySelectorAll('[data-cp-chapter-remove]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var taRm = row.querySelector('.cp-chapter-html');
        if (taRm && typeof window.cpDestroyChapterEditor === 'function') {
          window.cpDestroyChapterEditor(taRm);
        }
        row.remove();
        syncHiddenJson();
        introTa && introTa.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });
    row.querySelectorAll('[data-cp-chapter-up]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var p = row.previousElementSibling;
        if (p && chaptersRoot.contains(p)) {
          chaptersRoot.insertBefore(row, p);
          syncHiddenJson();
          introTa && introTa.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });
    });
    row.querySelectorAll('[data-cp-chapter-down]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var n = row.nextElementSibling;
        if (n && chaptersRoot.contains(n)) {
          chaptersRoot.insertBefore(n, row);
          syncHiddenJson();
          introTa && introTa.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });
    });
    row.querySelectorAll('.cp-chapter-title, .cp-chapter-html').forEach(function (el) {
      el.addEventListener('input', function () {
        syncHiddenJson();
        introTa && introTa.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });
  }

  window.cpHandbookIsActive = isHandbook;

  window.cpSyncHandbookJsonBeforeSubmit = syncHiddenJson;

  window.cpBuildHandbookPreviewHtml = function () {
    var title = titleInput ? titleInput.value : '';
    var intro =
      introTa && typeof window.cpGetTextareaValue === 'function'
        ? window.cpGetTextareaValue(introTa)
        : introTa
          ? introTa.value
          : '';
    var chapters = collectChapters();
    var toc = '<nav class="formation-doc-toc" aria-label="Sommaire"><p class="formation-doc-toc__label">Sommaire</p><p class="formation-doc-toc__count">' + chapters.length + ' chapitre' + (chapters.length > 1 ? 's' : '') + '</p><ol class="formation-doc-toc__list">';
    var main = '<main class="formation-doc-main formation-doc-main--book">';
    chapters.forEach(function (ch, i) {
      var slug = 'chapitre-' + (i + 1);
      var t = escapeHtml(ch.title || 'Chapitre ' + (i + 1));
      toc += '<li><a href="#' + slug + '">' + t + '</a></li>';
    });
    toc += '</ol></nav>';
    if (intro.trim()) {
      main += '<section class="formation-doc-panel formation-doc-prose formation-doc-intro">' + intro + '</section>';
    }
    chapters.forEach(function (ch, i) {
      var slug = 'chapitre-' + (i + 1);
      var t = escapeHtml(ch.title || 'Chapitre ' + (i + 1));
      main +=
        '<article id="' +
        slug +
        '" class="formation-doc-chapter formation-doc-panel"><h2 class="formation-doc-chapter__title"><span class="formation-doc-chapter__index">' +
        (i + 1) +
        '</span> ' +
        t +
        '</h2><div class="formation-doc-prose">' +
        (ch.html || '') +
        '</div></article>';
    });
    main += '</main>';
    var body =
      '<div class="formation-doc-shell formation-doc-shell--book">' +
      '<header class="formation-doc-header"><div class="formation-doc-header__inner">' +
      '<p class="formation-doc-kicker">Manuel de formation</p>' +
      '<h1 class="formation-doc-title">' +
      escapeTitle(title) +
      '</h1></div></header>' +
      '<div class="formation-doc-layout">' +
      toc +
      main +
      '</div></div>';
    var cssHref = (window.cpDocCssHref || '').replace(/"/g, '%22');
    return (
      '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">' +
      '<meta name="viewport" content="width=device-width, initial-scale=1">' +
      '<title>' +
      escapeTitle(title) +
      '</title>' +
      '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;display=swap">' +
      (cssHref
        ? '<link rel="stylesheet" href="' + cssHref + '">'
        : '') +
      '</head><body class="formation-doc-body">' +
      body +
      '</body></html>'
    );
  };

  var addBtn = document.getElementById('cp-chapter-add');
  if (addBtn) {
    addBtn.addEventListener('click', function () {
      addChapter(null);
    });
  }

  if (chaptersRoot) {
    chaptersRoot.querySelectorAll('.cp-chapter').forEach(function (row) {
      bindRow(row);
      if (typeof window.cpBindChapterRow === 'function') {
        window.cpBindChapterRow(row);
      }
    });
  }

  radios.forEach(function (r) {
    r.addEventListener('change', refreshPanels);
  });

  if (form) {
    form.addEventListener('submit', function () {
      syncHiddenJson();
    });
  }

  refreshPanels();

  if (root.getAttribute('data-initial-handbook') === '1' && chaptersRoot && !chaptersRoot.querySelector('.cp-chapter')) {
    addChapter(null);
  }
})();
