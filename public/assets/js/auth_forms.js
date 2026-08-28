document.addEventListener('input', (event) => {
  const target = event.target;
  if (!(target instanceof HTMLInputElement)) {
    return;
  }
  if (target.dataset.lowercase === 'email') {
    target.value = target.value.toLowerCase();
  }
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
