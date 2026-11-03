/*
 * Fiche détaillée d'une qualification du catalogue de référence (brevets et qualifications par pays et OTAN), commune
 * à la fenêtre « Chercher dans le catalogue » des emplois (assets/js/job-catalog.js) et à la page Catalogue de
 * référence du référentiel des qualifications. Contenu lu en JSON (referentiels/qualifications/catalogue/fiche, dans
 * la langue du lecteur, marqué translate="no") : intitulé officiel et traductions, émetteur réel, référence, niveau,
 * prérequis reliés, validité, sources, emplois qui l'exigent, cours Academy ; « Importer dans mon référentiel »
 * (même import que la page, sans doublon) ou lien vers la fiche déjà importée.
 *
 * window.AthenaQualificationSheet.open(container, id, options) — options : url (fiche JSON), csrf, back (libellé du
 * bouton retour, facultatif), onBack(), onQualification(id) (prérequis et qualifications reliées), onJob(job) (sinon
 * le lien de l'emploi est suivi), announce(message).
 */
(() => {
    'use strict';
    if (window.AthenaQualificationSheet) return;
    const cache = new Map();
    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    };
    const data = (tag, className, text) => {
        const node = el(tag, className, text);
        node.setAttribute('translate', 'no');
        return node;
    };
    const button = (className, text) => {
        const node = el('button', className, text);
        node.type = 'button';
        return node;
    };
    const link = (className, text, href) => {
        const node = el('a', className, text);
        node.href = href;
        return node;
    };

    async function load(url, id) {
        const key = url + '?id=' + id;
        if (cache.has(key)) return cache.get(key);
        const response = await fetch(url + (url.includes('?') ? '&' : '?') + 'id=' + encodeURIComponent(id), {credentials: 'same-origin', headers: {Accept: 'application/json'}});
        const json = await response.json().catch(() => null);
        if (!response.ok || !json || !json.ok) throw new Error(json?.message || 'Fiche indisponible.');
        cache.set(key, json);
        return json;
    }

    function row(list, label, content, wide) {
        if (!content) return;
        const box = el('div', wide ? 'is-wide' : '');
        box.append(el('dt', '', label));
        const dd = el('dd');
        dd.append(content);
        box.append(dd);
        list.append(box);
    }

    /** Bouton vers une autre qualification du catalogue (prérequis, accès ouvert). */
    function qualButton(q, options) {
        const b = button('qsheet__qual');
        b.dataset.qsheetQual = q.id;
        b.append(data('span', '', q.name));
        if (q.code) b.append(data('small', 'qsheet__code', q.code));
        b.setAttribute('aria-label', `Ouvrir la fiche de la qualification ${q.name}`);
        b.addEventListener('click', () => options.onQualification?.(q.id));
        return b;
    }

    function render(container, json, options) {
        const q = json.qualification;
        container.textContent = '';
        container.dataset.qsheetId = q.id;
        const root = el('article', 'qsheet');
        root.setAttribute('aria-labelledby', 'qsheet-title-' + q.id);
        if (options.back) {
            const back = button('jobcat__back qsheet__back', options.back);
            back.dataset.qsheetBack = '';
            back.addEventListener('click', () => options.onBack?.());
            root.append(back);
        }
        const head = el('header', 'qsheet__head');
        const origin = el('p', 'jobcat__origin');
        const flag = data('span', 'jobcat__flag', q.country);
        flag.dataset.flag = q.country;
        flag.setAttribute('aria-hidden', 'true');
        origin.append(flag, data('span', '', q.country_label), data('span', '', q.category_label));
        const title = data('h3', 'qsheet__title', q.official_name || q.name);
        title.id = 'qsheet-title-' + q.id;
        if (q.official_name) title.lang = q.official_lang || 'en';
        head.append(origin, title);
        const names = q.names || {};
        const others = [['fr', names.fr], ['en', names.en]].filter(([, text]) => text && text !== title.textContent);
        if (others.length) {
            const line = el('p', 'jobcat__official');
            others.forEach(([lang, text], i) => {
                if (i) line.append(document.createTextNode(' · '));
                const code = el('abbr', 'jobcat__lang', lang.toUpperCase());
                code.title = lang === 'fr' ? 'Français' : 'Anglais';
                const span = data('span', '', text);
                span.lang = lang;
                line.append(code, ' ', span);
            });
            head.append(line);
        }
        const chips = el('p', 'jobcat__chips');
        chips.append(data('span', 'jobcat__chip is-abbr', q.short_name), data('span', 'jobcat__chip', q.code));
        if (q.level) {
            const level = el('span', 'jobcat__chip qsheet__level');
            level.dataset.level = q.level.key;
            level.append(el('span', '', 'Niveau : '), data('strong', '', q.level.label));
            if (q.level.estimated) level.append(el('small', '', ' (niveau estimé)'));
            level.title = q.level.estimated ? 'Niveau estimé d’après l’intitulé et les prérequis, pas une donnée officielle.' : '';
            chips.append(level);
        }
        if (q.validity_months) chips.append(el('span', 'jobcat__chip', `Validité : ${q.validity_months} mois`));
        head.append(chips);
        root.append(head);
        root.append(data('p', 'qsheet__disclaimer', q.disclaimer));

        // Actions : import (sans doublon), fiche déjà importée, formation Academy, catalogue Academy.
        const actions = el('div', 'qsheet__actions');
        const status = el('p', 'qsheet__status');
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        const done = imported => {
            actions.querySelector('[data-qsheet-import]')?.remove();
            const badge = el('span', 'qsheet__done', 'Déjà dans votre référentiel');
            const open = link('eff-btn', 'Ouvrir sa fiche', imported.url);
            actions.prepend(badge, open);
        };
        if (q.imported) done(q.imported);
        else if (json.can_import) {
            const imp = button('eff-btn eff-btn--primary', 'Importer dans mon référentiel');
            imp.dataset.qsheetImport = '';
            imp.addEventListener('click', async () => {
                imp.disabled = true;
                status.textContent = 'Import en cours…';
                const body = new FormData();
                body.append('ids[]', q.id);
                body.append('_csrf_token', options.csrf || '');
                try {
                    const response = await fetch(json.import_url, {method: 'POST', body, credentials: 'same-origin', headers: {Accept: 'application/json', 'X-CSRF-Token': options.csrf || ''}});
                    const answer = await response.json().catch(() => null);
                    if (!answer?.ok) throw new Error(answer?.message || '');
                    q.imported = answer.definitions[q.id] || Object.values(answer.definitions)[0];
                    done(q.imported);
                    // Conditions lues dans la qualification importée : l'encart « Prérequis détectés » les propose.
                    if (answer.prerequisites_url) actions.append(link('eff-btn eff-btn--primary', 'Voir les prérequis détectés', answer.prerequisites_url));
                    status.textContent = answer.message;
                    options.announce?.(answer.message);
                    options.onImported?.(q);
                } catch (error) {
                    imp.disabled = false;
                    status.textContent = error.message || 'L’import a échoué. Réessayez.';
                }
            });
            actions.append(imp);
        }
        if (q.academy) {
            const follow = link('eff-btn', 'Suivre la formation', q.academy.url);
            follow.append(data('small', '', ' · ' + q.academy.title));
            actions.append(follow);
        }
        actions.append(link('qsheet__link', 'Catalogue Academy', json.academy_catalogue_url));
        root.append(actions, status);

        const list = el('dl', 'jobcat__fields qsheet__fields');
        row(list, 'Description', q.description ? data('span', '', q.description) : null, true);
        const issuer = q.issuer_detail || {};
        if (issuer.name) {
            const box = el('span', 'qsheet__issuer');
            box.append(data('strong', '', issuer.name));
            if (issuer.short_name) box.append(data('small', 'qsheet__code', issuer.short_name));
            if (issuer.description && issuer.description !== issuer.name) box.append(data('span', 'qsheet__muted', issuer.description));
            row(list, issuer.kind === 'school' ? 'Organisme émetteur' : 'Émetteur de référence', box, true);
        }
        if (q.body && q.body !== issuer.description) row(list, 'Organisme de référence', data('span', '', q.body), true);
        row(list, 'Référence publique', q.reference ? data('span', '', q.reference) : null, true);
        if ((q.prerequisite_links || []).length) {
            const ul = el('ul', 'jobcat__list qsheet__prereqs');
            q.prerequisite_links.forEach(p => {
                const li = el('li');
                li.append(data('span', '', p.text));
                if (p.links.length) {
                    const go = el('span', 'qsheet__go');
                    p.links.forEach(target => go.append(qualButton(target, options)));
                    li.append(go);
                }
                ul.append(li);
            });
            row(list, 'Prérequis', ul, true);
        }
        row(list, 'Validité', q.validity ? data('span', '', q.validity) : null, true);
        if ((q.unlocks || []).length) {
            const box = el('span', 'qsheet__go');
            q.unlocks.forEach(target => box.append(qualButton(target, options)));
            row(list, 'Ouvre l’accès à', box, true);
        }
        if ((q.jobs || []).length) {
            const ul = el('ul', 'qsheet__jobs');
            q.jobs.forEach(job => {
                const li = el('li');
                const target = options.onJob ? button('qsheet__job') : link('qsheet__job', '', job.url);
                const flagJob = data('span', 'jobcat__flag', job.country);
                flagJob.dataset.flag = job.country;
                flagJob.setAttribute('aria-hidden', 'true');
                target.append(flagJob, data('span', '', job.official_name || job.name));
                if (job.official_name && job.official_name !== job.name) target.append(data('small', '', job.name));
                target.setAttribute('aria-label', `Ouvrir l’emploi ${job.official_name || job.name}`);
                if (options.onJob) target.addEventListener('click', () => options.onJob(job));
                li.append(target);
                ul.append(li);
            });
            const box = el('div');
            box.append(ul);
            if (!options.onJob && q.jobs_url) box.append(link('qsheet__link', 'Voir ces emplois dans le catalogue d’emplois', q.jobs_url));
            row(list, `Emplois qui l’exigent (${q.jobs.length})`, box, true);
        }
        if ((q.sources || []).length) {
            const ol = el('ol', 'jobcat__sources');
            q.sources.forEach(source => {
                const li = el('li');
                const a = data('a', '', source.title || source.url.replace(/^https:\/\//, ''));
                a.href = source.url;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                li.append(a);
                try { li.append(data('small', 'jobcat__source-host', new URL(source.url).hostname.replace(/^www\./, ''))); } catch (error) { /* adresse contrôlée côté serveur */ }
                ol.append(li);
            });
            row(list, 'Sources publiques', ol, true);
        }
        root.append(list);
        container.append(root);
        return root;
    }

    /** Charge et affiche la fiche ; rend l'élément affiché (null si la fiche est indisponible). */
    async function open(container, id, options = {}) {
        container.setAttribute('aria-busy', 'true');
        container.textContent = '';
        container.append(el('p', 'qsheet__loading', 'Chargement de la fiche…'));
        try {
            const json = await load(options.url, id);
            const root = render(container, json, options);
            container.removeAttribute('aria-busy');
            return root;
        } catch (error) {
            container.removeAttribute('aria-busy');
            container.textContent = '';
            if (options.back) {
                const back = button('jobcat__back qsheet__back', options.back);
                back.addEventListener('click', () => options.onBack?.());
                container.append(back);
            }
            container.append(el('p', 'qsheet__error', 'La fiche de cette qualification est indisponible pour le moment.'));
            return null;
        }
    }

    window.AthenaQualificationSheet = {open, forget: () => cache.clear()};
})();
