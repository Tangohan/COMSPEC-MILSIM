<?php
declare(strict_types=1);

/**
 * Pipeline complet schéma + extensions DDL + migrations bootstrap + seed (appelé par setup-database.php).
 *
 * Point d’entrée utilisateur recommandé : **php setup-database.php** (un seul script documenté).
 * Ce fichier reste le moteur procédural ; ne pas le confondre avec les seuls bootstrap PHP isolés.
 *
 * Web (UI sécurisée) : /run-migrations.php — mot de passe + tableau de bord + console live
 * (bootstrap/migrations_web_ui.php). CLI : sortie texte inchangée.
 */

$root = dirname(__FILE__);

if (PHP_SAPI !== 'cli') {
    require_once $root . '/bootstrap/migrations_web_ui.php';
    migrations_web_gate();
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
    while (ob_get_level() > 0) {
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
        echo '  [ATTENTION] Reconnexion MySQL impossible : ' . $e->getMessage() . "\n";
        $migrationFlush();
    }
};

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
    'rank_catalog_migration.php',
    'arma_playtime_migration.php',
    'operator_game_registry_migration.php',
    'personnel_org_history_migration.php',
    'personnel_stage_bilans_migration.php',
    'member_integration_migration.php',
    'personnel_function_kits_migration.php',
    'document_versions_file_path_nullable_migration.php',
    'personnel_absences_migration.php',
    'positions_admin_category_migration.php',
    'personnel_profile_extended_details_migration.php',
    'personnel_profile_rp_identity_migration.php',
    'personnel_personal_dossier_enhancements_migration.php',
    'user_deletion_request_migration.php',
    'user_community_identity_migration.php',
    'user_advanced_edit_grants_migration.php',
    'hr_charter_lms_migration.php',
    'recon_pv_tammuc_migration.php',
    'core_schema_extensions_migration.php',
    'forum_reporting_workflow_migration.php',
    'request_telemetry_migration.php',
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

// ----- Schéma (exécution statement par statement : PDO::exec ne gère qu'une requête) -----
set_time_limit(PHP_SAPI === 'cli' ? 300 : 0);
$schemaPath = $root . '/migrations/schema.sql';
echo "Exécution du schéma...\n";
$migrationFlush();

$sql = @file_get_contents($schemaPath);
if ($sql === false || $sql === '') {
    echo "[ERREUR] Impossible de lire le fichier schema.sql\n";
    exit(1);
}
echo "  Fichier lu (" . strlen($sql) . " octets)\n";
$migrationFlush();

$sql = preg_replace('/--[^\r\n]*/s', '', $sql);
$chunks = preg_split('/;\s*[\r\n]+/', $sql);
$statements = array_filter(array_map('trim', $chunks), function ($s) { return $s !== ''; });
echo "  " . count($statements) . " instructions à exécuter\n";
$migrationFlush();

$done = 0;
$errors = [];
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '') continue;
    try {
        $pdo->exec($stmt . (str_ends_with($stmt, ';') ? '' : ';'));
        $done++;
        // Premières instructions souvent lentes (DDL) : feedback plus fréquent pour éviter l’impression de blocage.
        if ($done <= 25 || $done % 10 === 0) {
            echo "  … {$done}\n";
            $migrationFlush();
        }
    } catch (PDOException $e) {
        $errors[] = $e->getMessage() . ' (extrait: ' . substr($stmt, 0, 80) . '…)';
    }
}
if (!empty($errors)) {
    echo "[ATTENTION] " . count($errors) . " erreur(s) :\n";
    foreach (array_slice($errors, 0, 5) as $err) echo "  - $err\n";
}
echo "Schéma OK. ({$done} instructions exécutées)\n";
$migrationFlush();

// CREATE TABLE IF NOT EXISTS ne met jamais à jour une table existante : combler les colonnes critiques.
require_once $root . '/bootstrap/schema_ensure_column.php';
echo "Vérification colonnes manquantes (ensure schema drift)…\n";
$migrationFlush();
$ensureAdded = schema_ensure_columns($pdo, 'atak_units', [
    'military_id' => '`military_id` varchar(32) DEFAULT NULL AFTER `call_sign`',
    'pos_x' => '`pos_x` decimal(15,4) DEFAULT NULL AFTER `heading`',
    'pos_y' => '`pos_y` decimal(15,4) DEFAULT NULL AFTER `pos_x`',
]);
if ($ensureAdded === 0) {
    echo "  [OK] atak_units : military_id, pos_x, pos_y déjà présentes\n";
} else {
    echo "  [OK] atak_units : {$ensureAdded} colonne(s) comblée(s)\n";
}
$migrationFlush();

// Suppression douce (anonymisation) d'un compte depuis l'annuaire plateforme admin/users.
$ensureAddedUsers = schema_ensure_columns($pdo, 'users', [
    'deleted_at' => '`deleted_at` DATETIME NULL AFTER `status`',
    'deleted_by' => '`deleted_by` INT UNSIGNED NULL AFTER `deleted_at`',
]);
if ($ensureAddedUsers === 0) {
    echo "  [OK] users : deleted_at, deleted_by déjà présentes\n";
} else {
    echo "  [OK] users : {$ensureAddedUsers} colonne(s) comblée(s)\n";
}
$migrationFlush();

// Profil communauté (Complet / Effectifs / ATAK) — critique pour CREATE communauté.
// CREATE TABLE IF NOT EXISTS ne met jamais à jour une table déjà présente : combler tôt.
$ensureTenantType = schema_ensure_columns($pdo, 'tenants', [
    'tenant_type' => "`tenant_type` VARCHAR(32) NOT NULL DEFAULT 'full' COMMENT 'Profil communauté : full | effectifs | atak' AFTER `slug`",
]);
if ($ensureTenantType === 0) {
    echo "  [OK] tenants : tenant_type déjà présente\n";
} else {
    echo "  [OK] tenants : tenant_type comblée ({$ensureTenantType})\n";
}
try {
    $idxTt = $pdo->query(
        "SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND INDEX_NAME = 'idx_tenants_type' LIMIT 1"
    );
    if ($idxTt && !$idxTt->fetchColumn()) {
        $pdo->exec('ALTER TABLE tenants ADD INDEX idx_tenants_type (tenant_type)');
        echo "  [OK] index idx_tenants_type ajouté\n";
    }
} catch (Throwable $e) {
    echo '  [ATTENTION] idx_tenants_type (ensure) : ' . $e->getMessage() . "\n";
}
$migrationFlush();

// Extensions DDL (tableau opérationnel planning_*, ORBAT, tenant_email_*, app_maintenance, label_en rôles…).
run_core_schema_extensions_migration($pdo, $root, $migrationFlush);

// Plans Stripe, colonnes tenants, invitations, modération, événements, usage, codes communauté, parrainage — idempotent.
echo "Migrations bootstrap plateforme (community_platform + unit_commander + rbac_three_layer)...\n";
$migrationFlush();
run_community_platform_migration($pdo);
run_platform_unit_commander_migration($pdo);
run_moderation_granular_sanctions_migration($pdo);
run_seniority_engine_migration($pdo);
run_personnel_progression_engine_migration($pdo);
run_tenant_member_number_migration($pdo);
run_personnel_capability_axes_migration($pdo);
run_rank_catalog_migration($pdo);
run_arma_playtime_migration($pdo);
run_operator_game_registry_migration($pdo);
run_personnel_org_history_migration($pdo);
run_personnel_stage_bilans_migration($pdo);
run_member_integration_migration($pdo);
run_personnel_function_kits_migration($pdo);
run_document_versions_file_path_nullable_migration($pdo);
run_personnel_absences_migration($pdo);
run_personnel_profile_extended_details_migration($pdo);
run_personnel_profile_rp_identity_migration($pdo);
run_personnel_personal_dossier_enhancements_migration($pdo);
run_user_deletion_request_migration($pdo);
run_user_community_identity_migration($pdo, static function (string $m) use ($migrationFlush): void {
    echo '  ' . $m . "\n";
    $migrationFlush();
});
run_user_advanced_edit_grants_migration($pdo);
run_hr_charter_lms_migration($pdo);
run_recon_pv_tammuc_migration($pdo);
run_production_import_gap_migrations($pdo, $root);
run_rbac_three_layer_migration($pdo);
run_user_roles_migration($pdo);
run_tenant_user_roles_graph_catalog_migration($pdo);
try {
    run_co_unit_rbac_triggers_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] co_unit_rbac_triggers : ' . $e->getMessage() . "\n";
}
run_permissions_action_migration($pdo);
run_request_telemetry_migration($pdo);
try {
    run_forum_reporting_workflow_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] forum_reporting_workflow : ' . $e->getMessage() . "\n";
}
try {
    run_roles_organic_architecture_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] roles_organic_architecture : ' . $e->getMessage() . "\n";
}
try {
    run_positions_admin_category_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] positions_admin_category : ' . $e->getMessage() . "\n";
}
$sitePlatformRolesPath = $root . '/bootstrap/site_platform_roles_migration.php';
if (is_file($sitePlatformRolesPath)) {
    require_once $sitePlatformRolesPath;
    try {
        run_site_platform_roles_migration($pdo);
    } catch (Throwable $e) {
        echo '  [ATTENTION] site_platform_roles : ' . $e->getMessage() . "\n";
    }
} else {
    echo "  [ATTENTION] Fichier absent : bootstrap/site_platform_roles_migration.php — ajoutez-le sur le serveur (même version que le dépôt) puis relancez pour créer les rôles site (modération, assistance).\n";
}
try {
    run_military_role_catalog_schema_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] military_role_catalog_schema : ' . $e->getMessage() . "\n";
}
// Invariant : aucun rôle de communauté ne porte d’habilitation réservée à la plateforme.
$reservedPermissionsCleanupPath = $root . '/bootstrap/system_reserved_permissions_cleanup.php';
if (is_file($reservedPermissionsCleanupPath)) {
    try {
        $reservedCleanup = require $reservedPermissionsCleanupPath;
        if (is_callable($reservedCleanup)) {
            $reservedReport = $reservedCleanup($pdo);
            $reservedTotal = (int) ($reservedReport['role_links'] ?? 0)
                + (int) ($reservedReport['user_overrides'] ?? 0)
                + (int) ($reservedReport['tenant_permissions'] ?? 0);
            if ($reservedTotal > 0) {
                echo '  [NETTOYÉ] Habilitations plateforme retirées du périmètre communauté : '
                    . (int) ($reservedReport['role_links'] ?? 0) . " lien(s) de rôle, "
                    . (int) ($reservedReport['user_overrides'] ?? 0) . " surcharge(s) utilisateur, "
                    . (int) ($reservedReport['tenant_permissions'] ?? 0) . " permission(s) de tenant.\n";
            }
        }
    } catch (Throwable $e) {
        echo '  [ATTENTION] system_reserved_permissions_cleanup : ' . $e->getMessage() . "\n";
    }
} else {
    echo "  [ATTENTION] Fichier absent : bootstrap/system_reserved_permissions_cleanup.php — déployez-le puis relancez (purge des habilitations plateforme rattachées à une communauté).\n";
}
echo "Bootstrap plateforme OK (subscription_plans, tenants.*, RBAC 3 couches, community_invitations, moderation_*, security_*, community_code, referral_*…).\n";
$migrationFlush();

$tenantTypePath = $root . '/bootstrap/tenant_type_migration.php';
echo "Migration tenant_type (profil communauté Complet / Effectifs / ATAK)...\n";
$migrationFlush();
if (!is_file($tenantTypePath)) {
    echo "  [ATTENTION] Fichier absent : bootstrap/tenant_type_migration.php — déployez-le puis relancez (création de communauté bloquée sans tenants.tenant_type).\n";
} else {
    $tenantTypeMigrate = require $tenantTypePath;
    try {
        if (!is_callable($tenantTypeMigrate)) {
            throw new RuntimeException('bootstrap/tenant_type_migration.php doit retourner une callable');
        }
        $tenantTypeMigrate($pdo);
        // Confirmation finale lisible (OK / SKIP déjà loggés dans le bootstrap).
        $hasTt = false;
        try {
            $stTt = $pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'tenant_type' LIMIT 1"
            );
            $hasTt = $stTt !== false && (bool) $stTt->fetchColumn();
        } catch (Throwable) {
            $hasTt = false;
        }
        echo $hasTt
            ? "  [OK] tenant_type : colonne prête (création de communauté)\n"
            : "  [ATTENTION] tenant_type : colonne toujours absente après migration\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] tenant_type : ' . $e->getMessage() . "\n";
    }
}
$migrationFlush();

// Socle LMS moderne (training_courses, lessons, enrollments, quizzes…) : créé à partir de
// migrations/lms_training.sql si absent. Sans cette étape, une installation neuve n'a pas de
// table training_courses et le tableau de bord / la brique Formations plantent.
echo "Socle LMS moderne (training_courses, lessons, enrollments…)...\n";
$migrationFlush();
$lmsTrainingBaseMigrate = require $root . '/bootstrap/lms_training_base_migration.php';
try {
    $lmsTrainingBaseMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] socle LMS moderne : ' . $e->getMessage() . "\n";
}

// LMS formations : colonnes training_courses + tables engagement — exécuté tôt (idempotent). Anciennement en fin de fichier :
// si le script s’arrêtait avant (timeout, erreur), colonnes comme enrollment_policy_json manquaient en prod.
echo "Migrations LMS formation (training_courses, politique d’inscription, vitrine)...\n";
$migrationFlush();
$trainingCourseLmsThemeMigrateEarly = require $root . '/bootstrap/training_course_lms_theme_migration.php';
$trainingCourseLmsThemeMigrateEarly($pdo);
$trainingShowcaseMigrateEarly = require $root . '/bootstrap/training_showcase_migration.php';
$trainingShowcaseMigrateEarly($pdo);
$trainingLmsEngagementMigrateEarly = require $root . '/bootstrap/training_lms_engagement_migration.php';
try {
    $trainingLmsEngagementMigrateEarly($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_lms_engagement : ' . $e->getMessage() . "\n";
}
$trainingEnrollmentFeaturesMigrate = require $root . '/bootstrap/training_enrollment_features_migration.php';
try {
    $trainingEnrollmentFeaturesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_enrollment_features : ' . $e->getMessage() . "\n";
}
$trainingEnrollmentWithdrawnMigrate = require $root . '/bootstrap/training_enrollment_withdrawn_status_migration.php';
try {
    $trainingEnrollmentWithdrawnMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_enrollment_withdrawn : ' . $e->getMessage() . "\n";
}
$trainingCourseLmsPlatformVersionMigrate = require $root . '/bootstrap/training_course_lms_platform_version_migration.php';
try {
    $trainingCourseLmsPlatformVersionMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_course_lms_platform_version : ' . $e->getMessage() . "\n";
}
$trainingCoursesLmsScopeMigrate = require $root . '/bootstrap/training_courses_lms_scope_migration.php';
try {
    $trainingCoursesLmsScopeMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_courses_lms_scope : ' . $e->getMessage() . "\n";
}
$trainingPublicationEngineMigrate = require $root . '/bootstrap/training_publication_engine_migration.php';
try {
    $trainingPublicationEngineMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_publication_engine : ' . $e->getMessage() . "\n";
}
$trainingCertificateTemplatesMigrate = require $root . '/bootstrap/training_certificate_templates_migration.php';
try {
    $trainingCertificateTemplatesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_certificate_templates : ' . $e->getMessage() . "\n";
}
$trainingCompetencyFrameworkMigrate = require $root . '/bootstrap/training_competency_framework_migration.php';
try {
    $trainingCompetencyFrameworkMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_competency_framework : ' . $e->getMessage() . "\n";
}
$trainingFormationCustomPagesMigrate = require $root . '/bootstrap/training_formation_custom_pages_migration.php';
try {
    $trainingFormationCustomPagesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_formation_custom_pages : ' . $e->getMessage() . "\n";
}
$competencyProgressionFrameworkMigrate = require $root . '/bootstrap/competency_progression_framework_migration.php';
try {
    $competencyProgressionFrameworkMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] competency_progression_framework : ' . $e->getMessage() . "\n";
}
$personnelQualificationsTrainingLinkMigrate = require $root . '/bootstrap/personnel_qualifications_training_link_migration.php';
try {
    $personnelQualificationsTrainingLinkMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] personnel_qualifications_training_link : ' . $e->getMessage() . "\n";
}
$communityEventSlotQualificationMigrate = require $root . '/bootstrap/community_event_slot_qualification_migration.php';
try {
    $communityEventSlotQualificationMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_event_slot_qualification : ' . $e->getMessage() . "\n";
}
$trainingLessonPlayerModeMigrate = require $root . '/bootstrap/training_lesson_player_mode_migration.php';
try {
    $trainingLessonPlayerModeMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_lesson_player_mode : ' . $e->getMessage() . "\n";
}
$pedagogyChainMigrate = require $root . '/bootstrap/pedagogy_chain_migration.php';
try {
    $pedagogyChainMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] pedagogy_chain : ' . $e->getMessage() . "\n";
}

$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_modules'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration canaux de déploiement / communautés test (platform_modules, …)...\n";
    $migrationFlush();
    $platformReleasePath = $root . '/migrations/20260415000001_release_channels_and_tester_communities.sql';
    if (is_file($platformReleasePath)) {
        $sql = file_get_contents($platformReleasePath);
        if ($sql !== false && $sql !== '') {
            $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
            $sql = preg_replace('/SET NAMES utf8mb4;/', '', $sql);
            $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
            foreach ($chunks as $stmtSql) {
                $stmtSql = trim($stmtSql);
                if ($stmtSql !== '') {
                    try {
                        $pdo->exec($stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';'));
                    } catch (Throwable $e) {
                        echo '  [ATTENTION] release_channels_tester_communities : ' . $e->getMessage() . "\n";
                    }
                }
            }
            echo "  [OK] release_channels_tester_communities\n";
        }
    } else {
        echo "  [ATTENTION] Fichier absent : migrations/20260415000001_release_channels_and_tester_communities.sql\n";
    }
    require_once $root . '/bootstrap/platform_training_release_seed.php';
    try {
        run_platform_training_release_seed($pdo);
    } catch (Throwable $e) {
        echo '  [ATTENTION] platform_training_release_seed : ' . $e->getMessage() . "\n";
    }
    $migrationFlush();
}

$stmtPm = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_modules'");
if ($stmtPm && $stmtPm->fetch()) {
    require_once $root . '/bootstrap/platform_training_release_seed.php';
    try {
        run_platform_training_release_seed($pdo);
    } catch (Throwable $e) {
        echo '  [ATTENTION] platform_training_release_seed : ' . $e->getMessage() . "\n";
    }
    $migrationFlush();
}

$stmtDj = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deployment_jobs' LIMIT 1");
if ($stmtDj && $stmtDj->fetch()) {
    $stmtCampTbl = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deployment_campaigns' LIMIT 1");
    if (!$stmtCampTbl || !$stmtCampTbl->fetch()) {
        echo "Migration campagnes de déploiement (deployment_campaigns)...\n";
        $migrationFlush();
        $campSqlPath = $root . '/migrations/20260415120000_deployment_campaigns.sql';
        if (is_file($campSqlPath)) {
            $sql = file_get_contents($campSqlPath);
            if ($sql !== false && $sql !== '') {
                $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
                $sql = preg_replace('/SET NAMES utf8mb4;/', '', $sql);
                $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
                foreach ($chunks as $stmtSql) {
                    $stmtSql = trim($stmtSql);
                    if ($stmtSql !== '') {
                        try {
                            $pdo->exec($stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';'));
                        } catch (Throwable $e) {
                            echo '  [ATTENTION] deployment_campaigns : ' . $e->getMessage() . "\n";
                        }
                    }
                }
                echo "  [OK] deployment_campaigns\n";
            }
        } else {
            echo "  [ATTENTION] Fichier absent : migrations/20260415120000_deployment_campaigns.sql\n";
        }
        $migrationFlush();
    }
    $colCamp = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deployment_jobs' AND COLUMN_NAME = 'campaign_id' LIMIT 1");
    if ($colCamp && !$colCamp->fetch()) {
        echo "Migration deployment_jobs (campagnes : colonnes)...\n";
        $migrationFlush();
        try {
            $pdo->exec('ALTER TABLE deployment_jobs ADD COLUMN campaign_id BIGINT UNSIGNED NULL AFTER id');
        } catch (Throwable $e) {
            echo '  [ATTENTION] deployment_jobs.campaign_id : ' . $e->getMessage() . "\n";
        }
        try {
            $pdo->exec('ALTER TABLE deployment_jobs ADD COLUMN step_order SMALLINT UNSIGNED NULL AFTER target_channel_id');
        } catch (Throwable $e) {
            echo '  [ATTENTION] deployment_jobs.step_order : ' . $e->getMessage() . "\n";
        }
        try {
            $pdo->exec('ALTER TABLE deployment_jobs ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER log_path');
        } catch (Throwable $e) {
            echo '  [ATTENTION] deployment_jobs.created_at : ' . $e->getMessage() . "\n";
        }
        try {
            $pdo->exec('ALTER TABLE deployment_jobs ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
        } catch (Throwable $e) {
            echo '  [ATTENTION] deployment_jobs.updated_at : ' . $e->getMessage() . "\n";
        }
        try {
            $pdo->exec('ALTER TABLE deployment_jobs ADD COLUMN error_message TEXT NULL AFTER updated_at');
        } catch (Throwable $e) {
            echo '  [ATTENTION] deployment_jobs.error_message : ' . $e->getMessage() . "\n";
        }
        try {
            $pdo->exec('ALTER TABLE deployment_jobs ADD KEY idx_deployment_jobs_campaign (campaign_id)');
        } catch (Throwable $e) {
            echo '  [ATTENTION] deployment_jobs idx campaign : ' . $e->getMessage() . "\n";
        }
        try {
            $pdo->exec(
                'ALTER TABLE deployment_jobs ADD CONSTRAINT fk_deployment_jobs_campaign FOREIGN KEY (campaign_id) REFERENCES deployment_campaigns (id) ON DELETE CASCADE ON UPDATE CASCADE'
            );
        } catch (Throwable $e) {
            echo '  [ATTENTION] deployment_jobs FK campaign : ' . $e->getMessage() . "\n";
        }
        echo "  [OK] deployment_jobs colonnes campagne\n";
        $migrationFlush();
    }
}

