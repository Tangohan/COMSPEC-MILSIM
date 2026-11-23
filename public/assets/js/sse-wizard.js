/*
 * Assistant de création du portail SSE (toile, dossier, objet).
 * Piloté par des attributs data-wz-* : aucun texte n'est écrit ici, tout vient du HTML (donc traduit avec la page).
 *  form[data-sse-wizard][data-wz-key]   le formulaire ; la clé nomme le brouillon
 *  [data-wz-step]                        une étape (section)
 *  [data-wz-field][data-wz-rules]        un champ : règles « required », « min:N » ; data-wz-need-when="nom=valeur"
 *  [data-wz-err="règle"]                 message d'erreur masqué, révélé par la règle correspondante
 *  [data-wz-when="nom=a|b"]              bloc affiché (et activé) seulement si la condition est vraie ; « nom!= » : non vide
 *  [data-wz-from="nom"]                  ligne du récapitulatif ; [data-wz-goto="n"] bouton de retour à l'étape n
 * Brouillon : localStorage (try/catch), sans les champs data-wz-nodraft, les fichiers ni le jeton CSRF.
 */
(function () {
    'use strict';

    var form = document.querySelector('form[data-sse-wizard]');
    if (!form) { return; }

    var KEY = 'sseWizard:' + (form.getAttribute('data-wz-key') || 'form');
    var LAST = KEY + ':last';
    var MAX_AGE = 7 * 24 * 3600 * 1000;
    var steps = Array.prototype.slice.call(form.querySelectorAll('[data-wz-step]'));
    var total = steps.length;
    var current = 1;
    var reached = 1;
    var restoring = false;
    var busy = false;
    var timer = null;

    function store(op, k, v) {
        try {
            if (op === 'get') { return window.localStorage.getItem(k); }
            if (op === 'set') { window.localStorage.setItem(k, v); }
            if (op === 'del') { window.localStorage.removeItem(k); }
        } catch (e) { /* stockage indisponible : l'assistant fonctionne sans brouillon */ }
        return null;
    }

    function controls(root) {
        return Array.prototype.slice.call((root || form).querySelectorAll('input, select, textarea'));
    }

    function valueOf(name) {
        var list = form.querySelectorAll('[name="' + name.replace(/"/g, '\\"') + '"]');
        var val = '';
        Array.prototype.forEach.call(list, function (c) {
            if (c.disabled) { return; }
            if (c.type === 'radio' || c.type === 'checkbox') {
                if (c.checked) { val = c.value; }
            } else if (!val) {
                val = c.value;
            }
        });
        return val;
    }

    function cond(expr) {
        var m = /^([^!=]+)(!?=)(.*)$/.exec(expr || '');
        if (!m) { return true; }
        var v = valueOf(m[1].trim());
        var list = m[3].split('|');
        var hit = list.indexOf(v) !== -1;
        if (m[2] === '!=') { return m[3] === '' ? v !== '' : !hit; }
        return hit;
    }

    function applyWhen() {
        Array.prototype.forEach.call(form.querySelectorAll('[data-wz-when]'), function (el) {
            var on = cond(el.getAttribute('data-wz-when'));
            el.hidden = !on;
            if (/^(INPUT|SELECT|TEXTAREA|BUTTON|FIELDSET)$/.test(el.tagName)) {
                el.disabled = !on;
            }
            controls(el).forEach(function (c) {
                if (!on) {
                    if (!c.disabled) { c.setAttribute('data-wz-off', '1'); c.disabled = true; }
                } else if (c.getAttribute('data-wz-off')) {
                    c.removeAttribute('data-wz-off');
                    c.disabled = false;
                }
            });
        });
        Array.prototype.forEach.call(form.querySelectorAll('.wz-card'), function (card) {
            var input = card.querySelector('input');
            card.classList.toggle('is-checked', !!(input && input.checked));
        });
    }

    /* ---------- validation ---------- */

    function fieldValue(field) {
        var list = controls(field).filter(function (c) {
            return !c.disabled && c.type !== 'hidden' && c.type !== 'file' && c.type !== 'button';
        });
        if (!list.length) { return null; }
        var first = list[0];
        if (first.type === 'radio') {
            var checked = list.filter(function (c) { return c.checked; })[0];
            return { list: list, value: checked ? checked.value : '' };
        }
        if (first.type === 'checkbox') {
            return { list: list, value: first.checked ? first.value : '' };
        }
        return { list: list, value: first.value.replace(/^\s+|\s+$/g, '') };
    }

    function ruleFailed(field) {
        if (field.closest('[data-wz-when][hidden]')) { return null; }
        var fv = fieldValue(field);
        if (!fv) { return null; }
        var rules = (field.getAttribute('data-wz-rules') || '').split(/[\s|]+/);
        var need = field.getAttribute('data-wz-need-when');
        if ((rules.indexOf('required') !== -1 || (need && cond(need))) && fv.value === '') { return 'required'; }
        for (var i = 0; i < rules.length; i++) {
            var m = /^min:(\d+)$/.exec(rules[i]);
            if (m && fv.value !== '' && fv.value.length < parseInt(m[1], 10)) { return 'min'; }
        }
        return null;
    }

    function showError(field, rule) {
        var fv = fieldValue(field);
        var ids = [];
        Array.prototype.forEach.call(field.querySelectorAll('[data-wz-err]'), function (p) {
            var on = p.getAttribute('data-wz-err') === rule;
            p.hidden = !on;
            if (on) { ids.push(p.id); }
        });
        field.classList.add('has-error');
        if (fv) {
            fv.list.forEach(function (c) {
                c.setAttribute('aria-invalid', 'true');
                if (ids.length && !c.hasAttribute('data-wz-desc')) {
                    c.setAttribute('data-wz-desc', c.getAttribute('aria-describedby') || '');
                }
                if (ids.length) {
                    c.setAttribute('aria-describedby', ((c.getAttribute('data-wz-desc') || '') + ' ' + ids.join(' ')).replace(/^\s+/, ''));
                }
            });
        }
    }

    function clearError(field) {
        field.classList.remove('has-error');
        Array.prototype.forEach.call(field.querySelectorAll('[data-wz-err]'), function (p) { p.hidden = true; });
        controls(field).forEach(function (c) {
            c.removeAttribute('aria-invalid');
            if (c.hasAttribute('data-wz-desc')) {
                c.setAttribute('aria-describedby', c.getAttribute('data-wz-desc'));
            }
        });
    }

    /* Valide une étape ; retourne le premier champ en erreur (ou null). */
    function validateStep(n, quiet) {
        var step = steps[n - 1];
        var firstBad = null;
        Array.prototype.forEach.call(step.querySelectorAll('[data-wz-field]'), function (field) {
            var rule = ruleFailed(field);
            if (rule) {
                if (!quiet) { showError(field, rule); }
                if (!firstBad) { firstBad = field; }
            } else if (!quiet) {
                clearError(field);
            }
        });
        var alertBox = step.querySelector('[data-wz-alert]');
        if (alertBox) { alertBox.hidden = quiet || !firstBad; }

        return firstBad;
    }

    function focusField(field) {
        var fv = fieldValue(field);
        var target = fv ? (fv.list.filter(function (c) { return c.checked; })[0] || fv.list[0]) : null;
        if (target && target.focus) {
            target.focus();
            if (target.scrollIntoView) { target.scrollIntoView({ block: 'center' }); }
        }
    }

    /* ---------- navigation ---------- */

    function render() {
        steps.forEach(function (s, i) { s.hidden = (i + 1) !== current; });
        Array.prototype.forEach.call(form.querySelectorAll('[data-wz-prog]'), function (li) {
            var k = parseInt(li.getAttribute('data-wz-prog'), 10);
            li.classList.toggle('is-current', k === current);
            li.classList.toggle('is-done', k < current);
            li.classList.toggle('is-reachable', k <= reached);
            if (k === current) { li.setAttribute('aria-current', 'step'); } else { li.removeAttribute('aria-current'); }
            var b = li.querySelector('button');
            if (b) { b.disabled = k > reached; }
        });
        var bar = form.querySelector('[data-wz-bar]');
        if (bar) { bar.setAttribute('aria-valuenow', String(current)); }
        var fill = form.querySelector('[data-wz-fill]');
        if (fill) { fill.style.width = Math.round((current / total) * 100) + '%'; }
        var cur = form.querySelector('[data-wz-cur]');
        if (cur) { cur.textContent = String(current); }
        var title = form.querySelector('[data-wz-cur-title]');
        var head = steps[current - 1].querySelector('[data-wz-step-title]');
        if (title && head) { title.textContent = head.textContent; }
        var prev = form.querySelector('[data-wz-prev]');
        var next = form.querySelector('[data-wz-next]');
        var done = form.querySelector('[data-wz-submit-wrap]');
        if (prev) { prev.hidden = current === 1; }
        if (next) { next.hidden = current === total; }
        if (done) { done.hidden = current !== total; }
        if (current === total) { summarize(); }
    }

    function go(n, focus) {
        n = Math.max(1, Math.min(total, n));
        current = n;
        if (n > reached) { reached = n; }
        render();
        scheduleSave();
        var head = steps[n - 1].querySelector('[data-wz-step-title]');
        if (focus !== false && head) {
            head.setAttribute('tabindex', '-1');
            head.focus();
            if (head.scrollIntoView) { head.scrollIntoView({ block: 'start' }); }
        }
    }

    function next() {
        var bad = validateStep(current, false);
        if (bad) { focusField(bad); return; }
        go(current + 1);
    }

    function validateAll() {
        for (var i = 1; i <= total; i++) {
            var bad = validateStep(i, false);
            if (bad) { return { step: i, field: bad }; }
        }
        return null;
    }

    /* ---------- récapitulatif ---------- */

    function labelOf(name) {
        var list = form.querySelectorAll('[name="' + name.replace(/"/g, '\\"') + '"]');
        var out = '';
        Array.prototype.forEach.call(list, function (c) {
            if (c.disabled || out) { return; }
            if (c.type === 'radio') {
                if (c.checked) {
                    var t = c.closest('.wz-card');
                    var ti = t && t.querySelector('.wz-card__title');
                    out = ti ? ti.textContent : c.value;
                }
            } else if (c.type === 'checkbox') {
                var mark = form.querySelector(c.checked ? '[data-wz-yes]' : '[data-wz-no]');
                out = mark ? mark.textContent : (c.checked ? c.value : '');
            } else if (c.tagName === 'SELECT') {
                out = c.value === '' ? '' : (c.options[c.selectedIndex] ? c.options[c.selectedIndex].text : c.value);
            } else if (c.type === 'file') {
                out = c.files && c.files[0] ? c.files[0].name : '';
            } else {
                out = c.value.replace(/^\s+|\s+$/g, '');
            }
        });
        return out;
    }

    function summarize() {
        var none = form.querySelector('[data-wz-none]');
        Array.prototype.forEach.call(form.querySelectorAll('[data-wz-from]'), function (row) {
            var cw = row.getAttribute('data-wz-row-when');
            row.hidden = !!cw && !cond(cw);
            var v = labelOf(row.getAttribute('data-wz-from'));
            var dd = row.querySelector('[data-wz-val]');
            if (dd) {
                if (dd.hasAttribute('data-wz-mask') && v !== '') { v = '••••••'; }
                dd.textContent = v !== '' ? v : (none ? none.textContent : '');
                dd.classList.toggle('is-empty', v === '');
            }
        });
    }

    /* ---------- brouillon ---------- */

    function snapshot() {
        var data = {};
        controls().forEach(function (c) {
            if (!c.name || c.type === 'file' || c.type === 'hidden' || c.type === 'password' || c.type === 'submit' || c.type === 'button') { return; }
            if (c.hasAttribute('data-wz-nodraft')) { return; }
            if (c.type === 'radio') {
                if (c.checked) { data[c.name] = c.value; }
            } else if (c.type === 'checkbox') {
                data[c.name] = c.checked ? c.value : '';
            } else {
                data[c.name] = c.value;
            }
        });
        return data;
    }

    function scheduleSave() {
        if (restoring) { return; }
        window.clearTimeout(timer);
        timer = window.setTimeout(function () {
            store('set', KEY, JSON.stringify({ v: 1, t: Date.now(), step: current, reached: reached, f: snapshot() }));
            var tag = form.querySelector('[data-wz-saved]');
            if (tag) { tag.hidden = false; }
        }, 300);
    }

    function applyData(f) {
        restoring = true;
        controls().forEach(function (c) {
            if (!c.name || !Object.prototype.hasOwnProperty.call(f, c.name)) { return; }
            if (c.hasAttribute('data-wz-seed') || c.hasAttribute('data-wz-nodraft')) { return; }
            if (c.type === 'radio') {
                c.checked = (c.value === f[c.name]) && !c.disabled;
            } else if (c.type === 'checkbox') {
                c.checked = f[c.name] !== '' && f[c.name] === c.value;
            } else if (c.tagName === 'SELECT') {
                var ok = Array.prototype.some.call(c.options, function (o) { return o.value === f[c.name]; });
                if (ok) { c.value = f[c.name]; }
            } else if (c.type !== 'file') {
                c.value = f[c.name];
            }
        });
        restoring = false;
    }

    function restore() {
        var raw = store('get', KEY);
        var banner = form.querySelector('[data-wz-draft]');
        var failed = !!document.querySelector('[data-sse-note="error"]');
        var src = 'draft';
        if (failed) {
            var last = store('get', LAST);
            if (last) { raw = last; src = 'last'; }
        }
        if (!raw) { return; }
        var d;
        try { d = JSON.parse(raw); } catch (e) { store('del', KEY); return; }
        if (!d || d.v !== 1 || !d.f || (Date.now() - d.t) > MAX_AGE) { store('del', KEY); return; }
        if (Object.keys(d.f).length === 0) { return; }
        applyData(d.f);
        applyWhen();
        reached = Math.max(1, Math.min(total, d.reached || d.step || 1));
        current = Math.max(1, Math.min(total, d.step || 1));
        if (src === 'last') { current = 1; reached = total; }
        var which = form.querySelector(src === 'last' ? '[data-wz-restored]' : '[data-wz-draft]');
        if (which) { which.hidden = false; }
        if (banner && src === 'last') { banner.hidden = true; }
    }

    function reset() {
        store('del', KEY);
        store('del', LAST);
        window.location.reload();
    }

    /* ---------- aides à la saisie ---------- */

    function applyTemplate(radio) {
        var id = radio.getAttribute('data-wz-tpl');
        var src = id ? form.querySelector('[data-wz-tpl-src="' + id + '"]') : null;
        var target = form.querySelector('[data-wz-tpl-name="' + radio.name + '"]');
        if (!src || !target) { return; }
        var text = src.textContent.replace(/^\s+|\s+$/g, '');
        var prev = target.getAttribute('data-wz-tpl-last') || '';
        var cur = target.value.replace(/^\s+|\s+$/g, '');
        if (cur === '' || cur === prev) {
            target.value = text ? text + ' ' : '';
            target.setAttribute('data-wz-tpl-last', text);
        }
    }

    var classTouched = false;

    function followClass(select) {
        if (classTouched || restoring) { return; }
        var opt = select.options[select.selectedIndex];
        var level = opt ? opt.getAttribute('data-class') : '';
        if (!level) { return; }
        var radio = form.querySelector('input[name="classification"][value="' + level + '"]');
        if (radio && !radio.disabled) { radio.checked = true; applyWhen(); }
    }

    function bind() {
        form.addEventListener('input', function (e) {
            var f = e.target.closest ? e.target.closest('[data-wz-field]') : null;
            if (f && f.classList.contains('has-error') && !ruleFailed(f)) { clearError(f); }
            scheduleSave();
        });
        form.addEventListener('change', function (e) {
            var t = e.target;
            if (t.name === 'classification') { classTouched = true; }
            if (t.type === 'radio' && t.hasAttribute('data-wz-tpl') && !restoring) { applyTemplate(t); }
            if (t.hasAttribute('data-wz-follow')) { followClass(t); }
            applyWhen();
            var f = t.closest ? t.closest('[data-wz-field]') : null;
            if (f && f.classList.contains('has-error') && !ruleFailed(f)) { clearError(f); }
            scheduleSave();
        });
        form.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('button, [data-wz-goto]') : null;
            if (!b) { return; }
            if (b.hasAttribute('data-wz-goto')) {
                var n = parseInt(b.getAttribute('data-wz-goto'), 10);
                if (n > current) {
                    for (var i = current; i < n; i++) {
                        var bad = validateStep(i, false);
                        if (bad) { go(i, false); focusField(bad); return; }
                    }
                }
                go(n);
            } else if (b.hasAttribute('data-wz-next')) {
                next();
            } else if (b.hasAttribute('data-wz-prev')) {
                go(current - 1);
            } else if (b.hasAttribute('data-wz-reset')) {
                reset();
            }
        });
        form.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' || e.defaultPrevented) { return; }
            var t = e.target;
            if (t.tagName === 'TEXTAREA' || t.tagName === 'BUTTON' || t.tagName === 'SELECT' || t.tagName === 'A') { return; }
            e.preventDefault();
            if (current < total) { next(); }
        });
        form.addEventListener('submit', function (e) {
            if (busy) { e.preventDefault(); e.stopImmediatePropagation(); return; }
            var bad = validateAll();
            if (bad) {
                e.preventDefault();
                e.stopImmediatePropagation();
                go(bad.step, false);
                focusField(bad.field);
                return;
            }
            busy = true;
            form.setAttribute('aria-busy', 'true');
            store('set', LAST, JSON.stringify({ v: 1, t: Date.now(), step: total, reached: total, f: snapshot() }));
            store('del', KEY);
        }, true);
        window.addEventListener('pageshow', function () { busy = false; form.removeAttribute('aria-busy'); });
    }

    form.classList.add('is-wizard');
    steps.forEach(function (s) { s.hidden = true; });
    applyWhen();
    restore();
    applyWhen();
    bind();
    render();
    // Une erreur renvoyée par le serveur : on rouvre le premier écran, la saisie est restaurée.
    if (document.querySelector('[data-sse-note="error"]')) { current = 1; render(); }
}());
