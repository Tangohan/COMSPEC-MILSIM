<?php

declare(strict_types=1);

/**
 * Étape « Qualifications : émetteurs enrichis » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque colonne, index ou table
 * n’est ajouté que s’il manque. Sur qualification_issuers : description, unité de rattachement, signataire du brevet
 * (nom et qualité), logo et archivage (archived_at, NULL = actif). Sur personnel_qualification_definitions :
 * default_issuer_id (émetteur habituel, NULL = rien ne change). Table qualification_issuer_authorizations (membres ou
 * fonctions habilités) depuis migrations/2026_11_02_050_qualification_issuer_authorizations.sql.
 * Aucune donnée n’est modifiée ni supprimée. Sans la table qualification_issuers (référentiel jamais installé),
 * l’étape ne fait rien.
 */

$issuerSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$issuerSchema->tableExists('qualification_issuers')) {
    echo "  [OK] Table qualification_issuers absente : rien à faire.\n";
} else {
    $issuerAdded = [];
    $issuerColumns = [
        'description' => 'TEXT DEFAULT NULL AFTER parent_issuer_id',
        'unit_id' => 'INT UNSIGNED DEFAULT NULL AFTER description',
        'signatory_name' => 'VARCHAR(150) DEFAULT NULL AFTER unit_id',
        'signatory_title' => 'VARCHAR(150) DEFAULT NULL AFTER signatory_name',
        'logo_media_path' => 'VARCHAR(500) DEFAULT NULL AFTER signatory_title',
        'archived_at' => 'DATETIME DEFAULT NULL AFTER logo_media_path',
        'created_by' => 'INT UNSIGNED DEFAULT NULL AFTER archived_at',
    ];
    foreach ($issuerColumns as $issuerCol => $issuerDef) {
        if ($issuerSchema->addColumnIfMissing('qualification_issuers', $issuerCol, $issuerDef)) {
            $issuerAdded[] = 'qualification_issuers.' . $issuerCol;
        }
    }
    if ($issuerSchema->addIndexIfMissing('qualification_issuers', 'idx_qual_issuer_unit', ['unit_id'])) {
        $issuerAdded[] = 'idx_qual_issuer_unit';
    }
    if ($issuerSchema->addColumnIfMissing('personnel_qualification_definitions', 'default_issuer_id', 'INT UNSIGNED DEFAULT NULL')) {
        $issuerAdded[] = 'personnel_qualification_definitions.default_issuer_id';
    }
    if ($issuerSchema->addIndexIfMissing('personnel_qualification_definitions', 'idx_pqd_default_issuer', ['default_issuer_id'])) {
        $issuerAdded[] = 'idx_pqd_default_issuer';
    }
    if (!$issuerSchema->tableExists('qualification_issuer_authorizations')) {
        $issuerSchema->runSqlFile($root . '/migrations/2026_11_02_050_qualification_issuer_authorizations.sql');
        $issuerAdded[] = 'qualification_issuer_authorizations';
    }
    echo $issuerAdded === []
        ? "  [OK] Émetteurs de qualifications déjà à jour.\n"
        : '  [OK] Ajouté : ' . implode(', ', $issuerAdded) . ".\n";
}
