<?php
declare(strict_types=1);

/**
 * Progression d'un membre vers les qualifications automatiques (fiche du membre, « Mes qualifications »).
 * Rendu dans une fonction : aucune variable de la page qui l'inclut n'est écrasée.
 *
 * @var list<array<string, mixed>> $qualificationProgress
 * @var array<string, mixed> $progressNames
 */
(static function (array $qpItems, array $criterionNames): void {
    if ($qpItems === []) {
        return;
    }
    $qpe = static fn (mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $qpFmt = static fn (float $v): string => \App\Support\QualificationCriteriaCatalog::formatNumber($v);
    ?>
<div class="qpg" aria-label="Progression vers les qualifications">
    <?php foreach ($qpItems as $it):
        $def = $it['definition'];
        $state = (string) $it['state'];
        ?>
    <article class="qpg-item">
        <div class="qpg-head">
            <b><?= $qpe($def['name'] ?? '') ?></b>
            <?php if ($state === \App\Services\Personnel\QualificationCriteriaEngine::PENDING): ?>
                <span class="qfs-tag qfs-tag--info">À valider par un responsable</span>
            <?php elseif ($it['met'] && $it['prereq']['ok']): ?>
                <span class="qfs-tag qfs-tag--ok">Conditions remplies, délivrée au prochain passage</span>
            <?php else: ?>
                <span class="qfs-tag qfs-tag--info"><?= (int) $it['pct'] ?> %</span>
            <?php endif; ?>
        </div>
        <div class="qpg-bar<?= $it['met'] ? ' is-met' : '' ?>" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $it['pct'] ?>"><i style="width:<?= max(2, min(100, (int) $it['pct'])) ?>%"></i></div>
        <ul class="qpg-crit">
            <?php foreach ($it['eval']['groups'] ?? [] as $g): foreach ($g['items'] as $ci):
                $criterion = $ci['criterion'];
                $isMetric = ($criterion['type'] ?? 'metric') === 'metric';
                ?>
            <li class="<?= $ci['met'] ? 'is-met' : '' ?>">
                <?php require __DIR__ . '/qualification_criterion.php'; ?>
                <span><?php if ($isMetric): ?><?= $qpe($qpFmt((float) $ci['current'])) ?> / <?= $qpe($qpFmt((float) $ci['target'])) ?><?php else: ?><?= $ci['met'] ? 'Fait' : 'À faire' ?><?php endif; ?></span>
            </li>
            <?php endforeach; endforeach; ?>
        </ul>
        <?php if (!$it['prereq']['ok']): ?>
            <p class="qpg-missing">Prérequis manquants : <?= $qpe(implode(', ', $it['prereq']['missing'])) ?></p>
        <?php endif; ?>
    </article>
    <?php endforeach; ?>
</div>
    <?php
})(is_array($qualificationProgress ?? null) ? $qualificationProgress : [], is_array($progressNames ?? null) ? $progressNames : []);
