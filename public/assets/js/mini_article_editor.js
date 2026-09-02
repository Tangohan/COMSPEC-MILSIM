/**
 * Éditeur riche TinyMCE pour les mini-articles communauté.
 */
(function () {
  'use strict';

  var TINYMCE_VER = '7.6.0';
  var TINYMCE_BASE = 'https://cdn.jsdelivr.net/npm/tinymce@' + TINYMCE_VER;
  var form = document.getElementById('mini-article-form');
  var ta = document.getElementById('ma-body');
  if (!form || !ta) return;

  function loadTinyMce(callback) {
    if (typeof window.tinymce !== 'undefined' && window.tinymce.init) {
      callback();
      return;
    }
    var s = document.createElement('script');
    s.src = TINYMCE_BASE + '/tinymce.min.js';
    s.async = true;
    s.onload = function () { callback(); };
    s.onerror = function () {
      console.warn('Éditeur riche indisponible : édition HTML brute.');
    };
    document.head.appendChild(s);
  }

  function initEditor() {
    if (typeof tinymce === 'undefined') return;
    if (tinymce.get('ma-body')) return;
    tinymce.init({
      selector: '#ma-body',
      base_url: TINYMCE_BASE,
      suffix: '.min',
      license_key: 'gpl',
      promotion: false,
      branding: false,
      menubar: false,
      statusbar: true,
      resize: true,
      height: 420,
      relative_urls: false,
      remove_script_host: false,
      entity_encoding: 'raw',
      plugins: 'lists link table autoresize image',
      toolbar:
        'undo redo | blocks | bold italic underline | bullist numlist | link image table | removeformat',
      block_formats: 'Paragraphe=p; Titre 2=h2; Titre 3=h3; Titre 4=h4',
      valid_elements:
        '@[id|class|colspan|rowspan],a[href|title|rel],p,br,h2,h3,h4,ul,ol,li,strong/b,em/i,u,blockquote,table,thead,tbody,tr,th,td,div,span,img[src|alt|width|height],hr',
      invalid_elements: 'script,iframe,object,embed,form,input,button,textarea,select,option,style',
      content_style:
        'body{font-family:Inter,system-ui,sans-serif;font-size:16px;line-height:1.65;color:#0f172a;padding:10px 14px;max-width:42rem;margin:0 auto;} img{max-width:100%;height:auto;border-radius:8px;}'
    });
  }

  form.addEventListener(
    'submit',
    function () {
      if (typeof tinymce !== 'undefined' && tinymce.triggerSave) {
        tinymce.triggerSave();
      }
    },
    true
  );

  loadTinyMce(initEditor);
})();
