/*
 * Fiche identité en dossier : onglets accessibles (flèches, Début, Fin), section ouverte gardée dans l'adresse (#papiers).
 * Sans ce script, toutes les sections restent visibles les unes sous les autres et les onglets sont de simples liens.
 */
(function () {
    'use strict';
    var root = document.querySelector('[data-dos]');
    if (!root) {
        return;
    }
    // Portrait de l'en-tête introuvable : la silhouette remplace l'icône d'image cassée.
    var headImg = root.querySelector('[data-dos-portrait-img]');
    if (headImg) {
        var broke = function () { headImg.parentNode.classList.add('is-broken'); };
        headImg.addEventListener('error', broke);
        if (headImg.complete && headImg.naturalWidth === 0) { broke(); }
    }
    var tabs = Array.prototype.slice.call(root.querySelectorAll('[role="tab"]'));
    var panels = Array.prototype.slice.call(root.querySelectorAll('[role="tabpanel"]'));
    if (!tabs.length || tabs.length !== panels.length) {
        return;
    }
    root.classList.add('dos-js');

    function idOf(tab) {
        return tab.getAttribute('aria-controls');
    }

    function show(id, focus, push) {
        var found = false;
        tabs.forEach(function (tab) {
            var on = idOf(tab) === id;
            found = found || on;
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
            tab.setAttribute('tabindex', on ? '0' : '-1');
        });
        if (!found) {
            return false;
        }
        panels.forEach(function (panel) {
            panel.hidden = panel.id !== id;
        });
        // Barre d'onglets défilante (téléphone) : l'onglet ouvert reste visible.
        var current = tabs.filter(function (t) { return idOf(t) === id; })[0];
        if (current && current.parentNode && current.parentNode.scrollWidth > current.parentNode.clientWidth) {
            current.parentNode.scrollLeft = Math.max(0, current.offsetLeft - 24);
        }
        if (focus) {
            var active = tabs.filter(function (t) { return idOf(t) === id; })[0];
            if (active) {
                active.focus();
            }
        }
        if (push && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', '#' + id);
        }
        return true;
    }

    function fromHash() {
        var hash = (window.location.hash || '').replace(/^#/, '');
        if (hash && show(hash, false, false)) {
            return;
        }
        // Une ancre à l'intérieur d'une section ouvre cette section.
        if (hash) {
            var target = document.getElementById(hash);
            var panel = target ? target.closest('[role="tabpanel"]') : null;
            if (panel && show(panel.id, false, false)) {
                target.scrollIntoView();
                return;
            }
        }
        show(idOf(tabs[0]), false, false);
    }

    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            show(idOf(tab), false, true);
        });
        tab.addEventListener('keydown', function (e) {
            var next = null;
            if (e.key === 'ArrowRight') { next = tabs[(i + 1) % tabs.length]; }
            else if (e.key === 'ArrowLeft') { next = tabs[(i - 1 + tabs.length) % tabs.length]; }
            else if (e.key === 'Home') { next = tabs[0]; }
            else if (e.key === 'End') { next = tabs[tabs.length - 1]; }
            if (next) {
                e.preventDefault();
                show(idOf(next), true, true);
            }
        });
    });
    // Liens internes du dossier (actions de l'en-tête vers #avis, #papiers, #photos).
    root.addEventListener('click', function (e) {
        var link = e.target && e.target.closest ? e.target.closest('a[data-dos-go]') : null;
        if (!link) {
            return;
        }
        var id = (link.getAttribute('href') || '').replace(/^.*#/, '');
        if (id && show(id, false, true)) {
            e.preventDefault();
            var el = document.getElementById(id);
            if (el) {
                el.scrollIntoView();
            }
        }
    });
    window.addEventListener('hashchange', fromHash);
    fromHash();
})();
