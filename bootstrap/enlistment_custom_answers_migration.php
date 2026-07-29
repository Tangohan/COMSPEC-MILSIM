<?php

declare(strict_types=1);

/**
 * Réponses aux questions personnalisées du dossier MilSim + marqueur de refus automatique.
 * Idempotent — appelée depuis run-migrations.php.
 */
if (!function_exists('ensure_enlistment_custom_answers_schema')) {
    function ensure_enlistment_custom_answers_schema(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $hasCol = static function (PDO $pdo, string $table, string $column): bool {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $st->execute([$table, $column]);

            return (bool) $st->fetchColumn();
        };

        if (!$hasCol($pdo, 'enlistments', 'custom_answers_json')) {
            try {
                $pdo->exec('ALTER TABLE enlistments ADD COLUMN custom_answers_json JSON DEFAULT NULL AFTER discord_answers_json');
            } catch (Throwable $e) {
                if (PHP_SAPI === 'cli') {
                    echo '  [ATTENTION] custom_answers_json : ' . $e->getMessage() . "\n";
                }
            }
        }

        if (!$hasCol($pdo, 'enlistments', 'auto_rejected')) {
            try {
                $pdo->exec('ALTER TABLE enlistments ADD COLUMN auto_rejected TINYINT(1) NOT NULL DEFAULT 0 AFTER custom_answers_json');
            } catch (Throwable $e) {
                if (PHP_SAPI === 'cli') {
                    echo '  [ATTENTION] auto_rejected : ' . $e->getMessage() . "\n";
                }
            }
        }
    }
}

return static function (PDO $pdo): void {
    ensure_enlistment_custom_answers_schema($pdo);
};
