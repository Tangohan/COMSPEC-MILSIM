/*
 * Back-office « Remontées » : onglets, fenêtres, copie, listes de choix avec recherche, identifiant Steam.
 * Aucun texte ici : tous les libellés viennent du HTML (attributs data-rmt-*), donc de la traduction du site.
 */
(function () {
    'use strict';
    var root = document.querySelector('[data-rmt]');
    if (!root) { return; }

    function all(sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); }
    function fold(s) {
        return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/’/g, "'").replace(/\s+/g, ' ').trim();
    }

    /* ——— Message bref (copie) ——— */
    var toastTimer = null;
    function toast(message) {
        if (!message) { return; }
        var el = root.querySelector('.rmt-toast');
        if (!el) {
            el = document.createElement('div');
            el.className = 'rmt-toast';
            el.setAttribute('role', 'status');
            root.appendChild(el);
        }
        el.textContent = message;
        el.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { el.hidden = true; }, 2600);
    }
    function copy(text) {
        var ok = function () { toast(root.getAttribute('data-rmt-copied')); };
        var ko = function () { toast(root.getAttribute('data-rmt-copy-failed')); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(ok, ko);
            return;
        }
        try {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            (document.execCommand('copy') ? ok : ko)();
            document.body.removeChild(ta);
        } catch (e) { ko(); }
    }

    /* ——— Onglets ——— */
    var tabs = all('[data-rmt-tab]');
    function selectTab(key, focus) {
        var found = false;
        tabs.forEach(function (tab) {
            var on = tab.getAttribute('data-rmt-tab') === key;
            found = found || on;
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
            tab.tabIndex = on ? 0 : -1;
            if (on && focus) { tab.focus(); }
        });
        if (!found) { return; }
        all('[data-rmt-panel]').forEach(function (panel) { panel.hidden = panel.getAttribute('data-rmt-panel') !== key; });
        try { history.replaceState(null, '', '#' + key); } catch (e) { /* adresse inchangée */ }
    }
    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () { selectTab(tab.getAttribute('data-rmt-tab'), false); });
        tab.addEventListener('keydown', function (ev) {
            var step = ev.key === 'ArrowRight' ? 1 : (ev.key === 'ArrowLeft' ? -1 : 0);
            if (ev.key === 'Home') { ev.preventDefault(); selectTab(tabs[0].getAttribute('data-rmt-tab'), true); return; }
            if (ev.key === 'End') { ev.preventDefault(); selectTab(tabs[tabs.length - 1].getAttribute('data-rmt-tab'), true); return; }
            if (!step) { return; }
            ev.preventDefault();
            selectTab(tabs[(i + step + tabs.length) % tabs.length].getAttribute('data-rmt-tab'), true);
        });
    });
    var wanted = (location.hash || '').replace('#', '');
    if (wanted && tabs.some(function (t) { return t.getAttribute('data-rmt-tab') === wanted; })) { selectTab(wanted, false); }

    /* ——— Fenêtres ——— */
    function openDialog(id) {
        var dlg = document.getElementById(id);
        if (!dlg) { return; }
        all('[data-rmt-more][open]').forEach(function (d) { d.open = false; });
        if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
    }
    root.addEventListener('click', function (ev) {
        var t = ev.target.closest('[data-rmt-open],[data-rmt-close],[data-rmt-copy],[data-rmt-fill]');
        if (!t) {
            all('[data-rmt-more][open]').forEach(function (d) { if (!d.contains(ev.target)) { d.open = false; } });
            return;
        }
        if (t.hasAttribute('data-rmt-open')) { openDialog(t.getAttribute('data-rmt-open')); return; }
        if (t.hasAttribute('data-rmt-close')) {
            var dlg = t.closest('dialog');
            if (dlg) { if (typeof dlg.close === 'function') { dlg.close(); } else { dlg.removeAttribute('open'); } }
            return;
        }
        if (t.hasAttribute('data-rmt-copy')) {
            copy(t.getAttribute('data-rmt-copy'));
            all('[data-rmt-more][open]').forEach(function (d) { d.open = false; });
            return;
        }
        var target = root.querySelector(t.getAttribute('data-rmt-fill'));
        if (target) { target.value = t.getAttribute('data-value') || ''; target.focus(); }
    });
    all('dialog').forEach(function (dlg) {
        // Clic sur le fond : la fenêtre se ferme.
        dlg.addEventListener('click', function (ev) { if (ev.target === dlg && typeof dlg.close === 'function') { dlg.close(); } });
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') { all('[data-rmt-more][open]').forEach(function (d) { d.open = false; }); }
    });

    /* ——— Suppression : le bouton attend la référence recopiée ——— */
    all('[data-rmt-confirm]').forEach(function (input) {
        var form = input.closest('form');
        var submit = form ? form.querySelector('[data-rmt-confirm-submit]') : null;
        if (!submit) { return; }
        var check = function () { submit.disabled = input.value.trim().toUpperCase() !== String(input.getAttribute('data-rmt-confirm')).toUpperCase(); };
        input.addEventListener('input', check);
        check();
    });

    /* ——— Listes de choix avec recherche (transmettre, doublon, relier) ——— */
    all('[data-rmt-picker]').forEach(function (picker) {
        var input = picker.querySelector('[data-rmt-picker-input]');
        var list = picker.querySelector('[data-rmt-picker-list]');
        var empty = picker.querySelector('[data-rmt-picker-empty]');
        var name = picker.getAttribute('data-rmt-picker-name');
        var url = picker.getAttribute('data-rmt-picker-url');
        var remoteOnly = picker.hasAttribute('data-rmt-picker-remote');
        if (!input || !list) { return; }
        var timer = null;
        var seq = 0;

        function option(item) {
            var label = document.createElement('label');
            label.className = 'rmt-opt';
            label.setAttribute('data-rmt-remote', '');
            var radio = document.createElement('input');
            radio.type = 'radio'; radio.name = name; radio.value = item.value; radio.required = true;
            var span = document.createElement('span');
            span.textContent = item.label; span.setAttribute('translate', 'no');
            var small = document.createElement('small');
            small.textContent = item.kind || '';
            label.appendChild(radio); label.appendChild(span); label.appendChild(small);
            return label;
        }
        function refreshEmpty() {
            if (!empty) { return; }
            empty.hidden = all('.rmt-opt', list).some(function (o) { return !o.hidden; });
        }
        function filterLocal(q) {
            all('.rmt-opt', list).forEach(function (o) {
                if (o.hasAttribute('data-rmt-remote')) { return; }
                var hit = q === '' || fold(o.textContent).indexOf(q) !== -1;
                var radio = o.querySelector('input');
                o.hidden = !hit && !(radio && radio.checked);
            });
        }
        function search() {
            var raw = input.value.trim();
            var q = fold(raw);
            if (!remoteOnly) { filterLocal(q); }
            all('[data-rmt-remote]', list).forEach(function (o) {
                var radio = o.querySelector('input');
                if (!(radio && radio.checked)) { o.remove(); }
            });
            if (raw.length < 2 || !url || !window.fetch) { refreshEmpty(); return; }
            var mine = ++seq;
            fetch(url + '&q=' + encodeURIComponent(raw), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : { items: [] }; })
                .then(function (data) {
                    if (mine !== seq) { return; }
                    var have = {};
                    all('input[type="radio"]', list).forEach(function (r) { have[r.value] = true; });
                    (data.items || []).forEach(function (item) {
                        if (!item || !item.value || have[item.value]) { return; }
                        list.appendChild(option(item));
                    });
                    refreshEmpty();
                })
                .catch(function () { refreshEmpty(); });
        }
        input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(search, 180); });
        input.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); search(); } });
    });

    /* ——— Identifiant Steam : affichage et copie journalisés ——— */
    all('[data-rmt-steam]').forEach(function (form) {
        var out = root.querySelector('[data-rmt-steam-out]');
        var show = form.querySelector('[data-rmt-steam-show]');
        var copyBtn = form.querySelector('[data-rmt-steam-copy]');
        var token = form.querySelector('input[name="_csrf_token"]');
        var value = '';
        function ask(mode) {
            var body = new URLSearchParams();
            body.set('_csrf_token', token ? token.value : '');
            body.set('mode', mode);
            return fetch(form.getAttribute('action'), {
                method: 'POST', credentials: 'same-origin', body: body,
                headers: { 'X-Requested-With': 'fetch', Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' }
            }).then(function (r) { return r.json(); });
        }
        if (!window.fetch || !out) { return; }
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            ask('reveal').then(function (data) {
                if (!data || !data.ok || !data.steam) { toast(data && data.message); return; }
                value = data.steam;
                out.textContent = value;
                if (show) { show.hidden = true; }
                if (copyBtn) { copyBtn.hidden = false; copyBtn.focus(); }
            }).catch(function () { form.submit(); });
        });
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                if (!value) { return; }
                ask('copy').then(function (data) { if (data && data.ok) { copy(data.steam || value); } });
            });
        }
    });
})();
