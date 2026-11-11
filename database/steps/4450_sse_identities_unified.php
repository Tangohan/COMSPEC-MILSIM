<?php

declare(strict_types=1);

/**
 * Étape « Identités unifiées : les couvertures exportées deviennent des identités » (Schéma + données).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente et rejouable :
 * bootstrap/sse_identities_unified_migration.php crée sse_identity_stories et sse_cover_export_merges, puis fond chaque
 * couverture exportée pas encore traitée dans une identité du membre (lien existant, sinon même nom et même date de
 * naissance, sinon identité d'emprunt créée) et ajoute le texte de sa légende à l'histoire de cette identité.
 * Rien n'est supprimé : sse_cover_exports, sse_cover_export_links et operator_identity_states restent en place.
 *
 * Mode à blanc : variable d'environnement SSE_IDENTITES_A_BLANC=1. Rien n'est créé ni écrit ; l'étape liste ce qui
 * serait fondu et les doublons trouvés. À rejouer ensuite sans la variable.
 */

$sseIdentitiesUnifiedMigrate = require $root . '/bootstrap/sse_identities_unified_migration.php';
try {
    $sseIdentitiesDryRun = in_array((string) getenv('SSE_IDENTITES_A_BLANC'), ['1', 'true', 'oui'], true);
    echo 'Migration identités unifiées (couvertures exportées → identités)' . ($sseIdentitiesDryRun ? ' : À BLANC, rien n’est écrit' : '') . "…\n";
    $sseIdentitiesUnifiedMigrate($pdo, $sseCliLog, $sseIdentitiesDryRun);
} catch (Throwable $e) {
    echo '  [ATTENTION] sse_identities_unified : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('sse_identities_unified', $e);
}
$migrationEnsurePdo();
