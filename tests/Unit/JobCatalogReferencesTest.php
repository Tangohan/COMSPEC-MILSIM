<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Doctrine\DoctrineLibraryCatalog;
use App\Services\Personnel\JobCatalogQualificationLinker;
use App\Services\Personnel\JobPrerequisiteService;
use App\Services\Personnel\QualificationCatalogImporter;
use App\Services\Personnel\QualificationReferenceWriter;
use App\Support\JobCatalog\JobCatalog;
use App\Support\QualificationCatalog\QualificationCatalog;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Références typiques des emplois du catalogue (brevets, qualifications, formations, doctrines) : chacune porte une
 * clé stable (reference_keys) vers un élément réel du catalogue de qualifications ou du référentiel doctrinal, et
 * « Rattacher à la fonction » l’ajoute aux prérequis de l’emploi (qualification importée à la volée, doctrine du
 * référentiel en critère doctrine_ref).
 */
final class JobCatalogReferencesTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public function testEveryTypicalReferenceOfEveryJobOfEveryCountryResolvesToAnExistingCatalogElement(): void
    {
        $orphans = [];
        $countries = [];
        foreach (JobCatalog::rawEntries() as $job) {
            $fr = is_array($job['qualifications']['fr'] ?? null) ? $job['qualifications']['fr'] : [];
            $keys = JobCatalog::referenceKeys($job);
            self::assertCount(count($fr), $keys, $job['id'] . ' : une clé par référence citée');
            foreach ($fr as $i => $text) {
                $key = $keys[$i] ?? '';
                if ($key === '' || JobCatalog::resolveReference($key, 'fr') === null || JobCatalog::resolveReference($key, 'en') === null) {
                    $orphans[] = $job['id'] . ' : « ' . $text . ' » → ' . ($key !== '' ? $key : '(aucune clé)');
                }
            }
            $countries[(string) $job['country']] = true;
            self::assertNotContains('reference_keys invalides', JobCatalog::problems($job), $job['id']);
        }
        self::assertSame([], $orphans, 'références orphelines');
        self::assertSame(['US', 'FR', 'DE', 'CN', 'RU', 'MY', 'NATO'], array_keys($countries));
    }

    public function testJobSheetServesEachReferenceInTheReaderLanguageWithItsKind(): void
    {
        $kinds = [];
        foreach (JobCatalog::entries('en') as $entry) {
            self::assertCount(count($entry['qualifications']), $entry['references'], $entry['id']);
            foreach ($entry['references'] as $i => $ref) {
                self::assertIsArray($ref, $entry['id']);
                self::assertNotSame('', $ref['name']);
                $kinds[$ref['kind']] = true;
                if ($ref['kind'] === 'qualification') {
                    self::assertSame($ref['id'], $entry['qualification_links'][$i], 'sous-fiche de la qualification');
                    self::assertContains($ref['id'], $entry['qualification_refs']);
                }
            }
        }
        self::assertArrayHasKey('doctrine', $kinds);
        self::assertArrayHasKey('qualification', $kinds);
        // Exemple de la demande : emploi SOCC de l’OTAN, doctrine AJP-3.5.
        $socc = null;
        foreach (JobCatalog::entries('fr') as $entry) {
            foreach ($entry['references'] as $ref) {
                if ($ref['kind'] === 'doctrine' && $ref['code'] === 'AJP-3.5') {
                    $socc = $ref;
                }
            }
        }
        self::assertNotNull($socc, 'AJP-3.5 créée dans le référentiel doctrinal');
        self::assertNotNull(DoctrineLibraryCatalog::find($socc['id']));
        self::assertSame(['qualification_refs invalides'], array_values(array_filter(JobCatalog::problems(['qualification_refs' => ['Pas un id']]), static fn (string $p): bool => str_contains($p, 'qualification_refs'))));
        self::assertContains('reference_keys invalides', JobCatalog::problems(['qualifications' => ['fr' => ['A'], 'en' => ['A']], 'reference_keys' => ['autre:x']]));
    }

    public function testNewCountryPacksAreRegisteredAndKeepTheirOriginalLanguage(): void
    {
        foreach (['cn' => ['CN', 'zh', '/\p{Han}/u'], 'ru' => ['RU', 'ru', '/\p{Cyrillic}/u']] as $pack => [$country, $lang, $script]) {
            $data = QualificationCatalog::pack($pack);
            self::assertIsArray($data, $pack);
            self::assertSame([$country, $lang], [$data['country'], $data['official_lang']]);
            $original = 0;
            foreach (QualificationCatalog::rawEntries($pack) as $e) {
                $original += preg_match($script, (string) $e['official_name']) === 1 ? 1 : 0;
            }
            self::assertGreaterThan(count(QualificationCatalog::rawEntries($pack)) / 2, $original, $pack . ' : intitulés officiels dans la langue d’origine');
            self::assertSame($country, QualificationCatalog::entries('fr', $pack)[0]['country']);
        }
    }

    private static function base(): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        foreach ([
            'CREATE TABLE personnel_qualification_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, code TEXT, name TEXT, short_name TEXT, description TEXT, category_id INTEGER, default_issuer_id INTEGER, default_validity_months INTEGER, validity_days INTEGER, is_permanent INTEGER, is_active INTEGER, created_by INTEGER, updated_by INTEGER, archived_at TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE qualification_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, sort_order INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE qualification_issuers (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, short_name TEXT, issuer_kind TEXT, parent_issuer_id INTEGER, description TEXT, created_by INTEGER)',
            'CREATE TABLE personnel_job_role_requirements (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, job_role_id INTEGER, criteria_json TEXT, updated_by INTEGER, updated_at TEXT)',
            'CREATE TABLE personnel_job_role_waivers (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER)',
            'CREATE TABLE doctrine_library_links (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, entry_key TEXT, document_id INTEGER, linked_by INTEGER, unlinked_at TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE document_doctrines (document_id INTEGER, tenant_id INTEGER, scope TEXT, reference_code TEXT, doctrine_status TEXT)',
            'CREATE TABLE document_versions (id INTEGER PRIMARY KEY AUTOINCREMENT, document_id INTEGER, is_current INTEGER)',
            'CREATE TABLE document_views (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, version_id INTEGER)',
        ] as $sql) {
            $pdo->exec($sql);
        }

        return $pdo;
    }

    public function testAttachToFunctionImportsTheQualificationAndAddsTheDoctrineAsPrerequisites(): void
    {
        $pdo = self::base();
        $prerequisites = new JobPrerequisiteService($pdo);
        $linker = new JobCatalogQualificationLinker(new QualificationCatalogImporter(new QualificationReferenceWriter($pdo)), $prerequisites);
        $doctrine = DoctrineLibraryCatalog::all()[0];

        $result = $linker->applyReferences(7, 5, ['qualification:nato-jtac', 'doctrine:' . $doctrine['key'], 'doctrine:nato-inconnue', 'pas une clé'], 'fr', 3);
        self::assertSame(['imported' => 1, 'existing' => 0, 'added' => 2], $result);
        $items = $prerequisites->requirements(7, 5);
        $jtac = (int) $pdo->query("SELECT id FROM personnel_qualification_definitions WHERE tenant_id = 7 AND code = 'OTAN-JTAC'")->fetchColumn();
        self::assertGreaterThan(0, $jtac, 'qualification importée à la volée');
        self::assertSame([['type' => 'qualification', 'definition_id' => $jtac], ['type' => 'doctrine_ref', 'key' => $doctrine['key'], 'mode' => 'read']], $items);
        self::assertSame(0, $linker->applyReferences(7, 5, ['qualification:nato-jtac', 'doctrine:' . $doctrine['key']], 'fr', 3)['added'], 'relance : rien en double');

        // Doctrine sans document de la communauté : non mesurable, ne bloque jamais.
        $eval = $prerequisites->evaluate(7, 11, [$items[1]]);
        self::assertNull($eval['items'][0]['met']);
        // Document rattaché à la publication et lu : tenue.
        $pdo->exec("INSERT INTO doctrine_library_links (tenant_id, entry_key, document_id) VALUES (7, '" . $doctrine['key'] . "', 90)");
        $pdo->exec('INSERT INTO document_versions (id, document_id, is_current) VALUES (300, 90, 1)');
        $fresh = new JobPrerequisiteService($pdo);
        self::assertFalse($fresh->evaluate(7, 11, [$items[1]])['items'][0]['met']);
        $pdo->exec('INSERT INTO document_views (tenant_id, user_id, version_id) VALUES (7, 11, 300)');
        self::assertTrue((new JobPrerequisiteService($pdo))->evaluate(7, 11, [$items[1]])['items'][0]['met']);
        $parts = JobPrerequisiteService::describe($items[1]);
        self::assertSame('Doctrine lue', $parts['label']);
        self::assertStringStartsWith($doctrine['code'] . ' · ', $parts['target']);
        self::assertSame($items[1], JobPrerequisiteService::normalizeCriterion(['type' => 'doctrine_ref', 'key' => $doctrine['key']]));
        self::assertNull(JobPrerequisiteService::normalizeCriterion(['type' => 'doctrine_ref', 'key' => 'Pas une clé']));
    }

    public function testJobSheetLinksEachReferenceAndOffersAttachToFunction(): void
    {
        $modal = (string) file_get_contents(self::ROOT . '/views/admin/effectifs_workspace/partials/job_catalog_modal.php');
        self::assertStringContainsString('data-doctrine-url=', $modal);
        self::assertStringContainsString('Rattacher à la fonction', $modal);
        self::assertStringContainsString('data-job-catalog-copy="qualifications">Ajouter à la description', $modal, 'copie dans la description gardée');
        $js = (string) file_get_contents(self::ROOT . '/public/assets/js/job-catalog.js');
        self::assertStringContainsString("'catalog_reference_keys[]'", $js);
        self::assertStringContainsString("dataset.doctrineUrl + '#ref-'", $js);
        $controller = (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/EffectifsWorkspaceController.php');
        self::assertStringContainsString("input('catalog_reference_keys'", $controller);
        $editor = (string) file_get_contents(self::ROOT . '/views/admin/effectifs_workspace/partials/job_prerequisites.php');
        self::assertStringContainsString('value="doctrine_ref"', $editor, 'critère conservé à la modification des prérequis');
    }
}
