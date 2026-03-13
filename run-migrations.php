<?php
declare(strict_types=1);

/**
 * Exécute le schéma SQL et le seed par défaut (sans Phinx, sans Composer).
 * CLI : php run-migrations.php
 * Web : public/run-migrations.php
 */

$root = dirname(__FILE__);

// Charger .env
if (is_file($root . '/.env')) {
    $lines = file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, " \t\"'");
        putenv(trim($name) . '=' . trim($value, " \t\"'"));
    }
}

$host = getenv('DB_HOST') ?: 'localhost';
$name = getenv('DB_NAME') ?: 'athena';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$name;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    echo "Connexion impossible : " . $e->getMessage() . "\n";
    exit(1);
}

$schemaPath = $root . '/migrations/schema.sql';
if (!is_file($schemaPath)) {
    echo "Fichier schema.sql introuvable.\n";
    exit(1);
}

echo "Exécution du schéma...\n";
$sql = file_get_contents($schemaPath);
$pdo->exec($sql);
echo "Schéma OK.\n";

// Seed : tenant + role + grade + admin
$stmt = $pdo->query("SELECT id FROM tenants WHERE slug = 'default' LIMIT 1");
if ($stmt && $stmt->fetch()) {
    echo "Seed déjà appliqué (tenant default existe).\n";
    return;
}

echo "Insertion du tenant et admin par défaut...\n";
$pdo->exec("INSERT INTO tenants (name, slug, created_at, updated_at) VALUES ('Default Organisation', 'default', NOW(), NOW())");
$tenantId = (int) $pdo->lastInsertId();

$pdo->exec("INSERT INTO roles (tenant_id, name, slug, description, is_system, created_at) VALUES ($tenantId, 'Administrator', 'tenant_admin', 'Full access', 1, NOW())");
$roleId = (int) $pdo->lastInsertId();

$pdo->exec("INSERT INTO grades (tenant_id, name, short_name, rank_order, created_at) VALUES ($tenantId, 'Officer', 'OFR', 10, NOW())");
$gradeId = (int) $pdo->lastInsertId();

$hash = password_hash('admin', PASSWORD_ARGON2ID);
$pdo->prepare("INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, role_id, grade_id, status, created_at, updated_at) VALUES (?, 'admin@athena.local', ?, 'Admin', 'ADMIN', ?, ?, 'active', NOW(), NOW())")
    ->execute([$tenantId, $hash, $roleId, $gradeId]);

echo "Seed OK. Compte : admin@athena.local / admin\n";

return;
