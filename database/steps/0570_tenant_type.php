<?php

declare(strict_types=1);

/**
 * Étape « Profil de communauté Complet / Effectifs / ATAK » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$tenantTypePath = $root . '/bootstrap/tenant_type_migration.php';
echo "Migration tenant_type (profil communauté Complet / Effectifs / ATAK)...\n";
$migrationFlush();
if (!is_file($tenantTypePath)) {
    echo "  [ATTENTION] Fichier absent : bootstrap/tenant_type_migration.php — déployez-le puis relancez (création de communauté bloquée sans tenants.tenant_type).\n";
} else {
    $tenantTypeMigrate = require $tenantTypePath;
    try {
        if (!is_callable($tenantTypeMigrate)) {
            throw new RuntimeException('bootstrap/tenant_type_migration.php doit retourner une callable');
        }
        $tenantTypeMigrate($pdo);
        // Confirmation finale lisible (OK / SKIP déjà loggés dans le bootstrap).
        $hasTt = false;
        try {
            $stTt = $pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'tenant_type' LIMIT 1"
            );
            $hasTt = $stTt !== false && (bool) $stTt->fetchColumn();
        } catch (Throwable) {
            $hasTt = false;
        }
        echo $hasTt
            ? "  [OK] tenant_type : colonne prête (création de communauté)\n"
            : "  [ATTENTION] tenant_type : colonne toujours absente après migration\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] tenant_type : ' . $e->getMessage() . "\n";
        $migrationRunner->warn('tenant_type', $e);
    }
}
$migrationFlush();
