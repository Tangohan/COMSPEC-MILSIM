/*
 * Bureau SSE : fenêtre « Rattacher » universelle et section « Éléments rattachés ».
 *
 * Aucun texte dans ce fichier : les libellés viennent du bloc caché [data-ra-labels] et des gabarits <template> de la vue
 * (views/atak/sse/_attach_window.php), donc traduits avec la page. Le serveur décide de tout (droits, confidentialité,
 * alignement) : le script n'affiche que ce qu'il reçoit.
 *
 *   <button data-ra-open data-ra-pieces="note:12">                                  une pièce
 *   <button data-ra-open data-ra-type="person" data-ra-collect="input[name='ids[]']:checked">   un lot (valeur = identifiant)
 *   <button data-ra-open data-ra-pieces="note:12" data-ra-move="34">                déplacer le rattachement 34
 *   window.SseRattacher.open(['note:12', 'note:13'])
 */
(function () {
    'use strict';
    var root = document.querySelector('[data-ra]');
    if (!root || root.getAttribute('data-ra-on') === '1') { return; }
    root.setAttribute('data-ra-on', '1');
    // La fenêtre peut avoir été rendue au milieu de la page (dans un onglet caché, une colonne) : elle vit au niveau du document.
    if (root.parentNode !== document.body) {
        var bar = document.querySelector('[data-ra-undo-bar]');
        document.body.appendChild(root);
        if (bar) { document.body.appendChild(bar); }
    }
    var dialog = root.querySelector('.ra-dialog');
    var RECENTS_KEY = 'sse.rattacher.recents';
    var ACCENTS = ['green', 'amber', 'blue', 'violet', 'teal'];
    var STEPS = { attach: ['pick', 'settings', 'recap'], move: ['pick', 'move'] };
    var st = null;
    var seq = 0;
    var hoverTimer = null;
    var searchTimer = null;
    var undoTimer = null;

    function $(sel, ctx) { return (ctx || root).querySelector(sel); }
    function $all(sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); }
    function L(key) {
        var el = root.querySelector('[data-ra-labels] [data-k="' + key + '"]');
        var text = el ? (el.textContent || '').trim() : '';
        for (var i = 1; i < arguments.length; i++) { text = text.split('%' + i).join(String(arguments[i])); }
        return text;
    }
    function tpl(name) {
        var t = root.querySelector('template[data-ra-tpl="' + name + '"]');
        return t.content.firstElementChild.cloneNode(true);
    }
    function show(el, on) { if (el) { el.hidden = !on; } }
    function wide() { return window.matchMedia('(min-width: 1101px)').matches; }
    function url(name) { return root.getAttribute('data-ra-' + name) || ''; }

    function get(name, params) {
        var qs = Object.keys(params).filter(function (k) { return params[k] !== '' && params[k] != null && params[k] !== false; })
            .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k] === true ? '1' : params[k]); }).join('&');
        return fetch(url(name) + (qs ? '?' + qs : ''), { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(readJson);
    }
    function post(target, data) {
        var body = new FormData();
        body.append('_csrf_token', root.getAttribute('data-ra-csrf') || '');
        Object.keys(data).forEach(function (k) { body.append(k, data[k] == null ? '' : data[k]); });
        return fetch(target, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: body }).then(readJson);
    }
    function readJson(res) {
        return res.json().then(function (j) { return j; }, function () { return { ok: false, error: '' }; });
    }
    function fail(res) { error(res && res.error ? res.error : L('network')); }
    function error(text) {
        var el = $('[data-ra-error]');
        el.textContent = text || '';
        show(el, !!text);
    }

    // ------------------------------------------------------------------ Ouverture et fermeture

    function open(pieces, opts) {
        opts = opts || {};
        st = {
            pieces: pieces, mode: opts.move ? 'move' : 'attach', moveId: opts.move || 0, opener: opts.opener || document.activeElement,
            ctx: null, scope: '', q: '', sort: 'relevance', filters: { status: '', 'class': '', tag: '', mine: false }, page: 1,
            items: {}, picked: [], step: 'pick', tags: [], preview: null
        };
        resetForms();
        root.hidden = false;
        document.documentElement.classList.add('ra-lock');
        $('[data-ra-verb]').textContent = L(st.mode === 'move' ? 'verb.move' : 'verb.attach');
        $('[data-ra-ref]').textContent = '';
        $('[data-ra-status]').textContent = L('loading');
        $('[data-ra-results]').innerHTML = '';
        $('[data-ra-scopes]').innerHTML = '';
        show($('[data-ra-suggest]'), false);
        show($('[data-ra-recents]'), false);
        show($('[data-ra-empty]'), false);
        show($('[data-ra-more]'), false);
        show($('[data-ra-banner]'), false);
        $all('.ra-steps .ra-step').forEach(function (li) { show(li, st.mode === 'attach'); });
        closeDetail(true);
        step('pick');
        dialog.focus();
        get('context', { pieces: pieces.join(','), recents: recents().join(',') }).then(function (ctx) {
            if (!st) { return; }
            if (!ctx.ok) {
                banner(ctx.error || L('notfound'));
                $('[data-ra-status]').textContent = '';
                return;
            }
            st.ctx = ctx;
            head(ctx);
            fillFilters(ctx);
            catalog(ctx);
            strips(ctx.suggestions || []);
            chips($('[data-ra-recents-list]'), ctx.recents || [], true);
            show($('[data-ra-recents]'), (ctx.recents || []).length > 0);
            if (!ctx.ready) { banner(L('degraded')); } else if (!ctx.can_attach) { banner(ctx.deny || L('readonly')); }
            st.scope = ctx.scopes.indexOf(opts.scope) >= 0 ? opts.scope : (ctx.scopes[0] || '');
            load(false);
            var q = $('[data-ra-q]');
            if (wide()) { q.focus(); }
        }, function () { fail(null); });
    }

    function close() {
        if (!st) { return; }
        var opener = st.opener;
        st = null;
        root.hidden = true;
        document.documentElement.classList.remove('ra-lock');
        error('');
        if (opener && opener.focus) { try { opener.focus(); } catch (e) { /* élément retiré */ } }
    }

    function resetForms() {
        $all('form', root).forEach(function (f) { f.reset(); });
        $('[data-ra-q]').value = '';
        $('[data-ra-sort]').value = 'relevance';
        $('[data-ra-tags-picked]').innerHTML = '';
        $('[data-ra-alerts]').innerHTML = '';
        show($('[data-ra-filters]'), false);
        $('[data-ra-filters-toggle]').setAttribute('aria-expanded', 'false');
        error('');
    }

    function banner(text) {
        $('[data-ra-banner-text]').textContent = text;
        show($('[data-ra-banner]'), true);
    }

    function head(ctx) {
        var s = ctx.sources;
        $('[data-ra-ref]').textContent = s.length === 1 ? (s[0].ref || s[0].title) : L('pieces', s.length);
        $('[data-ra-lead]').textContent = st.mode === 'move' ? L('lead.move') : (s.length === 1 ? (L('one.' + s[0].type) || s[0].type_label) + ' · ' + s[0].title + '. ' + L('lead.one') : L('lead.many', s.length));
        var level = $('[data-ra-level]');
        var levels = s.map(function (x) { return x.level || ''; }).filter(function (v, i, a) { return a.indexOf(v) === i; });
        level.className = 'ra-level' + (levels.length === 1 && levels[0] ? ' ra-level--' + levels[0] : '');
        level.textContent = levels.length > 1 ? L('level.mixed') : (levels[0] ? L('level.piece', s[0].level_abbr) : L('level.none'));
        level.title = levels.length === 1 && levels[0] ? s[0].level_label : '';
        show(level, true);
        var input = $('[data-ra-label-input]');
        input.placeholder = s.length === 1 ? s[0].title : '';
        show($('[data-ra-label-field]'), s.length === 1 && ctx.ready);
        show($('[data-ra-tags-field]'), ctx.ready);
        var classifiable = s.some(function (x) { return x.classifiable; });
        var align = $('[data-ra-align]');
        align.disabled = !classifiable || !ctx.ready || ctx.always_align;
        align.checked = !!ctx.always_align && classifiable;
        show($('[data-ra-align-forced]'), !!ctx.always_align && classifiable);
        if (!classifiable) { $('[data-ra-align-help]').textContent = L('align.na'); }
    }

    function fillFilters(ctx) {
        var sel = $('[data-ra-filter="class"]');
        while (sel.options.length > 1) { sel.remove(1); }
        (ctx.levels || []).forEach(function (l) { sel.add(new Option(l.abbr + ' · ' + l.label, l.value)); });
        var tag = $('[data-ra-filter="tag"]');
        while (tag.options.length > 1) { tag.remove(1); }
        (ctx.tags || []).forEach(function (t) { tag.add(new Option(t.name, t.id)); });
        var lvl = $('[data-ra-create-level]');
        lvl.innerHTML = '';
        (ctx.levels || []).forEach(function (l) { lvl.add(new Option(l.abbr + ' · ' + l.label, l.value)); });
        if (lvl.options.length > 1) { lvl.value = 'encadrement'; }
    }

    // ------------------------------------------------------------------ Étape 1 : cibles

    function load(append) {
        if (!st || !st.ctx) { return; }
        if (!append) { st.page = 1; }
        var mine = ++seq;
        $('[data-ra-status]').textContent = L('loading');
        get('targets', {
            pieces: st.pieces.join(','), scope: st.scope, q: st.q, sort: st.sort, status: st.filters.status, 'class': st.filters['class'],
            tag: st.filters.tag, mine: st.filters.mine, page: st.page
        }).then(function (res) {
            if (!st || mine !== seq) { return; }
            if (!res.ok) { fail(res); return; }
            st.scope = res.scope;
            scopes(res.scopes);
            var list = $('[data-ra-results]');
            if (!append) { list.innerHTML = ''; }
            res.items.forEach(function (it) { st.items[it.key] = it; list.appendChild(row(it)); });
            $('[data-ra-status]').textContent = res.total === 0 ? '' : (res.total === 1 ? L('results.one') : L('results.many', res.total));
            show($('[data-ra-more]'), !!res.more);
            var tab = res.scopes.filter(function (s) { return s.key === res.scope; })[0];
            var disabled = tab && !tab.enabled;
            show($('[data-ra-empty]'), res.total === 0);
            $('[data-ra-empty-title]').textContent = L('results.none');
            $('[data-ra-empty-text]').textContent = disabled ? L('empty.disabled') : (st.q || activeFilters() ? L('empty.search') : (L('empty.' + res.scope) || res.empty));
            creatable(!disabled);
            var hasQuery = st.q !== '' || activeFilters() > 0;
            show($('[data-ra-suggest]'), !hasQuery && $all('.ra-strip').length > 0 && st.mode === 'attach');
            show($('[data-ra-recents]'), !hasQuery && $all('[data-ra-recents-list] li').length > 0);
        }, function () { if (st && mine === seq) { fail(null); } });
    }

    function activeFilters() {
        var f = st.filters;
        return (f.status ? 1 : 0) + (f['class'] ? 1 : 0) + (f.tag ? 1 : 0) + (f.mine ? 1 : 0);
    }

    function creatable(enabled) {
        var can = enabled && st.mode === 'attach' && st.ctx.can_attach && (st.ctx.can_create || []).indexOf(st.scope) !== -1;
        $all('[data-ra-create-open]').forEach(function (b) {
            show(b, can);
            $('[data-ra-create-label]', b).textContent = L('create.' + st.scope);
        });
    }

    function scopes(list) {
        var nav = $('[data-ra-scopes]');
        nav.innerHTML = '';
        list.forEach(function (s) {
            var b = tpl('scope');
            b.setAttribute('data-ra-scope', s.key);
            b.setAttribute('aria-selected', s.key === st.scope ? 'true' : 'false');
            b.tabIndex = s.key === st.scope ? 0 : -1;
            if (!s.enabled) { b.classList.add('is-off'); }
            b.querySelector('.ra-scope__l').textContent = L('scope.' + s.key) || s.label;
            b.querySelector('.ra-scope__n').textContent = s.enabled ? s.count : '–';
            nav.appendChild(b);
        });
    }

    function level(el, it) {
        if (!it.level) { show(el, false); return; }
        el.className = 'ra-level ra-level--' + it.level;
        el.textContent = it.level_abbr;
        el.title = it.level_label;
        show(el, true);
    }

    function tagEl(t) {
        var s = document.createElement('span');
        s.className = 'ra-tag ra-tag--' + (t.color || 'slate');
        s.textContent = t.name;
        return s;
    }

    function row(it) {
        var li = tpl('row');
        li.setAttribute('data-ra-key', it.key);
        var box = li.querySelector('input');
        box.checked = st.picked.indexOf(it.key) !== -1;
        box.disabled = !st.ctx.can_attach || it.linked_all;
        box.setAttribute('aria-label', L('pick') + ' : ' + (it.ref || it.title));
        li.classList.toggle('is-picked', box.checked);
        li.querySelector('.ra-dot').className = 'ra-dot ra-dot--' + it.tone;
        li.querySelector('.ra-row__ref').textContent = it.ref;
        li.querySelector('.ra-row__title').textContent = it.title + (it.alias ? ' · ' + it.alias : '');
        var desc = li.querySelector('.ra-row__desc');
        desc.textContent = it.excerpt;
        show(desc, !!it.excerpt);
        li.querySelector('.ra-row__state').textContent = it.status_label;
        li.querySelector('.ra-row__owner').textContent = [it.owner, it.date].filter(Boolean).join(' · ');
        var tags = li.querySelector('.ra-row__tags');
        (it.tags || []).slice(0, 3).forEach(function (t) { tags.appendChild(tagEl(t)); });
        level(li.querySelector('.ra-level'), it);
        var count = li.querySelector('.ra-row__count');
        count.querySelector('b').textContent = it.count;
        show(count, it.count > 0);
        var flag = li.querySelector('.ra-flag');
        if (it.linked > 0) {
            flag.textContent = it.linked_all ? L('flag.all') : L('flag.some', it.linked);
            show(flag, true);
        }
        return li;
    }

    function strips(list) {
        var ol = $('[data-ra-strips]');
        ol.innerHTML = '';
        list.forEach(function (it, n) {
            st.items[it.key] = it;
            var li = tpl('strip');
            li.setAttribute('data-ra-key', it.key);
            li.classList.add('ra-strip--' + ACCENTS[n % ACCENTS.length]);
            if (n % 2 === 1) { li.classList.add('is-mirror'); }
            li.style.setProperty('--ra-i', n);
            li.querySelector('.ra-strip__index').textContent = '/ ' + (n < 9 ? '0' : '') + (n + 1);
            li.querySelector('.ra-strip__title').textContent = (it.ref ? it.ref + ' · ' : '') + it.title;
            li.querySelector('.ra-strip__why').textContent = (L('one.' + it.type) || it.type_label) + ' — ' + it.why;
            var pill = li.querySelector('.ra-strip__pill');
            pill.textContent = it.owner && it.date ? L('strip.meta', it.owner, it.date) : (it.owner || it.date || it.status_label);
            show(pill, !!pill.textContent);
            li.querySelector('.ra-strip__figure b').textContent = it.count;
            li.querySelector('[data-ra-strip-pick]').disabled = !st.ctx.can_attach;
            ol.appendChild(li);
        });
    }

    function chips(ul, list, pickable) {
        ul.innerHTML = '';
        list.forEach(function (it) {
            st.items[it.key] = it;
            var li = document.createElement('li');
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'ra-chip';
            b.setAttribute('data-ra-recent', it.key);
            b.textContent = (it.ref ? it.ref + ' · ' : '') + it.title;
            b.disabled = !!pickable && (!st.ctx.can_attach || it.linked_all);
            li.appendChild(b);
            ul.appendChild(li);
        });
    }

    function toggle(key, on) {
        var it = st.items[key];
        if (!it || !st.ctx.can_attach || it.linked_all) { return; }
        var at = st.picked.indexOf(key);
        var want = on == null ? at === -1 : on;
        if (st.mode === 'move') { st.picked = want ? [key] : []; } else if (want && at === -1) {
            if (st.picked.length >= 12) { return; }
            st.picked.push(key);
        } else if (!want && at !== -1) { st.picked.splice(at, 1); }
        $all('.ra-row').forEach(function (li) {
            var k = li.getAttribute('data-ra-key');
            var is = st.picked.indexOf(k) !== -1;
            li.classList.toggle('is-picked', is);
            li.querySelector('input').checked = is;
        });
        $all('.ra-strip').forEach(function (li) { li.classList.toggle('is-picked', st.picked.indexOf(li.getAttribute('data-ra-key')) !== -1); });
        picked();
        if ($('[data-ra-detail-card]').getAttribute('data-ra-key') === key) { detailPickLabel(key); }
    }

    function picked() {
        var n = st.picked.length;
        $('[data-ra-picked-n]').textContent = n === 0 ? L('picked.none') : (n === 1 ? L('picked.one') : L('picked.many', n));
        var ul = $('[data-ra-picked-list]');
        ul.innerHTML = '';
        st.picked.forEach(function (key) {
            var it = st.items[key];
            var li = tpl('chip');
            li.querySelector('.ra-chipitem__t').textContent = it.ref || it.title;
            li.querySelector('button').setAttribute('data-ra-unpick', key);
            li.querySelector('button').setAttribute('aria-label', L('unpick') + ' : ' + (it.ref || it.title));
            ul.appendChild(li);
        });
        nextState();
    }

    // ------------------------------------------------------------------ Détail et création à la volée

    function detail(key, focus) {
        var it = st.items[key];
        if (!it) { return; }
        var card = $('[data-ra-detail-card]');
        card.setAttribute('data-ra-key', key);
        get('target', { pieces: st.pieces.join(','), cible: key }).then(function (res) {
            if (!st || card.getAttribute('data-ra-key') !== key || !res.ok) { return; }
            var d = res.item;
            st.items[key] = Object.assign({}, it, d);
            d.type_label = L('one.' + d.type) || d.type_label;
            ['type_label', 'ref', 'title', 'description', 'owner', 'date', 'updated', 'readers', 'count'].forEach(function (k) {
                var el = card.querySelector('[data-ra-d="' + k + '"]');
                if (el) { el.textContent = d[k] == null ? '' : d[k]; }
                var rowEl = card.querySelector('[data-ra-row="' + k + '"]');
                if (rowEl) { show(rowEl, !!d[k]); }
            });
            show(card.querySelector('[data-ra-d="description"]'), !!d.description);
            var state = card.querySelector('[data-ra-d-state]');
            state.innerHTML = '';
            if (d.status_label) {
                var dot = document.createElement('span');
                dot.className = 'ra-dot ra-dot--' + d.tone;
                state.appendChild(dot);
                state.appendChild(document.createTextNode(d.status_label));
            }
            level(card.querySelector('[data-ra-d-level]'), d);
            var ul = card.querySelector('[data-ra-d-attached]');
            ul.innerHTML = '';
            (d.attached || []).forEach(function (a) {
                var li = document.createElement('li');
                li.textContent = (a.ref ? a.ref + ' · ' : '') + a.label;
                if (!a.ref) { li.className = 'is-restricted'; }
                ul.appendChild(li);
            });
            show(card.querySelector('[data-ra-row="attached"]'), (d.attached || []).length > 0);
            var openLink = card.querySelector('[data-ra-detail-open]');
            openLink.href = d.href || '#';
            show(openLink, !!d.href);
            detailPickLabel(key);
            show($('[data-ra-detail-none]'), false);
            show($('[data-ra-create-form]'), false);
            show(card, true);
            $('[data-ra-detail]').classList.add('is-open');
            if (focus) { $('[data-ra-detail]').focus(); }
        });
    }

    function detailPickLabel(key) {
        var b = $('[data-ra-detail-pick]');
        var it = st.items[key];
        b.setAttribute('data-ra-key', key);
        b.disabled = !st.ctx.can_attach || !!(it && it.linked_all);
        $('[data-ra-detail-pick-label]').textContent = it && it.linked_all ? L('flag.all') : (st.picked.indexOf(key) !== -1 ? L('unpick') : L('pick'));
    }

    function closeDetail(reset) {
        var aside = $('[data-ra-detail]');
        aside.classList.remove('is-open');
        if (reset) {
            show($('[data-ra-detail-card]'), false);
            show($('[data-ra-create-form]'), false);
            show($('[data-ra-detail-none]'), true);
            $('[data-ra-detail-card]').removeAttribute('data-ra-key');
        }
    }

    function createOpen() {
        var form = $('[data-ra-create-form]');
        form.reset();
        if ($all('option', form.elements.classification).some(function (o) { return o.value === 'encadrement'; })) { form.elements.classification.value = 'encadrement'; }
        form.elements.type.value = st.scope;
        $('[data-ra-create-title]').textContent = L('create.' + st.scope);
        var q = $('[data-ra-q]').value.trim();
        if (q.length > 2) { form.elements.title.value = q; }
        show($('[data-ra-create-error]'), false);
        show($('[data-ra-detail-card]'), false);
        show($('[data-ra-detail-none]'), false);
        show(form, true);
        $('[data-ra-detail]').classList.add('is-open');
        form.elements.title.focus();
    }

    function createSubmit(form) {
        var err = $('[data-ra-create-error]');
        if (form.elements.title.value.trim().length < 3) {
            err.textContent = L('title.short');
            show(err, true);
            form.elements.title.focus();
            return;
        }
        post(url('create'), { type: form.elements.type.value, title: form.elements.title.value, description: form.elements.description.value, classification: form.elements.classification.value }).then(function (res) {
            if (!st) { return; }
            if (!res.ok) {
                err.textContent = res.error || L('network');
                show(err, true);
                return;
            }
            get('target', { pieces: st.pieces.join(','), cible: res.key }).then(function (d) {
                if (!st || !d.ok) { return; }
                st.items[res.key] = d.item;
                toggle(res.key, true);
                show(form, false);
                st.q = '';
                $('[data-ra-q]').value = '';
                load(false);
                detail(res.key, false);
            });
        }, function () { err.textContent = L('network'); show(err, true); });
    }

    // ------------------------------------------------------------------ Étapes 2 et 3

    function step(name) {
        st.step = name;
        $all('[data-ra-step]').forEach(function (el) { show(el, el.getAttribute('data-ra-step') === name); });
        var order = STEPS[st.mode];
        $all('[data-ra-stepmark]').forEach(function (li) {
            var at = order.indexOf(li.getAttribute('data-ra-stepmark'));
            li.classList.toggle('is-on', at === order.indexOf(name));
            li.classList.toggle('is-done', at !== -1 && at < order.indexOf(name));
            if (at === order.indexOf(name)) { li.setAttribute('aria-current', 'step'); } else { li.removeAttribute('aria-current'); }
        });
        show($('[data-ra-prev]'), order.indexOf(name) > 0);
        var last = order.indexOf(name) === order.length - 1;
        $('[data-ra-next-label]').textContent = last ? L(st.mode === 'move' ? 'next.move' : 'next.attach') : (name === 'settings' ? L('next.recap') : L('next.continue'));
        $('[data-ra-next]').classList.toggle('is-final', last);
        error('');
        nextState();
        if (name === 'settings') {
            alerts();
            $('#ra-reason').focus();
        } else if (name === 'move') {
            var it = st.items[st.picked[0]];
            $('[data-ra-move-lead]').textContent = (st.ctx.sources[0].ref || st.ctx.sources[0].title) + ' → ' + (it.ref ? it.ref + ' · ' : '') + it.title;
            $('#ra-move-reason').focus();
        } else if (name === 'recap') {
            $('[data-ra-next]').focus();
        }
    }

    function nextState() {
        if (!st) { return; }
        $('[data-ra-next]').disabled = !st.ctx || !st.ctx.can_attach || st.picked.length === 0;
    }

    function settings(reasonFallback) {
        var f = $('form[data-ra-step="settings"]');
        return {
            pieces: st.pieces.join(','), cibles: st.picked.join(','), nature: f.elements.nature.value, nature_label: f.elements.nature_label.value,
            label: f.elements.label.value, reason: f.elements.reason.value.trim() || reasonFallback || '', note: f.elements.note.value,
            tags: JSON.stringify(st.tags.map(function (t) { return t.id ? '#' + t.id : t.name + '|' + t.color; })), align: f.elements.align.checked ? '1' : '0'
        };
    }

    function alertList(ul, messages) {
        ul.innerHTML = '';
        messages.forEach(function (m) {
            var li = tpl('alert');
            li.classList.add('ra-alert--' + m.kind);
            li.querySelector('span').textContent = m.text;
            ul.appendChild(li);
        });
    }

    function alerts() {
        post(url('preview'), settings('preview')).then(function (res) {
            if (!st || st.step !== 'settings' || !res.ok) { return; }
            var seen = {};
            var out = [];
            res.rows.forEach(function (r) {
                var text = effectText(r);
                if (text && !seen[text]) {
                    seen[text] = true;
                    out.push({ kind: r.effect === 'target_lower' ? 'warn' : (r.effect === 'raise' ? 'raise' : 'info'), text: text });
                }
            });
            alertList($('[data-ra-alerts]'), out);
        });
    }

    /* Phrase de l'effet de confidentialité : libellé de la vue (traduit avec la page), à défaut le texte du serveur. */
    function effectText(r) {
        return L('fx.' + r.effect, r.target_level, r.piece_level) || r.message || '';
    }
    function natureText(r) {
        var opt = r.nature_key ? root.querySelector('[data-ra-nature] option[value="' + r.nature_key + '"]') : null;
        return opt ? opt.textContent : r.nature;
    }

    function recap() {
        var data = settings('');
        if (data.reason.length < 5) {
            error(L('reason.short'));
            $('#ra-reason').focus();
            return;
        }
        post(url('preview'), data).then(function (res) {
            if (!st) { return; }
            if (!res.ok) { fail(res); return; }
            st.preview = res;
            var body = $('[data-ra-recap-rows]');
            body.innerHTML = '';
            res.rows.forEach(function (r) {
                var tr = document.createElement('tr');
                var note = r.state === 'already' ? L('recap.already') : (r.state === 'enrich' ? L('recap.enrich') : '');
                var fx = effectText(r);
                [[r.source, r.label || r.source_title], [natureText(r), ''], [r.target, (L('one.' + r.target_scope) || r.target_type) + ' · ' + r.target_title], [fx || note || (r.legacy ? L('recap.legacy') : L('recap.none')), fx && note ? note : '']].forEach(function (c, n) {
                    var td = document.createElement('td');
                    var b = document.createElement(n === 3 ? 'span' : 'b');
                    b.textContent = c[0];
                    td.appendChild(b);
                    if (c[1]) {
                        var s = document.createElement('small');
                        s.textContent = c[1];
                        td.appendChild(s);
                    }
                    tr.appendChild(td);
                });
                if (r.state === 'already') { tr.className = 'is-skipped'; }
                if (r.effect === 'target_lower') { tr.className += ' is-warn'; }
                body.appendChild(tr);
            });
            alertList($('[data-ra-recap-effects]'), res.effects.map(function (e) {
                return { kind: 'raise', text: L('effect.raise', e.source, e.from, e.to, e.lose, e.keep) };
            }));
            $('[data-ra-recap-lead]').textContent = L('recap.lead', res['new']);
            $('[data-ra-recap="reason"]').textContent = data.reason;
            $('[data-ra-recap="note"]').textContent = data.note;
            show($('[data-ra-row="recap-note"]'), !!data.note.trim());
            var tags = $('[data-ra-recap="tags"]');
            tags.innerHTML = '';
            st.tags.forEach(function (t) { tags.appendChild(tagEl(t)); });
            show($('[data-ra-row="recap-tags"]'), st.tags.length > 0);
            step('recap');
            $('[data-ra-next]').disabled = res['new'] === 0;
        }, function () { fail(null); });
    }

    function submit() {
        var btn = $('[data-ra-next]');
        btn.disabled = true;
        var first = st.items[st.picked[0]];
        var src = st.ctx.sources;
        var done = st.mode === 'move'
            ? post(url('base') + '/' + st.moveId + '/deplacer', { cible: st.picked[0], reason: $('#ra-move-reason').value.trim() })
            : post(url('attach'), settings(''));
        var mode = st.mode;
        var keys = st.picked.slice();
        done.then(function (res) {
            if (!st) { return; }
            if (!res.ok) { btn.disabled = false; fail(res); return; }
            remember(keys);
            var text = mode === 'move' ? L('done.move') : (res.count === 1 && src.length === 1 ? L('done.one', src[0].ref || src[0].title, first.ref || first.title) : L('done.many', res.count));
            (res.aligned || []).forEach(function (a) { text += ' ' + L('done.aligned', a.source + ' ' + a.from + ' → ' + a.to); });
            close();
            if (window.SseNotify) { window.SseNotify.success(text, L('done.title')); }
            document.dispatchEvent(new CustomEvent('ra:attached', { detail: res }));
            undoBar(text, mode === 'attach' ? (res.created || []) : []);
        }, function () { btn.disabled = false; fail(null); });
    }

    function next() {
        if (st.step === 'pick') { step(st.mode === 'move' ? 'move' : 'settings'); return; }
        if (st.step === 'settings') { recap(); return; }
        if (st.step === 'move') {
            if ($('#ra-move-reason').value.trim().length < 5) { error(L('reason.short')); $('#ra-move-reason').focus(); return; }
            submit();
            return;
        }
        submit();
    }

    function prev() {
        var order = STEPS[st.mode];
        var at = order.indexOf(st.step);
        if (at > 0) { step(order[at - 1]); }
    }

    // ------------------------------------------------------------------ Étiquettes

    function catalog(ctx) {
        var box = $('[data-ra-tags-catalog]');
        box.innerHTML = '';
        (ctx.tags || []).slice(0, 14).forEach(function (t) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'ra-tag ra-tag--' + t.color + ' ra-tag--btn';
            b.setAttribute('data-ra-tag-id', t.id);
            b.setAttribute('aria-pressed', 'false');
            b.textContent = t.name;
            box.appendChild(b);
        });
    }

    function addTag(tag) {
        var fold = function (s) { return s.toLowerCase().trim(); };
        if (!tag.name || st.tags.length >= 8 || st.tags.some(function (t) { return fold(t.name) === fold(tag.name); })) { return; }
        var known = (st.ctx.tags || []).filter(function (t) { return fold(t.name) === fold(tag.name); })[0];
        st.tags.push(known || tag);
        drawTags();
    }

    function drawTags() {
        var ul = $('[data-ra-tags-picked]');
        ul.innerHTML = '';
        st.tags.forEach(function (t, n) {
            var li = document.createElement('li');
            li.className = 'ra-tag ra-tag--' + t.color;
            li.appendChild(document.createTextNode(t.name));
            var x = document.createElement('button');
            x.type = 'button';
            x.setAttribute('data-ra-tag-drop', n);
            x.setAttribute('aria-label', L('tag.remove', t.name));
            x.textContent = '×';
            li.appendChild(x);
            ul.appendChild(li);
        });
        $all('[data-ra-tag-id]').forEach(function (b) {
            b.setAttribute('aria-pressed', st.tags.some(function (t) { return String(t.id) === b.getAttribute('data-ra-tag-id'); }) ? 'true' : 'false');
        });
    }

    // ------------------------------------------------------------------ Récents et annulation rapide

    function recents() {
        try {
            var list = JSON.parse(window.localStorage.getItem(RECENTS_KEY) || '[]');
            return Array.isArray(list) ? list.filter(function (k) { return /^[a-z_]+:\d+$/.test(k); }).slice(0, 8) : [];
        } catch (e) { return []; }
    }
    function remember(keys) {
        try {
            var list = keys.concat(recents().filter(function (k) { return keys.indexOf(k) === -1; })).slice(0, 8);
            window.localStorage.setItem(RECENTS_KEY, JSON.stringify(list));
        } catch (e) { /* stockage indisponible : les récents ne sont qu'un confort */ }
    }

    function undoBar(text, ids) {
        var bar = document.querySelector('[data-ra-undo-bar]');
        if (!bar) { return; }
        var seconds = st && st.ctx ? st.ctx.undo_seconds : 15;
        var btn = bar.querySelector('[data-ra-undo-btn]');
        var reload = bar.querySelector('[data-ra-undo-reload]');
        bar.querySelector('[data-ra-undo-text]').textContent = text;
        bar.setAttribute('data-ra-ids', ids.join(','));
        show(btn, ids.length > 0);
        show(reload, ids.length === 0);
        show(bar, true);
        window.clearInterval(undoTimer);
        var count = bar.querySelector('[data-ra-undo-count]');
        var left = 15;
        count.textContent = left;
        undoTimer = window.setInterval(function () {
            left -= 1;
            count.textContent = left;
            if (left <= 0) {
                window.clearInterval(undoTimer);
                show(btn, false);
                show(reload, true);
                var busy = document.activeElement && /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName);
                if (!busy && !document.querySelector('dialog[open]') && (!st)) { window.location.reload(); }
            }
        }, 1000);
        if (seconds === 0) { show(btn, false); }
    }

    document.addEventListener('click', function (e) {
        var bar = e.target.closest ? e.target.closest('[data-ra-undo-bar]') : null;
        if (!bar) { return; }
        if (e.target.closest('[data-ra-undo-close]')) { window.clearInterval(undoTimer); show(bar, false); }
        if (e.target.closest('[data-ra-undo-reload]')) { window.location.reload(); }
        if (e.target.closest('[data-ra-undo-btn]')) {
            window.clearInterval(undoTimer);
            post(url('undo'), { ids: bar.getAttribute('data-ra-ids') }).then(function (res) {
                show(bar, false);
                if (!window.SseNotify) { return; }
                if (res.ok) { window.SseNotify.success(L('undo.done')); window.setTimeout(function () { window.location.reload(); }, 900); } else { window.SseNotify.error(res.error || L('network')); }
            });
        }
    });

    // ------------------------------------------------------------------ Événements de la fenêtre

    root.addEventListener('click', function (e) {
        if (!st) { return; }
        var t = e.target;
        var hit = function (sel) { return t.closest(sel); };
        var el;
        if (hit('[data-ra-close]')) { close(); return; }
        if ((el = hit('[data-ra-scope]'))) {
            st.scope = el.getAttribute('data-ra-scope');
            closeDetail(true);
            load(false);
            return;
        }
        if ((el = hit('[data-ra-unpick]'))) { toggle(el.getAttribute('data-ra-unpick'), false); return; }
        if ((el = hit('[data-ra-recent]'))) { toggle(el.getAttribute('data-ra-recent')); detail(el.getAttribute('data-ra-recent'), false); return; }
        if ((el = hit('[data-ra-strip-pick]'))) { toggle(el.closest('.ra-strip').getAttribute('data-ra-key')); return; }
        if ((el = hit('[data-ra-strip-detail], .ra-strip__who'))) { detail(el.closest('.ra-strip').getAttribute('data-ra-key'), true); return; }
        if ((el = hit('.ra-row__body'))) {
            var key = el.closest('.ra-row').getAttribute('data-ra-key');
            if (wide()) { toggle(key); detail(key, false); } else { detail(key, true); }
            return;
        }
        if (hit('[data-ra-detail-close]')) { closeDetail(false); return; }
        if ((el = hit('[data-ra-detail-pick]'))) { toggle(el.getAttribute('data-ra-key')); if (!wide()) { closeDetail(false); } return; }
        if (hit('[data-ra-create-open]')) { createOpen(); return; }
        if (hit('[data-ra-create-cancel]')) { closeDetail(true); return; }
        if (hit('[data-ra-more]')) { st.page += 1; load(true); return; }
        if ((el = hit('[data-ra-filters-toggle]'))) {
            var box = $('[data-ra-filters]');
            show(box, box.hidden);
            el.setAttribute('aria-expanded', box.hidden ? 'false' : 'true');
            return;
        }
        if (hit('[data-ra-filters-reset]')) {
            $all('[data-ra-filter]').forEach(function (f) { if (f.type === 'checkbox') { f.checked = false; } else { f.value = ''; } });
            st.filters = { status: '', 'class': '', tag: '', mine: false };
            filtersCount();
            load(false);
            return;
        }
        if ((el = hit('[data-ra-template]'))) {
            $('#ra-reason').value = el.textContent.trim();
            $('#ra-reason').focus();
            error('');
            return;
        }
        if ((el = hit('[data-ra-tag-id]'))) {
            var id = el.getAttribute('data-ra-tag-id');
            var at = -1;
            st.tags.forEach(function (x, n) { if (String(x.id) === id) { at = n; } });
            if (at !== -1) { st.tags.splice(at, 1); drawTags(); } else { addTag((st.ctx.tags || []).filter(function (x) { return String(x.id) === id; })[0]); }
            return;
        }
        if ((el = hit('[data-ra-tag-drop]'))) { st.tags.splice(parseInt(el.getAttribute('data-ra-tag-drop'), 10), 1); drawTags(); return; }
        if (hit('[data-ra-next]')) { next(); return; }
        if (hit('[data-ra-prev]')) { prev(); }
    });

    function filtersCount() {
        var n = activeFilters();
        var badge = $('[data-ra-filters-count]');
        badge.textContent = n;
        show(badge, n > 0);
    }

    root.addEventListener('change', function (e) {
        if (!st) { return; }
        var t = e.target;
        if (t.matches('.ra-row__pick input')) { toggle(t.closest('.ra-row').getAttribute('data-ra-key'), t.checked); return; }
        if (t.matches('[data-ra-sort]')) { st.sort = t.value; load(false); return; }
        if (t.matches('[data-ra-filter]')) {
            st.filters[t.getAttribute('data-ra-filter')] = t.type === 'checkbox' ? t.checked : t.value;
            filtersCount();
            load(false);
            return;
        }
        if (t.matches('[data-ra-nature]')) { show($('[data-ra-nature-free]'), t.value === 'related'); return; }
        if (t.matches('[data-ra-align]')) { alerts(); }
    });

    root.addEventListener('input', function (e) {
        if (!st || !e.target.matches('[data-ra-q]')) { return; }
        window.clearTimeout(searchTimer);
        var value = e.target.value;
        searchTimer = window.setTimeout(function () { if (st) { st.q = value.trim(); load(false); } }, 220);
    });

    root.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!st) { return; }
        if (e.target.matches('[data-ra-create-form]')) { createSubmit(e.target); return; }
        if (!$('[data-ra-next]').disabled) { next(); }
    });

    root.addEventListener('mouseover', function (e) {
        if (!st || !wide()) { return; }
        var li = e.target.closest('.ra-row');
        if (!li || !$('[data-ra-create-form]').hidden) { return; }
        window.clearTimeout(hoverTimer);
        var key = li.getAttribute('data-ra-key');
        hoverTimer = window.setTimeout(function () { if (st) { detail(key, false); } }, 260);
    });

    root.addEventListener('keydown', function (e) {
        if (!st) { return; }
        var t = e.target;
        if (e.key === 'Escape') {
            e.preventDefault();
            if (!wide() && $('[data-ra-detail]').classList.contains('is-open')) { closeDetail(false); } else { close(); }
            return;
        }
        if (e.key === 'Enter' && t.matches('[data-ra-tag-input]')) {
            e.preventDefault();
            addTag({ id: 0, name: t.value.trim().slice(0, 40), color: $('[data-ra-tag-color]').value });
            t.value = '';
            return;
        }
        if (e.key === 'Enter' && t.matches('[data-ra-q]')) { e.preventDefault(); return; }
        if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && (t.matches('.ra-row__body') || t.matches('[data-ra-q]') || t.matches('[data-ra-scope]'))) {
            var list = t.matches('[data-ra-scope]') ? $all('[data-ra-scope]') : $all('.ra-row__body');
            var at = list.indexOf(t);
            var to = e.key === 'ArrowDown' ? at + 1 : at - 1;
            if (t.matches('[data-ra-q]')) { to = e.key === 'ArrowDown' ? 0 : -1; }
            if (to >= 0 && to < list.length) {
                e.preventDefault();
                list[to].focus();
                if (t.matches('[data-ra-scope]')) { list[to].click(); var again = root.querySelector('[data-ra-scope="' + list[to].getAttribute('data-ra-scope') + '"]'); if (again) { again.focus(); } }
            } else if (to < 0 && t.matches('.ra-row__body')) { e.preventDefault(); $('[data-ra-q]').focus(); }
            return;
        }
        if (e.key === ' ' && t.matches('.ra-row__body')) { e.preventDefault(); toggle(t.closest('.ra-row').getAttribute('data-ra-key')); return; }
        if (e.key === 'Tab') {
            var focusable = $all('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])', dialog).filter(function (el) {
                return !el.disabled && el.offsetParent !== null;
            });
            if (focusable.length === 0) { return; }
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (e.shiftKey && (document.activeElement === first || document.activeElement === dialog)) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });

    // ------------------------------------------------------------------ Ouvreurs (toutes les pages)

    function piecesOf(btn) {
        var direct = (btn.getAttribute('data-ra-pieces') || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        var sel = btn.getAttribute('data-ra-collect');
        var type = btn.getAttribute('data-ra-type');
        if (sel && type) {
            $all(sel, document).forEach(function (el) {
                var v = el.getAttribute('data-ra-id') || el.value;
                if (/^\d+$/.test(v || '')) { direct.push(type + ':' + v); }
            });
        }
        return direct.filter(function (v, i, a) { return a.indexOf(v) === i; }).slice(0, 50);
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-ra-open]') : null;
        if (!btn || btn.disabled) { return; }
        e.preventDefault();
        var pieces = piecesOf(btn);
        if (pieces.length === 0) {
            if (window.SseNotify && btn.getAttribute('data-ra-none')) { window.SseNotify.warning(btn.getAttribute('data-ra-none')); }
            return;
        }
        var owner = btn.closest('dialog[open]');
        if (owner && owner.close) { owner.close(); }
        open(pieces, { move: parseInt(btn.getAttribute('data-ra-move') || '0', 10) || 0, opener: btn, scope: btn.getAttribute('data-ra-initial-scope') || '' });
    });

    // Fenêtres simples des pages Pistes et de la section : ouvrir par identifiant, fermer la fenêtre courante.
    document.addEventListener('click', function (e) {
        var show = e.target.closest ? e.target.closest('[data-ras-show]') : null;
        if (show) {
            var dlg = document.getElementById(show.getAttribute('data-ras-show'));
            if (dlg && dlg.showModal) { dlg.showModal(); }
            return;
        }
        var shut = e.target.closest ? e.target.closest('[data-ras-close]') : null;
        if (shut && shut.closest('dialog')) { shut.closest('dialog').close(); }
    });

    window.SseRattacher = { open: function (pieces, opts) { open([].concat(pieces), opts); }, close: close };

    // ------------------------------------------------------------------ Section « Éléments rattachés »

    $all('[data-ras]', document).forEach(function (sec) {
        var list = sec.querySelector('[data-ras-list]');
        var liaisons = sec.querySelector('[data-ras-liaisons]');
        var widget = document.querySelector('[data-sl-open]');
        if (liaisons && widget) {
            liaisons.hidden = false;
            liaisons.addEventListener('click', function () { widget.click(); });
        }
        var rows = list ? $all('[data-ras-item]', list) : [];
        function apply() {
            var q = (sec.querySelector('[data-ras-q]') || {}).value || '';
            q = q.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
            var f = {};
            $all('[data-ras-filter]', sec).forEach(function (s) { f[s.getAttribute('data-ras-filter')] = s.value; });
            var shown = 0;
            rows.forEach(function (li) {
                var ok = (!f.type || li.getAttribute('data-type') === f.type) && (!f.nature || li.getAttribute('data-nature') === f.nature)
                    && (!f.tag || (' ' + li.getAttribute('data-tags') + ' ').indexOf(' ' + f.tag + ' ') !== -1)
                    && (!q || (li.getAttribute('data-hay') || '').indexOf(q) !== -1);
                li.hidden = !ok;
                if (ok) { shown += 1; }
            });
            var sort = (sec.querySelector('[data-ras-sort]') || {}).value || 'recent';
            rows.slice().sort(function (a, b) {
                if (sort === 'alpha') { return (a.getAttribute('data-title') || '').localeCompare(b.getAttribute('data-title') || ''); }
                if (sort === 'type') { return (a.getAttribute('data-type') || '').localeCompare(b.getAttribute('data-type') || '') || (b.getAttribute('data-at') || '').localeCompare(a.getAttribute('data-at') || ''); }
                return (b.getAttribute('data-at') || '').localeCompare(a.getAttribute('data-at') || '');
            }).forEach(function (li) { list.appendChild(li); });
            var none = sec.querySelector('[data-ras-none]');
            if (none) { none.hidden = shown > 0 || rows.length === 0; }
        }
        sec.addEventListener('input', function (e) { if (e.target.matches('[data-ras-q]')) { apply(); } });
        sec.addEventListener('change', function (e) { if (e.target.matches('[data-ras-filter], [data-ras-sort]')) { apply(); } });
        sec.addEventListener('click', function (e) {
            var b = e.target.closest('[data-ras-dialog]');
            if (b) {
                var dlg = document.getElementById(b.getAttribute('data-ras-dialog'));
                var item = b.closest('[data-ras-item]');
                if (!dlg || !item || !dlg.showModal) { return; }
                var form = dlg.querySelector('form');
                form.action = sec.getAttribute('data-ras-base') + '/' + item.getAttribute('data-ras-item') + '/' + form.getAttribute('data-ras-form');
                dlg.querySelector('[data-ras-ref]').textContent = item.getAttribute('data-ref');
                if (form.elements.label) {
                    form.elements.label.value = item.getAttribute('data-label');
                    form.elements.reason.value = item.getAttribute('data-reason');
                    form.elements.note.value = item.getAttribute('data-note');
                    form.elements.tags.value = item.getAttribute('data-tagnames');
                } else { form.elements.reason.value = ''; }
                var menu = b.closest('details');
                if (menu) { menu.open = false; }
                dlg.showModal();
                return;
            }
        });
    });
}());
