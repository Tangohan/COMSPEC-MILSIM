/**
 * Mesure d’audience (durée de consultation, clics vers candidature) si le visiteur a accepté les cookies « audience ».
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'athena_portal_cookie_prefs';

    function readAnalyticsAllowed() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return false;
            var o = JSON.parse(raw);
            if (!o) return false;
            if (String(o.v) === '1' && o.choice === 'all') return true;
            if (String(o.v) === '2' && o.analytics === true) return true;
        } catch (e) {}
        return false;
    }

    function cfg() {
        return window.__COMSPEC_ANALYTICS__ || null;
    }

    function sendBeacon(payload) {
        var c = cfg();
        if (!c || !c.beaconUrl || !c.csrf) return;
        var body = new URLSearchParams();
        body.set('_csrf_token', c.csrf);
        body.set('tenant_id', String(c.tenantId));
        body.set('category', payload.category || c.category);
        body.set('name', payload.name);
        if (payload.subject_type) body.set('subject_type', payload.subject_type);
        if (payload.subject_id) body.set('subject_id', String(payload.subject_id));
        if (payload.duration_seconds != null) body.set('duration_seconds', String(payload.duration_seconds));
        if (payload.props_json) body.set('props_json', payload.props_json);
        try {
            if (navigator.sendBeacon) {
                var blob = new Blob([body.toString()], { type: 'application/x-www-form-urlencoded' });
                navigator.sendBeacon(c.beaconUrl, blob);
            } else {
                fetch(c.beaconUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                    keepalive: true,
                    credentials: 'same-origin'
                }).catch(function () {});
            }
        } catch (e) {}
    }

    function flushDuration() {
        var c = cfg();
        if (!c || !readAnalyticsAllowed()) return;
        var start = window.__COMSPEC_ANALYTICS_T0__;
        if (!start) return;
        var sec = Math.floor((Date.now() - start) / 1000);
        if (sec < 2) return;
        if (sec > 86400) sec = 86400;
        sendBeacon({
            name: c.durationEvent,
            category: c.category,
            subject_type: c.subjectType || '',
            subject_id: c.subjectId || 0,
            duration_seconds: sec
        });
        window.__COMSPEC_ANALYTICS_T0__ = null;
    }

    function initDuration() {
        var c = cfg();
        if (!c || !readAnalyticsAllowed()) return;
        window.__COMSPEC_ANALYTICS_T0__ = Date.now();
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') flushDuration();
        });
        window.addEventListener('pagehide', flushDuration);
    }

    function onCtaClick(ev) {
        var el = ev.target.closest('.comspec-analytics-cta');
        if (!el || !readAnalyticsAllowed()) return;
        var c = cfg();
        if (!c || !c.beaconUrl) return;
        var zone = el.getAttribute('data-comspec-zone') || 'cta';
        var opening = el.getAttribute('data-comspec-opening') || '';
        var props = { zone: zone };
        if (opening) props.opening_id = parseInt(opening, 10) || 0;
        sendBeacon({
            name: 'tenant_recruitment_cta_click',
            category: 'recruitment',
            subject_type: 'tenant',
            subject_id: c.tenantId,
            duration_seconds: 0,
            props_json: JSON.stringify(props)
        });
    }

    function bindCta() {
        document.addEventListener('click', onCtaClick, true);
    }

    function onConsent(ev) {
        if (ev && ev.detail && ev.detail.analytics === true) {
            initDuration();
        }
    }

    window.addEventListener('portalCookieConsent', onConsent);
    if (readAnalyticsAllowed()) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDuration);
        } else {
            initDuration();
        }
    }
    bindCta();
})();
