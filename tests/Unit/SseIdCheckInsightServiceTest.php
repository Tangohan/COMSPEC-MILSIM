<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AtakMobileAppsController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\AtakAdminControlRepository;
use App\Repositories\AtakMobileLinkRepository;
use App\Repositories\SseFieldNoteRepository;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SsePersonRepository;
use App\Services\Atak\AtakMobileLinkService;
use App\Services\Personnel\MemberNameService;
use App\Services\Qr\QrPngGenerator;
use App\Services\Sse\SseIdCheckInsightService;
use App\Services\Sse\SseIdCheckService;
use App\Support\ComspecApiKeyAuth;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * COMSPEC IDENTITY (Lot 2) : page « Contrôle ID » du vrai téléphone (mes contrôles de la partie, refusée sans session,
 * brouillon FRM seulement sur mes contrôles), dossier RENS (contrôles regroupés par opération et carte, photos des
 * fiches liées), tableau de bord de l'opération, rattachement d'une fiche FRS de l'ATAK, cloison des communautés.
 */
final class SseIdCheckInsightServiceTest extends TestCase
{
    private const OWN = 7;

    private const FOREIGN = 3;

    private const PLAYER = 5;

    private const STEAM = '76561198000000001';

    private PDO $pdo;

    private ?PDO $savedPdo = null;

    private bool $savedStarted = false;

    /** @var array{set: bool, value: mixed} */
    private array $savedSession = ['set' => false, 'value' => null];

    /** @var array<string, mixed> */
    private array $savedPost = [];

    protected function setUp(): void
    {
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
        $this->savedPost = $_POST;
        MemberNameService::forgetSchema();
    }

