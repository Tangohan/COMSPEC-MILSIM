<?php
declare(strict_types=1);

/**
 * Exécute les migrations (schéma + seed) — sans Composer/Phinx.
 * CLI : php migrate.php
 * Web : public/migrate.php
 */

$root = dirname(__FILE__);
$isWeb = php_sapi_name() !== 'cli';

if ($isWeb) {
    header('Content-Type: text/html; charset=utf-8');
    ob_start();
}

echo "=== Migrations Athena ===\n\n";

require $root . DIRECTORY_SEPARATOR . 'run-migrations.php';

if ($isWeb) {
    $out = ob_get_clean();
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Migrations Athena</title></head><body><pre>' . htmlspecialchars($out) . '</pre></body></html>';
}
