/* Pilotage des formations — vue commandement des compétences : filtres de la liste des membres. Aucun texte affiché. */
(function () {
    var root = document.querySelector('[data-ccm]');
    if (!root) { return; }
    var form = root.querySelector('[data-ccm-filters]');
    var list = root.querySelector('[data-ccm-list]');
    if (!form || !list) { return; }
    var rows = Array.prototype.slice.call(list.querySelectorAll('[data-ccm-row]'));
    var shown = root.querySelector('[data-ccm-shown]');
    var none = root.querySelector('[data-ccm-none]');
    var showAll = root.querySelector('[data-ccm-showall]');
    var expanded = !showAll;
    var field = function (name) { return form.querySelector('[data-ccm-f="' + name + '"]'); };
    var val = function (name) { var f = field(name); return f ? String(f.value || '').trim() : ''; };

    function matches(row, f) {
        if (f.q && (row.getAttribute('data-search') || '').indexOf(f.q) === -1) { return false; }
        if (f.unit && row.getAttribute('data-unit') !== f.unit) { return false; }
        if (f.grade && row.getAttribute('data-grade') !== f.grade) { return false; }
        if (f.job && (row.getAttribute('data-job') || '').indexOf(' ' + f.job + ' ') === -1) { return false; }
        if (f.group && (row.getAttribute('data-group') || '').indexOf(' ' + f.group + ' ') === -1) { return false; }
        if (!f.status) { return true; }
        if (f.status === 'CRIT' && !f.module) { return parseInt(row.getAttribute('data-critical') || '0', 10) > 0; }
        var dots = row.querySelectorAll(f.module ? '[data-m="' + f.module + '"]' : '[data-m]');
        for (var i = 0; i < dots.length; i++) {
            var s = dots[i].getAttribute('data-s');
            if (f.status === 'GAP' && s !== 'COMPLETED') { return true; }
            if (f.status === 'CRIT' && (s === 'EXPIRED' || s === 'FAILED')) { return true; }
            if (f.status === s) { return true; }
        }
        return false;
    }

    function apply() {
        var f = { q: val('q').toLowerCase(), unit: val('unit'), job: val('job'), grade: val('grade'), group: val('group'), module: val('module'), status: val('status') };
        var filtering = !!(f.q || f.unit || f.job || f.grade || f.group || f.module || f.status);
        var n = 0;
        rows.forEach(function (row, i) {
            var ok = matches(row, f);
            var visible = ok && (filtering || expanded || !row.hasAttribute('data-ccm-more'));
            row.hidden = !visible;
            if (ok) { n++; }
            Array.prototype.forEach.call(row.querySelectorAll('[data-m]'), function (d) {
                d.classList.toggle('is-focus', !!f.module && d.getAttribute('data-m') === f.module);
            });
        });
        if (shown) { shown.textContent = String(n); }
        if (none) { none.hidden = n > 0; }
        if (showAll) { showAll.parentNode.hidden = expanded || filtering; }
    }

    form.addEventListener('input', apply);
    form.addEventListener('change', apply);
    form.addEventListener('reset', function () { window.setTimeout(apply, 0); });
    if (showAll) {
        showAll.addEventListener('click', function () { expanded = true; apply(); });
    }

    root.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('[data-ccm-goto]') : null;
        if (!b) { return; }
        form.reset();
        var kind = b.getAttribute('data-ccm-goto');
        var value = b.getAttribute('data-ccm-value') || '';
        var target = field(kind);
        if (target) { target.value = value; }
        if (kind === 'module') { var st = field('status'); if (st) { st.value = 'GAP'; } }
        apply();
        var section = root.querySelector('#ccm-membres');
        if (section) { section.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        if (target) { target.focus({ preventScroll: true }); }
    });

    apply();
})();
