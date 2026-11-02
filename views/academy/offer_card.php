<?php
/**
 * Carte d'un élément du catalogue Academy.
 *
 * @var array<string, mixed> $it
 */
$cardMeta = [];
if ($it['level'] !== '' && $it['kind'] !== 'issuer') {
    $cardMeta[] = ['Niveau', $oxLevel((string) $it['level'])];
}
if ($oxDuration($it['duration_min'] ?? null) !== '') {
    $cardMeta[] = ['Durée', $h($oxDuration($it['duration_min']))];
}
if (!empty($it['chapters'])) {
    $cardMeta[] = ['Contenu', (int) $it['chapters'] . ' <span>' . ((int) $it['chapters'] === 1 ? 'chapitre' : 'chapitres') . '</span>'];
}
if ($oxValidity($it['validity'] ?? []) !== '') {
    $cardMeta[] = ['Validité', $oxValidity($it['validity'])];
}
if (!empty($it['grade'])) {
    $cardMeta[] = ['Grade', '<span translate="no">' . $h($it['grade']) . '</span>'];
}
if (!empty($it['unit'])) {
    $cardMeta[] = ['Unité', '<span translate="no">' . $h($it['unit']) . '</span>'];
}
$cardKind = $it['kind'] === 'qualification' && in_array('brevets', $it['tabs'], true) ? 'Brevet' : ($it['kind'] === 'course' && ($it['licence'] ?? '') !== '' ? 'Formation à licence' : $oxKinds[$it['kind']]);
?>
<article class="acx-card acx-card--<?= $h($it['kind']) ?> is-<?= $h($it['state']) ?>" data-offer-item>
    <header class="acx-card__head">
        <?= $oxBadge($it) ?>
        <div class="acx-card__title">
            <p class="acx-kicker"><span><?= $h($cardKind) ?></span><?php if (($it['category'] ?? '') !== '' && $it['kind'] !== 'issuer'): ?> · <span translate="no"><?= $h($it['category']) ?></span><?php endif ?></p>
            <h3><a href="<?= $h($oxFiche((string) $it['id'])) ?>" translate="no"><?= $h($it['title']) ?></a></h3>
            <?php if (($it['issuer'] ?? '') !== ''): ?><p class="acx-issuer"><span><?= $it['kind'] === 'issuer' ? 'Rattaché à' : ($it['kind'] === 'qualification' ? 'Émetteur :' : 'Proposé par') ?></span> <span translate="no"><?= $h($it['issuer']) ?></span></p><?php endif ?>
        </div>
        <?= $oxStatePill($it) ?>
    </header>
    <?php if (($it['summary'] ?? '') !== ''): ?><p class="acx-card__sum" translate="no"><?= $h($it['summary']) ?></p><?php endif ?>
    <?php require __DIR__ . '/offer_how.php'; ?>
    <?php if ($it['pct'] !== null && in_array($it['state'], ['progress', 'pending', 'eligible'], true)): ?>
        <div class="acx-progress"><span><span>Progression</span> <b><?= (int) $it['pct'] ?> %</b></span><?= $oxBar((int) $it['pct'], 'Progression') ?></div>
    <?php endif ?>
    <?php if (($it['missing'] ?? []) !== []): ?><p class="acx-missing"><?= $oxSymbol('alert') ?><span><span>Il vous manque :</span><?php foreach ($it['missing'] as $missingLine): ?> <span class="acx-missing__item"><?= $h($missingLine) ?></span><?php endforeach ?></span></p><?php endif ?>
    <?php if ($cardMeta !== []): ?>
    <dl class="acx-meta"><?php foreach ($cardMeta as [$label, $value]): ?><div><dt><?= $h($label) ?></dt><dd><?= $value ?></dd></div><?php endforeach ?></dl>
    <?php endif ?>
    <?php require __DIR__ . '/offer_actions.php'; ?>
</article>
