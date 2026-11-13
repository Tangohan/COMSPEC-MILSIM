/*
 * Configurateur d'équipement (/equipment/configurateur, vue views/equipment/configurator.php).
 *
 * Modèle : deux tenues (A en cours, B pour récupérer et copier), chacune au format getUnitLoadout d'Arma 3 une fois
 * sérialisée. Règles (mêmes que App\Services\GameEquipment\Configurator\LoadoutValidator, le serveur a le dernier mot) :
 * bon emplacement, accessoires et chargeurs compatibles avec l'arme, objets acceptés par le conteneur (allowedSlots),
 * charge maximale (maximumLoad). Rien n'est inventé : une donnée absente du catalogue est signalée « non vérifiée ».
 *
 * Mise en page : palette (gauche, onglets par type ; dans l'établi, accessoires filtrés sur l'arme), plan de travail en
 * deux vues (① établi d'arme : l'arme au centre, ses emplacements autour avec leur état et la raison d'un refus ;
 * ② tenue et sacs : armes, équipement porté, conteneurs avec jauge et grille d'objets, tenue B pour copier), résumé à
 * droite (masse, accessoires, chargeurs, alertes, actions principales). Guide en 4 étapes, ouvert une fois par membre.
 *
 * Glisser-déposer : événements pointer (souris, stylet ; au doigt par la poignée ⋮⋮), copie avec Ctrl / Alt, retrait en
 * lâchant hors de la tenue ou de l'arme. Alternative clavier : Entrée ouvre le menu d'actions de l'objet (ajouter,
 * déplacer, copier vers…), Ctrl+C / Ctrl+V, Suppr. Annuler / rétablir : Ctrl+Z / Ctrl+Y (120 étapes).
 * Palette virtualisée (plusieurs milliers d'objets). Textes du jeu toujours échappés (esc) et marqués translate="no".
 */
