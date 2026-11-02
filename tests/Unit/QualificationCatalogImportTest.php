<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\QualificationCatalogImporter;
use App\Services\Personnel\QualificationReferenceWriter;
use App\Support\QualificationCatalog\QualificationCatalog;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Catalogue OTAN des qualifications : pack livré valide et sourcé, import dans le référentiel d’une communauté au
 * choix, sans doublon, avec l’émetteur « OTAN (référence) » et la mention « qualification de jeu ».
 */
final class QualificationCatalogImportTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    protected function tearDown(): void
    {
        QualificationCatalog::useDirectory(null);
    }

    public function testShippedNatoCatalogIsValidUniqueSourcedAndBilingual(): void
    {
        $pack = QualificationCatalog::pack('nato');
        self::assertIsArray($pack, 'config/qualification_catalog/nato.json livré et lisible');
        self::assertSame(QualificationCatalog::PACKS, array_keys(QualificationCatalog::packs()));
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $pack['compiled_at']);
        foreach (['fr', 'en'] as $l) {
            self::assertNotSame('', trim((string) $pack['sources_note'][$l]));
            self::assertNotSame('', trim((string) $pack['issuer']['description'][$l]));
        }
        self::assertSame('OTAN (référence)', $pack['issuer']['name']);
        $entries = QualificationCatalog::rawEntries('nato');
        self::assertGreaterThanOrEqual(15, count($entries));
        self::assertLessThanOrEqual(60, count($entries));
        $problems = [];
        foreach ($entries as $e) {
            foreach (QualificationCatalog::problems($e) as $p) {
                $problems[] = $e['id'] . ' : ' . $p;
            }
            self::assertStringStartsWith('nato-', (string) $e['id']);
            self::assertStringStartsWith('OTAN-', (string) $e['code']);
        }
        self::assertSame([], $problems);
        self::assertSame(count($entries), count(array_unique(array_column($entries, 'id'))), 'identifiant en double');
        self::assertSame(count($entries), count(array_unique(array_column($entries, 'code'))), 'code en double');
        $all = (string) json_encode($entries, JSON_UNESCAPED_UNICODE);
        self::assertStringContainsString('STANAG 3797', $all, 'JTAC selon le STANAG 3797');
        // Servi dans la langue du lecteur.
        $first = $entries[0];
        self::assertSame(trim($first['name']['en']), QualificationCatalog::entries('en', 'nato')[0]['name']);
        self::assertSame(trim($first['name']['fr']), QualificationCatalog::entries('fr-FR', 'nato')[0]['name']);
    }

    private function useFixture(): void
    {
        $dir = sys_get_temp_dir() . '/athena-qualcat-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $entry = static fn (string $id, string $code, string $cat, string $fr, string $en): array => [
            'id' => $id, 'code' => $code, 'category' => $cat, 'name' => ['fr' => $fr, 'en' => $en], 'short_name' => 'X',
            'description' => ['fr' => 'Description française.', 'en' => 'English description.'],
            'body' => ['fr' => 'JAPCC', 'en' => 'JAPCC'], 'reference' => ['fr' => 'STANAG 3797', 'en' => 'STANAG 3797'],
            'prerequisites' => ['fr' => ['Pré-requis'], 'en' => ['Prerequisite']], 'validity' => ['fr' => '', 'en' => ''],
            'validity_months' => null, 'sources' => [['url' => 'https://www.japcc.org/', 'title' => 'JAPCC']],
        ];
        file_put_contents($dir . '/nato.json', json_encode([
            'pack' => 'nato', 'label' => ['fr' => 'OTAN', 'en' => 'NATO'], 'compiled_at' => '2026-11-02',
            'issuer' => ['name' => 'OTAN (référence)', 'short_name' => 'OTAN', 'description' => ['fr' => 'Émetteur de référence.', 'en' => 'Reference issuer.']],
            'sources_note' => ['fr' => 'Sources publiques.', 'en' => 'Public sources.'],
            'entries' => [
                $entry('nato-jtac', 'OTAN-JTAC', 'air_support', 'JTAC qualifié', 'Qualified JTAC'),
                $entry('nato-jfo', 'OTAN-JFO', 'fires', 'Observateur JFO', 'Joint Fires Observer'),
                $entry('nato-slp', 'OTAN-SLP', 'c2_staff', 'Niveau de langue', 'Language proficiency'),
            ],
        ], JSON_UNESCAPED_UNICODE));
        QualificationCatalog::useDirectory($dir);
    }

    private static function base(): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        foreach ([
            'CREATE TABLE personnel_qualification_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, code TEXT, name TEXT, short_name TEXT, description TEXT, category_id INTEGER, default_issuer_id INTEGER, default_validity_months INTEGER, validity_days INTEGER, is_permanent INTEGER, is_active INTEGER, created_by INTEGER, updated_by INTEGER, archived_at TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE qualification_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, sort_order INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE qualification_issuers (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, short_name TEXT, issuer_kind TEXT, parent_issuer_id INTEGER, description TEXT, created_by INTEGER)',
            "INSERT INTO personnel_qualification_definitions (id, tenant_id, code, name) VALUES (40, 7, 'otan-jfo', 'JFO déjà saisi'), (41, 8, 'OTAN-JTAC', 'Autre communauté')",
        ] as $sql) {
            $pdo->exec($sql);
        }

        return $pdo;
    }

    public function testImportCreatesChosenQualificationsOnceWithTheNatoIssuerAndTheGameMention(): void
    {
        $this->useFixture();
        $pdo = self::base();
        $importer = new QualificationCatalogImporter(new QualificationReferenceWriter($pdo));

        $first = $importer->import(7, 'nato', ['nato-jtac', 'nato-jfo', 'inconnu'], 'fr', 3);
        self::assertSame(['JTAC qualifié'], $first['created']);
        self::assertSame(['Observateur JFO'], $first['skipped'], 'code déjà présent (casse ignorée) : pas de doublon');
        self::assertSame(1, $first['unknown']);
        $def = $pdo->query("SELECT * FROM personnel_qualification_definitions WHERE tenant_id = 7 AND code = 'OTAN-JTAC'")->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('JTAC qualifié', $def['name']);
        self::assertStringContainsString('Qualification de jeu inspirée du standard OTAN, sans valeur réelle.', (string) $def['description']);
        self::assertStringContainsString('Référence publique : STANAG 3797', (string) $def['description']);
        self::assertStringContainsString('https://www.japcc.org/', (string) $def['description']);
        self::assertSame(1, (int) $def['is_permanent']);
        $issuer = $pdo->query('SELECT * FROM qualification_issuers WHERE id = ' . (int) $def['default_issuer_id'])->fetch(\PDO::FETCH_ASSOC);
        self::assertSame(['OTAN (référence)', 'OTAN', 'external', 7], [$issuer['name'], $issuer['short_name'], $issuer['issuer_kind'], (int) $issuer['tenant_id']]);
        self::assertSame($first['issuer_id'], (int) $issuer['id']);
        self::assertSame('OTAN — Appui aérien', $pdo->query('SELECT name FROM qualification_categories WHERE id = ' . (int) $def['category_id'])->fetchColumn());

        // Relance et « tout importer » : rien en double, l’émetteur est réutilisé.
        $again = $importer->import(7, 'nato', ['nato-jtac', 'nato-jfo', 'nato-slp'], 'fr', 3);
        self::assertSame(['Niveau de langue'], $again['created']);
        self::assertCount(2, $again['skipped']);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM qualification_issuers WHERE tenant_id = 7')->fetchColumn());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM personnel_qualification_definitions WHERE tenant_id = 7 AND code = 'OTAN-JTAC'")->fetchColumn());
        $codes = $importer->importedCodes(7);
        ksort($codes);
        self::assertSame(['OTAN-JFO' => true, 'OTAN-JTAC' => true, 'OTAN-SLP' => true], $codes);

        // Autre communauté, anglophone : sa propre fiche et son propre émetteur, en anglais.
        $en = $importer->import(9, 'nato', ['nato-jfo'], 'en', null);
        self::assertSame(['Joint Fires Observer'], $en['created']);
        $desc = (string) $pdo->query("SELECT description FROM personnel_qualification_definitions WHERE tenant_id = 9")->fetchColumn();
        self::assertStringContainsString('In-game qualification inspired by the NATO standard, with no real-world value.', $desc);
        self::assertSame('NATO — Fire support', $pdo->query('SELECT name FROM qualification_categories WHERE tenant_id = 9')->fetchColumn());
        self::assertSame('Autre communauté', $pdo->query("SELECT name FROM personnel_qualification_definitions WHERE id = 41")->fetchColumn(), 'fiche existante jamais modifiée');
    }

    public function testCatalogPageAndImportAreRoutedAndTheQualificationsPageLinksToThem(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        self::assertStringContainsString("'/back-office/referentiels/qualifications/catalogue-otan', [QualificationReferentielController::class, 'catalog']", $routes);
        self::assertStringContainsString("'/back-office/referentiels/qualifications/catalogue-otan/importer', [QualificationReferentielController::class, 'importCatalog']", $routes);
        self::assertLessThan(strpos($routes, "qualifications/{id}', [QualificationRulesController::class, 'show']"), strpos($routes, 'catalogue-otan'), 'avant la fiche {id}');
        $index = (string) file_get_contents(self::ROOT . '/views/admin/organization/qualifications/index.php');
        self::assertStringContainsString('Importer depuis le catalogue OTAN', $index);
        $view = (string) file_get_contents(self::ROOT . '/views/admin/organization/qualifications/catalog.php');
        self::assertStringContainsString('Qualification de jeu inspirée du standard OTAN, sans valeur réelle.', $view);
    }
}
