<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Modèle métier Athena ORBAT — objets stables.
 *
 * PERSONNEL ↔ AFFECTATION ↔ POSTE ↔ STRUCTURE
 * + QUALIFICATION + HISTORIQUE + DUTY_MISSION + WORKFLOW
 *
 * Grade ≠ poste ≠ duty mission ≠ qualification ≠ rôle technique Athena.
 * Statut, confidentialité, dates d’effet et permissions sont transversaux.
 *
 * @see docs/architecture/2026-10-01-org-duty-workbench.md
 */
final class OrgDomainModel
{
    public const PERSONNEL = 'personnel';
    public const ASSIGNMENT = 'assignment';
    public const BILLET = 'billet';
    public const STRUCTURE = 'structure';
    public const QUALIFICATION = 'qualification';
    public const HISTORY = 'history';
    public const DUTY_MISSION = 'duty_mission';
    public const WORKFLOW = 'workflow';

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
        self::DUTY_MISSION,
        self::WORKFLOW,
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
            self::DUTY_MISSION => 'Duty mission',
            self::WORKFLOW => 'Workflow',
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
            self::DUTY_MISSION => 'Organisation opérationnelle temporaire (indicatif, expire en fin d’opération).',
            self::WORKFLOW => 'Tâches, rapports, demandes et validations — souvent adressés au poste, pas à la personne.',
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
