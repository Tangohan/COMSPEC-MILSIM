/**
 * Fiche effectif : fenêtre « Accès SSE ». Choix de l'effet à l'ouverture (donner / retirer), « toutes les sections »,
 * et aperçu en une phrase avant l'enregistrement (POST JSON, jeton CSRF du formulaire ; rien n'est écrit par l'aperçu).
 */
(function () {
    'use strict';

    var dialog = document.getElementById('eff-dlg-acces-sse');
    if (!dialog) {
        return;
    }
    var form = dialog.querySelector('[data-sse-form]');
    var effect = dialog.querySelector('[data-sse-effect]');
    var all = dialog.querySelector('[data-sse-all]');
    if (!form || !effect) {
        return;
    }
    var url = dialog.getAttribute('data-sse-preview-url') || '';
    var timer = 0;
    var seq = 0;
    var idle = effect.textContent;

    function sections() {
        return Array.prototype.slice.call(form.querySelectorAll('.eff-sse-sections input[type="checkbox"]'));
    }

    function chosen() {
        return !!(all && all.checked) || sections().some(function (box) { return box.checked; });
    }

    function show(text, tone) {
        effect.textContent = text;
        effect.classList.toggle('is-bad', tone === 'bad');
        effect.classList.toggle('is-ok', tone === 'ok');
    }

    function refresh() {
        window.clearTimeout(timer);
        if (!url || !chosen()) {
            show(idle, '');
            return;
        }
        timer = window.setTimeout(function () {
            var mine = ++seq;
            var body = new FormData(form);
            fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json().catch(function () { return null; }); })
                .then(function (data) {
                    if (mine !== seq || !data) {
                        return;
                    }
                    if (data.ok) {
                        show(data.line || '', 'ok');
                    } else {
                        var errors = (data.errors || []).concat(data.skipped || []);
                        show(errors.length ? errors.join(' ') : (data.line || idle), errors.length ? 'bad' : '');
                    }
                })
                .catch(function () { show(idle, ''); });
        }, 350);
    }

    document.querySelectorAll('[data-sse-mode]').forEach(function (button) {
        button.addEventListener('click', function () {
            var radio = form.querySelector('input[name="effect"][value="' + button.getAttribute('data-sse-mode') + '"]');
            if (radio) {
                radio.checked = true;
            }
            refresh();
        });
    });

    if (all) {
        all.addEventListener('change', function () {
            sections().forEach(function (box) {
                box.disabled = all.checked;
                if (all.checked) {
                    box.checked = false;
                }
            });
            refresh();
        });
    }

    form.addEventListener('submit', function (event) {
        if (form.getAttribute('data-sse-sent') === '1') {
            event.preventDefault();
            return;
        }
        form.setAttribute('data-sse-sent', '1');
        var button = form.querySelector('[data-sse-submit]');
        if (button) {
            window.setTimeout(function () { button.disabled = true; }, 0);
        }
    });

    form.addEventListener('change', refresh);
    form.addEventListener('input', function (event) {
        if (event.target && event.target.name === 'reason') {
            refresh();
        }
    });
})();
