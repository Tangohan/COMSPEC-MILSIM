/**
 * Mon compte › Profil et préférences : état d'enregistrement, sommaire actif, notifications par e-mail.
 * Le formulaire reste un envoi classique : sans JavaScript, tout s'enregistre de la même façon.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-prefs]');
    if (!root) return;
    var form = root.querySelector('[data-prefs-form]');
    var bar = root.querySelector('[data-savebar]');
    var msg = bar ? bar.querySelector('[data-save-message]') : null;
    var submit = root.querySelector('[data-prefs-submit]');
    var resetBtn = root.querySelector('[data-prefs-reset]');

    /* ——— État d'enregistrement ——— */
    function snapshot() {
        if (!form) return '';
        var parts = [];
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el.name || el.name === '_csrf_token' || el.disabled) return;
            if (el.type === 'checkbox' || el.type === 'radio') {
                parts.push(el.name + '=' + (el.checked ? el.value : ''));
            } else if (el.type !== 'submit' && el.type !== 'button' && el.type !== 'reset') {
                parts.push(el.name + '=' + el.value);
            }
        });
        return parts.join('&');
    }

    var initial = snapshot();
    var initialState = bar ? bar.getAttribute('data-save-state') : 'clean';
    var saving = false;

    function setState(state) {
        if (!bar) return;
        bar.setAttribute('data-save-state', state);
        if (msg) msg.textContent = msg.getAttribute('data-msg-' + state) || '';
        if (resetBtn) resetBtn.hidden = state !== 'dirty';
    }

    function refresh() {
        if (saving) return;
        var dirty = snapshot() !== initial;
        setState(dirty ? 'dirty' : (initialState === 'clean' ? 'clean' : initialState));
    }

    if (form) {
        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        form.addEventListener('reset', function () {
            window.setTimeout(function () {
                updateStats();
                refresh();
            }, 0);
        });
        form.addEventListener('submit', function () {
            saving = true;
            setState('saving');
            if (submit) {
                submit.disabled = true;
                submit.setAttribute('aria-busy', 'true');
            }
        });
        // Retour arrière du navigateur : la page revient depuis le cache, on réactive le bouton.
        window.addEventListener('pageshow', function (ev) {
            if (ev.persisted) {
                saving = false;
                if (submit) {
                    submit.disabled = false;
                    submit.removeAttribute('aria-busy');
                }
                refresh();
            }
        });
        window.addEventListener('beforeunload', function (ev) {
            if (!saving && snapshot() !== initial) {
                ev.preventDefault();
                ev.returnValue = '';
            }
        });
    }

    var alertBox = root.querySelector('[data-prefs-alert]');
    if (alertBox && !window.location.hash) {
        try { alertBox.focus({ preventScroll: false }); } catch (e) { alertBox.focus(); }
    }

    /* ——— Sommaire : section visible ——— */
    var tocLinks = Array.prototype.slice.call(root.querySelectorAll('[data-toc-link]'));
    if ('IntersectionObserver' in window && tocLinks.length) {
        var byId = {};
        tocLinks.forEach(function (a) { byId[a.getAttribute('data-toc-link')] = a; });
        var seen = {};
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { seen[en.target.id] = en.isIntersecting; });
            var current = null;
            tocLinks.forEach(function (a) {
                var id = a.getAttribute('data-toc-link');
                if (!current && seen[id]) current = id;
            });
            if (!current) return;
            tocLinks.forEach(function (a) {
                var on = a.getAttribute('data-toc-link') === current;
                a.classList.toggle('is-current', on);
                if (on) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
            });
        }, { rootMargin: '-15% 0px -60% 0px' });
        tocLinks.forEach(function (a) {
            var sec = document.getElementById(a.getAttribute('data-toc-link'));
            if (sec) io.observe(sec);
        });
    }

    /* ——— Adresse courte : minuscules au fil de la saisie ——— */
    var slug = root.querySelector('[data-slug-input]');
    if (slug) {
        slug.addEventListener('input', function () {
            var v = slug.value.toLowerCase().replace(/\s+/g, '-');
            if (v !== slug.value) slug.value = v;
        });
    }

    /* ——— Fuseau horaire : heure locale correspondante ——— */
    var tz = root.querySelector('[data-tz-input]');
    var tzPreview = root.querySelector('[data-tz-preview]');
    if (tz && tzPreview && window.Intl && Intl.DateTimeFormat) {
        var tzDefault = tzPreview.textContent;
        var showTime = function () {
            var zone = (tz.value || '').trim();
            if (zone === '') {
                tzPreview.textContent = tzDefault;
                return;
            }
            try {
                var t = new Intl.DateTimeFormat(document.documentElement.lang || 'fr', { timeZone: zone, hour: '2-digit', minute: '2-digit' }).format(new Date());
                tzPreview.textContent = 'Il est ' + t + ' dans ce fuseau.';
                tz.removeAttribute('aria-invalid');
            } catch (e) {
                tzPreview.textContent = 'Fuseau non reconnu : choisissez-en un dans la liste (ex. Europe/Paris).';
            }
        };
        tz.addEventListener('input', showTime);
        tz.addEventListener('change', showTime);
        showTime();
    }

    /* ——— Notifications par e-mail ——— */
    var search = document.getElementById('notif-search');
    var stats = document.getElementById('notif-stats');
    var filterNote = document.getElementById('notif-filter-note');
    var boxes = function () { return Array.prototype.slice.call(root.querySelectorAll('.notif-email-toggle')); };
    var visibleRows = function () {
        return Array.prototype.slice.call(root.querySelectorAll('[data-notif-item]')).filter(function (row) { return !row.hidden; });
    };

    function updateStats() {
        if (!stats) return;
        var all = boxes();
        var on = all.filter(function (b) { return b.checked; }).length;
        stats.textContent = on + ' sur ' + all.length + ' activées';
    }

    function applyFilter() {
        if (!search) return;
        var q = (search.value || '').toLowerCase().trim();
        Array.prototype.forEach.call(root.querySelectorAll('[data-notif-group]'), function (group) {
            var shown = 0;
            Array.prototype.forEach.call(group.querySelectorAll('[data-notif-item]'), function (row) {
                var ok = q === '' || (row.getAttribute('data-notif-item') || '').indexOf(q) !== -1;
                row.hidden = !ok;
                if (ok) shown++;
            });
            group.hidden = shown === 0;
        });
        if (filterNote) filterNote.hidden = q === '';
    }

    function changed() {
        updateStats();
        refresh();
    }

    if (search) search.addEventListener('input', applyFilter);
    var resetFilter = document.getElementById('notif-reset-filter');
    if (resetFilter) {
        resetFilter.addEventListener('click', function () {
            if (search) search.value = '';
            applyFilter();
            if (search) search.focus();
        });
    }
    Array.prototype.forEach.call(root.querySelectorAll('[data-notif-all]'), function (btn) {
        btn.addEventListener('click', function () {
            var on = btn.getAttribute('data-notif-all') === '1';
            visibleRows().forEach(function (row) {
                var b = row.querySelector('.notif-email-toggle');
                if (b) b.checked = on;
            });
            changed();
        });
    });
    Array.prototype.forEach.call(root.querySelectorAll('[data-notif-group]'), function (group) {
        Array.prototype.forEach.call(group.querySelectorAll('[data-group-toggle]'), function (btn) {
            btn.addEventListener('click', function () {
                var on = btn.getAttribute('data-group-toggle') === '1';
                Array.prototype.forEach.call(group.querySelectorAll('.notif-email-toggle'), function (b) { b.checked = on; });
                changed();
            });
        });
    });
    Array.prototype.forEach.call(root.querySelectorAll('[data-notif-preset]'), function (btn) {
        btn.addEventListener('click', function () {
            var mode = btn.getAttribute('data-notif-preset');
            boxes().forEach(function (b) {
                var id = b.id || '';
                if (mode === 'minimum') {
                    b.checked = id.indexOf('new_device_login') !== -1 || id.indexOf('multiple_login_attempts') !== -1;
                } else if (mode === 'standard') {
                    b.checked = id.indexOf('community_report_new_staff') === -1 && id.indexOf('new_community_member') === -1;
                }
            });
            changed();
        });
    });
    boxes().forEach(function (b) { b.addEventListener('change', updateStats); });
    updateStats();
})();
