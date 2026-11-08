<?php

declare(strict_types=1);

namespace App\Services\Watch;

use App\Support\PlanGift;

/**
 * Rang de la formule effective de chaque communauté (0 Gratuit, 1 Standard, 2 Pro, 3 Pro+), tenu dans un fichier
 * (storage/cache/watch/plans.json) rafraîchi par WatchFlusher : le choix de ralentir une requête ne fait donc aucune
 * requête SQL. Le rang vient de PlanGift::effectivePlanSlug, la même source que FeatureGateService::effectivePlanSlug
 * (formule offerte, abonnement actif ou en essai, essai fondateur Pro, formule de base).
 *
 * Fichier absent ou trop ancien, communauté inconnue : rang inconnu (null) et donc aucun ralentissement.
 */
final class PlanRankIndex
{
    /** Au-delà, l'index n'est plus cru : une formule a pu changer (secondes). */
    public const MAX_AGE_SECONDS = 900;

    /** @var array{at: int, t: array<int, int>}|null */
    private static ?array $memo = null;

    public static function path(): string
    {
        return WatchSpool::dir() . '/plans.json';
    }

    public static function forget(): void
    {
        self::$memo = null;
    }

    /** Rang de la communauté, ou null s'il est inconnu ou si l'index est trop ancien. */
    public static function rankFor(int $tenantId, ?int $now = null): ?int
    {
        if ($tenantId < 1) {
            return null;
        }
        if (self::$memo === null) {
            $data = json_decode((string) @file_get_contents(self::path()), true);
            if (!is_array($data) || !isset($data['at'], $data['t']) || !is_array($data['t'])) {
                return null;
            }
            self::$memo = ['at' => (int) $data['at'], 't' => array_map('intval', $data['t'])];
        }
        if (($now ?? time()) - self::$memo['at'] > self::MAX_AGE_SECONDS) {
            return null;
        }

        return self::$memo['t'][$tenantId] ?? null;
    }

    /** Âge de l'index (secondes), null s'il manque. */
    public static function ageSeconds(?int $now = null): ?int
    {
        $data = json_decode((string) @file_get_contents(self::path()), true);

        return is_array($data) && isset($data['at']) ? max(0, ($now ?? time()) - (int) $data['at']) : null;
    }

    /**
     * Rang d'une ligne de la table tenants.
     *
     * @param array<string, mixed> $tenant
     */
    public static function rankOfTenantRow(array $tenant, ?int $now = null): int
    {
        return PlanGift::rank(PlanGift::effectivePlanSlug($tenant, $now));
    }

    /**
     * Reconstruit l'index depuis la base et l'écrit. Ne lève pas : en cas d'échec l'ancien fichier reste en place.
     */
    public static function refresh(\PDO $pdo, ?int $now = null): bool
    {
        $now ??= time();
        try {
            $st = $pdo->query(
                'SELECT id, plan_slug, subscription_status, settings, stripe_subscription_id, paypal_subscription_id FROM tenants'
            );
            if ($st === false) {
                return false;
            }
            $map = [];
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $map[$id] = self::rankOfTenantRow($row, $now);
                }
            }
            $dir = WatchSpool::dir();
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return false;
            }
            $tmp = self::path() . '.' . getmypid() . '.tmp';
            $json = json_encode(['at' => $now, 't' => $map]);
            if ($json === false || @file_put_contents($tmp, $json) === false || !@rename($tmp, self::path())) {
                @unlink($tmp);

                return false;
            }
            self::forget();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
