/**
 * Back-office — Qualité des données (views/admin/organization/data_quality.php).
 * Sommaire qui suit le défilement, filtres des anomalies (sévérité, type, texte), liste repliée au-delà de 25 lignes.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-dq]');
    if (!root) {
        return;
    }

    /* ── Sommaire : section visible en surbrillance ── */
    var links = Array.prototype.slice.call(root.querySelectorAll('[data-dq-nav]'));
    var sections = Array.prototype.slice.call(root.querySelectorAll('[data-dq-section]'));
    function setActive(id) {
        links.forEach(function (a) {
            a.classList.toggle('is-active', a.getAttribute('data-dq-nav') === id);
        });
    }
    if ('IntersectionObserver' in window && sections.length) {
        var visible = {};
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                visible[e.target.getAttribute('data-dq-section')] = e.isIntersecting ? e.intersectionRatio : 0;
            });
            var best = null;
            sections.forEach(function (s) {
                var id = s.getAttribute('data-dq-section');
                if (best === null && visible[id] > 0) {
                    best = id;
                }
            });
            if (best) {
                setActive(best);
            }
        }, { rootMargin: '-80px 0px -55% 0px', threshold: [0, 0.01, 0.5, 1] });
        sections.forEach(function (s) { io.observe(s); });
    }
    setActive(sections.length ? sections[0].getAttribute('data-dq-section') : '');

    /* ── Filtres des anomalies ── */
    var list = root.querySelector('[data-dq-list]');
    if (!list) {
        return;
    }
    var rows = Array.prototype.slice.call(list.querySelectorAll('[data-dq-row]'));
    var sevBtns = Array.prototype.slice.call(root.querySelectorAll('[data-dq-sev]'));
    var typeSel = root.querySelector('[data-dq-type]');
    var search = root.querySelector('[data-dq-search]');
    var noResult = root.querySelector('[data-dq-noresult]');
    var more = root.querySelector('[data-dq-more]');
    var moreCount = root.querySelector('[data-dq-more-count]');
    var LIMIT = 25;
    var expanded = false;
    var state = { sev: '', type: '', q: '' };

    function norm(s) {
        return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    function apply() {
        var q = norm(state.q).trim();
        var shown = 0;
        var matched = 0;
        rows.forEach(function (r) {
            var ok = (!state.sev || r.getAttribute('data-sev') === state.sev)
                && (!state.type || r.getAttribute('data-type') === state.type)
                && (!q || norm(r.getAttribute('data-text')).indexOf(q) !== -1);
            if (ok) {
                matched++;
            }
            var show = ok && (expanded || matched <= LIMIT);
            r.hidden = !show;
            if (show) {
                shown++;
            }
        });
        if (noResult) {
            noResult.hidden = matched > 0;
        }
        list.hidden = matched === 0;
        if (more) {
            more.hidden = matched <= shown;
            if (moreCount) {
                moreCount.textContent = String(matched - shown);
            }
        }
    }

    sevBtns.forEach(function (b) {
        b.addEventListener('click', function () {
            state.sev = b.getAttribute('data-dq-sev') || '';
            sevBtns.forEach(function (x) { x.classList.toggle('is-active', x === b); });
            apply();
        });
    });
    if (typeSel) {
        typeSel.addEventListener('change', function () {
            state.type = typeSel.value;
            apply();
        });
    }
    if (search) {
        search.addEventListener('input', function () {
            state.q = search.value;
            apply();
        });
    }
    if (more) {
        more.addEventListener('click', function () {
            expanded = true;
            apply();
        });
    }

    /* Cartes « type » de l’état général : filtrent la liste puis y descendent. */
    Array.prototype.slice.call(root.querySelectorAll('[data-dq-pick-type]')).forEach(function (card) {
        card.addEventListener('click', function () {
            var code = card.getAttribute('data-dq-pick-type') || '';
            if (typeSel) {
                var has = Array.prototype.some.call(typeSel.options, function (o) { return o.value === code; });
                typeSel.value = has ? code : '';
                state.type = typeSel.value;
                apply();
            }
        });
    });

    apply();
})();
