/*
 * Espace Matériel de la page Équipement (/equipment?espace=materiel, vue views/equipment/_space_materiel.php) :
 * catalogue des objets de la communauté.
 *
 * Données : catalogue du configurateur (GET equipment/configurateur/catalogue, forme compacte de
 * ConfiguratorCatalog::compact, mise en cache par le navigateur grâce à l'ETag) ; favoris, tendances 30 jours et
 * pré-kits du membre (GET equipment/configurateur/membre). Écritures : favori (POST …/favori) et ajout à un pré-kit
 * (POST …/pre-kits), validées par le serveur comme dans le configurateur. Rien n'est inventé : une donnée absente du
 * catalogue est dite « inconnue ».
 *
 * Recherche instantanée (nom, classe, mod ; sans accents), familles avec compteurs, catégories de la famille, source
 * (mod), tri, favoris, tendances, filtre « compatible avec » (arme ↔ accessoires et munitions), grille ou liste.
 * Plusieurs milliers d'objets : le filtre parcourt les données en mémoire, l'affichage ajoute les cartes par lots au
 * défilement. Fiche d'un objet dans une fenêtre (poids, capacité, compatibilités, configurateur, pré-kit), ouverte
 * aussi par ?objet=CLASSE. Les filtres sont dans l'adresse (replaceState). Textes du jeu toujours échappés et marqués
 * translate="no".
 */
