/*!
 * Aperçu réel des annonces (administration du site, Alertes).
 * Construit les MÊMES éléments que le portail (classes de dsfr-service.css, navbar-info-banners.css et fenêtre du tableau de
 * bord), à leur taille réelle, avec le texte saisi : ce que voit un visiteur, pas une maquette réduite.
 *   AthenaAlertPreview.mount(conteneur, { kind, styles: [...], title, body, cta_label, cta_url, scope, theme })
 *   AthenaAlertPreview.gallery(conteneur, [[type, libellé]...], { styles, theme })   tous les types, un exemple chacun
 * Aucun HTML interprété : tout passe par textContent / linkify (AthenaLinks) ; rien n'est envoyé ni enregistré.
 */
(function () {
    'use strict';
    if (window.AthenaAlertPreview) { return; }

    var TONE = { urgent: 'error', security: 'error', discount: 'warning', maintenance: 'warning', novelty: 'info', event: 'info', notice: 'info', training: 'info', recruitment: 'info', info: 'info' };
    var LABEL = { discount: 'Promotion', novelty: 'Nouveau', urgent: 'Urgent', info: 'Information', notice: 'Annonce', event: 'Événement', maintenance: 'Maintenance', training: 'Formation', recruitment: 'Recrutement', security: 'Sécurité' };
    var KICKER = { urgent: 'Urgent', info: 'Information', notice: 'Consigne', event: 'Événement', novelty: 'Nouveauté', discount: 'Promotion', maintenance: 'Maintenance', training: 'Formation', recruitment: 'Recrutement', security: 'Sécurité' };
    var STYLE_NAME = {
        classic: 'Bandeau du portail', mini_info: 'Barre sous le menu : information', mini_success: 'Barre sous le menu : succès',
        mini_warning: 'Barre sous le menu : attention', mini_danger: 'Barre sous le menu : critique', breaking: 'Bandeau défilant « Attention »',
        popup: 'Fenêtre au tableau de bord', important: 'Annonce importante', activity_feed: 'Fil d’activité', members_only: 'Membres seulement', back_office: 'Back-office'
    };

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text != null) { n.textContent = text; }
        return n;
    }
    function write(node, text) {
        if (window.AthenaLinks) { window.AthenaLinks.linkify(node, text); } else { node.textContent = text; }
        return node;
    }

    var styled = false;
    function injectStyle() {
        if (styled) { return; }
        styled = true;
        var css = [
            // Même feuille que la fenêtre du tableau de bord (dashboard_popup_modal.php), sans voile ni animation d'entrée.
            '.dash-popup-panel{position:relative;width:min(100%,30rem);border-radius:1rem;background:#fff;box-shadow:0 25px 50px -12px rgba(0,0,0,.45);overflow:hidden}',
            '.dash-popup-panel__body{padding:1.5rem}',
            '.dash-popup-panel__kicker{margin:0 0 .4rem;font-size:.6875rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#059669}',
            '.dash-popup-panel__title{margin:0 0 .6rem;font-size:1.25rem;font-weight:900;letter-spacing:-.01em;color:#0f172a}',
            '.dash-popup-panel__desc{margin:0 0 1.1rem;font-size:.875rem;line-height:1.55;color:#475569;white-space:pre-wrap}',
            '.dash-popup-panel__actions{display:flex;flex-wrap:wrap;gap:.6rem}',
            '.dash-popup-panel__close{position:absolute;top:.75rem;right:.75rem;display:inline-flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:.6rem;border:1px solid #e2e8f0;background:#f8fafc;color:#334155}',
            '.dash-popup-panel .ap-btn{display:inline-flex;align-items:center;min-height:2.5rem;padding:0 1rem;border-radius:.6rem;font-size:.875rem;font-weight:800;border:1px solid #0f172a;background:#0f172a;color:#fff;text-decoration:none}',
            '.dash-popup-panel .ap-btn--ghost{background:#fff;color:#0f172a;border-color:#cbd5e1}',
            // Cadre d'aperçu.
            '.ap-item{margin:0 0 1.1rem}',
            '.ap-cap{display:flex;align-items:center;gap:.5rem;margin:0 0 .45rem;font-size:.6875rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#64748b}',
            '.ap-stage{position:relative;border:1px solid #e2e8f0;border-radius:.9rem;overflow:hidden;background:#fff}',
            '.ap-stage--dark{background:#0b0f14;border-color:#1e293b}',
            '.ap-stage--popup{display:flex;justify-content:center;padding:1.25rem;background:rgba(2,6,23,.72)}',
            '.ap-stage .navbar-info-banners{position:static}',
            '.ap-stage .nib-ticker-track{animation-play-state:paused}',
            '.ap-stage a{pointer-events:none}',
            '.ap-empty{margin:0;padding:.9rem 1rem;border-radius:.7rem;background:#fffbeb;color:#92400e;font-size:.8125rem}'
        ].join('\n');
        var s = el('style');
        s.id = 'ap-style';
        s.appendChild(document.createTextNode(css));
        (document.head || document.documentElement).appendChild(s);
    }

    function classic(a) {
        var kind = a.kind || 'info';
        var node = el('div', 'ds-alert ds-alert--' + (TONE[kind] || 'info'));
        node.setAttribute('role', kind === 'urgent' ? 'alert' : 'status');
        node.appendChild(el('p', 'ds-alert__title', a.title || LABEL[kind] || 'Information'));
        if (a.body) { node.appendChild(write(el('p'), a.body)); }
        if (a.cta_url && a.cta_label) {
            var cta = el('a', 'ds-alert__link', a.cta_label);
            cta.href = '#';
            node.appendChild(cta);
        }
        return node;
    }

    function mini(a) {
        var style = a.display_style || 'mini_info';
        var tone = style === 'mini_success' ? 'success' : style === 'mini_warning' ? 'warning' : style === 'mini_danger' ? 'danger' : 'info';
        var tag = style === 'mini_success' ? 'Succès' : style === 'mini_warning' ? 'Alerte' : style === 'mini_danger' ? 'Critique' : 'Info';
        var node = el('div', 'mini-banner ' + tone);
        node.setAttribute('role', 'status');
        node.appendChild(el('div', 'banner-tag', tag));
        var content = el('div', 'banner-content');
        content.appendChild(el('span', 'banner-title', a.title || ''));
        if (a.body) { content.appendChild(write(el('span', 'banner-desc'), a.body)); }
        if (a.cta_url && a.cta_label) {
            var cta = el('a', 'banner-cta', a.cta_label);
            cta.href = '#';
            content.appendChild(cta);
        }
        node.appendChild(content);
        return node;
    }

    function breaking(a) {
        var node = el('div', 'nib-breaking');
        node.setAttribute('role', 'status');
        var pill = el('div', 'nib-breaking-pill');
        pill.appendChild(el('span', 'nib-live-dot'));
        pill.appendChild(el('span', null, 'Attention'));
        node.appendChild(pill);
        var ticker = el('div', 'nib-breaking-ticker');
        var track = el('div', 'nib-ticker-track');
        var parts = [];
        if ((a.title || '').trim()) { parts.push(a.title.trim()); }
        String(a.body || '').split(/\r?\n+| · | • |\|/).forEach(function (p) { p = p.trim(); if (p) { parts.push(p); } });
        if (!parts.length) { parts.push('Annonce'); }
        for (var pass = 0; pass < 2; pass++) {
            for (var i = 0; i < parts.length; i++) {
                if (pass > 0 || i > 0) { track.appendChild(el('span', 'ticker-sep nib-ticker-sep', '◆')); }
                track.appendChild(el('span', null, parts[i]));
            }
        }
        ticker.appendChild(track);
        node.appendChild(ticker);
        return node;
    }

    function popup(a) {
        var kind = a.kind || 'info';
        var panel = el('div', 'dash-popup-panel');
        var body = el('div', 'dash-popup-panel__body');
        body.appendChild(el('p', 'dash-popup-panel__kicker', (KICKER[kind] || 'Annonce') + (a.scope === 'tenant' ? '' : ' · Athena')));
        body.appendChild(el('h2', 'dash-popup-panel__title', a.title || 'Annonce'));
        if (a.body) { body.appendChild(write(el('p', 'dash-popup-panel__desc'), a.body)); }
        var actions = el('div', 'dash-popup-panel__actions');
        var hasCta = a.cta_url && a.cta_label;
        if (hasCta) { actions.appendChild(el('span', 'ap-btn', a.cta_label)); }
        actions.appendChild(el('span', 'ap-btn' + (hasCta ? ' ap-btn--ghost' : ''), 'J’ai pris connaissance'));
        body.appendChild(actions);
        panel.appendChild(body);
        panel.appendChild(el('span', 'dash-popup-panel__close', '×'));
        return panel;
    }

    function build(style, a) {
        var b = {};
        for (var k in a) { if (Object.prototype.hasOwnProperty.call(a, k)) { b[k] = a[k]; } }
        b.display_style = style;
        if (style === 'popup') { return { node: popup(b), cls: 'ap-stage ap-stage--popup' }; }
        if (style === 'breaking') { var w = el('div', 'navbar-info-banners'); w.appendChild(breaking(b)); return { node: w, cls: 'ap-stage' }; }
        if (style.indexOf('mini_') === 0) { var m = el('div', 'navbar-info-banners'); m.appendChild(mini(b)); return { node: m, cls: 'ap-stage' }; }
        var c = el('div'); c.style.padding = '.75rem'; c.appendChild(classic(b));
        return { node: c, cls: 'ap-stage' };
    }

    function mount(box, a) {
        injectStyle();
        a = a || {};
        while (box.firstChild) { box.removeChild(box.firstChild); }
        var styles = a.styles && a.styles.length ? a.styles : ['classic'];
        for (var i = 0; i < styles.length; i++) {
            var built = build(styles[i], a);
            var item = el('div', 'ap-item');
            if (a.caption !== false) { item.appendChild(el('p', 'ap-cap', STYLE_NAME[styles[i]] || styles[i])); }
            var stage = el('div', built.cls + (a.theme === 'dark' && styles[i] !== 'popup' ? ' ap-stage--dark' : ''));
            stage.appendChild(built.node);
            item.appendChild(stage);
            box.appendChild(item);
        }
        return box;
    }

    function gallery(box, kinds, opts) {
        injectStyle();
        opts = opts || {};
        while (box.firstChild) { box.removeChild(box.firstChild); }
        for (var i = 0; i < kinds.length; i++) {
            var sub = el('div');
            sub.appendChild(el('p', 'ap-cap', kinds[i][1]));
            var inner = el('div');
            mount(inner, { caption: false, kind: kinds[i][0], styles: opts.styles || ['classic'], theme: opts.theme, title: opts.title || kinds[i][1], body: opts.body || '', scope: 'platform', cta_label: opts.cta_label, cta_url: opts.cta_url });
            sub.appendChild(inner);
            box.appendChild(sub);
        }
        return box;
    }

    window.AthenaAlertPreview = { mount: mount, gallery: gallery, styleName: function (s) { return STYLE_NAME[s] || s; } };
})();
