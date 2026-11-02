<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * COMSPEC IDENTITY — pays émetteurs des papiers (fictifs par défaut, codes hors ISO).
 *
 * Format du numéro (number_format) : « # » = chiffre, « @ » = lettre, tout autre caractère est recopié.
 */
final class SseIdCountryRepository
{
    /** Pays fictifs proposés à chaque communauté (ajoutés s'ils manquent, jamais modifiés ensuite). */
    public const DEFAULTS = [
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
        $rows = $this->fetch($tenantId, $activeOnly);
        if ($rows === [] && $tenantId > 0) {
            $this->ensureDefaults($tenantId);
            $rows = $this->fetch($tenantId, $activeOnly);
        }

        return $rows;
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
        foreach (self::DEFAULTS as $c) {
            $exists = $this->db->fetchOne(
                'SELECT id FROM sse_id_countries WHERE tenant_id = :t AND code = :c',
                ['t' => $tenantId, 'c' => $c['code']]
            );
            if ($exists !== null) {
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
        return [
            'id' => (int) ($row['id'] ?? 0),
            'code' => strtoupper((string) ($row['code'] ?? '')),
            'name' => (string) ($row['name'] ?? ''),
            'name_en' => (string) ($row['name_en'] ?? ''),
            'mrz_code' => strtoupper((string) (($row['mrz_code'] ?? '') !== '' ? $row['mrz_code'] : ($row['code'] ?? ''))),
            'number_format' => (string) ($row['number_format'] ?? '@@#######'),
            'authority' => (string) ($row['authority'] ?? ''),
            'region' => (string) ($row['region'] ?? ''),
            'validity_years' => max(1, (int) ($row['validity_years'] ?? 10)),
            'fictional' => (int) ($row['fictional'] ?? 1) === 1,
            'active' => (int) ($row['active'] ?? 1) === 1,
        ];
    }
}
