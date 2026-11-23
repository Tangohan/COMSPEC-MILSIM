/*
 * Bureau des effectifs plein écran (vue « logiciel »). Données : GET /back-office/bureau-effectifs/donnees (lecture
 * seule, même cloisonnement et mêmes droits que la liste des membres). Seule écriture : l'invitation d'une fiche, par
 * la route existante POST …/effectifs/membres/{id}/invitations, avec le jeton CSRF de la page. L'import de tableur est
 * un formulaire classique (voir page.php). Aucun texte de la page ne vient de la base sans passage par esc().
 */
(function () {
    'use strict';

    var root = document.getElementById('ed-app');
    if (!root) { return; }
    var CFG = {};
    try { CFG = JSON.parse(root.getAttribute('data-ed-config') || '{}'); } catch (e) { CFG = {}; }
    var URLS = CFG.urls || {};
    var LS_KEY = 'ed-shortcuts-v1';

    // ——— Utilitaires ———
    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function z(name) { return root.querySelector('[data-zone="' + name + '"]'); }
    function norm(s) { return String(s == null ? '' : s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
    function pad(n) { return String(n).padStart(2, '0'); }
    function clock(d) { return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds()); }
    function hours(sec) {
        sec = Number(sec) || 0;
        if (sec <= 0) { return '—'; }
        var m = Math.floor(sec / 60);
        return Math.floor(m / 60) + ' h ' + pad(m % 60);
    }
    function fmtDate(s) {
        var d = s ? new Date(String(s).replace(' ', 'T')) : null;
        return d && !isNaN(d.getTime()) ? pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear() : '—';
    }
    var collator = new Intl.Collator('fr', { numeric: true, sensitivity: 'base' });
    var AVP = [['#dfe7e3', '#8aa197'], ['#e3e6ee', '#8d96ad'], ['#ece6dc', '#a8977c'], ['#e2e9ec', '#7f97a1'], ['#e8e3ea', '#9d8ea4'], ['#e4ebe1', '#8ba083']];
    function avatar(id, size) {
        var p = AVP[Math.abs(Number(id) || 0) % AVP.length];
        return '<span class="ed-av" style="width:' + size + 'px;height:' + size + 'px;background:' + p[0] + '"><svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="9" r="4.2" fill="' + p[1] + '"/><path d="M3.5 24c.6-5 4.2-8 8.5-8s7.9 3 8.5 8z" fill="' + p[1] + '"/></svg></span>';
    }
    function tag(text, tone) { return '<span class="ed-tag ed-tag--' + tone + '">' + esc(text) + '</span>'; }
    function plural(n, one, many) { return n + ' ' + (n > 1 ? many : one); }

    // ——— État ———
    var S = {
        data: null, loading: true, error: '',
        tab: 'reg', chip: 0, q: '', sortKey: null, sortDir: 1, sel: null, checked: {},
        menu: null, pop: null, showPanel: true, showTree: true, showCards: true, hiddenCards: { nou: true }, showOrg: true,
        scope: 'org', unitFilter: 0, cf: {}, groupBy: '', collapsed: {}, fmenu: null, fsearch: '',
        hiddenCols: {}, dense: false, extra: {}, dataOpen: false,
        shortcuts: [], naming: false, newName: '', activeShortcut: null,
        modal: null, mtab: 'profil', pal: false, pq: '', pf: 'all', pi: 0,
        online: navigator.onLine !== false, syncing: false, lastSync: '', nextSync: 60, toast: null,
        bootStep: 0, booting: true
    };
    try {
        var saved = JSON.parse(window.localStorage.getItem(LS_KEY) || '[]');
        if (Array.isArray(saved)) { S.shortcuts = saved.slice(0, 20); }
    } catch (e) { /* stockage indisponible */ }
    var narrow = window.innerWidth < 900;
    var collapsedPanels = { tree: narrow, dossier: narrow };

    var REG = [];
    function reg(fn) { REG.push(fn); return REG.length - 1; }

    // ——— Unités ———
    var U = { byId: {}, kids: {}, roots: [] };
    function indexUnits() {
        U = { byId: {}, kids: {}, roots: [] };
        (S.data.units || []).forEach(function (u) { U.byId[u.id] = u; U.kids[u.id] = []; });
        (S.data.units || []).forEach(function (u) {
            if (u.parentId && U.byId[u.parentId]) { U.kids[u.parentId].push(u); } else { U.roots.push(u); }
        });
        var cmp = function (a, b) { return a.order - b.order || collator.compare(a.name, b.name); };
        U.roots.sort(cmp);
        Object.keys(U.kids).forEach(function (k) { U.kids[k].sort(cmp); });
    }
    function descend(id) {
        var out = {}; var stack = [id];
        while (stack.length) {
            var c = stack.pop();
            if (out[c]) { continue; }
            out[c] = true;
            (U.kids[c] || []).forEach(function (k) { stack.push(k.id); });
        }
        return out;
    }
    function scopeSet() {
        if (S.scope === 'unit' && S.data.viewerUnitId && U.byId[S.data.viewerUnitId]) { return descend(S.data.viewerUnitId); }
        return null;
    }
    function inUnitFilter(unitId) {
        var sc = scopeSet();
        if (sc && !sc[unitId]) { return false; }
        if (S.unitFilter === 0) { return true; }
        if (S.unitFilter === -1) { return !unitId; }
        return !!descend(S.unitFilter)[unitId];
    }
    function memberCount(unitId) {
        var set = descend(unitId); var n = 0;
        S.data.members.forEach(function (m) { if (set[m.unitId]) { n++; } });
        return n;
    }

    // ——— Feuilles ———
    function tone(kind, v) {
        if (kind === 'status') { return v === 'active' ? 'ok' : (v === 'pending_verification' ? 'warn' : 'neut'); }
        return { actif: 'ok', compte: 'ok', fiche: 'neut', invitee: 'info', 'en_attente': 'warn' }[v] || 'neut';
    }
    function isAccount(m) { return m.account === 'actif' || m.account === 'compte'; }
    function qualTone(m) { return m.qualAlert ? 'warn' : (m.qualCount > 0 ? 'ok' : 'neut'); }
    var DATASETS = [
        { id: 'jeu', ico: 'H', label: 'Heures de jeu', src: 'Sessions du serveur Arma 3 · total cumulé', cols: [{ k: 'jeu', l: 'Heures de jeu', type: 'h', v: function (m) { return hours(m.playSeconds); }, sortv: function (m) { return m.playSeconds; }, num: true }] },
        { id: 'vol', ico: 'V', label: 'Heures de vol', src: 'Télémétrie ATAK : vol des pilotes · total', cols: [{ k: 'vol', l: 'Heures de vol', type: 'h', v: function (m) { return hours(m.pilotSeconds); }, sortv: function (m) { return m.pilotSeconds; }, num: true }] },
        { id: 'mail', ico: '@', label: 'E-mail rattaché', src: 'Adresse rattachée à la fiche sans compte', cols: [{ k: 'email', l: 'E-mail rattaché', type: 'Aa', v: function (m) { return m.email || '—'; } }] }
    ];
    function extraCols() {
        var out = [];
        DATASETS.forEach(function (d) { if (S.extra[d.id]) { d.cols.forEach(function (c) { out.push(Object.assign({ data: true }, c)); }); } });
        return out;
    }
    function buildSheets() {
        var d = S.data; var out = {};
        var mem = d.members.filter(function (m) { return inUnitFilter(m.unitId); });
        var ex = extraCols();
        out.reg = {
            label: 'Registre nominatif', id: function (r) { return r.id; }, rows: mem, member: function (r) { return r; },
            hay: function (r) { return [r.callsign, r.first, r.last, r.matricule, r.unit, r.emploi, r.grade, r.email].join(' '); },
            cols: [
                { k: 'ind', l: 'Indicatif', type: 'ID', mono: true, v: function (r) { return r.callsign || '—'; } },
                { k: 'mat', l: 'Matricule', type: 'ID', mono: true, v: function (r) { return r.matricule || '—'; } },
                { k: 'grade', l: 'Grade', type: 'Aa', v: function (r) { return r.grade || '—'; }, title: function (r) { return r.gradeLong; } },
                { k: 'nom', l: 'Nom', type: 'Aa', av: true, v: function (r) { return r.last || '—'; } },
                { k: 'prenom', l: 'Prénom', type: 'Aa', v: function (r) { return r.first || '—'; } },
                { k: 'unite', l: 'Unité', type: 'Aa', v: function (r) { return r.unit || 'Sans unité'; } },
                { k: 'parent', l: 'Rattachement', type: 'Aa', v: function (r) { return r.parent || '—'; } },
                { k: 'emploi', l: 'Emploi', type: 'Aa', v: function (r) { return r.emploi || '—'; } },
                { k: 'statut', l: 'Statut', type: 'Tag', tag: true, v: function (r) { return r.statusLabel; }, tone: function (r) { return tone('status', r.status); } },
                { k: 'compte', l: 'Compte', type: 'Tag', tag: true, v: function (r) { return r.accountLabel; }, tone: function (r) { return tone('account', r.account); } },
                { k: 'presence', l: 'Présence %', type: '123', num: true, bar: 100, v: function (r) { return r.presence; } },
                { k: 'anc', l: 'Ancienneté (j)', type: '123', num: true, v: function (r) { return r.seniorityDays; }, title: function (r) { return r.seniority; } },
                { k: 'dossier', l: 'Dossier %', type: '123', num: true, bar: 100, v: function (r) { return r.completion; } },
                { k: 'qual', l: 'Qualifications', type: '123', num: true, v: function (r) { return r.qualCount; } }
            ].concat(ex),
            chips: [
                ['Tous', function () { return true; }],
                ['Comptes actifs', isAccount],
                ['Sans compte', function (r) { return r.account === 'fiche'; }],
                ['Invitations', function (r) { return r.account === 'invitee' || r.account === 'en_attente'; }],
                ['Dossier incomplet', function (r) { return r.completion < 100; }],
                ['Inactifs', function (r) { return r.status === 'inactive'; }]
            ],
            groups: [['', 'Aucun'], ['unite', 'Unité'], ['grade', 'Grade'], ['compte', 'Compte'], ['statut', 'Statut']]
        };
        var units = d.units.filter(function (u) { return inUnitFilter(u.id); }).map(function (u) {
            var own = d.members.filter(function (m) { return m.unitId === u.id; });
            var act = own.filter(isAccount).length;
            var pres = own.length ? Math.round(own.reduce(function (a, m) { return a + m.presence; }, 0) / own.length) : 0;
            return { id: 'u' + u.id, u: u, name: u.name, code: u.code, parent: u.path.indexOf(' / ') > 0 ? u.path.slice(0, u.path.lastIndexOf(' / ')) : '—', chef: u.commander, n: own.length, act: act, sans: own.filter(function (m) { return m.account === 'fiche'; }).length, pres: pres };
        });
        out.orbat = {
            label: 'Affectations / ORBAT', id: function (r) { return r.id; }, rows: units, member: function () { return null; },
            hay: function (r) { return [r.name, r.code, r.chef, r.parent].join(' '); },
            cols: [
                { k: 'code', l: 'Code', type: 'ID', mono: true, v: function (r) { return r.code || '—'; } },
                { k: 'name', l: 'Unité', type: 'Aa', v: function (r) { return r.name; } },
                { k: 'parent', l: 'Rattachement', type: 'Aa', v: function (r) { return r.parent; } },
                { k: 'chef', l: 'Chef de l’unité', type: 'Aa', v: function (r) { return r.chef || '—'; } },
                { k: 'n', l: 'Effectif', type: '123', num: true, v: function (r) { return r.n; } },
                { k: 'act', l: 'Comptes actifs', type: '123', num: true, v: function (r) { return r.act; } },
                { k: 'sans', l: 'Sans compte', type: '123', num: true, v: function (r) { return r.sans; } },
                { k: 'pres', l: 'Présence moy. %', type: '123', num: true, bar: 100, v: function (r) { return r.pres; } },
                { k: 'etat', l: 'État', type: 'Tag', tag: true, v: function (r) { return r.chef ? 'Pourvu' : 'Chef absent'; }, tone: function (r) { return r.chef ? 'ok' : 'bad'; } }
            ],
            chips: [['Toutes', function () { return true; }], ['Chef absent', function (r) { return !r.chef; }], ['Sans effectif', function (r) { return r.n === 0; }]],
            groups: [['', 'Aucun']]
        };
        out.quals = {
            label: 'Qualifications', id: function (r) { return r.id; }, rows: mem, member: function (r) { return r; },
            hay: function (r) { return [r.callsign, r.first, r.last, r.qualSummary].join(' '); },
            cols: [
                { k: 'grade', l: 'Grade', type: 'Aa', v: function (r) { return r.grade || '—'; } },
                { k: 'nom', l: 'Nom', type: 'Aa', av: true, v: function (r) { return r.last || r.callsign || '—'; } },
                { k: 'unite', l: 'Unité', type: 'Aa', v: function (r) { return r.unit || 'Sans unité'; } },
                { k: 'n', l: 'Nombre', type: '123', num: true, v: function (r) { return r.qualCount; } },
                { k: 'liste', l: 'Qualifications obtenues', type: 'Aa', v: function (r) { return r.qualSummary; } },
                { k: 'alerte', l: 'Alerte', type: 'Tag', tag: true, v: function (r) { return r.qualAlert || (r.qualCount ? 'À jour' : '—'); }, tone: qualTone }
            ].concat(ex),
            chips: [['Tous', function () { return true; }], ['Avec alerte', function (r) { return !!r.qualAlert; }], ['Sans qualification', function (r) { return r.qualCount === 0; }]],
            groups: [['', 'Aucun'], ['unite', 'Unité']]
        };
        var fic = mem.filter(function (m) { return m.account !== 'actif'; });
        out.fiches = {
            label: 'Sans compte et invitations', id: function (r) { return r.id; }, rows: fic, member: function (r) { return r; },
            hay: function (r) { return [r.callsign, r.first, r.last, r.email].join(' '); },
            cols: [
                { k: 'nom', l: 'Nom', type: 'Aa', av: true, v: function (r) { return r.last || r.callsign || '—'; } },
                { k: 'prenom', l: 'Prénom', type: 'Aa', v: function (r) { return r.first || '—'; } },
                { k: 'ind', l: 'Indicatif', type: 'ID', mono: true, v: function (r) { return r.callsign || '—'; } },
                { k: 'unite', l: 'Unité', type: 'Aa', v: function (r) { return r.unit || 'Sans unité'; } },
                { k: 'compte', l: 'Compte', type: 'Tag', tag: true, v: function (r) { return r.accountLabel; }, tone: function (r) { return tone('account', r.account); } },
                { k: 'email', l: 'E-mail rattaché', type: 'Aa', v: function (r) { return r.email || '—'; } },
                { k: 'inv', l: 'Invitation du', type: 'ID', mono: true, v: function (r) { return fmtDate(r.inviteAt); } },
                { k: 'src', l: 'Origine', type: 'Aa', v: function (r) { return r.source || '—'; } },
                { k: 'act', l: 'Dernière activité', type: 'ID', mono: true, v: function (r) { return fmtDate(r.lastActivity); } }
            ],
            chips: [
                ['Toutes', function () { return true; }],
                ['Sans compte', function (r) { return r.account === 'fiche'; }],
                ['Invitation envoyée', function (r) { return r.account === 'invitee'; }],
                ['Invitation expirée', function (r) { return r.inviteExpired; }],
                ['Compte à activer', function (r) { return r.account === 'en_attente'; }]
            ],
            groups: [['', 'Aucun'], ['compte', 'Compte'], ['unite', 'Unité']]
        };
        out.proc = { label: 'Processus', id: function (r) { return r.id; }, rows: mem, member: function (r) { return r; }, cols: [{ k: 'nom', l: 'Nom', v: function () { return ''; } }], chips: [['Fiches et comptes', function () { return true; }]], groups: [['', 'Aucun']], hay: function () { return ''; } };
        Object.keys(out).forEach(function (k) {
            out[k].allCols = out[k].cols;
            var h = S.hiddenCols[k] || {};
            out[k].cols = out[k].cols.filter(function (c) { return !h[c.k]; });
        });
        return out;
    }
    var TAB_ORDER = ['reg', 'orbat', 'quals', 'fiches', 'proc'];

    // ——— Lignes visibles (filtres, tri, regroupement) ———
    function colByKey(sh, k) { for (var i = 0; i < sh.allCols.length; i++) { if (sh.allCols[i].k === k) { return sh.allCols[i]; } } return null; }
    function visible(sh) {
        var rows = sh.rows;
        var chip = sh.chips[S.chip] || sh.chips[0];
        rows = rows.filter(chip[1]);
        var q = norm(S.q.trim());
        if (q) { rows = rows.filter(function (r) { return norm(sh.hay(r)).indexOf(q) >= 0; }); }
        var cf = S.cf[S.tab] || {};
        Object.keys(cf).forEach(function (k) {
            var c = colByKey(sh, k); var ex = cf[k];
            if (c && ex && ex.length) { rows = rows.filter(function (r) { return ex.indexOf(String(c.v(r))) < 0; }); }
        });
        if (S.sortKey) {
            var sc = colByKey(sh, S.sortKey);
            if (sc) {
                var g = sc.sortv || sc.v;
                rows = rows.slice().sort(function (a, b) {
                    var x = g(a), y = g(b);
                    var r = typeof x === 'number' && typeof y === 'number' ? x - y : collator.compare(String(x), String(y));
                    return r * S.sortDir;
                });
            }
        }
        return rows;
    }
    function groupLabel(sh, r) {
        if (S.groupBy === 'unite') { return r.unit || 'Sans unité'; }
        if (S.groupBy === 'grade') { return r.grade || 'Sans grade'; }
        if (S.groupBy === 'compte') { return r.accountLabel; }
        if (S.groupBy === 'statut') { return r.statusLabel; }
        return '';
    }
    function layout(sh, rows) {
        var items = []; var n = 0;
        if (!S.groupBy || sh.groups.every(function (g) { return g[0] !== S.groupBy; })) {
            rows.forEach(function (r) { items.push({ t: 'row', r: r, n: ++n }); });
            return items;
        }
        var order = []; var map = {};
        rows.forEach(function (r) { var l = groupLabel(sh, r); if (!map[l]) { map[l] = []; order.push(l); } map[l].push(r); });
        order.forEach(function (l) {
            var col = !!S.collapsed[S.tab + '|' + l];
            items.push({ t: 'group', label: l, count: map[l].length, collapsed: col });
            if (!col) { map[l].forEach(function (r) { items.push({ t: 'row', r: r, n: ++n }); }); } else { n += map[l].length; }
        });
        return items;
    }
    var V = { sh: null, rows: [], items: [], cols: [] };
    function compute() {
        var all = buildSheets();
        var sh = all[S.tab];
        V.all = all; V.sh = sh;
        V.rows = S.tab === 'proc' ? [] : visible(sh);
        V.items = S.tab === 'proc' ? [] : layout(sh, V.rows);
        V.dataRows = V.items.filter(function (i) { return i.t === 'row'; });
        V.cols = sh.cols;
        if (S.tab !== 'proc') {
            var ok = S.sel && V.dataRows.some(function (i) { return String(sh.id(i.r)) === String(S.sel.id); }) && V.cols.some(function (c) { return c.k === S.sel.key; });
            if (!ok) { S.sel = V.dataRows.length && V.cols.length ? { id: sh.id(V.dataRows[0].r), key: (V.cols[1] || V.cols[0]).k } : null; }
        }
    }
    function selRow() {
        if (!S.sel || !V.sh) { return null; }
        for (var i = 0; i < V.dataRows.length; i++) { if (String(V.sh.id(V.dataRows[i].r)) === String(S.sel.id)) { return V.dataRows[i]; } }
        return null;
    }
    function selMember() { var r = selRow(); return r ? V.sh.member(r.r) : null; }
    function memberById(id) { for (var i = 0; i < S.data.members.length; i++) { if (S.data.members[i].id === id) { return S.data.members[i]; } } return null; }
    function letters(i) { return String.fromCharCode(65 + (i % 26)); }

    // ——— Rendu : barre de titre ———
    function renderTitlebar() {
        root.querySelectorAll('[data-act="scope"]').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-v') === S.scope ? 'true' : 'false'); });
        var net = z('net');
        net.classList.toggle('is-off', !S.online);
        net.querySelector('[data-net-label]').textContent = S.online ? 'En ligne' : 'Hors ligne';
        net.title = S.online ? 'Le classeur se synchronise toutes les minutes.' : 'Vous travaillez hors ligne : la synchronisation reprendra au retour du réseau.';
        var sy = z('sync');
        sy.disabled = S.syncing || !S.online;
        sy.querySelector('svg').setAttribute('class', S.syncing ? 'ed-spin' : '');
        sy.querySelector('[data-sync-label]').textContent = S.syncing ? 'Synchronisation…' : (S.lastSync ? 'Synchro ' + S.lastSync : 'Synchro');
        z('sweep').hidden = !S.syncing;
    }

    // ——— Rendu : menus ———
    function row(label, fn, key, check, extra) { return Object.assign({ label: label, fn: fn, key: key || '', check: !!check }, extra || {}); }
    var SEP = { sep: true };
    function closeMenu() { S.menu = null; render(); }
    function menuDefs() {
        var sh = V.sh; var m = selMember();
        var expQ = [];
        if (S.q.trim()) { expQ.push('q=' + encodeURIComponent(S.q.trim())); }
        if (S.unitFilter > 0) { expQ.push('unite=' + S.unitFilter); }
        var exp = URLS.exportCsv + (expQ.length ? '?' + expQ.join('&') : '');
        var cards = cardDefs();
        var file = [];
        if (CFG.canImport) { file.push(row('Importer un tableur…', function () { var f = root.querySelector('[data-import-file]'); S.menu = null; render(); if (f) { f.click(); } })); }
        file.push(row('Télécharger le modèle de tableur', null, '', false, { href: URLS.template }));
        file.push(SEP);
        file.push(row('Exporter en CSV', null, '', false, { href: exp }));
        if (CFG.canPdf) { file.push(row('Exporter en PDF', null, '', false, { href: URLS.exportPdf })); }
        file.push(SEP, row('Liste des membres classique', null, '', false, { href: URLS.classic }), row('Retour au back-office', null, '', false, { href: URLS.back }));
        var view = [row('Cartes', function () { S.showCards = !S.showCards; closeMenu(); }, '', S.showCards)];
        cards.forEach(function (c) { view.push(row('    ' + c.label, function () { S.hiddenCards[c.id] = !S.hiddenCards[c.id]; closeMenu(); }, '', !S.hiddenCards[c.id])); });
        view.push(SEP,
            row('Panneau dossier', function () { S.showPanel = !S.showPanel; closeMenu(); }, '', S.showPanel),
            row('Arbre de l’organisation', function () { S.showTree = !S.showTree; closeMenu(); }, '', S.showTree),
            row('Organigramme', function () { S.showOrg = !S.showOrg; closeMenu(); }, '', S.showOrg),
            row('Lignes compactes', function () { S.dense = !S.dense; closeMenu(); }, '', S.dense), SEP);
        TAB_ORDER.forEach(function (k) { view.push(row(V.all[k].label, function () { goTab(k); }, '', k === S.tab)); });
        var ins = [];
        if (CFG.canCreate) { ins.push(row('Nouveau membre…', null, '', false, { href: URLS.newMember })); }
        ins.push(row('Membre sans compte…', null, '', false, { href: URLS.noAccount }), SEP, row('Données : heures de jeu, vol…', function () { S.dataOpen = true; S.menu = null; render(); }));
        var selCol = S.sel ? colByKey(sh, S.sel.key) : null;
        var data = [
            row('Trier de A à Z', function () { if (selCol) { sortBy(selCol.k, 1); } else { closeMenu(); } }),
            row('Trier de Z à A', function () { if (selCol) { sortBy(selCol.k, -1); } else { closeMenu(); } }), SEP,
            row('Ajouter des données…', function () { S.dataOpen = true; S.menu = null; render(); }),
            row('Colonnes affichées…', function () { S.pop = 'cols'; S.menu = null; render(); }), SEP,
            row('Synchroniser maintenant', function () { S.menu = null; sync(false); }, 'F5'),
            row('Travailler hors ligne', function () { S.menu = null; setOnline(!S.online); }, '', !S.online)
        ];
        var mem = [
            row('Fiche rapide', function () { if (m) { openMember(m.id); } else { closeMenu(); } }, 'Entrée'),
            row('Inviter ou relancer', function () { if (m) { invite(m); } else { closeMenu(); } }, '', false, { disabled: !(m && CFG.canInvite && (m.account === 'fiche' || m.account === 'invitee')) }),
            row('Ouvrir la fiche complète', null, '', false, { href: m ? m.ficheUrl : '', disabled: !m })
        ];
        var org = [
            row('Bureau d’unité', function () { S.scope = 'unit'; S.menu = null; render(); }, '', S.scope === 'unit'),
            row('Gestion complète de l’organisation', function () { S.scope = 'org'; S.menu = null; render(); }, '', S.scope === 'org'), SEP,
            row('Chaîne de commandement…', null, '', false, { href: URLS.orgChart }),
            row('Affectations…', null, '', false, { href: URLS.assignments }),
            row('Rôles et niveaux d’accès…', null, '', false, { href: URLS.roles })
        ];
        return [
            ['Fichier', file],
            ['Édition', [row('Rechercher…', function () { openPalette(); }, 'Ctrl+K'), row('Effacer les filtres', function () { clearFilters(); S.menu = null; render(); })]],
            ['Affichage', view], ['Insertion', ins], ['Données', data], ['Membres', mem], ['Organisation', org]
        ];
    }
    function renderMenubar() {
        var defs = menuDefs();
        var h = defs.map(function (d) {
            var open = S.menu === d[0];
            var out = '<div class="ed-mb"><button type="button" class="ed-mb-item' + (open ? ' is-open' : '') + '" aria-haspopup="menu" aria-expanded="' + open + '" data-fn="' + reg(function () { S.menu = open ? null : d[0]; S.pop = null; render(); }) + '">' + esc(d[0]) + '</button>';
            if (open) {
                out += '<div class="ed-mb-menu" role="menu">' + d[1].map(function (it) {
                    if (it.sep) { return '<div class="ed-mb-sep" role="separator"></div>'; }
                    var inner = '<span class="ed-mb-check" aria-hidden="true">' + (it.check ? '✓' : '') + '</span>' + esc(it.label) + '<span class="ed-mb-key">' + esc(it.key) + '</span>';
                    if (it.href && !it.disabled) { return '<a class="ed-mb-row" role="menuitem" href="' + esc(it.href) + '">' + inner + '</a>'; }
                    return '<button type="button" class="ed-mb-row" role="menuitem"' + (it.disabled || !it.fn ? ' disabled' : '') + ' data-fn="' + (it.fn ? reg(it.fn) : '') + '">' + inner + '</button>';
                }).join('') + '</div>';
            }
            return out + '</div>';
        }).join('');
        h += '<span class="ed-spacer"></span><span class="ed-menubar__info">' + esc(S.lastSync ? 'Dernière synchronisation à ' + S.lastSync : '') + '</span>';
        z('menubar').innerHTML = h;
    }

    // ——— Rendu : ruban ———
    var ICON = {
        up: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 16l4 4 4-4M7 20V4M15 4h5l-5 7h5"/></svg>',
        down: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8l4-4 4 4M7 4v16M15 4h5l-5 7h5"/></svg>',
        data: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3M19 6v4M5 12v6c0 1.7 3.1 3 7 3"/><path d="M18 14v6M15 17h6"/></svg>',
        cols: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="16"/><path d="M9 4v16M15 4v16"/></svg>',
        dense: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>',
        filter: '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg>'
    };
    function checkedMembers() {
        return Object.keys(S.checked).filter(function (k) { return S.checked[k]; }).map(function (k) { return memberById(Number(k)); }).filter(Boolean);
    }
    function renderToolbar() {
        var isGrid = S.tab !== 'proc';
        var h = '';
        if (isGrid) {
            h += '<div class="ed-tg"><button type="button" class="ed-tb" title="Trier de A à Z" aria-label="Trier de A à Z" data-fn="' + reg(function () { if (S.sel) { sortBy(S.sel.key, 1); } }) + '">' + ICON.up + '</button>' +
                '<button type="button" class="ed-tb" title="Trier de Z à A" aria-label="Trier de Z à A" data-fn="' + reg(function () { if (S.sel) { sortBy(S.sel.key, -1); } }) + '">' + ICON.down + '</button>' +
                '<button type="button" class="ed-tb" title="Ajouter des données au tableau" data-fn="' + reg(function () { S.dataOpen = true; render(); }) + '">' + ICON.data + 'Données</button>' +
                '<div style="position:relative"><button type="button" class="ed-tb" title="Colonnes affichées" aria-expanded="' + (S.pop === 'cols') + '" data-fn="' + reg(function () { S.pop = S.pop === 'cols' ? null : 'cols'; S.menu = null; render(); }) + '">' + ICON.cols + 'Colonnes ▾</button>';
            if (S.pop === 'cols') {
                h += '<div class="ed-pop" role="dialog" aria-label="Colonnes affichées"><span class="ed-side-h">Colonnes affichées</span>' + V.sh.allCols.map(function (c) {
                    var on = !(S.hiddenCols[S.tab] || {})[c.k];
                    return '<label><input type="checkbox"' + (on ? ' checked' : '') + ' data-chg="' + reg(function () { S.hiddenCols[S.tab] = S.hiddenCols[S.tab] || {}; S.hiddenCols[S.tab][c.k] = on; render(); }) + '">' + esc(c.l) + (c.data ? '<span class="ed-pop__tag">DONNÉES</span>' : '') + '</label>';
                }).join('') + '</div>';
            }
            h += '</div><span class="ed-tg-l">Données</span></div>';
            h += '<div class="ed-tg"><button type="button" class="ed-tb' + (S.dense ? ' is-on' : '') + '" aria-pressed="' + S.dense + '" title="Lignes compactes" data-fn="' + reg(function () { S.dense = !S.dense; render(); }) + '">' + ICON.dense + 'Compact</button><span class="ed-tg-l">Affichage</span></div>';
        }
        h += '<span class="ed-spacer"></span><div class="ed-tg">';
        var cm = checkedMembers();
        if (cm.length) {
            var inv = cm.filter(function (m) { return m.account === 'fiche' || m.account === 'invitee'; });
            h += '<span class="ed-bulk">' + esc(plural(cm.length, 'fiche sélectionnée', 'fiches sélectionnées')) + '</span>';
            if (CFG.canInvite && inv.length) { h += '<button type="button" class="ed-btn ed-btn--sm" data-fn="' + reg(function () { bulkInvite(inv); }) + '">Inviter (' + inv.length + ')</button>'; }
            h += '<button type="button" class="ed-tb" data-fn="' + reg(function () { S.checked = {}; render(); }) + '">Désélectionner</button>';
        }
        h += '<button type="button" class="ed-tb' + (S.showCards ? ' is-on' : '') + '" aria-pressed="' + S.showCards + '" data-fn="' + reg(function () { S.showCards = !S.showCards; render(); }) + '">Cartes</button>' +
            '<button type="button" class="ed-tb' + (S.showPanel ? ' is-on' : '') + '" aria-pressed="' + S.showPanel + '" data-fn="' + reg(function () { S.showPanel = !S.showPanel; render(); }) + '">Dossier</button>' +
            '<a class="ed-btn ed-btn--solid" href="' + esc(URLS.exportCsv + (S.unitFilter > 0 ? '?unite=' + S.unitFilter : '')) + '">Exporter</a><span class="ed-tg-l">Fenêtres</span></div>';
        z('toolbar').innerHTML = h;
    }

    // ——— Rendu : raccourcis ———
    function builtinShortcuts() {
        return [
            { id: 'b1', label: 'Sans compte', color: '#6d7a80', hint: 'Registre, fiches sans compte', v: { tab: 'reg', chip: 2 } },
            { id: 'b2', label: 'Invitations à relancer', color: '#c98a12', hint: 'Fiches dont l’invitation a expiré', v: { tab: 'fiches', chip: 3 } },
            { id: 'b3', label: 'Dossiers incomplets', color: '#c72e2e', hint: 'Registre, dossier à moins de 100 %', v: { tab: 'reg', chip: 4, groupBy: 'unite' } },
            { id: 'b4', label: 'Sans unité', color: '#1e6fbf', hint: 'Membres sans affectation', v: { tab: 'reg', chip: 0, unitFilter: -1 } },
            { id: 'b5', label: 'Qualifications à surveiller', color: '#0b8a5c', hint: 'Qualifications qui expirent', v: { tab: 'quals', chip: 1 } }
        ];
    }
    function snapshot() { return { tab: S.tab, chip: S.chip, q: S.q, groupBy: S.groupBy, cf: S.cf, sortKey: S.sortKey, sortDir: S.sortDir, hiddenCols: S.hiddenCols, unitFilter: S.unitFilter }; }
    function applyView(sc) {
        var base = { tab: 'reg', chip: 0, q: '', groupBy: '', cf: {}, sortKey: null, sortDir: 1, unitFilter: 0 };
        Object.assign(S, base, sc.v, { checked: {}, menu: null, pop: null, fmenu: null, activeShortcut: sc.id, sel: null });
        render();
    }
    function persistShortcuts() { try { window.localStorage.setItem(LS_KEY, JSON.stringify(S.shortcuts)); } catch (e) { /* ignoré */ } }
    function renderShortcuts() {
        var h = '<span class="ed-side-h" style="margin-right:4px">Raccourcis</span>';
        builtinShortcuts().concat(S.shortcuts).forEach(function (sc) {
            h += '<span style="display:inline-flex"><button type="button" class="ed-chip' + (S.activeShortcut === sc.id ? ' is-on' : '') + '" title="' + esc(sc.hint) + '" data-fn="' + reg(function () { applyView(sc); }) + '"><span class="ed-chip__dot" style="background:' + esc(sc.color) + '"></span>' + esc(sc.label) + '</button>';
            if (sc.custom) { h += '<button type="button" class="ed-chip" aria-label="Supprimer le raccourci ' + esc(sc.label) + '" style="border-left:0;padding:0 7px" data-fn="' + reg(function () { S.shortcuts = S.shortcuts.filter(function (x) { return x.id !== sc.id; }); persistShortcuts(); render(); }) + '">×</button>'; }
            h += '</span>';
        });
        if (S.naming) {
            h += '<input class="ed-input" aria-label="Nom du raccourci" placeholder="Nom du raccourci…" value="' + esc(S.newName) + '" data-name-input style="height:24px;width:180px"><button type="button" class="ed-chip is-on" data-fn="' + reg(saveShortcut) + '">Enregistrer</button>';
        } else {
            h += '<button type="button" class="ed-chip ed-chip--dash" data-fn="' + reg(function () { S.naming = true; render(); var i = root.querySelector('[data-name-input]'); if (i) { i.focus(); } }) + '">+ Enregistrer cette vue</button>';
        }
        z('shortcuts').innerHTML = h;
    }
    function saveShortcut() {
        var name = S.newName.trim() || ('Vue ' + (S.shortcuts.length + 1));
        var id = 'u' + Date.now();
        S.shortcuts.push({ id: id, label: name.slice(0, 40), color: '#3c474c', hint: 'Vue enregistrée · ' + V.sh.label, v: JSON.parse(JSON.stringify(snapshot())), custom: true });
        S.naming = false; S.newName = ''; S.activeShortcut = id; persistShortcuts(); render();
    }

    // ——— Rendu : cartes ———
    function cardDefs() {
        var ms = S.data.members.filter(function (m) { return inUnitFilter(m.unitId); });
        var n = ms.length;
        var cnt = function (f) { return ms.filter(f).length; };
        var act = cnt(isAccount), sans = cnt(function (m) { return m.account === 'fiche'; }), inv = cnt(function (m) { return m.account === 'invitee' || m.account === 'en_attente'; });
        var inc = cnt(function (m) { return m.completion < 100; }), al = cnt(function (m) { return !!m.qualAlert; }), nu = cnt(function (m) { return !m.unitId; });
        var pct = function (x) { return n ? Math.round(x * 100 / n) + ' %' : '—'; };
        return [
            { id: 'eff', label: 'Effectif', value: n, delta: sans + inv > 0 ? '' : '', tone: '#0b8a5c', note: 'fiches de la communauté', cta: 'Registre', go: function () { goTab('reg', 0); } },
            { id: 'act', label: 'Comptes actifs', value: act, delta: pct(act), tone: '#0b8a5c', note: 'du registre', cta: 'Filtrer', go: function () { goTab('reg', 1); } },
            { id: 'sans', label: 'Sans compte', value: sans, delta: pct(sans), tone: '#6d7a80', note: 'fiches tenues à la main', cta: 'Ouvrir', go: function () { goTab('reg', 2); } },
            { id: 'inv', label: 'Invitations en cours', value: inv, delta: '', tone: '#1e6fbf', note: 'liens ou comptes à activer', cta: 'Suivre', go: function () { goTab('fiches', 0); } },
            { id: 'dos', label: 'Dossiers incomplets', value: inc, delta: pct(inc), tone: '#c98a12', note: 'pièces à compléter', cta: 'Traiter', go: function () { goTab('reg', 4); } },
            { id: 'qua', label: 'Qualifs à surveiller', value: al, delta: '', tone: '#c72e2e', note: 'expirent bientôt', cta: 'Voir', go: function () { goTab('quals', 1); } },
            { id: 'nou', label: 'Sans unité', value: nu, delta: '', tone: '#8a5a06', note: 'à affecter', cta: 'Affecter', go: function () { S.unitFilter = -1; goTab('reg', 0); } }
        ];
    }
    function renderCards() {
        var zn = z('cards');
        zn.hidden = !S.showCards;
        if (!S.showCards) { zn.innerHTML = ''; return; }
        var all = cardDefs();
        var shown = all.filter(function (c) { return !S.hiddenCards[c.id]; });
        zn.style.setProperty('--ed-card-cols', Math.max(1, Math.min(7, shown.length + (shown.length < all.length ? 1 : 0))));
        var h = shown.map(function (c) {
            return '<div class="ed-card" style="box-shadow:inset 3px 0 0 ' + c.tone + '"><button type="button" class="ed-card__open" aria-label="' + esc(c.label) + ' : ouvrir la vue" data-fn="' + reg(c.go) + '"></button>' +
                '<button type="button" class="ed-card__x" aria-label="Masquer la carte ' + esc(c.label) + '" data-fn="' + reg(function () { S.hiddenCards[c.id] = true; render(); }) + '">×</button>' +
                '<span class="ed-card__l">' + esc(c.label) + '</span><span class="ed-card__v">' + esc(c.value) + (c.delta ? '<span class="ed-card__d" style="color:#0b6b47">' + esc(c.delta) + '</span>' : '') + '</span>' +
                '<span class="ed-card__foot"><span>' + esc(c.note) + '</span><b>' + esc(c.cta) + ' →</b></span></div>';
        }).join('');
        if (shown.length < all.length) { h += '<button type="button" class="ed-cards__more" data-fn="' + reg(function () { S.hiddenCards = {}; render(); }) + '">+ ' + esc(plural(all.length - shown.length, 'carte masquée', 'cartes masquées')) + '</button>'; }
        zn.innerHTML = h;
    }

    // ——— Rendu : filtres ———
    function activeFilters() {
        var out = []; var cf = S.cf[S.tab] || {};
        Object.keys(cf).forEach(function (k) {
            if (cf[k] && cf[k].length) { var c = colByKey(V.sh, k); if (c) { out.push({ label: c.l + ' : ' + cf[k].length + ' masquée(s)', clear: function () { delete cf[k]; render(); } }); } }
        });
        if (S.unitFilter !== 0) { out.push({ label: 'Unité : ' + unitLabel(S.unitFilter), clear: function () { S.unitFilter = 0; render(); } }); }
        return out;
    }
    function unitLabel(id) { return id === -1 ? 'Sans unité' : (U.byId[id] ? U.byId[id].name : '—'); }
    function clearFilters() { S.q = ''; S.chip = 0; S.groupBy = ''; S.cf[S.tab] = {}; S.unitFilter = 0; S.activeShortcut = null; }
    function renderFilters() {
        var zn = z('filters');
        if (S.tab === 'proc') { zn.hidden = true; zn.innerHTML = ''; return; }
        zn.hidden = false;
        var sh = V.sh; var af = activeFilters();
        var h = '<span class="ed-side-h">Vue</span>' + sh.chips.map(function (c, i) {
            return '<button type="button" class="ed-chip' + (S.chip === i ? ' is-on' : '') + '" aria-pressed="' + (S.chip === i) + '" data-fn="' + reg(function () { S.chip = i; S.activeShortcut = null; render(); }) + '">' + esc(c[0]) + '</button>';
        }).join('') + '<span class="ed-vr"></span>';
        h += '<label><span class="ed-side-h">Regrouper</span><select class="ed-input" data-chg="' + reg(function (e, el) { S.groupBy = el.value; render(); }) + '">' + sh.groups.map(function (g) { return '<option value="' + esc(g[0]) + '"' + (S.groupBy === g[0] ? ' selected' : '') + '>' + esc(g[1]) + '</option>'; }).join('') + '</select></label>';
        h += '<input class="ed-input" type="search" placeholder="Filtrer : nom, indicatif, matricule…" aria-label="Filtrer les lignes" value="' + esc(S.q) + '" data-q-input style="width:230px;height:26px">';
        af.forEach(function (f) { h += '<span class="ed-active-f">' + esc(f.label) + '<button type="button" aria-label="Retirer le filtre ' + esc(f.label) + '" data-fn="' + reg(f.clear) + '">×</button></span>'; });
        h += '<span class="ed-spacer"></span>';
        if (af.length || S.q || S.chip || S.groupBy) { h += '<button type="button" class="ed-tb" data-fn="' + reg(function () { clearFilters(); render(); }) + '">Effacer les filtres</button>'; }
        zn.innerHTML = h;
    }

    // ——— Rendu : barre de formule ———
    function selRef() {
        if (!S.sel || !V.sh) { return '—'; }
        var ci = V.cols.findIndex(function (c) { return c.k === S.sel.key; });
        var row = selRow();
        return ci < 0 || !row ? '—' : letters(ci) + row.n;
    }
    function renderFormula() {
        var zn = z('formula');
        if (S.tab === 'proc') { zn.hidden = true; zn.innerHTML = ''; return; }
        zn.hidden = false;
        var row = selRow(); var c = S.sel ? colByKey(V.sh, S.sel.key) : null;
        var val = row && c ? String(c.v(row.r)) : '';
        var m = selMember();
        var info = m ? m.accountLabel + ' · Entrée : fiche rapide' : (row ? 'Unité · cliquez pour filtrer' : '');
        zn.innerHTML = '<span class="ed-formula__ref">' + esc(selRef()) + '</span><span class="ed-formula__fx">fx</span><input class="ed-formula__val" aria-label="Contenu de la case sélectionnée" value="' + esc(val) + '" readonly><span class="ed-formula__info">' + esc(info) + '</span>';
    }

    // ——— Rendu : arbre ———
    function renderTree() {
        var zn = z('tree');
        zn.hidden = !(S.showTree && S.scope === 'org');
        zn.classList.toggle('is-collapsed', collapsedPanels.tree);
        if (zn.hidden) { zn.innerHTML = ''; return; }
        var h = '<div class="ed-panel-h"><span class="ed-side-h">Structure</span><button type="button" class="ed-tb ed-panel-toggle" aria-expanded="' + !collapsedPanels.tree + '" aria-label="Replier ou déplier la structure" data-fn="' + reg(function () { collapsedPanels.tree = !collapsedPanels.tree; render(); }) + '">' + (collapsedPanels.tree ? '▾' : '▴') + '</button></div>';
        h += '<div class="ed-tree__list" role="tree" aria-label="Unités" data-tree>';
        function item(label, n, id, level, glyph) {
            var on = S.unitFilter === id;
            return '<button type="button" role="treeitem" aria-level="' + level + '" aria-selected="' + on + '" class="ed-tree-btn" style="padding-left:' + (10 + (level - 1) * 14) + 'px" tabindex="' + (on || (S.unitFilter === 0 && id === 0) ? 0 : -1) + '" data-fn="' + reg(function () { S.unitFilter = id; S.activeShortcut = null; render(); }) + '"><span aria-hidden="true" style="opacity:.6">' + glyph + '</span>' + esc(label) + '<span class="ed-tree-n">' + n + '</span></button>';
        }
        h += item('Toute l’organisation', S.data.members.length, 0, 1, '◧');
        function walk(list, level) { list.forEach(function (u) { h += item(u.name, memberCount(u.id), u.id, level + 1, level > 1 ? '·' : '▸'); walk(U.kids[u.id] || [], level + 1); }); }
        walk(U.roots, 1);
        var nu = S.data.members.filter(function (m) { return !m.unitId; }).length;
        if (nu) { h += item('Sans unité', nu, -1, 2, '○'); }
        h += '</div><div class="ed-tree__foot"><span class="ed-side-h">Gestion complète</span><a class="ed-btn" href="' + esc(URLS.assignments) + '">Affectations</a><a class="ed-btn" href="' + esc(URLS.orgChart) + '">Chaîne de commandement</a><a class="ed-btn" href="' + esc(URLS.roles) + '">Rôles et accès</a></div>';
        zn.innerHTML = h;
    }

    // ——— Rendu : zone principale ———
    function orgCard(u, cls, root) {
        var n = u ? memberCount(u.id) : S.data.members.length;
        var set = u ? descend(u.id) : null;
        var act = S.data.members.filter(function (m) { return (!set || set[m.unitId]) && isAccount(m); }).length;
        var pct = n ? Math.round(act * 100 / n) : 0;
        var on = u ? S.unitFilter === u.id : S.unitFilter === 0;
        var code = u ? (u.code || u.name.slice(0, 3).toUpperCase()) : 'ORG';
        return '<button type="button" class="ed-org-card' + (root ? ' ed-org-card--root' : '') + (on ? ' is-on' : '') + '" aria-pressed="' + on + '" data-fn="' + reg(function () { S.unitFilter = u ? u.id : 0; S.activeShortcut = null; render(); }) + '"><span class="ed-org-tile">' + esc(String(code).slice(0, 4)) + '</span><span class="ed-org-body"><span class="ed-org-name">' + esc(u ? u.name : CFG.community) + '</span><span class="ed-org-line"><span>' + esc(u ? (u.commander || 'Chef absent') : 'Communauté') + '</span><b>' + act + '/' + n + '</b></span><span class="ed-org-bar"><span style="width:' + pct + '%"></span></span></span></button>';
    }
    function renderOrgChart() {
        var kids = U.roots.slice(0, 12);
        if (!S.showOrg) { return '<div class="ed-panel-head" style="margin:0"><span class="ed-side-h">Organigramme masqué</span><button type="button" class="ed-tb" data-fn="' + reg(function () { S.showOrg = true; render(); }) + '">Afficher l’organigramme</button></div>'; }
        var cols = Math.min(kids.length, 6) || 1;
        var h = '<section class="ed-org" aria-label="Organigramme"><div class="ed-panel-head"><span class="ed-side-h ed-panel-head__t">Organigramme</span><span class="ed-panel-head__hint">Cliquez une unité ou une sous-unité pour filtrer le tableau</span>';
        if (S.unitFilter !== 0) { h += '<button type="button" class="ed-chip is-on" data-fn="' + reg(function () { S.unitFilter = 0; render(); }) + '">Filtre : ' + esc(unitLabel(S.unitFilter)) + ' ×</button>'; }
        h += '<span class="ed-spacer"></span><button type="button" class="ed-tb" data-fn="' + reg(function () { S.showOrg = false; render(); }) + '">Masquer l’organigramme</button></div>';
        h += '<div style="display:flex;justify-content:center">' + orgCard(null, '', true) + '</div><div class="ed-org-v"></div>';
        h += '<div class="ed-org-kids" style="grid-template-columns:repeat(' + cols + ',minmax(0,1fr))"><span class="ed-org-h" style="left:' + (50 / cols) + '%;right:' + (50 / cols) + '%"></span>';
        kids.forEach(function (u) {
            h += '<div class="ed-org-col"><span class="ed-org-stub"></span>' + orgCard(u, '', false) + '<ul class="ed-org-subs">';
            (U.kids[u.id] || []).slice(0, 8).forEach(function (s) {
                h += '<li><button type="button" class="ed-org-sub' + (S.unitFilter === s.id ? ' is-on' : '') + '" data-fn="' + reg(function () { S.unitFilter = s.id; S.activeShortcut = null; render(); }) + '">' + esc(s.name) + '<span>' + memberCount(s.id) + '</span></button></li>';
            });
            h += '</ul></div>';
        });
        h += '</div><p class="ed-panel-foot"><i></i>' + esc(plural(U.roots.length, 'unité principale', 'unités principales') + ' · ' + S.data.units.length + ' au total · barre : part de comptes actifs') + '</p></section>';
        return h;
    }
    function cellHtml(c, r, rid) {
        var v = c.v(r); var cls = 'ed-cell' + (c.num ? ' is-num' : '') + (c.mono ? ' is-mono' : '');
        var sel = S.sel && String(S.sel.id) === String(rid) && S.sel.key === c.k;
        var inner;
        var m = V.sh.member(r);
        if (c.av && m) {
            inner = '<button type="button" class="ed-av-btn" aria-label="Fiche rapide de ' + esc(v) + '" data-fn="' + reg(function (e) { e.stopPropagation(); openMember(m.id); }) + '">' + avatar(m.id, 24) + '<span class="ed-av-name">' + esc(v) + '</span></button>';
        } else if (c.tag) {
            inner = tag(v, c.tone(r));
        } else if (c.bar) {
            inner = '<span class="ed-minibar">' + esc(v) + '<i><b style="width:' + Math.max(0, Math.min(100, Number(v) || 0)) + '%"></b></i></span>';
        } else {
            inner = esc(v);
        }
        return '<td role="gridcell" class="' + cls + (sel ? ' is-sel' : '') + '" tabindex="' + (sel ? 0 : -1) + '" aria-selected="' + !!sel + '" data-rid="' + esc(rid) + '" data-k="' + esc(c.k) + '"' + (c.title && c.title(r) ? ' title="' + esc(c.title(r)) + '"' : '') + '>' + inner + '</td>';
    }
    function renderGrid() {
        var sh = V.sh; var cols = V.cols; var h = '';
        var allOn = V.dataRows.length > 0 && V.dataRows.every(function (i) { return S.checked[i.r.id]; });
        h += '<table class="ed-grid ' + (S.dense ? 'is-dense' : 'is-comfy') + '" role="grid" aria-label="' + esc(sh.label) + '" aria-rowcount="' + (V.dataRows.length + 1) + '" data-grid><thead><tr class="ed-letters" role="row"><th class="ed-rownum" role="presentation"></th><th class="ed-check" role="presentation"></th>';
        cols.forEach(function (c, i) { h += '<th role="presentation">' + letters(i) + '</th>'; });
        h += '</tr><tr class="ed-heads" role="row"><th class="ed-rownum" role="columnheader">#</th><th class="ed-check" role="columnheader">' + (S.tab === 'orbat' ? '' : '<input type="checkbox" aria-label="Tout sélectionner"' + (allOn ? ' checked' : '') + ' data-chg="' + reg(function (e, el) { V.dataRows.forEach(function (i) { if (el.checked) { S.checked[i.r.id] = true; } else { delete S.checked[i.r.id]; } }); render(); }) + '">') + '</th>';
        cols.forEach(function (c) {
            var sorted = S.sortKey === c.k; var cf = (S.cf[S.tab] || {})[c.k];
            h += '<th role="columnheader" aria-sort="' + (sorted ? (S.sortDir > 0 ? 'ascending' : 'descending') : 'none') + '"><div class="ed-head"><button type="button" class="ed-head-btn' + (sorted ? ' is-sorted' : '') + (c.num ? ' is-num' : '') + (c.data ? ' is-data' : '') + '" title="Trier par ' + esc(c.l) + '" data-fn="' + reg(function () { sortBy(c.k, sorted && S.sortDir > 0 ? -1 : 1); }) + '"><span class="ed-type" aria-hidden="true">' + esc(c.type || 'Aa') + '</span>' + esc(c.l) + '<span class="ed-arrow" aria-hidden="true">' + (sorted ? (S.sortDir > 0 ? '▲' : '▼') : '') + '</span></button>' +
                '<button type="button" class="ed-fbtn' + (cf && cf.length ? ' is-on' : '') + '" aria-label="Filtrer la colonne ' + esc(c.l) + '" title="Filtrer la colonne ' + esc(c.l) + '" data-fn="' + reg(function (e, el) { openFmenu(c.k, el); }) + '">' + ICON.filter + '</button></div></th>';
        });
        h += '</tr></thead><tbody>';
        V.items.forEach(function (it) {
            if (it.t === 'group') {
                h += '<tr class="ed-group" role="row"><td colspan="' + (cols.length + 2) + '" role="gridcell"><button type="button" class="ed-group-btn" aria-expanded="' + !it.collapsed + '" data-fn="' + reg(function () { S.collapsed[S.tab + '|' + it.label] = !it.collapsed; render(); }) + '"><span style="width:12px;color:#0b8a5c" aria-hidden="true">' + (it.collapsed ? '▸' : '▾') + '</span>' + esc(it.label) + '<span class="ed-group-n">' + it.count + '</span></button></td></tr>';
                return;
            }
            var r = it.r; var rid = sh.id(r); var selR = S.sel && String(S.sel.id) === String(rid);
            var m = sh.member(r);
            h += '<tr role="row" aria-rowindex="' + (it.n + 1) + '" aria-selected="' + !!selR + '" class="ed-row' + (selR ? ' is-row-sel' : '') + (m && m.status === 'inactive' ? ' is-muted' : '') + '" data-rid="' + esc(rid) + '"><td class="ed-rownum" role="rowheader">' + it.n + '</td><td class="ed-check" role="gridcell">' + (m && S.tab !== 'orbat' ? '<input type="checkbox" aria-label="Sélectionner la ligne ' + it.n + '"' + (S.checked[m.id] ? ' checked' : '') + ' data-chg="' + reg(function (e, el) { if (el.checked) { S.checked[m.id] = true; } else { delete S.checked[m.id]; } renderToolbar(); }) + '">' : '') + '</td>';
            cols.forEach(function (c) { h += cellHtml(c, r, rid); });
            h += '</tr>';
        });
        if (!V.items.length) { h += '<tr class="ed-empty"><td colspan="' + (cols.length + 2) + '">Aucune ligne ne correspond aux filtres.</td></tr>'; }
        return h + '</tbody></table>';
    }
    function renderBoard() {
        var lanes = [['fiche', 'Sans compte'], ['invitee', 'Invitation envoyée'], ['en_attente', 'Compte à activer'], ['actif', 'Compte actif']];
        var ms = V.sh.rows;
        var h = '<div class="ed-board">';
        lanes.forEach(function (ln) {
            var items = ms.filter(function (m) { return ln[0] === 'actif' ? isAccount(m) : m.account === ln[0]; });
            h += '<section class="ed-lane" aria-label="' + esc(ln[1]) + '"><div class="ed-lane-h"><span>' + esc(ln[1]) + '</span><span>' + items.length + '</span></div>';
            items.slice(0, 40).forEach(function (m) {
                var okMail = !!m.email || isAccount(m);
                h += '<article class="ed-pcard"><div class="ed-pcard__top">' + avatar(m.id, 26) + '<button type="button" class="ed-av-btn" data-fn="' + reg(function () { openMember(m.id); }) + '"><span class="ed-av-name" style="font-size:12px;font-weight:800">' + esc(m.callsign || m.last || '—') + '</span></button><span class="ed-pcard__due">' + esc(m.inviteAt ? fmtDate(m.inviteAt) : '') + '</span></div>' +
                    '<span class="ed-pcard__detail">' + esc([m.grade, [m.first, m.last].join(' ').trim(), m.unit].filter(Boolean).join(' · ')) + '</span>' +
                    '<div class="ed-pcard__checks"><span class="ed-check-line" style="color:' + (okMail ? '#0b6b47' : '#8a5a06') + '"><span aria-hidden="true">' + (okMail ? '✓' : '○') + '</span>' + (isAccount(m) ? 'Compte actif' : (m.email ? 'E-mail rattaché' : 'E-mail à rattacher')) + '</span>' +
                    '<span class="ed-check-line" style="color:' + (m.completion >= 100 ? '#0b6b47' : '#8a5a06') + '"><span aria-hidden="true">' + (m.completion >= 100 ? '✓' : '○') + '</span>Dossier ' + m.completion + ' %</span></div>' +
                    '<div class="ed-pcard__acts">';
                if (CFG.canInvite && (m.account === 'fiche' || m.account === 'invitee')) { h += '<button type="button" class="ed-btn ed-btn--solid ed-btn--sm" data-fn="' + reg(function () { invite(m); }) + '">' + (m.account === 'invitee' || m.inviteExpired ? 'Relancer' : 'Inviter') + '</button>'; }
                h += '<button type="button" class="ed-btn ed-btn--sm" data-fn="' + reg(function () { openMember(m.id); }) + '">Fiche</button></div></article>';
            });
            if (items.length > 40) { h += '<p class="ed-lane__more">+ ' + (items.length - 40) + ' autres : filtrez par unité pour les voir.</p>'; }
            h += '<div class="ed-lane__end"></div></section>';
        });
        return h + '</div>';
    }
    function renderMain() {
        var zn = z('main');
        var h = '';
        if (S.loading) { zn.innerHTML = '<p class="ed-loading">Chargement du classeur…</p>'; return; }
        if (S.error) { zn.innerHTML = '<p class="ed-loading">' + esc(S.error) + ' <button type="button" class="ed-btn" data-fn="' + reg(function () { load(false); }) + '">Réessayer</button></p>'; return; }
        if (S.tab === 'proc') { zn.innerHTML = renderBoard(); return; }
        if (S.tab === 'orbat') { h += renderOrgChart(); }
        zn.innerHTML = h + '<div class="ed-main__scroll" data-scroll>' + renderGrid() + '</div>';
    }

    // ——— Rendu : dossier ———
    function renderDossier() {
        var zn = z('dossier');
        zn.hidden = !S.showPanel;
        zn.classList.toggle('is-collapsed', collapsedPanels.dossier);
        if (!S.showPanel) { zn.innerHTML = ''; return; }
        var m = selMember();
        var h = '<div class="ed-panel-h"><span class="ed-side-h">Dossier</span><span><button type="button" class="ed-tb ed-panel-toggle" aria-expanded="' + !collapsedPanels.dossier + '" aria-label="Replier ou déplier le dossier" data-fn="' + reg(function () { collapsedPanels.dossier = !collapsedPanels.dossier; render(); }) + '">' + (collapsedPanels.dossier ? '▾' : '▴') + '</button><button type="button" class="ed-tb" aria-label="Fermer le panneau dossier" style="height:24px;min-width:24px" data-fn="' + reg(function () { S.showPanel = false; render(); }) + '">×</button></span></div>';
        if (!m) {
            var rw = selRow();
            h += '<p class="ed-empty-note">' + (rw ? 'Cette ligne est une unité : sélectionnez une ligne avec un membre pour ouvrir son dossier.' : 'Sélectionnez une ligne pour ouvrir le dossier.') + '</p>';
            zn.innerHTML = h; return;
        }
        h += '<div class="ed-side-sec" style="gap:10px"><div style="display:flex;gap:11px;align-items:center">' + avatar(m.id, 52) + '<div style="display:flex;flex-direction:column;gap:2px;min-width:0"><span style="font-size:9px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:#0b6b47">' + esc(m.callsign || '—') + '</span><span style="font-size:15px;font-weight:900;letter-spacing:-.02em">' + esc([m.grade, m.last, m.first].filter(Boolean).join(' ') || '—') + '</span><span style="font-family:var(--ed-mono);font-size:10.5px;font-weight:700;color:#6d7a80">' + esc((m.matricule || '—') + ' · ' + m.seniorityDays + ' j') + '</span></div></div>' +
            '<div style="display:flex;gap:6px;flex-wrap:wrap">' + tag(m.statusLabel, tone('status', m.status)) + tag(m.accountLabel, tone('account', m.account)) + '</div>' +
            '<div class="ed-facts">' + [['Unité', m.unit || 'Sans unité'], ['Emploi', m.emploi || '—'], ['Grade', m.gradeLong || m.grade || '—'], ['Ancienneté', m.seniority || '—'], ['E-mail rattaché', m.email || '—'], ['Dossier', m.completion + ' %']].map(function (f) { return '<div><div class="ed-side-h">' + esc(f[0]) + '</div><div>' + esc(f[1]) + '</div></div>'; }).join('') + '</div>' +
            '<button type="button" class="ed-btn ed-btn--solid" data-fn="' + reg(function () { openMember(m.id); }) + '">Fiche rapide</button>';
        if (CFG.canInvite && (m.account === 'fiche' || m.account === 'invitee')) { h += '<button type="button" class="ed-btn" data-fn="' + reg(function () { invite(m); }) + '">' + (m.account === 'invitee' || m.inviteExpired ? 'Relancer l’invitation' : 'Inviter à créer son compte') + '</button>'; }
        h += '<a class="ed-btn" href="' + esc(m.ficheUrl) + '">Ouvrir la fiche complète</a></div>';
        h += '<div class="ed-side-sec"><span class="ed-side-h">Activité en jeu</span>' + [['Heures de jeu', hours(m.playSeconds)], ['Heures de vol', hours(m.pilotSeconds)], ['Présence', m.presence + ' %'], ['Disponibilité', m.availability + ' %']].map(function (a) { return '<div class="ed-kv"><span>' + esc(a[0]) + '</span><span>' + esc(a[1]) + '</span></div>'; }).join('') + '</div>';
        h += '<div class="ed-side-sec"><span class="ed-side-h">Qualifications</span>' + (m.qualItems.length ? m.qualItems.slice(0, 8).map(function (q) { return '<div class="ed-kv"><span>' + esc(q.name + (q.level ? ' ' + q.level : '')) + '</span>' + tag(q.temporal || 'Valide', /expir/i.test(q.temporal) ? 'warn' : 'ok') + '</div>'; }).join('') : '<span style="font-size:11.5px;font-weight:600;color:#6d7a80">Aucune qualification obtenue.</span>') + '</div>';
        h += '<div class="ed-side-sec"><span class="ed-side-h">Complétude du dossier</span><div class="ed-bar"><span style="width:' + Math.max(0, Math.min(100, m.completion)) + '%"></span></div><span style="font-size:11.5px;font-weight:700">' + m.completion + ' %</span></div>';
        zn.innerHTML = h;
    }

    // ——— Rendu : pied ———
    function statusLine() {
        var sh = V.sh;
        if (S.tab === 'proc') { return plural(sh.rows.length, 'fiche', 'fiches') + ' · 4 étapes'; }
        var line = V.rows.length + ' / ' + sh.rows.length + ' lignes';
        var c = S.sel ? colByKey(sh, S.sel.key) : null;
        if (c && c.num) {
            var vals = V.rows.map(function (r) { return Number((c.sortv || c.v)(r)); }).filter(function (x) { return !isNaN(x); });
            var sum = vals.reduce(function (a, b) { return a + b; }, 0);
            line = c.l + ' — Somme ' + sum.toLocaleString('fr-FR') + ' · Moyenne ' + (vals.length ? Math.round(sum / vals.length) : 0) + ' · Nb ' + vals.length + '   |   ' + line;
        } else if (c) { line = c.l + ' — Nb ' + V.rows.length + '   |   ' + line; }
        return line;
    }
    function renderFooter() {
        var h = '<a class="ed-tab" aria-label="Construire un nouveau tableau" title="Nouveau tableau" href="' + esc(URLS.classic + '/tableaux') + '" style="font-size:16px;padding:0 12px;text-decoration:none">+</a><div role="tablist" aria-label="Feuilles" style="display:flex">';
        TAB_ORDER.forEach(function (k) {
            var s = V.all[k]; var n = k === 'proc' ? s.rows.length : s.rows.length;
            h += '<button type="button" role="tab" class="ed-tab" aria-selected="' + (k === S.tab) + '" data-fn="' + reg(function () { goTab(k); }) + '">' + esc(s.label) + '<span class="ed-tab-n">' + n + '</span></button>';
        });
        h += '</div><span class="ed-spacer"></span><span class="ed-status">' + esc(statusLine()) + '<span><span class="ed-net__dot" style="background:' + (S.online ? '#12d18e' : '#f5b942') + '"></span>' + (S.online ? 'En ligne' : 'Hors ligne') + '</span></span>';
        z('footer').innerHTML = h;
    }

    // ——— Rendu : fenêtres ———
    function palResults() {
        var q = norm(S.pq.trim()); var out = [];
        if (S.pf === 'all' || S.pf === 'mem') { S.data.members.forEach(function (m) { out.push({ kind: 'Membre', label: [m.grade, m.last, m.first].filter(Boolean).join(' ') || m.callsign, meta: m.callsign || m.matricule, hay: [m.callsign, m.first, m.last, m.matricule, m.unit, m.emploi, m.email].join(' '), m: m, run: function () { S.pal = false; openMember(m.id); } }); }); }
        if (S.pf === 'all' || S.pf === 'unit') { S.data.units.forEach(function (u) { out.push({ kind: 'Unité', label: u.name, meta: u.code || memberCount(u.id) + '', hay: [u.name, u.code, u.commander, u.path].join(' '), u: u, run: function () { S.pal = false; S.unitFilter = u.id; goTab('reg', 0); } }); }); }
        if (S.pf === 'all' || S.pf === 'act') {
            [['Synchroniser maintenant', 'Données', function () { sync(false); }], ['Afficher les fiches sans compte', 'Vue', function () { goTab('reg', 2); }], ['Invitations à relancer', 'Vue', function () { goTab('fiches', 3); }], ['Ouvrir le tableau des fiches et invitations', 'Vue', function () { goTab('fiches', 0); }], ['Passer en gestion d’organisation', 'Périmètre', function () { S.scope = 'org'; render(); }]]
                .forEach(function (a) { out.push({ kind: 'Action', label: a[0], meta: a[1], hay: a[0] + ' ' + a[1], run: function () { S.pal = false; a[2](); render(); } }); });
        }
        return (q ? out.filter(function (r) { return norm(r.hay).indexOf(q) >= 0; }) : out).slice(0, 40);
    }
    function palPreview(r) {
        if (!r) { return ''; }
        var h = '';
        if (r.m) {
            var m = r.m;
            h += '<div style="display:flex;gap:12px;align-items:center">' + avatar(m.id, 64) + '<div><div class="ed-pal-prev__title">' + esc(r.label) + '</div><div class="ed-pal-prev__sub">' + esc((m.matricule || '—') + ' · ' + (m.callsign || '—')) + '</div></div></div><div>' + tag(m.statusLabel, tone('status', m.status)) + ' ' + tag(m.accountLabel, tone('account', m.account)) + '</div>' +
                [['Unité', m.unit || '—'], ['Emploi', m.emploi || '—'], ['Ancienneté', m.seniority || '—'], ['Dossier', m.completion + ' %']].map(function (x) { return '<div class="ed-pal-row"><span>' + esc(x[0]) + '</span><span>' + esc(x[1]) + '</span></div>'; }).join('');
        } else if (r.u) {
            h += '<div class="ed-pal-prev__title">' + esc(r.label) + '</div><div class="ed-pal-prev__sub">' + esc(r.u.path) + '</div>' + [['Chef', r.u.commander || '—'], ['Effectif', memberCount(r.u.id)]].map(function (x) { return '<div class="ed-pal-row"><span>' + esc(x[0]) + '</span><span>' + esc(x[1]) + '</span></div>'; }).join('');
        } else { h += '<div class="ed-pal-prev__title">' + esc(r.label) + '</div><div class="ed-pal-prev__sub">' + esc(r.meta) + '</div>'; }
        return h;
    }
    function renderPalResults() {
        var res = palResults(); S.pi = Math.max(0, Math.min(S.pi, res.length - 1));
        var list = root.querySelector('[data-pal-list]'); var prev = root.querySelector('[data-pal-prev]');
        if (!list) { return; }
        S.palRes = res;
        list.innerHTML = res.map(function (r, i) { return '<button type="button" role="option" class="ed-pal-res" aria-selected="' + (i === S.pi) + '" data-pi="' + i + '"><span class="ed-pal-kind">' + esc(r.kind) + '</span>' + (r.m ? avatar(r.m.id, 22) : '') + '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc(r.label) + '</span><span class="ed-pal-meta">' + esc(r.meta) + '</span></button>'; }).join('') + (res.length ? '' : '<p style="margin:20px;font-size:12px;color:#6d7a80">Aucun résultat pour « ' + esc(S.pq) + ' ».</p>');
        prev.innerHTML = palPreview(res[S.pi]);
    }
    function openPalette() { S.pal = true; S.pq = ''; S.pi = 0; S.menu = null; S.pop = null; render(); var i = root.querySelector('[data-pal-input]'); if (i) { i.focus(); } }
    function renderOverlay() {
        var h = '';
        if (S.menu || S.pop) { h += '<button type="button" class="ed-click-away ed-click-away--menu" aria-label="Fermer" data-fn="' + reg(function () { S.menu = null; S.pop = null; render(); }) + '"></button>'; }
        if (S.pal) {
            h += '<div class="ed-overlay"><button type="button" class="ed-overlay__bg" aria-label="Fermer la recherche" data-fn="' + reg(function () { S.pal = false; render(); }) + '"></button><section class="ed-dialog ed-dialog--pal" role="dialog" aria-modal="true" aria-label="Recherche"><div class="ed-pal-top"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0b8a5c" stroke-width="2.4" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg><input aria-label="Rechercher" placeholder="Membre, indicatif, matricule, unité, action…" value="' + esc(S.pq) + '" data-pal-input autocomplete="off">' +
                [['all', 'Tout'], ['mem', 'Membres'], ['unit', 'Unités'], ['act', 'Actions']].map(function (f) { return '<button type="button" class="ed-tb' + (S.pf === f[0] ? ' is-on' : '') + '" aria-pressed="' + (S.pf === f[0]) + '" data-fn="' + reg(function () { S.pf = f[0]; S.pi = 0; renderPalResults(); renderOverlayFilters(); }) + '">' + esc(f[1]) + '</button>'; }).join('') +
                '</div><div class="ed-pal-body"><div class="ed-pal-list" role="listbox" aria-label="Résultats" data-pal-list></div><div class="ed-pal-prev" aria-live="polite"><span class="ed-side-h">Aperçu</span><div data-pal-prev style="display:flex;flex-direction:column;gap:12px"></div><span class="ed-pal-hint">↑ ↓ pour parcourir · Entrée pour ouvrir · Échap pour fermer</span></div></div></section></div>';
        }
        if (S.modal) { h += ficheHtml(); }
        if (S.dataOpen) { h += dataHtml(); }
        if (S.fmenu) { h += fmenuHtml(); }
        z('overlay').innerHTML = h;
        if (S.pal) { renderPalResults(); }
    }
    function renderOverlayFilters() { render({ keepPal: true }); }
    function ficheHtml() {
        var m = memberById(S.modal); if (!m) { return ''; }
        var list = V.dataRows.length && V.sh.member(V.dataRows[0].r) ? V.dataRows : [];
        var tabs = [['profil', 'Profil'], ['qual', 'Qualifications'], ['compte', 'Compte et invitation']];
        var body = '';
        if (S.mtab === 'profil') {
            body = '<div class="ed-fiche-grid">' + [['Indicatif', m.callsign || '—'], ['Matricule', m.matricule || '—'], ['Grade', m.gradeLong || m.grade || '—'], ['Unité', m.unitPath || 'Sans unité'], ['Emploi', m.emploi || '—'], ['Ancienneté', m.seniority || '—'], ['Présence', m.presence + ' %'], ['Disponibilité', m.availability + ' %'], ['Dossier', m.completion + ' %']].map(function (f) { return '<div><div class="ed-side-h">' + esc(f[0]) + '</div><div>' + esc(f[1]) + '</div></div>'; }).join('') + '</div>';
        } else if (S.mtab === 'qual') {
            body = m.qualItems.length ? m.qualItems.map(function (q) { return '<div class="ed-fiche-line"><span>' + esc(q.name + (q.level ? ' ' + q.level : '')) + '</span>' + tag(q.temporal || 'Valide', /expir/i.test(q.temporal) ? 'warn' : 'ok') + '</div>'; }).join('') : '<span style="font-size:12px;font-weight:600;color:#6d7a80">Aucune qualification obtenue.</span>';
        } else {
            body = '<div class="ed-fiche-grid">' + [['Compte', m.accountLabel], ['E-mail rattaché', m.email || '—'], ['Invitation du', fmtDate(m.inviteAt)], ['Origine', m.source || '—'], ['Dernière activité', fmtDate(m.lastActivity)]].map(function (f) { return '<div><div class="ed-side-h">' + esc(f[0]) + '</div><div>' + esc(f[1]) + '</div></div>'; }).join('') + '</div>';
            if (CFG.canInvite && (m.account === 'fiche' || m.account === 'invitee')) { body += '<div><button type="button" class="ed-btn ed-btn--solid" data-fn="' + reg(function () { invite(m); }) + '">' + (m.account === 'invitee' || m.inviteExpired ? 'Relancer l’invitation' : 'Inviter à créer son compte') + '</button></div>'; }
        }
        return '<div class="ed-overlay"><button type="button" class="ed-overlay__bg" aria-label="Fermer la fiche" data-fn="' + reg(closeModal) + '"></button><section class="ed-dialog ed-dialog--fiche" role="dialog" aria-modal="true" aria-label="Fiche rapide"><div class="ed-fiche-head">' + avatar(m.id, 92) + '<div class="ed-fiche-head__t"><span class="ed-fiche-head__ind">Fiche rapide · ' + esc(m.callsign || '—') + '</span><span class="ed-fiche-head__name">' + esc([m.grade, m.last, m.first].filter(Boolean).join(' ') || '—') + '</span><span class="ed-fiche-head__meta">' + esc((m.matricule || '—') + ' · ' + (m.unit || 'Sans unité') + ' · ' + (m.emploi || '—')) + '</span><div style="display:flex;gap:6px;margin-top:4px">' + tag(m.statusLabel, tone('status', m.status)) + tag(m.accountLabel, tone('account', m.account)) + '</div></div><button type="button" class="ed-fiche-close" aria-label="Fermer" data-fn="' + reg(closeModal) + '" data-modal-close>×</button></div>' +
            '<div class="ed-mtabs" role="tablist">' + tabs.map(function (t) { return '<button type="button" role="tab" class="ed-mtab" aria-selected="' + (S.mtab === t[0]) + '" data-fn="' + reg(function () { S.mtab = t[0]; render(); }) + '">' + esc(t[1]) + '</button>'; }).join('') + '</div><div class="ed-fiche-body">' + body + '</div>' +
            '<div class="ed-fiche-foot"><a class="ed-btn ed-btn--solid" href="' + esc(m.ficheUrl) + '">Ouvrir le dossier complet</a><span class="ed-spacer"></span><button type="button" class="ed-btn" data-fn="' + reg(function () { stepMember(-1); }) + '">← Précédent</button><button type="button" class="ed-btn" data-fn="' + reg(function () { stepMember(1); }) + '">Suivant →</button></div></section></div>';
    }
    function stepMember(dir) {
        var ids = V.dataRows.map(function (i) { var m = V.sh.member(i.r); return m ? m.id : 0; }).filter(Boolean);
        if (!ids.length) { return; }
        var i = ids.indexOf(S.modal); S.modal = ids[(i + dir + ids.length) % ids.length];
        S.sel = { id: S.modal, key: S.sel ? S.sel.key : 'nom' }; render();
    }
    function closeModal() { var id = S.modal; S.modal = null; render(); var c = id ? root.querySelector('td[data-rid="' + id + '"].is-sel') : null; if (c) { c.focus(); } }
    function openMember(id) { S.modal = id; S.mtab = 'profil'; S.pal = false; S.menu = null; S.pop = null; if (V.sh && V.sh.member && S.tab !== 'orbat') { S.sel = { id: id, key: S.sel ? S.sel.key : 'nom' }; } render(); var b = root.querySelector('[data-modal-close]'); if (b) { b.focus(); } }
    function dataHtml() {
        return '<div class="ed-overlay"><button type="button" class="ed-overlay__bg" aria-label="Fermer" data-fn="' + reg(function () { S.dataOpen = false; render(); }) + '"></button><section class="ed-dialog ed-dialog--data" role="dialog" aria-modal="true" aria-label="Ajouter des données"><div class="ed-dialog-h"><div><span class="ed-side-h ed-panel-head__t">Sources de données</span><b>Ajouter des colonnes au tableau</b></div><button type="button" class="ed-tb" aria-label="Fermer" data-fn="' + reg(function () { S.dataOpen = false; render(); }) + '">×</button></div><div class="ed-data-body">' +
            DATASETS.map(function (d) { var on = !!S.extra[d.id]; return '<div class="ed-data-row"><span class="ed-data-ico">' + esc(d.ico) + '</span><div style="flex:1;display:flex;flex-direction:column;gap:2px"><span style="font-size:13px;font-weight:800">' + esc(d.label) + '</span><span style="font-size:11.5px;font-weight:600;color:#6d7a80">' + esc(d.src) + '</span></div><button type="button" class="ed-btn' + (on ? '' : ' ed-btn--solid') + '" data-fn="' + reg(function () { S.extra[d.id] = !on; render(); }) + '">' + (on ? 'Retirer' : 'Ajouter') + '</button></div>'; }).join('') +
            '<p>Les colonnes ajoutées se placent à droite du registre et des qualifications. Elles se trient, se filtrent et se regroupent comme les autres.</p></div></section></div>';
    }
    function fmenuHtml() {
        var f = S.fmenu; var c = colByKey(V.sh, f.k); if (!c) { return ''; }
        var all = {}; V.sh.rows.forEach(function (r) { var v = String(c.v(r)); all[v] = (all[v] || 0) + 1; });
        var ex = (S.cf[S.tab] || {})[f.k] || []; var q = norm(S.fsearch);
        var vals = Object.keys(all).sort(function (a, b) { return collator.compare(a, b); }).filter(function (v) { return !q || norm(v).indexOf(q) >= 0; }).slice(0, 200);
        var setEx = function (arr) { S.cf[S.tab] = S.cf[S.tab] || {}; S.cf[S.tab][f.k] = arr; render(); };
        var h = '<button type="button" class="ed-click-away" aria-label="Fermer le filtre" data-fn="' + reg(closeFmenu) + '"></button><div class="ed-fmenu" role="dialog" aria-label="Filtre de la colonne ' + esc(c.l) + '" style="left:' + f.x + 'px;top:' + f.y + 'px"><div class="ed-fmenu__h"><span class="ed-side-h">' + esc(c.l) + '</span><button type="button" class="ed-tb" aria-label="Fermer" style="height:24px;min-width:24px" data-fn="' + reg(closeFmenu) + '">×</button></div><div class="ed-fmenu__sec">' +
            '<button type="button" class="ed-mb-row" data-fn="' + reg(function () { closeFmenu(); sortBy(f.k, 1); }) + '"><span class="ed-mb-check"></span>Trier de A à Z</button><button type="button" class="ed-mb-row" data-fn="' + reg(function () { closeFmenu(); sortBy(f.k, -1); }) + '"><span class="ed-mb-check"></span>Trier de Z à A</button>' +
            '<button type="button" class="ed-mb-row" data-fn="' + reg(function () { S.hiddenCols[S.tab] = S.hiddenCols[S.tab] || {}; S.hiddenCols[S.tab][f.k] = true; S.fmenu = null; render(); }) + '"><span class="ed-mb-check"></span>Masquer la colonne</button></div>' +
            '<div class="ed-fmenu__search"><span class="ed-side-h">Filtrer par valeur</span><input class="ed-input" type="search" placeholder="Rechercher une valeur…" aria-label="Rechercher une valeur" value="' + esc(S.fsearch) + '" data-fsearch><div class="ed-fmenu__links"><button type="button" data-fn="' + reg(function () { setEx([]); }) + '">Tout sélectionner</button><button type="button" data-fn="' + reg(function () { setEx(Object.keys(all)); }) + '">Effacer</button></div></div><div class="ed-fmenu__list">';
        vals.forEach(function (v) {
            var on = ex.indexOf(v) < 0;
            h += '<div class="ed-fmenu__item"><label><input type="checkbox"' + (on ? ' checked' : '') + ' data-chg="' + reg(function () { setEx(on ? ex.concat([v]) : ex.filter(function (x) { return x !== v; })); }) + '"><span>' + esc(v) + '</span></label><span class="ed-fmenu__n">' + all[v] + '</span><button type="button" class="ed-fmenu__only" title="Afficher seulement ' + esc(v) + '" data-fn="' + reg(function () { setEx(Object.keys(all).filter(function (x) { return x !== v; })); }) + '">seul</button></div>';
        });
        return h + '</div></div>';
    }
    function openFmenu(k, el) {
        var r = el.getBoundingClientRect(); var a = root.getBoundingClientRect();
        S.fmenu = { k: k, x: Math.max(4, Math.min(r.left - a.left - 200, a.width - 290)), y: r.bottom - a.top + 2 }; S.fsearch = ''; render();
    }
    function closeFmenu() { S.fmenu = null; render(); }

    // ——— Rendu : notification et amorçage ———
    function toast(title, text, extra) { S.toast = { k: title, t: text, extra: extra || null }; renderToast(); }
    function renderToast() {
        var zn = z('toast');
        if (!S.toast) { zn.hidden = true; zn.innerHTML = ''; return; }
        zn.hidden = false;
        var acts = '';
        if (S.toast.extra && S.toast.extra.url) { acts += '<button type="button" class="is-main" data-fn="' + reg(function () { copyText(S.toast.extra.url); }) + '">Copier le lien</button>'; }
        zn.innerHTML = '<span class="ed-toast__t">' + esc(S.toast.k) + '</span><span class="ed-toast__m">' + esc(S.toast.t) + '</span><div class="ed-toast__acts">' + acts + '<button type="button" data-fn="' + reg(function () { S.toast = null; renderToast(); }) + '">OK</button></div>';
    }
    function copyText(t) {
        try { navigator.clipboard.writeText(t).then(function () { toast('Lien', 'Lien copié dans le presse-papiers.'); }, function () { toast('Lien', t); }); } catch (e) { toast('Lien', t); }
    }
    var BOOT = ['Connexion à la communauté', 'Registre nominatif', 'Structure des unités', 'Qualifications et dossiers', 'Classeur prêt'];
    function renderBoot() {
        var zn = z('boot');
        zn.hidden = !S.booting;
        if (!S.booting) { return; }
        zn.querySelector('[data-boot-bar]').style.width = Math.round(S.bootStep * 100 / BOOT.length) + '%';
        zn.querySelector('[data-boot-steps]').innerHTML = BOOT.map(function (l, i) {
            var done = i < S.bootStep; var run = i === S.bootStep;
            return '<span class="ed-boot-step ' + (done ? 'is-done' : (run ? 'is-run' : '')) + '"><i aria-hidden="true">' + (done ? '✓' : (run ? '›' : '·')) + '</i>' + esc(l) + '</span>';
        }).join('');
    }

    // ——— Rendu global ———
    function render(opt) {
        opt = opt || {};
        REG = [];
        var hadFocus = document.activeElement; var inGrid = hadFocus && hadFocus.closest && hadFocus.closest('[data-grid]');
        var scrollEl = root.querySelector('[data-scroll]'); var sTop = scrollEl ? scrollEl.scrollTop : 0; var sLeft = scrollEl ? scrollEl.scrollLeft : 0;
        renderTitlebar(); renderBoot();
        if (!S.data) { renderMain(); return; }
        compute();
        renderMenubar(); renderToolbar(); renderShortcuts(); renderCards(); renderFilters(); renderFormula();
        renderTree(); renderMain(); renderDossier(); renderFooter(); renderOverlay(); renderToast();
        scrollEl = root.querySelector('[data-scroll]'); if (scrollEl) { scrollEl.scrollTop = sTop; scrollEl.scrollLeft = sLeft; }
        if (inGrid && !S.modal && !S.pal) { var c = root.querySelector('td.is-sel'); if (c) { c.focus({ preventScroll: true }); } }
        if (opt.keepPal) { var pi = root.querySelector('[data-pal-input]'); if (pi) { pi.focus(); } }
    }
    function lite() {
        // Sélection d'une case : met à jour seulement la grille (classes), la formule, le dossier et le pied.
        REG = []; compute();
        root.querySelectorAll('td.is-sel').forEach(function (td) { td.classList.remove('is-sel'); td.setAttribute('tabindex', '-1'); td.setAttribute('aria-selected', 'false'); });
        root.querySelectorAll('tr.is-row-sel').forEach(function (tr) { tr.classList.remove('is-row-sel'); tr.setAttribute('aria-selected', 'false'); });
        var cell = S.sel ? root.querySelector('td[data-rid="' + S.sel.id + '"][data-k="' + S.sel.key + '"]') : null;
        if (cell) { cell.classList.add('is-sel'); cell.setAttribute('tabindex', '0'); cell.setAttribute('aria-selected', 'true'); var tr = cell.parentNode; tr.classList.add('is-row-sel'); tr.setAttribute('aria-selected', 'true'); }
        renderFormula(); renderDossier(); renderFooter(); renderMenubar(); renderToolbar();
        return cell;
    }

    // ——— Actions ———
    function goTab(k, chip) {
        S.tab = k; S.chip = chip || 0; S.q = ''; S.sortKey = null; S.sortDir = 1; S.checked = {}; S.menu = null; S.pop = null; S.fmenu = null; S.groupBy = ''; S.activeShortcut = null; S.sel = null; S.pal = false;
        render();
    }
    function sortBy(k, dir) { S.sortKey = k; S.sortDir = dir; S.menu = null; render(); }
    function setOnline(on) {
        if (on === S.online) { return; }
        S.online = on;
        toast(on ? 'En ligne' : 'Hors ligne', on ? 'Connexion rétablie. Le classeur se met à jour.' : 'Vous travaillez hors ligne. Les données affichées restent consultables.');
        render();
        if (on) { sync(true); }
    }
    function load(auto) {
        return fetch(CFG.dataUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(function (res) {
            if (res.status === 403) { window.location.href = URLS.classic; throw new Error('forbidden'); }
            if (res.status === 401) { window.location.reload(); throw new Error('auth'); }
            if (!res.ok) { throw new Error('http'); }
            return res.json();
        }).then(function (d) {
            var before = S.data ? S.data.members.length : null;
            S.data = d; S.loading = false; S.error = ''; indexUnits();
            S.lastSync = clock(new Date());
            return before;
        });
    }
    function sync(auto) {
        if (S.syncing || !S.online || !S.data) { return; }
        S.syncing = true; S.nextSync = 60; renderTitlebar();
        load(auto).then(function (before) {
            S.syncing = false;
            if (!auto) {
                var diff = before === null ? 0 : S.data.members.length - before;
                toast('Synchronisation', diff ? Math.abs(diff) + (diff > 0 ? ' fiche(s) de plus.' : ' fiche(s) de moins.') : 'Aucune modification reçue. Le classeur est à jour.');
            }
            render();
        }).catch(function () { S.syncing = false; if (!auto) { toast('Synchronisation', 'Synchronisation impossible pour le moment.'); } renderTitlebar(); });
    }
    function post(url, body) {
        var fd = new URLSearchParams(body);
        fd.set('_csrf_token', CFG.csrf);
        return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' }, body: fd.toString() })
            .then(function (res) { return res.json().catch(function () { return { ok: false, message: 'Réponse illisible.' }; }); });
    }
    function inviteBody(m) { var b = { duration_hours: '48', single_use: '1' }; if (m.email) { b.send_email = '1'; } if (m.account === 'invitee' || m.inviteExpired) { b.relance = '1'; } return b; }
    function invite(m) {
        if (!CFG.canInvite) { return; }
        S.menu = null;
        post(URLS.inviteBase + '/' + m.id + '/invitations', inviteBody(m)).then(function (r) {
            toast(r.ok ? 'Invitation' : 'Invitation impossible', r.message || '', r.ok && r.url && !m.email ? { url: r.url } : null);
            if (r.ok) { sync(true); }
        }).catch(function () { toast('Invitation impossible', 'Le réseau n’a pas répondu.'); });
    }
    function bulkInvite(list) {
        var ok = 0; var ko = 0; var chain = Promise.resolve();
        list.slice(0, 50).forEach(function (m) { chain = chain.then(function () { return post(URLS.inviteBase + '/' + m.id + '/invitations', inviteBody(m)).then(function (r) { if (r.ok) { ok++; } else { ko++; } }).catch(function () { ko++; }); }); });
        chain.then(function () { S.checked = {}; toast('Invitations', ok + ' envoyée(s)' + (ko ? ', ' + ko + ' en échec.' : '.')); sync(true); });
    }

    // ——— Événements ———
    root.addEventListener('click', function (e) {
        var el = e.target.closest ? e.target.closest('[data-fn],[data-act],[data-pi]') : null;
        if (!el || !root.contains(el)) { return; }
        if (el.hasAttribute('data-pi')) { S.pi = Number(el.getAttribute('data-pi')); var r = S.palRes && S.palRes[S.pi]; if (r) { r.run(); } return; }
        if (el.hasAttribute('data-fn')) { var fn = REG[Number(el.getAttribute('data-fn'))]; if (fn) { fn(e, el); } return; }
        var a = el.getAttribute('data-act');
        if (a === 'scope') { S.scope = el.getAttribute('data-v'); S.unitFilter = 0; render(); }
        else if (a === 'palette') { openPalette(); }
        else if (a === 'toggle-net') { setOnline(!S.online); }
        else if (a === 'sync') { sync(false); }
    });
    root.addEventListener('change', function (e) {
        var el = e.target;
        if (el.matches && el.matches('[data-import-file]')) { if (el.files && el.files.length) { el.form.submit(); } return; }
        var c = el.closest ? el.closest('[data-chg]') : null;
        if (c) { var fn = REG[Number(c.getAttribute('data-chg'))]; if (fn) { fn(e, c); } }
    });
    root.addEventListener('input', function (e) {
        var el = e.target;
        if (el.hasAttribute('data-q-input')) { S.q = el.value; S.activeShortcut = null; REG = []; compute(); renderMain(); renderFooter(); renderFormula(); renderDossier(); renderCards(); renderTree(); renderMenubar(); renderToolbar(); }
        else if (el.hasAttribute('data-name-input')) { S.newName = el.value; }
        else if (el.hasAttribute('data-pal-input')) { S.pq = el.value; S.pi = 0; renderPalResults(); }
        else if (el.hasAttribute('data-fsearch')) { S.fsearch = el.value; var pos = el.selectionStart; render(); var n = root.querySelector('[data-fsearch]'); if (n) { n.focus(); n.setSelectionRange(pos, pos); } }
    });
    // Un clic dans la grille choisit la case ; un double clic ouvre la fiche rapide.
    root.addEventListener('mousedown', function (e) {
        var td = e.target.closest ? e.target.closest('td[data-rid][data-k]') : null;
        if (!td || e.target.closest('button,input,a')) { return; }
        var id = td.getAttribute('data-rid'); var key = td.getAttribute('data-k');
        S.sel = { id: /^\d+$/.test(id) ? Number(id) : id, key: key };
        var cell = lite(); if (cell) { cell.focus({ preventScroll: true }); }
    });
    root.addEventListener('dblclick', function (e) {
        var tr = e.target.closest ? e.target.closest('tr.ed-row') : null;
        var m = selMember(); if (tr && m && !e.target.closest('button,input,a')) { openMember(m.id); }
    });
    function moveSel(dr, dc, ends) {
        if (!S.sel || !V.dataRows.length) { return; }
        var ri = V.dataRows.findIndex(function (i) { return String(V.sh.id(i.r)) === String(S.sel.id); });
        var ci = V.cols.findIndex(function (c) { return c.k === S.sel.key; });
        if (ends === 'home') { ci = 0; } else if (ends === 'end') { ci = V.cols.length - 1; }
        ri = Math.max(0, Math.min(V.dataRows.length - 1, ri + dr)); ci = Math.max(0, Math.min(V.cols.length - 1, ci + dc));
        S.sel = { id: V.sh.id(V.dataRows[ri].r), key: V.cols[ci].k };
        var cell = lite(); if (cell) { cell.focus({ preventScroll: true }); cell.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
    }
    function moveTree(dir) {
        var items = Array.prototype.slice.call(root.querySelectorAll('[data-tree] [role="treeitem"]'));
        var i = items.indexOf(document.activeElement); if (i < 0) { i = 0; }
        var n = items[Math.max(0, Math.min(items.length - 1, dir === 'home' ? 0 : (dir === 'end' ? items.length - 1 : i + dir)))]; if (n) { n.focus(); }
    }
    root.addEventListener('keydown', function (e) {
        var t = e.target; var typing = t.matches && t.matches('input:not([type=checkbox]),select,textarea');
        if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); openPalette(); return; }
        if (e.key === 'F5') { e.preventDefault(); sync(false); return; }
        if (S.pal) {
            if (e.key === 'Escape') { e.preventDefault(); S.pal = false; render(); return; }
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); S.pi = Math.max(0, Math.min((S.palRes || []).length - 1, S.pi + (e.key === 'ArrowDown' ? 1 : -1))); renderPalResults(); var sel = root.querySelector('.ed-pal-res[aria-selected="true"]'); if (sel) { sel.scrollIntoView({ block: 'nearest' }); } return; }
            if (e.key === 'Enter') { e.preventDefault(); var r = S.palRes && S.palRes[S.pi]; if (r) { r.run(); } return; }
            return;
        }
        if (e.key === 'Escape') {
            if (S.fmenu) { closeFmenu(); } else if (S.modal) { closeModal(); } else if (S.dataOpen) { S.dataOpen = false; render(); } else if (S.menu || S.pop) { S.menu = null; S.pop = null; render(); } else if (S.toast) { S.toast = null; renderToast(); }
            else if (!typing && S.unitFilter !== 0) { S.unitFilter = 0; render(); }
            return;
        }
        if (typing || S.modal || S.dataOpen) { return; }
        if (t.closest && t.closest('[data-tree]')) {
            if (e.key === 'ArrowDown') { e.preventDefault(); moveTree(1); } else if (e.key === 'ArrowUp') { e.preventDefault(); moveTree(-1); } else if (e.key === 'Home') { e.preventDefault(); moveTree('home'); } else if (e.key === 'End') { e.preventDefault(); moveTree('end'); }
            return;
        }
        if (!(t.closest && t.closest('[data-grid]')) || t.matches('button,a,input')) { return; }
        var map = { ArrowDown: [1, 0], ArrowUp: [-1, 0], ArrowRight: [0, 1], ArrowLeft: [0, -1] };
        if (map[e.key]) { e.preventDefault(); moveSel(map[e.key][0], map[e.key][1]); }
        else if (e.key === 'Home') { e.preventDefault(); moveSel(0, 0, 'home'); }
        else if (e.key === 'End') { e.preventDefault(); moveSel(0, 0, 'end'); }
        else if (e.key === 'PageDown') { e.preventDefault(); moveSel(10, 0); }
        else if (e.key === 'PageUp') { e.preventDefault(); moveSel(-10, 0); }
        else if (e.key === 'Enter' || e.key === ' ') { var m = selMember(); if (m) { e.preventDefault(); openMember(m.id); } }
    });
    window.addEventListener('online', function () { setOnline(true); });
    window.addEventListener('offline', function () { setOnline(false); });
    setInterval(function () {
        if (S.booting || document.hidden) { return; }
        S.nextSync -= 1;
        if (S.nextSync <= 0) { S.nextSync = 60; sync(true); }
    }, 1000);

    // ——— Démarrage ———
    function finishBoot() {
        var i = S.bootStep;
        (function step() {
            if (S.bootStep >= BOOT.length) { S.booting = false; render(); if (CFG.flashOk) { toast('Import', CFG.flashOk); } else if (CFG.flashError) { toast('Information', CFG.flashError); } root.focus(); return; }
            S.bootStep += 1; renderBoot(); setTimeout(step, 110);
        })();
        void i;
    }
    renderBoot();
    S.bootStep = 1; renderBoot();
    load(false).then(function () { S.bootStep = 2; render(); finishBoot(); }).catch(function (err) {
        S.loading = false; S.booting = false; S.error = 'Impossible de charger le classeur.';
        render();
        if (err && err.message === 'forbidden') { return; }
    });
})();
