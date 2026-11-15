/*
 * Bureau SSE — comportements du système de design (sse-ds.css). Aucune dépendance, aucun texte dans le script :
 * tous les libellés vivent dans le HTML de la page.
 *
 *  - Fenêtres <dialog> (.sse-modal, .sse-drawer) : ouverture par [data-sse-open="#id"], fermeture par [data-sse-close],
 *    focus piégé dans la fenêtre, retour du focus sur le bouton d'origine, Échap, clic sur le fond, défilement de la
 *    page bloqué. [data-sse-autoopen] ouvre la fenêtre au chargement ; data-sse-record / -field- / -text- la remplissent.
 *  - Tiroir d'une ligne : [data-sse-drawer="#tiroir"] sur le tableau ; chaque ligne [data-sse-item="clé"] ouvre le tiroir
 *    avec le contenu du <template data-for="clé"> rangé dans le tiroir.
 *  - Onglets : [data-sse-tabs] > [role="tab"], flèches / Début / Fin, [data-sse-hash] garde l'onglet dans l'URL (#valeur).
 *  - Liste filtrable : [data-sse-list] (onglets [data-sse-tab], recherche [data-sse-search], filtres select[data-sse-filter],
 *    tri th[data-sse-sort], « Afficher plus » [data-sse-more], compteur [data-sse-count], vide filtré [data-sse-empty-filtered]).
 *  - Sélection de lignes : [data-sse-select] avec case d'en-tête [data-sse-select-all] et barre [data-sse-bulk].
 * API : window.SseDs.open(dialogOuSélecteur, bouton), window.SseDs.close(dialog).
 */
