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
