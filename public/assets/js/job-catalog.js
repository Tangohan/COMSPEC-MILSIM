/*
 * Emplois & fonctions : fenêtre « Chercher dans le catalogue ».
 * Catalogue d'emplois de référence tous pays (fonctions/catalogue, JSON dans la langue du lecteur) : catégories par
 * pays, filtres combinables pays → armée → commandement → unité → type (puces des filtres actifs, réinitialisation),
 * recherche plein texte avec mise en évidence, tri, regroupement par pays et armée, fiche détaillée, puis copie de
 * tout ou d'un champ dans la fiche d'emploi ouverte (confirmation avant de remplacer un champ déjà rempli). Rien
 * n'est enregistré : l'utilisateur relit et valide le formulaire lui-même. Les derniers filtres sont mémorisés dans
 * le navigateur (localStorage, facultatif).
 * Catalogues de communauté : affichés comme des pays (emblème, bannière), créés et complétés depuis la fenêtre
 * (formulaires envoyés en JSON) ; fenêtre agrandissable (mémorisé, Alt + Entrée) ; fenêtre de confirmation avant
 * « Copier tout » (récapitulatif, champs remplacés, « Ne plus afficher »).
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
    const STORE = 'athena.jobCatalog.filters.v2';
    const EXPANDED = 'athena.jobCatalog.expanded.v1';
    const SKIP_CONFIRM = 'athena.jobCatalog.skipCopyConfirm.v1';
    // Stockage du navigateur protégé : navigation privée ou stockage bloqué = comportement par défaut.
    const stored = key => { try { return window.localStorage.getItem(key); } catch (error) { return null; } };
    const store = (key, value) => { try { if (value === null) window.localStorage.removeItem(key); else window.localStorage.setItem(key, value); } catch (error) { /* indisponible */ } };
    const FILTERS = ['country', 'branch', 'command', 'formation', 'type'];
    const state = {country: '', branch: '', command: '', formation: '', type: '', sort: 'relevance', group: true};
    const LIMITS = {name: 120, abbreviation: 8, description: 500, tags: 400, code: 40};
    // Sans accents ni casse ; « ß » (sans forme décomposée) devient « ss ».
    const normalize = value => String(value || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLocaleLowerCase().replace(/ß/g, 'ss').replace(/[^\p{L}\p{N}]+/gu, ' ');
    // Graphie allemande sans tréma : « Fallschirmjäger » se trouve aussi en tapant « fallschirmjaeger » ;
    // russe en latin usuel (même table que JobCatalog::CYRILLIC) : « Спецназ » se trouve en tapant « spetsnaz ».
    const CYRILLIC = {а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'kh', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'shch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya'};
    const transliterate = value => {
        const latin = value.replace(/[äöüÄÖÜ]/g, c => ({ä: 'ae', ö: 'oe', ü: 'ue', Ä: 'Ae', Ö: 'Oe', Ü: 'Ue'})[c]);
        return /\p{Script=Cyrillic}/u.test(latin) ? latin.toLowerCase().replace(/[а-яё]/g, c => CYRILLIC[c]) : latin;
    };
    const dialog = () => document.querySelector('[data-job-catalog]');
    const form = () => document.querySelector('form[data-job-catalog-form]');
    const isEnglish = () => (document.documentElement.lang || 'fr').slice(0, 2) === 'en';
    const branchKey = entry => entry.country + ':' + entry.branch;
    // Nom officiel (langue du pays) en titre ; translittération (« ru-Latn »), puis traductions (langue du lecteur,
    // puis anglais) en ligne secondaire.
    const officialTitle = entry => entry.official_name || entry.name;
    const translations = entry => Object.entries(entry.names || {}).slice(1).filter(([lang]) => lang === 'en' || lang === (isEnglish() ? 'en' : 'fr') || lang.endsWith('-Latn'));
    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    function announce(message) {
        const live = dialog()?.querySelector('[data-job-catalog-live]');
        if (live) { live.textContent = ''; window.setTimeout(() => { live.textContent = message; }, 30); }
    }

    /** Libellé fixe de la fenêtre (traduit avec la page). */
    function label(key) {
        return dialog()?.querySelector('[data-job-catalog-label="' + key + '"]')?.textContent.trim() || key;
    }

    function restore() {
        try {
            const saved = JSON.parse(window.localStorage.getItem(STORE) || 'null');
            if (saved && typeof saved === 'object') {
                Object.keys(state).forEach(key => { if (typeof saved[key] === typeof state[key]) state[key] = saved[key]; });
            }
        } catch (error) { /* stockage indisponible : filtres par défaut */ }
    }

    function persist() {
        try { window.localStorage.setItem(STORE, JSON.stringify(state)); } catch (error) { /* navigation privée */ }
    }

    function haystack(entry) {
        const text = [entry.name, entry.official_name, Object.values(entry.names || {}).join(' '), entry.alt, entry.abbreviation, entry.code, entry.unit, entry.command,
            entry.formation, entry.branch_label, entry.country_label, entry.type_label, (entry.tags || []).join(' '),
            (entry.qualifications || []).join(' '), entry.id].join(' ');
        const german = transliterate(text);
        return ' ' + normalize(text) + ' ' + (german !== text ? normalize(german) + ' ' : '') + String(entry.code || '').toLowerCase().replace(/\s+/g, '') + ' ';
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
                json.entries.forEach((entry, index) => {
                    entry._hay = haystack(entry);
                    entry._name = ' ' + normalize(officialTitle(entry) + ' ' + entry.name) + ' ';
                    entry._index = index;
                });
                json.countries = json.countries || [];
                data = json;
                return data;
            })
            .finally(() => { loading = null; });
        return loading;
    }

    const catalogOf = code => (data?.countries || []).find(c => c.code === code) || null;
    const initials = text => String(text || '').split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();

    /** Drapeau d'un pays (CSS) ou emblème d'un catalogue de communauté (image, sinon initiales sur sa couleur). */
    function paintFlag(node, code) {
        node.className = 'jobcat__flag';
        node.dataset.flag = code;
        node.setAttribute('aria-hidden', 'true');
        node.textContent = '';
        node.style.removeProperty('--jc-flag');
        const c = catalogOf(code);
        if (c?.community) {
            node.classList.add('is-community');
            if (c.color) node.style.setProperty('--jc-flag', c.color);
            if (c.emblem_url) { const img = el('img'); img.src = c.emblem_url; img.alt = ''; img.loading = 'lazy'; node.appendChild(img); }
            else node.textContent = initials(c.label);
            return node;
        }
        node.textContent = code;
        return node;
    }

    function flag(code) {
        return paintFlag(el('span'), code);
    }

    /** Catégories pays : « Tous les pays » est dans la page, les pays viennent du catalogue. */
    function buildCountries(box) {
        const bar = box.querySelector('[data-job-catalog-countries]');
        bar.querySelectorAll('[data-job-catalog-country]:not([data-job-catalog-country=""])').forEach(node => node.remove());
        const add = bar.querySelector('[data-job-catalog-new-collection]');
        if (add) add.hidden = !data.community_ready;
        data.countries.forEach(country => {
            const button = el('button', 'jobcat__country');
            button.type = 'button';
            button.setAttribute('role', 'radio');
            button.setAttribute('translate', 'no');
            button.dataset.jobCatalogCountry = country.code;
            const count = el('span', 'jobcat__country-count');
            count.dataset.jobCatalogCountryCount = '';
            button.append(flag(country.code), el('span', 'jobcat__country-name', country.label), count);
            if (country.community) {
                button.classList.add('is-community');
                if (country.author) button.title = label('shared-by') + ' ' + country.author;
            }
            bar.insertBefore(button, add || null);
        });
    }

    /** Valeurs mémorisées qui n'existent plus dans le catalogue : oubliées. */
    function sanitize() {
        const entries = data.entries;
        if (state.country && !data.countries.some(c => c.code === state.country)) state.country = '';
        const keep = (key, read) => { if (state[key] && !entries.some(e => read(e) === state[key])) state[key] = ''; };
        keep('branch', branchKey);
        keep('command', e => e.command);
        keep('formation', e => e.formation);
        keep('type', e => e.type);
        if (!['relevance', 'name', 'unit'].includes(state.sort)) state.sort = 'relevance';
    }

    function queryWords(box) {
        const input = box.querySelector('[data-job-catalog-search]');
        return {words: normalize(input.value).split(' ').filter(Boolean), raw: input.value.trim().toLowerCase().replace(/\s+/g, ''), text: input.value.trim()};
    }

    function score(entry, query) {
        let total = 0;
        for (const word of query.words) {
            if (!entry._hay.includes(word)) return -1;
            total += (entry._hay.includes(' ' + word + ' ') ? 2 : 1) + (entry._name.includes(' ' + word) ? 1 : 0);
        }
        if (query.raw && (query.raw === String(entry.abbreviation).toLowerCase() || query.raw === String(entry.code).toLowerCase())) total += 10;
        return total;
    }

    const READ = {country: e => e.country, branch: branchKey, command: e => e.command, formation: e => e.formation, type: e => e.type};

    function passes(entry, upTo) {
        for (const key of FILTERS) {
            if (key === upTo) return true;
            if (state[key] && READ[key](entry) !== state[key]) return false;
        }
        return true;
    }

    /** Options d'un filtre : valeurs encore possibles avec les filtres situés avant lui, et leur nombre. */
    function options(scored, key) {
        const seen = new Map();
        scored.forEach(({entry}) => {
            if (!passes(entry, key)) return;
            const value = READ[key](entry);
            if (!value) return;
            const known = seen.get(value);
            if (known) known.count++;
            else seen.set(value, {value, count: 1, label: key === 'branch' ? entry.branch_label : (key === 'type' ? entry.type_label : value), group: entry.country_label, order: entry._index});
        });
        const list = Array.from(seen.values());
        if (key === 'command' || key === 'formation') list.sort((a, b) => a.label.localeCompare(b.label, undefined, {numeric: true, sensitivity: 'base'}));
        else list.sort((a, b) => a.order - b.order);
        return list;
    }

    function fillSelect(select, list, groupByCountry) {
        const keepFirst = select.options[0];
        select.textContent = '';
        select.appendChild(keepFirst);
        let group = null;
        list.forEach(item => {
            const option = new Option(item.label + ' (' + item.count + ')', item.value);
            if (groupByCountry) {
                if (!group || group.label !== item.group) { group = document.createElement('optgroup'); group.label = item.group; select.appendChild(group); }
                group.appendChild(option);
            } else {
                select.appendChild(option);
            }
        });
        select.setAttribute('translate', 'no');
    }

    /** Petits morceaux du texte qui correspondent aux mots cherchés (sans accents ni casse), entourés de <mark>. */
    function highlight(text, words) {
        const fragment = document.createDocumentFragment();
        const source = String(text || '');
        if (!words.length || !source) { fragment.appendChild(document.createTextNode(source)); return fragment; }
        // Texte normalisé caractère par caractère, avec la position de début et de fin de chaque caractère d'origine.
        let flat = '';
        const starts = [];
        const ends = [];
        let offset = 0;
        for (const char of source) {
            for (const c of (normalize(char) || ' ')) { flat += c; starts.push(offset); ends.push(offset + char.length); }
            offset += char.length;
        }
        const marks = [];
        words.forEach(word => {
            let at = flat.indexOf(word);
            while (at !== -1) {
                marks.push([starts[at], ends[at + word.length - 1]]);
                at = flat.indexOf(word, at + word.length);
            }
        });
        if (!marks.length) { fragment.appendChild(document.createTextNode(source)); return fragment; }
        marks.sort((a, b) => a[0] - b[0]);
        const merged = [];
        marks.forEach(range => {
            const last = merged[merged.length - 1];
            if (last && range[0] <= last[1]) last[1] = Math.max(last[1], range[1]); else merged.push(range.slice());
        });
        let pos = 0;
        merged.forEach(([start, end]) => {
            if (start > pos) fragment.appendChild(document.createTextNode(source.slice(pos, start)));
            fragment.appendChild(el('mark', '', source.slice(start, end)));
            pos = end;
        });
        if (pos < source.length) fragment.appendChild(document.createTextNode(source.slice(pos)));
        return fragment;
    }

    function sorted(hits) {
        const collator = new Intl.Collator(isEnglish() ? 'en' : 'fr', {sensitivity: 'base', numeric: true});
        const byName = (a, b) => collator.compare(officialTitle(a.entry), officialTitle(b.entry));
        if (state.sort === 'name') return hits.sort((a, b) => byName(a, b) || a.entry._index - b.entry._index);
        if (state.sort === 'unit') return hits.sort((a, b) => collator.compare(a.entry.formation || a.entry.unit, b.entry.formation || b.entry.unit) || byName(a, b));
        return hits.sort((a, b) => b.score - a.score || a.entry._index - b.entry._index);
    }

    /** Code de langue (« DE ») avec son nom complet en infobulle. */
    function langCode(lang) {
        const code = el('abbr', 'jobcat__lang', lang.toUpperCase());
        code.title = label('lang-' + lang) !== 'lang-' + lang ? label('lang-' + lang) : lang.toUpperCase();
        return code;
    }

    function resultButton(entry, words) {
        const button = el('button', 'jobcat__result' + (current && current.id === entry.id ? ' is-current' : ''));
        button.type = 'button';
        button.dataset.jobCatalogEntry = entry.id;
        if (current && current.id === entry.id) button.setAttribute('aria-current', 'true');
        if (entry.image_url) {
            button.classList.add('has-thumb');
            const thumb = el('img', 'jobcat__thumb');
            thumb.src = entry.image_url;
            thumb.alt = '';
            thumb.loading = 'lazy';
            button.appendChild(thumb);
        }
        const title = el('strong');
        if (entry.abbreviation) {
            const abbr = el('span', 'jobcat__chip is-abbr');
            abbr.appendChild(highlight(entry.abbreviation, words));
            title.appendChild(abbr);
        }
        const name = el('span', 'jobcat__result-name');
        name.appendChild(highlight(entry.official_name || entry.name, words));
        title.appendChild(name);
        if (entry.code) {
            const code = el('code');
            code.appendChild(highlight(entry.code, words));
            title.appendChild(code);
        }
        const meta = el('small');
        meta.appendChild(highlight([entry.formation || entry.command, entry.type_label, entry.formation ? '' : entry.unit].filter(Boolean).join(' · '), words));
        button.append(title);
        const others = translations(entry);
        if (others.length) {
            const alt = el('span', 'jobcat__result-alt');
            others.forEach(([lang, text], i) => {
                if (i) alt.appendChild(document.createTextNode(' · '));
                alt.append(langCode(lang), highlight(text, words));
            });
            button.append(alt);
        }
        if (!state.group) {
            const line = el('span', 'jobcat__result-origin');
            line.append(flag(entry.country), document.createTextNode(entry.branch_label));
            button.append(line);
        }
        button.append(meta);
        return button;
    }

    function chips(box, query) {
        const holder = box.querySelector('[data-job-catalog-chips]');
        const active = box.querySelector('[data-job-catalog-active]');
        holder.textContent = '';
        const add = (key, value) => {
            const chip = el('button', 'jobcat__fchip');
            chip.type = 'button';
            chip.dataset.jobCatalogUnset = key;
            const kind = label(key);
            chip.setAttribute('aria-label', label('remove') + ' ' + kind + ' : ' + value);
            chip.append(el('span', 'jobcat__fchip-kind', kind), el('span', 'jobcat__fchip-value', value), el('span', 'jobcat__fchip-x', '×'));
            chip.lastChild.setAttribute('aria-hidden', 'true');
            chip.setAttribute('translate', 'no');
            holder.appendChild(chip);
        };
        if (query.text) add('q', query.text);
        if (state.country) add('country', data.countries.find(c => c.code === state.country)?.label || state.country);
        ['branch', 'command', 'formation', 'type'].forEach(key => {
            if (!state[key]) return;
            const sample = data.entries.find(e => READ[key](e) === state[key]);
            add(key, key === 'branch' ? (sample?.branch_label || state[key]) : (key === 'type' ? (sample?.type_label || state[key]) : state[key]));
        });
        active.hidden = holder.childElementCount === 0;
        const badge = box.querySelector('[data-job-catalog-filter-badge]');
        const n = ['branch', 'command', 'formation', 'type'].filter(key => state[key]).length;
        badge.textContent = n ? String(n) : '';
        badge.hidden = n === 0;
    }

    function render() {
        const box = dialog();
        if (!box || !data) return;
        const query = queryWords(box);
        const scored = [];
        data.entries.forEach(entry => {
            const s = score(entry, query);
            if (s >= 0) scored.push({entry, score: s});
        });
        // Pays : nombre d'emplois qui répondent à la recherche, quels que soient les autres filtres.
        box.querySelectorAll('[data-job-catalog-country]').forEach(button => {
            const code = button.dataset.jobCatalogCountry;
            const on = code === state.country;
            button.setAttribute('aria-checked', on ? 'true' : 'false');
            button.tabIndex = on ? 0 : -1;
            const count = button.querySelector('[data-job-catalog-country-count]');
            if (count) count.textContent = String(code ? scored.filter(h => h.entry.country === code).length : scored.length);
            // Barre des pays défilante (mobile) : le pays choisi reste visible.
            const bar = button.parentElement;
            if (on && (button.offsetLeft < bar.scrollLeft || button.offsetLeft + button.offsetWidth > bar.scrollLeft + bar.clientWidth)) bar.scrollLeft = Math.max(0, button.offsetLeft - 16);
        });
        ['branch', 'command', 'formation', 'type'].forEach(key => {
            const select = box.querySelector('[data-job-catalog-filter="' + key + '"]');
            const list = options(scored, key);
            if (state[key] && !list.some(item => item.value === state[key])) state[key] = '';
            fillSelect(select, list, key === 'branch' && !state.country && data.countries.length > 1);
            select.value = state[key];
            select.closest('label').hidden = list.length === 0 && !state[key];
        });
        box.querySelector('[data-job-catalog-sort]').value = state.sort;
        box.querySelector('[data-job-catalog-group]').checked = state.group;
        const hits = sorted(scored.filter(({entry}) => passes(entry, null)));
        const list = box.querySelector('[data-job-catalog-results]');
        list.textContent = '';
        list.classList.toggle('is-grouped', state.group);
        const groups = state.group ? groupHits(hits) : [{key: '', hits}];
        groups.forEach(group => {
            if (group.key) {
                const head = el('p', 'jobcat__group-title');
                head.append(flag(group.country), el('span', '', group.title), el('span', 'jobcat__group-count', String(group.hits.length)));
                list.appendChild(head);
            }
            const ul = el('ul', 'jobcat__group');
            if (group.key) ul.setAttribute('aria-label', group.title);
            group.hits.forEach(({entry}) => {
                const li = el('li');
                li.appendChild(resultButton(entry, query.words));
                ul.appendChild(li);
            });
            list.appendChild(ul);
        });
        chips(box, query);
        const count = box.querySelector('[data-job-catalog-count]');
        count.textContent = hits.length > 0 ? `${hits.length} emploi(s)` : 'Aucun emploi ne correspond.';
        notes(box);
        banner(box);
        persist();
    }

    /** Bannière du catalogue de communauté choisi : image, description, auteur ou boutons de gestion. */
    function banner(box) {
        const zone = box.querySelector('[data-job-catalog-banner]');
        if (!zone) return;
        const c = catalogOf(state.country);
        zone.hidden = !c?.community;
        if (!c?.community) return;
        if (c.color) zone.style.setProperty('--jc-flag', c.color); else zone.style.removeProperty('--jc-flag');
        const img = zone.querySelector('[data-job-catalog-banner-img]');
        img.hidden = !c.banner_url;
        if (c.banner_url) img.src = c.banner_url; else img.removeAttribute('src');
        paintFlag(zone.querySelector('[data-job-catalog-banner-flag]'), c.code);
        zone.querySelector('[data-job-catalog-banner-name]').textContent = c.label;
        zone.querySelector('[data-job-catalog-banner-text]').textContent = c.description || '';
        zone.querySelector('[data-job-catalog-banner-shared]').hidden = !(c.editable && c.is_public);
        zone.querySelector('[data-job-catalog-banner-author]').hidden = !c.author;
        zone.querySelector('[data-job-catalog-banner-author-name]').textContent = c.author || '';
        zone.querySelector('[data-job-catalog-banner-actions]').hidden = !c.editable;
    }

    /**
     * Pied de fenêtre sur une ligne : « Sources : n catalogues » (tous pays) ou la note du pays choisi, tronquée ;
     * le panneau repliable liste la note complète de chaque pack affiché.
     */
    function notes(box) {
        const line = box.querySelector('[data-job-catalog-note-line]');
        const list = box.querySelector('[data-job-catalog-note-list]');
        if (!line || !list) return;
        const packs = (data.packs || []).filter(p => !state.country || p.country === state.country);
        const text = p => p.label + ' · ' + p.count + ' · ' + p.compiled_at + (p.sources_note ? ' — ' + p.sources_note : '');
        line.textContent = packs.length === 1 ? text(packs[0]) : label('sources') + (isEnglish() ? ': ' : ' : ') + packs.length + ' ' + label('catalogs');
        box.querySelector('[data-job-catalog-note-more]').textContent = label(packs.length === 1 ? 'see-more' : 'see-sources');
        list.textContent = '';
        packs.forEach(p => {
            const item = el('li');
            item.append(flag(p.country), el('span', '', text(p)));
            list.appendChild(item);
        });
    }

    /** Groupes pays → armée, dans l'ordre des pays puis de la première entrée de chaque armée. */
    function groupHits(hits) {
        const order = new Map(data.countries.map((c, i) => [c.code, i]));
        const groups = new Map();
        hits.forEach(hit => {
            const key = branchKey(hit.entry);
            if (!groups.has(key)) groups.set(key, {key, country: hit.entry.country, title: hit.entry.country_label + ' · ' + hit.entry.branch_label, first: Infinity, hits: []});
            const group = groups.get(key);
            group.first = Math.min(group.first, hit.entry._index);
            group.hits.push(hit);
        });
        return Array.from(groups.values()).sort((a, b) => (order.get(a.country) ?? 99) - (order.get(b.country) ?? 99) || a.first - b.first);
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
        const official = detail.querySelector('[data-jc-translations]');
        official.textContent = '';
        translations(entry).forEach(([lang, text], i) => {
            if (i) official.appendChild(document.createTextNode(' · '));
            official.append(langCode(lang), document.createTextNode(text));
        });
        official.hidden = !official.childNodes.length;
        const names = detail.querySelector('[data-jc-names]');
        const select = detail.querySelector('[data-job-catalog-name-lang]');
        names.textContent = '';
        select.textContent = '';
        // Catalogue de communauté : un seul nom, langue non précisée.
        const nameList = Object.entries(entry.names || {});
        if (!nameList.length) nameList.push(['', entry.official_name || entry.name]);
        nameList.forEach(([lang, text], i) => {
            const item = el('li');
            const copy = el('button', 'jobcat__copy', label('copy'));
            copy.type = 'button';
            copy.dataset.jobCatalogCopy = 'name';
            copy.dataset.lang = lang;
            copy.setAttribute('aria-label', label('copy-name') + (lang ? ' (' + label('lang-' + lang) + ')' : ''));
            const name = el('span', 'jobcat__names-text', text);
            if (i === 0 && lang) name.appendChild(el('small', '', ' — ' + label('official')));
            if (lang) item.append(langCode(lang));
            item.append(name, copy);
            names.appendChild(item);
            const option = el('option', '', lang.toUpperCase() + ' · ' + text + (i === 0 ? ' (' + label('official') + ')' : ''));
            option.value = lang;
            select.appendChild(option);
        });
        select.value = Object.keys(entry.names || {})[0] || '';
        // Choix de la langue utile seulement s'il existe plusieurs noms.
        select.closest('.jobcat__name-lang').hidden = select.options.length < 2;
        paintFlag(detail.querySelector('[data-jc-flag]'), entry.country);
        const image = detail.querySelector('[data-jc-image]');
        image.hidden = !entry.image_url;
        if (entry.image_url) image.src = entry.image_url; else image.removeAttribute('src');
        detail.querySelector('[data-jc-author]').hidden = !entry.author;
        detail.querySelector('[data-jc-author-name]').textContent = entry.author || '';
        detail.querySelector('[data-jc-owner]').hidden = !entry.editable;
        box.querySelector('[data-job-catalog-manage]').hidden = true;
        detail.querySelectorAll('[data-jc-row]').forEach(row => {
            const key = row.dataset.jcRow;
            const value = entry[key];
            row.hidden = key === 'names' ? false : Array.isArray(value) ? value.length === 0 : !value;
        });
        ['qualifications', 'billets', 'tags'].forEach(key => {
            const target = detail.querySelector('[data-jc-list="' + key + '"]');
            target.textContent = '';
            (entry[key] || []).forEach(text => {
                target.appendChild(el(key === 'tags' ? 'span' : 'li', key === 'tags' ? 'job-tag job-tag--slate' : '', text));
            });
        });
        const sources = detail.querySelector('[data-jc-list="sources"]');
        sources.textContent = '';
        (entry.sources || []).forEach(source => {
            const li = el('li');
            const link = el('a', '', source.title || source.url.replace(/^https:\/\//, ''));
            link.href = source.url;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            li.appendChild(link);
            try { li.appendChild(el('small', 'jobcat__source-host', new URL(source.url).hostname.replace(/^www\./, ''))); } catch (error) { /* URL déjà contrôlée côté serveur */ }
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

    /** Recharge le catalogue (après un enregistrement) et revient sur le catalogue ou l'emploi indiqué. */
    async function reload(code, entryId) {
        data = null;
        await load();
        const box = dialog();
        buildCountries(box);
        if (code !== undefined) state.country = code;
        sanitize();
        render();
        const entry = entryId ? data.entries.find(e => e.id === entryId) : null;
        if (entry) show(entry); else closeManage();
    }

    function manageSection() {
        return dialog().querySelector('[data-job-catalog-manage]');
    }

    function closeManage() {
        const box = dialog();
        const section = manageSection();
        if (!section || section.hidden) return;
        section.hidden = true;
        if (current && !box.querySelector('[data-job-catalog-detail]').hidden) return;
        box.querySelector('[data-job-catalog-placeholder]').hidden = false;
        box.querySelector('.jobcat__frame').classList.remove('is-detail');
    }

    /** Affiche un formulaire (catalogue ou emploi) à la place de la fiche. */
    function openForm(selector) {
        const box = dialog();
        const section = manageSection();
        box.querySelector('[data-job-catalog-detail]').hidden = true;
        box.querySelector('[data-job-catalog-placeholder]').hidden = true;
        box.querySelector('.jobcat__frame').classList.add('is-detail');
        section.hidden = false;
        section.querySelectorAll('form').forEach(f => { f.hidden = !f.matches(selector); });
        const formEl = section.querySelector(selector);
        formEl.querySelector('[data-job-catalog-form-error]').hidden = true;
        section.scrollTop = 0;
        formEl.querySelector('input:not([type=hidden]), textarea, select')?.focus();
        return formEl;
    }

    function openCollectionForm(c) {
        const formEl = openForm('[data-job-catalog-collection-form]');
        formEl.reset();
        formEl.querySelector('[data-job-catalog-manage-title]').textContent = label(c ? 'edit-collection' : 'new-collection');
        formEl.elements.collection_id.value = c ? String(c.id) : '0';
        formEl.elements.name.value = c?.label || '';
        formEl.elements.description.value = c?.description || '';
        formEl.elements.icon.value = c?.icon || '';
        formEl.elements.color.value = c?.color || '#1f6f5c';
        formEl.elements.is_public.checked = !!c?.is_public;
        formEl.querySelector('[data-job-catalog-delete-collection]').hidden = !c;
        formEl.elements.name.focus();
    }

    function openEntryForm(c, entry) {
        const formEl = openForm('[data-job-catalog-entry-form]');
        formEl.reset();
        formEl.querySelector('[data-job-catalog-entry-title]').textContent = label(entry ? 'edit-entry' : 'new-entry');
        formEl.querySelector('[data-job-catalog-entry-collection]').textContent = c.label;
        formEl.elements.collection_id.value = String(c.id);
        formEl.elements.entry_id.value = entry ? String(entry.entry_id) : '0';
        if (entry) {
            formEl.elements.name.value = entry.name || '';
            formEl.elements.abbreviation.value = entry.abbreviation || '';
            formEl.elements.type.value = entry.type || 'operator';
            formEl.elements.category.value = entry.category || '';
            formEl.elements.unit.value = entry.unit || '';
            formEl.elements.description.value = entry.description || '';
            formEl.elements.qualifications.value = (entry.qualifications || []).join('\n');
            formEl.elements.tags.value = (entry.tags || []).join(', ');
            formEl.elements.icon.value = entry.icon || '';
        }
        formEl.elements.name.focus();
    }

    /** Envoi JSON d'un formulaire de gestion ; null si l'envoi a échoué (message affiché). */
    async function send(url, body, errorNode) {
        const box = dialog();
        body.append('_csrf_token', box.dataset.csrf || '');
        if (errorNode) { errorNode.hidden = false; errorNode.textContent = label('saving'); errorNode.classList.add('is-pending'); }
        try {
            const response = await fetch(url, {method: 'POST', body, credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-CSRF-Token': box.dataset.csrf || ''}});
            const json = await response.json().catch(() => ({ok: false, message: label('save-failed')}));
            if (errorNode) errorNode.classList.remove('is-pending');
            if (!json.ok) {
                if (errorNode) errorNode.textContent = json.message || label('save-failed');
                else window.alert(json.message || label('save-failed'));
                return null;
            }
            if (errorNode) errorNode.hidden = true;
            announce(json.message || '');
            return json;
        } catch (error) {
            if (errorNode) { errorNode.classList.remove('is-pending'); errorNode.textContent = label('save-failed'); }
            return null;
        }
    }

    async function submitForm(formEl) {
        const box = dialog();
        const isEntry = formEl.matches('[data-job-catalog-entry-form]');
        const url = isEntry ? box.dataset.entryUrl : box.dataset.collectionUrl;
        const json = await send(url, new FormData(formEl), formEl.querySelector('[data-job-catalog-form-error]'));
        if (!json) return;
        if (isEntry) await reload('c' + formEl.elements.collection_id.value, json.id);
        else await reload(json.code);
    }

    function setExpanded(on, remember = true) {
        const box = dialog();
        if (!box) return;
        box.classList.toggle('is-expanded', on);
        box.querySelector('[data-job-catalog-expand]')?.setAttribute('aria-pressed', on ? 'true' : 'false');
        if (remember) store(EXPANDED, on ? '1' : null);
    }

    async function open(trigger) {
        const box = dialog();
        if (!box) return;
        opener = trigger || document.activeElement;
        setExpanded(stored(EXPANDED) === '1', false);
        if (!box.open) box.showModal();
        box.querySelector('[data-job-catalog-search]').focus();
        try {
            await load();
            if (!box.dataset.restored) { box.dataset.restored = '1'; restore(); }
            buildCountries(box);
            sanitize();
            render();
        } catch (error) {
            box.querySelector('[data-job-catalog-count]').textContent = 'Le catalogue n’a pas pu être chargé. Réessayez.';
        }
    }

    function close() {
        const box = dialog();
        if (box?.open) box.close();
    }

    function setCountry(code) {
        if (state.country === code) return;
        state.country = code;
        // L'armée, le commandement et l'unité dépendent du pays ; le type reste.
        if (code) ['branch', 'command', 'formation'].forEach(key => { if (state[key] && !data.entries.some(e => e.country === code && READ[key](e) === state[key])) state[key] = ''; });
        render();
    }

    function unset(key) {
        const box = dialog();
        if (key === 'q') {
            box.querySelector('[data-job-catalog-search]').value = '';
        } else {
            state[key] = '';
            // Retirer un niveau retire aussi ceux qui en dépendent.
            FILTERS.slice(FILTERS.indexOf(key) + 1, 4).forEach(next => {
                if (state[next] && !data.entries.some(e => passes(e, next) && READ[next](e) === state[next])) state[next] = '';
            });
        }
        render();
    }

    function reset() {
        const box = dialog();
        box.querySelector('[data-job-catalog-search]').value = '';
        FILTERS.forEach(key => { state[key] = ''; });
        render();
        box.querySelector('[data-job-catalog-search]').focus();
    }

    function field(name) {
        return form()?.querySelector('[name="' + name + '"]:not([type=radio])') || null;
    }

    function rowLabel(key) {
        if (key === 'name') return label('name');
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

    /** Nom copié : langue demandée, sinon celle choisie dans la fiche, sinon le nom officiel. */
    function nameIn(lang) {
        const names = current.names || {};
        const chosen = lang || dialog()?.querySelector('[data-job-catalog-name-lang]')?.value || '';
        return names[chosen] || current.official_name || current.name || '';
    }

    function valueOf(key, lang) {
        return key === 'name' ? nameIn(lang) : String(current[key] || '');
    }

    function plain(key) {
        return key === 'code' ? field('code') : field(key);
    }

    /** Copie d'un champ ; confirmation si le champ de la fiche est déjà rempli autrement. */
    function copyOne(key, confirmed = false, lang = '') {
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
        const value = valueOf(key, lang);
        if (!target || value === '') return false;
        if (!confirmed && target.value.trim() !== '' && target.value.trim() !== value && !window.confirm('Remplacer le champ « ' + rowLabel(key) + ' » déjà rempli ?')) return null;
        if (key === 'code') target.closest('details')?.setAttribute('open', '');
        write(target, key === 'abbreviation' ? value.toUpperCase() : value, LIMITS[key] || 500);
        return true;
    }

    /** Ce que « Copier tout » écrit dans la fiche : champs, valeur résumée, champ déjà rempli remplacé ou non. */
    function copyPlan() {
        const keys = ['name', 'abbreviation', 'description', 'code'].filter(key => valueOf(key) && plain(key));
        const plan = keys.map(key => {
            const target = plain(key);
            return {key, value: valueOf(key), replaced: target.value.trim() !== '' && target.value.trim() !== valueOf(key)};
        });
        if (current.icon && form()?.querySelector('input[name=icon]')) {
            const checkedIcon = form().querySelector('input[name=icon]:checked');
            plan.push({key: 'icon', value: iconPreview(current.icon)?.label || current.icon, replaced: !!(checkedIcon && checkedIcon.value !== '' && checkedIcon.value !== current.icon)});
        }
        if ((current.tags || []).length && field('tags')) plan.push({key: 'tags', value: current.tags.join(', '), replaced: false});
        return plan;
    }

    /** Fenêtre unique avant « Copier tout » (sauf « Ne plus afficher ») ; résout true pour copier. */
    function confirmCopy(plan) {
        const box = dialog();
        const modal = box?.querySelector('[data-job-catalog-confirm]');
        if (!modal || stored(SKIP_CONFIRM) === '1') return Promise.resolve(true);
        const caption = key => modal.querySelector('[data-job-catalog-confirm-label="' + key + '"]')?.textContent.trim() || key;
        const list = modal.querySelector('[data-job-catalog-confirm-fields]');
        list.textContent = '';
        plan.forEach(item => {
            const row = el('div', item.replaced ? 'is-replaced' : '');
            const term = el('dt', '', caption(item.key));
            if (item.replaced) term.appendChild(el('span', 'jobcat-confirm__badge', caption('replaced')));
            const text = String(item.value);
            row.append(term, el('dd', '', text.length > 160 ? text.slice(0, 157) + '…' : text));
            list.appendChild(row);
        });
        modal.querySelector('[data-job-catalog-confirm-author]').hidden = !current.author;
        modal.querySelector('[data-job-catalog-confirm-author-name]').textContent = current.author || '';
        modal.querySelector('[data-job-catalog-confirm-reference]').hidden = !!current.community;
        const skip = modal.querySelector('[data-job-catalog-confirm-skip]');
        skip.checked = false;
        modal.returnValue = '';
        return new Promise(resolve => {
            modal.addEventListener('close', () => {
                const ok = modal.returnValue === 'copy';
                if (ok && skip.checked) store(SKIP_CONFIRM, '1');
                resolve(ok);
            }, {once: true});
            modal.showModal();
            modal.querySelector('[data-job-catalog-confirm-ok]').focus();
        });
    }

    async function copyAll() {
        if (!current) return;
        const plan = copyPlan();
        if (!plan.length || !(await confirmCopy(plan))) return;
        const keys = plan.map(item => item.key).filter(key => key !== 'icon' && key !== 'tags');
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
        if (event.target.closest('[data-job-catalog-expand]')) { setExpanded(!box.classList.contains('is-expanded')); return; }
        if (!data) return;
        if (event.target.closest('[data-job-catalog-new-collection]')) { openCollectionForm(null); return; }
        if (event.target.closest('[data-job-catalog-edit-collection]')) { openCollectionForm(catalogOf(state.country)); return; }
        if (event.target.closest('[data-job-catalog-new-entry]')) { const c = catalogOf(state.country); if (c?.editable) openEntryForm(c, null); return; }
        if (event.target.closest('[data-job-catalog-edit-entry]')) { const c = current ? catalogOf(current.country) : null; if (c?.editable) openEntryForm(c, current); return; }
        if (event.target.closest('[data-job-catalog-manage-cancel]')) { closeManage(); back(); return; }
        if (event.target.closest('[data-job-catalog-delete-collection]')) {
            const formEl = event.target.closest('form');
            if (!window.confirm(label('confirm-delete-collection'))) return;
            const body = new FormData();
            body.append('collection_id', formEl.elements.collection_id.value);
            send(box.dataset.collectionUrl + '/supprimer', body, formEl.querySelector('[data-job-catalog-form-error]')).then(json => { if (json) reload(''); });
            return;
        }
        if (event.target.closest('[data-job-catalog-delete-entry]')) {
            if (!current?.editable || !window.confirm(label('confirm-delete-entry'))) return;
            const body = new FormData();
            body.append('entry_id', String(current.entry_id));
            const code = current.country;
            send(box.dataset.entryUrl + '/supprimer', body, null).then(json => { if (json) { current = null; box.querySelector('[data-job-catalog-detail]').hidden = true; reload(code); } });
            return;
        }
        const country = event.target.closest('[data-job-catalog-country]');
        if (country) { setCountry(country.dataset.jobCatalogCountry); country.focus(); return; }
        const chip = event.target.closest('[data-job-catalog-unset]');
        if (chip) {
            const keys = Array.from(chip.parentElement.children).map(node => node.dataset.jobCatalogUnset);
            const nextKey = keys[keys.indexOf(chip.dataset.jobCatalogUnset) + 1];
            unset(chip.dataset.jobCatalogUnset);
            const target = nextKey ? box.querySelector('[data-job-catalog-unset="' + nextKey + '"]') : null;
            (target || box.querySelector('[data-job-catalog-search]')).focus();
            return;
        }
        if (event.target.closest('[data-job-catalog-reset]')) { reset(); return; }
        const toggle = event.target.closest('[data-job-catalog-filters-toggle]');
        if (toggle) {
            const on = box.querySelector('.jobcat__frame').classList.toggle('is-filters-open');
            toggle.setAttribute('aria-expanded', on ? 'true' : 'false');
            return;
        }
        const pick = event.target.closest('[data-job-catalog-entry]');
        if (pick) {
            const entry = data.entries.find(e => e.id === pick.dataset.jobCatalogEntry);
            if (entry) show(entry);
            return;
        }
        const copy = event.target.closest('[data-job-catalog-copy]');
        if (copy) {
            const key = copy.dataset.jobCatalogCopy;
            if (key === 'all') { copyAll(); return; }
            const done = copyOne(key, false, copy.dataset.lang || '');
            if (done === true) announce('Copié : ' + rowLabel(key));
            else if (done === false) announce('Champ indisponible dans cette fiche : ' + rowLabel(key));
        }
    });

    document.addEventListener('submit', event => {
        const formEl = event.target.closest('[data-job-catalog-collection-form], [data-job-catalog-entry-form]');
        if (!formEl) return;
        event.preventDefault();
        if (!formEl.elements.name.value.trim()) { formEl.elements.name.focus(); return; }
        submitForm(formEl);
    });

    document.addEventListener('input', event => {
        if (!event.target.matches('[data-job-catalog-search]')) return;
        window.clearTimeout(timer);
        timer = window.setTimeout(render, 120);
    });
    document.addEventListener('change', event => {
        if (!data || !dialog()?.contains(event.target)) return;
        const filter = event.target.closest('[data-job-catalog-filter]');
        if (filter) {
            state[filter.dataset.jobCatalogFilter] = filter.value;
            // Un commandement ou une unité choisis sans armée fixent leur pays et leur armée s'ils n'en ont qu'un.
            const key = filter.dataset.jobCatalogFilter;
            if (filter.value && (key === 'command' || key === 'formation')) {
                const matching = data.entries.filter(e => READ[key](e) === filter.value);
                const countries = new Set(matching.map(e => e.country));
                if (!state.country && countries.size === 1) state.country = matching[0].country;
            }
            render();
            return;
        }
        if (event.target.matches('[data-job-catalog-sort]')) { state.sort = event.target.value; render(); return; }
        if (event.target.matches('[data-job-catalog-group]')) { state.group = event.target.checked; render(); }
    });

    function focusables(box) {
        return Array.from(box.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'))
            .filter(node => !node.disabled && node.tabIndex >= 0 && node.getClientRects().length > 0);
    }

    // Clavier : focus gardé dans la fenêtre (Tab), flèches dans les pays et la liste, Échap (retour à la liste sur mobile).
    document.addEventListener('keydown', event => {
        const box = dialog();
        if (!box?.open || !box.contains(event.target)) return;
        if (event.target.closest('[data-job-catalog-confirm]')) return;
        if (event.key === 'Enter' && event.altKey) { event.preventDefault(); setExpanded(!box.classList.contains('is-expanded')); return; }
        if (event.key === 'Tab') {
            const list = focusables(box);
            if (!list.length) return;
            const first = list[0];
            const last = list[list.length - 1];
            if (event.shiftKey && (event.target === first || !list.includes(event.target))) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && event.target === last) { event.preventDefault(); first.focus(); }
            return;
        }
        if (event.key === 'Escape' && box.querySelector('.jobcat__frame').classList.contains('is-detail') && window.matchMedia('(max-width: 760px)').matches) {
            event.preventDefault();
            back();
            return;
        }
        const country = event.target.closest('[data-job-catalog-country]');
        if (country && data && ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) {
            event.preventDefault();
            const all = Array.from(box.querySelectorAll('[data-job-catalog-country]'));
            let i = all.indexOf(country);
            if (event.key === 'Home') i = 0;
            else if (event.key === 'End') i = all.length - 1;
            else i = (i + (event.key === 'ArrowLeft' || event.key === 'ArrowUp' ? -1 : 1) + all.length) % all.length;
            setCountry(all[i].dataset.jobCatalogCountry);
            box.querySelector('[data-job-catalog-country="' + CSS.escape(all[i].dataset.jobCatalogCountry) + '"]').focus();
            return;
        }
        const results = Array.from(box.querySelectorAll('[data-job-catalog-entry]'));
        if (event.key === 'ArrowDown' && event.target.matches('[data-job-catalog-search]')) {
            event.preventDefault();
            results[0]?.focus();
            return;
        }
        const item = event.target.closest('[data-job-catalog-entry]');
        if (!item || !['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const i = results.indexOf(item);
        const next = event.key === 'Home' ? results[0] : event.key === 'End' ? results[results.length - 1] : results[i + (event.key === 'ArrowDown' ? 1 : -1)];
        if (next) next.focus(); else if (event.key === 'ArrowUp') box.querySelector('[data-job-catalog-search]').focus();
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
