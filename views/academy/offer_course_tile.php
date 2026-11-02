<?php
/**
 * Tuile de formation du tableau de bord Academy : couverture, progression et action principale.
 *
 * @var array<string, mixed> $it
 * @var callable $tileCover
 */
$tileNext = $it['next'] ?? null;
$tileEnrolled = !empty($it['enrolled']) || in_array($it['state'], ['progress', 'pending'], true);
?>
<article class="acd-tile is-<?= $h($it['state']) ?>">
    <div class="acd-tile__cover"><img src="<?= $h($tileCover($it)) ?>" alt="" loading="lazy" width="640" height="360"><?= $oxStatePill($it) ?></div>
    <div class="acd-tile__body">
        <?php if (($it['issuer'] ?? '') !== ''): ?><p class="acd-tile__src" translate="no"><?= $h($it['issuer']) ?></p><?php endif ?>
        <h3><a href="<?= $h($oxFiche((string) $it['id'])) ?>" translate="no"><?= $h($it['title']) ?></a></h3>
        <?php if (!$tileEnrolled && ($it['summary'] ?? '') !== ''): ?><p class="acd-tile__sum" translate="no"><?= $h($it['summary']) ?></p><?php endif ?>
        <?php if ($tileEnrolled && $it['pct'] !== null): ?>
            <div class="acd-tile__progress">
                <?= $oxBar((int) $it['pct'], 'Progression') ?>
                <p><b><?= (int) $it['pct'] ?> %</b><?php if ($tileNext !== null): ?> <span class="acd-tile__next"><span>Prochaine étape :</span> <span>chapitre</span> <?= (int) $tileNext['chapter'] ?> · <span><?= $h($t($tileNext['stage'])) ?></span></span><?php elseif ($it['state'] === 'pending'): ?> <span class="acd-tile__next"><span>En attente de validation</span></span><?php endif ?></p>
            </div>
        <?php else: ?>
            <?php $tileMeta = array_filter([$oxDuration($it['duration_min'] ?? null) !== '' ? $h($oxDuration($it['duration_min'])) : '', !empty($it['chapters']) ? (int) $it['chapters'] . ' <span>' . ((int) $it['chapters'] === 1 ? 'chapitre' : 'chapitres') . '</span>' : '']); ?>
            <?php if ($tileMeta !== []): ?><p class="acd-tile__meta"><?= implode('<span aria-hidden="true" class="acd-dot"></span>', $tileMeta) ?></p><?php endif ?>
        <?php endif ?>
        <?php $actionsInDetail = true; require __DIR__ . '/offer_actions.php'; $actionsInDetail = false; ?>
    </div>
</article>
