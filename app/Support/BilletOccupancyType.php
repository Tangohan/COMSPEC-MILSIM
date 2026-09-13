<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Types d’occupation d’un poste ORBAT.
 * Le poste est indépendant du personnel ; l’occupation décrit le lien.
 */
final class BilletOccupancyType
{
    public const PRIMARY = 'primary';
    public const ACTING = 'acting';
    public const DEPUTY = 'deputy';
    public const ALTERNATE = 'alternate';

    public const ALL = [
        self::PRIMARY,
        self::ACTING,
        self::DEPUTY,
        self::ALTERNATE,
    ];

    public static function normalize(?string $raw): string
    {
        $v = strtolower(trim((string) $raw));
        $aliases = [
            'titulaire' => self::PRIMARY,
            'holder' => self::PRIMARY,
            'interim' => self::ACTING,
            'intérim' => self::ACTING,
            'interim_acting' => self::ACTING,
            'adjoint' => self::DEPUTY,
            'suppléance' => self::DEPUTY,
            'suppleance' => self::DEPUTY,
            'suppléant' => self::ALTERNATE,
            'suppleant' => self::ALTERNATE,
            'secondary' => self::ALTERNATE,
        ];
        if (isset($aliases[$v])) {
            return $aliases[$v];
        }

        return in_array($v, self::ALL, true) ? $v : self::PRIMARY;
    }

    public static function label(string $type): string
    {
        return match (self::normalize($type)) {
            self::ACTING => 'Intérim',
            self::DEPUTY => 'Adjoint / suppléance',
            self::ALTERNATE => 'Suppléant',
            default => 'Titulaire',
        };
    }

    /** Compte pour le pourvoi du poste (effectif théorique). */
    public static function fillsSeat(string $type): bool
    {
        $t = self::normalize($type);

        return $t === self::PRIMARY || $t === self::ACTING;
    }

    /** Conserve le poste organique du personnel. */
    public static function keepsOrganicByDefault(string $type): bool
    {
        $t = self::normalize($type);

        return $t === self::ACTING || $t === self::DEPUTY || $t === self::ALTERNATE;
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::ALL as $id) {
            $out[] = ['id' => $id, 'label' => self::label($id)];
        }

        return $out;
    }
}
