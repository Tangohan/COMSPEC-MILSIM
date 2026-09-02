<?php

declare(strict_types=1);

/**
 * Connexion MySQL du pipeline de mise à jour (CLI / web).
 *
 * Hostinger / PHP-FPM : `localhost` ouvre un socket Unix souvent interdit
 * (SQLSTATE HY000/2002 « Operation not permitted »). On force le TCP 127.0.0.1
 * et on réutilise le PDO déjà ouvert tant qu’il répond.
 */

function migration_normalize_mysql_host(string $host): string
{
    $host = trim($host);
    if ($host === '' || strcasecmp($host, 'localhost') === 0) {
        return '127.0.0.1';
    }

    return $host;
}

function migration_mysql_dsn(
    ?string $host = null,
    ?string $name = null,
    ?string $charset = null,
    ?int $port = null
): string {
    $host = migration_normalize_mysql_host(
        $host ?? (string) ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1')
    );
    $name = $name ?? (string) ($_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '');
    $charset = $charset ?? (string) ($_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4');
    $port = $port ?? (int) ($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);

    return sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);
}

function migration_pdo_is_alive(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

function migration_is_lost_connection(string $msg): bool
{
    return str_contains($msg, '2006')
        || str_contains($msg, '2013')
        || str_contains($msg, 'gone away')
        || str_contains($msg, 'Lost connection');
}

function migration_is_connect_blocked(string $msg): bool
{
    return str_contains($msg, '2002')
        || str_contains($msg, 'Operation not permitted');
}

/**
 * Recrée $pdo seulement si la session actuelle est morte.
 * Jamais de socket Unix `localhost` : TCP uniquement.
 */
function migration_reconnect_pdo(PDO &$pdo): void
{
    if (migration_pdo_is_alive($pdo)) {
        return;
    }

    $name = (string) ($_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '');
    $user = (string) ($_ENV['DB_USER'] ?? getenv('DB_USER') ?: '');
    $pass = (string) ($_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '');
    if ($name === '' || $user === '') {
        throw new RuntimeException('Connexion MySQL perdue et identifiants indisponibles pour reconnecter.');
    }

    $dsn = migration_mysql_dsn();
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    migration_apply_session_collation($pdo);
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    } catch (Throwable) {
    }
}

function migration_session_init_sql(): string
{
    if (class_exists(\App\Core\Database::class)) {
        return \App\Core\Database::sessionInitSql();
    }

    return "SET character_set_client = 'utf8mb4', character_set_connection = 'utf8mb4',"
        . " character_set_results = 'utf8mb4', collation_connection = 'utf8mb4_unicode_ci',"
        . " time_zone = '+00:00'";
}

function migration_apply_session_collation(PDO $pdo): void
{
    try {
        $pdo->exec(migration_session_init_sql());
    } catch (Throwable) {
    }
}
