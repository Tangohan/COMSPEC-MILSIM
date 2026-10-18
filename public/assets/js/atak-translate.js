/*
 * Atelier de traduction de l'ATAK natif (/traduire-atak).
 * Modèle : GET /traduire-atak/modele.json ([[français, anglais], ...], tiré de localisation/template/xx.txt).
 * Saisie gardée dans le navigateur (localStorage), vérifiée à la frappe, puis mod fabriqué sur place : PBO
 * (format BI, en-tête « prefix », SHA-1 final), mod.cpp, image d'aperçu, description Workshop, zip. Rien ne part
 * sur le serveur. Mêmes règles de fichier que tools/i18n.py (community) et fn_langApply.sqf.
 */
(function () {
    'use strict';

    var STORE = 'comspec-atakt:v1:';
    var PAGE_SIZE = 25;
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
    function check(fr, tr) {
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

    /** Fichier de traduction, dans l'ordre du modèle. Les phrases en erreur sont écrites vides (restent en anglais). */
    function buildTxt(entries, tr) {
        var lines = [];
        var skipped = 0;
        for (var i = 0; i < entries.length; i++) {
            var fr = entries[i][0];
            var t = tr[fr] || '';
            if (t && check(fr, t).errors.length) { t = ''; skipped++; }
            lines.push('[' + q(fr) + ', ' + q(t) + ', ' + q(entries[i][1]) + ']');
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

    var api = { placeholders: placeholders, check: check, parseCommunity: parseCommunity, buildTxt: buildTxt, buildConfig: buildConfig, buildModCpp: buildModCpp, buildVdf: buildVdf, buildPbo: buildPbo, buildZip: buildZip, sha1: sha1, crc32: crc32 };
    if (typeof module !== 'undefined' && module.exports) { module.exports = api; }
    if (typeof document === 'undefined') { return; }

    // ---------- Page ----------

    var root = document.getElementById('atakt');
    if (!root) { return; }
    var L = {};
    try { L = JSON.parse(root.getAttribute('data-i18n') || '{}'); } catch (e) { L = {}; }
    function tx(key, vars) {
        var s = L[key] || key;
        if (vars) { Object.keys(vars).forEach(function (k) { s = s.split('{' + k + '}').join(String(vars[k])); }); }
        return s;
    }
    function $(id) { return document.getElementById(id); }
    function el(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) { e.className = cls; }
        if (text !== undefined) { e.textContent = text; }
        return e;
    }
    var fmt = new Intl.NumberFormat(document.documentElement.lang || undefined);

    var state = {
        entries: [],
        version: root.getAttribute('data-version') || '',
        tr: {},
        issues: {},
        norm: [],
        filter: 'todo',
        query: '',
        page: 0,
        view: [],
        meta: { code: '', name: '', game: '', author: '' },
    };

    function fold(s) { return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); }

    function storageGet(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } }
    function storageSet(k, v) { try { window.localStorage.setItem(k, v); return true; } catch (e) { return false; } }
    function storageDel(k) { try { window.localStorage.removeItem(k); } catch (e) { /* rien */ } }

    var saveTimer = null;
    function scheduleSave() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(save, 600);
    }
    function save() {
        var code = state.meta.code;
        if (!validCode(code)) { return; }
        var ok = storageSet(STORE + code, JSON.stringify({ meta: state.meta, tr: state.tr, at: Date.now() }));
        storageSet(STORE + 'last', code);
        $('atakt-save-state').textContent = ok ? tx('saved') : tx('save_failed');
        $('atakt-save-state').classList.toggle('is-bad', !ok);
    }
    function loadSaved(code) {
        var raw = storageGet(STORE + code);
        if (!raw) { return null; }
        try { return JSON.parse(raw); } catch (e) { return null; }
    }

    function validCode(c) { return /^[a-z]{2}$/.test(c) && c !== 'fr' && c !== 'en'; }

    function recheck(fr) {
        var r = check(fr, state.tr[fr] || '');
        if (r.errors.length || r.warnings.length) { state.issues[fr] = r; } else { delete state.issues[fr]; }
    }

    function counts() {
        var done = 0, errors = 0, warnings = 0;
        state.entries.forEach(function (e) {
            if (state.tr[e[0]]) { done++; }
            var r = state.issues[e[0]];
            if (r) { if (r.errors.length) { errors++; } else if (r.warnings.length) { warnings++; } }
        });
        return { done: done, total: state.entries.length, errors: errors, warnings: warnings };
    }

    function matches(i) {
        var fr = state.entries[i][0];
        var t = state.tr[fr] || '';
        var r = state.issues[fr];
        switch (state.filter) {
        case 'todo': if (t) { return false; } break;
        case 'done': if (!t) { return false; } break;
        case 'errors': if (!r || !r.errors.length) { return false; } break;
        case 'warnings': if (!r || r.errors.length || !r.warnings.length) { return false; } break;
        default: break;
        }
        if (!state.query) { return true; }
        return state.norm[i].indexOf(state.query) >= 0 || fold(t).indexOf(state.query) >= 0;
    }

    function rebuildView(keepPage) {
        state.view = [];
        for (var i = 0; i < state.entries.length; i++) { if (matches(i)) { state.view.push(i); } }
        var pages = Math.max(1, Math.ceil(state.view.length / PAGE_SIZE));
        if (!keepPage) { state.page = 0; }
        if (state.page >= pages) { state.page = pages - 1; }
        renderList();
    }

    function issueLines(r) {
        var out = [];
        if (!r) { return out; }
        r.errors.forEach(function (x) { out.push(['is-error', tx(x[0], x[1])]); });
        r.warnings.forEach(function (x) { out.push(['is-warn', tx(x[0], x[1])]); });
        return out;
    }

    function renderIssues(box, fr) {
        box.textContent = '';
        issueLines(state.issues[fr]).forEach(function (l) { box.appendChild(el('li', l[0], l[1])); });
    }

    function renderRow(i) {
        var fr = state.entries[i][0];
        var en = state.entries[i][1];
        var li = el('li', 'atakt-row');
        li.value = i + 1;
        var id = 'atakt-t' + i;
        var src = el('div', 'atakt-src');
        var p1 = el('p', 'atakt-fr');
        p1.appendChild(el('span', 'atakt-tag', tx('original')));
        p1.appendChild(document.createTextNode(fr));
        var p2 = el('p', 'atakt-en');
        p2.appendChild(el('span', 'atakt-tag', tx('english')));
        p2.appendChild(document.createTextNode(en || '—'));
        src.appendChild(p1);
        src.appendChild(p2);
        var lab = el('label', 'atakt-sr', tx('translation') + ' #' + (i + 1));
        lab.setAttribute('for', id);
        var ta = el('textarea', 'atakt-input');
        ta.id = id;
        ta.rows = Math.min(6, Math.max(1, Math.ceil(Math.max(fr.length, en.length) / 70)));
        ta.placeholder = tx('placeholder_tr');
        ta.value = state.tr[fr] || '';
        ta.spellcheck = true;
        if (state.meta.code) { ta.lang = state.meta.code; }
        var box = el('ul', 'atakt-row-issues');
        ta.addEventListener('input', function () {
            var v = ta.value;
            if (v === '') { delete state.tr[fr]; } else { state.tr[fr] = v; }
            recheck(fr);
            renderIssues(box, fr);
            li.classList.toggle('is-done', v !== '');
            scheduleSave();
            renderProgress();
            scheduleStats();
        });
        var tools = el('div', 'atakt-row-tools');
        var copy = el('button', 'atakt-link', tx('copy_en'));
        copy.type = 'button';
        copy.addEventListener('click', function () { ta.value = en; ta.dispatchEvent(new Event('input')); ta.focus(); });
        var clear = el('button', 'atakt-link', tx('clear'));
        clear.type = 'button';
        clear.addEventListener('click', function () { ta.value = ''; ta.dispatchEvent(new Event('input')); ta.focus(); });
        tools.appendChild(copy);
        tools.appendChild(clear);
        li.appendChild(src);
        li.appendChild(lab);
        li.appendChild(ta);
        li.appendChild(box);
        li.appendChild(tools);
        li.classList.toggle('is-done', !!state.tr[fr]);
        renderIssues(box, fr);
        return li;
    }

    function renderList() {
        var list = $('atakt-list');
        list.textContent = '';
        var start = state.page * PAGE_SIZE;
        var slice = state.view.slice(start, start + PAGE_SIZE);
        if (!slice.length) { list.appendChild(el('li', 'atakt-empty', tx('empty_list'))); }
        slice.forEach(function (i) { list.appendChild(renderRow(i)); });
        list.start = start + 1;
        renderPager();
    }

    function renderPager() {
        var nav = $('atakt-pager');
        nav.textContent = '';
        var pages = Math.max(1, Math.ceil(state.view.length / PAGE_SIZE));
        if (pages <= 1) { return; }
        function go(p) {
            state.page = Math.max(0, Math.min(pages - 1, p));
            renderList();
            $('atakt-tr-h').scrollIntoView({ block: 'start', behavior: 'smooth' });
        }
        var prev = el('button', 'atakt-link', '‹ ' + tx('prev'));
        prev.type = 'button';
        prev.disabled = state.page === 0;
        prev.addEventListener('click', function () { go(state.page - 1); });
        var label = el('span', 'atakt-page', tx('page', { page: state.page + 1, pages: pages }));
        var next = el('button', 'atakt-link', tx('next') + ' ›');
        next.type = 'button';
        next.disabled = state.page >= pages - 1;
        next.addEventListener('click', function () { go(state.page + 1); });
        nav.appendChild(prev);
        nav.appendChild(label);
        nav.appendChild(next);
    }

    function renderProgress() {
        var c = counts();
        var pct = c.total ? Math.floor((c.done / c.total) * 1000) / 10 : 0;
        $('atakt-bar').style.width = pct + '%';
        $('atakt-progress').textContent = tx('progress', { done: fmt.format(c.done), total: fmt.format(c.total), pct: fmt.format(pct) });
        return c;
    }

    var statsTimer = null;
    function scheduleStats() { clearTimeout(statsTimer); statsTimer = setTimeout(renderStats, 400); }

    function renderStats() {
        var c = counts();
        var dl = $('atakt-stats');
        dl.textContent = '';
        [[c.done, 'stat_done', ''], [c.total - c.done, 'stat_left', 'is-muted'], [c.errors, 'stat_errors', c.errors ? 'is-error' : ''], [c.warnings, 'stat_warnings', c.warnings ? 'is-warn' : '']].forEach(function (s) {
            var d = el('div', s[2]);
            d.appendChild(el('dt', '', fmt.format(s[0])));
            d.appendChild(el('dd', '', tx(s[1])));
            dl.appendChild(d);
        });
        var ul = $('atakt-issues');
        ul.textContent = '';
        var shown = 0;
        var total = 0;
        state.entries.forEach(function (e, i) {
            var r = state.issues[e[0]];
            if (!r) { return; }
            total++;
            if (shown >= 30) { return; }
            shown++;
            var li = el('li', r.errors.length ? 'is-error' : 'is-warn');
            var b = el('button', 'atakt-link', '#' + (i + 1) + ' · ' + (e[0].length > 70 ? e[0].slice(0, 70) + '…' : e[0]));
            b.type = 'button';
            b.addEventListener('click', function () { jumpTo(i, r.errors.length ? 'errors' : 'warnings'); });
            li.appendChild(b);
            li.appendChild(el('span', '', ' ' + issueLines(r).map(function (x) { return x[1]; }).join(' ')));
            ul.appendChild(li);
        });
        if (!total) { ul.appendChild(el('li', 'is-ok', tx('issues_none'))); } else if (total > shown) { ul.appendChild(el('li', '', tx('issues_more', { n: total - shown }))); }
        renderTree();
    }

    function jumpTo(i, filter) {
        state.filter = filter;
        $('atakt-filter').value = filter;
        state.query = '';
        $('atakt-search').value = '';
        rebuildView(false);
        var pos = state.view.indexOf(i);
        if (pos >= 0) { state.page = Math.floor(pos / PAGE_SIZE); renderList(); }
        var ta = $('atakt-t' + i);
        if (ta) { ta.scrollIntoView({ block: 'center', behavior: 'smooth' }); ta.focus({ preventScroll: true }); }
    }

    function folderName() { return '@COMSPEC_ATAK_Lang_' + (state.meta.code || 'xx'); }

    function renderTree() {
        var c = state.meta.code || 'xx';
        $('atakt-tree').textContent = folderName() + '/\n'
            + '  addons/comspec_atak_lang_' + c + '.pbo\n'
            + '  keys/                      (' + (document.documentElement.lang === 'en' ? 'your .bikey' : 'votre .bikey') + ')\n'
            + '  mod.cpp\n'
            + '  preview.jpg\n'
            + '  workshop_description.txt\n'
            + '  workshop_item.vdf\n'
            + '  source/comspec_atak_lang_' + c + '/{config.cpp, $PBOPREFIX$, ' + c + '.txt}\n'
            + '  README.txt';
    }

    function applyMeta(m) {
        state.meta = { code: m.code || '', name: m.name || '', game: m.game || '', author: m.author || '' };
        $('atakt-code').value = state.meta.code;
        $('atakt-name').value = state.meta.name;
        $('atakt-game').value = state.meta.game;
        $('atakt-author').value = state.meta.author;
    }

    function setTranslations(map) {
        state.tr = {};
        state.issues = {};
        var keys = {};
        state.entries.forEach(function (e) { keys[e[0]] = true; });
        var kept = 0, dropped = 0;
        Object.keys(map || {}).forEach(function (fr) {
            if (!map[fr]) { return; }
            if (keys[fr]) { state.tr[fr] = String(map[fr]); kept++; } else { dropped++; }
        });
        Object.keys(state.tr).forEach(recheck);
        return { kept: kept, dropped: dropped };
    }

    function refreshAll() {
        renderProgress();
        renderStats();
        rebuildView(false);
    }

    function onCodeChange() {
        var c = $('atakt-code').value.trim().toLowerCase();
        $('atakt-code').value = c;
        $('atakt-code').setCustomValidity(c === '' || validCode(c) ? '' : tx('code_invalid'));
        if (!validCode(c) || c === state.meta.code) { renderTree(); return; }
        var saved = loadSaved(c);
        var had = Object.keys(state.tr).length > 0;
        if (saved && saved.tr && (!had || window.confirm(tx('confirm_switch', { code: c })))) {
            applyMeta(saved.meta || { code: c });
            state.meta.code = c;
            setTranslations(saved.tr);
            refreshAll();
        } else {
            state.meta.code = c;
            if (!state.meta.game && GAME_BY_CODE[c]) { state.meta.game = GAME_BY_CODE[c]; $('atakt-game').value = state.meta.game; }
            save();
        }
        renderTree();
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
        if (!validCode(state.meta.code)) { msg.textContent = tx('code_invalid'); msg.className = 'atakt-msg is-bad'; $('atakt-code').focus(); return false; }
        if (!state.meta.name.trim()) { msg.textContent = tx('name_missing'); msg.className = 'atakt-msg is-bad'; $('atakt-name').focus(); return false; }
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
            g.fillText(state.meta.code.toUpperCase(), 56, 440);
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
            + '4. Next COMSPEC version: resume source/comspec_atak_lang_' + o.code + '/' + o.code + '.txt on ' + o.site + '.\r\n'
            + 'The source folder only matters to rebuild the PBO with Addon Builder: leave it out of the Workshop upload if you like.\r\n\r\n'
            + 'FR\r\n'
            + '1. Chargez ' + o.folder + ' avec COMSPEC ATAK Native (Launcher > Mods > Ajouter un mod local), téléphone > Réglages > Langue.\r\n'
            + '2. Conseillé : signez addons/comspec_atak_lang_' + o.code + '.pbo avec votre propre clé (Arma 3 Tools > DSUtils), .bikey dans keys/.\r\n'
            + '3. Publiez le dossier avec Arma 3 Tools > Publisher (titre et description dans workshop_description.txt, image preview.jpg),\r\n'
            + '   puis sur la page Steam : Ajouter/Supprimer des éléments requis > COMSPEC ATAK Native et CBA_A3.\r\n'
            + '4. Version suivante de COMSPEC : reprenez source/comspec_atak_lang_' + o.code + '/' + o.code + '.txt sur ' + o.site + '.\r\n'
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
            + 'Translation by ' + (o.author || 'the community') + '. Made with ' + o.site + '\r\n';
    }

    function buildAll() {
        if (!requireMeta()) { return; }
        var msg = $('atakt-dl-state');
        msg.className = 'atakt-msg';
        msg.textContent = tx('zip_building');
        var c = counts();
        var o = {
            code: state.meta.code,
            name: state.meta.name.trim(),
            game: state.meta.game,
            author: state.meta.author.trim(),
            version: state.version,
            folder: folderName(),
            site: window.location.origin + window.location.pathname,
        };
        // Description Workshop toujours en anglais (public de Steam), quelle que soit la langue de la page.
        o.desc = o.name + ' translation of the COMSPEC ATAK Native phone for Arma 3.';
        setTimeout(function () {
            try {
                var txt = buildTxt(state.entries, state.tr);
                var txtBytes = enc.encode(txt.text);
                var cfg = enc.encode(buildConfig(o));
                var prefix = 'comspec_atak_lang_' + o.code;
                var pbo = buildPbo(prefix, [{ name: 'config.cpp', data: cfg }, { name: o.code + '.txt', data: txtBytes }], Math.floor(Date.now() / 1000));
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
                        { name: F + 'README.txt', data: enc.encode(readme(o)) },
                    ];
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
        var code = validCode(state.meta.code) ? state.meta.code : 'xx';
        var txt = buildTxt(state.entries, state.tr);
        download(code + '.txt', enc.encode(txt.text), 'text/plain;charset=utf-8');
        var msg = $('atakt-dl-state');
        msg.textContent = tx('zip_done', { file: code + '.txt' }) + (txt.skipped ? ' ' + tx('zip_skipped', { n: txt.skipped }) : '');
        msg.className = 'atakt-msg ' + (txt.skipped ? 'is-warn' : 'is-ok');
    }

    function bind() {
        var search = $('atakt-search');
        var searchTimer = null;
        search.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { state.query = fold(search.value.trim()); rebuildView(false); }, 200);
        });
        $('atakt-filter').addEventListener('change', function (e) { state.filter = e.target.value; rebuildView(false); });
        $('atakt-code').addEventListener('change', onCodeChange);
        $('atakt-name').addEventListener('input', function (e) { state.meta.name = e.target.value; scheduleSave(); });
        $('atakt-game').addEventListener('change', function (e) { state.meta.game = e.target.value; scheduleSave(); });
        $('atakt-author').addEventListener('input', function (e) { state.meta.author = e.target.value; scheduleSave(); });
        $('atakt-reset').addEventListener('click', function () {
            if (!window.confirm(tx('confirm_reset'))) { return; }
            if (state.meta.code) { storageDel(STORE + state.meta.code); }
            setTranslations({});
            refreshAll();
        });
        $('atakt-import').addEventListener('change', function (e) {
            var file = e.target.files && e.target.files[0];
            if (!file) { return; }
            var fromName = /^([a-z]{2})\.txt$/i.exec(file.name);
            file.text().then(function (text) {
                var parsed = parseCommunity(text);
                var msg = $('atakt-save-state');
                if (!parsed.lines) { msg.textContent = tx('import_none'); msg.classList.add('is-bad'); return; }
                if (fromName && !state.meta.code && validCode(fromName[1].toLowerCase())) {
                    $('atakt-code').value = fromName[1].toLowerCase();
                    state.meta.code = fromName[1].toLowerCase();
                    if (GAME_BY_CODE[state.meta.code] && !state.meta.game) { state.meta.game = GAME_BY_CODE[state.meta.code]; $('atakt-game').value = state.meta.game; }
                }
                var r = setTranslations(parsed.map);
                refreshAll();
                save();
                msg.textContent = tx('import_done', { kept: fmt.format(r.kept), dropped: fmt.format(r.dropped) });
                msg.classList.remove('is-bad');
            });
            e.target.value = '';
        });
        $('atakt-zip').addEventListener('click', buildAll);
        $('atakt-txt').addEventListener('click', downloadTxt);
    }

    function start(data) {
        state.entries = (data.entries || []).filter(function (e) { return Array.isArray(e) && typeof e[0] === 'string'; });
        if (data.version) { state.version = data.version; }
        state.norm = state.entries.map(function (e) { return fold(e[0] + '\n' + (e[1] || '')); });
        $('atakt-template-info').textContent = tx('template_info', { version: state.version || '?', total: fmt.format(state.entries.length) });
        var last = storageGet(STORE + 'last');
        var saved = last ? loadSaved(last) : null;
        if (saved) {
            applyMeta(saved.meta || { code: last });
            setTranslations(saved.tr || {});
        }
        bind();
        refreshAll();
    }

    fetch(root.getAttribute('data-template-url'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
        .then(start)
        .catch(function () {
            var info = $('atakt-template-info');
            info.textContent = tx('load_error');
            info.classList.add('is-bad');
        });
})();
