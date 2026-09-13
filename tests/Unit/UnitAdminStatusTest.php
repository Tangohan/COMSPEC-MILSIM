<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\UnitAdminStatus;
use PHPUnit\Framework\TestCase;

final class UnitAdminStatusTest extends TestCase
{
    public function testNormalizeAliasesAndFallback(): void
    {
        self::assertSame(UnitAdminStatus::ACTIVE, UnitAdminStatus::normalize('actif'));
        self::assertSame(UnitAdminStatus::PARTIALLY_ACTIVE, UnitAdminStatus::normalize('partiel'));
        self::assertSame(UnitAdminStatus::INACTIVE, UnitAdminStatus::normalize('inactif'));
        self::assertSame(UnitAdminStatus::FORMING, UnitAdminStatus::normalize('en_formation'));
        self::assertSame(UnitAdminStatus::REORGANIZING, UnitAdminStatus::normalize('reorganisation'));
        self::assertSame(UnitAdminStatus::ARCHIVED, UnitAdminStatus::normalize('archivé'));
        self::assertSame(UnitAdminStatus::ACTIVE, UnitAdminStatus::normalize('unknown'));
        self::assertSame(UnitAdminStatus::ACTIVE, UnitAdminStatus::normalize(null));
    }

    public function testAssignableForbiddenOnlyArchived(): void
    {
        self::assertTrue(UnitAdminStatus::isAssignableForbidden(UnitAdminStatus::ARCHIVED));
        self::assertFalse(UnitAdminStatus::isAssignableForbidden(UnitAdminStatus::INACTIVE));
        self::assertFalse(UnitAdminStatus::isAssignableForbidden(UnitAdminStatus::ACTIVE));
        self::assertTrue(UnitAdminStatus::isAssignableByDefault(UnitAdminStatus::FORMING));
        self::assertFalse(UnitAdminStatus::isAssignableByDefault(UnitAdminStatus::ARCHIVED));
    }

    public function testBadgesAndLabels(): void
    {
        self::assertSame('ARCHIVÉ', UnitAdminStatus::badgeLabel(UnitAdminStatus::ARCHIVED));
        self::assertSame('EN FORMATION', UnitAdminStatus::badgeLabel(UnitAdminStatus::FORMING));
        self::assertSame('Partiellement actif', UnitAdminStatus::label(UnitAdminStatus::PARTIALLY_ACTIVE));
        self::assertSame('admin-status-partially-active', UnitAdminStatus::cssClass(UnitAdminStatus::PARTIALLY_ACTIVE));
        $options = UnitAdminStatus::options();
        self::assertCount(count(UnitAdminStatus::ALL), $options);
        self::assertSame(UnitAdminStatus::ACTIVE, $options[0]['id']);
        self::assertArrayHasKey('badge', $options[0]);
    }

    public function testHiddenByDefault(): void
    {
        self::assertTrue(UnitAdminStatus::isHiddenByDefault(UnitAdminStatus::ARCHIVED));
        self::assertTrue(UnitAdminStatus::isHiddenByDefault(UnitAdminStatus::INACTIVE));
        self::assertFalse(UnitAdminStatus::isHiddenByDefault(UnitAdminStatus::ACTIVE));
    }
}
