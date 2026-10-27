<?php
declare(strict_types=1);

/**
 * Pipeline des migrations : schéma, migrations PHP de bootstrap/, référentiels et maintenance.
 *
 * Point d’entrée recommandé : **php setup-database.php** (qui inclut ce fichier).
 *
 * Organisation :
 *  - ce fichier prépare l’exécution (.env, connexion, moteur, fichiers bootstrap) puis enchaîne les étapes
 *    du registre database/steps/registry.php, dans l’ordre ;
 *  - chaque étape est un fichier de database/steps/ exécuté dans la portée de ce script ($pdo, $root,
 *    $migrationRunner, $migrationFlush, $migrationEnsurePdo, database/steps/_context.php) ;
 *  - chaque étape est chronométrée et notée (ok / avertissement / erreur) dans schema_migration_runs,
 *    que le tableau de bord web affiche ; un échec n’arrête pas la suite ;
 *  - les fichiers migrations/*.sql sont suivis dans comspec_schema_migrations (bootstrap/migration_runner.php).
 *
 * Web : console d'exploitation /admin/system/exploitation (admin du site + double facteur), qui lance
 * ce pipeline par /run-migrations.php?ticket=… ; /run-migrations.php seul reste l'accès de secours par mot de passe
 * (bootstrap/migrations_web_ui.php), utile quand l'application elle-même ne démarre plus.
 *
 * Options CLI :
 *   php run-migrations.php --list              liste des étapes (sans connexion à la base)
 *   php run-migrations.php --step=identifiant  relance une seule étape (si elle est relançable seule)
 *   php run-migrations.php --status            état des fichiers SQL, sans rien exécuter
 *   php run-migrations.php --replay=nom.sql    rejoue ce(s) fichier(s) (liste séparée par des virgules)
 *   php run-migrations.php --replay-all        rejoue tous les fichiers SQL
 */

$root = dirname(__FILE__);

if (PHP_SAPI !== 'cli') {
    require_once $root . '/bootstrap/migrations_web_ui.php';
    migrations_web_gate();
}

require_once $root . '/app/Support/Migrations/MigrationRegistry.php';
require_once $root . '/app/Support/Migrations/MigrationRunLog.php';
require_once $root . '/app/Support/Migrations/MigrationStepTracker.php';

$migrationCliArgs = PHP_SAPI === 'cli' ? array_slice((array) ($GLOBALS['argv'] ?? []), 1) : [];
if (in_array('--list', $migrationCliArgs, true)) {
    foreach (\App\Support\Migrations\MigrationRegistry::load($root) as $migrationListStep) {
        printf(
            "%3d  %-13s %-46s %s%s\n",
            $migrationListStep['index'],
            \App\Support\Migrations\MigrationRegistry::GROUPS[$migrationListStep['group']],
            $migrationListStep['id'],
            $migrationListStep['label'],
            $migrationListStep['rerun'] ? '' : '  (non relançable seule)'
        );
    }
    exit(0);
}

// ----- Vérifications préalables -----
$checks = [];
$checks['.env'] = is_file($root . '/.env');
$checks['schema.sql'] = is_file($root . '/migrations/schema.sql');

foreach ($checks as $label => $ok) {
    if (!$ok) {
        echo "[ERREUR] Fichier manquant : $label\n";
    }
}
if (!($checks['schema.sql'] ?? false)) {
    echo "Créez migrations/schema.sql ou placez-vous à la racine du projet.\n";
    exit(1);
}

// Charger .env
if ($checks['.env']) {
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
    echo "[OK] .env chargé\n";
} else {
    echo "[ATTENTION] Pas de fichier .env — utilisation des variables d'environnement ou valeurs par défaut.\n";
}

/**
 * Affiche tout de suite en mode web (tampons / compression).
 * En UI sécurisée, on conserve le tampon de journal (ob_start) : ob_flush seulement.
 */
$migrationFlush = static function (): void {
    if (PHP_SAPI !== 'cli') {
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
    }
    if (!empty($GLOBALS['__migrations_web_run'])) {
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();

        return;
    }
    // Ne descend pas sous le tampon de suivi de l’étape en cours (MigrationStepTracker).
    while (ob_get_level() > (int) ($GLOBALS['__migrations_ob_floor'] ?? 0)) {
        @ob_end_flush();
    }
    @flush();
};

// Connexion DB — TCP (jamais le socket Unix « localhost », bloqué sur Hostinger).
require_once $root . '/bootstrap/migration_pdo.php';
$name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
$user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
$pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';

if ($name === '' || $user === '') {
    echo "Erreur : DB_NAME et DB_USER sont requis. Créez un fichier .env (voir .env.example) ou définissez les variables d'environnement.\n";
    exit(1);
}

