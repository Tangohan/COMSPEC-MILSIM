<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PersonnelAssignmentHistoryCoalescer;
use PHPUnit\Framework\TestCase;

final class PersonnelAssignmentHistoryCoalescerTest extends TestCase
{
    public function testSameActiveAssignmentIgnoresEmptyRoleAsMember(): void
    {
        self::assertTrue(PersonnelAssignmentHistoryCoalescer::sameActiveAssignment(
            ['unit_id' => 8, 'role_name' => 'Membre'],
            ['unit_id' => 8, 'role_name' => '']
        ));
        self::assertFalse(PersonnelAssignmentHistoryCoalescer::sameActiveAssignment(
            ['unit_id' => 8, 'role_name' => 'Membre'],
            ['unit_id' => 8, 'role_name' => 'Chef de groupe']
        ));
        self::assertFalse(PersonnelAssignmentHistoryCoalescer::sameActiveAssignment(
            ['unit_id' => 8, 'role_name' => 'Membre'],
            ['unit_id' => 9, 'role_name' => 'Membre']
        ));
    }

    public function testCoalesceMergesSameDaySaveNoiseOnSameUnit(): void
    {
        $rows = [
            $this->row(13, 8, 'Membre', '2026-09-02', null, 'active'),
            $this->row(12, 8, 'Membre', '2026-09-02', '2026-09-02', 'inactive'),
            $this->row(11, 8, 'Membre', '2026-09-02', '2026-09-02', 'inactive'),
            $this->row(10, 8, 'Membre', '2026-09-01', '2026-09-02', 'inactive'),
            $this->row(9, 8, 'En formation', '2026-09-01', '2026-09-01', 'inactive'),
            $this->row(8, 8, 'Membre', '2026-09-01', '2026-09-01', 'inactive'),
            $this->row(7, 8, 'Membre', '2026-08-30', '2026-09-01', 'inactive'),
            $this->row(6, 8, 'Membre', '2026-08-29', '2026-08-30', 'inactive'),
            $this->row(5, 8, 'Membre', '2026-08-29', '2026-08-29', 'inactive'),
            $this->row(4, 8, 'Chef de groupe', '2026-08-29', '2026-08-29', 'inactive'),
            $this->row(3, 8, 'TACP', '2026-08-29', '2026-08-29', 'inactive'),
        ];

        $merged = PersonnelAssignmentHistoryCoalescer::coalesceForDisplay($rows);
        self::assertCount(1, $merged);
        self::assertSame(8, (int) $merged[0]['unit_id']);
        self::assertSame('2026-08-29', (string) $merged[0]['started_at']);
        self::assertTrue($merged[0]['ended_at'] === null || $merged[0]['ended_at'] === '');
        self::assertSame('active', (string) $merged[0]['status']);
        self::assertSame('Membre', (string) $merged[0]['role_name']);
        self::assertGreaterThan(5, count($merged[0]['coalesced_from_ids'] ?? []));
    }

    public function testCoalesceKeepsMultiDayRoleChangeOnSameUnit(): void
    {
        $rows = [
            $this->row(2, 4, 'Chef de groupe', '2026-06-01', null, 'active'),
            $this->row(1, 4, 'Membre', '2026-03-01', '2026-05-31', 'inactive'),
        ];

        $merged = PersonnelAssignmentHistoryCoalescer::coalesceForDisplay($rows);
        self::assertCount(2, $merged);
        $roles = array_map(static fn (array $r): string => (string) $r['role_name'], $merged);
        self::assertContains('Chef de groupe', $roles);
        self::assertContains('Membre', $roles);
    }

    public function testCoalesceKeepsDistinctUnits(): void
    {
        $rows = [
            $this->row(2, 4, 'Membre', '2026-08-01', null, 'active'),
            $this->row(1, 9, 'Membre', '2026-07-01', '2026-07-31', 'inactive'),
        ];

        $merged = PersonnelAssignmentHistoryCoalescer::coalesceForDisplay($rows);
        self::assertCount(2, $merged);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $id, int $unitId, string $role, string $start, ?string $end, string $status): array
    {
        return [
            'id' => $id,
            'unit_id' => $unitId,
            'unit_name' => '24th STS Gold Team SOF TACP',
            'role_name' => $role,
            'started_at' => $start,
            'ended_at' => $end,
            'status' => $status,
            'is_primary' => 1,
        ];
    }
}
