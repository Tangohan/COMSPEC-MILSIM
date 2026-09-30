<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AdvancementRepository;
use App\Services\Advancement\AdvancementEligibilityService;
use App\Services\Advancement\AdvancementNotifier;
use App\Services\Advancement\AdvancementRankingService;
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

    public function testPublicationExceptionnellePasseOutreLeTempsDeGrade(): void
    {
        $pdo = $this->pdo();
        $repo = new AdvancementRepository($pdo);
        $workflow = new AdvancementWorkflowService($repo, new AdvancementEligibilityService(), new class extends AdvancementNotifier {
            public function notify(int $tenantId, int $personnelId, int $actorId, string $subject, string $body): void
            {
            }
        });
        $pdo->exec("INSERT INTO grade_definitions (id, tenant_id, code, label, rank_order, advancement_seniority_enabled, advancement_choice_enabled, min_time_in_previous_grade_months) VALUES (1, 7, 'GND', 'Gendarme', 1, 1, 0, 0)");
        $pdo->exec("INSERT INTO grade_definitions (id, tenant_id, code, label, rank_order, advancement_seniority_enabled, advancement_choice_enabled, min_time_in_previous_grade_months) VALUES (2, 7, 'MDL', 'Maréchal des logis', 2, 0, 1, 12)");
        $pdo->exec("INSERT INTO users (id, tenant_id, display_name, email, status) VALUES (4, 7, 'Tanguy', 'tanguy@example.test', 'active')");
        $pdo->exec("INSERT INTO personnel_grade_history (id, personnel_id, grade_id, obtained_at, obtained_via) VALUES (10, 4, 1, '2026-09-30', 'initial')");
        $pdo->exec("INSERT INTO advancement_campaigns (id, tenant_id, grade_id, year, opens_at, closes_at, status) VALUES (3, 7, 2, 2026, '2026-01-01', '2026-12-31', 'en_commission')");
        $pdo->exec("INSERT INTO advancement_candidacies (id, campaign_id, personnel_id, is_eligible, decision) VALUES (8, 3, 4, 1, 'inscrit')");

        try {
            $workflow->publish(7, 3, 4, new DateTimeImmutable('2026-09-30'));
            self::fail('La publication sans motif exceptionnel doit échouer.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('n’est plus éligible', $e->getMessage());
        }

        $pdo->exec("UPDATE advancement_candidacies SET exceptional_override = 1, exceptional_reason = 'Besoin opérationnel du groupement' WHERE id = 8");
        $out = $workflow->publish(7, 3, 4, new DateTimeImmutable('2026-09-30'));
        self::assertSame(1, $out['promoted']);
        $active = $repo->activeGrade(7, 4);
        self::assertSame('exception', $active['obtained_via']);
        self::assertSame(2, (int) $active['grade_id']);
    }

    public function testMonAvancementReprendLeGradeDeLaFiche(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('ALTER TABLE users ADD COLUMN grade_id INT');
        $pdo->exec('ALTER TABLE users ADD COLUMN created_at TEXT');
        $pdo->exec('CREATE TABLE personnel_profiles (user_id INT, rank_display TEXT)');
        $pdo->exec('CREATE TABLE grades (id INTEGER PRIMARY KEY, code TEXT, label_long TEXT, label_short TEXT)');
        $pdo->exec("INSERT INTO grades (id, code, label_long, label_short) VALUES (40, 'PFC', 'Private First Class', 'PFC')");
        $pdo->exec("INSERT INTO grade_definitions (id, tenant_id, code, label, short_label, rank_order, advancement_seniority_enabled, advancement_choice_enabled) VALUES (5, 7, 'PFC', 'Private First Class', 'PFC', 3, 1, 0)");
        $pdo->exec("INSERT INTO users (id, tenant_id, display_name, email, status, grade_id, created_at) VALUES (11, 7, 'Jake', 'jake@example.test', 'active', 40, '2024-03-01')");
        $pdo->exec("INSERT INTO personnel_profiles (user_id, rank_display) VALUES (11, 'Airman First Class')");

        $repo = new AdvancementRepository($pdo);
        $workflow = new AdvancementWorkflowService($repo, new AdvancementEligibilityService(), new class extends AdvancementNotifier {
            public function notify(int $tenantId, int $personnelId, int $actorId, string $subject, string $body): void
            {
            }
        });

        $panel = $workflow->personnelPanel(7, 11, new DateTimeImmutable('2026-09-30'));
        self::assertSame('Airman First Class', (string) ($panel['current']['label'] ?? ''));
        self::assertSame('initial', (string) ($panel['current']['obtained_via'] ?? ''));
        self::assertSame('2024-03-01', (string) ($panel['current']['obtained_at'] ?? ''));
        self::assertSame(5, (int) ($panel['current']['grade_id'] ?? 0));
        self::assertCount(1, $panel['history']);
        $again = $workflow->personnelPanel(7, 11, new DateTimeImmutable('2026-09-30'));
        self::assertCount(1, $again['history']);
    }

    public function testMonAvancementAfficheLeTitreDeFicheMemeSansEchelle(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('ALTER TABLE users ADD COLUMN grade_id INT');
        $pdo->exec('CREATE TABLE personnel_profiles (user_id INT, rank_display TEXT)');
        $pdo->exec("INSERT INTO users (id, tenant_id, display_name, email, status, grade_id) VALUES (12, 7, 'Jake', 'jake2@example.test', 'active', 0)");
        $pdo->exec("INSERT INTO personnel_profiles (user_id, rank_display) VALUES (12, 'Airman First Class')");

        $repo = new AdvancementRepository($pdo);
        $workflow = new AdvancementWorkflowService($repo, new AdvancementEligibilityService(), new class extends AdvancementNotifier {
            public function notify(int $tenantId, int $personnelId, int $actorId, string $subject, string $body): void
            {
            }
        });

        $panel = $workflow->personnelPanel(7, 12, new DateTimeImmutable('2026-09-30'));
        self::assertSame('Airman First Class', (string) ($panel['current']['label'] ?? ''));
        self::assertSame([], $panel['history']);
        self::assertNull($panel['next'] ?? null);
        self::assertSame([], $panel['opinions'] ?? []);
    }

    public function testPersonnelPanelMontreProchainGradeConditionsEtMode(): void
    {
        $pdo = $this->pdo();
        $pdo->exec("INSERT INTO grade_definitions (id, tenant_id, code, label, short_label, rank_order, advancement_seniority_enabled, advancement_choice_enabled, min_time_in_previous_grade_months) VALUES
            (1, 7, 'SD2', 'Soldat de 2e classe', 'Sdt 2', 1, 0, 0, NULL),
            (2, 7, 'SD1', 'Soldat de 1re classe', 'Sdt 1', 2, 1, 1, 12)");
        $pdo->exec("INSERT INTO users (id, tenant_id, display_name, email, status) VALUES (21, 7, 'Léa', 'lea@example.test', 'active')");
        $pdo->exec("INSERT INTO personnel_grade_history (personnel_id, grade_id, obtained_at, obtained_via) VALUES (21, 1, '2026-01-30', 'initial')");

        $repo = new AdvancementRepository($pdo);
        $workflow = new AdvancementWorkflowService($repo, new AdvancementEligibilityService(), new class extends AdvancementNotifier {
            public function notify(int $tenantId, int $personnelId, int $actorId, string $subject, string $body): void
            {
            }
        });

        $panel = $workflow->personnelPanel(7, 21, new DateTimeImmutable('2026-09-30'));
        $next = $panel['next'] ?? null;
        self::assertIsArray($next);
        self::assertSame('Soldat de 1re classe', (string) ($next['grade_label'] ?? ''));
        self::assertTrue(!empty($next['automatic']));
        self::assertTrue(!empty($next['choice']));
        self::assertSame('both', (string) ($next['mode'] ?? ''));
        self::assertSame('2027-01-30', (string) ($next['due_on'] ?? ''));
        self::assertFalse(!empty($next['seniority_eligible']));
        self::assertFalse(!empty($next['choice_eligible']));
        $keys = array_column($next['conditions'] ?? [], 'key');
        self::assertContains('temps_de_grade', $keys);
        self::assertContains('voie_auto', $keys);
        self::assertContains('voie_choix', $keys);
    }

    public function testPersonnelPanelMontreAvisCommandement(): void
    {
        $pdo = $this->pdo();
        $pdo->exec("INSERT INTO grade_definitions (id, tenant_id, code, label, short_label, rank_order, advancement_seniority_enabled, advancement_choice_enabled) VALUES
            (1, 7, 'SD2', 'Soldat', 'Sdt', 1, 0, 0),
            (2, 7, 'CPL', 'Caporal', 'Cpl', 2, 0, 1)");
        $pdo->exec("INSERT INTO users (id, tenant_id, display_name, email, status) VALUES (22, 7, 'Marc', 'marc@example.test', 'active')");
        $pdo->exec("INSERT INTO personnel_grade_history (personnel_id, grade_id, obtained_at, obtained_via) VALUES (22, 1, '2024-01-01', 'initial')");
        $pdo->exec("INSERT INTO advancement_campaigns (id, tenant_id, grade_id, year, opens_at, closes_at, status) VALUES (9, 7, 2, 2026, '2026-01-01', '2026-12-31', 'en_commission')");
        $pdo->exec("INSERT INTO advancement_candidacies (campaign_id, personnel_id, volunteered_at, is_eligible, commission_opinion, decision) VALUES (9, 22, '2026-03-01', 1, 'propose', 'inscrit')");

        $repo = new AdvancementRepository($pdo);
        $workflow = new AdvancementWorkflowService($repo, new AdvancementEligibilityService(), new class extends AdvancementNotifier {
            public function notify(int $tenantId, int $personnelId, int $actorId, string $subject, string $body): void
            {
            }
        });

        $panel = $workflow->personnelPanel(7, 22, new DateTimeImmutable('2026-09-30'));
        self::assertNotEmpty($panel['opinions'] ?? []);
        self::assertSame('propose', (string) ($panel['opinions'][0]['commission_opinion'] ?? ''));
        self::assertSame('Proposé', (string) ($panel['opinions'][0]['opinion_label'] ?? ''));
        self::assertSame('Inscrit', (string) ($panel['opinions'][0]['decision_label'] ?? ''));
        self::assertSame('Caporal', (string) ($panel['opinions'][0]['grade_label'] ?? ''));
    }

    public function testClassementAutomatiqueEtDetections(): void
    {
        $svc = new AdvancementRankingService();
        $out = $svc->proposeCandidacyOrder([
            ['id' => 1, 'display_name' => 'Junior', 'is_eligible' => 0, 'months_in_grade' => 40, 'volunteered_at' => '2026-01-01'],
            ['id' => 2, 'display_name' => 'Ancien', 'is_eligible' => 1, 'months_in_grade' => 20, 'volunteered_at' => '2026-01-02'],
            ['id' => 3, 'display_name' => 'Récent', 'is_eligible' => 1, 'months_in_grade' => 8, 'volunteered_at' => '2026-01-01'],
        ]);
        self::assertSame(1, $out['ranks'][2]);
        self::assertSame(2, $out['ranks'][3]);
        self::assertSame(3, $out['ranks'][1]);

        $hits = $svc->detectCandidacies([
            ['id' => 1, 'display_name' => 'Ada', 'preference_rank' => 1, 'is_eligible' => 1, 'decision' => 'inscrit'],
            ['id' => 2, 'display_name' => 'Bob', 'preference_rank' => 1, 'is_eligible' => 0, 'decision' => 'inscrit'],
        ]);
        $codes = array_column($hits, 'code');
        self::assertContains('rang_double', $codes);
        self::assertContains('ineligible_listed', $codes);

        $grades = $svc->proposeGradeOrder([
            ['id' => 1, 'code' => 'COL', 'label' => 'Colonel', 'filiere_id' => 3, 'rank_order' => 1],
            ['id' => 2, 'code' => 'SL', 'label' => 'Sous-lieutenant', 'filiere_id' => 3, 'rank_order' => 2],
        ]);
        self::assertSame([2, 1], $grades['order']);
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
        self::assertContains('SL', $codes);
        self::assertNotContains('ASP', $codes);
        self::assertNotContains('GAV', $codes);
        self::assertGreaterThanOrEqual(19, count($codes));

        self::assertTrue($scales->duplicate(8, 'us_classic'));
        $us = array_column($repo->listGrades(8, true), 'code');
        self::assertContains('SGM', $us);
        self::assertContains('PVT', $us);
        self::assertContains('GEN', $us);
        self::assertNotContains('SGM', $codes);
        self::assertNotContains('WO1', $us);
        self::assertNotContains('SPC', $us);
    }

    public function testEnsurePourUneCommunauteExistanteResteIdempotent(): void
    {
        $pdo = $this->pdo();
        $repo = new AdvancementRepository($pdo);
        $scales = new GradeScaleTemplateService($repo);

        self::assertTrue($scales->ensureForTenant(9, 'generique'));
        $first = $repo->listGrades(9, true);
        self::assertNotEmpty($first);
        self::assertFalse($scales->ensureForTenant(9, 'us_classic'));
        self::assertCount(count($first), $repo->listGrades(9, true));
    }

    public function testLesModelesCouvrentTouteLaHierarchie(): void
    {
        $scales = new GradeScaleTemplateService(new AdvancementRepository($this->pdo()));
        $templates = $scales->templates();
        $fr = array_column($templates['fr_classic']['grades'], 'code');
        $us = array_column($templates['us_classic']['grades'], 'code');

        self::assertSame(['fr_classic', 'us_classic', 'generique'], array_keys($templates));
        self::assertArrayNotHasKey('gendarmerie', $templates);
        self::assertArrayNotHasKey('us_army_enlisted', $templates);
        self::assertSame('fr_classic', $scales->templateForSystem('FR_CLASSIC'));
        self::assertSame('us_classic', $scales->templateForSystem('US_CLASSIC'));
        self::assertContains('SD2', $fr);
        self::assertContains('CCH', $fr);
        self::assertContains('SCH', $fr);
        self::assertContains('SL', $fr);
        self::assertContains('COL', $fr);
        self::assertContains('GAR', $fr);
        self::assertNotContains('ASP', $fr);
        self::assertNotContains('GAV', $fr);
        self::assertContains('PVT', $us);
        self::assertContains('CPL', $us);
        self::assertContains('SGM', $us);
        self::assertContains('GEN', $us);
        self::assertNotContains('SPC', $us);
        self::assertNotContains('1SG', $us);
        self::assertNotContains('CW5', $us);
    }

    public function testCompleteSansReferentielNeMelangePasUneEchelleExistante(): void
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
        self::assertSame(0, $added);
        $codes = array_column($repo->listGrades(3, true), 'code');
        self::assertSame(['GND', 'MAJ'], $codes);
        self::assertSame(0, $scales->completeForTenant(3, 'FR_CLASSIC'));
    }

    public function testArchiveLesGradesHorsReferentielInutilises(): void
    {
        $pdo = $this->pdo();
        $repo = new AdvancementRepository($pdo);
        $scales = new GradeScaleTemplateService($repo);

        $gnd = $repo->saveGrade(5, [
            'code' => 'GND',
            'label' => 'Gendarme',
            'short_label' => 'GND',
            'rank_order' => 1,
            'advancement_seniority_enabled' => 1,
            'advancement_choice_enabled' => 0,
        ]);
        $maj = $repo->saveGrade(5, [
            'code' => 'MAJ',
            'label' => 'Major',
            'short_label' => 'MAJ',
            'rank_order' => 2,
            'advancement_seniority_enabled' => 0,
            'advancement_choice_enabled' => 1,
        ]);
        $pdo->prepare('INSERT INTO personnel_grade_history (personnel_id, grade_id, obtained_at, obtained_via) VALUES (1, ?, ?, ?)')
            ->execute([$maj, '2024-01-01', 'initial']);

        self::assertSame(1, $scales->archiveUnusedGradesNotIn(5, ['MAJ', 'SD2']));
        $gndRow = $repo->findGrade($gnd, 5);
        $majRow = $repo->findGrade($maj, 5);
        self::assertNotEmpty($gndRow['archived_at'] ?? null);
        self::assertEmpty($majRow['archived_at'] ?? null);
    }

    public function testCompleteProjetteLeReferentielUnique(): void
    {
        $pdo = $this->pdo();
        $this->seedCatalog($pdo);
        $repo = new AdvancementRepository($pdo);
        $scales = new GradeScaleTemplateService($repo);

        $gnd = $repo->saveGrade(4, [
            'code' => 'GND',
            'label' => 'Gendarme',
            'short_label' => 'GND',
            'rank_order' => 1,
            'advancement_seniority_enabled' => 1,
            'advancement_choice_enabled' => 0,
        ]);

        $added = $scales->completeForTenant(4, 'FR_CLASSIC');
        self::assertSame(3, $added);
        $codes = array_column($repo->listGrades(4, false), 'code');
        sort($codes);
        self::assertSame(['GND', 'SD2', 'SGT', 'SL'], $codes);
        $leftover = $repo->findGrade($gnd, 4);
        self::assertSame('Gendarme', (string) ($leftover['label'] ?? ''));
        self::assertEmpty($leftover['archived_at'] ?? null);
        self::assertSame(0, $scales->completeForTenant(4, 'FR_CLASSIC'));
    }

    public function testCompleteReutiliseUnGradeDejaPresent(): void
    {
        $pdo = $this->pdo();
        $this->seedCatalog($pdo);
        $repo = new AdvancementRepository($pdo);
        $scales = new GradeScaleTemplateService($repo);

        $repo->saveGrade(6, [
            'code' => 'SD2',
            'label' => 'Bleu de la commu',
            'short_label' => 'Bleu',
            'rank_order' => 9,
            'advancement_seniority_enabled' => 0,
            'advancement_choice_enabled' => 1,
            'min_time_in_previous_grade_months' => 3,
        ]);

        self::assertSame(2, $scales->completeForTenant(6, 'FR_CLASSIC'));
        $sd2 = $repo->findGradeByCode(6, 'SD2');
        self::assertSame('Bleu de la commu', (string) ($sd2['label'] ?? ''));
        self::assertSame(9, (int) ($sd2['rank_order'] ?? 0));
        self::assertSame(3, (int) ($sd2['min_time_in_previous_grade_months'] ?? 0));
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
            decided_at TEXT, mobility_requested INT, requested_billet_id INT, notes TEXT, created_by INT,
            exceptional_override INT DEFAULT 0, exceptional_reason TEXT, exceptional_by INT, exceptional_at TEXT
        )');
        $pdo->exec('CREATE TABLE advancement_commissions (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INT, meeting_date TEXT, minutes_document_id INT)');
        $pdo->exec('CREATE TABLE advancement_commission_members (id INTEGER PRIMARY KEY AUTOINCREMENT, commission_id INT, personnel_id INT, role TEXT)');
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY)');

        return $pdo;
    }

    private function seedCatalog(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE grade_categories (id INTEGER PRIMARY KEY, code TEXT, label TEXT, sort_order INT)');
        $pdo->exec('CREATE TABLE grade_systems (id INTEGER PRIMARY KEY, code TEXT, label TEXT)');
        $pdo->exec('CREATE TABLE grades (
            id INTEGER PRIMARY KEY, grade_system_id INT, grade_category_id INT, code TEXT,
            label_short TEXT, label_long TEXT, label_otan TEXT, sort_order INT, is_commissioned INT, is_active INT
        )');
        $pdo->exec("INSERT INTO grade_categories (id, code, label, sort_order) VALUES
            (1, 'OFFICIER', 'Officier', 10),
            (2, 'SOUS_OFFICIER', 'Sous-officier', 20),
            (3, 'MDR', 'Militaire du rang', 30)");
        $pdo->exec("INSERT INTO grade_systems (id, code, label) VALUES (1, 'FR_CLASSIC', 'FR')");
        $pdo->exec("INSERT INTO grades (id, grade_system_id, grade_category_id, code, label_short, label_long, label_otan, sort_order, is_commissioned, is_active) VALUES
            (1, 1, 3, 'SD2', 'Sdt 2', 'Soldat de 2e classe', 'OR-1', 34, 0, 1),
            (2, 1, 2, 'SGT', 'Sgt', 'Sergent', 'OR-5', 25, 0, 1),
            (3, 1, 1, 'SL', 'Slt', 'Sous-lieutenant', 'OF-1', 11, 1, 1)");
    }
}
