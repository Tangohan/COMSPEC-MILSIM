<?php

declare(strict_types=1);

/**
 * Interface web du pipeline de migrations (/run-migrations.php), au thème back-office.
 *
 * - Accès par mot de passe (config/migrations_web.php ou MIGRATIONS_WEB_PASSWORD), verrouillage après
 *   plusieurs échecs (par session et par adresse IP), session courte, jeton CSRF sur chaque action.
 * - Tableau de bord : nouvelles étapes du registre, historique en lecture et état de la base.
 * - Lancement des nouvelles étapes ou d'une entrée active seule, console en direct, journal enregistré.
 *
 * Appelé au début de run-migrations.php (hors CLI). Ne rend la main que si une exécution est demandée.
 */

require_once dirname(__DIR__) . '/app/Support/SqlText.php';
require_once dirname(__DIR__) . '/app/Support/Migrations/MigrationRegistry.php';
require_once dirname(__DIR__) . '/app/Support/Migrations/MigrationRunLog.php';
require_once dirname(__DIR__) . '/app/Support/Migrations/MigrationStepTracker.php';

use App\Support\Migrations\MigrationRegistry;
use App\Support\Migrations\MigrationRunLog;
use App\Support\Migrations\MigrationStepTracker;

/** Durée d'inactivité au-delà de laquelle il faut se reconnecter (secondes). */
const MIGRATIONS_WEB_IDLE_SECONDS = 1800;

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

    return $fromEnv !== '' ? $fromEnv : null;
}

function migrations_web_password_ok(string $provided): bool
{
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

    return hash_equals($digest, hash('sha256', $pepper . $provided));
}

function migrations_web_is_authenticated(): bool
{
    $cfg = migrations_web_config();
    $key = (string) ($cfg['session_auth_key'] ?? 'migrations_web_ok');
    if (empty($_SESSION[$key])) {
        return false;
    }
    $last = (int) ($_SESSION['migrations_web_seen'] ?? 0);
    if ($last > 0 && time() - $last > MIGRATIONS_WEB_IDLE_SECONDS) {
        unset($_SESSION[$key]);

        return false;
    }
    $_SESSION['migrations_web_seen'] = time();

    return true;
}

// ------------------------------------------------------------------ verrouillage (session + adresse IP)

function migrations_web_client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'inconnue'), 0, 64);
}

function migrations_web_ip_lock_path(): string
{
    return migrations_web_root() . '/storage/cache/migrations-web-attempts.json';
}

/** @return array<string, array{n: int, until: int, at: int}> */
function migrations_web_ip_lock_read(): array
{
    $raw = @file_get_contents(migrations_web_ip_lock_path());
    $data = is_string($raw) ? json_decode($raw, true) : null;

    return is_array($data) ? $data : [];
}

function migrations_web_ip_lock_write(array $data): void
{
    $path = migrations_web_ip_lock_path();
    if (!is_dir(dirname($path))) {
        @mkdir(dirname($path), 0775, true);
    }
    $now = time();
    $data = array_filter($data, static fn ($r) => is_array($r) && ((int) ($r['until'] ?? 0) > $now || $now - (int) ($r['at'] ?? 0) < 86400));
    @file_put_contents($path, json_encode($data), LOCK_EX);
}

function migrations_web_lock_remaining(): int
{
    $cfg = migrations_web_config();
    $untilKey = (string) ($cfg['session_lock_until_key'] ?? 'migrations_web_lock_until');
    $session = (int) ($_SESSION[$untilKey] ?? 0) - time();
    $ip = (int) (migrations_web_ip_lock_read()[migrations_web_client_ip()]['until'] ?? 0) - time();

    return max(0, $session, $ip);
}

function migrations_web_register_failed_attempt(): void
{
    $cfg = migrations_web_config();
    $attemptsKey = (string) ($cfg['session_attempts_key'] ?? 'migrations_web_attempts');
    $untilKey = (string) ($cfg['session_lock_until_key'] ?? 'migrations_web_lock_until');
    $max = max(1, (int) ($cfg['max_attempts'] ?? 5));
    $lockSec = (int) ($cfg['lockout_seconds'] ?? 900);

    $n = (int) ($_SESSION[$attemptsKey] ?? 0) + 1;
    $_SESSION[$attemptsKey] = $n;
    if ($n >= $max) {
        $_SESSION[$untilKey] = time() + $lockSec;
        $_SESSION[$attemptsKey] = 0;
    }
    // Même compteur par adresse IP : effacer le cookie ne remet pas les essais à zéro.
    $all = migrations_web_ip_lock_read();
    $ip = migrations_web_client_ip();
    $row = $all[$ip] ?? ['n' => 0, 'until' => 0, 'at' => 0];
    $row['n'] = (int) $row['n'] + 1;
    $row['at'] = time();
    if ($row['n'] >= $max) {
        $row['until'] = time() + $lockSec;
        $row['n'] = 0;
    }
    $all[$ip] = $row;
    migrations_web_ip_lock_write($all);
}

function migrations_web_clear_attempts(): void
{
    $cfg = migrations_web_config();
    unset(
        $_SESSION[(string) ($cfg['session_attempts_key'] ?? 'migrations_web_attempts')],
        $_SESSION[(string) ($cfg['session_lock_until_key'] ?? 'migrations_web_lock_until')]
    );
    $all = migrations_web_ip_lock_read();
    unset($all[migrations_web_client_ip()]);
    migrations_web_ip_lock_write($all);
}

