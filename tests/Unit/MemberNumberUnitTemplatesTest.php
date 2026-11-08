<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\TenantMemberNumberService;
use App\Support\MemberNumberAnalysis;
use PHPUnit\Framework\TestCase;

/**
 * Matricules : modèles prêts à choisir, modèle par unité, préfixes d’unité expliqués, page en onglets.
 */
final class MemberNumberUnitTemplatesTest extends TestCase
{
    private static function read(string $relative): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
    }

    public function testEveryReadyMadeTemplateIsUsable(): void
    {
        $templates = TenantMemberNumberService::templates();
        self::assertArrayHasKey('simple', $templates);
        self::assertArrayHasKey('unite', $templates);
        foreach ($templates as $key => $t) {
            self::assertNull(TenantMemberNumberService::checkPattern($t['pattern']), 'Modèle inutilisable : ' . $key);
            self::assertSame($key, TenantMemberNumberService::templateKeyFor($t['pattern']));
            self::assertNotSame('', $t['label']);
            self::assertNotSame('', $t['help']);
        }
        self::assertNull(TenantMemberNumberService::templateKeyFor('{PREFIX}/{NUMBER:2}/x'));
    }

    public function testUnitPatternIsRefusedWithAClearMessage(): void
    {
        self::assertNotNull(TenantMemberNumberService::checkPattern(''));
        self::assertStringContainsString('numéro', (string) TenantMemberNumberService::checkPattern('{UNIT}-ALPHA'));
        self::assertStringContainsString('inconnu', (string) TenantMemberNumberService::checkPattern('{FOO}-{NUMBER:3}'));
        self::assertNotNull(TenantMemberNumberService::checkPattern(str_repeat('A', 130) . '{NUMBER}'));
        self::assertNull(TenantMemberNumberService::checkPattern('{UNIT}-{GRADE}-{NUMBER:3}'));
    }

    public function testNumbersFollowingAUnitTemplateAreNotFlaggedAsOffFormat(): void
    {
        $members = [
            ['id' => 1, 'tenant_member_number' => 'G-0001'],
            ['id' => 2, 'tenant_member_number' => 'ALPHA-002'],
        ];
        $without = MemberNumberAnalysis::analyze($members, '{PREFIX}-{NUMBER:4}', 'G', null, 3);
        self::assertSame([2], $without['off_format']);
        $with = MemberNumberAnalysis::analyze($members, '{PREFIX}-{NUMBER:4}', 'G', null, 3, ['{UNIT}-{NUMBER:3}']);
        self::assertSame([], $with['off_format']);
    }

    public function testSchemaStepIsRegisteredAndCreatesTheTenantScopedTable(): void
    {
        $registry = self::read('database/steps/registry.php');
        self::assertStringContainsString("'file' => '4020_member_number_unit_templates.php'", $registry);
        $step = self::read('database/steps/4020_member_number_unit_templates.php');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS tenant_member_number_unit_templates', $step);
        self::assertStringContainsString('PRIMARY KEY (tenant_id, unit_id)', $step);
        self::assertFileExists(dirname(__DIR__, 2) . '/database/steps/4020_member_number_unit_templates.php');
    }

    public function testRouteAndControllerCheckTheUnitBelongsToTheCommunity(): void
    {
        $routes = self::read('routes/web.php');
        self::assertStringContainsString("'/back-office/organisation/matricules/modeles-unite', [OrganizationMemberNumberController::class, 'saveUnitTemplate'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]", $routes);
        $controller = self::read('app/Controllers/Admin/Organization/OrganizationMemberNumberController.php');
        self::assertStringContainsString('public function saveUnitTemplate', $controller);
        self::assertStringContainsString('findById($unitId, $tenantId)', $controller);
        self::assertStringContainsString('guardManage(true, $request)', $controller);
        $repo = self::read('app/Repositories/TenantMemberNumberConfigRepository.php');
        self::assertStringContainsString('WHERE tenant_id = ?', $repo);
        self::assertStringContainsString('tenant_member_number_unit_templates', $repo);
    }

    public function testPageUsesTabsAndNoMoreShowsEmailAddresses(): void
    {
        $view = self::read('views/admin/organization/member_numbers.php');
        self::assertStringContainsString('declare(strict_types=1);', $view);
        foreach (['apercu', 'regles', 'modeles', 'prefixes', 'membres', 'import'] as $tab) {
            self::assertStringContainsString('data-mn-tab="' . $tab . '"', $view);
        }
        self::assertStringContainsString('modeles-unite', $view);
        self::assertStringContainsString('data-mn-dlg="unit-template"', $view);
        self::assertStringNotContainsString('<th>E-MAIL</th>', $view);
        self::assertStringNotContainsString("\$m['email']", $view);
        self::assertStringNotContainsString("\$ir['email']", $view);
    }

    public function testEnglishTranslationsExistForTheNewTexts(): void
    {
        $wanted = array_fill_keys(['Modèles par unité', 'Préfixes d’unité', 'Modèles prêts à choisir', 'Import et historique'], false);
        $file = new \SplFileObject(dirname(__DIR__, 2) . '/lang/en/site_auto.php');
        foreach ($file as $line) {
            foreach (array_keys($wanted) as $fr) {
                if (str_starts_with((string) $line, "    '" . $fr . "' =>")) {
                    $wanted[$fr] = true;
                }
            }
        }
        self::assertSame(array_fill_keys(array_keys($wanted), true), $wanted);
    }
}
