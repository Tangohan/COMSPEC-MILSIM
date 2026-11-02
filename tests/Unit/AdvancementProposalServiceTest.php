<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AdvancementProposalRepository;
use App\Repositories\AdvancementRepository;
use App\Repositories\TenantRepository;
use App\Services\Advancement\AdvancementEligibilityService;
use App\Services\Advancement\AdvancementNotifier;
use App\Services\Advancement\AdvancementProposalEngine as Engine;
use App\Services\Advancement\AdvancementProposalService;
use App\Services\Advancement\AdvancementWorkflowService;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Propositions d’une campagne à partir des données réelles (base SQLite) : sessions Arma, sanctions, temps de grade ;
 * retenir crée la candidature avec la justification, écarter / rétablir, commission conservée au passage en commission.
 */
final class AdvancementProposalServiceTest extends TestCase
{
    private const TODAY = '2026-11-02';

    private PDO $pdo;

    /** @var array<string, mixed> */
    public array $stored = [];

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach ([
            'CREATE TABLE grade_filiere_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, code TEXT, label TEXT, sort_order INT)',
            'CREATE TABLE grade_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, code TEXT, label TEXT, short_label TEXT, filiere_id INT,
                rank_order INT, advancement_seniority_enabled INT, advancement_choice_enabled INT, min_time_in_previous_grade_months INT,
                required_qualification_id INT, required_qualification_level_id INT, archived_at TEXT)',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INT, display_name TEXT, callsign TEXT, email TEXT, status TEXT, created_at TEXT, last_login_at TEXT)',
            'CREATE TABLE personnel_grade_history (id INTEGER PRIMARY KEY AUTOINCREMENT, personnel_id INT, grade_id INT, obtained_at TEXT, obtained_via TEXT,
                candidacy_id INT, ends_at TEXT, created_by INT)',
            'CREATE TABLE advancement_campaigns (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, grade_id INT, filiere_id INT, year INT,
                opens_at TEXT, closes_at TEXT, status TEXT, quota_slots INT, published_at TEXT, created_by INT)',
            'CREATE TABLE advancement_candidacies (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INT, personnel_id INT, volunteered_at TEXT,
                is_eligible INT, eligibility_reason TEXT, preference_rank INT, commission_opinion TEXT, decision TEXT, decided_at TEXT,
                mobility_requested INT, requested_billet_id INT, notes TEXT, created_by INT, exceptional_override INT DEFAULT 0,
                exceptional_reason TEXT, exceptional_by INT, exceptional_at TEXT)',
            'CREATE TABLE advancement_commissions (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INT, meeting_date TEXT, minutes_document_id INT)',
            'CREATE TABLE advancement_commission_members (id INTEGER PRIMARY KEY AUTOINCREMENT, commission_id INT, personnel_id INT, role TEXT)',
            'CREATE TABLE roleplay_game_sessions (id INTEGER PRIMARY KEY, tenant_id INT, started_at TEXT, created_at TEXT)',
            'CREATE TABLE roleplay_game_session_members (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, session_id INT, user_id INT,
                validated_seconds INT, checked_in_at TEXT, attendance_valid INT, rh_excluded INT DEFAULT 0)',
            'CREATE TABLE moderation_actions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, target_user_id INT, action_type TEXT,
                created_at TEXT, expires_at TEXT, revoked_at TEXT, sanction_scope TEXT)',
            'CREATE TABLE advancement_proposal_decisions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, campaign_id INT, personnel_id INT,
                decision TEXT, score INT, justification TEXT, decided_by INT, decided_at TEXT, UNIQUE (campaign_id, personnel_id))',
        ] as $sql) {
            $pdo->exec($sql);
        }
        $pdo->exec("INSERT INTO grade_definitions (id, tenant_id, code, label, rank_order, advancement_choice_enabled) VALUES (1, 7, 'CPL', 'Caporal', 1, 0)");
        $pdo->exec("INSERT INTO grade_definitions (id, tenant_id, code, label, rank_order, advancement_choice_enabled, min_time_in_previous_grade_months) VALUES (2, 7, 'SGT', 'Sergent', 2, 1, 8)");
        $pdo->exec("INSERT INTO users (id, tenant_id, display_name, status, created_at) VALUES
            (4, 7, 'Alice', 'active', '2024-01-10'), (5, 7, 'Bruno', 'active', '2024-01-10'), (6, 7, 'Chloé', 'active', '2025-06-01'),
            (8, 7, 'Parti', 'inactive', '2023-01-01'), (9, 7, 'Juge', 'active', '2022-01-01')");
        $pdo->exec("INSERT INTO personnel_grade_history (personnel_id, grade_id, obtained_at, obtained_via) VALUES
            (4, 1, '2025-09-01', 'initial'), (5, 1, '2025-09-01', 'initial'), (6, 1, '2026-05-01', 'initial'), (8, 1, '2020-01-01', 'initial')");
        $pdo->exec("INSERT INTO advancement_campaigns (id, tenant_id, grade_id, year, opens_at, closes_at, status) VALUES (3, 7, 2, 2026, '2026-10-15', '2026-11-30', 'ouverte')");
        // Quatre sessions sur la période : Alice présente partout (3 h validées chacune), Bruno à deux.
        foreach (['2026-10-03', '2026-10-10', '2026-10-17', '2026-10-24'] as $i => $day) {
            $sid = 100 + $i;
            $pdo->exec("INSERT INTO roleplay_game_sessions (id, tenant_id, started_at, created_at) VALUES ($sid, 7, '$day 21:00:00', '$day 20:00:00')");
            $pdo->exec("INSERT INTO roleplay_game_session_members (tenant_id, session_id, user_id, validated_seconds, checked_in_at, attendance_valid) VALUES (7, $sid, 4, 10800, '$day 21:05:00', 1)");
            if ($i < 2) {
                $pdo->exec("INSERT INTO roleplay_game_session_members (tenant_id, session_id, user_id, validated_seconds, checked_in_at, attendance_valid) VALUES (7, $sid, 5, 10800, '$day 21:05:00', 1)");
            }
        }
        $pdo->exec("INSERT INTO moderation_actions (tenant_id, target_user_id, action_type, created_at, expires_at, sanction_scope) VALUES (7, 5, 'suspend', '2026-09-20 10:00:00', '2026-09-27 10:00:00', 'tenant')");
        $this->pdo = $pdo;
    }

    private function service(): AdvancementProposalService
    {
        $repo = new AdvancementRepository($this->pdo);
        $workflow = new AdvancementWorkflowService($repo, new AdvancementEligibilityService(), new class extends AdvancementNotifier {
            public function notify(int $tenantId, int $personnelId, int $actorId, string $subject, string $body): void
            {
            }
        });
        $test = $this;
        $tenants = new class ($this->pdo, $test) extends TenantRepository {
            public function __construct(PDO $pdo, private AdvancementProposalServiceTest $test)
            {
                parent::__construct($pdo);
            }

            public function getSettings(int $tenantId): array
            {
                return $this->test->stored;
            }

            public function mergeSettings(int $tenantId, array $patch): void
            {
                $this->test->stored = array_merge($this->test->stored, $patch);
            }
        };

        return new AdvancementProposalService($repo, $workflow, new AdvancementProposalRepository($this->pdo), null, $tenants);
    }

    public function testPropositionsDuGradePrecedentAvecDonneesReelles(): void
    {
        $service = $this->service();
        $campaign = (new AdvancementRepository($this->pdo))->findCampaign(3, 7);
        $out = $service->forTarget(7, $campaign, 3, new DateTimeImmutable(self::TODAY));
        $rows = array_column($out['rows'], null, 'name');

        self::assertSame(['Alice', 'Bruno', 'Chloé'], array_column($out['rows'], 'name'), 'Membre inactif exclu ; proposés, écartés, puis pas encore éligibles');
        self::assertSame('Caporal', $out['previous_label']);
        self::assertSame(3, $out['counts']['population']);

        $alice = $rows['Alice']['proposal'];
        self::assertSame(Engine::STATUS_PROPOSE, $alice['status']);
        $aliceCriteria = array_column($alice['criteria'], null, 'key');
        self::assertSame('4 présences sur 90 jours (minimum 3)', $aliceCriteria['presences']['detail']);
        self::assertStringStartsWith('12 h de jeu validées', $aliceCriteria['hours']['detail']);
        self::assertSame('Dernière activité il y a 9 jours', $aliceCriteria['activity']['detail']);
        self::assertSame('session Arma', $aliceCriteria['activity']['hint']);

        $bruno = $rows['Bruno']['proposal'];
        self::assertSame(Engine::STATUS_BLOCKED, $bruno['status']);
        self::assertStringStartsWith('Suspension le 20 septembre 2026', $bruno['missing'][0]);

        $chloe = $rows['Chloé']['proposal'];
        self::assertSame(Engine::STATUS_INELIGIBLE, $chloe['status']);
        self::assertStringStartsWith('Manque 2 mois dans le grade (6 sur 8', $chloe['missing'][0]);
        self::assertTrue($chloe['near']);

        $sources = array_column($service->sources(7, new DateTimeImmutable(self::TODAY)), null, 'key');
        self::assertTrue($sources['presences']['measured']);
        self::assertTrue($sources['hours']['measured']);
        self::assertTrue($sources['conduct']['measured']);
        self::assertFalse($sources['absences']['measured'], 'Table des absences absente : non mesuré');
        self::assertStringContainsString('1 sans grade', $sources['grade_time']['detail']);
    }

    public function testRetenirCreeLaCandidatureAvecLaJustificationEtEcarterSeRetablit(): void
    {
        $service = $this->service();
        $today = new DateTimeImmutable(self::TODAY);
        $service->accept(7, 3, 4, '', 9, $today);

        $candidacy = (new AdvancementRepository($this->pdo))->findCandidacyForPersonnel(3, 4);
        self::assertNotNull($candidacy);
        self::assertSame(1, (int) $candidacy['is_eligible']);
        self::assertStringStartsWith('Proposition de la détection automatique (score ', (string) $candidacy['notes']);
        self::assertSame('retenue', $this->pdo->query('SELECT decision FROM advancement_proposal_decisions WHERE personnel_id = 4')->fetchColumn());

        $campaign = (new AdvancementRepository($this->pdo))->findCampaign(3, 7);
        $after = array_column($service->forTarget(7, $campaign, 3, $today)['rows'], null, 'name');
        self::assertTrue($after['Alice']['is_candidate']);

        $service->dismiss(7, 3, 5, 'Vu en entretien', 9, $today);
        $dismissed = array_column($service->forTarget(7, $campaign, 3, $today)['rows'], null, 'name');
        self::assertSame('ecartee', $dismissed['Bruno']['decision']['decision']);
        self::assertSame('Vu en entretien', $dismissed['Bruno']['decision']['justification']);
        $service->restore(7, 3, 5);
        self::assertNull(array_column($service->forTarget(7, $campaign, 3, $today)['rows'], null, 'name')['Bruno']['decision']);

        $this->expectExceptionMessage('déjà candidat');
        $service->accept(7, 3, 4, 'Encore', 9, $today);
    }

    public function testToutRetenirNePrendQueLesProposesEtLesSeuilsSontEnregistres(): void
    {
        $service = $this->service();
        $out = $service->acceptAll(7, 3, 9, new DateTimeImmutable(self::TODAY));
        self::assertSame(1, $out['accepted']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM advancement_candidacies')->fetchColumn());

        $saved = $service->saveSettings(7, ['min_presences' => 5, 'min_score' => 'x']);
        self::assertSame(5, $saved['min_presences']);
        self::assertSame(Engine::DEFAULTS['min_score'], $saved['min_score']);
        self::assertSame(5, $this->stored[Engine::SETTINGS_KEY]['min_presences']);
        $campaign = (new AdvancementRepository($this->pdo))->findCampaign(3, 7);
        $rows = array_column($service->forTarget(7, $campaign, 3, new DateTimeImmutable(self::TODAY))['rows'], null, 'name');
        self::assertContains('Manque 1 présence sur 90 jours (4 sur 5)', $rows['Alice']['proposal']['missing']);
    }

    public function testLaCommissionChoisieALaCreationEstConserveeAuPassageEnCommission(): void
    {
        $repo = new AdvancementRepository($this->pdo);
        $repo->saveCommission(3, '2026-12-05', null, [['personnel_id' => 9, 'role' => 'titulaire']]);
        $workflow = new AdvancementWorkflowService($repo, new AdvancementEligibilityService(), new AdvancementNotifier());
        $workflow->openCommission(7, 3, '', null, []);

        $commission = $repo->findCommission(3);
        self::assertSame('2026-12-05', $commission['meeting_date']);
        self::assertSame([9], array_map('intval', array_column($commission['members'], 'personnel_id')));
    }
}
