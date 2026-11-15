<?php

declare(strict_types=1);

/**
 * Étape « SEEK II : signature tracée » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la colonne sse_persons.signature_image n'est
 * ajoutée que si elle manque (depuis migrations/2026_11_15_110_sse_persons_signature_image.sql). Aucune donnée modifiée.
 * Retour arrière : migrations/rollback/sse_persons_signature_image_down.sql.
 */

$sigImgSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($sigImgSchema->tableExists('sse_seek_records')) {
    if ($sigImgSchema->columnExists('sse_seek_records', 'signature_image')) {
        echo "  [OK] Colonne sse_seek_records.signature_image déjà présente.\n";
    } else {
        $sigImgSchema->runSqlFile($root . '/migrations/2026_11_15_111_sse_seek_records_signature_image.sql');
        echo "  [OK] Colonne créée : sse_seek_records.signature_image.\n";
    }
}
if (!$sigImgSchema->tableExists('sse_persons')) {
    echo "  [OK] Table sse_persons absente : la colonne sera créée avec elle (atak_sse_persons).\n";
} elseif ($sigImgSchema->columnExists('sse_persons', 'signature_image')) {
    echo "  [OK] Colonne sse_persons.signature_image déjà présente.\n";
} else {
    $sigImgSchema->runSqlFile($root . '/migrations/2026_11_15_110_sse_persons_signature_image.sql');
    if (!$sigImgSchema->columnExists('sse_persons', 'signature_image')) {
        throw new RuntimeException('Création de la colonne sse_persons.signature_image impossible.');
    }
    echo "  [OK] Colonne créée : sse_persons.signature_image.\n";
}
