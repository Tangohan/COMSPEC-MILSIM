<?php

declare(strict_types=1);

/**
 * Étape « Les permissions créées par les migrations PHP n’ont que `slug` : recopier dans `code` (colonne héritée) » (Maintenance).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Les permissions créées par les migrations PHP n’ont que `slug` : recopier dans `code` (colonne héritée).
$migrationRunner->step('permissions.code', static function () use ($pdo): void {
    $hasCode = (bool) $pdo->query(
        "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'permissions' AND COLUMN_NAME = 'code' LIMIT 1"
    )->fetchColumn();
    if ($hasCode) {
        $n = $pdo->exec("UPDATE IGNORE permissions SET code = slug WHERE (code IS NULL OR code = '') AND slug IS NOT NULL AND slug <> ''");
        echo '  [OK] permissions.code complété depuis slug : ' . (int) $n . " ligne(s)\n";
    }
});
