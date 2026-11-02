<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Academy\AcademyOffer;
use App\Services\Billing\QualificationCatalogQuota;
use App\Services\Billing\SubscriptionPlanFeaturesCatalog;
use App\Services\Personnel\QualificationCatalogImporter;
use App\Services\Personnel\QualificationIssuerCatalogImporter;
use App\Services\Personnel\QualificationReferenceWriter;
use App\Support\QualificationCatalog\CatalogCountry;
use App\Support\QualificationCatalog\QualificationCatalog;
use App\Support\QualificationCatalog\QualificationIssuerCatalog;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Catalogue des émetteurs réels (config/qualification_issuers), son lien avec le catalogue de qualifications
 * (issuer_key), l’import sans doublon, les plafonds de formule et le catalogue de référence dans l’Academy.
 */
final class QualificationIssuerCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        QualificationCatalog::useDirectory(null);
        QualificationIssuerCatalog::useDirectory(null);
    }

    public function testEveryRealIssuerIsValidWithUniqueKeysAndAliases(): void
    {
        $all = QualificationIssuerCatalog::all();
        self::assertGreaterThan(150, count($all));
        $forms = [];
        $countries = [];
        foreach (QualificationIssuerCatalog::packs() as $pack => $data) {
            self::assertArrayHasKey((string) $data['country'], QualificationCatalog::COUNTRIES, $pack);
            $keys = [];
            foreach ($data['issuers'] as $issuer) {
                self::assertSame([], QualificationIssuerCatalog::problems($issuer), (string) ($issuer['key'] ?? '?'));
                self::assertStringStartsWith($pack . '-', (string) $issuer['key']);
                self::assertNotContains($issuer['key'], $keys, 'clé en double');
                $keys[] = $issuer['key'];
                foreach (QualificationIssuerCatalog::forms($issuer)['names'] as $form) {
                    self::assertFalse(isset($forms[$form]) && $forms[$form] !== $issuer['key'], 'nom ou alias partagé : ' . $form);
                    $forms[$form] = $issuer['key'];
                }
            }
            $countries[$data['country']] = count($keys);
        }
        foreach (['NATO', 'US', 'FR', 'DE'] as $c) {
            self::assertGreaterThanOrEqual(25, $countries[$c] ?? 0, $c);
        }
    }

    public function testEveryIssuerKeyOfTheQualificationCatalogResolves(): void
    {
        $linked = 0;
        foreach (QualificationCatalog::rawEntries() as $entry) {
            self::assertSame([], QualificationCatalog::problems($entry), (string) $entry['id']);
            if (!array_key_exists('issuer_key', $entry)) {
                continue;
            }
            $linked++;
            $issuer = QualificationIssuerCatalog::find((string) $entry['issuer_key']);
            self::assertNotNull($issuer, $entry['id'] . ' → ' . $entry['issuer_key']);
            $own = QualificationIssuerCatalog::norm((string) ($entry['issuer']['name'] ?? ''));
            self::assertContains($own, QualificationIssuerCatalog::forms($issuer)['names'], 'émetteur cité par l’entrée = nom ou alias de l’émetteur réel : ' . $entry['id']);
            self::assertSame((string) $issuer['name'], QualificationCatalog::issuerOf($entry, 'fr')['name']);
        }
        self::assertGreaterThan(250, $linked);
    }

    private static function pdo(): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE personnel_qualification_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, code TEXT, name TEXT, short_name TEXT, description TEXT, category_id INTEGER, default_issuer_id INTEGER, default_validity_months INTEGER, validity_days INTEGER, is_permanent INTEGER, is_active INTEGER, created_by INTEGER, updated_by INTEGER, archived_at TEXT, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE qualification_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, sort_order INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE qualification_issuers (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, short_name TEXT, issuer_kind TEXT, parent_issuer_id INTEGER, description TEXT, created_by INTEGER, archived_at TEXT)');

        return $pdo;
    }

    public function testRealIssuerImportIsIdempotentMatchesBySigleAndNeverTouchesExistingIssuers(): void
    {
        $pdo = self::pdo();
        $pdo->exec("INSERT INTO qualification_issuers (tenant_id, name, short_name, issuer_kind, description) VALUES (7, 'Notre école para', 'ETAP', 'unit', 'Texte de la communauté')");
        $importer = new QualificationIssuerCatalogImporter(new QualificationReferenceWriter($pdo));
        $first = $importer->import(7, ['fr-etap', 'us-usajfkswcs', 'nato-nso', 'inconnu-x'], 'fr', 3);
        self::assertSame(['Notre école para'], array_map(static fn (): string => 'Notre école para', $first['skipped']), 'ETAP déjà présent par son sigle');
        self::assertCount(2, $first['created']);
        self::assertSame(1, $first['unknown']);
        $row = $pdo->query("SELECT * FROM qualification_issuers WHERE short_name = 'USAJFKSWCS'")->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('school', $row['issuer_kind']);
        self::assertStringContainsString('sans valeur réelle', (string) $row['description']);
        self::assertSame(['Notre école para', 'unit', 'Texte de la communauté'], array_values($pdo->query("SELECT name, issuer_kind, description FROM qualification_issuers WHERE short_name = 'ETAP'")->fetch(\PDO::FETCH_ASSOC)), 'jamais modifié');

        $again = $importer->import(7, ['fr-etap', 'us-usajfkswcs', 'nato-nso'], 'fr', 3);
        self::assertSame([], $again['created']);
        self::assertSame(3, (int) $pdo->query('SELECT COUNT(*) FROM qualification_issuers WHERE tenant_id = 7')->fetchColumn());
        self::assertArrayHasKey('fr-etap', $importer->matched(7));

        // Plafond de formule : au-delà, rien n’est créé.
        $capped = (new QualificationIssuerCatalogImporter(new QualificationReferenceWriter($pdo)))->import(8, ['fr-etap', 'us-usajfkswcs', 'nato-nso'], 'fr', 3, 1);
        self::assertCount(1, $capped['created']);
        self::assertCount(2, $capped['blocked']);
    }

    public function testImportingAQualificationBringsItsRealIssuerOnce(): void
    {
        $entries = array_values(array_filter(QualificationCatalog::rawEntries('us'), static fn (array $e): bool => isset($e['issuer_key'])));
        $byKey = [];
        foreach ($entries as $e) {
            $byKey[$e['issuer_key']][] = $e['id'];
        }
        $shared = array_values(array_filter($byKey, static fn (array $ids): bool => count($ids) >= 2))[0];
        $pdo = self::pdo();
        $importer = new QualificationCatalogImporter(new QualificationReferenceWriter($pdo));
        $result = $importer->import(7, [$shared[0], $shared[1]], 'fr', 3);
        self::assertCount(2, $result['created']);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM qualification_issuers WHERE tenant_id = 7')->fetchColumn(), 'un seul émetteur réel pour les deux');
        $issuer = QualificationIssuerCatalog::find((string) QualificationCatalog::findRaw($shared[0])['issuer_key']);
        self::assertSame((string) $issuer['name'], $pdo->query('SELECT name FROM qualification_issuers WHERE tenant_id = 7')->fetchColumn());

        // Plafond : la troisième est bloquée, les deux premières restent.
        $more = array_values(array_diff(array_column($entries, 'id'), $shared));
        $capped = $importer->import(7, [$more[0], $more[1]], 'fr', 3, 1);
        self::assertCount(1, $capped['created']);
        self::assertCount(1, $capped['blocked']);
        self::assertSame([], $importer->import(7, [$shared[0]], 'fr', 3)['created'], 'relance : rien en double');
    }

    public function testQuotaCountsWhatIsInTheRegistryAndOnlyBlocksAdditions(): void
    {
        $pdo = self::pdo();
        $codes = array_slice(array_map(static fn (array $e): string => (string) $e['code'], QualificationCatalog::rawEntries('fr')), 0, 3);
        foreach ($codes as $c) {
            $pdo->exec("INSERT INTO personnel_qualification_definitions (tenant_id, code, name) VALUES (7, '" . $c . "', 'x')");
        }
        $pdo->exec("INSERT INTO personnel_qualification_definitions (tenant_id, code, name) VALUES (7, 'MAISON', 'x'), (7, 'ACAD-JTAC', 'x')");
        $pdo->exec("INSERT INTO personnel_qualification_definitions (tenant_id, code, name, archived_at) VALUES (7, 'VIEILLE', 'x', '2026-01-01')");
        $pdo->exec("INSERT INTO qualification_issuers (tenant_id, name, short_name) VALUES (7, 'École des troupes aéroportées', 'ETAP'), (7, 'Maison', 'MSN')");
        $used = QualificationCatalogQuota::countUsed(7, $pdo);
        self::assertSame([QualificationCatalogQuota::IMPORTS => 3, QualificationCatalogQuota::ISSUERS => 1, QualificationCatalogQuota::CUSTOM => 1], $used);

        $free = SubscriptionPlanFeaturesCatalog::defaultsForSlug('free');
        $quota = new QualificationCatalogQuota([QualificationCatalogQuota::IMPORTS => 3, QualificationCatalogQuota::CUSTOM => (int) $free[QualificationCatalogQuota::CUSTOM]], $used, false);
        self::assertFalse($quota->canAdd(QualificationCatalogQuota::IMPORTS));
        self::assertSame(0, $quota->remaining(QualificationCatalogQuota::IMPORTS));
        self::assertTrue($quota->canAdd(QualificationCatalogQuota::CUSTOM));
        self::assertNull($quota->remaining(QualificationCatalogQuota::ISSUERS), 'clé absente = illimité');
        self::assertFalse($quota->bulk());
        self::assertSame('standard', QualificationCatalogQuota::upgradeOffer(QualificationCatalogQuota::IMPORTS, 10)['slug']);
        self::assertSame('pro', QualificationCatalogQuota::upgradeOffer(QualificationCatalogQuota::BULK)['slug']);
        self::assertTrue(QualificationCatalogQuota::unlimited()->canAdd(QualificationCatalogQuota::IMPORTS, 1000));
        foreach ([QualificationCatalogQuota::IMPORTS, QualificationCatalogQuota::ISSUERS, QualificationCatalogQuota::CUSTOM] as $key) {
            self::assertContains($key, SubscriptionPlanFeaturesCatalog::INT_FEATURES);
            self::assertSame(0, SubscriptionPlanFeaturesCatalog::defaultsForSlug('pro_plus')[$key], 'Pro+ : illimité');
            self::assertGreaterThan(0, SubscriptionPlanFeaturesCatalog::defaultsForSlug('free')[$key], 'gratuite : plafonnée');
        }
    }

    public function testAcademyCatalogueShowsReferenceEntriesWithTheirOrigin(): void
    {
        $rows = [
            ['id' => 'fr-bmp', 'code' => 'FR-BMP', 'name' => 'Brevet militaire de parachutiste', 'official_name' => 'Brevet militaire de parachutiste', 'short_name' => 'BMP', 'description' => 'd', 'issuer' => 'ETAP', 'issuer_key' => 'fr-etap',
                'category' => 'airborne', 'category_label' => 'Parachutisme', 'country' => 'FR', 'country_label' => 'France', 'validity_months' => null, 'level' => ['key' => 'initial', 'rank' => 1, 'estimated' => true], 'jobs_count' => 2, 'academy' => null],
            ['id' => 'us-sfqc', 'code' => 'US-SFQC', 'name' => 'Special Forces Qualification Course', 'official_name' => '', 'short_name' => 'SFQC', 'description' => 'd', 'issuer' => 'SWCS', 'issuer_key' => '',
                'category' => 'selection', 'category_label' => 'Sélection', 'country' => 'US', 'country_label' => 'États-Unis', 'validity_months' => null, 'level' => ['key' => 'confirmed', 'rank' => 3, 'estimated' => true], 'jobs_count' => 0, 'academy' => null],
        ];
        $offer = AcademyOffer::assemble(['tenant_name' => 'T', 'reference' => $rows, 'definitions' => [
            4 => ['id' => 4, 'code' => 'fr-bmp', 'name' => 'BMP chez nous'], 5 => ['id' => 5, 'code' => 'MAISON', 'name' => 'Qualif maison'],
        ]]);
        self::assertSame(1, $offer['references'], 'entrée importée : remplacée par la fiche du référentiel');
        $mine = AcademyOffer::find($offer['items'], 'q-4');
        self::assertSame(['referentiel', 'fr-bmp', 'FR', 'brevet'], [$mine['origin'], $mine['ref_id'], $mine['country'], $mine['nature']]);
        self::assertSame('communaute', AcademyOffer::find($offer['items'], 'q-5')['origin']);
        $ref = AcademyOffer::find($offer['items'], 'r-us-sfqc');
        self::assertSame(['reference', 'catalogue', 'cursus'], [$ref['kind'], $ref['origin'], $ref['nature']]);
        self::assertSame(2, $offer['totals']['tout'], 'le catalogue de référence n’est pas dans « Tout »');

        $f = AcademyOffer::filters(['onglet' => 'qualifications', 'pays' => 'us', 'origine' => 'catalogue', 'page' => '2']);
        self::assertSame(['US', 'catalogue', '2'], [$f['pays'], $f['origine'], $f['page']]);
        self::assertSame(['r-us-sfqc'], array_column(AcademyOffer::filter($offer['items'], ['page' => ''] + $f), 'id'));
        self::assertSame(['q-4'], array_column(AcademyOffer::filter($offer['items'], AcademyOffer::filters(['onglet' => 'qualifications', 'q' => 'parachutiste'])), 'id'));
        self::assertSame([], AcademyOffer::filter($offer['items'], AcademyOffer::filters(['onglet' => 'tout', 'q' => 'special forces'])), '« Tout » sans le catalogue');
        self::assertSame('licence', AcademyOffer::nature('Licence de pilote'));
        self::assertTrue(AcademyOffer::needsReference(['onglet' => 'brevets']));
        self::assertFalse(AcademyOffer::needsReference(['onglet' => 'formations']));
        self::assertStringContainsString('<svg', CatalogCountry::flag('DE'));
        self::assertSame('Россия', CatalogCountry::native('RU'));
        self::assertSame('Malaysia', CatalogCountry::native('MY'));
        self::assertStringContainsString('<svg', CatalogCountry::flag('MY'));
    }
}
