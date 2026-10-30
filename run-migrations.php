<?php

declare(strict_types=1);

/**
 * Future schema changes only. The 2026-10-29 baseline retired the historical pipeline.
 *
 * Register new, self-contained steps in database/steps/registry.php. An empty queue
 * succeeds before loading environment variables or opening the database. Historical
 * SQL, bootstrap code, reference data and migration ledgers are never scanned or reset.
 *
 * CLI: php run-migrations.php [--list | --status | --step=active_id]
 * Web: the existing password/CSRF or single-use administration ticket gate is retained.
 */
$root = __DIR__;
if (PHP_SAPI !== 'cli') {
    require_once $root . '/bootstrap/migrations_web_ui.php';
    migrations_web_gate();
}
require_once $root . '/app/Support/Migrations/MigrationRegistry.php';
require_once $root . '/app/Support/Migrations/MigrationRunLog.php';
require_once $root . '/app/Support/Migrations/MigrationStepTracker.php';

$migrationArgs = PHP_SAPI === 'cli' ? array_slice((array) ($GLOBALS['argv'] ?? []), 1) : [];
$migrationOnlyStep = (string) ($GLOBALS['__migrations_only_step'] ?? '');
foreach ($migrationArgs as $migrationArg) {
    if (str_starts_with($migrationArg, '--step=')) {
        $migrationOnlyStep = trim(substr($migrationArg, 7));
        if ($migrationOnlyStep === '') {
            echo "[ERREUR] --step exige l’identifiant d’une nouvelle migration active.\n";
            exit(1);
        }
    } elseif (!in_array($migrationArg, ['--list', '--status'], true)) {
        echo "[ERREUR] Option retirée ou inconnue. Seules --list, --status et --step=identifiant sont disponibles.\n";
        if (PHP_SAPI !== 'cli') http_response_code(400);
        exit(1);
    }
}
if (!empty($GLOBALS['__migrations_replay'])) {
    echo "[ERREUR] Le rejeu des fichiers SQL historiques est retiré du lanceur.\n";
    if (PHP_SAPI !== 'cli') http_response_code(400);
    exit(1);
}
$migrationSteps = \App\Support\Migrations\MigrationRegistry::load($root);
if ($migrationOnlyStep !== '') {
    $migrationOne = \App\Support\Migrations\MigrationRegistry::find($root, $migrationOnlyStep);
    if ($migrationOne === null || !$migrationOne['rerun']) {
        echo "[ERREUR] Étape absente de la file active ou non relançable seule.\n";
        if (PHP_SAPI !== 'cli') http_response_code(400);
        exit(1);
    }
    $migrationSteps = [$migrationOne];
}
if (in_array('--list', $migrationArgs, true)) {
    foreach ($migrationSteps as $migrationStep) {
        printf("%3d  %-13s %-46s %s\n", $migrationStep['index'], \App\Support\Migrations\MigrationRegistry::GROUPS[$migrationStep['group']], $migrationStep['id'], $migrationStep['label']);
    }
    if ($migrationSteps === []) echo "Aucune migration en attente : la file active est vide.\n";
    return;
}
if ($migrationSteps === []) {
    echo "[OK] Aucune migration en attente : la file active est vide.\n";
    echo "Les migrations historiques, les données et les journaux existants sont conservés.\n";
    return;
}