(function () {
    'use strict';
    var root = document.querySelector('[data-eqm]');
    var bootEl = document.getElementById('eqm-boot');
    if (!root || !bootEl) {
        return;
    }
    var boot = {};
    try { boot = JSON.parse(bootEl.textContent || '{}'); } catch (e) { boot = {}; }
    var urls = boot.urls || {};
    var can = boot.can || {};
    var KIND = boot.kinds || {};
    var CATS = boot.cats || {};
    var FAMILIES = boot.families || [];
    var BATCH = boot.batch || 48;
    var KG = boot.kg || 0.0453592;
    var REFUSED = boot.prekitRefused || [];
    var PK_MAX = boot.prekitMax || { lines: 40, count: 99, name: 90 };
    var SLOT_KIND = boot.slots || {};
    var WEAPON_KINDS = ['primary', 'handgun', 'secondary'];
    var LOCALE = (document.documentElement.getAttribute('lang') || 'fr').indexOf('en') === 0 ? 'en-GB' : 'fr-FR';
    var famOfKind = {};
    FAMILIES.forEach(function (f) { f.kinds.forEach(function (k) { famOfKind[k] = f.id; }); });

    // ------------------------------------------------------------------ outils
    function $(sel, ctx) { return (ctx || root).querySelector(sel); }
    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function fold(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
    function num(n) { return Number(n).toLocaleString(LOCALE); }
    function plural(n, one, many) { return num(n) + ' ' + (n > 1 ? many : one); }
    /** Masse d'Arma (dixièmes de livre, convention d'ACE) en kg : deux décimales sous 1 kg, une au-delà. */
    function kg(m) {
        if (m == null) { return ''; }
        var v = m * KG;
        var d = v < 1 ? 100 : 10;
        var r = Math.round(v * d) / d;
        if (r === 0 && m > 0) { return '< ' + (0.01).toLocaleString(LOCALE) + ' kg'; }
        return r.toLocaleString(LOCALE) + ' kg';
    }
    function store(key, value) {
        try {
            if (value === undefined) { return window.localStorage.getItem(key); }
            window.localStorage.setItem(key, value);
        } catch (e) { /* stockage indisponible : simple confort perdu */ }
        return null;
    }
    function request(url, opts) {
        opts = opts || {};
        var init = { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
        if (opts.form) {
            init.method = 'POST';
            opts.form.append('_csrf_token', boot.csrf || '');
            init.body = opts.form;
        }
        return fetch(url, init).then(function (r) {
            return r.json().catch(function () { return { ok: false, error: 'Réponse illisible du serveur.' }; });
        }).catch(function () { return { ok: false, error: 'Serveur injoignable : vérifiez la connexion.' }; });
    }
    var live = $('[data-eqm-live]');
    function announce(msg) { if (live) { live.textContent = ''; window.setTimeout(function () { live.textContent = msg; }, 30); } }
    var toastEl = document.createElement('p');
    toastEl.className = 'eqm-toast';
    toastEl.setAttribute('role', 'status');
    toastEl.hidden = true;
    document.body.appendChild(toastEl);
    var toastTimer = 0;
    function toast(msg, kind) {
        toastEl.textContent = msg;
        toastEl.className = 'eqm-toast' + (kind ? ' eqm-toast--' + kind : '');
        toastEl.hidden = false;
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(function () { toastEl.hidden = true; }, kind === 'err' ? 6500 : 4000);
    }

    // Pictogrammes par famille (mêmes tracés que le configurateur) quand le jeu n'a pas fourni d'icône.
    var ICONS = {
        primary: 'M2 12h13l2-2h5v3h-3l-1 3h-3l-1-2H8l-2 4H3l1-4H2z', handgun: 'M3 8h15v4h-6l-1 2h-2l-1 5H4l1-6H3z',
        secondary: 'M2 10h18l2 2-2 2H2zM6 14v4M14 14v3', optic: 'M4 9h16v6H4zM8 9V6h8v3M8 15v3', muzzle: 'M3 10h14v4H3zM17 9h4v6h-4',
        pointer: 'M4 9h10v6H4zM14 11h7M14 13h7', bipod: 'M12 4v8M12 12l-6 8M12 12l6 8', magazine: 'M8 3h8l-1 18H9zM9 8h6M9 12h6',
        uniform: 'M8 3l4 2 4-2 5 4-3 3-2-1v12H8V9L6 10 3 7z', vest: 'M7 3h3l2 3 2-3h3l1 18H6zM6 10h12', backpack: 'M7 6a5 5 0 0 1 10 0v15H7zM9 12h6v5H9z',
        headgear: 'M3 16a9 9 0 0 1 18 0zM2 16h20v2H2z', goggles: 'M3 10h18v5h-7l-2-2-2 2H3z', nvg: 'M6 8h12v6H6zM8 14v4M16 14v4M9 5h6',
        binocular: 'M4 8h6v11H4zM14 8h6v11h-6zM10 11h4', radio: 'M7 7h10v14H7zM10 3v4M10 11h4M10 15h4', map: 'M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2z',
        gps: 'M6 3h12v18H6zM9 7h6v6H9z', compass: 'M12 3a9 9 0 1 1 0 18 9 9 0 0 1 0-18zM14 10l-4 4 1-5z', watch: 'M9 2h6v4H9zM9 18h6v4H9zM12 6a6 6 0 1 1 0 12 6 6 0 0 1 0-12zM12 9v3h2',
        medical: 'M4 7h16v12H4zM12 9v8M8 13h8', item: 'M4 8l8-4 8 4v8l-8 4-8-4zM4 8l8 4 8-4M12 12v8'
    };
    var STAR = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/></svg>';
    var TREND = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17l6-6 4 4 8-8M15 7h6v6"/></svg>';
    function iconHtml(it, cls) {
        if (it.i && urls.icons) {
            return '<span class="' + cls + '"><img src="' + esc(urls.icons + it.i + '.png') + '" alt="" loading="lazy" decoding="async"></span>';
        }
        return '<span class="' + cls + ' ' + cls + '--svg"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="' + (ICONS[it.k] || ICONS.item) + '"/></svg></span>';
    }

    // ------------------------------------------------------------------ catalogue
    var C = { items: [], byKey: {}, groups: [], sets: [], ready: false, loading: false, error: '', sources: [], groupWeapons: {}, classGroups: {} };
    var collator = window.Intl && Intl.Collator ? new Intl.Collator(LOCALE, { sensitivity: 'base', numeric: true }) : null;
    function cmpName(a, b) { return collator ? collator.compare(a._n, b._n) : (a._n < b._n ? -1 : (a._n > b._n ? 1 : 0)); }
    function nameOf(it) { return it.n || it.c; }
    function srcKey(it) { return it.o ? 'mod:' + it.o : (it.b ? 'base' : 'none'); }
    function srcLabel(key) {
        if (key === 'base') { return 'Jeu de base d’Arma 3'; }
        if (key === 'none') { return 'Source non indiquée'; }
        return key.slice(4);
    }
    function srcHtml(key) {
        return key.indexOf('mod:') === 0 ? '<span translate="no">' + esc(srcLabel(key)) + '</span>' : esc(srcLabel(key));
    }
    function kindLine(it) {
        var parts = [KIND[it.k] || KIND.item || ''];
        if (it.g && CATS[it.g] && CATS[it.g] !== parts[0]) { parts.push(CATS[it.g]); }
        return parts.join(' · ');
    }
    function isWeapon(it) { return WEAPON_KINDS.indexOf(it.k) >= 0; }
    function groupSet(i) {
        if (i == null || !C.groups[i]) { return null; }
        if (!C.sets[i]) {
            var set = {};
            C.groups[i].forEach(function (c) { set[c] = true; });
            C.sets[i] = set;
        }
        return C.sets[i];
    }
    /** Index inverse : pour chaque classe, les armes qui l'acceptent (accessoire ou chargeur). */
    function indexCompat() {
        var gw = {};
        C.items.forEach(function (it) {
            if (!isWeapon(it) || !it.x) { return; }
            Object.keys(it.x).forEach(function (short) {
                var g = it.x[short];
                (gw[g] = gw[g] || []).push(it._i);
            });
        });
        var cg = {};
        Object.keys(gw).forEach(function (g) {
            (C.groups[g] || []).forEach(function (cls) { (cg[cls] = cg[cls] || []).push(g); });
        });
        C.groupWeapons = gw;
        C.classGroups = cg;
    }
    /** Armes (index) qui acceptent la classe. */
    function weaponsFor(it) {
        var seen = {};
        var out = [];
        (C.classGroups[it._k] || []).forEach(function (g) {
            C.groupWeapons[g].forEach(function (i) { if (!seen[i]) { seen[i] = true; out.push(i); } });
        });
        return out;
    }
    /** Objets du catalogue compatibles avec une arme, par emplacement ; null si le jeu ne l'a pas dit. */
    function compatOf(w, short) {
        if (!w.x || w.x[short] === undefined) { return null; }
        var out = [];
        (C.groups[w.x[short]] || []).forEach(function (cls) { var it = C.byKey[cls]; if (it) { out.push(it); } });
        return out;
    }
    function compatTotal(w) {
        if (w._ct !== undefined) { return w._ct; }
        var n = null;
        ['mg', 'op', 'mu', 'po', 'bi'].forEach(function (s) { var l = compatOf(w, s); if (l) { n = (n || 0) + l.length; } });
        w._ct = n;
        return n;
    }
    function fitsCount(it) {
        if (it._wf === undefined) { it._wf = C.classGroups[it._k] ? weaponsFor(it).length : 0; }
        return it._wf;
    }

    function loadCatalog() {
        C.loading = true;
        C.error = '';
        render();
        return request(urls.catalogue).then(function (j) {
            C.loading = false;
            if (!j.ok) {
                C.error = j.error || 'Catalogue indisponible.';
                render();
                return;
            }
            C.items = j.items || [];
            C.groups = j.groups || [];
            C.sets = [];
            C.byKey = {};
            var src = {};
            C.items.forEach(function (it, idx) {
                it._i = idx;
                it._k = String(it.c).toLowerCase();
                it._n = fold(nameOf(it));
                it._f = fold(nameOf(it) + ' ' + it.c + ' ' + (it.o || ''));
                it._fam = famOfKind[it.k] || 'divers';
                it._src = srcKey(it);
                C.byKey[it._k] = it;
                src[it._src] = (src[it._src] || 0) + 1;
            });
            C.sources = Object.keys(src).sort(function (a, b) {
                if (a === 'base' || b === 'base') { return a === 'base' ? -1 : 1; }
                return src[b] - src[a] || srcLabel(a).localeCompare(srcLabel(b));
            }).map(function (k) { return { key: k, n: src[k] }; });
            indexCompat();
            C.ready = true;
            fillSources();
            var total = $('[data-eqm-total]', document);
            if (total) { total.textContent = num(C.items.length); total.hidden = false; }
            render();
            openFromUrl();
        });
    }

    // ------------------------------------------------------------------ données du membre
    var M = { ready: false, favReady: false, preReady: false, fav: {}, prekits: [], trends: { items: {}, days: 30, min: 2 } };
    function trendOf(it) { return M.trends.items[it._k] || null; }
    function trendScore(t) { return t ? (t.w || 0) * 3 + (t.t || 0) * 2 + (t.f || 0) : 0; }
    function trendLabel(t) {
        var parts = [];
        if (t.w) { parts.push('porté en jeu par ' + plural(t.w, 'membre', 'membres')); }
        if (t.t) { parts.push('dans les tenues de ' + plural(t.t, 'membre', 'membres')); }
        if (t.f) { parts.push('favori de ' + plural(t.f, 'membre', 'membres')); }
        return 'Tendance sur ' + M.trends.days + ' jours : ' + parts.join(', ') + '.';
    }
    function favCount() { return Object.keys(M.fav).length; }
    function loadMember() {
        if (!urls.social) { return Promise.resolve(); }
        return request(urls.social).then(function (j) {
            if (!j.ok) { return; }
            M.ready = true;
            M.favReady = !!(j.ready && j.ready.favorites);
            M.preReady = !!(j.ready && j.ready.prekits);
            M.fav = {};
            (j.favorites || []).forEach(function (c) { M.fav[String(c).toLowerCase()] = true; });
            M.prekits = j.prekits || [];
            M.trends = j.trends || M.trends;
            M.trends.items = M.trends.items && !Array.isArray(M.trends.items) ? M.trends.items : {};
            $('[data-eqm-fav-count]').textContent = num(favCount());
            if (C.ready) { render(); }
            if (sheet.open && S.stack.length) { renderSheet(); }
        });
    }
    function toggleFav(it) {
        if (!M.ready) { toast('Vos favoris se chargent encore : réessayez dans un instant.', 'warn'); return; }
        if (!M.favReady) { toast('Favoris pas encore disponibles : la mise à jour de la base doit être appliquée par un responsable.', 'warn'); return; }
        var on = !M.fav[it._k];
        if (on) { M.fav[it._k] = true; } else { delete M.fav[it._k]; }
        syncFav(it);
        var fd = new FormData();
        fd.append('class', it.c);
        fd.append('on', on ? '1' : '0');
        request(urls.favorite, { form: fd }).then(function (j) {
            if (j.ok) { announce(on ? nameOf(it) + ' ajouté à vos favoris.' : nameOf(it) + ' retiré de vos favoris.'); return; }
            if (on) { delete M.fav[it._k]; } else { M.fav[it._k] = true; }
            syncFav(it);
            toast(j.error || 'Favori non enregistré.', 'err');
        });
    }
    function starHtml(it, big) {
        var on = !!M.fav[it._k];
        var label = (on ? 'Retirer de mes favoris : ' : 'Ajouter à mes favoris : ') + nameOf(it);
        return '<button type="button" class="eqm-star' + (big ? ' eqm-star--big' : '') + (on ? ' is-on' : '') + '" data-eqm-fav="' + it._i + '" aria-pressed="' + on + '" aria-label="' + esc(label) + '" title="' + esc(label) + '">' + STAR + (big ? '<span>' + (on ? 'Dans mes favoris' : 'Ajouter aux favoris') + '</span>' : '') + '</button>';
    }
    /** Met à jour l'étoile partout où l'objet est affiché (sans tout redessiner, sauf avec le filtre Favoris). */
    function syncFav(it) {
        $('[data-eqm-fav-count]').textContent = num(favCount());
        if (F.fav) { render(); } else {
            Array.prototype.forEach.call(document.querySelectorAll('[data-eqm-fav="' + it._i + '"]'), function (b) {
                b.outerHTML = starHtml(it, b.classList.contains('eqm-star--big'));
            });
            var card = results.querySelector('[data-eqm-card="' + it._i + '"]');
            if (card) { card.classList.toggle('is-fav', !!M.fav[it._k]); }
        }
    }

    // ------------------------------------------------------------------ filtres
    var F = { q: '', fam: '', cat: '', src: '', sort: 'pertinence', fav: false, trend: false, rel: null };
    var view = store('athena.eqm.view') === 'list' ? 'list' : 'grid';
    /** Catégorie d'un objet dans sa famille : type d'arme ou de munition, sinon famille du configurateur. */
    function catOf(it) { return it._fam === 'armes' || it._fam === 'munitions' ? (it.g || '') : it.k; }
    function catLabel(it) { return it._fam === 'armes' || it._fam === 'munitions' ? (CATS[it.g] || 'Autres') : (KIND[it.k] || ''); }
    function relSet() {
        if (!F.rel) { return null; }
        var it = C.byKey[F.rel.key];
        if (!it) { return null; }
        if (F.rel._set && F.rel._for === it) { return F.rel._set; }
        var set = {};
        if (F.rel.mode === 'for') {
            ['mg', 'op', 'mu', 'po', 'bi'].forEach(function (s) { (compatOf(it, s) || []).forEach(function (x) { set[x._k] = true; }); });
        } else {
            weaponsFor(it).forEach(function (i) { set[C.items[i]._k] = true; });
        }
        F.rel._set = set;
        F.rel._for = it;
        return set;
    }
    function relItem() { return F.rel ? C.byKey[F.rel.key] || null : null; }

    var results = $('[data-eqm-results]');
    var countEl = $('[data-eqm-count]');
    var stateEl = $('[data-eqm-state]');
    var moreEl = $('[data-eqm-more]');
    var R = { list: [], shown: 0 };

    function compute() {
        var words = fold(F.q).split(/\s+/).filter(Boolean);
        var rel = relSet();
        var famCount = {};
        var catCount = {};
        var catName = {};
        var list = [];
        var base = 0;
        for (var i = 0; i < C.items.length; i++) {
            var it = C.items[i];
            if (F.src && it._src !== F.src) { continue; }
            if (F.fav && !M.fav[it._k]) { continue; }
            if (F.trend && !trendOf(it)) { continue; }
            if (rel && !rel[it._k]) { continue; }
            var ok = true;
            for (var w = 0; w < words.length; w++) { if (it._f.indexOf(words[w]) < 0) { ok = false; break; } }
            if (!ok) { continue; }
            base++;
            famCount[it._fam] = (famCount[it._fam] || 0) + 1;
            if (F.fam && it._fam !== F.fam) { continue; }
            var cat = catOf(it);
            if (F.fam) { catCount[cat] = (catCount[cat] || 0) + 1; catName[cat] = catLabel(it); }
            if (F.cat && cat !== F.cat) { continue; }
            list.push(it);
        }
        sortList(list, words);
        return { list: list, base: base, famCount: famCount, catCount: catCount, catName: catName };
    }
    function sortList(list, words) {
        var q = words.join(' ');
        var by = {
            nom: function (a, b) { return cmpName(a, b); },
            'poids-asc': function (a, b) { return (a.m == null) - (b.m == null) || (a.m || 0) - (b.m || 0) || cmpName(a, b); },
            'poids-desc': function (a, b) { return (a.m == null) - (b.m == null) || (b.m || 0) - (a.m || 0) || cmpName(a, b); },
            capacite: function (a, b) { return (!a.l) - (!b.l) || (b.l || 0) - (a.l || 0) || cmpName(a, b); },
            porte: function (a, b) { return (b.p || 0) - (a.p || 0) || cmpName(a, b); }
        }[F.sort];
        if (!by) {
            // Pertinence : nom ou classe exacts, puis début du nom, puis tendances et objets portés en jeu.
            var rank = function (it) {
                if (!q) { return 0; }
                if (it._n === q || it._k === q) { return 0; }
                return it._n.indexOf(q) === 0 || it._k.indexOf(q) === 0 ? 1 : 2;
            };
            list.forEach(function (it) { it._r = rank(it); it._t = trendScore(trendOf(it)); });
            by = function (a, b) { return a._r - b._r || b._t - a._t || (b.p || 0) - (a.p || 0) || cmpName(a, b); };
        }
        list.sort(by);
    }

    function famChips(famCount, base) {
        var html = '<button type="button" class="eqm-chip' + (F.fam === '' ? ' is-on' : '') + '" aria-pressed="' + (F.fam === '') + '" data-eqm-fam="">' + esc('Tout') + ' <span>' + num(base) + '</span></button>';
        FAMILIES.forEach(function (f) {
            var n = famCount[f.id] || 0;
            if (!n && F.fam !== f.id) { return; }
            html += '<button type="button" class="eqm-chip' + (F.fam === f.id ? ' is-on' : '') + '" aria-pressed="' + (F.fam === f.id) + '" data-eqm-fam="' + esc(f.id) + '">' + esc(f.label) + ' <span>' + num(n) + '</span></button>';
        });
        $('[data-eqm-families]').innerHTML = html;
    }
    function catChips(catCount, catName) {
        var box = $('[data-eqm-cats]');
        var keys = Object.keys(catCount).sort(function (a, b) { return catCount[b] - catCount[a]; });
        if (!F.fam || keys.length < 2) {
            box.hidden = true;
            box.innerHTML = '';
            if (F.cat && !catCount[F.cat]) { F.cat = ''; }
            return;
        }
        box.hidden = false;
        box.innerHTML = '<button type="button" class="eqm-chip eqm-chip--sm' + (F.cat === '' ? ' is-on' : '') + '" aria-pressed="' + (F.cat === '') + '" data-eqm-cat="">' + esc('Toutes') + '</button>'
            + keys.map(function (k) {
                return '<button type="button" class="eqm-chip eqm-chip--sm' + (F.cat === k ? ' is-on' : '') + '" aria-pressed="' + (F.cat === k) + '" data-eqm-cat="' + esc(k) + '">' + esc(catName[k] || 'Autres') + ' <span>' + num(catCount[k]) + '</span></button>';
            }).join('');
    }
    function activeChips() {
        var box = $('[data-eqm-active]');
        var parts = [];
        var ri = relItem();
        if (ri) {
            parts.push('<span class="eqm-pill">' + (F.rel.mode === 'for' ? 'Va sur l’arme : ' : 'Armes qui acceptent : ') + '<strong translate="no">' + esc(nameOf(ri)) + '</strong>'
                + '<button type="button" data-eqm-clear="rel" aria-label="Retirer ce filtre">×</button></span>');
        }
        if (F.src) {
            parts.push('<span class="eqm-pill">Source : <strong>' + srcHtml(F.src) + '</strong><button type="button" data-eqm-clear="src" aria-label="Retirer ce filtre">×</button></span>');
        }
        if (parts.length || F.q || F.fam || F.fav || F.trend) {
            parts.push('<button type="button" class="eqm-link" data-eqm-clear="all">Effacer les filtres</button>');
        }
        box.innerHTML = parts.join('');
        box.hidden = !parts.length;
    }

    function cardHtml(it) {
        var fav = !!M.fav[it._k];
        var tr = trendOf(it);
        var facts = [];
        facts.push(it.m != null ? '<span class="eqm-fact"><b>' + esc(kg(it.m)) + '</b></span>' : '<span class="eqm-fact eqm-fact--unk">Poids inconnu</span>');
        if (it.l) { facts.push('<span class="eqm-fact">Capacité <b>' + esc(kg(it.l)) + '</b></span>'); }
        if (it.k === 'magazine' && it.a) { facts.push('<span class="eqm-fact"><b>' + num(it.a) + '</b> ' + (it.a > 1 ? 'coups' : 'coup') + '</span>'); }
        if (isWeapon(it)) {
            var ct = compatTotal(it);
            if (ct) { facts.push('<span class="eqm-fact"><b>' + num(ct) + '</b> ' + (ct > 1 ? 'compatibles' : 'compatible') + '</span>'); }
        } else if (it.k === 'magazine' || famOfKind[it.k] === 'accessoires') {
            var wf = fitsCount(it);
            if (wf) { facts.push('<span class="eqm-fact">Va sur <b>' + plural(wf, 'arme', 'armes') + '</b></span>'); }
        }
        return '<li class="eqm-card' + (fav ? ' is-fav' : '') + (tr ? ' is-trend' : '') + '" data-eqm-card="' + it._i + '">'
            + '<button type="button" class="eqm-card__open" data-eqm-open="' + it._i + '" aria-haspopup="dialog">'
            + iconHtml(it, 'eqm-card__ico')
            + '<span class="eqm-card__body">'
            + '<strong class="eqm-card__name" translate="no">' + esc(nameOf(it)) + '</strong>'
            + '<span class="eqm-card__kind">' + esc(kindLine(it)) + '</span>'
            + '<span class="eqm-card__facts">' + facts.join('') + '</span>'
            + '<span class="eqm-card__src">' + srcHtml(it._src) + '</span>'
            + '</span></button>'
            + (tr ? '<span class="eqm-trend" title="' + esc(trendLabel(tr)) + '">' + TREND + '<span>Tendance</span></span>' : '')
            + starHtml(it, false)
            + '</li>';
    }
    function appendBatch() {
        var end = Math.min(R.list.length, R.shown + BATCH);
        var html = '';
        for (var i = R.shown; i < end; i++) { html += cardHtml(R.list[i]); }
        results.insertAdjacentHTML('beforeend', html);
        R.shown = end;
        var left = R.list.length - R.shown;
        moreEl.hidden = left <= 0;
        if (left > 0) {
            $('[data-eqm-more-text]').textContent = num(R.shown) + ' objets affichés sur ' + num(R.list.length) + '.';
        }
    }
    function setState(html) {
        stateEl.innerHTML = html;
        stateEl.hidden = !html;
    }
    function render() {
        results.className = 'eqm-results eqm-results--' + view;
        Array.prototype.forEach.call(root.querySelectorAll('[data-eqm-view]'), function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-eqm-view') === view)); });
        Array.prototype.forEach.call(root.querySelectorAll('[data-eqm-only]'), function (b) {
            var on = !!F[b.getAttribute('data-eqm-only')];
            b.setAttribute('aria-pressed', String(on));
            b.classList.toggle('is-on', on);
        });
        if (!C.ready) {
            moreEl.hidden = true;
            if (C.error) {
                results.innerHTML = '';
                results.setAttribute('aria-busy', 'false');
                countEl.textContent = 'Catalogue indisponible.';
                setState('<p><strong>Le catalogue n’a pas pu être chargé.</strong></p><p>' + esc(C.error) + '</p><button type="button" class="eq-hub__btn" data-eqm-retry>Réessayer</button>');
            } else {
                countEl.textContent = 'Chargement du catalogue…';
                setState('');
            }
            return;
        }
        results.setAttribute('aria-busy', 'false');
        var r = compute();
        famChips(r.famCount, r.base);
        catChips(r.catCount, r.catName);
        if (F.cat && !r.catCount[F.cat]) { F.cat = ''; r = compute(); }
        activeChips();
        R.list = r.list;
        R.shown = 0;
        results.innerHTML = '';
        if (!C.items.length) {
            countEl.textContent = baseRunning ? 'Préparation du catalogue de base…' : 'Catalogue vide.';
            setState(baseRunning
                ? '<p><strong>Le catalogue de base d’Arma 3 se prépare.</strong></p><p>Les armes et l’équipement du jeu arrivent dans quelques secondes, la liste se mettra à jour toute seule.</p>'
                : '<p><strong>Aucun objet dans le catalogue pour le moment.</strong></p><p>Les objets arrivent dès qu’un membre joue avec COMSPEC Link, ou quand un responsable importe la configuration du jeu depuis le configurateur.</p>'
                    + (can.catalog ? '<a class="eq-hub__btn" href="' + esc(urls.configurator) + '">Importer depuis le configurateur</a>' : ''));
            moreEl.hidden = true;
            syncUrl();
            return;
        }
        if (!R.list.length) {
            countEl.textContent = 'Aucun objet ne correspond.';
            var tips = '';
            if (F.fav && !favCount()) {
                tips = '<p><strong>Aucun favori pour le moment.</strong></p><p>Cliquez sur l’étoile d’un objet pour le retrouver ici, d’une session à l’autre (le configurateur les montre aussi).</p>';
            } else if (F.trend && !Object.keys(M.trends.items).length) {
                tips = '<p><strong>Pas encore de tendance.</strong></p><p>Un objet devient tendance quand au moins ' + M.trends.min + ' membres le portent en jeu, l’ajoutent à une tenue ou le mettent en favori en ' + M.trends.days + ' jours.</p>';
            } else {
                tips = '<p><strong>Aucun objet ne correspond' + (F.q ? ' à « ' + esc(F.q) + ' »' : '') + '.</strong></p><p>Essayez une partie du nom (« MX », « carryall »), la classe Arma, ou retirez un filtre.</p>';
            }
            setState(tips + '<button type="button" class="eq-hub__btn eq-hub__btn--outline" data-eqm-clear="all">Effacer les filtres</button>');
            moreEl.hidden = true;
            syncUrl();
            return;
        }
        setState('');
        countEl.textContent = R.list.length === C.items.length
            ? plural(C.items.length, 'objet dans le catalogue', 'objets dans le catalogue')
            : plural(R.list.length, 'objet', 'objets') + ' sur ' + num(C.items.length);
        appendBatch();
        syncUrl();
    }

    // Défilement : un lot de plus quand le bas de la liste approche (bouton « Afficher plus » en secours).
    var sentinel = $('[data-eqm-sentinel]');
    if ('IntersectionObserver' in window && sentinel) {
        new IntersectionObserver(function (entries) {
            if (entries[0].isIntersecting && C.ready && R.shown < R.list.length && !root.closest('[hidden]')) { appendBatch(); }
        }, { rootMargin: '600px 0px' }).observe(sentinel);
    }

    function fillSources() {
        var sel = $('[data-eqm-source]');
        sel.innerHTML = '<option value="">Toutes les sources (' + num(C.items.length) + ')</option>' + C.sources.map(function (s) {
            return '<option value="' + esc(s.key) + '"' + (F.src === s.key ? ' selected' : '') + '>' + esc(srcLabel(s.key)) + ' (' + num(s.n) + ')</option>';
        }).join('');
        if (F.src && !C.sources.some(function (s) { return s.key === F.src; })) { F.src = ''; }
        sel.value = F.src;
    }

    // ------------------------------------------------------------------ adresse
    function syncUrl() {
        if (!window.history || !window.history.replaceState) { return; }
        var p = new URLSearchParams(window.location.search);
        var set = function (k, v) { if (v) { p.set(k, v); } else { p.delete(k); } };
        set('q', F.q.trim());
        set('famille', F.fam);
        set('cat', F.cat);
        set('source', F.src);
        set('tri', F.sort === 'pertinence' ? '' : F.sort);
        set('favoris', F.fav ? '1' : '');
        set('tendances', F.trend ? '1' : '');
        set('avec', F.rel ? F.rel.mode + ':' + F.rel.key : '');
        set('objet', sheet.open && S.stack.length ? C.items[S.stack[S.stack.length - 1]].c : '');
        var qs = p.toString();
        window.history.replaceState(window.history.state, '', window.location.pathname + (qs ? '?' + qs : '') + window.location.hash);
    }
    function readUrl() {
        var p = new URLSearchParams(window.location.search);
        F.q = (p.get('q') || '').slice(0, 120);
        F.fam = FAMILIES.some(function (f) { return f.id === p.get('famille'); }) ? p.get('famille') : '';
        F.cat = (p.get('cat') || '').slice(0, 40);
        F.src = (p.get('source') || '').slice(0, 140);
        F.sort = boot.sorts && boot.sorts[p.get('tri')] ? p.get('tri') : 'pertinence';
        F.fav = p.get('favoris') === '1';
        F.trend = p.get('tendances') === '1';
        var rel = /^(for|on):(.{1,128})$/.exec(p.get('avec') || '');
        F.rel = rel ? { mode: rel[1], key: rel[2].toLowerCase() } : null;
        pendingItem = (p.get('objet') || '').slice(0, 128);
        $('[data-eqm-q]').value = F.q;
        $('[data-eqm-sort]').value = F.sort;
    }
    /** Objet demandé par l'adresse (?objet=CLASSE), lu avant que la synchronisation de l'adresse ne l'efface. */
    var pendingItem = '';
    function openFromUrl() {
        var cls = pendingItem;
        pendingItem = '';
        if (!cls) { return; }
        var it = C.byKey[cls.toLowerCase()];
        if (it) { openSheet(it, false); } else { toast('Cet objet n’est pas (ou plus) dans le catalogue de la communauté.', 'warn'); }
    }

    // ------------------------------------------------------------------ fiche d'un objet
    var sheet = document.getElementById('eqm-sheet');
    var sheetBody = sheet.querySelector('[data-eqm-sheet-body]');
    var S = { stack: [], opener: null };
    function openSheet(it, push) {
        if (!push) { S.stack = []; S.opener = document.activeElement; }
        S.stack.push(it._i);
        renderSheet();
        if (!sheet.open) {
            if (typeof sheet.showModal === 'function') { sheet.showModal(); } else { sheet.setAttribute('open', ''); }
        }
        sheetBody.scrollTop = 0;
        sheet.querySelector('.eqm-sheet__shell').scrollTop = 0;
        syncUrl();
    }
    function closeSheet() {
        if (sheet.open) { sheet.close(); }
    }
    sheet.addEventListener('close', function () {
        S.stack = [];
        syncUrl();
        if (S.opener && document.contains(S.opener)) { S.opener.focus(); }
    });
    sheet.addEventListener('click', function (e) {
        if (e.target === sheet) { closeSheet(); }
    });
    function miniList(items, max) {
        return '<ul class="eqm-mini">' + items.slice(0, max).map(function (x) {
            return '<li><button type="button" data-eqm-goto="' + x._i + '">' + iconHtml(x, 'eqm-mini__ico')
                + '<span><strong translate="no">' + esc(nameOf(x)) + '</strong><small>' + esc([KIND[x.k] || '', x.m != null ? kg(x.m) : ''].filter(Boolean).join(' · ')) + '</small></span></button></li>';
        }).join('') + '</ul>';
    }
    var COMPAT_SLOTS = [['mg', 'Chargeurs et munitions'], ['op', 'Optiques'], ['mu', 'Bouches'], ['po', 'Rails latéraux'], ['bi', 'Bipieds et poignées']];
    function compatHtml(it) {
        var html = '';
        if (isWeapon(it)) {
            var any = false;
            COMPAT_SLOTS.forEach(function (s) {
                var list = compatOf(it, s[0]);
                if (list === null) { return; }
                any = true;
                list.sort(cmpName);
                html += '<section class="eqm-sec"><h3>' + esc(s[1]) + ' <span>' + num(list.length) + '</span></h3>'
                    + (list.length ? miniList(list, 8) : '<p class="eqm-muted">Rien de compatible dans le catalogue.</p>') + '</section>';
            });
            if (any && compatTotal(it)) {
                html += '<p><button type="button" class="eq-hub__btn eq-hub__btn--outline" data-eqm-rel="for:' + esc(it._k) + '">' + esc('Voir les ' + num(compatTotal(it)) + ' objets compatibles dans le catalogue') + '</button></p>';
            } else if (!any && it.v) {
                html += '<p class="eqm-muted">Le jeu n’a pas fourni la liste des accessoires et chargeurs compatibles avec cette arme.</p>';
            }
            return html;
        }
        if (it.k === 'magazine' || famOfKind[it.k] === 'accessoires') {
            var ws = weaponsFor(it).map(function (i) { return C.items[i]; }).sort(cmpName);
            html += '<section class="eqm-sec"><h3>Armes compatibles <span>' + num(ws.length) + '</span></h3>'
                + (ws.length ? miniList(ws, 8) : '<p class="eqm-muted">Aucune arme du catalogue ne déclare cet objet comme compatible' + (it.k === 'magazine' ? ' (grenades à main, explosifs et munitions de mods sans configuration importée n’en ont pas).' : '.') + '</p>')
                + '</section>';
            if (ws.length > 8) {
                html += '<p><button type="button" class="eq-hub__btn eq-hub__btn--outline" data-eqm-rel="on:' + esc(it._k) + '">' + esc('Voir les ' + num(ws.length) + ' armes dans le catalogue') + '</button></p>';
            }
        }
        return html;
    }
    function prekitHtml(it) {
        if (REFUSED.indexOf(it.k) >= 0) {
            return '<p class="eqm-muted">Les armes, uniformes, gilets et sacs ne vont pas dans un pré-kit : posez-les dans le configurateur.</p>';
        }
        var head = '<h3>Ajouter à un pré-kit</h3><p class="eqm-muted">Un pré-kit est un lot d’objets inséré en un clic dans l’uniforme, le gilet ou le sac depuis le configurateur.</p>';
        if (!M.ready) { return head + '<p class="eqm-muted">Chargement de vos pré-kits…</p>'; }
        if (!M.preReady) { return head + '<p class="eqm-muted">Pré-kits pas encore disponibles : la mise à jour de la base doit être appliquée par un responsable.</p>'; }
        var mine = M.prekits.filter(function (p) { return p.editable; });
        var opts = mine.map(function (p) {
            var has = p.items.some(function (l) { return String(l[0]).toLowerCase() === it._k; });
            return '<option value="' + esc(p.id) + '">' + esc(p.name) + (p.shared ? ' · communauté' : '') + (has ? ' · déjà dedans' : '') + '</option>';
        }).join('');
        return head + '<div class="eqm-pk" data-eqm-pk>'
            + '<label class="eqm-field"><span>Pré-kit</span><select data-eqm-pk-id>' + opts + '<option value="new"' + (mine.length ? '' : ' selected') + '>' + esc('Nouveau pré-kit…') + '</option></select></label>'
            + '<label class="eqm-field eqm-field--n"><span>Nombre</span><input type="number" min="1" max="' + PK_MAX.count + '" value="1" inputmode="numeric" data-eqm-pk-n></label>'
            + '<label class="eqm-field eqm-field--wide" data-eqm-pk-new' + (mine.length ? ' hidden' : '') + '><span>Nom du nouveau pré-kit</span><input type="text" maxlength="' + PK_MAX.name + '" placeholder="' + esc('Équipement obligatoire') + '" data-eqm-pk-name></label>'
            + (can.sharePrekits ? '<label class="eqm-check" data-eqm-pk-sharewrap' + (mine.length ? ' hidden' : '') + '><input type="checkbox" data-eqm-pk-shared> Partager avec la communauté</label>' : '')
            + '<button type="button" class="eq-hub__btn" data-eqm-pk-add>Ajouter au pré-kit</button>'
            + '<p class="eqm-pk__msg" role="status" data-eqm-pk-msg></p></div>';
    }
    function renderSheet() {
        var it = C.items[S.stack[S.stack.length - 1]];
        if (!it) { return; }
        var tr = trendOf(it);
        var facts = [];
        var fact = function (dt, dd) { facts.push('<div><dt>' + esc(dt) + '</dt><dd>' + dd + '</dd></div>'); };
        fact('Poids', it.m != null ? esc(kg(it.m)) : '<span class="eqm-muted">inconnu</span>');
        if (it.l) { fact('Capacité', esc(kg(it.l)) + ' <small class="eqm-muted">de charge</small>'); }
        if (it.k === 'magazine' && it.a) { fact('Contenance', esc(plural(it.a, 'coup', 'coups'))); }
        if (it.s && it.s.length) {
            var words = { uniform: 'uniforme', vest: 'gilet', backpack: 'sac' };
            var where = it.s.map(function (c) { return words[SLOT_KIND[c]] || ''; }).filter(Boolean);
            if (where.length) { fact('Se range dans', esc(where.join(', '))); }
        }
        if (it.p) { fact('Porté en jeu', esc('par ' + plural(it.p, 'joueur', 'joueurs'))); }
        fact('Source', srcHtml(it._src));
        fact('Classe Arma', '<code translate="no">' + esc(it.c) + '</code> <button type="button" class="eqm-link" data-eqm-copy="' + it._i + '">Copier</button>');
        var html = '<div class="eqm-sheet__head">' + iconHtml(it, 'eqm-sheet__ico')
            + '<div class="eqm-sheet__title"><h2 id="eqm-sheet-title" translate="no">' + esc(nameOf(it)) + '</h2><p>' + esc(kindLine(it)) + '</p></div></div>'
            + '<div class="eqm-sheet__acts">'
            + '<a class="eq-hub__btn" href="' + esc(urls.configurator + '?' + new URLSearchParams({ objet: it.c }).toString()) + '">' + esc('Utiliser dans le configurateur') + '</a>'
            + starHtml(it, true) + '</div>'
            + (tr ? '<p class="eqm-sheet__trend">' + TREND + '<span>' + esc(trendLabel(tr)) + '</span></p>' : '')
            + '<dl class="eqm-facts">' + facts.join('') + '</dl>'
            + (it.v ? '' : '<p class="eqm-note">Poids, capacité et compatibilités non renseignés : la configuration de cet objet n’a pas encore été importée du jeu. Un responsable peut l’importer depuis le configurateur.</p>')
            + compatHtml(it)
            + '<section class="eqm-sec eqm-sec--prekit">' + prekitHtml(it) + '</section>';
        sheetBody.innerHTML = html;
        sheet.querySelector('[data-eqm-back]').hidden = S.stack.length < 2;
    }
    function addToPrekit(it) {
        var box = sheetBody.querySelector('[data-eqm-pk]');
        if (!box) { return; }
        var msg = box.querySelector('[data-eqm-pk-msg]');
        var say = function (t, bad) { msg.textContent = t; msg.classList.toggle('is-err', !!bad); };
        var id = box.querySelector('[data-eqm-pk-id]').value;
        var n = parseInt(box.querySelector('[data-eqm-pk-n]').value, 10);
        if (!(n >= 1 && n <= PK_MAX.count)) { say('Nombre entre 1 et ' + PK_MAX.count + '.', true); return; }
        var fd = new FormData();
        var p = null;
        var items;
        if (id === 'new') {
            var name = box.querySelector('[data-eqm-pk-name]').value.trim();
            if (!name) { say('Donnez un nom au nouveau pré-kit.', true); box.querySelector('[data-eqm-pk-name]').focus(); return; }
            var sh = box.querySelector('[data-eqm-pk-shared]');
            items = [[it.c, n]];
            fd.append('name', name);
            fd.append('description', '');
            fd.append('shared', sh && sh.checked ? '1' : '0');
        } else {
            p = M.prekits.filter(function (x) { return String(x.id) === id; })[0];
            if (!p) { say('Ce pré-kit n’existe plus : rechargez la page.', true); return; }
            items = p.items.map(function (l) { return [l[0], l[1]]; });
            var found = false;
            items.forEach(function (l) { if (String(l[0]).toLowerCase() === it._k) { l[1] = Math.min(PK_MAX.count, l[1] + n); found = true; } });
            if (!found) {
                if (items.length >= PK_MAX.lines) { say(PK_MAX.lines + ' objets différents au plus dans un pré-kit.', true); return; }
                items.push([it.c, n]);
            }
            fd.append('id', String(p.id));
            fd.append('name', p.name);
            fd.append('description', p.description || '');
            fd.append('shared', p.shared ? '1' : '0');
        }
        fd.append('items', JSON.stringify(items));
        var btn = box.querySelector('[data-eqm-pk-add]');
        btn.disabled = true;
        say('Enregistrement…');
        request(urls.prekits, { form: fd }).then(function (j) {
            btn.disabled = false;
            if (!j.ok) {
                say((j.error || 'Pré-kit non enregistré.') + (j.problems && j.problems.length ? ' ' + j.problems.join(' ') : ''), true);
                return;
            }
            var label;
            if (p) {
                p.items = j.items || items;
                label = p.name;
            } else {
                label = fd.get('name');
                M.prekits.unshift({ id: j.id, name: label, description: '', items: j.items || items, shared: fd.get('shared') === '1', mine: true, editable: true });
            }
            renderSheet();
            var again = sheetBody.querySelector('[data-eqm-pk-msg]');
            if (again) { again.textContent = nameOf(it) + ' × ' + n + ' ajouté au pré-kit « ' + label + ' ». Insérez-le depuis le configurateur (bouton Pré-kit d’un contenant).'; }
            announce(nameOf(it) + ' ajouté au pré-kit ' + label + '.');
        });
    }

    // ------------------------------------------------------------------ catalogue de base d'Arma 3
    var baseRunning = false;
    function runBase() {
        if (!(boot.base && boot.base.needed) || !window.AthenaEquipmentBase || !urls.base) { return; }
        var box = $('[data-eqm-base]');
        var bar = $('[data-eqm-base-bar]');
        var text = $('[data-eqm-base-text]');
        baseRunning = true;
        box.hidden = false;
        window.AthenaEquipmentBase.run({
            url: urls.base,
            csrf: boot.csrf,
            onProgress: function (done, total) {
                var pct = total ? Math.round(done / total * 100) : 0;
                bar.style.width = pct + '%';
                bar.parentNode.setAttribute('aria-valuenow', String(pct));
                text.textContent = 'Catalogue de base d’Arma 3 : ' + num(done) + ' / ' + num(total) + ' objets';
            },
            onDone: function () {
                baseRunning = false;
                boot.base.needed = false;
                box.hidden = true;
                loadCatalog();
            },
            onError: function (m) {
                baseRunning = false;
                text.textContent = m;
                render();
            }
        });
    }

    // ------------------------------------------------------------------ événements
    var qTimer = 0;
    $('[data-eqm-q]').addEventListener('input', function (e) {
        window.clearTimeout(qTimer);
        var v = e.target.value;
        qTimer = window.setTimeout(function () { F.q = v; render(); }, 110);
    });
    $('[data-eqm-form]').addEventListener('submit', function (e) {
        e.preventDefault();
        window.clearTimeout(qTimer);
        F.q = $('[data-eqm-q]').value;
        render();
    });
    $('[data-eqm-source]').addEventListener('change', function (e) { F.src = e.target.value; render(); });
    $('[data-eqm-sort]').addEventListener('change', function (e) { F.sort = e.target.value; render(); });
    function clearAll() {
        F.q = ''; F.fam = ''; F.cat = ''; F.src = ''; F.fav = false; F.trend = false; F.rel = null;
        $('[data-eqm-q]').value = '';
        $('[data-eqm-source]').value = '';
        render();
        $('[data-eqm-q]').focus();
    }
    root.addEventListener('click', function (e) {
        var b = e.target.closest('button');
        if (!b || !root.contains(b)) { return; }
        if (b.hasAttribute('data-eqm-fam')) { F.fam = b.getAttribute('data-eqm-fam'); F.cat = ''; render(); return; }
        if (b.hasAttribute('data-eqm-cat')) { F.cat = b.getAttribute('data-eqm-cat'); render(); return; }
        if (b.hasAttribute('data-eqm-only')) { var k = b.getAttribute('data-eqm-only'); F[k] = !F[k]; render(); return; }
        if (b.hasAttribute('data-eqm-view')) { view = b.getAttribute('data-eqm-view') === 'list' ? 'list' : 'grid'; store('athena.eqm.view', view); render(); return; }
        if (b.hasAttribute('data-eqm-more-btn')) { appendBatch(); return; }
        if (b.hasAttribute('data-eqm-retry')) { loadCatalog(); return; }
        var clr = b.getAttribute('data-eqm-clear');
        if (clr === 'all') { clearAll(); return; }
        if (clr === 'rel') { F.rel = null; render(); return; }
        if (clr === 'src') { F.src = ''; $('[data-eqm-source]').value = ''; render(); return; }
        var fav = b.getAttribute('data-eqm-fav');
        if (fav !== null) { var fi = C.items[parseInt(fav, 10)]; if (fi) { toggleFav(fi); } return; }
        var open = b.getAttribute('data-eqm-open');
        if (open !== null) { var oi = C.items[parseInt(open, 10)]; if (oi) { openSheet(oi, false); } }
    });
    sheet.addEventListener('click', function (e) {
        var b = e.target.closest('button');
        if (!b) { return; }
        if (b.hasAttribute('data-eqm-close')) { closeSheet(); return; }
        if (b.hasAttribute('data-eqm-back')) { S.stack.pop(); renderSheet(); syncUrl(); return; }
        var go = b.getAttribute('data-eqm-goto');
        if (go !== null) { var gi = C.items[parseInt(go, 10)]; if (gi) { openSheet(gi, true); } return; }
        var fav = b.getAttribute('data-eqm-fav');
        if (fav !== null) { var fi = C.items[parseInt(fav, 10)]; if (fi) { toggleFav(fi); } return; }
        var rel = b.getAttribute('data-eqm-rel');
        if (rel) {
            var parts = rel.split(':');
            F.rel = { mode: parts[0], key: parts.slice(1).join(':') };
            F.fam = ''; F.cat = ''; F.fav = false; F.trend = false; F.q = '';
            $('[data-eqm-q]').value = '';
            closeSheet();
            render();
            root.scrollIntoView({ block: 'start' });
            return;
        }
        var cp = b.getAttribute('data-eqm-copy');
        if (cp !== null) {
            var ci = C.items[parseInt(cp, 10)];
            if (ci && navigator.clipboard) {
                navigator.clipboard.writeText(ci.c).then(function () { b.textContent = 'Copié'; }, function () { toast('Copie impossible : sélectionnez la classe à la main.', 'warn'); });
            }
            return;
        }
        if (b.hasAttribute('data-eqm-pk-add')) { addToPrekit(C.items[S.stack[S.stack.length - 1]]); }
    });
    sheet.addEventListener('change', function (e) {
        if (!e.target.hasAttribute('data-eqm-pk-id')) { return; }
        var isNew = e.target.value === 'new';
        var nw = sheetBody.querySelector('[data-eqm-pk-new]');
        var sw = sheetBody.querySelector('[data-eqm-pk-sharewrap]');
        if (nw) { nw.hidden = !isNew; }
        if (sw) { sw.hidden = !isNew; }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey || sheet.open || root.closest('[hidden]')) { return; }
        var t = e.target;
        if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) { return; }
        e.preventDefault();
        $('[data-eqm-q]').focus();
    });

    // ------------------------------------------------------------------ démarrage (à l'ouverture de l'onglet Catalogue)
    var started = false;
    function start() {
        if (started) { return; }
        started = true;
        readUrl();
        render();
        runBase();
        loadCatalog().then(function () { loadMember(); });
    }
    var panel = root.closest('[data-eq-panel]');
    if (!panel || !panel.hidden) {
        start();
    } else {
        Array.prototype.forEach.call(document.querySelectorAll('[data-eq-tab="catalogue"]'), function (t) { t.addEventListener('click', start); });
    }
})();
