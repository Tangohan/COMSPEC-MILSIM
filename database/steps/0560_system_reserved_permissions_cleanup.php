<?php

declare(strict_types=1);

/**
 * Étape « Habilitations plateforme retirées des communautés » (Maintenance).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Invariant : aucun rôle de communauté ne porte d’habilitation réservée à la plateforme.
$reservedPermissionsCleanupPath = $root . '/bootstrap/system_reserved_permissions_cleanup.php';
if (is_file($reservedPermissionsCleanupPath)) {
    try {
        $reservedCleanup = require $reservedPermissionsCleanupPath;
        if (is_callable($reservedCleanup)) {
            $reservedReport = $reservedCleanup($pdo);
            $reservedTotal = (int) ($reservedReport['role_links'] ?? 0)
                + (int) ($reservedReport['user_overrides'] ?? 0)
                + (int) ($reservedReport['tenant_permissions'] ?? 0);
            if ($reservedTotal > 0) {
                echo '  [NETTOYÉ] Habilitations plateforme retirées du périmètre communauté : '
                    . (int) ($reservedReport['role_links'] ?? 0) . " lien(s) de rôle, "
                    . (int) ($reservedReport['user_overrides'] ?? 0) . " surcharge(s) utilisateur, "
                    . (int) ($reservedReport['tenant_permissions'] ?? 0) . " permission(s) de tenant.\n";
            }
        }
    } catch (Throwable $e) {
        echo '  [ATTENTION] system_reserved_permissions_cleanup : ' . $e->getMessage() . "\n";
        $migrationRunner->warn('system_reserved_permissions_cleanup', $e);
    }
} else {
    echo "  [ATTENTION] Fichier absent : bootstrap/system_reserved_permissions_cleanup.php — déployez-le puis relancez (purge des habilitations plateforme rattachées à une communauté).\n";
}
echo "Bootstrap plateforme OK (subscription_plans, tenants.*, RBAC 3 couches, community_invitations, moderation_*, security_*, community_code, referral_*…).\n";
$migrationFlush();
