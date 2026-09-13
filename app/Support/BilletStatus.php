<?php

declare(strict_types=1);

namespace App\Support;

/**
 * État opérationnel d’un poste ORBAT (référentiel théorique).
 * Distinct de la situation administrative du personnel.
 */
final class BilletStatus
{
    public const ACTIVE = 'active';
    public const FILLED = 'filled';
    public const VACANT = 'vacant';
    public const FROZEN = 'frozen';
    public const DELETED = 'deleted';

    public const ALL = [
        self::ACTIVE,
        self::FROZEN,
        self::DELETED,
    ];

    public static function normalize(?string $raw): string
    {
        $v = strtolower(trim((string) $raw));
        $aliases = [
            'actif' => self::ACTIVE,
            'pourvu' => self::FILLED,
            'vacant' => self::VACANT,
            'gelé' => self::FROZEN,
            'gele' => self::FROZEN,
            'frozen' => self::FROZEN,
            'supprimé' => self::DELETED,
            'supprime' => self::DELETED,
            'deleted' => self::DELETED,
            'archived' => self::DELETED,
        ];
        if (isset($aliases[$v])) {
            return $aliases[$v] === self::FILLED || $aliases[$v] === self::VACANT
                ? self::ACTIVE
                : $aliases[$v];
        }

        return in_array($v, self::ALL, true) ? $v : self::ACTIVE;
    }

    public static function label(string $status): string
    {
        return match (self::normalize($status)) {
            self::FROZEN => 'Gelé',
            self::DELETED => 'Supprimé',
            default => 'Actif',
        };
    }

    /**
     * État d’affichage combinant statut du poste et pourvoi.
     */
    public static function seatLabel(string $billetStatus, int $filled, int $authorized): string
    {
        $status = self::normalize($billetStatus);
        if ($status === self::FROZEN) {
            return 'Gelé';
        }
        if ($status === self::DELETED) {
            return 'Supprimé';
        }
        if ($filled <= 0) {
            return 'Vacant';
        }
        if ($filled < max(1, $authorized)) {
            return 'Partiellement pourvu';
        }

        return 'Pourvu';
    }

    public static function isOccupiable(string $status): bool
    {
        $s = self::normalize($status);

        return $s === self::ACTIVE;
    }
}
