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

    $columnExists = static function (string $column) use ($pdo): bool {
        try {
            $st = $pdo->query("SHOW COLUMNS FROM permissions LIKE " . $pdo->quote($column));

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
        echo "atak_sse_clearance_permissions : aucun tenant.\n";

        return;
    }

    $selectPerm = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
    $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');

    $created = 0;
    $linked = 0;

    while ($trow = $tenants->fetch(PDO::FETCH_ASSOC)) {
        $tid = (int) ($trow['id'] ?? 0);
        if ($tid < 1) {
            continue;
        }

        $permIds = [];
        foreach ($defs as $def) {
            $selectPerm->execute([$tid, $def['slug']]);
            $existing = $selectPerm->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $permIds[$def['slug']] = (int) $existing['id'];
                continue;
            }

            $cols = ['tenant_id', 'name', 'slug', 'module'];
            $vals = [$tid, $def['name'], $def['slug'], 'atak'];
            $placeholders = ['?', '?', '?', '?'];

            if ($hasCode) {
                $cols[] = 'code';
                $vals[] = $def['slug'];
                $placeholders[] = '?';
            }
            if ($hasLabel) {
                $cols[] = 'label';
                $vals[] = $def['name'];
                $placeholders[] = '?';
            }
            if ($hasAction) {
                $cols[] = 'action';
                $vals[] = 'view';
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
