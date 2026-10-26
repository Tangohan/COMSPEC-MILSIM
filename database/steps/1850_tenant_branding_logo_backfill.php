<?php

declare(strict_types=1);

/**
 * Étape « Synchroniser tenant_branding.logo_url depuis tenants.logo_url (première passe) » (Maintenance).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Synchroniser tenant_branding.logo_url depuis tenants.logo_url (première passe)
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_branding'");
if ($stmt && $stmt->fetch()) {
    $chkEmpty = $pdo->query('SELECT COUNT(*) c FROM tenant_branding')->fetch(PDO::FETCH_ASSOC);
    if ($chkEmpty && (int) ($chkEmpty['c'] ?? 0) === 0) {
        echo "Seed tenant_branding depuis tenants.logo_url...\n";
        $pdo->exec(
            'INSERT INTO tenant_branding (tenant_id, logo_url, updated_at)
             SELECT id, logo_url, NOW() FROM tenants WHERE logo_url IS NOT NULL AND TRIM(logo_url) <> \'\''
        );
    }
}