(function () {
    'use strict';
    var root = document.querySelector('[data-cfg]');
    var bootEl = document.getElementById('cfg-boot');
    if (!root || !bootEl) {
        return;
    }
    var boot = {};
    try { boot = JSON.parse(bootEl.textContent || '{}'); } catch (e) { boot = {}; }
    var urls = boot.urls || {};
    var can = boot.can || {};

    // ------------------------------------------------------------------ outils
    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function fold(s) {
        return String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    function $(sel, ctx) { return (ctx || root).querySelector(sel); }
    function $$(sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); }
    function clone(o) { return o == null ? o : JSON.parse(JSON.stringify(o)); }
    function fmtMass(m) {
        if (m == null) { return '?'; }
        return (Math.round(m * 10) / 10).toLocaleString('fr-FR');
    }
    function kg(m) {
        // Masse Arma = dixièmes de livre (convention d'ACE) : 1 unité ≈ 45 g.
        return m == null ? '' : (Math.round(m * 0.0453592 * 10) / 10).toLocaleString('fr-FR') + ' kg';
    }
    function rounds(n) { return n + (n > 1 ? ' coups' : ' coup'); }
    function plural(n, one, many) { return n + ' ' + (n > 1 ? many : one); }
    function store(key, value) {
        try {
            if (value === undefined) { return window.localStorage.getItem(key); }
            if (value === null) { window.localStorage.removeItem(key); } else { window.localStorage.setItem(key, value); }
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
            return r.json().catch(function () { return { ok: false, error: 'Réponse illisible du serveur.' }; }).then(function (j) {
                j = j || {};
                j.status = r.status;
                return j;
            });
        }).catch(function () { return { ok: false, error: 'Serveur injoignable : vérifiez la connexion.' }; });
    }
    var live = $('[data-cfg-live]');
    function announce(msg) { if (live) { live.textContent = ''; window.setTimeout(function () { live.textContent = msg; }, 30); } }
    var toastEl = $('[data-cfg-toast]');
    var toastTimer = 0;
    function toast(msg, kind) {
        if (!toastEl) { return; }
        toastEl.textContent = msg;
        toastEl.className = 'cfg-toast' + (kind ? ' cfg-toast--' + kind : '');
        toastEl.hidden = false;
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(function () { toastEl.hidden = true; }, kind === 'err' ? 6500 : 4200);
        announce(msg);
    }
    function svgIcon(d) { return '<svg class="cfg-ico-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="' + d + '"/></svg>'; }

    // ------------------------------------------------------------------ libellés
    var KIND_LABEL = {
        primary: 'Arme principale', secondary: 'Lanceur', handgun: 'Arme de poing', muzzle: 'Bouche', optic: 'Optique',
        pointer: 'Rail latéral', bipod: 'Bipied ou poignée', magazine: 'Chargeur ou munition', uniform: 'Uniforme',
        vest: 'Gilet', backpack: 'Sac à dos', headgear: 'Casque ou coiffe', goggles: 'Lunettes', nvg: 'Vision nocturne',
        binocular: 'Jumelles', radio: 'Radio', map: 'Carte', gps: 'GPS ou terminal', compass: 'Boussole', watch: 'Montre',
        medical: 'Médical', item: 'Objet'
    };
    var CAT_LABEL = {
        fusil: 'Fusils', pm: 'Pistolets-mitrailleurs', mitrailleuse: 'Mitrailleuses', precision: 'Précision',
        pompe: 'Fusils à pompe', 'lance-grenades': 'Lance-grenades', pistolet: 'Pistolets', lanceur: 'Lanceurs',
        balles: 'Balles', grenades: 'Grenades et fumigènes', roquettes: 'Roquettes et missiles', explosifs: 'Mines et explosifs'
    };
    // Onglets de la palette, par vue. Dans l'établi, les accessoires et chargeurs sont filtrés sur l'arme.
    var TABS = {
        bench: [
            { id: 'weapons', label: 'Armes', kinds: ['primary', 'handgun', 'secondary'] },
            { id: 'optic', label: 'Optiques', kinds: ['optic'], att: 'optic' },
            { id: 'muzzle', label: 'Bouches', kinds: ['muzzle'], att: 'muzzle' },
            { id: 'pointer', label: 'Rails', kinds: ['pointer'], att: 'pointer' },
            { id: 'bipod', label: 'Bipieds', kinds: ['bipod'], att: 'bipod' },
            { id: 'mag', label: 'Chargeurs', kinds: ['magazine'], att: 'mag' }
        ],
        // Peu d'onglets, précisés par des sous-filtres : la liste reste visible sans défiler.
        outfit: [
            { id: 'all', label: 'Tout', kinds: null },
            { id: 'weapons', label: 'Armes', kinds: ['primary', 'handgun', 'secondary'] },
            { id: 'wear', label: 'Tenues et sacs', kinds: ['uniform', 'vest', 'backpack'],
                subs: [['uniform', 'Uniformes', ['uniform']], ['vest', 'Gilets', ['vest']], ['backpack', 'Sacs', ['backpack']]] },
            { id: 'head', label: 'Casques et lunettes', kinds: ['headgear', 'goggles'],
                subs: [['headgear', 'Casques et coiffes', ['headgear']], ['goggles', 'Lunettes', ['goggles']]] },
            { id: 'ammo', label: 'Munitions', kinds: ['magazine'] },
            { id: 'acc', label: 'Accessoires', kinds: ['optic', 'muzzle', 'pointer', 'bipod'],
                subs: [['optic', 'Optiques', ['optic']], ['muzzle', 'Bouches', ['muzzle']], ['pointer', 'Rails latéraux', ['pointer']], ['bipod', 'Bipieds', ['bipod']]] },
            { id: 'items', label: 'Objets', kinds: ['nvg', 'binocular', 'radio', 'map', 'gps', 'compass', 'watch', 'medical', 'item'],
                subs: [['optics', 'Vision et jumelles', ['nvg', 'binocular']], ['tools', 'Radio, carte, GPS', ['radio', 'map', 'gps', 'compass', 'watch']],
                    ['medical', 'Médical', ['medical']], ['item', 'Autres', ['item']]] }
        ]
    };
    function subKinds(t, id) {
        for (var i = 0; t.subs && i < t.subs.length; i++) { if (t.subs[i][0] === id) { return t.subs[i][2]; } }
        return null;
    }
    var WEAPON_SLOTS = [['primary', 'Arme principale'], ['handgun', 'Arme de poing'], ['secondary', 'Lanceur']];
    var GEAR_SLOTS = [['headgear', 'Casque'], ['goggles', 'Lunettes'], ['nvg', 'Vision nocturne'], ['binocular', 'Jumelles'],
        ['radio', 'Radio'], ['map', 'Carte'], ['gps', 'GPS'], ['compass', 'Boussole'], ['watch', 'Montre']];
    var CONTAINERS = [['uniform', 'Uniforme'], ['vest', 'Gilet'], ['backpack', 'Sac à dos']];
    var CONTAINER_CODE = { uniform: 801, vest: 701, backpack: 901 };
    var ATTACH = [['muzzle', 'Bouche'], ['optic', 'Optique'], ['pointer', 'Rail latéral'], ['bipod', 'Bipied / poignée'], ['mag', 'Chargeur'], ['mag2', '2e chargeur / grenade']];
    var ATT_SHORT = { muzzle: 'mu', optic: 'op', pointer: 'po', bipod: 'bi' };
    var ATT_HINT = {
        optic: 'Lunette, viseur point rouge…', muzzle: 'Silencieux, cache-flamme…', pointer: 'Lampe ou laser',
        bipod: 'Bipied ou poignée', mag: 'Chargeur engagé', mag2: 'Lance-grenades ou 2e chargeur'
    };
    var OUTFIT_NAME = { a: 'Tenue A', b: 'Tenue B' };
    function slotLabel(slot) {
        var all = WEAPON_SLOTS.concat(GEAR_SLOTS, CONTAINERS, ATTACH);
        for (var i = 0; i < all.length; i++) { if (all[i][0] === slot) { return all[i][1]; } }
        return slot;
    }

    // ------------------------------------------------------------------ catalogue
    var CAT = { items: [], byKey: {}, groups: [], sets: [], ready: false, specs: 0, base: 0, counts: {} };
    function spec(cls) { return cls ? (CAT.byKey[String(cls).toLowerCase()] || null) : null; }
    function nameOf(cls) { var s = spec(cls); return s && s.n ? s.n : String(cls || ''); }
    function groupSet(i) {
        if (i == null || !CAT.groups[i]) { return null; }
        if (!CAT.sets[i]) {
            var set = {};
            CAT.groups[i].forEach(function (c) { set[c] = true; });
            CAT.sets[i] = set;
        }
        return CAT.sets[i];
    }
    function compat(weaponCls, short) {
        var s = spec(weaponCls);
        return s && s.x && s.x[short] !== undefined ? groupSet(s.x[short]) : null;
    }
    function loadCatalog() {
        return request(urls.catalogue).then(function (j) {
            if (!j.ok) {
                $('[data-cfg-count]').textContent = j.error || 'Catalogue indisponible.';
                return;
            }
            CAT.items = j.items || [];
            CAT.groups = j.groups || [];
            CAT.sets = [];
            CAT.byKey = {};
            CAT.specs = 0;
            CAT.base = 0;
            CAT.counts = {};
            CAT.items.forEach(function (it) {
                it._f = fold((it.n || '') + ' ' + it.c);
                CAT.byKey[it.c.toLowerCase()] = it;
                if (it.v) { CAT.specs++; }
                if (it.b) { CAT.base++; }
                CAT.counts[it.k] = (CAT.counts[it.k] || 0) + 1;
            });
            // Ordre : porté en jeu d'abord, puis famille (fusils avant pistolets et lanceurs), puis nom.
            var order = ['primary', 'handgun', 'secondary', 'optic', 'muzzle', 'pointer', 'bipod', 'magazine', 'uniform', 'vest', 'backpack', 'headgear'];
            var rank = function (k) { var i = order.indexOf(k); return i < 0 ? order.length : i; };
            CAT.items.sort(function (a, b) { return (b.p || 0) - (a.p || 0) || rank(a.k) - rank(b.k) || (a.n || a.c).localeCompare(b.n || b.c); });
            CAT.items.forEach(function (it, idx) { it._i = idx; });
            CAT.ready = true;
            renderTabs();
            filterPalette();
            renderAll();
        });
    }

    // ------------------------------------------------------------------ modèle
    function blankOutfit() {
        return { name: '', ref: '', meta: {}, primary: null, secondary: null, handgun: null, binocular: null,
            uniform: null, vest: null, backpack: null, headgear: '', goggles: '', map: '', gps: '', radio: '', compass: '', watch: '', nvg: '' };
    }
    function weaponFrom(a) {
        if (!Array.isArray(a) || !a.length || typeof a[0] !== 'string' || a[0] === '') { return null; }
        function mag(m) { return Array.isArray(m) && m.length && m[0] ? { cls: String(m[0]), ammo: Math.max(0, parseInt(m[1], 10) || 0) } : null; }
        return { cls: a[0], muzzle: a[1] || '', pointer: a[2] || '', optic: a[3] || '', mag: mag(a[4]), mag2: mag(a[5]), bipod: a[6] || '' };
    }
    function weaponTo(w) {
        if (!w) { return []; }
        function mag(m) { return m ? [m.cls, m.ammo] : []; }
        return [w.cls, w.muzzle || '', w.pointer || '', w.optic || '', mag(w.mag), mag(w.mag2), w.bipod || ''];
    }
    function containerFrom(a) {
        if (!Array.isArray(a) || !a.length || !a[0]) { return null; }
        var items = [];
        (Array.isArray(a[1]) ? a[1] : []).forEach(function (e) {
            if (!Array.isArray(e) || e.length < 2) { return; }
            if (Array.isArray(e[0])) {
                var w = weaponFrom(e[0]);
                if (w) { items.push({ t: 'weapon', w: w, n: Math.max(1, parseInt(e[1], 10) || 1) }); }
            } else if (e[1] === true || e[1] === false) {
                items.push({ t: 'pack', cls: String(e[0]), n: 1 });
            } else if (e.length >= 3) {
                items.push({ t: 'mag', cls: String(e[0]), n: Math.max(1, parseInt(e[1], 10) || 1), ammo: Math.max(0, parseInt(e[2], 10) || 0) });
            } else {
                items.push({ t: 'item', cls: String(e[0]), n: Math.max(1, parseInt(e[1], 10) || 1) });
            }
        });
        return { cls: a[0], items: items };
    }
    function containerTo(c) {
        if (!c) { return []; }
        return [c.cls, c.items.map(function (e) {
            if (e.t === 'weapon') { return [weaponTo(e.w), e.n]; }
            if (e.t === 'pack') { return [e.cls, true]; }
            if (e.t === 'mag') { return [e.cls, e.n, e.ammo]; }
            return [e.cls, e.n];
        })];
    }
    function outfitFrom(lo, name, ref, meta) {
        var o = blankOutfit();
        o.name = name || '';
        o.ref = ref || '';
        o.meta = meta || {};
        if (!Array.isArray(lo)) { return o; }
        o.primary = weaponFrom(lo[0]);
        o.secondary = weaponFrom(lo[1]);
        o.handgun = weaponFrom(lo[2]);
        o.uniform = containerFrom(lo[3]);
        o.vest = containerFrom(lo[4]);
        o.backpack = containerFrom(lo[5]);
        o.headgear = typeof lo[6] === 'string' ? lo[6] : '';
        o.goggles = typeof lo[7] === 'string' ? lo[7] : '';
        o.binocular = weaponFrom(lo[8]);
        var as = Array.isArray(lo[9]) ? lo[9] : [];
        ['map', 'gps', 'radio', 'compass', 'watch', 'nvg'].forEach(function (k, i) { o[k] = typeof as[i] === 'string' ? as[i] : ''; });
        return o;
    }
    function outfitTo(o) {
        return [weaponTo(o.primary), weaponTo(o.secondary), weaponTo(o.handgun), containerTo(o.uniform), containerTo(o.vest),
            containerTo(o.backpack), o.headgear || '', o.goggles || '', o.binocular ? weaponTo(o.binocular) : [],
            [o.map || '', o.gps || '', o.radio || '', o.compass || '', o.watch || '', o.nvg || '']];
    }
    function outfitClasses(o) {
        var out = {};
        function add(c) { if (c) { out[c.toLowerCase()] = c; } }
        function weapon(w) {
            if (!w) { return; }
            add(w.cls); add(w.muzzle); add(w.pointer); add(w.optic); add(w.bipod);
            if (w.mag) { add(w.mag.cls); }
            if (w.mag2) { add(w.mag2.cls); }
        }
        weapon(o.primary); weapon(o.secondary); weapon(o.handgun); weapon(o.binocular);
        CONTAINERS.forEach(function (c) {
            var ct = o[c[0]];
            if (!ct) { return; }
            add(ct.cls);
            ct.items.forEach(function (e) { if (e.t === 'weapon') { weapon(e.w); } else { add(e.cls); } });
        });
        ['headgear', 'goggles', 'map', 'gps', 'radio', 'compass', 'watch', 'nvg'].forEach(function (k) { add(o[k]); });
        return Object.keys(out).map(function (k) { return out[k]; });
    }
    function ammoFor(cls) {
        var s = spec(cls);
        if (s && s.a) { return { ammo: s.a, known: true }; }
        // Même règle que RoleKitLoadout::ammoFor : nombre lu dans la classe (« 30Rnd ») ; le mod remplit les chargeurs.
        var m = /(\d{1,4})\s*rnd/i.exec(cls || '');
        return { ammo: m ? parseInt(m[1], 10) : 1, known: false };
    }

    // ------------------------------------------------------------------ masses et règles
    function massOf(cls) { var s = spec(cls); return s && typeof s.m === 'number' ? s.m : null; }
    function weaponMass(w) {
        if (!w) { return 0; }
        var parts = [w.cls, w.muzzle, w.pointer, w.optic, w.bipod, w.mag && w.mag.cls, w.mag2 && w.mag2.cls].filter(Boolean);
        var t = 0;
        for (var i = 0; i < parts.length; i++) {
            var m = massOf(parts[i]);
            if (m == null) { return null; }
            t += m;
        }
        return t;
    }
    function entryMass(e) {
        var m = e.t === 'weapon' ? weaponMass(e.w) : massOf(e.cls);
        return m == null ? null : m * (e.n || 1);
    }
    function containerLoad(c) {
        if (!c) { return { load: 0, max: null, known: true }; }
        var t = 0;
        var known = true;
        c.items.forEach(function (e) { var m = entryMass(e); if (m == null) { known = false; } else { t += m; } });
        var s = spec(c.cls);
        return { load: t, max: s && s.l ? s.l : null, known: known };
    }
    function outfitMass(o) {
        var t = 0;
        var known = true;
        function add(m) { if (m == null) { known = false; } else { t += m; } }
        ['primary', 'secondary', 'handgun', 'binocular'].forEach(function (k) { add(weaponMass(o[k])); });
        CONTAINERS.forEach(function (c) {
            var ct = o[c[0]];
            if (!ct) { return; }
            add(massOf(ct.cls));
            var l = containerLoad(ct);
            add(l.known ? l.load : null);
        });
        ['headgear', 'goggles', 'map', 'gps', 'radio', 'compass', 'watch', 'nvg'].forEach(function (k) { if (o[k]) { add(massOf(o[k])); } });
        return known ? t : null;
    }
    function acceptedKinds(slot) {
        if (slot === 'map' || slot === 'gps' || slot === 'compass' || slot === 'watch') { return [slot, 'item']; }
        if (slot === 'radio') { return ['radio', 'item']; }
        return [slot];
    }
    /** L'objet va-t-il à cet emplacement de la tenue ? {ok, reason} */
    function kindFits(slot, cls) {
        var s = spec(cls);
        if (!s) { return { ok: false, reason: '« ' + cls + ' » est absent du catalogue de la communauté.' }; }
        var kinds = acceptedKinds(slot);
        if (kinds.indexOf(s.k) >= 0 && !(s.k === 'item' && s.v && slot !== 'radio' && kinds.length > 1 && slot !== s.k)) {
            return { ok: true };
        }
        if (s.k === 'item' && !s.v && kinds.indexOf('item') >= 0) { return { ok: true }; }
        return { ok: false, reason: '« ' + nameOf(cls) + ' » (' + (KIND_LABEL[s.k] || s.k) + ') ne va pas à l’emplacement ' + slotLabel(slot) + '.' };
    }
    /** Accessoire ou chargeur compatible avec l'arme ? {ok, reason, unchecked} */
    function attachFits(weaponCls, att, cls) {
        var s = spec(cls);
        if (!s) { return { ok: false, reason: '« ' + cls + ' » est absent du catalogue de la communauté.' }; }
        if (att === 'mag' || att === 'mag2') {
            if (s.k !== 'magazine') { return { ok: false, reason: '« ' + nameOf(cls) + ' » n’est pas un chargeur.' }; }
            var mags = compat(weaponCls, 'mg');
            if (!mags) { return { ok: true, unchecked: true }; }
            return mags[cls.toLowerCase()] ? { ok: true } : { ok: false, reason: '« ' + nameOf(cls) + ' » ne va pas dans « ' + nameOf(weaponCls) + ' » (chargeur incompatible).' };
        }
        if (s.k !== att) { return { ok: false, reason: '« ' + nameOf(cls) + ' » (' + (KIND_LABEL[s.k] || s.k) + ') ne se monte pas à l’emplacement ' + slotLabel(att) + '.' }; }
        var list = compat(weaponCls, ATT_SHORT[att]);
        if (!list) { return { ok: true, unchecked: true }; }
        return list[cls.toLowerCase()] ? { ok: true } : { ok: false, reason: '« ' + nameOf(cls) + ' » ne se monte pas sur « ' + nameOf(weaponCls) + ' » (emplacement ' + slotLabel(att).toLowerCase() + ').' };
    }
    /**
     * Arme remplacée par une autre classe : les accessoires et chargeurs compatibles avec la nouvelle arme sont gardés,
     * les autres retirés (et annoncés). Compatibilités inconnues : gardés, l'alerte « non vérifiées » le dit.
     */
    function transferWeapon(prev, cls) {
        var w = { cls: cls, muzzle: '', pointer: '', optic: '', bipod: '', mag: null, mag2: null };
        var kept = [];
        var removed = [];
        if (!prev) { return { w: w, kept: kept, removed: removed }; }
        ['muzzle', 'optic', 'pointer', 'bipod'].forEach(function (a) {
            if (!prev[a]) { return; }
            if (attachFits(cls, a, prev[a]).ok) { w[a] = prev[a]; kept.push(nameOf(prev[a])); } else { removed.push(nameOf(prev[a])); }
        });
        ['mag', 'mag2'].forEach(function (a) {
            if (!prev[a]) { return; }
            if (attachFits(cls, a, prev[a].cls).ok) { w[a] = clone(prev[a]); kept.push(nameOf(prev[a].cls)); } else { removed.push(nameOf(prev[a].cls)); }
        });
        return { w: w, kept: kept, removed: removed };
    }
    function transferNote(tr) {
        return (tr.kept.length ? ' Gardé : ' + tr.kept.join(', ') + '.' : '') + (tr.removed.length ? ' Retiré car incompatible : ' + tr.removed.join(', ') + '.' : '');
    }
    /** Chargeurs (balles, roquettes) rangés qui ne vont dans aucune arme de la tenue (même règle que LoadoutValidator). */
    function orphanMags(o) {
        var weapons = WEAPON_SLOTS.map(function (s) { return o[s[0]]; }).filter(Boolean);
        var stored = {};
        CONTAINERS.forEach(function (c) {
            var ct = o[c[0]];
            if (!ct) { return; }
            ct.items.forEach(function (e) {
                if (e.t === 'weapon') { weapons.push(e.w); } else if (e.t === 'mag') { stored[e.cls.toLowerCase()] = e.cls; }
            });
        });
        if (!weapons.length) { return []; }
        var ok = {};
        for (var i = 0; i < weapons.length; i++) {
            var mags = compat(weapons[i].cls, 'mg');
            if (!mags) { return []; }
            Object.keys(mags).forEach(function (k) { ok[k] = true; });
        }
        return Object.keys(stored).filter(function (k) {
            var s = spec(k);
            return !ok[k] && s && (s.g === 'balles' || s.g === 'roquettes');
        }).map(function (k) { return stored[k]; });
    }
    /**
     * Armes de la tenue (posées et rangées) : objets compatibles pour un emplacement (« mg » chargeurs, « mu », « op »,
     * « po », « bi »), réunis. null si la tenue n'a pas d'arme ou si une de ses armes n'a pas de liste connue.
     */
    function outfitCompat(o, short) {
        var weapons = WEAPON_SLOTS.map(function (s) { return o[s[0]]; }).filter(Boolean);
        CONTAINERS.forEach(function (c) { if (o[c[0]]) { o[c[0]].items.forEach(function (e) { if (e.t === 'weapon') { weapons.push(e.w); } }); } });
        if (!weapons.length) { return null; }
        var out = {};
        for (var i = 0; i < weapons.length; i++) {
            var set = compat(weapons[i].cls, short);
            if (!set) { return null; }
            Object.keys(set).forEach(function (k) { out[k] = true; });
        }
        return out;
    }
    /** Le conteneur accepte-t-il cet objet, et y a-t-il la place ? */
    function containerFits(o, slot, entry, sameRef) {
        var c = o[slot];
        if (!c) { return { ok: false, reason: 'Posez d’abord ' + (slot === 'backpack' ? 'un sac' : (slot === 'vest' ? 'un gilet' : 'un uniforme')) + ' pour y ranger des objets.' }; }
        var cls = entry.t === 'weapon' ? entry.w.cls : entry.cls;
        var s = spec(cls);
        if (!s) { return { ok: false, reason: '« ' + cls + ' » est absent du catalogue de la communauté.' }; }
        if (s.k === 'backpack') { return { ok: false, reason: 'Arma ne range pas un sac dans un conteneur porté.' }; }
        if (s.s && s.s.length && s.s.indexOf(CONTAINER_CODE[slot]) < 0) {
            return { ok: false, reason: '« ' + nameOf(cls) + ' » ne se range pas dans ' + (slot === 'backpack' ? 'le sac' : (slot === 'vest' ? 'le gilet' : 'l’uniforme')) + ' (le jeu le refuse).' };
        }
        var load = containerLoad(c);
        var add = entryMass(entry);
        if (sameRef) { return { ok: true }; }
        if (load.max != null && load.known && add != null && load.load + add > load.max + 0.0001) {
            return { ok: false, reason: 'Plus de place : ' + fmtMass(load.load) + ' / ' + load.max + ', il faut ' + fmtMass(add) + '.' };
        }
        return { ok: true, unchecked: load.max == null || !load.known || add == null };
    }
    /** Points à corriger et avertissements d'une tenue (mêmes règles que le serveur). */
    function checkOutfit(o) {
        var errors = [];
        var warns = [];
        var unknown = outfitClasses(o).filter(function (c) { return !spec(c); });
        if (unknown.length) { errors.push('Absent du catalogue : ' + unknown.slice(0, 6).join(', ') + (unknown.length > 6 ? '…' : '') + '.'); }
        function weapon(w, slot) {
            if (!w) { return; }
            if (slot) { var k = kindFits(slot, w.cls); if (!k.ok && spec(w.cls)) { errors.push(k.reason); } }
            var unchecked = false;
            ['muzzle', 'optic', 'pointer', 'bipod'].forEach(function (a) {
                if (!w[a] || !spec(w[a])) { return; }
                var f = attachFits(w.cls, a, w[a]);
                if (!f.ok) { errors.push(f.reason); } else if (f.unchecked) { unchecked = true; }
            });
            ['mag', 'mag2'].forEach(function (a) {
                if (!w[a] || !spec(w[a].cls)) { return; }
                var f = attachFits(w.cls, a, w[a].cls);
                if (!f.ok) { errors.push(f.reason); } else if (f.unchecked) { unchecked = true; }
            });
            if (unchecked && spec(w.cls)) { warns.push('Compatibilités de « ' + nameOf(w.cls) + ' » non vérifiées (catalogue de configuration à importer).'); }
        }
        weapon(o.primary, 'primary'); weapon(o.secondary, 'secondary'); weapon(o.handgun, 'handgun'); weapon(o.binocular, 'binocular');
        GEAR_SLOTS.forEach(function (g) {
            if (g[0] !== 'binocular' && o[g[0]] && spec(o[g[0]])) { var k = kindFits(g[0], o[g[0]]); if (!k.ok) { errors.push(k.reason); } }
        });
        CONTAINERS.forEach(function (c) {
            var ct = o[c[0]];
            if (!ct) { return; }
            if (spec(ct.cls)) { var k = kindFits(c[0], ct.cls); if (!k.ok) { errors.push(k.reason); } }
            ct.items.forEach(function (e) {
                if (e.t === 'weapon') { weapon(e.w, null); }
                var cls = e.t === 'weapon' ? e.w.cls : e.cls;
                var s = spec(cls);
                if (s && s.s && s.s.length && s.s.indexOf(CONTAINER_CODE[c[0]]) < 0) { errors.push('« ' + nameOf(cls) + ' » ne se range pas dans : ' + c[1] + '.'); }
            });
            var l = containerLoad(ct);
            if (l.max != null && l.known && l.load > l.max + 0.0001) { errors.push(c[1] + ' déborde : ' + fmtMass(l.load) + ' / ' + l.max + '.'); }
            if (ct.items.length && (l.max == null || !l.known)) { warns.push('Charge non vérifiée : ' + c[1] + ' (masse ou capacité inconnue).'); }
        });
        orphanMags(o).forEach(function (cls) { warns.push('« ' + nameOf(cls) + ' » ne va dans aucune arme de la tenue (munitions pour un autre joueur ?).'); });
        return { errors: errors.filter(uniq), warns: warns.filter(uniq) };
    }
    function uniq(v, i, a) { return a.indexOf(v) === i; }

    // ------------------------------------------------------------------ état et historique
    var S = { outfits: { a: blankOutfit(), b: null }, mode: 'bench', bench: 'a:primary:-', sel: '' };
    var undoStack = [];
    var redoStack = [];
    function snapshot() { return JSON.stringify(S.outfits); }
    function commit(label, fn) {
        var before = snapshot();
        var res = fn();
        if (res === false) { return false; }
        if (snapshot() === before) { return false; }
        undoStack.push(before);
        if (undoStack.length > 120) { undoStack.shift(); }
        redoStack = [];
        afterChange(label);
        return true;
    }
    function afterChange(label) {
        // Munitions et accessoires filtrés sur les armes de la tenue : la liste suit les armes posées.
        if (CAT.ready && outfitFiltered(currentTab())) { var keepTop = vlist.scrollTop; filterPalette(); vlist.scrollTop = keepTop; renderRows(); }
        renderAll();
        saveDraft();
        if (label) { announce(label); }
    }
    function undo() {
        if (!undoStack.length) { return; }
        redoStack.push(snapshot());
        S.outfits = JSON.parse(undoStack.pop());
        afterChange('Annulé.');
        toast('Annulé.');
    }
    function redo() {
        if (!redoStack.length) { return; }
        undoStack.push(snapshot());
        S.outfits = JSON.parse(redoStack.pop());
        afterChange('Rétabli.');
        toast('Rétabli.');
    }
    var draftTimer = 0;
    function saveDraft() {
        window.clearTimeout(draftTimer);
        draftTimer = window.setTimeout(function () {
            store('athena.cfg.draft.v1', JSON.stringify({ outfits: S.outfits, at: Date.now() }));
        }, 400);
    }

    // ------------------------------------------------------------------ icônes et silhouettes
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
    // Silhouettes génériques (240 × 80) : fusil, pistolet, lanceur. Aucune image du jeu.
    var SIL = {
        rifle: 'M4 31l40-4v18L10 52zM44 24h106v17H44zM150 27h46v11h-46zM196 30h40v4h-40zM70 19h68v5H70zM96 41h16l5 24-15 2zM62 41h15l-6 22H58z',
        pistol: 'M36 22h150v18H36zM186 26h18v10h-18zM58 40h38l-8 34H52zM96 40h18l4 10h-22z',
        launcher: 'M14 30h208a8 8 0 0 1 0 16H14a8 8 0 0 1 0-16zM96 46h16l-4 22H94zM130 46h12l-2 14h-12zM70 20h34v10H70z'
    };
    function silKind(w, slot) {
        var s = spec(w && w.cls);
        var k = (s && s.k) || slot;
        return k === 'handgun' ? 'pistol' : (k === 'secondary' ? 'launcher' : 'rifle');
    }
    function silhouette(kind, cls) {
        return '<svg class="cfg-sil ' + (cls || '') + '" viewBox="0 0 240 80" aria-hidden="true"><path d="' + SIL[kind] + '"/></svg>';
    }
    function iconHtml(cls, fallbackKind) {
        var s = spec(cls);
        if (s && s.i && urls.icons) {
            return '<img class="cfg-ico" src="' + esc(urls.icons + s.i + '.png') + '" alt="" loading="lazy" decoding="async">';
        }
        var k = (s && s.k) || fallbackKind || 'item';
        return '<svg class="cfg-ico cfg-ico--svg" viewBox="0 0 24 24" aria-hidden="true"><path d="' + (ICONS[k] || ICONS.item) + '"/></svg>';
    }

    // ------------------------------------------------------------------ établi : arme travaillée
    /** Arme désignée par « o:slot:i » (i = « - » pour un emplacement d'arme, sinon l'index dans le conteneur). */
    function weaponAt(ref) {
        var p = String(ref).split(':');
        var out = S.outfits[p[0]];
        if (!out) { return null; }
        if (p[2] === '-' || p[2] === undefined) { return out[p[1]] || null; }
        var c = out[p[1]];
        var e = c && c.items[parseInt(p[2], 10)];
        return e && e.t === 'weapon' ? e.w : null;
    }
    function benchWeapon() { return weaponAt(S.bench); }
    /** Après un changement : l'établi garde une arme existante (sinon la première de la tenue A). */
    function fixBench() {
        if (weaponAt(S.bench)) { return; }
        var p = String(S.bench).split(':');
        if (p[0] === 'a' && p[2] === '-' && ['primary', 'handgun', 'secondary'].indexOf(p[1]) >= 0) {
            for (var i = 0; i < WEAPON_SLOTS.length; i++) {
                if (S.outfits.a[WEAPON_SLOTS[i][0]]) { S.bench = 'a:' + WEAPON_SLOTS[i][0] + ':-'; return; }
            }
            return;
        }
        S.bench = 'a:primary:-';
    }

    // ------------------------------------------------------------------ données du membre : favoris, pré-kits, tendances
    var M = { ready: false, readyFav: false, readyPre: false, fav: {}, prekits: [], trends: { items: {}, wardrobes: [], days: 30, min: 2 } };
    function isFav(cls) { return !!M.fav[String(cls || '').toLowerCase()]; }
    function trendOf(cls) { return M.trends.items[String(cls || '').toLowerCase()] || null; }
    function trendScore(t) { return t ? (t.w || 0) * 3 + (t.t || 0) * 2 + (t.f || 0) : 0; }
    function trendLabel(t) {
        if (!t) { return ''; }
        var parts = [];
        if (t.w) { parts.push('porté en jeu par ' + t.w); }
        if (t.t) { parts.push('dans les tenues de ' + t.t); }
        if (t.f) { parts.push('favori de ' + t.f); }
        return parts.join(', ') + ' (' + M.trends.days + ' j)';
    }
    function favCount() { return Object.keys(M.fav).length; }
    function loadMember() {
        if (!urls.social) { return Promise.resolve(); }
        return request(urls.social).then(function (j) {
            if (!j.ok) { return; }
            M.ready = true;
            M.readyFav = !!(j.ready && j.ready.favorites);
            M.readyPre = !!(j.ready && j.ready.prekits);
            M.fav = {};
            (j.favorites || []).forEach(function (c) { M.fav[String(c).toLowerCase()] = true; });
            M.prekits = j.prekits || [];
            M.trends = j.trends || M.trends;
            M.trends.items = M.trends.items && !Array.isArray(M.trends.items) ? M.trends.items : {};
            var fc = $('[data-cfg-fav-count]');
            if (fc) { fc.textContent = String(favCount()); }
            filterPalette();
        });
    }
    function toggleFav(cls) {
        if (!M.readyFav) { toast('Favoris pas encore disponibles : la mise à jour de la base doit être appliquée par un responsable.', 'warn'); return; }
        var key = String(cls).toLowerCase();
        var on = !M.fav[key];
        if (on) { M.fav[key] = true; } else { delete M.fav[key]; }
        $('[data-cfg-fav-count]').textContent = String(favCount());
        if (P.fav) { filterPalette(); } else { renderRows(); }
        var fd = new FormData();
        fd.append('class', cls);
        fd.append('on', on ? '1' : '0');
        request(urls.favorite, { form: fd }).then(function (j) {
            if (j.ok) { announce(on ? nameOf(cls) + ' ajouté aux favoris.' : nameOf(cls) + ' retiré des favoris.'); return; }
            if (on) { delete M.fav[key]; } else { M.fav[key] = true; }
            $('[data-cfg-fav-count]').textContent = String(favCount());
            filterPalette();
            toast(j.error || 'Favori non enregistré.', 'err');
        });
    }

    // ------------------------------------------------------------------ palette virtualisée
    var P = { list: [], tab: { bench: 'weapons', outfit: 'all' }, cat: '', q: '', rowH: 58, showAll: false, fav: false, trend: false, forWeapons: true };
    /** Onglet de la vue « Tenue » filtré sur les armes de la tenue A (munitions, accessoires). */
    function outfitFiltered(t) { return S.mode === 'outfit' && (t.id === 'ammo' || t.id === 'acc'); }
    var vlist = $('[data-cfg-vlist]');
    var vspacer = $('[data-cfg-vspacer]');
    function currentTab() {
        var tabs = TABS[S.mode];
        for (var i = 0; i < tabs.length; i++) { if (tabs[i].id === P.tab[S.mode]) { return tabs[i]; } }
        return tabs[0];
    }
    function countFor(t) {
        if (!t.kinds) { return CAT.items.length; }
        return t.kinds.reduce(function (n, k) { return n + (CAT.counts[k] || 0); }, 0);
    }
    function renderTabs() {
        var tabs = TABS[S.mode].filter(function (t) { return !t.kinds || t.att || countFor(t) > 0; });
        var cur = currentTab();
        $('[data-cfg-families]').innerHTML = tabs.map(function (t) {
            var on = t.id === cur.id;
            return '<button type="button" class="cfg-tab" role="tab" aria-selected="' + on + '" data-tab="' + t.id + '">' + esc(t.label) + ' <span>' + countFor(t).toLocaleString('fr-FR') + '</span></button>';
        }).join('');
        renderCats();
        var help = $('[data-cfg-palette-help]');
        var w = benchWeapon();
        if (S.mode === 'bench' && cur.att) {
            help.innerHTML = w
                ? esc('Seuls les objets compatibles avec l’arme sont proposés. Glissez-en un sur son emplacement, ou cliquez sur ＋.')
                : esc('Choisissez d’abord une arme (onglet Armes) : les accessoires compatibles apparaîtront ici.');
        } else if (S.mode === 'bench') {
            help.innerHTML = esc('Glissez une arme sur l’établi, ou cliquez sur ＋. Exemple : tapez') + ' <em>MX</em>.';
        } else if (outfitFiltered(cur)) {
            help.innerHTML = esc(P.forWeapons ? 'Seuls les chargeurs et accessoires qui vont sur les armes de la tenue A sont proposés (grenades et explosifs : toujours).' : 'Tous les chargeurs et accessoires du catalogue sont proposés.');
        } else {
            help.innerHTML = esc('Glissez un objet sur un emplacement ou dans un conteneur, ou cliquez sur ＋. Exemple :') + ' <em>carryall</em>.';
        }
        $('[data-cfg-showall-wrap]').hidden = !(S.mode === 'bench' && cur.att && w);
        $('[data-cfg-forweapons-wrap]').hidden = !outfitFiltered(cur);
        $$('[data-cfg-pfilter]').forEach(function (b) { b.setAttribute('aria-pressed', String(!!P[b.getAttribute('data-cfg-pfilter')])); b.classList.toggle('is-on', !!P[b.getAttribute('data-cfg-pfilter')]); });
    }
    function renderCats() {
        var box = $('[data-cfg-cats]');
        var t = currentTab();
        var chips = [];
        if (t.id === 'weapons' || t.id === 'ammo' || t.id === 'mag') {
            var cats = {};
            CAT.items.forEach(function (it) { if (it.g && t.kinds.indexOf(it.k) >= 0) { cats[it.g] = true; } });
            chips = Object.keys(CAT_LABEL).filter(function (c) { return cats[c]; }).map(function (c) { return [c, CAT_LABEL[c]]; });
        } else if (t.subs) {
            chips = t.subs.filter(function (s) { return s[2].some(function (k) { return CAT.counts[k]; }); }).map(function (s) { return [s[0], s[1]]; });
        }
        if (chips.length < 2) { box.hidden = true; box.innerHTML = ''; P.cat = ''; return; }
        box.hidden = false;
        box.innerHTML = '<button type="button" class="cfg-chip' + (P.cat === '' ? ' is-on' : '') + '" data-cat="" aria-pressed="' + (P.cat === '') + '">' + esc('Toutes') + '</button>'
            + chips.map(function (c) {
                return '<button type="button" class="cfg-chip' + (P.cat === c[0] ? ' is-on' : '') + '" data-cat="' + esc(c[0]) + '" aria-pressed="' + (P.cat === c[0]) + '">' + esc(c[1]) + '</button>';
            }).join('');
    }
    /** Dans l'établi, un accessoire de l'onglet va-t-il sur l'arme ? null hors établi. */
    function palFit(it) {
        var t = currentTab();
        if (outfitFiltered(t)) {
            if (!P.forWeapons || it.g === 'grenades' || it.g === 'explosifs') { return null; }
            var short = it.k === 'magazine' ? 'mg' : ATT_SHORT[it.k];
            if (!short) { return null; }
            var set = outfitCompat(S.outfits.a, short);
            if (!set) { return null; }
            return set[it.c.toLowerCase()] ? { ok: true } : { ok: false, reason: 'Ne va sur aucune arme de la tenue A.' };
        }
        var w = benchWeapon();
        if (S.mode !== 'bench' || !t.att || !w) { return null; }
        return attachFits(w.cls, t.att, it.c);
    }
    function filterPalette() {
        var t = currentTab();
        var words = fold(P.q).split(/\s+/).filter(Boolean);
        var list = [];
        var strictOff = S.mode === 'bench' && P.showAll;
        CAT.items.forEach(function (it) {
            if (t.kinds && t.kinds.indexOf(it.k) < 0) { return; }
            if (P.cat && (t.subs ? (subKinds(t, P.cat) || []).indexOf(it.k) < 0 : it.g !== P.cat)) { return; }
            for (var i = 0; i < words.length; i++) { if (it._f.indexOf(words[i]) < 0) { return; } }
            if (P.fav && !isFav(it.c)) { return; }
            var tr = trendOf(it.c);
            if (P.trend && !tr) { return; }
            var fit = palFit(it);
            if (fit && !fit.ok && !strictOff) { return; }
            list.push({ it: it, fit: fit, tr: tr });
        });
        if (P.trend) { list.sort(function (a, b) { return trendScore(b.tr) - trendScore(a.tr); }); }
        if (S.mode === 'bench' && t.att) { list.sort(function (a, b) { return (b.fit && b.fit.ok ? 1 : 0) - (a.fit && a.fit.ok ? 1 : 0); }); }
        P.list = list;
        var count = $('[data-cfg-count]');
        var empty = $('[data-cfg-palette-empty]');
        if (!CAT.ready) {
            count.textContent = 'Chargement du catalogue…';
            empty.hidden = true;
        } else if (!CAT.items.length) {
            count.textContent = 'Catalogue vide.';
            empty.hidden = false;
            empty.innerHTML = baseRunning
                ? '<p><strong>' + esc('Préparation du catalogue de base…') + '</strong></p><p>' + esc('Les armes et l’équipement d’Arma 3 arrivent dans quelques secondes.') + '</p>'
                : '<p><strong>' + esc('Aucun objet dans le catalogue.') + '</strong></p><p>' + esc('Les objets apparaissent dès qu’un membre joue avec COMSPEC Link, ou après l’import du catalogue du jeu.') + '</p>'
                    + (can.catalog ? '<button type="button" class="cfg-btn cfg-btn--primary" data-cfg-open="catalog">' + esc('Catalogue des objets') + '</button>' : '');
        } else if (!list.length) {
            count.textContent = 'Aucun résultat.';
            empty.hidden = false;
            if (P.fav) {
                empty.innerHTML = '<p>' + esc('Aucun favori dans cet onglet. Cliquez sur l’étoile ☆ d’un objet pour le retrouver ici, d’une session à l’autre.') + '</p>';
            } else if (P.trend) {
                empty.innerHTML = '<p>' + esc('Pas encore de tendance dans cet onglet : il faut qu’au moins ' + M.trends.min + ' membres portent, ajoutent ou mettent en favori un même objet en ' + M.trends.days + ' jours.') + '</p>';
            } else if (outfitFiltered(t) && P.forWeapons) {
                empty.innerHTML = '<p>' + esc('Rien ne va sur les armes de la tenue A.') + '</p><button type="button" class="cfg-btn cfg-btn--sm" data-cfg-forweapons-off>' + esc('Montrer tout le catalogue') + '</button>';
            } else {
                empty.innerHTML = S.mode === 'bench' && t.att && !P.showAll && benchWeapon()
                    ? '<p>' + esc('Aucun objet compatible ne correspond.') + '</p><button type="button" class="cfg-btn cfg-btn--sm" data-cfg-showall-on>' + esc('Montrer aussi les incompatibles') + '</button>'
                    : '<p>' + esc('Rien ne correspond. Essayez une partie du nom (ex. « MX », « carryall ») ou la classe Arma.') + '</p>';
            }
        } else {
            empty.hidden = true;
            var ok = list.filter(function (r) { return !r.fit || r.fit.ok; }).length;
            var unchecked = list.some(function (r) { return r.fit && r.fit.unchecked; });
            count.textContent = S.mode === 'bench' && t.att && benchWeapon()
                ? (unchecked ? plural(list.length, 'objet', 'objets') + ' : compatibilités de ' + nameOf(benchWeapon().cls) + ' inconnues, liste non filtrée'
                    : plural(ok, 'objet compatible', 'objets compatibles') + ' avec ' + nameOf(benchWeapon().cls))
                : (outfitFiltered(t) && P.forWeapons && outfitCompat(S.outfits.a, 'mg')
                    ? plural(list.length, 'objet', 'objets') + ' pour les armes de la tenue A'
                    : plural(list.length, 'objet', 'objets'));
        }
        vspacer.style.height = (list.length * P.rowH) + 'px';
        vlist.scrollTop = 0;
        renderRows();
    }
    var rowsFrame = 0;
    function renderRows() {
        rowsFrame = 0;
        var top = vlist.scrollTop;
        var h = vlist.clientHeight || 600;
        var first = Math.max(0, Math.floor(top / P.rowH) - 8);
        var last = Math.min(P.list.length, Math.ceil((top + h) / P.rowH) + 8);
        var html = '';
        for (var i = first; i < last; i++) {
            var r = P.list[i];
            var it = r.it;
            var off = r.fit && !r.fit.ok;
            var meta = [KIND_LABEL[it.k] || ''];
            if (it.m != null) { meta.push(kg(it.m)); }
            if (it.l) { meta.push('contient ' + kg(it.l)); }
            if (it.a) { meta.push(rounds(it.a)); }
            if (r.fit && r.fit.unchecked) { meta.push('compatibilité non vérifiée'); }
            if (r.tr) { meta.push(trendLabel(r.tr)); }
            if (it.o) { meta.push(it.o); }
            var fav = isFav(it.c);
            var starLabel = (fav ? 'Retirer des favoris : ' : 'Mettre en favori : ') + (it.n || it.c);
            html += '<div class="cfg-row' + (off ? ' is-off' : '') + (r.tr ? ' is-trend' : '') + '" role="option" tabindex="-1" style="top:' + (i * P.rowH) + 'px"' + (off ? '' : ' data-dnd="pal:' + it._i + '"') + ' data-pal-index="' + i + '" data-pal-item="' + it._i + '" aria-label="' + esc((it.n || it.c) + ' — ' + (KIND_LABEL[it.k] || '') + (off ? ' — incompatible' : '')) + '">'
                + (off ? '<span class="cfg-grip" aria-hidden="true"></span>' : '<span class="cfg-grip" data-grip aria-hidden="true">⋮⋮</span>') + iconHtml(it.c)
                + '<span class="cfg-row__txt"><span class="cfg-row__name" translate="no">' + esc(it.n || it.c) + '</span>'
                + (off ? '<span class="cfg-row__why">' + esc(r.fit.reason) + '</span>' : '<span class="cfg-row__meta"><span translate="no">' + esc(it.c) + '</span> · ' + esc(meta.join(' · ')) + '</span>') + '</span>'
                + '<button type="button" class="cfg-row__star' + (fav ? ' is-on' : '') + '" data-cfg-star="' + it._i + '" aria-pressed="' + fav + '" aria-label="' + esc(starLabel) + '" title="' + esc(starLabel) + '">' + (fav ? '★' : '☆') + '</button>'
                + (off ? '' : '<button type="button" class="cfg-row__add" data-cfg-quick="' + it._i + '" aria-label="' + esc(quickLabel(it)) + '" title="' + esc(quickLabel(it)) + '">＋</button>') + '</div>';
        }
        $$('.cfg-row', vlist).forEach(function (row) { row.remove(); });
        vspacer.insertAdjacentHTML('afterend', html);
    }
    function quickLabel(it) {
        if (S.mode === 'bench' && ['primary', 'handgun', 'secondary'].indexOf(it.k) >= 0) { return 'Mettre sur l’établi'; }
        if (S.mode === 'bench' && currentTab().att && benchWeapon()) { return 'Monter sur l’arme'; }
        return 'Ajouter à…';
    }
    vlist.addEventListener('scroll', function () { if (!rowsFrame) { rowsFrame = window.requestAnimationFrame(renderRows); } }, { passive: true });
    window.addEventListener('resize', function () { if (!rowsFrame) { rowsFrame = window.requestAnimationFrame(renderRows); } });
    var searchTimer = 0;
    $('[data-cfg-search]').addEventListener('input', function (e) {
        window.clearTimeout(searchTimer);
        var v = e.target.value;
        searchTimer = window.setTimeout(function () { P.q = v; filterPalette(); }, 90);
    });
    $('[data-cfg-showall]').addEventListener('change', function (e) { P.showAll = e.target.checked; filterPalette(); });
    function setTab(id) {
        P.tab[S.mode] = id;
        P.cat = '';
        renderTabs();
        filterPalette();
        renderStage();
    }
    root.addEventListener('click', function (e) {
        var f = e.target.closest('[data-tab]');
        if (f && root.contains(f)) { setTab(f.getAttribute('data-tab')); return; }
        var c = e.target.closest('[data-cat]');
        if (c) { P.cat = c.getAttribute('data-cat'); renderCats(); filterPalette(); return; }
        if (e.target.closest('[data-cfg-showall-on]')) { P.showAll = true; $('[data-cfg-showall]').checked = true; filterPalette(); }
        if (e.target.closest('[data-cfg-forweapons-off]')) { P.forWeapons = false; $('[data-cfg-forweapons]').checked = false; renderTabs(); filterPalette(); }
        var pf = e.target.closest('[data-cfg-pfilter]');
        if (pf) { var k = pf.getAttribute('data-cfg-pfilter'); P[k] = !P[k]; renderTabs(); filterPalette(); return; }
        var star = e.target.closest('[data-cfg-star]');
        if (star) { var si = CAT.items[parseInt(star.getAttribute('data-cfg-star'), 10)]; if (si) { toggleFav(si.c); } }
        var starCls = e.target.closest('[data-cfg-star-cls]');
        if (starCls) { toggleFav(starCls.getAttribute('data-cfg-star-cls')); renderStage(); }
    });
    $('[data-cfg-forweapons]').addEventListener('change', function (e) { P.forWeapons = e.target.checked; renderTabs(); filterPalette(); });
    // Clavier dans la liste : flèches pour parcourir, Entrée pour le menu d'actions.
    vlist.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') { return; }
        e.preventDefault();
        var cur = document.activeElement && document.activeElement.closest('.cfg-row');
        var idx = cur ? parseInt(cur.getAttribute('data-pal-index'), 10) : -1;
        idx = Math.max(0, Math.min(P.list.length - 1, idx + (e.key === 'ArrowDown' ? 1 : -1)));
        if (idx * P.rowH < vlist.scrollTop || (idx + 1) * P.rowH > vlist.scrollTop + vlist.clientHeight) {
            vlist.scrollTop = Math.max(0, idx * P.rowH - vlist.clientHeight / 2);
        }
        renderRows();
        var row = vlist.querySelector('[data-pal-index="' + idx + '"]');
        if (row) { row.focus(); }
    });
    // Survol ou focus d'un accessoire : son emplacement sur l'arme dit s'il le prend, et sinon pourquoi.
    function previewSlot(it) {
        clearPreview();
        var t = currentTab();
        var w = benchWeapon();
        if (S.mode !== 'bench' || !t.att || !w || !it) { return; }
        var att = t.att;
        var slot = $('[data-bench-slot="' + att + '"]');
        if (!slot) { return; }
        var fit = attachFits(w.cls, att, it.c);
        slot.classList.add(fit.ok ? 'is-ok' : 'is-bad');
        var why = slot.querySelector('[data-why]');
        if (why) { why.textContent = fit.ok ? (fit.unchecked ? 'Compatibilité non vérifiée' : 'Compatible') : (fit.reason || ''); }
    }
    function clearPreview() {
        $$('.cfg-bslot.is-ok, .cfg-bslot.is-bad').forEach(function (el) { el.classList.remove('is-ok', 'is-bad'); });
        $$('.cfg-bslot [data-why]').forEach(function (el) { el.textContent = ''; });
    }
    function rowItem(el) {
        var row = el && el.closest && el.closest('.cfg-row');
        return row ? CAT.items[parseInt(row.getAttribute('data-pal-item'), 10)] : null;
    }
    vlist.addEventListener('mouseover', function (e) { if (!D) { previewSlot(rowItem(e.target)); } });
    vlist.addEventListener('mouseleave', function () { if (!D) { clearPreview(); } });
    vlist.addEventListener('focusin', function (e) { previewSlot(rowItem(e.target)); });
    vlist.addEventListener('focusout', function () { if (!D) { clearPreview(); } });

    // ------------------------------------------------------------------ rendu : établi d'arme
    var stage = $('[data-cfg-stage]');
    var summary = $('[data-cfg-summary]');
    function weaponAttrs(o, slot, i) { return o + ':' + slot + ':' + (i == null ? '-' : i); }
    function benchPicks() {
        var a = S.outfits.a;
        var html = WEAPON_SLOTS.map(function (s) {
            var w = a[s[0]];
            var ref = 'a:' + s[0] + ':-';
            return '<button type="button" class="cfg-pick' + (w ? '' : ' is-empty') + '" aria-pressed="' + (S.bench === ref) + '" data-cfg-pick="' + ref + '">'
                + '<small>' + esc(s[1]) + '</small><strong' + (w ? ' translate="no"' : '') + '>' + esc(w ? nameOf(w.cls) : 'vide') + '</strong></button>';
        }).join('');
        CONTAINERS.forEach(function (c) {
            var ct = a[c[0]];
            if (!ct) { return; }
            ct.items.forEach(function (e, i) {
                if (e.t !== 'weapon') { return; }
                var ref = 'a:' + c[0] + ':' + i;
                html += '<button type="button" class="cfg-pick" aria-pressed="' + (S.bench === ref) + '" data-cfg-pick="' + ref + '"><small>' + esc('Rangée · ' + c[1]) + '</small><strong translate="no">' + esc(nameOf(e.w.cls)) + '</strong></button>';
            });
        });
        return '<div class="cfg-picks" role="group" aria-label="Arme sur l’établi">' + html + '</div>';
    }
    function benchSlot(w, att, label) {
        var ref = S.bench.split(':');
        var drop = 'a:' + ref[0] + ':' + ref[1] + ':' + ref[2] + ':' + att;
        var v = att === 'mag' || att === 'mag2' ? (w[att] && w[att].cls) : w[att];
        var t = currentTab();
        var tabAtt = t.att === 'mag' ? 'mag' : t.att;
        var inner = '<span class="cfg-bslot__label"><span>' + esc(label) + '</span>' + (v ? '<span class="cfg-tag cfg-tag--ok">' + esc('posé') + '</span>' : '<span class="cfg-tag">' + esc('vide') + '</span>') + '</span>';
        if (v) {
            inner += '<div class="cfg-piece" data-dnd="' + drop + '" tabindex="0" aria-label="' + esc(label + ' : ' + nameOf(v)) + '"><span class="cfg-grip" data-grip aria-hidden="true">⋮⋮</span>' + iconHtml(v, att === 'mag' || att === 'mag2' ? 'magazine' : att)
                + '<span class="cfg-piece__txt"><span class="cfg-piece__name" translate="no">' + esc(nameOf(v)) + '</span>'
                + '<span class="cfg-piece__meta">' + esc([att === 'mag' || att === 'mag2' ? rounds(w[att].ammo) : '', massOf(v) != null ? kg(massOf(v)) : 'poids ?'].filter(Boolean).join(' · ')) + '</span></span>'
                + '<button type="button" class="cfg-piece__x" data-bench-remove="' + att + '" aria-label="' + esc('Retirer : ' + nameOf(v)) + '">×</button></div>';
        } else {
            inner += '<span class="cfg-bslot__hint">' + esc(ATT_HINT[att] + ' : glissez-le ici') + '</span>';
        }
        inner += '<span class="cfg-bslot__why" data-why></span>';
        return '<div class="cfg-bslot cfg-bslot--' + att + (v ? ' is-set' : '') + (tabAtt === att ? ' is-tab' : '') + '" data-cfg-drop="' + drop + '" data-bench-slot="' + att + '" tabindex="' + (v ? -1 : 0) + '" data-bench-focus="' + att + '" aria-label="' + esc(label + (v ? ' : ' + nameOf(v) : ' : vide')) + '">' + inner + '</div>';
    }
    function benchHtml() {
        var w = benchWeapon();
        var ref = S.bench.split(':');
        var head = '<div class="cfg-stage__head"><h2>' + esc('Établi d’arme') + '</h2>' + benchPicks() + '</div>';
        if (!w) {
            var slot = ref[2] === '-' ? ref[1] : 'primary';
            return '<section class="cfg-panel">' + head + '<div class="cfg-stage__body"><div class="cfg-start" data-cfg-drop="bench">'
                + silhouette('rifle')
                + '<h3>' + esc('Choisissez une arme à gauche') + '</h3>'
                + '<p>' + esc('Onglet « Armes » de la palette : glissez-la ici ou cliquez sur ＋. Ses emplacements (optique, bouche, rail, bipied, chargeurs) apparaissent autour.') + '</p>'
                + '<span class="cfg-start__arrow">' + svgIcon('M19 12H5M11 6l-6 6 6 6') + esc('Palette') + '</span>'
                + '<div class="cfg-row-actions"><button type="button" class="cfg-btn cfg-btn--primary" data-cfg-act="example">' + esc('Essayer sur un exemple') + '</button>'
                + '<button type="button" class="cfg-btn" data-cfg-open="sources">' + esc('Récupérer une tenue') + '</button></div>'
                + '<p class="cfg-muted">' + esc('Emplacement visé : ') + esc(slotLabel(slot)) + '</p>'
                + '</div></div></section>';
        }
        var s = spec(w.cls);
        var m = weaponMass(w);
        var catLabel = s && s.g ? CAT_LABEL[s.g] : (KIND_LABEL[s ? s.k : ''] || '');
        var unknown = !s || !s.x;
        var board = '<div class="cfg-board" role="group" aria-label="' + esc('Arme et ses emplacements') + '">'
            + benchSlot(w, 'muzzle', 'Bouche') + benchSlot(w, 'optic', 'Optique') + benchSlot(w, 'pointer', 'Rail latéral')
            + '<div class="cfg-board__weapon" data-cfg-drop="bench" title="' + esc('Glissez une autre arme ici pour la remplacer') + '">'
            + (s && s.i && urls.icons ? '<img class="cfg-sil" src="' + esc(urls.icons + s.i + '.png') + '" alt="">' : silhouette(silKind(w, ref[1])))
            + '<span class="cfg-board__name" translate="no">' + esc(w.cls) + '</span></div>'
            + benchSlot(w, 'mag2', '2e chargeur / grenade') + benchSlot(w, 'mag', 'Chargeur') + benchSlot(w, 'bipod', 'Bipied / poignée')
            + '</div>';
        var wFav = isFav(w.cls);
        var meta = '<p class="cfg-bench__meta"><span><strong translate="no">' + esc(nameOf(w.cls)) + '</strong>'
            + ' <button type="button" class="cfg-row__star' + (wFav ? ' is-on' : '') + '" data-cfg-star-cls="' + esc(w.cls) + '" aria-pressed="' + wFav + '" aria-label="' + esc(wFav ? 'Retirer l’arme des favoris' : 'Mettre l’arme en favori') + '" title="' + esc(wFav ? 'Retirer l’arme des favoris' : 'Mettre l’arme en favori') + '">' + (wFav ? '★' : '☆') + '</button></span>'
            + (catLabel ? '<span>' + esc(catLabel) + '</span>' : '')
            + '<span>' + esc('Masse montée : ') + '<strong>' + (m == null ? esc('inconnue') : esc(kg(m))) + '</strong></span>'
            + '<span>' + esc(ref[2] === '-' ? OUTFIT_NAME[ref[0]] + ' · ' + slotLabel(ref[1]) : OUTFIT_NAME[ref[0]] + ' · rangée dans ' + slotLabel(ref[1]).toLowerCase()) + '</span></p>';
        return '<section class="cfg-panel">' + head + '<div class="cfg-stage__body">' + board + meta
            + (unknown ? '<p class="cfg-notice">' + esc('Compatibilités de cette arme inconnues : tous les accessoires de la famille sont proposés et rien n’est vérifié.') + '</p>' : '')
            + '<p class="cfg-help">' + esc('Glissez un accessoire de la palette sur son emplacement. Pour retirer : ×, ou glissez-le hors de l’arme. Exemple : onglet Optiques › ACO sur « Optique ».') + '</p>'
            + '</div></section>';
    }

    // ------------------------------------------------------------------ rendu : tenue et sacs
    function selClass(src) { return S.sel && S.sel === src ? ' is-selected' : ''; }
    function weaponLine(o, slot, label) {
        var out = S.outfits[o];
        var w = out[slot];
        var drop = 'w:' + o + ':' + slot;
        if (!w) {
            return '<div class="cfg-wline is-empty" data-cfg-drop="' + drop + '" tabindex="0" aria-label="' + esc(label + ' : vide') + '">'
                + '<svg class="cfg-ico cfg-ico--svg cfg-ico--ghost" viewBox="0 0 24 24" aria-hidden="true"><path d="' + ICONS[slot] + '"/></svg>'
                + '<span><span class="cfg-wline__label">' + esc(label) + '</span>' + esc('Glissez une arme ici') + '</span></div>';
        }
        var parts = [w.optic, w.muzzle, w.pointer, w.bipod].filter(Boolean).map(nameOf);
        var mags = [w.mag, w.mag2].filter(Boolean).length;
        var m = weaponMass(w);
        return '<div class="cfg-wline' + selClass(drop) + '" data-cfg-drop="' + drop + '">'
            + '<div class="cfg-piece" data-dnd="' + drop + '" tabindex="0" aria-label="' + esc(label + ' : ' + nameOf(w.cls)) + '">'
            + '<span class="cfg-grip" data-grip aria-hidden="true">⋮⋮</span>' + iconHtml(w.cls, slot)
            + '<span class="cfg-piece__txt"><span class="cfg-wline__label">' + esc(label) + (m != null ? ' · ' + esc(kg(m)) : '') + '</span>'
            + '<span class="cfg-piece__name" translate="no">' + esc(nameOf(w.cls)) + '</span>'
            + '<span class="cfg-wline__atts"' + (parts.length ? ' translate="no"' : '') + '>' + esc(parts.length ? parts.join(' · ') : 'sans accessoire') + (mags ? esc(' · ' + plural(mags, 'chargeur', 'chargeurs')) : '') + '</span></span></div>'
            + '<button type="button" class="cfg-btn cfg-btn--sm" data-cfg-bench="' + weaponAttrs(o, slot) + '">' + esc('Établi') + '</button>'
            + '<button type="button" class="cfg-wline__menu" data-cfg-menu-for="' + drop + '" aria-label="' + esc('Actions : ' + nameOf(w.cls)) + '">⋯</button></div>';
    }
    function gearTile(o, slot, label) {
        var out = S.outfits[o];
        var drop = (slot === 'binocular' ? 'w:' : 'g:') + o + ':' + slot;
        var v = slot === 'binocular' ? (out.binocular && out.binocular.cls) : out[slot];
        if (!v) {
            return '<div class="cfg-tile is-empty" data-cfg-drop="' + drop + '" tabindex="0" aria-label="' + esc(label + ' : vide') + '">'
                + '<svg class="cfg-ico cfg-ico--svg cfg-ico--ghost" viewBox="0 0 24 24" aria-hidden="true"><path d="' + (ICONS[slot] || ICONS.item) + '"/></svg><span class="cfg-tile__label">' + esc(label) + '</span></div>';
        }
        return '<div class="cfg-tile' + selClass(drop) + '" data-cfg-drop="' + drop + '">'
            + '<div class="cfg-piece cfg-piece--tile" data-dnd="' + drop + '" tabindex="0" aria-label="' + esc(label + ' : ' + nameOf(v)) + '">' + iconHtml(v, slot)
            + '<span class="cfg-tile__label">' + esc(label) + '</span><span class="cfg-tile__name" translate="no">' + esc(nameOf(v)) + '</span></div>'
            + '<button type="button" class="cfg-tile__menu" data-cfg-menu-for="' + drop + '" aria-label="' + esc('Actions : ' + nameOf(v)) + '">⋯</button></div>';
    }
    function loadBar(l, compact) {
        if (l.max == null) {
            return '<div class="cfg-load cfg-load--unk"><span class="cfg-load__bar"></span><span class="cfg-load__txt">' + esc(l.known ? kg(l.load) + ' rangés' : 'poids ?') + ' · ' + esc('capacité inconnue') + '</span></div>';
        }
        var pct = l.known ? Math.min(100, Math.round(l.load / l.max * 100)) : 0;
        var tone = !l.known ? 'unk' : (l.load > l.max + 0.0001 ? 'over' : (pct >= 90 ? 'high' : (pct >= 65 ? 'mid' : 'low')));
        var left = l.known ? Math.max(0, l.max - l.load) : null;
        // Rempli / capacité en kg (la capacité maximumLoad d'Arma est dans la même unité que la masse des objets).
        return '<div class="cfg-load cfg-load--' + tone + '" role="meter" aria-valuemin="0" aria-valuemax="' + l.max + '" aria-valuenow="' + (l.known ? Math.round(l.load) : 0) + '" aria-label="' + esc('Charge : ' + (l.known ? kg(l.load) : '?') + ' sur ' + kg(l.max)) + '">'
            + '<span class="cfg-load__bar"><i style="width:' + pct + '%"></i></span>'
            + '<span class="cfg-load__txt"><strong>' + (l.known ? pct + ' %' : '?') + '</strong>' + (compact ? '' : ' · ' + esc((l.known ? kg(l.load) : '?') + ' / ' + kg(l.max) + (left != null ? ' · reste ' + kg(left) : ''))) + '</span></div>';
    }
    var openContainers = { a: { uniform: true, vest: true, backpack: true }, b: { uniform: true, vest: true, backpack: true } };
    function containerCard(o, slot, label) {
        var out = S.outfits[o];
        var c = out[slot];
        var head = 'c:' + o + ':' + slot;
        if (!c) {
            return '<div class="cfg-box is-empty" data-cfg-drop="' + head + '" tabindex="0" aria-label="' + esc(label + ' : vide') + '">'
                + '<svg class="cfg-ico cfg-ico--svg cfg-ico--ghost" viewBox="0 0 24 24" aria-hidden="true"><path d="' + ICONS[slot] + '"/></svg>'
                + '<span><span class="cfg-wline__label">' + esc(label) + '</span>' + esc('Glissez ' + (slot === 'backpack' ? 'un sac' : (slot === 'vest' ? 'un gilet' : 'un uniforme')) + ' ici') + '</span></div>';
        }
        var l = containerLoad(c);
        var open = openContainers[o][slot] !== false;
        var cells = c.items.map(function (e, i) {
            var cls = e.t === 'weapon' ? e.w.cls : e.cls;
            var src = 'e:' + o + ':' + slot + ':' + i;
            var m = entryMass(e);
            var sub = (e.t === 'mag' ? rounds(e.ammo) + ' · ' : '') + (m == null ? 'masse ?' : kg(m));
            return '<li class="cfg-cell' + selClass(src) + '" data-cfg-drop="cc:' + o + ':' + slot + '">'
                + (e.n > 1 ? '<span class="cfg-cell__n">×' + e.n + '</span>' : '')
                + '<div class="cfg-piece" data-dnd="' + src + '" tabindex="0" aria-label="' + esc(nameOf(cls) + ' × ' + e.n) + '">' + iconHtml(cls)
                + '<span class="cfg-cell__name" translate="no">' + esc(nameOf(cls)) + '</span><span class="cfg-cell__sub">' + esc(sub) + '</span></div>'
                + '<span class="cfg-cell__acts">' + (e.t === 'weapon'
                    ? '<button type="button" class="cfg-btn cfg-btn--sm" data-cfg-bench="' + weaponAttrs(o, slot, i) + '">' + esc('Établi') + '</button>'
                    : '<span class="cfg-qty"><button type="button" data-cfg-qty="' + o + ':' + slot + ':' + i + ':-1" aria-label="' + esc('Un de moins') + '">−</button><button type="button" data-cfg-qty="' + o + ':' + slot + ':' + i + ':1" aria-label="' + esc('Un de plus') + '">+</button></span>')
                + '</span><button type="button" class="cfg-cell__menu" data-cfg-menu-for="' + src + '" aria-label="' + esc('Actions : ' + nameOf(cls)) + '">⋯</button></li>';
        }).join('');
        return '<div class="cfg-box' + (open ? ' is-open' : '') + '">'
            + '<div class="cfg-box__head' + selClass(head) + '" data-cfg-drop="' + head + '">'
            + '<div class="cfg-piece" data-dnd="' + head + '" tabindex="0" aria-label="' + esc(label + ' : ' + nameOf(c.cls)) + '">'
            + '<span class="cfg-grip" data-grip aria-hidden="true">⋮⋮</span>' + iconHtml(c.cls, slot)
            + '<span class="cfg-piece__txt"><span class="cfg-wline__label">' + esc(label) + '</span><span class="cfg-piece__name" translate="no">' + esc(nameOf(c.cls)) + '</span></span></div>'
            + '<span class="cfg-box__mass" title="' + esc('Poids du contenant vide') + '">' + esc(massOf(c.cls) != null ? kg(massOf(c.cls)) : '') + '</span>'
            + '<button type="button" class="cfg-btn cfg-btn--sm" data-cfg-open="prekits" data-cfg-target="' + o + ':' + slot + '" title="' + esc('Insérer un pré-kit dans ' + label.toLowerCase()) + '">' + esc('Pré-kit') + '</button>'
            + '<button type="button" class="cfg-box__toggle" data-cfg-toggle="' + o + ':' + slot + '" aria-expanded="' + open + '">' + esc((open ? 'Replier' : 'Ouvrir') + ' (' + c.items.length + ')') + '</button>'
            + '<button type="button" class="cfg-box__menu" data-cfg-menu-for="' + head + '" aria-label="' + esc('Actions : ' + nameOf(c.cls)) + '">⋯</button></div>'
            + loadBar(l, false)
            + (open ? '<ul class="cfg-cells" data-cfg-drop="cc:' + o + ':' + slot + '" tabindex="0" aria-label="' + esc('Contenu : ' + label) + '">' + (cells || '<li class="cfg-cells__empty">' + esc('Vide : glissez des chargeurs, des objets ou une arme ici.') + '</li>') + '</ul>' : '')
            + '</div>';
    }
    function outfitHtml(o) {
        var out = S.outfits[o];
        var source = out.ref ? ({ w: 'Tenue', k: 'Kit de départ', p: 'Porté en jeu', m: 'Porté en jeu' }[out.ref.charAt(0)] || '') : '';
        var total = outfitMass(out);
        var head = '<header class="cfg-outfit__head">'
            + '<span class="cfg-outfit__tag">' + esc(OUTFIT_NAME[o]) + '</span>'
            + '<label class="cfg-outfit__name"><span class="cfg-sr">' + esc('Nom de la tenue') + '</span><input type="text" maxlength="90" value="' + esc(out.name) + '" placeholder="' + esc('Tenue sans nom') + '" data-cfg-name="' + o + '" translate="no"></label>'
            + (source ? '<span class="cfg-pill">' + esc(source) + '</span>' : '')
            + '<span class="cfg-pill cfg-pill--mass" title="' + esc('Poids total porté') + '">' + esc(total == null ? 'poids incomplet' : kg(total)) + '</span>'
            + '<span class="cfg-outfit__acts">'
            + (o === 'b' ? '<button type="button" class="cfg-btn cfg-btn--sm" data-cfg-act="swap">' + esc('Échanger A ↔ B') + '</button><button type="button" class="cfg-btn cfg-btn--sm cfg-btn--quiet" data-cfg-act="close-b">' + esc('Fermer B') + '</button>' : '')
            + (o === 'a' && /^k:\d+$/.test(out.ref) && can.kits ? '<button type="button" class="cfg-btn cfg-btn--sm" data-cfg-open="versions">' + esc('Historique') + '</button>' : '')
            + '<button type="button" class="cfg-btn cfg-btn--sm cfg-btn--quiet" data-cfg-act="clear-' + o + '">' + esc('Vider') + '</button>'
            + '</span></header>';
        var empty = !outfitClasses(out).length
            ? '<p class="cfg-hint-empty">' + esc('Tenue vide. Glissez une arme, un uniforme ou un sac depuis la palette, ou') + ' <button type="button" class="cfg-link" data-cfg-open="sources" data-cfg-target="' + o + '">' + esc('récupérez une tenue existante') + '</button>.</p>'
            : '';
        return '<section class="cfg-panel cfg-outfit' + (o === 'b' ? ' cfg-outfit--b' : '') + '" data-cfg-outfit="' + o + '" aria-label="' + esc(OUTFIT_NAME[o]) + '">' + head
            + '<div class="cfg-outfit__body">' + empty
            + '<div><h3 class="cfg-section-title">' + esc('Armes') + '</h3><div class="cfg-weapons">' + WEAPON_SLOTS.map(function (s) { return weaponLine(o, s[0], s[1]); }).join('') + '</div></div>'
            + '<div><h3 class="cfg-section-title">' + esc('Équipement porté') + '</h3><div class="cfg-gear">' + GEAR_SLOTS.map(function (s) { return gearTile(o, s[0], s[1]); }).join('') + '</div></div>'
            + '<div><h3 class="cfg-section-title">' + esc('Conteneurs') + ' <span>' + esc('la jauge montre la charge') + '</span></h3><div class="cfg-boxes">' + CONTAINERS.map(function (s) { return containerCard(o, s[0], s[1]); }).join('') + '</div></div>'
            + '</div></section>';
    }
    function outfitsHtml() {
        return '<div class="cfg-outfits' + (S.outfits.b ? ' has-b' : '') + '">' + outfitHtml('a') + (S.outfits.b ? outfitHtml('b') : '') + '</div>';
    }

    // ------------------------------------------------------------------ rendu : résumé
    function alertsHtml(o) {
        var chk = checkOutfit(o);
        var items = chk.errors.map(function (x) { return '<li class="cfg-alert cfg-alert--err">' + esc(x) + '</li>'; })
            .concat(chk.warns.map(function (x) { return '<li class="cfg-alert cfg-alert--warn">' + esc(x) + '</li>'; }));
        if (!items.length) {
            items.push(outfitClasses(o).length
                ? '<li class="cfg-alert cfg-alert--ok">' + esc('Tenue valide : prête à enregistrer.') + '</li>'
                : '<li class="cfg-alert cfg-alert--warn">' + esc('Tenue vide : ajoutez au moins un objet.') + '</li>');
        }
        return '<div><h3 class="cfg-section-title">' + esc('Alertes') + (chk.errors.length ? ' <span>' + esc(plural(chk.errors.length, 'point à corriger', 'points à corriger')) + '</span>' : '') + '</h3><ul class="cfg-alerts">' + items.slice(0, 8).join('') + '</ul></div>';
    }
    function gaugesHtml(o) {
        var rows = CONTAINERS.map(function (c) {
            var ct = o[c[0]];
            if (!ct) { return '<div class="cfg-sum__gauge"><span>' + esc(c[1]) + '</span><span class="cfg-muted">' + esc('aucun') + '</span><span></span></div>'; }
            var l = containerLoad(ct);
            var pct = l.max && l.known ? Math.min(100, Math.round(l.load / l.max * 100)) : null;
            var tone = pct == null ? 'unk' : (l.load > l.max + 0.0001 ? 'over' : (pct >= 90 ? 'high' : (pct >= 65 ? 'mid' : 'low')));
            var detail = (l.known ? kg(l.load) : '?') + (l.max ? ' / ' + kg(l.max) : '');
            return '<div class="cfg-sum__gauge cfg-load--' + tone + '" title="' + esc(c[1] + ' : ' + detail) + '"><span>' + esc(c[1]) + '</span><span class="cfg-load__bar"><i style="width:' + (pct || 0) + '%"></i></span><span class="cfg-load__txt">' + esc(pct == null ? '?' : pct + ' %') + '<small>' + esc(detail) + '</small></span></div>';
        }).join('');
        return '<div><h3 class="cfg-section-title">' + esc('Charge') + '</h3><div class="cfg-sum__gauges">' + rows + '</div></div>';
    }
    function magsInOutfit(o, w) {
        var n = 0;
        if (!w) { return 0; }
        var list = compat(w.cls, 'mg');
        CONTAINERS.forEach(function (c) {
            var ct = o[c[0]];
            if (!ct) { return; }
            ct.items.forEach(function (e) {
                if (e.t === 'mag' && (!list || list[e.cls.toLowerCase()])) { n += e.n; }
            });
        });
        return n;
    }
    function selectionHtml() {
        if (!S.sel || S.mode !== 'outfit') { return ''; }
        var pl = payloadFrom(S.sel);
        if (!pl) { S.sel = ''; return ''; }
        var quick = [];
        var p = S.sel.split(':');
        var otherO = p[1] === 'a' ? 'b' : 'a';
        if (S.outfits[otherO]) {
            var same = p[0] === 'e' ? 'cc:' + otherO + ':' + p[2] : p[0] + ':' + otherO + ':' + p[2];
            if (canDrop(pl, same).ok) { quick.push('<button type="button" class="cfg-btn cfg-btn--sm" data-sel-copy="' + same + '">' + esc('Copier vers la ' + OUTFIT_NAME[otherO].toLowerCase()) + '</button>'); }
        }
        var bag = 'cc:' + p[1] + ':backpack';
        if (!(p[0] === 'e' && p[2] === 'backpack') && canDrop(pl, bag).ok) { quick.push('<button type="button" class="cfg-btn cfg-btn--sm" data-sel-move="' + bag + '">' + esc('Mettre dans le sac') + '</button>'); }
        quick.push('<button type="button" class="cfg-btn cfg-btn--sm" data-cfg-menu-for="' + esc(S.sel) + '">' + esc('Déplacer ou copier vers…') + '</button>');
        quick.push('<button type="button" class="cfg-btn cfg-btn--sm cfg-btn--danger" data-sel-remove>' + esc('Retirer') + '</button>');
        return '<div class="cfg-sel"><h3 class="cfg-section-title">' + esc('Sélection') + '</h3><p class="cfg-sel__name" translate="no">' + esc(payloadName(pl)) + '</p><div class="cfg-row-actions">' + quick.join('') + '</div></div>';
    }
    function summaryHtml() {
        var a = S.outfits.a;
        var m = outfitMass(a);
        var html = '<section class="cfg-panel cfg-sum" aria-labelledby="cfg-sum-title">'
            + '<div><h2 class="cfg-panel__title" id="cfg-sum-title">' + esc('Résumé · Tenue A') + '</h2>'
            + '<p class="cfg-help">' + esc('Masse portée, charge et alertes, recalculées à chaque geste.') + '</p></div>';
        if (S.mode === 'bench') {
            html += '<label class="cfg-field"><span>' + esc('Nom de la tenue') + '</span><input type="text" maxlength="90" value="' + esc(a.name) + '" placeholder="' + esc('Ex. Fusilier MX') + '" data-cfg-name="a" translate="no"></label>';
        }
        html += '<div class="cfg-sum__mass"><strong>' + (m == null ? '?' : esc(kg(m))) + '</strong><span>' + esc(m == null ? 'masse incomplète (objets sans masse connue)' : 'masse totale portée · ' + fmtMass(m) + ' u.') + '</span></div>';
        if (S.mode === 'bench') {
            var w = benchWeapon();
            if (w) {
                html += '<div><h3 class="cfg-section-title">' + esc('Arme sur l’établi') + '</h3><dl>' + ATTACH.map(function (at) {
                    var v = at[0] === 'mag' || at[0] === 'mag2' ? (w[at[0]] && w[at[0]].cls) : w[at[0]];
                    return '<div><dt>' + esc(at[1]) + '</dt><dd class="' + (v ? '' : 'is-none') + '"' + (v ? ' translate="no"' : '') + '>' + esc(v ? nameOf(v) : '—') + '</dd></div>';
                }).join('') + '<div><dt>' + esc('Dans la tenue') + '</dt><dd>' + esc(plural(magsInOutfit(a, w), 'chargeur compatible', 'chargeurs compatibles')) + '</dd></div></dl></div>';
            } else {
                html += '<p class="cfg-muted">' + esc('Aucune arme sur l’établi : choisissez-en une à gauche.') + '</p>';
            }
        } else {
            html += gaugesHtml(a);
        }
        html += alertsHtml(a) + selectionHtml();
        var acts = [];
        if (S.mode === 'bench' && benchWeapon()) {
            var ref = S.bench.split(':');
            acts.push('<button type="button" class="cfg-btn cfg-btn--block" data-bench-copy>' + svgIcon('M8 8h12v12H8zM4 16V4h12') + esc('Copier l’arme…') + '</button>');
            if (ref[2] === '-') {
                acts.push('<button type="button" class="cfg-btn cfg-btn--block" data-bench-stow' + (S.outfits[ref[0]].backpack ? '' : ' disabled title="' + esc('Posez d’abord un sac (vue Tenue et sacs)') + '"') + '>' + svgIcon('M7 6a5 5 0 0 1 10 0v15H7zM9 12h6v5H9z') + esc('Mettre dans le sac') + '</button>');
            }
        }
        if (S.mode === 'outfit' && !S.outfits.b) {
            acts.push('<div class="cfg-sel"><h3 class="cfg-section-title">' + esc('Tenue B') + '</h3><p class="cfg-help">' + esc('Ouvrez une 2e tenue à côté pour y copier une arme, un sac ou un contenu. Exemple : récupérez le kit « Fusilier » en B et glissez son gilet sur A.') + '</p>'
                + '<div class="cfg-row-actions"><button type="button" class="cfg-btn cfg-btn--sm" data-cfg-open="sources" data-cfg-target="b">' + esc('Récupérer une tenue en B') + '</button>'
                + '<button type="button" class="cfg-btn cfg-btn--sm cfg-btn--quiet" data-cfg-act="new-b">' + esc('Tenue B vide') + '</button></div></div>');
        }
        acts.push('<button type="button" class="cfg-btn cfg-btn--primary cfg-btn--block" data-cfg-open="save">' + svgIcon('M5 4h11l3 3v13H5zM8 4v5h8V4M8 20v-6h8v6') + esc('Enregistrer') + '</button>');
        html += '<div class="cfg-sum__acts">' + acts.join('') + '</div></section>';
        return html;
    }

    function renderStage() {
        stage.innerHTML = S.mode === 'bench' ? benchHtml() : outfitsHtml();
    }
    function renderAll() {
        var act = document.activeElement;
        var focusKey = act && (act.getAttribute('data-dnd') || act.getAttribute('data-cfg-drop') || (act.getAttribute('data-cfg-pick') && 'pick:' + act.getAttribute('data-cfg-pick')));
        var nameFocus = act && act.getAttribute('data-cfg-name');
        fixBench();
        $$('[data-cfg-mode]').forEach(function (b) { b.setAttribute('aria-selected', String(b.getAttribute('data-cfg-mode') === S.mode)); });
        $('[data-cfg-work]').classList.toggle('has-b', S.mode === 'outfit' && !!S.outfits.b);
        renderStage();
        summary.innerHTML = summaryHtml();
        if (nameFocus) {
            var n = $('[data-cfg-name="' + nameFocus + '"]');
            if (n) { n.focus(); n.setSelectionRange(n.value.length, n.value.length); }
        } else if (focusKey) {
            var el = focusKey.indexOf('pick:') === 0 ? $('[data-cfg-pick="' + focusKey.slice(5) + '"]') : ($('[data-dnd="' + focusKey + '"]') || $('[data-cfg-drop="' + focusKey + '"]'));
            if (el) { el.focus({ preventScroll: true }); }
        }
        $('[data-cfg-act="undo"]').disabled = !undoStack.length;
        $('[data-cfg-act="redo"]').disabled = !redoStack.length;
    }
    function setMode(mode) {
        if (mode !== 'bench' && mode !== 'outfit') { return; }
        S.mode = mode;
        S.sel = '';
        store('athena.cfg.mode', mode);
        renderTabs();
        filterPalette();
        renderAll();
    }
    root.addEventListener('input', function (e) {
        var o = e.target.getAttribute && e.target.getAttribute('data-cfg-name');
        if (o && S.outfits[o]) {
            S.outfits[o].name = e.target.value.slice(0, 90);
            saveDraft();
        }
    });

    // ------------------------------------------------------------------ charges utiles et dépôts
    /** Charge utile d'une source (palette, pièce posée, ligne de conteneur, accessoire). */
    function payloadFrom(src) {
        var p = String(src).split(':');
        var out;
        if (p[0] === 'pal') {
            var it = CAT.items[parseInt(p[1], 10)];
            return it ? { k: 'cls', cls: it.c, n: 1 } : null;
        }
        out = S.outfits[p[1]];
        if (!out) { return null; }
        if (p[0] === 'w') { return out[p[2]] ? { k: 'weapon', w: clone(out[p[2]]), n: 1, src: src } : null; }
        if (p[0] === 'g') { return out[p[2]] ? { k: 'cls', cls: out[p[2]], n: 1, src: src } : null; }
        if (p[0] === 'c') { return out[p[2]] ? { k: 'container', c: clone(out[p[2]]), src: src } : null; }
        if (p[0] === 'e') {
            var e = out[p[2]] && out[p[2]].items[parseInt(p[3], 10)];
            if (!e) { return null; }
            if (e.t === 'weapon') { return { k: 'weapon', w: clone(e.w), n: e.n, src: src }; }
            if (e.t === 'mag') { return { k: 'mag', cls: e.cls, ammo: e.ammo, n: e.n, src: src }; }
            return { k: 'cls', cls: e.cls, n: e.n, src: src };
        }
        if (p[0] === 'a') {
            var w = weaponAt(p[1] + ':' + p[2] + ':' + p[3]);
            var att = p[4];
            if (!w) { return null; }
            if (att === 'mag' || att === 'mag2') { return w[att] ? { k: 'mag', cls: w[att].cls, ammo: w[att].ammo, n: 1, src: src } : null; }
            return w[att] ? { k: 'cls', cls: w[att], n: 1, src: src } : null;
        }
        return null;
    }
    function payloadName(pl) {
        if (pl.k === 'weapon') { return nameOf(pl.w.cls); }
        if (pl.k === 'container') { return nameOf(pl.c.cls); }
        return nameOf(pl.cls);
    }
    function payloadCls(pl) { return pl.k === 'weapon' ? pl.w.cls : (pl.k === 'container' ? pl.c.cls : pl.cls); }
    /** Ligne de conteneur à créer pour une charge utile. */
    function entryFor(pl) {
        if (pl.k === 'weapon') { return { t: 'weapon', w: clone(pl.w), n: Math.min(pl.n || 1, 20) }; }
        if (pl.k === 'container') { return { t: 'item', cls: pl.c.cls, n: 1 }; }
        var s = spec(pl.cls);
        if (pl.k === 'mag' || (s && s.k === 'magazine')) {
            var a = pl.k === 'mag' ? { ammo: pl.ammo } : ammoFor(pl.cls);
            return { t: 'mag', cls: pl.cls, n: pl.n || 1, ammo: a.ammo, guessed: a.known === false };
        }
        return { t: 'item', cls: pl.cls, n: pl.n || 1 };
    }
    /** Emplacement d'arme de la tenue A pour une arme posée sur l'établi (selon sa famille). */
    function benchSlotFor(pl) {
        var s = spec(payloadCls(pl));
        return s && ['primary', 'handgun', 'secondary'].indexOf(s.k) >= 0 ? s.k : null;
    }
    /** Le dépôt de cette charge utile sur cette cible est-il permis ? {ok, reason, noop} */
    function canDrop(pl, target) {
        if (!pl) { return { ok: false }; }
        var t = String(target).split(':');
        if (t[0] === 'trash' || t[0] === 'pal') { return pl.src ? { ok: true } : { ok: false, noop: true }; }
        if (t[0] === 'bench') {
            if (pl.k !== 'weapon' && pl.k !== 'cls') { return { ok: false, reason: 'Seule une arme se pose sur l’établi.' }; }
            var bs = benchSlotFor(pl);
            if (!bs) { return { ok: false, reason: '« ' + payloadName(pl) + ' » n’est pas une arme : posez-la sur un emplacement de l’arme ou dans la tenue.' }; }
            if (pl.src === 'w:a:' + bs) { return { ok: false, noop: true }; }
            return kindFits(bs, payloadCls(pl));
        }
        var out = S.outfits[t[1]];
        if (!out) { return { ok: false }; }
        if (pl.src && (pl.src === target || (t[0] === 'cc' && pl.src.indexOf('e:' + t[1] + ':' + t[2] + ':') === 0))) { return { ok: false, noop: true }; }
        var cls = payloadCls(pl);
        if (t[0] === 'w') {
            if (pl.k === 'container' || pl.k === 'mag') { return { ok: false, reason: 'Seule une arme va à l’emplacement ' + slotLabel(t[2]) + '.' }; }
            return kindFits(t[2], cls);
        }
        if (t[0] === 'g') {
            if (pl.k !== 'cls') { return { ok: false, reason: 'Cet emplacement reçoit un objet seul (' + slotLabel(t[2]) + ').' }; }
            return kindFits(t[2], cls);
        }
        if (t[0] === 'c') {
            if (pl.k === 'weapon' || pl.k === 'mag') { return { ok: false, reason: 'Glissez-le dans le contenu, pas sur ' + slotLabel(t[2]).toLowerCase() + '.' }; }
            return kindFits(t[2], cls);
        }
        if (t[0] === 'cc') {
            return containerFits(out, t[2], entryFor(pl), false);
        }
        if (t[0] === 'a') {
            var w = weaponAt(t[1] + ':' + t[2] + ':' + t[3]);
            if (!w) { return { ok: false }; }
            if (pl.k === 'weapon' || pl.k === 'container') { return { ok: false, reason: 'Seul un accessoire ou un chargeur se monte sur l’arme.' }; }
            return attachFits(w.cls, t[4], cls);
        }
        return { ok: false };
    }
    /** Retire la source d'un déplacement (n unités d'une ligne, ou tout). */
    function removeSource(src, units) {
        var p = String(src).split(':');
        var out = S.outfits[p[1]];
        if (!out) { return; }
        if (p[0] === 'w' || p[0] === 'c') { out[p[2]] = null; return; }
        if (p[0] === 'g') { out[p[2]] = ''; return; }
        if (p[0] === 'e') {
            var list = out[p[2]] && out[p[2]].items;
            var i = parseInt(p[3], 10);
            if (!list || !list[i]) { return; }
            if (units && list[i].n > units) { list[i].n -= units; } else { list.splice(i, 1); }
            return;
        }
        if (p[0] === 'a') {
            var w = weaponAt(p[1] + ':' + p[2] + ':' + p[3]);
            if (w) { w[p[4]] = (p[4] === 'mag' || p[4] === 'mag2') ? null : ''; }
        }
    }
    /** Ajoute une ligne à un conteneur (même objet = même pile). */
    function addEntry(c, e) {
        if (e.t !== 'weapon' && e.t !== 'pack') {
            for (var i = 0; i < c.items.length; i++) {
                var x = c.items[i];
                if (x.t === e.t && x.cls.toLowerCase() === e.cls.toLowerCase() && (e.t !== 'mag' || x.ammo === e.ammo)) {
                    x.n = Math.min(999, x.n + e.n);
                    return;
                }
            }
        }
        delete e.guessed;
        c.items.push(e);
    }
    function newWeapon(pl) {
        return pl.k === 'weapon' ? clone(pl.w) : { cls: pl.cls, muzzle: '', pointer: '', optic: '', bipod: '', mag: null, mag2: null };
    }
    /** Dépose (copie ou déplacement). Retourne le message à annoncer, ou une chaîne vide. */
    function applyDrop(pl, target, copy) {
        var t = String(target).split(':');
        var msg = '';
        var move = !copy && !!pl.src;
        if (t[0] === 'trash' || t[0] === 'pal') {
            removeSource(pl.src);
            if (S.sel === pl.src) { S.sel = ''; }
            return 'Retiré : ' + payloadName(pl) + ' (Ctrl+Z pour annuler).';
        }
        if (t[0] === 'bench') {
            var bs = benchSlotFor(pl);
            var prev = S.outfits.a[bs];
            if (move) { removeSource(pl.src); }
            var trB = pl.k === 'cls' && prev ? transferWeapon(prev, pl.cls) : null;
            S.outfits.a[bs] = trB ? trB.w : newWeapon(pl);
            S.bench = 'a:' + bs + ':-';
            return (prev ? '« ' + nameOf(prev.cls) + ' » remplacé par « ' + payloadName(pl) + ' ».' + (trB ? transferNote(trB) : '') : payloadName(pl) + ' sur l’établi (' + slotLabel(bs).toLowerCase() + '). Ajoutez maintenant une optique ou un chargeur.');
        }
        var out = S.outfits[t[1]];
        // Cible résolue avant de retirer la source : un index de conteneur peut bouger.
        var weaponTarget = t[0] === 'a' ? weaponAt(t[1] + ':' + t[2] + ':' + t[3]) : null;
        var containerTarget = t[0] === 'cc' ? out[t[2]] : null;
        var mountOne = t[0] === 'a';
        if (move) { removeSource(pl.src, mountOne ? 1 : 0); }
        if (t[0] === 'w') {
            var prevW = out[t[2]];
            var trW = pl.k === 'cls' && prevW && t[2] !== 'binocular' ? transferWeapon(prevW, pl.cls) : null;
            out[t[2]] = trW ? trW.w : newWeapon(pl);
            msg = (prevW ? '« ' + nameOf(prevW.cls) + ' » remplacé par « ' + payloadName(pl) + ' »' : payloadName(pl) + ' posé : ' + slotLabel(t[2])) + '.' + (trW ? transferNote(trW) : '');
        } else if (t[0] === 'g') {
            var prevG = out[t[2]];
            out[t[2]] = pl.cls;
            msg = (prevG ? '« ' + nameOf(prevG) + ' » remplacé par « ' + payloadName(pl) + ' »' : payloadName(pl) + ' posé : ' + slotLabel(t[2])) + '.';
        } else if (t[0] === 'c') {
            var prevC = out[t[2]];
            if (pl.k === 'container') {
                out[t[2]] = clone(pl.c);
            } else {
                // Nouveau conteneur : le contenu de l'ancien est gardé (la jauge dit s'il tient).
                out[t[2]] = { cls: pl.cls, items: prevC ? prevC.items : [] };
            }
            msg = (prevC ? '« ' + nameOf(prevC.cls) + ' » remplacé par « ' + payloadName(pl) + ' »' : payloadName(pl) + ' posé : ' + slotLabel(t[2])) + '.';
        } else if (t[0] === 'cc') {
            var e = entryFor(pl);
            var guessed = e.guessed;
            addEntry(containerTarget, e);
            msg = payloadName(pl) + (e.n > 1 ? ' × ' + e.n : '') + ' rangé : ' + slotLabel(t[2]) + '.' + (guessed ? ' Munitions du chargeur non fournies par le jeu : ' + e.ammo + ' (le mod remplit le chargeur).' : '');
        } else if (t[0] === 'a') {
            var att = t[4];
            if (att === 'mag' || att === 'mag2') {
                var prevM = weaponTarget[att];
                var a = pl.k === 'mag' ? pl.ammo : ammoFor(pl.cls).ammo;
                weaponTarget[att] = { cls: pl.cls, ammo: a };
                msg = (prevM ? '« ' + nameOf(prevM.cls) + ' » remplacé par « ' + payloadName(pl) + ' »' : payloadName(pl) + ' engagé') + '.';
            } else {
                var prevA = weaponTarget[att];
                weaponTarget[att] = pl.cls;
                msg = (prevA ? '« ' + nameOf(prevA) + ' » remplacé par « ' + payloadName(pl) + ' »' : payloadName(pl) + ' monté : ' + slotLabel(att).toLowerCase()) + '.';
            }
        }
        if (move && S.sel === pl.src) { S.sel = ''; }
        return (copy && pl.src ? 'Copie · ' : '') + msg;
    }
    function tryDrop(pl, target, copy) {
        var chk = canDrop(pl, target);
        if (!chk.ok) {
            if (!chk.noop && chk.reason) { toast('Refusé : ' + chk.reason, 'err'); }
            return false;
        }
        var msg = '';
        commit('', function () { msg = applyDrop(pl, target, copy); });
        if (msg) { toast(msg, chk.unchecked || msg.indexOf('Retiré car incompatible') >= 0 ? 'warn' : ''); }
        return true;
    }

    // ------------------------------------------------------------------ glisser-déposer (pointer : souris, stylet, doigt)
    var D = null;
    var ghost = null;
    var trash = $('[data-cfg-drop="trash"]');
    function allTargets() { return $$('[data-cfg-drop]', document); }
    function dropTargetAt(x, y) {
        var el = document.elementFromPoint(x, y);
        return el ? el.closest('[data-cfg-drop]') : null;
    }
    function startDrag() {
        D.pl = payloadFrom(D.src);
        if (!D.pl) { D = null; return; }
        D.started = true;
        clearPreview();
        document.body.classList.add('cfg-dragging');
        if (D.pl.src) { trash.classList.add('is-visible'); }
        ghost = document.createElement('div');
        ghost.className = 'cfg-ghost';
        ghost.setAttribute('aria-hidden', 'true');
        ghost.innerHTML = iconHtml(payloadCls(D.pl)) + '<span><strong translate="no">' + esc(payloadName(D.pl)) + '</strong><em data-ghost-hint>' + esc('Lâchez sur un emplacement') + '</em></span>';
        document.body.appendChild(ghost);
        // Zones de dépôt : celles qui acceptent s'illuminent, celles qui refusent disent pourquoi (emplacements de l'arme).
        allTargets().forEach(function (el) {
            var c = canDrop(D.pl, el.getAttribute('data-cfg-drop'));
            if (c.ok) { el.classList.add('cfg-can'); } else if (!c.noop) { el.classList.add('cfg-cannot'); }
            var why = el.querySelector(':scope > [data-why]');
            if (why) { why.textContent = c.ok ? '' : (c.reason || ''); }
        });
    }
    function moveGhost(x, y, copy) {
        if (!ghost) { return; }
        ghost.style.transform = 'translate(' + (x + 14) + 'px,' + (y + 12) + 'px)';
        ghost.classList.toggle('is-copy', !!copy && !!D.pl.src);
        var over = dropTargetAt(x, y);
        if (D.over && D.over !== over) { D.over.classList.remove('cfg-over'); }
        D.over = over;
        var hint = ghost.querySelector('[data-ghost-hint]');
        if (over) {
            over.classList.add('cfg-over');
            var c = canDrop(D.pl, over.getAttribute('data-cfg-drop'));
            ghost.classList.toggle('is-bad', !c.ok && !c.noop);
            hint.textContent = c.ok ? (copy && D.pl.src ? 'Lâchez pour copier' : 'Lâchez pour poser') : (c.reason || 'Pas ici');
        } else {
            ghost.classList.remove('is-bad');
            hint.textContent = D.pl.src && !copy ? 'Lâchez hors de la tenue pour retirer' : 'Lâchez sur un emplacement';
        }
        autoScroll(x, y);
    }
    function autoScroll(x, y) {
        var edge = 48;
        if (y < edge) { window.scrollBy(0, -14); } else if (y > window.innerHeight - edge) { window.scrollBy(0, 14); }
        var r = vlist.getBoundingClientRect();
        if (x >= r.left && x <= r.right) {
            if (y < r.top + 30 && y > r.top - 10) { vlist.scrollTop -= 18; } else if (y > r.bottom - 30 && y < r.bottom + 10) { vlist.scrollTop += 18; }
        }
    }
    function endDrag(x, y, copy, cancelled) {
        var d = D;
        D = null;
        document.body.classList.remove('cfg-dragging');
        trash.classList.remove('is-visible');
        allTargets().forEach(function (el) { el.classList.remove('cfg-can', 'cfg-cannot', 'cfg-over'); });
        $$('[data-why]').forEach(function (el) { el.textContent = ''; });
        if (ghost) { ghost.remove(); ghost = null; }
        if (!d || !d.started || cancelled) { return; }
        var over = dropTargetAt(x, y);
        if (over) {
            tryDrop(d.pl, over.getAttribute('data-cfg-drop'), copy);
            return;
        }
        // Lâché hors de toute cible : un objet posé est retiré (« drop »), un objet de la palette ne fait rien.
        if (d.pl.src && !copy) { tryDrop(d.pl, 'trash', false); }
    }
    document.addEventListener('pointerdown', function (e) {
        if (e.button !== 0 || D) { return; }
        var h = e.target.closest('[data-dnd]');
        if (!h || !root.contains(h)) { return; }
        var onGrip = !!e.target.closest('[data-grip]');
        if (e.pointerType === 'touch' && !onGrip) { return; }
        if (!onGrip && e.target.closest('button, input, select, textarea, a')) { return; }
        D = { src: h.getAttribute('data-dnd'), x: e.clientX, y: e.clientY, id: e.pointerId, started: false, over: null };
        if (onGrip) { e.preventDefault(); }
    });
    document.addEventListener('pointermove', function (e) {
        if (!D || e.pointerId !== D.id) { return; }
        if (!D.started) {
            if (Math.abs(e.clientX - D.x) + Math.abs(e.clientY - D.y) < 7) { return; }
            startDrag();
            if (!D) { return; }
        }
        e.preventDefault();
        moveGhost(e.clientX, e.clientY, e.ctrlKey || e.altKey || e.metaKey);
    }, { passive: false });
    document.addEventListener('pointerup', function (e) {
        if (!D || e.pointerId !== D.id) { return; }
        var started = D.started;
        var src = D.src;
        endDrag(e.clientX, e.clientY, e.ctrlKey || e.altKey || e.metaKey, false);
        // Simple clic sur un objet posé (vue tenue) : il devient la sélection, avec ses actions dans le résumé.
        if (!started && S.mode === 'outfit' && src.indexOf('pal:') !== 0) {
            S.sel = S.sel === src ? '' : src;
            renderAll();
        }
    });
    document.addEventListener('pointercancel', function () { if (D) { endDrag(0, 0, false, true); } });
    // Glisser natif du navigateur (images, liens) : jamais pendant un glisser du configurateur.
    root.addEventListener('dragstart', function (e) { if (e.target.closest('[data-dnd]')) { e.preventDefault(); } });

    // ------------------------------------------------------------------ menu d'actions (alternative au glisser)
    var menu = $('[data-cfg-menu]');
    var menuFor = null;
    /** Toutes les cibles possibles, avec leur libellé (menus « Ajouter à… », « Déplacer vers… »). */
    function targetList() {
        var list = [];
        ['a', 'b'].forEach(function (o) {
            var out = S.outfits[o];
            if (!out) { return; }
            var pre = OUTFIT_NAME[o] + ' · ';
            WEAPON_SLOTS.forEach(function (s) { list.push(['w:' + o + ':' + s[0], pre + s[1]]); });
            GEAR_SLOTS.forEach(function (s) { list.push([(s[0] === 'binocular' ? 'w:' : 'g:') + o + ':' + s[0], pre + s[1]]); });
            CONTAINERS.forEach(function (s) {
                list.push(['c:' + o + ':' + s[0], pre + s[1]]);
                if (out[s[0]]) { list.push(['cc:' + o + ':' + s[0], pre + 'dans ' + s[1].toLowerCase()]); }
            });
            WEAPON_SLOTS.forEach(function (s) {
                if (!out[s[0]]) { return; }
                ATTACH.forEach(function (a) { list.push(['a:' + o + ':' + s[0] + ':-:' + a[0], pre + s[1] + ' › ' + a[1]]); });
            });
        });
        return list;
    }
    function openMenu(src, anchor) {
        var pl = payloadFrom(src);
        if (!pl) { return; }
        menuFor = { src: src, anchor: anchor };
        var valid = targetList().filter(function (t) { return canDrop(pl, t[0]).ok; });
        var html = '<p class="cfg-menu__title" translate="no">' + esc(payloadName(pl)) + '</p>';
        if (pl.k === 'weapon' && pl.src) {
            var wp = pl.src.split(':');
            html += '<button type="button" role="menuitem" data-menu-bench="' + (wp[0] === 'w' ? wp[1] + ':' + wp[2] + ':-' : wp[1] + ':' + wp[2] + ':' + wp[3]) + '">' + esc('Ouvrir l’établi') + '</button>';
        }
        if (!valid.length) {
            html += '<p class="cfg-menu__none">' + esc('Aucun emplacement libre ne l’accepte' + (pl.k === 'cls' && !spec(pl.cls) ? ' (objet absent du catalogue)' : '') + '.') + '</p>';
        } else if (pl.src) {
            html += '<p class="cfg-menu__group">' + esc('Déplacer vers') + '</p>' + valid.slice(0, 24).map(function (t) { return '<button type="button" role="menuitem" data-menu-drop="' + esc(t[0]) + '">' + esc(t[1]) + '</button>'; }).join('');
            html += '<p class="cfg-menu__group">' + esc('Copier vers') + '</p>' + valid.slice(0, 24).map(function (t) { return '<button type="button" role="menuitem" data-menu-copy="' + esc(t[0]) + '">' + esc(t[1]) + '</button>'; }).join('');
        } else {
            html += '<p class="cfg-menu__group">' + esc('Ajouter à') + '</p>' + valid.slice(0, 30).map(function (t) { return '<button type="button" role="menuitem" data-menu-copy="' + esc(t[0]) + '">' + esc(t[1]) + '</button>'; }).join('');
        }
        if (pl.src) { html += '<button type="button" role="menuitem" class="cfg-menu__danger" data-menu-remove>' + esc('Retirer') + '</button>'; }
        html += '<button type="button" role="menuitem" data-menu-close>' + esc('Fermer') + '</button>';
        menu.innerHTML = html;
        menu.hidden = false;
        var r = (anchor || document.activeElement || root).getBoundingClientRect();
        var top = Math.min(window.innerHeight - 20, r.bottom + 4);
        menu.style.left = Math.max(8, Math.min(window.innerWidth - 328, r.left)) + 'px';
        menu.style.top = top + 'px';
        menu.style.maxHeight = Math.max(180, window.innerHeight - top - 16) + 'px';
        var first = menu.querySelector('button');
        if (first) { first.focus(); }
    }
    function closeMenu(restore) {
        if (menu.hidden) { return; }
        menu.hidden = true;
        if (restore && menuFor && menuFor.anchor && document.body.contains(menuFor.anchor)) { menuFor.anchor.focus(); }
        menuFor = null;
    }
    document.addEventListener('click', function (e) {
        var m = e.target.closest('[data-cfg-menu]');
        if (m) {
            var b = e.target.closest('button');
            if (!b || !menuFor) { return; }
            var pl = payloadFrom(menuFor.src);
            if (b.hasAttribute('data-menu-drop')) { closeMenu(false); tryDrop(pl, b.getAttribute('data-menu-drop'), false); return; }
            if (b.hasAttribute('data-menu-copy')) { closeMenu(false); tryDrop(pl, b.getAttribute('data-menu-copy'), true); return; }
            if (b.hasAttribute('data-menu-remove')) { closeMenu(false); tryDrop(pl, 'trash', false); return; }
            if (b.hasAttribute('data-menu-bench')) { closeMenu(false); openBench(b.getAttribute('data-menu-bench')); return; }
            closeMenu(true);
            return;
        }
        if (!menu.hidden) { closeMenu(false); }
        var mf = e.target.closest('[data-cfg-menu-for]');
        if (mf) { e.stopPropagation(); openMenu(mf.getAttribute('data-cfg-menu-for'), mf); }
    }, true);
    menu.addEventListener('keydown', function (e) {
        var items = $$('button', menu);
        var i = items.indexOf(document.activeElement);
        if (e.key === 'ArrowDown') { e.preventDefault(); (items[i + 1] || items[0]).focus(); }
        if (e.key === 'ArrowUp') { e.preventDefault(); (items[i - 1] || items[items.length - 1]).focus(); }
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); closeMenu(true); }
    });

    // ------------------------------------------------------------------ établi : ouverture et actions
    function openBench(ref) {
        if (!weaponAt(ref)) { return; }
        S.bench = ref;
        if (S.mode !== 'bench') { setMode('bench'); } else { renderTabs(); filterPalette(); renderAll(); }
        if (!currentTab().att) { setTab('optic'); }
        var pick = $('[data-cfg-pick="' + ref + '"]');
        if (pick) { pick.focus({ preventScroll: false }); }
    }
    /** « ＋ » de la palette : arme sur l'établi, accessoire monté sur l'arme, sinon menu « Ajouter à… ». */
    function quickAdd(it, anchor) {
        var pl = { k: 'cls', cls: it.c, n: 1 };
        if (S.mode === 'bench' && ['primary', 'handgun', 'secondary'].indexOf(it.k) >= 0) { tryDrop(pl, 'bench', true); return; }
        var t = currentTab();
        var w = benchWeapon();
        if (S.mode === 'bench' && t.att && w) {
            var ref = S.bench.split(':');
            var att = t.att;
            if (att === 'mag' && w.mag && !w.mag2 && !attachFits(w.cls, 'mag', it.c).ok) { att = 'mag2'; }
            tryDrop(pl, 'a:' + ref[0] + ':' + ref[1] + ':' + ref[2] + ':' + att, true);
            return;
        }
        openMenu('pal:' + it._i, anchor);
    }
    var EXAMPLE = { weapon: ['arifle_MX_F', 'arifle_MXC_F', 'arifle_TRG21_F', 'arifle_Katiba_F'], mag: ['30Rnd_65x39_caseless_mag', '30Rnd_556x45_Stanag', '30Rnd_65x39_caseless_green'] };
    function loadExample() {
        if (!CAT.ready) { toast('Le catalogue se charge encore : réessayez dans un instant.', 'warn'); return; }
        var cls = '';
        EXAMPLE.weapon.forEach(function (c) { if (!cls && spec(c) && spec(c).k === 'primary') { cls = spec(c).c; } });
        if (!cls) {
            for (var i = 0; i < CAT.items.length; i++) { if (CAT.items[i].k === 'primary') { cls = CAT.items[i].c; break; } }
        }
        if (!cls) { toast('Aucune arme dans le catalogue pour l’exemple.', 'err'); return; }
        var a = S.outfits.a;
        if (a.primary && !window.confirm('Remplacer l’arme principale de la tenue A par l’exemple ? (Ctrl+Z pour revenir)')) { return; }
        var mags = compat(cls, 'mg');
        var mag = null;
        EXAMPLE.mag.forEach(function (m) { if (!mag && spec(m) && (!mags || mags[m.toLowerCase()])) { mag = spec(m).c; } });
        commit('Exemple chargé.', function () {
            S.outfits.a.primary = { cls: cls, muzzle: '', pointer: '', optic: '', bipod: '', mag: mag ? { cls: mag, ammo: ammoFor(mag).ammo } : null, mag2: null };
            if (!S.outfits.a.name) { S.outfits.a.name = 'Exemple : fusilier'; }
        });
        S.bench = 'a:primary:-';
        S.mode = 'bench';
        store('athena.cfg.mode', 'bench');
        P.tab.bench = 'optic';
        P.q = '';
        $('[data-cfg-search]').value = '';
        closeTour();
        renderTabs();
        filterPalette();
        renderAll();
        toast('Exemple chargé : glissez une optique de la palette sur l’emplacement « Optique ».');
    }

    // ------------------------------------------------------------------ boutons
    root.addEventListener('click', function (e) {
        var b = e.target.closest('button');
        if (!b || !root.contains(b)) { return; }
        var more = b.closest('[data-cfg-more]');
        if (more && b.closest('.cfg-more__list')) { more.open = false; }
        var q = b.getAttribute('data-cfg-qty');
        if (q) {
            var p = q.split(':');
            commit('', function () {
                var c = S.outfits[p[0]][p[1]];
                var en = c && c.items[parseInt(p[2], 10)];
                if (!en) { return false; }
                var n = en.n + parseInt(p[3], 10);
                if (n < 1) { c.items.splice(parseInt(p[2], 10), 1); S.sel = ''; return true; }
                if (n > 999) { return false; }
                if (parseInt(p[3], 10) > 0) {
                    var chk = containerFits(S.outfits[p[0]], p[1], { t: en.t, cls: en.cls, n: 1, w: en.w }, false);
                    if (!chk.ok) { toast('Refusé : ' + chk.reason, 'err'); return false; }
                }
                en.n = n;
                return true;
            });
            return;
        }
        var tg = b.getAttribute('data-cfg-toggle');
        if (tg) {
            var tp = tg.split(':');
            openContainers[tp[0]][tp[1]] = !(openContainers[tp[0]][tp[1]] !== false);
            renderAll();
            return;
        }
        var quick = b.getAttribute('data-cfg-quick');
        if (quick !== null) { var it = CAT.items[parseInt(quick, 10)]; if (it) { quickAdd(it, b); } return; }
        var pick = b.getAttribute('data-cfg-pick');
        if (pick) { S.bench = pick; renderTabs(); filterPalette(); renderAll(); return; }
        var bench = b.getAttribute('data-cfg-bench');
        if (bench) { openBench(bench); return; }
        var mode = b.getAttribute('data-cfg-mode');
        if (mode) { setMode(mode); return; }
        if (b.hasAttribute('data-bench-remove')) {
            var r = S.bench.split(':');
            tryDrop(payloadFrom('a:' + r[0] + ':' + r[1] + ':' + r[2] + ':' + b.getAttribute('data-bench-remove')), 'trash', false);
            return;
        }
        if (b.hasAttribute('data-bench-copy')) {
            var rc = S.bench.split(':');
            openMenu(rc[2] === '-' ? 'w:' + rc[0] + ':' + rc[1] : 'e:' + rc[0] + ':' + rc[1] + ':' + rc[2], b);
            return;
        }
        if (b.hasAttribute('data-bench-stow')) {
            var rs = S.bench.split(':');
            tryDrop(payloadFrom('w:' + rs[0] + ':' + rs[1]), 'cc:' + rs[0] + ':backpack', false);
            return;
        }
        if (b.hasAttribute('data-sel-copy')) { tryDrop(payloadFrom(S.sel), b.getAttribute('data-sel-copy'), true); return; }
        if (b.hasAttribute('data-sel-move')) { tryDrop(payloadFrom(S.sel), b.getAttribute('data-sel-move'), false); return; }
        if (b.hasAttribute('data-sel-remove')) { tryDrop(payloadFrom(S.sel), 'trash', false); return; }
        if (b.hasAttribute('data-cfg-base-reload')) { reloadBase(); return; }
        var act = b.getAttribute('data-cfg-act');
        if (act === 'undo') { undo(); }
        if (act === 'redo') { redo(); }
        if (act === 'help') { openTour(0); }
        if (act === 'example') { loadExample(); }
        if (act === 'new') { commit('Nouvelle tenue.', function () { S.outfits.a = blankOutfit(); }); toast('Nouvelle tenue (Ctrl+Z pour revenir).'); }
        if (act === 'new-b') { commit('Tenue B ouverte.', function () { S.outfits.b = blankOutfit(); }); }
        if (act === 'close-b') { commit('Tenue B fermée.', function () { S.outfits.b = null; }); }
        if (act === 'clear-a' || act === 'clear-b') {
            var o = act.slice(-1);
            commit('Tenue vidée.', function () { var keep = S.outfits[o]; S.outfits[o] = blankOutfit(); S.outfits[o].name = keep.name; S.outfits[o].ref = keep.ref; S.outfits[o].meta = keep.meta; });
            toast('Tenue vidée (Ctrl+Z pour annuler).');
        }
        if (act === 'swap') { commit('Tenues A et B échangées.', function () { var a = S.outfits.a; S.outfits.a = S.outfits.b; S.outfits.b = a; }); }
        if (act === 'theme') { cycleTheme(); }
        var open = b.getAttribute('data-cfg-open');
        if (open) { openDialog(open, b.getAttribute('data-cfg-target') || 'a'); }
    });
    // Clic sur un emplacement vide de l'établi : l'onglet correspondant de la palette s'ouvre.
    root.addEventListener('click', function (e) {
        var slot = e.target.closest('[data-bench-focus]');
        if (!slot || e.target.closest('button, .cfg-piece')) { return; }
        var att = slot.getAttribute('data-bench-focus');
        setTab(att === 'mag2' ? 'mag' : att);
    });
    document.addEventListener('click', function (e) {
        var more = $('[data-cfg-more]');
        if (more && more.open && !more.contains(e.target)) { more.open = false; }
    });

    // ------------------------------------------------------------------ clavier
    var clip = null;
    document.addEventListener('keydown', function (e) {
        var typing = e.target.closest && e.target.closest('input, textarea, select');
        var mod = e.ctrlKey || e.metaKey;
        if (e.key === 'Escape' && D) { endDrag(0, 0, false, true); return; }
        if (e.key === 'Escape' && S.sel && !document.querySelector('dialog[open]')) { S.sel = ''; renderAll(); return; }
        if (mod && !e.shiftKey && (e.key === 'z' || e.key === 'Z') && !typing) { e.preventDefault(); undo(); return; }
        if (mod && ((e.key === 'y' || e.key === 'Y') || (e.shiftKey && (e.key === 'z' || e.key === 'Z'))) && !typing) { e.preventDefault(); redo(); return; }
        if (mod && (e.key === 's' || e.key === 'S')) { e.preventDefault(); openDialog('save', 'a'); return; }
        if (typing) { return; }
        var focus = document.activeElement;
        var src = focus && focus.getAttribute && focus.getAttribute('data-dnd');
        if (e.key === '/' && !mod) { e.preventDefault(); $('[data-cfg-search]').focus(); return; }
        if ((e.key === 'Enter' || e.key === ' ') && src && !e.target.closest('button')) { e.preventDefault(); openMenu(src, focus); return; }
        if ((e.key === 'Enter' || e.key === ' ') && focus && focus.hasAttribute && focus.hasAttribute('data-bench-focus') && !src) {
            e.preventDefault();
            var att = focus.getAttribute('data-bench-focus');
            setTab(att === 'mag2' ? 'mag' : att);
            return;
        }
        if ((e.key === 'Delete' || e.key === 'Backspace') && src && src.indexOf('pal:') !== 0) {
            e.preventDefault();
            tryDrop(payloadFrom(src), 'trash', false);
            return;
        }
        if (mod && (e.key === 'c' || e.key === 'C') && src) {
            var pl = payloadFrom(src);
            if (pl) { clip = clone(pl); delete clip.src; toast('Copié : ' + payloadName(pl) + '. Sélectionnez un emplacement puis Ctrl+V.'); }
            return;
        }
        if (mod && (e.key === 'v' || e.key === 'V') && clip) {
            var tgt = focus && focus.closest && focus.closest('[data-cfg-drop]');
            if (tgt) { e.preventDefault(); tryDrop(clone(clip), tgt.getAttribute('data-cfg-drop'), true); }
        }
    });

    // ------------------------------------------------------------------ guide (4 étapes)
    var tour = document.getElementById('cfg-tour');
    var tourStep = 0;
    var TOUR_KEY = 'athena.cfg.tour.v2.' + String(boot.user || '0');
    function renderTour() {
        var steps = $$('[data-step]', tour);
        steps.forEach(function (s, i) { s.classList.toggle('is-current', i === tourStep); s.setAttribute('aria-current', i === tourStep ? 'step' : 'false'); });
        tour.querySelector('[data-cfg-tour-dots]').innerHTML = steps.map(function (s, i) {
            return '<button type="button" aria-label="' + esc('Étape ' + (i + 1)) + '"' + (i === tourStep ? ' aria-current="step"' : '') + ' data-tour-go="' + i + '"></button>';
        }).join('');
        tour.querySelector('[data-cfg-tour-prev]').disabled = tourStep === 0;
        var next = tour.querySelector('[data-cfg-tour-next]');
        next.textContent = tourStep >= steps.length - 1 ? 'Terminer' : 'Suivant';
    }
    function openTour(step) {
        tourStep = step || 0;
        renderTour();
        if (!tour.open) { tour.showModal(); }
        var next = tour.querySelector('[data-cfg-tour-next]');
        if (next) { next.focus(); }
    }
    function closeTour() {
        store(TOUR_KEY, '1');
        if (tour.open) { tour.close(); }
    }
    tour.addEventListener('close', function () { store(TOUR_KEY, '1'); });
    tour.addEventListener('click', function (e) {
        var go = e.target.closest('[data-tour-go]');
        if (go) { tourStep = parseInt(go.getAttribute('data-tour-go'), 10) || 0; renderTour(); return; }
        if (e.target.closest('[data-cfg-tour-prev]')) { tourStep = Math.max(0, tourStep - 1); renderTour(); return; }
        if (e.target.closest('[data-cfg-tour-next]')) {
            if (tourStep >= $$('[data-step]', tour).length - 1) { closeTour(); return; }
            tourStep++;
            renderTour();
        }
        // « Essayer sur un exemple » : traité par le gestionnaire des boutons de la page (le guide est dans la racine).
    });
    tour.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight') { tourStep = Math.min($$('[data-step]', tour).length - 1, tourStep + 1); renderTour(); }
        if (e.key === 'ArrowLeft') { tourStep = Math.max(0, tourStep - 1); renderTour(); }
    });

    // ------------------------------------------------------------------ fenêtres
    function dlg(id) { return document.getElementById(id); }
    function openDialog(name, target) {
        closeMenu(false);
        // Une seule fenêtre à la fois (ex. « Coller une tenue » depuis « Récupérer une tenue »).
        $$('dialog.cfg-dialog[open]', document).forEach(function (d) { d.close(); });
        if (name === 'sources') { openSources(target || 'a'); return; }
        if (name === 'prekits') { openPrekits(target || ''); return; }
        if (name === 'import') { dlg('cfg-import').showModal(); dlg('cfg-import').querySelector('textarea').focus(); return; }
        if (name === 'export') { renderExport(); dlg('cfg-export').showModal(); return; }
        if (name === 'save') { prepareSave(); dlg('cfg-save').showModal(); return; }
        if (name === 'versions') { openVersions(); return; }
        if (name === 'catalog') { renderCatalogInfo(); dlg('cfg-catalog').showModal(); }
    }
    document.addEventListener('click', function (e) {
        var c = e.target.closest('[data-cfg-close]');
        if (c) { var d = c.closest('dialog'); if (d) { d.close(); } }
    });
    $$('dialog.cfg-dialog', document).forEach(function (d) {
        d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
    });

    // ------------------------------------------------------------------ ranger plusieurs objets d'un coup (pré-kits, fusion)
    var CONTAINER_WORD = { uniform: 'l’uniforme', vest: 'le gilet', backpack: 'le sac' };
    /**
     * Range des lignes dans un contenant de la tenue, unité par unité tant qu'il y a la place (mêmes règles que
     * containerFits ; même plan que PrekitDocument::plan côté serveur). dry = true : aperçu, la tenue ne bouge pas.
     * Retour : {added: unités rangées, skipped: [{cls, n, reason}], unchecked, error}.
     */
    function insertEntries(o, slot, entries, dry) {
        var target = dry ? clone(o) : o;
        var res = { added: 0, skipped: [], unchecked: false, error: '' };
        if (!target[slot]) { res.error = 'Posez d’abord ' + ({ uniform: 'un uniforme', vest: 'un gilet', backpack: 'un sac' }[slot]) + ' pour y ranger des objets.'; return res; }
        entries.forEach(function (e0) {
            var want = Math.max(1, e0.n || 1);
            var unit = clone(e0);
            unit.n = 1;
            var put = 0;
            var reason = '';
            for (var k = 0; k < want; k++) {
                var chk = containerFits(target, slot, unit, false);
                if (!chk.ok) { reason = chk.reason; break; }
                if (chk.unchecked) { res.unchecked = true; }
                addEntry(target[slot], clone(unit));
                put++;
            }
            res.added += put;
            if (put < want) {
                var cls = e0.t === 'weapon' ? e0.w.cls : e0.cls;
                res.skipped.push({ cls: cls, n: want - put, reason: reason || ('Plus de place dans ' + CONTAINER_WORD[slot] + '.') });
            }
        });
        return res;
    }
    function skippedText(list) {
        return list.map(function (s) { return nameOf(s.cls) + (s.n > 1 ? ' × ' + s.n : ''); }).join(', ');
    }

    // ------------------------------------------------------------------ Récupérer une tenue
    var sourcesCache = null;
    var sourcesTarget = 'a';
    var SRC = { tab: 'all', sel: '', sides: {}, cache: {}, parts: null, how: 'replace' };
    var SRC_TABS = [['all', 'Tout'], ['mine', 'Mes tenues'], ['fav', 'Favoris'], ['kits', 'Kits de départ'], ['models', 'Modèles'], ['community', 'Communauté'], ['game', 'Porté en jeu'], ['trend', 'Tendances']];
    var GROUP_LABEL = { mine: 'Ma tenue', kits: 'Kit de départ', models: 'Modèle de la communauté', community: 'Tenue de la communauté', game: 'Porté en jeu' };
    var PARTS = [
        ['weapons', 'Armes et accessoires', ['primary', 'handgun', 'secondary']],
        ['uniform', 'Uniforme et son contenu', ['uniform']],
        ['vest', 'Gilet et son contenu', ['vest']],
        ['backpack', 'Sac et son contenu', ['backpack']],
        ['head', 'Casque et lunettes', ['headgear', 'goggles']],
        ['items', 'Jumelles, carte, radio, GPS, boussole, montre, JVN', ['binocular', 'map', 'gps', 'radio', 'compass', 'watch', 'nvg']]
    ];
    function srcDlg() { return dlg('cfg-sources'); }
    function openSources(target) {
        sourcesTarget = target === 'b' ? 'b' : 'a';
        SRC.how = sourcesTarget === 'b' ? 'b' : 'replace';
        var d = srcDlg();
        d.showModal();
        var q = d.querySelector('[data-cfg-sources-q]');
        q.value = '';
        q.focus();
        if (sourcesCache) { renderSources(); renderSourcePreview(); return; }
        d.querySelector('[data-cfg-sources-list]').innerHTML = '<p class="cfg-muted">' + esc('Chargement…') + '</p>';
        request(urls.sources).then(function (j) {
            if (!j.ok) { d.querySelector('[data-cfg-sources-list]').innerHTML = '<p class="cfg-flash cfg-flash--err">' + esc(j.error || 'Liste indisponible.') + '</p>'; return; }
            sourcesCache = j.sources || [];
            SRC.sides = j.sides || {};
            var trendRefs = {};
            (M.trends.wardrobes || []).forEach(function (t) { trendRefs[t.ref] = t.members; });
            sourcesCache.forEach(function (s) { if (trendRefs[s.ref]) { s.trend = trendRefs[s.ref]; } });
            fillSourceFilters();
            renderSources();
            renderSourcePreview();
        });
    }
    function sourceGroupKey(s) { return s.collection || s.role || ''; }
    function fillSourceFilters() {
        var d = srcDlg();
        var sides = {};
        var groups = {};
        sourcesCache.forEach(function (s) { if (s.side) { sides[s.side] = true; } if (sourceGroupKey(s)) { groups[sourceGroupKey(s)] = true; } });
        var sideSel = d.querySelector('[data-cfg-sources-side]');
        sideSel.innerHTML = '<option value="">' + esc('Tous les camps') + '</option>' + Object.keys(SRC.sides).filter(function (k) { return sides[k]; }).map(function (k) {
            return '<option value="' + esc(k) + '">' + esc(SRC.sides[k]) + '</option>';
        }).join('');
        sideSel.closest('label').hidden = !Object.keys(sides).length;
        var grpSel = d.querySelector('[data-cfg-sources-group]');
        grpSel.innerHTML = '<option value="">' + esc('Tous') + '</option>' + Object.keys(groups).sort().map(function (g) { return '<option value="' + esc(g) + '" translate="no">' + esc(g) + '</option>'; }).join('');
        grpSel.closest('label').hidden = !Object.keys(groups).length;
    }
    function sourceMatches(s, tab) {
        if (tab === 'all') { return true; }
        if (tab === 'fav') { return !!s.fav; }
        if (tab === 'trend') { return !!s.trend; }
        return s.group === tab;
    }
    function renderSources() {
        var d = srcDlg();
        var words = fold(d.querySelector('[data-cfg-sources-q]').value).split(/\s+/).filter(Boolean);
        var side = d.querySelector('[data-cfg-sources-side]').value;
        var grp = d.querySelector('[data-cfg-sources-group]').value;
        var base = sourcesCache.filter(function (s) {
            var hay = fold(s.name + ' ' + (s.owner || '') + ' ' + (s.role || '') + ' ' + (s.collection || ''));
            return words.every(function (w) { return hay.indexOf(w) >= 0; }) && (!side || s.side === side) && (!grp || sourceGroupKey(s) === grp);
        });
        d.querySelector('[data-cfg-sources-tabs]').innerHTML = SRC_TABS.filter(function (t) {
            return t[0] === 'all' || t[0] === SRC.tab || sourcesCache.some(function (s) { return sourceMatches(s, t[0]); });
        }).map(function (t) {
            var n = base.filter(function (s) { return sourceMatches(s, t[0]); }).length;
            return '<button type="button" class="cfg-tab" role="tab" aria-selected="' + (SRC.tab === t[0]) + '" data-src-tab="' + t[0] + '">' + esc(t[1]) + ' <span>' + n + '</span></button>';
        }).join('');
        var list = base.filter(function (s) { return sourceMatches(s, SRC.tab); });
        if (SRC.tab === 'trend') { list.sort(function (a, b) { return (b.trend || 0) - (a.trend || 0); }); }
        var html = list.slice(0, 250).map(function (s) {
            var meta = [GROUP_LABEL[s.group] || '', s.owner, s.collection && s.collection !== s.owner ? s.collection : '', s.side && SRC.sides[s.side] ? SRC.sides[s.side] : ''].filter(Boolean);
            var star = /^w:/.test(s.ref)
                ? '<button type="button" class="cfg-row__star' + (s.fav ? ' is-on' : '') + '" data-src-fav="' + esc(s.ref) + '" aria-pressed="' + !!s.fav + '" aria-label="' + esc((s.fav ? 'Retirer des favoris : ' : 'Mettre en favori : ') + s.name) + '">' + (s.fav ? '★' : '☆') + '</button>'
                : '';
            return '<li class="cfg-src' + (SRC.sel === s.ref ? ' is-selected' : '') + '"><button type="button" class="cfg-src__pick" data-src-pick="' + esc(s.ref) + '" aria-pressed="' + (SRC.sel === s.ref) + '">'
                + '<strong translate="no">' + esc(s.name) + '</strong><em translate="no">' + esc(meta.join(' · ')) + '</em>'
                + (s.trend ? '<small>' + esc('Favori de ' + s.trend + ' membres (' + M.trends.days + ' j)') + '</small>' : '') + '</button>' + star + '</li>';
        }).join('');
        d.querySelector('[data-cfg-sources-list]').innerHTML = html ? '<ul class="cfg-src-list">' + html + '</ul>' : (sourcesCache.length
            ? '<p class="cfg-muted">' + esc(SRC.tab === 'fav' ? 'Aucune tenue favorite : cliquez sur l’étoile ☆ d’une tenue.' : 'Aucune tenue ne correspond à la recherche.') + '</p>'
            : '<div class="cfg-empty"><p><strong>' + esc('Aucune tenue à récupérer pour l’instant.') + '</strong></p><p>' + esc('Enregistrez une tenue depuis l’arsenal en jeu (COMSPEC Link), avec ce configurateur, ou copiez un kit de départ.') + '</p></div>');
    }
    function previewWeapon(w, label) {
        if (!w) { return ''; }
        var atts = [w.optic, w.muzzle, w.pointer, w.bipod].filter(Boolean).map(nameOf);
        var mags = [w.mag, w.mag2].filter(Boolean).map(function (m) { return nameOf(m.cls); });
        var m = weaponMass(w);
        return '<li class="cfg-pv__weapon">' + iconHtml(w.cls) + '<span><small>' + esc(label + (m != null ? ' · ' + kg(m) : '')) + '</small><strong translate="no">' + esc(nameOf(w.cls)) + '</strong>'
            + '<em translate="no">' + esc(atts.concat(mags).join(' · ') || 'sans accessoire') + '</em></span></li>';
    }
    function previewContainer(o, slot, label) {
        var c = o[slot];
        if (!c) { return '<div class="cfg-pv__box is-empty"><small>' + esc(label) + '</small><span class="cfg-muted">' + esc('aucun') + '</span></div>'; }
        var cells = c.items.slice(0, 24).map(function (e) {
            var cls = e.t === 'weapon' ? e.w.cls : e.cls;
            return '<li title="' + esc(nameOf(cls)) + '">' + iconHtml(cls) + '<span translate="no">' + esc(nameOf(cls)) + '</span>' + (e.n > 1 ? '<b>×' + e.n + '</b>' : '') + '</li>';
        }).join('');
        return '<div class="cfg-pv__box"><small>' + esc(label) + '</small><strong translate="no">' + esc(nameOf(c.cls)) + '</strong>' + loadBar(containerLoad(c), false)
            + (cells ? '<ul class="cfg-pv__cells">' + cells + '</ul>' + (c.items.length > 24 ? '<p class="cfg-muted">' + esc('… et ' + (c.items.length - 24) + ' autres lignes') + '</p>' : '') : '<p class="cfg-muted">' + esc('Vide') + '</p>') + '</div>';
    }
    function renderSourcePreview() {
        var box = srcDlg().querySelector('[data-cfg-sources-preview]');
        var j = SRC.sel ? SRC.cache[SRC.sel] : null;
        if (!SRC.sel) { return; }
        if (!j) { box.innerHTML = '<p class="cfg-muted">' + esc('Chargement de la tenue…') + '</p>'; return; }
        if (!j.ok) { box.innerHTML = '<p class="cfg-flash cfg-flash--err">' + esc(j.error || 'Tenue indisponible.') + '</p>'; return; }
        var o = outfitFrom(j.loadout, j.name, SRC.sel, j.meta || {});
        var m = outfitMass(o);
        var unk = j.report && j.report.unknown ? j.report.unknown.length : 0;
        var items = ['headgear', 'goggles', 'nvg', 'map', 'gps', 'radio', 'compass', 'watch'].filter(function (k) { return o[k]; }).map(function (k) { return nameOf(o[k]); });
        if (o.binocular) { items.unshift(nameOf(o.binocular.cls)); }
        if (!SRC.parts) { SRC.parts = {}; PARTS.forEach(function (p) { SRC.parts[p[0]] = true; }); }
        var hasPart = function (p) { return p[2].some(function (k) { return !!o[k]; }); };
        var opts = PARTS.map(function (p) {
            var on = hasPart(p);
            return '<label class="cfg-check"><input type="checkbox" data-src-part="' + p[0] + '"' + (on && SRC.parts[p[0]] ? ' checked' : '') + (on ? '' : ' disabled') + '> <span>' + esc(p[1]) + (on ? '' : ' (absent)') + '</span></label>';
        }).join('');
        var how = [
            ['replace', 'Remplacer dans la tenue A', 'Les parties cochées remplacent celles de la tenue A ; le reste de A ne bouge pas.'],
            ['merge', 'Fusionner avec la tenue A', 'Remplit les emplacements vides de A et ajoute le contenu des sacs dans la limite de la place.'],
            ['b', 'Ouvrir en tenue B', 'À côté de la tenue A, pour copier à la main par glisser-déposer.']
        ].map(function (h) {
            return '<label class="cfg-target"><input type="radio" name="cfg-src-how" value="' + h[0] + '"' + (SRC.how === h[0] ? ' checked' : '') + ' data-src-how> <span><strong>' + esc(h[1]) + '</strong> ' + esc(h[2]) + '</span></label>';
        }).join('');
        box.innerHTML = '<div class="cfg-pv">'
            + '<div class="cfg-pv__head"><h3 translate="no">' + esc(j.name) + '</h3><span class="cfg-pill cfg-pill--mass">' + esc(m == null ? 'poids incomplet' : kg(m)) + '</span></div>'
            + (unk ? '<p class="cfg-flash cfg-flash--warn">' + esc(plural(unk, 'objet absent', 'objets absents') + ' du catalogue : à remplacer avant d’enregistrer.') + '</p>' : '')
            + (j.meta && j.meta.description ? '<p class="cfg-muted" translate="no">' + esc(j.meta.description) + '</p>' : '')
            + '<ul class="cfg-pv__weapons">' + (WEAPON_SLOTS.map(function (s) { return previewWeapon(o[s[0]], s[1]); }).join('') || '<li class="cfg-muted">' + esc('Aucune arme') + '</li>') + '</ul>'
            + '<div class="cfg-pv__boxes">' + CONTAINERS.map(function (c) { return previewContainer(o, c[0], c[1]); }).join('') + '</div>'
            + (items.length ? '<p class="cfg-pv__items"><small>' + esc('Porté') + '</small> <span translate="no">' + esc(items.join(' · ')) + '</span></p>' : '')
            + '<fieldset class="cfg-pv__opts"><legend>' + esc('Ce que vous prenez') + '</legend><div class="cfg-pv__parts">' + opts + '</div></fieldset>'
            + '<fieldset class="cfg-targets"><legend>' + esc('Comment') + '</legend>' + how + '</fieldset>'
            + '<footer class="cfg-dialog__foot"><button type="button" class="cfg-btn" data-cfg-close>' + esc('Annuler') + '</button><button type="button" class="cfg-btn cfg-btn--primary" data-src-apply>' + esc('Récupérer') + '</button></footer>'
            + '</div>';
    }
    function selectSource(ref) {
        SRC.sel = ref;
        renderSources();
        if (SRC.cache[ref]) { renderSourcePreview(); return; }
        renderSourcePreview();
        request(urls.source + '?ref=' + encodeURIComponent(ref)).then(function (j) {
            SRC.cache[ref] = j;
            if (SRC.sel === ref) { renderSourcePreview(); }
        });
    }
    /** Copie les parties choisies d'une tenue récupérée dans la tenue visée (remplacer, fusionner, ou tenue B). */
    function applySource() {
        var j = SRC.cache[SRC.sel];
        if (!j || !j.ok) { return; }
        var src = outfitFrom(j.loadout, j.name, SRC.sel, j.meta || {});
        var parts = PARTS.filter(function (p) { return SRC.parts[p[0]] && p[2].some(function (k) { return !!src[k]; }); });
        if (!parts.length) { toast('Cochez au moins une partie à récupérer.', 'warn'); return; }
        var all = parts.length === PARTS.filter(function (p) { return p[2].some(function (k) { return !!src[k]; }); }).length;
        var how = SRC.how;
        var notes = [];
        commit('Tenue récupérée.', function () {
            if (how === 'b' || (how === 'replace' && all)) {
                var to = how === 'b' ? 'b' : 'a';
                var dest = all ? src : blankOutfit();
                if (!all) { parts.forEach(function (p) { p[2].forEach(function (k) { dest[k] = clone(src[k]); }); }); dest.name = src.name; }
                S.outfits[to] = dest;
                return;
            }
            var a = S.outfits.a;
            parts.forEach(function (p) {
                p[2].forEach(function (k) {
                    var v = src[k];
                    if (!v) { return; }
                    if (how === 'replace' || !a[k]) { a[k] = clone(v); return; }
                    if (CONTAINER_CODE[k]) {
                        var r = insertEntries(a, k, clone(v.items), false);
                        if (r.skipped.length) { notes.push('Pas de place dans ' + CONTAINER_WORD[k] + ' pour : ' + skippedText(r.skipped) + '.'); }
                        return;
                    }
                    notes.push(slotLabel(k) + ' déjà pris : gardé.');
                });
            });
        });
        if (how === 'b' && S.mode !== 'outfit') { setMode('outfit'); }
        srcDlg().close();
        var unk = j.report && j.report.unknown ? j.report.unknown.length : 0;
        var msg = '« ' + j.name + ' » ' + (how === 'b' ? 'ouverte en tenue B.' : (how === 'merge' ? 'fusionnée avec la tenue A.' : 'récupérée dans la tenue A.'))
            + (notes.length ? ' ' + notes.join(' ') : '') + (unk ? ' ' + unk + ' objet(s) absent(s) du catalogue : à remplacer avant d’enregistrer.' : '');
        toast(msg, notes.length || unk ? 'warn' : '');
    }
    function toggleWardrobeFav(ref) {
        var s = sourcesCache.filter(function (x) { return x.ref === ref; })[0];
        if (!s || !urls.wardrobeFavorite) { return; }
        var on = !s.fav;
        s.fav = on;
        renderSources();
        var fd = new FormData();
        fd.append('on', on ? '1' : '0');
        request(urls.wardrobeFavorite + ref.slice(2) + '/favori', { form: fd }).then(function (j) {
            if (!j.ok) { s.fav = !on; renderSources(); toast('Favori non enregistré.', 'err'); }
        });
    }
    srcDlg().addEventListener('input', function (e) { if (e.target.hasAttribute('data-cfg-sources-q') && sourcesCache) { renderSources(); } });
    srcDlg().addEventListener('change', function (e) {
        if ((e.target.hasAttribute('data-cfg-sources-side') || e.target.hasAttribute('data-cfg-sources-group')) && sourcesCache) { renderSources(); }
        if (e.target.hasAttribute('data-src-part')) { SRC.parts[e.target.getAttribute('data-src-part')] = e.target.checked; }
        if (e.target.hasAttribute('data-src-how')) { SRC.how = e.target.value; }
    });
    srcDlg().addEventListener('click', function (e) {
        var t = e.target.closest('[data-src-tab]');
        if (t) { SRC.tab = t.getAttribute('data-src-tab'); renderSources(); return; }
        var p = e.target.closest('[data-src-pick]');
        if (p) { selectSource(p.getAttribute('data-src-pick')); return; }
        var f = e.target.closest('[data-src-fav]');
        if (f) { toggleWardrobeFav(f.getAttribute('data-src-fav')); return; }
        if (e.target.closest('[data-src-apply]')) { applySource(); }
    });
    function loadSource(ref, to, done) {
        request(urls.source + '?ref=' + encodeURIComponent(ref)).then(function (j) {
            if (!j.ok) { toast(j.error || 'Tenue indisponible.', 'err'); return; }
            commit('Tenue ouverte.', function () { S.outfits[to] = outfitFrom(j.loadout, j.name, ref, j.meta || {}); });
            if (to === 'b' && S.mode !== 'outfit') { setMode('outfit'); }
            var unk = j.report && j.report.unknown ? j.report.unknown.length : 0;
            toast('« ' + j.name + ' » ouverte en ' + OUTFIT_NAME[to] + '.' + (unk ? ' ' + unk + ' objet(s) absent(s) du catalogue : à remplacer avant d’enregistrer.' : ''), unk ? 'warn' : '');
            if (done) { done(); }
        });
    }

    // ------------------------------------------------------------------ Pré-kits
    var preDlg = dlg('cfg-prekits');
    var preForm = preDlg.querySelector('[data-cfg-prekit-form]');
    var PK = { target: 'a:vest', edit: null, lines: [], pending: null };
    var PREKIT_REFUSED = ['primary', 'secondary', 'handgun', 'backpack', 'uniform', 'vest'];
    // Exemple « Équipement obligatoire » : premières classes présentes dans le catalogue (ACE, radios des mods, jeu de base).
    var PREKIT_EXAMPLE = [
        [['ACE_tourniquet'], 2],
        [['ACE_fieldDressing', 'ACE_packingBandage', 'ACE_elasticBandage', 'FirstAidKit'], 4],
        [['ACE_morphine', 'ACE_epinephrine'], 1],
        [['ACRE_PRC343', 'TFAR_anprc152', 'TFAR_rf7800str', 'ItemRadio'], 1],
        [['ACE_MapTools'], 1],
        [['ItemMap'], 1],
        [['ACE_EarPlugs'], 1]
    ];
    function prekitEntries(items) { return items.map(function (l) { return entryFor({ k: 'cls', cls: (spec(l[0]) || { c: l[0] }).c, n: l[1] }); }); }
    function prekitMass(items) {
        var t = 0;
        for (var i = 0; i < items.length; i++) { var m = massOf(items[i][0]); if (m == null) { return null; } t += m * items[i][1]; }
        return t;
    }
    function targetParts(v) { var p = String(v || '').split(':'); return S.outfits[p[0]] && CONTAINER_CODE[p[1]] ? p : null; }
    function fillPrekitTargets() {
        var sel = preDlg.querySelector('[data-cfg-prekit-target]');
        var opts = [];
        ['a', 'b'].forEach(function (o) {
            if (!S.outfits[o]) { return; }
            CONTAINERS.forEach(function (c) {
                var ct = S.outfits[o][c[0]];
                opts.push('<option value="' + o + ':' + c[0] + '"' + (ct ? '' : ' disabled') + (PK.target === o + ':' + c[0] ? ' selected' : '') + '>' + esc(OUTFIT_NAME[o] + ' · ' + c[1] + (ct ? ' (' + (containerLoad(ct).max ? 'reste ' + kg(Math.max(0, containerLoad(ct).max - containerLoad(ct).load)) : 'capacité inconnue') + ')' : ' : aucun')) + '</option>');
            });
        });
        sel.innerHTML = opts.join('');
        if (!targetParts(sel.value) || !S.outfits[sel.value.split(':')[0]][sel.value.split(':')[1]]) {
            var first = Array.prototype.slice.call(sel.options).filter(function (op) { return !op.disabled; })[0];
            if (first) { sel.value = first.value; }
        }
        PK.target = sel.value;
    }
    function openPrekits(target) {
        if (target && targetParts(target)) { PK.target = target; }
        PK.pending = null;
        showPrekitForm(false);
        fillPrekitTargets();
        renderPrekits();
        preDlg.showModal();
        if (!M.ready) { loadMember().then(renderPrekits); }
    }
    function renderPrekits() {
        var box = preDlg.querySelector('[data-cfg-prekits-list]');
        var planBox = preDlg.querySelector('[data-cfg-prekit-plan]');
        planBox.hidden = !PK.pending;
        if (!M.ready) { box.innerHTML = '<p class="cfg-muted">' + esc('Chargement…') + '</p>'; return; }
        if (!M.readyPre) {
            box.innerHTML = '<div class="cfg-empty"><p><strong>' + esc('Pré-kits pas encore disponibles.') + '</strong></p><p>' + esc('Un responsable doit appliquer la mise à jour de la base (étape « Configurateur : favoris d’objets et pré-kits »).') + '</p></div>';
            preDlg.querySelector('[data-cfg-prekit-new]').disabled = true;
            return;
        }
        preDlg.querySelector('[data-cfg-prekit-new]').disabled = false;
        if (!M.prekits.length) {
            box.innerHTML = '<div class="cfg-empty"><p><strong>' + esc('Aucun pré-kit pour l’instant.') + '</strong></p><p>' + esc('Créez-en un : « Nouveau pré-kit », puis l’exemple « Équipement obligatoire » ou le contenu de votre gilet.') + '</p></div>';
            return;
        }
        var tp = targetParts(PK.target);
        box.innerHTML = '<ul class="cfg-prekit-list">' + M.prekits.map(function (p) {
            var m = prekitMass(p.items);
            var plan = tp ? insertEntries(S.outfits[tp[0]], tp[1], prekitEntries(p.items), true) : null;
            var fit = !plan ? 'Posez un uniforme, un gilet ou un sac dans la tenue pour y insérer un pré-kit.' : (plan.error ? plan.error : (plan.skipped.length ? 'Ne tient pas en entier : ' + skippedText(plan.skipped) : (plan.unchecked ? 'Place non vérifiée (capacité ou poids inconnu)' : 'Tient en entier')));
            var tone = !plan || plan.error ? 'err' : (plan.skipped.length ? 'warn' : (plan.unchecked ? 'warn' : 'ok'));
            var list = p.items.slice(0, 8).map(function (l) { return nameOf(l[0]) + (l[1] > 1 ? ' × ' + l[1] : ''); }).join(' · ') + (p.items.length > 8 ? ' …' : '');
            return '<li class="cfg-prekit">'
                + '<div class="cfg-prekit__head"><strong translate="no">' + esc(p.name) + '</strong><span class="cfg-pill">' + esc(p.shared ? 'Communauté' : 'Personnel') + '</span><span class="cfg-pill cfg-pill--mass">' + esc(m == null ? 'poids ?' : kg(m)) + '</span></div>'
                + (p.description ? '<p class="cfg-muted" translate="no">' + esc(p.description) + '</p>' : '')
                + '<p class="cfg-prekit__items" translate="no">' + esc(list) + '</p>'
                + '<p class="cfg-prekit__fit cfg-prekit__fit--' + tone + '">' + esc(fit) + '</p>'
                + '<div class="cfg-row-actions"><button type="button" class="cfg-btn cfg-btn--sm cfg-btn--primary" data-pk-insert="' + p.id + '"' + (plan && !plan.error && plan.added ? '' : ' disabled') + '>' + esc('Insérer') + '</button>'
                + (p.editable ? '<button type="button" class="cfg-btn cfg-btn--sm" data-pk-edit="' + p.id + '">' + esc('Modifier') + '</button><button type="button" class="cfg-btn cfg-btn--sm cfg-btn--quiet" data-pk-del="' + p.id + '">' + esc('Supprimer') + '</button>' : '')
                + '</div></li>';
        }).join('') + '</ul>';
    }
    function prekitById(id) { return M.prekits.filter(function (p) { return String(p.id) === String(id); })[0] || null; }
    function insertPrekit(p, partialOk) {
        var tp = targetParts(PK.target);
        if (!tp) { return; }
        var entries = prekitEntries(p.items);
        var plan = insertEntries(S.outfits[tp[0]], tp[1], entries, true);
        if (plan.error) { toast(plan.error, 'err'); return; }
        if (!plan.added) { toast('Refusé : rien du pré-kit ne tient dans ' + CONTAINER_WORD[tp[1]] + '. ' + skippedText(plan.skipped) + '.', 'err'); return; }
        if (plan.skipped.length && !partialOk) {
            PK.pending = p.id;
            var box = preDlg.querySelector('[data-cfg-prekit-plan]');
            box.hidden = false;
            var title = '« ' + p.name + ' » ne tient pas en entier dans ' + CONTAINER_WORD[tp[1]] + ' (' + OUTFIT_NAME[tp[0]] + ').';
            var why = plan.skipped.map(function (s) { return '<li>' + esc(nameOf(s.cls) + (s.n > 1 ? ' × ' + s.n : '') + ' : ' + s.reason) + '</li>'; }).join('');
            box.innerHTML = '<p class="cfg-report__title">' + esc(title) + '</p><ul>' + why + '</ul>'
                + '<div class="cfg-row-actions"><button type="button" class="cfg-btn cfg-btn--sm cfg-btn--primary" data-pk-partial="' + p.id + '">' + esc('Insérer ce qui tient (' + plural(plan.added, 'objet', 'objets') + ')') + '</button>'
                + '<button type="button" class="cfg-btn cfg-btn--sm" data-pk-cancel>' + esc('Annuler') + '</button></div>';
            return;
        }
        var res = null;
        commit('Pré-kit inséré.', function () { res = insertEntries(S.outfits[tp[0]], tp[1], entries, false); });
        PK.pending = null;
        preDlg.close();
        if (tp[0] === 'a' && S.mode !== 'outfit') { setMode('outfit'); }
        toast('« ' + p.name + ' » inséré dans ' + CONTAINER_WORD[tp[1]] + ' : ' + plural(res.added, 'objet', 'objets') + '.'
            + (res.skipped.length ? ' Pas inséré, faute de place : ' + skippedText(res.skipped) + '.' : '') + (res.unchecked ? ' Place non vérifiée pour une partie (poids ou capacité inconnus).' : ''), res.skipped.length || res.unchecked ? 'warn' : 'ok');
    }
    function showPrekitForm(on, p) {
        preForm.hidden = !on;
        preDlg.querySelector('[data-cfg-prekits-browse]').hidden = on;
        if (!on) { return; }
        PK.edit = p ? p.id : null;
        PK.lines = p ? p.items.map(function (l) { return [l[0], l[1]]; }) : [];
        preForm.querySelector('[data-cfg-prekit-form-title]').textContent = p ? 'Modifier le pré-kit' : 'Nouveau pré-kit';
        preForm.querySelector('input[name="name"]').value = p ? p.name : '';
        preForm.querySelector('input[name="description"]').value = p ? p.description : '';
        var sh = preForm.querySelector('input[name="shared"]');
        if (sh) { sh.checked = !!(p && p.shared); }
        preForm.querySelector('[data-cfg-prekit-q]').value = '';
        preForm.querySelector('[data-cfg-prekit-hits]').hidden = true;
        preForm.querySelector('[data-cfg-prekit-report]').hidden = true;
        renderPrekitLines();
        preForm.querySelector('input[name="name"]').focus();
    }
    function renderPrekitLines() {
        var ul = preForm.querySelector('[data-cfg-prekit-lines]');
        ul.innerHTML = PK.lines.length ? PK.lines.map(function (l, i) {
            var m = massOf(l[0]);
            return '<li>' + iconHtml(l[0]) + '<span class="cfg-prekit-lines__name"><strong translate="no">' + esc(nameOf(l[0])) + '</strong><small>' + esc(m == null ? 'poids ?' : kg(m) + ' l’unité') + '</small></span>'
                + '<label><span class="cfg-sr">' + esc('Nombre') + '</span><input type="number" min="1" max="99" value="' + l[1] + '" data-pk-qty="' + i + '"></label>'
                + '<button type="button" class="cfg-piece__x" data-pk-line-del="' + i + '" aria-label="' + esc('Retirer : ' + nameOf(l[0])) + '">×</button></li>';
        }).join('') : '<li class="cfg-muted">' + esc('Pré-kit vide : cherchez un objet ci-dessus, ou reprenez le contenu d’un contenant.') + '</li>';
        var m = prekitMass(PK.lines);
        preForm.querySelector('[data-cfg-prekit-mass]').textContent = PK.lines.length ? 'Poids du pré-kit : ' + (m == null ? 'incomplet (objets sans poids connu)' : kg(m)) : '';
    }
    function addPrekitLine(cls, n) {
        var s = spec(cls);
        if (!s) { return false; }
        if (PREKIT_REFUSED.indexOf(s.k) >= 0) { toast('« ' + nameOf(cls) + ' » ne va pas dans un pré-kit : seulement des objets à ranger.', 'warn'); return false; }
        for (var i = 0; i < PK.lines.length; i++) {
            if (PK.lines[i][0].toLowerCase() === s.c.toLowerCase()) { PK.lines[i][1] = Math.min(99, PK.lines[i][1] + n); return true; }
        }
        if (PK.lines.length >= 40) { toast('40 objets différents au plus dans un pré-kit.', 'warn'); return false; }
        PK.lines.push([s.c, Math.min(99, n)]);
        return true;
    }
    preDlg.addEventListener('change', function (e) {
        if (e.target.hasAttribute('data-cfg-prekit-target')) { PK.target = e.target.value; PK.pending = null; renderPrekits(); }
        if (e.target.hasAttribute('data-pk-qty')) {
            var i = parseInt(e.target.getAttribute('data-pk-qty'), 10);
            var n = Math.max(1, Math.min(99, parseInt(e.target.value, 10) || 1));
            if (PK.lines[i]) { PK.lines[i][1] = n; }
            renderPrekitLines();
        }
        if (e.target.hasAttribute('data-cfg-prekit-from') && e.target.value) {
            var ct = S.outfits.a[e.target.value];
            if (!ct) { toast('La tenue A n’a pas de ' + ({ uniform: 'uniforme', vest: 'gilet', backpack: 'sac' }[e.target.value]) + '.', 'warn'); } else {
                ct.items.forEach(function (it) { if (it.t !== 'weapon') { addPrekitLine(it.cls, it.n); } });
                renderPrekitLines();
            }
            e.target.value = '';
        }
    });
    preDlg.addEventListener('input', function (e) {
        if (!e.target.hasAttribute('data-cfg-prekit-q')) { return; }
        var words = fold(e.target.value).split(/\s+/).filter(Boolean);
        var hits = preForm.querySelector('[data-cfg-prekit-hits]');
        if (!words.length) { hits.hidden = true; return; }
        var found = [];
        for (var i = 0; i < CAT.items.length && found.length < 8; i++) {
            var it = CAT.items[i];
            if (PREKIT_REFUSED.indexOf(it.k) >= 0) { continue; }
            if (words.every(function (w) { return it._f.indexOf(w) >= 0; })) { found.push(it); }
        }
        hits.hidden = false;
        hits.innerHTML = found.length ? found.map(function (it) {
            return '<li><button type="button" data-pk-add="' + it._i + '">' + iconHtml(it.c) + '<span><strong translate="no">' + esc(it.n || it.c) + '</strong><small>' + esc((KIND_LABEL[it.k] || '') + (it.m != null ? ' · ' + kg(it.m) : '')) + '</small></span></button></li>';
        }).join('') : '<li class="cfg-muted">' + esc('Aucun objet trouvé.') + '</li>';
    });
    preDlg.addEventListener('click', function (e) {
        var b = e.target.closest('button');
        if (!b) { return; }
        if (b.hasAttribute('data-cfg-prekit-new')) { showPrekitForm(true, null); return; }
        if (b.hasAttribute('data-cfg-prekit-cancel')) { showPrekitForm(false); renderPrekits(); return; }
        if (b.hasAttribute('data-pk-cancel')) { PK.pending = null; renderPrekits(); return; }
        if (b.hasAttribute('data-pk-insert')) { var p1 = prekitById(b.getAttribute('data-pk-insert')); if (p1) { insertPrekit(p1, false); } return; }
        if (b.hasAttribute('data-pk-partial')) { var p2 = prekitById(b.getAttribute('data-pk-partial')); if (p2) { insertPrekit(p2, true); } return; }
        if (b.hasAttribute('data-pk-edit')) { var p3 = prekitById(b.getAttribute('data-pk-edit')); if (p3) { showPrekitForm(true, p3); } return; }
        if (b.hasAttribute('data-pk-del')) {
            var p4 = prekitById(b.getAttribute('data-pk-del'));
            if (!p4 || !window.confirm('Supprimer le pré-kit « ' + p4.name + ' » ?' + (p4.shared ? ' Il disparaît pour toute la communauté.' : ''))) { return; }
            request(urls.prekits + '/' + p4.id + '/supprimer', { form: new FormData() }).then(function (j) {
                if (!j.ok) { toast(j.error || 'Suppression impossible.', 'err'); return; }
                M.prekits = M.prekits.filter(function (x) { return x.id !== p4.id; });
                renderPrekits();
                toast(j.message, 'ok');
            });
            return;
        }
        if (b.hasAttribute('data-pk-add')) {
            var it = CAT.items[parseInt(b.getAttribute('data-pk-add'), 10)];
            if (it && addPrekitLine(it.c, 1)) { renderPrekitLines(); }
            preForm.querySelector('[data-cfg-prekit-hits]').hidden = true;
            var q = preForm.querySelector('[data-cfg-prekit-q]');
            q.value = '';
            q.focus();
            return;
        }
        if (b.hasAttribute('data-pk-line-del')) { PK.lines.splice(parseInt(b.getAttribute('data-pk-line-del'), 10), 1); renderPrekitLines(); return; }
        if (b.hasAttribute('data-cfg-prekit-example')) {
            var added = 0;
            PREKIT_EXAMPLE.forEach(function (ex) {
                for (var i = 0; i < ex[0].length; i++) { if (spec(ex[0][i])) { if (addPrekitLine(ex[0][i], ex[1])) { added++; } return; } }
            });
            var nm = preForm.querySelector('input[name="name"]');
            if (!nm.value) { nm.value = 'Équipement obligatoire'; }
            renderPrekitLines();
            toast(added ? 'Exemple ajouté : ajustez les nombres puis enregistrez.' : 'Aucun objet de l’exemple dans le catalogue de la communauté.', added ? '' : 'warn');
        }
    });
    preForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var rep = preForm.querySelector('[data-cfg-prekit-report]');
        if (!PK.lines.length) { rep.hidden = false; rep.innerHTML = '<p class="cfg-flash cfg-flash--err">' + esc('Ajoutez au moins un objet.') + '</p>'; return; }
        var fd = new FormData();
        fd.append('name', preForm.querySelector('input[name="name"]').value);
        fd.append('description', preForm.querySelector('input[name="description"]').value);
        var sh = preForm.querySelector('input[name="shared"]');
        fd.append('shared', sh && sh.checked ? '1' : '0');
        fd.append('items', JSON.stringify(PK.lines));
        if (PK.edit) { fd.append('id', String(PK.edit)); }
        var btn = preForm.querySelector('button[type="submit"]');
        btn.disabled = true;
        request(urls.prekits, { form: fd }).then(function (j) {
            btn.disabled = false;
            if (!j.ok) {
                rep.hidden = false;
                rep.innerHTML = '<p class="cfg-flash cfg-flash--err">' + esc(j.error || 'Enregistrement impossible.') + '</p>'
                    + (j.problems && j.problems.length ? '<ul>' + j.problems.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul>' : '');
                return;
            }
            toast(j.message, 'ok');
            loadMember().then(function () { showPrekitForm(false); renderPrekits(); });
        });
    });

    // Importer
    var imported = null;
    var importDlg = dlg('cfg-import');
    importDlg.querySelector('[data-cfg-import-analyse]').addEventListener('click', function () {
        var fd = new FormData();
        fd.append('text', importDlg.querySelector('[data-cfg-import-text]').value);
        request(urls.analyse, { form: fd }).then(function (j) {
            var box = importDlg.querySelector('[data-cfg-import-report]');
            box.hidden = false;
            imported = j.ok ? j : null;
            $$('[data-cfg-import-open]', importDlg).forEach(function (b) { b.disabled = !j.ok; });
            importDlg.querySelector('[data-cfg-import-strip]').hidden = !(j.ok && j.report && j.report.unknown && j.report.unknown.length);
            box.innerHTML = j.ok ? reportHtml(j.report, 'Tenue lisible.') : '<p class="cfg-flash cfg-flash--err">' + esc(j.error || 'Texte illisible.') + '</p>';
        });
    });
    importDlg.querySelector('[data-cfg-import-strip]').addEventListener('click', function () {
        if (!imported) { return; }
        var unknown = {};
        imported.report.unknown.forEach(function (c) { unknown[c.toLowerCase()] = true; });
        var o = outfitFrom(imported.loadout, '', '', {});
        stripUnknown(o, unknown);
        imported.loadout = outfitTo(o);
        imported.report.unknown = [];
        this.hidden = true;
        importDlg.querySelector('[data-cfg-import-report]').innerHTML = '<p class="cfg-flash cfg-flash--ok">' + esc('Objets inconnus retirés : la tenue ne contient plus que des objets du catalogue.') + '</p>';
    });
    $$('[data-cfg-import-open]', importDlg).forEach(function (b) {
        b.addEventListener('click', function () {
            if (!imported) { return; }
            var to = b.getAttribute('data-cfg-import-open');
            commit('Tenue importée.', function () { S.outfits[to] = outfitFrom(imported.loadout, 'Tenue importée', '', {}); });
            importDlg.close();
            toast('Tenue importée en ' + OUTFIT_NAME[to] + '.');
        });
    });
    function stripUnknown(o, unknown) {
        function bad(c) { return c && unknown[c.toLowerCase()]; }
        function weapon(w) {
            if (!w) { return null; }
            if (bad(w.cls)) { return null; }
            ['muzzle', 'pointer', 'optic', 'bipod'].forEach(function (k) { if (bad(w[k])) { w[k] = ''; } });
            ['mag', 'mag2'].forEach(function (k) { if (w[k] && bad(w[k].cls)) { w[k] = null; } });
            return w;
        }
        ['primary', 'secondary', 'handgun', 'binocular'].forEach(function (k) { o[k] = weapon(o[k]); });
        CONTAINERS.forEach(function (c) {
            var ct = o[c[0]];
            if (!ct) { return; }
            if (bad(ct.cls)) { o[c[0]] = null; return; }
            ct.items = ct.items.filter(function (e) { return e.t === 'weapon' ? !!weapon(e.w) : !bad(e.cls); });
        });
        ['headgear', 'goggles', 'map', 'gps', 'radio', 'compass', 'watch', 'nvg'].forEach(function (k) { if (bad(o[k])) { o[k] = ''; } });
    }
    function reportHtml(r, okText) {
        if (!r) { return ''; }
        var html = '';
        if (r.errors && r.errors.length) { html += '<p class="cfg-report__title cfg-report__title--err">' + esc('À corriger') + '</p><ul>' + r.errors.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul>'; }
        if (r.warnings && r.warnings.length) { html += '<p class="cfg-report__title">' + esc('Avertissements') + '</p><ul>' + r.warnings.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul>'; }
        return html || '<p class="cfg-flash cfg-flash--ok">' + esc(okText) + '</p>';
    }

    // Exporter
    var exportDlg = dlg('cfg-export');
    function renderExport() {
        var fmt = exportDlg.querySelector('[data-cfg-export-format]:checked').value;
        var a = S.outfits.a;
        var lo = outfitTo(a);
        exportDlg.querySelector('[data-cfg-export-text]').value = fmt === 'ace' ? JSON.stringify(lo) : JSON.stringify({ format: 'comspec-loadout', v: 1, name: a.name || '', loadout: lo });
        exportDlg.querySelector('[data-cfg-export-status]').textContent = '';
    }
    $$('[data-cfg-export-format]', exportDlg).forEach(function (r) { r.addEventListener('change', renderExport); });
    exportDlg.querySelector('[data-cfg-export-copy]').addEventListener('click', function () {
        var ta = exportDlg.querySelector('[data-cfg-export-text]');
        var status = exportDlg.querySelector('[data-cfg-export-status]');
        var done = function () { status.textContent = 'Copié dans le presse-papier.'; };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(ta.value).then(done, function () { ta.select(); status.textContent = 'Texte sélectionné : Ctrl+C pour copier.'; });
        } else {
            ta.select();
            status.textContent = 'Texte sélectionné : Ctrl+C pour copier.';
        }
    });

    // Enregistrer
    var saveDlg = dlg('cfg-save');
    var saveForm = saveDlg.querySelector('[data-cfg-save-form]');
    function fillAudiences(selected) {
        var sel = saveForm.querySelector('[data-cfg-audience]');
        if (!sel) { return; }
        sel.innerHTML = (boot.audiences || []).map(function (g) {
            var opts = g.options.map(function (o) { return '<option value="' + esc(o.value) + '"' + (o.value === selected ? ' selected' : '') + ' translate="no">' + esc(o.label) + '</option>'; }).join('');
            return g.label ? '<optgroup label="' + esc(g.label) + '">' + opts + '</optgroup>' : opts;
        }).join('');
    }
    function prepareSave() {
        var a = S.outfits.a;
        var ref = a.ref || '';
        var meta = a.meta || {};
        var target = 'tenue';
        var canUpdate = false;
        var label = '';
        if (/^k:\d+$/.test(ref) && can.kits && meta.editable) { target = 'kit'; canUpdate = true; label = 'Mettre à jour le kit « ' + a.name + ' » (nouvelle version)'; }
        if (/^w:\d+$/.test(ref) && meta.mine) {
            target = meta.kind === 'model' && can.kits ? 'modele' : 'tenue';
            canUpdate = true;
            label = 'Mettre à jour « ' + a.name + ' » au lieu d’en créer une nouvelle';
        }
        var radio = saveForm.querySelector('input[name="target"][value="' + target + '"]') || saveForm.querySelector('input[name="target"]');
        radio.checked = true;
        saveForm.querySelector('input[name="name"]').value = a.name || '';
        saveForm.querySelector('textarea[name="description"]').value = meta.description || '';
        var wrap = saveForm.querySelector('[data-cfg-save-update-wrap]');
        wrap.hidden = !canUpdate;
        saveForm.querySelector('[data-cfg-save-update-label]').textContent = label;
        saveForm.querySelector('[data-cfg-save-update]').checked = canUpdate;
        fillAudiences(meta.audience || 'all');
        toggleKitFields();
        var rep = saveForm.querySelector('[data-cfg-save-report]');
        var chk = checkOutfit(a);
        rep.hidden = !chk.errors.length;
        rep.innerHTML = chk.errors.length ? reportHtml({ errors: chk.errors }, '') : '';
    }
    function toggleKitFields() {
        var t = saveForm.querySelector('input[name="target"]:checked');
        var f = saveForm.querySelector('[data-cfg-kit-fields]');
        if (f) { f.hidden = !t || t.value !== 'kit'; }
    }
    saveForm.addEventListener('change', function (e) { if (e.target.name === 'target') { toggleKitFields(); } });
    saveForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var a = S.outfits.a;
        var fd = new FormData(saveForm);
        var target = fd.get('target');
        var update = fd.get('update') === '1';
        fd.delete('update');
        var ref = a.ref || '';
        if (!(update && ((target === 'kit' && /^k:/.test(ref)) || (target !== 'kit' && /^w:/.test(ref))))) { ref = ''; }
        fd.append('ref', ref);
        fd.append('loadout', JSON.stringify(outfitTo(a)));
        var btn = saveForm.querySelector('button[type="submit"]');
        btn.disabled = true;
        request(urls.save, { form: fd }).then(function (j) {
            btn.disabled = false;
            var rep = saveForm.querySelector('[data-cfg-save-report]');
            if (!j.ok) {
                rep.hidden = false;
                rep.innerHTML = '<p class="cfg-flash cfg-flash--err">' + esc(j.error || 'Enregistrement impossible.') + '</p>' + reportHtml(j.report, '');
                return;
            }
            a.name = String(fd.get('name') || a.name);
            a.ref = j.ref || a.ref;
            a.meta = { kind: target === 'kit' ? 'kit' : (target === 'modele' ? 'model' : 'wardrobe'), mine: true, editable: true, audience: fd.get('audience') || 'all', description: String(fd.get('description') || '') };
            sourcesCache = null;
            SRC.cache = {};
            saveDlg.close();
            renderAll();
            saveDraft();
            toast(j.message + (j.warnings && j.warnings.length ? ' ' + j.warnings.length + ' vérification(s) impossible(s) faute de données du jeu.' : ''), j.warnings && j.warnings.length ? 'warn' : 'ok');
        });
    });

    // Historique d'un kit
    function openVersions() {
        var m = /^k:(\d+)$/.exec(S.outfits.a.ref || '');
        if (!m) { return; }
        var d = dlg('cfg-versions');
        var box = d.querySelector('[data-cfg-versions-list]');
        box.innerHTML = '<p class="cfg-muted">' + esc('Chargement…') + '</p>';
        d.showModal();
        request(urls.versions + m[1] + '/versions').then(function (j) {
            if (!j.ok) { box.innerHTML = '<p class="cfg-flash cfg-flash--err">' + esc(j.error || 'Historique indisponible.') + '</p>'; return; }
            var v = j.versions || [];
            box.innerHTML = v.length ? '<ol class="cfg-versions__list">' + v.map(function (x, i) {
                return '<li><span><strong>' + esc('Version ' + x.version_no) + '</strong>' + (i === 0 ? ' <span class="cfg-pill">' + esc('en cours') + '</span>' : '') + '<em>' + esc(x.created_at) + (x.author ? ' · <span translate="no">' + esc(x.author) + '</span>' : '') + '</em>'
                    + (x.note ? '<small translate="no">' + esc(x.note) + '</small>' : '') + '</span>'
                    + '<button type="button" class="cfg-btn cfg-btn--sm" data-ver-open="' + x.version_no + '">' + esc('Ouvrir en B') + '</button>'
                    + (i > 0 ? '<button type="button" class="cfg-btn cfg-btn--sm cfg-btn--primary" data-ver-restore="' + x.version_no + '">' + esc('Restaurer') + '</button>' : '') + '</li>';
            }).join('') + '</ol>' : '<div class="cfg-empty"><p>' + esc('Pas encore d’historique : la première version est gardée au prochain enregistrement du kit.') + '</p></div>';
        });
        box.onclick = function (e) {
            var o = e.target.closest('[data-ver-open]');
            var r = e.target.closest('[data-ver-restore]');
            if (o) {
                request(urls.versions + m[1] + '/versions/' + o.getAttribute('data-ver-open')).then(function (j) {
                    if (!j.ok) { toast(j.error || 'Version indisponible.', 'err'); return; }
                    commit('Version ouverte en B.', function () { S.outfits.b = outfitFrom(j.loadout, j.name + ' (v' + o.getAttribute('data-ver-open') + ')', '', {}); });
                    setMode('outfit');
                    d.close();
                });
            }
            if (r) {
                if (!window.confirm('Restaurer la version ' + r.getAttribute('data-ver-restore') + ' ? Elle devient la version en cours ; l’historique est gardé.')) { return; }
                request(urls.versions + m[1] + '/versions/' + r.getAttribute('data-ver-restore') + '/restaurer', { form: new FormData() }).then(function (j) {
                    if (!j.ok) { toast(j.error || 'Restauration impossible.', 'err'); return; }
                    d.close();
                    toast(j.message, 'ok');
                    loadSource('k:' + m[1], 'a');
                });
            }
        };
    }

    // Catalogue : contenu, import des mods, catalogue de base
    var catDlg = dlg('cfg-catalog');
    var catForm = catDlg.querySelector('[data-cfg-catalog-form]');
    function renderCatalogInfo() {
        var list = catDlg.querySelector('[data-cfg-catalog-families]');
        var kinds = Object.keys(CAT.counts).sort(function (x, y) { return CAT.counts[y] - CAT.counts[x]; });
        list.innerHTML = kinds.map(function (k) { return '<li><span>' + esc(KIND_LABEL[k] || k) + '</span><b>' + CAT.counts[k].toLocaleString('fr-FR') + '</b></li>'; }).join('');
        catDlg.querySelector('[data-cfg-catalog-summary]').textContent = CAT.items.length
            ? CAT.items.length.toLocaleString('fr-FR') + ' objets : ' + CAT.base.toLocaleString('fr-FR') + ' du catalogue de base d’Arma 3 (jeu de base et DLC officiels), ' + Math.max(0, CAT.specs - CAT.base).toLocaleString('fr-FR') + ' importés du jeu avec les mods, ' + Math.max(0, CAT.items.length - CAT.specs).toLocaleString('fr-FR') + ' seulement vus en jeu. Un objet importé passe avant le catalogue de base.'
            : 'Catalogue vide pour l’instant.';
    }
    if (catForm.querySelector('button[type="submit"]')) {
        catForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var rep = catForm.querySelector('[data-cfg-catalog-report]');
            var btn = catForm.querySelector('button[type="submit"]');
            btn.disabled = true;
            rep.hidden = false;
            rep.innerHTML = '<p class="cfg-muted">' + esc('Import en cours…') + '</p>';
            request(urls.import, { form: new FormData(catForm) }).then(function (j) {
                btn.disabled = false;
                if (!j.ok) { rep.innerHTML = '<p class="cfg-flash cfg-flash--err">' + esc(j.error || 'Import impossible.') + '</p>'; return; }
                rep.innerHTML = '<p class="cfg-flash cfg-flash--ok">' + esc(j.message + ' Le catalogue est rechargé.') + '</p>';
                var notice = $('[data-cfg-specs-notice]');
                if (notice) { notice.hidden = true; }
                loadCatalog().then(renderCatalogInfo);
            });
        });
    }

    // Catalogue de base d'Arma 3 : chargé par lots au premier accès, avec l'avancement.
    var baseRunning = false;
    var baseBox = $('[data-cfg-base]');
    function baseProgress(done, total) {
        var pct = total ? Math.round(done / total * 100) : 0;
        baseBox.querySelector('[data-cfg-base-bar] i').style.width = pct + '%';
        baseBox.querySelector('[data-cfg-base-bar]').setAttribute('aria-valuenow', String(pct));
        baseBox.querySelector('[data-cfg-base-detail]').textContent = done.toLocaleString('fr-FR') + ' / ' + total.toLocaleString('fr-FR') + ' objets · ' + pct + ' %';
    }
    function runBase(restart) {
        if (baseRunning || !window.AthenaEquipmentBase || !urls.base) { return; }
        baseRunning = true;
        baseBox.hidden = false;
        baseBox.classList.remove('is-done', 'is-error');
        baseBox.querySelector('[data-cfg-base-retry]').hidden = true;
        baseBox.querySelector('[data-cfg-base-title]').textContent = restart ? 'Rechargement du catalogue de base d’Arma 3' : 'Préparation du catalogue de base d’Arma 3';
        baseProgress(restart ? 0 : ((boot.base && boot.base.done) || 0), (boot.base && boot.base.total) || 0);
        if (!CAT.items.length) { filterPalette(); }
        window.AthenaEquipmentBase.run({
            url: urls.base,
            csrf: boot.csrf,
            restart: restart,
            onProgress: baseProgress,
            onDone: function (j) {
                baseRunning = false;
                baseBox.classList.add('is-done');
                baseBox.querySelector('[data-cfg-base-title]').textContent = 'Catalogue de base prêt';
                baseBox.querySelector('[data-cfg-base-detail]').textContent = (j.total || 0).toLocaleString('fr-FR') + ' objets d’Arma 3 et de ses DLC : masses, contenances et compatibilités vérifiées.';
                window.setTimeout(function () { baseBox.hidden = true; }, 5000);
                loadCatalog().then(function () { if (catDlg.open) { renderCatalogInfo(); } });
            },
            onError: function (msg) {
                baseRunning = false;
                baseBox.classList.add('is-error');
                baseBox.querySelector('[data-cfg-base-title]').textContent = 'Catalogue de base pas encore chargé';
                baseBox.querySelector('[data-cfg-base-detail]').textContent = msg;
                baseBox.querySelector('[data-cfg-base-retry]').hidden = false;
                filterPalette();
            }
        });
    }
    function reloadBase() {
        if (!window.confirm('Recharger le catalogue de base d’Arma 3 ? Les objets importés du jeu ne sont pas touchés.')) { return; }
        runBase(true);
    }
    baseBox.querySelector('[data-cfg-base-retry]').addEventListener('click', function () { runBase(false); });

    /** Objet envoyé depuis l'espace Matériel : seul en tête de la palette, dans la vue où il se pose (rien n'est posé d'office). */
    function showItem(cls) {
        var s = spec(cls);
        if (!s) { toast('« ' + cls + ' » n’est pas dans le catalogue de la communauté.', 'warn'); return; }
        var weapon = ['primary', 'handgun', 'secondary'].indexOf(s.k) >= 0;
        S.mode = weapon ? 'bench' : 'outfit';
        store('athena.cfg.mode', S.mode);
        P.tab[S.mode] = weapon ? 'weapons' : 'all';
        P.cat = '';
        P.fav = false;
        P.trend = false;
        P.q = s.c;
        $('[data-cfg-search]').value = s.c;
        renderTabs();
        filterPalette();
        renderAll();
        toast('« ' + nameOf(s.c) + ' » est en tête de la palette : ' + (weapon ? 'glissez-le sur l’établi ou cliquez sur ＋.' : 'glissez-le sur la tenue ou cliquez sur ＋.'));
    }

    // ------------------------------------------------------------------ thème et démarrage
    var THEME_LABEL = { auto: 'Thème : auto', dark: 'Thème : sombre', light: 'Thème : clair' };
    function applyTheme(t) {
        root.setAttribute('data-cfg-theme', t);
        var b = $('[data-cfg-act="theme"]');
        if (b && b.firstChild) { b.firstChild.nodeValue = THEME_LABEL[t] + ' '; }
    }
    function cycleTheme() {
        var cur = root.getAttribute('data-cfg-theme') || 'auto';
        var next = { auto: 'dark', dark: 'light', light: 'auto' }[cur];
        applyTheme(next);
        store('athena.cfg.theme', next);
    }
    applyTheme(['auto', 'dark', 'light'].indexOf(store('athena.cfg.theme')) >= 0 ? store('athena.cfg.theme') : 'auto');
    S.mode = store('athena.cfg.mode') === 'outfit' ? 'outfit' : 'bench';

    renderTabs();
    filterPalette();
    renderAll();
    if (boot.base && boot.base.available && boot.base.needed) { runBase(false); }
    loadCatalog().then(function () {
        // Favoris, pré-kits et tendances : après le catalogue (noms, poids), sans bloquer la page.
        loadMember().then(function () { if (dlg('cfg-prekits').open) { renderPrekits(); } });
        var restored = false;
        if (boot.open) {
            loadSource(boot.open, 'a');
        } else {
            var raw = store('athena.cfg.draft.v1');
            try {
                var d = raw ? JSON.parse(raw) : null;
                if (d && d.outfits && d.outfits.a && (outfitClasses(d.outfits.a).length || (d.outfits.b && outfitClasses(d.outfits.b).length))) {
                    S.outfits = d.outfits;
                    restored = true;
                    renderTabs();
                    filterPalette();
                    renderAll();
                    toast('Brouillon de votre dernière session restauré. « Plus › Nouvelle tenue » pour repartir de zéro.');
                }
            } catch (err) { /* brouillon illisible : ignoré */ }
        }
        if (boot.pick) { openSources('a'); return; }
        if (boot.item) { showItem(boot.item); return; }
        if (store(TOUR_KEY) !== '1' && !restored && !boot.open) { openTour(0); }
    });
})();
