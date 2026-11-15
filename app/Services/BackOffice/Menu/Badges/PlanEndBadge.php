<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu\Badges;

use App\Core\Container;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\BackOffice\Menu\BadgeProvider;
use App\Services\BackOffice\Menu\PendingTaskDetail;
use App\Services\BackOffice\Menu\PendingTaskDetailProvider;
use App\Services\Platform\PlanEndWarningService;
use App\Support\PlanAccessWindow;

/**
 * Formule de la communauté qui se termine dans les 7 jours, ou terminée depuis moins de 14 jours : pastille rouge « 1 ».
 * Calcul pur sur la ligne tenants déjà lue (PlanAccessWindow), sans autre requête. Le texte de la tâche du tableau de
 * bord (nom de la formule, dates, conséquence) est composé par PlanEndTaskCopy.
 */
final class PlanEndBadge implements BadgeProvider, PendingTaskDetailProvider
{
    /** Une formule qui se termine dans ce nombre de jours déclenche la pastille. */
    public const WARNING_DAYS = 7;

    /** Une formule terminée depuis moins de ce nombre de jours la déclenche encore. */
    public const LAPSED_DAYS = 14;

    public function badge(int $tenantId, int $userId): ?array
    {
        $tenant = (new TenantRepository())->findById($tenantId);
        if (!is_array($tenant)) {
            return null;
        }
        $now = time();
        $hit = PlanAccessWindow::endingSoon($tenant, self::WARNING_DAYS, $now) !== null;
        if (!$hit) {
            $lapsed = PlanAccessWindow::lapsed($tenant, $now);
            $endedTs = $lapsed !== null ? strtotime($lapsed['ended_at']) : false;
            $hit = $endedTs !== false && $endedTs >= $now - self::LAPSED_DAYS * 86400;
        }

        return ['count' => $hit ? 1 : 0, 'important' => $hit];
    }

    public function detail(int $tenantId, int $userId, string $locale): ?PendingTaskDetail
    {
        $tenant = (new TenantRepository())->findById($tenantId);
        if (!is_array($tenant)) {
            return null;
        }
        $plans = Container::get(SubscriptionPlanRepository::class);
        $cfg = (new PlanEndWarningService())->config();

        return PlanEndTaskCopy::compose(
            $tenant,
            $locale,
            static function (string $slug) use ($plans): string {
                $p = $plans->findBySlug($slug);

                return trim((string) ($p['name'] ?? '')) ?: $slug;
            },
            static function (string $slug) use ($plans): bool {
                $p = $plans->findBySlug($slug);
                $f = $p ? json_decode((string) ($p['features_json'] ?? '{}'), true) : [];

                return is_array($f) && !empty($f['atak']);
            },
            $cfg['grace_enabled'] ? $cfg['grace_days'] : 0,
            self::ownerName($tenant),
        );
    }

    /**
     * Nom du propriétaire de la communauté, '' s'il est inconnu.
     *
     * @param array<string, mixed> $tenant
     */
    private static function ownerName(array $tenant): string
    {
        $ownerId = (int) ($tenant['owner_user_id'] ?? 0);
        if ($ownerId < 1) {
            return '';
        }
        try {
            $owner = Container::get(UserRepository::class)->findById($ownerId);
        } catch (\Throwable) {
            return '';
        }

        return is_array($owner) ? trim((string) ($owner['display_name'] ?? '')) : '';
    }
}
