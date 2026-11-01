'use strict';
const search = document.querySelector('[data-course-search]');
if (search) search.addEventListener('input', () => {
  const query = search.value.trim().toLocaleLowerCase();
  let visible = 0;
  document.querySelectorAll('[data-course-row]').forEach(card => {
    card.hidden = !card.textContent.toLocaleLowerCase().includes(query);
    if (!card.hidden) visible++;
  });
  const empty = document.querySelector('[data-search-empty]');
  if (empty) empty.hidden = visible > 0;
});
document.querySelectorAll('[data-countdown]').forEach(clock => {
  const initial = Math.max(0, Number(clock.dataset.countdown) - Number(clock.dataset.serverNow));
  const started = performance.now();
  const update = () => {
    const remaining = Math.max(0, initial - Math.floor((performance.now() - started) / 1000));
    clock.textContent = `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
    if (remaining === 0) clearInterval(interval);
  };
  const interval = setInterval(update, 1000);
  update();
});
document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => {
  const notes = document.querySelector('[name=notes]');
  const printed = document.querySelector('[data-print-notes]');
  if (notes && printed) printed.textContent = notes.value;
  window.print();
}));
// This stores acknowledgement only. Identity, community and entitlement stay server-side.
document.querySelectorAll('[data-academy-disclaimer]').forEach(dialog => {
  const key = `athena:academy:disclaimer:${dialog.dataset.disclaimerVersion}`;
  const open = () => {
    if (dialog.open) return;
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
  };
  let acknowledged = false;
  try { acknowledged = localStorage.getItem(key) === 'accepted'; } catch { /* Storage may be unavailable in private mode. */ }
  if (!acknowledged) open();
  dialog.querySelector('[data-disclaimer-form]').addEventListener('submit', event => {
    event.preventDefault();
    if (!event.currentTarget.reportValidity()) return;
    try { localStorage.setItem(key, 'accepted'); } catch { /* Keep the page usable when persistence is denied. */ }
    if (typeof dialog.close === 'function') dialog.close();
    else dialog.removeAttribute('open');
  });
  document.querySelectorAll('[data-open-disclaimer]').forEach(button => button.addEventListener('click', open));
});

// Salle de cours : mode focus (préférence locale), sommaire mobile, choix de réponse, onglets.
(() => {
  const body = document.body;
  const focusButton = document.querySelector('[data-focus-toggle]');
  if (focusButton) {
    const key = 'athena:academy:focus';
    const apply = on => { body.classList.toggle('acr-focus', on); focusButton.setAttribute('aria-pressed', String(on)); };
    try { apply(localStorage.getItem(key) === '1'); } catch { /* préférence facultative */ }
    const toggle = () => { const on = !body.classList.contains('acr-focus'); apply(on); try { localStorage.setItem(key, on ? '1' : '0'); } catch { /* idem */ } };
    focusButton.addEventListener('click', toggle);
    document.addEventListener('keydown', event => {
      if (event.key.toLowerCase() === 'f' && !event.ctrlKey && !event.metaKey && !event.altKey && !event.target.closest('input,textarea,select,[contenteditable]')) toggle();
    });
  }
  const outlineButton = document.querySelector('[data-outline-toggle]');
  if (outlineButton) outlineButton.addEventListener('click', () => {
    if (window.matchMedia('(max-width: 900px)').matches) {
      const open = body.classList.toggle('acr-outline-open');
      outlineButton.setAttribute('aria-expanded', String(open));
    } else if (focusButton) {
      focusButton.click();
      outlineButton.setAttribute('aria-expanded', String(!body.classList.contains('acr-focus')));
    }
  });
  document.querySelectorAll('[data-choice]').forEach(group => {
    const message = group.closest('form')?.querySelector('[data-choice-msg]');
    group.addEventListener('change', event => {
      if (!message || event.target.type !== 'radio') return;
      const letter = event.target.closest('.op')?.querySelector('.lt')?.textContent ?? '';
      message.textContent = `${message.dataset.chosen} ${letter}`;
    });
  });
  document.querySelectorAll('[data-tabs]').forEach(root => {
    const tabs = [...root.querySelectorAll('[data-tab]')];
    const show = name => {
      tabs.forEach(tab => tab.setAttribute('aria-selected', String(tab.dataset.tab === name)));
      root.querySelectorAll('[data-panel]').forEach(panel => { panel.hidden = panel.dataset.panel !== name; });
    };
    tabs.forEach(tab => tab.addEventListener('click', () => show(tab.dataset.tab)));
    show(tabs[0]?.dataset.tab);
  });
})();

// Studio : une étape visible à la fois, état d'enregistrement, Ctrl+S, confirmations.
document.querySelectorAll('[data-studio-form]').forEach(form => {
  const stepInput = form.querySelector('[data-step-input]');
  const state = document.querySelector('[data-save-state]');
  let dirty = false;
  let submitting = false;
  const showStep = (step, push) => {
    const panel = form.querySelector(`[data-step-panel="${step}"]`);
    if (!panel) return false;
    form.querySelectorAll('[data-step-panel]').forEach(item => { item.hidden = item !== panel; });
    document.querySelectorAll('[data-step-link]').forEach(link => {
      const on = link.dataset.stepLink === step && link.classList.contains('l');
      link.classList.toggle('on', on);
      if (on) link.setAttribute('aria-current', 'true'); else link.removeAttribute('aria-current');
    });
    const stage = step.split('.')[1];
    document.querySelectorAll('[data-step-tip]').forEach(tip => { tip.hidden = tip.dataset.stepTip !== stage; });
    const chapter = step.split('.')[0];
    const target = form.querySelector('[name=slides_target]');
    if (target && target.value !== 'new') target.value = chapter;
    if (stepInput) stepInput.value = step;
    if (push) {
      const url = new URL(window.location.href);
      url.searchParams.set('step', step);
      history.replaceState(null, '', url);
    }
    panel.closest('.col')?.scrollTo({ top: 0 });
    return true;
  };
  document.querySelectorAll('[data-step-link]').forEach(link => link.addEventListener('click', event => {
    if (showStep(link.dataset.stepLink, true)) {
      event.preventDefault();
      link.closest('details')?.removeAttribute('open');
    }
  }));
  form.addEventListener('input', () => {
    if (dirty || !state) return;
    dirty = true;
    state.textContent = state.dataset.dirty;
    state.classList.add('dirty');
  });
  form.addEventListener('click', event => {
    const button = event.target.closest('[data-confirm]');
    if (button && !window.confirm(button.dataset.confirm)) event.preventDefault();
  });
  form.addEventListener('submit', () => { submitting = true; });
  document.querySelectorAll('button[form="ast-form"]').forEach(button => button.addEventListener('click', () => { submitting = true; }));
  window.addEventListener('beforeunload', event => {
    if (dirty && !submitting) { event.preventDefault(); event.returnValue = ''; }
  });
  document.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
      event.preventDefault();
      if (form.querySelector('fieldset:disabled')) return;
      submitting = true;
      form.requestSubmit();
    }
  });
  document.querySelectorAll('[data-open-course-tab]').forEach(button => button.addEventListener('click', () => document.querySelector('[data-itab="course"]')?.click()));
  document.querySelectorAll('[data-itab]').forEach(tab => tab.addEventListener('click', () => {
    document.querySelectorAll('[data-itab]').forEach(item => item.setAttribute('aria-selected', String(item === tab)));
    document.querySelectorAll('[data-ipanel]').forEach(panel => { panel.hidden = panel.dataset.ipanel !== tab.dataset.itab; });
  }));
});
