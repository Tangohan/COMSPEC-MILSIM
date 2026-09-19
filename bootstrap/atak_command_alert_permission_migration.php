<?php

declare(strict_types=1);

/**
 * Droit d’alerte plein écran Overwatch : crée atak.command.alert
 * et l’accorde aux rôles qui pilotent déjà le cycle de mission.
 */
return static function (PDO $pdo): void {
    $columnExists = static function (string $column) use ($pdo): bool {
        try {
            $st = $pdo->query('SHOW COLUMNS FROM permissions LIKE ' . $pdo->quote($column));

            return $st !== false && (bool) $st->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return false;
        }
    };

    $hasAction = $columnExists('action');
    $hasCode = $columnExists('code');
    $hasLabel = $columnExists('label');
    $hasScope = $columnExists('scope');

    $tenants = $pdo->query('SELECT id FROM tenants WHERE id > 0');
    if (!$tenants) {
        echo "atak_command_alert_permission : aucun tenant.\n";

        return;
    }

    $selectPerm = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
    $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
    $created = 0;
    $linked = 0;
    $slug = 'atak.command.alert';
    $name = 'Envoyer une alerte plein écran à tous les opérateurs';
    $fromSlugs = ['atak.mission_cycle.manage', 'admin.access', 'admin.organization'];

    while ($trow = $tenants->fetch(PDO::FETCH_ASSOC)) {
        $tid = (int) ($trow['id'] ?? 0);
        if ($tid < 1) {
            continue;
        }

        $selectPerm->execute([$tid, $slug]);
        $existing = $selectPerm->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $permId = (int) $existing['id'];
        } else {
            $cols = ['tenant_id', 'name', 'slug', 'module'];
            $vals = [$tid, $name, $slug, 'atak'];
            $placeholders = ['?', '?', '?', '?'];
            if ($hasCode) {
                $cols[] = 'code';
                $vals[] = $slug;
                $placeholders[] = '?';
            }
            if ($hasLabel) {
                $cols[] = 'label';
                $vals[] = $name;
                $placeholders[] = '?';
            }
            if ($hasAction) {
                $cols[] = 'action';
                $vals[] = 'manage';
                $placeholders[] = '?';
            }
            if ($hasScope) {
                $cols[] = 'scope';
                $vals[] = 'community';
                $placeholders[] = '?';
            }
            $cols[] = 'created_at';
            $placeholders[] = 'NOW()';
            $sql = 'INSERT INTO permissions (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $pdo->prepare($sql)->execute($vals);
            $permId = (int) $pdo->lastInsertId();
            $created++;
        }

        if ($permId < 1) {
            continue;
        }
        $placeholders = implode(',', array_fill(0, count($fromSlugs), '?'));
        $sql = "SELECT DISTINCT rp.role_id
                FROM role_permissions rp
                INNER JOIN permissions p ON p.id = rp.permission_id
                WHERE p.tenant_id = ? AND p.slug IN ($placeholders)";
        $st = $pdo->prepare($sql);
        $st->execute(array_merge([$tid], $fromSlugs));
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $link->execute([(int) $r['role_id'], $permId]);
            if ($link->rowCount() > 0) {
                $linked++;
            }
        }
    }

    echo "atak_command_alert_permission : {$created} permission(s) créée(s), {$linked} liaison(s) ajoutée(s).\n";
};
