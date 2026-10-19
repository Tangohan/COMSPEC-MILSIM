<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Branding étendu (bannière, couleurs, favicon). Le logo peut rester sur `tenants.logo_url` :
 * utiliser mergeWithTenantLogo() pour l’affichage unifié.
 */
class TenantBrandingRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return array<string, mixed>|null */
    public function findByTenantId(int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tenant_branding WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Bannière, logo et couleurs de toutes les communautés en une requête (cartes de l’accueil public).
     *
     * @return array<int, array{banner_url: string, logo_url: string, primary_color: string, accent_color: string}>
     */
    public function listCardBrandingByTenant(): array
    {
        $stmt = $this->pdo->query('SELECT tenant_id, banner_url, logo_url, primary_color, accent_color FROM tenant_branding');
        if ($stmt === false) {
            return [];
        }
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['tenant_id']] = [
                'banner_url' => trim((string) ($row['banner_url'] ?? '')),
                'logo_url' => trim((string) ($row['logo_url'] ?? '')),
                'primary_color' => trim((string) ($row['primary_color'] ?? '')),
                'accent_color' => trim((string) ($row['accent_color'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * Crée ou met à jour la ligne de marque (logo/bannière/favicon/couleurs) d’un tenant.
     * Seules les clés fournies dans $fields sont modifiées (les autres colonnes sont préservées).
     *
     * @param array<string, string|null> $fields clés autorisées : logo_url, banner_url, favicon_url, primary_color, accent_color, public_home_hero_json
     */
    public function upsert(int $tenantId, array $fields): void
    {
        $allowed = ['logo_url', 'banner_url', 'favicon_url', 'primary_color', 'accent_color', 'public_home_hero_json'];
        $fields = array_intersect_key($fields, array_flip($allowed));
        if ($fields === []) {
            return;
        }
        $existing = $this->findByTenantId($tenantId);
        $merged = array_merge([
            'logo_url' => $existing['logo_url'] ?? null,
            'banner_url' => $existing['banner_url'] ?? null,
            'favicon_url' => $existing['favicon_url'] ?? null,
            'primary_color' => $existing['primary_color'] ?? null,
            'accent_color' => $existing['accent_color'] ?? null,
            'public_home_hero_json' => $existing['public_home_hero_json'] ?? null,
        ], $fields);
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_branding (tenant_id, logo_url, banner_url, favicon_url, primary_color, accent_color, public_home_hero_json, updated_at)
             VALUES (:tenant_id, :logo_url, :banner_url, :favicon_url, :primary_color, :accent_color, :public_home_hero_json, NOW())
             ON DUPLICATE KEY UPDATE
                logo_url = VALUES(logo_url),
                banner_url = VALUES(banner_url),
                favicon_url = VALUES(favicon_url),
                primary_color = VALUES(primary_color),
                accent_color = VALUES(accent_color),
                public_home_hero_json = VALUES(public_home_hero_json),
                updated_at = NOW()'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'logo_url' => $merged['logo_url'],
            'banner_url' => $merged['banner_url'],
            'favicon_url' => $merged['favicon_url'],
            'primary_color' => $merged['primary_color'],
            'accent_color' => $merged['accent_color'],
            'public_home_hero_json' => $merged['public_home_hero_json'],
        ]);
    }

    /**
     * @param array<string, mixed>|null $tenantRow ligne `tenants` (logo_url, …)
     * @return array{logo_url: ?string, banner_url: ?string, primary_color: ?string, accent_color: ?string, favicon_url: ?string, public_home_hero_json: mixed}
     */
    public function mergeWithTenantLogo(?array $tenantRow, ?array $brandingRow): array
    {
        $tenantLogo = isset($tenantRow['logo_url']) ? trim((string) $tenantRow['logo_url']) : '';
        $brandLogo = isset($brandingRow['logo_url']) ? trim((string) $brandingRow['logo_url']) : '';
        $logo = $brandLogo !== '' ? $brandLogo : ($tenantLogo !== '' ? $tenantLogo : null);

        $hero = null;
        if (isset($brandingRow['public_home_hero_json'])) {
            $raw = $brandingRow['public_home_hero_json'];
            if (is_string($raw) && $raw !== '') {
                $hero = json_decode($raw, true);
            } elseif (is_array($raw)) {
                $hero = $raw;
            }
        }

        return [
            'logo_url' => $logo,
            'banner_url' => $brandingRow['banner_url'] ?? null,
            'primary_color' => $brandingRow['primary_color'] ?? null,
            'accent_color' => $brandingRow['accent_color'] ?? null,
            'favicon_url' => $brandingRow['favicon_url'] ?? null,
            'public_home_hero_json' => $hero,
        ];
    }
}
