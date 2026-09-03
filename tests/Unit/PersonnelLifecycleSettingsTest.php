<?php

declare(strict_types=1);

use App\Services\Personnel\PersonnelLifecycleSettings;
use PHPUnit\Framework\TestCase;

final class PersonnelLifecycleSettingsTest extends TestCase
{
    public function testDefaultsAndTenantValuesAreResolved(): void
    {
        self::assertSame(['training_days' => 14, 'active_service_days' => 0], PersonnelLifecycleSettings::resolve([]));
        self::assertSame(
            ['training_days' => 30, 'active_service_days' => 7],
            PersonnelLifecycleSettings::resolve(['personnel_lifecycle' => ['training_days' => 30, 'active_service_days' => 7]])
        );
    }

    public function testBackOfficeValuesAreBounded(): void
    {
        self::assertSame(
            ['training_days' => 0, 'active_service_days' => 3650],
            PersonnelLifecycleSettings::fromInput('-2', '9999')
        );
    }
}
