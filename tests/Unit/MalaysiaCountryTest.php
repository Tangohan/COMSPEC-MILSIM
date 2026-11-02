<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Services\Community\CommunityOnboardingValidationService;
use App\Services\Organization\EchelonStandards;
use App\Services\Organization\GradeLevel;
use App\Services\Organization\OrgAdequacySettings;
use App\Services\OrganizationCatalog\RealUnitKitDefinitions;
use App\Support\JobCatalog\JobCatalog;
use App\Support\OfficialGradeSystems;
use App\Support\QualificationCatalog\CatalogCountry;
use App\Support\QualificationCatalog\QualificationCatalog;
use App\Support\QualificationCatalog\QualificationIssuerCatalog;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Malaisie (Angkatan Tentera Malaysia) branchée comme les autres pays : grades en malais (Tentera Darat, TLDM, TUDM)
 * avec code OTAN et équivalent anglais, référentiel d’adéquation de modèle britannique, emplois, qualifications,
 * émetteurs et modèles d’unités réelles.
 */
final class MalaysiaCountryTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public function testMalaysianGradeSystemsAreCompleteAndComparable(): void
    {
        foreach (['MY_CLASSIC', 'MY_TLDM', 'MY_TUDM'] as $system) {
            self::assertTrue(OfficialGradeSystems::has($system));
            self::assertSame('MY', OfficialGradeSystems::country($system));
            self::assertContains($system, CommunityOnboardingValidationService::GRADE_SYSTEMS);
            $grades = OfficialGradeSystems::GRADES[$system];
            $codes = array_column($grades, 0);
            self::assertSame(count($codes), count(array_unique($codes)), $system);
            self::assertContains(OfficialGradeSystems::founderGrade($system), $codes, $system . ' : grade du fondateur');
            $levels = [];
            foreach ($grades as [$code, $short, $long, $english, $nato, $category, $order, $commissioned]) {
                self::assertMatchesRegularExpression('/^[A-Z0-9]{1,10}$/', $code);
                self::assertNotSame('', $short, $code);
                self::assertNotSame('', $english, $code);
                self::assertContains($category, ['OFFICIER', 'SOUS_OFFICIER', 'MDR'], $code);
                if ($nato !== 'OF(D)') {
                    $levels[] = GradeLevel::fromCode($nato);
                    self::assertNotNull(GradeLevel::fromCode($nato), $system . ' ' . $code);
                }
                self::assertSame($english, OfficialGradeSystems::english($system, $code));
            }
            self::assertSame(18, max($levels), $system . ' : jusqu’à OF-9');
            self::assertSame(1, min($levels), $system . ' : depuis OR-1');
        }
        $army = array_column(OfficialGradeSystems::GRADES['MY_CLASSIC'], null, 0);
        self::assertSame(['Lt Kol', 'Leftenan Kolonel', 'Lieutenant Colonel', 'OF-4'], array_slice($army['LTKOL'], 1, 4));
        self::assertSame(['Kpl', 'Koperal', 'Corporal', 'OR-4'], array_slice($army['KPL'], 1, 4));
        self::assertSame(['PW I', 'Pegawai Waran I', 'Warrant Officer I', 'OR-9'], array_slice($army['PW1'], 1, 4));
        self::assertSame('my_tldm', OfficialGradeSystems::tab('MY_TLDM'));
        self::assertSame('MY_TUDM', OfficialGradeSystems::codeForTab('my_tudm'));
        self::assertSame('FR_CLASSIC', OfficialGradeSystems::normalize('GB_INCONNU'));
    }

    public function testSeedStepAddsTheMalaysianSystemsOnceWithoutTouchingOthers(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => '2026-11-02 12:00:00', 0);
        $pdo->exec('CREATE TABLE grade_categories (id INTEGER PRIMARY KEY, code TEXT)');
        $pdo->exec('CREATE TABLE grade_systems (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT, label TEXT, country_code TEXT, format_type TEXT, is_active INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE grades (id INTEGER PRIMARY KEY AUTOINCREMENT, grade_system_id INTEGER, grade_category_id INTEGER, code TEXT, label_short TEXT, label_long TEXT, label_otan TEXT, sort_order INTEGER, is_commissioned INTEGER, is_active INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec("INSERT INTO grade_categories (id, code) VALUES (1, 'OFFICIER'), (2, 'SOUS_OFFICIER'), (3, 'MDR')");
        $pdo->exec("INSERT INTO grade_systems (code, label, country_code) VALUES ('FR_CLASSIC', 'Grades français', 'FR')");
        $root = realpath(self::ROOT);
        ob_start();
        require self::ROOT . '/database/steps/3610_grade_systems_malaysia.php';
        $first = (string) ob_get_clean();
        self::assertStringContainsString('3 système(s)', $first);
        $total = count(OfficialGradeSystems::GRADES['MY_CLASSIC']) + count(OfficialGradeSystems::GRADES['MY_TLDM']) + count(OfficialGradeSystems::GRADES['MY_TUDM']);
        self::assertSame($total, (int) $pdo->query('SELECT COUNT(*) FROM grades')->fetchColumn());
        self::assertSame(['FR_CLASSIC', 'MY_CLASSIC', 'MY_TLDM', 'MY_TUDM'], $pdo->query('SELECT code FROM grade_systems ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame('MY', $pdo->query("SELECT country_code FROM grade_systems WHERE code = 'MY_TUDM'")->fetchColumn());
        $row = $pdo->query("SELECT g.label_short, g.label_long, g.label_otan, g.is_commissioned FROM grades g JOIN grade_systems s ON s.id = g.grade_system_id WHERE s.code = 'MY_CLASSIC' AND g.code = 'KAPT'")->fetch(PDO::FETCH_NUM);
        self::assertSame(['Kapt', 'Kapten', 'OF-2', 1], [$row[0], $row[1], $row[2], (int) $row[3]]);

        ob_start();
        require self::ROOT . '/database/steps/3610_grade_systems_malaysia.php';
        self::assertStringContainsString('0 système(s) et 0 grade(s)', (string) ob_get_clean(), 'relance sans effet');
        self::assertSame($total, (int) $pdo->query('SELECT COUNT(*) FROM grades')->fetchColumn());
    }

    public function testAdequacyFollowsTheBritishModelForMalaysianGrades(): void
    {
        self::assertContains(EchelonStandards::PROFILE_MY, EchelonStandards::profiles());
        $my = EchelonStandards::all(EchelonStandards::PROFILE_MY);
        self::assertSame(EchelonStandards::keys(), array_keys($my));
        self::assertSame([4, 4], $my['groupe']['leader_grade'], 'Ketua Seksyen = Koperal (OR-4)');
        self::assertSame([3, 3], $my['groupe']['deputy_grade'], 'Penolong = Lans Koperal (OR-3)');
        self::assertSame([10, 10], $my['section']['leader_grade'], 'Komander Platun = Leftenan Muda / Leftenan (OF-1)');
        self::assertSame([6, 6], $my['section']['deputy_grade'], 'Sarjan Platun (OR-6)');
        self::assertSame([13, 13], $my['bataillon']['leader_grade'], 'Pegawai Memerintah = Leftenan Kolonel (OF-4)');
        foreach ($my as $def) {
            self::assertNotSame([], $def['sources'], $def['key']);
        }
        self::assertSame(EchelonStandards::PROFILE_MY, OrgAdequacySettings::resolveProfile([], 'fr', 'MY_CLASSIC'));
        self::assertSame(EchelonStandards::PROFILE_FR, OrgAdequacySettings::resolveProfile([], 'fr', 'FR_CLASSIC'));
        self::assertSame(EchelonStandards::PROFILE_MY, OrgAdequacySettings::sanitize(['profile' => 'my'])['profile']);
    }

    public function testCountryIsRegisteredWithFlagNativeNameAndBranches(): void
    {
        self::assertSame(['fr' => 'Malaisie', 'en' => 'Malaysia'], JobCatalog::COUNTRIES['MY']);
        self::assertSame(['fr' => 'Malaisie', 'en' => 'Malaysia'], QualificationCatalog::COUNTRIES['MY']);
        self::assertSame('Malaysia', CatalogCountry::native('MY'));
        self::assertSame('Malay', CatalogCountry::language('MY', 'en'));
        self::assertStringContainsString('#010066', CatalogCountry::flag('MY'));
        self::assertStringContainsString('Polis Diraja Malaysia', JobCatalog::branchLabel('MY', 'gendarmerie', 'fr'));
        self::assertStringContainsString('Tentera Laut Diraja Malaysia', JobCatalog::branchLabel('MY', 'navy', 'en'));
        self::assertStringContainsString('data-flag="MY"', (string) file_get_contents(self::ROOT . '/public/assets/css/job-catalog.css'));
        self::assertStringContainsString('data-job-catalog-label="lang-ms">Malais<', (string) file_get_contents(self::ROOT . '/views/admin/effectifs_workspace/partials/job_catalog_modal.php'));
    }

    public function testJobsQualificationsAndIssuersKeepTheirMalayNames(): void
    {
        $pack = JobCatalog::packs()['my_sof'] ?? null;
        self::assertIsArray($pack);
        self::assertSame(['MY', 'ms'], [$pack['country'], $pack['official_lang']]);
        $jobs = array_values(array_filter(JobCatalog::rawEntries(), static fn (array $e): bool => $e['country'] === 'MY'));
        self::assertGreaterThanOrEqual(30, count($jobs));
        $commands = [];
        foreach ($jobs as $job) {
            self::assertStringStartsWith('my-', (string) $job['id']);
            self::assertNotSame('', trim((string) ($job['name']['ms'] ?? '')), $job['id'] . ' : intitulé malais');
            self::assertSame([], JobCatalog::problems($job), $job['id']);
            $commands[(string) $job['command']] = true;
            if ($job['branch'] === 'gendarmerie') {
                self::assertSame('PDRM', $job['command'], $job['id'] . ' : police');
            }
        }
        foreach (['GGK', '10 Briged Para', 'TLDM', 'TUDM', 'PDRM'] as $command) {
            self::assertArrayHasKey($command, $commands, $command);
        }
        $entry = JobCatalog::find($jobs[0]['id'], 'en');
        self::assertSame('ms', $entry['official_lang']);
        self::assertSame((string) $jobs[0]['name']['ms'], $entry['official_name']);

        $qualifs = QualificationCatalog::rawEntries('my');
        self::assertGreaterThanOrEqual(15, count($qualifs));
        $issuers = array_column(QualificationIssuerCatalog::all(), null, 'key');
        foreach ($qualifs as $q) {
            self::assertNotSame('', trim((string) $q['official_name']), $q['id']);
            if (($q['issuer_key'] ?? '') !== '') {
                self::assertArrayHasKey($q['issuer_key'], $issuers, $q['id'] . ' : émetteur réel');
            }
        }
    }

    public function testRealUnitKitsUseTheMalaysianGrades(): void
    {
        $kits = array_column(RealUnitKitDefinitions::kits(), null, 'code');
        $expect = ['my_21ggk' => 'MY_CLASSIC', 'my_10bpara' => 'MY_CLASSIC', 'my_9ramd' => 'MY_CLASSIC', 'my_paskal' => 'MY_TLDM', 'my_paskau' => 'MY_TUDM'];
        foreach ($expect as $suffix => $grade) {
            $kit = $kits[RealUnitKitDefinitions::PREFIX . $suffix] ?? null;
            self::assertIsArray($kit, $suffix);
            self::assertSame($grade, $kit['grade_system_code'], $suffix);
            self::assertSame('MY', $kit['origin']['country']);
            self::assertGreaterThan(0, RealUnitKitDefinitions::jobCatalogCount($kit), $suffix . ' : emplois liés');
        }
        $bn = $kits[RealUnitKitDefinitions::PREFIX . 'my_9ramd'];
        $echelons = array_count_values(array_column($bn['units'], 'echelon'));
        self::assertSame(5, $echelons['company'], 'quatre kompeni de fusiliers et la kompeni d’appui');
        self::assertSame(3, $echelons['platoon']);
        self::assertSame(9, $echelons['section'], 'trois seksyen par platun');
        self::assertContains('9 RAMD (Para)', array_column($kits[RealUnitKitDefinitions::PREFIX . 'my_10bpara']['units'], 'code'));
        foreach (['11 RGK', '21 Komando', '22 Komando'] as $code) {
            self::assertContains($code, array_column($kits[RealUnitKitDefinitions::PREFIX . 'my_21ggk']['units'], 'code'));
        }
    }

    public function testGradeReferentialShowsTheMalaysianTabWithEnglishEquivalent(): void
    {
        $_SESSION = ['tenant_id' => 42, '_csrf_token' => str_repeat('a', 64)];
        $GLOBALS['__app_locale'] = 'fr';
        $grade = ['id' => 9, 'code' => 'LTKOL', 'label_short' => 'Lt Kol', 'label_long' => 'Leftenan Kolonel', 'catalog_label_long' => 'Leftenan Kolonel', 'catalog_label_short' => 'Lt Kol', 'category_code' => 'OFFICIER', 'category_label' => 'Officiers', 'country_code' => 'MY', 'system_code' => 'MY_CLASSIC', 'label_otan' => 'OF-4', 'is_enabled' => 1];
        $body = Response::view('admin.organization.referentiels.grades.index', [
            'tab' => 'my', 'gradesBySystem' => ['FR_CLASSIC' => [], 'US_CLASSIC' => [], 'MY_CLASSIC' => [$grade], 'MY_TLDM' => [], 'MY_TUDM' => []],
            'tenantSystemCode' => 'MY_CLASSIC', 'tenantExtras' => [], 'customSystems' => [],
        ])->body();
        unset($GLOBALS['__app_locale']);
        self::assertStringContainsString('Malaisie : armée de terre', $body);
        self::assertStringContainsString('Leftenan Kolonel', $body);
        self::assertStringContainsString('Lieutenant Colonel', $body);
        self::assertStringContainsString('grades malaisiens de l’armée de terre (Tentera Darat)', $body);
        self::assertStringNotContainsString('Malaisie : marine', $body, 'onglet masqué tant que ses grades ne sont pas installés');
    }
}
