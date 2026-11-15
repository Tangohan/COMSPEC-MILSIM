/*
 * SSE > Réglages de la notice : onglets « Notice » et « Codification ». Sans JavaScript, les deux sections s'empilent et le
 * même formulaire les enregistre ensemble. Aucun texte dans ce fichier.
 */
(function () {
    'use strict';
    var nav = document.querySelector('[data-wc-stabs]');
    if (!nav) { return; }
    var tabs = Array.prototype.slice.call(nav.querySelectorAll('[data-wc-stab]'));
    var panes = Array.prototype.slice.call(document.querySelectorAll('[data-wc-pane]'));
    function show(key, focus) {
        tabs.forEach(function (t) {
            var on = t.getAttribute('data-wc-stab') === key;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.tabIndex = on ? 0 : -1;
            if (on && focus) { t.focus(); }
        });
        panes.forEach(function (p) { p.hidden = p.getAttribute('data-wc-pane') !== key; });
        try { window.history.replaceState(null, '', key === 'codes' ? '#codification' : '#notice'); } catch (e) { /* sans effet */ }
    }
    nav.hidden = false;
    tabs.forEach(function (t, i) {
        t.addEventListener('click', function () { show(t.getAttribute('data-wc-stab')); });
        t.addEventListener('keydown', function (e) {
            var n = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : -1;
            if (n >= 0) { e.preventDefault(); show(tabs[(n + tabs.length) % tabs.length].getAttribute('data-wc-stab'), true); }
        });
    });
    // Un champ invalide dans le volet masqué : on l'ouvre.
    document.addEventListener('invalid', function (e) {
        var p = e.target.closest ? e.target.closest('[data-wc-pane]') : null;
        if (p && p.hidden) { show(p.getAttribute('data-wc-pane')); }
    }, true);
    show(window.location.hash === '#codification' ? 'codes' : 'notice');
})();
