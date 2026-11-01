<?php
declare(strict_types=1);

// Corps du bandeau de don, partagé par le site (views/partials/donation_banner.php) et l'aperçu de
// l'administration (Admin > Paiements > Dons). Attend $dnb (DonationBanner::view), $dnbLocale, $dnbE, $dnbT.
// $dnbId : identifiant HTML (aperçu : sans identifiant, pour ne pas croiser le script de fermeture).

use App\Services\Platform\DonationBanner;

/** @var array{message: string, goal_cents: int, raised_cents: int, percent: int, key: string} $dnb */
/** @var string $dnbLocale */
/** @var callable(string): string $dnbE */
/** @var callable(string, array<string, string>=): string $dnbT */
$dnbId = isset($dnbId) ? (string) $dnbId : 'athena-donation-banner';
?>
<link rel="stylesheet" href="<?= $dnbE(asset_url('assets/css/donation-banner.css') . '?v=2') ?>">
<aside class="dnb" <?= $dnbId !== '' ? 'id="' . $dnbE($dnbId) . '"' : '' ?> data-dnb-key="<?= $dnbE($dnb['key']) ?>" aria-label="<?= $dnbT('region') ?>">
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
