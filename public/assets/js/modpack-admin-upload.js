/**
 * Upload admin de modpacks : zone glisser-déposer, validation locale,
 * envoi par morceaux avec barre de progression.
 */
(function () {
  'use strict';

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }

  function formatBytes(bytes) {
    bytes = Number(bytes) || 0;
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(1).replace('.', ',') + ' Go';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1).replace('.', ',') + ' Mo';
    if (bytes >= 1024) return (bytes / 1024).toFixed(1).replace('.', ',') + ' Ko';
    return bytes + ' o';
  }

  function allowedExt(name) {
    return /\.(zip|rar|7z)$/i.test(name || '');
  }

  function initForm(form) {
    if (!form || form.getAttribute('data-modpack-upload-ready') === '1') return;
    form.setAttribute('data-modpack-upload-ready', '1');

    var limits = {};
    try {
      limits = JSON.parse(form.getAttribute('data-upload-limits') || '{}') || {};
    } catch (e) {
      limits = {};
    }
    var maxBytes = Number(limits.max_bytes) || (2 * 1024 * 1024 * 1024);
    var chunkBytes = Number(limits.chunk_bytes) || (8 * 1024 * 1024);
    var csrf = (form.querySelector('input[name="_csrf_token"]') || {}).value || '';
    var initUrl = form.getAttribute('data-upload-init') || '';
    var chunkUrl = form.getAttribute('data-upload-chunk') || '';
    var finalizeUrl = form.getAttribute('data-upload-finalize') || '';

    var dropzone = $('[data-modpack-dropzone]', form);
    var fileInput = $('input[name="modpack_file"]', form);
    var stagedInput = $('input[name="staged_upload_id"]', form);
    var statusEl = $('[data-modpack-upload-status]', form);
    var progressWrap = $('[data-modpack-progress]', form);
    var progressBar = $('[data-modpack-progress-bar]', form);
    var fileMeta = $('[data-modpack-file-meta]', form);
    var clearBtn = $('[data-modpack-clear-file]', form);
    var submitBtn = form.querySelector('button[type="submit"]');
    var selectedFile = null;
    var uploading = false;

    function setStatus(msg, isError) {
      if (!statusEl) return;
      statusEl.textContent = msg || '';
      statusEl.classList.toggle('is-error', !!isError);
      statusEl.hidden = !msg;
    }

    function setProgress(pct) {
      if (!progressWrap || !progressBar) return;
      progressWrap.hidden = pct < 0;
      progressBar.style.width = Math.max(0, Math.min(100, pct)) + '%';
      progressWrap.setAttribute('aria-valuenow', String(Math.round(pct)));
    }

    function clearFile() {
      selectedFile = null;
      if (fileInput) fileInput.value = '';
      if (stagedInput) stagedInput.value = '';
      if (fileMeta) {
        fileMeta.hidden = true;
        fileMeta.textContent = '';
      }
      if (clearBtn) clearBtn.hidden = true;
      if (dropzone) dropzone.classList.remove('has-file');
      setProgress(-1);
      setStatus('');
    }

    function acceptFile(file) {
      if (!file) return;
      if (!allowedExt(file.name)) {
        setStatus('Format attendu : ZIP, RAR ou 7z.', true);
        return;
      }
      if (file.size > maxBytes) {
        setStatus('Fichier trop volumineux (max ' + (limits.max_label || '2 Go') + ').', true);
        return;
      }
      selectedFile = file;
      if (stagedInput) stagedInput.value = '';
      if (fileMeta) {
        fileMeta.hidden = false;
        fileMeta.textContent = file.name + ' · ' + formatBytes(file.size);
      }
      if (clearBtn) clearBtn.hidden = false;
      if (dropzone) dropzone.classList.add('has-file');
      setStatus('Fichier prêt. L’envoi démarre à la validation du formulaire.', false);
      setProgress(-1);
    }

    if (dropzone) {
      ['dragenter', 'dragover'].forEach(function (evName) {
        dropzone.addEventListener(evName, function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          dropzone.classList.add('is-dragover');
        });
      });
      ['dragleave', 'drop'].forEach(function (evName) {
        dropzone.addEventListener(evName, function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          dropzone.classList.remove('is-dragover');
        });
      });
      dropzone.addEventListener('drop', function (ev) {
        var files = ev.dataTransfer && ev.dataTransfer.files;
        if (files && files[0]) acceptFile(files[0]);
      });
      dropzone.addEventListener('click', function (ev) {
        if (ev.target.closest('[data-modpack-clear-file]')) return;
        if (fileInput) fileInput.click();
      });
      dropzone.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' || ev.key === ' ') {
          ev.preventDefault();
          if (fileInput) fileInput.click();
        }
      });
    }

    if (fileInput) {
      fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files[0]) acceptFile(fileInput.files[0]);
      });
    }
    if (clearBtn) {
      clearBtn.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        clearFile();
      });
    }

    function postForm(url, data) {
      return fetch(url, {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      }).then(function (r) {
        return r.json().then(function (j) {
          return { ok: r.ok, status: r.status, data: j || {} };
        }).catch(function () {
          return { ok: r.ok, status: r.status, data: {} };
        });
      });
    }

    function uploadChunked(file) {
      return postForm(initUrl, (function () {
        var fd = new FormData();
        fd.append('_csrf_token', csrf);
        fd.append('filename', file.name);
        fd.append('size', String(file.size));
        return fd;
      })()).then(function (res) {
        if (!res.ok || !res.data.success || !res.data.upload_id) {
          throw new Error((res.data && res.data.message) || 'Initialisation de l’upload impossible.');
        }
        var uploadId = res.data.upload_id;
        var size = file.size;
        var total = Math.max(1, Math.ceil(size / chunkBytes));
        var index = 0;

        function sendNext() {
          if (index >= total) {
            var fin = new FormData();
            fin.append('_csrf_token', csrf);
            fin.append('upload_id', uploadId);
            fin.append('chunk_total', String(total));
            setStatus('Assemblage du fichier…', false);
            return postForm(finalizeUrl, fin).then(function (fres) {
              if (!fres.ok || !fres.data.success) {
                throw new Error((fres.data && fres.data.message) || 'Assemblage impossible.');
              }
              if (stagedInput) stagedInput.value = uploadId;
              setProgress(100);
              setStatus('Archive prête (' + (fres.data.size_label || formatBytes(file.size)) + '). Enregistrement…', false);
              return uploadId;
            });
          }

          var start = index * chunkBytes;
          var end = Math.min(size, start + chunkBytes);
          var blob = file.slice(start, end);
          var fd = new FormData();
          fd.append('_csrf_token', csrf);
          fd.append('upload_id', uploadId);
          fd.append('chunk_index', String(index));
          fd.append('chunk_total', String(total));
          fd.append('chunk', blob, file.name + '.part' + index);

          return postForm(chunkUrl, fd).then(function (cres) {
            if (!cres.ok || !cres.data.success) {
              throw new Error((cres.data && cres.data.message) || ('Échec du morceau #' + index));
            }
            index += 1;
            setProgress((index / total) * 100);
            setStatus('Envoi… ' + Math.round((index / total) * 100) + '%', false);
            return sendNext();
          });
        }

        setProgress(0);
        setStatus('Envoi… 0 %', false);
        return sendNext();
      });
    }

    form.addEventListener('submit', function (ev) {
      if (uploading) {
        ev.preventDefault();
        return;
      }
      // Fichier déjà stagé, ou pas de nouveau fichier : laisser le POST classique.
      if (!selectedFile || (stagedInput && stagedInput.value)) {
        return;
      }
      if (!initUrl || !chunkUrl || !finalizeUrl) {
        return;
      }
      ev.preventDefault();
      uploading = true;
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.setAttribute('aria-busy', 'true');
      }
      uploadChunked(selectedFile)
        .then(function () {
          // Retirer le fichier du champ pour éviter un double envoi multipart.
          if (fileInput) fileInput.value = '';
          form.submit();
        })
        .catch(function (err) {
          uploading = false;
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.removeAttribute('aria-busy');
          }
          setStatus((err && err.message) || 'Échec de l’upload.', true);
        });
    });
  }

  document.querySelectorAll('form[data-modpack-upload]').forEach(initForm);
})();
