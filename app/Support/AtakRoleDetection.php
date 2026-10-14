<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Comment un rôle de atak_screen_time a été détecté, d'après sa clé, et qui a remonté la ligne (source_mask).
 *
 * Ordre de détection du jeu (fn_roleKey.sqf) :
 *   1. poste d'équipage : PIL, CPL (aéronef), DRV, CREW (véhicule) ;
 *   2. rôle d'équipe de feu choisi dans le téléphone (app Groupe) : CDE, FUS, JTAC, A<id>, C_<NOM>… ;
 *   3. spécialité Arma : MED, EOD, ENG, DRN ;
 *   4. nom du slot : SLOT:<NOM>.
 * Certains codes d'équipe reprennent ceux des postes et spécialités (MED, PIL…) : la clé seule ne les distingue
 * pas, on garde alors la lecture la plus probable (poste ou spécialité).
 */
final class AtakRoleDetection
{
    public const CREW = 'crew';
    public const TEAM = 'team';
    public const SPECIALTY = 'specialty';
    public const SLOT = 'slot';
    public const MANUAL = 'manual';

    /** Bits de source_mask (AtakScreenTimeRepository::SOURCE_PHONE / SOURCE_SERVER). */
    public const SOURCE_PHONE = 1;
    public const SOURCE_SERVER = 2;

    /** @var list<string> */
    public const CREW_KEYS = ['PIL', 'CPL', 'DRV', 'CREW'];

    /** @var list<string> */
    public const SPECIALTY_KEYS = ['MED', 'EOD', 'ENG', 'DRN'];

    /** @var array<string, string> */
    public const LABELS = [
        self::CREW => 'Poste d’équipage',
        self::TEAM => 'Rôle d’équipe (téléphone)',
        self::SPECIALTY => 'Spécialité Arma',
        self::SLOT => 'Nom du slot',
        self::MANUAL => 'Corrigé à la main',
    ];

    /** @var array<string, string> */
    public const HINTS = [
        self::CREW => 'Place tenue dans un véhicule ou un aéronef (pilote, copilote, conducteur, équipage).',
        self::TEAM => 'Rôle choisi dans l’app Groupe du téléphone (onglet Équipes).',
        self::SPECIALTY => 'Aptitude du slot Arma (infirmier, démineur, sapeur, télépilote), ou rôle d’équipe portant le même code.',
        self::SLOT => 'Aucun autre indice : nom du slot choisi dans le lobby.',
        self::MANUAL => 'Temps déplacé vers ce rôle par l’encadrement (fiche personnel).',
    ];

    /**
     * Catégorie de détection d'une clé de rôle. $corrected : la clé n'existe que par une correction manuelle.
     */
    public static function category(string $key, bool $corrected = false): string
    {
        if ($corrected) {
            return self::MANUAL;
        }
        $key = trim($key);
        $upper = strtoupper($key);
        if (str_starts_with($upper, 'SLOT:')) {
            return self::SLOT;
        }
        if (in_array($upper, self::CREW_KEYS, true)) {
            return self::CREW;
        }
        if (in_array($upper, self::SPECIALTY_KEYS, true)) {
            return self::SPECIALTY;
        }

        return self::TEAM;
    }

    public static function label(string $category): string
    {
        return self::LABELS[$category] ?? self::LABELS[self::TEAM];
    }

    public static function hint(string $category): string
    {
        return self::HINTS[$category] ?? '';
    }

    /** Libellé « Remonté par » d'un masque de sources. */
    public static function sourceLabel(int $mask): string
    {
        $phone = ($mask & self::SOURCE_PHONE) !== 0;
        $server = ($mask & self::SOURCE_SERVER) !== 0;

        return match (true) {
            $phone && $server => 'Serveur + téléphone',
            $server => 'Serveur Arma',
            $phone => 'Téléphone ATAK',
            default => 'Non précisé',
        };
    }

    /**
     * Type lisible d'une ligne (colonne « Type » du tableur).
     */
    public static function kindLabel(string $kind, string $key = ''): string
    {
        return match ($kind) {
            'play' => 'Temps de jeu',
            'role' => 'Rôle',
            'app' => 'App',
            'screen' => match (strtoupper($key)) {
                'HAND' => 'Écran en main',
                'CARRY' => 'Écran porté',
                default => 'Écran (total)',
            },
            default => $kind,
        };
    }

    /** Libellé d'un rôle sans libellé enregistré : « SLOT:FUSILIER » → « FUSILIER ». */
    public static function fallbackLabel(string $key): string
    {
        return str_starts_with($key, 'SLOT:') ? substr($key, 5) : $key;
    }
}
