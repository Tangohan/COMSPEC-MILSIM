<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Quatre rôles techniques Athena — distincts des postes MILSIM (billets / duty).
 */
final class AthenaTechnicalRoles
{
    public const OWNER = 'owner';
    public const ADMIN = 'admin';
    public const MANAGER = 'manager';
    public const MEMBER = 'member';

    /** @var array<string, list<string>> slugs historiques → rôle technique */
    private const SLUG_MAP = [
        self::OWNER => ['community_owner'],
        self::ADMIN => ['tenant_admin', 'deputy_commander'],
        self::MANAGER => ['hr', 'recruiter', 'trainer', 'senior_instructor', 'instructor'],
        self::MEMBER => ['member'],
    ];

    public static function label(string $role): string
    {
        return match ($role) {
            self::OWNER => 'Propriétaire',
            self::ADMIN => 'Administrateur technique',
            self::MANAGER => 'Gestionnaire organisation',
            self::MEMBER => 'Membre',
            default => $role,
        };
    }

    public static function description(string $role): string
    {
        return match ($role) {
            self::OWNER => 'Propriétaire du tenant : facturation, suppression, configuration ultime.',
            self::ADMIN => 'Configuration technique Athena (modules, paramètres, accès). Pas un grade ni un poste S2/S3.',
            self::MANAGER => 'Pilotage organisation / missions / effectifs sans être admin plateforme.',
            self::MEMBER => 'Utilisateur normal : le poste ORBAT et le duty mission définissent le travail.',
            default => '',
        };
    }

    /**
     * @return list<array{id: string, label: string, description: string, legacy_slugs: list<string>}>
     */
    public static function catalog(): array
    {
        $out = [];
        foreach ([self::OWNER, self::ADMIN, self::MANAGER, self::MEMBER] as $id) {
            $out[] = [
                'id' => $id,
                'label' => self::label($id),
                'description' => self::description($id),
                'legacy_slugs' => self::SLUG_MAP[$id],
            ];
        }

        return $out;
    }

    public static function fromLegacySlug(?string $slug): string
    {
        $slug = strtolower(trim((string) $slug));
        if ($slug === '') {
            return self::MEMBER;
        }
        foreach (self::SLUG_MAP as $role => $slugs) {
            if (in_array($slug, $slugs, true)) {
                return $role;
            }
        }

        return self::MEMBER;
    }
}
