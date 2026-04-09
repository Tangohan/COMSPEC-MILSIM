<?php

declare(strict_types=1);

namespace App\Services\Analytics;

final class AnalyticsEventName
{
    public const TRAINING_CATALOG_VIEW = 'training_catalog_view';

    public const COURSE_VIEW = 'course_view';

    public const COURSE_SHARE_CODE_USED = 'course_share_code_used';

    public const TENANT_PUBLIC_VIEW = 'tenant_public_view';

    public const RECRUITMENT_OPENING_VIEW = 'recruitment_opening_view';

    public const ENLISTMENT_FORM_OPEN = 'enlistment_form_open';

    public const ENLISTMENT_SUBMITTED = 'enlistment_submitted';

    public const COURSE_PAGE_DURATION = 'course_page_duration';

    public const TENANT_PUBLIC_PAGE_DURATION = 'tenant_public_page_duration';

    public const RECRUITMENT_OPENING_PAGE_DURATION = 'recruitment_opening_page_duration';

    public const TENANT_RECRUITMENT_CTA_CLICK = 'tenant_recruitment_cta_click';

    /** @return array<string, list<string>> category => event names */
    public static function serverEventsByCategory(): array
    {
        return [
            AnalyticsEventCategory::TRAINING => [
                self::TRAINING_CATALOG_VIEW,
                self::COURSE_VIEW,
                self::COURSE_SHARE_CODE_USED,
            ],
            AnalyticsEventCategory::TENANT_PUBLIC => [
                self::TENANT_PUBLIC_VIEW,
            ],
            AnalyticsEventCategory::RECRUITMENT => [
                self::RECRUITMENT_OPENING_VIEW,
                self::ENLISTMENT_FORM_OPEN,
                self::ENLISTMENT_SUBMITTED,
            ],
        ];
    }

    /** @return list<string> */
    public static function beaconEventNames(): array
    {
        return [
            self::COURSE_PAGE_DURATION,
            self::TENANT_PUBLIC_PAGE_DURATION,
            self::RECRUITMENT_OPENING_PAGE_DURATION,
            self::TENANT_RECRUITMENT_CTA_CLICK,
        ];
    }
}
