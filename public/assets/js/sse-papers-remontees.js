/* Consigner depuis les contrôles : ouverture de la modale, filtres, sélection, champs modifiables, doublons dans la sélection. */
(function () {
    'use strict';
    var dlg = document.getElementById('rem-dialog');
    if (!dlg) { return; }
    var rows = Array.prototype.slice.call(dlg.querySelectorAll('[data-rem-row]'));
    var q = dlg.querySelector('[data-rem-q]');
    var fState = dlg.querySelector('[data-rem-state]');
    var fType = dlg.querySelector('[data-rem-type]');
    var fScope = dlg.querySelector('[data-rem-scope]');
    var fSince = dlg.querySelector('[data-rem-since]');
    var none = dlg.querySelector('[data-rem-none]');
    var count = dlg.querySelector('[data-rem-count]');
    var submit = dlg.querySelector('[data-rem-submit]');
    function txt(k, d) { var n = dlg.querySelector('[data-rem-t="' + k + '"]'); return n ? n.textContent : d; }
    var T = {
        none: txt('none', 'Aucun papier sélectionné'),
        one: txt('one', '1 papier sélectionné'),
        many: txt('many', '%d papiers sélectionnés'),
        show: txt('show', 'Modifier'),
        hide: txt('hide', 'Replier')
    };

    function picked(row) { var c = row.querySelector('[data-rem-pick]'); return !!(c && c.checked && !c.disabled); }

    function filter() {
        var text = q ? q.value.trim().toLowerCase() : '';
        var st = fState ? fState.value : '';
        var ty = fType ? fType.value : '';
        var sc = fScope ? fScope.value : '';
        var days = fSince && fSince.value ? parseInt(fSince.value, 10) : 0;
        var min = days > 0 ? Date.now() / 1000 - days * 86400 : 0;
        var shown = 0;
        rows.forEach(function (row) {
            var ok = (!text || (row.getAttribute('data-text') || '').indexOf(text) !== -1)
                && (!st || row.getAttribute('data-state') === st)
                && (!ty || row.getAttribute('data-type') === ty)
                && (!sc || row.getAttribute('data-scope') === sc)
                && (!min || parseInt(row.getAttribute('data-ts') || '0', 10) >= min);
            // Une ligne cochée reste visible : on ne cache jamais ce qui sera envoyé.
            row.hidden = !(ok || picked(row));
            if (!row.hidden) { shown++; }
        });
        if (none) { none.hidden = shown > 0 || rows.length === 0; }
    }

    function refresh() {
        var n = 0;
        var seen = {};
        rows.forEach(function (row) {
            var on = picked(row);
            row.classList.toggle('is-picked', on);
            if (on) {
                n++;
                var k = row.getAttribute('data-key');
                seen[k] = (seen[k] || 0) + 1;
            }
        });
        rows.forEach(function (row) {
            var dup = row.querySelector('[data-rem-dup]');
            if (dup) { dup.hidden = !(picked(row) && seen[row.getAttribute('data-key')] > 1); }
        });
        if (count) { count.textContent = n === 0 ? T.none : (n === 1 ? T.one : T.many.replace('%d', String(n))); }
        if (submit) { submit.disabled = n === 0; }
    }

    function open() {
        if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
        filter();
        refresh();
    }
    function close() {
        if (typeof dlg.close === 'function') { dlg.close(); } else { dlg.removeAttribute('open'); }
    }

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!(t instanceof Element)) { return; }
        if (t.closest('[data-rem-open]')) { e.preventDefault(); open(); return; }
        if (t.closest('[data-rem-close]')) { e.preventDefault(); close(); return; }
        var ed = t.closest('[data-rem-edit]');
        if (ed) {
            var box = document.getElementById(ed.getAttribute('aria-controls'));
            if (box) {
                var show = box.hidden;
                box.hidden = !show;
                ed.setAttribute('aria-expanded', show ? 'true' : 'false');
                ed.textContent = show ? T.hide : T.show;
            }
        }
    });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) { close(); } });
    dlg.addEventListener('change', function (e) {
        var t = e.target;
        if (!(t instanceof Element)) { return; }
        if (t.matches('[data-rem-pick]')) { refresh(); }
        if (t.matches('[data-rem-state],[data-rem-type],[data-rem-scope],[data-rem-since]')) { filter(); }
    });
    if (q) { q.addEventListener('input', filter); }
    // Modifier un champ coche la ligne : ce qu'on corrige est ce qu'on veut consigner.
    dlg.addEventListener('input', function (e) {
        var t = e.target;
        if (!(t instanceof Element) || !t.closest('.rem-fields')) { return; }
        var row = t.closest('[data-rem-row]');
        var c = row && row.querySelector('[data-rem-pick]');
        if (c && !c.disabled && !c.checked) { c.checked = true; refresh(); }
    });

    var pre = parseInt(dlg.getAttribute('data-rem-preselect') || '0', 10);
    if (pre > 0) {
        var target = rows.filter(function (r) { return parseInt(r.getAttribute('data-id'), 10) === pre; })[0];
        var box = target && target.querySelector('[data-rem-pick]');
        if (box && !box.disabled) { box.checked = true; }
        if (fState) { fState.value = ''; }
        open();
        if (target) { target.scrollIntoView({ block: 'center' }); }
    }
    refresh();
})();
