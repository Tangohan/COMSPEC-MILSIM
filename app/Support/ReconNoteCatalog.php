<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Notes de reconnaissance (INTEL_MARK / recon_note).
 * Libellés destinés aux opérateurs — pas de codes internes à l’écran.
 */
final class ReconNoteCatalog
{
    public const EVENT_TYPE = 'INTEL_MARK';
    public const NOTE_TYPE = 'recon_note';
    public const TEXT_MAX = 140;
    public const COOLDOWN_SEC = 8;
    public const FRESH_SEC = 20 * 60;
    public const STALE_SEC = 40 * 60;

    /** @var array<string, array{label: string, color: string}> */
    public const TAGS = [
        'vehicle' => ['label' => 'Véhicule', 'color' => '#f97316'],
        'armed_group' => ['label' => 'Groupe armé', 'color' => '#ef4444'],
        'static' => ['label' => 'Position statique', 'color' => '#eab308'],
        'mine' => ['label' => 'Obstacle / mine', 'color' => '#ec4899'],
        'civilian' => ['label' => 'Civil', 'color' => '#22c55e'],
        'infrastructure' => ['label' => 'Infrastructure', 'color' => '#38bdf8'],
        'other' => ['label' => 'Autre', 'color' => '#94a3b8'],
    ];

    public static function normalizeTag(string $raw): string
    {
        $t = strtolower(trim($raw));
        $t = str_replace(['-', ' '], '_', $t);
        $aliases = [
            'vehicule' => 'vehicle',
            'véhicule' => 'vehicle',
            'groupe' => 'armed_group',
            'groupe_arme' => 'armed_group',
            'groupe_armé' => 'armed_group',
            'position' => 'static',
            'position_statique' => 'static',
            'obstacle' => 'mine',
            'obstacle_mine' => 'mine',
            'civil' => 'civilian',
            'infra' => 'infrastructure',
        ];
        if (isset($aliases[$t])) {
            $t = $aliases[$t];
        }
        if ($t === '' || !isset(self::TAGS[$t])) {
            return '';
        }

        return $t;
    }

    public static function tagLabel(string $tag): string
    {
        $n = self::normalizeTag($tag);
        if ($n === '') {
            return 'Observation';
        }

        return self::TAGS[$n]['label'];
    }

    public static function tagColor(string $tag): string
    {
        $n = self::normalizeTag($tag);
        if ($n === '') {
            return self::TAGS['other']['color'];
        }

        return self::TAGS[$n]['color'];
    }

    public static function normalizeConfidence(string $raw): string
    {
        $c = strtolower(trim($raw));
        $c = str_replace(['-', ' '], '_', $c);
        if (in_array($c, ['vu_direct', 'direct', 'seen', 'vu'], true)) {
            return 'vu_direct';
        }
        if (in_array($c, ['rapporte', 'rapporté', 'reported', 'ouie', 'ouï-dire'], true)) {
            return 'rapporte';
        }

        return '';
    }

    public static function confidenceLabel(string $confidence): string
    {
        return match (self::normalizeConfidence($confidence)) {
            'vu_direct' => 'Vu direct',
            'rapporte' => 'Rapporté',
            default => '',
        };
    }

    public static function freshness(int $ageSec): string
    {
        if ($ageSec < self::FRESH_SEC) {
            return 'fresh';
        }
        if ($ageSec < self::STALE_SEC) {
            return 'aging';
        }

        return 'stale';
    }

    public static function opacity(int $ageSec): float
    {
        if ($ageSec < self::FRESH_SEC) {
            return 1.0;
        }
        if ($ageSec >= self::STALE_SEC) {
            return 0.32;
        }
        $span = self::STALE_SEC - self::FRESH_SEC;
        $t = ($ageSec - self::FRESH_SEC) / max(1, $span);

        return round(1.0 - (0.68 * $t), 2);
    }
}
