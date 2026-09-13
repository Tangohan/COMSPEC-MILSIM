<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Statuts administratifs des structures ORBAT (indépendants de la readiness opérationnelle).
 */
final class UnitAdminStatus
{
    public const ACTIVE = 'active';
    public const PARTIALLY_ACTIVE = 'partially_active';
    public const INACTIVE = 'inactive';
    public const FORMING = 'forming';
    public const REORGANIZING = 'reorganizing';
    public const ARCHIVED = 'archived';

    /** @var list<string> */
    public const ALL = [
        self::ACTIVE,
        self::PARTIALLY_ACTIVE,
        self::INACTIVE,
        self::FORMING,
        self::REORGANIZING,
        self::ARCHIVED,
    ];

    /** Statuts affichés par défaut dans la vue opérationnelle. */
    public const DEFAULT_OPERATIONAL = [
        self::ACTIVE,
        self::PARTIALLY_ACTIVE,
        self::FORMING,
        self::REORGANIZING,
    ];

    public static function normalize(?string $raw): string
    {
        $t = strtolower(trim((string) $raw));
        $aliases = [
            'actif' => self::ACTIVE,
            'active' => self::ACTIVE,
            'partiel' => self::PARTIALLY_ACTIVE,
            'partielle' => self::PARTIALLY_ACTIVE,
            'partial' => self::PARTIALLY_ACTIVE,
            'partially_active' => self::PARTIALLY_ACTIVE,
            'inactif' => self::INACTIVE,
            'inactive' => self::INACTIVE,
            'en_formation' => self::FORMING,
            'forming' => self::FORMING,
            'formation' => self::FORMING,
            'en_reorganisation' => self::REORGANIZING,
            'reorganizing' => self::REORGANIZING,
            'reorganisation' => self::REORGANIZING,
            'archive' => self::ARCHIVED,
            'archived' => self::ARCHIVED,
            'archivé' => self::ARCHIVED,
        ];
        if (isset($aliases[$t])) {
            return $aliases[$t];
        }
        if (!in_array($t, self::ALL, true)) {
            return self::ACTIVE;
        }

        return $t;
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::ALL, true);
    }

    public static function label(string $status): string
    {
        return match (self::normalize($status)) {
            self::PARTIALLY_ACTIVE => 'Partiellement actif',
            self::INACTIVE => 'Inactif',
            self::FORMING => 'En formation',
            self::REORGANIZING => 'En réorganisation',
            self::ARCHIVED => 'Archivé',
            default => 'Actif',
        };
    }

    public static function badgeLabel(string $status): string
    {
        return match (self::normalize($status)) {
            self::PARTIALLY_ACTIVE => 'PARTIELLEMENT ACTIF',
            self::INACTIVE => 'INACTIF',
            self::FORMING => 'EN FORMATION',
            self::REORGANIZING => 'EN RÉORGANISATION',
            self::ARCHIVED => 'ARCHIVÉ',
            default => 'ACTIF',
        };
    }

    public static function cssClass(string $status): string
    {
        return 'admin-status-' . str_replace('_', '-', self::normalize($status));
    }

    /** Peut être proposée pour une nouvelle affectation (par défaut). */
    public static function isAssignableByDefault(string $status): bool
    {
        $s = self::normalize($status);

        return in_array($s, [self::ACTIVE, self::PARTIALLY_ACTIVE, self::FORMING, self::REORGANIZING], true);
    }

    /** Jamais proposée pour une nouvelle affectation. */
    public static function isAssignableForbidden(string $status): bool
    {
        return self::normalize($status) === self::ARCHIVED;
    }

    /** Masquée par défaut dans l’ORBAT courant. */
    public static function isHiddenByDefault(string $status): bool
    {
        $s = self::normalize($status);

        return $s === self::ARCHIVED || $s === self::INACTIVE;
    }

    /**
     * @return list<array{id: string, label: string, badge: string}>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::ALL as $id) {
            $out[] = [
                'id' => $id,
                'label' => self::label($id),
                'badge' => self::badgeLabel($id),
            ];
        }

        return $out;
    }
}
