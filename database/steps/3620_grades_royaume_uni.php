<?php

declare(strict_types=1);

/**
 * Étape « Grades britanniques » (Référentiels).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : ajoute au catalogue commun des grades
 * les systèmes GB_ARMY (British Army et Royal Marines), GB_RN (Royal Navy) et GB_RAF (Royal Air Force) et leurs grades
 * (App\Support\OfficialGradeSystems::GRADES) s’ils manquent. Rien d’existant n’est modifié ni supprimé.
 */

require_once $root . '/bootstrap/official_grade_systems_seed.php';
$gbGradesAdded = run_official_grade_systems_seed($pdo, ['GB_ARMY', 'GB_RN', 'GB_RAF']);
echo 'Grades britanniques : ' . $gbGradesAdded['systems'] . ' système(s) et ' . $gbGradesAdded['grades'] . " grade(s) ajoutés.\n";
