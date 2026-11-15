/**
 * JNET — espace d'une unité : recherche dans l'espace (touche « / »), filtres instantanés du suivi des membres et envoi
 * automatique du changement de responsable d'une tâche. Tout reste utilisable sans ce script : la recherche est un
 * confort, le tableau est complet et chaque changement de responsable a son bouton (balise noscript).
 */
(function () {
    'use strict';

    // —— Recherche ——
    var form = document.querySelector('[data-uw-search]');
    if (form) {
        var input = form.querySelector('input[type="search"]');
        var list = form.querySelector('.uw-search__list');
        var openers = document.querySelectorAll('[data-uw-search-open]');
        var url = form.getAttribute('data-url') || '';
        var timer = null;
        var seq = 0;
        var active = -1;

        var setOpen = function (open) {
            form.hidden = !open;
            openers.forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
            if (open) {
                input.focus();
                input.select();
            } else {
                list.textContent = '';
                active = -1;
            }
        };
        var items = function () { return Array.prototype.slice.call(list.querySelectorAll('a')); };
        var mark = function (i) {
            var all = items();
            if (!all.length) { active = -1; return; }
            active = (i + all.length) % all.length;
            all.forEach(function (a, k) { a.classList.toggle('is-on', k === active); });
            all[active].scrollIntoView({ block: 'nearest' });
        };
        var show = function (rows, q) {
            list.textContent = '';
            if (!rows.length) {
                var none = document.createElement('li');
                none.className = 'uw-search__none';
                none.textContent = 'Aucun résultat pour « ' + q + ' ».';
                list.appendChild(none);
                return;
            }
            rows.forEach(function (r) {
                var li = document.createElement('li');
                var a = document.createElement('a');
                a.href = r.href;
                var t = document.createElement('small');
                t.textContent = r.type;
                var b = document.createElement('b');
                b.textContent = r.label;
                a.appendChild(t);
                a.appendChild(b);
                if (r.detail) {
                    var d = document.createElement('span');
                    d.textContent = r.detail;
                    a.appendChild(d);
                }
                li.appendChild(a);
                list.appendChild(li);
            });
            mark(0);
        };
        var run = function () {
            var q = input.value.trim();
            if (q.length < 2) {
                list.textContent = '';
                active = -1;
                return;
            }
            var mine = ++seq;
            fetch(url + '?q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : { results: [] }; })
                .then(function (data) { if (mine === seq) { show(Array.isArray(data.results) ? data.results : [], q); } })
                .catch(function () { if (mine === seq) { show([], q); } });
        };

        openers.forEach(function (b) { b.addEventListener('click', function () { setOpen(form.hidden); }); });
        var closer = form.querySelector('[data-uw-search-close]');
        if (closer) { closer.addEventListener('click', function () { setOpen(false); }); }
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var all = items();
            if (all[active]) { window.location.href = all[active].href; }
        });
        input.addEventListener('input', function () {
            window.clearTimeout(timer);
            timer = window.setTimeout(run, 180);
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); mark(active + 1); }
            if (e.key === 'ArrowUp') { e.preventDefault(); mark(active - 1); }
        });
        document.addEventListener('keydown', function (e) {
            var tag = (e.target && e.target.tagName) || '';
            var typing = /INPUT|TEXTAREA|SELECT/.test(tag) || (e.target && e.target.isContentEditable);
            if (e.key === 'Escape' && !form.hidden) { setOpen(false); return; }
            if (e.key === '/' && !typing && !e.ctrlKey && !e.metaKey && !e.altKey) {
                e.preventDefault();
                setOpen(true);
            }
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setOpen(true);
            }
        });
    }

    // —— Suivi des membres : filtres instantanés ——
    var tools = document.querySelector('[data-uw-filter]');
    var table = document.querySelector('[data-uw-table]');
    if (tools && table) {
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
        var none = table.querySelector('[data-uw-none]');
        var q = tools.querySelector('[data-uw-q]');
        var current = 'all';
        var apply = function () {
            var needle = q ? q.value.trim().toLowerCase() : '';
            var visible = 0;
            rows.forEach(function (tr) {
                var ok = current === 'all'
                    || (current === 'todo' ? tr.getAttribute('data-todo') === '1' : tr.getAttribute('data-status') === current);
                if (ok && needle) { ok = (tr.getAttribute('data-search') || '').indexOf(needle) !== -1; }
                tr.hidden = !ok;
                if (ok) { visible++; }
            });
            if (none) { none.hidden = visible !== 0; }
        };
        tools.querySelectorAll('[data-filter]').forEach(function (b) {
            b.addEventListener('click', function () {
                current = b.getAttribute('data-filter') || 'all';
                tools.querySelectorAll('[data-filter]').forEach(function (o) {
                    var on = o === b;
                    o.classList.toggle('is-on', on);
                    o.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
                apply();
            });
        });
        if (q) { q.addEventListener('input', apply); }
    }

    // —— Tâches : le changement de responsable s'envoie seul ——
    document.querySelectorAll('[data-uw-autosubmit]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            if (sel.form) { sel.form.submit(); }
        });
    });
})();

/**
 * Améliorations progressives de l'espace d'unité. Rien ici n'est nécessaire : sans ce script, chaque action garde son
 * bouton ou son formulaire, et la prise en compte obligatoire reste un bandeau en ligne. Les actions envoyées sans
 * rechargement passent par les mêmes adresses serveur que les formulaires, avec le même jeton CSRF, et demandent du JSON
 * (en-tête Accept) ; le serveur refait tous les contrôles de droits.
 */