$stmtAppUpd = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_app_releases' LIMIT 1");
if (!$stmtAppUpd || !$stmtAppUpd->fetch()) {
    echo "Migration mises à jour applicatives (platform_app_releases, …)...\n";
    $migrationFlush();
    $appUpdPath = $root . '/migrations/20260718000001_platform_app_updates.sql';
    if (is_file($appUpdPath)) {
        $sql = file_get_contents($appUpdPath);
        if ($sql !== false && $sql !== '') {
            $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
            $sql = preg_replace('/SET NAMES utf8mb4;/', '', $sql);
            $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
            foreach ($chunks as $stmtSql) {
                $stmtSql = trim($stmtSql);
                if ($stmtSql !== '') {
                    try {
                        $pdo->exec($stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';'));
                    } catch (Throwable $e) {
                        echo '  [ATTENTION] platform_app_updates : ' . $e->getMessage() . "\n";
                    }
                }
            }
            echo "  [OK] platform_app_updates\n";
        }
    } else {
        echo "  [ATTENTION] Fichier absent : migrations/20260718000001_platform_app_updates.sql\n";
    }
    $migrationFlush();
}

$usageAnalyticsMigrate = require $root . '/bootstrap/usage_analytics_migration.php';
try {
    $usageAnalyticsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] usage_analytics : ' . $e->getMessage() . "\n";
}
echo "Migrations LMS formation (première passe) OK.\n";
$migrationFlush();

