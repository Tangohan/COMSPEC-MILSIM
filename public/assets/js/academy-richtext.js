'use strict';
// Academy — texte riche : mode nuit, agrandissement des images, annonce du résultat d'un exercice,
// éditeur du Studio (barre d'outils, aperçu en direct rendu par le serveur, images). Libellés fournis par la page.
(() => {
  const root = document.documentElement;
  const body = document.body;

  // Mode nuit : même préférence que le reste du site (clé athena.bo.theme ; absente = système).
  const themeKey = 'athena.bo.theme';
  const media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  const syncTheme = () => {
    const dark = root.getAttribute('data-bo-theme') === 'dark';
    document.querySelectorAll('[data-academy-theme]').forEach(button => {
      const label = dark ? button.dataset.toLight : button.dataset.toDark;
      button.setAttribute('aria-pressed', String(dark));
      button.title = label;
      const text = button.querySelector('[data-theme-label]');
      if (text) text.textContent = label;
    });
  };
  document.querySelectorAll('[data-academy-theme]').forEach(button => button.addEventListener('click', () => {
    const next = root.getAttribute('data-bo-theme') !== 'dark';
    try {
      if (media && next === media.matches) localStorage.removeItem(themeKey); else localStorage.setItem(themeKey, next ? 'dark' : 'light');
    } catch { /* préférence facultative */ }
    root.setAttribute('data-bo-theme', next ? 'dark' : 'light');
    syncTheme();
  }));
  media?.addEventListener?.('change', event => {
    let stored = null;
    try { stored = localStorage.getItem(themeKey); } catch { /* idem */ }
    if (stored !== 'dark' && stored !== 'light' && document.querySelector('[data-academy-theme]')) {
      root.setAttribute('data-bo-theme', event.matches ? 'dark' : 'light');
      syncTheme();
    }
  });
  syncTheme();

  // Images des cours : agrandissement dans une fenêtre (le lien direct reste disponible sans JavaScript).
  let lightbox = null;
  const openImage = (src, alt) => {
    if (!lightbox) {
      lightbox = document.createElement('dialog');
      lightbox.className = 'rt-lightbox';
      const close = document.createElement('button');
      close.type = 'button';
      close.className = 'rt-lightbox__x';
      close.setAttribute('aria-label', body.dataset.rtClose || '×');
      close.textContent = '×';
      close.addEventListener('click', () => lightbox.close());
      const img = document.createElement('img');
      const caption = document.createElement('p');
      lightbox.append(close, img, caption);
      lightbox.addEventListener('click', event => { if (event.target === lightbox) lightbox.close(); });
      body.append(lightbox);
    }
    const [, img, caption] = lightbox.children;
    img.src = src;
    img.alt = alt;
    caption.textContent = alt;
    caption.hidden = alt === '';
    if (typeof lightbox.showModal === 'function') lightbox.showModal(); else window.open(src, '_blank', 'noopener');
  };
  document.addEventListener('click', event => {
    const link = event.target.closest?.('a[data-rt-zoom]');
    if (!link || event.ctrlKey || event.metaKey || event.shiftKey) return;
    const img = link.querySelector('img');
    if (!img) return;
    event.preventDefault();
    openImage(link.href, img.alt || '');
  });

  // Résultat d'un exercice ou d'une évaluation : annoncé aux lecteurs d'écran, carte amenée à l'écran.
  const result = document.querySelector('[data-result-announce]');
  if (result) {
    const live = document.createElement('p');
    live.className = 'sr-only';
    live.setAttribute('role', 'status');
    live.setAttribute('aria-live', 'polite');
    body.append(live);
    window.setTimeout(() => { live.textContent = result.dataset.resultAnnounce; }, 150);
    if (window.location.hash === '#acr-result') {
      const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      result.focus({ preventScroll: true });
      result.scrollIntoView({ block: 'center', behavior: still ? 'auto' : 'smooth' });
    }
  }
  document.querySelectorAll('[data-retry]').forEach(button => button.addEventListener('click', () => {
    const form = button.closest('form');
    const first = form?.querySelector('[data-choice] input[type=radio]:not(:disabled)');
    form?.querySelector('[data-choice]')?.scrollIntoView({ block: 'center' });
    first?.focus();
  }));

  // Studio — éditeur de texte riche.
  const config = document.querySelector('[data-rte-config]');
  if (!config) return;
  let labels = {};
  try { labels = JSON.parse(config.dataset.labels || '{}'); } catch { labels = {}; }
  const L = key => labels[key] || '';
  const [callA, callB] = (config.dataset.callsigns || 'A|B').split('|');
  const hosts = (config.dataset.hosts || '').split(' ').filter(Boolean);
  const csrf = () => document.querySelector('[data-studio-form] [name="_csrf_token"]')?.value || '';
  const editors = [...document.querySelectorAll('[data-rte]')];
  const activeInput = editor => [...editor.querySelectorAll('[data-rte-input]')].find(input => input.offsetParent !== null)
    || editor.querySelector(`[data-rte-input][lang="${document.querySelector('[data-studio-form]')?.dataset.lang || 'fr'}"]`);

  const changed = input => input.dispatchEvent(new Event('input', { bubbles: true }));
  const replaceSelection = (input, text, selectFrom = null, selectTo = null) => {
    const start = input.selectionStart;
    input.focus();
    input.setRangeText(text, start, input.selectionEnd, 'end');
    if (selectFrom !== null) input.setSelectionRange(start + selectFrom, start + (selectTo ?? selectFrom));
    changed(input);
  };
  const selected = input => input.value.slice(input.selectionStart, input.selectionEnd);
  const wrap = (input, before, after, placeholder) => {
    const text = selected(input) || placeholder;
    replaceSelection(input, before + text + after, before.length, before.length + text.length);
  };
  // Préfixe ligne par ligne (titre, liste, citation) sur les lignes sélectionnées.
  const prefixLines = (input, prefix, placeholder) => {
    const value = input.value;
    const start = value.lastIndexOf('\n', input.selectionStart - 1) + 1;
    let end = value.indexOf('\n', input.selectionEnd);
    if (end === -1) end = value.length;
    const block = value.slice(start, end) || placeholder;
    const lines = block.split('\n').map((line, index) => (typeof prefix === 'function' ? prefix(index) : prefix) + line.replace(/^(#{1,4}\s+|>\s?|[-*•]\s+|\d{1,3}[.)]\s+)/, ''));
    input.focus();
    input.setSelectionRange(start, end);
    input.setRangeText(lines.join('\n'), start, end, 'select');
    changed(input);
  };
  // Bloc sur ses propres lignes, séparé du texte voisin par une ligne vide.
  const insertBlock = (input, text, focusFrom = null, focusTo = null) => {
    const value = input.value;
    const start = input.selectionStart;
    const before = value.slice(0, start);
    const lead = before === '' || before.endsWith('\n\n') ? '' : (before.endsWith('\n') ? '\n' : '\n\n');
    const after = value.slice(input.selectionEnd);
    const trail = after === '' || after.startsWith('\n\n') ? '' : (after.startsWith('\n') ? '\n' : '\n\n');
    replaceSelection(input, lead + text + trail, focusFrom === null ? null : lead.length + focusFrom, focusTo === null ? null : lead.length + focusTo);
  };
  const blockAround = (input, open, close, placeholder) => {
    const inner = selected(input) || placeholder;
    insertBlock(input, `${open}\n${inner}\n${close}`, open.length + 1, open.length + 1 + inner.length);
  };

  let imageTarget = null;
  const commands = {
    bold: input => wrap(input, '**', '**', L('ph_bold')),
    italic: input => wrap(input, '*', '*', L('ph_italic')),
    heading: input => prefixLines(input, '## ', L('ph_heading')),
    list: input => prefixLines(input, '- ', L('ph_item')),
    numbered: input => prefixLines(input, index => `${index + 1}. `, L('ph_item')),
    quote: input => prefixLines(input, '> ', L('ph_quote')),
    rule: input => insertBlock(input, '---'),
    radio: input => blockAround(input, ':::transmission', ':::', `${callA} : ${L('ph_message')}\n${callB} : ${L('ph_message')}`),
    steps: input => blockAround(input, `:::${input.lang === 'en' ? 'steps' : 'etapes'}`, ':::', `1. ${L('ph_step')}\n2. ${L('ph_step')}`),
    log: input => blockAround(input, '```', '```', L('ph_log')),
    table: input => insertBlock(input, `| ${L('ph_col')} | ${L('ph_col')} |\n|---|---|\n| ${L('ph_cell')} | ${L('ph_cell')} |`),
    link: input => {
      const url = window.prompt(L('link_prompt'), 'https://');
      if (!url || !/^https?:\/\/[^\s<>"'`]+$/i.test(url.trim())) return;
      wrap(input, '[', `](${url.trim()})`, L('ph_link'));
    },
    image: input => {
      imageTarget = input;
      const dialog = document.getElementById('rte-image');
      if (!dialog) return;
      dialog.querySelector('[data-rte-caption]').value = selected(input);
      if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
    },
  };

  // Aperçu : rendu par le serveur (même code que la salle de cours), demandé après une courte pause de frappe.
  const previews = new WeakMap();
  const refresh = input => {
    const editor = input.closest('[data-rte]');
    const pane = editor?.querySelector(`[data-rte-preview][lang="${input.lang}"]`);
    if (!pane || editor.querySelector('[data-rte-pane]')?.hidden) return;
    previews.get(input)?.abort();
    const controller = new AbortController();
    previews.set(input, controller);
    const data = new FormData();
    data.append('_csrf_token', csrf());
    data.append('course', config.dataset.course || '');
    data.append('text', input.value);
    fetch(config.dataset.previewUrl, { method: 'POST', body: data, credentials: 'same-origin', signal: controller.signal, headers: { Accept: 'application/json' } })
      .then(response => response.ok ? response.json() : Promise.reject(new Error(String(response.status))))
      .then(json => {
        if (typeof json.html !== 'string') throw new Error('format');
        pane.innerHTML = json.html !== '' ? json.html : '';
        if (json.html === '') {
          const empty = document.createElement('p');
          empty.className = 'rte-empty';
          empty.textContent = L('preview_empty');
          pane.append(empty);
        }
      })
      .catch(error => {
        if (error.name === 'AbortError') return;
        pane.replaceChildren(Object.assign(document.createElement('p'), { className: 'rte-empty', textContent: L('preview_error') }));
      });
  };
  const timers = new WeakMap();
  editors.forEach(editor => {
    const pane = editor.querySelector('[data-rte-pane]');
    const toggle = editor.querySelector('[data-rte-toggle]');
    if (toggle && pane) {
      toggle.setAttribute('aria-pressed', String(!pane.hidden));
      toggle.addEventListener('click', () => {
        pane.hidden = !pane.hidden;
        toggle.setAttribute('aria-pressed', String(!pane.hidden));
        editor.classList.toggle('is-previewing', !pane.hidden);
        if (!pane.hidden) { const input = activeInput(editor); if (input) refresh(input); }
      });
    }
    editor.querySelectorAll('[data-rte-cmd]').forEach(button => button.addEventListener('click', () => {
      const input = activeInput(editor);
      if (input && !input.disabled) commands[button.dataset.rteCmd]?.(input);
    }));
    editor.querySelector('[data-rte-callout]')?.addEventListener('change', event => {
      const input = activeInput(editor);
      const kind = event.target.value;
      event.target.value = '';
      if (input && kind) blockAround(input, `:::${kind}`, ':::', L('ph_callout'));
    });
    editor.querySelectorAll('[data-rte-input]').forEach(input => {
      input.addEventListener('input', () => {
        clearTimeout(timers.get(input));
        timers.set(input, setTimeout(() => refresh(input), 350));
      });
      input.addEventListener('keydown', event => {
        if (!(event.ctrlKey || event.metaKey) || event.altKey) return;
        const key = event.key.toLowerCase();
        if (key === 'b' || key === 'i') { event.preventDefault(); commands[key === 'b' ? 'bold' : 'italic'](input); }
      });
    });
  });

  // Fenêtre « Insérer une image » : médiathèque, envoi depuis l'ordinateur, ou adresse d'un hôte autorisé.
  const dialog = document.getElementById('rte-image');
  if (!dialog) return;
  const status = dialog.querySelector('[data-rte-status]');
  const src = dialog.querySelector('[data-rte-src]');
  const library = dialog.querySelector('[data-rte-library]');
  let picked = '';
  const pick = path => {
    picked = path;
    src.value = '';
    library.querySelectorAll('[data-rte-pick]').forEach(item => item.setAttribute('aria-pressed', String(item.dataset.rtePick === path)));
  };
  library.addEventListener('click', event => {
    const item = event.target.closest('[data-rte-pick]');
    if (item) pick(item.dataset.rtePick);
  });
  src.addEventListener('input', () => { if (src.value) pick(''); });
  const allowedUrl = value => {
    try {
      const url = new URL(value);
      return url.protocol === 'https:' && hosts.includes(url.hostname.toLowerCase()) && !url.username && !url.password;
    } catch { return false; }
  };
  dialog.querySelector('[data-rte-file]').addEventListener('change', event => {
    const file = event.target.files?.[0];
    if (!file) return;
    status.textContent = L('uploading');
    status.classList.remove('is-error');
    const data = new FormData();
    data.append('_csrf_token', csrf());
    data.append('course', config.dataset.course || '');
    data.append('image', file);
    fetch(config.dataset.imageUrl, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(response => response.json().then(json => (response.ok && json.path ? json : Promise.reject(new Error('upload')))))
      .then(json => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'rte-thumb';
        button.dataset.rtePick = json.path;
        const img = document.createElement('img');
        img.src = json.url;
        img.alt = '';
        button.append(img);
        library.prepend(button);
        dialog.querySelector('[data-rte-none]')?.setAttribute('hidden', '');
        pick(json.path);
        status.textContent = '';
        event.target.value = '';
      })
      .catch(() => { status.textContent = L('upload_failed'); status.classList.add('is-error'); });
  });
  dialog.querySelector('[data-rte-insert-image]').addEventListener('click', () => {
    const external = src.value.trim();
    const path = picked || external;
    if (!path) return;
    if (!picked && !allowedUrl(external)) { status.textContent = L('url_refused'); status.classList.add('is-error'); return; }
    const caption = dialog.querySelector('[data-rte-caption]').value.replace(/[\[\]\n]/g, ' ').trim() || L('ph_caption');
    dialog.close?.();
    if (imageTarget) insertBlock(imageTarget, `![${caption}](${path})`);
  });
})();