(function () {
    'use strict';

    var body = document.body;
    var toasts = document.querySelector('[data-uw-toasts]');
    var hasDialog = typeof HTMLDialogElement === 'function' && typeof document.createElement('dialog').showModal === 'function';

    document.querySelectorAll('.uw-js-only').forEach(function (el) { el.hidden = false; });

    // —— Messages discrets (toasts) pour les actions sans rechargement ——
    var toast = function (text, bad) {
        if (!toasts || !text) { return; }
        var t = document.createElement('div');
        t.className = 'uw-toast' + (bad ? ' is-bad' : '');
        t.textContent = text;
        toasts.appendChild(t);
        window.setTimeout(function () { t.classList.add('is-out'); }, bad ? 5200 : 3200);
        window.setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t); } }, bad ? 5700 : 3700);
    };

    // Envoie une écriture au serveur et lit sa réponse JSON {ok, message}.
    var send = function (url, data) {
        return fetch(url, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' }
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false, message: 'Réponse inattendue du serveur.' }; });
        }).catch(function () { return { ok: false, message: 'Connexion impossible. Réessayez.' }; });
    };
    var formData = function (csrf, fields) {
        var fd = new FormData();
        fd.append('_csrf_token', csrf || '');
        Object.keys(fields || {}).forEach(function (k) {
            [].concat(fields[k]).forEach(function (v) { fd.append(k, v); });
        });
        return fd;
    };
    var pad2 = function (n) { return (n < 10 ? '0' : '') + n; };

    // Dialogue modal natif : focus piégé et reste de la page inerte ; le focus revient sur le déclencheur à la fermeture.
    var openModal = function (dlg, opener) {
        document.body.appendChild(dlg);
        dlg.addEventListener('close', function () {
            if (dlg.parentNode) { dlg.parentNode.removeChild(dlg); }
            if (opener && typeof opener.focus === 'function') { opener.focus(); }
        });
        dlg.addEventListener('click', function (e) { if (e.target === dlg) { dlg.close(); } });
        dlg.showModal();
    };
    var el = function (tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text) { n.textContent = text; }
        return n;
    };

    // —— Prise en compte obligatoire : la boîte en ligne devient une fenêtre bloquante ——
    var gate = document.querySelector('[data-uw-gate]');
    if (gate && hasDialog) {
        var armed = false;
        var left = function () { return gate.querySelectorAll('[data-uw-gate-item]:not([hidden])').length; };
        gate.addEventListener('cancel', function (e) { e.preventDefault(); });
        gate.addEventListener('close', function () {
            if (armed && left() > 0) { gate.showModal(); }
        });
        gate.close();
        gate.showModal();
        armed = true;
        var firstBtn = gate.querySelector('[data-uw-gate-item]:not([hidden]) button');
        if (firstBtn) { firstBtn.focus(); }
        gate.querySelectorAll('[data-uw-gate-form]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = form.querySelector('button');
                var item = form.closest('[data-uw-gate-item]');
                btn.disabled = true;
                send(form.action, new FormData(form)).then(function (res) {
                    var old = item.querySelector('.uw-gate__err');
                    if (old) { old.parentNode.removeChild(old); }
                    if (!res.ok) {
                        btn.disabled = false;
                        var err = el('p', 'uw-gate__err', res.message || 'L’accusé n’a pas pu être enregistré.');
                        err.setAttribute('role', 'alert');
                        item.appendChild(err);
                        return;
                    }
                    item.hidden = true;
                    var next = gate.querySelector('[data-uw-gate-item]:not([hidden]) button');
                    if (next) {
                        next.focus();
                        return;
                    }
                    armed = false;
                    gate.close();
                    toast('Prise en compte enregistrée.');
                });
            });
        });
    }

    // —— Glisser-déposer : une liste d'éléments réordonnables par une poignée ——
    // $container : parent des éléments ; $sel : sélecteur d'un élément ; $onDrop(ordre) appelé après un déplacement.
    var sortable = function (container, sel, onDrop) {
        var dragged = null;
        var before = null;
        container.addEventListener('dragstart', function (e) {
            var grip = e.target.closest ? e.target.closest('.uw-grip') : null;
            var item = grip ? grip.closest(sel) : null;
            if (!item || !container.contains(item)) { return; }
            dragged = item;
            before = Array.prototype.slice.call(container.querySelectorAll(sel));
            item.classList.add('is-drag');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', 'uw');
                if (e.dataTransfer.setDragImage) { e.dataTransfer.setDragImage(item, 12, 12); }
            }
        });
        container.addEventListener('dragover', function (e) {
            if (!dragged) { return; }
            e.preventDefault();
            var items = Array.prototype.slice.call(container.querySelectorAll(sel)).filter(function (n) { return n !== dragged; });
            var after = null;
            for (var i = 0; i < items.length; i++) {
                var r = items[i].getBoundingClientRect();
                if (e.clientY < r.top + r.height / 2) { after = items[i]; break; }
            }
            if (after) { container.insertBefore(dragged, after); }
            else if (items.length) { items[items.length - 1].parentNode.insertBefore(dragged, items[items.length - 1].nextSibling); }
        });
        var finish = function (cancel) {
            if (!dragged) { return; }
            var item = dragged;
            dragged = null;
            item.classList.remove('is-drag');
            var now = Array.prototype.slice.call(container.querySelectorAll(sel));
            var changed = now.some(function (n, i) { return n !== before[i]; });
            if (changed) { onDrop(now, before, cancel); }
        };
        container.addEventListener('drop', function (e) { if (dragged) { e.preventDefault(); } });
        container.addEventListener('dragend', function () { finish(false); });
    };

    // —— Tâches : cartes glissées d'une colonne à l'autre ——
    var board = document.querySelector('[data-uw-kanban]');
    if (board) {
        var csrf = board.getAttribute('data-uw-csrf') || '';
        var nextOf = { todo: ['doing', 'Démarrer'], doing: ['done', 'Terminer'], done: ['todo', 'Rouvrir'] };
        var colTitle = function (col) { var h = col.querySelector('h2'); return h ? h.textContent : ''; };
        var refreshCol = function (col) {
            var n = col.querySelectorAll('[data-uw-task]').length;
            var count = col.querySelector('.uw-col__head .ja-mono');
            if (count) { count.textContent = String(n); }
            var empty = col.querySelector('.uw-col__empty');
            if (n === 0 && !empty) {
                empty = el('p', 'uw-col__empty', 'Rien ici.');
                col.insertBefore(empty, col.querySelector('.uw-add') || null);
            } else if (n > 0 && empty) {
                empty.parentNode.removeChild(empty);
            }
        };
        var dragCard = null;
        var fromCol = null;
        board.addEventListener('dragstart', function (e) {
            var card = e.target.closest ? e.target.closest('[data-uw-task]') : null;
            if (!card) { return; }
            dragCard = card;
            fromCol = card.closest('[data-uw-col]');
            card.classList.add('is-drag');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', 'task');
            }
        });
        board.addEventListener('dragover', function (e) {
            var col = e.target.closest ? e.target.closest('[data-uw-col]') : null;
            if (!dragCard || !col) { return; }
            e.preventDefault();
            board.querySelectorAll('.is-over').forEach(function (c) { if (c !== col) { c.classList.remove('is-over'); } });
            col.classList.add('is-over');
        });
        board.addEventListener('dragleave', function (e) {
            if (e.target === board) { board.querySelectorAll('.is-over').forEach(function (c) { c.classList.remove('is-over'); }); }
        });
        board.addEventListener('dragend', function () {
            if (dragCard) { dragCard.classList.remove('is-drag'); }
            board.querySelectorAll('.is-over').forEach(function (c) { c.classList.remove('is-over'); });
            dragCard = null;
        });
        board.addEventListener('drop', function (e) {
            var col = e.target.closest ? e.target.closest('[data-uw-col]') : null;
            if (!dragCard || !col) { return; }
            e.preventDefault();
            var card = dragCard;
            var origin = fromCol;
            board.querySelectorAll('.is-over').forEach(function (c) { c.classList.remove('is-over'); });
            var status = col.getAttribute('data-uw-col');
            if (col === origin) { return; }
            col.insertBefore(card, col.querySelector('.uw-add') || null);
            refreshCol(col);
            refreshCol(origin);
            send(card.getAttribute('data-uw-url'), formData(csrf, { act: 'status', status: status })).then(function (res) {
                if (!res.ok) {
                    origin.insertBefore(card, origin.querySelector('.uw-add') || null);
                    refreshCol(col);
                    refreshCol(origin);
                    toast(res.message || 'La tâche n’a pas pu être déplacée.', true);
                    return;
                }
                var step = card.querySelector('button[name="status"]');
                if (step) { step.value = nextOf[status][0]; step.textContent = nextOf[status][1]; }
                toast('Tâche déplacée vers « ' + colTitle(col) + ' ».');
            });
        });
    }

    // —— Pages : ordre par poignée ——
    var pageList = document.querySelector('[data-uw-reorder]');
    if (pageList) {
        sortable(pageList, '[data-uw-page]', function (now, was) {
            now.forEach(function (row, i) {
                var n = row.querySelector('.ja-row .n');
                if (n) { n.textContent = pad2(i + 1); }
            });
            send(pageList.getAttribute('data-uw-reorder'), formData(pageList.getAttribute('data-uw-csrf'), {
                'ids[]': now.map(function (r) { return r.getAttribute('data-uw-page'); })
            })).then(function (res) {
                if (res.ok) { toast('Ordre des pages enregistré.'); return; }
                was.forEach(function (r) { pageList.appendChild(r); });
                was.forEach(function (row, i) {
                    var n = row.querySelector('.ja-row .n');
                    if (n) { n.textContent = pad2(i + 1); }
                });
                toast(res.message || 'L’ordre n’a pas pu être enregistré.', true);
            });
        });
    }

    // —— Page ouverte en lecture : case, vote et réaction sans recharger la page ——
    var plural = function (n, one, many) { return n + ' ' + (n > 1 ? many : one); };
    document.querySelectorAll('form[data-uw-act]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            var kind = f.getAttribute('data-uw-act');
            var btn = e.submitter || f.querySelector('button[type="submit"]');
            if (!btn || typeof window.fetch !== 'function') { return; }
            e.preventDefault();
            var fd = new FormData(f);
            if (btn.name) { fd.set(btn.name, btn.value); }
            var undo;
            if (kind === 'todo') {
                var label = f.querySelector('.uw-todo__t');
                var wasOn = btn.classList.contains('is-on');
                var setTodo = function (on) {
                    btn.classList.toggle('is-on', on);
                    btn.setAttribute('aria-checked', on ? 'true' : 'false');
                    btn.setAttribute('aria-label', (on ? 'Marquer comme à faire' : 'Marquer comme fait') + ' : ' + (label ? label.textContent : ''));
                    if (label) { label.classList.toggle('is-done', on); }
                };
                setTodo(!wasOn);
                undo = function () { setTodo(wasOn); };
            } else if (kind === 'vote') {
                var poll = f.closest('[data-uw-poll]');
                var opts = poll ? [].slice.call(poll.querySelectorAll('button.uw-po')) : [];
                var before = opts.map(function (b) { return [b.classList.contains('is-on'), parseInt(b.getAttribute('data-n') || '0', 10)]; });
                var paint = function (state) {
                    var total = 0;
                    state.forEach(function (s) { total += s[1]; });
                    opts.forEach(function (b, i) {
                        b.classList.toggle('is-on', state[i][0]);
                        b.setAttribute('aria-pressed', state[i][0] ? 'true' : 'false');
                        b.setAttribute('data-n', String(state[i][1]));
                        var n = b.querySelector('.uw-po__n');
                        if (n) { n.textContent = String(state[i][1]); }
                        var bar = b.querySelector('i');
                        if (bar) { bar.style.setProperty('--w', (total > 0 ? Math.round(state[i][1] / total * 1000) / 10 : 0) + '%'); }
                    });
                    var small = poll ? poll.querySelector('[data-uw-poll-total]') : null;
                    if (small) { small.textContent = plural(total, 'vote', 'votes') + ' · un choix par membre, modifiable'; }
                };
                var at = opts.indexOf(btn);
                var next = before.map(function (s, i) { return [s[0], s[1]]; });
                next.forEach(function (s, i) {
                    if (i === at) { s[0] = !before[i][0]; s[1] += before[i][0] ? -1 : 1; }
                    else if (s[0]) { s[0] = false; s[1] -= 1; }
                });
                paint(next);
                undo = function () { paint(before); };
            } else if (kind === 'react') {
                var count = btn.querySelector('small');
                var had = btn.classList.contains('is-on');
                var n0 = parseInt(btn.getAttribute('data-n') || '0', 10);
                var setReact = function (on, n) {
                    btn.classList.toggle('is-on', on);
                    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                    btn.setAttribute('data-n', String(n));
                    if (count) { count.textContent = String(n); }
                };
                setReact(!had, Math.max(0, n0 + (had ? -1 : 1)));
                undo = function () { setReact(had, n0); };
            } else {
                return;
            }
            send(f.action, fd).then(function (res) {
                if (!res.ok) {
                    if (undo) { undo(); }
                    toast(res.message || 'L’action n’a pas pu être enregistrée.', true);
                }
            });
        });
    });

    // —— Éditeur de page : texte en place, poignée, Entrée, Retour arrière, palette « / », enregistrement automatique ——
    var editor = document.querySelector('[data-uw-editor]');
    var blocks = document.querySelector('[data-uw-blocks]');
    if (editor && blocks) {
        var MAX_BLOCKS = 150;
        var MAX_OPTS = 8;
        var SVG_NS = 'http://www.w3.org/2000/svg';
        var plain = (function () {
            var probe = document.createElement('div');
            try { probe.contentEditable = 'plaintext-only'; } catch (err) { return false; }
            return probe.contentEditable === 'plaintext-only';
        }());
        editor.querySelectorAll('.uw-nojs').forEach(function (n) { n.hidden = true; });

        var icon = function (paths) {
            var s = document.createElementNS(SVG_NS, 'svg');
            s.setAttribute('class', 'ja-i');
            s.setAttribute('viewBox', '0 0 24 24');
            s.setAttribute('aria-hidden', 'true');
            String(paths || '').split('|').forEach(function (d) {
                if (!d) { return; }
                var p = document.createElementNS(SVG_NS, 'path');
                p.setAttribute('d', d);
                s.appendChild(p);
            });
            return s;
        };
        var newId = function () {
            var a = new Uint8Array(4);
            if (window.crypto && window.crypto.getRandomValues) { window.crypto.getRandomValues(a); }
            else { for (var i = 0; i < 4; i++) { a[i] = Math.floor(Math.random() * 256); } }
            return [].map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
        };
        var norm = function (s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
        var KEYS = {
            p: 'texte paragraphe text', h: 'titre intertitre heading h1 h2', todo: 'tache todo case cocher checklist',
            call: 'encadre callout note attention', poll: 'sondage vote question poll', count: 'compte rebours decompte j-', div: 'separateur ligne filet hr'
        };

        // —— Un champ de texte : zone modifiable en place (ou, à défaut, zone de texte) ——
        var isDiv = function (f) { return f.tagName === 'DIV'; };
        var getText = function (f) { return isDiv(f) ? f.textContent : f.value; };
        var sync = function (f) { if (f._src) { f._src.value = f.textContent; } };
        var setText = function (f, v) {
            if (isDiv(f)) { f.textContent = v; sync(f); } else { f.value = v; }
        };
        var caretRange = function (f) {
            if (f.tagName !== 'DIV') { return [f.selectionStart || 0, f.selectionEnd || 0]; }
            var sel = window.getSelection();
            if (!sel || !sel.rangeCount || !f.contains(sel.anchorNode)) { var n = f.textContent.length; return [n, n]; }
            var r = sel.getRangeAt(0);
            var a = r.cloneRange();
            a.selectNodeContents(f);
            a.setEnd(r.startContainer, r.startOffset);
            var b = r.cloneRange();
            b.selectNodeContents(f);
            b.setEnd(r.endContainer, r.endOffset);
            return [a.toString().length, b.toString().length];
        };
        var setCaret = function (f, n) {
            if (!f) { return; }
            f.focus();
            if (f.tagName !== 'DIV') {
                if (typeof f.setSelectionRange === 'function') { try { f.setSelectionRange(n, n); } catch (err) { /* champ sans sélection */ } }
                return;
            }
            var sel = window.getSelection();
            var r = document.createRange();
            var walk = document.createTreeWalker(f, NodeFilter.SHOW_TEXT);
            var rest = n;
            var node;
            var placed = false;
            while ((node = walk.nextNode())) {
                if (rest <= node.nodeValue.length) { r.setStart(node, rest); placed = true; break; }
                rest -= node.nodeValue.length;
            }
            if (!placed) { r.selectNodeContents(f); r.collapse(false); } else { r.collapse(true); }
            sel.removeAllRanges();
            sel.addRange(r);
        };
        var focusEnd = function (f) { setCaret(f, f ? getText(f).length : 0); };
        var autosize = function (ta) { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; };
        var enhance = function (root) {
            root.querySelectorAll('textarea.uw-ed').forEach(function (ta) {
                if (!plain) {
                    ta.setAttribute('placeholder', ta.getAttribute('data-ph') || '');
                    autosize(ta);
                    return;
                }
                var d = document.createElement('div');
                d.className = ta.className;
                d.setAttribute('contenteditable', 'plaintext-only');
                d.setAttribute('role', 'textbox');
                d.setAttribute('aria-label', ta.getAttribute('aria-label') || '');
                if (!ta.hasAttribute('data-line')) { d.setAttribute('aria-multiline', 'true'); } else { d.setAttribute('data-line', ''); }
                d.setAttribute('data-ph', ta.getAttribute('data-ph') || '');
                d.setAttribute('data-max', ta.getAttribute('maxlength') || '0');
                d.textContent = ta.value;
                if (ta.id) { d.id = ta.id; ta.removeAttribute('id'); }
                d._src = ta;
                ta.parentNode.insertBefore(d, ta);
                ta.hidden = true;
                ta.tabIndex = -1;
            });
        };

        // —— Blocs ——
        var isBlock = function (n) { return !!n && n.nodeType === 1 && n.hasAttribute('data-uw-block'); };
        var all = function () { return [].slice.call(blocks.children).filter(isBlock); };
        var typeOf = function (fs) { return fs.getAttribute('data-t'); };
        var mainField = function (fs) { return fs.querySelector('.uw-ed:not([hidden])'); };
        var stops = function () { return [].slice.call(editor.querySelectorAll('.uw-ed:not([hidden]), .uw-opt input')); };
        var isEmptyBlock = function (fs) {
            var f = mainField(fs);
            return !!f && getText(f) === '' && !fs.querySelector('.uw-opt input[value]:not([value=""])');
        };
        var renumber = function () {
            var list = all();
            list.forEach(function (fs, i) {
                fs.querySelectorAll('[name]').forEach(function (f) {
                    var name = f.getAttribute('name') || '';
                    if (/^blocks\[[^\]]*\]/.test(name)) { f.setAttribute('name', name.replace(/^blocks\[[^\]]*\]/, 'blocks[' + i + ']')); }
                    if (name === 'act') {
                        var m = /^(up|down|del):/.exec(f.value);
                        if (m) {
                            f.value = m[1] + ':' + i;
                            if (m[1] === 'up') { f.disabled = i === 0; }
                            if (m[1] === 'down') { f.disabled = i === list.length - 1; }
                        }
                    }
                });
                fs.querySelectorAll('.uw-opt input').forEach(function (inp, k) {
                    inp.setAttribute('placeholder', 'Choix ' + (k + 1));
                    inp.setAttribute('aria-label', 'Choix ' + (k + 1));
                });
                var add = fs.querySelector('[data-uw-opt-add]');
                if (add) { add.hidden = fs.querySelectorAll('.uw-opt').length >= MAX_OPTS; }
            });
        };
        var makeBlock = function (type) {
            var tpl = document.querySelector('template[data-uw-block-tpl="' + type + '"]');
            if (!tpl) { return null; }
            var node = tpl.content.firstElementChild.cloneNode(true);
            var id = node.querySelector('input[name$="[id]"]');
            if (id) { id.value = newId(); }
            node.querySelectorAll('.uw-js-only').forEach(function (n) { n.hidden = false; });
            node.querySelectorAll('.uw-nojs').forEach(function (n) { n.hidden = true; });
            enhance(node);
            return node;
        };

        // —— Enregistrement : état, enregistrement automatique, conflit ——
        var form = editor;
        var statusEl = form.querySelector('[data-uw-status]');
        var statusT = form.querySelector('[data-uw-status-t]');
        var retryB = form.querySelector('[data-uw-retry]');
        var reloadB = form.querySelector('[data-uw-reload]');
        var cancelA = form.querySelector('[data-uw-cancel]');
        var versionIn = form.querySelector('input[name="version"]');
        var syncAll = function () { form.querySelectorAll('.uw-ed').forEach(sync); };
        var sigOf = function () {
            syncAll();
            var out = [];
            new FormData(form).forEach(function (v, k) { if (k === 'title' || k.indexOf('blocks[') === 0) { out.push(k + '=' + v); } });
            return out.join('\u0001');
        };
        var savedSig = sigOf();
        var saving = false;
        var again = false;
        var timer = null;
        var broken = false;
        var autosaved = false;
        var leaving = false;
        var pendingSubmit = null;
        var setState = function (state, text) {
            if (!statusEl) { return; }
            statusEl.setAttribute('data-state', state);
            statusT.textContent = text || { saved: 'Enregistré', dirty: 'Modifications non enregistrées', saving: 'Enregistrement…', error: 'Enregistrement impossible' }[state];
            if (retryB) { retryB.hidden = state !== 'error' || broken; }
            if (reloadB) { reloadB.hidden = !(state === 'error' && broken); }
        };
        var clock = function () { var d = new Date(); return pad2(d.getHours()) + ':' + pad2(d.getMinutes()); };
        var save = function () {
            window.clearTimeout(timer);
            if (broken) { return; }
            if (saving) { again = true; return; }
            var sent = sigOf();
            if (sent === savedSig) { setState('saved'); return; }
            saving = true;
            setState('saving');
            var fd = new FormData(form);
            fd.set('act', 'save');
            send(form.getAttribute('action'), fd).then(function (res) {
                saving = false;
                if (res.ok) {
                    savedSig = sent;
                    if (res.version && versionIn) { versionIn.value = res.version; }
                    autosaved = true;
                    if (cancelA) { cancelA.textContent = 'Fermer'; }
                    if (pendingSubmit) { var sub = pendingSubmit; pendingSubmit = null; leaving = true; if (form.requestSubmit && sub !== true) { form.requestSubmit(sub); } else { form.submit(); } return; }
                    if (again || sigOf() !== savedSig) { again = false; setState('dirty'); timer = window.setTimeout(save, 400); return; }
                    setState('saved', 'Enregistré à ' + clock());
                    return;
                }
                pendingSubmit = null;
                broken = !!res.conflict;
                setState('error', res.message || 'Enregistrement impossible');
            });
        };
        var markDirty = function () {
            if (broken) { return; }
            if (sigOf() === savedSig && !saving) { window.clearTimeout(timer); setState('saved'); return; }
            if (!saving) { setState('dirty'); }
            window.clearTimeout(timer);
            timer = window.setTimeout(save, 1500);
        };
        if (statusEl) { setState('saved'); }
        if (retryB) { retryB.addEventListener('click', function () { setState('dirty'); save(); }); }
        form.addEventListener('submit', function (e) {
            syncAll();
            if (saving) { e.preventDefault(); pendingSubmit = e.submitter || true; return; }
            leaving = true;
        });
        window.addEventListener('beforeunload', function (e) {
            if (!leaving && (saving || sigOf() !== savedSig)) { e.preventDefault(); e.returnValue = ''; }
        });
        document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden' && sigOf() !== savedSig) { save(); } });

        var changed = function () { renumber(); markDirty(); };

        // —— Menu flottant (palette « / », « + » d'un bloc, menu de la poignée) ——
        var pop = null;
        var closePop = function (refocus) {
            if (!pop) { return; }
            var p = pop;
            pop = null;
            document.removeEventListener('mousedown', p.outside, true);
            window.removeEventListener('resize', p.place);
            window.removeEventListener('scroll', p.place, true);
            if (p.node.parentNode) { p.node.parentNode.removeChild(p.node); }
            if (refocus && p.back) { p.back(); }
        };
        var openPop = function (cfg) {
            closePop(false);
            var node = el('div', 'uw-pop');
            node.setAttribute('role', 'dialog');
            node.setAttribute('aria-label', cfg.label);
            var q = null;
            if (cfg.search) {
                var row = el('div', 'uw-pop__q');
                if (cfg.prefix) { row.appendChild(el('span', '', cfg.prefix)); }
                q = document.createElement('input');
                q.type = 'text';
                q.autocomplete = 'off';
                q.setAttribute('role', 'combobox');
                q.setAttribute('aria-expanded', 'true');
                q.setAttribute('aria-controls', 'uw-pop-list');
                q.setAttribute('aria-label', 'Filtrer les blocs');
                q.placeholder = 'Tapez pour filtrer';
                row.appendChild(q);
                node.appendChild(row);
            }
            var ul = el('ul');
            ul.id = 'uw-pop-list';
            ul.setAttribute('role', 'listbox');
            ul.setAttribute('aria-label', cfg.label);
            node.appendChild(ul);
            var shown = [];
            var active = 0;
            var mark = function (i) {
                if (!shown.length) { return; }
                active = (i + shown.length) % shown.length;
                shown.forEach(function (s, k) {
                    s.li.setAttribute('aria-selected', k === active ? 'true' : 'false');
                });
                if (q) { q.setAttribute('aria-activedescendant', shown[active].li.id); }
                shown[active].li.scrollIntoView({ block: 'nearest' });
            };
            var render = function (filter) {
                while (ul.firstChild) { ul.removeChild(ul.firstChild); }
                shown = [];
                var f = norm(filter);
                cfg.items.forEach(function (it) {
                    if (f && norm(it.label + ' ' + it.hint + ' ' + (KEYS[it.key] || '')).indexOf(f) === -1) { return; }
                    var li = el('li');
                    li.id = 'uw-pop-' + it.key;
                    li.setAttribute('role', 'option');
                    li.setAttribute('aria-selected', 'false');
                    if (it.disabled) { li.setAttribute('aria-disabled', 'true'); }
                    var ic = el('span', 'uw-pop__i');
                    ic.appendChild(icon(it.icon));
                    var tx = el('span', 'uw-pop__t');
                    tx.appendChild(el('b', '', it.label));
                    if (it.hint) { tx.appendChild(el('small', '', it.hint)); }
                    li.appendChild(ic);
                    li.appendChild(tx);
                    ul.appendChild(li);
                    var rec = { it: it, li: li };
                    li.addEventListener('mousemove', function () { if (!it.disabled) { mark(shown.indexOf(rec)); } });
                    li.addEventListener('mousedown', function (e) { e.preventDefault(); });
                    li.addEventListener('click', function () { if (!it.disabled) { choose(rec); } });
                    shown.push(rec);
                });
                if (!shown.length) { ul.appendChild(el('li', 'uw-pop__none', 'Aucun bloc ne correspond.')); }
                var first = 0;
                shown.some(function (s, k) { if (!s.it.disabled) { first = k; return true; } return false; });
                mark(first);
            };
            var choose = function (rec) {
                if (!rec || rec.it.disabled) { return; }
                closePop(false);
                cfg.onPick(rec.it.key);
            };
            var place = function () {
                var r = cfg.anchor();
                var w = node.offsetWidth;
                var h = node.offsetHeight;
                var vw = window.innerWidth;
                var vh = window.innerHeight;
                var left = Math.min(Math.max(8, r.left), Math.max(8, vw - w - 8));
                var below = r.bottom + 6;
                var top = below + h <= vh - 8 ? below : Math.max(8, Math.min(r.top - h - 6, vh - h - 8));
                node.style.left = left + 'px';
                node.style.top = top + 'px';
            };
            node.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown') { e.preventDefault(); mark(active + 1); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); mark(active - 1); }
                else if (e.key === 'Enter') { e.preventDefault(); choose(shown[active]); }
                else if (e.key === 'Escape' || e.key === 'Tab') { e.preventDefault(); e.stopPropagation(); closePop(true); }
                else if (e.key === 'Backspace' && q && q.value === '') { e.preventDefault(); closePop(true); }
            });
            if (q) { q.addEventListener('input', function () { render(q.value); }); }
            var outside = function (e) { if (!node.contains(e.target)) { closePop(false); } };
            document.addEventListener('mousedown', outside, true);
            window.addEventListener('resize', place);
            window.addEventListener('scroll', place, true);
            document.body.appendChild(node);
            pop = { node: node, outside: outside, place: place, back: cfg.back };
            render('');
            place();
            if (q) { q.focus(); } else { node.tabIndex = -1; node.focus(); }
        };
        var typeItems = function () {
            var out = [];
            document.querySelectorAll('template[data-uw-block-tpl]').forEach(function (t) {
                out.push({ key: t.getAttribute('data-uw-block-tpl'), label: t.getAttribute('data-label'), hint: t.getAttribute('data-hint'), icon: t.getAttribute('data-icon') });
            });
            return out;
        };
        var rectOf = function (n) { var r = n.getBoundingClientRect(); return { left: r.left, top: r.top, bottom: r.bottom }; };

        // —— Gestes sur les blocs ——
        var focusBlock = function (fs, atEnd) {
            var f = fs && mainField(fs);
            if (!f) { var o = fs && fs.querySelector('.uw-opt input'); if (o) { o.focus(); } return !!o; }
            if (atEnd) { focusEnd(f); } else { setCaret(f, 0); }
            return true;
        };
        var replaceBlock = function (fs, type, text) {
            var nb = makeBlock(type);
            if (!nb) { return null; }
            if (text) { var f = mainField(nb); if (f) { setText(f, text); } }
            blocks.replaceChild(nb, fs);
            return nb;
        };
        var insertAfter = function (fs, type) {
            var nb = makeBlock(type);
            if (nb) { blocks.insertBefore(nb, fs ? fs.nextSibling : null); }
            return nb;
        };
        var focusAfterInsert = function (nb) {
            if (!nb) { return; }
            if (!mainField(nb) && !nb.querySelector('.uw-opt input')) {
                var next = nb.nextElementSibling;
                if (!isBlock(next)) { next = insertAfter(nb, 'p'); }
                focusBlock(next, false);
                return;
            }
            focusBlock(nb, false);
        };
        var removeBlock = function (fs) {
            var list = all();
            if (list.length < 2) {
                var fresh = replaceBlock(fs, 'p');
                changed();
                focusBlock(fresh, false);
                return;
            }
            var i = list.indexOf(fs);
            var target = null;
            var atEnd = true;
            for (var k = i - 1; k >= 0 && !target; k--) { if (mainField(list[k]) || list[k].querySelector('.uw-opt input')) { target = list[k]; } }
            if (!target) {
                atEnd = false;
                for (var j = i + 1; j < list.length && !target; j++) { if (mainField(list[j]) || list[j].querySelector('.uw-opt input')) { target = list[j]; } }
            }
            blocks.removeChild(fs);
            changed();
            if (target) { focusBlock(target, atEnd); }
        };
        var moveBlock = function (fs, dir) {
            var sib = dir < 0 ? fs.previousElementSibling : fs.nextElementSibling;
            if (!isBlock(sib)) { return false; }
            if (dir < 0) { blocks.insertBefore(fs, sib); } else { blocks.insertBefore(sib, fs); }
            changed();
            return true;
        };
        var pickType = function (ctx, type) {
            var nb = null;
            if (ctx.mode === 'replace') {
                if (typeOf(ctx.fs) === type) { focusBlock(ctx.fs, true); return; }
                nb = replaceBlock(ctx.fs, type);
            } else {
                var host = ctx.mode === 'end' ? all()[all().length - 1] : ctx.fs;
                if (host && typeOf(host) === 'p' && isEmptyBlock(host)) {
                    if (type === 'p') { focusBlock(host, true); return; }
                    nb = replaceBlock(host, type);
                } else {
                    if (all().length >= MAX_BLOCKS) { toast('Cette page a atteint ' + MAX_BLOCKS + ' blocs.', true); return; }
                    nb = insertAfter(host, type);
                }
            }
            changed();
            focusAfterInsert(nb);
        };
        var openTypes = function (ctx, anchorEl, back) {
            openPop({
                label: 'Insérer un bloc',
                search: true,
                prefix: ctx.mode === 'replace' ? '/' : '',
                items: typeItems(),
                anchor: function () { return rectOf(anchorEl); },
                onPick: function (type) { pickType(ctx, type); },
                back: back
            });
        };
        var openGripMenu = function (fs, grip) {
            var list = all();
            var i = list.indexOf(fs);
            openPop({
                label: 'Menu du bloc',
                search: false,
                items: [
                    { key: 'up', label: 'Monter', hint: 'Avant le bloc précédent', icon: 'm6 15 6-6 6 6', disabled: i <= 0 },
                    { key: 'down', label: 'Descendre', hint: 'Après le bloc suivant', icon: 'm6 9 6 6 6-6', disabled: i >= list.length - 1 }
                ],
                anchor: function () { return rectOf(grip); },
                onPick: function (key) { moveBlock(fs, key === 'up' ? -1 : 1); grip.focus(); },
                back: function () { grip.focus(); }
            });
        };
        var addOption = function (fs, after) {
            var rows = fs.querySelectorAll('.uw-opt');
            if (rows.length >= MAX_OPTS) { return null; }
            var ref = (after || rows[rows.length - 1]);
            var src = ref.querySelector('input');
            var row = el('div', 'uw-opt');
            var dot = el('span', 'uw-opt__dot');
            dot.setAttribute('aria-hidden', 'true');
            var inp = document.createElement('input');
            inp.type = 'text';
            inp.name = src.name;
            inp.maxLength = src.maxLength;
            inp.autocomplete = 'off';
            row.appendChild(dot);
            row.appendChild(inp);
            ref.parentNode.insertBefore(row, ref.nextSibling);
            renumber();
            return inp;
        };
        var stepStop = function (f, dir) {
            var list = stops();
            var i = list.indexOf(f);
            var t = list[i + dir];
            if (!t) { return false; }
            if (t.tagName === 'INPUT') { t.focus(); t.setSelectionRange(dir < 0 ? t.value.length : 0, dir < 0 ? t.value.length : 0); }
            else if (dir < 0) { focusEnd(t); } else { setCaret(t, 0); }
            return true;
        };

        enhance(editor);
        renumber();
        sortable(blocks, '[data-uw-block]', function () { changed(); });

        // Clics : « + » d'un bloc, « Insérer un outil », poignée, croix, « Ajouter un choix ».
        editor.addEventListener('click', function (e) {
            var t = e.target.closest ? e.target : null;
            if (!t) { return; }
            var add = t.closest('[data-uw-add]');
            if (add) {
                var fs = add.closest('[data-uw-block]');
                openTypes({ mode: 'after', fs: fs }, add, function () { add.focus(); });
                return;
            }
            var end = t.closest('[data-uw-add-end]');
            if (end) { openTypes({ mode: 'end' }, end, function () { end.focus(); }); return; }
            var grip = t.closest('[data-uw-grip]');
            if (grip) { openGripMenu(grip.closest('[data-uw-block]'), grip); return; }
            var oa = t.closest('[data-uw-opt-add]');
            if (oa) { var inp = addOption(oa.closest('[data-uw-block]')); if (inp) { inp.focus(); markDirty(); } return; }
            var del = t.closest('button.uw-del');
            if (del) { e.preventDefault(); removeBlock(del.closest('[data-uw-block]')); }
        });

        editor.addEventListener('input', function (e) {
            var f = e.target;
            if (f && f.classList && f.classList.contains('uw-ed') && f.tagName === 'DIV') {
                var text = f.textContent;
                var max = parseInt(f.getAttribute('data-max') || '0', 10);
                var fix = text;
                if (f.hasAttribute('data-line')) { fix = fix.replace(/\s*\n\s*/g, ' '); }
                if (max > 0 && fix.length > max) { fix = fix.slice(0, max); }
                if (fix !== text) { setText(f, fix); setCaret(f, fix.length); }
                else if (text === '' && f.firstChild) { f.textContent = ''; }
                sync(f);
            } else if (f && f.tagName === 'TEXTAREA' && !plain) {
                autosize(f);
            }
            markDirty();
        });
        editor.addEventListener('change', markDirty);

        editor.addEventListener('keydown', function (e) {
            var f = e.target;
            if (e.isComposing || !f || !f.classList) { return; }
            if (f.hasAttribute('data-uw-grip')) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openGripMenu(f.closest('[data-uw-block]'), f); }
                return;
            }
            var isOpt = f.tagName === 'INPUT' && !!f.closest('.uw-opt');
            if (!isOpt && !f.classList.contains('uw-ed')) { return; }
            var mod = e.ctrlKey || e.metaKey || e.altKey;
            var fs = f.closest('[data-uw-block]');
            var text = getText(f);
            var rg = caretRange(f);
            var collapsed = rg[0] === rg[1];

            // Titre de la page : Entrée passe au premier bloc.
            if (!fs) {
                if (e.key === 'Enter') { e.preventDefault(); if (!stepStop(f, 1)) { focusBlock(all()[0], false); } }
                else if (e.key === 'ArrowDown' && collapsed && rg[0] === text.length) { if (stepStop(f, 1)) { e.preventDefault(); } }
                return;
            }
            var type = typeOf(fs);

            if (e.altKey && !e.ctrlKey && !e.metaKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) {
                e.preventDefault();
                if (moveBlock(fs, e.key === 'ArrowUp' ? -1 : 1)) { f.focus(); }
                return;
            }

            // Choix d'un sondage : Entrée ajoute un choix, Retour arrière sur un choix vide le retire.
            if (isOpt) {
                var row = f.closest('.uw-opt');
                if (e.key === 'Enter' && !mod) {
                    e.preventDefault();
                    var nxt = row.nextElementSibling ? row.nextElementSibling.querySelector('input') : null;
                    var made = f.value !== '' ? addOption(fs, row) : null;
                    var go = made || nxt;
                    if (go) { go.focus(); markDirty(); } else { stepStop(f, 1); }
                } else if (e.key === 'Backspace' && !mod && f.value === '' && fs.querySelectorAll('.uw-opt').length > 2) {
                    e.preventDefault();
                    var prev = row.previousElementSibling ? row.previousElementSibling.querySelector('input') : null;
                    var after = row.nextElementSibling ? row.nextElementSibling.querySelector('input') : null;
                    row.parentNode.removeChild(row);
                    renumber();
                    markDirty();
                    var tgt = prev || after;
                    if (tgt) { tgt.focus(); tgt.setSelectionRange(tgt.value.length, tgt.value.length); }
                } else if (e.key === 'ArrowUp' && !mod) { if (stepStop(f, -1)) { e.preventDefault(); } }
                else if (e.key === 'ArrowDown' && !mod) { if (stepStop(f, 1)) { e.preventDefault(); } }
                return;
            }

            if (e.key === '/' && text === '' && !mod && type !== 'poll') {
                e.preventDefault();
                openTypes({ mode: 'replace', fs: fs }, f, function () { focusEnd(f); });
                return;
            }
            if (e.key === 'Enter' && !mod) {
                if (e.shiftKey) { if (f.hasAttribute('data-line')) { e.preventDefault(); } return; }
                e.preventDefault();
                if (type === 'poll') { var first = fs.querySelector('.uw-opt input'); if (first) { first.focus(); } return; }
                if (type === 'todo' && text === '') { var plainBlock = replaceBlock(fs, 'p'); changed(); focusBlock(plainBlock, false); return; }
                if (all().length >= MAX_BLOCKS) { toast('Cette page a atteint ' + MAX_BLOCKS + ' blocs.', true); return; }
                var before = text.slice(0, rg[0]);
                var rest = text.slice(rg[1]);
                setText(f, before);
                var nb = insertAfter(fs, type === 'todo' ? 'todo' : 'p');
                var nf = nb && mainField(nb);
                if (nf) { setText(nf, rest); }
                changed();
                if (nf) { setCaret(nf, 0); }
                return;
            }
            if (e.key === 'Backspace' && !mod) {
                if (text === '') {
                    if (type === 'poll' && [].some.call(fs.querySelectorAll('.uw-opt input'), function (o) { return o.value !== ''; })) { return; }
                    if (all().length > 1 || type !== 'p') { e.preventDefault(); removeBlock(fs); }
                } else if (collapsed && rg[0] === 0 && (type === 'h' || type === 'todo' || type === 'call')) {
                    e.preventDefault();
                    var asText = replaceBlock(fs, 'p', text);
                    changed();
                    if (asText) { setCaret(mainField(asText), 0); }
                }
                return;
            }
            if (e.key === 'ArrowUp' && !mod && collapsed && rg[0] === 0) { if (stepStop(f, -1)) { e.preventDefault(); } return; }
            if (e.key === 'ArrowDown' && !mod && collapsed && rg[0] === text.length) { if (stepStop(f, 1)) { e.preventDefault(); } }
        });

        // —— Citer une unité, un membre, une opération ou une page : « @ » ou « [[ » ouvre une recherche ——
        // Le texte garde une marque simple « [[type:identifiant|libellé]] » ; à l'affichage, le serveur la remplace par un lien
        // si le lecteur voit l'objet, par « Lien masqué » sinon. Sans ce script, la marque se tape à la main ou le lien se pose
        // depuis « Lier à… » : rien n'en dépend.
        var mentionUrl = editor.getAttribute('data-uw-mention-search') || '';
        var mentionSpace = editor.getAttribute('data-uw-mention-space') || '0';
        var MENTION_ICONS = {
            unite: 'M4 21V8l8-5 8 5v13|M9 21v-6h6v6',
            membre: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z|M4 21a8 8 0 0 1 16 0',
            operation: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z|M12 7v5l3 2',
            page: 'M6 3h9l4 4v14H6z|M9 13h7|M9 17h7'
        };
        var mpop = null;
        var closeMention = function () {
            if (!mpop) { return; }
            var m = mpop;
            mpop = null;
            document.removeEventListener('mousedown', m.outside, true);
            window.removeEventListener('resize', m.place);
            window.removeEventListener('scroll', m.place, true);
            if (m.node.parentNode) { m.node.parentNode.removeChild(m.node); }
        };
        var caretRect = function (f) {
            if (f.tagName === 'DIV') {
                var sel = window.getSelection();
                if (sel && sel.rangeCount && f.contains(sel.anchorNode)) {
                    var r = sel.getRangeAt(0).getBoundingClientRect();
                    if (r && (r.width || r.height)) { return { left: r.left, top: r.top, bottom: r.bottom }; }
                }
            }
            return rectOf(f);
        };
        var openMention = function (f, pos, trigger) {
            if (!window.JnetLinks || !mentionUrl) { return; }
            closePop(false);
            closeMention();
            var node = el('div', 'uw-pop');
            node.setAttribute('role', 'dialog');
            node.setAttribute('aria-label', 'Citer une unité, un membre, une opération ou une page');
            var row = el('div', 'uw-pop__q');
            row.appendChild(el('span', '', trigger === '[[' ? '[[' : '@'));
            var q = document.createElement('input');
            q.type = 'text';
            q.autocomplete = 'off';
            q.maxLength = 80;
            q.setAttribute('role', 'combobox');
            q.setAttribute('aria-expanded', 'true');
            q.setAttribute('aria-controls', 'uw-mention-list');
            q.setAttribute('aria-label', 'Chercher une unité, un membre, une opération ou une page');
            q.placeholder = 'Unité, membre, opération, page';
            row.appendChild(q);
            node.appendChild(row);
            var ul = el('ul');
            ul.id = 'uw-mention-list';
            ul.setAttribute('role', 'listbox');
            ul.setAttribute('aria-label', 'Résultats');
            node.appendChild(ul);
            var shown = [];
            var active = 0;
            var seq = 0;
            var timer = null;
            var mark = function (i) {
                if (!shown.length) { return; }
                active = (i + shown.length) % shown.length;
                shown.forEach(function (s, k) { s.li.setAttribute('aria-selected', k === active ? 'true' : 'false'); });
                q.setAttribute('aria-activedescendant', shown[active].li.id);
                shown[active].li.scrollIntoView({ block: 'nearest' });
            };
            var note = function (text) {
                ul.textContent = '';
                shown = [];
                ul.appendChild(el('li', 'uw-pop__none', text));
            };
            var cancel = function () {
                closeMention();
                // La saisie n'était pas une citation : on rend ce qui avait été tapé.
                var t = getText(f);
                setText(f, t.slice(0, pos) + trigger + t.slice(pos));
                setCaret(f, pos + trigger.length);
                markDirty();
            };
            var choose = function (r) {
                var token = '[[' + r.type + ':' + String(r.ref).split(':').slice(1).join(':') + '|' + String(r.title).replace(/[\[\]|\r\n]+/g, ' ').trim().slice(0, 80) + ']] ';
                var t = getText(f);
                var max = parseInt(f.getAttribute('data-max') || f.getAttribute('maxlength') || '0', 10);
                if (max > 0 && t.length + token.length > max) {
                    toast('Pas assez de place dans ce bloc pour la citation.', true);
                    return;
                }
                closeMention();
                setText(f, t.slice(0, pos) + token + t.slice(pos));
                setCaret(f, pos + token.length);
                markDirty();
            };
            var render = function (rows) {
                ul.textContent = '';
                shown = [];
                if (!rows.length) { note('Aucun résultat parmi ce que vous pouvez voir.'); return; }
                rows.forEach(function (r, k) {
                    var li = el('li');
                    li.id = 'uw-mention-' + k;
                    li.setAttribute('role', 'option');
                    li.setAttribute('aria-selected', 'false');
                    var ic = el('span', 'uw-pop__i');
                    ic.appendChild(icon(MENTION_ICONS[r.type] || MENTION_ICONS.page));
                    var tx = el('span', 'uw-pop__t');
                    tx.appendChild(el('b', '', r.title));
                    tx.appendChild(el('small', '', r.typeLabel + (r.sub ? ' · ' + r.sub : '')));
                    li.appendChild(ic);
                    li.appendChild(tx);
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
                if (text.length < 2) { note('Tapez au moins deux lettres.'); return; }
                var mine = ++seq;
                window.JnetLinks.search(mentionUrl, text, 'unite,membre,operation,page', mentionSpace).then(function (rows) { if (mine === seq && mpop) { render(rows); } });
            };
            var place = function () {
                var r = caretRect(f);
                var w = node.offsetWidth;
                var hh = node.offsetHeight;
                var left = Math.min(Math.max(8, r.left), Math.max(8, window.innerWidth - w - 8));
                var below = r.bottom + 6;
                var top = below + hh <= window.innerHeight - 8 ? below : Math.max(8, Math.min(r.top - hh - 6, window.innerHeight - hh - 8));
                node.style.left = left + 'px';
                node.style.top = top + 'px';
            };
            node.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown') { e.preventDefault(); mark(active + 1); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); mark(active - 1); }
                else if (e.key === 'Enter') { e.preventDefault(); if (shown[active]) { choose(shown[active].r); } }
                else if (e.key === 'Escape' || e.key === 'Tab') { e.preventDefault(); e.stopPropagation(); cancel(); }
                else if (e.key === 'Backspace' && q.value === '') { e.preventDefault(); cancel(); }
            });
            q.addEventListener('input', function () { window.clearTimeout(timer); timer = window.setTimeout(run, 180); });
            var outside = function (e) { if (!node.contains(e.target)) { cancel(); } };
            document.addEventListener('mousedown', outside, true);
            window.addEventListener('resize', place);
            window.addEventListener('scroll', place, true);
            document.body.appendChild(node);
            mpop = { node: node, outside: outside, place: place };
            note('Tapez au moins deux lettres.');
            place();
            q.focus();
        };
        editor.addEventListener('input', function (e) {
            var f = e.target;
            if (e.isComposing || !f || !f.classList || !f.classList.contains('uw-ed') || !f.closest('[data-uw-block]')) { return; }
            if (!window.JnetLinks || !mentionUrl || mpop) { return; }
            var type = typeOf(f.closest('[data-uw-block]'));
            if (type === 'poll' || type === 'count' || type === 'div') { return; }
            if (e.inputType !== 'insertText' || (e.data !== '@' && e.data !== '[')) { return; }
            var rg = caretRange(f);
            if (rg[0] !== rg[1]) { return; }
            var text = getText(f);
            var before = text.slice(0, rg[0]);
            var trigger = '';
            if (before.slice(-2) === '[[') { trigger = '[['; }
            else if (before.slice(-1) === '@' && (before.length === 1 || /[\s(]/.test(before.charAt(before.length - 2)))) { trigger = '@'; }
            if (trigger === '') { return; }
            var pos = rg[0] - trigger.length;
            setText(f, text.slice(0, pos) + text.slice(rg[0]));
            setCaret(f, pos);
            openMention(f, pos, trigger);
        });

        // Ctrl+S (ou Cmd+S) : enregistre tout de suite, sans quitter la page.
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && !e.altKey && String(e.key).toLowerCase() === 's') {
                e.preventDefault();
                save();
            }
        });

        // Ouverture : curseur dans le titre quand on arrive depuis « Nouvelle page » ou « Modifier ».
        var title = editor.querySelector('.uw-title');
        if (title && (window.location.hash === '#page-title' || getText(title) === '')) { setCaret(title, getText(title).length); }
    }

    // —— Photos en plein écran (← → Échap) : image, puis panneau latéral comme dans le design ——
    var photos = [].slice.call(document.querySelectorAll('[data-uw-photo]'));
    if (photos.length && hasDialog) {
        var viewer = null;
        var index = 0;
        var showPhoto = function (i) {
            index = (i + photos.length) % photos.length;
            var a = photos[index];
            var img = viewer.querySelector('img');
            img.src = a.getAttribute('href');
            img.alt = a.getAttribute('data-title') || '';
            viewer.querySelector('h3').textContent = a.getAttribute('data-title') || '';
            viewer.querySelector('.uw-viewer__n').textContent = pad2(index + 1) + ' / ' + pad2(photos.length);
            [['album', 'data-album'], ['by', 'data-by'], ['date', 'data-date']].forEach(function (f) {
                var row = viewer.querySelector('[data-row="' + f[0] + '"]');
                var v = a.getAttribute(f[1]) || '';
                row.hidden = v === '';
                row.querySelector('dd').textContent = v;
            });
            viewer.querySelector('[data-viewer-open]').href = a.getAttribute('data-open') || a.getAttribute('href');
            var share = viewer.querySelector('[data-viewer-share]');
            if (share) {
                var url = a.getAttribute('data-share');
                share.hidden = !url;
                if (url) {
                    share.href = url;
                    share.setAttribute('data-kind', a.getAttribute('data-kind') || 'file');
                    share.setAttribute('data-item', a.getAttribute('data-item') || '');
                    share.setAttribute('data-title', a.getAttribute('data-name') || a.getAttribute('data-title') || '');
                }
            }
        };
        var openViewer = function (i, opener) {
            viewer = el('dialog', 'uw-viewer');
            viewer.setAttribute('aria-label', 'Photo en plein écran');
            var fig = el('figure');
            fig.appendChild(el('img'));
            var prev = el('button', 'uw-viewer__nav uw-viewer__nav--prev', '←');
            var next = el('button', 'uw-viewer__nav uw-viewer__nav--next', '→');
            prev.type = next.type = 'button';
            prev.setAttribute('aria-label', 'Photo précédente');
            next.setAttribute('aria-label', 'Photo suivante');
            prev.addEventListener('click', function () { showPhoto(index - 1); });
            next.addEventListener('click', function () { showPhoto(index + 1); });
            if (photos.length > 1) { fig.appendChild(prev); fig.appendChild(next); }
            var side = el('div', 'uw-viewer__side');
            var top = el('div', 'uw-viewer__top');
            top.appendChild(el('span', 'uw-viewer__n'));
            var close = el('button', 'uw-viewer__close', 'Fermer');
            close.type = 'button';
            close.addEventListener('click', function () { viewer.close(); });
            top.appendChild(close);
            side.appendChild(top);
            side.appendChild(el('h3'));
            var dl = el('dl');
            [['album', 'Album'], ['by', 'Déposée par'], ['date', 'Date']].forEach(function (f) {
                var row = el('div');
                row.setAttribute('data-row', f[0]);
                row.appendChild(el('dt', '', f[1]));
                row.appendChild(el('dd'));
                dl.appendChild(row);
            });
            side.appendChild(dl);
            var acts = el('div', 'uw-viewer__acts');
            var open = el('a', 'ja-b1 sm', 'Ouvrir l’original');
            open.setAttribute('data-viewer-open', '');
            open.target = '_blank';
            open.rel = 'noopener';
            acts.appendChild(open);
            if (document.getElementById('uw-share-tpl')) {
                var sh = el('a', 'uw-link', 'Partager');
                sh.setAttribute('data-viewer-share', '');
                sh.setAttribute('data-uw-share', '');
                acts.appendChild(sh);
            }
            side.appendChild(acts);
            viewer.appendChild(fig);
            viewer.appendChild(side);
            viewer.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowLeft') { e.preventDefault(); showPhoto(index - 1); }
                if (e.key === 'ArrowRight') { e.preventDefault(); showPhoto(index + 1); }
            });
            openModal(viewer, opener);
            showPhoto(i);
            close.focus();
        };
        photos.forEach(function (a, i) {
            a.addEventListener('click', function (e) {
                if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) { return; }
                e.preventDefault();
                openViewer(i, a);
            });
        });
    }
    if (hasDialog) {
        document.querySelectorAll('[data-uw-preview]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) { return; }
                e.preventDefault();
                var dlg = el('dialog', 'uw-viewer uw-viewer--doc');
                dlg.setAttribute('aria-label', 'Aperçu : ' + (a.getAttribute('data-title') || 'document'));
                var bar = el('div', 'uw-viewer__bar');
                bar.appendChild(el('b', '', a.getAttribute('data-title') || ''));
                var open = el('a', 'uw-link', 'Ouvrir le fichier');
                open.href = a.getAttribute('data-open') || a.href;
                bar.appendChild(open);
                var close = el('button', 'uw-viewer__close', 'Fermer');
                close.type = 'button';
                close.addEventListener('click', function () { dlg.close(); });
                bar.appendChild(close);
                var frame = el('iframe');
                frame.title = 'Aperçu : ' + (a.getAttribute('data-title') || '');
                frame.src = a.href;
                dlg.appendChild(bar);
                dlg.appendChild(frame);
                openModal(dlg, a);
                close.focus();
            });
        });
    }

    // —— Partager : la même page, en fenêtre ——
    var shareTpl = document.getElementById('uw-share-tpl');
    if (shareTpl && hasDialog) {
        document.addEventListener('click', function (e) {
            var a = e.target.closest ? e.target.closest('[data-uw-share]') : null;
            if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) { return; }
            e.preventDefault();
            var opener = a;
            var openDlg = document.querySelector('dialog.uw-viewer[open]');
            if (openDlg) { openDlg.close(); }
            var dlg = el('dialog', 'uw-modal');
            dlg.setAttribute('aria-labelledby', 'uw-share-h');
            var box = el('div', 'uw-modal__box');
            var head = el('div', 'uw-modal__head');
            var h = el('h2', '', 'Partager avec une autre unité');
            h.id = 'uw-share-h';
            head.appendChild(h);
            var close = el('button', 'uw-viewer__close', 'Fermer');
            close.type = 'button';
            close.addEventListener('click', function () { dlg.close(); });
            head.appendChild(close);
            box.appendChild(head);
            var form = shareTpl.content.firstElementChild.cloneNode(true);
            form.querySelector('[data-uw-share-kind]').value = a.getAttribute('data-kind') || 'exchange';
            form.querySelector('[data-uw-share-item]').value = a.getAttribute('data-item') || '0';
            form.querySelector('[data-uw-share-title]').textContent = a.getAttribute('data-title') || '';
            var msg = form.querySelector('[data-uw-share-msg]');
            form.addEventListener('submit', function (ev) {
                ev.preventDefault();
                var btn = form.querySelector('button[type="submit"]');
                btn.disabled = true;
                send(form.action, new FormData(form)).then(function (res) {
                    msg.hidden = false;
                    msg.className = 'uw-share__done' + (res.ok ? '' : ' is-bad');
                    msg.textContent = res.message || (res.ok ? 'Partagé.' : 'Le partage a échoué.');
                    if (res.ok) { toast(res.message || 'Partagé.'); close.focus(); } else { btn.disabled = false; }
                });
            });
            box.appendChild(form);
            dlg.appendChild(box);
            openModal(dlg, opener);
            var sel = form.querySelector('select');
            if (sel) { sel.focus(); }
        });
    }

    // —— Relancer les sans-réponse : confirmation en ligne, sans recharger ——
    document.querySelectorAll('[data-uw-remind]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = form.querySelector('button');
            if (btn) { btn.disabled = true; }
            send(form.action, new FormData(form)).then(function (res) {
                var note = form.querySelector('.uw-note') || form.appendChild(el('span', 'uw-note'));
                note.textContent = res.message || (res.ok ? 'Rappel envoyé.' : 'Le rappel n’a pas pu être envoyé.');
                toast(note.textContent, !res.ok);
                if (!res.ok && btn) { btn.disabled = false; }
            });
        });
    });

    // —— Mémoire de l'unité et de l'onglet courants (le navigateur seulement, jamais le serveur) ——
    try {
        var unit = body.getAttribute('data-uw-unit');
        var view = body.getAttribute('data-uw-view');
        if (unit && view && view !== 'personnaliser' && view !== 'partager') {
            var tab = view === 'page' ? 'pages' : view;
            window.localStorage.setItem('jnet.unit.last', JSON.stringify({ id: unit, label: body.getAttribute('data-uw-label') || '', vue: tab }));
            window.localStorage.setItem('jnet.unit.' + unit + '.tab', tab);
            // Arrivée depuis l'écran d'entrée, sans rubrique demandée : on rouvre l'onglet où l'on était.
            if (!/[?&]vue=/.test(window.location.search) && /\/entree(\?|#|$)/.test(document.referrer || '')) {
                var wanted = window.localStorage.getItem('jnet.unit.' + unit + '.tab');
                if (wanted && wanted !== 'accueil') {
                    var link = document.querySelector('.ja-tabs a[href*="vue=' + wanted + '&"], .ja-tabs a[href$="vue=' + wanted + '"]');
                    if (link) { window.location.replace(link.href); }
                }
            }
        }
    } catch (err) {
        // Stockage indisponible (navigation privée, données bloquées) : sans effet sur la page.
    }
})();
