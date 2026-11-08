<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AtakNineLineViewController;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Services\Personnel\MemberNameService;
use App\Services\Tactical\NineLineBoardService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Page /9-line : suppression (auteur ou responsable, communauté de la session), vidage de l'historique, liste des
 * aéronefs remontés par le jeu (pilote et équipage reconnus parmi les membres), contrôle des lignes, câblage
 * (routes, vue au thème du questionnaire, script, migration). Dépôt réel sur sqlite.
 */
final class AtakNineLineBoardTest extends TestCase
{
    private const OWN = 7;

    private const FOREIGN = 3;

    private const AUTHOR = 5;

    private const OTHER = 6;

    private PDO $pdo;

    private NineLineBoardService $board;

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
        $this->board = new NineLineBoardService($this->pdo);
    }

    protected function tearDown(): void
    {
        Gate::reset();
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

    public function testAuthorDeletesOwnNineLineButNotSomeoneElses(): void
    {
        $row = $this->board->create(self::OWN, self::AUTHOR, 'VIPER 1', 'cas', self::lines(), ['HAWG 1', 'HAWG 2', 'HAWG 1'], 2);
        $id = (int) $row['id'];
        self::assertSame(self::AUTHOR, (int) $this->col($id, 'author_user_id'));
        self::assertSame('HAWG 1, HAWG 2', $this->col($id, 'assigned_aircraft'), 'destinataires sans doublon');
        self::assertSame('2', (string) $this->col($id, 'map_id'));

        self::assertSame('forbidden', $this->board->delete(self::OWN, $id, self::OTHER, false)['result']);
        self::assertSame(1, $this->stored(), 'un autre membre ne supprime pas le 9-LINE');

        self::assertSame('ok', $this->board->delete(self::OWN, $id, self::AUTHOR, false)['result']);
        self::assertSame(0, $this->stored());
        self::assertSame('not_found', $this->board->delete(self::OWN, $id, self::AUTHOR, false)['result']);
    }

    public function testGameNineLineIsDeletedByAManagerOnly(): void
    {
        $id = $this->insertGameNineLine(self::OWN, 1);

        self::assertSame('forbidden', $this->board->delete(self::OWN, $id, self::AUTHOR, false)['result'], 'sans auteur Athena : responsable seulement');
        self::assertSame('ok', $this->board->delete(self::OWN, $id, self::AUTHOR, true)['result']);
        self::assertSame(0, $this->stored());
    }

    public function testDeleteAndClearNeverReachAnotherCommunity(): void
    {
        $foreign = $this->insertGameNineLine(self::FOREIGN, 1);
        $ownMap1 = $this->insertGameNineLine(self::OWN, 1);
        $ownMap2 = $this->insertGameNineLine(self::OWN, 2);

        self::assertSame('not_found', $this->board->delete(self::OWN, $foreign, self::AUTHOR, true)['result']);
        self::assertSame(3, $this->stored());
        self::assertSame([], array_filter($this->board->history(self::OWN, self::AUTHOR, true), static fn (array $i): bool => $i['id'] === $foreign));

        self::assertSame(1, $this->board->clear(self::OWN, 2), 'vidage d’une seule carte');
        self::assertNull($this->col($ownMap2, 'id'));
        self::assertNotNull($this->col($ownMap1, 'id'));

        self::assertSame(1, $this->board->clear(self::OWN));
        self::assertSame(1, $this->stored(), 'le 9-LINE de l’autre communauté reste');
        self::assertNotNull($this->col($foreign, 'id'));
    }

    public function testHistoryFlagsWhatTheMemberMayDelete(): void
    {
        $mine = (int) $this->board->create(self::OWN, self::AUTHOR, 'VIPER 1', 'cas', self::lines(), [], 1)['id'];
        $game = $this->insertGameNineLine(self::OWN, 1);

        $byId = [];
        foreach ($this->board->history(self::OWN, self::AUTHOR, false) as $item) {
            $byId[$item['id']] = $item;
        }
        self::assertTrue($byId[$mine]['can_delete']);
        self::assertTrue($byId[$mine]['from_site']);
        self::assertFalse($byId[$game]['can_delete']);
        self::assertSame('Altis', $byId[$game]['map_name']);
        foreach ($this->board->history(self::OWN, self::OTHER, true) as $item) {
            self::assertTrue($item['can_delete'], 'un responsable supprime tout');
        }
        self::assertSame([['id' => 1, 'label' => 'Altis', 'count' => 2]], $this->board->historyMaps(self::OWN));
    }

    public function testAircraftShowPilotAndCrewWithMemberNamesAndFreshness(): void
    {
        $now = strtotime('2026-11-02 20:00:00');
        $crew = json_encode([
            ['name' => 'Viper 1', 'seat' => 'driver', 'role' => 'Pilote', 'player' => true],
            ['name' => 'jean martin', 'seat' => 'gunner', 'role' => '', 'player' => true],
            ['name' => 'Alpha 1-1 (3)', 'seat' => 'cargo', 'role' => '', 'player' => false],
        ]);
        $this->air(self::OWN, 'HAWG 1', '2026-11-02 19:59:50', $crew, 1250.0, 800.4);
        $this->air(self::OWN, 'DUSTOFF', '2026-11-02 19:58:00', null, 100.0, 15.0, 'Kowalski');
        $this->air(self::OWN, 'OLD', '2026-11-02 19:40:00', null, 0.0, 0.0);
        $this->air(self::FOREIGN, 'ENEMY', '2026-11-02 19:59:59', null, 0.0, 0.0);

        $list = $this->board->aircraft(self::OWN, $now);
        self::assertSame(['HAWG 1', 'DUSTOFF'], array_column($list, 'callsign'), 'récents d’abord, ni ancien ni autre communauté');

        $hawg = $list[0];
        self::assertTrue($hawg['live']);
        self::assertSame(10, $hawg['age_s']);
        self::assertSame('0125 0080', $hawg['grid']);
        self::assertSame(800, $hawg['alt_m']);
        self::assertSame(2626, $hawg['alt_ft']);
        self::assertSame('Viper 1', $hawg['pilot']['label'], 'C2 : l’indicatif d’abord');
        self::assertSame('Paul D.', $hawg['pilot']['name'], 'puis « Prénom N. »');
        self::assertTrue($hawg['pilot']['linked']);
        self::assertSame(3, $hawg['crew_count']);
        self::assertSame('Jean M.', $hawg['crew'][1]['label'], 'membre sans indicatif reconnu par son pseudo');
        self::assertSame('gunner', $hawg['crew'][1]['seat']);
        self::assertFalse($hawg['crew'][2]['linked']);
        self::assertSame('Alpha 1-1 (3)', $hawg['crew'][2]['label']);

        $dustoff = $list[1];
        self::assertFalse($dustoff['live'], 'plus de 30 s : signal perdu');
        self::assertSame('Kowalski', $dustoff['pilot']['label'], 'pilote envoyé seul, sans équipage');
        self::assertFalse($dustoff['pilot']['linked']);

        self::assertSame([], $this->board->aircraft(99, $now), 'aucune donnée : liste vide');
    }

    public function testCrewAndAuthorCarryWhatTheFileSays(): void
    {
        $this->pdo->exec('CREATE TABLE personnel_job_roles (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT, slug TEXT)');
        $this->pdo->exec('CREATE TABLE personnel_profile_job_roles (id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER, personnel_job_role_id INTEGER)');
        $this->pdo->exec("INSERT INTO personnel_job_roles (id, tenant_id, name, slug) VALUES (1, 7, 'Pilote hélicoptère', 'pil'), (2, 7, 'JTAC', 'jtac')");
        $this->pdo->exec('INSERT INTO personnel_profile_job_roles (tenant_id, user_id, personnel_job_role_id) VALUES (7, 5, 1), (7, 6, 2)');
        $now = strtotime('2026-11-02 20:00:00');
        $crew = json_encode([
            ['name' => 'Viper 1', 'seat' => 'driver', 'role' => '', 'player' => true],
            ['name' => 'jean martin', 'seat' => 'gunner', 'role' => '', 'player' => true],
        ]);
        $this->air(self::OWN, 'HAWG 1', '2026-11-02 19:59:50', $crew, 1250.0, 800.4);
        $hawg = $this->board->aircraft(self::OWN, $now)[0];
        self::assertSame('role', $hawg['pilot']['spec_pilot']['status']);
        self::assertSame('Pilote hélicoptère', $hawg['pilot']['spec_pilot']['label']);
        self::assertNull($hawg['pilot']['spec_jtac']);
        self::assertSame('JTAC', $hawg['crew'][1]['spec_jtac']['label']);
    }

    public function testLinesAreCheckedOneByOne(): void
    {
        self::assertSame([4 => 'required', 5 => 'required', 6 => 'required'], NineLineBoardService::validate('cas', []));
        self::assertSame([], NineLineBoardService::validate('cas', self::lines()));
        self::assertSame([6 => 'grid'], NineLineBoardService::validate('cas', [6 => '12345'] + self::lines()));
        self::assertSame([2 => 'heading'], NineLineBoardService::validate('cas', [2 => '420° M'] + self::lines()));
        self::assertSame([9 => 'too_long'], NineLineBoardService::validate('cas', [9 => str_repeat('x', 1001)] + self::lines()));
        self::assertSame([1 => 'required', 2 => 'required', 3 => 'required'], NineLineBoardService::validate('medevac', [4 => 'Aucun']));
        foreach (['0234 5678', '35T LF 0234 5678', '02345678', '0123456789', '012 345'] as $ok) {
            self::assertTrue(NineLineBoardService::looksLikeGrid($ok), $ok);
        }
        foreach (['', '123', '0234 567', 'LF'] as $bad) {
            self::assertFalse(NineLineBoardService::looksLikeGrid($bad), $bad);
        }
    }

    public function testWritesNeedASessionAndTheCsrfToken(): void
    {
        $controller = new AtakNineLineViewController($this->board);
        self::assertSame(401, $controller->store(new Request())->statusCode());

        $_SESSION = ['tenant_id' => self::OWN, 'user_id' => self::AUTHOR, '_csrf_token' => 'jeton'];
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'autre';
        $id = $this->insertGameNineLine(self::OWN, 1);
        self::assertSame(419, $controller->store(new Request())->statusCode());
        self::assertSame(419, $controller->delete(new Request(), ['id' => (string) $id])->statusCode());
        self::assertSame(419, $controller->clear(new Request())->statusCode());
        self::assertSame(1, $this->stored(), 'rien n’est supprimé sans jeton');
    }

    public function testRoutesAreWired(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $c = preg_quote('[\\App\\Controllers\\Web\\AtakNineLineViewController::class, ', '#');
        foreach ([
            "get\\('/9-line/appareils', " . $c . "'aircraft'\\], \\[AuthMiddleware::class\\]",
            "post\\('/9-line', " . $c . "'store'\\], \\[AuthMiddleware::class\\]",
            "post\\('/9-line/vider', " . $c . "'clear'\\], \\[AuthMiddleware::class\\]",
            "post\\('/9-line/\\{id\\}/supprimer', " . $c . "'delete'\\], \\[AuthMiddleware::class\\]",
        ] as $re) {
            self::assertMatchesRegularExpression('#' . $re . '#', $routes);
        }
        foreach (['aircraft', 'store', 'clear', 'delete', 'feed'] as $method) {
            self::assertTrue(method_exists(AtakNineLineViewController::class, $method), $method);
        }
    }

    public function testViewFollowsTheMockupWithAMinimalHeader(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/atak/nine_line_view.php');
        self::assertStringNotContainsString('community-enlistment.css', $view);
        self::assertStringNotContainsString('back-office-shell.css', $view, 'plus le thème du back-office');
        self::assertStringNotContainsString('fonts.googleapis', $view, 'aucune dépendance externe');
        self::assertStringContainsString('atak-nine-line.css', $view);
        self::assertStringContainsString('class="nl-page"', $view);
        self::assertStringContainsString('Csrf::token()', $view);
        foreach (['data-kind="cas"', 'data-kind="fiveline"', 'data-kind="medevac"', 'id="nl-free"', 'id="nl-players-only"', 'id="nl-manual-cs"', 'id="nl-jtac-cs"', 'id="nl-history"', 'id="nl-clear"', 'id="nl-submit"', 'form="nl-form"'] as $needle) {
            self::assertStringContainsString($needle, $view, $needle);
        }
        // Barre du haut réduite : marque, titre, langue, compte ; aucun lien de navigation publique.
        foreach (["url('communities')", "url('sse')", "url('atak-natif')", "url('wiki/atak')", "url('a-propos')", 'home_topbar'] as $nav) {
            self::assertStringNotContainsString($nav, $view, $nav);
        }
        self::assertStringContainsString('locale_switch_url', $view);
        self::assertStringContainsString("url('account')", $view);
        $css = (string) file_get_contents($root . '/public/assets/css/atak-nine-line.css');
        foreach (['../fonts/nine-line/inter-latin.woff2', '../fonts/nine-line/jetbrains-mono-latin.woff2', '../images/night-team.jpg', '--accent: #34d399'] as $needle) {
            self::assertStringContainsString($needle, $css, $needle);
        }
        foreach (['inter-latin.woff2', 'inter-latin-italic-900.woff2', 'jetbrains-mono-latin.woff2'] as $font) {
            self::assertFileExists($root . '/public/assets/fonts/nine-line/' . $font);
        }
        self::assertFileExists($root . '/public/assets/images/night-team.jpg');
        self::assertStringContainsString("url('9-line')", (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php'), 'entrée 9-LINE sous ATAK');
    }

    public function testScriptCallsTheEndpointsWithTheToken(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-nine-line.js');
        foreach (["'/9-line/appareils'", "'/9-line/feed'", "'/9-line/vider'", "'/supprimer'", "post('/9-line', fd)", "'X-CSRF-Token'", "'aircraft[]'"] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        self::assertStringContainsString('Aucun appareil remonté par le jeu', $js, 'état vide explicite');
    }

    public function testMigrationIsRegistered(): void
    {
        $root = dirname(__DIR__, 2);
        $registry = require $root . '/database/steps/registry.php';
        $mine = array_values(array_filter($registry, static fn (array $s): bool => $s['id'] === 'atak_nine_line_author_user'));
        self::assertCount(1, $mine);
        self::assertSame('3675_atak_nine_line_author_user.php', $mine[0]['file']);
        self::assertFileExists($root . '/database/steps/' . $mine[0]['file']);
        self::assertCount(1, array_filter($registry, static fn (array $s): bool => str_starts_with($s['file'], '3675_')), 'numéro unique');
        self::assertStringContainsString("addColumnIfMissing('atak_nine_line', 'author_user_id'", (string) file_get_contents($root . '/database/steps/' . $mine[0]['file']));
    }

    /** @return array<int, string> */
    private static function lines(): array
    {
        return [1 => 'IP DELTA', 2 => '245° M', 3 => '4.2 NM', 4 => '450 ft MSL', 5 => '2 BTR', 6 => '0234 5678', 7 => 'Laser 1688', 8 => 'Sud 600 m', 9 => 'Sortie nord'];
    }

    private function insertGameNineLine(int $tenantId, int $mapId): int
    {
        $this->pdo->prepare("INSERT INTO atak_nine_line (tenant_id, map_id, author, line1, line6, status, created_at) VALUES (?, ?, 'JTAC jeu', 'IP', '0101 0202', 'SUBMITTED', '2026-11-02 19:00:00')")
            ->execute([$tenantId, $mapId]);

        return (int) $this->pdo->lastInsertId();
    }

    private function air(int $tenantId, string $cs, string $updated, ?string $crew, float $x, float $alt, ?string $pilot = null): void
    {
        $this->pdo->prepare('INSERT INTO atak_air_assets (tenant_id, map_id, callsign, model, aircraft_type, pos_x, pos_y, alt, heading, status, pilot, crew, updated_at)
            VALUES (?, 1, ?, ?, ?, ?, ?, ?, 92, ?, ?, ?, ?)')
            ->execute([$tenantId, $cs, 'A-10C', 'plane', $x, 805.0, $alt, 'IN-FLIGHT', $pilot, $crew, $updated]);
    }

    private function stored(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM atak_nine_line')->fetchColumn();
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
            line1 TEXT, line2 TEXT, line3 TEXT, line4 TEXT, line5 TEXT, line6 TEXT, line7 TEXT, line8 TEXT, line9 TEXT,
            lines_checked TEXT, geo_json TEXT, status TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT)");
        $pdo->exec('CREATE TABLE atak_maps (id INTEGER PRIMARY KEY, label TEXT)');
        $pdo->exec("INSERT INTO atak_maps (id, label) VALUES (1, 'Altis'), (2, 'Malden')");
        $pdo->exec('CREATE TABLE atak_air_assets (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, map_id INTEGER, callsign TEXT, model TEXT,
            aircraft_type TEXT, freq TEXT, laser TEXT, pos_x REAL, pos_y REAL, alt REAL, heading REAL, side TEXT, status TEXT, pilot TEXT, crew TEXT,
            fuel_pct INTEGER, updated_at TEXT)');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, display_name TEXT, callsign TEXT)');
        $pdo->exec('CREATE TABLE user_profiles (user_id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT)');
        $pdo->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, action TEXT, entity_type TEXT,
            entity_id INTEGER, old_value TEXT, new_value TEXT, ip TEXT, user_agent TEXT, created_at TEXT)');
        $pdo->exec("INSERT INTO users (id, tenant_id, display_name, callsign) VALUES
            (5, 7, 'paul_d', 'Viper 1'), (6, 7, 'Jean Martin', NULL), (8, 3, 'kowalski', 'Kowalski')");
        $pdo->exec("INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (5, 'Paul', 'Durand'), (6, 'Jean', 'Martin')");

        return $pdo;
    }
}
