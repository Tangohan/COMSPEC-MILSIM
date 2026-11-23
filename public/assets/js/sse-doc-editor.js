/*
 * SSE document editor: hand-written contenteditable word processor (ES5, no dependency).
 * All visible labels come from the server-rendered DOM (toolbar attributes and #sse-ed-msgs), so the page translator
 * handles them. HTML that enters the editor from outside (paste, local draft) is parsed inertly with DOMParser and
 * rebuilt through the whitelist below, which mirrors App\Support\SseRichText on the server (the server re-sanitises anyway).
 */
(function () {
    'use strict';

    var root = document.getElementById('sse-ed');
    if (!root) { return; }
    var sheet = document.getElementById('sse-ed-sheet');
    var zoomer = document.getElementById('sse-ed-zoomer');
    var form = document.getElementById('sse-doc-form');
    var bodyField = document.getElementById('body');
    var bar = document.getElementById('sse-ed-bar');
    if (!sheet || !form || !bodyField || !bar) { return; }

    var MARK = '<!--sse-rich-->';
    var cfg = {};
    try { cfg = JSON.parse(document.getElementById('sse-ed-cfg').textContent) || {}; } catch (e) { cfg = {}; }
    var TEXT_COLORS = ['#0b1220', '#475569', '#b91c1c', '#b45309', '#1d4ed8', '#15803d'];
    var HILITE_COLORS = ['#fef08a', '#fed7aa', '#fecaca', '#bfdbfe', '#bbf7d0', '#e2e8f0'];
    var FIELDS = ['nom', 'grade', 'date', 'dossier', 'classification', 'reference', 'titre', 'type', 'id_fiche', 'nom_code', 'dse'];
    var LOCKABLE = /^(p|div|span|h2|h3|h4|blockquote|ul|ol|table)$/;
    var MEDIA_TOKEN = /^[0-9]{1,10}_[a-f0-9]{16}$/;
    var PAGE_PX = 1122.5; // 297 mm at 96 dpi

    function msg(name) {
        var el = document.querySelector('#sse-ed-msgs [data-msg="' + name + '"]');
        return el ? el.textContent : '';
    }
    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function each(list, fn) { Array.prototype.forEach.call(list, fn); }
    function byId(id) { return document.getElementById(id); }

    /* ---------- whitelist cleaner (DOM -> string) ---------- */
    var TAGS = {
        p: 'p', div: 'div', br: 'br', hr: 'hr', span: 'span', h1: 'h2', h2: 'h2', h3: 'h3', h4: 'h4', h5: 'h4', h6: 'h4',
        blockquote: 'blockquote', strong: 'strong', b: 'strong', em: 'em', i: 'em', u: 'u', s: 's', strike: 's', del: 's',
        sub: 'sub', sup: 'sup', mark: 'span', ul: 'ul', ol: 'ol', li: 'li', table: 'table', thead: 'thead', tbody: 'tbody',
        tfoot: 'tbody', tr: 'tr', td: 'td', th: 'th', caption: '', colgroup: '', col: ''
    };
    var DROP = { script: 1, style: 1, iframe: 1, object: 1, embed: 1, svg: 1, math: 1, template: 1, noscript: 1, textarea: 1,
        select: 1, option: 1, button: 1, input: 1, form: 1, link: 1, meta: 1, base: 1, head: 1, title: 1, img: 1, video: 1, audio: 1, canvas: 1 };
    var ALIGNS = { left: 1, right: 1, center: 1, justify: 1 };

    function paletteColor(v, palette) {
        v = String(v).replace(/\s+/g, '').toLowerCase();
        var m = v.match(/^rgba?\((\d+),(\d+),(\d+)(?:,1(?:\.0+)?)?\)$/);
        if (m) {
            v = '#' + [m[1], m[2], m[3]].map(function (n) {
                var h = Math.min(255, parseInt(n, 10)).toString(16);
                return h.length < 2 ? '0' + h : h;
            }).join('');
        }
        return palette.indexOf(v) >= 0 ? v : null;
    }

    function cleanStyle(raw, tag) {
        var out = {};
        String(raw || '').split(';').forEach(function (decl) {
            var i = decl.indexOf(':');
            if (i < 0) { return; }
            var prop = decl.slice(0, i).trim().toLowerCase();
            var val = decl.slice(i + 1).trim().toLowerCase();
            if (prop === 'text-align' && ALIGNS[val] && /^(p|div|h2|h3|h4|blockquote|li|td|th|ul|ol)$/.test(tag)) { out['text-align'] = val; }
            else if (prop === 'line-height' && /^\d(\.\d{1,2})?$/.test(val) && parseFloat(val) >= 1 && parseFloat(val) <= 3) { out['line-height'] = val; }
            else if (prop === 'color') { var c = paletteColor(val, TEXT_COLORS); if (c) { out.color = c; } }
            else if (prop === 'background-color' || prop === 'background') { var b = paletteColor(val, HILITE_COLORS); if (b) { out['background-color'] = b; } }
        });
        var s = '';
        for (var k in out) { if (Object.prototype.hasOwnProperty.call(out, k)) { s += k + ':' + out[k] + ';'; } }
        return s;
    }

    function cleanNode(node, depth) {
        if (node.nodeType === 3) { return esc(node.data); }
        if (node.nodeType !== 1) { return ''; }
        var name = node.nodeName.toLowerCase();
        if (DROP[name]) { return ''; }
        var inner = function () {
            if (depth > 24) { return esc(node.textContent); }
            var buf = '';
            for (var c = node.firstChild; c; c = c.nextSibling) { buf += cleanNode(c, depth + 1); }
            return buf;
        };
        if (!Object.prototype.hasOwnProperty.call(TAGS, name)) { return inner(); }
        var tag = TAGS[name];
        if (tag === '') { return inner(); }
        if (tag === 'br' || tag === 'hr') { return '<' + tag + '>'; }
        var cls = (node.getAttribute('class') || '').split(/\s+/);
        var attrs = '';
        if (tag === 'div' && cls.indexOf('sse-pagebreak') >= 0) { return '<div class="sse-pagebreak"></div>'; }
        if (tag === 'div' && cls.indexOf('sse-media') >= 0) {
            var tok = node.getAttribute('data-media') || '';
            return MEDIA_TOKEN.test(tok) ? '<div class="sse-media" data-media="' + tok + '"></div>' : '';
        }
        if (tag === 'span' && cls.indexOf('sse-field') >= 0) {
            var f = (node.getAttribute('data-field') || '').toLowerCase();
            if (FIELDS.indexOf(f) >= 0) {
                return '<span class="sse-field" data-field="' + f + '">' + esc(node.textContent) + '</span>';
            }
        }
        if (tag === 'span' && cls.indexOf('sse-hint') >= 0 && cls.indexOf('sse-field') < 0) {
            return '<span class="sse-hint">' + esc(node.textContent) + '</span>';
        }
        if (tag === 'td' || tag === 'th') {
            ['colspan', 'rowspan'].forEach(function (a) {
                var v = node.getAttribute(a);
                if (v && /^\d{1,2}$/.test(v) && parseInt(v, 10) >= 2 && parseInt(v, 10) <= 20) { attrs += ' ' + a + '="' + parseInt(v, 10) + '"'; }
            });
        }
        if (node.getAttribute('data-lock') === '1' && LOCKABLE.test(tag)) { attrs += ' data-lock="1"'; }
        var style = cleanStyle(node.getAttribute('style'), tag);
        if (name === 'mark' && style.indexOf('background-color') < 0) { style += 'background-color:' + HILITE_COLORS[0] + ';'; }
        if (style) { attrs += ' style="' + style + '"'; }
        var body = inner();
        if (tag === 'span' && !attrs) { return body; }
        if (tag === 'div' && !attrs) { return '<div>' + body + '</div>'; }
        return '<' + tag + attrs + '>' + body + '</' + tag + '>';
    }

    function cleanHtml(html) {
        var doc = new DOMParser().parseFromString('<!doctype html><body>' + html, 'text/html');
        var out = '';
        for (var c = doc.body.firstChild; c; c = c.nextSibling) { out += cleanNode(c, 0); }
        return out;
    }
    function cleanSheet() {
        var out = '';
        for (var c = sheet.firstChild; c; c = c.nextSibling) { out += cleanNode(c, 0); }
        return out;
    }
    function sheetText() { return (sheet.innerText || sheet.textContent || '').replace(/ /g, ' '); }

    /* ---------- selection helpers ---------- */
    var savedRange = null;
    function inSheet(node) { return node && (node === sheet || sheet.contains(node)); }
    function rememberSelection() {
        var sel = window.getSelection();
        if (sel && sel.rangeCount && inSheet(sel.getRangeAt(0).commonAncestorContainer)) { savedRange = sel.getRangeAt(0).cloneRange(); }
    }
    function restoreSelection() {
        sheet.focus();
        if (savedRange) {
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(savedRange);
        }
    }
    function currentRange() {
        var sel = window.getSelection();
        if (sel && sel.rangeCount && inSheet(sel.getRangeAt(0).commonAncestorContainer)) { return sel.getRangeAt(0); }
        return null;
    }
    function offsets() {
        var r = currentRange();
        if (!r) { return null; }
        var pre = document.createRange();
        pre.selectNodeContents(sheet);
        pre.setEnd(r.startContainer, r.startOffset);
        var a = pre.toString().length;
        return [a, a + r.toString().length];
    }
    function setOffsets(o) {
        if (!o) { return; }
        var walker = document.createTreeWalker(sheet, NodeFilter.SHOW_TEXT, null, false);
        var pos = 0, n, startSet = false, range = document.createRange();
        range.selectNodeContents(sheet);
        range.collapse(false);
        while ((n = walker.nextNode())) {
            var len = n.data.length;
            if (!startSet && o[0] <= pos + len) { range.setStart(n, o[0] - pos); startSet = true; }
            if (startSet && o[1] <= pos + len) { range.setEnd(n, o[1] - pos); break; }
            pos += len;
        }
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    }
    function placeCaret(el, atEnd) {
        var r = document.createRange();
        r.selectNodeContents(el);
        r.collapse(!atEnd);
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(r);
        savedRange = r.cloneRange();
    }
    function closest(node, sel) {
        while (node && node !== sheet) {
            if (node.nodeType === 1 && (node.matches || node.msMatchesSelector).call(node, sel)) { return node; }
            node = node.parentNode;
        }
        return null;
    }

    /* ---------- history (snapshots) ---------- */
    var undoStack = [], redoStack = [], lastSnap = null, snapTimer = null, restoring = false;
    function snapshot(force) {
        if (restoring) { return; }
        var html = sheet.innerHTML;
        if (lastSnap && lastSnap.html === html && !force) { return; }
        if (lastSnap) { undoStack.push(lastSnap); if (undoStack.length > 200) { undoStack.shift(); } }
        lastSnap = { html: html, sel: offsets() };
        redoStack = [];
        refreshUndoButtons();
    }
    function scheduleSnapshot() {
        window.clearTimeout(snapTimer);
        snapTimer = window.setTimeout(function () { snapshot(false); }, 450);
    }
    function applySnap(s) {
        restoring = true;
        sheet.innerHTML = s.html;
        lastSnap = s;
        sheet.focus();
        setOffsets(s.sel);
        restoring = false;
        changed(true);
    }
    function undo() {
        snapshot(false);
        if (!undoStack.length) { return; }
        redoStack.push({ html: sheet.innerHTML, sel: offsets() });
        applySnap(undoStack.pop());
        refreshUndoButtons();
    }
    function redo() {
        if (!redoStack.length) { return; }
        undoStack.push({ html: sheet.innerHTML, sel: offsets() });
        applySnap(redoStack.pop());
        refreshUndoButtons();
    }
    function refreshUndoButtons() {
        var u = bar.querySelector('[data-cmd="undo"]'), r = bar.querySelector('[data-cmd="redo"]');
        if (u) { u.disabled = !undoStack.length; }
        if (r) { r.disabled = !redoStack.length; }
    }

    /* ---------- state, counters, page layout ---------- */
    var dirty = false, listeners = [];
    function layoutBreaks() {
        var breaks = sheet.querySelectorAll('.sse-pagebreak');
        each(breaks, function (b) { b.style.height = '0px'; });
        var scale = parseFloat(zoomer.getAttribute('data-zoom') || '1') || 1;
        each(breaks, function (b) {
            var top = b.offsetTop;
            var rest = PAGE_PX - (top % PAGE_PX);
            b.style.height = Math.max(12, Math.round(rest)) + 'px';
        });
        return scale;
    }
    function counters() {
        var text = sheetText().trim();
        var words = text ? text.split(/\s+/).length : 0;
        var pages = Math.max(1, Math.ceil((sheet.scrollHeight - 2) / PAGE_PX));
        var w = byId('sse-ed-words'), p = byId('sse-ed-pages');
        if (w) { w.textContent = String(words); }
        if (p) { p.textContent = String(pages); }
        var hc = byId('sse-ed-hints');
        if (hc) { hc.textContent = String(sheet.querySelectorAll('.sse-hint').length); }
    }
    function normalizeRoot() {
        if (!sheet.firstChild || (sheet.childNodes.length === 1 && sheet.firstChild.nodeName === 'BR')) {
            sheet.innerHTML = '<p><br></p>';
            placeCaret(sheet.firstChild, false);
            return;
        }
        // Loose text or inline content at the top level is wrapped into a paragraph.
        var loose = false;
        for (var c = sheet.firstChild; c; c = c.nextSibling) {
            if (c.nodeType === 3 && c.data.trim() !== '') { loose = true; }
            if (c.nodeType === 1 && /^(span|strong|em|u|s|sub|sup|b|i|font)$/i.test(c.nodeName)) { loose = true; }
        }
        if (loose) { try { document.execCommand('formatBlock', false, 'p'); } catch (e) { /* ignore */ } }
    }
    function changed(noSnap) {
        dirty = true;
        if (!noSnap) { scheduleSnapshot(); }
        layoutBreaks();
        counters();
        refreshState();
        scheduleAutosave();
        listeners.forEach(function (fn) { try { fn(); } catch (e) { /* ignore */ } });
    }

    /* ---------- commands ---------- */
    function css(on) { try { document.execCommand('styleWithCSS', false, !!on); } catch (e) { /* ignore */ } }
    function exec(cmd, val, withCss) {
        restoreSelection();
        css(withCss);
        try { document.execCommand(cmd, false, val == null ? null : val); } catch (e) { /* ignore */ }
        snapshot(false);
        changed(true);
        rememberSelection();
    }
    function blocksInSelection() {
        var r = currentRange() || savedRange;
        if (!r) { return []; }
        var all = sheet.querySelectorAll('p,h2,h3,h4,blockquote,li,td,th');
        var res = [];
        each(all, function (el) {
            if (!r.intersectsNode(el)) { return; }
            if (el.querySelector('p,h2,h3,h4,blockquote,li,td,th')) { return; }
            res.push(el);
        });
        return res;
    }
    function setLineHeight(v) {
        restoreSelection();
        blocksInSelection().forEach(function (el) { el.style.lineHeight = v === '1' ? '' : v; });
        snapshot(false);
        changed(true);
    }
    function insertHtml(html) {
        restoreSelection();
        css(false);
        document.execCommand('insertHTML', false, html);
        snapshot(false);
        changed(true);
        rememberSelection();
    }
    function insertText(text) {
        var lines = String(text).replace(/\r\n?/g, '\n').split('\n');
        var html = '';
        var para = [];
        function flush() { if (para.length) { html += '<p>' + para.join('<br>') + '</p>'; para = []; } }
        lines.forEach(function (l) { if (l.trim() === '') { flush(); } else { para.push(esc(l)); } });
        flush();
        insertHtml(html || '');
    }

    function fieldValue(name) {
        var f = cfg.fields || {};
        if (name === 'classification') {
            var c = byId('classification');
            return c && c.selectedIndex >= 0 ? c.options[c.selectedIndex].text : '';
        }
        if (name === 'type') {
            var ty = byId('document_type');
            return ty && ty.selectedIndex >= 0 ? ty.options[ty.selectedIndex].text : '';
        }
        if (name === 'titre') {
            var tt = byId('title');
            return tt ? tt.value.trim() : '';
        }
        if (name === 'dossier') {
            var k = byId('case_id');
            return k && k.value ? (k.options[k.selectedIndex].getAttribute('data-ref') || '') : '';
        }
        return f[name] || '';
    }
    function insertField(name) {
        if (FIELDS.indexOf(name) < 0) { return; }
        var v = fieldValue(name) || msg('field-' + name);
        insertHtml('<span class="sse-field" data-field="' + name + '" contenteditable="false">' + esc(v) + '</span>&nbsp;');
    }
    function refreshFields() {
        each(sheet.querySelectorAll('.sse-field'), function (el) {
            var n = el.getAttribute('data-field');
            if (n === 'classification' || n === 'dossier' || n === 'titre' || n === 'reference' || n === 'type' || n === 'id_fiche' || n === 'nom_code' || n === 'dse') {
                var v = fieldValue(n);
                if (v && el.textContent !== v) { el.textContent = v; }
            }
            el.setAttribute('contenteditable', 'false');
        });
    }

    /* ---------- images and logos : uploaded to the private store, the page only keeps a token ---------- */
    function mediaBlock(token) {
        return '<div class="sse-media" data-media="' + token + '" contenteditable="false"><img src="' + esc((cfg.mediaUrl || '') + '/' + token) + '" alt="' + esc(msg('media-alt')) + '"></div><p><br></p>';
    }
    function showMedia() {
        each(sheet.querySelectorAll('.sse-media'), function (el) {
            var tok = el.getAttribute('data-media') || '';
            el.setAttribute('contenteditable', 'false');
            if (MEDIA_TOKEN.test(tok) && !el.querySelector('img')) {
                var im = document.createElement('img');
                im.setAttribute('src', (cfg.mediaUrl || '') + '/' + tok);
                im.setAttribute('alt', msg('media-alt'));
                el.appendChild(im);
            }
        });
    }
    function uploadMedia(file) {
        if (!file || !cfg.mediaUrl) { return; }
        if (!/^image\/(png|jpeg|webp)$/.test(file.type)) { stateText('media-type'); return; }
        if (file.size > 3 * 1024 * 1024) { stateText('media-size'); return; }
        var data = new FormData();
        var tk = form.querySelector('input[name="_csrf_token"]');
        data.append('_csrf_token', tk ? tk.value : '');
        data.append('image', file);
        stateText('media-sending');
        var xhr = new XMLHttpRequest();
        xhr.open('POST', cfg.mediaUrl);
        xhr.onload = function () {
            var r = null;
            try { r = JSON.parse(xhr.responseText); } catch (e) { r = null; }
            if (xhr.status === 200 && r && r.ok && MEDIA_TOKEN.test(r.token || '')) {
                restoreSelection();
                insertHtml(mediaBlock(r.token));
                stateText('ready');
            } else {
                var el = document.querySelector('#sse-ed-msgs [data-msg="media-error"]');
                if (el && r && r.error) { el.setAttribute('data-detail', r.error); }
                stateText('media-error');
            }
        };
        xhr.onerror = function () { stateText('media-error'); };
        xhr.send(data);
    }
    var mediaInput = byId('sse-ed-media-file');
    if (mediaInput) {
        mediaInput.addEventListener('change', function () { if (mediaInput.files && mediaInput.files[0]) { uploadMedia(mediaInput.files[0]); } mediaInput.value = ''; });
    }

    function insertPageBreak() {
        insertHtml('<div class="sse-pagebreak" contenteditable="false"></div><p><br></p>');
    }

    /* tables */
    function buildTable(rows, cols) {
        var h = '<table><tbody>';
        for (var r = 0; r < rows; r++) {
            h += '<tr>';
            for (var c = 0; c < cols; c++) { h += '<td><br></td>'; }
            h += '</tr>';
        }
        return h + '</tbody></table><p><br></p>';
    }
    function currentCell() {
        var r = currentRange() || savedRange;
        return r ? closest(r.startContainer, 'td,th') : null;
    }
    function tableOp(op) {
        restoreSelection();
        var cell = currentCell();
        if (!cell) { return; }
        var row = cell.parentNode, table = closest(cell, 'table');
        var idx = Array.prototype.indexOf.call(row.children, cell);
        var cells = row.children.length;
        function emptyRow() {
            var tr = document.createElement('tr');
            for (var i = 0; i < cells; i++) { var td = document.createElement('td'); td.appendChild(document.createElement('br')); tr.appendChild(td); }
            return tr;
        }
        if (op === 'row-add') { row.parentNode.insertBefore(emptyRow(), row.nextSibling); }
        else if (op === 'row-del') {
            if (table.rows.length <= 1) { table.parentNode.removeChild(table); } else {
                var nxt = row.nextElementSibling || row.previousElementSibling;
                row.parentNode.removeChild(row);
                if (nxt) { placeCaret(nxt.children[Math.min(idx, nxt.children.length - 1)], false); }
            }
        } else if (op === 'col-add') {
            each(table.rows, function (tr) {
                var td = document.createElement('td');
                td.appendChild(document.createElement('br'));
                tr.insertBefore(td, tr.children[idx + 1] || null);
            });
        } else if (op === 'col-del') {
            if (cells <= 1) { table.parentNode.removeChild(table); } else {
                each(table.rows, function (tr) { if (tr.children[idx]) { tr.removeChild(tr.children[idx]); } });
            }
        } else if (op === 'table-del') { table.parentNode.removeChild(table); }
        normalizeRoot();
        snapshot(false);
        changed(true);
    }
    function onTab(e) {
        var cell = currentCell();
        if (!cell) { return false; }
        e.preventDefault();
        var cells = Array.prototype.slice.call(closest(cell, 'table').querySelectorAll('td,th'));
        var i = cells.indexOf(cell) + (e.shiftKey ? -1 : 1);
        if (i >= cells.length) { tableOp('row-add'); cells = Array.prototype.slice.call(closest(cell, 'table').querySelectorAll('td,th')); }
        if (cells[i]) { placeCaret(cells[i], false); }
        return true;
    }

    /* ---------- toolbar wiring ---------- */
    var STATE_CMDS = { bold: 'bold', italic: 'italic', underline: 'underline', strike: 'strikeThrough', ul: 'insertUnorderedList',
        ol: 'insertOrderedList', 'align-left': 'justifyLeft', 'align-center': 'justifyCenter', 'align-right': 'justifyRight', 'align-justify': 'justifyFull' };
    var SIMPLE = { bold: 'bold', italic: 'italic', underline: 'underline', strike: 'strikeThrough', ul: 'insertUnorderedList', ol: 'insertOrderedList',
        hr: 'insertHorizontalRule' };
    var ALIGN_CMD = { 'align-left': 'justifyLeft', 'align-center': 'justifyCenter', 'align-right': 'justifyRight', 'align-justify': 'justifyFull' };

    function refreshState() {
        each(bar.querySelectorAll('[data-cmd]'), function (b) {
            var c = STATE_CMDS[b.getAttribute('data-cmd')];
            if (!c || b.tagName === 'SELECT') { return; }
            var on = false;
            try { on = document.queryCommandState(c); } catch (e) { on = false; }
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        var sel = bar.querySelector('[data-cmd="block"]');
        var r = currentRange();
        if (sel && r) {
            var b = closest(r.startContainer, 'h2,h3,h4,blockquote,p,li');
            var v = b ? b.nodeName.toLowerCase() : 'p';
            sel.value = (v === 'li') ? 'p' : v;
            if (sel.value !== v && v !== 'li') { sel.value = 'p'; }
        }
    }

    function closePops(except) {
        each(root.querySelectorAll('.sse-ed__pop'), function (p) {
            if (p !== except) {
                p.hidden = true;
                var t = root.querySelector('[aria-controls="' + p.id + '"]');
                if (t) { t.setAttribute('aria-expanded', 'false'); }
            }
        });
    }
    function togglePop(btn) {
        var pop = byId(btn.getAttribute('aria-controls'));
        if (!pop) { return; }
        var open = pop.hidden;
        closePops(open ? pop : null);
        pop.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            var first = pop.querySelector('button,input');
            if (first) { first.focus(); }
        }
    }

    function runCommand(el) {
        var cmd = el.getAttribute('data-cmd');
        if (SIMPLE[cmd]) { exec(SIMPLE[cmd], null, false); return; }
        if (ALIGN_CMD[cmd]) { exec(ALIGN_CMD[cmd], null, true); return; }
        switch (cmd) {
            case 'undo': undo(); sheet.focus(); break;
            case 'redo': redo(); sheet.focus(); break;
            case 'block': exec('formatBlock', '<' + el.value + '>', false); break;
            case 'lineheight': setLineHeight(el.value); break;
            case 'field': if (el.value) { insertField(el.value); el.value = ''; } break;
            case 'color': exec('foreColor', el.getAttribute('data-value'), true); closePops(null); break;
            case 'hilite':
                restoreSelection();
                css(true);
                try { if (!document.execCommand('hiliteColor', false, el.getAttribute('data-value'))) { document.execCommand('backColor', false, el.getAttribute('data-value')); } } catch (e) { /* ignore */ }
                snapshot(false); changed(true); closePops(null); break;
            case 'clear': exec('removeFormat', null, false); break;
            case 'pagebreak': insertPageBreak(); break;
            case 'media': rememberSelection(); if (mediaInput) { mediaInput.click(); } break;
            case 'lock': lockSelection(true); break;
            case 'unlock': lockSelection(false); break;
            case 'redact': redactSelection(); break;
            case 'table-insert':
                var rows = Math.max(1, Math.min(20, parseInt(byId('sse-ed-rows').value, 10) || 3));
                var cols = Math.max(1, Math.min(8, parseInt(byId('sse-ed-cols').value, 10) || 3));
                insertHtml(buildTable(rows, cols));
                closePops(null);
                break;
            case 'row-add': case 'row-del': case 'col-add': case 'col-del': case 'table-del': tableOp(cmd); break;
            case 'print': window.print(); break;
            default: break;
        }
    }
    function redactSelection() {
        restoreSelection();
        var r = currentRange();
        if (!r || r.collapsed) { return; }
        var t = r.toString();
        document.execCommand('insertText', false, '[[' + t + ']]');
        snapshot(false);
        changed(true);
    }

    bar.addEventListener('mousedown', function (e) {
        var t = e.target;
        if (t.tagName === 'SELECT' || t.tagName === 'INPUT') { rememberSelection(); return; }
        if (t.closest && t.closest('[data-cmd],[aria-controls]')) { rememberSelection(); e.preventDefault(); }
    });
    root.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('button') : null;
        if (!t || !root.contains(t)) { return; }
        if (t.getAttribute('aria-controls')) { togglePop(t); return; }
        if (t.hasAttribute('data-cmd')) { runCommand(t); }
    });
    bar.addEventListener('change', function (e) {
        var t = e.target;
        if (t.tagName === 'SELECT' && t.hasAttribute('data-cmd')) { runCommand(t); }
    });
    root.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var open = root.querySelector('.sse-ed__pop:not([hidden])');
            if (open) {
                var t = root.querySelector('[aria-controls="' + open.id + '"]');
                closePops(null);
                if (t) { t.focus(); }
                e.stopPropagation();
            }
        }
    });
    document.addEventListener('click', function (e) {
        if (!root.contains(e.target)) { closePops(null); }
    });

    /* roving tabindex on the toolbar (arrow keys, Home, End) */
    var tbItems = [];
    function tbCollect() {
        tbItems = [];
        each(bar.querySelectorAll('button,select'), function (el) { if (!el.disabled && !el.closest('.sse-ed__pop')) { tbItems.push(el); } });
        tbItems.forEach(function (el, i) { el.setAttribute('tabindex', i === 0 ? '0' : '-1'); });
    }
    bar.addEventListener('keydown', function (e) {
        if (e.target.tagName === 'INPUT' || e.target.closest('.sse-ed__pop')) { return; }
        var i = tbItems.indexOf(e.target);
        if (i < 0) { return; }
        var n = -1;
        if (e.key === 'ArrowRight') { n = (i + 1) % tbItems.length; }
        else if (e.key === 'ArrowLeft') { n = (i + tbItems.length - 1) % tbItems.length; }
        else if (e.key === 'Home') { n = 0; }
        else if (e.key === 'End') { n = tbItems.length - 1; }
        if (n >= 0) {
            e.preventDefault();
            tbItems[i].setAttribute('tabindex', '-1');
            tbItems[n].setAttribute('tabindex', '0');
            tbItems[n].focus();
        }
    });

    /* ---------- locked text (data-lock) : islands the server also enforces ---------- */
    var lockRef = [], lockGoodHtml = '', selectedLock = null;
    function lockedBlocks() { return sheet.querySelectorAll('[data-lock="1"]'); }
    function lockSig() {
        var out = [];
        each(lockedBlocks(), function (el) {
            var c = el.cloneNode(true);
            each(c.querySelectorAll('.sse-field'), function (f) { f.textContent = ''; });
            out.push(c.textContent.replace(/\s+/g, ' ').trim());
        });
        return out;
    }
    function lockSetup() {
        each(lockedBlocks(), function (el) { el.setAttribute('contenteditable', 'false'); el.setAttribute('title', msg('lock-title')); });
    }
    function lockSnapshot() { lockSetup(); lockRef = lockSig(); lockGoodHtml = sheet.innerHTML; }
    /* Without the right to unlock, any change that alters, moves or removes a locked block is cancelled on the spot. */
    function lockGuard() {
        if (cfg.canUnlock || !lockRef.length) { lockSnapshot(); return true; }
        var now = lockSig(), i = 0, j = 0;
        for (; i < lockRef.length; i++) {
            while (j < now.length && now[j] !== lockRef[i]) { j++; }
            if (j >= now.length) { break; }
            j++;
        }
        if (i < lockRef.length) {
            restoring = true;
            sheet.innerHTML = lockGoodHtml;
            restoring = false;
            lockSetup();
            stateText('lock-refused');
            return false;
        }
        lockSnapshot();
        return true;
    }
    function lockSelection(on) {
        restoreSelection();
        var r = currentRange();
        var n = r ? r.startContainer : null;
        while (n && n.parentNode !== sheet) { n = n.parentNode; }
        var el = on ? n : selectedLock;
        if (!el || el.nodeType !== 1 || !LOCKABLE.test(el.nodeName.toLowerCase())) { return; }
        if (on) { el.setAttribute('data-lock', '1'); }
        else if (cfg.canUnlock) { el.removeAttribute('data-lock'); el.removeAttribute('contenteditable'); el.removeAttribute('title'); el.classList.remove('is-selected'); selectedLock = null; }
        else { stateText('lock-forbidden'); return; }
        lockSnapshot();
        snapshot(false);
        changed(true);
    }
    sheet.addEventListener('click', function (e) {
        var el = e.target.closest ? e.target.closest('[data-lock="1"]') : null;
        if (selectedLock) { selectedLock.classList.remove('is-selected'); }
        selectedLock = el && sheet.contains(el) ? el : null;
        if (selectedLock) { selectedLock.classList.add('is-selected'); }
    });
    sheet.addEventListener('beforeinput', function (e) {
        if (cfg.canUnlock || String(e.inputType || '').indexOf('delete') !== 0) { return; }
        var r = currentRange();
        if (!r) { return; }
        var hit = false;
        each(lockedBlocks(), function (el) { if (r.intersectsNode(el)) { hit = true; } });
        if (hit) { e.preventDefault(); stateText('lock-forbidden'); }
    });

    /* ---------- editing events ---------- */
    sheet.addEventListener('input', function () { normalizeRoot(); if (!lockGuard()) { return; } changed(false); });
    sheet.addEventListener('keyup', function () { rememberSelection(); refreshState(); });
    sheet.addEventListener('mouseup', function () { rememberSelection(); refreshState(); });
    document.addEventListener('selectionchange', function () { if (document.activeElement === sheet) { rememberSelection(); } });
    sheet.addEventListener('beforeinput', function (e) {
        if (e.inputType === 'historyUndo') { e.preventDefault(); undo(); }
        else if (e.inputType === 'historyRedo') { e.preventDefault(); redo(); }
    });
    sheet.addEventListener('keydown', function (e) {
        var mod = e.ctrlKey || e.metaKey;
        var k = (e.key || '').toLowerCase();
        if (mod && k === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
        else if (mod && (k === 'y' || (k === 'z' && e.shiftKey))) { e.preventDefault(); redo(); }
        else if (mod && k === 's') { e.preventDefault(); submitForm(); }
        else if (mod && k === 'b') { e.preventDefault(); exec('bold', null, false); }
        else if (mod && k === 'i') { e.preventDefault(); exec('italic', null, false); }
        else if (mod && k === 'u') { e.preventDefault(); exec('underline', null, false); }
        else if (e.key === 'Tab' && currentCell()) { onTab(e); }
    });
    // Ctrl+S also works when the focus is elsewhere in the form.
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key || '').toLowerCase() === 's' && form.contains(e.target) && e.target !== sheet) {
            e.preventDefault();
            submitForm();
        }
    });
    sheet.addEventListener('paste', function (e) {
        var cd = e.clipboardData;
        if (!cd) { return; }
        e.preventDefault();
        var html = cd.getData('text/html');
        if (html) { insertHtml(cleanHtml(html)); return; }
        var text = cd.getData('text/plain');
        if (text) { insertText(text); }
    });
    sheet.addEventListener('drop', function (e) { e.preventDefault(); });
    sheet.addEventListener('dragover', function (e) { e.preventDefault(); });

    /* ---------- zoom ---------- */
    var zoomSel = byId('sse-ed-zoom');
    function setZoom(pct) {
        var z = Math.max(0.5, Math.min(2, pct / 100));
        zoomer.style.zoom = String(z);
        zoomer.setAttribute('data-zoom', String(z));
    }
    if (zoomSel) {
        zoomSel.addEventListener('change', function () { setZoom(parseInt(zoomSel.value, 10) || 100); });
        // narrow screens start fitted to the width
        if (window.innerWidth < 900 && !zoomSel.getAttribute('data-touched')) {
            var fit = Math.max(50, Math.min(100, Math.floor((window.innerWidth - 32) / 794 * 100 / 5) * 5));
            zoomSel.value = String(fit);
            if (zoomSel.value !== String(fit)) { zoomSel.value = '50'; }
            setZoom(parseInt(zoomSel.value, 10));
        }
    }

    /* ---------- ruler ---------- */
    var ruler = byId('sse-ed-ruler');
    if (ruler) {
        var ticks = '';
        for (var cm = 0; cm <= 21; cm++) { ticks += '<span style="left:' + (cm * 37.795) + 'px">' + (cm % 2 === 0 ? cm : '') + '</span>'; }
        ruler.innerHTML = ticks;
    }

    /* ---------- autosave ---------- */
    var docId = parseInt(root.getAttribute('data-doc-id') || '0', 10) || 0;
    var lsKey = 'sse-doc-draft:' + docId;
    var saveTimer = null, serverTimer = null, inflight = false;
    function stateText(name, extra) {
        var el = byId('sse-ed-save');
        if (el) { el.textContent = msg(name) + (extra || ''); }
        root.setAttribute('data-save', name);
    }
    function clock() {
        var d = new Date();
        function z(n) { return n < 10 ? '0' + n : String(n); }
        return ' ' + z(d.getHours()) + ':' + z(d.getMinutes());
    }
    function gather() {
        var f = function (id) { var e = byId(id); return e ? e.value : ''; };
        return { title: f('title'), type: f('document_type'), cls: f('classification'), caseId: f('case_id'), html: cleanSheet() };
    }
    function saveLocal() {
        try {
            var g = gather();
            g.t = Date.now();
            window.localStorage.setItem(lsKey, JSON.stringify(g));
            return true;
        } catch (e) { return false; }
    }
    function saveServer() {
        if (!cfg.draftUrl || inflight || !dirty) { return; }
        var g = gather();
        var data = new FormData();
        data.append('_csrf_token', (form.querySelector('input[name="_csrf_token"]') || {}).value || '');
        data.append('doc_id', String(docId));
        data.append('title', g.title);
        data.append('document_type', g.type);
        data.append('classification', g.cls);
        data.append('case_id', g.caseId);
        data.append('body', MARK + g.html);
        inflight = true;
        fetch(cfg.draftUrl, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                inflight = false;
                if (j && j.ok) { dirty = false; stateText('saved-server', clock()); }
                else { stateText('saved-local', clock()); }
            })
            .catch(function () { inflight = false; stateText('saved-local', clock()); });
    }
    function scheduleAutosave() {
        stateText('typing');
        window.clearTimeout(saveTimer);
        window.clearTimeout(serverTimer);
        saveTimer = window.setTimeout(function () {
            if (saveLocal()) { stateText('saved-local', clock()); }
        }, 1200);
        serverTimer = window.setTimeout(saveServer, 5000);
    }
    each(['title', 'document_type', 'classification', 'case_id'], function (id) {
        var el = byId(id);
        if (el) { el.addEventListener('change', function () { refreshFields(); dirty = true; scheduleAutosave(); syncClassBanner(); }); }
    });
    var titleEl = byId('title');
    if (titleEl) { titleEl.addEventListener('input', function () { dirty = true; scheduleAutosave(); refreshFields(); refreshFieldList(); }); }

    /* classification banner (always visible in the editor) */
    function syncClassBanner() {
        var c = byId('classification'), b = byId('sse-ed-class');
        if (!c || !b) { return; }
        var label = c.options[c.selectedIndex] ? c.options[c.selectedIndex].text : '';
        var strong = b.querySelector('strong');
        if (strong) { strong.textContent = label.toUpperCase(); }
        each(root.querySelectorAll('.sse-ed__pband'), function (pb) { pb.textContent = label.toUpperCase(); });
        each(root.querySelectorAll('.sse-ed__pband--top'), function (pb) { pb.setAttribute('data-stamp', label.toUpperCase()); });
        var pg = byId('sse-ed-page');
        if (pg) { pg.setAttribute('data-level', c.value); pg.setAttribute('data-wm', label.toUpperCase() + (cfg.refText ? ' · ' + cfg.refText : '')); }
        b.setAttribute('data-level', c.value);
    }

    /* ---------- sheet theme (paper, bands, watermark, typeface), saved with the document ---------- */
    function setTheme(id) {
        var pg = byId('sse-ed-page');
        var found = false;
        each(document.querySelectorAll('input[name="sheet_theme"]'), function (r) { if (r.value === id) { r.checked = true; found = true; } });
        if (!found || !pg) { return; }
        pg.className = pg.className.replace(/(^|\s)sse-theme--[a-z_]+/g, '').trim() + ' sse-theme--' + id;
    }
    each(document.querySelectorAll('input[name="sheet_theme"]'), function (r) {
        r.addEventListener('change', function () { if (r.checked) { setTheme(r.value); dirty = true; scheduleAutosave(); } });
    });

    /* ---------- submit ---------- */
    function submitForm() {
        if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
    }
    form.addEventListener('submit', function (e) {
        normalizeRoot();
        var html = cleanSheet();
        if (!sheetText().trim()) {
            e.preventDefault();
            stateText('empty');
            sheet.focus();
            return;
        }
        var t = byId('title');
        if (t && !t.value.trim()) { e.preventDefault(); t.focus(); return; }
        bodyField.value = MARK + html;
        try { window.localStorage.removeItem(lsKey); } catch (err) { /* ignore */ }
        dirty = false;
    });

    /* ---------- classification picker (segmented buttons driving the hidden select) ---------- */
    var classPick = byId('sse-ed-classpick');
    function syncClassPick() {
        var c = byId('classification');
        if (!classPick || !c) { return; }
        each(classPick.querySelectorAll('[data-class-pick]'), function (b) {
            b.setAttribute('aria-pressed', b.getAttribute('data-class-pick') === c.value ? 'true' : 'false');
        });
    }
    if (classPick) {
        classPick.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('[data-class-pick]') : null;
            var c = byId('classification');
            if (!b || !c) { return; }
            c.value = b.getAttribute('data-class-pick');
            c.dispatchEvent(new Event('change', { bubbles: true }));
        });
        var classSel = byId('classification');
        if (classSel) { classSel.addEventListener('change', syncClassPick); }
        syncClassPick();
    }

    /* ---------- status chips (saved, draft, locked) ---------- */
    var statusSel = byId('status');
    function syncStatusChip() {
        var chip = byId('sse-ed-chip-status');
        if (chip && statusSel && statusSel.selectedIndex >= 0) { chip.textContent = statusSel.options[statusSel.selectedIndex].text; }
    }
    if (statusSel) { statusSel.addEventListener('change', syncStatusChip); syncStatusChip(); }

    /* ---------- template hints (grey guidance text, replaced as soon as the author writes over it) ---------- */
    function initHints() {
        each(sheet.querySelectorAll('.sse-hint'), function (h) {
            if (!h.hasAttribute('data-hint')) { h.setAttribute('data-hint', h.textContent); }
        });
    }
    function hintCount() { return sheet.querySelectorAll('.sse-hint').length; }
    function settleHints() {
        var o = null, any = false;
        each(sheet.querySelectorAll('.sse-hint'), function (h) {
            var orig = h.getAttribute('data-hint');
            if (orig === null) { h.setAttribute('data-hint', h.textContent); return; }
            if (h.textContent === orig) { return; }
            if (!any) { o = offsets(); any = true; }
            var parent = h.parentNode;
            if (h.textContent === '') {
                parent.removeChild(h);
                if (parent !== sheet && parent.childNodes.length === 0) { parent.appendChild(document.createElement('br')); }
                return;
            }
            while (h.firstChild) { parent.insertBefore(h.firstChild, h); }
            parent.removeChild(h);
        });
        if (any) { setOffsets(o); }
    }
    function selectHint(h) {
        var r = document.createRange();
        r.selectNodeContents(h);
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(r);
        rememberSelection();
    }
    function selectHintAtCaret() {
        var r = currentRange();
        if (!r || !r.collapsed) { return; }
        var h = closest(r.startContainer, '.sse-hint');
        if (h) { selectHint(h); }
    }
    function gotoNextHint() {
        var all = sheet.querySelectorAll('.sse-hint');
        if (!all.length) { return; }
        var r = currentRange() || savedRange;
        var target = all[0];
        if (r) {
            for (var i = 0; i < all.length; i++) {
                var after = document.createRange();
                after.selectNode(all[i]);
                if (r.compareBoundaryPoints(Range.START_TO_START, after) < 0) { target = all[i]; break; }
            }
        }
        sheet.focus();
        selectHint(target);
        if (target.scrollIntoView) { target.scrollIntoView({ block: 'center' }); }
    }
    function clearHints() {
        restoreSelection();
        each(sheet.querySelectorAll('.sse-hint'), function (h) {
            var parent = h.parentNode;
            parent.removeChild(h);
            if (parent !== sheet && parent.childNodes.length === 0) { parent.appendChild(document.createElement('br')); }
        });
        snapshot(false);
        changed(true);
    }
    sheet.addEventListener('click', selectHintAtCaret);
    sheet.addEventListener('keyup', function (e) { if (e.key === 'Tab') { selectHintAtCaret(); } });
    sheet.addEventListener('input', settleHints);

    /* ---------- side panel: outline, fields, models ---------- */
    var side = byId('sse-ed-side');
    var outlineEl = byId('sse-ed-outline');
    var outlineTimer = null;
    function refreshOutline() {
        if (!outlineEl) { return; }
        outlineEl.innerHTML = '';
        var heads = sheet.querySelectorAll('h2,h3,h4');
        each(heads, function (hd, i) {
            var li = document.createElement('li');
            li.className = 'sse-ed__ol sse-ed__ol--' + hd.nodeName.toLowerCase();
            var b = document.createElement('button');
            b.type = 'button';
            b.setAttribute('data-outline', String(i));
            b.textContent = (hd.textContent || '').replace(/\s+/g, ' ').trim() || '…';
            li.appendChild(b);
            outlineEl.appendChild(li);
        });
        var empty = byId('sse-ed-outline-empty');
        if (empty) { empty.hidden = heads.length > 0; }
    }
    function scheduleOutline() {
        window.clearTimeout(outlineTimer);
        outlineTimer = window.setTimeout(refreshOutline, 300);
    }
    function refreshFieldList() {
        each(root.querySelectorAll('[data-field-value]'), function (el) {
            el.textContent = fieldValue(el.getAttribute('data-field-value')) || '—';
        });
    }
    function setSideTab(name) {
        if (!side) { return; }
        each(side.querySelectorAll('[data-side-tab]'), function (t) {
            var on = t.getAttribute('data-side-tab') === name;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.setAttribute('tabindex', on ? '0' : '-1');
        });
        each(side.querySelectorAll('[data-side-panel]'), function (p) { p.hidden = p.getAttribute('data-side-panel') !== name; });
        try { window.localStorage.setItem('sse-ed-side', name); } catch (e) { /* ignore */ }
    }
    var modelIndex = {};
    each(cfg.models || [], function (m) { modelIndex[m.code] = m; });
    function setModelMsg(name) {
        var el = byId('sse-ed-model-msg');
        if (el) { el.textContent = name ? msg(name) : ''; }
    }
    function applyModel(code, mode) {
        var m = modelIndex[code];
        if (!m) { return; }
        if (mode === 'insert') {
            insertHtml(cleanHtml(m.body));
            lockSnapshot();
            initHints();
            scheduleOutline();
            return;
        }
        var cur = sheetText().trim();
        if (cur && !window.confirm(msg('confirm-replace'))) { return; }
        sheet.innerHTML = cleanHtml(m.body) || '<p><br></p>';
        initHints();
        var t = byId('title');
        if (t && !t.value.trim() && m.label) { t.value = m.label; }
        if (m.document_type) { var ty = byId('document_type'); if (ty) { ty.value = m.document_type; } }
        if (m.classification) {
            var cl = byId('classification');
            if (cl) { cl.value = m.classification; cl.dispatchEvent(new Event('change', { bubbles: true })); }
        }
        refreshFields();
        lockSnapshot();
        var mc = byId('sse-model-code');
        if (mc) { mc.value = /^perso-/.test(code) ? '' : code; }
        if (m.theme) { setTheme(m.theme); }
        snapshot(false);
        changed(true);
        sheet.focus();
        gotoNextHint();
    }
    function saveAsModel() {
        var name = byId('sse-ed-model-name'), descr = byId('sse-ed-model-descr'), shared = byId('sse-ed-model-shared');
        if (!name || !name.value.trim()) { setModelMsg('model-need-name'); if (name) { name.focus(); } return; }
        if (!sheetText().trim()) { setModelMsg('empty'); return; }
        var data = new FormData();
        data.append('_csrf_token', (form.querySelector('input[name="_csrf_token"]') || {}).value || '');
        data.append('title', name.value.trim());
        data.append('description', descr ? descr.value.trim() : '');
        data.append('body', MARK + cleanSheet());
        data.append('document_type', (byId('document_type') || {}).value || 'note_analyse');
        data.append('classification', (byId('classification') || {}).value || 'confidentiel');
        data.append('shared', shared && shared.checked ? '1' : '0');
        setModelMsg('model-saving');
        fetch(cfg.templateUrl, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j || !j.ok) { setModelMsg('model-error'); return; }
                var list = byId('sse-ed-models');
                var code = 'perso-' + j.id;
                modelIndex[code] = { code: code, label: name.value.trim(), body: cleanSheet(), document_type: (byId('document_type') || {}).value, classification: (byId('classification') || {}).value };
                if (list) {
                    var li = document.createElement('li');
                    li.className = 'sse-ed__model';
                    li.setAttribute('data-model', code);
                    var s = document.createElement('strong'); s.textContent = name.value.trim();
                    var p = document.createElement('span'); p.textContent = descr ? descr.value.trim() : '';
                    var acts = document.createElement('span'); acts.className = 'sse-ed__model-actions';
                    [['replace', 'btn btn--sm'], ['insert', 'btn btn--ghost btn--sm']].forEach(function (d) {
                        var bt = document.createElement('button');
                        bt.type = 'button'; bt.className = d[1];
                        bt.setAttribute('data-model-use', code); bt.setAttribute('data-mode', d[0]);
                        bt.textContent = d[0] === 'replace' ? msg('btn-use') : msg('btn-insert');
                        acts.appendChild(bt);
                    });
                    li.appendChild(s); li.appendChild(p); li.appendChild(acts);
                    list.insertBefore(li, list.firstChild);
                }
                setModelMsg('model-saved');
                name.value = ''; if (descr) { descr.value = ''; }
            })
            .catch(function () { setModelMsg('model-error'); });
    }
    if (side) {
        side.addEventListener('click', function (e) {
            var t = e.target.closest ? e.target.closest('button') : null;
            if (!t) { return; }
            if (t.hasAttribute('data-side-tab')) { setSideTab(t.getAttribute('data-side-tab')); return; }
            if (t.hasAttribute('data-outline')) {
                var heads = sheet.querySelectorAll('h2,h3,h4');
                var h = heads[parseInt(t.getAttribute('data-outline'), 10)];
                if (h) { h.scrollIntoView({ block: 'start' }); placeCaret(h, true); sheet.focus(); }
                return;
            }
            if (t.hasAttribute('data-insert-field')) { insertField(t.getAttribute('data-insert-field')); return; }
            if (t.hasAttribute('data-model-use')) { applyModel(t.getAttribute('data-model-use'), t.getAttribute('data-mode') || 'replace'); return; }
            var act = t.getAttribute('data-act');
            if (act === 'next-hint') { gotoNextHint(); }
            else if (act === 'clear-hints') { clearHints(); }
            else if (act === 'save-model') { saveAsModel(); }
        });
        side.addEventListener('mousedown', function (e) {
            // keeps the caret in the sheet when an insertion button is pressed
            if (e.target.closest && e.target.closest('button[data-insert-field],button[data-model-use],button[data-act]')) { rememberSelection(); }
        });
        var toggle = root.querySelector('[data-side-toggle]');
        if (toggle) {
            toggle.addEventListener('click', function () {
                var closed = root.classList.toggle('is-side-closed');
                toggle.setAttribute('aria-expanded', closed ? 'false' : 'true');
            });
        }
        var saved = 'outline';
        try { saved = window.localStorage.getItem('sse-ed-side') || saved; } catch (e) { /* ignore */ }
        if (!side.querySelector('[data-side-tab="' + saved + '"]')) { saved = 'outline'; }
        setSideTab(saved);
    }
    listeners.push(scheduleOutline);
    listeners.push(refreshFieldList);

    /* ---------- page markers (A4 boundaries) and classification bands ---------- */
    var pageEl = byId('sse-ed-page');
    function pageMarks() {
        if (!pageEl) { return; }
        each(pageEl.querySelectorAll('.sse-ed__pgmark'), function (m) { pageEl.removeChild(m); });
        var pages = Math.max(1, Math.ceil((sheet.scrollHeight - 2) / PAGE_PX));
        var foot = pageEl.querySelector('.sse-ed__pband--bottom');
        if (foot) { foot.setAttribute('data-foot', (cfg.refText ? cfg.refText + ' · ' : '') + msg('page-label') + ' ' + pages + ' ' + msg('page-of') + ' ' + pages); }
        for (var k = 1; k < pages; k++) {
            var m = document.createElement('div');
            m.className = 'sse-ed__pgmark';
            m.style.top = Math.round(k * PAGE_PX) + 'px';
            m.setAttribute('data-label', msg('page-label') + ' ' + (k + 1) + ' ' + msg('page-of') + ' ' + pages);
            m.setAttribute('aria-hidden', 'true');
            pageEl.appendChild(m);
        }
    }
    listeners.push(pageMarks);

    /* ---------- draft restore notice ---------- */
    function offerRestore() {
        var notice = byId('sse-ed-restore');
        if (!notice) { return; }
        var local = null, server = cfg.serverDraft || null;
        try { local = JSON.parse(window.localStorage.getItem(lsKey) || 'null'); } catch (e) { local = null; }
        var pick = null;
        if (local && local.html != null) { pick = { html: cleanHtml(local.html), title: local.title, from: 'local' }; }
        else if (server && server.body) { pick = { html: cleanHtml(server.body), title: server.title, from: 'server' }; }
        if (!pick || !pick.html || pick.html === cleanSheet()) { return; }
        notice.hidden = false;
        byId('sse-ed-restore-yes').onclick = function () {
            sheet.innerHTML = pick.html;
            initHints();
            if (pick.title && titleEl && !titleEl.value.trim()) { titleEl.value = pick.title; }
            notice.hidden = true;
            snapshot(false);
            changed(true);
            sheet.focus();
        };
        byId('sse-ed-restore-no').onclick = function () {
            notice.hidden = true;
            try { window.localStorage.removeItem(lsKey); } catch (e) { /* ignore */ }
            sheet.focus();
        };
    }

    window.addEventListener('beforeprint', function () { layoutBreaks(); });

    /* ---------- public API (used by the mention library script) ---------- */
    window.SseDocEditor = {
        text: sheetText,
        insertText: insertText,
        onChange: function (fn) { listeners.push(fn); }
    };

    /* ---------- init ---------- */
    document.execCommand && (function () { try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* ignore */ } })();
    each(sheet.querySelectorAll('.sse-field'), function (el) { el.setAttribute('contenteditable', 'false'); });
    showMedia();
    lockSnapshot();
    each(sheet.querySelectorAll('.sse-pagebreak'), function (el) { el.setAttribute('contenteditable', 'false'); });
    if (!sheet.firstChild) { sheet.innerHTML = '<p><br></p>'; }
    lastSnap = { html: sheet.innerHTML, sel: null };
    tbCollect();
    refreshUndoButtons();
    syncClassBanner();
    refreshFields();
    initHints();
    refreshOutline();
    refreshFieldList();
    layoutBreaks();
    counters();
    pageMarks();
    stateText('ready');
    offerRestore();
    root.setAttribute('data-ready', '1');
})();
