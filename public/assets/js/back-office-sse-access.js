/**
 * Back-office › Accès renseignement : onglets (flèches, Début/Fin, ?onglet= dans l'adresse), filtres des tableaux,
 * récapitulatif de la fenêtre de délivrance, ouverture de la fenêtre du code émis et copie du code.
 * Les fenêtres elles-mêmes (ouverture, Échap, focus rendu) relèvent de back-office-bands.js. Aucun texte ici :
 * tout libellé vient de la page (traduite côté serveur).
 */
(function () {
    'use strict';
    var root = document.querySelector('[data-sseacc]');
    if (!root) { return; }

    /* ——— Onglets ——— */
    var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-sseacc-tab]'));
    function select(tab, focus) {
        tabs.forEach(function (t) {
            var on = t === tab;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.setAttribute('tabindex', on ? '0' : '-1');
            var panel = document.getElementById(t.getAttribute('aria-controls'));
            if (panel) { panel.hidden = !on; }
        });
        if (focus) { tab.focus(); }
        try {
            var u = new URL(window.location.href);
            u.searchParams.set('onglet', tab.getAttribute('data-sseacc-tab'));
            window.history.replaceState(null, '', u.toString());
        } catch (e) { /* adresse inchangée */ }
    }
    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            select(tab, false);
        });
        tab.addEventListener('keydown', function (e) {
            var next = null;
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { next = tabs[(i + 1) % tabs.length]; }
            if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { next = tabs[(i - 1 + tabs.length) % tabs.length]; }
            if (e.key === 'Home') { next = tabs[0]; }
            if (e.key === 'End') { next = tabs[tabs.length - 1]; }
            if (next) {
                e.preventDefault();
                select(next, true);
            }
        });
    });

    /* ——— Filtres (état, type d'événement, recherche) ——— */
    Array.prototype.forEach.call(root.querySelectorAll('[data-sseacc-filter]'), function (bar) {
        var panel = bar.closest('.sseacc-panel');
        var rows = Array.prototype.slice.call(panel.querySelectorAll('[data-sseacc-row]'));
        var none = panel.querySelector('[data-sseacc-noresult]');
        var search = bar.querySelector('[data-sseacc-search]');
        var event = bar.querySelector('[data-sseacc-event]');
        var status = '';
        bar.hidden = false;
        function apply() {
            var q = search ? search.value.trim().toLowerCase() : '';
            var ev = event ? event.value : '';
            var shown = 0;
            rows.forEach(function (r) {
                var ok = (!status || r.getAttribute('data-status') === status)
                    && (!ev || r.getAttribute('data-event') === ev)
                    && (!q || (r.getAttribute('data-text') || '').indexOf(q) !== -1);
                r.hidden = !ok;
                if (ok) { shown++; }
            });
            if (none) { none.hidden = shown > 0; }
        }
        Array.prototype.forEach.call(bar.querySelectorAll('[data-sseacc-status]'), function (b, _, all) {
            b.addEventListener('click', function () {
                status = b.getAttribute('data-sseacc-status');
                Array.prototype.forEach.call(bar.querySelectorAll('[data-sseacc-status]'), function (o) {
                    o.setAttribute('aria-pressed', o === b ? 'true' : 'false');
                });
                apply();
            });
        });
        if (search) { search.addEventListener('input', apply); }
        if (event) { event.addEventListener('change', apply); }
    });

    /* ——— Récapitulatif de la délivrance ——— */
    var form = root.querySelector('[data-sseacc-issue]');
    if (form) {
        var text = function (el) {
            if (el.tagName === 'SELECT') { return el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : ''; }
            if (el.type === 'radio') {
                var t = el.closest('label').querySelector('.sseacc-choice__name, .sseacc-choice__title');
                return t ? t.textContent.replace(/\s+/g, ' ').trim() : el.value;
            }
            return el.value.trim();
        };
        var refresh = function () {
            Array.prototype.forEach.call(form.querySelectorAll('[data-sseacc-recap-src]'), function (el) {
                if (el.type === 'radio' && !el.checked) { return; }
                var out = form.querySelector('[data-sseacc-recap="' + el.getAttribute('data-sseacc-recap-src') + '"]');
                if (out) { out.textContent = text(el) || '—'; }
            });
        };
        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        refresh();
    }

    /* ——— Code émis : fenêtre modale à l'arrivée, annonce et copie ——— */
    var issued = root.querySelector('dialog[data-sseacc-autoopen]');
    if (issued && typeof issued.showModal === 'function') {
        var value = issued.querySelector('[data-sseacc-code]');
        var plain = value ? value.textContent : '';
        issued.close();
        issued.showModal();
        if (value) {
            // Réécrit après l'ouverture : la région « status » annonce le code aux lecteurs d'écran.
            value.textContent = '';
            window.setTimeout(function () { value.textContent = plain; }, 60);
        }
        var copyBtn = issued.querySelector('[data-sseacc-copy]');
        if (copyBtn) { copyBtn.focus(); }
    }
    Array.prototype.forEach.call(root.querySelectorAll('[data-sseacc-copy]'), function (btn) {
        btn.addEventListener('click', function () {
            var src = document.getElementById(btn.getAttribute('data-sseacc-copy'));
            var done = btn.parentNode.querySelector('[data-sseacc-copied]');
            if (!src) { return; }
            var code = src.textContent.trim();
            var shown = function () { if (done) { done.hidden = false; } };
            var fallback = function () {
                var range = document.createRange();
                range.selectNodeContents(src);
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
                try { if (document.execCommand('copy')) { shown(); } } catch (e) { /* le code reste sélectionné */ }
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(code).then(shown, fallback);
            } else {
                fallback();
            }
        });
    });
})();
