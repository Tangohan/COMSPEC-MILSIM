<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Liens d'invitation partageables (/i/<code>) : extension de recruitment_invite_codes (code_kind = 'link') et
 * journal des usages recruitment_invite_link_events (clics, comptes créés, candidatures, adhésions).
 * Idempotent : chaque colonne, index et table n'est ajouté que s'il manque. Aucune ligne existante n'est modifiée :
 * les codes prioritaires gardent leur fonctionnement. Sans la table des codes (étape recruitment_invite_codes),
 * rien n'est fait.
 */
function run_recruitment_invite_links_migration(PDO $pdo): void
{
    $schema = new SchemaInspector($pdo);
    $table = 'recruitment_invite_codes';
    if (!$schema->tableExists($table)) {
        echo "  [OK] Table des codes d'invitation absente : rien à faire.\n";

        return;
    }
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $added = [];
    $columns = [
        'code_kind' => "VARCHAR(32) NOT NULL DEFAULT 'priority'",
        'public_code' => "VARCHAR(64) DEFAULT NULL COMMENT 'Code public du lien /i/<code>, unique sur tout le site'",
        'link_action' => "VARCHAR(16) DEFAULT NULL COMMENT 'apply = candidature, join = adhésion'",
        'requires_approval' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Adhésion soumise à validation'",
        'revoked_at' => "DATETIME DEFAULT NULL COMMENT 'Lien révoqué (réactivable)'",
    ];
    foreach ($columns as $column => $definition) {
        if ($schema->addColumnIfMissing($table, $column, $definition)) {
            $added[] = $table . '.' . $column;
        }
    }
    if (!$schema->indexExists($table, 'uq_invite_public_code')) {
        $pdo->exec($sqlite
            ? 'CREATE UNIQUE INDEX uq_invite_public_code ON recruitment_invite_codes (public_code)'
            : 'ALTER TABLE recruitment_invite_codes ADD UNIQUE KEY uq_invite_public_code (public_code)');
        $added[] = 'index uq_invite_public_code';
    }
    if (!$schema->tableExists('recruitment_invite_link_events')) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS recruitment_invite_link_events (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                invite_code_id INT UNSIGNED NOT NULL,
                event VARCHAR(16) NOT NULL COMMENT 'click, signup, application, join, request',
                user_id INT UNSIGNED DEFAULT NULL,
                enlistment_id INT UNSIGNED DEFAULT NULL,
                ip_hash CHAR(64) DEFAULT NULL COMMENT 'Empreinte de l adresse IP, jamais l adresse',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_link_event (invite_code_id, event),
                INDEX idx_tenant_created (tenant_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $added[] = 'table recruitment_invite_link_events';
    }
    echo $added === []
        ? "  [OK] Liens d'invitation déjà installés.\n"
        : '  [OK] Liens d\'invitation : ' . implode(', ', $added) . ".\n";
}
