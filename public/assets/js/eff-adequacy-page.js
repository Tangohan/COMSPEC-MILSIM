/**
 * Effectifs › Adéquation de l’organisation (views/admin/effectifs_workspace/adequation.php).
 * Onglets, fenêtres (focus piégé, Échap, retour du focus), étapes, aide contextuelle « ? », état « référentiel /
 * personnalisé » des champs, rétablissement, récapitulatif, aperçu « Ce que ça change », modifications non
 * enregistrées. Aucun texte affiché n’est écrit ici : ils viennent de la page (traduite côté serveur).
 */
(function () {
    'use strict';
    var root = document.querySelector('[data-adqp]');
    if (!root || root.__adqp) { return; }
    root.__adqp = true;

    var form = root.querySelector('[data-adqp-form]');
    var editable = root.getAttribute('data-adqp-editable') === '1';
    var FOCUSABLE = 'a[href], button:not([disabled]):not([hidden]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])';
    var submitting = false;
    var opener = null;
    var snapshots = {};

    function $all(sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); }
    function visible(el) { return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length); }
    function store(fn) { try { return fn(window.localStorage); } catch (e) { return null; } }

    /* ——— Valeurs du formulaire ——— */
    function fieldValues(ctx) {
        var out = [];
        $all('[data-adqp-input]', ctx).forEach(function (el) {
            if (el.type === 'checkbox' || el.type === 'radio') { out.push(el.checked ? '1' : '0'); } else { out.push(el.value); }
        });
        return out.join('\u0001');
    }
    function setValues(ctx, serial) {
        var vals = serial.split('\u0001');
        $all('[data-adqp-input]', ctx).forEach(function (el, i) {
            if (el.type === 'checkbox' || el.type === 'radio') { el.checked = vals[i] === '1'; } else { el.value = vals[i] || ''; }
        });
        $all('[data-adqp-field]', ctx).forEach(refreshField);
    }

    /* ——— État d’un champ : valeur du référentiel ou personnalisée ——— */
    function isCustom(field) {
        var custom = false;
        $all('[data-adqp-input]', field).forEach(function (el) {
            if (el.type === 'checkbox') { custom = custom || el.checked; } else if (el.type === 'radio') { custom = custom || (el.checked && el.value !== ''); } else if (el.value !== '') { custom = true; }
        });
        return custom;
    }
    function setPills(scope, custom) {
        $all('[data-adqp-is="ref"]', scope).forEach(function (p) { p.hidden = custom; });
        $all('[data-adqp-is="custom"]', scope).forEach(function (p) { p.hidden = !custom; });
    }
    function refreshField(field) {
        var custom = isCustom(field);
        setPills(field.querySelector('.adqp-field__head') || field, custom);
        $all('select.adqp-select', field).forEach(function (s) { s.classList.toggle('is-ref', s.value === ''); });
        $all('[data-adqp-reset], [data-adqp-ref-line]', field).forEach(function (el) { el.hidden = !custom; });
    }
    function resetField(field) {
        $all('[data-adqp-input]', field).forEach(function (el) {
            if (el.type === 'checkbox') { el.checked = false; } else if (el.type === 'radio') { el.checked = el.hasAttribute('data-adqp-ref-choice'); } else { el.value = ''; el.removeAttribute('aria-invalid'); }
        });
        refreshField(field);
        changed();
    }

    /* ——— Modifications non enregistrées ——— */
    var initial = form ? fieldValues(form) : '';
    var initialEch = {};
    $all('[data-adqp-ech-dialog]').forEach(function (d) { initialEch[d.getAttribute('data-adqp-ech-dialog')] = fieldValues(d); });
    function dirty() { return !!form && fieldValues(form) !== initial; }
    function changed() {
        var d = dirty();
        $all('[data-adqp-dirty]').forEach(function (el) { el.hidden = !d; });
        $all('[data-adqp-saved]').forEach(function (el) { el.hidden = d; });
        var bar = root.querySelector('[data-adqp-savebar]');
        if (bar) { bar.classList.toggle('is-dirty', d); }
        $all('[data-adqp-ech]').forEach(function (li) {
            var key = li.getAttribute('data-adqp-ech');
            var dlg = document.getElementById('adqp-dlg-ech-' + key);
            var toggle = li.querySelector('[data-adqp-ech-toggle]');
            var moved = (dlg && fieldValues(dlg) !== initialEch[key]) || (toggle && toggle.checked !== toggle.defaultChecked);
            var badge = li.querySelector('[data-adqp-unsaved]');
            if (badge) { badge.hidden = !moved; }
            if (toggle) { li.classList.toggle('is-off', !toggle.checked); }
        });
    }
    window.addEventListener('beforeunload', function (e) {
        if (editable && !submitting && dirty()) { e.preventDefault(); e.returnValue = ''; }
    });

    /* ——— Onglets ——— */
    var tabs = $all('[data-adqp-tab]');
    function showTab(key, focus) {
        tabs.forEach(function (t) {
            var on = t.getAttribute('data-adqp-tab') === key;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.tabIndex = on ? 0 : -1;
            var panel = document.getElementById(t.getAttribute('aria-controls'));
            if (panel) { panel.hidden = !on; }
            if (on && focus) { t.focus(); }
        });
        var bar = root.querySelector('[data-adqp-savebar]');
        if (bar) { bar.hidden = key === 'ensemble'; }
        var tf = root.querySelector('[data-adqp-tabfield]');
        if (tf) { tf.value = key; }
        try {
            var u = new URL(window.location.href);
            u.searchParams.set('onglet', key);
            u.searchParams.delete('echelon');
            window.history.replaceState(null, '', u.toString());
        } catch (e) { /* adresse inchangée */ }
    }
    tabs.forEach(function (t, i) {
        t.addEventListener('click', function (e) { e.preventDefault(); showTab(t.getAttribute('data-adqp-tab'), false); });
        t.addEventListener('keydown', function (e) {
            var n = null;
            if (e.key === 'ArrowRight') { n = (i + 1) % tabs.length; } else if (e.key === 'ArrowLeft') { n = (i - 1 + tabs.length) % tabs.length; } else if (e.key === 'Home') { n = 0; } else if (e.key === 'End') { n = tabs.length - 1; } else if (e.key === ' ') { e.preventDefault(); showTab(t.getAttribute('data-adqp-tab'), true); return; }
            if (n !== null) { e.preventDefault(); showTab(tabs[n].getAttribute('data-adqp-tab'), true); }
        });
    });

    /* ——— Aide contextuelle « ? » ——— */
    function closePops(except) {
        $all('.adqp-q[aria-expanded="true"]', document).forEach(function (b) {
            if (b === except) { return; }
            b.setAttribute('aria-expanded', 'false');
            var p = document.getElementById(b.getAttribute('aria-controls'));
            if (p) { p.hidden = true; }
        });
    }
    function togglePop(btn) {
        var pop = document.getElementById(btn.getAttribute('aria-controls'));
        var open = btn.getAttribute('aria-expanded') !== 'true';
        closePops(btn);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (pop) { pop.hidden = !open; }
    }

    /* ——— Fenêtres ——— */
    function firstFocus(dlg) {
        var body = dlg.querySelector('[data-adqp-stepbody]:not([hidden])') || dlg;
        var el = $all(FOCUSABLE, body).filter(visible)[0] || dlg.querySelector('.bo-dialog__close');
        if (el) { el.focus(); }
    }
    function openDialog(dlg, from) {
        if (!dlg || typeof dlg.showModal !== 'function') { return; }
        opener = from || document.activeElement;
        if (dlg.hasAttribute('data-adqp-ech-dialog')) { snapshots[dlg.id] = fieldValues(dlg); }
        if (dlg.hasAttribute('data-adqp-steps')) { goStep(dlg, 0); }
        dlg.showModal();
        firstFocus(dlg);
    }
    function closeDialog(dlg) { if (dlg && dlg.open) { dlg.close(); } }

    document.addEventListener('keydown', function (e) {
        var dlg = e.target.closest ? e.target.closest('dialog[open].adqp-dlg') : null;
        if (e.key === 'Escape' && $all('.adqp-q[aria-expanded="true"]', document).length) {
            var b = $all('.adqp-q[aria-expanded="true"]', document)[0];
            e.preventDefault();
            closePops(null);
            b.focus();
            return;
        }
        if (e.key !== 'Tab' || !dlg) { return; }
        var items = $all(FOCUSABLE, dlg).filter(visible);
        if (!items.length) { return; }
        var first = items[0];
        var last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
    $all('dialog.adqp-dlg').forEach(function (dlg) {
        dlg.addEventListener('cancel', function (e) {
            if ($all('.adqp-q[aria-expanded="true"]', dlg).length) { e.preventDefault(); closePops(null); }
        });
        dlg.addEventListener('close', function () {
            closePops(null);
            if (dlg.hasAttribute('data-adqp-guide')) { store(function (s) { s.setItem(guideKey, '1'); }); }
            if (opener && document.contains(opener)) { opener.focus(); }
            opener = null;
        });
        dlg.addEventListener('click', function (e) {
            if (e.target !== dlg) { return; }
            var r = dlg.getBoundingClientRect();
            if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) { closeDialog(dlg); }
        });
    });

    /* ——— Étapes (fenêtre d’un échelon, aide) ——— */
    function goStep(dlg, i) {
        var bodies = $all('[data-adqp-stepbody]', dlg);
        var last = bodies.length - 1;
        i = Math.max(0, Math.min(last, i));
        dlg.setAttribute('data-adqp-step', String(i));
        bodies.forEach(function (b, n) { b.hidden = n !== i; });
        $all('[data-adqp-goto-step]', dlg).forEach(function (b, n) {
            if (n === i) { b.setAttribute('aria-current', 'step'); } else { b.removeAttribute('aria-current'); }
            b.classList.toggle('is-done', n < i);
        });
        var show = function (sel, on) { $all(sel, dlg).forEach(function (el) { el.hidden = !on; }); };
        show('[data-adqp-prev]', i > 0);
        show('[data-adqp-next-step]', i < last);
        show('[data-adqp-save-echelon], [data-adqp-close-btn], [data-adqp-last]', i === last);
        show('[data-adqp-cancel]', fieldValues(dlg) !== snapshots[dlg.id]);
        if (dlg.hasAttribute('data-adqp-ech-dialog') && i === last) { recap(dlg); preview(dlg); }
        var body = dlg.querySelector('.adqp-dlg__body');
        if (body) { body.scrollTop = 0; }
    }

    /* ——— Récapitulatif ——— */
    function displayOf(dlg, id) {
        var el = document.getElementById(id);
        var field = el ? el.closest('[data-adqp-field]') : null;
        if (!field) {
            var lbl = document.getElementById(id + '-l');
            field = lbl ? lbl.closest('[data-adqp-field]') : null;
        }
        if (!field) { return null; }
        var def = field.querySelector('[data-adqp-default]');
        var custom = isCustom(field);
        var text = def ? def.textContent : '';
        if (custom) {
            var sel = field.querySelector('select');
            var num = field.querySelector('input[type="number"]');
            var radio = field.querySelector('input[type="radio"]:checked');
            var boxes = $all('input[type="checkbox"]:checked', field);
            if (sel) { text = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].textContent : text; } else if (num) { text = num.value; } else if (radio) { text = radio.parentNode.textContent.trim(); } else if (boxes.length) { text = boxes.map(function (b) { return b.parentNode.textContent.trim(); }).join(', '); }
        }
        return { text: text, custom: custom };
    }
    function recap(dlg) {
        $all('[data-adqp-recap]', dlg).forEach(function (row) {
            var d = displayOf(dlg, row.getAttribute('data-adqp-recap'));
            if (!d) { row.hidden = true; return; }
            row.querySelector('[data-adqp-recap-v]').textContent = d.text;
            setPills(row, d.custom);
        });
    }

    /* ——— Ce que ça change ——— */
    var previewSeq = 0;
    function impactMsg(box, state) { $all('[data-adqp-impact-msg]', box).forEach(function (p) { p.hidden = p.getAttribute('data-adqp-impact-msg') !== state; }); }
    function preview(dlg) {
        var key = dlg.getAttribute('data-adqp-ech-dialog');
        var box = dlg.querySelector('[data-adqp-impact]');
        var url = root.getAttribute('data-adqp-preview');
        var next = box ? box.querySelector('[data-adqp-next]') : null;
        if (!box || !next || !form || !url || !window.fetch) { return; }
        var toggle = root.querySelector('[data-adqp-ech="' + key + '"] [data-adqp-ech-toggle]');
        if (toggle && !toggle.checked) { next.textContent = '0'; impactMsg(box, 'off'); return; }
        var seq = ++previewSeq;
        next.textContent = '…';
        impactMsg(box, 'loading');
        var data = new FormData(form);
        data.delete('reset');
        fetch(url, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function (res) {
                if (seq !== previewSeq) { return; }
                if (!res || !res.ok || !res.proposed || !res.proposed[key]) { throw new Error('apercu'); }
                var after = Number(res.proposed[key].issues) || 0;
                var before = res.current && res.current[key] ? Number(res.current[key].issues) || 0 : after;
                var now = box.querySelector('[data-adqp-now]');
                if (now) { now.textContent = String(before); }
                next.textContent = String(after) + (after !== before ? ' (' + (after > before ? '+' : '−') + Math.abs(after - before) + ')' : '');
                next.parentNode.classList.toggle('is-up', after > before);
                next.parentNode.classList.toggle('is-down', after < before);
                impactMsg(box, 'idle');
            })
            .catch(function () { if (seq === previewSeq) { next.textContent = '—'; impactMsg(box, 'error'); } });
    }

    /* ——— Contrôles côté page (même règle que l’enregistrement : minimum ≤ maximum) ——— */
    function pairError(dlg) {
        var key = dlg.getAttribute('data-adqp-ech-dialog');
        var pairs = [['lmin', 'lmax', 0], ['dmin', 'dmax', 1], ['smin', 'smax', 2]];
        var level = function (v) { var m = /^(OR|OF)-(\d+)$/.exec(v || ''); return m ? (m[1] === 'OR' ? +m[2] : 9 + +m[2]) : null; };
        for (var i = 0; i < pairs.length; i++) {
            var a = document.getElementById('adq-' + key + '-' + pairs[i][0]);
            var b = document.getElementById('adq-' + key + '-' + pairs[i][1]);
            if (!a || !b) { continue; }
            var va = a.value !== '' ? a.value : (a.tagName === 'SELECT' ? null : a.placeholder);
            var vb = b.value !== '' ? b.value : (b.tagName === 'SELECT' ? null : b.placeholder);
            if (a.tagName === 'SELECT') {
                va = level(a.value) !== null ? level(a.value) : level(a.closest('[data-adqp-field]').querySelector('[data-adqp-default]').textContent.replace(/^.*\(|\)$/g, ''));
                vb = level(b.value) !== null ? level(b.value) : level(b.closest('[data-adqp-field]').querySelector('[data-adqp-default]').textContent.replace(/^.*\(|\)$/g, ''));
            } else { va = Number(va); vb = Number(vb); }
            a.removeAttribute('aria-invalid');
            b.removeAttribute('aria-invalid');
            if (va !== null && vb !== null && !isNaN(va) && !isNaN(vb) && va > vb && (a.value !== '' || b.value !== '')) {
                (a.value !== '' ? a : b).setAttribute('aria-invalid', 'true');
                return { step: pairs[i][2], el: a.value !== '' ? a : b };
            }
        }
        return null;
    }
    function showError(dlg, el, step) {
        var box = dlg.querySelector('[data-adqp-err]');
        var msg = root.querySelector('[data-adqp-msg="order"]');
        if (box && msg) { box.textContent = msg.textContent; box.hidden = false; }
        if (!dlg.open) { openDialog(dlg, root.querySelector('[data-adqp-open="' + dlg.id + '"]')); }
        goStep(dlg, step);
        if (el) { el.focus(); }
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            var submitter = e.submitter || document.activeElement;
            if (!(submitter && submitter.name === 'reset')) {
                var dlgs = $all('[data-adqp-ech-dialog]');
                for (var i = 0; i < dlgs.length; i++) {
                    var err = pairError(dlgs[i]);
                    if (err) { e.preventDefault(); showError(dlgs[i], err.el, err.step); return; }
                }
            }
            var ech = root.querySelector('[data-adqp-echfield]');
            if (ech) { ech.value = submitter && submitter.hasAttribute('data-adqp-save-echelon') ? submitter.getAttribute('data-adqp-save-echelon') : ''; }
            submitting = true;
            $all('[data-adqp-save], [data-adqp-save-echelon]').forEach(function (b) { b.setAttribute('aria-busy', 'true'); });
        });
        form.addEventListener('input', function (e) { var f = e.target.closest('[data-adqp-field]'); if (f) { refreshField(f); } changed(); });
        form.addEventListener('change', function (e) {
            var f = e.target.closest('[data-adqp-field]');
            if (f) { refreshField(f); }
            if (e.target.hasAttribute('data-adqp-margin')) { margin(0); }
            afterEdit(e.target.closest('[data-adqp-ech-dialog]'));
            changed();
        });
    }

    function afterEdit(dlg) {
        if (!dlg || !dlg.hasAttribute('data-adqp-ech-dialog')) { return; }
        var box = dlg.querySelector('[data-adqp-err]');
        if (box && !box.hidden && !pairError(dlg)) { box.hidden = true; }
        $all('[data-adqp-cancel]', dlg).forEach(function (b) { b.hidden = fieldValues(dlg) === snapshots[dlg.id]; });
    }

    /* ——— Seuil de surqualification ——— */
    function code(l) { return l <= 9 ? 'OR-' + l : 'OF-' + (l - 9); }
    function margin(delta) {
        var input = root.querySelector('[data-adqp-margin]');
        if (!input) { return; }
        var v = Math.max(1, Math.min(6, (parseInt(input.value, 10) || 2) + delta));
        input.value = String(v);
        $all('[data-adqp-margin-n]').forEach(function (el) { el.textContent = String(v); });
        $all('[data-adqp-margin-ex]').forEach(function (el) { el.textContent = code(6 + v); });
        if (delta !== 0) { changed(); }
    }

    /* ——— Clics ——— */
    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('button, a, [data-adqp-goto]') : null;
        if (!e.target.closest || (!e.target.closest('.adqp-pop') && !e.target.closest('.adqp-q'))) { closePops(null); }
        if (!t || !root.contains(t)) { return; }
        var dlg = t.closest('dialog');
        if (t.classList.contains('adqp-q')) { e.preventDefault(); togglePop(t); return; }
        if (t.hasAttribute('data-adqp-open')) { e.preventDefault(); openDialog(document.getElementById(t.getAttribute('data-adqp-open')), t); return; }
        if (t.hasAttribute('data-adqp-help-open')) { openDialog(document.getElementById('adqp-dlg-help'), t); return; }
        if (t.hasAttribute('data-adqp-goto')) { closeDialog(dlg); showTab(t.getAttribute('data-adqp-goto'), true); return; }
        if (t.hasAttribute('data-adqp-close') || t.hasAttribute('data-adqp-close-btn')) { closeDialog(dlg); return; }
        if (t.hasAttribute('data-adqp-step-by')) { margin(parseInt(t.getAttribute('data-adqp-step-by'), 10) || 0); return; }
        if (!dlg) { return; }
        var step = parseInt(dlg.getAttribute('data-adqp-step') || '0', 10);
        if (t.hasAttribute('data-adqp-next-step')) { goStep(dlg, step + 1); firstFocus(dlg); return; }
        if (t.hasAttribute('data-adqp-prev')) { goStep(dlg, step - 1); firstFocus(dlg); return; }
        if (t.hasAttribute('data-adqp-goto-step')) { goStep(dlg, parseInt(t.getAttribute('data-adqp-goto-step'), 10) || 0); return; }
        if (t.hasAttribute('data-adqp-reset')) {
            var f = t.closest('[data-adqp-field]');
            var focusTo = f.querySelector('select, input:not([type="hidden"])');
            resetField(f);
            afterEdit(dlg);
            if (focusTo) { focusTo.focus(); }
            return;
        }
        if (t.hasAttribute('data-adqp-reset-echelon')) {
            $all('[data-adqp-field]', dlg).forEach(resetField);
            afterEdit(dlg);
            if (step === 3) { recap(dlg); preview(dlg); }
            return;
        }
        if (t.hasAttribute('data-adqp-cancel')) {
            if (snapshots[dlg.id] !== undefined) { setValues(dlg, snapshots[dlg.id]); }
            var box = dlg.querySelector('[data-adqp-err]');
            if (box) { box.hidden = true; }
            changed();
            closeDialog(dlg);
        }
    });

    /* ——— Filtre des listes de cases ——— */
    root.addEventListener('input', function (e) {
        if (!e.target.hasAttribute('data-adqp-filter')) { return; }
        var q = e.target.value.trim().toLowerCase();
        $all('.adqp-checks li', e.target.closest('[data-adqp-field]')).forEach(function (li) { li.hidden = q !== '' && li.textContent.toLowerCase().indexOf(q) < 0; });
    });

    /* ——— Démarrage ——— */
    $all('[data-adqp-field]').forEach(refreshField);
    changed();
    var guideKey = 'athena.adqp.guide.v1.' + (root.getAttribute('data-adqp-viewer') || '0');
    var openKey = root.getAttribute('data-adqp-open-echelon');
    if (openKey) {
        var target = document.getElementById('adqp-dlg-ech-' + openKey);
        openDialog(target, root.querySelector('[data-adqp-open="adqp-dlg-ech-' + openKey + '"]'));
        if (target && target.querySelector('[data-adqp-err]:not([hidden])')) { goStep(target, 3); }
    } else if (store(function (s) { return s.getItem(guideKey); }) === null && store(function () { return true; }) === true) {
        openDialog(document.getElementById('adqp-dlg-help'), root.querySelector('[data-adqp-help-open]'));
    }
})();
