/**
 * ORBAT — page unique : organigramme, annuaire et tableur, édition en fenêtres, menu contextuel, glisser-déposer,
 * renommage sur place, enregistrement optimiste (annulé si le serveur refuse), annuler / rétablir, raccourcis.
 *
 * Données : #orbat-boot (JSON posé par views/partials/orbat/orbat_canvas.php). API : /api/orbat/*.
 * Rendu incrémental : une carte par unité, mise à jour seulement si son contenu change ; zoom et déplacement
 * par transform CSS. Symboles MIL-STD-2525 par milsymbol (chargé après le premier affichage, mis en cache par
 * NatoSidcIcons).
 */
(function () {
  'use strict';

  var bootEl = document.getElementById('orbat-boot');
  var rootEl = document.getElementById('orb');
  if (!bootEl || !rootEl) return;
  var B = JSON.parse(bootEl.textContent || '{}');
  var L = B.i18n || {};

  /* ------------------------------------------------------------------ utilitaires */
  var NW = 272, NH = 232, GX = 28, GY = 72;
  var STATUS_IDS = ['active', 'partially_active', 'forming', 'reorganizing', 'reserve', 'inactive', 'archived'];
  var STATUS_COLOR = {
    active: '#34d399', partially_active: '#fbbf24', forming: '#60a5fa', reorganizing: '#a78bfa',
    reserve: '#94a3b8', inactive: '#f87171', archived: '#64748b'
  };
  var DEFAULT_STATUSES = ['active', 'partially_active', 'forming', 'reorganizing', 'reserve'];
  var VIS_COLOR = { normal: '#34d399', restricted: '#fbbf24', anonymized: '#60a5fa', hidden: '#64748b' };
  var LEADER_KINDS = { commander: true, deputy: true };

  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function t(key, vars) {
    var s = L[key] != null ? String(L[key]) : key;
    if (vars) Object.keys(vars).forEach(function (k) { s = s.split(':' + k).join(String(vars[k])); });
    return s;
  }
  /* Pastille commune « masqué / anonymisé / restreint » (même rendu que App\Support\ConfidentialDisplay). */
  var CONF_ICON = {
    hidden: '<path d="M3 3l18 18"/><path d="M10.6 5.1A10 10 0 0 1 12 5c5 0 9 4.5 10 7a12.6 12.6 0 0 1-3.2 4.3"/><path d="M6.6 6.6C4.4 8 2.8 10.1 2 12c1 2.5 5 7 10 7a9.9 9.9 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
    anonymized: '<path d="M3 7c3-1 6-1.5 9-1.5S18 6 21 7c0 6-3.5 10-9 10S3 13 3 7z"/><circle cx="8.5" cy="10.5" r="1.3"/><circle cx="15.5" cy="10.5" r="1.3"/>',
    restricted: '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>'
  };
  function confBadge(c, compact) {
    if (!c || !c.label) return '';
    var tone = /^[a-z_-]+$/.test(c.tone || '') ? c.tone : 'restricted';
    return '<span class="ath-confid ath-confid--' + tone + (compact ? ' ath-confid--sm' : '') + '" title="' + esc(c.hint || '') + '" tabindex="0">' +
      '<svg class="ath-confid__icon" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' +
      (CONF_ICON[tone] || CONF_ICON.restricted) + '</svg><span class="ath-confid__label">' + esc(c.label) + '</span>' +
      (c.hint ? '<span class="sr-only"> — ' + esc(c.hint) + '</span>' : '') + '</span>';
  }
  function tn(n, one, many, vars) { return t(n > 1 ? many : one, Object.assign({ n: n }, vars || {})); }
  function clone(o) {
    if (typeof structuredClone === 'function') return structuredClone(o);
    return JSON.parse(JSON.stringify(o));
  }
  function api(path) { return B.base + path; }
  function abbr(n) {
    var src = String(n.role && n.role !== 'Unité' ? n.role : (n.label || '')).trim();
    var w = src.split(/[\s\-\/—]+/).filter(Boolean);
    return ((w.length === 1 ? w[0].slice(0, 4) : w.map(function (x) { return x[0]; }).join('').slice(0, 4)) || '?').toUpperCase();
  }
  function statusLabel(id) { return t('status_' + (id || 'active')); }
  function structLabel(id) {
    var list = B.structTypes || [];
    for (var i = 0; i < list.length; i++) if (list[i].id === id) return list[i].label;
    return '';
  }
  function readinessColor(v) { return v >= 80 ? '#34d399' : (v >= 60 ? '#fbbf24' : '#f87171'); }

  /* ------------------------------------------------------------------ état */
  var S = {
    root: null,
    byKey: new Map(),
    parent: new Map(),
    keyOfUnit: new Map(),
    statuses: new Set(DEFAULT_STATUSES),
    collapsed: new Set(),
    q: '',
    hits: [],
    hitIdx: -1,
    sel: null,
    view: 'tree',
    z: 1, x: 0, y: 0,
    pos: new Map(),
    previewAs: '',
    seq: 0,
    pending: 0,
    undo: [],
    redo: [],
    opts: null,
    ms: false,
    lastSnap: '',
    dirtyViews: true,
    ver: 1
  };
  var cards = new Map();
  var cardSig = new Map();

  function setRoster(root) {
    S.root = root || null;
    S.byKey = new Map();
    S.parent = new Map();
    S.keyOfUnit = new Map();
    if (!S.root) return;
    (function walk(n, p) {
      S.byKey.set(n.id, n);
      if (p) S.parent.set(n.id, p.id);
      if (n.unitId > 0) S.keyOfUnit.set(n.unitId, n.id);
      (n.children || []).forEach(function (c) { walk(c, n); });
    })(S.root, null);
    if (S.sel && !S.byKey.has(S.sel)) S.sel = null;
    S.dirtyViews = true;
    S.ver++;
  }
  /* Signature d’un nœud sans ses enfants (chaque carte ne dépend que de sa propre unité). */
  function sigOf(n) { return JSON.stringify(n, function (k, v) { return k === 'children' ? (v ? v.length : 0) : v; }); }
  function node(key) { return S.byKey.get(key) || null; }
  function nodeOfUnit(id) { return node(S.keyOfUnit.get(id)); }
  function parentNode(n) { return n ? node(S.parent.get(n.id)) : null; }
  function ancestors(key) {
    var out = [];
    var p = S.parent.get(key);
    while (p) { out.unshift(p); p = S.parent.get(p); }
    return out;
  }
  function descendants(n) {
    var out = [];
    (function walk(x) { (x.children || []).forEach(function (c) { out.push(c); walk(c); }); })(n);
    return out;
  }
  function shown(n) { return !(n.unitId > 0) || S.statuses.has(n.adminStatus || 'active'); }
  function kids(n) { return (n.children || []).filter(shown); }
  function isUnit(n) { return !!n && n.unitId > 0 && !n.isOrbatPlaceholder; }
  function canEdit(n) { return B.canManage && isUnit(n); }

  /* ------------------------------------------------------------------ symboles MIL-STD-2525 */
  /* onLight : symbole posé sur fond clair (fiche, listes) ; sinon liseré clair pour lire échelon et hampe sur le canevas sombre. */
  function symbolSvg(sidc, size, onLight) {
    if (!S.ms || !window.NatoSidcIcons || !sidc) return '';
    var r = window.NatoSidcIcons.milsymbolSvg({ sidc: sidc, size: size, outlineColor: onLight ? '' : 'rgba(233,234,228,.85)' });
    return r && r.svg ? r.svg : '';
  }
  function symHtml(n, size, onLight) {
    if (n.isOrbatPlaceholder) return '<span class="orb-sym-ph">◆</span>';
    var svg = symbolSvg(n.symbolSidc, size, onLight);
    return svg || '<span class="orb-sym-ph">' + esc(abbr(n)) + '</span>';
  }
  function loadMilsymbol() {
    if (window.ms && typeof window.ms.Symbol === 'function') { S.ms = true; return; }
    if (!B.msUrl) return;
    var s = document.createElement('script');
    s.src = B.msUrl;
    s.async = true;
    s.onload = function () { S.ms = !!(window.ms && window.ms.Symbol); S.ver++; render(); refreshOpenModalPreview(); };
    document.head.appendChild(s);
  }
  function echelonLetter(id) {
    var list = (B.symbols && B.symbols.echelons) || [];
    for (var i = 0; i < list.length; i++) if (list[i].id === id) return list[i].letter;
    return '';
  }
  function echelonLabel(id) {
    var list = (B.symbols && B.symbols.echelons) || [];
    for (var i = 0; i < list.length; i++) if (list[i].id === id) return list[i].label;
    return '';
  }
  function functionOption(id) {
    var list = (B.symbols && B.symbols.functions) || [];
    for (var i = 0; i < list.length; i++) if (list[i].id === id) return list[i];
    return null;
  }
  /* SIDC final côté client (aperçu immédiat ; le serveur renvoie la valeur de référence). */
  function composeSidc(sidc, echelon) {
    var letter = echelonLetter(echelon);
    if (!letter || !sidc || sidc.length !== 15) return sidc || '';
    return sidc.slice(0, 11) + letter + sidc.slice(12);
  }
  function withHq(sidc, hq) {
    if (!sidc || sidc.length !== 15) return sidc;
    return sidc.slice(0, 10) + (hq ? 'A' : '-') + sidc.slice(11);
  }

  /* ------------------------------------------------------------------ cadres */
  function leaders(n) {
    var chef = null;
    if (n.commander && n.commander.label) {
      chef = { label: n.commander.label, grade: n.commander.grade || '', trigram: n.commander.trigram || '', userId: n.commander.userId || 0, billetId: n.commander.billetId || 0, confidential: n.commander.confidential || null };
    } else if ((n.commanderUserId || 0) > 0 && n.leader && n.leader !== '—') {
      chef = { label: n.leader, grade: n.commanderGrade || '', trigram: n.commanderTrigram || '', userId: n.commanderUserId, billetId: 0 };
    } else if (n.leaderConfidential && n.leader && n.leader !== '—') {
      chef = { label: n.leader, grade: '', userId: 0, billetId: 0, confidential: n.leaderConfidential };
    }
    var adj = n.deputy && n.deputy.label
      ? { label: n.deputy.label, grade: n.deputy.grade || '', trigram: n.deputy.trigram || '', userId: n.deputy.userId || 0, billetId: n.deputy.billetId || 0, confidential: n.deputy.confidential || null }
      : null;
    return { chef: chef, adj: adj };
  }
  function personHtml(p) {
    if (p && p.confidential) return confBadge(p.confidential, true);
    var g = String(p.grade || '').trim();
    var label = String(p.label || '');
    if (g && label.toUpperCase().indexOf(g.toUpperCase() + ' ') === 0) g = '';
    var tri = /^[A-Z]{3}$/.test(String(p.trigram || '')) ? String(p.trigram) : '';
    return (g ? '<em class="orb-grd">' + esc(g) + '</em>' : '') + '<span class="orb-pn">' + esc(label) + '</span>' +
      (tri ? '<small class="orb-tri" title="' + esc(t('trigram')) + '">' + tri + '</small>' : '');
  }
  function cardPosts(n) {
    return (n.billets || []).filter(function (b) { return !LEADER_KINDS[String(b.key_post_kind || '').toLowerCase()]; });
  }
  function leaderRow(kind, p, health) {
    var icon = '<svg class="orb-ico" aria-hidden="true"><use href="#orb-i-' + kind + '"></use></svg>';
    var lab = kind === 'chef' ? t('chef') : t('deputy');
    if (p) {
      return '<div class="orb-ldr orb-ldr--' + kind + '"' + (p.userId ? ' data-person="' + p.userId + '"' : '') + '>' + icon +
        '<span class="orb-ldr-k">' + esc(lab) + '</span><span class="orb-ldr-n">' + personHtml(p) + '</span></div>';
    }
    var warn = kind === 'chef' || health;
    return '<div class="orb-ldr orb-ldr--' + kind + ' is-empty' + (warn ? ' is-warn' : '') + '">' + icon +
      '<span class="orb-ldr-k">' + esc(lab) + '</span><span class="orb-ldr-n"><i>' +
      esc(kind === 'chef' ? t('chef_none') : t('deputy_none')) + '</i></span></div>';
  }

  /* ------------------------------------------------------------------ carte */
  function strengthOf(n) {
    var r = typeof n.readinessScore === 'number' ? n.readinessScore : null;
    var auth = n.strengthTheoretical || n.billetAuthorized || 0;
    var filled = n.strengthFilled != null ? n.strengthFilled : (n.billetFilled || 0);
    if (r === null && auth > 0) r = Math.round(filled / auth * 100);
    /* Effectif de l'unité et de tout ce qui lui est rattaché : la disponibilité est calculée sur cet ensemble,
       l'afficher à côté d'un effectif « direct » donnait « 0 pax » avec un pourcentage. */
    var pax = n.strength || 0;
    if (n.unitId > 0) descendants(n).forEach(function (c) { if (c.unitId > 0 && shown(c)) pax += c.strength || 0; });
    return { pax: pax, auth: auth, filled: filled, r: r };
  }
  function cardInner(n, opt) {
    opt = opt || {};
    if (n.isOrbatPlaceholder) {
      return '<div class="orb-nd-top"><span class="orb-sym">' + symHtml(n, 22) + '</span><div class="orb-nd-t"><b>' + esc(n.label) +
        '</b><span>' + esc(n.role || '') + '</span></div></div><div class="orb-nd-tags">' +
        (n.confidential ? confBadge(n.confidential, true) : '<span class="orb-pill">' + esc(t('out_of_scope')) + '</span>') +
        '</div><p class="orb-ph-txt">' + esc(n.mission || '') + '</p>';
    }
    var st = n.adminStatus || 'active';
    var lead = leaders(n);
    var health = n.keyPostsHealth || {};
    var s = strengthOf(n);
    var code = n.unitCode ? n.unitCode + (n.adminId ? ' · ' + n.adminId : '') : (n.role && n.role !== 'Unité' ? n.role : '—');
    var posts = cardPosts(n);
    var shownPosts = posts.length > 3 ? posts.slice(0, 2) : posts;
    var tags = '<span class="orb-pill"><i style="background:' + (STATUS_COLOR[st] || '#94a3b8') + '"></i>' + esc(n.adminStatusLabel || statusLabel(st)) + '</span>';
    if (n.confidential) {
      tags += confBadge(n.confidential, true);
    } else if (n.visibilityHintLabel || n.maskHintLabel) {
      tags += '<span class="orb-pill orb-pill--vis">' + esc(n.visibilityHintLabel || n.maskHintLabel) + '</span>';
    }
    if (n.echelon) tags += '<span class="orb-pill orb-pill--ech">' + esc(echelonLabel(n.echelon)) + '</span>';
    var aq = S.adq && n.unitId > 0 && !opt.preview ? S.adq[n.unitId] : null;
    if (aq) {
      tags += '<span class="orb-pill orb-pill--adq is-' + esc(aq.tone || 'info') + '" title="' + esc((aq.texts || []).join(' · ')) + '">' +
        esc(aq.label || '') + (aq.count > 1 ? ' · ' + aq.count : '') + '</span>';
    }
    var html = '<div class="orb-nd-top"><span class="orb-sym" aria-hidden="true">' + symHtml(n, 25) + '</span>' +
      '<div class="orb-nd-t"><b data-name>' + esc(n.label || '—') + '</b><span class="orb-code">' + esc(code) + '</span></div>' +
      (n.chartIconUrl ? '<img class="orb-nd-icon" alt="" src="' + esc(mediaSrc(n.chartIconUrl)) + '">' : '') +
      (opt.preview ? '' : '<button type="button" class="orb-nd-m" data-menu aria-label="' + esc(t('actions')) + '">⋯</button>') +
      '</div><div class="orb-nd-tags">' + tags + '</div>' +
      '<div class="orb-nd-cmd">' + leaderRow('chef', lead.chef, health.commanderVacant) + leaderRow('adj', lead.adj, health.deputyVacant) + '</div>' +
      '<ul class="orb-posts">' + shownPosts.map(function (b) {
        var h = (b.holders || [])[0];
        return '<li data-billet="' + b.id + '" class="' + (h ? '' : 'is-vacant') + '"><span>' + esc(b.function_label || b.title || b.code) +
          '</span><b>' + (h ? personHtml(h) : esc(t('vacant'))) + '</b></li>';
      }).join('') +
      (posts.length > 3 ? '<li class="orb-more">' + esc(tn(posts.length - 2, 'posts_more_one', 'posts_more_many')) + '</li>' : '') + '</ul>' +
      '<div class="orb-nd-f"><div class="orb-rd"><i style="width:' + (s.r == null ? 0 : s.r) + '%;background:' + readinessColor(s.r || 0) + '"></i></div>' +
      '<span><b>' + s.pax + '</b> ' + esc(t('pax')) + (s.auth ? ' · <b>' + s.filled + '</b>/' + s.auth : '') +
      (s.r != null ? ' · <b style="color:' + readinessColor(s.r) + '">' + s.r + ' %</b>' : '') + '</span></div>';
    var k = opt.preview ? [] : kids(n);
    if (k.length && !opt.flow) {
      var col = S.collapsed.has(n.id);
      var nDesc = descendants(n).filter(shown).length;
      html += '<button type="button" class="orb-nd-tg" data-tg>' + esc(col ? tn(nDesc, 'expand_one', 'expand_many') : '− ' + t('collapse')) + '</button>';
    }
    return html;
  }
  function cardStyle(n) {
    var c = /^#[0-9A-Fa-f]{6}$/.test(n.accentColor || '') ? n.accentColor : (STATUS_COLOR[n.adminStatus || 'active'] || '#94a3b8');
    return '--c:' + c;
  }
  function mediaSrc(path) { return path && path.charAt(0) === '/' ? B.base + path : path; }

  /* ------------------------------------------------------------------ disposition */
  function layout() {
    var pos = new Map();
    if (!S.root) return pos;
    var memo = new Map();
    function width(n) {
      if (memo.has(n.id)) return memo.get(n.id);
      var k = S.collapsed.has(n.id) ? [] : kids(n);
      var w = k.length ? Math.max(NW, k.reduce(function (s, c) { return s + width(c); }, 0) + GX * (k.length - 1)) : NW;
      memo.set(n.id, w);
      return w;
    }
    function place(n, x, y) {
      var W = width(n);
      pos.set(n.id, { x: Math.round(x + (W - NW) / 2), y: y });
      if (S.collapsed.has(n.id)) return;
      var k = kids(n);
      var total = k.reduce(function (s, c) { return s + width(c); }, 0) + GX * (k.length - 1);
      var cx = x + (W - total) / 2;
      k.forEach(function (c) { place(c, cx, y + NH + GY); cx += width(c) + GX; });
    }
    place(S.root, 0, 0);
    return pos;
  }

  /* ------------------------------------------------------------------ rendu */
  var world = $('#orb-world');
  var cv = $('#orb-cv');
  var edgesSvg = $('#orb-edges');
  var edgePath = $('#orb-edges-base');
  var edgeOn = $('#orb-edges-on');

  function render() {
    if (!S.root) { renderEmpty(); return; }
    S.pos = layout();
    var maxX = 0, maxY = 0;
    var seen = new Set();
    S.pos.forEach(function (p, key) {
      var n = node(key);
      seen.add(key);
      maxX = Math.max(maxX, p.x + NW);
      maxY = Math.max(maxY, p.y + NH);
      var el = cards.get(key);
      if (!el) {
        el = document.createElement('div');
        el.className = 'orb-nd';
        el.dataset.key = key;
        el.setAttribute('role', 'button');
        el.tabIndex = -1;
        world.appendChild(el);
        cards.set(key, el);
      }
      var sig = el._ver === S.ver ? cardSig.get(key) : sigOf(n) + S.ms + S.collapsed.has(key) + kids(n).length + descendants(n).filter(shown).length;
      el._ver = S.ver;
      if (cardSig.get(key) !== sig) {
        el.innerHTML = cardInner(n);
        el.setAttribute('style', cardStyle(n));
        el.setAttribute('aria-label', n.label || '');
        el.classList.toggle('is-ph', !!n.isOrbatPlaceholder);
        el.classList.toggle('is-virtual', !(n.unitId > 0) && !n.isOrbatPlaceholder);
        cardSig.set(key, sig);
        el._px = null;
      }
      if (el._px !== p.x + ',' + p.y) {
        el.style.transform = 'translate(' + p.x + 'px,' + p.y + 'px)';
        el._px = p.x + ',' + p.y;
      }
    });
    cards.forEach(function (el, key) {
      if (!seen.has(key)) { el.remove(); cards.delete(key); cardSig.delete(key); }
    });
    edgesSvg.setAttribute('width', String(maxX));
    edgesSvg.setAttribute('height', String(maxY + 20));
    renderEdges();
    applyStates();
    renderStats();
    renderFilters();
    if (S.view === 'directory' && S.dirtyViews) renderDirectory();
    if (S.view === 'sheet' && S.dirtyViews) renderSheet();
    if (S.view === 'tree') S.dirtyViews = true;
    renderDrawer();
  }

  function renderEdges() {
    var chain = new Set(S.sel ? ancestors(S.sel).concat([S.sel]) : []);
    var dBase = '', dOn = '';
    S.pos.forEach(function (p, key) {
      var pk = S.parent.get(key);
      var a = pk ? S.pos.get(pk) : null;
      if (!a) return;
      var x1 = a.x + NW / 2, y1 = a.y + NH + 13, x2 = p.x + NW / 2, y2 = p.y;
      var ym = y1 + (y2 - y1) / 2, r = Math.min(12, Math.abs(x2 - x1) / 2), dx = x2 > x1 ? 1 : -1;
      var d = Math.abs(x1 - x2) < 1
        ? 'M' + x1 + ' ' + y1 + 'V' + y2
        : 'M' + x1 + ' ' + y1 + 'V' + (ym - r) + 'Q' + x1 + ' ' + ym + ' ' + (x1 + dx * r) + ' ' + ym + 'H' + (x2 - dx * r) + 'Q' + x2 + ' ' + ym + ' ' + x2 + ' ' + (ym + r) + 'V' + y2;
      if (chain.has(key)) dOn += d; else dBase += d;
    });
    edgePath.setAttribute('d', dBase);
    edgeOn.setAttribute('d', dOn);
  }

  function matches(n) {
    if (!S.q) return true;
    var q = S.q.toLowerCase();
    var lead = leaders(n);
    var hay = [n.label, n.role, n.unitCode, n.adminId, n.adminStatusLabel, n.mission, lead.chef && lead.chef.label, lead.adj && lead.adj.label,
      echelonLabel(n.echelon)].concat((n.billets || []).map(function (b) {
      return [b.title, b.function_label].concat((b.holders || []).map(function (h) { return h.label; })).join(' ');
    })).concat((n.members || []).map(function (m) { return m.label; })).join(' ').toLowerCase();
    return hay.indexOf(q) !== -1;
  }
  function applyStates() {
    var hitKey = S.hitIdx >= 0 ? S.hits[S.hitIdx] : null;
    cards.forEach(function (el, key) {
      var n = node(key);
      el.classList.toggle('sel', key === S.sel);
      el.classList.toggle('dim', !!S.q && !matches(n));
      el.classList.toggle('hit', key === hitKey);
      el.tabIndex = key === S.sel ? 0 : -1;
    });
  }

  function visibleUnits() {
    var out = [];
    if (!S.root) return out;
    (function walk(n) {
      if (n.unitId > 0 && !n.isOrbatPlaceholder) out.push(n);
      kids(n).forEach(walk);
    })(S.root);
    return out;
  }
  function renderStats() {
    var units = visibleUnits();
    var pax = 0, active = 0, rs = 0, rc = 0, posts = 0, vacant = 0;
    units.forEach(function (n) {
      pax += n.strength || 0;
      if (n.billetAuthorized > 0) { posts += n.billetAuthorized; vacant += n.billetVacant || 0; }
      if ((n.adminStatus || 'active') === 'active') active++;
      if (typeof n.localReadinessScore === 'number') {
        var w = (n.members || []).length || 1;
        rs += n.localReadinessScore * w; rc += w;
      }
    });
    var r = rc > 0 ? Math.round(rs / rc) : (typeof S.root.readinessScore === 'number' ? S.root.readinessScore : null);
    var el = $('#orb-stats');
    if (!el) return;
    el.innerHTML = '<div><span class="orb-k">' + esc(t('stat_units')) + '</span><b>' + units.length + '</b></div>' +
      '<div><span class="orb-k">' + esc(t('stat_strength')) + '</span><b>' + pax + '</b></div>' +
      '<div><span class="orb-k">' + esc(t('stat_active')) + '</span><b>' + active + '</b></div>' +
      '<div><span class="orb-k">' + esc(t('stat_watch')) + '</span><b class="orb-warn">' + (units.length - active) + '</b></div>' +
      (posts > 0 ? '<div title="' + esc(t('stat_vacant_hint', { n: vacant, total: posts })) + '"><span class="orb-k">' + esc(t('stat_vacant')) + '</span><b' + (vacant > 0 ? ' class="orb-warn"' : '') + '>' + vacant + '</b></div>' : '') +
      '<div><span class="orb-k">' + esc(t('stat_readiness')) + '</span><b style="color:' + (r == null ? 'inherit' : readinessColor(r)) + '">' + (r == null ? '—' : r + ' %') + '</b></div>';
  }
  function renderFilters() {
    var box = $('#orb-fc');
    if (!box) return;
    var counts = {};
    if (S.root) (function walk(n) { if (n.unitId > 0) counts[n.adminStatus || 'active'] = (counts[n.adminStatus || 'active'] || 0) + 1; (n.children || []).forEach(walk); })(S.root);
    box.innerHTML = STATUS_IDS.map(function (id) {
      var on = S.statuses.has(id);
      if (!counts[id] && !on && B.canManage) return '';
      return '<button type="button" class="' + (on ? 'on' : '') + '" data-st="' + id + '" aria-pressed="' + on + '"><i style="background:' + STATUS_COLOR[id] + '"></i>' +
        esc(statusLabel(id)) + (B.canManage || counts[id] ? ' <span>' + (counts[id] || 0) + '</span>' : '') + '</button>';
    }).join('');
  }
  function renderEmpty() {
    world.innerHTML = '';
    cards.clear();
    cardSig.clear();
    var e = $('#orb-empty');
    if (e) e.hidden = false;
    var st = $('#orb-stats');
    if (st) st.innerHTML = '';
  }

  /* ------------------------------------------------------------------ vue : déplacement et zoom */
  function applyT() {
    world.style.transform = 'translate(' + S.x + 'px,' + S.y + 'px) scale(' + S.z + ')';
    cv.style.backgroundPosition = S.x + 'px ' + S.y + 'px';
    cv.style.backgroundSize = (24 * S.z) + 'px ' + (24 * S.z) + 'px';
    world.classList.toggle('lod-mid', S.z < 0.45);
    world.classList.toggle('lod-low', S.z < 0.3);
    var zv = $('#orb-zv');
    if (zv) zv.textContent = Math.round(S.z * 100) + ' %';
  }
  var tPending = false;
  function scheduleT() {
    if (tPending) return;
    tPending = true;
    requestAnimationFrame(function () { tPending = false; applyT(); });
  }
  function availW() { return cv.clientWidth - (S.sel && $('#orb-dr').classList.contains('on') && cv.clientWidth > 900 ? 424 : 0); }
  function animate() {
    world.classList.add('anim');
    clearTimeout(animate.t);
    animate.t = setTimeout(function () { world.classList.remove('anim'); }, 420);
  }
  function fit(anim) {
    if (!S.pos.size) return;
    var x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
    S.pos.forEach(function (p) { x0 = Math.min(x0, p.x); y0 = Math.min(y0, p.y); x1 = Math.max(x1, p.x + NW); y1 = Math.max(y1, p.y + NH + 14); });
    var aw = Math.max(200, availW() - 64), ah = Math.max(200, cv.clientHeight - 96);
    S.z = Math.max(0.15, Math.min(1, aw / (x1 - x0), ah / (y1 - y0)));
    S.x = 32 + (aw - (x1 - x0) * S.z) / 2 - x0 * S.z;
    S.y = 28 + Math.max(0, (ah - (y1 - y0) * S.z) / 2) - y0 * S.z;
    if (anim !== false) animate();
    applyT();
  }
  function centerOn(key, anim) {
    var p = S.pos.get(key);
    if (!p) return;
    S.z = Math.max(S.z, 0.7);
    S.x = availW() / 2 - (p.x + NW / 2) * S.z;
    S.y = cv.clientHeight / 2.4 - (p.y + NH / 2) * S.z;
    if (anim !== false) animate();
    applyT();
  }
  function ensureVisible(key) {
    var p = S.pos.get(key);
    if (!p) return;
    var sx = S.x + p.x * S.z, sy = S.y + p.y * S.z;
    if (sx < 0 || sx + NW * S.z > availW() || sy < 0 || sy + NH * S.z > cv.clientHeight) centerOn(key);
  }
  function zoomAt(f, cx, cy) {
    var nz = Math.max(0.15, Math.min(1.6, S.z * f));
    S.x = cx - (cx - S.x) * (nz / S.z);
    S.y = cy - (cy - S.y) * (nz / S.z);
    S.z = nz;
    scheduleT();
  }
  function expandTo(key) {
    var changed = false;
    ancestors(key).forEach(function (a) { if (S.collapsed.delete(a)) changed = true; });
    if (changed) S.ver++;
    return changed;
  }
  function select(key, center) {
    if (key && !S.byKey.has(key)) return;
    S.sel = key;
    if ((key && expandTo(key)) || !S.pos.has(key)) render();
    else { renderEdges(); applyStates(); renderDrawer(); }
    if (key && S.view === 'tree') { if (center) centerOn(key); else ensureVisible(key); }
    var el = key ? cards.get(key) : null;
    if (el && document.activeElement !== el && !isTyping()) el.focus({ preventScroll: true });
  }
  function isTyping() {
    var a = document.activeElement;
    return !!a && (/^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName) || a.isContentEditable);
  }

  /* ------------------------------------------------------------------ fiche (tiroir) */
  function renderDrawer() {
    var dr = $('#orb-dr');
    var n = S.sel ? node(S.sel) : null;
    dr.classList.toggle('on', !!n);
    dr.setAttribute('aria-hidden', n ? 'false' : 'true');
    if (!n) { dr.innerHTML = ''; dr._sig = null; return; }
    var sig = sigOf(n) + S.ms + S.ver;
    if (dr._sig === sig) return;
    dr._sig = sig;
    var lead = leaders(n);
    var s = strengthOf(n);
    var st = n.adminStatus || 'active';
    var crumbs = ancestors(n.id).map(function (k) {
      var a = node(k);
      return '<a href="#" data-go="' + esc(k) + '">' + esc(a.unitCode || a.role && a.role !== 'Unité' && a.role || a.label) + '</a>';
    }).join('<span>›</span>') || '<span>' + esc(t('root_unit')) + '</span>';
    var sigs = [];
    if (isUnit(n) && !lead.chef) sigs.push(['w', t('sig_no_chef')]);
    (n.capacitySignals || []).forEach(function (c) { sigs.push([c.severity === 'high' ? 'x' : (c.severity === 'low' ? 'i' : 'w'), c.message || c.code]); });
    if (s.r != null && s.r < 70 && isUnit(n)) sigs.push(['x', t('sig_low_readiness')]);
    if (n.adminStatusNote) sigs.push(['i', n.adminStatusNote]);
    var children = kids(n);
    var html = '<div class="orb-dh" style="' + cardStyle(n) + '"><div class="orb-dh-top"><div class="orb-crumbs">' + crumbs + '</div>' +
      '<button type="button" class="orb-ib" data-close title="' + esc(t('drawer_close')) + '" aria-label="' + esc(t('drawer_close')) + '">×</button></div>' +
      '<div class="orb-dh-id"><span class="orb-sym orb-sym--lg">' + symHtml(n, 34) + '</span><div><div class="orb-k">' +
      esc([structLabel(n.structType), n.echelon ? echelonLabel(n.echelon) : ''].filter(Boolean).join(' · ')) + (n.unitCode ? ' · <span class="orb-mono">' + esc(n.unitCode) + '</span>' : '') +
      (n.adminId ? ' · <span class="orb-mono">' + esc(n.adminId) + '</span>' : '') + '</div><h2>' + esc(n.label) + '</h2>' +
      '<div class="orb-dh-tags"><span class="orb-pill"><i style="background:' + (STATUS_COLOR[st] || '#94a3b8') + '"></i>' + esc(n.adminStatusLabel || statusLabel(st)) + '</span>' +
      (n.confidential ? confBadge(n.confidential) : (n.staffVisibilityActive ? '<span class="orb-pill orb-pill--vis">' + esc(n.visibilityHintLabel || '') + '</span>' : '')) + '</div></div></div>' +
      (canEdit(n) ? '<div class="orb-dh-acts"><button type="button" class="orb-hb1" data-edit="identity">' + esc(t('edit_unit')) + '</button>' +
        '<button type="button" class="orb-hb2" data-add>' + esc(t('add_sub')) + '</button>' +
        '<button type="button" class="orb-hb2 sq" data-menu-dr title="' + esc(t('more_actions')) + '" aria-label="' + esc(t('more_actions')) + '">⋯</button></div>' : '') + '</div>';
    html += '<div class="orb-db">';
    if (n.viewerNamesRedacted || n.isOrbatPlaceholder) {
      html += '<p class="orb-sg i">' + esc(n.confidential && n.confidential.hint ? n.confidential.hint : t(n.isOrbatPlaceholder ? 'placeholder' : 'redacted')) + '</p>';
    }
    html += '<div class="orb-kg"><div><span class="orb-k">' + esc(t('kpi_strength')) + '</span><b>' + s.pax + '<small>' + esc(t('pax')) + '</small></b>' +
      (s.auth ? '<em>' + esc(t('kpi_strength_sub', { n: s.filled, m: s.auth })) + '</em>' : '') + '</div>' +
      '<div><span class="orb-k">' + esc(t('kpi_readiness')) + '</span><b style="color:' + (s.r == null ? 'inherit' : readinessColor(s.r)) + '">' + (s.r == null ? '—' : s.r + ' %') + '</b><em>' +
      esc(s.r == null ? t('not_computed') : t('kpi_readiness_sub')) + '</em></div>' +
      '<div class="orb-kg-ldr">' + leaderRow('chef', lead.chef, false) + '</div><div class="orb-kg-ldr">' + leaderRow('adj', lead.adj, false) + '</div></div>';
    if (sigs.length) html += '<section class="orb-dsec"><h4>' + esc(t('sec_signals')) + '</h4><div class="orb-sigs">' + sigs.map(function (x) { return '<div class="orb-sg ' + x[0] + '">' + esc(x[1]) + '</div>'; }).join('') + '</div></section>';
    html += '<section class="orb-dsec"><h4>' + esc(t('sec_mission')) + '</h4>' + (n.mission && n.mission !== '—'
      ? '<p>' + esc(n.mission) + '</p>' : (canEdit(n) ? '<button type="button" class="orb-add" data-edit="public">' + esc(t('define_mission')) + '</button>' : '<p class="orb-muted">' + esc(t('no_mission')) + '</p>')) +
      (n.motto ? '<p class="orb-motto">« ' + esc(n.motto) + ' »</p>' : '') + '</section>';
    if ((n.orbatDetails || '').trim() || n.chartImageUrl) {
      html += '<section class="orb-dsec"><h4>' + esc(t('sec_details')) + '</h4>' + (n.orbatDetails ? '<p class="orb-pre">' + esc(n.orbatDetails) + '</p>' : '') +
        (n.chartImageUrl ? '<img class="orb-dimg" alt="" src="' + esc(mediaSrc(n.chartImageUrl)) + '">' : '') + '</section>';
    }
    if (isUnit(n)) {
      var billets = n.billets || [];
      var filled = billets.filter(function (b) { return (b.holders || []).length > 0; }).length;
      html += '<section class="orb-dsec"><h4>' + esc(t('sec_posts')) + ' <span>' + filled + '/' + billets.length + '</span></h4>' +
        (billets.length ? billets.map(postRowHtml).join('') : '<p class="orb-muted">' + esc(t('no_posts')) + '</p>') +
        (canEdit(n) ? '<button type="button" class="orb-add" data-post-new>' + esc(t('add_post')) + '</button>' : '') + '</section>';
      var cmds = n.derivedCommand || [];
      if (cmds.length) {
        html += '<section class="orb-dsec"><h4>' + esc(t('sec_command')) + '</h4>' + cmds.map(function (c) {
          return '<div class="orb-post"><span>' + esc(c.title || t('key_post')) + '</span><b>' + esc(c.label || '') + (c.occupancy_label ? ' <small>(' + esc(c.occupancy_label) + ')</small>' : '') + '</b></div>';
        }).join('') + '</section>';
      }
    }
    html += '<section class="orb-dsec"><h4>' + esc(t('sec_children')) + ' <span>' + children.length + '</span></h4>' + (children.length ? children.map(function (c) {
      var cs = strengthOf(c);
      return '<button type="button" class="orb-sub" data-go="' + esc(c.id) + '"><span class="orb-sym orb-sym--sm">' + symHtml(c, 12, true) + '</span><span><b>' + esc(c.label) + '</b><small>' +
        esc(c.unitCode || '') + ' · ' + cs.pax + ' ' + esc(t('pax')) + '</small></span><em style="color:' + (cs.r == null ? 'inherit' : readinessColor(cs.r)) + '">' + (cs.r == null ? '—' : cs.r + ' %') + '</em></button>';
    }).join('') : '<p class="orb-muted">' + esc(t('no_children')) + '</p>') +
      (canEdit(n) ? '<button type="button" class="orb-add" data-add>' + esc(t('add_child')) + '</button>' : '') + '</section>';
    if (isUnit(n)) {
      var mems = n.members || [];
      html += '<section class="orb-dsec"><h4>' + esc(t('sec_members')) + ' <span>' + mems.length + '</span></h4>' + (mems.length ? '<div class="orb-mems">' + mems.map(function (m) {
        var inner = personHtml(m) + (typeof m.readiness === 'number' ? '<em style="color:' + readinessColor(m.readiness) + '">' + m.readiness + ' %</em>' : '');
        return m.user_id > 0 ? '<a href="' + esc(B.personnelBase + encodeURIComponent(String(m.user_id))) + '" data-person="' + m.user_id + '">' + inner + '</a>' : '<span>' + inner + '</span>';
      }).join('') + '</div>' : '<p class="orb-muted">' + esc(t('no_members')) + '</p>') + '</section>';
      if (B.canManage) html += '<a class="orb-full" href="' + esc(B.fullSheetBase + n.unitId) + '">' + esc(t('full_sheet')) + '</a>';
    }
    html += '</div>';
    dr.innerHTML = html;
  }
  function postRowHtml(b) {
    var seat = b.seat_status || ((b.holders || []).length ? 'filled' : 'vacant');
    var kind = String(b.key_post_kind || '').toLowerCase();
    var icon = kind === 'commander' ? 'chef' : (kind === 'deputy' ? 'adj' : '');
    var holders = (b.holders || []).map(function (h) {
      return '<span class="orb-holder" data-holder="' + h.id + '" data-person="' + (h.user_id || 0) + '">' + personHtml(h) +
        (h.occupancy_type && h.occupancy_type !== 'primary' ? ' <small>' + esc(h.occupancy_label || '') + '</small>' : '') + '</span>';
    }).join('');
    return '<div class="orb-post orb-post--' + seat + '" data-billet="' + b.id + '" tabindex="0">' +
      '<span class="orb-post-t">' + (icon ? '<svg class="orb-ico orb-ico--' + icon + '" aria-hidden="true"><use href="#orb-i-' + icon + '"></use></svg>' : '') +
      '<b>' + esc(b.title || b.code || '') + '</b>' + (b.function_label ? '<small>' + esc(b.function_label) + '</small>' : '') +
      (b.org_callsign ? '<small class="orb-mono">' + esc(b.org_callsign) + '</small>' : '') + '</span>' +
      '<span class="orb-post-h">' + (holders || '<i>' + esc(t('vacant')) + '</i>') +
      ((b.authorized || 1) > 1 ? '<small>' + (b.filled || 0) + '/' + b.authorized + '</small>' : '') + '</span></div>';
  }

  /* ------------------------------------------------------------------ annuaire et tableur */
  function renderDirectory() {
    var box = $('#orb-dir');
    if (!box || !S.root) return;
    S.dirtyViews = false;
    var h = '';
    (function sec(n) {
      var k = kids(n);
      if (!k.length) return;
      var path = ancestors(n.id).concat([n.id]).map(function (x) { var a = node(x); return esc(a.unitCode || a.label); }).join(' › ');
      h += '<section class="orb-dsg"><header><span class="orb-k">' + path + '</span><b>' + esc(tn(k.length, 'expand_one', 'expand_many').replace('+ ', '')) + '</b></header><div class="orb-dgrid">' +
        k.map(function (c) { return '<div class="orb-nd flow" data-key="' + esc(c.id) + '" role="button" tabindex="-1" style="' + cardStyle(c) + '">' + cardInner(c, { flow: true }) + '</div>'; }).join('') + '</div></section>';
      k.forEach(sec);
    })(S.root);
    var root = '<section class="orb-dsg"><header><span class="orb-k">' + esc(t('dir_command')) + '</span></header><div class="orb-dgrid"><div class="orb-nd flow" data-key="' +
      esc(S.root.id) + '" role="button" tabindex="-1" style="' + cardStyle(S.root) + '">' + cardInner(S.root, { flow: true }) + '</div></div></section>';
    box.innerHTML = root + h || '<p class="orb-muted">' + esc(t('no_result')) + '</p>';
    $$('.orb-nd', box).forEach(function (el) { el.classList.toggle('sel', el.dataset.key === S.sel); el.classList.toggle('dim', !!S.q && !matches(node(el.dataset.key))); });
  }
  function selectHtml(field, list, cur) {
    return '<select data-sheet="' + field + '">' + list.map(function (o) {
      return '<option value="' + esc(o.id) + '"' + (String(o.id) === String(cur) ? ' selected' : '') + '>' + esc(o.label) + '</option>';
    }).join('') + '</select>';
  }
  function renderSheet() {
    var box = $('#orb-sheet');
    if (!box || !S.root) return;
    S.dirtyViews = false;
    var units = visibleUnits().filter(function (n) { return !S.q || matches(n); });
    var o = S.opts || {};
    var structs = o.structTypes || B.structTypes || [];
    var statuses = o.adminStatuses || STATUS_IDS.map(function (id) { return { id: id, label: statusLabel(id) }; });
    var vis = o.visibilityLevels || [];
    var ech = [{ id: '', label: t('f_echelon_none') }].concat((B.symbols && B.symbols.echelons) || []);
    var edit = B.canManage;
    box.innerHTML = '<table class="orb-table"><thead><tr><th>' + [t('sheet_name'), t('sheet_code'), t('sheet_type'), t('sheet_echelon'), t('sheet_status'), t('sheet_visibility'),
      t('sheet_strength'), t('sheet_chef'), t('sheet_deputy'), t('sheet_mission')].map(esc).join('</th><th>') + '</th></tr></thead><tbody>' + units.map(function (n) {
      var lead = leaders(n);
      return '<tr data-key="' + esc(n.id) + '"><td><button type="button" class="orb-link" data-go="' + esc(n.id) + '"><span class="orb-sym orb-sym--sm">' + symHtml(n, 12) + '</span>' + esc(n.label) + '</button></td>' +
        '<td class="orb-mono orb-nowrap">' + esc(n.unitCode || '') + (n.adminId ? '<br><small>' + esc(n.adminId) + '</small>' : '') + '</td>' +
        '<td>' + (edit ? selectHtml('struct_type', structs, n.structType) : esc(structLabel(n.structType) || '—')) + '</td>' +
        '<td>' + (edit && S.opts && S.opts.capabilities && S.opts.capabilities.military_symbol ? selectHtml('echelon', ech, n.echelonAuto ? '' : n.echelon) : esc(echelonLabel(n.echelon))) + '</td>' +
        '<td>' + (edit ? selectHtml('admin_status', statuses, n.adminStatus || 'active') : esc(n.adminStatusLabel || '')) + '</td>' +
        '<td>' + (edit && vis.length ? selectHtml('visibility_level', vis, n.visibilityLevel || 'normal') : (n.confidential ? confBadge(n.confidential, true) : esc(n.visibilityHintLabel || ''))) + '</td>' +
        '<td>' + (n.strength || 0) + '</td><td>' + (lead.chef ? personHtml(lead.chef) : '—') + '</td><td>' + (lead.adj ? personHtml(lead.adj) : '—') + '</td>' +
        '<td class="orb-cut" title="' + esc(n.mission || '') + '">' + esc(n.mission && n.mission !== '—' ? n.mission : '') + '</td></tr>';
    }).join('') + '</tbody></table>' + (units.length ? '' : '<p class="orb-muted">' + esc(t('no_result')) + '</p>');
  }
  function setView(v) {
    S.view = v === 'directory' || v === 'sheet' ? v : 'tree';
    rootEl.dataset.view = S.view;
    $$('#orb-vw button').forEach(function (b) { var on = b.dataset.v === S.view; b.classList.toggle('on', on); b.setAttribute('aria-pressed', String(on)); });
    S.dirtyViews = true;
    if (S.view === 'directory') renderDirectory();
    if (S.view === 'sheet') renderSheet();
    if (S.view === 'tree') fit(false);
  }

  /* ------------------------------------------------------------------ réseau */
  function post(url, fields) {
    var fd = new FormData();
    fd.append('_csrf_token', B.csrf);
    Object.keys(fields || {}).forEach(function (k) {
      var v = fields[k];
      if (v === undefined || v === null) return;
      if (v instanceof Blob) fd.append(k, v); else fd.append(k, String(v));
    });
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (j) {
          if (!r.ok || !j || !j.success) {
            var e = new Error((j && j.message) || t('t_error'));
            e.data = j || {};
            throw e;
          }
          return j;
        });
      }, function () { throw new Error(t('t_offline')); });
  }
  function rosterUrl() {
    var params = [];
    var all = B.canManage && !S.previewAs;
    params.push('admin_status=' + encodeURIComponent((all ? STATUS_IDS : Array.from(S.statuses)).join(',')));
    if (S.previewAs) params.push('preview_as=' + encodeURIComponent(S.previewAs));
    return api('/api/orbat/roster') + '?' + params.join('&');
  }
  /* quiet : rafraîchissement de fond, sans message si le réseau ou la session lâche. */
  function reloadRoster(quiet) {
    return fetch(rosterUrl(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (j) {
        if (!j || !j.success) throw new Error('roster');
        if (j.roster) applyServer(j.roster);
      })
      .catch(function () { if (!quiet) toast(t('t_reload_error'), null, true); });
  }
  function applyServer(root) {
    var snap = JSON.stringify(root);
    if (snap === S.lastSnap) return;
    S.lastSnap = snap;
    setRoster(root);
    render();
    refreshOpenModal();
    loadAdequacy();
  }
  /* Adéquation de l’organisation (gestionnaires) : pastille par unité, textes déjà traduits par le serveur. */
  function loadAdequacy() {
    if (!B.canManage || S.previewAs) { S.adq = null; return; }
    fetch(api('/api/orbat/adequation'), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j || !j.success) return;
        var next = j.units || {};
        if (JSON.stringify(next) === JSON.stringify(S.adq || {})) return;
        S.adq = next;
        if (S.root) render();
      })
      .catch(function () { /* pastilles facultatives */ });
  }
  function ensureOptions() {
    if (S.opts || !B.canManage) return Promise.resolve(S.opts);
    if (ensureOptions.p) return ensureOptions.p;
    ensureOptions.p = fetch(api('/api/orbat/structure-options'), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (j && j.success) { S.opts = j; if (j.symbols) B.symbols = j.symbols; S.dirtyViews = true; } return S.opts; })
      .catch(function () { ensureOptions.p = null; return null; });
    return ensureOptions.p;
  }

  /**
   * Modification optimiste : `apply` change l’arbre local tout de suite, la requête suit ; si le serveur refuse,
   * l’arbre est restauré tel qu’avant. Le roster renvoyé par le serveur fait foi (appliqué si aucune requête plus
   * récente n’est partie entre-temps).
   */
  function mutate(o) {
    var snapshot = S.root ? clone(S.root) : null;
    var seq = ++S.seq;
    S.pending++;
    if (o.apply && S.root) {
      try { o.apply(); } catch (e) { /* aperçu local seulement */ }
      setRoster(S.root);
      render();
    }
    return post(o.url, o.fields).then(function (j) {
      S.pending--;
      if (j.roster && seq === S.seq) {
        if (S.previewAs) reloadRoster(); else applyServer(j.roster);
      }
      if (o.undo) { S.undo.push(o.undo); if (S.undo.length > 50) S.undo.shift(); S.redo = []; }
      if (o.ok) o.ok(j);
      if (o.toast) toast(o.toast, o.undo ? function () { undo(); } : null);
      return j;
    }, function (err) {
      S.pending--;
      if (snapshot && seq === S.seq) { setRoster(snapshot); render(); refreshOpenModal(); }
      toast(err.message || t('t_error'), null, true);
      if (o.fail) o.fail(err);
      throw err;
    });
  }

  /* Correspondance champ de l’API ↔ champ du roster, pour l’aperçu local et l’annulation. */
  var FIELD_MAP = {
    name: 'label', code: 'role', struct_type: 'structType', orbat_type: 'type', admin_status: 'adminStatus',
    admin_status_note: 'adminStatusNote', visibility_level: 'visibilityLevel', public_blurb: 'mission', motto: 'motto',
    accent_color: 'accentColor', orbat_details: 'orbatDetails', commander_user_id: 'commanderUserId',
    show_on_public_page: 'showOnPublicPage', public_founded_on: 'publicFoundedOn', public_custom_date: 'publicCustomDate',
    public_custom_date_label: 'publicCustomDateLabel'
  };
  function apiValue(n, field) {
    // Nom affiché traduit (libellé généré) : on édite le nom enregistré.
    if (field === 'name') return n.labelSource != null ? String(n.labelSource) : (n.label == null ? '' : String(n.label));
    if (field === 'sidc') return n.sidcAuto ? '' : n.sidc;
    if (field === 'echelon') return n.echelonAuto ? '' : n.echelon;
    if (field === 'code') return n.role && n.role !== 'Unité' ? n.role : '';
    if (field === 'public_blurb') return n.mission && n.mission !== '—' ? n.mission : '';
    if (field === 'show_on_public_page') return n.showOnPublicPage === false ? '0' : '1';
    if (field === 'commander_user_id') return n.commanderUserId > 0 ? String(n.commanderUserId) : '';
    var v = n[FIELD_MAP[field]];
    return v == null ? '' : String(v);
  }
  function applyLocal(n, fields) {
    Object.keys(fields).forEach(function (f) {
      var v = fields[f];
      if (f === 'sidc') { n.sidcAuto = v === ''; if (v) n.sidc = v; n.symbolSidc = composeSidc(n.sidc, n.echelon); return; }
      if (f === 'echelon') { n.echelonAuto = v === ''; if (v) n.echelon = v; n.symbolSidc = composeSidc(n.sidc, n.echelon); return; }
      if (f === 'name') { n.label = v; delete n.labelSource; return; }
      if (f === 'code') { n.role = v || 'Unité'; return; }
      if (f === 'public_blurb') { n.mission = v || '—'; return; }
      if (f === 'show_on_public_page') { n.showOnPublicPage = v === '1'; return; }
      if (f === 'commander_user_id') {
        n.commanderUserId = parseInt(v, 10) || 0;
        var c = (B.commanders || []).filter(function (x) { return String(x.id) === String(v); })[0];
        n.leader = c ? c.label : '—';
        n.commanderGrade = '';
        if (!n.commander || !n.commander.billetId) n.commander = null;
        return;
      }
      if (f === 'admin_status') { n.adminStatus = v; n.adminStatusLabel = statusLabel(v); return; }
      if (FIELD_MAP[f]) n[FIELD_MAP[f]] = v;
    });
  }
  function patchUnit(unitId, fields, opt) {
    opt = opt || {};
    var n = nodeOfUnit(unitId);
    if (!n) return Promise.reject(new Error(t('t_error')));
    var before = {};
    Object.keys(fields).forEach(function (f) { before[f] = apiValue(n, f); });
    var body = Object.assign({ unit_id: unitId }, fields);
    if (fields.struct_type !== undefined && fields.name === undefined) body.name = apiValue(n, 'name');
    return mutate({
      url: api('/api/orbat/unit'),
      fields: body,
      apply: function () { var x = nodeOfUnit(unitId); if (x) applyLocal(x, fields); },
      toast: opt.silent ? null : (opt.toast || t('t_saved')),
      undo: opt.noUndo ? null : {
        run: function () { return patchUnit(unitId, before, { noUndo: true, toast: t('t_undone') }); },
        redo: function () { return patchUnit(unitId, fields, { noUndo: true, toast: t('t_redone') }); }
      }
    });
  }
  function moveUnit(unitId, parentUnitId, opt) {
    opt = opt || {};
    var n = nodeOfUnit(unitId);
    if (!n) return Promise.reject(new Error(t('t_error')));
    var oldParent = parentNode(n);
    var oldParentId = oldParent && oldParent.unitId > 0 ? oldParent.unitId : 0;
    var target = parentUnitId > 0 ? nodeOfUnit(parentUnitId) : null;
    if (target && (target.id === n.id || descendants(n).some(function (d) { return d.id === target.id; }))) {
      toast(t('t_cycle'), null, true);
      return Promise.reject(new Error(t('t_cycle')));
    }
    return mutate({
      url: api('/api/orbat/structure'),
      fields: { action: 'move', unit_id: unitId, parent_id: parentUnitId > 0 ? parentUnitId : '' },
      apply: function () {
        var x = nodeOfUnit(unitId), from = parentNode(x), to = parentUnitId > 0 ? nodeOfUnit(parentUnitId) : S.root;
        if (!x || !from || !to) return;
        from.children = (from.children || []).filter(function (c) { return c.id !== x.id; });
        to.children = (to.children || []).concat([x]);
        S.collapsed.delete(to.id);
      },
      toast: opt.noUndo ? opt.toast : t('t_moved', { name: n.label, parent: target ? target.label : t('f_parent_root') }),
      undo: opt.noUndo ? null : {
        run: function () { return moveUnit(unitId, oldParentId, { noUndo: true, toast: t('t_undone') }); },
        redo: function () { return moveUnit(unitId, parentUnitId, { noUndo: true, toast: t('t_redone') }); }
      }
    });
  }
  function structure(action, fields, o) {
    o = o || {};
    return mutate({ url: api('/api/orbat/structure'), fields: Object.assign({ action: action }, fields), apply: o.apply, toast: o.toast, ok: o.ok });
  }
  function undo() {
    var u = S.undo.pop();
    if (!u) { toast(t('t_nothing_undo')); return; }
    S.redo.push(u);
    u.run().catch(function () {});
  }
  function redo() {
    var u = S.redo.pop();
    if (!u) { toast(t('t_nothing_redo')); return; }
    S.undo.push(u);
    u.redo().catch(function () {});
  }

  /* ------------------------------------------------------------------ notifications */
  function toast(msg, action, isErr) {
    var el = $('#orb-toast');
    if (!el) return;
    el.innerHTML = '<span>' + esc(msg) + '</span>' + (action ? '<button type="button">' + esc(t('t_undo')) + '</button>' : '');
    el.classList.toggle('err', !!isErr);
    el.classList.add('on');
    var b = $('button', el);
    if (b) b.onclick = function () { el.classList.remove('on'); action(); };
    clearTimeout(toast.t);
    toast.t = setTimeout(function () { el.classList.remove('on'); }, isErr ? 6000 : 4200);
  }

  /* ------------------------------------------------------------------ menu contextuel */
  var cm = $('#orb-cm');
  var cmFocusBack = null;
  function showMenu(items, x, y) {
    cmFocusBack = document.activeElement;
    cm.innerHTML = items.map(function (it, i) {
      if (it === '-') return '<hr>';
      return '<button type="button" role="menuitem" data-i="' + i + '"' + (it.disabled ? ' disabled' : '') + (it.danger ? ' class="dg"' : '') + '>' +
        esc(it.label) + (it.kbd ? '<kbd>' + esc(it.kbd) + '</kbd>' : '') + '</button>';
    }).join('');
    cm._items = items;
    cm.classList.add('on');
    cm.setAttribute('aria-hidden', 'false');
    var r = cm.getBoundingClientRect();
    cm.style.left = Math.max(8, Math.min(x, innerWidth - r.width - 8)) + 'px';
    cm.style.top = Math.max(8, Math.min(y, innerHeight - r.height - 8)) + 'px';
    var first = $('button:not([disabled])', cm);
    if (first) first.focus({ preventScroll: true });
  }
  function hideMenu() {
    if (!cm.classList.contains('on')) return;
    cm.classList.remove('on');
    cm.setAttribute('aria-hidden', 'true');
    if (cmFocusBack && cmFocusBack.focus) cmFocusBack.focus({ preventScroll: true });
  }
  cm.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-i]');
    if (!b) return;
    var it = cm._items[+b.dataset.i];
    hideMenu();
    if (it && it.run) it.run();
  });
  cm.addEventListener('keydown', function (e) {
    var btns = $$('button:not([disabled])', cm);
    var i = btns.indexOf(document.activeElement);
    if (e.key === 'ArrowDown') { e.preventDefault(); btns[(i + 1) % btns.length].focus(); }
    if (e.key === 'ArrowUp') { e.preventDefault(); btns[(i - 1 + btns.length) % btns.length].focus(); }
    if (e.key === 'Escape' || e.key === 'Tab') { e.preventDefault(); hideMenu(); }
  });
  function unitMenu(n) {
    var items = [];
    var edit = canEdit(n);
    var col = S.collapsed.has(n.id);
    items.push({ label: t('menu_open'), run: function () { select(n.id); } });
    if (edit) {
      items.push({ label: t('menu_edit'), kbd: 'E', run: function () { openEdit(n.unitId, 'identity'); } });
      items.push({ label: t('menu_rename'), kbd: 'F2', run: function () { inlineRename(n.id); } });
      items.push({ label: t('menu_symbol'), run: function () { openEdit(n.unitId, 'symbol'); } });
      items.push({ label: t('menu_posts'), run: function () { openEdit(n.unitId, 'posts'); } });
      items.push({ label: t('menu_status'), run: function () { openEdit(n.unitId, 'status'); } });
      items.push({ label: t('menu_visibility'), run: function () { openEdit(n.unitId, 'status'); } });
    }
    if (B.canManage && !n.isOrbatPlaceholder) {
      items.push('-');
      items.push({ label: t('menu_create'), kbd: 'N', run: function () { openCreate(n.unitId > 0 ? n.unitId : 0); } });
      if (edit) {
        items.push({ label: t('menu_move'), run: function () { openMove(n.unitId); } });
        var p = parentNode(n);
        items.push({ label: t('menu_detach'), disabled: !p || !(p.unitId > 0), run: function () { if (window.confirm(t('detach_confirm'))) moveUnit(n.unitId, 0); } });
      }
    }
    if (B.hub && !n.isOrbatPlaceholder) {
      items.push('-');
      items.push({ label: t('invite_member') + '…', run: openInvite });
      if (B.canManage) {
        items.push({ label: t('menu_new_group'), run: function () { openCreate(n.unitId > 0 ? n.unitId : 0, 'group'); } });
        items.push({ label: t('menu_new_team'), run: function () { openCreate(n.unitId > 0 ? n.unitId : 0, 'team'); } });
      }
    }
    items.push('-');
    if (kids(n).length) items.push({ label: col ? t('menu_expand') : t('menu_collapse'), run: function () { toggleCollapse(n.id); } });
    items.push({ label: t('menu_center'), kbd: '0', run: function () { setView('tree'); select(n.id, true); } });
    if (edit) items.push({ label: t('menu_full_sheet'), run: function () { location.href = B.fullSheetBase + n.unitId; } });
    if (edit) { items.push('-'); items.push({ label: t('menu_delete'), kbd: 'Suppr', danger: true, run: function () { openDelete(n.unitId); } }); }
    return items;
  }
  function postMenu(n, billetId, holderId) {
    var b = (n.billets || []).filter(function (x) { return String(x.id) === String(billetId); })[0];
    if (!b) return [];
    var items = [];
    var edit = canEdit(n);
    var holders = b.holders || [];
    if (edit) items.push({ label: t('menu_assign'), run: function () { openAssign(n.unitId, b.id); } });
    holders.forEach(function (h) {
      if (holderId && String(h.id) !== String(holderId)) return;
      if (edit) items.push({ label: t('menu_vacate', { name: h.label }), run: function () { vacate(n.unitId, b.id, h); } });
      if (h.user_id > 0) items.push({ label: t('menu_dossier', { name: h.label }), run: function () { location.href = B.personnelBase + h.user_id; } });
    });
    if (edit) {
      items.push('-');
      items.push({ label: t('menu_post_edit'), run: function () { openPostForm(n.unitId, b.id); } });
      items.push({ label: t('menu_post_delete'), danger: true, run: function () { deletePost(n.unitId, b.id); } });
    }
    return items;
  }
  function canvasMenu() {
    var items = [];
    if (B.canManage) items.push({ label: t('menu_new_root'), run: function () { openCreate(0); } });
    if (B.hub) items.push({ label: t('invite_member') + '…', run: openInvite });
    if (items.length) items.push('-');
    items.push({ label: t('expand_all'), run: expandAll });
    items.push({ label: t('collapse_all'), run: collapseAll });
    items.push({ label: t('fit'), kbd: '0', run: function () { fit(); } });
    return items;
  }
  function contextFor(target, x, y) {
    var nd = target.closest('.orb-nd, tr[data-key]');
    var dr = target.closest('#orb-dr');
    var key = nd ? nd.dataset.key : (dr ? S.sel : null);
    var n = key ? node(key) : null;
    var billet = target.closest('[data-billet]');
    if (n && billet) {
      var holder = target.closest('[data-holder]');
      var items = postMenu(n, billet.dataset.billet, holder ? holder.dataset.holder : null);
      if (items.length) { showMenu(items, x, y); return true; }
    }
    if (n) { showMenu(unitMenu(n), x, y); return true; }
    if (target.closest('#orb-cv')) { showMenu(canvasMenu(), x, y); return true; }
    return false;
  }
  rootEl.addEventListener('contextmenu', function (e) {
    if (e.target.closest('input, textarea, select, a[href]')) return;
    if (contextFor(e.target, e.clientX, e.clientY)) e.preventDefault();
  });

  /* ------------------------------------------------------------------ fenêtres modales */
  var ov = $('#orb-ov');
  var mdl = $('#orb-mdl');
  var M = null;
  function openModal(o) {
    if (M && M.onClose) M.onClose();
    M = o;
    M.back = M.back || document.activeElement;
    mdl.className = 'orb-mdl' + (o.wide ? '' : ' sm') + (o.lg ? ' lg' : '');
    mdl.innerHTML = o.html();
    ov.classList.add('on');
    ov.setAttribute('aria-hidden', 'false');
    if (o.init) o.init();
    setTimeout(function () {
      var f = $(o.focus || '[autofocus], input:not([type=hidden]):not([disabled]), select, textarea, button.orb-btn--dark', mdl);
      if (f) f.focus();
    }, 40);
  }
  function closeModal(force) {
    if (!M) return;
    if (!force && M.dirty && M.dirty() && !window.confirm(t('unsaved') + ' — ' + t('close') + ' ?')) return;
    var back = M.back;
    if (M.onClose) M.onClose();
    M = null;
    ov.classList.remove('on');
    ov.setAttribute('aria-hidden', 'true');
    mdl.innerHTML = '';
    if (back && back.focus && document.contains(back)) back.focus({ preventScroll: true });
  }
  function refreshOpenModal() { if (M && M.refresh) M.refresh(); }
  function refreshOpenModalPreview() { if (M && M.preview) M.preview(); }
  ov.addEventListener('mousedown', function (e) { if (e.target === ov) closeModal(); });
  ov.addEventListener('click', function (e) {
    if (e.target.closest('[data-mclose]')) { closeModal(); return; }
    if (M && M.onClick) M.onClick(e);
  });
  ov.addEventListener('input', function (e) { if (M && M.onInput) M.onInput(e); });
  ov.addEventListener('change', function (e) { if (M && M.onChange) M.onChange(e); });
  ov.addEventListener('keydown', function (e) {
    if (e.key === 'Tab') {
      var f = $$('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea, a[href], [tabindex="0"]', mdl).filter(function (x) { return x.offsetParent !== null; });
      if (!f.length) return;
      if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
    }
  });

  function opt(v, label, cur) { return '<option value="' + esc(v) + '"' + (String(cur == null ? '' : cur) === String(v) ? ' selected' : '') + '>' + esc(label) + '</option>'; }
  function memberSelect(attr, cur, none) {
    return '<select class="orb-in" ' + attr + '>' + opt('', none || t('f_none'), cur) + (B.commanders || []).map(function (c) { return opt(c.id, c.label, cur); }).join('') + '</select>';
  }
  function unitOptions(excludeKeys, cur) {
    var out = [];
    (function walk(n, depth) {
      if (n.unitId > 0 && !excludeKeys.has(n.id)) out.push(opt(n.unitId, ' '.repeat(Math.max(0, depth)) + n.label + (n.unitCode ? ' (' + n.unitCode + ')' : ''), cur));
      (n.children || []).forEach(function (c) { walk(c, n.unitId > 0 ? depth + 1 : depth); });
    })(S.root || { children: [] }, 0);
    return out.join('');
  }
  function structTypes() { return (S.opts && S.opts.structTypes) || B.structTypes || []; }
  function chartTypes() { return (S.opts && S.opts.chartDisplayTypes) || []; }
  function caps() { return (S.opts && S.opts.capabilities) || {}; }
  function visibilityLevels() { return (S.opts && S.opts.visibilityLevels) || []; }
  function adminStatuses() { return (S.opts && S.opts.adminStatuses) || STATUS_IDS.map(function (id) { return { id: id, label: statusLabel(id) }; }); }

  /* ---- édition d’une unité (onglets) ---- */
  var TABS = ['identity', 'symbol', 'status', 'posts', 'public', 'media', 'history'];
  var EDIT_FIELDS = ['name', 'code', 'struct_type', 'orbat_type', 'accent_color', 'motto', 'orbat_details', 'admin_status', 'admin_status_note',
    'visibility_level', 'commander_user_id', 'show_on_public_page', 'public_blurb', 'public_founded_on', 'public_custom_date', 'public_custom_date_label'];

  function openEdit(unitId, tab) {
    var n = nodeOfUnit(unitId);
    if (!canEdit(n)) return;
    hideMenu();
    ensureOptions().then(function () {
      n = nodeOfUnit(unitId);
      if (!n) return;
      var d = {};
      EDIT_FIELDS.forEach(function (f) { d[f] = apiValue(n, f); });
      var p = parentNode(n);
      d.parent = p && p.unitId > 0 ? String(p.unitId) : '';
      d.fn = n.sidcAuto ? '' : (n.symbolFunction || '');
      d.hq = (n.sidc || '').charAt(10) === 'A';
      d.sidc = apiValue(n, 'sidc');
      d.sidcExpert = !n.sidcAuto && !n.symbolFunction ? n.sidc : '';
      d.echelon = apiValue(n, 'echelon');
      var orig = clone(d);
      var E = { unitId: unitId, tab: TABS.indexOf(tab) >= 0 ? tab : 'identity', d: d, orig: orig, tried: false };
      openModal({
        wide: true,
        html: function () { return editShell(E); },
        dirty: function () { return Object.keys(editDiff(E)).length > 0; },
        init: function () { editPreview(E); },
        preview: function () { editPreview(E); },
        refresh: function () {
          var x = nodeOfUnit(unitId);
          if (!x) { closeModal(true); return; }
          if (E.tab === 'posts' || E.tab === 'media') { $('#orb-mbd').innerHTML = editBody(E); }
          editPreview(E);
        },
        onClick: function (e) { editClick(E, e); },
        onInput: function (e) { editInput(E, e); },
        onChange: function (e) { editInput(E, e); },
        onKey: function (e) { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); editSave(E); } }
      });
    });
  }
  function editShell(E) {
    var n = nodeOfUnit(E.unitId);
    var p = parentNode(n);
    var vac = (n.billets || []).filter(function (b) { return !(b.holders || []).length; }).length;
    return '<div class="orb-ml"><div><div class="orb-k">' + esc(t('preview')) + '</div><div class="orb-pvw" id="orb-pv"></div></div>' +
      '<nav class="orb-mt" role="tablist">' + TABS.map(function (k) {
        if (k === 'symbol' && !caps().military_symbol) return '';
        if (k === 'media' && !caps().chart_media_upload) return '';
        var badge = k === 'identity' && !E.d.name.trim() ? '<em>' + esc(t('required')) + '</em>' : (k === 'posts' && vac ? '<em>' + vac + '</em>' : '');
        return '<button type="button" role="tab" data-tab="' + k + '" aria-selected="' + (E.tab === k) + '" class="' + (E.tab === k ? 'on' : '') + '">' + esc(t('tab_' + k)) + badge + '</button>';
      }).join('') + '</nav><p class="orb-tip">' + esc(t('attached_to', { name: p ? p.label : '—' })) + '<br>' + esc(t('modal_tip')) + '</p></div>' +
      '<div class="orb-mr"><div class="orb-mh"><div><div class="orb-k">' + esc(t('edit_unit')) + '</div><h2 id="orb-mh-name">' + esc(E.d.name || t('untitled')) + '</h2></div>' +
      '<button type="button" class="orb-x" data-mclose aria-label="' + esc(t('close')) + '">×</button></div>' +
      '<div class="orb-mbd" id="orb-mbd" role="tabpanel">' + editBody(E) + '</div>' +
      '<div class="orb-mf"><button type="button" class="orb-btn orb-btn--dng" data-mdel>' + esc(t('menu_delete')) + '</button><span class="orb-sp"></span>' +
      '<span class="orb-st" id="orb-mst"></span><button type="button" class="orb-btn" data-mclose>' + esc(t('cancel')) + '</button>' +
      '<button type="button" class="orb-btn orb-btn--dark" data-save>' + esc(t('save')) + '</button></div></div>';
  }
  function fl(label, control, help, full) {
    return '<div class="orb-fl' + (full ? ' full' : '') + '"><label>' + label + '</label>' + control + (help ? '<span class="orb-h">' + esc(help) + '</span>' : '') + '</div>';
  }
  function editBody(E) {
    var d = E.d;
    var n = nodeOfUnit(E.unitId);
    if (E.tab === 'identity') {
      var excl = new Set([n.id].concat(descendants(n).map(function (x) { return x.id; })));
      var bad = E.tried && !d.name.trim();
      return '<div class="orb-fs"><h3>' + esc(t('f_identity')) + '</h3><p>' + esc(t('f_identity_d')) + '</p></div><div class="orb-fg">' +
        fl(esc(t('f_name')) + ' <i>*</i>', '<input class="orb-in' + (bad ? ' bad' : '') + '" data-f="name" maxlength="255" value="' + esc(d.name) + '">' + (bad ? '<span class="orb-er">' + esc(t('f_name_required')) + '</span>' : ''), '', true) +
        fl(esc(t('f_code')), '<input class="orb-in orb-mono" data-f="code" maxlength="20" value="' + esc(d.code) + '">', t('f_code_h')) +
        fl(esc(t('f_unit_code')), '<input class="orb-in orb-mono" disabled value="' + esc((n.unitCode || '—') + (n.adminId ? ' · ' + n.adminId : '')) + '">', t('f_unit_code_h')) +
        fl(esc(t('f_struct_type')), '<select class="orb-in" data-f="struct_type">' + structTypes().map(function (o) { return opt(o.id, o.label, d.struct_type); }).join('') + '</select>') +
        fl(esc(t('f_chart_type')), '<select class="orb-in" data-f="orbat_type">' + chartTypes().map(function (o) { return opt(o.id, o.label || o.id, d.orbat_type); }).join('') + '</select>' +
          (caps().custom_chart_types ? '<div class="orb-inline"><input class="orb-in" id="orb-ct-name" maxlength="120" placeholder="' + esc(t('f_chart_type_name')) + '"><button type="button" class="orb-btn" data-ct-add>' + esc(t('f_chart_type_add')) + '</button></div>' : '')) +
        fl(esc(t('f_parent')), '<select class="orb-in" data-f="parent">' + opt('', t('f_parent_root'), d.parent) + unitOptions(excl, d.parent) + '</select>', '', true) +
        fl(esc(t('f_accent')), '<input class="orb-in orb-color" type="color" data-f="accent_color" value="' + esc(/^#[0-9A-Fa-f]{6}$/.test(d.accent_color) ? d.accent_color : '#34d399') + '">') +
        fl(esc(t('f_motto')), '<input class="orb-in" data-f="motto" maxlength="255" value="' + esc(d.motto) + '">') +
        (caps().details_field !== false ? fl(esc(t('f_details')), '<textarea class="orb-in" data-f="orbat_details" rows="3">' + esc(d.orbat_details) + '</textarea>', t('f_details_h'), true) : '') + '</div>';
    }
    if (E.tab === 'symbol') {
      var auto = (function () {
        var fo = functionOption(n.symbolFunction);
        return fo ? fo.label : '';
      })();
      var autoEch = n.echelonAuto && n.echelon ? echelonLabel(n.echelon) : t('f_echelon_none');
      var fns = (B.symbols && B.symbols.functions) || [];
      var ech = (B.symbols && B.symbols.echelons) || [];
      return '<div class="orb-fs"><h3>' + esc(t('f_symbol')) + '</h3><p>' + esc(t('f_symbol_d')) + '</p></div>' +
        '<div class="orb-symgrid"><div class="orb-symbig" id="orb-symbig"></div><div class="orb-fg">' +
        fl(esc(t('f_function')), '<select class="orb-in" data-f="fn"' + (d.sidcExpert ? ' disabled' : '') + '>' + opt('', t('f_function_auto', { label: auto || t('f_echelon_none') }), d.fn) +
          fns.map(function (o) { return opt(o.id, o.label, d.fn); }).join('') + '</select>', '', true) +
        fl(esc(t('f_echelon')), '<select class="orb-in" data-f="echelon">' + opt('', t('f_echelon_auto', { label: autoEch }), d.echelon) +
          ech.map(function (o) { return opt(o.id, o.label, d.echelon); }).join('') + '</select>', '', true) +
        '<label class="orb-swt full"><input type="checkbox" data-f="hq"' + (d.hq ? ' checked' : '') + (d.fn || d.sidcExpert ? '' : ' disabled') + '><span><b>' + esc(t('f_hq')) + '</b></span></label>' +
        '</div></div><details class="orb-expert"' + (d.sidcExpert ? ' open' : '') + '><summary>' + esc(t('f_sidc')) + '</summary>' +
        fl('', '<input class="orb-in orb-mono" data-f="sidcExpert" maxlength="30" spellcheck="false" value="' + esc(d.sidcExpert) + '" placeholder="SFGPUCI----D---">', t('f_sidc_h'), true) + '</details>' +
        '<div class="orb-symlib">' + fns.map(function (o) {
          return '<button type="button" data-pick="' + esc(o.id) + '" class="' + (d.fn === o.id ? 'on' : '') + '" title="' + esc(o.label) + '"><span>' + (symbolSvg(composeSidc(o.sidc, d.echelon || n.echelon), 14, true) || esc(o.label.slice(0, 3))) + '</span><small>' + esc(o.label) + '</small></button>';
        }).join('') + '</div>';
    }
    if (E.tab === 'status') {
      var vis = visibilityLevels();
      return '<div class="orb-fs"><h3>' + esc(t('f_status')) + '</h3><p>' + esc(t('f_status_d')) + '</p></div><div class="orb-rcs">' +
        adminStatuses().map(function (o) {
          return '<button type="button" class="orb-ro' + (d.admin_status === o.id ? ' on' : '') + '" data-r="admin_status" data-v="' + esc(o.id) + '" aria-pressed="' + (d.admin_status === o.id) + '"><i style="background:' +
            (STATUS_COLOR[o.id] || '#94a3b8') + '"></i><span><b>' + esc(o.label) + '</b><small>' + esc(L['status_' + o.id + '_d'] || '') + '</small></span></button>';
        }).join('') + '</div><div class="orb-fg" style="margin-top:14px">' +
        fl(esc(t('f_status_note')), '<input class="orb-in" data-f="admin_status_note" maxlength="500" value="' + esc(d.admin_status_note) + '" placeholder="' + esc(t('f_status_note_ph')) + '">', '', true) + '</div>' +
        (vis.length ? '<div class="orb-fs"><h3>' + esc(t('f_visibility')) + '</h3><p>' + esc(t('f_visibility_d')) + '</p></div><div class="orb-rcs">' + vis.map(function (o) {
          return '<button type="button" class="orb-ro' + (d.visibility_level === o.id ? ' on' : '') + '" data-r="visibility_level" data-v="' + esc(o.id) + '" aria-pressed="' + (d.visibility_level === o.id) + '"><i style="background:' +
            (VIS_COLOR[o.id] || '#94a3b8') + '"></i><span><b>' + esc(o.label) + '</b><small>' + esc(o.consequence || '') + '</small></span></button>';
        }).join('') + '</div>' : '');
    }
    if (E.tab === 'posts') {
      var billets = n.billets || [];
      return '<div class="orb-fs"><h3>' + esc(t('f_command')) + '</h3><p>' + esc(t('f_command_d')) + '</p></div><div class="orb-fg">' +
        fl(esc(t('f_commander')), memberSelect('data-f="commander_user_id"', d.commander_user_id), '', true) + '</div>' +
        '<div class="orb-fs"><h3>' + esc(t('f_posts')) + '</h3><p>' + esc(t('f_posts_d')) + '</p></div>' +
        '<div class="orb-plist">' + billets.map(function (b) {
          return '<div class="orb-prow" data-billet="' + b.id + '">' + postRowHtml(b) + '<div class="orb-pact">' +
            '<button type="button" class="orb-btn" data-assign="' + b.id + '">' + esc(t('assign')) + '</button>' +
            ((b.holders || []).map(function (h) { return '<button type="button" class="orb-btn" data-vacate="' + b.id + '" data-hid="' + h.id + '" title="' + esc(t('menu_vacate', { name: h.label })) + '">' + esc(t('f_vacate')) + '</button>'; }).join('')) +
            '<button type="button" class="orb-btn" data-pedit="' + b.id + '" aria-label="' + esc(t('menu_post_edit')) + '">✎</button>' +
            '<button type="button" class="orb-btn orb-btn--dng" data-pdel="' + b.id + '" aria-label="' + esc(t('menu_post_delete')) + '">×</button></div></div>';
        }).join('') + '</div><button type="button" class="orb-add" data-post-new>' + esc(t('add_post')) + '</button>';
    }
    if (E.tab === 'public') {
      var pub = d.show_on_public_page === '1';
      return '<div class="orb-fs"><h3>' + esc(t('f_public')) + '</h3><p>' + esc(t('f_public_d')) + '</p></div>' +
        '<label class="orb-swt"><input type="checkbox" data-f="show_on_public_page"' + (pub ? ' checked' : '') + '><span><b>' + esc(t('f_public_show')) + '</b><small>' + esc(t('f_public_show_h')) + '</small></span></label>' +
        '<div class="orb-fg" style="margin-top:16px">' +
        fl(esc(t('f_public_blurb')), '<textarea class="orb-in" data-f="public_blurb" rows="4">' + esc(d.public_blurb) + '</textarea>', '', true) +
        fl(esc(t('f_founded')), '<input class="orb-in" type="date" data-f="public_founded_on" value="' + esc(d.public_founded_on) + '">') +
        fl(esc(t('f_custom_date')), '<input class="orb-in" type="date" data-f="public_custom_date" value="' + esc(d.public_custom_date) + '">') +
        fl(esc(t('f_custom_date_label')), '<input class="orb-in" data-f="public_custom_date_label" maxlength="80" value="' + esc(d.public_custom_date_label) + '" placeholder="' + esc(t('f_custom_date_ph')) + '">', '', true) + '</div>';
    }
    if (E.tab === 'history') {
      if (!E.hist) loadHistory(E);
      return '<div class="orb-fs"><h3>' + esc(t('tab_history')) + '</h3><p>' + esc(t('history_d')) + '</p></div>' + historyHtml(E);
    }
    return '<div class="orb-fs"><h3>' + esc(t('f_media')) + '</h3><p>' + esc(t('f_media_d')) + '</p></div><div class="orb-fg">' +
      ['icon', 'image'].map(function (slot) {
        var url = slot === 'icon' ? n.chartIconUrl : n.chartImageUrl;
        return fl(esc(t('f_' + slot)), '<div class="orb-drop">' + (url ? '<img alt="" src="' + esc(mediaSrc(url)) + '">' : '<span class="orb-muted">—</span>') +
          '<label class="orb-btn"><input type="file" hidden data-upload="' + slot + '" accept="' + (slot === 'icon' ? '.png,.ico,.jpg,.jpeg,image/png,image/x-icon,image/jpeg' : '.png,.jpg,.jpeg,image/png,image/jpeg') + '">' + esc(t('f_choose_file')) + '</label>' +
          (url ? '<button type="button" class="orb-btn orb-btn--dng" data-clear="' + slot + '">' + esc(t('f_remove')) + '</button>' : '') + '</div>');
      }).join('') + '</div>';
  }
  function historyHtml(E) {
    if (E.hist === 'error') return '<p class="orb-muted">' + esc(t('history_error')) + '</p>';
    if (!E.hist) return '<p class="orb-muted">' + esc(t('history_loading')) + '</p>';
    if (!E.hist.length) return '<p class="orb-muted">' + esc(t('history_empty')) + '</p>';
    return '<ul class="orb-hist">' + E.hist.map(function (r) {
      var label = L['hf_' + r.field] != null ? t('hf_' + r.field) : r.field;
      var shown = function (v) { return r.field === 'status' ? statusLabel(v) : v; };
      var line = r.old ? t('history_line', { field: label, old: shown(r.old), new: shown(r.new) }) : t('history_line_new', { field: label, new: shown(r.new) });
      return '<li><span>' + esc(line) + '</span><small>' + esc(r.at) + (r.by ? ' · ' + esc(t('history_by', { name: r.by })) : '') + '</small></li>';
    }).join('') + '</ul>';
  }
  function loadHistory(E) {
    E.hist = null;
    post(api('/api/orbat/structure'), { action: 'unit_history', unit_id: E.unitId }).then(function (j) {
      E.hist = j.history || [];
    }, function () { E.hist = 'error'; }).then(function () {
      var body = $('#orb-mbd');
      if (body && E.tab === 'history') body.innerHTML = editBody(E);
    });
  }
  function editSymbolSidc(E) {
    var n = nodeOfUnit(E.unitId);
    var d = E.d;
    var ech = d.echelon || (n.echelonAuto ? n.echelon : '');
    if (d.sidcExpert) return /^[A-Z0-9\-*]{15}$/.test(d.sidcExpert.toUpperCase()) ? composeSidc(d.sidcExpert.toUpperCase(), ech) : d.sidcExpert.toUpperCase();
    if (d.fn) { var fo = functionOption(d.fn); return fo ? composeSidc(withHq(fo.sidc, d.hq), ech) : n.symbolSidc; }
    return n.sidcAuto ? composeSidc(n.sidc, ech) : n.symbolSidc;
  }
  function editPreview(E) {
    var n = nodeOfUnit(E.unitId);
    if (!n) return;
    var draft = clone(n);
    applyLocal(draft, { name: E.d.name, code: E.d.code, admin_status: E.d.admin_status, accent_color: E.d.accent_color, visibility_level: E.d.visibility_level });
    draft.symbolSidc = editSymbolSidc(E);
    if (E.d.echelon) draft.echelon = E.d.echelon;
    var pv = $('#orb-pv');
    if (pv) pv.innerHTML = '<div class="orb-nd pv" style="' + cardStyle(draft) + '">' + cardInner(draft, { preview: true }) + '</div>';
    var big = $('#orb-symbig');
    if (big) big.innerHTML = symbolSvg(draft.symbolSidc, 46) || '<span class="orb-sym-ph">' + esc(t('symbol_loading')) + '</span>';
    var st = $('#orb-mst');
    if (st) {
      var dirty = Object.keys(editDiff(E)).length > 0;
      st.className = 'orb-st' + (dirty ? ' d' : '');
      st.textContent = dirty ? t('unsaved') : t('no_change');
    }
    var h = $('#orb-mh-name');
    if (h) h.textContent = E.d.name || t('untitled');
  }
  function editDiff(E) {
    var out = {};
    EDIT_FIELDS.forEach(function (f) { if (String(E.d[f]) !== String(E.orig[f])) out[f] = E.d[f]; });
    // Symbole : base SIDC (expert, fonction choisie ou automatique) puis échelon.
    var sidc = E.d.sidcExpert ? E.d.sidcExpert.toUpperCase() : (E.d.fn ? withHq((functionOption(E.d.fn) || {}).sidc || '', E.d.hq) : '');
    var origSidc = E.orig.sidcExpert ? E.orig.sidcExpert.toUpperCase() : (E.orig.fn ? withHq((functionOption(E.orig.fn) || {}).sidc || '', E.orig.hq) : '');
    if (sidc !== origSidc) out.sidc = sidc;
    if (E.d.echelon !== E.orig.echelon) out.echelon = E.d.echelon;
    if (E.d.parent !== E.orig.parent) out.parent = E.d.parent;
    return out;
  }
  function editSave(E) {
    if (!E.d.name.trim()) { E.tried = true; E.tab = 'identity'; mdl.innerHTML = editShell(E); editPreview(E); return; }
    if (E.d.sidcExpert && !/^([A-Z][A-Z\-*]{14}|\d{20}|\d{30})$/.test(E.d.sidcExpert.toUpperCase())) {
      E.tab = 'symbol'; mdl.innerHTML = editShell(E); editPreview(E); toast(t('f_sidc_bad'), null, true); return;
    }
    var diff = editDiff(E);
    var parent = diff.parent;
    delete diff.parent;
    if (diff.name !== undefined) diff.name = diff.name.trim();
    closeModal(true);
    var hasPatch = Object.keys(diff).length > 0;
    if (!hasPatch && parent === undefined) { toast(t('no_change')); return; }
    /* En série : deux réponses parallèles portent chacune l'organigramme complet, la plus lente écraserait l'autre. */
    var chain = hasPatch ? patchUnit(E.unitId, diff).then(function () { return true; }, function () { return false; }) : Promise.resolve(true);
    if (parent !== undefined) chain.then(function (ok) { if (ok) moveUnit(E.unitId, parseInt(parent, 10) || 0).catch(function () {}); });
  }
  function editClick(E, e) {
    var tg = e.target;
    var tab = tg.closest('[data-tab]');
    if (tab) { E.tab = tab.dataset.tab; mdl.innerHTML = editShell(E); editPreview(E); var b = $('[data-tab="' + E.tab + '"]', mdl); if (b) b.focus(); return; }
    var r = tg.closest('[data-r]');
    if (r) { E.d[r.dataset.r] = r.dataset.v; $('#orb-mbd').innerHTML = editBody(E); editPreview(E); return; }
    var pick = tg.closest('[data-pick]');
    if (pick) { E.d.fn = pick.dataset.pick; E.d.sidcExpert = ''; $('#orb-mbd').innerHTML = editBody(E); editPreview(E); return; }
    if (tg.closest('[data-save]')) { editSave(E); return; }
    if (tg.closest('[data-mdel]')) { closeModal(true); openDelete(E.unitId); return; }
    if (tg.closest('[data-ct-add]')) { addChartType(E); return; }
    var n = nodeOfUnit(E.unitId);
    if (tg.closest('[data-post-new]')) { openPostForm(E.unitId, 0, E); return; }
    var as = tg.closest('[data-assign]');
    if (as) { openAssign(E.unitId, +as.dataset.assign, E); return; }
    var va = tg.closest('[data-vacate]');
    if (va) {
      var b = (n.billets || []).filter(function (x) { return String(x.id) === va.dataset.vacate; })[0];
      var h = b && (b.holders || []).filter(function (x) { return String(x.id) === va.dataset.hid; })[0];
      if (h) vacate(E.unitId, b.id, h);
      return;
    }
    var pe = tg.closest('[data-pedit]');
    if (pe) { openPostForm(E.unitId, +pe.dataset.pedit, E); return; }
    var pd = tg.closest('[data-pdel]');
    if (pd) { deletePost(E.unitId, +pd.dataset.pdel); return; }
    var cl = tg.closest('[data-clear]');
    if (cl) { clearMedia(E.unitId, cl.dataset.clear); return; }
  }
  function editInput(E, e) {
    var tg = e.target;
    if (tg.dataset.upload && e.type === 'change') { uploadMedia(E.unitId, tg.dataset.upload, tg.files && tg.files[0]); return; }
    var f = tg.dataset.f;
    if (!f) return;
    var v = tg.type === 'checkbox' ? tg.checked : tg.value;
    if (f === 'show_on_public_page') v = v ? '1' : '0';
    E.d[f] = v;
    if (f === 'fn' || f === 'echelon' || f === 'sidcExpert') {
      if (f === 'fn' && !v) E.d.hq = false;
      if (e.type === 'change' || f === 'sidcExpert') {
        var keep = f === 'sidcExpert' ? tg.selectionStart : null;
        if (f !== 'sidcExpert') { $('#orb-mbd').innerHTML = editBody(E); }
        else {
          var fnSel = $('[data-f="fn"]', mdl);
          if (fnSel) fnSel.disabled = !!v;
          if (keep != null) tg.setSelectionRange(keep, keep);
        }
      }
    }
    if (f === 'name' && E.tried && String(v).trim()) E.tried = false;
    editPreview(E);
  }
  function addChartType(E) {
    var inp = $('#orb-ct-name');
    var label = inp ? inp.value.trim() : '';
    if (!label) { if (inp) inp.focus(); return; }
    post(api('/api/orbat/chart-type'), { action: 'create', label: label }).then(function (j) {
      if (j.chartDisplayTypes && S.opts) S.opts.chartDisplayTypes = j.chartDisplayTypes;
      var created = (j.chartDisplayTypes || []).filter(function (x) { return x.label === label; })[0];
      if (created) E.d.orbat_type = created.id;
      $('#orb-mbd').innerHTML = editBody(E);
      editPreview(E);
    }).catch(function (err) { toast(err.message, null, true); });
  }

  /* ---- visuels ---- */
  function uploadMedia(unitId, slot, file) {
    if (!file) return;
    post(api('/api/orbat/unit-upload'), { unit_id: unitId, slot: slot, file: file }).then(function (j) {
      if (j.roster) applyServer(j.roster); else reloadRoster();
      toast(t('f_media_sent'));
    }).catch(function (err) { toast(err.message, null, true); });
  }
  function clearMedia(unitId, slot) {
    var f = { unit_id: unitId };
    f[slot === 'icon' ? 'clear_chart_icon' : 'clear_chart_image'] = '1';
    mutate({
      url: api('/api/orbat/unit'), fields: f, toast: t('f_media_removed'),
      apply: function () { var n = nodeOfUnit(unitId); if (n) n[slot === 'icon' ? 'chartIconUrl' : 'chartImageUrl'] = null; }
    }).catch(function () {});
  }

  /* ---- postes ---- */
  function findBillet(n, id) { return (n.billets || []).filter(function (b) { return String(b.id) === String(id); })[0] || null; }
  function vacate(unitId, billetId, h) {
    structure('billet_vacate', { holder_id: h.id, billet_id: billetId, user_id: h.user_id || '' }, {
      toast: t('t_vacated'),
      apply: function () {
        var b = findBillet(nodeOfUnit(unitId) || {}, billetId);
        if (!b) return;
        b.holders = (b.holders || []).filter(function (x) { return x.id !== h.id; });
        b.filled = Math.max(0, (b.filled || 0) - 1);
        b.seat_status = b.holders.length ? 'partial' : 'vacant';
        var n = nodeOfUnit(unitId);
        if (n.commander && n.commander.billetId === billetId) n.commander = null;
        if (n.deputy && n.deputy.billetId === billetId) n.deputy = null;
      }
    }).catch(function () {});
  }
  function deletePost(unitId, billetId) {
    structure('billet_delete', { billet_id: billetId }, {
      toast: t('t_post_deleted'),
      apply: function () { var n = nodeOfUnit(unitId); if (n) n.billets = (n.billets || []).filter(function (b) { return b.id !== billetId; }); }
    }).catch(function () {});
  }
  function openAssign(unitId, billetId, parentEdit) {
    var n = nodeOfUnit(unitId);
    var b = n && findBillet(n, billetId);
    if (!b) return;
    var back = parentEdit ? function () { openEdit(unitId, 'posts'); } : null;
    openModal({
      html: function () {
        return '<h2>' + esc(t('f_assign')) + '</h2><p>' + esc(t('f_assign_to', { title: b.title || b.code })) + '</p><div class="orb-fg one">' +
          fl(esc(t('f_holder')), memberSelect('id="orb-as-user" autofocus', '', '—')) +
          fl(esc(t('f_occupancy')), '<select class="orb-in" id="orb-as-occ">' + ['primary', 'acting', 'deputy', 'alternate'].map(function (o) { return opt(o, t('occ_' + o), 'primary'); }).join('') + '</select>') +
          '</div><div class="orb-acts"><button type="button" class="orb-btn" data-mclose>' + esc(t('cancel')) + '</button><button type="button" class="orb-btn orb-btn--dark" data-go-assign>' + esc(t('assign')) + '</button></div>';
      },
      onClose: null,
      onClick: function (e) {
        if (!e.target.closest('[data-go-assign]')) return;
        var uid = $('#orb-as-user').value;
        var occ = $('#orb-as-occ').value;
        if (!uid) { $('#orb-as-user').focus(); return; }
        var label = $('#orb-as-user').selectedOptions[0].textContent;
        closeModal(true);
        structure('billet_occupy', { billet_id: billetId, user_id: uid, occupancy_type: occ }, {
          toast: t('t_assigned'),
          apply: function () {
            var x = findBillet(nodeOfUnit(unitId) || {}, billetId);
            if (!x) return;
            x.holders = (x.holders || []).concat([{ id: -Date.now(), user_id: +uid, label: label, occupancy_type: occ, occupancy_label: t('occ_' + occ) }]);
            x.filled = (x.filled || 0) + 1;
            x.seat_status = x.filled >= (x.authorized || 1) ? 'filled' : 'partial';
          }
        }).then(function () { if (back) back(); }, function () {});
      },
      onKey: function (e) { if (e.key === 'Enter' && e.target.tagName === 'SELECT') { e.preventDefault(); var g = $('[data-go-assign]', mdl); if (g) g.click(); } }
    });
  }
  function openPostForm(unitId, billetId, parentEdit) {
    var n = nodeOfUnit(unitId);
    var b = billetId ? findBillet(n, billetId) : null;
    var kind = b ? String(b.key_post_kind || '') : '';
    openModal({
      html: function () {
        return '<h2>' + esc(b ? t('f_post_edit') : t('f_post_new')) + '</h2><p>' + esc(n.label) + '</p><div class="orb-fg">' +
          fl(esc(t('f_post_title')) + ' <i>*</i>', '<input class="orb-in" id="orb-pf-title" maxlength="150" autofocus value="' + esc(b ? (b.titleSource || b.title) : '') + '">', '', true) +
          fl(esc(t('f_post_function')), '<input class="orb-in" id="orb-pf-fn" maxlength="150" value="' + esc(b ? b.function_label : '') + '">') +
          fl(esc(t('f_post_slots')), '<input class="orb-in" id="orb-pf-slots" type="number" min="1" max="50" value="' + esc(b ? b.authorized : 1) + '">') +
          fl(esc(t('f_post_kind')), '<select class="orb-in" id="orb-pf-kind">' + opt('', t('f_post_kind_none'), kind) + opt('commander', t('f_post_kind_commander'), kind) + opt('deputy', t('f_post_kind_deputy'), kind) + '</select>', '', true) +
          '</div><div class="orb-acts"><button type="button" class="orb-btn" data-mclose>' + esc(t('cancel')) + '</button><button type="button" class="orb-btn orb-btn--dark" data-go-post>' + esc(t('save')) + '</button></div>';
      },
      onClick: function (e) {
        if (!e.target.closest('[data-go-post]')) return;
        var title = $('#orb-pf-title').value.trim();
        if (!title) { $('#orb-pf-title').focus(); return; }
        var k = $('#orb-pf-kind').value;
        var fields = {
          title: title, function_label: $('#orb-pf-fn').value.trim(), authorized_slots: Math.max(1, parseInt($('#orb-pf-slots').value, 10) || 1),
          key_post_kind: k, is_key_post: k ? 1 : 0
        };
        closeModal(true);
        var done = function () { if (parentEdit) openEdit(unitId, 'posts'); };
        if (b) {
          structure('billet_update', Object.assign({ billet_id: b.id }, fields), {
            toast: t('t_post_saved'),
            apply: function () { var x = findBillet(nodeOfUnit(unitId) || {}, b.id); if (x) { x.title = fields.title; x.function_label = fields.function_label; x.authorized = fields.authorized_slots; x.key_post_kind = k; x.is_key_post = !!k; } }
          }).then(done, function () {});
        } else {
          structure('billet_create', Object.assign({ unit_id: unitId }, fields), {
            toast: t('t_post_saved'),
            apply: function () {
              var x = nodeOfUnit(unitId);
              if (x) x.billets = (x.billets || []).concat([{ id: -Date.now(), title: fields.title, function_label: fields.function_label, authorized: fields.authorized_slots, filled: 0, vacant: fields.authorized_slots, seat_status: 'vacant', key_post_kind: k, holders: [] }]);
            }
          }).then(done, function () {});
        }
      },
      onKey: function (e) { if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); var g = $('[data-go-post]', mdl); if (g) g.click(); } }
    });
  }

  /* ---- création ---- */
  function openCreate(parentUnitId, structType) {
    if (!B.canManage) return;
    hideMenu();
    ensureOptions().then(function () {
      var d = { name: '', struct_type: structType || (structTypes()[0] && structTypes().filter(function (o) { return o.id === 'unit'; }).length ? 'unit' : (structTypes()[0] || {}).id || 'unit'), parent: parentUnitId > 0 ? String(parentUnitId) : '', code: '', fn: '', echelon: '' };
      var tried = false;
      function body() {
        var fns = (B.symbols && B.symbols.functions) || [];
        var ech = (B.symbols && B.symbols.echelons) || [];
        var bad = tried && !d.name.trim();
        return '<h2>' + esc(t('create_title')) + '</h2><p>' + esc(t('create_d')) + '</p><div class="orb-create"><div class="orb-pvw sm" id="orb-pv"></div><div class="orb-fg">' +
          fl(esc(t('f_name')) + ' <i>*</i>', '<input class="orb-in' + (bad ? ' bad' : '') + '" data-c="name" maxlength="255" autofocus value="' + esc(d.name) + '">' + (bad ? '<span class="orb-er">' + esc(t('f_name_required')) + '</span>' : ''), '', true) +
          fl(esc(t('f_struct_type')), '<select class="orb-in" data-c="struct_type">' + structTypes().map(function (o) { return opt(o.id, o.label, d.struct_type); }).join('') + '</select>') +
          fl(esc(t('f_code')), '<input class="orb-in orb-mono" data-c="code" maxlength="20" value="' + esc(d.code) + '">') +
          fl(esc(t('f_parent')), '<select class="orb-in" data-c="parent">' + opt('', t('f_parent_root'), d.parent) + unitOptions(new Set(), d.parent) + '</select>', '', true) +
          (caps().military_symbol ? fl(esc(t('f_function')), '<select class="orb-in" data-c="fn">' + opt('', t('f_function_auto', { label: '…' }), d.fn) + fns.map(function (o) { return opt(o.id, o.label, d.fn); }).join('') + '</select>') +
            fl(esc(t('f_echelon')), '<select class="orb-in" data-c="echelon">' + opt('', t('f_echelon_auto', { label: '…' }), d.echelon) + ech.map(function (o) { return opt(o.id, o.label, d.echelon); }).join('') + '</select>') : '') +
          '</div></div><div class="orb-acts"><button type="button" class="orb-btn" data-mclose>' + esc(t('cancel')) + '</button><button type="button" class="orb-btn orb-btn--dark" data-go-create>' + esc(t('create')) + '</button></div>';
      }
      function preview() {
        var fo = functionOption(d.fn);
        var fake = { id: 'preview', unitId: 0, label: d.name || t('untitled'), role: d.code || 'Unité', adminStatus: 'active', adminStatusLabel: statusLabel('active'),
          symbolSidc: fo ? composeSidc(fo.sidc, d.echelon) : composeSidc('SFGPUCI--------', d.echelon), echelon: d.echelon, billets: [], members: [], children: [] };
        var pv = $('#orb-pv');
        if (pv) pv.innerHTML = '<div class="orb-nd pv" style="' + cardStyle(fake) + '">' + cardInner(fake, { preview: true }) + '</div>';
      }
      function submit() {
        if (!d.name.trim()) { tried = true; mdl.innerHTML = body(); preview(); $('[data-c="name"]', mdl).focus(); return; }
        var fo = functionOption(d.fn);
        var before = new Set(S.keyOfUnit.keys());
        var parentId = parseInt(d.parent, 10) || 0;
        var fields = { name: d.name.trim(), struct_type: d.struct_type, parent_id: parentId || '', code: d.code.trim() };
        if (caps().military_symbol) { fields.sidc = fo ? fo.sidc : ''; fields.echelon = d.echelon; }
        closeModal(true);
        structure('create', fields, {
          toast: t('t_created'),
          apply: function () {
            var p = parentId ? nodeOfUnit(parentId) : S.root;
            if (!p) return;
            S.collapsed.delete(p.id);
            p.children = (p.children || []).concat([{ id: 'tmp-' + Date.now(), unitId: 0, label: fields.name, role: fields.code || 'Unité', adminStatus: 'active', adminStatusLabel: statusLabel('active'),
              symbolSidc: fo ? composeSidc(fo.sidc, d.echelon) : 'SFGPUCI--------', echelon: d.echelon, billets: [], members: [], children: [], strength: 0 }]);
          },
          ok: function () {
            var fresh = null;
            S.keyOfUnit.forEach(function (key, id) { if (!before.has(id)) fresh = key; });
            if (fresh) select(fresh, true);
          }
        }).catch(function () {});
      }
      openModal({
        lg: true,
        html: body,
        init: preview,
        preview: preview,
        dirty: function () { return d.name.trim() !== ''; },
        onInput: function (e) { var c = e.target.dataset.c; if (!c) return; d[c] = e.target.value; if (c === 'name' && tried && d.name.trim()) tried = false; preview(); },
        onClick: function (e) { if (e.target.closest('[data-go-create]')) submit(); },
        onKey: function (e) { if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); submit(); } }
      });
    });
  }

  /* ---- rattachement ---- */
  function openMove(unitId) {
    var n = nodeOfUnit(unitId);
    if (!canEdit(n)) return;
    var excl = new Set([n.id].concat(descendants(n).map(function (x) { return x.id; })));
    var list = [];
    (function walk(x, depth) {
      if (x.unitId > 0 && !excl.has(x.id)) list.push({ x: x, depth: depth });
      (x.children || []).forEach(function (c) { walk(c, x.unitId > 0 ? depth + 1 : depth); });
    })(S.root, 0);
    var current = parentNode(n);
    function items(q) {
      q = (q || '').toLowerCase();
      return '<button type="button" class="orb-mv' + (!current || !(current.unitId > 0) ? ' on' : '') + '" data-to="0"><b>' + esc(t('f_parent_root')) + '</b></button>' + list.filter(function (it) {
        return !q || (it.x.label + ' ' + (it.x.unitCode || '')).toLowerCase().indexOf(q) !== -1;
      }).map(function (it) {
        return '<button type="button" class="orb-mv' + (current && current.id === it.x.id ? ' on' : '') + '" data-to="' + it.x.unitId + '" style="padding-left:' + (12 + (q ? 0 : it.depth * 14)) + 'px"><span class="orb-sym orb-sym--sm">' +
          symHtml(it.x, 10, true) + '</span><b>' + esc(it.x.label) + '</b><small class="orb-mono">' + esc(it.x.unitCode || '') + '</small></button>';
      }).join('');
    }
    openModal({
      html: function () {
        return '<h2>' + esc(t('move_title', { name: n.label })) + '</h2><p>' + esc(t('move_d')) + '</p><input class="orb-in" id="orb-mv-q" autofocus placeholder="' + esc(t('move_search')) + '">' +
          '<div class="orb-mvl" id="orb-mvl">' + items('') + '</div><div class="orb-acts"><button type="button" class="orb-btn" data-mclose>' + esc(t('cancel')) + '</button></div>';
      },
      onInput: function (e) { if (e.target.id === 'orb-mv-q') $('#orb-mvl').innerHTML = items(e.target.value); },
      onClick: function (e) {
        var b = e.target.closest('[data-to]');
        if (!b) return;
        closeModal(true);
        moveUnit(unitId, parseInt(b.dataset.to, 10) || 0).catch(function () {});
      },
      onKey: function (e) { if (e.key === 'Enter' && e.target.id === 'orb-mv-q') { var f = $('#orb-mvl [data-to]:not(.on)', mdl); if (f) f.click(); } }
    });
  }

  /* ---- suppression ---- */
  function openDelete(unitId) {
    var n = nodeOfUnit(unitId);
    if (!canEdit(n)) return;
    var code = n.unitCode || n.label;
    openModal({
      html: function () {
        return '<div class="orb-k orb-danger">' + esc(t('menu_delete').replace('…', '')) + '</div><h2>' + esc(t('delete_title', { name: n.label })) + '</h2><p>' + esc(t('delete_d')) + '</p>' +
          fl(t('delete_confirm', { code: '<span class="orb-code-chip">' + esc(code) + '</span>' }), '<input class="orb-in orb-mono" id="orb-dcf" autocomplete="off" autofocus>', '', true) +
          '<div class="orb-acts"><button type="button" class="orb-btn" data-mclose>' + esc(t('cancel')) + '</button><button type="button" class="orb-btn" data-archive>' + esc(t('archive_submit')) +
          '</button><button type="button" class="orb-btn orb-btn--red" id="orb-dgo" disabled>' + esc(t('delete_submit')) + '</button></div>';
      },
      onInput: function (e) { if (e.target.id === 'orb-dcf') $('#orb-dgo').disabled = e.target.value.trim() !== code; },
      onClick: function (e) {
        if (e.target.closest('[data-archive]')) {
          closeModal(true);
          structure('archive', { unit_id: unitId }, { toast: t('t_archived'), apply: function () { var x = nodeOfUnit(unitId); if (x) { x.adminStatus = 'archived'; x.adminStatusLabel = statusLabel('archived'); } } }).catch(function () {});
          return;
        }
        if (e.target.id === 'orb-dgo' && !e.target.disabled) {
          closeModal(true);
          if (S.sel === n.id) S.sel = S.parent.get(n.id) || null;
          structure('delete', { unit_id: unitId }, {
            toast: t('t_deleted'),
            apply: function () { var x = nodeOfUnit(unitId), p = parentNode(x); if (p) p.children = p.children.filter(function (c) { return c.id !== x.id; }); }
          }).catch(function () {});
        }
      }
    });
  }

  /* ---- invitation (gestionnaires de la communauté) ---- */
  function openInvite() {
    var tpl = $('#orb-invite');
    if (!tpl || !B.hub) return;
    var form = tpl.firstElementChild;
    openModal({
      lg: true,
      html: function () { return '<div class="orb-invite-host"></div>'; },
      init: function () { $('.orb-invite-host', mdl).appendChild(form); },
      onClose: function () { tpl.appendChild(form); }
    });
  }

  /* ---- aide clavier ---- */
  function openShortcuts() {
    var rows = [['/', 'kb_search'], ['Entrée', 'kb_next'], ['← ↑ → ↓', 'kb_nav'], ['E', 'kb_edit'], ['F2', 'kb_rename'], ['N', 'kb_new'], ['Suppr', 'kb_delete'],
      ['Maj + F10', 'kb_menu'], ['Ctrl + Z / Ctrl + Y', 'kb_undo'], ['0', 'kb_fit'], ['+ / −', 'kb_zoom'], ['F', 'kb_full'], ['Échap', 'kb_close']];
    openModal({
      html: function () {
        return '<h2>' + esc(t('shortcuts')) + '</h2><dl class="orb-kbd">' + rows.map(function (r) { return '<dt><kbd>' + esc(r[0]) + '</kbd></dt><dd>' + esc(t(r[1])) + '</dd>'; }).join('') +
          '</dl><div class="orb-acts"><button type="button" class="orb-btn orb-btn--dark" data-mclose>' + esc(t('close')) + '</button></div>';
      }
    });
  }

  /* ------------------------------------------------------------------ renommage sur place */
  function inlineRename(key) {
    var n = node(key);
    if (!canEdit(n)) return;
    if (S.view !== 'tree') setView('tree');
    select(key);
    var el = cards.get(key);
    var b = el && $('[data-name]', el);
    if (!b) return;
    var input = document.createElement('input');
    input.className = 'orb-inline-name';
    input.value = apiValue(n, 'name');
    input.maxLength = 255;
    input.setAttribute('aria-label', t('f_name'));
    b.replaceWith(input);
    S.inline = true;
    input.focus();
    input.select();
    var done = false;
    function finish(save) {
      if (done) return;
      done = true;
      S.inline = false;
      var v = input.value.trim();
      cardSig.delete(key);
      if (save && v && v !== apiValue(n, 'name')) patchUnit(n.unitId, { name: v }).catch(function () {});
      else render();
      var c = cards.get(key);
      if (c) c.focus({ preventScroll: true });
    }
    input.addEventListener('keydown', function (e) {
      e.stopPropagation();
      if (e.key === 'Enter') { e.preventDefault(); finish(true); }
      if (e.key === 'Escape') { e.preventDefault(); finish(false); }
    });
    input.addEventListener('blur', function () { finish(true); });
    input.addEventListener('pointerdown', function (e) { e.stopPropagation(); });
    input.addEventListener('dblclick', function (e) { e.stopPropagation(); });
  }

  /* ------------------------------------------------------------------ interactions sur le canevas */
  var pointers = new Map();
  var drag = null;
  var pinch = null;
  cv.addEventListener('pointerdown', function (e) {
    if (e.button !== 0 && e.pointerType === 'mouse') return;
    if (e.target.closest('button, input, a, select, textarea')) return;
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (pointers.size === 2) {
      var pts = Array.from(pointers.values());
      pinch = { d: Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y), z: S.z };
      drag = null;
      return;
    }
    var card = e.target.closest('.orb-nd');
    var key = card ? card.dataset.key : null;
    drag = { x: e.clientX, y: e.clientY, ox: S.x, oy: S.y, moved: false, key: key, mode: key && canEdit(node(key)) && e.pointerType === 'mouse' ? 'card' : 'pan', id: e.pointerId };
  });
  addEventListener('pointermove', function (e) {
    if (pointers.has(e.pointerId)) pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (pinch && pointers.size === 2) {
      var pts = Array.from(pointers.values());
      var d = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
      var r = cv.getBoundingClientRect();
      zoomAt((pinch.z * d / pinch.d) / S.z, (pts[0].x + pts[1].x) / 2 - r.left, (pts[0].y + pts[1].y) / 2 - r.top);
      return;
    }
    if (!drag || drag.id !== e.pointerId) return;
    var dx = e.clientX - drag.x, dy = e.clientY - drag.y;
    if (!drag.moved && Math.hypot(dx, dy) > 6) {
      drag.moved = true;
      if (drag.mode === 'card') startCardDrag(e); else cv.classList.add('pan');
    }
    if (!drag.moved) return;
    if (drag.mode === 'pan') { S.x = drag.ox + dx; S.y = drag.oy + dy; scheduleT(); }
    else moveCardDrag(e);
  });
  function endPointer(e) {
    pointers.delete(e.pointerId);
    if (pointers.size < 2) pinch = null;
    if (!drag || drag.id !== e.pointerId) return;
    if (drag.moved) {
      cv.dataset.justPanned = '1';
      setTimeout(function () { delete cv.dataset.justPanned; }, 0);
      if (drag.mode === 'card') endCardDrag(e.type !== 'pointercancel');
    }
    cv.classList.remove('pan');
    drag = null;
  }
  addEventListener('pointerup', endPointer);
  addEventListener('pointercancel', endPointer);

  var ghost = null;
  function startCardDrag(e) {
    var n = node(drag.key);
    ghost = document.createElement('div');
    ghost.className = 'orb-ghost';
    ghost.innerHTML = '<span class="orb-sym orb-sym--sm">' + symHtml(n, 12) + '</span>' + esc(n.label);
    document.body.appendChild(ghost);
    var el = cards.get(drag.key);
    if (el) el.classList.add('dragging');
    rootEl.classList.add('is-dragging');
    moveCardDrag(e);
  }
  function dropTargetAt(x, y) {
    if (ghost) ghost.style.display = 'none';
    var el = document.elementFromPoint(x, y);
    if (ghost) ghost.style.display = '';
    var c = el && el.closest('#orb-world .orb-nd');
    if (!c) return null;
    var n = node(drag.key), tgt = node(c.dataset.key);
    if (!tgt || tgt.id === n.id || tgt.isOrbatPlaceholder) return null;
    if (descendants(n).some(function (d) { return d.id === tgt.id; })) return { key: tgt.id, bad: true };
    if (S.parent.get(n.id) === tgt.id) return null;
    return { key: tgt.id, bad: false };
  }
  function moveCardDrag(e) {
    if (!ghost) return;
    ghost.style.transform = 'translate(' + (e.clientX + 14) + 'px,' + (e.clientY + 10) + 'px)';
    var tgt = dropTargetAt(e.clientX, e.clientY);
    cards.forEach(function (el, key) {
      el.classList.toggle('drop-ok', !!tgt && !tgt.bad && tgt.key === key);
      el.classList.toggle('drop-bad', !!tgt && tgt.bad && tgt.key === key);
    });
    drag.target = tgt;
  }
  function endCardDrag(commit) {
    var tgt = drag.target;
    if (ghost) { ghost.remove(); ghost = null; }
    rootEl.classList.remove('is-dragging');
    cards.forEach(function (el) { el.classList.remove('dragging', 'drop-ok', 'drop-bad'); });
    if (!commit || !tgt) return;
    if (tgt.bad) { toast(t('t_cycle'), null, true); return; }
    var n = node(drag.key), to = node(tgt.key);
    moveUnit(n.unitId, to.unitId > 0 ? to.unitId : 0).catch(function () {});
  }

  cv.addEventListener('wheel', function (e) {
    if (e.target.closest('.orb-hud')) return;
    e.preventDefault();
    var r = cv.getBoundingClientRect();
    if (e.shiftKey || Math.abs(e.deltaX) > Math.abs(e.deltaY)) {
      S.x -= e.shiftKey ? e.deltaY : e.deltaX;
      if (!e.shiftKey) S.y -= e.deltaY;
      scheduleT();
      return;
    }
    zoomAt(Math.exp(-e.deltaY * (e.ctrlKey ? 0.01 : 0.0015)), e.clientX - r.left, e.clientY - r.top);
  }, { passive: false });

  cv.addEventListener('dblclick', function (e) {
    var c = e.target.closest('.orb-nd');
    if (!c) { fit(); return; }
    var n = node(c.dataset.key);
    if (e.target.closest('[data-name]') && canEdit(n)) { inlineRename(n.id); return; }
    if (canEdit(n)) openEdit(n.unitId, 'identity');
  });
  $('#orb-dir').addEventListener('dblclick', function (e) {
    var c = e.target.closest('.orb-nd');
    if (c && canEdit(node(c.dataset.key))) openEdit(node(c.dataset.key).unitId, 'identity');
  });

  rootEl.addEventListener('click', function (e) {
    var tg = e.target;
    if (cv.dataset.justPanned) return;
    var b;
    if ((b = tg.closest('[data-tg]'))) { toggleCollapse(b.closest('.orb-nd').dataset.key); return; }
    if ((b = tg.closest('[data-menu]'))) { var r = b.getBoundingClientRect(); var k = b.closest('.orb-nd').dataset.key; select(k); showMenu(unitMenu(node(k)), r.right - 230, r.bottom + 6); return; }
    if ((b = tg.closest('[data-menu-dr]'))) { var r2 = b.getBoundingClientRect(); showMenu(unitMenu(node(S.sel)), r2.right - 230, r2.bottom + 6); return; }
    if ((b = tg.closest('[data-go]'))) { e.preventDefault(); if (S.view === 'sheet' || S.view === 'directory') { select(b.dataset.go); } else select(b.dataset.go, true); return; }
    if (tg.closest('[data-close]')) { S.sel = null; render(); return; }
    if ((b = tg.closest('[data-edit]'))) { openEdit(node(S.sel).unitId, b.dataset.edit); return; }
    if (tg.closest('[data-add]')) { openCreate(node(S.sel).unitId); return; }
    if (tg.closest('[data-post-new]') && tg.closest('#orb-dr')) { openPostForm(node(S.sel).unitId, 0); return; }
    var post = tg.closest('#orb-dr [data-billet]');
    if (post && !tg.closest('a')) { var pr = post.getBoundingClientRect(); var items = postMenu(node(S.sel), post.dataset.billet); if (items.length) showMenu(items, pr.left + 24, pr.bottom + 4); return; }
    if ((b = tg.closest('[data-st]'))) { toggleStatus(b.dataset.st); return; }
    if ((b = tg.closest('#orb-vw [data-v]'))) { setView(b.dataset.v); return; }
    var c = tg.closest('.orb-nd');
    if (c && !c.classList.contains('pv')) { select(c.dataset.key); return; }
    if (tg.closest('#orb-cv') && S.sel) { S.sel = null; render(); }
  });
  document.addEventListener('mousedown', function (e) { if (!e.target.closest('#orb-cm')) hideMenu(); });
  rootEl.addEventListener('change', function (e) {
    var s = e.target.closest('[data-sheet]');
    if (!s) return;
    var tr = s.closest('tr');
    var n = node(tr.dataset.key);
    if (!canEdit(n)) return;
    var f = {};
    f[s.dataset.sheet] = s.value;
    patchUnit(n.unitId, f).catch(function () {});
  });

  function toggleCollapse(key) {
    if (S.collapsed.has(key)) S.collapsed.delete(key); else S.collapsed.add(key);
    S.ver++;
    render();
  }
  function expandAll() { S.collapsed.clear(); S.ver++; render(); fit(); }
  function collapseAll() {
    S.collapsed.clear();
    S.byKey.forEach(function (n) { if (n !== S.root && kids(n).length) S.collapsed.add(n.id); });
    S.ver++;
    render();
    fit();
  }
  function toggleStatus(id) {
    if (S.statuses.has(id)) S.statuses.delete(id); else S.statuses.add(id);
    if (!S.statuses.size) S.statuses.add(id);
    S.ver++;
    if (B.canManage && !S.previewAs) render(); else reloadRoster();
    if (S.view === 'tree') fit();
  }

  /* ------------------------------------------------------------------ recherche */
  var q = $('#orb-q');
  function runSearch() {
    S.q = q.value.trim();
    S.hits = [];
    if (S.q) {
      (function walk(n) { if (n.unitId > 0 && shown(n) && matches(n)) S.hits.push(n.id); (n.children || []).filter(shown).forEach(walk); })(S.root || { children: [] });
    }
    S.hitIdx = -1;
    var qn = $('#orb-qn');
    if (qn) qn.textContent = S.q ? tn(S.hits.length, 'results_one', 'results_many') : '';
    S.dirtyViews = true;
    applyStates();
    if (S.view !== 'tree') { if (S.view === 'directory') renderDirectory(); else renderSheet(); }
  }
  var qT;
  q.addEventListener('input', function () { clearTimeout(qT); qT = setTimeout(runSearch, 90); });
  q.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      runSearch();
      if (!S.hits.length) return;
      S.hitIdx = (S.hitIdx + 1) % S.hits.length;
      var k = S.hits[S.hitIdx];
      expandTo(k);
      select(k, true);
      q.focus();
    }
    if (e.key === 'Escape') { q.value = ''; runSearch(); q.blur(); }
  });

  /* ------------------------------------------------------------------ clavier */
  function siblings(key) {
    var p = node(S.parent.get(key));
    return p ? kids(p) : [];
  }
  document.addEventListener('keydown', function (e) {
    if (M) {
      if (e.key === 'Escape') { e.preventDefault(); closeModal(); return; }
      if (M.onKey) M.onKey(e);
      return;
    }
    if (cm.classList.contains('on')) return;
    if (!rootEl.contains(document.activeElement) && document.activeElement !== document.body) return;
    var typing = isTyping();
    var mod = e.ctrlKey || e.metaKey;
    if (mod && !typing && (e.key === 'z' || e.key === 'Z')) { e.preventDefault(); if (e.shiftKey) redo(); else undo(); return; }
    if (mod && !typing && (e.key === 'y' || e.key === 'Y')) { e.preventDefault(); redo(); return; }
    if (e.key === 'Escape') {
      if (typing) { document.activeElement.blur(); return; }
      if (rootEl.classList.contains('is-full')) { toggleFull(); return; }
      if (S.sel) { S.sel = null; render(); }
      return;
    }
    if (typing || mod || e.altKey) return;
    var n = S.sel ? node(S.sel) : null;
    if (e.key === '/') { e.preventDefault(); q.focus(); q.select(); return; }
    if (e.key === '?') { e.preventDefault(); openShortcuts(); return; }
    if (e.key === '0') { fit(); return; }
    if (e.key === '+' || e.key === '=') { zoomAt(1.2, availW() / 2, cv.clientHeight / 2); return; }
    if (e.key === '-') { zoomAt(1 / 1.2, availW() / 2, cv.clientHeight / 2); return; }
    if (e.key === 'f' || e.key === 'F') { toggleFull(); return; }
    if ((e.key === 'ContextMenu' || (e.shiftKey && e.key === 'F10')) && n) {
      e.preventDefault();
      var el = cards.get(n.id);
      var r = el ? el.getBoundingClientRect() : { left: 100, top: 100, bottom: 120 };
      showMenu(unitMenu(n), r.left + 20, r.top + 30);
      return;
    }
    if (!n) {
      if (/^Arrow/.test(e.key) && S.root) { e.preventDefault(); select(S.root.id); }
      return;
    }
    if (e.key === 'e' || e.key === 'E' || e.key === 'Enter') { if (canEdit(n)) { e.preventDefault(); openEdit(n.unitId, 'identity'); } return; }
    if (e.key === 'F2') { e.preventDefault(); inlineRename(n.id); return; }
    if (e.key === 'n' || e.key === 'N') { if (B.canManage && !n.isOrbatPlaceholder) { e.preventDefault(); openCreate(n.unitId > 0 ? n.unitId : 0); } return; }
    if (e.key === 'Delete') { if (canEdit(n)) openDelete(n.unitId); return; }
    if (e.key === 'ArrowUp') { e.preventDefault(); var p = S.parent.get(n.id); if (p) select(p); return; }
    if (e.key === 'ArrowDown') { e.preventDefault(); if (S.collapsed.has(n.id)) toggleCollapse(n.id); var k = kids(n); if (k.length) select(k[Math.floor((k.length - 1) / 2)].id); return; }
    if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
      e.preventDefault();
      var sib = siblings(n.id);
      var i = sib.map(function (x) { return x.id; }).indexOf(n.id);
      var j = i + (e.key === 'ArrowLeft' ? -1 : 1);
      if (sib[j]) select(sib[j].id);
    }
  });

  /* ------------------------------------------------------------------ barre d’outils */
  function toggleFull() {
    rootEl.classList.toggle('is-full');
    document.documentElement.classList.toggle('orb-full-lock', rootEl.classList.contains('is-full'));
    sizeRoot();
    setTimeout(function () { fit(false); }, 30);
  }
  $('#orb-zi').addEventListener('click', function () { zoomAt(1.2, availW() / 2, cv.clientHeight / 2); });
  $('#orb-zo').addEventListener('click', function () { zoomAt(1 / 1.2, availW() / 2, cv.clientHeight / 2); });
  $('#orb-zf').addEventListener('click', function () { fit(); });
  $('#orb-exp').addEventListener('click', expandAll);
  $('#orb-col').addEventListener('click', collapseAll);
  $('#orb-fs').addEventListener('click', toggleFull);
  $('#orb-help').addEventListener('click', openShortcuts);
  var newBtn = $('#orb-new');
  if (newBtn) newBtn.addEventListener('click', function () { var n = S.sel ? node(S.sel) : null; openCreate(n && n.unitId > 0 ? n.unitId : (S.root && S.root.unitId > 0 ? S.root.unitId : 0)); });
  var emptyNew = $('#orb-empty-new');
  if (emptyNew) emptyNew.addEventListener('click', function () { openCreate(0); });
  var inviteBtn = $('#orb-invite-btn');
  if (inviteBtn) inviteBtn.addEventListener('click', openInvite);
  var asSel = $('#orb-as');
  if (asSel) asSel.addEventListener('change', function () { S.previewAs = asSel.value; reloadRoster().then(function () { fit(false); }); });
  var langSel = $('#orb-lang');
  var exportLink = $('#orb-export');
  if (langSel && exportLink) langSel.addEventListener('change', function () {
    var base = exportLink.dataset.base;
    exportLink.href = langSel.value ? base + (base.indexOf('?') >= 0 ? '&' : '?') + 'lang=' + encodeURIComponent(langSel.value) : base;
  });

  /* ------------------------------------------------------------------ dimensions et démarrage */
  function sizeRoot() {
    if (rootEl.classList.contains('is-full')) { rootEl.style.height = ''; return; }
    var top = rootEl.getBoundingClientRect().top + window.scrollY;
    rootEl.style.height = Math.max(560, window.innerHeight - Math.max(0, top - window.scrollY)) + 'px';
  }
  var rt;
  addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(function () { sizeRoot(); if (S.view === 'tree') fit(false); }, 120); });

  function buildSprite() {
    var host = document.createElement('div');
    host.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute" aria-hidden="true">' +
      // Chef : cadre d’unité plein, hampe de poste de commandement (APP-6) et étoile de commandement.
      '<symbol id="orb-i-chef" viewBox="0 0 24 24"><rect x="5" y="2.5" width="16" height="11" rx="1.2" fill="currentColor"/>' +
      '<path d="M5 13.5V22" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>' +
      '<path d="M13 4.6l1.3 2.7 2.9.4-2.1 2 .5 2.9-2.6-1.4-2.6 1.4.5-2.9-2.1-2 2.9-.4z" fill="var(--orb-card,#0f1412)"/></symbol>' +
      // Adjoint : même cadre au trait, moitié pleine (second dans la chaîne de commandement).
      '<symbol id="orb-i-adj" viewBox="0 0 24 24"><rect x="5.9" y="3.4" width="14.2" height="9.2" rx="1" fill="none" stroke="currentColor" stroke-width="1.8"/>' +
      '<path d="M5.9 12.6L20.1 3.4v9.2z" fill="currentColor" opacity=".55"/>' +
      '<path d="M5.9 12.6V22" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-dasharray="2.6 2.2"/></symbol></svg>';
    document.body.appendChild(host.firstChild);
  }

  function focusFromQuery() {
    var p = new URLSearchParams(location.search);
    var id = parseInt(p.get('unit') || p.get('unite') || '', 10) || 0;
    var openKind = B.open || p.get('ouvrir') || '';
    var key = id ? S.keyOfUnit.get(id) : null;
    if (key) {
      var n = node(key);
      if (n && !shown(n)) { S.statuses.add(n.adminStatus); S.ver++; render(); }
      select(key, true);
      var el = cards.get(key);
      if (el) { el.classList.add('flash'); setTimeout(function () { el.classList.remove('flash'); }, 2200); }
      if (p.get('modifier') === '1' && canEdit(n)) openEdit(id, 'identity');
    }
    if (openKind === 'membre') openInvite();
    else if (openKind === 'unite' || openKind === 'groupe' || openKind === 'equipe') {
      var base = S.sel ? node(S.sel) : S.root;
      openCreate(base && base.unitId > 0 ? base.unitId : 0, openKind === 'groupe' ? 'group' : (openKind === 'equipe' ? 'team' : ''));
    }
  }

  // Menu, fenêtres et notifications au niveau du document : jamais piégés par un parent transformé ou rogné.
  [cm, ov, $('#orb-toast')].forEach(function (el) { if (el && el.parentNode !== document.body) document.body.appendChild(el); });
  buildSprite();
  sizeRoot();
  setRoster(B.roster);
  S.lastSnap = JSON.stringify(B.roster);
  if (S.root) {
    // Arbres très larges : n’afficher d’emblée que les trois premiers niveaux.
    if (S.byKey.size > 120) S.byKey.forEach(function (n, key) { if (ancestors(key).length >= 2 && kids(n).length) S.collapsed.add(key); });
    render();
    // Petit écran : partir du commandement à taille lisible plutôt que d’un arbre entier minuscule.
    if (cv.clientWidth < 700) centerOn(S.root.id, false); else fit(false);
    focusFromQuery();
  } else {
    renderEmpty();
    if (B.open && B.canManage) focusFromQuery();
  }
  if (B.canManage) ensureOptions().then(function () { if (S.view === 'sheet') renderSheet(); });
  loadAdequacy();
  // milsymbol (850 Ko) arrive après le premier affichage : les cartes montrent d’abord un repère, puis le symbole.
  if (window.requestIdleCallback) window.requestIdleCallback(loadMilsymbol, { timeout: 1200 }); else setTimeout(loadMilsymbol, 200);

  // Rafraîchissement discret (travail à plusieurs) : jamais pendant une saisie, un glisser ou une fenêtre ouverte.
  setInterval(function () {
    if (!B.canManage || document.hidden || S.pending || M || drag || S.inline || !S.root) return;
    reloadRoster(true);
  }, 32000);

})();
