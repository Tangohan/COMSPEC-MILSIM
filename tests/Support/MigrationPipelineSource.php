<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Source complète du pipeline de migrations pour les tests d'assets : run-migrations.php, puis le contexte
 * partagé et chaque étape de database/steps/ dans l'ordre du registre (l'ordre d'exécution).
 *
 * Avant la refonte, tout tenait dans run-migrations.php ; les tests qui vérifient qu'une migration est bien
 * branchée lisent désormais cette concaténation.
 */
final class MigrationPipelineSource
{
    public static function read(string $root): string
    {
        $root = rtrim($root, '/');
        $out = (string) file_get_contents($root . '/run-migrations.php');
        $context = $root . '/database/steps/_context.php';
        if (is_file($context)) {
            $out .= "\n" . (string) file_get_contents($context);
        }
        $registry = $root . '/database/steps/registry.php';
        $steps = is_file($registry) ? require $registry : [];
        foreach (is_array($steps) ? $steps : [] as $step) {
            $file = $root . '/database/steps/' . (string) ($step['file'] ?? '');
            if (is_file($file)) {
                $out .= "\n" . (string) file_get_contents($file);
            }
        }

        return $out;
    }
}
