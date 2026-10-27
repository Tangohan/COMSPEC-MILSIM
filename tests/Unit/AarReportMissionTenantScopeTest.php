<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AdminAarReportsController;
use App\Controllers\Api\AarReportsApiController;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\AarReportRepository;
use App\Repositories\AarReportTemplateRepository;
use App\Repositories\TheatreMissionCycleRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * SEC-032 : un compte rendu AAR ne se rattache qu'à un cycle de mission de sa communauté.
 *
 * Avant, save() gardait le mission_cycle_id envoyé même quand il appartenait à une autre communauté, et les lectures
 * joignaient theatre_mission_cycles sans la communauté : un membre qui a le droit ATAK « gérer » lisait le titre de
 * n'importe quel cycle (identifiants énumérables) dans GET /api/atak/aar-reports, en fiche ou en registre.
 * Dépôt réel sur sqlite (information_schema, DATABASE() et UTC_TIMESTAMP() simulés).
 */
final class AarReportMissionTenantScopeTest extends TestCase
{
    private const OWN = 7;

    private const FOREIGN = 3;

    private const OWN_CYCLE = 11;

    private const FOREIGN_CYCLE = 12;

    private const FOREIGN_TITLE = 'Opération Minuit de la communauté 3';

    private const AUTHOR = 5;

    private PDO $pdo;

    private AarReportRepository $reports;

    private ?PDO $savedPdo = null;

    private bool $savedSessionStarted = false;

    /** @var array{set: bool, value: mixed} */
    private array $savedSession = ['set' => false, 'value' => null];

    private bool $hadPost = false;

    private mixed $savedPost = null;

    /** @var array<string, mixed> */
    private array $savedServer = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        // Un test précédent peut avoir retiré $_POST (unset) : Request le copie dans une propriété typée array.
        $this->hadPost = isset($_POST);
        $this->savedPost = $_POST ?? null;
        $_POST = [];
        foreach (['REQUEST_METHOD', 'REQUEST_URI', 'HTTP_X_CSRF_TOKEN'] as $key) {
            if (array_key_exists($key, $_SERVER)) {
                $this->savedServer[$key] = $_SERVER[$key];
            }
        }

        $this->pdo = self::schema();
        $db = new \ReflectionProperty(Database::class, 'pdo');
        $saved = $db->getValue();
        $this->savedPdo = $saved instanceof PDO ? $saved : null;
        $db->setValue(null, $this->pdo);

        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedSessionStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $this->savedSession = ['set' => isset($_SESSION), 'value' => $_SESSION ?? null];
        $_SESSION = [];

