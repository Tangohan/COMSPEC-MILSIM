/**
 * Bureau SSE — comportements de la coque, sans dépendance :
 *  - tiroir de navigation (mobile) et sections repliables mémorisées ;
 *  - palette de commandes (Ctrl+K / ⌘K) ;
 *  - heure Zulu et temps de session restant, en direct ;
 *  - texte brouillé puis « déchiffré » sur les titres ([data-scramble], titre de page) ;
 *  - rideaux de transition entre les pages du bureau ;
 *  - galerie 3D des pièces (photos de dossier, pièces jointes de fiche).
 * Tout est désactivé ou simplifié si l'utilisateur demande moins d'animations.
 */
(function () {
    'use strict';

    var reduce = false;
    try { reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}
    var app = document.querySelector('[data-sse-shell]');
    var store = {
        get: function (k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } },
        set: function (k, v) { try { window.localStorage.setItem(k, v); } catch (e) {} },
        sget: function (k) { try { return window.sessionStorage.getItem(k); } catch (e) { return null; } },
        sset: function (k, v) { try { window.sessionStorage.setItem(k, v); } catch (e) {} },
        sdel: function (k) { try { window.sessionStorage.removeItem(k); } catch (e) {} }
    };
    function norm(s) {
        return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    /* ---------------------------------------------------------------- Texte brouillé */
    var GLYPHS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789#%&/<>=+';
    function scramble(el, opts) {
        if (!el || reduce) return;
        var target = el.getAttribute('data-scramble-text') || el.textContent;
        if (!target || target.trim() === '' || el.children.length > 0) return;
        el.setAttribute('data-scramble-text', target);
        if (!el.hasAttribute('aria-label')) el.setAttribute('aria-label', target.trim());
        var duration = (opts && opts.duration) || Math.min(900, 260 + target.length * 22);
        var t0 = null;
        var chars = target.split('');
        var order = chars.map(function (_, i) { return (i / Math.max(1, chars.length - 1)) * 0.75 + Math.random() * 0.25; });
        function frame(ts) {
            if (t0 === null) t0 = ts;
            var p = Math.min(1, (ts - t0) / duration);
            var out = '';
            for (var i = 0; i < chars.length; i++) {
                var c = chars[i];
                if (c === ' ' || p >= order[i]) {
                    out += c;
                } else {
                    out += GLYPHS.charAt(Math.floor(Math.random() * GLYPHS.length));
                }
            }
            el.textContent = out;
            if (p < 1) {
                window.requestAnimationFrame(frame);
            } else {
                el.textContent = target;
            }
        }
        window.requestAnimationFrame(frame);
    }
    window.SseScramble = scramble;

    function scrambleOnLoad() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-scramble]'), function (el) { scramble(el); });
        // Titre principal de la page : premier h1 sans balisage interne.
        var main = document.querySelector('.iw-main');
        var h1 = main ? main.querySelector('h1') : null;
        if (h1 && h1.children.length === 0 && h1.textContent.trim().length <= 60) scramble(h1);
    }

    /* ---------------------------------------------------------------- Navigation */
    function initNav() {
        if (!app) return;
        var toggle = app.querySelector('[data-sx-nav-toggle]');
        var scrim = app.querySelector('[data-sx-nav-close]');
        function setOpen(open) {
            app.classList.toggle('is-nav-open', open);
            if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (scrim) scrim.hidden = !open;
        }
        if (toggle) toggle.addEventListener('click', function () { setOpen(!app.classList.contains('is-nav-open')); });
        if (scrim) scrim.addEventListener('click', function () { setOpen(false); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && app.classList.contains('is-nav-open')) setOpen(false);
        });
        // Sections repliables : l'état choisi par l'utilisateur est mémorisé.
        var saved = {};
        try { saved = JSON.parse(store.get('sse.nav.groups') || '{}') || {}; } catch (e) { saved = {}; }
        Array.prototype.forEach.call(app.querySelectorAll('[data-sx-group]'), function (g) {
            var id = g.getAttribute('data-sx-group');
            var hasActive = !!g.querySelector('.is-active');
            if (!hasActive && Object.prototype.hasOwnProperty.call(saved, id)) g.open = !!saved[id];
            g.addEventListener('toggle', function () {
                saved[id] = g.open;
                store.set('sse.nav.groups', JSON.stringify(saved));
            });
        });
        // Menu « Nouveau » : se ferme au clic dehors.
        document.addEventListener('click', function (e) {
            Array.prototype.forEach.call(document.querySelectorAll('.sx-new[open]'), function (d) {
                if (!d.contains(e.target)) d.open = false;
            });
        });
    }

    /* ---------------------------------------------------------------- Heure Zulu / session */
    function initClock() {
        var zulu = document.querySelector('[data-sx-zulu]');
        var left = document.querySelector('[data-sx-countdown]');
        var until = left ? parseInt(left.getAttribute('data-sx-countdown'), 10) || 0 : 0;
        function pad(n) { return (n < 10 ? '0' : '') + n; }
        function tick() {
            var d = new Date();
            if (zulu) zulu.textContent = pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()) + ':' + pad(d.getUTCSeconds()) + 'Z';
            if (left && until > 0) {
                var s = until - Math.floor(Date.now() / 1000);
                if (s <= 0) {
                    left.textContent = 'expirée';
                    left.classList.add('is-low');
                } else {
                    var h = Math.floor(s / 3600);
                    var m = Math.floor((s % 3600) / 60);
                    left.textContent = h > 0 ? h + 'h ' + pad(m) : Math.max(1, m) + ' min';
                    left.classList.toggle('is-low', s < 900);
                }
            }
        }
        tick();
        window.setInterval(tick, 1000);
    }

    /* ---------------------------------------------------------------- Rideaux */
    var CURTAIN_KEY = 'sse.curtain';
    function makeCurtains(held) {
        var n = Math.max(6, Math.min(14, Math.round(window.innerWidth / 120)));
        var wrap = document.createElement('div');
        wrap.className = 'sx-curtains' + (held ? ' is-held is-active' : '');
        wrap.setAttribute('aria-hidden', 'true');
        for (var i = 0; i < n; i++) {
            var c = document.createElement('span');
            c.className = 'sx-curtain';
            c.style.setProperty('--i', String(i));
            wrap.appendChild(c);
        }
        var label = document.createElement('span');
        label.className = 'sx-curtains__label';
        label.textContent = 'DÉCHIFFREMENT';
        document.body.appendChild(wrap);
        document.body.appendChild(label);
        return { wrap: wrap, label: label, n: n };
    }
    function eligible(a, e) {
        if (!a || reduce) return false;
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return false;
        if (a.target && a.target !== '_self') return false;
        if (a.hasAttribute('download') || a.hasAttribute('data-no-curtain') || a.closest('[data-no-curtain]')) return false;
        var href = a.getAttribute('href') || '';
        if (href === '' || href.charAt(0) === '#' || /^(mailto:|tel:|javascript:)/i.test(href)) return false;
        var url;
        try { url = new URL(a.href, window.location.href); } catch (err) { return false; }
        if (url.origin !== window.location.origin) return false;
        if (url.pathname.indexOf('/atak/sse') === -1) return false;
        if (/\/(pdf|flux|emport|export|texte)(\/|$)|\.pdf$/i.test(url.pathname)) return false;
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return false;
        return true;
    }
    function coverThen(go) {
        var c = makeCurtains(false);
        c.wrap.classList.add('is-active');
        // Force le recalcul avant d'animer.
        void c.wrap.offsetWidth;
        c.wrap.classList.add('is-cover');
        var total = 340 + c.n * 32;
        store.sset(CURTAIN_KEY, String(Date.now()));
        window.setTimeout(go, total);
        // Si la page ne change pas (téléchargement, refus serveur…), on relève le rideau.
        window.setTimeout(function () {
            if (document.visibilityState === 'visible' && c.wrap.isConnected) {
                store.sdel(CURTAIN_KEY);
                reveal(c);
            }
        }, 6000);
    }
    function reveal(c) {
        c.wrap.classList.remove('is-cover', 'is-held');
        void c.wrap.offsetWidth;
        c.wrap.classList.add('is-reveal');
        window.setTimeout(function () {
            if (c.wrap.parentNode) c.wrap.parentNode.removeChild(c.wrap);
            if (c.label.parentNode) c.label.parentNode.removeChild(c.label);
        }, 460 + c.n * 32);
    }
    function initCurtains() {
        // Arrivée après une transition : le rideau posé au chargement (script en tête de page) se lève.
        var held = document.querySelector('.sx-curtains.is-held');
        var label = document.querySelector('.sx-curtains__label');
        store.sdel(CURTAIN_KEY);
        if (held) {
            var n = held.children.length;
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () { reveal({ wrap: held, label: label || document.createElement('span'), n: n }); });
            });
        }
        if (reduce) return;
        document.addEventListener('click', function (e) {
            var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
            if (!eligible(a, e)) return;
            e.preventDefault();
            var href = a.href;
            coverThen(function () { window.location.href = href; });
        });
        // Formulaires d'entrée (sas, engagement) : le rideau tombe, puis le formulaire part.
        Array.prototype.forEach.call(document.querySelectorAll('form[data-curtain-submit]'), function (form) {
            form.addEventListener('submit', function (e) {
                if (form.__sxSending || e.defaultPrevented || (form.checkValidity && !form.checkValidity())) return;
                e.preventDefault();
                form.__sxSending = true;
                coverThen(function () { HTMLFormElement.prototype.submit.call(form); });
            });
        });
        // Retour arrière (cache du navigateur) : aucun rideau ne doit rester posé.
        window.addEventListener('pageshow', function (ev) {
            if (ev.persisted) {
                Array.prototype.forEach.call(document.querySelectorAll('.sx-curtains, .sx-curtains__label'), function (el) { el.remove(); });
            }
        });
    }
    window.SseCurtains = { go: function (href) { if (reduce) { window.location.href = href; return; } coverThen(function () { window.location.href = href; }); } };

    /* ---------------------------------------------------------------- Palette */
    function initPalette() {
        var dlg = document.getElementById('sx-palette');
        var input = document.getElementById('sx-palette-q');
        var list = document.getElementById('sx-palette-list');
        var dataEl = document.getElementById('sx-palette-data');
        if (!dlg || !input || !list || !dataEl || typeof dlg.showModal !== 'function') return;
        var entries = [];
        try { entries = JSON.parse(dataEl.textContent || '[]') || []; } catch (e) { entries = []; }
        entries.forEach(function (en) { en._hay = norm(en.label + ' ' + en.hint + ' ' + en.group + ' ' + en.keywords); });
        var shown = [];
        var active = 0;

        function esc(s) {
            return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
        }
        function hl(label, terms) {
            var safe = esc(label);
            if (!terms.length) return safe;
            var n = norm(label);
            var t = terms[0];
            var i = n.indexOf(t);
            if (i < 0) return safe;
            return esc(label.slice(0, i)) + '<mark>' + esc(label.slice(i, i + t.length)) + '</mark>' + esc(label.slice(i + t.length));
        }
        function render() {
            var q = norm(input.value).trim();
            var terms = q ? q.split(/\s+/) : [];
            shown = entries.filter(function (en) { return terms.every(function (t) { return en._hay.indexOf(t) !== -1; }); });
            if (terms.length) {
                shown.sort(function (a, b) {
                    var ai = norm(a.label).indexOf(terms[0]);
                    var bi = norm(b.label).indexOf(terms[0]);
                    return (ai < 0 ? 99 : ai) - (bi < 0 ? 99 : bi);
                });
            }
            active = Math.min(active, Math.max(0, shown.length - 1));
            var html = '';
            var last = '';
            shown.forEach(function (en, i) {
                if (!terms.length && en.group !== last) {
                    html += '<li class="sx-palette__group" role="presentation">' + esc(en.group) + '</li>';
                    last = en.group;
                }
                html += '<li class="sx-palette__item' + (i === active ? ' is-active' : '') + '" role="option" aria-selected="' + (i === active ? 'true' : 'false') + '" data-i="' + i + '">'
                    + '<a href="' + esc(en.href) + '"><strong>' + hl(en.label, terms) + '</strong><span>' + esc(en.hint) + '</span><em>' + esc(en.group) + '</em></a></li>';
            });
            list.innerHTML = html || '<li class="sx-palette__empty">Aucune page ne correspond. Entrée lance une recherche dans le bureau.</li>';
        }
        function move(d) {
            if (!shown.length) return;
            active = (active + d + shown.length) % shown.length;
            render();
            var el = list.querySelector('.is-active');
            if (el && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
        }
        function open() {
            input.value = '';
            active = 0;
            render();
            dlg.showModal();
            input.focus();
        }
        function go(href) {
            dlg.close();
            if (window.SseCurtains) window.SseCurtains.go(href); else window.location.href = href;
        }
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                if (dlg.open) dlg.close(); else open();
            }
        });
        Array.prototype.forEach.call(document.querySelectorAll('[data-sx-palette-open]'), function (b) { b.addEventListener('click', open); });
        input.addEventListener('input', function () { active = 0; render(); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
            else if (e.key === 'Enter') {
                e.preventDefault();
                if (shown[active]) go(shown[active].href);
                else if (input.value.trim()) {
                    var form = document.getElementById('iw-search-form');
                    var base = form ? form.getAttribute('action') : '';
                    if (base) go(base + '?q=' + encodeURIComponent(input.value.trim()));
                }
            }
        });
        list.addEventListener('click', function (e) {
            var a = e.target.closest('a');
            if (!a) return;
            e.preventDefault();
            go(a.getAttribute('href'));
        });
        dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
    }

    /* ---------------------------------------------------------------- Galerie 3D */
    function collectItems(root) {
        var items = [];
        Array.prototype.forEach.call(root.querySelectorAll('img'), function (img) {
            var src = img.getAttribute('data-gallery-src') || img.currentSrc || img.src;
            if (!src || img.closest('.is-blurred')) return;
            var fig = img.closest('figure, li, .sse-ev-card, a');
            var cap = img.getAttribute('data-caption') || '';
            var ref = '';
            if (fig) {
                var strong = fig.querySelector('figcaption strong, strong');
                var span = fig.querySelector('figcaption span, figcaption, .sse-ev-card__title');
                ref = strong ? strong.textContent.trim() : '';
                if (!cap && span) cap = span.textContent.replace(ref, '').trim();
            }
            if (!cap) cap = img.getAttribute('alt') || '';
            items.push({ src: src, caption: cap, ref: ref, img: img });
        });
        return items;
    }

    function Gallery(items, title) {
        this.items = items;
        this.title = title || 'Pièces';
        this.index = 0;
        this.zoom = false;
        this.build();
    }
    Gallery.prototype.build = function () {
        var self = this;
        var root = document.createElement('div');
        root.className = 'sx-gal';
        root.hidden = true;
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-label', this.title);
        root.innerHTML = '<div class="sx-gal__bar"><p class="sx-gal__title"></p><span class="sx-gal__count"></span>'
            + '<button type="button" class="sx-gal__close" aria-label="Fermer la galerie">×</button></div>'
            + '<div class="sx-gal__viewport" tabindex="0" role="region" aria-roledescription="galerie"><ul class="sx-gal__stage" role="listbox"></ul>'
            + '<button type="button" class="sx-gal__nav sx-gal__nav--prev" aria-label="Pièce précédente">‹</button>'
            + '<button type="button" class="sx-gal__nav sx-gal__nav--next" aria-label="Pièce suivante">›</button>'
            + '<div class="sx-gal__detail" aria-live="polite"><strong></strong><span></span></div></div>'
            + '<div class="sx-gal__strip" role="tablist" aria-label="Miniatures"></div>';
        root.querySelector('.sx-gal__title').textContent = this.title;
        var stage = root.querySelector('.sx-gal__stage');
        var strip = root.querySelector('.sx-gal__strip');
        this.nodes = [];
        this.thumbs = [];
        this.items.forEach(function (it, i) {
            var li = document.createElement('li');
            li.className = 'sx-gal__item';
            li.setAttribute('role', 'option');
            li.innerHTML = '<div class="sx-gal__plane"><img alt="" draggable="false"><span class="sx-gal__idx"></span></div>';
            var im = li.querySelector('img');
            im.src = it.src;
            im.alt = it.caption || it.ref || ('Pièce ' + (i + 1));
            im.addEventListener('load', function () {
                if (im.naturalWidth && im.naturalHeight) li.style.setProperty('--ar', String(im.naturalWidth / im.naturalHeight));
            });
            li.querySelector('.sx-gal__idx').textContent = (i < 10 ? '0' : '') + i;
            li.addEventListener('click', function () {
                if (self.moved) return;
                if (i === self.index) { self.zoom = !self.zoom; self.layout(); } else { self.go(i); }
            });
            stage.appendChild(li);
            self.nodes.push(li);
            var tb = document.createElement('button');
            tb.type = 'button';
            tb.className = 'sx-gal__thumb';
            tb.setAttribute('aria-label', 'Pièce ' + (i + 1) + (it.caption ? ' : ' + it.caption : ''));
            tb.innerHTML = '<img alt="">';
            tb.querySelector('img').src = it.src;
            tb.addEventListener('click', function () { self.go(i); });
            strip.appendChild(tb);
            self.thumbs.push(tb);
        });
        root.querySelector('.sx-gal__close').addEventListener('click', function () { self.close(); });
        root.querySelector('.sx-gal__nav--prev').addEventListener('click', function () { self.go(self.index - 1); });
        root.querySelector('.sx-gal__nav--next').addEventListener('click', function () { self.go(self.index + 1); });
        var vp = root.querySelector('.sx-gal__viewport');
        this.onKey = function (e) {
            if (root.hidden) return;
            if (e.key === 'Escape') { e.preventDefault(); self.close(); }
            else if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); self.go(self.index + 1); }
            else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { e.preventDefault(); self.go(self.index - 1); }
            else if (e.key === 'Home') { e.preventDefault(); self.go(0); }
            else if (e.key === 'End') { e.preventDefault(); self.go(self.items.length - 1); }
            else if (e.key === 'Enter' || e.key === ' ') {
                if (document.activeElement === vp) { e.preventDefault(); self.zoom = !self.zoom; self.layout(); }
            }
        };
        var wheelLock = 0;
        vp.addEventListener('wheel', function (e) {
            e.preventDefault();
            var now = Date.now();
            if (now - wheelLock < 260) return;
            wheelLock = now;
            self.go(self.index + ((e.deltaY || e.deltaX) > 0 ? 1 : -1));
        }, { passive: false });
        var x0 = null;
        vp.addEventListener('pointerdown', function (e) { x0 = e.clientX; self.moved = false; });
        vp.addEventListener('pointermove', function (e) {
            if (x0 === null) return;
            var dx = e.clientX - x0;
            if (Math.abs(dx) > 50) {
                self.moved = true;
                self.go(self.index + (dx < 0 ? 1 : -1));
                x0 = e.clientX;
            }
        });
        window.addEventListener('pointerup', function () { x0 = null; window.setTimeout(function () { self.moved = false; }, 0); });
        window.addEventListener('resize', function () { if (!root.hidden) self.layout(); });
        this.root = root;
        this.vp = vp;
        document.body.appendChild(root);
    };
    Gallery.prototype.layout = function () {
        var w = this.nodes[0] ? this.nodes[0].getBoundingClientRect().width || 360 : 360;
        var self = this;
        this.nodes.forEach(function (li, i) {
            var k = i - self.index;
            var t;
            var op = 1;
            if (k === 0) {
                t = self.zoom
                    ? 'translate(-50%, -50%) translate3d(0, 0, 220px) rotateY(0deg) scale(1.25)'
                    : 'translate(-50%, -50%) translate3d(0, 4%, 0) rotateY(-24deg) scale(1.04)';
            } else {
                // Escalier de plans comme sur une table lumineuse : à droite et en profondeur.
                t = 'translate(-50%, -50%) translate3d(' + (k * w * 0.58) + 'px, ' + (k * -12) + '%, ' + (-Math.abs(k) * 300) + 'px) rotateY(-50deg)';
                if (k < -2 || k > 4) op = 0;
                if (self.zoom) op = 0;
            }
            li.style.transform = t;
            li.style.opacity = String(op);
            li.style.zIndex = String(100 - Math.abs(k));
            li.style.pointerEvents = op === 0 ? 'none' : '';
            li.style.filter = k === 0 ? '' : 'brightness(' + Math.max(0.35, 1 - Math.abs(k) * 0.16) + ')';
            li.classList.toggle('is-active', k === 0);
            li.setAttribute('aria-selected', k === 0 ? 'true' : 'false');
        });
        this.thumbs.forEach(function (tb, i) { tb.classList.toggle('is-active', i === self.index); });
        var it = this.items[this.index] || {};
        var det = this.root.querySelector('.sx-gal__detail');
        det.querySelector('strong').textContent = it.ref || ('PIÈCE ' + (this.index < 9 ? '0' : '') + (this.index + 1));
        var cap = det.querySelector('span');
        cap.textContent = it.caption || '';
        cap.removeAttribute('data-scramble-text');
        cap.removeAttribute('aria-label');
        scramble(cap, { duration: 420 });
        this.root.querySelector('.sx-gal__count').textContent = (this.index + 1) + ' / ' + this.items.length;
        var active = this.thumbs[this.index];
        if (active && active.scrollIntoView) active.scrollIntoView({ block: 'nearest', inline: 'center' });
    };
    Gallery.prototype.go = function (i) {
        var n = this.items.length;
        this.index = Math.max(0, Math.min(n - 1, i));
        this.zoom = false;
        this.layout();
    };
    Gallery.prototype.open = function (i, opener) {
        this.opener = opener || document.activeElement;
        this.root.hidden = false;
        document.documentElement.style.overflow = 'hidden';
        document.addEventListener('keydown', this.onKey);
        this.index = i || 0;
        var self = this;
        this.layout();
        window.requestAnimationFrame(function () { self.root.classList.add('is-open'); self.vp.focus(); self.layout(); });
    };
    Gallery.prototype.close = function () {
        this.root.classList.remove('is-open');
        this.root.hidden = true;
        document.documentElement.style.overflow = '';
        document.removeEventListener('keydown', this.onKey);
        if (this.opener && this.opener.focus) this.opener.focus();
    };

    function initGalleries() {
        var roots = Array.prototype.slice.call(document.querySelectorAll('[data-sse-gallery], .sse-ev-grid, .sse-reading__atts'));
        var seen = [];
        roots.forEach(function (root) {
            if (seen.indexOf(root) !== -1) return;
            seen.push(root);
            var items = collectItems(root);
            if (items.length === 0) return;
            var title = root.getAttribute('data-gallery-title') || (document.querySelector('.iw-main h1') || {}).textContent || 'Pièces';
            var gal = null;
            function ensure() { if (!gal) gal = new Gallery(items, String(title).trim()); return gal; }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'sx-gal-open';
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="13" height="14" rx="1.5" transform="skewY(-8)"/><path d="M18 7v12M21 9v8"/></svg>'
                + 'Parcourir en galerie (' + items.length + ')';
            btn.addEventListener('click', function () { ensure().open(0, btn); });
            // Le bouton n'apparaît qu'à partir de deux pièces ; une seule s'ouvre d'un clic sur l'image.
            if (items.length >= 2) root.parentNode.insertBefore(btn, root);
            items.forEach(function (it, i) {
                // Un clic sur la vignette ouvre la galerie à cette pièce, sauf si la vignette est déjà un lien.
                if (it.img.closest('a[href]')) return;
                it.img.style.cursor = 'zoom-in';
                it.img.addEventListener('click', function () { ensure().open(i, it.img); });
            });
        });
    }

    function boot() {
        initNav();
        initClock();
        initPalette();
        initCurtains();
        initGalleries();
        scrambleOnLoad();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
