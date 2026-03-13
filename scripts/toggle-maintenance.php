<?php

declare(strict_types=1);

/**
 * Bascule le mode maintenance (fichier storage/maintenance.json).
 * CLI : php scripts/toggle-maintenance.php on [message]
 *       php scripts/toggle-maintenance.php off
 * Web : GET/POST ?token=MAINTENANCE_TOKEN&action=on|off (voir .env)
 */

$root = dirname(__DIR__);

if (!is_file($root . '/.env')) {
    fwrite(STDERR, ".env introuvable.\n");
    exit(1);
}
$lines = file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
    [$name, $value] = explode('=', $line, 2);
    $_ENV[trim($name)] = trim($value, " \t\"'");
}

$isWeb = php_sapi_name() !== 'cli';
$file = $root . '/storage/maintenance.json';
$storageDir = dirname($file);
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

if ($isWeb) {
    fwrite(STDERR, "Utilisez public/maintenance-toggle.php?token=...&action=on|off depuis le navigateur.\n");
    exit(1);
}
$action = $argv[1] ?? '';
$message = $argv[2] ?? 'Maintenance en cours. Merci de réessayer dans quelques minutes.';

$enabled = in_array(strtolower($action), ['1', 'on', 'true', 'yes'], true);

$data = ['enabled' => $enabled, 'message' => $message, 'allowed_ips' => []];
if (is_file($file)) {
    $cur = @json_decode((string) file_get_contents($file), true);
    if (is_array($cur) && isset($cur['allowed_ips'])) {
        $data['allowed_ips'] = $cur['allowed_ips'];
    }
}

if (!file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    fwrite(STDERR, "Impossible d'écrire $file\n");
    exit(1);
}

echo $enabled ? "Maintenance activée.\n" : "Maintenance désactivée.\n";
