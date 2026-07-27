<?php
declare(strict_types=1);

/**
 * Pop-up(s) éphémère(s) — annonces communauté/plateforme en display_style=popup,
 * affichées une fois par visiteur (localStorage) + fenêtre starts_at/ends_at gérée en amont
 * par AlertPresentationService. Se ferme via le même endpoint que les tuiles d’annonces.
 *
 * @var list<array<string,mixed>> $dashboard_popup_items
 */

$popupItems = is_array($dashboard_popup_items ?? null) ? $dashboard_popup_items : [];
if ($popupItems === []) {
    return;
}

$popupJsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
$popupJson = json_encode($popupItems, $popupJsonFlags);
if (!is_string($popupJson) || $popupJson === '') {
    return;
}
$popupCsrf = \App\Core\Csrf::token();
$popupDismissUrl = url('api/alerts/dismiss');
$popupLoggedIn = (bool) \App\Core\Session::get('user_id');
?>
<div id="dashboard-popup-root"
     data-popups="<?= htmlspecialchars($popupJson, ENT_QUOTES, 'UTF-8') ?>"
     data-csrf="<?= htmlspecialchars($popupCsrf, ENT_QUOTES, 'UTF-8') ?>"
     data-dismiss-url="<?= htmlspecialchars($popupDismissUrl, ENT_QUOTES, 'UTF-8') ?>"
     data-logged-in="<?= $popupLoggedIn ? '1' : '0' ?>"
     aria-live="polite"
