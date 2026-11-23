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
            left: function (n) { return 'Il vous reste ' + n + ' s pour valider. Sans réponse, vous restez sur Athena.'; },
            expired: 'Délai écoulé : vous êtes resté sur Athena. Aucune page externe n’a été ouverte.',
            retry: 'Recommencer',
            close: 'Fermer',
            remember: function (h) { return 'Ne plus me demander pour ' + h + ' pendant cette session'; },
            flags: 'À vérifier',
            safeNote: 'Aucun signal d’alerte repéré. Cela ne prouve pas que le site est sûr : vérifiez qu’il est bien celui que vous attendez.',
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
            left: function (n) { return 'You have ' + n + ' s to confirm. With no answer, you stay on Athena.'; },
            expired: 'Time is up: you stayed on Athena. No external page was opened.',
            retry: 'Try again',
            close: 'Close',
            remember: function (h) { return 'Do not ask again for ' + h + ' during this session'; },
            flags: 'Worth checking',
            safeNote: 'No warning sign found. That does not prove the site is safe: make sure it is the one you expect.',
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
            '.athena-gate{position:fixed;inset:0;z-index:2147483000;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.62);font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;--ag-bg:#fff;--ag-fg:#0f172a;--ag-mut:#475569;--ag-line:#e2e8f0;--ag-soft:#f1f5f9;--ag-pri:#0f172a;--ag-prif:#fff;--ag-warn:#b45309;--ag-warnbg:#fffbeb;--ag-bad:#b91c1c}',
            '@media (prefers-color-scheme:dark){.athena-gate{--ag-bg:#0f172a;--ag-fg:#f1f5f9;--ag-mut:#94a3b8;--ag-line:#1e293b;--ag-soft:#1e293b;--ag-pri:#f1f5f9;--ag-prif:#0f172a;--ag-warn:#fbbf24;--ag-warnbg:#2a2110;--ag-bad:#f87171}}',
            ':root[data-theme="dark"] .athena-gate,.dark .athena-gate{--ag-bg:#0f172a;--ag-fg:#f1f5f9;--ag-mut:#94a3b8;--ag-line:#1e293b;--ag-soft:#1e293b;--ag-pri:#f1f5f9;--ag-prif:#0f172a;--ag-warn:#fbbf24;--ag-warnbg:#2a2110;--ag-bad:#f87171}',
            '.athena-gate__box{width:100%;max-width:520px;max-height:calc(100vh - 32px);overflow:auto;background:var(--ag-bg);color:var(--ag-fg);border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.35);padding:20px}',
            '.athena-gate h2{margin:0 0 4px;font-size:18px;font-weight:800;color:var(--ag-fg)}',
            '.athena-gate p{margin:0 0 12px;color:var(--ag-mut)}',
            '.athena-gate__addr{background:var(--ag-soft);border-radius:10px;padding:10px 12px;margin:0 0 12px;word-break:break-all;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:13px;color:var(--ag-mut)}',
            '.athena-gate__addr b{color:var(--ag-fg);font-weight:800}',
            '.athena-gate__addr .is-bad{color:var(--ag-bad);font-weight:800}',
            '.athena-gate dl{display:grid;grid-template-columns:auto 1fr;gap:2px 12px;margin:0 0 12px;font-size:12.5px}',
            '.athena-gate dt{color:var(--ag-mut)}',
            '.athena-gate dd{margin:0;word-break:break-all;color:var(--ag-fg)}',
            '.athena-gate__flags{margin:0 0 12px;padding:10px 12px 10px 28px;border-radius:10px;background:var(--ag-warnbg);color:var(--ag-warn);font-size:13px}',
            '.athena-gate__ok{font-size:12.5px;color:var(--ag-mut);margin:0 0 12px}',
            '.athena-gate details{margin:0 0 12px;border:1px solid var(--ag-line);border-radius:10px;padding:8px 12px;font-size:13px}',
            '.athena-gate summary{cursor:pointer;font-weight:700;color:var(--ag-fg)}',
            '.athena-gate details ul{margin:8px 0 0;padding-left:18px;color:var(--ag-mut)}',
            '.athena-gate details li{margin:0 0 6px}',
            '.athena-gate__bar{height:6px;border-radius:99px;background:var(--ag-soft);overflow:hidden;margin:0 0 6px}',
            '.athena-gate__bar i{display:block;height:100%;width:100%;background:var(--ag-pri)}',
            '.athena-gate__time{font-size:12.5px;color:var(--ag-mut);margin:0 0 12px;min-height:1.5em}',
            '.athena-gate__rem{display:flex;gap:8px;align-items:center;font-size:12.5px;color:var(--ag-mut);margin:0 0 12px}',
            '.athena-gate__acts{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}',
            '.athena-gate button{font:inherit;font-weight:700;border-radius:9px;padding:9px 14px;cursor:pointer;border:1px solid var(--ag-line);background:var(--ag-bg);color:var(--ag-fg)}',
            '.athena-gate button.is-pri{background:var(--ag-pri);color:var(--ag-prif);border-color:var(--ag-pri)}',
            '.athena-gate button:focus-visible{outline:3px solid #2563eb;outline-offset:2px}',
            '.athena-gate[hidden]{display:none}'
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

        var overlay = el('div', 'athena-gate');
        overlay.setAttribute('role', 'presentation');
        var box = el('div', 'athena-gate__box');
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.setAttribute('aria-labelledby', 'athena-gate-title');
        var h = el('h2', null, t.title);
        h.id = 'athena-gate-title';
        box.appendChild(h);
        box.appendChild(el('p', null, t.lead));

        // Adresse décomposée : protocole, site réel (en gras), reste.
        var addr = el('div', 'athena-gate__addr');
        addr.setAttribute('title', info.url);
        var prefix = info.host.length > info.registrable.length && info.host.slice(-info.registrable.length) === info.registrable
            ? info.host.slice(0, info.host.length - info.registrable.length) : '';
        addr.appendChild(el('span', info.protocol === 'http://' ? 'is-bad' : null, info.protocol));
        if (prefix) { addr.appendChild(document.createTextNode(prefix)); }
        addr.appendChild(el('b', null, info.registrable));
        var shown = info.path.length > 120 ? info.path.slice(0, 117) + '…' : info.path;
        addr.appendChild(document.createTextNode(shown));
        box.appendChild(addr);

        var dl = el('dl');
        var rows = [[t.proto, info.protocol.replace('://', '')], [t.site, info.registrable], [t.path, info.path ? shown : t.none]];
        for (var i = 0; i < rows.length; i++) {
            dl.appendChild(el('dt', null, rows[i][0]));
            dl.appendChild(el('dd', null, rows[i][1]));
        }
        box.appendChild(dl);

        if (info.flags.length) {
            var ul = el('ul', 'athena-gate__flags');
            ul.setAttribute('aria-label', t.flags);
            for (var f = 0; f < info.flags.length; f++) { ul.appendChild(el('li', null, info.flags[f])); }
            box.appendChild(ul);
        } else {
            box.appendChild(el('p', 'athena-gate__ok', t.safeNote));
        }

        var det = el('details');
        det.appendChild(el('summary', null, t.guideTitle));
        var gl = el('ul');
        for (var g = 0; g < t.guide.length; g++) { gl.appendChild(el('li', null, t.guide[g])); }
        det.appendChild(gl);
        box.appendChild(det);

        var bar = el('div', 'athena-gate__bar');
        bar.setAttribute('aria-hidden', 'true');
        var fill = el('i');
        bar.appendChild(fill);
        box.appendChild(bar);
        var status = el('p', 'athena-gate__time');
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        box.appendChild(status);

        var remRow = null;
        var remBox = null;
        if (!info.flags.length) {
            remRow = el('label', 'athena-gate__rem');
            remBox = el('input');
            remBox.type = 'checkbox';
            remRow.appendChild(remBox);
            remRow.appendChild(document.createTextNode(t.remember(info.registrable)));
            box.appendChild(remRow);
        }

        var acts = el('div', 'athena-gate__acts');
        var stay = el('button', null, t.stay);
        stay.type = 'button';
        var go = el('button', 'is-pri', t.go);
        go.type = 'button';
        acts.appendChild(stay);
        acts.appendChild(go);
        box.appendChild(acts);
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
            fill.style.width = '0%';
            status.textContent = t.expired;
            go.textContent = t.retry;
            go.onclick = function () { destroy(); confirmLeave(info, opts); };
            stay.textContent = t.close;
            if (remRow) { remRow.style.display = 'none'; }
            stay.focus();
        }
        function tick() {
            var elapsed = (Date.now() - started) / 1000;
            var left = Math.max(0, DELAY - elapsed);
            fill.style.width = (left / DELAY * 100) + '%';
            var secs = Math.ceil(left);
            if (secs !== tick.last) { tick.last = secs; status.textContent = t.left(secs); }
            if (left <= 0) { expire(); }
        }
        function onKey(e) {
            if (e.key === 'Escape' || e.keyCode === 27) { e.preventDefault(); destroy(); return; }
            if (e.key !== 'Tab' && e.keyCode !== 9) { return; }
            var focusables = box.querySelectorAll('button, summary, input');
            var visible = [];
            for (var k = 0; k < focusables.length; k++) { if (focusables[k].offsetParent !== null) { visible.push(focusables[k]); } }
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
