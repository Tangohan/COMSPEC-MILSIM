/**
 * JNET — espace d'une unité : recherche dans l'espace (touche « / »), filtres instantanés du suivi des membres et envoi
 * automatique du changement de responsable d'une tâche. Tout reste utilisable sans ce script : la recherche est un
 * confort, le tableau est complet et chaque changement de responsable a son bouton (balise noscript).
 */
(function () {
    'use strict';

    // —— Recherche ——
    var form = document.querySelector('[data-uw-search]');
    if (form) {
        var input = form.querySelector('input[type="search"]');
        var list = form.querySelector('.uw-search__list');
        var openers = document.querySelectorAll('[data-uw-search-open]');
        var url = form.getAttribute('data-url') || '';
        var timer = null;
        var seq = 0;
        var active = -1;

        var setOpen = function (open) {
            form.hidden = !open;
            openers.forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
            if (open) {
                input.focus();
                input.select();
            } else {
                list.textContent = '';
                active = -1;
            }
        };
        var items = function () { return Array.prototype.slice.call(list.querySelectorAll('a')); };
        var mark = function (i) {
            var all = items();
            if (!all.length) { active = -1; return; }
            active = (i + all.length) % all.length;
            all.forEach(function (a, k) { a.classList.toggle('is-on', k === active); });
            all[active].scrollIntoView({ block: 'nearest' });
        };
        var show = function (rows, q) {
            list.textContent = '';
            if (!rows.length) {
                var none = document.createElement('li');
                none.className = 'uw-search__none';
                none.textContent = 'Aucun résultat pour « ' + q + ' ».';
                list.appendChild(none);
                return;
            }
            rows.forEach(function (r) {
                var li = document.createElement('li');
                var a = document.createElement('a');
                a.href = r.href;
                var t = document.createElement('small');
                t.textContent = r.type;
                var b = document.createElement('b');
                b.textContent = r.label;
                a.appendChild(t);
                a.appendChild(b);
                if (r.detail) {
                    var d = document.createElement('span');
                    d.textContent = r.detail;
                    a.appendChild(d);
                }
                li.appendChild(a);
                list.appendChild(li);
            });
            mark(0);
        };
        var run = function () {
            var q = input.value.trim();
            if (q.length < 2) {
                list.textContent = '';
                active = -1;
                return;
            }
            var mine = ++seq;
            fetch(url + '?q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : { results: [] }; })
                .then(function (data) { if (mine === seq) { show(Array.isArray(data.results) ? data.results : [], q); } })
                .catch(function () { if (mine === seq) { show([], q); } });
        };

        openers.forEach(function (b) { b.addEventListener('click', function () { setOpen(form.hidden); }); });
        var closer = form.querySelector('[data-uw-search-close]');
        if (closer) { closer.addEventListener('click', function () { setOpen(false); }); }
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var all = items();
            if (all[active]) { window.location.href = all[active].href; }
        });
        input.addEventListener('input', function () {
            window.clearTimeout(timer);
            timer = window.setTimeout(run, 180);
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); mark(active + 1); }
            if (e.key === 'ArrowUp') { e.preventDefault(); mark(active - 1); }
        });
        document.addEventListener('keydown', function (e) {
            var tag = (e.target && e.target.tagName) || '';
            var typing = /INPUT|TEXTAREA|SELECT/.test(tag) || (e.target && e.target.isContentEditable);
            if (e.key === 'Escape' && !form.hidden) { setOpen(false); return; }
            if (e.key === '/' && !typing && !e.ctrlKey && !e.metaKey && !e.altKey) {
                e.preventDefault();
                setOpen(true);
            }
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setOpen(true);
            }
        });
    }

    // —— Suivi des membres : filtres instantanés ——
    var tools = document.querySelector('[data-uw-filter]');
    var table = document.querySelector('[data-uw-table]');
    if (tools && table) {
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
        var none = table.querySelector('[data-uw-none]');
        var q = tools.querySelector('[data-uw-q]');
        var current = 'all';
        var apply = function () {
            var needle = q ? q.value.trim().toLowerCase() : '';
            var visible = 0;
            rows.forEach(function (tr) {
                var ok = current === 'all'
                    || (current === 'todo' ? tr.getAttribute('data-todo') === '1' : tr.getAttribute('data-status') === current);
                if (ok && needle) { ok = (tr.getAttribute('data-search') || '').indexOf(needle) !== -1; }
                tr.hidden = !ok;
                if (ok) { visible++; }
            });
            if (none) { none.hidden = visible !== 0; }
        };
        tools.querySelectorAll('[data-filter]').forEach(function (b) {
            b.addEventListener('click', function () {
                current = b.getAttribute('data-filter') || 'all';
                tools.querySelectorAll('[data-filter]').forEach(function (o) {
                    var on = o === b;
                    o.classList.toggle('is-on', on);
                    o.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
                apply();
            });
        });
        if (q) { q.addEventListener('input', apply); }
    }

    // —— Tâches : le changement de responsable s'envoie seul ——
    document.querySelectorAll('[data-uw-autosubmit]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            if (sel.form) { sel.form.submit(); }
        });
    });
})();
