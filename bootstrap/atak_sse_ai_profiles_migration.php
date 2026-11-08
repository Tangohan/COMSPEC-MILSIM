<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Profils d'IA du Bureau SSE et éléments gardés d'une partie à l'autre : tables sse_ai_profiles et sse_ai_placements.
 *
 * Idempotente ; MySQL, MariaDB et sqlite (tests). Pas de clé étrangère : les tables reliées n'ont pas toutes le même type
 * d'une base à l'autre (erreur 150 de la migration 4070) ; la communauté est vérifiée à chaque requête.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $pk = $sqlite ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id INT UNSIGNED NOT NULL AUTO_INCREMENT';
    $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $tables = [
        'sse_ai_profiles' => [
            [
                $pk,
                'tenant_id INT UNSIGNED NOT NULL',
                'identity_id INT UNSIGNED DEFAULT NULL',
                'context_id INT UNSIGNED DEFAULT NULL',
                'op_key VARCHAR(64) DEFAULT NULL',
                'label VARCHAR(120) NOT NULL',
                "side VARCHAR(8) NOT NULL DEFAULT 'civ'",
                "category VARCHAR(16) NOT NULL DEFAULT 'civilian'",
                'faction VARCHAR(120) DEFAULT NULL',
                "papers VARCHAR(16) NOT NULL DEFAULT 'identity'",
                "behaviour VARCHAR(16) NOT NULL DEFAULT 'auto'",
                'kit VARCHAR(190) DEFAULT NULL',
                'notes TEXT DEFAULT NULL',
                'created_by_user_id INT UNSIGNED DEFAULT NULL',
                'created_at DATETIME NOT NULL',
                'updated_at DATETIME NOT NULL',
                'archived_at DATETIME DEFAULT NULL',
            ],
            ['idx_sse_ai_profiles_tenant' => 'tenant_id, archived_at', 'idx_sse_ai_profiles_identity' => 'tenant_id, identity_id'],
        ],
        'sse_ai_placements' => [
            [
                $pk,
                'tenant_id INT UNSIGNED NOT NULL',
                'context_id INT UNSIGNED NOT NULL',
                'op_key VARCHAR(64) DEFAULT NULL',
                "kind VARCHAR(8) NOT NULL DEFAULT 'ai'",
                'profile_id INT UNSIGNED DEFAULT NULL',
                'var_name VARCHAR(64) DEFAULT NULL',
                'class_name VARCHAR(120) DEFAULT NULL',
                'pos_x DOUBLE NOT NULL DEFAULT 0',
                'pos_y DOUBLE NOT NULL DEFAULT 0',
                'label VARCHAR(190) NOT NULL',
                'config_json TEXT DEFAULT NULL',
                'created_by_user_id INT UNSIGNED DEFAULT NULL',
                'created_at DATETIME NOT NULL',
            ],
            ['idx_sse_ai_placements_scope' => 'tenant_id, context_id'],
        ],
    ];
    foreach ($tables as $name => [$lines, $indexes]) {
        if ($schema->tableExists($name)) {
            $log("  [OK] {$name} (déjà présente)\n");
            continue;
        }
        if (!$sqlite) {
            $lines[] = 'PRIMARY KEY (id)';
            foreach ($indexes as $idx => $cols) {
                $lines[] = "KEY {$idx} ({$cols})";
            }
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$name} (\n    " . implode(",\n    ", $lines) . "\n){$engine}");
        if ($sqlite) {
            foreach ($indexes as $idx => $cols) {
                $pdo->exec("CREATE INDEX IF NOT EXISTS {$idx} ON {$name} ({$cols})");
            }
        }
        $log("  [OK] {$name}\n");
    }
};
