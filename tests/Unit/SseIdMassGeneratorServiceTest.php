<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdKitRepository;
use App\Repositories\SseIdOperationRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseWatchlistRepository;
use App\Services\Sse\SseIdentityPackService;
use App\Services\Sse\SseIdMassGeneratorService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Préparation de mission en masse (COMSPEC IDENTITY, Lot 6) : tirage déterministe, parts de faux / expirés / volés,
 * vérité réservée à l'habilitation Confidentiel, enregistrement du lot, pack étendu (population[], wanted[] avec les
 * camps qui y ont accès), retrait d'un lot, plafond de 500 papiers, cloison des communautés.
 */
final class SseIdMassGeneratorServiceTest extends TestCase
{
    private PDO $pdo;

    /** @var list<array<string, mixed>> */
    private array $audit = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseIdentitySqlite::open();
        $this->audit = [];
        SseIdMassGeneratorService::useAuditSink(function (array $e): void {
            $this->audit[] = $e;
        });
    }

    protected function tearDown(): void
    {
        SseIdMassGeneratorService::useAuditSink(null);
        SseIdentitySqlite::close();
    }

    /** @return array<string, array<string, mixed>> */
    private function countries(int $tenant): array
    {
        $out = [];
        foreach ((new SseIdCountryRepository())->listForTenant($tenant) as $c) {
            $out[$c['code']] = $c;
        }

        return $out;
    }

    /** @param array<string, array<string, int|string>> $cats */
    private function params(array $cats, bool $truth = true, array $extra = []): array
    {
        return SseIdMassGeneratorService::normalizeParams($extra + [
            'countries' => ['ALT', 'MLD'],
            'doc_types' => ['id_card', 'passport'],
            'age_min' => 20,
            'age_max' => 40,
            'female_pct' => 30,
            'visible_sides' => ['west'],
            'categories' => $cats,
        ], array_keys($this->countries(1)), $truth);
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

    public function testPreviewIsDeterministicForASeedAndRespectsTheParameters(): void
    {
        $params = $this->params(['civilian' => ['count' => 12], 'military' => ['count' => 4, 'side' => 'east', 'faction' => 'Armée de Malden'], 'hvt' => ['count' => 2]]);
        $gen = new SseIdMassGeneratorService();
        $a = $gen->preview($params, $this->countries(1), 4242, '2026-11-03');
        $b = $gen->preview($params, $this->countries(1), 4242, '2026-11-03');
        $c = $gen->preview($params, $this->countries(1), 4243, '2026-11-03');

        self::assertCount(18, $a);
        self::assertSame($a, $b, 'même graine, mêmes paramètres : même population');
        self::assertNotSame(array_column($a, 'last_name'), array_column($c, 'last_name'));
        $sum = SseIdMassGeneratorService::summary($a);
        self::assertSame(['civilian' => 12, 'military' => 4, 'hvt' => 2], $sum['by_category']);
        foreach ($a as $p) {
            self::assertGreaterThanOrEqual(20, $p['age']);
            self::assertLessThanOrEqual(40, $p['age']);
            self::assertSame(2026 - $p['age'], (int) substr($p['birth_date'], 0, 4));
            self::assertContains($p['nationality'], ['ALT', 'MLD']);
            self::assertNotEmpty($p['documents']);
            if ($p['category'] === 'military') {
                self::assertSame('east', $p['side']);
                self::assertSame('Armée de Malden', $p['faction']);
                self::assertSame('Militaire', $p['profession']);
            }
            if ($p['category'] === 'hvt') {
                self::assertSame('hvt', $p['wanted_level']);
                self::assertNotSame('', $p['alias']);
            }
            if ($p['category'] === 'civilian') {
                self::assertSame('civ', $p['side']);
                self::assertNull($p['wanted_level']);
            }
        }
    }

    public function testShareOfForgedExpiredAndStolenPapersAndTruthGate(): void
    {
        $gen = new SseIdMassGeneratorService();
        $forged = $gen->preview($this->params(['suspect' => ['count' => 10, 'forged' => 100]]), $this->countries(1), 7, '2026-11-03');
        foreach ($forged as $p) {
            foreach ($p['documents'] as $d) {
                self::assertSame('forged', $d['flag']);
                self::assertSame('forged', $d['st_origin']);
                self::assertContains($d['forgery_kind'], ['counterfeit', 'photo_swap', 'data_alteration', 'stolen_blank']);
                self::assertGreaterThanOrEqual(30, $d['forgery_quality']);
            }
        }
        $expired = $gen->preview($this->params(['civilian' => ['count' => 8, 'expired' => 100]]), $this->countries(1), 7, '2026-11-03');
        foreach ($expired as $p) {
            foreach ($p['documents'] as $d) {
                self::assertSame('expired', $d['st_validity']);
                self::assertLessThan('2026-11-03', $d['expires_on']);
            }
        }
        $stolen = $gen->preview($this->params(['civilian' => ['count' => 8, 'stolen' => 100]]), $this->countries(1), 7, '2026-11-03');
        self::assertSame(['stolen'], array_values(array_unique(array_merge(...array_map(static fn (array $p): array => array_column($p['documents'], 'st_report'), $stolen)))));

        // Sans habilitation Confidentiel : aucun faux, même demandé.
        $noTruth = $this->params(['suspect' => ['count' => 5, 'forged' => 100, 'expired' => 10]], false);
        self::assertSame(0, $noTruth['categories']['suspect']['forged']);
        self::assertSame(10, $noTruth['categories']['suspect']['expired']);
        // Plafonds : 100 par catégorie, 250 en tout.
        $big = $this->params(['civilian' => ['count' => 999], 'military' => ['count' => 100], 'suspect' => ['count' => 100]]);
        self::assertSame(100, $big['categories']['civilian']['count']);
        self::assertSame(250, SseIdMassGeneratorService::total($big));
    }

    public function testCommitStoresTheBatchAndExtendsThePackForTheOperation(): void
    {
        $params = $this->params(['civilian' => ['count' => 6], 'suspect' => ['count' => 2, 'side' => 'guer'], 'hvt' => ['count' => 1, 'side' => 'east']]);
        $res = (new SseIdMassGeneratorService())->commit(1, null, 'OP-LOT6', $params, 991, 5);
        self::assertTrue($res['ok']);
        self::assertSame(9, $res['identities']);
        self::assertSame(3, $res['wanted']);
        self::assertGreaterThanOrEqual(9, $res['documents']);

        $rows = $this->pdo->query("SELECT kind, batch_id, category, side, op_key FROM sse_identities WHERE tenant_id = 1")->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(9, $rows);
        foreach ($rows as $r) {
            self::assertSame('generated', $r['kind']);
            self::assertSame($res['batch_id'], (int) $r['batch_id']);
            self::assertSame('OP-LOT6', $r['op_key']);
        }
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM sse_id_documents WHERE tenant_id = 1 AND workflow <> 'issued'")->fetchColumn());

        $pack = $this->pack(1, 'OP-LOT6');
        self::assertCount(9, $pack['population']);
        $docIds = array_column($pack['documents'], 'id');
        $identityIds = array_column($pack['identities'], 'id');
        foreach ($pack['population'] as $p) {
            self::assertSame(['identity_id', 'side', 'category', 'faction', 'zone', 'language', 'documents'], array_keys($p));
            self::assertContains($p['identity_id'], $identityIds);
            foreach ($p['documents'] as $d) {
                self::assertContains($d, $docIds, 'les papiers de la population sont dans documents[]');
            }
        }
        self::assertCount(3, $pack['wanted']);
        foreach ($pack['wanted'] as $w) {
            self::assertSame(['ref', 'identity_id', 'person_id', 'last_name', 'first_name', 'alias', 'level', 'sides', 'zone', 'documents', 'has_photo'], array_keys($w));
            self::assertSame(['west'], $w['sides']);
            self::assertContains($w['level'], ['recherche', 'hvt']);
            self::assertNotEmpty($w['documents']);
        }
        // Hors opération : rien de l'opération ; autre communauté : rien du tout.
        self::assertSame([], $this->pack(1, null)['population']);
        self::assertSame([], $this->pack(2, 'OP-LOT6')['population']);
        self::assertSame([], $this->pack(2, 'OP-LOT6')['wanted']);

        // Retrait du lot : papiers révoqués, population et recherchés sortis du pack.
        $revoked = (new SseIdMassGeneratorService())->archive(1, (int) $res['batch_id'], 5);
        self::assertSame($res['documents'], $revoked);
        $after = $this->pack(1, 'OP-LOT6');
        self::assertSame(['id_batch.create', 'id_batch.archive'], array_column($this->audit, 'action'));
        self::assertSame(1, $this->audit[0]['tenant_id']);
        self::assertSame(5, $this->audit[0]['actor_user_id']);
        self::assertSame([], $after['population']);
        self::assertSame([], $after['wanted']);
        self::assertSame([], $after['documents']);
    }

    public function testCommitRefusesToExceedThePackCapAndNeedsACountry(): void
    {
        $gen = new SseIdMassGeneratorService();
        $params = $this->params(['civilian' => ['count' => 100], 'military' => ['count' => 100], 'suspect' => ['count' => 50]], true, ['doc_types' => ['id_card', 'passport', 'driving_license']]);
        $res = $gen->commit(1, null, null, $params, 3, 5);
        self::assertFalse($res['ok']);
        self::assertStringContainsString('500', (string) $res['error']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_id_batches')->fetchColumn());

        $none = SseIdMassGeneratorService::normalizeParams(['categories' => ['civilian' => ['count' => 3]]], [], true);
        self::assertSame([], $gen->preview($none, [], 3));
    }
}
