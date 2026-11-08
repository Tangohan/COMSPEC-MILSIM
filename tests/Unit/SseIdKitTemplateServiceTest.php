<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdKitRepository;
use App\Repositories\SseIdKitTemplateRepository;
use App\Repositories\SseIdOperationRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseWatchlistRepository;
use App\Services\Sse\SseIdentityPackService;
use App\Services\Sse\SseIdKitAssignmentService;
use App\Services\Sse\SseIdMassGeneratorService;
use App\Support\SseIdWorkshopCatalog;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Kits documentaires types (COMSPEC IDENTITY, Lot 6) : modèles réutilisables, affectation à des membres (papiers,
 * kit de mission sse_id_kits, brief de couverture), couverture réservée à l'habilitation Confidentiel, pack étendu
 * (kits[] et briefs[] liés au Steam ID), cloison des communautés.
 */
final class SseIdKitTemplateServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseIdentitySqlite::open();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (10, 1, '76561198000000010', 'Joueur'), (11, 1, NULL, 'Sans Steam'), (20, 2, '76561198000000020', 'Autre')");
        SseIdMassGeneratorService::useAuditSink(static function (array $e): void {
        });
    }

    protected function tearDown(): void
    {
        SseIdMassGeneratorService::useAuditSink(null);
        SseIdentitySqlite::close();
    }

    private function pack(int $tenant, ?string $op): array
    {
        $watch = $this->createMock(SseWatchlistRepository::class);
        $watch->method('listActive')->willReturn([]);
        $persons = $this->createMock(SsePersonRepository::class);
        $persons->method('listForContext')->willReturn([]);

        return (new SseIdentityPackService(new SseIdDocumentRepository(), new SseIdentityRepository(), new SseIdCountryRepository(), $watch, $persons, new SseIdKitRepository(), new SseIdOperationRepository()))
            ->build($tenant, 1, $op);
    }

    private function template(int $tenant, string $preset, array $over = []): array
    {
        $repo = new SseIdKitTemplateRepository();
        $id = $repo->create($tenant, $over + SseIdWorkshopCatalog::KIT_PRESETS[$preset] + ['category' => $preset, 'country_code' => 'ALT', 'phone' => '+990 12 34 56', 'address' => '4 rue des Oliviers, Kavala'], 1);

        return (array) $repo->find($id, $tenant);
    }

    public function testTemplatesAreNormalizedListedArchivedAndCloisonnedByCommunity(): void
    {
        $repo = new SseIdKitTemplateRepository();
        $t = $this->template(1, 'journalist', ['doc_types' => ['passport', 'bogus'], 'print' => ['service' => 'Presse étrangère', 'unknown' => 'x'], 'layout' => 'nope']);
        self::assertSame('journalist', $t['category']);
        self::assertSame(['passport'], $t['doc_types']);
        self::assertSame(['service' => 'Presse étrangère'], $t['print']);
        self::assertSame('modern', $t['layout']);
        self::assertSame('alias', $t['identity_mode']);
        self::assertMatchesRegularExpression('/^KIT-\d{4}$/', $t['ref']);
        self::assertNull($repo->find((int) $t['id'], 2), 'kit d’une autre communauté invisible');
        self::assertCount(1, $repo->list(1));
        self::assertTrue($repo->setArchived((int) $t['id'], 1, true));
        self::assertSame([], $repo->list(1));
        self::assertCount(1, $repo->list(1, true));
        self::assertFalse($repo->setArchived((int) $t['id'], 2, false));
    }

    public function testAssigningAKitCreatesPapersMissionKitAndBriefPerMember(): void
    {
        // Le joueur 10 a une identité principale ; le 11 non.
        (new SseIdentityRepository())->create(1, ['kind' => 'civil', 'last_name' => 'Vlachos', 'first_name' => 'Petros', 'sex' => 'M', 'birth_date' => '1991-02-03', 'nationality' => 'ALT', 'user_id' => 10]);
        $t = $this->template(1, 'civil');
        $res = (new SseIdKitAssignmentService())->assign(1, $t, [10, 11], 1, 'OP-K', true, false, 7);
        self::assertTrue($res['ok']);
        self::assertSame(1, $res['members']);
        self::assertSame([11], $res['skipped']);
        self::assertSame(2, $res['documents'], 'carte + permis du kit civil');

        $docs = (new SseIdDocumentRepository())->list(1, ['user_id' => 10]);
        self::assertCount(2, $docs);
        foreach ($docs as $d) {
            self::assertSame('issued', $d['workflow']);
            self::assertSame('ALT', $d['country_code']);
            self::assertSame('OP-K', $d['op_key']);
            self::assertSame('+990 12 34 56', $d['print']['phone']);
        }

        $pack = $this->pack(1, 'OP-K');
        self::assertCount(1, $pack['kits']);
        self::assertSame('76561198000000010', $pack['kits'][0]['uid']);
        self::assertSame('operation', $pack['kits'][0]['scope']);
        self::assertEqualsCanonicalizing(array_column($docs, 'id'), $pack['kits'][0]['documents']);
        self::assertCount(1, $pack['briefs']);
        $b = $pack['briefs'][0];
        self::assertSame(['uid', 'user_id', 'scope', 'identity_id', 'title', 'cover_name', 'biography', 'legend', 'contacts', 'instructions', 'phone', 'address'], array_slice(array_keys($b), 0, 12), 'Champs du contrat d’abord, dans le même ordre');
        self::assertSame('76561198000000010', $b['uid']);
        self::assertSame('VLACHOS Petros', $b['cover_name']);
        self::assertSame($t['legend'], $b['legend']);
        self::assertSame([], $this->pack(2, 'OP-K')['briefs']);
        // Le joueur relit son brief (Ma situation › Mes papiers).
        self::assertCount(1, (new SseIdOperationRepository())->briefsForUser(1, 10));
        self::assertSame([], (new SseIdOperationRepository())->briefsForUser(2, 10));
    }

    public function testCoverKitsNeedConfidentialClearanceAndAliasKitsCreateTheirOwnIdentity(): void
    {
        $cover = $this->template(1, 'undercover');
        $refused = (new SseIdKitAssignmentService())->assign(1, $cover, [10], null, null, true, false, 7);
        self::assertFalse($refused['ok']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identities')->fetchColumn());

        $ok = (new SseIdKitAssignmentService())->assign(1, $cover, [10], null, null, false, true, 7, 1234);
        self::assertTrue($ok['ok']);
        $ids = (new SseIdentityRepository())->listForUser(1, 10);
        self::assertSame('cover', $ids[0]['kind']);
        foreach ((new SseIdDocumentRepository())->list(1, ['user_id' => 10]) as $d) {
            self::assertSame('cover', $d['st_origin']);
            self::assertSame('draft', $d['workflow'], 'non émis à la demande');
        }

        $alias = $this->template(1, 'contractor');
        (new SseIdKitAssignmentService())->assign(1, $alias, [10], null, null, true, false, 7);
        $kinds = array_column((new SseIdentityRepository())->listForUser(1, 10), 'kind');
        self::assertContains('alias', $kinds);
        // Le kit par défaut a été remplacé par les papiers du dernier kit affecté.
        $kits = (new SseIdKitRepository())->listForUser(1, 10);
        self::assertCount(1, $kits);
        self::assertSame('default', $kits[0]['scope']);
    }
}
