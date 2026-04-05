<?php

declare(strict_types=1);

namespace App\Services\Moderation;

/**
 * Résultat d'analyse (avant persistance).
 *
 * @phpstan-type ScanLog array{clamav?: array{infected: bool, detail?: string}, text?: array{score: int, codes: string[]}, meta?: array{score: int, codes: string[]}}
 */
final class ModerationScanResult
{
    /**
     * @param string[] $reasonCodes
     * @param ScanLog $scanLog
     */
    public function __construct(
        public readonly string $state,
        public readonly int $riskScore,
        public readonly array $reasonCodes,
        public readonly array $scanLog,
    ) {
    }
}
