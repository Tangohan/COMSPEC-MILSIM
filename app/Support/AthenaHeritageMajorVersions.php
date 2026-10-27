<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Grandes étapes de version ATHENA / COMSPEC (uniquement x.0.0).
 * Les dates non documentées restent nulles — jamais inventées.
 */
final class AthenaHeritageMajorVersions
{
    /**
     * @return list<array{
     *   version: string,
     *   released_at: ?string,
     *   date_precision: 'exact'|'day'|'month'|'unknown',
     *   title_key: string,
     *   summary_key: string,
     *   changelog_ref: ?string
     * }>
     */
    public static function catalog(): array
    {
        return [
            [
                'version' => '1.0.0',
                'released_at' => null,
                'date_precision' => 'unknown',
                'title_key' => 'site.hist_ver_1_0_0_t',
                'summary_key' => 'site.hist_ver_1_0_0_b',
                'changelog_ref' => '2025-12-tenants',
            ],
            [
                'version' => '2.0.0',
                'released_at' => null,
                'date_precision' => 'unknown',
                'title_key' => 'site.hist_ver_2_0_0_t',
                'summary_key' => 'site.hist_ver_2_0_0_b',
                'changelog_ref' => null,
            ],
            [
                'version' => '3.0.0',
                'released_at' => null,
                'date_precision' => 'unknown',
                'title_key' => 'site.hist_ver_3_0_0_t',
                'summary_key' => 'site.hist_ver_3_0_0_b',
                'changelog_ref' => null,
            ],
        ];
    }

    public static function isMajorStep(string $version): bool
    {
        $v = trim($version);
        if ($v === '') {
            return false;
        }

        return (bool) preg_match('/^\d+\.0\.0(?:[-+].*)?$/', $v);
    }

    public static function normalizeMajor(string $version): ?string
    {
        $v = trim($version);
        if ($v === '' || !preg_match('/^(\d+)\.(\d+)\.(\d+)/', $v, $m)) {
            return null;
        }

        return ((int) $m[1]) . '.0.0';
    }

    /**
     * Versions majeures déjà atteintes ou dépassées par la version courante.
     *
     * @return list<string>
     */
    public static function reachedMajors(string $currentVersion): array
    {
        if (!preg_match('/^(\d+)\./', trim($currentVersion), $m)) {
            return [];
        }
        $major = (int) $m[1];
        $out = [];
        for ($i = 1; $i <= $major; $i++) {
            $out[] = $i . '.0.0';
        }

        return $out;
    }
}
