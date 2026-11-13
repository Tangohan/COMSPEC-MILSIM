<?php

declare(strict_types=1);

/**
 * Étape « Droits des fichiers SSE » :
 *  - atak.sse.files.admin : gérer les fichiers SSE depuis le back-office (fiches, fusion de doublons, alias, photos,
 *    mentions, journal complet, modération des extractions de téléphones, réglages) ;
 *  - atak.sse.lore.manage : préparer le lore des sessions (MJ) : personnes, ce qui est connu ou caché, phases,
 *    verrouillage, synchronisation avec le jeu.
 *
 * Créés dans chaque communauté ; accordés au propriétaire, à l'administrateur de la communauté et aux rôles qui ont déjà
 * la gestion des dossiers SSE (atak.sse.case.manage) ou l'attribution des accès SSE (atak.sse.grant). Les autres rôles
 * se cochent dans Accès. Idempotente.
 */

try {
    $ssePerms = [
        'atak.sse.files.admin' => 'Gérer les fichiers SSE (fiches, fusion, photos, journal, extractions, réglages)',
        'atak.sse.lore.manage' => 'Préparer le lore des sessions (personnes, connu ou caché, phases, synchronisation avec le jeu)',
    ];
    $tenants = $pdo->query('SELECT id FROM tenants');
    if ($tenants) {
        $findPerm = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $addPerm = $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, ?)');
        $findRoles = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::inLiterals($pdo, 'slug', ['community_owner', 'tenant_admin']));
        $findSseRoles = $pdo->prepare(
            'SELECT DISTINCT rp.role_id FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE p.tenant_id = ? AND ' . \App\Support\SqlText::inLiterals($pdo, 'p.slug', ['atak.sse.case.manage', 'atak.sse.grant'])
        );
        $findLink = $pdo->prepare('SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1');
        $addLink = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach ($tenants->fetchAll(PDO::FETCH_COLUMN) ?: [] as $tenantId) {
            $tenantId = (int) $tenantId;
            if ($tenantId < 1) {
                continue;
            }
            $findRoles->execute([$tenantId]);
            $roleIds = array_map('intval', $findRoles->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $findSseRoles->execute([$tenantId]);
            $roleIds = array_values(array_unique(array_merge($roleIds, array_map('intval', $findSseRoles->fetchAll(PDO::FETCH_COLUMN) ?: []))));
            foreach ($ssePerms as $permSlug => $permName) {
                $findPerm->execute([$tenantId, $permSlug]);
                $permId = (int) $findPerm->fetchColumn();
                if ($permId < 1) {
                    $addPerm->execute([$tenantId, $permName, $permSlug, 'atak', date('Y-m-d H:i:s')]);
                    $permId = (int) $pdo->lastInsertId();
                }
                if ($permId < 1) {
                    continue;
                }
                foreach ($roleIds as $roleId) {
                    $findLink->execute([$roleId, $permId]);
                    if ($findLink->fetchColumn() === false) {
                        $addLink->execute([$roleId, $permId]);
                    }
                }
            }
        }
    }
    echo "Droits des fichiers SSE (gestion, lore des sessions) : OK.\n";
} catch (Throwable $e) {
    echo '  [ATTENTION] Droits des fichiers SSE : ' . $e->getMessage() . "\n";
}