function migrations_web_set_authenticated(bool $ok): void
{
    $cfg = migrations_web_config();
    $key = (string) ($cfg['session_auth_key'] ?? 'migrations_web_ok');
    if ($ok) {
        if (function_exists('session_regenerate_id')) {
            @session_regenerate_id(true);
        }
        $_SESSION[$key] = true;
        $_SESSION['migrations_web_seen'] = time();
        migrations_web_clear_attempts();
    } else {
        unset($_SESSION[$key], $_SESSION['migrations_web_seen']);
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

// ------------------------------------------------------------------ état

/** Connexion pour le tableau de bord (null si impossible ; l'erreur est rangée dans $error). */
function migrations_web_pdo(?string &$error = null): ?PDO
{
    $host = (string) ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1');
    $name = (string) ($_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '');
    $user = (string) ($_ENV['DB_USER'] ?? getenv('DB_USER') ?: '');
    $pass = (string) ($_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '');
    $charset = (string) ($_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4');
    if ($name === '' || $user === '') {
        $error = 'Identifiants de base incomplets (nom de base ou utilisateur manquant).';

        return null;
    }
    try {
        $port = (string) ($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '');
        $dsn = "mysql:host={$host}" . ($port !== '' ? ";port={$port}" : '') . ";dbname={$name};charset={$charset}";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        if (function_exists('migration_apply_session_collation')) {
            migration_apply_session_collation($pdo);
        }

        return $pdo;
    } catch (Throwable $e) {
        $error = $e->getMessage();

        return null;
    }
}

/**
 * @return array<string, mixed>
 */
function migrations_web_collect_status(string $root): array
{
    $status = [
        'checked_at' => date('c'),
        'env' => [],
        'database' => [
            'connected' => false, 'name' => null, 'host' => null, 'error' => null, 'tables' => null, 'tenants' => null,
            'users' => null, 'demo_accounts' => null, 'has_default_tenant' => null, 'has_tenant_type' => null, 'module_tables' => [],
        ],
        // Historical SQL files are archived source, never the active migration queue.
        'sql' => ['total'=>0, 'applied'=>0, 'excluded'=>0, 'pending'=>[], 'failed'=>[]],
        'steps' => MigrationRegistry::load($root),
        'results' => [],
        'last_db_run' => null,
        'last_run' => null,
        'files' => [
            '.env' => is_file($root . '/.env'),
            'migrations/schema.sql' => is_file($root . '/migrations/schema.sql'),
            'database/steps/registry.php' => is_file($root . '/database/steps/registry.php'),
            'config/migrations_web.php' => is_file($root . '/config/migrations_web.php'),
        ],
    ];
    $host = (string) ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1');
    $name = (string) ($_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '');
    $status['env'] = [
        'DB_HOST' => $host !== '' ? $host : '(vide)',
        'DB_NAME' => $name !== '' ? $name : '(vide)',
        'DB_USER' => (string) ($_ENV['DB_USER'] ?? getenv('DB_USER') ?: '') !== '' ? 'renseigné' : 'manquant',
        'DB_PASSWORD' => (string) ($_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '') !== '' ? 'renseigné' : 'manquant',
    ];
    $status['database']['host'] = $host;
    $status['database']['name'] = $name !== '' ? $name : null;

    $error = null;
    $pdo = migrations_web_pdo($error);
    if ($pdo === null) {
        $status['database']['error'] = $error;
    } else {
        $status['database']['connected'] = true;
        $q = static function (string $sql) use ($pdo) {
            try {
                $st = $pdo->query($sql);

                return $st !== false ? $st->fetchColumn() : null;
            } catch (Throwable) {
                return null;
            }
        };
        $status['database']['tables'] = ($v = $q('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')) !== null ? (int) $v : null;
        $status['database']['tenants'] = ($v = $q('SELECT COUNT(*) FROM tenants')) !== null ? (int) $v : null;
        $status['database']['users'] = ($v = $q('SELECT COUNT(*) FROM users')) !== null ? (int) $v : null;
        $status['database']['demo_accounts'] = ($v = $q("SELECT COUNT(*) FROM users WHERE LOWER(email) LIKE '%@demo.local'")) !== null ? (int) $v : null;
        try {
            $status['database']['has_default_tenant'] = (bool) $q('SELECT 1 FROM tenants WHERE ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'default') . ' LIMIT 1');
        } catch (Throwable) {
        }
        $status['database']['has_tenant_type'] = (bool) $q("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'tenant_type' LIMIT 1");
        $modules = [
            'atak_poi' => 'POI carte', 'atak_tactical_zones' => 'Zones tactiques', 'atak_tactical_reports' => 'Rapports tactiques',
            'atak_medevac_requests' => 'MEDEVAC', 'atak_qrf_requests' => 'QRF', 'atak_vehicle_tracking' => 'Véhicules',
            'fire_units' => 'Appui-feu', 'danger_zones' => 'Zones de danger', 'intel_reports' => 'Renseignement',
            'logs_positions' => 'Replay positions', 'iff_challenges' => 'IFF', 'asset_logistics_status' => 'Logistique',
        ];
        try {
            $chk = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
            foreach ($modules as $table => $label) {
                $chk->execute([$table]);
                $status['database']['module_tables'][$table] = ['label' => $label, 'ok' => (bool) $chk->fetchColumn()];
            }
        } catch (Throwable) {
        }
        $status['results'] = MigrationRunLog::latestByStep($pdo);
        $status['last_db_run'] = MigrationRunLog::lastRun($pdo);
    }

    $cfg = migrations_web_config();
    $logJson = $root . '/' . ltrim((string) ($cfg['log_relative'] ?? 'storage/logs/migrations-last-run.json'), '/');
    if (is_file($logJson)) {
        $decoded = json_decode((string) file_get_contents($logJson), true);
        if (is_array($decoded)) {
            $status['last_run'] = $decoded;
        }
    }

    return $status;
}

/** Validate launch intent against the future queue, without opening the database. */
function migrations_web_queue_error(string $root, string $action, string $stepId = ''): ?string
{
    if ($action === 'replay') {
        return 'Le rejeu des migrations historiques est désactivé. Seules les nouvelles étapes inscrites au registre peuvent être exécutées.';
    }
    if ($action === 'run_step') {
        $step = MigrationRegistry::find($root, $stepId);
        return $step !== null && $step['rerun'] ? null : 'Cette étape ne fait pas partie des nouvelles migrations exécutables seules.';
    }
    if ($action !== 'run') {
        return 'Action inconnue.';
    }

    return MigrationRegistry::load($root) === [] ? 'Aucune migration en attente. Les migrations historiques restent archivées ; aucune donnée ne sera modifiée.' : null;
}

/** Past results remain readable even after their steps leave the active queue. */
function migrations_web_historical_steps(array $steps, array $results): array
{
    $active = array_fill_keys(array_column($steps, 'id'), true);
    $history = [];
    foreach ($results as $id => $result) {
        if (!isset($active[$id])) {
            $history[] = ['id'=>(string)$id, 'label'=>(string)$id, 'index'=>count($history)+1, 'rerun'=>false];
        }
    }

    return $history;
}

/**
 * @param array<string, mixed> $meta
 */
function migrations_web_write_last_run(string $root, array $meta, string $logText): void
{
    $cfg = migrations_web_config();
    $jsonPath = $root . '/' . ltrim((string) ($cfg['log_relative'] ?? 'storage/logs/migrations-last-run.json'), '/');
    $txtPath = $root . '/' . ltrim((string) ($cfg['log_text_relative'] ?? 'storage/logs/migrations-last-run.txt'), '/');
    if (!is_dir(dirname($jsonPath))) {
        @mkdir(dirname($jsonPath), 0775, true);
    }
    $logText = MigrationStepTracker::redact($logText);
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
    $meta['errors'] = array_slice(array_values(array_unique($errors)), 0, 80);
    $meta['warnings'] = array_slice(array_values(array_unique($warnings)), 0, 80);
    $meta['log_bytes'] = strlen($logText);
    $meta['summary_line'] = $errors !== []
        ? 'Échec : ' . count($errors) . ' erreur(s)'
        : ($warnings !== [] ? 'Terminé avec ' . count($warnings) . ' avertissement(s)' : 'Terminé sans erreur');

    @file_put_contents($jsonPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @file_put_contents($txtPath, $logText);
}

function migrations_web_last_log_text(string $root): string
{
    $cfg = migrations_web_config();
    $txtPath = $root . '/' . ltrim((string) ($cfg['log_text_relative'] ?? 'storage/logs/migrations-last-run.txt'), '/');
    $raw = is_file($txtPath) ? (string) file_get_contents($txtPath) : '';

    return MigrationStepTracker::redact($raw);
}

// ------------------------------------------------------------------ rendu

function migrations_web_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function migrations_web_self(): string
{
    return migrations_web_h(basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'run-migrations.php')));
}

function migrations_web_send_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store, no-cache, must-revalidate');
}

function migrations_web_ago(?string $utc): string
{
    if ($utc === null || $utc === '') {
        return '—';
    }
    $t = strtotime($utc . (preg_match('/[zZ]|[+-]\d\d:?\d\d$/', $utc) ? '' : ' UTC'));
    if (!$t) {
        return $utc;
    }
    $d = time() - $t;
    if ($d < 60) {
        return 'à l’instant';
    }
    if ($d < 3600) {
        return 'il y a ' . (int) floor($d / 60) . ' min';
    }
    if ($d < 86400) {
        return 'il y a ' . (int) floor($d / 3600) . ' h';
    }

    return 'le ' . date('d/m/Y H:i', $t);
}

function migrations_web_duration(int $ms): string
{
    if ($ms < 1000) {
        return $ms . ' ms';
    }
    $s = $ms / 1000;

    return $s < 60 ? number_format($s, 1, ',', ' ') . ' s' : (int) floor($s / 60) . ' min ' . (int) round(fmod($s, 60)) . ' s';
}

function migrations_web_icon(string $name): string
{
    $paths = [
        'play' => '<path d="M7 5v14l12-7z"/>',
        'refresh' => '<path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/>',
        'log' => '<path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'out' => '<path d="M14 4h6v6"/><path d="M20 4l-9 9"/><path d="M18 14v6H4V6h6"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'db' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'moon' => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>',
        'back' => '<path d="M15 5l-7 7 7 7"/>',
    ];

    return '<svg class="mg-ico" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

function migrations_web_layout_start(string $title, bool $authed = true): void
{
    migrations_web_send_headers();
    $t = migrations_web_h($title);
    $self = migrations_web_self();
    $csrf = $authed ? migrations_web_h(migrations_web_csrf_token()) : '';
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex,nofollow"><title>' . $t . ' — Athena</title>';
    echo "<script>(function(){var p='auto';try{p=localStorage.getItem('athena.bo.theme')||'auto'}catch(e){}var d=p==='dark'||(p==='auto'&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.setAttribute('data-bo-theme',d?'dark':'light')})();</script>";
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">';
    foreach (['back-office-shell.css', 'back-office-dark.generated.css', 'back-office-dark.css', 'migrations-console.css'] as $css) {
        echo '<link rel="stylesheet" href="assets/css/' . $css . '">';
    }
    echo '</head><body class="mg-body"><div class="ath-shell mg-shell">';
    echo '<header class="mg-top"><a class="mg-brand" href="' . $self . '"><span class="mg-brand__mark" aria-hidden="true">' . migrations_web_icon('db') . '</span><span class="mg-brand__name">ATHENA</span><span class="mg-brand__sep">/</span><span class="mg-brand__app">Migrations</span></a>';
    echo '<div class="mg-top__actions">';
    echo '<button type="button" class="mg-iconbtn" data-ath-theme-toggle aria-pressed="false" title="Mode nuit" aria-label="Mode nuit">' . migrations_web_icon('moon') . '</button>';
    if ($authed) {
        echo '<form method="post" action="' . $self . '"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="logout"><button class="ath-btn mg-top__logout" type="submit">' . migrations_web_icon('lock') . '<span>Se déconnecter</span></button></form>';
    }
    echo '</div></header><main class="mg-main">';
}

function migrations_web_layout_end(): void
{
    echo '</main></div><script src="assets/js/back-office-theme.js" defer></script><script src="assets/js/migrations-console.js" defer></script></body></html>';
}

function migrations_web_render_login(?string $message = null, string $tone = 'bad'): void
{
    migrations_web_layout_start('Connexion', false);
    $locked = migrations_web_lock_remaining();
    $csrf = migrations_web_h(migrations_web_csrf_token());
    echo '<section class="mg-login ath-panel">';
    echo '<p class="ath-page-head__kicker">ADMINISTRATION DU SITE · BASE DE DONNÉES</p>';
    echo '<h1 class="mg-login__title">Migrations de la base</h1>';
    echo '<p class="mg-login__sub">Les migrations se pilotent désormais depuis la console d’exploitation, avec votre compte d’administrateur du site.</p>';
    echo '<a class="ath-btn ath-btn--solid mg-login__btn" href="/admin/system/exploitation?onglet=migrations">' . migrations_web_icon('db') . '<span>Ouvrir la console d’exploitation</span></a>';
    echo '<p class="mg-login__sub" style="margin-top:1.25rem">Accès de secours, quand le site lui-même ne démarre plus : mot de passe du service de migrations.</p>';
    if ($message !== null) {
        echo '<div class="mg-flash mg-flash--' . migrations_web_h($tone) . '" role="alert">' . migrations_web_h($message) . '</div>';
    }
    if ($locked > 0) {
        echo '<div class="mg-flash mg-flash--warn">Trop d’essais : nouvel essai possible dans ' . (int) ceil($locked / 60) . ' min.</div>';
    } else {
        echo '<form method="post" action="' . migrations_web_self() . '" class="mg-login__form" autocomplete="off">';
        echo '<input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="login">';
        echo '<label class="ath-field"><span class="ath-field__label">Mot de passe</span><input class="ath-field__input" id="password" name="password" type="password" required autofocus autocomplete="current-password"></label>';
        echo '<button class="ath-btn ath-btn--solid mg-login__btn" type="submit">' . migrations_web_icon('lock') . '<span>Se connecter</span></button>';
        echo '</form>';
    }
    echo '<p class="mg-login__foot">Verrouillage après plusieurs essais. Session fermée après 30 minutes sans activité.</p>';
    echo '</section>';
    migrations_web_layout_end();
}

/** @return array{0: string, 1: string} classe de pastille et libellé d'un statut d'étape */
function migrations_web_status_tag(?string $status): array
{
    return match ($status) {
        'ok' => ['ok', 'OK'],
        'warn' => ['warn', 'Avertissement'],
        'error' => ['bad', 'Erreur'],
        default => ['neut', 'Jamais lancée'],
    };
}

function migrations_web_render_dashboard(array $status, ?string $flash = null, string $flashType = 'ok'): void
{
    migrations_web_layout_start('Migrations de la base');
    $self = migrations_web_self();
    $csrf = migrations_web_h(migrations_web_csrf_token());
    $db = $status['database'];
    $steps = $status['steps'];
    $results = $status['results'];
    $lastRun = $status['last_db_run'];
    $lastFile = is_array($status['last_run']) ? $status['last_run'] : null;

    $counts = ['ok' => 0, 'warn' => 0, 'error' => 0, 'never' => 0];
    foreach ($steps as $s) {
        $st = $results[$s['id']]['status'] ?? null;
        $counts[$st !== null && isset($counts[$st]) ? $st : 'never']++;
    }
    $groups = MigrationRegistry::byGroup($steps);
    $issues = $counts['warn'] + $counts['error'] + $counts['never'];

    echo '<section class="ath-page-head mg-head" aria-labelledby="mg-title"><div class="ath-page-head__copy">';
    echo '<p class="ath-page-head__kicker">ADMINISTRATION DU SITE · BASE DE DONNÉES</p>';
    echo '<h1 class="ath-page-head__title" id="mg-title">Migrations de la base</h1>';
    echo '<p class="ath-page-head__sub">Seules les nouvelles migrations inscrites au registre sont exécutables. Les migrations déjà déployées sont archivées ; leurs résultats et journaux restent consultables.</p>';
    echo '</div><div class="ath-page-head__actions">';
    echo '<form method="post" action="' . $self . '"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="refresh"><button class="ath-btn" type="submit">' . migrations_web_icon('refresh') . '<span>Actualiser</span></button></form>';
    echo '<a class="ath-btn" href="#mg-log">' . migrations_web_icon('log') . '<span>Dernier journal</span></a>';
    echo '<form method="post" action="' . $self . '" data-mg-confirm="Exécuter les nouvelles migrations maintenant ? Le passage complet prend quelques minutes."><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="run">';
    echo '<button class="ath-btn ath-btn--solid" type="submit"' . (!$db['connected'] || $steps === [] ? ' disabled' : '') . '>' . migrations_web_icon('play') . '<span>Exécuter les nouvelles migrations</span></button></form>';
    echo '</div></section>';

    if ($flash !== null) {
        echo '<div class="mg-flash mg-flash--' . migrations_web_h($flashType === 'err' ? 'bad' : $flashType) . '" role="status">' . migrations_web_h($flash) . '</div>';
    }

    // Alertes
    $alerts = [];
    if (!$db['connected']) {
        $alerts[] = ['bad', 'Connexion à la base impossible', (string) ($db['error'] ?? '') . ' — vérifiez DB_HOST, DB_NAME, DB_USER et DB_PASSWORD dans .env.'];
    }
    if (($db['demo_accounts'] ?? 0) > 0) {
        $alerts[] = ['warn', 'Comptes de démonstration présents : ' . (int) $db['demo_accounts'], 'Diagnostic uniquement : aucune purge automatique n’est programmée.'];
    }
    if ($db['connected'] && $db['has_tenant_type'] === false) {
        $alerts[] = ['bad', 'Profil de communauté absent (tenants.tenant_type)', 'Diagnostic de l’installation : aucune migration historique ne sera relancée automatiquement.'];
    }
    foreach ($alerts as [$tone, $title, $text]) {
        echo '<div class="mg-alert mg-alert--' . $tone . '"><b>' . migrations_web_h($title) . '</b><span>' . migrations_web_h($text) . '</span></div>';
    }

    // Indicateurs
    $lastLabel = '—';
    $lastNote = 'aucun passage enregistré';
    if (is_array($lastRun)) {
        $lastLabel = migrations_web_ago($lastRun['finished_at']);
        $lastNote = ($lastRun['steps'] === 1 ? 'étape seule' : $lastRun['steps'] . ' étapes') . ' · ' . migrations_web_duration($lastRun['duration_ms']) . ' · ' . ($lastRun['mode'] === 'web' ? 'web' : 'ligne de commande');
    } elseif ($lastFile !== null) {
        $lastLabel = migrations_web_ago((string) ($lastFile['finished_at'] ?? ''));
        $lastNote = (string) ($lastFile['summary_line'] ?? '');
    }
    echo '<div class="ath-kpi-grid mg-kpis">';
    echo '<article class="ath-card ath-kpi"><p class="ath-kpi__label">Étapes</p><p class="ath-kpi__value">' . count($steps) . '</p><p class="ath-kpi__note">' . count($groups['schema']) . ' schéma · ' . count($groups['migrations']) . ' migrations · ' . count($groups['referentiels']) . ' référentiels · ' . count($groups['maintenance']) . ' maintenance</p></article>';
    echo '<article class="ath-card ath-kpi mg-kpi--ok"><p class="ath-kpi__label">OK</p><p class="ath-kpi__value">' . $counts['ok'] . '</p><p class="ath-kpi__note">au dernier passage de chaque étape</p></article>';
    echo '<article class="ath-card ath-kpi' . ($counts['warn'] > 0 ? ' mg-kpi--warn' : '') . '"><p class="ath-kpi__label">Avertissements</p><p class="ath-kpi__value">' . $counts['warn'] . '</p><p class="ath-kpi__note">fichier absent, élément déjà présent…</p></article>';
    echo '<article class="ath-card ath-kpi' . ($counts['error'] > 0 ? ' mg-kpi--bad' : '') . '"><p class="ath-kpi__label">Erreurs</p><p class="ath-kpi__value">' . $counts['error'] . '</p><p class="ath-kpi__note">' . ($counts['never'] > 0 ? $counts['never'] . ' jamais lancée(s)' : 'à corriger puis relancer') . '</p></article>';
    echo '<article class="ath-card ath-kpi"><p class="ath-kpi__label">Dernier passage</p><p class="ath-kpi__value mg-kpi__text">' . migrations_web_h($lastLabel) . '</p><p class="ath-kpi__note">' . migrations_web_h($lastNote) . '</p></article>';
    echo '<article class="ath-card ath-kpi"><p class="ath-kpi__label">File active</p><p class="ath-kpi__value">' . count($steps) . '</p><p class="ath-kpi__note">nouvelles étapes uniquement</p></article>';
    echo '</div>';

    if ($steps === []) {
        echo '<div class="mg-flash mg-flash--ok" role="status">Aucune migration en attente. La file est vide ; aucune donnée ne sera modifiée.</div>';
    }

    // Étapes
    echo '<section class="ath-table-panel mg-steps" id="mg-steps">';
    echo '<div class="ath-table-toolbar mg-toolbar"><span class="ath-table-toolbar__title">Étapes du pipeline</span><span class="ath-table-toolbar__count" data-mg-count>' . count($steps) . '</span>';
    echo '<input class="ath-table-toolbar__search" type="search" placeholder="Rechercher une étape, une table…" data-mg-search aria-label="Rechercher une étape">';
    echo '<select class="ath-table-toolbar__filter" data-mg-group aria-label="Groupe"><option value="">Tous les groupes</option>';
    foreach (MigrationRegistry::GROUPS as $g => $gl) {
        echo '<option value="' . $g . '">' . migrations_web_h($gl) . '</option>';
    }
    echo '</select><select class="ath-table-toolbar__filter" data-mg-status aria-label="Statut">' . '<option value="issues"' . ($issues > 0 ? ' selected' : '') . '>À traiter (' . $issues . ')</option><option value=""' . ($issues > 0 ? '' : ' selected') . '>Tous les statuts</option><option value="error">Erreurs</option><option value="warn">Avertissements</option><option value="ok">OK</option><option value="never">Jamais lancées</option></select>';
    echo '</div>';
    echo '<div class="ath-table-wrap"><table class="ath-table mg-table"><thead><tr><th class="mg-col-n">N°</th><th>Étape</th><th>Statut</th><th class="mg-col-num">Durée</th><th>Dernier message</th><th>Passage</th><th class="mg-col-act"><span class="mg-sr">Actions</span></th></tr></thead>';
    foreach ($groups as $g => $list) {
        if ($list === []) {
            continue;
        }
        echo '<tbody data-mg-tbody="' . $g . '"><tr class="mg-grouprow"><th colspan="7"><span class="mg-grouprow__name">' . migrations_web_h(MigrationRegistry::GROUPS[$g]) . '</span><span class="mg-grouprow__n">' . count($list) . '</span><span class="mg-grouprow__hint">' . migrations_web_h(MigrationRegistry::GROUP_HINTS[$g]) . '</span></th></tr>';
        foreach ($list as $s) {
            $r = $results[$s['id']] ?? null;
            [$tone, $tl] = migrations_web_status_tag($r['status'] ?? null);
            $search = mb_strtolower($s['id'] . ' ' . $s['label'] . ' ' . ($r['message'] ?? ''));
            echo '<tr class="mg-row" data-mg-row data-group="' . $g . '" data-status="' . migrations_web_h($r['status'] ?? 'never') . '" data-search="' . migrations_web_h($search) . '">';
            echo '<td class="mg-col-n">' . (int) $s['index'] . '</td>';
            echo '<td class="mg-step"><b>' . migrations_web_h($s['label']) . '</b><code>' . migrations_web_h($s['id']) . '</code></td>';
            echo '<td data-label="Statut"><span class="ath-tag ath-tag--' . $tone . '">' . $tl . '</span></td>';
            echo '<td class="mg-col-num" data-label="Durée">' . ($r !== null ? migrations_web_h(migrations_web_duration($r['duration_ms'])) : '—') . '</td>';
            echo '<td class="mg-msg" data-label="Message">' . ($r !== null ? migrations_web_h($r['message']) : '<span class="mg-muted">—</span>') . '</td>';
            echo '<td class="mg-when" data-label="Passage">' . ($r !== null ? migrations_web_h(migrations_web_ago($r['ran_at'])) : '—') . '</td>';
            echo '<td class="mg-col-act">';
            if ($r !== null) {
                echo '<a class="ath-btn mg-mini" href="' . $self . '?action=output&amp;step=' . rawurlencode($s['id']) . '" title="Sortie de la dernière exécution">' . migrations_web_icon('out') . '<span class="mg-sr">Sortie</span></a>';
            }
            if ($s['rerun'] && $db['connected']) {
                echo '<form method="post" action="' . $self . '" data-mg-confirm="Relancer seulement l’étape « ' . migrations_web_h($s['label']) . ' » ?"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="run_step"><input type="hidden" name="step" value="' . migrations_web_h($s['id']) . '"><button class="ath-btn mg-mini" type="submit">' . migrations_web_icon('play') . '<span>Relancer</span></button></form>';
            } elseif (!$s['rerun']) {
                echo '<span class="mg-muted mg-mini-note" title="Dépend des étapes précédentes : passe avec le pipeline complet">avec le pipeline</span>';
            }
            echo '</td></tr>';
        }
        echo '</tbody>';
    }
    echo '</table></div><p class="ath-table-empty mg-empty" data-mg-empty hidden>Aucune étape ne correspond.</p>';
    echo '<p class="mg-steps__foot" data-mg-issues-note' . ($issues > 0 ? '' : ' hidden') . '>Seules les étapes à traiter sont affichées. <button type="button" class="mg-linkbtn" data-mg-show-all>Afficher les ' . count($steps) . ' étapes</button></p></section>';

    // Historical results are read-only; they never repopulate the execution queue.
    $history = migrations_web_historical_steps($steps, $results);
    if ($history !== []) {
        echo '<section class="ath-panel mg-card"><h2 class="ath-panel__title">Historique des étapes archivées</h2><p>Consultation uniquement ; ces étapes ne peuvent plus être relancées.</p><ul class="mg-list">';
        foreach ($history as $past) {
            $result = $results[$past['id']];
            echo '<li><code>' . migrations_web_h($past['id']) . '</code><span>' . migrations_web_h($result['message'] ?? '') . '</span><a class="ath-btn mg-mini" href="' . $self . '?action=output&amp;step=' . rawurlencode($past['id']) . '">Sortie</a></li>';
        }
        echo '</ul></section>';
    }

    echo '<div class="mg-grid"><section class="ath-panel mg-card"><header class="ath-panel__head"><h2 class="ath-panel__title">File des nouvelles migrations</h2></header><p>Le dossier historique <code>migrations/</code> n’est plus parcouru. Seules les étapes du registre actif sont proposées.</p><p>' . count($steps) . ' étape(s) inscrite(s).</p></section>';

    $bool = static fn (?bool $v, string $yes, string $no): string => $v === null ? '<span class="mg-muted">—</span>' : '<span class="ath-tag ath-tag--' . ($v ? 'ok' : 'bad') . '">' . ($v ? $yes : $no) . '</span>';
    echo '<section class="ath-panel mg-card"><header class="ath-panel__head"><h2 class="ath-panel__title">État de la base</h2>' . $bool($db['connected'], 'Connectée', 'Injoignable') . '</header>';
    echo '<dl class="mg-kv mg-kv--rows">';
    echo '<div><dt>Hôte</dt><dd><code>' . migrations_web_h((string) ($db['host'] ?? '—')) . '</code></dd></div>';
    echo '<div><dt>Base</dt><dd><code>' . migrations_web_h((string) ($db['name'] ?? '—')) . '</code></dd></div>';
    echo '<div><dt>Identifiants</dt><dd>utilisateur ' . migrations_web_h($status['env']['DB_USER']) . ', mot de passe ' . migrations_web_h($status['env']['DB_PASSWORD']) . '</dd></div>';
    echo '<div><dt>Tables</dt><dd>' . ($db['tables'] ?? '—') . '</dd></div>';
    echo '<div><dt>Communautés</dt><dd>' . ($db['tenants'] ?? '—') . '</dd></div>';
    echo '<div><dt>Comptes</dt><dd>' . ($db['users'] ?? '—') . '</dd></div>';
    echo '<div><dt>Comptes de démonstration</dt><dd>' . ($db['demo_accounts'] === null ? '—' : ((int) $db['demo_accounts'] === 0 ? '<span class="ath-tag ath-tag--ok">Aucun</span>' : '<span class="ath-tag ath-tag--warn">' . (int) $db['demo_accounts'] . ' à supprimer</span>')) . '</dd></div>';
    echo '<div><dt>Communauté système</dt><dd>' . $bool($db['has_default_tenant'], 'présente', 'absente') . '</dd></div>';
    echo '<div><dt>Profil de communauté</dt><dd>' . $bool($db['has_tenant_type'], 'présent', 'manquant') . '</dd></div>';
    echo '</dl>';
    if ($db['module_tables'] !== []) {
        echo '<p class="mg-sub">Tables des modules ATAK et C2</p><ul class="mg-chips">';
        foreach ($db['module_tables'] as $table => $m) {
            echo '<li class="' . ($m['ok'] ? 'is-ok' : 'is-bad') . '" title="' . migrations_web_h($table) . '">' . migrations_web_h($m['label']) . '</li>';
        }
        echo '</ul>';
    }
    $missingFiles = array_keys(array_filter($status['files'], static fn ($ok) => !$ok));
    if ($missingFiles !== []) {
        echo '<p class="mg-flash mg-flash--warn">Fichier(s) absent(s) : ' . migrations_web_h(implode(', ', $missingFiles)) . '</p>';
    }
    echo '</section></div>';

    // Journal
    $log = migrations_web_last_log_text(migrations_web_root());
    echo '<section class="ath-panel mg-card mg-logcard" id="mg-log"><header class="ath-panel__head"><h2 class="ath-panel__title">Journal du dernier passage</h2>';
    if ($lastFile !== null) {
        $ok = !empty($lastFile['ok']);
        echo '<span class="ath-tag ath-tag--' . ($ok ? ((int) ($lastFile['warning_count'] ?? 0) > 0 ? 'warn' : 'ok') : 'bad') . '">' . migrations_web_h((string) ($lastFile['summary_line'] ?? '')) . '</span>';
    }
    echo '</header>';
    if ($lastFile !== null) {
        echo '<p class="ath-panel__lead">' . migrations_web_h(migrations_web_ago((string) ($lastFile['finished_at'] ?? ''))) . ' · ' . migrations_web_h(($lastFile['mode'] ?? '') === 'cli' ? 'ligne de commande' : 'web')
            . (isset($lastFile['duration_sec']) && $lastFile['duration_sec'] !== null ? ' · ' . migrations_web_h(str_replace('.', ',', (string) $lastFile['duration_sec'])) . ' s' : '') . '</p>';
        foreach (['errors' => ['bad', 'Erreurs'], 'warnings' => ['warn', 'Avertissements']] as $k => [$tone, $label]) {
            $items = is_array($lastFile[$k] ?? null) ? $lastFile[$k] : [];
            if ($items !== []) {
                echo '<details class="mg-details"' . ($k === 'errors' ? ' open' : '') . '><summary><span class="ath-tag ath-tag--' . $tone . '">' . count($items) . '</span> ' . $label . '</summary><ul class="mg-list mg-list--' . $tone . '">';
                foreach (array_slice($items, 0, 40) as $line) {
                    echo '<li><span>' . migrations_web_h((string) $line) . '</span></li>';
                }
                echo '</ul></details>';
            }
        }
    }
    if ($log === '') {
        echo '<p class="mg-muted">Aucun journal enregistré pour l’instant : il apparaît après le premier passage.</p>';
    } else {
        $lines = preg_split('/\r\n|\r|\n/', rtrim($log)) ?: [];
        $tail = array_slice($lines, -400);
        echo '<details class="mg-details"><summary>Afficher la console (' . count($tail) . ' dernières lignes sur ' . count($lines) . ')</summary>';
        echo '<pre class="mg-console mg-console--static">' . migrations_web_colorize(implode("\n", $tail)) . '</pre></details>';
    }
    echo '</section>';

    echo '<p class="mg-foot">Ligne de commande : <code>php run-migrations.php</code> (nouvelles étapes uniquement), <code>--list</code> (étapes), <code>--step=identifiant</code> (une étape), <code>--status</code> (file active).</p>';
    migrations_web_layout_end();
}

/** Console : échappe puis colore les marqueurs [OK] / [ATTENTION] / [ERREUR] et les en-têtes d'étape. */
function migrations_web_colorize(string $text): string
{
    $out = [];
    foreach (preg_split('/\n/', $text) ?: [] as $line) {
        $h = migrations_web_h($line);
        if (str_starts_with($line, '» [')) {
            $out[] = '<span class="mg-c-step">' . $h . '</span>';
        } elseif (str_contains($line, '[ERREUR')) {
            $out[] = '<span class="mg-c-bad">' . $h . '</span>';
        } elseif (str_contains($line, '[ATTENTION]')) {
            $out[] = '<span class="mg-c-warn">' . $h . '</span>';
        } elseif (preg_match('/\[(OK|APPLIQUÉ|NETTOYÉ)\]/u', $line)) {
            $out[] = '<span class="mg-c-ok">' . $h . '</span>';
        } else {
            $out[] = $h;
        }
    }

    return implode("\n", $out);
}

function migrations_web_render_output(string $root, string $stepId): void
{
    $step = MigrationRegistry::find($root, $stepId);
    $error = null;
    $pdo = migrations_web_pdo($error);
    $output = $pdo !== null ? MigrationRunLog::lastOutput($pdo, $stepId) : null;
    $result = $pdo !== null ? (MigrationRunLog::latestByStep($pdo)[$stepId] ?? null) : null;
    migrations_web_layout_start('Sortie d’étape');
    $self = migrations_web_self();
    echo '<section class="ath-page-head mg-head"><div class="ath-page-head__copy"><p class="ath-page-head__kicker">MIGRATIONS · SORTIE DE LA DERNIÈRE EXÉCUTION</p>';
    echo '<h1 class="ath-page-head__title">' . migrations_web_h($step['label'] ?? $stepId) . '</h1>';
    echo '<p class="ath-page-head__sub"><code>' . migrations_web_h($stepId) . '</code>' . ($step !== null ? ' · database/steps/' . migrations_web_h($step['file']) : '') . '</p></div>';
    echo '<div class="ath-page-head__actions"><a class="ath-btn" href="' . $self . '#mg-steps">' . migrations_web_icon('back') . '<span>Retour aux étapes</span></a></div></section>';
    if ($result !== null) {
        [$tone, $tl] = migrations_web_status_tag($result['status']);
        echo '<div class="mg-runbar"><span class="ath-tag ath-tag--' . $tone . '">' . $tl . '</span><span>' . migrations_web_h(migrations_web_ago($result['ran_at'])) . '</span><span>' . migrations_web_h(migrations_web_duration($result['duration_ms'])) . '</span><span class="mg-muted">' . migrations_web_h($result['message']) . '</span></div>';
    }
    echo '<pre class="mg-console">' . ($output !== null && $output !== '' ? migrations_web_colorize(MigrationStepTracker::redact($output)) : 'Aucune sortie enregistrée pour cette étape.') . '</pre>';
    migrations_web_layout_end();
}

/** Adresse de retour : la console d'exploitation quand le passage y a été lancé, sinon ce tableau de bord. */
function migrations_web_return_url(string $anchor = ''): string
{
    $console = (string) ($GLOBALS['__migrations_console_return'] ?? '');
    if ($console !== '') {
        return migrations_web_h($console);
    }

    return migrations_web_self() . $anchor;
}

function migrations_web_begin_run_console(string $root, ?array $onlyStep = null): void
{
    @set_time_limit(0);
    @ini_set('max_execution_time', '0');
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    migrations_web_send_headers();
    if (!headers_sent()) {
        header('X-Accel-Buffering: no');
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
        'step' => $onlyStep['id'] ?? null,
    ];
    if ($onlyStep !== null) {
        $GLOBALS['__migrations_only_step'] = $onlyStep['id'];
    }

    migrations_web_layout_start('Migrations en cours');
    $total = count(MigrationRegistry::load($root));
    echo '<section class="ath-page-head mg-head"><div class="ath-page-head__copy"><p class="ath-page-head__kicker">MIGRATIONS · EXÉCUTION EN COURS</p>';
    $runTitle = $onlyStep !== null ? 'Exécution : ' . migrations_web_h($onlyStep['label']) : 'Nouvelles migrations';
    echo '<h1 class="ath-page-head__title">' . $runTitle . '</h1>';
    echo '<p class="ath-page-head__sub">Démarré ' . migrations_web_h(date('d/m/Y à H:i:s')) . ($onlyStep !== null ? ' · étape <code>' . migrations_web_h($onlyStep['id']) . '</code>' : ' · ' . $total . ' étapes') . '. Ne fermez pas la page : le journal est enregistré à la fin.</p></div>';
    echo '<div class="ath-page-head__actions"><a class="ath-btn" href="' . migrations_web_return_url() . '">' . migrations_web_icon('back') . '<span>' . (isset($GLOBALS['__migrations_console_return']) ? 'Console d’exploitation' : 'Tableau de bord') . '</span></a></div></section>';
    echo '<div class="mg-runbar" data-mg-runbar data-total="' . ($onlyStep !== null ? 1 : $total) . '"><span class="mg-pulse" aria-hidden="true"></span><b data-mg-progress-label>Préparation…</b><span class="mg-progress"><span class="mg-progress__bar" data-mg-progress></span></span></div>';
    echo '<pre class="mg-console" id="mg-console" data-mg-live>';
    echo str_repeat(' ', 2048) . "\n";
    echo migrations_web_h('=== Athena · pipeline des migrations — ' . $started . " ===\n\n");
    @flush();

    // Tout ce qu'écrit le pipeline passe ici : enregistré tel quel, affiché échappé et coloré.
    ob_start(static function (string $chunk): string {
        if (isset($GLOBALS['__migrations_web_run']) && is_array($GLOBALS['__migrations_web_run'])) {
            $GLOBALS['__migrations_web_run']['log'] .= $chunk;
        }

        return migrations_web_colorize($chunk);
    }, 1);
    $GLOBALS['__migrations_ob_floor'] = ob_get_level();

    register_shutdown_function(static function (): void {
        $run = $GLOBALS['__migrations_web_run'] ?? null;
        if (!is_array($run)) {
            return;
        }
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        $log = (string) ($run['log'] ?? '');
        $ok = !str_contains($log, '[ERREUR') && !str_contains($log, 'Connexion impossible') && http_response_code() < 500;
        $err = error_get_last();
        if (is_array($err) && in_array((int) ($err['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $ok = false;
            $line = '[ERREUR FATALE] ' . ($err['message'] ?? '') . ' — ' . basename((string) ($err['file'] ?? '')) . ':' . (string) ($err['line'] ?? '');
            $log .= "\n" . $line . "\n";
            echo "\n" . '<span class="mg-c-bad">' . migrations_web_h($line) . '</span>' . "\n";
        }
        $duration = round(microtime(true) - (float) ($run['started_ts'] ?? microtime(true)), 2);
        migrations_web_write_last_run((string) $run['root'], [
            'started_at' => $run['started_at'] ?? null,
            'finished_at' => date('c'),
            'duration_sec' => $duration,
            'ok' => $ok,
            'mode' => 'web',
            'step' => $run['step'] ?? null,
        ], $log);
        $warn = substr_count($log, '[ATTENTION]');
        echo "\n" . migrations_web_h('=== Fin (' . str_replace('.', ',', (string) $duration) . ' s) ===') . '</pre>';
        echo '<div class="mg-runbar mg-runbar--done" data-mg-done>';
        echo $ok
            ? '<span class="ath-tag ath-tag--' . ($warn > 0 ? 'warn' : 'ok') . '">' . ($warn > 0 ? 'Terminé avec ' . $warn . ' avertissement(s)' : 'Terminé sans erreur') . '</span>'
            : '<span class="ath-tag ath-tag--bad">Terminé avec des erreurs</span>';
        echo '<span>' . migrations_web_h(str_replace('.', ',', (string) $duration)) . ' s</span><span class="mg-runbar__spacer"></span>';
        echo '<a class="ath-btn ath-btn--solid" href="' . migrations_web_return_url('#mg-steps') . '">' . migrations_web_icon('back') . '<span>Voir le résultat des étapes</span></a></div>';
        migrations_web_layout_end();
        @flush();
    });
}

/**
 * Point d'entrée : authentifie, affiche l'interface ou prépare la console.
 * Ne rend la main que si l'exécution du pipeline doit continuer.
 */
function migrations_web_gate(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $root = migrations_web_root();
    migrations_web_load_env($root);
    migrations_web_boot_session();
    if (is_file($root . '/bootstrap/migration_pdo.php')) {
        require_once $root . '/bootstrap/migration_pdo.php';
    }
    $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $action = (string) ($method === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? ''));

    // Lancement depuis la console d'exploitation (admin du site vérifié) : ticket à usage unique.
    if ($method === 'GET' && isset($_GET['ticket'])) {
        require_once $root . '/app/Support/Ops/OpsMigrationTicket.php';
        $ticket = \App\Support\Ops\OpsMigrationTicket::consume($root, (string) $_GET['ticket']);
        if ($ticket === null) {
            migrations_web_render_login('Lien de lancement expiré ou déjà utilisé : relancez depuis la console d’exploitation (Administration du site › Exploitation).');
            exit;
        }
        $return = (string) ($ticket['return'] ?? '');
        $GLOBALS['__migrations_console_return'] = preg_match('#^(https?://[^\s"<>]+|/[^\s"<>]*)$#', $return) === 1 ? $return : '/admin/system/exploitation?onglet=migrations';
        $queueError = migrations_web_queue_error($root, (string) ($ticket['action'] ?? ''), (string) ($ticket['step'] ?? ''));
        if ($queueError !== null) {
            migrations_web_render_login($queueError, $ticket['action'] === 'run' ? 'ok' : 'warn');
            exit;
        }
        $only = null;
        if ($ticket['action'] === 'run_step') {
            $only = MigrationRegistry::find($root, (string) ($ticket['step'] ?? ''));
        }
        migrations_web_begin_run_console($root, $only);

        return; // la suite de run-migrations.php exécute le pipeline
    }

    if ($action === 'logout' && $method === 'POST') {
        if (migrations_web_csrf_ok($_POST['csrf'] ?? null)) {
            migrations_web_set_authenticated(false);
            $_SESSION = [];
        }
        migrations_web_render_login('Session fermée.', 'ok');
        exit;
    }

    if ($action === 'login' && $method === 'POST') {
        if (!migrations_web_csrf_ok($_POST['csrf'] ?? null)) {
            migrations_web_render_login('Session expirée. Réessayez.');
            exit;
        }
        if (migrations_web_lock_remaining() > 0) {
            migrations_web_render_login('Accès temporairement verrouillé.');
            exit;
        }
        if (migrations_web_password_ok((string) ($_POST['password'] ?? ''))) {
            migrations_web_set_authenticated(true);
            header('Location: ' . basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'run-migrations.php')), true, 303);
            exit;
        }
        migrations_web_register_failed_attempt();
        usleep(400000);
        migrations_web_render_login('Mot de passe incorrect.');
        exit;
    }

    if (!migrations_web_is_authenticated()) {
        migrations_web_render_login();
        exit;
    }

    if (in_array($action, ['run', 'run_step', 'replay'], true) && $method === 'POST') {
        if (!migrations_web_csrf_ok($_POST['csrf'] ?? null)) {
            migrations_web_render_dashboard(migrations_web_collect_status($root), 'Jeton de sécurité invalide. Actualisez la page puis réessayez.', 'bad');
            exit;
        }
        $queueError = migrations_web_queue_error($root, $action, (string) ($_POST['step'] ?? ''));
        if ($queueError !== null) {
            migrations_web_render_dashboard(migrations_web_collect_status($root), $queueError, $action === 'run' ? 'ok' : 'warn');
            exit;
        }
        $only = null;
        if ($action === 'run_step') {
            $only = MigrationRegistry::find($root, (string) ($_POST['step'] ?? ''));
        }
        migrations_web_begin_run_console($root, $only);

        return; // la suite de run-migrations.php exécute le pipeline
    }

    if ($action === 'output') {
        migrations_web_render_output($root, (string) ($_GET['step'] ?? ''));
        exit;
    }

    $flash = $action === 'refresh' ? 'État actualisé à ' . date('H:i:s') . '.' : null;
    migrations_web_render_dashboard(migrations_web_collect_status($root), $flash, 'ok');
    exit;
}
