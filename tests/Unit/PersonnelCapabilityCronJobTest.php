<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Cron\Jobs\PersonnelCapabilityCronJob;
use App\Services\Personnel\OperationalCapabilityService;
use PHPUnit\Framework\TestCase;

final class PersonnelCapabilityCronJobTest extends TestCase
{
    public function testFreeTextOperatorStatusAlwaysMapsToEnumValue(): void
    {
        $enum = ['AVAILABLE', 'LIMITED', 'ABSENT', 'LOA', 'RESERVE', 'MEDICAL', 'SUSPENDED'];
        foreach (['Actif', 'Disponible', 'En formation', 'Réserve', 'Indisponible', 'actif', '', '  ', 'Truc inconnu', 'EN-MISSION'] as $raw) {
            self::assertContains(OperationalCapabilityService::normalizeAvailability($raw), $enum, 'brut : ' . $raw);
        }
    }

    public function testKnownStatusesKeepTheirMeaning(): void
    {
        self::assertSame('AVAILABLE', OperationalCapabilityService::normalizeAvailability('Actif'));
        self::assertSame('AVAILABLE', OperationalCapabilityService::normalizeAvailability('Disponible'));
        self::assertSame('LIMITED', OperationalCapabilityService::normalizeAvailability('En formation'));
        self::assertSame('ABSENT', OperationalCapabilityService::normalizeAvailability('Indisponible'));
        self::assertSame('SUSPENDED', OperationalCapabilityService::normalizeAvailability('suspended'));
    }

    public function testSummaryDistinguishesNonCurrentFromErrors(): void
    {
        $ok = PersonnelCapabilityCronJob::buildSummary(4, 16, 0, null);
        self::assertStringContainsString('16 qualification(s) non-current (info)', $ok);
        self::assertStringContainsString('0 erreur(s)', $ok);
        self::assertStringNotContainsString('cause', $ok);
    }

    public function testSummaryCarriesFirstErrorCause(): void
    {
        $err = PersonnelCapabilityCronJob::describeError(
            new \RuntimeException("Data truncated for column 'availability'. Incorrect value: 'Jean Dupont'")
        );
        self::assertStringContainsString('RuntimeException', $err);
        self::assertStringNotContainsString('Jean Dupont', $err);
        $summary = PersonnelCapabilityCronJob::buildSummary(4, 16, 12, $err);
        self::assertStringContainsString('12 erreur(s)', $summary);
        self::assertStringContainsString('1re cause : RuntimeException', $summary);
    }
}
