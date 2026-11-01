/**
 * Recherche de l'espace « Mon compte » : filtrage instantané de l'index rendu par
 * views/partials/account/search.php, navigation au clavier, raccourcis « / » et Ctrl+K.
 * Arrivée sur une ancre (#…) : le réglage visé est mis en évidence un court instant.
 */
(function () {
    'use strict';

    function fold(s) {
        var out = '';
        var str = String(s || '').toLowerCase();
        for (var i = 0; i < str.length; i++) {
            out += foldChar(str.charAt(i));
        }
        return out.replace(/\s+/g, ' ').trim();
    }

    /** Un caractère → un caractère (sauf œ/æ), pour garder les positions lors du surlignage. */
    function foldChar(c) {
        if (c === '’' || c === '\'') return ' ';
        if (c === 'œ') return 'oe';
        if (c === 'æ') return 'ae';
        var n = c.normalize ? c.normalize('NFD').replace(/[̀-ͯ]/g, '') : c;
        return n.length === 1 ? n : c;
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' }[c];
        });
    }

    function isTypingTarget(el) {
        if (!el) return false;
        var tag = (el.tagName || '').toLowerCase();
        return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
    }

    /* ——— Mise en évidence de la cible d'une ancre ——— */
    function locateHash() {
        var id = decodeURIComponent((window.location.hash || '').slice(1));
        if (!id) return;
        var el = document.getElementById(id);
        if (!el || !el.closest('.account-hub')) return;
        var box = el.closest('[data-locate-box]') || el.closest('.account-hub__panel') || el;
        box.classList.remove('is-located');
        void box.offsetWidth;
        box.classList.add('is-located');
        window.setTimeout(function () { box.classList.remove('is-located'); }, 2600);
        var tag = (el.tagName || '').toLowerCase();
        if ((tag === 'input' || tag === 'select' || tag === 'textarea') && el.type !== 'hidden' && el.type !== 'file') {
            try { el.focus({ preventScroll: true }); } catch (e) { el.focus(); }
        }
    }
    window.addEventListener('hashchange', locateHash);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', locateHash);
    } else {
        locateHash();
    }

    var root = document.querySelector('[data-account-search]');
    if (!root) return;
    var input = root.querySelector('#account-search-input');
    var panel = root.querySelector('#account-search-panel');
    var list = root.querySelector('#account-search-results');
    var status = root.querySelector('#account-search-status');
    var empty = root.querySelector('.ah-search__empty');
    if (!input || !panel || !list) return;

    var groups = Array.prototype.slice.call(list.querySelectorAll('[data-search-group]'));
    var items = Array.prototype.slice.call(list.querySelectorAll('[data-search-item]')).map(function (li, index) {
        var titleEl = li.querySelector('[data-search-title]');
        var desc = li.querySelector('.ah-search__item-desc');
        var visibleTitle = titleEl ? titleEl.textContent : '';
        return {
            li: li,
            index: index,
            kind: li.getAttribute('data-kind'),
            group: li.getAttribute('data-group'),
            titleEl: titleEl,
            titleText: visibleTitle,
            title: fold(visibleTitle + ' ' + (li.getAttribute('data-title') || '')),
            keywords: fold(li.getAttribute('data-keywords') || ''),
            blob: fold((li.getAttribute('data-blob') || '') + ' ' + visibleTitle + ' ' + (desc ? desc.textContent : '')),
            href: (li.querySelector('a') || {}).href || ''
        };
    });
    var visible = [];
    var active = -1;

    function wordStarts(hay, token) {
        return hay.indexOf(token) === 0 || hay.indexOf(' ' + token) !== -1;
    }

    function score(item, tokens, q) {
        var s = 0;
        for (var i = 0; i < tokens.length; i++) {
            var t = tokens[i];
            if (item.blob.indexOf(t) === -1) return -1;
            if (wordStarts(item.title, t)) s += 40;
            else if (item.title.indexOf(t) !== -1) s += 18;
            if (wordStarts(item.keywords, t)) s += 14;
            else if (wordStarts(item.blob, t)) s += 6;
        }
        if (item.title.indexOf(q) === 0) s += 60;
        if (item.kind === 'section') s += 4;
        return s;
    }

    function highlight(item, tokens) {
        if (!item.titleEl) return;
        var text = item.titleText;
        if (!tokens.length) {
            item.titleEl.textContent = text;
            return;
        }
        var folded = '';
        var map = [];
        for (var i = 0; i < text.length; i++) {
            var f = foldChar(text.charAt(i).toLowerCase());
            for (var k = 0; k < f.length; k++) { folded += f.charAt(k); map.push(i); }
        }
        var marks = new Array(text.length);
        tokens.forEach(function (t) {
            var from = 0, at;
            while ((at = folded.indexOf(t, from)) !== -1) {
                for (var j = at; j < at + t.length && j < map.length; j++) marks[map[j]] = true;
                from = at + t.length;
            }
        });
        var html = '', open = false;
        for (var c = 0; c < text.length; c++) {
            if (marks[c] && !open) { html += '<mark>'; open = true; }
            if (!marks[c] && open) { html += '</mark>'; open = false; }
            html += escapeHtml(text.charAt(c));
        }
        if (open) html += '</mark>';
        item.titleEl.innerHTML = html;
    }

    function setActive(i) {
        visible.forEach(function (it, n) {
            var on = n === i;
            it.li.classList.toggle('is-active', on);
            it.li.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        active = i;
        if (i >= 0 && visible[i]) {
            input.setAttribute('aria-activedescendant', visible[i].li.id);
            visible[i].li.scrollIntoView({ block: 'nearest' });
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    }

    function render() {
        var q = fold(input.value);
        var tokens = q === '' ? [] : q.split(' ').filter(Boolean);
        if (!tokens.length) {
            items.forEach(function (it) {
                it.li.hidden = it.kind !== 'page';
                highlight(it, []);
            });
            // Ordre d'origine : chaque groupe suivi de ses pages.
            groups.forEach(function (g) {
                g.hidden = false;
                list.appendChild(g);
                items.forEach(function (it) {
                    if (it.group === g.getAttribute('data-search-group')) list.appendChild(it.li);
                });
            });
            visible = items.filter(function (it) { return !it.li.hidden; });
            if (status) status.textContent = '';
            if (empty) empty.hidden = true;
            setActive(-1);
            return;
        }
        var ranked = [];
        items.forEach(function (it) {
            var s = score(it, tokens, q);
            it.li.hidden = s < 0;
            if (s >= 0) ranked.push({ it: it, s: s });
        });
        ranked.sort(function (a, b) { return b.s - a.s || a.it.index - b.it.index; });
        groups.forEach(function (g) { g.hidden = true; });
        visible = ranked.slice(0, 12).map(function (r) { return r.it; });
        ranked.slice(12).forEach(function (r) { r.it.li.hidden = true; });
        visible.forEach(function (it) {
            highlight(it, tokens);
            list.appendChild(it.li);
        });
        if (empty) empty.hidden = visible.length > 0;
        if (status) {
            status.textContent = visible.length === 0
                ? 'Aucun résultat'
                : visible.length + (visible.length > 1 ? ' résultats' : ' résultat');
        }
        setActive(visible.length ? 0 : -1);
    }

    function open() {
        if (!panel.hidden) return;
        panel.hidden = false;
        root.classList.add('is-open');
        input.setAttribute('aria-expanded', 'true');
        render();
    }

    function close() {
        panel.hidden = true;
        root.classList.remove('is-open');
        input.setAttribute('aria-expanded', 'false');
        setActive(-1);
    }

    function go(item) {
        if (!item || !item.href) return;
        var url;
        try { url = new URL(item.href, window.location.href); } catch (e) { window.location.href = item.href; return; }
        close();
        input.blur();
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
            if (window.location.hash === url.hash) {
                locateHash();
                var target = document.getElementById(decodeURIComponent(url.hash.slice(1)));
                if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                window.location.hash = url.hash;
            }
            return;
        }
        window.location.href = url.href;
    }

    input.addEventListener('focus', open);
    input.addEventListener('input', function () { open(); render(); });
    input.addEventListener('keydown', function (ev) {
        if (ev.key === 'ArrowDown') {
            ev.preventDefault();
            open();
            if (visible.length) setActive(active < visible.length - 1 ? active + 1 : 0);
        } else if (ev.key === 'ArrowUp') {
            ev.preventDefault();
            if (visible.length) setActive(active > 0 ? active - 1 : visible.length - 1);
        } else if (ev.key === 'Enter') {
            ev.preventDefault();
            go(visible[active >= 0 ? active : 0]);
        } else if (ev.key === 'Escape') {
            ev.preventDefault();
            if (input.value !== '') {
                input.value = '';
                render();
            } else {
                close();
                input.blur();
            }
        } else if (ev.key === 'Tab') {
            close();
        }
    });

    list.addEventListener('mousemove', function (ev) {
        var li = ev.target.closest('[data-search-item]');
        if (!li) return;
        var i = visible.findIndex(function (it) { return it.li === li; });
        if (i !== -1 && i !== active) setActive(i);
    });
    list.addEventListener('click', function (ev) {
        var li = ev.target.closest('[data-search-item]');
        if (!li) return;
        var item = items.find(function (it) { return it.li === li; });
        if (item && !(ev.ctrlKey || ev.metaKey || ev.shiftKey)) {
            ev.preventDefault();
            go(item);
        }
    });

    document.addEventListener('mousedown', function (ev) {
        if (!root.contains(ev.target)) close();
    });

    document.addEventListener('keydown', function (ev) {
        var k = (ev.key || '').toLowerCase();
        if ((ev.ctrlKey || ev.metaKey) && k === 'k') {
            ev.preventDefault();
            input.focus();
            input.select();
            return;
        }
        if (ev.key === '/' && !ev.ctrlKey && !ev.metaKey && !ev.altKey && !isTypingTarget(document.activeElement)) {
            ev.preventDefault();
            input.focus();
        }
    });

    var keys = root.querySelector('.ah-search__keys');
    if (keys && /Mac|iPhone|iPad/.test(navigator.platform || '')) {
        var hint = root.querySelector('.ah-search__help-shortcut');
        if (hint) hint.innerHTML = hint.innerHTML.replace('<kbd>Ctrl</kbd>', '<kbd>⌘</kbd>');
    }
})();
