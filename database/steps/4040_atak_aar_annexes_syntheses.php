<?php

declare(strict_types=1);

/**
 * Étape « Comptes rendus : annexes (BDA, CR du terrain, textes rédigés), synthèses de plusieurs CR, et filet pour les
 * tables des terminaux ATAK (Data Inspector) ».
 *
 * Idempotente : l'état réel de la base est vérifié avant toute création.
 */

$aarSchema = new \App\Support\Migrations\SchemaInspector($pdo);

if (!$aarSchema->tableExists('aar_report_annexes')) {
    $pdo->exec(
        'CREATE TABLE aar_report_annexes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            report_id INT UNSIGNED NOT NULL,
            kind VARCHAR(16) NOT NULL,
            source_id INT UNSIGNED DEFAULT NULL,
            title VARCHAR(200) NOT NULL DEFAULT \'\',
            body TEXT DEFAULT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_aar_annexes_report (tenant_id, report_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "  [OK] Table aar_report_annexes créée.\n";
}

if (!$aarSchema->tableExists('aar_syntheses')) {
    $pdo->exec(
        'CREATE TABLE aar_syntheses (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            author_user_id INT UNSIGNED DEFAULT NULL,
            title VARCHAR(200) NOT NULL,
            report_ids_json TEXT DEFAULT NULL,
            improvements TEXT DEFAULT NULL,
            lessons TEXT DEFAULT NULL,
            conclusion TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_aar_syntheses_tenant (tenant_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "  [OK] Table aar_syntheses créée.\n";
}

// Data Inspector : la page annonçait « terminaux » indisponible quand la table des terminaux ou celle de leur
// synchronisation manquait (étapes d'origine archivées, jamais rejouées sur certaines installations).
try {
    if (!$aarSchema->tableExists('atak_terminals')) {
        $atakTerminalsMigrate = require $root . '/bootstrap/atak_realism_registry_migration.php';
        $atakTerminalsMigrate($pdo);
        echo "  [OK] Table atak_terminals créée.\n";
    }
    if (!$aarSchema->tableExists('atak_terminal_sync')) {
        require_once $root . '/bootstrap/atak_terminal_sync_migration.php';
        run_atak_terminal_sync_migration($pdo);
        echo "  [OK] Table atak_terminal_sync créée.\n";
    }
} catch (Throwable $e) {
    echo '  [ATTENTION] Tables des terminaux ATAK : ' . $e->getMessage() . "\n";
}
