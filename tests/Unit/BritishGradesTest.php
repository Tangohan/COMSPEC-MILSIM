<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Organization\EchelonStandards;
use App\Services\Organization\GradeLevel;
use App\Services\Organization\OrgAdequacySettings;
use App\Support\BritishGradeCatalog;
use App\Support\SharedGradeSystems;
use PHPUnit\Framework\TestCase;

/**
 * Grades britanniques (British Army et Royal Marines, Royal Navy, Royal Air Force) : catalogue posé par l’étape
 * 3610_grades_royaume_uni, proposé à la création d’une communauté et dans le référentiel des grades, et référentiel
 * d’adéquation des échelons britannique.
 */
final class BritishGradesTest extends TestCase
{
    public function testEveryBritishGradeIsValidUniqueAndComparable(): void
    {
        $seen = [];
        $bySystem = [];
        foreach (BritishGradeCatalog::rows() as [$system, $category, $code, $short, $long, $otan, $order, $commissioned]) {
            self::assertArrayHasKey($system, BritishGradeCatalog::SYSTEMS);
            self::assertContains($category, ['OFFICIER', 'SOUS_OFFICIER', 'MDR', 'CIVIL', 'HORS_GRADE'], $code);
            self::assertMatchesRegularExpression('/^[A-Z0-9]{1,12}$/', $code);
            self::assertArrayNotHasKey($system . ':' . $code, $seen, 'code en double ' . $system . ':' . $code);
            $seen[$system . ':' . $code] = true;
            self::assertNotSame('', trim($short));
            self::assertNotSame('', trim($long));
            self::assertSame($category === 'OFFICIER' ? 1 : 0, $commissioned, $code);
            if (in_array($category, ['CIVIL', 'HORS_GRADE'], true)) {
                self::assertNull($otan);
            } elseif ($otan !== 'OF(D)') {
                $level = GradeLevel::fromCode($otan);
                self::assertNotNull($level, $system . ':' . $code . ' ' . $otan);
                self::assertSame($category === 'OFFICIER', $level >= 10, $code);
            }
            $bySystem[$system][$code] = $long;
        }
        // Grades demandés par la communauté (modèle britannique).
        foreach (['Private', 'Trooper', 'Gunner', 'Sapper', 'Signaller', 'Guardsman', 'Rifleman', 'Craftsman', 'Lance Corporal', 'Lance Bombardier', 'Corporal', 'Bombardier', 'Sergeant', 'Staff Sergeant', 'Colour Sergeant', 'Warrant Officer Class 2', 'Warrant Officer Class 1', 'Officer Cadet', 'Second Lieutenant', 'Lieutenant', 'Captain', 'Major', 'Lieutenant Colonel', 'Colonel', 'Brigadier', 'Major General', 'Lieutenant General', 'General', 'Field Marshal', 'Marine'] as $rank) {
            self::assertContains($rank, $bySystem['GB_ARMY'], $rank);
        }
        self::assertContains('Lieutenant Commander', $bySystem['GB_RN']);
        self::assertContains('Flight Lieutenant', $bySystem['GB_RAF']);
        // Le grade proposé au fondateur existe dans chaque système britannique.
        foreach (array_keys(BritishGradeCatalog::SYSTEMS) as $system) {
            self::assertTrue(SharedGradeSystems::isShared($system));
            self::assertSame('GB', SharedGradeSystems::country($system));
            self::assertArrayHasKey(SharedGradeSystems::founderCode($system), $bySystem[$system], $system);
        }
    }

    public function testSharedSystemsHaveDistinctTabsAndFallBackToFrench(): void
    {
        $tabs = SharedGradeSystems::tabs();
        self::assertSame(count($tabs), count(array_unique($tabs)));
        self::assertSame(['fr', 'us', 'gb', 'gb-rn', 'gb-raf'], $tabs);
        foreach (SharedGradeSystems::CODES as $code) {
            self::assertSame($code, SharedGradeSystems::codeForTab(SharedGradeSystems::tab($code)));
        }
        self::assertSame('fr', SharedGradeSystems::tab('INCONNU'));
        self::assertNull(SharedGradeSystems::codeForTab('custom'));
        self::assertSame(SharedGradeSystems::CODES, \App\Services\Community\CommunityOnboardingValidationService::GRADE_SYSTEMS);
    }

