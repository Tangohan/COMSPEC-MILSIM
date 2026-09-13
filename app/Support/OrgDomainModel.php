<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Modèle métier Athena ORBAT — six objets stables.
 *
 * PERSONNEL ↔ AFFECTATION ↔ POSTE ↔ STRUCTURE
 * + QUALIFICATION + HISTORIQUE
 *
 * Statut, confidentialité, dates d’effet et permissions sont transversaux.
 */
final class OrgDomainModel
{
    public const PERSONNEL = 'personnel';
    public const ASSIGNMENT = 'assignment';
    public const BILLET = 'billet';
    public const STRUCTURE = 'structure';
    public const QUALIFICATION = 'qualification';
    public const HISTORY = 'history';

    public const CORE = [
        self::PERSONNEL,
        self::ASSIGNMENT,
        self::BILLET,
        self::STRUCTURE,
    ];

    public const ALL = [
        self::PERSONNEL,
        self::ASSIGNMENT,
        self::BILLET,
        self::STRUCTURE,
        self::QUALIFICATION,
        self::HISTORY,
    ];

    public static function label(string $object): string
    {
        return match ($object) {
            self::PERSONNEL => 'Personnel',
            self::ASSIGNMENT => 'Affectation',
            self::BILLET => 'Poste',
            self::STRUCTURE => 'Structure',
            self::QUALIFICATION => 'Qualification',
            self::HISTORY => 'Historique',
            default => $object,
        };
    }

    public static function description(string $object): string
    {
        return match ($object) {
            self::PERSONNEL => 'Identité, grade, situation administrative, confidentialité du profil.',
            self::ASSIGNMENT => 'Lien daté personnel ↔ poste (titulaire, intérim, adjoint) avec motif.',
            self::BILLET => 'Poste théorique dans une structure : intitulé, callsign, fonction, quals attendues.',
            self::STRUCTURE => 'Unité / équipe : rattachement, statut admin, visibilité, postes et effectifs.',
            self::QUALIFICATION => 'Compétences détenues ou exigées par un poste — jamais confondues avec le grade.',
            self::HISTORY => 'Journal de carrière, snapshots ORBAT, audit des mouvements sensibles.',
            default => '',
        };
    }

    /**
     * @return list<array{id: string, label: string, description: string}>
     */
    public static function catalog(): array
    {
        $out = [];
        foreach (self::ALL as $id) {
            $out[] = [
                'id' => $id,
                'label' => self::label($id),
                'description' => self::description($id),
            ];
        }

        return $out;
    }
}
