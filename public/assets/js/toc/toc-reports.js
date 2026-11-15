/* Poste TOC web : panneau Rapports. Liste de type ticketing des rapports du jeu (BDA, comptes rendus, SITREP, 9-line,
 * autres) : familles, recherche, filtres (période, carte, unité ou émetteur, priorité, statut), tri, non lus avec
 * pastille. Un clic ouvre la fiche dans le tiroir de la console (rendu propre à chaque type), avec accusé de réception,
 * lu / non lu, jonction à un plan et à une phase, PDF et lien. Les épingles de la carte viennent de pins().
 * Données : /atak/toc/api/reports (lecture et actions du poste, droit « tenir le poste TOC web »). Rafraîchi quand
 * l'empreinte du canal de synchro de la salle change (onStamp). Les textes sont des phrases entières, traduites par le
 * dictionnaire du site. */
window.TocReports = (function () {
  'use strict';
  var FAMILIES = [['bda', 'BDA'], ['cr', 'Comptes rendus'], ['sitrep', 'SITREP'], ['nineline', '9-line'], ['other', 'Autres']];
  var PERIODS = [['1h', 'Dernière heure'], ['6h', '6 dernières heures'], ['24h', '24 dernières heures'], ['7d', '7 derniers jours'], ['14d', '14 derniers jours'], ['all', 'Tout l’historique']];
  var PRIORITIES = [['FLASH', 'FLASH'], ['IMMEDIATE', 'Immédiat'], ['PRIORITY', 'Prioritaire'], ['ROUTINE', 'Routine']];
  var SORTS = [['recent', 'Plus récents d’abord'], ['unread', 'Non lus d’abord'], ['priority', 'Priorité'], ['oldest', 'Plus anciens d’abord']];
  var PRIO_TONE = { FLASH: 'red', IMMEDIATE: 'amber', PRIORITY: 'blue', ROUTINE: 'grey' };
  var PRIO_COLOR = { FLASH: '#ff5f56', IMMEDIATE: '#f5b841', PRIORITY: '#62b0f5', ROUTINE: '#98a4ae' };
  var CLASS_COLOR = { UNCLASSIFIED: '#007a33', CUI: '#502878', CONFIDENTIAL: '#0033a0', 'SECRET//NOFORN': '#c8102e', 'TOP SECRET//SCI//NOFORN': '#ff671f' };
  // Étapes d'un 9-line : état du dossier => rang (le rang courant est coloré).
  var STEPS = {
    cas: [['SUBMITTED', 'Reçu'], ['ACKNOWLEDGED', 'Accusé'], ['INBOUND', 'En approche'], ['CLEARED_HOT', 'Feu autorisé'], ['ENGAGED', 'Engagé'], ['BDA_PENDING', 'BDA attendu'], ['COMPLETE', 'Terminé']],
    medevac: [['REQUESTED', 'Demandé'], ['LAUNCHED', 'Appareil lancé'], ['ON_SCENE', 'Sur zone'], ['COMPLETE', 'Terminé']]
  };
  var ERRORS = {
    csrf: 'Session expirée : rechargez la page.', rate_limited: 'Trop d’actions d’affilée : attendez quelques secondes.', forbidden: 'Ce poste demande le droit « tenir le poste TOC web ».',
    not_found: 'Ce rapport n’est plus disponible ou dépasse votre habilitation.', plan_not_found: 'Plan introuvable.', read_only: 'Ce plan est en lecture seule pour vous.',
    no_position: 'Ce rapport n’a pas de position : rien à placer sur le plan.', phase_not_found: 'Phase introuvable dans ce plan.', locked: 'Un repère verrouillé du plan empêche la jonction.',
    pdf_unavailable: 'Le PDF n’est pas disponible pour le moment.', schema_not_ready: 'Le suivi des rapports n’est pas encore installé sur le site.'
  };

  /** Libellé d'interface : un texte entier, posé tel quel (le dictionnaire du site le traduit à l'affichage). */
  var lbl = function (s) { return s; };
  var esc = function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var BLOCK = /█+/g;
  /** Texte du jeu : caviardage (blocs pleins) en pastille noire de largeur fixe, jamais le texte masqué lui-même. */
  /** Même texte sans bloc plein (aria-label, titre provisoire) : le caviardage devient « … ». */
  function plain(s) { return String(s == null ? '' : s).replace(BLOCK, '…'); }
  function rich(s) { return esc(s).replace(BLOCK, '<span class="rp-redact" aria-label="' + lbl('passage caviardé') + '"></span>').replace(/\n/g, '<br>'); }

  function create(ctx) {
    var T = window.TocTracks;
    var store = (function () { try { return window.localStorage; } catch (e) { return null; } })();
    var PREF = 'athena:toc:reports:' + (ctx.userId || 0);
    var saved = {};
    try { saved = JSON.parse((store && store.getItem(PREF)) || '{}') || {}; } catch (e) { saved = {}; }
    var f = {
      family: saved.family || '', q: '', unread: !!saved.unread, period: saved.period || '24h', allMaps: !!saved.allMaps, unit: '', priority: saved.priority || '',
      status: saved.status || '', sort: saved.sort || 'recent', open: false
    };
    var st = { items: [], counts: null, hidden: 0, readState: 'ok', truncated: false, loaded: false, loading: false, error: '', stamp: '', localRead: {}, key: null, detail: null, tab: 'rapport', plans: null };
    var dead = false;
    var seq = 0;

    function savePrefs() {
      try { if (store) store.setItem(PREF, JSON.stringify({ family: f.family, unread: f.unread, period: f.period, allMaps: f.allMaps, priority: f.priority, status: f.status, sort: f.sort })); } catch (e) {}
    }
    function isRead(it) { return !!(it.read || st.localRead[it.key]); }
    function unreadCount() { return st.items.filter(function (it) { return !isRead(it); }).length; }
    function famLabel(id) { var r = FAMILIES.filter(function (x) { return x[0] === id; })[0]; return r ? r[1] : id; }
    function terrainLabel(id) { var t = (ctx.terrains() || []).filter(function (x) { return x.id === id; })[0]; return t ? t.label : ''; }
    function activeFilters() { return (f.unit ? 1 : 0) + (f.priority ? 1 : 0) + (f.status ? 1 : 0) + (f.period !== '24h' ? 1 : 0) + (f.allMaps ? 1 : 0); }
    function ago(it) {
      var s = Math.max(0, Math.round(ctx.now() - it.ts));
      if (!it.ts) return '';
      if (s < 90) return 'à l’instant';
      if (s < 3600) return Math.round(s / 60) + ' min';
      if (s < 86400) return Math.floor(s / 3600) + ' h ' + ('0' + Math.round((s % 3600) / 60)).slice(-2);
      return Math.floor(s / 86400) + ' j';
    }

    /* ---------- Données ---------- */
    function query() {
      return '?map=' + (f.allMaps ? 'all' : ctx.mapId()) + '&period=' + encodeURIComponent(f.period) + '&limit=300';
    }
    function load(silent) {
      if (dead) return Promise.resolve();
      var mine = ++seq;
      st.loading = !silent && !st.loaded;
      if (st.loading) refresh();
      return ctx.api('/atak/toc/api/reports' + query()).then(function (j) {
        if (dead || mine !== seq || (j && j.notModified)) return;
        st.loading = false;
        if (!j || !j.ok) { st.error = ERRORS[j && j.error] || 'Rapports indisponibles pour le moment.'; st.loaded = true; refresh(); return; }
        st.error = '';
        st.items = j.reports || [];
        st.counts = j.counts || null;
        st.hidden = j.hidden || 0;
        st.readState = j.readState || 'ok';
        st.truncated = !!j.truncated;
        st.loaded = true;
        if (st.readState === 'ok') st.localRead = {};
        refresh();
        if (ctx.onData) ctx.onData(st.items);
      }).catch(function () { st.loading = false; st.error = 'Réseau indisponible : les rapports ne peuvent pas être relus.'; st.loaded = true; refresh(); });
    }
    function onStamp(stamp) {
      if (!stamp && st.stamp) return;
      if (stamp === st.stamp && st.loaded) return;
      st.stamp = stamp || '';
      load(true);
    }

    /* ---------- Filtres ---------- */
    function norm(s) { var x = String(s || '').toLowerCase(); return x.normalize ? x.normalize('NFD').replace(/[̀-ͯ]/g, '') : x; }
    function visible() {
      var q = norm(f.q), unit = norm(f.unit);
      var list = st.items.filter(function (it) {
        if (f.family && it.family !== f.family) return false;
        if (f.unread && isRead(it)) return false;
        if (f.priority && it.priority !== f.priority) return false;
        if (f.status === 'open' && it.closed) return false;
        if (f.status === 'closed' && !it.closed) return false;
        if (unit && norm(it.who + ' ' + it.unit).indexOf(unit) < 0) return false;
        if (q && norm([it.title, it.summary, it.who, it.unit, it.type_label, it.grid, it.ref].join(' ')).indexOf(q) < 0) return false;
        return true;
      });
      var recent = function (a, b) { return (b.ts - a.ts) || (b.id - a.id); };
      list.sort(f.sort === 'oldest' ? function (a, b) { return -recent(a, b); }
        : (f.sort === 'priority' ? function (a, b) { return (b.rank - a.rank) || recent(a, b); }
          : (f.sort === 'unread' ? function (a, b) { return (isRead(a) - isRead(b)) || recent(a, b); } : recent)));
      return list;
    }
    function familyCount(id) {
      return st.items.filter(function (it) { return !id || it.family === id; }).length;
    }
    function familyUnread(id) {
      return st.items.filter(function (it) { return (!id || it.family === id) && !isRead(it); }).length;
    }

    /* ---------- Panneau ---------- */
    function sym(it, size) {
      var col = PRIO_COLOR[it.priority] || '#98a4ae';
      return '<span class="rp-sym" style="color:' + col + '">' + T.iconSvg(it.icon || 'report', col, size || 24) + '</span>';
    }
    function pill(text, tone) { return '<span class="toc-pill' + (tone ? ' is-' + tone : '') + '">' + esc(text) + '</span>'; }
    function banner(it) {
      var c = it.classification || 'UNCLASSIFIED';
      var col = CLASS_COLOR[c] || '#007a33';
      var dark = c === 'UNCLASSIFIED' || c === 'CUI' || c === 'CONFIDENTIAL' || c === 'SECRET//NOFORN';
      return '<span class="rp-class" style="background:' + col + ';color:' + (dark ? '#fff' : '#000') + '">' + esc(c) + '</span>';
    }
    function item(it) {
      var unread = !isRead(it);
      return '<li class="rp-li"><button type="button" class="rp-item' + (unread ? ' is-unread' : '') + (it.key === st.key ? ' is-sel' : '') + '" data-rp-open="' + esc(it.key) + '" aria-label="' + esc([unread ? 'Non lu :' : '', it.type_label + ',', plain(it.title)].join(' ')) + '">' +
        '<span class="rp-lead">' + sym(it, 26) + (unread ? '<i class="rp-dot" aria-hidden="true"></i>' : '') + '</span>' +
        '<span class="rp-main"><span class="rp-title">' + rich(it.title) + '</span>' +
        (it.summary && it.summary !== it.title ? '<span class="rp-sum">' + rich(it.summary) + '</span>' : '') +
        '<span class="rp-meta"><b>' + esc(it.type_label) + '</b><span>' + esc(it.who) + (it.unit ? ' · ' + esc(it.unit) : '') + '</span><span class="mono">' + esc(it.t) + 'Z</span>' + (it.grid ? '<span class="mono">' + esc(it.grid) + '</span>' : '') + '</span></span>' +
        '<span class="rp-side">' + pill(it.priority_label, PRIO_TONE[it.priority]) + '<span class="rp-state' + (it.acked ? ' is-acked' : '') + '">' + esc(it.status_label) + '</span><span class="rp-ago">' + esc(ago(it)) + '</span></span></button></li>';
    }
    function results() {
      if (st.loading) return '<p class="toc-note">Chargement des rapports…</p>';
      if (st.error) return '<div class="toc-empty"><p>' + esc(st.error) + '</p><button type="button" class="toc-btn" data-rp-reload>Réessayer</button></div>';
      var list = visible();
      var h = '';
      if (!list.length) {
        var filtered = !!(f.family || f.q || f.unread || activeFilters());
        h = '<div class="toc-empty"><p>' + esc(filtered ? 'Aucun rapport ne correspond à ces filtres.' : 'Aucun rapport reçu sur cette période.') + '</p>' +
          (filtered ? '<button type="button" class="toc-btn" data-rp-reset>Effacer les filtres</button>' : '<p class="toc-note">BDA, comptes rendus, SITREP et 9-line envoyés des téléphones ATAK arrivent ici dès leur envoi.</p>') + '</div>';
      } else {
        h = '<ul class="rp-list" aria-label="Rapports">' + list.map(item).join('') + '</ul>';
        if (list.length >= 200) h += '<p class="toc-note">Les 200 premiers rapports sont affichés : affinez la recherche ou la période.</p>';
      }
      if (st.truncated) h += '<p class="toc-note">La période contient plus de 300 rapports : seuls les plus récents sont chargés.</p>';
      if (st.hidden) h += '<p class="toc-note rp-hidden"><b>' + esc(st.hidden) + '</b> <span>rapport(s) classifié(s) au-dessus de votre habilitation ne sont pas affichés.</span></p>';
      return h;
    }
    function options(list, cur) {
      return list.map(function (o) { return '<option value="' + esc(o[0]) + '"' + (o[0] === cur ? ' selected' : '') + '>' + esc(o[1]) + '</option>'; }).join('');
    }
    function panel() {
      var total = st.items.length, un = unreadCount();
      var h = '<div class="toc-sec"><h2>Rapports</h2><span class="toc-dots"><span class="toc-n"><b>' + total + '</b> <span>rapport(s)</span></span><span class="toc-n' + (un ? ' rp-unread-n' : '') + '"><b>' + un + '</b> <span>non lu(s)</span></span></span></div>';
      h += '<p class="rp-desc">Les BDA, comptes rendus, SITREP et 9-line reçus du terrain, à lire, accuser et rattacher au plan.</p>';
      if (st.readState === 'degraded') h += '<p class="rp-warn" role="status">Le suivi lu / non lu n’est pas encore installé sur le site (étape 4750 à lancer) : vos lectures ne sont conservées que dans cette fenêtre.</p>';
      h += '<div class="rp-fams" role="tablist" aria-label="Familles de rapports">' + [['', 'Tous']].concat(FAMILIES).map(function (x) {
        var n = familyCount(x[0]), u = familyUnread(x[0]);
        return '<button type="button" role="tab" class="rp-fam" aria-selected="' + (f.family === x[0]) + '" data-rp-fam="' + x[0] + '"><span>' + esc(x[1]) + '</span><b class="mono">' + n + '</b>' + (u ? '<i class="rp-fam-u" title="' + esc(u + ' non lu(s)') + '">' + u + '</i>' : '') + '</button>';
      }).join('') + '</div>';
      h += '<div class="rp-bar"><label class="toc-sr" for="rp-q">Chercher un rapport</label><input id="rp-q" name="rp-q" class="toc-input rp-q" data-rp-q type="search" value="' + esc(f.q) + '" placeholder="Chercher : indicatif, grille, texte…" autocomplete="off">' +
        '<button type="button" class="toc-btn rp-tog' + (f.unread ? ' is-on' : '') + '" data-rp-unread aria-pressed="' + f.unread + '">Non lus</button>' +
        '<button type="button" class="toc-btn rp-tog' + (f.open || activeFilters() ? ' is-on' : '') + '" data-rp-filters aria-expanded="' + f.open + '" aria-controls="rp-filters">Filtres' + (activeFilters() ? ' <b class="toc-count">' + activeFilters() + '</b>' : '') + '</button></div>';
      h += '<div class="rp-filters" id="rp-filters"' + (f.open ? '' : ' hidden') + '>' +
        '<label class="toc-field"><span>Période</span><select class="toc-select" data-rp-set="period">' + options(PERIODS, f.period) + '</select></label>' +
        '<label class="toc-field"><span>Carte</span><select class="toc-select" data-rp-set="allMaps"><option value="0"' + (f.allMaps ? '' : ' selected') + '>' + esc(terrainLabel(ctx.mapId()) || 'Ce terrain') + '</option><option value="1"' + (f.allMaps ? ' selected' : '') + '>Toutes les cartes</option></select></label>' +
        '<label class="toc-field"><span>Unité ou émetteur</span><input class="toc-input" name="rp-unit" data-rp-set="unit" value="' + esc(f.unit) + '" placeholder="ALPHA 1, TA1…" autocomplete="off"></label>' +
        '<label class="toc-field"><span>Priorité</span><select class="toc-select" data-rp-set="priority"><option value="">Toutes</option>' + options(PRIORITIES, f.priority) + '</select></label>' +
        '<label class="toc-field"><span>Statut</span><select class="toc-select" data-rp-set="status"><option value=""' + (f.status ? '' : ' selected') + '>Tous</option><option value="open"' + (f.status === 'open' ? ' selected' : '') + '>Ouverts</option><option value="closed"' + (f.status === 'closed' ? ' selected' : '') + '>Clos</option></select></label>' +
        '<label class="toc-field"><span>Tri</span><select class="toc-select" data-rp-set="sort">' + options(SORTS, f.sort) + '</select></label>' +
        '<div class="rp-filter-act"><button type="button" class="toc-btn" data-rp-reset>Effacer les filtres</button><button type="button" class="toc-btn" data-rp-readall' + (un ? '' : ' disabled') + '>Tout marquer comme lu</button></div></div>';
      return h + '<div class="rp-results" id="rp-results" aria-live="polite">' + results() + '</div>';
    }
    /** Redessine la liste seule (la saisie de la recherche garde le focus). */
    function refreshResults() {
      var box = $('#rp-results');
      if (box) box.innerHTML = results();
    }
    function refresh() {
      if (dead) return;
      var typing = document.activeElement && document.activeElement.hasAttribute && (document.activeElement.hasAttribute('data-rp-q') || document.activeElement.getAttribute('data-rp-set') === 'unit');
      if (ctx.isOpenTab()) { if (typing) refreshResults(); else ctx.renderPanel(); }
      ctx.renderBadges();
      if (st.key && st.detail) renderDetail();
    }

    /* ---------- Tiroir : fiche d'un rapport ---------- */
    function dlRow(label, value, tone, mono) {
      var v = String(value == null ? '' : value);
      return '<div class="rp-kv"><dt>' + esc(label) + '</dt><dd' + (mono ? ' class="mono"' : '') + '>' + (v === '' ? '<span class="rp-none">Non communiqué</span>' : (tone ? '<span class="toc-pill is-' + tone + '">' + esc(v) + '</span>' : rich(v))) + '</dd></div>';
    }
    function bdaCard(r) {
      var res = r.result || { code: 'unknown', label: 'Non communiqué', tone: 'grey' };
      var target = '', ord = '', coll = '', obs = '', ekia = '';
      (r.facts || []).forEach(function (x) {
        if (x.key === 'type' || x.key === 'target') target = target || x.value;
        if (x.key === 'ordnance' || x.key === 'munitions') ord = ord ? ord + ' · ' + x.value : x.value;
        if (x.key === 'collateral') coll = x.value;
        if (x.key === 'observer') obs = x.value;
        if (x.key === 'ekia') ekia = x.value;
      });
      return '<div class="rp-bda rp-bda-' + esc(res.code) + '"><div class="rp-bda-res"><span>Résultat</span><b>' + esc(res.label) + '</b></div><dl class="rp-bda-grid">' +
        dlRow('Cible', target) + dlRow('Munition ou arme', ord) + dlRow('Dommages collatéraux', coll) + dlRow('Observateur', obs || r.who) + dlRow('Ennemis tués (EKIA)', ekia) + dlRow('Heure', r.t ? r.t + 'Z · ' + r.dtg : r.dtg, '', true) + '</dl></div>';
    }
    function steps(r) {
      var kind = r.type === 'MEDEVAC' ? 'medevac' : 'cas';
      var list = STEPS[kind];
      var cur = -1;
      list.forEach(function (s, i) { if (s[0] === r.status) cur = i; });
      if (r.status === 'ABORTED' || r.status === 'CANCELLED') return '<p class="rp-steps-off">' + pill(r.status_label, 'red') + '</p>';
      return '<ol class="rp-steps" aria-label="Avancement">' + list.map(function (s, i) { return '<li class="' + (i < cur ? 'is-done' : (i === cur ? 'is-now' : '')) + '"' + (i === cur ? ' aria-current="step"' : '') + '><i aria-hidden="true"></i><span>' + esc(s[1]) + '</span></li>'; }).join('') + '</ol>';
    }
    function nineBody(r) {
      var h = steps(r) + '<ol class="rp-lines">' + (r.lines || []).map(function (ln) {
        return '<li><span class="rp-ln">' + ln.n + '</span><span class="rp-ll">' + esc(ln.label) + '</span><span class="rp-lv">' + (ln.value ? rich(ln.value) : '<span class="rp-none">Néant</span>') + '</span></li>';
      }).join('') + '</ol>';
      var g = r.geo;
      if (g && (g.fah || g.danger_close || g.laser || g.ordnance || g.aircraft)) {
        h += '<dl class="rp-dl">' + (g.aircraft ? dlRow('Appareil', g.aircraft) : '') + (g.ordnance ? dlRow('Munition', g.ordnance) : '') + (g.fah ? dlRow('Cap d’attaque (FAH)', g.fah + '°') : '') +
          (g.laser ? dlRow('Code laser', g.laser_code ? String(g.laser_code) : 'Oui', '', true) : '') + (g.danger_close ? dlRow('Danger close', 'Oui', 'red') : '') + '</dl>';
      }
      if (r.remarks) h += '<section class="rp-block"><h3>Remarques</h3><p>' + rich(r.remarks) + '</p></section>';
      return h;
    }
    function reportBody(r) {
      if (r.family === 'nineline') return nineBody(r);
      var h = r.family === 'bda' ? bdaCard(r) : '';
      var facts = (r.facts || []).filter(function (x) {
        if (r.family !== 'bda') return true;
        return ['type', 'target', 'ordnance', 'munitions', 'collateral', 'observer', 'ekia', 'result'].indexOf(x.key) < 0;
      });
      if (facts.length) h += '<dl class="rp-dl">' + facts.map(function (x) { return dlRow(x.label, x.value, x.tone); }).join('') + '</dl>';
      (r.blocks || []).forEach(function (b) { h += '<section class="rp-block"><h3>' + esc(b.label) + '</h3><p>' + rich(b.text) + '</p></section>'; });
      if ((r.extra || []).length) h += '<details class="rp-extra"' + (!facts.length && !(r.blocks || []).length ? ' open' : '') + '><summary>Autres informations</summary><dl class="rp-dl">' + r.extra.map(function (x) { return dlRow(x.label, x.value); }).join('') + '</dl></details>';
      if (!facts.length && !(r.blocks || []).length && !(r.extra || []).length) {
        h += '<section class="rp-block"><h3>Détail</h3><p>' + (r.text ? rich(r.text) : '<span class="rp-none">Ce rapport ne contient pas de détail.</span>') + '</p></section>';
      }
      if (r.remarks && !(r.blocks || []).some(function (b) { return b.key === 'remarks'; })) h += '<section class="rp-block"><h3>Remarques</h3><p>' + rich(r.remarks) + '</p></section>';
      if (r.redacted) h += '<p class="rp-warn">Des passages de ce rapport sont caviardés : ils ne sont pas lisibles avec votre habilitation.</p>';
      return h;
    }
    function photos(r) {
      if (!(r.photos || []).length) return r.has_photo ? '<p class="toc-note">Une photo est jointe au rapport en jeu : elle apparaît ici dès qu’elle est arrivée dans la photothèque.</p>' : '';
      return '<div class="rp-photos">' + r.photos.map(function (p) { return '<a href="' + esc(ctx.base + p.url) + '" target="_blank" rel="noopener"><img loading="lazy" alt="' + lbl('Photo jointe au rapport') + '" src="' + esc(ctx.base + p.url) + '"></a>'; }).join('') + '</div>';
    }
    function whereRow(r) {
      var pos = r.pos ? ctx.grid(r.pos) : '';
      var terr = terrainLabel(r.map_id);
      return '<div class="rp-where"><span class="rp-w-main"><b class="mono">' + esc(r.grid || pos || '—') + '</b>' + (terr ? '<span>' + esc(terr) + '</span>' : '') + (r.location ? '<span>' + rich(r.location) + '</span>' : '') + '</span>' +
        '<button type="button" class="toc-btn" data-rp-center' + (r.pos ? '' : ' disabled title="' + lbl('Ce rapport n’a pas de position') + '"') + '>' + lbl('Centrer la carte') + '</button></div>';
    }
    function followTab(r) {
      var h = '<dl class="rp-dl">' + dlRow('Statut', r.status_label) + dlRow('Accusé de réception', r.acked ? (r.acked_at ? 'Oui · ' + r.acked_at : 'Oui') : 'Pas encore') + dlRow('Lecture', isRead(r) ? 'Lu' : 'Non lu') +
        dlRow('Référence', r.ref || r.key, '', true) + (r.mission_key ? dlRow('Session de jeu', r.mission_key, '', true) : '') + '</dl>';
      h += '<section class="rp-block"><h3>Plans d’opération</h3>';
      if ((r.plans || []).length) {
        h += '<ul class="rp-plans">' + r.plans.map(function (p) { return '<li><a href="' + esc(ctx.base + '/atak/toc/plans/' + p.plan_id) + '">' + esc(p.title) + '</a>' + (p.phases.length ? '<span>' + esc(p.phases.join(', ')) + '</span>' : '') + '</li>'; }).join('') + '</ul>';
      } else h += '<p class="toc-note">Ce rapport n’est joint à aucun plan.</p>';
      h += '<div class="rp-attach"><label class="toc-field"><span>Plan</span><select class="toc-select" data-rp-plan><option value="">Choisir un plan…</option></select></label>' +
        '<label class="toc-field"><span>Phase</span><select class="toc-select" data-rp-phase disabled><option value="">Toutes les phases</option></select></label>' +
        '<button type="button" class="toc-btn is-primary" data-rp-attach disabled>Joindre au plan</button></div></section>';
      return h;
    }
    function actionBar(r) {
      var un = !isRead(r);
      return '<div class="rp-actions">' +
        '<button type="button" class="toc-btn is-primary" data-rp-ack' + (r.acked ? ' disabled' : '') + '>' + (r.acked ? 'Réception accusée' : 'Accuser réception') + '</button>' +
        '<a class="toc-btn" href="' + esc(ctx.base + '/atak/toc/reports/' + r.key + '/export.pdf') + '" data-rp-pdf>' + lbl('Exporter en PDF') + '</a>' +
        '<button type="button" class="toc-btn" data-rp-link>Copier le lien</button>' +
        '<button type="button" class="toc-btn" data-rp-toggle-read>' + (un ? 'Marquer comme lu' : 'Marquer comme non lu') + '</button></div><p class="toc-drawer-msg" role="status"></p>';
    }
    function detailHtml(r) {
      return '<div class="rp-chips">' + pill(r.priority_label, PRIO_TONE[r.priority]) + pill(r.status_label, r.closed ? 'accent' : (r.acked ? 'blue' : 'amber')) + banner(r) + (!isRead(r) ? pill('Non lu', 'red') : '') + (r.has_photo ? pill('Photo', 'blue') : '') + '</div>' +
        '<dl class="rp-head-grid">' + dlRow('Émetteur', r.who) + dlRow('Unité', r.unit) + dlRow('GDH', r.dtg, '', true) + '</dl>' + whereRow(r) +
        '<div class="rp-tabs" role="tablist" aria-label="Fiche du rapport"><button type="button" role="tab" class="rp-tab" data-rp-tab="rapport" aria-selected="' + (st.tab === 'rapport') + '">Rapport</button><button type="button" role="tab" class="rp-tab" data-rp-tab="suivi" aria-selected="' + (st.tab === 'suivi') + '">Suivi</button></div>' +
        '<div class="rp-pane" role="tabpanel">' + (st.tab === 'suivi' ? followTab(r) : reportBody(r) + photos(r)) + '</div>' + actionBar(r);
    }
    function renderDetail() {
      var body = ctx.drawerBody();
      if (!body || ctx.drawerId() !== 'rapport' || !st.detail) return;
      var scroll = body.scrollTop;
      body.innerHTML = detailHtml(st.detail);
      body.scrollTop = scroll;
      if (st.tab === 'suivi') loadPlans(body);
    }
    function loadPlans(body) {
      var sel = $('[data-rp-plan]', body);
      if (!sel) return;
      var fill = function (plans) {
        sel.innerHTML = '<option value="">Choisir un plan…</option>' + plans.map(function (p) { return '<option value="' + p.id + '">' + esc(p.title) + '</option>'; }).join('');
        if (!plans.length) sel.innerHTML = '<option value="">Aucun plan modifiable</option>';
      };
      if (st.plans) { fill(st.plans); return; }
      ctx.api('/atak/toc/api/reports/plans').then(function (j) { st.plans = (j && j.ok) ? j.plans : []; if (document.body.contains(sel)) fill(st.plans); }).catch(function () { st.plans = []; fill([]); });
    }
    function msg(text, isError) { ctx.drawerMessage(text, isError); }

    function open(key) {
      if (!/^(tr|nl)[-:]\d+$/.test(String(key))) return;
      key = String(key).replace(':', '-');
      st.key = key;
      st.tab = 'rapport';
      st.detail = null;
      var head = st.items.filter(function (x) { return x.key === key; })[0];
      ctx.openDrawer({
        id: 'rapport', title: head ? plain(head.title) : 'Rapport', sub: head ? [head.type_label, head.dtg].join(' · ') : 'Chargement du rapport…',
        body: '<p class="toc-note">Chargement du rapport…</p>',
        onClose: function () { st.key = null; st.detail = null; ctx.setHash({}); refresh(); }
      });
      ctx.setHash({ rapport: key });
      ctx.api('/atak/toc/api/reports/' + key).then(function (j) {
        if (st.key !== key) return;
        var body = ctx.drawerBody();
        if (!j || !j.ok) { if (body) body.innerHTML = '<div class="toc-empty"><p>' + esc(ERRORS[j && j.error] || 'Rapport indisponible.') + '</p></div>'; return; }
        st.detail = j.report;
        ctx.drawerTitle(j.report.title, [j.report.type_label, j.report.dtg].join(' · '));
        renderDetail();
        // Ouvrir la fiche vaut lecture.
        if (!isRead(j.report)) setRead(key, true, true);
      }).catch(function () { var b = ctx.drawerBody(); if (b) b.innerHTML = '<div class="toc-empty"><p>Réseau indisponible : le rapport n’a pas pu être chargé.</p></div>'; });
    }
    function patch(key, props) {
      st.items.forEach(function (x) { if (x.key === key) Object.keys(props).forEach(function (k) { x[k] = props[k]; }); });
      if (st.detail && st.detail.key === key) Object.keys(props).forEach(function (k) { st.detail[k] = props[k]; });
    }
    function setRead(key, read, quiet) {
      ctx.api('/atak/toc/api/reports/' + key + '/read', { method: 'POST', body: { read: read } }).then(function (j) {
        if (!j || !j.ok) { if (!quiet) msg(ERRORS[j && j.error] || 'Marque non enregistrée.', true); return; }
        if (j.persisted === false) { st.readState = 'degraded'; if (read) st.localRead[key] = true; else delete st.localRead[key]; patch(key, { read: false }); }
        else patch(key, { read: read });
        refresh();
        if (!quiet) msg(read ? 'Marqué comme lu.' : 'Marqué comme non lu.');
      }).catch(function () { if (!quiet) msg('Réseau indisponible.', true); });
    }
    function ack(key) {
      ctx.api('/atak/toc/api/reports/' + key + '/ack', { method: 'POST', body: {} }).then(function (j) {
        if (!j || !j.ok) { msg(ERRORS[j && j.error] || 'Accusé refusé.', true); return; }
        st.detail = Object.assign({}, j.report, { read: st.detail ? st.detail.read : j.report.read, photos: st.detail ? st.detail.photos : [] });
        patch(key, { acked: true, status: j.report.status, status_label: j.report.status_label, closed: j.report.closed });
        renderDetail();
        refresh();
        msg('Réception accusée.');
        ctx.say('Réception accusée : ' + j.report.title);
      }).catch(function () { msg('Réseau indisponible : accusé non envoyé.', true); });
    }
    function attach(key, body) {
      var planId = Number($('[data-rp-plan]', body).value), phase = $('[data-rp-phase]', body).value;
      if (!planId) return;
      ctx.api('/atak/toc/api/reports/' + key + '/attach', { method: 'POST', body: { planId: planId, phase: phase } }).then(function (j) {
        if (!j || !j.ok) { msg(ERRORS[j && j.error] || 'Jonction refusée.', true); return; }
        if (st.detail) st.detail.plans = j.plans || [];
        renderDetail();
        msg('Rapport joint au plan.');
      }).catch(function () { msg('Réseau indisponible : jonction non faite.', true); });
    }

    /* ---------- Événements ---------- */
    function onPanel(e) {
      var t = e.target;
      var b = t.closest ? t.closest('[data-rp-open],[data-rp-fam],[data-rp-unread],[data-rp-filters],[data-rp-reset],[data-rp-readall],[data-rp-reload]') : null;
      if (!b) return;
      if (b.hasAttribute('data-rp-open')) { open(b.getAttribute('data-rp-open')); return; }
      if (b.hasAttribute('data-rp-fam')) { f.family = b.getAttribute('data-rp-fam'); savePrefs(); ctx.renderPanel(); return; }
      if (b.hasAttribute('data-rp-unread')) { f.unread = !f.unread; savePrefs(); ctx.renderPanel(); return; }
      if (b.hasAttribute('data-rp-filters')) { f.open = !f.open; ctx.renderPanel(); return; }
      if (b.hasAttribute('data-rp-reset')) { f.family = ''; f.q = ''; f.unread = false; f.unit = ''; f.priority = ''; f.status = ''; f.period = '24h'; f.allMaps = false; f.sort = 'recent'; savePrefs(); load(); ctx.renderPanel(); return; }
      if (b.hasAttribute('data-rp-reload')) { st.loaded = false; load(); return; }
      if (b.hasAttribute('data-rp-readall')) {
        ctx.api('/atak/toc/api/reports/read-all' + query(), { method: 'POST', body: {} }).then(function (j) {
          if (j && j.persisted === false) { st.items.forEach(function (x) { st.localRead[x.key] = true; }); st.readState = 'degraded'; }
          else st.items.forEach(function (x) { x.read = true; });
          refresh();
          ctx.say(j && j.ok ? 'Tous les rapports sont marqués comme lus.' : 'Marque non enregistrée.', !(j && j.ok));
        });
      }
    }
    function onPanelInput(e) {
      var t = e.target;
      if (t.hasAttribute && t.hasAttribute('data-rp-q')) { f.q = t.value; refreshResults(); return; }
      var k = t.getAttribute && t.getAttribute('data-rp-set');
      if (!k) return;
      if (k === 'allMaps') f.allMaps = t.value === '1';
      else f[k] = t.value;
      savePrefs();
      if (k === 'period' || k === 'allMaps') { st.loaded = false; load(true); }
      if (k === 'unit') refreshResults(); else ctx.renderPanel();
    }
    function onDrawer(e) {
      if (ctx.drawerId() !== 'rapport') return;
      var r = st.detail;
      var t = e.target.closest ? e.target.closest('[data-rp-tab],[data-rp-center],[data-rp-ack],[data-rp-link],[data-rp-toggle-read],[data-rp-attach],[data-rp-pdf]') : null;
      if (!r || !t || !ctx.inDrawer(t)) return;
      if (t.hasAttribute('data-rp-tab')) { st.tab = t.getAttribute('data-rp-tab'); renderDetail(); return; }
      if (t.hasAttribute('data-rp-center')) { if (r.pos) { ctx.center(r.pos); ctx.closeDrawer(); } return; }
      if (t.hasAttribute('data-rp-ack')) { ack(r.key); return; }
      if (t.hasAttribute('data-rp-link')) { ctx.copy(ctx.base + '/atak/toc?onglet=rapports#rapport=' + r.key, 'Lien copié.'); return; }
      if (t.hasAttribute('data-rp-toggle-read')) { setRead(r.key, !isRead(r)); return; }
      if (t.hasAttribute('data-rp-attach')) { attach(r.key, ctx.drawerBody()); }
    }
    function onDrawerChange(e) {
      if (ctx.drawerId() !== 'rapport' || !e.target.hasAttribute || !e.target.hasAttribute('data-rp-plan')) return;
      var body = ctx.drawerBody();
      var id = Number(e.target.value);
      var ph = $('[data-rp-phase]', body), go = $('[data-rp-attach]', body);
      var plan = (st.plans || []).filter(function (p) { return p.id === id; })[0];
      ph.innerHTML = '<option value="">Toutes les phases</option>' + (plan ? plan.phases.map(function (p) { return '<option value="' + esc(p.uid) + '">' + esc(p.name || p.uid) + '</option>'; }).join('') : '');
      ph.disabled = !plan;
      go.disabled = !plan;
    }

    /* ---------- Épingles de la carte ---------- */
    function pins() {
      return st.items.filter(function (it) { return it.pos; });
    }

    var panelEl = null;
    function bind(el) {
      if (panelEl === el) return;
      panelEl = el;
      el.addEventListener('click', onPanel);
      el.addEventListener('input', onPanelInput);
      el.addEventListener('change', onPanelInput);
      document.addEventListener('click', onDrawer);
      document.addEventListener('change', onDrawerChange);
    }

    return {
      bind: bind, panel: panel, load: load, onStamp: onStamp, open: open, pins: pins, items: function () { return st.items; },
      unread: unreadCount, loaded: function () { return st.loaded; }, state: function () { return st; },
      setMap: function () { st.loaded = false; load(true); },
      destroy: function () { dead = true; }
    };
  }

  return { create: create, rich: rich };
})();
