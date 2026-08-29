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

    /**
     * Titre de grade pour le bandeau : titre saisi sur le dossier, sinon libellé du grade attribué.
     *
     * @param array<string, mixed> $gradeRow
     * @param array<string, mixed>|null $personnelProfile
     */
    public function headerTitle(array $gradeRow, ?array $personnelProfile = null): string
    {
        $custom = trim((string) ($personnelProfile['rank_display'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }
        $long = trim($this->getLong($gradeRow));
        if ($long !== '') {
            return $long;
        }

        return trim($this->getShort($gradeRow));
    }

    /**
     * Code court à côté du titre (O-5, OF-4…). Le libellé personnalisé du dossier prime.
     *
     * @param array<string, mixed> $gradeRow
     * @param array<string, mixed>|null $personnelProfile
     */
    public function headerShortCode(array $gradeRow, ?array $personnelProfile = null): ?string
    {
        $override = trim((string) ($personnelProfile['rank_display_override'] ?? ''));
        if ($override !== '') {
            return $override;
        }

        return $this->getOtan($gradeRow);
    }

    /**
     * Détecte un bandeau incohérent : titre « Colonel » + grade communauté LCL/OF-4.
     *
     * @param array<string, mixed> $gradeRow
     * @param array<string, mixed>|null $personnelProfile
     * @return array{mismatch: bool, code: ?string, message: ?string}
     */
    public function detectTitleGradeMismatch(array $gradeRow, ?array $personnelProfile = null): array
    {
        $title = mb_strtolower(trim($this->headerTitle($gradeRow, $personnelProfile)));
        $gradeName = mb_strtolower(trim($this->getLong($gradeRow)));
        $otan = $this->getOtan($gradeRow);
        $code = strtoupper((string) ($gradeRow['code'] ?? ''));

        $titleLooksColonel = str_contains($title, 'colonel') && !str_contains($title, 'lieutenant');
        $gradeIsLcl = $code === 'LCL' || str_contains($gradeName, 'lieutenant-colonel') || $otan === 'OF-4';
        if ($titleLooksColonel && $gradeIsLcl) {
            return [
                'mismatch' => true,
                'code' => 'TITLE_COLONEL_GRADE_LCL_OF4',
                'message' => 'Titre « Colonel » avec grade communauté Lieutenant-colonel (OF-4). Le grade canonique Colonel FR est OF-5.',
            ];
        }

        return ['mismatch' => false, 'code' => null, 'message' => null];
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
