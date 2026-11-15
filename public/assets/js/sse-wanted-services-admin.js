/*
 * SSE > Réglages de la notice > onglet « Services » : filtre de la liste, tiroir « Nouveau service » / « Modifier le service »
 * (champs remplis depuis la ligne, onglets du tiroir, ouverture et fermeture des fenêtres <dialog>). Aucun libellé dans ce fichier : titres et boutons viennent des attributs
 * data-title-* de la vue, donc traduits avec la page. Sans JavaScript, la liste reste complète et les opérations par bouton
 * (dupliquer, masquer, par défaut, ordre) fonctionnent ; seul le tiroir demande le script.
 */
(function () {
    'use strict';
    var admin = document.querySelector('[data-ws-admin]');
    if (!admin) { return; }
    function $(s, r) { return (r || document).querySelector(s); }
    function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
    function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }


    /* ------------------------------------------------------------------ Fenêtres (tiroirs) */
    var openers = new WeakMap();
    function openDialog(d, opener) {
        if (!d || d.hasAttribute('open')) { return; }
        openers.set(d, opener || document.activeElement);
        if (typeof d.showModal === 'function') { d.showModal(); } else { d.setAttribute('open', ''); }
        document.documentElement.classList.add('ws-dialog-open');
    }
    function closeDialog(d) {
        if (!d || !d.hasAttribute('open')) { return; }
        if (typeof d.close === 'function') { d.close(); } else { d.removeAttribute('open'); }
    }
    function afterClose(d) {
        document.documentElement.classList.remove('ws-dialog-open');
        var o = openers.get(d);
        if (o && o.focus) { o.focus({ preventScroll: true }); }
    }
    $$('dialog.ws-dialog').forEach(function (d) {
        d.addEventListener('close', function () { afterClose(d); });
        // Clic sur le fond (hors du contenu) : fermeture.
        d.addEventListener('click', function (e) { if (e.target === d) { closeDialog(d); } });
        if (d.hasAttribute('data-ws-autoopen')) { openDialog(d, null); }
    });
    document.addEventListener('click', function (e) {
        var op = e.target.closest ? e.target.closest('[data-ws-open]') : null;
        if (op) { openDialog($(op.getAttribute('data-ws-open')), op); return; }
        var cl = e.target.closest ? e.target.closest('[data-ws-close]') : null;
        if (cl) { closeDialog(cl.closest('dialog')); }
    });

    /* ------------------------------------------------------------------ Filtre */
    var q = $('[data-ws-q]', admin), fam = $('[data-ws-fam]', admin), src = $('[data-ws-src]', admin);
    var rows = $$('[data-ws-row]', admin), none = $('[data-ws-none]', admin), count = $('[data-ws-count]', admin);
    var template = count ? count.textContent : '';
    function filter() {
        var text = norm(q ? q.value : ''), f = fam ? fam.value : '', o = src ? src.value : '';
        var shown = 0;
        rows.forEach(function (r) {
            var ok = (!text || norm(r.getAttribute('data-search')).indexOf(text) >= 0)
                && (!f || r.getAttribute('data-family') === f)
                && (!o || (o === 'off' ? r.getAttribute('data-enabled') === '0' : r.getAttribute('data-source') === o));
            r.hidden = !ok;
            if (ok) { shown++; }
        });
        if (none) { none.hidden = shown !== 0; }
        if (count) {
            var total = parseInt(count.getAttribute('data-total') || '0', 10);
            count.textContent = (text || f || o) ? shown + ' / ' + total : template;
        }
    }
    [q, fam, src].forEach(function (n) { if (n) { n.addEventListener('input', filter); n.addEventListener('change', filter); } });

    /* ------------------------------------------------------------------ Tiroir de service */
    var dlg = $('[data-ws-drawer]');
    if (!dlg) { return; }
    var form = $('[data-ws-form]', dlg);
    var opInput = $('[data-ws-op]', dlg), codeInput = $('[data-ws-code]', dlg);
    var title = $('[data-ws-title]', dlg), save = $('[data-ws-save]', dlg), del = $('[data-ws-delete]', dlg);
    var tabs = $$('[data-ws-tab]', dlg), panes = $$('[data-ws-pane]', dlg);
    var T = { create: dlg.getAttribute('data-title-create') || '', update: dlg.getAttribute('data-title-update') || '' };

    function showPane(key) {
        tabs.forEach(function (t) { var on = t.getAttribute('data-ws-tab') === key; t.setAttribute('aria-selected', on ? 'true' : 'false'); t.tabIndex = on ? 0 : -1; });
        panes.forEach(function (p) { p.hidden = p.getAttribute('data-ws-pane') !== key; });
    }
    tabs.forEach(function (t, i) {
        t.addEventListener('click', function () { showPane(t.getAttribute('data-ws-tab')); });
        t.addEventListener('keydown', function (e) {
            var n = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : -1;
            if (n >= 0) { e.preventDefault(); var nt = tabs[(n + tabs.length) % tabs.length]; showPane(nt.getAttribute('data-ws-tab')); nt.focus(); }
        });
    });
    // Un champ invalide dans un volet masqué : on l'ouvre.
    dlg.addEventListener('invalid', function (e) {
        var p = e.target.closest ? e.target.closest('[data-ws-pane]') : null;
        if (p && p.hidden) { showPane(p.getAttribute('data-ws-pane')); }
    }, true);

    function fill(v) {
        $$('[data-f]', dlg).forEach(function (f) {
            var k = f.getAttribute('data-f');
            var val = v ? v[k] : undefined;
            if (f.type === 'checkbox') {
                f.checked = Array.isArray(val) ? val.indexOf(f.value) >= 0 : (k === 'enabled' ? (v ? val !== false && val !== '' && val !== 0 : true) : !!val);
            } else if (f.type === 'radio') {
                f.checked = val !== undefined ? String(val) === f.value : false;
            } else if (v) {
                f.value = val === undefined || val === null ? '' : String(val);
            } else {
                f.value = f.tagName === 'SELECT' ? f.options[0].value : '';
            }
        });
        if (!v) {
            // Valeurs de départ d'un nouveau service.
            var set = function (k, val) { var f = $('[data-f="' + k + '"]', dlg); if (f) { f.value = val; } };
            set('max', 'encadrement'); set('class', 'encadrement'); set('thr', 'medium'); set('exp', '30');
            $$('[data-f="roles"]', dlg).forEach(function (c) { c.checked = c.value === 'request'; });
            $$('[data-f="lang"]', dlg).forEach(function (c) { c.checked = true; });
            $$('[data-f="threats"]', dlg).forEach(function (c) { c.checked = true; });
            var color = $('[data-f="color"]', dlg); if (color) { color.checked = true; }
            var emb = $('[data-f="emblem"]', dlg); if (emb) { emb.checked = true; }
        }
        ['color', 'emblem'].forEach(function (k) {
            var any = $$('[data-f="' + k + '"]', dlg).some(function (r) { return r.checked; });
            if (!any) { var first = $('[data-f="' + k + '"]', dlg); if (first) { first.checked = true; } }
        });
    }
    function mode(kind, v) {
        opInput.value = kind === 'edit' ? 'update' : 'create';
        codeInput.value = kind === 'edit' && v ? v.code : '';
        if (title) { title.textContent = kind === 'edit' ? T.update : T.create; }
        if (save) { save.textContent = kind === 'edit' ? (save.getAttribute('data-label-update') || '') : (save.getAttribute('data-label-create') || ''); }
        if (del) { del.hidden = kind !== 'edit'; }
        fill(kind === 'edit' ? v : null);
        showPane('id');
    }
    $$('[data-ws-new]', admin).forEach(function (b) { b.addEventListener('click', function () { mode('new', null); }); });
    $$('[data-ws-edit]', admin).forEach(function (b) {
        b.addEventListener('click', function () {
            var row = b.closest('[data-ws-row]');
            var v = null;
            try { v = JSON.parse(row.getAttribute('data-ws-json') || 'null'); } catch (e) { v = null; }
            if (v) { mode('edit', v); }
        });
    });
    // Retour du serveur avec des erreurs : on remet la saisie dans le tiroir.
    var old = null;
    try { old = JSON.parse(dlg.getAttribute('data-ws-old') || 'null'); } catch (e) { old = null; }
    if (old && old.old && (old.op === 'create' || old.op === 'update')) {
        mode(old.op === 'update' ? 'edit' : 'new', old.op === 'update' ? Object.assign({ code: old.code }, old.old) : null);
        if (old.op === 'create') { fill(old.old); opInput.value = 'create'; }
    } else {
        showPane('id');
    }
    filter();
})();
