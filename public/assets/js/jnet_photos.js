/**
 * JNET — Photothèque : lecteur plein écran (← → Échap), visages étiquetés à la main, zones masquées, commentaires
 * ancrés avec mentions, étiquettes, versement au dossier SSE. La grille et ses filtres fonctionnent sans ce script
 * (liens vers /jnet/photos/{id}). Le serveur décide de tout (droits, masques appliqués dans l'image servie, jeton CSRF) :
 * ce script ne cache rien par calque et ne reconnaît aucun visage, il envoie ce qu'un humain a dessiné.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-ph]');
    if (!root) { return; }
    var viewer = root.querySelector('[data-ph-viewer]');
    var base = root.getAttribute('data-base') || '';
    var csrf = root.getAttribute('data-csrf') || '';
    var door = root.getAttribute('data-door') === '1';
    var NS = 'http://www.w3.org/2000/svg';
    var MIN = 0.01;

    var cur = null;        // fiche de la photo ouverte (JSON du serveur)
    var tool = '';         // '', 'face', 'mask', 'zone', 'redraw'
    var maskMode = 'blur';
    var redrawTag = 0;
    var anchor = null;     // ancre du commentaire en préparation : {anchor:'zone'|'tag', x, y, w, h, tag}
    var sel = { kind: '', id: 0 };
    var showAll = false;
    var lastFocus = null;
    var listUrl = base + (window.location.search || '');
    var draft = null;
    var popover = null;
    var msgBox = null;

    // ——— Outils DOM (tout texte passe par textContent) ———

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text !== undefined && text !== null && text !== '') { n.textContent = text; }
        return n;
    }

    function icon(name) {
        var s = document.createElementNS(NS, 'svg');
        s.setAttribute('class', 'ph-ic');
        s.setAttribute('aria-hidden', 'true');
        var u = document.createElementNS(NS, 'use');
        u.setAttribute('href', '#ph-i-' + name);
        s.appendChild(u);
        return s;
    }

    function btn(cls, text, onClick, attrs) {
        var b = el('button', cls, text);
        b.type = 'button';
        if (attrs) { Object.keys(attrs).forEach(function (k) { b.setAttribute(k, attrs[k]); }); }
        if (onClick) { b.addEventListener('click', onClick); }
        return b;
    }

    function when(s) {
        var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(String(s || ''));
        return m ? m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5] : '';
    }

    function say(text, bad) {
        if (!msgBox) { return; }
        msgBox.textContent = text || '';
        msgBox.className = 'ph-msg' + (bad ? ' is-err' : '');
        msgBox.hidden = !text;
    }

    // Suppression en deux temps : le premier clic arme le bouton, le second confirme.
    function twoStep(b, label, fn) {
        var armed = false;
        var original = b.textContent;
        b.addEventListener('click', function () {
            if (!armed) {
                armed = true;
                b.textContent = label;
                window.setTimeout(function () { armed = false; b.textContent = original; }, 4000);
                return;
            }
            fn();
        });
    }

    // ——— Échanges avec le serveur ———

    function post(path, data) {
        var body = new URLSearchParams();
        Object.keys(data || {}).forEach(function (k) {
            if (data[k] !== null && data[k] !== undefined) { body.append(k, String(data[k])); }
        });
        body.append('_csrf_token', csrf);
        return fetch(base + path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf },
            body: body
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false, error: 'Réponse illisible.' }; });
        }).catch(function () { return { ok: false, error: 'Réseau indisponible : réessayez.' }; });
    }

    function getJson(path) {
        return fetch(base + path, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) {
            return r.ok ? r.json() : null;
        }).catch(function () { return null; });
    }

    function act(path, data, okText) {
        return post(path, data).then(function (res) {
            if (!res || !res.ok) {
                say((res && res.error) || 'Action refusée.', true);
                return res;
            }
            return reload().then(function () { say(okText || '', false); return res; });
        });
    }

    function reload() {
        if (!cur) { return Promise.resolve(); }
        return getJson('/' + cur.photo.id + '/donnees').then(function (d) {
            if (d) { cur = d; render(); }
        });
    }

    // ——— Ouverture, fermeture, navigation ———

    function gridIds() {
        return Array.prototype.map.call(root.querySelectorAll('[data-ph-open]'), function (a) { return parseInt(a.getAttribute('data-ph-open'), 10); });
    }

    function openPhoto(id, push) {
        return getJson('/' + id + '/donnees').then(function (d) {
            if (!d) {
                say('Photo introuvable.', true);
                return;
            }
            lastFocus = lastFocus || document.activeElement;
            cur = d;
            tool = '';
            anchor = null;
            sel = { kind: '', id: 0 };
            viewer.hidden = false;
            document.body.classList.add('ph-lock');
            render();
            if (push !== false) { window.history.pushState({ ph: id }, '', base + '/' + id + (window.location.search || '')); }
            var c = viewer.querySelector('[data-ph-close]');
            if (c) { c.focus(); }
        });
    }

    function closeViewer(push) {
        closePop();
        viewer.hidden = true;
        viewer.textContent = '';
        document.body.classList.remove('ph-lock');
        cur = null;
        tool = '';
        if (push !== false) { window.history.pushState({}, '', listUrl); }
        if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
        lastFocus = null;
    }

    function step(delta) {
        if (!cur) { return; }
        var ids = gridIds();
        var i = ids.indexOf(cur.photo.id);
        var next = i < 0 ? -1 : i + delta;
        if (next >= 0 && next < ids.length) { openPhoto(ids[next], true); }
    }

    // ——— Rendu du lecteur ———

    function render() {
        if (!cur) { return; }
        closePop();
        var p = cur.photo;
        var r = cur.rights;
        var ids = gridIds();
        var idx = ids.indexOf(p.id);
        viewer.textContent = '';
        var wrap = el('div', 'ph-v');

        // Scène
        var stage = el('div', 'ph-v__stage');
        var top = el('div', 'ph-v__top');
        var title = el('h2', '', p.title || p.caption || p.album || 'Photo terrain');
        top.appendChild(title);
        if (p.sse) {
            var pill = el('span', 'ph-pill ph-pill--sse ph-pill--inline');
            pill.appendChild(icon('lock'));
            pill.appendChild(document.createTextNode('SSE'));
            top.appendChild(pill);
        }
        var prev = btn('ph-icbtn', '', function () { step(-1); }, { 'aria-label': 'Photo précédente', 'title': 'Photo précédente (←)' });
        prev.appendChild(icon('prev'));
        prev.disabled = idx <= 0;
        var next = btn('ph-icbtn', '', function () { step(1); }, { 'aria-label': 'Photo suivante', 'title': 'Photo suivante (→)' });
        next.appendChild(icon('next'));
        next.disabled = idx < 0 || idx >= ids.length - 1;
        var close = btn('ph-icbtn', '', function () { closeViewer(true); }, { 'aria-label': 'Fermer', 'title': 'Fermer (Échap)', 'data-ph-close': '1' });
        close.appendChild(icon('close'));
        top.appendChild(prev);
        top.appendChild(next);
        top.appendChild(close);
        stage.appendChild(top);

        var canvas = el('div', 'ph-v__canvas');
        var frame = el('div', 'ph-frame');
        var img = el('img');
        img.src = p.image;
        img.alt = p.title || p.caption || 'Photo terrain';
        img.draggable = false;
        var layer = el('div', 'ph-layer' + (showAll ? ' show-all' : ''));
        layer.setAttribute('data-ph-layer', '1');
        frame.appendChild(img);
        frame.appendChild(layer);
        canvas.appendChild(frame);
        stage.appendChild(canvas);
        stage.appendChild(toolbar());
        wrap.appendChild(stage);

        // Panneau
        var side = el('aside', 'ph-v__side');
        side.setAttribute('aria-label', 'Détails de la photo');
        side.appendChild(infoSection());
        side.appendChild(faceSection());
        side.appendChild(maskSection());
        side.appendChild(labelSection());
        side.appendChild(commentSection());
        var sse = sseSection();
        if (sse) { side.appendChild(sse); }
        msgBox = el('p', 'ph-msg');
        msgBox.hidden = true;
        msgBox.setAttribute('role', 'status');
        msgBox.setAttribute('aria-live', 'polite');
        side.appendChild(msgBox);
        wrap.appendChild(side);
        viewer.appendChild(wrap);

        drawBoxes();
        bindDrawing(layer, frame);
        updateCursor();
    }

    function toolbar() {
        var r = cur.rights;
        var bar = el('div', 'ph-tools');
        var faceBtn = btn('ph-tool', 'Étiqueter un visage', function () { setTool(tool === 'face' ? '' : 'face'); }, { 'aria-pressed': tool === 'face' ? 'true' : 'false', 'data-ph-tool': 'face' });
        faceBtn.insertBefore(icon('face'), faceBtn.firstChild);
        faceBtn.disabled = !r.tag;
        bar.appendChild(faceBtn);
        if (r.maskRequested) {
            var maskBtn = btn('ph-tool', 'Masquer une zone', function () { setTool(tool === 'mask' ? '' : 'mask'); }, { 'aria-pressed': tool === 'mask' ? 'true' : 'false', 'data-ph-tool': 'mask' });
            maskBtn.insertBefore(icon('mask'), maskBtn.firstChild);
            maskBtn.disabled = !r.mask;
            bar.appendChild(maskBtn);
            var sel2 = el('select');
            sel2.setAttribute('aria-label', 'Façon de masquer');
            sel2.setAttribute('data-ph-mode', '1');
            [['blur', 'Flou'], ['pixel', 'Mosaïque'], ['fill', 'Aplat noir']].forEach(function (o) {
                var op = el('option', '', o[1]);
                op.value = o[0];
                if (o[0] === maskMode) { op.selected = true; }
                sel2.appendChild(op);
            });
            sel2.addEventListener('change', function () { maskMode = sel2.value; });
            bar.appendChild(sel2);
        }
        var zoneBtn = btn('ph-tool', 'Commenter une zone', function () { setTool(tool === 'zone' ? '' : 'zone'); }, { 'aria-pressed': tool === 'zone' ? 'true' : 'false', 'data-ph-tool': 'zone' });
        zoneBtn.insertBefore(icon('note'), zoneBtn.firstChild);
        zoneBtn.disabled = !r.comment;
        bar.appendChild(zoneBtn);
        var allBtn = btn('ph-tool', 'Afficher les noms', function () {
            showAll = !showAll;
            allBtn.setAttribute('aria-pressed', showAll ? 'true' : 'false');
            var l = viewer.querySelector('[data-ph-layer]');
            if (l) { l.classList.toggle('show-all', showAll); }
        }, { 'aria-pressed': showAll ? 'true' : 'false' });
        bar.appendChild(allBtn);
        var hint = el('p', 'ph-hint', hintText());
        hint.setAttribute('data-ph-hint', '1');
        bar.appendChild(hint);
        return bar;
    }

    function hintText() {
        if (tool === 'face') { return 'Tracez un cadre autour du visage, puis choisissez qui c’est. Échap pour annuler.'; }
        if (tool === 'mask') { return 'Tracez la zone à masquer : elle est cachée dans l’image servie aux autres, pas seulement à l’écran.'; }
        if (tool === 'zone') { return 'Tracez la zone sur laquelle porte votre commentaire.'; }
        if (tool === 'redraw') { return 'Tracez le nouveau cadre du visage.'; }
        if (cur.maskedForYou) { return 'Certaines zones de cette photo sont masquées pour vous.'; }
        return 'Étiquetage fait à la main : le site ne reconnaît aucun visage.';
    }

    function setTool(t) {
        tool = t;
        redrawTag = t === 'redraw' ? redrawTag : 0;
        closePop();
        Array.prototype.forEach.call(viewer.querySelectorAll('[data-ph-tool]'), function (b) {
            b.setAttribute('aria-pressed', b.getAttribute('data-ph-tool') === t ? 'true' : 'false');
        });
        var h = viewer.querySelector('[data-ph-hint]');
        if (h) { h.textContent = hintText(); }
        updateCursor();
    }

    function updateCursor() {
        var l = viewer.querySelector('[data-ph-layer]');
        if (l) { l.classList.toggle('is-drawing', tool !== ''); }
    }

    // ——— Cadres sur l'image ———

    function box(cls, r, label, onClick) {
        var b = el('div', 'ph-box ' + cls);
        b.style.left = (r.x * 100) + '%';
        b.style.top = (r.y * 100) + '%';
        b.style.width = (r.w * 100) + '%';
        b.style.height = (r.h * 100) + '%';
        if (label) { b.appendChild(el('span', 'ph-box__lbl', label)); }
        if (onClick) {
            b.tabIndex = 0;
            b.addEventListener('click', function (e) { if (tool === '') { e.stopPropagation(); onClick(); } });
            b.addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && tool === '') { e.preventDefault(); onClick(); } });
        }
        return b;
    }

    function drawBoxes() {
        var layer = viewer.querySelector('[data-ph-layer]');
        if (!layer) { return; }
        layer.textContent = '';
        cur.masks.forEach(function (m) {
            var b = box('ph-box--mask', m, '', function () { select('mask', m.id); });
            b.setAttribute('aria-label', 'Zone masquée');
            if (sel.kind === 'mask' && sel.id === m.id) { b.classList.add('is-on'); }
            layer.appendChild(b);
        });
        cur.tags.forEach(function (t) {
            var b = box('ph-box--face' + (t.type === 'sse' ? ' ph-box--sse' : ''), t, t.label, function () { select('tag', t.id); });
            b.setAttribute('aria-label', 'Visage : ' + t.label);
            if (sel.kind === 'tag' && sel.id === t.id) { b.classList.add('is-on'); }
            layer.appendChild(b);
        });
        if (sel.kind === 'comment') {
            var c = cur.comments.filter(function (x) { return x.id === sel.id; })[0];
            if (c && c.anchor && c.anchor.kind === 'zone') {
                layer.appendChild(box('ph-box--zone is-on', c.anchor, '', null));
            }
        }
        if (anchor && anchor.anchor === 'zone') {
            layer.appendChild(box('ph-box--zone is-on', anchor, '', null));
        }
    }

    function select(kind, id) {
        sel = (sel.kind === kind && sel.id === id) ? { kind: '', id: 0 } : { kind: kind, id: id };
        drawBoxes();
        Array.prototype.forEach.call(viewer.querySelectorAll('[data-ph-row]'), function (row) {
            row.classList.toggle('is-on', row.getAttribute('data-ph-row') === sel.kind + ':' + sel.id);
        });
    }

    // ——— Dessin au pointeur ———

    function bindDrawing(layer, frame) {
        var start = null;
        function pos(e) {
            var rc = layer.getBoundingClientRect();
            return {
                x: Math.min(1, Math.max(0, (e.clientX - rc.left) / rc.width)),
                y: Math.min(1, Math.max(0, (e.clientY - rc.top) / rc.height))
            };
        }
        function rectOf(a, b) {
            var x = Math.min(a.x, b.x), y = Math.min(a.y, b.y);
            return { x: x, y: y, w: Math.abs(a.x - b.x), h: Math.abs(a.y - b.y) };
        }
        layer.addEventListener('pointerdown', function (e) {
            if (tool === '' || e.button > 0) { return; }
            e.preventDefault();
            closePop();
            start = pos(e);
            try { layer.setPointerCapture(e.pointerId); } catch (x) { /* navigateur sans capture */ }
            draft = box('ph-box--draft' + (tool === 'mask' ? ' ph-box--mask' : (tool === 'zone' ? ' ph-box--zone' : '')), { x: start.x, y: start.y, w: 0, h: 0 }, '', null);
            layer.appendChild(draft);
        });
        layer.addEventListener('pointermove', function (e) {
            if (!start || !draft) { return; }
            var r = rectOf(start, pos(e));
            draft.style.left = (r.x * 100) + '%';
            draft.style.top = (r.y * 100) + '%';
            draft.style.width = (r.w * 100) + '%';
            draft.style.height = (r.h * 100) + '%';
        });
        function finish(e, cancel) {
            if (!start) { return; }
            var r = rectOf(start, pos(e));
            start = null;
            if (draft && draft.parentNode) { draft.parentNode.removeChild(draft); }
            draft = null;
            if (cancel || r.w < MIN || r.h < MIN) { return; }
            drawn(r, e);
        }
        layer.addEventListener('pointerup', function (e) { finish(e, false); });
        layer.addEventListener('pointercancel', function (e) { finish(e, true); });
    }

    function round(r) {
        return { x: +r.x.toFixed(6), y: +r.y.toFixed(6), w: +r.w.toFixed(6), h: +r.h.toFixed(6) };
    }

    function drawn(raw, e) {
        var r = round(raw);
        if (tool === 'face') {
            openTargetPicker(r, e);
        } else if (tool === 'redraw' && redrawTag) {
            var id = redrawTag;
            setTool('');
            act('/' + cur.photo.id + '/visages/' + id, { x: r.x, y: r.y, w: r.w, h: r.h }, 'Cadre modifié.');
        } else if (tool === 'mask') {
            act('/' + cur.photo.id + '/masques', { x: r.x, y: r.y, w: r.w, h: r.h, mode: maskMode }, 'Zone masquée dans l’image.').then(function (res) {
                if (res && res.ok) { refreshImage(); }
            });
        } else if (tool === 'zone') {
            anchor = { anchor: 'zone', x: r.x, y: r.y, w: r.w, h: r.h };
            setTool('');
            drawBoxes();
            var ta = viewer.querySelector('[data-ph-comment-body]');
            if (ta) { ta.focus(); }
            refreshAnchorNote();
        }
    }

    // Nouvelle adresse d'image après un changement de masques : le navigateur ne garde pas l'ancienne.
    function refreshImage() {
        var img = viewer.querySelector('.ph-frame img');
        if (img && cur) { img.src = cur.photo.image; }
    }

    // ——— Choix de la personne d'un visage ———

    function closePop() {
        if (popover && popover.parentNode) { popover.parentNode.removeChild(popover); }
        popover = null;
    }

    function openTargetPicker(rect, e, existingTag) {
        closePop();
        var pop = el('div', 'ph-pop');
        pop.setAttribute('role', 'dialog');
        pop.setAttribute('aria-label', 'Qui est-ce ?');
        pop.appendChild(el('h4', '', 'Qui est-ce ?'));
        var kind = 'member';
        var segWrap = el('div', 'ph-seg');
        var kinds = [['member', 'Membre']];
        if (cur.rights.tagSse) { kinds.push(['sse', 'Personne SSE']); }
        kinds.push(['unknown', 'Inconnu']);
        segWrap.style.gridTemplateColumns = 'repeat(' + kinds.length + ', 1fr)';
        var input = el('input');
        input.type = 'text';
        input.maxLength = 60;
        input.autocomplete = 'off';
        input.placeholder = 'Nom ou indicatif';
        input.setAttribute('aria-label', 'Chercher une personne');
        var list = el('ul', 'ph-suggest');
        list.hidden = true;
        function submit(type, target) {
            var data = { type: type, target: target || '' };
            closePop();
            if (existingTag) {
                setTool('');
                act('/' + cur.photo.id + '/visages/' + existingTag, data, 'Visage mis à jour.');
            } else {
                data.x = rect.x; data.y = rect.y; data.w = rect.w; data.h = rect.h;
                setTool('');
                act('/' + cur.photo.id + '/visages', data, 'Visage étiqueté.');
            }
        }
        var timer = 0;
        function search() {
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                var q = input.value.trim();
                list.textContent = '';
                list.hidden = true;
                if (q.length < 1) { return; }
                getJson('/cibles?type=' + kind + '&q=' + encodeURIComponent(q)).then(function (res) {
                    list.textContent = '';
                    var rows = (res && res.results) || [];
                    list.hidden = false;
                    if (!rows.length) { list.appendChild(el('li', 'ph-empty-line', 'Aucune personne trouvée.')); return; }
                    rows.forEach(function (it) {
                        var li = el('li');
                        var b = btn('', '', function () { submit(it.type, it.id); });
                        b.appendChild(el('span', '', it.label));
                        if (it.sub) { b.appendChild(el('small', '', it.sub)); }
                        li.appendChild(b);
                        list.appendChild(li);
                    });
                });
            }, 180);
        }
        kinds.forEach(function (k) {
            var b = btn('', k[1], function () {
                if (k[0] === 'unknown') { submit('unknown', ''); return; }
                kind = k[0];
                Array.prototype.forEach.call(segWrap.children, function (c) { c.setAttribute('aria-pressed', 'false'); });
                b.setAttribute('aria-pressed', 'true');
                input.value = '';
                list.textContent = '';
                list.hidden = true;
                input.focus();
            }, { 'aria-pressed': k[0] === 'member' ? 'true' : 'false' });
            segWrap.appendChild(b);
        });
        input.addEventListener('input', search);
        pop.appendChild(segWrap);
        pop.appendChild(input);
        pop.appendChild(list);
        var cancel = btn('ph-act', 'Annuler', function () { closePop(); setTool(''); });
        cancel.style.marginTop = '10px';
        pop.appendChild(cancel);
        document.body.appendChild(pop);
        // Près du cadre tracé, jamais hors de l'écran.
        var layer = viewer.querySelector('[data-ph-layer]');
        var rc = layer.getBoundingClientRect();
        var left = rc.left + (rect ? (rect.x + rect.w) * rc.width + 8 : 24);
        var topPos = rc.top + (rect ? rect.y * rc.height : 24);
        popover = pop;
        var pw = pop.offsetWidth, ph = pop.offsetHeight;
        pop.style.left = Math.max(8, Math.min(left, window.innerWidth - pw - 8)) + 'px';
        pop.style.top = Math.max(8, Math.min(topPos, window.innerHeight - ph - 8)) + 'px';
        input.focus();
    }

    // ——— Sections du panneau ———

    function section(title, count) {
        var s = el('section', 'ph-sec');
        var h = el('h3', '', title);
        if (count !== undefined && count !== null) { h.appendChild(el('small', '', String(count))); }
        s.appendChild(h);
        return s;
    }

    function infoSection() {
        var p = cur.photo;
        var s = section('Photo');
        var dl = el('dl', 'ph-dl');
        [['Auteur', p.author], ['Unité', p.unit], ['Album', p.album], ['Grille', p.grid], ['Date', when(p.when)], ['Appareil', p.device]].forEach(function (row) {
            if (!row[1]) { return; }
            dl.appendChild(el('dt', '', row[0]));
            dl.appendChild(el('dd', '', row[1]));
        });
        s.appendChild(dl);
        var text = p.description || (p.title ? p.caption : '');
        if (text) { s.appendChild(el('p', 'ph-empty-line', text)); }
        if (p.original) {
            var a = el('a', 'ph-act', 'Ouvrir l’original (sans masque)');
            a.href = p.original;
            a.target = '_blank';
            a.rel = 'noopener';
            a.style.display = 'inline-block';
            a.style.marginTop = '10px';
            s.appendChild(a);
        }
        return s;
    }

    function faceSection() {
        var s = section('Visages', cur.tags.length);
        if (!cur.ready) {
            s.appendChild(el('p', 'ph-empty-line', 'Disponible après la mise à jour de la base.'));
            return s;
        }
        if (!cur.tags.length) { s.appendChild(el('p', 'ph-empty-line', 'Aucun visage étiqueté.')); return s; }
        var ul = el('ul', 'ph-list');
        cur.tags.forEach(function (t) {
            var li = el('li', 'ph-row' + (sel.kind === 'tag' && sel.id === t.id ? ' is-on' : ''));
            li.setAttribute('data-ph-row', 'tag:' + t.id);
            var main = btn('ph-link', '', function () { select('tag', t.id); });
            main.appendChild(document.createTextNode(t.label));
            var sub = [t.type === 'member' ? 'Membre' : (t.type === 'sse' ? 'Fichiers SSE' + (t.sub ? ' · ' + t.sub : '') : ''), t.by ? 'par ' + t.by : ''].filter(Boolean).join(' · ');
            main.appendChild(el('small', '', sub));
            li.appendChild(main);
            if (t.canEdit) {
                li.appendChild(btn('ph-act', 'Changer', function () { openTargetPicker(null, null, t.id); }));
                li.appendChild(btn('ph-act', 'Cadre', function () { redrawTag = t.id; setTool('redraw'); }));
                if (cur.rights.mask) {
                    li.appendChild(btn('ph-act', 'Masquer', function () {
                        act('/' + cur.photo.id + '/masques', { x: t.x, y: t.y, w: t.w, h: t.h, mode: maskMode }, 'Visage masqué dans l’image.').then(function (res) { if (res && res.ok) { refreshImage(); } });
                    }));
                }
                var rm = btn('ph-act ph-act--bad', 'Retirer', null);
                twoStep(rm, 'Confirmer', function () { act('/' + cur.photo.id + '/visages/' + t.id + '/retirer', {}, 'Visage retiré.'); });
                li.appendChild(rm);
            }
            ul.appendChild(li);
        });
        s.appendChild(ul);
        return s;
    }

    function maskSection() {
        var s = section('Zones masquées', cur.masks.length);
        if (!cur.gd && cur.rights.maskRequested) {
            s.appendChild(el('p', 'ph-warn', 'Le masquage est indisponible sur ce serveur (extension GD absente) : aucune zone ne peut être posée.'));
        }
        if (cur.maskedForYou) {
            s.appendChild(el('p', 'ph-warn', 'Ces zones sont cachées dans l’image qui vous est servie : le serveur ne vous donne pas ce qu’il y a dessous.'));
        }
        if (cur.photo.original) {
            s.appendChild(el('p', 'ph-warn', 'Vous voyez l’original : les autres lecteurs reçoivent l’image où ces zones sont déjà masquées.'));
        }
        if (!cur.masks.length) { s.appendChild(el('p', 'ph-empty-line', 'Aucune zone masquée.')); return s; }
        var ul = el('ul', 'ph-list');
        var names = { blur: 'Flou', pixel: 'Mosaïque', fill: 'Aplat noir' };
        cur.masks.forEach(function (m) {
            var li = el('li', 'ph-row' + (sel.kind === 'mask' && sel.id === m.id ? ' is-on' : ''));
            li.setAttribute('data-ph-row', 'mask:' + m.id);
            var main = btn('ph-link ph-link--inline', names[m.mode] || 'Zone', function () { select('mask', m.id); });
            li.appendChild(main);
            if (m.canRemove) {
                var rm = btn('ph-act ph-act--bad', 'Retirer', null);
                twoStep(rm, 'Confirmer', function () {
                    act('/' + cur.photo.id + '/masques/' + m.id + '/retirer', {}, 'Zone retirée.').then(function (res) { if (res && res.ok) { refreshImage(); } });
                });
                li.appendChild(rm);
            }
            ul.appendChild(li);
        });
        s.appendChild(ul);
        return s;
    }

    function labelSection() {
        var s = section('Étiquettes', cur.labels.length);
        if (cur.labels.length) {
            var ul = el('ul', 'ph-chips');
            cur.labels.forEach(function (l) {
                var li = el('li', 'ph-chip');
                li.appendChild(el('span', '', l));
                if (cur.rights.label) {
                    var x = btn('', '', function () { act('/' + cur.photo.id + '/etiquettes/retirer', { label: l }, ''); }, { 'aria-label': 'Retirer l’étiquette ' + l });
                    x.appendChild(icon('close'));
                    x.firstChild.style.width = '12px';
                    x.firstChild.style.height = '12px';
                    li.appendChild(x);
                }
                ul.appendChild(li);
            });
            s.appendChild(ul);
        } else {
            s.appendChild(el('p', 'ph-empty-line', 'Aucune étiquette.'));
        }
        if (cur.rights.label) {
            var form = el('form', 'ph-form');
            var row = el('div', 'ph-inline');
            var input = el('input');
            input.type = 'text';
            input.maxLength = 40;
            input.placeholder = 'Nouvelle étiquette';
            input.setAttribute('aria-label', 'Nouvelle étiquette');
            row.appendChild(input);
            var add = el('button', 'ph-btn ph-btn--ghost', 'Ajouter');
            add.type = 'submit';
            row.appendChild(add);
            form.appendChild(row);
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var v = input.value.trim();
                if (v) { act('/' + cur.photo.id + '/etiquettes', { label: v }, ''); }
            });
            s.appendChild(form);
        }
        return s;
    }

    function mentionHtml(parent, text) {
        var re = /(@[\p{L}\p{N}_.\-]{2,40})/u;
        text.split(re).forEach(function (part) {
            if (re.test(part) && part.charAt(0) === '@') { parent.appendChild(el('span', 'mention', part)); }
            else if (part) { parent.appendChild(document.createTextNode(part)); }
        });
    }

    function commentSection() {
        var s = section('Commentaires', cur.comments.length);
        s.setAttribute('data-ph-comments', '1');
        if (!cur.comments.length) { s.appendChild(el('p', 'ph-empty-line', 'Aucun commentaire pour l’instant.')); }
        cur.comments.forEach(function (c) {
            var item = el('article', 'ph-comment');
            item.setAttribute('data-ph-row', 'comment:' + c.id);
            var head = el('header');
            head.appendChild(el('b', '', c.author));
            head.appendChild(el('time', '', when(c.when)));
            if (c.edited) { head.appendChild(el('em', '', 'modifié')); }
            item.appendChild(head);
            if (c.anchor) {
                var lab = c.anchor.kind === 'tag' ? 'Sur un visage' : 'Sur une zone';
                var a = btn('ph-anchor', lab, function () { select('comment', c.id); if (c.anchor.kind === 'tag') { sel = { kind: 'tag', id: c.anchor.tag }; drawBoxes(); } });
                item.appendChild(a);
            }
            var p = el('p');
            mentionHtml(p, c.body);
            item.appendChild(p);
            var acts = el('div', 'ph-comment__acts');
            if (c.canEdit) { acts.appendChild(btn('ph-act', 'Modifier', function () { editComment(item, p, c); })); }
            if (c.canDelete) {
                var del = btn('ph-act ph-act--bad', 'Supprimer', null);
                twoStep(del, 'Confirmer', function () { act('/' + cur.photo.id + '/commentaires/' + c.id + '/supprimer', {}, 'Commentaire supprimé.'); });
                acts.appendChild(del);
            }
            if (!c.mine) {
                var rep = btn('ph-act', 'Signaler', null);
                twoStep(rep, 'Confirmer le signalement', function () {
                    post('/' + cur.photo.id + '/commentaires/' + c.id + '/signaler', { reason: 'inappropriate' }).then(function (res) {
                        say(res && res.ok ? 'Signalement envoyé à la modération.' : ((res && res.error) || 'Signalement impossible.'), !(res && res.ok));
                    });
                });
                acts.appendChild(rep);
            }
            item.appendChild(acts);
            s.appendChild(item);
        });
        if (cur.rights.comment) { s.appendChild(commentForm()); }
        return s;
    }

    function editComment(item, p, c) {
        var ta = el('textarea');
        ta.value = c.body;
        ta.maxLength = 2000;
        var save = btn('ph-btn', 'Enregistrer', function () {
            var v = ta.value.trim();
            if (v) { act('/' + cur.photo.id + '/commentaires/' + c.id, { body: v }, 'Commentaire modifié.'); }
        });
        var cancel = btn('ph-btn ph-btn--ghost', 'Annuler', function () { render(); });
        p.replaceWith(ta);
        var row = el('div', 'ph-inline');
        row.appendChild(save);
        row.appendChild(cancel);
        row.style.display = 'flex';
        row.style.gap = '8px';
        item.appendChild(row);
        ta.focus();
    }

    function refreshAnchorNote() {
        var host = viewer.querySelector('[data-ph-anchor-note]');
        if (!host) { return; }
        host.textContent = '';
        if (!anchor) { host.hidden = true; return; }
        host.hidden = false;
        host.appendChild(el('span', '', anchor.anchor === 'zone' ? 'Commentaire ancré sur la zone tracée' : 'Commentaire ancré sur un visage'));
        host.appendChild(btn('ph-act', 'Retirer l’ancre', function () { anchor = null; drawBoxes(); refreshAnchorNote(); }));
    }

    function commentForm() {
        var form = el('form', 'ph-form');
        form.style.marginTop = '12px';
        var ta = el('textarea');
        ta.maxLength = 2000;
        ta.placeholder = 'Votre commentaire — @indicatif pour citer un membre';
        ta.setAttribute('aria-label', 'Votre commentaire');
        ta.setAttribute('data-ph-comment-body', '1');
        form.appendChild(ta);
        var sug = el('ul', 'ph-suggest');
        sug.style.position = 'static';
        sug.hidden = true;
        form.appendChild(sug);
        var note = el('p', 'ph-inline');
        note.setAttribute('data-ph-anchor-note', '1');
        note.style.display = 'flex';
        note.style.justifyContent = 'space-between';
        note.style.alignItems = 'center';
        note.style.margin = '0';
        note.style.fontSize = '12.5px';
        note.hidden = true;
        form.appendChild(note);
        if (cur.tags.length) {
            var pick = el('select');
            pick.setAttribute('aria-label', 'Ancrer sur un visage');
            var none = el('option', '', 'Ancrer sur un visage…');
            none.value = '';
            pick.appendChild(none);
            cur.tags.forEach(function (t) { var o = el('option', '', t.label); o.value = String(t.id); pick.appendChild(o); });
            pick.addEventListener('change', function () {
                anchor = pick.value ? { anchor: 'tag', tag: parseInt(pick.value, 10) } : null;
                if (anchor) { sel = { kind: 'tag', id: anchor.tag }; }
                drawBoxes();
                refreshAnchorNote();
            });
            form.appendChild(pick);
        }
        var go = el('button', 'ph-btn', 'Publier');
        go.type = 'submit';
        form.appendChild(go);

        // Mentions : « @ » + deux lettres propose les membres de la communauté.
        var timer = 0;
        ta.addEventListener('input', function () {
            window.clearTimeout(timer);
            var upto = ta.value.slice(0, ta.selectionStart || 0);
            var m = /(?:^|[^\p{L}\p{N}_])@([\p{L}\p{N}_.\-]{1,40})$/u.exec(upto);
            if (!m) { sug.hidden = true; return; }
            timer = window.setTimeout(function () {
                getJson('/cibles?type=member&q=' + encodeURIComponent(m[1])).then(function (res) {
                    sug.textContent = '';
                    var rows = ((res && res.results) || []).filter(function (it) { return /^[\p{L}\p{N}_.\-]{2,40}$/u.test(it.label); });
                    sug.hidden = rows.length === 0;
                    rows.forEach(function (it) {
                        var li = el('li');
                        li.appendChild(btn('', it.label, function () {
                            var start = (ta.selectionStart || 0) - m[1].length;
                            ta.value = ta.value.slice(0, start) + it.label + ' ' + ta.value.slice(ta.selectionStart || 0);
                            sug.hidden = true;
                            ta.focus();
                        }));
                        sug.appendChild(li);
                    });
                });
            }, 160);
        });
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var v = ta.value.trim();
            if (!v) { say('Écrivez un commentaire.', true); return; }
            var data = { body: v, anchor: 'none' };
            if (anchor && anchor.anchor === 'zone') { data = { body: v, anchor: 'zone', x: anchor.x, y: anchor.y, w: anchor.w, h: anchor.h }; }
            if (anchor && anchor.anchor === 'tag') { data = { body: v, anchor: 'tag', tag: anchor.tag }; }
            go.disabled = true;
            post('/' + cur.photo.id + '/commentaires', data).then(function (res) {
                go.disabled = false;
                if (!res || !res.ok) { say((res && res.error) || 'Commentaire refusé.', true); return; }
                anchor = null;
                reload().then(function () { say('Commentaire publié.', false); });
            });
        });
        window.setTimeout(refreshAnchorNote, 0);
        return form;
    }

    function sseSection() {
        var r = cur.rights;
        var p = cur.photo;
        if (!r.sse && !r.withdraw && !p.sse) { return null; }
        var s = section('Dossier SSE');
        var box = el('div', 'ph-sse');
        if (p.sse) {
            var pill = el('span', 'ph-pill ph-pill--sse ph-pill--inline');
            pill.appendChild(icon('lock'));
            pill.appendChild(document.createTextNode('Versée au dossier SSE'));
            box.appendChild(pill);
            if (cur.sseInfo) {
                var who = [cur.sseInfo.by ? 'par ' + cur.sseInfo.by : '', cur.sseInfo.when ? 'le ' + when(cur.sseInfo.when) : '', cur.sseInfo.identity ? 'fiche ' + cur.sseInfo.identity.ref + ' ' + cur.sseInfo.identity.name : ''].filter(Boolean).join(' · ');
                box.appendChild(el('p', 'ph-empty-line', who));
            }
            box.appendChild(el('p', 'ph-warn', 'Cette photo n’est plus visible que par les membres qui passent la porte du bureau SSE.'));
            if (r.withdraw) {
                var out = btn('ph-btn ph-btn--ghost', 'Retirer du dossier SSE', null);
                twoStep(out, 'Confirmer le retrait', function () { act('/' + p.id + '/sse/retirer', {}, 'Photo retirée du dossier SSE.'); });
                box.appendChild(out);
            }
        } else if (r.sse) {
            box.appendChild(el('p', 'ph-warn', 'La photo entre dans la photothèque des fichiers SSE et ne sera plus visible que par ceux qui ont la porte SSE. L’opération est journalisée.'));
            var input = el('input');
            input.type = 'text';
            input.maxLength = 24;
            input.placeholder = 'Fiche (IDT-000123), facultatif';
            input.setAttribute('aria-label', 'Fiche SSE à laquelle rattacher la photo');
            box.appendChild(input);
            var go = btn('ph-btn', 'Verser au dossier SSE', null);
            twoStep(go, 'Confirmer le versement', function () {
                act('/' + p.id + '/sse', { identity: input.value.trim() }, 'Photo versée au dossier SSE.');
            });
            box.appendChild(go);
        }
        s.appendChild(box);
        return s;
    }

    // ——— Événements de la page ———

    root.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('[data-ph-open]') : null;
        if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.button > 0) { return; }
        e.preventDefault();
        lastFocus = a;
        openPhoto(parseInt(a.getAttribute('data-ph-open'), 10), true);
    });

    document.addEventListener('keydown', function (e) {
        if (viewer.hidden) { return; }
        var t = e.target;
        var typing = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT');
        if (e.key === 'Escape') {
            if (popover) { closePop(); setTool(''); }
            else if (tool !== '') { setTool(''); }
            else { closeViewer(true); }
            e.preventDefault();
        } else if (!typing && e.key === 'ArrowLeft') { step(-1); e.preventDefault(); }
        else if (!typing && e.key === 'ArrowRight') { step(1); e.preventDefault(); }
        else if (e.key === 'Tab' && !popover) {
            var f = viewer.querySelectorAll('button:not([disabled]), a[href], input, select, textarea, [tabindex="0"]');
            if (!f.length) { return; }
            var first = f[0], last = f[f.length - 1];
            if (e.shiftKey && document.activeElement === first) { last.focus(); e.preventDefault(); }
            else if (!e.shiftKey && document.activeElement === last) { first.focus(); e.preventDefault(); }
        }
    });

    window.addEventListener('popstate', function () {
        var m = /\/jnet\/photos\/(\d+)\/?$/.exec(window.location.pathname);
        if (m) { openPhoto(parseInt(m[1], 10), false); }
        else if (!viewer.hidden) { closeViewer(false); }
    });

    // Filtre « personne » : recherche d'un membre (ou d'une personne SSE) dans la barre de filtres.
    var personSel = root.querySelector('[data-ph-person]');
    var whoBox = root.querySelector('[data-ph-who]');
    if (personSel && whoBox) {
        whoBox.hidden = false;
        var whoInput = whoBox.querySelector('[data-ph-who-input]');
        var whoList = whoBox.querySelector('[data-ph-who-list]');
        var wt = 0;
        whoInput.addEventListener('input', function () {
            window.clearTimeout(wt);
            wt = window.setTimeout(function () {
                var q = whoInput.value.trim();
                whoList.textContent = '';
                whoList.hidden = true;
                if (!q) { return; }
                var kinds = door ? ['member', 'sse'] : ['member'];
                Promise.all(kinds.map(function (k) { return getJson('/cibles?type=' + k + '&q=' + encodeURIComponent(q)); })).then(function (all) {
                    all.forEach(function (res) {
                        ((res && res.results) || []).forEach(function (it) {
                            var li = el('li');
                            var b = btn('', '', function () {
                                var v = (it.type === 'sse' ? 's' : 'm') + it.id;
                                var o = personSel.querySelector('option[value="' + v + '"]');
                                if (!o) { o = el('option', '', it.label); o.value = v; personSel.appendChild(o); }
                                personSel.value = v;
                                whoList.hidden = true;
                                whoInput.value = it.label;
                            });
                            b.appendChild(el('span', '', it.label));
                            b.appendChild(el('small', '', it.type === 'sse' ? 'Fichiers SSE' : 'Membre'));
                            li.appendChild(b);
                            whoList.appendChild(li);
                        });
                    });
                    whoList.hidden = whoList.children.length === 0;
                });
            }, 180);
        });
    }

    // Adresse /jnet/photos/{id} ouverte directement : le serveur a mis la fiche dans la page.
    var initial = document.getElementById('ph-open');
    if (initial) {
        try {
            cur = JSON.parse(initial.textContent || 'null');
        } catch (x) {
            cur = null;
        }
        if (cur) {
            viewer.hidden = false;
            document.body.classList.add('ph-lock');
            render();
            var c0 = viewer.querySelector('[data-ph-close]');
            if (c0) { c0.focus(); }
        }
    }

    window.JnetPhotos = { open: openPhoto, close: closeViewer };
}());
