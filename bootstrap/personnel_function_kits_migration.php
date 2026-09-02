<?php

declare(strict_types=1);

/**
 * Choix des kits de fonctions par communauté. Idempotent.
 */
function run_personnel_function_kits_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $sqlFile = dirname(__DIR__) . '/migrations/20260902000001_personnel_function_kits.sql';
    if (!is_file($sqlFile)) {
        echo "  [ATTENTION] personnel_function_kits : fichier SQL introuvable\n";

        return;
    }

    if ($hasTable('tenant_function_kit_state')) {
        echo "  [OK] tenant_function_kit_state déjà présent\n";

        return;
    }

    $sql = (string) file_get_contents($sqlFile);
    $sql = preg_replace('/^--.*$/m', '', $sql) ?? $sql;
    $statements = array_values(array_filter(array_map('trim', explode(';', $sql))));
    foreach ($statements as $stmt) {
        if ($stmt === '') {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            echo '  [ATTENTION] personnel_function_kits : ' . $e->getMessage() . "\n";
        }
    }

    if ($hasTable('tenant_function_kit_state')) {
        echo "  [OK] tenant_function_kit_state créé\n";
    }
}
