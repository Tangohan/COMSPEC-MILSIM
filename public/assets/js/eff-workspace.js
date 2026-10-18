/*
 * Listes Effectifs (« À traiter », tableur) : puces de filtre + recherche instantanée.
 * Conteneur [data-ws] ; puces [data-ws-chip="tag"] ; champ [data-ws-search] ;
 * éléments [data-ws-item] avec data-ws-tags="a b" et data-ws-text ; [data-ws-empty] si rien ne correspond.
 * Le hash de l'adresse (#incomplete…) présélectionne la puce correspondante.
 * form[data-autosubmit] : les listes déroulantes appliquent le filtre dès qu'elles changent.
 */
(function () {
    'use strict';
    if (window.__effWorkspaceInit) {
        return;
    }
    window.__effWorkspaceInit = true;

    function normalize(text) {
        return String(text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    function initScope(scope) {
        var chips = scope.querySelectorAll('[data-ws-chip]');
        var search = scope.querySelector('[data-ws-search]');
        var items = scope.querySelectorAll('[data-ws-item]');
        var empty = scope.querySelector('[data-ws-empty]');
        var tag = '';

        function apply() {
            var q = search ? normalize(search.value.trim()) : '';
            var shown = 0;
            items.forEach(function (item) {
                var tags = (item.getAttribute('data-ws-tags') || '').split(' ');
                var ok = (tag === '' || tags.indexOf(tag) !== -1)
                    && (q === '' || normalize(item.getAttribute('data-ws-text')).indexOf(q) !== -1);
                item.hidden = !ok;
                if (ok) {
                    shown++;
                }
            });
            if (empty) {
                empty.hidden = shown > 0 || items.length === 0;
            }
        }

        function select(value) {
            tag = value;
            chips.forEach(function (chip) {
                var on = chip.getAttribute('data-ws-chip') === value;
                chip.classList.toggle('is-active', on);
                chip.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            apply();
        }

        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                var value = chip.getAttribute('data-ws-chip');
                select(value === tag ? '' : value);
            });
        });
        if (search) {
            search.addEventListener('input', apply);
        }
        var hash = window.location.hash.replace('#', '');
        if (hash && scope.querySelector('[data-ws-chip="' + hash.replace(/[^a-z0-9_-]/gi, '') + '"]')) {
            select(hash);
        }
    }

    // Filtres serveur : un changement de liste déroulante applique le filtre sans clic supplémentaire.
    function initAutosubmit() {
        document.querySelectorAll('form[data-autosubmit]').forEach(function (form) {
            form.querySelectorAll('select').forEach(function (select) {
                select.addEventListener('change', function () {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                });
            });
        });
    }

    function init() {
        document.querySelectorAll('[data-ws]').forEach(initScope);
        initAutosubmit();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
