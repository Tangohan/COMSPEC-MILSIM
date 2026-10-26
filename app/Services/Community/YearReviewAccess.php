<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Container;
use App\Repositories\PlatformSettingsRepository;
use App\Services\Platform\FeatureGateService;
use App\Support\YearReviewCalendar;

/**
 * Qui voit quoi dans la rétrospective annuelle : calendrier réglé par l'administration du site, bilan collectif
 * réservé aux formules payantes (module year_review_collective), rétrospective individuelle pour tous.
 */
final class YearReviewAccess
{
    public const FEATURE = 'year_review_collective';

    public static function calendar(): YearReviewCalendar
    {
        try {
            $repo = new PlatformSettingsRepository();
            $keys = [YearReviewCalendar::SETTING_ENABLED, YearReviewCalendar::SETTING_PUBLISH_MD, YearReviewCalendar::SETTING_POPUP_DAYS, YearReviewCalendar::SETTING_EMAIL];
            $settings = [];
            foreach ($keys as $k) {
                $v = $repo->get($k, '');
                if ($v !== '') {
                    $settings[$k] = $v;
                }
            }

            return YearReviewCalendar::fromSettings($settings);
        } catch (\Throwable) {
            return new YearReviewCalendar();
        }
    }

    public static function collectiveAllowed(int $tenantId): bool
    {
        if ($tenantId < 1) {
            return false;
        }
        try {
            return Container::get(FeatureGateService::class)->allowsWithCatalogDefault($tenantId, self::FEATURE);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Gestionnaire de la communauté (voit l'invitation à passer à une formule payante, pas les simples membres). */
    public static function isManager(): bool
    {
        return function_exists('can') && (can('admin.organization') || can('admin.access'));
    }

    public static function isPlatformAdmin(): bool
    {
        return function_exists('is_platform_admin') && is_platform_admin();
    }
}
