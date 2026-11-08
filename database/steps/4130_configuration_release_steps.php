<?php

declare(strict_types=1);

/**
 * Étape « Mise à niveau : étapes déclarées par version » (Données).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : enregistre les lignes de
 * config/configuration_release_steps.php ; la date de sortie d'une étape déjà connue ne change pas.
 * Ajouter une étape ne demande pas de nouvelle migration : cette étape est relancée.
 */

try {
    echo "Mise à niveau : étapes déclarées par version…\n";
    $configurationReleaseSteps = require $root . '/config/configuration_release_steps.php';
    $configurationReleaseUpsert = $pdo->prepare(
        'INSERT INTO system_configuration_updates
            (code, version, title, description, configuration_level, configure_path, estimate_minutes,
             mandatory, blocking, dismissible, sort_order, released_at, active)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 1, ?, NOW(), 1)
         ON DUPLICATE KEY UPDATE
            version = VALUES(version),
            title = VALUES(title),
            description = VALUES(description),
            configuration_level = VALUES(configuration_level),
            configure_path = VALUES(configure_path),
            estimate_minutes = VALUES(estimate_minutes),
            sort_order = VALUES(sort_order),
            active = 1'
    );
    foreach ($configurationReleaseSteps as $configurationReleaseIndex => $configurationReleaseStep) {
        $configurationReleaseUpsert->execute([
            $configurationReleaseStep['code'],
            $configurationReleaseStep['version'],
            $configurationReleaseStep['title'],
            $configurationReleaseStep['description'],
            $configurationReleaseStep['level'],
            $configurationReleaseStep['path'],
            $configurationReleaseStep['minutes'],
            200 + (int) $configurationReleaseIndex,
        ]);
    }
} catch (Throwable $e) {
    echo '  [ATTENTION] configuration_release_steps : ' . $e->getMessage() . "\n";
    $migrationRunner?->warn('configuration_release_steps', $e);
}
$migrationEnsurePdo();
