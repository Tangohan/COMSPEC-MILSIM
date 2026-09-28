<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Noms de code des « gros » packs Overwatch / Athena.
 *
 * Convention : à chaque changement du **premier** chiffre de version (X dans X.Y.Z),
 * le pack reçoit un nom de ville ou d’État des États-Unis (jamais réutilisé).
 * Les mises à jour mineures (Y ou Z) restent sous le même nom de code.
 */
final class PackCodenameCatalog
{
    /**
     * Index = major (premier segment de version Overwatch / portail aligné).
     * Ordre chronologique de déblocage — ne pas réordonner ni recycler.
     *
     * @var array<int, array{name: string, kind: 'city'|'state', note: string}>
     */
    private const BY_MAJOR = [
        1 => [
            'name' => 'Phoenix',
            'kind' => 'city',
            'note' => 'Ligne 1.x — liaison Athena / Overwatch initiale',
        ],
        2 => [
            'name' => 'Denver',
            'kind' => 'city',
            'note' => 'Réservé au passage en 2.x',
        ],
        3 => [
            'name' => 'Austin',
            'kind' => 'city',
            'note' => 'Réservé au passage en 3.x',
        ],
        4 => [
            'name' => 'Seattle',
            'kind' => 'city',
            'note' => 'Réservé au passage en 4.x',
        ],
        5 => [
            'name' => 'Boston',
            'kind' => 'city',
            'note' => 'Réservé au passage en 5.x',
        ],
        6 => [
            'name' => 'Nashville',
            'kind' => 'city',
            'note' => 'Réservé au passage en 6.x',
        ],
        7 => [
            'name' => 'Portland',
            'kind' => 'city',
            'note' => 'Réservé au passage en 7.x',
        ],
        8 => [
            'name' => 'Chicago',
            'kind' => 'city',
            'note' => 'Réservé au passage en 8.x',
        ],
        9 => [
            'name' => 'Miami',
            'kind' => 'city',
            'note' => 'Réservé au passage en 9.x',
        ],
        10 => [
            'name' => 'Anchorage',
            'kind' => 'city',
            'note' => 'Réservé au passage en 10.x',
        ],
        11 => [
            'name' => 'Montana',
            'kind' => 'state',
            'note' => 'Réservé au passage en 11.x',
        ],
        12 => [
            'name' => 'Oregon',
            'kind' => 'state',
            'note' => 'Réservé au passage en 12.x',
        ],
    ];

    /**
     * @return array{major: int, name: string, kind: string, label: string, note: string}
     */
    public static function forVersion(string $version): array
    {
        $major = self::majorOf($version);
        $row = self::BY_MAJOR[$major] ?? null;
        if ($row === null) {
            return [
                'major' => $major,
                'name' => '',
                'kind' => '',
                'label' => '',
                'note' => 'Ajouter un nom dans PackCodenameCatalog pour le major ' . $major,
            ];
        }

        $name = $row['name'];

        return [
            'major' => $major,
            'name' => $name,
            'kind' => $row['kind'],
            'label' => 'Opération ' . $name,
            'note' => $row['note'],
        ];
    }

    public static function nameForVersion(string $version): string
    {
        return self::forVersion($version)['name'];
    }

    public static function labelForVersion(string $version): string
    {
        return self::forVersion($version)['label'];
    }

    public static function majorOf(string $version): int
    {
        if (preg_match('/^(\d+)\./', trim($version), $m)) {
            return max(0, (int) $m[1]);
        }

        return 0;
    }

    /**
     * Prochain nom à utiliser lors d’un bump de major.
     *
     * @return array{major: int, name: string, label: string}|null
     */
    public static function nextReserved(?int $currentMajor = null): ?array
    {
        $from = ($currentMajor ?? 1) + 1;
        if (!isset(self::BY_MAJOR[$from])) {
            return null;
        }
        $row = self::BY_MAJOR[$from];

        return [
            'major' => $from,
            'name' => $row['name'],
            'label' => 'Opération ' . $row['name'],
        ];
    }
}
