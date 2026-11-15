/*
 * SSE > Fiches de vigilance : modales (renouveler, lever, adresse et QR code), copie du lien, onglets de la liste des fiches,
 * et écran d'émission en étapes (niveau, consignes, cadre, service à informer, récapitulatif et aperçu PDF), brouillon
 * enregistré tout seul. Les textes affichés viennent de la page (#vig-i18n), traduits avec elle. Sans JavaScript, les
 * formulaires restent utilisables (toutes les étapes s'affichent à la suite).
 */
(function () {
    'use strict';
    var doc = document;
    doc.documentElement.classList.add('js');

    function qs(sel, root) { return (root || doc).querySelector(sel); }
    function qsa(sel, root) { return Array.prototype.slice.call((root || doc).querySelectorAll(sel)); }

    // ------------------------------------------------------------------ Modales, copie, onglets de la liste
    doc.addEventListener('click', function (ev) {
        var t = ev.target;
        var open = t.closest ? t.closest('[data-vig-open]') : null;
        if (open) {
            var dlg = doc.getElementById(open.getAttribute('data-vig-open'));
            if (dlg && typeof dlg.showModal === 'function' && !open.disabled) { dlg.showModal(); }
            return;
        }
        var close = t.closest ? t.closest('[data-vig-close]') : null;
        if (close) {
            var d = close.closest('dialog');
            if (d) { d.close(); }
            return;
        }
        if (t.tagName === 'DIALOG' && t.classList.contains('vig-dialog')) {
            var r = t.getBoundingClientRect();
            if (ev.clientX < r.left || ev.clientX > r.right || ev.clientY < r.top || ev.clientY > r.bottom) { t.close(); }
            return;
        }
        var copy = t.closest ? t.closest('[data-vig-copy]') : null;
        if (copy) {
            var input = doc.getElementById(copy.getAttribute('data-vig-copy'));
            if (!input) { return; }
            var done = function () {
                var ok = copy.parentElement ? qs('[data-vig-copied]', copy.parentElement) : null;
                if (ok) { ok.hidden = false; window.setTimeout(function () { ok.hidden = true; }, 3000); }
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(input.value).then(done, function () { input.select(); try { doc.execCommand('copy'); done(); } catch (e) { /* sélection laissée */ } });
            } else {
                input.select();
                try { doc.execCommand('copy'); done(); } catch (e) { /* sélection laissée */ }
            }
            return;
        }
        var conf = t.closest ? t.closest('[data-confirm]') : null;
        if (conf && !window.confirm(conf.getAttribute('data-confirm'))) { ev.preventDefault(); }
    });
    qsa('[data-vig-seg]').forEach(function (seg) {
        var map = { vigilances: doc.getElementById('vigilances'), autres: doc.getElementById('vig-others') };
        seg.addEventListener('click', function (ev) {
            var b = ev.target.closest('[data-vig-tab]');
            if (!b) { return; }
            qsa('[data-vig-tab]', seg).forEach(function (x) {
                var on = x === b;
                x.classList.toggle('is-on', on);
                x.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            Object.keys(map).forEach(function (k) { if (map[k]) { map[k].hidden = k !== b.getAttribute('data-vig-tab'); } });
        });
        if (map.autres) { map.autres.hidden = true; }
    });

    // Téléchargement automatique du PDF qui vient d'être émis.
    var auto = qs('[data-vig-autodownload]');
    if (auto) {
        var key = 'vig-dl-' + auto.getAttribute('data-vig-autodownload');
        try {
            if (!window.sessionStorage.getItem(key)) {
                window.sessionStorage.setItem(key, '1');
                window.setTimeout(function () { auto.click(); }, 400);
            }
        } catch (e) { /* stockage indisponible : le bouton reste là */ }
    }

    var form = doc.getElementById('vig-form');
    if (!form) { return; }

    // ------------------------------------------------------------------ Écran d'émission
    var data = {};
    try { data = JSON.parse(doc.getElementById('vig-data').textContent); } catch (e) { data = {}; }
    var i18n = {};
    qsa('#vig-i18n [data-k]').forEach(function (s) { i18n[s.getAttribute('data-k')] = s.textContent; });
    var base = form.getAttribute('data-base') || '';
    var csrf = form.getAttribute('data-csrf') || '';
    var ready = form.getAttribute('data-ready') === '1';
    var levels = {};
    (data.levels || []).forEach(function (l) { levels[l.key] = l; });
    var THREAT = { watch: ['low', 'no'], reinforced: ['medium', 'no'], caution: ['high', 'no'], danger: ['high', 'yes'] };
    var steps = qsa('[data-vig-step]');
    var tabs = qsa('[data-vig-go]');
    var prev = qs('[data-vig-prev]');
    var next = qs('[data-vig-next]');
    var issue = qs('[data-vig-issue]');
    var saveState = doc.getElementById('vig-save-state');
    var current = 1;
    var visited = { 1: true };
    var measuresTouched = false;
    var dirty = false;
    var saveTimer = null;
    var pdfTimer = null;
    var pdfStale = true;
    var pdfUrl = null;
    var small = window.matchMedia ? window.matchMedia('(max-width: 760px)') : { matches: false };

    function field(name) { return form.elements[name]; }
    function val(name) { var f = field(name); return f && f.value !== undefined ? String(f.value) : ''; }
    function radio(name) { var c = qs('input[name="' + name + '"]:checked', form); return c ? c.value : ''; }
    function codes(kind) { return qsa('input[data-vig-code="' + kind + '"]:checked', form); }
    function el(sel) { return qs(sel, form) || qs(sel); }
    function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function t(k) { return i18n[k] || ''; }

    function shapeSvg(shape, size) {
        var poly = shape === 'diamond' ? '<polygon points="12,1 23,12 12,23 1,12"/>' : (shape === 'octagon' ? '<polygon points="8,1.5 16,1.5 22.5,8 22.5,16 16,22.5 8,22.5 1.5,16 1.5,8"/>' : '<polygon points="12,2 23,21 1,21" stroke-linejoin="round"/>');
        var mark = shape === 'triangle' ? '<rect x="11" y="9" width="2" height="6.5" rx="1" class="vig-ico__mark"/><circle cx="12" cy="18" r="1.15" class="vig-ico__mark"/>' : '<rect x="11" y="6.5" width="2" height="7.5" rx="1" class="vig-ico__mark"/><circle cx="12" cy="17.2" r="1.2" class="vig-ico__mark"/>';
        return '<svg class="vig-ico" viewBox="0 0 24 24" width="' + size + '" height="' + size + '" aria-hidden="true" focusable="false"><g class="vig-ico__shape">' + poly + '</g>' + mark + '</svg>';
    }
    function pips(rank) { var o = '<span class="vig-pips" aria-hidden="true">'; for (var i = 1; i <= 4; i++) { o += '<i' + (i <= rank ? ' class="is-on"' : '') + '></i>'; } return o + '</span>'; }
    // Échéance affichée selon la durée choisie : heures, jours ou jusqu'à levée.
    function untilLabel() {
        var c = qs('input[name="validity"]:checked', form);
        if (c && c.getAttribute('data-lifted')) { return t('untilLifted'); }
        if (c && c.getAttribute('data-hours')) {
            var d = new Date(Date.now() + parseInt(c.getAttribute('data-hours'), 10) * 3600000);
            var z = function (n) { return (n < 10 ? '0' : '') + n; };
            return z(d.getUTCDate()) + '/' + z(d.getUTCMonth() + 1) + '/' + d.getUTCFullYear() + ' ' + z(d.getUTCHours()) + ':' + z(d.getUTCMinutes()) + ' UTC';
        }
        return dateLabel(parseInt(c ? c.value : '30', 10) || 30);
    }
    function durationLabel() {
        var c = qs('input[name="validity"]:checked', form);
        if (c && c.getAttribute('data-lifted')) { return t('untilLifted'); }
        if (c && c.getAttribute('data-hours')) { return c.getAttribute('data-hours') + ' ' + t('hours'); }
        return (c ? c.value : '30') + ' ' + t('days');
    }
    function dateLabel(days) {
        var p = String(data.today || '').split('-');
        if (p.length < 3) { return '—'; }
        var d = new Date(Date.UTC(+p[0], +p[1] - 1, +p[2] + days));
        function z(n) { return (n < 10 ? '0' : '') + n; }
        return z(d.getUTCDate()) + '/' + z(d.getUTCMonth() + 1) + '/' + d.getUTCFullYear();
    }

    // Service résolu : le choix du formulaire, sinon celui de la communauté.
    function service() {
        var code = val('service');
        var svcs = data.services || {};
        if (code === 'OTHER') { return { name: val('service_other').trim() || t('other'), contact: '', color: '#1F3A5F', desc: '' }; }
        if (code === '' && data.defaultService && svcs[data.defaultService]) { code = data.defaultService; }
        if (code !== '' && svcs[code]) {
            var s = svcs[code];
            return { name: s.name + (s.ac ? ' (' + s.ac + ')' : ''), contact: s.contact, color: s.color, desc: s.desc };
        }
        return { name: data.defaultLabel || t('noService'), contact: data.defaultChannel || '', color: '#1F3A5F', desc: '' };
    }

    function chips(list) {
        if (!list.length) { return '<span class="vig-hint">' + esc(t('none')) + '</span>'; }
        return '<ul class="vig-recap__chips">' + list.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul>';
    }

    function render() {
        var key = radio('level') || 'watch';
        var lv = levels[key] || { rank: 1, title: '', shape: 'triangle', hex: '#f2c200', ink: '#141414' };
        var th = THREAT[key] || THREAT.watch;
        var thr = qs('[data-vig-threat]', form); var arm = qs('[data-vig-armed]', form);
        if (thr) { thr.value = th[0]; }
        if (arm) { arm.value = th[1]; }
        var measures = codes('measures').map(function (c) { return c.getAttribute('data-label'); });
        var mcodes = codes('measures').map(function (c) { return c.value; });
        var reasons = codes('reasons').map(function (c) { return c.getAttribute('data-label'); });
        var order = '';
        if (mcodes.some(function (c) { return (data.actionCodes || []).indexOf(c) >= 0; })) { order = 'order'; }
        else if (mcodes.some(function (c) { return (data.holdCodes || []).indexOf(c) >= 0; })) { order = 'hold'; }
        var orderLabel = order ? (data.orders || {})[order] : '';
        var until = untilLabel();
        var svc = service();
        var delay = (data.delays || {})[radio('report_delay')] || '';
        var channel = val('report_channel').trim() || svc.contact;

        // Carte en direct
        var card = qs('[data-vig-card]');
        if (card) {
            card.style.setProperty('--lv', lv.hex); card.style.setProperty('--lv-ink', lv.ink);
            qs('[data-vig-card-ico]', card).innerHTML = shapeSvg(lv.shape, 40);
            qs('[data-vig-card-title]', card).textContent = lv.title;
            qs('[data-vig-card-rank]', card).innerHTML = '<span>' + lv.rank + '/4</span> ' + pips(lv.rank);
            qs('[data-vig-card-order]', card).textContent = orderLabel;
            var ul = qs('[data-vig-card-measures]', card);
            ul.innerHTML = measures.length ? measures.slice(0, 5).map(function (m) { return '<li>' + esc(m) + '</li>'; }).join('') + (measures.length > 5 ? '<li class="is-empty">+ ' + (measures.length - 5) + '</li>' : '') : '<li class="is-empty">' + esc(t('empty')) + '</li>';
            qs('[data-vig-card-until]', card).textContent = until;
            qs('[data-vig-card-service]', card).textContent = svc.name;
            qs('[data-vig-card-delay]', card).textContent = delay;
        }
        var untilEl = qs('[data-vig-until]', form); if (untilEl) { untilEl.textContent = until; }

        // Étape 4 : fiche du service
        var box = qs('[data-vig-svc]', form);
        if (box) {
            box.style.setProperty('--svc', svc.color);
            qs('[data-vig-svc-name]', box).textContent = svc.name;
            qs('[data-vig-svc-desc]', box).textContent = svc.desc || '';
            qs('[data-vig-svc-contact]', box).textContent = svc.contact || '';
        }
        var other = qs('[data-vig-other]', form); if (other) { other.hidden = val('service') !== 'OTHER'; }
        var chan = qs('[data-vig-channel]', form); if (chan) { chan.placeholder = svc.contact || t('channelAuto'); }

        // Étape 2 : compteurs et plafond
        ['measures', 'reasons'].forEach(function (kind) {
            var all = qsa('input[data-vig-code="' + kind + '"]', form);
            var n = all.filter(function (c) { return c.checked; }).length;
            var max = data.maxCodes || 6;
            all.forEach(function (c) { c.disabled = !c.checked && n >= max; });
            var badge = qs('[data-vig-count="' + kind + '"]', form); if (badge) { badge.textContent = String(n); }
            var m = qs('[data-vig-max="' + kind + '"]', form);
            if (m) { m.textContent = n + ' ' + t('of') + ' ' + max + (n >= max ? ' · ' + t('limit') : ''); m.classList.toggle('is-full', n >= max); }
        });

        // Récapitulatif
        var rc = function (k) { return qs('[data-r="' + k + '"]', form); };
        if (rc('level')) {
            rc('level').innerHTML = '<span class="vig-recap__lv" style="--lv:' + lv.hex + ';--lv-ink:' + lv.ink + '">' + shapeSvg(lv.shape, 24) + '<strong>' + esc(lv.title) + '</strong><span>' + lv.rank + '/4</span>' + pips(lv.rank) + '</span>' + (orderLabel ? '<br><strong>' + esc(orderLabel) + '</strong>' : '');
            rc('measures').innerHTML = chips(measures);
            rc('reasons').innerHTML = chips(reasons);
            rc('zone').textContent = val('zone').trim() || '—';
            rc('validity').textContent = durationLabel() + ' · ' + (until === t('untilLifted') ? t('noEnd') : t('until') + ' ' + until);
            var cls = (data.classes || {})[val('classification')] || val('classification');
            rc('doc').textContent = ((data.langs || {})[radio('lang')] || '') + ' · ' + cls;
            rc('service').textContent = svc.name;
            rc('channel').textContent = (channel || '—') + (delay ? ' · ' + delay : '');
            rc('address').textContent = t('addressSoon');
        }
    }

    // ------------------------------------------------------------------ Étapes
    function go(n) {
        n = Math.max(1, Math.min(steps.length, n));
        current = n; visited[n] = true;
        steps.forEach(function (s) { s.classList.toggle('is-on', +s.getAttribute('data-vig-step') === n); });
        tabs.forEach(function (b) {
            var k = +b.getAttribute('data-vig-go');
            b.classList.toggle('is-on', k === n);
            b.classList.toggle('is-done', k < n || (visited[k] && k !== n));
            b.setAttribute('aria-selected', k === n ? 'true' : 'false');
        });
        if (prev) { prev.hidden = n === 1; }
        if (next) { next.hidden = n === steps.length; }
        if (issue) { issue.hidden = n !== steps.length; }
        render();
        if (n === steps.length) { loadPdf(false); }
    }
    tabs.forEach(function (b) { b.addEventListener('click', function () { go(+b.getAttribute('data-vig-go')); var f = qs('.vig-steps', form); if (f && f.scrollIntoView) { f.scrollIntoView({ block: 'nearest' }); } }); });
    if (prev) { prev.addEventListener('click', function () { go(current - 1); }); }
    if (next) { next.addEventListener('click', function () { go(current + 1); var f = qs('.vig-steps', form); if (f && f.scrollIntoView) { f.scrollIntoView({ block: 'nearest' }); } }); }
    qsa('[data-vig-sub]', form).forEach(function (b) {
        b.addEventListener('click', function () {
            var k = b.getAttribute('data-vig-sub');
            qsa('[data-vig-sub]', form).forEach(function (x) { var on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-selected', on ? 'true' : 'false'); });
            qsa('[data-vig-subpanel]', form).forEach(function (p) { p.classList.toggle('is-on', p.getAttribute('data-vig-subpanel') === k); });
        });
    });

    // ------------------------------------------------------------------ Niveau : consignes d'office tant qu'elles ne sont pas touchées
    function applyDefaults(key) {
        var def = (levels[key] || {}).measures || [];
        qsa('input[data-vig-code="measures"]', form).forEach(function (c) { c.checked = def.indexOf(c.value) >= 0; });
    }
    (function initTouched() {
        var key = radio('level') || 'watch';
        var def = (levels[key] || {}).measures || [];
        var now = codes('measures').map(function (c) { return c.value; });
        var sameSet = now.length === def.filter(function (c) { return qs('input[data-vig-code="measures"][value="' + c + '"]', form); }).length && now.every(function (c) { return def.indexOf(c) >= 0; });
        measuresTouched = !sameSet;
    }());
    qsa('[data-vig-level]', form).forEach(function (r) {
        r.addEventListener('change', function () { if (!measuresTouched) { applyDefaults(r.value); } changed(); });
    });
    qsa('input[data-vig-code="measures"]', form).forEach(function (c) { c.addEventListener('change', function () { measuresTouched = true; }); });

    // ------------------------------------------------------------------ Filtre des listes
    qsa('[data-vig-find]', form).forEach(function (inp) {
        inp.addEventListener('input', function () {
            var panel = inp.closest('[data-vig-subpanel]');
            var q = inp.value.trim().toLowerCase();
            var any = false;
            qsa('[data-vig-group]', panel).forEach(function (g) {
                var shown = 0;
                qsa('[data-vig-opt]', g).forEach(function (o) { var ok = q === '' || (o.getAttribute('data-find') || '').indexOf(q) >= 0; o.hidden = !ok; if (ok) { shown++; } });
                g.hidden = shown === 0; if (shown) { any = true; }
            });
            var none = qs('[data-vig-nofind]', panel); if (none) { none.hidden = any; }
        });
    });

    // ------------------------------------------------------------------ Textes : compteurs et textes d'office
    function plainLength(s) { return s.replace(/^[ \t]*-[ \t]+/gm, '• ').replace(/\*\*(.+?)\*\*/g, '$1').replace(/(^|[^\w*])\*([^\s*][^*]*?)\*(?![\w*])/g, '$1$2').length; }
    var texts = qsa('[data-vig-text]', form);
    function count(f) {
        var ta = qs('textarea', f); var max = parseInt(f.getAttribute('data-max'), 10) || 0; var n = plainLength(ta.value);
        qs('[data-count]', f).textContent = String(n);
        f.classList.toggle('is-over', n > max); f.classList.toggle('is-near', n <= max && n > max * 0.9);
    }
    texts.forEach(function (f) { count(f); qs('textarea', f).addEventListener('input', function () { count(f); changed(); }); });
    function reloadDefaults() {
        fetch(base + '/defauts?lang=' + encodeURIComponent(radio('lang')) + '&classification=' + encodeURIComponent(val('classification')), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j || !j.ok) { return; }
                texts.forEach(function (f) {
                    var ta = qs('textarea', f); var k = ta.name.replace(/^texts\[|\]$/g, '');
                    var untouched = ta.value === (ta.getAttribute('data-orig') || '');
                    if (j.texts && typeof j.texts[k] === 'string') { ta.setAttribute('data-orig', j.texts[k]); if (untouched) { ta.value = j.texts[k]; } count(f); }
                });
                changed();
            }).catch(function () { /* textes laissés tels quels */ });
    }
    qsa('[data-vig-lang]', form).forEach(function (r) { r.addEventListener('change', reloadDefaults); });
    var cls = qs('[data-vig-class]', form); if (cls) { cls.addEventListener('change', reloadDefaults); }

    // ------------------------------------------------------------------ Brouillon et aperçu
    function setState(text, bad) { if (saveState) { saveState.textContent = text; saveState.style.color = bad ? '#f87171' : ''; } }
    function request(path, body) {
        return fetch(base + path, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json, application/pdf' } });
    }
    function saveDraft() {
        if (!ready) { return Promise.resolve(); }
        setState(t('saving'), false);
        return request('/brouillon', new FormData(form)).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.ok && res.j.ok) { var id = doc.getElementById('vig-draft-id'); if (id) { id.value = String(res.j.id); } dirty = false; setState(t('saved') + ' · ' + res.j.saved_at, false); }
                else { setState(t('saveFail') + ' : ' + (res.j.error || ''), true); }
            }).catch(function () { setState(t('saveFail'), true); });
    }
    function changed() {
        dirty = true; pdfStale = true; render();
        if (ready) { window.clearTimeout(saveTimer); saveTimer = window.setTimeout(saveDraft, 1500); }
        if (current === steps.length) { window.clearTimeout(pdfTimer); pdfTimer = window.setTimeout(function () { loadPdf(true); }, 1400); }
    }
    function pdfState(text, bad) { var s = qs('[data-vig-pdf-state]', form); if (s) { s.textContent = text; s.style.color = bad ? '#f87171' : ''; } }
    function loadPdf(force) {
        var frame = qs('[data-vig-pdf]', form);
        if (!frame || (!pdfStale && !force)) { return; }
        if (small.matches) { pdfState('', false); return; }
        pdfState(t('previewLoading'), false);
        pdfStale = false;
        request('/apercu', new FormData(form)).then(function (r) {
            var ct = r.headers.get('Content-Type') || '';
            if (r.ok && ct.indexOf('pdf') >= 0) { return r.blob().then(function (b) { if (pdfUrl) { URL.revokeObjectURL(pdfUrl); } pdfUrl = URL.createObjectURL(b); frame.src = pdfUrl + '#toolbar=0&navpanes=0&view=FitH'; pdfState(t('previewReady'), false); }); }
            return checkErrors().then(function (msg) { pdfState(msg || t('previewFail'), true); });
        }).catch(function () { pdfState(t('previewFail'), true); });
    }
    function checkErrors() {
        var fd = new FormData(form); fd.set('format', 'json');
        return request('/apercu', fd).then(function (r) { return r.json(); }).then(function (j) {
            var errs = (j && j.errors) || [];
            showServiceError(errs);
            return errs.join(' ');
        }).catch(function () { return ''; });
    }
    function showServiceError(errs) {
        var box = qs('[data-vig-other-err]', form); if (!box) { return; }
        var m = (errs || []).filter(function (e) { return /ervice/.test(e); }).join(' ');
        box.textContent = m; box.hidden = m === '';
    }
    var refresh = qs('[data-vig-refresh]', form); if (refresh) { refresh.addEventListener('click', function () { pdfStale = true; loadPdf(true); }); }
    var otherInput = qs('[data-vig-other-input]', form);
    if (otherInput) { otherInput.addEventListener('change', function () { if (otherInput.value.trim() !== '') { checkErrors(); } }); }
    qsa('[data-vig-save]', form).forEach(function (b) { b.addEventListener('click', function () { window.clearTimeout(saveTimer); saveDraft(); }); });

    // Toute modification de saisie met à jour la carte, le récapitulatif et le brouillon.
    form.addEventListener('input', function (ev) { if (ev.target && ev.target.closest && !ev.target.closest('[data-vig-find]') && ev.target.name !== 'draft_title') { changed(); } else if (ev.target && ev.target.name === 'draft_title') { dirty = true; } });
    form.addEventListener('change', function (ev) { if (ev.target && ev.target.name && ev.target.name !== 'draft_title') { changed(); } });
    form.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' && ev.target && /^(text|search)$/.test(ev.target.type || '')) { ev.preventDefault(); } });
    form.addEventListener('submit', function (ev) {
        var sub = ev.submitter;
        if (sub && sub.hasAttribute('formtarget')) { return; }
        if (sub && sub.hasAttribute('formaction') && /supprimer/.test(sub.getAttribute('formaction'))) { dirty = false; return; }
        if (val('service') === 'OTHER' && val('service_other').trim() === '') {
            ev.preventDefault(); go(4);
            var box = qs('[data-vig-other-err]', form); if (box) { box.textContent = t('nameError'); box.hidden = false; }
            if (otherInput) { otherInput.focus(); }
            return;
        }
        dirty = false;
    });
    window.addEventListener('beforeunload', function (ev) { if (dirty && ready) { ev.preventDefault(); ev.returnValue = ''; } });

    render();
    var errStep = parseInt(form.getAttribute('data-error-step'), 10) || 0;
    go(errStep > 0 ? errStep : 1);
    dirty = false;
}());
