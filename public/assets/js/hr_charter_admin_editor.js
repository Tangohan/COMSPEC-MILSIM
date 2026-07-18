/**
 * Éditeur riche + aperçu pour la charte RH formations (pilotage LMS).
 * Conserves name="body_html" pour la sauvegarde serveur existante.
 */
(function () {
  'use strict';

  var TINYMCE_VER = '7.6.0';
  var TINYMCE_BASE = 'https://cdn.jsdelivr.net/npm/tinymce@' + TINYMCE_VER;

  var form = document.getElementById('hr-charter-admin-form');
  var ta = document.getElementById('hr-charter-body');
  var preview = document.getElementById('hr-charter-preview');
  if (!form || !ta) return;

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
      console.warn('Éditeur riche indisponible : édition en texte simple.');
      refreshPreview();
    };
    document.head.appendChild(s);
  }

  function getBodyHtml() {
    if (typeof tinymce !== 'undefined') {
      var ed = tinymce.get('hr-charter-body');
      if (ed) {
        ed.save();
        return ta.value;
      }
    }
    return ta.value;
  }

  function refreshPreview() {
    if (!preview) return;
    var html = getBodyHtml();
    preview.innerHTML = html && html.trim() !== ''
      ? html
      : '<p class="text-slate-400 text-sm italic">L’aperçu apparaîtra ici au fur et à mesure de la rédaction.</p>';
  }

  function initEditor() {
    if (typeof tinymce === 'undefined') return;
    if (tinymce.get('hr-charter-body')) return;
    tinymce.init({
      selector: '#hr-charter-body',
      base_url: TINYMCE_BASE,
      suffix: '.min',
      license_key: 'gpl',
      promotion: false,
      branding: false,
      menubar: false,
      statusbar: true,
      resize: true,
      height: 380,
      autoresize_bottom_margin: 16,
      relative_urls: false,
      remove_script_host: false,
      entity_encoding: 'raw',
      plugins: 'lists link table autoresize',
      toolbar:
        'undo redo | blocks | bold italic | bullist numlist | link table | removeformat',
      block_formats: 'Paragraphe=p; Titre 2=h2; Titre 3=h3; Titre 4=h4',
      valid_elements:
        '@[id|class|colspan|rowspan],a[href|title|rel],p,br,h2,h3,h4,ul,ol,li,strong/b,em/i,blockquote,table,thead,tbody,tr,th,td,div,span',
      invalid_elements: 'script,iframe,object,embed,form,input,button,textarea,select,option,style',
      content_style:
        'body{font-family:Inter,system-ui,sans-serif;font-size:16px;line-height:1.65;color:#0f172a;padding:10px 14px;max-width:40rem;margin:0 auto;}',
      setup: function (ed) {
        ed.on('change keyup undo redo SetContent', function () {
          ed.save();
          refreshPreview();
        });
      },
      init_instance_callback: function () {
        refreshPreview();
      }
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

  ta.addEventListener('input', refreshPreview);

  loadTinyMce(initEditor);
  refreshPreview();
})();
