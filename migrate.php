<?php
declare(strict_types=1);

/**
 * Alias historique — même pipeline que setup-database.php (schéma + seed complet).
 * CLI : php migrate.php
 * Web : public/migrate.php
 */

$root = dirname(__FILE__);
$isWeb = php_sapi_name() !== 'cli';

if ($isWeb) {
    header('Content-Type: text/html; charset=utf-8');
    ob_start();
}

echo "=== Migrations Athena (setup-database) ===\n\n";

require $root . DIRECTORY_SEPARATOR . 'setup-database.php';

if ($isWeb) {
    $out = ob_get_clean();
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Migrations Athena</title></head><body><pre>' . htmlspecialchars($out) . '</pre></body></html>';
}
