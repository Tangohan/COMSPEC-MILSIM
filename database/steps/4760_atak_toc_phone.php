<?php

declare(strict_types=1);

/**
 * Étape « Actions sur les téléphones depuis le poste TOC web » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente :
 *  - tables atak_toc_phone_settings et atak_toc_phone_events (bootstrap/atak_toc_phone_migration.php, équivalent MySQL
 *    migrations/2026_11_15_140_atak_toc_phone.sql), rejouées sans effet si elles existent ;
 *  - droit atak.toc.phone (« agir sur les téléphones en jeu ») créé dans chaque communauté, accordé UNIQUEMENT aux
 *    niveaux d'accès qui ont déjà atak.toc.manage (les gestionnaires du poste TOC) ; aucun autre niveau, aucun membre.
 * Aucune donnée existante modifiée. Réglage de chaque communauté : désactivé tant qu'elle ne l'a pas activé.
 * Retour arrière : migrations/rollback/atak_toc_phone_down.sql.
 */

$atakTocPhoneMigrate = require $root . '/bootstrap/atak_toc_phone_migration.php';
try {
    echo "Migration atak_toc_phone (actions sur les téléphones en jeu : réglage et journal)…\n";
    $atakTocPhoneMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_toc_phone : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_toc_phone', $e);
}

try {
    $tenants = $pdo->query('SELECT id FROM tenants');
    $created = 0;
    $linked = 0;
    if ($tenants) {
        $findPerm = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $addPerm = $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, ?)');
        $managers = $pdo->prepare(
            'SELECT DISTINCT rp.role_id FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE p.tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'p.slug')
        );
        $findLink = $pdo->prepare('SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1');
        $addLink = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach ($tenants->fetchAll(PDO::FETCH_COLUMN) ?: [] as $tenantId) {
            $tenantId = (int) $tenantId;
            if ($tenantId < 1) {
                continue;
            }
            $findPerm->execute([$tenantId, 'atak.toc.phone']);
            $permId = (int) $findPerm->fetchColumn();
            if ($permId < 1) {
                // Libellé court : la colonne name de certaines bases est limitée à 100 caractères (le détail reste dans le catalogue des droits).
                $addPerm->execute([$tenantId, 'Agir sur les téléphones en jeu depuis le poste TOC web', 'atak.toc.phone', 'atak', date('Y-m-d H:i:s')]);
                $permId = (int) $pdo->lastInsertId();
                $created++;
            }
            if ($permId < 1) {
                continue;
            }
            // Équivalence stricte avec les gestionnaires du poste : les niveaux qui ont atak.toc.manage, et eux seuls.
            $managers->execute([$tenantId, 'atak.toc.manage']);
            foreach (array_map('intval', $managers->fetchAll(PDO::FETCH_COLUMN) ?: []) as $roleId) {
                $findLink->execute([$roleId, $permId]);
                if ($findLink->fetchColumn() === false) {
                    $addLink->execute([$roleId, $permId]);
                    $linked++;
                }
            }
        }
    }
    echo '  [OK] Droit atak.toc.phone : ' . $created . ' créé(s), ' . $linked . " niveau(x) de gestionnaires du poste TOC lié(s), aucun autre.\n";
} catch (Throwable $e) {
    echo '  [ATTENTION] Droit atak.toc.phone : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();
