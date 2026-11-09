/*
 * Catalogue de base d'Arma 3 : chargement par lots au premier accès à la page Équipement (hub et configurateur).
 * Appelle POST equipment/configurateur/catalogue/base jusqu'à « finished », un lot à la fois (EquipmentConfiguratorController
 * ::baseCatalog). Un seul chargement à la fois par onglet ; les autres onglets relancent sans risque (idempotent).
 *
 * window.AthenaEquipmentBase.run({ url, csrf, restart, onProgress(done, total), onDone(result), onError(message) })
 * Sans configurateur sur la page : [data-eq-base] (vue d'ensemble du hub) lit data-url, data-csrf, data-needed et
 * montre l'avancement dans [data-eq-base-bar] et [data-eq-base-text].
 */
(function () {
    'use strict';
    var running = null;

    function post(url, csrf, restart) {
        var body = new FormData();
        body.append('_csrf_token', csrf || '');
        if (restart) { body.append('restart', '1'); }
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false, error: 'Réponse illisible du serveur.' }; });
        }).catch(function () { return { ok: false, error: 'Serveur injoignable : vérifiez la connexion.' }; });
    }

    function run(opts) {
        if (running) { return running; }
        var restart = !!opts.restart;
        var rounds = 0;
        running = new Promise(function (resolve) {
            function next() {
                rounds++;
                post(opts.url, opts.csrf, restart && rounds === 1).then(function (j) {
                    if (!j || !j.ok) {
                        running = null;
                        if (opts.onError) { opts.onError((j && j.error) || 'Chargement interrompu.'); }
                        resolve(j || { ok: false });
                        return;
                    }
                    if (opts.onProgress) { opts.onProgress(j.done || 0, j.total || 0); }
                    if (j.finished || rounds > 120) {
                        running = null;
                        if (opts.onDone) { opts.onDone(j); }
                        resolve(j);
                        return;
                    }
                    next();
                });
            }
            next();
        });
        return running;
    }

    window.AthenaEquipmentBase = { run: run };

    // Vue d'ensemble du hub : chargement discret, avancement dans la carte « Configurateur ».
    document.addEventListener('DOMContentLoaded', function () {
        var box = document.querySelector('[data-eq-base]');
        if (!box || box.getAttribute('data-needed') !== '1' || document.querySelector('[data-cfg]')) { return; }
        var bar = box.querySelector('[data-eq-base-bar]');
        var text = box.querySelector('[data-eq-base-text]');
        box.hidden = false;
        run({
            url: box.getAttribute('data-url'),
            csrf: box.getAttribute('data-csrf'),
            onProgress: function (done, total) {
                var pct = total ? Math.round(done / total * 100) : 0;
                if (bar) { bar.style.width = pct + '%'; bar.parentNode.setAttribute('aria-valuenow', String(pct)); }
                if (text) { text.textContent = 'Catalogue de base : ' + done + ' / ' + total + ' objets'; }
            },
            onDone: function (j) {
                if (text) { text.textContent = 'Catalogue de base prêt : ' + (j.total || 0) + ' objets d’Arma 3.'; }
            },
            onError: function (msg) {
                if (text) { text.textContent = msg; }
            }
        });
    });
})();
