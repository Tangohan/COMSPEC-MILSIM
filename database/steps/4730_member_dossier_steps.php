<?php

declare(strict_types=1);

/**
 * Étape « Suivi du dossier des membres : étapes personnalisées, compléments et historique » (Schéma).
 *
 * Crée, quand elles manquent, les tables member_dossier_steps et member_dossier_step_events (CREATE TABLE IF NOT EXISTS,
 * depuis migrations/2026_11_19_100_member_dossier_steps.sql), puis déclare dans chaque communauté les deux droits
 * effectifs.suivi.view et effectifs.suivi.manage pour qu'ils soient cochables dans Niveaux d'accès. Aucun niveau n'est
 * modifié : personne ne reçoit ces droits automatiquement, aucune donnée existante n'est touchée. Idempotente.
 * Retour arrière : migrations/rollback/member_dossier_steps_down.sql.
 */

$mdsSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$mdsTables = ['member_dossier_steps', 'member_dossier_step_events'];
$mdsMissing = array_values(array_filter($mdsTables, static fn (string $t): bool => !$mdsSchema->tableExists($t)));
if ($mdsMissing === []) {
    echo "  [OK] Tables du suivi de dossier déjà présentes.\n";
} else {
    $mdsSchema->runSqlFile($root . '/migrations/2026_11_19_100_member_dossier_steps.sql');
    foreach ($mdsTables as $mdsTable) {
        if (!$mdsSchema->tableExists($mdsTable)) {
            throw new RuntimeException('Création de la table ' . $mdsTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $mdsMissing) . ".\n";
}

try {
    $mdsDeclared = [
        'effectifs.suivi.view' => ['Consulter le suivi du dossier des membres', 'personnel'],
        'effectifs.suivi.manage' => ['Modifier le suivi du dossier des membres', 'personnel'],
    ];
    $mdsAdded = 0;
    $mdsTenants = $pdo->query('SELECT id FROM tenants');
    if ($mdsTenants) {
        $mdsFind = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $mdsInsert = $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, ?)');
        foreach ($mdsTenants->fetchAll(PDO::FETCH_COLUMN) ?: [] as $mdsTenantId) {
            $mdsTenantId = (int) $mdsTenantId;
            if ($mdsTenantId < 1) {
                continue;
            }
            foreach ($mdsDeclared as $mdsSlug => [$mdsName, $mdsModule]) {
                $mdsFind->execute([$mdsTenantId, $mdsSlug]);
                if ($mdsFind->fetchColumn() === false) {
                    $mdsInsert->execute([$mdsTenantId, $mdsName, $mdsSlug, $mdsModule, date('Y-m-d H:i:s')]);
                    $mdsAdded++;
                }
            }
        }
    }
    echo '  [OK] Droits du suivi de dossier : ' . $mdsAdded . " ligne(s) créée(s), aucun niveau modifié.\n";
} catch (Throwable $e) {
    echo '  [ATTENTION] Droits du suivi de dossier : ' . $e->getMessage() . "\n";
}
