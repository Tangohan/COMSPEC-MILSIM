/**
 * Effectifs > Accès : filtres instantanés des cartes de niveaux et fenêtre « Fiche du niveau » (étapes, aide « ? »,
 * aperçu en direct de la carte, validation avant envoi). Les textes affichés viennent de textes cachés de la vue
 * (traduits avec la page). Sans JavaScript : les filtres s'envoient par le bouton « Filtrer » et toutes les étapes de la
 * fenêtre s'affichent l'une sous l'autre ; l'ouverture des fenêtres vient de back-office-bands.js.
 */
(function () {
    'use strict';

    function all(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }
    function norm(s) {
        return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    /* ——— Filtres des cartes ——— */
    var filters = document.querySelector('[data-rol-filters]');
    if (filters) {
        var cards = all('[data-rol-card]');
        var count = document.querySelector('[data-rol-count]');
        var none = document.querySelector('[data-rol-nomatch]');
        var q = filters.querySelector('[data-rol-f-q]');
        var tag = filters.querySelector('[data-rol-f-tag]');
        var qualif = filters.querySelector('[data-rol-f-qualif]');
        var incomplete = filters.querySelector('[data-rol-f-incomplete]');
        var archives = filters.querySelector('[data-rol-f-archives]');
        var apply = function () {
            var text = norm(q ? q.value.trim() : '');
            var t = tag ? tag.value : '0';
            var ql = qualif ? qualif.value : '';
            var shown = 0;
            cards.forEach(function (c) {
                var ok = true;
                if (c.getAttribute('data-archived') === '1' && !(archives && archives.checked)) { ok = false; }
                if (ok && t !== '0' && (' ' + c.getAttribute('data-tags') + ' ').indexOf(' ' + t + ' ') === -1) { ok = false; }
                if (ok && ql === 'avec' && c.getAttribute('data-quals') === '0') { ok = false; }
                if (ok && ql === 'sans' && c.getAttribute('data-quals') !== '0') { ok = false; }
                if (ok && incomplete && incomplete.checked && c.getAttribute('data-incomplete') !== '1') { ok = false; }
                if (ok && text && norm(c.getAttribute('data-text')).indexOf(text) === -1) { ok = false; }
                c.hidden = !ok;
                shown += ok ? 1 : 0;
            });
            if (count) {
                var word = document.querySelector(shown > 1 ? '[data-rol-count-many]' : '[data-rol-count-one]');
                count.textContent = shown + ' ' + (word ? word.textContent : '');
            }
            if (none) { none.hidden = shown > 0; }
        };
        filters.addEventListener('input', apply);
        filters.addEventListener('change', apply);
    }

    /* ——— Fenêtres « Fiche du niveau » ——— */
    all('[data-rol-dialog]').forEach(function (dlg) {
        var form = dlg.querySelector('[data-rol-form]');
        if (!form) { return; }
        var msg = function (k) { var m = form.querySelector('[data-rol-msgs] [data-k="' + k + '"]'); return m ? m.textContent.trim() : ''; };
        var panels = all('[data-rol-panel]', form);
        var stepBtns = all('[data-rol-step]', form);
        var prev = form.querySelector('[data-rol-prev]');
        var next = form.querySelector('[data-rol-next]');
        var errBox = form.querySelector('[data-rol-error]');
        var current = 1;

        dlg.classList.add('is-stepped');
        var nav = form.querySelector('[data-rol-steps]');
        if (nav) { nav.hidden = false; }

        function show(n) {
            current = Math.max(1, Math.min(panels.length, n));
            panels.forEach(function (p) {
                if (Number(p.getAttribute('data-rol-panel')) === current) { p.removeAttribute('data-rol-hidden'); } else { p.setAttribute('data-rol-hidden', ''); }
            });
            stepBtns.forEach(function (b) {
                if (Number(b.getAttribute('data-rol-step')) === current) { b.setAttribute('aria-current', 'step'); } else { b.removeAttribute('aria-current'); }
            });
            if (prev) { prev.hidden = current === 1; }
            if (next) { next.hidden = current === panels.length; }
        }
        stepBtns.forEach(function (b) {
            b.addEventListener('click', function () { show(Number(b.getAttribute('data-rol-step'))); });
        });
        if (prev) { prev.addEventListener('click', function () { show(current - 1); var f = panels[current - 1].querySelector('input:not([type=hidden]), select, textarea'); if (f) { f.focus(); } }); }
        if (next) { next.addEventListener('click', function () { show(current + 1); var f = panels[current - 1].querySelector('input:not([type=hidden]), select, textarea'); if (f) { f.focus(); } }); }
        show(1);

        /* Aide « ? » */
        all('[data-rol-help]', form).forEach(function (b) {
            b.addEventListener('click', function () {
                var box = document.getElementById(b.getAttribute('aria-controls'));
                var open = b.getAttribute('aria-expanded') !== 'true';
                b.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (box) { box.hidden = !open; }
            });
        });

        /* Compteurs de caractères */
        all('[data-rol-counter]', form).forEach(function (field) {
            var out = field.parentNode.querySelector('[data-rol-counter-out]');
            var max = Number(field.getAttribute('data-rol-counter'));
            var paint = function () { if (out) { out.textContent = field.value.length + ' / ' + max; } };
            field.addEventListener('input', paint);
            paint();
        });

        /* Abrégé en majuscules, lettres et chiffres seulement */
        var abbr = form.querySelector('[data-rol-abbr]');
        if (abbr) {
            abbr.addEventListener('input', function () {
                var caret = abbr.selectionStart;
                abbr.value = abbr.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                if (caret !== null) { abbr.setSelectionRange(Math.min(caret, abbr.value.length), Math.min(caret, abbr.value.length)); }
            });
        }

        /* Icône ou logo */
        var icons = form.querySelector('[data-rol-icons]');
        var logo = form.querySelector('[data-rol-logo]');
        var file = form.querySelector('[data-rol-logo-file]');
        var logoPreview = null;
        function visual() {
            var checked = form.querySelector('[data-rol-visual]:checked');
            var v = checked ? checked.value : 'icon';
            if (icons) { icons.hidden = v !== 'icon'; }
            if (logo) { logo.hidden = v !== 'logo'; }
            if (v === 'icon' && file) { file.value = ''; logoPreview = null; }
            return v;
        }
        all('[data-rol-visual]', form).forEach(function (r) { r.addEventListener('change', function () { visual(); preview(); }); });
        if (file) {
            file.addEventListener('change', function () {
                logoPreview = null;
                if (file.files && file.files[0] && window.URL && file.files[0].size <= Number(file.getAttribute('data-max'))) {
                    logoPreview = window.URL.createObjectURL(file.files[0]);
                }
                preview();
            });
        }
        var iconSearch = form.querySelector('[data-rol-icon-search]');
        if (iconSearch) {
            iconSearch.addEventListener('input', function () {
                var t = norm(iconSearch.value.trim());
                var shown = 0;
                all('[data-rol-icon]', form).forEach(function (o) {
                    o.hidden = !!t && norm(o.getAttribute('data-search')).indexOf(t) === -1;
                    shown += o.hidden ? 0 : 1;
                });
                var empty = form.querySelector('[data-rol-icon-empty]');
                if (empty) { empty.hidden = shown > 0; }
            });
        }

        /* Couleur */
        var colors = form.querySelector('[data-rol-colors]');
        var accent = '';
        if (colors) {
            var value = colors.querySelector('[data-rol-color-value]');
            var free = colors.querySelector('[data-rol-color-free]');
            var press = function (btn) {
                all('.rol-swatch', colors).forEach(function (s) { if (s.tagName === 'BUTTON') { s.setAttribute('aria-pressed', s === btn ? 'true' : 'false'); } });
            };
            all('button.rol-swatch', colors).forEach(function (b) {
                if (b.getAttribute('aria-pressed') === 'true') { accent = b.getAttribute('data-hex') || ''; }
                b.addEventListener('click', function () {
                    value.value = b.getAttribute('data-color') || '';
                    accent = b.getAttribute('data-hex') || '';
                    press(b);
                    preview();
                });
            });
            if (/^#[0-9a-f]{6}$/i.test(value.value)) { accent = value.value; }
            if (free) {
                free.addEventListener('input', function () {
                    value.value = free.value.toLowerCase();
                    accent = value.value;
                    press(null);
                    preview();
                });
            }
        }

        /* Étiquettes : suggestions du catalogue filtrées sur le mot en cours */
        var tags = form.querySelector('[data-rol-tags]');
        var sugg = all('[data-rol-tag-add]', form);
        function tagList() {
            return tags ? tags.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean) : [];
        }
        function paintSuggest() {
            if (!tags) { return; }
            var list = tagList().map(norm);
            var last = norm((tags.value.split(',').pop() || '').trim());
            sugg.forEach(function (b) {
                var n = norm(b.getAttribute('data-rol-tag-add'));
                b.classList.toggle('is-on', list.indexOf(n) !== -1);
                b.hidden = !!last && list.indexOf(last) === -1 && n.indexOf(last) === -1;
            });
        }
        sugg.forEach(function (b) {
            b.addEventListener('click', function () {
                var name = b.getAttribute('data-rol-tag-add');
                var raw = tags.value.split(',');
                var last = (raw.pop() || '').trim();
                var base = raw.map(function (s) { return s.trim(); }).filter(Boolean);
                // Le mot en cours de saisie est remplacé par la suggestion qu'il commence ; sinon il est gardé.
                if (last && norm(name).indexOf(norm(last)) !== 0) { base.push(last); }
                if (base.map(norm).indexOf(norm(name)) === -1) { base.push(name); }
                tags.value = base.join(', ');
                tags.dispatchEvent(new Event('input', { bubbles: true }));
                tags.focus();
            });
        });
        if (tags) { tags.addEventListener('input', paintSuggest); paintSuggest(); }

        /* Recherche dans les listes (qualifications, emplois) */
        all('[data-rol-list-search]', form).forEach(function (s) {
            var key = s.getAttribute('data-rol-list-search');
            var list = form.querySelector('[data-rol-list="' + key + '"]');
            var empty = form.querySelector('[data-rol-list-empty="' + key + '"]');
            s.addEventListener('input', function () {
                var t = norm(s.value.trim());
                var shown = 0;
                all('li', list).forEach(function (li) {
                    li.hidden = !!t && norm(li.getAttribute('data-text')).indexOf(t) === -1;
                    shown += li.hidden ? 0 : 1;
                });
                if (empty) { empty.hidden = shown > 0; }
            });
        });
        all('[data-rol-qual], [data-rol-job]', form).forEach(function (f) {
            f.addEventListener('change', function () {
                var li = f.closest('li');
                if (li) { li.classList.toggle('is-on', f.type === 'checkbox' ? f.checked : f.value !== ''); }
                preview();
            });
        });

        /* Aperçu en direct */
        var pv = {
            card: form.querySelector('[data-rol-preview-card]'),
            mark: form.querySelector('[data-rol-preview-mark]'),
            name: form.querySelector('[data-rol-preview-name]'),
            abbr: form.querySelector('[data-rol-preview-abbr]'),
            desc: form.querySelector('[data-rol-preview-desc]'),
            tags: form.querySelector('[data-rol-preview-tags]'),
            fill: form.querySelector('[data-rol-preview-meter] .rol-meter__fill'),
            txt: form.querySelector('[data-rol-preview-meter-txt]')
        };
        var nameField = form.querySelector('[data-rol-name]');
        var descField = form.querySelector('[data-rol-desc]');
        var details = form.querySelector('textarea[name="details"]');
        var existingLogo = logo ? logo.querySelector('.rol-logo__current img') : null;
        var removeLogo = form.querySelector('[data-rol-logo-remove]');
        function preview() {
            if (!pv.card) { return; }
            var nm = nameField ? nameField.value.trim() : '';
            pv.name.textContent = nm || '—';
            if (pv.abbr) { pv.abbr.textContent = abbr ? abbr.value : ''; pv.abbr.hidden = !abbr || !abbr.value; }
            var d = descField ? descField.value.trim() : '';
            pv.desc.textContent = '';
            if (d) { pv.desc.textContent = d; } else { var m = document.createElement('span'); m.className = 'rol-muted'; m.textContent = msg('nodesc'); pv.desc.appendChild(m); }
            pv.tags.textContent = '';
            tagList().slice(0, 8).forEach(function (t) { var s = document.createElement('span'); s.className = 'job-tag job-tag--slate'; s.textContent = t; pv.tags.appendChild(s); pv.tags.appendChild(document.createTextNode(' ')); });
            pv.card.style.setProperty('--rol-accent', accent || '');
            var v = form.querySelector('[data-rol-visual]:checked');
            v = v ? v.value : 'icon';
            var icon = form.querySelector('input[name="icon"]:checked');
            pv.mark.textContent = '';
            var hasVisual = false;
            var src = logoPreview || (existingLogo && !(removeLogo && removeLogo.checked) ? existingLogo.getAttribute('src') : '');
            if (v === 'logo' && src) {
                var img = document.createElement('img'); img.src = src; img.alt = ''; img.width = 44; img.height = 44; pv.mark.appendChild(img); hasVisual = true;
            } else if (v === 'icon' && icon && icon.value) {
                var svg = icon.parentNode.querySelector('svg');
                if (svg) { var c = svg.cloneNode(true); c.setAttribute('class', 'rol-mark__svg'); pv.mark.appendChild(c); hasVisual = true; }
            }
            if (!hasVisual) { var ini = document.createElement('span'); ini.className = 'rol-mark__initial'; ini.textContent = (nm || '?').charAt(0).toUpperCase(); pv.mark.appendChild(ini); }
            var quals = all('[data-rol-qual]', form).some(function (s) { return s.value !== ''; });
            var filled = [d !== '', !!(details && details.value.trim()), !!(abbr && abbr.value), hasVisual, tagList().length > 0, quals];
            var done = filled.filter(Boolean).length;
            if (pv.fill) { pv.fill.style.setProperty('--rol-p', Math.round(done * 100 / filled.length) + '%'); }
            if (pv.txt) { pv.txt.textContent = done + '/' + filled.length + ' ' + msg('fields'); }
        }
        form.addEventListener('input', preview);
        form.addEventListener('change', preview);
        visual();
        preview();

        /* Validation avant envoi : l'étape fautive s'ouvre, le champ prend le focus */
        function fail(field, text) {
            var panel = field.closest('[data-rol-panel]');
            if (panel) { show(Number(panel.getAttribute('data-rol-panel'))); }
            field.setAttribute('aria-invalid', 'true');
            if (errBox) { errBox.textContent = text; errBox.hidden = false; }
            field.focus();
            return false;
        }
        form.addEventListener('input', function (e) {
            if (e.target && e.target.getAttribute && e.target.getAttribute('aria-invalid') === 'true') { e.target.removeAttribute('aria-invalid'); }
        });
        form.addEventListener('submit', function (e) {
            if (errBox) { errBox.hidden = true; }
            var ok = true;
            var gmin = form.querySelector('[data-rol-gmin]');
            var gmax = form.querySelector('[data-rol-gmax]');
            var confirmBox = form.querySelector('[data-rol-logo-confirm]');
            if (nameField && !nameField.value.trim()) {
                ok = fail(nameField, msg('name'));
            } else if (abbr && abbr.value && !/^[A-Z0-9]{2,8}$/.test(abbr.value)) {
                ok = fail(abbr, msg('abbr'));
            } else if (gmin && gmax && gmin.value && gmax.value && Number(gmin.value) > Number(gmax.value)) {
                ok = fail(gmin, msg('grades'));
            } else if (file && file.files && file.files[0] && file.files[0].size > Number(file.getAttribute('data-max'))) {
                ok = fail(file, msg('size'));
            } else if (file && file.files && file.files[0] && confirmBox && !confirmBox.checked) {
                ok = fail(confirmBox, msg('logo'));
            }
            if (!ok) { e.preventDefault(); }
        });

        if (dlg.hasAttribute('data-rol-autoopen') && typeof dlg.showModal === 'function') {
            var open = function () { if (!dlg.open) { dlg.showModal(); var f = form.querySelector('[data-rol-name]'); if (f) { f.focus(); } } };
            if (document.readyState === 'complete') { open(); } else { window.addEventListener('load', open); }
        }
    });
})();
