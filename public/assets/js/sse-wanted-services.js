/*
 * SSE > Avis de recherche > onglet « Émission » : sélecteur de service demandeur (liste recherchable groupée par famille, avec
 * aperçu), préremplissage, contrôle de la classification, dates localisées avec raccourcis, récapitulatif avec contrôles de
 * cohérence, brouillon automatique du formulaire.
 *
 * Aucun libellé dans ce fichier : les textes viennent du bloc caché #ws-labels et des données JSON #ws-data (rendus par la vue,
 * donc traduits avec la page). Les contrôles reprennent ceux du serveur (SseWantedChecks), qui garde le dernier mot.
 * Sans JavaScript, la liste <select> du formulaire reste utilisable et les champs se saisissent à la main.
 */
(function () {
    'use strict';
    var form = document.querySelector('[data-wc-form]');
    var dataEl = document.getElementById('ws-data');
    var root = form ? form.querySelector('[data-ws-root]') : null;
    if (!form || !dataEl || !root) { return; }
    var D = {};
    try { D = JSON.parse(dataEl.textContent || '{}'); } catch (e) { D = {}; }
    var items = D.items || [];
    var byCode = {};
    items.forEach(function (i) { byCode[i.code] = i; });
    var classes = D.classes || {};

    function $(sel, r) { return (r || form).querySelector(sel); }
    function $$(sel, r) { return Array.prototype.slice.call((r || form).querySelectorAll(sel)); }
    function label(key, a) {
        var n = document.querySelector('#ws-labels [data-k="' + key + '"]');
        var t = n ? (n.textContent || '').trim() : '';
        return t.replace('%1', a === undefined ? '' : String(a));
    }
    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text !== undefined && text !== null) { n.textContent = text; }
        return n;
    }
    function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
    function store(op, key, value) {
        try {
            if (op === 'get') { return window.localStorage.getItem(key); }
            if (op === 'set') { window.localStorage.setItem(key, value); }
            if (op === 'del') { window.localStorage.removeItem(key); }
        } catch (e) { /* stockage indisponible : sans effet */ }
        return null;
    }

    var select = $('[data-ws-select]');
    var contact = $('[data-ws-contact]');
    var contactDetail = $('[data-ws-contact-detail]');
    var classSel = $('[data-ws-class]');
    var issued = $('[data-ws-issued]');
    var expires = $('[data-ws-expires]');
    var noExp = $('[data-ws-noexp-input]');
    var threatSel = $('[data-wc-threat]');
    var typeSel = $('[data-wc-type]');
    var armedSel = $('[data-wc-armed]');
    var card = $('[data-ws-card]');
    var go = $('[data-ws-go]');
    var touched = { threat: !!D.hasOld, expires: !!D.hasOld };
    var current = null;

    /* ------------------------------------------------------------------ Heure de la communauté */
    var t0 = Date.now();
    var base = Date.parse(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(D.nowLocal || '') ? D.nowLocal + ':00Z' : '');
    function nowLocal() { return new Date((isNaN(base) ? Date.now() : base) + (Date.now() - t0)); }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function fmt(d) { return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate()) + 'T' + pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()); }
    function parse(v) {
        if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(String(v || ''))) { return null; }
        var t = Date.parse(v + ':00Z');
        return isNaN(t) ? null : new Date(t);
    }
    function plusDays(from, n) { return new Date(from.getTime() + n * 86400000); }

    /* ------------------------------------------------------------------ Aperçu du service */
    function emblem(item) {
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('width', '28'); svg.setAttribute('height', '28'); svg.setAttribute('aria-hidden', 'true');
        var paths = {
            hex: 'M12 2l8.5 5v10L12 22l-8.5-5V7z', circle: 'M12 3a9 9 0 100 18 9 9 0 000-18z', diamond: 'M12 2l9 10-9 10-9-10z',
            bars: 'M4 5h16v3H4zM4 10.5h16v3H4zM4 16h16v3H4z', chevron: 'M3 6l9 6 9-6v5l-9 6-9-6z', cross: 'M9 3h6v6h6v6h-6v6H9v-6H3V9h6z',
            ring: 'M12 3a9 9 0 100 18 9 9 0 000-18zm0 5a4 4 0 110 8 4 4 0 010-8z'
        };
        var p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        p.setAttribute('d', paths[item.emblem] || paths.hex); p.setAttribute('fill', item.color); p.setAttribute('fill-rule', 'evenodd');
        svg.appendChild(p);
        return svg;
    }
    function renderCard(item) {
        if (!card) { return; }
        card.textContent = '';
        if (!item) { card.hidden = true; return; }
        card.hidden = false;
        card.style.setProperty('--ws-color', item.color);
        var head = el('div', 'ws-card__head');
        var mark = el('span', 'ws-card__mark'); mark.appendChild(emblem(item)); head.appendChild(mark);
        var titles = el('div', 'ws-card__titles');
        titles.appendChild(el('strong', 'ws-card__name', item.name));
        titles.appendChild(el('span', 'ws-card__code', (item.ac ? item.ac + ' · ' : '') + item.code));
        head.appendChild(titles);
        card.appendChild(head);
        var dl = el('dl', 'ws-card__list');
        function row(k, v) { if (!v) { return; } dl.appendChild(el('dt', null, label(k))); dl.appendChild(el('dd', null, v)); }
        row('role', (item.roles || []).join(' · '));
        row('contact', item.contact);
        row('max', (classes[item.max] || {}).label || '');
        card.appendChild(dl);
        card.appendChild(el('p', 'ws-card__tag', item.custom ? label('custom') : label('catalogue')));
    }

    /* ------------------------------------------------------------------ Liste déroulante recherchable */
    var combo = null;
    function buildCombo() {
        if (!select) { return; }
        var wrap = el('div', 'ws-combo');
        var btn = el('button', 'ws-combo__btn'); btn.type = 'button';
        btn.setAttribute('role', 'combobox'); btn.setAttribute('aria-haspopup', 'listbox'); btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-labelledby', 'ws-service-lbl');
        var lbl = form.querySelector('label[for="ws-service"]'); if (lbl) { lbl.id = 'ws-service-lbl'; }
        var btnText = el('span', 'ws-combo__text'); btn.appendChild(btnText);
        var chev = el('span', 'ws-combo__chev'); chev.setAttribute('aria-hidden', 'true'); btn.appendChild(chev);
        var panel = el('div', 'ws-combo__panel'); panel.hidden = true;
        var search = el('input', 'ws-combo__search'); search.type = 'search'; search.placeholder = label('search'); search.setAttribute('aria-label', label('search')); search.autocomplete = 'off';
        var list = el('div', 'ws-combo__list'); list.setAttribute('role', 'listbox'); list.id = 'ws-listbox';
        btn.setAttribute('aria-controls', 'ws-listbox');
        var empty = el('p', 'ws-combo__empty', label('none')); empty.hidden = true;
        panel.appendChild(search); panel.appendChild(list); panel.appendChild(empty);
        wrap.appendChild(btn); wrap.appendChild(panel);
        select.parentNode.insertBefore(wrap, select.nextSibling);
        select.classList.add('ws-native');
        var famNames = {};
        $$('optgroup', select).forEach(function (g) { famNames[g.label] = g.label; });
        var rows = [];
        var active = -1;

        function describe(item) { return item ? (item.name + (item.ac ? ' (' + item.ac + ')' : '')) : label('other'); }
        function paintBtn() { btnText.textContent = describe(byCode[select.value]); btnText.classList.toggle('is-other', !byCode[select.value]); }
        function build() {
            list.textContent = ''; rows = [];
            function addRow(item) {
                var r = el('div', 'ws-opt'); r.setAttribute('role', 'option'); r.id = 'ws-opt-' + rows.length; r.tabIndex = -1;
                r.dataset.code = item ? item.code : '';
                r.setAttribute('aria-selected', select.value === (item ? item.code : '') ? 'true' : 'false');
                var dot = el('span', 'ws-opt__dot'); dot.style.background = item ? item.color : 'transparent'; r.appendChild(dot);
                var body = el('span', 'ws-opt__body');
                body.appendChild(el('span', 'ws-opt__name', item ? item.name : label('other')));
                if (item) {
                    body.appendChild(el('span', 'ws-opt__meta', (item.ac ? item.ac + ' · ' : '') + (item.roles || []).join(', ') + ' · ' + item.maxLabel));
                }
                r.appendChild(body);
                if (item && item.code === D.last) { r.appendChild(el('span', 'ws-opt__tag', label('last'))); }
                r.addEventListener('mousedown', function (e) { e.preventDefault(); });
                r.addEventListener('click', function () { choose(item ? item.code : ''); });
                list.appendChild(r); rows.push(r);
            }
            addRow(null);
            var q = norm(search.value);
            var fams = D.families || {};
            var shown = 0;
            Object.keys(fams).forEach(function (fk) {
                var inFam = items.filter(function (i) {
                    if (i.family !== fk) { return false; }
                    return q === '' || norm(i.name + ' ' + i.ac + ' ' + i.code + ' ' + (i.roles || []).join(' ') + ' ' + i.contact).indexOf(q) >= 0;
                });
                if (!inFam.length) { return; }
                var g = el('div', 'ws-opt__group', fams[fk]); g.setAttribute('role', 'presentation'); list.appendChild(g);
                inFam.forEach(function (i) { addRow(i); shown++; });
            });
            empty.hidden = !(q !== '' && shown === 0);
            var cur = rows.map(function (r) { return r.dataset.code; }).indexOf(select.value);
            setActive(cur >= 0 ? cur : 0, false);
        }
        function setActive(i, scroll) {
            if (active >= 0 && rows[active]) { rows[active].classList.remove('is-active'); }
            active = Math.max(0, Math.min(rows.length - 1, i));
            if (rows[active]) {
                rows[active].classList.add('is-active'); btn.setAttribute('aria-activedescendant', rows[active].id); search.setAttribute('aria-activedescendant', rows[active].id);
                if (scroll !== false) { rows[active].scrollIntoView({ block: 'nearest' }); }
            }
        }
        function open() {
            panel.hidden = false; btn.setAttribute('aria-expanded', 'true'); search.value = ''; build(); search.focus();
        }
        function close(focusBtn) {
            if (panel.hidden) { return; }
            panel.hidden = true; btn.setAttribute('aria-expanded', 'false'); btn.removeAttribute('aria-activedescendant');
            if (focusBtn) { btn.focus(); }
        }
        function choose(code) {
            select.value = code; close(true); paintBtn(); onService(true);
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        btn.addEventListener('click', function () { if (panel.hidden) { open(); } else { close(true); } });
        btn.addEventListener('keydown', function (e) { if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
        search.addEventListener('input', build);
        search.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active - 1); }
            else if (e.key === 'Enter') { e.preventDefault(); if (rows[active]) { choose(rows[active].dataset.code); } }
            else if (e.key === 'Escape') { e.preventDefault(); close(true); }
            else if (e.key === 'Tab') { close(false); }
        });
        document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) { close(false); } });
        combo = { paint: paintBtn };
        paintBtn();
    }

    /* ------------------------------------------------------------------ Choix d'un service */
    function onService(byUser) {
        current = byCode[select ? select.value : ''] || null;
        renderCard(current);
        var auth = $('[data-ws-authority]');
        if (current && byUser) {
            var p = current.prefill || {};
            if (contact) { contact.value = p.contact_label || ''; }
            if (contactDetail) { contactDetail.value = p.contact_detail || ''; }
            if (classSel && p.classification) { classSel.value = p.classification; }
            if (threatSel && p.threat_level && !touched.threat) { threatSel.value = p.threat_level; }
            if (p.expiry_days > 0 && !touched.expires && noExp) { noExp.value = ''; setExpiry(p.expiry_days); }
            if (auth && p.authority) { auth.textContent = p.authority; }
            if (p.instructions) { var m = $('[data-wc-text="m"]'); if (m) { m.placeholder = p.instructions; } }
            store('set', D.recentKey || 'ws-service', current.code);
        }
        check();
        refresh();
    }
    function setExpiry(days) {
        if (!expires) { return; }
        var from = parse(issued && issued.value) || nowLocal();
        expires.value = fmt(plusDays(from, days));
        if (noExp) { noExp.value = ''; }
    }

    /* ------------------------------------------------------------------ Classification et service */
    function classRank(c) { return (classes[c] || {}).rank === undefined ? 1 : classes[c].rank; }
    function classProblem() {
        return !!(current && classSel && classRank(classSel.value) > classRank(current.max));
    }
    function check() {
        var msg = $('[data-ws-class-msg]');
        if (!msg || !classSel) { return; }
        var bad = classProblem();
        classSel.classList.toggle('ws-invalid', bad);
        classSel.setAttribute('aria-invalid', bad ? 'true' : 'false');
        msg.textContent = !current ? '' : (bad ? label('class_bad', current.maxLabel) : label('class_ok'));
        msg.classList.toggle('ws-bad', bad);
    }

    /* ------------------------------------------------------------------ Dates et raccourcis */
    var nowBtn = $('[data-ws-now]');
    if (nowBtn) { nowBtn.addEventListener('click', function () { if (issued) { issued.value = fmt(nowLocal()); issued.dispatchEvent(new Event('input', { bubbles: true })); } }); }
    $$('[data-ws-plus]').forEach(function (b) {
        b.addEventListener('click', function () { touched.expires = true; setExpiry(parseInt(b.getAttribute('data-ws-plus'), 10)); expires.dispatchEvent(new Event('input', { bubbles: true })); });
    });
    var noBtn = $('[data-ws-noexp]');
    if (noBtn) {
        noBtn.addEventListener('click', function () {
            touched.expires = true;
            if (expires) { expires.value = ''; }
            if (noExp) { noExp.value = '1'; }
            if (expires) { expires.dispatchEvent(new Event('input', { bubbles: true })); }
        });
    }
    if (expires) { expires.addEventListener('input', function () { touched.expires = true; if (noExp && expires.value) { noExp.value = ''; } }); }
    if (threatSel) { threatSel.addEventListener('change', function () { touched.threat = true; }); }

    /* ------------------------------------------------------------------ Récapitulatif et contrôles */
    var recap = $('[data-ws-recap]');
    var findingsBox = $('[data-ws-findings]');
    var recapList = $('[data-ws-recap-list]');
    var blocking = 0;
    function optText(sel) { var o = sel && sel.options[sel.selectedIndex]; return o ? o.textContent.trim() : ''; }
    function codesOf(name) { return $$('input[name="' + name + '"]').map(function (i) { return i.value; }); }
    function files() {
        return $$('[data-wc-file]').filter(function (f) { return f.files && f.files.length > 0; }).length + $$('[data-wc-photo]:checked').length;
    }
    function findings() {
        var out = [];
        function add(level, code, text) { out.push({ level: level, code: code, text: text }); }
        var reason = ($('[name="reason"]') || { value: '' }).value.trim();
        var iss = parse(issued && issued.value) || nowLocal();
        var exp = expires && expires.value ? parse(expires.value) : null;
        var now = nowLocal();
        if (D.isMember) { add('block', 'member', label('c_member')); }
        if (D.hasActive) { add('block', 'active', label('c_active')); }
        if (reason.length < 3) { add('block', 'reason', label('c_reason')); }
        if (exp && exp <= iss) { add('block', 'expiry_order', label('c_expiry_order')); }
        else if (exp && exp <= now) { add('block', 'expiry_past', label('c_expiry_past')); }
        if (classProblem()) { add('block', 'service_class', label('c_service_class', current.maxLabel)); }
        var zone = ($('[name="zone"]') || { value: '' }).value.trim();
        if (armedSel && armedSel.value === 'yes' && zone === '') { add('warn', 'armed_zone', label('c_armed_zone')); }
        if (current) {
            var p = current.prefill || {};
            if (threatSel && p.threats && p.threats.length && p.threats.indexOf(threatSel.value) < 0) { add('warn', 'service_threat', label('c_service_threat')); }
            if (typeSel && p.notice_types && p.notice_types.length && p.notice_types.indexOf(typeSel.value) < 0) { add('warn', 'service_type', label('c_service_type')); }
            if (!exp && noExp && noExp.value !== '1' && p.expiry_days > 0) { add('warn', 'no_expiry', label('c_no_expiry', p.expiry_days)); }
        }
        if (typeSel && (typeSel.value === 'capture_or_kill' || typeSel.value === 'hvt') && threatSel && ['low', 'medium'].indexOf(threatSel.value) >= 0) { add('warn', 'type_threat', label('c_type_threat')); }
        if (exp && exp.getTime() - iss.getTime() > 400 * 86400000) { add('warn', 'long_expiry', label('c_long_expiry')); }
        return out;
    }
    function refresh() {
        if (!recap) { return; }
        recap.hidden = false;
        var list = findings();
        blocking = list.filter(function (f) { return f.level === 'block'; }).length;
        findingsBox.textContent = '';
        if (!list.length) {
            var ok = el('li', 'ws-finding ws-finding--ok', label('ready')); findingsBox.appendChild(ok);
        }
        ['block', 'warn'].forEach(function (lv) {
            list.filter(function (f) { return f.level === lv; }).forEach(function (f) {
                var li = el('li', 'ws-finding ws-finding--' + lv);
                li.appendChild(el('strong', null, lv === 'block' ? label('blocking') : label('warning')));
                li.appendChild(el('span', null, f.text));
                findingsBox.appendChild(li);
            });
        });
        recapList.textContent = '';
        function row(k, v) { recapList.appendChild(el('dt', null, label(k))); recapList.appendChild(el('dd', null, v && String(v).trim() ? v : label('r_none'))); }
        row('r_subject', D.subject || '');
        row('r_type', optText(typeSel));
        row('r_threat', optText(threatSel) + (armedSel && armedSel.value === 'yes' ? ' · ' + optText(armedSel) : ''));
        var r = codesOf('codes_reasons[]'), m = codesOf('codes_measures[]');
        row('r_reasons', r.length ? r.join(', ') : '');
        row('r_measures', m.length ? m.join(', ') : '');
        row('r_zone', ($('[name="zone"]') || { value: '' }).value);
        row('r_service', current ? current.name + (current.ac ? ' (' + current.ac + ')' : '') : (contact && contact.value.trim() ? contact.value.trim() + ' — ' + label('r_free') : ''));
        row('r_class', classSel ? (classes[classSel.value] || {}).label : '');
        row('r_issued', issued && issued.value ? issued.value.replace('T', ' ') : label('r_now'));
        row('r_expires', expires && expires.value ? expires.value.replace('T', ' ') : label('r_noexp'));
        row('r_files', files() ? label('r_images', files()) : '');
        if (go) {
            go.setAttribute('aria-disabled', blocking ? 'true' : 'false');
            go.classList.toggle('ws-go--blocked', blocking > 0);
            go.title = blocking ? label('go_issues', blocking) : '';
        }
        var tab = $('[data-wc-count="service"]');
        if (tab) { tab.textContent = blocking ? String(blocking) : ''; }
    }
    form.addEventListener('submit', function (e) {
        if (blocking > 0) {
            e.preventDefault();
            var t = $('[data-wc-tab="service"]'); if (t) { t.click(); }
            if (window.SseNotify) { window.SseNotify.error(label('go_blocked')); }
            if (findingsBox) { findingsBox.scrollIntoView({ block: 'center' }); }
            return;
        }
        store('del', D.draftKey || 'ws-draft');
    });
    if (classSel) { classSel.addEventListener('change', function () { check(); refresh(); }); }

    /* ------------------------------------------------------------------ Brouillon automatique */
    var timer = null;
    function snapshot() {
        var out = {};
        $$('input, select, textarea').forEach(function (f) {
            if (!f.name || f.type === 'file' || f.name === '_csrf_token' || f.type === 'password') { return; }
            if (f.type === 'checkbox' || f.type === 'radio') {
                if (f.checked) { (out[f.name] = out[f.name] || []).push(f.value); }
                return;
            }
            if (f.name.slice(-2) === '[]') { (out[f.name] = out[f.name] || []).push(f.value); } else { out[f.name] = f.value; }
        });
        return out;
    }
    function saveDraft() {
        var snap = snapshot();
        if (!(snap.reason || snap.instructions || snap.zone || (snap['codes_reasons[]'] || []).length)) { return; }
        store('set', D.draftKey || 'ws-draft', JSON.stringify({ at: Date.now(), v: snap }));
    }
    function restoreDraft() {
        if (D.hasOld) { return; }
        var raw = store('get', D.draftKey || 'ws-draft');
        if (!raw) { return; }
        var d = null;
        try { d = JSON.parse(raw); } catch (e) { d = null; }
        if (!d || !d.v || Date.now() - (d.at || 0) > 7 * 86400000) { store('del', D.draftKey || 'ws-draft'); return; }
        $$('input, select, textarea').forEach(function (f) {
            if (!f.name || f.type === 'file' || f.name === '_csrf_token' || !(f.name in d.v)) { return; }
            var v = d.v[f.name];
            if (f.type === 'checkbox' || f.type === 'radio') { f.checked = Array.isArray(v) && v.indexOf(f.value) >= 0; return; }
            if (f.name.slice(-2) === '[]') { return; }
            f.value = v;
        });
        if (window.SseWantedForm) {
            (d.v['codes_reasons[]'] || []).forEach(function (c) { window.SseWantedForm.toggle('r', c); });
            (d.v['codes_measures[]'] || []).forEach(function (c) { window.SseWantedForm.toggle('m', c); });
        }
        touched.threat = true; touched.expires = !!(d.v.expires_at || d.v.no_expiry);
        var note = el('div', 'ws-draft');
        note.appendChild(el('span', null, label('draft_restored')));
        var clr = el('button', 'ws-chip', label('draft_clear')); clr.type = 'button';
        clr.addEventListener('click', function () { store('del', D.draftKey || 'ws-draft'); note.textContent = ''; note.appendChild(el('span', null, label('draft_cleared'))); });
        note.appendChild(clr);
        form.parentNode.insertBefore(note, form);
        if (select && d.v.service_code !== undefined) { select.value = d.v.service_code; if (select.value !== d.v.service_code) { select.value = ''; } }
    }
    function scheduleDraft() { window.clearTimeout(timer); timer = window.setTimeout(saveDraft, 500); }

    /* ------------------------------------------------------------------ Départ */
    restoreDraft();
    buildCombo();
    if (combo) { combo.paint(); }
    current = byCode[select ? select.value : ''] || null;
    renderCard(current);
    if (!D.hasOld && current && expires && !expires.value && noExp && noExp.value !== '1' && (current.prefill || {}).expiry_days > 0) { setExpiry(current.prefill.expiry_days); }
    if (!D.hasOld && !current && D.selected === '' && !(select && select.value)) { renderCard(null); }
    check();
    refresh();
    form.addEventListener('input', function () { refresh(); scheduleDraft(); });
    form.addEventListener('change', function () { refresh(); scheduleDraft(); });
    var showTabBtn = $('[data-wc-tab="service"]');
    if (showTabBtn) { showTabBtn.addEventListener('click', refresh); }
})();
