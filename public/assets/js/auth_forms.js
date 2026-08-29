document.addEventListener('input', (event) => {
  const target = event.target;
  if (!(target instanceof HTMLInputElement)) {
    return;
  }
  if (target.dataset.lowercase === 'email') {
    target.value = target.value.toLowerCase();
  }
  syncPasswordConfirm(target);
});

document.addEventListener('click', (event) => {
  const btn = event.target instanceof Element ? event.target.closest('[data-password-toggle]') : null;
  if (!btn) {
    return;
  }
  event.preventDefault();
  const id = btn.getAttribute('data-password-toggle') || btn.getAttribute('aria-controls') || 'password';
  const input = document.getElementById(id);
  if (!(input instanceof HTMLInputElement)) {
    return;
  }
  const hide = input.getAttribute('type') !== 'password';
  input.setAttribute('type', hide ? 'password' : 'text');
  const showLabel = btn.getAttribute('data-label-show');
  const hideLabel = btn.getAttribute('data-label-hide');
  const label = hide
    ? (showLabel || btn.textContent || '')
    : (hideLabel || btn.textContent || '');
  const span = btn.querySelector('[data-password-toggle-label]');
  if (span) {
    span.textContent = label.trim() !== '' ? label : (hide ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
  }
  btn.setAttribute('aria-label', (span && span.textContent) ? span.textContent : (hide ? 'Afficher le mot de passe' : 'Masquer le mot de passe'));
});

document.addEventListener('submit', (event) => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-register-form')) {
    return;
  }
  const confirm = form.querySelector('[data-password-confirm-of]');
  if (!(confirm instanceof HTMLInputElement)) {
    return;
  }
  if (!syncPasswordConfirm(confirm)) {
    event.preventDefault();
    confirm.reportValidity();
  }
});

/**
 * @param {HTMLInputElement} el
 * @returns {boolean}
 */
function syncPasswordConfirm(el) {
  let confirm = el;
  if (!el.hasAttribute('data-password-confirm-of')) {
    const form = el.form;
    if (!form || el.id === '') {
      return true;
    }
    const linked = form.querySelector(`[data-password-confirm-of="${CSS.escape(el.id)}"]`);
    if (!(linked instanceof HTMLInputElement)) {
      return true;
    }
    confirm = linked;
  }
  const sourceId = confirm.getAttribute('data-password-confirm-of') || 'password';
  const source = document.getElementById(sourceId);
  if (!(source instanceof HTMLInputElement)) {
    return true;
  }
  const mismatch = confirm.getAttribute('data-password-mismatch') || 'Les deux mots de passe doivent être identiques.';
  if (confirm.value !== '' && source.value !== confirm.value) {
    confirm.setCustomValidity(mismatch);
    return false;
  }
  confirm.setCustomValidity('');
  return true;
}
