<?php

declare(strict_types=1);

/**
 * Migrations idempotentes : plans d'abonnement, colonnes tenants (facturation / propriétaire).
 * Appelée depuis run-migrations.php.
 */
function run_community_platform_migration(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_plans (
        id int unsigned NOT NULL AUTO_INCREMENT,
        slug varchar(50) NOT NULL,
        name varchar(100) NOT NULL,
        sort_order int NOT NULL DEFAULT 0,
        features_json text,
        stripe_price_id_monthly varchar(100) DEFAULT NULL,
        stripe_price_id_yearly varchar(100) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->query("SELECT COUNT(*) FROM subscription_plans");
    if ($stmt && (int) $stmt->fetchColumn() === 0) {
        $free = json_encode([
            'forum' => true,
            'documents' => true,
            'training' => true,
            'atak' => false,
            'max_members' => 50,
            'community_create' => true,
        ], JSON_THROW_ON_ERROR);
        $std = json_encode([
            'forum' => true,
            'documents' => true,
            'training' => true,
            'atak' => true,
            'max_members' => 200,
            'community_create' => true,
        ], JSON_THROW_ON_ERROR);
        $pro = json_encode([
            'forum' => true,
            'documents' => true,
            'training' => true,
            'atak' => true,
            'analytics' => true,
            'events' => true,
            'max_members' => 2000,
            'community_create' => true,
        ], JSON_THROW_ON_ERROR);
        $ins = $pdo->prepare('INSERT INTO subscription_plans (slug, name, sort_order, features_json, created_at) VALUES (?, ?, ?, ?, NOW())');
        $ins->execute(['free', 'Gratuit', 10, $free]);
        $ins->execute(['standard', 'Standard', 20, $std]);
        $ins->execute(['pro', 'Pro', 30, $pro]);
        echo "Plans subscription_plans insérés (free, standard, pro).\n";
    }

    $cols = [
        'owner_user_id' => "ADD COLUMN owner_user_id int unsigned DEFAULT NULL COMMENT 'Utilisateur propriétaire créateur' AFTER settings",
        'plan_slug' => "ADD COLUMN plan_slug varchar(50) NOT NULL DEFAULT 'free' AFTER owner_user_id",
        'stripe_customer_id' => "ADD COLUMN stripe_customer_id varchar(100) DEFAULT NULL AFTER plan_slug",
        'stripe_subscription_id' => "ADD COLUMN stripe_subscription_id varchar(100) DEFAULT NULL AFTER stripe_customer_id",
        'subscription_status' => "ADD COLUMN subscription_status varchar(32) NOT NULL DEFAULT 'none' AFTER stripe_subscription_id",
        'subscription_current_period_end' => "ADD COLUMN subscription_current_period_end datetime DEFAULT NULL AFTER subscription_status",
    ];
    foreach ($cols as $col => $frag) {
        $check = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = " . $pdo->quote($col));
        if ($check && !$check->fetch()) {
            echo "Ajout tenants.$col...\n";
            $pdo->exec("ALTER TABLE tenants $frag");
        }
    }

    $idx = $pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND INDEX_NAME = 'tenants_plan_slug'");
    if ($idx && !$idx->fetch()) {
        try {
            $pdo->exec('ALTER TABLE tenants ADD KEY tenants_plan_slug (plan_slug)');
        } catch (PDOException) {
            // ignore
        }
    }

    $fk = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND CONSTRAINT_NAME = 'tenants_owner_user_id_fk'");
    if ($fk && !$fk->fetch()) {
        try {
            $pdo->exec('ALTER TABLE tenants ADD CONSTRAINT tenants_owner_user_id_fk FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (PDOException $e) {
            echo '  [ATTENTION] FK owner_user_id : ' . $e->getMessage() . "\n";
        }
    }

    $pdo->exec("UPDATE tenants SET plan_slug = 'free' WHERE plan_slug = '' OR plan_slug IS NULL");
}
