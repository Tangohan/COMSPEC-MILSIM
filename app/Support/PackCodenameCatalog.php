<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Noms de version du portail Athena : un nom de ville américaine par version X.Y.
 *
 * Convention (depuis 1.7.0) : chaque nouvelle version X.Y du site (storage/app_version.json)
 * reçoit le prochain nom de ville réservé, jamais réutilisé. Les correctifs X.Y.Z gardent le
 * nom de leur version. Toutes les versions antérieures à 1.7 restent « Phoenix ».
 */
final class PackCodenameCatalog
{
    /**
     * Versions déjà nommées, dans l’ordre. Ne pas réordonner ni recycler.
     *
     * @var array<string, array{name: string, state: string}>
     */
    private const ASSIGNED = [
        '1.6' => ['name' => 'Phoenix', 'state' => 'Arizona'],
        '1.7' => ['name' => 'Denver', 'state' => 'Colorado'],
    ];

    /**
     * Prochains noms, à attribuer dans l’ordre (déplacer le premier dans ASSIGNED au bump X.Y).
     *
     * @var list<array{name: string, state: string}>
     */
    private const RESERVED = [
        ['name' => 'Austin', 'state' => 'Texas'],
        ['name' => 'Seattle', 'state' => 'Washington'],
        ['name' => 'Boston', 'state' => 'Massachusetts'],
        ['name' => 'Nashville', 'state' => 'Tennessee'],
        ['name' => 'Portland', 'state' => 'Oregon'],
        ['name' => 'Chicago', 'state' => 'Illinois'],
        ['name' => 'Miami', 'state' => 'Floride'],
        ['name' => 'Anchorage', 'state' => 'Alaska'],
        ['name' => 'Houston', 'state' => 'Texas'],
        ['name' => 'Savannah', 'state' => 'Géorgie'],
        ['name' => 'Tucson', 'state' => 'Arizona'],
        ['name' => 'San Diego', 'state' => 'Californie'],
    ];

    /**
     * @return array{major: int, line: string, name: string, state: string, label: string, note: string}
     */
    public static function forVersion(string $version): array
    {
        $line = self::lineOf($version);
        $major = self::majorOf($version);
        $row = self::ASSIGNED[$line] ?? null;
        if ($row === null && $line !== '' && version_compare($line, (string) array_key_first(self::ASSIGNED), '<')) {
            $row = self::ASSIGNED[array_key_first(self::ASSIGNED)];
        }
        if ($row === null) {
            return [
                'major' => $major,
                'line' => $line,
                'name' => '',
                'state' => '',
                'label' => '',
                'note' => 'Ajouter un nom de ville dans PackCodenameCatalog pour la version ' . $line,
            ];
        }

        return [
            'major' => $major,
            'line' => $line,
            'name' => $row['name'],
            'state' => $row['state'],
            'label' => $row['name'],
            'note' => $row['name'] . ' (' . $row['state'] . ')',
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

    /** « 1.7.2 » → « 1.7 », '' si illisible. */
    public static function lineOf(string $version): string
    {
        if (preg_match('/^(\d+)\.(\d+)/', trim($version), $m)) {
            return (int) $m[1] . '.' . (int) $m[2];
        }

        return '';
    }

    /**
     * Nom à donner à la prochaine version X.Y (X.Y+1 de la dernière nommée).
     *
     * @return array{line: string, name: string, state: string, label: string}|null
     */
    public static function nextReserved(): ?array
    {
        $row = self::RESERVED[0] ?? null;
        if ($row === null) {
            return null;
        }
        $last = (string) array_key_last(self::ASSIGNED);
        [$major, $minor] = array_map('intval', explode('.', $last));

        return [
            'line' => $major . '.' . ($minor + 1),
            'name' => $row['name'],
            'state' => $row['state'],
            'label' => $row['name'],
        ];
    }

    /**
     * @return array<string, array{name: string, state: string}>
     */
    public static function assigned(): array
    {
        return self::ASSIGNED;
    }
}
