<?php

declare(strict_types=1);

/**
 * Étape « Grades malaisiens » (Référentiels).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : ajoute au catalogue commun des grades
 * les systèmes MY_CLASSIC (Tentera Darat), MY_TLDM (marine) et MY_TUDM (armée de l’air) et leurs grades
 * (App\Support\OfficialGradeSystems::GRADES) s’ils manquent. Rien d’existant n’est modifié ni supprimé.
 */

if (!class_exists(AppSupportOfficialGradeSystems::class)) {
    require_once $root . '/app/Support/OfficialGradeSystems.php';
}
require_once $root . '/bootstrap/official_grade_systems_seed.php';
$added = run_official_grade_systems_seed($pdo, ['MY_CLASSIC', 'MY_TLDM', 'MY_TUDM']);
echo 'Grades malaisiens : ' . $added['systems'] . ' système(s) et ' . $added['grades'] . " grade(s) ajoutés.\n";
