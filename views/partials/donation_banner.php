<?php
declare(strict_types=1);

// Bandeau de don (réglage Admin > Paramètres système > Bandeau de don) : App\Services\Platform\DonationBanner.
// Inclus par layout.main (pages portail hors administration) et layout.marketing (site public).
// Jamais sur l'administration, la connexion, le paiement, la page de dons, l'ATAK web et les outils plein écran.
// Fermé par le visiteur : masqué 5 jours (localStorage), revient si l'équipe change le message.

use App\Services\Platform\DonationBanner;

$dnbLocale = function_exists('locale') ? (string) locale() : 'fr';
try {
    $dnb = DonationBanner::forRequest(function_exists('back_office_path_suffix') ? back_office_path_suffix() : '', $dnbLocale);
} catch (\Throwable) {
    $dnb = null;
}
if ($dnb === null || $dnb['message'] === '') {
    return;
}
$dnbE = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$dnbT = static fn (string $k, array $r = []): string => htmlspecialchars(__('site.dnb_' . $k, $r), ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="<?= $dnbE(asset_url('assets/css/donation-banner.css') . '?v=1') ?>">
<aside class="dnb" id="athena-donation-banner" data-dnb-key="<?= $dnbE($dnb['key']) ?>" aria-label="<?= $dnbT('region') ?>">
    <div class="dnb__inner">
        <svg class="dnb__heart" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 20.5s-7.5-4.6-9.2-9.4C1.6 7.6 4 4.5 7.3 4.5c2 0 3.5 1.1 4.7 2.8 1.2-1.7 2.7-2.8 4.7-2.8 3.3 0 5.7 3.1 4.5 6.6-1.7 4.8-9.2 9.4-9.2 9.4z"/></svg>
        <p class="dnb__msg" translate="no"><?= $dnbE($dnb['message']) ?></p>
        <?php if ($dnb['goal_cents'] > 0): ?>
        <div class="dnb__goal">
            <span class="dnb__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $dnb['percent'] ?>" aria-label="<?= $dnbT('progress_label') ?>"><span style="width: <?= max(2, (int) $dnb['percent']) ?>%"></span></span>
            <span class="dnb__sum"><?= $dnbT('progress', ['raised' => DonationBanner::euros($dnb['raised_cents'], $dnbLocale), 'goal' => DonationBanner::euros($dnb['goal_cents'], $dnbLocale)]) ?></span>
        </div>
        <?php endif; ?>
        <a class="dnb__cta" href="<?= $dnbE(url('soutenir-atak')) ?>"><?= $dnbT('cta') ?></a>
        <button type="button" class="dnb__close" data-dnb-close aria-label="<?= $dnbT('close') ?>" title="<?= $dnbT('close') ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>
</aside>
<script>
(function () {
    var el = document.getElementById('athena-donation-banner');
    if (!el) { return; }
    var KEY = 'athena.donationBanner';
    var key = el.getAttribute('data-dnb-key') || '';
    try {
        var saved = JSON.parse(localStorage.getItem(KEY) || 'null');
        if (saved && saved.k === key && saved.until > Date.now()) { el.remove(); return; }
    } catch (e) {}
    var btn = el.querySelector('[data-dnb-close]');
    if (!btn) { return; }
    btn.addEventListener('click', function () {
        try { localStorage.setItem(KEY, JSON.stringify({ k: key, until: Date.now() + 5 * 86400000 })); } catch (e) {}
        el.classList.add('is-leaving');
        var done = function () { el.remove(); };
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce) { done(); } else { setTimeout(done, 260); }
    });
})();
</script>
