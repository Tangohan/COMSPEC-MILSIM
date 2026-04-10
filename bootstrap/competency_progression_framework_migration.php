<?php

declare(strict_types=1);

/**
 * Cadre progression compétences (modules, tenant_modules, user_progress, journaux, etc.).
 * Complète {@see training_competency_framework_migration.php} (matrices / rôles formateur).
 */
return function (PDO $pdo): void {
    $root = dirname(__DIR__);
    $files = [
        $root . '/migrations/20260408000001_competency_progression_framework.sql',
        $root . '/migrations/20260408000002_competency_progression_logs.sql',
    ];
    foreach ($files as $path) {
        if (!is_file($path)) {
            echo '  [ATTENTION] competency_progression : fichier manquant ' . basename($path) . "\n";

            continue;
        }
        $sql = @file_get_contents($path);
        if ($sql === false || $sql === '') {
            echo '  [ATTENTION] competency_progression : lecture impossible ' . basename($path) . "\n";

            continue;
        }
        $sql = preg_replace('/--[^\r\n]*/', '', $sql);
        $chunks = preg_split('/;\s*[\r\n]+/', $sql);
        foreach ($chunks as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') {
                continue;
            }
            try {
                $pdo->exec($stmt . (str_ends_with($stmt, ';') ? '' : ';'));
            } catch (PDOException $e) {
                echo '  [ATTENTION] competency_progression (' . basename($path) . ') : ' . $e->getMessage() . "\n";
            }
        }
    }
    echo "  competency_progression_framework (SQL) : traité.\n";
};
