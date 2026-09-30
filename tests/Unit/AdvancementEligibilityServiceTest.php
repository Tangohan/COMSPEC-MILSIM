<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AdvancementRepository;
use App\Services\Advancement\AdvancementEligibilityService;
use App\Services\Advancement\AdvancementNotifier;
use App\Services\Advancement\AdvancementWorkflowService;
use App\Services\Advancement\GradeScaleTemplateService;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdvancementEligibilityServiceTest extends TestCase
{
    public function testTempsDeGradeInsuffisant(): void
    {
        $result = (new AdvancementEligibilityService())->evaluate([
            'current' => ['rank_order' => 1, 'filiere_id' => null, 'obtained_at' => '2024-07-01', 'label' => 'Gendarme'],
            'target' => [
                'rank_order' => 2,
                'filiere_id' => null,
                'label' => 'Maréchal des logis',
                'min_time_in_previous_grade_months' => 24,
                'required_qualification_id' => null,
                'advancement_choice_enabled' => true,
            ],
        ], new DateTimeImmutable('2025-09-01'));

        self::assertFalse($result['is_eligible']);
        self::assertSame(14, $result['months_in_grade']);
        self::assertSame('Temps de grade insuffisant : 14 mois sur 24 requis', $result['eligibility_reason']);
    }

    public function testQualificationManquante(): void
    {
        $result = (new AdvancementEligibilityService())->evaluate([
            'current' => ['rank_order' => 4, 'filiere_id' => 1, 'obtained_at' => '2020-01-01'],
            'target' => [
                'rank_order' => 5,
                'filiere_id' => 1,
                'label' => 'Major',
                'min_time_in_previous_grade_months' => 24,
                'required_qualification_id' => 9,
                'required_qualification_label' => 'CEFEO',
                'advancement_choice_enabled' => true,
            ],
            'qualification_award' => null,
        ], new DateTimeImmutable('2026-09-30'));

        self::assertFalse($result['is_eligible']);
        self::assertSame('Qualification CEFEO manquante', $result['eligibility_reason']);
    }

    public function testJourExactDEcheanceEstEligibleEtLaVeilleNeLEstPas(): void
    {
        $service = new AdvancementEligibilityService();
        $input = [
            'current' => ['rank_order' => 1, 'filiere_id' => null, 'obtained_at' => '2024-09-30'],
            'target' => [
                'rank_order' => 2,
                'filiere_id' => null,
                'label' => 'Caporal',
                'min_time_in_previous_grade_months' => 24,
                'advancement_seniority_enabled' => true,
            ],
        ];

        $veille = $service->evaluate($input, new DateTimeImmutable('2026-09-29'), AdvancementEligibilityService::PATH_SENIORITY);
        $jour = $service->evaluate($input, new DateTimeImmutable('2026-09-30'), AdvancementEligibilityService::PATH_SENIORITY);

        self::assertFalse($veille['is_eligible']);
        self::assertSame('2026-09-30', $veille['due_on']);
        self::assertSame(23, $veille['months_in_grade']);
        self::assertTrue($jour['is_eligible']);
        self::assertNull($jour['eligibility_reason']);
        self::assertSame(24, $jour['months_in_grade']);
        self::assertSame(
            '2026-02-28',
            $service->dueOn(new DateTimeImmutable('2026-01-31'), 1)->format('Y-m-d')
        );
    }

    public function testPublicationEstIrreversibleEtNEcrasePasLaLigne(): void
    {
        $pdo = $this->pdo();
        $repo = new AdvancementRepository($pdo);
        $workflow = new AdvancementWorkflowService($repo, new AdvancementEligibilityService(), new class extends AdvancementNotifier {
            public function notify(int $tenantId, int $personnelId, int $actorId, string $subject, string $body): void
            {
            }
        });

        $pdo->exec("INSERT INTO grade_definitions (id, tenant_id, code, label, rank_order, advancement_seniority_enabled, advancement_choice_enabled) VALUES (1, 7, 'GND', 'Gendarme', 1, 1, 0)");
        $pdo->exec("INSERT INTO grade_definitions (id, tenant_id, code, label, rank_order, advancement_seniority_enabled, advancement_choice_enabled, min_time_in_previous_grade_months) VALUES (2, 7, 'MDL', 'Maréchal des logis', 2, 0, 1, 12)");
        $pdo->exec("INSERT INTO users (id, tenant_id, display_name, email, status) VALUES (4, 7, 'Ada', 'ada@example.test', 'active')");
        $pdo->exec("INSERT INTO personnel_grade_history (id, personnel_id, grade_id, obtained_at, obtained_via) VALUES (10, 4, 1, '2020-01-01', 'initial')");
        $pdo->exec("INSERT INTO advancement_campaigns (id, tenant_id, grade_id, year, opens_at, closes_at, status) VALUES (3, 7, 2, 2026, '2026-01-01', '2026-12-31', 'en_commission')");
        $pdo->exec("INSERT INTO advancement_candidacies (id, campaign_id, personnel_id, is_eligible, decision) VALUES (8, 3, 4, 1, 'inscrit')");

        $out = $workflow->publish(7, 3, 4, new DateTimeImmutable('2026-09-30'));
        self::assertSame(1, $out['promoted']);

        $previous = $repo->findHistory(10);
        self::assertSame('initial', $previous['obtained_via']);
        self::assertSame('2026-09-30', $previous['ends_at']);
        $active = $repo->activeGrade(7, 4);
        self::assertSame('choix', $active['obtained_via']);
        self::assertSame(2, (int) $active['grade_id']);
        self::assertSame(8, (int) $active['candidacy_id']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('déjà publié');
        $workflow->publish(7, 3, 4, new DateTimeImmutable('2026-10-01'));
    }

    public function testAncienneteTombeLeJourDEcheance(): void
    {
        $pdo = $this->pdo();
        $repo = new AdvancementRepository($pdo);
        $workflow = new AdvancementWorkflowService($repo, new AdvancementEligibilityService(), new class extends AdvancementNotifier {
            public function notify(int $tenantId, int $personnelId, int $actorId, string $subject, string $body): void
            {
            }
        });
        $pdo->exec("INSERT INTO grade_definitions (id, tenant_id, code, label, rank_order, advancement_seniority_enabled, advancement_choice_enabled, min_time_in_previous_grade_months) VALUES (1, 7, 'GND', 'Gendarme', 1, 1, 0, 0)");
        $pdo->exec("INSERT INTO grade_definitions (id, tenant_id, code, label, rank_order, advancement_seniority_enabled, advancement_choice_enabled, min_time_in_previous_grade_months) VALUES (2, 7, 'MDL', 'Maréchal des logis', 2, 1, 0, 24)");
        $pdo->exec("INSERT INTO users (id, tenant_id, display_name, email, status) VALUES (4, 7, 'Ada', 'ada@example.test', 'active')");
        $pdo->exec("INSERT INTO personnel_grade_history (id, personnel_id, grade_id, obtained_at, obtained_via) VALUES (10, 4, 1, '2024-09-30', 'initial')");

        $early = $workflow->applySeniorityForTenant(7, new DateTimeImmutable('2026-09-29'));
        self::assertSame(0, $early['promoted']);
        self::assertSame(1, (int) $repo->activeGrade(7, 4)['grade_id']);

        $due = $workflow->applySeniorityForTenant(7, new DateTimeImmutable('2026-09-30'));
        self::assertSame(1, $due['promoted']);
        $active = $repo->activeGrade(7, 4);
        self::assertSame('anciennete', $active['obtained_via']);
        self::assertSame('2026-09-30', $active['obtained_at']);
    }

    public function testDuplicationDEchelleEstPropreALaCommunaute(): void
    {
        $pdo = $this->pdo();
        $repo = new AdvancementRepository($pdo);
        $scales = new GradeScaleTemplateService($repo);

        self::assertTrue($scales->seedForNewTenant(7, 'FR_CLASSIC'));
        self::assertFalse($scales->seedForNewTenant(7, 'FR_CLASSIC'));
        $grades = $repo->listGrades(7, true);
        self::assertNotEmpty($grades);
        self::assertSame(7, (int) $grades[0]['tenant_id']);
        $codes = array_column($grades, 'code');
        self::assertContains('MAJ', $codes);
        self::assertContains('SD2', $codes);
        self::assertContains('COL', $codes);
        self::assertContains('GAR', $codes);
        self::assertContains('ASP', $codes);
        self::assertGreaterThanOrEqual(19, count($codes));

        self::assertTrue($scales->duplicate(8, 'us_army_enlisted'));
        $us = array_column($repo->listGrades(8, true), 'code');
        self::assertContains('SGM', $us);
        self::assertNotContains('SGM', $codes);
    }

    public function testEnsurePourUneCommunauteExistanteResteIdempotent(): void
    {
        $pdo = $this->pdo();
        $repo = new AdvancementRepository($pdo);
        $scales = new GradeScaleTemplateService($repo);

        self::assertTrue($scales->ensureForTenant(9, 'generique'));
        $first = $repo->listGrades(9, true);
        self::assertNotEmpty($first);
        self::assertFalse($scales->ensureForTenant(9, 'us_army_enlisted'));
        self::assertCount(count($first), $repo->listGrades(9, true));
    }

    public function testLesModelesCouvrentTouteLaHierarchie(): void
    {
        $scales = new GradeScaleTemplateService(new AdvancementRepository($this->pdo()));
        $fr = array_column($scales->templates()['fr_classic']['grades'], 'code');
        $us = array_column($scales->templates()['us_classic']['grades'], 'code');
        $gd = array_column($scales->templates()['gendarmerie']['grades'], 'code');

        self::assertSame('fr_classic', $scales->templateForSystem('FR_CLASSIC'));
        self::assertSame('us_classic', $scales->templateForSystem('US_CLASSIC'));
        self::assertContains('SD2', $fr);
        self::assertContains('CCH', $fr);
        self::assertContains('SCH', $fr);
        self::assertContains('ASP', $fr);
        self::assertContains('COL', $fr);
        self::assertContains('GAR', $fr);
        self::assertContains('SPC', $us);
        self::assertContains('1SG', $us);
        self::assertContains('CW5', $us);
        self::assertContains('GEN', $us);
        self::assertContains('GAV', $gd);
        self::assertContains('MDL', $gd);
        self::assertContains('CEN', $gd);
        self::assertContains('GAR', $gd);
    }

    public function testCompleteAjouteLesGradesManquantsSansEcraser(): void
    {
        $pdo = $this->pdo();
        $repo = new AdvancementRepository($pdo);
        $scales = new GradeScaleTemplateService($repo);

        $repo->saveFiliere(3, ['code' => 'cadre', 'label' => 'Cadre', 'sort_order' => 1]);
        $repo->saveGrade(3, [
            'code' => 'GND',
            'label' => 'Gendarme',
            'short_label' => 'GND',
            'rank_order' => 1,
            'advancement_seniority_enabled' => 1,
            'advancement_choice_enabled' => 0,
        ]);
        $repo->saveGrade(3, [
            'code' => 'MAJ',
            'label' => 'Major',
            'short_label' => 'MAJ',
            'rank_order' => 2,
            'advancement_seniority_enabled' => 0,
            'advancement_choice_enabled' => 1,
        ]);

        $added = $scales->completeForTenant(3, 'FR_CLASSIC');
        self::assertGreaterThan(10, $added);
        $codes = array_column($repo->listGrades(3, true), 'code');
        self::assertContains('GND', $codes);
        self::assertContains('MAJ', $codes);
        self::assertContains('SD2', $codes);
        self::assertContains('COL', $codes);
        self::assertSame(0, $scales->completeForTenant(3, 'FR_CLASSIC'));
    }

    public function testLeDepotNeReecritPasLaVoieDObtention(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/AdvancementRepository.php');
        self::assertStringContainsString('SET ends_at = ?', $source);
        self::assertDoesNotMatchRegularExpression('/UPDATE personnel_grade_history SET (?!ends_at)/', $source);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE grade_filiere_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, code TEXT, label TEXT, sort_order INT)');
        $pdo->exec('CREATE TABLE grade_definitions (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, code TEXT, label TEXT, short_label TEXT, filiere_id INT,
            rank_order INT, advancement_seniority_enabled INT, advancement_choice_enabled INT,
            min_time_in_previous_grade_months INT, required_qualification_id INT, required_qualification_level_id INT, archived_at TEXT
        )');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INT, display_name TEXT, callsign TEXT, email TEXT, status TEXT)');
        $pdo->exec('CREATE TABLE personnel_grade_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT, personnel_id INT, grade_id INT, obtained_at TEXT, obtained_via TEXT,
            candidacy_id INT, ends_at TEXT, created_by INT
        )');
        $pdo->exec('CREATE TABLE advancement_campaigns (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, grade_id INT, filiere_id INT, year INT,
            opens_at TEXT, closes_at TEXT, status TEXT, quota_slots INT, published_at TEXT, created_by INT
        )');
        $pdo->exec('CREATE TABLE advancement_candidacies (
            id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INT, personnel_id INT, volunteered_at TEXT,
            is_eligible INT, eligibility_reason TEXT, preference_rank INT, commission_opinion TEXT, decision TEXT,
            decided_at TEXT, mobility_requested INT, requested_billet_id INT, notes TEXT, created_by INT
        )');
        $pdo->exec('CREATE TABLE advancement_commissions (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INT, meeting_date TEXT, minutes_document_id INT)');
        $pdo->exec('CREATE TABLE advancement_commission_members (id INTEGER PRIMARY KEY AUTOINCREMENT, commission_id INT, personnel_id INT, role TEXT)');
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY)');

        return $pdo;
    }
}
