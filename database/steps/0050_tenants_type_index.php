<?php

declare(strict_types=1);

/**
 * Étape « Index sur tenants.tenant_type » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

try {
    $idxTt = $pdo->query(
        "SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND INDEX_NAME = 'idx_tenants_type' LIMIT 1"
    );
    if ($idxTt && !$idxTt->fetchColumn()) {
        $pdo->exec('ALTER TABLE tenants ADD INDEX idx_tenants_type (tenant_type)');
        echo "  [OK] index idx_tenants_type ajouté\n";
    }
} catch (Throwable $e) {
    echo '  [ATTENTION] idx_tenants_type (ensure) : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('idx_tenants_type (ensure)', $e);
}
$migrationFlush();
