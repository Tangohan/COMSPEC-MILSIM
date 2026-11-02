<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AdminAtakOperatorsController;
use App\Services\Atak\AtakOperatorPersonnelService;
use App\Support\OrgVisibilityCapabilities;
use PHPUnit\Framework\TestCase;

/**
 * Sessions & connexions ATAK : la fonction tenue sur le site remplace le rôle qui remontait du jeu,
 * affiché seulement à part (« déclaré en jeu ») et signalé quand il diffère.
 */
final class AtakOperatorsCurrentFunctionTest extends TestCase
{
    private const UNITS = [
        ['id' => 1, 'name' => '1er Bataillon', 'parent_id' => null],
        ['id' => 2, 'name' => '2e Compagnie', 'parent_id' => 1],
    ];

    private static function src(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    private static function staff(): OrgVisibilityCapabilities
    {
        $caps = new OrgVisibilityCapabilities();
        $caps->bypassAll = true;

        return $caps;
    }

    /** @return array<string, mixed> */
    private static function visibleMember(): array
    {
        return AtakOperatorPersonnelService::describe(
            ['id' => 7, 'display_name' => 'Noopy', 'callsign' => 'N-10', 'grade_short' => 'Sgt', 'grade_long' => 'Sergent', 'unit_id' => 2, 'unit_name' => '2e Compagnie'],
            [],
            ['first_name' => 'Jean', 'last_name' => 'Dupont', 'trigram' => 'JDU'],
            [
                ['personnel_job_role_id' => 31, 'role_name' => 'Chef de groupe', 'role_detail' => 'Alpha', 'is_primary' => 1],
                ['personnel_job_role_id' => 32, 'role_name' => 'Radio', 'role_detail' => '', 'is_primary' => 0],
            ],
            self::UNITS,
            self::staff()
        );
    }

    public function testLinkedAccountShowsCurrentFunctionGradeNameAndAssignment(): void
    {
        $p = self::visibleMember();

        self::assertSame('visible', $p['state']);
        self::assertSame('Jean D.', $p['name']);
        self::assertSame('JDU', $p['trigram']);
        self::assertSame('Sgt', $p['grade_short']);
        self::assertSame('Chef de groupe — Alpha', $p['function_label']);
        self::assertSame([31, 32], array_column($p['functions'], 'id'));
        self::assertSame('1er Bataillon / 2e Compagnie', $p['unit_path']);
        self::assertSame([1, 2], array_column($p['unit_chain'], 'id'));
    }

    public function testAnonymizedMemberKeepsNoIdentityNorFunction(): void
    {
        $p = AtakOperatorPersonnelService::describe(
            ['id' => 7, 'display_name' => 'Noopy', 'grade_short' => 'Sgt', 'unit_id' => 2],
            ['visibility_level' => 'anonymized', 'anonymized_label' => 'Opérateur A'],
            ['first_name' => 'Jean', 'last_name' => 'Dupont', 'trigram' => 'JDU'],
            [['personnel_job_role_id' => 31, 'role_name' => 'Chef de groupe']],
            self::UNITS,
            new OrgVisibilityCapabilities()
        );
        self::assertSame('anonymized', $p['state']);
        self::assertSame('Opérateur A', $p['masked_label']);
        self::assertSame('', $p['name'].$p['trigram'].$p['grade_short'].$p['function_label'].$p['unit_label']);

        $row = AdminAtakOperatorsController::decorateRow(
            ['call_sign' => 'N-10', 'linked_user_id' => 7, 'linked_display_name' => 'Noopy', 'linked_url' => '/personnel/7', 'steam_id' => '7656', 'game_role_raw' => 'Chef'],
            $p
        );
        self::assertNull($row['linked_user_id']);
        self::assertNull($row['linked_url']);
        self::assertSame('', $row['steam_id']);
        self::assertSame('Opérateur A', $row['linked_display_name']);
    }

    public function testDeclaredGameRoleIsSeparateAndFlaggedWhenItDiffers(): void
    {
        self::assertSame(['label' => '', 'differs' => false], AtakOperatorPersonnelService::declaredRole('Operator', 'Chef de groupe'));
        self::assertSame(['label' => '', 'differs' => false], AtakOperatorPersonnelService::declaredRole('chef de groupe', 'Chef de groupe — Alpha'));
        self::assertSame(['label' => 'Tireur AT', 'differs' => true], AtakOperatorPersonnelService::declaredRole('Tireur AT', 'Chef de groupe'));
        self::assertSame(['label' => 'Tireur AT', 'differs' => false], AtakOperatorPersonnelService::declaredRole('Tireur AT', ''));
    }

    public function testOperatorsPageNoLongerShowsTheGameRoleAsRole(): void
    {
        $row = AdminAtakOperatorsController::decorateRow([
            'id' => 1, 'call_sign' => 'N-10', 'military_id' => 'FR-01', 'status' => 'linked', 'grid_ref' => '012345',
            'heading_label' => '90°', 'unit_group_label' => '', 'fire_team_label' => '', 'fire_team_id' => 0,
            'linked_user_id' => 7, 'linked_display_name' => 'Noopy', 'linked_url' => '/personnel/7', 'steam_id' => '',
            'updated_at' => '', 'updated_at_label' => 'il y a 5 s', 'map_url' => '/atak', 'mod_version' => '2.4.1',
            'game_role_raw' => 'Tireur AT',
        ], self::visibleMember());

        $html = (static function (array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            require dirname(__DIR__, 2) . '/views/admin/atak_operators/index.php';

            return (string) ob_get_clean();
        })([
            'atakOperators' => [$row],
            'atakOperatorsStats' => ['total' => 1, 'linked' => 1, 'delayed' => 0, 'offline' => 0, 'shown' => 1],
            'atakOperatorsMaps' => [], 'atakOperatorsMapId' => 1, 'atakOperatorsFilter' => 'liaison', 'atakOperatorsQuery' => '',
            'atakOperatorsUnitOptions' => [['id' => 2, 'label' => '1er Bataillon / 2e Compagnie']],
            'atakOperatorsFunctionOptions' => [['id' => 31, 'label' => 'Chef de groupe']],
        ]);

        self::assertDoesNotMatchRegularExpression('/<th[^>]*>\s*Rôle\s*<\/th>/u', $html);
        self::assertStringContainsString('Chef de groupe — Alpha', $html);
        self::assertStringContainsString('1er Bataillon / 2e Compagnie', $html);
        self::assertStringContainsString('Jean D.', $html);
        self::assertStringContainsString('JDU', $html);
        self::assertStringContainsString('>Sgt<', $html);
        self::assertStringContainsString('Déclaré en jeu : Tireur AT', $html);
        self::assertStringContainsString('Écart', $html);
        self::assertStringContainsString('2.4.1', $html);
        self::assertStringContainsString('name="unite"', $html);
        self::assertStringContainsString('name="fonction"', $html);
        self::assertStringNotContainsString('Noopy', $html);
    }

    public function testWiringControllerReadsFiltersFromQueryAndNoLongerExposesRoleLabel(): void
    {
        $c = self::src('app/Controllers/Admin/AdminAtakOperatorsController.php');
        self::assertStringNotContainsString("'role_label'", $c);
        self::assertStringContainsString("\$request->query('unite')", $c);
        self::assertStringContainsString("\$request->query('fonction')", $c);
        self::assertStringContainsString('OrgVisibilityCapabilities::fromGate()', $c);
    }

    public function testWiringContainerInjectsThePersonnelService(): void
    {
        self::assertMatchesRegularExpression(
            '/AdminAtakOperatorsController\(.*?new \\\\App\\\\Services\\\\Atak\\\\AtakOperatorPersonnelService\(/s',
            self::src('app/Core/Container.php')
        );
    }

    public function testWiringCsvExportUsesFunctionAndDeclaredRoleColumns(): void
    {
        $c = self::src('app/Controllers/Admin/AdminAtakOperatorsController.php');
        foreach (["'Membre',", "'Grade',", "'Fonction',", "'Unité d’affectation',", "'Rôle déclaré en jeu',", "'Version du mod',"] as $col) {
            self::assertStringContainsString($col, $c);
        }
        self::assertStringNotContainsString("            'Rôle',\n", $c);
    }

    public function testWiringOperatorFileShowsFunctionNotGameRole(): void
    {
        $v = self::src('views/admin/atak_operators/profile.php');
        self::assertStringContainsString("\$role = \$field(\$live, 'function_label');", $v);
        self::assertStringNotContainsString("'role_label'", $v);
        self::assertStringContainsString("\$row('Fonction', \$role)", $v);
        self::assertStringContainsString('Déclaré en jeu', $v);
    }
}
