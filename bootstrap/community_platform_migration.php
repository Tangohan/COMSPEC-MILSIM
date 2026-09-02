<?php

declare(strict_types=1);

/**
 * Migrations idempotentes : plans d'abonnement, colonnes tenants (facturation / propriétaire).
 * Appelée depuis run-migrations.php.
 */

require_once dirname(__DIR__) . '/app/Support/SqlText.php';

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

    $limCol = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_plans' AND COLUMN_NAME = 'limits_json'");
    if ($limCol && !$limCol->fetch()) {
        echo "Ajout subscription_plans.limits_json...\n";
        $pdo->exec("ALTER TABLE subscription_plans ADD COLUMN limits_json text DEFAULT NULL COMMENT 'Quotas gratuit limité (JSON)' AFTER features_json");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_usage_counters (
        tenant_id int unsigned NOT NULL,
        metric_key varchar(64) NOT NULL,
        period_start date NOT NULL,
        amount int unsigned NOT NULL DEFAULT 0,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (tenant_id, metric_key, period_start),
        KEY tenant_metric (tenant_id, metric_key),
        CONSTRAINT fk_tuc_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $freeLimitsDefault = json_encode([
        'quotas' => [
            'events' => [
                'limit' => 3,
                'reset_period' => 'monthly',
                'soft_block_threshold' => 0.8,
                'soft_block_message' => 'Vous approchez de la limite de créations d’événements ce mois-ci.',
                'upgrade_cta' => 'platform/upgrade',
                'binds_feature' => 'events',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    require_once dirname(__DIR__) . '/app/Services/Billing/SubscriptionPlanFeaturesCatalog.php';
    $planFeatureDefaults = \App\Services\Billing\SubscriptionPlanFeaturesCatalog::defaultsByPlanSlug();

    $stmt = $pdo->query("SELECT COUNT(*) FROM subscription_plans");
    if ($stmt && (int) $stmt->fetchColumn() === 0) {
        $ins = $pdo->prepare('INSERT INTO subscription_plans (slug, name, sort_order, features_json, limits_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $ins->execute(['free', 'Gratuit', 10, json_encode($planFeatureDefaults['free'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $freeLimitsDefault]);
        $ins->execute(['standard', 'Standard', 20, json_encode($planFeatureDefaults['standard'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), null]);
        $ins->execute(['pro', 'Pro', 30, json_encode($planFeatureDefaults['pro'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), null]);
        $ins->execute(['pro_plus', 'Pro+', 40, json_encode($planFeatureDefaults['pro_plus'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), null]);
        echo "Plans subscription_plans insérés (free, standard, pro, pro_plus).\n";
    } else {
        $up = $pdo->prepare('UPDATE subscription_plans SET limits_json = ? WHERE ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'free') . ' AND (limits_json IS NULL OR limits_json = \'\')');
        $up->execute([$freeLimitsDefault]);
        $stdRow = $pdo->query('SELECT features_json FROM subscription_plans WHERE ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'standard') . ' LIMIT 1');
        $stdFeat = $stdRow ? $stdRow->fetch(PDO::FETCH_ASSOC) : false;
        if (is_array($stdFeat)) {
            $fj = json_decode((string) ($stdFeat['features_json'] ?? '{}'), true);
            if (is_array($fj) && empty($fj['events'])) {
                $fj['events'] = true;
                $pdo->prepare('UPDATE subscription_plans SET features_json = ? WHERE ' . \App\Support\SqlText::equals($pdo, 'slug'))->execute([
                    json_encode($fj, JSON_THROW_ON_ERROR),
                    'standard',
                ]);
                echo "Plan standard : événements activés (alignement payant).\n";
            }
        }
    }

    $proPlusExists = $pdo->query('SELECT 1 FROM subscription_plans WHERE ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'pro_plus') . ' LIMIT 1');
    if ($proPlusExists && !$proPlusExists->fetch()) {
        $insPp = $pdo->prepare('INSERT INTO subscription_plans (slug, name, sort_order, features_json, limits_json, created_at) VALUES (?, ?, ?, ?, NULL, NOW())');
        $insPp->execute(['pro_plus', 'Pro+', 40, json_encode($planFeatureDefaults['pro_plus'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
        echo "Plan pro_plus inséré.\n";
    }

    $paypalCols = [
        'paypal_plan_id_monthly' => "ADD COLUMN paypal_plan_id_monthly varchar(100) DEFAULT NULL COMMENT 'Plan PayPal Billing mensuel (P-…)' AFTER stripe_price_id_yearly",
        'paypal_plan_id_yearly' => "ADD COLUMN paypal_plan_id_yearly varchar(100) DEFAULT NULL COMMENT 'Plan PayPal Billing annuel (P-…)' AFTER paypal_plan_id_monthly",
    ];
    foreach ($paypalCols as $col => $frag) {
        $check = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_plans' AND COLUMN_NAME = " . $pdo->quote($col));
        if ($check && !$check->fetch()) {
            echo "Ajout subscription_plans.$col...\n";
            $pdo->exec("ALTER TABLE subscription_plans $frag");
        }
    }

    $mergeMissingPlanFeatures = static function (PDO $pdoConn, string $slug, array $defaults): void {
        $st = $pdoConn->prepare('SELECT features_json FROM subscription_plans WHERE ' . \App\Support\SqlText::equals($pdoConn, 'slug') . ' LIMIT 1');
        $st->execute([$slug]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return;
        }
        $cur = json_decode((string) ($row['features_json'] ?? '{}'), true);
        if (!is_array($cur)) {
            $cur = [];
        }
        $changed = false;
        foreach ($defaults as $k => $v) {
            if (!array_key_exists($k, $cur)) {
                $cur[$k] = $v;
                $changed = true;
            }
        }
        if ($changed) {
            $up = $pdoConn->prepare('UPDATE subscription_plans SET features_json = ? WHERE ' . \App\Support\SqlText::equals($pdoConn, 'slug'));
            $up->execute([json_encode($cur, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $slug]);
            echo "Plan {$slug} : clés de fonctionnalités complétées.\n";
        }
    };
    foreach ($planFeatureDefaults as $slug => $defaults) {
        $mergeMissingPlanFeatures($pdo, $slug, $defaults);
    }

    $cols = [
        // Profil communauté (Complet / Effectifs / ATAK) — aussi via bootstrap/tenant_type_migration.php
        'tenant_type' => "ADD COLUMN tenant_type VARCHAR(32) NOT NULL DEFAULT 'full' COMMENT 'Profil communauté : full | effectifs | atak' AFTER slug",
        'owner_user_id' => "ADD COLUMN owner_user_id int unsigned DEFAULT NULL COMMENT 'Utilisateur propriétaire créateur' AFTER settings",
        'plan_slug' => "ADD COLUMN plan_slug varchar(50) NOT NULL DEFAULT 'free' AFTER owner_user_id",
        'stripe_customer_id' => "ADD COLUMN stripe_customer_id varchar(100) DEFAULT NULL AFTER plan_slug",
        'stripe_subscription_id' => "ADD COLUMN stripe_subscription_id varchar(100) DEFAULT NULL AFTER stripe_customer_id",
        'paypal_subscription_id' => "ADD COLUMN paypal_subscription_id varchar(100) DEFAULT NULL AFTER stripe_subscription_id",
        'paypal_payer_id' => "ADD COLUMN paypal_payer_id varchar(100) DEFAULT NULL AFTER paypal_subscription_id",
        'subscription_status' => "ADD COLUMN subscription_status varchar(32) NOT NULL DEFAULT 'none' AFTER paypal_payer_id",
        'subscription_current_period_end' => "ADD COLUMN subscription_current_period_end datetime DEFAULT NULL AFTER subscription_status",
    ];
    foreach ($cols as $col => $frag) {
        $check = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = " . $pdo->quote($col));
        if ($check && !$check->fetch()) {
            echo "Ajout tenants.$col...\n";
            // Colonnes PayPal : position après stripe si déjà présentes
            if ($col === 'paypal_subscription_id') {
                $frag = "ADD COLUMN paypal_subscription_id varchar(100) DEFAULT NULL AFTER stripe_subscription_id";
            } elseif ($col === 'paypal_payer_id') {
                $frag = "ADD COLUMN paypal_payer_id varchar(100) DEFAULT NULL AFTER paypal_subscription_id";
            } elseif ($col === 'subscription_status') {
                $hasPaypal = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'paypal_payer_id'");
                if ($hasPaypal && $hasPaypal->fetch()) {
                    $frag = "ADD COLUMN subscription_status varchar(32) NOT NULL DEFAULT 'none' AFTER paypal_payer_id";
                } else {
                    $frag = "ADD COLUMN subscription_status varchar(32) NOT NULL DEFAULT 'none' AFTER stripe_subscription_id";
                }
            }
            try {
                $pdo->exec("ALTER TABLE tenants $frag");
            } catch (PDOException $e) {
                // Repli sans AFTER si schéma partiel
                if (str_contains($e->getMessage(), 'AFTER') || str_contains($e->getMessage(), 'Unknown column')) {
                    $simple = preg_replace('/\s+AFTER\s+\w+/i', '', $frag) ?? $frag;
                    $pdo->exec("ALTER TABLE tenants $simple");
                } else {
                    throw $e;
                }
            }
        }
    }

    $idxType = $pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND INDEX_NAME = 'idx_tenants_type'");
    if ($idxType && !$idxType->fetch()) {
        try {
            $pdo->exec('ALTER TABLE tenants ADD KEY idx_tenants_type (tenant_type)');
        } catch (PDOException) {
            // ignore
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

    $pdo->prepare('UPDATE tenants SET plan_slug = \'free\' WHERE ' . \App\Support\SqlText::equals($pdo, 'plan_slug') . ' OR plan_slug IS NULL')->execute(['']);

    $pdo->exec("CREATE TABLE IF NOT EXISTS pending_community_creates (
        id int unsigned NOT NULL AUTO_INCREMENT,
        token char(64) NOT NULL,
        user_id int unsigned NOT NULL,
        payload_json text NOT NULL,
        plan_slug varchar(50) NOT NULL,
        stripe_price_id varchar(100) NOT NULL,
        stripe_checkout_session_id varchar(255) DEFAULT NULL,
        tenant_id int unsigned DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY pcc_token (token),
        KEY pcc_user (user_id),
        KEY pcc_stripe_sess (stripe_checkout_session_id),
        KEY pcc_tenant (tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pccErr = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pending_community_creates' AND COLUMN_NAME = 'creation_error'");
    if ($pccErr && !$pccErr->fetch()) {
        echo "Ajout pending_community_creates.creation_error...\n";
        $pdo->exec('ALTER TABLE pending_community_creates ADD COLUMN creation_error text DEFAULT NULL AFTER tenant_id');
    }

    $pccProvider = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pending_community_creates' AND COLUMN_NAME = 'payment_provider'");
    if ($pccProvider && !$pccProvider->fetch()) {
        echo "Ajout pending_community_creates.payment_provider...\n";
        $pdo->exec("ALTER TABLE pending_community_creates ADD COLUMN payment_provider varchar(16) NOT NULL DEFAULT 'stripe' COMMENT 'stripe|paypal' AFTER stripe_price_id");
    }

    $pccPaypal = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pending_community_creates' AND COLUMN_NAME = 'paypal_subscription_id'");
    if ($pccPaypal && !$pccPaypal->fetch()) {
        echo "Ajout pending_community_creates.paypal_subscription_id...\n";
        $pdo->exec('ALTER TABLE pending_community_creates ADD COLUMN paypal_subscription_id varchar(100) DEFAULT NULL AFTER stripe_checkout_session_id');
        try {
            $pdo->exec('ALTER TABLE pending_community_creates ADD KEY pcc_paypal_sub (paypal_subscription_id)');
        } catch (PDOException) {
            // ignore
        }
    }

    $profSlug = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'profile_slug'");
    if ($profSlug && !$profSlug->fetch()) {
        echo "Ajout users.profile_slug...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_slug varchar(40) DEFAULT NULL COMMENT 'Identifiant URL fiche personnel (tenant)' AFTER callsign");
        $idx = $pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'users_tenant_profile_slug'");
        if ($idx && !$idx->fetch()) {
            try {
                $pdo->exec('ALTER TABLE users ADD UNIQUE KEY users_tenant_profile_slug (tenant_id, profile_slug)');
            } catch (PDOException $e) {
                echo '  [ATTENTION] Index profile_slug : ' . $e->getMessage() . "\n";
            }
        }
    }
}
