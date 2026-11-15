<?php

declare(strict_types=1);

/**
 * Étape « SSE : images confidentielles des membres, demandes d'accès et dérogations » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table sse_member_image_grants n'est créée
 * que si elle manque (bootstrap/sse_member_image_grants_migration.php). Aucune donnée ajoutée ni modifiée : le contrôle d'accès
 * aux images des membres ne dépend pas de cette table (sans elle, aucune dérogation n'est reconnue : l'image reste masquée).
 * Retour arrière : migrations/rollback/sse_member_image_grants_down.sql.
 */

$sseImageGrantSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($sseImageGrantSchema->tableExists('sse_member_image_grants')) {
    echo "  [OK] Table sse_member_image_grants déjà présente.\n";
} else {
    (require $root . '/bootstrap/sse_member_image_grants_migration.php')($pdo, static function (string $m): void {
        echo $m;
    });
    if (!$sseImageGrantSchema->tableExists('sse_member_image_grants')) {
        throw new RuntimeException('Création de la table sse_member_image_grants impossible.');
    }
}
