<?php

declare(strict_types=1);

/**
 * Étape « Briefs air : nom libre et référence automatique » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque colonne, index et table n'est
 * créé que s'il manque, aucune donnée lue ni modifiée, aucune transaction. Ajoute à atak_nine_line :
 * - brief_name : nom libre facultatif du brief (40 caractères) que le JTAC et le pilote peuvent s'annoncer ;
 * - brief_ref : référence automatique lisible à la radio (CAS-23-04), posée à la création par le serveur ;
 * et crée atak_brief_refs, le registre des références déjà attribuées (jamais purgé : une référence ne sert qu'une
 * fois, même si son brief est supprimé ; unicités dans le CREATE TABLE : une référence par communauté, un numéro par
 * communauté et par jour, ce qui départage deux créations simultanées).
 * Sans cette étape les briefs fonctionnent comme avant, sans nom ni référence. Compatible SQLite et MySQL / MariaDB.
 * Retour arrière : migrations/rollback/atak_brief_name_reference_down.sql.
 */

$briefRefSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$briefRefMysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
$briefRefCollate = $briefRefMysql ? ' COLLATE utf8mb4_unicode_ci' : '';
$briefRefDone = [];

if (!$briefRefSchema->tableExists('atak_nine_line')) {
    echo "  [INFO] Table atak_nine_line absente : rien à ajouter.\n";
} else {
    if ($briefRefSchema->addColumnIfMissing('atak_nine_line', 'brief_name', 'VARCHAR(40)' . $briefRefCollate . ' NULL DEFAULT NULL')) {
        $briefRefDone[] = 'brief_name';
    }
    if ($briefRefSchema->addColumnIfMissing('atak_nine_line', 'brief_ref', 'VARCHAR(24)' . $briefRefCollate . ' NULL DEFAULT NULL')) {
        $briefRefDone[] = 'brief_ref';
    }
    foreach (['brief_name', 'brief_ref'] as $briefRefColumn) {
        if (!$briefRefSchema->columnExists('atak_nine_line', $briefRefColumn)) {
            throw new RuntimeException('Colonne atak_nine_line.' . $briefRefColumn . ' impossible à créer.');
        }
    }
    if ($briefRefSchema->addIndexIfMissing('atak_nine_line', 'idx_nine_line_brief_ref', ['tenant_id', 'brief_ref'])) {
        $briefRefDone[] = 'idx_nine_line_brief_ref';
    }
}

if (!$briefRefSchema->tableExists('atak_brief_refs')) {
    $briefRefEngine = $briefRefMysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $briefRefId = $briefRefMysql ? 'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'id INTEGER PRIMARY KEY AUTOINCREMENT';
    $briefRefCode = $briefRefMysql ? 'VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL' : 'TEXT NOT NULL';
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS atak_brief_refs ('
        . $briefRefId . ', tenant_id INT NOT NULL, ref_day DATE NOT NULL, seq INT NOT NULL, ref ' . $briefRefCode . ', created_at DATETIME NOT NULL, '
        . 'CONSTRAINT uq_brief_refs_ref UNIQUE (tenant_id, ref), CONSTRAINT uq_brief_refs_day_seq UNIQUE (tenant_id, ref_day, seq))' . $briefRefEngine
    );
    $briefRefDone[] = 'atak_brief_refs';
}

echo $briefRefDone === []
    ? "  [OK] Nom et référence des briefs déjà en place.\n"
    : '  [OK] Ajouté : ' . implode(', ', $briefRefDone) . ".\n";
