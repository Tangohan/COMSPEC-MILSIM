<?php

declare(strict_types=1);

/**
 * JNET : origine des échanges créés automatiquement depuis le jeu (reco, photos, rapports ATAK).
 * - source_kind / source_ref : identifient la donnée de terrain (une reco modifiée en jeu met à jour
 *   son échange au lieu d'en créer un second) ;
 * - image_url : vignette d'une photo de reco non confidentielle.
 *
 * Les installations neuves reçoivent ces colonnes via migrations/jnet_exchanges.sql ; ce script
 * complète les tables déjà créées.
 *
 * @return callable(PDO): void
 */
return static function (PDO $pdo): void {
    $hasTable = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };
    $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };
    $hasIndex = static function (PDO $pdo, string $table, string $index): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $index]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable($pdo, 'jnet_exchanges')) {
        return;
    }

    $columns = [
        'source_kind' => "ALTER TABLE jnet_exchanges ADD COLUMN source_kind VARCHAR(24) NULL
            COMMENT 'Origine automatique : recon_note|recon_image|atak_report (NULL = publié à la main)' AFTER requires_ack",
        'source_ref' => "ALTER TABLE jnet_exchanges ADD COLUMN source_ref VARCHAR(64) NULL
            COMMENT 'Identifiant de la donnée de terrain' AFTER source_kind",
        'image_url' => "ALTER TABLE jnet_exchanges ADD COLUMN image_url VARCHAR(500) NULL
            COMMENT 'Vignette d’une photo de reco (jamais pour un cliché confidentiel)' AFTER link_url",
    ];
    foreach ($columns as $column => $sql) {
        if ($hasColumn($pdo, 'jnet_exchanges', $column)) {
            continue;
        }
        try {
            $pdo->exec($sql);
            echo "  [OK] jnet_exchanges.{$column}\n";
        } catch (Throwable $e) {
            echo "  [ATTENTION] jnet_exchanges.{$column} : " . $e->getMessage() . "\n";
        }
    }

    if (!$hasIndex($pdo, 'jnet_exchanges', 'jnet_exchanges_source')
        && $hasColumn($pdo, 'jnet_exchanges', 'source_kind')
        && $hasColumn($pdo, 'jnet_exchanges', 'source_ref')) {
        try {
            $pdo->exec('ALTER TABLE jnet_exchanges ADD UNIQUE KEY jnet_exchanges_source (tenant_id, source_kind, source_ref)');
            echo "  [OK] jnet_exchanges index source\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] jnet_exchanges index source : ' . $e->getMessage() . "\n";
        }
    }
};
