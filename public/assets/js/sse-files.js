/* Fichiers SSE — comportements communs des écrans (sans dépendance) :
 *  - filtres instantanés : un formulaire [data-fx-instant] s'envoie seul (listes, cases : tout de suite ; texte : après
 *    une courte pause), le champ en cours de saisie retrouve le focus après rechargement ;
 *  - « / » place le curseur dans la recherche [data-fx-focus], Ctrl+Entrée envoie, Échap ferme le toast ;
 *  - préréglages [data-fx-set="nom=valeur&…"], copie [data-fx-copy="#id"], motif retenu [data-fx-remember] ;
 *  - comparaison de deux fiches cochées dans la photothèque [data-fx-compare].
 * Aucune animation au chargement d'une page. */
(function () {
  'use strict';
  var store = {
    get: function (k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { window.localStorage.setItem(k, v); } catch (e) { /* stockage indisponible */ } },
    sget: function (k) { try { return window.sessionStorage.getItem(k); } catch (e) { return null; } },
    sset: function (k, v) { try { window.sessionStorage.setItem(k, v); } catch (e) { /* stockage indisponible */ } },
    sdel: function (k) { try { window.sessionStorage.removeItem(k); } catch (e) { /* stockage indisponible */ } }
  };

  function submit(form, field) {
    if (field && field.name) {
      store.sset('fx-refocus', JSON.stringify({ path: location.pathname, name: field.name }));
    }
    if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); }
  }

  // Motif retenu d'une consultation à l'autre (préférence du poste, facultative).
  Array.prototype.forEach.call(document.querySelectorAll('[data-fx-remember]'), function (el) {
    var key = 'fx-' + el.getAttribute('data-fx-remember');
    if (!el.value) {
      var v = store.get(key);
      if (v && el.querySelector('option[value="' + v.replace(/"/g, '') + '"]')) { el.value = v; }
    }
    el.addEventListener('change', function () { store.set(key, el.value); });
  });

  Array.prototype.forEach.call(document.querySelectorAll('form[data-fx-instant]'), function (form) {
    var timer = null;
    form.addEventListener('input', function (e) {
      var t = e.target;
      if (!t || !t.name || t.type === 'checkbox' || t.type === 'radio' || t.tagName === 'SELECT') { return; }
      clearTimeout(timer);
      timer = setTimeout(function () { submit(form, t); }, 750);
    });
    form.addEventListener('change', function (e) {
      var t = e.target;
      if (!t || !t.name) { return; }
      if (t.type === 'checkbox' || t.type === 'radio' || t.tagName === 'SELECT' || t.type === 'date') {
        clearTimeout(timer);
        submit(form, t);
      }
    });
  });

  // Après un envoi instantané : focus rendu au champ en cours de saisie, curseur en fin de texte.
  try {
    var ref = JSON.parse(store.sget('fx-refocus') || 'null');
    store.sdel('fx-refocus');
    if (ref && ref.path === location.pathname) {
      var f = document.querySelector('form[data-fx-instant] [name="' + String(ref.name).replace(/"/g, '') + '"]');
      if (f && typeof f.focus === 'function') {
        f.focus();
        if (typeof f.setSelectionRange === 'function' && typeof f.value === 'string' && f.type !== 'number' && f.type !== 'date') {
          f.setSelectionRange(f.value.length, f.value.length);
        }
      }
    }
  } catch (e) { /* rien à restaurer */ }

  document.addEventListener('click', function (e) {
    var set = e.target.closest ? e.target.closest('[data-fx-set]') : null;
    if (set) {
      var form = set.closest('form');
      var on = set.classList.contains('on');
      set.getAttribute('data-fx-set').split('&').forEach(function (pair) {
        var kv = pair.split('=');
        var input = form ? form.querySelector('[name="' + kv[0] + '"]') : null;
        if (input) { input.value = on ? '' : decodeURIComponent(kv[1] || ''); }
      });
      if (form) { submit(form, null); }
      return;
    }
    var copy = e.target.closest ? e.target.closest('[data-fx-copy]') : null;
    if (copy) {
      var src = document.querySelector(copy.getAttribute('data-fx-copy'));
      if (src && navigator.clipboard) {
        navigator.clipboard.writeText(src.innerText).then(function () { copy.textContent = 'Copié'; });
      }
      return;
    }
    if (e.target.closest && e.target.closest('[data-fx-toast-close]')) {
      var toast = document.querySelector('[data-fx-toast]');
      if (toast) { toast.remove(); }
    }
  });

  document.addEventListener('keydown', function (e) {
    var tag = (e.target && e.target.tagName) || '';
    var typing = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || (e.target && e.target.isContentEditable);
    if (e.key === '/' && !typing && !e.ctrlKey && !e.metaKey) {
      var q = document.querySelector('[data-fx-focus]');
      if (q) { e.preventDefault(); q.focus(); }
    } else if (e.key === 'Escape') {
      var toast = document.querySelector('[data-fx-toast]');
      if (toast) { toast.remove(); }
    } else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && e.target && e.target.form) {
      e.preventDefault();
      submit(e.target.form, null);
    }
  });

  // Photothèque : deux fiches cochées → comparaison.
  Array.prototype.forEach.call(document.querySelectorAll('form[data-fx-compare]'), function (form) {
    var go = form.querySelector('[data-fx-compare-go]');
    function picked() { return Array.prototype.filter.call(form.querySelectorAll('input[name="pick[]"]'), function (c) { return c.checked; }); }
    form.addEventListener('change', function () {
      var p = picked();
      if (go) { go.disabled = p.length !== 2; }
    });
    form.addEventListener('submit', function (e) {
      var p = picked();
      if (p.length !== 2) { e.preventDefault(); return; }
      form.querySelector('input[name="a"]').value = p[0].value;
      form.querySelector('input[name="b"]').value = p[1].value;
      p.forEach(function (c) { c.disabled = true; });
    });
  });
})();