require_once $root . '/bootstrap/training_onboarding_course_seed.php';
try {
    run_training_onboarding_course_seed($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_onboarding_course : ' . $e->getMessage() . "\n";
}

require_once $root . '/bootstrap/training_roles_org_course_seed.php';
try {
    run_training_roles_org_course_seed($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_roles_org_course : ' . $e->getMessage() . "\n";
}

require_once $root . '/bootstrap/training_bureau_recrutement_course_seed.php';
try {
    run_training_bureau_recrutement_course_seed($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_bureau_recrutement_course : ' . $e->getMessage() . "\n";
}

require_once $root . '/bootstrap/training_atak_course_seed.php';
try {
    run_training_atak_course_seed($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_atak_course : ' . $e->getMessage() . "\n";
}

// Pointage / RSVP : colonnes community_events + community_event_rsvps (idempotent si bootstrap déjà passé)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_events' AND COLUMN_NAME = 'cancelled_at'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration community_events (event_type, annulation)...\n";
    try {
        $pdo->exec("ALTER TABLE community_events ADD COLUMN event_type varchar(32) NOT NULL DEFAULT 'evenement' AFTER campaign_tag");
    } catch (Throwable) {
    }
    try {
        $pdo->exec("ALTER TABLE community_events ADD COLUMN cancelled_at datetime DEFAULT NULL AFTER updated_at");
        $pdo->exec("ALTER TABLE community_events ADD COLUMN cancelled_reason varchar(500) DEFAULT NULL AFTER cancelled_at");
    } catch (Throwable $e) {
        echo '  [ATTENTION] community_events annulation : ' . $e->getMessage() . "\n";
    }
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_event_rsvps' AND COLUMN_NAME = 'checked_in_at'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration community_event_rsvps (checked_in_at, reminder_sent_at)...\n";
    try {
        $pdo->exec("ALTER TABLE community_event_rsvps ADD COLUMN checked_in_at datetime DEFAULT NULL AFTER status");
        $pdo->exec("ALTER TABLE community_event_rsvps ADD COLUMN reminder_sent_at datetime DEFAULT NULL AFTER checked_in_at");
        $pdo->exec('ALTER TABLE community_event_rsvps ADD KEY idx_rsvp_reminder (event_id, reminder_sent_at)');
    } catch (Throwable $e) {
        echo '  [ATTENTION] community_event_rsvps pointage : ' . $e->getMessage() . "\n";
    }
}

// Enlistments : colonnes formulaire Olympus (si table existante sans ces colonnes)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'age'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout des colonnes formulaire Olympus à enlistments...\n";
    $alters = [
        "ALTER TABLE enlistments ADD COLUMN age smallint unsigned DEFAULT NULL AFTER notes",
        "ALTER TABLE enlistments ADD COLUMN timezone varchar(100) DEFAULT NULL AFTER age",
        "ALTER TABLE enlistments ADD COLUMN weekly_availability varchar(255) DEFAULT NULL AFTER timezone",
        "ALTER TABLE enlistments ADD COLUMN system_config varchar(500) DEFAULT NULL AFTER weekly_availability",
        "ALTER TABLE enlistments ADD COLUMN microphone_quality varchar(20) DEFAULT NULL AFTER system_config",
        "ALTER TABLE enlistments ADD COLUMN past_milsim_experience text AFTER microphone_quality",
        "ALTER TABLE enlistments ADD COLUMN ace_acre_level varchar(50) DEFAULT NULL AFTER past_milsim_experience",
        "ALTER TABLE enlistments ADD COLUMN motivation_why_join text AFTER ace_acre_level",
        "ALTER TABLE enlistments ADD COLUMN motivation_accountability text AFTER motivation_why_join",
        "ALTER TABLE enlistments ADD COLUMN commitment_effort varchar(20) DEFAULT NULL AFTER motivation_accountability",
        "ALTER TABLE enlistments ADD COLUMN availability_wed_sat varchar(20) DEFAULT NULL AFTER commitment_effort",
        "ALTER TABLE enlistments ADD COLUMN no_ai_confirmed tinyint(1) DEFAULT 0 AFTER availability_wed_sat",
    ];
    foreach ($alters as $alter) {
        $pdo->exec($alter);
    }
    echo "Colonnes Olympus OK.\n";
}

// Enlistments : étape de pipeline explicite (submitted/interview_scheduled/on_hold/accepted/rejected/blocked),
// en complément de `status` — n'affecte aucune logique existante basée sur `status`.
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'pipeline_stage'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout de la colonne pipeline_stage à enlistments...\n";
    $pdo->exec("ALTER TABLE enlistments ADD COLUMN pipeline_stage varchar(30) DEFAULT NULL AFTER status");
    $pdo->exec("ALTER TABLE enlistments ADD INDEX idx_enlistments_tenant_stage (tenant_id, pipeline_stage)");
    echo "pipeline_stage OK.\n";
}

// Profils de candidature + colonnes enrôlement (compte Athena, consentement)
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recruitment_presets'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table recruitment_presets...\n";
    $pdo->exec("CREATE TABLE `recruitment_presets` (
      `id` int unsigned NOT NULL AUTO_INCREMENT,
      `user_id` int unsigned NOT NULL,
      `label` varchar(120) NOT NULL,
      `payload` json NOT NULL,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      CONSTRAINT `recruitment_presets_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "recruitment_presets OK.\n";
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'submitted_via'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout colonnes compte / consentement à enlistments...\n";
    $pdo->exec("ALTER TABLE enlistments ADD COLUMN submitter_user_id int unsigned DEFAULT NULL AFTER reviewer_comment");
    $pdo->exec("ALTER TABLE enlistments ADD COLUMN recruitment_preset_id int unsigned DEFAULT NULL AFTER submitter_user_id");
    $pdo->exec("ALTER TABLE enlistments ADD COLUMN submitted_via varchar(20) NOT NULL DEFAULT 'guest' AFTER recruitment_preset_id");
    $pdo->exec("ALTER TABLE enlistments ADD COLUMN consent_sharing_at datetime DEFAULT NULL AFTER submitted_via");
    $pdo->exec("ALTER TABLE enlistments ADD COLUMN shared_fields json DEFAULT NULL AFTER consent_sharing_at");
    $pdo->exec("ALTER TABLE enlistments ADD KEY submitter_user_id (submitter_user_id)");
    $pdo->exec("ALTER TABLE enlistments ADD CONSTRAINT enlistments_submitter_user_fk FOREIGN KEY (submitter_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE");
    $pdo->exec("ALTER TABLE enlistments ADD CONSTRAINT enlistments_recruitment_preset_fk FOREIGN KEY (recruitment_preset_id) REFERENCES recruitment_presets (id) ON DELETE SET NULL ON UPDATE CASCADE");
    echo "Colonnes enrôlement compte OK.\n";
}

$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'recruitment_rp_json'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout colonne enlistments.recruitment_rp_json...\n";
    $pdo->exec("ALTER TABLE enlistments ADD COLUMN recruitment_rp_json JSON DEFAULT NULL AFTER shared_fields");
    echo "recruitment_rp_json OK.\n";
}

// Colonne nato_code sur grades (si absente)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades' AND COLUMN_NAME = 'nato_code'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout colonne grades.nato_code...\n";
    $pdo->exec("ALTER TABLE grades ADD COLUMN nato_code varchar(10) DEFAULT NULL");
}

// Colonne default_map_slug sur tenant_atak_config (si absente)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_atak_config' AND COLUMN_NAME = 'default_map_slug'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout colonne tenant_atak_config.default_map_slug...\n";
    $pdo->exec("ALTER TABLE tenant_atak_config ADD COLUMN default_map_slug varchar(50) DEFAULT 'altis' AFTER instructions");
}

// Table atak_intel (logs PING / CHAT / PHOTO depuis le mod Arma)
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_intel'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table atak_intel...\n";
    $pdo->exec("CREATE TABLE atak_intel (
        id int unsigned NOT NULL AUTO_INCREMENT,
        type varchar(20) NOT NULL,
        author varchar(255) NOT NULL,
        pos_x decimal(15,8) DEFAULT NULL,
        pos_y decimal(15,8) DEFAULT NULL,
        content text DEFAULT NULL,
        metadata json DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY type_created (type, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "atak_intel OK.\n";
}

// Tables ATAK live (parité Node, full PHP)
$atakLiveTables = [
    'atak_layers' => "CREATE TABLE atak_layers (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        label varchar(255) NOT NULL,
        phase int DEFAULT NULL, `order` int NOT NULL DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_layers_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_markers' => "CREATE TABLE atak_markers (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        layer_id int unsigned NOT NULL DEFAULT 1,
        marker_data text NOT NULL,
        arma_name varchar(255) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_markers_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_units' => "CREATE TABLE atak_units (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        call_sign varchar(255) NOT NULL,
        military_id varchar(32) DEFAULT NULL,
        role varchar(255) DEFAULT NULL,
        status varchar(50) DEFAULT 'linked',
        grid_ref varchar(100) DEFAULT NULL,
        heading decimal(10,4) DEFAULT NULL,
        pos_x decimal(15,4) DEFAULT NULL,
        pos_y decimal(15,4) DEFAULT NULL,
        extra json DEFAULT NULL,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY tenant_map (tenant_id, map_id), KEY map_callsign (map_id, call_sign),
        KEY idx_atak_units_tenant_military (tenant_id, military_id),
        CONSTRAINT atak_units_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_chat_messages' => "CREATE TABLE atak_chat_messages (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        author varchar(255) NOT NULL,
        body text NOT NULL,
        source varchar(16) NOT NULL DEFAULT 'game',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_chat_messages_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_pings' => "CREATE TABLE atak_pings (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        author varchar(255) NOT NULL,
        pos_x decimal(15,4) NOT NULL,
        pos_y decimal(15,4) NOT NULL,
        message text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_pings_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_nine_line' => "CREATE TABLE atak_nine_line (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        author varchar(255) NOT NULL,
        line1 varchar(255) DEFAULT NULL, line2 varchar(255) DEFAULT NULL, line3 varchar(255) DEFAULT NULL,
        line4 varchar(255) DEFAULT NULL, line5 varchar(255) DEFAULT NULL, line6 varchar(255) DEFAULT NULL,
        line7 varchar(255) DEFAULT NULL, line8 varchar(255) DEFAULT NULL, line9 text DEFAULT NULL,
        status varchar(50) DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_nine_line_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_intel_photos' => "CREATE TABLE atak_intel_photos (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        filename varchar(255) NOT NULL,
        path varchar(500) NOT NULL,
        author varchar(255) DEFAULT NULL,
        pos_x decimal(15,4) DEFAULT NULL,
        pos_y decimal(15,4) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_intel_photos_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_designator_targets' => "CREATE TABLE atak_designator_targets (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        call_sign varchar(255) NOT NULL,
        pos_x decimal(15,4) NOT NULL,
        pos_y decimal(15,4) NOT NULL,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY tenant_map_callsign (tenant_id, map_id, call_sign),
        CONSTRAINT atak_designator_targets_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_sigint_reports' => "CREATE TABLE atak_sigint_reports (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        call_sign varchar(255) NOT NULL,
        pos_x decimal(15,4) NOT NULL,
        pos_y decimal(15,4) NOT NULL,
        bearing decimal(10,4) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_sigint_reports_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_last_activity' => "CREATE TABLE atak_last_activity (
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        last_activity_at datetime NOT NULL,
        PRIMARY KEY (tenant_id, map_id),
        CONSTRAINT atak_last_activity_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_air_assets' => "CREATE TABLE atak_air_assets (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        callsign varchar(128) NOT NULL,
        model varchar(255) DEFAULT NULL,
        aircraft_type varchar(32) DEFAULT NULL,
        freq varchar(64) DEFAULT NULL,
        laser varchar(32) DEFAULT '1688',
        auth varchar(128) DEFAULT NULL,
        pos_x decimal(15,4) DEFAULT NULL,
        pos_y decimal(15,4) DEFAULT NULL,
        alt decimal(10,2) DEFAULT NULL,
        heading decimal(8,2) DEFAULT NULL,
        side varchar(16) DEFAULT 'WEST',
        status varchar(32) DEFAULT 'IN-FLIGHT',
        pilot_status varchar(32) DEFAULT NULL,
        aircraft_count int unsigned DEFAULT 1,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY tenant_map_callsign (tenant_id, map_id, callsign),
        KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_air_assets_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($atakLiveTables as $table => $createSql) {
    $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table'");
    if ($stmt && !$stmt->fetch()) {
        echo "Création table $table...\n";
        $pdo->exec($createSql);
        echo "$table OK.\n";
    }
}

// COMSPEC C2/CAS : colonnes atak_nine_line (mission_id, assigned_aircraft, lines_checked)
foreach (['mission_id' => "ADD COLUMN mission_id varchar(128) DEFAULT NULL AFTER map_id", 'assigned_aircraft' => "ADD COLUMN assigned_aircraft varchar(128) DEFAULT NULL AFTER author", 'lines_checked' => "ADD COLUMN lines_checked json DEFAULT NULL AFTER line9"] as $col => $alterFrag) {
    $stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_nine_line' AND COLUMN_NAME = '$col'");
    if ($stmt && !$stmt->fetch()) {
        echo "Ajout atak_nine_line.$col...\n";
        $pdo->exec("ALTER TABLE atak_nine_line $alterFrag");
    }
}

// COMSPEC C2/CAS : tables recon_images, atak_map_shapes, atak_laser_codes
$c2Tables = [
    'recon_images' => "CREATE TABLE recon_images (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        mission_id varchar(128) DEFAULT NULL,
        author_callsign varchar(128) NOT NULL,
        unit_name varchar(255) DEFAULT NULL,
        side varchar(16) DEFAULT 'WEST',
        image_path varchar(500) NOT NULL,
        thumb_path varchar(500) DEFAULT NULL,
        caption text DEFAULT NULL,
        pos_x decimal(15,4) DEFAULT NULL,
        pos_y decimal(15,4) DEFAULT NULL,
        pos_z decimal(15,4) DEFAULT NULL,
        grid_ref varchar(32) DEFAULT NULL,
        heading decimal(8,2) DEFAULT NULL,
        altitude decimal(10,2) DEFAULT NULL,
        device_type varchar(64) DEFAULT 'CTAB',
        captured_at datetime DEFAULT NULL,
        atak_cas_id int unsigned DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY tenant_mission (tenant_id, mission_id),
        KEY author_callsign (author_callsign),
        KEY captured_at (captured_at),
        CONSTRAINT recon_images_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_map_shapes' => "CREATE TABLE atak_map_shapes (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        mission_id varchar(128) DEFAULT NULL,
        shape_uid varchar(64) NOT NULL,
        type varchar(32) NOT NULL,
        label varchar(255) DEFAULT NULL,
        color varchar(32) DEFAULT '#3388ff',
        stroke int unsigned DEFAULT 2,
        fill_opacity decimal(3,2) DEFAULT 0.15,
        created_by varchar(128) DEFAULT NULL,
        visible_to json DEFAULT NULL,
        geometry json NOT NULL,
        meta json DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY tenant_map_uid (tenant_id, map_id, shape_uid),
        KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_map_shapes_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'atak_laser_codes' => "CREATE TABLE atak_laser_codes (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        map_id int unsigned NOT NULL DEFAULT 1,
        call_sign varchar(128) NOT NULL,
        laser_code varchar(32) NOT NULL,
        pos_x decimal(15,4) DEFAULT NULL,
        pos_y decimal(15,4) DEFAULT NULL,
        status varchar(32) DEFAULT 'ACTIVE',
        last_update bigint DEFAULT NULL,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY tenant_map_callsign (tenant_id, map_id, call_sign),
        KEY tenant_map (tenant_id, map_id),
        CONSTRAINT atak_laser_codes_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($c2Tables as $table => $createSql) {
    $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table'");
    if ($stmt && !$stmt->fetch()) {
        echo "Création table $table...\n";
        $pdo->exec($createSql);
        echo "$table OK.\n";
    }
}

// COMSPEC C2/CAS : colonnes atak_air_assets (flight manifest enrichi)
$airAssetCols = [
    'mission_id' => "ADD COLUMN mission_id varchar(128) DEFAULT NULL AFTER map_id",
    'radio_main' => "ADD COLUMN radio_main varchar(64) DEFAULT NULL AFTER freq",
    'radio_aux' => "ADD COLUMN radio_aux varchar(64) DEFAULT NULL AFTER radio_main",
    'auth_code' => "ADD COLUMN auth_code varchar(128) DEFAULT NULL AFTER auth",
    'pilot' => "ADD COLUMN pilot varchar(255) DEFAULT NULL AFTER auth_code",
    'crew' => "ADD COLUMN crew json DEFAULT NULL AFTER pilot",
    'fuel_pct' => "ADD COLUMN fuel_pct int unsigned DEFAULT NULL AFTER crew",
    'ordnance' => "ADD COLUMN ordnance json DEFAULT NULL AFTER fuel_pct",
    'station' => "ADD COLUMN station varchar(128) DEFAULT NULL AFTER ordnance",
    'eta_minutes' => "ADD COLUMN eta_minutes int unsigned DEFAULT NULL AFTER station",
    'bingo_fuel' => "ADD COLUMN bingo_fuel varchar(32) DEFAULT NULL AFTER eta_minutes",
    'checklist' => "ADD COLUMN checklist json DEFAULT NULL AFTER bingo_fuel",
    'pos_z' => "ADD COLUMN pos_z decimal(15,4) DEFAULT NULL AFTER pos_y",
    'last_update' => "ADD COLUMN last_update bigint DEFAULT NULL AFTER aircraft_count",
    'source' => "ADD COLUMN source varchar(32) DEFAULT NULL AFTER last_update",
    'vehicle_id' => "ADD COLUMN vehicle_id varchar(64) DEFAULT NULL AFTER source",
];
foreach ($airAssetCols as $col => $alterFrag) {
    $stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_air_assets' AND COLUMN_NAME = '$col'");
    if ($stmt && !$stmt->fetch()) {
        echo "Ajout atak_air_assets.$col...\n";
        $pdo->exec("ALTER TABLE atak_air_assets $alterFrag");
    }
}

// Colonne steam_id sur users (liaison Steam ATAK)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'steam_id'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout colonne users.steam_id...\n";
    $pdo->exec("ALTER TABLE users ADD COLUMN steam_id varchar(20) DEFAULT NULL AFTER callsign");
}

// Colonne arma_callsign sur user_profiles (liaison Arma ATAK)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profiles' AND COLUMN_NAME = 'arma_callsign'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout colonne user_profiles.arma_callsign...\n";
    $pdo->exec("ALTER TABLE user_profiles ADD COLUMN arma_callsign varchar(100) DEFAULT NULL AFTER language");
}

// Colonne is_locked sur roles (rôles système non modifiables par admin org)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'is_locked'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout colonne roles.is_locked...\n";
    $pdo->exec("ALTER TABLE roles ADD COLUMN is_locked tinyint(1) DEFAULT 0 AFTER is_system");
}

// Table categories (rôles, utilisateurs, organisation, métier)
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table categories...\n";
    $pdo->exec("CREATE TABLE categories (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        type varchar(50) NOT NULL DEFAULT 'organizational',
        name varchar(255) NOT NULL,
        slug varchar(100) NOT NULL,
        description varchar(500) DEFAULT NULL,
        color varchar(50) DEFAULT NULL,
        display_order int DEFAULT 0,
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY tenant_id_slug (tenant_id, slug),
        KEY tenant_id (tenant_id),
        CONSTRAINT categories_tenant_id_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Dossier personnel opérationnel (personnel_profiles, qualifications, assignments, service_history, media)
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_profiles'");
if ($stmt && !$stmt->fetch()) {
    echo "Création tables dossier personnel (personnel_profiles, personnel_qualifications, etc.)...\n";
    $personnelDossierPath = $root . '/migrations/personnel_dossier.sql';
    if (is_file($personnelDossierPath)) {
        $sql = file_get_contents($personnelDossierPath);
        $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
        $sql = preg_replace('/SET NAMES utf8mb4;|SET FOREIGN_KEY_CHECKS = \d+;/', '', $sql);
        $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
        foreach ($chunks as $stmtSql) {
            $stmtSql = trim($stmtSql);
            if ($stmtSql !== '') {
                $pdo->exec($stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';'));
            }
        }
        echo "Dossier personnel OK.\n";
        // Migration des données existantes : personnel_extras -> personnel_profiles, user_units -> personnel_assignments
        $pdo->exec("INSERT IGNORE INTO personnel_profiles (user_id, matricule_internal, enlistment_date, clearance_level, readiness_score, command_notes, created_at, updated_at)
            SELECT user_id, service_number, date_of_enlistment, clearance_level, COALESCE(readiness_percent, 0), admin_notes, created_at, updated_at
            FROM personnel_extras");
        $pdo->exec("INSERT INTO personnel_assignments (user_id, unit_id, role_name, is_primary, started_at, ended_at, status)
            SELECT user_id, unit_id, COALESCE(NULLIF(TRIM(assignment_type), ''), 'Membre'), is_primary, assigned_at, ended_at,
            CASE WHEN ended_at IS NULL OR ended_at > NOW() THEN 'active' ELSE 'inactive' END
            FROM user_units");
        echo "Migration données personnel OK.\n";
    }
}

// Préférences affichage forum / fiche (pseudo forum, visibilité)
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profile_display_settings'");
if ($stmt && !$stmt->fetch()) {
    echo "Création user_profile_display_settings...\n";
    $udsPath = $root . '/migrations/user_profile_display_settings.sql';
    if (is_file($udsPath)) {
        $sql = file_get_contents($udsPath);
        $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
        $sql = preg_replace('/SET NAMES utf8mb4;/', '', $sql);
        $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
        foreach ($chunks as $stmtSql) {
            $stmtSql = trim($stmtSql);
            if ($stmtSql !== '') {
                $pdo->exec($stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';'));
            }
        }
        echo "user_profile_display_settings OK.\n";
    }
}

// Fiche publique vitrine : roster opt-in + champs unité
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profile_display_settings' AND COLUMN_NAME = 'public_roster_opt_in'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration public_roster_opt_in (user_profile_display_settings)...\n";
    $pdo->exec("ALTER TABLE user_profile_display_settings ADD COLUMN public_roster_opt_in tinyint(1) NOT NULL DEFAULT 0 AFTER fiche_show_matricule_to_others");
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profile_display_settings' AND COLUMN_NAME = 'hide_personal_info'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration hide_personal_info (user_profile_display_settings)...\n";
    try {
        $pdo->exec("ALTER TABLE user_profile_display_settings ADD COLUMN hide_personal_info tinyint(1) NOT NULL DEFAULT 0 AFTER public_roster_opt_in");
    } catch (Throwable $e) {
        echo '  [ATTENTION] hide_personal_info : ' . $e->getMessage() . "\n";
    }
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profile_display_settings' AND COLUMN_NAME = 'hide_forum_level'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration hide_forum_level (user_profile_display_settings)...\n";
    try {
        $pdo->exec("ALTER TABLE user_profile_display_settings ADD COLUMN hide_forum_level tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = masquer LVL sur carte forum' AFTER show_bio_forum");
    } catch (Throwable $e) {
        echo '  [ATTENTION] hide_forum_level : ' . $e->getMessage() . "\n";
    }
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profile_display_settings' AND COLUMN_NAME = 'forum_visible_role_id'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration forum_visible_role_id (user_profile_display_settings)...\n";
    try {
        $pdo->exec("ALTER TABLE user_profile_display_settings ADD COLUMN forum_visible_role_id int unsigned DEFAULT NULL COMMENT 'Rôle org affiché sur carte forum (NULL = rôle principal du compte)' AFTER forum_label_mode");
    } catch (Throwable $e) {
        echo '  [ATTENTION] forum_visible_role_id : ' . $e->getMessage() . "\n";
    }
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profile_display_settings' AND COLUMN_NAME = 'site_photo_priority'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration site_photo_priority (user_profile_display_settings)...\n";
    try {
        $pdo->exec("ALTER TABLE user_profile_display_settings ADD COLUMN site_photo_priority varchar(16) NOT NULL DEFAULT 'operator' COMMENT 'operator|account — photo prioritaire header / portail' AFTER hide_personal_info");
    } catch (Throwable $e) {
        echo '  [ATTENTION] site_photo_priority : ' . $e->getMessage() . "\n";
    }
}

$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_topics' AND COLUMN_NAME = 'is_official'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration forum_topics is_official / auto_locked_at / suppress_auto_lock...\n";
    try {
        $pdo->exec("ALTER TABLE forum_topics ADD COLUMN is_official tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Communiqué officiel (modo)' AFTER is_hidden");
    } catch (Throwable $e) {
        echo '  [ATTENTION] is_official : ' . $e->getMessage() . "\n";
    }
    try {
        $pdo->exec("ALTER TABLE forum_topics ADD COLUMN auto_locked_at datetime DEFAULT NULL COMMENT 'Verrouillage auto 6 mois' AFTER updated_at");
    } catch (Throwable $e) {
        echo '  [ATTENTION] auto_locked_at : ' . $e->getMessage() . "\n";
    }
    try {
        $pdo->exec("ALTER TABLE forum_topics ADD COLUMN suppress_auto_lock tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = déverrouillage manuel, ne pas reverrouiller auto' AFTER auto_locked_at");
    } catch (Throwable $e) {
        echo '  [ATTENTION] suppress_auto_lock : ' . $e->getMessage() . "\n";
    }
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'units' AND COLUMN_NAME = 'public_blurb'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration units public_blurb / public_tags / show_on_public_page...\n";
    $pdo->exec("ALTER TABLE units ADD COLUMN public_blurb text DEFAULT NULL AFTER display_order");
    $pdo->exec("ALTER TABLE units ADD COLUMN public_tags json DEFAULT NULL AFTER public_blurb");
    $pdo->exec("ALTER TABLE units ADD COLUMN show_on_public_page tinyint(1) NOT NULL DEFAULT 1 AFTER public_tags");
}

// Seed / upsert atak_maps (théâtres Arma via CDN jetelain/Arma3Map)
$atakTileCdn = rtrim((string) (getenv('ATAK_MAP_TILES_CDN') ?: 'https://jetelain.github.io/Arma3Map'), '/');
$atakMapsSeed = [
    [
        'slug' => 'altis',
        'label' => 'Altis',
        'world_name' => 'altis',
        'display_order' => 0,
        'factorx' => 0.006839,
        'factory' => 0.006836,
        'tileSize' => 212,
        'worldSize' => 30720,
        'center' => [15000, 15000],
        'defaultZoom' => 3,
        'maxZoom' => 6,
        'attribution' => '&copy; Bohemia Interactive',
    ],
    [
        'slug' => 'stratis',
        'label' => 'Stratis',
        'world_name' => 'stratis',
        'display_order' => 1,
        'factorx' => 0.027475,
        'factory' => 0.027475,
        'tileSize' => 226,
        'worldSize' => 8192,
        'center' => [4100, 4100],
        'defaultZoom' => 2,
        'maxZoom' => 4,
        'attribution' => '&copy; Bohemia Interactive',
    ],
    [
        'slug' => 'malden',
        'label' => 'Malden',
        'world_name' => 'malden',
        'display_order' => 2,
        'factorx' => 0.01448,
        'factory' => 0.01448,
        'tileSize' => 186,
        'worldSize' => 12800,
        'center' => [7000, 7000],
        'defaultZoom' => 2,
        'maxZoom' => 5,
        'attribution' => '&copy; Bohemia Interactive',
    ],
    [
        'slug' => 'tanoa',
        'label' => 'Tanoa',
        'world_name' => 'tanoa',
        'display_order' => 3,
        'factorx' => 0.01385,
        'factory' => 0.01385,
        'tileSize' => 213,
        'worldSize' => 15360,
        'center' => [7000, 7000],
        'defaultZoom' => 2,
        'maxZoom' => 5,
        'attribution' => '&copy; Bohemia Interactive',
    ],
    [
        'slug' => 'enoch',
        'label' => 'Livonia',
        'world_name' => 'enoch',
        'display_order' => 4,
        'factorx' => 0.02735,
        'factory' => 0.02735,
        'tileSize' => 356,
        'worldSize' => 12800,
        'center' => [7100, 7100],
        'defaultZoom' => 2,
        'maxZoom' => 4,
        'attribution' => '&copy; Bohemia Interactive',
    ],
    [
        'slug' => 'chernarus',
        'label' => 'Chernarus',
        'world_name' => 'chernarus',
        'display_order' => 5,
        'factorx' => 0.01575,
        'factory' => 0.01575,
        'tileSize' => 242,
        'worldSize' => 15360,
        'center' => [7680, 7680],
        'defaultZoom' => 2,
        'maxZoom' => 5,
        'attribution' => '&copy; Bohemia Interactive, CUP Team',
    ],
    [
        'slug' => 'takistan',
        'label' => 'Takistan',
        'world_name' => 'takistan',
        'display_order' => 6,
        'factorx' => 0.01575,
        'factory' => 0.01575,
        'tileSize' => 202,
        'worldSize' => 12800,
        'center' => [6400, 6400],
        'defaultZoom' => 2,
        'maxZoom' => 5,
        'attribution' => '&copy; Bohemia Interactive, CUP Team',
    ],
    [
        // GameMapStorage (successeur Arma3Map) : pas de pyramide sur GitHub Pages.
        // JS officiel : https://atlas.plan-ops.fr/data/1/maps/sze_kimmirut.js
        'slug' => 'sze_kimmirut',
        'label' => 'Kimmirut',
        'world_name' => 'sze_kimmirut',
        'display_order' => 7,
        'factorx' => 0.01575,
        'factory' => 0.01575,
        'tileSize' => 323,
        'worldSize' => 20480,
        'center' => [10240, 10240],
        'defaultZoom' => 2,
        'maxZoom' => 6,
        'attribution' => '&copy; Bohemia Interactive, szephi',
        'tile_cdn' => 'https://atlas.plan-ops.fr/data/1',
        'tile_path' => 'maps/107/107/{z}/{x}/{y}.png',
    ],
];
try {
    $chkMap = $pdo->prepare('SELECT id, tile_pattern FROM atak_maps WHERE ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
    $insMap = $pdo->prepare('INSERT INTO atak_maps (slug, label, world_name, tile_pattern, config, display_order) VALUES (?, ?, ?, ?, ?, ?)');
    $updMap = $pdo->prepare('UPDATE atak_maps SET label = ?, world_name = ?, tile_pattern = ?, config = ?, display_order = ? WHERE ' . \App\Support\SqlText::equals($pdo, 'slug'));
    $seeded = 0;
    $updated = 0;
    foreach ($atakMapsSeed as $row) {
        $slug = (string) $row['slug'];
        $ws = (int) $row['worldSize'];
        $ts = (int) $row['tileSize'];
        $mapCdn = rtrim((string) ($row['tile_cdn'] ?? $atakTileCdn), '/');
        $mapPath = ltrim((string) ($row['tile_path'] ?? ('maps/' . $slug . '/{z}/{x}/{y}.png')), '/');
        $tilePattern = $mapCdn . '/' . $mapPath;
        $hasCustomTiles = isset($row['tile_cdn']) || isset($row['tile_path']);
        $configPayload = [
            'center' => $row['center'],
            'defaultZoom' => (int) $row['defaultZoom'],
            'minZoom' => 0,
            'maxZoom' => (int) $row['maxZoom'],
            'tileSize' => $ts,
            'worldSize' => $ws,
            'bounds' => [[0, 0], [$ws, $ws]],
            'crs' => [
                'factorx' => (float) $row['factorx'],
                'factory' => (float) $row['factory'],
                'tileWidth' => $ts,
            ],
            'attribution' => (string) $row['attribution'],
            'title' => (string) $row['label'],
        ];
        $configJson = json_encode($configPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $chkMap->execute([$slug]);
        $existing = $chkMap->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            $insMap->execute([
                $slug,
                (string) $row['label'],
                (string) $row['world_name'],
                $tilePattern,
                $configJson,
                (int) $row['display_order'],
            ]);
            $seeded++;
            continue;
        }
        $oldPattern = trim((string) ($existing['tile_pattern'] ?? ''));
        $forceCdn = $oldPattern === ''
            || str_contains($oldPattern, '/assets/maps/')
            || str_contains($oldPattern, 'assets/maps/')
            || str_contains($oldPattern, 'ressources/MapViewers/')
            || !str_contains($oldPattern, '/maps/' . $slug . '/');
        $nextPattern = ($forceCdn || $hasCustomTiles) ? $tilePattern : $oldPattern;
        $updMap->execute([
            (string) $row['label'],
            (string) $row['world_name'],
            $nextPattern,
            $configJson,
            (int) $row['display_order'],
            $slug,
        ]);
        $updated++;
    }
    if ($seeded > 0 || $updated > 0) {
        echo "atak_maps seed/upsert : +{$seeded} insert, {$updated} maj (CDN {$atakTileCdn}).\n";
    }
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_maps seed : ' . $e->getMessage() . "\n";
}

// Documents Athena : colonnes et tables manquantes (migration incrémentale)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_categories' AND COLUMN_NAME = 'color'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout document_categories.color...\n";
    $pdo->exec("ALTER TABLE document_categories ADD COLUMN color varchar(50) DEFAULT NULL AFTER slug");
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'description'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout documents.description...\n";
    $pdo->exec("ALTER TABLE documents ADD COLUMN description text DEFAULT NULL AFTER slug");
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_versions' AND COLUMN_NAME = 'version_number'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout document_versions.version_number...\n";
    $pdo->exec("ALTER TABLE document_versions ADD COLUMN version_number int unsigned NOT NULL DEFAULT 1 AFTER document_id");
}
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipment_classes'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table equipment_classes...\n";
    $pdo->exec("CREATE TABLE equipment_classes (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        name varchar(255) NOT NULL,
        slug varchar(100) NOT NULL,
        category varchar(100) DEFAULT NULL,
        description text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY tenant_id_slug (tenant_id, slug),
        KEY tenant_id (tenant_id),
        CONSTRAINT equipment_classes_tenant_id_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_links'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table document_links...\n";
    $pdo->exec("CREATE TABLE document_links (
        id int unsigned NOT NULL AUTO_INCREMENT,
        tenant_id int unsigned NOT NULL,
        document_id int unsigned NOT NULL,
        entity_type enum('training','equipment_class','unit','user') NOT NULL,
        entity_id int unsigned NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY document_id (document_id),
        KEY entity_type (entity_type),
        KEY entity_id (entity_id),
        KEY tenant_id (tenant_id),
        CONSTRAINT document_links_tenant_id_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT document_links_document_id_fk FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Refonte module documentaire : colonnes et tables (si classification_level absente)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'classification_level'");
if ($stmt && !$stmt->fetch()) {
    echo "Refonte module documentaire : extension documents...\n";
    $pdo->exec("ALTER TABLE documents
        ADD COLUMN uuid CHAR(36) NULL UNIQUE AFTER id,
        ADD COLUMN short_description VARCHAR(500) NULL AFTER slug,
        ADD COLUMN document_type VARCHAR(100) NULL AFTER short_description,
        ADD COLUMN classification_level VARCHAR(50) NOT NULL DEFAULT 'interne' AFTER document_category_id,
        ADD COLUMN visibility_scope VARCHAR(50) NOT NULL DEFAULT 'private' AFTER classification_level,
        ADD COLUMN owner_user_id INT UNSIGNED NULL AFTER visibility_scope,
        ADD COLUMN author_user_id INT UNSIGNED NULL AFTER owner_user_id,
        ADD COLUMN parent_document_id INT UNSIGNED NULL AFTER author_user_id,
        ADD COLUMN relation_type VARCHAR(50) NULL AFTER parent_document_id,
        ADD COLUMN version_label VARCHAR(50) NULL AFTER relation_type,
        ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER version_label,
        ADD COLUMN current_file_id INT UNSIGNED NULL AFTER sort_order,
        ADD COLUMN formation_id INT UNSIGNED NULL AFTER current_file_id,
        ADD COLUMN equipment_class_id INT UNSIGNED NULL AFTER formation_id,
        ADD COLUMN unit_id INT UNSIGNED NULL AFTER equipment_class_id,
        ADD COLUMN operator_id INT UNSIGNED NULL AFTER unit_id,
        ADD COLUMN mission_id VARCHAR(128) NULL AFTER operator_id,
        ADD COLUMN effective_at DATETIME NULL AFTER mission_id,
        ADD COLUMN review_due_at DATETIME NULL AFTER effective_at,
        ADD COLUMN expires_at DATETIME NULL AFTER review_due_at,
        ADD COLUMN download_allowed TINYINT(1) NOT NULL DEFAULT 1 AFTER expires_at,
        ADD COLUMN print_allowed TINYINT(1) NOT NULL DEFAULT 1 AFTER download_allowed,
        ADD COLUMN locked TINYINT(1) NOT NULL DEFAULT 0 AFTER print_allowed,
        ADD COLUMN tags JSON NULL AFTER locked,
        ADD COLUMN inherit_parent_security TINYINT(1) NOT NULL DEFAULT 0 AFTER tags,
        ADD INDEX idx_documents_status (status),
        ADD INDEX idx_documents_owner (owner_user_id),
        ADD INDEX idx_documents_parent (parent_document_id),
        ADD INDEX idx_documents_classification (classification_level),
        ADD INDEX idx_documents_visibility (visibility_scope)");
    $pdo->exec("UPDATE documents SET owner_user_id = created_by WHERE owner_user_id IS NULL AND created_by IS NOT NULL");
    $pdo->exec("UPDATE documents SET author_user_id = created_by WHERE author_user_id IS NULL AND created_by IS NOT NULL");
    $stmt2 = $pdo->query("SELECT id FROM documents WHERE uuid IS NULL LIMIT 1");
    if ($stmt2 && $stmt2->fetch()) {
        $pdo->exec("UPDATE documents SET uuid = LOWER(UUID()) WHERE uuid IS NULL");
    }
    try {
        $pdo->exec("ALTER TABLE documents ADD CONSTRAINT documents_owner_user_id_fk FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE");
    } catch (Throwable $e) { /* ignore if exists */ }
    try {
        $pdo->exec("ALTER TABLE documents ADD CONSTRAINT documents_author_user_id_fk FOREIGN KEY (author_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE");
    } catch (Throwable $e) { /* ignore */ }
    try {
        $pdo->exec("ALTER TABLE documents ADD CONSTRAINT documents_parent_document_id_fk FOREIGN KEY (parent_document_id) REFERENCES documents (id) ON DELETE SET NULL ON UPDATE CASCADE");
    } catch (Throwable $e) { /* ignore */ }
    try {
        $pdo->exec("ALTER TABLE documents ADD CONSTRAINT documents_current_file_id_fk FOREIGN KEY (current_file_id) REFERENCES document_versions (id) ON DELETE SET NULL ON UPDATE CASCADE");
    } catch (Throwable $e) { /* ignore */ }
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_versions' AND COLUMN_NAME = 'original_name'");
if ($stmt && !$stmt->fetch()) {
    echo "Refonte module documentaire : extension document_versions...\n";
    $pdo->exec("ALTER TABLE document_versions ADD COLUMN original_name VARCHAR(255) NULL AFTER file_path, ADD COLUMN version_label VARCHAR(50) NULL AFTER change_notes");
}
$stmt = $pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_versions' AND COLUMN_NAME = 'file_path'");
$filePathNullable = $stmt ? $stmt->fetchColumn() : null;
if (is_string($filePathNullable) && strtoupper($filePathNullable) === 'NO') {
    echo "document_versions.file_path : NULL autorisé (retrait de pièce jointe)...\n";
    try {
        $pdo->exec('ALTER TABLE document_versions MODIFY file_path VARCHAR(500) NULL');
        echo "  [OK] document_versions.file_path nullable\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] document_versions.file_path nullable : ' . $e->getMessage() . "\n";
    }
}
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_collaborators'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table document_collaborators...\n";
    $pdo->exec("CREATE TABLE document_collaborators (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        document_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        role VARCHAR(50) NOT NULL,
        granted_by INT UNSIGNED NULL,
        granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_document_collaborator (document_id, user_id, role),
        KEY idx_document_collaborators_user (user_id),
        CONSTRAINT fk_document_collaborators_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
        CONSTRAINT fk_document_collaborators_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_permissions'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table document_permissions...\n";
    $pdo->exec("CREATE TABLE document_permissions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        document_id INT UNSIGNED NOT NULL,
        permission_type VARCHAR(50) NOT NULL,
        permission_value VARCHAR(190) NOT NULL,
        access_level VARCHAR(50) NOT NULL DEFAULT 'read',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_document_permissions_lookup (permission_type, permission_value, access_level),
        CONSTRAINT fk_document_permissions_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_relations'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table document_relations...\n";
    $pdo->exec("CREATE TABLE document_relations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        parent_document_id INT UNSIGNED NOT NULL,
        child_document_id INT UNSIGNED NOT NULL,
        relation_type VARCHAR(50) NOT NULL,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_document_relation (parent_document_id, child_document_id, relation_type),
        CONSTRAINT fk_document_relations_parent FOREIGN KEY (parent_document_id) REFERENCES documents (id) ON DELETE CASCADE,
        CONSTRAINT fk_document_relations_child FOREIGN KEY (child_document_id) REFERENCES documents (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_audit_log'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table document_audit_log...\n";
    $pdo->exec("CREATE TABLE document_audit_log (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        document_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NULL,
        action VARCHAR(100) NOT NULL,
        old_value JSON NULL,
        new_value JSON NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_document_audit_log_document (document_id),
        KEY idx_document_audit_log_user (user_id),
        CONSTRAINT fk_document_audit_log_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Module Bureau Courrier : tables document_presets, document_templates, courrier_documents, etc.
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_presets'");
if ($stmt && !$stmt->fetch()) {
    echo "Création tables module Courrier (document_presets, document_templates, courrier_documents, etc.)...\n";
    $courrierPath = $root . '/migrations/courrier_module.sql';
    if (is_file($courrierPath)) {
        $sql = file_get_contents($courrierPath);
        $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
        $sql = preg_replace('/SET NAMES utf8mb4;|SET FOREIGN_KEY_CHECKS = \d+;/', '', $sql);
        $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
        foreach ($chunks as $stmtSql) {
            $stmtSql = trim($stmtSql);
            if ($stmtSql !== '') {
                try {
                    $pdo->exec($stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';'));
                } catch (PDOException $e) {
                    echo "  [ATTENTION] " . $e->getMessage() . "\n";
                }
            }
        }
        echo "Module Courrier tables OK.\n";
    }
}

// Référentiel grades multi-doctrine : grade_categories, grade_systems, grades_referentiel
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grade_categories'");
if ($stmt && !$stmt->fetch()) {
    echo "Création tables référentiel grades (grade_categories, grade_systems, grades_referentiel)...\n";
    $gradeRefPath = $root . '/migrations/grade_referentiel.sql';
    if (is_file($gradeRefPath)) {
        $sql = file_get_contents($gradeRefPath);
        $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
        $sql = preg_replace('/SET NAMES utf8mb4;|SET FOREIGN_KEY_CHECKS = \d+;/', '', $sql);
        $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
        foreach ($chunks as $stmtSql) {
            $stmtSql = trim($stmtSql);
            if ($stmtSql !== '') {
                try {
                    $pdo->exec($stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';'));
                } catch (PDOException $e) {
                    echo "  [ATTENTION] " . $e->getMessage() . "\n";
                }
            }
        }
        echo "Référentiel grades tables OK.\n";
    }
}

// Seed référentiel grades : catégories, systèmes, grades FR/US minimal (si grade_categories vide)
$stmt = $pdo->query("SELECT 1 FROM grade_categories LIMIT 1");
if ($stmt && !$stmt->fetch()) {
    echo "Seed référentiel grades (catégories, systèmes, grades FR/US)...\n";
    $now = date('Y-m-d H:i:s');
    $pdo->exec("INSERT INTO grade_categories (code, label, sort_order, is_active, created_at, updated_at) VALUES
        ('OFFICIER', 'Officier', 10, 1, '$now', '$now'),
        ('SOUS_OFFICIER', 'Sous-officier', 20, 1, '$now', '$now'),
        ('MDR', 'Militaire du rang', 30, 1, '$now', '$now'),
        ('CIVIL', 'Civil', 40, 1, '$now', '$now'),
        ('HORS_GRADE', 'Hors grades', 50, 1, '$now', '$now')");
    $pdo->exec("INSERT INTO grade_systems (code, label, country_code, format_type, is_active, created_at, updated_at) VALUES
        ('FR_CLASSIC', 'Grades français (classique)', 'FR', 'classic', 1, '$now', '$now'),
        ('US_CLASSIC', 'Grades américains (classique)', 'US', 'classic', 1, '$now', '$now')");
    $cat = [];
    foreach ($pdo->query("SELECT id, code FROM grade_categories")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cat[$r['code']] = (int) $r['id'];
    }
    $sys = [];
    foreach ($pdo->query("SELECT id, code FROM grade_systems")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $sys[$r['code']] = (int) $r['id'];
    }
    $insGrade = $pdo->prepare("INSERT INTO grades_referentiel (grade_system_id, grade_category_id, code, label_short, label_long, label_otan, sort_order, is_commissioned, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, '$now', '$now')");
    $gradesFr = [
        [1, 'SL', 'Sous-lieutenant', 'Sous-lieutenant', 'OF-1', 11, 1],
        [2, 'LT', 'Lieutenant', 'Lieutenant', 'OF-1', 12, 1],
        [3, 'CNE', 'Capitaine', 'Capitaine', 'OF-2', 13, 1],
        [4, 'CDT', 'Commandant', 'Commandant', 'OF-3', 14, 1],
        [5, 'LCL', 'Lieutenant-colonel', 'Lieutenant-colonel', 'OF-4', 15, 1],
        [6, 'COL', 'Colonel', 'Colonel', 'OF-5', 16, 1],
    ];
    foreach ($gradesFr as $g) {
        $insGrade->execute([$sys['FR_CLASSIC'], $cat['OFFICIER'], $g[1], $g[2], $g[3], $g[4], $g[5], $g[6]]);
    }
    $gradesUs = [
        [1, '2LT', 'Second Lieutenant', 'Second Lieutenant', 'O-1', 11, 1],
        [2, '1LT', 'First Lieutenant', 'First Lieutenant', 'O-2', 12, 1],
        [3, 'CPT', 'Captain', 'Captain', 'O-3', 13, 1],
        [4, 'MAJ', 'Major', 'Major', 'O-4', 14, 1],
        [5, 'LTC', 'Lieutenant Colonel', 'Lieutenant Colonel', 'O-5', 15, 1],
        [6, 'COL', 'Colonel', 'Colonel', 'O-6', 16, 1],
    ];
    foreach ($gradesUs as $g) {
        $insGrade->execute([$sys['US_CLASSIC'], $cat['OFFICIER'], $g[1], $g[2], $g[3], $g[4], $g[5], $g[6]]);
    }
    echo "Seed référentiel grades OK.\n";
}

// Overrides grades par tenant
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_grade_overrides'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table tenant_grade_overrides...\n";
    $pdo->exec("CREATE TABLE `tenant_grade_overrides` (
      `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `tenant_id` int(10) UNSIGNED NOT NULL,
      `grade_id` bigint(20) UNSIGNED NOT NULL,
      `label_short_override` varchar(100) DEFAULT NULL,
      `label_long_override` varchar(150) DEFAULT NULL,
      `sort_order_override` int(11) DEFAULT NULL,
      `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `tenant_grade` (`tenant_id`,`grade_id`),
      KEY `tenant_id` (`tenant_id`),
      KEY `grade_id` (`grade_id`),
      CONSTRAINT `tenant_grade_overrides_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Table tenant_grade_overrides OK.\n";
}

// Users : colonnes référentiel grades (nationalité, format préféré, catégorie pro)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'preferred_grade_format'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout colonnes users (nationality_code, preferred_grade_format, professional_category_code)...\n";
    $pdo->exec("ALTER TABLE users ADD COLUMN nationality_code VARCHAR(10) DEFAULT NULL AFTER email");
    $pdo->exec("ALTER TABLE users ADD COLUMN preferred_grade_format ENUM('classic','otan','hybrid') NOT NULL DEFAULT 'classic' AFTER nationality_code");
    $pdo->exec("ALTER TABLE users ADD COLUMN professional_category_code VARCHAR(50) DEFAULT NULL AFTER grade_id");
    echo "Colonnes users référentiel grades OK.\n";
}

