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

// Studio — fenêtres (nouveau cours, publication, partage) : ouverture, fermeture, ouverture automatique.
(() => {
  const open = id => {
    const dialog = document.getElementById(id);
    if (!dialog) return false;
    if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
    return true;
  };
  document.querySelectorAll('[data-open-dialog]').forEach(button => button.addEventListener('click', event => {
    if (open(button.dataset.openDialog)) event.preventDefault();
  }));
  document.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => {
    const dialog = button.closest('dialog');
    if (dialog && typeof dialog.close === 'function') dialog.close(); else dialog?.removeAttribute('open');
  }));
  document.querySelectorAll('dialog[data-autoshow]').forEach(dialog => open(dialog.id));
})();

// Studio — accueil : filtres, recherche et tri des cartes (tout reste affiché sans JavaScript).
document.querySelectorAll('[data-studio-grid]').forEach(grid => {
  const cards = [...grid.querySelectorAll('[data-course-card]')];
  const search = document.querySelector('[data-studio-search]');
  const sort = document.querySelector('[data-studio-sort]');
  const empty = grid.querySelector('[data-studio-empty]');
  const newCard = grid.querySelector('[data-new-card]');
  let filter = 'all';
  const apply = () => {
    const query = (search?.value || '').trim().toLocaleLowerCase();
    let visible = 0;
    cards.forEach(card => {
      const status = card.dataset.status;
      const byFilter = filter === 'all' || (filter === 'pub' ? status === 'pub' : status === 'draft' || status === 'version');
      card.hidden = !(byFilter && (!query || card.dataset.title.includes(query)));
      if (!card.hidden) visible++;
    });
    if (newCard) newCard.hidden = query !== '' || filter !== 'all';
    if (empty) empty.hidden = visible > 0 || cards.length === 0;
  };
  document.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click', () => {
    filter = button.dataset.filter;
    document.querySelectorAll('[data-filter]').forEach(item => { item.classList.toggle('on', item === button); item.setAttribute('aria-pressed', String(item === button)); });
    apply();
  }));
  search?.addEventListener('input', apply);
  sort?.addEventListener('change', () => {
    const by = sort.value;
    const ordered = [...cards].sort((a, b) => by === 'title' ? a.dataset.title.localeCompare(b.dataset.title)
      : by === 'chapters' ? Number(b.dataset.chapters) - Number(a.dataset.chapters) : Number(b.dataset.updated) - Number(a.dataset.updated));
    ordered.forEach(card => grid.insertBefore(card, newCard || empty));
  });
});

// Studio — partage : recherche d'un membre, rôle appliqué aussitôt, lien public en lecture seule, copie du lien.
(() => {
  document.querySelectorAll('[data-member-filter]').forEach(input => {
    const select = input.closest('form')?.querySelector('[data-member-select]');
    if (!select) return;
    input.addEventListener('input', () => {
      const query = input.value.trim().toLocaleLowerCase();
      let first = null;
      [...select.options].forEach(option => {
        if (!option.value) return;
        option.hidden = query !== '' && !option.textContent.toLocaleLowerCase().includes(query);
        if (!option.hidden && !first) first = option;
      });
      if (first && query !== '') select.value = first.value;
    });
  });
  document.querySelectorAll('[data-autosubmit]').forEach(select => select.addEventListener('change', () => select.form?.requestSubmit()));
  document.querySelectorAll('[data-link-form]').forEach(form => {
    const access = form.querySelector('[data-link-access]');
    const role = form.querySelector('[data-link-role]');
    const help = form.querySelector('[data-link-help]');
    const sync = () => {
      const editor = role?.querySelector('option[value="editor"]');
      if (editor) editor.disabled = access.value === 'public';
      if (access.value === 'public' && role) role.value = 'viewer';
      if (role) role.disabled = access.value === 'off';
      if (help) help.textContent = access.selectedOptions[0]?.dataset.help || '';
    };
    access?.addEventListener('change', sync);
    if (access) sync();
  });
  document.querySelectorAll('[data-copy-link]').forEach(button => button.addEventListener('click', async () => {
    const input = button.parentElement.querySelector('[data-share-url]');
    try { await navigator.clipboard.writeText(input.value); } catch { input.select(); document.execCommand?.('copy'); }
    button.textContent = button.dataset.copied;
  }));
})();

