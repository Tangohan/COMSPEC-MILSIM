<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\SseIdentityApiController;
use App\Core\Request;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TenantRepository;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseEditorLibraryService;
use App\Services\Sse\SseIdCheckService;
use App\Services\Sse\SseIdDocumentLookupService;
use App\Services\Sse\SseIdentityPackService;
use App\Support\ComspecApiKeyAuth;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Bibliothèque de l'éditeur SSE (POST /api/sse/identity/checks, kind = editor_…) : droits, cloison des communautés,
 * format rendu au jeu par la DLL (« OK|id|ref »), versions, découverte.
 */
final class SseEditorLibraryTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = SseIdentitySqlite::open();
        $pdo->exec('CREATE TABLE game_sessions (id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER, steam_id TEXT, access_token_hash TEXT, expires_at TEXT, revoked_at TEXT)');
        $this->saved = ['get' => $_GET ?? [], 'post' => $_POST ?? [], 'session' => $_SESSION ?? null, 'server' => $_SERVER];
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_X_ATAK_TOKEN']);
        ComspecApiKeyAuth::resetForTests();
    }

    protected function tearDown(): void
    {
        SseIdentitySqlite::close();
        $_GET = $this->saved['get'];
        $_POST = $this->saved['post'];
        $_SERVER = $this->saved['server'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
        ComspecApiKeyAuth::resetForTests();
    }

    /** Service dont le compte a tous les droits, ou aucun. */
    private function library(bool $allowed = true): SseEditorLibraryService
    {
        return new SseEditorLibraryService(null, static fn (): bool => $allowed, static fn (): string => '');
    }

    /** @return array<string, mixed> */
    private static function item(string $id = 'E20261108-1', array $extra = []): array
    {
        return $extra + [
            'id' => $id,
            'type' => 'intel',
            'title' => 'Livraison prévue au port',
            'body' => "Source \"H1\" | fiable\nQuai 4",
            'marking' => 2,
            'paper' => 0,
            'nature' => 0,
            'delivery' => 1,
            'state' => 'Brouillon',
            'op' => 'OP-KAVALA',
            'link' => '',
            'version' => 1,
            'history' => [['Version 1 enregistrée', 'VIPER 1', '08/11/2026 21:42']],
            'found' => [],
        ];
    }

    /** @return list<array<int, mixed>> ce que le jeu relit : même forme que parseSimpleArray, sans les remplacements DLL. */
    private static function rows(string $ref): array
    {
        self::assertStringNotContainsString('|', $ref);
        self::assertStringNotContainsString("\n", $ref);
        // « "" » du SQF → JSON équivalent pour relire le tableau dans le test.
        $json = preg_replace_callback('/"((?:[^"]|"")*)"/', static fn (array $m): string => (string) json_encode(str_replace('""', '"', $m[1]), JSON_UNESCAPED_UNICODE), $ref);
        $rows = json_decode((string) $json, true);
        self::assertIsArray($rows);

        return $rows;
    }

    private function controller(string $body, bool $sessionAllowed, ?SseEditorLibraryService $library = null): SseIdentityApiController
    {
        $clearance = $this->createMock(SseClearanceService::class);
        $clearance->method('allows')->willReturn($sessionAllowed);
        $config = $this->createMock(TenantAtakConfigRepository::class);
        $config->method('isMaintenanceEnabled')->willReturn(false);

        return new SseIdentityApiController(
            $this->createMock(SseIdentityPackService::class),
            $this->createMock(SseIdDocumentLookupService::class),
            $this->createMock(SseIdCheckService::class),
            $clearance,
            $config,
            $this->createMock(TenantRepository::class),
            static fn (): string => $body,
            null,
            $library ?? $this->library(),
        );
    }

    /** Jeton de jeu du compte 5 de la communauté donnée. */
    private function gameSession(int $tenant): void
    {
        $token = str_repeat('a', 64);
        $st = \App\Core\Database::getPdo()->prepare('INSERT INTO game_sessions (tenant_id, user_id, access_token_hash, expires_at) VALUES (?, 5, ?, ?)');
        $st->execute([$tenant, hash('sha256', $token), '2099-01-01 00:00:00']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    }

    public function testSavedItemComesBackInTheGameFormatAndStaysInItsCommunity(): void
    {
        $library = $this->library();
        $saved = $library->handle(1, 5, ['kind' => 'editor_save', 'item' => self::item()]);
        self::assertSame('saved', $saved['ref']);
        self::assertGreaterThan(0, $saved['id']);

        $page = $library->handle(1, 5, ['kind' => 'editor_list', 'offset' => 0]);
        self::assertSame(0, $page['id']);
        $rows = self::rows($page['ref']);
        self::assertCount(1, $rows);
        [$id, $type, $title, $body] = $rows[0];
        self::assertSame(['E20261108-1', 'intel', 'Livraison prévue au port'], [$id, $type, $title]);
        self::assertSame('Source "H1" \\p fiable\\nQuai 4', $body);
        self::assertSame([2, 0, 0, 1, 'Brouillon', 'OP-KAVALA', '', 1], array_slice($rows[0], 4, 8));
        self::assertSame([['Version 1 enregistrée', 'VIPER 1', '08/11/2026 21:42']], $rows[0][12]);
        self::assertSame([], $rows[0][13]);

        // Autre communauté : rien à lire, même identifiant = autre contenu, découverte sans effet.
        self::assertSame('[]', $library->handle(2, 9, ['kind' => 'editor_list'])['ref']);
        self::assertSame('unknown', $library->handle(2, null, ['kind' => 'editor_found', 'uid' => 'E20261108-1', 'player' => 'X', 'when' => 'hier'])['ref']);
        self::assertSame('saved', $library->handle(2, 9, ['kind' => 'editor_save', 'item' => self::item('E20261108-1', ['title' => 'Autre'])])['ref']);
        self::assertSame('Livraison prévue au port', self::rows($library->handle(1, 5, ['kind' => 'editor_list'])['ref'])[0][2]);
    }

    public function testOlderVersionIsRefusedAndLargeContentIsNotCut(): void
    {
        $library = $this->library();
        $library->handle(1, 5, ['kind' => 'editor_save', 'item' => self::item('E1', ['version' => 3, 'title' => 'Récent'])]);

        self::assertSame('conflict', $library->handle(1, 5, ['kind' => 'editor_save', 'item' => self::item('E1', ['version' => 2, 'title' => 'Ancien'])])['ref']);
        self::assertSame('Récent', self::rows($library->handle(1, 5, ['kind' => 'editor_list'])['ref'])[0][2]);
        self::assertSame('saved', $library->handle(1, 5, ['kind' => 'editor_save', 'item' => self::item('E1', ['version' => 3, 'title' => 'Même version'])])['ref']);

        self::assertSame(['error' => 'too_large'], $library->handle(1, 5, ['kind' => 'editor_save', 'item' => self::item('E2', ['body' => str_repeat('é', 4000)])]));
        foreach ([['id' => 'a/b'], ['id' => ''], ['title' => ' '], ['type' => 'x'], ['state' => 'x'], ['version' => 0]] as $bad) {
            self::assertSame(['error' => 'invalid_item'], $library->handle(1, 5, ['kind' => 'editor_save', 'item' => $bad + self::item('E3')]));
        }
    }

    public function testPagesStayWithinWhatTheDllCanReturn(): void
    {
        $library = $this->library();
        for ($i = 0; $i < 12; $i++) {
            $saved = $library->handle(1, 5, ['kind' => 'editor_save', 'item' => self::item('E' . $i, ['body' => str_repeat('x', 2000)])]);
            self::assertSame('saved', $saved['ref']);
        }
        $seen = [];
        $offset = 0;
        $calls = 0;
        do {
            $page = $library->handle(1, 5, ['kind' => 'editor_list', 'offset' => $offset]);
            self::assertLessThan(7500, strlen($page['ref']));
            foreach (self::rows($page['ref']) as $row) {
                $seen[] = $row[0];
            }
            $offset = $page['id'];
            self::assertLessThan(13, ++$calls);
        } while ($offset > 0);
        sort($seen);
        self::assertCount(12, array_unique($seen));
    }

    public function testFirstFinderStaysAndCanArriveWithALateSave(): void
    {
        $library = $this->library();
        $library->handle(1, 5, ['kind' => 'editor_save', 'item' => self::item('E1')]);
        $found = ['kind' => 'editor_found', 'uid' => 'E1', 'player' => 'Sgt Durand', 'when' => '08/11/2026 22:10'];
        self::assertSame('found', $library->handle(1, null, $found)['ref']);
        self::assertSame('known', $library->handle(1, null, ['player' => 'Autre'] + $found)['ref']);
        self::assertSame(['Sgt Durand', '08/11/2026 22:10'], self::rows($library->handle(1, 5, ['kind' => 'editor_list'])['ref'])[0][13]);

        // Enregistrement suivant d'un poste qui l'ignorait : la découverte reste.
        $library->handle(1, 5, ['kind' => 'editor_save', 'item' => self::item('E1', ['version' => 2])]);
        self::assertSame(['Sgt Durand', '08/11/2026 22:10'], self::rows($library->handle(1, 5, ['kind' => 'editor_list'])['ref'])[0][13]);

        // Poste resté hors ligne pendant la découverte : elle arrive avec l'enregistrement.
        $library->handle(1, 5, ['kind' => 'editor_save', 'item' => self::item('E2', ['found' => ['Cpl Roy', '09/11/2026 01:00']])]);
        $rows = self::rows($library->handle(1, 5, ['kind' => 'editor_list'])['ref']);
        self::assertContains(['Cpl Roy', '09/11/2026 01:00'], array_column($rows, 13));
        self::assertSame(['error' => 'invalid_item'], $library->handle(1, null, ['kind' => 'editor_found', 'uid' => 'E1']));
    }

    public function testAccountRightsFollowTheClearanceRules(): void
    {
        $service = static function (array $granted, string $profile = ''): SseEditorLibraryService {
            return new SseEditorLibraryService(
                null,
                static fn (int $t, int $u, array $p): bool => array_intersect($p, $granted) !== [],
                static fn (int $u): string => $profile,
            );
        };
        self::assertTrue($service(['atak.sse.clearance.confidentiel'])->userAllowed(1, 5));
        self::assertTrue($service(['atak.sse.case.manage'])->userAllowed(1, 5));
        self::assertTrue($service(['admin.access'])->userAllowed(1, 5));
        // L'habilitation explicite décide, même si le rôle donnerait plus.
        self::assertFalse($service(['atak.sse.clearance.encadrement', 'atak.sse.case.manage'])->userAllowed(1, 5));
        self::assertFalse($service(['atak.sse.access'])->userAllowed(1, 5));
        self::assertFalse($service([])->userAllowed(1, 5));
        self::assertTrue($service(['atak.sse.access'], 'confidentiel')->userAllowed(1, 5));
        self::assertFalse($service(['atak.sse.access'], 'encadrement')->userAllowed(1, 5));
        self::assertFalse($service([], 'confidentiel')->userAllowed(1, 5));
    }

    public function testEndpointAnswersIdAndRefToAnAllowedGameAccount(): void
    {
        $this->gameSession(1);
        $saved = $this->controller((string) json_encode(['kind' => 'editor_save', 'item' => self::item()]), false)->checksStore(new Request());
        self::assertSame(200, $saved->statusCode());
        $data = json_decode($saved->body(), true);
        self::assertSame(['id', 'ref'], array_keys($data));
        self::assertIsInt($data['id']);

        $listed = $this->controller('{"kind":"editor_list"}', false)->checksStore(new Request());
        self::assertSame(200, $listed->statusCode());
        self::assertSame('E20261108-1', self::rows(json_decode($listed->body(), true)['ref'])[0][0]);
    }

    public function testGameAccountWithoutClearanceIsRefusedWithTheCodeTheDllReads(): void
    {
        $this->gameSession(1);
        foreach (['{"kind":"editor_list"}', (string) json_encode(['kind' => 'editor_save', 'item' => self::item()])] as $body) {
            $denied = $this->controller($body, true, $this->library(false))->checksStore(new Request());
            self::assertSame(403, $denied->statusCode());
            self::assertStringContainsString('clearance_required', $denied->body());
        }
        self::assertSame(0, (int) \App\Core\Database::getPdo()->query('SELECT COUNT(*) FROM sse_editor_items')->fetchColumn());
    }

    public function testCommunityFromTheTokenWinsOverTheBody(): void
    {
        $library = $this->library();
        $library->handle(2, 9, ['kind' => 'editor_save', 'item' => self::item('EB')]);
        $this->gameSession(1);
        $_GET = ['tenant_id' => '2'];

        $listed = $this->controller('{"kind":"editor_list","tenant_id":2}', false, $library)->checksStore(new Request());
        self::assertSame('[]', json_decode($listed->body(), true)['ref']);
    }

    public function testPortalSessionNeedsConfidentialClearance(): void
    {
        if (!ComspecApiKeyAuth::armaInlineAuthOk()) {
            self::markTestSkipped('clé plateforme configurée dans cet environnement');
        }
        $_SESSION = ['user_id' => 5, 'tenant_id' => 1];

        self::assertSame(403, $this->controller('{"kind":"editor_list"}', false)->checksStore(new Request())->statusCode());
        self::assertSame(200, $this->controller('{"kind":"editor_list"}', true)->checksStore(new Request())->statusCode());
    }

    /** Clé de communauté, sans compte : le serveur de jeu, ou tout poste relié. La découverte passe, la lecture non. */
    public function testCommunityKeyAloneCanOnlyReportADiscovery(): void
    {
        $library = $this->library();
        $library->handle(1, 5, ['kind' => 'editor_save', 'item' => self::item('E1')]);
        $key = 'cle-de-communaute';
        $_SERVER['HTTP_X_COMSPEC_KEY'] = $key;
        (new \ReflectionProperty(ComspecApiKeyAuth::class, 'resolvedKeyCache'))->setValue(null, [
            'key' => hash('sha256', $key . "\n" . ComspecApiKeyAuth::expectedSecret()),
            'ok' => true, 'unavailable' => false, 'tenant' => 1, 'steam' => null, 'user' => null, 'platform' => false,
        ]);

        foreach (['{"kind":"editor_list"}', (string) json_encode(['kind' => 'editor_save', 'item' => self::item('E2')])] as $body) {
            $denied = $this->controller($body, true, $library)->checksStore(new Request());
            self::assertSame(403, $denied->statusCode());
            self::assertStringContainsString('clearance_required', $denied->body());
        }
        $found = (string) json_encode(['kind' => 'editor_found', 'uid' => 'E1', 'player' => 'Sgt Durand', 'when' => '22:10']);
        $ok = $this->controller($found, false, $library)->checksStore(new Request());
        self::assertSame(200, $ok->statusCode());
        self::assertSame('found', json_decode($ok->body(), true)['ref']);
    }

    public function testMissingTableAnswersUnavailableSoTheModStaysLocal(): void
    {
        \App\Core\Database::getPdo()->exec('DROP TABLE sse_editor_items');
        $this->gameSession(1);
        $r = $this->controller('{"kind":"editor_list"}', false)->checksStore(new Request());
        self::assertSame(503, $r->statusCode());
    }

    public function testModAndDllStayOnThisContract(): void
    {
        $root = dirname(__DIR__, 2);
        $mod = (string) file_get_contents($root . '/mod/@COMSPEC_SSE/addons/editor/functions/fn_editorLibrary.sqf');
        self::assertStringContainsString('"SubmitIdCheck"', $mod);
        foreach (['editor_save', 'editor_list'] as $kind) {
            self::assertStringContainsString('"' . $kind . '"', $mod);
        }
        self::assertStringContainsString('"editor_found"', (string) file_get_contents($root . '/mod/@COMSPEC_SSE/addons/editor/functions/fn_editorFound.sqf'));
        $dll = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension_Identity.cs');
        self::assertStringContainsString('"/api/sse/identity/checks"', $dll);
        self::assertStringContainsString('"OK|" + id.ToString(CultureInfo.InvariantCulture) + "|" + SqfField(reference)', $dll);
        self::assertStringContainsString('"clearance_required"', $dll);
    }

    public function testStepIsRegisteredAndRepeatsWithoutEffect(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertStringContainsString("'file' => '4070_atak_sse_editor_library.php', 'rerun' => true", (string) file_get_contents($root . '/database/steps/registry.php'));
        self::assertFileExists($root . '/database/steps/4070_atak_sse_editor_library.php');
        $out = SseIdentitySqlite::migrate(\App\Core\Database::getPdo());
        self::assertStringContainsString('sse_editor_items (déjà présente)', $out);
    }
}
