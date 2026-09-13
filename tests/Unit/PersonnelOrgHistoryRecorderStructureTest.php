<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PersonnelOrgHistoryRepository;
use App\Repositories\RoleRepository;
use App\Services\Personnel\PersonnelOrgHistoryRecorder;
use PHPUnit\Framework\TestCase;

final class PersonnelOrgHistoryRecorderStructureTest extends TestCase
{
    public function testRecordStructureChangesWritesAffectationLineLikeEmail(): void
    {
        $history = $this->createMock(PersonnelOrgHistoryRepository::class);
        $history->method('schemaReady')->willReturn(true);
        $history->expects($this->once())
            ->method('append')
            ->with(
                12,
                34,
                56,
                'Affectation : Non renseigné → 24th STS Gold Team SOF TACP'
            );

        $roles = $this->createMock(RoleRepository::class);
        $recorder = new PersonnelOrgHistoryRecorder($history, $roles);
        $recorder->recordStructureChanges(12, 34, 56, [
            [
                'type' => 'unit',
                'label' => 'Affectation',
                'from' => '',
                'to' => '24th STS Gold Team SOF TACP',
            ],
        ]);
    }

    public function testRecordStructureChangesCombinesGradeAndUnit(): void
    {
        $history = $this->createMock(PersonnelOrgHistoryRepository::class);
        $history->method('schemaReady')->willReturn(true);
        $history->expects($this->once())
            ->method('append')
            ->with(
                1,
                2,
                null,
                'Grade : Sgt → Adj · Affectation : Alpha → Bravo'
            );

        $roles = $this->createMock(RoleRepository::class);
        $recorder = new PersonnelOrgHistoryRecorder($history, $roles);
        $recorder->recordStructureChanges(1, 2, null, [
            ['type' => 'grade', 'label' => 'Grade', 'from' => 'Sgt', 'to' => 'Adj'],
            ['type' => 'unit', 'label' => 'Affectation', 'from' => 'Alpha', 'to' => 'Bravo'],
        ]);
    }

    public function testRecordStructureChangesNoopWhenEmpty(): void
    {
        $history = $this->createMock(PersonnelOrgHistoryRepository::class);
        $history->method('schemaReady')->willReturn(true);
        $history->expects($this->never())->method('append');

        $roles = $this->createMock(RoleRepository::class);
        $recorder = new PersonnelOrgHistoryRecorder($history, $roles);
        $recorder->recordStructureChanges(1, 2, 3, []);
    }
}
