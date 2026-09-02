<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Repositories\TenantLoginAccueilImageRepository;
use App\Support\LoginAccueilImageStorage;

/**
 * Images du sas d’accueil après connexion : photos de la communauté, sinon photo plateforme.
 */
final class LoginAccueilBackgroundService
{
    public const ROTATE_INTERVAL_MS = 10000;

    public function __construct(
        private TenantLoginAccueilImageRepository $images,
    ) {}

    /**
     * @param array<string, mixed>|null $communitySettings
     * @return array{
     *   urls: list<string>,
     *   alts: list<string>,
     *   rotate: bool,
     *   interval_ms: int,
     *   is_default: bool
     * }
     */
    public function forTenant(int $tenantId, ?array $communitySettings = null): array
    {
        $defaultUrl = LoginAccueilImageStorage::defaultPublicUrl();
        if ($tenantId < 1) {
            return [
                'urls' => [$defaultUrl],
                'alts' => [''],
                'rotate' => false,
                'interval_ms' => self::ROTATE_INTERVAL_MS,
                'is_default' => true,
            ];
        }
        $rows = $this->images->listForTenant($tenantId);
        $urls = [];
        $alts = [];
        foreach ($rows as $row) {
            $url = LoginAccueilImageStorage::publicUrl(isset($row['storage_path']) ? (string) $row['storage_path'] : null);
            if ($url === null || $url === '') {
                continue;
            }
            $urls[] = $url;
            $alts[] = trim((string) ($row['alt_text'] ?? ''));
        }
        if ($urls === []) {
            return [
                'urls' => [$defaultUrl],
                'alts' => [''],
                'rotate' => false,
                'interval_ms' => self::ROTATE_INTERVAL_MS,
                'is_default' => true,
            ];
        }
        $rotate = count($urls) > 1 && $this->slideshowEnabled($communitySettings);

        return [
            'urls' => $urls,
            'alts' => $alts,
            'rotate' => $rotate,
            'interval_ms' => self::ROTATE_INTERVAL_MS,
            'is_default' => false,
        ];
    }

    /**
     * @param array<string, mixed>|null $communitySettings
     */
    public function slideshowEnabled(?array $communitySettings): bool
    {
        if ($communitySettings === null || !array_key_exists('login_accueil_slideshow', $communitySettings)) {
            return true;
        }
        $flag = $communitySettings['login_accueil_slideshow'];
        if (is_bool($flag)) {
            return $flag;
        }
        if (is_int($flag) || is_float($flag)) {
            return (int) $flag === 1;
        }

        $s = strtolower(trim((string) $flag));

        return !in_array($s, ['0', 'false', 'off', 'no'], true);
    }
}
