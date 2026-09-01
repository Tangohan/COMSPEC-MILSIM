<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PersonnelProfileGapScanService;
use PHPUnit\Framework\TestCase;

final class PersonnelProfileGapScanServiceTest extends TestCase
{
    public function testBlankLabelTreatsPlaceholdersAsEmpty(): void
    {
        self::assertTrue(PersonnelProfileGapScanService::isBlankLabel(''));
        self::assertTrue(PersonnelProfileGapScanService::isBlankLabel(' — '));
        self::assertTrue(PersonnelProfileGapScanService::isBlankLabel('Non indiqué'));
        self::assertTrue(PersonnelProfileGapScanService::isBlankLabel('— Non renseigné —'));
        self::assertFalse(PersonnelProfileGapScanService::isBlankLabel('Chef de groupe'));
        self::assertFalse(PersonnelProfileGapScanService::isBlankLabel('SGT'));
    }

    public function testClassifyRowFlagsTheFiveProfileGaps(): void
    {
        $issues = PersonnelProfileGapScanService::classifyRow([
            'job_role_name' => '',
            'primary_role' => 'Non renseigné',
            'grade_id' => 0,
            'rank_display' => '',
            'rank_display_override' => '',
            'grade_short' => '',
            'grade_long' => '',
            'role_id' => null,
            'has_tenant_role' => 0,
            'community_role_name' => '',
            'character_portrait_path' => '',
            'has_active_absence' => 0,
            'readiness_score' => 0,
        ]);

        self::assertTrue($issues['missing']['function']);
        self::assertTrue($issues['missing']['rank']);
        self::assertTrue($issues['missing']['role']);
        self::assertTrue($issues['missing']['operator_image']);
        self::assertTrue($issues['missing']['absence']);
        self::assertSame(5, $issues['issue_count']);
        self::assertSame(['function', 'rank', 'role', 'operator_image', 'absence'], $issues['issue_keys']);
    }

    public function testClassifyRowAcceptsFilledDossier(): void
    {
        $issues = PersonnelProfileGapScanService::classifyRow([
            'job_role_name' => 'Fusilier',
            'primary_role' => '',
            'grade_id' => 12,
            'rank_display' => '',
            'rank_display_override' => '',
            'grade_short' => 'SGT',
            'grade_long' => 'Sergent',
            'role_id' => 4,
            'has_tenant_role' => 0,
            'community_role_name' => 'Opérateur',
            'character_portrait_path' => 'uploads/portraits/op.jpg',
            'has_active_absence' => 1,
            'readiness_score' => 0,
        ]);

        self::assertSame(0, $issues['issue_count']);
        self::assertSame('Fusilier', $issues['function_label']);
        self::assertSame('SGT', $issues['rank_label']);
        self::assertSame('Opérateur', $issues['role_label']);
    }

    public function testCustomRankDisplayCountsAsGrade(): void
    {
        $issues = PersonnelProfileGapScanService::classifyRow([
            'job_role_name' => 'Radio',
            'grade_id' => 0,
            'rank_display' => 'Chef de section',
            'role_id' => 1,
            'has_tenant_role' => 0,
            'character_portrait_path' => 'portrait.png',
            'has_active_absence' => 0,
            'readiness_score' => 80,
        ]);

        self::assertFalse($issues['missing']['rank']);
        self::assertFalse($issues['missing']['absence']);
        self::assertSame(0, $issues['issue_count']);
    }

    public function testTenantRoleFillsCommunityRoleGap(): void
    {
        $issues = PersonnelProfileGapScanService::classifyRow([
            'job_role_name' => 'Médecin',
            'grade_id' => 3,
            'grade_short' => 'CPL',
            'role_id' => 0,
            'has_tenant_role' => 1,
            'character_portrait_path' => 'p.jpg',
            'has_active_absence' => 0,
            'readiness_score' => 10,
        ]);

        self::assertFalse($issues['missing']['role']);
        self::assertSame(0, $issues['issue_count']);
    }
}
