<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GradeDisplayService;
use App\Services\Rank\RankReferenceValidator;
use PHPUnit\Framework\TestCase;

final class RankReferenceValidatorTest extends TestCase
{
    public function testFrArmyOfficerNatoCodesAreExplicitAndCorrect(): void
    {
        $v = new RankReferenceValidator();
        $matrix = RankReferenceValidator::expectedFrArmy();

        self::assertSame('OF-5', $matrix['Colonel']['nato_code']);
        self::assertSame('OF-4', $matrix['Lieutenant-colonel']['nato_code']);
        self::assertSame('OF-2', $matrix['Capitaine']['nato_code']);
        self::assertSame('OF-3', $matrix['Commandant']['nato_code']);
        self::assertSame('OF-6', $matrix['Général de brigade']['nato_code']);

        self::assertSame('VERIFIED', $v->evaluateFrArmyRow('Colonel', 'OF-5')['status']);
        self::assertSame('INVALID', $v->evaluateFrArmyRow('Colonel', 'OF-4')['status']);
        self::assertSame('OF-5', $v->evaluateFrArmyRow('Colonel', 'OF-4')['expected_nato']);
        self::assertSame('VERIFIED', $v->evaluateFrArmyRow('COL', 'OF-5')['status']);
        self::assertSame('VERIFIED', $v->evaluateFrArmyRow('LCL', 'OF-4')['status']);
    }

    public function testNatoCodeIsIndependentFromHierarchyOrder(): void
    {
        $matrix = RankReferenceValidator::expectedFrArmy();
        $v = new RankReferenceValidator();

        $colonelOrder = $matrix['Colonel']['hierarchy_order'];
        $colonelNato = $matrix['Colonel']['nato_code'];
        self::assertSame('OF-5', $colonelNato);
        self::assertNotSame(5, $colonelOrder);
        self::assertTrue($v->assertNatoNotDerivedFromOrder($colonelNato, $colonelOrder));

        /* Changer l’ordre ne doit pas changer le code OTAN attendu. */
        $alteredOrder = $colonelOrder + 1000;
        self::assertSame('OF-5', RankReferenceValidator::expectedFrArmy()['Colonel']['nato_code']);
        self::assertTrue($v->assertNatoNotDerivedFromOrder('OF-5', $alteredOrder));

        /* Pattern interdit : OF-4 avec hierarchy_order = 4. */
        self::assertFalse($v->assertNatoNotDerivedFromOrder('OF-4', 4));
    }

    public function testNatoFormatValidation(): void
    {
        $v = new RankReferenceValidator();
        self::assertTrue($v->isValidNatoCode('OF-5'));
        self::assertTrue($v->isValidNatoCode('OR-9'));
        self::assertTrue($v->isValidNatoCode(null));
        self::assertFalse($v->isValidNatoCode('OF-10'));
        self::assertFalse($v->isValidNatoCode('OF-0'));
        self::assertTrue($v->isValidNatoCode('O-6', true));
        self::assertFalse($v->isValidNatoCode('O-6', false));
    }

    public function testBandeauMismatchColonelTitleWithLclGrade(): void
    {
        $svc = new GradeDisplayService();
        $grade = [
            'code' => 'LCL',
            'label_short' => 'LCL',
            'label_long' => 'Lieutenant-colonel',
            'label_otan' => 'OF-4',
        ];
        $profile = ['rank_display' => 'Colonel', 'rank_display_override' => ''];
        $hit = $svc->detectTitleGradeMismatch($grade, $profile);
        self::assertTrue($hit['mismatch']);
        self::assertSame('TITLE_COLONEL_GRADE_LCL_OF4', $hit['code']);

        $ok = $svc->detectTitleGradeMismatch([
            'code' => 'COL',
            'label_long' => 'Colonel',
            'label_otan' => 'OF-5',
        ], null);
        self::assertFalse($ok['mismatch']);
    }

    public function testAssetsWired(): void
    {
        $migration = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/rank_catalog_migration.php');
        self::assertStringContainsString('rank_catalog', $migration);
        self::assertStringContainsString('tenant_ranks', $migration);
        self::assertStringContainsString('personnel_rank_history', $migration);
        self::assertStringContainsString('rank_migration_audit', $migration);
        self::assertStringContainsString("'OF-5'", $migration);
        self::assertStringContainsString('GENDARMERIE', $migration);

        $run = (string) file_get_contents(dirname(__DIR__, 2) . '/run-migrations.php');
        self::assertStringContainsString('rank_catalog_migration.php', $run);
        self::assertStringContainsString('run_rank_catalog_migration', $run);

        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        self::assertStringContainsString('referentiels/grades/catalogue', $routes);

        $audit = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/technique/rank-catalog-audit.md');
        self::assertStringContainsString('Colonel = OF-5', $audit);
        self::assertStringContainsString('rank_level = 4 ⇒ OF-4', $audit);
    }
}
