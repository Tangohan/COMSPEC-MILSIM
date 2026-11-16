/**
 * Effectifs : fenêtre « Vérifier la disponibilité » (views/admin/effectifs_workspace/partials/availability_check_modal.php).
 *
 * Trois parties : Aide, Recherche (étapes lues une à une, avec leur état, leur source et leur date), Validation.
 * Un membre (fiche, ligne du tableur) ou une sélection du tableur (cases cochées, 25 au plus) : les étapes tournent pour
 * chaque membre l'un après l'autre. La fenêtre est un <dialog> modal : le focus reste dedans et Échap la ferme.
 * Les textes affichés viennent de la page (bloc [data-avc-texts]) ou du serveur : rien n'est écrit en dur ici.
 */
(function () {
    'use strict';

    var dlg = document.getElementById('eff-dlg-avc');
    if (!dlg || typeof dlg.showModal !== 'function') {
        return;
    }

    var T = {};
    dlg.querySelectorAll('[data-avc-texts] [data-t]').forEach(function (el) {
        T[el.getAttribute('data-t')] = el.textContent.trim();
    });
    var LABELS = {};
    var LABEL_KEYS = [];
    dlg.querySelectorAll('[data-avc-labels] [data-k]').forEach(function (el) {
        LABELS[el.getAttribute('data-k')] = el.textContent.trim();
        LABEL_KEYS.push(el.getAttribute('data-k'));
    });

    function t(key, values) {
        var s = T[key] || '';
        (values || []).forEach(function (v, i) {
            s = s.split('%' + (i + 1)).join(String(v));
        });
        return s;
    }

    function q(sel) {
        return dlg.querySelector(sel);
    }

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) {
            n.className = cls;
        }
        if (text !== undefined && text !== null) {
            n.textContent = text;
        }
        return n;
    }

    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var STEP_DELAY = reduced ? 0 : 260;
    var MAX = parseInt(dlg.getAttribute('data-avc-max'), 10) || 25;

    var state = { ids: [], names: {}, mode: 'single', results: {}, part: 'aide', saved: false, busy: false, token: 0 };

    function url(attr, id) {
        return dlg.getAttribute(attr).replace('__ID__', encodeURIComponent(String(id)));
    }

    function live(text) {
        var n = q('[data-avc-live]');
        if (n) {
            n.textContent = '';
            window.setTimeout(function () { n.textContent = text; }, 30);
        }
    }

    function showOnly(mode) {
        dlg.querySelectorAll('[data-avc-only]').forEach(function (n) {
            n.hidden = n.getAttribute('data-avc-only') !== mode;
        });
    }

    function setPart(part, focus) {
        state.part = part;
        dlg.querySelectorAll('[data-avc-part]').forEach(function (s) {
            s.hidden = s.getAttribute('data-avc-part') !== part;
        });
        var order = ['aide', 'recherche', 'validation'];
        dlg.querySelectorAll('[data-avc-goto]').forEach(function (b) {
            var p = b.getAttribute('data-avc-goto');
            if (p === part || (part === 'fin' && p === 'validation')) {
                b.setAttribute('aria-current', 'step');
            } else {
                b.removeAttribute('aria-current');
            }
            b.classList.toggle('is-done', order.indexOf(p) < order.indexOf(part === 'fin' ? 'validation' : part) || part === 'fin');
        });
        q('[data-avc-start]').hidden = part !== 'aide';
        q('[data-avc-next]').hidden = part !== 'recherche';
        q('[data-avc-submit]').hidden = part !== 'validation';
        q('[data-avc-back]').hidden = !(part === 'validation' || (part === 'aide' && researchDone()));
        if (focus) {
            var h = q('[data-avc-part="' + part + '"] h3');
            if (h) {
                h.setAttribute('tabindex', '-1');
                h.focus();
            }
        }
    }

    function researchDone() {
        return state.ids.length > 0 && state.ids.every(function (id) { return !!state.results[id]; });
    }

    function enableNav() {
        dlg.querySelector('[data-avc-goto="recherche"]').disabled = Object.keys(state.results).length === 0 && state.part === 'aide';
        dlg.querySelector('[data-avc-goto="validation"]').disabled = !researchDone() || state.saved;
    }

    function reset() {
        state.results = {};
        state.saved = false;
        state.busy = false;
        state.token++;
        q('[data-avc-list]').innerHTML = '';
        q('[data-avc-rows]').innerHTML = '';
        q('[data-avc-val-rows]').innerHTML = '';
        q('[data-avc-progress]').textContent = '';
        q('[data-avc-verdict]').hidden = true;
        q('[data-avc-error]').hidden = true;
        q('[data-avc-next]').disabled = true;
        q('[data-avc-done-notice]').hidden = true;
        q('[data-avc-position]').hidden = true;
        var form = q('[data-avc-form]');
        form.reset();
        dlg.querySelectorAll('[data-avc-goto]').forEach(function (b) {
            b.disabled = b.getAttribute('data-avc-goto') !== 'aide';
            b.classList.remove('is-done');
        });
    }

    function open(ids, names) {
        reset();
        state.ids = ids;
        state.names = names;
        state.mode = ids.length > 1 ? 'bulk' : 'single';
        showOnly(state.mode);
        q('[data-avc-subject]').textContent = state.mode === 'bulk' ? t('many', [ids.length]) : (names[ids[0]] || t('one'));
        setPart('aide', false);
        dlg.showModal();
        q('[data-avc-start]').focus();
    }

    function selectionFromTable() {
        var ids = [];
        var names = {};
        document.querySelectorAll('.eff-bulk-check:checked').forEach(function (cb) {
            var id = parseInt(cb.value, 10);
            if (id > 0 && ids.indexOf(id) < 0) {
                ids.push(id);
                var row = cb.closest('tr');
                var link = row ? row.querySelector('.eff-mq-mb__name') : null;
                names[id] = link ? link.textContent.trim() : '';
            }
        });
        return { ids: ids, names: names };
    }

    document.addEventListener('click', function (e) {
        var opener = e.target.closest ? e.target.closest('[data-avc-open]') : null;
        if (!opener) {
            return;
        }
        e.preventDefault();
        var ids = [];
        var names = {};
        if (opener.hasAttribute('data-avc-from-selection')) {
            var sel = selectionFromTable();
            ids = sel.ids;
            names = sel.names;
            if (ids.length === 0) {
                window.alert(t('none'));
                return;
            }
            if (ids.length > MAX) {
                window.alert(t('too_many', [MAX]));
                return;
            }
        } else {
            (opener.getAttribute('data-avc-ids') || '').split(',').forEach(function (v) {
                var id = parseInt(v, 10);
                if (id > 0) {
                    ids.push(id);
                }
            });
            if (ids.length === 1) {
                names[ids[0]] = opener.getAttribute('data-avc-name') || '';
            }
        }
        if (ids.length > 0) {
            open(ids, names);
        }
    });

    function fetchRun(id) {
        return fetch(url('data-avc-run', id), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false }; }).then(function (j) {
                if (!r.ok || !j || !j.ok) {
                    throw new Error((j && j.message) || t('network'));
                }
                return j;
            });
        });
    }

    function stateChip(st, label) {
        var chip = el('span', 'eff-avc__state is-' + st);
        chip.appendChild(el('i', null));
        chip.appendChild(el('span', null, label));
        return chip;
    }

    function stepItem(step) {
        var li = el('li', 'eff-avc__step is-running');
        li.appendChild(stateChip('running', t('running')));
        var body = el('div', 'eff-avc__step-b');
        body.appendChild(el('p', 'eff-avc__step-t', step.label));
        li.appendChild(body);
        return li;
    }

    function finishItem(li, step) {
        li.className = 'eff-avc__step is-' + step.state;
        li.replaceChild(stateChip(step.state, step.status_label), li.firstChild);
        var body = li.querySelector('.eff-avc__step-b');
        (step.detail || []).forEach(function (d) {
            body.appendChild(el('p', 'eff-avc__step-d', d));
        });
        if (step.source) {
            body.appendChild(el('p', 'eff-avc__src', t('source', [step.source.label, step.source.date])));
        }
    }

    // Garde la dernière étape lue à l'écran quand la liste dépasse la hauteur de la fenêtre.
    function reveal(node) {
        if (node && typeof node.scrollIntoView === 'function') {
            node.scrollIntoView({ block: 'nearest', behavior: reduced ? 'auto' : 'smooth' });
        }
    }

    function wait(ms) {
        return new Promise(function (resolve) { window.setTimeout(resolve, ms); });
    }

    function runSingle(token) {
        var id = state.ids[0];
        var list = q('[data-avc-list]');
        var placeholder = el('li', 'eff-avc__step is-running');
        placeholder.appendChild(stateChip('running', t('running')));
        list.appendChild(placeholder);
        return fetchRun(id).then(function (data) {
            if (token !== state.token) {
                return;
            }
            list.innerHTML = '';
            if (data.member && data.member.name) {
                state.names[id] = data.member.name;
                q('[data-avc-subject]').textContent = data.member.name;
            }
            var steps = data.steps || [];
            var chain = Promise.resolve();
            steps.forEach(function (step, i) {
                chain = chain.then(function () {
                    if (token !== state.token) {
                        return;
                    }
                    var li = stepItem(step);
                    list.appendChild(li);
                    reveal(li);
                    q('[data-avc-progress]').textContent = t('progress', [i + 1, steps.length]);
                    return wait(STEP_DELAY).then(function () {
                        if (token !== state.token) {
                            return;
                        }
                        finishItem(li, step);
                        live(t('live', [i + 1, steps.length, step.label, step.status_label]));
                    });
                });
            });
            return chain.then(function () {
                if (token !== state.token) {
                    return;
                }
                state.results[id] = data;
                var v = data.verdict || {};
                q('[data-avc-verdict-label]').textContent = v.label || '';
                q('[data-avc-verdict-rule]').textContent = v.detail || '';
                q('[data-avc-verdict-dot]').className = 'eff-avc__dot is-' + (v.key || '');
                q('[data-avc-verdict]').hidden = false;
                reveal(q('[data-avc-verdict]'));
                q('[data-avc-progress]').textContent = '';
                live(t('live_done', [v.label || '']));
                prepareSingleValidation(data);
            });
        });
    }

    function runBulk(token) {
        var tbody = q('[data-avc-rows]');
        var rows = {};
        state.ids.forEach(function (id) {
            var tr = el('tr', 'is-pending');
            var th = el('th', null, state.names[id] || ('#' + id));
            th.setAttribute('scope', 'row');
            tr.appendChild(th);
            var steps = el('td', 'eff-avc__cell-steps');
            steps.appendChild(stateChip('pending', t('pending')));
            tr.appendChild(steps);
            tr.appendChild(el('td', 'eff-avc__cell-verdict', ''));
            tbody.appendChild(tr);
            rows[id] = tr;
        });
        var chain = Promise.resolve();
        state.ids.forEach(function (id, i) {
            chain = chain.then(function () {
                if (token !== state.token) {
                    return;
                }
                var tr = rows[id];
                tr.className = 'is-running';
                tr.children[1].innerHTML = '';
                tr.children[1].appendChild(stateChip('running', t('running')));
                q('[data-avc-progress]').textContent = t('progress', [i + 1, state.ids.length]);
                return fetchRun(id).then(function (data) {
                    if (token !== state.token) {
                        return;
                    }
                    state.results[id] = data;
                    if (data.member && data.member.name) {
                        state.names[id] = data.member.name;
                        tr.children[0].textContent = data.member.name;
                    }
                    var c = data.counts || { ok: 0, warn: 0, none: 0 };
                    tr.className = 'is-done';
                    tr.children[1].innerHTML = '';
                    tr.children[1].appendChild(el('span', 'eff-avc__counts', t('counts', [c.ok, c.warn, c.none])));
                    var v = data.verdict || {};
                    tr.children[2].innerHTML = '';
                    var verdict = el('span', 'eff-avc__pill is-' + (v.key || ''), v.label || '');
                    verdict.title = v.detail || '';
                    tr.children[2].appendChild(verdict);
                    live(t('live_member', [i + 1, state.ids.length, state.names[id] || '', v.label || '']));
                }).catch(function (err) {
                    tr.className = 'is-error';
                    tr.children[1].innerHTML = '';
                    tr.children[1].appendChild(stateChip('warn', t('error')));
                    tr.children[2].textContent = err.message;
                });
            });
        });
        return chain.then(function () {
            q('[data-avc-progress]').textContent = '';
            prepareBulkValidation();
        });
    }

    function prepareSingleValidation(data) {
        var proposed = (data.verdict || {}).key;
        dlg.querySelectorAll('[data-avc-form] input[name="conclusion"]').forEach(function (r) {
            r.checked = r.value === proposed;
            var tag = r.closest('label').querySelector('[data-avc-proposed-tag]');
            if (tag) {
                tag.hidden = r.value !== proposed;
            }
        });
        noteRequirement();
        var submit = q('[data-avc-submit]');
        if (data.can_save === false) {
            submit.disabled = true;
            showError(data.notice || '');
        }
    }

    function prepareBulkValidation() {
        var tbody = q('[data-avc-val-rows]');
        tbody.innerHTML = '';
        state.ids.forEach(function (id) {
            var data = state.results[id];
            if (!data) {
                return;
            }
            var proposed = (data.verdict || {}).key;
            var tr = el('tr');
            tr.setAttribute('data-avc-row', String(id));
            var th = el('th', null, state.names[id] || ('#' + id));
            th.setAttribute('scope', 'row');
            tr.appendChild(th);
            var tdSel = el('td');
            var select = el('select');
            select.name = 'conclusion_' + id;
            select.setAttribute('aria-label', (state.names[id] || '') + ' · ' + (LABELS[proposed] || ''));
            LABEL_KEYS.forEach(function (k) {
                var o = el('option', null, LABELS[k] + (k === proposed ? ' (' + t('proposed') + ')' : ''));
                o.value = k;
                o.selected = k === proposed;
                select.appendChild(o);
            });
            select.setAttribute('data-proposed', proposed || '');
            tdSel.appendChild(select);
            tr.appendChild(tdSel);
            var tdNote = el('td');
            var note = el('input');
            note.type = 'text';
            note.maxLength = 2000;
            note.placeholder = t('note_placeholder');
            note.setAttribute('aria-label', t('note_placeholder') + ' · ' + (state.names[id] || ''));
            tdNote.appendChild(note);
            tr.appendChild(tdNote);
            var tdBtn = el('td');
            var btn = el('button', 'eff-avc__btn is-small', t('save_row'));
            btn.type = 'button';
            btn.setAttribute('data-avc-save-row', String(id));
            tdBtn.appendChild(btn);
            tr.appendChild(tdBtn);
            tbody.appendChild(tr);
        });
        q('[data-avc-submit]').textContent = t('save_all');
    }

    function noteRequirement() {
        var picked = q('[data-avc-form] input[name="conclusion"]:checked');
        var proposed = state.results[state.ids[0]] ? state.results[state.ids[0]].verdict.key : '';
        var needed = !!picked && picked.value !== proposed;
        q('[data-avc-note-req]').hidden = !needed;
        var note = q('#eff-avc-note');
        if (needed) {
            note.setAttribute('aria-required', 'true');
        } else {
            note.removeAttribute('aria-required');
        }
        return needed;
    }

    function showError(msg) {
        var n = q('[data-avc-error]');
        n.textContent = msg || '';
        n.hidden = !msg;
    }

    function formBase() {
        var fd = new FormData();
        fd.append('_csrf_token', dlg.getAttribute('data-avc-csrf') || '');
        var follow = q('[data-avc-form] input[name="follow_up"]:checked');
        fd.append('follow_up', follow ? follow.value : 'none');
        fd.append('recheck_days', q('[data-avc-form] select[name="recheck_days"]').value);
        return fd;
    }

    function post(target, fd) {
        return fetch(target, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false, message: t('network') }; });
        });
    }

    function finish(message, notice, positionUrl) {
        state.saved = true;
        q('[data-avc-done-msg]').textContent = message || '';
        var n = q('[data-avc-done-notice]');
        n.textContent = notice || '';
        n.hidden = !notice;
        if (positionUrl) {
            q('[data-avc-position-link]').href = positionUrl;
            q('[data-avc-position]').hidden = false;
        }
        enableNav();
        // La validation est faite : sa pastille reste marquée, sans pouvoir y revenir.
        dlg.querySelector('[data-avc-goto="validation"]').disabled = false;
        setPart('fin', true);
        live(message || '');
    }

    function submitSingle() {
        var id = state.ids[0];
        var picked = q('[data-avc-form] input[name="conclusion"]:checked');
        var note = q('#eff-avc-note').value.trim();
        if (noteRequirement() && note.length < 3) {
            showError(t('note_needed'));
            q('#eff-avc-note').focus();
            return;
        }
        var fd = formBase();
        fd.append('conclusion', picked ? picked.value : '');
        fd.append('note', note);
        var btn = q('[data-avc-submit]');
        btn.disabled = true;
        post(url('data-avc-save', id), fd).then(function (j) {
            btn.disabled = false;
            if (!j || !j.ok) {
                showError((j && j.message) || t('network'));
                return;
            }
            showError('');
            finish(j.message, j.notice, j.position_url);
        }).catch(function () {
            btn.disabled = false;
            showError(t('network'));
        });
    }

    function bulkItems(onlyId) {
        var items = [];
        var problem = null;
        dlg.querySelectorAll('[data-avc-row]').forEach(function (tr) {
            var id = parseInt(tr.getAttribute('data-avc-row'), 10);
            if ((onlyId && id !== onlyId) || tr.classList.contains('is-saved')) {
                return;
            }
            var select = tr.querySelector('select');
            var note = tr.querySelector('input[type="text"]');
            if (select.value !== select.getAttribute('data-proposed') && note.value.trim().length < 3) {
                problem = problem || note;
            }
            items.push({ id: id, conclusion: select.value, note: note.value.trim() });
        });
        return { items: items, problem: problem };
    }

    function submitBulk(onlyId) {
        var b = bulkItems(onlyId);
        if (b.problem) {
            showError(t('note_needed'));
            b.problem.focus();
            return;
        }
        if (b.items.length === 0) {
            return;
        }
        var fd = formBase();
        b.items.forEach(function (it, i) {
            fd.append('items[' + i + '][id]', String(it.id));
            fd.append('items[' + i + '][conclusion]', it.conclusion);
            fd.append('items[' + i + '][note]', it.note);
        });
        var btn = q('[data-avc-submit]');
        btn.disabled = true;
        post(dlg.getAttribute('data-avc-bulk'), fd).then(function (j) {
            btn.disabled = false;
            (j.rows || []).forEach(function (row) {
                var tr = dlg.querySelector('[data-avc-row="' + row.id + '"]');
                if (!tr) {
                    return;
                }
                var cell = tr.lastChild;
                cell.innerHTML = '';
                if (row.ok) {
                    tr.classList.add('is-saved');
                    tr.querySelectorAll('select, input').forEach(function (n) { n.disabled = true; });
                    cell.appendChild(stateChip('ok', t('saved_row')));
                } else {
                    cell.appendChild(el('span', 'eff-avc__row-error', row.message || ''));
                }
            });
            if (!j.ok && !(j.rows || []).length) {
                showError(j.message || t('network'));
                return;
            }
            showError('');
            state.saved = state.saved || (j.saved || 0) > 0;
            var left = dlg.querySelectorAll('[data-avc-row]:not(.is-saved)').length;
            if (left === 0) {
                finish(j.message, (j.rows || []).map(function (r) { return r.notice || ''; }).filter(Boolean)[0] || '', null);
            } else {
                live(j.message || '');
            }
        }).catch(function () {
            btn.disabled = false;
            showError(t('network'));
        });
    }

    function start() {
        if (state.busy) {
            return;
        }
        state.busy = true;
        var token = state.token;
        setPart('recherche', true);
        enableNav();
        var job = state.mode === 'bulk' ? runBulk(token) : runSingle(token);
        job.then(function () {
            state.busy = false;
            if (token !== state.token) {
                return;
            }
            q('[data-avc-next]').disabled = !researchDone();
            enableNav();
            q('[data-avc-back]').hidden = true;
        }).catch(function (err) {
            state.busy = false;
            var list = q('[data-avc-list]');
            list.innerHTML = '';
            var li = el('li', 'eff-avc__step is-warn');
            li.appendChild(stateChip('warn', t('error')));
            li.appendChild(el('div', 'eff-avc__step-b', err.message || t('network')));
            list.appendChild(li);
            live(err.message || t('network'));
        });
    }

    function closeDialog() {
        state.token++;
        if (dlg.open) {
            dlg.close();
        }
    }

    dlg.addEventListener('close', function () {
        state.token++;
        if (state.saved) {
            // La fiche et le tableur affichent la dernière vérification : rechargés après un enregistrement.
            window.location.reload();
        }
    });

    dlg.addEventListener('click', function (e) {
        var target = e.target;
        if (target === dlg) {
            closeDialog();
            return;
        }
        if (target.closest('[data-avc-close]')) {
            closeDialog();
        } else if (target.closest('[data-avc-start]')) {
            start();
        } else if (target.closest('[data-avc-next]')) {
            setPart('validation', true);
            enableNav();
        } else if (target.closest('[data-avc-back]')) {
            setPart('recherche', true);
            q('[data-avc-back]').hidden = state.part !== 'validation';
        } else if (target.closest('[data-avc-submit]')) {
            if (state.mode === 'bulk') {
                submitBulk(0);
            } else {
                submitSingle();
            }
        } else if (target.closest('[data-avc-save-row]')) {
            submitBulk(parseInt(target.closest('[data-avc-save-row]').getAttribute('data-avc-save-row'), 10));
        } else if (target.closest('[data-avc-goto]')) {
            var part = target.closest('[data-avc-goto]').getAttribute('data-avc-goto');
            if (part === 'aide' || (part === 'recherche' && (state.busy || Object.keys(state.results).length)) || (part === 'validation' && researchDone() && !state.saved)) {
                setPart(part, true);
            }
        }
    });

    dlg.addEventListener('change', function (e) {
        if (e.target.name === 'conclusion') {
            noteRequirement();
            showError('');
        }
    });

})();
