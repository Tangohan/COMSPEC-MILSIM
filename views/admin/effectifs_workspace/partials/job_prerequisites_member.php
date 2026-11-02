<?php
declare(strict_types=1);
// Fiche membre, carte « Fonctions » : prérequis des emplois tenus (remplis / manquants, progression, dérogation).
// Données : JobPrerequisiteViewData::forMember ($memberJobPrerequisites).
$memberPrereq = is_array($memberJobPrerequisites ?? null) ? $memberJobPrerequisites : [];
$memberPrereqRoles = is_array($memberPrereq['roles'] ?? null) ? $memberPrereq['roles'] : [];
if (empty($memberPrereq['ready']) || $memberPrereqRoles === []) {
    return;
}
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/job-catalog.css')) ?>">
<div class="eff-fm-prereq" aria-label="Prérequis des fonctions">
    <?php foreach ($memberPrereqRoles as $prereqRoleId => $prereqRole): $holderPrereq = $prereqRole['eval'] + ['waived' => !empty($prereqRole['waiver']) && empty($prereqRole['eval']['complete'])]; ?>
    <details <?= empty($prereqRole['eval']['complete']) ? 'open' : '' ?>>
        <summary><b translate="no"><?= $h($prereqRole['name']) ?></b> <?php require __DIR__ . '/job_prereq_meter.php'; ?></summary>
        <ul>
            <?php foreach ($prereqRole['rows'] as $prereqRow): $prereqParts = $prereqRow['parts']; ?>
            <li><span class="eff-prereq-state <?= $prereqRow['met'] === true ? 'is-ok' : ($prereqRow['met'] === false ? 'is-missing' : 'is-unknown') ?>"><?= $prereqRow['met'] === true ? 'Rempli' : ($prereqRow['met'] === false ? 'Manquant' : 'À vérifier') ?></span> <?php require __DIR__ . '/job_prereq_label.php'; ?><?php if ($prereqRow['current'] !== null): ?> <small>(<?= $h(\App\Services\Personnel\JobPrerequisiteService::formatNumber((float) $prereqRow['current'])) ?> h)</small><?php endif ?></li>
            <?php endforeach ?>
        </ul>
        <?php if (!empty($prereqRole['waiver'])): $prereqWaiver = $prereqRole['waiver']; ?>
            <p class="eff-fm-hint">Dérogation accordée le <?= $h((string) $prereqWaiver['granted_at']) ?><?php if (!empty($prereqWaiver['granted_by_name'])): ?> par <span translate="no"><?= $h($prereqWaiver['granted_by_name']) ?></span><?php endif ?>. Motif : <span translate="no"><?= $h($prereqWaiver['reason']) ?></span></p>
            <?php if (!empty($memberPrereq['canWaive'])): ?>
            <form method="post" action="<?= $h(effectifs_workspace_url('fonctions/derogations/revoquer')) ?>" onsubmit="return confirm('Révoquer cette dérogation ? Le membre garde la fonction.');">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>"><input type="hidden" name="waiver_id" value="<?= (int) $prereqWaiver['id'] ?>"><input type="hidden" name="membre" value="<?= (int) $id ?>">
                <label class="eff-fm-hint">Motif de la révocation (facultatif) <input name="revoke_reason" maxlength="500"></label>
                <button class="eff-fm-btn is-sm">Révoquer la dérogation</button>
            </form>
            <?php endif ?>
        <?php endif ?>
    </details>
    <?php endforeach ?>
</div>
