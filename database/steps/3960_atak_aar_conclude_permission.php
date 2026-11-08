<?php

declare(strict_types=1);

/**
 * Étape « Droit de conclusion des comptes rendus (atak.aar.conclude) ».
 *
 * Crée le droit dans chaque communauté et l'accorde aux niveaux Gestionnaire et adjoint.
 * Les rôles personnalisés ne le reçoivent pas : il se coche dans Accès.
 */

try {
    $permSlug = 'atak.aar.conclude';
    $permName = 'Écrire la conclusion du commandement des comptes rendus';
    $tenants = $pdo->query('SELECT id FROM tenants');
    if ($tenants) {
        $findPerm = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $addPerm = $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, ?)');
        $findRoles = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::inLiterals($pdo, 'slug', ['community_owner', 'tenant_admin', 'deputy_commander', 'unit_manager']));
        $findLink = $pdo->prepare('SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1');
        $addLink = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach ($tenants->fetchAll(PDO::FETCH_COLUMN) ?: [] as $tenantId) {
            $tenantId = (int) $tenantId;
            if ($tenantId < 1) {
                continue;
            }
            $findPerm->execute([$tenantId, $permSlug]);
            $permId = (int) $findPerm->fetchColumn();
            if ($permId < 1) {
                $addPerm->execute([$tenantId, $permName, $permSlug, 'atak', date('Y-m-d H:i:s')]);
                $permId = (int) $pdo->lastInsertId();
            }
            if ($permId < 1) {
                continue;
            }
            $findRoles->execute([$tenantId]);
            foreach ($findRoles->fetchAll(PDO::FETCH_COLUMN) ?: [] as $roleId) {
                $findLink->execute([(int) $roleId, $permId]);
                if ($findLink->fetchColumn() === false) {
                    $addLink->execute([(int) $roleId, $permId]);
                }
            }
        }
    }
    echo "Droit de conclusion des comptes rendus : OK.\n";
} catch (Throwable $e) {
    echo '  [ATTENTION] Droit de conclusion des comptes rendus : ' . $e->getMessage() . "\n";
}
