<?php

declare(strict_types=1);

/**
 * Étape « Poste TOC web : suivi lu / non lu des rapports par opérateur » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table atak_toc_report_reads n'est créée
 * que si elle manque (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_16_200_atak_toc_report_reads.sql). Aucune donnée
 * n'est ajoutée ni modifiée. Sans cette étape, le panneau Rapports du poste TOC web marque tout « non lu » sans rien conserver.
 * Retour arrière : migrations/rollback/atak_toc_report_reads_down.sql.
 */

$tocReadsSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($tocReadsSchema->tableExists('atak_toc_report_reads')) {
    echo "  [OK] Table atak_toc_report_reads déjà présente.\n";
} else {
    $tocReadsSchema->runSqlFile($root . '/migrations/2026_11_16_200_atak_toc_report_reads.sql');
    if (!$tocReadsSchema->tableExists('atak_toc_report_reads')) {
        throw new RuntimeException('Création de la table atak_toc_report_reads impossible.');
    }
    echo "  [OK] Table atak_toc_report_reads créée.\n";
}