    protected function tearDown(): void
    {
        Session::actAs([]);
        ComspecApiKeyAuth::resetForTests();
        MemberNameService::forgetSchema();
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedStarted);
        if ($this->savedSession['set']) {
            $_SESSION = $this->savedSession['value'];
        } else {
            unset($_SESSION);
        }
        $_POST = $this->savedPost;
        unset($_SERVER['HTTP_X_CSRF_TOKEN'], $_SERVER['REQUEST_METHOD']);
    }

    public function testPhonePageListsOnlyMyChecksOfTheGameAndNeedsTheMobileSession(): void
    {
        $mine = $this->check(self::OWN, 'k-mine', self::STEAM, 1, 'recherche', ['N0' => ['state' => 'ok', 'verdict' => 'ras', 'codes' => []], 'N1' => ['state' => 'fail', 'verdict' => 'faux', 'codes' => ['mrz_viz_name']]]);
        $other = $this->check(self::OWN, 'k-other', '76561198000000099', 1, 'ras');
        $foreign = $this->check(self::FOREIGN, 'k-foreign', self::STEAM, 1, 'hvt');
        $otherMap = $this->check(self::OWN, 'k-map2', self::STEAM, 2, 'vole');

        $ctl = $this->controller();
        self::assertSame(403, $ctl->controle($this->req())->statusCode(), 'sans session mobile : refusé');
        self::assertSame(401, $ctl->controleFrm($this->req(['remark' => 'x']), ['id' => (string) $mine])->statusCode());

        $this->openSession('controle');
        $res = $ctl->controle($this->req());
        self::assertSame(200, $res->statusCode());
        $html = $res->body();
        self::assertStringContainsString('CTRL-' . $mine, $html);
        self::assertStringContainsString('Recherché', $html);
        self::assertStringContainsString('N1 — lecture de la bande MRZ', $html);
        self::assertStringContainsString('Nom de la bande MRZ différent du recto', $html, 'constats du mod en clair');
        self::assertStringContainsString('Faux document', $html);
        self::assertStringContainsString('data-idc-frm="' . $mine . '"', $html);
        foreach ([$other, $foreign, $otherMap] as $id) {
            self::assertStringNotContainsString('CTRL-' . $id . ' ', $html, 'seulement mes contrôles, de ma communauté, sur la carte de la partie');
        }

        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'faux';
        self::assertSame(419, $ctl->controleFrm($this->req(['remark' => 'x']), ['id' => (string) $mine])->statusCode(), 'CSRF exigé');
        $_SERVER['HTTP_X_CSRF_TOKEN'] = Csrf::token();
        foreach ([$other, $foreign, $otherMap + 1000] as $id) {
            self::assertSame(404, $ctl->controleFrm($this->req(['remark' => 'x']), ['id' => (string) $id])->statusCode(), 'pas sur le contrôle d’un autre');
        }
        $first = json_decode($ctl->controleFrm($this->req(['remark' => 'Nerveux, a tenté de partir']), ['id' => (string) $mine])->body(), true);
        self::assertTrue($first['ok']);
        self::assertTrue($first['created']);
        $note = $this->pdo->query('SELECT * FROM sse_field_notes WHERE id = ' . (int) $first['note_id'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['brouillon', 'FRM', self::OWN], [$note['status'], $note['note_kind'], (int) $note['tenant_id']]);
        self::assertStringContainsString('Remarque : Nerveux, a tenté de partir', $note['body']);
        self::assertStringContainsString('N1 : Anomalie, faux document', $note['body']);
        self::assertStringContainsString('TERROR', $note['themes'], 'personne recherchée : thèmes renseignement');
        self::assertSame((int) $first['note_id'], (int) $this->pdo->query('SELECT field_note_id FROM sse_id_checks WHERE id = ' . $mine)->fetchColumn());
        $again = json_decode($ctl->controleFrm($this->req(['remark' => 'bis']), ['id' => (string) $mine])->body(), true);
        self::assertSame([(int) $first['note_id'], false], [(int) $again['note_id'], $again['created']], 'une seule fiche par contrôle');
        self::assertStringContainsString('>' . $first['ref'] . '</span> · Brouillon', $ctl->controle($this->req())->body());
    }

    public function testPersonDossierGroupsChecksByOperationWithLinkedPhotosAndStaysInItsTenant(): void
    {
        $a = $this->check(self::OWN, 'p-a', self::STEAM, 1, 'ras', [], 'OP-KAVALA', 12);
        $b = $this->check(self::OWN, 'p-b', self::STEAM, 1, 'hvt', [], 'OP-KAVALA', 12);
        $c = $this->check(self::OWN, 'p-c', self::STEAM, 2, 'signale', [], null, 12);
        $this->check(self::FOREIGN, 'p-x', self::STEAM, 1, 'hvt', [], 'OP-KAVALA', 12);
        $note = $this->note(self::OWN, 'transmise');
        $this->pdo->exec("INSERT INTO sse_field_note_attachments (tenant_id, note_id, file_path, original_name, mime_type, kind, caption)
            VALUES (7, {$note}, 'uploads/sse/fiches/doc.jpg', 'doc.jpg', 'image/jpeg', 'photo', 'Recto du passeport')");
        $this->pdo->exec("INSERT INTO sse_field_note_attachments (tenant_id, note_id, file_path, original_name, mime_type, kind, caption)
            VALUES (3, {$note}, 'uploads/sse/fiches/autre.jpg', 'autre.jpg', 'image/jpeg', 'photo', 'Autre communauté')");
        (new SseIdCheckRepository())->setFieldNote($b, self::OWN, $note);

        $d = $this->insights()->personDossier(self::OWN, 12);
        self::assertSame(3, $d['total']);
        self::assertSame(1, $d['photos'], 'pièce jointe de la fiche liée, de ma communauté seulement');
        self::assertCount(2, $d['groups']);
        $byOp = array_column($d['groups'], null, 'op_key');
        self::assertSame(2, $byOp['OP-KAVALA']['total']);
        self::assertSame(['hvt', 'ras'], array_keys($byOp['OP-KAVALA']['verdicts']), 'le plus grave d’abord');
        self::assertSame('Recto du passeport', $byOp['OP-KAVALA']['photos'][0]['caption']);
        self::assertSame('CTRL-' . $b, $byOp['OP-KAVALA']['photos'][0]['check_ref']);
        self::assertSame(2, $byOp['']['context_id']);
        self::assertSame([$c], array_column($byOp['']['checks'], 'id'));
        self::assertSame(0, $this->insights()->personDossier(self::FOREIGN, 99)['total']);
        self::assertSame(1, $this->insights()->personDossier(self::FOREIGN, 12)['total'], 'l’autre communauté ne voit que le sien');
        self::assertContains($a, array_column($byOp['OP-KAVALA']['checks'], 'id'));
    }

    public function testOperationDashboardCountsDetectionsForgeriesWantedAndDraftsToValidate(): void
    {
        $this->check(self::OWN, 'd-1', self::STEAM, 1, 'ras', [], 'OP-A');
        $wanted = $this->check(self::OWN, 'd-2', self::STEAM, 1, 'recherche', [], 'OP-A', 12);
        $this->check(self::OWN, 'd-3', '76561198000000002', 1, 'recherche', [], 'OP-A', 12);
        $fake = $this->check(self::OWN, 'd-4', self::STEAM, 1, 'inconnu', ['N1' => ['state' => 'fail', 'verdict' => 'faux', 'codes' => []]], 'OP-A');
        $this->check(self::OWN, 'd-5', self::STEAM, 1, 'hvt', [], 'OP-B');
        $this->check(self::FOREIGN, 'd-6', self::STEAM, 1, 'hvt', [], 'OP-A');
        $draft = $this->note(self::OWN, 'brouillon');
        (new SseIdCheckRepository())->setFieldNote($fake, self::OWN, $draft);
        $sent = $this->note(self::OWN, 'transmise');
        (new SseIdCheckRepository())->setFieldNote($wanted, self::OWN, $sent);

        $board = $this->insights()->dashboard(self::OWN, 1, 'OP-A', false);
        self::assertSame(4, $board['total']);
        self::assertSame(3, $board['detections']);
        self::assertSame(1, $board['forgeries']);
        self::assertSame(1, $board['wanted'], 'même personne contrôlée deux fois : une interpellation');
        self::assertNull($board['real_forgeries'], 'vérité de jeu masquée sans habilitation');
        self::assertSame([$draft], array_column($board['drafts'], 'note_id'), 'seuls les brouillons sont à valider');
        self::assertSame('CTRL-' . $fake, $board['drafts'][0]['check_ref']);
        self::assertSame(['recherche', 'inconnu', 'ras'], array_keys($board['by_verdict']), 'du plus grave au moins grave');
        self::assertSame(5, $this->insights()->dashboard(self::OWN, null, null, true)['total']);
        self::assertSame(0, $this->insights()->dashboard(self::OWN, 1, 'OP-A', true)['real_forgeries']);
        self::assertSame(1, $this->insights()->dashboard(self::FOREIGN, null, null, false)['total']);
        self::assertSame([], $this->insights()->pendingDrafts(self::FOREIGN));
        $ops = array_column($this->insights()->operations(self::OWN), 'op_key');
        self::assertSame(['OP-A', 'OP-B'], array_values(array_intersect(['OP-A', 'OP-B'], $ops)));
    }

    public function testAtakFrmTakesOverThePhoneDraftOfTheSameCheck(): void
    {
        $id = $this->check(self::OWN, 'atak-idem-1', self::STEAM, 1, 'vole');
        $draft = $this->note(self::OWN, 'brouillon');
        $checks = new SseIdCheckRepository();
        $checks->setFieldNote($id, self::OWN, $draft);
        $frs = $this->note(self::OWN, 'transmise');

        $svc = $this->insights();
        self::assertFalse($svc->linkNote(self::FOREIGN, 'CTRL-' . $id, $frs), 'contrôle d’une autre communauté');
        self::assertTrue($svc->linkNote(self::OWN, 'CTRL-' . $id, $frs));
        self::assertSame($frs, $checks->find($id, self::OWN)['field_note_id']);
        self::assertSame('sans_suite', $this->pdo->query('SELECT status FROM sse_field_notes WHERE id = ' . $draft)->fetchColumn(), 'brouillon remplacé');
        $other = $this->note(self::OWN, 'transmise');
        self::assertTrue($svc->linkNote(self::OWN, 'atak-idem-1', $other), 'aussi par la clé d’idempotence du contrôle');
        self::assertSame('transmise', $this->pdo->query('SELECT status FROM sse_field_notes WHERE id = ' . $frs)->fetchColumn(), 'une fiche transmise n’est jamais classée');
        self::assertFalse($svc->linkNote(self::OWN, 'CTRL-99999', $other));
    }

    public function testNumericLevelsFromTheModAreKept(): void
    {
        $persons = $this->createMock(SsePersonRepository::class);
        $persons->method('findById')->willReturn(null);
        $persons->method('findLikelyDuplicate')->willReturn(null);
        $svc = new SseIdCheckService(new SseIdCheckRepository(), new SseIdDocumentRepository(), $persons);
        $res = $svc->store(self::OWN, 1, [
            'idempotency_key' => 'lvl-0001',
            'levels' => [1, 0, '3', 9],
            'results' => ['N0' => ['state' => 'ok'], 'N2' => ['state' => 'na']],
            'verdict' => 'ras',
        ]);
        self::assertSame(['N0', 'N1', 'N2', 'N3'], (new SseIdCheckRepository())->find($res['id'], self::OWN)['levels']);
        self::assertSame('N2', SseIdCheckService::levelCode(2));
        self::assertNull(SseIdCheckService::levelCode('N7'));
        $rows = SseIdCheckInsightService::levelRows((new SseIdCheckRepository())->find($res['id'], self::OWN));
        self::assertSame(['ok', 'off', 'off', 'off'], array_column($rows, 'tone'));
    }

    public function testSiteAndPhoneAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("'/m/controle'", $routes);
        self::assertStringContainsString("'/m/controle/{id}/frm'", $routes);
        self::assertStringContainsString("'/atak/sse/papiers/tableau'", $routes);
        self::assertLessThan(strpos($routes, "'/m/{token}'"), strpos($routes, "'/m/controle'"), 'page avant le jeton');
        self::assertSame(['m/controle', 'Contrôle ID'], AtakMobileLinkService::APPS['controle']);
        self::assertFileExists($root . '/views/m/controle.php');
        self::assertFileExists($root . '/views/atak/sse/papers/dashboard.php');
        self::assertFileExists($root . '/database/steps/3750_atak_sse_identity_lot2.php');
        self::assertStringContainsString("'file' => '3750_atak_sse_identity_lot2.php'", (string) file_get_contents($root . '/database/steps/registry.php'));
        self::assertStringContainsString("'id_check_ref'", (string) file_get_contents($root . '/app/Controllers/Api/SseFieldNoteApiController.php'));

        $nat = $root . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions';
        self::assertStringContainsString('["controle", "Contrôle ID"]', (string) file_get_contents($nat . '/ui/fn_mobileQr.sqf'));
        self::assertStringContainsString('"id_check_ref"', (string) file_get_contents($nat . '/pages/fn_frsSubmit.sqf'));
        $bt = (string) file_get_contents($nat . '/bluetooth/fn_btAction.sqf');
        foreach (['case "shareIdCheck";', 'case "shareIdCheckNfc":', 'case "idcFrm":', 'comspec_atak_native_bt_nfc_range'] as $needle) {
            self::assertStringContainsString($needle, $bt);
        }
        self::assertStringContainsString('case "idcStore":', (string) file_get_contents($nat . '/bluetooth/fn_btItems.sqf'));
        self::assertStringContainsString('["_mode",""]', (string) file_get_contents($nat . '/ui/fn_notify.sqf'));
        self::assertStringContainsString('params [["_noSound", false]];', (string) file_get_contents($nat . '/ui/fn_vibrate.sqf'));
        self::assertStringContainsString('COMSPEC_ATAK_SpotrepDraft', (string) file_get_contents($nat . '/reports/fn_pageReports.sqf'));
        self::assertStringContainsString('case "prefill":', (string) file_get_contents($nat . '/reports/fn_reportAction.sqf'));
    }

    // ----------------------------------------------------------------- Outils

    /** @param array<string, mixed> $results */
    private function check(int $tenant, string $key, string $controller, int $map, string $verdict, array $results = [], ?string $op = null, ?int $person = null): int
    {
        return (new SseIdCheckRepository())->store($tenant, [
            'context_id' => $map,
            'op_key' => $op,
            'idempotency_key' => $key,
            'person_id' => $person,
            'doc_type' => 'passport',
            'country_code' => 'ALT',
            'doc_number' => 'ALK' . substr(md5($key), 0, 6),
            'presented_last' => 'Papadopoulos',
            'presented_first' => 'Nikos',
            'presented_dob' => '1990-05-01',
            'controller_callsign' => 'VIPER 1-1',
            'controller_steam_id' => $controller,
            'levels' => implode(',', array_keys($results)) ?: null,
            'results_json' => json_encode($results === [] ? new \stdClass() : $results),
            'verdict' => $verdict,
            'grid' => '0123 4567',
            'pos_x' => 1230.0,
            'pos_y' => 4560.0,
            'pos_z' => 0.0,
            'game_time' => '2035-06-14 08:30',
        ])['id'];
    }

    private function note(int $tenant, string $status): int
    {
        return (new SseFieldNoteRepository())->create($tenant, ['body' => 'Fiche de test du contrôle.', 'themes' => ['PERSON'], 'status' => $status, 'origin' => 'atak']);
    }

    private function insights(): SseIdCheckInsightService
    {
        return new SseIdCheckInsightService(null, null, static function (int $tenant, array $input): array {
            $repo = new SseFieldNoteRepository();
            $existing = $repo->findByIdempotencyKey($tenant, (string) ($input['idempotency_key'] ?? ''));
            if ($existing !== null) {
                return ['note' => $existing, 'created' => false];
            }

            return ['note' => $repo->findForTenant($tenant, $repo->create($tenant, $input)) ?? [], 'created' => true];
        });
    }

    private function controller(): AtakMobileAppsController
    {
        $limiter = new class () extends \App\Services\Security\FileRateLimiter {
            public function __construct()
            {
            }

            public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
            {
                return false;
            }

            public function hit(string $key, int $decaySeconds): int
            {
                return 1;
            }
        };

        return new AtakMobileAppsController($this->links(), null, $limiter, null, $this->insights());
    }

    private function links(): AtakMobileLinkService
    {
        $notices = new AtakAdminControlRepository($this->pdo);
        (new \ReflectionProperty(AtakAdminControlRepository::class, 'ready'))->setValue($notices, true);

        return new AtakMobileLinkService(new AtakMobileLinkRepository($this->pdo), new QrPngGenerator(), $this->pdo, $notices);
    }

    /** @return array<string, mixed> */
    private function openSession(string $app): array
    {
        $res = $this->links()->issue(self::OWN, self::PLAYER, self::STEAM, 'Op Test@Altis#202611021900', 1, $app, 'Tango');
        self::assertTrue($res['ok']);
        $link = $this->links()->redeem((string) substr($res['url'], (int) strrpos($res['url'], '/') + 1), '10.0.0.1', 'Firefox');
        self::assertNotNull($link);
        $_SESSION[AtakMobileLinkService::SESSION_KEY] = ['id' => (int) $link['id'], 'tenant' => (int) $link['tenant_id']];

        return $link;
    }

    /** @param array<string, mixed> $post */
    private function req(array $post = []): Request
    {
        $_SERVER['REQUEST_METHOD'] = $post === [] ? 'GET' : 'POST';
        $_POST = $post;

        return new Request();
    }

    private static function schema(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $pdo->sqliteCreateFunction('UTC_TIMESTAMP', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $pdo->exec("INSERT INTO tenants (id, name, slug) VALUES (7, 'Alpha', 'alpha'), (3, 'Bravo', 'bravo')");
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, steam_id TEXT, display_name TEXT, callsign TEXT)');
        $pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name, callsign) VALUES (5, 7, '76561198000000001', 'paul_d', 'Viper 1')");
        $pdo->exec('CREATE TABLE user_profiles (user_id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT)');
        $pdo->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, action TEXT, entity_type TEXT,
            entity_id INTEGER, old_value TEXT, new_value TEXT, ip TEXT, user_agent TEXT, created_at TEXT)');
        $pdo->exec("CREATE TABLE atak_mobile_links (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, user_id INTEGER NOT NULL,
            steam_uid TEXT, mission_key TEXT, map_id INTEGER NOT NULL DEFAULT 1, app TEXT NOT NULL DEFAULT 'cr', player_name TEXT NOT NULL DEFAULT '',
            pos_x REAL, pos_y REAL, context_json TEXT, token_hash TEXT NOT NULL UNIQUE, token_expires_at TEXT NOT NULL, redeemed_at TEXT,
            session_expires_at TEXT, revoked_at TEXT, last_seen_at TEXT, ip TEXT, user_agent TEXT, created_at TEXT NOT NULL)");
        $pdo->exec("CREATE TABLE atak_admin_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, user_id INTEGER, steam_uid TEXT,
            terminal_uid TEXT, kind TEXT NOT NULL DEFAULT 'message', title TEXT NOT NULL, body TEXT NOT NULL, level TEXT NOT NULL DEFAULT 'info',
            require_ack INTEGER NOT NULL DEFAULT 1, expires_at TEXT, created_by_user_id INTEGER, created_at TEXT, delivered_at TEXT, acked_at TEXT, cancelled_at TEXT)");
        $pdo->exec('CREATE TABLE atak_air_assets (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, map_id INTEGER, callsign TEXT, model TEXT,
            aircraft_type TEXT, freq TEXT, laser TEXT, pos_x REAL, pos_y REAL, alt REAL, heading REAL, side TEXT, status TEXT, pilot TEXT, crew TEXT,
            fuel_pct INTEGER, updated_at TEXT)');
        $pdo->exec("CREATE TABLE sse_field_notes (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, context_id INTEGER NOT NULL DEFAULT 1,
            reference_code TEXT NOT NULL, note_kind TEXT NOT NULL DEFAULT 'FRM', themes TEXT NOT NULL DEFAULT '[]', title TEXT, body TEXT NOT NULL,
            observed_at TEXT NOT NULL, place_label TEXT, grid_reference TEXT, pos_x REAL, pos_y REAL, pos_z REAL, lat REAL, lng REAL,
            urgency TEXT NOT NULL DEFAULT 'routine', intel_source TEXT, classification TEXT NOT NULL DEFAULT 'interne', source_reliability TEXT NOT NULL DEFAULT 'C',
            info_credibility INTEGER NOT NULL DEFAULT 3, status TEXT NOT NULL DEFAULT 'transmise', origin TEXT NOT NULL DEFAULT 'web', author_label TEXT,
            author_user_id INTEGER, author_steam_id TEXT, author_unit TEXT, case_id INTEGER, interest_case_id INTEGER, triage_note TEXT, triaged_by INTEGER,
            triaged_at TEXT, idempotency_key TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE sse_field_note_attachments (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, note_id INTEGER NOT NULL,
            file_path TEXT NOT NULL, original_name TEXT, mime_type TEXT, byte_size INTEGER, kind TEXT NOT NULL DEFAULT 'photo', caption TEXT,
            grid_reference TEXT, pos_x REAL, pos_y REAL, pos_z REAL, author_label TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        SseIdentitySqlite::migrate($pdo);

        return $pdo;
    }
}
