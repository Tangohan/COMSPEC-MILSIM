<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Services\Community\CommunityOnboardingValidationService;
use App\Services\Organization\EchelonStandards;
use App\Services\Organization\GradeLevel;
use App\Services\Organization\OrgAdequacySettings;
use App\Services\OrganizationCatalog\OrganizationCatalogService;
use App\Services\OrganizationCatalog\RealUnitKitDefinitions;
use App\Support\OfficialGradeSystems;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Grades allemands (Bundeswehr), russes, chinois (Armée populaire de libération) et échelle OTAN générique : intitulés
 * dans la langue d’origine, translittération (russe, pinyin), équivalent anglais, code OTAN, référentiel d’adéquation
 * par échelon, étape de migration idempotente et modèles d’unités allemands.
 */
final class GradeSystemsGermanyRussiaChinaNatoTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private const SYSTEMS = [
        'DE_HEER' => ['DE', 'de', 29], 'DE_MARINE' => ['DE', 'de', 29], 'DE_LW' => ['DE', 'de', 29],
        'RU_SV' => ['RU', 'ru', 20], 'RU_VMF' => ['RU', 'ru', 20], 'RU_VKS' => ['RU', 'ru', 20],
        'CN_PLA' => ['CN', 'cn', 20], 'CN_PLAN' => ['CN', 'cn', 20], 'CN_PLAAF' => ['CN', 'cn', 20],
        'NATO_GENERIC' => ['NATO', 'nato', 20],
    ];

    public function testSystemsAreCompleteAndComparable(): void
    {
        $tabs = OfficialGradeSystems::tabs();
        self::assertSame(count($tabs), count(array_unique($tabs)), 'un onglet par système');
        foreach (self::SYSTEMS as $system => [$country, $profile, $count]) {
            self::assertTrue(OfficialGradeSystems::has($system));
            self::assertSame($country, OfficialGradeSystems::country($system));
            self::assertSame($profile, OfficialGradeSystems::adequacyProfile($system));
            self::assertContains($system, CommunityOnboardingValidationService::GRADE_SYSTEMS);
            self::assertSame($system, OfficialGradeSystems::codeForTab(OfficialGradeSystems::tab($system)));
            self::assertNotSame('', OfficialGradeSystems::family($system));
            self::assertLessThanOrEqual(100, mb_strlen(OfficialGradeSystems::label($system)), 'grade_systems.label');
            $grades = OfficialGradeSystems::GRADES[$system];
            self::assertCount($count, $grades, $system);
            $codes = array_column($grades, 0);
            self::assertSame(count($codes), count(array_unique($codes)), $system);
            $orders = array_column($grades, 6);
            self::assertSame(count($orders), count(array_unique($orders)), $system . ' : ordres');
            self::assertContains(OfficialGradeSystems::founderGrade($system), $codes, $system . ' : grade du fondateur');
            $levels = [];
            foreach ($grades as $g) {
                [$code, $short, $long, $english, $nato, $category] = $g;
                self::assertMatchesRegularExpression('/^[A-Z0-9]{1,10}$/', $code);
                self::assertNotSame('', $short, $code);
                self::assertNotSame('', $long, $code);
                self::assertLessThanOrEqual(150, mb_strlen($long));
                self::assertNotSame('', $english, $code);
                self::assertContains($category, ['OFFICIER', 'SOUS_OFFICIER', 'MDR'], $code);
                self::assertSame($english !== $long ? $english : '', OfficialGradeSystems::english($system, $code));
                if ($nato !== 'OF(D)') {
                    $level = GradeLevel::fromCode($nato);
                    self::assertNotNull($level, $system . ' ' . $code);
                    $levels[] = $level;
                }
                if (in_array($country, ['RU', 'CN'], true)) {
                    self::assertNotSame('', OfficialGradeSystems::romanized($system, $code), $system . ' ' . $code . ' : translittération');
                    self::assertMatchesRegularExpression('/^[\p{Latin} \-0-9]+$/u', OfficialGradeSystems::romanized($system, $code));
                }
            }
            self::assertSame(1, min($levels), $system . ' : depuis OR-1');
            self::assertGreaterThanOrEqual(18, max($levels), $system . ' : jusqu’à OF-9');
        }

        $heer = array_column(OfficialGradeSystems::GRADES['DE_HEER'], null, 0);
        self::assertSame(['Hptm', 'Hauptmann', 'Captain', 'OF-2'], array_slice($heer['HPTM'], 1, 4));
        self::assertSame(['OStFw', 'Oberstabsfeldwebel', 'Sergeant Major', 'OR-9'], array_slice($heer['OSTFW'], 1, 4));
        self::assertSame('Flieger', array_column(OfficialGradeSystems::GRADES['DE_LW'], null, 0)['FLG'][2]);
        self::assertSame('Kapitän zur See', array_column(OfficialGradeSystems::GRADES['DE_MARINE'], null, 0)['KPTZS'][2]);
        $ru = array_column(OfficialGradeSystems::GRADES['RU_SV'], null, 0);
        self::assertSame(['Подполковник', 'Lieutenant Colonel', 'OF-4'], array_slice($ru['PPLK'], 2, 3));
        self::assertSame('Podpolkovnik', OfficialGradeSystems::romanized('RU_SV', 'PPLK'));
        self::assertSame('Адмирал флота', array_column(OfficialGradeSystems::GRADES['RU_VMF'], null, 0)['ADMFL'][2]);
        self::assertSame('Shàngwèi', OfficialGradeSystems::romanized('CN_PLA', 'SHANGWEI'));
        self::assertSame('海军上将', array_column(OfficialGradeSystems::GRADES['CN_PLAN'], null, 0)['SHANGJIANG'][2]);
        $nato = array_column(OfficialGradeSystems::GRADES['NATO_GENERIC'], null, 0);
        self::assertSame(19, GradeLevel::fromCode($nato['OF10'][4]));
        self::assertSame(9, GradeLevel::fromCode($nato['WO'][4]));
        self::assertSame('', OfficialGradeSystems::romanized('DE_HEER', 'HPTM'));
        self::assertSame('ru', OfficialGradeSystems::lang('RU_VMF'));
        self::assertSame('zh', OfficialGradeSystems::lang('CN_PLAAF'));
    }

    public function testSeedStepAddsTheSystemsOnceWithoutTouchingOthers(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => '2026-11-02 12:00:00', 0);
        $pdo->exec('CREATE TABLE grade_categories (id INTEGER PRIMARY KEY, code TEXT)');
        $pdo->exec('CREATE TABLE grade_systems (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT, label TEXT, country_code TEXT, format_type TEXT, is_active INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE grades (id INTEGER PRIMARY KEY AUTOINCREMENT, grade_system_id INTEGER, grade_category_id INTEGER, code TEXT, label_short TEXT, label_long TEXT, label_otan TEXT, sort_order INTEGER, is_commissioned INTEGER, is_active INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec("INSERT INTO grade_categories (id, code) VALUES (1, 'OFFICIER'), (2, 'SOUS_OFFICIER'), (3, 'MDR')");
        $pdo->exec("INSERT INTO grade_systems (code, label, country_code) VALUES ('FR_CLASSIC', 'Grades français', 'FR')");
        $root = realpath(self::ROOT);
        $step = self::ROOT . '/database/steps/3660_grade_systems_de_ru_cn_nato.php';
        ob_start();
        require $step;
        self::assertStringContainsString('10 système(s)', (string) ob_get_clean());
        $total = 0;
        foreach (array_keys(self::SYSTEMS) as $system) {
            $total += count(OfficialGradeSystems::GRADES[$system]);
        }
        self::assertSame(227, $total);
        self::assertSame($total, (int) $pdo->query('SELECT COUNT(*) FROM grades')->fetchColumn());
        self::assertSame(array_merge(['FR_CLASSIC'], array_keys(self::SYSTEMS)), $pdo->query('SELECT code FROM grade_systems ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame('NATO', $pdo->query("SELECT country_code FROM grade_systems WHERE code = 'NATO_GENERIC'")->fetchColumn());
        $row = $pdo->query("SELECT g.label_short, g.label_long, g.label_otan, g.is_commissioned FROM grades g JOIN grade_systems s ON s.id = g.grade_system_id WHERE s.code = 'RU_SV' AND g.code = 'KPT'")->fetch(PDO::FETCH_NUM);
        self::assertSame(['к-н', 'Капитан', 'OF-2', 1], [$row[0], $row[1], $row[2], (int) $row[3]]);

        ob_start();
        require $step;
        self::assertStringContainsString('0 système(s) et 0 grade(s)', (string) ob_get_clean(), 'relance sans effet');
        self::assertSame($total, (int) $pdo->query('SELECT COUNT(*) FROM grades')->fetchColumn());

        $registry = require self::ROOT . '/database/steps/registry.php';
        $ids = array_column($registry, 'id');
        self::assertSame(count($ids), count(array_unique($ids)));
        self::assertContains('3660_grade_systems_de_ru_cn_nato.php', array_column($registry, 'file'));
    }

    public function testAdequacyProfilesFollowTheGradeSystem(): void
    {
        foreach (['de', 'ru', 'cn', 'nato'] as $profile) {
            self::assertContains($profile, EchelonStandards::profiles());
            $defs = EchelonStandards::all($profile);
            self::assertSame(EchelonStandards::keys(), array_keys($defs), $profile);
            foreach ($defs as $def) {
                self::assertNotSame([], $def['sources'], $profile . ' ' . $def['key']);
                self::assertLessThanOrEqual($def['leader_grade'][1], $def['leader_grade'][0]);
                self::assertGreaterThanOrEqual(GradeLevel::MIN, $def['leader_grade'][0]);
                self::assertLessThanOrEqual(GradeLevel::MAX, $def['leader_grade'][1]);
            }
            self::assertSame($profile, OrgAdequacySettings::sanitize(['profile' => $profile])['profile']);
        }
        self::assertSame([5, 6], EchelonStandards::all('de')['groupe']['leader_grade'], 'Gruppenführer : Unteroffizier à Feldwebel');
        self::assertSame([4, 5], EchelonStandards::all('ru')['groupe']['leader_grade'], 'командир отделения : младший сержант à сержант');
        self::assertSame([11, 11], EchelonStandards::all('cn')['compagnie']['leader_grade'], '连长 : 上尉');
        self::assertSame(EchelonStandards::PROFILE_DE, OrgAdequacySettings::resolveProfile([], 'fr', 'DE_MARINE'));
        self::assertSame(EchelonStandards::PROFILE_RU, OrgAdequacySettings::resolveProfile([], 'us', 'RU_VKS'));
        self::assertSame(EchelonStandards::PROFILE_CN, OrgAdequacySettings::resolveProfile([], 'fr', 'CN_PLA'));
        self::assertSame(EchelonStandards::PROFILE_NATO, OrgAdequacySettings::resolveProfile([], 'fr', 'NATO_GENERIC'));
        self::assertSame(EchelonStandards::PROFILE_MY, OrgAdequacySettings::resolveProfile([], 'fr', 'MY_TLDM'));
        self::assertSame(EchelonStandards::PROFILE_US, OrgAdequacySettings::resolveProfile([], 'us', 'US_CLASSIC'));
        self::assertSame(EchelonStandards::PROFILE_FR, OrgAdequacySettings::resolveProfile([], 'fr', 'FR_CLASSIC'));
        self::assertSame(EchelonStandards::PROFILE_US, OrgAdequacySettings::resolveProfile(['profile' => 'us'], 'fr', 'DE_HEER'), 'réglage explicite');
    }

    public function testGermanUnitKitsApplyTheBundeswehrGrades(): void
    {
        $kits = array_column(RealUnitKitDefinitions::kits(), null, 'code');
        $expect = ['de_dsk' => 'DE_HEER', 'de_ksk' => 'DE_HEER', 'de_llbrig1' => 'DE_HEER', 'de_ksm' => 'DE_MARINE', 'de_seebtl' => 'DE_MARINE', 'de_hsg64' => 'DE_LW'];
        foreach ($expect as $suffix => $grade) {
            $kit = $kits[RealUnitKitDefinitions::PREFIX . $suffix] ?? null;
            self::assertIsArray($kit, $suffix);
            self::assertSame($grade, $kit['grade_system_code'], $suffix);
            self::assertStringNotContainsString('Sans système de grades', (string) $kit['summary'], $suffix);
        }
        self::assertSame('Système de grades allemand (Luftwaffe)', OrganizationCatalogService::gradeSystemLabel('DE_LW'));
        self::assertSame('Système de grades OTAN générique', OrganizationCatalogService::gradeSystemLabel('NATO_GENERIC'));
    }

    public function testGradeReferentialShowsNativeTitleTransliterationAndEnglish(): void
    {
        $_SESSION = ['tenant_id' => 42, '_csrf_token' => str_repeat('a', 64)];
        $GLOBALS['__app_locale'] = 'fr';
        $grade = ['id' => 9, 'code' => 'PPLK', 'label_short' => 'п/п-к', 'label_long' => 'Подполковник', 'catalog_label_long' => 'Подполковник', 'catalog_label_short' => 'п/п-к', 'category_code' => 'OFFICIER', 'label_otan' => 'OF-4', 'sort_order' => 15, 'is_enabled' => 1, 'country_code' => 'RU', 'system_code' => 'RU_SV'];
        $systems = array_fill_keys(OfficialGradeSystems::CODES, []);
        $systems['RU_SV'] = [$grade];
        $body = Response::view('admin.organization.referentiels.grades.index', [
            'tab' => 'ru', 'gradesBySystem' => $systems,
            'tenantSystemCode' => 'RU_SV', 'tenantExtras' => [], 'customSystems' => [],
        ])->body();
        unset($GLOBALS['__app_locale']);
        self::assertStringContainsString('Russie : armée de terre', $body);
        self::assertStringContainsString('<strong lang="ru">Подполковник</strong>', $body);
        self::assertStringContainsString('lang="ru-Latn" translate="no">Podpolkovnik<', $body);
        self::assertStringContainsString('Lieutenant Colonel', $body);
        self::assertStringContainsString('grades russes de l’armée de terre (Сухопутные войска)', $body);
        self::assertStringNotContainsString('Chine : marine', $body, 'plus d’onglet par pays : les systèmes sont dans la fenêtre');
    }

    public function testCatalogModalListsEverySystemOfTheRegistry(): void
    {
        $_SESSION = ['tenant_id' => 42, '_csrf_token' => str_repeat('a', 64)];
        $GLOBALS['__app_locale'] = 'fr';
        $installed = ['US_CLASSIC' => [['id' => 1, 'code' => 'CPT', 'label_short' => 'CPT', 'label_long' => 'Captain', 'label_otan' => 'O-3', 'category_code' => 'OFFICIER', 'sort_order' => 3, 'is_commissioned' => 1]]];
        foreach (['DE_HEER', 'RU_SV'] as $code) {
            $i = 100;
            foreach (OfficialGradeSystems::GRADES[$code] as $g) {
                $installed[$code][] = ['id' => ++$i, 'code' => $g[0], 'label_short' => $g[1], 'label_long' => $g[2], 'label_otan' => $g[4], 'category_code' => $g[5], 'sort_order' => $g[6], 'is_commissioned' => $g[7]];
            }
        }
        $countries = \App\Support\GradeSystemCatalog::countries($installed, 'US_CLASSIC');
        self::assertSame('US', $countries[0]['country'], 'pays du système actuel en tête');
        $codes = [];
        foreach ($countries as $c) {
            foreach ($c['systems'] as $s) {
                $codes[] = $s['code'];
            }
        }
        self::assertEqualsCanonicalizing(OfficialGradeSystems::CODES, $codes);
        $byCode = array_column(array_merge(...array_column($countries, 'systems')), null, 'code');
        self::assertTrue($byCode['DE_HEER']['installed']);
        self::assertFalse($byCode['CN_PLAN']['installed'], 'grades lus dans le registre tant que l’étape n’a pas tourné');
        self::assertCount(20, $byCode['CN_PLAN']['grades']);
        self::assertSame('OF-9', $byCode['CN_PLAN']['grades'][0]['nato'], 'du plus élevé au plus bas');
        self::assertSame('Hǎijūn shàngjiàng', $byCode['CN_PLAN']['grades'][0]['roman']);

        $systems = array_fill_keys(OfficialGradeSystems::CODES, []);
        $body = Response::view('admin.organization.referentiels.grades.index', [
            'tab' => 'us', 'gradesBySystem' => $systems, 'gradeCatalogCountries' => $countries,
            'tenantSystemCode' => 'US_CLASSIC', 'tenantExtras' => [], 'customSystems' => [],
        ])->body();
        unset($GLOBALS['__app_locale']);
        foreach (OfficialGradeSystems::CODES as $code) {
            self::assertStringContainsString('data-grade-catalog-panel="' . $code . '"', $body, $code);
        }
        foreach (['Deutschland', 'Россия', '中国', 'Malaysia', 'United Kingdom', 'NATO · OTAN'] as $native) {
            self::assertStringContainsString($native, $body);
        }
        self::assertStringContainsString('data-grade-catalog-open', $body);
        self::assertStringContainsString('Système de grades de la communauté', $body);
        self::assertStringContainsString('data-grade-catalog-confirm', $body);
        self::assertStringContainsString('value="DE_HEER"', $body, 'système installé : formulaire « Utiliser pour la communauté »');
        self::assertStringNotContainsString('value="CN_PLAN"', $body, 'système non installé : pas de formulaire');
        self::assertStringContainsString('data-grade-catalog-compare-col hidden translate="no">CPT<', $body, 'Hauptmann (OF-2) = Captain (O-3)');
        preg_match('~<nav class="grd-ref__tabs"[^>]*>(.*?)</nav>~s', $body, $m);
        self::assertSame(3, substr_count($m[1] ?? '', '<a '), 'grades officiels, sur mesure, grades d’avancement');
        self::assertStringContainsString('grade-system-catalog.js', $body);
    }

    public function testWizardOffersTheCatalogInsteadOfOneCardPerSystem(): void
    {
        $view = (string) file_get_contents(self::ROOT . '/views/community/create.php');
        self::assertStringContainsString('data-grade-catalog-card', $view);
        self::assertStringContainsString('data-grade-catalog-options', $view);
        self::assertStringContainsString("\$gscMode = 'wizard'", $view);
        $ctrl = (string) file_get_contents(self::ROOT . '/app/Controllers/Web/CommunityController.php');
        self::assertStringContainsString('GradeSystemCatalog::countries($catalogRows)', $ctrl);
        $js = (string) file_get_contents(self::ROOT . '/public/assets/js/grade-system-catalog.js');
        self::assertStringContainsString('wizard_grade_system_code', $js);
        self::assertStringContainsString('requestSubmit', $js);
    }
}