// Bascule grades : ancienne table -> référentiel (mapping par libellé), renommage tables
$hasOldGrades = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades' LIMIT 1")->fetch();
$hasRef = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades_referentiel' LIMIT 1")->fetch();
$oldGradesHasTenant = $hasOldGrades && $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades' AND COLUMN_NAME = 'tenant_id' LIMIT 1")->fetch();
if ($hasOldGrades && $hasRef && $oldGradesHasTenant) {
    echo "Bascule grades (ancienne table -> référentiel)...\n";
    $frSystem = $pdo->query("SELECT id FROM grade_systems WHERE code = 'FR_CLASSIC' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $frSystemId = $frSystem ? (int) $frSystem['id'] : null;
    $oldRows = $pdo->query("SELECT id, name, short_name FROM grades")->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    if ($frSystemId) {
        $stmtMap = $pdo->prepare("SELECT id FROM grades_referentiel WHERE grade_system_id = ? AND (label_long = ? OR label_short = ?) LIMIT 1");
        foreach ($oldRows as $o) {
            $stmtMap->execute([$frSystemId, $o['name'], $o['short_name']]);
            $n = $stmtMap->fetch(PDO::FETCH_ASSOC);
            if ($n) {
                $map[(int) $o['id']] = (int) $n['id'];
            }
        }
    }
    $pdo->exec("ALTER TABLE users ADD COLUMN grade_ref_id BIGINT UNSIGNED DEFAULT NULL AFTER grade_id");
    $upd = $pdo->prepare("UPDATE users SET grade_ref_id = ? WHERE grade_id = ?");
    foreach ($map as $oldId => $newId) {
        $upd->execute([$newId, $oldId]);
    }
    $pdo->exec("ALTER TABLE users DROP FOREIGN KEY users_grade_id_fk");
    $pdo->exec("ALTER TABLE users DROP COLUMN grade_id");
    $pdo->exec("ALTER TABLE users CHANGE COLUMN grade_ref_id grade_id BIGINT UNSIGNED DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD KEY grade_id (grade_id)");
    $pdo->exec("RENAME TABLE grades TO grades_legacy");
    $pdo->exec("RENAME TABLE grades_referentiel TO grades");
    $pdo->exec("ALTER TABLE users ADD CONSTRAINT users_grade_id_fk FOREIGN KEY (grade_id) REFERENCES grades (id) ON DELETE SET NULL ON UPDATE CASCADE");
    echo "Bascule grades OK (grades_referentiel -> grades, ancienne table -> grades_legacy).\n";
}

// Grades sous-officiers / militaires du rang FR & US (référentiel global, idempotent)
{
    $gradeTable = null;
    foreach (['grades', 'grades_referentiel'] as $t) {
        $chk = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($t) . " AND COLUMN_NAME = 'grade_system_id' LIMIT 1");
        if ($chk && $chk->fetchColumn()) {
            $gradeTable = $t;
            break;
        }
    }
    if ($gradeTable !== null) {
        $cat = [];
        foreach ($pdo->query('SELECT id, code FROM grade_categories')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cat[$r['code']] = (int) $r['id'];
        }
        $sys = [];
        foreach ($pdo->query('SELECT id, code FROM grade_systems')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $sys[$r['code']] = (int) $r['id'];
        }
        if (isset($cat['SOUS_OFFICIER'], $cat['MDR'], $sys['FR_CLASSIC'], $sys['US_CLASSIC'])) {
            $exists = $pdo->prepare("SELECT 1 FROM `{$gradeTable}` WHERE grade_system_id = ? AND code = ? LIMIT 1");
            $ins = $pdo->prepare("INSERT INTO `{$gradeTable}` (grade_system_id, grade_category_id, code, label_short, label_long, label_otan, sort_order, is_commissioned, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, NOW(), NOW())");
            $rows = [
                ['FR_CLASSIC', 'SOUS_OFFICIER', 'MAJ', 'Major', 'Major', 'OR-9', 21],
                ['FR_CLASSIC', 'SOUS_OFFICIER', 'ADC', 'Adc', 'Adjudant-chef', 'OR-8', 22],
                ['FR_CLASSIC', 'SOUS_OFFICIER', 'ADJ', 'Adj', 'Adjudant', 'OR-7', 23],
                ['FR_CLASSIC', 'SOUS_OFFICIER', 'SCH', 'Sch', 'Sergent-chef', 'OR-6', 24],
                ['FR_CLASSIC', 'SOUS_OFFICIER', 'SGT', 'Sgt', 'Sergent', 'OR-5', 25],
                ['FR_CLASSIC', 'MDR', 'CCH', 'Cch', 'Caporal-chef', 'OR-4', 31],
                ['FR_CLASSIC', 'MDR', 'CPL', 'Cpl', 'Caporal', 'OR-3', 32],
                ['FR_CLASSIC', 'MDR', 'SD1', 'Sdt 1', 'Soldat de 1re classe', 'OR-2', 33],
                ['FR_CLASSIC', 'MDR', 'SD2', 'Sdt 2', 'Soldat de 2e classe', 'OR-1', 34],
                ['US_CLASSIC', 'SOUS_OFFICIER', 'SGM', 'SGM', 'Sergeant Major', 'E-9', 21],
                ['US_CLASSIC', 'SOUS_OFFICIER', 'MSG', 'MSG', 'Master Sergeant', 'E-8', 22],
                ['US_CLASSIC', 'SOUS_OFFICIER', 'SFC', 'SFC', 'Sergeant First Class', 'E-7', 23],
                ['US_CLASSIC', 'SOUS_OFFICIER', 'SSG', 'SSG', 'Staff Sergeant', 'E-6', 24],
                ['US_CLASSIC', 'SOUS_OFFICIER', 'SGT', 'SGT', 'Sergeant', 'E-5', 25],
                ['US_CLASSIC', 'MDR', 'CPL', 'CPL', 'Corporal', 'E-4', 31],
                ['US_CLASSIC', 'MDR', 'PFC', 'PFC', 'Private First Class', 'E-3', 32],
                ['US_CLASSIC', 'MDR', 'PV2', 'PV2', 'Private Second Class', 'E-2', 33],
                ['US_CLASSIC', 'MDR', 'PVT', 'PVT', 'Private', 'E-1', 34],
            ];
            $added = 0;
            foreach ($rows as $row) {
                [$sc, $cc, $code, $ls, $ll, $lo, $so] = $row;
                $exists->execute([$sys[$sc], $code]);
                if ($exists->fetchColumn()) {
                    continue;
                }
                $ins->execute([$sys[$sc], $cat[$cc], $code, $ls, $ll, $lo, $so]);
                $added++;
            }
            if ($added > 0) {
                echo "Référentiel grades : ajout SO / MdR ({$added} lignes) dans {$gradeTable}.\n";
            }
        }

        // Officiers généraux, entrées civiles et hors grade (idempotent, même table cible)
        if (isset($cat['OFFICIER'], $sys['FR_CLASSIC'], $sys['US_CLASSIC'])) {
            $existsGen = $pdo->prepare("SELECT 1 FROM `{$gradeTable}` WHERE grade_system_id = ? AND code = ? LIMIT 1");
            $insOff = $pdo->prepare("INSERT INTO `{$gradeTable}` (grade_system_id, grade_category_id, code, label_short, label_long, label_otan, sort_order, is_commissioned, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, NOW(), NOW())");
            $rowsOff = [
                ['FR_CLASSIC', 'GBR', 'Gén. bde', 'Général de brigade', 'OF-6', 17],
                ['FR_CLASSIC', 'GDV', 'Gén. div.', 'Général de division', 'OF-7', 18],
                ['FR_CLASSIC', 'GCA', 'Gén. c. a.', 'Général de corps d’armée', 'OF-8', 19],
                ['FR_CLASSIC', 'GAR', 'Gén. armée', 'Général d’armée', 'OF-9', 20],
                ['US_CLASSIC', 'BG', 'Brig. Gen.', 'Brigadier General', 'O-7', 17],
                ['US_CLASSIC', 'MG', 'Maj. Gen.', 'Major General', 'O-8', 18],
                ['US_CLASSIC', 'LTG', 'Lt Gen.', 'Lieutenant General', 'O-9', 19],
                ['US_CLASSIC', 'GEN', 'Gen.', 'General', 'O-10', 20],
            ];
            $addedOff = 0;
            foreach ($rowsOff as $ro) {
                [$sc, $code, $ls, $ll, $lo, $so] = $ro;
                $existsGen->execute([$sys[$sc], $code]);
                if ($existsGen->fetchColumn()) {
                    continue;
                }
                $insOff->execute([$sys[$sc], $cat['OFFICIER'], $code, $ls, $ll, $lo, $so]);
                $addedOff++;
            }
            if ($addedOff > 0) {
                echo "Référentiel grades : ajout officiers généraux ({$addedOff} lignes) dans {$gradeTable}.\n";
            }
        }
        if (isset($cat['CIVIL'], $sys['FR_CLASSIC'], $sys['US_CLASSIC'])) {
            $existsCiv = $pdo->prepare("SELECT 1 FROM `{$gradeTable}` WHERE grade_system_id = ? AND code = ? LIMIT 1");
            $insCiv = $pdo->prepare("INSERT INTO `{$gradeTable}` (grade_system_id, grade_category_id, code, label_short, label_long, label_otan, sort_order, is_commissioned, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NULL, ?, 0, 1, NOW(), NOW())");
            $rowsCiv = [
                ['FR_CLASSIC', 'CIV', 'Civil', 'Personnel civil', 80],
                ['US_CLASSIC', 'CIV', 'Civilian', 'Civilian (non-military)', 80],
            ];
            $addedCiv = 0;
            foreach ($rowsCiv as $rc) {
                [$sc, $code, $ls, $ll, $so] = $rc;
                $existsCiv->execute([$sys[$sc], $code]);
                if ($existsCiv->fetchColumn()) {
                    continue;
                }
                $insCiv->execute([$sys[$sc], $cat['CIVIL'], $code, $ls, $ll, $so]);
                $addedCiv++;
            }
            if ($addedCiv > 0) {
                echo "Référentiel grades : ajout entrées civiles ({$addedCiv} lignes) dans {$gradeTable}.\n";
            }
        }
        if (isset($cat['HORS_GRADE'], $sys['FR_CLASSIC'], $sys['US_CLASSIC'])) {
            $existsHg = $pdo->prepare("SELECT 1 FROM `{$gradeTable}` WHERE grade_system_id = ? AND code = ? LIMIT 1");
            $insHg = $pdo->prepare("INSERT INTO `{$gradeTable}` (grade_system_id, grade_category_id, code, label_short, label_long, label_otan, sort_order, is_commissioned, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NULL, ?, 0, 1, NOW(), NOW())");
            $rowsHg = [
                ['FR_CLASSIC', 'HG', 'Hors grade', 'Sans grade militaire', 90],
                ['US_CLASSIC', 'HG', 'No grade', 'No military grade', 90],
            ];
            $addedHg = 0;
            foreach ($rowsHg as $rh) {
                [$sc, $code, $ls, $ll, $so] = $rh;
                $existsHg->execute([$sys[$sc], $code]);
                if ($existsHg->fetchColumn()) {
                    continue;
                }
                $insHg->execute([$sys[$sc], $cat['HORS_GRADE'], $code, $ls, $ll, $so]);
                $addedHg++;
            }
            if ($addedHg > 0) {
                echo "Référentiel grades : ajout hors grade ({$addedHg} lignes) dans {$gradeTable}.\n";
            }
        }
    }
}

// Seed presets et variables courrier (si table document_presets vide)
$stmt = $pdo->query("SELECT 1 FROM document_presets LIMIT 1");
if ($stmt && !$stmt->fetch()) {
    echo "Seed presets et variables courrier...\n";
    $presets = [
        ['Format · A4 Portrait', 'a4_portrait', 'a4', 'portrait', 1, 1],
        ['Format · A4 Paysage', 'a4_landscape', 'a4', 'landscape', 1, 0],
        ['Format · Note interne', 'note_interne', 'a4', 'portrait', 1, 0],
        ['Format · Compte rendu', 'compte_rendu', 'a4', 'portrait', 1, 0],
        ['Format · Courrier hiérarchique', 'courrier_hierarchique', 'a4', 'portrait', 1, 0],
        ['Format · Décision', 'decision', 'a4', 'portrait', 1, 0],
        ['Format · Fiche de transmission', 'fiche_transmission', 'a4', 'portrait', 1, 0],
        ['Format · Message court', 'message_court', 'a4', 'portrait', 1, 0],
        ['Format · Compte rendu incident', 'cr_incident', 'a4', 'portrait', 1, 0],
        ['Format · Rapport circonstancié', 'rapport_circonstancie', 'a4', 'portrait', 1, 0],
    ];
    $insPreset = $pdo->prepare("INSERT INTO document_presets (tenant_id, name, code, paper_size, orientation, is_system, is_default, created_at) VALUES (NULL, ?, ?, ?, ?, ?, ?, NOW())");
    foreach ($presets as $i => $p) {
        $insPreset->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $i === 0 ? 1 : 0]);
    }
    $vars = [
        ['current_date_fr', 'Date du jour (FR)', 'system', 'Date au format français', 'system'],
        ['current_datetime_fr', 'Date et heure (FR)', 'system', 'Date et heure au format français', 'system'],
        ['current_year', 'Année en cours', 'system', 'Année courante', 'system'],
        ['document.uuid', 'UUID du document', 'document', 'Identifiant unique du document', 'document'],
        ['document.reference_number', 'Référence du document', 'document', 'Numéro de référence', 'document'],
        ['user.first_name', 'Prénom', 'user', 'Prénom de l\'utilisateur connecté', 'user'],
        ['user.last_name', 'Nom', 'user', 'Nom de l\'utilisateur connecté', 'user'],
        ['user.full_name', 'Nom complet', 'user', 'Prénom et nom', 'user'],
        ['user.rank', 'Grade (code)', 'user', 'Code court du grade', 'user'],
        ['user.rank_label', 'Grade (libellé)', 'user', 'Libellé du grade', 'user'],
        ['user.service_number', 'Matricule', 'user', 'Numéro de service', 'user'],
        ['user.email', 'Email', 'user', 'Adresse email', 'user'],
        ['unit.name', 'Unité (nom)', 'structure', 'Nom de l\'unité', 'structure'],
        ['unit.company', 'Compagnie', 'structure', 'Nom de la compagnie', 'structure'],
        ['unit.section', 'Section', 'structure', 'Section', 'structure'],
        ['unit.address', 'Adresse', 'structure', 'Adresse de l\'unité', 'structure'],
        ['unit.city', 'Ville', 'structure', 'Ville', 'structure'],
        ['superior.rank_label', 'Grade du supérieur', 'hierarchy', 'Grade du supérieur hiérarchique', 'hierarchy'],
        ['superior.full_name', 'Nom du supérieur', 'hierarchy', 'Nom complet du supérieur', 'hierarchy'],
        ['superior.position_label', 'Fonction du supérieur', 'hierarchy', 'Fonction du supérieur', 'hierarchy'],
    ];
    $insVar = $pdo->prepare("INSERT INTO document_variables_catalog (tenant_id, code, label, source_type, description, category, is_active) VALUES (NULL, ?, ?, ?, ?, ?, 1)");
    foreach ($vars as $v) {
        $insVar->execute([$v[0], $v[1], $v[2], $v[3], $v[4]]);
    }
    echo "Seed presets et variables courrier OK.\n";
}