    public function testBritishEchelonsExpectBritishRanks(): void
    {
        self::assertContains(EchelonStandards::PROFILE_GB, EchelonStandards::profiles());
        $gb = EchelonStandards::all(EchelonStandards::PROFILE_GB);
        self::assertSame(EchelonStandards::keys(), array_keys($gb));
        self::assertSame([4, 4], $gb['groupe']['leader_grade'], 'section : Corporal');
        self::assertSame([10, 10], $gb['section']['leader_grade'], 'platoon : Second Lieutenant ou Lieutenant');
        self::assertSame([6, 6], $gb['section']['deputy_grade'], 'platoon : Platoon Sergeant');
        self::assertSame([12, 12], $gb['compagnie']['leader_grade'], 'company : Major');
        self::assertSame([13, 13], $gb['bataillon']['leader_grade'], 'battalion : Lieutenant Colonel');
        self::assertSame(EchelonStandards::PROFILE_GB, OrgAdequacySettings::resolveProfile([], 'fr', 'GB_ARMY'));
        self::assertSame(EchelonStandards::PROFILE_GB, OrgAdequacySettings::resolveProfile([], 'us', 'GB_RAF'));
        self::assertSame(EchelonStandards::PROFILE_US, OrgAdequacySettings::resolveProfile([], 'us', 'US_CLASSIC'));
        self::assertSame('gb', OrgAdequacySettings::sanitize(['profile' => 'gb'])['profile']);
    }

    public function testMigrationStepAddsTheBritishSystemsOnceAndKeepsExistingGrades(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE grade_categories (id INTEGER PRIMARY KEY, code TEXT)');
        $pdo->exec('CREATE TABLE grade_systems (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT, label TEXT, country_code TEXT, format_type TEXT, is_active INTEGER, owner_tenant_id INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE grades (id INTEGER PRIMARY KEY AUTOINCREMENT, grade_system_id INTEGER, grade_category_id INTEGER, code TEXT, label_short TEXT, label_long TEXT, label_otan TEXT, sort_order INTEGER, is_commissioned INTEGER, is_active INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec("INSERT INTO grade_categories (id, code) VALUES (1, 'OFFICIER'), (2, 'SOUS_OFFICIER'), (3, 'MDR'), (4, 'CIVIL'), (5, 'HORS_GRADE')");
        // Système de même code créé par une communauté : jamais réutilisé.
        $pdo->exec("INSERT INTO grade_systems (code, label, country_code, owner_tenant_id) VALUES ('GB_ARMY', 'Sur mesure', 'ZZ', 9)");
        $run = static function () use ($pdo): string {
            $root = dirname(__DIR__, 2);
            ob_start();
            require $root . '/database/steps/3610_grades_royaume_uni.php';

            return (string) ob_get_clean();
        };
        $run();
        $count = (int) $pdo->query("SELECT COUNT(*) FROM grades g JOIN grade_systems s ON s.id = g.grade_system_id WHERE s.country_code = 'GB'")->fetchColumn();
        self::assertSame(count(BritishGradeCatalog::rows()), $count);
        self::assertSame(3, (int) $pdo->query("SELECT COUNT(*) FROM grade_systems WHERE country_code = 'GB' AND owner_tenant_id IS NULL")->fetchColumn());
        self::assertSame('OF-3', $pdo->query("SELECT g.label_otan FROM grades g JOIN grade_systems s ON s.id = g.grade_system_id WHERE s.code = 'GB_ARMY' AND s.owner_tenant_id IS NULL AND g.code = 'MAJ'")->fetchColumn());
        self::assertStringContainsString('déjà présents', $run());
        self::assertSame($count, (int) $pdo->query("SELECT COUNT(*) FROM grades g JOIN grade_systems s ON s.id = g.grade_system_id WHERE s.country_code = 'GB'")->fetchColumn());
    }

    public function testMigrationStepIsRegisteredOnce(): void
    {
        $registry = require __DIR__ . '/../../database/steps/registry.php';
        $ids = array_column($registry, 'id');
        self::assertSame(1, array_count_values($ids)['grades_royaume_uni'] ?? 0);
        $files = array_column($registry, 'file');
        self::assertContains('3610_grades_royaume_uni.php', $files);
        self::assertFileExists(__DIR__ . '/../../database/steps/3610_grades_royaume_uni.php');
    }
}
