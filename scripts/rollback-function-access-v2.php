<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/migration_pdo.php';
require dirname(__DIR__) . '/app/Support/SqlText.php';
$name = getenv('DB_NAME') ?: ''; $user = getenv('DB_USER') ?: ''; $pass = getenv('DB_PASSWORD') ?: '';
if ($name === '' || $user === '') { fwrite(STDERR, "DB_NAME and DB_USER are required\n"); exit(1); }
$pdo = new PDO(migration_mysql_dsn(), $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();
try {
    $pdo->exec('DELETE FROM role_permissions');
    $rows = $pdo->query('SELECT role_id,permission_slugs_json FROM access_control_v2_role_snapshot ORDER BY snapshot_id')->fetchAll(PDO::FETCH_ASSOC);
    $permission = $pdo->prepare('SELECT id FROM permissions WHERE slug=? AND (tenant_id=(SELECT tenant_id FROM roles WHERE id=?) OR tenant_id IS NULL) ORDER BY tenant_id IS NULL LIMIT 1');
    $insert = $pdo->prepare('INSERT IGNORE INTO role_permissions(role_id,permission_id) VALUES (?,?)');
    foreach ($rows as $row) {
        foreach ((json_decode((string) $row['permission_slugs_json'], true) ?: []) as $slug) {
            $permission->execute([$slug, $row['role_id']]); $id = $permission->fetchColumn();
            if ($id) { $insert->execute([$row['role_id'], $id]); }
        }
    }
    $pdo->exec('DELETE FROM user_roles');
    $pdo->exec("INSERT IGNORE INTO user_roles(user_id,role_id) SELECT user_id,role_id FROM access_control_v2_user_role_snapshot WHERE source_kind='user_roles'");
    $pdo->exec('UPDATE users u JOIN access_control_v2_user_primary_role_snapshot s ON s.user_id=u.id SET u.role_id=s.role_id');
    $pdo->exec('DELETE FROM tenant_user_roles');
    $pdo->exec('INSERT IGNORE INTO tenant_user_roles(tenant_id,user_id,role_id,org_unit_id,co_unit_id,created_at) SELECT tenant_id,user_id,role_id,org_unit_id,co_unit_id,created_at FROM access_control_v2_tenant_user_role_snapshot');
    $pdo->exec('DELETE FROM site_role_assignments');
    $pdo->exec('INSERT INTO site_role_assignments (id,email_normalized,role_id,assigned_by_user_id,created_at,revoked_at) SELECT assignment_id,email_normalized,role_id,assigned_by_user_id,created_at,revoked_at FROM access_control_v2_site_assignment_snapshot');
    $pdo->commit(); echo "Role grants and legacy site assignments restored from the immutable FBAC v2 snapshot.\n";
} catch (Throwable $e) { $pdo->rollBack(); throw $e; }