// Mise à jour des libellés des presets courrier (format · libellé) pour éviter la confusion avec les modèles
$presetLabels = [
    'a4_portrait' => 'Format · A4 Portrait',
    'a4_landscape' => 'Format · A4 Paysage',
    'note_interne' => 'Format · Note interne',
    'compte_rendu' => 'Format · Compte rendu',
    'courrier_hierarchique' => 'Format · Courrier hiérarchique',
    'decision' => 'Format · Décision',
    'fiche_transmission' => 'Format · Fiche de transmission',
    'message_court' => 'Format · Message court',
    'cr_incident' => 'Format · Compte rendu incident',
    'rapport_circonstancie' => 'Format · Rapport circonstancié',
];
$updPreset = $pdo->prepare('UPDATE document_presets SET name = ? WHERE code = ?');
foreach ($presetLabels as $code => $name) {
    $updPreset->execute([$name, $code]);
}

// Seed modèles courrier (templates) liés aux presets — si pas déjà présents
$stmt = $pdo->query("SELECT id, code FROM document_presets WHERE tenant_id IS NULL");
$presetIdsByCode = [];
while ($stmt && ($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
    $presetIdsByCode[$row['code']] = (int) $row['id'];
}
$templatesToSeed = [
    ['note-interne', 'Note interne', 'note_interne', 'courrier', 'Modèle pour note interne.', '<div class="p-8 text-sm"><p class="text-right text-gray-500">Le {{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><p class="mt-4">{{document.reference_number}}</p><div class="mt-6"><p>Madame, Monsieur,</p><p>Contenu de la note.</p></div>{{signature_block}}</div>'],
    ['compte-rendu', 'Compte rendu', 'compte_rendu', 'courrier', 'Modèle compte rendu.', '<div class="p-8 text-sm"><p class="text-right">Le {{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><p><strong>Réf. :</strong> {{document.reference_number}}</p><div class="mt-6 space-y-4"><p>Rédigez le compte rendu ci-dessous.</p></div>{{signature_block}}</div>'],
    ['courrier-hierarchique', 'Courrier hiérarchique', 'courrier_hierarchique', 'courrier', 'Courrier à la hiérarchie.', '<div class="p-8 text-sm"><p>{{user.rank_label}} {{user.last_name}} {{user.first_name}}</p><p>Matricule : {{user.service_number}}</p><p class="text-right mt-4">Le {{current_date_fr}}</p><p><strong>À :</strong> {{destination_label}}</p><p><strong>Objet :</strong> {{subject}}</p><div class="mt-6">{{signature_block}}</div></div>'],
    ['decision', 'Décision', 'decision', 'courrier', 'Modèle décision.', '<div class="p-8 text-sm"><p class="font-bold">Décision</p><p>Réf. {{document.reference_number}} — Le {{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><div class="mt-6"><p>Il est décidé ce qui suit :</p><p class="mt-4">[Contenu de la décision]</p></div>{{signature_block}}</div>'],
    ['fiche-transmission', 'Fiche de transmission', 'fiche_transmission', 'courrier', 'Fiche de transmission.', '<div class="p-8 text-sm border border-gray-200"><p><strong>Fiche de transmission</strong></p><p>Réf. {{document.reference_number}} — {{current_date_fr}}</p><p>De : {{user.full_name}} — À : {{destination_label}}</p><p>Objet : {{subject}}</p><div class="mt-4 min-h-[4rem] border-b border-gray-200"></div>{{signature_block}}</div>'],
    ['message-court', 'Message court', 'message_court', 'courrier', 'Message court.', '<div class="p-8 text-sm"><p>{{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><p class="mt-4">[Message]</p>{{signature_block}}</div>'],
    ['compte-rendu-incident', 'Compte rendu incident', 'cr_incident', 'courrier', 'Compte rendu d\'incident.', '<div class="p-8 text-sm"><p class="text-right">Le {{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><p><strong>Réf. :</strong> {{document.reference_number}}</p><div class="mt-6"><p>Compte rendu des faits :</p><p class="mt-2">[Rédiger le compte rendu]</p></div>{{signature_block}}</div>'],
    ['rapport-circonstancie', 'Rapport circonstancié', 'rapport_circonstancie', 'courrier', 'Rapport circonstancié.', '<div class="p-8 text-sm"><p class="font-bold">Rapport circonstancié</p><p>Réf. {{document.reference_number}} — Le {{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><div class="mt-6 space-y-4"><p>[Exposé des faits]</p></div>{{signature_block}}</div>'],
];
$insTpl = $pdo->prepare("INSERT INTO document_templates (tenant_id, name, slug, category, description, is_system, is_locked, is_active, preset_id, body_template, created_at) VALUES (NULL, ?, ?, ?, ?, 1, 0, 1, ?, ?, NOW())");
foreach ($templatesToSeed as $t) {
    $stmt = $pdo->query("SELECT 1 FROM document_templates WHERE slug = " . $pdo->quote($t[0]) . " LIMIT 1");
    if ($stmt && !$stmt->fetch()) {
        $presetId = $presetIdsByCode[$t[2]] ?? null;
        if ($presetId) {
            $insTpl->execute([$t[1], $t[0], $t[3], $t[4], $presetId, $t[5]]);
        }
    }
}

// Variables courrier : grades multi-doctrine (si pas déjà présentes)
$stmt = $pdo->query("SELECT 1 FROM document_variables_catalog WHERE code = 'user.grade_text' LIMIT 1");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout variables courrier (grades)...\n";
    $gradeVars = [
        ['user.grade_text', 'Grade (texte classique)', 'user', 'Libellé classique du grade', 'user'],
        ['user.grade_short', 'Grade (code court)', 'user', 'Code court (ex. CNE)', 'user'],
        ['user.grade_otan', 'Grade (code OTAN)', 'user', 'Code OTAN (ex. OF-2)', 'user'],
        ['user.grade_full', 'Grade (hybride)', 'user', 'Libellé avec code OTAN (ex. Capitaine (OF-2))', 'user'],
        ['user.category_label', 'Catégorie de personnel', 'user', 'Libellé de la catégorie (Officier, etc.)', 'user'],
        ['superior.grade_text', 'Grade du supérieur (texte)', 'hierarchy', 'Grade classique du supérieur', 'hierarchy'],
        ['superior.grade_otan', 'Grade du supérieur (OTAN)', 'hierarchy', 'Code OTAN du supérieur', 'hierarchy'],
    ];
    $insVar = $pdo->prepare("INSERT INTO document_variables_catalog (tenant_id, code, label, source_type, description, category, is_active) VALUES (NULL, ?, ?, ?, ?, ?, 1)");
    foreach ($gradeVars as $v) {
        $insVar->execute([$v[0], $v[1], $v[2], $v[3], $v[4]]);
    }
    echo "Variables grades courrier OK.\n";
}

$stmt = $pdo->query("SELECT 1 FROM document_variables_catalog WHERE code = 'tenant.name' LIMIT 1");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout variables courrier (communauté / groupe)...\n";
    $orgVars = [
        ['tenant.name', 'Communauté', 'structure', 'Nom de la communauté', 'structure'],
        ['group.name', 'Groupe', 'structure', 'Groupe d’affectation', 'structure'],
    ];
    $insVar = $pdo->prepare("INSERT INTO document_variables_catalog (tenant_id, code, label, source_type, description, category, is_active) VALUES (NULL, ?, ?, ?, ?, ?, 1)");
    foreach ($orgVars as $v) {
        $insVar->execute([$v[0], $v[1], $v[2], $v[3], $v[4]]);
    }
    echo "Variables communauté / groupe courrier OK.\n";
}

// Module Courrier : table user_signatures et colonnes signature sur courrier_documents
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_signatures'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table user_signatures...\n";
    $courrierSigPath = $root . '/migrations/courrier_signatures.sql';
    if (is_file($courrierSigPath)) {
        $sql = file_get_contents($courrierSigPath);
        $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
        $sql = preg_replace('/SET NAMES utf8mb4;|SET FOREIGN_KEY_CHECKS = \d+;/', '', $sql);
        $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
        foreach ($chunks as $stmtSql) {
            $stmtSql = trim($stmtSql);
            if ($stmtSql !== '') {
                try {
                    $pdo->exec($stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';'));
                } catch (PDOException $e) {
                    echo "  [ATTENTION] " . $e->getMessage() . "\n";
                }
            }
        }
        echo "user_signatures OK.\n";
    }
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courrier_documents' AND COLUMN_NAME = 'signed_at'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout courrier_documents.signed_at...\n";
    $pdo->exec("ALTER TABLE courrier_documents ADD COLUMN signed_at DATETIME DEFAULT NULL AFTER signed_by");
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courrier_documents' AND COLUMN_NAME = 'signature_data_json'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout courrier_documents.signature_data_json...\n";
    $pdo->exec("ALTER TABLE courrier_documents ADD COLUMN signature_data_json JSON DEFAULT NULL AFTER signed_at");
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courrier_documents' AND COLUMN_NAME = 'content_hash'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout courrier_documents.content_hash...\n";
    $pdo->exec("ALTER TABLE courrier_documents ADD COLUMN content_hash VARCHAR(64) DEFAULT NULL AFTER signature_data_json");
}

// Courrier : notifications in-app (document signalé aux membres)
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courrier_document_notifications'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table courrier_document_notifications...\n";
    $cdnPath = $root . '/migrations/courrier_document_notifications.sql';
    if (is_file($cdnPath)) {
        $sql = file_get_contents($cdnPath);
        $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
        $sql = preg_replace('/SET NAMES utf8mb4;|SET FOREIGN_KEY_CHECKS = \d+;/', '', $sql);
        $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
        foreach ($chunks as $stmtSql) {
            $stmtSql = trim($stmtSql);
            if ($stmtSql !== '') {
                try {
                    $pdo->exec($stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';'));
                } catch (PDOException $e) {
                    echo "  [ATTENTION] " . $e->getMessage() . "\n";
                }
            }
        }
        echo "courrier_document_notifications OK.\n";
    }
}

// Seed preset et template CERBERE (Compte-rendu officiel 92e RI)
$stmt = $pdo->query("SELECT id FROM document_presets WHERE code = 'cerbere_officiel' LIMIT 1");
if ($stmt && !$stmt->fetch()) {
    echo "Seed preset et template CERBERE...\n";
    $pdo->exec("INSERT INTO document_presets (tenant_id, name, code, paper_size, orientation, is_system, is_default, created_at) VALUES (NULL, 'Compte-rendu officiel CERBERE / 92e RI', 'cerbere_officiel', 'a4', 'portrait', 1, 0, NOW())");
    $cerberePresetId = (int) $pdo->lastInsertId();
    $bodyTemplate = <<<'HTM'
<div class="p-12 bg-white text-gray-900 overflow-x-auto">
<div class="max-w-[21cm] mx-auto min-h-[29.7cm] p-10 border border-gray-200">
<div class="text-[10px] font-bold uppercase leading-tight mb-12">
<p>MINISTÈRE DE LA DÉFENSE</p>
<p class="border-b-2 border-black w-fit mb-1">UNITÉ : {{unit.name}}</p>
<p>SECTION : {{unit.section}}</p>
<p class="mt-4">N° {{document.reference_number}} / CERBERE / RH</p>
</div>
<div class="text-right text-[11px] mb-10">
<p>Le {{current_date_fr}}</p>
</div>
<div class="ml-auto w-1/2 text-[11px] font-bold space-y-1 mb-12">
<p>{{user.rank_label}} {{user.last_name}} {{user.first_name}}</p>
<p>Matricule : {{user.service_number}}</p>
<p class="text-blue-600 italic py-2">à</p>
<p>{{destination_label}}</p>
</div>
<div class="text-[11px] space-y-2 mb-10">
<p><span class="underline font-bold">OBJET</span> : {{subject}}</p>
<p><span class="underline font-bold">RÉFÉRENCE</span> : {{document.reference_number}}</p>
</div>
<div class="text-xs leading-relaxed text-justify space-y-4">
<p class="font-bold italic">Mon Capitaine,</p>
<p>Contenu du compte-rendu à rédiger.</p>
</div>
{{signature_block}}
</div>
</div>
HTM;
    $bodyEscaped = $pdo->quote($bodyTemplate);
    $pdo->exec("INSERT INTO document_templates (tenant_id, name, slug, category, description, is_system, is_locked, is_active, preset_id, body_template, created_at) VALUES (NULL, 'Compte-rendu d''incident CERBERE', 'compte-rendu-cerbere', 'cerbere', 'Modèle officiel type 92e RI / CERBERE', 1, 1, 1, $cerberePresetId, $bodyEscaped, NOW())");
    echo "Preset et template CERBERE OK.\n";
}

// Forum : colonnes et tables additionnelles (category icon, post is_hidden, category subscriptions)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_categories' AND COLUMN_NAME = 'icon'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout forum_categories.icon...\n";
    $pdo->exec("ALTER TABLE forum_categories ADD COLUMN icon varchar(50) DEFAULT NULL AFTER description");
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_posts' AND COLUMN_NAME = 'is_hidden'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout forum_posts.is_hidden...\n";
    $pdo->exec("ALTER TABLE forum_posts ADD COLUMN is_hidden tinyint(1) DEFAULT 0 AFTER body");
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_posts' AND COLUMN_NAME = 'body_format'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout forum_posts.body_format...\n";
    $pdo->exec("ALTER TABLE forum_posts ADD COLUMN body_format VARCHAR(20) NOT NULL DEFAULT 'markdown' AFTER body");
    try {
        $pdo->exec("UPDATE forum_posts SET body_format = 'html' WHERE body LIKE '%rofa-wrap%'");
    } catch (\Throwable $e) {
        echo '  [note] backfill forum_posts.body_format : ' . $e->getMessage() . "\n";
    }
}
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_category_subscriptions'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table forum_category_subscriptions...\n";
    $pdo->exec("CREATE TABLE forum_category_subscriptions (
        user_id int unsigned NOT NULL,
        category_id int unsigned NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, category_id),
        CONSTRAINT forum_category_subscriptions_user_id_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT forum_category_subscriptions_category_id_fk FOREIGN KEY (category_id) REFERENCES forum_categories (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$forumPremiumMigrate = require $root . '/bootstrap/forum_premium_migration.php';
$forumPremiumMigrate($pdo);

$forumV2Migrate = require $root . '/bootstrap/forum_v2_migration.php';
$forumV2Migrate($pdo);

$forumModerationBotMigrate = require $root . '/bootstrap/forum_moderation_bot_migration.php';
$forumModerationBotMigrate($pdo);

$alertsMigrate = require $root . '/bootstrap/alerts_migration.php';
$alertsMigrate($pdo);

$doctrineReferentialMigrate = require $root . '/bootstrap/doctrine_referential_migration.php';
$doctrineReferentialMigrate($pdo);

$tenantAlertsVisualMigrate = require $root . '/bootstrap/tenant_alerts_visual_migration.php';
try {
    $tenantAlertsVisualMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_alerts_visual : ' . $e->getMessage() . "\n";
}

$platformAlertsFeaturesMigrate = require $root . '/bootstrap/platform_alerts_features_migration.php';
try {
    $platformAlertsFeaturesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] platform_alerts_features : ' . $e->getMessage() . "\n";
}

$alertDisplayStyleMigrate = require $root . '/bootstrap/alert_display_style_migration.php';
try {
    $alertDisplayStyleMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] alert_display_style : ' . $e->getMessage() . "\n";
}

$tenantAlertsFeaturesMigrate = require $root . '/bootstrap/tenant_alerts_features_migration.php';
try {
    $tenantAlertsFeaturesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_alerts_features : ' . $e->getMessage() . "\n";
}

$communityEventsDetailsMigrate = require $root . '/bootstrap/community_events_details_migration.php';
try {
    echo "Migration community_events (détails / image / conditions)...\n";
    $communityEventsDetailsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_events_details : ' . $e->getMessage() . "\n";
}

$communityMediaMigrate = require $root . '/bootstrap/community_media_migration.php';
try {
    echo "Migration community_media (collections / items)...\n";
    $communityMediaMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_media : ' . $e->getMessage() . "\n";
}

$communityShowcaseVitrineMigrate = require $root . '/bootstrap/community_showcase_vitrine_migration.php';
try {
    echo "Migration community_showcase_vitrine (places unités / agenda public)...\n";
    $communityShowcaseVitrineMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_showcase_vitrine : ' . $e->getMessage() . "\n";
}

$unitsPublicDatesMigrate = require $root . '/bootstrap/units_public_dates_migration.php';
try {
    echo "Migration units public dates (création / date complémentaire)...\n";
    $unitsPublicDatesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] units_public_dates : ' . $e->getMessage() . "\n";
}

$militaryReferentialMigrate = require $root . '/bootstrap/military_referential_migration.php';
try {
    echo "Migration référentiel militaire SOF (countries / military_units / affiliations)...\n";
    $militaryReferentialMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] military_referential : ' . $e->getMessage() . "\n";
}

$configurationUpdatesMigrate = require $root . '/bootstrap/configuration_updates_migration.php';
try {
    echo "Migration configuration post-mise à jour (system/tenant_configuration_updates)...\n";
    $configurationUpdatesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] configuration_updates : ' . $e->getMessage() . "\n";
}

$operationsWorkspaceMigrate = require $root . '/bootstrap/operations_workspace_migration.php';
try {
    echo "Migration espaces opérationnels (operations, calques, tâches, objectifs)...\n";
    $operationsWorkspaceMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] operations_workspace : ' . $e->getMessage() . "\n";
}

$communityMediaReelsMigrate = require $root . '/bootstrap/community_media_reels_migration.php';
try {
    echo "Migration community_media reels (feed / social)...\n";
    $communityMediaReelsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_media_reels : ' . $e->getMessage() . "\n";
}

$communityEventRsvpHistoryMigrate = require $root . '/bootstrap/community_event_rsvp_history_migration.php';
try {
    echo "Migration community_event_rsvp_history...\n";
    $communityEventRsvpHistoryMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_event_rsvp_history : ' . $e->getMessage() . "\n";
}

$communityEventSlotsMigrate = require $root . '/bootstrap/community_event_slots_migration.php';
try {
    echo "Migration community_event_slots (slotting de mission)...\n";
    $communityEventSlotsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_event_slots : ' . $e->getMessage() . "\n";
}

$competencyGradeRequirementsMigrate = require $root . '/bootstrap/competency_grade_requirements_migration.php';
try {
    echo "Migration competency_grade_requirements (matrice compétences × grades)...\n";
    $competencyGradeRequirementsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] competency_grade_requirements : ' . $e->getMessage() . "\n";
}

$contentTagsMigrate = require $root . '/bootstrap/content_tags_migration.php';
try {
    echo "Migration content_tags (tags partagés formations/documentations)...\n";
    $contentTagsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] content_tags : ' . $e->getMessage() . "\n";
}

$trainingFormationCustomPageFeedbackMigrate = require $root . '/bootstrap/training_formation_custom_page_feedback_migration.php';
try {
    echo "Migration training_formation_custom_page_feedback (avis lecteurs Documentations)...\n";
    $trainingFormationCustomPageFeedbackMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_formation_custom_page_feedback : ' . $e->getMessage() . "\n";
}

$platformUxFeedbackMigrate = require $root . '/bootstrap/platform_ux_feedback_migration.php';
try {
    echo "Migration platform_ux_feedback (retours UI + flash_info_detailed)...\n";
    $platformUxFeedbackMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] platform_ux_feedback : ' . $e->getMessage() . "\n";
}

$tenantCustomMapsMigrate = require $root . '/bootstrap/tenant_custom_maps_migration.php';
try {
    $tenantCustomMapsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_custom_maps : ' . $e->getMessage() . "\n";
}

