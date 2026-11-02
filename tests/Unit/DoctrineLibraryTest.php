<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\Doctrine\DoctrineLibraryRepository;
use App\Services\Doctrine\DoctrineLibraryCatalog;
use App\Services\Doctrine\DoctrineLibraryService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Doctrine › Référentiel : catalogue livré (packs valides, références publiques sourcées, référentiel allemand),
 * étape de migration rejouable, personnalisation par communauté (ajout, masquage, catégories, rattachements) et
 * isolation entre communautés.
 */
final class DoctrineLibraryTest extends TestCase
{
    private MysqlDdlSqlitePdo $pdo;

    protected function setUp(): void
    {
        DoctrineLibraryCatalog::useDirectory(null);
        $this->pdo = new MysqlDdlSqlitePdo();
        foreach ([
            'CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT)',
            "INSERT INTO tenants (id, name) VALUES (14, 'Task Force Phantom'), (15, 'Fondation 07')",
            'CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, action TEXT, entity_type TEXT, entity_id INTEGER, old_value TEXT, new_value TEXT, created_at TEXT)',
            'CREATE TABLE documents (id INTEGER PRIMARY KEY, title TEXT)',
            'CREATE TABLE document_doctrines (id INTEGER PRIMARY KEY, document_id INTEGER, tenant_id INTEGER, reference_code TEXT, doctrine_status TEXT)',
            "INSERT INTO documents (id, title) VALUES (101, 'Procédure radio'), (202, 'SOP Fondation')",
            "INSERT INTO document_doctrines (id, document_id, tenant_id, reference_code, doctrine_status) VALUES (1, 101, 14, 'NS-2026-001', 'published'), (2, 202, 15, 'FND-001', 'draft')",
        ] as $sql) {
            $this->pdo->exec($sql);
        }
    }

    protected function tearDown(): void
    {
        DoctrineLibraryCatalog::useDirectory(null);
    }

    private function migrate(): string
    {
        $pdo = $this->pdo;
        $root = dirname(__DIR__, 2);
        ob_start();
        try {
            require $root . '/database/steps/3560_doctrine_library_custom.php';
        } finally {
            $out = (string) ob_get_clean();
        }

        return $out;
    }

    private function service(): DoctrineLibraryService
    {
        return new DoctrineLibraryService(new DoctrineLibraryRepository($this->pdo));
    }

    /* ---------- Catalogue livré ---------- */

    public function testShippedPacksAreValidPublicAndSourced(): void
    {
        self::assertSame([], DoctrineLibraryCatalog::validate());
        $packs = DoctrineLibraryCatalog::packs();
        self::assertSame(DoctrineLibraryCatalog::PACKS, array_keys($packs));
        $keys = [];
        foreach (DoctrineLibraryCatalog::all() as $e) {
            self::assertSame('public', $e['distribution'], $e['key']);
            self::assertStringStartsWith('https://', $e['source_url'], $e['key']);
            self::assertStringNotContainsStringIgnoringCase('VS-NfD', $e['title'] . ' ' . $e['summary_fr'] . ' ' . $e['summary_en'], $e['key']);
            self::assertStringNotContainsStringIgnoringCase('diffusion restreinte', $e['title'] . ' ' . $e['summary_fr'], $e['key']);
            $keys[] = $e['key'];
        }
        self::assertSame(count($keys), count(array_unique($keys)));
        foreach (DoctrineLibraryCatalog::PACKS as $pack) {
            self::assertGreaterThanOrEqual(15, count($packs[$pack]['entries']), $pack . ' : catalogue trop maigre');
        }
    }

    public function testGermanPackCoversBundeswehrPublicationsWithGermanTitlesAndTranslations(): void
    {
        $de = DoctrineLibraryCatalog::packs()['de'] ?? null;
        self::assertNotNull($de);
        self::assertSame('DE', $de['country']);
        $codes = [];
        $types = [];
        foreach ($de['entries'] as $e) {
            self::assertSame('de', $e['lang'], $e['key']);
            self::assertNotSame($e['title'], $e['title_fr'], $e['key'] . ' : traduction française attendue');
            self::assertNotSame('', trim($e['title_en']), $e['key']);
            self::assertMatchesRegularExpression('#^https://[a-z0-9.-]+\.de/#', $e['source_url'], $e['key'] . ' : source officielle allemande attendue');
            $codes[] = $e['code'];
            $types[$e['type']] = true;
        }
        self::assertContains('A-2600/1', $codes, 'Innere Führung');
        self::assertArrayHasKey('law', $types);
        self::assertArrayHasKey('regulation', $types);
    }

