/* Back-office — Configuration ATAK / Tacmap : onglets, copies, aperçus en direct, sélecteur de symboles, santé de liaison. */
(() => {
  'use strict';
  const root = document.getElementById('bo-ac');
  if (!root) return;
  const $ = (s, el = root) => el.querySelector(s);
  const $$ = (s, el = root) => [...el.querySelectorAll(s)];
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // ------------------------------------------------------------ onglets (ancre = section)
  const panels = $$('[data-ac-panel]');
  const links = $$('[data-ac-tab]');
  function show(id, push) {
    if (!panels.some(p => p.id === id)) id = panels[0].id;
    panels.forEach(p => { p.hidden = p.id !== id; });
    links.forEach(a => a.classList.toggle('is-active', a.dataset.acTab === id));
    if (push && location.hash !== '#' + id) history.replaceState(null, '', '#' + id);
  }
  root.classList.add('is-tabbed');
  show(location.hash.slice(1), false);
  window.addEventListener('hashchange', () => show(location.hash.slice(1), false));
  root.addEventListener('click', e => {
    const a = e.target.closest('[data-ac-tab], [data-ac-go]');
    if (!a) return;
    e.preventDefault();
    show(a.dataset.acTab || a.dataset.acGo, true);
    window.scrollTo({ top: root.getBoundingClientRect().top + window.scrollY - 70, behavior: 'smooth' });
  });

  // ------------------------------------------------------------ copier, confirmer
  root.addEventListener('click', e => {
    const b = e.target.closest('[data-ac-copy]');
    if (!b) return;
    const src = $(b.dataset.acCopy);
    navigator.clipboard?.writeText((src.value || src.textContent || '').trim()).then(() => {
      const t = b.textContent; b.textContent = 'Copié'; setTimeout(() => { b.textContent = t; }, 1500);
    });
  });
  root.addEventListener('submit', e => {
    const f = e.target.closest('form[data-ac-confirm]');
    if (f && !window.confirm(f.dataset.acConfirm)) e.preventDefault();
  });

  // ------------------------------------------------------------ aperçu d'un fichier image choisi
  root.addEventListener('change', e => {
    const input = e.target.closest('input[type=file][data-ac-file-preview]');
    if (!input || !input.files || !input.files[0]) return;
    const img = $(input.dataset.acFilePreview);
    if (!img) return;
    img.src = URL.createObjectURL(input.files[0]); img.hidden = false;
    const none = $('[data-ac-game-noimg]'); if (none && img.matches('[data-ac-game-img]')) none.hidden = true;
  });

  // ------------------------------------------------------------ connexion en jeu : aperçu + au moins une méthode
  const game = $('#bo-ac-game');
  if (game) {
    const out = k => $(`[data-ac-game-out="${k}"]`, game);
    const auths = $$('[data-ac-auth] input[type=checkbox]', game);
    const sync = () => {
      const name = $('[data-ac-game="name"]', game);
      out('name').textContent = name.value.trim() || name.placeholder;
      out('message').textContent = $('[data-ac-game="message"]', game).value.trim();
      const on = auths.filter(c => c.checked).map(c => c.closest('[data-ac-auth]').dataset.acAuth);
      out('methods').textContent = on.length ? on.join(' • ') : 'Aucune méthode : connexion impossible';
      out('methods').classList.toggle('is-bad', !on.length);
      $('[data-ac-auth-warn]', game).hidden = on.length > 0;
    };
    game.addEventListener('input', sync); game.addEventListener('change', sync); sync();
    game.addEventListener('submit', e => { if (!auths.some(c => c.checked)) { e.preventDefault(); $('[data-ac-auth-warn]', game).hidden = false; } });
  }

  // ------------------------------------------------------------ expérience : ambiance exclusive
  const exp = $('#bo-ac-exp');
  if (exp) {
    exp.addEventListener('change', e => {
      if (!e.target.matches('[data-ac-mode]')) return;
      $('[data-ac-mode-realism]', exp).value = e.target.value === 'realism' ? '1' : '0';
      $('[data-ac-mode-troll]', exp).value = e.target.value === 'troll' ? '1' : '0';
    });
  }

  // ------------------------------------------------------------ modules : tout activer / couper
  const countMods = () => { const c = $('[data-ac-count]'); if (c) c.textContent = $$('[data-ac-module] input[type=checkbox]:checked').length; };
  root.addEventListener('click', e => {
    const b = e.target.closest('[data-ac-all]');
    if (!b) return;
    $$('[data-ac-module] input[type=checkbox]').forEach(c => { c.checked = b.dataset.acAll === '1'; });
    countMods();
  });
  root.addEventListener('change', e => { if (e.target.closest('[data-ac-module]')) countMods(); });

  // ------------------------------------------------------------ bandeau photo : simulation instantanée
  const hud = $('#bo-ac-hud');
  if (hud) {
    const v = k => { const el = $(`[data-ac-hud="${k}"]`, hud); if (!el) return null; if (el.type === 'radio') { const c = $(`[name="${el.name}"]:checked`, hud); return c ? c.value : null; } return el.type === 'checkbox' ? el.checked : el.value; };
    const flag = k => { const el = $(`[data-ac-hud="${k}"] input[type=checkbox]`, hud); return el ? el.checked : false; };
    const shot = $('[data-ac-shot]', hud);
    const line = k => $(`[data-ac-line="${k}"]`, shot);
    const render = () => {
      const pos = v('position') || 'top';
      const style = v('style') || 'axon';
      const on = flag('enabled');
      const agency = (v('agency') || '').trim() || 'VOTRE UNITÉ';
      shot.classList.toggle('is-off', !on);
      shot.classList.toggle('is-discreet', style === 'discreet');
      $('[data-ac-bar="top"]', shot).hidden = !(pos === 'top' || pos === 'both');
      $('[data-ac-bar="bottom"]', shot).hidden = !(pos === 'bottom' || pos === 'both');
      line('agency').textContent = agency.toUpperCase();
      const meta = []; if (flag('show_datetime')) meta.push('2026-10-25 21:14:07'); if (flag('show_callsign')) meta.push('KODIAK 1-1'); if (flag('show_device')) meta.push('CAM CASQUE');
      const geo = []; if (flag('show_grid')) geo.push('GRID 151 173'); if (flag('show_heading')) geo.push('CAP 274°'); if (flag('show_altitude')) geo.push('ALT 312 m');
      line('meta').textContent = meta.join('  ·  ');
      line('geo').textContent = geo.join('  ·  ');
      line('custom').textContent = (v('custom') || '').trim().toUpperCase();
      line('logo').hidden = !flag('show_logo');
      line('logo').textContent = '© ' + agency.toUpperCase();
      // Une seule barre : tout y passe.
      if (pos === 'top') { line('meta').textContent = [...meta, ...geo].join('  ·  '); }
      if (pos === 'bottom') { line('geo').textContent = [...meta, ...geo].join('  ·  '); }
    };
    hud.addEventListener('input', render); hud.addEventListener('change', render); render();
  }

  // ------------------------------------------------------------ purge : mot de confirmation
  const purge = $('[data-ac-purge]');
  if (purge) {
    const input = $('[data-ac-purge-input]', purge); const btn = $('[data-ac-purge-btn]', purge);
    input.addEventListener('input', () => { btn.disabled = input.value.trim().toUpperCase() !== purge.dataset.acPurge; });
    purge.addEventListener('submit', e => { if (!window.confirm('Effacer définitivement le journal et les données de mission ? Action irréversible.')) e.preventDefault(); });
  }

  // ------------------------------------------------------------ sélecteur de symboles
  const markers = $('#bo-ac-markers');
  const dialog = $('#bo-ac-picker');
  if (markers && dialog) {
    let data = { cdn: '', library: [] };
    try { data = JSON.parse($('#bo-ac-marker-data').textContent || '{}'); } catch (e) { /* rien */ }
    const armaItems = () => { const idx = window.ArmaMarkerLibraryIndex; const it = idx && typeof idx.items === 'function' ? idx.items() : (idx && idx.ITEMS) || []; return it.filter(x => x && x.png); };
    const urlFor = value => {
      if (value.startsWith('upload:')) { const u = data.library.find(x => x.value === value); return u ? u.url : ''; }
      if (value.startsWith('arma:')) return (data.cdn ? data.cdn + '/' : '') + value.slice(5);
      return '';
    };
    const labelFor = value => {
      if (value === 'nato') return 'Symbole OTAN prévu';
      if (value.startsWith('upload:')) { const u = data.library.find(x => x.value === value); return u ? u.label + ' (vos icônes)' : 'Icône retirée'; }
      const it = armaItems().find(x => 'arma:' + x.png === value); return it ? it.label : value.slice(5);
    };
    const paint = li => {
      const value = $('input[type=hidden]', li).value;
      $('[data-ac-kind-label]', li).textContent = labelFor(value);
      const prev = $('[data-ac-kind-prev]', li);
      const u = urlFor(value);
      if (u) prev.innerHTML = `<img src="${esc(u)}" alt="">`;
      else if (value === 'nato') prev.innerHTML = '<span class="bo-ac-nato" aria-hidden="true"></span>';
    };
    $$('[data-ac-kind]', markers).forEach(paint);
    window.addEventListener('load', () => $$('[data-ac-kind]', markers).forEach(paint));

    let target = null;
    const grid = $('[data-ac-pick-grid]', dialog);
    const search = $('[data-ac-pick-search]', dialog);
    const norm = t => String(t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const cell = (value, label, url, group) => `<button type="button" class="bo-ac-pick" data-value="${esc(value)}" data-text="${esc(norm(label + ' ' + group + ' ' + value))}" title="${esc(label)}">${url ? `<img src="${esc(url)}" alt="" loading="lazy">` : '<span class="bo-ac-nato"></span>'}<span>${esc(label)}</span></button>`;
    const build = () => {
      const lib = data.library.map(x => cell(x.value, x.label, x.url, 'vos icônes')).join('');
      const groups = {};
      armaItems().forEach(x => { const g = x.source || x.category || 'carte'; (groups[g] = groups[g] || []).push(x); });
      grid.innerHTML = `<p class="bo-ac-pick__t">Par défaut</p><div class="bo-ac-pick__grid">${cell('nato', 'Symbole OTAN prévu', '', 'défaut otan')}</div>`
        + (lib ? `<p class="bo-ac-pick__t">Vos icônes</p><div class="bo-ac-pick__grid">${lib}</div>` : '')
        + Object.entries(groups).map(([g, items]) => `<p class="bo-ac-pick__t">Bibliothèque · ${esc(({ vanilla: 'Arma 3', metis: 'Metis Marker', markersplus: 'Markers Plus', ctab: 'cTab' })[g] || g)} <small>${items.length}</small></p><div class="bo-ac-pick__grid">${items.map(x => cell('arma:' + x.png, x.label || x.key, (data.cdn ? data.cdn + '/' : '') + x.png, g + ' ' + (x.category || ''))).join('')}</div>`).join('');
    };
    markers.addEventListener('click', e => {
      const b = e.target.closest('[data-ac-pick]'); if (!b) return;
      target = b.closest('[data-ac-kind]');
      if (!grid.childElementCount) build();
      const cur = $('input[type=hidden]', target).value;
      $$('.bo-ac-pick', grid).forEach(x => x.classList.toggle('is-current', x.dataset.value === cur));
      search.value = ''; search.dispatchEvent(new Event('input'));
      dialog.showModal(); search.focus();
    });
    search.addEventListener('input', () => {
      const q = norm(search.value.trim());
      $$('.bo-ac-pick', grid).forEach(x => { x.hidden = q !== '' && !q.split(/\s+/).every(w => x.dataset.text.includes(w)); });
      $$('.bo-ac-pick__grid', grid).forEach(g => {
        const any = [...g.children].some(x => !x.hidden);
        g.hidden = !any; if (g.previousElementSibling) g.previousElementSibling.hidden = !any;
      });
    });
    grid.addEventListener('click', e => {
      const p = e.target.closest('.bo-ac-pick'); if (!p || !target) return;
      $('input[type=hidden]', target).value = p.dataset.value; paint(target); dialog.close();
    });
  }

  // ------------------------------------------------------------ santé de la liaison (live)
  const base = root.dataset.base || '';
  const mapId = root.dataset.mapId || '1';
  const set = (k, v) => $$(`[data-live="${k}"]`).forEach(el => { el.textContent = v; });
  const mo = n => { n = Number(n || 0); return n < 1024 ? n + ' o' : n < 1048576 ? (n / 1024).toFixed(1) + ' ko' : (n / 1048576).toFixed(2) + ' Mo'; };
  const tick = () => fetch(base + '/api/atak/ingest-traffic?mapId=' + encodeURIComponent(mapId), { credentials: 'include', headers: { Accept: 'application/json' } })
    .then(r => (r.ok ? r.json() : null)).then(d => {
      if (!d) return;
      const load = d.load || {}; const live = Number(load.operators_live || 0); const total = Number(load.operators_total || 0);
      const k = Number(d.kbps_now || 0);
      set('ago', d.last_sync_ago || 'aucune'); set('ops', live + (total > live ? ' / ' + total : ''));
      set('kbps', k >= 10 ? Math.round(k) + ' ko/s' : k > 0 ? k.toFixed(1) + ' ko/s' : '0');
      set('vol', mo(d.bytes_15m)); set('photo', mo(d.photo_bytes_15m)); set('mk', String(Number(load.markers || 0)));
    }).catch(() => {});
  tick(); setInterval(() => { if (!document.hidden) tick(); }, 10000);
})();
