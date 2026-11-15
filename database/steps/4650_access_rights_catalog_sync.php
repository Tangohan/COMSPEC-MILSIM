<?php

declare(strict_types=1);

/**
 * Étape « Catalogue des droits d'accès : droits déclarés depuis la page Niveaux d'accès » (Schéma).
 *
 * Crée, pour chaque communauté qui ne l'a pas encore, les deux droits testés dans le code mais jusque-là absents du
 * catalogue : training.publications.manage (publier et retirer des parcours) et events.manage (présences et événements
 * des tableaux de pilotage). Aucun niveau d'accès n'est modifié : personne ne reçoit ces droits automatiquement, le
 * comportement actuel de chaque communauté est inchangé. Idempotente : une ligne déjà présente n'est ni touchée ni dupliquée.
 * Compatible SQLite et MySQL (INSERT simple, aucune clé ajoutée). Retour arrière : migrations/rollback/access_rights_catalog_sync_down.sql.
 */

try {
    $declared = [
        'training.publications.manage' => ['Publier et retirer des parcours de formation', 'training'],
        'events.manage' => ['Gérer les présences et les événements', 'operations'],
    ];
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
            foreach ($declared as $slug => [$name, $module]) {
                $find->execute([$tenantId, $slug]);
                if ($find->fetchColumn() === false) {
                    $insert->execute([$tenantId, $name, $slug, $module, date('Y-m-d H:i:s')]);
                    $added++;
                }
            }
        }
    }
    echo '  [OK] Droits du catalogue : ' . $added . " ligne(s) créée(s), aucun niveau modifié.\n";
} catch (Throwable $e) {
    echo '  [ATTENTION] Catalogue des droits : ' . $e->getMessage() . "\n";
}
