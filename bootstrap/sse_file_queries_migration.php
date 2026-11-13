<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * « Passage aux fichiers » (COMSPEC IDENTITY) :
 *  - sse_file_queries : journal des consultations (QW personne, QV véhicule, QA document, QB biométrie), web et jeu,
 *    avec le numéro de requête lisible (QRN), les critères, la réponse (HIT / NO HIT, codes) et les consultations
 *    faites hors liaison remontées plus tard (deferred, idempotentes par clé) ;
 *  - sse_file_mentions : mentions posées par les responsables sur une identité, un papier ou une fiche SEEK II
 *    (mandat, recherché, signalé, disparu, armé et dangereux, prudence), levées sans être effacées ;
 *  - sse_seek_records : fiches biométriques SEEK II remontées du jeu, rattachées au registre quand la clé ou le papier
 *    correspond.
 *
 * Idempotente ; MySQL, MariaDB et sqlite (tests).
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $id = $sqlite ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id INT UNSIGNED NOT NULL AUTO_INCREMENT';
    $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    /**
     * @param list<string> $columns
     * @param list<array{0: string, 1: string, 2: bool}> $indexes nom, colonnes, unique
     */
    $create = static function (string $table, array $columns, array $indexes) use ($pdo, $schema, $sqlite, $engine, $log): void {
        if ($schema->tableExists($table)) {
            $log("  [OK] {$table} (déjà présente)\n");

            return;
        }
        $lines = $columns;
        if (!$sqlite) {
            $lines[] = 'PRIMARY KEY (id)';
            foreach ($indexes as [$name, $cols, $unique]) {
                $lines[] = ($unique ? 'UNIQUE KEY ' : 'KEY ') . $name . ' (' . $cols . ')';
            }
            $lines[] = 'CONSTRAINT fk_' . $table . '_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE';
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$table} (\n    " . implode(",\n    ", $lines) . "\n)" . $engine);
        if ($sqlite) {
            foreach ($indexes as [$name, $cols, $unique]) {
                $pdo->exec('CREATE ' . ($unique ? 'UNIQUE ' : '') . "INDEX IF NOT EXISTS {$name} ON {$table} ({$cols})");
            }
        }
        $log("  [OK] {$table}\n");
    };

    $create('sse_file_queries', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'qrn VARCHAR(32) DEFAULT NULL',
        'query_type CHAR(2) NOT NULL',
        'criteria_json TEXT DEFAULT NULL',
        'source VARCHAR(12) NOT NULL',
        'operator_user_id INT UNSIGNED DEFAULT NULL',
        'operator_label VARCHAR(80) DEFAULT NULL',
        'operator_steam_id VARCHAR(32) DEFAULT NULL',
        'hit TINYINT(1) NOT NULL DEFAULT 0',
        'verdict VARCHAR(16) DEFAULT NULL',
        'codes VARCHAR(1000) DEFAULT NULL',
        'deferred TINYINT(1) NOT NULL DEFAULT 0',
        'idempotency_key VARCHAR(80) DEFAULT NULL',
        'game_time VARCHAR(32) DEFAULT NULL',
        'grid VARCHAR(32) DEFAULT NULL',
        'created_at DATETIME NOT NULL',
    ], [
        ['idx_sse_file_queries_tenant', 'tenant_id, created_at', false],
        ['idx_sse_file_queries_qrn', 'tenant_id, qrn', false],
        ['uq_sse_file_queries_key', 'tenant_id, idempotency_key', true],
    ]);

    $create('sse_file_mentions', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'mention VARCHAR(20) NOT NULL',
        'identity_id INT UNSIGNED DEFAULT NULL',
        'document_id INT UNSIGNED DEFAULT NULL',
        'seek_record_id INT UNSIGNED DEFAULT NULL',
        'note VARCHAR(1000) DEFAULT NULL',
        'reference VARCHAR(64) DEFAULT NULL',
        'created_by_user_id INT UNSIGNED DEFAULT NULL',
        'created_at DATETIME NOT NULL',
        'lifted_at DATETIME DEFAULT NULL',
        'lifted_by_user_id INT UNSIGNED DEFAULT NULL',
        'lift_note VARCHAR(500) DEFAULT NULL',
    ], [
        ['idx_sse_file_mentions_identity', 'tenant_id, identity_id', false],
        ['idx_sse_file_mentions_document', 'tenant_id, document_id', false],
        ['idx_sse_file_mentions_seek', 'tenant_id, seek_record_id', false],
    ]);

    $create('sse_seek_records', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'rec VARCHAR(40) NOT NULL',
        'idempotency_key VARCHAR(80) DEFAULT NULL',
        'bio_key VARCHAR(64) DEFAULT NULL',
        'last_name VARCHAR(120) DEFAULT NULL',
        'first_name VARCHAR(120) DEFAULT NULL',
        'birth_date VARCHAR(16) DEFAULT NULL',
        'subject_unknown TINYINT(1) NOT NULL DEFAULT 0',
        'subject_steam_id VARCHAR(32) DEFAULT NULL',
        'subject_netid VARCHAR(64) DEFAULT NULL',
        'doc_type VARCHAR(20) DEFAULT NULL',
        'doc_country VARCHAR(3) DEFAULT NULL',
        'doc_number VARCHAR(20) DEFAULT NULL',
        'verdict VARCHAR(12) NOT NULL',
        'enrolled TINYINT(1) NOT NULL DEFAULT 0',
        'mod_fingers TINYINT(1) NOT NULL DEFAULT 0',
        'mod_iris TINYINT(1) NOT NULL DEFAULT 0',
        'mod_face TINYINT(1) NOT NULL DEFAULT 0',
        'quality_json VARCHAR(255) DEFAULT NULL',
        'grid VARCHAR(32) DEFAULT NULL',
        'game_time VARCHAR(32) DEFAULT NULL',
        'controller_callsign VARCHAR(80) DEFAULT NULL',
        'controller_steam_id VARCHAR(32) DEFAULT NULL',
        'reason VARCHAR(255) DEFAULT NULL',
        'unit VARCHAR(80) DEFAULT NULL',
        'context_id INT UNSIGNED DEFAULT NULL',
        'op_key VARCHAR(64) DEFAULT NULL',
        'identity_id INT UNSIGNED DEFAULT NULL',
        'person_id INT UNSIGNED DEFAULT NULL',
        'document_id INT UNSIGNED DEFAULT NULL',
        'check_id INT UNSIGNED DEFAULT NULL',
        'created_at DATETIME NOT NULL',
        'updated_at DATETIME DEFAULT NULL',
    ], [
        ['uq_sse_seek_records_rec', 'tenant_id, rec', true],
        ['uq_sse_seek_records_key', 'tenant_id, idempotency_key', true],
        ['idx_sse_seek_records_bio', 'tenant_id, bio_key', false],
        ['idx_sse_seek_records_identity', 'tenant_id, identity_id', false],
    ]);
};
