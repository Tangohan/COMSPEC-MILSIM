<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Organization\OrgVisibilityService;
use App\Support\OrgVisibilityCapabilities;
use App\Support\UnitAdminStatus;
use App\Support\VisibilityLevel;
use PHPUnit\Framework\TestCase;

final class OrgVisibilityServiceTest extends TestCase
{
    private function caps(array $overrides = []): OrgVisibilityCapabilities
    {
        $caps = new OrgVisibilityCapabilities();
        foreach ($overrides as $k => $v) {
            $caps->{$k} = $v;
        }

        return $caps;
    }

    public function testFilterPersonnelRowHidesCompletely(): void
    {
        $member = [
            'user_id' => 12,
            'visibility_level' => VisibilityLevel::HIDDEN,
            'display_name' => 'Alpha',
        ];
        $out = OrgVisibilityService::filterPersonnelRow($member, $this->caps(), VisibilityLevel::NORMAL);
        self::assertNull($out);

        $visible = OrgVisibilityService::filterPersonnelRow(
            $member,
            $this->caps(['viewHiddenPersonnel' => true]),
            VisibilityLevel::NORMAL
        );
        self::assertNotNull($visible);
        self::assertSame(12, $visible['user_id']);
    }

    public function testFilterPersonnelRowAnonymizesIdentity(): void
    {
        $member = [
            'user_id' => 7,
            'visibility_level' => VisibilityLevel::ANONYMIZED,
            'anonymized_label' => 'Poste occupé',
            'display_name' => 'Jean Dupont',
            'callsign' => 'Wolf',
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'photo' => 'x.jpg',
        ];
        $out = OrgVisibilityService::filterPersonnelRow($member, $this->caps(), VisibilityLevel::NORMAL);
        self::assertNotNull($out);
        self::assertTrue($out['anonymized']);
        self::assertSame('Poste occupé', $out['display_name']);
        self::assertSame(0, $out['user_id']);
        self::assertNull($out['callsign']);
        self::assertNull($out['photo']);
        self::assertArrayNotHasKey('first_name', $out);
    }

    public function testFilterPersonnelRowRedactsAssignmentWhenRestricted(): void
    {
        $member = [
            'user_id' => 3,
            'visibility_level' => VisibilityLevel::NORMAL,
            'assignment_visibility' => VisibilityLevel::RESTRICTED,
            'unit_name' => 'Section Alfa',
            'unit_path' => 'HQ / Alfa',
        ];
        $out = OrgVisibilityService::filterPersonnelRow($member, $this->caps(), VisibilityLevel::NORMAL);
        self::assertNotNull($out);
        self::assertTrue($out['assignment_redacted']);
        self::assertSame('Restreinte', $out['unit_name']);
        self::assertNull($out['unit_path']);
    }

    public function testFilterPersonnelRowRedactsWhenUnitHidden(): void
    {
        $member = [
            'user_id' => 9,
            'visibility_level' => VisibilityLevel::NORMAL,
            'assignment_visibility' => VisibilityLevel::NORMAL,
            'unit_name' => 'Cellule noire',
        ];
        $out = OrgVisibilityService::filterPersonnelRow($member, $this->caps(), VisibilityLevel::HIDDEN);
        self::assertNotNull($out);
        self::assertTrue($out['assignment_redacted']);
        self::assertSame('Restreinte', $out['unit_name']);
    }

    public function testDetectStatusInconsistencies(): void
    {
        $warnings = OrgVisibilityService::detectStatusInconsistencies(
            ['admin_status' => UnitAdminStatus::ACTIVE],
            ['admin_status' => UnitAdminStatus::ARCHIVED]
        );
        self::assertNotEmpty($warnings);

        $inactiveParent = OrgVisibilityService::detectStatusInconsistencies(
            ['admin_status' => UnitAdminStatus::ACTIVE],
            ['admin_status' => UnitAdminStatus::INACTIVE]
        );
        self::assertNotEmpty($inactiveParent);

        $ok = OrgVisibilityService::detectStatusInconsistencies(
            ['admin_status' => UnitAdminStatus::ACTIVE],
            ['admin_status' => UnitAdminStatus::ACTIVE]
        );
        self::assertSame([], $ok);
    }

    public function testAnonymizeUnitNode(): void
    {
        $node = OrgVisibilityService::anonymizeUnitNode([
            'label' => '1re Section',
            'leader' => 'Cdt X',
            'mission' => 'Secret',
            'members' => [
                ['user_id' => 1, 'label' => 'Alice', 'anonymized_label' => 'Opérateur'],
            ],
            'strength' => 4,
            'strengthDisplayMode' => 'visible_only',
        ]);
        self::assertSame('Unité restreinte', $node['label']);
        self::assertTrue($node['isAnonymizedUnit']);
        self::assertSame('Opérateur', $node['members'][0]['label']);
        self::assertTrue($node['members'][0]['anonymized']);
        self::assertSame(0, $node['members'][0]['user_id']);
    }

    public function testShouldShowUnitByAdminStatus(): void
    {
        self::assertTrue(OrgVisibilityService::shouldShowUnitByAdminStatus(UnitAdminStatus::ACTIVE));
        self::assertFalse(OrgVisibilityService::shouldShowUnitByAdminStatus(UnitAdminStatus::ARCHIVED));
        self::assertTrue(OrgVisibilityService::shouldShowUnitByAdminStatus(
            UnitAdminStatus::ARCHIVED,
            [UnitAdminStatus::ARCHIVED]
        ));
    }
}