    public function testInvalidEntriesAreRejected(): void
    {
        $ok = ['key' => 'de-x', 'code' => 'X', 'series' => '', 'issuer' => 'BMVg', 'issuer_short' => 'BMVg', 'type' => 'law', 'domain' => 'law',
            'level' => 'institutional', 'lang' => 'de', 'title' => 'T', 'title_fr' => 'T', 'title_en' => 'T', 'summary_fr' => 'S', 'summary_en' => 'S',
            'edition' => '', 'distribution' => 'public', 'source_url' => 'https://www.bundeswehr.de/x', 'source_label' => '', 'verified_at' => '2026-11-02'];
        self::assertSame([], DoctrineLibraryCatalog::entryErrors($ok, 'de'));
        self::assertNotSame([], DoctrineLibraryCatalog::entryErrors(['distribution' => 'restricted'] + $ok, 'de'));
        self::assertNotSame([], DoctrineLibraryCatalog::entryErrors(['source_url' => 'http://www.bundeswehr.de/x'] + $ok, 'de'));
        self::assertNotSame([], DoctrineLibraryCatalog::entryErrors(['key' => 'fr-x'] + $ok, 'de'));
        self::assertNotSame([], DoctrineLibraryCatalog::entryErrors(['domain' => 'nope'] + $ok, 'de'));
    }

    public function testTitlesAreServedInTheReaderLanguage(): void
    {
        $entry = DoctrineLibraryCatalog::all()[0];
        $fr = $this->service()->resolve(14, $entry['key'], 'fr');
        $en = $this->service()->resolve(14, $entry['key'], 'en');
        self::assertNotNull($fr);
        self::assertSame($entry['lang'] === 'fr' ? '' : $entry['title_fr'], $fr['title_local']);
        self::assertSame($entry['lang'] === 'en' ? '' : $entry['title_en'], $en['title_local']);
        self::assertSame($entry['summary_en'], $en['summary']);
        self::assertSame(DoctrineLibraryCatalog::domainLabel($entry['domain'], 'en'), $en['domain_label']);
    }

    /* ---------- Migration ---------- */

    public function testStepCreatesTablesOnceAndAReplayChangesNothing(): void
    {
        self::assertFalse((new DoctrineLibraryRepository($this->pdo))->schemaReady());
        self::assertStringContainsString('doctrine_library_entries', $this->migrate());
        $this->pdo->changes = 0;
        $this->pdo->ddl = [];
        self::assertStringContainsString('déjà présentes', $this->migrate());
        self::assertSame(0, $this->pdo->changes, implode(' | ', $this->pdo->ddl));
        self::assertTrue((new DoctrineLibraryRepository($this->pdo))->schemaReady());
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/2026_11_02_100_doctrine_library.sql');
        foreach (['uk_dlc_tenant_label', 'uk_dle_tenant_org_code', 'uk_dlh_tenant_entry', 'uk_dll_tenant_entry_document', 'fk_dle_tenant', 'fk_dlh_tenant', 'fk_dll_tenant', 'fk_dlc_tenant'] as $name) {
            self::assertStringContainsString($name, $sql);
        }
        self::assertDoesNotMatchRegularExpression('/\bDROP\b|\bTRUNCATE\b|DELETE\s+FROM/i', $sql);
    }

    public function testWithoutTheStepTheCatalogStillShowsAndWritesAreRefused(): void
    {
        $view = $this->service()->overview(14, 'fr');
        self::assertFalse($view['schema_ready']);
        self::assertSame(count(DoctrineLibraryCatalog::all()), $view['stats']['visible']);
        $this->expectException(InvalidArgumentException::class);
        (new DoctrineLibraryRepository($this->pdo))->setHidden(14, 1, DoctrineLibraryCatalog::all()[0]['key'], true);
    }

    /* ---------- Personnalisation ---------- */

