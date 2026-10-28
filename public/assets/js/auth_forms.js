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

/* --- Verr. Maj : prévenir sous le champ mot de passe ---------------------- */
function syncCapsLock(event) {
  const input = event.target;
  if (!(input instanceof HTMLInputElement) || !input.hasAttribute('data-caps-watch')) {
    return;
  }
  const note = document.querySelector(`[data-caps-for="${CSS.escape(input.id)}"]`);
  if (!note || typeof event.getModifierState !== 'function') {
    return;
  }
  note.hidden = !event.getModifierState('CapsLock');
}
document.addEventListener('keydown', syncCapsLock);
document.addEventListener('keyup', syncCapsLock);
document.addEventListener('focusout', (event) => {
  const input = event.target;
  if (input instanceof HTMLInputElement && input.hasAttribute('data-caps-watch')) {
    const note = document.querySelector(`[data-caps-for="${CSS.escape(input.id)}"]`);
    if (note) note.hidden = true;
  }
});

/* --- Bouton « occupé » : un seul envoi, libellé d’attente ----------------- */
document.addEventListener('submit', (event) => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-busy-form') || event.defaultPrevented) {
    return;
  }
  if (form.dataset.submitting === '1') {
    event.preventDefault();
    return;
  }
  form.dataset.submitting = '1';
  const btn = form.querySelector('button[type="submit"]');
  if (btn instanceof HTMLButtonElement) {
    // Laisser partir la valeur du bouton avant de le désactiver.
    window.setTimeout(() => {
      btn.disabled = true;
      btn.setAttribute('aria-busy', 'true');
      const busy = btn.getAttribute('data-busy-label');
      if (busy) btn.textContent = busy;
    }, 0);
  }
});
window.addEventListener('pageshow', (event) => {
  if (!event.persisted) return;
  document.querySelectorAll('form[data-busy-form]').forEach((form) => {
    delete form.dataset.submitting;
    form.querySelectorAll('button[aria-busy="true"]').forEach((btn) => {
      btn.disabled = false;
      btn.removeAttribute('aria-busy');
    });
  });
});

/* --- Nouveau mot de passe : jauge de force et concordance ------------------ */
(function () {
  const form = document.querySelector('form[data-strength-form]');
  if (!(form instanceof HTMLFormElement)) return;
  const source = form.querySelector('[data-strength-source]');
  const meter = form.querySelector('[data-strength-meter]');
  const text = form.querySelector('[data-strength-text]');
  const confirm = form.querySelector('[data-password-confirm-of]');
  const match = form.querySelector('[data-match-text]');
  if (!(source instanceof HTMLInputElement)) return;

  /** Même barème que l’inscription : 0 rien, 1 faible, 2 correct, 3 bon, 4 solide. */
  const strength = (v) => {
    if (v.length < 8) return v.length === 0 ? 0 : 1;
    let score = 0;
    if (v.length >= 12) score++;
    if (v.length >= 16) score++;
    if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
    if (/\d/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    if (/^(.)\1+$/.test(v) || /^(?:0123|1234|azer|qwer|pass|mot2)/i.test(v)) score = 0;
    return Math.max(1, Math.min(4, score));
  };
  const update = () => {
    const v = source.value;
    const level = strength(v);
    if (meter) meter.setAttribute('data-level', String(level));
    if (text) {
      let label = text.getAttribute('data-default') || '';
      if (v.length > 0 && v.length < 8) {
        label = (text.getAttribute('data-too-short') || '').replace('{n}', String(8 - v.length));
      } else if (v.length >= 8) {
        label = text.getAttribute(['', 'data-weak', 'data-fair', 'data-good', 'data-strong'][level]) || label;
      }
      text.textContent = label;
      text.setAttribute('data-level', String(level));
    }
    if (match && confirm instanceof HTMLInputElement) {
      let state = '';
      let label = match.getAttribute('data-default') || '';
      if (confirm.value !== '') {
        const same = confirm.value === v;
        state = same ? 'ok' : 'ko';
        label = match.getAttribute(same ? 'data-match' : 'data-mismatch') || label;
      }
      match.textContent = label;
      match.setAttribute('data-state', state);
    }
  };
  form.addEventListener('input', update);
  update();
})();

/* --- Code de vérification : appareil de confiance, compte à rebours -------- */
(function () {
  const remember = document.querySelector('[data-remember-device]');
  const mirror = document.querySelector('[data-remember-mirror]');
  if (remember instanceof HTMLInputElement) {
    const key = 'athena.auth.remember_device';
    try { remember.checked = localStorage.getItem(key) === '1'; } catch (e) { /* stockage indisponible */ }
    const sync = () => {
      if (mirror instanceof HTMLInputElement) mirror.value = remember.checked ? '1' : '';
      try { localStorage.setItem(key, remember.checked ? '1' : '0'); } catch (e) { /* stockage indisponible */ }
    };
    remember.addEventListener('change', sync);
    sync();
  }

  const fmt = (sec) => {
    const s = Math.max(0, Math.floor(sec));
    return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
  };
  const expiry = document.querySelector('[data-expires-at]');
  const resend = document.querySelector('[data-resend-at]');
  const tick = () => {
    const now = Date.now() / 1000;
    if (expiry instanceof HTMLElement) {
      const left = Number(expiry.getAttribute('data-expires-at')) - now;
      if (left > 0) {
        expiry.textContent = (expiry.getAttribute('data-label') || '%s').replace('%s', fmt(left));
        expiry.removeAttribute('data-state');
      } else {
        expiry.textContent = expiry.getAttribute('data-expired') || '';
        expiry.setAttribute('data-state', 'expired');
      }
    }
    if (resend instanceof HTMLButtonElement && resend.getAttribute('aria-busy') !== 'true') {
      const wait = Number(resend.getAttribute('data-resend-at')) - now;
      if (wait > 0) {
        resend.disabled = true;
        resend.textContent = (resend.getAttribute('data-resend-wait') || '%s').replace('%s', fmt(wait));
      } else {
        resend.disabled = false;
        resend.textContent = resend.getAttribute('data-resend-label') || resend.textContent;
      }
    }
  };
  if (expiry || resend) {
    tick();
    window.setInterval(tick, 1000);
  }
})();
