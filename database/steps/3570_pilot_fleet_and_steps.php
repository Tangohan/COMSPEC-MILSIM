<?php

declare(strict_types=1);

/**
 * Étape « Cursus pilote : appareils, familles et paliers de la communauté » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : les tables ne sont créées que si
 * elles manquent, depuis le seul fichier migrations/2026_11_02_120_pilot_fleet_and_steps.sql (CREATE TABLE IF NOT
 * EXISTS). Aucune ligne n'est créée ni supprimée : les cursus déjà installés gardent leurs heures et leurs règles.
 * Sans le référentiel des qualifications (personnel_qualification_definitions), rien n'est créé.
 */

$pilotFleetSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$pilotFleetTables = ['pilot_aircraft_groups', 'pilot_aircraft', 'pilot_aircraft_classes', 'pilot_syllabus_steps'];
$pilotFleetMissing = array_values(array_filter($pilotFleetTables, static fn (string $t): bool => !$pilotFleetSchema->tableExists($t)));
if ($pilotFleetMissing === []) {
    echo "  [OK] Tables de la flotte et des paliers du cursus pilote déjà présentes.\n";
} elseif (!$pilotFleetSchema->tableExists('personnel_qualification_definitions')) {
    echo "  [OK] Référentiel des qualifications absent : rien à créer.\n";
} else {
    $pilotFleetSchema->runSqlFile($root . '/migrations/2026_11_02_120_pilot_fleet_and_steps.sql');
    echo '  [OK] Tables créées : ' . implode(', ', $pilotFleetMissing) . ".\n";
}
