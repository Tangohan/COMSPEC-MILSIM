<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\SseIdentityApiController;
use App\Core\Request;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseWatchlistRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TenantRepository;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseIdCheckService;
use App\Services\Sse\SseIdDocumentLookupService;
use App\Services\Sse\SseIdentityPackService;
use App\Services\Sse\SseIdWatchFromGameService;
use App\Support\ComspecApiKeyAuth;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * COMSPEC IDENTITY lots 4 et 5 : BOLO et HVT nés en jeu remontés sur Athena (liste de surveillance de la communauté),
 * mis à jour par leur référence du jeu, renvoyés dans watch[] du paquet ; niveau N4 (visage) des contrôles ;
 * migration rejouable. Dépôts réels sur sqlite.
 */
final class SseIdWatchFromGameServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseIdentitySqlite::open();
        // Table du registre SSE (migration MySQL des personnes), rendue pour sqlite.
        $this->pdo->exec("CREATE TABLE sse_watchlist_entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, last_name TEXT NOT NULL DEFAULT '',
            first_name TEXT NOT NULL DEFAULT '', alias TEXT NULL, threat_level TEXT NOT NULL DEFAULT 'surveillance',
            notes TEXT NULL, active INTEGER NOT NULL DEFAULT 1, created_at TEXT NULL, updated_at TEXT NULL)");
        (require dirname(__DIR__, 2) . '/bootstrap/atak_sse_identity_lot45_migration.php')($this->pdo);
    }

    protected function tearDown(): void
    {
        SseIdentitySqlite::close();
    }

    /** @param array<string, mixed> $watch */
    private function body(string $kind, array $watch, string $key = 'BOLO-test-1-active-1'): array
    {
        return ['kind' => $kind, 'idempotency_key' => $key, 'map' => '1', 'op' => '', 'watch' => $watch];
    }

    public function testMigrationRunsTwiceWithoutChangingAnything(): void
    {
        $out = '';
        (require dirname(__DIR__, 2) . '/bootstrap/atak_sse_identity_lot45_migration.php')($this->pdo, static function (string $m) use (&$out): void {
            $out .= $m;
        });
        self::assertSame('', $out);
        $cols = array_column($this->pdo->query('PRAGMA table_info(sse_watchlist_entries)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach (['source', 'game_ref', 'context_id', 'bio_key', 'known_docs', 'game_status'] as $c) {
            self::assertContains($c, $cols);
        }
    }

    public function testBoloIsCreatedThenUpdatedByItsGameReference(): void
    {
        $svc = new SseIdWatchFromGameService();
        $first = $svc->store(1, 1, $this->body('bolo', [
            'game_ref' => 'BOLO-Op_Test-1234-1', 'last_name' => 'Antoniou', 'first_name' => 'Dimitra', 'alias' => 'Corbeau',
            'level' => 'recherche', 'notes' => "Pick-up\nblanc", 'bio_key' => '', 'known_docs' => ['alt-123 456', 'ALT123456'], 'status' => 'active',
        ]));
        self::assertFalse($first['duplicate']);
        self::assertSame('WL-' . $first['id'], $first['ref']);

        $row = (new SseWatchlistRepository())->listActive(1)[0];
        self::assertSame('prioritaire', $row['threat_level']);
        self::assertSame('game', $row['source']);
        self::assertSame(['ALT-123456', 'ALT123456'], $row['known_docs']);
        self::assertSame('Pick-up blanc', $row['notes']);

        // Même référence : mise à jour (étape), nom vide gardé.
        $again = $svc->store(1, 1, $this->body('bolo', ['game_ref' => 'BOLO-Op_Test-1234-1', 'last_name' => '', 'level' => 'recherche', 'status' => 'fix'], 'BOLO-Op_Test-1234-1-fix-2'));
        self::assertTrue($again['duplicate']);
        self::assertSame($first['id'], $again['id']);
        $row = (new SseWatchlistRepository())->listActive(1)[0];
        self::assertSame('Antoniou', $row['last_name']);
        self::assertSame('fix', $row['game_status']);

        // Levée : l'avis n'est plus actif ; l'autre communauté ne voit rien.
        $svc->store(1, 1, $this->body('bolo', ['game_ref' => 'BOLO-Op_Test-1234-1', 'status' => 'removed', 'last_name' => 'Antoniou']));
        self::assertSame([], (new SseWatchlistRepository())->listActive(1));
        self::assertSame([], (new SseWatchlistRepository())->listActive(2));
    }

    public function testHvtGoesToThePackWithItsFaceAndKnownPapers(): void
    {
        $res = (new SseIdWatchFromGameService())->store(1, 1, $this->body('hvt', [
            'game_ref' => 'HVT-Op-1234-2', 'last_name' => 'Haddad', 'first_name' => 'Karim', 'alias' => 'Le Renard',
            'bio_key' => 'K-77', 'known_docs' => ['TNA998877'], 'status' => 'find',
        ], 'HVT-Op-1234-2-find-1'));
        $persons = $this->createMock(SsePersonRepository::class);
        $persons->method('listForContext')->willReturn([]);
        $pack = (new SseIdentityPackService(new SseIdDocumentRepository(), new SseIdentityRepository(), new SseIdCountryRepository(), new SseWatchlistRepository(), $persons))->build(1, 1);
        self::assertSame([[
            'ref' => $res['ref'], 'last_name' => 'Haddad', 'first_name' => 'Karim', 'alias' => 'Le Renard', 'level' => 'hvt', 'person_id' => null,
            'game_ref' => 'HVT-Op-1234-2', 'bio_key' => 'K-77', 'known_docs' => ['TNA998877'], 'status' => 'find',
        ]], $pack['watch']);
    }

    public function testIncompleteNoticesAreRefused(): void
    {
        $svc = new SseIdWatchFromGameService();
        self::assertSame(['error' => 'game_ref_required'], $svc->store(1, 1, $this->body('bolo', ['game_ref' => 'x y', 'last_name' => 'A'])));
        self::assertSame(['error' => 'game_ref_required'], $svc->store(1, 1, $this->body('autre', ['game_ref' => 'BOLO-1-2-3', 'last_name' => 'A'])));
        self::assertSame(['error' => 'watch_subject_required'], $svc->store(1, 1, $this->body('bolo', ['game_ref' => 'BOLO-1-2-3'])));
        self::assertSame('surveillance', SseIdWatchFromGameService::level('surveillance', 'bolo'));
        self::assertSame('hvt', SseIdWatchFromGameService::level('', 'hvt'));
    }

    public function testFaceComparisonLevelIsKept(): void
    {
        self::assertSame('N4', SseIdCheckService::levelCode(4));
        self::assertSame('N4', SseIdCheckService::levelCode('n4'));
        self::assertNull(SseIdCheckService::levelCode(5));
        $res = (new SseIdCheckService())->store(1, 1, [
            'idempotency_key' => 'IDCHK-test-n4', 'levels' => [0, 4], 'verdict' => 'hvt',
            'results' => ['N4' => ['state' => 'alert', 'verdict' => 'hvt', 'codes' => ['face_match', 'bio_hvt'], 'score' => 87]],
        ]);
        $row = $this->pdo->query('SELECT levels, results_json FROM sse_id_checks WHERE id = ' . (int) $res['id'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame('N0,N4', $row['levels']);
        self::assertStringContainsString('"score":87', (string) $row['results_json']);
    }

    public function testChecksEndpointRoutesGameNoticesToTheWatchlist(): void
    {
        if (!ComspecApiKeyAuth::armaInlineAuthOk()) {
            self::markTestSkipped('clé plateforme configurée dans cet environnement');
        }
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = ['tenant_id' => '1'];
        $clearance = $this->createMock(SseClearanceService::class);
        $clearance->method('allows')->willReturn(true);
        $config = $this->createMock(TenantAtakConfigRepository::class);
        $config->method('isMaintenanceEnabled')->willReturn(false);
        $checks = $this->createMock(SseIdCheckService::class);
        $checks->expects(self::never())->method('store');
        $ctl = fn (string $json): SseIdentityApiController => new SseIdentityApiController(
            $this->createMock(SseIdentityPackService::class), $this->createMock(SseIdDocumentLookupService::class), $checks,
            $clearance, $config, $this->createMock(TenantRepository::class), static fn (): string => $json, new SseIdWatchFromGameService()
        );
        $json = (string) json_encode($this->body('bolo', ['game_ref' => 'BOLO-Op-1234-9', 'last_name' => 'Antoniou', 'level' => 'surveillance']));
        $created = $ctl($json)->checksStore(new Request());
        self::assertSame(201, $created->statusCode());
        $data = json_decode($created->body(), true);
        self::assertSame('WL-' . $data['id'], $data['ref']);
        self::assertSame(200, $ctl($json)->checksStore(new Request())->statusCode());
        self::assertSame(422, $ctl((string) json_encode($this->body('hvt', ['game_ref' => 'HVT-Op-1234-3'])))->checksStore(new Request())->statusCode());
    }
}
