<?php

declare(strict_types=1);

/**
 * Portail web sécurisé pour le pipeline de migrations.
 *
 * - Authentification par mot de passe
 * - Tableau de bord (états, dernier run, erreurs)
 * - Console live lors de l’exécution
 *
 * Appelé au début de run-migrations.php (mode non-CLI uniquement).
 * Retourne seulement si l’opérateur a demandé l’exécution (POST run).
 */

require_once dirname(__DIR__) . '/app/Support/SqlText.php';

function migrations_web_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $path = dirname(__DIR__) . '/config/migrations_web.php';
        $cfg = is_file($path) ? (require $path) : [];
        if (!is_array($cfg)) {
            $cfg = [];
        }
    }

    return $cfg;
}

function migrations_web_root(): string
{
    return dirname(__DIR__);
}

function migrations_web_boot_session(): void
{
    $cfg = migrations_web_config();
    $name = (string) ($cfg['session_name'] ?? 'athena_mig_web');
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (!headers_sent()) {
            session_name($name);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }
        @session_start();
    }
}

function migrations_web_load_env(string $root): void
{
    $envFile = $root . '/.env';
    if (!is_file($envFile)) {
        return;
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\"'");
        if ($name === '') {
            continue;
        }
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
        }
        if (getenv($name) === false) {
            putenv($name . '=' . $value);
        }
    }
}

function migrations_web_expected_password(): ?string
{
    $cfg = migrations_web_config();
    $envKey = (string) ($cfg['env_password_key'] ?? 'MIGRATIONS_WEB_PASSWORD');
    $fromEnv = (string) ($_ENV[$envKey] ?? getenv($envKey) ?: '');
    if ($fromEnv !== '') {
        return $fromEnv;
    }

    return null;
}

function migrations_web_password_ok(string $provided): bool
{
    $provided = (string) $provided;
    if ($provided === '') {
        return false;
    }

    $plain = migrations_web_expected_password();
    if ($plain !== null) {
        return hash_equals($plain, $provided);
    }

    $cfg = migrations_web_config();
    $pepper = (string) ($cfg['password_pepper'] ?? '');
    $digest = (string) ($cfg['password_digest'] ?? '');
    if ($digest === '') {
        return false;
    }
    $calc = hash('sha256', $pepper . $provided);

    return hash_equals($digest, $calc);
}

function migrations_web_is_authenticated(): bool
{
    $cfg = migrations_web_config();
    $key = (string) ($cfg['session_auth_key'] ?? 'migrations_web_ok');

    return !empty($_SESSION[$key]);
}

function migrations_web_lock_remaining(): int
{
    $cfg = migrations_web_config();
    $untilKey = (string) ($cfg['session_lock_until_key'] ?? 'migrations_web_lock_until');
    $until = (int) ($_SESSION[$untilKey] ?? 0);
    $left = $until - time();

    return $left > 0 ? $left : 0;
}

function migrations_web_register_failed_attempt(): void
{
    $cfg = migrations_web_config();
    $attemptsKey = (string) ($cfg['session_attempts_key'] ?? 'migrations_web_attempts');
    $untilKey = (string) ($cfg['session_lock_until_key'] ?? 'migrations_web_lock_until');
    $max = (int) ($cfg['max_attempts'] ?? 5);
    $lockSec = (int) ($cfg['lockout_seconds'] ?? 900);

    $n = (int) ($_SESSION[$attemptsKey] ?? 0) + 1;
    $_SESSION[$attemptsKey] = $n;
    if ($n >= $max) {
        $_SESSION[$untilKey] = time() + $lockSec;
        $_SESSION[$attemptsKey] = 0;
    }
}

function migrations_web_clear_attempts(): void
{
    $cfg = migrations_web_config();
    unset(
        $_SESSION[(string) ($cfg['session_attempts_key'] ?? 'migrations_web_attempts')],
        $_SESSION[(string) ($cfg['session_lock_until_key'] ?? 'migrations_web_lock_until')]
    );
}

function migrations_web_set_authenticated(bool $ok): void
{
    $cfg = migrations_web_config();
    $key = (string) ($cfg['session_auth_key'] ?? 'migrations_web_ok');
    if ($ok) {
        $_SESSION[$key] = true;
        migrations_web_clear_attempts();
        if (function_exists('session_regenerate_id')) {
            @session_regenerate_id(true);
        }
    } else {
        unset($_SESSION[$key]);
    }
}

function migrations_web_csrf_token(): string
{
    if (empty($_SESSION['migrations_web_csrf'])) {
        $_SESSION['migrations_web_csrf'] = bin2hex(random_bytes(24));
    }

    return (string) $_SESSION['migrations_web_csrf'];
}

function migrations_web_csrf_ok(?string $token): bool
{
    $expected = (string) ($_SESSION['migrations_web_csrf'] ?? '');
    if ($expected === '' || $token === null || $token === '') {
        return false;
    }

    return hash_equals($expected, $token);
}

/**
 * @return array<string, mixed>
 */
