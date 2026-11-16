/*
 * SSE › Fiches PDF : fenêtres des documents émis (explication des passages masqués, réémission à un niveau supérieur, téléchargement
 * à un niveau de diffusion) et aperçu « Ce qui sera masqué à ce niveau » de l'éditeur. Tout le texte est rendu côté serveur : ce script
 * ouvre, ferme, bascule l'affichage et, dans la fenêtre de téléchargement, poste la réémission à un niveau supérieur vers la route
 * existante (réponse JSON) avant de télécharger la nouvelle référence. Sans JavaScript, les fenêtres restent fermées et l'aperçu
 * montre le niveau choisi.
 */
(function () {
    'use strict';

    function openDialog(dlg) {
        if (!dlg) { return; }
        if (typeof dlg.showModal === 'function') {
            if (!dlg.open) { dlg.showModal(); }
        } else {
            dlg.setAttribute('open', '');
        }
        var first = dlg.querySelector('select, textarea, button, a[href]');
        if (first && typeof first.focus === 'function') { first.focus(); }
    }

    function closeDialog(dlg) {
        if (!dlg) { return; }
        if (typeof dlg.close === 'function') { dlg.close(); } else { dlg.removeAttribute('open'); }
    }

    function syncPreview(select) {
        var form = select.form;
        if (!form) { return; }
        var rank = parseInt((select.options[select.selectedIndex] || {}).getAttribute('data-rank'), 10) || 0;
        Array.prototype.forEach.call(form.querySelectorAll('[data-shx-preview] > li'), function (li) {
            var need = parseInt(li.getAttribute('data-rank'), 10);
            li.classList.toggle('is-on', need <= rank);
            li.classList.toggle('is-off', need > rank);
        });
    }

    function text(form, key) {
        var el = form.querySelector('[data-shx-t="' + key + '"]');
        return el ? el.textContent : '';
    }

    // Réémettre à ce niveau : panneau de la fenêtre de téléchargement (le formulaire de choix du niveau laisse la place).
    function openRei(dlg, level) {
        var dl = dlg.querySelector('form[data-shx-dl]');
        var rei = dlg.querySelector('form[data-shx-rei]');
        if (!dl || !rei) { return; }
        rei.querySelector('[data-shx-rei-level]').value = level;
        var name = '';
        Array.prototype.forEach.call(rei.querySelectorAll('.shx-rei__lvl'), function (ul) {
            var on = ul.getAttribute('data-level') === level;
            ul.hidden = !on;
            if (on) { name = ul.getAttribute('data-name') || ''; }
        });
        rei.querySelector('[data-shx-rei-name]').textContent = name;
        var msg = rei.querySelector('[data-shx-rei-msg]');
        msg.textContent = '';
        msg.className = 'shx-rei__msg';
        dl.hidden = true;
        rei.hidden = false;
        var why = rei.querySelector('textarea');
        if (why) { why.focus(); }
    }

    function backToLevels(dlg) {
        var dl = dlg.querySelector('form[data-shx-dl]');
        var rei = dlg.querySelector('form[data-shx-rei]');
        if (!dl || !rei || rei.hidden) { return; }
        rei.hidden = true;
        dl.hidden = false;
        var select = dl.querySelector('[data-shx-dl-level]');
        if (select) {
            for (var i = 0; i < select.options.length; i++) {
                if (select.options[i].defaultSelected) { select.selectedIndex = i; }
            }
            select.dispatchEvent(new Event('change'));
            select.focus();
        }
    }

    function startDownload(url) {
        var a = document.createElement('a');
        a.href = url;
        a.setAttribute('download', '');
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // Liste « Documents émis » mise à jour sans quitter la page (la fenêtre étant fermée) ; sur la page des fiches de vigilance,
    // dont les onglets sont liés un à un, la page est rechargée.
    function refreshIssues() {
        if (document.querySelector('[data-vig-seg], [data-vig-panel]') || !window.fetch || !window.DOMParser) {
            window.location.reload();
            return;
        }
        window.fetch(window.location.href, { credentials: 'same-origin', headers: { 'Accept': 'text/html' } })
            .then(function (r) { return r.ok ? r.text() : Promise.reject(new Error('http')); })
            .then(function (html) {
                var fresh = new DOMParser().parseFromString(html, 'text/html').querySelectorAll('[data-shx-issues]');
                var old = document.querySelectorAll('[data-shx-issues]');
                if (!fresh.length || fresh.length !== old.length) { window.location.reload(); return; }
                Array.prototype.forEach.call(old, function (node, i) {
                    var next = document.importNode(fresh[i], true);
                    node.parentNode.replaceChild(next, node);
                    init(next);
                });
            })
            .catch(function () { window.location.reload(); });
    }

    function bindRei(rei) {
        if (rei.getAttribute('data-bound')) { return; }
        rei.setAttribute('data-bound', '1');
        var dlg = rei.closest('dialog');
        var msg = rei.querySelector('[data-shx-rei-msg]');
        var go = rei.querySelector('[data-shx-rei-go]');
        var cancel = rei.querySelector('[data-shx-rei-cancel]');
        var busy = false;
        var show = function (kind, message) {
            msg.className = 'shx-rei__msg shx-rei__msg--' + kind;
            msg.textContent = message;
        };
        cancel.addEventListener('click', function () { if (!busy && cancel.hasAttribute('data-shx-rei-cancel')) { backToLevels(dlg); } });
        rei.addEventListener('submit', function (e) {
            if (!window.fetch || !window.FormData) { return; } // repli : envoi normal vers la route, retour avec un message
            e.preventDefault();
            if (busy) { return; }
            if (!rei.checkValidity()) { rei.reportValidity(); return; }
            busy = true;
            go.disabled = true;
            show('busy', text(rei, 'busy'));
            window.fetch(rei.action, {
                method: 'POST', body: new FormData(rei), credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }
            }).then(function (r) {
                return r.json().then(function (j) { return j; }, function () { return null; });
            }).then(function (j) {
                busy = false;
                if (!j) { go.disabled = false; show('err', text(rei, 'net')); return; }
                if (!j.ok) { go.disabled = false; show('err', j.message || text(rei, 'net')); return; }
                show('ok', text(rei, 'done') + ' ' + j.ref + '. ' + text(rei, 'download'));
                rei.querySelector('textarea').readOnly = true;
                go.hidden = true;
                cancel.removeAttribute('data-shx-rei-cancel');
                cancel.setAttribute('data-shx-close', '');
                cancel.textContent = text(rei, 'close');
                dlg.setAttribute('data-shx-refresh', '1');
                startDownload(j.pdf_url);
                cancel.focus();
            }).catch(function () {
                busy = false;
                go.disabled = false;
                show('err', text(rei, 'net'));
            });
        });
        // Échap : depuis le panneau, retour au choix du niveau (pas de fermeture) ; la fenêtre se referme ensuite normalement.
        dlg.addEventListener('cancel', function (e) {
            if (!rei.hidden && !dlg.hasAttribute('data-shx-refresh')) {
                e.preventDefault();
                if (!busy) { backToLevels(dlg); }
            }
        });
    }

    function bindDl(select) {
        var form = select.form;
        if (!form || select.getAttribute('data-bound')) { return; }
        select.setAttribute('data-bound', '1');
        var show = function () {
            Array.prototype.forEach.call(form.querySelectorAll('.shx-dl__lvl'), function (b) {
                b.hidden = b.getAttribute('data-level') !== select.value;
            });
            var go = form.querySelector('[data-shx-dl-go]');
            var opt = select.options[select.selectedIndex];
            if (go) { go.disabled = !!(opt && (opt.disabled || opt.getAttribute('data-state') === 'reissue')); }
        };
        select.addEventListener('change', function () {
            show();
            var opt = select.options[select.selectedIndex];
            if (opt && opt.getAttribute('data-reissue') === '1') { openRei(form.closest('dialog'), select.value); }
        });
        form.addEventListener('submit', function () {
            window.setTimeout(function () { closeDialog(form.closest('dialog')); }, 400);
        });
        Array.prototype.forEach.call(form.querySelectorAll('[data-shx-rei-open]'), function (b) {
            b.addEventListener('click', function () { openRei(form.closest('dialog'), select.value); });
        });
        show();
    }

    function init(root) {
        Array.prototype.forEach.call(root.querySelectorAll('select[data-shx-level]'), function (select) {
            if (select.getAttribute('data-bound')) { return; }
            select.setAttribute('data-bound', '1');
            syncPreview(select);
            select.addEventListener('change', function () { syncPreview(select); });
        });
        // Télécharger à un niveau : le récapitulatif du niveau choisi seul est visible ; un niveau qui demande la réémission ouvre le panneau.
        Array.prototype.forEach.call(root.querySelectorAll('select[data-shx-dl-level]'), bindDl);
        Array.prototype.forEach.call(root.querySelectorAll('form[data-shx-rei]'), bindRei);
    }

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!(t instanceof Element)) { return; }
        var open = t.closest('[data-shx-open]');
        if (open) {
            e.preventDefault();
            openDialog(document.getElementById(open.getAttribute('data-shx-open')));
            return;
        }
        var swap = t.closest('[data-shx-swap]');
        if (swap) {
            e.preventDefault();
            closeDialog(swap.closest('dialog'));
            openDialog(document.getElementById(swap.getAttribute('data-shx-swap')));
            return;
        }
        if (t.closest('[data-shx-close]')) {
            e.preventDefault();
            closeDialog(t.closest('dialog'));
            return;
        }
        if (t.tagName === 'DIALOG' && t.classList.contains('shx-dialog')) { closeDialog(t); }
    });

    // Fenêtre refermée après une réémission : la liste est rafraîchie (nouvelle ligne liée à l'ancienne).
    document.addEventListener('close', function (e) {
        var dlg = e.target;
        if (dlg instanceof Element && dlg.hasAttribute('data-shx-refresh')) {
            dlg.removeAttribute('data-shx-refresh');
            refreshIssues();
        }
    }, true);

    init(document);

    var panel = document.querySelector('[data-shx-redact]');
    var level = document.getElementById('shx-class');
    if (panel && level) {
        var show = function () {
            Array.prototype.forEach.call(panel.querySelectorAll('.shx-redact__lvl'), function (b) {
                b.hidden = b.getAttribute('data-level') !== level.value;
            });
        };
        level.addEventListener('change', show);
        show();
    }
})();
