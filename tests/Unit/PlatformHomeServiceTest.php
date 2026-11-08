<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Services\Admin\PlatformHomeService;
use App\Support\ShortCache;
use PDO;
use PHPUnit\Framework\TestCase;

final class PlatformHomeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        ShortCache::setEnabled(false);
    }

    protected function tearDown(): void
    {
        ShortCache::setEnabled(null);
    }

    /** Base sans aucune table : toute requête échoue, comme sur un schéma pas encore migré. */
    private function emptyDatabase(): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('query')->willThrowException(new \PDOException('no such table'));
        $pdo->method('prepare')->willThrowException(new \PDOException('no such table'));

        return $pdo;
    }

    public function testMissingTablesGiveUnavailableCardsInsteadOfFailing(): void
    {
        $service = new PlatformHomeService($this->emptyDatabase());

        $actions = [];
        foreach ($service->actions() as $card) {
            $actions[$card['key']] = $card;
        }
        foreach (['mod_reports', 'purge', 'payments', 'abuse'] as $key) {
            self::assertSame('na', $actions[$key]['state'], $key);
            self::assertNull($actions[$key]['n'], $key);
        }
        foreach ($service->figures() as $card) {
            self::assertSame('na', $card['state'], $card['key']);
            self::assertNull($card['n'], $card['key']);
        }
        foreach ($service->health() as $card) {
            self::assertContains($card['state'], ['na', 'ok', 'warn', 'bad', 'info'], $card['key']);
        }
    }

    public function testActionsAreTheFiveSignalsAskedForInOrder(): void
    {
        $keys = array_column((new PlatformHomeService($this->emptyDatabase()))->actions(), 'key');

        self::assertSame(['mod_reports', 'purge', 'payments', 'migrations', 'abuse', 'reports'], $keys);
    }

    public function testEveryCardLeadsToAnAdminPage(): void
    {
        $service = new PlatformHomeService($this->emptyDatabase());
        $cards = array_merge($service->actions(), $service->health(), $service->figures(), PlatformHomeService::quickLinks());
        foreach ($cards as $card) {
            self::assertMatchesRegularExpression('#^(admin(/|\?|$)|back-office/signalements$)#', $card['href'], $card['key']);
        }
    }

    public function testHomeViewKeepsOrderAnchorsAndShowsUnavailableCards(): void
    {
        $service = new PlatformHomeService($this->emptyDatabase());
        $html = Response::view('admin.system.dashboard', [
            'homeTab' => 'vue',
            'homeActions' => $service->actions(),
            'homeHealth' => $service->health(),
            'homeFigures' => $service->figures(),
            'homeQuickLinks' => PlatformHomeService::quickLinks(),
            'adminPlatformEnvLabel' => 'Test',
            'adminPlatformEnvRaw' => 'testing',
        ])->body();

        $positions = [];
        foreach (['id="hub-moderation"', 'id="hub-sante"', 'id="hub-plateforme"', 'ph-quick-title'] as $needle) {
            $pos = strpos($html, $needle);
            self::assertNotFalse($pos, $needle);
            $positions[] = $pos;
        }
        $sorted = $positions;
        sort($sorted);
        self::assertSame($sorted, $positions, 'À traiter, santé, chiffres clés, accès rapides.');
        self::assertStringContainsString('Indisponible', $html);
        self::assertStringContainsString('onglet=sante', $html);
        self::assertStringContainsString('onglet=chiffres', $html);
    }

    public function testTabsKeepOldAnchorsReachable(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/system/dashboard.php');
        foreach (['hub-communication', 'hub-comptes', 'hub-annuaire', 'hub-assistance', 'hub-moderation', 'hub-sante', 'hub-plateforme'] as $anchor) {
            self::assertStringContainsString($anchor, $view, $anchor);
        }
        self::assertSame(['sante', 'chiffres', 'activite'], \App\Controllers\Admin\System\SystemDashboardController::DETAIL_TABS);
    }
}
