<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\SseIdentityApiController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TenantRepository;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseIdCheckService;
use App\Services\Sse\SseIdDocumentLookupService;
use App\Services\Sse\SseIdentityPackService;
use App\Support\ComspecApiKeyAuth;
use App\Support\GameApiResponseCompactor;
use App\Support\ShortCache;
use App\Support\SseIdentityPackCache;
use PHPUnit\Framework\TestCase;

/**
 * Paquet d'identité (GET /api/sse/identity/pack) : révision du contenu, réponse « unchanged » (since_rev) ou 304
 * (If-None-Match) quand elle n'a pas changé, paquet complet comme avant sans révision, cache cloisonné par communauté
 * et invalidé par les écritures du registre.
 */
final class SseIdentityPackRevisionTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $saved = [];

    /** Papiers renvoyés par le service simulé, par communauté. @var array<int, list<array<string, mixed>>> */
    private array $docs = [];

    private int $builds = 0;

    protected function setUp(): void
    {
        $this->saved = ['get' => $_GET ?? [], 'post' => $_POST ?? [], 'session' => $_SESSION ?? null, 'server' => $_SERVER];
        $_GET = [];
        $_POST = [];
        $_SESSION = ['user_id' => 1, 'tenant_id' => 7];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/sse/identity/pack';
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_X_ATAK_TOKEN'], $_SERVER['HTTP_IF_NONE_MATCH'], $_SERVER['HTTP_USER_AGENT']);
        ComspecApiKeyAuth::resetForTests();
        ShortCache::setEnabled(false);
        $this->docs = [7 => [['id' => 1, 'number' => 'ALK482913']], 8 => [['id' => 9, 'number' => 'ZZ0001']]];
        $this->builds = 0;
    }

    protected function tearDown(): void
    {
        ShortCache::setEnabled(null);
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

    private function controller(): SseIdentityApiController
    {
        $pack = $this->createMock(SseIdentityPackService::class);
        $pack->method('build')->willReturnCallback(function (int $tenantId, int $mapId, ?string $op, ?string $since, int $offset, int $limit): array {
            $this->builds++;

            return [
                'version' => 1,
                // Heure de génération différente à chaque calcul : ne doit pas changer la révision.
                'generated_at' => gmdate('Y-m-d\TH:i:s\Z', 1_700_000_000 + $this->builds),
                'map' => $mapId,
                'op' => $op !== '' ? $op : null,
                'countries' => [['code' => 'ALT']],
                'identities' => [],
                'documents' => $this->docs[$tenantId] ?? [],
                'watch' => [],
                'kits' => [],
                'population' => [],
                'wanted' => [],
                'briefs' => [['uid' => '7656119', 'cover_name' => 'Nikos Papadopoulos']],
                'settings' => ['auto_transmit' => true],
                'paging' => ['offset' => $offset, 'limit' => $limit, 'total' => count($this->docs[$tenantId] ?? []), 'next_offset' => null],
            ];
        });
        $clearance = $this->createMock(SseClearanceService::class);
        $clearance->method('allows')->willReturn(true);
        $config = $this->createMock(TenantAtakConfigRepository::class);
        $config->method('isMaintenanceEnabled')->willReturn(false);

        return new SseIdentityApiController(
            $pack,
            $this->createMock(SseIdDocumentLookupService::class),
            $this->createMock(SseIdCheckService::class),
            $clearance,
            $config,
            $this->createMock(TenantRepository::class),
        );
    }

    /** @param array<string, string> $query */
    private function call(array $query = [], ?string $ifNoneMatch = null): Response
    {
        $_GET = $query;
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
        if ($ifNoneMatch !== null) {
            $_SERVER['HTTP_IF_NONE_MATCH'] = $ifNoneMatch;
        }

        return $this->controller()->pack(new Request());
    }

    /** @return array<string, mixed> */
    private static function body(Response $r): array
    {
        $data = json_decode($r->body(), true);
        self::assertIsArray($data);

        return $data;
    }

    public function testWithoutRevisionTheFullPackIsServedAsBeforeWithARevision(): void
    {
        $r = $this->call(['map' => '3']);
        self::assertSame(200, $r->statusCode());
        $data = self::body($r);
        // Contrat d'avant (mêmes clés, même ordre), puis la révision en plus : la DLL publiée l'ignore.
        self::assertSame(
            ['version', 'generated_at', 'map', 'op', 'countries', 'identities', 'documents', 'watch', 'kits', 'population', 'wanted', 'briefs', 'settings', 'paging', 'revision'],
            array_keys($data)
        );
        self::assertSame([['id' => 1, 'number' => 'ALK482913']], $data['documents']);
        self::assertArrayNotHasKey('unchanged', $data);
        self::assertMatchesRegularExpression('/^[0-9a-f]{24}$/', $data['revision']);
        self::assertSame(SseIdentityPackCache::etag($data['revision']), $r->headerValue('ETag'));
    }

    public function testSameRevisionAnswersUnchangedWithoutThePayload(): void
    {
        $rev = self::body($this->call())['revision'];

        $r = $this->call(['since_rev' => $rev]);
        self::assertSame(200, $r->statusCode());
        $data = self::body($r);
        self::assertTrue($data['unchanged']);
        self::assertSame($rev, $data['revision']);
        self::assertArrayHasKey('paging', $data);
        foreach (['documents', 'identities', 'countries', 'kits', 'briefs', 'watch'] as $heavy) {
            self::assertArrayNotHasKey($heavy, $data);
        }
    }

    public function testSameRevisionInIfNoneMatchAnswers304(): void
    {
        $first = $this->call();
        $etag = (string) $first->headerValue('ETag');

        $r = $this->call([], $etag);
        self::assertSame(304, $r->statusCode());
        self::assertSame('', $r->body());
        self::assertSame($etag, $r->headerValue('ETag'));
        // Empreinte d'un autre contenu : paquet complet.
        self::assertSame(200, $this->call([], 'W/"idp-000000000000000000000000"')->statusCode());
    }

    public function testChangedContentGivesANewRevisionAndTheFullPack(): void
    {
        $rev = self::body($this->call())['revision'];
        $this->docs[7][] = ['id' => 2, 'number' => 'ALK000002'];

        $r = $this->call(['since_rev' => $rev]);
        $data = self::body($r);
        self::assertSame(200, $r->statusCode());
        self::assertArrayNotHasKey('unchanged', $data);
        self::assertNotSame($rev, $data['revision']);
        self::assertCount(2, $data['documents']);
        self::assertSame(200, $this->call([], SseIdentityPackCache::etag($rev))->statusCode());
    }

    public function testGenerationTimeDoesNotChangeTheRevision(): void
    {
        $a = ['version' => 1, 'generated_at' => '2026-11-09T10:00:00Z', 'documents' => [['id' => 1]]];
        $b = ['generated_at' => '2026-11-09T10:05:00Z'] + $a;
        self::assertSame(SseIdentityPackService::revisionOf($a), SseIdentityPackService::revisionOf($b));
        $b['documents'][] = ['id' => 2];
        self::assertNotSame(SseIdentityPackService::revisionOf($a), SseIdentityPackService::revisionOf($b));
    }

    public function testCacheIsPerCommunityAndDroppedByARegistryWrite(): void
    {
        ShortCache::setEnabled(true);
        SseIdentityPackCache::invalidate();
        $_SESSION['tenant_id'] = 7;
        $a = self::body($this->call(['map' => '42', 'op' => 'OP-TEST-' . getmypid()]));
        $_SESSION['tenant_id'] = 8;
        $b = self::body($this->call(['map' => '42', 'op' => 'OP-TEST-' . getmypid()]));
        self::assertSame(2, $this->builds);
        self::assertSame([['id' => 9, 'number' => 'ZZ0001']], $b['documents'], 'jamais le paquet d’une autre communauté');
        self::assertNotSame($a['revision'], $b['revision']);

        // Même communauté, même requête : servi par le cache.
        $_SESSION['tenant_id'] = 7;
        $again = self::body($this->call(['map' => '42', 'op' => 'OP-TEST-' . getmypid()]));
        self::assertSame(2, $this->builds);
        self::assertSame($a['revision'], $again['revision']);

        // Écriture réussie du registre (portail SSE) : recalculé.
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/atak/sse/papiers/documents/5/cycle';
        SseIdentityPackCache::invalidateAfter(new Request(), new Response());
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/sse/identity/pack';
        $this->docs[7][] = ['id' => 3, 'number' => 'ALK000003'];
        $fresh = self::body($this->call(['map' => '42', 'op' => 'OP-TEST-' . getmypid()]));
        self::assertSame(3, $this->builds);
        self::assertCount(2, $fresh['documents']);
        self::assertNotSame($a['revision'], $fresh['revision']);
    }

    public function testOnlySuccessfulRegistryWritesInvalidate(): void
    {
        foreach (['/atak/sse/papiers/pays', '/back-office/ressources/effectifs/membres/3/papiers/kit', '/back-office/ma-situation/mes-papiers/couverture', '/api/sse/identity/checks', '/account/bienvenue/identite'] as $path) {
            self::assertTrue(SseIdentityPackCache::touchesRegistry($path), $path);
        }
        foreach (['/api/atak/position', '/api/chat', '/back-office/tableau-operationnel'] as $path) {
            self::assertFalse(SseIdentityPackCache::touchesRegistry($path), $path);
        }

        ShortCache::setEnabled(true);
        $key = static fn (): string => SseIdentityPackCache::key(7, 1, '', '', 0, 200);
        $before = $key();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/atak/sse/papiers/pays';
        SseIdentityPackCache::invalidateAfter(new Request(), new Response());
        $_SERVER['REQUEST_METHOD'] = 'POST';
        SseIdentityPackCache::invalidateAfter(new Request(), (new Response())->setStatusCode(422));
        self::assertSame($before, $key(), 'lecture ou écriture refusée : cache gardé');
        usleep(2);
        SseIdentityPackCache::invalidateAfter(new Request(), (new Response())->setStatusCode(302));
        self::assertNotSame($before, $key());
    }

    public function testGameClientKeepsTheRevisionEtagAndGets304(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'COMSPECExtension/2.0.123';
        $first = $this->call(['map' => '1']);
        $etag = (string) $first->headerValue('ETag');
        GameApiResponseCompactor::apply(new Request(), $first);
        // L'empreinte de la route survit au compacteur (sinon generated_at la changerait à chaque appel).
        self::assertSame($etag, $first->headerValue('ETag'));

        // COMSPEC Link publié renvoie l'empreinte reçue pour la même URL : 304, il ressert sa copie.
        $second = $this->call(['map' => '1'], $etag);
        GameApiResponseCompactor::apply(new Request(), $second);
        self::assertSame(304, $second->statusCode());
    }
}