$dsn = migration_mysql_dsn();
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    migration_apply_session_collation($pdo);
} catch (PDOException $e) {
    echo "Connexion impossible : " . $e->getMessage() . "\n";
    echo "Vérifiez DB_HOST, DB_NAME, DB_USER et DB_PASSWORD.\n";
    exit(1);
}
echo "[OK] Connexion base : $name\n";
$migrationFlush();

require_once $root . '/app/Support/SqlText.php';

/** Recrée $pdo si la session MySQL est morte — TCP uniquement, pas de socket localhost. */
$migrationEnsurePdo = static function () use (&$pdo, $migrationFlush): void {
    if (migration_pdo_is_alive($pdo)) {
        return;
    }
    try {
        migration_reconnect_pdo($pdo);
        echo "  [INFO] Reconnexion MySQL OK\n";
        $migrationFlush();
    } catch (Throwable $e) {
        echo '  [ERREUR] Reconnexion MySQL impossible : ' . $e->getMessage() . "\n";
        $migrationFlush();
    }
};

require_once $root . '/bootstrap/migration_runner.php';
$migrationArgv = PHP_SAPI === 'cli' ? array_slice((array) ($GLOBALS['argv'] ?? []), 1) : [];
// Rejeu demandé depuis la console d'exploitation (fichiers déjà vérifiés par bootstrap/migrations_web_ui.php).
foreach ((array) ($GLOBALS['__migrations_replay'] ?? []) as $migrationReplayFile) {
    $migrationArgv[] = '--replay=' . $migrationReplayFile;
}
if (in_array('--status', $migrationArgv, true)) {
    // État des fichiers SQL sans rien exécuter.
    $sqlStatus = ComspecMigrationRunner::statusReport($pdo, $root . '/migrations');
    echo "Fichiers SQL : {$sqlStatus['total']} (appliqués {$sqlStatus['applied']}, exclus {$sqlStatus['excluded']}, "
        . 'en attente ' . count($sqlStatus['pending']) . ', en échec ' . count($sqlStatus['failed']) . ")\n";
    foreach ($sqlStatus['pending'] as $pendingName) {
        echo "  [EN ATTENTE] {$pendingName}\n";
    }
    foreach ($sqlStatus['failed'] as $failedRow) {
        echo "  [ERREUR] {$failedRow['name']} — {$failedRow['error']}\n";
    }
    exit(0);
}
$migrationRunner = new ComspecMigrationRunner(
    static function () use (&$pdo): PDO {
        return $pdo;
    },
    $migrationFlush,
    $migrationEnsurePdo,
    $migrationArgv
);
$migrationRunner->registerShutdownReport();

echo "[→] Chargement des fichiers bootstrap (plateforme / RBAC)…\n";
$migrationFlush();

$bootstrapFiles = [
    'community_platform_migration.php',
    'platform_unit_commander_migration.php',
    'prod_import_gaps.php',
    'rbac_three_layer_migration.php',
    'user_roles_migration.php',
    'tenant_user_roles_graph_catalog_migration.php',
    'co_unit_rbac_triggers_migration.php',
    'permissions_action_migration.php',
    'roles_organic_architecture_migration.php',
    'military_role_catalog_schema_migration.php',
    'moderation_granular_sanctions_migration.php',
    'seniority_engine_migration.php',
    'personnel_progression_engine_migration.php',
    'tenant_member_number_migration.php',
    'personnel_capability_axes_migration.php',
    'qualification_referentiel_migration.php',
    'advancement_grade_migration.php',
    'rank_catalog_migration.php',
    'personnel_career_advancement_migration.php',
    'arma_playtime_migration.php',
    'roleplay_game_sessions_migration.php',
    'personnel_phase_rules_migration.php',
    'personnel_pass_migration.php',
    'user_ui_tours_migration.php',
    'operator_game_registry_migration.php',
    'personnel_org_history_migration.php',
    'organization_visibility_status_migration.php',
    'orbat_billets_management_migration.php',
    'org_duty_workbench_migration.php',
    'unit_identity_enrichment_migration.php',
    'personnel_stage_bilans_migration.php',
    'member_integration_migration.php',
    'personnel_function_kits_migration.php',
    'function_based_access_v2_migration.php',
    'document_versions_file_path_nullable_migration.php',
    'personnel_absences_migration.php',
    'positions_admin_category_migration.php',
    'personnel_profile_extended_details_migration.php',
    'personnel_profile_rp_identity_migration.php',
    'personnel_personal_dossier_enhancements_migration.php',
    'tenant_decoration_motifs_migration.php',
    'user_deletion_request_migration.php',
    'user_community_identity_migration.php',
    'user_advanced_edit_grants_migration.php',
    'hr_charter_lms_migration.php',
    'recon_pv_tammuc_migration.php',
    'core_schema_extensions_migration.php',
    'forum_removal_migration.php',
    'request_telemetry_migration.php',
    'platform_admin_tenant_intervention_migration.php',
    'users_platform_admin_flag_migration.php',
];
foreach ($bootstrapFiles as $bf) {
    $path = $root . '/bootstrap/' . $bf;
    echo "    … {$bf}\n";
    $migrationFlush();
    require_once $path;
    echo "      [chargé]\n";
    $migrationFlush();
}

