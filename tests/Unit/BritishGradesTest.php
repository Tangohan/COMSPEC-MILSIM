<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Organization\EchelonStandards;
use App\Services\Organization\GradeLevel;
use App\Services\Organization\OrgAdequacySettings;
use App\Support\OfficialGradeSystems;
use PHPUnit\Framework\TestCase;

/**
 * Grades britanniques (British Army et Royal Marines, Royal Navy, Royal Air Force) du registre OfficialGradeSystems,
 * posés par l’étape 3620_grades_royaume_uni, proposés à la création d’une communauté et dans le référentiel des grades,
 * et référentiel d’adéquation des échelons britannique.
 */
final class BritishGradesTest extends TestCase
{
    private const GB = ['GB_ARMY', 'GB_RN', 'GB_RAF'];

    public function testEveryBritishGradeIsValidUniqueAndComparable(): void
    {
        $names = [];
        foreach (self::GB as $system) {
            self::assertTrue(OfficialGradeSystems::has($system));
            self::assertContains($system, OfficialGradeSystems::CODES);
            self::assertSame('GB', OfficialGradeSystems::country($system));
            $seen = [];
            foreach (OfficialGradeSystems::GRADES[$system] as [$code, $short, $long, $english, $otan, $category, $order, $commissioned]) {
                self::assertContains($category, ['OFFICIER', 'SOUS_OFFICIER', 'MDR'], $code);
                self::assertMatchesRegularExpression('/^[A-Z0-9]{1,12}$/', $code);
                self::assertArrayNotHasKey($code, $seen, 'code en double ' . $system . ':' . $code);
                $seen[$code] = true;
                self::assertNotSame('', trim($short));
                self::assertSame($long, $english, 'intitulé officiel déjà en anglais');
                self::assertSame('', OfficialGradeSystems::english($system, $code), 'pas d’équivalent répété');
                self::assertSame($category === 'OFFICIER' ? 1 : 0, $commissioned, $code);
                if ($otan !== 'OF(D)') {
                    $level = GradeLevel::fromCode($otan);
                    self::assertNotNull($level, $system . ':' . $code . ' ' . $otan);
                    self::assertSame($category === 'OFFICIER', $level >= 10, $code);
                }
                $names[$system][$code] = $long;
            }
            self::assertArrayHasKey(OfficialGradeSystems::founderGrade($system), $names[$system], 'grade du fondateur ' . $system);
        }
        foreach (['Private', 'Trooper', 'Gunner', 'Sapper', 'Signaller', 'Guardsman', 'Rifleman', 'Craftsman', 'Lance Corporal', 'Lance Bombardier', 'Corporal', 'Bombardier', 'Sergeant', 'Staff Sergeant', 'Colour Sergeant', 'Warrant Officer Class 2', 'Warrant Officer Class 1', 'Officer Cadet', 'Second Lieutenant', 'Lieutenant', 'Captain', 'Major', 'Lieutenant Colonel', 'Colonel', 'Brigadier', 'Major General', 'Lieutenant General', 'General', 'Field Marshal', 'Marine'] as $rank) {
            self::assertContains($rank, $names['GB_ARMY'], $rank);
        }
        self::assertContains('Lieutenant Commander', $names['GB_RN']);
        self::assertContains('Flight Lieutenant', $names['GB_RAF']);
        self::assertSame(['GB_ARMY', 'GB_RN', 'GB_RAF'], array_values(array_filter(OfficialGradeSystems::CODES, static fn (string $c): bool => OfficialGradeSystems::country($c) === 'GB')));
        self::assertSame('GB_ARMY', OfficialGradeSystems::codeForTab(OfficialGradeSystems::tab('GB_ARMY')));
        self::assertSame(OfficialGradeSystems::tabs(), array_values(array_unique(OfficialGradeSystems::tabs())));
    }

    public function testSeedStepAddsTheBritishSystemsOnce(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => '2026-11-02 12:00:00', 0);
        $pdo->exec('CREATE TABLE grade_categories (id INTEGER PRIMARY KEY, code TEXT)');
        $pdo->exec('CREATE TABLE grade_systems (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT, label TEXT, country_code TEXT, format_type TEXT, is_active INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE grades (id INTEGER PRIMARY KEY AUTOINCREMENT, grade_system_id INTEGER, grade_category_id INTEGER, code TEXT, label_short TEXT, label_long TEXT, label_otan TEXT, sort_order INTEGER, is_commissioned INTEGER, is_active INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec("INSERT INTO grade_categories (id, code) VALUES (1, 'OFFICIER'), (2, 'SOUS_OFFICIER'), (3, 'MDR'), (4, 'CIVIL'), (5, 'HORS_GRADE')");
        $run = static function () use ($pdo): string {
            $root = dirname(__DIR__, 2);
            ob_start();
            require $root . '/database/steps/3620_grades_royaume_uni.php';

            return (string) ob_get_clean();
        };
        self::assertStringContainsString('3 système(s)', $run());
        $expected = array_sum(array_map(static fn (string $s): int => count(OfficialGradeSystems::GRADES[$s]), self::GB));
        $count = static fn (): int => (int) $pdo->query("SELECT COUNT(*) FROM grades g JOIN grade_systems s ON s.id = g.grade_system_id WHERE s.country_code = 'GB'")->fetchColumn();
        self::assertSame($expected, $count());
        self::assertSame('OF-3', $pdo->query("SELECT g.label_otan FROM grades g JOIN grade_systems s ON s.id = g.grade_system_id WHERE s.code = 'GB_ARMY' AND g.code = 'MAJ'")->fetchColumn());
        self::assertStringContainsString('0 système(s) et 0 grade(s)', $run());
        self::assertSame($expected, $count());

        $registry = require dirname(__DIR__, 2) . '/database/steps/registry.php';
        self::assertSame(1, array_count_values(array_column($registry, 'id'))['grades_royaume_uni'] ?? 0);
        self::assertContains('3620_grades_royaume_uni.php', array_column($registry, 'file'));
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
        foreach (self::GB as $system) {
            self::assertSame(EchelonStandards::PROFILE_GB, OrgAdequacySettings::resolveProfile([], 'fr', $system));
        }
        self::assertSame(EchelonStandards::PROFILE_MY, OrgAdequacySettings::resolveProfile([], 'fr', 'MY_CLASSIC'));
        self::assertSame('gb', OrgAdequacySettings::sanitize(['profile' => 'gb'])['profile']);
    }
}
