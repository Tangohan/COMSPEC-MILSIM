<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Aligné sur les chaînes seed dans {@see run_community_platform_migration}. */
final class PlanFeaturesSchemaTest extends TestCase
{
    public function testProPlusFeaturesJsonAllowsIntegrationsAndUnlimitedCourses(): void
    {
        $raw = '{"forum":true,"documents":true,"training":true,"atak":true,"analytics":true,"events":true,'
            . '"max_members":10000,"max_training_courses":0,"advanced_integrations":true,"community_create":true}';
        $f = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($f['advanced_integrations']);
        self::assertSame(0, (int) $f['max_training_courses']);
    }

    public function testStandardFeaturesJsonCapsCoursesAndDisallowsIntegrations(): void
    {
        $raw = '{"forum":true,"documents":true,"training":true,"atak":true,"events":true,"max_members":200,'
            . '"max_training_courses":25,"advanced_integrations":false,"community_create":true}';
        $f = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(25, (int) $f['max_training_courses']);
        self::assertFalse($f['advanced_integrations']);
    }
}
