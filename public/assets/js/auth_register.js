/**
 * Page d’inscription : force du mot de passe, concordance des deux saisies,
 * erreurs affichées sous chaque champ (au départ du champ, puis à l’envoi).
 * Les textes viennent des attributs data-* posés par la vue (déjà traduits).
 */
(function () {
  'use strict';

  const form = document.querySelector('form[data-inline-validation]');
  if (!(form instanceof HTMLFormElement)) {
    return;
  }

  const msg = (name) => form.getAttribute('data-msg-' + name) || '';
  const byId = (id) => document.getElementById(id);
  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  const touched = new Set();

  const password = byId('password');
  const confirm = byId('password_confirmation');
  const meter = form.querySelector('[data-strength-meter]');
  const strengthText = form.querySelector('[data-strength-text]');
  const matchText = form.querySelector('[data-match-text]');
  const summary = form.querySelector('[data-error-summary]');

  /** Règles de saisie : renvoie le message d’erreur, ou '' si le champ est bon. */
  const rules = {
    email: (el) => {
      const v = el.value.trim();
      if (v === '') return msg('email-required');
      return EMAIL_RE.test(v) ? '' : msg('email-invalid');
    },
    first_name: (el) => (el.value.trim() === '' ? msg('first-name') : ''),
    last_name: (el) => (el.value.trim() === '' ? msg('last-name') : ''),
    password: (el) => (el.value.length < 8 ? msg('password') : ''),
    password_confirmation: (el) => {
      if (el.value === '') return msg('confirm');
      if (password instanceof HTMLInputElement && el.value !== password.value) {
        return el.getAttribute('data-password-mismatch') || '';
      }
      return '';
    },
    accept_terms: (el) => (el.checked ? '' : msg('terms')),
  };

  function setError(name, text) {
    const el = byId(name);
    const slot = form.querySelector('[data-error-for="' + name + '"]');
    if (slot) slot.textContent = text;
    if (el) {
      if (text) el.setAttribute('aria-invalid', 'true');
      else el.removeAttribute('aria-invalid');
    }
    if (name === 'accept_terms') {
      const box = form.querySelector('.rg-check');
      if (box) box.classList.toggle('is-invalid', text !== '');
    }
  }

  function check(name) {
    const el = byId(name);
    if (!el || !rules[name]) return true;
    const text = rules[name](el);
    setError(name, text);
    return text === '';
  }

  /** Force : 0 rien, 1 faible, 2 correct, 3 bon, 4 solide. */
  function strength(v) {
    if (v.length < 8) return v.length === 0 ? 0 : 1;
    let score = 0;
    if (v.length >= 12) score++;
    if (v.length >= 16) score++;
    if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
    if (/\d/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    if (/^(.)\1+$/.test(v) || /^(?:0123|1234|azer|qwer|pass|mot2)/i.test(v)) score = 0;
    if (score <= 1) return 1;
    if (score === 2) return 2;
    if (score === 3) return 3;
    return 4;
  }

  function updateStrength() {
    if (!(password instanceof HTMLInputElement) || !meter || !strengthText) return;
    const v = password.value;
    const level = strength(v);
    meter.setAttribute('data-level', String(level));
    let text = strengthText.getAttribute('data-default') || '';
    if (v.length > 0 && v.length < 8) {
      text = (strengthText.getAttribute('data-too-short') || '').replace('{n}', String(8 - v.length));
    } else if (v.length >= 8) {
      text = strengthText.getAttribute(['', 'data-weak', 'data-fair', 'data-good', 'data-strong'][level]) || text;
    }
    strengthText.textContent = text;
    strengthText.setAttribute('data-level', String(level));
  }

  function updateMatch() {
    if (!(confirm instanceof HTMLInputElement) || !(password instanceof HTMLInputElement) || !matchText) return;
    let state = '';
    let text = matchText.getAttribute('data-default') || '';
    if (confirm.value !== '') {
      const same = confirm.value === password.value;
      state = same ? 'ok' : 'ko';
      text = matchText.getAttribute(same ? 'data-match' : 'data-mismatch') || text;
    }
    matchText.textContent = text;
    matchText.setAttribute('data-state', state);
    if (state === 'ok' && touched.has('password_confirmation')) setError('password_confirmation', '');
  }

  form.addEventListener('input', (event) => {
    const el = event.target;
    if (!(el instanceof HTMLInputElement)) return;
    if (el === password) {
      updateStrength();
      updateMatch();
    }
    if (el === confirm) updateMatch();
    // Une fois l’erreur affichée, elle disparaît dès que la saisie devient bonne.
    if (touched.has(el.name) || el.getAttribute('aria-invalid') === 'true') {
      if (rules[el.name]) check(el.name);
      else setError(el.name, '');
    }
  });

  form.addEventListener('change', (event) => {
    const el = event.target;
    if (el instanceof HTMLInputElement && el.type === 'checkbox' && rules[el.name]) {
      touched.add(el.name);
      check(el.name);
    }
  });

  form.addEventListener('focusout', (event) => {
    const el = event.target;
    if (!(el instanceof HTMLInputElement) || !rules[el.name] || el.type === 'checkbox') return;
    // Pas d’erreur sur un champ simplement traversé sans rien saisir.
    if (el.value === '' && !touched.has(el.name)) return;
    touched.add(el.name);
    check(el.name);
  });

  form.addEventListener('submit', (event) => {
    let first = null;
    Object.keys(rules).forEach((name) => {
      touched.add(name);
      if (!check(name) && !first) first = byId(name);
    });
    if (!first) {
      if (summary) summary.hidden = true;
      return;
    }
    event.preventDefault();
    if (summary) {
      summary.textContent = msg('summary');
      summary.hidden = false;
    }
    const details = first.closest('details');
    if (details && !details.open) details.open = true;
    first.focus();
    if (typeof first.scrollIntoView === 'function') {
      first.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
  });

  // Champ en erreur dans la partie facultative repliée : on l’ouvre.
  const invalid = form.querySelector('[aria-invalid="true"]');
  if (invalid) {
    const details = invalid.closest('details');
    if (details) details.open = true;
  }
  updateStrength();
  updateMatch();
})();
