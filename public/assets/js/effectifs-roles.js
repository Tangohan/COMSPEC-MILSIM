/**
 * Effectifs > Accès : éditeur des niveaux d'accès.
 * - recherche et filtres sur la liste des droits (sans recharger) ;
 * - cocher un droit coche aussi ceux qu'il suppose ([data-requires]) ;
 * - compteurs par groupe ;
 * - aperçu d'impact avant enregistrement (POST roles/apercu, rien n'est écrit) ;
 * - confirmation (dialogue du portail, jamais de confirmation native du navigateur) quand un droit sensible bouge.
 * Sans JavaScript la page reste un formulaire classique.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-rl-form]');
    if (!form) {
        return;
    }
    var readOnly = form.getAttribute('data-readonly') === '1';

    function all(sel, root) {
        return Array.prototype.slice.call((root || form).querySelectorAll(sel));
    }
    function box(li) {
        return li.querySelector('input[type="checkbox"][name="permission_ids[]"]');
    }
    var rights = all('[data-rl-right]');
    var groups = all('[data-rl-group]');
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

    /** Texte français à trous « %1 » : i18n_dom.js le traduit ensuite comme les autres textes de la page. */
    function t(text, args) {
        var out = text;
        (args || []).forEach(function (a, i) { out = out.replace('%' + (i + 1), a); });
        return out;
    }

    function applyFilters() {
        var q = search ? search.value.trim().toLowerCase() : '';
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
            if (ok) {
                shown += 1;
            }
        });
        groups.forEach(function (g) {
            var items = all('[data-rl-right]', g);
            var visible = items.filter(function (li) { return !li.hidden; }).length;
            g.hidden = visible === 0;
            var on = items.filter(function (li) { var b = box(li); return b && b.checked; }).length;
            var c = g.querySelector('[data-rl-group-count]');
            if (c) {
                c.textContent = on + ' / ' + items.length;
            }
        });
        if (noMatch) {
            noMatch.hidden = shown > 0;
        }
        if (countEl) {
            var granted = rights.filter(function (li) { var b = box(li); return b && b.checked; }).length;
            countEl.textContent = t('Droits accordés : %1 sur %2', [granted, rights.length]) + (shown < rights.length ? ' · ' + t('Droits affichés : %1', [shown]) : '');
        }
    }

    ['input', 'change'].forEach(function (ev) {
        [search, onlyChecked, onlySensitive].forEach(function (el) {
            if (el) {
                el.addEventListener(ev, applyFilters);
            }
        });
    });

    if (readOnly) {
        applyFilters();
        return;
    }

    var bySlug = {};
    rights.forEach(function (li) { bySlug[li.getAttribute('data-code')] = li; });

    form.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || t.name !== 'permission_ids[]' || !t.checked) {
            return;
        }
        var li = t.closest('[data-rl-right]');
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
        })(li);
    });

    var impactEl = form.querySelector('[data-rl-impact]');
    var timer = null;
    var pendingCritical = false;

    function nameOf(slug) {
        var li = bySlug[slug];
        var s = li ? li.querySelector('strong') : null;
        return s ? s.textContent.replace(' (toujours requis)', '') : slug;
    }

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

    function list(title, rows, cls) {
        var wrap = el('div', 'rl-impact__block ' + cls);
        wrap.appendChild(el('h4', null, title));
        var ul = el('ul');
        rows.forEach(function (r) {
            var li = el('li');
            li.appendChild(el('span', null, t('%1 · personnes : %2', [r.name, r.count])));
            if (r.sensitivity === 'critical') {
                li.appendChild(el('span', 'rl-chip rl-chip--crit', 'Sensible'));
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
        var blocked = !data.ok || (data.escalation && data.escalation.length) || data.last_admin;
        if (!data.ok) {
            impactEl.appendChild(el('p', 'rl-note rl-note--warn', data.error || 'Aperçu indisponible : l’enregistrement reste possible, sans aperçu.'));
            return;
        }
        if (ch.added.length === 0 && ch.removed.length === 0) {
            impactEl.appendChild(el('p', 'rl-hint', 'Aucun droit modifié pour l’instant. Le nom et la description s’enregistrent sans effet sur les droits.'));
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
        syncConfirm(data);
    }

    function syncConfirm(data) {
        if (data && data.ok && pendingCritical) {
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
        all('input[name="permission_ids[]"]').forEach(function (b) {
            if (b.checked || b.type === 'hidden') {
                fd.append('permission_ids[]', b.value);
            }
        });
        impactEl.setAttribute('aria-busy', 'true');
        fetch(form.getAttribute('data-preview-url'), {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
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
