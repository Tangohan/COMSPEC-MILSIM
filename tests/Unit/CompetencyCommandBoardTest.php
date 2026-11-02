<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Training\CompetencyCommandBoard;
use PHPUnit\Framework\TestCase;

/**
 * Vue commandement des compétences : indicateurs calculés uniquement à partir des statuts réels,
 * modules de référence, écarts critiques, renouvellements proches, formations liées par le référentiel.
 */
final class CompetencyCommandBoardTest extends TestCase
{
    private const NOW = 1_800_000_000;

    /** @return list<array<string, mixed>> */
    private static function modules(bool $mandatory = true): array
    {
        return [
            ['id' => 1, 'code' => 'A1', 'name' => 'Doctrine', 'module_type' => 'ALPHA', 'delivery_mode' => 'INITIAL', 'is_mandatory' => $mandatory, 'recurrence_days' => null],
            ['id' => 2, 'code' => 'B1', 'name' => 'Secourisme', 'module_type' => 'BRAVO', 'delivery_mode' => 'CRITIQUE', 'is_mandatory' => false, 'recurrence_days' => 180],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function members(): array
    {
        return [
            10 => ['id' => 10, 'callsign' => 'HAWK', 'display_name' => 'Karim', 'unit_id' => 5, 'grade_id' => 1, 'job_ids' => [7]],
            11 => ['id' => 11, 'callsign' => '', 'display_name' => 'Léa', 'unit_id' => 0, 'grade_id' => 0, 'job_ids' => []],
        ];
    }

    public function testCoverageCriticalGapsAndRenewalsComeFromRealStatuses(): void
    {
        $progress = [
            10 => [1 => ['status' => 'COMPLETED', 'expires_at' => null], 2 => ['status' => 'COMPLETED', 'expires_at' => date('Y-m-d H:i:s', self::NOW + 5 * 86400)]],
            11 => [1 => ['status' => 'EXPIRED', 'expires_at' => null], 2 => ['status' => 'IN_PROGRESS', 'expires_at' => null]],
        ];
        $units = [5 => ['key' => 'u5', 'name' => '1re section', 'confidential' => null]];
        $board = CompetencyCommandBoard::compute(self::modules(), $progress, self::members(), [1 => ['label' => 'SGT', 'order' => 3]], $units, [['ref' => '7', 'title' => 'Infirmier']], [], [10 => [3]], self::NOW);

        self::assertFalse($board['reference_all']);
        self::assertSame(1, $board['kpi']['ref_done']);
        self::assertSame(2, $board['kpi']['ref_total']);
        self::assertSame(50, $board['kpi']['pct']);
        // Léa : module obligatoire expiré + module critique non acquis.
        self::assertSame(2, $board['kpi']['critical']);
        self::assertSame(1, $board['kpi']['members_critical']);
        self::assertSame(1, $board['kpi']['members_progress']);
        self::assertSame(1, $board['kpi']['soon']);

        self::assertSame('Léa', $board['members'][0]['name'], 'Les membres avec des écarts critiques passent en tête.');
        self::assertSame('u0', $board['members'][0]['unit_key']);
        self::assertSame([3], $board['members'][1]['group_ids']);
        self::assertSame('SGT', $board['members'][1]['grade_label']);

        $units = array_column($board['units'], null, 'key');
        self::assertSame(100, $units['u5']['pct']);
        self::assertSame(0, $units['u0']['pct']);
        self::assertSame('Infirmier', $board['jobs'][0]['name']);

        self::assertSame('B1', $board['gaps'][1]['code'], 'Les modules de référence passent avant les facultatifs.');
        self::assertSame(['COMPLETED' => 1, 'IN_PROGRESS' => 1, 'NOT_STARTED' => 0, 'FAILED' => 0, 'EXPIRED' => 0], $board['gaps'][1]['counts']);
    }

    public function testWithoutMandatoryModuleEveryActiveModuleCounts(): void
    {
        $board = CompetencyCommandBoard::compute(self::modules(false), [], self::members(), [], [], [], [], [], self::NOW);

        self::assertTrue($board['reference_all']);
        self::assertSame(4, $board['kpi']['ref_total']);
        self::assertSame(0, $board['kpi']['pct']);
        self::assertSame(2, $board['kpi']['critical'], 'Module critique pas encore acquis pour chacun.');
    }

    public function testNoDataGivesEmptyBoardWithoutError(): void
    {
        $board = CompetencyCommandBoard::compute([], [], [], [], [], [], [], [], self::NOW);

        self::assertSame([], $board['members']);
        self::assertSame(0, $board['kpi']['pct']);
        self::assertSame(0, $board['kpi']['members']);
    }

    public function testCoursesAndQualificationsAreThoseLinkedByTheCompetencyFramework(): void
    {
        $links = CompetencyCommandBoard::moduleLinks([
            'competencies' => [['id' => 41, 'links' => [['type' => 'module', 'ref' => '2'], ['type' => 'academy_course', 'ref' => 'sc1'], ['type' => 'qualification', 'ref' => '5'], ['type' => 'academy_course', 'ref' => 'inconnu']]]],
            'academy_courses' => [['ref' => 'sc1', 'title' => 'Secourisme', 'url' => '/academy/classroom?course=sc1']],
            'qualifications' => [['ref' => '5', 'title' => 'SC1']],
        ]);

        self::assertSame(41, $links[2]['competency_id']);
        self::assertSame([['title' => 'Secourisme', 'url' => '/academy/classroom?course=sc1', 'kind' => 'academy_course']], $links[2]['courses']);
        self::assertSame(['SC1'], $links[2]['qualifications']);
        self::assertArrayNotHasKey(1, $links);
    }

    public function testCommandViewKeepsEveryExistingActionAndUsesTheAcademyCharter(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/training/competency_command.php');
        foreach (['designate_referent', 'ensure_org_sections', 'create_matrix', 'seed_preset_matrices', 'auto_detect', 'assign_matrix', 'unassign_matrix', 'delete_matrix'] as $action) {
            self::assertStringContainsString('value="' . $action . '"', $view, $action);
        }
        self::assertStringContainsString('academy-workspace.css', $view);
        self::assertStringContainsString('competency-command.css', $view);
        self::assertStringContainsString('ConfidentialDisplay::renderPayload', $view);
        self::assertFileExists(dirname(__DIR__, 2) . '/public/assets/js/competency-command.js');
    }
}
