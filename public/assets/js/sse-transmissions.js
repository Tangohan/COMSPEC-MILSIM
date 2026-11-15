/*
 * Bureau SSE, transmissions terrain : onglets, menu d'actions, aperçu latéral, fenêtres et filtre des champs.
 * Les textes affichés restent dans le HTML de la page (le script ne fait que les remplir et les montrer).
 * Accessibilité : <dialog> modal (focus piégé, Échap, aria-modal natifs), retour du focus sur le bouton d'origine.
 */
(function () {
    'use strict';
    var root = document.querySelector('[data-txp]');
    if (!root) { return; }
    var lastOpener = null;

    /* ---------- Onglets ---------- */
    var tabs = [].slice.call(root.querySelectorAll('[data-txp-tab]'));
    var panels = [].slice.call(root.querySelectorAll('[data-txp-panel]'));
    function selectTab(key, focus) {
        var found = false;
        tabs.forEach(function (t) {
            var on = t.getAttribute('data-txp-tab') === key;
            found = found || on;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.tabIndex = on ? 0 : -1;
            if (on && focus) { t.focus(); }
        });
        if (!found) { return; }
        panels.forEach(function (p) { p.hidden = p.getAttribute('data-txp-panel') !== key; });
        try {
            var url = new URL(window.location.href);
            if (key === tabs[0].getAttribute('data-txp-tab')) { url.searchParams.delete('onglet'); } else { url.searchParams.set('onglet', key); }
            window.history.replaceState(null, '', url.toString());
        } catch (e) { /* adresse non modifiable : sans importance */ }
    }
    tabs.forEach(function (t, i) {
        t.addEventListener('click', function () { selectTab(t.getAttribute('data-txp-tab'), false); });
        t.addEventListener('keydown', function (e) {
            var n = null;
            if (e.key === 'ArrowRight') { n = tabs[(i + 1) % tabs.length]; }
            else if (e.key === 'ArrowLeft') { n = tabs[(i - 1 + tabs.length) % tabs.length]; }
            else if (e.key === 'Home') { n = tabs[0]; }
            else if (e.key === 'End') { n = tabs[tabs.length - 1]; }
            if (n) { e.preventDefault(); selectTab(n.getAttribute('data-txp-tab'), true); }
        });
    });
    root.querySelectorAll('[data-txp-goto]').forEach(function (a) {
        a.addEventListener('click', function (e) { e.preventDefault(); selectTab(a.getAttribute('data-txp-goto'), true); });
    });
    var wanted = '';
    try { wanted = new URL(window.location.href).searchParams.get('onglet') || ''; } catch (e) { wanted = ''; }
    if (wanted !== '') { selectTab(wanted, false); }

    /* ---------- Fenêtres et tiroir ---------- */
    function openDialog(dlg, opener) {
        if (!dlg || typeof dlg.showModal !== 'function') { return; }
        lastOpener = opener || document.activeElement;
        dlg.showModal();
    }
    function closeDialog(dlg) {
        if (dlg && typeof dlg.close === 'function') { dlg.close(); }
    }
    root.querySelectorAll('dialog[data-txp-dialog], dialog[data-txp-drawer]').forEach(function (dlg) {
        dlg.addEventListener('click', function (e) { if (e.target === dlg) { closeDialog(dlg); } });
        dlg.addEventListener('close', function () {
            if (lastOpener && document.contains(lastOpener) && typeof lastOpener.focus === 'function') { lastOpener.focus(); }
        });
    });
    root.addEventListener('click', function (e) {
        var open = e.target.closest('[data-txp-open]');
        if (open) { e.preventDefault(); openDialog(document.getElementById(open.getAttribute('data-txp-open')), open); return; }
        var close = e.target.closest('[data-txp-close]');
        if (close) { e.preventDefault(); closeDialog(close.closest('dialog')); }
    });

    /* ---------- Menu d'actions ---------- */
    var openMenu = null;
    function closeMenus(refocus) {
        if (!openMenu) { return; }
        var btn = openMenu.querySelector('.txp-menu__btn');
        var list = openMenu.querySelector('.txp-menu__list');
        list.hidden = true;
        list.style.cssText = '';
        btn.setAttribute('aria-expanded', 'false');
        openMenu = null;
        if (refocus) { btn.focus(); }
    }
    function place(btn, list) {
        var r = btn.getBoundingClientRect();
        list.style.position = 'fixed';
        var h = list.offsetHeight;
        var top = r.bottom + 4;
        if (top + h > window.innerHeight - 8) { top = Math.max(8, r.top - h - 4); }
        var left = Math.min(Math.max(8, r.right - list.offsetWidth), window.innerWidth - list.offsetWidth - 8);
        list.style.top = top + 'px';
        list.style.left = left + 'px';
    }
    root.querySelectorAll('[data-txp-menu]').forEach(function (menu) {
        var btn = menu.querySelector('.txp-menu__btn');
        var list = menu.querySelector('.txp-menu__list');
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (openMenu === menu) { closeMenus(true); return; }
            closeMenus();
            list.hidden = false;
            place(btn, list);
            btn.setAttribute('aria-expanded', 'true');
            openMenu = menu;
            var first = list.querySelector('[role="menuitem"]');
            if (first) { first.focus(); }
        });
        list.addEventListener('keydown', function (e) {
            var items = [].slice.call(list.querySelectorAll('[role="menuitem"]'));
            var i = items.indexOf(document.activeElement);
            if (e.key === 'ArrowDown') { e.preventDefault(); items[(i + 1) % items.length].focus(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); items[(i - 1 + items.length) % items.length].focus(); }
            else if (e.key === 'Home') { e.preventDefault(); items[0].focus(); }
            else if (e.key === 'End') { e.preventDefault(); items[items.length - 1].focus(); }
            else if (e.key === 'Tab') { closeMenus(); }
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && openMenu) { e.stopPropagation(); closeMenus(true); }
    }, true);
    document.addEventListener('click', function (e) { if (openMenu && !openMenu.contains(e.target)) { closeMenus(); } });
    window.addEventListener('resize', function () { closeMenus(); });
    window.addEventListener('scroll', function (e) {
        if (openMenu && !openMenu.contains(e.target)) { closeMenus(); }
    }, true);

    /* ---------- Aperçu latéral d'une transmission ---------- */
    var drawer = document.getElementById('txp-drawer');
    function fill(dlg, data) {
        dlg.querySelectorAll('[data-f]').forEach(function (el) {
            var v = String(data[el.getAttribute('data-f')] || '').trim();
            el.textContent = v !== '' ? v : '—';
            var row = el.parentNode;
            if (row && row.parentNode && row.parentNode.tagName === 'DL') { row.hidden = v === ''; }
        });
        dlg.querySelectorAll('[data-f-href]').forEach(function (a) { a.setAttribute('href', data[a.getAttribute('data-f-href')] || '#'); });
    }
    root.addEventListener('click', function (e) {
        var peek = e.target.closest('[data-txp-peek]');
        if (!peek || !drawer) { return; }
        var tr = peek.closest('tr[data-tx]');
        if (!tr) { return; }
        var data;
        try { data = JSON.parse(tr.getAttribute('data-tx')); } catch (err) { return; }
        e.preventDefault();
        closeMenus();
        fill(drawer, data);
        openDialog(drawer, tr.querySelector('.txp-peek') || peek);
    });

    /* ---------- Données : filtre et repli ---------- */
    var filter = root.querySelector('[data-txp-fieldfilter]');
    var groups = [].slice.call(root.querySelectorAll('[data-txp-group]'));
    var none = root.querySelector('[data-txp-nomatch]');
    if (filter) {
        filter.addEventListener('input', function () {
            var q = filter.value.trim().toLowerCase();
            var any = false;
            groups.forEach(function (g) {
                var hit = 0;
                g.querySelectorAll('[data-txp-field]').forEach(function (f) {
                    var ok = q === '' || (f.textContent || '').toLowerCase().indexOf(q) !== -1;
                    f.hidden = !ok;
                    if (ok) { hit++; }
                });
                g.hidden = hit === 0;
                if (q !== '' && hit > 0) { g.open = true; }
                any = any || hit > 0;
            });
            if (none) { none.hidden = any || q === ''; }
        });
    }
    root.querySelectorAll('[data-txp-expand]').forEach(function (b) {
        b.addEventListener('click', function () {
            var on = b.getAttribute('data-txp-expand') === '1';
            groups.forEach(function (g) { if (!g.hidden) { g.open = on; } });
        });
    });
})();
