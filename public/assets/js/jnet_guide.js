/**
 * JNET — guide des membres (views/jnet/guide.php) : filtre du sommaire et des chapitres, rubrique active du sommaire,
 * sommaire repliable sur mobile. Aucun texte affiché n'est écrit ici : les messages viennent de la page (data-jg-*),
 * donc traduits avec elle.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-jg]');
    if (!root) { return; }
    root.classList.add('is-js');

    function norm(s) {
        return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
    }
    function all(sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); }

    // —— Filtre ——
    var input = root.querySelector('[data-jg-filter]');
    var count = root.querySelector('[data-jg-count]');
    var empty = root.querySelector('[data-jg-empty]');
    var msgOne = root.querySelector('[data-jg-one]');
    var msgMany = root.querySelector('[data-jg-many]');
    var sections = all('[data-jg-sec]').map(function (sec) {
        var head = sec.querySelector('h2');
        return {
            el: sec,
            id: sec.id,
            head: norm(head ? head.textContent : ''),
            subs: all('[data-jg-sub]', sec).map(function (sub) {
                return { el: sub, text: norm(sub.textContent) };
            })
        };
    });
    var tocLinks = all('[data-jg-toc]');

    function apply() {
        var q = input ? norm(input.value) : '';
        var shown = 0;
        sections.forEach(function (s) {
            var headHit = q !== '' && s.head.indexOf(q) !== -1;
            var any = false;
            s.subs.forEach(function (sub) {
                var hit = q === '' || headHit || sub.text.indexOf(q) !== -1;
                sub.el.hidden = !hit;
                if (hit) {
                    any = true;
                    all('details', sub.el).forEach(function (d) {
                        if (q !== '') { d.open = norm(d.textContent).indexOf(q) !== -1; }
                    });
                }
            });
            if (q === '') { any = true; }
            s.el.hidden = !any;
            if (any) { shown += 1; }
            all('[data-jg-toc="' + s.id + '"]').forEach(function (a) { a.parentNode.classList.toggle('is-off', !any); });
            s.subs.forEach(function (sub) {
                if (!sub.el.id) { return; }
                all('[data-jg-toc="' + sub.el.id + '"]').forEach(function (a) { a.parentNode.classList.toggle('is-off', sub.el.hidden); });
            });
        });
        if (empty) { empty.hidden = q === '' || shown > 0; }
        if (count) {
            if (q === '') { count.textContent = ''; }
            else if (shown > 0 && msgOne && msgMany) { count.textContent = shown + ' ' + (shown > 1 ? msgMany : msgOne).textContent; }
            else { count.textContent = ''; }
        }
    }
    if (input) {
        input.addEventListener('input', apply);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && input.value !== '') { input.value = ''; apply(); }
        });
    }

    // —— Rubrique active du sommaire ——
    var targets = tocLinks.map(function (a) { return document.getElementById(a.getAttribute('data-jg-toc')); }).filter(Boolean);
    if ('IntersectionObserver' in window && targets.length) {
        var visible = {};
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { visible[en.target.id] = en.isIntersecting; });
            var first = null;
            for (var i = 0; i < targets.length; i += 1) {
                if (visible[targets[i].id]) { first = targets[i].id; break; }
            }
            if (first) {
                tocLinks.forEach(function (a) { a.classList.toggle('is-on', a.getAttribute('data-jg-toc') === first); });
            }
        }, { rootMargin: '-10% 0px -70% 0px', threshold: 0 });
        targets.forEach(function (t) { io.observe(t); });
    }

    // —— Sommaire repliable (écrans étroits) ——
    var toggle = document.getElementById('jg-toc-toggle');
    var side = document.getElementById('jg-toc');
    if (toggle && side) {
        toggle.hidden = false;
        toggle.addEventListener('click', function () {
            var open = !side.classList.contains('is-open');
            side.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        side.addEventListener('click', function (e) {
            if (e.target && e.target.closest && e.target.closest('a[href^="#"]')) {
                side.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // —— Adresse avec ancre : ouvre la question visée, s'il y en a une ——
    function openHash() {
        var id = decodeURIComponent((window.location.hash || '').slice(1));
        var el = id ? document.getElementById(id) : null;
        if (!el) { return; }
        var d = el.closest ? el.closest('details') : null;
        if (d) { d.open = true; }
    }
    window.addEventListener('hashchange', openHash);
    openHash();
})();
