<?php

declare(strict_types=1);

/**
 * Photo de compte (users.avatar_url) et portrait opérateur (personnel_profiles.character_portrait_path).
 * Idempotent — n’invente aucune photo : colonnes ajoutées vides (NULL) si absentes.
 * Ne crée pas users.avatar_path (colonne inexistante, jamais utilisée).
 */
return function (PDO $pdo): void {
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

    if ($hasTable('users') && !$hasColumn('users', 'avatar_url')) {
        $after = $hasColumn('users', 'steam_id') ? ' AFTER `steam_id`' : '';
        $pdo->exec(
            'ALTER TABLE `users` ADD COLUMN `avatar_url` varchar(500) DEFAULT NULL' . $after
        );
    }

    if ($hasTable('personnel_profiles') && !$hasColumn('personnel_profiles', 'character_portrait_path')) {
        $pdo->exec(
            'ALTER TABLE `personnel_profiles` ADD COLUMN `character_portrait_path` varchar(255) DEFAULT NULL'
        );
    }

    if ($hasTable('personnel_profiles') && !$hasColumn('personnel_profiles', 'character_portrait_locked')) {
        $pdo->exec(
            'ALTER TABLE `personnel_profiles` ADD COLUMN `character_portrait_locked` tinyint(1) NOT NULL DEFAULT 0'
        );
    }
};
