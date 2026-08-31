<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserProfileDisplaySettingsRepository;
use App\Services\Auth\LoginWelcomeProfileService;
use PHPUnit\Framework\TestCase;

final class LoginWelcomeProfileServiceTest extends TestCase
{
    public function testBuildUsesDisplayNameGradeAndAccountFacts(): void
    {
        $profiles = $this->createMock(PersonnelProfileRepository::class);
        $profiles->method('getByUserId')->willReturn([
            'primary_role' => 'Cellule C2',
            'enlistment_date' => '2020-01-01',
            'primary_unit_id' => 7,
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

        $jobRoles = $this->createMock(PersonnelJobRoleRepository::class);
        $jobRoles->method('findRoleById')->willReturn(null);

        $units = $this->createMock(UnitRepository::class);
        $units->method('findById')->willReturn(['name' => '24th STS Gold Team SOF TACP']);

        $svc = new LoginWelcomeProfileService($profiles, $assignments, $grades, $display, $jobRoles, $units);
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
        $this->assertCount(3, $profile['account_facts']);
        $this->assertSame('Ancienneté', $profile['account_facts'][0]['label']);
        $this->assertStringContainsString('an', $profile['account_facts'][0]['value']);
        $this->assertSame('Rôle / Fonction', $profile['account_facts'][1]['label']);
        $this->assertSame('Cellule C2', $profile['account_facts'][1]['value']);
        $this->assertSame('Affectation', $profile['account_facts'][2]['label']);
        $this->assertSame('24th STS Gold Team SOF TACP', $profile['account_facts'][2]['value']);
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
        $jobRoles = $this->createMock(PersonnelJobRoleRepository::class);
        $units = $this->createMock(UnitRepository::class);

        $svc = new LoginWelcomeProfileService($profiles, $assignments, $grades, $display, $jobRoles, $units);
        $profile = $svc->build([
            'id' => 2,
            'tenant_id' => 1,
            'display_name' => '',
            'callsign' => '',
            'email' => 'operateur@unit.mil',
        ]);

        $this->assertSame('operateur', $profile['display_name']);
        $this->assertSame('Non renseignée', $profile['account_facts'][0]['value']);
        $this->assertSame('Non renseignée', $profile['account_facts'][1]['value']);
        $this->assertSame('Non renseignée', $profile['account_facts'][2]['value']);
    }
}
