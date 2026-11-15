/**
 * Effectifs > Accès : page des niveaux d'accès.
 * - sections et domaines de droits repliables (aria-expanded, état mémorisé dans le navigateur) ;
 * - recherche et filtres sur les droits ; cocher un droit coche aussi ceux qu'il suppose ;
 * - aperçu d'impact avant enregistrement (POST roles/apercu, rien n'est écrit) ;
 * - copie ou déplacement de membres vers un autre niveau, avec aperçu et dialogue de confirmation du portail ;
 * - choix de la couleur du niveau.
 * Sans JavaScript tout reste déplié et les formulaires fonctionnent.
 */
(function () {
    'use strict';

    var STORE = 'rl-fold:';

    function all(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }
    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) {
            n.className = cls;
        }
        if (text != null) {
            n.textContent = text;
        }
        return n;
    }
    function t(text, args) {
        var out = text;
        (args || []).forEach(function (a, i) { out = out.replace('%' + (i + 1), a); });
        return out;
    }
    function saved(key) {
        try {
            var v = window.localStorage.getItem(STORE + key);
            return v === '1' ? true : (v === '0' ? false : null);
        } catch (e) {
            return null;
        }
    }
    function save(key, open) {
        try {
            window.localStorage.setItem(STORE + key, open ? '1' : '0');
        } catch (e) { /* stockage indisponible : l'état n'est simplement pas mémorisé */ }
    }

    /* ——— Plier / déplier ——— */
    var sections = all('[data-rl-fold]');
    var filtering = false;

    function bodyOf(sec) {
        var btn = sec.querySelector('.rl-fold');
        return btn ? document.getElementById(btn.getAttribute('aria-controls')) : null;
    }
    function paint(sec) {
        var btn = sec.querySelector('.rl-fold');
        var body = bodyOf(sec);
        var open = sec.__open;
        if (filtering && sec.hasAttribute('data-rl-group')) {
            open = all('[data-rl-right]', sec).some(function (li) { return !li.hidden; });
        }
        if (btn) {
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        if (body) {
            body.hidden = !open;
        }
        sec.classList.toggle('is-closed', !open);
    }
    function setOpen(sec, open, remember) {
        sec.__open = open;
        if (remember) {
            save(sec.getAttribute('data-rl-fold'), open);
        }
        paint(sec);
    }
    sections.forEach(function (sec) {
        var key = sec.getAttribute('data-rl-fold');
        var stored = saved(key);
        sec.__open = stored !== null ? stored : sec.getAttribute('data-default-open') !== '0';
        var btn = sec.querySelector('.rl-fold');
        if (btn) {
            btn.addEventListener('click', function () { setOpen(sec, !sec.__open, true); });
        }
        paint(sec);
    });
    all('[data-rl-fold-all]').forEach(function (b) {
        b.addEventListener('click', function () {
            var open = b.getAttribute('data-rl-fold-all') === 'open';
            sections.forEach(function (sec) { setOpen(sec, open, false); });
        });
    });
    all('[data-rl-groups]').forEach(function (b) {
        b.addEventListener('click', function () {
            var open = b.getAttribute('data-rl-groups') === 'open';
            sections.filter(function (s) { return s.hasAttribute('data-rl-group'); }).forEach(function (sec) { setOpen(sec, open, false); });
        });
    });

    /* ——— Couleur du niveau ——— */
    var colors = document.querySelector('[data-rl-colors]');
    if (colors) {
        var value = colors.querySelector('[data-rl-color-value]');
        var free = colors.querySelector('[data-rl-color-free]');
        var swatches = all('.rl-swatch[data-color]', colors);
        var press = function (v) {
            swatches.forEach(function (s) { s.setAttribute('aria-pressed', s.getAttribute('data-color') === v ? 'true' : 'false'); });
        };
        swatches.forEach(function (s) {
            s.addEventListener('click', function () {
                value.value = s.getAttribute('data-color');
                press(value.value);
            });
        });
        if (free) {
            free.addEventListener('input', function () {
                value.value = free.value;
                press('#');
            });
        }
    }

    /* ——— Membres du niveau : filtre ——— */
    var people = document.querySelector('[data-rl-people]');
    var mSearch = document.querySelector('[data-rl-m-search]');
    var mUnit = document.querySelector('[data-rl-m-unit]');
    var mAll = document.querySelector('[data-rl-m-all]');
    var transfer = document.querySelector('[data-rl-transfer]');
    function personBox(li) {
        return li.querySelector('input[type="checkbox"]');
    }
    function filterPeople() {
        if (!people) {
            return;
        }
        var q = mSearch ? mSearch.value.trim().toLowerCase() : '';
        var u = mUnit ? mUnit.value : '';
        var shown = 0;
        all('.rl-person', people).forEach(function (li) {
            var ok = (q === '' || li.getAttribute('data-text').indexOf(q) !== -1) && (u === '' || li.getAttribute('data-unit') === u);
            li.hidden = !ok;
            shown += ok ? 1 : 0;
        });
        var none = document.querySelector('[data-rl-m-nomatch]');
        if (none) {
            none.hidden = shown > 0;
        }
    }
    [mSearch, mUnit].forEach(function (c) {
        if (c) {
            c.addEventListener('input', filterPeople);
            c.addEventListener('change', filterPeople);
        }
    });
    if (mAll) {
        mAll.addEventListener('change', function () {
            all('.rl-person', people).forEach(function (li) {
                var b = personBox(li);
                if (b && !li.hidden) {
                    b.checked = mAll.checked;
                }
            });
            previewTransfer();
        });
    }

    /* ——— Copier / déplacer des membres ——— */
    var xferTimer = null;
    function selectedCount() {
        return all('.rl-person input[type="checkbox"]:checked').length;
    }
    function totalPeople() {
        return all('.rl-person').length;
    }
    function targetName() {
        var sel = transfer ? transfer.querySelector('[data-rl-target]') : null;
        return sel && sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex].text : '';
    }
    function previewTransfer() {
        if (!transfer) {
            return;
        }
        var out = transfer.querySelector('[data-rl-xfer-preview]');
        var n = selectedCount();
        window.clearTimeout(xferTimer);
        if (n === 0) {
            out.textContent = 'Cochez des membres, choisissez le niveau de destination : le nombre de personnes concernées s’affiche ici.';
            return;
        }
        xferTimer = window.setTimeout(function () {
            var fd = new FormData(transfer);
            fd.set('op', 'copy_sel');
            fetch(transfer.getAttribute('data-preview-url'), {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'X-CSRF-Token': transfer.getAttribute('data-csrf') || '', 'Accept': 'application/json' }
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (!d.ok) {
                    out.textContent = d.error || 'Aperçu indisponible.';
                    return;
                }
                var msg = t('Membres qui recevront « %1 » : %2', [d.target, d.receive]);
                if (d.already > 0) {
                    msg += ' · ' + t('l’ont déjà : %1', [d.already]);
                }
                if (d.escalation && d.escalation.length) {
                    msg += ' · ' + t('Refusé : vous ne détenez pas ces droits (%1)', [d.escalation.join(', ')]);
                }
                out.textContent = msg;
            }).catch(function () {
                out.textContent = 'Aperçu indisponible : l’attribution reste possible.';
            });
        }, 300);
    }
    if (transfer) {
        document.addEventListener('change', function (e) {
            if (e.target && (e.target.form === transfer || e.target.hasAttribute('data-rl-target')) && e.target.name !== 'op') {
                previewTransfer();
            }
        });
        all('button[data-op]', transfer).forEach(function (b) {
            b.addEventListener('click', function (e) {
                var op = b.getAttribute('data-op');
                transfer.querySelector('[data-rl-op]').value = op;
                var n = op === 'copy_all' ? totalPeople() : selectedCount();
                if (n === 0) {
                    e.preventDefault();
                    transfer.querySelector('[data-rl-xfer-preview]').textContent = 'Cochez au moins un membre à copier ou à déplacer.';
                    return;
                }
                var verb = op === 'move_sel' ? 'Les membres choisis quitteront ce niveau et recevront' : 'Les membres choisis recevront';
                transfer.setAttribute('data-ui-confirm', '1');
                transfer.setAttribute('data-ui-confirm-title', op === 'move_sel' ? 'Déplacer ces membres ?' : 'Copier ces membres ?');
                transfer.setAttribute('data-ui-confirm-body', t(verb + ' « %1 ». Membres concernés : %2. Ceux qui l’ont déjà sont ignorés.', [targetName(), n]));
            });
        });
        previewTransfer();
    }

    /* ——— Droits : filtres, dépendances, aperçu d'impact ——— */
    var form = document.querySelector('[data-rl-form]');
    if (!form) {
        return;
    }
    var readOnly = form.getAttribute('data-readonly') === '1';
    function box(li) {
        return li.querySelector('input[type="checkbox"][name="permission_ids[]"]');
    }
    var rights = all('[data-rl-right]', form);
    var groups = all('[data-rl-group]', form);
    var search = form.querySelector('[data-rl-search]');
    var onlyChecked = form.querySelector('[data-rl-only-checked]');
    var onlySensitive = form.querySelector('[data-rl-only-sensitive]');
    var countEl = form.querySelector('[data-rl-count]');
    var noMatch = form.querySelector('[data-rl-nomatch]');
    var initial = {};
    rights.forEach(function (li) {
        var b = box(li);
        initial[li.getAttribute('data-code')] = !!(b && b.checked);
    });

    function applyFilters() {
        var q = search ? search.value.trim().toLowerCase() : '';
        filtering = q !== '' || !!(onlyChecked && onlyChecked.checked) || !!(onlySensitive && onlySensitive.checked);
        var shown = 0;
        rights.forEach(function (li) {
            var b = box(li);
            var ok = true;
            if (q !== '' && (li.getAttribute('data-text') || '').indexOf(q) === -1) {
                ok = false;
            }
            if (ok && onlyChecked && onlyChecked.checked && !(b && b.checked)) {
                ok = false;
            }
            if (ok && onlySensitive && onlySensitive.checked && li.getAttribute('data-sens') !== 'critical') {
                ok = false;
            }
            li.hidden = !ok;
            shown += ok ? 1 : 0;
        });
        groups.forEach(function (g) {
            var items = all('[data-rl-right]', g);
            var on = items.filter(function (li) { var b = box(li); return b && b.checked; }).length;
            var c = g.querySelector('[data-rl-group-count]');
            if (c) {
                c.textContent = on + ' / ' + items.length;
            }
            g.hidden = filtering && items.every(function (li) { return li.hidden; });
            paint(g);
        });
        if (noMatch) {
            noMatch.hidden = shown > 0;
        }
        if (countEl) {
            var granted = rights.filter(function (li) { var b = box(li); return b && b.checked; }).length;
            countEl.textContent = t('Droits accordés : %1 sur %2', [granted, rights.length]) + (shown < rights.length ? ' · ' + t('Droits affichés : %1', [shown]) : '');
        }
    }
    [search, onlyChecked, onlySensitive].forEach(function (c) {
        if (c) {
            c.addEventListener('input', applyFilters);
            c.addEventListener('change', applyFilters);
        }
    });

    if (readOnly) {
        applyFilters();
        return;
    }

    var bySlug = {};
    rights.forEach(function (li) { bySlug[li.getAttribute('data-code')] = li; });

    form.addEventListener('change', function (e) {
        var tg = e.target;
        if (!tg || tg.name !== 'permission_ids[]' || !tg.checked) {
            return;
        }
        var seen = {};
        (function need(item) {
            (item.getAttribute('data-requires') || '').split(' ').forEach(function (slug) {
                if (!slug || seen[slug] || !bySlug[slug]) {
                    return;
                }
                seen[slug] = true;
                var b = box(bySlug[slug]);
                if (b && !b.disabled) {
                    b.checked = true;
                }
                need(bySlug[slug]);
            });
        })(tg.closest('[data-rl-right]'));
    });

    var impactEl = form.querySelector('[data-rl-impact]');
    var timer = null;
    var pendingCritical = false;

    function changes() {
        var added = [];
        var removed = [];
        rights.forEach(function (li) {
            var b = box(li);
            var slug = li.getAttribute('data-code');
            var now = !!(b && b.checked);
            if (now && !initial[slug]) {
                added.push(slug);
            }
            if (!now && initial[slug]) {
                removed.push(slug);
            }
        });
        return { added: added, removed: removed };
    }

    function list(title, rows, cls) {
        var wrap = el('div', 'rl-impact__block ' + cls);
        wrap.appendChild(el('h4', null, title));
        var ul = el('ul');
        rows.forEach(function (r) {
            var li = el('li');
            li.appendChild(el('span', null, t('%1 · personnes : %2', [r.name, r.count])));
            if (r.sensitivity === 'critical') {
                li.appendChild(el('span', 'rl-badge rl-badge--crit', 'Sensible'));
            }
            if (r.people && r.people.length) {
                var p = el('small', null, ' ');
                r.people.forEach(function (person, i) {
                    var a = el('a', null, person.name);
                    a.href = person.url;
                    p.appendChild(a);
                    if (i < r.people.length - 1) {
                        p.appendChild(document.createTextNode(', '));
                    }
                });
                if (r.count > r.people.length) {
                    p.appendChild(document.createTextNode(' …'));
                }
                li.appendChild(p);
            }
            ul.appendChild(li);
        });
        wrap.appendChild(ul);
        return wrap;
    }

    function render(data, ch) {
        if (!impactEl) {
            return;
        }
        impactEl.textContent = '';
        var save = form.querySelector('[data-rl-save]');
        if (!data.ok) {
            impactEl.appendChild(el('p', 'rl-note rl-note--warn', data.error || 'Aperçu indisponible : l’enregistrement reste possible, sans aperçu.'));
            return;
        }
        var blocked = (data.escalation && data.escalation.length) || data.last_admin;
        if (ch.added.length === 0 && ch.removed.length === 0) {
            impactEl.appendChild(el('p', 'rl-hint', 'Aucun droit modifié pour l’instant. Le nom, la description et la couleur s’enregistrent sans effet sur les droits.'));
        } else {
            impactEl.appendChild(el('p', 'rl-impact__sum', t('Droits ajoutés : %1 · droits retirés : %2 · membres concernés : %3', [ch.added.length, ch.removed.length, data.members])));
            if (data.gain.length) {
                impactEl.appendChild(list('Gagneraient', data.gain, 'rl-impact__gain'));
            }
            if (data.lose.length) {
                impactEl.appendChild(list('Perdraient', data.lose, 'rl-impact__lose'));
            }
            if (!data.gain.length && !data.lose.length) {
                impactEl.appendChild(el('p', 'rl-hint', 'Personne ne change réellement de droits : les titulaires les ont déjà par un autre niveau, ou le niveau n’a pas de titulaire.'));
            }
        }
        if (data.escalation && data.escalation.length) {
            impactEl.appendChild(el('p', 'rl-note rl-note--bad', 'Refusé : vous ne détenez pas ces droits, vous ne pouvez pas les donner (' + data.escalation.join(', ') + ').'));
        }
        if (data.last_admin) {
            impactEl.appendChild(el('p', 'rl-note rl-note--bad', 'Refusé : ce changement retirerait « Gérer les rôles » à la dernière personne qui peut gérer les accès.'));
        }
        (data.requirements || []).forEach(function (r) {
            impactEl.appendChild(el('p', 'rl-note rl-note--warn', '« ' + r.right + ' » suppose aussi : ' + r.needs.join(', ') + '.'));
        });
        pendingCritical = data.gain.concat(data.lose).some(function (r) { return r.sensitivity === 'critical'; });
        if (save) {
            save.disabled = !!blocked;
        }
        if (pendingCritical) {
            form.setAttribute('data-ui-confirm', '1');
            form.setAttribute('data-ui-confirm-body', 'Des droits sensibles changent pour des membres (données personnelles, sécurité de la communauté). Vérifiez l’aperçu avant de confirmer.');
        } else {
            form.removeAttribute('data-ui-confirm');
        }
    }

    function preview() {
        var ch = changes();
        if (!impactEl || !form.getAttribute('data-preview-url')) {
            return;
        }
        if (ch.added.length === 0 && ch.removed.length === 0) {
            render({ ok: true, members: 0, gain: [], lose: [], escalation: [], last_admin: false, requirements: [] }, ch);
            return;
        }
        var fd = new FormData();
        fd.append('_csrf_token', form.getAttribute('data-csrf') || '');
        fd.append('role_id', form.getAttribute('data-role') || '');
        all('input[name="permission_ids[]"]', form).forEach(function (b) {
            if (b.checked || b.type === 'hidden') {
                fd.append('permission_ids[]', b.value);
            }
        });
        impactEl.setAttribute('aria-busy', 'true');
        fetch(form.getAttribute('data-preview-url'), {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-CSRF-Token': form.getAttribute('data-csrf') || '', 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            impactEl.removeAttribute('aria-busy');
            render(data, ch);
        }).catch(function () {
            impactEl.removeAttribute('aria-busy');
            render({ ok: false, error: 'Aperçu indisponible : l’enregistrement reste possible, sans aperçu.' }, ch);
        });
    }

    form.addEventListener('change', function (e) {
        if (e.target && (e.target.name === 'permission_ids[]' || e.target.hasAttribute('data-perm-all') || e.target.hasAttribute('data-perm-group'))) {
            applyFilters();
            window.clearTimeout(timer);
            timer = window.setTimeout(preview, 350);
        }
    });

    applyFilters();
})();