    public function testCommunityAddsHidesCategorizesAndLinksWithoutTouchingOtherCommunities(): void
    {
        $this->migrate();
        $repo = new DoctrineLibraryRepository($this->pdo);
        $service = $this->service();
        $catalogKey = DoctrineLibraryCatalog::all()[0]['key'];
        $total = count(DoctrineLibraryCatalog::all());

        $cat = $repo->saveCategory(14, 7, 0, 'SOP de la coalition', '#112233');
        $id = $repo->saveEntry(14, 7, 0, DoctrineLibraryService::normalizeEntry([
            'organization' => 'Task Force Phantom', 'code' => 'TFP-SOP-03', 'title' => 'Procédures de mise en place héliportée',
            'domain' => 'cat:' . $cat, 'type' => 'tactics', 'level' => 'tactical',
            'link_url' => ['https://example.org/sop-03.pdf', ''], 'link_label' => ['PDF', ''],
        ]));
        $repo->setHidden(14, 7, $catalogKey, true);
        $repo->link(14, 7, DoctrineLibraryService::customKey($id), 101);

        $mine = $service->overview(14, 'fr');
        self::assertSame($total - 1 + 1, $mine['stats']['visible']);
        self::assertSame(1, $mine['stats']['hidden']);
        self::assertSame(1, $mine['stats']['custom']);
        self::assertSame(1, $mine['stats']['linked']);
        $custom = array_values(array_filter($mine['entries'], static fn (array $e): bool => $e['origin'] === 'custom'))[0];
        self::assertSame('SOP de la coalition', $custom['domain_label']);
        self::assertSame('https://example.org/sop-03.pdf', $custom['source_url']);
        self::assertSame(101, $custom['documents'][0]['document_id']);
        self::assertSame(1, $mine['categories'][0]['count']);

        // Autre communauté : rien de tout cela.
        $other = $service->overview(15, 'fr');
        self::assertSame($total, $other['stats']['visible']);
        self::assertSame(0, $other['stats']['hidden'] + $other['stats']['custom'] + $other['stats']['linked']);
        self::assertSame([], $other['categories']);
        self::assertNull($repo->findEntry(15, $id));
        self::assertFalse($service->knows(15, DoctrineLibraryService::customKey($id)));
        self::assertSame(['Procédure radio'], array_column($mine['documents'], 'title'));

        // Réaffichage et archivage : rien n’est supprimé.
        $repo->setHidden(14, 7, $catalogKey, false);
        $repo->setEntryArchived(14, 7, $id, true);
        $after = $service->overview(14, 'fr');
        self::assertSame($total, $after['stats']['visible']);
        self::assertSame(1, $after['stats']['archived']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM doctrine_library_hidden')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM doctrine_library_entries')->fetchColumn());
        self::assertNull($service->resolve(14, DoctrineLibraryService::customKey($id)));
        self::assertGreaterThanOrEqual(5, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action LIKE 'doctrine.library_%'")->fetchColumn());
    }

    public function testDuplicatesForeignDocumentsAndUnsafeLinksAreRefused(): void
    {
        $this->migrate();
        $repo = new DoctrineLibraryRepository($this->pdo);
        $values = DoctrineLibraryService::normalizeEntry(['organization' => 'Fondation', 'code' => 'FND-1', 'title' => 'Doctrine du site 19']);
        $id = $repo->saveEntry(15, 3, 0, $values);
        // Même code pour la même organisation : refusé ; pour une autre communauté : accepté.
        try {
            $repo->saveEntry(15, 3, 0, ['code' => 'fnd-1'] + $values);
            self::fail('Doublon accepté');
        } catch (InvalidArgumentException) {
        }
        self::assertGreaterThan(0, $repo->saveEntry(14, 3, 0, $values));
        self::assertSame($id, $repo->saveEntry(15, 3, $id, ['title' => 'Doctrine du site 19 (rév.)'] + $values));
        $repo->saveCategory(15, 3, 0, 'Univers', null);
        try {
            $repo->saveCategory(15, 3, 0, 'univers', null);
            self::fail('Catégorie en double acceptée');
        } catch (InvalidArgumentException) {
        }
        self::assertGreaterThan(0, $repo->saveCategory(14, 3, 0, 'Univers', null));
        try {
            $repo->link(15, 3, DoctrineLibraryService::customKey($id), 101);
            self::fail('Document d’une autre communauté rattaché');
        } catch (InvalidArgumentException) {
        }
        $this->expectException(InvalidArgumentException::class);
        DoctrineLibraryService::normalizeEntry(['organization' => 'X', 'code' => 'Y', 'title' => 'Z', 'link_url' => ['http://example.org']]);
    }

    public function testCustomKeysNeverCollideWithCatalogKeys(): void
    {
        self::assertSame(12, DoctrineLibraryService::customId(DoctrineLibraryService::customKey(12)));
        self::assertSame(0, DoctrineLibraryService::customId('de-a-2600-1'));
        foreach (DoctrineLibraryCatalog::all() as $e) {
            self::assertStringStartsNotWith(DoctrineLibraryCatalog::CUSTOM_PREFIX, $e['key']);
        }
    }
}
