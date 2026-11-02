<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\SseIdentityApiController;
use App\Core\Request;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TenantRepository;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseIdCheckService;
use App\Services\Sse\SseIdDocumentLookupService;
use App\Services\Sse\SseIdentityPackService;
use App\Support\ComspecApiKeyAuth;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Contrôles remontés du terrain (POST /api/sse/identity/checks) : idempotence, rattachement au papier et à la
 * personne, cloison des communautés ; garde de l'API (habilitation d'une session du portail).
 */
final class SseIdCheckServiceTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        SseIdentitySqlite::open();
        $this->saved = ['get' => $_GET ?? [], 'post' => $_POST ?? [], 'session' => $_SESSION ?? null, 'server' => $_SERVER];
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
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

    private function persons(): SsePersonRepository
    {
        $persons = $this->createMock(SsePersonRepository::class);
        $persons->method('findById')->willReturnCallback(static fn (int $id, ?int $t = null): ?array => $id === 12 && $t === 1 ? ['id' => 12] : null);
        $persons->method('findLikelyDuplicate')->willReturnCallback(
            static fn (int $t, int $map, array $d): ?array => $t === 1 && $map === 1 && ($d['target_unit_netid'] ?? '') === '2:345' ? ['id' => 40] : null
        );

        return $persons;
    }

    private function service(): SseIdCheckService
    {
        return new SseIdCheckService(new SseIdCheckRepository(), new SseIdDocumentRepository(), $this->persons());
    }

    private function issuedDocument(int $tenant = 1): int
    {
        $identity = (new SseIdentityRepository())->create($tenant, ['last_name' => 'Papadopoulos', 'first_name' => 'Nikos']);
        $docs = new SseIdDocumentRepository();
        $id = $docs->create($tenant, ['identity_id' => $identity, 'doc_type' => 'passport', 'country_code' => 'ALT', 'doc_number' => 'ALK482913']);
        $docs->transition($id, $tenant, 'validated');
        $docs->transition($id, $tenant, 'issued');

        return $id;
    }

    /** @return array<string, mixed> */
    private static function body(string $key, array $extra = []): array
    {
        return $extra + [
            'idempotency_key' => $key,
            'map' => 1,
            'op' => 'OP-KAVALA',
            'document' => ['id' => 0, 'type' => 'passport', 'country' => 'ALT', 'number' => 'ALK482913'],
            'presented_name' => ['last' => 'Papadopoulos', 'first' => 'Nikos', 'dob' => '1990-05-01'],
            'subject' => ['person_id' => 12, 'netid' => '2:345', 'bio_key' => 'FP-1'],
            'controller' => ['callsign' => 'VIPER 1-1', 'steam_id' => '76561198000000001'],
            'levels' => ['N1', 'N0', 'N3', 'N9'],
            'results' => ['N0' => 'ok', 'N1' => ['valid' => true], 'N3' => ['verdict' => 'ras'], 'X' => 'ignoré'],
            'verdict' => 'ras',
            'grid' => '123456',
            'pos' => [3650.5, 13100.2, 0],
            'game_time' => '2035-06-14T08:30',
            'case' => 'SSE-2026-014',
        ];
    }

    public function testCheckIsStoredOnceAndLinkedToThePaperAndThePerson(): void
    {
        $docId = $this->issuedDocument();
        $service = $this->service();

        $first = $service->store(1, 1, self::body('chk-0001'));
        self::assertFalse($first['duplicate']);
        self::assertSame('CTRL-' . $first['id'], $first['ref']);
        $again = $service->store(1, 1, self::body('chk-0001', ['verdict' => 'hvt']));
        self::assertTrue($again['duplicate']);
        self::assertSame($first['id'], $again['id']);

        $check = (new SseIdCheckRepository())->find($first['id'], 1);
        self::assertSame($docId, $check['document_id']);
        self::assertSame(12, $check['person_id']);
        self::assertSame(['N0', 'N1', 'N3'], $check['levels']);
        self::assertSame(['N0', 'N1', 'N3'], array_keys($check['results']));
        self::assertSame('ras', $check['verdict']);
        self::assertSame('VIPER 1-1', $check['controller_callsign']);
        self::assertSame([3650.5, 13100.2, 0.0], $check['pos']);
        self::assertSame('SSE-2026-014', $check['case_ref']);
        self::assertSame(1, (int) \App\Core\Database::getPdo()->query('SELECT COUNT(*) FROM sse_id_checks')->fetchColumn());
    }

    public function testPersonFallsBackToTheGameUnitAndCommunitiesStaySeparate(): void
    {
        $this->issuedDocument();
        $service = $this->service();

        $byNet = $service->store(1, 1, self::body('chk-0002', ['subject' => ['person_id' => 999, 'netid' => '2:345']]));
        self::assertSame(40, (new SseIdCheckRepository())->find($byNet['id'], 1)['person_id']);

        // Même clé, autre communauté : un contrôle distinct, sans rattachement au papier de la communauté 1.
        $other = $service->store(2, 1, self::body('chk-0002'));
        self::assertFalse($other['duplicate']);
        $row = (new SseIdCheckRepository())->find($other['id'], 2);
        self::assertNull($row['document_id']);
        self::assertNull($row['person_id']);
        self::assertNull((new SseIdCheckRepository())->find($other['id'], 1));

        self::assertSame(['error' => 'idempotency_key_required'], $service->store(1, 1, self::body('x y')));
        self::assertSame(['error' => 'idempotency_key_required'], $service->store(1, 1, ['verdict' => 'ras']));
    }

    private function controller(bool $allowed, ?string $body = null): SseIdentityApiController
    {
        $clearance = $this->createMock(SseClearanceService::class);
        $clearance->method('allows')->willReturn($allowed);
        $config = $this->createMock(TenantAtakConfigRepository::class);
        $config->method('isMaintenanceEnabled')->willReturn(false);
        $pack = $this->createMock(SseIdentityPackService::class);
        $pack->method('build')->willReturn(['version' => 1, 'documents' => []]);
        $lookup = $this->createMock(SseIdDocumentLookupService::class);
        $lookup->method('lookup')->willReturn(['found' => false, 'verdict' => 'inconnu', 'ref' => '', 'level' => '', 'flags' => ['unknown_document'], 'updated_at' => null]);

        return new SseIdentityApiController(
            $pack,
            $lookup,
            $this->service(),
            $clearance,
            $config,
            $this->createMock(TenantRepository::class),
            $body !== null ? static fn (): string => $body : null,
        );
    }

    public function testPortalSessionNeedsTheClearanceForThePack(): void
    {
        $_SESSION = ['user_id' => 5, 'tenant_id' => 1];
        $_GET = ['map' => '1'];

        $denied = $this->controller(false)->pack(new Request());
        self::assertSame(403, $denied->statusCode());
        self::assertSame('clearance_required', json_decode($denied->body(), true)['error']);

        $ok = $this->controller(true)->pack(new Request());
        self::assertSame(200, $ok->statusCode());
        self::assertSame(1, json_decode($ok->body(), true)['version']);

        self::assertSame(403, $this->controller(false)->lookup(new Request())->statusCode());
    }

    public function testChecksEndpointAnswersIdAndRefAndStaysIdempotent(): void
    {
        if (!ComspecApiKeyAuth::armaInlineAuthOk()) {
            self::markTestSkipped('clé plateforme configurée dans cet environnement');
        }
        $this->issuedDocument();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = ['tenant_id' => '1'];
        $json = (string) json_encode(self::body('chk-0100'));

        $created = $this->controller(true, $json)->checksStore(new Request());
        self::assertSame(201, $created->statusCode());
        $data = json_decode($created->body(), true);
        self::assertSame(['id', 'ref', 'duplicate'], array_keys($data));
        self::assertSame('CTRL-' . $data['id'], $data['ref']);

        $replay = $this->controller(true, $json)->checksStore(new Request());
        self::assertSame(200, $replay->statusCode());
        self::assertSame($data['id'], json_decode($replay->body(), true)['id']);

        self::assertSame(422, $this->controller(true, '{"verdict":"ras"}')->checksStore(new Request())->statusCode());
        self::assertSame(422, $this->controller(true, '')->checksStore(new Request())->statusCode());
    }
}
