<?php

declare(strict_types=1);

/**
 * Exporte les clés présentes dans tenants.settings (JSON) et site_settings (clé/valeur),
 * sans dump complet des valeurs sensibles — utile pour planifier la migration vers des tables typées.
 *
 * Usage : php scripts/export-tenant-settings-keys.php
 * Requiert .env avec DB_* (comme run-migrations.php).
 */

$root = dirname(__DIR__);
if (is_file($root . '/.env')) {
    $lines = file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, " \t\"'");
    }
}

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$name = $_ENV['DB_NAME'] ?? '';
$user = $_ENV['DB_USER'] ?? '';
$pass = $_ENV['DB_PASSWORD'] ?? '';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

if ($name === '' || $user === '') {
    fwrite(STDERR, "DB_NAME et DB_USER requis (.env).\n");
    exit(1);
}

$dsn = "mysql:host=$host;dbname=$name;charset=$charset";
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    fwrite(STDERR, 'Connexion impossible : ' . $e->getMessage() . "\n");
    exit(1);
}

$tenants = $pdo->query('SELECT id, slug, name, logo_url, settings FROM tenants ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

$out = [
    'generated_at' => date('c'),
    'database' => $name,
    'tenants' => [],
    'site_settings_keys_by_tenant' => [],
];

foreach ($tenants as $row) {
    $keys = [];
    $raw = $row['settings'] ?? null;
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $keys = array_keys($decoded);
            sort($keys);
        } else {
            $keys = ['_invalid_json'];
        }
    }
    $out['tenants'][] = [
        'id' => (int) $row['id'],
        'slug' => $row['slug'],
        'name' => $row['name'],
        'has_logo_url_column' => $row['logo_url'] !== null && $row['logo_url'] !== '',
        'settings_json_top_level_keys' => $keys,
        'settings_json_key_count' => count($keys),
    ];
}

$stmt = $pdo->query(
    'SELECT tenant_id, `key` FROM site_settings ORDER BY tenant_id, `key`'
);
$byTenant = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $tid = (int) $r['tenant_id'];
    if (!isset($byTenant[$tid])) {
        $byTenant[$tid] = [];
    }
    $byTenant[$tid][] = $r['key'];
}
$out['site_settings_keys_by_tenant'] = $byTenant;

$out['notes'] = [
    'logo' => 'La colonne tenants.logo_url coexiste avec la table tenant_branding (migration V2) : résolution côté appli (branding.logo_url ?? tenants.logo_url).',
    'next_step' => 'Migrer les clés les plus fréquentes depuis settings vers tenant_module_entitlements / colonnes typées.',
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
