<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\RoleplayDeadlinePolicy;
use PHPUnit\Framework\TestCase;

final class RoleplayDeadlinePolicyTest extends TestCase
{
    public function testNormalizeBloodTypeAcceptsArmaAndAceAliases(): void
    {
        self::assertSame('O+', RoleplayDeadlinePolicy::normalizeBloodType('O POS'));
        self::assertSame('O-', RoleplayDeadlinePolicy::normalizeBloodType('o neg'));
        self::assertSame('A+', RoleplayDeadlinePolicy::normalizeBloodType('A+'));
        self::assertSame('AB-', RoleplayDeadlinePolicy::normalizeBloodType('6'));
        self::assertSame('Inconnu', RoleplayDeadlinePolicy::normalizeBloodType('unknown'));
        self::assertSame('', RoleplayDeadlinePolicy::normalizeBloodType(''));
    }

    public function testBloodTypeChangedDetectsArmaUpdate(): void
    {
        self::assertTrue(RoleplayDeadlinePolicy::bloodTypeChanged('A+', 'O+'));
        self::assertFalse(RoleplayDeadlinePolicy::bloodTypeChanged('O POS', 'O+'));
        self::assertTrue(RoleplayDeadlinePolicy::bloodTypeChanged('', 'O+'));
        self::assertFalse(RoleplayDeadlinePolicy::bloodTypeChanged('O+', 'Inconnu'));
    }

    public function testBloodTypeNeedsConfirmationWhenSourcesDiverge(): void
    {
        self::assertTrue(RoleplayDeadlinePolicy::bloodTypeNeedsConfirmation(null, null, null));
        self::assertTrue(RoleplayDeadlinePolicy::bloodTypeNeedsConfirmation('A+', 'A+', 'O+'));
        self::assertTrue(RoleplayDeadlinePolicy::bloodTypeNeedsConfirmation('A+', '', 'A+'));
        self::assertFalse(RoleplayDeadlinePolicy::bloodTypeNeedsConfirmation('O+', 'O+', 'O POS'));
    }

    public function testBloodTypeMismatchOnlyFlagsKnownContradictions(): void
    {
        self::assertTrue(RoleplayDeadlinePolicy::bloodTypeMismatch('A+', 'O+'));
        self::assertFalse(RoleplayDeadlinePolicy::bloodTypeMismatch('O+', 'O POS'));
        self::assertFalse(RoleplayDeadlinePolicy::bloodTypeMismatch('', 'O+'));
        self::assertFalse(RoleplayDeadlinePolicy::bloodTypeMismatch('Inconnu', 'O+'));
    }

    public function testSuggestedBloodTypePrefersArmaThenDossier(): void
    {
        self::assertSame('B+', RoleplayDeadlinePolicy::suggestedBloodType('A+', 'A+', 'B+'));
        self::assertSame('A+', RoleplayDeadlinePolicy::suggestedBloodType('A+', 'O+', ''));
        self::assertSame('O+', RoleplayDeadlinePolicy::suggestedBloodType('', 'O+', ''));
    }

    public function testRotationKindsNormalizeFrenchAliases(): void
    {
        self::assertSame('advancement', RoleplayDeadlinePolicy::normalizeRotationKind('avancement'));
        self::assertSame('training', RoleplayDeadlinePolicy::normalizeRotationKind('formation'));
        self::assertSame('evaluation', RoleplayDeadlinePolicy::normalizeRotationKind('notation'));
        self::assertSame('service', RoleplayDeadlinePolicy::normalizeRotationKind(''));
        self::assertSame('Avancement', RoleplayDeadlinePolicy::rotationKindLabel('advancement'));
    }

    public function testInterviewIsRequiredBeforeEachRotation(): void
    {
        self::assertFalse(RoleplayDeadlinePolicy::canProceedWithRotation(null, null));
        self::assertTrue(RoleplayDeadlinePolicy::canProceedWithRotation('2026-08-20 10:00:00', null));
        self::assertTrue(RoleplayDeadlinePolicy::canProceedWithRotation('2026-08-22 10:00:00', '2026-08-21 10:00:00'));
        self::assertFalse(RoleplayDeadlinePolicy::canProceedWithRotation('2026-08-20 10:00:00', '2026-08-21 10:00:00'));
        self::assertFalse(RoleplayDeadlinePolicy::canProceedWithRotation('2026-08-21 10:00:00', '2026-08-21 10:00:00'));
    }
}
