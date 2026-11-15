<?php

declare(strict_types=1);

/**
 * Étape « Droit atak.toc.purge : purger la carte du poste TOC web » (Schéma).
 *
 * Crée le droit dans chaque communauté qui ne l'a pas encore, pour qu'il soit cochable dans Niveaux d'accès. Aucun niveau
 * d'accès n'est modifié : personne ne reçoit ce droit automatiquement (la purge est plus large que la purge des archives
 * et que « effacer la carte » : elle se donne à la main). L'administration de la communauté et celle de la plateforme
 * purgent sans l'avoir. Idempotente. Retour arrière : migrations/rollback/atak_toc_purge_permission_down.sql.
 */

try {
    $tenants = $pdo->query('SELECT id FROM tenants');
    $added = 0;
    if ($tenants) {
        $find = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $insert = $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, ?)');
        foreach ($tenants->fetchAll(PDO::FETCH_COLUMN) ?: [] as $tenantId) {
            $tenantId = (int) $tenantId;
            if ($tenantId < 1) {
                continue;
            }
            $find->execute([$tenantId, 'atak.toc.purge']);
            if ($find->fetchColumn() === false) {
                $insert->execute([$tenantId, 'Purger la carte du poste TOC web (suppression en masse par périmètre)', 'atak.toc.purge', 'atak', date('Y-m-d H:i:s')]);
                $added++;
            }
        }
    }
    echo '  [OK] Droit atak.toc.purge : ' . $added . " ligne(s) créée(s), aucun niveau modifié.\n";
} catch (Throwable $e) {
    echo '  [ATTENTION] Droit atak.toc.purge : ' . $e->getMessage() . "\n";
}