(function () {
    'use strict';

    var doc = document;
    var html = doc.documentElement;
    var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])';

    function $(sel, root) { return (root || doc).querySelector(sel); }
    function $$(sel, root) { return Array.prototype.slice.call((root || doc).querySelectorAll(sel)); }
    function resolve(ref) { return typeof ref === 'string' ? $(ref) : ref; }
    function fire(el, name, detail) {
        var ev;
        try { ev = new CustomEvent(name, { bubbles: true, detail: detail || {} }); } catch (e) { ev = doc.createEvent('CustomEvent'); ev.initCustomEvent(name, true, false, detail || {}); }
        el.dispatchEvent(ev);
    }
    function visible(el) { return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length); }
    function focusables(root) { return $$(FOCUSABLE, root).filter(visible); }

    /* ------------------------------------------------------------------ Fenêtres */
    var openers = new WeakMap();

    function openDialog(ref, opener) {
        var d = resolve(ref);
        if (!d || d.hasAttribute('open')) { return; }
        openers.set(d, opener || doc.activeElement || null);
        if (typeof d.showModal === 'function') { d.showModal(); } else { d.setAttribute('open', ''); d.setAttribute('aria-modal', 'true'); }
        html.classList.add('sse-dialog-open');
        var target = $('[data-sse-focus]', d) || $('[autofocus]', d);
        // Un tiroir de consultation reçoit le focus lui-même (son titre est annoncé) ; une modale, son premier champ.
        if (!target && d.classList.contains('sse-drawer')) { target = d; }
        if (!target) {
            var body = $('.sse-dialog__body', d) || d;
            target = focusables(body)[0] || focusables(d)[0] || d;
        }
        if (target === d) { d.setAttribute('tabindex', '-1'); }
        target.focus({ preventScroll: true });
        fire(d, 'sse:dialog-open', { opener: opener || null });
    }

    function closeDialog(ref) {
        var d = resolve(ref);
        if (!d || !d.hasAttribute('open')) { return; }
        if (typeof d.close === 'function') { d.close(); } else { d.removeAttribute('open'); afterClose(d); }
    }

    function afterClose(d) {
        if (!$$('dialog[open].sse-modal, dialog[open].sse-drawer').length) { html.classList.remove('sse-dialog-open'); }
        var back = openers.get(d);
        openers.delete(d);
        if (back && doc.documentElement.contains(back) && typeof back.focus === 'function') { back.focus({ preventScroll: true }); }
        fire(d, 'sse:dialog-close', {});
    }

    /*
     * Remplissage à l'ouverture (tout vient d'attributs du bouton, jamais de texte dans le script) :
     *  - data-sse-field-<nom>="valeur" renseigne le champ [name="<nom>"] de la fenêtre ;
     *  - data-sse-text-<clé>="texte" remplace le contenu de [data-sse-bind="<clé>"] ;
     *  - data-sse-record="<clé>" copie dans les champs de la fenêtre l'enregistrement <clé> du bloc JSON désigné par
     *    data-sse-records="#id" sur la fenêtre (clé vide = nouvel élément) et pose data-sse-mode="edit|new".
     */
    function fillDialog(d, opener) {
        if (!d || !opener || !opener.attributes) { return; }
        var form = $('form', d);
        var recordsRef = d.getAttribute('data-sse-records');
        if (recordsRef && opener.hasAttribute('data-sse-record')) {
            var key = opener.getAttribute('data-sse-record');
            var holder = $(recordsRef);
            var all = {};
            try { all = JSON.parse(holder ? holder.textContent : '{}') || {}; } catch (e) { all = {}; }
            var rec = all[key] || all[''] || {};
            d.setAttribute('data-sse-mode', key ? 'edit' : 'new');
            $$('[name]', d).forEach(function (el) {
                if (!Object.prototype.hasOwnProperty.call(rec, el.name)) { return; }
                if (el.type === 'checkbox') { el.checked = String(rec[el.name]) === '1' || rec[el.name] === true; }
                else if (el.type !== 'file') { el.value = rec[el.name] == null ? '' : String(rec[el.name]); }
            });
            if (form) { form.setAttribute('data-sse-record', key); }
        }
        for (var i = 0; i < opener.attributes.length; i++) {
            var a = opener.attributes[i];
            if (a.name.indexOf('data-sse-field-') === 0) {
                var f = d.querySelector('[name="' + a.name.slice(15) + '"]');
                if (f) { f.value = a.value; }
            } else if (a.name.indexOf('data-sse-text-') === 0) {
                var k = a.name.slice(14);
                $$('[data-sse-bind="' + k + '"]', d).forEach(function (n) { n.textContent = a.value; });
            }
        }
    }

    var downOnBackdrop = null;
    doc.addEventListener('mousedown', function (e) {
        var t = e.target;
        downOnBackdrop = (t && t.tagName === 'DIALOG' && (t.classList.contains('sse-modal') || t.classList.contains('sse-drawer'))) ? t : null;
    });
    doc.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) { return; }
        // Clic sur le fond : la cible est le <dialog> lui-même (son contenu le recouvre entièrement).
        if (t.tagName === 'DIALOG' && downOnBackdrop === t && (t.classList.contains('sse-modal') || t.classList.contains('sse-drawer'))) {
            closeDialog(t);
            return;
        }
        var opener = t.closest('[data-sse-open]');
        if (opener) {
            e.preventDefault();
            var target = resolve(opener.getAttribute('data-sse-open'));
            fillDialog(target, opener);
            openDialog(target, opener);
            return;
        }
        var closer = t.closest('[data-sse-close]');
        if (closer) {
            var host = closer.closest('dialog');
            if (host) { e.preventDefault(); closeDialog(host); }
        }
    });
    doc.addEventListener('close', function (e) {
        var d = e.target;
        if (d && d.tagName === 'DIALOG' && (d.classList.contains('sse-modal') || d.classList.contains('sse-drawer'))) { afterClose(d); }
    }, true);
    // Focus piégé : Tab et Maj+Tab restent dans la fenêtre ouverte (le navigateur le fait déjà pour showModal, mais pas jusqu'à ses propres menus).
    doc.addEventListener('keydown', function (e) {
        var d = $('dialog[open].sse-modal, dialog[open].sse-drawer');
        if (!d) { return; }
        if (e.key === 'Escape' && typeof d.showModal !== 'function') { e.preventDefault(); closeDialog(d); return; }
        if (e.key !== 'Tab') { return; }
        var items = focusables(d);
        if (!items.length) { e.preventDefault(); return; }
        var first = items[0], last = items[items.length - 1], active = doc.activeElement;
        if (!d.contains(active)) { e.preventDefault(); first.focus(); }
        else if (e.shiftKey && active === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && active === last) { e.preventDefault(); first.focus(); }
    });

    /* ------------------------------------------------------------------ Tiroir d'une ligne */
    function field(rec, name) { var v = rec[name]; return v == null ? '' : String(v); }
    function truthy(rec, expr) {
        var eq = expr.indexOf('=');
        if (eq > 0) { return field(rec, expr.slice(0, eq)) === expr.slice(eq + 1); }
        var v = field(rec, expr);
        return v !== '' && v !== '0' && v !== 'false';
    }
    /*
     * Gabarit de tiroir rempli depuis un enregistrement JSON :
     *  data-sse-text="champ"                 texte de l'élément ;
     *  data-sse-if="champ" / "champ=valeur"  affiche l'élément seulement si le champ est renseigné / égal à la valeur ;
     *  data-sse-unless="…"                   l'inverse ;
     *  data-sse-attrs="attribut=champ,…"     attributs de l'élément (href, data-…) ;
     *  data-sse-class-from="champ" + data-sse-class-prefix="préfixe-"   ajoute la classe préfixe + valeur.
     */
    function applyRecord(root, rec) {
        $$('[data-sse-text]', root).forEach(function (n) { n.textContent = field(rec, n.getAttribute('data-sse-text')); });
        $$('[data-sse-if]', root).forEach(function (n) { n.hidden = !truthy(rec, n.getAttribute('data-sse-if')); });
        $$('[data-sse-unless]', root).forEach(function (n) { n.hidden = truthy(rec, n.getAttribute('data-sse-unless')); });
        $$('[data-sse-attrs]', root).forEach(function (n) {
            n.getAttribute('data-sse-attrs').split(',').forEach(function (pair) {
                var p = pair.split('=');
                if (p.length === 2) { n.setAttribute(p[0].trim(), field(rec, p[1].trim())); }
            });
        });
        $$('[data-sse-class-from]', root).forEach(function (n) {
            var v = field(rec, n.getAttribute('data-sse-class-from'));
            if (v) { n.classList.add((n.getAttribute('data-sse-class-prefix') || '') + v); }
        });
    }
    function openItem(table, row, opener) {
        var drawer = resolve(table.getAttribute('data-sse-drawer'));
        if (!drawer) { return; }
        var key = row.getAttribute('data-sse-item');
        var body = $('[data-sse-drawer-body]', drawer);
        var title = $('[data-sse-drawer-title]', drawer);
        var tpl = null, rec = null;
        var shared = $('template[data-sse-template]', drawer);
        if (shared) {
            var holder = resolve(drawer.getAttribute('data-sse-records'));
            try { rec = (JSON.parse(holder ? holder.textContent : '{}') || {})[key] || null; } catch (e) { rec = null; }
            tpl = rec ? shared : null;
        } else {
            $$('template[data-for]', drawer).forEach(function (t) { if (t.getAttribute('data-for') === key) { tpl = t; } });
        }
        if (!tpl || !body) { return; }
        body.textContent = '';
        var frag = tpl.content.cloneNode(true);
        if (rec) { applyRecord(frag, rec); }
        body.appendChild(frag);
        if (title) { title.textContent = rec ? field(rec, 'label') : (tpl.getAttribute('data-title') || ''); }
        $$('tr[aria-selected="true"]', table).forEach(function (r) { r.removeAttribute('aria-selected'); });
        row.setAttribute('aria-selected', 'true');
        drawer.setAttribute('data-sse-current', key);
        body.scrollTop = 0;
        openDialog(drawer, opener || row);
        fire(drawer, 'sse:drawer-item', { key: key, row: row });
    }
    doc.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) { return; }
        var row = t.closest('tr[data-sse-item]');
        if (!row) { return; }
        var inner = t.closest('a, button, input, select, textarea, label, summary, form');
        if (inner && !inner.classList.contains('sse-rowlink') && row.contains(inner)) { return; }
        var table = row.closest('[data-sse-drawer]');
        if (!table) { return; }
        e.preventDefault();
        openItem(table, row, $('.sse-rowlink', row) || row);
    });
    doc.addEventListener('close', function (e) {
        var d = e.target;
        if (!d || !d.classList || !d.classList.contains('sse-drawer')) { return; }
        $$('tr[aria-selected="true"][data-sse-item]').forEach(function (r) { r.removeAttribute('aria-selected'); });
    }, true);

    /* ------------------------------------------------------------------ Onglets */
    function selectTab(tablist, tab, opts) {
        opts = opts || {};
        var tabs = $$('[role="tab"]', tablist);
        tabs.forEach(function (t) {
            var on = t === tab;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.tabIndex = on ? 0 : -1;
            var pid = t.getAttribute('aria-controls');
            var panel = pid ? doc.getElementById(pid) : null;
            if (panel && panel.getAttribute('role') === 'tabpanel') { panel.hidden = !on; }
        });
        if (opts.focus) { tab.focus(); }
        if (tablist.hasAttribute('data-sse-hash') && opts.hash !== false) {
            var v = tab.getAttribute('data-sse-tab') || tab.id || '';
            try {
                if (v && v !== 'all') { window.history.replaceState(null, '', '#' + v); }
                else { window.history.replaceState(null, '', window.location.pathname + window.location.search); }
            } catch (err) { /* contexte sans historique : l'onglet reste choisi */ }
        }
        fire(tablist, 'sse:tab', { tab: tab, value: tab.getAttribute('data-sse-tab') || '' });
    }
    doc.addEventListener('click', function (e) {
        var tab = e.target && e.target.closest ? e.target.closest('[data-sse-tabs] [role="tab"]') : null;
        if (tab) { selectTab(tab.closest('[data-sse-tabs]'), tab); }
    });
    doc.addEventListener('keydown', function (e) {
        var tab = e.target && e.target.closest ? e.target.closest('[data-sse-tabs] [role="tab"]') : null;
        if (!tab) { return; }
        var list = tab.closest('[data-sse-tabs]');
        var tabs = $$('[role="tab"]', list).filter(visible);
        var i = tabs.indexOf(tab), n = i;
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { n = (i + 1) % tabs.length; }
        else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { n = (i - 1 + tabs.length) % tabs.length; }
        else if (e.key === 'Home') { n = 0; }
        else if (e.key === 'End') { n = tabs.length - 1; }
        else { return; }
        e.preventDefault();
        selectTab(list, tabs[n], { focus: true });
    });

    /* ------------------------------------------------------------------ Liste filtrable */
    function norm(s) {
        s = String(s || '').toLowerCase();
        try { s = s.normalize('NFD').replace(/[̀-ͯ]/g, ''); } catch (e) { /* ancien navigateur : accents conservés */ }
        return s;
    }

    function initList(list) {
        var table = $('table', list);
        var tbody = table ? $('tbody', table) : null;
        if (!tbody) { return; }
        var rows = $$('tr[data-sse-row]', tbody);
        var tablist = $('[data-sse-tabs]', list);
        var search = $('[data-sse-search]', list);
        var filters = $$('select[data-sse-filter]', list);
        var more = $('[data-sse-more]', list);
        var count = $('[data-sse-count]', list);
        var emptyF = $('[data-sse-empty-filtered]', list);
        var size = parseInt(list.getAttribute('data-sse-page-size') || '0', 10) || 0;
        var shown = size;
        var state = { group: 'all', q: '', sort: null, dir: 1 };
        var index = rows.map(function (r) { return norm(r.getAttribute('data-search') || r.textContent); });

        function matches(row, i) {
            if (state.group !== 'all') {
                var g = (row.getAttribute('data-groups') || '').split(/\s+/);
                if (g.indexOf(state.group) < 0) { return false; }
            }
            if (state.q && index[i].indexOf(state.q) < 0) { return false; }
            for (var f = 0; f < filters.length; f++) {
                var key = filters[f].getAttribute('data-sse-filter');
                var val = filters[f].value;
                if (val && row.getAttribute('data-' + key) !== val) { return false; }
            }
            return true;
        }

        function render() {
            var total = 0, visibleN = 0;
            rows.forEach(function (row, i) {
                var ok = matches(row, i);
                if (ok) { total++; }
                var show = ok && (!size || total <= shown);
                if (show) { visibleN++; row.removeAttribute('data-sse-hidden'); } else { row.setAttribute('data-sse-hidden', ''); }
            });
            if (count) {
                var tpl = count.getAttribute('data-template') || '%1 / %2';
                count.textContent = tpl.replace('%1', String(total)).replace('%2', String(rows.length));
            }
            if (emptyF) { emptyF.hidden = total !== 0 || rows.length === 0; }
            var tableWrap = table.closest('.sse-table-wrap') || table;
            tableWrap.hidden = total === 0 && rows.length > 0;
            if (more) {
                var rest = total - visibleN;
                more.hidden = !size || rest <= 0;
                var restEl = $('[data-sse-more-count]', more);
                if (restEl) { restEl.textContent = String(rest); }
            }
            fire(list, 'sse:list', { total: total, visible: visibleN });
        }

        function sortBy(th) {
            var key = th.getAttribute('data-sse-sort');
            state.dir = state.sort === key ? -state.dir : 1;
            state.sort = key;
            $$('th[data-sse-sort]', table).forEach(function (h) { h.removeAttribute('aria-sort'); });
            th.setAttribute('aria-sort', state.dir > 0 ? 'ascending' : 'descending');
            var attr = 'data-sort-' + key;
            var pairs = rows.map(function (r, i) { return { r: r, i: i, v: r.hasAttribute(attr) ? r.getAttribute(attr) : (r.children[th.cellIndex] || r).textContent.trim() }; });
            pairs.sort(function (a, b) {
                var x = a.v, y = b.v;
                var nx = parseFloat(x), ny = parseFloat(y);
                var c = (!isNaN(nx) && !isNaN(ny) && String(nx) === String(x).trim() && String(ny) === String(y).trim())
                    ? nx - ny
                    : String(x).localeCompare(String(y), undefined, { sensitivity: 'base', numeric: true });
                return (c || a.i - b.i) * state.dir;
            });
            pairs.forEach(function (p) { tbody.appendChild(p.r); });
            rows = pairs.map(function (p) { return p.r; });
            index = pairs.map(function (p) { return norm(p.r.getAttribute('data-search') || p.r.textContent); });
            render();
        }

        if (tablist) {
            tablist.addEventListener('sse:tab', function (e) {
                state.group = e.detail.value || 'all';
                shown = size;
                render();
            });
            var wanted = (window.location.hash || '').replace(/^#/, '');
            var initial = null;
            $$('[role="tab"]', tablist).forEach(function (t) { if (wanted && t.getAttribute('data-sse-tab') === wanted) { initial = t; } });
            if (!initial) { initial = $('[role="tab"][aria-selected="true"]', tablist) || $('[role="tab"]', tablist); }
            if (initial) { state.group = initial.getAttribute('data-sse-tab') || 'all'; selectTab(tablist, initial, { hash: false }); }
        }
        if (search) {
            var timer = 0;
            search.addEventListener('input', function () {
                window.clearTimeout(timer);
                timer = window.setTimeout(function () { state.q = norm(search.value.trim()); shown = size; render(); }, 80);
            });
            search.addEventListener('keydown', function (e) { if (e.key === 'Escape' && search.value) { search.value = ''; state.q = ''; render(); } });
        }
        filters.forEach(function (f) { f.addEventListener('change', function () { shown = size; render(); }); });
        if (more) { more.addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) { shown += size; render(); } }); }
        $$('th[data-sse-sort]', table).forEach(function (th) {
            th.tabIndex = 0;
            th.addEventListener('click', function () { sortBy(th); });
            th.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); sortBy(th); } });
        });
        $$('[data-sse-reset]', list).forEach(function (b) {
            b.addEventListener('click', function () {
                state.q = ''; state.group = 'all';
                if (search) { search.value = ''; }
                filters.forEach(function (f) { f.value = ''; });
                if (tablist) { var all = $('[data-sse-tab="all"]', tablist); if (all) { selectTab(tablist, all); } }
                render();
            });
        });
        render();
    }

    /* ------------------------------------------------------------------ Sélection de lignes */
    function initSelect(table) {
        var wrap = table.closest('[data-sse-list], .sse-panel') || table.parentNode;
        var bulk = $('[data-sse-bulk]', wrap);
        var bulkCount = bulk ? $('[data-sse-bulk-count]', bulk) : null;
        var all = $('[data-sse-select-all]', table);
        function boxes() { return $$('tbody tr:not([data-sse-hidden]) .sse-tick input', table); }
        function sync() {
            var b = boxes(), n = 0;
            b.forEach(function (c) { var on = c.checked; if (on) { n++; } c.closest('tr').setAttribute('data-sse-checked', on ? 'true' : 'false'); });
            if (all) { all.checked = b.length > 0 && n === b.length; all.indeterminate = n > 0 && n < b.length; }
            if (bulk) { bulk.hidden = n === 0; }
            if (bulkCount) { bulkCount.textContent = String(n); }
            fire(table, 'sse:select', { count: n });
        }
        table.addEventListener('change', function (e) {
            if (e.target === all) { boxes().forEach(function (c) { c.checked = all.checked; }); }
            if (e.target.closest && e.target.closest('.sse-tick')) { sync(); }
        });
        table.addEventListener('click', function (e) {
            var tick = e.target.closest ? e.target.closest('td.sse-tick') : null;
            if (tick && e.target.tagName !== 'INPUT') { var c = $('input', tick); if (c) { c.checked = !c.checked; sync(); } }
        });
        if (wrap) { wrap.addEventListener('sse:list', sync); }
        sync();
    }

    function init() {
        $$('[data-sse-list]').forEach(initList);
        $$('[data-sse-select]').forEach(initSelect);
        $$('[data-sse-tabs]:not([data-sse-managed])').forEach(function (tl) {
            if (tl.closest('[data-sse-list]')) { return; }
            var wanted = (window.location.hash || '').replace(/^#/, '');
            var tabs = $$('[role="tab"]', tl), pick = null;
            tabs.forEach(function (t) { if (wanted && (t.getAttribute('data-sse-tab') === wanted || t.id === wanted)) { pick = t; } });
            selectTab(tl, pick || $('[role="tab"][aria-selected="true"]', tl) || tabs[0], { hash: false });
        });
        $$('dialog[data-sse-autoopen]').forEach(function (d) { d.removeAttribute('open'); openDialog(d, null); });
        $$('[data-sse-drawer][data-sse-open-item]').forEach(function (t) {
            var key = t.getAttribute('data-sse-open-item'), hit = null;
            $$('tr[data-sse-item]', t).forEach(function (r) { if (r.getAttribute('data-sse-item') === key) { hit = r; } });
            if (hit) { openItem(t, hit, null); }
        });
    }
    if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', init); } else { init(); }

    window.SseDs = { open: openDialog, close: closeDialog, openItem: openItem, selectTab: selectTab };
})();
