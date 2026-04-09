<?php

declare(strict_types=1);

namespace App\Services\Analytics;

final class AnalyticsSubjectType
{
    public const TRAINING_COURSE = 'training_course';

    public const TENANT = 'tenant';

    public const RECRUITMENT_OPENING = 'recruitment_opening';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::TRAINING_COURSE, self::TENANT, self::RECRUITMENT_OPENING];
    }
}
