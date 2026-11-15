/**
 * Bureau SSE — widget « Liaisons » : tiroir (chemin complet, liens directs), copie du chemin, citation JNET, recherche et
 * ajout d'un lien. Tout le contenu est rendu par le serveur avec les droits du lecteur ; ce script ne décide d'aucun droit.
 * Sans lui, la page reste lisible (le tiroir est un <dialog> fermé) ; le formulaire d'ajout est un formulaire ordinaire.
 * Aucun texte français ici : les messages viennent de la page (éléments [data-sl-msg]).
 */
(function () {
    'use strict';

    var hasDialog = typeof HTMLDialogElement === 'function' && typeof document.createElement('dialog').showModal === 'function';

    var el = function (tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text) { n.textContent = text; }
        return n;
    };

    var openDialog = function (d) {
        if (!d || d.open) { return; }
        if (hasDialog) { d.showModal(); } else { d.setAttribute('open', ''); }
        document.documentElement.classList.add('sl-noscroll');
    };
    var closeDialog = function (d) {
        if (!d) { return; }
        if (hasDialog && d.open) { d.close(); } else { d.removeAttribute('open'); }
        if (!document.querySelector('dialog.sl-drawer[open], dialog.sl-modal[open]')) {
            document.documentElement.classList.remove('sl-noscroll');
        }
    };

    var init = function (root) {
        if (root.getAttribute('data-sl-ready') === '1') { return; }
        root.setAttribute('data-sl-ready', '1');
        var drawer = root.querySelector('[data-sl-drawer]');
        var modal = root.querySelector('[data-sl-modal]');
        var opener = root.querySelector('[data-sl-open]');
        var lastFocus = null;

        var msg = function (key) {
            var n = root.querySelector('[data-sl-msg="' + key + '"]');

            return n ? n.textContent : '';
        };
        var toast = function (text, bad) {
            if (!text) { return; }
            var host = (modal && modal.open ? modal : (drawer && drawer.open ? drawer : root));
            var t = el('div', 'sl-toast' + (bad ? ' is-bad' : ''), text);
            t.setAttribute('role', 'status');
            host.appendChild(t);
            window.setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t); } }, 3600);
        };

        // —— Tiroir ——
        var openDrawer = function () {
            lastFocus = document.activeElement;
            openDialog(drawer);
            var title = drawer.querySelector('[data-sl-title]');
            if (title) { title.focus({ preventScroll: true }); }
        };
        var closeDrawer = function () {
            closeDialog(drawer);
            if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
        };
        if (opener && drawer) { opener.addEventListener('click', openDrawer); }
        [].forEach.call(root.querySelectorAll('[data-sl-close]'), function (b) { b.addEventListener('click', closeDrawer); });
        if (drawer) {
            // Clic sur le fond (hors du panneau) : ferme.
            drawer.addEventListener('click', function (e) { if (e.target === drawer) { closeDrawer(); } });
            drawer.addEventListener('close', function () {
                if (!document.querySelector('dialog.sl-drawer[open], dialog.sl-modal[open]')) { document.documentElement.classList.remove('sl-noscroll'); }
            });
            // Sans <dialog> natif : Échap ferme aussi.
            if (!hasDialog) {
                drawer.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeDrawer(); } });
            }
        }
        if (window.location.hash === '#liaisons' && drawer) { openDrawer(); }

        // —— Onglets (flèches, Début, Fin) ——
        var tabs = [].slice.call(root.querySelectorAll('[role="tab"][data-sl-tab]'));
        var select = function (tab, focus) {
            tabs.forEach(function (t) {
                var on = t === tab;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.setAttribute('tabindex', on ? '0' : '-1');
                var p = root.querySelector('[data-sl-panel="' + t.getAttribute('data-sl-tab') + '"]');
                if (p) { p.hidden = !on; }
            });
            if (focus) { tab.focus(); }
        };
        tabs.forEach(function (t, i) {
            t.addEventListener('click', function () { select(t, false); });
            t.addEventListener('keydown', function (e) {
                var k = e.key;
                var to = null;
                if (k === 'ArrowRight' || k === 'ArrowDown') { to = tabs[(i + 1) % tabs.length]; }
                else if (k === 'ArrowLeft' || k === 'ArrowUp') { to = tabs[(i - 1 + tabs.length) % tabs.length]; }
                else if (k === 'Home') { to = tabs[0]; }
                else if (k === 'End') { to = tabs[tabs.length - 1]; }
                if (to) { e.preventDefault(); select(to, true); }
            });
        });

        // —— Copie : chemin en texte, lien, citation JNET ——
        var copy = function (text, okKey) {
            var done = function () { toast(msg(okKey)); };
            var fail = function () { toast(msg('copy-failed'), true); };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done, function () { legacy(text) ? done() : fail(); });
            } else {
                legacy(text) ? done() : fail();
            }
        };
        var legacy = function (text) {
            var ta = el('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            (drawer && drawer.open ? drawer : document.body).appendChild(ta);
            ta.select();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            ta.parentNode.removeChild(ta);

            return ok;
        };
        [].forEach.call(root.querySelectorAll('[data-sl-copy]'), function (b) {
            b.addEventListener('click', function () {
                var what = b.getAttribute('data-sl-copy');
                if (what === 'text') { copy(root.getAttribute('data-sl-text') || '', 'copied'); }
                else if (what === 'url') { copy(new URL(root.getAttribute('data-sl-url') || '', window.location.href).href + '#liaisons', 'copied'); }
                else if (what === 'cite') { copy(root.getAttribute('data-sl-cite') || '', 'copied-cite'); }
            });
        });

        // —— Retrait d'un lien manuel : confirmation ——
        [].forEach.call(root.querySelectorAll('form[data-confirm]'), function (f) {
            f.addEventListener('submit', function (e) {
                if (!window.confirm(f.getAttribute('data-confirm') || '')) { e.preventDefault(); }
            });
        });

        // —— Ajouter un lien : fenêtre, recherche, choix ——
        if (!modal) { return; }
        var form = modal.querySelector('[data-sl-form]');
        var q = modal.querySelector('[data-sl-q]');
        var typeSel = modal.querySelector('[data-sl-type-filter]');
        var results = modal.querySelector('[data-sl-results]');
        var hint = modal.querySelector('[data-sl-hint]');
        var to = modal.querySelector('[data-sl-to]');
        var url = root.getAttribute('data-sl-search') || '';
        var seq = 0;
        var timer = null;
        var openers = root.querySelectorAll('[data-sl-add-open]');
        [].forEach.call(openers, function (b) {
            b.addEventListener('click', function () { openDialog(modal); if (q) { q.focus(); } });
        });
        [].forEach.call(modal.querySelectorAll('[data-sl-modal-close]'), function (b) { b.addEventListener('click', function () { closeDialog(modal); }); });
        modal.addEventListener('click', function (e) { if (e.target === modal) { closeDialog(modal); } });
        if (!hasDialog) {
            modal.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.stopPropagation(); closeDialog(modal); } });
        }

        var showResults = function (items) {
            results.textContent = '';
            if (!items.length) {
                results.hidden = true;
                if (hint) { hint.textContent = msg('no-result'); }
                return;
            }
            results.hidden = false;
            items.forEach(function (it, i) {
                var li = el('li');
                li.setAttribute('role', 'option');
                li.id = modal.id + '-r' + i;
                var b = el('button', 'sl-result');
                b.type = 'button';
                var t = el('span', 'sl-result__t');
                t.appendChild(el('small', '', it.type_label));
                t.appendChild(el('b', '', it.ref !== '' ? it.ref : it.title));
                if (it.title && it.ref !== '') { t.appendChild(el('span', '', it.title)); }
                b.appendChild(t);
                b.addEventListener('click', function () {
                    to.value = it.key;
                    [].forEach.call(results.querySelectorAll('.is-picked'), function (n) { n.classList.remove('is-picked'); });
                    b.classList.add('is-picked');
                    if (hint) { hint.textContent = it.type_label + ' · ' + (it.ref !== '' ? it.ref : it.title); }
                    var reason = form.querySelector('textarea[name="reason"]');
                    if (reason) { reason.focus(); }
                });
                li.appendChild(b);
                results.appendChild(li);
            });
        };
        var run = function () {
            var text = q.value.trim();
            if (text.length < 2) { results.hidden = true; results.textContent = ''; return; }
            var mine = ++seq;
            if (hint) { hint.textContent = msg('searching'); }
            fetch(url + '?q=' + encodeURIComponent(text) + '&types=' + encodeURIComponent(typeSel ? typeSel.value : ''), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : { items: [] }; })
                .then(function (d) { if (mine === seq) { showResults(Array.isArray(d.items) ? d.items : []); } })
                .catch(function () { if (mine === seq) { showResults([]); } });
        };
        if (q) { q.addEventListener('input', function () { window.clearTimeout(timer); timer = window.setTimeout(run, 220); }); }
        if (typeSel) { typeSel.addEventListener('change', run); }
    };

    var boot = function () { [].forEach.call(document.querySelectorAll('[data-sl]'), init); };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
}());
