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

    // —— Éditeur de page : poignée, Entrée, Retour arrière, palette « / » ——
    var blocks = document.querySelector('[data-uw-blocks]');
    if (blocks) {
        var count = function () { return blocks.querySelectorAll('[data-uw-block]').length; };
        var renumber = function () {
            var all = blocks.querySelectorAll('[data-uw-block]');
            all.forEach(function (fs, i) {
                fs.querySelectorAll('[name]').forEach(function (f) {
                    var name = f.getAttribute('name') || '';
                    if (/^blocks\[[^\]]*\]/.test(name)) { f.setAttribute('name', name.replace(/^blocks\[[^\]]*\]/, 'blocks[' + i + ']')); }
                    if (name === 'act') {
                        var m = /^(up|down|del):/.exec(f.value);
                        if (m) {
                            f.value = m[1] + ':' + i;
                            if (m[1] === 'up') { f.disabled = i === 0; }
                            if (m[1] === 'down') { f.disabled = i === all.length - 1; }
                        }
                    }
                });
                var legend = fs.querySelector('legend');
                if (legend) { legend.textContent = legend.textContent.replace(/^Bloc[^:]*:/, 'Bloc ' + (i + 1) + ' :'); }
                fs.querySelectorAll('[aria-label]').forEach(function (f) {
                    f.setAttribute('aria-label', f.getAttribute('aria-label').replace(/(bloc )(\d+|)/i, function (all2, p) { return p + (i + 1); }));
                });
                fs.querySelectorAll('textarea[id^="opts-"]').forEach(function (ta) {
                    ta.id = 'opts-' + i;
                    var lab = fs.querySelector('label[for^="opts-"]');
                    if (lab) { lab.setAttribute('for', ta.id); }
                });
            });
        };
        var fieldOf = function (fs) { return fs.querySelector('textarea, input[type="text"], input:not([type])'); };
        var makeBlock = function (type) {
            var tpl = document.querySelector('template[data-uw-block-tpl="' + type + '"]');
            if (!tpl) { return null; }
            var node = tpl.content.firstElementChild.cloneNode(true);
            var id = node.querySelector('input[name$="[id]"]');
            if (id) { id.value = ''; }
            node.querySelectorAll('.uw-js-only').forEach(function (n) { n.hidden = false; });
            return node;
        };
        var focusEnd = function (f) {
            if (!f) { return; }
            f.focus();
            if (typeof f.setSelectionRange === 'function' && f.value) { f.setSelectionRange(f.value.length, f.value.length); }
        };
        var palette = null;
        var closePalette = function () {
            if (palette) {
                if (palette.owner) { palette.owner.removeAttribute('aria-expanded'); palette.owner.removeAttribute('aria-activedescendant'); }
                if (palette.node.parentNode) { palette.node.parentNode.removeChild(palette.node); }
                palette = null;
            }
        };
        var openPalette = function (fs, field) {
            closePalette();
            var types = [];
            document.querySelectorAll('template[data-uw-block-tpl]').forEach(function (t) {
                types.push({ type: t.getAttribute('data-uw-block-tpl'), label: t.getAttribute('data-label'), hint: t.getAttribute('data-hint') });
            });
            if (!types.length) { return; }
            var node = el('ul', 'uw-palette');
            node.setAttribute('role', 'listbox');
            node.setAttribute('aria-label', 'Type de bloc');
            node.id = 'uw-palette';
            types.forEach(function (t, i) {
                var li = el('li');
                li.setAttribute('role', 'option');
                li.id = 'uw-palette-' + i;
                li.setAttribute('data-type', t.type);
                var b = el('b', '', t.label);
                var s = el('small', '', t.hint);
                li.appendChild(b);
                li.appendChild(s);
                node.appendChild(li);
            });
            fs.querySelector('.uw-block__body').appendChild(node);
            palette = { node: node, owner: field, fs: fs, active: 0, items: [].slice.call(node.children) };
            field.setAttribute('aria-expanded', 'true');
            field.setAttribute('aria-controls', 'uw-palette');
            var mark = function (i) {
                palette.active = (i + palette.items.length) % palette.items.length;
                palette.items.forEach(function (n, k) { n.setAttribute('aria-selected', k === palette.active ? 'true' : 'false'); });
                field.setAttribute('aria-activedescendant', palette.items[palette.active].id);
            };
            palette.mark = mark;
            mark(0);
            node.addEventListener('mousedown', function (e) {
                var li = e.target.closest('li');
                if (li) { e.preventDefault(); choose(li.getAttribute('data-type')); }
            });
        };
        var choose = function (type) {
            if (!palette) { return; }
            var fs = palette.fs;
            closePalette();
            if (type === 'p') { focusEnd(fieldOf(fs)); return; }
            var nb = makeBlock(type);
            if (!nb) { return; }
            fs.parentNode.insertBefore(nb, fs);
            fs.parentNode.removeChild(fs);
            renumber();
            focusEnd(fieldOf(nb));
        };
        sortable(blocks, '[data-uw-block]', function () { renumber(); toast('Bloc déplacé. Enregistrez la page pour garder cet ordre.'); });
        blocks.addEventListener('keydown', function (e) {
            var field = e.target;
            var fs = field.closest ? field.closest('[data-uw-block]') : null;
            if (!fs || !/^(TEXTAREA|INPUT)$/.test(field.tagName) || field.type === 'checkbox' || e.isComposing) { return; }
            var type = (/uw-block--(\w+)/.exec(fs.className) || [])[1];
            if (palette) {
                if (e.key === 'ArrowDown') { e.preventDefault(); palette.mark(palette.active + 1); return; }
                if (e.key === 'ArrowUp') { e.preventDefault(); palette.mark(palette.active - 1); return; }
                if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); choose(palette.items[palette.active].getAttribute('data-type')); return; }
                if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); closePalette(); return; }
                closePalette();
            }
            if (['p', 'h', 'todo', 'call'].indexOf(type) === -1 || e.target.name.indexOf('[text]') === -1) { return; }
            if (e.key === '/' && field.value === '' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                e.preventDefault();
                openPalette(fs, field);
                return;
            }
            if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.metaKey && !e.altKey && count() < 150) {
                var nb = makeBlock('p');
                if (!nb) { return; }
                e.preventDefault();
                var pos = typeof field.selectionStart === 'number' ? field.selectionStart : field.value.length;
                var rest = field.value.slice(pos);
                field.value = field.value.slice(0, pos);
                fs.parentNode.insertBefore(nb, fs.nextSibling);
                var nf = fieldOf(nb);
                nf.value = rest;
                renumber();
                nf.focus();
                if (typeof nf.setSelectionRange === 'function') { nf.setSelectionRange(0, 0); }
                return;
            }
            if (e.key === 'Backspace' && field.value === '' && count() > 1) {
                var prev = fs.previousElementSibling;
                var nextEl = fs.nextElementSibling;
                e.preventDefault();
                fs.parentNode.removeChild(fs);
                renumber();
                focusEnd(fieldOf(prev || nextEl));
            }
        });
        blocks.addEventListener('blur', function () { window.setTimeout(closePalette, 120); }, true);
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
