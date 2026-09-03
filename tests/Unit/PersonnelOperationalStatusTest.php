<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PersonnelOperationalStatus;
use PHPUnit\Framework\TestCase;

final class PersonnelOperationalStatusTest extends TestCase
{
    public function testReadyStatusIsComputedFromObjectiveChecks(): void
    {
        $status = PersonnelOperationalStatus::assess([
            'unit' => true, 'role' => true, 'clearance' => true,
            'qualification' => true, 'available' => true,
        ], true, true);

        self::assertSame(100, $status['score']);
        self::assertSame('Prêt', $status['label']);
    }

    public function testAbsenceOverridesOtherwiseCompleteStatus(): void
    {
        $status = PersonnelOperationalStatus::assess([
            'unit' => true, 'role' => true, 'clearance' => true,
            'qualification' => true, 'available' => false,
        ], true, true);

        self::assertSame('Non disponible', $status['label']);
        self::assertStringContainsString('absence', $status['summary']);
    }
}
