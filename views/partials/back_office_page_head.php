<?php
declare(strict_types=1);

/**
 * En-tête de page back-office ATHENA (kicker, titre, sous-titre, actions rapides).
 *
 * - $boPageKicker (string)
 * - $boPageTitle (string)
 * - $boPageSubtitle (string)
 * - $boPageQuick (list<string|array{label:string,href:string}>)
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$kicker = isset($boPageKicker) ? trim((string) $boPageKicker) : '';
$headTitle = isset($boPageTitle) ? trim((string) $boPageTitle) : (isset($title) ? trim((string) $title) : '');
$subtitle = isset($boPageSubtitle) ? trim((string) $boPageSubtitle) : '';
$quick = isset($boPageQuick) && is_array($boPageQuick) ? $boPageQuick : [];
// Raccourcis hors profil ou sans droit retirés (ex. « Candidatures » pour une communauté « Carte ATAK »).
if (function_exists('portal_nav_href_permission_allowed')) {
    $quick = array_values(array_filter($quick, static fn ($q): bool => !is_array($q)
        || portal_nav_href_permission_allowed((string) ($q['href'] ?? ''))));
}

if ($headTitle === '' && $kicker === '' && $subtitle === '' && $quick === []) {
    return;
}
?>
<section class="ath-page-head ath-rise" aria-labelledby="ath-page-title">
    <div class="ath-page-head__copy">
        <?php if ($kicker !== ''): ?>
        <p class="ath-page-head__kicker"><?= $h($kicker) ?></p>
        <?php endif; ?>
        <?php if ($headTitle !== ''): ?>
        <h1 class="ath-page-head__title" id="ath-page-title"><?= $h($headTitle) ?></h1>
        <?php endif; ?>
        <?php if ($subtitle !== ''): ?>
        <p class="ath-page-head__sub"><?= $h($subtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php if ($quick !== []): ?>
    <div class="ath-page-head__actions">
        <?php foreach ($quick as $q): ?>
            <?php if (is_array($q)): ?>
                <a href="<?= $h((string) ($q['href'] ?? '#')) ?>" class="ath-btn"><?= $h((string) ($q['label'] ?? '')) ?></a>
            <?php else: ?>
                <span class="ath-btn"><?= $h((string) $q) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
