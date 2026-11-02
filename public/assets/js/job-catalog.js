/*
 * Emplois & fonctions : fenêtre « Chercher dans le catalogue ».
 * Recherche dans le catalogue d'emplois de référence (fonctions/catalogue, JSON dans la langue du lecteur), fiche
 * détaillée, puis copie de tout ou d'un champ dans la fiche d'emploi ouverte (confirmation avant de remplacer un
 * champ déjà rempli). Rien n'est enregistré : l'utilisateur relit et valide le formulaire lui-même.
 */
(() => {
    'use strict';
    if (window.jobCatalogLoaded) return;
    window.jobCatalogLoaded = true;

    let data = null;
    let loading = null;
    let current = null;
    let opener = null;
    let timer = 0;
    const LIMITS = {name: 120, abbreviation: 8, description: 500, tags: 400, code: 40};
    const normalize = value => String(value || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLocaleLowerCase().replace(/[^\p{L}\p{N}]+/gu, ' ');
    const dialog = () => document.querySelector('[data-job-catalog]');
    const form = () => document.querySelector('form[data-job-catalog-form]');
    const isEnglish = () => (document.documentElement.lang || 'fr').slice(0, 2) === 'en';

    function announce(message) {
        const live = dialog()?.querySelector('[data-job-catalog-live]');
        if (live) { live.textContent = ''; window.setTimeout(() => { live.textContent = message; }, 30); }
    }

    function haystack(entry) {
        return ' ' + normalize([entry.name, entry.official_name, entry.abbreviation, entry.code, entry.unit, entry.command,
            entry.branch_label, entry.type_label, (entry.tags || []).join(' '), entry.id].join(' ')) + ' ' + String(entry.code || '').toLowerCase().replace(/\s+/g, '') + ' ';
    }

    async function load() {
        if (data) return data;
        if (loading) return loading;
        const box = dialog();
        loading = fetch(box.dataset.catalogUrl, {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
            .then(response => {
                if (!response.ok || !(response.headers.get('Content-Type') || '').includes('application/json')) throw new Error('catalogue');
                return response.json();
            })
            .then(json => {
                json.entries.forEach(entry => { entry._hay = haystack(entry); });
                data = json;
                return data;
            })
            .finally(() => { loading = null; });
        return loading;
    }

    function fillFilters(box) {
        box.querySelectorAll('[data-job-catalog-filter]').forEach(select => {
            if (select.options.length > 1) return;
            const facet = data.facets[select.dataset.jobCatalogFilter] || {};
            Object.entries(facet).forEach(([value, label]) => select.add(new Option(label, value)));
            select.setAttribute('translate', 'no');
        });
        const note = box.querySelector('[data-job-catalog-note]');
        if (note) note.textContent = (data.packs || []).map(p => p.label + ' · ' + p.count + ' · ' + p.compiled_at + (p.sources_note ? ' — ' + p.sources_note : '')).join(' | ');
    }

    function matches(box) {
        const words = normalize(box.querySelector('[data-job-catalog-search]').value).split(' ').filter(Boolean);
        const filters = {};
        box.querySelectorAll('[data-job-catalog-filter]').forEach(select => { if (select.value) filters[select.dataset.jobCatalogFilter] = select.value; });
        const raw = box.querySelector('[data-job-catalog-search]').value.trim().toLowerCase().replace(/\s+/g, '');
        return data.entries
            .map((entry, index) => {
                if (Object.entries(filters).some(([key, value]) => entry[key] !== value)) return null;
                let score = 0;
                for (const word of words) {
                    if (!entry._hay.includes(word)) return null;
                    score += entry._hay.includes(' ' + word + ' ') ? 2 : 1;
                }
                if (raw && (raw === String(entry.abbreviation).toLowerCase() || raw === String(entry.code).toLowerCase())) score += 10;
                return {entry, score, index};
            })
            .filter(Boolean)
            .sort((a, b) => b.score - a.score || a.index - b.index)
            .map(hit => hit.entry);
    }

    function render() {
        const box = dialog();
        if (!box || !data) return;
        const list = box.querySelector('[data-job-catalog-results]');
        const hits = matches(box);
        list.textContent = '';
        hits.forEach(entry => {
            const li = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'jobcat__result' + (current && current.id === entry.id ? ' is-current' : '');
            button.dataset.jobCatalogEntry = entry.id;
            if (current && current.id === entry.id) button.setAttribute('aria-current', 'true');
            const title = document.createElement('strong');
            if (entry.abbreviation) {
                const abbr = document.createElement('span');
                abbr.className = 'jobcat__chip is-abbr';
                abbr.textContent = entry.abbreviation;
                title.appendChild(abbr);
            }
            title.appendChild(document.createTextNode(entry.name));
            if (entry.code) {
                const code = document.createElement('code');
                code.textContent = entry.code;
                title.appendChild(code);
            }
            const meta = document.createElement('small');
            meta.textContent = [entry.command, entry.type_label, entry.unit].filter(Boolean).join(' · ');
            button.append(title, meta);
            li.appendChild(button);
            list.appendChild(li);
        });
        const count = box.querySelector('[data-job-catalog-count]');
        count.textContent = hits.length > 0 ? hits.length + ' emploi(s)' : 'Aucun emploi ne correspond.';
    }

    function iconPreview(key) {
        const option = form()?.querySelector('[data-icon-option] input[name=icon][value="' + CSS.escape(key) + '"]')?.closest('[data-icon-option]');
        return option ? {svg: option.querySelector('svg'), label: option.querySelector('small')?.textContent || key} : null;
    }

    function show(entry) {
        const box = dialog();
        current = entry;
        const detail = box.querySelector('[data-job-catalog-detail]');
        detail.hidden = false;
        box.querySelector('[data-job-catalog-placeholder]').hidden = true;
        box.querySelector('.jobcat__frame').classList.add('is-detail');
        detail.querySelectorAll('[data-jc]').forEach(node => { node.textContent = entry[node.dataset.jc] || ''; node.hidden = !entry[node.dataset.jc]; });
        detail.querySelectorAll('[data-jc-row]').forEach(row => {
            const key = row.dataset.jcRow;
            const value = key === 'unit' ? entry.unit : entry[key];
            row.hidden = Array.isArray(value) ? value.length === 0 : !value;
        });
        ['qualifications', 'billets', 'tags'].forEach(key => {
            const target = detail.querySelector('[data-jc-list="' + key + '"]');
            target.textContent = '';
            (entry[key] || []).forEach(text => {
                const item = document.createElement(key === 'tags' ? 'span' : 'li');
                if (key === 'tags') item.className = 'job-tag job-tag--slate';
                item.textContent = text;
                target.appendChild(item);
            });
        });
        const sources = detail.querySelector('[data-jc-list="sources"]');
        sources.textContent = '';
        (entry.sources || []).forEach(source => {
            const li = document.createElement('li');
            const link = document.createElement('a');
            link.href = source.url;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = source.title || source.url.replace(/^https:\/\//, '');
            li.appendChild(link);
            sources.appendChild(li);
        });
        const icon = detail.querySelector('[data-jc-icon]');
        const iconLabel = detail.querySelector('[data-jc-icon-label]');
        icon.textContent = '';
        iconLabel.textContent = '';
        const preview = entry.icon ? iconPreview(entry.icon) : null;
        if (preview?.svg) {
            icon.appendChild(preview.svg.cloneNode(true));
            iconLabel.appendChild(preview.svg.cloneNode(true));
        }
        iconLabel.appendChild(document.createTextNode(' ' + (preview ? preview.label : entry.icon || '')));
        detail.querySelector('[data-jc-row="icon"]').hidden = !entry.icon || !form()?.querySelector('input[name=icon]');
        box.querySelectorAll('[data-job-catalog-entry]').forEach(button => {
            const on = button.dataset.jobCatalogEntry === entry.id;
            button.classList.toggle('is-current', on);
            if (on) button.setAttribute('aria-current', 'true'); else button.removeAttribute('aria-current');
        });
        detail.scrollTop = 0;
        detail.focus({preventScroll: true});
    }

    function back() {
        const box = dialog();
        box.querySelector('.jobcat__frame').classList.remove('is-detail');
        const button = current ? box.querySelector('[data-job-catalog-entry="' + CSS.escape(current.id) + '"]') : null;
        (button || box.querySelector('[data-job-catalog-search]')).focus();
    }

    async function open(trigger) {
        const box = dialog();
        if (!box) return;
        opener = trigger || document.activeElement;
        if (!box.open) box.showModal();
        box.querySelector('[data-job-catalog-search]').focus();
        try {
            await load();
            fillFilters(box);
            render();
        } catch (error) {
            box.querySelector('[data-job-catalog-count]').textContent = 'Le catalogue n’a pas pu être chargé. Réessayez.';
        }
    }

    function close() {
        const box = dialog();
        if (box?.open) box.close();
    }

    function field(name) {
        return form()?.querySelector('[name="' + name + '"]:not([type=radio])') || null;
    }

    function rowLabel(key) {
        return dialog()?.querySelector('[data-jc-row="' + key + '"] dt')?.textContent.trim() || key;
    }

    function touch(target) {
        target.dispatchEvent(new Event('input', {bubbles: true}));
        target.dispatchEvent(new Event('change', {bubbles: true}));
    }

    function write(target, value, max) {
        target.value = String(value).slice(0, max);
        touch(target);
    }

    function listText(key) {
        const sep = isEnglish() ? ': ' : ' : ';
        return rowLabel(key) + sep + (current[key] || []).join(' ; ');
    }

    function setIcon(key) {
        const radio = form()?.querySelector('input[name=icon][value="' + CSS.escape(key) + '"]');
        if (!radio) return false;
        radio.checked = true;
        radio.closest('details')?.setAttribute('open', '');
        touch(radio);
        return true;
    }

    function mergeTags(tags) {
        const target = field('tags');
        if (!target) return false;
        const list = target.value.split(',').map(t => t.trim()).filter(Boolean);
        tags.forEach(tag => { if (!list.some(t => normalize(t) === normalize(tag)) && list.length < 8) list.push(tag); });
        write(target, list.join(', '), LIMITS.tags);
        return true;
    }

    function plain(key) {
        return key === 'code' ? field('code') : field(key);
    }

    /** Copie d'un champ ; confirmation si le champ de la fiche est déjà rempli autrement. */
    function copyOne(key, confirmed = false) {
        if (!current) return false;
        if (key === 'icon') return current.icon ? setIcon(current.icon) : false;
        if (key === 'tags') return mergeTags(current.tags || []);
        if (key === 'qualifications' || key === 'billets') {
            const target = field('description');
            if (!target) return false;
            const addition = listText(key);
            if (target.value.includes(addition)) return true;
            write(target, (target.value.trim() ? target.value.trim() + '\n' : '') + addition, LIMITS.description);
            return true;
        }
        const target = plain(key);
        const value = String(current[key] || '');
        if (!target || value === '') return false;
        if (!confirmed && target.value.trim() !== '' && target.value.trim() !== value && !window.confirm('Remplacer le champ « ' + rowLabel(key) + ' » déjà rempli ?')) return null;
        if (key === 'code') target.closest('details')?.setAttribute('open', '');
        write(target, key === 'abbreviation' ? value.toUpperCase() : value, LIMITS[key] || 500);
        return true;
    }

    function copyAll() {
        if (!current) return;
        const keys = ['name', 'abbreviation', 'description', 'code'].filter(key => current[key] && plain(key));
        const replaced = keys.filter(key => {
            const target = plain(key);
            return target.value.trim() !== '' && target.value.trim() !== String(current[key]);
        });
        const checkedIcon = form()?.querySelector('input[name=icon]:checked');
        if (current.icon && checkedIcon && checkedIcon.value !== '' && checkedIcon.value !== current.icon) replaced.push('icon');
        if (replaced.length && !window.confirm('Remplacer les champs déjà remplis : ' + replaced.map(rowLabel).join(', ') + ' ?')) return;
        keys.forEach(key => copyOne(key, true));
        if (current.icon) setIcon(current.icon);
        if ((current.tags || []).length) mergeTags(current.tags);
        close();
        const feedback = document.querySelector('[data-jobs-workspace] [data-jobs-feedback]');
        if (feedback) {
            feedback.textContent = 'Emploi du catalogue copié. Vérifiez la fiche puis enregistrez.';
            feedback.hidden = false;
            feedback.classList.remove('is-error');
        }
        field('name')?.focus();
    }

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-job-catalog-open]');
        if (trigger) { event.preventDefault(); open(trigger); return; }
        const box = dialog();
        if (!box || !box.contains(event.target)) return;
        if (event.target === box) { close(); return; }
        if (event.target.closest('[data-job-catalog-close]')) { close(); return; }
        if (event.target.closest('[data-job-catalog-back]')) { back(); return; }
        const pick = event.target.closest('[data-job-catalog-entry]');
        if (pick && data) {
            const entry = data.entries.find(e => e.id === pick.dataset.jobCatalogEntry);
            if (entry) show(entry);
            return;
        }
        const copy = event.target.closest('[data-job-catalog-copy]');
        if (copy) {
            const key = copy.dataset.jobCatalogCopy;
            if (key === 'all') { copyAll(); return; }
            const done = copyOne(key);
            if (done === true) announce('Copié : ' + rowLabel(key));
            else if (done === false) announce('Champ indisponible dans cette fiche : ' + rowLabel(key));
        }
    });

    document.addEventListener('input', event => {
        if (!event.target.matches('[data-job-catalog-search]')) return;
        window.clearTimeout(timer);
        timer = window.setTimeout(render, 120);
    });
    document.addEventListener('change', event => {
        if (event.target.matches('[data-job-catalog-filter]')) render();
    });

    // Flèches haut / bas dans la liste ; Échap est géré par la fenêtre elle-même.
    document.addEventListener('keydown', event => {
        const box = dialog();
        if (!box?.open || !box.contains(event.target)) return;
        if (event.key === 'ArrowDown' && event.target.matches('[data-job-catalog-search]')) {
            event.preventDefault();
            box.querySelector('[data-job-catalog-entry]')?.focus();
            return;
        }
        const item = event.target.closest('[data-job-catalog-entry]');
        if (!item || (event.key !== 'ArrowDown' && event.key !== 'ArrowUp')) return;
        event.preventDefault();
        const li = item.parentElement;
        const next = event.key === 'ArrowDown' ? li.nextElementSibling : li.previousElementSibling;
        if (next) next.querySelector('button').focus(); else if (event.key === 'ArrowUp') box.querySelector('[data-job-catalog-search]').focus();
    });

    document.addEventListener('close', event => {
        if (!event.target.matches?.('[data-job-catalog]')) return;
        if (opener && opener.isConnected) opener.focus();
        opener = null;
    }, true);

    // Éditeur des prérequis : seuls les champs du type choisi restent visibles et envoyés.
    function prereqRow(row) {
        const type = row.querySelector('[data-prereq-type]')?.value || '';
        row.querySelectorAll('[data-prereq-field]').forEach(label => {
            const on = type !== '' && label.dataset.prereqField.split(' ').includes(type);
            label.hidden = !on;
            label.querySelectorAll('input, select').forEach(control => { control.disabled = !on; });
        });
    }
    function prereqRows() {
        document.querySelectorAll('[data-prereq-row]:not([data-prereq-ready])').forEach(row => { row.setAttribute('data-prereq-ready', '1'); prereqRow(row); });
    }
    document.addEventListener('change', event => {
        if (event.target.matches('[data-prereq-type]')) prereqRow(event.target.closest('[data-prereq-row]'));
    });
    document.addEventListener('click', event => {
        const add = event.target.closest('[data-prereq-add]');
        if (!add) return;
        const formEl = add.closest('form');
        const rows = formEl.querySelector('[data-prereq-rows]');
        const template = formEl.querySelector('[data-prereq-template]');
        if (!rows || !template || rows.children.length >= 12) return;
        const index = 'n' + Date.now().toString(36) + rows.children.length;
        const holder = document.createElement('div');
        holder.innerHTML = template.innerHTML.replace(/__i__/g, index);
        const row = holder.firstElementChild;
        rows.appendChild(row);
        prereqRows();
        row.querySelector('[data-prereq-type]')?.focus();
        if (rows.children.length >= 12) add.disabled = true;
    });

    // Fenêtre d'attribution d'un membre : prérequis manquants des emplois cochés et motif de dérogation.
    function memberPicker(scope) {
        const box = scope.querySelector('[data-prereq-picker]');
        if (!box) return;
        const checked = new Set(Array.from(scope.querySelectorAll('input[name="job_role_ids[]"]:checked')).map(input => input.value));
        let any = false;
        box.querySelectorAll('[data-prereq-missing]').forEach(item => {
            const on = checked.has(item.dataset.prereqMissing) && !item.hasAttribute('data-prereq-held');
            item.hidden = !on;
            any = any || on;
        });
        box.hidden = !any;
        const reason = box.querySelector('textarea[name=prereq_waiver_reason]');
        if (reason) reason.required = any && box.dataset.prereqBlocking === '1';
    }
    document.addEventListener('change', event => {
        const picker = event.target.closest('form[data-job-picker]');
        if (picker && event.target.matches('input[name="job_role_ids[]"]')) memberPicker(picker);
    });

    // « + Créer depuis le catalogue » : la fenêtre s'ouvre d'elle-même sur la fiche de création.
    function autoOpen() {
        prereqRows();
        document.querySelectorAll('form[data-job-picker]:not([data-prereq-ready])').forEach(formEl => { formEl.setAttribute('data-prereq-ready', '1'); memberPicker(formEl); });
        const flag = document.querySelector('[data-job-catalog-autoopen]:not([data-done])');
        if (!flag) return;
        flag.setAttribute('data-done', '1');
        open(document.querySelector('[data-job-catalog-open]'));
    }
    new MutationObserver(autoOpen).observe(document.documentElement, {childList: true, subtree: true});
    autoOpen();
})();
