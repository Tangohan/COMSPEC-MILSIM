/**
 * Annonce de JNET sur le portail.
 *  - Fenêtre #jnet-announce : s'ouvre une fois, piège le focus, se ferme par Échap, la croix, « Plus tard » ou le fond,
 *    et note le « vu » côté serveur (api/jnet/annonce/fenetre/vu). localStorage ne sert que de secours si l'écriture échoue.
 *  - Bandeau [data-jnb] du tableau de bord : le masquer et le réafficher se notent côté serveur, sans recharger la page.
 * Aucun texte affiché n'est écrit ici : tout vient de la page.
 */
(function () {
    'use strict';

    // La fenêtre et le bandeau chargent le même script : une seule initialisation par page.
    if (window.__jnetAnnounce) { return; }
    window.__jnetAnnounce = true;

    function post(url, csrf, extra) {
        var body = new FormData();
        body.append('_csrf_token', csrf);
        Object.keys(extra || {}).forEach(function (k) { body.append(k, extra[k]); });
        try {
            return fetch(url, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true, headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok; }, function () { return false; });
        } catch (e) {
            return Promise.resolve(false);
        }
    }
    function store(key, value) {
        try {
            if (value === null) { window.localStorage.removeItem(key); } else { window.localStorage.setItem(key, value); }
        } catch (e) { /* stockage indisponible */ }
    }
    function read(key) {
        try { return window.localStorage.getItem(key); } catch (e) { return null; }
    }

    // —— Fenêtre ——
    var box = document.getElementById('jnet-announce');
    if (box) {
        var card = box.querySelector('[role="dialog"]');
        var csrf = box.getAttribute('data-csrf') || '';
        var saveUrl = box.getAttribute('data-save-url') || '';
        var skey = box.getAttribute('data-storage-key') || 'jnet.announce';
        var opener = null;
        var inFrame = false;
        try { inFrame = window.self !== window.top; } catch (e) { inFrame = true; }

        var focusables = function () {
            return Array.prototype.slice.call(card.querySelectorAll('a[href], button:not([disabled])'))
                .filter(function (el) { return el.offsetParent !== null; });
        };
        var close = function (completed) {
            if (box.hidden) { return; }
            box.hidden = true;
            box.classList.remove('is-open');
            document.documentElement.style.overflow = '';
            document.removeEventListener('keydown', onKey, true);
            store(skey, '1');
            post(saveUrl, csrf, { completed: completed ? '1' : '0' });
            if (opener && opener.focus) { try { opener.focus(); } catch (e) { /* élément disparu */ } }
        };
        var onKey = function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                close(false);
                return;
            }
            if (e.key !== 'Tab') { return; }
            var list = focusables();
            if (!list.length) { e.preventDefault(); return; }
            var first = list[0];
            var last = list[list.length - 1];
            var active = document.activeElement;
            if (!card.contains(active)) { e.preventDefault(); first.focus(); }
            else if (e.shiftKey && active === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && active === last) { e.preventDefault(); first.focus(); }
        };
        var open = function () {
            if (inFrame || read(skey) === '1') { return; }
            opener = document.activeElement;
            box.hidden = false;
            box.classList.add('is-open');
            document.documentElement.style.overflow = 'hidden';
            document.addEventListener('keydown', onKey, true);
            var go = card.querySelector('[data-jna-go]');
            (go || card).focus();
        };
        box.addEventListener('click', function (e) {
            var t = e.target;
            if (!t || !t.closest) { return; }
            var go = t.closest('[data-jna-go]');
            if (go) {
                // Le lien s'ouvre ensuite : la fenêtre est notée comme vue et ne reviendra pas.
                store(skey, '1');
                post(saveUrl, csrf, { completed: '1' });
                return;
            }
            if (t.closest('[data-jna-close]')) { close(false); }
        });
        // Laisse la page s'afficher avant d'ouvrir la fenêtre.
        window.setTimeout(open, 700);
    }

    // —— Bandeau du tableau de bord ——
    var banner = document.querySelector('[data-jnb]');
    var back = document.querySelector('[data-jnb-back]');
    if (banner && back) {
        var bcsrf = banner.getAttribute('data-csrf') || '';
        var hideUrl = banner.getAttribute('data-hide-url') || '';
        var showUrl = banner.getAttribute('data-show-url') || '';
        var swap = function (showBanner) {
            banner.hidden = !showBanner;
            back.hidden = showBanner;
            var target = showBanner ? banner.querySelector('a') : back.querySelector('button');
            if (target && target.focus) { target.focus(); }
        };
        var hideBtn = banner.querySelector('[data-jnb-hide]');
        var showBtn = back.querySelector('[data-jnb-show]');
        if (hideBtn) {
            hideBtn.hidden = false;
            hideBtn.addEventListener('click', function () {
                swap(false);
                post(hideUrl, bcsrf, { completed: '0' });
            });
        }
        if (showBtn) {
            showBtn.addEventListener('click', function () {
                swap(true);
                post(showUrl, bcsrf, {});
            });
        }
    }
})();
