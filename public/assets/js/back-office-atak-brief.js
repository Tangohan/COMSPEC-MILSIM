/**
 * Consignes et kit ATAK, sans rechargement de page.
 *  - Ajouter, modifier, supprimer : le formulaire du dépliant s'ouvre dans la fenêtre. Déplacer et filtrer : directement.
 *  - Écriture : fetch vers la route du formulaire (réponse JSON), puis la zone [data-brief-live] est remplacée par celle
 *    du serveur. Une erreur reste affichée dans la fenêtre : rien n'est montré comme réussi sans réponse positive.
 *  - Sélecteur du kit : recherche dans le catalogue de la communauté.
 * Sans ce script, les dépliants et les formulaires de la page fonctionnent tels quels (repli).
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-brief]');
  if (!root || typeof window.fetch !== 'function') return;

  var live = root.querySelector('[data-brief-live]');
  var dlg = root.querySelector('[data-brief-dialog]');
  var dlgBody = dlg.querySelector('[data-brief-dlg-body]');
  var dlgError = dlg.querySelector('[data-brief-error]');
  var status = root.querySelector('[data-brief-status]');
  var pageUrl = root.getAttribute('data-page-url') || '';
  var catalogUrl = root.getAttribute('data-catalog-url') || '';
  var busy = false;
  var opener = null;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
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
      throw failure('Le serveur ne répond pas : rien n’a été modifié. Vérifiez votre connexion, puis réessayez.');
    }).then(function (res) {
      var type = res.headers.get('Content-Type') || '';
      if (type.indexOf('application/json') === -1) {
        // Page de connexion ou d'erreur à la place du JSON.
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
      toast('warning', 'Enregistré, mais l’affichage n’a pas pu être actualisé. Rechargez la page pour voir le résultat.');
      return;
    }
    live.innerHTML = html;
  }
  function currentGenre() {
    return new URL(window.location.href).searchParams.get('genre') || '';
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
  function setBusy(form, on) {
    busy = on;
    dlg.setAttribute('aria-busy', on ? 'true' : 'false');
    dlg.querySelectorAll('button').forEach(function (b) { b.disabled = on; });
    var submit = form.querySelector('button[type="submit"]');
    if (!submit) return;
    if (on) {
      submit.setAttribute('data-label', submit.textContent);
      submit.textContent = 'Enregistrement…';
    } else if (submit.hasAttribute('data-label')) {
      submit.textContent = submit.getAttribute('data-label');
      submit.removeAttribute('data-label');
    }
  }
  dlg.addEventListener('click', function (e) {
    if (busy) return;
    if (e.target === dlg || e.target.closest('[data-brief-close]')) closeDialog();
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
    var source = fold.querySelector('form[data-brief-form]');
    if (!source) return false;
    var form = source.cloneNode(true);
    dlg.querySelector('[data-brief-dlg-kicker]').textContent = fold.getAttribute('data-kicker') || '';
    dlg.querySelector('[data-brief-dlg-title]').textContent = fold.getAttribute('data-title') || '';
    setError('');
    dlgBody.innerHTML = '';
    dlgBody.appendChild(form);
    form.querySelectorAll('[data-brief-close]').forEach(function (b) { b.hidden = false; });
    form.querySelectorAll('[data-count]').forEach(setupCounter);
    form.querySelectorAll('[data-brief-pick]').forEach(setupPicker);
    opener = fold.querySelector('summary');
    try {
      dlg.showModal();
    } catch (e) {
      dlgBody.innerHTML = '';
      return false;
    }
    var first = form.querySelector('[data-pick-q]:not([hidden]), input[type="text"], textarea, button[type="submit"]');
    if (first) first.focus();
    return true;
  }

  // ——— Compteurs de caractères ———
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

  // ——— Sélecteur du catalogue ———
  function rowHtml(item, type, name, checked) {
    var icon = item.icon
      ? '<img src="' + esc(item.icon) + '" alt="" loading="lazy">'
      : '<span class="bo-brief-noicon" aria-hidden="true"></span>';
    return '<label class="bo-brief-opt"><input type="' + type + '" name="' + esc(name) + '" value="' + esc(item['class']) + '"' + (checked ? ' checked' : '') + '>'
      + '<span class="bo-brief-ico">' + icon + '</span>'
      + '<span class="bo-brief-opt__t"><b translate="no">' + esc(item.name) + '</b><code translate="no">' + esc(item['class']) + '</code>'
      + '<small>' + esc(item.kindLabel || '') + (item.mod ? ' · <span translate="no">' + esc(item.mod) + '</span>' : '') + '</small></span></label>';
  }
  function setupPicker(box) {
    var q = box.querySelector('[data-pick-q]');
    var list = box.querySelector('[data-pick-list]');
    var state = box.querySelector('[data-pick-status]');
    var type = box.getAttribute('data-brief-pick');
    var name = box.getAttribute('data-pick-name');
    var timer = 0;
    var ctrl = null;
    q.hidden = false;

    /** Les objets déjà cochés restent en tête de liste, quelle que soit la recherche. */
    function render(items) {
      var chosen = Array.prototype.filter.call(list.querySelectorAll('label'), function (row) {
        return row.querySelector('input').checked;
      });
      var kept = {};
      var html = chosen.map(function (row) {
        kept[row.querySelector('input').value.toLowerCase()] = true;
        return row.outerHTML.replace(/<input /, '<input checked ');
      }).join('');
      html += items.filter(function (it) { return !kept[String(it['class']).toLowerCase()]; }).map(function (it) {
        return rowHtml(it, type, name, false);
      }).join('');
      list.innerHTML = html;
    }
    function load() {
      if (ctrl) ctrl.abort();
      ctrl = typeof AbortController === 'function' ? new AbortController() : null;
      var u = new URL(catalogUrl, window.location.href);
      u.searchParams.set('q', q.value.trim());
      state.textContent = 'Recherche…';
      request(u.toString(), ctrl ? { signal: ctrl.signal } : {}).then(function (json) {
        var items = Array.isArray(json.items) ? json.items : [];
        render(items);
        state.textContent = items.length ? '' : (q.value.trim()
          ? 'Aucun objet ne correspond. S’il n’a pas encore été vu en jeu, utilisez l’option avancée.'
          : 'Le catalogue de la communauté est vide : utilisez l’option avancée.');
      }).catch(function (err) {
        if (err && err.name === 'AbortError') return;
        state.textContent = err.message;
      });
    }
    q.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(load, 250);
    });
    q.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') e.preventDefault();
    });
    if (type === 'radio' || q.value.trim() !== '') load();
  }

  // ——— Écritures ———
  function focusAfter(id) {
    var el = id ? document.getElementById(id) : null;
    if (!el) return;
    el.classList.add('is-saved');
    setTimeout(function () { el.classList.remove('is-saved'); }, 2000);
    el.focus({ preventScroll: true });
    el.scrollIntoView({ block: 'nearest' });
  }
  function submitInDialog(form) {
    if (busy) return;
    setError('');
    var body = new FormData(form);
    setBusy(form, true);
    request(form.action, { method: 'POST', body: body }).then(function (json) {
      setBusy(form, false);
      opener = null;
      closeDialog();
      swap(json.html);
      toast('success', json.message);
    }).catch(function (err) {
      setBusy(form, false);
      if (err.data && err.data.conflict) {
        // Un autre responsable est passé avant : on montre l'état réel, rien n'a été écrit.
        closeDialog();
        swap(err.data.html);
        toast('warning', err.message);
        return;
      }
      setError(err.message, err.data && err.data.reload);
    });
  }
  function submitMove(form, button) {
    if (form.getAttribute('data-sending') === '1') return;
    form.setAttribute('data-sending', '1');
    var body = new FormData(form);
    if (button && button.name) body.set(button.name, button.value);
    request(form.action, { method: 'POST', body: body }).then(function (json) {
      swap(json.html);
      toast('success', json.message);
      focusAfter(json.focus);
    }).catch(function (err) {
      form.removeAttribute('data-sending');
      if (err.data && err.data.conflict) swap(err.data.html);
      toast(err.data && err.data.conflict ? 'warning' : 'error', err.message);
    });
  }

  // ——— Filtre par genre ———
  function setGenre(genre, link) {
    var u = new URL(window.location.href);
    var previous = u.toString();
    if (genre) u.searchParams.set('genre', genre); else u.searchParams.delete('genre');
    u.hash = 'consignes';
    var f = new URL(pageUrl, window.location.href);
    f.searchParams.set('fragment', '1');
    if (genre) f.searchParams.set('genre', genre);
    live.setAttribute('aria-busy', 'true');
    request(f.toString()).then(function (json) {
      swap(json.html);
      window.history.replaceState(null, '', u.toString());
      var again = root.querySelector('[data-brief-filter="' + genre + '"]');
      if (again) again.focus({ preventScroll: true });
    }).catch(function (err) {
      window.history.replaceState(null, '', previous);
      toast('error', err.message);
      if (link) link.focus();
    }).then(function () {
      live.removeAttribute('aria-busy');
    });
  }

  // ——— Délégation : la zone vivante est remplacée, les écouteurs restent sur la racine ———
  root.addEventListener('click', function (e) {
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if (dlg.contains(e.target)) return;
    var summary = e.target.closest('[data-brief-fold] > summary');
    if (summary) {
      var fold = summary.parentNode;
      if (!fold.open && openFold(fold)) e.preventDefault();
      return;
    }
    var filter = e.target.closest('[data-brief-filter]');
    if (filter) {
      e.preventDefault();
      setGenre(filter.getAttribute('data-brief-filter'), filter);
    }
  });
  root.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches) return;
    if (form.matches('[data-brief-move]')) {
      e.preventDefault();
      submitMove(form, e.submitter);
    } else if (form.matches('[data-brief-form]') && dlg.contains(form)) {
      e.preventDefault();
      submitInDialog(form);
    }
  });

  if (currentGenre() === '' && window.location.hash === '#kit-add') {
    var add = root.querySelector('#kit-add [data-brief-fold]');
    if (add) add.scrollIntoView({ block: 'center' });
  }
})();
