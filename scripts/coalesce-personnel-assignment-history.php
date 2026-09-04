#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Regroupe les tranches d’affectation bruitées (même unité, jours jointifs)
 * sans inventer de dates. Relançable sans danger.
 *
 *   php scripts/coalesce-personnel-assignment-history.php
 *   php scripts/coalesce-personnel-assignment-history.php --apply
 */

$root = dirname(__DIR__);
require_once $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/app.php';

use App\Core\Database;
use App\Repositories\PersonnelAssignmentRepository;

$apply = in_array('--apply', $argv ?? [], true);
$pdo = Database::getPdo();
$assignments = new PersonnelAssignmentRepository();

if (!$assignments->personnelAssignmentsTableExists()) {
    echo "Table des affectations absente.\n";
    exit(0);
}

$stmt = $pdo->query(
    'SELECT DISTINCT usr.tenant_id, pa.user_id
     FROM personnel_assignments pa
     INNER JOIN users usr ON usr.id = pa.user_id
     WHERE usr.tenant_id IS NOT NULL AND usr.tenant_id > 0 AND pa.user_id > 0'
);
$pairs = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

$kept = 0;
$updated = 0;
$deleted = 0;
$skipped = 0;
foreach ($pairs as $pair) {
    $one = $assignments->persistCoalescedHistoryForUser(
        (int) ($pair['tenant_id'] ?? 0),
        (int) ($pair['user_id'] ?? 0),
        !$apply
    );
    $kept += (int) ($one['kept'] ?? 0);
    $updated += (int) ($one['updated'] ?? 0);
    $deleted += (int) ($one['deleted'] ?? 0);
    $skipped += (int) ($one['skipped'] ?? 0);
}

echo ($apply ? 'Application' : 'Simulation') . "\n";
echo 'Dossiers parcourus : ' . count($pairs) . "\n";
echo 'Périodes retenues : ' . $kept . "\n";
echo 'Périodes à regrouper : ' . $updated . "\n";
echo 'Lignes en trop : ' . $deleted . "\n";
echo 'Ignorées : ' . $skipped . "\n";
if (!$apply && ($updated > 0 || $deleted > 0)) {
    echo "Relancez avec --apply pour enregistrer le regroupement.\n";
}

exit(0);
