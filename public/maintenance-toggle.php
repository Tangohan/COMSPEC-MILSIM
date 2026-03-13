<?php

declare(strict_types=1);

/**
 * Bascule le mode maintenance (appelé en GET avec token).
 * Ex. : /maintenance-toggle.php?token=VOTRE_MAINTENANCE_TOKEN&action=on
 *       /maintenance-toggle.php?token=...&action=off
 */

$root = dirname(__DIR__);
if (!is_file($root . '/.env')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => '.env introuvable']);
    exit;
}
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
    [$name, $value] = explode('=', $line, 2);
    $_ENV[trim($name)] = trim($value, " \t\"'");
}

header('Content-Type: application/json; charset=utf-8');
$token = $_ENV['MAINTENANCE_TOKEN'] ?? '';
$reqToken = $_GET['token'] ?? '';
if ($token === '' || $reqToken !== $token) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token invalide ou manquant']);
    exit;
}

$action = $_GET['action'] ?? '';
$message = $_GET['message'] ?? 'Maintenance en cours. Merci de réessayer dans quelques minutes.';
$enabled = in_array(strtolower($action), ['1', 'on', 'true', 'yes'], true);

$file = $root . '/storage/maintenance.json';
$storageDir = dirname($file);
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}
$data = ['enabled' => $enabled, 'message' => $message, 'allowed_ips' => []];
if (is_file($file)) {
    $cur = @json_decode((string) file_get_contents($file), true);
    if (is_array($cur) && isset($cur['allowed_ips'])) {
        $data['allowed_ips'] = $cur['allowed_ips'];
    }
}

if (!file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Impossible d\'écrire storage/maintenance.json']);
    exit;
}

echo json_encode(['ok' => true, 'maintenance' => $enabled]);
