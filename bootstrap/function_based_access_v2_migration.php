<?php

declare(strict_types=1);

use App\Services\AccessControl\FunctionBasedAccessCatalog;

/**
 * Reversible FBAC v2 transition. The immutable snapshots are deliberately kept after migration.
 * Roles remain as compatibility/display records, but their grants are rebuilt solely from tiers.
 */
function run_function_based_access_v2_migration(PDO $pdo): void
{
    FunctionBasedAccessCatalog::assertInvariants();
    $pdo->exec("CREATE TABLE IF NOT EXISTS access_control_v2_role_snapshot (
        snapshot_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, migrated_at DATETIME NOT NULL,
        role_id INT UNSIGNED NOT NULL, tenant_id INT UNSIGNED NULL, role_slug VARCHAR(190) NOT NULL,
        role_name VARCHAR(190) NOT NULL, role_created_at DATETIME NULL, origin_kind VARCHAR(16) NOT NULL,
        permission_slugs_json LONGTEXT NOT NULL, assigned_users_count INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY(snapshot_id), KEY acv2_snapshot_role(role_id), KEY acv2_snapshot_date(migrated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS access_control_v2_site_assignment_snapshot (
        assignment_id INT UNSIGNED NOT NULL, email_normalized VARCHAR(255) NOT NULL, role_id INT UNSIGNED NOT NULL,
        assigned_by_user_id INT UNSIGNED NULL, created_at DATETIME NOT NULL, revoked_at DATETIME NULL,
        snapshotted_at DATETIME NOT NULL, PRIMARY KEY(assignment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS access_control_v2_snapshot_state (
        snapshot_key VARCHAR(64) NOT NULL, snapshotted_at DATETIME NOT NULL, PRIMARY KEY(snapshot_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS access_control_v2_user_primary_role_snapshot (
        user_id INT UNSIGNED NOT NULL, role_id INT UNSIGNED NULL, snapshotted_at DATETIME NOT NULL,
        PRIMARY KEY(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS access_control_v2_user_role_snapshot (
        user_id INT UNSIGNED NOT NULL, role_id INT UNSIGNED NOT NULL, source_kind VARCHAR(24) NOT NULL,
        snapshotted_at DATETIME NOT NULL, PRIMARY KEY(user_id,role_id,source_kind)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS access_control_v2_tenant_user_role_snapshot (
        tenant_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, role_id INT UNSIGNED NOT NULL,
        org_unit_id INT UNSIGNED NULL, co_unit_id INT UNSIGNED NOT NULL DEFAULT 0, created_at DATETIME NULL,
        snapshotted_at DATETIME NOT NULL, PRIMARY KEY(tenant_id,user_id,role_id,co_unit_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS access_control_v2_user_access_roles (
        tenant_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, access_role_slug VARCHAR(64) NOT NULL,
        legacy_role_id INT UNSIGNED NULL, migrated_at DATETIME NOT NULL,
        PRIMARY KEY(tenant_id,user_id), KEY acv2_user_access_role(tenant_id,access_role_slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS access_control_sensitive_audit (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, tenant_id INT UNSIGNED NULL, actor_user_id INT UNSIGNED NOT NULL,
        function_slug VARCHAR(190) NOT NULL, target_type VARCHAR(100) NOT NULL, target_id VARCHAR(190) NOT NULL,
        occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, metadata_json LONGTEXT NULL,
        PRIMARY KEY(id), KEY acsa_actor_time(actor_user_id,occurred_at), KEY acsa_function_time(function_slug,occurred_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $already = (int) $pdo->query('SELECT COUNT(*) FROM access_control_v2_role_snapshot')->fetchColumn();
    if ($already > 0) {
        echo "  [OK] FBAC v2 : snapshot existant conservé, invariants réappliqués\n";
    }

    $pdo->beginTransaction();
    try {
        // Snapshot happens before any grant is changed. A slug found in the repository is a seeded/code role.
        $seeded = ['member','community_owner','tenant_admin','deputy_commander','trainer','instructor','senior_instructor','recruiter','hr','super_admin','site_super_admin'];
        $seedPlaceholders = implode(',', array_fill(0, count($seeded), '?'));
        if ($already === 0) {
            $snapshot = $pdo->prepare("INSERT INTO access_control_v2_role_snapshot
                (migrated_at,role_id,tenant_id,role_slug,role_name,role_created_at,origin_kind,permission_slugs_json,assigned_users_count)
                SELECT NOW(),r.id,r.tenant_id,r.slug,r.name,r.created_at,
                  CASE WHEN r.slug IN ({$seedPlaceholders}) THEN 'code' ELSE 'dynamic' END,
                  COALESCE((SELECT JSON_ARRAYAGG(p.slug) FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=r.id),JSON_ARRAY()),
                  (SELECT COUNT(DISTINCT x.user_id) FROM (SELECT user_id,role_id FROM user_roles UNION ALL SELECT id,role_id FROM users WHERE role_id IS NOT NULL) x WHERE x.role_id=r.id)
                FROM roles r");
            $snapshot->execute($seeded);
            $pdo->exec("INSERT INTO access_control_v2_site_assignment_snapshot
                (assignment_id,email_normalized,role_id,assigned_by_user_id,created_at,revoked_at,snapshotted_at)
                SELECT id,email_normalized,role_id,assigned_by_user_id,created_at,revoked_at,NOW() FROM site_role_assignments");
        }

        $assignmentSnapshotDone = $pdo->query("SELECT 1 FROM access_control_v2_snapshot_state WHERE snapshot_key='user_assignments' LIMIT 1")->fetchColumn();
        if (!$assignmentSnapshotDone) {
            $pdo->exec("INSERT INTO access_control_v2_user_primary_role_snapshot(user_id,role_id,snapshotted_at)
                SELECT id,role_id,NOW() FROM users");
            $pdo->exec("INSERT INTO access_control_v2_user_role_snapshot(user_id,role_id,source_kind,snapshotted_at)
                SELECT user_id,role_id,'user_roles',NOW() FROM user_roles");
            $pdo->exec("INSERT INTO access_control_v2_tenant_user_role_snapshot
                (tenant_id,user_id,role_id,org_unit_id,co_unit_id,created_at,snapshotted_at)
                SELECT tenant_id,user_id,role_id,org_unit_id,co_unit_id,created_at,NOW() FROM tenant_user_roles");
            $pdo->exec("INSERT INTO access_control_v2_snapshot_state(snapshot_key,snapshotted_at) VALUES ('user_assignments',NOW())");
        }

        // Ensure the closed catalogue exists for every tenant before assignment and grants.
        $roleInsert = $pdo->prepare("INSERT IGNORE INTO roles
            (tenant_id,name,slug,description,is_system,is_locked,role_layer,created_at)
            SELECT id,?,?,?,1,1,'community',NOW() FROM tenants");
        foreach (FunctionBasedAccessCatalog::roles() as $role) {
            $roleInsert->execute([$role['label'], $role['role_slug'], 'Rôle d’accès classique ATHENA.']);
        }

        // One classic access role per user. Unknown/custom/military roles map to Member.
        $pdo->exec("INSERT INTO access_control_v2_user_access_roles
            (tenant_id,user_id,access_role_slug,legacy_role_id,migrated_at)
            SELECT u.tenant_id,u.id,
              CASE WHEN r.slug='community_owner' THEN 'community_owner'
                   WHEN r.slug IN ('tenant_admin','deputy_commander') THEN 'tenant_admin'
                   WHEN r.slug IN ('trainer','instructor','senior_instructor') THEN 'trainer'
                   WHEN r.slug='recruiter' THEN 'recruiter'
                   WHEN r.slug='hr' THEN 'hr'
                   WHEN r.slug='deputy_hr' THEN 'deputy_hr' ELSE 'member' END,
              r.id,NOW()
            FROM users u LEFT JOIN roles r ON r.id=u.role_id WHERE u.tenant_id IS NOT NULL
            ON DUPLICATE KEY UPDATE access_role_slug=VALUES(access_role_slug),legacy_role_id=VALUES(legacy_role_id),migrated_at=VALUES(migrated_at)");

        // No platform-wide principal survives. Tenant roles lose reserved/aggregate grants.
        $pdo->exec('DELETE FROM site_role_assignments');
        $pdo->exec("DELETE rp FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id
            WHERE p.slug='*' OR p.slug='admin.system' OR p.slug='site.support' OR p.slug LIKE 'site.%' OR p.slug LIKE 'platform.%' OR p.slug LIKE 'system.%'");
        $pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.tenant_id IS NOT NULL");

        // Replace only access-role assignments; military/job roles remain attached for display.
        $accessSlugs = FunctionBasedAccessCatalog::accessRoleSlugs();
        $quotedSlugs = implode(',', array_map(static fn (string $slug): string => $pdo->quote($slug), $accessSlugs));
        $pdo->exec("DELETE ur FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE r.slug IN ({$quotedSlugs})");
        $pdo->exec("INSERT IGNORE INTO user_roles(user_id,role_id)
            SELECT a.user_id,r.id FROM access_control_v2_user_access_roles a
            JOIN roles r ON r.tenant_id=a.tenant_id AND r.slug=a.access_role_slug");
        $pdo->exec("UPDATE users u JOIN access_control_v2_user_access_roles a ON a.tenant_id=u.tenant_id AND a.user_id=u.id
            JOIN roles r ON r.tenant_id=a.tenant_id AND r.slug=a.access_role_slug SET u.role_id=r.id");
        $pdo->exec("DELETE tur FROM tenant_user_roles tur JOIN roles r ON r.id=tur.role_id WHERE r.slug IN ({$quotedSlugs})");
        $pdo->exec("INSERT IGNORE INTO tenant_user_roles(tenant_id,user_id,role_id,org_unit_id,co_unit_id,created_at)
            SELECT a.tenant_id,a.user_id,r.id,NULL,0,NOW() FROM access_control_v2_user_access_roles a
            JOIN roles r ON r.tenant_id=a.tenant_id AND r.slug=a.access_role_slug");
        $visualColumn = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='roles' AND COLUMN_NAME='is_visual_only' LIMIT 1")->fetchColumn();
        if ($visualColumn) {
            $pdo->exec("UPDATE roles SET is_visual_only=CASE WHEN slug IN ({$quotedSlugs}) THEN 0 ELSE 1 END WHERE tenant_id IS NOT NULL");
        }

        $link = $pdo->prepare("INSERT IGNORE INTO role_permissions(role_id,permission_id)
            SELECT r.id,p.id FROM roles r JOIN permissions p ON p.tenant_id=r.tenant_id WHERE r.tenant_id IS NOT NULL AND r.slug=? AND p.slug=?");
        foreach (FunctionBasedAccessCatalog::roles() as $role) {
            foreach ($role['permission_slugs'] as $permission) {
                $link->execute([$role['role_slug'], $permission]);
            }
        }
        $pdo->commit();
        echo "  [OK] FBAC v2 : snapshot, sept rôles classiques et mapping utilisateurs appliqués\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
