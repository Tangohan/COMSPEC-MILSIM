<?php

declare(strict_types=1);

use App\Core\Database;
use App\Repositories\PersonnelJobRoleRepository;
use App\Services\Personnel\PersonnelJobRoleBootstrapService;

/**
 * Tables rôles métier dossier + colonnes personnel_profiles ; seed par tenant.
 */
return function (PDO $pdo): void {
    require_once __DIR__ . '/personnel_profile_job_roles_migration.php';
    try {
        run_personnel_profile_job_roles_migration($pdo);
    } catch (Throwable $e) {
        echo '  [ATTENTION] personnel_profile_job_roles migration : ' . $e->getMessage() . "\n";
    }

    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_job_roles'");
    if (!$chk || !$chk->fetch()) {
        $root = dirname(__DIR__);
        $sqlFile = $root . '/migrations/personnel_job_roles_system.sql';
        if (is_file($sqlFile)) {
            echo "Migration personnel_job_roles_system.sql...\n";
            $pdo->exec(file_get_contents($sqlFile));
        }
    }

    // Historique : ce bloc recréait personnel_profiles.personnel_job_role_id / role_sub_label.
    // Ces colonnes ont été fusionnées dans la table pivot personnel_profile_job_roles
    // (voir bootstrap/personnel_role_consolidation_migration.php, qui les supprime) — ne plus
    // les recréer ici, sous peine de défaire cette fusion à chaque exécution des migrations.

    require_once dirname(__DIR__) . '/bootstrap/autoload.php';
    $repo = new PersonnelJobRoleRepository();
    if (!$repo->tablesExist()) {
        return;
    }
    $boot = new PersonnelJobRoleBootstrapService($repo);
    $tenants = $pdo->query('SELECT id FROM tenants');
    if ($tenants) {
        while ($t = $tenants->fetch(PDO::FETCH_ASSOC)) {
            $boot->ensureDefaultsForTenant($pdo, (int) $t['id']);
        }
    }
    echo "Rôles métier dossier (seed par tenant) OK.\n";
};
