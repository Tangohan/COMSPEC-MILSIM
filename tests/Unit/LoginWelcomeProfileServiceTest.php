<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UserProfileDisplaySettingsRepository;
use App\Services\Auth\LoginWelcomeProfileService;
use PHPUnit\Framework\TestCase;

final class LoginWelcomeProfileServiceTest extends TestCase
{
    public function testBuildUsesDisplayNameGradeUnitAndChanges(): void
    {
        $profiles = $this->createMock(PersonnelProfileRepository::class);
        $profiles->method('getByUserId')->willReturn([
            'primary_role' => 'Cellule C2',
            'character_portrait_path' => '',
        ]);

        $assignments = $this->createMock(PersonnelAssignmentRepository::class);
        $assignments->method('getPrimaryAssignment')->willReturn([
            'unit_name' => 'S.O.A.R.',
        ]);

        $grades = $this->createMock(GradeRepository::class);
        $grades->method('findById')->willReturn([
            'label_long' => 'Maréchal des logis-chef',
            'label_short' => 'MDC',
        ]);

        $display = $this->createMock(UserProfileDisplaySettingsRepository::class);
        $display->method('getByUserId')->willReturn(['site_photo_priority' => 'operator']);

        $svc = new LoginWelcomeProfileService($profiles, $assignments, $grades, $display);
        $profile = $svc->build([
            'id' => 10,
            'tenant_id' => 3,
            'display_name' => 'Tanguy TETARD',
            'email' => 'tanguy@example.com',
            'callsign' => 'EAGLE',
            'grade_id' => 42,
            'avatar_url' => '',
        ]);

        $this->assertSame('Tanguy TETARD', $profile['display_name']);
        $this->assertSame('Maréchal des logis-chef', $profile['grade_label']);
        $this->assertSame('S.O.A.R.', $profile['unit_label']);
        $this->assertIsArray($profile['changes']);
        $this->assertLessThanOrEqual(3, count($profile['changes']));
    }

    public function testDisplayNameFallsBackToEmailLocalPart(): void
    {
        $profiles = $this->createMock(PersonnelProfileRepository::class);
        $profiles->method('getByUserId')->willReturn(null);
        $assignments = $this->createMock(PersonnelAssignmentRepository::class);
        $assignments->method('getPrimaryAssignment')->willReturn(null);
        $grades = $this->createMock(GradeRepository::class);
        $grades->method('findById')->willReturn(null);
        $display = $this->createMock(UserProfileDisplaySettingsRepository::class);
        $display->method('getByUserId')->willReturn(null);

        $svc = new LoginWelcomeProfileService($profiles, $assignments, $grades, $display);
        $profile = $svc->build([
            'id' => 2,
            'tenant_id' => 1,
            'display_name' => '',
            'callsign' => '',
            'email' => 'operateur@unit.mil',
        ]);

        $this->assertSame('operateur', $profile['display_name']);
    }
}
