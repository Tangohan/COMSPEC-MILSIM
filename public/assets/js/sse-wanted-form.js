/*
 * SSE > Avis de recherche : formulaire « Placer l'avis ». Sélecteur de codes en tiroir (motifs et mesures), modèles d'avis,
 * texte composé et modifiable, derniers codes utilisés, onglets, signalement physique, pièces jointes, aperçu en direct.
 *
 * Aucun libellé dans ce fichier : les textes viennent du bloc caché #wc-labels et des données JSON #wc-data (rendus par la
 * vue, donc traduits avec la page). Les erreurs passent par window.SseNotify (composant des messages du portail).
 * Sans JavaScript, le formulaire reste complet : les sections s'empilent et le texte se saisit à la main.
 */
(function () {
    'use strict';
    var form = document.querySelector('[data-wc-form]');
    var dataEl = document.getElementById('wc-data');
    if (!form || !dataEl) { return; }
    var D = {};
    try { D = JSON.parse(dataEl.textContent || '{}'); } catch (e) { D = {}; }
    var S = D.state || {};
    var lang = S.lang === 'en' ? 'en' : 'fr';
    var MAX_SEL = 12;
    var RANK = { low: 0, medium: 1, high: 2, critical: 3 };

    function $(sel, root) { return (root || form).querySelector(sel); }
    function $$(sel, root) { return Array.prototype.slice.call((root || form).querySelectorAll(sel)); }
    function label(key, v1, v2) {
        var el = document.querySelector('#wc-labels [data-k="' + key + '"]');
        var t = el ? (el.textContent || '').trim() : '';
        return t.replace('%1', v1 === undefined ? '' : String(v1)).replace('%2', v2 === undefined ? '' : String(v2));
    }
    function notify(kind, text) {
        if (window.SseNotify && typeof window.SseNotify[kind] === 'function') { window.SseNotify[kind](text); }
    }
    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text !== undefined && text !== null) { n.textContent = text; }
        return n;
    }
    function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }

    /* ------------------------------------------------------------------ Données */
    var items = { r: {}, m: {} };
    var order = { r: [], m: [] };
    (D.items || []).forEach(function (i) { items[i.k][i.c] = i; order[i.k].push(i.c); });
    var sel = { r: [], m: [] };
    ['r', 'm'].forEach(function (k) { (S.selected && S.selected[k] || []).forEach(function (c) { if (items[k][c] && sel[k].indexOf(c) < 0) { sel[k].push(c); } }); });
    var ta = { r: $('[data-wc-text="r"]'), m: $('[data-wc-text="m"]') };
    var manual = !!S.hasOld;
    var autoCodes = { r: [], m: [] };
    function itemLabel(i) { return lang === 'en' ? i.en : i.fr; }
    function itemText(i) { return String(lang === 'en' ? i.te : i.tf || '').trim(); }

    /* ------------------------------------------------------------------ Texte composé */
    function addText(kind, code) {
        var s = itemText(items[kind][code]);
        var area = ta[kind];
        if (!s || !area || area.value.indexOf(s) !== -1) { return true; }
        var next = area.value.trim();
        next = next ? next + ' ' + s : s;
        if (area.maxLength > 0 && next.length > area.maxLength) { notify('warning', label('toolong')); return false; }
        area.value = next; grow(area);
        return true;
    }
    function removeText(kind, code) {
        var s = itemText(items[kind][code]);
        var area = ta[kind];
        if (!s || !area) { return; }
        var i = area.value.indexOf(s);
        if (i < 0) { return; }
        area.value = (area.value.slice(0, i) + area.value.slice(i + s.length)).replace(/ {2,}/g, ' ').replace(/^\s+|\s+$/g, ''); grow(area);
    }

    /* ------------------------------------------------------------------ Derniers codes utilisés */
    var recentKey = S.storageKey || 'wc-recent';
    function recentRead() {
        try { var v = JSON.parse(window.localStorage.getItem(recentKey) || '{}'); return { r: v.r || [], m: v.m || [] }; } catch (e) { return { r: [], m: [] }; }
    }
    function recentWrite(kind, code) {
        try {
            var v = recentRead();
            v[kind] = [code].concat(v[kind].filter(function (c) { return c !== code; })).slice(0, 8);
            window.localStorage.setItem(recentKey, JSON.stringify(v));
        } catch (e) { /* stockage indisponible : sans effet */ }
    }

    /* ------------------------------------------------------------------ Sélection */
    function toggle(kind, code, on, byUser) {
        var i = sel[kind].indexOf(code);
        if (on && i < 0) {
            if (sel[kind].length >= MAX_SEL || !items[kind][code]) { return false; }
            if (!addText(kind, code)) { return false; }
            sel[kind].push(code);
            if (byUser) { recentWrite(kind, code); }
        } else if (!on && i >= 0) {
            sel[kind].splice(i, 1);
            removeText(kind, code);
        }
        if (byUser) { manual = true; }
        sync();
        return true;
    }
    function clearAll() {
        ['r', 'm'].forEach(function (k) { sel[k].slice().forEach(function (c) { toggle(k, c, false, false); }); });
    }

    var typeSel = $('[data-wc-type]');
    var threatSel = $('[data-wc-threat]');
    var armedSel = $('[data-wc-armed]');
    function applyDefaults() {
        var d = (D.defaults || {})[typeSel ? typeSel.value : ''] || { reasons: [], measures: [] };
        autoCodes.r.concat([]).forEach(function (c) { toggle('r', c, false, false); });
        autoCodes.m.concat([]).forEach(function (c) { toggle('m', c, false, false); });
        autoCodes = { r: [], m: [] };
        (d.reasons || []).forEach(function (c) { if (sel.r.indexOf(c) < 0 && toggle('r', c, true, false)) { autoCodes.r.push(c); } });
        (d.measures || []).forEach(function (c) { if (sel.m.indexOf(c) < 0 && toggle('m', c, true, false)) { autoCodes.m.push(c); } });
    }
    if (typeSel) {
        typeSel.addEventListener('change', function () { if (!manual) { applyDefaults(); } });
    }

    /* ------------------------------------------------------------------ Rendu : pastilles, champs cachés, suggestion */
    function threatOf(code, kind) { return RANK[(items[kind][code] || {}).t] || 0; }
    function sync() {
        ['r', 'm'].forEach(function (k) {
            var box = $('[data-wc-chips="' + k + '"]');
            if (box) {
                box.textContent = '';
                sel[k].forEach(function (c) {
                    var chip = el('span', 'wc-chip');
                    chip.appendChild(el('b', null, c));
                    chip.appendChild(el('span', null, itemLabel(items[k][c])));
                    var x = el('button', 'wc-chip__x', '×');
                    x.type = 'button';
                    x.setAttribute('aria-label', label('remove') + ' ' + c);
                    x.addEventListener('click', function () { toggle(k, c, false, true); });
                    chip.appendChild(x);
                    box.appendChild(chip);
                });
            }
            counter(k);
        });
        var hid = $('[data-wc-hidden-inputs]');
        if (hid) {
            hid.textContent = '';
            [['r', 'codes_reasons[]'], ['m', 'codes_measures[]']].forEach(function (p) {
                sel[p[0]].forEach(function (c) {
                    var i = document.createElement('input');
                    i.type = 'hidden'; i.name = p[1]; i.value = c;
                    hid.appendChild(i);
                });
            });
        }
        suggest();
        recents();
        drawerState();
        preview();
        status();
    }
    function counter(k) {
        var n = $('[data-wc-counter="' + k + '"]');
        if (n && ta[k]) { n.textContent = label('chars', ta[k].value.length, ta[k].maxLength > 0 ? ta[k].maxLength : ''); }
    }
    function suggest() {
        var box = $('[data-wc-suggest]');
        if (!box || !threatSel) { return; }
        var best = -1, bestKey = '';
        ['r', 'm'].forEach(function (k) { sel[k].forEach(function (c) { var t = (items[k][c] || {}).t; if ((RANK[t] || 0) > best) { best = RANK[t] || 0; bestKey = t; } }); });
        box.textContent = '';
        var cur = RANK[threatSel.value] || 0;
        if (best > cur && bestKey) {
            var opt = $$('option', threatSel).filter(function (o) { return o.value === bestKey; })[0];
            box.appendChild(el('span', null, label('threat', opt ? opt.textContent : bestKey)));
            var b = el('button', 'btn btn--ghost btn--sm wc-btn', label('apply'));
            b.type = 'button';
            b.addEventListener('click', function () { threatSel.value = bestKey; sync(); });
            box.appendChild(b);
            box.hidden = false;
        } else { box.hidden = true; }
    }
    function recents() {
        var r = recentRead();
        ['r', 'm'].forEach(function (k) {
            var box = $('[data-wc-recent="' + k + '"]');
            if (!box) { return; }
            var list = r[k].filter(function (c) { return items[k][c] && sel[k].indexOf(c) < 0; }).slice(0, 5);
            box.textContent = '';
            if (!list.length) { box.hidden = true; return; }
            box.appendChild(el('span', 'wc-recent__t', label('recent')));
            list.forEach(function (c) {
                var b = el('button', 'wc-pillbtn', c);
                b.type = 'button';
                b.title = itemLabel(items[k][c]);
                b.addEventListener('click', function () { toggle(k, c, true, true); });
                box.appendChild(b);
            });
            box.hidden = false;
        });
    }

    /* ------------------------------------------------------------------ Tiroir des codes */
    var drawer = $('[data-wc-drawer]', document);
    var drawerKind = 'r';
    var lastFocus = null;
    function openDrawer(kind) {
        if (!drawer) { return; }
        drawerKind = kind;
        lastFocus = document.activeElement;
        $('[data-wc-drawer-title]', drawer).textContent = label(kind === 'r' ? 'reasons' : 'measures');
        var fam = $('[data-wc-family]', drawer);
        fam.textContent = '';
        var seen = {};
        fam.appendChild(new Option('—', ''));
        order[kind].forEach(function (c) { var f = items[kind][c].f; if (!seen[f]) { seen[f] = 1; fam.appendChild(new Option(f, f)); } });
        $('[data-wc-search]', drawer).value = '';
        renderList();
        drawer.hidden = false;
        document.body.classList.add('wc-lock');
        window.setTimeout(function () { $('[data-wc-search]', drawer).focus(); }, 30);
    }
    function closeModals() {
        $$('.wc-drawer', document).forEach(function (d) { d.hidden = true; });
        document.body.classList.remove('wc-lock');
        if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
    }
    function renderList() {
        var list = $('[data-wc-list]', drawer);
        var q = norm($('[data-wc-search]', drawer).value);
        var fam = $('[data-wc-family]', drawer).value;
        list.textContent = '';
        var shown = 0, group = null;
        order[drawerKind].forEach(function (c) {
            var i = items[drawerKind][c];
            if (fam && i.f !== fam) { return; }
            if (q && norm(c + ' ' + i.fr + ' ' + i.en + ' ' + i.tf + ' ' + i.te).indexOf(q) < 0) { return; }
            if (group !== i.f) { group = i.f; list.appendChild(el('h3', 'wc-group', i.f)); }
            var row = el('label', 'wc-code');
            var cb = document.createElement('input');
            cb.type = 'checkbox'; cb.value = c; cb.checked = sel[drawerKind].indexOf(c) >= 0;
            cb.addEventListener('change', function () { if (!toggle(drawerKind, c, cb.checked, true)) { cb.checked = !cb.checked; } });
            row.appendChild(cb);
            var body = el('span', 'wc-code__body');
            var head = el('span', 'wc-code__head');
            head.appendChild(el('b', 'wc-code__id', c));
            head.appendChild(el('span', 'wc-code__label', itemLabel(i)));
            head.appendChild(el('span', 'wc-dot wc-dot--' + i.t));
            body.appendChild(head);
            body.appendChild(el('span', 'wc-code__text', itemText(i)));
            row.appendChild(body);
            list.appendChild(row);
            shown++;
        });
        if (!shown) { list.appendChild(el('p', 'wc-empty', label('noresult'))); }
        drawerState();
    }
    function drawerState() {
        if (!drawer || drawer.hidden) { return; }
        $$('[data-wc-list] input', drawer).forEach(function (cb) { cb.checked = sel[drawerKind].indexOf(cb.value) >= 0; });
        $('[data-wc-drawer-count]', drawer).textContent = label('selected', sel[drawerKind].length);
    }
    if (drawer) {
        $('[data-wc-search]', drawer).addEventListener('input', renderList);
        $('[data-wc-family]', drawer).addEventListener('change', renderList);
    }
    $$('[data-wc-open]').forEach(function (b) { b.addEventListener('click', function () { openDrawer(b.getAttribute('data-wc-open')); }); });
    document.addEventListener('click', function (e) {
        if (e.target && e.target.closest && e.target.closest('[data-wc-close]')) { closeModals(); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.querySelector('.wc-drawer:not([hidden])')) { closeModals(); }
    });

    /* ------------------------------------------------------------------ Modèles d'avis */
    var tplModal = $('[data-wc-tpl-modal]', document);
    function openTemplates() {
        if (!tplModal) { return; }
        lastFocus = document.activeElement;
        var list = $('[data-wc-tpl-list]', tplModal);
        list.textContent = '';
        (D.templates || []).forEach(function (t) {
            var card = el('div', 'wc-tpl');
            var head = el('div', 'wc-tpl__head');
            head.appendChild(el('b', null, lang === 'en' ? t.en : t.fr));
            head.appendChild(el('span', 'wc-dot wc-dot--' + (t.threat || 'medium')));
            card.appendChild(head);
            var codes = el('div', 'wc-tpl__codes');
            (t.r || []).concat(t.m || []).forEach(function (c) { codes.appendChild(el('span', 'wc-mini', c)); });
            card.appendChild(codes);
            var b = el('button', 'btn btn--sm wc-btn', label('apply'));
            b.type = 'button';
            b.addEventListener('click', function () { applyTemplate(t); });
            card.appendChild(b);
            list.appendChild(card);
        });
        tplModal.hidden = false;
        document.body.classList.add('wc-lock');
    }
    function applyTemplate(t) {
        clearAll();
        autoCodes = { r: [], m: [] };
        (t.r || []).forEach(function (c) { toggle('r', c, true, true); });
        (t.m || []).forEach(function (c) { toggle('m', c, true, true); });
        if ($('[data-wc-tpl-apply-meta]', tplModal).checked) {
            if (typeSel && t.type && $$('option', typeSel).some(function (o) { return o.value === t.type; })) { typeSel.value = t.type; }
            if (threatSel && t.threat) { threatSel.value = t.threat; }
            if (armedSel && t.armed) { armedSel.value = t.armed; }
        }
        var input = $('[data-wc-template-input]');
        if (input) { input.value = t.key; }
        manual = true;
        closeModals();
        sync();
        notify('success', label('template_done', lang === 'en' ? t.en : t.fr));
    }
    var tplBtn = $('[data-wc-templates]');
    if (tplBtn) { tplBtn.addEventListener('click', openTemplates); }

    function grow(a) { a.style.height = 'auto'; a.style.height = Math.min(a.scrollHeight + 4, 416) + 'px'; }
    /* ------------------------------------------------------------------ Texte libre modifié à la main */
    ['r', 'm'].forEach(function (k) {
        if (ta[k]) { ta[k].addEventListener('input', function () { grow(ta[k]); counter(k); preview(); }); }
    });

    /* ------------------------------------------------------------------ Signalement physique */
    var reset = $('[data-wc-physical-reset]');
    if (reset) {
        reset.addEventListener('click', function () {
            var d = S.physicalDefaults || {};
            $$('[data-wc-phys]').forEach(function (f) { f.value = d[f.getAttribute('data-wc-phys')] === undefined ? '' : d[f.getAttribute('data-wc-phys')]; });
            $$('[data-wc-equipment]').forEach(function (c) { c.checked = (d.equipment || []).indexOf(c.value) >= 0; });
            preview();
        });
    }
    function physicalFilled() {
        var n = $$('[data-wc-phys]').filter(function (f) { return String(f.value).trim() !== ''; }).length;
        return n + $$('[data-wc-equipment]:checked').length;
    }

    /* ------------------------------------------------------------------ Pièces jointes */
    var rowsBox = $('[data-wc-rows]');
    var tpl = document.getElementById('wc-row-tpl');
    var addBtn = $('[data-wc-add-file]');
    function attRows() { return rowsBox ? $$('[data-wc-att]', rowsBox) : []; }
    function attCount() {
        return attRows().filter(function (r) { return $('[data-wc-file]', r).files.length > 0; }).length + $$('[data-wc-photo]:checked').length;
    }
    function reindex() {
        attRows().forEach(function (r, i) { $('[data-wc-main]', r).value = 'up:' + i; });
        var c = $('[data-wc-attach-count]');
        if (c) { c.textContent = label('images', attCount() + ' / ' + (S.max || 6)); }
    }
    function addRow() {
        if (!tpl || !rowsBox) { return null; }
        if (attRows().length + $$('[data-wc-photo]:checked').length >= (S.max || 6)) { notify('warning', label('toomany')); return null; }
        var row = tpl.content.firstElementChild.cloneNode(true);
        var file = $('[data-wc-file]', row);
        file.addEventListener('change', function () {
            var f = file.files[0];
            var thumb = $('[data-wc-thumb]', row);
            thumb.textContent = '';
            if (!f) { thumb.appendChild(el('span', null, 'JPEG, PNG, WebP')); reindex(); preview(); return; }
            if (['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) < 0) { notify('error', label('badtype')); file.value = ''; reindex(); return; }
            if (f.size > (S.maxBytes || 5242880)) { notify('error', label('toobig')); file.value = ''; reindex(); return; }
            var img = document.createElement('img');
            img.alt = f.name;
            img.src = URL.createObjectURL(f);
            thumb.appendChild(img);
            var main = $('[data-wc-main]', row);
            if (!$$('[data-wc-main]').some(function (m) { return m.checked; })) { main.checked = true; }
            reindex(); preview(); status();
        });
        $('[data-wc-remove-att]', row).addEventListener('click', function () { row.remove(); reindex(); preview(); status(); });
        rowsBox.appendChild(row);
        reindex();
        return row;
    }
    if (addBtn) { addBtn.addEventListener('click', function () { var r = addRow(); if (r) { $('[data-wc-file]', r).click(); } }); }
    $$('[data-wc-photo]').forEach(function (c) {
        c.addEventListener('change', function () {
            if (c.checked && attCount() > (S.max || 6)) { c.checked = false; notify('warning', label('toomany')); }
            reindex(); preview(); status();
        });
    });
    reindex();

    /* ------------------------------------------------------------------ Onglets */
    var tabs = $$('[data-wc-tab]');
    var secs = $$('[data-wc-sec]');
    function showTab(key, focus) {
        tabs.forEach(function (t) {
            var on = t.getAttribute('data-wc-tab') === key;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.tabIndex = on ? 0 : -1;
            if (on && focus) { t.focus(); }
            if (on && t.scrollIntoView) { try { t.scrollIntoView({ block: 'nearest', inline: 'nearest' }); } catch (e) { /* sans effet */ } }
        });
        secs.forEach(function (s) { s.hidden = s.getAttribute('data-wc-sec') !== key; });
        try { window.sessionStorage.setItem('wc-tab', key); } catch (e) { /* sans effet */ }
    }
    if (tabs.length) {
        form.classList.add('wc-js');
        tabs.forEach(function (t, i) {
            t.addEventListener('click', function () { showTab(t.getAttribute('data-wc-tab')); });
            t.addEventListener('keydown', function (e) {
                var n = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : -1;
                if (n >= 0) { e.preventDefault(); showTab(tabs[(n + tabs.length) % tabs.length].getAttribute('data-wc-tab'), true); }
            });
        });
        showTab('essential');
        // Un champ invalide dans une section masquée : on l'ouvre pour que le navigateur puisse l'indiquer.
        form.addEventListener('invalid', function (e) {
            var s = e.target.closest ? e.target.closest('[data-wc-sec]') : null;
            if (s && s.hidden) { showTab(s.getAttribute('data-wc-sec')); }
        }, true);
    }

    /* ------------------------------------------------------------------ Aperçu en direct et barre d'état */
    function optText(select) { return select && select.selectedOptions && select.selectedOptions[0] ? select.selectedOptions[0].textContent : ''; }
    function physLine() {
        var parts = [];
        ['sex', 'age', 'height_cm', 'weight_kg', 'build', 'hair', 'eyes'].forEach(function (k) {
            var f = $('[data-wc-phys="' + k + '"]');
            if (!f || !String(f.value).trim()) { return; }
            var v = f.tagName === 'SELECT' ? optText(f) : f.value + (k === 'height_cm' ? ' cm' : ' kg');
            parts.push(v);
        });
        return parts.join(' · ');
    }
    function preview() {
        var box = $('[data-wc-preview]');
        if (!box) { return; }
        box.textContent = '';
        var head = el('div', 'wc-pv__head');
        head.appendChild(el('b', null, optText(typeSel)));
        var th = threatSel ? threatSel.value : '';
        head.appendChild(el('span', 'wc-badge wc-badge--' + th, optText(threatSel)));
        if (armedSel && armedSel.value === 'yes') { head.appendChild(el('span', 'wc-badge wc-badge--critical', optText(armedSel))); }
        box.appendChild(head);
        function block(title, text, codes) {
            if (!text && !codes.length) { return; }
            var b = el('div', 'wc-pv__block');
            b.appendChild(el('h4', null, title));
            if (codes.length) {
                var c = el('div', 'wc-pv__codes');
                codes.forEach(function (x) { c.appendChild(el('span', 'wc-mini', x)); });
                b.appendChild(c);
            }
            if (text) { b.appendChild(el('p', null, text)); }
            box.appendChild(b);
        }
        block(label('lbl_reasons'), ta.r ? ta.r.value.trim() : '', sel.r);
        block(label('lbl_measures'), ta.m ? (ta.m.value.trim() || ta.m.placeholder) : '', sel.m);
        var place = $('[data-wc-place]');
        block(label('lbl_place'), place ? place.value.trim() : '', []);
        block(label('lbl_physical'), physLine(), []);
        var n = attCount();
        if (n) { box.appendChild(el('p', 'wc-pv__meta', label('images', n))); }
        if (!box.querySelector('.wc-pv__block')) { box.appendChild(el('p', 'wc-pv__meta', label('preview_empty'))); }
    }
    function status() {
        var s = $('[data-wc-status]');
        var n = sel.r.length + sel.m.length;
        if (s) { s.textContent = label('codes', n) + (attCount() ? ' · ' + label('images', attCount()) : ''); }
        var c = { essential: n || '', physical: physicalFilled() ? '•' : '', files: attCount() || '', service: '' };
        $$('[data-wc-count]').forEach(function (b) { var v = c[b.getAttribute('data-wc-count')]; b.textContent = v ? String(v) : ''; });
    }
    form.addEventListener('input', function () { preview(); status(); suggest(); });
    form.addEventListener('change', function () { preview(); status(); suggest(); });

    /* ------------------------------------------------------------------ Erreurs du serveur dans la zone des messages */
    var errs = $('[data-wc-errors]', document);
    if (errs && window.SseNotify && typeof window.SseNotify.error === 'function') {
        var items2 = $$('li', errs).map(function (li) { return li.textContent; });
        window.SseNotify.error(items2.join(' '));
        errs.hidden = true;
    }

    /* ------------------------------------------------------------------ Départ */
    if (!S.hasOld && typeSel && !ta.r.value.trim() && !ta.m.value.trim() && !sel.r.length && !sel.m.length) { applyDefaults(); }
    sync();
    ['r', 'm'].forEach(function (k) { if (ta[k]) { grow(ta[k]); } });
    if (window.matchMedia && window.matchMedia('(max-width: 1099px)').matches) { var pb = $('[data-wc-preview-box]'); if (pb) { pb.open = false; } }
    // Point d'accès pour le brouillon automatique (sse-wanted-services.js) : rétablit un code sans passer par le tiroir.
    window.SseWantedForm = { toggle: function (kind, code) { return toggle(kind, code, true, false); } };
})();
