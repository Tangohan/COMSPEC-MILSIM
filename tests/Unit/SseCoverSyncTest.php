<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakCoverSyncApiController;
use App\Core\Request;
use App\Repositories\SseCoverExportRepository;
use App\Services\Sse\SseCoverExportService;
use App\Services\Sse\SseCoverSyncService;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Couvertures d'Athena récupérées depuis le téléphone (Ma couverture › Synchroniser avec Athena) : liste bornée au
 * membre du jeton, téléchargement du seul propriétaire, fichier identique à celui du site, accès refusé sans compte
 * relié, limite de fréquence, relais par le paquet d'identité.
 */
final class SseCoverSyncTest extends TestCase
{
    private \DateTimeImmutable $now;
    /** @var list<array<string, mixed>> */
    private array $audit = [];

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-09 12:00:00', new \DateTimeZone('UTC'));
        $this->audit = [];
    }

    protected function tearDown(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            SseIdentitySqlite::close();
        }
        $_GET = [];
    }

    private function db(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = SseIdentitySqlite::open();
        (require dirname(__DIR__, 2) . '/bootstrap/sse_cover_exports_migration.php')($pdo);
    }

    private function export(): SseCoverExportService
    {
        return new SseCoverExportService(null, null, null, function (array $e): void {
            $this->audit[] = $e;
        }, 'test-key');
    }

    private function sync(): SseCoverSyncService
    {
        return new SseCoverSyncService(new SseCoverExportRepository(), $this->export());
    }

    /** @return array{ok: true, id: int, ref: string, name: string} */
    private function generate(int $tenant, int $user, ?int $by = null): array
    {
        $res = $this->export()->generate($tenant, $user, $by ?? $user, 'MLD', 'F', 'fr', null, $by !== null && $by !== $user, $this->now);
        self::assertTrue($res['ok'], (string) ($res['error'] ?? ''));

        return $res;
    }

    private function controller(int $tenant, int $user, ?\Closure $tooMany = null): AtakCoverSyncApiController
    {
        return new AtakCoverSyncApiController($this->sync(), static fn (): array => [$tenant, $user], $tooMany ?? static fn (): bool => false);
    }

    public function testListIsScopedToTheTokenMember(): void
    {
        $this->db();
        $mine = $this->generate(1, 10);
        $byManager = $this->generate(1, 10, 99);
        $this->generate(1, 11);
        $this->generate(2, 10);

        $list = $this->sync()->listFor(1, 10, $this->now);
        self::assertSame([$byManager['ref'], $mine['ref']], array_column($list, 'ref'));
        $first = $list[0];
        foreach (['ref', 'name', 'summary', 'country', 'country_code', 'status', 'has_photo', 'generated_at', 'expires_at', 'updated_at', 'size', 'sha'] as $k) {
            self::assertArrayHasKey($k, $first, $k);
        }
        self::assertSame('valid', $first['status']);
        self::assertTrue($first['by_manager']);
        self::assertFalse($list[1]['by_manager']);
        self::assertFalse($first['has_photo']);
        self::assertGreaterThan(200, $first['size']);
        self::assertNotSame('', $first['summary']);
        // Rien du contenu complet dans la liste.
        self::assertArrayNotHasKey('cover', $first);

        // Au-delà de 90 jours : listée, marquée expirée.
        $later = $this->sync()->listFor(1, 10, $this->now->modify('+91 days'));
        self::assertSame('expired', $later[0]['status']);
    }

    public function testActiveCoverIsFlaggedAndListedFirst(): void
    {
        $this->db();
        $old = $this->generate(1, 10);
        $mid = $this->generate(1, 10);
        $new = $this->generate(1, 10);
        $other = $this->generate(1, 11);

        // Table des états absente (site sans les outils opérateur) : champ présent, toujours faux, ordre inchangé.
        $list = $this->sync()->listFor(1, 10, $this->now);
        self::assertSame([$new['ref'], $mid['ref'], $old['ref']], array_column($list, 'ref'));
        self::assertSame([false, false, false], array_column($list, 'active'));

        (require dirname(__DIR__, 2) . '/bootstrap/operator_hub_migration.php')(\App\Core\Database::getPdo());
        $hub = new \App\Repositories\OperatorHubRepository();
        // Aucune active : tout à faux.
        self::assertSame([false, false, false], array_column($this->sync()->listFor(1, 10, $this->now), 'active'));

        // L'active d'un autre membre ou d'une autre communauté ne compte pas.
        $hub->setOnlyActive(1, 11, 'cover', $other['id'], 11);
        $hub->setOnlyActive(2, 10, 'cover', $old['id'], 10);
        self::assertSame([false, false, false], array_column($this->sync()->listFor(1, 10, $this->now), 'active'));

        // La plus ancienne marquée active : en tête, seule à vrai, le reste du plus récent au plus ancien.
        $hub->setOnlyActive(1, 10, 'cover', $old['id'], 10);
        $list = $this->sync()->listFor(1, 10, $this->now);
        self::assertSame([$old['ref'], $new['ref'], $mid['ref']], array_column($list, 'ref'));
        self::assertSame([true, false, false], array_column($list, 'active'));
        self::assertSame([true], array_column($this->sync()->listFor(1, 11, $this->now), 'active'));

        // Relais COMSPEC Link : même champ dans « settings.covers ».
        $pack = json_decode($this->controller(1, 10)->packResponse(SseCoverSyncService::OP_LIST, 'v=1', 1)->body(), true);
        self::assertTrue($pack['settings']['covers'][0]['active']);
        self::assertFalse($pack['settings']['covers'][1]['active']);

        // Active puis révoquée : retirée de la liste, plus aucune active.
        $hub->setState(1, 10, 'cover', $old['id'], ['revoked_at' => '2026-11-09 12:00:00'], 10);
        $list = $this->sync()->listFor(1, 10, $this->now);
        self::assertSame([$new['ref'], $mid['ref']], array_column($list, 'ref'));
        self::assertSame([false, false], array_column($list, 'active'));
    }

    public function testDownloadOnlyForTheOwnerAndSameFileAsTheSite(): void
    {
        $this->db();
        $mine = $this->generate(1, 10);
        $other = $this->generate(1, 11);
        $foreign = $this->generate(2, 10);

        $out = $this->sync()->filesFor(1, 10, [$mine['ref'], $other['ref'], $foreign['ref'], 'CVR-00000000'], $this->now);
        self::assertCount(1, $out['files']);
        self::assertSame(
            [['ref' => $other['ref'], 'error' => 'not_found'], ['ref' => $foreign['ref'], 'error' => 'not_found'], ['ref' => 'CVR-00000000', 'error' => 'not_found']],
            $out['errors']
        );
        // Aller-retour : le fichier servi au jeu, réécrit en JSON, est octet pour octet celui du bouton Télécharger du site,
        // et relu sans erreur par le contrat commun au site et au mod.
        $site = $this->export()->download(1, $mine['id'], 10, false, $this->now);
        self::assertNotNull($site);
        $file = $out['files'][0];
        self::assertSame($site['json'], json_encode($file, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $parsed = SseCoverExportService::parse((string) json_encode($file, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $this->now);
        self::assertTrue($parsed['ok']);
        self::assertSame($mine['name'], $parsed['cover']['name']);
        self::assertTrue($this->export()->verifySignature($file));
        // Téléchargement journalisé et noté sur la ligne.
        self::assertContains('id_cover.export_download', array_column($this->audit, 'action'));
        $row = (new SseCoverExportRepository())->findForUserByRef(1, 10, $mine['ref']);
        self::assertNotNull($row['downloaded_at'] ?? null);

        // Expirée : refusée.
        $late = $this->sync()->filesFor(1, 10, [$mine['ref']], $this->now->modify('+91 days'));
        self::assertSame([], $late['files']);
        self::assertSame('expired', $late['errors'][0]['error']);
    }

    public function testPhotoIsLeftOnAthena(): void
    {
        $this->db();
        $res = $this->generate(1, 10);
        $repo = new SseCoverExportRepository();
        $row = $repo->findForUserByRef(1, 10, $res['ref']);
        self::assertNotNull($row);
        $file = json_decode((string) $row['payload_json'], true);
        $file['cover']['photo'] = ['mime' => 'image/jpeg', 'b64' => base64_encode("\xFF\xD8\xFF" . str_repeat('a', 60))];
        $built = $this->export()->buildFile($file['cover'], 1, 10, $res['ref'], $this->now);
        \App\Core\Database::getInstance()->execute('UPDATE sse_cover_exports SET payload_json = :j, content_sha = :s WHERE id = :id', ['j' => $built['json'], 's' => $built['sha'], 'id' => $res['id']]);

        self::assertTrue($this->sync()->listFor(1, 10, $this->now)[0]['has_photo']);
        $out = $this->sync()->filesFor(1, 10, [$res['ref']], $this->now);
        self::assertNull($out['files'][0]['cover']['photo']);
    }

    public function testRefsAreValidated(): void
    {
        self::assertSame(['CVR-0A1B2C3D'], SseCoverSyncService::refsFrom(' CVR-0A1B2C3D , CVR-0A1B2C3D '));
        self::assertNull(SseCoverSyncService::refsFrom(''));
        self::assertNull(SseCoverSyncService::refsFrom('CVR-0a1b2c3d'));
        self::assertNull(SseCoverSyncService::refsFrom('CVR-0A1B2C3D,../etc'));
        self::assertNull(SseCoverSyncService::refsFrom('CVR-00000001,CVR-00000002,CVR-00000003,CVR-00000004,CVR-00000005,CVR-00000006'));
        self::assertSame(1, SseCoverSyncService::clientVersion(''));
        self::assertSame(2, SseCoverSyncService::clientVersion('v=2'));
    }

    public function testRefusedWithoutLinkedAccountOrCommunity(): void
    {
        $unlinked = new AtakCoverSyncApiController(null, static fn (): array => [1, 0], static fn (): bool => false);
        $r = $unlinked->index(new Request());
        self::assertSame(403, $r->statusCode());
        self::assertSame('unlinked', json_decode($r->body(), true)['error']);
        self::assertSame(403, $unlinked->packResponse(SseCoverSyncService::OP_LIST, 'v=1', 1)->statusCode());

        $noTenant = new AtakCoverSyncApiController(null, static fn (): array => [0, 10], static fn (): bool => false);
        self::assertSame('tenant_context_required', json_decode($noTenant->index(new Request())->body(), true)['error']);

        // Opération inconnue : jamais le paquet complet de la communauté.
        self::assertSame(422, $unlinked->packResponse('~coverz', '', 1)->statusCode());
    }

    public function testRateLimitedPerAccount(): void
    {
        $keys = [];
        $c = new AtakCoverSyncApiController(null, static fn (): array => [1, 10], static function (string $key, int $max, int $window) use (&$keys): bool {
            $keys[] = [$key, $max, $window];

            return true;
        });
        $r = $c->index(new Request());
        self::assertSame(429, $r->statusCode());
        self::assertSame('60', $r->headerValue('Retry-After'));
        $_GET = ['refs' => 'CVR-0A1B2C3D'];
        self::assertSame(429, $c->files(new Request())->statusCode());
        self::assertSame(['atak-covers:list:1:10', 12, 60], $keys[0]);
        self::assertSame(['atak-covers:files:1:10', 10, 600], $keys[1]);
    }

    public function testControllerAndPackRelay(): void
    {
        $this->db();
        $mine = $this->generate(1, 10);
        $c = $this->controller(1, 10);

        $list = json_decode($c->index(new Request())->body(), true);
        self::assertSame(SseCoverSyncService::API_VERSION, $list['covers_api']);
        self::assertSame($mine['ref'], $list['covers'][0]['ref']);

        $_GET = ['refs' => 'nope'];
        self::assertSame(422, $c->files(new Request())->statusCode());
        $_GET = ['refs' => $mine['ref']];
        $files = json_decode($c->files(new Request())->body(), true);
        self::assertSame($mine['ref'], $files['files'][0]['ref']);

        // Relais COMSPEC Link : forme du paquet, tout dans « settings » (seul bloc libre gardé par la DLL).
        $pack = json_decode($c->packResponse(SseCoverSyncService::OP_LIST, 'v=1', 3)->body(), true);
        self::assertSame(['version', 'generated_at', 'map', 'op', 'settings'], array_keys($pack));
        self::assertSame('~covers', $pack['op']);
        self::assertSame(3, $pack['map']);
        self::assertSame($mine['ref'], $pack['settings']['covers'][0]['ref']);
        $pack = json_decode($c->packResponse(SseCoverSyncService::OP_FILES . $mine['ref'], 'v=1', 1)->body(), true);
        self::assertSame('comspec-cover', $pack['settings']['files'][0]['format']);
        self::assertSame([], $pack['settings']['errors']);
        self::assertSame(422, $c->packResponse(SseCoverSyncService::OP_FILES . 'x', 'v=1', 1)->statusCode());
    }
}
