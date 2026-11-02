<?php
declare(strict_types=1);
// Fenêtre « Fonctions de … » : prérequis manquants des fonctions cochées (affichés par assets/js/job-catalog.js)
// et motif de dérogation. Contrôle définitif côté serveur (JobPrerequisiteService::gate).
$memberPrereq = is_array($memberJobPrerequisites ?? null) ? $memberJobPrerequisites : [];
$memberPrereqPicker = is_array($memberPrereq['picker'] ?? null) ? $memberPrereq['picker'] : [];
if (empty($memberPrereq['ready']) || $memberPrereqPicker === []) {
    return;
}
$memberPrereqHeld = [];
foreach ($jobRoles as $jr) {
    $memberPrereqHeld[(int) ($jr['personnel_job_role_id'] ?? 0)] = true;
}
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/job-catalog.css')) ?>">
<div class="eff-prereq-dialog" data-prereq-picker data-prereq-blocking="<?= !empty($memberPrereq['blocking']) ? '1' : '0' ?>" role="group" aria-labelledby="eff-prereq-dialog-title" hidden>
    <p id="eff-prereq-dialog-title"><strong>Prérequis non remplis</strong> — <?= !empty($memberPrereq['blocking']) ? 'votre communauté les rend bloquants : indiquez un motif pour attribuer quand même.' : 'ils sont indicatifs : vous pouvez attribuer, ou enregistrer une dérogation motivée.' ?></p>
    <?php foreach ($memberPrereqPicker as $prereqRoleId => $prereqMissing): ?>
    <div data-prereq-missing="<?= (int) $prereqRoleId ?>"<?= isset($memberPrereqHeld[(int) $prereqRoleId]) ? ' data-prereq-held' : '' ?> hidden>
        <ul><?php foreach ($prereqMissing as $prereqRow): $prereqParts = $prereqRow['parts']; ?><li><?php require __DIR__ . '/job_prereq_label.php'; ?></li><?php endforeach ?></ul>
    </div>
    <?php endforeach ?>
    <?php if (!empty($memberPrereq['canWaive'])): ?>
    <label>Motif de la dérogation<textarea name="prereq_waiver_reason" rows="2" maxlength="500" minlength="5" placeholder="Ex. ancien pilote d’une autre communauté, qualification en cours de transfert"></textarea></label>
    <p class="eff-card__hint">La dérogation est enregistrée avec votre nom, visible sur la fiche de l’emploi et du membre, et révocable.</p>
    <?php endif ?>
</div>
<script src="<?= $h(asset_url('assets/js/job-catalog.js')) ?>" defer></script>
