<?php

declare(strict_types=1);

use App\Core\Database;
use App\Repositories\PersonnelJobRoleRepository;
use App\Services\Personnel\PersonnelJobRoleBootstrapService;

/**
 * Tables rôles métier dossier + colonnes personnel_profiles ; seed par tenant.
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_job_roles'");
    if (!$chk || !$chk->fetch()) {
        $root = dirname(__DIR__);
        $sqlFile = $root . '/migrations/personnel_job_roles_system.sql';
        if (is_file($sqlFile)) {
            echo "Migration personnel_job_roles_system.sql...\n";
            $pdo->exec(file_get_contents($sqlFile));
        }
    }

    $stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_profiles' AND COLUMN_NAME = 'personnel_job_role_id'");
    if ($stmt && !$stmt->fetch()) {
        echo "Colonnes personnel_profiles.personnel_job_role_id / role_sub_label...\n";
        $pdo->exec('ALTER TABLE personnel_profiles ADD COLUMN personnel_job_role_id INT UNSIGNED NULL AFTER secondary_role');
        $pdo->exec('ALTER TABLE personnel_profiles ADD COLUMN role_sub_label VARCHAR(150) NULL AFTER personnel_job_role_id');
        $chkFk = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_profiles' AND CONSTRAINT_NAME = 'pp_personnel_job_role_fk'");
        if ($chkFk && !$chkFk->fetch()) {
            $pdo->exec(
                'ALTER TABLE personnel_profiles ADD CONSTRAINT pp_personnel_job_role_fk FOREIGN KEY (personnel_job_role_id) REFERENCES personnel_job_roles(id) ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }
    }

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
