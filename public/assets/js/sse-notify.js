/*
 * Bureau SSE : composant unique des messages (erreur, succès, avertissement, validation de formulaire).
 * Aucun libellé dans ce fichier : les textes viennent du bloc caché #sse-notify-labels (rendu par la vue,
 * donc traduit avec la page). Les erreurs restent affichées jusqu'à fermeture ; les succès se ferment seuls
 * après 8 s (jamais moins de 5 s) et restent fermables.
 *
 *   SseNotify.error(detail, title?)   SseNotify.success(detail, title?)   SseNotify.warning(detail, title?)
 *   SseNotify.failure(errOrResponse)  message générique (réseau ou code HTTP) pour un appel fetch qui échoue
 *   SseNotify.fieldErrors(form, [{ field: élément ou nom, message }])  récapitulatif + champs en erreur
 */
(function () {
    'use strict';
    var root = document.getElementById('sse-notify');
    if (!root) { return; }
    var SUCCESS_MS = 8000;
    var ICONS = {
        error: '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7v6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/><circle cx="12" cy="16.8" r="1.4" fill="currentColor"/></svg>',
        success: '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M7.5 12.4l3 3 6-6.4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        warning: '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M12 3.2l9.4 16.6H2.6z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 9.5v4.6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/><circle cx="12" cy="17.2" r="1.3" fill="currentColor"/></svg>',
        close: '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>'
    };
    var uid = 0;

    function label(key) {
        var el = root.querySelector('#sse-notify-labels [data-k="' + key + '"]');
        return el ? (el.textContent || '').trim() : '';
    }
    function fill(text, value) { return text.replace('%1', String(value)); }
    function stack(kind) { return root.querySelector('[data-sse-stack="' + (kind === 'error' ? 'error' : 'status') + '"]'); }
    function make(tag, cls, html) {
        var el = document.createElement(tag);
        if (cls) { el.className = cls; }
        if (html) { el.innerHTML = html; }
        return el;
    }

    /* La zone reste sous les barres fixes : décalage mesuré (la barre d'habilitation n'est collante que sur grand écran). */
    function place() {
        var top = 0;
        var bar = document.querySelector('.sx-topbar');
        var cls = document.querySelector('.sx-classbar');
        if (bar && getComputedStyle(bar).position === 'sticky') { top = bar.getBoundingClientRect().height; }
        if (cls && getComputedStyle(cls).position === 'sticky') { top += cls.getBoundingClientRect().height; }
        offset = top + 8;
        root.style.setProperty('--sse-notify-top', (top + 8) + 'px');
        pin();
    }
    /* Placeholder : garde la place de la zone quand elle passe en position fixe. */
    var offset = 8;
    var ph = document.createElement('div');
    ph.className = 'sse-notify__ph';
    root.parentNode.insertBefore(ph, root);
    function pin() {
        var has = !!root.querySelector('.sse-note');
        var top = ph.getBoundingClientRect();
        if (has && top.top < offset) {
            ph.style.height = (root.offsetHeight + 8) + 'px';
            root.style.left = top.left + 'px';
            root.style.width = top.width + 'px';
            root.classList.add('is-pinned');
        } else if (root.classList.contains('is-pinned') || ph.style.height) {
            root.classList.remove('is-pinned');
            root.style.left = '';
            root.style.width = '';
            ph.style.height = '0';
        }
    }
    window.addEventListener('scroll', pin, { passive: true });

    function dismiss(note) {
        if (!note || !note.parentNode) { return; }
        if (note._timer) { clearTimeout(note._timer); }
        var holder = note.parentNode;
        holder.removeChild(note);
        pin();
        var next = holder.querySelector('.sse-note');
        if (!next && document.activeElement === document.body) { var main = document.getElementById('sx-main'); if (main) { main.focus({ preventScroll: true }); } }
    }
    function arm(note) {
        var close = note.querySelector('[data-sse-close]');
        if (close) { close.addEventListener('click', function () { dismiss(note); }); }
        note.addEventListener('keydown', function (e) { if (e.key === 'Escape') { dismiss(note); } });
        if (note.getAttribute('data-sse-note') === 'success') {
            var start = function () { note._timer = setTimeout(function () { dismiss(note); }, SUCCESS_MS); };
            var stop = function () { if (note._timer) { clearTimeout(note._timer); note._timer = 0; } };
            note.addEventListener('mouseenter', stop);
            note.addEventListener('focusin', stop);
            note.addEventListener('mouseleave', start);
            start();
        }
    }

    function show(kind, detail, title) {
        kind = (kind === 'success' || kind === 'warning') ? kind : 'error';
        detail = String(detail == null ? '' : detail).trim();
        var holder = stack(kind);
        var same = holder.querySelectorAll('.sse-note--' + kind);
        for (var i = 0; i < same.length; i++) {
            var d = same[i].querySelector('.sse-note__detail');
            if (d && d.textContent === detail) {
                same[i].classList.remove('is-bump'); void same[i].offsetWidth; same[i].classList.add('is-bump');
                if (kind === 'error') { same[i].focus(); }
                return same[i];
            }
        }
        var note = make('div', 'sse-note sse-toast sse-note--' + kind);
        note.setAttribute('data-sse-note', kind);
        note.tabIndex = -1;
        note.appendChild(make('span', 'sse-note__icon', ICONS[kind]));
        var body = make('div', 'sse-note__body');
        var t = make('strong', 'sse-note__title');
        t.textContent = title || label(kind);
        body.appendChild(t);
        if (detail) { var p = make('p', 'sse-note__detail'); p.textContent = detail; body.appendChild(p); }
        note.appendChild(body);
        var close = make('button', 'sse-note__close', ICONS.close);
        close.type = 'button';
        close.setAttribute('data-sse-close', '');
        close.setAttribute('aria-label', label('close'));
        note.appendChild(close);
        holder.appendChild(note);
        while (holder.children.length > 4) { dismiss(holder.firstElementChild); }
        arm(note);
        place();
        if (kind === 'error') {
            try { note.scrollIntoView({ block: 'nearest' }); } catch (e) { /* ancien navigateur */ }
            note.focus({ preventScroll: true });
        }
        return note;
    }

    function failure(what) {
        if (what && what.name === 'AbortError') { return null; }
        var code = what && typeof what === 'object' && 'status' in what ? what.status : 0;
        if (code) { return show('error', fill(label('http'), code)); }
        if (what instanceof Error && !(what instanceof TypeError) && what.message) { return show('error', what.message); }
        return show('error', label('network'));
    }

    /* —— Validation de formulaire —— */
    function fieldLabel(el) {
        var l = el.labels && el.labels[0];
        if (!l) { return el.getAttribute('aria-label') || el.getAttribute('placeholder') || el.name || ''; }
        var c = l.cloneNode(true);
        var junk = c.querySelectorAll('input,select,textarea,button,.sse-field-error');
        for (var i = 0; i < junk.length; i++) { junk[i].parentNode.removeChild(junk[i]); }
        return (c.textContent || '').replace(/[\s*:]+$/, '').replace(/\s+/g, ' ').trim();
    }
    function ensureId(el) { if (!el.id) { el.id = 'sse-f-' + (++uid); } return el.id; }

    function mark(el, message) {
        var id = ensureId(el);
        var msg = document.getElementById(id + '-err');
        if (!msg) {
            msg = make('p', 'sse-field-error');
            msg.id = id + '-err';
            var host = el.closest('label');
            if (host && host.contains(el)) { host.appendChild(msg); } else { el.insertAdjacentElement('afterend', msg); }
        }
        msg.textContent = message;
        el.setAttribute('aria-invalid', 'true');
        var ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        if (ids.indexOf(msg.id) < 0) { ids.push(msg.id); el.setAttribute('aria-describedby', ids.join(' ')); }
    }
    function unmark(el) {
        el.removeAttribute('aria-invalid');
        var msg = document.getElementById((el.id || '') + '-err');
        if (msg) {
            var ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(function (x) { return x && x !== msg.id; });
            if (ids.length) { el.setAttribute('aria-describedby', ids.join(' ')); } else { el.removeAttribute('aria-describedby'); }
            msg.parentNode.removeChild(msg);
        }
    }
    function summary(form, items) {
        var old = form.querySelector(':scope > .sse-formsummary');
        if (old) { old.parentNode.removeChild(old); }
        if (!items.length) { return null; }
        var box = make('div', 'sse-formsummary');
        box.setAttribute('role', 'alert');
        box.tabIndex = -1;
        box.appendChild(make('span', 'sse-note__icon', ICONS.error));
        var body = make('div', 'sse-note__body');
        var t = make('strong', 'sse-note__title');
        t.textContent = fill(label('form'), items.length);
        body.appendChild(t);
        var ul = make('ul', 'sse-formsummary__list');
        items.forEach(function (it) {
            var li = document.createElement('li');
            var a = document.createElement('a');
            a.href = '#' + it.el.id;
            a.textContent = (it.name ? it.name + ' : ' : '') + it.message;
            a.addEventListener('click', function (e) { e.preventDefault(); it.el.focus(); });
            li.appendChild(a);
            ul.appendChild(li);
        });
        body.appendChild(ul);
        box.appendChild(body);
        form.insertBefore(box, form.firstChild);
        return box;
    }
    function applyErrors(form, entries) {
        var items = [];
        entries.forEach(function (en) {
            var el = en.el;
            if (!el) { return; }
            ensureId(el);
            var message = en.message || label('field');
            mark(el, message);
            items.push({ el: el, name: fieldLabel(el), message: message });
        });
        summary(form, items);
        if (items.length) {
            items[0].el.focus();
            try { (form.querySelector('.sse-formsummary') || items[0].el).scrollIntoView({ block: 'nearest' }); } catch (e) { /* ancien navigateur */ }
        }
        form._sseErrors = items;
        return items.length;
    }
    function refresh(form, el) {
        if (!form._sseErrors) { return; }
        form._sseErrors = form._sseErrors.filter(function (it) {
            if (it.el === el && (!el.validity || el.validity.valid)) { unmark(el); return false; }
            return true;
        });
        summary(form, form._sseErrors);
    }

    var pending = new WeakMap();
    document.addEventListener('invalid', function (e) {
        var el = e.target;
        var form = el && el.form;
        if (!form || !root.parentNode || !form.closest('.sx-main') || form.hasAttribute('data-sse-native-validation')) { return; }
        e.preventDefault();
        var batch = pending.get(form);
        if (!batch) {
            batch = [];
            pending.set(form, batch);
            setTimeout(function () {
                pending.delete(form);
                (form._sseErrors || []).forEach(function (it) { unmark(it.el); });
                applyErrors(form, batch.map(function (f) { return { el: f, message: f.validationMessage }; }));
            }, 0);
        }
        batch.push(el);
    }, true);
    ['input', 'change'].forEach(function (type) {
        document.addEventListener(type, function (e) {
            var el = e.target;
            if (el && el.form && el.form._sseErrors) { refresh(el.form, el); }
        });
    });

    function fieldErrors(form, list) {
        var entries = [];
        (list || []).forEach(function (it) {
            var el = it.field && it.field.nodeType ? it.field : form.elements[it.field];
            if (el) { entries.push({ el: el, message: it.message }); }
        });
        return applyErrors(form, entries);
    }

    window.SseNotify = {
        show: show,
        error: function (detail, title) { return show('error', detail, title); },
        success: function (detail, title) { return show('success', detail, title); },
        warning: function (detail, title) { return show('warning', detail, title); },
        failure: failure,
        fieldErrors: fieldErrors,
        dismiss: dismiss
    };

    function init() {
        var notes = root.querySelectorAll('.sse-note');
        for (var i = 0; i < notes.length; i++) { arm(notes[i]); }
        /* Anciens messages écrits directement dans les pages : annoncés aux lecteurs d'écran. */
        var legacy = document.querySelectorAll('.sx-main .flash');
        for (var j = 0; j < legacy.length; j++) {
            if (!legacy[j].hasAttribute('role')) { legacy[j].setAttribute('role', legacy[j].classList.contains('flash--error') ? 'alert' : 'status'); }
        }
        /* Erreurs de champ rendues par le serveur : <input data-sse-error="message">. */
        var flagged = document.querySelectorAll('.sx-main [data-sse-error]');
        var forms = [];
        for (var k = 0; k < flagged.length; k++) {
            var f = flagged[k].form;
            if (f && forms.indexOf(f) < 0) { forms.push(f); }
        }
        forms.forEach(function (form) {
            var entries = [];
            Array.prototype.forEach.call(form.querySelectorAll('[data-sse-error]'), function (el) { entries.push({ el: el, message: el.getAttribute('data-sse-error') }); });
            applyErrors(form, entries);
        });
        place();
        var err = root.querySelector('.sse-note--error');
        if (err && !forms.length) { err.focus({ preventScroll: true }); try { err.scrollIntoView({ block: 'nearest' }); } catch (e) { /* ancien navigateur */ } }
    }
    window.addEventListener('resize', place);
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
