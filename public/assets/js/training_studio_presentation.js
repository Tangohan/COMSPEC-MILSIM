/**
 * Studio formation — onglet Présentation : uploads, bibliothèque images site, aperçu apprenant.
 */
(function () {
  'use strict';

  var form = document.getElementById('studio-presentation-form');
  if (!form) return;

  var MAX_IMAGE = 4 * 1024 * 1024;
  var MAX_AUDIO = 12 * 1024 * 1024;
  var IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
  var AUDIO_TYPES = ['audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/mp4', 'audio/x-m4a', 'audio/m4a'];

  var libraryDialog = document.getElementById('studio-pres-library');
  var previewDialog = document.getElementById('studio-pres-preview');
  var libraryTargetKey = null;
  var objectUrls = [];

  function revokeLater(url) {
    if (url && url.indexOf('blob:') === 0) objectUrls.push(url);
  }

  function mediaField(key) {
    return form.querySelector('[data-pres-media="' + key + '"]');
  }

  function setImagePreview(key, url) {
    var field = mediaField(key);
    if (!field) return;
    var img = field.querySelector('[data-pres-preview-img="' + key + '"]');
    var empty = field.querySelector('[data-pres-preview-empty="' + key + '"]');
    var removeBtn = field.querySelector('[data-pres-remove-btn="' + key + '"]');
    var removeInput = field.querySelector('[data-pres-remove="' + key + '"]');
    if (img) {
      if (url) {
        img.src = url;
        img.classList.remove('hidden');
        if (empty) empty.classList.add('hidden');
      } else {
        img.removeAttribute('src');
        img.classList.add('hidden');
        if (empty) empty.classList.remove('hidden');
      }
    }
    if (removeBtn) removeBtn.classList.toggle('hidden', !url);
    if (removeInput) removeInput.value = '0';
  }

  function setAudioPreview(key, url) {
    var field = mediaField(key);
    if (!field) return;
    var audio = field.querySelector('[data-pres-audio="' + key + '"]');
    var empty = field.querySelector('[data-pres-audio-empty="' + key + '"]');
    var removeBtn = field.querySelector('[data-pres-remove-btn="' + key + '"]');
    var removeInput = field.querySelector('[data-pres-remove="' + key + '"]');
    if (audio) {
      if (url) {
        audio.src = url;
        audio.classList.remove('hidden');
        if (empty) empty.classList.add('hidden');
      } else {
        audio.removeAttribute('src');
        audio.classList.add('hidden');
        if (empty) empty.classList.remove('hidden');
      }
    }
    if (removeBtn) removeBtn.classList.toggle('hidden', !url);
    if (removeInput) removeInput.value = '0';
  }

  function setPath(key, path) {
    var field = mediaField(key);
    if (!field) return;
    var pathInput = field.querySelector('[data-pres-path="' + key + '"]');
    if (pathInput) pathInput.value = path || '';
  }

  function clearFileInput(key) {
    var field = mediaField(key);
    if (!field) return;
    var input = field.querySelector('[data-pres-file="' + key + '"]');
    if (input) input.value = '';
  }

  function handleImageFile(key, file) {
    if (!file) return;
    if (IMAGE_TYPES.indexOf(file.type) === -1) {
      window.alert('Cette image n’est pas dans un format pris en charge. Utilisez un JPG, PNG, WEBP ou GIF.');
      clearFileInput(key);
      return;
    }
    if (file.size > MAX_IMAGE) {
      window.alert('Cette image est trop volumineuse (maximum 4 Mo).');
      clearFileInput(key);
      return;
    }
    var url = URL.createObjectURL(file);
    revokeLater(url);
    setImagePreview(key, url);
    setPath(key, '');
    var field = mediaField(key);
    var filenameEl = field && field.querySelector('[data-pres-filename="' + key + '"]');
    if (filenameEl) {
      filenameEl.textContent = file.name + ' — ' + Math.max(1, Math.round(file.size / 1024)) + ' Ko';
    }
  }

  function handleAudioFile(key, file) {
    if (!file) return;
    var okType = AUDIO_TYPES.indexOf(file.type) !== -1 || /\.(mp3|ogg|wav|m4a)$/i.test(file.name || '');
    if (!okType) {
      window.alert('Ce fichier audio n’est pas dans un format pris en charge. Utilisez un MP3, OGG, WAV ou M4A.');
      clearFileInput(key);
      return;
    }
    if (file.size > MAX_AUDIO) {
      window.alert('Ce fichier audio est trop volumineux (maximum 12 Mo).');
      clearFileInput(key);
      return;
    }
    var url = URL.createObjectURL(file);
    revokeLater(url);
    setAudioPreview(key, url);
    setPath(key, '');
    var field = mediaField(key);
    var filenameEl = field && field.querySelector('[data-pres-filename="' + key + '"]');
    if (filenameEl) {
      filenameEl.textContent = file.name + ' — ' + Math.max(1, Math.round(file.size / 1024)) + ' Ko';
    }
  }

  form.querySelectorAll('[data-pres-media]').forEach(function (field) {
    var key = field.getAttribute('data-pres-media');
    var kind = field.getAttribute('data-pres-kind') || 'image';
    var input = field.querySelector('[data-pres-file="' + key + '"]');
    var dropZone = field.querySelector('[data-pres-dropzone="' + key + '"]');
    var removeBtn = field.querySelector('[data-pres-remove-btn="' + key + '"]');
    var removeInput = field.querySelector('[data-pres-remove="' + key + '"]');
    var filenameEl = field.querySelector('[data-pres-filename="' + key + '"]');
    var openLib = field.querySelector('[data-pres-open-library="' + key + '"]');

    if (input) {
      input.addEventListener('change', function () {
        if (!input.files || !input.files[0]) return;
        if (kind === 'audio') handleAudioFile(key, input.files[0]);
        else handleImageFile(key, input.files[0]);
      });
    }

    if (dropZone) {
      ['dragenter', 'dragover'].forEach(function (evt) {
        dropZone.addEventListener(evt, function (e) {
          e.preventDefault();
          dropZone.classList.add('border-emerald-500', 'bg-emerald-50');
        });
      });
      ['dragleave', 'drop'].forEach(function (evt) {
        dropZone.addEventListener(evt, function (e) {
          e.preventDefault();
          dropZone.classList.remove('border-emerald-500', 'bg-emerald-50');
        });
      });
      dropZone.addEventListener('drop', function (e) {
        if (!e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files[0] || !input) return;
        var dt = new DataTransfer();
        dt.items.add(e.dataTransfer.files[0]);
        input.files = dt.files;
        if (kind === 'audio') handleAudioFile(key, e.dataTransfer.files[0]);
        else handleImageFile(key, e.dataTransfer.files[0]);
      });
    }

    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        clearFileInput(key);
        setPath(key, '');
        if (kind === 'audio') setAudioPreview(key, '');
        else setImagePreview(key, '');
        if (filenameEl) filenameEl.textContent = '';
        if (removeInput) removeInput.value = '1';
        removeBtn.classList.add('hidden');
      });
    }

    if (openLib && libraryDialog) {
      openLib.addEventListener('click', function () {
        libraryTargetKey = key;
        if (typeof libraryDialog.showModal === 'function') libraryDialog.showModal();
      });
    }
  });

  if (libraryDialog) {
    var search = document.getElementById('studio-pres-library-search');
    var closeLib = libraryDialog.querySelector('[data-pres-library-close]');
    if (closeLib) {
      closeLib.addEventListener('click', function () {
        libraryDialog.close();
        libraryTargetKey = null;
      });
    }
    libraryDialog.addEventListener('click', function (e) {
      if (e.target === libraryDialog) {
        libraryDialog.close();
        libraryTargetKey = null;
      }
    });
    if (search) {
      search.addEventListener('input', function () {
        var q = (search.value || '').trim().toLowerCase();
        libraryDialog.querySelectorAll('[data-pres-lib-label]').forEach(function (btn) {
          var label = btn.getAttribute('data-pres-lib-label') || '';
          btn.classList.toggle('hidden', q !== '' && label.indexOf(q) === -1);
        });
      });
    }
    libraryDialog.querySelectorAll('[data-pres-lib-path]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!libraryTargetKey) return;
        var path = btn.getAttribute('data-pres-lib-path') || '';
        var url = btn.getAttribute('data-pres-lib-url') || '';
        clearFileInput(libraryTargetKey);
        setPath(libraryTargetKey, path);
        setImagePreview(libraryTargetKey, url);
        var field = mediaField(libraryTargetKey);
        var filenameEl = field && field.querySelector('[data-pres-filename="' + libraryTargetKey + '"]');
        if (filenameEl) filenameEl.textContent = 'Image du site sélectionnée';
        libraryDialog.close();
        libraryTargetKey = null;
      });
    });
  }

  function currentPreviewSrc(key) {
    var field = mediaField(key);
    if (!field) return '';
    var img = field.querySelector('[data-pres-preview-img="' + key + '"]');
    if (img && !img.classList.contains('hidden') && img.getAttribute('src')) return img.getAttribute('src');
    return '';
  }

  function fillPreview() {
    if (!previewDialog) return;
    var accent = (form.querySelector('[data-pres-accent]') || {}).value || '#10b981';
    var fontSel = form.querySelector('[data-pres-font]');
    var radiusSel = form.querySelector('[data-pres-radius]');
    var fontCss = fontSel && fontSel.selectedOptions[0]
      ? (fontSel.selectedOptions[0].getAttribute('data-font-css') || 'Inter, system-ui, sans-serif')
      : 'Inter, system-ui, sans-serif';
    var radiusCss = radiusSel && radiusSel.selectedOptions[0]
      ? (radiusSel.selectedOptions[0].getAttribute('data-radius-css') || '1.25rem')
      : '1.25rem';
    var title = form.getAttribute('data-pres-course-title') || 'Formation';
    var loaderTitle = (form.querySelector('[data-pres-loader-title]') || {}).value || 'Mise en place';
    var loaderBody = (form.querySelector('[data-pres-loader-body]') || {}).value || '';

    var card = previewDialog.querySelector('[data-prev-theme-card]');
    if (card) {
      card.style.setProperty('--lms-accent', accent);
      card.style.fontFamily = fontCss;
      card.style.borderRadius = radiusCss;
    }

    var setPrevImg = function (sel, emptySel, src) {
      var img = previewDialog.querySelector(sel);
      var empty = previewDialog.querySelector(emptySel);
      if (!img) return;
      if (src) {
        img.src = src;
        img.classList.remove('hidden');
        if (empty) empty.classList.add('hidden');
      } else {
        img.removeAttribute('src');
        img.classList.add('hidden');
        if (empty) empty.classList.remove('hidden');
      }
    };

    setPrevImg('[data-prev-loader-img]', '[data-prev-loader-empty]', currentPreviewSrc('loader'));
    setPrevImg('[data-prev-banner-img]', null, currentPreviewSrc('banner'));
    setPrevImg('[data-prev-thumb-img]', '[data-prev-thumb-empty]', currentPreviewSrc('thumbnail'));

    var tEl = previewDialog.querySelector('[data-prev-loader-title]');
    if (tEl) tEl.textContent = loaderTitle || 'Mise en place';
    var bEl = previewDialog.querySelector('[data-prev-loader-body]');
    if (bEl) bEl.textContent = loaderBody;
    var cEl = previewDialog.querySelector('[data-prev-course-title]');
    if (cEl) cEl.textContent = title;
  }

  var openPreview = document.getElementById('studio-pres-preview-open');
  if (openPreview && previewDialog) {
    openPreview.addEventListener('click', function () {
      fillPreview();
      if (typeof previewDialog.showModal === 'function') previewDialog.showModal();
    });
    var closePrev = previewDialog.querySelector('[data-pres-preview-close]');
    if (closePrev) closePrev.addEventListener('click', function () { previewDialog.close(); });
    previewDialog.addEventListener('click', function (e) {
      if (e.target === previewDialog) previewDialog.close();
    });
  }

  window.addEventListener('beforeunload', function () {
    objectUrls.forEach(function (u) {
      try { URL.revokeObjectURL(u); } catch (e) {}
    });
  });
})();
