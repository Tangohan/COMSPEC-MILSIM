<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakTocBaseController;
use App\Controllers\Api\AtakTocPhoneController;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Services\AtakToc\TocAccess;
use App\Services\AtakToc\TocPhoneService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Routes JSON des actions sur les téléphones : droit du poste pour lire, droit dédié atak.toc.phone pour agir (atak.toc.manage
 * ne le donne pas), jeton CSRF, réglage de la communauté (désactivé par défaut, changé par la gestion du poste seulement),
 * cloisonnement par communauté, journal d'audit et aucune fuite de compte Steam.
 */
final class AtakTocPhoneApiTest extends TestCase
{
    private PDO $pdo;

    private ?PDO $savedPdo = null;

    private bool $savedStarted = false;

    /** @var array<string, mixed> */
    private array $savedServer = [];

    /** @var list<array<string, mixed>> */
    private array $queue = [];

    private int $now = 1_800_000_000;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        (require dirname(__DIR__, 2) . '/bootstrap/atak_toc_phone_migration.php')($this->pdo);
        if ($this->pdo instanceof \Pdo\Sqlite) {
            $this->pdo->createFunction('NOW', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        } elseif (method_exists($this->pdo, 'sqliteCreateFunction')) {
            $this->pdo->sqliteCreateFunction('NOW', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        }
        $this->pdo->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, action TEXT, entity_type TEXT, entity_id INTEGER, old_value TEXT, new_value TEXT, ip TEXT, user_agent TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $db = new \ReflectionProperty(Database::class, 'pdo');
        $cur = $db->getValue();
        $this->savedPdo = $cur instanceof PDO ? $cur : null;
        $db->setValue(null, $this->pdo);
        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $_SESSION = [];
        foreach (['HTTP_X_CSRF_TOKEN', 'REQUEST_METHOD'] as $k) {
            if (array_key_exists($k, $_SERVER)) {
                $this->savedServer[$k] = $_SERVER[$k];
            }
            unset($_SERVER[$k]);
        }
        AtakTocBaseController::$testBody = null;
        $this->queue = [];
    }

    protected function tearDown(): void
    {
        Gate::reset();
        AtakTocBaseController::$testBody = null;
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedStarted);
        $_SESSION = [];
        foreach (['HTTP_X_CSRF_TOKEN', 'REQUEST_METHOD'] as $k) {
            unset($_SERVER[$k]);
        }
        foreach ($this->savedServer as $k => $v) {
            $_SERVER[$k] = $v;
        }
    }

    /** @param list<string> $perms */
    private function signIn(int $tenant, int $user, array $perms, bool $csrf = true): void
    {
        $_SESSION = ['tenant_id' => $tenant, 'user_id' => $user, 'callsign' => 'TOC-' . $user, '_csrf_token' => 'jeton'];
        if ($csrf) {
            $_SERVER['HTTP_X_CSRF_TOKEN'] = 'jeton';
        } else {
            unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        }
        $_SERVER['REQUEST_METHOD'] = 'POST';
        Gate::getInstance()->setPermissions($perms);
    }

    private function api(): AtakTocPhoneController
    {
        $svc = new TocPhoneService(
            $this->pdo,
            function (int $tenantId, int $mapId, string $cmd, string $uid, string $ref, string $label, array $args, array $wire, int $ttl): ?int {
                $this->queue[] = compact('tenantId', 'cmd', 'uid', 'wire');

                return 100 + count($this->queue);
            },
            static fn (): ?array => null,
            static fn (int $tenantId): array => [
                ['id' => 1, 'call_sign' => 'ALPHA 1-1', 'status' => 'linked', 'pos_x' => 10000, 'pos_y' => 12000, 'age_seconds' => 5, 'map_id' => 1, 'extra' => ['steam_uid' => '7656119800000000' . $tenantId, 'side' => 'WEST']],
                ['id' => 2, 'call_sign' => 'Garde 4', 'status' => 'linked', 'pos_x' => 10100, 'pos_y' => 12100, 'age_seconds' => 5, 'map_id' => 1, 'extra' => ['is_ai' => true, 'enemy_ai' => true, 'side' => 'EAST']],
            ],
            static fn (): array => [],
            null,
            fn (): int => time(),
            'secret'
        );

        return new AtakTocPhoneController(new TocAccess(null, static fn (): array => []), $svc);
    }

    /** @return array<string, mixed> */
    private static function json(\App\Core\Response $r): array
    {
        $d = json_decode($r->body(), true);

        return is_array($d) ? $d : [];
    }

    private function ref(string $callsign = 'ALPHA 1-1'): string
    {
        $d = self::json($this->api()->index(new Request()));
        foreach ($d['phones'] as $p) {
            if ($p['callsign'] === $callsign) {
                return (string) $p['ref'];
            }
        }
        throw new RuntimeException('téléphone absent');
    }

