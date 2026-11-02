<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\QualificationIssuerRepository;
use App\Services\Personnel\QualificationIssuerCatalogImporter;
use App\Services\Personnel\QualificationReferenceWriter;
use App\Support\QualificationCatalog\IssuerDetails;
use App\Support\QualificationCatalog\IssuerFieldsMigration;
use App\Support\QualificationCatalog\QualificationIssuerCatalog;
use PDO;
use PHPUnit\Framework\TestCase;

/** Étape 3680 : champs des émetteurs (pays, localisation, site, présentation anglaise, clé du catalogue). */
final class QualificationIssuerFieldsTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE qualification_issuers (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, short_name TEXT, issuer_kind TEXT, parent_issuer_id INTEGER, description TEXT, unit_id INTEGER, signatory_name TEXT, signatory_title TEXT, logo_media_path TEXT, archived_at TEXT, created_by INTEGER)');

        return $pdo;
    }

    /** @return array<string, mixed> */
    private function row(PDO $pdo, int $id): array
    {
        return $pdo->query('SELECT * FROM qualification_issuers WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
    }

    public function testMigrationExtractsEachFormAndIsIdempotent(): void
    {
        $pdo = $this->pdo();
        $created = IssuerDetails::compose(['description' => "Centre CAS.\nContact : Discord.", 'description_en' => 'CAS centre.', 'country' => 'FR', 'location' => 'Canjuers', 'website' => 'https://www.exemple.fr']);
        $cnec = QualificationIssuerCatalog::find('fr-cnec');
        self::assertNotNull($cnec);
        $imported = QualificationIssuerCatalog::description($cnec, 'fr');
        $importedEn = QualificationIssuerCatalog::description($cnec, 'en');
        $ins = $pdo->prepare('INSERT INTO qualification_issuers (tenant_id, name, short_name, issuer_kind, description) VALUES (?, ?, ?, ?, ?)');
        $ins->execute([7, 'Centre CAS', 'CI-CAS', 'training_center', $created]);
        $ins->execute([7, (string) $cnec['name'], 'CNEC', 'training_center', $imported]);
        $ins->execute([8, (string) $cnec['name'], 'CNEC', 'training_center', $importedEn]);
        $ins->execute([7, 'Libre', null, 'unit', 'Texte libre seulement.']);
        $ins->execute([7, 'Pays seul', null, 'unit', "Country: Germany"]);
        $ins->execute([7, 'Pays inconnu', null, 'unit', "Pays : Atlantide"]);

        $first = IssuerFieldsMigration::run($pdo);
        self::assertNotNull($first);
        self::assertContains('country_code', $first['added']);
        self::assertSame(4, $first['migrated']);

        $a = $this->row($pdo, 1);
        self::assertSame(["Centre CAS.\nContact : Discord.", 'FR', 'Canjuers', 'https://www.exemple.fr', 'CAS centre.', null], [$a['description'], $a['country_code'], $a['location'], $a['website_url'], $a['description_en'], $a['issuer_key']]);

        $b = $this->row($pdo, 2);
        self::assertSame(['fr-cnec', 'training_center', 'FR'], [$b['issuer_key'], $b['issuer_type'], $b['country_code']]);
        self::assertStringStartsWith('https://', (string) $b['website_url']);
        self::assertNotSame('', (string) $b['location']);
        self::assertNotSame('', (string) $b['description_en']);
        self::assertStringNotContainsString('Localisation :', (string) $b['description']);
        self::assertStringNotContainsString('Site officiel :', (string) $b['description']);
        self::assertStringContainsString('Émetteur de jeu inspiré', (string) $b['description']);
        self::assertStringContainsString('Sources :', (string) $b['description']);

        $c = $this->row($pdo, 3);
        self::assertSame('fr-cnec', $c['issuer_key']);
        self::assertStringNotContainsString('Location:', (string) $c['description']);
        self::assertSame('Mont-Louis and Collioure, France', $c['location'], 'localisation de la présentation conservée');

        self::assertSame(['Texte libre seulement.', null], array_values(array_intersect_key($this->row($pdo, 4), ['description' => 1, 'country_code' => 1])));
        self::assertSame(['DE', null], [$this->row($pdo, 5)['country_code'], $this->row($pdo, 5)['description']]);
        self::assertSame(['Pays : Atlantide', null], [$this->row($pdo, 6)['description'], $this->row($pdo, 6)['country_code']]);

        $before = $pdo->query('SELECT * FROM qualification_issuers ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $second = IssuerFieldsMigration::run($pdo);
        self::assertSame(['added' => [], 'migrated' => 0], $second);
        self::assertSame($before, $pdo->query('SELECT * FROM qualification_issuers ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testMigrationNeverOverwritesPresentValues(): void
    {
        $pdo = $this->pdo();
        IssuerFieldsMigration::run($pdo);
        $pdo->exec("INSERT INTO qualification_issuers (tenant_id, name, issuer_kind, description, country_code, location) VALUES (7, 'X', 'unit', 'Texte\n\nPays : France\nLocalisation : Ailleurs', 'US', 'Fort Bragg')");
        IssuerFieldsMigration::run($pdo);
        $r = $this->row($pdo, 1);
        self::assertSame(['US', 'Fort Bragg', 'Texte'], [$r['country_code'], $r['location'], $r['description']]);
    }

    public function testRepositoryCreateEditAndCountryFilter(): void
    {
        $pdo = $this->pdo();
        IssuerFieldsMigration::run($pdo);
        $repo = new QualificationIssuerRepository($pdo);
        self::assertTrue($repo->fieldsReady());
        $id = $repo->create(7, ['name' => 'École ALAT', 'issuer_kind' => 'school', 'description' => 'Pilotes.', 'description_en' => 'Pilots.', 'country_code' => 'fr', 'location' => 'Dax', 'website_url' => 'https://www.exemple.fr'], 3);
        $repo->create(7, ['name' => 'Fort', 'issuer_kind' => 'unit', 'country_code' => 'US', 'website_url' => 'pas une adresse']);
        $repo->create(7, ['name' => 'Sans pays', 'issuer_kind' => 'school', 'country_code' => 'ZZ']);
        $r = $this->row($pdo, $id);
        self::assertSame(['Pilotes.', 'Pilots.', 'FR', 'Dax', 'https://www.exemple.fr'], [$r['description'], $r['description_en'], $r['country_code'], $r['location'], $r['website_url']]);
        self::assertNull($this->row($pdo, 2)['website_url']);
        self::assertNull($this->row($pdo, 3)['country_code']);

        $repo->update(7, $id, ['name' => 'École ALAT', 'issuer_kind' => 'school', 'description' => 'Pilotes.', 'country_code' => 'DE', 'location' => '', 'website_url' => '']);
        $r = $this->row($pdo, $id);
        self::assertSame(['DE', null, null, null], [$r['country_code'], $r['location'], $r['website_url'], $r['description_en']]);

        self::assertSame(['École ALAT'], array_column($repo->listForTenant(7, ['country' => 'DE']), 'name'));
        self::assertSame(['Fort'], array_column($repo->listForTenant(7, ['country' => 'us']), 'name'));
        self::assertSame(['Sans pays', 'École ALAT'], array_column($repo->listForTenant(7, ['kind' => 'school']), 'name'));
        self::assertCount(3, $repo->listForTenant(7, ['country' => 'ZZ']));
    }

    public function testRepositoryFallsBackToDescriptionLines(): void
    {
        $pdo = $this->pdo();
        $repo = new QualificationIssuerRepository($pdo);
        self::assertFalse($repo->fieldsReady());
        $repo->create(7, ['name' => 'Ancien', 'issuer_kind' => 'unit', 'description' => 'Texte.', 'country_code' => 'GB', 'location' => 'Brecon']);
        self::assertStringContainsString('Pays : Royaume-Uni', (string) $this->row($pdo, 1)['description']);
        $row = $repo->listForTenant(7, ['country' => 'GB'])[0];
        self::assertSame(['Texte.', 'GB', 'Brecon'], [$row['description'], $row['country_code'], $row['location']]);
    }

    public function testRealImportFillsIssuerKeyAndFields(): void
    {
        $pdo = $this->pdo();
        IssuerFieldsMigration::run($pdo);
        $importer = new QualificationIssuerCatalogImporter(new QualificationReferenceWriter($pdo));
        $out = $importer->import(7, ['fr-cnec'], 'fr', 3);
        self::assertCount(1, $out['created']);
        $r = $this->row($pdo, 1);
        self::assertSame(['fr-cnec', 'training_center', 'FR'], [$r['issuer_key'], $r['issuer_type'], $r['country_code']]);
        self::assertStringStartsWith('https://', (string) $r['website_url']);
        self::assertNotSame('', (string) $r['description_en']);
        self::assertStringNotContainsString('Site officiel :', (string) $r['description']);

        // Renommé dans la communauté : retrouvé par sa clé, rien en double.
        $pdo->exec("UPDATE qualification_issuers SET name = 'Mon centre commando', short_name = 'MCC'");
        $again = (new QualificationIssuerCatalogImporter(new QualificationReferenceWriter($pdo)))->import(7, ['fr-cnec'], 'fr', 3);
        self::assertSame([], $again['created']);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM qualification_issuers')->fetchColumn());
    }
}
