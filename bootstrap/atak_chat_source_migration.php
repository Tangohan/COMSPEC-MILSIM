<?php

declare(strict_types=1);

/**
 * Origine des messages radio ATAK (terrain vs poste de commandement).
 * Idempotent — appelée depuis run-migrations.php.
 */
return static function (PDO $pdo): void {
    if (!function_exists('schema_ensure_column')) {
        require_once __DIR__ . '/schema_ensure_column.php';
    }

    schema_ensure_column(
        $pdo,
        'atak_chat_messages',
        'source',
        "`source` varchar(16) NOT NULL DEFAULT 'game' AFTER `body`"
    );
};
