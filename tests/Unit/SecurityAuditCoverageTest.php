<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\BackOfficeRouteMap as Map;

/**
 * Audit du back-office, lots 7.2 et 7.3 : chaque point de sécurité S1 à S16 (docs/audit-backoffice/README.md) a un
 * test, ou un écart déclaré avec sa raison.
 *
 * COVERAGE relie chaque point au test qui le couvre (SecurityAuditLot1/2/3Test, ou ce fichier) : le test échoue si
 * un test cité n'existe plus. Les points sans test complet (« partial ») sont listés avec ce qui manque ; ils sont
 * repris dans le rapport du lot 7. S14 et S15 n'avaient aucun test : ils sont couverts ici.
 */
final class SecurityAuditCoverageTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    /**
     * @var array<string, array{status: string, tests: list<string>, missing?: string}>
     */
    private const COVERAGE = [
        'S1' => ['status' => 'covered', 'tests' => ['SecurityAuditLot1Test::testJnetKeepsSseIntelligenceBehindTheSseGates']],
        'S2' => ['status' => 'covered', 'tests' => ['SecurityAuditLot1Test::testTacticalMarkersWriteNeedsEditorRightAndCsrf']],
        'S3' => ['status' => 'covered', 'tests' => ['SecurityAuditLot1Test::testRoleplayResetIsAPostWithCsrf']],
        'S4' => ['status' => 'covered', 'tests' => ['SecurityAuditLot1Test::testElevationThatGrantsAccessNeedsAnAccessManager']],
        'S5' => ['status' => 'covered', 'tests' => ['SecurityAuditLot1Test::testCommunitySetupNeedsAManager']],
        'S6' => [
            'status' => 'partial',
            'tests' => ['SecurityAuditLot2Test::testCommunitySettingsWritesCheckARight', 'BackOfficeRouteMapTest::testNoNewControllerReliesOnlyOnTheOrganizationMiddleware'],
            'missing' => 'Seul OrganizationSettingsController est testé. TenantInitialSetupController, OrganizationCommunityController, TenantAlertsController et TenantMiniArticlesController n’ont aucun contrôle de droit ; OrganizationAdminMiddleware laisse passer site.support sur tout /back-office, écritures comprises.',
        ],
        'S7' => [
            'status' => 'partial',
            'tests' => ['SecurityAuditLot3Test::testAccessControlApiReadsAreForAdminsToo'],
            'missing' => 'Le journal d’audit n’est branché que par AuthMiddleware sur les sessions d’intervention ; AccessControlMiddleware n’est attaché à aucune route (code mort ou règle jamais appliquée) et aucun test ne fige la décision.',
        ],
        'S8' => ['status' => 'covered', 'tests' => ['SecurityAuditLot3Test::testQualificationGrantsSayTheyAreNotApplied']],
        'S9' => ['status' => 'covered', 'tests' => ['SecurityAuditLot2Test::testLegacyDutyPositionRoutesNeedStatusRight']],
        'S10' => ['status' => 'covered', 'tests' => ['SecurityAuditLot3Test::testHrDocumentVaultNeedsAFileRight']],
        'S11' => ['status' => 'covered', 'tests' => ['SecurityAuditLot2Test::testDecorationGrantChecksTheCommunity']],
        'S12' => ['status' => 'covered', 'tests' => ['SecurityAuditLot3Test::testOnboardingGradeAndAccessNeedTheirOwnRights']],
        'S13' => ['status' => 'covered', 'tests' => ['SecurityAuditLot3Test::testTacticalIngestNeedsKeyOrManager']],
        'S14' => [
            'status' => 'partial',
            'tests' => ['SecurityAuditCoverageTest::testS14DevSeedIsClosedInProductionAndNeedsCsrf'],
            'missing' => 'La route reste ouverte à tout administrateur de communauté hors production (le plan 0.14 demande admin.system) ; seul le refus en production et le jeton CSRF sont figés ici.',
        ],
        'S15' => ['status' => 'covered', 'tests' => ['SecurityAuditCoverageTest::testS15PublicFeedsAreTokenOnlyAndStayTheOnlyPublicBackOfficeAdjacentRoutes']],
        'S16' => ['status' => 'covered', 'tests' => ['SecurityAuditLot3Test::testPagesNoLongerWriteOnGet', 'GetRoutesDoNotReadPostInputTest']],
    ];

    private static function src(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    public function testEveryAuditPointFromS1ToS16IsAccountedFor(): void
    {
        $expected = array_map(static fn (int $n): string => 'S' . $n, range(1, 16));
        self::assertSame($expected, array_keys(self::COVERAGE));
        $stale = [];
        foreach (self::COVERAGE as $point => $row) {
            self::assertContains($row['status'], ['covered', 'partial'], $point);
            self::assertNotSame([], $row['tests'], $point . ' : aucun test cité');
            if ($row['status'] === 'partial') {
                self::assertArrayHasKey('missing', $row, $point . ' : un point partiel dit ce qui manque');
            }
            foreach ($row['tests'] as $ref) {
                [$class, $method] = array_pad(explode('::', $ref, 2), 2, '');
                $fqcn = 'Tests\\Unit\\' . $class;
                $file = self::ROOT . 'tests/Unit/' . $class . '.php';
                if (!is_file($file)) {
                    $stale[] = $point . ' : fichier ' . $class . ' absent';
                    continue;
                }
                if (!class_exists($fqcn, false)) {
                    require_once $file;
                }
                if ($method !== '' && !method_exists($fqcn, $method)) {
                    $stale[] = $point . ' : ' . $ref . ' n’existe plus';
                }
            }
        }
        self::assertSame([], $stale);
    }

    /** S14 : l'amorçage de développement du Data Inspector ne répond pas en production et exige le jeton CSRF. */
    public function testS14DevSeedIsClosedInProductionAndNeedsCsrf(): void
    {
        $route = Map::match('POST', '/api/athena/v2/dev/seed');
        self::assertNotNull($route);
        self::assertSame(['AuthMiddleware', 'TenantResourceAdminMiddleware'], Map::middlewareNames($route));
        $src = self::src('app/Controllers/Api/AthenaDataApiController.php');
        $seed = substr($src, (int) strpos($src, 'public function devSeed('));
        $production = strpos($seed, 'isAppProduction()');
        $csrf = strpos($seed, 'Csrf::validate');
        self::assertNotFalse($production);
        self::assertNotFalse($csrf);
        self::assertLessThan($csrf, $production, 'le refus en production passe avant toute autre lecture');
        self::assertStringContainsString('not_available_in_production', substr($seed, 0, 400));
        self::assertStringContainsString('tenant_context_required', $seed);
    }

    /**
     * S15 : le flux d'agenda (ICS) et le mur opérationnel partagé sont publics par jeton ; ils sont les seules routes
     * publiques qui touchent aux données de gestion, et chacune lit et vérifie son jeton.
     */
    public function testS15PublicFeedsAreTokenOnlyAndStayTheOnlyPublicBackOfficeAdjacentRoutes(): void
    {
        $ics = Map::match('GET', '/calendrier/abonnement/abc');
        $wall = Map::match('GET', '/tableau-operationnel/p/abc');
        self::assertNotNull($ics);
        self::assertNotNull($wall);
        self::assertSame([], $ics['middleware']);
        self::assertSame([], $wall['middleware']);
        self::assertStringContainsString('->parse($token)', Map::handlerSource($ics));
        self::assertStringContainsString('findActiveBoardShareByToken($token)', Map::handlerSource($wall));
        // Le mur membre et toute l'édition du tableau opérationnel restent derrière une connexion et un droit.
        $member = Map::match('GET', '/tableau-operationnel');
        self::assertNotNull($member);
        self::assertSame(['AuthMiddleware', 'OperationalBoardViewMiddleware'], Map::middlewareNames($member));
        $edit = Map::match('GET', '/back-office/tableau-operationnel/fiche/nouvelle');
        self::assertNotNull($edit);
        self::assertSame(['AuthMiddleware', 'OperationalBoardEditMiddleware'], Map::middlewareNames($edit));
        // Aucune route publique ne se cache sous /back-office, /backoffice ou /admin.
        $public = [];
        foreach (Map::routes() as $route) {
            if (Map::isBackOfficePath($route['path']) && !Map::isRedirect($route) && Map::middlewareNames($route) === []
                && !isset(\App\Support\BackOfficeTabs::FAMILIES[ltrim($route['path'], '/')])) {
                $public[] = Map::key($route);
            }
        }
        self::assertSame([], $public);
    }
}