$moderationContentMigrate = require $root . '/bootstrap/moderation_content_migration.php';
$moderationContentMigrate($pdo);

require_once $root . '/bootstrap/transactional_email_migration.php';
run_transactional_email_migration($pdo);

$systemModeratorMigrate = require $root . '/bootstrap/system_moderator_account_migration.php';
$systemModeratorMigrate($pdo);

// training_showcase + training_course_lms_theme + training_lms_engagement : déjà exécutés après le bootstrap (début de fichier).

$trainingModuleLessonEnrichmentMigrate = require $root . '/bootstrap/training_module_lesson_enrichment_migration.php';
try {
    $trainingModuleLessonEnrichmentMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_module_lesson_enrichment : ' . $e->getMessage() . "\n";
}

$trainingResourcesLibraryDocMigrate = require $root . '/bootstrap/training_resources_library_document_migration.php';
try {
    $trainingResourcesLibraryDocMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_resources_library_document : ' . $e->getMessage() . "\n";
}

$trainingEnrollmentMotivationMigrate = require $root . '/bootstrap/training_enrollment_motivation_migration.php';
try {
    $trainingEnrollmentMotivationMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_enrollment_motivation : ' . $e->getMessage() . "\n";
}

$personnelJobRolesMigrate = require $root . '/bootstrap/personnel_job_roles_migration.php';
$personnelJobRolesMigrate($pdo);

$elevationRequestsProposalMigrate = require $root . '/bootstrap/elevation_requests_proposal_migration.php';
try {
    $elevationRequestsProposalMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] elevation_requests_proposal : ' . $e->getMessage() . "\n";
}

require_once $root . '/bootstrap/account_purge_requests_migration.php';
try {
    run_account_purge_requests_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] account_purge_requests : ' . $e->getMessage() . "\n";
}

$personnelCorrectionRequestsMigrate = require $root . '/bootstrap/personnel_correction_requests_migration.php';
try {
    echo "Migration personnel_correction_requests (corrections RH fiche opérateur)...\n";
    $personnelCorrectionRequestsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] personnel_correction_requests : ' . $e->getMessage() . "\n";
}

$memberDeparturesMigrate = require $root . '/bootstrap/member_departures_migration.php';
try {
    $memberDeparturesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] member_departures : ' . $e->getMessage() . "\n";
}

$rhDossierIndividuelMigrate = require $root . '/bootstrap/rh_dossier_individuel_migration.php';
try {
    echo "Migration rh_dossier_individuel (documents, mobilité, vivier, offboarding v2)...\n";
    $rhDossierIndividuelMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] rh_dossier_individuel : ' . $e->getMessage() . "\n";
}

$enlistmentCannedMessagesMigrate = require $root . '/bootstrap/enlistment_canned_messages_migration.php';
$enlistmentCannedMessagesMigrate($pdo);

$enlistmentPortalAttachmentsMigrate = require $root . '/bootstrap/enlistment_portal_attachments_migration.php';
try {
    $enlistmentPortalAttachmentsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] enlistment_portal_attachments : ' . $e->getMessage() . "\n";
}

$recruitmentOpeningsMigrate = require $root . '/bootstrap/recruitment_openings_migration.php';
try {
    $recruitmentOpeningsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] recruitment_openings : ' . $e->getMessage() . "\n";
}

$recruitmentTeamWallKindSubjectMigrate = require $root . '/bootstrap/recruitment_team_wall_kind_subject_migration.php';
try {
    $recruitmentTeamWallKindSubjectMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] recruitment_team_wall_kind_subject : ' . $e->getMessage() . "\n";
}

$recruitmentInviteCodesMigrate = require $root . '/bootstrap/recruitment_invite_codes_migration.php';
try {
    $recruitmentInviteCodesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] recruitment_invite_codes : ' . $e->getMessage() . "\n";
}

$tenantDashboardPinsMigrate = require $root . '/bootstrap/tenant_dashboard_pins_migration.php';
try {
    $tenantDashboardPinsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_dashboard_pins : ' . $e->getMessage() . "\n";
}

$dashboardWardrobePinsMigrate = require $root . '/bootstrap/dashboard_wardrobe_pins_migration.php';
try {
    $dashboardWardrobePinsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_dashboard_wardrobe_pins : ' . $e->getMessage() . "\n";
}

$loginAccueilImagesMigrate = require $root . '/bootstrap/tenant_login_accueil_images_migration.php';
try {
    $loginAccueilImagesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_login_accueil_images : ' . $e->getMessage() . "\n";
}

$forumTopicPinOnDashboardMigrate = require $root . '/bootstrap/forum_topic_pin_on_dashboard_migration.php';
try {
    $forumTopicPinOnDashboardMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] forum_topic_pin_on_dashboard : ' . $e->getMessage() . "\n";
}

$demoNdaGateMigrate = require $root . '/bootstrap/demo_nda_gate_migration.php';
try {
    $demoNdaGateMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] demo_nda_gate : ' . $e->getMessage() . "\n";
}

$operationalBoardShareMigrate = require $root . '/bootstrap/operational_board_share_migration.php';
try {
    $operationalBoardShareMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] operational_board_share : ' . $e->getMessage() . "\n";
}

$cronSystemMigrate = require $root . '/bootstrap/cron_system_migration.php';
try {
    $cronSystemMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] cron_system : ' . $e->getMessage() . "\n";
}

$userEmailLoginOtpMigrate = require $root . '/bootstrap/user_email_login_otp_migration.php';
try {
    $userEmailLoginOtpMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] user_email_login_otp : ' . $e->getMessage() . "\n";
}

$userTotpMigrate = require $root . '/bootstrap/user_totp_migration.php';
try {
    $userTotpMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] user_totp : ' . $e->getMessage() . "\n";
}

$userLegalIdentitiesMigrate = require $root . '/bootstrap/user_legal_identities_migration.php';
try {
    $userLegalIdentitiesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] user_legal_identities : ' . $e->getMessage() . "\n";
}

$userProfileBannerMigrate = require $root . '/bootstrap/user_profile_banner_migration.php';
try {
    $userProfileBannerMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] user_profile_banner : ' . $e->getMessage() . "\n";
}

$usersMemberPhotoMigrate = require $root . '/bootstrap/users_member_photo_columns_migration.php';
try {
    $usersMemberPhotoMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] users_member_photo : ' . $e->getMessage() . "\n";
}

$tenantCommunityFeedMigrate = require $root . '/bootstrap/tenant_community_feed_migration.php';
try {
    $tenantCommunityFeedMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_community_feed : ' . $e->getMessage() . "\n";
}

$tenantMiniArticlesMigrate = require $root . '/bootstrap/tenant_mini_articles_migration.php';
try {
    $tenantMiniArticlesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_mini_articles : ' . $e->getMessage() . "\n";
}

$briefPlatformInterteamMigrate = require $root . '/bootstrap/brief_platform_interteam_migration.php';
try {
    $briefPlatformInterteamMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] brief_platform_interteam : ' . $e->getMessage() . "\n";
}

$interteamCoopHubMigrate = require $root . '/bootstrap/interteam_cooperation_hub_migration.php';
try {
    $interteamCoopHubMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] interteam_cooperation_hub : ' . $e->getMessage() . "\n";
}

$interteamOperationalWorkflowMigrate = require $root . '/bootstrap/interteam_mission_operational_workflow_migration.php';
try {
    $interteamOperationalWorkflowMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] interteam_mission_operational_workflow : ' . $e->getMessage() . "\n";
}

$cooperationEnhanceMigrate = require $root . '/bootstrap/cooperation_module_enhancements_migration.php';
try {
    $cooperationEnhanceMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] cooperation_module_enhancements : ' . $e->getMessage() . "\n";
}

$cooperationCatalogMigrate = require $root . '/bootstrap/cooperation_catalog_and_announcements_migration.php';
try {
    $cooperationCatalogMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] cooperation_catalog_and_announcements : ' . $e->getMessage() . "\n";
}

$cooperationAnnouncementsV2 = require $root . '/bootstrap/cooperation_announcement_events_v2_migration.php';
try {
    $cooperationAnnouncementsV2($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] cooperation_announcement_events_v2 : ' . $e->getMessage() . "\n";
}

require_once $root . '/bootstrap/autoload.php';

try {
    echo "Fusion des comptes partageant le même e-mail (une identité, dossiers RH séparés)...\n";
    $merge = new \App\Services\Identity\UserIdentityMergeService(
        $pdo,
        new \App\Repositories\UserCommunityMembershipRepository($pdo)
    );
    $mergeSummary = $merge->mergeAllDuplicateEmails();
    echo '  groupes=' . (int) ($mergeSummary['groups'] ?? 0)
        . ' fiches réunies=' . (int) ($mergeSummary['merged'] ?? 0)
        . ' collisions Steam=' . (int) ($mergeSummary['collisions'] ?? 0) . "\n";
    foreach ($mergeSummary['errors'] ?? [] as $err) {
        echo '  [ATTENTION] fusion : ' . $err . "\n";
    }
    echo "Reprise des dossiers laissés sur les comptes réunis...\n";
    $restore = new \App\Services\Identity\UserIdentityProfileRestoreService($pdo);
    $restored = $restore->restoreAll();
    echo '  fusions=' . (int) ($restored['merges'] ?? 0)
        . ' dossiers RH=' . (int) ($restored['personnel'] ?? 0)
        . ' fiches civiles=' . (int) ($restored['user_profiles'] ?? 0)
        . ' fiches communauté=' . (int) ($restored['community_profiles'] ?? 0) . "\n";
} catch (Throwable $e) {
    echo '  [ATTENTION] user_identity_merge : ' . $e->getMessage() . "\n";
}

$organizationCatalogMigrate = require $root . '/bootstrap/organization_catalog_migration.php';
try {
    echo "Migration catalogue d’organisation (modèles et applications)...\n";
    $organizationCatalogMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] organization_catalog : ' . $e->getMessage() . "\n";
}

try {
    \App\Services\Rbac\MilitaryRoleCatalogSyncService::syncAllTenants($pdo);
    echo "Catalogue rôles militaires (synchronisation tenants) OK.\n";
} catch (Throwable $e) {
    echo '  [ATTENTION] military_role_catalog_sync : ' . $e->getMessage() . "\n";
}

$trainingOnboardingBulk = $root . '/bootstrap/training_onboarding_bulk_assign.php';
if (is_file($trainingOnboardingBulk)) {
    require_once $trainingOnboardingBulk;
    try {
        run_training_onboarding_bulk_assign($pdo);
    } catch (Throwable $e) {
        echo '  [ATTENTION] training_onboarding_bulk_assign : ' . $e->getMessage() . "\n";
    }
}

try {
    $userRepoSeed = new \App\Repositories\UserRepository();
    $tList = $pdo->query('SELECT id FROM tenants');
    if ($tList) {
        while ($tRow = $tList->fetch(PDO::FETCH_ASSOC)) {
            $userRepoSeed->ensureSystemModeratorUser((int) $tRow['id']);
        }
    }
    echo "Comptes techniques modération (par tenant) OK.\n";
} catch (Throwable $e) {
    echo '  [ATTENTION] Compte modération système : ' . $e->getMessage() . "\n";
}

$msgSql = $root . '/migrations/community_messaging.sql';
if (is_file($msgSql)) {
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_message_threads'");
    if ($chk && !$chk->fetch()) {
        echo "Migration community_messaging.sql...\n";
        $pdo->exec(file_get_contents($msgSql));
    }
}

$jnetMessagingMigrate = require $root . '/bootstrap/jnet_messaging_migration.php';
try {
    $jnetMessagingMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] jnet_messaging : ' . $e->getMessage() . "\n";
}

// Permission messagerie interne : comms.tenant_messages.receive (+ liaison rôles gouvernance)
try {
    $permSlug = 'comms.tenant_messages.receive';
    $permName = 'Recevoir les messages internes adressés à l’encadrement';
    $tStmt = $pdo->query('SELECT id FROM tenants');
    if ($tStmt) {
        $insPerm = $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())');
        $chkPerm = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $insRp = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        $roleIds = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::inLiterals($pdo, 'slug', ['tenant_admin', 'community_owner']));
        while ($tRow = $tStmt->fetch(PDO::FETCH_ASSOC)) {
            $tid = (int) ($tRow['id'] ?? 0);
            if ($tid < 1) {
                continue;
            }
            $chkPerm->execute([$tid, $permSlug]);
            $permRow = $chkPerm->fetch(PDO::FETCH_ASSOC);
            if ($permRow) {
                $permId = (int) ($permRow['id'] ?? 0);
            } else {
                $insPerm->execute([$tid, $permName, $permSlug, 'comms']);
                $permId = (int) $pdo->lastInsertId();
            }
            if ($permId < 1) {
                continue;
            }
            $roleIds->execute([$tid]);
            while ($r = $roleIds->fetch(PDO::FETCH_ASSOC)) {
                $rid = (int) ($r['id'] ?? 0);
                if ($rid > 0) {
                    $insRp->execute([$rid, $permId]);
                }
            }
        }
    }
    echo "Permission messagerie interne (comms.tenant_messages.receive) — synchronisation par tenant OK.\n";
} catch (Throwable $e) {
    echo '  [ATTENTION] Permission messagerie interne : ' . $e->getMessage() . "\n";
}

$platformIntPath = $root . '/migrations/platform_integrations.sql';
if (is_file($platformIntPath)) {
    echo "Migration platform_integrations.sql (idempotent)...\n";
    try {
        $pdo->exec(file_get_contents($platformIntPath));
    } catch (Throwable $e) {
        echo '  [ATTENTION] platform_integrations.sql : ' . $e->getMessage() . "\n";
    }
}

// ----- Schéma V2 : user_ui_preferences, tenant_branding, notifications, modules, quotas -----
$schemaV2Path = $root . '/migrations/schema_v2_tenant_user_prefs.sql';
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_ui_preferences'");
if ($stmt && !$stmt->fetch() && is_file($schemaV2Path)) {
    echo "Migration schema_v2_tenant_user_prefs.sql...\n";
    $sql = file_get_contents($schemaV2Path);
    $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
    $sql = preg_replace('/SET NAMES utf8mb4;|SET FOREIGN_KEY_CHECKS = \d+;/', '', $sql);
    $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
    foreach ($chunks as $stmtSql) {
        $stmtSql = trim($stmtSql);
        if ($stmtSql !== '') {
            $pdo->exec($stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';'));
        }
    }
    echo "Schéma V2 (préférences / tenant typé) OK.\n";
}

// Colonnes ciblées sur tenants (locale / pays) — idempotent
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'default_timezone'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration tenants.default_timezone / default_locale / country_code...\n";
    $pdo->exec("ALTER TABLE tenants ADD COLUMN default_timezone varchar(64) DEFAULT 'Europe/Paris'");
    $pdo->exec("ALTER TABLE tenants ADD COLUMN default_locale char(5) DEFAULT 'fr-FR'");
    $pdo->exec("ALTER TABLE tenants ADD COLUMN country_code char(2) DEFAULT NULL");
}

// Index activité utilisateurs (stats admin)
$stmt = $pdo->query("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_tenant_status_login'");
if ($stmt && !$stmt->fetch()) {
    echo "Index users (tenant_id, status, last_login_at)...\n";
    $pdo->exec('ALTER TABLE users ADD KEY idx_users_tenant_status_login (tenant_id, status, last_login_at)');
}

// Grade affiché : override explicite (texte libre déprécié au profit du référentiel)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_profiles' AND COLUMN_NAME = 'rank_display_override'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration personnel_profiles.rank_display_override...\n";
    $pdo->exec('ALTER TABLE personnel_profiles ADD COLUMN rank_display_override varchar(100) DEFAULT NULL COMMENT \'Exception métier; sinon grades + overrides\' AFTER rank_display');
}

// Synchroniser tenant_branding.logo_url depuis tenants.logo_url (première passe)
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_branding'");
if ($stmt && $stmt->fetch()) {
    $chkEmpty = $pdo->query('SELECT COUNT(*) c FROM tenant_branding')->fetch(PDO::FETCH_ASSOC);
    if ($chkEmpty && (int) ($chkEmpty['c'] ?? 0) === 0) {
        echo "Seed tenant_branding depuis tenants.logo_url...\n";
        $pdo->exec(
            'INSERT INTO tenant_branding (tenant_id, logo_url, updated_at)
             SELECT id, logo_url, NOW() FROM tenants WHERE logo_url IS NOT NULL AND TRIM(logo_url) <> \'\''
        );
    }
}

