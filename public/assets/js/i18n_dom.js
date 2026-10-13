/*
 * Traduction anglaise des textes écrits par le JavaScript des pages (visiteur en anglais uniquement).
 *
 * Le serveur traduit la page HTML (App\Support\HtmlTranslator) ; ce script reprend la même idée pour ce que les
 * scripts ajoutent ensuite : textes insérés dans la page, title/placeholder/aria-label, alert/confirm/prompt.
 * Dictionnaire : window.ATHENA_I18N_EN = { exact: {fr: en}, tpl: [[fr avec %1…, en]] }, généré par
 * `python3 tools/i18n/site_i18n.py js-bundle` (assets/js/i18n_en_dict.js). Un texte inconnu reste en français.
 */
(function () {
    'use strict';
    var dict = window.ATHENA_I18N_EN;
    if (!dict || document.documentElement.lang !== 'en') {
        return;
    }
    var exact = dict.exact || {};
    var tpls = [];
    (dict.tpl || []).forEach(function (pair) {
        var ids = [];
        var src = pair[0].replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/%(\d)/g, function (_m, d) {
            ids.push(d);
            return '(.+?)';
        });
        var letters = pair[0].replace(/%\d/g, '').replace(/[^A-Za-zÀ-ÿ]/g, '').length;
        try {
            tpls.push({ re: new RegExp('^' + src + '$'), ids: ids, en: pair[1], weak: letters < 5 });
        } catch (e) { /* modèle ignoré */ }
    });
    tpls.sort(function (a, b) { return b.re.source.length - a.re.source.length; });

    var memo = Object.create(null);
    function lookup(s, depth) {
        if (!s || depth > 2) {
            return null;
        }
        if (depth === 0 && s in memo) {
            return memo[s];
        }
        var r = Object.prototype.hasOwnProperty.call(exact, s) ? exact[s] : null;
        if (r === null) {
            var k = s.indexOf(' : ');
            if (k > 0 && Object.prototype.hasOwnProperty.call(exact, s.slice(0, k))) {
                var rest = s.slice(k + 3);
                r = exact[s.slice(0, k)] + ': ' + (lookup(rest, depth + 1) || rest);
            }
        }
        for (var i = 0; r === null && i < tpls.length; i++) {
            var t = tpls[i];
            var m = t.re.exec(s);
            if (!m) {
                continue;
            }
            var caps = m.slice(1);
            if (t.weak && caps.some(function (c) { return c.trim().split(/\s+/).length > 3; })) {
                continue;
            }
            var out = t.en;
            t.ids.forEach(function (id, j) {
                var c = caps[j];
                out = out.split('%' + id).join(lookup(c.trim(), depth + 1) || c);
            });
            r = out;
        }
        if (depth === 0) {
            memo[s] = r;
        }
        return r;
    }

    function tr(text) {
        if (!text || !/[A-Za-zÀ-ÿ]/.test(text)) {
            return null;
        }
        var lead = text.match(/^\s*/)[0];
        var trail = text.match(/\s*$/)[0];
        var core = text.trim().replace(/\s+/g, ' ');
        var en = lookup(core, 0);
        return en === null ? null : lead + en + trail;
    }

    var SKIP = 'script,style,textarea,pre,code,[translate="no"],[contenteditable="true"]';
    var ATTRS = ['title', 'placeholder', 'aria-label', 'data-confirm'];
    var busy = false;

    function translateNode(root) {
        if (root.nodeType === 3) {
            var p = root.parentElement;
            if (p && !p.closest(SKIP)) {
                var en = tr(root.nodeValue);
                if (en !== null && en !== root.nodeValue) {
                    root.nodeValue = en;
                }
            }
            return;
        }
        if (root.nodeType !== 1 || root.closest(SKIP)) {
            return;
        }
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        var n;
        var list = [];
        while ((n = walker.nextNode())) {
            list.push(n);
        }
        list.forEach(translateNode);
        var els = [root].concat(Array.prototype.slice.call(root.querySelectorAll('[title],[placeholder],[aria-label],[data-confirm],input[type=submit],input[type=button]')));
        els.forEach(function (el) {
            if (el.closest(SKIP)) {
                return;
            }
            ATTRS.forEach(function (a) {
                var v = el.getAttribute(a);
                var en = v ? tr(v) : null;
                if (en !== null && en !== v) {
                    el.setAttribute(a, en);
                }
            });
            if (el.tagName === 'INPUT' && (el.type === 'submit' || el.type === 'button')) {
                var bv = tr(el.value);
                if (bv !== null) {
                    el.value = bv;
                }
            }
        });
    }

    function run(fn) {
        if (busy) {
            return;
        }
        busy = true;
        try {
            fn();
        } finally {
            busy = false;
        }
    }

    // Boîtes de dialogue du navigateur.
    ['alert', 'confirm', 'prompt'].forEach(function (name) {
        var orig = window[name];
        if (typeof orig !== 'function') {
            return;
        }
        window[name] = function (msg) {
            var args = Array.prototype.slice.call(arguments);
            if (typeof msg === 'string') {
                args[0] = msg.split('\n').map(function (l) { return tr(l) || l; }).join('\n');
            }
            return orig.apply(window, args);
        };
    });

    function start() {
        run(function () { translateNode(document.body); });
        new MutationObserver(function (records) {
            run(function () {
                records.forEach(function (rec) {
                    if (rec.type === 'characterData') {
                        translateNode(rec.target);
                    } else if (rec.type === 'attributes') {
                        translateNode(rec.target.nodeType === 1 ? rec.target : rec.target.parentNode);
                    } else {
                        Array.prototype.forEach.call(rec.addedNodes, translateNode);
                    }
                });
            });
        }).observe(document.body, {
            childList: true, subtree: true, characterData: true,
            attributes: true, attributeFilter: ATTRS
        });
    }

    if (document.body) {
        start();
    } else {
        document.addEventListener('DOMContentLoaded', start);
    }
}());
