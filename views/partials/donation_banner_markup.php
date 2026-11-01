<?php
declare(strict_types=1);

// Corps du bandeau de don, partagé par le site (views/partials/donation_banner.php) et l'aperçu de
// l'administration (Admin > Paiements > Dons). Attend $dnb (DonationBanner::view), $dnbLocale, $dnbE, $dnbT.
// $dnbId : identifiant HTML (aperçu : sans identifiant, pour ne pas croiser le script de fermeture).
// Styles (DonationBanner::STYLES) : terrain (+ rouge, + cœurs), indicatif, emeraude, portail, compact.

use App\Services\Platform\DonationBanner;

/** @var array{message: string, goal_cents: int, raised_cents: int, percent: int, key: string, style?: string} $dnb */
/** @var string $dnbLocale */
/** @var callable(string): string $dnbE */
/** @var callable(string, array<string, string>=): string $dnbT */
$dnbId = isset($dnbId) ? (string) $dnbId : 'athena-donation-banner';
$dnbStyle = DonationBanner::cleanStyle((string) ($dnb['style'] ?? ''));
$dnbTerrain = str_starts_with($dnbStyle, 'terrain');
$dnbHearts = str_ends_with($dnbStyle, '_coeurs');
$dnbClass = 'dnb dnb--' . ($dnbTerrain ? 'terrain' : $dnbStyle)
    . (str_contains($dnbStyle, 'rouge') ? ' dnb--rouge' : '')
    . ($dnbHearts ? ' dnb--coeurs' : '');
$dnbHasGoal = $dnb['goal_cents'] > 0;
$dnbPct = (int) $dnb['percent'];
$dnbSum = $dnbHasGoal ? $dnbT('progress', ['raised' => DonationBanner::euros($dnb['raised_cents'], $dnbLocale), 'goal' => DonationBanner::euros($dnb['goal_cents'], $dnbLocale)]) : '';
$dnbPage = url('soutenir-atak');
$dnbHeart = '<svg class="dnb__ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>';
$dnbArrow = '<svg class="dnb__ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
$dnbBar = static fn (): string => '<span class="dnb__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $dnbPct . '" aria-label="' . $dnbT('progress_label') . '"><span style="width: ' . max(2, $dnbPct) . '%"></span></span>';
$dnbClose = '<button type="button" class="dnb__close" data-dnb-close aria-label="' . $dnbT('close') . '" title="' . $dnbT('close') . '"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M18 6 6 18M6 6l12 12"/></svg></button>';
?>
<link rel="stylesheet" href="<?= $dnbE(asset_url('assets/css/donation-banner.css') . '?v=3') ?>">
<aside class="<?= $dnbE($dnbClass) ?>" <?= $dnbId !== '' ? 'id="' . $dnbE($dnbId) . '"' : '' ?> data-dnb-key="<?= $dnbE($dnb['key']) ?>" data-dnb-style="<?= $dnbE($dnbStyle) ?>" aria-label="<?= $dnbT('region') ?>">
<?php if ($dnbTerrain): ?>
    <div class="dnb__photo" aria-hidden="true"></div>
    <div class="dnb__shade" aria-hidden="true"></div>
    <?php if ($dnbHearts): ?>
    <div class="dnb__hearts" aria-hidden="true"><?php for ($dnbI = 1; $dnbI <= 9; $dnbI++): ?><span style="--i: <?= $dnbI ?>"><?= $dnbHeart ?></span><?php endfor; ?></div>
    <?php endif; ?>
    <div class="dnb__in">
        <div class="dnb__text">
            <p class="dnb__kicker"><i class="dnb__dot"></i><span><?= $dnbT('kicker') ?></span></p>
            <p class="dnb__title"><?= $dnbT('title_a') ?> <span><?= $dnbT('title_b') ?></span></p>
            <p class="dnb__msg" translate="no"><?= $dnbE($dnb['message']) ?></p>
        </div>
        <?php if ($dnbHasGoal): ?>
        <div class="dnb__meter">
            <div class="dnb__row"><span><?= $dnbT('goal') ?></span><b><?= $dnbPct ?>&nbsp;%</b></div>
            <?= $dnbBar() ?>
            <div class="dnb__row"><span><?= $dnbSum ?></span></div>
        </div>
        <?php endif; ?>
        <div class="dnb__side">
            <a class="dnb__cta" href="<?= $dnbE($dnbPage) ?>"><?= $dnbHeart ?><span><?= $dnbT('cta') ?></span></a>
            <?= $dnbClose ?>
        </div>
    </div>
