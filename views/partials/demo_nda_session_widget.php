<?php
declare(strict_types=1);

$demoNdaRemaining = null;
$demoNdaExpiresIso = null;
try {
    if (class_exists(\App\Services\DemoNda\DemoNdaGateService::class)) {
        $demoNdaGate = \App\Core\Container::get(\App\Services\DemoNda\DemoNdaGateService::class);
        $demoNdaRemaining = $demoNdaGate->activeSessionRemainingSeconds();
        $expiresAt = $demoNdaGate->activeSessionExpiresAt();
        if ($expiresAt !== null) {
            $demoNdaExpiresIso = $expiresAt->format(DateTimeInterface::ATOM);
        }
    }
} catch (Throwable) {
    $demoNdaRemaining = null;
}

if ($demoNdaRemaining === null || $demoNdaExpiresIso === null) {
    return;
}

$feedbackUrl = url(ltrim(\App\Services\DemoNda\DemoNdaGateService::FEEDBACK_PATH, '/'));
?>
<div
    id="demo-nda-timer"
    class="demo-nda-timer"
    role="timer"
    aria-live="polite"
    aria-atomic="true"
    data-expires-at="<?= htmlspecialchars($demoNdaExpiresIso, ENT_QUOTES, 'UTF-8') ?>"
    data-feedback-url="<?= htmlspecialchars($feedbackUrl, ENT_QUOTES, 'UTF-8') ?>"
>
    <p class="demo-nda-timer__label" data-demo-nda-label>Accès démo</p>
    <p class="demo-nda-timer__value" data-demo-nda-countdown>—:—:—</p>
    <a
        class="demo-nda-timer__cta"
        href="<?= htmlspecialchars($feedbackUrl, ENT_QUOTES, 'UTF-8') ?>"
        data-demo-nda-cta
    >Répondre au questionnaire</a>
</div>
<style>
.demo-nda-timer {
    position: fixed;
    z-index: 9800;
    right: 1rem;
    bottom: 1rem;
    min-width: 9.5rem;
    max-width: 14rem;
    padding: 0.7rem 0.9rem;
    border: 1px solid rgba(52, 211, 153, 0.35);
    background: rgba(5, 5, 5, 0.88);
    color: #ecfdf5;
    font-family: Inter, "Segoe UI", system-ui, sans-serif;
    backdrop-filter: blur(10px);
    box-shadow: 0 12px 40px -18px rgba(0, 0, 0, 0.65);
}
.demo-nda-timer__label {
    margin: 0;
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: rgba(167, 243, 208, 0.7);
}
.demo-nda-timer__value {
    margin: 0.25rem 0 0;
    font-size: 1.05rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    font-variant-numeric: tabular-nums;
    color: #fff;
}
.demo-nda-timer__cta {
    display: block;
    margin-top: 0.65rem;
    padding: 0.45rem 0.55rem;
    border: 1px solid rgba(52, 211, 153, 0.45);
    background: rgba(52, 211, 153, 0.12);
    color: #a7f3d0;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    line-height: 1.35;
    text-align: center;
    text-decoration: none;
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}
.demo-nda-timer__cta:hover {
    background: rgba(52, 211, 153, 0.22);
    border-color: rgba(110, 231, 183, 0.7);
    color: #ecfdf5;
}
.demo-nda-timer.is-ended {
    min-width: 11.5rem;
    max-width: 16rem;
    border-color: rgba(52, 211, 153, 0.55);
    animation: none;
}
.demo-nda-timer.is-ended .demo-nda-timer__label {
    color: rgba(167, 243, 208, 0.9);
}
.demo-nda-timer.is-ended .demo-nda-timer__value {
    display: none;
}
.demo-nda-timer.is-ended .demo-nda-timer__cta {
    margin-top: 0.55rem;
    padding: 0.7rem 0.75rem;
    background: #34d399;
    border-color: #34d399;
    color: #052e1c;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.demo-nda-timer.is-ended .demo-nda-timer__cta:hover {
    background: #6ee7b7;
    border-color: #6ee7b7;
    color: #052e1c;
}
.demo-nda-timer.is-warn {
    border-color: rgba(251, 191, 36, 0.55);
}
.demo-nda-timer.is-warn .demo-nda-timer__label { color: rgba(253, 230, 138, 0.85); }
.demo-nda-timer.is-warn .demo-nda-timer__cta {
    border-color: rgba(251, 191, 36, 0.45);
    background: rgba(251, 191, 36, 0.1);
    color: #fde68a;
}
.demo-nda-timer.is-critical:not(.is-ended) {
    border-color: rgba(248, 113, 113, 0.65);
    animation: demo-nda-pulse 1.1s ease-in-out infinite;
}
.demo-nda-timer.is-critical:not(.is-ended) .demo-nda-timer__label { color: rgba(254, 202, 202, 0.9); }
.demo-nda-timer.is-critical:not(.is-ended) .demo-nda-timer__cta {
    border-color: rgba(248, 113, 113, 0.5);
    background: rgba(248, 113, 113, 0.12);
    color: #fecaca;
}
@keyframes demo-nda-pulse {
    0%, 100% { box-shadow: 0 12px 40px -18px rgba(0, 0, 0, 0.65); }
    50% { box-shadow: 0 0 0 1px rgba(248, 113, 113, 0.35), 0 12px 40px -12px rgba(248, 113, 113, 0.45); }
}
@media (max-width: 640px) {
    .demo-nda-timer {
        right: 0.75rem;
        bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));
        min-width: 8.5rem;
        max-width: 12.5rem;
        padding: 0.55rem 0.7rem;
    }
    .demo-nda-timer__value { font-size: 0.95rem; }
    .demo-nda-timer.is-ended {
        min-width: 10.5rem;
        max-width: 14rem;
    }
}
/* Au-dessus de la bottom nav éventuelle */
body:has(.bottom-nav) .demo-nda-timer,
body:has([data-bottom-nav]) .demo-nda-timer {
    bottom: 4.5rem;
}
</style>
<script>
(function () {
    var root = document.getElementById('demo-nda-timer');
    if (!root) return;
    var el = root.querySelector('[data-demo-nda-countdown]');
    var label = root.querySelector('[data-demo-nda-label]');
    var cta = root.querySelector('[data-demo-nda-cta]');
    var expiresAt = Date.parse(root.getAttribute('data-expires-at') || '');
    var feedbackUrl = root.getAttribute('data-feedback-url') || '';
    if (!el || !expiresAt) return;

    function pad(n) { return String(n).padStart(2, '0'); }

    function endSession() {
        el.textContent = '00:00:00';
        root.classList.remove('is-warn');
        root.classList.add('is-critical', 'is-ended');
        if (label) label.textContent = 'Temps écoulé';
        if (cta) {
            cta.textContent = 'Répondre au questionnaire';
            cta.setAttribute('href', feedbackUrl);
            cta.focus();
        }
    }

    function tick() {
        var left = Math.floor((expiresAt - Date.now()) / 1000);
        if (left <= 0) {
            endSession();
            return;
        }
        var h = Math.floor(left / 3600);
        var m = Math.floor((left % 3600) / 60);
        var s = left % 60;
        el.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);
        root.classList.toggle('is-warn', left <= 15 * 60 && left > 5 * 60);
        root.classList.toggle('is-critical', left <= 5 * 60);
        window.setTimeout(tick, 1000);
    }
    tick();
})();
</script>
