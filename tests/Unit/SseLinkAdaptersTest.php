<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\Links\SseLinkAdapter;
use App\Services\Sse\Links\SseLinkAdapters;
use App\Services\Sse\Links\SseLinkGraph;
use App\Support\SseLinkCatalog;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySseLinkViewer;
use Tests\Support\SseLinksFixture;
use Tests\Support\SseLinksSqlite;

/**
 * Adaptateurs d'éléments : un par type, avec référence, titre, résumé, adresse, date et marques de confidentialité ; communauté
 * toujours bornée ; registre extensible sans toucher au graphe.
 */
final class SseLinkAdaptersTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseLinksSqlite::open();
        $this->ids = SseLinksFixture::seed($this->pdo);
    }

    protected function tearDown(): void
    {
        SseLinksSqlite::close();
    }

    /** @return array<string, mixed> */
    private function one(string $type, int $id, int $tenant = 1): array
    {
        return SseLinkAdapters::standard()->get($type)?->describe($tenant, [(string) $id])[(string) $id] ?? [];
    }

    public function testEveryTypeOfTheCatalogueHasAnAdapterExceptJnet(): void
    {
        $adapters = SseLinkAdapters::standard();
        foreach (array_keys(SseLinkCatalog::TYPES) as $type) {
            if ($type === 'jnet') {
                self::assertNull($adapters->get($type), 'les contenus JNET passent par la passerelle, pas par un adaptateur SSE');
                continue;
            }
            self::assertNotNull($adapters->get($type), $type);
            self::assertSame($type, $adapters->get($type)->type());
        }
    }

    public function testReferencesTitlesAndAddressesOfTheMainTypes(): void
    {
        $p = $this->one('person', $this->ids['person']);
        self::assertSame('IDN-00001', $p['ref']);
        self::assertSame('Élodie Karimzadeh', $p['title']);
        self::assertSame('atak/sse/identites/' . $this->ids['person'], $p['href']);
        $i = $this->one('identity', $this->ids['identity']);
        self::assertSame('IDT-000001', $i['ref']);
        self::assertSame('atak/sse/papiers/identites/' . $this->ids['identity'], $i['href']);
        self::assertFalse($i['secret']);
        $d = $this->one('paper', $this->ids['paper1']);
        self::assertSame('DOC-000001', $d['ref']);
        self::assertSame('Passeport TAK', $d['title']);
        $c = $this->one('check', $this->ids['check2']);
        self::assertSame('CTRL-' . $this->ids['check2'], $c['ref']);
        self::assertSame('atak/sse/papiers/controles/' . $this->ids['check2'], $c['href']);
        self::assertStringContainsString('ALPHA-2', $c['summary']);
        self::assertSame('REC-0001', $this->one('seek', $this->ids['seek'])['ref']);
        self::assertSame('A-0001/2026', $this->one('notice', $this->ids['notice'])['ref']);
        $x = $this->one('doc_issue', $this->ids['issue1']);
        self::assertSame('AR-2026-0001', $x['ref']);
        self::assertSame('encadrement', $x['classification']);
        self::assertSame('SSE-2026-0001', $this->one('case', $this->ids['case'])['ref']);
        self::assertSame('FR-2026-0004', $this->one('note', $this->ids['note'])['ref']);
        self::assertSame('atak/sse/transmissions/' . $this->ids['transmission'], $this->one('transmission', $this->ids['transmission'])['href']);
        self::assertSame('validé', $this->one('document', $this->ids['document'])['summary']);
        self::assertSame('Entrepôt du port', $this->one('site', $this->ids['site'])['title']);
    }

    public function testConfidentialityMarksComeFromTheDataNotFromTheReader(): void
    {
        self::assertTrue($this->one('identity', $this->ids['cover_identity'])['secret'], 'couverture');
        self::assertTrue($this->one('identity', $this->ids['member_identity'])['secret'], 'alias de membre');
        self::assertSame(9, $this->one('identity', $this->ids['member_identity'])['member']);
        self::assertNull($this->one('identity', $this->ids['identity'])['member']);
        self::assertSame('confidentiel', $this->one('case', $this->ids['case_secret'])['classification']);
        $interest = $this->pdo->exec("INSERT INTO sse_interest_cases (tenant_id, reference_code, temporary_designation, created_by, created_at) VALUES (1, 'INT-1', 'Sujet', 7, '2026-11-01 00:00:00')");
        self::assertSame(1, $interest);
        self::assertTrue($this->one('interest', (int) $this->pdo->lastInsertId())['interest']);
    }

    public function testAnotherCommunityOrAMissingElementIsNotDescribed(): void
    {
        self::assertSame([], $this->one('person', $this->ids['person'], 2));
        self::assertSame([], $this->one('person', 999999));
        self::assertSame([], SseLinkAdapters::standard()->get('person')->describe(0, [(string) $this->ids['person']]));
        self::assertSame([], SseLinkAdapters::standard()->get('person')->describe(1, []));
    }

    public function testManyIdsAreDescribedInOneCall(): void
    {
        $got = SseLinkAdapters::standard()->get('photo')->describe(1, [(string) $this->ids['photo1'], (string) $this->ids['photo2'], (string) $this->ids['photo3'], '999999']);
        self::assertCount(3, $got);
        self::assertSame('Cliché 2', $got[(string) $this->ids['photo2']]['title']);
    }

    public function testSearchFindsByReferenceNameAndNumberWithinTheCommunity(): void
    {
        $adapters = SseLinkAdapters::standard();
        $byName = $adapters->get('person')->search(1, 'karim', 5);
        self::assertCount(1, $byName);
        self::assertSame('IDN-00001', $byName[0]['ref']);
        self::assertCount(1, $adapters->get('identity')->search(1, 'Karimzadeh', 5));
        self::assertCount(1, $adapters->get('paper')->search(1, 'TK1234567', 5));
        self::assertCount(2, $adapters->get('case')->search(1, 'SSE-2026', 5));
        self::assertSame([], $adapters->get('person')->search(2, 'karim', 5), 'autre communauté');
        self::assertSame([], $adapters->get('person')->search(1, 'k', 5), 'deux caractères au moins');
        // Les jokers de LIKE saisis par l'utilisateur ne ratissent pas tout.
        self::assertSame([], $adapters->get('person')->search(1, '%%', 5));
        self::assertSame([], $adapters->get('person')->search(1, '__', 5));
    }

    public function testANewTypeIsPluggedInWithOneClassAndTheGraphUsesIt(): void
    {
        $adapters = SseLinkAdapters::standard();
        $adapters->register(new class () implements SseLinkAdapter {
            public function type(): string
            {
                return 'campaign';
            }

            public function describe(int $tenantId, array $ids): array
            {
                return $tenantId === 1 && in_array('77', $ids, true) ? ['77' => ['ref' => 'CAM-0077', 'title' => 'Campagne de test', 'summary' => '', 'href' => 'atak/sse/campagnes/77', 'date' => '2026-11-01 00:00:00', 'secret' => false, 'member' => null, 'classification' => null, 'interest' => false]] : [];
            }

            public function search(int $tenantId, string $query, int $limit = 8): array
            {
                return [];
            }
        });
        $this->pdo->exec("INSERT INTO sse_entity_links (tenant_id, source_type, source_id, target_type, target_id, relation, reason, rule_code, created_by, created_at) VALUES (1, 'campaign', '77', 'person', '" . $this->ids['person'] . "', 'related', 'Personnage de la campagne', 'manual.link', 'user', '2026-11-02 00:00:00')");
        $g = (new SseLinkGraph(ArraySseLinkViewer::full(), $adapters))->resolve('person', (string) $this->ids['person']);
        self::assertSame('Campagne de test', $g['nodes']['campaign:77']['title']);
        self::assertContains('campaign', $adapters->types());
    }
}
