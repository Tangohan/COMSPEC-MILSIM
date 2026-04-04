<?php

declare(strict_types=1);

namespace App\Services\Courrier;

/**
 * Niveaux de classification documentaire (affichage + filigrane).
 */
final class CourrierClassification
{
    public const NON_CLASSE = 'non_classe';
    public const INTERNE = 'interne';
    public const DIFFUSION_RESTREINTE = 'diffusion_restreinte';
    public const CONFIDENTIEL = 'confidentiel';
    public const SECRET = 'secret';

    /** @return array<string, string> code => libellé */
    public static function labels(): array
    {
        return [
            self::NON_CLASSE => 'Non classifié',
            self::INTERNE => 'Interne',
            self::DIFFUSION_RESTREINTE => 'Diffusion restreinte',
            self::CONFIDENTIEL => 'Confidentiel',
            self::SECRET => 'Secret',
        ];
    }

    public static function label(string $code): string
    {
        return self::labels()[$code] ?? $code;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::labels());
    }

    public static function watermarkClass(string $code): string
    {
        return match ($code) {
            self::CONFIDENTIEL, self::SECRET => 'courrier-watermark--high',
            self::DIFFUSION_RESTREINTE, self::INTERNE => 'courrier-watermark--medium',
            default => 'courrier-watermark--low',
        };
    }
}
