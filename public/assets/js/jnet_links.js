/**
 * JNET — liens entre contenus : fenêtre « Lier à… » avec recherche, et retrait d'un lien sans recharger la page.
 * Tout reste utilisable sans ce script : « Lier à… » est un lien vers une page de recherche (jnet/liens/nouveau) et chaque
 * lien se retire par un bouton de formulaire. Le serveur contrôle tout (droits de l'auteur sur la source et la cible,
 * jeton CSRF) ; ce script ne décide d'aucun droit. Expose window.JnetLinks.search pour la saisie « @ » des pages.
 */
(function () {
    'use strict';

    var SVG_NS = 'http://www.w3.org/2000/svg';
    var hasDialog = typeof HTMLDialogElement === 'function' && typeof document.createElement('dialog').showModal === 'function';
    var TYPE_LABELS = { echange: 'Échange', page: 'Page', tache: 'Tâche', operation: 'Opération', cible: 'Cible', document: 'Document', unite: 'Unité', membre: 'Membre' };
    var GROUP_LABELS = { echange: 'Ordres et échanges', page: 'Pages', tache: 'Tâches', operation: 'Opérations', cible: 'Cibles', document: 'Documents', unite: 'Unités', membre: 'Membres' };

    var el = function (tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text) { n.textContent = text; }
        return n;
    };

    // Message discret : le coin de messages de l'espace d'une unité s'il existe, sinon un coin propre à la page.
    var say = function (text, bad) {
        if (!text) { return; }
        var host = document.querySelector('[data-uw-toasts]');
        var cls = host ? 'uw-toast' : 'jl-toast';
        if (!host) {
            host = document.querySelector('.jl-toasts');
            if (!host) {
                host = el('div', 'jl-toasts');
                host.setAttribute('role', 'status');
                host.setAttribute('aria-live', 'polite');
                document.body.appendChild(host);
            }
        }
        var t = el('div', cls + (bad ? ' is-bad' : ''), text);
        host.appendChild(t);
        window.setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t); } }, bad ? 5200 : 3200);
    };

    var post = function (url, fields, csrf) {
        var fd = new FormData();
        fd.append('_csrf_token', csrf || '');
        Object.keys(fields || {}).forEach(function (k) { fd.append(k, fields[k]); });

        return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Réponse inattendue du serveur.' }; }); })
            .catch(function () { return { ok: false, message: 'Connexion impossible. Réessayez.' }; });
    };

    var search = function (url, q, types, space) {
        var qs = '?q=' + encodeURIComponent(q) + '&types=' + encodeURIComponent(types || '') + '&space=' + encodeURIComponent(space || 0);

        return fetch(url + qs, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : { results: [] }; })
            .then(function (d) { return Array.isArray(d.results) ? d.results : []; })
            .catch(function () { return []; });
    };
    window.JnetLinks = { search: search, labels: TYPE_LABELS };

    // —— Retrait d'un lien, sans recharger ——
    var csrfOf = function (form) {
        var i = form.querySelector('input[name="_csrf_token"]');

        return i ? i.value : '';
    };
    document.addEventListener('submit', function (e) {
        var form = e.target && e.target.closest ? e.target.closest('form[data-jl-remove]') : null;
        if (!form) { return; }
        e.preventDefault();
        var btn = form.querySelector('button');
        if (btn) { btn.disabled = true; }
        post(form.action, {}, csrfOf(form)).then(function (res) {
            if (!res.ok) {
                if (btn) { btn.disabled = false; }
                say(res.message || 'Le lien n’a pas pu être retiré.', true);

                return;
            }
            var item = form.closest('[data-jl-item]');
            var block = form.closest('[data-jl-block]');
            if (item && item.parentNode) { item.parentNode.removeChild(item); }
            refreshEmpty(block);
            say(res.message || 'Lien retiré.');
        });
    });

    // Un bloc sans lien affiche sa phrase « Rien n'est lié » quand elle existe ; sinon il n'a plus de liste.
    var refreshEmpty = function (block) {
        if (!block) { return; }
        var list = block.querySelector('[data-jl-list]');
        var empty = block.querySelector('[data-jl-empty]');
        var left = list ? list.querySelectorAll('[data-jl-item]').length : 0;
        if (empty) { empty.hidden = left > 0; }
        if (list) { list.hidden = left === 0; }
    };

    // —— Ajout d'un lien dans le bloc, avec ce que le serveur vient d'accepter ——
    var addToBlock = function (block, r, id, csrf, removeBase) {
        if (!block) { return false; }
        var list = block.querySelector('[data-jl-list]');
        var inline = block.classList.contains('jl--inline');
        if (!list && inline) {
            var lead = el('span', 'jl__lead', 'Liés à');
            list = el('ul', 'jl-chips');
            list.setAttribute('data-jl-list', '');
            var add = block.querySelector('[data-jl-add]');
            block.insertBefore(lead, add);
            block.insertBefore(list, add);
        }
        if (!list) { return false; }
        var li = el('li', inline ? 'jl-chip' : 'jl-row');
        li.setAttribute('data-jl-item', '');
        var a = el('a', inline ? 'jl-chip__t' : 'jl-row__a');
        a.href = r.href;
        if (inline) {
            a.title = r.typeLabel;
            a.appendChild(el('span', 'jl-chip__k', r.typeLabel));
            a.appendChild(document.createTextNode(r.title));
        } else {
            a.appendChild(el('span', 'jl-row__k', r.typeLabel));
            var t = el('span', 'jl-row__t');
            t.appendChild(el('b', '', r.title));
            if (r.sub) { t.appendChild(el('small', '', r.sub)); }
            a.appendChild(t);
        }
        li.appendChild(a);
        if (id && removeBase) {
            var f = el('form', 'jl-rm');
            f.method = 'post';
            f.action = removeBase.replace(/\/$/, '') + '/' + id + '/supprimer';
            f.setAttribute('data-jl-remove', '');
            var tok = document.createElement('input');
            tok.type = 'hidden';
            tok.name = '_csrf_token';
            tok.value = csrf;
            f.appendChild(tok);
            var b = el('button', 'jl-rm__b');
            b.type = 'submit';
            b.title = 'Retirer ce lien';
            b.setAttribute('aria-label', 'Retirer ce lien');
            var svg = document.createElementNS(SVG_NS, 'svg');
            svg.setAttribute('class', 'ja-i');
            svg.setAttribute('viewBox', '0 0 24 24');
            svg.setAttribute('aria-hidden', 'true');
            ['M6 6l12 12', 'M18 6 6 18'].forEach(function (d) {
                var p = document.createElementNS(SVG_NS, 'path');
                p.setAttribute('d', d);
                svg.appendChild(p);
            });
            b.appendChild(svg);
            f.appendChild(b);
            li.appendChild(f);
        }
        list.appendChild(li);
        list.hidden = false;
        refreshEmpty(block);

        return true;
    };

    // —— Fenêtre « Lier à… » ——
    var open = function (opener) {
        var ref = opener.getAttribute('data-jl-ref') || '';
        var space = opener.getAttribute('data-jl-space') || '0';
        var allTypes = (opener.getAttribute('data-jl-types') || '').split(',').filter(Boolean);
        var searchUrl = opener.getAttribute('data-jl-search') || '';
        var createUrl = opener.getAttribute('data-jl-create') || '';
        var csrf = opener.getAttribute('data-jl-csrf') || '';
        var removeBase = createUrl;
        var block = opener.closest('[data-jl-block]');
        var current = allTypes.slice();
        var seq = 0;
        var timer = null;
        var shown = [];
        var active = 0;

        var dlg = el('dialog', 'jl-modal');
        dlg.setAttribute('aria-labelledby', 'jl-modal-h');
        var box = el('div', 'jl-modal__box');
        var head = el('div', 'jl-modal__head');
        var h = el('h2', '', 'Lier à…');
        h.id = 'jl-modal-h';
        head.appendChild(h);
        var close = el('button', 'jl-modal__close', 'Fermer');
        close.type = 'button';
        close.addEventListener('click', function () { dlg.close(); });
        head.appendChild(close);
        box.appendChild(head);

        var q = document.createElement('input');
        q.type = 'search';
        q.className = 'jl-modal__q';
        q.autocomplete = 'off';
        q.maxLength = 80;
        q.placeholder = 'Chercher un ordre, une page, une tâche, une opération, une unité…';
        q.setAttribute('role', 'combobox');
        q.setAttribute('aria-expanded', 'true');
        q.setAttribute('aria-controls', 'jl-modal-list');
        q.setAttribute('aria-label', 'Chercher ce qu’il faut lier');
        box.appendChild(q);

        var chips = el('div', 'jl-modal__types');
        chips.setAttribute('role', 'group');
        chips.setAttribute('aria-label', 'Filtrer par type');
        var chipBtns = [];
        var mkChip = function (label, types) {
            var b = el('button', 'ja-chip' + (types === null ? ' is-on' : ''), label);
            b.type = 'button';
            b.setAttribute('aria-pressed', types === null ? 'true' : 'false');
            b.addEventListener('click', function () {
                chipBtns.forEach(function (o) { var on = o === b; o.classList.toggle('is-on', on); o.setAttribute('aria-pressed', on ? 'true' : 'false'); });
                current = types === null ? allTypes.slice() : types;
                run();
                q.focus();
            });
            chipBtns.push(b);
            chips.appendChild(b);
        };
        if (allTypes.length > 1) {
            mkChip('Tout', null);
            allTypes.forEach(function (t) { mkChip(GROUP_LABELS[t] || t, [t]); });
            box.appendChild(chips);
        }

        var ul = el('ul', 'jl-modal__list');
        ul.id = 'jl-modal-list';
        ul.setAttribute('role', 'listbox');
        ul.setAttribute('aria-label', 'Résultats');
        box.appendChild(ul);
        var msg = el('p', 'jl-modal__msg');
        msg.setAttribute('role', 'status');
        msg.setAttribute('aria-live', 'polite');
        box.appendChild(msg);
        dlg.appendChild(box);

        var mark = function (i) {
            if (!shown.length) { return; }
            active = (i + shown.length) % shown.length;
            shown.forEach(function (s, k) { s.li.setAttribute('aria-selected', k === active ? 'true' : 'false'); });
            q.setAttribute('aria-activedescendant', shown[active].li.id);
            shown[active].li.scrollIntoView({ block: 'nearest' });
        };
        var none = function (text) {
            ul.textContent = '';
            shown = [];
            var li = el('li', 'is-none', text);
            ul.appendChild(li);
        };
        var render = function (rows) {
            ul.textContent = '';
            shown = [];
            rows = rows.filter(function (r) { return r.ref !== ref; });
            if (!rows.length) { none('Aucun résultat parmi ce que vous pouvez voir.'); return; }
            rows.forEach(function (r, k) {
                var li = el('li');
                li.id = 'jl-opt-' + k;
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', 'false');
                li.appendChild(el('span', 'jl-row__k', r.typeLabel));
                var t = el('span', 'jl-row__t');
                t.appendChild(el('b', '', r.title));
                if (r.sub) { t.appendChild(el('small', '', r.sub)); }
                li.appendChild(t);
                li.addEventListener('mousemove', function () { mark(k); });
                li.addEventListener('mousedown', function (e) { e.preventDefault(); });
                li.addEventListener('click', function () { choose(r); });
                ul.appendChild(li);
                shown.push({ li: li, r: r });
            });
            mark(0);
        };
        var run = function () {
            var text = q.value.trim();
            msg.textContent = '';
            msg.className = 'jl-modal__msg';
            if (text.length < 2) { none('Tapez au moins deux lettres.'); return; }
            var mine = ++seq;
            search(searchUrl, text, current.join(','), space).then(function (rows) { if (mine === seq) { render(rows); } });
        };
        var busy = false;
        var choose = function (r) {
            if (busy) { return; }
            busy = true;
            msg.className = 'jl-modal__msg';
            msg.textContent = 'Enregistrement…';
            post(createUrl, { src: ref, dst: r.ref, space: space }, csrf).then(function (res) {
                busy = false;
                if (!res.ok) {
                    msg.className = 'jl-modal__msg is-bad';
                    msg.textContent = res.message || 'Le lien n’a pas pu être posé.';

                    return;
                }
                say(res.message || 'Lien ajouté.');
                dlg.close();
                // La vue d'ensemble d'une opération range les liens par rubrique : le serveur la recompose.
                if ((block && block.classList.contains('jl--overview')) || !addToBlock(block, r, res.id, csrf, removeBase)) { window.location.reload(); }
            });
        };
        q.addEventListener('input', function () { window.clearTimeout(timer); timer = window.setTimeout(run, 180); });
        q.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); mark(active + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); mark(active - 1); }
            else if (e.key === 'Enter') { e.preventDefault(); if (shown[active]) { choose(shown[active].r); } }
        });
        dlg.addEventListener('close', function () {
            if (dlg.parentNode) { dlg.parentNode.removeChild(dlg); }
            if (opener && typeof opener.focus === 'function') { opener.focus(); }
        });
        dlg.addEventListener('click', function (e) { if (e.target === dlg) { dlg.close(); } });
        document.body.appendChild(dlg);
        none('Tapez au moins deux lettres.');
        dlg.showModal();
        q.focus();
    };

    if (hasDialog) {
        document.addEventListener('click', function (e) {
            var a = e.target && e.target.closest ? e.target.closest('[data-jl-add]') : null;
            if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) { return; }
            e.preventDefault();
            open(a);
        });
    }
}());
