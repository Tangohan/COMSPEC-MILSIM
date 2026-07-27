<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PersonnelQualificationRepository;
use PHPUnit\Framework\TestCase;

/**
 * Statut dérivé de l'échéance d'une qualification (chaînon Formation → Qualification).
 */
final class PersonnelQualificationStatusTest extends TestCase
{
    public function testQualificationSansEcheanceResteValide(): void
    {
        self::assertSame('valid', PersonnelQualificationRepository::statusForExpiry(null));
        self::assertSame('valid', PersonnelQualificationRepository::statusForExpiry(''));
        self::assertSame('valid', PersonnelQualificationRepository::statusForExpiry('   '));
    }

    public function testEcheancePasseeDonneExpired(): void
    {
        $hier = date('Y-m-d', strtotime('-1 day'));

        self::assertSame('expired', PersonnelQualificationRepository::statusForExpiry($hier));
    }

    public function testEcheanceDansLaFenetreDonneExpiring(): void
    {
        $dansDixJours = date('Y-m-d H:i:s', strtotime('+10 days'));

        self::assertSame('expiring', PersonnelQualificationRepository::statusForExpiry($dansDixJours));
    }

    public function testEcheanceLointaineResteValide(): void
    {
        $dansUnAn = date('Y-m-d H:i:s', strtotime('+365 days'));

        self::assertSame('valid', PersonnelQualificationRepository::statusForExpiry($dansUnAn));
    }

    public function testLaFenetreDAlerteEstParametrable(): void
    {
        $dansQuaranteJours = date('Y-m-d H:i:s', strtotime('+40 days'));

        self::assertSame('valid', PersonnelQualificationRepository::statusForExpiry($dansQuaranteJours, 30));
        self::assertSame('expiring', PersonnelQualificationRepository::statusForExpiry($dansQuaranteJours, 60));
    }

    public function testDateIllisibleNeBloquePasLEmission(): void
    {
        self::assertSame('valid', PersonnelQualificationRepository::statusForExpiry('pas-une-date'));
    }
}
