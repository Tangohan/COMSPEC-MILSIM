<?php

declare(strict_types=1);

/**
 * Enrichissement des comptes-rendus de reconnaissance (MRT / TAMMUC simplifié) :
 * heure de captation + sections Terrain / Adversaire / Mission / Moyens / Urgence / Cadre.
 * Idempotent.
 */
function run_recon_pv_tammuc_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('recon_pv_entries')) {
        return;
    }

    $columns = [
        'captured_at' => "ALTER TABLE recon_pv_entries ADD COLUMN captured_at DATETIME NULL DEFAULT NULL AFTER grid_ref",
        'terrain_text' => "ALTER TABLE recon_pv_entries ADD COLUMN terrain_text TEXT NULL AFTER captured_at",
        'adversary_text' => "ALTER TABLE recon_pv_entries ADD COLUMN adversary_text TEXT NULL AFTER terrain_text",
        'mission_text' => "ALTER TABLE recon_pv_entries ADD COLUMN mission_text TEXT NULL AFTER adversary_text",
        'means_text' => "ALTER TABLE recon_pv_entries ADD COLUMN means_text TEXT NULL AFTER mission_text",
        'urgency' => "ALTER TABLE recon_pv_entries ADD COLUMN urgency ENUM('immediate','deferred') NULL DEFAULT NULL AFTER means_text",
        'engagement_frame_text' => "ALTER TABLE recon_pv_entries ADD COLUMN engagement_frame_text TEXT NULL AFTER urgency",
    ];

    foreach ($columns as $col => $sql) {
        if ($hasColumn('recon_pv_entries', $col)) {
            continue;
        }
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            echo '  [ATTENTION] recon_pv_entries.' . $col . ' : ' . $e->getMessage() . "\n";
        }
    }
}
