<?php

declare(strict_types=1);

/**
 * Cadence automatique de bilans roleplay (6/8/12 mois selon ancienneté) : date du dernier
 * bilan effectué, source de vérité pour App\Support\RoleplayBilanPolicy. Idempotent.
 */
return static function (PDO $pdo): void {
    $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasColumn($pdo, 'personnel_profiles', 'rp_last_review_at')) {
        try {
            $pdo->exec('ALTER TABLE personnel_profiles ADD COLUMN rp_last_review_at DATETIME DEFAULT NULL');
        } catch (Throwable $e) {
            echo '  [ATTENTION] roleplay_bilan_cadence (rp_last_review_at) : ' . $e->getMessage() . "\n";
        }
    }
};
