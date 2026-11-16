/*
 * SSE › Fiches PDF : fenêtres des documents émis (explication des passages masqués, réémission à un niveau supérieur) et aperçu
 * « Ce qui sera masqué à ce niveau » de l'éditeur. Tout le texte est rendu côté serveur : ce script ne fait qu'ouvrir, fermer et
 * basculer l'affichage. Sans JavaScript, les fenêtres restent fermées et l'aperçu montre le niveau choisi.
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

    Array.prototype.forEach.call(document.querySelectorAll('select[data-shx-level]'), function (select) {
        syncPreview(select);
        select.addEventListener('change', function () { syncPreview(select); });
    });

    // Télécharger à un niveau : le récapitulatif du niveau choisi seul est visible ; un niveau qui exige la réémission ne se télécharge pas.
    Array.prototype.forEach.call(document.querySelectorAll('select[data-shx-dl-level]'), function (select) {
        var form = select.form;
        if (!form) { return; }
        var show = function () {
            Array.prototype.forEach.call(form.querySelectorAll('.shx-dl__lvl'), function (b) {
                b.hidden = b.getAttribute('data-level') !== select.value;
            });
            var go = form.querySelector('[data-shx-dl-go]');
            var opt = select.options[select.selectedIndex];
            if (go) { go.disabled = !!(opt && opt.disabled); }
        };
        select.addEventListener('change', show);
        form.addEventListener('submit', function () {
            window.setTimeout(function () { closeDialog(form.closest('dialog')); }, 400);
        });
        show();
    });

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
