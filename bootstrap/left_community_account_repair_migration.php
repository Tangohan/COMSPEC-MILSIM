<?php

declare(strict_types=1);

/**
 * Réparation : comptes bloqués après un départ volontaire de communauté.
 *
 * Avant le correctif, « Quitter la communauté » écrivait users.status = 'inactive' sur le compte
 * global (un seul compte pour toutes les communautés) : la personne était déconnectée et ne
 * pouvait plus se reconnecter. On réactive le compte quand sa dernière action de statut est un
 * départ volontaire (audit `user_left_community` fait par la personne elle-même), et on termine
 * proprement l’appartenance à la communauté quittée.
 *
 * Idempotent — appelée depuis run-migrations.php.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };
    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('users') || !$tableExists('audit_logs') || !$tableExists('user_community_memberships')) {
        echo "  [OK] Rien à réparer (modèle multi-communautés absent)\n";

        return;
    }

    $where = ["u.status = 'inactive'", "LOWER(u.email) NOT LIKE '%@deleted.invalid'", "LOWER(u.email) NOT LIKE '%@internal.local'"];
    if ($columnExists('users', 'is_service_account')) {
        $where[] = 'COALESCE(u.is_service_account, 0) = 0';
    }
    if ($columnExists('users', 'deleted_at')) {
        $where[] = 'u.deleted_at IS NULL';
    }

    // Dernière action de statut sur le compte = départ volontaire fait par la personne elle-même.
    $sql = "SELECT u.id, a.tenant_id
            FROM users u
            INNER JOIN audit_logs a ON a.id = (
                SELECT a2.id FROM audit_logs a2
                WHERE a2.entity_type = 'user' AND a2.entity_id = u.id
                  AND a2.action IN ('user_left_community', 'user_deactivated')
                ORDER BY a2.created_at DESC, a2.id DESC
                LIMIT 1
            )
            WHERE " . implode(' AND ', $where) . "
              AND a.action = 'user_left_community'
              AND a.user_id = u.id";
    $rows = $pdo->query($sql)?->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($rows === []) {
        echo "  [OK] Aucun compte bloqué par un départ de communauté\n";

        return;
    }

    $hasProfiles = $tableExists('user_community_profiles');
    $reactivate = $pdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ? AND status = 'inactive'");
    $leave = $pdo->prepare("UPDATE user_community_memberships SET status = 'left', left_at = COALESCE(left_at, NOW()), updated_at = NOW()
                            WHERE user_id = ? AND tenant_id = ? AND status = 'active'");
    $profile = $hasProfiles
        ? $pdo->prepare("UPDATE user_community_profiles SET status = 'inactive', updated_at = NOW() WHERE user_id = ? AND tenant_id = ?")
        : null;
    // Communauté courante restée sur celle quittée : on la remplace par une appartenance encore active.
    $nextTenant = $pdo->prepare("SELECT tenant_id FROM user_community_memberships WHERE user_id = ? AND status = 'active' AND tenant_id <> ? ORDER BY joined_at ASC, id ASC LIMIT 1");
    $moveCurrent = $pdo->prepare('UPDATE users SET tenant_id = ? WHERE id = ? AND tenant_id = ?');
    // Sans autre communauté : sas « Pas d’organisation » (slug default), comme après un départ normal.
    $defaultTenantId = (int) ($pdo->query("SELECT id FROM tenants WHERE LOWER(slug) = 'default' ORDER BY id ASC LIMIT 1")?->fetchColumn() ?: 0);
    $joinDefault = $pdo->prepare("INSERT INTO user_community_memberships (user_id, tenant_id, status, joined_at)
                                  VALUES (?, ?, 'active', NOW())
                                  ON DUPLICATE KEY UPDATE status = 'active', left_at = NULL, updated_at = NOW()");

    $fixed = 0;
    foreach ($rows as $row) {
        $userId = (int) $row['id'];
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        if ($tenantId > 0) {
            $leave->execute([$userId, $tenantId]);
            $profile?->execute([$userId, $tenantId]);
            $nextTenant->execute([$userId, $tenantId]);
            $next = (int) ($nextTenant->fetchColumn() ?: 0);
            if ($next < 1 && $defaultTenantId > 0 && $defaultTenantId !== $tenantId) {
                $joinDefault->execute([$userId, $defaultTenantId]);
                $next = $defaultTenantId;
            }
            if ($next > 0) {
                $moveCurrent->execute([$next, $userId, $tenantId]);
            }
        }
        $reactivate->execute([$userId]);
        $fixed += $reactivate->rowCount();
    }
    echo "  [OK] Comptes réactivés après un départ de communauté : {$fixed}\n";
};
