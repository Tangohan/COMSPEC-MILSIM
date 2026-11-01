<?php

declare(strict_types=1);

/**
 * Étape « Affectations : réparation des membres affectés à moitié ».
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Un double envoi du formulaire
 * d'affectation (page rouverte pendant le rechargement) pouvait laisser un membre « affecté » sur sa fiche
 * mais absent des effectifs de l'unité, des pages Équipes / Groupes et de l'ORBAT, et le réenvoi du
 * formulaire ne le débloquait pas. La vérité est l'affectation active du dossier (personnel_assignments) ;
 * user_units et l'unité du dossier (personnel_profiles.primary_unit_id) sont réalignées dessus
 * (App\Services\Personnel\UnitAssignmentConsistency). Un membre sans affectation active reprend son ancienne
 * ligne d'effectif, sinon l'unité de son dossier. Chaque correction est écrite dans le journal de l'étape.
 * Aucune donnée n'est supprimée : les doublons sont fermés (historique conservé).
 * Idempotente : seuls les membres incohérents sont repris ; une relance ne trouve plus rien.
 */

$unitRepair = new \App\Services\Personnel\UnitAssignmentConsistency($pdo);
if (!$unitRepair->ready()) {
    echo "  [OK] Pas de tables d’affectation : rien à réparer.\n";
} else {
    $unitRepairMembers = $unitRepair->findInconsistent();
    $unitRepairFixed = 0;
    foreach ($unitRepairMembers as $unitRepairMember) {
        $unitRepairLog = $unitRepair->repair($unitRepairMember['user_id'], $unitRepairMember['tenant_id']);
        if ($unitRepairLog === []) {
            continue;
        }
        ++$unitRepairFixed;
        echo '  [OK] Membre #' . $unitRepairMember['user_id'] . ' (communauté #' . $unitRepairMember['tenant_id'] . ') : '
            . implode(' ; ', $unitRepairLog) . "\n";
    }
    echo "  [OK] Affectations : {$unitRepairFixed} membre(s) réparé(s) sur " . count($unitRepairMembers) . " signalé(s).\n";
}
