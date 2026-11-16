/**
 * Back-office › Accès renseignement (centre de gestion des accès) : onglets (flèches, Début/Fin, ?onglet= dans l'adresse),
 * filtres des tableaux, filtres et sélection des personnels, membres visés par les fenêtres d'habilitation et d'accès,
 * tiroir « fiche du membre » (chargé à la demande), récapitulatif de la délivrance, fenêtre du code émis et copie du code.
 * Les fenêtres elles-mêmes (ouverture, Échap, focus rendu) relèvent de back-office-bands.js ; showModal() garde le focus
 * dans la fenêtre ouverte. Aucun texte ici : tout libellé vient de la page (traduite côté serveur).
 */
(function () {
    'use strict';
    var root = document.querySelector('[data-sseacc]');
    if (!root) { return; }
    var each = function (list, fn) { Array.prototype.forEach.call(list, fn); };
    var setParam = function (key, value) {
        try {
            var u = new URL(window.location.href);
            if (value) { u.searchParams.set(key, value); } else { u.searchParams.delete(key); }
            window.history.replaceState(null, '', u.toString());
        } catch (e) { /* adresse inchangée */ }
    };

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
        setParam('onglet', tab.getAttribute('data-sseacc-tab'));
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

    /* ——— Filtres des codes et du journal (état, type d'événement, membre concerné, recherche) ——— */
    each(root.querySelectorAll('[data-sseacc-filter]'), function (bar) {
        var panel = bar.closest('.sseacc-panel');
        var rows = Array.prototype.slice.call(panel.querySelectorAll('[data-sseacc-row]'));
        var none = panel.querySelector('[data-sseacc-noresult]');
        var search = bar.querySelector('[data-sseacc-search]');
        var event = bar.querySelector('[data-sseacc-event]');
        var subject = bar.querySelector('[data-sseacc-subject]');
        var status = '';
        bar.hidden = false;
        function apply() {
            var q = search ? search.value.trim().toLowerCase() : '';
            var ev = event ? event.value : '';
            var sub = subject ? subject.value : '';
            var shown = 0;
            rows.forEach(function (r) {
                var ok = (!status || r.getAttribute('data-status') === status)
                    && (!ev || r.getAttribute('data-event') === ev)
                    && (!sub || r.getAttribute('data-subject') === sub)
                    && (!q || (r.getAttribute('data-text') || '').indexOf(q) !== -1);
                r.hidden = !ok;
                if (ok) { shown++; }
            });
            if (none) { none.hidden = shown > 0; }
        }
        each(bar.querySelectorAll('[data-sseacc-status]'), function (b) {
            b.addEventListener('click', function () {
                status = b.getAttribute('data-sseacc-status');
                each(bar.querySelectorAll('[data-sseacc-status]'), function (o) {
                    o.setAttribute('aria-pressed', o === b ? 'true' : 'false');
                });
                apply();
            });
        });
        if (search) { search.addEventListener('input', apply); }
        if (event) { event.addEventListener('change', apply); }
        if (subject) { subject.addEventListener('change', apply); }
    });

    /* ——— Personnels : filtres instantanés (le formulaire GET reste la voie sans JavaScript) et sélection ——— */
    var people = root.querySelector('[data-sseh-people]');
    var picks = people ? Array.prototype.slice.call(people.querySelectorAll('[data-sseh-pick]')) : [];
    var rowsP = people ? Array.prototype.slice.call(people.querySelectorAll('[data-sseh-row]')) : [];
    var countSel = function () {
        var n = 0;
        picks.forEach(function (p) { if (p.checked) { n++; } });
        var out = people ? people.querySelector('[data-sseh-count]') : null;
        if (out) { out.textContent = String(n); }
        each(root.querySelectorAll('[data-sseh-bulk-open]'), function (b) { b.disabled = n === 0; });
        var all = people ? people.querySelector('[data-sseh-all]') : null;
        if (all) {
            var visible = picks.filter(function (p) { return !p.closest('[data-sseh-row]').hidden; });
            var on = visible.filter(function (p) { return p.checked; }).length;
            all.checked = visible.length > 0 && on === visible.length;
            all.indeterminate = on > 0 && on < visible.length;
        }
    };
    if (people) {
        var form = people.querySelector('[data-sseh-filter]');
        if (form) {
            var submit = form.querySelector('[data-sseh-submit]');
            if (submit) { submit.hidden = true; }
            var field = function (k) { return form.querySelector('[data-sseh-f="' + k + '"]'); };
            var applyP = function () {
                var q = field('q') ? field('q').value.trim().toLowerCase() : '';
                var unit = field('unit') ? field('unit').value : '';
                var level = field('level') ? field('level').value : '';
                var flag = field('flag') ? field('flag').value : '';
                var shown = 0;
                rowsP.forEach(function (r) {
                    var flags = ' ' + (r.getAttribute('data-flags') || '') + ' ';
                    var ok = (!unit || r.getAttribute('data-unit') === unit)
                        && (!level || r.getAttribute('data-level') === level)
                        && (!flag || flags.indexOf(' ' + flag + ' ') !== -1)
                        && (!q || (r.getAttribute('data-text') || '').indexOf(q) !== -1);
                    r.hidden = !ok;
                    if (ok) { shown++; }
                });
                var out = people.querySelector('[data-sseh-shown]');
                if (out) { out.textContent = String(shown); }
                var none = people.querySelector('[data-sseh-noresult]');
                if (none) { none.hidden = shown > 0; }
                setParam('q', q);
                setParam('unite', unit);
                setParam('niveau', level);
                setParam('etat', flag);
                countSel();
            };
            form.addEventListener('submit', function (e) { e.preventDefault(); applyP(); });
            form.addEventListener('input', applyP);
            form.addEventListener('change', applyP);
        }
        picks.forEach(function (p) { p.addEventListener('change', countSel); });
        var all = people.querySelector('[data-sseh-all]');
        if (all) {
            all.addEventListener('change', function () {
                picks.forEach(function (p) {
                    if (!p.closest('[data-sseh-row]').hidden) { p.checked = all.checked; }
                });
                countSel();
            });
        }
        countSel();
    }

    /* ——— Membres visés par les fenêtres d'habilitation et d'accès (avant l'ouverture : phase de capture) ——— */
    var nameOf = function (id) {
        var row = root.querySelector('[data-sseh-row][data-id="' + id + '"]');
        return row ? row.getAttribute('data-name') : '';
    };
    document.addEventListener('click', function (e) {
        var open = e.target.closest('[data-bo-dialog-open="sseh-clearance"], [data-bo-dialog-open="sseh-access"]');
        if (!open) { return; }
        var dlg = document.getElementById(open.getAttribute('data-bo-dialog-open'));
        var f = dlg ? dlg.querySelector('[data-sseh-targets-form]') : null;
        if (!f) { return; }
        var ids = [];
        var names = [];
        if (open.hasAttribute('data-sseh-member')) {
            ids = [open.getAttribute('data-sseh-member')];
            names = [open.getAttribute('data-sseh-name') || nameOf(ids[0])];
        } else {
            picks.forEach(function (p) {
                if (p.checked) {
                    ids.push(p.value);
                    names.push(nameOf(p.value));
                }
            });
        }
        each(f.querySelectorAll('input[name="member_ids[]"]'), function (i) { i.parentNode.removeChild(i); });
        ids.forEach(function (id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'member_ids[]';
            input.value = id;
            f.appendChild(input);
        });
        var out = f.querySelector('[data-sseh-names]');
        if (out) { out.textContent = names.filter(Boolean).join(', ') || '—'; }
        var effect = open.getAttribute('data-sseh-effect');
        var effInput = f.querySelector('[data-sseh-effect-input]');
        if (effInput && effect) {
            effInput.value = effect;
            each(f.querySelectorAll('[data-sseh-effect-show]'), function (s) { s.hidden = s.getAttribute('data-sseh-effect-show') !== effect; });
        }
    }, true);

    /* ——— Tiroir « fiche du membre » ——— */
    var drawer = root.querySelector('[data-sseh-drawer]');
    if (drawer && typeof drawer.showModal === 'function') {
        var body = drawer.querySelector('[data-sseh-drawer-body]');
        var lastOpener = null;
        var openDrawer = function () {
            if (!drawer.open) { drawer.showModal(); }
            var close = drawer.querySelector('[data-bo-dialog-close]');
            if (close) { close.focus(); }
        };
        if (drawer.hasAttribute('data-sseh-autoopen')) {
            drawer.close();
            openDrawer();
        }
        root.addEventListener('click', function (e) {
            var link = e.target.closest('[data-sseh-fiche]');
            if (!link || e.metaKey || e.ctrlKey || e.shiftKey) { return; }
            e.preventDefault();
            lastOpener = link;
            var id = link.getAttribute('data-sseh-fiche');
            var src = drawer.getAttribute('data-sseh-src') + '&membre=' + encodeURIComponent(id);
            window.fetch(src, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { if (!r.ok) { throw new Error(String(r.status)); } return r.text(); })
                .then(function (html) {
                    body.innerHTML = html;
                    setParam('membre', id);
                    openDrawer();
                })
                .catch(function () { window.location.href = link.href; });
        });
        drawer.addEventListener('click', function (e) {
            // Le bouton Fermer est un lien (repli sans JavaScript) : ici, la fenêtre se ferme sans recharger.
            if (e.target.closest('a[data-bo-dialog-close]')) { e.preventDefault(); }
        });
        drawer.addEventListener('close', function () {
            setParam('membre', '');
            if (lastOpener) { lastOpener.focus(); lastOpener = null; }
        });
    }

    /* ——— Récapitulatif de la délivrance ——— */
    var issueForm = root.querySelector('[data-sseacc-issue]');
    if (issueForm) {
        var text = function (el) {
            if (el.tagName === 'SELECT') { return el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : ''; }
            if (el.type === 'radio') {
                var t = el.closest('label').querySelector('.sseacc-choice__name, .sseacc-choice__title');
                return t ? t.textContent.replace(/\s+/g, ' ').trim() : el.value;
            }
            return el.value.trim();
        };
        var refresh = function () {
            each(issueForm.querySelectorAll('[data-sseacc-recap-src]'), function (el) {
                if (el.type === 'radio' && !el.checked) { return; }
                var out = issueForm.querySelector('[data-sseacc-recap="' + el.getAttribute('data-sseacc-recap-src') + '"]');
                if (out) { out.textContent = text(el) || '—'; }
            });
        };
        issueForm.addEventListener('input', refresh);
        issueForm.addEventListener('change', refresh);
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
    each(root.querySelectorAll('[data-sseacc-copy]'), function (btn) {
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
