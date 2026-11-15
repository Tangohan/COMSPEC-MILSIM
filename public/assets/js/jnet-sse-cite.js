/**
 * JNET — « Citer un élément SSE » dans le détail d'un échange (compte rendu) : recherche par référence ou par nom parmi ce que le
 * rédacteur a le droit de lire côté SSE, puis insertion d'une citation « [[sse:person-12|IDN-00012]] » au curseur. La citation
 * ne garde que la référence : le serveur résout le titre et le chemin de liaison à la lecture, avec les droits de CHAQUE lecteur.
 * Aucun texte français ici : les messages viennent de la page (éléments [data-jn-cite-msg]).
 */
(function () {
    'use strict';

    var el = function (tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text) { n.textContent = text; }
        return n;
    };

    var init = function (box) {
        if (box.getAttribute('data-jn-cite-ready') === '1') { return; }
        box.setAttribute('data-jn-cite-ready', '1');
        var open = box.querySelector('[data-jn-cite-open]');
        var pop = box.querySelector('.jn-cite__pop');
        var q = box.querySelector('[data-jn-cite-q]');
        var list = box.querySelector('[data-jn-cite-list]');
        var target = document.getElementById(box.getAttribute('data-jn-cite-target') || '');
        var url = box.getAttribute('data-jn-cite-search') || '';
        var space = box.getAttribute('data-jn-cite-space') || '0';
        if (!open || !pop || !q || !list || !target || !url) { return; }
        var seq = 0;
        var timer = null;
        var msg = function (k) {
            var n = box.querySelector('[data-jn-cite-msg="' + k + '"]');

            return n ? n.textContent : '';
        };
        var note = function (text) {
            list.textContent = '';
            if (text) { list.appendChild(el('li', '', text)); }
        };
        open.addEventListener('click', function () {
            var show = pop.hidden;
            pop.hidden = !show;
            open.setAttribute('aria-expanded', show ? 'true' : 'false');
            if (show) { q.focus(); note(msg('short')); }
        });
        pop.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { e.preventDefault(); pop.hidden = true; open.setAttribute('aria-expanded', 'false'); open.focus(); }
        });
        var insert = function (token) {
            var start = typeof target.selectionStart === 'number' ? target.selectionStart : target.value.length;
            var end = typeof target.selectionEnd === 'number' ? target.selectionEnd : start;
            var max = parseInt(target.getAttribute('maxlength') || '0', 10);
            if (max > 0 && target.value.length - (end - start) + token.length > max) { return false; }
            target.value = target.value.slice(0, start) + token + target.value.slice(end);
            target.focus();
            target.selectionStart = target.selectionEnd = start + token.length;
            target.dispatchEvent(new Event('input', { bubbles: true }));

            return true;
        };
        var render = function (rows) {
            list.textContent = '';
            if (!rows.length) { note(msg('none')); return; }
            rows.forEach(function (r) {
                var li = el('li');
                var b = el('button');
                b.type = 'button';
                b.appendChild(el('b', '', r.title));
                b.appendChild(el('small', '', r.sub || r.typeLabel));
                b.addEventListener('click', function () {
                    // « sse:person-12 » -> « [[sse:person-12|IDN-00012]] » : le libellé gardé est la référence, jamais un nom.
                    var token = '[[' + String(r.ref) + '|' + String(r.title).replace(/[\[\]|\r\n]+/g, ' ').trim().slice(0, 80) + ']] ';
                    if (insert(token)) { pop.hidden = true; open.setAttribute('aria-expanded', 'false'); }
                });
                li.appendChild(b);
                list.appendChild(li);
            });
        };
        q.addEventListener('input', function () {
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                var text = q.value.trim();
                if (text.length < 2) { note(msg('short')); return; }
                var mine = ++seq;
                fetch(url + '?q=' + encodeURIComponent(text) + '&types=sse&space=' + encodeURIComponent(space), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : { results: [] }; })
                    .then(function (d) { if (mine === seq) { render(Array.isArray(d.results) ? d.results : []); } })
                    .catch(function () { if (mine === seq) { render([]); } });
            }, 220);
        });
        q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
    };

    var boot = function () { [].forEach.call(document.querySelectorAll('[data-jn-cite]'), init); };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
}());
