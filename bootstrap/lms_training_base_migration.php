<?php

declare(strict_types=1);

/**
 * Installe le socle LMS moderne (training_courses, training_lessons, training_enrollments,
 * quizzes, certificats…) sur une base neuve.
 *
 * Contexte : schema.sql crée d'anciennes tables (training_modules / training_progress /
 * training_certificates) au format « legacy ». Les tables modernes vivent dans
 * migrations/lms_training.sql, mais ce fichier n'était branché à aucun pipeline. Une
 * installation neuve n'avait donc pas de table `training_courses`, ce qui faisait planter le
 * tableau de bord (App\Repositories\TrainingCourseRepository) et toute la brique Formations.
 *
 * Idempotent : ne fait rien si `training_courses` existe déjà. Les RENAME legacy → legacy_*
 * sont appliqués conditionnellement (jamais deux fois) pour laisser la place aux tables
 * modernes portant le même nom.
 */
return function (PDO $pdo): void {
    $tableExists = static function (string $name) use ($pdo): bool {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$name]);

        return (bool) $stmt->fetchColumn();
    };

    if ($tableExists('training_courses')) {
        echo "  [OK] Socle LMS moderne : training_courses déjà présente.\n";

        return;
    }

    // Les tables legacy issues de schema.sql cèdent la place aux versions modernes.
    foreach (['training_modules', 'training_progress', 'training_certificates'] as $legacy) {
        $legacyTarget = 'legacy_' . $legacy;
        if ($tableExists($legacy) && !$tableExists($legacyTarget)) {
            $pdo->exec("RENAME TABLE `{$legacy}` TO `{$legacyTarget}`");
            echo "  [OK] LMS : {$legacy} conservée sous {$legacyTarget}.\n";
        }
    }

    $sqlPath = dirname(__DIR__) . '/migrations/lms_training.sql';
    $sql = @file_get_contents($sqlPath);
    if ($sql === false || $sql === '') {
        echo "  [ATTENTION] Socle LMS : migrations/lms_training.sql illisible — ignoré.\n";

        return;
    }

    // Les RENAME sont déjà gérés (de façon conditionnelle) ci-dessus : les retirer du script.
    $sql = preg_replace('/^\s*RENAME TABLE[^;]*;\s*$/mi', '', $sql) ?? $sql;
    $sql = preg_replace('/--[^\r\n]*/', '', $sql) ?? $sql;

    $created = 0;
    foreach (preg_split('/;\s*[\r\n]+/', $sql) ?: [] as $chunk) {
        $stmt = trim((string) $chunk);
        if ($stmt === '') {
            continue;
        }
        try {
            $pdo->exec($stmt);
            if (stripos($stmt, 'CREATE TABLE') === 0) {
                $created++;
            }
        } catch (PDOException $e) {
            echo '  [ATTENTION] Socle LMS (' . substr($stmt, 0, 60) . '…) : ' . $e->getMessage() . "\n";
        }
    }

    // Toujours rétablir les contrôles FK, même si le script les a désactivés en cours de route.
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "  [OK] Socle LMS moderne installé ({$created} tables : training_courses, lessons, enrollments, quizzes…).\n";
};