<?php elseif ($dnbStyle === 'indicatif'): ?>
    <div class="dnb__in">
        <span class="dnb__status"><i class="dnb__dot"></i>SRV-01 · <?= $dnbT('status') ?></span>
        <p class="dnb__msg" translate="no"><?= $dnbE($dnb['message']) ?></p>
        <?php if ($dnbHasGoal): ?><span class="dnb__goal"><?= $dnbBar() ?><span class="dnb__sum"><?= $dnbSum ?></span></span><?php endif; ?>
        <a class="dnb__cta" href="<?= $dnbE($dnbPage) ?>"><?= $dnbHeart ?><span><?= $dnbT('cta_short') ?></span></a>
        <?= $dnbClose ?>
    </div>
<?php elseif ($dnbStyle === 'emeraude'): ?>
    <div class="dnb__in">
        <span class="dnb__badge" aria-hidden="true"><?= $dnbHeart ?></span>
        <div class="dnb__text">
            <p class="dnb__title"><?= $dnbT('title_short') ?></p>
            <p class="dnb__msg" translate="no"><?= $dnbE($dnb['message']) ?></p>
            <?php if ($dnbHasGoal): ?><span class="dnb__goal"><?= $dnbBar() ?><span class="dnb__sum"><?= $dnbSum ?></span></span><?php endif; ?>
        </div>
        <div class="dnb__amounts" role="group" aria-label="<?= $dnbT('amounts') ?>">
            <?php foreach ([5, 10, 25] as $dnbEur): $dnbAmount = DonationBanner::euros($dnbEur * 100, $dnbLocale); ?>
            <a class="dnb__amount<?= $dnbEur === 10 ? ' is-on' : '' ?>" href="<?= $dnbE($dnbPage . '?montant=' . $dnbEur . '#don') ?>" aria-label="<?= $dnbT('give_amount', ['amount' => $dnbAmount]) ?>"><?= $dnbE($dnbAmount) ?></a>
            <?php endforeach; ?>
            <a class="dnb__cta" href="<?= $dnbE($dnbPage . '#don') ?>"><span><?= $dnbT('give') ?></span><?= $dnbArrow ?></a>
        </div>
        <?= $dnbClose ?>
    </div>
<?php elseif ($dnbStyle === 'portail'): ?>
    <div class="dnb__in">
        <span class="dnb__badge" aria-hidden="true"><?= $dnbHeart ?></span>
        <div class="dnb__text">
            <p class="dnb__title"><?= $dnbT('title_short') ?></p>
            <p class="dnb__msg" translate="no"><?= $dnbE($dnb['message']) ?></p>
        </div>
        <?php if ($dnbHasGoal): ?><span class="dnb__goal"><?= $dnbBar() ?><span class="dnb__sum"><?= $dnbSum ?></span></span><?php endif; ?>
        <div class="dnb__acts">
            <a class="dnb__ghost" href="<?= $dnbE(url('contribuer')) ?>"><?= $dnbT('learn') ?></a>
            <a class="dnb__cta" href="<?= $dnbE($dnbPage) ?>"><?= $dnbHeart ?><span><?= $dnbT('cta') ?></span></a>
        </div>
        <?= $dnbClose ?>
    </div>
<?php else: ?>
    <div class="dnb__in">
        <span class="dnb__beat" aria-hidden="true"><?= $dnbHeart ?></span>
        <p class="dnb__msg" translate="no"><?= $dnbE($dnb['message']) ?></p>
        <?php if ($dnbHasGoal): ?><span class="dnb__goal"><?= $dnbBar() ?><span class="dnb__sum"><?= $dnbPct ?>&nbsp;%</span></span><?php endif; ?>
        <a class="dnb__link" href="<?= $dnbE($dnbPage) ?>"><span><?= $dnbT('cta') ?></span><?= $dnbArrow ?></a>
        <?= $dnbClose ?>
    </div>
<?php endif; ?>
</aside>
