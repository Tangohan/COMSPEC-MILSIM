<?php

declare(strict_types=1);

/**
 * Étape « Outils opérateur » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/operator_hub_migration.php (outils épinglés par membre ; état posé par le membre sur ses identités
 * d'emprunt et ses couvertures), rejoué sans effet si les tables sont en place.
 */

$operatorHubMigrate = require $root . '/bootstrap/operator_hub_migration.php';
try {
    echo "Migration operator_hub (outils épinglés, état des identités et couvertures)…\n";
    $operatorHubMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] operator_hub : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('operator_hub', $e);
}
$migrationEnsurePdo();
