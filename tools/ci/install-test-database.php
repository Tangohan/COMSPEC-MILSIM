<?php

declare(strict_types=1);

/**
 * Installe une base de TEST complète à partir de zéro : migrations/schema.sql, les fichiers bootstrap/*_migration.php,
 * toutes les étapes historiques (database/steps/registry-archive.php, y compris les fichiers migrations/*.sql) puis la
 * file active (database/steps/registry.php), dans cet ordre, comme le pipeline d'avant la baseline du 29 octobre 2026.
 *
 * Réservé à la CI (job mysql-directory) : il refuse de tourner si la base visée n'est pas vide ou si son nom ne commence
 * pas par « comspec_e2e ». Aucune connexion à un serveur de production : seules les variables DB_* de la CI sont lues.
 *
 * php tools/ci/install-test-database.php   (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD)
 * Sortie : les étapes en échec sont listées ; le code de sortie reste 0 tant que les tables de la création d'une
 * communauté existent (le test de contrat vérifie le reste).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
set_time_limit(0);
$root = dirname(__DIR__, 2);
$dbName = (string) (getenv('DB_NAME') ?: '');
if (!str_starts_with($dbName, 'comspec_e2e')) {
    fwrite(STDERR, "[ERREUR] DB_NAME doit commencer par comspec_e2e (base de test jetable).\n");
    exit(1);
}
require_once $root . '/app/Support/Migrations/MigrationRegistry.php';
require_once $root . '/app/Support/Migrations/MigrationRunLog.php';
require_once $root . '/app/Support/Migrations/MigrationStepTracker.php';
require_once $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/migration_pdo.php';
require_once $root . '/bootstrap/migration_runner.php';

$host = migration_normalize_mysql_host((string) (getenv('DB_HOST') ?: '127.0.0.1'));
$port = (int) (getenv('DB_PORT') ?: 3306);
$user = (string) (getenv('DB_USER') ?: '');
$pass = (string) (getenv('DB_PASSWORD') ?: '');
$server = new PDO('mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4', $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE IF NOT EXISTS `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$existing = $server->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = " . $server->quote($dbName))->fetchColumn();
if ((int) $existing > 0) {
    fwrite(STDERR, "[ERREUR] La base {$dbName} n'est pas vide.\n");
    exit(1);
}
// Mode strict de la production pour toutes les sessions (pipeline et application).
$server->exec("SET GLOBAL sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
$server = null;

$pdo = new PDO(migration_mysql_dsn(), $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
migration_apply_session_collation($pdo);
$migrationFlush = static function (): void {
    @flush();
};
$migrationEnsurePdo = static function () use (&$pdo): void {
    if (!migration_pdo_is_alive($pdo)) {
        migration_reconnect_pdo($pdo);
    }
};
$migrationRunner = new ComspecMigrationRunner(static function () use (&$pdo): PDO {
    return $pdo;
}, $migrationFlush, $migrationEnsurePdo);

// Fichiers chargés par l'ancien pipeline avant ses étapes (les étapes chargent les autres elles-mêmes).
$bootstrapFiles = [
    'community_platform_migration.php', 'platform_unit_commander_migration.php', 'prod_import_gaps.php',
    'rbac_three_layer_migration.php', 'user_roles_migration.php', 'tenant_user_roles_graph_catalog_migration.php',
    'co_unit_rbac_triggers_migration.php', 'permissions_action_migration.php', 'roles_organic_architecture_migration.php',
    'military_role_catalog_schema_migration.php', 'moderation_granular_sanctions_migration.php', 'seniority_engine_migration.php',
    'personnel_progression_engine_migration.php', 'tenant_member_number_migration.php', 'personnel_capability_axes_migration.php',
    'qualification_referentiel_migration.php', 'advancement_grade_migration.php', 'rank_catalog_migration.php',
    'personnel_career_advancement_migration.php', 'arma_playtime_migration.php', 'roleplay_game_sessions_migration.php',
    'personnel_phase_rules_migration.php', 'personnel_pass_migration.php', 'user_ui_tours_migration.php',
    'operator_game_registry_migration.php', 'personnel_org_history_migration.php', 'organization_visibility_status_migration.php',
    'orbat_billets_management_migration.php', 'org_duty_workbench_migration.php', 'unit_identity_enrichment_migration.php',
    'personnel_stage_bilans_migration.php', 'member_integration_migration.php', 'personnel_function_kits_migration.php',
    'function_based_access_v2_migration.php', 'document_versions_file_path_nullable_migration.php', 'personnel_absences_migration.php',
    'positions_admin_category_migration.php', 'personnel_profile_extended_details_migration.php',
    'personnel_profile_rp_identity_migration.php', 'personnel_personal_dossier_enhancements_migration.php',
    'tenant_decoration_motifs_migration.php', 'user_deletion_request_migration.php', 'user_community_identity_migration.php',
    'user_advanced_edit_grants_migration.php', 'hr_charter_lms_migration.php', 'recon_pv_tammuc_migration.php',
    'core_schema_extensions_migration.php', 'forum_removal_migration.php', 'request_telemetry_migration.php',
    'platform_admin_tenant_intervention_migration.php', 'users_platform_admin_flag_migration.php',
];
foreach ($bootstrapFiles as $bootstrapFile) {
    require_once $root . '/bootstrap/' . $bootstrapFile;
}
require_once $root . '/bootstrap/schema_ensure_column.php';
require $root . '/database/steps/_context.php';

$steps = [];
foreach ([(array) require $root . '/database/steps/registry-archive.php', (array) require $root . '/database/steps/registry.php'] as $registry) {
    foreach ($registry as $row) {
        $steps[] = ['id' => (string) $row['id'], 'path' => $root . '/database/steps/' . basename((string) $row['file'])];
    }
}
$failed = [];
$started = microtime(true);
foreach ($steps as $migrationStep) {
    ob_start();
    try {
        require $migrationStep['path'];
    } catch (Throwable $migrationStepError) {
        $failed[$migrationStep['id']] = $migrationStepError->getMessage();
        $migrationEnsurePdo();
    }
    $out = (string) ob_get_clean();
    if (preg_match_all('/^.*\[(?:ERREUR|ATTENTION)\].*$/m', $out, $m) > 0) {
        foreach (array_slice($m[0], 0, 3) as $line) {
            echo '  ' . $migrationStep['id'] . ' : ' . trim($line) . "\n";
        }
    }
}
$migrationRunner->printReport();
// L'étape 0390 (identités) passe avant l'ajout de users.is_service_account (1370) : en production, la migration repasse
// à la connexion suivante (UserRepository). Même état ici, sinon chaque connexion retenterait son ALTER.
run_user_community_identity_migration($pdo, static function (string $m): void {
    echo '  identités : ' . $m . "\n";
});

// MySQL ne connaît pas « ADD COLUMN IF NOT EXISTS » (syntaxe MariaDB) : ces fichiers échouent en entier ci-dessus.
// Pour que la base MySQL ait les mêmes colonnes que la base MariaDB, leurs ALTER sont rejoués clause par clause sans
// « IF NOT EXISTS », une colonne ou un index déjà présent (1060, 1061) étant ignoré.
$isMariaDb = stripos((string) $pdo->query('SELECT VERSION()')->fetchColumn(), 'mariadb') !== false;
if (!$isMariaDb) {
    $replayed = 0;
    foreach (glob($root . '/migrations/*.sql') ?: [] as $sqlFile) {
        $sql = (string) file_get_contents($sqlFile);
        if (!preg_match('/ADD\s+(?:COLUMN|INDEX|KEY|UNIQUE\s+(?:INDEX|KEY))\s+IF\s+NOT\s+EXISTS/i', $sql)) {
            continue;
        }
        foreach (ComspecMigrationRunner::splitStatements($sql) as $statement) {
            if (!preg_match('/^\s*ALTER\s+TABLE\s+(`?\w+`?)\s+(.*)$/is', $statement, $alter)) {
                continue;
            }
            $clauses = preg_split('/,\s*(?=(?:ADD|MODIFY|CHANGE|DROP)\s)/i', rtrim(trim($alter[2]), ';')) ?: [];
            foreach ($clauses as $clause) {
                $clause = (string) preg_replace('/\b(ADD\s+(?:COLUMN|INDEX|KEY|UNIQUE\s+(?:INDEX|KEY)))\s+IF\s+NOT\s+EXISTS\b/i', '$1', $clause);
                $clause = (string) preg_replace('/\b(DROP\s+(?:COLUMN|INDEX|KEY))\s+IF\s+EXISTS\b/i', '$1', $clause);
                try {
                    $pdo->exec('ALTER TABLE ' . $alter[1] . ' ' . $clause);
                    $replayed++;
                } catch (PDOException $e) {
                    if (!in_array((int) ($e->errorInfo[1] ?? 0), [1060, 1061, 1091, 1146], true)) {
                        echo '  [MySQL] ' . basename($sqlFile) . ' : ' . preg_replace('/\s+/', ' ', mb_substr($e->getMessage(), 0, 200)) . "\n";
                    }
                }
            }
        }
    }
    echo "MySQL : {$replayed} clause(s) « IF NOT EXISTS » rejouée(s) sans cette syntaxe.\n";
}
// Journal général en table : le test de contrat y relève les DDL exécutées pendant une création (validation implicite).
$pdo->exec("SET GLOBAL log_output = 'TABLE'");
$pdo->exec('SET GLOBAL general_log = 1');
printf("%d étapes exécutées en %.0f s, %d en échec.\n", count($steps), microtime(true) - $started, count($failed));
foreach ($failed as $id => $message) {
    echo "[ÉCHEC] {$id} : " . preg_replace('/\s+/', ' ', mb_substr($message, 0, 300)) . "\n";
}
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo count($tables) . " tables créées.\n";
foreach (['tenants', 'users', 'roles', 'permissions', 'units', 'grades', 'grade_systems', 'personnel_job_roles'] as $required) {
    if (!in_array($required, $tables, true)) {
        fwrite(STDERR, "[ERREUR] Table indispensable absente : {$required}\n");
        exit(1);
    }
}
