<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SseIdentityCatalog;

/**
 * COMSPEC IDENTITY — pays émetteurs des papiers (fictifs par défaut, codes hors ISO).
 *
 * Format du numéro (number_format) : « # » = chiffre, « @ » = lettre, tout autre caractère est recopié.
 * Pays créés par la communauté : thème d'univers, langues imprimées, types de papiers délivrés et durée de
 * validité par type, drapeau téléversé ou couleurs. Un pays n'est jamais supprimé : il s'archive (active = 0).
 */
final class SseIdCountryRepository
{
    /** Pays de simulation proposés à chaque communauté (ajoutés s'ils manquent, jamais modifiés ensuite). */
    public const DEFAULTS = [
        ['code' => 'USA', 'name' => 'États-Unis', 'name_en' => 'United States', 'number_format' => 'US########', 'authority' => 'Bureau des documents de simulation', 'region' => 'NORTH_AMERICA', 'validity_years' => 10],
        ['code' => 'GBR', 'name' => 'Royaume-Uni', 'name_en' => 'United Kingdom', 'number_format' => 'GB########', 'authority' => 'Bureau des documents de simulation', 'region' => 'EUROPE', 'validity_years' => 10],
        ['code' => 'FRA', 'name' => 'France', 'name_en' => 'France', 'number_format' => 'FR########', 'authority' => 'Bureau des documents de simulation', 'region' => 'EUROPE', 'validity_years' => 10],
        ['code' => 'BEL', 'name' => 'Belgique', 'name_en' => 'Belgium', 'number_format' => 'BE########', 'authority' => 'Bureau des documents de simulation', 'region' => 'EUROPE', 'validity_years' => 10],
        ['code' => 'DEU', 'name' => 'Allemagne', 'name_en' => 'Germany', 'number_format' => 'DE########', 'authority' => 'Bureau des documents de simulation', 'region' => 'EUROPE', 'validity_years' => 10],
        ['code' => 'ESP', 'name' => 'Espagne', 'name_en' => 'Spain', 'number_format' => 'ES########', 'authority' => 'Bureau des documents de simulation', 'region' => 'EUROPE', 'validity_years' => 10],
        ['code' => 'COL', 'name' => 'Colombie', 'name_en' => 'Colombia', 'number_format' => 'CO########', 'authority' => 'Bureau des documents de simulation', 'region' => 'SOUTH_AMERICA', 'validity_years' => 10],
        ['code' => 'AUT', 'name' => 'Autriche', 'name_en' => 'Austria', 'number_format' => 'AT########', 'authority' => 'Bureau des documents de simulation', 'region' => 'EUROPE', 'validity_years' => 10],
        ['code' => 'ALT', 'name' => 'République d’Altis', 'name_en' => 'Republic of Altis', 'number_format' => 'AL@######', 'authority' => 'Préfecture de Kavala', 'region' => 'ALTIS', 'validity_years' => 10],
        ['code' => 'TNA', 'name' => 'Tanoa', 'name_en' => 'Tanoa', 'number_format' => 'T########', 'authority' => 'Bureau de l’état civil de Georgetown', 'region' => 'TANOA', 'validity_years' => 10],
        ['code' => 'LVN', 'name' => 'Livonie', 'name_en' => 'Livonia', 'number_format' => 'LV#######', 'authority' => 'Mairie de Lubjansk', 'region' => 'LIVONIA', 'validity_years' => 10],
        ['code' => 'MLD', 'name' => 'Malden', 'name_en' => 'Malden', 'number_format' => 'M@@######', 'authority' => 'Préfecture du Port', 'region' => 'MALDEN', 'validity_years' => 10],
    ];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        SseIdentityRepository::ensureSchema();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, bool $activeOnly = true): array
    {
        if ($tenantId > 0) {
            $this->ensureDefaults($tenantId);
        }

        return $this->fetch($tenantId, $activeOnly);
    }

    /**
     * Durée de validité (années) d'un type de papier pour ce pays.
     *
     * @param array<string, mixed> $country
     */
    public static function validityFor(array $country, string $type): int
    {
        return max(1, (int) ($country['validity'][$type] ?? $country['validity_years'] ?? 10));
    }

    /** @return array<string, mixed>|null */
    public function findByCode(int $tenantId, string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        foreach ($this->listForTenant($tenantId, false) as $row) {
            if ($row['code'] === $code) {
                return $row;
            }
        }

        return null;
    }

    public function ensureDefaults(int $tenantId): int
    {
        $added = 0;
        $existingCodes = array_column($this->db->fetchAll(
            'SELECT code FROM sse_id_countries WHERE tenant_id = :t', ['t' => $tenantId]
        ), 'code');
        foreach (self::DEFAULTS as $c) {
            if (in_array($c['code'], $existingCodes, true)) {
                continue;
            }
            $this->db->insert(
                'INSERT INTO sse_id_countries (tenant_id, code, name, name_en, mrz_code, number_format, authority, region, validity_years, fictional, active, created_at)
                 VALUES (:t, :code, :name, :name_en, :mrz, :fmt, :auth, :region, :years, 1, 1, :now)',
                [
                    't' => $tenantId,
                    'code' => $c['code'],
                    'name' => $c['name'],
                    'name_en' => $c['name_en'],
                    'mrz' => $c['code'],
                    'fmt' => $c['number_format'],
                    'auth' => $c['authority'],
                    'region' => $c['region'],
                    'years' => $c['validity_years'],
                    'now' => gmdate('Y-m-d H:i:s'),
                ]
            );
            $added++;
        }

        return $added;
    }

    /**
     * Liste du registre (archivés compris) avec recherche sur le code, le nom et l'autorité.
     *
     * @return list<array<string, mixed>>
     */
    public function search(int $tenantId, string $q = '', bool $withArchived = true): array
    {
        $this->listForTenant($tenantId);
        $rows = $this->fetch($tenantId, !$withArchived);
        $q = mb_strtolower(trim($q));
        if ($q === '') {
            return $rows;
        }

        return array_values(array_filter($rows, static fn (array $c): bool => str_contains(mb_strtolower($c['code'] . ' ' . $c['name'] . ' ' . $c['name_en'] . ' ' . $c['authority']), $q)));
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_id_countries WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);

        return $row === null ? null : self::hydrate($row);
    }

    /**
     * @param array<string, mixed> $data déjà validé (SseIdCountryCatalog) : code, name, name_en, mrz_code, number_format,
     *     authority, region, theme, languages[], doc_types[], validity[type => années], flag_path, color_primary, color_secondary
     */
    public function create(int $tenantId, array $data, ?int $by = null): int
    {
        $row = self::row($data);

        return $this->db->insert(
            'INSERT INTO sse_id_countries (tenant_id, code, name, name_en, mrz_code, number_format, authority, region, validity_years,
                fictional, active, theme, languages, doc_types, validity_json, flag_path, color_primary, color_secondary, created_by_user_id, created_at)
             VALUES (:t, :code, :name, :name_en, :mrz, :fmt, :auth, :region, :years, 1, 1, :theme, :langs, :types, :validity, :flag,
                :c1, :c2, :by, :now)',
            ['t' => $tenantId, 'code' => strtoupper((string) $data['code']), 'by' => $by, 'now' => gmdate('Y-m-d H:i:s')] + $row
        );
    }

    /**
     * Le code d'un pays ne change pas (il est imprimé sur les papiers déjà émis).
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, int $tenantId, array $data): bool
    {
        $row = self::row($data);

        return $this->db->execute(
            'UPDATE sse_id_countries SET name = :name, name_en = :name_en, mrz_code = :mrz, number_format = :fmt, authority = :auth,
                region = :region, validity_years = :years, fictional = 1, theme = :theme, languages = :langs, doc_types = :types,
                validity_json = :validity, flag_path = :flag, color_primary = :c1, color_secondary = :c2, updated_at = :now
             WHERE id = :id AND tenant_id = :t',
            ['id' => $id, 't' => $tenantId, 'now' => gmdate('Y-m-d H:i:s')] + $row
        ) > 0;
    }

    /** Archive (retire des formulaires, garde les papiers émis) ou rétablit un pays. */
    public function setArchived(int $id, int $tenantId, bool $archived): bool
    {
        return $this->db->execute(
            'UPDATE sse_id_countries SET active = :a, archived_at = :at, updated_at = :now WHERE id = :id AND tenant_id = :t',
            ['a' => $archived ? 0 : 1, 'at' => $archived ? gmdate('Y-m-d H:i:s') : null, 'now' => gmdate('Y-m-d H:i:s'), 'id' => $id, 't' => $tenantId]
        ) > 0;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function row(array $data): array
    {
        $types = array_values(array_filter((array) ($data['doc_types'] ?? []), static fn ($t): bool => is_string($t) && isset(SseIdentityCatalog::DOC_TYPES[$t])));
        $validity = [];
        foreach ((array) ($data['validity'] ?? []) as $t => $y) {
            if (isset(SseIdentityCatalog::DOC_TYPES[$t]) && is_numeric($y)) {
                $validity[$t] = max(1, min(50, (int) $y));
            }
        }
        $years = $validity !== [] ? max($validity) : max(1, min(50, (int) ($data['validity_years'] ?? 10)));
        $str = static fn (mixed $v, int $max): ?string => ($s = trim(is_scalar($v) ? (string) $v : '')) !== '' ? mb_substr($s, 0, $max) : null;

        return [
            'name' => (string) $str($data['name'] ?? '', 120),
            'name_en' => $str($data['name_en'] ?? null, 120),
            'mrz' => strtoupper((string) ($str($data['mrz_code'] ?? null, 3) ?? (string) ($data['code'] ?? ''))),
            'fmt' => strtoupper((string) $str($data['number_format'] ?? '', 20)),
            'auth' => $str($data['authority'] ?? null, 160),
            'region' => $str($data['region'] ?? null, 24),
            'years' => $years,
            'theme' => (string) ($data['theme'] ?? 'contemporary'),
            'langs' => ($l = implode(',', (array) ($data['languages'] ?? []))) !== '' ? mb_substr($l, 0, 64) : null,
            'types' => $types !== [] ? implode(',', $types) : null,
            'validity' => $validity !== [] ? json_encode($validity) : null,
            'flag' => $str($data['flag_path'] ?? null, 255),
            'c1' => $data['color_primary'] ?? null,
            'c2' => $data['color_secondary'] ?? null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetch(int $tenantId, bool $activeOnly): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_id_countries WHERE tenant_id = :t' . ($activeOnly ? ' AND active = 1' : '') . ' ORDER BY code',
            ['t' => $tenantId]
        );

        return array_map([self::class, 'hydrate'], $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrate(array $row): array
    {
        $years = max(1, (int) ($row['validity_years'] ?? 10));
        $types = array_values(array_filter(explode(',', (string) ($row['doc_types'] ?? '')), static fn (string $t): bool => isset(SseIdentityCatalog::DOC_TYPES[$t])));
        if ($types === []) {
            $types = array_keys(SseIdentityCatalog::DOC_TYPES);
        }
        $json = json_decode((string) ($row['validity_json'] ?? ''), true);
        $validity = [];
        foreach ($types as $t) {
            $validity[$t] = is_array($json) && isset($json[$t]) ? max(1, (int) $json[$t]) : $years;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'code' => strtoupper((string) ($row['code'] ?? '')),
            'name' => (string) ($row['name'] ?? ''),
            'name_en' => (string) ($row['name_en'] ?? ''),
            'mrz_code' => strtoupper((string) (($row['mrz_code'] ?? '') !== '' ? $row['mrz_code'] : ($row['code'] ?? ''))),
            'number_format' => (string) ($row['number_format'] ?? '@@#######'),
            'authority' => (string) ($row['authority'] ?? ''),
            'region' => (string) ($row['region'] ?? ''),
            'validity_years' => $years,
            'fictional' => (int) ($row['fictional'] ?? 1) === 1,
            'active' => (int) ($row['active'] ?? 1) === 1,
            'theme' => (string) (($row['theme'] ?? '') !== '' ? $row['theme'] : 'contemporary'),
            'languages' => array_values(array_filter(explode(',', (string) ($row['languages'] ?? '')))),
            'doc_types' => $types,
            'validity' => $validity,
            'flag_path' => ($row['flag_path'] ?? null) !== null && $row['flag_path'] !== '' ? (string) $row['flag_path'] : null,
            'color_primary' => ($row['color_primary'] ?? null) !== null && $row['color_primary'] !== '' ? (string) $row['color_primary'] : null,
            'color_secondary' => ($row['color_secondary'] ?? null) !== null && $row['color_secondary'] !== '' ? (string) $row['color_secondary'] : null,
            'archived_at' => $row['archived_at'] ?? null,
        ];
    }
}