function migrations_web_collect_status(string $root): array
{
    $status = [
        'checked_at' => date('c'),
        'files' => [],
        'env' => [],
        'database' => [
            'connected' => false,
            'name' => null,
            'host' => null,
            'error' => null,
            'tables' => null,
            'tenants' => null,
            'users' => null,
            'has_default_tenant' => null,
            'has_tenant_type' => null,
            'module_tables' => [],
        ],
        'sql_migrations' => [
            'count' => 0,
            'samples' => [],
        ],
        'bootstrap' => [
            'count' => 0,
            'missing' => [],
        ],
        'last_run' => null,
    ];

    $checks = [
        '.env' => $root . '/.env',
        'schema.sql' => $root . '/migrations/schema.sql',
        'c2_pillars.sql' => $root . '/migrations/c2_pillars.sql',
        'modules ATAK (SQL)' => $root . '/migrations/2026_07_24_002_atak_poi_intelligence.sql',
        'config migrations web' => $root . '/config/migrations_web.php',
    ];
    foreach ($checks as $label => $path) {
        $status['files'][$label] = [
            'ok' => is_file($path),
            'path' => $label,
        ];
    }

    $host = (string) ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1');
    $name = (string) ($_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '');
    $user = (string) ($_ENV['DB_USER'] ?? getenv('DB_USER') ?: '');
    $pass = (string) ($_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '');
    $charset = (string) ($_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4');

    $status['env'] = [
        'DB_HOST' => $host !== '' ? $host : '(vide)',
        'DB_NAME' => $name !== '' ? $name : '(vide)',
        'DB_USER' => $user !== '' ? $user : '(vide)',
        'DB_PASSWORD' => $pass !== '' ? 'renseigné' : 'manquant',
        'DB_CHARSET' => $charset,
    ];

    $status['database']['host'] = $host;
    $status['database']['name'] = $name !== '' ? $name : null;

    if ($name !== '' && $user !== '') {
        try {
            $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            if (function_exists('migration_apply_session_collation')) {
                migration_apply_session_collation($pdo);
            } elseif (class_exists(\App\Core\Database::class)) {
                try {
                    $pdo->exec(\App\Core\Database::sessionInitSql());
                } catch (Throwable) {
                }
            }
            $status['database']['connected'] = true;

            $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
            $status['database']['tables'] = $tables;

            try {
                $status['database']['tenants'] = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
                $status['database']['has_default_tenant'] = (bool) $pdo->query('SELECT 1 FROM tenants WHERE ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'default') . ' LIMIT 1')->fetchColumn();
            } catch (Throwable $e) {
                $status['database']['tenants'] = null;
            }
            try {
                $status['database']['users'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            } catch (Throwable $e) {
                $status['database']['users'] = null;
            }
            try {
                $st = $pdo->query(
                    "SELECT 1 FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'tenant_type' LIMIT 1"
                );
                $status['database']['has_tenant_type'] = $st !== false && (bool) $st->fetchColumn();
            } catch (Throwable $e) {
                $status['database']['has_tenant_type'] = null;
            }

            $moduleTables = [
                'atak_poi' => ['label' => 'POI carte', 'group' => 'Modules carte'],
                'atak_tactical_zones' => ['label' => 'Zones tactiques', 'group' => 'Modules carte'],
                'atak_tactical_reports' => ['label' => 'Rapports tactiques', 'group' => 'Modules carte'],
                'atak_medevac_requests' => ['label' => 'MEDEVAC', 'group' => 'Modules carte'],
                'atak_qrf_requests' => ['label' => 'QRF', 'group' => 'Modules carte'],
                'atak_vehicle_tracking' => ['label' => 'Véhicules', 'group' => 'Modules carte'],
                'fire_units' => ['label' => 'Appui-feu', 'group' => 'Piliers C2'],
                'danger_zones' => ['label' => 'Zones de danger', 'group' => 'Piliers C2'],
                'intel_reports' => ['label' => 'Renseignement', 'group' => 'Piliers C2'],
                'logs_positions' => ['label' => 'Replay positions', 'group' => 'Piliers C2'],
                'iff_challenges' => ['label' => 'IFF', 'group' => 'Piliers C2'],
                'asset_logistics_status' => ['label' => 'Logistique assets', 'group' => 'Piliers C2'],
            ];
            $tableCheck = $pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            foreach ($moduleTables as $table => $meta) {
                try {
                    $tableCheck->execute([$table]);
                    $status['database']['module_tables'][$table] = [
                        'label' => (string) $meta['label'],
                        'group' => (string) $meta['group'],
                        'ok' => (bool) $tableCheck->fetchColumn(),
                    ];
                } catch (Throwable $e) {
                    $status['database']['module_tables'][$table] = [
                        'label' => (string) $meta['label'],
                        'group' => (string) $meta['group'],
                        'ok' => false,
                    ];
                }
            }
        } catch (Throwable $e) {
            $status['database']['error'] = $e->getMessage();
        }
    } else {
        $status['database']['error'] = 'Identifiants de base incomplets (nom de base ou utilisateur manquant).';
    }

    $migDir = $root . '/migrations';
    if (is_dir($migDir)) {
        $sqlFiles = glob($migDir . '/*.sql') ?: [];
        sort($sqlFiles);
        $status['sql_migrations']['count'] = count($sqlFiles);
        $status['sql_migrations']['samples'] = array_map('basename', array_slice($sqlFiles, -8));
    }

    $bootstrapExpected = [
        'community_platform_migration.php',
        'platform_unit_commander_migration.php',
        'prod_import_gaps.php',
        'rbac_three_layer_migration.php',
        'user_roles_migration.php',
        'core_schema_extensions_migration.php',
        'tenant_type_migration.php',
        'tenant_atak_maintenance_migration.php',
        'atak_modules_schema_migration.php',
        'c2_pillars_migration.php',
        'discord_recruitment_migration.php',
    ];
    $missing = [];
    foreach ($bootstrapExpected as $bf) {
        if (!is_file($root . '/bootstrap/' . $bf)) {
            $missing[] = $bf;
        }
    }
    $status['bootstrap']['count'] = count($bootstrapExpected) - count($missing);
    $status['bootstrap']['missing'] = $missing;

    $cfg = migrations_web_config();
    $logJson = $root . '/' . ltrim((string) ($cfg['log_relative'] ?? 'storage/logs/migrations-last-run.json'), '/');
    if (is_file($logJson)) {
        $raw = file_get_contents($logJson);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $status['last_run'] = $decoded;
        }
    }

    return $status;
}

/**
 * @param array<string, mixed> $meta
 */
function migrations_web_write_last_run(string $root, array $meta, string $logText): void
{
    $cfg = migrations_web_config();
    $jsonRel = (string) ($cfg['log_relative'] ?? 'storage/logs/migrations-last-run.json');
    $txtRel = (string) ($cfg['log_text_relative'] ?? 'storage/logs/migrations-last-run.txt');
    $jsonPath = $root . '/' . ltrim($jsonRel, '/');
    $txtPath = $root . '/' . ltrim($txtRel, '/');
    $dir = dirname($jsonPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $errors = [];
    $warnings = [];
    foreach (preg_split('/\r\n|\r|\n/', $logText) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (str_contains($line, '[ERREUR') || str_starts_with($line, 'Erreur ') || str_contains($line, 'Connexion impossible')) {
            $errors[] = $line;
        } elseif (str_contains($line, '[ATTENTION]')) {
            $warnings[] = $line;
        }
    }

    $meta['finished_at'] = $meta['finished_at'] ?? date('c');
    $meta['ok'] = ($meta['ok'] ?? null) !== false && $errors === [];
    $meta['error_count'] = count($errors);
    $meta['warning_count'] = count($warnings);
    $meta['errors'] = array_slice($errors, 0, 80);
    $meta['warnings'] = array_slice($warnings, 0, 80);
    $meta['log_bytes'] = strlen($logText);
    $meta['summary_line'] = $errors !== []
        ? 'Échec : ' . count($errors) . ' erreur(s)'
        : ($warnings !== []
            ? 'Terminé avec ' . count($warnings) . ' avertissement(s)'
            : 'Terminé sans erreur');

    @file_put_contents($jsonPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @file_put_contents($txtPath, $logText);
}

function migrations_web_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function migrations_web_layout_start(string $title, string $bodyClass = ''): void
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
    $title = migrations_web_h($title);
    $bodyClass = migrations_web_h($bodyClass);
    echo <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>{$title} · Athena</title>
  <style>
    :root {
      --bg: #0b1220;
      --bg2: #111827;
      --panel: #151f32;
      --panel2: #1a253a;
      --line: rgba(148,163,184,.18);
      --text: #e2e8f0;
      --muted: #94a3b8;
      --ok: #34d399;
      --warn: #fbbf24;
      --err: #fb7185;
      --accent: #10b981;
      --accent2: #059669;
      --mono: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      --sans: "Segoe UI", system-ui, -apple-system, sans-serif;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0; min-height: 100vh; color: var(--text); font-family: var(--sans);
      background:
        radial-gradient(900px 420px at 12% -10%, rgba(16,185,129,.18), transparent 55%),
        radial-gradient(700px 380px at 90% 0%, rgba(56,189,248,.10), transparent 50%),
        linear-gradient(180deg, var(--bg), #070b14 70%);
    }
    a { color: var(--accent); text-decoration: none; }
    a:hover { text-decoration: underline; }
    .wrap { width: min(1120px, calc(100% - 2rem)); margin: 0 auto; padding: 2rem 0 3rem; }
    .top {
      display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; justify-content: space-between;
      margin-bottom: 1.75rem;
    }
    .brand { letter-spacing: .28em; text-transform: uppercase; font-size: .68rem; font-weight: 800; color: var(--accent); }
    h1 { margin: .35rem 0 0; font-size: clamp(1.45rem, 2.4vw, 1.9rem); letter-spacing: -.02em; }
    .sub { color: var(--muted); margin: .45rem 0 0; max-width: 42rem; line-height: 1.5; font-size: .95rem; }
    .panel {
      background: linear-gradient(180deg, var(--panel), var(--panel2));
      border: 1px solid var(--line); border-radius: 1rem; padding: 1.25rem 1.35rem;
      box-shadow: 0 18px 50px -30px rgba(0,0,0,.65);
    }
    .grid { display: grid; gap: 1rem; grid-template-columns: repeat(12, 1fr); }
    .col-4 { grid-column: span 4; } .col-6 { grid-column: span 6; } .col-8 { grid-column: span 8; }
    .col-12 { grid-column: span 12; }
    @media (max-width: 860px) { .col-4, .col-6, .col-8 { grid-column: span 12; } }
    .label { font-size: .68rem; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; color: var(--muted); }
    .metric { font-size: 1.55rem; font-weight: 800; margin-top: .35rem; }
    .muted { color: var(--muted); font-size: .9rem; }
    .badge {
      display: inline-flex; align-items: center; gap: .35rem; border-radius: 999px;
      padding: .28rem .65rem; font-size: .72rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase;
      border: 1px solid transparent;
    }
    .badge-ok { background: rgba(52,211,153,.12); color: var(--ok); border-color: rgba(52,211,153,.28); }
    .badge-warn { background: rgba(251,191,36,.12); color: var(--warn); border-color: rgba(251,191,36,.28); }
    .badge-err { background: rgba(251,113,133,.12); color: var(--err); border-color: rgba(251,113,133,.28); }
    .badge-neutral { background: rgba(148,163,184,.1); color: var(--muted); border-color: var(--line); }
    .row { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; }
    .btn {
      appearance: none; border: 0; cursor: pointer; border-radius: .8rem; padding: .75rem 1.1rem;
      font-weight: 800; font-size: .85rem; letter-spacing: .04em; text-transform: uppercase;
      transition: transform .12s ease, filter .12s ease, background .12s ease;
    }
    .btn:hover { transform: translateY(-1px); filter: brightness(1.05); }
    .btn:disabled { opacity: .55; cursor: not-allowed; transform: none; }
    .btn-primary { background: linear-gradient(180deg, var(--accent), var(--accent2)); color: #04140e; }
    .btn-ghost { background: transparent; color: var(--text); border: 1px solid var(--line); }
    .btn-danger { background: rgba(251,113,133,.15); color: var(--err); border: 1px solid rgba(251,113,133,.35); }
    .field { display: grid; gap: .4rem; margin-bottom: 1rem; }
    .field label { font-size: .8rem; font-weight: 700; color: var(--muted); }
    .field input {
      width: 100%; border-radius: .75rem; border: 1px solid var(--line); background: rgba(2,6,23,.55);
      color: var(--text); padding: .8rem .9rem; font-size: 1rem;
    }
    .field input:focus { outline: 2px solid rgba(16,185,129,.35); border-color: rgba(16,185,129,.55); }
    .flash {
      border-radius: .85rem; padding: .85rem 1rem; margin-bottom: 1rem; border: 1px solid;
      font-size: .92rem; line-height: 1.45;
    }
    .flash-err { background: rgba(251,113,133,.1); border-color: rgba(251,113,133,.35); color: #fecdd3; }
    .flash-ok { background: rgba(52,211,153,.1); border-color: rgba(52,211,153,.35); color: #a7f3d0; }
    .flash-warn { background: rgba(251,191,36,.1); border-color: rgba(251,191,36,.35); color: #fde68a; }
    .list { margin: .75rem 0 0; padding: 0; list-style: none; display: grid; gap: .45rem; }
    .list li {
      font-family: var(--mono); font-size: .78rem; line-height: 1.4; color: #cbd5e1;
      background: rgba(2,6,23,.35); border: 1px solid var(--line); border-radius: .55rem; padding: .55rem .7rem;
      white-space: pre-wrap; word-break: break-word;
    }
    .list li.err { border-color: rgba(251,113,133,.35); color: #fecdd3; }
    .list li.warn { border-color: rgba(251,191,36,.3); color: #fde68a; }
    .kv { display: grid; gap: .4rem; margin-top: .7rem; }
    .kv div { display: flex; justify-content: space-between; gap: 1rem; font-size: .88rem; border-bottom: 1px dashed var(--line); padding: .35rem 0; }
    .kv span:last-child { color: #cbd5e1; font-family: var(--mono); font-size: .8rem; text-align: right; }
    .mod-groups { display: grid; gap: 1rem; margin-top: .9rem; }
    @media (min-width: 860px) { .mod-groups { grid-template-columns: 1fr 1fr; } }
    .mod-group {
      border: 1px solid var(--line); border-radius: .85rem; padding: .85rem .95rem;
      background: rgba(2,6,23,.28);
    }
    .mod-group__title {
      margin: 0 0 .7rem; font-size: .68rem; font-weight: 800; letter-spacing: .16em;
      text-transform: uppercase; color: var(--muted);
    }
    .mod-chips { display: flex; flex-wrap: wrap; gap: .45rem; }
    .mod-chip {
      display: inline-flex; align-items: center; gap: .4rem;
      border-radius: .65rem; padding: .4rem .65rem; font-size: .8rem; font-weight: 650;
      border: 1px solid var(--line); background: rgba(15,23,42,.55); color: #e2e8f0;
    }
    .mod-chip--ok { border-color: rgba(52,211,153,.35); background: rgba(52,211,153,.08); }
    .mod-chip--ko { border-color: rgba(251,191,36,.35); background: rgba(251,191,36,.08); color: #fde68a; }
    .mod-chip__dot {
      width: .55rem; height: .55rem; border-radius: 999px; background: var(--muted);
    }
    .mod-chip--ok .mod-chip__dot { background: var(--ok); }
    .mod-chip--ko .mod-chip__dot { background: var(--warn); }
    .login-wrap { min-height: 100vh; display: grid; place-items: center; padding: 1.5rem; }
    .login-card { width: min(420px, 100%); }
    .console-shell { display: flex; flex-direction: column; min-height: 100vh; }
    .console-bar {
      position: sticky; top: 0; z-index: 5; backdrop-filter: blur(10px);
      background: rgba(7,11,20,.88); border-bottom: 1px solid var(--line);
      padding: .9rem 1.25rem; display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between;
    }
    .console {
      flex: 1; margin: 0; padding: 1.1rem 1.25rem 2rem; overflow: auto;
      font-family: var(--mono); font-size: .82rem; line-height: 1.45; color: #d1fae5;
      background: #05080f; white-space: pre-wrap; word-break: break-word;
    }
    .dot { width: .55rem; height: .55rem; border-radius: 50%; background: var(--accent); box-shadow: 0 0 0 4px rgba(16,185,129,.15); animation: pulse 1.2s infinite; }
    @keyframes pulse { 50% { opacity: .45; } }
    .footer-note { margin-top: 1.25rem; color: var(--muted); font-size: .8rem; }
  </style>
</head>
<body class="{$bodyClass}">
HTML;
}

function migrations_web_layout_end(): void
{
    echo "</body></html>";
}

function migrations_web_render_login(?string $error = null): void
{
    migrations_web_layout_start('Accès migrations', 'login-page');
    $csrf = migrations_web_h(migrations_web_csrf_token());
    $lock = migrations_web_lock_remaining();
    echo '<div class="login-wrap"><div class="login-card panel">';
    echo '<p class="brand">Athena · Ops</p>';
    echo '<h1>Mise à jour de la base</h1>';
    echo '<p class="sub">Zone réservée. Saisissez le mot de passe d’exploitation pour consulter l’état et lancer les migrations.</p>';
    if ($error) {
        echo '<div class="flash flash-err">' . migrations_web_h($error) . '</div>';
    }
    if ($lock > 0) {
        $mins = (int) ceil($lock / 60);
        echo '<div class="flash flash-warn">Trop de tentatives. Réessayez dans environ ' . $mins . ' minute(s).</div>';
        echo '<button class="btn btn-primary" type="button" disabled>Verrouillé</button>';
    } else {
        echo '<form method="post" autocomplete="current-password">';
        echo '<input type="hidden" name="csrf" value="' . $csrf . '">';
        echo '<input type="hidden" name="action" value="login">';
        echo '<div class="field"><label for="password">Mot de passe</label>';
        echo '<input id="password" name="password" type="password" required autofocus placeholder="Mot de passe d’accès"></div>';
        echo '<button class="btn btn-primary" type="submit">Ouvrir le tableau de bord</button>';
        echo '</form>';
    }
    echo '<p class="footer-note">Journal et états visibles uniquement après connexion.</p>';
    echo '</div></div>';
    migrations_web_layout_end();
}

/**
 * @param array<string, mixed> $status
 */
function migrations_web_badge_for_bool(?bool $ok, string $yes = 'OK', string $no = 'KO', string $unk = '—'): string
{
    if ($ok === true) {
        return '<span class="badge badge-ok">' . migrations_web_h($yes) . '</span>';
    }
    if ($ok === false) {
        return '<span class="badge badge-err">' . migrations_web_h($no) . '</span>';
    }

    return '<span class="badge badge-neutral">' . migrations_web_h($unk) . '</span>';
}

/**
 * @param array<string, mixed> $status
 */
function migrations_web_render_dashboard(array $status, ?string $flash = null, string $flashType = 'ok'): void
{
    migrations_web_layout_start('Migrations — tableau de bord');
    $csrf = migrations_web_h(migrations_web_csrf_token());
    $db = $status['database'] ?? [];
    $last = $status['last_run'] ?? null;

    echo '<div class="wrap">';
    echo '<div class="top"><div>';
    echo '<p class="brand">Athena · Ops</p>';
    echo '<h1>Pipeline base de données</h1>';
    echo '<p class="sub">Contrôlez la connexion, le schéma, le dernier passage et les erreurs avant de relancer les migrations.</p>';
    echo '</div><div class="row">';
    echo '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="refresh"><button class="btn btn-ghost" type="submit">Actualiser</button></form>';
    echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Lancer le pipeline complet maintenant ?\');">';
    echo '<input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="run">';
    echo '<button class="btn btn-primary" type="submit">Lancer les migrations</button></form>';
    echo '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="logout"><button class="btn btn-danger" type="submit">Déconnexion</button></form>';
    echo '</div></div>';

    if ($flash) {
        $cls = $flashType === 'err' ? 'flash-err' : ($flashType === 'warn' ? 'flash-warn' : 'flash-ok');
        echo '<div class="flash ' . $cls . '">' . migrations_web_h($flash) . '</div>';
    }

    echo '<div class="grid">';

    // Connexion
    echo '<section class="panel col-4"><p class="label">Connexion</p>';
    echo '<div class="row" style="margin-top:.5rem">' . migrations_web_badge_for_bool(!empty($db['connected']), 'Connectée', 'Échec') . '</div>';
    echo '<div class="kv">';
    echo '<div><span>Hôte</span><span>' . migrations_web_h((string) ($db['host'] ?? '—')) . '</span></div>';
    echo '<div><span>Base</span><span>' . migrations_web_h((string) ($db['name'] ?? '—')) . '</span></div>';
    echo '<div><span>Tables</span><span>' . migrations_web_h($db['tables'] === null ? '—' : (string) $db['tables']) . '</span></div>';
    echo '</div>';
    if (!empty($db['error'])) {
        echo '<div class="flash flash-err" style="margin-top:1rem">' . migrations_web_h((string) $db['error']) . '</div>';
    }
    echo '</section>';

    // Données
    echo '<section class="panel col-4"><p class="label">Données</p>';
    echo '<p class="metric">' . migrations_web_h($db['tenants'] === null ? '—' : (string) $db['tenants']) . ' <span class="muted" style="font-size:.9rem">communautés</span></p>';
    echo '<div class="kv">';
    echo '<div><span>Comptes</span><span>' . migrations_web_h($db['users'] === null ? '—' : (string) $db['users']) . '</span></div>';
    echo '<div><span>Tenant défaut</span><span>' . (!empty($db['has_default_tenant']) ? 'présent' : ($db['has_default_tenant'] === false ? 'absent' : '—')) . '</span></div>';
    $ttLabel = $db['has_tenant_type'] === true ? 'présent' : ($db['has_tenant_type'] === false ? 'manquant — lancez les migrations' : '—');
    echo '<div><span>Profil de communauté</span><span>' . migrations_web_h($ttLabel) . '</span></div>';
    echo '<div><span>Fichiers SQL</span><span>' . (int) ($status['sql_migrations']['count'] ?? 0) . '</span></div>';
    echo '</div></section>';

    // Fichiers / bootstrap
    $filesOk = true;
    foreach (($status['files'] ?? []) as $f) {
        if (empty($f['ok'])) {
            $filesOk = false;
            break;
        }
    }
    $missingBoot = $status['bootstrap']['missing'] ?? [];
    echo '<section class="panel col-4"><p class="label">Prérequis</p>';
    echo '<div class="row" style="margin-top:.5rem">' . migrations_web_badge_for_bool($filesOk, 'Fichiers OK', 'Manquants') . '</div>';
    echo '<div class="kv">';
    foreach (($status['files'] ?? []) as $label => $f) {
        echo '<div><span>' . migrations_web_h((string) $label) . '</span><span>' . (!empty($f['ok']) ? 'OK' : 'manquant') . '</span></div>';
    }
    echo '<div><span>Bootstrap clés</span><span>' . (int) ($status['bootstrap']['count'] ?? 0) . ' / ' . ((int) ($status['bootstrap']['count'] ?? 0) + count($missingBoot)) . '</span></div>';
    echo '</div>';
    if ($missingBoot !== []) {
        echo '<div class="flash flash-warn" style="margin-top:1rem">Manquants : ' . migrations_web_h(implode(', ', $missingBoot)) . '</div>';
    }
    echo '</section>';

    // Modules ATAK / C2
    $moduleTables = $db['module_tables'] ?? [];
    if (is_array($moduleTables) && $moduleTables !== []) {
        $modOk = 0;
        $modTotal = count($moduleTables);
        $byGroup = [];
        foreach ($moduleTables as $table => $mt) {
            if (!empty($mt['ok'])) {
                $modOk++;
            }
            $group = (string) ($mt['group'] ?? 'Autres');
            $byGroup[$group][$table] = $mt;
        }
        $allModOk = $modOk === $modTotal;
        echo '<section class="panel col-12"><p class="label">État des modules métier</p>';
        echo '<div class="row" style="margin-top:.5rem">' . migrations_web_badge_for_bool($allModOk, $modOk . ' / ' . $modTotal . ' prêts', $modOk . ' / ' . $modTotal . ' présents') . '</div>';
        echo '<div class="mod-groups">';
        foreach ($byGroup as $groupName => $items) {
            echo '<div class="mod-group"><p class="mod-group__title">' . migrations_web_h($groupName) . '</p><div class="mod-chips">';
            foreach ($items as $mt) {
                $ok = !empty($mt['ok']);
                $cls = $ok ? 'mod-chip mod-chip--ok' : 'mod-chip mod-chip--ko';
                $title = $ok ? 'Présent' : 'À créer';
                echo '<span class="' . $cls . '" title="' . migrations_web_h($title) . '"><span class="mod-chip__dot" aria-hidden="true"></span>' . migrations_web_h((string) ($mt['label'] ?? '')) . '</span>';
            }
            echo '</div></div>';
        }
        echo '</div>';
        if (!$allModOk) {
            echo '<div class="flash flash-warn" style="margin-top:1rem">Des modules carte manquent encore. Cliquez sur <strong>Lancer les migrations</strong> pour les créer automatiquement, puis actualisez cette page.</div>';
        } else {
            echo '<div class="flash flash-ok" style="margin-top:1rem">Tous les modules listés sont en place.</div>';
        }
        echo '</section>';
    }

    // Dernier run
    echo '<section class="panel col-8"><p class="label">Dernier passage</p>';
    if (!is_array($last)) {
        echo '<p class="muted" style="margin-top:.7rem">Aucun journal enregistré pour l’instant. Lancez une migration pour constituer l’historique.</p>';
    } else {
        $ok = !empty($last['ok']);
        $errs = (int) ($last['error_count'] ?? 0);
        $warns = (int) ($last['warning_count'] ?? 0);
        echo '<div class="row" style="margin-top:.55rem; gap: .5rem;">';
        echo migrations_web_badge_for_bool($ok, 'Succès', 'Échec');
        if ($warns > 0) {
            echo '<span class="badge badge-warn">' . $warns . ' avertissement(s)</span>';
        }
        if ($errs > 0) {
            echo '<span class="badge badge-err">' . $errs . ' erreur(s)</span>';
        }
        echo '</div>';
        echo '<div class="kv">';
        echo '<div><span>Démarré</span><span>' . migrations_web_h((string) ($last['started_at'] ?? '—')) . '</span></div>';
        echo '<div><span>Terminé</span><span>' . migrations_web_h((string) ($last['finished_at'] ?? '—')) . '</span></div>';
        echo '<div><span>Durée</span><span>' . migrations_web_h(isset($last['duration_sec']) ? ((string) $last['duration_sec'] . ' s') : '—') . '</span></div>';
        echo '<div><span>Résumé</span><span>' . migrations_web_h((string) ($last['summary_line'] ?? '—')) . '</span></div>';
        echo '</div>';
    }
    echo '</section>';

    echo '<section class="panel col-4"><p class="label">Environnement</p><div class="kv">';
    foreach (($status['env'] ?? []) as $k => $v) {
        echo '<div><span>' . migrations_web_h((string) $k) . '</span><span>' . migrations_web_h((string) $v) . '</span></div>';
    }
    echo '</div></section>';

    // Erreurs / warnings
    $lastErrors = is_array($last) ? ($last['errors'] ?? []) : [];
    $lastWarnings = is_array($last) ? ($last['warnings'] ?? []) : [];
    echo '<section class="panel col-6"><p class="label">Erreurs (dernier passage)</p>';
    if ($lastErrors === []) {
        echo '<p class="muted" style="margin-top:.7rem">Aucune erreur relevée.</p>';
    } else {
        echo '<ul class="list">';
        foreach ($lastErrors as $line) {
            echo '<li class="err">' . migrations_web_h((string) $line) . '</li>';
        }
        echo '</ul>';
    }
    echo '</section>';

    echo '<section class="panel col-6"><p class="label">Avertissements (dernier passage)</p>';
    if ($lastWarnings === []) {
        echo '<p class="muted" style="margin-top:.7rem">Aucun avertissement relevé.</p>';
    } else {
        echo '<ul class="list">';
        foreach (array_slice($lastWarnings, 0, 40) as $line) {
            echo '<li class="warn">' . migrations_web_h((string) $line) . '</li>';
        }
        echo '</ul>';
        if (count($lastWarnings) > 40) {
            echo '<p class="muted">… et ' . (count($lastWarnings) - 40) . ' de plus dans le journal texte.</p>';
        }
    }
    echo '</section>';

    $samples = $status['sql_migrations']['samples'] ?? [];
    echo '<section class="panel col-12"><p class="label">Derniers fichiers SQL présents</p>';
    if ($samples === []) {
        echo '<p class="muted" style="margin-top:.7rem">Aucun fichier .sql détecté.</p>';
    } else {
        echo '<ul class="list">';
        foreach ($samples as $s) {
            echo '<li>' . migrations_web_h((string) $s) . '</li>';
        }
        echo '</ul>';
    }
    echo '</section>';

    echo '</div>'; // grid
    echo '<p class="footer-note">Le lancement ouvre une console en direct. Le journal est enregistré pour consultation ultérieure.</p>';
    echo '</div>';
    migrations_web_layout_end();
}

function migrations_web_begin_run_console(string $root): void
{
    @set_time_limit(0);
    @ini_set('max_execution_time', '0');
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Accel-Buffering: no');
        header('Cache-Control: no-store');
    }
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }

    $started = date('c');
    $GLOBALS['__migrations_web_run'] = [
        'root' => $root,
        'started_at' => $started,
        'started_ts' => microtime(true),
        'log' => '',
    ];

    migrations_web_layout_start('Migrations en cours', 'console-shell');
    echo '<div class="console-shell">';
    echo '<div class="console-bar"><div class="row"><span class="dot" aria-hidden="true"></span>';
    echo '<strong>Exécution en cours</strong><span class="muted">démarrée ' . migrations_web_h($started) . '</span></div>';
    echo '<div class="row"><a class="btn btn-ghost" href="' . migrations_web_h(basename($_SERVER['SCRIPT_NAME'] ?? 'run-migrations.php')) . '">Retour au tableau de bord</a></div></div>';
    echo '<pre class="console" id="mig-console">';
    echo str_repeat(' ', 2048) . "\n";
    echo "=== Athena · pipeline migrations ===\n";
    echo "Démarrage : {$started}\n\n";
    @flush();

    ob_start(static function (string $chunk): string {
        if (!isset($GLOBALS['__migrations_web_run']) || !is_array($GLOBALS['__migrations_web_run'])) {
            return $chunk;
        }
        $GLOBALS['__migrations_web_run']['log'] .= $chunk;

        return $chunk;
    }, 1);

    register_shutdown_function(static function (): void {
        $run = $GLOBALS['__migrations_web_run'] ?? null;
        if (!is_array($run)) {
            return;
        }
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        $log = (string) ($run['log'] ?? '');
        $ok = !str_contains($log, '[ERREUR') && !str_contains($log, 'Connexion impossible');
        if (http_response_code() >= 500) {
            $ok = false;
        }
        $err = error_get_last();
        if (is_array($err) && in_array((int) ($err['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $ok = false;
            $log .= "\n[ERREUR FATALE] " . ($err['message'] ?? '') . ' — ' . ($err['file'] ?? '') . ':' . (string) ($err['line'] ?? '') . "\n";
            echo "\n[ERREUR FATALE] " . htmlspecialchars((string) ($err['message'] ?? ''), ENT_QUOTES, 'UTF-8') . "\n";
        }

        $duration = round(microtime(true) - (float) ($run['started_ts'] ?? microtime(true)), 2);
        migrations_web_write_last_run((string) $run['root'], [
            'started_at' => $run['started_at'] ?? null,
            'finished_at' => date('c'),
            'duration_sec' => $duration,
            'ok' => $ok,
            'mode' => 'web',
        ], $log);

        echo "\n\n=== Fin d’exécution (" . $duration . " s) ===\n";
        echo $ok ? "Statut : succès (voir journal pour les avertissements éventuels)\n" : "Statut : échec — consultez les lignes d’erreur ci-dessus\n";
        echo '</pre>';
        echo '<div class="console-bar"><div class="row">';
        echo $ok
            ? '<span class="badge badge-ok">Terminé</span>'
            : '<span class="badge badge-err">Terminé avec erreurs</span>';
        echo '<span class="muted">' . htmlspecialchars((string) $duration, ENT_QUOTES, 'UTF-8') . ' s</span></div>';
        $back = htmlspecialchars(basename($_SERVER['SCRIPT_NAME'] ?? 'run-migrations.php'), ENT_QUOTES, 'UTF-8');
        echo '<a class="btn btn-primary" href="' . $back . '">Voir le tableau de bord</a></div>';
        echo '</div>';
        migrations_web_layout_end();
        @flush();
    });
}

/**
 * Point d’entrée : authentifie / affiche UI / prépare la console.
 * Ne retourne que si l’exécution du pipeline doit continuer.
 */
function migrations_web_gate(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    $root = migrations_web_root();
    migrations_web_load_env($root);
    migrations_web_boot_session();

    $action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');

    if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (migrations_web_csrf_ok($_POST['csrf'] ?? null)) {
            migrations_web_set_authenticated(false);
            $_SESSION = [];
        }
        migrations_web_render_login('Session fermée.');
        exit;
    }

    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!migrations_web_csrf_ok($_POST['csrf'] ?? null)) {
            migrations_web_render_login('Session expirée. Réessayez.');
            exit;
        }
        if (migrations_web_lock_remaining() > 0) {
            migrations_web_render_login('Accès temporairement verrouillé.');
            exit;
        }
        $password = (string) ($_POST['password'] ?? '');
        if (migrations_web_password_ok($password)) {
            migrations_web_set_authenticated(true);
            header('Location: ' . basename($_SERVER['SCRIPT_NAME'] ?? 'run-migrations.php'));
            exit;
        }
        migrations_web_register_failed_attempt();
        migrations_web_render_login('Mot de passe incorrect.');
        exit;
    }

    if (!migrations_web_is_authenticated()) {
        migrations_web_render_login();
        exit;
    }

    if ($action === 'run' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!migrations_web_csrf_ok($_POST['csrf'] ?? null)) {
            $status = migrations_web_collect_status($root);
            migrations_web_render_dashboard($status, 'Jeton de sécurité invalide. Actualisez puis réessayez.', 'err');
            exit;
        }
        migrations_web_begin_run_console($root);

        return; // continue run-migrations.php
    }

    // Dashboard (y compris refresh)
    $status = migrations_web_collect_status($root);
    $flash = null;
    $flashType = 'ok';
    if ($action === 'refresh') {
        $flash = 'État actualisé à ' . date('H:i:s');
    }
    migrations_web_render_dashboard($status, $flash, $flashType);
    exit;
}
