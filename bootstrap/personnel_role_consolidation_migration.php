<?php

declare(strict_types=1);

use App\Repositories\PersonnelJobRoleRepository;

/**
 * Fusion des champs "rôle métier" dupliqués sur personnel_profiles (primary_role, secondary_role,
 * personnel_job_role_id, role_sub_label) vers l'unique source de vérité : la table pivot
 * personnel_profile_job_roles + le référentiel personnel_job_roles.
 *
 * Reprise de données : tout texte libre (primary_role/secondary_role) sans ligne pivot existante
 * pour l'utilisateur devient une entrée référentiel « Importé » (is_system=0), rien n'est perdu.
 * Idempotent : si les colonnes n'existent déjà plus, ne fait rien.
 */
return function (PDO $pdo): void {
    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasColumn('personnel_profiles', 'primary_role')) {
        // Déjà migré (colonnes supprimées).
        return;
    }
    if (!$hasTable('personnel_job_roles') || !$hasTable('personnel_profile_job_roles')) {
        echo "  [ATTENTION] personnel_role_consolidation : référentiel rôles métier absent, migration reportée.\n";

        return;
    }

    require_once dirname(__DIR__) . '/bootstrap/autoload.php';
    $jobRoleRepo = new PersonnelJobRoleRepository();

    $hasSecondaryCol = $hasColumn('personnel_profiles', 'secondary_role');
    $select = 'pp.user_id, u.tenant_id, pp.primary_role' . ($hasSecondaryCol ? ', pp.secondary_role' : ', NULL AS secondary_role');
    $stmt = $pdo->query(
        "SELECT $select
         FROM personnel_profiles pp
         INNER JOIN users u ON u.id = pp.user_id
         WHERE (pp.primary_role IS NOT NULL AND TRIM(pp.primary_role) <> '')
            OR (" . ($hasSecondaryCol ? "pp.secondary_role IS NOT NULL AND TRIM(pp.secondary_role) <> ''" : '0') . ')'
    );
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $pivotCountStmt = $pdo->prepare('SELECT COUNT(*) FROM personnel_profile_job_roles WHERE tenant_id = ? AND user_id = ?');
    $insertPivot = $pdo->prepare(
        'INSERT INTO personnel_profile_job_roles (tenant_id, user_id, personnel_job_role_id, is_primary, sort_order, role_detail)
         VALUES (?, ?, ?, ?, ?, NULL)'
    );

    $imported = 0;
    foreach ($rows as $row) {
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $userId = (int) ($row['user_id'] ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            continue;
        }
        $pivotCountStmt->execute([$tenantId, $userId]);
        if ((int) $pivotCountStmt->fetchColumn() > 0) {
            // Ce membre a déjà des lignes pivot (déjà migrées via personnel_job_role_id, ou déjà
            // gérées manuellement) : on ne duplique pas à partir du texte libre.
            continue;
        }
        $primaryText = trim((string) ($row['primary_role'] ?? ''));
        $secondaryText = trim((string) ($row['secondary_role'] ?? ''));
        $sort = 0;
        try {
            if ($primaryText !== '') {
                $rid = $jobRoleRepo->findOrCreateImportedRoleByLabel($tenantId, $primaryText);
                if ($rid !== null) {
                    $insertPivot->execute([$tenantId, $userId, $rid, 1, $sort++]);
                    $imported++;
                }
            }
            if ($secondaryText !== '' && $secondaryText !== $primaryText) {
                $rid = $jobRoleRepo->findOrCreateImportedRoleByLabel($tenantId, $secondaryText);
                if ($rid !== null) {
                    $insertPivot->execute([$tenantId, $userId, $rid, 0, $sort++]);
                    $imported++;
                }
            }
        } catch (Throwable $e) {
            echo '  [ATTENTION] personnel_role_consolidation backfill user ' . $userId . ' : ' . $e->getMessage() . "\n";
        }
    }
    echo "  [OK] personnel_role_consolidation : $imported ligne(s) reprise(s) depuis le texte libre.\n";

    try {
        $fkStmt = $pdo->query(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_profiles' AND CONSTRAINT_NAME = 'pp_personnel_job_role_fk'"
        );
        if ($fkStmt && $fkStmt->fetch()) {
            $pdo->exec('ALTER TABLE personnel_profiles DROP FOREIGN KEY pp_personnel_job_role_fk');
        }
        foreach (['primary_role', 'secondary_role', 'personnel_job_role_id', 'role_sub_label'] as $col) {
            if ($hasColumn('personnel_profiles', $col)) {
                $pdo->exec("ALTER TABLE personnel_profiles DROP COLUMN `$col`");
            }
        }
        echo "  [OK] personnel_role_consolidation : colonnes dupliquées supprimées de personnel_profiles.\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] personnel_role_consolidation drop columns : ' . $e->getMessage() . "\n";
    }
};
