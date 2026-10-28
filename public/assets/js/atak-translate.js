/*
 * Atelier de traduction de l'ATAK natif (/traduire-atak, /traduire-atak/p/{pack}).
 * Modèle : GET /traduire-atak/modele.json ([clé, français, anglais, catégorie, fichier], tiré de
 * localisation/template/xx.txt et categories.json). Travail gardé sur l'appareil (localStorage) ou dans un pack
 * partagé (API /api/traduire-atak/packs : lien secret, pseudo libre, propositions, historique, synchronisation par
 * numéro de version). Vérifié à la frappe, puis mod fabriqué sur place : PBO (format BI, en-tête « prefix », SHA-1
 * final), mod.cpp, image d'aperçu, description Workshop, zip. Mêmes règles de fichier que tools/i18n.py (community)
 * et fn_langApply.sqf.
 */
(function () {
    'use strict';

    var LINE = /\[\s*"((?:[^"]|"")*)"\s*,\s*"((?:[^"]|"")*)"\s*(?:,\s*"(?:[^"]|"")*"\s*)?\]/g;
    var GAME_BY_CODE = { es: 'Spanish', de: 'German', it: 'Italian', pl: 'Polish', pt: 'Portuguese', ru: 'Russian', cs: 'Czech', tr: 'Turkish', zh: 'Chinesesimp', ja: 'Japanese', ko: 'Korean' };

    // ---------- Règles de vérification (exportées pour les tests) ----------

    function placeholders(s) {
        var out = [];
        var lone = 0;
        var pct = 0;
        for (var i = 0; i < s.length; i++) {
            if (s.charAt(i) !== '%') { continue; }
            var d = s.charAt(i + 1);
            if (d === '%') { pct++; i++; } else if (d >= '0' && d <= '9') {
                var j = i + 1;
                while (j < s.length && s.charAt(j) >= '0' && s.charAt(j) <= '9') { j++; }
                out.push('%' + s.slice(i + 1, j));
                i = j - 1;
            } else { lone++; }
        }
        out.sort();
        return { ids: out, lone: lone, pct: pct };
    }

    function tags(s) {
        return (s.match(/<\/?[a-zA-Z][^>]*>/g) || []).map(function (x) { return x.replace(/\s+/g, ' ').replace(/=(["'])[^"']*\1/g, '=').toLowerCase(); }).sort().join('|');
    }

    function edges(s) {
        return [/^\s*/.exec(s)[0].length > 0, /\s*$/.exec(s)[0].length > 0];
    }

    /** Problèmes d'une traduction : {errors: [[clé, valeurs]], warnings: [...]}. Vide si pas traduite. */
    function check(fr, tr, en) {
        var res = { errors: [], warnings: [] };
        if (!tr) { return res; }
        var a = placeholders(fr);
        var b = placeholders(tr);
        if (a.ids.join(',') !== b.ids.join(',')) {
            res.errors.push(['err_placeholders', { expected: a.ids.join(' ') || '-', found: b.ids.join(' ') || '-' }]);
        }
        if (/[\r\n]/.test(tr)) { res.errors.push(['err_newline', {}]); }
        var ea = edges(fr);
        var eb = edges(tr);
        if (ea[0] !== eb[0] || ea[1] !== eb[1]) { res.warnings.push(['warn_space', {}]); }
        if (tags(fr) !== tags(tr)) { res.warnings.push(['warn_tags', {}]); }
        if (a.pct !== b.pct) { res.warnings.push(['warn_percent', {}]); }
        if (a.ids.length > 0 && b.lone > a.lone) { res.warnings.push(['warn_lone_percent', {}]); }
        // Nettement plus long que l'anglais : risque de texte coupé sur l'écran du téléphone.
        var ref = en || fr;
        if (ref.length >= 8 && tr.length > ref.length * 1.6 + 4) { res.warnings.push(['warn_long', { pct: Math.round((tr.length / ref.length - 1) * 100) }]); }
        return res;
    }

    /** Lignes ["français", "traduction", "anglais"] d'un fichier (règles de load_community, tools/i18n.py). */
    function parseCommunity(txt) {
        var out = {};
        var n = 0;
        var m;
        LINE.lastIndex = 0;
        txt = txt.replace(/^\uFEFF/, '');
        while ((m = LINE.exec(txt)) !== null) {
            out[m[1].replace(/""/g, '"')] = m[2].replace(/""/g, '"');
            n++;
        }
        return { map: out, lines: n };
    }

    function q(s) { return '"' + String(s).replace(/"/g, '""') + '"'; }

    /**
     * Fichier de traduction, dans l'ordre du modèle ({h, fr, en} ; tr : {clé: texte}). Les phrases en erreur sont
     * écrites vides (elles restent en anglais dans le jeu).
     */
    function buildTxt(entries, tr) {
        var lines = [];
        var skipped = 0;
        for (var i = 0; i < entries.length; i++) {
            var e = entries[i];
            var t = tr[e.h] || '';
            if (t && check(e.fr, t).errors.length) { t = ''; skipped++; }
            lines.push('[' + q(e.fr) + ', ' + q(t) + ', ' + q(e.en) + ']');
        }
        return { text: '[\n' + lines.join(',\n') + '\n]\n', skipped: skipped };
    }

    function cfgStr(s) { return String(s).replace(/[\r\n]+/g, ' ').replace(/"/g, '""'); }

    function buildConfig(o) {
        var games = o.game ? '{"' + cfgStr(o.game) + '"}' : '{}';
        return '// COMSPEC ATAK translation mod: ' + cfgStr(o.name) + ' (' + o.code + '). Made with ' + o.site + '\n'
            + '// Template: COMSPEC ATAK Native ' + (o.version || '?') + '.\n'
            + 'class CfgPatches {\n'
            + '    class comspec_atak_lang_' + o.code + ' {\n'
            + '        name = "COMSPEC ATAK - ' + cfgStr(o.name) + '";\n'
            + '        author = "' + cfgStr(o.author || 'COMSPEC community') + '";\n'
            + '        requiredVersion = 2.14;\n'
            + '        // Loaded after COMSPEC ATAK Native.\n'
            + '        requiredAddons[] = {"comspec_atak_native_main"};\n'
            + '        units[] = {};\n'
            + '        weapons[] = {};\n'
            + '    };\n'
            + '};\n'
            + 'class CfgComspecLanguages {\n'
            + '    class ' + o.code + ' {\n'
            + '        name = "' + cfgStr(o.name) + '";\n'
            + '        file = "\\comspec_atak_lang_' + o.code + '\\' + o.code + '.txt";\n'
            + '        gameLanguage[] = ' + games + ';\n'
            + '    };\n'
            + '};\n';
    }

    // Colonnes de stringtable d'Arma 3 qu'un mod de langue peut remplir (valeurs de la commande language).
    var GAME_COLUMNS = ['German', 'Italian', 'Spanish', 'Czech', 'Polish', 'Russian', 'Portuguese', 'Turkish', 'Japanese', 'Korean', 'Chinese', 'Chinesesimp'];

    function xmlStr(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

    // stringtable.xml du mod : noms d'objets et de modules Eden/Zeus (ATAK, SSE, Overwatch connect) dans la colonne
    // de la langue du jeu ; '' sans langue du jeu reconnue ou sans nom traduit. Même sortie que
    // AtakTranslationModBuilder::buildStringtable côté serveur.
    function buildStringtable(keys, byText, code, game) {
        if (GAME_COLUMNS.indexOf(game) < 0) { return ''; }
        var rows = [];
        (keys || []).forEach(function (k) {
            var t = byText[k[1]] || '';
            if (!t || !/^[A-Za-z0-9_]+$/.test(k[0]) || check(k[1], t).errors.length) { return; }
            rows.push('        <Key ID="' + k[0] + '">\n'
                + '            <Original>' + xmlStr(k[1]) + '</Original>\n'
                + '            <English>' + xmlStr(k[2] || k[1]) + '</English>\n'
                + '            <' + game + '>' + xmlStr(t) + '</' + game + '>\n'
                + '        </Key>');
        });
        if (!rows.length) { return ''; }
        return '<?xml version="1.0" encoding="utf-8"?>\n<Project name="comspec_atak_lang_' + code + '">\n    <Package name="main">\n'
            + rows.join('\n') + '\n    </Package>\n</Project>\n';
    }

    function buildModCpp(o) {
        return 'name = "COMSPEC ATAK - ' + cfgStr(o.name) + '";\n'
            + 'author = "' + cfgStr(o.author || 'COMSPEC community') + '";\n'
            + 'tooltip = "COMSPEC ATAK - ' + cfgStr(o.name) + '";\n'
            + 'overview = "' + cfgStr(o.desc) + '";\n';
    }

    function buildVdf(o) {
        return '"workshopitem"\n{\n'
            + '\t"appid"\t\t"107410"\n'
            + '\t"contentfolder"\t"C:\\\\path\\\\to\\\\' + o.folder + '"\n'
            + '\t"previewfile"\t"C:\\\\path\\\\to\\\\' + o.folder + '\\\\preview.jpg"\n'
            + '\t"visibility"\t"0"\n'
            + '\t"title"\t\t"COMSPEC ATAK - ' + o.name.replace(/"/g, "'") + '"\n'
            + '\t"description"\t"' + o.desc.replace(/"/g, "'") + '"\n'
            + '\t"changenote"\t"COMSPEC ATAK Native ' + (o.version || '') + '"\n'
            + '}\n';
    }

    // ---------- Binaire : SHA-1, PBO, CRC-32, zip ----------

    var enc = new TextEncoder();

    function concat(parts) {
        var len = 0;
        var i;
        for (i = 0; i < parts.length; i++) { len += parts[i].length; }
        var out = new Uint8Array(len);
        var off = 0;
        for (i = 0; i < parts.length; i++) { out.set(parts[i], off); off += parts[i].length; }
        return out;
    }

    function u32(n) { return new Uint8Array([n & 255, (n >>> 8) & 255, (n >>> 16) & 255, (n >>> 24) & 255]); }
    function u16(n) { return new Uint8Array([n & 255, (n >>> 8) & 255]); }
    function cstr(s) { return concat([enc.encode(s), new Uint8Array([0])]); }

    function sha1(bytes) {
        var h0 = 0x67452301, h1 = 0xEFCDAB89, h2 = 0x98BADCFE, h3 = 0x10325476, h4 = 0xC3D2E1F0;
        var ml = bytes.length;
        var withPad = ((ml + 9 + 63) >> 6) << 6;
        var buf = new Uint8Array(withPad);
        buf.set(bytes);
        buf[ml] = 0x80;
        var bits = ml * 8;
        var dv = new DataView(buf.buffer);
        dv.setUint32(withPad - 4, bits >>> 0);
        dv.setUint32(withPad - 8, Math.floor(bits / 4294967296));
        var w = new Uint32Array(80);
        for (var off = 0; off < withPad; off += 64) {
            for (var i = 0; i < 16; i++) { w[i] = dv.getUint32(off + i * 4); }
            for (i = 16; i < 80; i++) { var x = w[i - 3] ^ w[i - 8] ^ w[i - 14] ^ w[i - 16]; w[i] = (x << 1) | (x >>> 31); }
            var a = h0, b = h1, c = h2, d = h3, e = h4;
            for (i = 0; i < 80; i++) {
                var f, k;
                if (i < 20) { f = (b & c) | (~b & d); k = 0x5A827999; } else if (i < 40) { f = b ^ c ^ d; k = 0x6ED9EBA1; } else if (i < 60) { f = (b & c) | (b & d) | (c & d); k = 0x8F1BBCDC; } else { f = b ^ c ^ d; k = 0xCA62C1D6; }
                var t = (((a << 5) | (a >>> 27)) + f + e + k + w[i]) | 0;
                e = d; d = c; c = (b << 30) | (b >>> 2); b = a; a = t;
            }
            h0 = (h0 + a) | 0; h1 = (h1 + b) | 0; h2 = (h2 + c) | 0; h3 = (h3 + d) | 0; h4 = (h4 + e) | 0;
        }
        var out = new Uint8Array(20);
        var odv = new DataView(out.buffer);
        [h0, h1, h2, h3, h4].forEach(function (v, j) { odv.setUint32(j * 4, v >>> 0); });
        return out;
    }

    /** PBO non compressé : en-tête « Vers » + prefix, entrées, entrée vide, données, 0x00 + SHA-1 du tout. */
    function buildPbo(prefix, files, time) {
        var parts = [new Uint8Array([0]), u32(0x56657273), u32(0), u32(0), u32(0), u32(0), cstr('prefix'), cstr(prefix), new Uint8Array([0])];
        files.forEach(function (f) {
            parts.push(cstr(f.name), u32(0), u32(f.data.length), u32(0), u32(time), u32(f.data.length));
        });
        parts.push(new Uint8Array([0]), u32(0), u32(0), u32(0), u32(0), u32(0));
        files.forEach(function (f) { parts.push(f.data); });
        var body = concat(parts);
        return concat([body, new Uint8Array([0]), sha1(body)]);
    }

    var CRC = (function () {
        var t = new Uint32Array(256);
        for (var n = 0; n < 256; n++) {
            var c = n;
            for (var k = 0; k < 8; k++) { c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1); }
            t[n] = c >>> 0;
        }
        return t;
    })();

    function crc32(bytes) {
        var c = 0xFFFFFFFF;
        for (var i = 0; i < bytes.length; i++) { c = CRC[(c ^ bytes[i]) & 255] ^ (c >>> 8); }
        return (c ^ 0xFFFFFFFF) >>> 0;
    }

    function deflate(bytes) {
        if (typeof CompressionStream === 'undefined') { return Promise.resolve(null); }
        try {
            var cs = new CompressionStream('deflate-raw');
            var stream = new Blob([bytes]).stream().pipeThrough(cs);
            return new Response(stream).arrayBuffer().then(function (b) { return new Uint8Array(b); }, function () { return null; });
        } catch (e) {
            return Promise.resolve(null);
        }
    }

    /** Zip (deflate si le navigateur sait le faire, sinon stocké) : [{name, data}] -> Uint8Array. */
    function buildZip(files, date) {
        var dt = date || new Date();
        var dosTime = (dt.getHours() << 11) | (dt.getMinutes() << 5) | (dt.getSeconds() >> 1);
        var dosDate = ((dt.getFullYear() - 1980) << 9) | ((dt.getMonth() + 1) << 5) | dt.getDate();
        return Promise.all(files.map(function (f) { return f.store ? Promise.resolve(null) : deflate(f.data); })).then(function (packed) {
            var locals = [];
            var central = [];
            var offset = 0;
            files.forEach(function (f, i) {
                var name = enc.encode(f.name);
                var crc = crc32(f.data);
                var comp = packed[i] && packed[i].length < f.data.length ? packed[i] : null;
                var method = comp ? 8 : 0;
                var data = comp || f.data;
                var head = concat([u32(0x04034b50), u16(20), u16(0x0800), u16(method), u16(dosTime), u16(dosDate), u32(crc), u32(data.length), u32(f.data.length), u16(name.length), u16(0), name]);
                locals.push(head, data);
                central.push(concat([u32(0x02014b50), u16(20), u16(20), u16(0x0800), u16(method), u16(dosTime), u16(dosDate), u32(crc), u32(data.length), u32(f.data.length), u16(name.length), u16(0), u16(0), u16(0), u16(0), u32(0), u32(offset), name]));
                offset += head.length + data.length;
            });
            var cd = concat(central);
            var end = concat([u32(0x06054b50), u16(0), u16(0), u16(files.length), u16(files.length), u32(cd.length), u32(offset), u16(0)]);
            return concat(locals.concat([cd, end]));
        });
    }

    var api = { placeholders: placeholders, check: check, parseCommunity: parseCommunity, buildTxt: buildTxt, buildConfig: buildConfig, buildStringtable: buildStringtable, buildModCpp: buildModCpp, buildVdf: buildVdf, buildPbo: buildPbo, buildZip: buildZip, sha1: sha1, crc32: crc32, renderRich: renderRich };
    if (typeof module !== 'undefined' && module.exports) { module.exports = api; }
    if (typeof document === 'undefined') { return; }

    // ---------- Page ----------

    var root = document.getElementById('atakt');
    if (!root) { return; }
    var L = {};
    try { L = JSON.parse(root.getAttribute('data-i18n') || '{}'); } catch (e) { L = {}; }
    var EN_UI = (document.documentElement.lang || 'fr').slice(0, 2) !== 'fr';
    var API = root.getAttribute('data-api-url');
    var PAGE = root.getAttribute('data-page-url');
    var ACCOUNT = root.getAttribute('data-account') === '1';
    var ACCOUNT_URL = root.getAttribute('data-account-url') || '';
    var CSRF = root.getAttribute('data-csrf') || '';
    var MINE = [];
    var fmt = new Intl.NumberFormat(document.documentElement.lang || undefined);
    var rtf = typeof Intl.RelativeTimeFormat === 'function' ? new Intl.RelativeTimeFormat(document.documentElement.lang || undefined, { numeric: 'auto' }) : null;

    function tx(key, vars) {
        var s = L[key] || key;
        if (vars) { Object.keys(vars).forEach(function (k) { s = s.split('{' + k + '}').join(String(vars[k])); }); }
        return s;
    }
    function $(id) { return document.getElementById(id); }
    function el(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) { e.className = cls; }
        if (text !== undefined && text !== null) { e.textContent = text; }
        return e;
    }
    function btn(cls, text, onClick) {
        var b = el('button', cls, text);
        b.type = 'button';
        if (onClick) { b.addEventListener('click', onClick); }
        return b;
    }
    function ago(sec) {
        if (!sec) { return ''; }
        var d = Math.round(sec - Date.now() / 1000);
        if (!rtf) { return new Date(sec * 1000).toLocaleString(); }
        var a = Math.abs(d);
        if (a < 60) { return rtf.format(Math.round(d), 'second'); }
        if (a < 3600) { return rtf.format(Math.round(d / 60), 'minute'); }
        if (a < 86400) { return rtf.format(Math.round(d / 3600), 'hour'); }
        return rtf.format(Math.round(d / 86400), 'day');
    }
    function fold(s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
    function validCode(c) { return /^[a-z]{2}$/.test(c) && c !== 'fr' && c !== 'en'; }

    function storageGet(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } }
    function storageSet(k, v) { try { window.localStorage.setItem(k, v); return true; } catch (e) { return false; } }
    function storageDel(k) { try { window.localStorage.removeItem(k); } catch (e) { /* rien */ } }
    function storageJson(k, def) { var r = storageGet(k); if (!r) { return def; } try { return JSON.parse(r); } catch (e) { return def; } }

    var STORE2 = 'comspec-atakt:v2:';
    var PACKS_KEY = 'comspec-atakt:packs';
    var PSEUDO_KEY = 'comspec-atakt:pseudo';
    var PREFS_KEY = 'comspec-atakt:prefs';
    var POLL_MS = 15000;

    var S = {
        entries: [],
        byH: {},
        byFr: {},
        cats: {},
        version: root.getAttribute('data-version') || '',
        tr: {},
        issues: {},
        meta: { code: '', name: '', game: '', author: '' },
        pack: null,          // {id, token, role, meta, rev, strRev:{}, by:{}, at:{}, sugg:{h: [..]}, people: []}
        pending: {},         // clé -> {timer, text} : écriture en attente d'envoi (pack)
        conflicts: {},       // clé -> {text, by, rev}
        rowState: {},        // clé -> 'saving' | 'saved' | 'suggested' | 'error'
        filter: 'todo',
        cat: '',
        query: '',
        page: 0,
        view: [],
        source: 'en',
        previewAll: false,
        pseudo: storageGet(PSEUDO_KEY) || '',
        catQuery: '',
        workshop: null,      // état du pack dans le Workshop des langues (API show, premier chargement)
        newKeys: {},         // clé -> true : phrases ajoutées par le mod depuis la dernière version publiée
    };
    var prefs = storageJson(PREFS_KEY, {});
    if (prefs.source === 'fr') { S.source = 'fr'; }
    if (prefs.previewAll) { S.previewAll = true; }

    // ---------- Données ----------

    function isReviewer() { return !!(S.pack && S.pack.role !== 'owner' && S.pack.meta.review_mode === 'review'); }
    function myName() { return S.pseudo || tx('anonymous'); }
    function mySuggestion(h) {
        var list = (S.pack && S.pack.sugg[h]) || [];
        for (var i = 0; i < list.length; i++) { if (list[i].by === myName()) { return list[i]; } }
        return null;
    }
    /** Texte montré dans la zone de saisie : la traduction, ou pour un contributeur en revue sa proposition. */
    function editValue(h) {
        if (isReviewer()) { var s = mySuggestion(h); if (s) { return s.text; } }
        return S.tr[h] || '';
    }

    function recheck(h) {
        var e = S.byH[h];
        var v = editValue(h);
        var r = check(e.fr, v, e.en);
        if (r.errors.length || r.warnings.length) { S.issues[h] = r; } else { delete S.issues[h]; }
    }
    function recheckAll() { S.issues = {}; S.entries.forEach(function (e) { if (editValue(e.h)) { recheck(e.h); } }); }

    function status(h) {
        if (S.conflicts[h]) { return 'conflict'; }
        var r = S.issues[h];
        if (r && r.errors.length) { return 'error'; }
        if (S.pack && (S.pack.sugg[h] || []).length && !isReviewer()) { return 'sugg'; }
        if (isReviewer() && mySuggestion(h)) { return 'mine'; }
        if (r && r.warnings.length) { return 'warn'; }
        return S.tr[h] ? 'done' : 'todo';
    }

    function hasEnglish(e) {
        var en = (e.en || '').trim();
        return en !== '' && fold(en) !== fold(e.fr);
    }

    function counts() {
        var c = { total: 0, done: 0, todo: 0, blank: 0, error: 0, warn: 0, sugg: 0, conflict: 0, mine: 0, cats: {} };
        S.entries.forEach(function (e) {
            c.total++;
            var k = c.cats[e.cat] || (c.cats[e.cat] = { total: 0, done: 0, shipped: 0 });
            k.total++;
            if (hasEnglish(e)) { k.shipped++; } else if (!S.tr[e.h]) { c.blank++; }
            if (S.tr[e.h]) { c.done++; k.done++; } else { c.todo++; }
            var st = status(e.h);
            if (st === 'error' || st === 'sugg' || st === 'conflict' || st === 'mine') { c[st]++; }
            if (st !== 'error' && S.issues[e.h] && S.issues[e.h].warnings.length) { c.warn++; }
        });
        return c;
    }

    function matches(e) {
        if (S.cat && e.cat !== S.cat) { return false; }
        var st = status(e.h);
        switch (S.filter) {
        case 'todo': if (S.tr[e.h]) { return false; } break;
        case 'blank': if (S.tr[e.h] || hasEnglish(e)) { return false; } break;
        case 'done': if (!S.tr[e.h]) { return false; } break;
        case 'error': if (st !== 'error') { return false; } break;
        case 'warn': if (!S.issues[e.h] || !S.issues[e.h].warnings.length || st === 'error') { return false; } break;
        case 'sugg': if (st !== 'sugg' && st !== 'mine') { return false; } break;
        case 'conflict': if (st !== 'conflict') { return false; } break;
        case 'new': if (!S.newKeys[e.h]) { return false; } break;
        default: break;
        }
        if (!S.query) { return true; }
        return e.norm.indexOf(S.query) >= 0 || fold(editValue(e.h)).indexOf(S.query) >= 0;
    }

    // ---------- Sauvegarde locale ----------

    var saveTimer = null;
    function scheduleLocalSave() { clearTimeout(saveTimer); saveTimer = setTimeout(saveLocal, 500); }
    function saveLocal() {
        if (!validCode(S.meta.code)) { return; }
        if (S.pack && !S.pack.account) { return; }
        var ok = storageSet(STORE2 + S.meta.code, JSON.stringify({ meta: S.meta, tr: S.tr, at: Date.now() }));
        storageSet(STORE2 + 'last', S.meta.code);
        if (!S.pack) { setSync(ok ? tx('saved_local') : tx('save_failed'), ok ? 'ok' : 'bad'); }
    }
    /** Travail local d'une langue : format v2 (clés), ou v1 (textes français) converti. */
    function loadLocal(code) {
        var v2 = storageJson(STORE2 + code, null);
        if (v2 && v2.tr) { return v2; }
        var v1 = storageJson('comspec-atakt:v1:' + code, null);
        if (!v1 || !v1.tr) { return null; }
        var tr = {};
        Object.keys(v1.tr).forEach(function (fr) { var e = S.byFr[fr]; if (e && v1.tr[fr]) { tr[e.h] = v1.tr[fr]; } });
        return { meta: v1.meta || { code: code }, tr: tr };
    }
    function myPacks() { var l = storageJson(PACKS_KEY, []); return Array.isArray(l) ? l : []; }
    function rememberPack(p) {
        var list = myPacks().filter(function (x) { return x.id !== p.id; });
        list.unshift(p);
        storageSet(PACKS_KEY, JSON.stringify(list.slice(0, 20)));
    }
    function forgetPack(id) { storageSet(PACKS_KEY, JSON.stringify(myPacks().filter(function (x) { return x.id !== id; }))); }

    // ---------- API des packs ----------

    function call(method, path, body) {
        var account = !!(S.pack && S.pack.account);
        var opt = { method: method, headers: { Accept: 'application/json' }, credentials: account ? 'same-origin' : 'omit' };
        if (account) {
            opt.headers['X-Account-Pack'] = '1';
            if (method !== 'GET') { opt.headers['X-CSRF-Token'] = CSRF; }
        } else {
            opt.headers['X-Pack-Token'] = S.pack ? S.pack.token : '';
        }
        if (body !== undefined) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
        return fetch(API + path, opt).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) { j.httpStatus = r.status; return j; });
        });
    }
    function accountFetch(method, body) {
        var opt = { method: method, headers: { Accept: 'application/json' }, credentials: 'same-origin' };
        if (method !== 'GET') { opt.headers['X-CSRF-Token'] = CSRF; }
        if (body !== undefined) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
        return fetch(ACCOUNT_URL, opt).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) { j.httpStatus = r.status; return j; });
        });
    }
    function apiError(j) {
        var code = (j && j.error) || 'network';
        return tx('api_' + code) !== 'api_' + code ? tx('api_' + code) : tx('api_network');
    }
    function packLink(role) {
        if (!S.pack) { return ''; }
        var token = role === 'owner' ? S.pack.token : S.pack.editToken;
        if (!token) { return ''; }
        return window.location.origin + PAGE + '/p/' + S.pack.id + '#' + (role === 'owner' ? 'o' : 'k') + '=' + token;
    }

    function applyChanges(j) {
        var p = S.pack;
        p.meta = j.pack;
        p.role = j.role;
        p.people = j.contributors || [];
        S.meta = { code: j.pack.code, name: j.pack.name, game: j.pack.game, author: j.pack.author_label };
        var touched = {};
        (j.strings || []).forEach(function (s) {
            p.strRev[s.h] = s.rev;
            p.by[s.h] = s.by;
            p.at[s.h] = s.at;
            if (S.pending[s.h]) { return; }
            if (S.conflicts[s.h] && S.conflicts[s.h].rev < s.rev) { S.conflicts[s.h] = { text: s.text, by: s.by, rev: s.rev }; }
            if (s.text) { S.tr[s.h] = s.text; } else { delete S.tr[s.h]; }
            touched[s.h] = true;
        });
        (j.suggestions || []).forEach(function (s) {
            var list = (p.sugg[s.h] || []).filter(function (x) { return x.id !== s.id; });
            if (s.status === 'pending') { list.push(s); }
            if (list.length) { p.sugg[s.h] = list; } else { delete p.sugg[s.h]; }
            touched[s.h] = true;
        });
        p.rev = Math.max(p.rev, j.rev || 0);
        Object.keys(touched).forEach(function (h) { if (S.byH[h]) { recheck(h); } });
        return Object.keys(touched);
    }

    var pollTimer = null;
    function poll() {
        clearTimeout(pollTimer);
        if (!S.pack) { return; }
        if (document.hidden) { pollTimer = setTimeout(poll, POLL_MS); return; }
        call('GET', '/' + S.pack.id + '?since=' + S.pack.rev).then(function (j) {
            if (!j.ok) {
                if (j.httpStatus === 404) { lostAccess(); return; }
                setSync(apiError(j), 'bad');
            } else {
                var changed = applyChanges(j);
                if (changed.length) { refreshRows(changed); renderSide(); renderBar(); }
                if (!Object.keys(S.pending).length) { setSync(tx('synced', { when: ago(Date.now() / 1000) }), 'ok'); }
            }
            pollTimer = setTimeout(poll, POLL_MS);
        }, function () { setSync(tx('api_network'), 'bad'); pollTimer = setTimeout(poll, POLL_MS * 2); });
    }
    document.addEventListener('visibilitychange', function () { if (!document.hidden && S.pack) { poll(); } });

    function lostAccess() {
        if (S.pack) { forgetPack(S.pack.id); }
        S.pack = null;
        showBanner(tx('lost_access'), 'bad');
        setSync(tx('lost_access_short'), 'bad');
    }

    function queueWrite(h, text) {
        var p = S.pending[h] || (S.pending[h] = {});
        p.text = text;
        clearTimeout(p.timer);
        S.rowState[h] = 'saving';
        setSync(tx('saving'), '');
        p.timer = setTimeout(function () { flush(h); }, 800);
    }
    function flush(h, force) {
        var p = S.pending[h];
        if (!p || !S.pack) { return; }
        clearTimeout(p.timer);
        var text = p.text;
        var base = force !== undefined ? force : (S.pack.strRev[h] || 0);
        p.inflight = true;
        call('POST', '/' + S.pack.id + '/strings', { h: h, text: text, base_rev: base, author: myName() }).then(function (j) {
            p.inflight = false;
            if (S.pending[h] !== p) { return; }
            if (p.text !== text) { flush(h); return; }
            delete S.pending[h];
            if (j.ok && j.suggested) {
                S.rowState[h] = 'suggested';
                // La proposition revient par la synchronisation ; on l'affiche tout de suite.
                var list = (S.pack.sugg[h] || []).filter(function (x) { return x.by !== myName(); });
                if (text) { list.push({ id: -1, h: h, text: text, by: myName(), status: 'pending', at: Date.now() / 1000 }); }
                if (list.length) { S.pack.sugg[h] = list; } else { delete S.pack.sugg[h]; }
            } else if (j.ok) {
                S.rowState[h] = 'saved';
                S.pack.strRev[h] = j.rev;
                S.pack.by[h] = myName();
                S.pack.at[h] = Date.now() / 1000;
                delete S.conflicts[h];
            } else if (j.httpStatus === 409 && j.current) {
                S.conflicts[h] = j.current;
                S.rowState[h] = 'error';
            } else {
                S.rowState[h] = 'error';
                if (j.httpStatus === 404) { lostAccess(); }
                setSync(apiError(j), 'bad');
                refreshRows([h]);
                return;
            }
            recheck(h);
            refreshRows([h]);
            renderSide();
            renderBar();
            if (!Object.keys(S.pending).length) { setSync(tx('synced', { when: ago(Date.now() / 1000) }), 'ok'); }
        }, function () {
            p.inflight = false;
            S.rowState[h] = 'error';
            setSync(tx('api_network'), 'bad');
            // Nouvel essai plus tard, le texte reste en attente.
            p.timer = setTimeout(function () { flush(h); }, 8000);
            refreshRows([h]);
        });
    }
    window.addEventListener('beforeunload', function (e) {
        if (Object.keys(S.pending).length) { e.preventDefault(); e.returnValue = ''; }
    });

    // ---------- Changement de texte ----------

    function setText(h, v) {
        if (S.pack) {
            if (!isReviewer()) { if (v) { S.tr[h] = v; } else { delete S.tr[h]; } }
            queueWrite(h, v);
            if (S.pack.account) { scheduleLocalSave(); }
        } else {
            if (v) { S.tr[h] = v; } else { delete S.tr[h]; }
            scheduleLocalSave();
        }
        recheck(h);
        scheduleSide();
    }

    // ---------- Rendu ----------

    var PAGE_SIZE = 20;

    function setSync(text, kind) {
        var s = $('tw-sync');
        s.textContent = text || '';
        s.className = 'tw-sync' + (kind ? ' is-' + kind : '');
    }
    function showBanner(text, kind, actions) {
        var b = $('tw-banner');
        b.textContent = '';
        if (!text) { b.hidden = true; return; }
        b.hidden = false;
        b.className = 'tw-banner' + (kind ? ' is-' + kind : '');
        b.appendChild(el('span', '', text));
        (actions || []).forEach(function (a) { b.appendChild(btn('tw-link', a[0], a[1])); });
    }

    function catLabel(id) { var c = S.cats[id]; return c ? (EN_UI ? c.en : c.fr) : id; }

    function renderBar() {
        var c = counts();
        var pct = c.total ? Math.floor((c.done / c.total) * 1000) / 10 : 0;
        $('tw-bar').style.width = pct + '%';
        $('tw-pct').textContent = fmt.format(pct) + ' %';
        $('tw-code').textContent = (S.meta.code || '··').toUpperCase();
        $('tw-name').textContent = S.meta.name || tx('no_language');
        var mode = tx('mode_local');
        if (S.pack && S.pack.account) { mode = tx('mode_account'); }
        else if (S.pack) { mode = (S.pack.role === 'owner' ? tx('mode_pack_owner') : tx('mode_pack_editor')) + (S.pack.meta.review_mode === 'review' ? ' · ' + tx('mode_review') : ''); }
        $('tw-mode').textContent = mode;
        renderTree();
        return c;
    }

    var sideTimer = null;
    function scheduleSide() { clearTimeout(sideTimer); sideTimer = setTimeout(function () { renderSide(); renderBar(); }, 300); }

    function ring(pct) {
        var r = 34, c = 2 * Math.PI * r;
        var ns = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 84 84');
        svg.setAttribute('class', 'tw-ring__svg');
        svg.setAttribute('aria-hidden', 'true');
        [['tw-ring__bg', c], ['tw-ring__fg', c * (1 - pct / 100)]].forEach(function (x) {
            var ci = document.createElementNS(ns, 'circle');
            ci.setAttribute('cx', '42'); ci.setAttribute('cy', '42'); ci.setAttribute('r', String(r));
            ci.setAttribute('class', x[0]);
            ci.setAttribute('stroke-dasharray', String(c));
            ci.setAttribute('stroke-dashoffset', String(x[1]));
            svg.appendChild(ci);
        });
        return svg;
    }

    function renderSide() {
        var c = counts();
        var pct = c.total ? Math.floor((c.done / c.total) * 1000) / 10 : 0;
        var rb = $('tw-ring');
        rb.textContent = '';
        rb.appendChild(ring(pct));
        var txt = el('div', 'tw-ring__txt');
        txt.appendChild(el('strong', '', fmt.format(pct) + ' %'));
        txt.appendChild(el('span', '', tx('ring_done', { done: fmt.format(c.done), total: fmt.format(c.total) })));
        rb.appendChild(txt);

        var filters = [['blank', c.blank], ['todo', c.todo], ['all', c.total], ['done', c.done]];
        if (S.pack) { filters.push(['sugg', isReviewer() ? c.mine : c.sugg]); }
        filters.push(['error', c.error], ['warn', c.warn]);
        if (c.conflict) { filters.push(['conflict', c.conflict]); }
        var nNew = Object.keys(S.newKeys).length;
        if (nNew) { filters.splice(1, 0, ['new', nNew]); }
        var ul = $('tw-filters');
        ul.textContent = '';
        filters.forEach(function (f) {
            var li = el('li');
            var b = btn('tw-filter tw-filter--' + f[0], '', function () { S.filter = f[0]; rebuild(); renderSide(); });
            b.setAttribute('aria-pressed', S.filter === f[0] ? 'true' : 'false');
            b.appendChild(el('span', '', tx('filter_' + (f[0] === 'sugg' && isReviewer() ? 'mine' : f[0]))));
            b.appendChild(el('b', '', fmt.format(f[1])));
            li.appendChild(b);
            ul.appendChild(li);
        });
        $('tw-fold-label').textContent = tx('filter_' + (S.filter === 'sugg' && isReviewer() ? 'mine' : S.filter)) + (S.cat ? ' · ' + catLabel(S.cat) : '');

        var cats = $('tw-cats');
        cats.textContent = '';
        var ids = Object.keys(c.cats).sort(function (a, b) {
            var ka = c.cats[a], kb = c.cats[b];
            var aBare = ka.shipped === 0, bBare = kb.shipped === 0;
            if (aBare !== bBare) { return aBare ? -1 : 1; }
            var ap = ka.total ? ka.done / ka.total : 0;
            var bp = kb.total ? kb.done / kb.total : 0;
            if (ap !== bp) { return ap - bp; }
            return kb.total - ka.total;
        });
        var all = el('li');
        var ab = btn('tw-cat', '', function () { S.cat = ''; rebuild(); renderSide(); });
        ab.setAttribute('aria-pressed', S.cat === '' ? 'true' : 'false');
        ab.appendChild(el('span', 'tw-cat__name', tx('cat_all')));
        ab.appendChild(el('span', 'tw-cat__n', fmt.format(c.total)));
        all.appendChild(ab);
        cats.appendChild(all);
        ids.forEach(function (id) {
            var label = catLabel(id);
            if (S.catQuery && fold(label).indexOf(S.catQuery) < 0) { return; }
            var k = c.cats[id];
            var li = el('li');
            var b = btn('tw-cat', '', function () { S.cat = id; rebuild(); renderSide(); });
            b.setAttribute('aria-pressed', S.cat === id ? 'true' : 'false');
            b.appendChild(el('span', 'tw-cat__name', label));
            b.appendChild(el('span', 'tw-cat__n', fmt.format(k.done) + '/' + fmt.format(k.total)));
            if (k.shipped === 0) { b.appendChild(el('span', 'tw-cat__bare', tx('cat_no_en'))); }
            var bar = el('span', 'tw-cat__bar');
            var fill = el('i');
            fill.style.width = (k.total ? (k.done / k.total) * 100 : 0) + '%';
            bar.appendChild(fill);
            b.appendChild(bar);
            li.appendChild(b);
            cats.appendChild(li);
        });

        var box = $('tw-people-box');
        var people = (S.pack && S.pack.people) || [];
        box.hidden = !S.pack;
        var pl = $('tw-people');
        pl.textContent = '';
        if (S.pack && !people.length) { pl.appendChild(el('li', 'tw-muted', tx('people_none'))); }
        people.forEach(function (p) {
            var li = el('li');
            li.appendChild(el('span', 'tw-avatar', (p.name || '?').slice(0, 1).toUpperCase()));
            li.appendChild(el('span', '', p.name));
            li.appendChild(el('b', '', fmt.format(p.count)));
            pl.appendChild(li);
        });
    }

    function rebuild(keepPage) {
        S.view = [];
        for (var i = 0; i < S.entries.length; i++) { if (matches(S.entries[i])) { S.view.push(i); } }
        var pages = Math.max(1, Math.ceil(S.view.length / PAGE_SIZE));
        if (!keepPage) { S.page = 0; }
        if (S.page >= pages) { S.page = pages - 1; }
        renderList();
    }

    /** Texte « structuré » du téléphone (<t color>, <br/>, %1) en HTML sûr pour l'aperçu. */
    function renderRich(s) {
        var esc = String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        esc = esc.replace(/&lt;br\s*\/?&gt;/gi, '<br>');
        esc = esc.replace(/&lt;t\s+([^&]*?)&gt;/gi, function (m, attrs) {
            var c = /color\s*=\s*['"]?(#[0-9a-fA-F]{3,8})/.exec(attrs);
            return '<span class="tw-rich"' + (c ? ' style="color:' + c[1] + '"' : '') + '>';
        }).replace(/&lt;\/t&gt;/gi, '</span>');
        esc = esc.replace(/%%/g, '%').replace(/%(\d)/g, '<b class="tw-ph">$1</b>');
        var open = (esc.match(/<span/g) || []).length - (esc.match(/<\/span>/g) || []).length;
        while (open-- > 0) { esc += '</span>'; }
        return esc;
    }

    function chips(fr) {
        var ids = placeholders(fr).ids;
        if (!ids.length) { return null; }
        var w = el('span', 'tw-chips');
        ids.filter(function (x, i) { return ids.indexOf(x) === i; }).forEach(function (id) { w.appendChild(el('code', 'tw-chip', id)); });
        return w;
    }

    var STATUS_LABEL = { todo: 'st_todo', done: 'st_done', error: 'st_error', warn: 'st_warn', sugg: 'st_sugg', conflict: 'st_conflict', mine: 'st_mine' };

    function renderRow(i) {
        var e = S.entries[i];
        var h = e.h;
        var li = el('li', 'tw-row');
        li.setAttribute('data-h', h);
        li.setAttribute('data-status', status(h));
        var head = el('div', 'tw-row__head');
        head.appendChild(el('span', 'tw-num', '#' + (i + 1)));
        var catb = btn('tw-tag', catLabel(e.cat), function () { S.cat = e.cat; rebuild(); renderSide(); });
        catb.title = tx('cat_filter');
        head.appendChild(catb);
        head.appendChild(el('span', 'tw-st', tx(STATUS_LABEL[status(h)])));
        var info = el('span', 'tw-row__info');
        var rs = S.rowState[h];
        if (rs) { info.appendChild(el('span', 'tw-rs tw-rs--' + rs, tx('rs_' + rs))); }
        if (S.pack && S.pack.by[h] && S.tr[h]) { info.appendChild(el('span', '', tx('by_ago', { by: S.pack.by[h], when: ago(S.pack.at[h]) }))); }
        head.appendChild(info);
        li.appendChild(head);

        var cols = el('div', 'tw-row__cols');
        var src = el('div', 'tw-src');
        var main = S.source === 'fr' ? e.fr : (e.en || e.fr);
        var other = S.source === 'fr' ? e.en : e.fr;
        var p1 = el('p', 'tw-src__main');
        p1.appendChild(document.createTextNode(main));
        src.appendChild(p1);
        var p2 = el('p', 'tw-src__alt');
        p2.appendChild(el('span', 'tw-src__lab', S.source === 'fr' ? 'EN' : 'FR'));
        p2.appendChild(document.createTextNode(other || '—'));
        src.appendChild(p2);
        var ch = chips(e.fr);
        if (ch) { src.appendChild(ch); }
        var around = el('div', 'tw-context');
        around.appendChild(el('p', 'tw-src__lab', tx('context')));
        around.appendChild(el('p', 'tw-context__where', tx('context_app', { cat: catLabel(e.cat) })));
        if (e.file && e.file !== catLabel(e.cat) && !/[\\/]/.test(e.file) && !/\.(sqf|hpp|txt|xml)$/i.test(e.file)) {
            around.appendChild(el('p', 'tw-context__where', tx('context_screen', { screen: e.file })));
        }
        (e.around || []).forEach(function (line) {
            var p = el('p', 'tw-context__line', line);
            p.lang = 'fr';
            around.appendChild(p);
        });
        src.appendChild(around);
        cols.appendChild(src);

        var ed = el('div', 'tw-ed');
        if (isReviewer() && S.tr[h]) {
            var off = el('p', 'tw-official');
            off.appendChild(el('span', 'tw-src__lab', tx('official')));
            off.appendChild(document.createTextNode(S.tr[h]));
            ed.appendChild(off);
        }
        var id = 'tw-t' + h;
        var lab = el('label', 'tw-sr', tx('translation') + ' #' + (i + 1));
        lab.setAttribute('for', id);
        var ta = el('textarea', 'tw-input');
        ta.id = id;
        ta.rows = Math.min(6, Math.max(1, Math.ceil(Math.max(e.fr.length, e.en.length) / 64)));
        ta.placeholder = isReviewer() ? tx('placeholder_suggest') : tx('placeholder_tr');
        ta.value = editValue(h);
        ta.spellcheck = true;
        if (S.meta.code) { ta.lang = S.meta.code; }
        var meter = el('span', 'tw-meter');
        var issues = el('ul', 'tw-issues');
        var prev = el('div', 'tw-preview');
        function refreshMini() {
            var v = ta.value;
            var ref = (e.en || e.fr).length;
            meter.textContent = tx('meter', { n: v.length, ref: ref });
            meter.classList.toggle('is-warn', ref >= 8 && v.length > ref * 1.6 + 4);
            issues.textContent = '';
            var r = S.issues[h];
            if (r) {
                r.errors.forEach(function (x) { issues.appendChild(el('li', 'is-error', tx(x[0], x[1]))); });
                r.warnings.forEach(function (x) { issues.appendChild(el('li', 'is-warn', tx(x[0], x[1]))); });
            }
            if (!prev.hidden) { drawPreview(prev, e, v); }
        }
        ta.addEventListener('input', function () {
            setText(h, ta.value);
            li.setAttribute('data-status', status(h));
            refreshMini();
        });
        ta.addEventListener('keydown', function (ev) {
            if ((ev.ctrlKey || ev.metaKey) && ev.key === 'Enter') { ev.preventDefault(); if (S.pack && S.pending[h]) { flush(h); } moveFocus(h, 1); }
            if (ev.altKey && (ev.key === 'ArrowDown' || ev.key === 'ArrowUp')) { ev.preventDefault(); moveFocus(h, ev.key === 'ArrowDown' ? 1 : -1); }
        });
        ta.addEventListener('blur', function () { if (S.pack && S.pending[h] && !S.pending[h].inflight) { flush(h); } });
        ed.appendChild(lab);
        ed.appendChild(ta);

        if (S.conflicts[h]) {
            var cf = S.conflicts[h];
            var box = el('div', 'tw-conflict');
            box.appendChild(el('p', '', tx('conflict_text', { by: cf.by || '?' })));
            box.appendChild(el('blockquote', '', cf.text || tx('empty_text')));
            var row = el('div', 'tw-row__tools');
            row.appendChild(btn('tw-link', tx('conflict_mine'), function () {
                S.pending[h] = { text: ta.value };
                delete S.conflicts[h];
                flush(h, cf.rev);
            }));
            row.appendChild(btn('tw-link', tx('conflict_theirs'), function () {
                delete S.conflicts[h];
                if (cf.text) { S.tr[h] = cf.text; } else { delete S.tr[h]; }
                S.pack.strRev[h] = cf.rev;
                recheck(h);
                refreshRows([h]);
                scheduleSide();
            }));
            box.appendChild(row);
            ed.appendChild(box);
        }

        var tools = el('div', 'tw-row__tools');
        tools.appendChild(btn('tw-link', tx('copy_en'), function () { ta.value = e.en; ta.dispatchEvent(new Event('input')); ta.focus(); }));
        tools.appendChild(btn('tw-link', tx('clear'), function () { ta.value = ''; ta.dispatchEvent(new Event('input')); ta.focus(); }));
        var pv = btn('tw-link', tx('preview'), function () {
            prev.hidden = !prev.hidden;
            pv.setAttribute('aria-pressed', prev.hidden ? 'false' : 'true');
            if (!prev.hidden) { drawPreview(prev, e, ta.value); }
        });
        tools.appendChild(pv);
        if (S.pack) { tools.appendChild(btn('tw-link', tx('history'), function () { openHistory(e); })); }
        tools.appendChild(meter);
        ed.appendChild(tools);
        ed.appendChild(issues);

        var sugg = (S.pack && S.pack.sugg[h]) || [];
        if (sugg.length) {
            var sb = el('ul', 'tw-sugg');
            sugg.forEach(function (s) {
                var it = el('li');
                var top = el('p', 'tw-sugg__by', tx(s.by === myName() && isReviewer() ? 'sugg_mine' : 'sugg_by', { by: s.by, when: ago(s.at) }));
                it.appendChild(top);
                it.appendChild(el('blockquote', '', s.text));
                if (S.pack.role === 'owner' && s.id > 0) {
                    var acts = el('div', 'tw-row__tools');
                    acts.appendChild(btn('tw-btn tw-btn--ok', tx('accept'), function () { decide(s, true); }));
                    acts.appendChild(btn('tw-btn', tx('reject'), function () { decide(s, false); }));
                    acts.appendChild(btn('tw-link', tx('sugg_edit'), function () { ta.value = s.text; ta.dispatchEvent(new Event('input')); ta.focus(); }));
                    it.appendChild(acts);
                }
                sb.appendChild(it);
            });
            ed.appendChild(sb);
        }
        prev.hidden = !S.previewAll;
        ed.appendChild(prev);
        cols.appendChild(ed);
        li.appendChild(cols);
        refreshMini();
        return li;
    }

    function drawPreview(box, e, v) {
        box.textContent = '';
        var ph = el('div', 'tw-phone');
        var scr = el('div', 'tw-phone__screen');
        var line = el('div', 'tw-phone__line');
        line.innerHTML = renderRich(v || e.en || e.fr);
        scr.appendChild(line);
        if (v) {
            var ref = el('div', 'tw-phone__ref');
            ref.innerHTML = renderRich(e.en || e.fr);
            scr.appendChild(ref);
        }
        ph.appendChild(scr);
        box.appendChild(ph);
        box.appendChild(el('p', 'tw-muted', tx('context_app', { cat: catLabel(e.cat) }) + (e.file && e.file !== catLabel(e.cat) && !/[\\/]/.test(e.file) && !/\.(sqf|hpp|txt|xml)$/i.test(e.file) ? ' · ' + e.file : '')));
    }

    function moveFocus(h, dir) {
        var rows = Array.prototype.slice.call(document.querySelectorAll('#tw-list .tw-row'));
        var idx = rows.findIndex(function (r) { return r.getAttribute('data-h') === h; });
        var next = rows[idx + dir];
        if (next) {
            var t = next.querySelector('textarea');
            t.focus();
            next.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        } else {
            var pages = Math.ceil(S.view.length / PAGE_SIZE);
            var np = S.page + dir;
            if (np >= 0 && np < pages) {
                S.page = np;
                renderList();
                var rs = document.querySelectorAll('#tw-list .tw-row textarea');
                var t2 = rs[dir > 0 ? 0 : rs.length - 1];
                if (t2) { t2.focus(); }
            }
        }
    }

    function renderList() {
        var list = $('tw-list');
        var active = document.activeElement && document.activeElement.id;
        list.textContent = '';
        var start = S.page * PAGE_SIZE;
        var slice = S.view.slice(start, start + PAGE_SIZE);
        if (!slice.length) {
            var em = el('li', 'tw-empty');
            em.appendChild(el('strong', '', tx(S.filter === 'todo' && !S.query ? 'empty_todo' : 'empty_list')));
            list.appendChild(em);
        }
        slice.forEach(function (i) { list.appendChild(renderRow(i)); });
        renderPager();
        if (active && $(active)) { $(active).focus({ preventScroll: true }); }
    }

    function refreshRows(hs) {
        hs.forEach(function (h) {
            var old = document.querySelector('#tw-list .tw-row[data-h="' + h + '"]');
            if (!old) { return; }
            var ta = old.querySelector('textarea');
            var focused = ta && document.activeElement === ta;
            // Une phrase en cours de saisie n'est pas redessinée : le texte tapé resterait sinon écrasé.
            if (focused && S.pending[h]) { old.setAttribute('data-status', status(h)); return; }
            var sel = focused ? [ta.selectionStart, ta.selectionEnd] : null;
            var nu = renderRow(S.byH[h].i);
            old.replaceWith(nu);
            if (sel) { var t = nu.querySelector('textarea'); t.focus({ preventScroll: true }); t.setSelectionRange(sel[0], sel[1]); }
        });
    }

    function renderPager() {
        var nav = $('tw-pager');
        nav.textContent = '';
        var pages = Math.max(1, Math.ceil(S.view.length / PAGE_SIZE));
        nav.appendChild(el('span', 'tw-muted', tx('count_shown', { n: fmt.format(S.view.length) })));
        if (pages <= 1) { return; }
        function go(p) {
            S.page = Math.max(0, Math.min(pages - 1, p));
            renderList();
            $('tw-body').scrollIntoView({ block: 'start', behavior: 'smooth' });
        }
        var prev = btn('tw-btn', '‹ ' + tx('prev'), function () { go(S.page - 1); });
        prev.disabled = S.page === 0;
        var next = btn('tw-btn', tx('next') + ' ›', function () { go(S.page + 1); });
        next.disabled = S.page >= pages - 1;
        var jump = el('label', 'tw-jump');
        var inp = el('input');
        inp.type = 'number';
        inp.min = '1';
        inp.max = String(pages);
        inp.value = String(S.page + 1);
        inp.setAttribute('aria-label', tx('page_label'));
        inp.addEventListener('change', function () { go(parseInt(inp.value, 10) - 1); });
        jump.appendChild(inp);
        jump.appendChild(el('span', '', ' / ' + fmt.format(pages)));
        nav.appendChild(prev);
        nav.appendChild(jump);
        nav.appendChild(next);
    }

    // ---------- Propositions, historique ----------

    function decide(s, accept) {
        call('POST', '/' + S.pack.id + '/suggestions/' + s.id, { decision: accept ? 'accept' : 'reject' }).then(function (j) {
            if (!j.ok) { setSync(apiError(j), 'bad'); return; }
            poll();
        }, function () { setSync(tx('api_network'), 'bad'); });
    }

    function openHistory(e) {
        var d = $('tw-dlg-history');
        d.querySelector('[data-role="source"]').textContent = e.en || e.fr;
        var list = d.querySelector('[data-role="list"]');
        list.textContent = '';
        list.appendChild(el('li', 'tw-muted', tx('loading_short')));
        openDialog(d);
        call('GET', '/' + S.pack.id + '/history?h=' + e.h).then(function (j) {
            list.textContent = '';
            if (!j.ok) { list.appendChild(el('li', 'is-error', apiError(j))); return; }
            if (!j.history.length) { list.appendChild(el('li', 'tw-muted', tx('history_none'))); return; }
            j.history.forEach(function (x) {
                var li = el('li');
                li.appendChild(el('p', 'tw-sugg__by', tx('history_' + x.action, { by: x.by, when: ago(x.at) })));
                li.appendChild(el('blockquote', '', x.new || tx('empty_text')));
                if (S.pack.role === 'owner') {
                    li.appendChild(btn('tw-link', tx('history_undo'), function () {
                        call('POST', '/' + S.pack.id + '/revert', { history_id: x.id, author: myName() }).then(function (r) {
                            if (!r.ok) { setSync(apiError(r), 'bad'); return; }
                            closeDialog(d);
                            poll();
                        });
                    }));
                }
                list.appendChild(li);
            });
        });
    }

    // ---------- Fenêtres ----------

    function openDialog(d) { if (typeof d.showModal === 'function') { d.showModal(); } else { d.setAttribute('open', ''); } }
    function closeDialog(d) { if (typeof d.close === 'function') { d.close(); } else { d.removeAttribute('open'); } }
    Array.prototype.forEach.call(document.querySelectorAll('.tw-dlg'), function (d) {
        Array.prototype.forEach.call(d.querySelectorAll('[data-role="close"]'), function (b) { b.addEventListener('click', function () { closeDialog(d); }); });
        d.addEventListener('click', function (ev) { if (ev.target === d) { closeDialog(d); } });
    });
    function formError(form, text) {
        var p = form.querySelector('[data-role="error"]');
        p.textContent = text || '';
        p.hidden = !text;
    }

    function openSettings() {
        var d = $('tw-dlg-settings');
        var f = $('tw-settings-form');
        var owner = !S.pack || S.pack.role === 'owner';
        f.code.value = S.meta.code;
        f.code.disabled = !!S.pack;
        f.name.value = S.meta.name;
        f.game.value = S.meta.game;
        f.author.value = S.meta.author;
        f.pseudo.value = S.pseudo;
        f.review.value = S.pack ? S.pack.meta.review_mode : 'direct';
        f.querySelector('[data-role="review-field"]').hidden = !S.pack;
        ['name', 'game', 'author', 'review'].forEach(function (n) { f[n].disabled = !owner; });
        f.querySelector('[data-role="locked"]').hidden = owner;
        f.querySelector('[data-role="import-wrap"]').hidden = !owner;
        f.querySelector('[data-role="reset"]').hidden = !!S.pack;
        formError(f, '');
        openDialog(d);
    }

    $('tw-settings-form').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var f = ev.target;
        var pseudo = f.pseudo.value.trim().slice(0, 32);
        S.pseudo = pseudo;
        storageSet(PSEUDO_KEY, pseudo);
        if (!S.pack) {
            var code = f.code.value.trim().toLowerCase();
            if (!validCode(code)) { formError(f, tx('code_invalid')); return; }
            if (!f.name.value.trim()) { formError(f, tx('name_missing')); return; }
            if (code !== S.meta.code) {
                var other = loadLocal(code);
                if (other && Object.keys(other.tr).length && window.confirm(tx('confirm_switch', { code: code }))) {
                    setTranslations(other.tr);
                }
            }
            S.meta = { code: code, name: f.name.value.trim(), game: f.game.value, author: f.author.value.trim() };
            saveLocal();
            afterDataChange();
            closeDialog($('tw-dlg-settings'));
            return;
        }
        if (S.pack.role !== 'owner') { closeDialog($('tw-dlg-settings')); rebuild(true); return; }
        call('POST', '/' + S.pack.id + '/settings', { name: f.name.value.trim(), game: f.game.value, author_label: f.author.value.trim(), review_mode: f.review.value }).then(function (j) {
            if (!j.ok) { formError(f, apiError(j)); return; }
            closeDialog($('tw-dlg-settings'));
            poll();
        }, function () { formError(f, tx('api_network')); });
    });
    $('tw-settings-form').querySelector('[data-role="reset"]').addEventListener('click', function () {
        if (!window.confirm(tx('confirm_reset'))) { return; }
        if (S.meta.code) { storageDel(STORE2 + S.meta.code); storageDel('comspec-atakt:v1:' + S.meta.code); }
        setTranslations({});
        afterDataChange();
        closeDialog($('tw-dlg-settings'));
    });

    // ---------- Workshop des langues : publication, avis de mise à jour du mod ----------

    function setWorkshop(ws) {
        S.workshop = ws || null;
        S.newKeys = {};
        var rel = S.workshop && S.workshop.release;
        ((rel && rel.new_keys) || []).forEach(function (h) { if (S.byH[h]) { S.newKeys[h] = true; } });
    }

    function showFilter(f) { S.filter = f; S.page = 0; rebuild(); renderSide(); }

    /** Avis au chargement d'un pack quand une mise à jour du mod touche sa version publiée ; false si rien à dire. */
    function workshopBanner() {
        var rel = S.workshop && S.workshop.release;
        if (!rel || !rel.status || rel.status.key === 'ok') { return false; }
        var st = rel.status;
        var vars = { n: fmt.format(st.key === 'recheck' ? st.errors : st.new), v: rel.version, atak: rel.current_atak || S.version };
        var acts = [];
        if (st.key === 'outdated') { acts.push([tx('banner_show_new'), function () { showFilter(Object.keys(S.newKeys).length ? 'new' : 'todo'); }]); }
        if (st.key === 'recheck') { acts.push([tx('banner_show_errors'), function () { showFilter('error'); }]); }
        if (S.pack && S.pack.role === 'owner') { acts.push([tx('banner_publish'), openPublish]); }
        showBanner(tx('banner_' + st.key, vars), st.key === 'recheck' ? 'bad' : 'warn', acts);
        return true;
    }

    function pubState(p) {
        var ws = S.workshop;
        p.textContent = '';
        p.hidden = !ws;
        if (!ws) { return; }
        var rel = ws.release;
        if (!rel) { p.appendChild(el('span', '', tx('pub_none'))); return; }
        var chip = el('span', 'atakw-status atakw-status--' + rel.status.key, tx('pub_status_' + rel.status.key, { n: fmt.format(rel.status.key === 'recheck' ? rel.status.errors : rel.status.new) }));
        p.appendChild(chip);
        p.appendChild(el('span', '', tx('pub_state', { v: rel.version, atak: rel.atak_version || '?', n: fmt.format(ws.downloads || 0) })));
        if (ws.blocked) { p.appendChild(el('span', 'is-bad', tx('pub_blocked'))); } else if (!ws.listed) { p.appendChild(el('span', '', tx('pub_hidden'))); }
    }

    function openPublish() {
        var d = $('tw-dlg-publish');
        var owner = !!(S.pack && S.pack.role === 'owner');
        d.querySelector('[data-role="local"]').hidden = !!S.pack;
        d.querySelector('[data-role="editor"]').hidden = !S.pack || owner;
        var f = d.querySelector('[data-role="owner"]');
        f.hidden = !owner;
        Array.prototype.forEach.call(d.querySelectorAll('[data-role="state"]'), pubState);
        if (owner) {
            var ws = S.workshop;
            var rel = ws && ws.release;
            f.description.value = ws ? ws.description : '';
            f.workshop_id.value = ws && ws.workshop_id ? 'https://steamcommunity.com/sharedfiles/filedetails/?id=' + ws.workshop_id : '';
            f.note.value = '';
            f.querySelector('[data-role="publish"]').textContent = tx('pub_button', { v: rel ? rel.version + 1 : 1 });
            f.querySelector('[data-role="publish"]').disabled = !ws;
            var view = f.querySelector('[data-role="view"]');
            view.hidden = !rel;
            if (ws) { view.href = ws.url; }
            f.querySelector('[data-role="unlist"]').hidden = !(rel && ws.listed);
            f.querySelector('[data-role="relist"]').hidden = !(rel && !ws.listed);
            f.querySelector('[data-role="save"]').hidden = !rel;
            formError(f, ws ? '' : tx('api_unavailable'));
        }
        openDialog(d);
    }

    function saveListing(listed) {
        var f = $('tw-dlg-publish').querySelector('[data-role="owner"]');
        var body = { listed: listed, description: f.description.value, workshop_id: f.workshop_id.value.trim() };
        formError(f, '');
        call('POST', '/' + S.pack.id + '/listing', body).then(function (j) {
            if (!j.ok) { formError(f, apiError(j)); return; }
            var steam = /([0-9]{6,15})/.exec(body.workshop_id);
            S.workshop.listed = listed;
            S.workshop.description = body.description.trim();
            S.workshop.workshop_id = body.workshop_id ? (steam ? steam[1] : '') : '';
            setSync(tx('pub_saved'), 'ok');
            openPublish();
        }, function () { formError(f, tx('api_network')); });
    }

    (function () {
        var d = $('tw-dlg-publish');
        var f = d.querySelector('[data-role="owner"]');
        d.querySelector('[data-role="to-share"]').addEventListener('click', function () { closeDialog(d); openShare(); });
        f.querySelector('[data-role="save"]').addEventListener('click', function () { saveListing(S.workshop.listed); });
        f.querySelector('[data-role="unlist"]').addEventListener('click', function () { saveListing(false); });
        f.querySelector('[data-role="relist"]').addEventListener('click', function () { saveListing(true); });
        f.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var b = f.querySelector('[data-role="publish"]');
            formError(f, '');
            b.disabled = true;
            call('POST', '/' + S.pack.id + '/publish', { description: f.description.value, workshop_id: f.workshop_id.value.trim(), note: f.note.value }).then(function (j) {
                b.disabled = false;
                if (!j.ok) {
                    formError(f, j.error === 'too_few' ? tx('pub_too_few', { min: j.min, n: fmt.format(j.translated || 0) }) : apiError(j));
                    return;
                }
                setWorkshop(j.workshop);
                closeDialog(d);
                renderSide();
                showBanner(tx('pub_done', { v: j.version }), 'ok', [[tx('pub_view'), function () { window.open(j.url, '_blank', 'noopener'); }]]);
            }, function () { b.disabled = false; formError(f, tx('api_network')); });
        });
    })();

    function openShare() {
        var d = $('tw-dlg-share');
        var local = d.querySelector('[data-role="local"]');
        var pk = d.querySelector('[data-role="pack"]');
        local.hidden = !!S.pack;
        pk.hidden = !S.pack;
        local.querySelector('[name="pseudo"]').value = S.pseudo;
        formError(local, '');
        formError(pk, '');
        if (S.pack && S.pack.account && !S.pack.token) {
            call('POST', '/' + S.pack.id + '/liens', {}).then(function (j) {
                var pk = d.querySelector('[data-role="pack"]');
                if (!j.ok) { formError(pk, apiError(j)); openDialog(d); return; }
                S.pack.token = j.owner_token || '';
                S.pack.editToken = j.edit_token || '';
                rememberPack({ id: S.pack.id, token: S.pack.token, editToken: S.pack.editToken, role: 'owner', name: S.meta.name, code: S.meta.code, at: Date.now() });
                openShare();
            }, function () { formError(d.querySelector('[data-role="pack"]'), tx('api_network')); openDialog(d); });
            return;
        }
        if (S.pack) {
            var owner = S.pack.role === 'owner';
            pk.querySelector('[data-role="pack-lead"]').textContent = tx(owner ? 'share_pack_owner' : 'share_pack_editor');
            pk.querySelector('[data-role="editor-link"]').value = packLink('editor') || tx('share_editor_unknown');
            pk.querySelector('[data-role="owner-only"]').hidden = !owner;
            pk.querySelector('[data-role="owner-link"]').value = owner ? packLink('owner') : '';
            Array.prototype.forEach.call(pk.querySelectorAll('[data-owner]'), function (b) { b.hidden = !owner; });
        } else if (!validCode(S.meta.code) || !S.meta.name) {
            openSettings();
            return;
        }
        openDialog(d);
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-copy]'), function (b) {
        b.addEventListener('click', function () {
            var inp = b.parentNode.querySelector('input');
            inp.select();
            var done = function () { var t = b.textContent; b.textContent = tx('copied'); setTimeout(function () { b.textContent = t; }, 1500); };
            if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(inp.value).then(done, function () { document.execCommand('copy'); done(); }); } else { document.execCommand('copy'); done(); }
        });
    });

    function chunked(items, size) {
        var keys = Object.keys(items);
        var out = [];
        for (var i = 0; i < keys.length; i += size) {
            var part = {};
            keys.slice(i, i + size).forEach(function (k) { part[k] = items[k]; });
            out.push(part);
        }
        return out;
    }
    function uploadAll(items, overwrite, onStep) {
        var parts = chunked(items, 1500);
        var done = 0;
        return parts.reduce(function (pr, part, idx) {
            return pr.then(function () {
                return call('POST', '/' + S.pack.id + '/bulk', { items: part, overwrite: overwrite, author: myName() }).then(function (j) {
                    if (!j.ok) { throw new Error(apiError(j)); }
                    done += j.written || 0;
                    if (onStep) { onStep(idx + 1, parts.length); }
                });
            });
        }, Promise.resolve()).then(function () { return done; });
    }

    $('tw-dlg-share').querySelector('[data-role="create"]').addEventListener('click', function (ev) {
        var b = ev.currentTarget;
        var box = $('tw-dlg-share').querySelector('[data-role="local"]');
        var pseudo = box.querySelector('[name="pseudo"]').value.trim().slice(0, 32);
        S.pseudo = pseudo;
        storageSet(PSEUDO_KEY, pseudo);
        b.disabled = true;
        formError(box, '');
        fetch(API, {
            method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ code: S.meta.code, name: S.meta.name, game: S.meta.game, author_label: S.meta.author, review_mode: box.querySelector('[name="review"]').value }),
        }).then(function (r) { return r.json(); }).then(function (j) {
            if (!j.ok) { throw new Error(apiError(j)); }
            var localTr = S.tr;
            S.pack = newPackState(j.public_id, j.owner_token, 'owner');
            S.pack.editToken = j.edit_token;
            rememberPack({ id: j.public_id, token: j.owner_token, editToken: j.edit_token, role: 'owner', name: S.meta.name, code: S.meta.code, at: Date.now() });
            setSync(tx('uploading'), '');
            return uploadAll(localTr, true, function (n, of) { setSync(tx('uploading_step', { n: n, of: of }), ''); });
        }).then(function () {
            b.disabled = false;
            history.replaceState(null, '', PAGE + '/p/' + S.pack.id + '#o=' + S.pack.token);
            return openPack(true);
        }).then(function () { openShare(); }).catch(function (err) {
            b.disabled = false;
            formError(box, err && err.message ? err.message : tx('api_network'));
        });
    });

    $('tw-dlg-share').querySelector('[data-role="rotate"]').addEventListener('click', function () {
        if (!window.confirm(tx('confirm_rotate'))) { return; }
        call('POST', '/' + S.pack.id + '/rotate', {}).then(function (j) {
            var pk = $('tw-dlg-share').querySelector('[data-role="pack"]');
            if (!j.ok) { formError(pk, apiError(j)); return; }
            S.pack.editToken = j.edit_token;
            var saved = myPacks().filter(function (x) { return x.id === S.pack.id; })[0] || {};
            saved.editToken = j.edit_token;
            rememberPack(Object.assign(saved, { id: S.pack.id, token: S.pack.token, role: 'owner', name: S.meta.name, code: S.meta.code, at: Date.now() }));
            pk.querySelector('[data-role="editor-link"]').value = packLink('editor');
        });
    });
    $('tw-dlg-share').querySelector('[data-role="delete"]').addEventListener('click', function () {
        if (!window.confirm(tx('confirm_delete'))) { return; }
        call('POST', '/' + S.pack.id + '/delete', {}).then(function (j) {
            if (!j.ok) { formError($('tw-dlg-share').querySelector('[data-role="pack"]'), apiError(j)); return; }
            forgetPack(S.pack.id);
            window.location.href = PAGE;
        });
    });

    // ---------- Menu « mes traductions » ----------

    function renderMenu() {
        var m = $('tw-menu');
        m.textContent = '';
        var local = loadLocalSummary();
        var items = [];
        var mineCodes = {};
        MINE.forEach(function (p) {
            mineCodes[p.code] = true;
            items.push([(p.code || '').toUpperCase() + ' · ' + (p.name || p.code), tx('mode_account'), PAGE + '?compte=' + encodeURIComponent(p.id), !!(S.pack && S.pack.account && S.pack.id === p.id)]);
        });
        local.forEach(function (l) {
            if (ACCOUNT && mineCodes[l.code]) { return; }
            items.push([l.code.toUpperCase() + ' · ' + (l.name || l.code), tx('mode_local'), PAGE + '?local=' + l.code, !S.pack && S.meta.code === l.code]);
        });
        myPacks().forEach(function (p) {
            items.push([(p.code || '').toUpperCase() + ' · ' + (p.name || p.id), p.role === 'owner' ? tx('mode_pack_owner') : tx('mode_pack_editor'), PAGE + '/p/' + p.id + '#' + (p.role === 'owner' ? 'o' : 'k') + '=' + p.token, S.pack && S.pack.id === p.id]);
        });
        var ul = el('ul');
        items.forEach(function (it) {
            var li = el('li');
            var a = el('a', it[3] ? 'is-current' : '');
            a.href = it[2];
            a.appendChild(el('strong', '', it[0]));
            a.appendChild(el('small', '', it[1]));
            li.appendChild(a);
            ul.appendChild(li);
        });
        var nl = el('li');
        var na = el('a', 'tw-menu__new');
        na.href = PAGE + '?new=1';
        na.textContent = tx('menu_new');
        nl.appendChild(na);
        ul.appendChild(nl);
        m.appendChild(ul);
    }
    function loadLocalSummary() {
        var out = [];
        try {
            for (var i = 0; i < window.localStorage.length; i++) {
                var k = window.localStorage.key(i);
                var m = /^comspec-atakt:v[12]:([a-z]{2})$/.exec(k || '');
                if (m && !out.some(function (x) { return x.code === m[1]; })) {
                    var d = storageJson(k, {});
                    out.push({ code: m[1], name: (d.meta && d.meta.name) || '' });
                }
            }
        } catch (e) { /* stockage indisponible */ }
        return out;
    }
    $('tw-switch').addEventListener('click', function () {
        var m = $('tw-menu');
        var open = m.hidden;
        if (open) { renderMenu(); }
        m.hidden = !open;
        $('tw-switch').setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (ev) {
        if (!$('tw-menu').hidden && !ev.target.closest('.tw-id')) { $('tw-menu').hidden = true; $('tw-switch').setAttribute('aria-expanded', 'false'); }
    });

    // ---------- Import .txt ----------

    function importFile(file, then) {
        var fromName = /^([a-z]{2})\.txt$/i.exec(file.name);
        file.text().then(function (text) {
            var parsed = parseCommunity(text);
            if (!parsed.lines) { showBanner(tx('import_none'), 'bad'); return; }
            var items = {};
            var kept = 0, dropped = 0;
            Object.keys(parsed.map).forEach(function (fr) {
                if (!parsed.map[fr]) { return; }
                var e = S.byFr[fr];
                if (e) { items[e.h] = parsed.map[fr]; kept++; } else { dropped++; }
            });
            var msg = tx('import_done', { kept: fmt.format(kept), dropped: fmt.format(dropped) });
            if (S.pack) {
                setSync(tx('uploading'), '');
                uploadAll(items, true).then(function () { showBanner(msg, 'ok'); poll(); }, function (err) { showBanner(err.message, 'bad'); });
            } else {
                Object.keys(items).forEach(function (h) { S.tr[h] = items[h]; });
                if (fromName && !S.meta.code && validCode(fromName[1].toLowerCase())) { S.meta.code = fromName[1].toLowerCase(); }
                if (!S.meta.game && GAME_BY_CODE[S.meta.code]) { S.meta.game = GAME_BY_CODE[S.meta.code]; }
                saveLocal();
                afterDataChange();
                showBanner(msg, 'ok');
            }
            if (then) { then(); }
        });
    }
    Array.prototype.forEach.call(document.querySelectorAll('[data-role="import"]'), function (inp) {
        inp.addEventListener('change', function () {
            var f = inp.files && inp.files[0];
            if (!f) { return; }
            var inSetup = !!inp.closest('#tw-setup');
            if (inSetup) {
                var form = $('tw-setup-form');
                var m = /^([a-z]{2})\.txt$/i.exec(f.name);
                if (m && !form.code.value) { form.code.value = m[1].toLowerCase(); }
            }
            importFile(f, function () {
                closeDialog($('tw-dlg-settings'));
                if (inSetup && validCode(S.meta.code)) {
                    if (!S.meta.name) { S.meta.name = $('tw-setup-form').name.value.trim() || S.meta.code.toUpperCase(); }
                    saveLocal();
                    showWorkspace();
                }
            });
            inp.value = '';
        });
    });

    // ---------- Démarrage ----------

    function setTranslations(map) {
        S.tr = {};
        Object.keys(map || {}).forEach(function (h) { if (S.byH[h] && map[h]) { S.tr[h] = String(map[h]); } });
    }
    function afterDataChange() { recheckAll(); renderSide(); renderBar(); rebuild(false); }

    function newPackState(id, token, role) {
        return { id: id, token: token, role: role, editToken: '', meta: { review_mode: 'direct' }, rev: 0, strRev: {}, by: {}, at: {}, sugg: {}, people: [] };
    }

    function showWorkspace() {
        $('tw').setAttribute('data-state', 'ready');
        $('tw-loading').hidden = true;
        $('tw-setup').hidden = true;
        $('tw-body').hidden = false;
        afterDataChange();
    }
    function showSetup() {
        $('tw').setAttribute('data-state', 'setup');
        $('tw-loading').hidden = true;
        $('tw-setup').hidden = false;
        $('tw-body').hidden = true;
        var ul = $('tw-mypacks');
        ul.textContent = '';
        var local = loadLocalSummary();
        var mineCodes = {};
        MINE.forEach(function (p) {
            mineCodes[p.code] = true;
            var li = el('li');
            var a = el('a');
            a.href = PAGE + '?compte=' + encodeURIComponent(p.id);
            a.textContent = (p.code || '').toUpperCase() + ' · ' + (p.name || p.code) + ' — ' + tx('mode_account');
            li.appendChild(a);
            ul.appendChild(li);
        });
        myPacks().forEach(function (p) {
            var li = el('li');
            var a = el('a');
            a.href = PAGE + '/p/' + p.id + '#' + (p.role === 'owner' ? 'o' : 'k') + '=' + p.token;
            a.textContent = (p.code || '').toUpperCase() + ' · ' + (p.name || p.id) + ' — ' + (p.role === 'owner' ? tx('mode_pack_owner') : tx('mode_pack_editor'));
            li.appendChild(a);
            ul.appendChild(li);
        });
        local.forEach(function (l) {
            if (ACCOUNT && mineCodes[l.code]) { return; }
            var li = el('li');
            var a = el('a');
            a.href = PAGE + '?local=' + l.code;
            a.textContent = l.code.toUpperCase() + ' · ' + (l.name || l.code) + ' — ' + tx('mode_local');
            li.appendChild(a);
            ul.appendChild(li);
        });
        renderBar();
    }

    $('tw-setup-form').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var f = ev.target;
        var code = f.code.value.trim().toLowerCase();
        if (!validCode(code)) { formError(f, tx('code_invalid')); return; }
        if (!f.name.value.trim()) { formError(f, tx('name_missing')); return; }
        var saved = loadLocal(code);
        if (saved) { setTranslations(saved.tr); }
        S.meta = { code: code, name: f.name.value.trim(), game: (saved && saved.meta && saved.meta.game) || GAME_BY_CODE[code] || '', author: (saved && saved.meta && saved.meta.author) || '' };
        if (ACCOUNT) {
            ensureAccount().catch(function () {
                S.pack = null;
                saveLocal();
                history.replaceState(null, '', PAGE + '?local=' + code);
                showWorkspace();
                setSync(tx('account_unavailable'), 'bad');
            });
            return;
        }
        saveLocal();
        history.replaceState(null, '', PAGE + '?local=' + code);
        showWorkspace();
    });
    $('tw-join-form').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var v = ev.target.link.value.trim();
        var m = /\/traduire-atak\/p\/([a-z0-9]{12})#([ok])=([A-Za-z0-9_-]{16,64})/.exec(v);
        var err = $('tw-join-error');
        if (!m) { err.textContent = tx('join_invalid'); err.hidden = false; return; }
        window.location.href = PAGE + '/p/' + m[1] + '#' + m[2] + '=' + m[3];
        if (window.location.pathname.indexOf('/p/' + m[1]) >= 0) { window.location.reload(); }
    });

    function openPack(silent) {
        return call('GET', '/' + S.pack.id + '?since=0').then(function (j) {
            if (!j.ok) {
                if (j.httpStatus === 404) { forgetPack(S.pack.id); }
                S.pack = null;
                showSetup();
                var err = $('tw-join-error');
                err.textContent = apiError(j);
                err.hidden = false;
                return;
            }
            S.tr = {};
            applyChanges(j);
            setWorkshop(j.workshop);
            var saved = myPacks().filter(function (x) { return x.id === S.pack.id; })[0] || {};
            if (S.pack.role === 'editor') { S.pack.editToken = S.pack.token; }
            else if (saved.editToken) { S.pack.editToken = saved.editToken; }
            if (!S.pack.account) {
                rememberPack({ id: S.pack.id, token: S.pack.token, editToken: S.pack.editToken, role: S.pack.role, name: S.meta.name, code: S.meta.code, at: Date.now() });
            }
            showWorkspace();
            setSync(S.pack.account ? tx('saved_account') : tx('synced', { when: ago(Date.now() / 1000) }), 'ok');
            if (S.pack.account) { saveLocal(); }
            if (!silent && !workshopBanner() && !S.pseudo) { showBanner(tx('pseudo_hint'), '', [[tx('pseudo_set'), openSettings]]); }
            pollTimer = setTimeout(poll, POLL_MS);
        }, function () { setSync(tx('api_network'), 'bad'); });
    }

    function bind() {
        var search = $('tw-search');
        var st = null;
        search.addEventListener('input', function () {
            clearTimeout(st);
            st = setTimeout(function () { S.query = fold(search.value.trim()); rebuild(false); }, 200);
        });
        $('tw-catsearch').addEventListener('input', function (e) { S.catQuery = fold(e.target.value.trim()); renderSide(); });
        Array.prototype.forEach.call(document.querySelectorAll('.tw-seg [data-source]'), function (b) {
            b.setAttribute('aria-pressed', b.getAttribute('data-source') === S.source ? 'true' : 'false');
            b.addEventListener('click', function () {
                S.source = b.getAttribute('data-source');
                prefs.source = S.source;
                storageSet(PREFS_KEY, JSON.stringify(prefs));
                Array.prototype.forEach.call(document.querySelectorAll('.tw-seg [data-source]'), function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
                renderList();
            });
        });
        var pa = $('tw-preview-all');
        pa.checked = S.previewAll;
        pa.addEventListener('change', function () { S.previewAll = pa.checked; prefs.previewAll = pa.checked; storageSet(PREFS_KEY, JSON.stringify(prefs)); renderList(); });
        $('tw-share').addEventListener('click', openShare);
        $('tw-publish').addEventListener('click', openPublish);
        $('tw-settings').addEventListener('click', openSettings);
        $('tw-download').addEventListener('click', function () { $('publier').scrollIntoView({ behavior: 'smooth' }); });
        $('atakt-zip').addEventListener('click', buildAll);
        $('atakt-txt').addEventListener('click', downloadTxt);
        if (window.matchMedia && window.matchMedia('(max-width: 899px)').matches) { $('tw-fold').open = false; }
    }

    function isWholeSentence(fr) {
        var t = String(fr || '').trim();
        if (!t || /^["'<]/.test(t) || t.indexOf('class=') >= 0) { return false; }
        if (t.length >= 80) { return true; }
        var n = t.split(/\s+/).length;
        if (n >= 8 && t.length >= 45) { return true; }
        if (n >= 6 && /[.!?…:]$/.test(t)) { return true; }
        return false;
    }
    function attachAround(entries) {
        entries.forEach(function (e, i) {
            e.around = [];
            if (isWholeSentence(e.fr)) { return; }
            [entries[i - 1], entries[i + 1]].forEach(function (n) {
                if (!n) { return; }
                var same = e.file ? n.file === e.file : n.cat === e.cat;
                if (!same) { return; }
                var line = String(n.fr || '').trim();
                if (!line || line === String(e.fr).trim() || line.length > 180) { return; }
                e.around.push(line.length > 140 ? line.slice(0, 139) + '…' : line);
            });
        });
    }

    function upsertMine(p) {
        MINE = MINE.filter(function (x) { return x.id !== p.id && x.code !== p.code; });
        MINE.unshift(p);
    }
    function openAccount(id) {
        S.pack = newPackState(id, '', 'owner');
        S.pack.account = true;
        history.replaceState(null, '', PAGE + '?compte=' + encodeURIComponent(id));
        return openPack(true).then(function () {
            if (!S.pack || !validCode(S.meta.code)) { return; }
            var saved = loadLocal(S.meta.code);
            if (!saved || !saved.tr) { return; }
            var missing = {};
            Object.keys(saved.tr).forEach(function (h) { if (saved.tr[h] && !S.tr[h]) { missing[h] = saved.tr[h]; } });
            if (!Object.keys(missing).length) { return; }
            return uploadAll(missing, false).then(function () { return openPack(true); });
        });
    }
    function ensureAccount() {
        return accountFetch('POST', { code: S.meta.code, name: S.meta.name, game: S.meta.game, author_label: S.meta.author }).then(function (j) {
            if (!j.ok) { throw new Error(apiError(j)); }
            var localTr = {};
            Object.keys(S.tr).forEach(function (h) { if (S.tr[h]) { localTr[h] = S.tr[h]; } });
            S.pack = newPackState(j.public_id, j.owner_token || '', 'owner');
            S.pack.account = true;
            if (j.edit_token) { S.pack.editToken = j.edit_token; }
            upsertMine({ id: j.public_id, code: S.meta.code, name: S.meta.name });
            history.replaceState(null, '', PAGE + '?compte=' + encodeURIComponent(j.public_id));
            if (j.resumed) {
                return openPack(true).then(function () {
                    if (!S.pack) { return; }
                    var missing = {};
                    Object.keys(localTr).forEach(function (h) { if (localTr[h] && !S.tr[h]) { missing[h] = localTr[h]; } });
                    if (!Object.keys(missing).length) { return; }
                    return uploadAll(missing, false).then(function () { return openPack(true); });
                });
            }
            if (Object.keys(localTr).length) {
                return uploadAll(localTr, true).then(function () { return openPack(true); });
            }
            showWorkspace();
            setSync(tx('saved_account'), 'ok');
            saveLocal();
        });
    }
    function bootLocal() {
        var q = new URLSearchParams(window.location.search);
        var code = q.get('local') || (q.get('new') ? '' : storageGet(STORE2 + 'last') || storageGet('comspec-atakt:v1:last') || '');
        var saved2 = validCode(code) ? loadLocal(code) : null;
        if (saved2) {
            S.meta = { code: code, name: (saved2.meta && saved2.meta.name) || '', game: (saved2.meta && saved2.meta.game) || '', author: (saved2.meta && saved2.meta.author) || '' };
            setTranslations(saved2.tr);
            if (ACCOUNT && !q.get('new')) {
                ensureAccount().catch(function () {
                    S.pack = null;
                    showWorkspace();
                    setSync(tx('saved_local'), 'ok');
                });
                return;
            }
            showWorkspace();
            setSync(tx('saved_local'), 'ok');
        } else {
            showSetup();
        }
    }
    function bootAccount() {
        var q = new URLSearchParams(window.location.search);
        if (q.get('new')) { showSetup(); return; }
        var compte = q.get('compte') || '';
        if (compte && MINE.some(function (p) { return p.id === compte; })) { openAccount(compte); return; }
        var code = q.get('local') || '';
        if (validCode(code)) {
            var mine = MINE.filter(function (p) { return p.code === code; })[0];
            if (mine) { openAccount(mine.id); return; }
            bootLocal();
            return;
        }
        if (MINE.length) { openAccount(MINE[0].id); return; }
        bootLocal();
    }

    function start(data) {
        S.cats = data.categories || {};
        S.stringtable = Array.isArray(data.stringtable) ? data.stringtable : [];
        S.entries = (data.entries || []).filter(function (e) { return Array.isArray(e) && typeof e[0] === 'string'; }).map(function (e, i) {
            return { i: i, h: e[0], fr: e[1], en: e[2] || '', cat: e[3] || 'other', file: e[4] || '', norm: fold(e[1] + '\n' + (e[2] || '')) };
        });
        attachAround(S.entries);
        S.entries.forEach(function (e) { S.byH[e.h] = e; S.byFr[e.fr] = e; });
        if (data.version) { S.version = data.version; }
        bind();

        var packId = root.getAttribute('data-pack-id');
        if (packId) {
            var hm = /^#([ok])=([A-Za-z0-9_-]{16,64})$/.exec(window.location.hash);
            var saved = myPacks().filter(function (x) { return x.id === packId; })[0];
            var token = hm ? hm[2] : (saved ? saved.token : '');
            var role = hm ? (hm[1] === 'o' ? 'owner' : 'editor') : (saved ? saved.role : 'editor');
            if (!token) {
                if (ACCOUNT) {
                    S.pack = newPackState(packId, '', 'owner');
                    S.pack.account = true;
                    openPack(false);
                    return;
                }
                showSetup(); var er = $('tw-join-error'); er.textContent = tx('join_missing_key'); er.hidden = false; return;
            }
            S.pack = newPackState(packId, token, role);
            openPack(false);
            return;
        }
        if (ACCOUNT) {
            accountFetch('GET').then(function (j) {
                MINE = (j && j.ok && Array.isArray(j.packs)) ? j.packs : [];
                bootAccount();
            }, function () { bootLocal(); });
            return;
        }
        bootLocal();
    }

    // ---------- Téléchargement ----------

    function folderName() { return '@COMSPEC_ATAK_Lang_' + (S.meta.code || 'xx'); }

    function renderTree() {
        var c = S.meta.code || 'xx';
        $('atakt-tree').textContent = folderName() + '/\n'
            + '  addons/comspec_atak_lang_' + c + '.pbo\n'
            + '  keys/                      (' + (EN_UI ? 'your .bikey' : 'votre .bikey') + ')\n'
            + '  mod.cpp\n'
            + '  preview.jpg\n'
            + '  workshop_description.txt\n'
            + '  workshop_item.vdf\n'
            + '  source/comspec_atak_lang_' + c + '/{config.cpp, $PBOPREFIX$, ' + c + '.txt}\n'
            + '  README.txt';
    }

    function download(name, bytes, type) {
        var blob = new Blob([bytes], { type: type });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = name;
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
    }

    function requireMeta() {
        var msg = $('atakt-dl-state');
        if (!validCode(S.meta.code) || !S.meta.name.trim()) {
            msg.textContent = !validCode(S.meta.code) ? tx('code_invalid') : tx('name_missing');
            msg.className = 'atakt-msg is-bad';
            return false;
        }
        return true;
    }

    function preview(name) {
        return new Promise(function (resolve) {
            var cv = document.createElement('canvas');
            cv.width = 512;
            cv.height = 512;
            var g = cv.getContext('2d');
            if (!g) { resolve(null); return; }
            g.fillStyle = '#070908';
            g.fillRect(0, 0, 512, 512);
            g.strokeStyle = 'rgba(92,199,107,0.35)';
            g.lineWidth = 2;
            for (var y = 64; y < 512; y += 64) { g.beginPath(); g.moveTo(0, y); g.lineTo(512, y); g.stroke(); }
            g.strokeStyle = '#5cc76b';
            g.lineWidth = 6;
            g.strokeRect(28, 28, 456, 456);
            g.fillStyle = '#5cc76b';
            g.font = '900 30px Inter, Arial, sans-serif';
            g.fillText('COMSPEC ATAK', 56, 104);
            g.fillStyle = 'rgba(255,255,255,0.6)';
            g.font = '700 24px Inter, Arial, sans-serif';
            g.fillText(tx('preview_line').toUpperCase(), 56, 146);
            g.fillStyle = '#ffffff';
            var size = 92;
            g.font = '900 ' + size + 'px Inter, Arial, sans-serif';
            while (size > 28 && g.measureText(name).width > 400) { size -= 6; g.font = '900 ' + size + 'px Inter, Arial, sans-serif'; }
            g.fillText(name, 56, 330);
            g.fillStyle = '#f2a33a';
            g.font = '900 64px Inter, Arial, sans-serif';
            g.fillText(S.meta.code.toUpperCase(), 56, 440);
            cv.toBlob(function (b) {
                if (!b) { resolve(null); return; }
                b.arrayBuffer().then(function (ab) { resolve(new Uint8Array(ab)); }, function () { resolve(null); });
            }, 'image/jpeg', 0.9);
        });
    }

    function readme(o) {
        return 'COMSPEC ATAK - ' + o.name + ' (' + o.code + ')\r\n'
            + 'Template: COMSPEC ATAK Native ' + (o.version || '?') + ' - ' + o.site + '\r\n\r\n'
            + 'EN\r\n'
            + '1. Load ' + o.folder + ' with COMSPEC ATAK Native (Launcher > Mods > Add local mod), open the phone, Settings > Language.\r\n'
            + '2. Optional but recommended: sign addons/comspec_atak_lang_' + o.code + '.pbo with your own key (Arma 3 Tools > DSUtils) and put the .bikey in keys/.\r\n'
            + '3. Publish the folder with Arma 3 Tools > Publisher (title and description in workshop_description.txt, image preview.jpg),\r\n'
            + '   then on the Steam page: Add/Remove Required Items > COMSPEC ATAK Native and CBA_A3.\r\n'
            + '4. Next COMSPEC version: reopen your shared pack link, or resume source/comspec_atak_lang_' + o.code + '/' + o.code + '.txt on ' + o.site + '.\r\n'
            + 'The source folder only matters to rebuild the PBO with Addon Builder: leave it out of the Workshop upload if you like.\r\n\r\n'
            + 'FR\r\n'
            + '1. Chargez ' + o.folder + ' avec COMSPEC ATAK Native (Launcher > Mods > Ajouter un mod local), téléphone > Réglages > Langue.\r\n'
            + '2. Conseillé : signez addons/comspec_atak_lang_' + o.code + '.pbo avec votre propre clé (Arma 3 Tools > DSUtils), .bikey dans keys/.\r\n'
            + '3. Publiez le dossier avec Arma 3 Tools > Publisher (titre et description dans workshop_description.txt, image preview.jpg),\r\n'
            + '   puis sur la page Steam : Ajouter/Supprimer des éléments requis > COMSPEC ATAK Native et CBA_A3.\r\n'
            + '4. Version suivante de COMSPEC : rouvrez le lien de votre pack partagé, ou reprenez source/comspec_atak_lang_' + o.code + '/' + o.code + '.txt sur ' + o.site + '.\r\n'
            + 'Le dossier source ne sert qu’à reconstruire le PBO avec Addon Builder : inutile de le publier.\r\n';
    }

    function workshopDescription(o, c) {
        return 'Title: COMSPEC ATAK - ' + o.name + '\r\n\r\n'
            + '[h1]COMSPEC ATAK - ' + o.name + '[/h1]\r\n'
            + o.desc + '\r\n\r\n'
            + '[list]\r\n'
            + '[*] ' + c.done + ' / ' + c.total + ' sentences translated (COMSPEC ATAK Native ' + (o.version || '') + '). Untranslated sentences are shown in English.\r\n'
            + '[*] Requires: COMSPEC ATAK Native, CBA_A3.\r\n'
            + '[*] In game: phone > Settings > Language, or Esc > Options > Addon Options > COMSPEC > Language.' + (o.game ? ' Picked automatically when Arma 3 runs in ' + o.game + '.' : '') + '\r\n'
            + '[/list]\r\n\r\n'
            + 'Translation by ' + (o.author || 'the community') + (o.people ? ' (' + o.people + ')' : '') + '. Made with ' + o.site + '\r\n';
    }

    function buildAll() {
        if (!requireMeta()) { return; }
        var msg = $('atakt-dl-state');
        msg.className = 'atakt-msg';
        msg.textContent = tx('zip_building');
        var c = counts();
        var o = {
            code: S.meta.code,
            name: S.meta.name.trim(),
            game: S.meta.game,
            author: (S.meta.author || '').trim(),
            version: S.version,
            folder: folderName(),
            site: window.location.origin + PAGE,
            people: S.pack ? (S.pack.people || []).map(function (p) { return p.name; }).slice(0, 12).join(', ') : '',
        };
        // Description Workshop toujours en anglais (public de Steam), quelle que soit la langue de la page.
        o.desc = o.name + ' translation of the COMSPEC ATAK phone and the SSE mod for Arma 3.';
        setTimeout(function () {
            try {
                var txt = buildTxt(S.entries, S.tr);
                var txtBytes = enc.encode(txt.text);
                var cfg = enc.encode(buildConfig(o));
                var prefix = 'comspec_atak_lang_' + o.code;
                var byText = {};
                S.entries.forEach(function (e) { if (S.tr[e.h]) { byText[e.fr] = S.tr[e.h]; } });
                var st = buildStringtable(S.stringtable, byText, o.code, o.game);
                var pboFiles = [{ name: 'config.cpp', data: cfg }, { name: o.code + '.txt', data: txtBytes }];
                if (st) { pboFiles.push({ name: 'stringtable.xml', data: enc.encode(st) }); }
                var pbo = buildPbo(prefix, pboFiles, Math.floor(Date.now() / 1000));
                preview(o.name).then(function (jpg) {
                    var F = o.folder + '/';
                    var files = [
                        { name: F + 'addons/' + prefix + '.pbo', data: pbo },
                        { name: F + 'keys/', data: new Uint8Array(0), store: true },
                        { name: F + 'mod.cpp', data: enc.encode(buildModCpp(o)) },
                        { name: F + 'workshop_description.txt', data: enc.encode(workshopDescription(o, c)) },
                        { name: F + 'workshop_item.vdf', data: enc.encode(buildVdf(o)) },
                        { name: F + 'source/' + prefix + '/config.cpp', data: cfg },
                        { name: F + 'source/' + prefix + '/$PBOPREFIX$', data: enc.encode(prefix + '\n') },
                        { name: F + 'source/' + prefix + '/' + o.code + '.txt', data: txtBytes },
                    ].concat(st ? [{ name: F + 'source/' + prefix + '/stringtable.xml', data: enc.encode(st) }] : []).concat([
                        { name: F + 'README.txt', data: enc.encode(readme(o)) },
                    ]);
                    if (jpg) { files.splice(3, 0, { name: F + 'preview.jpg', data: jpg, store: true }); }
                    return buildZip(files);
                }).then(function (zip) {
                    var file = 'COMSPEC_ATAK_Lang_' + o.code + '.zip';
                    download(file, zip, 'application/zip');
                    msg.textContent = tx('zip_done', { file: file }) + (txt.skipped ? ' ' + tx('zip_skipped', { n: txt.skipped }) : '');
                    msg.className = 'atakt-msg ' + (txt.skipped ? 'is-warn' : 'is-ok');
                }).catch(function (e) {
                    msg.textContent = tx('zip_failed', { error: e && e.message ? e.message : String(e) });
                    msg.className = 'atakt-msg is-bad';
                });
            } catch (e) {
                msg.textContent = tx('zip_failed', { error: e && e.message ? e.message : String(e) });
                msg.className = 'atakt-msg is-bad';
            }
        }, 20);
    }

    function downloadTxt() {
        var code = validCode(S.meta.code) ? S.meta.code : 'xx';
        var txt = buildTxt(S.entries, S.tr);
        download(code + '.txt', enc.encode(txt.text), 'text/plain;charset=utf-8');
        var msg = $('atakt-dl-state');
        msg.textContent = tx('zip_done', { file: code + '.txt' }) + (txt.skipped ? ' ' + tx('zip_skipped', { n: txt.skipped }) : '');
        msg.className = 'atakt-msg ' + (txt.skipped ? 'is-warn' : 'is-ok');
    }

    fetch(root.getAttribute('data-template-url'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
        .then(start)
        .catch(function (err) {
            if (window.console) { window.console.error(err); }
            var l = $('tw-loading');
            l.textContent = tx('load_error');
            l.classList.add('is-bad');
        });
})();
