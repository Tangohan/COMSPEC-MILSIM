<?php

declare(strict_types=1);

/**
 * RBAC 3 couches : role_layer / scope, rôles site globaux (tenant_id NULL), site_role_assignments.
 * Idempotent — appelée depuis run-migrations.php.
 */
function run_rbac_three_layer_migration(PDO $pdo): void
{
    $check = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'role_layer' LIMIT 1");
    if ($check && !$check->fetch()) {
        echo "RBAC: ajout roles.role_layer, permissions.scope...\n";
        $pdo->exec("ALTER TABLE roles ADD COLUMN role_layer ENUM('site','community','intra') NOT NULL DEFAULT 'community' AFTER is_locked");
        $pdo->exec("ALTER TABLE permissions ADD COLUMN scope ENUM('site','community','intra') NOT NULL DEFAULT 'community' AFTER module");
        try {
            $pdo->exec('ALTER TABLE roles ADD KEY roles_tenant_layer (tenant_id, role_layer)');
        } catch (PDOException) {
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS site_role_assignments (
        id int unsigned NOT NULL AUTO_INCREMENT,
        email_normalized varchar(255) NOT NULL,
        role_id int unsigned NOT NULL,
        assigned_by_user_id int unsigned DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        revoked_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        KEY email_normalized (email_normalized),
        KEY role_id (role_id),
        CONSTRAINT site_role_assignments_role_fk FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $uniq = $pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_role_assignments' AND INDEX_NAME = 'uk_site_role_email_role'");
    if ($uniq && !$uniq->fetch()) {
        try {
            $pdo->exec('CREATE UNIQUE INDEX uk_site_role_email_role ON site_role_assignments (email_normalized, role_id)');
        } catch (PDOException) {
        }
    }

    // --- Permissions globales (site) ---
    $sitePerms = [
        ['admin.system', 'Administration système (plateforme)', 'admin', 'site'],
        ['admin.access', 'Accès back-office plateforme', 'admin', 'site'],
        ['site.tenants.manage', 'Gérer les communautés (tenants)', 'admin', 'site'],
    ];
    $insPerm = $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, scope, created_at) VALUES (NULL, ?, ?, ?, ?, NOW())');
    foreach ($sitePerms as $p) {
        $st = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id IS NULL AND slug = ? LIMIT 1');
        $st->execute([$p[0]]);
        if (!$st->fetch()) {
            $insPerm->execute([$p[1], $p[0], $p[2], $p[3]]);
            echo "RBAC: permission globale {$p[0]} créée.\n";
        }
    }

    // --- Rôle site global site_super_admin ---
    $st = $pdo->prepare("SELECT id FROM roles WHERE tenant_id IS NULL AND slug = 'site_super_admin' LIMIT 1");
    $st->execute();
    $globalSiteRoleRow = $st->fetch(PDO::FETCH_ASSOC);
    $globalSiteRoleId = $globalSiteRoleRow ? (int) $globalSiteRoleRow['id'] : 0;
    if ($globalSiteRoleId <= 0) {
        $pdo->prepare("INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at)
            VALUES (NULL, 'Super administrateur site', 'site_super_admin', 'Administration plateforme (global)', 1, 1, 'site', NOW())")
            ->execute();
        $globalSiteRoleId = (int) $pdo->lastInsertId();
        echo "RBAC: rôle global site_super_admin créé (id={$globalSiteRoleId}).\n";
    } else {
        $pdo->prepare("UPDATE roles SET role_layer = 'site' WHERE id = ?")->execute([$globalSiteRoleId]);
    }

    foreach (['admin.system', 'admin.access', 'site.tenants.manage'] as $slug) {
        $p = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id IS NULL AND slug = ? LIMIT 1');
        $p->execute([$slug]);
        $pid = $p->fetch(PDO::FETCH_ASSOC);
        if ($pid) {
            $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)')
                ->execute([$globalSiteRoleId, (int) $pid['id']]);
        }
    }
    try {
        $pdo->exec("UPDATE permissions SET scope = 'site' WHERE tenant_id IS NULL");
    } catch (PDOException) {
    }

    // Backfill scope sur permissions tenant existantes
    $pdo->exec("UPDATE permissions SET scope = 'community' WHERE tenant_id IS NOT NULL AND slug IN ('admin.organization','admin.access')");
    $pdo->exec("UPDATE permissions SET scope = 'intra' WHERE tenant_id IS NOT NULL AND slug IN ('forum.view','forum.reply','forum.create_topic','forum.edit_own','forum.delete_own')");
    $pdo->exec("UPDATE permissions SET scope = 'community' WHERE tenant_id IS NOT NULL AND slug LIKE 'forum.%' AND scope = 'community'");

    // Couches par slug sur rôles tenant existants
    $pdo->exec("UPDATE roles SET role_layer = 'intra' WHERE tenant_id IS NOT NULL AND slug IN ('member','officer','forum_moderator')");
    $pdo->exec("UPDATE roles SET role_layer = 'community' WHERE tenant_id IS NOT NULL AND slug IN ('tenant_admin','community_owner')");
    $pdo->exec("UPDATE roles SET role_layer = 'community' WHERE tenant_id IS NOT NULL AND slug = 'super_admin'");

    // --- Migration super_admin (tenant) -> community_owner + site_role_assignments ---
    $tenants = $pdo->query('SELECT id FROM tenants')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tenants as $tid) {
        $tenantId = (int) $tid;
        $sa = $pdo->prepare("SELECT id FROM roles WHERE tenant_id = ? AND slug = 'super_admin' LIMIT 1");
        $sa->execute([$tenantId]);
        $saRow = $sa->fetch(PDO::FETCH_ASSOC);
        if (!$saRow) {
            continue;
        }
        $superAdminId = (int) $saRow['id'];

        $co = $pdo->prepare("SELECT id FROM roles WHERE tenant_id = ? AND slug = 'community_owner' LIMIT 1");
        $co->execute([$tenantId]);
        $coRow = $co->fetch(PDO::FETCH_ASSOC);
        if (!$coRow) {
            $pdo->prepare("INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at)
                VALUES (?, 'Propriétaire communauté', 'community_owner', 'Gouvernance complète de la communauté (sans administration plateforme)', 1, 1, 'community', NOW())")
                ->execute([$tenantId]);
            $coId = (int) $pdo->lastInsertId();
            echo "RBAC: tenant {$tenantId} — rôle community_owner créé.\n";
        } else {
            $coId = (int) $coRow['id'];
        }

        $permSys = $pdo->prepare("SELECT id FROM permissions WHERE tenant_id = ? AND slug = 'admin.system' LIMIT 1");
        $permSys->execute([$tenantId]);
        $sysPermId = $permSys->fetch(PDO::FETCH_ASSOC);
        $excludePid = $sysPermId ? (int) $sysPermId['id'] : 0;

        $rp = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id = ?');
        $rp->execute([$superAdminId]);
        $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        while ($row = $rp->fetch(PDO::FETCH_ASSOC)) {
            $pid = (int) $row['permission_id'];
            if ($excludePid > 0 && $pid === $excludePid) {
                continue;
            }
            $link->execute([$coId, $pid]);
        }

        $emails = $pdo->prepare('SELECT DISTINCT LOWER(TRIM(email)) AS em FROM users WHERE tenant_id = ? AND role_id = ?');
        $emails->execute([$tenantId, $superAdminId]);
        while ($e = $emails->fetch(PDO::FETCH_ASSOC)) {
            $em = (string) ($e['em'] ?? '');
            if ($em === '') {
                continue;
            }
            $pdo->prepare('INSERT IGNORE INTO site_role_assignments (email_normalized, role_id, created_at) VALUES (?, ?, NOW())')
                ->execute([$em, $globalSiteRoleId]);
        }

        $pdo->prepare('UPDATE users SET role_id = ? WHERE tenant_id = ? AND role_id = ?')->execute([$coId, $tenantId, $superAdminId]);
        $pdo->prepare('DELETE FROM roles WHERE id = ?')->execute([$superAdminId]);
        echo "RBAC: tenant {$tenantId} — super_admin migré vers community_owner + site.\n";
    }

    // Retirer admin.system des rôles tenant (tenant_admin) si encore lié par erreur
    $bad = $pdo->query("SELECT rp.role_id, rp.permission_id FROM role_permissions rp
        INNER JOIN roles r ON r.id = rp.role_id
        INNER JOIN permissions p ON p.id = rp.permission_id
        WHERE r.tenant_id IS NOT NULL AND p.tenant_id = r.tenant_id AND p.slug = 'admin.system'");
    if ($bad) {
        $del = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?');
        while ($row = $bad->fetch(PDO::FETCH_ASSOC)) {
            $del->execute([(int) $row['role_id'], (int) $row['permission_id']]);
        }
    }

    // Tenants sans community_owner : créer à partir de tenant_admin (gouvernance communauté)
    foreach ($tenants as $tid) {
        $tenantId = (int) $tid;
        $co = $pdo->prepare("SELECT id FROM roles WHERE tenant_id = ? AND slug = 'community_owner' LIMIT 1");
        $co->execute([$tenantId]);
        if ($co->fetch()) {
            continue;
        }
        $ta = $pdo->prepare("SELECT id FROM roles WHERE tenant_id = ? AND slug = 'tenant_admin' LIMIT 1");
        $ta->execute([$tenantId]);
        $taRow = $ta->fetch(PDO::FETCH_ASSOC);
        if (!$taRow) {
            continue;
        }
        $taId = (int) $taRow['id'];
        $pdo->prepare("INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at)
            VALUES (?, 'Propriétaire communauté', 'community_owner', 'Gouvernance complète de la communauté', 1, 1, 'community', NOW())")
            ->execute([$tenantId]);
        $coId = (int) $pdo->lastInsertId();
        $rp = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id = ?');
        $rp->execute([$taId]);
        $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        while ($row = $rp->fetch(PDO::FETCH_ASSOC)) {
            $pid = (int) $row['permission_id'];
            $chk = $pdo->prepare('SELECT slug FROM permissions WHERE id = ? LIMIT 1');
            $chk->execute([$pid]);
            $slugRow = $chk->fetch(PDO::FETCH_ASSOC);
            if ($slugRow && ($slugRow['slug'] ?? '') === 'admin.system') {
                continue;
            }
            $link->execute([$coId, $pid]);
        }
        echo "RBAC: tenant {$tenantId} — community_owner ajouté (copie tenant_admin).\n";
    }

    echo "RBAC three-layer migration OK.\n";
}