echo "[OK] Fichiers bootstrap chargés.\n";
$migrationFlush();

// ----- Étapes du registre (database/steps/registry.php) -----
$migrationSteps = \App\Support\Migrations\MigrationRegistry::load($root);
if ($migrationSteps === []) {
    echo "[ERREUR] Registre des étapes vide ou absent : database/steps/registry.php\n";
    exit(1);
}

// Relance d’une seule étape : php run-migrations.php --step=identifiant (ou bouton « Relancer » de l’interface web).
$migrationOnlyStep = (string) ($GLOBALS['__migrations_only_step'] ?? '');
foreach ($migrationArgv as $migrationArg) {
    if (str_starts_with($migrationArg, '--step=')) {
        $migrationOnlyStep = trim(substr($migrationArg, 7));
    }
}
if ($migrationOnlyStep !== '') {
    $migrationOne = \App\Support\Migrations\MigrationRegistry::find($root, $migrationOnlyStep);
    if ($migrationOne === null) {
        echo "[ERREUR] Étape inconnue : {$migrationOnlyStep} (php run-migrations.php --list)\n";
        exit(1);
    }
    if (!$migrationOne['rerun']) {
        echo "[ERREUR] L’étape {$migrationOnlyStep} dépend des étapes précédentes : lancez le pipeline complet.\n";
        exit(1);
    }
    // Ce que les étapes précédentes chargent d’habitude en chemin.
    require_once $root . '/bootstrap/autoload.php';
    require_once $root . '/bootstrap/schema_ensure_column.php';
    $migrationSteps = [$migrationOne];
    echo "[→] Relance de l’étape seule : {$migrationOne['label']} ({$migrationOne['id']})\n";
}

$migrationRunId = \App\Support\Migrations\MigrationRunLog::newRunId();
\App\Support\Migrations\MigrationRunLog::ensure($pdo);
\App\Support\Migrations\MigrationStepTracker::start(
    static function () use (&$pdo): ?PDO {
        return $pdo instanceof PDO ? $pdo : null;
    },
    $migrationRunId,
    PHP_SAPI === 'cli' ? 'cli' : 'web',
    count(\App\Support\Migrations\MigrationRegistry::load($root)),
    static fn (): int => $migrationRunner->failureCount()
);
require $root . '/database/steps/_context.php';

foreach ($migrationSteps as $migrationStep) {
    if (!is_file($migrationStep['path'])) {
        echo "\n[ATTENTION] Fichier d’étape absent : database/steps/{$migrationStep['file']}\n";
        continue;
    }
    \App\Support\Migrations\MigrationStepTracker::begin($migrationStep);
    try {
        require $migrationStep['path'];
        \App\Support\Migrations\MigrationStepTracker::end();
    } catch (Throwable $migrationStepError) {
        \App\Support\Migrations\MigrationStepTracker::end($migrationStepError);
        $migrationRunner->warn($migrationStep['id'], $migrationStepError);
        $migrationEnsurePdo();
    }
    $migrationFlush();
}
\App\Support\Migrations\MigrationStepTracker::printSummary();

if ($migrationOnlyStep !== '') {
    $migrationsOk = $migrationRunner->printReport();
    if (PHP_SAPI === 'cli' && !$migrationsOk) {
        exit(1);
    }

    return;
}

require_once $root . '/bootstrap/migrations_full_post.php';
comspec_print_post_migration_report($pdo, $root, $migrationFlush);
$migrationsOk = $migrationRunner->printReport();

if (PHP_SAPI === 'cli' && function_exists('migrations_web_write_last_run') === false) {
    // Journal CLI optionnel si l’UI web n’a pas chargé le helper
    $cliLogPath = $root . '/bootstrap/migrations_web_ui.php';
    if (is_file($cliLogPath)) {
        require_once $cliLogPath;
    }
}
if (PHP_SAPI === 'cli' && function_exists('migrations_web_write_last_run')) {
    // Le journal détaillé est surtout alimenté en mode web (ob_start). En CLI on note le bilan.
    migrations_web_write_last_run($root, [
        'started_at' => date('c'),
        'finished_at' => date('c'),
        'duration_sec' => null,
        'ok' => $migrationsOk,
        'mode' => 'cli',
        'summary_line' => $migrationsOk ? 'Passage CLI terminé' : 'Passage CLI terminé avec des échecs',
    ], $migrationsOk ? "Migrations terminées (CLI).\n" : "[ERREUR] Migrations terminées avec des échecs (CLI).\n");
}
if (PHP_SAPI === 'cli' && !$migrationsOk) {
    exit(1);
}
