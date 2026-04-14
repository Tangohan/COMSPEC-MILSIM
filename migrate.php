<?php

declare(strict_types=1);

/**
 * Alias historique — même pipeline que setup-database.php (schéma + seed complet).
 * CLI : php migrate.php
 * Web : public/migrate.php (même rendu texte brut que les autres entrées web du pipeline).
 */

$root = dirname(__FILE__);

if (PHP_SAPI !== 'cli') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');

    require_once $root . '/bootstrap/env.php';
    load_env($root);

    require_once $root . '/bootstrap/migrations_web_stream.php';
    migrations_web_begin_plain_response();
}

echo "=== Migrations Athena (setup-database) ===\n\n";

require $root . DIRECTORY_SEPARATOR . 'setup-database.php';
