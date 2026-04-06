/**
 * Préférences cookies (localStorage v2) : nécessaires toujours actifs ; audience et publicité au choix.
 * Événement : portalCookieConsent — detail { analytics, ads, essentialOnly }
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'athena_portal_cookie_prefs';
    var VERSION = 2;

    function safeParse(raw) {
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    /**
     * @returns {{ analytics: boolean, ads: boolean } | null}
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
            return { analytics: true, ads: true };
        }
        if (String(o.v) === '1' && o.choice === 'essential') {
            return { analytics: false, ads: false };
        }
        if (String(o.v) === '2' && typeof o.analytics === 'boolean' && typeof o.ads === 'boolean') {
            return { analytics: o.analytics, ads: o.ads };
        }
        return null;
    }

    function hasConsent() {
        return readPrefs() !== null;
    }

    function persist(analytics, ads) {
        var payload = { v: VERSION, analytics: !!analytics, ads: !!ads, ts: Date.now() };
        localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        try {
            document.dispatchEvent(new CustomEvent('portalCookieConsent', {
                detail: {
                    analytics: !!analytics,
                    ads: !!ads,
                    essentialOnly: !analytics && !ads,
                },
            }));
        } catch (e) { /* ignore */ }
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
        var ads = document.getElementById('portal-cookie-ads');
        if (aud) {
            aud.checked = p ? !!p.analytics : false;
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

        if (accept) {
            accept.addEventListener('click', function () {
                persist(true, true);
                hideBanner();
            });
        }
        if (essential) {
            essential.addEventListener('click', function () {
                persist(false, false);
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
                var adsEl = document.getElementById('portal-cookie-ads');
                persist(
                    !!(aud && aud.checked),
                    !!(adsEl && adsEl.checked)
                );
                hideBanner();
            });
        }
    }

    function init() {
        bindDelegatedClicks();
        if (hasConsent()) {
            hideBanner();
            var p = readPrefs();
            if (p) {
                try {
                    document.dispatchEvent(new CustomEvent('portalCookieConsent', {
                        detail: {
                            analytics: p.analytics,
                            ads: p.ads,
                            essentialOnly: !p.analytics && !p.ads,
                        },
                    }));
                } catch (e) { /* ignore */ }
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
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
