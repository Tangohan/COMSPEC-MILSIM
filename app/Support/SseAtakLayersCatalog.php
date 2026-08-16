<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Libellés métier des calques ATAK / SSE (LOT 5).
 */
final class SseAtakLayersCatalog
{
    /** @var array<string,string> */
    public const LAYER_KINDS = [
        'cases' => 'Dossiers SSE',
        'pir' => 'Priorités de renseignement',
        'taskings' => 'Ordres de collecte',
        'photos' => 'Photos terrain',
        'tracks' => 'Tracés',
        'ghost_tracks' => 'Tracés fantômes',
        'history' => 'Historique',
    ];

    /** @var array<string,string> */
    public const TRACK_KINDS = [
        'live' => 'Tracé en cours',
        'ghost' => 'Tracé fantôme',
        'history' => 'Tracé d’historique',
    ];

    /** @var array<string,string> */
    public const POINT_COLORS = [
        'cases' => '#34d399',
        'pir' => '#38bdf8',
        'taskings' => '#a78bfa',
        'photos' => '#c084fc',
        'tracks' => '#67e8f9',
        'ghost_tracks' => '#94a3b8',
        'history' => '#fbbf24',
        'site' => '#f59e0b',
    ];

    public static function layerLabel(string $kind): string
    {
        return self::LAYER_KINDS[$kind] ?? $kind;
    }

    public static function trackLabel(string $kind): string
    {
        return self::TRACK_KINDS[$kind] ?? $kind;
    }

    public static function colorFor(string $kind): string
    {
        return self::POINT_COLORS[$kind] ?? '#34d399';
    }
}