// Backfill indicatif plateforme : users.callsign ← personnel_profiles / user_profiles (source unique)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'callsign'");
if ($stmt && $stmt->fetch()) {
    echo "Backfill users.callsign depuis dossier / profil (si vide)...\n";
    $pdo->exec(
        'UPDATE users u
         LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
         LEFT JOIN user_profiles up ON up.user_id = u.id
         SET u.callsign = COALESCE(
           NULLIF(TRIM(u.callsign), \'\'),
           NULLIF(TRIM(pp.callsign), \'\'),
           NULLIF(TRIM(up.arma_callsign), \'\')
         )
         WHERE (u.callsign IS NULL OR TRIM(u.callsign) = \'\')
           AND (
             (pp.callsign IS NOT NULL AND TRIM(pp.callsign) <> \'\')
             OR (up.arma_callsign IS NOT NULL AND TRIM(up.arma_callsign) <> \'\')
           )'
    );
}

// Aligner readiness_score depuis personnel_extras.readiness_percent quand le dossier est à 0
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_extras'");
if ($stmt && $stmt->fetch()) {
    echo "Backfill personnel_profiles.readiness_score depuis personnel_extras (si applicable)...\n";
    $pdo->exec(
        'UPDATE personnel_profiles pp
         INNER JOIN personnel_extras pe ON pe.user_id = pp.user_id
         SET pp.readiness_score = LEAST(100, GREATEST(COALESCE(pp.readiness_score, 0), COALESCE(pe.readiness_percent, 0)))
         WHERE pe.readiness_percent IS NOT NULL
           AND pe.readiness_percent > 0
           AND (pp.readiness_score IS NULL OR pp.readiness_score = 0)'
    );
    echo "Backfill personnel_profiles.clearance_level depuis personnel_extras (si dossier vide)...\n";
    $pdo->exec(
        'UPDATE personnel_profiles pp
         INNER JOIN personnel_extras pe ON pe.user_id = pp.user_id
         SET pp.clearance_level = pe.clearance_level
         WHERE (pp.clearance_level IS NULL OR TRIM(pp.clearance_level) = \'\')
           AND pe.clearance_level IS NOT NULL AND TRIM(pe.clearance_level) <> \'\''
    );
}

// Affectations : compléter personnel_assignments depuis user_units lorsque la ligne manque (personnel_assignments = source métier riche ; user_units = compat / historique)
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_assignments'");
if ($stmt && $stmt->fetch()) {
    echo "Complément personnel_assignments depuis user_units (lignes manquantes)...\n";
    $pdo->exec(
        'INSERT INTO personnel_assignments (user_id, unit_id, role_name, is_primary, started_at, ended_at, status, created_at)
         SELECT uu.user_id, uu.unit_id,
                COALESCE(NULLIF(TRIM(uu.assignment_type), \'\'), \'Membre\'),
                COALESCE(uu.is_primary, 0),
                CASE WHEN uu.assigned_at IS NULL THEN CURDATE() ELSE DATE(uu.assigned_at) END,
                CASE WHEN uu.ended_at IS NULL THEN NULL ELSE DATE(uu.ended_at) END,
                CASE WHEN uu.ended_at IS NULL OR uu.ended_at > NOW() THEN \'active\' ELSE \'inactive\' END,
                NOW()
         FROM user_units uu
         WHERE NOT EXISTS (
           SELECT 1 FROM personnel_assignments pa
           WHERE pa.user_id = uu.user_id AND pa.unit_id = uu.unit_id
         )'
    );
}

// ----- Seed forum (permissions, rôles, catégories) — idempotent -----
$run_forum_seed = function (PDO $pdo, int $tenantId): void {
    $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'forum.view') . ' LIMIT 1');
    $stmt->execute([$tenantId]);
    if ($stmt->fetch()) {
        echo "Forum seed déjà appliqué (permissions existantes).\n";
        return;
    }

    echo "Seed forum : permissions et rôles...\n";

    $permissions = [
        ['admin.access', 'Accès administration', 'admin'],
        ['forum.view', 'Voir le forum', 'forum'],
        ['forum.create_topic', 'Créer un sujet', 'forum'],
        ['forum.reply', 'Répondre', 'forum'],
        ['forum.edit_own', 'Modifier son message', 'forum'],
        ['forum.delete_own', 'Supprimer son message', 'forum'],
        ['forum.moderate', 'Modérer le forum', 'forum'],
        ['forum.moderate_organization', 'Modérer la section forum de l\'organisation', 'forum'],
        ['forum.manage_categories', 'Gérer les catégories', 'forum'],
    ];

    $permIds = [];
    $insertPerm = $pdo->prepare("INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())");
    foreach ($permissions as $p) {
        $insertPerm->execute([$tenantId, $p[1], $p[0], $p[2]]);
        $permIds[$p[0]] = (int) $pdo->lastInsertId();
    }

    $adminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'tenant_admin') . ' LIMIT 1');
    $adminRole->execute([$tenantId]);
    $adminRoleId = (int) ($adminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
    if ($adminRoleId) {
        $link = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($permIds as $pid) {
            $link->execute([$adminRoleId, $pid]);
        }
    }

    foreach (['forum_moderator' => 'Modérateur forum', 'member' => 'Membre', 'officer' => 'Officier'] as $slug => $roleName) {
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $stmt->execute([$tenantId, $slug]);
        if (!$stmt->fetch()) {
            $pdo->prepare("INSERT INTO roles (tenant_id, name, slug, description, is_system, created_at) VALUES (?, ?, ?, ?, 1, NOW())")
                ->execute([$tenantId, $roleName, $slug, '']);
        }
    }

    $modRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'forum_moderator') . ' LIMIT 1');
    $modRole->execute([$tenantId]);
    $modRoleId = (int) ($modRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
    if ($modRoleId) {
        $link = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach (['forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.moderate', 'forum.moderate_organization'] as $slug) {
            if (isset($permIds[$slug])) {
                $link->execute([$modRoleId, $permIds[$slug]]);
            }
        }
    }

    $memberRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'member') . ' LIMIT 1');
    $memberRole->execute([$tenantId]);
    $memberRoleId = (int) ($memberRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
    if ($memberRoleId) {
        $link = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach (['forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own'] as $slug) {
            if (isset($permIds[$slug])) {
                $link->execute([$memberRoleId, $permIds[$slug]]);
            }
        }
    }

    $stmt = $pdo->prepare("SELECT 1 FROM forum_categories WHERE tenant_id = ? LIMIT 1");
    $stmt->execute([$tenantId]);
    if ($stmt->fetch()) {
        echo "Catégories forum déjà présentes.\n";
        return;
    }

    echo "Création des catégories forum par défaut...\n";
    $categories = [
        ['Communiqués officiels', 'annonces', 'Annonces et communiqués de l\'équipe.', 'orange', 10],
        ['Général', 'general', 'Discussions générales et présentation.', 'indigo', 20],
        ['Missions & Opérations', 'missions', 'Briefs et retours d\'opérations.', 'violet', 30],
        ['Support & Technique', 'support', 'Aide, ATAK, équipement, technique.', 'rose', 40],
        ['Hors sujet', 'hors-sujet', 'Échanges informels.', 'emerald', 50],
    ];
    $insCat = $pdo->prepare("INSERT INTO forum_categories (tenant_id, parent_id, name, slug, description, color_theme, display_order, is_locked, created_at, updated_at) VALUES (?, NULL, ?, ?, ?, ?, ?, 0, NOW(), NOW())");
    foreach ($categories as $c) {
        $insCat->execute([$tenantId, $c[0], $c[1], $c[2], $c[3], $c[4]]);
    }
    echo "Forum seed OK.\n";
};

$run_documents_seed = function (PDO $pdo, int $tenantId): void {
    $docPermSlugs = ['documents.view', 'documents.upload', 'documents.update', 'documents.archive', 'documents.download_sensitive'];
    $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'documents.view') . ' LIMIT 1');
    $stmt->execute([$tenantId]);
    $docPermIds = [];
    if ($stmt->fetch()) {
        foreach ($docPermSlugs as $slug) {
            $s = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
            $s->execute([$tenantId, $slug]);
            $id = $s->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
            if ($id) {
                $docPermIds[$slug] = (int) $id;
            }
        }
    } else {
        $docPerms = [
            ['documents.view', 'Voir les documents', 'documents'],
            ['documents.upload', 'Uploader des documents', 'documents'],
            ['documents.update', 'Modifier les documents', 'documents'],
            ['documents.archive', 'Archiver les documents', 'documents'],
            ['documents.download_sensitive', 'Télécharger documents sensibles', 'documents'],
        ];
        $insPerm = $pdo->prepare("INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())");
        foreach ($docPerms as $p) {
            $insPerm->execute([$tenantId, $p[1], $p[0], $p[2]]);
            $docPermIds[$p[0]] = (int) $pdo->lastInsertId();
        }
    }
    $adminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'tenant_admin') . ' LIMIT 1');
    $adminRole->execute([$tenantId]);
    $adminRoleId = (int) ($adminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
    if ($adminRoleId) {
        $link = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($docPermIds as $pid) {
            $link->execute([$adminRoleId, $pid]);
        }
    }
    $memberRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'member') . ' LIMIT 1');
    $memberRole->execute([$tenantId]);
    $memberRoleId = (int) ($memberRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
    if ($memberRoleId && isset($docPermIds['documents.view'])) {
        $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$memberRoleId, $docPermIds['documents.view']]);
    }
    $officerRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'officer') . ' LIMIT 1');
    $officerRole->execute([$tenantId]);
    $officerRoleId = (int) ($officerRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
    if (!$officerRoleId) {
        $pdo->prepare("INSERT INTO roles (tenant_id, name, slug, description, is_system, created_at) VALUES (?, 'Officier', 'officer', 'Encadrement, organisations, équipes', 1, NOW())")->execute([$tenantId]);
        $officerRoleId = (int) $pdo->lastInsertId();
    }
    if ($officerRoleId) {
        $link = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach (['documents.view', 'documents.upload', 'documents.update'] as $slug) {
            if (isset($docPermIds[$slug])) {
                $link->execute([$officerRoleId, $docPermIds[$slug]]);
            }
        }
    }
    echo "Permissions documents.* ajoutées et liées aux rôles.\n";

    $stmt = $pdo->prepare("SELECT 1 FROM document_categories WHERE tenant_id = ? LIMIT 1");
    $stmt->execute([$tenantId]);
    if (!$stmt->fetch()) {
        foreach ([['Doctrine / SOP', 'doctrine'], ['Manuel opérateur', 'manuel'], ['Fiche équipement', 'fiche-equipement'], ['Rapport mission', 'rapport'], ['Média pédagogique', 'media']] as $i => $c) {
            $pdo->prepare("INSERT INTO document_categories (tenant_id, name, slug, color, created_at) VALUES (?, ?, ?, ?, NOW())")->execute([$tenantId, $c[0], $c[1], ['emerald', 'blue', 'amber', 'slate', 'violet'][$i] ?? null]);
        }
        echo "Catégories documents par défaut créées.\n";
    }
    $stmt = $pdo->prepare("SELECT 1 FROM equipment_classes WHERE tenant_id = ? LIMIT 1");
    $stmt->execute([$tenantId]);
    if (!$stmt->fetch()) {
        foreach ([['Radio', 'radio', 'radio'], ['Optique', 'optic', 'optic'], ['Armement', 'weapon', 'weapon'], ['Véhicule', 'vehicle', 'vehicle'], ['Drone', 'drone', 'drone'], ['Médical', 'medical', 'medical']] as $c) {
            $pdo->prepare("INSERT INTO equipment_classes (tenant_id, name, slug, category, description, created_at) VALUES (?, ?, ?, ?, NULL, NOW())")->execute([$tenantId, $c[0], $c[1], $c[2]]);
        }
        echo "Classes d'équipement par défaut créées.\n";
    }
};

// ----- Seed : tenant + role + grade + admin (ou mise à jour) -----
$stmt = $pdo->query('SELECT id FROM tenants WHERE ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'default') . ' LIMIT 1');
if ($stmt && $stmt->fetch()) {
    echo "Seed déjà appliqué (tenant default existe).\n";
    $row = $pdo->query('SELECT id FROM tenants WHERE ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'default') . ' LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $tenantId = (int) $row['id'];

    $stmt = $pdo->query("SELECT 1 FROM personnel_admin_panels WHERE tenant_id = $tenantId LIMIT 1");
    if ($stmt && !$stmt->fetch()) {
        echo "Création des panneaux admin par défaut...\n";
        $panels = [
            ['État civil', 'etat-civil', 'Identité et état civil', 10],
            ['Affectation', 'affectation', 'Unité, poste, affectation', 20],
            ['Formation', 'formation', 'Parcours et qualifications', 30],
            ['Sécurité / Clearance', 'securite', 'Niveaux de sécurité et habilitations', 40],
            ['Santé / Aptitude', 'sante', 'Aptitude médicale et restrictions', 50],
            ['Références / Notes', 'references-notes', 'Références et notes administratives', 60],
        ];
        foreach ($panels as $p) {
            $pdo->prepare("INSERT INTO personnel_admin_panels (tenant_id, name, slug, description, display_order) VALUES (?, ?, ?, ?, ?)")
                ->execute([$tenantId, $p[0], $p[1], $p[2], $p[3]]);
        }
        $pdo->exec("INSERT IGNORE INTO tenant_matricule_config (tenant_id, prefix, format_pattern, next_number, updated_at) VALUES ($tenantId, 'ATH', '{prefix}-{seq:5}', 1, NOW())");
    }

    $run_forum_seed($pdo, $tenantId);

    // S'assurer que la permission admin.access existe et est liée au rôle Administrator (menu Admin)
    $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'admin.access') . ' LIMIT 1');
    $stmt->execute([$tenantId]);
    if (!$stmt->fetch()) {
        $pdo->prepare("INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, 'Accès administration', 'admin.access', 'admin', NOW())")
            ->execute([$tenantId]);
        $permId = (int) $pdo->lastInsertId();
        $adminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'tenant_admin') . ' LIMIT 1');
        $adminRole->execute([$tenantId]);
        $adminRoleId = $adminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        if ($adminRoleId && $permId) {
            $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$adminRoleId, $permId]);
        }
        echo "Permission admin.access ajoutée et liée au rôle Administrator.\n";
    }

    // Permissions organisation (admin.system est réservé aux rôles site globaux)
    $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'admin.organization') . ' LIMIT 1');
    $stmt->execute([$tenantId]);
    if (!$stmt->fetch()) {
        $pdo->prepare("INSERT INTO permissions (tenant_id, name, slug, module, scope, created_at) VALUES (?, 'Administration organisationnelle', 'admin.organization', 'admin', 'community', NOW())")->execute([$tenantId]);
    }
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'community_owner') . ' LIMIT 1');
    $stmt->execute([$tenantId]);
    if (!$stmt->fetch()) {
        $pdo->prepare("INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES (?, 'Propriétaire communauté', 'community_owner', 'Gouvernance complète de la communauté (sans administration plateforme)', 1, 1, 'community', NOW())")->execute([$tenantId]);
        $coId = (int) $pdo->lastInsertId();
        $ta = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'tenant_admin') . ' LIMIT 1');
        $ta->execute([$tenantId]);
        $taId = $ta->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        if ($taId) {
            $rp = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id = ?');
            $rp->execute([(int) $taId]);
            $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            while ($row = $rp->fetch(PDO::FETCH_ASSOC)) {
                $pid = (int) $row['permission_id'];
                $chk = $pdo->prepare('SELECT slug FROM permissions WHERE id = ? LIMIT 1');
                $chk->execute([$pid]);
                $s = $chk->fetch(PDO::FETCH_ASSOC);
                if ($s && ($s['slug'] ?? '') === 'admin.system') {
                    continue;
                }
                $link->execute([$coId, $pid]);
            }
        }
        foreach (['admin.organization', 'admin.access'] as $permSlug) {
            $p = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
            $p->execute([$tenantId, $permSlug]);
            $permId = $p->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
            if ($permId) {
                $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$coId, $permId]);
            }
        }
        echo "Rôle community_owner créé (permissions communauté, sans admin.system tenant).\n";
    }
    $tenantAdminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'tenant_admin') . ' LIMIT 1');
    $tenantAdminRole->execute([$tenantId]);
    $tenantAdminRoleId = $tenantAdminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
    $permOrg = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'admin.organization') . ' LIMIT 1');
    $permOrg->execute([$tenantId]);
    $permOrgId = $permOrg->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
    if ($tenantAdminRoleId && $permOrgId) {
        $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$tenantAdminRoleId, $permOrgId]);
    }

    // Permissions Formation (LMS)
    $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'training.view') . ' LIMIT 1');
    $stmt->execute([$tenantId]);
    if (!$stmt->fetch()) {
        foreach ([['training.view', 'Voir les formations', 'training'], ['training.manage', 'Gérer les formations', 'training'], ['training.assign', 'Assigner des formations', 'training']] as $p) {
            $pdo->prepare("INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())")->execute([$tenantId, $p[1], $p[0], $p[2]]);
        }
        $adminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'tenant_admin') . ' LIMIT 1');
        $adminRole->execute([$tenantId]);
        $adminRoleId = $adminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        if ($adminRoleId) {
            $trainPerms = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::inLiterals($pdo, 'slug', ['training.view', 'training.manage', 'training.assign']));
            $trainPerms->execute([$tenantId]);
            while ($row = $trainPerms->fetch(PDO::FETCH_ASSOC)) {
                $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$adminRoleId, $row['id']]);
            }
        }
        echo "Permissions training.* ajoutées.\n";
    }

    $run_documents_seed($pdo, $tenantId);

    // Permissions Bureau Courrier
    $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'courrier.view') . ' LIMIT 1');
    $stmt->execute([$tenantId]);
    if (!$stmt->fetch()) {
        foreach ([['courrier.view', 'Voir le Bureau Courrier', 'courrier'], ['courrier.create', 'Créer des documents courrier', 'courrier'], ['courrier.validate', 'Valider des documents', 'courrier'], ['courrier.archive', 'Archiver des documents', 'courrier']] as $p) {
            $pdo->prepare("INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())")->execute([$tenantId, $p[1], $p[0], $p[2]]);
        }
        $adminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'tenant_admin') . ' LIMIT 1');
        $adminRole->execute([$tenantId]);
        $adminRoleId = $adminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        if ($adminRoleId) {
            $courrierPerms = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::inLiterals($pdo, 'slug', ['courrier.view', 'courrier.create', 'courrier.validate', 'courrier.archive']));
            $courrierPerms->execute([$tenantId]);
            while ($row = $courrierPerms->fetch(PDO::FETCH_ASSOC)) {
                $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$adminRoleId, $row['id']]);
            }
        }
        $memberRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'member') . ' LIMIT 1');
        $memberRole->execute([$tenantId]);
        $memberRoleId = $memberRole->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        if ($memberRoleId) {
            $pid = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'courrier.view') . ' LIMIT 1');
            $pid->execute([$tenantId]);
            $pidRow = $pid->fetch(PDO::FETCH_ASSOC);
            if ($pidRow) {
                $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$memberRoleId, $pidRow['id']]);
            }
        }
        echo "Permissions courrier.* ajoutées.\n";
    }

    try {
        $allTenants = $pdo->query('SELECT id FROM tenants');
        if ($allTenants) {
            while ($trow = $allTenants->fetch(PDO::FETCH_ASSOC)) {
                \App\Services\Community\TenantSeedHelper::ensureTenantPermissionCatalog($pdo, (int) $trow['id']);
            }
        }
        echo "Catalogue permissions tenant synchronisé.\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] Catalogue permissions : ' . $e->getMessage() . "\n";
    }

    try {
        \App\Services\Rbac\MilitaryRoleCatalogSyncService::syncAllTenants($pdo);
        echo "Catalogue rôles militaires (post-permissions) OK.\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] military_role_catalog_sync (post-permissions) : ' . $e->getMessage() . "\n";
    }

    // LMS : type de leçon canvas (slides / modales)
    try {
        $tc = $pdo->query("SHOW TABLES LIKE 'training_lessons'");
        if ($tc && $tc->fetch()) {
            $col = $pdo->query("SHOW COLUMNS FROM training_lessons LIKE 'lesson_type'");
            $row = $col ? $col->fetch(PDO::FETCH_ASSOC) : null;
            if ($row && is_string($row['Type'] ?? null) && stripos($row['Type'], 'canvas') === false) {
                $pdo->exec(
                    "ALTER TABLE training_lessons MODIFY COLUMN lesson_type ENUM("
                    . "'richtext','video','pdf','audio','scorm_like','checklist','external_link','canvas'"
                    . ") NOT NULL DEFAULT 'richtext'"
                );
                echo "training_lessons.lesson_type : valeur « canvas » ajoutée.\n";
            }
            $col2 = $pdo->query("SHOW COLUMNS FROM training_lessons LIKE 'lesson_type'");
            $row2 = $col2 ? $col2->fetch(PDO::FETCH_ASSOC) : null;
            if ($row2 && is_string($row2['Type'] ?? null) && stripos($row2['Type'], 'quiz') === false) {
                $pdo->exec(
                    "ALTER TABLE training_lessons MODIFY COLUMN lesson_type ENUM("
                    . "'richtext','video','pdf','audio','scorm_like','checklist','external_link','canvas',"
                    . "'quiz','modals','video_embed','video_integrated','slideshow'"
                    . ") NOT NULL DEFAULT 'richtext'"
                );
                echo "training_lessons.lesson_type : quiz, modals, video_embed, video_integrated, slideshow ajoutés.\n";
            }
        }
    } catch (Throwable $e) {
        echo '  [ATTENTION] training_lessons canvas : ' . $e->getMessage() . "\n";
    }

    try {
        $fr = $pdo->query("SHOW TABLES LIKE 'forum_reports'");
        if ($fr && $fr->fetch()) {
            $ck = $pdo->query("SHOW COLUMNS FROM forum_reports LIKE 'content_kind'");
            if ($ck && !$ck->fetch()) {
                $pdo->exec('ALTER TABLE forum_reports ADD COLUMN content_kind VARCHAR(64) NULL DEFAULT NULL');
                echo "forum_reports.content_kind ajouté (signalements étendus).\n";
            }
        }
    } catch (Throwable $e) {
        echo '  [ATTENTION] forum_reports.content_kind : ' . $e->getMessage() . "\n";
    }

    echo "Seed déjà présent — poursuite des migrations annexes (Discord, ATAK, etc.).\n";
    $migrationFlush();
} else {
    echo "Insertion du tenant et admin par défaut...\n";
    $pdo->exec("INSERT INTO tenants (name, slug, created_at, updated_at) VALUES ('Pas d''organisation', 'default', NOW(), NOW())");
    $tenantId = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES ($tenantId, 'Propriétaire communauté', 'community_owner', 'Gouvernance complète de la communauté', 1, 1, 'community', NOW())");
    $communityOwnerRoleId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES ($tenantId, 'Administrator', 'tenant_admin', 'Accès administration organisation', 1, 0, 'community', NOW())");
    $tenantAdminRoleId = (int) $pdo->lastInsertId();
    $roleId = $communityOwnerRoleId;

    // La table `grades` peut être l'ancien modèle tenant (tenant_id/name/short_name)
    // ou le référentiel multi-doctrine après bascule (grade_system_id/label_short).
    // Sur une installation neuve, la bascule référentielle a déjà eu lieu : on rattache
    // alors l'admin à un grade officier existant du référentiel (ou NULL) plutôt que
    // d'insérer des colonnes disparues.
    $gradesHasTenantCol = $pdo->query(
        "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() "
        . "AND TABLE_NAME = 'grades' AND COLUMN_NAME = 'tenant_id' LIMIT 1"
    );
    if ($gradesHasTenantCol && $gradesHasTenantCol->fetchColumn()) {
        $pdo->exec("INSERT INTO grades (tenant_id, name, short_name, rank_order, created_at) VALUES ($tenantId, 'Officer', 'OFR', 10, NOW())");
        $gradeId = (int) $pdo->lastInsertId();
    } else {
        $refGrade = $pdo->query(
            "SELECT id FROM grades WHERE is_active = 1 "
            . "ORDER BY (is_commissioned = 1) DESC, sort_order ASC, id ASC LIMIT 1"
        );
        $refGradeId = $refGrade ? $refGrade->fetchColumn() : false;
        $gradeId = $refGradeId !== false && $refGradeId !== null ? (int) $refGradeId : null;
    }

    $hash = password_hash('admin', PASSWORD_ARGON2ID);
    $pdo->prepare("INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, role_id, grade_id, status, created_at, updated_at) VALUES (?, 'admin@athena.local', ?, 'Admin', 'ADMIN', ?, ?, 'active', NOW(), NOW())")
        ->execute([$tenantId, $hash, $roleId, $gradeId]);

    $siteGlobalId = $pdo->query('SELECT id FROM roles WHERE tenant_id IS NULL AND ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'site_super_admin') . ' LIMIT 1')->fetchColumn();
    if ($siteGlobalId) {
        $pdo->prepare('INSERT IGNORE INTO site_role_assignments (email_normalized, role_id, created_at) VALUES (?, ?, NOW())')
            ->execute(['admin@athena.local', (int) $siteGlobalId]);
    }

    $panels = [
        ['État civil', 'etat-civil', 'Identité et état civil', 10],
        ['Affectation', 'affectation', 'Unité, poste, affectation', 20],
        ['Formation', 'formation', 'Parcours et qualifications', 30],
        ['Sécurité / Clearance', 'securite', 'Niveaux de sécurité et habilitations', 40],
        ['Santé / Aptitude', 'sante', 'Aptitude médicale et restrictions', 50],
        ['Références / Notes', 'references-notes', 'Références et notes administratives', 60],
    ];
    foreach ($panels as $p) {
        $pdo->prepare("INSERT INTO personnel_admin_panels (tenant_id, name, slug, description, display_order) VALUES (?, ?, ?, ?, ?)")
            ->execute([$tenantId, $p[0], $p[1], $p[2], $p[3]]);
    }
    $pdo->exec("INSERT INTO tenant_matricule_config (tenant_id, prefix, format_pattern, next_number, updated_at) VALUES ($tenantId, 'ATH', '{prefix}-{seq:5}', 1, NOW())");

    $run_forum_seed($pdo, $tenantId);
    $run_documents_seed($pdo, $tenantId);

    try {
        $allTenants = $pdo->query('SELECT id FROM tenants');
        if ($allTenants) {
            while ($trow = $allTenants->fetch(PDO::FETCH_ASSOC)) {
                \App\Services\Community\TenantSeedHelper::ensureTenantPermissionCatalog($pdo, (int) $trow['id']);
            }
        }
        echo "Catalogue permissions tenant synchronisé.\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] Catalogue permissions : ' . $e->getMessage() . "\n";
    }

    try {
        \App\Services\Rbac\MilitaryRoleCatalogSyncService::syncAllTenants($pdo);
        echo "Catalogue rôles militaires (seed tenant) OK.\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] military_role_catalog_sync (seed) : ' . $e->getMessage() . "\n";
    }

    echo "Seed OK. Compte : admin@athena.local / admin\n";
}

// ----- Migrations annexes (toujours, même si le tenant default existait déjà) -----
// Avant : exit(0) dès que slug=default existait → discord_recruitment, ATAK, etc. ne tournaient jamais en prod.
$discordRecruitmentMigrate = require $root . '/bootstrap/discord_recruitment_migration.php';
try {
    echo "Migration discord_recruitment (recrutement via Discord)...\n";
    $discordRecruitmentMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] discord_recruitment : ' . $e->getMessage() . "\n";
}

