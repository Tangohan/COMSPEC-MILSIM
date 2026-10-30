<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Historical implementation source used by legacy wiring/asset tests.
 *
 * This helper deliberately reads registry-archive.php, never the active queue.
 * These checks preserve the archived migration implementations used by selected
 * module provisioning paths; they do not assert that historical migrations run.
 * Active queue execution is tested separately by MigrationQueueResetTest.
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
        $registry = $root . '/database/steps/registry-archive.php';
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
