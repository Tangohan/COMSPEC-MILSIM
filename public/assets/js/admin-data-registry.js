/* Administration plateforme — Registre des données : tableaux façon tableur, corrélations, purge. */
(() => {
  'use strict';
  const root = document.getElementById('adr');
  if (!root) return;

  const $ = (s, el = root) => el.querySelector(s);
  const $$ = (s, el = root) => [...el.querySelectorAll(s)];
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const num = v => Number(v || 0).toLocaleString('fr-FR');
  const api = root.dataset.api, actionUrl = root.dataset.action, corrUrl = root.dataset.corr, csrf = root.dataset.csrf;

  const state = {
    dataset: root.dataset.initial || 'users', q: '', filters: {}, flag: '', sort: '', dir: 'desc', page: 1, per: 100,
    meta: null, rows: [], total: 0, selected: new Set(), hidden: {}, busy: false,
  };
  try { state.hidden = JSON.parse(localStorage.getItem('adr.hidden') || '{}') || {}; } catch (e) { state.hidden = {}; }
  const saveHidden = () => { try { localStorage.setItem('adr.hidden', JSON.stringify(state.hidden)); } catch (e) { /* stockage indisponible */ } };

  // ---------------------------------------------------------------- rendu des cellules
  const fmtDate = v => {
    if (!v) return '<span class="adr-mute">—</span>';
    const d = new Date(String(v).replace(' ', 'T') + 'Z');
    if (Number.isNaN(+d)) return esc(v);
    return `<span title="${esc(v)} UTC">${esc(d.toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }))}</span>`;
  };
  const TAGS = { ok: 'ok', warn: 'warn', bad: 'bad', info: 'info', neut: 'neut' };
  function cell(col, row) {
    const v = row[col.key];
    switch (col.type) {
      case 'num': return v == null || v === '' ? '<span class="adr-mute">—</span>' : num(v);
      case 'date': return fmtDate(v);
      case 'mono': return v ? `<code>${esc(v)}</code>` : '<span class="adr-mute">—</span>';
      case 'tags': return (Array.isArray(v) ? v : []).map(t => `<span class="ath-tag ath-tag--${TAGS[t.tone] || 'neut'}" title="${esc(t.title || '')}">${esc(t.label)}</span>`).join(' ') || '<span class="adr-mute">—</span>';
      case 'entity': {
        if (!v || !v.label) return '<span class="adr-mute">—</span>';
        return `<button type="button" class="adr-link" data-corr-type="${esc(v.type)}" data-corr-id="${esc(v.id)}">${esc(v.label)}</button>${v.sub ? `<small>${esc(v.sub)}</small>` : ''}`;
      }
      case 'link': return v && v.href ? `<a href="${esc(v.href)}" class="adr-link">${esc(v.label || 'Ouvrir')}</a>` : '<span class="adr-mute">—</span>';
      default: return v == null || v === '' ? '<span class="adr-mute">—</span>' : esc(v);
    }
  }

  // ---------------------------------------------------------------- chargement
  function query(extra = {}) {
    const u = new URL(api, location.origin);
    u.searchParams.set('dataset', state.dataset);
    if (state.q) u.searchParams.set('q', state.q);
    if (state.flag) u.searchParams.set('flag', state.flag);
    if (state.sort) { u.searchParams.set('sort', state.sort); u.searchParams.set('dir', state.dir); }
    u.searchParams.set('page', state.page); u.searchParams.set('per', state.per);
    Object.entries(state.filters).forEach(([k, v]) => { if (v !== '') u.searchParams.set('f[' + k + ']', v); });
    Object.entries(extra).forEach(([k, v]) => u.searchParams.set(k, v));
    return u;
  }
  async function load() {
    if (state.busy) return;
    state.busy = true;
    $('#adr-status').textContent = 'Chargement…';
    try {
      const r = await fetch(query(), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const j = await r.json();
      if (!r.ok || !j.ok) throw new Error(j.message || j.error || ('HTTP ' + r.status));
      state.meta = j.meta; state.rows = j.rows || []; state.total = j.total || 0;
      render();
    } catch (e) {
      $('#adr-grid').innerHTML = `<p class="adr-empty">Chargement impossible : ${esc(e.message)}</p>`;
      $('#adr-status').textContent = '';
    } finally { state.busy = false; }
  }

  // ---------------------------------------------------------------- rendu
  function visibleCols() { return state.meta.columns.filter(c => !(state.hidden[state.dataset] || []).includes(c.key)); }

  function render() {
    const m = state.meta;
    // Indicateurs / filtres rapides
    $('#adr-flags').innerHTML = [{ key: '', label: 'Tout', count: m.total_all ?? state.total, tone: 'neut' }, ...(m.flags || [])].map(f =>
      `<button type="button" class="adr-flag${state.flag === f.key ? ' is-on' : ''} is-${esc(f.tone || 'neut')}" data-flag="${esc(f.key)}" title="${esc(f.title || '')}">${esc(f.label)} <b>${num(f.count)}</b></button>`).join('');
    // Actions groupées
    $('#adr-bulk').innerHTML = (m.actions || []).map(a =>
      `<button type="button" class="ath-btn${a.danger ? ' adr-danger' : ''}" data-action="${esc(a.key)}" ${state.selected.size ? '' : 'disabled'}>${esc(a.label)}</button>`).join('');
    $('#adr-selcount').textContent = state.selected.size ? `${num(state.selected.size)} sélectionnée(s)` : '';
    // Colonnes
    $('#adr-cols-menu').innerHTML = m.columns.map(c => `<label><input type="checkbox" data-col="${esc(c.key)}" ${(state.hidden[state.dataset] || []).includes(c.key) ? '' : 'checked'}> ${esc(c.label)}</label>`).join('');

    const cols = visibleCols();
    const allSel = state.rows.length > 0 && state.rows.every(r => state.selected.has(String(r._id)));
    const head = `<tr><th class="ath-th-check"><input type="checkbox" id="adr-selall" aria-label="Tout sélectionner" ${allSel ? 'checked' : ''}></th>${cols.map(c => {
      const s = state.sort === c.key ? (state.dir === 'asc' ? ' ▲' : ' ▼') : '';
      return `<th class="${c.type === 'num' ? 'ath-th-num' : ''}${c.sortable === false ? '' : ' adr-sortable'}" data-sort="${esc(c.key)}">${esc(c.label)}${s}</th>`;
    }).join('')}<th></th></tr>
      <tr class="adr-filters"><th></th>${cols.map(c => {
        if (c.filter === false) return '<th></th>';
        const val = state.filters[c.key] ?? '';
        if (Array.isArray(c.options)) return `<th><select data-filter="${esc(c.key)}" aria-label="Filtrer ${esc(c.label)}"><option value="">Tous</option>${c.options.map(o => `<option value="${esc(o.value)}" ${String(val) === String(o.value) ? 'selected' : ''}>${esc(o.label)}</option>`).join('')}</select></th>`;
        return `<th><input type="search" data-filter="${esc(c.key)}" value="${esc(val)}" placeholder="Filtrer" aria-label="Filtrer ${esc(c.label)}"></th>`;
      }).join('')}<th></th></tr>`;
    const body = state.rows.map(r => {
      const id = String(r._id), sel = state.selected.has(id);
      return `<tr class="${sel ? 'is-sel' : ''}${r._ghost ? ' is-ghost' : ''}" data-id="${esc(id)}">
        <td class="ath-td-check"><input type="checkbox" data-sel="${esc(id)}" ${sel ? 'checked' : ''} aria-label="Sélectionner"></td>
        ${cols.map(c => `<td class="${c.type === 'num' ? 'ath-td-num' : ''}">${cell(c, r)}</td>`).join('')}
        <td class="adr-rowact">${r._corr ? `<button type="button" class="ath-btn adr-mini" data-corr-type="${esc(r._corr.type)}" data-corr-id="${esc(r._corr.id)}">Corrélations</button>` : ''}</td></tr>`;
    }).join('');
    $('#adr-grid').innerHTML = state.rows.length
      ? `<div class="ath-table-wrap adr-scroll"><table class="ath-table adr-table"><thead>${head}</thead><tbody>${body}</tbody></table></div>`
      : `<div class="ath-table-wrap adr-scroll"><table class="ath-table adr-table"><thead>${head}</thead></table></div><p class="adr-empty">Aucune ligne pour ces filtres.</p>`;

    const pages = Math.max(1, Math.ceil(state.total / state.per));
    $('#adr-count').textContent = `${num(state.total)} ligne(s)`;
    $('#adr-pager').innerHTML = `<button type="button" class="ath-btn adr-mini" data-page="${state.page - 1}" ${state.page <= 1 ? 'disabled' : ''}>‹</button>
      <span>Page ${state.page} / ${pages}</span>
      <button type="button" class="ath-btn adr-mini" data-page="${state.page + 1}" ${state.page >= pages ? 'disabled' : ''}>›</button>
      <select id="adr-per" aria-label="Lignes par page">${[50, 100, 250, 500].map(n => `<option ${n === state.per ? 'selected' : ''}>${n}</option>`).join('')}</select>`;
    $('#adr-export').href = query({ format: 'csv', page: 1, per: 100000 }).toString();
    $('#adr-status').textContent = m.note || '';
    $('#adr-lead').textContent = m.lead || '';
  }

  // ---------------------------------------------------------------- corrélations
  const drawer = $('#adr-drawer');
  async function openCorr(type, id) {
    drawer.hidden = false;
    $('#adr-drawer-title').textContent = 'Corrélations';
    $('#adr-drawer-kicker').textContent = type;
    $('#adr-drawer-body').innerHTML = '<p class="adr-empty">Recherche de tout ce qui est rattaché…</p>';
    try {
      const u = new URL(corrUrl, location.origin); u.searchParams.set('type', type); u.searchParams.set('id', id);
      const r = await fetch(u, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const j = await r.json();
      if (!r.ok || !j.ok) throw new Error(j.message || 'HTTP ' + r.status);
      renderCorr(j);
    } catch (e) { $('#adr-drawer-body').innerHTML = `<p class="adr-empty">${esc(e.message)}</p>`; }
  }
  function renderCorr(j) {
    $('#adr-drawer-title').textContent = j.entity.label;
    $('#adr-drawer-kicker').textContent = j.entity.kind;
    const facts = (j.entity.facts || []).map(([k, v]) => `<dt>${esc(k)}</dt><dd>${esc(v ?? '—')}</dd>`).join('');
    const links = (j.links || []).map(l => `<li><button type="button" class="adr-link" data-corr-type="${esc(l.type)}" data-corr-id="${esc(l.id)}">${esc(l.label)}</button><small>${esc(l.kind)}${l.sub ? ' · ' + esc(l.sub) : ''}</small>${l.warn ? `<span class="ath-tag ath-tag--warn">${esc(l.warn)}</span>` : ''}</li>`).join('');
    const total = (j.tables || []).reduce((a, t) => a + Number(t.count || 0), 0);
    const tables = (j.tables || []).map(t => `<tr><td><b>${esc(t.label)}</b><small><code>${esc(t.table)}</code></small></td><td class="ath-td-num">${num(t.count)}</td><td>${esc(t.last ? new Date(String(t.last).replace(' ', 'T') + 'Z').toLocaleString('fr-FR') : '—')}</td><td>${t.purge ? '<span class="ath-tag ath-tag--bad">purgé</span>' : '<span class="ath-tag ath-tag--neut">conservé</span>'}</td></tr>`).join('');
    $('#adr-drawer-body').innerHTML = `
      ${j.warnings && j.warnings.length ? `<div class="ath-warn adr-warn">${j.warnings.map(w => `<p>${esc(w)}</p>`).join('')}</div>` : ''}
      <dl class="adr-facts">${facts}</dl>
      <h4>Entités liées (${(j.links || []).length})</h4>${links ? `<ul class="adr-links">${links}</ul>` : '<p class="adr-empty">Aucune.</p>'}
      <h4>Traces dans la base (${num(total)} ligne(s))</h4>
      <div class="ath-table-panel"><div class="ath-table-wrap"><table class="ath-table adr-table"><thead><tr><th>Table</th><th class="ath-th-num">Lignes</th><th>Dernière</th><th>Purge</th></tr></thead><tbody>${tables || '<tr><td colspan="4">Aucune trace.</td></tr>'}</tbody></table></div></div>
      ${j.purge ? `<form class="adr-purge" data-purge-type="${esc(j.entity.type)}" data-purge-id="${esc(j.entity.id)}">
        <h4>Purger</h4><p class="adr-mute">${esc(j.purge.lead)}</p>
        <label class="adr-confirm">Tapez <code>${esc(j.purge.confirm)}</code> pour confirmer<input type="text" name="confirm" autocomplete="off" required></label>
        <button type="submit" class="ath-btn adr-danger">${esc(j.purge.label)}</button></form>` : ''}
      ${(j.open || []).length ? `<p class="adr-open">${j.open.map(o => `<a class="ath-btn" href="${esc(o.href)}">${esc(o.label)}</a>`).join(' ')}</p>` : ''}`;
  }

  async function post(body) {
    const r = await fetch(actionUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(body) });
    const j = await r.json().catch(() => ({ ok: false, message: 'Réponse illisible.' }));
    return j;
  }
  function toast(msg, ok = true) {
    const t = $('#adr-toast'); t.textContent = msg; t.className = 'adr-toast ' + (ok ? 'is-ok' : 'is-ko'); t.hidden = false;
    clearTimeout(toast.t); toast.t = setTimeout(() => { t.hidden = true; }, 7000);
  }

  // ---------------------------------------------------------------- événements
  $$('[data-dataset]').forEach(b => b.addEventListener('click', () => {
    state.dataset = b.dataset.dataset; state.q = ''; state.filters = {}; state.flag = ''; state.sort = ''; state.page = 1; state.selected.clear();
    $('#adr-q').value = '';
    $$('[data-dataset]').forEach(x => { x.classList.toggle('is-active', x === b); x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
    try { history.replaceState(null, '', '#' + state.dataset); } catch (e) { /* ignore */ }
    load();
  }));
  let qt = null;
  $('#adr-q').addEventListener('input', e => { clearTimeout(qt); qt = setTimeout(() => { state.q = e.target.value.trim(); state.page = 1; load(); }, 300); });
  root.addEventListener('input', e => {
    const f = e.target.closest('[data-filter]'); if (!f) return;
    clearTimeout(qt); qt = setTimeout(() => { state.filters[f.dataset.filter] = f.value.trim(); state.page = 1; load(); }, 350);
  });
  root.addEventListener('change', e => {
    if (e.target.matches('select[data-filter]')) { state.filters[e.target.dataset.filter] = e.target.value; state.page = 1; load(); return; }
    if (e.target.id === 'adr-per') { state.per = Number(e.target.value); state.page = 1; load(); return; }
    if (e.target.id === 'adr-selall') { state.rows.forEach(r => e.target.checked ? state.selected.add(String(r._id)) : state.selected.delete(String(r._id))); render(); return; }
    if (e.target.matches('[data-sel]')) { const id = e.target.dataset.sel; e.target.checked ? state.selected.add(id) : state.selected.delete(id); render(); return; }
    if (e.target.matches('[data-col]')) {
      const list = new Set(state.hidden[state.dataset] || []);
      e.target.checked ? list.delete(e.target.dataset.col) : list.add(e.target.dataset.col);
      state.hidden[state.dataset] = [...list]; saveHidden(); render();
    }
  });
  root.addEventListener('click', async e => {
    const corr = e.target.closest('[data-corr-type]');
    if (corr) { openCorr(corr.dataset.corrType, corr.dataset.corrId); return; }
    const th = e.target.closest('th.adr-sortable');
    if (th) { const k = th.dataset.sort; state.dir = state.sort === k && state.dir === 'desc' ? 'asc' : 'desc'; state.sort = k; state.page = 1; load(); return; }
    const flag = e.target.closest('[data-flag]');
    if (flag) { state.flag = flag.dataset.flag; state.page = 1; state.selected.clear(); load(); return; }
    const pg = e.target.closest('[data-page]');
    if (pg && !pg.disabled) { state.page = Number(pg.dataset.page); load(); return; }
    if (e.target.closest('[data-close]') || e.target === drawer) { drawer.hidden = true; return; }
    if (e.target.closest('#adr-cols')) { $('#adr-cols-menu').hidden = !$('#adr-cols-menu').hidden; return; }
    const act = e.target.closest('[data-action]');
    if (act && state.selected.size) {
      const a = (state.meta.actions || []).find(x => x.key === act.dataset.action);
      if (!a) return;
      if (!confirm(`${a.label} : ${state.selected.size} ligne(s).\n\n${a.confirm || 'Confirmer ?'}`)) return;
      act.disabled = true;
      const j = await post({ dataset: state.dataset, action: a.key, ids: [...state.selected] });
      toast(j.message || (j.ok ? 'Fait.' : 'Échec.'), !!j.ok);
      if (j.ok) state.selected.clear();
      load();
    }
  });
  root.addEventListener('submit', async e => {
    const f = e.target.closest('.adr-purge'); if (!f) return;
    e.preventDefault();
    const btn = $('button[type="submit"]', f); btn.disabled = true;
    const j = await post({ purge: { type: f.dataset.purgeType, id: f.dataset.purgeId, confirm: f.confirm.value } });
    toast(j.message || (j.ok ? 'Purgé.' : 'Échec.'), !!j.ok);
    btn.disabled = false;
    if (j.ok) { drawer.hidden = true; load(); }
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { drawer.hidden = true; $('#adr-cols-menu').hidden = true; } });

  const hash = location.hash.replace('#', '');
  const start = hash && $(`[data-dataset="${CSS.escape(hash)}"]`) ? $(`[data-dataset="${CSS.escape(hash)}"]`) : $(`[data-dataset="${CSS.escape(state.dataset)}"]`);
  if (start) start.click(); else load();
})();
