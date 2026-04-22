/**
 * Documentations HTML : snippets, modèles, TinyMCE (CDN), sync avant enregistrement.
 */
(function () {
  'use strict';

  var TINYMCE_VER = '7.6.0';
  var TINYMCE_BASE = 'https://cdn.jsdelivr.net/npm/tinymce@' + TINYMCE_VER;

  var root = document.getElementById('cp-editor-root');
  if (!root) return;

  var chapterSeq = 0;

  var SNIPPETS = {
    encadre:
      '<blockquote><p><strong>Note.</strong> Texte de l’encadré : précisez le contexte ou la règle applicable.</p></blockquote>',
    alerte:
      '<p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-amber-950 text-sm"><strong>Attention.</strong> Point sensible ou limite opérationnelle.</p>',
    deuxcolonnes:
      '<div class="grid gap-4 md:grid-cols-2"><div class="formation-doc-prose"><p>Colonne A — détail.</p></div><div class="formation-doc-prose"><p>Colonne B — suite.</p></div></div>',
    liste: '<ol class="list-decimal pl-5 space-y-1"><li>Premier point</li><li>Deuxième point</li></ol>',
    tableau:
      '<table class="w-full text-sm border-collapse border border-slate-200"><thead><tr><th class="border border-slate-200 p-2 text-left">Critère</th><th class="border border-slate-200 p-2 text-left">Valeur</th></tr></thead><tbody><tr><td class="border border-slate-200 p-2">Exemple</td><td class="border border-slate-200 p-2">—</td></tr></tbody></table>',
    lien: '<p><a href="https://" rel="noopener noreferrer">Libellé du lien</a></p>'
  };

  var PRESETS = {
    page_theme:
      '<div class="formation-doc-prose">' +
      '<p class="text-slate-600 text-sm">Introduction courte (contexte, périmètre, lecteurs visés).</p>' +
      '<h2>Première partie</h2><p>Paragraphe de contenu…</p>' +
      '<h2>Deuxième partie</h2><p>Paragraphe de contenu…</p>' +
      '<h2>Références</h2><ul><li>Document interne</li><li>Point de contact</li></ul></div>',
    chapitre_type:
      '<div class="formation-doc-prose">' +
      '<h2>Titre du chapitre</h2><p>Objectifs de ce chapitre en une ou deux phrases.</p>' +
      '<h3>Sous-partie</h3><p>Développement…</p></div>',
    sommaire_static:
      '<nav class="formation-doc-toc" aria-label="Sommaire interne"><p class="formation-doc-toc__label">Dans ce chapitre</p><ol class="formation-doc-toc__list">' +
      '<li><a href="#partie-a">Partie A</a></li><li><a href="#partie-b">Partie B</a></li></ol></nav>' +
      '<div class="formation-doc-prose"><h2 id="partie-a">Partie A</h2><p>…</p><h2 id="partie-b">Partie B</h2><p>…</p></div>'
  };

  function isFullDocument(html) {
    var t = (html || '').trim();
    return /^\s*<!DOCTYPE\s+html/i.test(t) || /^\s*<html[\s>]/i.test(t);
  }

  function insertAtCursor(textarea, html) {
    if (!textarea) return;
    if (typeof tinymce !== 'undefined') {
      var ed = tinymce.get(textarea.id);
      if (ed && !ed.isHidden()) {
        ed.insertContent(html);
        ed.save();
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        return;
      }
    }
    var start = textarea.selectionStart || 0;
    var end = textarea.selectionEnd || 0;
    var val = textarea.value;
    textarea.value = val.substring(0, start) + html + val.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + html.length;
    textarea.focus();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function wrapSelectionProse(textarea) {
    if (!textarea) return;
    var start = textarea.selectionStart || 0;
    var end = textarea.selectionEnd || 0;
    var val = textarea.value;
    if (start === end) return;
    var sel = val.substring(start, end);
    var wrapped = '<div class="formation-doc-prose">' + sel + '</div>';
    textarea.value = val.substring(0, start) + wrapped + val.substring(end);
    textarea.selectionStart = start;
    textarea.selectionEnd = start + wrapped.length;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function resolveSnippetTarget(btn) {
    var ctx = btn.closest('[data-cp-snippet-context]');
    if (ctx && ctx.getAttribute('data-cp-snippet-context') === 'chapter') {
      var row = btn.closest('.cp-chapter');
      return row ? row.querySelector('.cp-chapter-html') : null;
    }
    return document.getElementById('cp-html');
  }

  function loadTinyMce(callback) {
    if (typeof window.tinymce !== 'undefined' && window.tinymce.init) {
      callback();
      return;
    }
    var s = document.createElement('script');
    s.src = TINYMCE_BASE + '/tinymce.min.js';
    s.async = true;
    s.onload = function () {
      callback();
    };
    s.onerror = function () {
      console.warn('TinyMCE CDN indisponible.');
    };
    document.head.appendChild(s);
  }

  function commonTinySettings() {
    return {
      base_url: TINYMCE_BASE,
      suffix: '.min',
      license_key: 'gpl',
      promotion: false,
      branding: false,
      menubar: false,
      statusbar: true,
      resize: true,
      relative_urls: false,
      remove_script_host: false,
      entity_encoding: 'raw',
      plugins: 'lists link table autoresize code',
      toolbar:
        'undo redo | blocks | bold italic | bullist numlist | link table | removeformat | code',
      block_formats: 'Paragraphe=p; Titre 2=h2; Titre 3=h3; Titre 4=h4',
      valid_elements:
        '@[id|class|colspan|rowspan],a[href|title|rel],p,br,h2,h3,h4,ul,ol,li,strong/b,em/i,blockquote,table,thead,tbody,tr,th,td,div,span',
      invalid_elements: 'script,iframe,object,embed,form,input,button,textarea,select,option,style',
      content_style:
        'body{font-family:"Source Serif 4",Georgia,serif;font-size:17px;line-height:1.65;color:#0f172a;padding:8px 12px;max-width:42rem;margin:0 auto;}',
      setup: function (ed) {
        ed.on('change keyup undo redo', function () {
          ed.save();
          var el = ed.getElement();
          if (el) el.dispatchEvent(new Event('input', { bubbles: true }));
        });
      }
    };
  }

  function destroyEditorById(id) {
    if (typeof tinymce === 'undefined') return;
    var clean = id.replace(/^#/, '');
    var ed = tinymce.get(clean);
    if (ed) ed.remove();
  }

  function destroyIntroEditor() {
    destroyEditorById('cp-html');
  }

  function initIntroEditor() {
    var ta = document.getElementById('cp-html');
    if (!ta || isFullDocument(ta.value)) return;
    if (typeof tinymce === 'undefined') return;
    if (tinymce.get('cp-html')) return;
    tinymce.init(
      Object.assign({}, commonTinySettings(), {
        selector: '#cp-html',
        height: 440,
        autoresize_bottom_margin: 24
      })
    );
  }

  function initChapterEditor(textarea) {
    if (!textarea || isFullDocument(textarea.value)) return;
    if (!textarea.id) {
      chapterSeq += 1;
      textarea.id = 'cp-chapter-html-' + chapterSeq;
    }
    if (typeof tinymce === 'undefined') return;
    if (tinymce.get(textarea.id)) return;
    tinymce.init(
      Object.assign({}, commonTinySettings(), {
        selector: '#' + textarea.id,
        height: 300,
        autoresize_bottom_margin: 16
      })
    );
  }

  function destroyChapterEditor(textarea) {
    if (!textarea || !textarea.id) return;
    destroyEditorById(textarea.id);
  }

  window.cpGetTextareaValue = function (textarea) {
    if (!textarea) return '';
    if (typeof tinymce !== 'undefined') {
      var ed = tinymce.get(textarea.id);
      if (ed) {
        ed.save();
        return textarea.value;
      }
    }
    return textarea.value;
  };

  window.cpSyncAllEditorsBeforeSubmit = function () {
    if (typeof tinymce !== 'undefined' && tinymce.triggerSave) {
      tinymce.triggerSave();
    }
  };

  window.cpDestroyChapterEditor = destroyChapterEditor;

  window.cpBindChapterRow = function (row) {
    if (!row || row.querySelector('[data-cp-chapter-rich-bound]')) return;
    row.setAttribute('data-cp-chapter-rich-bound', '1');
    var ta = row.querySelector('.cp-chapter-html');
    if (!ta) return;
    if (!ta.id) {
      chapterSeq += 1;
      ta.id = 'cp-chapter-html-' + chapterSeq;
    }
    var btn = row.querySelector('[data-cp-chapter-visual-toggle]');
    if (!btn) return;
    btn.addEventListener('click', function () {
      var on = btn.getAttribute('aria-pressed') === 'true';
      if (on) {
        destroyChapterEditor(ta);
        btn.setAttribute('aria-pressed', 'false');
        btn.classList.remove('is-on');
        btn.textContent = 'Éditeur visuel';
      } else {
        loadTinyMce(function () {
          initChapterEditor(ta);
          btn.setAttribute('aria-pressed', 'true');
          btn.classList.add('is-on');
          btn.textContent = 'Code source';
        });
      }
    });
  };

  root.addEventListener('click', function (ev) {
    var snippetBtn = ev.target.closest('[data-cp-snippet]');
    if (snippetBtn) {
      ev.preventDefault();
      var key = snippetBtn.getAttribute('data-cp-snippet');
      var html = SNIPPETS[key];
      if (!html) return;
      var ta = resolveSnippetTarget(snippetBtn);
      insertAtCursor(ta, html);
      return;
    }
    var wrapBtn = ev.target.closest('[data-cp-wrap-prose]');
    if (wrapBtn) {
      ev.preventDefault();
      wrapSelectionProse(resolveSnippetTarget(wrapBtn));
      return;
    }
  });

  var presetApply = document.getElementById('cp-preset-apply');
  var presetSelect = document.getElementById('cp-preset-select');
  if (presetApply && presetSelect) {
    presetApply.addEventListener('click', function () {
      var key = presetSelect.value;
      var html = PRESETS[key];
      if (!html) return;
      var ta = document.getElementById('cp-html');
      insertAtCursor(ta, html);
    });
  }

  var introVis = document.getElementById('cp-intro-visual-toggle');
  if (introVis) {
    introVis.addEventListener('click', function () {
      var ta = document.getElementById('cp-html');
      if (!ta) return;
      if (isFullDocument(ta.value)) {
        alert('Document HTML complet : utilisez l’onglet code ou extrayez le corps avant l’éditeur visuel.');
        return;
      }
      var on = introVis.getAttribute('aria-pressed') === 'true';
      if (on) {
        destroyIntroEditor();
        introVis.setAttribute('aria-pressed', 'false');
        introVis.classList.remove('is-on');
        introVis.textContent = 'Éditeur visuel';
      } else {
        loadTinyMce(function () {
          initIntroEditor();
          introVis.setAttribute('aria-pressed', 'true');
          introVis.classList.add('is-on');
          introVis.textContent = 'Code source';
        });
      }
    });
  }

  var form = document.getElementById('cp-main-form');
  if (form) {
    form.addEventListener(
      'submit',
      function () {
        window.cpSyncAllEditorsBeforeSubmit();
        if (typeof window.cpSyncHandbookJsonBeforeSubmit === 'function') {
          window.cpSyncHandbookJsonBeforeSubmit();
        }
      },
      true
    );
  }

  var radios = root.querySelectorAll('input[name="doc_structure"]');
  radios.forEach(function (r) {
    r.addEventListener('change', function () {
      document.querySelectorAll('.cp-chapter-html').forEach(function (ta) {
        destroyChapterEditor(ta);
      });
      var b = document.querySelectorAll('[data-cp-chapter-visual-toggle]');
      b.forEach(function (btn) {
        btn.setAttribute('aria-pressed', 'false');
        btn.classList.remove('is-on');
        btn.textContent = 'Éditeur visuel';
      });
      destroyIntroEditor();
      var iv = document.getElementById('cp-intro-visual-toggle');
      if (iv) {
        iv.setAttribute('aria-pressed', 'false');
        iv.classList.remove('is-on');
        iv.textContent = 'Éditeur visuel';
      }
    });
  });

  document.querySelectorAll('.cp-chapter').forEach(function (row) {
    window.cpBindChapterRow(row);
  });
})();
