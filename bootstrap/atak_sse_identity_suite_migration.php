<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * COMSPEC IDENTITY — recoupement par clé biométrique et carte militaire :
 *   - sse_persons.bio_key et sse_identities.bio_key, index de recherche, et sse_id_checks (tenant_id, subject_bio_key) ;
 *   - sse_id_settings : réglages par communauté (envoi automatique des contrôles, activé sans ligne) ;
 *   - la carte militaire est proposée aux pays existants, une seule fois (au premier passage, repéré par l'ajout de la
 *     colonne) : un pays dont le responsable la retire ensuite ne la retrouve pas à la migration suivante.
 *
 * Idempotente ; MySQL, MariaDB et sqlite (tests). Suppose le registre déjà passé : sinon ne fait rien.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    if (!$schema->tableExists('sse_identities') || !$schema->tableExists('sse_id_checks')) {
        $log("  [ATTENTION] registre d'identité SSE absent — recoupement reporté\n");

        return;
    }
    if (!$schema->tableExists('sse_id_settings')) {
        $pdo->exec('CREATE TABLE sse_id_settings (tenant_id INT NOT NULL PRIMARY KEY, auto_transmit TINYINT NOT NULL DEFAULT 1, updated_at DATETIME DEFAULT NULL)');
        $log("  [OK] sse_id_settings\n");
    }
    $firstPass = $schema->addColumnIfMissing('sse_identities', 'bio_key', 'VARCHAR(64) DEFAULT NULL');
    if ($firstPass) {
        $log("  [OK] sse_identities.bio_key\n");
    }
    if ($schema->addIndexIfMissing('sse_identities', 'idx_sse_identities_bio', ['tenant_id', 'bio_key'])) {
        $log("  [OK] index sse_identities (tenant_id, bio_key)\n");
    }
    if ($schema->addIndexIfMissing('sse_id_checks', 'idx_sse_id_checks_bio', ['tenant_id', 'subject_bio_key'])) {
        $log("  [OK] index sse_id_checks (tenant_id, subject_bio_key)\n");
    }
    if ($schema->tableExists('sse_persons')) {
        if ($schema->addColumnIfMissing('sse_persons', 'bio_key', 'VARCHAR(64) DEFAULT NULL')) {
            $log("  [OK] sse_persons.bio_key\n");
        }
        if ($schema->addIndexIfMissing('sse_persons', 'idx_sse_persons_bio', ['tenant_id', 'bio_key'])) {
            $log("  [OK] index sse_persons (tenant_id, bio_key)\n");
        }
    }
    if ($firstPass && $schema->tableExists('sse_id_countries')) {
        $update = $pdo->prepare('UPDATE sse_id_countries SET doc_types = ? WHERE id = ?');
        $rows = $pdo->query("SELECT id, doc_types FROM sse_id_countries WHERE doc_types IS NOT NULL AND doc_types <> ''");
        foreach ($rows !== false ? $rows->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
            if (!str_contains((string) $row['doc_types'], 'military_id')) {
                $update->execute([$row['doc_types'] . ',military_id', (int) $row['id']]);
            }
        }
        $log("  [OK] carte militaire proposée aux pays existants\n");
    }
};
