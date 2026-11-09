<?php

declare(strict_types=1);

namespace App\Services\GameEquipment\Configurator;

/**
 * Catalogue de base d'Arma 3 livré avec le site (database/data/arma3_catalogue_vanilla.json), extrait hors ligne de
 * la configuration du jeu installé par tools/arma/extraire_catalogue_vanilla.py : jeu de base et DLC officiels non
 * chiffrés, métadonnées seulement (classes, noms anglais et français, masses, contenances, compatibilités).
 *
 * Pur : lit le fichier, contrôle chaque ligne avec ItemCatalogImport::row (mêmes bornes qu'un import fait en jeu) et
 * rend des lignes prêtes pour GameItemSpecRepository::importVanilla, triées par classe (lots stables).
 */
final class VanillaCatalog
{
    public const FORMAT = 'comspec-vanilla-catalog';

    public const MAX_BYTES = 8 * 1024 * 1024;

    /** @var array<string, array{version: string, rows: list<array<string, mixed>>, dlc: list<string>}> */
    private static array $cache = [];

    public static function defaultPath(): string
    {
        return dirname(__DIR__, 4) . '/database/data/arma3_catalogue_vanilla.json';
    }

    /**
     * @return array{ok: bool, version: string, rows: list<array<string, mixed>>, dlc: list<string>}
     */
    public static function load(?string $path = null): array
    {
        $path ??= self::defaultPath();
        if (isset(self::$cache[$path])) {
            return ['ok' => true] + self::$cache[$path];
        }
        $none = ['ok' => false, 'version' => '', 'rows' => [], 'dlc' => []];
        if (!is_file($path) || (int) filesize($path) > self::MAX_BYTES) {
            return $none;
        }
        $doc = json_decode((string) file_get_contents($path), true);
        if (!is_array($doc) || ($doc['format'] ?? null) !== self::FORMAT || (int) ($doc['v'] ?? 0) !== 1 || !is_array($doc['items'] ?? null)) {
            return $none;
        }
        $parsed = self::parse($doc);
        self::$cache[$path] = $parsed;

        return ['ok' => true] + $parsed;
    }

    /**
     * @param array<string, mixed> $doc
     * @return array{version: string, rows: list<array<string, mixed>>, dlc: list<string>}
     */
    public static function parse(array $doc): array
    {
        $groups = is_array($doc['groups'] ?? null) ? $doc['groups'] : [];
        $group = static fn (mixed $i): array => is_int($i) && isset($groups[$i]) && is_array($groups[$i]) ? $groups[$i] : [];
        $rows = [];
        foreach (array_slice((array) $doc['items'], 0, ItemCatalogImport::MAX_ITEMS) as $it) {
            if (!is_array($it) || count($it) < 14) {
                continue;
            }
            $compat = null;
            if (is_array($it[11] ?? null) && count($it[11]) === 4) {
                $compat = array_map($group, $it[11]);
            }
            // Même forme qu'une ligne exportée en jeu : [classe, racine, type, sous-type, nom, masse, charge,
            // emplacement, munitions, emplacements autorisés, compat, chargeurs, mod].
            $row = ItemCatalogImport::row([
                $it[0], $it[1], $it[2], $it[3], $it[5], $it[6], $it[7], $it[8], $it[9], $it[10],
                $compat ?? [], $it[12] !== null ? $group($it[12]) : null, $it[13],
            ]);
            if ($row === null) {
                continue;
            }
            if ($compat === null) {
                $row['compat'] = null;
            }
            if ($it[12] === null) {
                $row['mags'] = null;
            }
            $row['name_en'] = is_string($it[4] ?? null) ? mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', $it[4])), 0, 160) : '';
            if ($row['name'] === '') {
                $row['name'] = $row['name_en'];
            }
            $rows[strtolower($row['class_name'])] = $row;
        }
        ksort($rows, SORT_STRING);
        $dlc = [];
        foreach (is_array($doc['dlc'] ?? null) ? $doc['dlc'] : [] as $d) {
            if (is_string($d) && $d !== '') {
                $dlc[] = mb_substr($d, 0, 60);
            }
        }

        return [
            'version' => preg_match('/^[A-Za-z0-9._-]{1,40}$/', (string) ($doc['version'] ?? '')) === 1 ? (string) $doc['version'] : substr(hash('sha256', serialize(array_keys($rows))), 0, 16),
            'rows' => array_values($rows),
            'dlc' => $dlc,
        ];
    }

    /**
     * Nombre d'objets par famille du configurateur (rapport, aide).
     *
     * @param list<array<string, mixed>> $rows
     * @return array<string, int>
     */
    public static function familyCounts(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $k = ConfiguratorKinds::fromSpec((string) $r['type_main'], (string) $r['type_sub'], $r['slot_type'] ?? null)['kind'];
            $out[$k] = ($out[$k] ?? 0) + 1;
        }
        arsort($out);

        return $out;
    }

    /** Vide le cache (tests). */
    public static function forget(): void
    {
        self::$cache = [];
    }
}
