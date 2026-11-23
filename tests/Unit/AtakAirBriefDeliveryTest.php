<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Core\Session;
use App\Repositories\CasNineLineRepository;
use App\Services\Tactical\AirBriefService;
use App\Services\Tactical\NineLineBoardService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Le pilote reçoit son 9-LINE : indicatifs comparés par leur clé (« TIGER 6 » = « tiger-6 » = « TIGER6 »), boîte du
 * pilote (GET /api/cas?assignedTo=~air|…, tableau SQF exact lu par la DLL), demande affectée par assigned_aircraft seule,
 * livraison (sent → delivered au relevé → read → acknowledged, expired), accusé de lecture idempotent, état exposé au web,
 * journal discret d'un indicatif sans appareil connu, et contrat jeu → serveur (POST /api/cas du jeu → boîte du pilote).
 * Dépôt réel sur sqlite, colonnes de livraison (étape 4910) présentes.
 */
final class AtakAirBriefDeliveryTest extends TestCase
{
    private const T = 7;

    private const OTHER = 3;

    private PDO $pdo;

    /** @var list<string> */
    private array $logs = [];

    private AirBriefService $briefs;

    private ?PDO $savedPdo = null;

    private bool $savedStarted = false;

    /** @var array{set: bool, value: mixed} */
    private array $savedSession = ['set' => false, 'value' => null];

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = self::schema();
        $db = new \ReflectionProperty(Database::class, 'pdo');
        $saved = $db->getValue();
        $this->savedPdo = $saved instanceof PDO ? $saved : null;
        $db->setValue(null, $this->pdo);
        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $this->savedSession = ['set' => isset($_SESSION), 'value' => $_SESSION ?? null];
        $_SESSION = [];
        $this->logs = [];
        $this->briefs = new AirBriefService($this->pdo, function (string $m): void {
            $this->logs[] = $m;
        });
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedStarted);
        if ($this->savedSession['set']) {
            $_SESSION = $this->savedSession['value'];
        } else {
            unset($_SESSION);
        }
        parent::tearDown();
    }

    public function testCallsignKeyIgnoresCaseSpacesDashesAndAccents(): void
    {
        foreach (['TIGER 6', 'tiger-6', 'Tiger6', '  TIGER   6 ', 'tiger_6', 'TIGER.6', 'Tiger/6'] as $v) {
            self::assertSame('tiger6', AirBriefService::callsignKey($v), $v);
        }
        self::assertSame('echo11', AirBriefService::callsignKey('Écho 1-1'));
        self::assertSame('', AirBriefService::callsignKey(' - '));
        self::assertSame('', AirBriefService::callsignKey(null));
        self::assertSame(['hog21' => 'HOG 21', 'tango' => 'Tango'], AirBriefService::inboxKeys(['HOG 21', 'hog-21', 'Pilote', 'Operateur', 'Tango', '', 'unknown']));
    }

    public function testRecipientsAreDeduplicatedByKey(): void
    {
        $list = AirBriefService::normalizeRecipients(['TIGER 6', 'tiger-6', 'Tiger6', 'HOG 21']);
        self::assertSame(['TIGER 6', 'HOG 21'], array_column($list, 'callsign'));
    }

    public function testInboxFindsTheBriefWhateverTheSpellingOfTheCallsign(): void
    {
        $id = $this->gameBrief('CAS-1-100', ['TIGER 6']);
        foreach (['TIGER6', 'tiger-6', ' Tiger  6 ', 'TIGER_6'] as $asked) {
            $box = $this->briefs->inbox(self::T, ['Pilote', $asked]);
            self::assertCount(1, $box, $asked);
            self::assertSame(['CAS-1-100', $id, 'TIGER 6', $asked === ' Tiger  6 ' ? 'Tiger  6' : $asked], [$box[0]['ref'], $box[0]['id'], $box[0]['recipient'], $box[0]['matched']]);
        }
        self::assertSame([], $this->briefs->inbox(self::T, ['TIGER 7', 'Pilote']), 'autre appareil');
        self::assertSame([], $this->briefs->inbox(self::OTHER, ['TIGER 6']), 'communauté étanche');
        self::assertSame([], $this->briefs->inbox(self::T, []));
    }

    public function testInboxMatchesAnyOfSeveralNamesAndKeepsTheNewestFirst(): void
    {
        $this->air('HOG 21', '[{"name":"Paul Durand","seat":"driver","player":true}]', 'Paul Durand');
        $a = $this->gameBrief('CAS-1-1', ['HOG 21']);
        $b = $this->gameBrief('CAS-1-2', ['VIPER 11']);
        $box = $this->briefs->inbox(self::T, ['VIPER-11', 'paul durand']);
        self::assertSame([$b, $a], array_column($box, 'id'), 'par indicatif et par nom d’équipage, le plus récent d’abord');
        self::assertSame(['VIPER-11', 'paul durand'], array_column($box, 'matched'));
    }

    public function testBriefAssignedToAnAircraftWithoutRecipientReachesThePilotAndIsDelivered(): void
    {
        $repo = new CasNineLineRepository($this->pdo);
        $row = $repo->createCas(self::T, 1, 'HAMMER 11', ['assigned_aircraft' => 'Hog 2-1, VIPER 11', 'line1' => 'IP DELTA', 'line6' => '0234 5678']);
        $box = $this->briefs->inbox(self::T, ['HOG21']);
        self::assertCount(1, $box);
        self::assertSame(['HOG 2-1', 0, 'sent'], [$box[0]['recipient'], $box[0]['recipient_id'], $box[0]['status']]);
        self::assertSame(1, $this->briefs->markDelivered(self::T, $box, strtotime('2026-11-16 10:00:00')));
        $rec = $this->briefs->forLines(self::T, [(int) $row['id']])[(int) $row['id']];
        self::assertSame('HOG 2-1', $rec[0]['callsign']);
        self::assertSame(['delivered', '2026-11-16 10:00:00', 'HOG21', '2026-11-16 10:00:00'], [
            $rec[0]['delivery']['status'], $rec[0]['delivery']['delivered_at'], $rec[0]['delivery']['pilot_callsign'], $rec[0]['delivery']['last_polled_at'],
        ]);
        self::assertCount(1, $this->briefs->inbox(self::T, ['HOG21']), 'toujours une seule demande (destinataire ajouté)');
    }

    public function testDeliveryMovesFromSentToDeliveredReadAndAcknowledged(): void
    {
        $id = $this->gameBrief('CAS-1-300', ['TIGER 6', 'HOG 21']);
        $show = fn (): array => array_column($this->briefs->forLines(self::T, [$id])[$id], 'delivery', 'callsign');
        self::assertSame('sent', $show()['TIGER 6']['status']);
        self::assertSame(['status' => 'sent', 'counts' => ['expired' => 0, 'sent' => 2, 'delivered' => 0, 'read' => 0, 'acknowledged' => 0], 'total' => 2, 'last_polled_at' => ''],
            AirBriefService::deliverySummary($this->briefs->forLines(self::T, [$id])[$id]));

        $box = $this->briefs->inbox(self::T, ['tiger6']);
        self::assertSame('sent', $box[0]['status'], 'la réponse du relevé garde l’état d’avant');
        $this->briefs->markDelivered(self::T, $box, strtotime('2026-11-16 10:00:00'));
        $this->briefs->markDelivered(self::T, $this->briefs->inbox(self::T, ['tiger6']), strtotime('2026-11-16 10:00:20'));
        $d = $show()['TIGER 6'];
        self::assertSame(['delivered', '2026-11-16 10:00:00', '2026-11-16 10:00:20', 'tiger6'], [$d['status'], $d['delivered_at'], $d['last_polled_at'], $d['pilot_callsign']], 'livré une fois, relevé suivi');
        self::assertSame('sent', $show()['HOG 21']['status'], 'un autre destinataire reste envoyé');

        self::assertSame('ok', $this->briefs->acknowledge(self::T, 'CAS-1-300', 'read', 'TIGER6')['result'], 'accusé sous une autre graphie');
        self::assertSame('read', $show()['TIGER 6']['status']);
        self::assertSame('read', $this->briefs->acknowledge(self::T, 'CAS-1-300', 'read', 'TIGER 6')['recipient']['delivery']['status'], 'lecture idempotente');
        $this->briefs->acknowledge(self::T, 'CAS-1-300', 'wilco', 'tiger-6');
        $d = $show()['TIGER 6'];
        self::assertSame(['acknowledged', 'wilco', 'TIGER-6'], [$d['status'], $d['answer'], $d['pilot_callsign']]);
        self::assertNotSame('', $d['acknowledged_at']);
        self::assertSame('acknowledged', $this->briefs->acknowledge(self::T, 'CAS-1-300', 'read', 'TIGER 6')['recipient']['delivery']['status'], 'jamais en arrière');
        $sum = AirBriefService::deliverySummary($this->briefs->forLines(self::T, [$id])[$id]);
        self::assertSame(['acknowledged', 1, 1], [$sum['status'], $sum['counts']['acknowledged'], $sum['counts']['sent']]);
        self::assertSame(2, count(array_filter($this->briefs->forLines(self::T, [$id])[$id], static fn (array $r): bool => isset($r['delivery']))), 'aucun destinataire ajouté par une autre graphie');
    }

    public function testNeverPolledBriefExpiresAfterTheInboxWindow(): void
    {
        $old = ['status' => 'sent', 'created_at' => '2026-11-16 06:00:00'];
        self::assertSame('expired', AirBriefService::delivery($old, strtotime('2026-11-16 09:30:00'))['status']);
        self::assertSame('sent', AirBriefService::delivery($old, strtotime('2026-11-16 08:00:00'))['status']);
        self::assertSame('delivered', AirBriefService::delivery(['status' => 'received', 'created_at' => '2026-11-16 06:00:00'], strtotime('2026-11-16 12:00:00'))['status']);
        self::assertSame(array_keys(AirBriefService::delivery([])), ['status', 'answer', 'delivered_at', 'read_at', 'acknowledged_at', 'pilot_callsign', 'last_polled_at']);
    }

    public function testUnknownCallsignIsLoggedDiscreetly(): void
    {
        $this->air('HOG 21', '[{"name":"Paul","seat":"driver","player":true}]', 'Paul');
        $this->gameBrief('CAS-1-400', ['hog-21', 'TIGRE 9']);
        self::assertCount(1, $this->logs, 'HOG 21 est connu sous une autre graphie, TIGRE 9 ne l’est pas');
        self::assertStringContainsString('« TIGRE 9 »', $this->logs[0]);
    }

    public function testApiInboxRouteAnswersTheExactSqfArrayAndMarksDelivery(): void
    {
        $id = $this->gameBrief('CAS-1-500', ['TIGER 6'], ['line1' => 'IP "NORD"', 'line6' => '0234|5678']);
        $body = $this->briefs->inboxResponse(self::T, '~air|Pilote|Tiger-6');
        $time = date('H:i', (int) strtotime((string) $this->pdo->query('SELECT created_at FROM atak_nine_line WHERE id = ' . $id)->fetchColumn()));
        $expected = '[["CAS-1-500",' . $id . ',"9line","HAMMER 11","' . $time . '",["IP ""NORD""","","","","","0234 5678","","",""],"","sent","TIGER 6"]]';
        self::assertSame($expected, $body, 'tableau SQF pour parseSimpleArray, guillemets doublés, « | » retiré');
        $row = $this->pdo->query('SELECT status, polled_as FROM atak_nine_line_recipients WHERE nine_line_id = ' . $id)->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['received', 'Tiger-6'], [$row['status'], $row['polled_as']], 'livré, sous le nom interrogé');
        self::assertSame('[]', $this->briefs->inboxResponse(self::T, '~air|VIPER 11'));
        self::assertSame('[]', $this->briefs->inboxResponse(self::T, '~air|'));

        // GET /api/cas : le préfixe ~air| passe par inboxResponse, en texte brut non mis en cache ; l'ancien filtre reste.
        $api = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php');
        $start = (int) strpos($api, 'public function casIndex');
        $fn = substr($api, $start, (int) strpos($api, 'public function casStore', $start) - $start);
        foreach (['AirBriefService::INBOX_PREFIX', '->inboxResponse($tenantId, $assignedTo)', "'text/plain; charset=UTF-8'", "'no-store'", '$this->casRepo->listCas('] as $needle) {
            self::assertStringContainsString($needle, $fn, $needle);
        }
    }

    public function testGameToServerContractThenPilotInbox(): void
    {
        // Corps envoyé par fn_airBrief "send" (SubmitNineLine → POST /api/cas) : destinataires par indicatif.
        $body = ['mapId' => 1, 'author' => 'HAMMER 11', 'jtac' => 'HAMMER 11', 'mission_kind' => 'CAS', 'format' => '9line', 'free' => false,
            'mission_id' => 'CAS-12345-987654321', 'remarks' => 'Menace SA-7', 'lines' => ['line1' => 'IP DELTA', 'line6' => '0234 5678'], 'recipients' => ['Tiger 6']];
        $repo = new CasNineLineRepository($this->pdo);
        $row = $repo->createCas(self::T, 1, 'HAMMER 11', $body);
        $this->briefs->addRecipients(self::T, (int) $row['id'], $body['recipients']);
        $box = $this->briefs->inbox(self::T, ['TIGER6', 'Paul']);
        self::assertCount(1, $box);
        self::assertSame(['CAS-12345-987654321', '9line', 'IP DELTA', '0234 5678', 'Menace SA-7', 'TIGER 6'], [$box[0]['ref'], $box[0]['format'], $box[0]['lines'][0], $box[0]['lines'][5], $box[0]['remarks'], $box[0]['recipient']]);

        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/tactical/fn_airBrief.sqf');
        foreach (['"mapId":%1', '"mission_id":"%6"', '"recipients":[%9]', '"lines":{%8}', 'SubmitNineLine', 'GetCASForCallsign', '"~air|%1"'] as $needle) {
            self::assertStringContainsString($needle, $src, $needle);
        }
    }

    public function testReadAndDeliveryRoutesAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("\$router->post('/api/cas/{id}/read', [AtakApiController::class, 'casRead']);", $routes);
        self::assertStringContainsString("\$router->get('/api/cas/{id}/delivery', [AtakApiController::class, 'casDelivery']);", $routes);

        $this->gameBrief('CAS-1-600', ['TIGER 6']);
        self::assertSame('read', $this->briefs->acknowledge(self::T, 'CAS-1-600', 'read', '')['recipient']['delivery']['status'], 'un seul destinataire : accusé sans indicatif');
        $json = $this->briefs->lineDelivery(self::T, 'CAS-1-600');
        self::assertNotNull($json);
        self::assertSame(['CAS-1-600', 'read'], [$json['ref'], $json['delivery']['status']]);
        self::assertSame('read', $json['recipients'][0]['delivery']['status']);
        self::assertNull($this->briefs->lineDelivery(self::T, 'CAS-inconnu'));
        self::assertNull($this->briefs->lineDelivery(self::OTHER, 'CAS-1-600'), 'communauté étanche');

        $api = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        foreach (['public function casRead', 'public function casDelivery', "->acknowledge(\$r, \$ref, 'read', \$by)", '->lineDelivery($r, $ref)', 'AirBriefService::normalizeLookup('] as $needle) {
            self::assertStringContainsString($needle, $api, $needle);
        }
    }

    public function testBoardHistoryCarriesTheDeliveryBadge(): void
    {
        $board = new NineLineBoardService($this->pdo, null, null, $this->briefs);
        $board->create(self::T, 5, 'HAMMER 11', 'cas', [1 => 'IP', 6 => '0234 5678'], ['TIGER 6'], 1);
        $this->briefs->markDelivered(self::T, $this->briefs->inbox(self::T, ['TIGER6']));
        $item = $board->history(self::T, 5, true)[0];
        self::assertSame('delivered', $item['delivery']['status']);
        self::assertSame('delivered', $item['recipients'][0]['delivery']['status']);
    }

    public function testMigrationStepAddsTheDeliveryColumnsOnceWithItsRollback(): void
    {
        $root = dirname(__DIR__, 2);
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE atak_nine_line_recipients (id INTEGER PRIMARY KEY AUTOINCREMENT, callsign TEXT NOT NULL)');
        ob_start();
        require $root . '/database/steps/4970_atak_air_brief_delivery.php';
        require $root . '/database/steps/4970_atak_air_brief_delivery.php';
        $out = (string) ob_get_clean();
        self::assertStringContainsString('polled_as, last_polled_at', $out);
        self::assertStringContainsString('déjà en place', $out);
        $cols = array_column($pdo->query('PRAGMA table_info(atak_nine_line_recipients)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        self::assertContains('polled_as', $cols);
        self::assertContains('last_polled_at', $cols);

        $registry = require $root . '/database/steps/registry.php';
        $mine = array_values(array_filter($registry, static fn (array $s): bool => $s['id'] === 'atak_air_brief_delivery'));
        self::assertCount(1, $mine);
        self::assertSame('4970_atak_air_brief_delivery.php', $mine[0]['file']);
        self::assertCount(1, array_filter($registry, static fn (array $s): bool => str_starts_with($s['file'], '4910_')), 'numéro unique');
        self::assertFileExists($root . '/migrations/rollback/atak_air_brief_delivery_down.sql');
        $schema = (string) file_get_contents($root . '/migrations/schema.sql');
        self::assertMatchesRegularExpression('/`polled_as` varchar\(64\) COLLATE utf8mb4_unicode_ci DEFAULT NULL,\R\s*`last_polled_at` datetime DEFAULT NULL,/', $schema);
    }

    /**
     * 9-LINE transmis par l'app JTAC (mission_id du jeu) avec ses destinataires.
     *
     * @param list<string> $recipients
     * @param array<string, string> $lines
     */
    private function gameBrief(string $missionId, array $recipients, array $lines = ['line1' => 'IP', 'line6' => '0101 0202']): int
    {
        $row = (new CasNineLineRepository($this->pdo))->createCas(self::T, 1, 'HAMMER 11', ['mission_id' => $missionId, 'lines' => $lines]);
        $this->briefs->addRecipients(self::T, (int) $row['id'], $recipients);

        return (int) $row['id'];
    }

    private function air(string $cs, string $crew, string $pilot): void
    {
        $this->pdo->prepare('INSERT INTO atak_air_assets (tenant_id, map_id, callsign, model, aircraft_type, pos_x, pos_y, alt, heading, status, pilot, crew, updated_at)
            VALUES (?, 1, ?, ?, ?, 1000, 2000, 300, 92, ?, ?, ?, ?)')
            ->execute([self::T, $cs, 'A-10C', 'plane', 'IN-FLIGHT', $pilot, $crew, date('Y-m-d H:i:s')]);
    }

    private static function schema(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $pdo->exec("CREATE TABLE atak_nine_line (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, map_id INTEGER NOT NULL DEFAULT 1,
            mission_kind TEXT NOT NULL DEFAULT 'cas', mission_id TEXT, author TEXT NOT NULL, author_user_id INTEGER, assigned_aircraft TEXT,
            line1 TEXT, line2 TEXT, line3 TEXT, line4 TEXT, line5 TEXT, line6 TEXT, line7 TEXT, line8 TEXT, line9 TEXT, remarks TEXT,
            lines_checked TEXT, geo_json TEXT, status TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT)");
        $pdo->exec("CREATE TABLE atak_nine_line_recipients (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, nine_line_id INTEGER NOT NULL,
            callsign TEXT NOT NULL, label TEXT, crew_names TEXT, status TEXT NOT NULL DEFAULT 'sent', received_at TEXT, read_at TEXT, ack_at TEXT,
            ack_by TEXT, polled_as TEXT, last_polled_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT, UNIQUE (nine_line_id, callsign))");
        $pdo->exec('CREATE TABLE atak_brief_callsigns (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, scope TEXT NOT NULL,
            owner_key TEXT NOT NULL, callsign TEXT NOT NULL, updated_by_user_id INTEGER, updated_at TEXT, UNIQUE (tenant_id, scope, owner_key))');
        $pdo->exec('CREATE TABLE atak_maps (id INTEGER PRIMARY KEY, label TEXT)');
        $pdo->exec("INSERT INTO atak_maps (id, label) VALUES (1, 'Altis')");
        $pdo->exec('CREATE TABLE atak_air_assets (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, map_id INTEGER, callsign TEXT, model TEXT,
            aircraft_type TEXT, freq TEXT, laser TEXT, pos_x REAL, pos_y REAL, alt REAL, heading REAL, side TEXT, status TEXT, pilot TEXT, crew TEXT,
            fuel_pct INTEGER, updated_at TEXT)');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, display_name TEXT, callsign TEXT)');
        $pdo->exec('CREATE TABLE user_profiles (user_id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT)');
        $pdo->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, action TEXT, entity_type TEXT,
            entity_id INTEGER, old_value TEXT, new_value TEXT, ip TEXT, user_agent TEXT, created_at TEXT)');

        return $pdo;
    }
}
