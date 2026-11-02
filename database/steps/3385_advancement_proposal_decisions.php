<?php

declare(strict_types=1);

/**
 * Étape « Avancement : suivi des propositions automatiques » (Schéma) : table advancement_proposal_decisions.
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table n'est créée que si elle
 * manque, depuis migrations/2026_11_02_040_advancement_proposal_decisions.sql (CREATE TABLE IF NOT EXISTS).
 * Aucune donnée existante n'est touchée.
 */

$advProposalSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($advProposalSchema->tableExists('advancement_proposal_decisions')) {
    echo "  [OK] Table advancement_proposal_decisions déjà présente.\n";
} else {
    $advProposalSchema->runSqlFile($root . '/migrations/2026_11_02_040_advancement_proposal_decisions.sql');
    echo "  [OK] Table advancement_proposal_decisions créée.\n";
}
