(() => {
  'use strict';
  if (window.__athenaGradeWorkspace) return;
  window.__athenaGradeWorkspace = true;
  let busy = false;
  const workspace = () => document.querySelector('[data-grade-workspace]');
  const dialog = () => workspace()?.querySelector('[data-grade-dialog]');
  const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
  const notice = message => {
    const modal = dialog();
    let status;
    if (modal?.open) {
      const slot = modal.querySelector('[data-grade-form-slot]');
      status = slot.querySelector('[data-grade-status]');
      if (!status) {
        status = document.createElement('p');
        status.className = 'grd-ref__status';
        status.dataset.gradeStatus = '';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        slot.prepend(status);
      }
    } else status = workspace()?.querySelector('[data-grade-status]');
    if (status) status.textContent = message;
  };
  function filter() {
    const root = workspace();
    if (!root) return;
    const query = normalize(root.querySelector('[data-grade-filter]')?.value.trim() || '');
    let count = 0;
    const rows = [...root.querySelectorAll('[data-grade-row]')];
    rows.forEach(row => {
      row.hidden = Boolean(query) && !normalize([...row.querySelectorAll('.grd-ref__short,.grd-ref__name,.grd-ref__otan')].map(item => item.textContent).join(' ')).includes(query);
      if (!row.hidden) {
        count++;
        if (query) {
          let ancestor = row.parentElement;
          while (ancestor && ancestor !== root) {
            if (ancestor.tagName === 'DETAILS') ancestor.open = true;
            ancestor = ancestor.parentElement;
          }
        }
      }
    });
    const output = root.querySelector('[data-grade-filter-count]');
    if (output) output.textContent = query ? `${count} / ${rows.length}` : `${rows.length} grades dans cet onglet`;
  }
  const detailKey = detail => detail.querySelector('form')?.getAttribute('action') || detail.querySelector('summary')?.textContent.trim().replace(/\s*:\s*\d+.*$/, '') || '';
  function parse(html) { return new DOMParser().parseFromString(html, 'text/html'); }
  function openForm(formPage) {
    const modal = dialog();
    if (!modal || typeof modal.showModal !== 'function') return false;
    modal.querySelector('[data-grade-form-slot]').replaceChildren(document.importNode(formPage, true));
    if (!modal.open) modal.showModal();
    modal.querySelector('input:not([type=hidden]):not([readonly]):not([disabled]),select:not([disabled]),textarea')?.focus();
    return true;
  }
  async function load(url, options = {}) {
    if (busy) return;
    const target = new URL(url, location.href);
    if (target.origin !== location.origin) return;
    const old = workspace();
    if (!old) return;
    const query = old.querySelector('[data-grade-filter]')?.value || '';
    const openDetails = new Set([...old.querySelectorAll('details[open]')].map(detailKey));
    const scroll = window.scrollY;
    const isWrite = options.method === 'POST';
    busy = true;
    old.setAttribute('aria-busy', 'true');
    notice(isWrite ? 'Enregistrement…' : 'Chargement…');
    try {
      const response = await fetch(target, {
        method: options.method || 'GET', body: options.body,
        headers: {'X-Grade-Fragment': '1', 'X-Requested-With': 'XMLHttpRequest'},
        credentials: 'same-origin', redirect: 'follow'
      });
      const finalUrl = new URL(response.url, location.href);
      if (finalUrl.origin !== location.origin) throw new Error('Unexpected redirect');
      if (/\/(login|connexion)(?:\/|$)/.test(finalUrl.pathname)) {
        location.assign(finalUrl.href);
        return;
      }
      if (!response.ok) throw new Error('Request failed');
      const page = parse(await response.text());
      const formPage = page.querySelector('[data-grade-form-page]');
      if (formPage && openForm(formPage)) {
        const status = old.querySelector('[data-grade-status]');
        if (status) status.textContent = '';
        notice('');
        return;
      }
      const updated = page.querySelector('[data-grade-workspace]');
      if (!updated) throw new Error('Workspace missing');
      const modal = dialog();
      if (modal?.open) modal.close();
      old.replaceWith(document.importNode(updated, true));
      const root = workspace();
      const input = root.querySelector('[data-grade-filter]');
      if (input) input.value = query;
      root.querySelectorAll('details').forEach(detail => { if (openDetails.has(detailKey(detail))) detail.open = true; });
      filter();
      if (!options.popstate) {
        history[isWrite ? 'replaceState' : 'pushState']({}, '', finalUrl.pathname + finalUrl.search + finalUrl.hash);
      }
      if (options.focusAction) {
        const savedForm = [...root.querySelectorAll('form')].find(form => form.action === options.focusAction);
        const focus = savedForm?.querySelector('button[type=submit],button:not([type])');
        if (focus?.getClientRects().length) focus.focus({preventScroll: true});
        else input?.focus({preventScroll: true});
      }
      requestAnimationFrame(() => window.scrollTo({top: scroll, behavior: 'instant'}));
    } catch {
      notice(isWrite
        ? 'La réponse n’a pas pu être reçue. Actualisez la liste avant de réessayer la modification.'
        : 'Chargement impossible. Le lien reste accessible en ouvrant une nouvelle page.');
    } finally {
      busy = false;
      old.removeAttribute('aria-busy');
      workspace()?.removeAttribute('aria-busy');
    }
  }
  document.addEventListener('input', event => { if (event.target.matches('[data-grade-filter]')) filter(); });
  document.addEventListener('click', event => {
    const close = event.target.closest('[data-grade-close],[data-grade-cancel]');
    if (close && dialog()?.open) { event.preventDefault(); dialog().close(); return; }
    const link = event.target.closest('[data-grade-edit],[data-grade-tabs] a,.grd-cus__systems a');
    if (!link || !workspace()?.contains(link) || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const target = new URL(link.href, location.href);
    if (target.origin !== location.origin || (target.pathname === location.pathname && target.search === location.search && target.hash)) return;
    if (link.matches('[data-grade-edit]') && typeof dialog()?.showModal !== 'function') return;
    event.preventDefault();
    load(target.href);
  });
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!workspace()?.contains(form) || form.method.toUpperCase() !== 'POST' || event.defaultPrevented) return;
    if (new URL(form.action, location.href).origin !== location.origin) return;
    event.preventDefault();
    if (busy || !form.reportValidity()) return;
    const data = new FormData(form);
    if (event.submitter?.name) data.append(event.submitter.name, event.submitter.value);
    load(form.action, {method: 'POST', body: data, focusAction: form.action});
  });
  window.addEventListener('popstate', () => { if (workspace()) load(location.href, {popstate: true}); });
  filter();
})();
