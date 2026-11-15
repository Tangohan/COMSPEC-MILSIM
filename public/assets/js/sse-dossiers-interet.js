/*
 * Dossiers d'intérêt du bureau SSE : modales <dialog> (création en étapes avec brouillon automatique, modification, état,
 * suppression), onglets de la fiche, tiroir d'aperçu de la file, recherche d'éléments à lier.
 * Aucun libellé dans ce fichier : les textes viennent du bloc caché [data-di-labels] rendu par la vue (donc traduit avec la page).
 * Sans JavaScript, les liens d'onglets et les formulaires fonctionnent encore ; les modales demandent JavaScript.
 */
(function () {
    'use strict';
    var root = document.querySelector('[data-di-root]');
    if (!root) { return; }

    function label(key) {
        var el = root.querySelector('[data-di-labels] [data-k="' + key + '"]');
        return el ? (el.textContent || '').trim() : '';
    }
    function notify(kind, text) {
        if (window.SseNotify && typeof window.SseNotify[kind] === 'function') { window.SseNotify[kind](text); }
    }
    function qs(sel, ctx) { return (ctx || root).querySelector(sel); }
    function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); }
    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text != null) { n.textContent = text; }
        return n;
    }
    function stripParam(name) {
        try {
            var u = new URL(window.location.href);
            if (u.searchParams.has(name)) {
                u.searchParams.delete(name);
                window.history.replaceState(null, '', u.pathname + (u.search || '') + u.hash);
            }
        } catch (e) { /* ancien navigateur */ }
    }

    /* ---------- Modales ---------- */
    function openDialog(dlg) {
        if (!dlg) { return; }
        if (typeof dlg.showModal === 'function') {
            if (!dlg.open) { dlg.showModal(); }
        } else {
            dlg.setAttribute('open', '');
        }
        var first = dlg.querySelector('.is-invalid input, .is-invalid select, .is-invalid textarea') || dlg.querySelector('input:not([type=hidden]), select, textarea');
        if (first) { try { first.focus({ preventScroll: true }); } catch (e) { /* rien */ } }
    }
    function closeDialog(dlg) {
        if (dlg && dlg.open) { dlg.close(); }
    }
    root.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-di-open]');
        if (opener) {
            e.preventDefault();
            openDialog(document.getElementById(opener.getAttribute('data-di-open')));
            return;
        }
        var closer = e.target.closest('[data-di-close]');
        if (closer) {
            e.preventDefault();
            closeDialog(closer.closest('dialog'));
            return;
        }
        if (e.target.tagName === 'DIALOG' && e.target.hasAttribute('data-di-dialog')) {
            var r = e.target.getBoundingClientRect();
            if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) { closeDialog(e.target); }
        }
    });

    /* ---------- Mise en forme (gras, italique, liste) ---------- */
    root.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-di-fmt]');
        if (!btn) { return; }
        var field = btn.closest('.di-field');
        var ta = field && field.querySelector('textarea');
        if (!ta) { return; }
        e.preventDefault();
        var kind = btn.getAttribute('data-di-fmt');
        var s = ta.selectionStart, t = ta.selectionEnd, v = ta.value, sel = v.slice(s, t);
        var out;
        if (kind === 'list') {
            var start = v.lastIndexOf('\n', s - 1) + 1;
            var end = t;
            var block = v.slice(start, end || start);
            var lines = (block === '' ? [''] : block.split('\n')).map(function (l) { return /^\s*-\s/.test(l) ? l : '- ' + l; });
            out = v.slice(0, start) + lines.join('\n') + v.slice(Math.max(end, start));
            ta.value = out;
            ta.selectionStart = start; ta.selectionEnd = start + lines.join('\n').length;
        } else {
            var mark = kind === 'bold' ? '**' : '*';
            out = v.slice(0, s) + mark + sel + mark + v.slice(t);
            ta.value = out;
            ta.selectionStart = s + mark.length; ta.selectionEnd = t + mark.length;
        }
        ta.focus();
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    });

    /* ---------- Erreurs de champ ---------- */
    function setError(field, message) {
        if (!field) { return; }
        var old = field.querySelector('.di-error[data-js]');
        if (old) { old.remove(); }
        field.classList.toggle('is-invalid', !!message);
        var ctl = field.querySelector('input, select, textarea');
        if (ctl) { if (message) { ctl.setAttribute('aria-invalid', 'true'); } else { ctl.removeAttribute('aria-invalid'); } }
        if (message) {
            var p = el('p', 'di-error');
            p.setAttribute('data-js', '1');
            p.setAttribute('role', 'alert');
            p.appendChild(el('span', null, '!'));
            p.appendChild(el('span', null, message));
            field.appendChild(p);
        }
    }
    root.addEventListener('input', function (e) {
        var f = e.target.closest('.di-field.is-invalid');
        if (f && !f.querySelector('.di-error:not([data-js])')) { setError(f, ''); }
    });

    /* ---------- Création : étapes, brouillon ---------- */
    var create = document.getElementById('di-create');
    if (create) {
        var form = qs('form', create);
        var steps = qsa('[data-di-step]', create);
        var marks = qsa('[data-di-stepmark]', create);
        var cur = 1, total = steps.length || 1;
        var btnNext = qs('[data-di-next]', create), btnPrev = qs('[data-di-prev]', create), btnSubmit = qs('[data-di-submit]', create), btnDraft = qs('[data-di-draftbtn]', create);
        var go = function (n) {
            cur = Math.max(1, Math.min(total, n));
            steps.forEach(function (s) { s.hidden = Number(s.getAttribute('data-di-step')) !== cur; });
            marks.forEach(function (m) {
                var k = Number(m.getAttribute('data-di-stepmark'));
                m.classList.toggle('is-current', k === cur);
                m.classList.toggle('is-done', k < cur);
            });
            btnPrev.hidden = cur === 1;
            btnNext.hidden = cur === total;
            btnSubmit.hidden = cur !== total;
            var body = qs('.di-dialog__body', create);
            if (body) { body.scrollTop = 0; }
        };
        var checkStep = function (n) {
            var bad = null;
            if (n === 1) {
                var title = qs('[name=temporary_designation]', create), reason = qs('[name=opening_reason]', create);
                if (title.value.trim().length < 3) { setError(title.closest('.di-field'), label('required')); bad = bad || title; }
                if (reason.value.trim().length < 10) { setError(reason.closest('.di-field'), label('required')); bad = bad || reason; }
            }
            return bad;
        };
        btnNext.addEventListener('click', function () {
            var bad = checkStep(cur);
            if (bad) { bad.focus(); notify('error', label('required')); return; }
            go(cur + 1);
        });
        btnPrev.addEventListener('click', function () { go(cur - 1); });
        form.addEventListener('submit', function (e) {
            var isDraft = e.submitter && e.submitter.hasAttribute('data-di-draftbtn');
            var title = qs('[name=temporary_designation]', create);
            var bad = null;
            if (title.value.trim().length < 3) { setError(title.closest('.di-field'), label('required')); bad = title; go(1); }
            if (!isDraft) {
                var reason = qs('[name=opening_reason]', create);
                if (!bad && reason.value.trim().length < 10) { setError(reason.closest('.di-field'), label('required')); bad = reason; go(1); }
                var sign = qs('[name=digital_signature]', create);
                if (!bad && !sign.checked) { setError(sign.closest('.di-check'), label('required')); bad = sign; go(total); }
            }
            if (bad) { e.preventDefault(); bad.focus(); notify('error', label('required')); return; }
            clearDraft();
        });
        create.addEventListener('close', function () { go(1); });

        /* brouillon automatique (navigateur) */
        var key = create.getAttribute('data-di-draft-key') || 'di-draft';
        var fields = ['temporary_designation', 'opening_reason', 'case_type', 'interest_level', 'classification', 'assignee_user_id', 'due_at', 'tags', 'suspected_alias', 'suspected_affiliation', 'confidence_level', 'description'];
        var bar = qs('[data-di-draftbar]', create);
        var timer = 0;
        var read = function () { try { var raw = window.localStorage.getItem(key); return raw ? JSON.parse(raw) : null; } catch (e) { return null; } };
        function clearDraft() { try { window.localStorage.removeItem(key); } catch (e) { /* rien */ } if (bar) { bar.hidden = true; } }
        var save = function () {
            var data = {}, any = false;
            fields.forEach(function (f) {
                var c = form.elements[f];
                if (c && c.value) { data[f] = c.value; if (['interest_level', 'confidence_level', 'classification'].indexOf(f) < 0) { any = true; } }
            });
            try { if (any) { window.localStorage.setItem(key, JSON.stringify(data)); } else { window.localStorage.removeItem(key); } } catch (e) { /* stockage indisponible */ }
        };
        form.addEventListener('input', function () { window.clearTimeout(timer); timer = window.setTimeout(save, 600); });
        var draft = read();
        var hasServerValues = !!(qs('[name=temporary_designation]', create).value);
        if (draft && bar && !hasServerValues) { bar.hidden = false; }
        var restore = qs('[data-di-draft-restore]', create), clear = qs('[data-di-draft-clear]', create);
        if (restore) {
            restore.addEventListener('click', function () {
                var d = read() || {};
                fields.forEach(function (f) { var c = form.elements[f]; if (c && d[f] != null) { c.value = d[f]; } });
                bar.hidden = true;
            });
        }
        if (clear) { clear.addEventListener('click', clearDraft); }
        // Erreurs du serveur : on ouvre l'étape qui porte le premier champ en erreur.
        var firstBad = qs('.is-invalid', create);
        if (firstBad) {
            var st = firstBad.closest('[data-di-step]');
            if (st) { go(Number(st.getAttribute('data-di-step'))); }
        } else { go(1); }
        if (new URLSearchParams(window.location.search).get('creer') === '1') { openDialog(create); stripParam('creer'); }
    }

    /* ---------- État : motif obligatoire selon la transition ---------- */
    var statusDlg = document.getElementById('di-status');
    if (statusDlg) {
        var flag = qs('[data-di-reason-flag]', statusDlg), reasonBox = qs('[name=reason]', statusDlg);
        var refresh = function () {
            var r = qs('input[name=status]:checked', statusDlg);
            var need = !!r && r.getAttribute('data-needs-reason') === '1';
            if (flag) { flag.textContent = r ? '(' + label(need ? 'reasonreq' : 'reasonopt') + ')' : ''; }
            if (reasonBox) { reasonBox.required = need; }
        };
        statusDlg.addEventListener('change', refresh);
        refresh();
    }

    /* ---------- Suppression définitive : référence à retaper ---------- */
    var purgeInput = document.querySelector('[data-di-confirm]');
    if (purgeInput) {
        var purgeBtn = document.querySelector('[data-di-confirm-submit]');
        var sync = function () { if (purgeBtn && !purgeInput.disabled) { purgeBtn.disabled = purgeInput.value.trim().toLowerCase() !== purgeInput.getAttribute('data-di-confirm').toLowerCase(); } };
        purgeInput.addEventListener('input', sync);
        sync();
    }

    /* ---------- Retrait d'un lien ---------- */
    root.addEventListener('click', function (e) {
        var b = e.target.closest('[data-di-unlink]');
        if (!b) { return; }
        var dlg = document.getElementById('di-unlink');
        if (!dlg) { return; }
        var f = qs('form', dlg);
        f.action = f.getAttribute('data-di-unlink-base') + encodeURIComponent(b.getAttribute('data-di-unlink')) + '/retirer';
        var l = qs('[data-di-unlink-label]', dlg);
        if (l) { l.textContent = b.getAttribute('data-di-unlink-name') || ''; }
        openDialog(dlg);
    });

    /* ---------- Ouverture automatique (erreurs renvoyées par le serveur) ---------- */
    qsa('dialog[data-di-autoopen]').forEach(function (d) { openDialog(d); });
    ['modifier', 'etat', 'supprimer'].forEach(function (p) {
        var v = new URLSearchParams(window.location.search).get(p);
        if (v === '1') {
            var id = { modifier: 'di-edit', etat: 'di-status', supprimer: document.getElementById('di-purge') ? 'di-purge' : 'di-trash' }[p];
            openDialog(document.getElementById(id));
            stripParam(p);
        }
    });

    /* ---------- Onglets de la fiche ---------- */
    var tabsBar = qs('[data-di-tabs]');
    if (tabsBar) {
        var tabs = qsa('[data-di-tab]', tabsBar);
        var panes = qsa('.di-pane');
        var show = function (key, push) {
            tabs.forEach(function (t) {
                var on = t.getAttribute('data-di-tab') === key;
                t.classList.toggle('is-active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.tabIndex = on ? 0 : -1;
            });
            panes.forEach(function (p) { p.hidden = p.id !== 'di-panel-' + key; });
            if (push) {
                try {
                    var u = new URL(window.location.href);
                    u.searchParams.set('onglet', key);
                    window.history.replaceState(null, '', u.pathname + u.search + u.hash);
                } catch (e) { /* rien */ }
            }
        };
        tabs.forEach(function (t, i) {
            t.addEventListener('click', function (e) {
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) { return; }
                e.preventDefault();
                show(t.getAttribute('data-di-tab'), true);
            });
            t.addEventListener('keydown', function (e) {
                var n = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : e.key === 'Home' ? 0 : e.key === 'End' ? tabs.length - 1 : null;
                if (n === null) { return; }
                e.preventDefault();
                var next = tabs[(n + tabs.length) % tabs.length];
                next.focus();
                show(next.getAttribute('data-di-tab'), true);
            });
        });
        var active = tabs.filter(function (t) { return t.classList.contains('is-active'); })[0];
        if (active) { show(active.getAttribute('data-di-tab'), false); }
    }

    /* ---------- Filtres (téléphone) ---------- */
    var tb = qs('[data-di-toolbar]'), tbBtn = qs('[data-di-filter-toggle]');
    if (tb && tbBtn) {
        tbBtn.addEventListener('click', function () {
            var open = tb.hasAttribute('data-open');
            if (open) { tb.removeAttribute('data-open'); } else { tb.setAttribute('data-open', ''); }
            tbBtn.setAttribute('aria-expanded', open ? 'false' : 'true');
        });
    }

    /* ---------- Tiroir d'aperçu ---------- */
    var drawer = qs('[data-di-drawer]'), scrim = qs('[data-di-scrim]');
    if (drawer) {
        var base = root.getAttribute('data-preview-base');
        var body = qs('[data-di-d-body]', drawer), openLink = qs('[data-di-d-open]', drawer);
        var lastFocus = null, ctrl = null;
        var closeDrawer = function () {
            drawer.hidden = true; scrim.hidden = true;
            qsa('tr.is-selected').forEach(function (r) { r.classList.remove('is-selected'); });
            if (ctrl) { ctrl.abort(); ctrl = null; }
            if (lastFocus) { try { lastFocus.focus(); } catch (e) { /* rien */ } }
        };
        var fact = function (dl, k, v, cls) {
            var d = el('div'); d.appendChild(el('dt', null, label(k)));
            var dd = el('dd', cls || null);
            if (v instanceof Node) { dd.appendChild(v); } else { dd.textContent = v || label('none'); }
            d.appendChild(dd); dl.appendChild(d);
        };
        var render = function (c) {
            qs('[data-di-d-title]', drawer).textContent = c.title;
            qs('[data-di-d-ref]', drawer).textContent = c.reference + (c.is_deleted ? ' · corbeille' : '');
            body.textContent = '';
            var badges = el('div', 'di-hero__badges');
            var st = el('span', 'di-status', c.status_label); badges.appendChild(st);
            badges.appendChild(el('span', 'di-prio di-prio--' + String(c.priority_code).toLowerCase(), c.priority_code + ' ' + c.priority_label));
            badges.appendChild(el('span', 'di-tag di-tag--class di-tag--class-' + c.classification, c.classification_label));
            body.appendChild(badges);
            var dl = el('dl', 'di-facts di-facts--1');
            fact(dl, 'type', c.type_label);
            fact(dl, 'assignee', c.assignee_label);
            var due = c.due_at ? new Date(String(c.due_at).replace(' ', 'T')) : null;
            var dueTxt = due && !isNaN(due) ? due.toLocaleDateString() + (c.is_overdue ? ' · ' + label('late') : '') : '';
            fact(dl, 'due', dueTxt, c.is_overdue ? 'di-late' : '');
            fact(dl, 'links', String(c.links_count));
            body.appendChild(dl);
            if (c.tags && c.tags.length) {
                var tg = el('div', 'di-tags'); c.tags.forEach(function (t) { tg.appendChild(el('span', 'di-tag', '#' + t)); }); body.appendChild(tg);
            }
            [['reason', c.reason_html], ['description', c.description_html]].forEach(function (p) {
                if (!p[1]) { return; }
                var sec = el('div'); sec.appendChild(el('h3', null, label(p[0])));
                var rich = el('div', 'di-rich'); rich.innerHTML = p[1]; /* HTML assaini par le serveur (SseInterestMarkup) */
                sec.appendChild(rich); body.appendChild(sec);
            });
            openLink.href = base + '/' + c.id;
        };
        root.addEventListener('click', function (e) {
            var a = e.target.closest('[data-di-preview]');
            if (a) {
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) { return; }
                e.preventDefault();
                var id = a.getAttribute('data-di-preview');
                lastFocus = a;
                qsa('tr.is-selected').forEach(function (r) { r.classList.remove('is-selected'); });
                var tr = a.closest('tr'); if (tr) { tr.classList.add('is-selected'); }
                drawer.hidden = false; scrim.hidden = false;
                body.textContent = ''; body.appendChild(el('p', 'di-sub', label('searching')));
                openLink.href = base + '/' + id;
                qs('[data-di-d-title]', drawer).textContent = a.querySelector('.di-row__title').textContent;
                qs('[data-di-d-ref]', drawer).textContent = '';
                if (ctrl) { ctrl.abort(); }
                ctrl = window.AbortController ? new AbortController() : null;
                fetch(base + '/' + encodeURIComponent(id) + '/apercu', { credentials: 'same-origin', headers: { 'Accept': 'application/json' }, signal: ctrl ? ctrl.signal : undefined })
                    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                    .then(function (x) {
                        if (!x.ok || !x.j.ok) { body.textContent = ''; body.appendChild(el('p', 'di-error', x.j && x.j.message ? x.j.message : label('error'))); return; }
                        render(x.j.case);
                    })
                    .catch(function (err) {
                        if (err && err.name === 'AbortError') { return; }
                        body.textContent = ''; body.appendChild(el('p', 'di-error', label('error')));
                    });
                var close = qs('[data-di-drawer-close]', drawer); if (close) { close.focus(); }
                return;
            }
            if (e.target.closest('[data-di-drawer-close]') || e.target === scrim) { closeDrawer(); }
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !drawer.hidden && !document.querySelector('dialog[open]')) { closeDrawer(); } });
        var wanted = Number(root.getAttribute('data-open-case') || 0);
        if (wanted > 0) { var t = qs('[data-di-preview="' + wanted + '"]'); if (t) { t.click(); } }
    }

    /* ---------- Recherche d'éléments à lier ---------- */
    qsa('[data-di-picker]').forEach(function (pk) {
        var multi = pk.getAttribute('data-di-picker') === 'multi';
        var typeSel = qs('[data-di-picker-type]', pk), q = qs('[data-di-picker-q]', pk), list = qs('[data-di-picker-results]', pk);
        var free = qs('[data-di-picker-free]', pk), freeIn = qs('[data-di-picker-label]', pk), searchBox = qs('[data-di-picker-searchbox]', pk);
        var picked = qs('[data-di-picker-picked]', pk), inputs = qs('[data-di-picker-inputs]', pk);
        var target = qs('[data-di-picker-target]', pk), labelOut = qs('[data-di-picker-labelout]', pk);
        var url = pk.getAttribute('data-search-url'), timer = 0, ctl = null, count = 0;
        var isVehicle = function () { return typeSel.value === 'vehicle'; };
        var applyType = function () {
            var v = isVehicle();
            free.hidden = !v; searchBox.hidden = v; list.hidden = true; list.textContent = '';
            if (!multi) { target.value = ''; labelOut.value = ''; picked.textContent = ''; }
        };
        var chip = function (text, onRemove) {
            var c = el('span', 'di-tag', text);
            var x = el('button', null, '×'); x.type = 'button'; x.setAttribute('aria-label', label('remove') + ' ' + text);
            x.addEventListener('click', function () { c.remove(); onRemove(); });
            c.appendChild(x); picked.appendChild(c);
        };
        var pick = function (type, id, text) {
            if (multi) {
                if (count >= 10) { return; }
                var value = type + ':' + id;
                if (qs('input[value="' + value.replace(/"/g, '') + '"]', inputs)) { return; }
                var inp = document.createElement('input'); inp.type = 'hidden'; inp.name = 'pending_links[]'; inp.value = value; inputs.appendChild(inp);
                count++;
                chip(typeSel.options[typeSel.selectedIndex].text + ' · ' + text, function () { inp.remove(); count--; });
            } else {
                target.value = id; labelOut.value = text; picked.textContent = '';
                chip(typeSel.options[typeSel.selectedIndex].text + ' · ' + text, function () { target.value = ''; labelOut.value = ''; });
            }
            list.hidden = true; list.textContent = ''; q.value = '';
        };
        var run = function () {
            var term = q.value.trim();
            if (term.length < 2) { list.hidden = true; return; }
            if (ctl) { ctl.abort(); }
            ctl = window.AbortController ? new AbortController() : null;
            list.hidden = false; list.textContent = ''; list.appendChild(el('li', 'is-empty', label('searching')));
            fetch(url + '?type=' + encodeURIComponent(typeSel.value) + '&q=' + encodeURIComponent(term), { credentials: 'same-origin', headers: { 'Accept': 'application/json' }, signal: ctl ? ctl.signal : undefined })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (x) {
                    list.textContent = '';
                    if (!x.ok || !x.j.ok) { list.appendChild(el('li', 'is-empty', (x.j && x.j.message) || label('error'))); return; }
                    if (!x.j.results.length) { list.appendChild(el('li', 'is-empty', label('noresult'))); return; }
                    x.j.results.forEach(function (r) {
                        var li = el('li'), b = el('button'); b.type = 'button';
                        b.appendChild(el('strong', null, r.label)); if (r.hint) { b.appendChild(el('span', 'di-sub', r.hint)); }
                        b.addEventListener('click', function () { pick(typeSel.value, r.id, r.label); });
                        li.appendChild(b); list.appendChild(li);
                    });
                })
                .catch(function (err) { if (!err || err.name !== 'AbortError') { list.textContent = ''; list.appendChild(el('li', 'is-empty', label('error'))); } });
        };
        typeSel.addEventListener('change', applyType);
        q.addEventListener('input', function () { window.clearTimeout(timer); timer = window.setTimeout(run, 250); });
        q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); run(); } });
        if (!multi && freeIn) { freeIn.addEventListener('input', function () { labelOut.value = freeIn.value; target.value = ''; }); }
        var addFree = qs('[data-di-picker-addfree]', pk);
        if (addFree) { addFree.addEventListener('click', function () { var t = freeIn.value.trim(); if (t.length >= 2) { pick('vehicle', t, t); freeIn.value = ''; } }); }
        applyType();
    });
})();