// Studio — éditeur : une étape visible à la fois, une langue à la fois, lignes à ajouter, état d'enregistrement, Ctrl+S.
document.querySelectorAll('[data-studio-form]').forEach(form => {
  const stepInput = form.querySelector('[data-step-input]');
  const langInput = form.querySelector('[data-edit-lang-input]');
  const state = document.querySelector('[data-save-state]');
  let dirty = false;
  let submitting = false;
  const setUrl = (name, value) => {
    const url = new URL(window.location.href);
    if (value === null) url.searchParams.delete(name); else url.searchParams.set(name, value);
    history.replaceState(null, '', url);
  };
  const showStep = step => {
    const panel = form.querySelector(`[data-step-panel="${step}"]`);
    if (!panel) return false;
    form.querySelectorAll('[data-step-panel]').forEach(item => { item.hidden = item !== panel; });
    document.querySelectorAll('.ast-app [data-step-link]').forEach(link => {
      const on = link.dataset.stepLink === step && link.classList.contains('l');
      link.classList.toggle('on', on);
      if (on) link.setAttribute('aria-current', 'true'); else link.removeAttribute('aria-current');
    });
    const [chapter, stage] = step.split('.');
    document.querySelectorAll('[data-step-tip]').forEach(tip => { tip.hidden = tip.dataset.stepTip !== stage; });
    const target = form.querySelector('[name=slides_target]');
    if (target && target.value !== 'new') target.value = chapter;
    if (stepInput) stepInput.value = step;
    setUrl('step', step);
    panel.closest('.col')?.scrollTo({ top: 0 });
    return true;
  };
  document.querySelectorAll('[data-step-link]').forEach(link => link.addEventListener('click', event => {
    if (link.hasAttribute('data-open-course-tab')) return;
    if (showStep(link.dataset.stepLink)) {
      event.preventDefault();
      link.closest('details')?.removeAttribute('open');
    }
  }));
  document.querySelectorAll('[data-edit-lang]').forEach(link => link.addEventListener('click', event => {
    event.preventDefault();
    const language = link.dataset.editLang;
    form.dataset.lang = language;
    if (langInput) langInput.value = language;
    document.querySelectorAll('.langsw [data-edit-lang]').forEach(item => item.setAttribute('aria-pressed', String(item.dataset.editLang === language)));
    setUrl('edit', language === 'en' ? 'en' : null);
  }));
  // Lignes vides (critère, ressource, question) : masquées jusqu'à « Ajouter ».
  document.querySelectorAll('[data-reveal-slot]').forEach(button => {
    const slots = [...button.parentElement.querySelectorAll('[data-slot]')];
    if (!slots.length || form.querySelector('fieldset:disabled')?.contains(button)) return;
    slots.forEach(slot => { slot.hidden = true; });
    button.hidden = false;
    button.addEventListener('click', () => {
      const slot = slots.find(item => item.hidden);
      if (!slot) return;
      slot.hidden = false;
      slot.querySelector('input:not([type=hidden]),textarea,select')?.focus();
      button.hidden = !slots.some(item => item.hidden);
    });
  });
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
  document.querySelectorAll('button[form="ast-form"]').forEach(button => button.addEventListener('click', () => { submitting = !button.hasAttribute('data-open-dialog'); }));
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
  const showTab = name => {
    document.querySelectorAll('[data-itab]').forEach(item => item.setAttribute('aria-selected', String(item.dataset.itab === name)));
    document.querySelectorAll('[data-ipanel]').forEach(panel => { panel.hidden = panel.dataset.ipanel !== name; });
  };
  document.querySelectorAll('[data-itab]').forEach(tab => tab.addEventListener('click', () => showTab(tab.dataset.itab)));
  document.querySelectorAll('[data-open-course-tab]').forEach(button => button.addEventListener('click', event => {
    event.preventDefault();
    button.closest('details')?.removeAttribute('open');
    showTab('course');
    document.querySelector('[data-ipanel="course"] .in')?.focus();
  }));
});
// Confirmation des boutons de partage (hors formulaire principal).
document.querySelectorAll('.ast-share [data-confirm]').forEach(button => button.addEventListener('click', event => {
  if (!window.confirm(button.dataset.confirm)) event.preventDefault();
}));

// Catalogue : un filtre changé relance la recherche (le bouton reste là sans JavaScript).
document.querySelectorAll('form[data-autosubmit]').forEach(form => {
  form.addEventListener('change', event => {
    if (event.target instanceof HTMLSelectElement) {
      if (typeof form.requestSubmit === 'function') form.requestSubmit();
      else form.submit();
    }
  });
});

// Débriefing : la case « Rien à améliorer » permet d'envoyer sans texte (le serveur garde le maximum de 5 000 caractères).
document.querySelectorAll('[data-nothing-toggle]').forEach(box => {
  const area = document.getElementById(box.getAttribute('aria-controls') || '');
  if (!area) return;
  const sync = () => { area.required = !box.checked; area.minLength = box.checked ? 0 : 20; };
  box.addEventListener('change', sync);
  sync();
});
