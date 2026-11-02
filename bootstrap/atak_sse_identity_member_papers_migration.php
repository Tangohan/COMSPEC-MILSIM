<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * COMSPEC IDENTITY — papiers des joueurs dans la fiche membre, kit de mission, pays émetteurs créés à la main.
 *
 * - sse_identities.user_id : identité rattachée au compte d'un membre (identité principale, alias, couverture).
 * - sse_id_kits : papiers que le joueur porte en mission (par défaut, par carte ou par opération), envoyés au
 *   serveur de jeu dans le pack (kits[], clé de liaison = Steam ID du compte).
 * - sse_id_checks.subject_steam_id : Steam ID du joueur contrôlé (historique des contrôles subis).
 * - sse_id_countries : thème d'univers, langues imprimées, types de papiers délivrés, durées par type, drapeau ou
 *   couleurs, archivage (un pays utilisé n'est jamais supprimé).
 *
 * Idempotente (colonnes et table ajoutées seulement si elles manquent) ; MySQL et sqlite (tests). Suppose la
 * migration du registre (atak_sse_identity_migration.php) déjà passée : sinon ne fait rien.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $u = static fn (string $def): string => $sqlite ? str_replace(' UNSIGNED', '', $def) : $def;

    if (!$schema->tableExists('sse_identities') || !$schema->tableExists('sse_id_countries')) {
        $log("  [ATTENTION] registre d'identité SSE absent — papiers des membres reportés\n");

        return;
    }

    $columns = [
        'sse_identities' => [
            'user_id' => 'INT UNSIGNED DEFAULT NULL',
        ],
        'sse_id_checks' => [
            'subject_steam_id' => 'VARCHAR(32) DEFAULT NULL',
        ],
        'sse_id_countries' => [
            'theme' => "VARCHAR(24) NOT NULL DEFAULT 'contemporary'",
            'languages' => 'VARCHAR(64) DEFAULT NULL',
            'doc_types' => 'VARCHAR(80) DEFAULT NULL',
            'validity_json' => 'VARCHAR(255) DEFAULT NULL',
            'flag_path' => 'VARCHAR(255) DEFAULT NULL',
            'color_primary' => 'VARCHAR(7) DEFAULT NULL',
            'color_secondary' => 'VARCHAR(7) DEFAULT NULL',
            'archived_at' => 'DATETIME DEFAULT NULL',
            'created_by_user_id' => 'INT UNSIGNED DEFAULT NULL',
        ],
    ];
    foreach ($columns as $table => $cols) {
        if (!$schema->tableExists($table)) {
            continue;
        }
        foreach ($cols as $col => $def) {
            if ($schema->addColumnIfMissing($table, $col, $u($def))) {
                $log("  [OK] {$table}.{$col}\n");
            }
        }
    }
    if ($schema->addIndexIfMissing('sse_identities', 'idx_sse_identities_user', ['tenant_id', 'user_id'])) {
        $log("  [OK] index idx_sse_identities_user\n");
    }
    if ($schema->tableExists('sse_id_checks') && $schema->addIndexIfMissing('sse_id_checks', 'idx_sse_id_checks_subject_steam', ['tenant_id', 'subject_steam_id'])) {
        $log("  [OK] index idx_sse_id_checks_subject_steam\n");
    }

    if (!$schema->tableExists('sse_id_kits')) {
        $lines = [
            $sqlite ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
            $u('tenant_id INT UNSIGNED NOT NULL'),
            $u('user_id INT UNSIGNED NOT NULL'),
            $u('document_id INT UNSIGNED NOT NULL'),
            $u('context_id INT UNSIGNED DEFAULT NULL'),
            'op_key VARCHAR(64) DEFAULT NULL',
            $u('created_by_user_id INT UNSIGNED DEFAULT NULL'),
            'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ];
        if (!$sqlite) {
            $lines[] = 'PRIMARY KEY (id)';
            $lines[] = 'KEY idx_sse_id_kits_user (tenant_id, user_id)';
            $lines[] = 'KEY idx_sse_id_kits_document (tenant_id, document_id)';
            $lines[] = 'CONSTRAINT fk_sse_id_kits_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE';
            if ($schema->tableExists('sse_id_documents')) {
                $lines[] = 'CONSTRAINT fk_sse_id_kits_document FOREIGN KEY (document_id) REFERENCES sse_id_documents (id) ON DELETE CASCADE ON UPDATE CASCADE';
            }
        }
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sse_id_kits (\n    " . implode(",\n    ", $lines) . "\n)"
            . ($sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')
        );
        if ($sqlite) {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sse_id_kits_user ON sse_id_kits (tenant_id, user_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sse_id_kits_document ON sse_id_kits (tenant_id, document_id)');
        }
        $log("  [OK] sse_id_kits\n");
    } else {
        $log("  [OK] sse_id_kits (déjà présente)\n");
    }
};
