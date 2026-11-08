/**
 * Centre de diffusion du back-office, sans rechargement de page.
 *  - Nouveau message, Modifier, Retirer : le formulaire du dépliant s'ouvre dans la fenêtre de la page (pas de boîte de confirmation du navigateur).
 *  - Écriture : fetch vers la route du formulaire (réponse JSON, jeton CSRF du formulaire), puis la zone
 *    [data-bc-live] est remplacée par celle du serveur. Une erreur reste affichée : rien n'est annoncé comme réussi
 *    sans réponse positive du serveur ; un envoi incomplet est signalé canal par canal.
 *  - Nouveau message : canaux et cibles proposés selon le type, aperçu par cible demandé au serveur à chaque changement.
 *  - Filtres : la liste est rechargée en arrière-plan et l'adresse de la page suit les filtres.
 * Sans ce script, les dépliants et les formulaires de la page fonctionnent tels quels (repli).
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-bc]');
  if (!root || typeof window.fetch !== 'function' || typeof window.FormData !== 'function') return;

  var live = root.querySelector('[data-bc-live]');
  var dlg = root.querySelector('[data-bc-dialog]');
  if (!live || !dlg || typeof dlg.showModal !== 'function') return;
  var dlgBody = dlg.querySelector('[data-bc-dlg-body]');
  var dlgError = dlg.querySelector('[data-bc-error]');
  var status = root.querySelector('[data-bc-status]');
  var pageUrl = root.getAttribute('data-page-url') || '';
  var previewUrl = root.getAttribute('data-preview-url') || '';
  var busy = false;
  var opener = null;

  function toast(variant, message) {
    if (status) status.textContent = message;
    if (typeof window.athToast === 'function') window.athToast(variant, message);
  }

  // ——— Échanges avec le serveur ———
  function failure(message, extra) {
    var err = new Error(message);
    err.data = extra || {};
    return err;
  }
  function request(url, options) {
    var opts = options || {};
    opts.credentials = 'same-origin';
    opts.headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    return fetch(url, opts).catch(function (e) {
      if (e && e.name === 'AbortError') throw e;
      throw failure('Le serveur ne répond pas : rien n’a été envoyé. Vérifiez votre connexion, puis réessayez.');
    }).then(function (res) {
      var type = res.headers.get('Content-Type') || '';
      if (type.indexOf('application/json') === -1) {
        throw failure('Votre session a peut-être expiré. Rechargez la page, puis réessayez.', { reload: true });
      }
      return res.json().catch(function () {
        throw failure('Réponse illisible du serveur. Rechargez la page pour vérifier ce qui a été enregistré.', { reload: true });
      }).then(function (json) {
        if (!res.ok || !json || json.ok !== true) {
          throw failure((json && json.message) || 'Le serveur a refusé l’enregistrement. Réessayez dans un instant.', json || {});
        }
        return json;
      });
    });
  }
  function swap(html) {
    if (typeof html !== 'string') {
      toast('warning', 'Enregistré, mais la liste n’a pas pu être actualisée. Rechargez la page pour voir le résultat.');
      return;
    }
    live.innerHTML = html;
    setupFilters();
  }

  // ——— Fenêtre ———
  function setError(message, reload) {
    dlgError.hidden = !message;
    dlgError.textContent = message || '';
    if (message && reload) {
      var link = document.createElement('a');
      link.href = window.location.href;
      link.textContent = 'Recharger la page';
      dlgError.appendChild(document.createTextNode(' '));
      dlgError.appendChild(link);
    }
  }
  function closeDialog() {
    if (dlg.open) dlg.close();
  }
  function setBusy(form, on, label) {
    busy = on;
    dlg.setAttribute('aria-busy', on ? 'true' : 'false');
    dlg.querySelectorAll('button').forEach(function (b) { b.disabled = on; });
    var submit = form.querySelector('button[type="submit"]:not([data-bc-preview-btn])');
    if (!submit) return;
    if (on) {
      submit.setAttribute('data-label', submit.textContent);
      submit.textContent = label || 'Enregistrement…';
    } else if (submit.hasAttribute('data-label')) {
      submit.textContent = submit.getAttribute('data-label');
      submit.removeAttribute('data-label');
    }
  }
  dlg.addEventListener('click', function (e) {
    if (busy) return;
    if (e.target === dlg || e.target.closest('[data-bc-close]')) closeDialog();
  });
  dlg.addEventListener('cancel', function (e) {
    if (busy) e.preventDefault();
  });
  dlg.addEventListener('close', function () {
    dlgBody.innerHTML = '';
    setError('');
    if (opener && document.contains(opener)) opener.focus({ preventScroll: true });
    opener = null;
  });

  function openFold(fold) {
    var source = fold.querySelector('form[data-bc-form]');
    if (!source) return null;
    var form = source.cloneNode(true);
    dlg.querySelector('[data-bc-dlg-kicker]').textContent = fold.getAttribute('data-kicker') || '';
    dlg.querySelector('[data-bc-dlg-title]').textContent = fold.getAttribute('data-title') || '';
    setError('');
    dlgBody.innerHTML = '';
    dlgBody.appendChild(form);
    form.querySelectorAll('[data-bc-close]').forEach(function (b) { b.hidden = false; });
    form.querySelectorAll('[data-count]').forEach(setupCounter);
    if (form.hasAttribute('data-bc-compose-form')) setupCompose(form);
    opener = fold.querySelector('summary');
    try {
      dlg.showModal();
    } catch (e) {
      dlgBody.innerHTML = '';
      return null;
    }
    var first = form.querySelector('input[type="text"], textarea, button[type="submit"]');
    if (first) first.focus();
    return form;
  }

  function setupCounter(field) {
    var label = field.closest('label');
    var out = label && label.querySelector('[data-count-out]');
    if (!out) return;
    var max = parseInt(field.getAttribute('maxlength'), 10) || 0;
    var update = function () {
      var n = Array.from(field.value).length;
      out.textContent = n + ' / ' + max;
      out.classList.toggle('is-over', n >= max);
    };
    field.addEventListener('input', update);
    update();
  }

  // ——— Nouveau message ———
  function checkedType(form) {
    var r = form.querySelector('input[name="type"]:checked');
    return r ? r : null;
  }
  function setupCompose(form) {
    var hint = form.querySelector('[data-bc-type-hint]');
    var target = form.querySelector('[data-bc-target]');
    var timer = 0;
    var ctrl = null;
    var pv = form.querySelector('[data-bc-preview]');

    function syncChannels() {
      var type = checkedType(form);
      var allowed = type ? (type.getAttribute('data-channels') || '').split(',') : [];
      if (hint && type) hint.textContent = type.closest('label').getAttribute('title') || '';
      var any = false;
      form.querySelectorAll('[data-bc-channel]').forEach(function (row) {
        var box = row.querySelector('input');
        var on = allowed.indexOf(row.getAttribute('data-bc-channel')) !== -1;
        row.classList.toggle('is-off', !on);
        if (!on) box.checked = false;
        if (box.checked) any = true;
      });
      if (!any) {
        allowed.some(function (c) {
          var box = form.querySelector('[data-bc-channel="' + c + '"] input');
          if (box && !box.disabled) { box.checked = true; return true; }
          return false;
        });
      }
      syncExtras();
    }
    function syncExtras() {
      var on = {};
      form.querySelectorAll('input[name="channels[]"]:checked').forEach(function (b) { on[b.value] = true; });
      form.querySelectorAll('[data-bc-only]').forEach(function (el) {
        el.classList.toggle('is-off', !on[el.getAttribute('data-bc-only')]);
      });
    }
    function syncTarget() {
      var value = target ? target.value : 'all';
      form.querySelectorAll('[data-bc-target-for]').forEach(function (el) {
        el.classList.toggle('is-off', el.getAttribute('data-bc-target-for') !== value);
      });
    }
    var q = form.querySelector('[data-bc-person-q]');
    if (q) {
      q.hidden = false;
      q.addEventListener('input', function () {
        var needle = q.value.trim().toLowerCase();
        form.querySelectorAll('[data-bc-people] label').forEach(function (row) {
          row.hidden = needle !== '' && row.textContent.toLowerCase().indexOf(needle) === -1 && !row.querySelector('input').checked;
        });
      });
    }

    function preview() {
      if (!pv) return;
      if (ctrl) ctrl.abort();
      ctrl = typeof AbortController === 'function' ? new AbortController() : null;
      var opts = { method: 'POST', body: new FormData(form) };
      if (ctrl) opts.signal = ctrl.signal;
      pv.setAttribute('aria-busy', 'true');
      request(previewUrl, opts).then(function (json) {
        if (typeof json.html === 'string') pv.innerHTML = json.html;
      }).catch(function (err) {
        if (err && err.name === 'AbortError') return;
        pv.textContent = err.message;
      }).then(function () {
        pv.removeAttribute('aria-busy');
      });
    }
    function schedule() {
      window.clearTimeout(timer);
      timer = window.setTimeout(preview, 450);
    }

    form.addEventListener('change', function (e) {
      if (e.target.name === 'type') syncChannels();
      if (e.target.name === 'channels[]') syncExtras();
      if (e.target === target) syncTarget();
      schedule();
    });
    form.addEventListener('input', function (e) {
      if (e.target.name === 'title' || e.target.name === 'body') schedule();
    });
    form.querySelector('[data-bc-preview-btn]').addEventListener('click', function (e) {
      e.preventDefault();
      preview();
    });
    syncChannels();
    syncTarget();
    form.__bcPreview = preview;
  }

  function fillCompose(form, data) {
    if (!form || !data) return;
    var type = form.querySelector('input[name="type"][value="' + data.type + '"]');
    if (type && !type.disabled) {
      type.checked = true;
      type.dispatchEvent(new Event('change', { bubbles: true }));
    }
    form.querySelector('[name="title"]').value = data.title || '';
    form.querySelector('[name="body"]').value = data.body || '';
    var imp = form.querySelector('input[name="importance"][value="' + (data.importance || 'info') + '"]');
    if (imp) imp.checked = true;
    if (Array.isArray(data.channels) && data.channels.length) {
      form.querySelectorAll('input[name="channels[]"]').forEach(function (b) {
        b.checked = !b.disabled && data.channels.indexOf(b.value) !== -1;
      });
    }
    form.querySelectorAll('[data-count]').forEach(function (f) { f.dispatchEvent(new Event('input', { bubbles: true })); });
    form.dispatchEvent(new Event('change', { bubbles: true }));
  }

  // ——— Envoi des formulaires de la fenêtre ———
  dlg.addEventListener('submit', function (e) {
    var form = e.target.closest('form[data-bc-form]');
    if (!form) return;
    e.preventDefault();
    if (busy) return;
    var submitter = e.submitter || null;
    if (submitter && submitter.hasAttribute('data-bc-preview-btn')) {
      if (form.__bcPreview) form.__bcPreview();
      return;
    }
    var compose = form.hasAttribute('data-bc-compose-form');
    setError('');
    setBusy(form, true, compose ? 'Envoi…' : 'Enregistrement…');
    request(form.getAttribute('action'), { method: 'POST', body: new FormData(form) }).then(function (json) {
      setBusy(form, false);
      swap(json.html);
      closeDialog();
      toast('success', json.message || 'Enregistré.');
    }).catch(function (err) {
      setBusy(form, false);
      var data = err.data || {};
      if (typeof data.html === 'string') swap(data.html);
      if (compose && Array.isArray(data.results) && data.results.length) {
        // Envoi incomplet : une partie est déjà partie. La fenêtre se ferme pour ne pas renvoyer deux fois.
        closeDialog();
        toast('error', err.message);
        return;
      }
      setError(err.message, !!data.reload);
    });
  });

  // ——— Ouverture des dépliants et duplication ———
  root.addEventListener('click', function (e) {
    var summary = e.target.closest('[data-bc-fold] > summary');
    if (summary) {
      e.preventDefault();
      openFold(summary.parentElement);
      return;
    }
    var dup = e.target.closest('[data-bc-duplicate]');
    if (dup) {
      var compose = root.querySelector('[data-bc-compose]');
      if (!compose) return;
      e.preventDefault();
      var data = null;
      try { data = JSON.parse(dup.getAttribute('data-bc-duplicate') || 'null'); } catch (err) { data = null; }
      var form = openFold(compose);
      if (form) {
        dlg.querySelector('[data-bc-dlg-title]').textContent = 'Dupliquer : nouveau message';
        opener = dup;
        fillCompose(form, data);
      }
    }
  });

  // ——— Filtres ———
  var filterCtrl = null;
  function setupFilters() {
    var f = live.querySelector('[data-bc-filters]');
    if (!f) return;
    f.querySelectorAll('[data-bc-nojs]').forEach(function (b) { b.classList.add('is-hidden'); });
  }
  function loadList(params) {
    if (filterCtrl) filterCtrl.abort();
    filterCtrl = typeof AbortController === 'function' ? new AbortController() : null;
    var u = new URL(pageUrl, window.location.href);
    params.forEach(function (value, key) { if (value !== '') u.searchParams.set(key, value); });
    var shown = u.toString();
    u.searchParams.set('fragment', '1');
    live.setAttribute('aria-busy', 'true');
    request(u.toString(), filterCtrl ? { signal: filterCtrl.signal } : {}).then(function (json) {
      swap(json.html);
      if (window.history && window.history.replaceState) window.history.replaceState(null, '', shown + '#diffusions');
      var count = live.querySelector('[data-bc-count]');
      if (count && status) status.textContent = count.textContent;
    }).catch(function (err) {
      if (err && err.name === 'AbortError') return;
      toast('error', err.message);
    }).then(function () {
      live.removeAttribute('aria-busy');
    });
  }
  live.addEventListener('change', function (e) {
    var f = e.target.closest('[data-bc-filters]');
    if (f) loadList(new FormData(f));
  });
  live.addEventListener('submit', function (e) {
    var f = e.target.closest('[data-bc-filters]');
    if (!f) return;
    e.preventDefault();
    loadList(new FormData(f));
  });
  live.addEventListener('click', function (e) {
    var reset = e.target.closest('[data-bc-reset]');
    if (!reset) return;
    e.preventDefault();
    loadList(new FormData());
  });

  setupFilters();
  // Page ouverte sur un formulaire prérempli (lien « Dupliquer » ouvert à part) : il passe dans la fenêtre.
  var openCompose = root.querySelector('[data-bc-compose][open]');
  if (openCompose) {
    openCompose.open = false;
    openFold(openCompose);
  }
})();
