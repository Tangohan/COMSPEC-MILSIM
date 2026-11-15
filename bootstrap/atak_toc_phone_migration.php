<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Actions sur les téléphones en jeu depuis le poste TOC web (étape 4760) :
 *  - atak_toc_phone_settings : réglage de chaque communauté (fonction active ou non, journal masqué pour les cibles
 *    hostiles). Sans ligne : fonction désactivée, journal visible ;
 *  - atak_toc_phone_events : journal de chaque demande (notifier, faire sonner, localiser, trianguler) : qui, quoi,
 *    quel téléphone, quand, résultat ; sert aussi aux limites de débit, à l'historique des positions et aux
 *    triangulations en cours. Les ordres vers le jeu passent par la file existante atak_web_commands (genre « phone »).
 *
 * Aucune donnée existante n'est lue ni modifiée. Idempotente ; MySQL, MariaDB et sqlite (tests).
 * Équivalent MySQL : migrations/2026_11_15_140_atak_toc_phone.sql. Retour arrière : migrations/rollback/atak_toc_phone_down.sql.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    if ($schema->tableExists('atak_toc_phone_settings')) {
        $log("  [OK] atak_toc_phone_settings (déjà présente)\n");
    } else {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS atak_toc_phone_settings (
    tenant_id INTEGER NOT NULL PRIMARY KEY,
    enabled TINYINT NOT NULL DEFAULT 0,
    hide_hostile_journal TINYINT NOT NULL DEFAULT 0,
    updated_by INTEGER DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL
)' . $engine
        );
        $log("  [OK] atak_toc_phone_settings\n");
    }

    if ($schema->tableExists('atak_toc_phone_events')) {
        $log("  [OK] atak_toc_phone_events (déjà présente)\n");

        return;
    }
    $id = $sqlite ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT';
    $text = $sqlite ? 'TEXT' : 'MEDIUMTEXT';
    $lines = [
        $id,
        'tenant_id INTEGER NOT NULL',
        'map_id INTEGER NOT NULL DEFAULT 1',
        'user_id INTEGER DEFAULT NULL',
        'operator VARCHAR(60) NOT NULL DEFAULT \'\'',
        'target_ref VARCHAR(40) NOT NULL',
        'target_label VARCHAR(80) NOT NULL DEFAULT \'\'',
        'target_kind VARCHAR(8) NOT NULL DEFAULT \'player\'',
        'action VARCHAR(16) NOT NULL',
        'level VARCHAR(8) DEFAULT NULL',
        'message VARCHAR(200) DEFAULT NULL',
        'ack_requested TINYINT NOT NULL DEFAULT 0',
        'command_id INTEGER DEFAULT NULL',
        'status VARCHAR(12) NOT NULL DEFAULT \'queued\'',
        'result_text VARCHAR(255) NOT NULL DEFAULT \'\'',
        'pos_x DOUBLE DEFAULT NULL',
        'pos_y DOUBLE DEFAULT NULL',
        'accuracy_m DOUBLE DEFAULT NULL',
        'detail_json ' . $text . ' DEFAULT NULL',
        'ready_at DATETIME DEFAULT NULL',
        'expires_at DATETIME DEFAULT NULL',
        'created_at DATETIME NOT NULL',
        'updated_at DATETIME NOT NULL',
    ];
    $indexes = [
        ['idx_atak_toc_phone_events_tenant', 'tenant_id, created_at'],
        ['idx_atak_toc_phone_events_target', 'tenant_id, target_ref, action, created_at'],
        ['idx_atak_toc_phone_events_operator', 'tenant_id, user_id, action, created_at'],
        ['idx_atak_toc_phone_events_command', 'tenant_id, command_id'],
    ];
    if (!$sqlite) {
        $lines[] = 'PRIMARY KEY (id)';
        foreach ($indexes as [$name, $cols]) {
            $lines[] = 'KEY ' . $name . ' (' . $cols . ')';
        }
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS atak_toc_phone_events (\n    " . implode(",\n    ", $lines) . "\n)" . $engine);
    if ($sqlite) {
        foreach ($indexes as [$name, $cols]) {
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$name} ON atak_toc_phone_events ({$cols})");
        }
    }
    $log("  [OK] atak_toc_phone_events\n");
};
