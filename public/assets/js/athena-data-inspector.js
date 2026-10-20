/* Athena Data Inspector — métriques des remontées jeu (mods, terminaux, débit, CR, erreurs, disponibilité). */
(() => {
  'use strict';
  const root = document.getElementById('athena-data-inspector');
  if (!root) return;

  const $ = (s, el = root) => el.querySelector(s);
  const $$ = (s, el = root) => [...el.querySelectorAll(s)];
  const state = { tab: 'overview', hours: 24, scope: 'tenant', q: '', offset: '+00:00', cache: {}, sort: {}, timer: null, busy: false };
  const view = $('#adi-view');

  // ------------------------------------------------------------------ format
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const num = v => Number(v || 0).toLocaleString('fr-FR');
  const pct = (v, d = 1) => v == null ? '—' : `${Number(v).toLocaleString('fr-FR', { maximumFractionDigits: d })} %`;
  const bytes = v => {
    let n = Number(v || 0); const u = ['o', 'Ko', 'Mo', 'Go', 'To']; let i = 0;
    while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
    return `${n.toLocaleString('fr-FR', { maximumFractionDigits: n < 10 && i ? 1 : 0 })} ${u[i]}`;
  };
  const toDate = s => { if (!s) return null; const d = new Date(String(s).replace(' ', 'T') + state.offset); return Number.isNaN(+d) ? null : d; };
  const dt = s => { const d = toDate(s); return d ? d.toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '—'; };
  const ago = s => {
    const d = toDate(s); if (!d) return '—';
    const n = Math.max(0, (Date.now() - d) / 1000);
    if (n < 60) return `il y a ${Math.round(n)} s`;
    if (n < 3600) return `il y a ${Math.round(n / 60)} min`;
    if (n < 86400) return `il y a ${Math.round(n / 3600)} h`;
    return `il y a ${Math.round(n / 86400)} j`;
  };
  const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'local';
  const period = () => ({ 1: 'dernière heure', 6: '6 dernières heures', 24: '24 dernières heures', 72: '3 derniers jours', 168: '7 derniers jours', 720: '30 derniers jours' }[state.hours]);

  const STATUS = {
    ok: ['OK', 'ok'], warn: ['ALERTE', 'warn'], ko: ['KO', 'ko'], missing: ['—', 'none'],
    online: ['EN LIGNE', 'ok'], degraded: ['DÉGRADÉ', 'warn'], offline: ['HORS LIGNE', 'ko'], never: ['JAMAIS VU', 'none'],
    new: ['NOUVEAU', 'ko'], ack: ['PRIS EN COMPTE', 'warn'], acknowledged: ['PRIS EN COMPTE', 'warn'], in_progress: ['EN COURS', 'warn'],
    resolved: ['RÉSOLU', 'ok'], closed: ['CLOS', 'ok'], ignored: ['IGNORÉ', 'none'],
  };
  const TAG = { ok: 'ok', warn: 'warn', ko: 'bad', none: 'neut' };
  const pill = s => { const [l, c] = STATUS[s] || [String(s || '—').toUpperCase(), 'none']; return c === 'none' && l === '—' ? '<span class="bo-di-muted">—</span>' : `<span class="ath-tag ath-tag--${TAG[c]}">${esc(l)}</span>`; };

  const STAGES = [
    ['link', 'Liaison jeu', 'Session COMSPEC Link authentifiée'],
    ['profile', 'Profil opérateur', 'Synchronisation du profil et du compte'],
    ['mods', 'Liste des mods', 'Mods chargés déclarés'],
    ['terminal', 'Terminal ATAK', 'Terminal en jeu enrôlé et vu'],
    ['queue', 'File d’envoi', 'COMSPEC Link : file, latence, échecs'],
    ['ingest', 'Ingestion Athena', 'Événements acceptés / rejetés'],
    ['reports', 'Remontées', 'CR, fiches, photos, messages'],
    ['incidents', 'Incidents mod', 'Tickets in-game-reports'],
  ];
  const playerName = p => p.callsign || p.user_label || p.steam_uid || p.key || '—';

  // ------------------------------------------------------------------ charts (SVG)
  function bars(series, key, opts = {}) {
    const w = 720, h = opts.h || 180, pad = 22, L = 64;
    const vals = series.map(s => Number(s[key] || 0));
    const max = Math.max(1, ...vals);
    const bw = (w - L - pad) / Math.max(1, vals.length);
    const fmt = opts.fmt || num;
    const ticks = [0, 0.5, 1].map(f => `<g><line x1="${L}" x2="${w - pad}" y1="${h - pad - (h - pad * 2) * f}" y2="${h - pad - (h - pad * 2) * f}" class="g"/><text x="${L - 6}" y="${h - pad - (h - pad * 2) * f + 3}" text-anchor="end">${esc(fmt(max * f))}</text></g>`).join('');
    const rects = vals.map((v, i) => {
      const bh = (h - pad * 2) * v / max, x = L + i * bw + 1, y = h - pad - bh;
      const t = toDate(series[i].t);
      const label = t ? t.toLocaleString('fr-FR', state.hours > 72 ? { day: '2-digit', month: '2-digit' } : { day: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
      return `<rect x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${Math.max(1, bw - 2).toFixed(1)}" height="${Math.max(0, bh).toFixed(1)}" class="${opts.cls || 'b'}"><title>${esc(label)} · ${esc(fmt(v))}</title></rect>`;
    }).join('');
    const first = toDate(series[0]?.t), last = toDate(series[series.length - 1]?.t);
    const lf = d => d ? d.toLocaleString('fr-FR', state.hours > 72 ? { day: '2-digit', month: '2-digit' } : { hour: '2-digit', minute: '2-digit' }) : '';
    return `<svg viewBox="0 0 ${w} ${h}" class="bo-di-chart" role="img" aria-label="${esc(opts.label || key)}">${ticks}${rects}
      <text x="${L}" y="${h - 4}">${esc(lf(first))}</text><text x="${w - pad}" y="${h - 4}" text-anchor="end">${esc(lf(last))}</text></svg>`;
  }
  function line(series, key, opts = {}) {
    const w = 720, h = opts.h || 180, pad = 22, L = 64;
    const pts = series.map((s, i) => [i, s[key]]).filter(p => p[1] != null);
    if (!pts.length) return `<div class="bo-di-empty is-small">Pas de mesure sur la période.</div>`;
    const min = opts.min ?? Math.min(...pts.map(p => p[1])), max = opts.max ?? Math.max(...pts.map(p => p[1]));
    const sx = i => L + (w - L - pad) * i / Math.max(1, series.length - 1);
    const sy = v => h - pad - (h - pad * 2) * (v - min) / Math.max(0.0001, max - min);
    const d = pts.map((p, j) => `${j ? 'L' : 'M'}${sx(p[0]).toFixed(1)},${sy(p[1]).toFixed(1)}`).join('');
    return `<svg viewBox="0 0 ${w} ${h}" class="bo-di-chart" role="img" aria-label="${esc(opts.label || key)}">
      <line x1="${L}" x2="${w - pad}" y1="${sy(max)}" y2="${sy(max)}" class="g"/><line x1="${L}" x2="${w - pad}" y1="${sy(min)}" y2="${sy(min)}" class="g"/>
      <text x="${L - 6}" y="${sy(max) + 4}" text-anchor="end">${esc((opts.fmt || num)(max))}</text><text x="${L - 6}" y="${sy(min) + 4}" text-anchor="end">${esc((opts.fmt || num)(min))}</text>
      <path d="${d}" class="ln"/>${pts.map(p => `<circle cx="${sx(p[0]).toFixed(1)}" cy="${sy(p[1]).toFixed(1)}" r="2.2" class="pt"><title>${esc(dt(series[p[0]].t))} · ${esc((opts.fmt || num)(p[1]))}</title></circle>`).join('')}</svg>`;
  }
  function hbars(items, valueKey, labelKey, fmt = num) {
    if (!items.length) return `<div class="bo-di-empty is-small">Aucune donnée.</div>`;
    const max = Math.max(1, ...items.map(i => Number(i[valueKey] || 0)));
    return `<ul class="bo-di-bars">${items.map(i => `<li><span class="bo-di-bars__l" title="${esc(i[labelKey])}">${esc(i[labelKey] || '—')}</span><span class="bo-di-bars__track"><i style="width:${(100 * Number(i[valueKey] || 0) / max).toFixed(1)}%"></i></span><span class="bo-di-bars__v">${esc(fmt(i[valueKey]))}</span></li>`).join('')}</ul>`;
  }

  // ------------------------------------------------------------------ blocs
  const kpi = (label, value, sub = '', tone = '') => `<article class="ath-card ath-kpi bo-di-kpi${tone ? ' is-' + tone : ''}"><p class="ath-kpi__label">${esc(label)}</p><p class="ath-kpi__value">${value}</p>${sub ? `<p class="ath-kpi__note">${sub}</p>` : ''}</article>`;
  const card = (title, body, lead = '') => `<section class="ath-panel bo-di-panel"><div class="bo-di-panel__head"><h3 class="ath-panel__title">${esc(title)}</h3>${lead ? `<p class="ath-panel__lead">${esc(lead)}</p>` : ''}</div>${body}</section>`;
  const empty = msg => `<p class="bo-di-empty">${esc(msg)}</p>`;

  /** Tableau triable : cols = [{k, l, f?(row), sort?(row), cls?}] */
  function table(id, cols, rows, opts = {}) {
    const head0 = opts.title ? `<div class="ath-table-toolbar"><span class="ath-table-toolbar__title">${esc(opts.title)}</span><span class="ath-table-toolbar__count">${num(rows.length)}</span>${opts.lead ? `<span class="bo-di-muted">${esc(opts.lead)}</span>` : ''}</div>` : '';
    if (!rows.length) return `<section class="ath-table-panel bo-di-tpanel">${head0}<p class="bo-di-empty is-flat">${esc(opts.empty || 'Aucune donnée pour ces filtres.')}</p></section>`;
    const s = state.sort[id];
    if (s) {
      const col = cols.find(c => c.k === s.k);
      const val = r => col && col.sort ? col.sort(r) : r[s.k];
      rows = [...rows].sort((a, b) => { const x = val(a), y = val(b); const c = (typeof x === 'number' && typeof y === 'number') ? x - y : String(x ?? '').localeCompare(String(y ?? ''), 'fr', { numeric: true }); return s.dir * c; });
    }
    const thc = c => (c.cls || '').replace('num', 'ath-th-num'), tdc = c => (c.cls || '').replace('num', 'ath-td-num');
    const head = cols.map(c => `<th class="${thc(c)}" data-sort="${esc(c.k)}" data-table="${esc(id)}" title="Trier">${esc(c.l)}${s && s.k === c.k ? (s.dir > 0 ? ' ▲' : ' ▼') : ''}</th>`).join('');
    const body = rows.map((r, i) => `<tr ${opts.click ? `data-row="${i}" tabindex="0" class="ath-row--link"` : ''}>${cols.map(c => `<td class="${tdc(c)}">${c.f ? c.f(r) : esc(r[c.k] ?? '—')}</td>`).join('')}</tr>`).join('');
    state.rows = state.rows || {}; state.rows[id] = rows;
    return `<section class="ath-table-panel bo-di-tpanel">${head0}<div class="ath-table-wrap"><table class="ath-table bo-di-table" data-id="${esc(id)}"><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></div></section>`;
  }

  // ------------------------------------------------------------------ vues
  const R = {};

  R.overview = d => {
    const http = d.http || {}, inc = d.incidents || {}, v = d.versions || {};
    const s = d.series || [];
    const platform = state.scope === 'platform';
    const outdatedPct = v.players ? Math.round(100 * v.outdated / v.players) : 0;
    return `<div class="ath-kpi-grid bo-di-kpis">
        ${platform ? kpi('Communautés actives', num(d.communities_active), `${num(d.communities_total)} au total`) : ''}
        ${kpi('Joueurs actifs', num(d.players_active), period())}
        ${kpi('Terminaux en ligne', num(d.terminals_online), `${num(d.terminals_active)} actifs · ${num(d.terminals_total)} enrôlés${d.terminals_compromised ? ` · <b class="bo-di-t-ko">${num(d.terminals_compromised)} compromis</b>` : ''}`)}
        ${kpi('Volume transmis', bytes(d.bytes), `${num(d.requests)} requêtes · photos ${bytes(d.photo_bytes)}`)}
        ${kpi('Remontées', num(d.reports_total), 'CR, fiches, MEDEVAC, SIGINT, photos, messages')}
        ${kpi('Disponibilité API', pct(http.availability, 2), `${num(http.errors_5xx)} erreurs serveur`, http.availability != null && http.availability < 99 ? 'warn' : 'ok')}
        ${kpi('Taux d’erreur API', pct(http.error_rate, 2), `${num(http.errors_4xx)} 4xx · ${num(http.errors_5xx)} 5xx`, http.error_rate > 5 ? 'warn' : '')}
        ${kpi('Rejets d’ingestion', pct(d.ingest_error_rate, 2), `${num(d.events_rejected)} rejetés / ${num(d.events_accepted + d.events_rejected)} événements`, d.ingest_error_rate > 2 ? 'warn' : '')}
        ${kpi('Incidents mod', num(inc.tickets), `${num(inc.hits)} occurrences · <b class="${inc.open ? 'bo-di-t-ko' : ''}">${num(inc.open)} ouvert(s)</b>`, inc.open ? 'ko' : '')}
        ${kpi('Versions à jour', v.players ? `${100 - outdatedPct} %` : '—', v.latest_link ? `Link ${esc(v.latest_link)} · mod ${esc(v.latest_mod || '?')}` : 'Version de référence inconnue', outdatedPct > 20 ? 'warn' : '')}
      </div>
      <div class="bo-di-grid2">
        ${card('Débit transmis', bars(s, 'bytes', { fmt: bytes, label: 'Octets transmis' }))}
        ${card('Disponibilité API (estimée)', line(s, 'availability', { fmt: v => pct(v, 2), max: 100, label: 'Disponibilité' }))}
        ${card('Erreurs HTTP API', bars(s, 'errors', { cls: 'b ko', label: 'Erreurs' }))}
        ${card('Incidents remontés par le mod', bars(s, 'incidents', { cls: 'b warn', label: 'Incidents' }))}
      </div>
      <div class="bo-di-grid2">
        ${card('Remontées par catégorie', hbars(d.reports || [], 'count', 'label'))}
        ${card('Versions COMSPEC Link en jeu', hbars(Object.entries(v.link_versions || {}).map(([k, n]) => ({ k: k === (v.latest_link || '') ? `${k} (dernière)` : k, n })), 'n', 'k'))}
      </div>`;
  };

  R.pipeline = d => {
    const rows = d.players || [];
    const cols = [
      { k: 'health', l: '', f: r => `<span class="bo-di-dot is-${r.health}" title="${esc({ ok: 'Chaîne complète', warn: 'En alerte', ko: 'En échec' }[r.health] || '')}"></span>`, sort: r => ({ ko: 0, warn: 1, ok: 2 }[r.health]) },
      { k: 'callsign', l: 'Joueur', f: r => `<b>${esc(playerName(r))}</b>${r.user_label && r.user_label !== r.callsign ? `<small>${esc(r.user_label)}</small>` : ''}`, sort: playerName },
      ...STAGES.map(([k, l]) => ({
        k: 'st_' + k, l, cls: 'stage',
        f: r => { const s = (r.stages || {})[k] || {}; return `<div class="bo-di-stage"><span class="bo-di-stage__d">${s.at ? esc(dt(s.at)) : ''}</span>${pill(s.status)}</div>`; },
        sort: r => ((r.stages || {})[k] || {}).at || '',
      })),
    ];
    const counts = { ok: 0, warn: 0, ko: 0 };
    rows.forEach(r => counts[r.health] = (counts[r.health] || 0) + 1);
    return `<div class="ath-kpi-grid bo-di-kpis">
        ${kpi('Joueurs suivis', num(rows.length), period())}
        ${kpi('Chaîne complète OK', num(counts.ok), '', 'ok')}
        ${kpi('En alerte', num(counts.warn), 'étape incomplète ou dégradée', counts.warn ? 'warn' : '')}
        ${kpi('En échec', num(counts.ko), 'rejets, terminal compromis ou ticket ouvert', counts.ko ? 'ko' : '')}
      </div>
      <p class="bo-di-hint">Chaque ligne suit un joueur de la liaison jeu jusqu’à ses remontées. Cliquez sur une ligne pour le détail des étapes. Heures affichées en ${esc(tz)}.</p>
      ${table('pipeline', cols, rows, { click: r => openPlayer(r), empty: 'Aucun joueur actif sur la période.' })}`;
  };

  R.players = d => {
    const rows = d.players || [];
    const totalBytes = rows.reduce((a, r) => a + Number(r.bytes || 0), 0);
    const cols = [
      { k: 'rank', l: '#', f: r => `${rows.indexOf(r) + 1}`, sort: r => -Number(r.bytes || 0) },
      { k: 'callsign', l: 'Joueur', f: r => `<b>${esc(playerName(r))}</b>${r.user_label && r.user_label !== r.callsign ? `<small>${esc(r.user_label)}</small>` : ''}`, sort: playerName },
      { k: 'steam_uid', l: 'Steam', f: r => r.steam_uid ? `<code>${esc(r.steam_uid)}</code>` : '—' },
      { k: 'bytes', l: 'Volume', cls: 'num', f: r => `${esc(bytes(r.bytes))}<small>${totalBytes ? pct(100 * r.bytes / totalBytes, 1) : ''}</small>`, sort: r => Number(r.bytes || 0) },
      { k: 'requests', l: 'Requêtes', cls: 'num', f: r => num(r.requests), sort: r => Number(r.requests || 0) },
      { k: 'reports', l: 'CR', cls: 'num', f: r => num(r.reports), sort: r => Number(r.reports || 0) },
      { k: 'sse_notes', l: 'Fiches SSE', cls: 'num', f: r => num(r.sse_notes), sort: r => Number(r.sse_notes || 0) },
      { k: 'photos', l: 'Photos', cls: 'num', f: r => num(r.photos), sort: r => Number(r.photos || 0) },
      { k: 'chat', l: 'Messages', cls: 'num', f: r => num(r.chat), sort: r => Number(r.chat || 0) },
      { k: 'reject_rate', l: 'Rejets', cls: 'num', f: r => r.reject_rate == null ? '—' : `<span class="${r.reject_rate > 5 ? 'bo-di-t-ko' : r.reject_rate > 0 ? 'bo-di-t-warn' : ''}">${pct(r.reject_rate)}</span>`, sort: r => Number(r.reject_rate ?? -1) },
      { k: 'incidents', l: 'Incidents', cls: 'num', f: r => r.incidents ? `<span class="bo-di-t-ko">${num(r.incidents)}</span>` : '0', sort: r => Number(r.incidents || 0) },
      { k: 'extension_version', l: 'Link / mod', f: r => `${esc(r.extension_version || '—')}<small>${esc(r.mod_version || '')}</small>` },
      { k: 'last_at', l: 'Dernière activité', f: r => `${esc(ago(r.last_at))}<small>${esc(dt(r.last_at))}</small>`, sort: r => r.last_at || '' },
    ];
    const top = rows.slice(0, 10).map(r => ({ l: playerName(r), b: r.bytes }));
    return `<div class="bo-di-grid2">${card('Qui transmet le plus', hbars(top, 'b', 'l', bytes))}
        ${card('Qui remonte le plus de comptes rendus', hbars([...rows].sort((a, b) => b.items - a.items).slice(0, 10).filter(r => r.items).map(r => ({ l: playerName(r), n: r.items })), 'n', 'l'))}</div>
      ${table('players', cols, rows, { click: r => openPlayer(r), empty: 'Aucun joueur actif sur la période.' })}`;
  };

  R.mods = d => {
    const v = d.versions || {};
    const cols = [
      { k: 'name', l: 'Mod', f: r => `<b>${esc(r.name || r.mod_dir)}</b><small>${esc(r.mod_dir)}</small>`, sort: r => r.name || r.mod_dir },
      { k: 'is_dlc', l: 'Type', f: r => r.is_dlc ? '<span class="ath-tag ath-tag--info">DLC</span>' : (r.workshop_id ? `<a class="ath-tag ath-tag--neut" href="https://steamcommunity.com/sharedfiles/filedetails/?id=${encodeURIComponent(r.workshop_id)}" target="_blank" rel="noopener">Workshop</a>` : '<span class="ath-tag ath-tag--neut bo-di-muted">Local</span>') },
      { k: 'players', l: 'Joueurs', cls: 'num', f: r => num(r.players), sort: r => r.players },
      { k: 'players_recent', l: 'Sur la période', cls: 'num', f: r => num(r.players_recent), sort: r => r.players_recent },
      ...(state.scope === 'platform' ? [{ k: 'communities', l: 'Communautés', cls: 'num', f: r => num(r.communities), sort: r => Number(r.communities || 0) }] : []),
      { k: 'first_seen', l: 'Vu la 1re fois', f: r => esc(dt(r.first_seen)), sort: r => r.first_seen || '' },
      { k: 'last_seen', l: 'Dernière détection', f: r => `${esc(ago(r.last_seen))}`, sort: r => r.last_seen || '' },
    ];
    return `<div class="ath-kpi-grid bo-di-kpis">
        ${kpi('Mods détectés', num(d.mods_total))}
        ${kpi('Joueurs avec modlist', num(d.players_with_list))}
        ${kpi('Mods par joueur', num(d.avg_mods_per_player), `max ${num(d.max_mods_per_player)}`)}
        ${kpi('Joueurs hors version', `${num(v.outdated)} / ${num(v.players)}`, v.latest_link ? `référence Link ${esc(v.latest_link)}` : '', v.outdated ? 'warn' : 'ok')}
      </div>
      <div class="bo-di-grid2">${card('Versions du mod COMSPEC', hbars(Object.entries(v.mod_versions || {}).map(([k, n]) => ({ k, n })), 'n', 'k'))}
        ${card('Versions COMSPEC Link', hbars(Object.entries(v.link_versions || {}).map(([k, n]) => ({ k, n })), 'n', 'k'))}</div>
      ${table('mods', cols, d.mods || [], { click: r => openMod(r), empty: 'Aucun mod déclaré par les joueurs.' })}`;
  };

  R.terminals = d => {
    const st = d.by_status || {};
    const cols = [
      { k: 'live', l: 'État', f: r => pill(r.live), sort: r => ({ online: 0, degraded: 1, offline: 2, never: 3 }[r.live]) },
      { k: 'terminal_label', l: 'Terminal', f: r => `<b>${esc(r.terminal_label || r.terminal_uid)}</b><small><code>${esc(r.terminal_uid)}</code></small>`, sort: r => r.terminal_label },
      { k: 'terminal_type', l: 'Type', f: r => `<span class="ath-tag ath-tag--neut">${esc(r.terminal_type)}</span>` },
      ...(state.scope === 'platform' ? [{ k: 'tenant_name', l: 'Communauté' }] : []),
      { k: 'operator_callsign', l: 'Opérateur', f: r => `${esc(r.operator_callsign || '—')}${r.user_label ? `<small>${esc(r.user_label)}</small>` : ''}` },
      { k: 'status', l: 'Enrôlement', f: r => `${esc(r.status)}${r.compromise_state && r.compromise_state !== 'none' ? ` <span class="ath-tag ath-tag--bad">${esc(r.compromise_state)}</span>` : ''}` },
      { k: 'mod_version', l: 'Mod / Link', f: r => `${esc(r.mod_version || '—')}<small>${esc(r.extension_version || '')}</small>` },
      { k: 'sync_pending', l: 'En attente', cls: 'num', f: r => r.sync_pending == null ? '—' : num(r.sync_pending), sort: r => Number(r.sync_pending || 0) },
      { k: 'last_seen_at', l: 'Dernier contact', f: r => `${esc(ago(r.last_seen_at))}<small>${esc(dt(r.last_seen_at))}</small>`, sort: r => r.last_seen_at || '' },
      { k: 'first_seen_at', l: 'Mise en service', f: r => esc(dt(r.first_seen_at)), sort: r => r.first_seen_at || '' },
    ];
    return `<div class="ath-kpi-grid bo-di-kpis">
        ${kpi('En ligne', num(st.online), `vu < ${d.online_min} min`, 'ok')}
        ${kpi('Dégradés', num(st.degraded), 'vu < 30 min', st.degraded ? 'warn' : '')}
        ${kpi('Hors ligne', num(st.offline))}
        ${kpi('Jamais vus', num(st.never), 'enrôlés sans contact')}
      </div>
      <div class="bo-di-grid2">${card('Par type de terminal', hbars(Object.entries(d.by_type || {}).map(([k, n]) => ({ k, n })), 'n', 'k'))}
        ${card('Disponibilité du parc', hbars([['En ligne', st.online], ['Dégradés', st.degraded], ['Hors ligne', st.offline], ['Jamais vus', st.never]].map(([k, n]) => ({ k, n })), 'n', 'k'))}</div>
      ${table('terminals', cols, d.terminals || [], { empty: 'Aucun terminal ATAK enrôlé.' })}`;
  };

  R.traffic = d => {
    const q = d.queue_summary || {};
    const qcols = [
      { k: 'call_sign', l: 'Joueur', f: r => `<b>${esc(r.call_sign || r.steam_uid)}</b><small>${esc(r.client_kind)}</small>` },
      { k: 'overload', l: 'Charge', f: r => r.overload === 'ok' ? pill('ok') : `<span class="ath-tag ath-tag--warn">${esc(r.overload)}</span>` },
      { k: 'queued', l: 'En file', cls: 'num', sort: r => Number(r.queued) },
      { k: 'retry', l: 'Réessais', cls: 'num', sort: r => Number(r.retry) },
      { k: 'failed', l: 'Échecs', cls: 'num', f: r => r.failed > 0 ? `<span class="bo-di-t-ko">${num(r.failed)}</span>` : '0', sort: r => Number(r.failed) },
      { k: 'p95_latency_ms', l: 'Latence p95', cls: 'num', f: r => `${num(r.p95_latency_ms)} ms<small>moy. ${num(r.avg_latency_ms)} ms</small>`, sort: r => Number(r.p95_latency_ms) },
      { k: 'http_5xx', l: '5xx / 429 / timeouts', cls: 'num', f: r => `${num(r.http_5xx)} / ${num(r.http_429)} / ${num(r.timeouts)}` },
      { k: 'extension_version', l: 'Link', f: r => esc(r.extension_version || '—') },
      { k: 'received_at', l: 'Relevé', f: r => esc(ago(r.received_at)), sort: r => r.received_at || '' },
    ];
    const pcols = [
      { k: 'callsign', l: 'Joueur', f: r => `<b>${esc(r.callsign || r.steam_uid)}</b>` },
      { k: 'bytes', l: 'Volume', cls: 'num', f: r => esc(bytes(r.bytes)), sort: r => Number(r.bytes) },
      { k: 'photo_bytes', l: 'dont photos', cls: 'num', f: r => esc(bytes(r.photo_bytes)), sort: r => Number(r.photo_bytes) },
      { k: 'requests', l: 'Requêtes', cls: 'num', f: r => num(r.requests), sort: r => Number(r.requests) },
      { k: 'accepted', l: 'Acceptés', cls: 'num', f: r => num(r.accepted), sort: r => Number(r.accepted) },
      { k: 'rejected', l: 'Rejetés', cls: 'num', f: r => r.rejected > 0 ? `<span class="bo-di-t-ko">${num(r.rejected)}</span>` : '0', sort: r => Number(r.rejected) },
      { k: 'last_at', l: 'Dernier envoi', f: r => esc(ago(r.last_at)), sort: r => r.last_at || '' },
    ];
    return `<div class="ath-kpi-grid bo-di-kpis">
        ${kpi('Volume transmis', bytes(d.bytes), `${num(d.requests)} requêtes`)}
        ${kpi('Débit moyen', `${Number(d.avg_kbps || 0).toLocaleString('fr-FR')} kbit/s`, period())}
        ${kpi('Pic', `${Number(d.peak?.kbps || 0).toLocaleString('fr-FR')} kbit/s`, d.peak?.at ? esc(dt(d.peak.at)) : '—')}
        ${kpi('Photos', bytes(d.photo_bytes), d.bytes ? pct(100 * d.photo_bytes / d.bytes) + ' du volume' : '')}
        ${kpi('Clients COMSPEC Link', num(q.clients), `${num(q.overloaded)} en surcharge`, q.overloaded ? 'warn' : '')}
        ${kpi('Latence d’envoi', `${num(q.avg_latency_ms)} ms`, `p95 max ${num(q.p95_latency_ms)} ms`)}
        ${kpi('Échecs d’envoi', num(q.failed), `${num(q.http_5xx)} 5xx · ${num(q.http_429)} 429 · ${num(q.timeouts)} timeouts`, q.failed ? 'warn' : '')}
      </div>
      <div class="bo-di-grid2">${card('Volume transmis dans le temps', bars(d.series || [], 'bytes', { fmt: bytes }))}
        ${card('Requêtes dans le temps', bars(d.series || [], 'requests', { cls: 'b alt' }))}</div>
      ${card(`Volume par ${d.group_kind}`, hbars(d.by_group || [], 'bytes', 'label', bytes))}
      ${table('top_players', pcols, d.top_players || [], { title: 'Joueurs qui transmettent le plus', empty: 'Pas encore de mesure par joueur : elle démarre avec cette version.' })}
      ${table('queues', qcols, d.queues || [], { title: 'Files d’envoi COMSPEC Link (dernier relevé par joueur)', empty: 'Aucun relevé de file d’envoi sur la période.' })}`;
  };

  R.errors = d => {
    const http = d.http || {};
    const tcols = [
      { k: 'id', l: 'Ticket', f: r => `<b>#${esc(r.id)}</b>`, sort: r => Number(r.id) },
      { k: 'workflow_status', l: 'Statut', f: r => pill(r.workflow_status) },
      { k: 'severity', l: 'Gravité', f: r => `<span class="ath-tag ath-tag--${r.severity === 'error' ? 'bad' : r.severity === 'warn' || r.severity === 'warning' ? 'warn' : 'neut'}">${esc(r.severity)}</span>` },
      { k: 'channel', l: 'Canal' },
      { k: 'message', l: 'Message', f: r => `<span class="bo-di-msg" title="${esc(r.message)}">${esc(r.message)}</span>` },
      { k: 'callsign', l: 'Joueur', f: r => `${esc(r.callsign || r.player_name || '—')}<small>${esc(r.source)}</small>` },
      { k: 'mod_version', l: 'Versions', f: r => `${esc(r.mod_version || '—')}<small>Link ${esc(r.extension_version || '—')}</small>` },
      { k: 'hit_count', l: 'Occ.', cls: 'num', f: r => num(r.hit_count), sort: r => Number(r.hit_count) },
      { k: 'last_seen_at', l: 'Dernier envoi', f: r => `${esc(ago(r.last_seen_at))}<small>${esc(dt(r.last_seen_at))}</small>`, sort: r => r.last_seen_at || '' },
    ];
    const icols = [
      { k: 'event_type', l: 'Type d’événement', f: r => `<code>${esc(r.event_type)}</code>` },
      { k: 'error_code', l: 'Motif du rejet', f: r => `<code>${esc(r.error_code)}</code>` },
      { k: 'hits', l: 'Rejets', cls: 'num', f: r => num(r.hits), sort: r => Number(r.hits) },
      { k: 'last_at', l: 'Dernier', f: r => esc(ago(r.last_at)), sort: r => r.last_at || '' },
    ];
    const hcols = [
      { k: 'route_path', l: 'Route', f: r => `<code>${esc(r.method)} ${esc(r.route_path)}</code>` },
      { k: 'status_code', l: 'Code', f: r => `<span class="ath-tag ath-tag--${r.status_code >= 500 ? 'bad' : 'warn'}">${esc(r.status_code)}</span>`, sort: r => Number(r.status_code) },
      { k: 'n', l: 'Nombre', cls: 'num', f: r => num(r.n), sort: r => Number(r.n) },
      { k: 'avg_ms', l: 'Durée moy.', cls: 'num', f: r => `${num(r.avg_ms)} ms`, sort: r => Number(r.avg_ms) },
      { k: 'last_at', l: 'Dernière', f: r => esc(ago(r.last_at)), sort: r => r.last_at || '' },
    ];
    const scols = [
      { k: 'route_path', l: 'Route lente (≥ 1 s)', f: r => `<code>${esc(r.method)} ${esc(r.route_path)}</code>` },
      { k: 'n', l: 'Nombre', cls: 'num', f: r => num(r.n), sort: r => Number(r.n) },
      { k: 'avg_ms', l: 'Moyenne', cls: 'num', f: r => `${num(r.avg_ms)} ms`, sort: r => Number(r.avg_ms) },
      { k: 'max_ms', l: 'Max', cls: 'num', f: r => `${num(r.max_ms)} ms`, sort: r => Number(r.max_ms) },
    ];
    const open = (d.by_status || []).find(s => s.label === 'new');
    return `<div class="ath-kpi-grid bo-di-kpis">
        ${kpi('Disponibilité API', pct(http.availability, 3), '1 − part des erreurs serveur', http.availability != null && http.availability < 99 ? 'warn' : 'ok')}
        ${kpi('Taux d’erreur', pct(http.error_rate, 2), `${num(http.errors_4xx)} 4xx · ${num(http.errors_5xx)} 5xx`, http.error_rate > 5 ? 'warn' : '')}
        ${kpi('Requêtes API (estim.)', num(http.requests_estimated), `succès échantillonnés à ${pct((http.sample_rate || 0) * 100, 0)}`)}
        ${kpi('Temps de réponse', http.avg_ms == null ? '—' : `${num(http.avg_ms)} ms`, 'moyenne des succès')}
        ${kpi('Tickets ouverts', num(open ? open.tickets : 0), `${num((d.tickets || []).length)} sur la période`, open && open.tickets ? 'ko' : '')}
      </div>
      <div class="bo-di-grid2">${card('Incidents par canal', hbars(d.by_channel || [], 'hits', 'label'))}${card('Incidents par version', hbars(d.by_version || [], 'hits', 'label'))}</div>
      ${table('tickets', tcols, d.tickets || [], { title: 'Tickets in-game-reports', click: r => openTicket(r), empty: 'Aucun incident remonté par le mod sur la période.' })}
      <div class="bo-di-grid2">${table('ingest_errors', icols, d.ingest_errors || [], { title: 'Rejets d’ingestion télémétrie', empty: 'Aucun rejet enregistré (mesure active depuis cette version).' })}
        ${table('http_errors', hcols, d.http_errors || [], { title: 'Erreurs HTTP par route', empty: 'Aucune erreur HTTP sur la période.' })}</div>
      ${table('slow_routes', scols, d.slow_routes || [], { title: 'Routes lentes', empty: 'Aucune requête lente.' })}`;
  };

  R.communities = d => {
    const rows = d.communities || [];
    const cols = [
      { k: 'name', l: 'Communauté', f: r => `<b>${esc(r.name)}</b><small>${esc(r.slug)} · ${esc(r.type)}</small>` },
      { k: 'users', l: 'Comptes', cls: 'num', f: r => num(r.users), sort: r => r.users },
      { k: 'players_active', l: 'Joueurs actifs', cls: 'num', f: r => num(r.players_active), sort: r => r.players_active },
      { k: 'terminals', l: 'Terminaux', cls: 'num', f: r => `${num(r.terminals_online)} / ${num(r.terminals)}<small>en ligne / total</small>`, sort: r => r.terminals },
      { k: 'bytes', l: 'Volume', cls: 'num', f: r => esc(bytes(r.bytes)), sort: r => r.bytes },
      { k: 'requests', l: 'Requêtes jeu', cls: 'num', f: r => num(r.requests), sort: r => r.requests },
      { k: 'reports', l: 'Remontées', cls: 'num', f: r => num(r.reports), sort: r => r.reports },
      { k: 'mods', l: 'Mods', cls: 'num', f: r => num(r.mods), sort: r => r.mods },
      { k: 'error_rate', l: 'Erreurs API', cls: 'num', f: r => r.error_rate == null ? '—' : `<span class="${r.error_rate > 5 ? 'bo-di-t-ko' : ''}">${pct(r.error_rate, 2)}</span>`, sort: r => Number(r.error_rate ?? -1) },
      { k: 'availability', l: 'Dispo.', cls: 'num', f: r => pct(r.availability, 2), sort: r => Number(r.availability ?? -1) },
      { k: 'last_activity', l: 'Dernière activité jeu', f: r => esc(ago(r.last_activity)), sort: r => r.last_activity || '' },
    ];
    const active = rows.filter(r => r.bytes > 0).length;
    return `<div class="ath-kpi-grid bo-di-kpis">${kpi('Communautés', num(rows.length))}${kpi('Actives en jeu', num(active), period(), 'ok')}
        ${kpi('Joueurs actifs', num(rows.reduce((a, r) => a + r.players_active, 0)))}${kpi('Volume total', bytes(rows.reduce((a, r) => a + r.bytes, 0)))}</div>
      ${card('Volume par communauté', hbars(rows.filter(r => r.bytes).slice(0, 12), 'bytes', 'name', bytes))}
      ${table('communities', cols, rows)}`;
  };

  // ------------------------------------------------------------------ tiroir de détail
  const drawer = $('#adi-drawer');
  function openDrawer(title, html, kicker = 'Détail') { $('#adi-drawer-kicker').textContent = kicker; $('#adi-drawer-title').textContent = title; $('#adi-drawer-body').innerHTML = html; drawer.hidden = false; $('[data-close]', drawer).focus(); }
  function closeDrawer() { drawer.hidden = true; }
  drawer.addEventListener('click', e => { if (e.target === drawer || e.target.closest('[data-close]')) closeDrawer(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !drawer.hidden) closeDrawer(); });

  function openPlayer(p) {
    const stages = p.stages || {};
    const cards = STAGES.map(([k, l, hint]) => {
      const s = stages[k];
      return `<article class="bo-di-step is-${s ? s.status : 'missing'}"><header><span>${esc(l)}</span>${s && s.status !== 'missing' ? pill(s.status) : ''}</header>
        ${s && s.at ? `<b>${esc(dt(s.at))}</b>` : ''}${s && s.first_at ? `<small>depuis le ${esc(dt(s.first_at))}</small>` : ''}
        <p>${esc(s && s.status !== 'missing' ? s.detail : 'Donnée non renseignée.')}</p><small class="hint">${esc(hint)}</small></article>`;
    }).join('');
    const facts = [
      ['Indicatif', p.callsign], ['Compte Athena', p.user_label], ['Steam', p.steam_uid], ['Mod / Link', `${p.mod_version || '—'} · ${p.extension_version || '—'}`],
      ['Volume transmis', bytes(p.bytes)], ['Requêtes', num(p.requests)], ['Événements acceptés', num(p.events_accepted)], ['Rejetés', `${num(p.events_rejected)}${p.reject_rate != null ? ` (${pct(p.reject_rate)})` : ''}`],
      ['Comptes rendus', num(p.reports)], ['Fiches SSE', num(p.sse_notes)], ['Photos', num(p.photos)], ['Messages', num(p.chat)], ['Incidents', num(p.incidents)], ['Dernière activité', dt(p.last_at)],
    ];
    openDrawer(playerName(p), `${stages && Object.keys(stages).length ? `<h4>Étapes</h4><div class="bo-di-steps">${cards}</div>` : ''}
      <h4>Synthèse ${esc(period())}</h4><dl class="bo-di-facts">${facts.map(([k, v]) => `<dt>${esc(k)}</dt><dd>${esc(v || '—')}</dd>`).join('')}</dl>
      <p class="bo-di-actions"><a class="ath-btn ath-btn--solid" href="${esc(root.dataset.remontees)}?q=${encodeURIComponent(p.callsign || p.steam_uid || '')}">Voir ses remontées</a></p>`, 'Joueur');
  }
  async function openMod(m) {
    openDrawer(m.name || m.mod_dir, '<p class="bo-di-empty is-loading">Chargement des joueurs…</p>', m.is_dlc ? 'DLC' : 'Mod');
    const j = await load('mod_players', { mod: m.mod_dir });
    const rows = (j && j.data && j.data.players) || [];
    $('#adi-drawer-body').innerHTML = `<dl class="bo-di-facts"><dt>Dossier</dt><dd><code>${esc(m.mod_dir)}</code></dd><dt>Workshop</dt><dd>${esc(m.workshop_id || '—')}</dd>
      <dt>Joueurs</dt><dd>${num(m.players)}</dd><dt>Première détection</dt><dd>${esc(dt(m.first_seen))}</dd></dl>
      <h4>Joueurs l’ayant chargé</h4>${rows.length ? `<ul class="bo-di-list">${rows.map(r => `<li><b>${esc(r.callsign || r.steam_id)}</b><span>${esc(ago(r.last_seen))}</span></li>`).join('')}</ul>` : empty('Aucun joueur.')}`;
  }
  function openTicket(t) {
    openDrawer(`Ticket #${t.id}`, `<dl class="bo-di-facts">
      <dt>Statut</dt><dd>${pill(t.workflow_status)}</dd><dt>Gravité</dt><dd>${esc(t.severity)}</dd><dt>Canal</dt><dd>${esc(t.channel)}</dd>
      <dt>Source</dt><dd>${esc(t.source)}</dd><dt>Joueur</dt><dd>${esc(t.callsign || t.player_name || '—')}</dd><dt>Steam</dt><dd>${esc(t.steam_uid || '—')}</dd>
      <dt>Versions</dt><dd>Mod ${esc(t.mod_version || '—')} · Link ${esc(t.extension_version || '—')}</dd><dt>Occurrences</dt><dd>${num(t.hit_count)}</dd>
      <dt>Premier envoi</dt><dd>${esc(dt(t.created_at))}</dd><dt>Dernier envoi</dt><dd>${esc(dt(t.last_seen_at))}</dd></dl>
      <h4>Message</h4><pre class="bo-di-pre">${esc(t.message)}</pre>`, 'Incident mod');
  }

  // ------------------------------------------------------------------ chargement
  function url(viewName, extra = {}) {
    const u = new URL(root.dataset.api, location.origin);
    u.searchParams.set('view', viewName); u.searchParams.set('hours', state.hours); u.searchParams.set('scope', state.scope);
    if (state.q) u.searchParams.set('q', state.q);
    Object.entries(extra).forEach(([k, v]) => u.searchParams.set(k, v));
    return u;
  }
  async function load(viewName, extra = {}) {
    try {
      const r = await fetch(url(viewName, extra), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const j = await r.json().catch(() => null);
      if (!r.ok || !j || !j.ok) throw new Error((j && j.error) || `HTTP ${r.status}`);
      state.offset = j.db_offset || state.offset;
      if (viewName === state.tab && !Object.keys(extra).length) state.last = j.data; // re-tri sans rechargement
      setLive(true);
      return j;
    } catch (e) { setLive(false, e.message); return null; }
  }
  function setLive(ok, msg = '') {
    const el = $('#adi-live'); el.classList.toggle('is-ok', ok); el.classList.toggle('is-ko', !ok);
    $('span', el).textContent = ok ? `À jour · ${new Date().toLocaleTimeString('fr-FR')}` : `Indisponible${msg ? ' · ' + msg : ''}`;
  }
  const SEARCHABLE = ['pipeline', 'players', 'mods', 'terminals'];
  const CSV = ['pipeline', 'players', 'mods', 'terminals', 'traffic', 'errors', 'communities'];
  async function render(silent = false) {
    if (state.busy) return; state.busy = true;
    const tab = state.tab;
    $('#adi-toolbar').hidden = !SEARCHABLE.includes(tab) && !CSV.includes(tab);
    $('#adi-q').closest('label').hidden = !SEARCHABLE.includes(tab);
    const csv = $('#adi-csv'); csv.hidden = !CSV.includes(tab); csv.href = url(tab, { format: 'csv' }).toString();
    if (!silent) view.innerHTML = '<p class="bo-di-empty is-loading">Chargement des métriques…</p>';
    const j = await load(tab);
    state.busy = false;
    if (tab !== state.tab) return;
    if (!j) { if (!silent) view.innerHTML = empty('Impossible de charger les métriques. Nouvel essai dans 30 s.'); return; }
    const notice = $('#adi-notice');
    const miss = (j.unavailable || []).filter(Boolean);
    notice.hidden = !miss.length;
    notice.textContent = miss.length ? `Sources non disponibles sur cette installation : ${miss.join(', ')}. Les sections concernées restent vides.` : '';
    const scrollY = window.scrollY;
    view.innerHTML = R[tab](j.data || {});
    if (silent) window.scrollTo(0, scrollY);
  }

  // ------------------------------------------------------------------ interactions
  $$('[data-tab]').forEach(b => b.addEventListener('click', () => {
    state.tab = b.dataset.tab;
    $$('[data-tab]').forEach(x => { x.classList.toggle('is-active', x === b); x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
    render();
  }));
  $$('[data-control]').forEach(group => group.addEventListener('click', e => {
    const b = e.target.closest('button'); if (!b) return;
    $$('button', group).forEach(x => { x.classList.toggle('is-on', x === b); x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
    if (group.dataset.control === 'hours') state.hours = Number(b.dataset.value);
    if (group.dataset.control === 'scope') {
      state.scope = b.dataset.value;
      const ct = $('[data-tab="communities"]');
      if (ct) ct.hidden = state.scope !== 'platform';
      if (state.tab === 'communities' && state.scope !== 'platform') { $('[data-tab="overview"]').click(); return; }
    }
    render();
  }));
  const ct = $('[data-tab="communities"]'); if (ct) ct.hidden = true;
  let qTimer = null;
  $('#adi-q').addEventListener('input', e => { clearTimeout(qTimer); qTimer = setTimeout(() => { state.q = e.target.value.trim(); render(); }, 350); });
  view.addEventListener('click', e => {
    const th = e.target.closest('th[data-sort]');
    if (th) {
      const id = th.dataset.table, k = th.dataset.sort, cur = state.sort[id];
      state.sort[id] = { k, dir: cur && cur.k === k ? -cur.dir : -1 };
      const j = state.last; if (j) view.innerHTML = R[state.tab](j);
      return;
    }
    const tr = e.target.closest('tr[data-row]');
    if (tr && !e.target.closest('a')) rowClick(tr);
  });
  view.addEventListener('keydown', e => { const tr = e.target.closest('tr[data-row]'); if (tr && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); rowClick(tr); } });
  function rowClick(tr) {
    const id = tr.closest('table').dataset.id, row = (state.rows[id] || [])[Number(tr.dataset.row)];
    if (!row) return;
    if (id === 'pipeline' || id === 'players') openPlayer(row);
    else if (id === 'mods') openMod(row);
    else if (id === 'tickets') openTicket(row);
  }
  render();
  state.timer = setInterval(() => { if (!document.hidden && drawer.hidden) render(true); }, 30000);
})();
