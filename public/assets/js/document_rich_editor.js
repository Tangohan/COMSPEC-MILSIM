/**
 * Éditeur riche TinyMCE pour la publication documentaire.
 */
(function () {
  'use strict';

  var TINYMCE_VER = '7.6.0';
  var TINYMCE_BASE = 'https://cdn.jsdelivr.net/npm/tinymce@' + TINYMCE_VER;
  var form = document.getElementById('doc-publish-form') || document.getElementById('doc-version-form');
  var ta = document.getElementById('doc-body-html');
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
      console.warn('Éditeur riche indisponible.');
    };
    document.head.appendChild(s);
  }

  function initEditor() {
    if (typeof tinymce === 'undefined') return;
    if (tinymce.get('doc-body-html')) return;
    tinymce.init({
      selector: '#doc-body-html',
      base_url: TINYMCE_BASE,
      suffix: '.min',
      license_key: 'gpl',
      promotion: false,
      branding: false,
      menubar: false,
      statusbar: true,
      resize: true,
      height: 480,
      relative_urls: false,
      remove_script_host: false,
      entity_encoding: 'raw',
      plugins: 'lists link table autoresize hr',
      toolbar:
        'undo redo | blocks | bold italic underline | bullist numlist | link table hr | removeformat',
      block_formats: 'Paragraphe=p; Titre 2=h2; Titre 3=h3; Titre 4=h4',
      valid_elements:
        '@[id|class|colspan|rowspan],a[href|title|rel],p,br,h2,h3,h4,ul,ol,li,strong/b,em/i,u,blockquote,table,thead,tbody,tr,th,td,div,span,hr',
      invalid_elements: 'script,iframe,object,embed,form,input,button,textarea,select,option,style',
      content_style:
        'body{font-family:Inter,system-ui,sans-serif;font-size:16px;line-height:1.65;color:#0f172a;padding:10px 14px;}'
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
