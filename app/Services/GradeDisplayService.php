<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Rendu du grade selon le format : classique (FR/US), OTAN, hybride.
 * Entrée : ligne grade (avec system_code, country_code, label_short, label_long, label_otan).
 */
class GradeDisplayService
{
    public const FORMAT_CLASSIC = 'classic';
    public const FORMAT_OTAN = 'otan';
    public const FORMAT_HYBRID = 'hybrid';

    /**
     * Retourne le libellé selon le format demandé.
     * @param array<string, mixed> $gradeRow ligne grade (avec label_short, label_long, label_otan, country_code, etc.)
     */
    public function formatForUser(array $gradeRow, string $format, ?string $countryCode = null): string
    {
        $country = $countryCode ?? ($gradeRow['country_code'] ?? 'FR');
        $long = (string) ($gradeRow['label_long'] ?? '');
        $short = (string) ($gradeRow['label_short'] ?? $long);
        $otan = $gradeRow['label_otan'] ?? null;
        $otan = $otan !== null && $otan !== '' ? (string) $otan : null;

        return match ($format) {
            self::FORMAT_OTAN => $otan ?? $long,
            self::FORMAT_HYBRID => $otan !== null ? $long . ' (' . $otan . ')' : $long,
            self::FORMAT_CLASSIC => $long,
            default => $long,
        };
    }

    /** Court (ex. CNE, CPT). */
    public function getShort(array $gradeRow): string
    {
        return (string) ($gradeRow['label_short'] ?? $gradeRow['label_long'] ?? '');
    }

    /** Long (ex. Capitaine, Captain). */
    public function getLong(array $gradeRow): string
    {
        return (string) ($gradeRow['label_long'] ?? $gradeRow['label_short'] ?? '');
    }

    /** Code OTAN (ex. OF-2, O-3). */
    public function getOtan(array $gradeRow): ?string
    {
        $v = $gradeRow['label_otan'] ?? null;
        return $v !== null && $v !== '' ? (string) $v : null;
    }

    /** Hybride : Capitaine (OF-2). */
    public function getFull(array $gradeRow): string
    {
        return $this->formatForUser($gradeRow, self::FORMAT_HYBRID);
    }

    /** Libellé catégorie (ex. Officier). */
    public function getCategoryLabel(array $gradeRow): string
    {
        return (string) ($gradeRow['category_label'] ?? '');
    }
}