        $this->reports = new AarReportRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        Gate::reset();
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedSessionStarted);
        if ($this->savedSession['set']) {
            $_SESSION = $this->savedSession['value'];
        } else {
            unset($_SESSION);
        }
        foreach (['REQUEST_METHOD', 'REQUEST_URI', 'HTTP_X_CSRF_TOKEN'] as $key) {
            if (array_key_exists($key, $this->savedServer)) {
                $_SERVER[$key] = $this->savedServer[$key];
            } else {
                unset($_SERVER[$key]);
            }
        }
        if ($this->hadPost) {
            $_POST = $this->savedPost;
        } else {
            unset($_POST);
        }
        parent::tearDown();
    }

    public function testForeignMissionCycleIsRefusedAndNothingIsStored(): void
    {
        foreach ([self::FOREIGN_CYCLE, (string) self::FOREIGN_CYCLE, 999] as $missionId) {
            try {
                $this->reports->save(self::OWN, null, self::AUTHOR, ['title' => 'Compte rendu', 'mission_cycle_id' => $missionId]);
                self::fail('cycle ' . var_export($missionId, true) . ' hors de la communauté accepté');
            } catch (\DomainException $e) {
                self::assertSame(AarReportRepository::UNKNOWN_MISSION_MESSAGE, $e->getMessage());
            }
        }

        self::assertSame(0, $this->storedReports(), 'rien n’est enregistré');
    }

    public function testUpdateWithAForeignMissionCycleLeavesTheReportUnchanged(): void
    {
        $saved = $this->reports->save(self::OWN, null, self::AUTHOR, ['title' => 'Version 1', 'mission_cycle_id' => self::OWN_CYCLE]);
        $id = (int) ($saved['id'] ?? 0);
        self::assertGreaterThan(0, $id);

        try {
            $this->reports->save(self::OWN, $id, self::AUTHOR, ['title' => 'Version 2', 'mission_cycle_id' => self::FOREIGN_CYCLE]);
            self::fail('cycle d’une autre communauté accepté à la modification');
        } catch (\DomainException $e) {
            self::assertSame(AarReportRepository::UNKNOWN_MISSION_MESSAGE, $e->getMessage());
        }

        $row = $this->pdo->query('SELECT title, mission_cycle_id FROM aar_reports WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['title' => 'Version 1', 'mission_cycle_id' => self::OWN_CYCLE], $row);
    }

    /**
     * Lien déjà enregistré avant ce correctif (save() le gardait) : aucune lecture ne rend plus le titre du cycle
     * de l'autre communauté, ni comme titre de mission ni comme intitulé d'opération par défaut.
     */
    public function testReadsNeverShowTheTitleOfAnotherCommunitysCycle(): void
    {
        $this->pdo->prepare(
            "INSERT INTO aar_reports (tenant_id, mission_cycle_id, mission_plan_id, author_user_id, title, status, reported_at)
             VALUES (?, ?, 77, ?, 'Compte rendu ancien', 'pending', '2026-10-01 23:00:00')"
        )->execute([self::OWN, self::FOREIGN_CYCLE, self::AUTHOR]);
        $id = (int) $this->pdo->lastInsertId();

        $reads = [
            'listForTenant' => $this->reports->listForTenant(self::OWN)[0] ?? null,
            'findForTenant' => $this->reports->findForTenant(self::OWN, $id),
            'findByMissionPlanId' => $this->reports->findByMissionPlanId(self::OWN, 77),
        ];
        foreach ($reads as $read => $report) {
            self::assertIsArray($report, $read);
            self::assertSame($id, $report['id'] ?? null, $read);
            self::assertSame('', $report['mission_title'] ?? null, $read);
            self::assertSame('', $report['operation_label'] ?? null, $read);
            self::assertStringNotContainsString('Minuit', (string) json_encode($report, JSON_UNESCAPED_UNICODE), $read);
        }
        $registry = array_keys($this->reports->operationStatusIndexForTenant(self::OWN));
        self::assertSame(['compte rendu ancien'], $registry, 'registre des opérations : le titre étranger n’y entre plus');
    }

    public function testOwnMissionCycleIsStillLinkedWithItsTitleAndWindow(): void
    {
        $report = $this->reports->save(self::OWN, null, self::AUTHOR, ['title' => 'Compte rendu', 'mission_cycle_id' => (string) self::OWN_CYCLE]);

        self::assertSame(self::OWN_CYCLE, $report['mission_cycle_id'] ?? null);
        self::assertSame('Aigle noir', $report['mission_title'] ?? null);
        self::assertSame('Aigle noir', $report['operation_label'] ?? null, 'intitulé par défaut : le titre de la mission');
        self::assertSame('2026-10-01 20:00:00', $report['mission_started_at'] ?? null);
        self::assertSame('2026-10-01 22:00:00', $report['mission_ended_at'] ?? null);
    }

    public function testReportWithoutMissionIsStillSaved(): void
    {
        foreach (['', null, 0, '0'] as $missionId) {
            $report = $this->reports->save(self::OWN, null, self::AUTHOR, ['title' => 'Retour libre', 'mission_cycle_id' => $missionId]);
            self::assertNull($report['mission_cycle_id'] ?? null, var_export($missionId, true));
        }
        self::assertSame(4, $this->storedReports());
    }

    /** POST /api/atak/aar-reports : 422 et rien d'enregistré, au lieu de 201 avec le titre du cycle étranger. */
    public function testApiStoreAnswers422ForAForeignMissionCycle(): void
    {
        $this->signInAsAtakManager();
        $_POST = ['title' => 'Compte rendu', 'mission_cycle_id' => (string) self::FOREIGN_CYCLE];

        $response = (new AarReportsApiController($this->reports))->store(new Request());

        self::assertSame(422, $response->statusCode(), $response->body());
        $json = json_decode($response->body(), true);
        self::assertIsArray($json);
        self::assertFalse($json['ok'] ?? null);
        self::assertSame(AarReportRepository::UNKNOWN_MISSION_MESSAGE, $json['error'] ?? null);
        self::assertStringNotContainsString('Minuit', $response->body());
        self::assertSame(0, $this->storedReports());

        $_POST = ['title' => 'Compte rendu', 'mission_cycle_id' => (string) self::OWN_CYCLE];
        $own = (new AarReportsApiController($this->reports))->store(new Request());
        self::assertSame(201, $own->statusCode(), $own->body());
        $ownJson = json_decode($own->body(), true);
        self::assertIsArray($ownJson);
        self::assertSame('Aigle noir', $ownJson['report']['mission_title'] ?? null);
    }

    /** Back-office : retour au formulaire avec le message, rien d'enregistré, au lieu d'une page d'erreur. */
    public function testBackOfficeStoreAndUpdateGoBackToTheFormForAForeignMissionCycle(): void
    {
        $this->signInAsAtakManager();
        $controller = new AdminAarReportsController(
            $this->reports,
            new TheatreMissionCycleRepository($this->pdo),
            $this->createMock(AarReportTemplateRepository::class)
        );

        $_POST = ['_csrf_token' => 'jeton', 'title' => 'Compte rendu', 'mission_cycle_id' => (string) self::FOREIGN_CYCLE];
        $created = $controller->store(new Request());
        self::assertSame(302, $created->statusCode());
        self::assertStringEndsWith('/back-office/atak/comptes-rendus/nouveau', (string) $created->headerValue('Location'));
        self::assertSame(AarReportRepository::UNKNOWN_MISSION_MESSAGE, $_SESSION['_flash']['error'] ?? null);
        self::assertSame(0, $this->storedReports());

        $saved = $this->reports->save(self::OWN, null, self::AUTHOR, ['title' => 'Version 1']);
        $id = (int) ($saved['id'] ?? 0);
        $_SESSION['_flash'] = [];
        $_POST = ['_csrf_token' => 'jeton', 'title' => 'Version 2', 'mission_cycle_id' => (string) self::FOREIGN_CYCLE];
        $updated = $controller->update(new Request(), ['id' => (string) $id]);
        self::assertSame(302, $updated->statusCode());
        self::assertStringEndsWith('/back-office/atak/comptes-rendus/' . $id . '/edit', (string) $updated->headerValue('Location'));
        self::assertSame(AarReportRepository::UNKNOWN_MISSION_MESSAGE, $_SESSION['_flash']['error'] ?? null);
        self::assertSame('Version 1', $this->pdo->query('SELECT title FROM aar_reports WHERE id = ' . $id)->fetchColumn());
    }

    /** Membre de la communauté OWN, droit ATAK « gérer » (habilitation admin.access), jeton CSRF valide. */
    private function signInAsAtakManager(): void
    {
        $_SESSION = ['tenant_id' => self::OWN, 'user_id' => self::AUTHOR, '_csrf_token' => 'jeton'];
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'jeton';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/atak/aar-reports';
        Gate::getInstance()->setPermissions(['admin.access']);
    }

    private function storedReports(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM aar_reports')->fetchColumn();
    }

    /** Tables de main (colonnes utiles) sur sqlite, information_schema simulé, deux communautés et leurs cycles. */
    private static function schema(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->sqliteCreateFunction('DATABASE', static fn (): string => 'main', 0);
        $pdo->sqliteCreateFunction('UTC_TIMESTAMP', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE information_schema.TABLES (TABLE_SCHEMA TEXT, TABLE_NAME TEXT)');
        $pdo->exec('CREATE TABLE information_schema.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        foreach (['aar_reports', 'aar_report_templates', 'theatre_mission_cycles', 'users'] as $table) {
            $pdo->prepare("INSERT INTO information_schema.TABLES VALUES ('main', ?)")->execute([$table]);
        }
        foreach (['mission_plan_id', 'template_id', 'custom_answers_json'] as $column) {
            $pdo->prepare("INSERT INTO information_schema.COLUMNS VALUES ('main', 'aar_reports', ?)")->execute([$column]);
        }

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, display_name TEXT)');
        $pdo->exec('CREATE TABLE theatre_mission_cycles (
            id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, map_id INTEGER NOT NULL DEFAULT 1, title TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'preparation\', started_at TEXT, ended_at TEXT, aar_summary TEXT,
            replay_mission_id TEXT NOT NULL DEFAULT \'\', created_by_user_id INTEGER, closed_by_user_id INTEGER,
            created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE aar_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, mission_cycle_id INTEGER,
            mission_plan_id INTEGER, template_id INTEGER, author_user_id INTEGER, validated_by_user_id INTEGER,
            title TEXT NOT NULL, operation_label TEXT, status TEXT NOT NULL DEFAULT \'pending\', reported_at TEXT,
            validated_at TEXT, mission_started_at TEXT, mission_ended_at TEXT, summary_text TEXT, strengths_json TEXT,
            weaknesses_json TEXT, open_actions_json TEXT, closed_actions_json TEXT, scores_json TEXT, metrics_json TEXT,
            custom_answers_json TEXT, created_at TEXT, updated_at TEXT)');

        $pdo->exec("INSERT INTO users (id, display_name) VALUES (" . self::AUTHOR . ", 'Rédacteur')");
        $cycle = $pdo->prepare(
            "INSERT INTO theatre_mission_cycles (id, tenant_id, title, status, started_at, ended_at, replay_mission_id)
             VALUES (?, ?, ?, 'cloturee', ?, ?, ?)"
        );
        $cycle->execute([self::OWN_CYCLE, self::OWN, 'Aigle noir', '2026-10-01 20:00:00', '2026-10-01 22:00:00', 'mission_7_map_1']);
        $cycle->execute([self::FOREIGN_CYCLE, self::FOREIGN, self::FOREIGN_TITLE, '2026-10-02 20:00:00', '2026-10-02 23:00:00', 'mission_3_map_1']);

        return $pdo;
    }
}
