/*
 * Poste maître du jeu (SSE) : onglets, filtres, tiroir de détail, modales de création et de retrait.
 * Les dialogues sont des <dialog> natifs (showModal : focus piégé, Échap, arrière-plan inerte).
 */
(function () {
    'use strict';
    var data = {};
    try { data = JSON.parse((document.getElementById('mj-data') || {}).textContent || '{}'); } catch (e) { data = {}; }
    var lastOpener = null;
    var current = null;

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    /* Onglets */
    var tablist = $('[data-mj-tabs]');
    var tabs = tablist ? $all('[role="tab"]', tablist) : [];
    function selectTab(tab, focus) {
        tabs.forEach(function (t) {
            var on = t === tab;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.tabIndex = on ? 0 : -1;
            var panel = document.getElementById(t.getAttribute('aria-controls'));
            if (panel) { panel.hidden = !on; }
        });
        if (focus) { tab.focus(); }
        try { history.replaceState(null, '', '#' + tab.getAttribute('aria-controls')); } catch (e) { /* ignore */ }
    }
    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () { selectTab(tab, false); });
        tab.addEventListener('keydown', function (ev) {
            var next = null;
            if (ev.key === 'ArrowRight') { next = tabs[(i + 1) % tabs.length]; }
            else if (ev.key === 'ArrowLeft') { next = tabs[(i - 1 + tabs.length) % tabs.length]; }
            else if (ev.key === 'Home') { next = tabs[0]; }
            else if (ev.key === 'End') { next = tabs[tabs.length - 1]; }
            if (next) { ev.preventDefault(); selectTab(next, true); }
        });
    });
    var wanted = (location.hash || '').replace('#', '');
    tabs.forEach(function (t) { if (t.getAttribute('aria-controls') === wanted) { selectTab(t, false); } });

    /* Filtres et pagination (25 lignes à la fois) */
    var PAGE = 25;
    var views = [];
    function track(table, test) {
        var panel = table.closest('.mj-panel');
        var view = { table: table, test: test, limit: PAGE, none: $('[data-mj-none]', panel), more: $('[data-mj-more]', panel), count: $('[data-mj-count]', panel) };
        views.push(view);
        if (view.more) { view.more.addEventListener('click', function () { view.limit += PAGE; render(view); }); }
        render(view);
        return view;
    }
    function render(view) {
        var matches = 0;
        var shown = 0;
        $all('tbody tr', view.table).forEach(function (r) {
            var ok = view.test(r);
            if (ok) { matches++; }
            var visible = ok && matches <= view.limit;
            r.hidden = !visible;
            if (visible) { shown++; }
        });
        if (view.none) { view.none.hidden = matches !== 0; }
        if (view.more) { view.more.hidden = shown >= matches; }
        if (view.count) { view.count.textContent = matches > PAGE ? shown + ' / ' + matches : ''; }
    }
    var level = $('[data-mj-level]');
    var entries = $('[data-mj-entries]');
    if (level && entries) {
        var ev = track(entries, function (r) { return !level.value || r.getAttribute('data-level') === level.value; });
        level.addEventListener('change', function () { ev.limit = PAGE; render(ev); });
    }
    $all('[data-mj-filter]').forEach(function (input) {
        var table = document.getElementById(input.getAttribute('data-mj-filter'));
        if (!table) { return; }
        var pv = track(table, function (r) {
            var q = input.value.trim().toLowerCase();
            return !q || (r.textContent || '').toLowerCase().indexOf(q) !== -1;
        });
        input.addEventListener('input', function () { pv.limit = PAGE; render(pv); });
    });

    /* Dialogues */
    function open(dlg, opener) {
        if (!dlg || typeof dlg.showModal !== 'function') { return; }
        lastOpener = opener || document.activeElement;
        dlg.showModal();
    }
    function close(dlg) {
        if (dlg && dlg.open) { dlg.close(); }
    }
    $all('dialog.mj-dialog').forEach(function (dlg) {
        dlg.addEventListener('click', function (ev) { if (ev.target === dlg) { dlg.close(); } });
        dlg.addEventListener('close', function () {
            var still = $all('dialog.mj-dialog').some(function (d) { return d.open; });
            if (!still && lastOpener && document.contains(lastOpener)) { lastOpener.focus(); }
        });
    });
    document.addEventListener('click', function (ev) {
        var t = ev.target;
        if (!(t instanceof Element)) { return; }
        var o = t.closest('[data-mj-open]');
        if (o) { open(document.getElementById(o.getAttribute('data-mj-open')), o); return; }
        var c = t.closest('[data-mj-close]');
        if (c) { close(c.closest('dialog')); return; }
        var d = t.closest('[data-mj-detail]');
        if (d) { showDetail(d.getAttribute('data-mj-detail'), d); return; }
        if (t.closest('[data-mj-remove]') && current) { askRemove(); }
    });

    function setText(root, key, value) {
        var el = $('[data-mj-d="' + key + '"]', root);
        if (el) { el.textContent = value || ''; }
        return el;
    }
    function word(root, key) {
        var el = $('[data-mj-t="' + key + '"]', root);
        return el ? (el.textContent || '').trim() : '';
    }
    function showDetail(id, opener) {
        var item = data[id];
        var dlg = document.getElementById('mj-drawer');
        if (!item || !dlg) { return; }
        current = item;
        setText(dlg, 'name', item.name);
        setText(dlg, 'alias', item.alias ? word(dlg, 'alias') + ' « ' + item.alias + ' »' : '');
        var badge = setText(dlg, 'levelLabel', item.levelLabel);
        if (badge) { badge.className = 'badge ' + (item.tone || ''); }
        setText(dlg, 'notes', item.notes || word(dlg, 'nonotes'));
        setText(dlg, 'origin', word(dlg, item.origin));
        setText(dlg, 'added', item.added || '-');
        setText(dlg, 'docs', item.docs);
        var docs = $('[data-mj-docs]', dlg);
        if (docs) { docs.hidden = !item.docs; }
        open(dlg, opener);
    }
    function askRemove() {
        var dlg = document.getElementById('mj-remove');
        if (!dlg || !current) { return; }
        var form = $('[data-mj-remove-form]', dlg);
        if (form) { form.setAttribute('action', current.removeUrl); }
        var name = $('[data-mj-remove-name]', dlg);
        if (name) { name.textContent = current.name; }
        open(dlg, lastOpener);
    }
})();
