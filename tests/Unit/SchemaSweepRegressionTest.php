<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Core\Request;
use App\Repositories\AtakNotificationRepository;
use App\Repositories\AtakZoneThreatRepository;
use App\Repositories\EnlistmentRepository;
use App\Repositories\ProbationOversightRepository;
use App\Services\Personnel\UnitReadinessService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Régressions de la chasse aux erreurs du 01/11/2026 (requêtes et appels qui échouaient à coup sûr).
 */
final class SchemaSweepRegressionTest extends TestCase
{
    private ?PDO $savedPdo = null;

    /** @var array<string, mixed> */
    private array $savedStatics = [];

    protected function setUp(): void
    {
        parent::setUp();
        $saved = (new \ReflectionProperty(Database::class, 'pdo'))->getValue();
        $this->savedPdo = $saved instanceof PDO ? $saved : null;
        foreach ([[EnlistmentRepository::class, 'hasCandidatePortalTables'], [ProbationOversightRepository::class, 'hasTenantUserRolesTable']] as [$class, $prop]) {
            $ref = new \ReflectionProperty($class, $prop);
            $this->savedStatics[$class . '::' . $prop] = $ref->getValue();
            $ref->setValue(null, null);
        }
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        foreach ($this->savedStatics as $key => $value) {
            [$class, $prop] = explode('::', $key);
            (new \ReflectionProperty($class, $prop))->setValue(null, $value);
        }
        parent::tearDown();
    }