$enlistmentCustomAnswersMigrate = require $root . '/bootstrap/enlistment_custom_answers_migration.php';
try {
    echo "Migration enlistment_custom_answers (réponses questions perso + refus auto)...\n";
    $enlistmentCustomAnswersMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] enlistment_custom_answers : ' . $e->getMessage() . "\n";
}

$personnelRoleConsolidationMigrate = require $root . '/bootstrap/personnel_role_consolidation_migration.php';
try {
    echo "Migration personnel_role_consolidation (fusion rôle métier / primary_role / secondary_role)...\n";
    $personnelRoleConsolidationMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] personnel_role_consolidation : ' . $e->getMessage() . "\n";
}

$defaultTenantCleanupMigrate = require $root . '/bootstrap/default_tenant_cleanup_migration.php';
try {
    echo "Nettoyage default_tenant_cleanup (artefacts du tenant système)...\n";
    $defaultTenantCleanupMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] default_tenant_cleanup : ' . $e->getMessage() . "\n";
}

$roleplayBilanCadenceMigrate = require $root . '/bootstrap/roleplay_bilan_cadence_migration.php';
try {
    echo "Migration roleplay_bilan_cadence (date du dernier bilan RP)...\n";
    $roleplayBilanCadenceMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] roleplay_bilan_cadence : ' . $e->getMessage() . "\n";
}

$roleplayDeadlinesMedicalRotationMigrate = require $root . '/bootstrap/roleplay_deadlines_medical_rotation_migration.php';
try {
    echo "Migration roleplay_deadlines_medical_rotation (groupe sanguin + type de rotation)...\n";
    $roleplayDeadlinesMedicalRotationMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] roleplay_deadlines_medical_rotation : ' . $e->getMessage() . "\n";
}

$tacticalPhonePairingMigrate = require $root . '/bootstrap/tactical_phone_pairing_migration.php';
try {
    echo "Migration tactical_phone_pairing (connexion téléphone ATAK)...\n";
    $tacticalPhonePairingMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tactical_phone_pairing : ' . $e->getMessage() . "\n";
}

$tacticalGameLinkMigrate = require $root . '/bootstrap/tactical_game_link_migration.php';
try {
    echo "Migration tactical_game_link (code de liaison Arma)...\n";
    $tacticalGameLinkMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tactical_game_link : ' . $e->getMessage() . "\n";
}

require_once $root . '/bootstrap/atak_map_gateway_migration.php';
try {
    echo "Migration atak_map_gateway (passerelle ATAK inter-équipes)...\n";
    run_atak_map_gateway_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_map_gateway : ' . $e->getMessage() . "\n";
}

require_once $root . '/bootstrap/atak_beta_registrations_migration.php';
try {
    echo "Migration atak_beta_registrations (accès anticipé Overwatch)...\n";
    run_atak_beta_registrations_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_beta_registrations : ' . $e->getMessage() . "\n";
}

require_once $root . '/bootstrap/atak_mod_reports_migration.php';
try {
    echo "Migration atak_mod_reports (rapports erreurs / bugs Overwatch)...\n";
    run_atak_mod_reports_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_mod_reports : ' . $e->getMessage() . "\n";
}

require_once $root . '/bootstrap/atak_device_logs_migration.php';
try {
    echo "Migration atak_device_logs (journal des appareils ATAK)...\n";
    run_atak_device_logs_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_device_logs : ' . $e->getMessage() . "\n";
}

$tenantAtakAccessKeyMigrate = require $root . '/bootstrap/tenant_atak_access_key_migration.php';
try {
    echo "Migration tenant_atak_access_key (clé d’accès Overwatch par communauté)...\n";
    $tenantAtakAccessKeyMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_atak_access_key : ' . $e->getMessage() . "\n";
}

$tenantAtakMaintenanceMigrate = require $root . '/bootstrap/tenant_atak_maintenance_migration.php';
try {
    echo "Migration tenant_atak_maintenance (mode maintenance ATAK / Tacmap)...\n";
    $tenantAtakMaintenanceMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_atak_maintenance : ' . $e->getMessage() . "\n";
}

$atakModulesSchemaMigrate = require $root . '/bootstrap/atak_modules_schema_migration.php';
try {
    echo "Migration atak_modules_schema (rapports, POI, zones, MEDEVAC, QRF, véhicules…)...\n";
    $migrationFlush();
    $atakModulesSchemaMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_modules_schema : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakIcemanReportsMigrate = require $root . '/bootstrap/atak_iceman_reports_migration.php';
try {
    echo "Migration atak_iceman_reports (types Reports ATAK Enhanced)…\n";
    $atakIcemanReportsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_iceman_reports : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$c2PillarsMigrate = require $root . '/bootstrap/c2_pillars_migration.php';
try {
    echo "Migration c2_pillars (appui-feu, zones danger, logistique, intel, replay, IFF)...\n";
    $migrationFlush();
    $c2PillarsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] c2_pillars : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$theatreMissionCycleMigrate = require $root . '/bootstrap/theatre_mission_cycle_migration.php';
try {
    echo "Migration theatre_mission_cycles (cycle briefing → exécution → après-action)...\n";
    $migrationFlush();
    $theatreMissionCycleMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] theatre_mission_cycles : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakRealismRegistryMigrate = require $root . '/bootstrap/atak_realism_registry_migration.php';
try {
    echo "Migration atak_realism_registry (terminaux ATAK, certificats, rattachements opérateur)...\n";
    $migrationFlush();
    $atakRealismRegistryMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_realism_registry : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$arsenalWardrobeMigrate = require $root . '/bootstrap/arsenal_wardrobe_migration.php';
try {
    echo "Migration arsenal_wardrobe (wardrobes ACE Arsenal + collections d’équipement)...\n";
    $migrationFlush();
    $arsenalWardrobeMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] arsenal_wardrobe : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$aarReportsMigrate = require $root . '/bootstrap/aar_reports_migration.php';
try {
    echo "Migration aar_reports (comptes rendus post-op structurés)...\n";
    $migrationFlush();
    $aarReportsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] aar_reports : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$rolePermissionMatrixMigrate = require $root . '/bootstrap/role_permission_matrix_migration.php';
try {
    echo "Migration role_permission_matrix (matrice rôles & permissions par module)...\n";
    $migrationFlush();
    $rolePermissionMatrixMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] role_permission_matrix : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$communityEventRsvpNominativeMigrate = require $root . '/bootstrap/community_event_rsvp_nominative_migration.php';
try {
    echo "Migration community_event_rsvp_nominative (disponibilité, relances, commentaires orga)...\n";
    $migrationFlush();
    $communityEventRsvpNominativeMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_event_rsvp_nominative : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$tenantAtakExperienceMigrate = require $root . '/bootstrap/tenant_atak_experience_migration.php';
try {
    echo "Migration tenant_atak_experience (expérience Overwatch par communauté)...\n";
    $migrationFlush();
    $tenantAtakExperienceMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_atak_experience : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$tenantAtakPhotoHudMigrate = require $root . '/bootstrap/tenant_atak_photo_hud_migration.php';
try {
    echo "Migration tenant_atak_photo_hud (bandeau d’identification des photos terrain)...\n";
    $migrationFlush();
    $tenantAtakPhotoHudMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_atak_photo_hud : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$tenantAtakRoleplayMigrate = require $root . '/bootstrap/tenant_atak_roleplay_config_migration.php';
try {
    echo "Migration tenant_atak_roleplay (configuration roleplay — réseau, capteurs, liaison)...\n";
    $migrationFlush();
    $tenantAtakRoleplayMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_atak_roleplay : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$fireTeamsMigrate = require $root . '/bootstrap/fire_teams_migration.php';
try {
    echo "Migration fire_teams (équipes de feu mission + organigramme)...\n";
    $migrationFlush();
    $fireTeamsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] fire_teams : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$missionPlanningMigrate = require $root . '/bootstrap/mission_planning_migration.php';
try {
    echo "Migration mission_planning (planification / organisation de combat)...\n";
    $migrationFlush();
    $missionPlanningMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] mission_planning : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakOrdersMigrate = require $root . '/bootstrap/atak_orders_migration.php';
try {
    echo "Migration atak_orders (ordres C2 Tacmap)...\n";
    $atakOrdersMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_orders : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakOrdersV2Migrate = require $root . '/bootstrap/atak_orders_v2_migration.php';
try {
    echo "Migration atak_orders v2 (destinataires, ACK, délais radio, ID militaire)...\n";
    $atakOrdersV2Migrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_orders_v2 : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakOrdersSinceMigrate = require $root . '/bootstrap/atak_orders_since_migration.php';
try {
    echo "Migration atak_orders since (updated_at + index poll delta)...\n";
    $atakOrdersSinceMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_orders_since : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakNineLineKindMigrate = require $root . '/bootstrap/atak_nine_line_mission_kind_migration.php';
try {
    echo "Migration atak_nine_line.mission_kind (CAS vs MEDEVAC)...\n";
    $atakNineLineKindMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_nine_line_mission_kind : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakOrderTemplatesMigrate = require $root . '/bootstrap/atak_order_templates_migration.php';
try {
    echo "Migration atak_order_templates (modèles d’ordres personnalisés)...\n";
    $atakOrderTemplatesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_order_templates : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakOrderTypesMigrate = require $root . '/bootstrap/atak_order_types_migration.php';
try {
    echo "Migration atak_order_types (types d’ordres personnalisés)...\n";
    $atakOrderTypesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_order_types : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakUnitsPositionMigrate = require $root . '/bootstrap/atak_units_position_migration.php';
try {
    echo "Migration atak_units_position (coordonnées carte pos_x / pos_y)...\n";
    $atakUnitsPositionMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_units_position : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakUnitMotionMigrate = require $root . '/bootstrap/atak_unit_motion_migration.php';
try {
  echo "Migration atak_unit_motion (cinématique BFT et destinations)...\n";
  $atakUnitMotionMigrate($pdo);
} catch (Throwable $e) {
  echo '  [ATTENTION] atak_unit_motion : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakCopTerrainMigrate = require $root . '/bootstrap/atak_cop_terrain_migration.php';
try {
  echo "Migration atak_cop_terrain (relief de théâtre et événements d’analyse)...\n";
  $atakCopTerrainMigrate($pdo);
} catch (Throwable $e) {
  echo '  [ATTENTION] atak_cop_terrain : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakGeoNetworkMigrate = require $root . '/bootstrap/atak_geo_network_migration.php';
try {
  echo "Migration atak_geo_network (lieux nommés et segments routiers)...\n";
  $atakGeoNetworkMigrate($pdo);
} catch (Throwable $e) {
  echo '  [ATTENTION] atak_geo_network : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakMedicalTriageMigrate = require $root . '/bootstrap/atak_medical_triage_migration.php';
try {
    echo "Migration atak_medical_alert_triage (triage alertes médicales)...\n";
    $atakMedicalTriageMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_medical_alert_triage : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakChatSourceMigrate = require $root . '/bootstrap/atak_chat_source_migration.php';
try {
    echo "Migration atak_chat_messages.source (origine terrain / poste de commandement)...\n";
    $atakChatSourceMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_chat_messages.source : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakExplosiveTimersMigrate = require $root . '/bootstrap/atak_explosive_timers_migration.php';
try {
    echo "Migration atak_explosive_timers (charges à retardement ATAK)...\n";
    $atakExplosiveTimersMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_explosive_timers : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakExplosiveCommandMigrate = require $root . '/bootstrap/atak_explosive_command_migration.php';
try {
    echo "Migration atak_explosive_command (déclenchement TOC des charges ACE)...\n";
    $atakExplosiveCommandMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_explosive_command : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$sseCliLog = static function (string $m): void {
    echo $m;
};

$atakSsePersonsMigrate = require $root . '/bootstrap/atak_sse_persons_migration.php';
try {
    echo "Migration atak_sse_persons (SSE — personnes, photos, sites, watchlist)…\n";
    $atakSsePersonsMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_persons : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSsePortalMigrate = require $root . '/bootstrap/atak_sse_portal_migration.php';
try {
    echo "Migration atak_sse_portal (dossiers, codes d’accès, notes, preuves)…\n";
    $atakSsePortalMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_portal : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseInterestMigrate = require $root . '/bootstrap/atak_sse_interest_cases_migration.php';
try {
    echo "Migration atak_sse_interest_cases (SSE — dossiers d’intérêt)…\n";
    $atakSseInterestMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_interest_cases : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseInterestEnrichMigrate = require $root . '/bootstrap/atak_sse_interest_case_enrichment_migration.php';
try {
    echo "Migration atak_sse_interest_case_enrichment (SSE — DI description, ACL, journal, délais)…\n";
    $atakSseInterestEnrichMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_interest_case_enrichment : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseCaseOriginMigrate = require $root . '/bootstrap/atak_sse_case_origin_migration.php';
try {
    echo "Migration atak_sse_case_origin (SSE — origine des dossiers)…\n";
    $atakSseCaseOriginMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_case_origin : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseCrossDecisionsMigrate = require $root . '/bootstrap/atak_sse_cross_decisions_migration.php';
try {
    echo "Migration atak_sse_cross_decisions (SSE — décisions de rapprochement)…\n";
    $atakSseCrossDecisionsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_cross_decisions : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseTextLibraryMigrate = require $root . '/bootstrap/atak_sse_text_library_migration.php';
try {
    echo "Migration atak_sse_text_library (SSE — bibliothèque rédactionnelle)…\n";
    $atakSseTextLibraryMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_text_library : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseCaseMapMigrate = require $root . '/bootstrap/atak_sse_case_map_migration.php';
try {
    echo "Migration atak_sse_case_map (SSE — carte permanente des dossiers)…\n";
    $atakSseCaseMapMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_case_map : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseAnalyticalMigrate = require $root . '/bootstrap/atak_sse_analytical_migration.php';
try {
    echo "Migration atak_sse_analytical (SSE — appréciation, lacunes, décisions)…\n";
    $atakSseAnalyticalMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_analytical : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseEngineMigrate = require $root . '/bootstrap/atak_sse_engine_migration.php';
try {
    echo "Migration atak_sse_engine (SSE — file suggestions + signaux moteur)…\n";
    $atakSseEngineMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_engine : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseMeshesMigrate = require $root . '/bootstrap/atak_sse_meshes_migration.php';
try {
    echo "Migration atak_sse_meshes (SSE — toiles de données)…\n";
    $atakSseMeshesMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_meshes : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseDigitalLabMigrate = require $root . '/bootstrap/atak_sse_digital_lab_migration.php';
try {
    echo "Migration atak_sse_digital_lab (SSE — laboratoire numérique)…\n";
    $atakSseDigitalLabMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_digital_lab : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseDomexPacketsMigrate = require $root . '/bootstrap/atak_sse_domex_packets_migration.php';
try {
    echo "Migration atak_sse_domex_packets (SSE — DOMEX paquets)…\n";
    $atakSseDomexPacketsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_domex_packets : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseArmaModelsMigrate = require $root . '/bootstrap/atak_sse_arma_models_migration.php';
try {
    echo "Migration atak_sse_arma_models (SSE — modèles mission Arma)…\n";
    $atakSseArmaModelsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_arma_models : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseIntelFoundationMigrate = require $root . '/bootstrap/atak_sse_intel_foundation_migration.php';
try {
    echo "Migration atak_sse_intel_foundation (SSE — LOT 1 Intelligence Workspace)…\n";
    $atakSseIntelFoundationMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_intel_foundation : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseTerrainLot3Migrate = require $root . '/bootstrap/atak_sse_terrain_lot3_migration.php';
try {
    echo "Migration atak_sse_terrain_lot3 (SSE — LOT 3 Terrain)…\n";
    $atakSseTerrainLot3Migrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_terrain_lot3 : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseIntelCycleLot4Migrate = require $root . '/bootstrap/atak_sse_intel_cycle_lot4_migration.php';
try {
    echo "Migration atak_sse_intel_cycle_lot4 (SSE — LOT 4 Cycle renseignement)…\n";
    $atakSseIntelCycleLot4Migrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_intel_cycle_lot4 : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseMapLayersLot5Migrate = require $root . '/bootstrap/atak_sse_map_layers_lot5_migration.php';
try {
    echo "Migration atak_sse_map_layers_lot5 (SSE — LOT 5 Calques ATAK)…\n";
    $atakSseMapLayersLot5Migrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_map_layers_lot5 : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseAnalysisLot6Migrate = require $root . '/bootstrap/atak_sse_analysis_lot6_migration.php';
try {
    echo "Migration atak_sse_analysis_lot6 (SSE — LOT 6 Analyse)…\n";
    $atakSseAnalysisLot6Migrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_analysis_lot6 : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseRobustnessLot7Migrate = require $root . '/bootstrap/atak_sse_robustness_lot7_migration.php';
try {
    echo "Migration atak_sse_robustness_lot7 (SSE — LOT 7 Robustesse)…\n";
    $atakSseRobustnessLot7Migrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_robustness_lot7 : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakSseFieldNotesMigrate = require $root . '/bootstrap/atak_sse_field_notes_migration.php';
try {
    echo "Migration atak_sse_field_notes (SSE — fiches de renseignement simplifiées)…\n";
    $atakSseFieldNotesMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_field_notes : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakDonationsMigrate = require $root . '/bootstrap/atak_donations_migration.php';
try {
    echo "Migration atak_donations (financement ATAK)...\n";
    $atakDonationsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_donations : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$atakForumChannelsSeed = require $root . '/bootstrap/atak_forum_channels_seed.php';
try {
    echo "Seed forum ATAK / COMSPEC (changelogs, FAQ, retours)...\n";
    $atakForumChannelsSeed($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_forum_channels_seed : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

$trainingGroupsMigrate = require $root . '/bootstrap/training_groups_migration.php';
try {
    echo "Migration training_groups (cohortes de formation)...\n";
    $trainingGroupsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_groups : ' . $e->getMessage() . "\n";
}
$migrationEnsurePdo();

echo "\n--- Pipeline exécuté (résumé) ---\n";
echo "Schéma SQL (migrations/schema.sql) ; ensure colonnes (atak_units.*, users.deleted_*, tenants.tenant_type) ; bootstrap : community_platform, unit_commander, tenant_type, prod_import_gaps, rbac_three_layer, user_roles, tenant_user_roles_graph + co_unit triggers, permissions_action ;\n";
echo "LMS (thème, vitrine, engagement, parcours portail) ; migrations forum/alerts/modération/e-mail/modo système ; training enrichments ; personnel job roles ; messages enrôlement ; dashboard pins ;\n";
echo "Annexes post-seed (toujours) : discord_recruitment, ATAK (access_key, maintenance, modules schema, c2_pillars, experience, roleplay, orders…), atak_donations, training_groups ;\n";
echo "autoload (modération système) ; option TRAINING_ONBOARDING_ASSIGN_ALL ; seeds tenant default (forum, documents, permissions) si applicable.\n";
echo "Migrations terminées.\n";
if (PHP_SAPI !== 'cli') {
    echo "Si vous ne voyez que les premières lignes dans le navigateur, le script a tout de même pu aller au bout côté serveur — préférez : php run-migrations.php (ou php setup-database.php) en SSH pour une sortie complète.\n";
}
if (defined('COMSPEC_MIGRATIONS_WEB_FULL') && COMSPEC_MIGRATIONS_WEB_FULL) {
    require_once $root . '/bootstrap/migrations_full_post.php';
    comspec_run_all_supplementary_sql_files($pdo, $root, $migrationFlush);
    comspec_print_post_migration_report($pdo, $root, $migrationFlush);
}

if (PHP_SAPI === 'cli' && function_exists('migrations_web_write_last_run') === false) {
    // Journal CLI optionnel si l’UI web n’a pas chargé le helper
    $cliLogPath = $root . '/bootstrap/migrations_web_ui.php';
    if (is_file($cliLogPath)) {
        require_once $cliLogPath;
    }
}
if (PHP_SAPI === 'cli' && function_exists('migrations_web_write_last_run')) {
    // Le journal détaillé est surtout alimenté en mode web (ob_start). En CLI on marque un passage OK sommaire.
    migrations_web_write_last_run($root, [
        'started_at' => date('c'),
        'finished_at' => date('c'),
        'duration_sec' => null,
        'ok' => true,
        'mode' => 'cli',
        'summary_line' => 'Passage CLI terminé',
    ], "Migrations terminées (CLI).\n");
}