// Only a non-empty active queue needs application helpers and a database connection.
require_once $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/migration_pdo.php';
require_once $root . '/bootstrap/migration_runner.php';
$migrationFlush = static function (): void {
    if (!empty($GLOBALS['__migrations_web_run'])) {
        if (ob_get_level() > 0) @ob_flush();
        @flush();
        return;
    }
    while (ob_get_level() > (int) ($GLOBALS['__migrations_ob_floor'] ?? 0)) @ob_end_flush();
    @flush();
};
try {
    $migrationName = (string) ($_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '');
    $migrationUser = (string) ($_ENV['DB_USER'] ?? getenv('DB_USER') ?: '');
    $migrationPassword = (string) ($_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '');
    if ($migrationName === '' || $migrationUser === '') {
        throw new RuntimeException('DB_NAME et DB_USER sont requis pour appliquer une nouvelle migration.');
    }
    $pdo = new PDO(migration_mysql_dsn(), $migrationUser, $migrationPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    migration_apply_session_collation($pdo);
} catch (Throwable $migrationConnectionError) {
    echo '[ERREUR] Connexion impossible : ' . $migrationConnectionError->getMessage() . "\n";
    if (PHP_SAPI !== 'cli') http_response_code(503);
    exit(1);
}
$migrationResults = \App\Support\Migrations\MigrationRunLog::latestByStep($pdo);
if (in_array('--status', $migrationArgs, true)) {
    foreach ($migrationSteps as $migrationStep) {
        $migrationStatus = $migrationResults[$migrationStep['id']]['status'] ?? 'pending';
        echo '[' . ($migrationStatus === 'ok' ? 'APPLIQUÉE' : 'EN ATTENTE') . '] ' . $migrationStep['id'] . "\n";
    }
    return;
}
if ($migrationOnlyStep === '') {
    $migrationSteps = array_values(array_filter($migrationSteps, static fn (array $step): bool => ($migrationResults[$step['id']]['status'] ?? '') !== 'ok'));
}
if ($migrationSteps === []) {
    echo "[OK] Toutes les migrations de la file active sont déjà appliquées.\n";
    return;
}
if (!\App\Support\Migrations\MigrationRunLog::ensure($pdo)) {
    echo "[ERREUR] Le journal des nouvelles migrations est indisponible. Aucune étape n’a été lancée.\n";
    if (PHP_SAPI !== 'cli') http_response_code(503);
    exit(1);
}
$migrationEnsurePdo = static function () use (&$pdo): void {
    if (!migration_pdo_is_alive($pdo)) migration_reconnect_pdo($pdo);
};
$migrationRunner = new ComspecMigrationRunner(static function () use (&$pdo): PDO { return $pdo; }, $migrationFlush, $migrationEnsurePdo);
$migrationRunner->registerShutdownReport();
\App\Support\Migrations\MigrationStepTracker::start(
    static function () use (&$pdo): ?PDO { return $pdo; },
    \App\Support\Migrations\MigrationRunLog::newRunId(),
    PHP_SAPI === 'cli' ? 'cli' : 'web',
    count($migrationSteps),
    static fn (): int => $migrationRunner->failureCount()
);
require $root . '/database/steps/_context.php';
foreach ($migrationSteps as $migrationIndex => $migrationStep) {
    $migrationStep['index'] = $migrationIndex + 1;
    \App\Support\Migrations\MigrationStepTracker::begin($migrationStep);
    try {
        $migrationRunner->step($migrationStep['id'], static function () use ($migrationStep, &$pdo, $root, $migrationRunner, $migrationFlush, $migrationEnsurePdo, $sseCliLog): void {
            if (!is_file($migrationStep['path'])) throw new RuntimeException('Fichier de nouvelle migration absent.');
            require $migrationStep['path'];
        });
        \App\Support\Migrations\MigrationStepTracker::end();
    } catch (Throwable $migrationStepError) {
        \App\Support\Migrations\MigrationStepTracker::end($migrationStepError);
        $migrationRunner->warn($migrationStep['id'], $migrationStepError);
    }
    $migrationFlush();
}
\App\Support\Migrations\MigrationStepTracker::printSummary();
$migrationCounts = \App\Support\Migrations\MigrationStepTracker::counts();
$migrationsOk = $migrationRunner->printReport() && $migrationCounts['error'] === 0;
if (!$migrationsOk) {
    if (PHP_SAPI !== 'cli') http_response_code(500);
    exit(1);
}
