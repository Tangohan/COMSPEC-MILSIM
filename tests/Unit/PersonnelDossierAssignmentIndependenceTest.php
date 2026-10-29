<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\PersonnelController;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class PersonnelDossierAssignmentIndependenceTest extends TestCase
{
    private function save(PersonnelAssignmentRepository $assignments, array $rows, ?int $primaryUnitId = 100): void
    {
        $controller = (new ReflectionClass(PersonnelController::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($controller, 'personnelAssignmentRepository'))->setValue($controller, $assignments);
        // A job edit may exist in the same dossier, but assignment persistence must never consult it.
        $jobs = $this->createMock(PersonnelJobRoleRepository::class);
        $jobs->expects(self::never())->method('listPivotAssignmentsForUsers');
        (new ReflectionProperty($controller, 'personnelJobRoleRepository'))->setValue($controller, $jobs);
        (new ReflectionMethod($controller, 'saveIndependentUnitAssignments'))->invoke($controller, 14, 7, $rows, $primaryUnitId, 'Mutation');
    }

    public function testExplicitPrimaryAndSecondaryOrbatLabelsRemainUnchanged(): void
    {
        $rows = [
            ['unit_id' => 100, 'role_name' => 'Chef de section', 'is_primary' => true],
            ['unit_id' => 101, 'role_name' => 'Adjoint', 'is_primary' => false],
        ];
        $assignments = $this->createMock(PersonnelAssignmentRepository::class);
        $assignments->expects(self::never())->method('getPrimaryAssignment');
        $assignments->expects(self::once())->method('replaceActiveAssignmentsFromDossier')->with(7, $rows);
        $assignments->expects(self::never())->method('syncPrimaryAssignmentFromDossier');
        $this->save($assignments, $rows);
    }

    public function testBlankPrimaryKeepsCurrentOrbatLabelAndBlankSecondaryDefaultsToMember(): void
    {
        $assignments = $this->createMock(PersonnelAssignmentRepository::class);
        $assignments->expects(self::once())->method('getPrimaryAssignment')->with(7, 14)->willReturn(['role_name' => 'Chef de section']);
        $assignments->expects(self::once())->method('replaceActiveAssignmentsFromDossier')->with(7, [
            ['unit_id' => 100, 'role_name' => 'Chef de section', 'is_primary' => true],
            ['unit_id' => 101, 'role_name' => 'Membre', 'is_primary' => false],
        ]);
        $this->save($assignments, [
            ['unit_id' => 100, 'role_name' => '', 'is_primary' => true],
            ['unit_id' => 101, 'role_name' => '', 'is_primary' => false],
        ]);
    }

    public function testLegacyUnitFallbackUsesCurrentAssignmentInsteadOfDossierJob(): void
    {
        $assignments = $this->createMock(PersonnelAssignmentRepository::class);
        $assignments->expects(self::once())->method('getPrimaryAssignment')->with(7, 14)->willReturn(['role_name' => 'Chef de section']);
        $assignments->expects(self::once())->method('syncPrimaryAssignmentFromDossier')->with(7, 100, 'Chef de section', 'Mutation');
        $assignments->expects(self::never())->method('replaceActiveAssignmentsFromDossier');
        $this->save($assignments, []);
    }

    public function testNewAssignmentWithoutCurrentPostUsesMemberAndExplicitRemovalStillUsesNullUnit(): void
    {
        $assignments = $this->createMock(PersonnelAssignmentRepository::class);
        $assignments->expects(self::once())->method('getPrimaryAssignment')->with(7, 14)->willReturn(null);
        $assignments->expects(self::once())->method('syncPrimaryAssignmentFromDossier')->with(7, null, 'Membre', 'Mutation');
        $this->save($assignments, [], null);
    }
}
