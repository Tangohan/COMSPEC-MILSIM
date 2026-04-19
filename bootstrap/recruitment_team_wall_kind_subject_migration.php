<?php

declare(strict_types=1);

/**
 * Fil équipe recrutement : type de message + sujet court (classement / filtres).
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $t = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recruitment_team_wall_entries' LIMIT 1");
    if (!$t || !$t->fetchColumn()) {
        return;
    }
    $c = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recruitment_team_wall_entries' AND COLUMN_NAME = 'post_kind' LIMIT 1");
    if ($c && $c->fetchColumn()) {
        return;
    }
    $pdo->exec('ALTER TABLE recruitment_team_wall_entries ADD COLUMN post_kind VARCHAR(32) NOT NULL DEFAULT \'general\' AFTER actor_user_id');
    $pdo->exec('ALTER TABLE recruitment_team_wall_entries ADD COLUMN subject VARCHAR(200) NULL DEFAULT NULL AFTER post_kind');
    $pdo->exec('ALTER TABLE recruitment_team_wall_entries ADD KEY idx_rtw_tenant_kind_created (tenant_id, post_kind, created_at)');
};