    private function enableFeature(): void
    {
        $this->signIn(1, 1, ['atak.toc.use', 'atak.toc.manage']);
        AtakTocBaseController::$testBody = json_encode(['enabled' => true, 'hide_hostile_journal' => false]);
        self::assertSame(200, $this->api()->settings(new Request())->statusCode());
    }

    public function testReadingNeedsTheStationRightAndNeverLeaksSteamAccounts(): void
    {
        self::assertSame(401, $this->api()->index(new Request())->statusCode());
        $this->signIn(1, 7, []);
        self::assertSame(403, $this->api()->index(new Request())->statusCode());
        self::assertSame(403, $this->api()->events(new Request())->statusCode());
        self::assertSame(403, $this->api()->show(new Request(), ['ref' => 'pabcdefabcdef'])->statusCode());
        $this->signIn(1, 7, ['atak.toc.use']);
        $r = $this->api()->index(new Request());
        self::assertSame(200, $r->statusCode());
        $d = self::json($r);
        self::assertFalse($d['canPhone'], 'tenir le poste ne donne pas le droit d’agir sur les téléphones');
        self::assertFalse($d['enabled']);
        self::assertTrue($d['ready']);
        self::assertCount(2, $d['phones']);
        self::assertStringNotContainsString('76561198', $r->body());
        self::assertSame('private, no-store', $r->headerValue('Cache-Control'));
    }

    public function testActionsNeedTheDedicatedRightNotJustManage(): void
    {
        $this->enableFeature();
        $ref = $this->ref();
        AtakTocBaseController::$testBody = json_encode(['message' => 'Salut', 'level' => 'info']);
        foreach ([['atak.toc.use'], ['atak.toc.use', 'atak.toc.manage']] as $perms) {
            $this->signIn(1, 7, $perms);
            foreach (['notify', 'ring', 'ringStop', 'locate', 'triangulate'] as $m) {
                $r = $this->api()->$m(new Request(), ['ref' => $ref]);
                self::assertSame(403, $r->statusCode(), $m . ' ' . implode(',', $perms));
                self::assertSame('forbidden_phone', self::json($r)['error']);
            }
        }
        self::assertSame([], $this->queue, 'rien n’est parti en jeu');
        $this->signIn(1, 7, ['atak.toc.phone']);
        self::assertSame(403, $this->api()->notify(new Request(), ['ref' => $ref])->statusCode(), 'le droit dédié suppose atak.toc.use');
        $this->signIn(1, 7, ['atak.toc.use', 'atak.toc.phone']);
        self::assertSame(200, $this->api()->notify(new Request(), ['ref' => $ref])->statusCode());
        $this->signIn(1, 8, ['atak.toc.use', 'admin.organization']);
        self::assertSame(200, $this->api()->locate(new Request(), ['ref' => $ref])->statusCode(), 'l’administration de la communauté vaut le droit');
    }

    public function testWritesNeedACsrfTokenAndTheCommunityMustHaveEnabledTheFunction(): void
    {
        $this->signIn(1, 7, ['atak.toc.use', 'atak.toc.phone']);
        $ref = $this->ref();
        AtakTocBaseController::$testBody = json_encode(['message' => 'Salut', 'level' => 'info']);
        $r = $this->api()->notify(new Request(), ['ref' => $ref]);
        self::assertSame(403, $r->statusCode());
        self::assertSame('disabled', self::json($r)['error'], 'désactivée tant que la communauté ne l’a pas activée');
        $this->enableFeature();
        $this->signIn(1, 7, ['atak.toc.use', 'atak.toc.phone'], false);
        self::assertSame(419, $this->api()->notify(new Request(), ['ref' => $ref])->statusCode(), 'CSRF');
        $this->signIn(1, 8, ['atak.toc.use', 'atak.toc.manage'], false);
        self::assertSame(419, $this->api()->settings(new Request())->statusCode());
        self::assertSame([], $this->queue);
    }

    public function testSettingsAreChangedByTheStationManagersOnly(): void
    {
        AtakTocBaseController::$testBody = json_encode(['enabled' => true, 'hide_hostile_journal' => true]);
        $this->signIn(1, 7, ['atak.toc.use', 'atak.toc.phone']);
        self::assertSame(403, $this->api()->settings(new Request())->statusCode(), 'le droit d’agir ne règle pas la communauté');
        $this->signIn(1, 8, ['atak.toc.use', 'atak.toc.manage'], false);
        self::assertSame(419, $this->api()->settings(new Request())->statusCode());
        $this->signIn(1, 8, ['atak.toc.use', 'atak.toc.manage']);
        $r = $this->api()->settings(new Request());
        self::assertSame(200, $r->statusCode());
        self::assertTrue(self::json($r)['enabled']);
        self::assertTrue(self::json($r)['hide_hostile_journal']);
        $this->signIn(2, 9, ['atak.toc.use']);
        self::assertFalse(self::json($this->api()->index(new Request()))['enabled'], 'réglage propre à chaque communauté');
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'atak_toc.phone.settings' AND user_id = 8")->fetchColumn());
    }

