/* Administration du site — Paiements et dons : dépliage du registre, copie, confirmations, recherche de membre. */
(function () {
  'use strict';
  const root = document.getElementById('ap');
  if (!root) return;

  // Dépliage des lignes du registre
  const toggle = (item, open) => {
    item.classList.toggle('is-open', open);
    const b = item.querySelector('.ap-item__row button[aria-expanded]');
    if (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); b.textContent = open ? 'Fermer' : 'Ouvrir'; }
  };
  root.addEventListener('click', (e) => {
    const row = e.target.closest('[data-ap-toggle]');
    if (row && !e.target.closest('a, input, select, textarea, form')) {
      const item = row.closest('.ap-item');
      toggle(item, !item.classList.contains('is-open'));
      return;
    }
    const copy = e.target.closest('[data-ap-copy]');
    if (copy) {
      const text = copy.getAttribute('data-ap-copy') || '';
      const done = () => { const t = copy.textContent; copy.classList.add('is-done'); copy.textContent = 'Copié'; setTimeout(() => { copy.classList.remove('is-done'); copy.textContent = t; }, 1400); };
      if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, () => {}); else done();
    }
  });
  const focus = root.dataset.focus;
  if (focus) {
    const item = document.getElementById(focus);
    if (item) { toggle(item, true); setTimeout(() => item.scrollIntoView({ block: 'center' }), 60); }
  }

  // Confirmations
  root.querySelectorAll('form[data-ap-confirm]').forEach((f) => {
    f.addEventListener('submit', (e) => { if (!window.confirm(f.getAttribute('data-ap-confirm'))) e.preventDefault(); });
  });
  // Évite le double envoi
  root.querySelectorAll('form[method="post"]').forEach((f) => {
    f.addEventListener('submit', (e) => {
      if (e.defaultPrevented) return;
      f.querySelectorAll('button:not([type=button])').forEach((b) => { b.disabled = true; });
    });
  });

  // Fermer les menus « Plus » en cliquant ailleurs
  document.addEventListener('click', (e) => {
    root.querySelectorAll('details.ap-menu[open]').forEach((d) => { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });

  // Recherche de membre (formulaire de don)
  const input = root.querySelector('[data-ap-member]');
  const list = root.querySelector('[data-ap-member-list]');
  if (input && list) {
    const nameField = input.form ? input.form.elements.namedItem('donor_name') : null;
    let timer = 0, seq = 0, active = -1, items = [];
    const close = () => { list.hidden = true; active = -1; };
    const pick = (u) => {
      input.value = u.email || '';
      if (nameField && !nameField.value) nameField.value = u.name || '';
      close();
    };
    const render = () => {
      list.innerHTML = '';
      items.forEach((u, i) => {
        const li = document.createElement('li');
        const b = document.createElement('button');
        b.type = 'button';
        if (i === active) b.className = 'is-active';
        b.textContent = u.name || u.email;
        const s = document.createElement('small');
        s.textContent = [u.email, u.tenant].filter(Boolean).join(' · ');
        b.appendChild(s);
        b.addEventListener('mousedown', (ev) => { ev.preventDefault(); pick(u); });
        li.appendChild(b);
        list.appendChild(li);
      });
      list.hidden = items.length === 0;
    };
    input.addEventListener('input', () => {
      clearTimeout(timer);
      const q = input.value.trim();
      if (q.length < 2) { items = []; close(); return; }
      timer = setTimeout(() => {
        const my = ++seq;
        fetch(root.dataset.members + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
          .then((r) => (r.ok ? r.json() : { members: [] }))
          .then((d) => { if (my !== seq) return; items = Array.isArray(d.members) ? d.members : []; active = -1; render(); })
          .catch(() => {});
      }, 220);
    });
    input.addEventListener('keydown', (e) => {
      if (list.hidden) return;
      if (e.key === 'ArrowDown') { active = Math.min(items.length - 1, active + 1); render(); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { active = Math.max(0, active - 1); render(); e.preventDefault(); }
      else if (e.key === 'Enter' && active >= 0) { pick(items[active]); e.preventDefault(); }
      else if (e.key === 'Escape') close();
    });
    input.addEventListener('blur', () => setTimeout(close, 120));
  }
})();
