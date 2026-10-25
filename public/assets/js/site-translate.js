/*
 * Espace de traduction du site par la communauté (/traduire-site/{code}).
 * Liste des textes page par page (GET data-api ?q=&f=&p=), enregistrement d'un texte (POST data-api {h, text}) :
 * validé directement pour un traducteur de la langue ou l'administration, sinon proposé. Un traducteur accepte ou
 * refuse les propositions (POST data-decide/{id} {action}). Mêmes contrôles que CommunityLanguages::check côté
 * serveur (valeurs %1, %2…, balises HTML gardées) pour prévenir avant l'envoi.
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
    var list = document.getElementById('sitetr-list');
    var bar = document.getElementById('sitetr-bar');
    var count = document.getElementById('sitetr-count');
    var pageLabel = document.getElementById('sitetr-page');
    var S = { q: '', f: 'todo', p: 1, pages: 1, review: root.getAttribute('data-review') === '1', seq: 0 };

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text !== undefined) { n.textContent = text; }
        return n;
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

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json().catch(function () { return { ok: false, errors: ['http_' + r.status] }; }); });
    }

    function renderRow(row) {
        var li = el('li', 'ctb-row' + (row.tr ? ' is-done' : '') + (row.sugg.length ? ' has-sugg' : ''));
        var src = el('div', 'ctb-row__src');
        var fr = el('p', 'ctb-row__fr', row.fr);
        fr.lang = 'fr';
        src.appendChild(fr);
        if (row.en && row.en !== row.fr) {
            var en = el('p', 'ctb-row__en', row.en);
            en.lang = 'en';
            src.appendChild(en);
        }
        var meta = el('p', 'ctb-row__meta');
        if (row.code) { meta.appendChild(el('span', 'ctb-tag', tx('code'))); }
        if (/%\d/.test(row.fr)) { meta.appendChild(el('span', 'ctb-tag', tx('values'))); }
        if (row.key) { meta.appendChild(el('span', 'ctb-tag ctb-tag--key', row.key)); }
        if (meta.childNodes.length) { src.appendChild(meta); }
        li.appendChild(src);

        var edit = el('div', 'ctb-row__edit');
        var ta = el('textarea', 'ctb-row__input');
        ta.rows = Math.min(6, Math.max(2, Math.ceil(row.fr.length / 70)));
        ta.value = row.tr;
        ta.placeholder = row.tr ? '' : tx('placeholder');
        ta.disabled = !LOGGED;
        ta.setAttribute('aria-label', tx('input_label'));
        edit.appendChild(ta);
        var state = el('p', 'ctb-row__state');
        var actions = el('div', 'ctb-row__actions');
        if (LOGGED) {
            var save = el('button', 'ctb-btn ctb-btn--primary', S.review ? tx('save') : tx('suggest'));
            save.type = 'button';
            save.addEventListener('click', function () {
                var text = ta.value;
                var errs = check(row, text);
                if (errs.length) { state.textContent = errs.map(function (e) { return tx('err_' + e); }).join(' '); state.className = 'ctb-row__state is-bad'; return; }
                save.disabled = true;
                post(API, { h: row.h, text: text }).then(function (res) {
                    save.disabled = false;
                    if (res && res.ok) {
                        state.textContent = res.mode === 'saved' ? tx('saved') : tx('suggested');
                        state.className = 'ctb-row__state is-ok';
                        if (res.mode === 'saved') { row.tr = text; li.classList.add('is-done'); }
                    } else {
                        state.textContent = ((res && res.errors) || ['failed']).map(function (e) { return tx('err_' + e); }).join(' ');
                        state.className = 'ctb-row__state is-bad';
                    }
                }).catch(function () { save.disabled = false; state.textContent = tx('err_failed'); state.className = 'ctb-row__state is-bad'; });
            });
            actions.appendChild(save);
            if (!row.tr && row.en && row.en !== row.fr) {
                var copy = el('button', 'ctb-btn', tx('copy_en'));
                copy.type = 'button';
                copy.addEventListener('click', function () { ta.value = row.en; ta.focus(); });
                actions.appendChild(copy);
            }
            if (S.review && row.tr) {
                var clear = el('button', 'ctb-btn ctb-btn--ghost', tx('clear'));
                clear.type = 'button';
                clear.addEventListener('click', function () {
                    if (!window.confirm(tx('clear_confirm'))) { return; }
                    post(API + '/effacer', { h: row.h }).then(function (res) {
                        if (res && res.ok) { row.tr = ''; ta.value = ''; li.classList.remove('is-done'); state.textContent = tx('cleared'); state.className = 'ctb-row__state'; }
                    });
                });
                actions.appendChild(clear);
            }
        }
        edit.appendChild(actions);
        edit.appendChild(state);

        if (row.sugg.length) {
            var sl = el('ul', 'ctb-sugg');
            row.sugg.forEach(function (s) {
                var item = el('li', 'ctb-sugg__item');
                item.appendChild(el('p', 'ctb-sugg__text', s.text));
                item.appendChild(el('p', 'ctb-sugg__by', s.mine ? tx('sugg_mine') : tx('sugg_by', { name: s.author })));
                if (S.review) {
                    ['accept', 'reject'].forEach(function (a) {
                        var b = el('button', 'ctb-btn' + (a === 'accept' ? ' ctb-btn--primary' : ' ctb-btn--ghost'), tx(a));
                        b.type = 'button';
                        b.addEventListener('click', function () {
                            post(DECIDE + '/' + s.id, { action: a }).then(function (res) {
                                if (res && res.ok) {
                                    item.remove();
                                    if (a === 'accept') { row.tr = s.text; ta.value = s.text; li.classList.add('is-done'); }
                                } else {
                                    state.textContent = ((res && res.errors) || ['failed']).map(function (e) { return tx('err_' + e); }).join(' ');
                                    state.className = 'ctb-row__state is-bad';
                                }
                            });
                        });
                        item.appendChild(b);
                    });
                }
                sl.appendChild(item);
            });
            edit.appendChild(sl);
        }
        li.appendChild(edit);
        return li;
    }

    function load() {
        var seq = ++S.seq;
        list.setAttribute('aria-busy', 'true');
        var url = API + '?q=' + encodeURIComponent(S.q) + '&f=' + encodeURIComponent(S.f) + '&p=' + S.p;
        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
            .then(function (data) {
                if (seq !== S.seq) { return; }
                S.pages = data.pages || 1;
                S.p = data.page || 1;
                S.review = !!data.can_review;
                list.textContent = '';
                (data.rows || []).forEach(function (row) { list.appendChild(renderRow(row)); });
                if (!(data.rows || []).length) { list.appendChild(el('li', 'ctb-row ctb-row--empty', tx('empty'))); }
                count.textContent = tx('count', { n: data.total || 0 });
                pageLabel.textContent = tx('page', { p: S.p, n: S.pages });
                list.setAttribute('aria-busy', 'false');
            })
            .catch(function () {
                if (seq !== S.seq) { return; }
                list.textContent = '';
                list.appendChild(el('li', 'ctb-row ctb-row--empty', tx('load_error')));
                list.setAttribute('aria-busy', 'false');
            });
    }

    var timer = null;
    bar.q.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { S.q = bar.q.value.trim(); S.p = 1; load(); }, 300);
    });
    bar.f.addEventListener('change', function () { S.f = bar.f.value; S.p = 1; load(); });
    bar.addEventListener('submit', function (e) { e.preventDefault(); S.q = bar.q.value.trim(); S.p = 1; load(); });
    document.getElementById('sitetr-pager').addEventListener('click', function (e) {
        var b = e.target.closest('[data-page]');
        if (!b) { return; }
        var next = S.p + parseInt(b.getAttribute('data-page'), 10);
        if (next < 1 || next > S.pages) { return; }
        S.p = next;
        load();
        root.querySelector('.ctb-ws').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    load();
})();
