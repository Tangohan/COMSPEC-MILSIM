<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\CompetencyModuleRepository;
use App\Services\Training\CompetencyModuleService;
use PHPUnit\Framework\TestCase;

final class CompetencyModuleServiceTest extends TestCase
{
    public function testInputIsNormalised(): void
    {
        [$data, $error] = CompetencyModuleService::normalizeInput([
            'name' => '  Règles   d’engagement ',
            'code' => ' roe 01 ',
            'module_type' => 'alpha',
            'delivery_mode' => 'initial',
            'duration_min' => '90',
            'recurrence_days' => '365',
            'is_active' => '1',
            'prereq_ids' => ['4', '4', '99', '7'],
        ], [4, 7], 7);

        self::assertSame(null, $error);
        self::assertSame('Règles d’engagement', $data['name']);
        self::assertSame('ROE-01', $data['code']);
        self::assertSame('ALPHA', $data['module_type']);
        self::assertSame(90, $data['duration_min']);
        self::assertSame(365, $data['recurrence_days']);
        self::assertTrue($data['is_active']);
        self::assertFalse($data['is_mandatory']);
        // 99 n'appartient pas à l'organisation, 7 est le module lui-même.
        self::assertSame([4], $data['prereq_ids']);
    }

    public function testInvalidInputIsRejected(): void
    {
        self::assertSame('Donnez un nom au module.', CompetencyModuleService::normalizeInput(['code' => 'X', 'module_type' => 'ALPHA'], [], null)[1]);
        self::assertSame('Choisissez une phase : ALPHA, BRAVO, CHARLIE ou DELTA.', CompetencyModuleService::normalizeInput(['name' => 'A', 'code' => 'X', 'module_type' => 'ECHO'], [], null)[1]);
        self::assertSame('Renouvellement invalide : entre 1 et 3650 jours, ou vide.', CompetencyModuleService::normalizeInput(['name' => 'A', 'code' => 'X', 'module_type' => 'BRAVO', 'recurrence_days' => '0'], [], null)[1]);
        self::assertSame('Donnez un code court au module (ex. TIR-01).', CompetencyModuleService::normalizeInput(['name' => 'A', 'code' => '!!!', 'module_type' => 'BRAVO'], [], null)[1]);
    }

    public function testPrerequisiteCyclesAreDetected(): void
    {
        // 2 dépend de 1, 3 dépend de 2.
        $graph = [1 => [], 2 => [1], 3 => [2]];
        self::assertTrue(CompetencyModuleService::createsCycle($graph, 1, [3]));
        self::assertTrue(CompetencyModuleService::createsCycle($graph, 2, [2]));
        self::assertFalse(CompetencyModuleService::createsCycle($graph, 3, [1]));
        self::assertFalse(CompetencyModuleService::createsCycle($graph, 1, []));
    }

    public function testExpiryFollowsRenewal(): void
    {
        $now = mktime(12, 0, 0, 10, 2, 2026);
        self::assertSame('2027-10-02 12:00:00', CompetencyModuleService::expiryFor('COMPLETED', 365, $now));
        self::assertSame(null, CompetencyModuleService::expiryFor('COMPLETED', null, $now));
        self::assertSame(null, CompetencyModuleService::expiryFor('IN_PROGRESS', 365, $now));
    }

    public function testPastDeadlineMeansRenewal(): void
    {
        $now = mktime(12, 0, 0, 10, 2, 2026);
        self::assertSame('EXPIRED', CompetencyModuleRepository::effectiveStatus('COMPLETED', '2026-09-01 00:00:00', $now));
        self::assertSame('COMPLETED', CompetencyModuleRepository::effectiveStatus('COMPLETED', '2027-01-01 00:00:00', $now));
        self::assertSame('COMPLETED', CompetencyModuleRepository::effectiveStatus('COMPLETED', null, $now));
        self::assertSame('IN_PROGRESS', CompetencyModuleRepository::effectiveStatus('IN_PROGRESS', '2020-01-01', $now));
        self::assertSame('NOT_STARTED', CompetencyModuleRepository::effectiveStatus('BOGUS', null, $now));
    }

    public function testEditorIsReachableFromTrainingPilotage(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $nav = (string) file_get_contents($root . '/views/admin/training/partials/command_shell_open.php');
        $sidebar = (string) file_get_contents($root . '/views/training/partials/lms_command_sidebar.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/TrainingCompetencyModulesController.php');

        self::assertStringContainsString("'/formation/competences/modules'", $routes);
        self::assertStringContainsString("'/formation/competences/modules/{id}/suivi'", $routes);
        self::assertStringContainsString("competences/modules", $nav);
        self::assertStringContainsString("competences/modules", $sidebar);
        self::assertStringContainsString("'/formations/competences'", $sidebar);
        self::assertSame(3, substr_count($controller, 'Csrf::validate'), 'Chaque action POST valide le jeton CSRF.');
    }
}
