/**
 * Préférences cookies (localStorage v3) :
 * - nécessaires toujours actifs
 * - audience, personnalisation, publicité séparées
 * - expiration automatique du consentement
 * - synchronisation inter-onglets
 *
 * Événement : portalCookieConsent
 * detail { analytics, personalization, ads, essentialOnly, hasAnyOptional, source, ts }
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'athena_portal_cookie_prefs';
    var VERSION = 3;
    var CONSENT_TTL_DAYS = 180;
    var OPTIONAL_COOKIE_PREFIXES = ['_ga', '_gid', '_gat', '_fbp', '_gcl_'];

    function safeParse(raw) {
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function nowTs() {
        return Date.now();
    }

    function ttlMs() {
        return CONSENT_TTL_DAYS * 24 * 60 * 60 * 1000;
    }

    /**
     * @returns {{ analytics: boolean, personalization: boolean, ads: boolean } | null}
     */
    function readPrefs() {
        var raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return null;
        }
        var o = safeParse(raw);
        if (!o) {
            return null;
        }
        if (String(o.v) === '1' && o.choice === 'all') {
            return { analytics: true, personalization: true, ads: true };
        }
        if (String(o.v) === '1' && o.choice === 'essential') {
            return { analytics: false, personalization: false, ads: false };
        }
        if (String(o.v) === '2' && typeof o.analytics === 'boolean' && typeof o.ads === 'boolean') {
            return { analytics: o.analytics, personalization: o.ads, ads: o.ads };
        }
        if (
            String(o.v) === '3'
            && typeof o.analytics === 'boolean'
            && typeof o.personalization === 'boolean'
            && typeof o.ads === 'boolean'
        ) {
            var ts = Number(o.ts || 0);
            if (ts > 0 && (nowTs() - ts) > ttlMs()) {
                try {
                    localStorage.removeItem(STORAGE_KEY);
                } catch (e) { /* ignore */ }
                return null;
            }
            return {
                analytics: o.analytics,
                personalization: o.personalization,
                ads: o.ads,
            };
        }
        return null;
    }

    function hasConsent() {
        return readPrefs() !== null;
    }

    function buildDetail(prefs, source) {
        var detail = {
            analytics: !!prefs.analytics,
            personalization: !!prefs.personalization,
            ads: !!prefs.ads,
            source: source || 'unknown',
            ts: nowTs(),
        };
        detail.essentialOnly = !detail.analytics && !detail.personalization && !detail.ads;
        detail.hasAnyOptional = detail.analytics || detail.personalization || detail.ads;

        return detail;
    }

    function notifyConsent(prefs, source) {
        var detail = buildDetail(prefs, source);
        try {
            document.dispatchEvent(new CustomEvent('portalCookieConsent', { detail: detail }));
        } catch (e) { /* ignore */ }
    }

    function persist(analytics, personalization, ads, source) {
        var payload = {
            v: VERSION,
            analytics: !!analytics,
            personalization: !!personalization,
            ads: !!ads,
            ts: nowTs(),
        };
        localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        if (!payload.analytics && !payload.personalization && !payload.ads) {
            pruneKnownOptionalCookies();
        }
        notifyConsent(payload, source || 'persist');
        updateChoiceTimestamp(payload.ts);
    }

    function pruneKnownOptionalCookies() {
        var cookies = document.cookie ? document.cookie.split(';') : [];
        cookies.forEach(function (chunk) {
            var p = chunk.split('=');
            var rawName = p[0] || '';
            var name = rawName.replace(/^\s+|\s+$/g, '');
            if (!name) {
                return;
            }
            var shouldDelete = OPTIONAL_COOKIE_PREFIXES.some(function (prefix) {
                return name.indexOf(prefix) === 0;
            });
            if (!shouldDelete) {
                return;
            }
            document.cookie = name + '=; Max-Age=0; path=/; SameSite=Lax';
        });
    }

    function updateChoiceTimestamp(ts) {
        var el = document.getElementById('portal-cookie-last-choice');
        if (!el) {
            return;
        }
        if (!ts || isNaN(ts)) {
            el.textContent = 'Aucun choix enregistré';
            return;
        }
        try {
            var date = new Date(ts);
            el.textContent = 'Dernier choix : ' + date.toLocaleString();
        } catch (e) {
            el.textContent = 'Dernier choix enregistré';
        }
    }

    function setCustomizeExpanded(expanded) {
        var btn = document.getElementById('portal-cookie-customize');
        if (btn) {
            btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
    }

    function hidePanel() {
        var panel = document.getElementById('portal-cookie-panel');
        if (!panel) {
            return;
        }
        panel.classList.add('hidden');
        panel.setAttribute('hidden', '');
        panel.hidden = true;
        panel.setAttribute('aria-hidden', 'true');
        panel.style.display = '';
        setCustomizeExpanded(false);
    }

    function hideBanner() {
        var el = document.getElementById('portal-cookie-banner');
        if (el) {
            el.setAttribute('hidden', '');
            el.classList.add('hidden');
        }
        hidePanel();
    }

    function showBanner() {
        var el = document.getElementById('portal-cookie-banner');
        if (!el) {
            return;
        }
        el.removeAttribute('hidden');
        el.classList.remove('hidden');
    }

    function showPanel() {
        var panel = document.getElementById('portal-cookie-panel');
        if (!panel) {
            return;
        }
        // Toujours garder la bannière visible tant qu’on personnalise
        showBanner();
        panel.removeAttribute('hidden');
        panel.hidden = false;
        panel.classList.remove('hidden');
        panel.setAttribute('aria-hidden', 'false');
        panel.style.removeProperty('display');
        setCustomizeExpanded(true);
        var p = readPrefs();
        var aud = document.getElementById('portal-cookie-audience');
        var pers = document.getElementById('portal-cookie-personalization');
        var ads = document.getElementById('portal-cookie-ads');
        if (aud) {
            aud.checked = p ? !!p.analytics : false;
        }
        if (pers) {
            pers.checked = p ? !!p.personalization : false;
        }
        if (ads) {
            ads.checked = p ? !!p.ads : false;
        }
        requestAnimationFrame(function () {
            try {
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } catch (e) { /* ignore */ }
        });
    }

    function openPreferences() {
        showBanner();
        showPanel();
        try {
            var el = document.getElementById('portal-cookie-banner');
            if (el && el.scrollIntoView) {
                el.scrollIntoView({ behavior: 'smooth', block: 'end' });
            }
        } catch (e) { /* ignore */ }
    }

    function bindDelegatedClicks() {
        document.addEventListener('click', function (e) {
            var t = e.target && e.target.closest ? e.target.closest('[data-cookie-preferences]') : null;
            if (!t) {
                return;
            }
            e.preventDefault();
            openPreferences();
        });
    }

    function bindControls() {
        var accept = document.getElementById('portal-cookie-accept-all');
        var essential = document.getElementById('portal-cookie-essential-only');
        var customize = document.getElementById('portal-cookie-customize');
        var saveCustom = document.getElementById('portal-cookie-save-custom');
        var rejectAll = document.getElementById('portal-cookie-reject-all');
        var reset = document.getElementById('portal-cookie-reset');

        if (accept) {
            accept.addEventListener('click', function () {
                persist(true, true, true, 'accept_all');
                hideBanner();
            });
        }
        if (essential) {
            essential.addEventListener('click', function () {
                persist(false, false, false, 'essential_only');
                hideBanner();
            });
        }
        if (rejectAll) {
            rejectAll.addEventListener('click', function () {
                persist(false, false, false, 'reject_all');
                hideBanner();
            });
        }
        if (customize) {
            customize.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                showPanel();
            });
        }
        if (saveCustom) {
            saveCustom.addEventListener('click', function () {
                var aud = document.getElementById('portal-cookie-audience');
                var pers = document.getElementById('portal-cookie-personalization');
                var adsEl = document.getElementById('portal-cookie-ads');
                persist(
                    !!(aud && aud.checked),
                    !!(pers && pers.checked),
                    !!(adsEl && adsEl.checked),
                    'custom_save'
                );
                hideBanner();
            });
        }
        if (reset) {
            reset.addEventListener('click', function () {
                try {
                    localStorage.removeItem(STORAGE_KEY);
                } catch (e) { /* ignore */ }
                updateChoiceTimestamp(0);
                showBanner();
                showPanel();
            });
        }
    }

    function bindStorageSync() {
        window.addEventListener('storage', function (event) {
            if (event.key !== STORAGE_KEY) {
                return;
            }
            var p = readPrefs();
            if (p) {
                hideBanner();
                notifyConsent(p, 'storage_sync');
            } else {
                showBanner();
            }
        });
    }

    function init() {
        bindDelegatedClicks();
        bindStorageSync();
        var existing = readPrefs();
        if (existing) {
            updateChoiceTimestamp(Number(safeParse(localStorage.getItem(STORAGE_KEY) || '{}').ts || nowTs()));
        } else {
            updateChoiceTimestamp(0);
        }
        if (hasConsent()) {
            hideBanner();
            var p = existing;
            if (p) {
                notifyConsent(p, 'boot');
            }
            bindControls();
            return;
        }
        showBanner();
        bindControls();
    }

    window.AthenaCookieConsent = {
        openPreferences: openPreferences,
        getPreferences: readPrefs,
        exportPreferences: function () {
            return localStorage.getItem(STORAGE_KEY);
        },
        importPreferences: function (raw) {
            var parsed = safeParse(String(raw || ''));
            if (!parsed || String(parsed.v) !== String(VERSION)) {
                return false;
            }
            persist(!!parsed.analytics, !!parsed.personalization, !!parsed.ads, 'api_import');
            hideBanner();
            return true;
        },
        resetPreferences: function () {
            try {
                localStorage.removeItem(STORAGE_KEY);
            } catch (e) { /* ignore */ }
            updateChoiceTimestamp(0);
            showBanner();
        },
        acceptEssentialOnly: function () {
            persist(false, false, false, 'api_essential_only');
            hideBanner();
        },
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
