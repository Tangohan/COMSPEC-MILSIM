<?php
declare(strict_types=1);

/**
 * Fiche du niveau ouvert, en lecture : description détaillée, étiquettes, qualifications, emplois, grades, complétude.
 * Variables : $selCardView (carte du niveau, AccessRoleProfileView), $h, $canManage, $rolReady.
 */

use App\Services\Rbac\AccessRoleProfileService;

if ($selCardView === null) {
    return;
}
$sp = $selCardView['profile'];
$sheetDetails = AccessRoleProfileService::detailsHtml((string) $sp['details']);
$sheetDone = (int) $selCardView['completeness']['done'];
$sheetTotal = (int) $selCardView['completeness']['total'];
?>
<section class="rol-sheet" aria-labelledby="rol-sheet-title">
    <div class="rol-sheet__head">
        <span class="rol-mark rol-mark--lg" aria-hidden="true"><?php if ($selCardView['logo_url'] !== null): ?><img src="<?= $h($selCardView['logo_url']) ?>" alt="" width="56" height="56"><?php elseif (!empty($sp['icon'])): ?><?= \App\Support\JobRoleIconLibrary::svg((string) $sp['icon'], 'rol-mark__svg') ?><?php else: ?><span class="rol-mark__initial"><?= $h(mb_strtoupper(mb_substr((string) $selCardView['name'], 0, 1))) ?></span><?php endif; ?></span>
        <div class="rol-sheet__titles">
            <h3 class="rol-sheet__title" id="rol-sheet-title">Fiche du niveau<?php if (!empty($sp['abbreviation'])): ?> <span class="rol-abbr" translate="no"><?= $h($sp['abbreviation']) ?></span><?php endif; ?></h3>
            <?php if ($selCardView['description'] !== ''): ?><p class="rol-sheet__desc" translate="no"><?= $h($selCardView['description']) ?></p><?php else: ?><p class="rol-sheet__desc">Aucune description courte.</p><?php endif; ?>
        </div>
        <?php if ($rolReady): ?>
        <div class="rol-meter rol-meter--wide" title="<?= $h($selCardView['completeness']['missing'] !== [] ? 'À renseigner : ' . implode(', ', $selCardView['completeness']['missing']) : 'Fiche complète') ?>">
            <span class="rol-meter__bar" aria-hidden="true"><span class="rol-meter__fill" style="--rol-p:<?= (int) round($sheetDone * 100 / max(1, $sheetTotal)) ?>%"></span></span>
            <span class="rol-meter__txt"><?= $sheetDone ?>/<?= $sheetTotal ?> champs renseignés</span>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($sheetDetails !== ''): ?>
    <div class="rol-sheet__rich" translate="no"><?= $sheetDetails ?></div>
    <?php endif; ?>
    <?php if ($sp['tags'] !== []): ?><p class="rol-card__tags"><?= \App\Support\JobRoleBadge::tags($sp['tags']) ?></p><?php endif; ?>
    <?php if ($selCardView['qualification_labels'] !== [] || $selCardView['job_labels'] !== [] || $selCardView['grade_label'] !== ''): ?>
    <dl class="rol-card__facts rol-sheet__facts">
        <?php foreach (AccessRoleProfileService::KINDS as $kind => $klabel): if (empty($selCardView['qualification_labels'][$kind])) { continue; } ?>
        <div><dt><?= $h($kind === 'required' ? 'Qualifications requises' : ($kind === 'recommended' ? 'Qualifications recommandées' : 'Qualifications délivrées')) ?></dt><dd translate="no"><?= $h(implode(', ', $selCardView['qualification_labels'][$kind])) ?></dd></div>
        <?php endforeach; ?>
        <?php if ($selCardView['job_labels'] !== []): ?><div><dt>Emplois associés</dt><dd translate="no"><?= $h(implode(', ', $selCardView['job_labels'])) ?></dd></div><?php endif; ?>
        <?php if ($selCardView['grade_label'] !== ''): ?><div><dt>Grades attendus</dt><dd><?= $h($selCardView['grade_label']) ?></dd></div><?php endif; ?>
    </dl>
    <?php elseif ($rolReady && $sheetDone < $sheetTotal): ?>
    <p class="rl-hint">À renseigner : <?= $h(implode(', ', $selCardView['completeness']['missing'])) ?>.<?php if (!empty($canManage)): ?> Utilisez « Modifier la fiche ».<?php endif; ?></p>
    <?php endif; ?>
</section>
