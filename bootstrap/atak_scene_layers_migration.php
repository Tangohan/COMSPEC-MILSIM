<?php

declare(strict_types=1);

/**
 * Volumes du théâtre : kinds linéaires (murs, clôtures…) + extras (entrées).
 */
function run_atak_scene_layers_migration(PDO $pdo): void
{
    if (!schema_table_exists($pdo, 'atak_scene_objects')) {
        echo "  [OK] atak_scene_objects absente — skip\n";

        return;
    }
    try {
        $pdo->exec("ALTER TABLE atak_scene_objects MODIFY `kind` VARCHAR(32) NOT NULL DEFAULT 'building'");
        echo "  [OK] atak_scene_objects.kind élargi\n";
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (!str_contains($msg, 'Duplicate') && !str_contains(strtolower($msg), 'same')) {
            echo '  [ATTENTION] kind : ' . $msg . "\n";
        }
    }
    if (function_exists('schema_ensure_column')) {
        $added = schema_ensure_column(
            $pdo,
            'atak_scene_objects',
            'extras',
            '`extras` TEXT NULL AFTER `density`'
        );
        echo $added ? "  [OK] extras ajoutée\n" : "  [OK] extras déjà présente\n";
    }
}
