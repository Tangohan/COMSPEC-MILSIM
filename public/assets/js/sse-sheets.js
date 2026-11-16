/*
 * SSE › Fiches PDF : éditeur de textes (mise en forme, variables, trames), compteur de caractères, brouillon enregistré tout
 * seul, textes par défaut selon la langue et la diffusion, vérification de la mise en page (aperçu JSON) et téléchargement
 * automatique du document émis. Sans JavaScript, le formulaire reste utilisable (aperçu et émission par envoi classique).
 */
(function () {
    'use strict';
    var form = document.getElementById('shx-form');
    var done = document.getElementById('shx-download');
    if (done && done.getAttribute('data-autodownload')) {
        var key = 'shx-dl-' + done.getAttribute('data-autodownload');
        try {
            if (!window.sessionStorage.getItem(key)) {
                window.sessionStorage.setItem(key, '1');
                window.setTimeout(function () { done.click(); }, 400);
            }
        } catch (e) { /* stockage indisponible : le bouton reste là */ }
    }
    if (!form) { return; }

    var base = form.getAttribute('data-base') || '';
    var csrf = form.getAttribute('data-csrf') || '';
    var ready = form.getAttribute('data-ready') === '1';
    var saveState = document.getElementById('shx-save-state');
    var layoutNote = document.getElementById('shx-layout');
    var draftId = document.getElementById('shx-draft-id');
    var fields = Array.prototype.slice.call(form.querySelectorAll('.shx-field'));
    var saveTimer = null;
    var layoutTimer = null;
    var dirty = false;

    function plainLength(text) {
        return text.replace(/^[ \t]*-[ \t]+/gm, '• ').replace(/\*\*(.+?)\*\*/g, '$1').replace(/(^|[^\w*])\*([^\s*][^*]*?)\*(?![\w*])/g, '$1$2').length;
    }

    function updateCount(field) {
        var ta = field.querySelector('textarea');
        var max = parseInt(field.getAttribute('data-max'), 10) || 0;
        var n = plainLength(ta.value);
        field.querySelector('[data-count]').textContent = String(n);
        field.classList.toggle('is-over', n > max);
        field.classList.toggle('is-near', n <= max && n > max * 0.9);
    }

    function wrapSelection(ta, mark) {
        var s = ta.selectionStart, e = ta.selectionEnd, v = ta.value;
        var sel = v.slice(s, e) || 'texte';
        ta.value = v.slice(0, s) + mark + sel + mark + v.slice(e);
        ta.focus();
        ta.setSelectionRange(s + mark.length, s + mark.length + sel.length);
    }

    function listSelection(ta) {
        var s = ta.selectionStart, e = ta.selectionEnd, v = ta.value;
        var from = v.lastIndexOf('\n', s - 1) + 1;
        var to = v.indexOf('\n', e);
        if (to < 0) { to = v.length; }
        var block = v.slice(from, to).split('\n').map(function (l) { return /^\s*-\s/.test(l) ? l : '- ' + l; }).join('\n');
        ta.value = v.slice(0, from) + block + v.slice(to);
        ta.focus();
    }

    function insertAtCursor(ta, text) {
        var s = ta.selectionStart, e = ta.selectionEnd, v = ta.value;
        ta.value = v.slice(0, s) + text + v.slice(e);
        ta.focus();
        ta.setSelectionRange(s + text.length, s + text.length);
    }

    function changed(field) {
        updateCount(field);
        dirty = true;
        scheduleSave();
        scheduleLayout();
    }

    fields.forEach(function (field) {
        var ta = field.querySelector('textarea');
        updateCount(field);
        ta.addEventListener('input', function () { changed(field); });
        field.querySelector('.shx-toolbar').addEventListener('click', function (ev) {
            var b = ev.target.closest('button');
            if (!b) { return; }
            if (b.hasAttribute('data-wrap')) { wrapSelection(ta, b.getAttribute('data-wrap')); }
            else if (b.hasAttribute('data-list')) { listSelection(ta); }
            else if (b.hasAttribute('data-reset')) { ta.value = ta.getAttribute('data-orig') || ''; }
            else { return; }
            changed(field);
        });
        Array.prototype.forEach.call(field.querySelectorAll('select[data-insert]'), function (sel) {
            sel.addEventListener('change', function () {
                if (sel.value) { insertAtCursor(ta, sel.value); changed(field); }
                sel.selectedIndex = 0;
            });
        });
    });

    function setState(text, bad) {
        if (!saveState) { return; }
        saveState.textContent = text;
        saveState.style.color = bad ? '#f87171' : '';
    }

    function scheduleSave() {
        if (!ready) { return; }
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(saveDraft, 1500);
    }

    function request(path, data) {
        return fetch(base + path, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); });
    }

    function saveDraft() {
        if (!ready) { return Promise.resolve(); }
        setState('Enregistrement du brouillon…', false);
        return request('/brouillon', new FormData(form))
            .then(function (res) {
                if (res.ok && res.j.ok) {
                    if (draftId) { draftId.value = String(res.j.id); }
                    dirty = false;
                    setState('Brouillon enregistré à ' + res.j.saved_at, false);
                } else {
                    setState('Brouillon non enregistré : ' + (res.j.error || 'erreur'), true);
                }
            })
            .catch(function () { setState('Brouillon non enregistré : connexion perdue.', true); });
    }

    function scheduleLayout() {
        window.clearTimeout(layoutTimer);
        layoutTimer = window.setTimeout(checkLayout, 2500);
    }

    function checkLayout() {
        if (!layoutNote) { return; }
        var data = new FormData(form);
        data.set('format', 'json');
        layoutNote.textContent = 'Mise en page : vérification…';
        layoutNote.className = 'shx-layout';
        request('/apercu', data)
            .then(function (res) {
                fields.forEach(function (f) { var o = f.querySelector('[data-over]'); if (o) { o.hidden = true; } });
                var sens = document.getElementById('shx-sensitive');
                if (!res.ok) {
                    layoutNote.textContent = 'Mise en page : ' + ((res.j.errors || ['erreur']).join(' '));
                    layoutNote.className = 'shx-layout is-bad';
                    return;
                }
                var over = res.j.overflow || [];
                over.forEach(function (k) {
                    var f = form.querySelector('.shx-field[data-field="' + k + '"] [data-over]');
                    if (f) { f.hidden = false; }
                });
                layoutNote.textContent = 'Mise en page : ' + res.j.pages + (res.j.pages > 1 ? ' pages' : ' page') + (over.length ? ' — un texte est trop long pour la place prévue.' : ' — tout tient.');
                layoutNote.className = 'shx-layout' + (over.length ? ' is-warn' : '');
                if (sens) {
                    var s = (res.j.sensitive || []);
                    sens.hidden = s.length === 0;
                    sens.textContent = s.length ? 'Ce document contient des données sensibles (' + s.join(', ') + ') : à diffuser selon la classification choisie.' : '';
                }
            })
            .catch(function () { layoutNote.textContent = 'Mise en page : vérification impossible (connexion perdue).'; layoutNote.className = 'shx-layout is-bad'; });
    }

    // Langue ou diffusion modifiées : les textes que vous n'avez pas touchés sont pré-remplis de nouveau.
    function reloadDefaults() {
        var lang = form.elements.lang.value;
        var level = form.elements.classification.value;
        var vis = '';
        Array.prototype.forEach.call(form.querySelectorAll('input[type="radio"][name^="visibility["]:checked'), function (r) {
            vis += '&' + encodeURIComponent(r.name) + '=' + encodeURIComponent(r.value);
        });
        fetch(base + '/defauts?lang=' + encodeURIComponent(lang) + '&classification=' + encodeURIComponent(level) + vis, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.ok) { return; }
                fields.forEach(function (f) {
                    var ta = f.querySelector('textarea');
                    var k = f.getAttribute('data-field');
                    var untouched = ta.value === (ta.getAttribute('data-orig') || '');
                    if (j.texts && typeof j.texts[k] === 'string') {
                        ta.setAttribute('data-orig', j.texts[k]);
                        if (untouched) { ta.value = j.texts[k]; }
                        updateCount(f);
                    }
                });
                dirty = true;
                scheduleSave();
                scheduleLayout();
            });
    }
    ['lang', 'classification'].forEach(function (n) {
        var el = form.elements[n];
        if (el) { el.addEventListener('change', reloadDefaults); }
    });
    Array.prototype.forEach.call(form.querySelectorAll('input[type="radio"][name^="visibility["]'), function (r) {
        r.addEventListener('change', reloadDefaults);
    });
    ['faces', 'threat', 'armed', 'validity'].forEach(function (n) {
        var el = form.elements[n];
        if (el) { el.addEventListener('change', function () { dirty = true; scheduleSave(); scheduleLayout(); }); }
    });
    Array.prototype.forEach.call(form.querySelectorAll('input[name="checks[]"]'), function (c) {
        c.addEventListener('change', function () { dirty = true; scheduleSave(); scheduleLayout(); });
    });
    var title = document.getElementById('shx-draft-title');
    if (title) { title.addEventListener('input', function () { dirty = true; scheduleSave(); }); }
    var saveBtn = document.getElementById('shx-save');
    if (saveBtn) { saveBtn.addEventListener('click', function () { window.clearTimeout(saveTimer); saveDraft(); }); }
    window.addEventListener('beforeunload', function (ev) {
        if (dirty && ready) { ev.preventDefault(); ev.returnValue = ''; }
    });
    form.addEventListener('submit', function () { dirty = false; });
    scheduleLayout();
}());