></div>
<script>
(function () {
    var root = document.getElementById('dashboard-popup-root');
    if (!root) return;
    var raw = root.getAttribute('data-popups');
    var items = [];
    try { items = JSON.parse(raw || '[]'); } catch (e) { return; }
    var csrf = root.getAttribute('data-csrf') || '';
    var dismissUrl = root.getAttribute('data-dismiss-url') || '';
    var loggedIn = root.getAttribute('data-logged-in') === '1';
    var LS = 'athena_alert_dismissed_';

    function storageKey(a) { return LS + a.scope + '_' + a.id; }
    function isDismissed(a) {
        try { return localStorage.getItem(storageKey(a)) === '1'; } catch (e) { return false; }
    }
    function setDismissed(a) {
        try { localStorage.setItem(storageKey(a), '1'); } catch (e) {}
    }

    var queue = items.filter(function (a) {
        var canDismiss = a.dismissible !== false && a.dismissible !== 0;
        return !(canDismiss && isDismissed(a));
    });
    if (!queue.length) { root.remove(); return; }

    var overlay = document.createElement('div');
    overlay.className = 'dash-popup-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');

    var panel = document.createElement('div');
    panel.className = 'dash-popup-panel';
    overlay.appendChild(panel);
    root.appendChild(overlay);

    function persistDismiss(a) {
        setDismissed(a);
        if (!loggedIn || !dismissUrl || !csrf) return;
        var fd = new FormData();
        fd.append('_csrf_token', csrf);
        fd.append('scope', a.scope);
        fd.append('alert_id', String(a.id));
        fetch(dismissUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {});
    }

    function renderNext() {
        var a = queue.shift();
        if (!a) {
            overlay.classList.remove('is-open');
            window.setTimeout(function () { root.remove(); }, 180);
            document.removeEventListener('keydown', onKeydown);
            return;
        }
        var canDismiss = a.dismissible !== false && a.dismissible !== 0;
        var kindLabels = {
            urgent: 'Urgent', info: 'Information', notice: 'Consigne',
            event: 'Événement', novelty: 'Nouveauté', discount: 'Promotion', maintenance: 'Maintenance',
            training: 'Formation', recruitment: 'Recrutement', security: 'Sécurité'
        };
        var visual = a.banner_url || a.image_url || null;

        panel.innerHTML = '';
        panel.setAttribute('data-alert-scope', a.scope);
        panel.setAttribute('data-alert-id', String(a.id));

        if (visual) {
            var media = document.createElement('div');
            media.className = 'dash-popup-panel__media';
            media.style.backgroundImage = 'url("' + String(visual).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '")';
            panel.appendChild(media);
        }

        var body = document.createElement('div');
        body.className = 'dash-popup-panel__body';

        var kicker = document.createElement('p');
        kicker.className = 'dash-popup-panel__kicker';
        if (a.accent_color) kicker.style.color = a.accent_color;
        kicker.textContent = (kindLabels[a.kind] || 'Annonce') + (a.scope === 'platform' ? ' · Athena' : '');
        body.appendChild(kicker);

        var title = document.createElement('h2');
        title.className = 'dash-popup-panel__title';
        title.textContent = a.title || 'Annonce';
        body.appendChild(title);

        if (a.body) {
            var desc = document.createElement('p');
            desc.className = 'dash-popup-panel__desc';
            desc.textContent = a.body;
            body.appendChild(desc);
        }

        var actions = document.createElement('div');
        actions.className = 'dash-popup-panel__actions';

        if (a.cta_url && a.cta_label) {
            var cta = document.createElement('a');
            cta.href = a.cta_url;
            cta.className = 'cc-btn cc-btn-primary';
            cta.textContent = a.cta_label;
            if (a.accent_color) { cta.style.background = a.accent_color; cta.style.borderColor = a.accent_color; }
            actions.appendChild(cta);
        }

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = a.cta_url && a.cta_label ? 'cc-btn cc-btn-ghost' : 'cc-btn cc-btn-primary';
        closeBtn.textContent = canDismiss ? 'J’ai pris connaissance' : 'Fermer';
        closeBtn.addEventListener('click', function () {
            if (canDismiss) persistDismiss(a);
            renderNext();
        });
        actions.appendChild(closeBtn);

        body.appendChild(actions);
        panel.appendChild(body);

        var xBtn = document.createElement('button');
        xBtn.type = 'button';
        xBtn.className = 'dash-popup-panel__close';
        xBtn.setAttribute('aria-label', 'Fermer');
        xBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>';
        xBtn.addEventListener('click', function () {
            if (canDismiss) persistDismiss(a);
            renderNext();
        });
        panel.appendChild(xBtn);

        overlay.classList.add('is-open');
    }

    function onKeydown(e) {
        if (e.key === 'Escape') renderNext();
    }
    document.addEventListener('keydown', onKeydown);
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) renderNext();
    });

    if (!document.getElementById('dashboard-popup-style')) {
        var st = document.createElement('style');
        st.id = 'dashboard-popup-style';
        st.textContent = [
            '.dash-popup-overlay{position:fixed;inset:0;z-index:110;display:flex;align-items:center;justify-content:center;padding:1rem;background:rgba(2,6,23,0);pointer-events:none;transition:background 0.18s ease;}',
            '.dash-popup-overlay.is-open{background:rgba(2,6,23,0.72);backdrop-filter:blur(4px);pointer-events:auto;}',
            '.dash-popup-panel{position:relative;width:min(100%,30rem);border-radius:1rem;background:#fff;box-shadow:0 25px 50px -12px rgba(0,0,0,0.45);overflow:hidden;opacity:0;transform:translateY(8px) scale(0.98);transition:opacity 0.18s ease, transform 0.18s ease;}',
            '.dash-popup-overlay.is-open .dash-popup-panel{opacity:1;transform:translateY(0) scale(1);}',
            '.dash-popup-panel__media{height:10rem;background-size:cover;background-position:center;}',
            '.dash-popup-panel__body{padding:1.5rem;}',
            '.dash-popup-panel__kicker{margin:0 0 0.4rem;font-size:0.6875rem;font-weight:800;letter-spacing:0.14em;text-transform:uppercase;color:#059669;}',
            '.dash-popup-panel__title{margin:0 0 0.6rem;font-size:1.25rem;font-weight:900;letter-spacing:-0.01em;color:#0f172a;}',
            '.dash-popup-panel__desc{margin:0 0 1.1rem;font-size:0.875rem;line-height:1.55;color:#475569;white-space:pre-wrap;}',
            '.dash-popup-panel__actions{display:flex;flex-wrap:wrap;gap:0.6rem;}',
            '.dash-popup-panel__close{position:absolute;top:0.75rem;right:0.75rem;display:inline-flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:0.6rem;border:1px solid #e2e8f0;background:#f8fafc;color:#334155;cursor:pointer;}',
            '.dash-popup-panel__close:hover{background:#f1f5f9;}',
            '@media (prefers-reduced-motion:reduce){.dash-popup-overlay,.dash-popup-panel{transition:none!important;}}'
        ].join('');
        document.head.appendChild(st);
    }

    renderNext();
})();
</script>
