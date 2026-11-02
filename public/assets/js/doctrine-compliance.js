/*
 * Suivi de diffusion (views/admin/documents/doctrine_compliance.php) : filtres par statut, recherche,
 * vue « Par document » / « Par personne », repli des documents et fenêtre de relance.
 * Le HTML est complet sans ce script (tout déplié, vue par document).
 */
(function () {
    'use strict';
    var root = document.querySelector('[data-dfs]');
    if (!root) return;

    var tools = root.querySelector('[data-dfs-tools]');
    if (tools) tools.hidden = false;

    var state = { view: 'doc', status: '', q: '' };
    var docs = Array.prototype.slice.call(root.querySelectorAll('[data-dfs-doc]'));
    var prows = Array.prototype.slice.call(root.querySelectorAll('[data-dfs-prow]'));
    var openId = root.getAttribute('data-dfs-open') || '0';
    var initialStatus = root.getAttribute('data-dfs-status') || '';
    if (initialStatus && root.querySelector('[data-dfs-filter="' + initialStatus + '"]')) state.status = initialStatus;

    function setOpen(doc, open) {
        doc.classList.toggle('is-open', open);
        var b = doc.querySelector('[data-dfs-toggle]');
        if (b) b.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    // Repliés par défaut, sauf le document demandé (retour de relance) ou un document unique.
    docs.forEach(function (doc) {
        doc.classList.add('is-collapsible');
        setOpen(doc, docs.length === 1 || doc.id === 'dfs-doc-' + openId);
    });

    function apply() {
        var q = state.q.trim().toLowerCase();
        var filtering = q !== '' || state.status !== '';
        var anyDoc = false;
        docs.forEach(function (doc) {
            var docHit = q === '' || (doc.getAttribute('data-dfs-q') || '').indexOf(q) !== -1;
            var rows = doc.querySelectorAll('[data-dfs-row]');
            var visible = 0;
            Array.prototype.forEach.call(rows, function (tr) {
                var okStatus = state.status === '' || tr.getAttribute('data-status') === state.status;
                var okQ = docHit || (tr.getAttribute('data-dfs-q') || '').indexOf(q) !== -1;
                var show = okStatus && okQ;
                tr.hidden = !show;
                if (show) visible++;
            });
            var showDoc = filtering ? visible > 0 : true;
            doc.hidden = !showDoc;
            if (showDoc) anyDoc = true;
            if (filtering && showDoc) setOpen(doc, true);
        });
        var none = root.querySelector('[data-dfs-none]');
        if (none) none.hidden = anyDoc;

        var anyP = false;
        prows.forEach(function (tr) {
            var okStatus = state.status === '' || (' ' + tr.getAttribute('data-statuses') + ' ').indexOf(' ' + state.status + ' ') !== -1;
            var okQ = q === '' || (tr.getAttribute('data-dfs-q') || '').indexOf(q) !== -1;
            tr.hidden = !(okStatus && okQ);
            if (!tr.hidden) anyP = true;
            Array.prototype.forEach.call(tr.querySelectorAll('td[data-status]'), function (td) {
                td.classList.toggle('is-dim', state.status !== '' && td.getAttribute('data-status') !== state.status);
            });
        });
        var pnone = root.querySelector('[data-dfs-pnone]');
        if (pnone) pnone.hidden = anyP || prows.length === 0;

        Array.prototype.forEach.call(root.querySelectorAll('[data-dfs-filter]'), function (b) {
            var on = b.getAttribute('data-dfs-filter') === state.status;
            b.classList.toggle('is-on', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        Array.prototype.forEach.call(root.querySelectorAll('[data-dfs-view]'), function (b) {
            var on = b.getAttribute('data-dfs-view') === state.view;
            b.classList.toggle('is-on', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        Array.prototype.forEach.call(root.querySelectorAll('[data-dfs-pane]'), function (p) {
            p.hidden = p.getAttribute('data-dfs-pane') !== state.view;
        });
    }

    var dialog = root.querySelector('[data-dfs-dialog]');
    function openRemind(btn) {
        if (!dialog) return;
        dialog.querySelector('[data-dfs-dialog-doc]').value = btn.getAttribute('data-dfs-remind') || '';
        dialog.querySelector('[data-dfs-dialog-ref]').textContent = btn.getAttribute('data-dfs-ref') || '';
        dialog.querySelector('[data-dfs-dialog-count]').textContent = btn.getAttribute('data-dfs-count') || '';
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
    }

    root.addEventListener('click', function (e) {
        var t = e.target.closest('button, a, summary, [data-dfs-zone]');
        if (!t) return;
        if (t.hasAttribute('data-dfs-filter')) {
            var f = t.getAttribute('data-dfs-filter');
            state.status = state.status === f ? '' : f;
            apply();
            return;
        }
        if (t.hasAttribute('data-dfs-view')) {
            state.view = t.getAttribute('data-dfs-view');
            apply();
            return;
        }
        if (t.hasAttribute('data-dfs-remind')) {
            var menu = t.closest('details');
            if (menu) menu.open = false;
            openRemind(t);
            return;
        }
        if (t.hasAttribute('data-dfs-close')) {
            if (dialog) dialog.close();
            return;
        }
        if (t.hasAttribute('data-dfs-zone') && e.target.closest('.dfs-menu')) return;
        if (t.hasAttribute('data-dfs-toggle') || t.hasAttribute('data-dfs-zone')) {
            var doc = t.closest('[data-dfs-doc]');
            if (doc) setOpen(doc, !doc.classList.contains('is-open'));
        }
    });

    // Un seul menu d'actions ouvert ; clic ailleurs : fermé.
    document.addEventListener('click', function (e) {
        Array.prototype.forEach.call(root.querySelectorAll('.dfs-menu[open]'), function (d) {
            if (!d.contains(e.target)) d.open = false;
        });
    });
    if (dialog) {
        dialog.addEventListener('click', function (e) {
            if (e.target === dialog) dialog.close();
        });
    }

    var input = root.querySelector('input[data-dfs-q]');
    if (input) {
        input.addEventListener('input', function () {
            state.q = input.value;
            apply();
        });
    }

    apply();
    if (openId !== '0') {
        var target = document.getElementById('dfs-doc-' + openId);
        if (target && target.scrollIntoView) target.scrollIntoView({ block: 'start' });
    }
})();
