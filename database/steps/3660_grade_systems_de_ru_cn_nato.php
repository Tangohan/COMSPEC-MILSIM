<?php

declare(strict_types=1);

/**
 * Étape « Grades allemands, russes, chinois et OTAN génériques » (Référentiels).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : ajoute au catalogue commun des grades
 * les systèmes DE_HEER, DE_MARINE, DE_LW (Bundeswehr), RU_SV, RU_VMF, RU_VKS (Russie), CN_PLA, CN_PLAN, CN_PLAAF
 * (Armée populaire de libération) et NATO_GENERIC (échelle OTAN OR / WO / OF) et leurs grades
 * (App\Support\OfficialGradeSystems::GRADES) s’ils manquent. Rien d’existant n’est modifié ni supprimé.
 */

if (!class_exists(\App\Support\OfficialGradeSystems::class)) {
    require_once $root . '/app/Support/OfficialGradeSystems.php';
}
require_once $root . '/bootstrap/official_grade_systems_seed.php';
$added = run_official_grade_systems_seed($pdo, [
    'DE_HEER', 'DE_MARINE', 'DE_LW',
    'RU_SV', 'RU_VMF', 'RU_VKS',
    'CN_PLA', 'CN_PLAN', 'CN_PLAAF',
    'NATO_GENERIC',
]);
echo 'Grades allemands, russes, chinois et OTAN : ' . $added['systems'] . ' système(s) et ' . $added['grades'] . " grade(s) ajoutés.\n";
