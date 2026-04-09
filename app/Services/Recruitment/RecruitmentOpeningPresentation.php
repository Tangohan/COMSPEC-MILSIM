<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

/**
 * Libellés français pour champs structurés des offres (aucun jargon brut côté UI).
 */
final class RecruitmentOpeningPresentation
{
    /** @return array<string, string> */
    public static function personnelCategories(): array
    {
        return [
            'officer' => 'Officier',
            'officer_contract' => 'Officier sous contrat',
            'nco' => 'Sous-officier',
            'specialist' => 'Spécialiste',
            'civilian' => 'Personnel civil',
            'other' => 'Autre profil',
        ];
    }

    /** @return array<string, string> */
    public static function armDomains(): array
    {
        return [
            'infantry' => 'Infanterie',
            'cavalry' => 'Cavalerie',
            'artillery' => 'Artillerie',
            'logistics' => 'Logistique',
            'train' => 'Train',
            'engineering' => 'Génie',
            'aviation' => 'Aviation',
            'signals' => 'Transmissions',
            'other' => 'Autre domaine',
        ];
    }

    /** @return array<string, string> */
    public static function clearanceLevels(): array
    {
        return [
            'none' => 'Non requis',
            'confidential' => 'Confidentiel défense',
            'secret' => 'Secret',
            'secret_defense' => 'Secret défense',
        ];
    }

    public static function personnelCategoryLabel(string $key): string
    {
        return self::personnelCategories()[$key] ?? $key;
    }

    public static function armDomainLabel(?string $key): string
    {
        if ($key === null || $key === '') {
            return '—';
        }

        return self::armDomains()[$key] ?? $key;
    }

    public static function clearanceLabel(string $key): string
    {
        return self::clearanceLevels()[$key] ?? $key;
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            'draft' => 'Brouillon',
            'published' => 'Publiée',
            'closed' => 'Clôturée',
        ];
    }

    public static function statusPublicBadge(string $status): string
    {
        return match ($status) {
            'published' => 'Poste à pourvoir',
            'closed' => 'Clôturé',
            default => '—',
        };
    }
}
