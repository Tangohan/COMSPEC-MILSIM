/**
 * JNET : composeur de message (views/jnet/mail_compose.php).
 * Les destinataires sont les cases members[] et groups[] du formulaire (dans la fenêtre d'annuaire) : pastilles,
 * suggestions, décompte et brouillon ne font que les cocher. Aucun texte ici : tout vient de la page.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-jmc]');
    var form = root && root.querySelector('form.jmc-form');
    if (!root || !form) { return; }

    var $ = function (sel, ctx) { return (ctx || root).querySelector(sel); };
    var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); };
    var show = function (el, on) { if (el) { el.hidden = !on; } };
    var norm = function (s) {
        return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[’']/g, ' ').replace(/\s+/g, ' ').trim();
    };

    var emailLimit = parseInt(root.getAttribute('data-email-limit') || '25', 10);
    var maxRecipients = parseInt(root.getAttribute('data-max') || '250', 10);
    var toInput = $('#jmc-to');
    var suggestBox = $('#jmc-suggest');
    var pillList = $('[data-jmc-pills]');
    var subject = $('#jnet-subject');
    var body = $('#jnet-body');
    var submit = $('[data-jmc-submit]');
    var dir = $('[data-jmc-dir]');
    var confirmBox = $('[data-jmc-confirm]');
    var removeLabel = pillList.getAttribute('data-label') || '';

    // —— Destinataires possibles : une entrée par case de l'annuaire. ——
    var entries = $$('[data-jmc-opt]', dir).map(function (row) {
        var input = row.querySelector('input[type="checkbox"]');
        var strong = row.querySelector('strong');
        var small = row.querySelector('small');
        var avatar = row.querySelector('.jmc-av');
        var isGroup = input.name === 'groups[]';
        return {
            row: row,
            input: input,
            group: isGroup,
            panel: row.closest('[data-jmc-panel]').getAttribute('data-jmc-panel'),
            name: strong ? strong.textContent.trim() : '',
            sub: small ? small.textContent.trim() : '',
            avatar: avatar,
            keep: strong && strong.getAttribute('translate') === 'no' || !isGroup,
            count: parseInt(input.getAttribute('data-count') || '0', 10),
            members: isGroup
                ? (input.getAttribute('data-members') || '').split(',').filter(Boolean)
                : [input.value],
            hay: norm(row.getAttribute('data-search') || '')
        };
    });
    var byId = {};
    entries.forEach(function (e) { byId[e.input.id] = e; });

    function matches(entry, terms) {
        for (var i = 0; i < terms.length; i++) {
            if (entry.hay.indexOf(terms[i]) === -1) { return false; }
        }
        return true;
    }

    // —— Pastilles ——
    function avatarNode(entry) {
        var node = entry.avatar.cloneNode(true);
        node.className = 'jmc-pill__av';
        node.removeAttribute('loading');
        return node;
    }

    function renderPills() {
        pillList.textContent = '';
        entries.forEach(function (e) {
            if (!e.input.checked) { return; }
            var li = document.createElement('li');
            li.className = 'jmc-pill' + (e.group ? ' jmc-pill--group' : '');
            li.setAttribute('data-for', e.input.id);
            li.appendChild(avatarNode(e));
            var name = document.createElement('span');
            name.className = 'jmc-pill__name';
            if (e.keep) { name.setAttribute('translate', 'no'); }
            name.textContent = e.name;
            li.appendChild(name);
            if (e.group) {
                var count = document.createElement('span');
                count.className = 'jmc-pill__count';
                count.textContent = String(e.count);
                li.appendChild(count);
            }
            var x = document.createElement('button');
            x.type = 'button';
            x.className = 'jmc-pill__x';
            x.setAttribute('data-jmc-remove', '');
            x.setAttribute('aria-label', (removeLabel + ' ' + e.name).trim());
            x.textContent = '×';
            li.appendChild(x);
            pillList.appendChild(li);
        });
        // Beaucoup de pastilles : la ligne défile, la saisie reste visible en bas.
        var box = pillList.parentNode;
        box.scrollTop = box.scrollHeight;
    }

    // —— Décompte réel : union des groupes et des destinataires nominatifs. ——
    function reach() {
        var all = {};
        var viaGroups = {};
        var groups = 0;
        var everyone = false;
        entries.forEach(function (e) {
            if (!e.input.checked) { return; }
            if (e.group) {
                groups++;
                if (e.input.hasAttribute('data-jmc-everyone')) { everyone = true; }
            }
            e.members.forEach(function (id) {
                all[id] = true;
                if (e.group) { viaGroups[id] = true; }
            });
        });
        return { total: Object.keys(all).length, via: Object.keys(viaGroups).length, groups: groups, everyone: everyone };
    }

    function missing() {
        var r = reach();
        return {
            to: r.total === 0,
            subject: subject.value.trim() === '',
            body: body.value.trim() === '',
            max: r.total > maxRecipients
        };
    }

    function refresh() {
        var r = reach();
        show($('[data-jmc-recap-none]'), r.total === 0);
        show($('[data-jmc-recap-some]'), r.total > 0);
        $('[data-jmc-total]').textContent = String(r.total);
        show($('[data-jmc-one]'), r.total === 1);
        show($('[data-jmc-many]'), r.total !== 1);
        show($('[data-jmc-via]'), r.groups > 0 && r.total > 0);
        $('[data-jmc-via-n]').textContent = String(r.via);
        show($('[data-jmc-rule-mail]'), r.total > emailLimit && r.total <= maxRecipients);
        show($('[data-jmc-rule-max]'), r.total > maxRecipients);

        var miss = missing();
        var blocked = miss.to || miss.subject || miss.body || miss.max;
        show($('[data-jmc-missing]'), miss.to || miss.subject || miss.body);
        show($('[data-jmc-miss="to"]'), miss.to);
        show($('[data-jmc-miss="subject"]'), miss.subject);
        show($('[data-jmc-miss="body"]'), miss.body);
        submit.setAttribute('aria-disabled', blocked ? 'true' : 'false');

        var picked = entries.filter(function (e) { return e.input.checked; }).length;
        $('[data-jmc-dir-n]').textContent = String(picked);
        show($('[data-jmc-dir-one]'), picked === 1);
        show($('[data-jmc-dir-many]'), picked !== 1);

        var left = subject.maxLength - subject.value.length;
        var counter = $('[data-jmc-count]');
        show(counter, subject.maxLength > 0 && left <= 40);
        counter.textContent = subject.value.length + '/' + subject.maxLength;
        counter.classList.toggle('is-full', left <= 0);

        var prec = form.querySelector('input[name="precedence"]:checked');
        form.setAttribute('data-precedence', prec ? prec.value : '');
    }

    function changed() {
        renderPills();
        refresh();
        saveDraft();
    }

    function setChecked(entry, on) {
        if (entry.input.checked !== on) {
            entry.input.checked = on;
            dirty = true;
        }
    }

    // —— Suggestions sous la saisie ——
    var active = -1;
    var shown = [];

    function closeSuggest() {
        suggestBox.hidden = true;
        suggestBox.textContent = '';
        toInput.setAttribute('aria-expanded', 'false');
        toInput.removeAttribute('aria-activedescendant');
        shown = [];
        active = -1;
    }

    function openSuggest() {
        var terms = norm(toInput.value).split(' ').filter(Boolean);
        if (terms.length === 0) { closeSuggest(); return; }
        var found = entries.filter(function (e) { return matches(e, terms); });
        // Ce qui commence par la saisie d'abord, puis les groupes, puis les membres.
        var first = terms[0];
        found.sort(function (a, b) {
            var sa = (a.hay.indexOf(first) === 0 ? 0 : 2) + (a.input.checked ? 4 : 0) + (a.group ? 0 : 1);
            var sb = (b.hay.indexOf(first) === 0 ? 0 : 2) + (b.input.checked ? 4 : 0) + (b.group ? 0 : 1);
            return sa - sb;
        });
        shown = found.slice(0, 8);
        suggestBox.textContent = '';
        if (shown.length === 0) {
            var none = document.createElement('li');
            none.className = 'jmc-suggest__none';
            none.setAttribute('role', 'presentation');
            none.textContent = ($('[data-jmc-noresult]') || {}).textContent || '';
            suggestBox.appendChild(none);
        }
        shown.forEach(function (e, i) {
            var li = document.createElement('li');
            li.id = 'jmc-sg-' + i;
            li.setAttribute('role', 'option');
            li.setAttribute('data-i', String(i));
            if (e.input.checked) { li.className = 'is-picked'; }
            var av = e.avatar.cloneNode(true);
            av.removeAttribute('loading');
            li.appendChild(av);
            var id = document.createElement('span');
            id.className = 'jmc-opt__id';
            var strong = document.createElement('strong');
            if (e.keep) { strong.setAttribute('translate', 'no'); }
            strong.textContent = e.name;
            id.appendChild(strong);
            if (e.sub) {
                var small = document.createElement('small');
                if (!e.group) { small.setAttribute('translate', 'no'); }
                small.textContent = e.sub;
                id.appendChild(small);
            }
            li.appendChild(id);
            suggestBox.appendChild(li);
        });
        suggestBox.hidden = false;
        toInput.setAttribute('aria-expanded', 'true');
        setActive(shown.length ? 0 : -1);
    }

    function setActive(i) {
        active = i;
        $$('li[role="option"]', suggestBox).forEach(function (li, n) {
            li.setAttribute('aria-selected', n === i ? 'true' : 'false');
            if (n === i) {
                toInput.setAttribute('aria-activedescendant', li.id);
                if (li.scrollIntoView) { li.scrollIntoView({ block: 'nearest' }); }
            }
        });
    }

    function pick(i) {
        var e = shown[i];
        if (!e) { return; }
        setChecked(e, true);
        toInput.value = '';
        closeSuggest();
        changed();
        toInput.focus();
    }

    toInput.addEventListener('input', openSuggest);
    toInput.addEventListener('keydown', function (ev) {
        if (ev.key === 'ArrowDown' && !suggestBox.hidden && shown.length) {
            ev.preventDefault();
            setActive((active + 1) % shown.length);
        } else if (ev.key === 'ArrowUp' && !suggestBox.hidden && shown.length) {
            ev.preventDefault();
            setActive((active - 1 + shown.length) % shown.length);
        } else if (ev.key === 'Enter' && !(ev.ctrlKey || ev.metaKey)) {
            // Entrée ne doit jamais envoyer le message depuis la ligne « À ».
            ev.preventDefault();
            if (!suggestBox.hidden && active >= 0) { pick(active); }
        } else if (ev.key === 'Escape' && !suggestBox.hidden) {
            ev.preventDefault();
            closeSuggest();
        } else if (ev.key === 'Backspace' && toInput.value === '') {
            var last = pillList.lastElementChild;
            if (last && byId[last.getAttribute('data-for')]) {
                setChecked(byId[last.getAttribute('data-for')], false);
                changed();
            }
        }
    });
    toInput.addEventListener('blur', function () { window.setTimeout(closeSuggest, 150); });
    suggestBox.addEventListener('mousedown', function (ev) {
        var li = ev.target.closest('li[role="option"]');
        if (li) {
            ev.preventDefault();
            pick(parseInt(li.getAttribute('data-i'), 10));
        }
    });
    $('[data-jmc-to]').addEventListener('click', function (ev) {
        var x = ev.target.closest('[data-jmc-remove]');
        if (x) {
            var entry = byId[x.closest('[data-for]').getAttribute('data-for')];
            if (entry) {
                setChecked(entry, false);
                changed();
                toInput.focus();
            }
            return;
        }
        if (ev.target === ev.currentTarget || ev.target === pillList) { toInput.focus(); }
    });

    // —— Annuaire : onglets, recherche, tout sélectionner. ——
    var dirSearch = $('[data-jmc-dir-q]', dir);
    var tabs = $$('[data-jmc-tab]', dir);
    var panels = $$('[data-jmc-panel]', dir);

    function selectTab(name, focus) {
        tabs.forEach(function (tab) {
            var on = tab.getAttribute('data-jmc-tab') === name;
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
            tab.tabIndex = on ? 0 : -1;
            if (on && focus) { tab.focus(); }
        });
        panels.forEach(function (panel) { panel.hidden = panel.getAttribute('data-jmc-panel') !== name; });
    }

    function filterDir() {
        var terms = norm(dirSearch.value).split(' ').filter(Boolean);
        var counts = {};
        entries.forEach(function (e) {
            var ok = terms.length === 0 || matches(e, terms);
            e.row.hidden = !ok;
            counts[e.panel] = (counts[e.panel] || 0) + (ok ? 1 : 0);
        });
        panels.forEach(function (panel) {
            var name = panel.getAttribute('data-jmc-panel');
            show(panel.querySelector('[data-jmc-empty]'), !counts[name]);
            var tab = dir.querySelector('[data-jmc-tab="' + name + '"] [data-jmc-tab-n]');
            if (tab) { tab.textContent = String(counts[name] || 0); }
        });
        // La recherche ne trouve rien dans l'onglet ouvert : on montre le premier qui a des résultats.
        var current = dir.querySelector('[data-jmc-tab][aria-selected="true"]');
        if (terms.length && current && !counts[current.getAttribute('data-jmc-tab')]) {
            for (var i = 0; i < tabs.length; i++) {
                if (counts[tabs[i].getAttribute('data-jmc-tab')]) {
                    selectTab(tabs[i].getAttribute('data-jmc-tab'));
                    break;
                }
            }
        }
    }

    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () { selectTab(tab.getAttribute('data-jmc-tab')); });
        tab.addEventListener('keydown', function (ev) {
            if (ev.key === 'ArrowRight' || ev.key === 'ArrowLeft') {
                ev.preventDefault();
                var next = tabs[(i + (ev.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
                selectTab(next.getAttribute('data-jmc-tab'), true);
            }
        });
    });
    dirSearch.addEventListener('input', filterDir);
    dirSearch.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); } });
    dir.addEventListener('change', function (ev) {
        if (ev.target && ev.target.type === 'checkbox') {
            dirty = true;
            changed();
        }
    });
    dir.addEventListener('click', function (ev) {
        var bulk = ev.target.closest('[data-jmc-all], [data-jmc-clear]');
        if (bulk) {
            var name = bulk.closest('[data-jmc-panel]').getAttribute('data-jmc-panel');
            var on = bulk.hasAttribute('data-jmc-all');
            entries.forEach(function (e) {
                if (e.panel === name && !e.row.hidden) { setChecked(e, on); }
            });
            changed();
            return;
        }
        if (ev.target.closest('[data-jmc-dir-close]') || ev.target === dir) { dir.close(); }
    });
    dir.addEventListener('close', function () {
        dirSearch.value = '';
        filterDir();
        toInput.focus();
    });
    $('[data-jmc-dir-open]').addEventListener('click', function () {
        if (typeof dir.showModal !== 'function') { return; }
        dir.showModal();
        dirSearch.focus();
    });

    // —— Brouillon local, par destinataire d'arrivée. ——
    var draftKey = 'jnetMailDraft:' + (root.getAttribute('data-user') || '0') + ':' + (root.getAttribute('data-context') || 'nouveau');
    // Message en cours d'envoi : rendu si la page revient sur un refus sans la saisie (session expirée).
    var sentKey = 'jnetMailDraft:' + (root.getAttribute('data-user') || '0') + ':envoi';
    var dirty = false;
    var sending = false;
    var initial = snapshot();

    function snapshot() {
        var prec = form.querySelector('input[name="precedence"]:checked');
        return {
            subject: subject.value,
            body: body.value,
            precedence: prec ? prec.value : '',
            picked: entries.filter(function (e) { return e.input.checked; }).map(function (e) { return e.input.id; })
        };
    }

    function apply(state) {
        subject.value = state.subject || '';
        body.value = state.body || '';
        $$('input[name="precedence"]', form).forEach(function (radio) { radio.checked = radio.value === state.precedence; });
        var picked = {};
        (state.picked || []).forEach(function (id) { picked[id] = true; });
        entries.forEach(function (e) { e.input.checked = !!picked[e.input.id]; });
    }

    function saveDraft() {
        if (!dirty || sending) { return; }
        try {
            var state = snapshot();
            state.at = Date.now();
            window.localStorage.setItem(draftKey, JSON.stringify(state));
        } catch (e) { /* stockage indisponible : le brouillon n'est pas gardé */ }
    }

    function dropDraft() {
        try { window.localStorage.removeItem(draftKey); } catch (e) { /* rien à retirer */ }
    }

    function store(key, write) {
        try {
            var kept = window.localStorage.getItem(key);
            if (write === null) { window.localStorage.removeItem(key); } else if (write) { window.localStorage.setItem(key, write); }
            return kept;
        } catch (e) { return null; }
    }

    function restoreDraft() {
        // Une saisie rendue par le serveur (envoi refusé) passe avant le brouillon local.
        if (root.getAttribute('data-server-draft') === '1') { return; }
        var state = null;
        var sent = store(sentKey, null);
        try {
            state = JSON.parse((document.querySelector('.jnet-flash--err') && sent) || window.localStorage.getItem(draftKey) || 'null');
        } catch (e) { state = null; }
        if (!state || typeof state !== 'object') { return; }
        // Un brouillon de plus de sept jours n'est plus proposé.
        if (!state.at || Date.now() - state.at > 7 * 24 * 3600 * 1000) { dropDraft(); return; }
        if (String(state.subject || '').trim() === '' && String(state.body || '').trim() === '') { return; }
        // Le destinataire d'arrivée reste posé ; ceux du brouillon s'y ajoutent s'ils sont encore dans l'annuaire.
        state.picked = (Array.isArray(state.picked) ? state.picked : []).concat(initial.picked).filter(function (id) { return !!byId[id]; });
        if (!form.querySelector('input[name="precedence"][value="' + String(state.precedence).replace(/[^a-z_-]/gi, '') + '"]')) {
            state.precedence = initial.precedence;
        }
        apply(state);
        dirty = true;
        var when = $('[data-jmc-draft-when]');
        if (when && state.at) {
            try {
                when.textContent = new Date(state.at).toLocaleString(document.documentElement.lang || 'fr', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' });
            } catch (e) { when.textContent = ''; }
        }
        show($('[data-jmc-draft]'), true);
    }

    $('[data-jmc-draft-clear]').addEventListener('click', function () {
        dropDraft();
        apply(initial);
        dirty = false;
        show($('[data-jmc-draft]'), false);
        renderPills();
        refresh();
        (initial.picked.length ? subject : toInput).focus();
    });

    [subject, body].forEach(function (field) {
        field.addEventListener('input', function () {
            dirty = true;
            field.classList.remove('jmc-field-error');
            refresh();
            saveDraft();
        });
    });
    $$('input[name="precedence"]', form).forEach(function (radio) {
        radio.addEventListener('change', function () {
            dirty = true;
            refresh();
            saveDraft();
        });
    });

    window.addEventListener('beforeunload', function (ev) {
        if (sending || !dirty) { return; }
        if (subject.value.trim() === '' && body.value.trim() === '') { return; }
        ev.preventDefault();
        ev.returnValue = '';
    });

    // —— Envoi : champs manquants montrés, confirmation pour Flash et pour toute l'unité. ——
    function send() {
        var state = snapshot();
        state.at = Date.now();
        store(sentKey, JSON.stringify(state));
        sending = true;
        dropDraft();
        form.submit();
    }

    function askConfirm(kind, r) {
        confirmBox.setAttribute('data-kind', kind);
        $$('[data-jmc-confirm-title]', confirmBox).forEach(function (el) { el.hidden = el.getAttribute('data-jmc-confirm-title') !== kind; });
        $$('[data-jmc-confirm-why]', confirmBox).forEach(function (el) { el.hidden = el.getAttribute('data-jmc-confirm-why') !== kind; });
        $('[data-jmc-confirm-n]', confirmBox).textContent = String(r.total);
        show($('[data-jmc-confirm-one]', confirmBox), r.total === 1);
        show($('[data-jmc-confirm-many]', confirmBox), r.total !== 1);
        show($('[data-jmc-confirm-mail]', confirmBox), r.total > emailLimit);
        confirmBox.showModal();
        $('[data-jmc-confirm-back]', confirmBox).focus();
    }

    function trySend() {
        var miss = missing();
        $('[data-jmc-recap]').classList.toggle('is-nudged', miss.to || miss.subject || miss.body || miss.max);
        if (miss.to || miss.max) { toInput.focus(); return; }
        if (miss.subject) { subject.classList.add('jmc-field-error'); subject.focus(); return; }
        if (miss.body) { body.classList.add('jmc-field-error'); body.focus(); return; }
        var r = reach();
        var prec = form.querySelector('input[name="precedence"]:checked');
        var kind = prec && prec.value === 'flash' ? 'flash' : (r.everyone ? 'everyone' : '');
        if (kind !== '' && confirmBox && typeof confirmBox.showModal === 'function') {
            askConfirm(kind, r);
            return;
        }
        send();
    }

    form.setAttribute('novalidate', '');
    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        trySend();
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey) && !dir.open && !(confirmBox && confirmBox.open)) {
            ev.preventDefault();
            trySend();
        }
    });
    if (confirmBox) {
        $('[data-jmc-confirm-back]', confirmBox).addEventListener('click', function () { confirmBox.close(); });
        $('[data-jmc-confirm-go]', confirmBox).addEventListener('click', function () {
            confirmBox.close();
            send();
        });
        confirmBox.addEventListener('click', function (ev) { if (ev.target === confirmBox) { confirmBox.close(); } });
    }

    restoreDraft();
    renderPills();
    refresh();
    if (root.getAttribute('data-focus') === 'body' || (root.getAttribute('data-focus') === 'subject' && subject.value.trim() !== '')) {
        body.focus();
        body.setSelectionRange(body.value.length, body.value.length);
    }
})();
