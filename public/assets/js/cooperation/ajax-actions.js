/**
 * Coopération — actions courtes sans rechargement (amélioration progressive).
 *
 * Formulaires marqués data-coop-ajax : envoi en arrière-plan (le contrôleur répond en JSON quand
 * X-Requested-With est présent), bouton en état de chargement (désactivé, indicateur, aria-busy),
 * toast de résultat via athToast(), puis rafraîchissement des blocs [data-coop-region] de la page.
 * Erreur liée à un champ : message affiché sous le champ (motif « message d’erreur »).
 * Sans JavaScript, ou si la réponse n’est pas du JSON, le formulaire est soumis normalement.
 */
(function () {
  'use strict';

  function toast(variant, message) {
    if (typeof window.athToast === 'function') window.athToast(variant, message);
  }

  function setLoading(form, button, on) {
    form.setAttribute('aria-busy', on ? 'true' : 'false');
    if (!button) return;
    button.disabled = on;
    button.classList.toggle('is-loading', on);
    if (on) button.setAttribute('aria-busy', 'true'); else button.removeAttribute('aria-busy');
  }

  function clearFieldErrors(form) {
    form.querySelectorAll('.coop-field-error').forEach(function (n) { n.remove(); });
    form.querySelectorAll('.fr-input--error').forEach(function (n) {
      n.classList.remove('fr-input--error');
      n.removeAttribute('aria-invalid');
    });
  }

  function showFieldError(form, field, message) {
    if (!field) return false;
    var input = form.querySelector('[name="' + field + '"], [name="' + field + '[]"]');
    var target = input && input.hidden ? form.querySelector('.coop-cbx__input') : input;
    if (!target) target = form.querySelector('.coop-cbx__input');
    if (!target) return false;
    var msg = document.createElement('p');
    msg.className = 'fr-message fr-message--error coop-field-error';
    msg.id = 'coop-err-' + field.replace(/[^a-z0-9_-]/gi, '');
    msg.textContent = message;
    var anchor = target.closest('.coop-cbx') || target;
    anchor.insertAdjacentElement('afterend', msg);
    target.classList.add('fr-input--error');
    target.setAttribute('aria-invalid', 'true');
    target.setAttribute('aria-describedby', msg.id);
    target.focus();
    return true;
  }

  function refreshRegions() {
    var regions = Array.prototype.slice.call(document.querySelectorAll('[data-coop-region][id]'));
    if (!regions.length) return Promise.resolve();
    return fetch(window.location.href, { credentials: 'same-origin', headers: { Accept: 'text/html' } })
      .then(function (r) { return r.ok ? r.text() : Promise.reject(new Error('refresh')); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        regions.forEach(function (old) {
          if (!old.isConnected) return;
          var fresh = doc.getElementById(old.id);
          if (fresh) old.replaceWith(document.importNode(fresh, true));
        });
        if (typeof window.coopComboboxInit === 'function') window.coopComboboxInit();
        document.dispatchEvent(new CustomEvent('coop:regions-updated'));
      })
      .catch(function () { /* la page reste cohérente ; un rechargement manuel suffit */ });
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || form.tagName !== 'FORM' || !form.hasAttribute('data-coop-ajax') || e.defaultPrevented) return;
    if (!window.fetch || !window.FormData) return;
    e.preventDefault();

    var button = e.submitter || form.querySelector('[type="submit"]');
    var data;
    try {
      data = new FormData(form, e.submitter || undefined);
    } catch (err) {
      data = new FormData(form);
      if (e.submitter && e.submitter.name) data.append(e.submitter.name, e.submitter.value);
    }
    clearFieldErrors(form);
    setLoading(form, button, true);

    fetch(form.action, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) {
        var type = r.headers.get('Content-Type') || '';
        if (type.indexOf('application/json') === -1) throw new Error('not-json');
        return r.json();
      })
      .then(function (res) {
        setLoading(form, button, false);
        if (!res.ok) {
          if (!showFieldError(form, res.field, res.message)) toast('error', res.message);
          else toast('error', res.message);
          return;
        }
        toast(res.variant || 'success', res.message);
        if (res.warning) toast('warning', res.warning);
        var here = window.location.pathname;
        var target = res.redirect ? new URL(res.redirect, window.location.href) : null;
        if (target && target.pathname !== here) {
          window.location.assign(target.href);
          return;
        }
        var details = form.closest('details');
        if (details) details.open = false;
        refreshRegions();
      })
      .catch(function () {
        // Réponse inattendue (session expirée, redirection HTML…) : envoi classique.
        setLoading(form, button, false);
        form.removeAttribute('data-coop-ajax');
        form.setAttribute('data-ui-confirm-skip', '1');
        if (typeof form.requestSubmit === 'function') form.requestSubmit(e.submitter || undefined);
        else form.submit();
      });
  });
})();
