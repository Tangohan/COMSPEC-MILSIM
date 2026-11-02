<?php
/**
 * Lignes « comment l'obtenir » d'un élément du catalogue.
 *
 * @var array<string, mixed> $it
 * @var bool $howFull fiche détaillée : toutes les lignes, avec l'avancement de chaque critère
 */
$howFull = $howFull ?? false;
$howLines = $it['how'] ?? [];
$howShown = $howFull ? $howLines : array_slice($howLines, 0, 3);
$criterionNames = $offer['names'] ?? [];
if ($howShown === [] && !in_array($it['kind'], ['pathway', 'competency', 'issuer'], true)) {
    return;
}
?>
<ul class="acx-how<?= $howFull ? ' acx-how--full' : '' ?>">
<?php $howPrev = ''; foreach ($howShown as $line): $met = !empty($line['met']); $howAuto = in_array($line['type'], ['course', 'qualification', 'metric'], true); ?>
<?php if ($howFull && $howAuto && $howPrev === ''): ?><li class="acx-how__group"><?= !empty($it['rule_any']) ? 'Automatiquement, dès que l’une de ces conditions est remplie :' : 'Automatiquement, quand toutes ces conditions sont remplies :' ?></li><?php endif ?>
<?php if ($howFull && !$howAuto && $howPrev === 'auto'): ?><li class="acx-how__group">Ou :</li><?php endif ?>
<?php $howPrev = $howAuto ? 'auto' : 'other'; ?>
    <li class="acx-how__line<?= $met ? ' is-met' : '' ?>">
        <span class="acx-how__mark" aria-hidden="true"><?= $met ? $oxSymbol('check') : '' ?></span>
        <span class="acx-how__text">
        <?php if ($line['type'] === 'academy_course'): ?>
            <span>Suivre la formation et réussir l’évaluation</span>
        <?php elseif ($line['type'] === 'lms_course'): ?>
            <span>Suivre la formation</span><?php if (!empty($line['certifying'])): ?> · <span>attestation délivrée à la fin</span><?php endif ?>
        <?php elseif ($line['type'] === 'course'): ?>
            <span>Suivre la formation</span>
            <?php if (($line['url'] ?? '') !== ''): ?><a href="<?= $h($line['url']) ?>" translate="no"><?= $h($line['label']) ?></a><?php else: ?><b translate="no"><?= $h($line['label']) ?></b><?php endif ?>
        <?php elseif ($line['type'] === 'qualification'): ?>
            <span>Détenir la qualification</span>
            <?php if (($line['detail'] ?? '') !== ''): ?><a href="<?= $h($oxFiche($line['detail'])) ?>" translate="no"><?= $h($line['label']) ?></a><?php else: ?><b translate="no"><?= $h($line['label']) ?></b><?php endif ?>
        <?php elseif ($line['type'] === 'metric'): $criterion = $line['criterion']; ?>
            <?php if (!$howFull): ?><span>Automatique :</span> <?php endif ?><?php require base_path('views/partials/qualification_criterion.php'); ?>
            <?php if ($howFull && $line['current'] !== null): ?>
                <span class="acx-how__count"><?= $h(\App\Support\QualificationCriteriaCatalog::formatNumber((float) $line['current'])) ?> / <?= $h(\App\Support\QualificationCriteriaCatalog::formatNumber((float) $line['target'])) ?></span>
                <?= $oxBar((int) $line['pct']) ?>
            <?php endif ?>
        <?php elseif ($line['type'] === 'pilot'): ?>
            <span>Cursus pilote : selon vos heures de vol</span>
        <?php elseif ($line['type'] === 'manual'): ?>
            <span>Délivrée par un instructeur</span><?php if (($line['issuer'] ?? '') !== ''): ?> · <b translate="no"><?= $h($line['issuer']) ?></b><?php endif ?>
        <?php endif ?>
        </span>
    </li>
<?php endforeach ?>
<?php if (!$howFull && count($howLines) > 3): ?><li class="acx-how__more"><span>et</span> <?= count($howLines) - 3 ?> <span>autres conditions</span></li><?php endif ?>
<?php if ($it['kind'] === 'pathway'): ?>
    <li class="acx-how__info"><?= count($it['steps'] ?? []) ?> <span><?= count($it['steps'] ?? []) === 1 ? 'étape' : 'étapes' ?></span></li>
<?php elseif ($it['kind'] === 'competency' && ($it['links'] ?? []) !== []): ?>
    <li class="acx-how__info"><span>S’acquiert avec</span> <?= count($it['links']) ?> <span><?= count($it['links']) === 1 ? 'élément lié' : 'éléments liés' ?></span></li>
<?php elseif ($it['kind'] === 'issuer'): ?>
    <li class="acx-how__info"><?= (int) ($it['count'] ?? 0) ?> <span><?= (int) ($it['count'] ?? 0) === 1 ? 'qualification délivrée' : 'qualifications délivrées' ?></span></li>
<?php endif ?>
</ul>
