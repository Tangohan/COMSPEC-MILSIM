/**
 * Comptes utilisateurs (administration du site) : fenêtres, confirmations, sélection, lignes détaillées,
 * fenêtre de bannissement. Le tri, les filtres et la pagination sont faits par le serveur : ce script
 * n'ajoute que du confort et ne contourne aucune vérification côté serveur.
 */
(function () {
    'use strict';

    function openDialog(id) {
        var d = document.getElementById(id);
        if (d && typeof d.showModal === 'function') { d.showModal(); }
        return d;
    }

    /* Fenêtres : ouvrir / fermer, clic sur le fond. */
    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-pu-open]');
        if (opener) { e.preventDefault(); openDialog(opener.getAttribute('data-pu-open')); return; }
        var closer = e.target.closest('[data-pu-close]');
        if (closer) { e.preventDefault(); var d = closer.closest('dialog'); if (d) { d.close(); } }
    });
    document.querySelectorAll('.pu-dialog').forEach(function (d) {
        d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
    });

    /* Bannissement : durée « jusqu'à une date » et rappel des sanctions déjà prises. */
    function wireBanBlock(block) {
        var date = block.querySelector('[data-ban-date]');
        var radios = block.querySelectorAll('input[name="duree"]');
        function refresh() {
            var chosen = block.querySelector('input[name="duree"]:checked');
            var needsDate = !!chosen && chosen.value === 'date';
            if (date) {
                date.required = needsDate;
                date.closest('label').classList.toggle('is-dim', !needsDate);
            }
        }
        radios.forEach(function (r) { r.addEventListener('change', refresh); });
        if (date) {
            date.addEventListener('input', function () {
                var date_radio = block.querySelector('input[name="duree"][value="date"]');
                if (date_radio && !date_radio.disabled && date.value) { date_radio.checked = true; refresh(); }
            });
        }
        refresh();
    }
    document.querySelectorAll('[data-pu-ban]').forEach(wireBanBlock);

    var banDialog = document.getElementById('pu-ban-dialog');
    if (banDialog && typeof banDialog.showModal === 'function') {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-pu-ban-open]');
            if (!btn) { return; }
            e.preventDefault();
            var form = banDialog.querySelector('form');
            form.reset();
            form.querySelector('[data-ban-email-input]').value = btn.getAttribute('data-ban-email') || '';
            banDialog.querySelectorAll('[data-ban-label]').forEach(function (n) { n.textContent = btn.getAttribute('data-ban-label') || ''; });
            banDialog.querySelectorAll('[data-ban-email]').forEach(function (n) { n.textContent = btn.getAttribute('data-ban-email') || ''; });
            var prior = parseInt(btn.getAttribute('data-ban-prior') || '0', 10) || 0;
            var wrap = banDialog.querySelector('[data-ban-prior-wrap]');
            if (wrap) {
                wrap.hidden = prior < 1;
                var count = wrap.querySelector('[data-ban-prior]');
                if (count) { count.textContent = prior > 0 ? String(prior) : ''; }
                var link = wrap.querySelector('a');
                if (link) { link.setAttribute('href', btn.getAttribute('data-ban-history') || '#'); }
            }
            var title = document.getElementById('pu-ban-dialog-title');
            if (title) { title.textContent = btn.getAttribute('data-ban-label') || 'Bannir une personne'; }
            banDialog.querySelectorAll('[data-pu-ban]').forEach(wireBanBlock);
            banDialog.showModal();
        });
    }

    /* Lignes détaillées. */
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-pu-toggle]');
        if (!toggle) { return; }
        var row = document.getElementById(toggle.getAttribute('data-pu-toggle'));
        if (!row) { return; }
        var open = row.hasAttribute('hidden');
        row.toggleAttribute('hidden', !open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.textContent = open ? 'Masquer' : 'Détails';
    });

    /* Sélection multiple : compteur et export de la sélection. */
    var picks = Array.prototype.slice.call(document.querySelectorAll('[data-pu-pick]'));
    var selectAll = document.querySelector('[data-pu-select-all]');
    var counter = document.querySelector('[data-pu-selected-count]');
    var exportBtn = document.querySelector('[data-pu-export-selection]');
    function refreshSelection() {
        var n = picks.filter(function (p) { return p.checked; }).length;
        if (counter) {
            counter.hidden = n === 0;
            counter.textContent = '· ' + n + (n > 1 ? ' sélectionnées' : ' sélectionnée');
        }
        if (exportBtn) { exportBtn.disabled = n === 0; }
        if (selectAll) {
            selectAll.checked = n > 0 && n === picks.length;
            selectAll.indeterminate = n > 0 && n < picks.length;
        }
        picks.forEach(function (p) {
            var tr = p.closest('tr');
            if (tr) { tr.classList.toggle('is-picked', p.checked); }
        });
    }
    picks.forEach(function (p) { p.addEventListener('change', refreshSelection); });
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            picks.forEach(function (p) { p.checked = selectAll.checked; });
            refreshSelection();
        });
    }
    refreshSelection();

    /* Colonnes : un clic sur « Appliquer » recharge la page avec le choix dans l'adresse. */
    var cols = document.querySelector('.pu-cols');
    if (cols) {
        cols.addEventListener('change', function () {
            var form = cols.closest('form');
            if (form && typeof form.requestSubmit === 'function') { form.requestSubmit(); }
        });
    }

    /* Confirmation des actions (fenêtre commune). */
    var dlg = document.getElementById('pu-confirm');
    if (!dlg || typeof dlg.showModal !== 'function') { return; }
    var title = document.getElementById('pu-confirm-title');
    var body = document.getElementById('pu-confirm-body');
    var kicker = document.getElementById('pu-confirm-kicker');
    var typeWrap = document.getElementById('pu-confirm-type-wrap');
    var typeInput = document.getElementById('pu-confirm-type');
    var typeExpected = document.getElementById('pu-confirm-type-expected');
    var okBtn = document.getElementById('pu-confirm-ok');
    var pending = null;

    function norm(v) { return String(v || '').trim().toLowerCase(); }
    function refresh() {
        if (!pending) { return; }
        var expected = pending.getAttribute('data-confirm-type');
        okBtn.disabled = !!expected && norm(typeInput.value) !== norm(expected);
    }
    typeInput.addEventListener('input', refresh);

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.getAttribute('data-pu-confirm') !== '1') { return; }
        if (form.getAttribute('data-pu-confirmed') === '1') { form.removeAttribute('data-pu-confirmed'); return; }
        e.preventDefault();
        pending = form;
        var danger = form.getAttribute('data-confirm-tone') === 'danger';
        kicker.textContent = danger ? 'Action définitive' : 'Confirmer';
        kicker.classList.toggle('is-danger', danger);
        title.textContent = form.getAttribute('data-confirm-title') || 'Confirmer ?';
        body.innerHTML = '';
        String(form.getAttribute('data-confirm-body') || '').split('\n').forEach(function (line) {
            if (line.trim() === '') { return; }
            var li = document.createElement('li');
            li.textContent = line;
            body.appendChild(li);
        });
        var expected = form.getAttribute('data-confirm-type');
        typeWrap.hidden = !expected;
        typeInput.value = '';
        typeExpected.textContent = expected || '';
        okBtn.textContent = form.getAttribute('data-confirm-ok') || 'Confirmer';
        okBtn.className = 'pa-btn ' + (danger ? 'pu-btn-danger' : 'pa-btn--solid');
        dlg.showModal();
        refresh();
        if (expected) { typeInput.focus(); }
    }, true);

    okBtn.addEventListener('click', function () {
        if (!pending || okBtn.disabled) { return; }
        var form = pending;
        pending = null;
        form.querySelectorAll('input[name="confirm_email"], input[name="confirm_phrase"]').forEach(function (typed) {
            typed.value = typeInput.value.trim();
        });
        dlg.close();
        form.setAttribute('data-pu-confirmed', '1');
        if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); }
    });
    dlg.addEventListener('close', function () { pending = null; });
})();
