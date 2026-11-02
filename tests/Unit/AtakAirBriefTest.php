<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AtakNineLineViewController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\CasNineLineRepository;
use App\Services\Personnel\MemberNameService;
use App\Services\Tactical\AirBriefService;
use App\Services\Tactical\NineLineBoardService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * 9-LINE / 5-LINE envoyés au pilote : 5-LINE (lignes 1 à 5), saisie libre, destinataires (équipage repris des
 * appareils remontés), boîte du pilote (tableau SQF pour parseSimpleArray), accusés reçu / lu / WILCO / UNABLE (jamais
 * en arrière, idempotents, ref du jeu ou identifiant Athena), statut dans l'historique, indicatifs JTAC et appareil,
 * communauté étanche, câblage (routes, API, migration 3720). Dépôt réel sur sqlite.
 */
final class AtakAirBriefTest extends TestCase
{
    private const OWN = 7;

    private const FOREIGN = 3;

    private const AUTHOR = 5;

    private PDO $pdo;

    private NineLineBoardService $board;

    private AirBriefService $briefs;

    private ?PDO $savedPdo = null;

    private bool $savedSessionStarted = false;

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
        $this->savedSessionStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $this->savedSession = ['set' => isset($_SESSION), 'value' => $_SESSION ?? null];
        $_SESSION = [];
        MemberNameService::forgetSchema();
        $this->briefs = new AirBriefService($this->pdo);
        $this->board = new NineLineBoardService($this->pdo, null, null, $this->briefs);
    }

    protected function tearDown(): void
    {
        MemberNameService::forgetSchema();
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedSessionStarted);
        if ($this->savedSession['set']) {
            $_SESSION = $this->savedSession['value'];
        } else {
            unset($_SESSION);
        }
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        parent::tearDown();
    }

    public function testFiveLineKeepsFiveLinesAndItsOwnRequiredLines(): void
    {
        self::assertSame(CasNineLineRepository::KIND_FIVE, CasNineLineRepository::normalizeKind('5LINE'));
        self::assertSame(CasNineLineRepository::KIND_FIVE, CasNineLineRepository::normalizeKind('5-line'));
        self::assertSame(CasNineLineRepository::KIND_CAS, CasNineLineRepository::normalizeKind('CAS'));
        $errors = NineLineBoardService::validate('fiveline', [1 => 'HAMMER 11, 5-LINE']);
        self::assertSame(['required', 'required', 'required'], [$errors[2] ?? '', $errors[3] ?? '', $errors[4] ?? '']);
        self::assertArrayNotHasKey(6, NineLineBoardService::validate('fiveline', [2 => 'a', 3 => 'Nord 400 m', 4 => 'b']), 'pas de grille obligatoire en 5-LINE');

        $row = $this->board->create(self::OWN, self::AUTHOR, 'HAMMER 11', 'fiveline', self::five() + [6 => 'ignorée', 9 => 'ignorée'], ['HOG 21'], 1);
        self::assertSame('fiveline', $this->col((int) $row['id'], 'mission_kind'));
        self::assertSame('Nord 400 m de mon marquage', $this->col((int) $row['id'], 'line3'));
        self::assertSame('', (string) $this->col((int) $row['id'], 'line6'), 'lignes 6 à 9 vides en 5-LINE');
        $item = $this->board->history(self::OWN, self::AUTHOR, false)[0];
        self::assertSame('fiveline', $item['mission_kind']);
        self::assertSame('5line', $item['format']);
    }

    public function testFreeEntryOnlyBlocksOnLength(): void
    {
        self::assertNotSame([], NineLineBoardService::validate('cas', [6 => 'pas une grille']));
        self::assertSame([], NineLineBoardService::validate('cas', [6 => 'pas une grille', 2 => '999'], true), 'saisie libre : ni obligatoires, ni grille, ni cap');
        self::assertSame([3 => 'too_long'], NineLineBoardService::validate('cas', [3 => str_repeat('x', 300)], true));
    }

    public function testRecipientsTakeTheCrewFromReportedAircraftAndTheSiteLabel(): void
    {
        $this->air(self::OWN, 'HOG 21', date('Y-m-d H:i:s'), '[{"name":"Tango","seat":"driver","player":true},{"name":"Bravo","seat":"gunner","player":false}]', 'Tango');
        $this->briefs->setAircraftCallsign(self::OWN, 'HOG 21', 'hog 2-1', self::AUTHOR);
        $row = $this->board->create(self::OWN, self::AUTHOR, 'HAMMER 11', 'cas', self::lines(), ['HOG 21', 'hog 21', 'VIPER 11'], 1, 'FAH 270 ± 20');
        $rec = $row['recipients'];
        self::assertCount(2, $rec, 'dédoublonnés sans tenir compte de la casse');
        self::assertSame(['HOG 21', 'HOG 2-1', 'sent'], [$rec[0]['callsign'], $rec[0]['label'], $rec[0]['status']]);
        self::assertSame(['Tango', 'Bravo'], $rec[0]['crew']);
        self::assertSame('VIPER 11', $rec[1]['label']);
        self::assertSame('FAH 270 ± 20', $this->col((int) $row['id'], 'remarks'));

        $ac = $this->board->aircraft(self::OWN);
        self::assertSame('HOG 2-1', $ac[0]['label'], 'indicatif choisi sur le site');
        self::assertSame('HOG 21', $ac[0]['callsign'], 'indicatif du jeu gardé pour joindre l’appareil');
        self::assertTrue($ac[0]['has_player']);
    }

    public function testPilotInboxFindsTheBriefByAircraftCallsignOrCrewNameAsAnSqfArray(): void
    {
        $this->air(self::OWN, 'HOG 21', date('Y-m-d H:i:s'), '[{"name":"Tango","seat":"driver","player":true}]', 'Tango');
        $row = $this->board->create(self::OWN, self::AUTHOR, 'HAMMER 11', 'cas', array_replace(self::lines(), [5 => 'Mitrailleuse "lourde" | toit']), ['HOG 21'], 1, 'Menace SA-7');
        $this->board->create(self::FOREIGN, 0, 'AUTRE', 'cas', self::lines(), ['HOG 21'], 1);

        $byCrew = $this->briefs->inbox(self::OWN, ['Tango']);
        self::assertCount(1, $byCrew, 'reconnu par le nom de jeu du pilote, communauté étanche');
        self::assertSame((string) $row['id'], $byCrew[0]['ref']);
        self::assertSame(['9line', 'HAMMER 11', 'HOG 21', 'sent'], [$byCrew[0]['format'], $byCrew[0]['from'], $byCrew[0]['recipient'], $byCrew[0]['status']]);
        self::assertSame('Menace SA-7', $byCrew[0]['remarks']);
        self::assertCount(1, $this->briefs->inbox(self::OWN, ['hog 21']), 'reconnu par l’indicatif de l’appareil');
        self::assertSame([], $this->briefs->inbox(self::OWN, ['VIPER 11', 'Operateur']));

        $sqf = AirBriefService::inboxSqf($byCrew);
        self::assertStringStartsWith('[["', $sqf);
        self::assertStringContainsString('Mitrailleuse ""lourde"" toit', $sqf, 'guillemets doublés, « | » retiré');
        self::assertSame('[]', AirBriefService::inboxSqf([]));
        $many = array_fill(0, 6, $byCrew[0] + ['lines' => array_fill(0, 9, str_repeat('x', 140))]);
        self::assertLessThanOrEqual(AirBriefService::INBOX_MAX_BYTES, strlen(AirBriefService::inboxSqf($many)));
    }

    public function testApiInboxAnswersWithTheSqfArrayAndKeepsTheLegacyFilter(): void
    {
        $api = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString('AirBriefService::INBOX_PREFIX', $api);
        self::assertStringContainsString('AirBriefService::parseAck($status)', $api);
        self::assertStringContainsString("createFive(\$tenantId, \$mapId", $api);
        self::assertSame('~air|', AirBriefService::INBOX_PREFIX);
    }

    public function testAcknowledgementsMoveForwardOnlyAndAreIdempotent(): void
    {
        $row = $this->pdoInsertGameBrief('B7656-1234', ['HOG 21', 'VIPER 11']);
        self::assertSame(['received', 'HOG 21'], AirBriefService::parseAck('RECEIVED:HOG 21'));
        self::assertSame(['wilco', 'HOG 21'], AirBriefService::parseAck('wilco : hog 21'));
        self::assertNull(AirBriefService::parseAck('CLEARED_HOT'));

        self::assertSame('ok', $this->briefs->acknowledge(self::OWN, 'B7656-1234', 'read', 'HOG 21')['result']);
        $back = $this->briefs->acknowledge(self::OWN, 'B7656-1234', 'received', 'HOG 21');
        self::assertSame('read', $back['recipient']['status'], 'un accusé « reçu » tardif ne fait pas reculer');
        $w = $this->briefs->acknowledge(self::OWN, 'B7656-1234', 'wilco', 'HOG 21');
        self::assertSame(['wilco', 'HOG 21'], [$w['recipient']['status'], $w['recipient']['ack_by']]);
        self::assertNotSame('', $w['recipient']['read_at']);
        self::assertSame('wilco', $this->briefs->acknowledge(self::OWN, 'B7656-1234', 'wilco', 'HOG 21')['recipient']['status'], 'doublon sans effet');
        self::assertSame('unable', $this->briefs->acknowledge(self::OWN, 'B7656-1234', 'unable', 'HOG 21')['recipient']['status'], 'WILCO et UNABLE se remplacent');

        $new = $this->briefs->acknowledge(self::OWN, (string) $row, 'wilco', 'DUDE 01');
        self::assertSame('DUDE 01', $new['recipient']['callsign'], 'accusé par l’identifiant Athena, destinataire inconnu ajouté');
        self::assertSame('not_found', $this->briefs->acknowledge(self::FOREIGN, 'B7656-1234', 'wilco', 'HOG 21')['result'], 'communauté étanche');
        self::assertSame('not_found', $this->briefs->acknowledge(self::OWN, 'B-inconnu', 'wilco', 'HOG 21')['result']);

        $hist = $this->board->history(self::OWN, self::AUTHOR, false)[0];
        $st = array_column($hist['recipients'], 'status', 'callsign');
        self::assertSame(['HOG 21' => 'unable', 'VIPER 11' => 'sent', 'DUDE 01' => 'wilco'], $st, 'statut de chaque destinataire dans l’historique');
    }

    public function testDeletingABriefDropsItsRecipients(): void
    {
        $row = $this->board->create(self::OWN, self::AUTHOR, 'HAMMER 11', 'cas', self::lines(), ['HOG 21'], 1);
        $this->board->delete(self::OWN, (int) $row['id'], self::AUTHOR, false);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_nine_line_recipients')->fetchColumn());
        $this->board->create(self::OWN, self::AUTHOR, 'HAMMER 11', 'cas', self::lines(), ['HOG 21'], 1);
        $this->board->clear(self::OWN);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_nine_line_recipients')->fetchColumn());
    }

    public function testJtacCallsignIsUsedAsAuthorAndNeedsTheToken(): void
    {
        $controller = new AtakNineLineViewController($this->board, $this->briefs);
        $_SESSION = ['tenant_id' => self::OWN, 'user_id' => self::AUTHOR, '_csrf_token' => 'jeton'];
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'autre';
        self::assertSame(419, $controller->jtacCallsign(new Request())->statusCode());
        self::assertSame(419, $controller->aircraftCallsign(new Request())->statusCode());

        self::assertTrue($this->briefs->setJtacCallsign(self::OWN, self::AUTHOR, ' hammer   11 '));
        self::assertSame('HAMMER 11', $this->briefs->jtacCallsign(self::OWN, self::AUTHOR));
        self::assertNull($this->briefs->jtacCallsign(self::FOREIGN, self::AUTHOR), 'par communauté');
        self::assertTrue($this->briefs->setJtacCallsign(self::OWN, self::AUTHOR, ''));
        self::assertNull($this->briefs->jtacCallsign(self::OWN, self::AUTHOR), 'vide : retour à l’indicatif Athena');
        self::assertSame('HOG 21', AirBriefService::cleanCallsign("<b>hog</b>\t21"));
    }

    public function testRoutesAndMigrationAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $c = preg_quote('[\\App\\Controllers\\Web\\AtakNineLineViewController::class, ', '#');
        foreach ([
            "post\\('/9-line/indicatif', " . $c . "'jtacCallsign'\\], \\[AuthMiddleware::class\\]",
            "post\\('/9-line/appareils/indicatif', " . $c . "'aircraftCallsign'\\], \\[AuthMiddleware::class\\]",
        ] as $re) {
            self::assertMatchesRegularExpression('#' . $re . '#', $routes);
        }
        $registry = require $root . '/database/steps/registry.php';
        $mine = array_values(array_filter($registry, static fn (array $s): bool => $s['id'] === 'atak_nine_line_recipients'));
        self::assertCount(1, $mine);
        self::assertSame('3720_atak_nine_line_recipients.php', $mine[0]['file']);
        self::assertCount(1, array_filter($registry, static fn (array $s): bool => str_starts_with($s['file'], '3720_')), 'numéro unique');
        $step = (string) file_get_contents($root . '/database/steps/' . $mine[0]['file']);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS atak_nine_line_recipients', $step);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS atak_brief_callsigns', $step);
        self::assertStringContainsString("addColumnIfMissing('atak_nine_line', 'remarks'", $step);
        $schema = (string) file_get_contents($root . '/migrations/schema.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `atak_nine_line_recipients`', $schema);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `atak_brief_callsigns`', $schema);
        $js = (string) file_get_contents($root . '/public/assets/js/atak-nine-line.js');
        foreach (["'/9-line/indicatif'", "'/9-line/appareils/indicatif'", 'PLAYERS_KEY', "fd.delete('free')", 'fiveline'] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
    }

    /** @return array<int, string> */
    private static function lines(): array
    {
        return [1 => 'IP DELTA', 2 => '245° M', 3 => '4.2 NM', 4 => '450 ft MSL', 5 => '2 BTR', 6 => '0234 5678', 7 => 'Laser 1688', 8 => 'Sud 600 m', 9 => 'Sortie nord'];
    }

    /** @return array<int, string> */
    private static function five(): array
    {
        return [1 => 'HAMMER 11, 5-LINE', 2 => '0231 5642, panneau VS-17', 3 => 'Nord 400 m de mon marquage', 4 => 'Mitrailleuse, fumigène rouge', 5 => 'FAH 270, DANGER CLOSE'];
    }

    /** 9-LINE transmis par l'app JTAC (mission_id du jeu) avec ses destinataires. */
    private function pdoInsertGameBrief(string $missionId, array $recipients): int
    {
        $repo = new CasNineLineRepository($this->pdo);
        $row = $repo->createCas(self::OWN, 1, 'HAMMER 11', ['mission_id' => $missionId, 'lines' => ['line1' => 'IP', 'line6' => '0101 0202']]);
        $this->briefs->addRecipients(self::OWN, (int) $row['id'], $recipients);

        return (int) $row['id'];
    }

    private function air(int $tenantId, string $cs, string $updated, ?string $crew, ?string $pilot = null): void
    {
        $this->pdo->prepare('INSERT INTO atak_air_assets (tenant_id, map_id, callsign, model, aircraft_type, pos_x, pos_y, alt, heading, status, pilot, crew, updated_at)
            VALUES (?, 1, ?, ?, ?, 1000, 2000, 300, 92, ?, ?, ?, ?)')
            ->execute([$tenantId, $cs, 'A-10C', 'plane', 'IN-FLIGHT', $pilot, $crew, $updated]);
    }

    private function col(int $id, string $col): mixed
    {
        $v = $this->pdo->query('SELECT ' . $col . ' FROM atak_nine_line WHERE id = ' . $id)->fetchColumn();

        return $v === false ? null : $v;
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
            ack_by TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT, UNIQUE (nine_line_id, callsign))");
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
        $pdo->exec("INSERT INTO users (id, tenant_id, display_name, callsign) VALUES (5, 7, 'paul_d', 'Viper 1')");

        return $pdo;
    }
}
