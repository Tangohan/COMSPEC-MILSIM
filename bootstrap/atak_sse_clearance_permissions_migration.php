<?php

declare(strict_types=1);

/**
 * Permissions d’habilitation de lecture SSE (niveaux Encadrement / Confidentiel / Très restreint).
 * Idempotent : crée les lignes manquantes puis accorde selon les droits déjà présents.
 */
return static function (PDO $pdo): void {
    $defs = [
        [
            'slug' => 'atak.sse.clearance.encadrement',
            'name' => 'Lire le renseignement jusqu’au niveau Encadrement',
            'from' => ['atak.sse.access'],
        ],
        [
            'slug' => 'atak.sse.clearance.confidentiel',
            'name' => 'Lire le renseignement jusqu’au niveau Confidentiel',
            'from' => ['atak.sse.case.manage'],
        ],
        [
            'slug' => 'atak.sse.clearance.tres_restreint',
            'name' => 'Lire le renseignement jusqu’au niveau Diffusion très restreinte',
            'from' => ['atak.sse.grant', 'admin.access'],
        ],
    ];

    $tenants = $pdo->query('SELECT id FROM tenants WHERE id > 0')->fetchAll(PDO::FETCH_COLUMN);
    if (!is_array($tenants) || $tenants === []) {
        echo "atak_sse_clearance_permissions : aucun tenant.\n";

        return;
    }

    $hasAction = false;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM permissions LIKE 'action'");
        $hasAction = $col && $col->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable) {
    }

    $selectPerm = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
    $insertPerm = $hasAction
        ? $pdo->prepare(
            'INSERT INTO permissions (tenant_id, name, slug, module, action, scope, created_at)
             VALUES (?, ?, ?, \'atak\', \'view\', \'community\', NOW())'
        )
        : $pdo->prepare(
            'INSERT INTO permissions (tenant_id, name, slug, module, scope, created_at)
             VALUES (?, ?, ?, \'atak\', \'community\', NOW())'
        );
    $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');

    $created = 0;
    $linked = 0;

    foreach ($tenants as $tidRaw) {
        $tid = (int) $tidRaw;
        if ($tid < 1) {
            continue;
        }

        $permIds = [];
        foreach ($defs as $def) {
            $selectPerm->execute([$tid, $def['slug']]);
            $row = $selectPerm->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $permIds[$def['slug']] = (int) $row['id'];
                continue;
            }
            if ($hasAction) {
                $insertPerm->execute([$tid, $def['name'], $def['slug']]);
            } else {
                $insertPerm->execute([$tid, $def['name'], $def['slug']]);
            }
            $permIds[$def['slug']] = (int) $pdo->lastInsertId();
            $created++;
        }

        foreach ($defs as $def) {
            $targetId = $permIds[$def['slug']] ?? 0;
            if ($targetId < 1) {
                continue;
            }
            $placeholders = implode(',', array_fill(0, count($def['from']), '?'));
            $sql = "SELECT DISTINCT rp.role_id
                    FROM role_permissions rp
                    INNER JOIN permissions p ON p.id = rp.permission_id
                    WHERE p.tenant_id = ? AND p.slug IN ($placeholders)";
            $st = $pdo->prepare($sql);
            $st->execute(array_merge([$tid], $def['from']));
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $link->execute([(int) $r['role_id'], $targetId]);
                if ($link->rowCount() > 0) {
                    $linked++;
                }
            }
        }
    }

    echo "atak_sse_clearance_permissions : {$created} permission(s) créée(s), {$linked} liaison(s) ajoutée(s).\n";
};
