/*!
 * Athena : passerelle des liens externes.
 *
 * Injecté sur toute page HTML par SiteUpdateInjector (public, back-office, portail, ATAK web).
 *  - Un clic sur un lien http(s) qui mène hors du site ouvre une fenêtre : l'adresse décomposée (protocole, site réel,
 *    chemin), les signaux d'alerte repérés, un guide pour lire une adresse, et 10 secondes pour valider la sortie.
 *    Le délai écoulé sans réponse, le site reste ouvert (choix par défaut sûr).
 *  - AthenaLinks.linkify(élément, texte) rend un texte en nœuds DOM avec ses adresses cliquables (annonces, alertes) :
 *    aucun texte saisi n'est interprété comme du HTML.
 * Rien n'est envoyé nulle part : tout se passe dans le navigateur. Un lien marqué data-no-gate (ou placé dans un
 * élément data-no-gate) n'est pas intercepté.
 */
(function () {
    'use strict';
    if (window.AthenaLinks) { return; }

    var DELAY = 10; // secondes laissées pour valider la sortie
    var SKIP_KEY = 'athena_links_trusted';

    var SHORTENERS = ['bit.ly', 't.co', 'tinyurl.com', 'goo.gl', 'is.gd', 'ow.ly', 'buff.ly', 'rebrand.ly', 'cutt.ly',
        'shorturl.at', 't.ly', 'rb.gy', 'lnkd.in', 'tiny.cc', 'bl.ink', 'v.gd'];
    // Marque : domaines officiels. Une adresse qui cite la marque sans être l'un d'eux est signalée.
    var BRANDS = {
        steam: ['steampowered.com', 'steamcommunity.com', 'steamstatic.com', 'steam.com', 'valvesoftware.com'],
        discord: ['discord.com', 'discord.gg', 'discordapp.com', 'discord.media', 'discordapp.net'],
        paypal: ['paypal.com', 'paypal.me'],
        google: ['google.com', 'google.fr', 'googleapis.com', 'googleusercontent.com', 'gstatic.com'],
        microsoft: ['microsoft.com', 'live.com', 'office.com', 'microsoftonline.com'],
        github: ['github.com', 'github.io', 'githubusercontent.com'],
        apple: ['apple.com', 'icloud.com'],
        arma: ['bistudio.com', 'arma3.com']
    };

    var T = {
        fr: {
            title: 'Vous quittez Athena',
            lead: 'Ce lien mène vers un autre site. Vérifiez l’adresse avant de continuer.',
            dest: 'Destination',
            proto: 'Protocole',
            site: 'Site réel',
            path: 'Chemin',
            none: '(aucun)',
            stay: 'Rester sur Athena',
            go: 'Continuer',
            left: function (n) { return n + ' s pour valider. Sans réponse, vous restez sur Athena.'; },
            expired: 'Délai écoulé : vous êtes resté sur Athena. Aucune page externe n’a été ouverte.',
            retry: 'Recommencer',
            close: 'Fermer',
            remember: function (h) { return 'Ne plus me demander pour ' + h + ' pendant cette session'; },
            flags: 'À vérifier',
            ok: 'Aucun signal d’alerte repéré',
            okNote: 'Cela ne prouve pas que le site est sûr : vérifiez qu’il est bien celui que vous attendez.',
            guideTitle: 'Comment lire une adresse web',
            guide: [
                'Le site réel est le nom juste avant la première barre oblique « / » : dans https://aide.exemple.fr/page, c’est exemple.fr, pas « aide ».',
                'Tout ce qui précède un « @ » est ignoré par le navigateur : https://steam.com@autre.site mène à autre.site.',
                'Méfiez-vous des noms qui imitent une marque (steam-offre.com, discord-nitro.xyz) : seul le nom de domaine compte, pas les mots autour.',
                'Un lien raccourci (bit.ly, t.co…) cache sa destination. Un cadenas ou « https » prouve seulement que la connexion est chiffrée, pas que le site est honnête.',
                'Dans le doute, n’entrez jamais de mot de passe, de code ou de paiement : ouvrez le site vous-même depuis un favori.'
            ],
            f_http: 'Connexion non chiffrée (http) : ce que vous saisissez peut être lu en route.',
            f_puny: 'Nom de site avec des caractères inhabituels : il peut imiter un autre site (homographe).',
            f_ip: 'Adresse numérique (IP) au lieu d’un nom de site.',
            f_user: function (h) { return 'L’adresse contient un « @ » : le vrai site est ' + h + '.'; },
            f_short: 'Lien raccourci : la destination finale est cachée.',
            f_port: function (p) { return 'Port inhabituel (' + p + ').'; },
            f_deep: 'Nom de site très long avec beaucoup de sous-domaines : méthode courante pour masquer le vrai site.',
            f_brand: function (b) { return 'Cite « ' + b + ' » sans appartenir à ' + b + '.'; }
        },
        en: {
            title: 'You are leaving Athena',
            lead: 'This link goes to another site. Check the address before you continue.',
            dest: 'Destination',
            proto: 'Protocol',
            site: 'Actual site',
            path: 'Path',
            none: '(none)',
            stay: 'Stay on Athena',
            go: 'Continue',
            left: function (n) { return n + ' s to confirm. With no answer, you stay on Athena.'; },
            expired: 'Time is up: you stayed on Athena. No external page was opened.',
            retry: 'Try again',
            close: 'Close',
            remember: function (h) { return 'Do not ask again for ' + h + ' during this session'; },
            flags: 'Worth checking',
            ok: 'No warning sign found',
            okNote: 'That does not prove the site is safe: make sure it is the one you expect.',
            guideTitle: 'How to read a web address',
            guide: [
                'The actual site is the name just before the first slash “/”: in https://help.example.com/page, it is example.com, not “help”.',
                'Everything before an “@” is ignored by the browser: https://steam.com@other.site leads to other.site.',
                'Beware of names that imitate a brand (steam-offer.com, discord-nitro.xyz): only the domain name counts, not the words around it.',
                'A shortened link (bit.ly, t.co…) hides its destination. A padlock or “https” only proves the connection is encrypted, not that the site is honest.',
                'When in doubt, never enter a password, a code or a payment: open the site yourself from a bookmark.'
            ],
            f_http: 'Unencrypted connection (http): what you type can be read on the way.',
            f_puny: 'Site name with unusual characters: it may imitate another site (homograph).',
            f_ip: 'Numeric address (IP) instead of a site name.',
            f_user: function (h) { return 'The address contains an “@”: the real site is ' + h + '.'; },
            f_short: 'Shortened link: the final destination is hidden.',
            f_port: function (p) { return 'Unusual port (' + p + ').'; },
            f_deep: 'Very long site name with many subdomains: a common way to hide the real site.',
            f_brand: function (b) { return 'Mentions “' + b + '” without belonging to ' + b + '.'; }
        }
    };

    function lang() {
        var meta = document.querySelector('meta[name="athena-build"]');
        var l = (meta && meta.getAttribute('data-locale')) || document.documentElement.getAttribute('lang') || 'fr';
        return String(l).slice(0, 2).toLowerCase() === 'en' ? 'en' : 'fr';
    }
    function tr() { return T[lang()]; }

    function registrable(host) {
        var p = host.split('.');
        var n = p.length;
        if (n <= 2) { return host; }
        if (n >= 3 && p[n - 1].length === 2 && p[n - 2].length <= 3) { return p.slice(-3).join('.'); }
        return p.slice(-2).join('.');
    }

    function inList(list, value) {
        for (var i = 0; i < list.length; i++) { if (list[i] === value) { return true; } }
        return false;
    }

    function isIp(host) {
        return /^\d{1,3}(\.\d{1,3}){3}$/.test(host) || host.indexOf(':') !== -1 || /^\[.*\]$/.test(host);
    }

    function parse(href) {
        var u;
        try { u = new URL(href, window.location.href); } catch (e) { return null; }
        if (u.protocol !== 'http:' && u.protocol !== 'https:') { return null; }
        return u;
    }

    /** Analyse une adresse : drapeaux de risque, décomposition, caractère externe. */
    function analyse(href) {
        var u = parse(href);
        if (!u) { return null; }
        var host = u.hostname.toLowerCase();
        var here = window.location.hostname.toLowerCase();
        var external = !(host === here || registrable(host) === registrable(here) || host === 'localhost');
        var raw = String(href);
        var flags = [];
        var t = tr();
        if (u.protocol === 'http:') { flags.push(t.f_http); }
        if (host.indexOf('xn--') !== -1 || /[^\x00-\x7f]/.test(host)) { flags.push(t.f_puny); }
        if (isIp(host)) { flags.push(t.f_ip); }
        if (u.username || u.password || /^[a-z][a-z0-9+.-]*:\/\/[^\/?#]*@/i.test(raw)) { flags.push(t.f_user(host)); }
        if (inList(SHORTENERS, registrable(host))) { flags.push(t.f_short); }
        if (u.port && u.port !== '80' && u.port !== '443') { flags.push(t.f_port(u.port)); }
        if (host.split('.').length > 5) { flags.push(t.f_deep); }
        var reg = registrable(host);
        for (var brand in BRANDS) {
            if (Object.prototype.hasOwnProperty.call(BRANDS, brand) && host.indexOf(brand) !== -1 && !inList(BRANDS[brand], reg)) {
                flags.push(t.f_brand(brand));
            }
        }
        var path = (u.pathname === '/' ? '' : u.pathname) + u.search + u.hash;
        return { url: u.href, protocol: u.protocol + '//', host: host, registrable: reg, path: path, external: external, flags: flags };
    }

    function skipped() {
        try { return JSON.parse(window.sessionStorage.getItem(SKIP_KEY) || '[]'); } catch (e) { return []; }
    }
    function remember(host) {
        try {
            var l = skipped();
            if (!inList(l, host)) { l.push(host); }
            window.sessionStorage.setItem(SKIP_KEY, JSON.stringify(l));
        } catch (e) { /* stockage indisponible : on redemandera */ }
    }

    var styled = false;
    function injectStyle() {
        if (styled) { return; }
        styled = true;
        var css = [
            // Variables : sombre par défaut, clair quand la page l'est (classe posée à l'ouverture d'après le fond réel de la page).
            '.athena-gate{--ag-bg:#0c1424;--ag-card:#121c32;--ag-fg:#eef3ff;--ag-mut:#97a6c4;--ag-line:rgba(148,163,184,.2);--ag-acc:var(--accent,#34d399);--ag-accfg:#04130d;--ag-ok:#34d399;--ag-okbg:rgba(52,211,153,.12);--ag-warn:#fbbf24;--ag-warnbg:rgba(251,191,36,.12);--ag-bad:#f87171;--ag-shadow:0 30px 80px rgba(0,0,0,.55)}',
            '.athena-gate.is-light{--ag-bg:#ffffff;--ag-card:#f3f6fb;--ag-fg:#0f172a;--ag-mut:#566178;--ag-line:rgba(15,23,42,.12);--ag-acc:var(--accent,#047857);--ag-accfg:#ffffff;--ag-ok:#047857;--ag-okbg:rgba(4,120,87,.1);--ag-warn:#b45309;--ag-warnbg:rgba(180,83,9,.1);--ag-bad:#b91c1c;--ag-shadow:0 30px 80px rgba(15,23,42,.28)}',
            '.athena-gate{position:fixed;inset:0;z-index:2147483000;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(2,6,23,.64);-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);font:14px/1.5 Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;animation:ag-fade .18s ease-out}',
            '.athena-gate *{box-sizing:border-box}',
            '.athena-gate__box{width:100%;max-width:480px;max-height:calc(100vh - 32px);overflow:auto;background:var(--ag-bg);color:var(--ag-fg);border:1px solid var(--ag-line);border-radius:22px;box-shadow:var(--ag-shadow);animation:ag-pop .22s cubic-bezier(.2,.9,.3,1.2)}',
            '.athena-gate__head{display:flex;gap:14px;align-items:flex-start;padding:24px 24px 6px}',
            '.athena-gate__icon{flex:none;display:grid;place-items:center;width:46px;height:46px;border-radius:14px;color:var(--ag-acc);background:var(--ag-okbg)}',
            '.athena-gate__icon.is-warn{color:var(--ag-warn);background:var(--ag-warnbg)}',
            '.athena-gate__icon svg{width:24px;height:24px}',
            '.athena-gate h2{margin:2px 0 4px;font-size:19px;line-height:1.2;font-weight:800;letter-spacing:-.02em;color:var(--ag-fg)}',
            '.athena-gate__lead{margin:0;color:var(--ag-mut);font-size:13.5px}',
            '.athena-gate__dest{margin:14px 24px 0;padding:14px 16px;background:var(--ag-card);border:1px solid var(--ag-line);border-radius:16px}',
            '.athena-gate__label{display:block;margin-bottom:6px;color:var(--ag-mut);font-size:11px;font-weight:700;letter-spacing:.09em;text-transform:uppercase}',
            '.athena-gate__host{display:flex;flex-wrap:wrap;align-items:baseline;gap:2px 0;font-size:21px;line-height:1.25;font-weight:800;letter-spacing:-.02em;word-break:break-all}',
            '.athena-gate__host em{font-style:normal;font-weight:500;color:var(--ag-mut)}',
            '.athena-gate__proto{display:inline-flex;align-items:center;gap:5px;margin-right:9px;padding:2px 8px;border-radius:99px;font-size:11.5px;font-weight:700;letter-spacing:.02em;color:var(--ag-ok);background:var(--ag-okbg);vertical-align:middle}',
            '.athena-gate__proto.is-bad{color:var(--ag-bad);background:rgba(248,113,113,.14)}',
            '.athena-gate__path{margin:8px 0 0;color:var(--ag-mut);font:12px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace;word-break:break-all;max-height:3.1em;overflow:hidden}',
            '.athena-gate__chips{display:flex;flex-direction:column;gap:8px;margin:12px 24px 0}',
            '.athena-gate__chip{display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border-radius:12px;font-size:13px;line-height:1.45;color:var(--ag-warn);background:var(--ag-warnbg)}',
            '.athena-gate__chip svg{flex:none;width:18px;height:18px;margin-top:1px}',
            '.athena-gate__chip.is-ok{color:var(--ag-ok);background:var(--ag-okbg)}',
            '.athena-gate__chip.is-ok span{color:var(--ag-fg)}',
            '.athena-gate__chip.is-ok small{display:block;margin-top:2px;color:var(--ag-mut);font-size:12px}',
            '.athena-gate details{margin:12px 24px 0;border:1px solid var(--ag-line);border-radius:14px;background:transparent}',
            '.athena-gate summary{display:flex;align-items:center;gap:8px;padding:11px 14px;cursor:pointer;list-style:none;font-weight:700;font-size:13px;color:var(--ag-fg)}',
            '.athena-gate summary::-webkit-details-marker{display:none}',
            '.athena-gate summary svg{width:16px;height:16px;color:var(--ag-mut);transition:transform .15s}',
            '.athena-gate details[open] summary svg{transform:rotate(90deg)}',
            '.athena-gate details ol{margin:0;padding:0 16px 12px 34px;color:var(--ag-mut);font-size:12.5px;line-height:1.55}',
            '.athena-gate details li{margin:0 0 7px}',
            '.athena-gate__remember{display:flex;align-items:center;gap:10px;margin:14px 24px 0;color:var(--ag-mut);font-size:12.5px;cursor:pointer}',
            '.athena-gate__remember input{position:absolute;opacity:0;width:0;height:0}',
            '.athena-gate__switch{position:relative;flex:none;width:34px;height:20px;border-radius:99px;background:var(--ag-line);transition:background .15s}',
            '.athena-gate__switch::after{content:"";position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:var(--ag-fg);transition:transform .15s}',
            '.athena-gate__remember input:checked+.athena-gate__switch{background:var(--ag-acc)}',
            '.athena-gate__remember input:checked+.athena-gate__switch::after{transform:translateX(14px);background:var(--ag-accfg)}',
            '.athena-gate__remember input:focus-visible+.athena-gate__switch{outline:3px solid var(--ag-acc);outline-offset:2px}',
            '.athena-gate__foot{display:flex;align-items:center;gap:14px;margin-top:18px;padding:16px 24px 22px;border-top:1px solid var(--ag-line);flex-wrap:wrap}',
            '.athena-gate__timer{display:flex;align-items:center;gap:12px;flex:1 1 100%;min-width:0}',
            '.athena-gate__ring{position:relative;flex:none;width:46px;height:46px}',
            '.athena-gate__ring svg{width:46px;height:46px;transform:rotate(-90deg)}',
            '.athena-gate__ring circle{fill:none;stroke-width:3.5}',
            '.athena-gate__ring .trk{stroke:var(--ag-line)}',
            '.athena-gate__ring .bar{stroke:var(--ag-acc);stroke-linecap:round}',
            '.athena-gate__ring b{position:absolute;inset:0;display:grid;place-items:center;font-size:15px;font-weight:800;font-variant-numeric:tabular-nums}',
            '.athena-gate__sub{margin:0;color:var(--ag-mut);font-size:12.5px;line-height:1.4}',
            '.athena-gate__acts{display:flex;gap:10px;flex:1 1 100%}.athena-gate__acts button{flex:1}',
            '.athena-gate button{font:inherit;font-weight:700;font-size:14px;height:44px;padding:0 18px;border-radius:13px;cursor:pointer;border:1px solid var(--ag-line);background:transparent;color:var(--ag-fg);transition:transform .08s,background .15s,border-color .15s}',
            '.athena-gate button:hover{background:var(--ag-card)}',
            '.athena-gate button:active{transform:translateY(1px)}',
            '.athena-gate button.is-pri{background:var(--ag-acc);color:var(--ag-accfg);border-color:transparent}',
            '.athena-gate button.is-pri:hover{filter:brightness(1.08);background:var(--ag-acc)}',
            '.athena-gate button:focus-visible{outline:3px solid var(--ag-acc);outline-offset:2px}',
            '.athena-gate.is-over .athena-gate__ring .bar{stroke:var(--ag-line)}',
            '@keyframes ag-fade{from{opacity:0}to{opacity:1}}',
            '@keyframes ag-pop{from{opacity:0;transform:translateY(10px) scale(.97)}to{opacity:1;transform:none}}',
            '@media (max-width:480px){.athena-gate{align-items:flex-end;padding:0}.athena-gate__box{border-radius:22px 22px 0 0;max-height:92vh}}',
            '@media (prefers-reduced-motion:reduce){.athena-gate,.athena-gate__box{animation:none}.athena-gate *{transition:none!important}}'
        ].join('\n');
        var s = document.createElement('style');
        s.id = 'athena-links-style';
        s.appendChild(document.createTextNode(css));
        (document.head || document.documentElement).appendChild(s);
    }

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text != null) { n.textContent = text; }
        return n;
    }

    var SVGNS = 'http://www.w3.org/2000/svg';
    function icon(path) {
        var s = document.createElementNS(SVGNS, 'svg');
        s.setAttribute('viewBox', '0 0 24 24');
        s.setAttribute('aria-hidden', 'true');
        var p = document.createElementNS(SVGNS, 'path');
        p.setAttribute('d', path);
        p.setAttribute('fill', 'none');
        p.setAttribute('stroke', 'currentColor');
        p.setAttribute('stroke-width', '2');
        p.setAttribute('stroke-linecap', 'round');
        p.setAttribute('stroke-linejoin', 'round');
        s.appendChild(p);
        return s;
    }
    var ICON_OUT = 'M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5';
    var ICON_CHECK = 'M5 12.5l4.5 4.5L19 7.5';
    var ICON_WARN = 'M12 3 2.5 20h19L12 3zM12 10v4.5M12 17.5v.01';
    var ICON_CHEV = 'M9 6l6 6-6 6';

    /** Vrai si le fond réel de la page est sombre (repli : préférence du système). */
    function pageIsDark() {
        try {
            var c = window.getComputedStyle(document.body || document.documentElement).backgroundColor;
            var m = /rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/.exec(c);
            if (m && (m[4] === undefined || parseFloat(m[4]) > 0.1)) {
                return (0.2126 * m[1] + 0.7152 * m[2] + 0.0722 * m[3]) / 255 < 0.5;
            }
        } catch (e) { /* repli */ }
        return !(window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches);
    }

    var openGate = null;

    /** Ouvre la fenêtre de confirmation. opts.newTab : ouvrir la destination dans un nouvel onglet. */
    function confirmLeave(info, opts) {
        opts = opts || {};
        if (openGate) { openGate.destroy(); }
        injectStyle();
        var t = tr();
        var previous = document.activeElement;
        var timer = null;
        var started = 0;
        var CIRC = 2 * Math.PI * 19;

        var overlay = el('div', 'athena-gate' + (pageIsDark() ? '' : ' is-light'));
        overlay.setAttribute('role', 'presentation');
        var box = el('div', 'athena-gate__box');
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.setAttribute('aria-labelledby', 'athena-gate-title');

        // En-tête : icône (verte, ou orange s'il y a un signal d'alerte), titre, une phrase.
        var head = el('div', 'athena-gate__head');
        var ic = el('div', 'athena-gate__icon' + (info.flags.length ? ' is-warn' : ''));
        ic.appendChild(icon(info.flags.length ? ICON_WARN : ICON_OUT));
        head.appendChild(ic);
        var headText = el('div');
        var h = el('h2', null, t.title);
        h.id = 'athena-gate-title';
        headText.appendChild(h);
        headText.appendChild(el('p', 'athena-gate__lead', t.lead));
        head.appendChild(headText);
        box.appendChild(head);

        // Destination : une seule fois, lisible : protocole, site réel en gros, chemin en petit.
        var dest = el('div', 'athena-gate__dest');
        dest.setAttribute('title', info.url);
        dest.appendChild(el('span', 'athena-gate__label', t.dest));
        var hostRow = el('div', 'athena-gate__host');
        var proto = el('span', 'athena-gate__proto' + (info.protocol === 'http://' ? ' is-bad' : ''), info.protocol.replace('://', ''));
        hostRow.appendChild(proto);
        var prefix = info.host.length > info.registrable.length && info.host.slice(-info.registrable.length) === info.registrable
            ? info.host.slice(0, info.host.length - info.registrable.length) : '';
        if (prefix) { hostRow.appendChild(el('em', null, prefix)); }
        hostRow.appendChild(el('span', null, info.registrable));
        dest.appendChild(hostRow);
        if (info.path) {
            dest.appendChild(el('p', 'athena-gate__path', info.path.length > 140 ? info.path.slice(0, 137) + '…' : info.path));
        }
        box.appendChild(dest);

        // Signaux : une pastille par signal, ou une seule pastille verte.
        var chips = el('div', 'athena-gate__chips');
        chips.setAttribute('role', 'list');
        if (info.flags.length) {
            for (var f = 0; f < info.flags.length; f++) {
                var chip = el('div', 'athena-gate__chip');
                chip.setAttribute('role', 'listitem');
                chip.appendChild(icon(ICON_WARN));
                chip.appendChild(el('span', null, info.flags[f]));
                chips.appendChild(chip);
            }
        } else {
            var okc = el('div', 'athena-gate__chip is-ok');
            okc.setAttribute('role', 'listitem');
            okc.appendChild(icon(ICON_CHECK));
            var okt = el('span', null, t.ok);
            okt.appendChild(el('small', null, t.okNote));
            okc.appendChild(okt);
            chips.appendChild(okc);
        }
        box.appendChild(chips);

        // Guide de lecture d'une adresse : replié.
        var det = el('details');
        var sum = el('summary');
        sum.appendChild(icon(ICON_CHEV));
        sum.appendChild(document.createTextNode(t.guideTitle));
        det.appendChild(sum);
        var gl = el('ol');
        for (var g = 0; g < t.guide.length; g++) { gl.appendChild(el('li', null, t.guide[g])); }
        det.appendChild(gl);
        box.appendChild(det);

        var remBox = null;
        if (!info.flags.length) {
            var remRow = el('label', 'athena-gate__remember');
            remBox = el('input');
            remBox.type = 'checkbox';
            remRow.appendChild(remBox);
            remRow.appendChild(el('span', 'athena-gate__switch'));
            remRow.appendChild(el('span', null, t.remember(info.registrable)));
            box.appendChild(remRow);
        }

        // Pied : compte à rebours en anneau, puis les deux choix.
        var foot = el('div', 'athena-gate__foot');
        var timerBox = el('div', 'athena-gate__timer');
        var ring = el('div', 'athena-gate__ring');
        ring.setAttribute('aria-hidden', 'true');
        var rs = document.createElementNS(SVGNS, 'svg');
        rs.setAttribute('viewBox', '0 0 46 46');
        var trk = document.createElementNS(SVGNS, 'circle');
        var bar = document.createElementNS(SVGNS, 'circle');
        [trk, bar].forEach(function (c) { c.setAttribute('cx', '23'); c.setAttribute('cy', '23'); c.setAttribute('r', '19'); });
        trk.setAttribute('class', 'trk');
        bar.setAttribute('class', 'bar');
        bar.setAttribute('stroke-dasharray', String(CIRC));
        rs.appendChild(trk);
        rs.appendChild(bar);
        ring.appendChild(rs);
        var count = el('b', null, String(DELAY));
        ring.appendChild(count);
        timerBox.appendChild(ring);
        var status = el('p', 'athena-gate__sub');
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        timerBox.appendChild(status);
        foot.appendChild(timerBox);

        var acts = el('div', 'athena-gate__acts');
        var stay = el('button', null, t.stay);
        stay.type = 'button';
        var go = el('button', 'is-pri', t.go);
        go.type = 'button';
        go.setAttribute('title', info.url);
        acts.appendChild(stay);
        acts.appendChild(go);
        foot.appendChild(acts);
        box.appendChild(foot);
        overlay.appendChild(box);

        function destroy() {
            if (timer) { window.clearInterval(timer); timer = null; }
            document.removeEventListener('keydown', onKey, true);
            if (overlay.parentNode) { overlay.parentNode.removeChild(overlay); }
            openGate = null;
            if (previous && previous.focus) { try { previous.focus(); } catch (e) { /* élément disparu */ } }
        }
        function leave() {
            if (remBox && remBox.checked) { remember(info.registrable); }
            var url = info.url;
            var newTab = !!opts.newTab;
            destroy();
            if (newTab) { window.open(url, '_blank', 'noopener,noreferrer'); } else { window.location.assign(url); }
        }
        function expire() {
            if (timer) { window.clearInterval(timer); timer = null; }
            overlay.className += ' is-over';
            bar.setAttribute('stroke-dashoffset', String(CIRC));
            count.textContent = '0';
            status.textContent = t.expired;
            go.textContent = t.retry;
            go.onclick = function () { destroy(); confirmLeave(info, opts); };
            stay.textContent = t.close;
            if (remBox) { remBox.parentNode.style.display = 'none'; }
            stay.focus();
        }
        function tick() {
            var elapsed = (Date.now() - started) / 1000;
            var left = Math.max(0, DELAY - elapsed);
            bar.setAttribute('stroke-dashoffset', String(CIRC * (1 - left / DELAY)));
            var secs = Math.ceil(left);
            if (secs !== tick.last) { tick.last = secs; count.textContent = String(secs); status.textContent = t.left(secs); }
            if (left <= 0) { expire(); }
        }
        function onKey(e) {
            if (e.key === 'Escape' || e.keyCode === 27) { e.preventDefault(); destroy(); return; }
            if (e.key !== 'Tab' && e.keyCode !== 9) { return; }
            var focusables = box.querySelectorAll('button, summary, input');
            var visible = [];
            for (var k = 0; k < focusables.length; k++) { if (focusables[k].offsetParent !== null || focusables[k].tagName === 'INPUT') { visible.push(focusables[k]); } }
            if (!visible.length) { return; }
            var first = visible[0];
            var last = visible[visible.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }

        stay.onclick = destroy;
        go.onclick = leave;
        overlay.addEventListener('mousedown', function (e) { if (e.target === overlay) { destroy(); } });
        document.addEventListener('keydown', onKey, true);
        (document.body || document.documentElement).appendChild(overlay);
        started = Date.now();
        tick();
        timer = window.setInterval(tick, 100);
        // Le choix par défaut est le plus sûr : rester.
        stay.focus();
        openGate = { destroy: destroy };
    }

    function gate(a, e) {
        if (typeof a.href !== 'string') { return false; }
        var info = analyse(a.href);
        if (!info || !info.external) { return false; }
        if (!info.flags.length && inList(skipped(), info.registrable)) { return false; }
        e.preventDefault();
        e.stopPropagation();
        var newTab = a.target === '_blank' || e.ctrlKey || e.metaKey || e.shiftKey || e.type === 'auxclick';
        confirmLeave(info, { newTab: newTab });
        return true;
    }

    function onClick(e) {
        if (e.defaultPrevented) { return; }
        if (e.type === 'click' && e.button !== 0) { return; }
        var node = e.target;
        if (!node || !node.closest) { return; }
        var a = node.closest('a[href]');
        if (!a || a.hasAttribute('download') || a.hasAttribute('data-no-gate') || a.closest('[data-no-gate]')) { return; }
        gate(a, e);
    }
    document.addEventListener('click', onClick, true);
    document.addEventListener('auxclick', function (e) { if (e.button === 1) { onClick(e); } }, true);

    function count(s, ch) {
        var n = 0;
        for (var i = 0; i < s.length; i++) { if (s.charAt(i) === ch) { n++; } }
        return n;
    }

    /** Écrit « text » dans « parent » : texte brut et adresses http(s) en liens, jamais de HTML interprété. */
    function linkify(parent, text) {
        text = String(text == null ? '' : text);
        var re = /https?:\/\/[^\s<>"'`]+/gi;
        var last = 0;
        var m;
        while ((m = re.exec(text)) !== null) {
            var url = m[0];
            var trail = '';
            while (url.length > 0 && /[.,;:!?)\]}»”’]$/.test(url)) {
                var ch = url.charAt(url.length - 1);
                if (ch === ')' && count(url, '(') >= count(url, ')')) { break; }
                trail = ch + trail;
                url = url.slice(0, -1);
            }
            if (m.index > last) { parent.appendChild(document.createTextNode(text.slice(last, m.index))); }
            if (parse(url)) {
                var a = document.createElement('a');
                a.href = url;
                a.textContent = url;
                a.target = '_blank';
                a.rel = 'noopener noreferrer nofollow ugc';
                a.className = 'athena-linkified';
                parent.appendChild(a);
            } else {
                parent.appendChild(document.createTextNode(url));
            }
            if (trail) { parent.appendChild(document.createTextNode(trail)); }
            last = m.index + m[0].length;
        }
        if (last < text.length) { parent.appendChild(document.createTextNode(text.slice(last))); }
        return parent;
    }

    window.AthenaLinks = { analyse: analyse, confirm: confirmLeave, linkify: linkify, delay: DELAY };
})();
