<?php

declare(strict_types=1);

/**
 * Étape « Canaux de déploiement, campagnes, mises à jour applicatives et release du module Formation » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

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
                        $migrationRunner->warn('release_channels_tester_communities', $e);
                    }
                }
            }
            echo "  [OK] release_channels_tester_communities\n";
        }
    } else {
        echo "  [ATTENTION] Fichier absent : migrations/20260415000001_release_channels_and_tester_communities.sql\n";
    }
    $migrationFlush();
}

// Release minimale du module Formation sur chaque canal (référentiel : sans elle le module est refusé).
// Lancée une seule fois par passage, que la table vienne d'être créée ou non.
$stmtPm = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_modules'");
if ($stmtPm && $stmtPm->fetch()) {
    require_once $root . '/bootstrap/platform_training_release_seed.php';
    try {
        run_platform_training_release_seed($pdo);
    } catch (Throwable $e) {
        echo '  [ATTENTION] platform_training_release_seed : ' . $e->getMessage() . "\n";
        $migrationRunner->warn('platform_training_release_seed', $e);
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
                            $migrationRunner->warn('deployment_campaigns', $e);
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
            $migrationRunner->warn('deployment_jobs.campaign_id', $e);
        }
        try {
            $pdo->exec('ALTER TABLE deployment_jobs ADD COLUMN step_order SMALLINT UNSIGNED NULL AFTER target_channel_id');
        } catch (Throwable $e) {
            echo '  [ATTENTION] deployment_jobs.step_order : ' . $e->getMessage() . "\n";
            $migrationRunner->warn('deployment_jobs.step_order', $e);
        }
        try {
            $pdo->exec('ALTER TABLE deployment_jobs ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER log_path');
        } catch (Throwable $e) {
            echo '  [ATTENTION] deployment_jobs.created_at : ' . $e->getMessage() . "\n";
            $migrationRunner->warn('deployment_jobs.created_at', $e);
        }
        try {
            $pdo->exec('ALTER TABLE deployment_jobs ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
        } catch (Throwable $e) {
            echo '  [ATTENTION] deployment_jobs.updated_at : ' . $e->getMessage() . "\n";
            $migrationRunner->warn('deployment_jobs.updated_at', $e);
        }
        try {
            $pdo->exec('ALTER TABLE deployment_jobs ADD COLUMN error_message TEXT NULL AFTER updated_at');
        } catch (Throwable $e) {
            echo '  [ATTENTION] deployment_jobs.error_message : ' . $e->getMessage() . "\n";
            $migrationRunner->warn('deployment_jobs.error_message', $e);
        }
        try {
            $pdo->exec('ALTER TABLE deployment_jobs ADD KEY idx_deployment_jobs_campaign (campaign_id)');
        } catch (Throwable $e) {
            echo '  [ATTENTION] deployment_jobs idx campaign : ' . $e->getMessage() . "\n";
            $migrationRunner->warn('deployment_jobs idx campaign', $e);
        }
        try {
            $pdo->exec(
                'ALTER TABLE deployment_jobs ADD CONSTRAINT fk_deployment_jobs_campaign FOREIGN KEY (campaign_id) REFERENCES deployment_campaigns (id) ON DELETE CASCADE ON UPDATE CASCADE'
            );
        } catch (Throwable $e) {
            echo '  [ATTENTION] deployment_jobs FK campaign : ' . $e->getMessage() . "\n";
            $migrationRunner->warn('deployment_jobs FK campaign', $e);
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
                        $migrationRunner->warn('platform_app_updates', $e);
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
