<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Libellés français pour la doctrine S1 (types de liens entre rôles, familles de fonctions).
 * Les valeurs techniques restent en base ; l’interface affiche ces intitulés.
 */
final class RoleDoctrineUiLabels
{
    /** Ordre d’affichage dans les listes déroulantes. */
    private const RELATION_ORDER = ['reports_to', 'cross_cutting', 'mentored_by', 'independent'];

    /** @var array<string, string> */
    private const RELATION_SHORT = [
        'reports_to' => 'Chaîne de commandement',
        'cross_cutting' => 'Liaison transversale',
        'mentored_by' => 'Tutorat ou mentorat',
        'independent' => 'Lien sans autorité hiérarchique',
    ];

    /** @var array<string, string> */
    private const RELATION_HELP = [
        'reports_to' => 'Le rôle source relève du rôle destination dans la hiérarchie.',
        'cross_cutting' => 'Coordination ou expertise qui traverse les lignes (S1, RH, etc.).',
        'mentored_by' => 'Accompagnement pédagogique ou professionnel, sans ordre opérationnel direct.',
        'independent' => 'Les deux rôles coexistent sans lien d’autorité (repère sur la toile).',
    ];

    /** @var array<string, string> */
    private const FAMILY = [
        'command' => 'Commandement',
        'hr' => 'Ressources humaines',
        'training' => 'Formation',
        'system' => 'Administration système',
        'support' => 'Soutien et logistique',
        'comms' => 'Communication',
    ];

    /**
     * @return list<string>
     */
    public static function relationTypeValues(): array
    {
        return self::RELATION_ORDER;
    }

    public static function relationTypeIsAllowed(string $type): bool
    {
        return isset(self::RELATION_SHORT[$type]);
    }

    public static function relationTypeShort(string $type): string
    {
        return self::RELATION_SHORT[$type] ?? 'Autre type de lien';
    }

    public static function relationTypeLong(string $type): string
    {
        $short = self::relationTypeShort($type);
        if (!isset(self::RELATION_HELP[$type])) {
            return $short;
        }

        return $short . ' — ' . self::RELATION_HELP[$type];
    }

    /**
     * @return list<array{value: string, label: string, title: string}>
     */
    public static function relationSelectRows(): array
    {
        $out = [];
        foreach (self::RELATION_ORDER as $value) {
            $out[] = [
                'value' => $value,
                'label' => self::RELATION_SHORT[$value],
                'title' => self::relationTypeLong($value),
            ];
        }

        return $out;
    }

    /** Couleurs d’arête pour le schéma (contraste sur fond clair). */
    public static function relationTypeChartColor(string $type): string
    {
        return match ($type) {
            'reports_to' => '#334155',
            'cross_cutting' => '#7c3aed',
            'mentored_by' => '#0369a1',
            'independent' => '#94a3b8',
            default => '#64748b',
        };
    }

    public static function definitionFamilyLabel(string $family): string
    {
        $k = mb_strtolower(trim($family));
        if (isset(self::FAMILY[$k])) {
            return self::FAMILY[$k];
        }
        $raw = trim($family);
        if ($raw === '') {
            return '—';
        }

        return mb_convert_case(str_replace(['_', '-'], ' ', $raw), MB_CASE_TITLE, 'UTF-8');
    }
}