    private static function sqlite(array $tables): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->sqliteCreateFunction('DATABASE', static fn (): string => 'main', 0);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $pdo->sqliteCreateFunction('CURDATE', static fn (): string => date('Y-m-d'), 0);
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE information_schema.TABLES (TABLE_SCHEMA TEXT, TABLE_NAME TEXT)');
        $pdo->exec('CREATE TABLE information_schema.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        foreach ($tables as $table => $ddl) {
            $pdo->exec('CREATE TABLE ' . $table . ' (' . $ddl . ')');
            $pdo->prepare("INSERT INTO information_schema.TABLES VALUES ('main', ?)")->execute([$table]);
        }
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $pdo);

        return $pdo;
    }

    /** /atak/passerelle/creer|rejoindre|annuler appelaient une méthode absente de Request (erreur 500). */
    public function testRequestExposesThePostedBody(): void
    {
        $hadPost = isset($_POST);
        $hadGet = isset($_GET);
        $saved = $_POST ?? [];
        $_GET ??= [];
        $_POST = ['gateway_id' => '12', 'reason' => 'fin de l’exercice'];
        try {
            $request = new Request();
            self::assertSame('12', $request->getParsedBody()['gateway_id']);
            self::assertSame('fin de l’exercice', $request->getParsedBody()['reason']);
        } finally {
            $_POST = $saved;
            if (!$hadPost) {
                unset($_POST);
            }
            if (!$hadGet) {
                unset($_GET);
            }
        }
    }

    /** Signalement d'un message depuis le portail candidat : la méthode n'existait pas (erreur 500). */
    public function testCandidatePortalMessageIsLimitedToTheCandidateDossier(): void
    {
        $pdo = self::sqlite([
            'enlistment_candidate_tokens' => 'id INTEGER PRIMARY KEY',
            'enlistment_candidate_messages' => 'id INTEGER PRIMARY KEY, tenant_id INTEGER, enlistment_id INTEGER, entry_kind TEXT, body TEXT, created_at TEXT',
        ]);
        $pdo->exec("INSERT INTO enlistment_candidate_messages VALUES (5, 1, 10, 'staff', 'Bonjour', '2026-11-01 10:00:00')");
        $repo = new EnlistmentRepository();

        self::assertSame('staff', $repo->findCandidatePortalMessage(1, 10, 5)['entry_kind'] ?? null);
        self::assertNull($repo->findCandidatePortalMessage(1, 11, 5), 'message d’un autre dossier');
        self::assertNull($repo->findCandidatePortalMessage(2, 10, 5), 'message d’une autre communauté');
        self::assertNull($repo->findCandidatePortalMessage(1, 10, 0));
    }

    /** « $this->pdo » était interpolé dans la chaîne SQL : Error « PDO could not be converted to string ». */
    public function testProbationQueriesAreBuiltWithoutInterpolatingThePdoObject(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = new class ('sqlite::memory:') extends PDO {
            /** @var list<string> */
            public array $prepared = [];

            public function prepare(string $query, array $options = []): \PDOStatement|false
            {
                $this->prepared[] = $query;

                return parent::prepare('SELECT 0 AS c WHERE ? = ? AND 1 = 0');
            }

            public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
            {
                return parent::query('SELECT 1');
            }
        };
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $pdo);
        $repo = new ProbationOversightRepository();

        self::assertSame([], $repo->listOverdue(1, 30));
        self::assertSame(0, $repo->countOverdue(1, 30));
        self::assertCount(2, $pdo->prepared);
        foreach ($pdo->prepared as $sql) {
            self::assertStringContainsString("r.slug = 'probation'", $sql);
            self::assertStringContainsString("u.status = 'active'", $sql);
            self::assertStringNotContainsString('SqlText::', $sql);
        }
    }

    /** users n'a ni user_id ni unit_id : le score de disponibilité ne trouvait jamais les membres de l'unité. */
    public function testUnitReadinessFindsMembersThroughAssignments(): void
    {
        $pdo = self::sqlite([
            'units' => 'id INTEGER PRIMARY KEY, tenant_id INTEGER',
            'users' => 'id INTEGER PRIMARY KEY, tenant_id INTEGER, status TEXT',
            'personnel_assignments' => 'id INTEGER PRIMARY KEY, user_id INTEGER, unit_id INTEGER, is_primary INTEGER, status TEXT, ended_at TEXT',
            'user_units' => 'id INTEGER PRIMARY KEY, user_id INTEGER, unit_id INTEGER, ended_at TEXT',
        ]);
        $pdo->exec('INSERT INTO units VALUES (1, 7), (2, 7), (3, 8)');
        $pdo->exec("INSERT INTO users VALUES (10, 7, 'active'), (11, 7, 'active'), (12, 7, 'inactive'), (13, 7, 'active'), (14, 8, 'active')");
        $pdo->exec("INSERT INTO personnel_assignments VALUES
            (1, 10, 1, 1, 'active', NULL),
            (2, 12, 1, 1, 'active', NULL),
            (3, 13, 1, 1, 'ended', '2020-01-01'),
            (4, 14, 3, 1, 'active', NULL)");
        // Membre sans affectation : sa ligne user_units compte (même règle que les rosters).
        $pdo->exec("INSERT INTO user_units VALUES (1, 11, 1, NULL), (2, 13, 1, '2020-01-01')");

        $service = (new \ReflectionClass(UnitReadinessService::class))->newInstanceWithoutConstructor();
        $members = (new \ReflectionMethod(UnitReadinessService::class, 'memberIdsForUnit'))->invoke($service, 7, 1);
        ksort($members);

        self::assertSame([10 => 10, 11 => 11], $members);
        self::assertSame([], (new \ReflectionMethod(UnitReadinessService::class, 'memberIdsForUnit'))->invoke($service, 7, 3), 'unité d’une autre communauté');
    }

    /** Database::execute() renvoie déjà un entier : ->rowCount() dessus levait une erreur. */
    public function testAtakCleanupOfExpiredItemsReturnsTheNumberOfRows(): void
    {
        $pdo = self::sqlite([
            'atak_realtime_notifications' => 'id INTEGER PRIMARY KEY, tenant_id INTEGER, context_id INTEGER, is_active INTEGER, expires_at TEXT',
            'atak_zone_events' => 'id INTEGER PRIMARY KEY, tenant_id INTEGER, context_id INTEGER, is_active INTEGER, expires_at TEXT',
        ]);
        foreach (['atak_realtime_notifications', 'atak_zone_events'] as $table) {
            $pdo->exec("INSERT INTO {$table} VALUES (1, 1, 2, 1, '2020-01-01 00:00:00'), (2, 1, 2, 1, '2999-01-01 00:00:00'), (3, 1, 3, 1, '2020-01-01 00:00:00')");
        }

        self::assertSame(1, (new AtakNotificationRepository())->cleanupExpired(1, 2));
        self::assertSame(1, (new AtakZoneThreatRepository())->cleanupExpiredEvents(1, 2));
    }

    /** Lien interne avec ancre dans une leçon : motif invalide (« # » délimiteur), avertissement puis erreur 500. */
    public function testTrainingCanvasKeepsInternalLinks(): void
    {
        require_once dirname(__DIR__, 2) . '/app/Support/training_canvas.php';

        self::assertSame('/academy/cours?id=3#slide-2', training_canvas_sanitize_href('/academy/cours?id=3#slide-2'));
        self::assertNull(training_canvas_sanitize_href('/a<script>'));
        self::assertNull(training_canvas_sanitize_href('//evil.example'));
    }
}
