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

    public function testClassifyRowFlagsTheFourProfileGaps(): void
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
        self::assertSame(4, $issues['issue_count']);
        self::assertSame(['function', 'rank', 'role', 'operator_image'], $issues['issue_keys']);
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

    /**
     * Non-régression : l'absence est déclarative. Un membre sans absence déclarée et sans score de
     * disponibilité (ou avec) a un dossier complet dès que fonction, grade, rôle et image sont là.
     */
    public function testAbsenceNeverMakesADossierIncomplete(): void
    {
        $complete = [
            'job_role_name' => 'Radio',
            'grade_id' => 3,
            'grade_short' => 'CPL',
            'role_id' => 2,
            'has_tenant_role' => 0,
            'character_portrait_path' => 'p.jpg',
        ];
        foreach ([[0, 0], [0, 80], [1, 0], [1, 80]] as [$absence, $readiness]) {
            $issues = PersonnelProfileGapScanService::classifyRow($complete + [
                'has_active_absence' => $absence,
                'readiness_score' => $readiness,
            ]);
            self::assertSame(0, $issues['issue_count'], 'absence=' . $absence . ' disponibilité=' . $readiness);
            self::assertSame([], $issues['issue_keys']);
            self::assertArrayNotHasKey('absence', $issues['missing']);
        }
        self::assertNotContains('absence', PersonnelProfileGapScanService::ISSUE_KEYS);
        self::assertArrayNotHasKey('absence', PersonnelProfileGapScanService::ISSUE_LABELS);
    }

    public function testDossierCompletenessIgnoresAvailabilityScore(): void
    {
        $user = ['display_name' => 'Membre', 'callsign' => 'ALPHA', 'email' => 'a@example.test'];
        $rich = [
            'matricule_internal' => 'M-1',
            'grade_short' => 'CPL',
            'job_role_display' => 'Radio',
            'enlistment_date_resolved' => '2024-01-01',
        ];
        $without = \App\Support\PersonnelDossierCompleteness::evaluate($user, $rich + ['readiness_score' => 0], true);
        $with = \App\Support\PersonnelDossierCompleteness::evaluate($user, $rich + ['readiness_score' => 90], true);
        self::assertSame(100, $without['score']);
        self::assertSame($with['score'], $without['score']);
        self::assertSame([], $without['missing']);
    }
}
