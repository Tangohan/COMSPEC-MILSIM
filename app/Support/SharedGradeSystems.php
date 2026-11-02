<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Systèmes de grades du catalogue partagé (grade_systems sans communauté propriétaire), proposés à la création
 * d’une communauté et dans Back-office › Référentiel des grades : français, américain et britanniques (British Army
 * et Royal Marines, Royal Navy, Royal Air Force ; étape database/steps/3610_grades_royaume_uni.php).
 *
 * Chaque système : onglet du référentiel, pays, libellés, grade d’officier proposé par défaut au fondateur et
 * référentiel d’adéquation des échelons (App\Services\Organization\EchelonStandards).
 */
final class SharedGradeSystems
{
    public const DEFAULT = 'FR_CLASSIC';

    /** Codes dans l’ordre d’affichage. */
    public const CODES = ['FR_CLASSIC', 'US_CLASSIC', 'GB_ARMY', 'GB_RN', 'GB_RAF'];

    /**
     * @var array<string, array{tab: string, country: string, title: string, tab_label: string, name: string, text: string, founder: string, profile: string}>
     */
    private const SYSTEMS = [
        'FR_CLASSIC' => [
            'tab' => 'fr', 'country' => 'FR', 'title' => 'Français', 'tab_label' => 'Grades français', 'name' => 'grades français',
            'text' => 'Grades classiques de l’armée de terre française.', 'founder' => 'CNE', 'profile' => 'fr',
        ],
        'US_CLASSIC' => [
            'tab' => 'us', 'country' => 'US', 'title' => 'United States', 'tab_label' => 'Grades américains', 'name' => 'grades américains',
            'text' => 'Grades classiques des forces armées américaines.', 'founder' => 'CPT', 'profile' => 'us',
        ],
        'GB_ARMY' => [
            'tab' => 'gb', 'country' => 'GB', 'title' => 'British Army', 'tab_label' => 'British Army et Royal Marines', 'name' => 'grades britanniques (British Army et Royal Marines)',
            'text' => 'Modèle britannique : grades de la British Army et des Royal Marines (Private, Trooper, Lance Corporal, Sergeant, Captain…).', 'founder' => 'CAPT', 'profile' => 'gb',
        ],
        'GB_RN' => [
            'tab' => 'gb-rn', 'country' => 'GB', 'title' => 'Royal Navy', 'tab_label' => 'Royal Navy', 'name' => 'grades de la Royal Navy',
            'text' => 'Modèle britannique : grades de la Royal Navy (Able Rating, Petty Officer, Lieutenant Commander…).', 'founder' => 'LT', 'profile' => 'gb',
        ],
        'GB_RAF' => [
            'tab' => 'gb-raf', 'country' => 'GB', 'title' => 'Royal Air Force', 'tab_label' => 'Royal Air Force', 'name' => 'grades de la Royal Air Force',
            'text' => 'Modèle britannique : grades de la Royal Air Force (Aircraftman, Corporal, Flight Lieutenant…).', 'founder' => 'FLTLT', 'profile' => 'gb',
        ],
    ];

    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    public static function isShared(string $code): bool
    {
        return isset(self::SYSTEMS[self::normalize($code)]);
    }

    /** @return array{tab: string, country: string, title: string, tab_label: string, name: string, text: string, founder: string, profile: string} */
    public static function get(string $code): array
    {
        return self::SYSTEMS[self::normalize($code)] ?? self::SYSTEMS[self::DEFAULT];
    }

    /** Onglet du référentiel d’un système partagé (un code inconnu retombe sur l’onglet français). */
    public static function tab(string $code): string
    {
        return self::get($code)['tab'];
    }

    /** Code du système d’un onglet, null si l’onglet n’est pas celui d’un système partagé. */
    public static function codeForTab(string $tab): ?string
    {
        foreach (self::SYSTEMS as $code => $s) {
            if ($s['tab'] === $tab) {
                return $code;
            }
        }

        return null;
    }

    /** @return list<string> onglets des systèmes partagés */
    public static function tabs(): array
    {
        return array_values(array_map(static fn (array $s): string => $s['tab'], self::SYSTEMS));
    }

    public static function country(string $code): string
    {
        return self::get($code)['country'];
    }

    /** « grades britanniques (British Army et Royal Marines) » */
    public static function name(string $code): string
    {
        return self::get($code)['name'];
    }

    /** Code du grade proposé au fondateur (officier subalterne du système). */
    public static function founderCode(string $code): string
    {
        return self::get($code)['founder'];
    }

    /** Référentiel des échelons (fr, us, gb) associé au système. */
    public static function profile(string $code): string
    {
        return self::get($code)['profile'];
    }

    /** @return array<string, array{tab: string, country: string, title: string, tab_label: string, name: string, text: string, founder: string, profile: string}> */
    public static function all(): array
    {
        return self::SYSTEMS;
    }
}
