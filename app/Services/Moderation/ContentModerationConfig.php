<?php

declare(strict_types=1);

namespace App\Services\Moderation;

/**
 * Configuration modération (variables d'environnement).
 */
final class ContentModerationConfig
{
    public const DEFAULT_RULESET_VERSION = '2026.04.05';

    public function __construct(
        public readonly bool $enabled,
        public readonly string $rulesetVersion,
        public readonly int $thresholdLow,
        public readonly int $thresholdHigh,
        public readonly ?string $clamavBin,
        public readonly int $quarantineTtlDays,
    ) {
    }

    public static function fromEnv(): self
    {
        $enabled = true;
        if (array_key_exists('MODERATION_ENABLED', $_ENV)) {
            $enabled = filter_var($_ENV['MODERATION_ENABLED'], FILTER_VALIDATE_BOOL);
        } else {
            $ge = getenv('MODERATION_ENABLED');
            if ($ge !== false && $ge !== '') {
                $enabled = filter_var($ge, FILTER_VALIDATE_BOOL);
            }
        }
        $ruleset = trim((string) ($_ENV['MODERATION_RULESET_VERSION'] ?? getenv('MODERATION_RULESET_VERSION') ?: self::DEFAULT_RULESET_VERSION));
        $low = (int) ($_ENV['MODERATION_THRESHOLD_LOW'] ?? getenv('MODERATION_THRESHOLD_LOW') ?: 30);
        $high = (int) ($_ENV['MODERATION_THRESHOLD_HIGH'] ?? getenv('MODERATION_THRESHOLD_HIGH') ?: 75);
        $clam = trim((string) ($_ENV['MODERATION_CLAMAV_BIN'] ?? getenv('MODERATION_CLAMAV_BIN') ?: ''));
        if ($clam === '') {
            $clam = null;
        }
        $ttl = (int) ($_ENV['MODERATION_QUARANTINE_DAYS'] ?? getenv('MODERATION_QUARANTINE_DAYS') ?: 14);
        $low = max(0, min(100, $low));
        $high = max($low, min(100, $high));

        return new self($enabled, $ruleset, $low, $high, $clam, max(1, $ttl));
    }
}
