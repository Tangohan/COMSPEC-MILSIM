<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Audit du back-office, lot 4 (mutualisations techniques) : code mort retiré après recherche exhaustive,
 * aucune route supprimée ni middleware modifié (4.9 : les doubles adresses sont gardées telles quelles).
 */
final class AuditLot4Test extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function src(string $path): string
    {
        return (string) file_get_contents(self::root() . '/' . $path);
    }

    /** @return list<string> */
    private static function phpFiles(string $dir): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root() . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }

    /** 4.10 : la page « liste des classes d'équipement » n'avait ni route ni appelant. */
    public function testOrphanEquipmentIndexIsGone(): void
    {
        self::assertFileDoesNotExist(self::root() . '/views/equipment/index.php');
        $controller = self::src('app/Controllers/Web/EquipmentController.php');
        self::assertStringNotContainsString('function index(', $controller);
        self::assertStringNotContainsString('equipment.index', $controller);
        self::assertStringContainsString('function show(', $controller);
        self::assertStringContainsString("\$router->get('/equipment/{slug}', [EquipmentController::class, 'show'], [AuthMiddleware::class]);", self::src('routes/web.php'));
        foreach (['app', 'routes', 'views', 'config'] as $dir) {
            foreach (self::phpFiles($dir) as $file) {
                self::assertStringNotContainsString("'equipment.index'", (string) file_get_contents($file), $file);
                self::assertStringNotContainsString('equipment/index.php', (string) file_get_contents($file), $file);
            }
        }
    }

    /** 4.10 : PortalNextStepsService::forHub n'avait aucun appelant ; forDashboard reste. */
    public function testUnusedForHubIsGone(): void
    {
        $service = self::src('app/Services/Portal/PortalNextStepsService.php');
        self::assertStringNotContainsString('forHub', $service);
        self::assertStringContainsString('public static function forDashboard(', $service);
        foreach (['app', 'routes', 'views', 'config'] as $dir) {
            foreach (self::phpFiles($dir) as $file) {
                self::assertStringNotContainsString('forHub(', (string) file_get_contents($file), $file);
            }
        }
        self::assertStringContainsString('PortalNextStepsService::forDashboard(', self::src('app/Controllers/Web/HomeController.php'));
    }

    /**
     * 4.10 : ce qui a été laissé en place l'a été parce qu'il est encore référencé.
     * FunctionBasedAccessCatalog nourrit la migration function_based_access_v2 (toujours enregistrée).
     */
    public function testKeptCandidatesStillHaveTheirReferences(): void
    {
        self::assertStringContainsString('FunctionBasedAccessCatalog::roles()', self::src('bootstrap/function_based_access_v2_migration.php'));
        self::assertStringContainsString('function_based_access_v2_migration', self::src('database/steps/3030_function_based_access_v2.php'));
        self::assertFileExists(self::root() . '/app/Services/AccessControl/FunctionBasedAccessCatalog.php');

        // Vues sans route, mais encore lues par des méthodes de contrôleur et par des tests d'actifs : retrait dans une PR dédiée.
        foreach (['index', 'show', 'edit'] as $view) {
            self::assertFileExists(self::root() . '/views/admin/organization/users/' . $view . '.php');
        }
        self::assertFileExists(self::root() . '/views/atak.php');
    }

    /**
     * 4.9 : pour chaque double adresse du plan, les deux routes existent encore avec le même middleware qu'avant.
     * Aucune n'a été convertie en redirection, car le plus strict retirerait un accès légitime (voir la PR).
     */
    public function testDuplicateAddressesKeepTheirRoutesAndMiddlewares(): void
    {
        $routes = self::src('routes/web.php');
        $expected = [
            // Traçabilité du courrier : le bureau courrier (membres du module) et le back-office (administration).
            "\$router->get('/courrier/traceabilite', [CourrierDashboardController::class, 'traceability'], \$mwCourrier);",
            "\$router->get('/back-office/courrier/traceabilite', [CourrierDashboardController::class, 'traceability'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);",
            // Partage de fichiers : page des membres et même page dans la coque du back-office.
            "\$router->get('/partage', [\\App\\Controllers\\Web\\FileShareController::class, 'index'], [AuthMiddleware::class]);",
            "\$router->get('/back-office/partage', [\\App\\Controllers\\Web\\FileShareController::class, 'index'], [AuthMiddleware::class, TenantResourceAdminMiddleware::class]);",
            // Indicateurs : deux adresses, même middleware.
            "\$router->get('/back-office/analytics', [OrganizationAnalyticsController::class, 'index'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);",
            "\$router->get('/back-office/analytics/conversion', [OrganizationAnalyticsController::class, 'index'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);",
            // Première liaison : page du portail (avec la sanction du module) et page dans le back-office.
            "\$router->get('/atak/premiere-liaison', [AtakFirstLinkController::class, 'index'], \$mwAtakMemberOnly);",
            "\$router->get('/back-office/ma-situation/premiere-liaison', [MemberSituationController::class, 'premiereLiaison'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);",
            // Révocation d'un appareil : deux POST.
            "\$router->post('/account/security/devices/revoke', [\\App\\Controllers\\Web\\AtakDeviceSecurityController::class, 'revoke'], [AuthMiddleware::class]);",
            "\$router->post('/back-office/ma-situation/appareils/retirer', [MemberSituationController::class, 'revokeAppareil'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);",
            // Certificats : un POST sous deux adresses, même middleware.
            "\$router->post('/back-office/atak/realisme/certificats', [\\App\\Controllers\\Admin\\AdminAtakRealismController::class, 'storeCertificate'], [AuthMiddleware::class, TenantResourceAdminMiddleware::class]);",
            "\$router->post('/back-office/atak/certificats', [\\App\\Controllers\\Admin\\AdminAtakRealismController::class, 'storeCertificate'], [AuthMiddleware::class, TenantResourceAdminMiddleware::class]);",
        ];
        foreach ($expected as $line) {
            self::assertStringContainsString($line, $routes);
        }
    }

    /** Le bureau courrier renvoie lui-même vers la traçabilité : la rediriger vers le back-office retirerait la page à ses utilisateurs. */
    public function testCourrierDeskLinksToItsOwnTraceability(): void
    {
        self::assertStringContainsString('/courrier/traceabilite', self::src('views/courrier/dashboard.php'));
    }
}
