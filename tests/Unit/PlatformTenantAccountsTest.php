<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\System\SystemTenantAccountsController;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

/** Annuaire des communautés : le nombre cliqué et la liste des comptes partagent une seule définition. */
final class PlatformTenantAccountsTest extends TestCase
{
    private function src(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testCounterAndListShareTheSameAccountDefinition(): void
    {
        $tenants = $this->src('app/Repositories/TenantRepository.php');
        self::assertStringContainsString("UserRepository::sqlPlatformAccountFilter('u', 'all')", $tenants);
        $users = $this->src('app/Repositories/UserRepository.php');
        // Liste et décompte passent par le même constructeur de clause.
        self::assertSame(2, substr_count($users, '$this->platformAccountsWhere('));
        self::assertStringContainsString("'u.tenant_id = ? AND ' . self::sqlPlatformAccountFilter('u', \$filter)", $users);
        // Le compteur de l'annuaire est « tous » : même clause que le filtre par défaut de la liste.
        self::assertSame(
            UserRepository::sqlLiteralExcludeTechnicalInternalEmails('u'),
            UserRepository::sqlPlatformAccountFilter('u', 'all')
        );
    }

    public function testFiltersAreWhitelisted(): void
    {
        self::assertSame('all', UserRepository::normalizePlatformAccountFilter("active'; DROP"));
        self::assertSame('all', UserRepository::normalizePlatformAccountFilter(null));
        foreach (UserRepository::PLATFORM_ACCOUNT_FILTERS as $f) {
            self::assertSame($f, UserRepository::normalizePlatformAccountFilter($f));
        }
        self::assertStringContainsString("u.status = 'active'", UserRepository::sqlPlatformAccountFilter('u', 'active'));
        self::assertStringContainsString('IS NULL', UserRepository::sqlPlatformAccountFilter('u', 'never'));
        self::assertSame(UserRepository::sqlPlatformAccountFilter('u', 'all'), UserRepository::sqlPlatformAccountFilter('u', 'zzz'));
        self::assertSame(
            array_keys(SystemTenantAccountsController::filterLabels()),
            UserRepository::PLATFORM_ACCOUNT_FILTERS
        );
    }

    public function testQueriesAreTenantScopedAndPrepared(): void
    {
        $users = $this->src('app/Repositories/UserRepository.php');
        $start = strpos($users, 'function platformAccountsWhere');
        $end = strpos($users, 'function technicalAccountExclusionPredicate');
        $body = substr($users, (int) $start, (int) $end - (int) $start);
        self::assertStringContainsString('u.tenant_id = ?', $body);
        self::assertStringContainsString('$this->pdo()->prepare', $body);
        self::assertStringContainsString("'name' => 'u.display_name'", $body);
        self::assertStringNotContainsString('$_GET', $body);
    }

    public function testRouteIsGetOnlyAndGuardedBySystemAdmin(): void
    {
        $routes = $this->src('routes/web.php');
        $line = "\$router->get('/admin/tenants/{id}/comptes', [\App\Controllers\Admin\System\SystemTenantAccountsController::class, 'index'], [AuthMiddleware::class, SystemAdminMiddleware::class]);";
        self::assertStringContainsString($line, $routes);
        self::assertStringNotContainsString("post('/admin/tenants/{id}/comptes'", $routes);
        $ctl = $this->src('app/Controllers/Admin/System/SystemTenantAccountsController.php');
        self::assertStringContainsString("deny('admin.system')", $ctl);
        self::assertStringContainsString('$tenantId > 1', $ctl);
        // L'identifiant de communauté vient de la route validée, jamais de la requête.
        self::assertStringNotContainsString("query('tenant", $ctl);
    }

    public function testIndexLinksEveryAccountCountToTheList(): void
    {
        $view = $this->src('views/admin/system/tenants_index.php');
        self::assertStringContainsString("'/comptes'", $view);
        self::assertStringContainsString('pa-count-link', $view);
    }

    public function testCsvCellNeutralizesFormulas(): void
    {
        self::assertSame('"\'=SUM(A1)"', SystemTenantAccountsController::csvCell('=SUM(A1)'));
        self::assertSame('"\'+1"', SystemTenantAccountsController::csvCell('+1'));
        self::assertSame('"\'-1"', SystemTenantAccountsController::csvCell('-1'));
        self::assertSame('"\'@x"', SystemTenantAccountsController::csvCell('@x'));
        self::assertSame('"a ""b"""', SystemTenantAccountsController::csvCell('a "b"'));
    }
}
