/*
 * SSE > Réglages de la notice > Services : liste (recherche, filtres, tri), fiche du service choisi, fenêtre d'édition à onglets
 * avec l'aperçu en direct de l'en-tête de document, téléversement de l'emblème. Aucun libellé dans ce fichier : les textes viennent
 * du bloc caché [data-svc-words] de la vue, donc traduits avec la page. Les messages s'affichent dans la page ou dans la fenêtre.
 */
(function () {
    'use strict';
    var root = document.querySelector('[data-svc-admin]');
    if (!root) { return; }
    function $(s, r) { return (r || document).querySelector(s); }
    function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
    function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
    function el(tag, cls, text) { var n = document.createElement(tag); if (cls) { n.className = cls; } if (text !== undefined && text !== null) { n.textContent = text; } return n; }

    var WORDS = {};
    $$('[data-svc-words] [data-k]', root).forEach(function (n) { WORDS[n.getAttribute('data-k')] = n.textContent; });
    function w(k) { return WORDS[k] || ''; }
    var SHAPES = {};
    try { SHAPES = JSON.parse(root.getAttribute('data-svc-shapes') || '{}'); } catch (e) { SHAPES = {}; }
    var pageLang = root.getAttribute('data-svc-lang') === 'en' ? 'en' : 'fr';
    var canAdmin = root.getAttribute('data-svc-can') === '1';
    var wide = window.matchMedia ? window.matchMedia('(min-width: 1280px)') : { matches: true };

    /* ------------------------------------------------------------------ Emblème générique et aperçu de document */
    var SVG = 'http://www.w3.org/2000/svg';
    function shapeSvg(shape, fill, textFill, label, size) {
        var svg = document.createElementNS(SVG, 'svg');
        svg.setAttribute('viewBox', '0 0 48 48'); svg.setAttribute('width', size); svg.setAttribute('height', size); svg.setAttribute('aria-hidden', 'true'); svg.setAttribute('focusable', 'false');
        var p = document.createElementNS(SVG, 'path');
        p.setAttribute('d', SHAPES[shape] || SHAPES.hex || ''); p.setAttribute('fill', fill); p.setAttribute('fill-rule', 'evenodd');
        p.setAttribute('stroke', 'rgba(255,255,255,0.42)'); p.setAttribute('stroke-width', '1.2');
        svg.appendChild(p);
        label = String(label || '').replace(/[^0-9A-Za-zÀ-ɏ]+/g, '').slice(0, 4).toUpperCase();
        if (label) {
            var t = document.createElementNS(SVG, 'text');
            t.setAttribute('x', '24'); t.setAttribute('y', '24'); t.setAttribute('text-anchor', 'middle'); t.setAttribute('dominant-baseline', 'central');
            t.setAttribute('font-size', [0, 17, 15, 12, 10][label.length] || 10); t.setAttribute('font-weight', '700'); t.setAttribute('fill', textFill);
            t.setAttribute('font-family', 'system-ui, sans-serif');
            t.textContent = label;
            svg.appendChild(t);
        }
        return svg;
    }
    /** Emblème d'un service : l'image téléversée, sinon la forme générique. onBand : sur le bandeau coloré du document. */
    function emblemNode(s, size, onBand, full) {
        var generic = function () { return onBand ? shapeSvg(s.emblem, '#ffffff', s.hex, s.ac || s.fr, size) : shapeSvg(s.emblem, s.hex, '#ffffff', s.ac || s.fr, size); };
        if (!s.image) { return generic(); }
        var img = document.createElement('img');
        img.alt = ''; img.width = size; img.height = size; img.decoding = 'async';
        img.addEventListener('error', function () { if (img.parentNode) { img.parentNode.replaceChild(generic(), img); } });
        img.src = full ? s.image.url : s.image.thumb;
        return img;
    }
    function printed(s, k) { return Array.isArray(s.print) ? s.print.indexOf(k) >= 0 : true; }
    function contactLine(s) {
        return ['radio', 'tel', 'mail'].filter(function (k) { return printed(s, k) && s[k]; }).map(function (k) { return s[k]; }).join(' · ');
    }
    function addressText(s) {
        var on = function (k) { return printed(s, k) ? String(s[k] || '').trim() : ''; };
        var place = [on('city'), on('country')].filter(Boolean).join(', ');
        var lines = [on('address').split(/\n+/).map(function (l) { return l.trim(); }).filter(Boolean).join('\n'), place, on('hours')].filter(Boolean);
        if (on('web')) { lines.push(on('web')); }
        return lines.join('\n');
    }
    function renderDoc(box, s, lang) {
        if (!box) { return; }
        var en = lang === 'en';
        var name = (en ? (s.en || s.fr) : s.fr) || w('name_default');
        var auth = (en ? (s.auth_en || s.auth_fr) : s.auth_fr) || '';
        var desc = (en ? (s.desc_en || s.desc_fr) : s.desc_fr) || '';
        box.textContent = '';
        box.style.setProperty('--svc-hex', s.hex || '#1F3A5F');
        var band = el('div', 'svc-doc__band');
        var mark = el('span', 'svc-doc__emblem'); mark.appendChild(emblemNode(s, 56, true, true)); band.appendChild(mark);
        var titles = el('div', 'svc-doc__titles');
        titles.appendChild(el('span', 'svc-doc__name', name));
        if (auth && norm(auth) !== norm(name)) { titles.appendChild(el('span', 'svc-doc__auth', auth)); }
        if (s.motto) { titles.appendChild(el('span', 'svc-doc__motto', s.motto)); }
        band.appendChild(titles);
        if (s.ac) { band.appendChild(el('span', 'svc-doc__ac', s.ac)); }
        box.appendChild(band);
        var body = el('div', 'svc-doc__body');
        if (desc) { body.appendChild(el('p', 'svc-doc__desc', desc)); }
        var inform = el('div', 'svc-doc__inform');
        inform.appendChild(el('strong', '', w('inform')));
        inform.appendChild(el('span', 'svc-doc__who', name + (s.ac ? ' (' + s.ac + ')' : '')));
        var channel = contactLine(s);
        inform.appendChild(el('span', channel ? '' : 'svc-doc__none', channel || w('no_channel')));
        var address = addressText(s);
        if (address) { inform.appendChild(el('span', '', address)); }
        body.appendChild(inform);
        box.appendChild(body);
    }

    /* ------------------------------------------------------------------ Liste : filtre, tri, compteur */
    var rows = $$('[data-svc-row]', root);
    rows.forEach(function (r) { try { r._svc = JSON.parse(r.getAttribute('data-svc-json') || 'null'); } catch (e) { r._svc = null; } });
    var tbody = rows.length ? rows[0].parentNode : null;
    var q = $('[data-svc-q]', root), fam = $('[data-svc-fam]', root), state = $('[data-svc-state]', root);
    var none = $('[data-svc-none]', root), count = $('[data-svc-count]', root);
    function filter() {
        var text = norm(q ? q.value : ''), f = fam ? fam.value : '', st = state ? state.value : '';
        var shown = 0;
        rows.forEach(function (r) {
            var ok = (!text || norm(r.getAttribute('data-search')).indexOf(text) >= 0) && (!f || r.getAttribute('data-family') === f);
            if (ok && st) {
                ok = st === 'on' ? r.getAttribute('data-enabled') === '1' : st === 'off' ? r.getAttribute('data-enabled') === '0'
                    : st === 'custom' ? r.getAttribute('data-source') === 'custom' : r.getAttribute('data-changed') === '1';
            }
            r.hidden = !ok;
            if (ok) { shown++; }
        });
        if (none) { none.hidden = shown !== 0; }
        if (count) { count.textContent = (text || f || st) ? shown + ' ' + w('shown') + ' ' + rows.length : w('count_all'); }
        if (picked && picked.hidden) { pickFirst(); }
    }
    [q, fam, state].forEach(function (n) { if (n) { n.addEventListener('input', filter); n.addEventListener('change', filter); } });

    var sortKey = '', sortDir = 1;
    function sortValue(r, key) {
        var s = r._svc || {};
        if (key === 'name') { return norm(pageLang === 'en' ? s.en : s.fr); }
        if (key === 'ac') { return norm(s.ac) || '￿'; }
        if (key === 'family') { return norm(s.family_label); }
        if (key === 'use') { return -(s.use || 0); }
        if (key === 'state') { return s.enabled ? 0 : 1; }
        return s.pos || 0;
    }
    function sortBy(key) {
        if (sortKey === key) { if (sortDir === 1) { sortDir = -1; } else { sortKey = ''; sortDir = 1; } } else { sortKey = key; sortDir = 1; }
        $$('[data-svc-sort]', root).forEach(function (b) {
            var th = b.closest('th');
            if (th) { th.setAttribute('aria-sort', b.getAttribute('data-svc-sort') === sortKey ? (sortDir === 1 ? 'ascending' : 'descending') : 'none'); }
        });
        var k = sortKey || 'pos';
        rows.slice().sort(function (a, b) {
            var x = sortValue(a, k), y = sortValue(b, k);
            var c = x < y ? -1 : x > y ? 1 : (a._svc.pos || 0) - (b._svc.pos || 0);
            return c * sortDir;
        }).forEach(function (r) { tbody.appendChild(r); });
    }
    $$('[data-svc-sort]', root).forEach(function (b) { b.addEventListener('click', function () { sortBy(b.getAttribute('data-svc-sort')); }); });

    /* ------------------------------------------------------------------ Fiche du service */
    var card = $('[data-svc-card]', root), picked = null;
    function bind(scope, s) {
        var values = {
            name: (pageLang === 'en' ? s.en : s.fr) + (s.ac ? ' (' + s.ac + ')' : ''),
            desc: (pageLang === 'en' ? (s.desc_en || s.desc_fr) : s.desc_fr) || w('no_desc'),
            family_label: s.family_label, roles_label: s.roles_label, max_label: s.max_label, use: s.use > 0 ? String(s.use) : w('none'),
            origin: s.source === 'custom' ? w('origin_custom') : s.overridden ? w('origin_changed') : w('origin_catalogue'),
            emblem_state: s.image ? w('emblem_image') : w('emblem_generic'),
            toggle_label: s.enabled ? w('hide') : w('show'), code: s.code, toggle_to: s.enabled ? '0' : '1'
        };
        $$('[data-b]', scope).forEach(function (n) { var k = n.getAttribute('data-b'); if (values[k] !== undefined) { n.textContent = values[k]; } });
        $$('[data-b-val]', scope).forEach(function (n) { var k = n.getAttribute('data-b-val'); if (values[k] !== undefined) { n.value = values[k]; } });
        $$('[data-b-show]', scope).forEach(function (n) { n.hidden = n.getAttribute('data-b-show') === 'can_default' ? !(s.enabled && !s.is_default) : false; });
    }
    function pick(row) {
        if (!row || !row._svc) { return; }
        if (picked) { picked.classList.remove('is-picked'); }
        picked = row;
        row.classList.add('is-picked');
        if (card) {
            card.hidden = false;
            bind(card, row._svc);
            renderDoc($('[data-svc-doc]', card), row._svc, pageLang);
        }
    }
    function pickFirst() {
        var first = rows.filter(function (r) { return !r.hidden; }).sort(function (a, b) { return a.compareDocumentPosition(b) & 2 ? 1 : -1; })[0];
        if (first) { pick(first); } else if (card) { card.hidden = true; if (picked) { picked.classList.remove('is-picked'); picked = null; } }
    }
    rows.forEach(function (r) {
        r.addEventListener('click', function () {
            pick(r);
            if (!wide.matches) { openService(r._svc, $('[data-svc-pick]', r)); }
        });
    });
    var editBtn = card ? $('[data-svc-edit]', card) : null;
    if (editBtn) { editBtn.addEventListener('click', function () { if (picked) { openService(picked._svc, editBtn); } }); }

    /* ------------------------------------------------------------------ Fenêtres */
    var openers = new WeakMap();
    function openDialog(d, opener) {
        if (!d || d.hasAttribute('open')) { return; }
        openers.set(d, opener || document.activeElement);
        if (typeof d.showModal === 'function') { d.showModal(); } else { d.setAttribute('open', ''); }
        document.documentElement.classList.add('svc-open');
    }
    function closeDialog(d) {
        if (!d || !d.hasAttribute('open')) { return; }
        if (typeof d.close === 'function') { d.close(); } else { d.removeAttribute('open'); }
    }
    $$('dialog.svc-modal, dialog#svc-import').forEach(function (d) {
        d.addEventListener('close', function () {
            document.documentElement.classList.remove('svc-open');
            var o = openers.get(d);
            if (o && o.focus) { o.focus({ preventScroll: true }); }
        });
        d.addEventListener('click', function (e) { if (e.target === d) { closeDialog(d); } });
        if (d.hasAttribute('data-svc-autoopen')) { openDialog(d, null); }
    });
    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target : null;
        if (!t) { return; }
        var op = t.closest('[data-svc-open]');
        if (op) { openDialog($(op.getAttribute('data-svc-open')), op); return; }
        var cl = t.closest('[data-svc-close]');
        if (cl) { closeDialog(cl.closest('dialog')); return; }
        // Confirmation en deux temps, dans la page (aucune fenêtre du navigateur).
        var ask = t.closest('[data-svc-ask-open]');
        if (ask) { var box = ask.closest('[data-svc-ask]'); ask.hidden = true; $('.svc-ask__confirm', box).hidden = false; return; }
        var cancel = t.closest('[data-svc-ask-cancel]');
        if (cancel) { resetAsk(cancel.closest('[data-svc-ask]')); }
    });
    function resetAsk(box) {
        if (!box) { return; }
        var o = $('[data-svc-ask-open]', box), c = $('.svc-ask__confirm', box);
        if (o) { o.hidden = false; }
        if (c) { c.hidden = true; }
    }

    /* ------------------------------------------------------------------ Fenêtre du service */
    var modal = $('[data-svc-modal]');
    if (!modal) { filter(); pickFirst(); return; }
    var form = $('[data-svc-form]', modal);
    var tabs = $$('[data-svc-tab]', modal), panes = $$('[data-svc-pane]', modal);
    var errors = $('[data-svc-errors]', modal), save = $('[data-svc-save]', modal), title = $('[data-svc-title]', modal);
    var live = $('[data-svc-live]', modal), codeInput = $('[data-svc-code]', modal), codeView = $('[data-svc-codeview]', modal);
    var resetBox = $('[data-svc-reset]', modal), deleteBox = $('[data-svc-delete]', modal);
    var current = null, previewLang = pageLang;

    function showTab(key) {
        tabs.forEach(function (t) { var on = t.getAttribute('data-svc-tab') === key; t.setAttribute('aria-selected', on ? 'true' : 'false'); t.tabIndex = on ? 0 : -1; });
        panes.forEach(function (p) { p.hidden = p.getAttribute('data-svc-pane') !== key; });
        var fields = $('[data-svc-fields]', modal);
        if (fields) { fields.scrollTop = 0; }
    }
    tabs.forEach(function (t, i) {
        t.addEventListener('click', function () { showTab(t.getAttribute('data-svc-tab')); });
        t.addEventListener('keydown', function (e) {
            var n = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : -1;
            if (n >= 0) { e.preventDefault(); var nt = tabs[(n + tabs.length) % tabs.length]; showTab(nt.getAttribute('data-svc-tab')); nt.focus(); }
        });
    });

    function fields(k) { return $$('[data-f="' + k + '"]', modal); }
    function fill(s) {
        $$('[data-f]', modal).forEach(function (f) {
            var k = f.getAttribute('data-f'), val = s[k];
            if (f.type === 'checkbox') { f.checked = Array.isArray(val) ? val.indexOf(f.value) >= 0 : !!val; }
            else if (f.type === 'radio') { f.checked = String(val) === f.value; }
            else { f.value = val === undefined || val === null ? '' : String(val); }
        });
        ['color', 'emblem'].forEach(function (k) {
            var list = fields(k);
            if (list.length && !list.some(function (r) { return r.checked; })) { list[0].checked = true; }
        });
    }
    /** Service tel que le formulaire le décrit en ce moment (pour l'aperçu en direct). */
    function draft() {
        var s = {};
        Object.keys(current || {}).forEach(function (k) { s[k] = current[k]; });
        $$('[data-f]', modal).forEach(function (f) {
            var k = f.getAttribute('data-f');
            if (f.type === 'checkbox') {
                if (f.name && f.name.slice(-2) === '[]') { if (!Array.isArray(s['_' + k])) { s['_' + k] = []; } if (f.checked) { s['_' + k].push(f.value); } }
                else { s[k] = f.checked; }
            } else if (f.type === 'radio') { if (f.checked) { s[k] = f.value; if (k === 'color') { s.hex = f.getAttribute('data-hex') || s.hex; } } }
            else { s[k] = k === 'ac' ? f.value.trim().toUpperCase() : f.value.trim(); }
        });
        ['print', 'roles', 'lang', 'threats', 'types'].forEach(function (k) { if (Array.isArray(s['_' + k])) { s[k] = s['_' + k]; delete s['_' + k]; } });
        return s;
    }
    function refresh() { renderDoc(live, draft(), previewLang); }
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    $$('[data-svc-plang]', modal).forEach(function (b) {
        b.addEventListener('click', function () {
            previewLang = b.getAttribute('data-svc-plang');
            $$('[data-svc-plang]', modal).forEach(function (o) { o.setAttribute('aria-pressed', o === b ? 'true' : 'false'); });
            refresh();
        });
    });

    var more = $('[data-svc-more]', modal), previewBox = $('.svc-modal__preview', modal);
    function setMore(open) {
        if (!more || !previewBox) { return; }
        previewBox.classList.toggle('is-open', open);
        more.setAttribute('aria-expanded', open ? 'true' : 'false');
        more.textContent = more.getAttribute(open ? 'data-label-close' : 'data-label-open') || '';
    }
    if (more) { more.addEventListener('click', function () { setMore(!previewBox.classList.contains('is-open')); }); }

    function showErrors(list) {
        if (!errors) { return; }
        errors.textContent = '';
        (list || []).forEach(function (m) { errors.appendChild(el('li', '', m)); });
        errors.hidden = !(list && list.length);
        if (!errors.hidden && errors.scrollIntoView) { errors.scrollIntoView({ block: 'nearest' }); }
    }

    /* Emblème téléversé */
    var up = $('[data-svc-upload]', modal);
    var upMark = up ? $('[data-svc-upload-mark]', up) : null, upState = up ? $('[data-svc-upload-state]', up) : null, upBy = up ? $('[data-svc-upload-by]', up) : null;
    var upForm = up ? $('[data-svc-upload-form]', up) : null, upNew = up ? $('[data-svc-upload-new]', up) : null, upMsg = up ? $('[data-svc-upload-msg]', up) : null;
    var upFile = up ? $('[data-svc-file]', up) : null, upConfirm = up ? $('[data-svc-confirm]', up) : null, upSend = up ? $('[data-svc-upload-send]', up) : null;
    var upRemove = up ? $('[data-svc-upload-remove]', up) : null;
    function uploadState() {
        if (!up || !current) { return; }
        var s = draft();
        upMark.textContent = '';
        upMark.appendChild(emblemNode(s, 56, false, true));
        upState.textContent = s.image ? w('emblem_image') : w('emblem_generic');
        upBy.textContent = s.image && s.image.by ? w('uploaded_by') + ' ' + s.image.by : '';
        var isNew = !current.code;
        if (upNew) { upNew.hidden = !isNew; }
        if (upForm) { upForm.hidden = isNew; }
        if (upRemove) { upRemove.hidden = !s.image; resetAsk(upRemove); }
    }
    function uploadMessage(text, bad) {
        if (!upMsg) { return; }
        upMsg.textContent = text || '';
        upMsg.hidden = !text;
        upMsg.classList.toggle('is-bad', !!bad);
    }
    function token() { var t = $('input[name="_csrf_token"]', form); return t ? t.value : ''; }
    function post(url, data) {
        return fetch(url, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().catch(function () { return { ok: false, errors: [w('net_error')] }; }); })
            .catch(function () { return { ok: false, errors: [w('net_error')] }; });
    }
    function syncRow() {
        // L'emblème a changé sans rechargement : la ligne de la liste et la fiche suivent.
        var row = rows.filter(function (r) { return r._svc && current && r._svc.code === current.code; })[0];
        if (!row) { return; }
        row._svc.image = current.image;
        var mark = $('[data-svc-mark]', row);
        if (mark) { mark.textContent = ''; mark.appendChild(emblemNode(row._svc, 40, false, false)); }
        if (picked === row) { pick(row); }
    }
    if (upSend) {
        upSend.addEventListener('click', function () {
            if (!current || !current.code) { return; }
            var file = upFile && upFile.files ? upFile.files[0] : null;
            if (!file) { uploadMessage(w('need_file'), true); return; }
            if (file.size > 1048576) { uploadMessage(w('too_big'), true); return; }
            if (!upConfirm || !upConfirm.checked) { uploadMessage(w('need_confirm'), true); return; }
            var data = new FormData();
            data.append('_csrf_token', token()); data.append('emblem_file', file); data.append('emblem_confirm', '1');
            upSend.disabled = true; uploadMessage(w('sending'), false);
            post(current.emblem_url, data).then(function (res) {
                upSend.disabled = false;
                if (!res.ok) { uploadMessage((res.errors || []).join(' ') || w('net_error'), true); return; }
                current.image = { url: res.url, thumb: res.thumb, by: '', at: '' };
                upFile.value = ''; upConfirm.checked = false;
                uploadMessage(res.message, false); uploadState(); refresh(); syncRow();
            });
        });
    }
    var removeGo = up ? $('[data-svc-emblem-remove]', up) : null;
    if (removeGo) {
        removeGo.addEventListener('click', function () {
            if (!current || !current.code) { return; }
            var data = new FormData();
            data.append('_csrf_token', token());
            removeGo.disabled = true;
            post(current.emblem_url + '/retirer', data).then(function (res) {
                removeGo.disabled = false;
                if (!res.ok) { uploadMessage((res.errors || []).join(' ') || w('net_error'), true); return; }
                current.image = null;
                uploadMessage(res.message, false); uploadState(); refresh(); syncRow();
            });
        });
    }

    function openService(s, opener) {
        var isNew = !s;
        current = s ? JSON.parse(JSON.stringify(s)) : {
            code: '', source: 'custom', enabled: true, is_default: false, fr: '', en: '', ac: '', family: '', roles: ['request'], lang: ['fr', 'en'],
            threats: ['low', 'medium', 'high', 'critical'], types: [], max: 'encadrement', class: 'encadrement', thr: 'medium', exp: 30, color: '', emblem: '',
            print: ['radio', 'tel', 'mail', 'address', 'city', 'country', 'hours', 'web'], pos: rows.length + 1, hex: '#1F3A5F', image: null
        };
        fill(current);
        if (isNew) {
            var fam0 = fields('family')[0]; if (fam0 && fam0.options.length) { fam0.selectedIndex = 0; }
            var c0 = fields('color')[0]; if (c0) { current.hex = c0.getAttribute('data-hex') || current.hex; }
        }
        form.action = isNew ? modal.getAttribute('data-create-url') : current.update_url;
        if (codeInput) { codeInput.value = current.code; }
        if (codeView) { codeView.value = current.code || '—'; }
        if (title) { title.textContent = !canAdmin ? w('title_read') : isNew ? w('title_create') : w('title_update'); }
        if (save) { save.textContent = isNew ? w('save_create') : w('save_update'); save.disabled = false; }
        if (resetBox) {
            resetBox.hidden = !(current.source === 'catalogue' && current.overridden);
            var go = $('[data-svc-reset-go]', resetBox); if (go) { go.setAttribute('formaction', current.reset_url || ''); }
            resetAsk($('[data-svc-ask]', resetBox));
        }
        if (deleteBox) {
            deleteBox.hidden = !(current.source === 'custom' && current.code);
            var del = $('[data-svc-delete-go]', deleteBox); if (del) { del.setAttribute('formaction', modal.getAttribute('data-list-url')); }
            resetAsk($('[data-svc-ask]', deleteBox));
        }
        showErrors([]); uploadMessage('', false);
        if (upFile) { upFile.value = ''; }
        if (upConfirm) { upConfirm.checked = false; }
        previewLang = pageLang;
        $$('[data-svc-plang]', modal).forEach(function (o) { o.setAttribute('aria-pressed', o.getAttribute('data-svc-plang') === previewLang ? 'true' : 'false'); });
        showTab('id'); uploadState(); refresh();
        setMore(false);
        openDialog(modal, opener);
        if (title && title.focus) { title.focus({ preventScroll: true }); }
    }
    $$('[data-svc-new]', root).forEach(function (b) { b.addEventListener('click', function () { openService(null, b); }); });
    form.addEventListener('change', function (e) { if (e.target && (e.target.getAttribute('data-f') === 'color' || e.target.getAttribute('data-f') === 'emblem')) { uploadState(); } });

    form.addEventListener('submit', function (e) {
        var by = e.submitter;
        if (by && by.hasAttribute('data-svc-plain')) { return; } // rétablir, supprimer : envoi simple, retour à la page
        e.preventDefault();
        if (!canAdmin || !save) { return; }
        var fr = fields('fr')[0];
        if (fr && fr.value.trim().length < 3) { showTab('id'); fr.focus(); if (fr.reportValidity) { fr.reportValidity(); } return; }
        save.disabled = true;
        var label = save.textContent;
        save.textContent = w('saving');
        post(form.action, new FormData(form)).then(function (res) {
            if (res.ok) {
                window.location.hash = '#services';
                window.location.reload();
                return;
            }
            save.disabled = false; save.textContent = label;
            showErrors(res.errors && res.errors.length ? res.errors : [w('net_error')]);
        });
    });

    filter();
    pickFirst();
})();
