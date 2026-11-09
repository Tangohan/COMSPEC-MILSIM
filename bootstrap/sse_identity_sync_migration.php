<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Synchronisation Athena ↔ jeu des identités (COMSPEC IDENTITY) :
 *  - sse_identity_history : chaque valeur écartée lors d'une reprise (version du jeu ou version d'Athena), champ par
 *    champ, restaurable. Rien n'est écrasé sans laisser la valeur perdante ici.
 *  - sse_id_game_covers.reviewed_hash / reviewed_at : identité remontée du jeu déjà examinée (la différence ne revient
 *    que si le jeu envoie autre chose).
 *
 * Idempotente ; MySQL, MariaDB et sqlite (tests).
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    if ($schema->tableExists('sse_identity_history')) {
        $log("  [OK] sse_identity_history (déjà présente)\n");
    } else {
        $lines = [
            $sqlite ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
            'tenant_id INT UNSIGNED NOT NULL',
            'user_id INT UNSIGNED NOT NULL',
            'identity_id INT UNSIGNED NOT NULL',
            'field VARCHAR(32) NOT NULL',
            'kept_value VARCHAR(255) DEFAULT NULL',
            'lost_value VARCHAR(255) DEFAULT NULL',
            'lost_side VARCHAR(8) NOT NULL',
            'decision VARCHAR(12) NOT NULL',
            'game_cover_id INT UNSIGNED DEFAULT NULL',
            'actor_user_id INT UNSIGNED DEFAULT NULL',
            'created_at DATETIME NOT NULL',
            'restored_at DATETIME DEFAULT NULL',
            'restored_by_user_id INT UNSIGNED DEFAULT NULL',
        ];
        if (!$sqlite) {
            $lines = array_merge($lines, [
                'PRIMARY KEY (id)',
                'KEY idx_sse_identity_history_identity (tenant_id, identity_id, created_at)',
                'KEY idx_sse_identity_history_user (tenant_id, user_id, created_at)',
                'CONSTRAINT fk_sse_identity_history_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE',
            ]);
        }
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sse_identity_history (\n    " . implode(",\n    ", $lines) . "\n)"
            . ($sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')
        );
        if ($sqlite) {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sse_identity_history_identity ON sse_identity_history (tenant_id, identity_id, created_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sse_identity_history_user ON sse_identity_history (tenant_id, user_id, created_at)');
        }
        $log("  [OK] sse_identity_history\n");
    }
    if ($schema->tableExists('sse_id_game_covers')) {
        $a = $schema->addColumnIfMissing('sse_id_game_covers', 'reviewed_hash', 'CHAR(40) DEFAULT NULL');
        $b = $schema->addColumnIfMissing('sse_id_game_covers', 'reviewed_at', 'DATETIME DEFAULT NULL');
        $log($a || $b ? "  [OK] sse_id_game_covers.reviewed_hash, reviewed_at\n" : "  [OK] sse_id_game_covers.reviewed_hash (déjà présente)\n");
    } else {
        $log("  [ATTENTION] sse_id_game_covers absente : passez d'abord l'étape 4110 (couvertures reliées à Athena), puis rejouez celle-ci.\n");
    }
};
