/*
 * Effectifs > Documents RH (views/admin/effectifs_workspace/rh_documents.php).
 * Filtres du registre (visibilité, type, recherche, regroupement), menu d’actions, volet de détail et fenêtre
 * « Ajouter au dossier ». Tous les textes viennent de la page (traduits côté serveur) : aucun libellé ici.
 */
(function () {
    'use strict';
    var root = document.querySelector('[data-edr]');
    if (!root) {
        return;
    }
    var list = root.querySelector('[data-edr-list]');
    var docs = list ? Array.prototype.slice.call(list.querySelectorAll('[data-edr-doc]')) : [];
    var copyBox = root.querySelector('[data-edr-copy]');
    var state = { stat: '', type: '', q: '', group: 'type' };

    function txt(key) {
        var el = copyBox ? copyBox.querySelector('[data-k="' + key + '"]:not([data-type])') : null;
        return el ? el.textContent.trim() : '';
    }
    function norm(s) {
        return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }
    docs.forEach(function (d, i) {
        d._i = i;
        d._q = norm(d.querySelector('.edr-doc__t').textContent + ' ' + d.querySelector('.edr-who').textContent);
    });

    /* ---------- registre ---------- */
    function matches(d) {
        if (state.type && d.dataset.type !== state.type) return false;
        if ((state.stat === 'MEMBER' || state.stat === 'STAFF') && d.dataset.vis !== state.stat) return false;
        if (state.stat === 'month' && d.dataset.month !== '1') return false;
        return !state.q || d._q.indexOf(state.q) !== -1;
    }
    function groupKey(d) {
        return state.group === 'member' ? d.dataset.member : d.dataset.type;
    }
    function header(d, count) {
        var h = document.createElement('div');
        h.className = 'edr-grp';
        if (state.group === 'member') {
            var av = d.querySelector('.edr-av').cloneNode(true);
            av.classList.add('edr-av--sm');
            h.appendChild(av);
            var b = document.createElement('b');
            b.textContent = d.querySelector('[data-edr-who-name]').textContent;
            h.appendChild(b);
            var sub = d.querySelector('[data-edr-who-sub]');
            if (sub) {
                var s = document.createElement('span');
                s.textContent = sub.textContent;
                h.appendChild(s);
            }
        } else {
            var sw = document.createElement('i');
            sw.className = 'edr-sw edr-t--' + d.dataset.type;
            sw.setAttribute('aria-hidden', 'true');
            h.appendChild(sw);
            var label = root.querySelector('[data-edr-type="' + d.dataset.type + '"] .edr-rail__l');
            var t = document.createElement('b');
            t.textContent = label ? label.textContent : d.dataset.type;
            h.appendChild(t);
        }
        var n = document.createElement('span');
        n.className = 'edr-grp__n';
        n.textContent = String(count);
        h.appendChild(n);
        return h;
    }
    function render() {
        if (!list) return;
        var shown = docs.filter(matches);
        var hidden = docs.filter(function (d) { return shown.indexOf(d) === -1; });
        if (state.group !== 'none') {
            var firstIdx = {};
            shown.forEach(function (d) {
                var k = groupKey(d);
                if (!(k in firstIdx)) firstIdx[k] = d._i;
            });
            shown.sort(function (a, b) {
                var ka = state.group === 'type' ? +a.dataset.order : firstIdx[groupKey(a)];
                var kb = state.group === 'type' ? +b.dataset.order : firstIdx[groupKey(b)];
                return ka - kb || a._i - b._i;
            });
        } else {
            shown.sort(function (a, b) { return a._i - b._i; });
        }
        list.textContent = '';
        var last = null;
        shown.forEach(function (d, i) {
            if (state.group !== 'none' && groupKey(d) !== last) {
                last = groupKey(d);
                var count = 0;
                for (var j = i; j < shown.length && groupKey(shown[j]) === last; j++) count++;
                list.appendChild(header(d, count));
            }
            d.hidden = false;
            list.appendChild(d);
        });
        hidden.forEach(function (d) {
            d.hidden = true;
            list.appendChild(d);
        });
        var none = root.querySelector('[data-edr-nomatch]');
        if (none) none.hidden = !(docs.length && !shown.length);
        root.querySelectorAll('[data-edr-stat]').forEach(function (b) {
            var on = b.dataset.edrStat === state.stat;
            b.classList.toggle('is-on', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        root.querySelectorAll('[data-edr-type]').forEach(function (b) {
            var on = b.dataset.edrType === state.type;
            b.classList.toggle('is-on', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    root.addEventListener('click', function (e) {
        var stat = e.target.closest('[data-edr-stat]');
        if (stat) {
            var v = stat.dataset.edrStat;
            state.stat = state.stat === v ? '' : v;
            if (!v) state.type = '';
            render();
            return;
        }
        var type = e.target.closest('[data-edr-type]');
        if (type) {
            state.type = type.dataset.edrType;
            render();
            return;
        }
        if (e.target.closest('[data-edr-reset]')) {
            state.stat = '';
            state.type = '';
            state.q = '';
            var qi = root.querySelector('[data-edr-q]');
            if (qi) qi.value = '';
            render();
        }
    });
    var q = root.querySelector('[data-edr-q]');
    if (q) {
        q.addEventListener('input', function () {
            state.q = norm(q.value.trim());
            render();
        });
    }
    var grp = root.querySelector('[data-edr-group]');
    if (grp) {
        grp.addEventListener('change', function () {
            state.group = grp.value;
            render();
        });
    }
    var memberFilter = root.querySelector('[data-edr-member-filter]');
    if (memberFilter) {
        memberFilter.addEventListener('change', function () {
            memberFilter.form.submit();
        });
    }

    /* ---------- menu d’actions ---------- */
    var openMenu = null;
    function closeMenu(focusBtn) {
        if (!openMenu) return;
        openMenu.menu.hidden = true;
        openMenu.btn.setAttribute('aria-expanded', 'false');
        if (focusBtn) openMenu.btn.focus();
        openMenu = null;
    }
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-edr-menu]');
        if (btn && root.contains(btn)) {
            var menu = btn.parentNode.querySelector('.edr-menu');
            var same = openMenu && openMenu.btn === btn;
            closeMenu(false);
            if (!same) {
                menu.hidden = false;
                btn.setAttribute('aria-expanded', 'true');
                openMenu = { btn: btn, menu: menu };
                var first = menu.querySelector('a,button');
                if (first) first.focus();
            }
            return;
        }
        if (openMenu && !openMenu.menu.contains(e.target)) closeMenu(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && openMenu) closeMenu(true);
    });

    /* ---------- volet de détail ---------- */
    var drawer = root.querySelector('[data-edr-drawer]');
    var drawerBody = root.querySelector('[data-edr-drawer-body]');
    var activeDoc = null;
    var returnFocus = null;
    function openDoc(doc, from) {
        if (!drawer || !drawerBody || typeof drawer.showModal !== 'function') return;
        var detail = doc.querySelector('[data-edr-detail]');
        drawerBody.innerHTML = detail.innerHTML;
        if (activeDoc) activeDoc.classList.remove('is-act');
        activeDoc = doc;
        doc.classList.add('is-act');
        returnFocus = from || doc.querySelector('[data-edr-open]');
        closeMenu(false);
        drawer.showModal();
        void drawer.offsetWidth; // départ de l’animation d’ouverture
        drawer.classList.add('is-on');
        var close = drawerBody.querySelector('[data-edr-close]');
        if (close) close.focus();
    }
    if (drawer) {
        drawer.addEventListener('close', function () {
            drawer.classList.remove('is-on');
            if (activeDoc) activeDoc.classList.remove('is-act');
            activeDoc = null;
            if (returnFocus && document.contains(returnFocus)) returnFocus.focus();
        });
        drawer.addEventListener('click', function (e) {
            if (e.target === drawer || e.target.closest('[data-edr-close]')) drawer.close();
        });
    }
    if (list) {
        list.addEventListener('click', function (e) {
            var doc = e.target.closest('[data-edr-doc]');
            if (!doc) return;
            var opener = e.target.closest('[data-edr-open]');
            if (opener) {
                openDoc(doc, doc.querySelector('.edr-doc__open'));
                return;
            }
            if (e.target.closest('a,button,.edr-menu')) return;
            openDoc(doc, doc.querySelector('.edr-doc__open'));
        });
    }

    /* ---------- fenêtre « Ajouter au dossier » ---------- */
    var dlg = root.querySelector('[data-edr-composer]');
    if (!dlg || typeof dlg.showModal !== 'function') {
        render();
        return;
    }
    var forms = { file: dlg.querySelector('[data-edr-form="file"]'), gen: dlg.querySelector('[data-edr-form="gen"]') };
    var mode = 'gen';
    var MAX = 15 * 1048576;
    var tenant = copyBox ? parseInt(copyBox.dataset.tenant || '0', 10) : 0;

    function typeCopy(type, key) {
        var el = copyBox ? copyBox.querySelector('[data-type="' + type + '"][data-k="' + key + '"]') : null;
        return el ? el.textContent.trim() : '';
    }
    function setP(key, value) {
        var el = dlg.querySelector('[data-edr-p="' + key + '"]');
        if (el) el.textContent = value;
        return el;
    }
    function memberSel(f) { return f.querySelector('[data-edr-f-member]'); }
    function memberLabel(f) {
        var s = memberSel(f);
        var o = s && s.selectedOptions.length ? s.selectedOptions[0] : null;
        return o && o.value ? o.textContent.trim() : '';
    }
    function pad(n, l) {
        n = String(n);
        while (n.length < l) n = '0' + n;
        return n;
    }
    function sizeLabel(b) {
        var mb = b > 1048576;
        var v = mb ? Math.round(b / 104857.6) / 10 : Math.max(1, Math.round(b / 1024));
        try {
            return new Intl.NumberFormat(document.documentElement.lang || undefined, {
                style: 'unit', unit: mb ? 'megabyte' : 'kilobyte', unitDisplay: 'short'
            }).format(v);
        } catch (err) {
            return String(v);
        }
    }
    function preview() {
        var f = forms[mode];
        if (!f) return;
        var member = memberLabel(f);
        var hint = f.querySelector('[data-edr-hint]');
        if (mode === 'gen') {
            var type = f.querySelector('[data-edr-f-type]').value;
            var label = typeCopy(type, 'label');
            var title = f.querySelector('[data-edr-f-title]').value.trim() || label;
            var who = member || txt('none');
            var uid = memberSel(f).value;
            var unit = uid && copyBox ? copyBox.querySelector('[data-unit="' + uid + '"]') : null;
            var pre = copyBox ? copyBox.querySelector('[data-type="' + type + '"][data-k="label"]') : null;
            var now = new Date();
            var ymd = now.getFullYear() + pad(now.getMonth() + 1, 2) + pad(now.getDate(), 2);
            setP('badge', label).className = 'edr-a4__badge edr-t--' + type;
            dlg.querySelector('[data-edr-p="obj-box"]').className = 'edr-a4__obj edr-t--' + type;
            setP('title', title);
            setP('object', typeCopy(type, 'object') + ' — ' + who);
            setP('member', who);
            setP('unit', unit ? unit.textContent.trim() : '—');
            setP('body', typeCopy(type, 'body'));
            setP('ref', 'RH-' + (pre ? pre.dataset.prefix : 'RH') + '-' + ymd + '-' + pad(tenant % 10000, 4) + '-' + (uid ? pad(parseInt(uid, 10) % 10000, 4) : '····'));
            var detail = f.querySelector('[data-edr-f-detail]').value.trim();
            setP('detail', detail);
            dlg.querySelector('[data-edr-p="detail-wrap"]').hidden = detail === '';
            if (hint) hint.textContent = member ? txt('ready') : txt('miss-member');
        } else {
            var input = f.querySelector('[data-edr-file]');
            var file = input.files && input.files[0];
            var loc = f.querySelector('[data-edr-f-loc]').value.trim();
            var ext = dlg.querySelector('[data-edr-p="ext"]');
            if (file) {
                ext.textContent = (file.name.split('.').pop() || '').toUpperCase().slice(0, 4);
                setP('fname', f.querySelector('[data-edr-f-title]').value.trim() || file.name);
                setP('fsize', file.name + ' · ' + sizeLabel(file.size));
            } else {
                setP('fname', loc || txt('no-file'));
                setP('fsize', '');
            }
            if (hint) {
                hint.textContent = !member ? txt('miss-member') : (!file && !loc ? txt('miss-file') : txt('ready'));
            }
        }
    }
    function setMode(m) {
        mode = m === 'file' ? 'file' : 'gen';
        Object.keys(forms).forEach(function (k) {
            if (forms[k]) forms[k].hidden = k !== mode;
        });
        dlg.querySelectorAll('[data-edr-mode]').forEach(function (b) {
            var on = b.dataset.edrMode === mode;
            b.classList.toggle('is-on', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        dlg.querySelectorAll('[data-edr-prev], [data-edr-prev-kind]').forEach(function (el) {
            el.hidden = (el.dataset.edrPrev || el.dataset.edrPrevKind) !== mode;
        });
        preview();
    }
    function openComposer(m, from) {
        returnFocus = from || null;
        setMode(m);
        var f = forms[mode];
        if (f && state.type) {
            var sel = f.querySelector('[data-edr-f-type]');
            if (sel && sel.querySelector('option[value="' + state.type + '"]')) sel.value = state.type;
        }
        preview();
        dlg.showModal();
        var first = f ? memberSel(f) : null;
        if (first && !first.value) first.focus();
    }
    root.addEventListener('click', function (e) {
        var n = e.target.closest('[data-edr-new]');
        if (n) openComposer(n.dataset.edrNew, n);
    });
    dlg.addEventListener('click', function (e) {
        if (e.target === dlg || e.target.closest('[data-edr-cmp-close]')) {
            dlg.close();
            return;
        }
        var m = e.target.closest('[data-edr-mode]');
        if (m) setMode(m.dataset.edrMode);
    });
    dlg.addEventListener('close', function () {
        if (returnFocus && document.contains(returnFocus)) returnFocus.focus();
    });
    ['input', 'change'].forEach(function (evt) {
        dlg.addEventListener(evt, function (e) {
            if (e.target.matches('[data-edr-f-member]')) {
                Object.keys(forms).forEach(function (k) {
                    var s = forms[k] && memberSel(forms[k]);
                    if (s && s !== e.target) s.value = e.target.value;
                });
            }
            preview();
        });
    });

    var fileInput = forms.file ? forms.file.querySelector('[data-edr-file]') : null;
    var drop = forms.file ? forms.file.querySelector('[data-edr-drop]') : null;
    if (fileInput && drop) {
        var nameEl = drop.querySelector('[data-edr-file-name]');
        fileInput.addEventListener('change', function () {
            var file = fileInput.files && fileInput.files[0];
            if (file && file.size > MAX) {
                fileInput.value = '';
                file = null;
                nameEl.textContent = txt('too-big');
            } else {
                nameEl.textContent = file ? file.name + ' · ' + sizeLabel(file.size) : txt('no-file');
            }
            drop.classList.toggle('has', !!file);
            preview();
        });
        ['dragenter', 'dragover'].forEach(function (evt) {
            drop.addEventListener(evt, function () { drop.classList.add('over'); });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            drop.addEventListener(evt, function () { drop.classList.remove('over'); });
        });
    }
    if (forms.file) {
        forms.file.addEventListener('submit', function (e) {
            var hasFile = fileInput && fileInput.files && fileInput.files.length;
            if (!hasFile && !forms.file.querySelector('[data-edr-f-loc]').value.trim()) {
                e.preventDefault();
                preview();
                forms.file.querySelector('[data-edr-f-loc]').focus();
            }
        });
    }

    render();
})();
