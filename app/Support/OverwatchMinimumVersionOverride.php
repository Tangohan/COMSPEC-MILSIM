<?php

declare(strict_types=1);

namespace App\Support;

final class OverwatchMinimumVersionOverride
{
    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function lowerIfAbove(array $config, string $target): array
    {
        $current = trim((string) ($config['min_mod_version'] ?? ''));
        if ($current !== '' && version_compare($current, $target, '>')) {
            $config['min_mod_version'] = $target;
        }

        return $config;
    }
}
