<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Libellés métier analyse SSE (LOT 6).
 */
final class SseAnalysisCatalog
{
    /** @var array<string,string> */
    public const FINDING_TYPES = [
        'contradiction' => 'Contradiction',
        'rapprochement' => 'Rapprochement',
        'anomaly' => 'Anomalie',
        'pol_gap' => 'Écart de rythme d’activité',
    ];

    /** @var array<string,string> */
    public const SEVERITIES = [
        'basse' => 'Basse',
        'normale' => 'Normale',
        'haute' => 'Haute',
        'critique' => 'Critique',
    ];

    /** @var array<string,string> */
    public const STATUSES = [
        'ouvert' => 'À examiner',
        'retenu' => 'Retenu',
        'ecarte' => 'Écarté',
        'archive' => 'Archivé',
    ];

    /** @var array<string,string> */
    public const CONFIDENCE = [
        'OBSERVE' => 'Observé',
        'PROBABLE' => 'Probable',
        'ESTIME' => 'Estimé',
    ];

    public static function typeLabel(string $t): string
    {
        return self::FINDING_TYPES[$t] ?? $t;
    }

    public static function statusLabel(string $s): string
    {
        return self::STATUSES[$s] ?? $s;
    }

    public static function confidenceLabel(string $c): string
    {
        return self::CONFIDENCE[$c] ?? $c;
    }
}
