<?php

declare(strict_types=1);

/**
 * Forum v2 : exécute migrations/forum_v2.sql (source de vérité pour le DDL),
 * puis permissions et backfill catégories organisation (logique PHP uniquement).
 */
return function (PDO $pdo): void {
    $sqlPath = dirname(__DIR__) . '/migrations/forum_v2.sql';
    if (!is_file($sqlPath)) {
        echo "[ATTENTION] forum_v2.sql introuvable — migration Forum v2 ignorée.\n";

        return;
    }

    echo "Forum v2 : exécution de migrations/forum_v2.sql...\n";
    @flush();
    @ob_flush();

    $sql = file_get_contents($sqlPath);
    if ($sql === false || $sql === '') {
        return;
    }
    $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
    $sql = preg_replace('/SET NAMES utf8mb4;/i', '', $sql);
    $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
    foreach ($chunks as $stmtSql) {
        $stmtSql = trim($stmtSql);
        if ($stmtSql === '') {
            continue;
        }
        $full = $stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';');
        try {
            $pdo->exec($full);
        } catch (PDOException $e) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            $msg = $e->getMessage();
            // DDL déjà appliqué (relance, import partiel)
            $ignorable = in_array($driverCode, [1005, 1007, 1022, 1050, 1060, 1061, 1091, 1826], true)
                || preg_match('/Duplicate (column|key|foreign key|entry)/i', $msg)
                || (str_contains($msg, 'already exists') && !str_contains($msg, 'Failed'));
            if (!$ignorable) {
                echo '  [ATTENTION] Forum v2 SQL : ' . $msg . "\n";
            }
        }
    }

    echo "Forum v2 : permissions organisation + backfill sections...\n";
    $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($table) . " AND COLUMN_NAME = " . $pdo->quote($column) . " LIMIT 1");

        return (bool) $stmt?->fetchColumn();
    };

    $stmtTenants = $pdo->query('SELECT id FROM tenants');
    $tenantIds = $stmtTenants ? $stmtTenants->fetchAll(PDO::FETCH_COLUMN) : [];
    foreach ($tenantIds as $tidRaw) {
        $tid = (int) $tidRaw;
        $chkPerm = $pdo->prepare("SELECT id FROM permissions WHERE tenant_id = ? AND slug = 'forum.moderate_organization' LIMIT 1");
        $chkPerm->execute([$tid]);
        if ($chkPerm->fetch()) {
            continue;
        }
        $pdo->prepare("INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, 'Modérer la section forum de l\'organisation', 'forum.moderate_organization', 'forum', NOW())")->execute([$tid]);
        $newPid = (int) $pdo->lastInsertId();
        foreach (['tenant_admin', 'forum_moderator'] as $roleSlug) {
            $r = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
            $r->execute([$tid, $roleSlug]);
            $rid = $r->fetchColumn();
            if ($rid) {
                $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([(int) $rid, $newPid]);
            }
        }
    }

    if ($hasColumn($pdo, 'forum_categories', 'scope')) {
        echo "Forum v2 : sections organisation (backfill par tenant)...\n";
        $tenants = $pdo->query('SELECT id, name, slug FROM tenants')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tenants as $t) {
            $tid = (int) $t['id'];
            $slug = 'org-' . preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) ($t['slug'] ?? '')));
            $slug = trim($slug, '-');
            if ($slug === 'org') {
                $slug = 'org-' . $tid;
            }
            if (strlen($slug) > 100) {
                $slug = substr('org-' . $tid . '-' . md5((string) ($t['slug'] ?? '')), 0, 100);
                $slug = rtrim($slug, '-');
            }
            // Déjà présente (scope organization ou section org migrée en scope « tenant » / stratifié)
            $chk = $pdo->prepare("SELECT 1 FROM forum_categories WHERE tenant_id = ? AND slug = ? LIMIT 1");
            $chk->execute([$tid, $slug]);
            if ($chk->fetch()) {
                continue;
            }
            $chkLegacy = $pdo->prepare("SELECT 1 FROM forum_categories WHERE tenant_id = ? AND scope = 'organization' LIMIT 1");
            $chkLegacy->execute([$tid]);
            if ($chkLegacy->fetch()) {
                continue;
            }
            $name = trim((string) $t['name']) . ' — Espace dédié';
            if (strlen($name) > 255) {
                $name = substr($name, 0, 252) . '…';
            }
            $ins = $pdo->prepare('INSERT INTO forum_categories (tenant_id, scope, owner_tenant_id, parent_id, name, slug, description, color_theme, display_order, is_locked, created_at, updated_at) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, 0, NOW(), NOW())');
            try {
                $ins->execute([$tid, 'organization', $tid, $name, $slug, 'Section forum de votre organisation.', 'slate', 15]);
            } catch (PDOException $e) {
                echo '  [ATTENTION] Forum v2 section org (tenant ' . $tid . ') : ' . $e->getMessage() . "\n";
            }
        }
    }
};
