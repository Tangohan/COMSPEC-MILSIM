/*
 * Espace de traduction du site par la communauté (/traduire-site/{code}).
 * Liste des textes à gauche (GET data-api ?q=&f=&p=, compteurs par filtre), éditeur du texte choisi à droite :
 * original avec les éléments à garder en surbrillance (cliquables pour les insérer), référence anglaise, mémoire
 * (même texte déjà traduit ailleurs), contrôle en direct, « Valider et suivant » (Ctrl+Entrée), propositions.
 * Enregistrement : POST data-api {h, text} — validé pour un traducteur de la langue ou l'administration, sinon
 * proposé. Décision : POST data-decide/{id} {action}. Mêmes contrôles que CommunityLanguages::check côté serveur.
 */
(function () {
    'use strict';
    var root = document.getElementById('sitetr');
    if (!root) { return; }
    var I18N = {};
    try { I18N = JSON.parse(root.getAttribute('data-i18n') || '{}'); } catch (e) { I18N = {}; }
    var tx = function (k, r) {
        var s = I18N[k] || k;
        Object.keys(r || {}).forEach(function (n) { s = s.split(':' + n).join(String(r[n])); });
        return s;
    };
    var API = root.getAttribute('data-api');
    var DECIDE = root.getAttribute('data-decide');
    var CSRF = root.getAttribute('data-csrf');
    var LOGGED = root.getAttribute('data-logged') === '1';
    var IS_EN = root.getAttribute('data-lang') === 'en';
    var TOTAL = parseInt(root.getAttribute('data-total') || '0', 10);
    var list = document.getElementById('sitetr-list');
    var bar = document.getElementById('sitetr-bar');
    var tabs = document.getElementById('tw-tabs');
    var pageLabel = document.getElementById('sitetr-page');
    var editor = document.getElementById('tw-editor');
    var S = { q: '', f: 'todo', p: 1, pages: 1, per: 40, total: 0, rows: [], i: -1, review: root.getAttribute('data-review') === '1', seq: 0, drafts: {} };

    // ------------------------------------------------------------------ outils
    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text !== undefined && text !== null) { n.textContent = text; }
        return n;
    }
    function btn(cls, text, fn) {
        var b = el('button', cls, text);
        b.type = 'button';
        if (fn) { b.addEventListener('click', fn); }
        return b;
    }
    function tokenRe(keyed) { return keyed ? /%\d+|:[a-z_]+\b|<[^<>]*>?/g : /%\d+|<[^<>]*>?/g; }
    function tokens(row) {
        var seen = {};
        return (row.fr.match(tokenRe(row.kind === 'key')) || []).filter(function (t) { if (seen[t]) { return false; } seen[t] = true; return true; });
    }
    function placeholders(s, keyed) {
        var re = keyed ? /%\d+|:[a-z_]+\b/g : /%\d+/g;
        return (s.match(re) || []).sort().join('|');
    }
    function markup(s) {
        var out = [];
        var gt = s.indexOf('>');
        var lt = s.indexOf('<');
        if (gt >= 0 && (lt < 0 || gt < lt)) { out.push(s.slice(0, gt + 1)); s = s.slice(gt + 1); }
        return out.concat(s.match(/<[^<>]*>?/g) || []).join('|');
    }
    function check(row, text) {
        var errs = [];
        if (!text.trim()) { return ['empty']; }
        if (placeholders(row.fr, row.kind === 'key') !== placeholders(text, row.kind === 'key')) { errs.push('placeholders'); }
        if (markup(row.fr) !== markup(text)) { errs.push('markup'); }
        if ((row.fr.indexOf('!!') === 0) !== (text.indexOf('!!') === 0)) { errs.push('prefix'); }
        return errs;
    }
    // Texte avec les éléments à garder en surbrillance.
    function highlighted(text, keyed) {
        var frag = document.createDocumentFragment();
        var re = tokenRe(keyed);
        var last = 0;
        var m;
        while ((m = re.exec(text)) !== null) {
            if (m.index > last) { frag.appendChild(document.createTextNode(text.slice(last, m.index))); }
            frag.appendChild(el('mark', 'tw-tok', m[0]));
            last = m.index + m[0].length;
        }
        if (last < text.length) { frag.appendChild(document.createTextNode(text.slice(last))); }
        return frag;
    }
    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json().catch(function () { return { ok: false, errors: ['http_' + r.status] }; }); });
    }
    function narrow() { return window.matchMedia('(max-width: 979px)').matches; }

    // ------------------------------------------------------------------ progression
    function bumpProgress(delta) {
        var lab = document.getElementById('tw-done-label');
        var cur = parseInt((lab && lab.textContent.match(/\d+/) || ['0'])[0], 10) + delta;
        if (lab) { lab.textContent = lab.textContent.replace(/\d+/, String(Math.max(0, cur))); }
        var pct = TOTAL > 0 ? Math.floor(Math.max(0, cur) * 100 / TOTAL) : 0;
        var p = document.getElementById('tw-pct'); if (p) { p.textContent = String(pct); }
        var b = document.getElementById('tw-bar'); if (b) { b.style.width = pct + '%'; }
    }
    function setCounts(c) {
        Object.keys(c || {}).forEach(function (k) {
            var n = tabs.querySelector('[data-count="' + k + '"]');
            if (n) { n.textContent = String(c[k]); }
        });
    }
    function adjustCount(k, d) {
        var n = tabs.querySelector('[data-count="' + k + '"]');
        if (n && n.textContent !== '') { n.textContent = String(Math.max(0, parseInt(n.textContent, 10) + d)); }
    }

    // ------------------------------------------------------------------ liste
    function itemState(row) { return row.tr ? 'done' : (row.sugg.length ? 'sugg' : 'todo'); }
    function renderItem(row, i) {
        var li = el('li', 'tw-item is-' + itemState(row) + (i === S.i ? ' is-active' : ''));
        var b = el('button', 'tw-item__btn');
        b.type = 'button';
        b.setAttribute('data-i', String(i));
        b.appendChild(el('i', 'tw-dot' + (row.tr ? ' is-done' : (row.sugg.length ? ' is-sugg' : ''))));
        var body = el('span', 'tw-item__body');
        var fr = el('span', 'tw-item__fr', row.fr);
        fr.lang = 'fr';
        body.appendChild(fr);
        var draft = S.drafts[row.h];
        var sub = draft !== undefined && draft !== row.tr ? '✎ ' + draft : (row.tr || (row.sugg.length ? tx('badge_sugg') + ' · ' + row.sugg[0].text : ''));
        if (sub) { body.appendChild(el('span', 'tw-item__tr', sub)); }
        b.appendChild(body);
        li.appendChild(b);
        return li;
    }
    function renderList() {
        list.textContent = '';
        if (!S.rows.length) {
            list.appendChild(el('li', 'tw-items__empty', S.f === 'todo' && !S.q ? tx('all_done') : tx('empty')));
        }
        S.rows.forEach(function (row, i) { list.appendChild(renderItem(row, i)); });
        var from = S.total ? (S.p - 1) * S.per + 1 : 0;
        pageLabel.textContent = tx('range', { from: from, to: Math.min(S.total, S.p * S.per), n: S.total });
    }
    function refreshItem(i) {
        var old = list.children[i];
        if (old && S.rows[i]) { list.replaceChild(renderItem(S.rows[i], i), old); }
    }
    list.addEventListener('click', function (e) {
        var b = e.target.closest('[data-i]');
        if (b) { select(parseInt(b.getAttribute('data-i'), 10), true); }
    });

    // ------------------------------------------------------------------ éditeur
    function select(i, focus) {
        if (i < 0 || i >= S.rows.length) { return; }
        var prev = S.i;
        S.i = i;
        if (prev >= 0) { refreshItem(prev); }
        refreshItem(i);
        renderEditor(S.rows[i], focus);
        var act = list.children[i];
        if (act && act.scrollIntoView) { act.scrollIntoView({ block: 'nearest' }); }
        if (focus && narrow()) { editor.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }
    function next() {
        if (S.i + 1 < S.rows.length) { select(S.i + 1, true); return; }
        if (S.p < S.pages) { S.p += 1; load(0); return; }
        editor.textContent = '';
        var done = el('div', 'tw-empty');
        done.appendChild(el('p', null, tx('all_done')));
        editor.appendChild(done);
    }
    function prevRow() { if (S.i > 0) { select(S.i - 1, true); } else if (S.p > 1) { S.p -= 1; load(-1); } }

    function renderEditor(row, focus) {
        editor.textContent = '';
        var keyed = row.kind === 'key';
        var globalIndex = (S.p - 1) * S.per + S.i + 1;

        var head = el('div', 'tw-ed__head');
        head.appendChild(el('span', 'tw-ed__pos', tx('position', { i: globalIndex, n: S.total })));
        head.appendChild(el('span', 'tw-badge is-' + itemState(row), row.tr ? tx('badge_done') : (row.sugg.length ? tx('badge_sugg') : tx('badge_todo'))));
        if (row.key) { head.appendChild(el('code', 'tw-key', row.key)); }
        if (row.code) { head.appendChild(el('span', 'ctb-tag', tx('code'))); }
        var nav = el('span', 'tw-ed__nav');
        nav.appendChild(btn('tw-iconbtn', '↑', prevRow));
        nav.appendChild(btn('tw-iconbtn', '↓', next));
        head.appendChild(nav);
        editor.appendChild(head);

        var src = el('div', 'tw-src');
        src.appendChild(el('p', 'tw-label', tx('original')));
        var fr = el('p', 'tw-src__text');
        fr.lang = 'fr';
        fr.appendChild(highlighted(row.fr, keyed));
        src.appendChild(fr);
        editor.appendChild(src);

        var ta = el('textarea', 'tw-input');
        if (row.en && row.en !== row.fr) {
            var ref = el('div', 'tw-ref');
            ref.appendChild(el('p', 'tw-label', IS_EN ? tx('reference_current') : tx('reference')));
            var en = el('p', 'tw-ref__text');
            en.lang = 'en';
            en.appendChild(highlighted(row.en, keyed));
            ref.appendChild(en);
            if (LOGGED) { ref.appendChild(btn('tw-mini', tx('copy_en'), function () { ta.value = row.en; ta.dispatchEvent(new Event('input')); ta.focus(); })); }
            editor.appendChild(ref);
        }
        if (row.memory) {
            var mem = el('div', 'tw-memory');
            mem.appendChild(el('p', 'tw-label', tx('memory')));
            mem.appendChild(el('p', 'tw-memory__text', row.memory));
            if (LOGGED) { mem.appendChild(btn('tw-mini', tx('use'), function () { ta.value = row.memory; ta.dispatchEvent(new Event('input')); ta.focus(); })); }
            editor.appendChild(mem);
        }

        var yours = el('div', 'tw-yours');
        yours.appendChild(el('p', 'tw-label', IS_EN ? tx('yours_en') : tx('yours')));
        var toks = tokens(row);
        var chips = null;
        if (toks.length) {
            chips = el('div', 'tw-chips');
            chips.appendChild(el('span', 'tw-chips__t', tx('tokens')));
            toks.forEach(function (t) {
                var c = btn('tw-chip', t, function () {
                    if (!LOGGED) { return; }
                    var a = ta.selectionStart || ta.value.length;
                    var z = ta.selectionEnd || a;
                    ta.value = ta.value.slice(0, a) + t + ta.value.slice(z);
                    ta.focus();
                    ta.setSelectionRange(a + t.length, a + t.length);
                    ta.dispatchEvent(new Event('input'));
                });
                c.setAttribute('data-tok', t);
                chips.appendChild(c);
            });
            yours.appendChild(chips);
        }
        ta.value = S.drafts[row.h] !== undefined ? S.drafts[row.h] : row.tr;
        ta.placeholder = LOGGED ? tx('placeholder') : tx('login_hint');
        ta.disabled = !LOGGED;
        ta.setAttribute('aria-label', tx('input_label'));
        ta.lang = root.getAttribute('data-lang') || '';
        ta.spellcheck = true;
        yours.appendChild(ta);
        var foot = el('div', 'tw-yours__foot');
        var counter = el('span', 'tw-counter');
        var state = el('span', 'tw-state');
        foot.appendChild(state);
        foot.appendChild(counter);
        yours.appendChild(foot);
        editor.appendChild(yours);

        function autosize() { ta.style.height = 'auto'; ta.style.height = Math.min(360, Math.max(96, ta.scrollHeight + 2)) + 'px'; }
        function live() {
            autosize();
            var v = ta.value;
            counter.textContent = tx('chars', { n: v.length, m: row.fr.length });
            if (v !== row.tr) { S.drafts[row.h] = v; } else { delete S.drafts[row.h]; }
            if (chips) {
                var missing = [];
                chips.querySelectorAll('[data-tok]').forEach(function (c) {
                    var has = v.indexOf(c.getAttribute('data-tok')) >= 0;
                    c.classList.toggle('is-ok', has);
                    if (!has) { missing.push(c.getAttribute('data-tok')); }
                });
                if (v.trim()) {
                    state.textContent = missing.length ? tx('missing', { list: missing.join(' ') }) : tx('check_ok');
                    state.className = 'tw-state ' + (missing.length ? 'is-bad' : 'is-ok');
                } else { state.textContent = ''; state.className = 'tw-state'; }
            } else if (v !== row.tr && v.trim()) {
                state.textContent = tx('unsaved'); state.className = 'tw-state';
            } else if (!state.classList.contains('is-ok')) { state.textContent = ''; }
        }
        ta.addEventListener('input', live);

        var actions = el('div', 'tw-actions');
        var save = null;
        if (LOGGED) {
            save = btn('ctb-btn ctb-btn--primary', S.review ? tx('save_next') : tx('suggest_next'), function () { submit(); });
            actions.appendChild(save);
            actions.appendChild(btn('ctb-btn', tx('skip'), next));
            if (S.review && row.tr) {
                actions.appendChild(btn('ctb-btn ctb-btn--ghost', tx('clear'), function () {
                    if (!window.confirm(tx('clear_confirm'))) { return; }
                    post(API + '/effacer', { h: row.h }).then(function (res) {
                        if (res && res.ok) {
                            row.tr = ''; ta.value = ''; delete S.drafts[row.h];
                            adjustCount('done', -1); adjustCount('todo', 1); bumpProgress(-1);
                            refreshItem(S.i); live(); state.textContent = tx('cleared'); state.className = 'tw-state';
                        }
                    });
                }));
            }
        } else {
            var login = el('p', 'ctb-msg', tx('login_hint'));
            actions.appendChild(login);
        }
        editor.appendChild(actions);

        function submit() {
            var text = ta.value;
            var errs = check(row, text);
            if (errs.length) { state.textContent = errs.map(function (e) { return tx('err_' + e); }).join(' '); state.className = 'tw-state is-bad'; ta.focus(); return; }
            if (text === row.tr) { next(); return; }
            save.disabled = true;
            post(API, { h: row.h, text: text }).then(function (res) {
                save.disabled = false;
                if (res && res.ok) {
                    delete S.drafts[row.h];
                    if (res.mode === 'saved') {
                        if (!row.tr) { adjustCount('done', 1); adjustCount('todo', -1); bumpProgress(1); }
                        row.tr = text;
                    } else {
                        if (!row.sugg.some(function (s) { return s.mine; })) { adjustCount('mine', 1); adjustCount('suggested', row.sugg.length ? 0 : 1); }
                        row.sugg = row.sugg.filter(function (s) { return !s.mine; }).concat([{ id: 0, text: text, author: '', mine: true }]);
                    }
                    refreshItem(S.i);
                    next();
                } else {
                    state.textContent = ((res && res.errors) || ['failed']).map(function (e) { return tx('err_' + e); }).join(' ');
                    state.className = 'tw-state is-bad';
                }
            }).catch(function () { save.disabled = false; state.textContent = tx('err_failed'); state.className = 'tw-state is-bad'; });
        }
        ta.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && save) { e.preventDefault(); submit(); }
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'E' || e.key === 'e') && row.en) { e.preventDefault(); ta.value = row.en; live(); }
        });

        if (row.sugg.length) {
            var sg = el('div', 'tw-sugg');
            sg.appendChild(el('p', 'tw-label', tx('suggestions') + ' · ' + row.sugg.length));
            var ul = el('ul', 'tw-sugg__list');
            row.sugg.forEach(function (s) {
                var item = el('li', 'tw-sugg__item');
                item.appendChild(el('p', 'tw-sugg__text', s.text));
                var meta = el('div', 'tw-sugg__meta');
                meta.appendChild(el('span', null, s.mine ? tx('sugg_mine') : tx('sugg_by', { name: s.author })));
                if (LOGGED) { meta.appendChild(btn('tw-mini', tx('reuse'), function () { ta.value = s.text; live(); ta.focus(); })); }
                if (S.review && s.id) {
                    ['accept', 'reject'].forEach(function (a) {
                        meta.appendChild(btn('tw-mini' + (a === 'accept' ? ' is-primary' : ''), tx(a), function () {
                            post(DECIDE + '/' + s.id, { action: a }).then(function (res) {
                                if (res && res.ok) {
                                    item.remove();
                                    row.sugg = row.sugg.filter(function (x) { return x.id !== s.id; });
                                    if (!row.sugg.length) { adjustCount('suggested', -1); }
                                    if (a === 'accept') {
                                        if (!row.tr) { adjustCount('done', 1); adjustCount('todo', -1); bumpProgress(1); }
                                        row.tr = s.text; ta.value = s.text; live();
                                    }
                                    refreshItem(S.i);
                                } else {
                                    state.textContent = ((res && res.errors) || ['failed']).map(function (e) { return tx('err_' + e); }).join(' ');
                                    state.className = 'tw-state is-bad';
                                }
                            });
                        }));
                    });
                }
                item.appendChild(meta);
                ul.appendChild(item);
            });
            sg.appendChild(ul);
            editor.appendChild(sg);
        }
        live();
        if (focus && LOGGED && !narrow()) { ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length); }
    }

    // ------------------------------------------------------------------ chargement
    function load(selectIndex) {
        var seq = ++S.seq;
        list.setAttribute('aria-busy', 'true');
        var url = API + '?q=' + encodeURIComponent(S.q) + '&f=' + encodeURIComponent(S.f) + '&p=' + S.p;
        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
            .then(function (data) {
                if (seq !== S.seq) { return; }
                S.pages = data.pages || 1;
                S.p = data.page || 1;
                S.per = data.per_page || S.per;
                S.total = data.total || 0;
                S.review = !!data.can_review;
                S.rows = data.rows || [];
                S.i = -1;
                setCounts(data.counts);
                renderList();
                list.setAttribute('aria-busy', 'false');
                if (S.rows.length) {
                    select(selectIndex === -1 ? S.rows.length - 1 : (selectIndex || 0), selectIndex !== undefined);
                } else {
                    editor.textContent = '';
                    var empty = el('div', 'tw-empty');
                    empty.appendChild(el('p', null, S.f === 'todo' && !S.q ? tx('all_done') : tx('empty')));
                    editor.appendChild(empty);
                }
            })
            .catch(function () {
                if (seq !== S.seq) { return; }
                list.textContent = '';
                list.appendChild(el('li', 'tw-items__empty', tx('load_error')));
                list.setAttribute('aria-busy', 'false');
            });
    }

    var timer = null;
    bar.q.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { S.q = bar.q.value.trim(); S.p = 1; load(); }, 300);
    });
    bar.addEventListener('submit', function (e) { e.preventDefault(); S.q = bar.q.value.trim(); S.p = 1; load(); });
    tabs.addEventListener('click', function (e) {
        var b = e.target.closest('[data-f]');
        if (!b) { return; }
        tabs.querySelectorAll('[data-f]').forEach(function (x) { var on = x === b; x.classList.toggle('is-active', on); x.setAttribute('aria-selected', on ? 'true' : 'false'); });
        S.f = b.getAttribute('data-f'); S.p = 1; load();
    });
    document.getElementById('sitetr-pager').addEventListener('click', function (e) {
        var b = e.target.closest('[data-page]');
        if (!b) { return; }
        var to = S.p + parseInt(b.getAttribute('data-page'), 10);
        if (to < 1 || to > S.pages) { return; }
        S.p = to;
        load(0);
    });
    document.addEventListener('keydown', function (e) {
        if (!e.altKey || (e.key !== 'ArrowDown' && e.key !== 'ArrowUp')) { return; }
        e.preventDefault();
        if (e.key === 'ArrowDown') { next(); } else { prevRow(); }
    });
    window.addEventListener('beforeunload', function (e) {
        if (Object.keys(S.drafts).length) { e.preventDefault(); e.returnValue = ''; }
    });
    load();
})();
