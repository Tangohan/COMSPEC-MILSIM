<?php

declare(strict_types=1);

/**
 * Étape « Droits du poste TOC web » :
 *  - atak.toc.use : tenir le poste TOC web (carte commune, chronos, main courante, EXCHECK, pertes, demandes) et
 *    préparer des plans d'opération, les envoyer en jeu ;
 *  - atak.toc.manage : configurer le poste (modèles de plans de la communauté, remise à zéro de la salle, plans de
 *    tous les membres).
 *
 * Créés dans chaque communauté ; accordés au propriétaire, à l'administrateur de la communauté, au commandant adjoint et
 * aux gestionnaires d'unité, et « tenir le poste » aux rôles qui pilotent déjà le cycle de mission ATAK
 * (atak.mission_cycle.manage). Les autres rôles se cochent dans Accès. Idempotente.
 */

try {
    $tocPerms = [
        'atak.toc.use' => 'Tenir le poste TOC web et préparer des plans d’opération',
        'atak.toc.manage' => 'Configurer le poste TOC web (envoi des plans en jeu, réglages, modèles de la communauté)',
    ];
    $tenants = $pdo->query('SELECT id FROM tenants');
    if ($tenants) {
        $findPerm = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $addPerm = $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, ?)');
        $findRoles = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::inLiterals($pdo, 'slug', ['community_owner', 'tenant_admin', 'deputy_commander', 'unit_manager']));
        $findCycleRoles = $pdo->prepare(
            'SELECT DISTINCT rp.role_id FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE p.tenant_id = ? AND ' . \App\Support\SqlText::inLiterals($pdo, 'p.slug', ['atak.mission_cycle.manage'])
        );
        $findLink = $pdo->prepare('SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1');
        $addLink = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach ($tenants->fetchAll(PDO::FETCH_COLUMN) ?: [] as $tenantId) {
            $tenantId = (int) $tenantId;
            if ($tenantId < 1) {
                continue;
            }
            $findRoles->execute([$tenantId]);
            $commandRoles = array_map('intval', $findRoles->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $findCycleRoles->execute([$tenantId]);
            $cycleRoles = array_map('intval', $findCycleRoles->fetchAll(PDO::FETCH_COLUMN) ?: []);
            foreach ($tocPerms as $permSlug => $permName) {
                $findPerm->execute([$tenantId, $permSlug]);
                $permId = (int) $findPerm->fetchColumn();
                if ($permId < 1) {
                    $addPerm->execute([$tenantId, $permName, $permSlug, 'atak', date('Y-m-d H:i:s')]);
                    $permId = (int) $pdo->lastInsertId();
                }
                if ($permId < 1) {
                    continue;
                }
                $roleIds = $permSlug === 'atak.toc.use'
                    ? array_values(array_unique(array_merge($commandRoles, $cycleRoles)))
                    : $commandRoles;
                foreach ($roleIds as $roleId) {
                    $findLink->execute([$roleId, $permId]);
                    if ($findLink->fetchColumn() === false) {
                        $addLink->execute([$roleId, $permId]);
                    }
                }
            }
        }
    }
    echo "Droits du poste TOC web (tenir le poste, configurer) : OK.\n";
} catch (Throwable $e) {
    echo '  [ATTENTION] Droits du poste TOC web : ' . $e->getMessage() . "\n";
}
