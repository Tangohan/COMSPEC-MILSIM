<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\MilitaryUnitRepository;
use App\Services\Community\MilitaryReferentialService;
use App\Services\Community\RealUnitAffiliationCatalog;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Intégrité du référentiel militaire (nécessite BDD migrée).
 */
final class MilitaryReferentialIntegrityTest extends TestCase
{
    private MilitaryUnitRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            $this->repo = new MilitaryUnitRepository();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Base de données indisponible pour le référentiel militaire : ' . $e->getMessage());
        }
        if (!$this->repo->tablesReady()) {
            $this->markTestSkipped('Tables military_* absentes — exécuter les migrations.');
        }
        $count = count($this->repo->listAll(false));
        if ($count === 0) {
            $this->markTestSkipped('Référentiel vide — seed non exécuté.');
        }
    }

    public function testCatalogHasNoHardcodedUnitLists(): void
    {
        $ref = new ReflectionClass(RealUnitAffiliationCatalog::class);
        $source = (string) file_get_contents($ref->getFileName() ?: '');
        $this->assertStringNotContainsString("'fr-cdo-hubert'", $source);
        $this->assertStringNotContainsString('franceUnits', $source);
        $this->assertStringContainsString('MilitaryReferentialService', $source);
    }

    public function testLegacyCodesExist(): void
    {
        foreach (['fr-cdo-hubert', 'fr-1rpima', 'fr-cos', 'fr-bfsa', 'us-usasoc', 'us-ussocom', 'us-5sfg', 'us-cia', 'us-mrr'] as $code) {
            $u = $this->repo->findByCode($code);
            $this->assertNotNull($u, "Code legacy manquant : {$code}");
            $this->assertSame(1, (int) ($u['active'] ?? 0));
        }
    }

    public function testFrenchCastAndBfsaHierarchy(): void
    {
        $cast = $this->repo->findByCode('fr-bfst');
        $this->assertNotNull($cast);
        $this->assertSame('CAST', (string) ($cast['short_name'] ?? ''));
        $this->assertSame('fr-cos', (string) ($cast['parent_code'] ?? ''));

        $cpa10 = $this->repo->findByCode('fr-cpa10');
        $this->assertNotNull($cpa10);
        $this->assertSame('fr-bfsa', (string) ($cpa10['parent_code'] ?? ''));

        $poitou = $this->repo->findByCode('fr-et-poitou');
        $this->assertNotNull($poitou);
        $this->assertSame('fr-bfsa', (string) ($poitou['parent_code'] ?? ''));
    }

    public function testHubertHierarchyAndAliases(): void
    {
        $hubert = $this->repo->findByCode('fr-cdo-hubert');
        $this->assertNotNull($hubert);
        $this->assertSame('fr-forfusco', (string) ($hubert['parent_code'] ?? ''));

        $aliases = $this->repo->listAliases((int) $hubert['id']);
        $aliasValues = array_map(static fn (array $a): string => (string) $a['alias'], $aliases);
        $this->assertContains('Hubert', $aliasValues);

        $hits = $this->repo->search('Hubert', 'FR');
        $codes = array_column($hits, 'code');
        $this->assertContains('fr-cdo-hubert', $codes);
    }

    public function testSearch1rpimaAlias(): void
    {
        $hits = $this->repo->search('1RPIMA', 'FR');
        $codes = array_column($hits, 'code');
        $this->assertContains('fr-1rpima', $codes);
    }

    public function testSearchPlongeeSpecialty(): void
    {
        $hits = $this->repo->search('plongée', 'FR');
        $codes = array_column($hits, 'code');
        $this->assertContains('fr-cdo-hubert', $codes);
    }

    public function testUsasocDescendantsInSearch(): void
    {
        $hits = $this->repo->search('USASOC', 'US');
        $codes = array_column($hits, 'code');
        $this->assertContains('us-usasoc', $codes);
        $this->assertContains('us-5sfg', $codes);
        $this->assertContains('us-75rr', $codes);
        $this->assertContains('us-95cab', $codes);
        $this->assertContains('us-160soar', $codes);
    }

    public function testNswAndMarsocHierarchy(): void
    {
        $st1 = $this->repo->findByCode('us-seal-team-1');
        $this->assertNotNull($st1);
        $this->assertSame('us-nswg1', (string) ($st1['parent_code'] ?? ''));

        $devgru = $this->repo->findByCode('us-devgru');
        $this->assertNotNull($devgru);
        $this->assertSame('us-nswc', (string) ($devgru['parent_code'] ?? ''));

        $mrb1 = $this->repo->findByCode('us-mrb-1');
        $this->assertNotNull($mrb1);
        $this->assertSame('us-mrr', (string) ($mrb1['parent_code'] ?? ''));
    }

    public function testFrontendPayloadFromDatabase(): void
    {
        $svc = new MilitaryReferentialService($this->repo);
        $payload = $svc->frontendPayload();
        $this->assertArrayHasKey('FR', $payload['countries']);
        $this->assertNotEmpty($payload['units']['FR'] ?? []);
        $ids = array_column($payload['units']['FR'], 'id');
        $this->assertContains('fr-cdo-hubert', $ids);
    }

    public function testParentChainForFifthSfg(): void
    {
        $unit = $this->repo->findByCode('us-5sfg');
        $this->assertNotNull($unit);
        $ancestors = $this->repo->getAncestors((int) $unit['id']);
        $codes = array_column($ancestors, 'code');
        $this->assertContains('us-1sfc', $codes);
        $this->assertContains('us-usasoc', $codes);
        $this->assertContains('us-ussocom', $codes);
    }
}
