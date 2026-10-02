<?php
declare(strict_types=1);
/**
 * Carte d’unité (lien vers son espace). Attend $u (JnetSpaceService::unitCard).
 * @var array<string, mixed> $u
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$accent = (string) ($u['accent'] ?? '');
$st = (array) ($u['strength'] ?? []);
$total = (int) ($st['total'] ?? 0);
$fill = max(0, min(100, (int) ($u['fill'] ?? 0)));
?>
<a class="jn-unit" href="<?= $h((string) $u['href']) ?>"<?= $accent !== '' ? ' style="--jn-unit: ' . $h($accent) . '"' : '' ?>>
    <span class="jn-unit__top">
        <span>
            <strong class="jn-unit__name"><?= $h((string) $u['label']) ?></strong>
            <?php if ((string) ($u['parentLabel'] ?? '') !== ''): ?>
                <span class="jn-unit__parent"><?= $h((string) $u['parentLabel']) ?></span>
            <?php endif; ?>
        </span>
        <?php if ((int) ($u['subCount'] ?? 0) > 0): ?>
            <span class="jn-unit__sub"><?= (int) $u['subCount'] ?> sous-unité<?= (int) $u['subCount'] > 1 ? 's' : '' ?></span>
        <?php endif; ?>
    </span>
    <span class="jn-meter" role="img" aria-label="<?= (int) ($st['available'] ?? 0) ?> disponibles sur <?= $total ?>">
        <span style="width: <?= $fill ?>%"></span>
    </span>
    <span class="jn-unit__facts">
        <span><?= $total > 0 ? (int) ($st['available'] ?? 0) . ' / ' . $total . ' disponibles' : 'Effectif non renseigné' ?></span>
        <?php if ((string) ($u['lastActivity'] ?? '') !== ''): ?>
            <span class="jn-unit__last"><?= $h((string) $u['lastActivity']) ?></span>
        <?php endif; ?>
    </span>
    <span class="jn-unit__activity">
        <?= (string) ($u['activity'] ?? '') !== '' ? $h((string) $u['activity']) : 'Aucune opération engagée' ?>
    </span>
</a>
