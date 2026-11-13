/*
 * Fiche membre › Identité & papiers, Mes papiers, bureau SSE : choix d'une image par dépôt ou clic (photo d'identité,
 * photo de la couverture) avec aperçu, et confirmation des actions qui remplacent quelque chose (data-idm-confirm).
 */
(function () {
    'use strict';
    if (window.__memberPapers) return;
    window.__memberPapers = true;

    function bindDrop(zone) {
        if (zone.dataset.dropReady) return;
        zone.dataset.dropReady = '1';
        var input = zone.querySelector('[data-drop-input]');
        var name = zone.querySelector('[data-drop-name]');
        if (!input) return;
        var form = zone.closest('form');
        var frame = form ? (form.closest('.idm-photo') || form).querySelector('[data-photo-frame]') : null;
        var initial = frame ? frame.innerHTML : '';
        var show = function () {
            var file = input.files && input.files[0];
            zone.classList.toggle('has-file', !!file);
            if (name) name.textContent = file ? file.name + ' · ' + Math.max(1, Math.round(file.size / 1024)) + ' Ko' : name.getAttribute('data-empty') || name.textContent;
            if (!frame) return;
            if (!file || !/^image\//.test(file.type)) {
                frame.innerHTML = initial;
                return;
            }
            var img = document.createElement('img');
            img.alt = '';
            img.src = URL.createObjectURL(file);
            frame.innerHTML = '';
            frame.appendChild(img);
            var submit = form.querySelector('[data-drop-submit]');
            if (submit) {
                (form.closest('.idm-photo') || form).scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                submit.focus({ preventScroll: true });
            }
        };
        if (name && !name.hasAttribute('data-empty')) name.setAttribute('data-empty', name.textContent);
        input.addEventListener('change', show);
        ['dragenter', 'dragover'].forEach(function (type) {
            zone.addEventListener(type, function (e) {
                e.preventDefault();
                zone.classList.add('is-over');
            });
        });
        ['dragleave', 'drop'].forEach(function (type) {
            zone.addEventListener(type, function () {
                zone.classList.remove('is-over');
            });
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            if (!e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files.length) return;
            try {
                input.files = e.dataTransfer.files;
            } catch (err) {
                return;
            }
            show();
        });
    }

    function init() {
        document.querySelectorAll('[data-drop]').forEach(bindDrop);
        document.querySelectorAll('form[data-idm-confirm]').forEach(function (form) {
            if (form.dataset.confirmReady) return;
            form.dataset.confirmReady = '1';
            form.addEventListener('submit', function (e) {
                if (!window.confirm(form.getAttribute('data-idm-confirm'))) e.preventDefault();
            });
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
