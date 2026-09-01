<?php

declare(strict_types=1);

/**
 * Parcours d’intégration des nouveaux membres. Idempotent.
 * MariaDB : pas de colonne GENERATED sur user_id (erreur 1901) — colonne physique + triggers.
 */
function run_member_integration_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $expected = [
        'member_integration_templates',
        'member_integration_template_steps',
        'member_integrations',
        'member_integration_steps',
        'member_integration_referents',
        'member_integration_events',
        'member_integration_appointments',
        'member_integration_invitations',
        'member_integration_invitation_history',
    ];

    $sqlFile = dirname(__DIR__) . '/migrations/20260901000001_member_integration.sql';
    if (!is_file($sqlFile)) {
        echo "  [ATTENTION] member_integration : fichier SQL introuvable\n";

        return;
    }

    $sql = (string) file_get_contents($sqlFile);
    $sql = preg_replace('/^--.*$/m', '', $sql) ?? $sql;
    $statements = array_values(array_filter(array_map('trim', explode(';', $sql))));

    $runCreates = static function () use ($pdo, $statements): void {
        foreach ($statements as $stmt) {
            if ($stmt === '' || !preg_match('/CREATE TABLE/i', $stmt)) {
                continue;
            }
            try {
                $pdo->exec($stmt);
            } catch (Throwable $e) {
                echo '  [ATTENTION] member_integration : ' . $e->getMessage() . "\n";
            }
        }
    };

    $runCreates();

    if ($hasTable('member_integrations') && !$hasColumn('member_integrations', 'active_user_key')) {
        try {
            $pdo->exec(
                'ALTER TABLE member_integrations ADD COLUMN active_user_key INT UNSIGNED NULL DEFAULT NULL'
            );
        } catch (Throwable $e) {
            echo '  [ATTENTION] member_integration.active_user_key : ' . $e->getMessage() . "\n";
        }
        try {
            $pdo->exec(
                'ALTER TABLE member_integrations ADD UNIQUE KEY uq_mi_active_user (tenant_id, active_user_key)'
            );
        } catch (Throwable) {
        }
    }

    if ($hasTable('member_integrations') && $hasColumn('member_integrations', 'active_user_key')) {
        try {
            $pdo->exec(
                "UPDATE member_integrations SET active_user_key = IF(status IN ('completed', 'cancelled'), NULL, user_id)
                 WHERE active_user_key IS NULL AND status NOT IN ('completed', 'cancelled')"
            );
        } catch (Throwable $e) {
            echo '  [ATTENTION] member_integration backfill : ' . $e->getMessage() . "\n";
        }
        foreach (['mi_active_user_bi', 'mi_active_user_bu'] as $tn) {
            try {
                $pdo->exec('DROP TRIGGER IF EXISTS `' . $tn . '`');
            } catch (Throwable) {
            }
        }
        $setExpr = "NEW.active_user_key = IF(NEW.status IN ('completed', 'cancelled'), NULL, NEW.user_id)";
        try {
            $pdo->exec(
                'CREATE TRIGGER `mi_active_user_bi` BEFORE INSERT ON `member_integrations` FOR EACH ROW SET ' . $setExpr
            );
            $pdo->exec(
                'CREATE TRIGGER `mi_active_user_bu` BEFORE UPDATE ON `member_integrations` FOR EACH ROW SET ' . $setExpr
            );
        } catch (Throwable $e) {
            echo '  [ATTENTION] member_integration trigger : ' . $e->getMessage() . "\n";
        }
    }

    $missing = array_values(array_filter($expected, static fn (string $t): bool => !$hasTable($t)));
    if ($missing !== []) {
        $runCreates();
        $missing = array_values(array_filter($expected, static fn (string $t): bool => !$hasTable($t)));
    }

    if ($missing === []) {
        echo "  [OK] member_integration\n";

        return;
    }

    echo '  [ATTENTION] member_integration : tables manquantes — ' . implode(', ', $missing) . "\n";
}