    public function testActionsAreValidatedLimitedAndJournalled(): void
    {
        $this->enableFeature();
        $ref = $this->ref();
        $this->signIn(1, 7, ['atak.toc.use', 'atak.toc.phone']);
        AtakTocBaseController::$testBody = json_encode(['message' => 'Urgent', 'level' => 'urgent', 'ack' => true]);
        $r = $this->api()->notify(new Request(), ['ref' => $ref]);
        self::assertSame(422, $r->statusCode());
        self::assertSame('confirm_required', self::json($r)['error'], 'Urgent demande une confirmation');
        AtakTocBaseController::$testBody = json_encode(['message' => 'Urgent', 'level' => 'urgent', 'ack' => true, 'confirm' => true]);
        self::assertSame(200, $this->api()->notify(new Request(), ['ref' => $ref])->statusCode());
        AtakTocBaseController::$testBody = json_encode(['seconds' => 10, 'mode' => 'ring']);
        self::assertSame(422, $this->api()->ring(new Request(), ['ref' => $ref])->statusCode(), 'sonner demande une confirmation');
        AtakTocBaseController::$testBody = json_encode(['seconds' => 10, 'mode' => 'ring', 'confirm' => true]);
        self::assertSame(200, $this->api()->ring(new Request(), ['ref' => $ref])->statusCode());
        self::assertSame(200, $this->api()->ringStop(new Request(), ['ref' => $ref])->statusCode());
        AtakTocBaseController::$testBody = '{}';
        self::assertSame(200, $this->api()->locate(new Request(), ['ref' => $ref])->statusCode());
        $again = $this->api()->locate(new Request(), ['ref' => $ref]);
        self::assertSame(429, $again->statusCode(), '1 localisation par 15 s et par téléphone');
        self::assertNotNull($again->headerValue('Retry-After'));
        self::assertSame(200, $this->api()->triangulate(new Request(), ['ref' => $ref])->statusCode());
        $names = $this->pdo->query("SELECT action FROM audit_logs WHERE action LIKE 'atak_toc.phone.%' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        foreach (['notify', 'ring', 'ring_stop', 'locate', 'triangulate', 'locate.refused'] as $a) {
            self::assertContains('atak_toc.phone.' . $a, $names, $a);
        }
        $ev = self::json($this->api()->events(new Request()));
        self::assertGreaterThanOrEqual(6, count($ev['events']));
        self::assertStringNotContainsString('76561198', (string) json_encode($ev));
        $sheet = self::json($this->api()->show(new Request(), ['ref' => $ref]));
        self::assertTrue($sheet['ok']);
    }

    public function testUnknownMalformedOrForeignTargetsAreNotFound(): void
    {
        $this->enableFeature();
        $ref = $this->ref();
        $this->signIn(1, 7, ['atak.toc.use', 'atak.toc.phone']);
        AtakTocBaseController::$testBody = json_encode(['message' => 'x', 'level' => 'info']);
        foreach (['pffffffffffff', 'p123', '../../etc', 'p' . str_repeat('0', 12)] as $bad) {
            self::assertSame(404, $this->api()->notify(new Request(), ['ref' => $bad])->statusCode(), $bad);
            self::assertSame(404, $this->api()->show(new Request(), ['ref' => $bad])->statusCode(), $bad);
        }
        // Autre communauté, même droit, fonction activée : la référence d'une autre communauté ne désigne rien.
        $this->signIn(2, 9, ['atak.toc.use', 'atak.toc.manage', 'atak.toc.phone']);
        AtakTocBaseController::$testBody = json_encode(['enabled' => true]);
        $this->api()->settings(new Request());
        AtakTocBaseController::$testBody = json_encode(['message' => 'x', 'level' => 'info']);
        self::assertSame(404, $this->api()->notify(new Request(), ['ref' => $ref])->statusCode());
        AtakTocBaseController::$testBody = '{}';
        self::assertSame(404, $this->api()->locate(new Request(), ['ref' => $ref])->statusCode());
        self::assertSame(404, $this->api()->triangulate(new Request(), ['ref' => $ref])->statusCode());
        self::assertSame([], array_filter($this->queue, static fn (array $q): bool => $q['tenantId'] === 2), 'rien pour l’autre communauté');
    }

    public function testNpcCannotBeNotifiedOrRung(): void
    {
        $this->enableFeature();
        $npc = $this->ref('Garde 4');
        $this->signIn(1, 7, ['atak.toc.use', 'atak.toc.phone']);
        AtakTocBaseController::$testBody = json_encode(['message' => 'x', 'level' => 'info']);
        $r = $this->api()->notify(new Request(), ['ref' => $npc]);
        self::assertSame(422, $r->statusCode());
        self::assertSame('target_not_player', self::json($r)['error']);
        AtakTocBaseController::$testBody = json_encode(['seconds' => 5, 'mode' => 'ring', 'confirm' => true]);
        self::assertSame(422, $this->api()->ring(new Request(), ['ref' => $npc])->statusCode());
        self::assertSame([], $this->queue);
    }
}
