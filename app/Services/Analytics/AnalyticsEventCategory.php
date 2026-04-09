<?php

declare(strict_types=1);

namespace App\Services\Analytics;

final class AnalyticsEventCategory
{
    public const TRAINING = 'training';

    public const TENANT_PUBLIC = 'tenant_public';

    public const RECRUITMENT = 'recruitment';

    public const PORTAL = 'portal';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::TRAINING, self::TENANT_PUBLIC, self::RECRUITMENT, self::PORTAL];
    }
}
