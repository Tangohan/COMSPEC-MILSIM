<?php
declare(strict_types=1);

/**
 * Styles + bootstrap JS pour les boutons RSVP dynamiques (inclus une seule fois).
 */
?>
<style>
.dash-rsvp {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem;
}
.dash-rsvp--compact { margin-top: 0.6rem; }
.dash-rsvp__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 0.85rem;
    border-radius: 0.6rem;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, opacity 0.15s ease, box-shadow 0.15s ease, transform 0.12s ease;
}
.dash-rsvp__btn:hover:not([disabled]):not(.is-active) {
    background: #f8fafc;
    border-color: #94a3b8;
}
.dash-rsvp__btn:focus-visible {
    outline: 2px solid #059669;
    outline-offset: 2px;
}
.dash-rsvp__btn[disabled] {
    opacity: 0.55;
    cursor: wait;
}
.dash-rsvp__btn.is-active,
.dash-rsvp[data-rsvp-current="yes"] .dash-rsvp__btn--yes.is-active,
.dash-rsvp[data-rsvp-current="maybe"] .dash-rsvp__btn--maybe.is-active,
.dash-rsvp[data-rsvp-current="no"] .dash-rsvp__btn--no.is-active {
    color: #fff;
    border-color: transparent;
    box-shadow: 0 6px 14px -8px rgba(15, 23, 42, 0.45);
    cursor: default;
}
.dash-rsvp[data-rsvp-current="yes"] .dash-rsvp__btn--yes.is-active { background: #059669; }
.dash-rsvp[data-rsvp-current="maybe"] .dash-rsvp__btn--maybe.is-active { background: #d97706; }
.dash-rsvp[data-rsvp-current="no"] .dash-rsvp__btn--no.is-active { background: #dc2626; }
.dash-rsvp--busy .dash-rsvp__btn.is-active { opacity: 0.85; }
.dash-rsvp__status {
    flex: 1 1 100%;
    font-size: 0.6875rem;
    font-weight: 700;
    color: #059669;
    min-height: 1.1em;
    line-height: 1.3;
}
.dash-rsvp__status.is-busy { color: #64748b; }
.dash-rsvp__status.is-error { color: #dc2626; }
.dash-rsvp__reason { display: inline-flex; align-items: center; }
.dash-rsvp__reason.hidden,
.dash-rsvp__reason[hidden] { display: none !important; }
.dash-rsvp__select {
    min-width: 9.5rem;
    padding: 0.4rem 0.55rem;
    border-radius: 0.55rem;
    border: 1px solid #cbd5e1;
    background: #fff;
    font-size: 0.75rem;
    font-weight: 600;
    color: #334155;
}
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
</style>
<script>
window.__DASH_RSVP__ = {
    csrf: <?= json_encode(\App\Core\Csrf::token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    endpointBase: <?= json_encode(url('api/events/'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
};
</script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/dashboard-rsvp.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
