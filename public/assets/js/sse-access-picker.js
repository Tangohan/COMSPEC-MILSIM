/**
 * Droits d'accès SSE : champ unique « ajouter un bénéficiaire ». Filtre positions, fonctions, qualifications, niveaux d'accès
 * et unités sur place ; cherche les personnes (prénom, nom, indicatif) auprès du serveur, qui applique l'annuaire.
 * Le champ envoie une référence « type:id » ; le serveur la revalide (communauté, droits, annuaire).
 */
(function () {
    'use strict';

    var FILTERS = [
        { key: 'all', label: 'Tout', kinds: null },
        { key: 'position', label: 'Positions', kinds: ['position', 'job'] },
        { key: 'qualif', label: 'Qualifications', kinds: ['qualif'] },
        { key: 'user', label: 'Personnes', kinds: ['user'] },
        { key: 'other', label: 'Autres', kinds: ['role', 'unit', 'unit_tree'] }
    ];
    var LIMIT = 40;

    function norm(text) {
        return String(text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    function init(root) {
        var js = root.querySelector('.sar-pick-js');
        var dataEl = root.querySelector('[data-sar-data]');
        if (!js || !dataEl) {
            return;
        }
        var data;
        try {
            data = JSON.parse(dataEl.textContent || '{}');
        } catch (e) {
            return;
        }
        var items = Array.isArray(data.items) ? data.items : [];
        var input = js.querySelector('[data-sar-q]');
        var value = js.querySelector('[data-sar-val]');
        var list = js.querySelector('[data-sar-list]');
        var chosen = js.querySelector('[data-sar-chosen]');
        var chosenLabel = js.querySelector('[data-sar-chosen-label]');
        var clear = js.querySelector('[data-sar-clear]');
        var filterBox = js.querySelector('[data-sar-filters]');
        var peopleUrl = root.getAttribute('data-people-url') || '';
        var people = [];
        var filter = 'all';
        var timer = 0;
        var seq = 0;
        var active = -1;

        js.hidden = false;
        var noscript = root.querySelector('noscript');
        if (noscript) {
            noscript.remove();
        }

        var kindsPresent = {};
        items.forEach(function (o) { kindsPresent[o.kind] = true; });
        if (data.people) {
            kindsPresent.user = true;
        }
        var shown = FILTERS.filter(function (f) {
            return f.kinds === null || f.kinds.some(function (k) { return kindsPresent[k]; });
        });
        if (shown.length > 2) {
            shown.forEach(function (f) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'sar-chip' + (f.key === filter ? ' is-on' : '');
                b.textContent = f.label;
                b.setAttribute('aria-pressed', f.key === filter ? 'true' : 'false');
                b.addEventListener('click', function () {
                    filter = f.key;
                    Array.prototype.forEach.call(filterBox.children, function (c) {
                        var on = c === b;
                        c.classList.toggle('is-on', on);
                        c.setAttribute('aria-pressed', on ? 'true' : 'false');
                    });
                    render();
                    input.focus();
                });
                filterBox.appendChild(b);
            });
        } else {
            filterBox.hidden = true;
        }

        function allowed(kind) {
            var def = FILTERS.filter(function (f) { return f.key === filter; })[0];
            return !def || def.kinds === null || def.kinds.indexOf(kind) !== -1;
        }

        function select(o) {
            value.value = o.ref;
            chosenLabel.textContent = o.label + ' · ' + o.group;
            chosen.hidden = false;
            input.hidden = true;
            filterBox.hidden = true;
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            value.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function reset() {
            value.value = '';
            chosen.hidden = true;
            input.hidden = false;
            filterBox.hidden = shown.length <= 2;
            input.value = '';
            render();
            input.focus();
            value.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function render() {
            var q = norm(input.value);
            var rows = items.concat(people).filter(function (o) {
                return allowed(o.kind || 'user') && (q === '' || norm(o.label + ' ' + (o.hint || '')).indexOf(q) !== -1);
            }).slice(0, LIMIT);
            list.textContent = '';
            active = -1;
            if (rows.length === 0) {
                var empty = document.createElement('li');
                empty.className = 'sar-pick-empty';
                empty.textContent = q.length >= 2 || items.length ? 'Aucun résultat.' : 'Tapez au moins deux lettres.';
                list.appendChild(empty);
            }
            rows.forEach(function (o, i) {
                var li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.id = input.id + '-o' + i;
                li.tabIndex = -1;
                var name = document.createElement('span');
                name.textContent = o.label;
                var kind = document.createElement('small');
                kind.textContent = o.group + (o.hint ? ' · ' + o.hint : '');
                li.appendChild(name);
                li.appendChild(kind);
                li.addEventListener('mousedown', function (ev) { ev.preventDefault(); select(o); });
                list.appendChild(li);
            });
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        function searchPeople() {
            window.clearTimeout(timer);
            var q = input.value.trim();
            if (!data.people || !peopleUrl || q.length < 2) {
                people = [];
                render();
                return;
            }
            timer = window.setTimeout(function () {
                var mine = ++seq;
                fetch(peopleUrl + '?q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (res) { if (!res.ok) { if (window.SseNotify) { SseNotify.failure(res); } return { items: [] }; } return res.json(); })
                    .then(function (json) {
                        if (mine !== seq) {
                            return;
                        }
                        people = (json.items || []).map(function (p) { return { ref: p.ref, label: p.label, group: p.group, hint: p.hint, kind: 'user' }; });
                        render();
                    })
                    .catch(function (err) { people = []; if (window.SseNotify) { SseNotify.failure(err); } });
            }, 250);
        }

        function move(delta) {
            var options = list.querySelectorAll('[role="option"]');
            if (options.length === 0) {
                return;
            }
            active = (active + delta + options.length) % options.length;
            Array.prototype.forEach.call(options, function (o, i) {
                o.classList.toggle('is-active', i === active);
                o.setAttribute('aria-selected', i === active ? 'true' : 'false');
            });
            input.setAttribute('aria-activedescendant', options[active].id);
            options[active].scrollIntoView({ block: 'nearest' });
        }

        input.addEventListener('focus', render);
        input.addEventListener('input', function () { render(); searchPeople(); });
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'ArrowDown') { ev.preventDefault(); move(1); }
            else if (ev.key === 'ArrowUp') { ev.preventDefault(); move(-1); }
            else if (ev.key === 'Enter') {
                var options = list.querySelectorAll('[role="option"]');
                if (active >= 0 && options[active]) {
                    ev.preventDefault();
                    options[active].dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }));
                } else if (options.length === 1) {
                    ev.preventDefault();
                    options[0].dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }));
                }
            } else if (ev.key === 'Escape') {
                list.hidden = true;
                input.setAttribute('aria-expanded', 'false');
            }
        });
        input.addEventListener('blur', function () {
            window.setTimeout(function () { list.hidden = true; input.setAttribute('aria-expanded', 'false'); }, 120);
        });
        if (clear) {
            clear.addEventListener('click', reset);
        }

        // Préremplissage (retour d'une erreur de saisie).
        if (value.value) {
            var found = items.filter(function (o) { return o.ref === value.value; })[0];
            if (found) {
                select(found);
            } else {
                value.value = '';
            }
        }
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
    }

    document.querySelectorAll('[data-sar-pick]').forEach(init);

    // Un seul envoi par formulaire : le bouton se bloque dès le premier clic (le serveur ignore de toute façon un second envoi).
    document.querySelectorAll('.sar form[method="post"]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.getAttribute('data-sar-sent') === '1') {
                event.preventDefault();
                return;
            }
            form.setAttribute('data-sar-sent', '1');
            window.setTimeout(function () {
                form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
            }, 0);
        });
    });
})();
