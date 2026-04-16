<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PersonnelAssignmentRepository;
use PHPUnit\Framework\TestCase;

final class PersonnelAssignmentDurationTest extends TestCase
{
    public function testInclusiveCalendarDaysBetweenSameDay(): void
    {
        $this->assertSame(1, PersonnelAssignmentRepository::inclusiveCalendarDaysBetween('2024-06-10', '2024-06-10', false));
    }

    public function testInclusiveCalendarDaysBetweenRange(): void
    {
        $this->assertSame(10, PersonnelAssignmentRepository::inclusiveCalendarDaysBetween('2024-01-01', '2024-01-10', false));
    }

    public function testInclusiveCalendarDaysBetweenNullStart(): void
    {
        $this->assertSame(0, PersonnelAssignmentRepository::inclusiveCalendarDaysBetween(null, '2024-01-10', false));
    }

    public function testInclusiveCalendarDaysBetweenEndBeforeStart(): void
    {
        $this->assertSame(0, PersonnelAssignmentRepository::inclusiveCalendarDaysBetween('2024-02-10', '2024-02-01', false));
    }

    public function testFormatDurationFrench(): void
    {
        $this->assertSame('—', PersonnelAssignmentRepository::formatDurationFrench(0));
        $this->assertSame('1 jour', PersonnelAssignmentRepository::formatDurationFrench(1));
        $this->assertSame('12 jours', PersonnelAssignmentRepository::formatDurationFrench(12));
    }
}
