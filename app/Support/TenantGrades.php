<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Container;
use App\Core\Session;
use App\Repositories\TenantRepository;

/**
 * Affichage des grades d’une communauté.
 *
 * Une communauté peut choisir « Aucun » référentiel (assistant de création ou back-office → Référentiel des grades) :
 * réglage `grades_hidden` de tenants.settings. Les grades restent attribués en base (un référentiel technique est
 * toujours posé) mais ne s’affichent plus dans l’en-tête, la fiche personnel et le tableau de bord.
 */
final class TenantGrades
{
    /** @var array<int, bool> */
    private static array $memo = [];

    public static function hidden(?int $tenantId = null): bool
    {
        $tenantId ??= (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return false;
        }
        if (!array_key_exists($tenantId, self::$memo)) {
            try {
                $settings = Container::get(TenantRepository::class)->getSettings($tenantId);
                self::$memo[$tenantId] = !empty($settings['grades_hidden']);
            } catch (\Throwable) {
                self::$memo[$tenantId] = false;
            }
        }

        return self::$memo[$tenantId];
    }

    public static function forget(int $tenantId): void
    {
        unset(self::$memo[$tenantId]);
    }
}
