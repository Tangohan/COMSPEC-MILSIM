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
  const fallback = hide ? 'Afficher le mot de passe' : 'Masquer le mot de passe';
  const finalLabel = (span && span.textContent) ? span.textContent : (label.trim() !== '' ? label.trim() : fallback);
  btn.setAttribute('aria-label', finalLabel);
  if (btn.hasAttribute('aria-pressed')) {
    btn.setAttribute('aria-pressed', hide ? 'false' : 'true');
  }
  if (btn.hasAttribute('title')) {
    btn.setAttribute('title', finalLabel);
  }
});

document.addEventListener('submit', (event) => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-register-form')) {
    return;
  }
  // Formulaire avec erreurs affichées sous les champs : géré par auth_register.js.
  if (form.hasAttribute('data-inline-validation')) {
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

const previewModal = document.querySelector('[data-preview-modal]');
if (previewModal instanceof HTMLDialogElement) {
  const openPreview = () => {
    if (!previewModal.open) {
      previewModal.showModal();
    }
  };
  document.querySelectorAll('[data-preview-open]').forEach((button) => {
    button.addEventListener('click', openPreview);
  });
  previewModal.querySelectorAll('[data-preview-close]').forEach((button) => {
    button.addEventListener('click', () => previewModal.close());
  });
  previewModal.addEventListener('click', (event) => {
    if (event.target === previewModal) {
      previewModal.close();
    }
  });
  // Ouverture automatique à la première visite seulement ; ensuite, le lien « En savoir plus » la rouvre.
  const seenKey = 'athena.register.preview_seen';
  previewModal.addEventListener('close', () => {
    try { localStorage.setItem(seenKey, '1'); } catch (e) { /* stockage indisponible */ }
  });
  let seen = false;
  try { seen = localStorage.getItem(seenKey) === '1'; } catch (e) { seen = false; }
  if (!seen) {
    openPreview();
  }
}
